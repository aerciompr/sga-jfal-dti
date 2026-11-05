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

/**
 * Consulta quais salas já foram confirmadas ou possuem atendimento ativo hoje por outros peritos.
 */
function obterSalasOcupadasHoje(PDO $pdo, ?string $ignorarPerito = null): array {
    $ocupadas = [];
    
    // 1. Peritos que confirmaram/travaram sala hoje (com expiração de inatividade de 4h)
    $stmt = $pdo->query("SELECT perito, sala, atualizado_em FROM salas_peritos WHERE DATE(atualizado_em) = CURRENT_DATE() AND atualizado_em >= (NOW() - INTERVAL 4 HOUR)");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($ignorarPerito !== null && trim($row['perito']) === trim($ignorarPerito)) continue;
        $ocupadas[$row['sala']] = [
            'perito' => $row['perito'],
            'origem' => 'confirmacao'
        ];
    }
    
    // 2. Salas com perícia ativa hoje (chamada ou atendimento)
    $stmtAtivos = $pdo->query("SELECT sala, perito FROM atendimentos WHERE data_pauta = CURRENT_DATE() AND status IN ('chamada', 'atendimento') AND sala IS NOT NULL");
    while ($row = $stmtAtivos->fetch(PDO::FETCH_ASSOC)) {
        if ($ignorarPerito !== null && trim($row['perito']) === trim($ignorarPerito)) continue;
        $ocupadas[$row['sala']] = [
            'perito' => $row['perito'],
            'origem' => 'atendimento_ativo'
        ];
    }
    
    return $ocupadas;
}

/**
 * Trava uma sala para uso exclusivo do perito no dia.
 */
function travarSalaPerito(PDO $pdo, string $perito, string $sala): void {
    $perito = trim($perito);
    $sala = trim($sala);
    if ($perito === '' || $sala === '') throw new DomainException('Perito e sala são obrigatórios.');

    $ocupadas = obterSalasOcupadasHoje($pdo, $perito);
    if (isset($ocupadas[$sala])) {
        $ocupante = $ocupadas[$sala]['perito'];
        throw new DomainException("A {$sala} já está ocupada hoje pelo perito {$ocupante}. Escolha outra sala disponível.");
    }

    $stmt = $pdo->prepare("INSERT INTO salas_peritos (perito, sala, atualizado_em) VALUES (?, ?, CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE sala = VALUES(sala), atualizado_em = CURRENT_TIMESTAMP");
    $stmt->execute([$perito, $sala]);
    registrarLog($pdo, 'Travar Sala', "Perito: $perito; Sala: $sala");
}

/**
 * Libera a sala que estava travada pelo perito.
 */
function liberarSalaPerito(PDO $pdo, string $perito, ?string $sala = null): void {
    $perito = trim($perito);
    if ($perito === '') return;
    
    if ($sala === null || $sala === '') {
        $stmtSala = $pdo->prepare("SELECT sala FROM salas_peritos WHERE perito = ?");
        $stmtSala->execute([$perito]);
        $sala = (string)$stmtSala->fetchColumn();
    }
    
    if ($sala !== '') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM atendimentos WHERE data_pauta = CURRENT_DATE() AND sala = ? AND perito = ? AND status IN ('chamada', 'atendimento')");
        $stmt->execute([$sala, $perito]);
        if ((int)$stmt->fetchColumn() > 0) {
            throw new DomainException('Não é possível liberar a sala com perícia em andamento. Conclua o atendimento antes.');
        }
    }
    $stmtDel = $pdo->prepare("DELETE FROM salas_peritos WHERE perito = ?");
    $stmtDel->execute([$perito]);
    registrarLog($pdo, 'Liberar Sala', "Perito: $perito; Sala: $sala");
}

function obterCriterioFilaPerito(PDO $pdo, string $perito): string {
    $temCriterio = colunaExiste($pdo, 'salas_peritos', 'criterio_fila');
    if (!$temCriterio) return 'chegada';
    $stmt = $pdo->prepare("SELECT criterio_fila FROM salas_peritos WHERE perito = ?");
    $stmt->execute([$perito]);
    $crit = (string)$stmt->fetchColumn();
    return in_array($crit, ['horario', 'chegada'], true) ? $crit : 'chegada';
}

