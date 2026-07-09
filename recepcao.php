<?php
/**
 * MÓDULO DE RECEPÇÃO - JFAL
 * 
 * recepcao.php: Tela para uso dos recepcionistas do Tribunal.
 * Funcionalidades:
 * - Importação da Pauta de Perícias em Excel (extração de processos, peritos e periciados).
 * - Agendamento manual de periciados.
 * - Confirmação de chegada de periciados (adicionando-os à fila de hoje).
 * - Busca de periciados por texto em tempo real (nome, processo ou perito).
 * - Remoção de agendamentos (manual, múltipla ou limpeza total da pauta de hoje).
 * - Prevenção ativa de duplicidade de peritos e processos.
 */

session_start();
require_once __DIR__ . '/banco.php';

// Proteção da sessão: exige que o usuário esteja autenticado e possua perfil administrativo, supervisão ou recepção
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['usuario_role'], ['admin', 'recepcao', 'supervisor'])) {
    header("Location: index.php");
    exit;
}

// Forçar troca de senha de primeiro acesso
if (isset($_SESSION['usuario_id'])) {
    $checkStmt = $pdo->prepare("SELECT cpf, password, role FROM usuarios WHERE id = ?");
    $checkStmt->execute([$_SESSION['usuario_id']]);
    $uData = $checkStmt->fetch();
    if ($uData) {
        $pwdPadrao = '';
        if ($uData['role'] === 'perito' && !empty($uData['cpf'])) {
            $cpfLimpo = preg_replace('/\D/', '', $uData['cpf']);
            if (strlen($cpfLimpo) === 11) {
                $pwdPadrao = substr($cpfLimpo, 0, 6);
            }
        }
        if ($pwdPadrao && password_verify($pwdPadrao, $uData['password'])) {
            header("Location: alterar_senha_obrigatorio.php");
            exit;
        }
    }
}

$sys_name = defined('SYS_NAME') ? SYS_NAME : 'Justiça Federal';
$message = '';
$message_type = '';
$dataFiltro = $_GET['data_filtro'] ?? date('Y-m-d');

/**
 * Função Auxiliar: getNextSenha
 * 
 * Obtém a próxima senha sequencial do tipo "PE-xxx" (Perícias) para a pauta de hoje.
 * Garante que a sequência reinicie no dia atual.
 *
 * @param PDO $pdo Instância da conexão com o banco.
 * @return string Próxima senha gerada.
 */
function getNextSenha($pdo) {
    $today = date('Y-m-d');
    $hasActiveTransaction = $pdo->inTransaction();
    if (!$hasActiveTransaction) {
        $pdo->beginTransaction();
    }
    try {
        $stmt = $pdo->prepare("INSERT IGNORE INTO sequencia_diaria_senhas (data_pauta, ultimo_numero) VALUES (?, 0)");
        $stmt->execute([$today]);
        
        $stmt = $pdo->prepare("UPDATE sequencia_diaria_senhas SET ultimo_numero = LAST_INSERT_ID(ultimo_numero + 1) WHERE data_pauta = ?");
        $stmt->execute([$today]);
        
        $num = (int)$pdo->lastInsertId();
        
        if (!$hasActiveTransaction) {
            $pdo->commit();
        }
    } catch (Exception $e) {
        if (!$hasActiveTransaction) {
            $pdo->rollBack();
        }
        $stmt = $pdo->prepare("SELECT senha FROM atendimentos WHERE senha LIKE 'PE-%' AND (DATE(chegada_em) = ? OR DATE(chamado_em) = ?) ORDER BY id DESC LIMIT 1");
        $stmt->execute([$today, $today]);
        $last = $stmt->fetch();
        $num = $last ? ((int)substr($last['senha'], 3) + 1) : 1;
    }
    return 'PE-' . sprintf('%03d', $num);
}

