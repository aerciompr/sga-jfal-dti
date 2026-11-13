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
foreach ([['admin-test','admin','Administrador Teste'],['perito-a','perito','Perito Teste A'],['perito-b','perito','Perito Teste B'],['recepcao-test','recepcao','Recepção Teste'],['supervisor-test','supervisor','Supervisão Teste']] as [$login,$role,$nome]) $ins->execute([$login,password_hash('Senha-local-teste-2026!',PASSWORD_DEFAULT),$role,$nome]);
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
$dataMascara=(new DateTimeImmutable('+3 days'))->format('Y-m-d');
$mascarado=['nome'=>'Pessoa Máscara','processo'=>'0000001-00.2026.4.05.8000','perito'=>'Perito Teste A','cpf'=>'52998224725'];
operarPauta($pdo,'agendar_unico',$mascarado,$dataMascara);
$mascaraId=(int)$pdo->query('SELECT MAX(id) FROM atendimentos')->fetchColumn();
check(item($pdo,$mascaraId)['cpf']==='529.982.247-25','CPF manual normalizado como importador');
$importado=['processo'=>'00000010020264058000','perito'=>'Perito Teste A','cpf_perito'=>'','cpf'=>'529.982.247-25','periciado'=>'Pessoa Máscara','data_pericia'=>$dataMascara];
$r=importarRegistros($pdo,[$importado]);check($r['novos']===0 && $r['atualizados']===0,'Máscaras não duplicam nem alteram importação equivalente');
$mascarado['processo']='00000010020264058000';
rejeita(fn()=>operarPauta($pdo,'agendar_unico',$mascarado,$dataMascara),'Duplicidade manual independe da máscara do processo');
$coautor=['nome'=>'Pessoa Coautora','processo'=>'00000010020264058000','perito'=>'Perito Teste A','cpf'=>'08041416497'];
operarPauta($pdo,'agendar_unico',$coautor,$dataMascara);
$coautorId=(int)$pdo->query('SELECT MAX(id) FROM atendimentos')->fetchColumn();
check(item($pdo,$coautorId)['nome']==='Pessoa Coautora','Permite coautor com CPF distinto no mesmo processo');
$importadoCoautor=['processo'=>'00000010020264058000','perito'=>'Perito Teste A','cpf_perito'=>'','cpf'=>'080.414.164-97','periciado'=>'Pessoa Coautora','data_pericia'=>$dataMascara];
$r=importarRegistros($pdo,[$importado,$importadoCoautor]);check($r['novos']===0 && $r['atualizados']===0,'Reimportação com múltiplos autores é idempotente');
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
operarPauta($pdo,'mover_pauta',['mover_tipo'=>'mes','mover_mes_origem'=>'2030-03','nova_data'=>'2030-04'],date('Y-m-d'));
check(item($pdo,$mesId)['data_pauta']==='2030-04-30','Remanejamento mensal respeita último dia do mês');
// Falha de trilha crítica deve reverter a mutação.
$pdo->exec("CREATE TRIGGER teste_log_falha BEFORE INSERT ON logs_auditoria FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='falha simulada'");
try {
    $dados['processo']='DEVE-REVERTER';
    try { operarPauta($pdo,'agendar_unico',$dados,date('Y-m-d')); } catch (PDOException $e) {}
    check((int)$pdo->query("SELECT COUNT(*) FROM atendimentos WHERE processo='DEVE-REVERTER'")->fetchColumn()===0,'Falha de auditoria reverte agendamento');
} finally { $pdo->exec('DROP TRIGGER teste_log_falha'); }
// Testes de Janela e Sincronização de Importação da Pauta
$dataSinc = (new DateTimeImmutable('+20 days'))->format('Y-m-d');
$ag1 = ['nome'=>'Pessoa Hoje 1','processo'=>'SINC-001','perito'=>'Perito Teste A','cpf'=>'','perito_id'=>$ids['perito-a'],'data_pauta'=>$dataSinc,'status'=>'agendado'];
$ag2 = ['nome'=>'Pessoa Hoje 2','processo'=>'SINC-002','perito'=>'Perito Teste A','cpf'=>'','perito_id'=>$ids['perito-a'],'data_pauta'=>$dataSinc,'status'=>'agendado'];
$ag3 = ['nome'=>'Pessoa Hoje 3 (Chegou)','processo'=>'SINC-003','perito'=>'Perito Teste A','cpf'=>'','perito_id'=>$ids['perito-a'],'data_pauta'=>$dataSinc,'status'=>'presente','senha'=>'PE-099'];
foreach ([$ag1, $ag2, $ag3] as $itemAg) {
    $ins = $pdo->prepare('INSERT INTO atendimentos (nome,processo,perito,cpf,perito_id,data_pauta,status,senha) VALUES (?,?,?,?,?,?,?,?)');
    $ins->execute([$itemAg['nome'],$itemAg['processo'],$itemAg['perito'],$itemAg['cpf'],$itemAg['perito_id'],$itemAg['data_pauta'],$itemAg['status'],$itemAg['senha'] ?? '---']);
}
$idAg1 = (int)$pdo->query("SELECT id FROM atendimentos WHERE processo='SINC-001'")->fetchColumn();
$idAg2 = (int)$pdo->query("SELECT id FROM atendimentos WHERE processo='SINC-002'")->fetchColumn();
$idAg3 = (int)$pdo->query("SELECT id FROM atendimentos WHERE processo='SINC-003'")->fetchColumn();