function salvarCriterioFilaPerito(PDO $pdo, string $perito, string $criterio): void {
    $criterio = in_array($criterio, ['horario', 'chegada'], true) ? $criterio : 'chegada';
    $temCriterio = colunaExiste($pdo, 'salas_peritos', 'criterio_fila');
    if (!$temCriterio) return;
    $stmt = $pdo->prepare("INSERT INTO salas_peritos (perito, sala, criterio_fila, atualizado_em) VALUES (?, 'A definir', ?, CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE criterio_fila = VALUES(criterio_fila), atualizado_em = CURRENT_TIMESTAMP");
    $stmt->execute([$perito, $criterio]);
    registrarLog($pdo, 'Alterar Critério de Fila', "Perito: $perito; Critério: $criterio");
}

function agirAtendimento(PDO $pdo, string $acao, string $perito, string $sala, array $dados): string {
    if ($perito === '') throw new DomainException('Selecione o perito.');
    $profissionalId = ($_SESSION['usuario_role'] ?? '') === 'perito' ? (int)$_SESSION['usuario_id'] : peritoId($pdo, $perito);
    
    // Tratamento direto de trava / liberação de sala
    if ($acao === 'travar_sala') {
        travarSalaPerito($pdo, $perito, $sala);
        return "Sala {$sala} confirmada e travada com sucesso para seus atendimentos de hoje.";
    }
    if ($acao === 'liberar_sala') {
        liberarSalaPerito($pdo, $perito, $sala);
        return "Sala {$sala} liberada com sucesso.";
    }
    if ($acao === 'alternar_criterio_fila') {
        $criterio = (string)($dados['criterio_fila'] ?? 'chegada');
        salvarCriterioFilaPerito($pdo, $perito, $criterio);
        return $criterio === 'horario' ? 'Fila alternada para ordenação por Horário Agendado (PJe).' : 'Fila alternada para ordenação por Ordem de Chegada.';
    }

    $pdo->beginTransaction();
    try {
        // Ordem estável de locks: profissional, sala, atendimento.
        $lock = $pdo->prepare('INSERT IGNORE INTO locks_peritos (perito) VALUES (?)'); $lock->execute([$perito]);
        $lock = $pdo->prepare('SELECT perito FROM locks_peritos WHERE perito = ? FOR UPDATE'); $lock->execute([$perito]);
        $lock = $pdo->prepare('SELECT id FROM salas WHERE nome = ? FOR UPDATE'); $lock->execute([$sala]);
        if (!$lock->fetchColumn()) throw new DomainException('Sala inválida.');

        // Validação anti-conflito de sala
        $ocupadas = obterSalasOcupadasHoje($pdo, $perito);
        if (isset($ocupadas[$sala])) {
            $ocupante = $ocupadas[$sala]['perito'];
            throw new DomainException("A {$sala} já está ocupada hoje pelo perito {$ocupante}. Escolha outra sala.");
        }

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
                $critPerito = obterCriterioFilaPerito($pdo, $perito);
                $temHora = colunaExiste($pdo, 'atendimentos', 'hora_pericia');
                $orderProximo = ($critPerito === 'horario' && $temHora) ? "ORDER BY COALESCE(hora_pericia, '23:59:59') ASC, fila_em ASC, id ASC" : "ORDER BY fila_em ASC, id ASC";
                $stmt = $pdo->prepare($sql . ' ' . $orderProximo . ' LIMIT 1 FOR UPDATE'); $stmt->execute($params); $item = $stmt->fetch();
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
                // Reposicionamento com tolerância inteligente (descer 2 posições na fila)
                $sqlFila = "SELECT id, fila_em FROM atendimentos WHERE data_pauta = CURRENT_DATE() AND status = 'presente' AND id != ?";
                if (($_SESSION['usuario_role'] ?? '') === 'perito') {
                    $sqlFila .= ' AND perito_id = ?';
                    $paramsFila = [$id, $profissionalId];
                } else {
                    $sqlFila .= ' AND perito = ?';
                    $paramsFila = [$id, $perito];
                }
                $sqlFila .= ' ORDER BY fila_em ASC, id ASC LIMIT 3';
                $stmtFila = $pdo->prepare($sqlFila);
                $stmtFila->execute($paramsFila);
                $proximos = $stmtFila->fetchAll(PDO::FETCH_ASSOC);

                if (empty($proximos)) {
                    $novaFilaEm = date('Y-m-d H:i:s') . '.000000';
                } elseif (count($proximos) === 1) {
                    $t1 = strtotime($proximos[0]['fila_em'] ?: date('Y-m-d H:i:s'));
                    $novaFilaEm = date('Y-m-d H:i:s', $t1 + 1) . '.000000';
                } else {
                    $fila2 = $proximos[1]['fila_em'] ?: date('Y-m-d H:i:s');
                    $t2 = strtotime($fila2);
                    if (isset($proximos[2])) {
                        $fila3 = $proximos[2]['fila_em'] ?: date('Y-m-d H:i:s');
                        $t3 = strtotime($fila3);
                        if ($t3 > $t2) {
                            $diff = $t3 - $t2;
                            $novaFilaEm = date('Y-m-d H:i:s', $t2 + max(1, (int)($diff / 2))) . '.500000';
                        } else {
                            $novaFilaEm = date('Y-m-d H:i:s', $t2 + 1) . '.000000';
                        }
                    } else {
                        $novaFilaEm = date('Y-m-d H:i:s', $t2 + 1) . '.000000';
                    }
                }
                $stmt = $pdo->prepare("UPDATE atendimentos SET status = 'presente', sala = NULL, fila_em = ? WHERE id = ?");
                $stmt->execute([$novaFilaEm, $id]);
                $mensagem = 'Paciente ausente reposicionado com tolerância (desceu 2 posições na fila).';
            } elseif (in_array($acao, ['desistencia', 'marcar_desistencia'], true)) {
                $stmt = $pdo->prepare("UPDATE atendimentos SET status = 'desistente', sala = NULL, finalizado_em = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$id]);
                $mensagem = 'Desistência registrada. O periciado foi retirado da fila.';
            } else throw new DomainException('Transição não permitida para o estado atual.');
        }
        registrarLog($pdo, $acao, "Perito: $perito; Sala: $sala", $id);
        $pdo->commit(); return $mensagem;
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}