// Processa as requisições POST do formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        $userRole = $_SESSION['usuario_role'];
        
        // Ação 1: Agendamento Único Manual
        if ($action === 'agendar_unico') {
            if (!in_array($userRole, ['admin', 'supervisor'])) {
                $message = "Acesso Negado: Apenas administradores ou supervisores podem cadastrar periciados manualmente.";
                $message_type = "error";
            } else {
                $nome = trim($_POST['nome'] ?? '');
                $processo = trim($_POST['processo'] ?? '');
                $perito = trim($_POST['perito'] ?? '');
                $cpf = trim($_POST['cpf'] ?? '');
                
                if ($nome) {
                    $nome = corrigirNomeInteligente($nome);
                    $perito = corrigirNomeInteligente($perito);
                    $perito = associarPeritoCadastrado($perito);
                    
                    // Deduplicação de perito: busca se já existe o perito com grafia aproximada no banco
                    $peritoClean = simplifyName($perito);
                    if ($peritoClean) {
                        $pst = $pdo->query("SELECT DISTINCT perito FROM atendimentos WHERE perito IS NOT NULL AND perito != ''");
                        while ($row = $pst->fetch()) {
                            if (simplifyName($row['perito']) === $peritoClean) {
                                $perito = $row['perito']; // Adota a grafia do banco
                                break;
                            }
                        }
                    }

                    // Evita duplicar processo na data agendada
                    $procClean = simplifyName($processo);
                    $isDuplicate = false;
                    if ($procClean) {
                        $pst = $pdo->prepare("SELECT processo FROM atendimentos WHERE data_pauta = ?");
                        $pst->execute([$dataFiltro]);
                        while ($row = $pst->fetch()) {
                            if (simplifyName($row['processo']) === $procClean) {
                                $isDuplicate = true;
                                break;
                            }
                        }
                    }

                    if ($isDuplicate) {
                        $message = "Processo $processo já agendado para esta data!";
                        $message_type = "error";
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO atendimentos (senha, nome, processo, perito, status, data_pauta, cpf) VALUES ('---', ?, ?, ?, 'agendado', ?, ?)");
                        $stmt->execute([$nome, $processo ?: null, $perito ?: null, $dataFiltro, $cpf ?: null]);
                        $newId = $pdo->lastInsertId();
                        registrarLog($pdo, 'Cadastrar Agendamento', "Paciente: $nome, Processo: $processo, Perito: $perito, Data da Pauta: $dataFiltro", $newId);
                        
                        // Verifica se este paciente (CPF ou Nome) possui agendamento em outra data
                        $avisoOutraData = '';
                        if (!empty($cpf)) {
                            $chkOutra = $pdo->prepare("SELECT data_pauta FROM atendimentos WHERE id != ? AND (cpf = ? OR REPLACE(REPLACE(cpf, '.', ''), '-', '') = ?) AND data_pauta != ? LIMIT 1");
                            $chkOutra->execute([$newId, $cpf, preg_replace('/\D/', '', $cpf), $dataFiltro]);
                            $outraData = $chkOutra->fetchColumn();
                            if ($outraData) {
                                $avisoOutraData = " (Atenção: Este paciente já possui um agendamento para o dia " . date('d/m/Y', strtotime($outraData)) . "!)";
                            }
                        } else {
                            $chkOutra = $pdo->prepare("SELECT data_pauta FROM atendimentos WHERE id != ? AND nome = ? AND data_pauta != ? LIMIT 1");
                            $chkOutra->execute([$newId, $nome, $dataFiltro]);
                            $outraData = $chkOutra->fetchColumn();
                            if ($outraData) {
                                $avisoOutraData = " (Atenção: Este paciente já possui um agendamento para o dia " . date('d/m/Y', strtotime($outraData)) . "!)";
                            }
                        }
                        
                        $message = "Periciado $nome agendado!" . $avisoOutraData;
                        $message_type = "success";
                    }
                } else {
                    $message = "Digite o nome.";
                    $message_type = "error";
                }
            }
        }
        
        // Ação 2: Importação de Pauta em Excel
        elseif ($action === 'importar_pdf') {
            if ($userRole !== 'admin') {
                $message = "Acesso Negado: Apenas administradores podem importar pautas.";
                $message_type = "error";
            } else {
                if (isset($_FILES['arquivo_pdf']) && $_FILES['arquivo_pdf']['error'] === UPLOAD_ERR_OK) {
                    $file_tmp = $_FILES['arquivo_pdf']['tmp_name'];
                    $filename = htmlspecialchars($_FILES['arquivo_pdf']['name'], ENT_QUOTES, 'UTF-8');
                    $file_size = $_FILES['arquivo_pdf']['size'];
                    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                    
                    if ($ext !== 'xlsx') {
                        $message = "Selecione um arquivo Excel (.xlsx) válido.";
                        $message_type = "error";
                    } elseif ($file_size > 5 * 1024 * 1024) { // 5MB
                        $message = "Erro: O arquivo enviado excede o limite de tamanho permitido de 5MB.";
                        $message_type = "error";
                    } else {
                        $cmd = "python " . escapeshellarg(__DIR__ . '/import_excel.py') . " " . escapeshellarg($file_tmp) . " 2>&1";
                        
                        $output = [];
                        $return_var = 0;
                        exec($cmd, $output, $return_var);
                        
                        if ($return_var === 0) {
                            $json_str = implode("\n", $output);
                            $records = json_decode($json_str, true);
                            
                            if (is_array($records) && !empty($records)) {
                                if (isset($records[0]['error'])) {
                                    $message = "Erro no processamento do arquivo: " . htmlspecialchars($records[0]['error']);
                                    $message_type = "error";
                                } else {
                                    $today = date('Y-m-d');
                                    
                                    // Carrega peritos existentes para evitar redundâncias na pauta
                                    $existingPeritos = [];
                                    $pst = $pdo->query("SELECT DISTINCT perito FROM atendimentos WHERE perito IS NOT NULL AND perito != ''");
                                    while ($row = $pst->fetch()) {
                                        $p = $row['perito'];
                                        $existingPeritos[simplifyName($p)] = $p;
                                    }

                                    // Carrega processos e suas datas já cadastrados para evitar duplicados ou para atualizar se mudaram
                                    $existingAppointments = [];
                                    $prStmt = $pdo->query("SELECT id, processo, data_pauta, nome, perito, cpf FROM atendimentos WHERE processo IS NOT NULL AND processo != '' AND data_pauta IS NOT NULL");
                                    while ($row = $prStmt->fetch()) {
                                        $key = simplifyName($row['processo']) . '_' . $row['data_pauta'];
                                        $existingAppointments[$key] = [
                                            'id' => $row['id'],
                                            'nome' => $row['nome'],
                                            'perito' => $row['perito'],
                                            'cpf' => $row['cpf']
                                        ];
                                    }

                                    // Carrega os CPFs e usernames de usuários cadastrados para cache em memória
                                    $existingUsers = [];
                                    try {
                                        $uStmt = $pdo->query("SELECT cpf, username FROM usuarios");
                                        while ($row = $uStmt->fetch()) {
                                            if ($row['cpf']) {
                                                $existingUsers[$row['cpf']] = true;
                                            }
                                            if ($row['username']) {
                                                $existingUsers[$row['username']] = true;
                                            }
                                        }
                                    } catch (PDOException $e) {}

                                    $pdo->beginTransaction();
                                    $stmt = $pdo->prepare("INSERT INTO atendimentos (senha, nome, processo, perito, status, data_pauta, cpf) VALUES ('---', ?, ?, ?, 'agendado', ?, ?)");
                                    $stmtUpdate = $pdo->prepare("UPDATE atendimentos SET nome = ?, perito = ?, cpf = ? WHERE id = ?");
                                    $stmtUser = $pdo->prepare("INSERT INTO usuarios (username, password, role, nome, cpf) VALUES (?, ?, 'perito', ?, ?)");
                                    $count = 0;
                                    $GLOBALS['sga_import_lote'] = true;
                                    foreach ($records as $r) {
                                        $procClean = simplifyName($r['processo']);

                                        // Se a importação tem data da perícia (Excel), usamos ela; senão usamos hoje.
                                        $dataPauta = $today;
                                        if (isset($r['data_pericia']) && $r['data_pericia']) {
                                            $dParts = explode(" ", $r['data_pericia']);
                                            $dataPauta = $dParts[0]; // Pega a parte YYYY-MM-DD
                                        }

                                        // REGRA: Importar apenas do dia atual para frente
                                        if ($dataPauta < $today) {
                                            continue;
                                        }

                                        // Padroniza e normaliza o nome do perito
                                        $pName = corrigirNomeInteligente($r['perito']);
                                        $pName = associarPeritoCadastrado($pName);
                                        $pClean = simplifyName($pName);
                                        if (isset($existingPeritos[$pClean])) {
                                            $pName = $existingPeritos[$pClean];
                                        } elseif ($pClean) {
                                            $existingPeritos[$pClean] = $pName;
                                        }

                                        // Pré-cadastro automático do Perito como Usuário do sistema
                                        $pCpf = isset($r['cpf_perito']) ? trim($r['cpf_perito']) : '';
                                        if ($pName && $pCpf && strlen(preg_replace('/\D/', '', $pCpf)) === 11) {
                                            $uName = preg_replace('/\D/', '', $pCpf);
                                            if (!isset($existingUsers[$pCpf]) && !isset($existingUsers[$uName])) {
                                                // Senha padrão inicial: primeiros 6 dígitos do CPF
                                                $tempPwd = password_hash(substr($uName, 0, 6), PASSWORD_DEFAULT);
                                                try {
                                                    $stmtUser->execute([$uName, $tempPwd, $pName, $pCpf]);
                                                    $existingUsers[$pCpf] = true;
                                                    $existingUsers[$uName] = true;
                                                } catch (PDOException $e) {
                                                    // Ignora falhas de inserção de usuário para não travar a pauta
                                                }
                                            }
                                        }

                                        // Se a planilha/PDF não tem nome do periciado, usamos o CPF ou um marcador
                                        $periciadoNome = isset($r['periciado']) ? corrigirNomeInteligente($r['periciado']) : '';
                                        if (!$periciadoNome) {
                                            if (isset($r['cpf']) && $r['cpf']) {
                                                $periciadoNome = "(Sem Nome) - CPF " . $r['cpf'];
                                            } else {
                                                $periciadoNome = "(Sem Nome)";
                                            }
                                        }

                                        $cpf = $r['cpf'] ?? null;
                                        $appKey = $procClean . '_' . $dataPauta;

                                        // REGRA: Se o processo já existir para a data, atualiza os dados se diferirem
                                        if ($procClean && isset($existingAppointments[$appKey])) {
                                            $existing = $existingAppointments[$appKey];
                                            $changed = ($existing['nome'] !== $periciadoNome) || 
                                                       ($existing['perito'] !== $pName) || 
                                                       ($existing['cpf'] !== $cpf);
                                            if ($changed) {
                                                $stmtUpdate->execute([$periciadoNome, $pName ?: null, $cpf ?: null, $existing['id']]);
                                                $count++;
                                            }
                                            continue;
                                        }

                                        $stmt->execute([
                                            $periciadoNome,
                                            $r['processo'] ?: null,
                                            $pName ?: null,
                                            $dataPauta,
                                            $cpf ?: null
                                        ]);
                                        $count++;
                                        
                                        // Registra a importação no mapa para evitar duplicidade na própria planilha
                                        if ($procClean) {
                                            $existingAppointments[$appKey] = [
                                                'id' => null, // não é mais necessário para inserções locais da mesma planilha
                                                'nome' => $periciadoNome,
                                                'perito' => $pName,
                                                'cpf' => $cpf
                                            ];
                                        }
                                    }
                                    unset($GLOBALS['sga_import_lote']);
                                    $pdo->commit();
                                    
                                    // Libera a memória RAM do PHP ativamente
                                    unset($records);
                                    unset($existingAppointments);
                                    unset($existingPeritos);
                                    unset($existingUsers);
                                    gc_collect_cycles();

                                    $message = "Sucesso! $count novos registros importados (perícias duplicadas ou já existentes foram ignoradas).";
                                    $message_type = "success";
                                }
                            } else {
                                $message = "Nenhum registro extraído.";
                                $message_type = "error";
                            }
                        } else {
                            $err_msg = !empty($output) ? implode("<br>", array_map('htmlspecialchars', $output)) : "Código de saída: " . $return_var;
                            $message = "Erro no processamento do arquivo:<br>" . $err_msg;
                            $message_type = "error";
                        }
                    }
                } else {
                    $message = "Selecione um arquivo Excel (.xlsx) válido.";
                    $message_type = "error";
                }
            }
        }
        
        // Ação 3: Confirmar Chegada do Periciado na recepção
        elseif ($action === 'confirmar_chegada') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id) {
                // Valida se o agendamento é de hoje para o perfil 'recepcao'
                $stmt = $pdo->prepare("SELECT data_pauta FROM atendimentos WHERE id = ?");
                $stmt->execute([$id]);
                $dataPautaAtendimento = $stmt->fetchColumn();
                
                if ($dataPautaAtendimento !== date('Y-m-d') && $userRole === 'recepcao') {
                    $message = "Acesso Negado: A recepção só pode confirmar a chegada na pauta do dia atual.";
                    $message_type = "error";
                } elseif ($userRole === 'recepcao' && !isPautaLiberada($pdo, $dataPautaAtendimento)) {
                    $message = "Acesso Negado: A pauta do dia " . date('d/m/Y', strtotime($dataPautaAtendimento)) . " ainda não foi homologada pelo supervisor.";
                    $message_type = "error";
                } else {
                    $senha = getNextSenha($pdo);
                    $stmt = $pdo->prepare("UPDATE atendimentos SET status = 'presente', senha = ?, chegada_em = CURRENT_TIMESTAMP WHERE id = ?");
                    $stmt->execute([$senha, $id]);
                    
                    $stName = $pdo->prepare("SELECT nome FROM atendimentos WHERE id = ?");
                    $stName->execute([$id]);
                    $pName = $stName->fetchColumn();
                    registrarLog($pdo, 'Confirmar Presença', "Paciente: $pName, Senha: $senha", $id);
                    
                    $message = "Chegada confirmada! Senha: $senha";
                    $message_type = "success";
                }
            }
        }
        
        // Ação 4: Remover Agendamento / Retornar da Fila
        elseif ($action === 'remover') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id) {
                $stmt = $pdo->prepare("SELECT status FROM atendimentos WHERE id = ?");
                $stmt->execute([$id]);
                $status = $stmt->fetchColumn();
                
                if ($status && $status !== 'agendado') {
                    // Retorna para a lista de agendados (Apenas Admin e Supervisor podem fazer)
                    if (!in_array($userRole, ['admin', 'supervisor'])) {
                        $message = "Acesso Negado: A recepção comum não tem permissão para retornar periciados para agendados.";
                        $message_type = "error";
                    } else {
                        $stmt = $pdo->prepare("UPDATE atendimentos SET status = 'agendado', senha = NULL, chegada_em = NULL, chamado_em = NULL, sala = NULL WHERE id = ?");
                        $stmt->execute([$id]);
                        
                        $stName = $pdo->prepare("SELECT nome FROM atendimentos WHERE id = ?");
                        $stName->execute([$id]);
                        $pName = $stName->fetchColumn();
                        registrarLog($pdo, 'Retornar Agendamento', "Paciente: $pName, Retornou ao status de Agendado", $id);
                        
                        $message = "Periciado retornou para a lista de agendados.";
                        $message_type = "success";
                    }
                } else {
                    // Exclusão física (Apenas admin e supervisor)
                    if (!in_array($userRole, ['admin', 'supervisor'])) {
                        $message = "Acesso Negado: A recepção comum não tem permissão para excluir registros.";
                        $message_type = "error";
                    } else {
                        $stName = $pdo->prepare("SELECT nome, processo FROM atendimentos WHERE id = ?");
                        $stName->execute([$id]);
                        $pData = $stName->fetch();
                        
                        $stmt = $pdo->prepare("DELETE FROM atendimentos WHERE id = ?");
                        $stmt->execute([$id]);
                        
                        if ($pData) {
                            registrarLog($pdo, 'Excluir Agendamento', "Paciente: {$pData['nome']}, Processo: {$pData['processo']}", $id);
                        }
                        
                        $message = "Registro removido.";
                        $message_type = "info";
                    }
                }
            }
        }
        
        // Ação 5: Exclusão em Lote ou Retorno para Agendados
        elseif ($action === 'remover_multiplos') {
            if (!in_array($userRole, ['admin', 'supervisor'])) {
                $message = "Acesso Negado: Apenas supervisores ou administradores podem executar esta ação.";
                $message_type = "error";
            } else {
                $ids = $_POST['selecionados'] ?? [];
                if (!empty($ids)) {
                    $ids = array_map('intval', $ids);
                    
                    $pdo->beginTransaction();
                    $excluidos = 0;
                    $retornados = 0;
                    foreach ($ids as $id) {
                        $stmt = $pdo->prepare("SELECT status, nome FROM atendimentos WHERE id = ?");
                        $stmt->execute([$id]);
                        $row = $stmt->fetch();
                        
                        if ($row) {
                            if ($row['status'] && $row['status'] !== 'agendado') {
                                $stmt = $pdo->prepare("UPDATE atendimentos SET status = 'agendado', senha = NULL, chegada_em = NULL, chamado_em = NULL, sala = NULL WHERE id = ?");
                                $stmt->execute([$id]);
                                registrarLog($pdo, 'Retornar Agendamento', "Paciente: {$row['nome']} (Retorno em Lote)", $id);
                                $retornados++;
                            } else {
                                $stmt = $pdo->prepare("DELETE FROM atendimentos WHERE id = ?");
                                $stmt->execute([$id]);
                                registrarLog($pdo, 'Excluir Agendamento', "Paciente: {$row['nome']} (Exclusão em Lote)", $id);
                                $excluidos++;
                            }
                        }
                    }
                    $pdo->commit();
                    registrarLog($pdo, 'Remover Multiplos', "Registros retornados: $retornados, Registros excluidos: $excluidos");
                    
                    if ($retornados > 0 && $excluidos > 0) {
                        $message = "$retornados periciados retornaram para agendados e $excluidos agendamentos manuais foram excluídos!";
                    } elseif ($retornados > 0) {
                        $message = "$retornados periciados retornaram para a lista de agendados!";
                    } else {
                        $message = "$excluidos agendamentos excluídos!";
                    }
                    $message_type = "info";
                } else {
                    $message = "Nenhum periciado selecionado.";
                    $message_type = "error";
                }
            }
        }

        // Ação 5.5: Confirmação de Chegada em Lote (Múltipla)
        elseif ($action === 'confirmar_multiplos') {
            $ids = $_POST['selecionados'] ?? [];
            if (!empty($ids)) {
                $ids = array_map('intval', $ids);
                
                // Valida se todos os registros pertencem à pauta de hoje e se a pauta está liberada caso seja recepção
                $podeConfirmar = true;
                $motivoBloqueio = '';
                if ($userRole === 'recepcao') {
                    foreach ($ids as $id) {
                        $stmt = $pdo->prepare("SELECT data_pauta FROM atendimentos WHERE id = ?");
                        $stmt->execute([$id]);
                        $dataP = $stmt->fetchColumn();
                        if ($dataP !== date('Y-m-d')) {
                            $podeConfirmar = false;
                            $motivoBloqueio = "A recepção só pode confirmar a chegada na pauta do dia atual.";
                            break;
                        }
                        if (!isPautaLiberada($pdo, $dataP)) {
                            $podeConfirmar = false;
                            $motivoBloqueio = "A pauta do dia " . date('d/m/Y', strtotime($dataP)) . " ainda não foi homologada pelo supervisor.";
                            break;
                        }
                    }
                }
                
                if (!$podeConfirmar) {
                    $message = "Acesso Negado: " . $motivoBloqueio;
                    $message_type = "error";
                } else {
                    $pdo->beginTransaction();
                    $count = 0;
                    foreach ($ids as $id) {
                        $senha = getNextSenha($pdo);
                        $stmt = $pdo->prepare("UPDATE atendimentos SET status = 'presente', senha = ?, chegada_em = CURRENT_TIMESTAMP WHERE id = ?");
                        $stmt->execute([$senha, $id]);
                        
                        $stName = $pdo->prepare("SELECT nome FROM atendimentos WHERE id = ?");
                        $stName->execute([$id]);
                        $pName = $stName->fetchColumn();
                        registrarLog($pdo, 'Confirmar Presença', "Paciente: $pName, Senha: $senha (Em Lote)", $id);
                        $count++;
                    }
                    $pdo->commit();
                    registrarLog($pdo, 'Confirmar Presença Multipla', "Registros confirmados em lote: $count");
                    
                    $message = "Chegada confirmada para $count periciados com sucesso!";
                    $message_type = "success";
                }
            } else {
                $message = "Nenhum periciado selecionado.";
                $message_type = "error";
            }
        }
        
        // Ação 6: Limpar todos os agendamentos pendentes da data selecionada
        elseif ($action === 'limpar_pauta_hoje') {
            if (!in_array($userRole, ['admin', 'supervisor'])) {
                $message = "Acesso Negado: Apenas administradores ou supervisores podem limpar a pauta.";
                $message_type = "error";
            } else {
                $stmt = $pdo->prepare("DELETE FROM atendimentos WHERE data_pauta = ? AND status = 'agendado'");
                $stmt->execute([$dataFiltro]);
                $count = $stmt->rowCount();
                registrarLog($pdo, 'Limpar Pauta', "Pauta do dia $dataFiltro limpa. Registros removidos: $count");
                $message = "Pauta de " . date('d/m/Y', strtotime($dataFiltro)) . " limpa! Todos os agendamentos não confirmados foram removidos.";
                $message_type = "info";
            }
        }

        // Ação 8: Editar dados do atendimento (Nome, Processo, Perito, CPF)
        elseif ($action === 'editar_atendimento') {
            if (!in_array($userRole, ['admin', 'supervisor'])) {
                $message = "Acesso Negado: Apenas administradores ou supervisores podem editar dados de periciados.";
                $message_type = "error";
            } else {
                $id = (int)($_POST['id'] ?? 0);
                $nome = trim($_POST['nome'] ?? '');
                $processo = trim($_POST['processo'] ?? '');
                $perito = trim($_POST['perito'] ?? '');
                $cpf = trim($_POST['cpf'] ?? '');
                
                if ($id && $nome) {
                    $nome = corrigirNomeInteligente($nome);
                    $perito = corrigirNomeInteligente($perito);
                    $perito = associarPeritoCadastrado($perito);
                    
                    // Deduplicação de perito: busca se já existe o perito com grafia aproximada no banco
                    $peritoClean = simplifyName($perito);
                    if ($peritoClean) {
                        $pst = $pdo->query("SELECT DISTINCT perito FROM atendimentos WHERE perito IS NOT NULL AND perito != ''");
                        while ($row = $pst->fetch()) {
                            if (simplifyName($row['perito']) === $peritoClean) {
                                    $perito = $row['perito']; // Adota a grafia do banco
                                    break;
                            }
                        }
                    }
                    
                    $stmt = $pdo->prepare("UPDATE atendimentos SET nome = ?, processo = ?, perito = ?, cpf = ? WHERE id = ?");
                    $stmt->execute([$nome, $processo ?: null, $perito ?: null, $cpf ?: null, $id]);
                    $message = "Dados do periciado atualizados com sucesso!";
                    $message_type = "success";
                } else {
                    $message = "Dados insuficientes para edição.";
                    $message_type = "error";
                }
            }
        }
        
        // Ação 9: Configuração do Vídeo da TV
        elseif ($action === 'set_yt_url') {
            if ($userRole !== 'admin') {
                $message = "Acesso Negado: Apenas administradores podem configurar o vídeo da TV.";
                $message_type = "error";
            } else {
                $yt_url = trim($_POST['yt_url'] ?? '');
                $yt_file = __DIR__ . '/youtube_url.txt';
                file_put_contents($yt_file, $yt_url);
                $message = "Vídeo de fundo da TV atualizado!";
                $message_type = "success";
            }
        }
    }
}

