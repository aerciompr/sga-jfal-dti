<?php
declare(strict_types=1);

function getNextSenha(PDO $pdo): string {
    if (!$pdo->inTransaction()) throw new LogicException('Sequência exige transação.');
    $pdo->exec("INSERT IGNORE INTO sequencia_diaria_senhas (data_pauta, ultimo_numero) VALUES (CURRENT_DATE(), 0)");
    $pdo->exec('UPDATE sequencia_diaria_senhas SET ultimo_numero = LAST_INSERT_ID(ultimo_numero + 1) WHERE data_pauta = CURRENT_DATE()');
    return 'PE-' . sprintf('%03d', (int)$pdo->lastInsertId());
}

function confirmarPresencas(PDO $pdo, array $ids, string $role): int {
    $ids = array_values(array_unique(array_map('intval', $ids))); sort($ids);
    $pdo->beginTransaction();
    try {
        $total = 0;
        $check = $pdo->prepare('SELECT * FROM atendimentos WHERE id = ? FOR UPDATE');
        $update = $pdo->prepare("UPDATE atendimentos SET status = 'presente', senha = ?, chegada_em = CURRENT_TIMESTAMP, fila_em = CURRENT_TIMESTAMP(6) WHERE id = ? AND status = 'agendado'");
        foreach ($ids as $id) {
            $check->execute([$id]); $item = $check->fetch();
            if (!$item) throw new DomainException('Agendamento inexistente.');
            if ($item['data_pauta'] !== date('Y-m-d')) throw new DomainException('Presença só pode ser confirmada no dia da pauta.');
            if ($role === 'recepcao' && !isPautaLiberada($pdo, $item['data_pauta'])) throw new DomainException('Pauta ainda não homologada.');
            if ($item['status'] !== 'agendado') continue; // Repetição é idempotente.
            $senha = getNextSenha($pdo); $update->execute([$senha, $id]);
            registrarLog($pdo, 'Confirmar Presença', "Paciente: {$item['nome']}, Senha: $senha", $id); $total++;
        }
        $pdo->commit(); return $total;
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}

function eventoChamada(PDO $pdo, int $id): void {
    $stmt = $pdo->prepare('INSERT INTO eventos_chamada (atendimento_id, nome, senha, sala, perito, data_pauta) SELECT id, nome, senha, sala, perito, data_pauta FROM atendimentos WHERE id = ?');
    $stmt->execute([$id]);
}

function agirAtendimento(PDO $pdo, string $acao, string $perito, string $sala, array $dados): string {
    if ($perito === '') throw new DomainException('Selecione o perito.');
    $profissionalId = ($_SESSION['usuario_role'] ?? '') === 'perito' ? (int)$_SESSION['usuario_id'] : peritoId($pdo, $perito);
    $pdo->beginTransaction();
    try {
        // Ordem estável de locks: profissional, sala, atendimento.
        $lock = $pdo->prepare('INSERT IGNORE INTO locks_peritos (perito) VALUES (?)'); $lock->execute([$perito]);
        $lock = $pdo->prepare('SELECT perito FROM locks_peritos WHERE perito = ? FOR UPDATE'); $lock->execute([$perito]);
        $lock = $pdo->prepare('SELECT id FROM salas WHERE nome = ? FOR UPDATE'); $lock->execute([$sala]);
        if (!$lock->fetchColumn()) throw new DomainException('Sala inválida.');
        $stmt = $pdo->prepare("SELECT * FROM atendimentos WHERE data_pauta = CURRENT_DATE() AND status IN ('chamada','atendimento') AND (sala = ? OR perito = ? OR perito_id = ?) ORDER BY id FOR UPDATE");
        $stmt->execute([$sala, $perito, $profissionalId]); $ativos = $stmt->fetchAll();
        $novo = in_array($acao, ['chamar_proximo', 'chamar_especifico', 'chamar_avulsa'], true);
        if ($novo) {
            if ($ativos) throw new DomainException('A sala ou o perito já possui atendimento ativo. Conclua-o antes de chamar outro.');
            if ($acao === 'chamar_avulsa') {
                $nome = trim((string)($dados['nome_avulso'] ?? ''));
                if ($nome === '' || mb_strlen($nome) > 100) throw new DomainException('Informe o nome (até 100 caracteres).');
                $senha = getNextSenha($pdo);
                $stmt = $pdo->prepare("INSERT INTO atendimentos (nome, senha, sala, perito, perito_id, status, data_pauta, chegada_em, chamado_em, primeira_chamada_em, chamadas_count) VALUES (?, ?, ?, ?, ?, 'chamada', CURRENT_DATE(), CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 1)");
                $stmt->execute([$nome, $senha, $sala, $perito, $profissionalId]); $id = (int)$pdo->lastInsertId();
            } else {
                $sql = "SELECT * FROM atendimentos WHERE data_pauta = CURRENT_DATE() AND status = 'presente'";
                if (($_SESSION['usuario_role'] ?? '') === 'perito') { $sql .= ' AND perito_id = ?'; $params = [$profissionalId]; }
                else { $sql .= ' AND perito = ?'; $params = [$perito]; }
                if ($acao === 'chamar_especifico') { $sql .= ' AND id = ?'; $params[] = (int)($dados['id'] ?? 0); }
                $stmt = $pdo->prepare($sql . ' ORDER BY fila_em, id LIMIT 1 FOR UPDATE'); $stmt->execute($params); $item = $stmt->fetch();
                if (!$item) throw new DomainException('Não há paciente elegível na fila deste perito.');
                $id = (int)$item['id'];
                $stmt = $pdo->prepare("UPDATE atendimentos SET status = 'chamada', sala = ?, chamado_em = CURRENT_TIMESTAMP, primeira_chamada_em = COALESCE(primeira_chamada_em, CURRENT_TIMESTAMP), chamadas_count = chamadas_count + 1 WHERE id = ? AND status = 'presente'");
                $stmt->execute([$sala, $id]);
            }
            eventoChamada($pdo, $id); $mensagem = 'Paciente chamado.';
        } else {
            $proprios = array_values(array_filter($ativos, static fn($a) => $a['sala'] === $sala && (($_SESSION['usuario_role'] ?? '') === 'perito' ? (int)$a['perito_id'] === (int)$_SESSION['usuario_id'] : $a['perito'] === $perito)));
            if (count($proprios) !== 1) throw new DomainException('Nenhum atendimento único deste perito nesta sala.');
            $item = $proprios[0]; $id = (int)$item['id'];
            // Impede que formulário antigo opere sobre o próximo paciente da sala.
            if ((int)($dados['atendimento_id'] ?? 0) !== $id) throw new DomainException('O atendimento mudou. Atualize a página.');
            if ($acao === 'chamar_novamente' && $item['status'] === 'chamada') {
                $stmt = $pdo->prepare('UPDATE atendimentos SET chamado_em = CURRENT_TIMESTAMP, chamadas_count = chamadas_count + 1 WHERE id = ?');
                $stmt->execute([$id]); eventoChamada($pdo, $id); $mensagem = 'Chamada repetida.';
            } elseif ($acao === 'iniciar_atendimento' && $item['status'] === 'chamada') {
                $stmt = $pdo->prepare("UPDATE atendimentos SET status = 'atendimento', iniciado_em = CURRENT_TIMESTAMP WHERE id = ?"); $stmt->execute([$id]); $mensagem = 'Perícia iniciada.';
            } elseif ($acao === 'concluir' && $item['status'] === 'atendimento') {
                $stmt = $pdo->prepare("UPDATE atendimentos SET status = 'concluido', finalizado_em = CURRENT_TIMESTAMP WHERE id = ?"); $stmt->execute([$id]); $mensagem = 'Perícia concluída.';
            } elseif ($acao === 'nao_compareceu' && $item['status'] === 'chamada') {
                $stmt = $pdo->prepare("UPDATE atendimentos SET status = 'presente', sala = NULL, fila_em = CURRENT_TIMESTAMP(6) WHERE id = ?"); $stmt->execute([$id]); $mensagem = 'Paciente retornou ao fim da fila; chamadas e chegada original preservadas.';
            } else throw new DomainException('Transição não permitida para o estado atual.');
        }
        registrarLog($pdo, $acao, "Perito: $perito; Sala: $sala", $id);
        $pdo->commit(); return $mensagem;
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}
