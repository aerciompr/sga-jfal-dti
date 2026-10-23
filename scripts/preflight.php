<?php
if (PHP_SAPI!=='cli') {http_response_code(404);exit;}
require_once dirname(__DIR__).'/banco.php';
$falhas=0;
$checks=[
    'Atendimentos ativos (encerre a operação antes da troca de versão)' => "SELECT COUNT(*) FROM atendimentos WHERE status IN ('chamada','atendimento') AND data_pauta=CURRENT_DATE()",
    'Pautas futuras sem vínculo de perito (corrigir em Pautas)' => "SELECT COUNT(*) FROM atendimentos WHERE data_pauta>=CURRENT_DATE() AND status IN ('agendado','presente') AND perito_id IS NULL",
    'Atendimentos sem data definida (revisar legado)' => 'SELECT COUNT(*) FROM atendimentos WHERE data_pauta IS NULL',
    'Salas sem cadastro' => 'SELECT IF(COUNT(*)=0,1,0) FROM salas',
    'Estados legados sem correspondência (revisar antes do corte)' => "SELECT COUNT(*) FROM atendimentos WHERE status NOT IN ('agendado','presente','chamada','atendimento','concluido','cancelado')",
    'Salas com múltiplos ativos' => "SELECT COUNT(*) FROM (SELECT sala FROM atendimentos WHERE data_pauta=CURRENT_DATE() AND status IN ('chamada','atendimento') GROUP BY sala HAVING COUNT(*)>1) conflitos",
    'Senhas duplicadas no mesmo dia' => "SELECT COUNT(*) FROM (SELECT data_pauta,senha FROM atendimentos WHERE senha REGEXP '^PE-[0-9]+$' AND status<>'cancelado' GROUP BY data_pauta,senha HAVING COUNT(*)>1) conflitos",
];
foreach($checks as $nome=>$sql) { $n=(int)$pdo->query($sql)->fetchColumn(); echo ($n?'REVISAR':'OK').": $nome: $n\n"; if($n)$falhas++; }
$duplicados=$pdo->query("SELECT processo,data_pauta,perito FROM atendimentos WHERE status<>'cancelado' AND processo IS NOT NULL AND processo<>'' GROUP BY processo,data_pauta,perito HAVING COUNT(*)>1")->fetchAll();
echo ($duplicados?'REVISAR':'OK').': Grupos duplicados: '.count($duplicados)."\n";if($duplicados)$falhas++;
echo "Nenhum dado foi alterado.\n";
exit($falhas?1:0);