// Carrega listas para exibição limitadas à data selecionada (já definida no topo)

// Carrega URL atual da TV
$yt_file = __DIR__ . '/youtube_url.txt';
$yt_url = file_exists($yt_file) ? trim(file_get_contents($yt_file)) : 'https://www.youtube.com/watch?v=5qap5aO4i9A';

$pauta_liberada = isPautaLiberada($pdo, $dataFiltro);
$permite_checkin = (in_array($_SESSION['usuario_role'], ['admin', 'supervisor']) || ($_SESSION['usuario_role'] === 'recepcao' && $dataFiltro === date('Y-m-d') && $pauta_liberada));

// Recepção comum não visualiza dados de pautas que não foram homologadas/liberadas pelo supervisor
$agendados = [];
$presentes = [];
$chamados = [];
$concluidos = [];

if (in_array($_SESSION['usuario_role'], ['admin', 'supervisor']) || ($_SESSION['usuario_role'] === 'recepcao' && $pauta_liberada)) {
    $stmt = $pdo->prepare("SELECT * FROM atendimentos WHERE status = 'agendado' AND data_pauta = ? ORDER BY perito ASC, nome ASC, id ASC");
    $stmt->execute([$dataFiltro]);
    $agendados = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT * FROM atendimentos WHERE status = 'presente' AND data_pauta = ? ORDER BY chegada_em ASC");
    $stmt->execute([$dataFiltro]);
    $presentes = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT * FROM atendimentos WHERE status = 'chamada' AND data_pauta = ? ORDER BY chamado_em DESC");
    $stmt->execute([$dataFiltro]);
    $chamados = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT * FROM atendimentos WHERE status = 'concluido' AND data_pauta = ? ORDER BY chamado_em DESC");
    $stmt->execute([$dataFiltro]);
    $concluidos = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=device-width, initial-scale=1.0">
    <title>Recepção - <?= htmlspecialchars($sys_name) ?></title>
    <script src="tema.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-950 text-gray-100 min-h-screen flex flex-col">
    <!-- Header -->
    <header class="bg-gray-900 border-b border-gray-800 px-6 py-4 flex items-center justify-between shadow-md shrink-0">
        <div class="flex items-center space-x-3">
            <a href="dashboard.php" class="text-blue-500 font-bold text-lg hover:underline">⚖️ JFAL - Alagoas</a>
            <span class="bg-gray-800 text-gray-400 text-[10px] px-2.5 py-0.5 rounded-full uppercase font-semibold">
                <?= htmlspecialchars(ucfirst($_SESSION['usuario_role'])) ?>
            </span>
        </div>
        <div class="flex items-center space-x-3">
            <a href="dashboard.php" class="text-xs text-gray-400 hover:text-white border border-gray-800 bg-gray-950/30 px-3 py-1.5 rounded-lg transition">Painel Principal</a>
            
            <?php if (in_array($_SESSION['usuario_role'], ['admin', 'supervisor', 'recepcao'])): ?>
                <a href="recepcao.php" class="text-xs text-emerald-400 hover:text-emerald-300 border border-emerald-900/60 bg-emerald-950/30 px-3 py-1.5 rounded-lg transition">Recepção</a>
            <?php endif; ?>

            <?php if (in_array($_SESSION['usuario_role'], ['admin', 'perito'])): ?>
                <a href="atendente.php" class="text-xs text-blue-400 hover:text-blue-300 border border-blue-900/60 bg-blue-950/30 px-3 py-1.5 rounded-lg transition">Atendimento</a>
            <?php endif; ?>

            <?php if (in_array($_SESSION['usuario_role'], ['admin', 'supervisor'])): ?>
                <a href="gerenciar_pauta.php" class="text-xs text-sky-400 hover:text-sky-300 border border-sky-900/60 bg-sky-950/30 px-3 py-1.5 rounded-lg transition">Pautas</a>
                <a href="salas.php" class="text-xs text-pink-400 hover:text-pink-300 border border-pink-900/60 bg-pink-950/30 px-3 py-1.5 rounded-lg transition">Salas</a>
                <a href="auditoria.php" class="text-xs text-teal-400 hover:text-teal-300 border border-teal-900/60 bg-teal-950/30 px-3 py-1.5 rounded-lg transition">Auditoria</a>
            <?php endif; ?>

            <?php if ($_SESSION['usuario_role'] === 'admin'): ?>
                <a href="usuarios.php" class="text-xs text-indigo-400 hover:text-indigo-300 border border-indigo-900/60 bg-indigo-950/30 px-3 py-1.5 rounded-lg transition">Perfis & Senhas</a>
            <?php endif; ?>

            <span class="text-xs text-gray-400 hidden sm:inline">Olá, <strong class="text-white"><?= htmlspecialchars($_SESSION['usuario_name']) ?></strong></span>
            <a href="logout.php" class="bg-red-950/40 hover:bg-red-900/40 border border-red-900/60 text-red-400 text-xs px-3 py-1.5 rounded-lg transition">Sair</a>
        </div>
    </header>

    <main class="flex-1 max-w-7xl w-full mx-auto p-6 <?= in_array($_SESSION['usuario_role'], ['admin', 'supervisor']) ? 'grid grid-cols-1 lg:grid-cols-3 gap-6' : 'space-y-6' ?>">
        <?php if (in_array($_SESSION['usuario_role'], ['admin', 'supervisor'])): ?>
        <!-- Sidebar de Operações (Importação e Cadastro Manual) -->
        <div class="space-y-6">
            <?php if ($message): ?>
                <div class="p-3 rounded-lg border text-xs text-center font-medium <?= $message_type === 'success' ? 'bg-green-950/40 border-green-900/80 text-green-300' : ($message_type === 'info' ? 'bg-blue-950/40 border-blue-900/80 text-blue-300' : 'bg-red-950/40 border-red-900/80 text-red-300') ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <?php if ($_SESSION['usuario_role'] === 'admin'): ?>
            <!-- Formulário de Importação de Arquivo -->
            <div class="bg-gray-900 p-6 rounded-2xl border border-gray-800 shadow-xl">
                <h2 class="text-sm font-semibold uppercase text-blue-500 tracking-wider mb-4 flex items-center gap-2">
                    <span>📄</span> Importar Pauta (Excel .xlsx)
                </h2>
                <form id="form-importar" method="POST" enctype="multipart/form-data" class="space-y-4">
                    <input type="hidden" name="action" value="importar_pdf">
                    <div>
                        <label class="block text-xs text-gray-400 mb-1">Selecione o arquivo da Pauta (Excel .xlsx)</label>
                        <input type="file" name="arquivo_pdf" accept=".xlsx" required class="w-full bg-gray-800 border border-gray-700 rounded-lg px-2.5 py-1.5 text-xs text-white file:bg-gray-700 file:border-0 file:text-white file:text-xs file:font-semibold file:px-2.5 file:py-1 file:rounded file:mr-2 file:hover:bg-gray-600">
                    </div>
                    <button type="submit" id="btn-importar" class="w-full bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold py-2.5 rounded-lg transition flex items-center justify-center gap-2">
                        <span id="btn-importar-texto">Importar Dados</span>
                    </button>
                </form>
            </div>
            <?php endif; ?>

            <!-- Cadastro Manual de Periciados -->
            <div class="bg-gray-900 p-6 rounded-2xl border border-gray-800 shadow-xl">
                <h2 class="text-sm font-semibold uppercase text-gray-400 tracking-wider mb-4">Adicionar Periciado Manual</h2>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="agendar_unico">
                    <div>
                        <label class="block text-xs text-gray-400 mb-1">Nome do Periciado</label>
                        <input type="text" name="nome" required placeholder="Nome Completo" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-400 mb-1">Processo</label>
                        <input type="text" name="processo" placeholder="0000000-00.2026.4.05.8000" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-400 mb-1">CPF do Periciado</label>
                        <input type="text" name="cpf" placeholder="000.000.000-00" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-400 mb-1">Nome do Perito</label>
                        <input type="text" name="perito" placeholder="Nome do Médico/Perito" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
                    </div>
                    <button type="submit" class="w-full bg-gray-800 hover:bg-gray-750 border border-gray-700 text-white text-xs font-semibold py-2.5 rounded-lg transition">
                        Agendar
                    </button>
                </form>
            </div>

            <!-- Configuração do Vídeo da TV -->
            <div class="bg-gray-900 p-6 rounded-2xl border border-gray-800 shadow-xl">
                <h2 class="text-sm font-semibold uppercase text-gray-400 tracking-wider mb-4 flex items-center gap-2">
                    <span>📺</span> Vídeo de Fundo da TV
                </h2>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="set_yt_url">
                    <div>
                        <label class="block text-xs text-gray-400 mb-1">Link do Vídeo (YouTube)</label>
                        <input type="url" name="yt_url" value="<?= htmlspecialchars($yt_url) ?>" required placeholder="https://www.youtube.com/watch?v=..." class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
                    </div>
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold py-2.5 rounded-lg transition">
                        Atualizar Vídeo
                    </button>
                </form>
                <p class="text-[10px] text-gray-500 mt-3 leading-normal">
                    ⚠️ <strong>Nota:</strong> Certifique-se de usar um vídeo público do YouTube que permita reprodução incorporada (incorporação). Vídeos com restrições de idade ou proteção contra bots não carregarão no painel.
                </p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Área de Listas (Fila de Hoje) -->
        <div class="<?= in_array($_SESSION['usuario_role'], ['admin', 'supervisor']) ? 'lg:col-span-2' : '' ?> space-y-6">
            <?php if (!in_array($_SESSION['usuario_role'], ['admin', 'supervisor']) && $message): ?>
                <div class="p-3 rounded-lg border text-xs text-center font-medium <?= $message_type === 'success' ? 'bg-green-950/40 border-green-900/80 text-green-300' : ($message_type === 'info' ? 'bg-blue-950/40 border-blue-900/80 text-blue-300' : 'bg-red-950/40 border-red-900/80 text-red-300') ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>
            <!-- Filtro de Data da Pauta -->
            <div class="bg-gray-900 p-4 rounded-2xl border border-gray-800 shadow-xl flex flex-col sm:flex-row justify-between items-center gap-4">
                <div class="flex items-center gap-2">
                    <span class="text-blue-400 font-semibold text-sm">📅</span>
                    <span class="text-xs font-semibold uppercase text-gray-400 tracking-wider">Visualizando Pauta de:</span>
                </div>
                <form method="GET" action="recepcao.php" class="flex items-center gap-2 w-full sm:w-auto">
                    <input type="date" name="data_filtro" id="data-filtro-input" value="<?= htmlspecialchars($dataFiltro) ?>" onchange="this.form.submit()" class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-1.5 text-xs text-white focus:outline-none focus:border-blue-500 w-full sm:w-auto">
                    <button type="button" onclick="irParaHoje()" class="bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold px-3 py-1.5 rounded-lg transition shrink-0">
                        Hoje
                    </button>
                    <noscript>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold px-3 py-1.5 rounded-lg">Filtrar</button>
                    </noscript>
                </form>
            </div>
            
            <?php if ($_SESSION['usuario_role'] === 'recepcao' && !$pauta_liberada && $dataFiltro === date('Y-m-d')): ?>
                <div class="p-4 rounded-xl border bg-amber-950/20 border-amber-900/50 text-amber-300 text-xs font-semibold flex items-center gap-3">
                    <span class="text-lg">⚠️</span>
                    <div>
                        <h4 class="font-bold uppercase tracking-wider text-amber-400">Pauta Pendente de Homologação</h4>
                        <p class="text-amber-500 mt-0.5 font-normal leading-relaxed">
                            A pauta do dia <?= date('d/m/Y', strtotime($dataFiltro)) ?> ainda não foi homologada pelo supervisor da recepção. Os check-ins de pacientes estão temporariamente bloqueados.
                        </p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- 1. Agendados (Ainda não chegaram) -->
            <div class="bg-gray-900 p-6 rounded-2xl border border-gray-800 shadow-xl">
                <form method="POST" id="form-agendados">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-4">
                        <h2 class="text-sm font-semibold uppercase text-amber-400 tracking-wider flex items-center gap-2">
                            <span>Agendados Para <?= date('d/m/Y', strtotime($dataFiltro)) ?></span>
                            <span class="bg-amber-950 text-amber-400 text-xs px-2.5 py-0.5 rounded-full"><?= count($agendados) ?></span>
                        </h2>
                        
                        <div class="flex flex-wrap gap-2 w-full sm:w-auto">
                            <?php if ($permite_checkin): ?>
                                <button type="submit" name="action" value="confirmar_multiplos" id="btn-confirmar-selecionados" disabled onclick="return confirm('Confirmar chegada de todos os periciados selecionados de uma só vez?');" class="bg-emerald-950/40 hover:bg-emerald-900/40 border border-emerald-900/60 text-emerald-400 text-xs px-3 py-1.5 rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed flex-1 sm:flex-none">
                                    ✅ Confirmar Selecionados
                                </button>
                            <?php endif; ?>
                            <?php if (in_array($_SESSION['usuario_role'], ['admin', 'supervisor'])): ?>
                                <button type="submit" name="action" value="remover_multiplos" id="btn-excluir-selecionados" disabled onclick="return confirm('Excluir periciados selecionados?');" class="bg-red-950/40 hover:bg-red-900/40 border border-red-900/60 text-red-400 text-xs px-3 py-1.5 rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed flex-1 sm:flex-none">
                                    🗑️ Excluir Selecionados
                                </button>
                                <button type="button" onclick="limparPautaHoje()" class="bg-orange-950/40 hover:bg-orange-900/40 border border-orange-900/60 text-orange-400 text-xs px-3 py-1.5 rounded-lg transition flex-1 sm:flex-none">
                                    ⚠️ Limpar Pauta
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Input para busca textual instantânea -->
                    <div class="mb-4">
                        <input type="text" id="filtro-busca" placeholder="🔍 Buscar por nome, processo ou perito..." class="w-full bg-gray-950 border border-gray-700 rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-blue-500">
                    </div>

                    <!-- Alerta de agendamentos em outras datas -->
                    <div id="alerta-outras-datas" class="hidden mb-4 p-4 rounded-xl border bg-blue-950/40 border-blue-900/60 text-blue-300 text-xs space-y-2 animate-fade-in">
                        <div class="font-bold flex items-center gap-1.5 uppercase text-blue-400">
                            <span>📅</span> Agendamento Encontrado em Outra Data!
                        </div>
                        <div id="lista-outras-datas" class="space-y-1.5 font-medium">
                            <!-- Injetado via JS -->
                        </div>
                    </div>

                    <div class="overflow-x-auto max-h-[300px] overflow-y-auto">
                        <table class="w-full text-left text-sm text-gray-300">
                            <thead>
                                <tr class="border-b border-gray-800 text-xs text-gray-400 uppercase">
                                    <?php if ($permite_checkin): ?>
                                        <th class="pb-2 w-8">
                                            <input type="checkbox" id="chk-select-all" class="rounded bg-gray-800 border-gray-700 text-blue-500 focus:ring-0 focus:ring-offset-0">
                                        </th>
                                    <?php endif; ?>
                                    <th class="pb-2">Nome / Processo / CPF</th>
                                    <th class="pb-2">Perito</th>
                                    <th class="pb-2 text-right">Ação</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-800/50" id="table-agendados-body">
                                <?php if (empty($agendados)): ?>
                                    <tr class="row-sem-registro">
                                        <td colspan="<?= ($_SESSION['usuario_role'] === 'admin' || $dataFiltro === date('Y-m-d')) ? 4 : 3 ?>" class="py-4 text-center text-xs text-gray-500">
                                            <?= $_SESSION['usuario_role'] === 'admin' ? 'Nenhum periciado agendado. Faça o upload do arquivo da Pauta ao lado.' : 'Nenhum periciado agendado para esta data.' ?>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($agendados as $item): ?>
                                        <tr onclick="if (event.target.tagName !== 'INPUT' && event.target.tagName !== 'BUTTON' && !event.target.closest('button') && !event.target.closest('a')) mostrarModalDetalhes({ nome: '<?= htmlspecialchars($item['nome'], ENT_QUOTES) ?>', cpf: '<?= htmlspecialchars($item['cpf'] ?? '---', ENT_QUOTES) ?>', processo: '<?= htmlspecialchars($item['processo'], ENT_QUOTES) ?>', perito: '<?= htmlspecialchars($item['perito'], ENT_QUOTES) ?>', data_pauta: '<?= date('d/m/Y', strtotime($item['data_pauta'])) ?>', status: '<?= $item['status'] ?>', chegada_em: '<?= $item['chegada_em'] ? date('H:i:s', strtotime($item['chegada_em'])) : '---' ?>' })" class="row-agendado cursor-pointer hover:bg-gray-850/40 transition">
                                            <?php if ($permite_checkin): ?>
                                                <td class="py-3">
                                                    <input type="checkbox" name="selecionados[]" value="<?= $item['id'] ?>" class="chk-item rounded bg-gray-800 border-gray-700 text-blue-500 focus:ring-0 focus:ring-offset-0">
                                                </td>
                                            <?php endif; ?>
                                            <td class="py-3">
                                                <div class="font-semibold text-white truncate max-w-[200px]"><?= htmlspecialchars($item['nome']) ?></div>
                                                <div class="text-[10px] text-gray-500 font-mono">
                                                    <?= htmlspecialchars($item['processo'] ?: 'Sem processo') ?>
                                                    <?php if (!empty($item['cpf'])): ?>
                                                        <span class="text-blue-400 ml-1.5">| CPF: <?= htmlspecialchars($item['cpf']) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="py-3 text-xs text-gray-300 truncate max-w-[150px]"><?= htmlspecialchars($item['perito'] ?: 'Não associado') ?></td>
                                            <td class="py-3 text-right flex items-center justify-end gap-1.5">
                                                <?php if ($permite_checkin): ?>
                                                    <button type="button" onclick="confirmarChegada(<?= $item['id'] ?>)" class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold px-2.5 py-1 rounded transition">
                                                        Confirmar Chegada
                                                    </button>
                                                <?php endif; ?>
                                                <?php if (in_array($_SESSION['usuario_role'], ['admin', 'supervisor'])): ?>
                                                    <button type="button" onclick="removerIndividual(<?= $item['id'] ?>)" class="bg-gray-800 hover:bg-gray-700 text-gray-400 text-xs px-2.5 py-1 rounded border border-gray-700 transition">
                                                        Remover
                                                    </button>
                                                    <button type="button" onclick="iniciarEdicao(<?= $item['id'] ?>, '<?= htmlspecialchars(addslashes($item['nome'])) ?>', '<?= htmlspecialchars(addslashes($item['processo'])) ?>', '<?= htmlspecialchars(addslashes($item['perito'])) ?>', '<?= htmlspecialchars(addslashes($item['cpf'] ?? '')) ?>')" title="Editar dados" class="bg-gray-800 hover:bg-gray-750 text-gray-300 text-xs px-2.5 py-1 rounded border border-gray-700 transition">
                                                        ✏️
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </form>

                <!-- Formulário oculto para envio de chegada individual -->
                <form id="form-chegada-unica" method="POST" class="hidden">
                    <input type="hidden" name="action" value="confirmar_chegada">
                    <input type="hidden" name="id" id="chegada-id">
                </form>
                <form id="form-remover-unico" method="POST" class="hidden">
                    <input type="hidden" name="action" value="remover">
                    <input type="hidden" name="id" id="remover-id">
                </form>
                <form id="form-limpar-pauta" method="POST" class="hidden">
                    <input type="hidden" name="action" value="limpar_pauta_hoje">
                </form>
            </div>

            <!-- 2. Presentes (Fila de espera ativa para chamada do perito) -->
            <div class="bg-gray-900 p-6 rounded-2xl border border-gray-800 shadow-xl">
                <h2 class="text-sm font-semibold uppercase text-emerald-400 tracking-wider mb-4 flex justify-between items-center">
                    <span>Aguardando Chamada (Fila de Espera)</span>
                    <?php if (in_array($_SESSION['usuario_role'], ['admin', 'supervisor'])): ?>
                    <div class="flex flex-col sm:flex-row gap-3 mb-4">
                        <button type="submit" name="action" value="remover_multiplos" id="btn-remover-presentes" disabled onclick="return confirm('Retornar todos os periciados selecionados para a lista de agendados?');" class="bg-amber-950/40 hover:bg-amber-900/40 border border-amber-900/60 text-amber-400 text-xs px-3 py-1.5 rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed flex-1 sm:flex-none">
                            🔄 Voltar Selecionados para Agendados
                        </button>
                    </div>
                    <?php endif; ?>
                </h2>

                    <div class="overflow-x-auto max-h-[300px] overflow-y-auto">
                        <table class="w-full text-left text-sm text-gray-300">
                            <thead>
                                <tr class="border-b border-gray-800 text-xs text-gray-400 uppercase">
                                    <?php if (in_array($_SESSION['usuario_role'], ['admin', 'supervisor'])): ?>
                                        <th class="pb-2 w-8">
                                            <input type="checkbox" id="chk-select-all-presentes" class="rounded bg-gray-800 border-gray-700 text-emerald-500 focus:ring-0 focus:ring-offset-0">
                                        </th>
                                    <?php endif; ?>
                                    <th class="pb-2">Senha</th>
                                    <th class="pb-2">Nome / Processo</th>
                                    <th class="pb-2">Perito</th>
                                    <th class="pb-2">Chegada</th>
                                    <?php if (in_array($_SESSION['usuario_role'], ['admin', 'supervisor'])): ?>
                                        <th class="pb-2 text-right">Ação</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-800/50">
                                <?php if (empty($presentes)): ?>
                                    <tr>
                                        <td colspan="<?= in_array($_SESSION['usuario_role'], ['admin', 'supervisor']) ? 6 : 4 ?>" class="py-4 text-center text-xs text-gray-500">Ninguém aguardando no momento.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($presentes as $item): ?>
                                        <tr onclick="if (event.target.tagName !== 'INPUT' && event.target.tagName !== 'BUTTON' && !event.target.closest('button') && !event.target.closest('a')) mostrarModalDetalhes({ nome: '<?= htmlspecialchars($item['nome'], ENT_QUOTES) ?>', cpf: '<?= htmlspecialchars($item['cpf'] ?? '---', ENT_QUOTES) ?>', processo: '<?= htmlspecialchars($item['processo'], ENT_QUOTES) ?>', perito: '<?= htmlspecialchars($item['perito'], ENT_QUOTES) ?>', data_pauta: '<?= date('d/m/Y', strtotime($item['data_pauta'])) ?>', status: '<?= $item['status'] ?>', chegada_em: '<?= $item['chegada_em'] ? date('H:i:s', strtotime($item['chegada_em'])) : '---' ?>' })" class="cursor-pointer hover:bg-gray-850/40 transition">
                                            <?php if (in_array($_SESSION['usuario_role'], ['admin', 'supervisor'])): ?>
                                                <td class="py-3">
                                                    <input type="checkbox" name="selecionados[]" value="<?= $item['id'] ?>" class="chk-presente-item rounded bg-gray-800 border-gray-700 text-emerald-500 focus:ring-0 focus:ring-offset-0">
                                                </td>
                                            <?php endif; ?>
                                            <td class="py-3 font-mono font-bold text-blue-400"><?= htmlspecialchars($item['senha']) ?></td>
                                            <td class="py-3">
                                                <div class="font-semibold text-white truncate max-w-[200px]"><?= htmlspecialchars($item['nome']) ?></div>
                                                <div class="text-[10px] text-gray-500 font-mono">
                                                    <?= htmlspecialchars($item['processo'] ?: 'Sem processo') ?>
                                                    <?php if (!empty($item['cpf'])): ?>
                                                        <span class="text-emerald-400 ml-1.5">| CPF: <?= htmlspecialchars($item['cpf']) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="py-3 text-xs text-gray-300 truncate max-w-[120px]"><?= htmlspecialchars($item['perito'] ?: 'Não associado') ?></td>
                                            <td class="py-3 text-xs text-gray-400"><?= date('H:i:s', strtotime($item['chegada_em'])) ?></td>
                                            <?php if (in_array($_SESSION['usuario_role'], ['admin', 'supervisor'])): ?>
                                                <td class="py-3 text-right flex items-center justify-end gap-1.5">
                                                    <button type="button" onclick="removerIndividual(<?= $item['id'] ?>)" class="bg-gray-800 hover:bg-gray-750 text-gray-400 text-xs px-2.5 py-1 rounded border border-gray-700 transition">
                                                        Remover
                                                    </button>
                                                    <button type="button" onclick="iniciarEdicao(<?= $item['id'] ?>, '<?= htmlspecialchars(addslashes($item['nome'])) ?>', '<?= htmlspecialchars(addslashes($item['processo'])) ?>', '<?= htmlspecialchars(addslashes($item['perito'])) ?>', '<?= htmlspecialchars(addslashes($item['cpf'] ?? '')) ?>')" title="Editar dados" class="bg-gray-800 hover:bg-gray-750 text-gray-300 text-xs px-2.5 py-1 rounded border border-gray-700 transition">
                                                        ✏️
                                                    </button>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
            </div>

            <!-- 3. Atendimentos Chamados e Concluídos de hoje -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                
                <!-- Lista de Chamados (Em atendimento nas salas) -->
                <div class="bg-gray-900 p-6 rounded-2xl border border-gray-800 shadow-xl">
                    <h3 class="text-sm font-semibold uppercase text-blue-400 tracking-wider mb-4">Em Atendimento</h3>
                    <div class="space-y-3 max-h-[250px] overflow-y-auto">
                        <?php if (empty($chamados)): ?>
                            <p class="text-xs text-gray-500 text-center py-4">Ninguém em atendimento.</p>
                        <?php else: ?>
                            <?php foreach ($chamados as $item): ?>
                                <div class="bg-gray-950/60 border border-gray-800 p-3 rounded-xl flex justify-between items-center">
                                    <div class="truncate flex-1 pr-2">
                                        <div class="text-xs font-semibold text-white truncate"><?= htmlspecialchars($item['nome']) ?></div>
                                        <div class="text-[10px] text-gray-400 truncate font-mono">Processo: <?= htmlspecialchars($item['processo']) ?></div>
                                        <div class="text-[10px] text-gray-400 truncate">Perito: <?= htmlspecialchars($item['perito']) ?></div>
                                        <div class="text-[10px] text-emerald-400 mt-0.5"><?= htmlspecialchars($item['sala']) ?></div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-sm font-black text-blue-400"><?= htmlspecialchars($item['senha']) ?></div>
                                        <div class="text-[9px] text-gray-500"><?= date('H:i', strtotime($item['chamado_em'])) ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Lista de Concluídos (Finalizados) -->
                <div class="bg-gray-900 p-6 rounded-2xl border border-gray-800 shadow-xl">
                    <h3 class="text-sm font-semibold uppercase text-gray-400 tracking-wider mb-4">Finalizados</h3>
                    <div class="space-y-3 max-h-[250px] overflow-y-auto">
                        <?php if (empty($concluidos)): ?>
                            <p class="text-xs text-gray-500 text-center py-4">Nenhum atendimento finalizado.</p>
                        <?php else: ?>
                            <?php foreach ($concluidos as $item): ?>
                                <div class="bg-gray-950/30 border border-gray-800/40 p-3 rounded-xl flex justify-between items-center opacity-70">
                                    <div class="truncate flex-1 pr-2">
                                        <div class="text-xs font-semibold text-gray-300 truncate"><?= htmlspecialchars($item['nome']) ?></div>
                                        <div class="text-[10px] text-gray-500 truncate">Perito: <?= htmlspecialchars($item['perito']) ?></div>
                                        <div class="text-[10px] text-gray-500 mt-0.5"><?= htmlspecialchars($item['sala']) ?></div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-sm font-bold text-gray-400"><?= htmlspecialchars($item['senha']) ?></div>
                                        <div class="text-[9px] text-gray-600 font-semibold">Fim: <?= date('H:i', strtotime($item['chamado_em'])) ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <!-- Modal de Edição de Agendamento -->
    <div id="modal-editar" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden flex items-center justify-center z-50 p-4">
        <div class="bg-gray-900 border border-gray-800 rounded-2xl w-full max-w-md p-6 shadow-2xl relative">
            <h3 class="text-sm font-bold uppercase text-blue-500 tracking-wider mb-4">📝 Editar Periciado</h3>
            
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="editar_atendimento">
                <input type="hidden" name="id" id="edit-id">
                
                <div>
                    <label class="block text-xs text-gray-400 mb-1 font-semibold uppercase">Nome do Paciente</label>
                    <input type="text" name="nome" id="edit-nome" required class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
                </div>
                
                <div>
                    <label class="block text-xs text-gray-400 mb-1 font-semibold uppercase">Processo</label>
                    <input type="text" name="processo" id="edit-processo" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
                </div>
                
                <div>
                    <label class="block text-xs text-gray-400 mb-1 font-semibold uppercase">CPF do Paciente</label>
                    <input type="text" name="cpf" id="edit-cpf" placeholder="000.000.000-00" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
                </div>
                
                <div>
                    <label class="block text-xs text-gray-400 mb-1 font-semibold uppercase">Perito</label>
                    <input type="text" name="perito" id="edit-perito" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
                </div>
                
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" onclick="fecharModalEdicao()" class="bg-gray-800 hover:bg-gray-750 border border-gray-700 text-gray-300 text-xs font-semibold px-4 py-2 rounded-lg transition">
                        Cancelar
                    </button>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold px-4 py-2 rounded-lg transition">
                        Salvar Alterações
                    </button>
                </div>
            </form>
        </div>
    </div>
    </main>

    <!-- Rodapé Flutuante Discreto -->
    <div class="fixed bottom-2 right-4 text-[9px] text-gray-600 font-medium z-50 pointer-events-none">
        &copy; <?= date('Y') ?> - Desenvolvido pela DTI - Justiça Federal em Alagoas
    </div>

    <!-- Script JavaScript local para controle de seleção e filtro -->
    <script>
        const chkSelectAll = document.getElementById('chk-select-all');
        const chkItems = document.querySelectorAll('.chk-item');
        const btnExcluir = document.getElementById('btn-excluir-selecionados');
        const btnConfirmar = document.getElementById('btn-confirmar-selecionados');
        const filtroBusca = document.getElementById('filtro-busca');
        const rows = document.querySelectorAll('.row-agendado');

        // Alterna a disponibilidade dos botões em lote com base na seleção
        function toggleBulkDeleteButton() {
            const checkedCount = document.querySelectorAll('.chk-item:checked').length;
            btnExcluir.disabled = checkedCount === 0;
            if (btnConfirmar) {
                btnConfirmar.disabled = checkedCount === 0;
            }
        }

        if (chkSelectAll) {
            chkSelectAll.addEventListener('change', function() {
                // Seleciona apenas os elementos visíveis para facilitar a exclusão filtrada
                rows.forEach(row => {
                    if (row.style.display !== 'none') {
                        const chk = row.querySelector('.chk-item');
                        if (chk) chk.checked = this.checked;
                    }
                });
                toggleBulkDeleteButton();
            });
        }

        chkItems.forEach(item => {
            item.addEventListener('change', function() {
                if (!this.checked) {
                    chkSelectAll.checked = false;
                } else {
                    const allChecked = document.querySelectorAll('.chk-item:checked').length === chkItems.length;
                    chkSelectAll.checked = allChecked;
                }
                toggleBulkDeleteButton();
            });
        });

        // Confirma chegada individual preenchendo formulário oculto
        function confirmarChegada(id) {
            document.getElementById('chegada-id').value = id;
            document.getElementById('form-chegada-unica').submit();
        }

        // Submete ação para limpar agendados do dia
        function limparPautaHoje() {
            if (confirm('Tem certeza de que deseja limpar a pauta de hoje? Isso excluirá todos os periciados agendados que ainda não tiveram sua chegada confirmada.')) {
                document.getElementById('form-limpar-pauta').submit();
            }
        }

        // Filtro em tempo real digitado pelo usuário e busca de outras datas
        let timeoutBuscaOutroDia = null;
        if (filtroBusca) {
            filtroBusca.addEventListener('input', function() {
                const query = this.value.toLowerCase().trim();
                const queryClean = query.replace(/[^a-z0-9]/g, '');
                
                rows.forEach(row => {
                    const text = row.innerText.toLowerCase();
                    const textClean = text.replace(/[^a-z0-9]/g, '');
                    
                    if (text.includes(query) || (queryClean && textClean.includes(queryClean))) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                        // Desmarca os itens ocultos para não excluir por acidente
                        const chk = row.querySelector('.chk-item');
                        if (chk) chk.checked = false;
                    }
                });
                
                toggleBulkDeleteButton();

                // Busca em outras datas via AJAX
                clearTimeout(timeoutBuscaOutroDia);
                const alertaEl = document.getElementById('alerta-outras-datas');
                const listaEl = document.getElementById('lista-outras-datas');
                
                if (query.length >= 3) {
                    timeoutBuscaOutroDia = setTimeout(() => {
                        fetch(`api_verificar_outro_dia.php?busca=${encodeURIComponent(query)}&hoje=<?= $dataFiltro ?>`)
                            .then(res => res.json())
                            .then(data => {
                                if (data && data.length > 0) {
                                    listaEl.innerHTML = '';
                                    data.forEach(item => {
                                        const div = document.createElement('div');
                                        div.className = 'bg-blue-900/30 border border-blue-800/40 p-2.5 rounded-lg flex flex-col sm:flex-row sm:justify-between sm:items-center gap-1.5';
                                        div.innerHTML = `
                                            <div>
                                                <strong class="text-white font-bold">${item.nome}</strong>
                                                <span class="text-blue-400 font-medium ml-1.5">| Perito: ${item.perito || 'Não associado'}</span>
                                            </div>
                                            <div class="flex items-center gap-2 text-[10px]">
                                                <span class="bg-blue-950 text-blue-400 px-2 py-0.5 rounded font-bold uppercase">${item.status_formatado}</span>
                                                <span class="bg-gray-800 text-gray-300 px-2 py-0.5 rounded font-mono font-bold">${item.data_pauta_formatada}</span>
                                            </div>
                                        `;
                                        listaEl.appendChild(div);
                                    });
                                    alertaEl.classList.remove('hidden');
                                } else {
                                    alertaEl.classList.add('hidden');
                                    listaEl.innerHTML = '';
                                }
                            })
                            .catch(() => {
                                alertaEl.classList.add('hidden');
                            });
                    }, 350); // Debounce de 350ms
                } else {
                    alertaEl.classList.add('hidden');
                    listaEl.innerHTML = '';
                }
            });
        }

        // Controle de seleção para a Fila de Espera (Presentes)
        const chkSelectAllPresentes = document.getElementById('chk-select-all-presentes');
        const chkPresentesItems = document.querySelectorAll('.chk-presente-item');
        const btnRemoverPresentes = document.getElementById('btn-remover-presentes');

        function toggleBulkRemoverPresentesButton() {
            if (btnRemoverPresentes) {
                const checkedCount = document.querySelectorAll('.chk-presente-item:checked').length;
                btnRemoverPresentes.disabled = checkedCount === 0;
            }
        }

        if (chkSelectAllPresentes) {
            chkSelectAllPresentes.addEventListener('change', function() {
                chkPresentesItems.forEach(chk => {
                    chk.checked = this.checked;
                });
                toggleBulkRemoverPresentesButton();
            });
        }

        chkPresentesItems.forEach(item => {
            item.addEventListener('change', function() {
                if (!this.checked) {
                    if (chkSelectAllPresentes) chkSelectAllPresentes.checked = false;
                } else {
                    const allChecked = document.querySelectorAll('.chk-presente-item:checked').length === chkPresentesItems.length;
                    if (chkSelectAllPresentes) chkSelectAllPresentes.checked = allChecked;
                }
                toggleBulkRemoverPresentesButton();
            });
        });

        // Remove individualmente da fila de espera e retorna para agendados
        function removerIndividual(id) {
            if (confirm('Deseja remover este periciado da fila de espera e retorná-lo para a lista de agendados?')) {
                document.getElementById('remover-id').value = id;
                document.getElementById('form-remover-unico').submit();
            }
        }

        // Inicia a edição exibindo o modal e preenchendo os campos
        function iniciarEdicao(id, nome, processo, perito, cpf) {
            document.getElementById('edit-id').value = id;
            document.getElementById('edit-nome').value = nome;
            document.getElementById('edit-processo').value = processo;
            document.getElementById('edit-cpf').value = cpf || '';
            document.getElementById('edit-perito').value = perito;
            document.getElementById('modal-editar').classList.remove('hidden');
        }

        // Fecha o modal de edição
        function fecharModalEdicao() {
            document.getElementById('modal-editar').classList.add('hidden');
        }

        // Exibe loading e desabilita o botão ao importar pauta
        const formImportar = document.getElementById('form-importar');
        const btnImportar = document.getElementById('btn-importar');
        const btnImportarTexto = document.getElementById('btn-importar-texto');

        if (formImportar) {
            formImportar.addEventListener('submit', function() {
                // Desabilita o botão para evitar cliques múltiplos
                btnImportar.disabled = true;
                btnImportar.classList.remove('bg-blue-600', 'hover:bg-blue-700');
                btnImportar.classList.add('bg-blue-800', 'cursor-not-allowed', 'opacity-75');
                
                // Altera o texto e adiciona um spinner de carregamento SVG
                btnImportarTexto.innerHTML = `
                    <svg class="animate-spin h-4 w-4 text-white inline-block mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>Processando Arquivo...</span>
                `;
            });
        }

        // Modal de Detalhes do Periciado
        function mostrarModalDetalhes(dados) {
            document.getElementById('modal-nome').textContent = dados.nome || '---';
            document.getElementById('modal-cpf').textContent = dados.cpf || '---';
            document.getElementById('modal-processo').textContent = dados.processo || '---';
            document.getElementById('modal-perito').textContent = dados.perito || '---';
            document.getElementById('modal-data').textContent = dados.data_pauta || '---';
            document.getElementById('modal-chegada').textContent = dados.chegada_em || '---';
            
            const statusEl = document.getElementById('modal-status');
            statusEl.innerHTML = '';
            const st = dados.status;
            let badge = '';
            if (st === 'concluido') {
                badge = '<span class="bg-gray-850 text-gray-400 text-[10px] px-2.5 py-0.5 rounded-full border border-gray-700 uppercase font-bold">Concluído</span>';
            } else if (st === 'atendimento') {
                badge = '<span class="bg-blue-950 text-blue-400 text-[10px] px-2.5 py-0.5 rounded-full border border-blue-900/50 uppercase font-bold">Em Exame</span>';
            } else if (st === 'chamada') {
                badge = '<span class="bg-amber-950/40 text-amber-400 text-[10px] px-2.5 py-0.5 rounded-full border border-amber-900/40 uppercase font-bold">Chamado</span>';
            } else if (st === 'presente' || st === 'fila') {
                badge = '<span class="bg-emerald-950 text-emerald-400 text-[10px] px-2.5 py-0.5 rounded-full border border-emerald-900/50 uppercase font-bold">Aguardando</span>';
            } else {
                badge = '<span class="bg-gray-900 text-gray-500 text-[10px] px-2.5 py-0.5 rounded-full border border-gray-800 uppercase font-bold">Agendado</span>';
            }
            statusEl.innerHTML = badge;

            const modal = document.getElementById('modal-detalhes');
            modal.classList.remove('hidden');
            setTimeout(() => {
                modal.firstElementChild.classList.remove('scale-95');
                modal.firstElementChild.classList.add('scale-100');
            }, 10);
        }

        function fecharModalDetalhes() {
            const modal = document.getElementById('modal-detalhes');
            modal.firstElementChild.classList.remove('scale-100');
            modal.firstElementChild.classList.add('scale-95');
            setTimeout(() => {
                modal.classList.add('hidden');
            }, 150);
        }

        // Vai direto para o dia de hoje
        function irParaHoje() {
            const input = document.getElementById('data-filtro-input');
            if (input) {
                input.value = '<?= date('Y-m-d') ?>';
                input.form.submit();
            }
        }
    </script>

    <!-- Modal de Detalhes do Periciado -->
    <div id="modal-detalhes" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-950/80 backdrop-blur-sm transition-opacity duration-300">
        <div class="bg-gray-900 border border-gray-800 rounded-3xl shadow-2xl max-w-lg w-full overflow-hidden transform scale-95 transition-transform duration-300">
            <div class="p-6 border-b border-gray-800 flex justify-between items-center bg-gray-900/50">
                <h3 class="text-sm font-bold uppercase tracking-wider text-blue-500 flex items-center gap-2">
                    🔎 Detalhes do Agendamento
                </h3>
                <button type="button" onclick="fecharModalDetalhes()" class="text-gray-400 hover:text-white transition text-lg">&times;</button>
            </div>
            <div class="p-6 space-y-4 text-sm text-gray-300">
                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <span class="block text-[10px] uppercase font-bold text-gray-500 tracking-wider">Nome do Periciado</span>
                        <strong id="modal-nome" class="text-base text-white font-black block mt-0.5">---</strong>
                    </div>
                    <div>
                        <span class="block text-[10px] uppercase font-bold text-gray-500 tracking-wider">CPF</span>
                        <span id="modal-cpf" class="font-mono text-white block mt-0.5">---</span>
                    </div>
                    <div>
                        <span class="block text-[10px] uppercase font-bold text-gray-500 tracking-wider">Número do Processo</span>
                        <span id="modal-processo" class="font-mono text-white block mt-0.5">---</span>
                    </div>
                    <div>
                        <span class="block text-[10px] uppercase font-bold text-gray-500 tracking-wider">Médico Perito</span>
                        <span id="modal-perito" class="text-white font-medium block mt-0.5">---</span>
                    </div>
                    <div>
                        <span class="block text-[10px] uppercase font-bold text-gray-500 tracking-wider">Data da Pauta</span>
                        <span id="modal-data" class="text-white block mt-0.5">---</span>
                    </div>
                    <div>
                        <span class="block text-[10px] uppercase font-bold text-gray-500 tracking-wider">Status Atual</span>
                        <span id="modal-status" class="block mt-0.5">---</span>
                    </div>
                    <div>
                        <span class="block text-[10px] uppercase font-bold text-gray-500 tracking-wider">Horário de Chegada</span>
                        <span id="modal-chegada" class="font-mono text-white block mt-0.5">---</span>
                    </div>
                </div>
            </div>
            <div class="p-4 border-t border-gray-800 bg-gray-950/40 flex justify-end">
                <button type="button" onclick="fecharModalDetalhes()" class="bg-gray-850 hover:bg-gray-700 text-white text-xs font-semibold px-4 py-2 rounded-xl transition border border-gray-750">
                    Fechar Detalhes
                </button>
            </div>
        </div>
    </div>
</body>
</html>
