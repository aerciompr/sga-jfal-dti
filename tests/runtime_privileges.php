<?php
if (getenv('SGA_TEST')!=='1' || getenv('DB_NAME')!=='sga_test' || getenv('DB_USER')==='root') exit(2);
require_once dirname(__DIR__).'/banco.php';
$bloqueados=0;
foreach(['CREATE TABLE sga_privilege_probe (id INT)','DELETE FROM eventos_chamada WHERE 1=0','DELETE FROM logs_auditoria WHERE 1=0'] as $sql) {
    try { $pdo->exec($sql); } catch (PDOException $e) {
        if ((string)$e->getCode()==='42000') { $bloqueados++; continue; } throw $e;
    }
}
if($bloqueados!==3) throw new RuntimeException('Privilégios excessivos na conta da aplicação.');
$pdo->exec('UPDATE atendimentos SET status=status WHERE 1=0');
echo "OK Conta de runtime sem DDL e sem exclusão de eventos/auditoria; UPDATE operacional permitido.\n";