/**
 * Retorna o resumo consolidado dos peritos ativos, salas, contadores e filas do dia.
 */
function obterResumoFilasPeritos(PDO $pdo, string $data): array {
    $stmtPeritos = $pdo->prepare("
        SELECT DISTINCT perito, perito_id 
        FROM atendimentos 
        WHERE data_pauta = ? AND perito IS NOT NULL AND perito != ''
        ORDER BY perito ASC
    ");
    $stmtPeritos->execute([$data]);
    $peritos = $stmtPeritos->fetchAll(PDO::FETCH_ASSOC);

    $temCriterio = colunaExiste($pdo, 'salas_peritos', 'criterio_fila');
    $salasMap = [];
    $criteriosMap = [];
    if ($temCriterio) {
        $stmtSalas = $pdo->query("SELECT perito, sala, criterio_fila FROM salas_peritos");
        while ($r = $stmtSalas->fetch(PDO::FETCH_ASSOC)) {
            $salasMap[$r['perito']] = $r['sala'];
            $criteriosMap[$r['perito']] = $r['criterio_fila'];
        }
    } else {
        $salasMap = $pdo->query("SELECT perito, sala FROM salas_peritos")->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    $temHora = colunaExiste($pdo, 'atendimentos', 'hora_pericia');
    $resultado = [];

    foreach ($peritos as $p) {
        $peritoNome = (string)$p['perito'];
        $peritoId = !empty($p['perito_id']) ? (int)$p['perito_id'] : null;

        $sala = $salasMap[$peritoNome] ?? 'A definir';
        $criterioFila = $criteriosMap[$peritoNome] ?? 'chegada';

        // Paciente em atendimento ou chamada ativa
        $stmtAtivo = $pdo->prepare("
            SELECT id, nome, processo, senha, sala, status, chamadas_count, chamado_em, iniciado_em
            FROM atendimentos
            WHERE data_pauta = ? AND status IN ('chamada', 'atendimento') 
              AND (perito_id = ? OR (? IS NULL AND perito = ?))
            ORDER BY chamado_em DESC LIMIT 1
        ");
        $stmtAtivo->execute([$data, $peritoId, $peritoId, $peritoNome]);
        $ativo = $stmtAtivo->fetch(PDO::FETCH_ASSOC) ?: null;

        if ($ativo && !empty($ativo['sala'])) {
            $sala = $ativo['sala'];
        }

        // Contadores
        $stmtCont = $pdo->prepare("
            SELECT status, COUNT(*) as qtd
            FROM atendimentos
            WHERE data_pauta = ? AND (perito_id = ? OR (? IS NULL AND perito = ?))
            GROUP BY status
        ");
        $stmtCont->execute([$data, $peritoId, $peritoId, $peritoNome]);
        $contagens = $stmtCont->fetchAll(PDO::FETCH_KEY_PAIR);

        $totAgendados = (int)($contagens['agendado'] ?? 0);
        $totPresentes = (int)($contagens['presente'] ?? 0);
        $totConcluidos = (int)($contagens['concluido'] ?? 0);
        $totDesistentes = (int)($contagens['desistente'] ?? 0);
        $totEmAtendimento = (int)($contagens['chamada'] ?? 0) + (int)($contagens['atendimento'] ?? 0);

        // Fila ordenada de espera conforme o critério ativo do perito
        $orderFila = ($criterioFila === 'horario' && $temHora)
            ? "COALESCE(hora_pericia, '23:59:59') ASC, fila_em ASC, id ASC"
            : "fila_em ASC, id ASC";

        $colsHora = $temHora ? ", hora_pericia" : "";
        $stmtFila = $pdo->prepare("
            SELECT id, nome, processo, cpf, senha, status, chegada_em, fila_em, chamadas_count $colsHora
            FROM atendimentos
            WHERE data_pauta = ? AND status = 'presente'
              AND (perito_id = ? OR (? IS NULL AND perito = ?))
            ORDER BY $orderFila
        ");
        $stmtFila->execute([$data, $peritoId, $peritoId, $peritoNome]);
        $filaRaw = $stmtFila->fetchAll(PDO::FETCH_ASSOC);

        $fila = [];
        $pos = 1;
        foreach ($filaRaw as $f) {
            $fCount = (int)$f['chamadas_count'];
            $horaFormatada = (!empty($f['hora_pericia'])) ? date('H:i', strtotime($f['hora_pericia'])) : null;
            $fila[] = [
                'id' => (int)$f['id'],
                'posicao' => $pos,
                'pessoas_na_frente' => $pos - 1,
                'nome' => (string)($f['nome'] ?: 'Sem Nome'),
                'processo' => (string)($f['processo'] ?: 'Sem processo'),
                'cpf' => (string)($f['cpf'] ?? ''),
                'senha' => (string)$f['senha'],
                'chegada_em' => !empty($f['chegada_em']) ? date('H:i:s', strtotime($f['chegada_em'])) : '--:--:--',
                'hora_pericia' => $horaFormatada,
                'chamadas_count' => $fCount,
                'ja_chamado' => $fCount > 0,
            ];
            $pos++;
        }

        $resultado[] = [
            'perito' => $peritoNome,
            'perito_id' => $peritoId,
            'sala' => $sala,
            'criterio_fila' => $criterioFila,
            'totais' => [
                'agendados' => $totAgendados,
                'presentes' => $totPresentes,
                'concluidos' => $totConcluidos,
                'desistentes' => $totDesistentes,
                'em_atendimento' => $totEmAtendimento,
                'total' => $totAgendados + $totPresentes + $totConcluidos + $totDesistentes + $totEmAtendimento
            ],
            'atendimento_ativo' => $ativo,
            'fila' => $fila,
        ];
    }

    return $resultado;
}

/**
 * Consulta indicadores executivos e detalhamento de produtividade por médico para o Dashboard.
 */
function obterMetricasDia(PDO $pdo, string $data): array {
    $stmtGeral = $pdo->prepare("
        SELECT
            COUNT(*) as total_agendados,
            SUM(CASE WHEN chegada_em IS NOT NULL THEN 1 ELSE 0 END) as presentes,
            SUM(CASE WHEN status = 'concluido' THEN 1 ELSE 0 END) as concluidos,
            SUM(CASE WHEN status IN ('chamada', 'atendimento') THEN 1 ELSE 0 END) as em_atendimento,
            SUM(CASE WHEN status = 'presente' THEN 1 ELSE 0 END) as aguardando,
            SUM(CASE WHEN status IN ('nao_compareceu', 'desistente') THEN 1 ELSE 0 END) as ausentes,
            AVG(CASE WHEN chegada_em IS NOT NULL AND (primeira_chamada_em IS NOT NULL OR iniciado_em IS NOT NULL) 
                     THEN TIMESTAMPDIFF(MINUTE, chegada_em, COALESCE(primeira_chamada_em, iniciado_em)) END) as media_espera_min,
            AVG(CASE WHEN iniciado_em IS NOT NULL AND finalizado_em IS NOT NULL 
                     THEN TIMESTAMPDIFF(MINUTE, iniciado_em, finalizado_em) END) as media_pericia_min
        FROM atendimentos
        WHERE data_pauta = ? AND status <> 'cancelado'
    ");
    $stmtGeral->execute([$data]);
    $geral = $stmtGeral->fetch(PDO::FETCH_ASSOC) ?: [];
    
    $totalAgendados = (int)($geral['total_agendados'] ?? 0);
    $presentes = (int)($geral['presentes'] ?? 0);
    $concluidos = (int)($geral['concluidos'] ?? 0);
    $emAtendimento = (int)($geral['em_atendimento'] ?? 0);
    $aguardando = (int)($geral['aguardando'] ?? 0);
    $ausentes = (int)($geral['ausentes'] ?? 0);
    $mediaEspera = round((float)($geral['media_espera_min'] ?? 0), 0);
    $mediaPericia = round((float)($geral['media_pericia_min'] ?? 0), 0);
    
    $taxaAbsenteismo = $totalAgendados > 0 ? round(($ausentes / $totalAgendados) * 100, 1) : 0.0;
    $taxaComparecimento = $totalAgendados > 0 ? round(($presentes / $totalAgendados) * 100, 1) : 0.0;
    
    $stmtPeritos = $pdo->prepare("
        SELECT
            perito,
            COUNT(*) as agendados,
            SUM(CASE WHEN chegada_em IS NOT NULL THEN 1 ELSE 0 END) as presentes,
            SUM(CASE WHEN status = 'concluido' THEN 1 ELSE 0 END) as concluidos,
            SUM(CASE WHEN status IN ('chamada', 'atendimento') THEN 1 ELSE 0 END) as em_atendimento,
            SUM(CASE WHEN status = 'presente' THEN 1 ELSE 0 END) as aguardando,
            SUM(CASE WHEN status IN ('nao_compareceu', 'desistente') THEN 1 ELSE 0 END) as ausentes,
            ROUND(AVG(CASE WHEN iniciado_em IS NOT NULL AND finalizado_em IS NOT NULL 
                           THEN TIMESTAMPDIFF(MINUTE, iniciado_em, finalizado_em) END), 0) as tempo_medio_min
        FROM atendimentos
        WHERE data_pauta = ? AND status <> 'cancelado' AND perito IS NOT NULL AND perito <> ''
        GROUP BY perito
        ORDER BY perito ASC
    ");
    $stmtPeritos->execute([$data]);
    $peritos = $stmtPeritos->fetchAll(PDO::FETCH_ASSOC);
    
    $salasMap = $pdo->query("SELECT perito, sala FROM salas_peritos")->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach ($peritos as &$p) {
        $p['sala'] = $salasMap[$p['perito']] ?? 'Não definida';
    }
    unset($p);
    
    return [
        'data' => $data,
        'totais' => [
            'agendados' => $totalAgendados,
            'presentes' => $presentes,
            'concluidos' => $concluidos,
            'em_atendimento' => $emAtendimento,
            'aguardando' => $aguardando,
            'ausentes' => $ausentes,
            'taxa_absenteismo' => $taxaAbsenteismo,
            'taxa_comparecimento' => $taxaComparecimento,
            'media_espera_min' => $mediaEspera,
            'media_pericia_min' => $mediaPericia,
        ],
        'peritos' => $peritos,
    ];
}