$planilhaNovaSinc = [
    ['processo'=>'SINC-001','perito'=>'Perito Teste A','cpf_perito'=>'','cpf'=>'','periciado'=>'Pessoa Hoje 1','data_pericia'=>$dataSinc],
    ['processo'=>'SINC-004','perito'=>'Perito Teste A','cpf_perito'=>'','cpf'=>'','periciado'=>'Pessoa Nova 4','data_pericia'=>$dataSinc],
];

$resumoPrevia = calcularResumoPrevia($pdo, $planilhaNovaSinc, [$dataSinc], true);
check($resumoPrevia['novos'] === 1, 'Resumo prévia: 1 novo a incluir');
check($resumoPrevia['mantidos'] === 1, 'Resumo prévia: 1 mantido');
check($resumoPrevia['total_cancelar'] === 1, 'Resumo prévia: 1 a cancelar (SINC-002)');
check((int)$resumoPrevia['cancelar'][0]['id'] === $idAg2, 'Resumo prévia aponta id correto para cancelamento');
check($resumoPrevia['preservados_andamento'] === 1, 'Resumo prévia: 1 preservado em andamento (SINC-003)');

$resImp = importarRegistros($pdo, $planilhaNovaSinc, [$dataSinc], true);
check($resImp['novos'] === 1, 'Importação inseriu 1 novo');
check($resImp['cancelados'] === 1, 'Importação cancelou 1 omitido da pauta');
check(item($pdo, $idAg2)['status'] === 'cancelado', 'SINC-002 foi marcado como cancelado');
check(item($pdo, $idAg3)['status'] === 'presente', 'Imunidade operacional: SINC-003 presente foi preservado');

$logCancel = $pdo->query("SELECT * FROM logs_auditoria WHERE atendimento_id = $idAg2 AND acao = 'Cancelar por Atualização de Pauta'")->fetch();
check(!empty($logCancel), 'Auditoria registrada para cancelamento por sincronização');

$resReimp = importarRegistros($pdo, $planilhaNovaSinc, [$dataSinc], true);
check($resReimp['novos'] === 0 && $resReimp['cancelados'] === 0, 'Reimportação sincronizada é idempotente');

$ag5 = ['nome'=>'Pessoa 5','processo'=>'SINC-005','perito'=>'Perito Teste A','cpf'=>'','data_pauta'=>$dataSinc];
$pdo->prepare('INSERT INTO atendimentos (nome,processo,perito,cpf,data_pauta,status) VALUES (?,?,?,?,?,"agendado")')->execute([$ag5['nome'],$ag5['processo'],$ag5['perito'],$ag5['cpf'],$ag5['data_pauta']]);
$idAg5 = (int)$pdo->query("SELECT id FROM atendimentos WHERE processo='SINC-005'")->fetchColumn();

$resSemSinc = importarRegistros($pdo, [$planilhaNovaSinc[0]], [$dataSinc], false);
check($resSemSinc['cancelados'] === 0, 'Sincronização desligada não cancela');
check(item($pdo, $idAg5)['status'] === 'agendado', 'SINC-005 mantido agendado com sincronização desligada');

