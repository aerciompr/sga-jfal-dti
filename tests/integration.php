<?php
if (getenv('SGA_TEST') !== '1' || getenv('DB_NAME') !== 'sga_test') { fwrite(STDERR,"Use somente banco descartável sga_test e SGA_TEST=1.\n"); exit(2); }
require_once dirname(__DIR__).'/banco.php';
require_once dirname(__DIR__).'/importacao_service.php';
require_once dirname(__DIR__).'/usuarios_service.php';
$checks = 0;
function check(bool $ok, string $nome): void { global $checks; if (!$ok) throw new RuntimeException('FALHOU: '.$nome); $checks++; echo "OK $nome\n"; }
function rejeita(callable $fn, string $nome): void { try { $fn(); } catch (DomainException $e) { check(true,$nome); return; } throw new RuntimeException('Deveria rejeitar: '.$nome); }
function item(PDO $pdo,int $id): array { $s=$pdo->prepare('SELECT * FROM atendimentos WHERE id=?');$s->execute([$id]); return $s->fetch(); }
foreach (['eventos_chamada','atendimentos','logs_auditoria','pautas_liberadas','sequencia_diaria_senhas','locks_peritos','login_tentativas','salas_peritos','usuarios'] as $table) $pdo->exec("DELETE FROM $table");
$ins=$pdo->prepare('INSERT INTO usuarios (username,password,role,nome) VALUES (?,?,?,?)');
foreach ([['admin-test','admin','Administrador Teste'],['perito-a','perito','Perito Teste A'],['perito-b','perito','Perito Teste B'],['recepcao-test','recepcao','Recepção Teste']] as [$login,$role,$nome]) $ins->execute([$login,password_hash('Senha-local-teste-2026!',PASSWORD_DEFAULT),$role,$nome]);
$ids=$pdo->query('SELECT username,id FROM usuarios')->fetchAll(PDO::FETCH_KEY_PAIR);
$_SESSION=['usuario_id'=>$ids['admin-test'],'usuario_role'=>'admin','usuario_name'=>'admin-test'];
operarPauta($pdo,'liberar_pauta',['data_alvo'=>date('Y-m-d')],date('Y-m-d'));
$dados=['nome'=>'Pessoa Teste','processo'=>'TESTE-001','perito'=>'Perito Teste A','cpf'=>''];
operarPauta($pdo,'agendar_unico',$dados,date('Y-m-d')); $id=(int)$pdo->query('SELECT MAX(id) FROM atendimentos')->fetchColumn();
check((int)item($pdo,$id)['perito_id']===$ids['perito-a'],'Vínculo por ID');
check(confirmarPresencas($pdo,[$id],'recepcao')===1,'Check-in homologado'); $senha=item($pdo,$id)['senha'];
check(confirmarPresencas($pdo,[$id],'recepcao')===0 && item($pdo,$id)['senha']===$senha,'Repetição não muda senha');
$_SESSION=['usuario_id'=>$ids['perito-a'],'usuario_role'=>'perito','usuario_name'=>'perito-a'];
agirAtendimento($pdo,'chamar_proximo','Perito Teste A','Sala 1',[]);
check(item($pdo,$id)['status']==='chamada','Chamada da própria fila');
rejeita(fn()=>agirAtendimento($pdo,'chamar_proximo','Perito Teste A','Sala 1',[]),'Impede dois ativos');
$_SESSION=['usuario_id'=>$ids['perito-b'],'usuario_role'=>'perito','usuario_name'=>'perito-b'];
rejeita(fn()=>agirAtendimento($pdo,'iniciar_atendimento','Perito Teste B','Sala 1',['atendimento_id'=>$id]),'Perito B não inicia A');
$_SESSION=['usuario_id'=>$ids['perito-a'],'usuario_role'=>'perito','usuario_name'=>'perito-a'];
rejeita(fn()=>agirAtendimento($pdo,'concluir','Perito Teste A','Sala 1',['atendimento_id'=>$id]),'Não conclui sem início');
rejeita(fn()=>agirAtendimento($pdo,'iniciar_atendimento','Perito Teste A','Sala 1',['atendimento_id'=>$id+100]),'Formulário antigo não atua em outro ID');
$primeira=item($pdo,$id)['primeira_chamada_em'];
agirAtendimento($pdo,'chamar_novamente','Perito Teste A','Sala 1',['atendimento_id'=>$id]);
check((int)$pdo->query('SELECT COUNT(*) FROM eventos_chamada')->fetchColumn()===2,'Rechamada no mesmo segundo gera outro evento');
check(item($pdo,$id)['primeira_chamada_em']===$primeira,'Rechamada preserva primeira chamada');
$chegadaOriginal=item($pdo,$id)['chegada_em'];
agirAtendimento($pdo,'nao_compareceu','Perito Teste A','Sala 1',['atendimento_id'=>$id]);
check(item($pdo,$id)['chegada_em']===$chegadaOriginal && (int)item($pdo,$id)['chamadas_count']===2,'Ausência preserva chegada e tentativas');
agirAtendimento($pdo,'chamar_especifico','Perito Teste A','Sala 1',['id'=>$id]);
agirAtendimento($pdo,'iniciar_atendimento','Perito Teste A','Sala 1',['atendimento_id'=>$id]);
agirAtendimento($pdo,'concluir','Perito Teste A','Sala 1',['atendimento_id'=>$id]);
check(confirmarPresencas($pdo,[$id],'recepcao')===0 && item($pdo,$id)['status']==='concluido','Check-in não reabre concluído');
$_SESSION=['usuario_id'=>$ids['admin-test'],'usuario_role'=>'admin','usuario_name'=>'admin-test'];
operarPauta($pdo,'excluir_selecionados',['selecionados'=>[$id]],date('Y-m-d'));
check(item($pdo,$id)['status']==='concluido','Cancelamento preserva concluído');
rejeita(fn()=>operarPauta($pdo,'mover_pauta',['mover_tipo'=>'selecionados','selecionados'=>[$id],'nova_data'=>"2026-10-26' OR 1=1 --"],date('Y-m-d')),'SQL hostil não altera histórico');
check(!dataValida("2026-10-26' OR 1=1 --"),'Validação rejeita SQL em data');
$dados['processo']='TESTE-002'; operarPauta($pdo,'agendar_unico',$dados,date('Y-m-d')); $cancel=(int)$pdo->query('SELECT MAX(id) FROM atendimentos')->fetchColumn();
operarPauta($pdo,'excluir_selecionados',['selecionados'=>[$cancel]],date('Y-m-d'));
check(item($pdo,$cancel)['status']==='cancelado','Cancelamento mantém registro');
$reg=['processo'=>'IMPORT-TESTE','perito'=>'Perito Teste A','cpf_perito'=>'','cpf'=>'','periciado'=>'Pessoa Importada','data_pericia'=>date('Y-m-d')];
$futuro=$reg; $futuro['data_pericia']=(new DateTimeImmutable('+1 day'))->format('Y-m-d');
$r=importarRegistros($pdo,[$reg,$futuro]); check($r['novos']===2,'Importação mantém datas diferentes');
$r=importarRegistros($pdo,[$reg,$futuro]); check($r['novos']===0 && $r['atualizados']===0,'Reimportação idempotente');
rejeita(fn()=>operarUsuario($pdo,['action'=>'editar','id'=>$ids['admin-test'],'username'=>'admin-test','role'=>'recepcao','nome'=>'Teste','password'=>'']),'Preserva último administrador');
$antes=$pdo->query('SELECT session_version FROM usuarios WHERE id='.$ids['perito-a'])->fetchColumn();
operarUsuario($pdo,['action'=>'editar','id'=>$ids['perito-a'],'username'=>'perito-a','role'=>'perito','nome'=>'Perito Teste A','password'=>'Nova-senha-teste-2026!']);
$depois=$pdo->query('SELECT session_version FROM usuarios WHERE id='.$ids['perito-a'])->fetchColumn();
check((int)$depois===(int)$antes+1,'Mudança de senha revoga sessões');
// Deixa a fixture de HTTP utilizável sem credencial inicial obrigatória.
$pdo->exec('UPDATE usuarios SET must_change_password=0');
$stream=fopen('php://memory','r+');csvSeguro($stream,['=1+1','Nome']);rewind($stream);check(str_starts_with(stream_get_contents($stream),"'=1+1"),'CSV neutraliza fórmula');
check(!dataValida('2026-02-31'),'Rejeita data impossível');
$mensal=$dados; $mensal['processo']='MES-TESTE';
operarPauta($pdo,'agendar_unico',$mensal,'2030-03-31');$mesId=(int)$pdo->query('SELECT MAX(id) FROM atendimentos')->fetchColumn();
operarPauta($pdo,'mover_pauta',['mover_tipo'=>'mes','mover_mes_origem'=>'2030-03','nova_data'=>'2030-04-01'],date('Y-m-d'));
check(item($pdo,$mesId)['data_pauta']==='2030-04-30','Remanejamento mensal respeita último dia do mês');
// Falha de trilha crítica deve reverter a mutação.
$pdo->exec("CREATE TRIGGER teste_log_falha BEFORE INSERT ON logs_auditoria FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='falha simulada'");
try {
    $dados['processo']='DEVE-REVERTER';
    try { operarPauta($pdo,'agendar_unico',$dados,date('Y-m-d')); } catch (PDOException $e) {}
    check((int)$pdo->query("SELECT COUNT(*) FROM atendimentos WHERE processo='DEVE-REVERTER'")->fetchColumn()===0,'Falha de auditoria reverte agendamento');
} finally { $pdo->exec('DROP TRIGGER teste_log_falha'); }
for ($n=0;$n<510;$n++) registrarLog($pdo,'Teste de paginação','Registro fictício de teste.');
echo "$checks verificações passaram.\n";
