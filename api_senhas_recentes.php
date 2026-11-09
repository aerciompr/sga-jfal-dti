<?php
require_once __DIR__ . '/banco.php';

$temPrio = colunaExiste($pdo, 'eventos_chamada', 'prioridade') || colunaExiste($pdo, 'atendimentos', 'prioridade');
$colPrio = $temPrio ? "COALESCE(ec.prioridade, a.prioridade, 'normal') AS prioridade," : "'normal' AS prioridade,";

$stmt = $pdo->query("
    SELECT ec.nome, ec.sala, $colPrio COALESCE(a.chamadas_count, 1) AS chamadas_count
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
");
$recentes = $stmt->fetchAll();

for ($i = 0; $i < 4; $i++) {
    $item = $recentes[$i] ?? null;
    if ($item) {
        $nome = escapar($item['nome'] ?: 'Sem Nome');
        $sala = escapar($item['sala']);
        $count = (int)($item['chamadas_count'] ?? 1);
        $prio = (string)($item['prioridade'] ?? 'normal');
        $badgePrio = '';
        if ($prio === 'superprioridade') {
            $badgePrio = "<span class='text-[9px] font-bold text-amber-300 bg-amber-950/80 border border-amber-800/80 px-1.5 py-0.2 rounded'>⭐ 80+</span>";
        } elseif ($prio === 'prioridade') {
            $badgePrio = "<span class='text-[9px] font-bold text-sky-300 bg-sky-950/80 border border-sky-800/80 px-1.5 py-0.2 rounded'>♿ Prioridade</span>";
        }

        $active_class = $i === 0 ? 'border-blue-500/70 bg-blue-950/40 shadow-md ring-1 ring-blue-500/30' : 'border-gray-800 bg-gray-950/80';
        echo "<div class='bg-gray-950/60 border border-gray-800/50 rounded-xl py-2.5 px-4 flex justify-between items-center $active_class'>";
        echo "  <div class='text-left truncate flex-1'>";
        echo "    <div class='text-sm font-black text-white truncate'>$nome</div>";
        echo "    <div class='flex items-center gap-1.5 mt-0.5'>";
        echo "      <span class='text-[10px] text-emerald-400 font-semibold tracking-wider uppercase'>$sala</span>";
        if ($badgePrio !== '') {
            echo "  $badgePrio";
        }
        echo "    </div>";
        echo "  </div>";
        echo "  <div class='text-lg font-bold text-blue-400 ml-4'>📢</div>";
        echo "</div>";
    } else {
        echo "<div class='bg-gray-950/20 border border-gray-800/20 border-dashed rounded-xl py-2 px-3 flex items-center justify-center text-xs text-gray-600'>---</div>";
    }
}