// Testes de janela com planilha real/sintética
$tmpXlsx = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'teste_janela_' . bin2hex(random_bytes(4)) . '.xlsx';
$hHoje = date('Y-m-d');
$hAmanha = (new DateTimeImmutable('+1 day'))->format('Y-m-d');
$hDia3 = (new DateTimeImmutable('+2 days'))->format('Y-m-d');
$hDia4 = (new DateTimeImmutable('+3 days'))->format('Y-m-d');
$hDia30 = (new DateTimeImmutable('+30 days'))->format('Y-m-d');
$python = getenv('PYTHON_BIN') ?: (PHP_OS_FAMILY === 'Windows' ? 'python' : '/opt/venv/bin/python');
$cmd = escapeshellarg($python) . " -c \"import openpyxl; wb = openpyxl.Workbook(); ws = wb.active; ws.append(['Processo', 'Documento', 'Nome Perito', 'Data perícia', 'Nome Parte']); ws.append(['PROC-HOJE', '', 'Perito Teste', '$hHoje', 'Parte Hoje']); ws.append(['PROC-AMANHA', '', 'Perito Teste', '$hAmanha', 'Parte Amanha']); ws.append(['PROC-DIA3', '', 'Perito Teste', '$hDia3', 'Parte Dia 3']); ws.append(['PROC-DIA4', '', 'Perito Teste', '$hDia4', 'Parte Dia 4']); ws.append(['PROC-DIA30', '', 'Perito Teste', '$hDia30', 'Parte Dia 30']); wb.save('" . addslashes($tmpXlsx) . "')\"";
exec($cmd);
$fileTeste = ['name' => 'teste.xlsx', 'tmp_name' => $tmpXlsx, 'size' => filesize($tmpXlsx), 'error' => UPLOAD_ERR_OK];

$resDia = prepararImportacao($fileTeste, 'dia');
check(count($resDia['registros']) === 1 && $resDia['registros'][0]['processo'] === 'PROC-HOJE', 'Janela dia filtra apenas hoje');

$res3 = prepararImportacao($fileTeste, '3dias');
check(count($res3['registros']) === 3, 'Janela 3 dias filtra hoje ate d+2');

$res30 = prepararImportacao($fileTeste, '30dias');
check(count($res30['registros']) === 1, 'Tentativa de 30 dias sanitiza para dia');
@unlink($tmpXlsx);
for ($n=0;$n<510;$n++) registrarLog($pdo,'Teste de paginação','Registro fictício de teste.');

// Testes de Multi-Subseção (Maceió = 1, União dos Palmares = 2)
function itemUser(PDO $pdo, int $id): array { $s = $pdo->prepare('SELECT * FROM usuarios WHERE id=?'); $s->execute([$id]); return $s->fetch(); }

set_config($pdo, 'multisubsecao_ativa', '1');
check(multisubsecaoAtiva($pdo) === true, 'Flag multi-subseção ativa no banco');

// 1. Isolamento de Senhas Diárias (PE-001 independente por subseção)
$dadosMcz = ['nome' => 'Paciente Maceio', 'processo' => 'MCZ-001', 'perito' => 'Perito Teste A', 'cpf' => ''];
operarPauta($pdo, 'agendar_unico', array_merge($dadosMcz, ['subsecao_id' => 1]), date('Y-m-d'));
$idMcz = (int)$pdo->query("SELECT id FROM atendimentos WHERE processo = 'MCZ-001'")->fetchColumn();
check(confirmarPresencas($pdo, [$idMcz], 'recepcao') === 1, 'Check-in Maceió confirmado');
$senhaMcz = item($pdo, $idMcz)['senha'];
check(str_starts_with($senhaMcz, 'PE-'), 'Senha Maceió gerada no formato PE-xxx');

$dadosUniao = ['nome' => 'Paciente Uniao', 'processo' => 'UNI-001', 'perito' => 'Perito Teste B', 'cpf' => ''];
operarPauta($pdo, 'agendar_unico', array_merge($dadosUniao, ['subsecao_id' => 2]), date('Y-m-d'));
$idUniao = (int)$pdo->query("SELECT id FROM atendimentos WHERE processo = 'UNI-001'")->fetchColumn();
check(confirmarPresencas($pdo, [$idUniao], 'recepcao') === 1, 'Check-in União confirmado');
$senhaUniao = item($pdo, $idUniao)['senha'];
check($senhaUniao === 'PE-001', 'União dos Palmares inicia sua própria sequência PE-001 independente de Maceió');

// 2. Isolamento de Filas e Atendimento
$resumoMcz = obterResumoFilasPeritos($pdo, date('Y-m-d'), 1);
$resumoUniao = obterResumoFilasPeritos($pdo, date('Y-m-d'), 2);
check(count($resumoMcz) >= 1 && count($resumoUniao) >= 1, 'Resumos de filas separados por subseção');

