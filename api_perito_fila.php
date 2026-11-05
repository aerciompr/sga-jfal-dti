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

    if ($user_role === 'perito') {
        $perito_selecionado = (string)($_SESSION['usuario_nome'] ?? $_SESSION['usuario_name']);
    } else {
        $perito_selecionado = (string)($_SESSION['perito_selecionado'] ?? '');
    }

    $sala = (string)($_SESSION['sala'] ?? 'Sala 1');
    $criterioFila = obterCriterioFilaPerito($pdo, $perito_selecionado);

    // Heartbeat: mantém a trava da sala ativa enquanto o médico estiver navegando
    if ($perito_selecionado !== '') {
        $pdo->prepare("UPDATE salas_peritos SET atualizado_em = CURRENT_TIMESTAMP WHERE perito = ?")->execute([$perito_selecionado]);
    }

    $temHora = colunaExiste($pdo, 'atendimentos', 'hora_pericia');
    $colHora = $temHora ? ", hora_pericia" : "";

    // Atendimento atual (sendo chamado ou atendido agora nesta sala)
    $stmtAtual = $pdo->prepare("SELECT id, nome, processo, cpf, senha, status, sala, chamado_em, chamadas_count $colHora FROM atendimentos WHERE status IN ('chamada', 'atendimento') AND sala = ? AND data_pauta = CURRENT_DATE() AND (perito_id = ? OR (? = 1 AND perito = ?)) ORDER BY chamado_em DESC LIMIT 1");
    $stmtAtual->execute([$sala, $ownerId, $ownerAdmin, $perito_selecionado]);
    $atendimentoAtual = $stmtAtual->fetch(PDO::FETCH_ASSOC);

    // Critério de ordenação da fila
    $orderSecundario = ($criterioFila === 'horario' && $temHora)
        ? "COALESCE(hora_pericia, '23:59:59') ASC, fila_em ASC, id ASC"
        : "fila_em ASC, id ASC";

    $orderClausula = "ORDER BY CASE WHEN status = 'presente' THEN 1 WHEN status IN ('chamada','atendimento') THEN 2 WHEN status = 'agendado' THEN 3 WHEN status = 'concluido' THEN 4 ELSE 5 END, " . $orderSecundario;

    // Consulta todos os agendamentos deste perito hoje
    if ($perito_selecionado !== '') {
        $stmtTodos = $pdo->prepare("SELECT id, nome, processo, cpf, senha, status, sala, chegada_em, fila_em, chamado_em, finalizado_em, chamadas_count $colHora FROM atendimentos WHERE data_pauta = CURRENT_DATE() AND (perito_id = ? OR (? = 1 AND perito = ?)) $orderClausula");
        $stmtTodos->execute([$ownerId, $ownerAdmin, $perito_selecionado]);
        $todosRegistros = $stmtTodos->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmtTodos = $pdo->query("SELECT id, nome, processo, cpf, senha, status, sala, chegada_em, fila_em, chamado_em, finalizado_em, chamadas_count $colHora FROM atendimentos WHERE data_pauta = CURRENT_DATE() $orderClausula");
        $todosRegistros = $stmtTodos->fetchAll(PDO::FETCH_ASSOC);
    }

    $filaFormatada = [];
    $todosFormatados = [];
    $contAguardando = 0;
    $contPendentes = 0;
    $contAtendidos = 0;
    $contDesistentes = 0;

    $posFila = 1;
    foreach ($todosRegistros as $p) {
        $st = (string)$p['status'];
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
        'criterio_fila' => $criterioFila,
        'total_aguardando' => $contAguardando,
        'totais' => [
            'aguardando' => $contAguardando,
            'pendentes' => $contPendentes,
            'atendidos' => $contAtendidos,
            'desistentes' => $contDesistentes,
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
        ] : null,
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => erroPublico($e)]);
}
