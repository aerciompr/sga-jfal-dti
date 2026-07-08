<?php
require_once __DIR__ . '/banco.php';

$today = date('Y-m-d');
$stmt = $pdo->prepare("SELECT * FROM atendimentos WHERE status IN ('chamada', 'concluido') AND DATE(chamado_em) = ? ORDER BY chamado_em DESC LIMIT 5");
$stmt->execute([$today]);
$recentes = $stmt->fetchAll();

for ($i = 0; $i < 4; $i++) {
    $item = $recentes[$i] ?? null;
    if ($item) {
        $nome = htmlspecialchars($item['nome'] ?: 'Sem Nome');
        $sala = htmlspecialchars($item['sala']);
        $active_class = $i === 0 ? 'border-blue-500/30 bg-blue-950/20 animate-pulse' : '';
        echo "<div class='bg-gray-950/60 border border-gray-800/50 rounded-xl py-2.5 px-4 flex justify-between items-center $active_class'>";
        echo "  <div class='text-left truncate flex-1'>";
        echo "    <div class='text-sm font-black text-white truncate'>$nome</div>";
        echo "    <div class='text-[10px] text-emerald-400 font-semibold tracking-wider uppercase mt-0.5'>$sala</div>";
        echo "  </div>";
        echo "  <div class='text-lg font-bold text-blue-400 ml-4'>📢</div>";
        echo "</div>";
    } else {
        echo "<div class='bg-gray-950/20 border border-gray-800/20 border-dashed rounded-xl py-2 px-3 flex items-center justify-center text-xs text-gray-600'>---</div>";
    }
}