// 3. Isolamento de Travas de Salas entre Subseções
travarSalaPerito($pdo, 'Perito Teste A', 'Sala 1', 1);
$ocupadasMcz = obterSalasOcupadasHoje($pdo, '', 1);
check(isset($ocupadasMcz['Sala 1']), 'Sala 1 ocupada em Maceió');

$ocupadasUniao = obterSalasOcupadasHoje($pdo, '', 2);
check(!isset($ocupadasUniao['Sala 1']), 'Sala 1 livre em União dos Palmares mesmo ocupada em Maceió');

travarSalaPerito($pdo, 'Perito Teste B', 'Sala 1', 2);
$ocupadasUniaoApos = obterSalasOcupadasHoje($pdo, '', 2);
check(isset($ocupadasUniaoApos['Sala 1']), 'Sala 1 pode ser travada em União dos Palmares por outro perito');

// Liberação em Maceió não afeta União
liberarSalaPerito($pdo, 'Perito Teste A', 'Sala 1', 1);
$ocupadasMczDepois = obterSalasOcupadasHoje($pdo, '', 1);
check(!isset($ocupadasMczDepois['Sala 1']), 'Sala 1 liberada em Maceió');
$ocupadasUniaoMantida = obterSalasOcupadasHoje($pdo, '', 2);
check(isset($ocupadasUniaoMantida['Sala 1']), 'Sala 1 continua travada em União após liberação em Maceió');
liberarSalaPerito($pdo, 'Perito Teste B', 'Sala 1', 2);

// 4. Restrições do Administrador Local
$pdo->prepare('INSERT INTO usuarios (username, password, role, nome, subsecao_id) VALUES (?, ?, ?, ?, ?)')->execute(['admin-uniao', password_hash('Senha-uniao-2026!', PASSWORD_DEFAULT), 'admin', 'Admin Local Uniao', 2]);
$idAdminUniao = (int)$pdo->lastInsertId();
$_SESSION = ['usuario_id' => $idAdminUniao, 'usuario_role' => 'admin', 'usuario_name' => 'admin-uniao', 'is_superadmin' => false, 'usuario_subsecao_id' => 2, 'subsecao_id_ativa' => 2];

// Admin local tenta cadastrar outro admin -> rejeitado
rejeita(fn() => operarUsuario($pdo, ['action' => 'cadastrar', 'username' => 'novo-admin', 'role' => 'admin', 'nome' => 'Novo Admin', 'password' => '12345678']), 'Admin local não pode criar novos admins');

// Admin local tenta cadastrar perito -> rejeitado
rejeita(fn() => operarUsuario($pdo, ['action' => 'cadastrar', 'username' => 'novo-perito', 'role' => 'perito', 'nome' => 'Novo Perito', 'password' => '12345678']), 'Admin local não pode criar peritos médicos');

// Admin local tenta editar usuário da Sede (Maceió) -> rejeitado
rejeita(fn() => operarUsuario($pdo, ['action' => 'editar', 'id' => $ids['recepcao-test'], 'username' => 'recepcao-test', 'role' => 'recepcao', 'nome' => 'Recepcao Alterada', 'password' => '']), 'Admin local não pode alterar usuários de outra subseção');

// Admin local cadastra recepção para sua própria subseção -> sucesso
operarUsuario($pdo, ['action' => 'cadastrar', 'username' => 'recepcao-uniao', 'role' => 'recepcao', 'nome' => 'Recepção União', 'password' => '12345678']);
$idRecUniao = (int)$pdo->query("SELECT id FROM usuarios WHERE username = 'recepcao-uniao'")->fetchColumn();
check((int)itemUser($pdo, $idRecUniao)['subsecao_id'] === 2, 'Usuário cadastrado pelo admin local fica vinculado à subseção 2');

// 5. Métricas e Produtividade isoladas
$metMcz = obterMetricasDia($pdo, date('Y-m-d'), 1);
$metUniao = obterMetricasDia($pdo, date('Y-m-d'), 2);
check(isset($metMcz['totais']['agendados']) && isset($metUniao['totais']['agendados']), 'Métricas calculadas por subseção');

// 6. Restaura estado multi-subseção para retrocompatibilidade
set_config($pdo, 'multisubsecao_ativa', '0');
check(multisubsecaoAtiva($pdo) === false, 'Flag multi-subseção restaurada para retrocompatibilidade');

echo "$checks verificações passaram.\n";
