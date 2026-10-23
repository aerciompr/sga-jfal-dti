<?php
if (getenv('SGA_TEST') !== '1' || getenv('DB_NAME') !== 'sga_test') exit(2);
require_once dirname(__DIR__).'/banco.php';
$_SESSION=['usuario_id'=>(int)$argv[1],'usuario_role'=>'perito','usuario_name'=>'race-test'];
try { agirAtendimento($pdo,'chamar_proximo','Perito Teste A','Sala 2',[]); echo 'chamado'; }
catch (DomainException $e) { echo 'bloqueado'; }
