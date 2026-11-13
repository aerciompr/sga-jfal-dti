<?php
declare(strict_types=1);

require_once __DIR__ . '/banco.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['usuario_role'], ['admin', 'perito'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acesso negado.']);
    exit;
}

try {
    $user_role = (string)$_SESSION['usuario_role'];
    $ownerId = (int)$_SESSION['usuario_id'];
    $ownerAdmin = $user_role === 'admin' ? 1 : 0;

    $subId = subsecaoAtivaId($pdo);
    $temSubAtend = colunaExiste($pdo, 'atendimentos', 'subsecao_id');
    $temSubSalasP = colunaExiste($pdo, 'salas_peritos', 'subsecao_id');

    if ($user_role === 'perito') {
        $perito_selecionado = (string)($_SESSION['perito_selecionado'] ?? $_SESSION['usuario_nome'] ?? $_SESSION['usuario_name'] ?? '');
    } else {
        $perito_selecionado = (string)($_SESSION['perito_selecionado'] ?? '');
        if ($perito_selecionado === '') {
            if ($temSubAtend) {
                $stmtAtivo = $pdo->prepare("SELECT DISTINCT perito FROM atendimentos WHERE subsecao_id = ? AND data_pauta = CURRENT_DATE() AND status IN ('chamada', 'atendimento') AND perito IS NOT NULL AND perito != '' LIMIT 1");
                $stmtAtivo->execute([$subId]);
            } else {
                $stmtAtivo = $pdo->query("SELECT DISTINCT perito FROM atendimentos WHERE data_pauta = CURRENT_DATE() AND status IN ('chamada', 'atendimento') AND perito IS NOT NULL AND perito != '' LIMIT 1");
            }
            $peritoAtivo = $stmtAtivo->fetchColumn();
            if ($peritoAtivo) {
                $perito_selecionado = (string)$peritoAtivo;
            } else {
                if ($temSubSalasP) {
                    $stmtSalaP = $pdo->prepare("SELECT DISTINCT perito FROM salas_peritos WHERE subsecao_id = ? AND DATE(atualizado_em) = CURRENT_DATE() LIMIT 1");
                    $stmtSalaP->execute([$subId]);
                } else {
                    $stmtSalaP = $pdo->query("SELECT DISTINCT perito FROM salas_peritos WHERE DATE(atualizado_em) = CURRENT_DATE() LIMIT 1");
                }
                $peritoSala = $stmtSalaP->fetchColumn();
                if ($peritoSala) {
                    $perito_selecionado = (string)$peritoSala;
                } else {
                    if ($temSubAtend) {
                        $stmtPrimeiro = $pdo->prepare("SELECT DISTINCT perito FROM atendimentos WHERE subsecao_id = ? AND data_pauta = CURRENT_DATE() AND perito IS NOT NULL AND perito != '' ORDER BY perito ASC LIMIT 1");
                        $stmtPrimeiro->execute([$subId]);
                    } else {
                        $stmtPrimeiro = $pdo->query("SELECT DISTINCT perito FROM atendimentos WHERE data_pauta = CURRENT_DATE() AND perito IS NOT NULL AND perito != '' ORDER BY perito ASC LIMIT 1");
                    }
                    $peritoPrimeiro = $stmtPrimeiro->fetchColumn();
                    if ($peritoPrimeiro) $perito_selecionado = (string)$peritoPrimeiro;
                }
            }
            if ($perito_selecionado !== '') {
                $_SESSION['perito_selecionado'] = $perito_selecionado;
            }
        }
    }

    $sala_confirmada = false;
    $sala = (string)($_SESSION['sala'] ?? 'Sala 1');
    if ($perito_selecionado !== '') {
        if ($temSubSalasP) {
            $stmtSala = $pdo->prepare("SELECT sala FROM salas_peritos WHERE subsecao_id = ? AND LOWER(TRIM(perito)) = LOWER(TRIM(?)) AND DATE(atualizado_em) = CURRENT_DATE()");
            $stmtSala->execute([$subId, $perito_selecionado]);
        } else {
            $stmtSala = $pdo->prepare("SELECT sala FROM salas_peritos WHERE LOWER(TRIM(perito)) = LOWER(TRIM(?)) AND DATE(atualizado_em) = CURRENT_DATE()");
            $stmtSala->execute([$perito_selecionado]);
        }
        $salaSalvaHoje = $stmtSala->fetchColumn();
        if (!$salaSalvaHoje) {
            $pId = peritoId($pdo, $perito_selecionado);
            if ($pId) {
                $stmtUserPerito = $pdo->prepare("SELECT username, nome FROM usuarios WHERE id = ?");
                $stmtUserPerito->execute([$pId]);
                $uP = $stmtUserPerito->fetch(PDO::FETCH_ASSOC);
                if ($uP) {
                    if ($temSubSalasP) {
                        $stmtSalaConfUser = $pdo->prepare("SELECT sala FROM salas_peritos WHERE subsecao_id = ? AND (LOWER(TRIM(perito)) = LOWER(TRIM(?)) OR LOWER(TRIM(perito)) = LOWER(TRIM(?))) AND DATE(atualizado_em) = CURRENT_DATE()");
                        $stmtSalaConfUser->execute([$subId, $uP['username'], $uP['nome'] ?? '']);
                    } else {
                        $stmtSalaConfUser = $pdo->prepare("SELECT sala FROM salas_peritos WHERE (LOWER(TRIM(perito)) = LOWER(TRIM(?)) OR LOWER(TRIM(perito)) = LOWER(TRIM(?))) AND DATE(atualizado_em) = CURRENT_DATE()");
                        $stmtSalaConfUser->execute([$uP['username'], $uP['nome'] ?? '']);
                    }
                    $salaSalvaHoje = $stmtSalaConfUser->fetchColumn();
                }
            }
        }
        if (!$salaSalvaHoje) {
            $pId = peritoId($pdo, $perito_selecionado);
            if ($temSubAtend) {
                $stmtSalaAtend = $pdo->prepare("SELECT sala FROM atendimentos WHERE subsecao_id = ? AND data_pauta = CURRENT_DATE() AND (perito = ? OR (? IS NOT NULL AND perito_id = ?)) AND sala IS NOT NULL AND sala != '' ORDER BY id DESC LIMIT 1");
                $stmtSalaAtend->execute([$subId, $perito_selecionado, $pId, $pId]);
            } else {
                $stmtSalaAtend = $pdo->prepare("SELECT sala FROM atendimentos WHERE data_pauta = CURRENT_DATE() AND (perito = ? OR (? IS NOT NULL AND perito_id = ?)) AND sala IS NOT NULL AND sala != '' ORDER BY id DESC LIMIT 1");
                $stmtSalaAtend->execute([$perito_selecionado, $pId, $pId]);
            }
            $salaSalvaHoje = $stmtSalaAtend->fetchColumn();
        }
        if ($salaSalvaHoje) {
            $sala = (string)$salaSalvaHoje;
            $_SESSION['sala'] = $sala;
            $sala_confirmada = true;
        }
    }

    $criterioFila = obterCriterioFilaPerito($pdo, $perito_selecionado);
    $atendePrioridade = obterAtenderPrioridadePerito($pdo, $perito_selecionado);

    // Heartbeat: mantém a trava da sala ativa enquanto o médico estiver navegando
    if ($perito_selecionado !== '' && $user_role === 'perito') {
        if ($temSubSalasP) {
            $pdo->prepare("UPDATE salas_peritos SET atualizado_em = CURRENT_TIMESTAMP WHERE perito = ? AND subsecao_id = ?")->execute([$perito_selecionado, $subId]);
        } else {
            $pdo->prepare("UPDATE salas_peritos SET atualizado_em = CURRENT_TIMESTAMP WHERE perito = ?")->execute([$perito_selecionado]);
        }
    }

    $temHora = colunaExiste($pdo, 'atendimentos', 'hora_pericia');
    $colHora = $temHora ? ", hora_pericia" : "";
    $temPrioridade = colunaExiste($pdo, 'atendimentos', 'prioridade');
    $colPrioridade = $temPrioridade ? ", prioridade" : "";

    $clausulaPerito = ($user_role === 'admin') ? "perito = ?" : "(perito_id = ? OR perito = ?)";
    $paramsPerito = ($user_role === 'admin') ? [$perito_selecionado] : [$ownerId, $perito_selecionado];

    // Atendimento atual (sendo chamado ou atendido agora nesta sala)
    if ($temSubAtend) {
        $stmtAtual = $pdo->prepare("SELECT id, nome, processo, cpf, senha, status, sala, chamado_em, chamadas_count $colHora $colPrioridade FROM atendimentos WHERE subsecao_id = ? AND status IN ('chamada', 'atendimento') AND sala = ? AND data_pauta = CURRENT_DATE() AND $clausulaPerito ORDER BY chamado_em DESC LIMIT 1");
        $stmtAtual->execute(array_merge([$subId, $sala], $paramsPerito));
    } else {
        $stmtAtual = $pdo->prepare("SELECT id, nome, processo, cpf, senha, status, sala, chamado_em, chamadas_count $colHora $colPrioridade FROM atendimentos WHERE status IN ('chamada', 'atendimento') AND sala = ? AND data_pauta = CURRENT_DATE() AND $clausulaPerito ORDER BY chamado_em DESC LIMIT 1");
        $stmtAtual->execute(array_merge([$sala], $paramsPerito));
    }
    $atendimentoAtual = $stmtAtual->fetch(PDO::FETCH_ASSOC);

    // Critério de ordenação da fila
    $orderSecundario = ($criterioFila === 'horario' && $temHora)
        ? "COALESCE(hora_pericia, '23:59:59') ASC, fila_em ASC, id ASC"
        : "fila_em ASC, id ASC";

    $orderFilaPresentes = ($atendePrioridade && $temPrioridade)
        ? "CASE WHEN prioridade = 'superprioridade' THEN 1 WHEN prioridade = 'prioridade' THEN 2 ELSE 3 END ASC, " . $orderSecundario
        : $orderSecundario;

    $orderClausula = "ORDER BY CASE WHEN status = 'presente' THEN 1 WHEN status IN ('chamada','atendimento') THEN 2 WHEN status = 'agendado' THEN 3 WHEN status = 'concluido' THEN 4 ELSE 5 END, " . $orderFilaPresentes;

    // Consulta todos os agendamentos deste perito hoje
    if ($perito_selecionado !== '') {
        if ($temSubAtend) {
            $stmtTodos = $pdo->prepare("SELECT id, nome, processo, cpf, senha, status, sala, chegada_em, fila_em, chamado_em, finalizado_em, chamadas_count $colHora $colPrioridade FROM atendimentos WHERE subsecao_id = ? AND data_pauta = CURRENT_DATE() AND $clausulaPerito $orderClausula");
            $stmtTodos->execute(array_merge([$subId], $paramsPerito));
        } else {
            $stmtTodos = $pdo->prepare("SELECT id, nome, processo, cpf, senha, status, sala, chegada_em, fila_em, chamado_em, finalizado_em, chamadas_count $colHora $colPrioridade FROM atendimentos WHERE data_pauta = CURRENT_DATE() AND $clausulaPerito $orderClausula");
            $stmtTodos->execute($paramsPerito);
        }
        $todosRegistros = $stmtTodos->fetchAll(PDO::FETCH_ASSOC);
    } else {
        if ($temSubAtend) {
            $stmtTodos = $pdo->prepare("SELECT id, nome, processo, cpf, senha, status, sala, chegada_em, fila_em, chamado_em, finalizado_em, chamadas_count $colHora $colPrioridade FROM atendimentos WHERE subsecao_id = ? AND data_pauta = CURRENT_DATE() $orderClausula");
            $stmtTodos->execute([$subId]);
        } else {
            $stmtTodos = $pdo->query("SELECT id, nome, processo, cpf, senha, status, sala, chegada_em, fila_em, chamado_em, finalizado_em, chamadas_count $colHora $colPrioridade FROM atendimentos WHERE data_pauta = CURRENT_DATE() $orderClausula");
        }
        $todosRegistros = $stmtTodos->fetchAll(PDO::FETCH_ASSOC);
    }

    $filaFormatada = [];
    $todosFormatados = [];
    $contAguardando = 0;
    $contPendentes = 0;
    $contAtendidos = 0;
    $contDesistentes = 0;
    $contPrioritarios = 0;

    $posFila = 1;
    foreach ($todosRegistros as $p) {
        $st = (string)$p['status'];
        $prio = (string)($p['prioridade'] ?? 'normal');
        if ($st === 'presente' && in_array($prio, ['prioridade', 'superprioridade'], true)) $contPrioritarios++;
        if ($st === 'presente') $contAguardando++;
        elseif ($st === 'agendado') $contPendentes++;
        elseif ($st === 'concluido') $contAtendidos++;
        elseif ($st === 'desistente') $contDesistentes++;

        $posAtual = null;
        if ($st === 'presente') {
            $posAtual = $posFila;
            $posFila++;
        }

        $horaFormatada = (!empty($p['hora_pericia'])) ? date('H:i', strtotime($p['hora_pericia'])) : null;

        $itemFormatado = [
            'id' => (int)$p['id'],
            'senha' => (string)($p['senha'] ?: '---'),
            'nome' => (string)($p['nome'] ?: 'Sem Nome'),
            'processo' => (string)($p['processo'] ?: 'Sem processo'),
            'cpf' => (string)($p['cpf'] ?? ''),
            'status' => $st,
            'sala' => (string)($p['sala'] ?? ''),
            'posicao_fila' => $posAtual,
            'prioridade' => $prio,
            'hora_pericia' => $horaFormatada,
            'chamadas_count' => (int)$p['chamadas_count'],
            'chegada' => !empty($p['chegada_em']) ? date('H:i:s', strtotime($p['chegada_em'])) : '--:--:--',
            'chamado' => !empty($p['chamado_em']) ? date('H:i:s', strtotime($p['chamado_em'])) : '--:--:--',
            'concluido' => !empty($p['finalizado_em']) ? date('H:i:s', strtotime($p['finalizado_em'])) : '--:--:--',
        ];

        $todosFormatados[] = $itemFormatado;
        if ($st === 'presente') {
            $filaFormatada[] = $itemFormatado;
        }
    }

    echo json_encode([
        'success' => true,
        'sala' => $sala,
        'sala_confirmada' => $sala_confirmada,
        'perito_selecionado' => $perito_selecionado,
        'criterio_fila' => $criterioFila,
        'atender_prioridade' => $atendePrioridade,
        'total_aguardando' => $contAguardando,
        'totais' => [
            'aguardando' => $contAguardando,
            'pendentes' => $contPendentes,
            'atendidos' => $contAtendidos,
            'desistentes' => $contDesistentes,
            'prioritarios' => $contPrioritarios,
            'total' => count($todosFormatados),
        ],
        'fila' => $filaFormatada,
        'todos' => $todosFormatados,
        'atendimento_atual_id' => $atendimentoAtual ? (int)$atendimentoAtual['id'] : 0,
        'atendimento_atual' => $atendimentoAtual ? [
            'id' => (int)$atendimentoAtual['id'],
            'nome' => (string)$atendimentoAtual['nome'],
            'processo' => (string)$atendimentoAtual['processo'],
            'senha' => (string)$atendimentoAtual['senha'],
            'status' => (string)$atendimentoAtual['status'],
            'hora_pericia' => !empty($atendimentoAtual['hora_pericia']) ? date('H:i', strtotime($atendimentoAtual['hora_pericia'])) : null,
            'chamadas_count' => (int)$atendimentoAtual['chamadas_count'],
            'prioridade' => (string)($atendimentoAtual['prioridade'] ?? 'normal'),
        ] : null,
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => erroPublico($e)]);
}
