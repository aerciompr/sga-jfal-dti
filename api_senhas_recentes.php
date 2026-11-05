<?php
require_once __DIR__ . '/banco.php';

$stmt = $pdo->query('
    SELECT ec.nome, ec.sala, COALESCE(a.chamadas_count, 1) AS chamadas_count
    FROM eventos_chamada ec
    INNER JOIN (
        SELECT MAX(id) AS max_id
        FROM eventos_chamada
        WHERE data_pauta = CURRENT_DATE()
        GROUP BY atendimento_id
    ) ultimos ON ec.id = ultimos.max_id
    LEFT JOIN atendimentos a ON a.id = ec.atendimento_id
    ORDER BY ec.id DESC
    LIMIT 4
');
$recentes = $stmt->fetchAll();

for ($i = 0; $i < 4; $i++) {
    $item = $recentes[$i] ?? null;
    if ($item) {
        $nome = escapar($item['nome'] ?: 'Sem Nome');
        $sala = escapar($item['sala']);
        $count = (int)($item['chamadas_count'] ?? 1);
        $active_class = $i === 0 ? 'border-blue-500/30 bg-blue-950/20 animate-pulse' : '';
        echo "<div class='bg-gray-950/60 border border-gray-800/50 rounded-xl py-2.5 px-4 flex justify-between items-center $active_class'>";
        echo "  <div class='text-left truncate flex-1'>";
        echo "    <div class='text-sm font-black text-white truncate'>$nome</div>";
        echo "    <div class='flex items-center gap-1.5 mt-0.5'>";
        echo "      <span class='text-[10px] text-emerald-400 font-semibold tracking-wider uppercase'>$sala</span>";
        if ($count > 1) {
            echo "  <span class='text-[9px] font-bold text-amber-400 bg-amber-950/60 border border-amber-800/60 px-1.5 py-0.2 rounded'>{$count}ª chamada</span>";
        }
        echo "    </div>";
        echo "  </div>";
        echo "  <div class='text-lg font-bold text-blue-400 ml-4'>📢</div>";
        echo "</div>";
    } else {
        echo "<div class='bg-gray-950/20 border border-gray-800/20 border-dashed rounded-xl py-2 px-3 flex items-center justify-center text-xs text-gray-600'>---</div>";
    }
}
