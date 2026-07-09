<?php
session_start();
require_once __DIR__ . '/banco.php';

// Apenas usuários logados podem consultar
if (!isset($_SESSION['usuario_id'])) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit;
}

$busca = trim($_GET['busca'] ?? '');
$hoje = $_GET['hoje'] ?? date('Y-m-d');

if (strlen($busca) < 3) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit;
}

try {
    // Limpa caracteres especiais do CPF caso o input seja apenas números de CPF
    $cpfLimpo = preg_replace('/\D/', '', $busca);
    
    if (strlen($cpfLimpo) === 11) {
        // Busca exata pelo CPF
        $stmt = $pdo->prepare("SELECT nome, data_pauta, perito, status FROM atendimentos WHERE (cpf = ? OR REPLACE(REPLACE(cpf, '.', ''), '-', '') = ?) AND data_pauta != ? ORDER BY data_pauta DESC LIMIT 3");
        $stmt->execute([$busca, $cpfLimpo, $hoje]);
    } else {
        // Busca parcial por nome ou processo
        $stmt = $pdo->prepare("SELECT nome, data_pauta, perito, status FROM atendimentos WHERE (nome LIKE ? OR processo LIKE ?) AND data_pauta != ? ORDER BY data_pauta DESC LIMIT 3");
        $stmt->execute(["%$busca%", "%$busca%", $hoje]);
    }
    
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formata a data para d/m/Y e traduz status para exibição amigável
    foreach ($resultados as &$res) {
        $res['data_pauta_formatada'] = date('d/m/Y', strtotime($res['data_pauta']));
        $st = $res['status'];
        if ($st === 'concluido') $res['status_formatado'] = 'Concluído';
        elseif ($st === 'atendimento') $res['status_formatado'] = 'Em Exame';
        elseif ($st === 'chamada') $res['status_formatado'] = 'Chamado';
        elseif ($st === 'presente' || $st === 'fila') $res['status_formatado'] = 'Aguardando';
        else $res['status_formatado'] = 'Agendado';
    }
    
    header('Content-Type: application/json');
    echo json_encode($resultados);
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([]);
}
