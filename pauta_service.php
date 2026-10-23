<?php
declare(strict_types=1);

function validarCpf(string $cpf): bool {
    if ($cpf === '') return true;
    $n = preg_replace('/\D/', '', $cpf);
    if (strlen($n) !== 11 || preg_match('/^(\d)\1{10}$/', $n) || preg_match('/[^\d.\-\s]/', $cpf)) return false;
    for ($t = 9; $t < 11; $t++) {
        $soma = 0; for ($i = 0; $i < $t; $i++) $soma += (int)$n[$i] * ($t + 1 - $i);
        if ((int)$n[$t] !== (($soma * 10 % 11) % 10)) return false;
    }
    return true;
}

function peritoId(PDO $pdo, string $nome): ?int {
    $s = $pdo->prepare("SELECT id FROM usuarios WHERE role = 'perito' AND nome = ?"); $s->execute([$nome]); $ids = $s->fetchAll(PDO::FETCH_COLUMN);
    return count($ids) === 1 ? (int)$ids[0] : null;
}

function validarAgendamento(PDO $pdo, array $dados, string $data, ?int $ignorar = null): array {
    $nome = preg_replace('/\s+/u', ' ', trim((string)($dados['nome'] ?? '')));
    $processo = trim((string)($dados['processo'] ?? ''));
    $perito = trim((string)($dados['perito'] ?? ''));
    $cpf = trim((string)($dados['cpf'] ?? ''));
    if ($nome === '' || mb_strlen($nome) > 100 || strlen($processo) > 50 || $perito === '' || mb_strlen($perito) > 100 || !validarCpf($cpf)) throw new DomainException('Confira nome, processo, perito e CPF.');
    if ($cpf !== '') $cpf = preg_replace('/^(\d{3})(\d{3})(\d{3})(\d{2})$/', '$1.$2.$3-$4', preg_replace('/\D/', '', $cpf));
    if ($processo !== '') {
        $s = $pdo->prepare("SELECT id, processo, perito FROM atendimentos WHERE data_pauta = ? AND status <> 'cancelado' AND id <> ?"); $s->execute([$data, $ignorar ?? 0]);
        foreach ($s as $item) if (simplifyName($item['processo'] ?? '') === simplifyName($processo) && simplifyNameWithSpaces($item['perito'] ?? '') === simplifyNameWithSpaces($perito)) throw new DomainException('Agendamento duplicado para este processo, data e perito.');
    }
    return [$nome, $processo ?: null, $perito, $cpf ?: null, peritoId($pdo, $perito)];
}

