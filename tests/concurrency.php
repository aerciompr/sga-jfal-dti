<?php
if (getenv('SGA_TEST') !== '1' || getenv('DB_NAME') !== 'sga_test') exit(2);
require_once dirname(__DIR__).'/banco.php';
$id=(int)$pdo->query("SELECT id FROM usuarios WHERE username='perito-a'")->fetchColumn();
$s=$pdo->prepare("INSERT INTO atendimentos (nome,processo,perito,perito_id,status,data_pauta,chegada_em) VALUES ('Pessoa Concorrência',?,'Perito Teste A',?,'presente',CURRENT_DATE(),NOW())");
for ($n=1;$n<=2;$n++) $s->execute(['RACE-'.$n,$id]);
$workers=[];
for($n=0;$n<2;$n++) {
    $pipes=[];
    $proc=proc_open([PHP_BINARY,__DIR__.'/race_worker.php',(string)$id],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
    $workers[]=[$proc,$pipes];
}
$resultados=[];
foreach($workers as [$proc,$pipes]) { $resultados[]=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($proc)!==0) throw new RuntimeException('Worker falhou: '.$err); }
sort($resultados);
if ($resultados!==['bloqueado','chamado']) throw new RuntimeException('Concorrência: '.json_encode($resultados));
if((int)$pdo->query("SELECT COUNT(*) FROM atendimentos WHERE processo LIKE 'RACE-%' AND status='chamada'")->fetchColumn()!==1) throw new RuntimeException('Mais de um ativo');
echo "OK Duas conexões concorrentes: um atendimento ativo e outro bloqueado.\n";