function operarPauta(PDO $pdo, string $acao, array $dados, string $dataFiltro): string {
    if (!in_array($_SESSION['usuario_role'], ['admin','supervisor'], true)) throw new DomainException('Acesso negado.');
    if ($acao==='mover_pauta' && ($dados['mover_tipo']??'')==='mes' && dataValida((string)($dados['nova_data']??''),'Y-m')) $dados['nova_data'].='-01';
    if ($acao === 'mover_pauta' && !dataValida((string)($dados['nova_data'] ?? ''))) throw new DomainException('Data de destino inválida.');
    if (!$pdo->query("SELECT GET_LOCK('sga_importacao', 15)")->fetchColumn()) throw new DomainException('Pauta em atualização. Tente novamente.');
    $pdo->beginTransaction();
    try {
        if (in_array($acao, ['liberar_pauta','bloquear_pauta'], true)) {
            $data = $dados['data_alvo'] ?? '';
            if (!dataValida($data)) throw new DomainException('Data inválida.');
            if ($acao === 'liberar_pauta') {
                $s = $pdo->prepare('INSERT IGNORE INTO pautas_liberadas (data_pauta, usuario_id) VALUES (?, ?)'); $s->execute([$data, $_SESSION['usuario_id']]);
            } else { $s = $pdo->prepare('DELETE FROM pautas_liberadas WHERE data_pauta = ?'); $s->execute([$data]); }
            registrarLog($pdo, $acao, "Data: $data"); $mensagem = 'Homologação atualizada.';
        } elseif ($acao === 'set_yt_url') {
            if ($_SESSION['usuario_role'] !== 'admin') throw new DomainException('Apenas administrador pode alterar o vídeo.');
            $url = trim((string)($dados['yt_url'] ?? ''));
            if (fonteYouTube($url)===null) throw new DomainException('Use um link HTTPS válido de vídeo ou playlist do YouTube.');
            set_config($pdo, 'youtube_url', $url); registrarLog($pdo, $acao, 'Vídeo ou playlist da TV atualizado.'); $mensagem = 'Conteúdo da TV atualizado.';
        } elseif ($acao === 'agendar_unico') {
            if (!dataValida($dataFiltro) || $dataFiltro < date('Y-m-d')) throw new DomainException('Agende para hoje ou data futura.');
            [$nome,$processo,$perito,$cpf,$peritoId] = validarAgendamento($pdo, $dados, $dataFiltro);
            $s = $pdo->prepare("INSERT INTO atendimentos (nome, processo, perito, cpf, perito_id, data_pauta, status) VALUES (?, ?, ?, ?, ?, ?, 'agendado')"); $s->execute([$nome,$processo,$perito,$cpf,$peritoId,$dataFiltro]);
            registrarLog($pdo, $acao, json_encode(array_diff_key($dados,['csrf'=>1]), JSON_UNESCAPED_UNICODE), (int)$pdo->lastInsertId()); $mensagem = 'Agendamento cadastrado.';
        } else {
            $ids = isset($dados['id']) ? [(int)$dados['id']] : array_values(array_unique(array_map('intval', (array)($dados['selecionados'] ?? []))));
            $params = []; $where = '';
            $tipo = $dados['mover_tipo'] ?? $dados['perito_tipo'] ?? '';
            if ($acao === 'excluir_periodo') {
                if (($dados['periodo_tipo'] ?? '') === 'dia' && dataValida($dados['data_alvo'] ?? '')) { $where = 'data_pauta = ?'; $params = [$dados['data_alvo']]; }
                elseif (($dados['periodo_tipo'] ?? '') === 'mes' && dataValida($dados['mes_alvo'] ?? '', 'Y-m')) { $where = 'data_pauta >= ? AND data_pauta < DATE_ADD(?, INTERVAL 1 MONTH)'; $params = [$dados['mes_alvo'].'-01',$dados['mes_alvo'].'-01']; }
            } elseif ($acao === 'limpar_pauta_hoje') { $where = 'data_pauta = ?'; $params = [$dataFiltro]; }
            elseif ($acao === 'mover_pauta' && $tipo === 'dia') { $where = 'data_pauta = ?'; $params = [$dados['mover_data_origem'] ?? '']; }
            elseif ($acao === 'mover_pauta' && $tipo === 'mes') { $where = 'data_pauta >= ? AND data_pauta < DATE_ADD(?, INTERVAL 1 MONTH)'; $params = [($dados['mover_mes_origem'] ?? '').'-01',($dados['mover_mes_origem'] ?? '').'-01']; }
            elseif ($acao === 'alterar_perito_lote' && $tipo === 'dia') { $where = 'data_pauta = ?'; $params = [$dados['perito_data_origem'] ?? '']; }
            elseif ($ids) { $where = 'id IN (' . implode(',', array_fill(0,count($ids),'?')) . ')'; $params = $ids; }
            if ($where === '') throw new DomainException('Selecione registros ou um período válido.');
            $s = $pdo->prepare("SELECT * FROM atendimentos WHERE $where ORDER BY id FOR UPDATE"); $s->execute($params); $itens = $s->fetchAll();
            $total = 0;
            foreach ($itens as $item) {
                $estado = $item['status']; $id = (int)$item['id'];
                if ($acao === 'editar_atendimento') {
                    if ($estado !== 'agendado') throw new DomainException('Só é possível editar agendamentos ainda não confirmados.');
                    [$nome,$processo,$perito,$cpf,$peritoId] = validarAgendamento($pdo,$dados,$item['data_pauta'],$id);
                    $u = $pdo->prepare("UPDATE atendimentos SET nome=?, processo=?, perito=?, cpf=?, perito_id=? WHERE id=? AND status='agendado'"); $u->execute([$nome,$processo,$perito,$cpf,$peritoId,$id]);
                } elseif ($acao === 'mover_pauta' || $acao === 'alterar_perito_lote') {
                    if ($estado !== 'agendado') continue;
                    $data = $item['data_pauta']; $perito = $item['perito'];
                    if ($acao === 'mover_pauta') {
                        $data = $dados['nova_data'] ?? '';
                        if (!dataValida($data) || $data < date('Y-m-d')) throw new DomainException('Use uma data futura válida.');
                        if ($tipo === 'mes') {
                            $base = new DateTimeImmutable(substr($data,0,7).'-01');
                            $dia = min((int)substr($item['data_pauta'],8,2),(int)$base->format('t'));
                            $data = $base->setDate((int)$base->format('Y'),(int)$base->format('m'),$dia)->format('Y-m-d');
                        }
                    } else $perito = trim((string)($dados['novo_perito'] ?? ''));
                    validarAgendamento($pdo, array_merge($item,['perito'=>$perito]), $data,$id);
                    $u=$pdo->prepare('UPDATE atendimentos SET data_pauta=?, perito=?, perito_id=? WHERE id=?'); $u->execute([$data,$perito,peritoId($pdo,$perito),$id]);
                } elseif (in_array($acao,['remover_chegada_lote','remover','remover_multiplos'],true) && $estado === 'presente') {
                    if ((int)$item['chamadas_count'] > 0) throw new DomainException('Atendimento com chamadas anteriores deve ser preservado.');
                    $u=$pdo->prepare("UPDATE atendimentos SET status='agendado', senha='---', chegada_em=NULL, fila_em=NULL, chamado_em=NULL, primeira_chamada_em=NULL, iniciado_em=NULL, finalizado_em=NULL, sala=NULL, chamadas_count=0 WHERE id=?"); $u->execute([$id]);
                } elseif (in_array($acao,['remover','remover_multiplos','excluir_selecionados','excluir_periodo','limpar_pauta_hoje'],true) && $estado === 'agendado') {
                    $u=$pdo->prepare("UPDATE atendimentos SET status='cancelado' WHERE id=?"); $u->execute([$id]);
                } else continue;
                registrarLog($pdo,$acao,json_encode(['antes'=>$item,'parametros'=>array_diff_key($dados,['csrf'=>1,'selecionados'=>1])],JSON_UNESCAPED_UNICODE),$id); $total++;
            }
            $mensagem = "$total registros atualizados. Atendimentos iniciados/concluídos foram preservados.";
        }
        $pdo->commit(); return $mensagem;
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    finally { $pdo->query("SELECT RELEASE_LOCK('sga_importacao')"); }
}
