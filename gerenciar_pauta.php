<?php
require_once 'banco.php';
session_start();

// Proteção da página: apenas administradores e supervisores
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['usuario_role'], ['admin', 'supervisor'])) {
    header("Location: dashboard.php");
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

$sys_name = SYS_NAME;

// Função auxiliar de senha PE-xxx
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

// Lógica de Processamento de Ações POST
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    // 1. Excluir Selecionados
    if ($action === 'excluir_selecionados') {
        $ids = [];
        if (isset($_POST['selecionados']) && is_array($_POST['selecionados'])) {
            $ids = array_map('intval', $_POST['selecionados']);
        }
        
        if (!empty($ids)) {
            $in_query = implode(',', $ids);
            try {
                $pst = $pdo->query("SELECT id, nome FROM atendimentos WHERE id IN ($in_query)");
                while ($row = $pst->fetch()) {
                    registrarLog($pdo, 'Excluir Agendamento', "Paciente: {$row['nome']} (Em Lote)", $row['id']);
                }
                $pdo->exec("DELETE FROM atendimentos WHERE id IN ($in_query)");
                $message = "Sucesso! " . count($ids) . " registros foram excluídos permanentemente.";
                $message_type = 'success';
            } catch (PDOException $e) {
                $message = "Erro ao excluir registros: " . $e->getMessage();
                $message_type = 'error';
            }
        } else {
            $message = "Erro: Nenhum atendimento foi selecionado para exclusão.";
            $message_type = 'error';
        }
    }
    
    // 2. Excluir por Período (Dia ou Mês)
    elseif ($action === 'excluir_periodo') {
        $tipo = $_POST['periodo_tipo'] ?? ''; // 'dia' ou 'mes'
        $data_alvo = $_POST['data_alvo'] ?? '';
        $mes_alvo = $_POST['mes_alvo'] ?? ''; // YYYY-MM
        
        if ($tipo === 'dia' && !empty($data_alvo)) {
            try {
                $stmt = $pdo->prepare("DELETE FROM atendimentos WHERE data_pauta = ?");
                $stmt->execute([$data_alvo]);
                $count = $stmt->rowCount();
                registrarLog($pdo, 'Excluir Pauta Dia', "Data: $data_alvo, Registros apagados: $count");
                $message = "Sucesso! " . $count . " perícias do dia " . date('d/m/Y', strtotime($data_alvo)) . " foram excluídas.";
                $message_type = 'success';
            } catch (PDOException $e) {
                $message = "Erro ao excluir pauta do dia: " . $e->getMessage();
                $message_type = 'error';
            }
        } elseif ($tipo === 'mes' && !empty($mes_alvo)) {
            try {
                $stmt = $pdo->prepare("DELETE FROM atendimentos WHERE DATE_FORMAT(data_pauta, '%Y-%m') = ?");
                $stmt->execute([$mes_alvo]);
                $count = $stmt->rowCount();
                registrarLog($pdo, 'Excluir Pauta Mes', "Mês: $mes_alvo, Registros apagados: $count");
                $message = "Sucesso! " . $count . " perícias do mês " . date('m/Y', strtotime($mes_alvo . "-01")) . " foram excluídas.";
                $message_type = 'success';
            } catch (PDOException $e) {
                $message = "Erro ao excluir pauta do mês: " . $e->getMessage();
                $message_type = 'error';
            }
        } else {
            $message = "Erro: Especifique a data ou o mês alvo para exclusão.";
            $message_type = 'error';
        }
    }
    
    // 3. Mover Data em Lote
    elseif ($action === 'mover_pauta') {
        $tipo = $_POST['mover_tipo'] ?? ''; // 'dia', 'mes' ou 'selecionados'
        $nova_data = $_POST['nova_data'] ?? '';
        
        if (!empty($nova_data)) {
            if ($tipo === 'selecionados' && isset($_POST['selecionados']) && is_array($_POST['selecionados'])) {
                $ids = array_map('intval', $_POST['selecionados']);
                if (!empty($ids)) {
                    $in_query = implode(',', $ids);
                    try {
                        $pst = $pdo->query("SELECT id, nome FROM atendimentos WHERE id IN ($in_query)");
                        while ($row = $pst->fetch()) {
                            registrarLog($pdo, 'Mover Pauta', "Mover pauta do paciente: {$row['nome']} para $nova_data", $row['id']);
                        }
                        $pdo->exec("UPDATE atendimentos SET data_pauta = '$nova_data' WHERE id IN ($in_query)");
                        $message = "Sucesso! " . count($ids) . " atendimentos foram movidos para " . date('d/m/Y', strtotime($nova_data)) . ".";
                        $message_type = 'success';
                    } catch (PDOException $e) {
                        $message = "Erro ao mover atendimentos: " . $e->getMessage();
                        $message_type = 'error';
                    }
                } else {
                    $message = "Erro: Selecione ao menos um atendimento para mover.";
                    $message_type = 'error';
                }
            } elseif ($tipo === 'dia') {
                $data_origem = $_POST['mover_data_origem'] ?? '';
                if (!empty($data_origem)) {
                    try {
                        $stmt = $pdo->prepare("UPDATE atendimentos SET data_pauta = ? WHERE data_pauta = ?");
                        $stmt->execute([$nova_data, $data_origem]);
                        $count = $stmt->rowCount();
                        registrarLog($pdo, 'Mover Pauta Dia', "Data de origem $data_origem movida para $nova_data. Registros afetados: $count");
                        $message = "Sucesso! $count atendimentos foram transferidos do dia " . date('d/m/Y', strtotime($data_origem)) . " para " . date('d/m/Y', strtotime($nova_data)) . ".";
                        $message_type = 'success';
                    } catch (PDOException $e) {
                        $message = "Erro ao transferir pauta do dia: " . $e->getMessage();
                        $message_type = 'error';
                    }
                } else {
                    $message = "Erro: Especifique a data de origem.";
                    $message_type = 'error';
                }
            } elseif ($tipo === 'mes') {
                $mes_origem = $_POST['mover_mes_origem'] ?? '';
                if (!empty($mes_origem)) {
                    try {
                        $stmt = $pdo->prepare("UPDATE atendimentos SET data_pauta = CONCAT(?, '-', DATE_FORMAT(data_pauta, '%d')) WHERE DATE_FORMAT(data_pauta, '%Y-%m') = ?");
                        $novo_ano_mes = date('Y-m', strtotime($nova_data));
                        $stmt->execute([$novo_ano_mes, $mes_origem]);
                        $count = $stmt->rowCount();
                        registrarLog($pdo, 'Mover Pauta Mes', "Mês de origem $mes_origem movido para $nova_data. Registros afetados: $count");
                        $message = "Sucesso! $count atendimentos foram transferidos do mês " . date('m/Y', strtotime($mes_origem . "-01")) . " para " . date('m/Y', strtotime($nova_data)) . ".";
                        $message_type = 'success';
                    } catch (PDOException $e) {
                        $message = "Erro ao transferir pauta do mês: " . $e->getMessage();
                        $message_type = 'error';
                    }
                } else {
                    $message = "Erro: Especifique o mês de origem.";
                    $message_type = 'error';
                }
            }
        } else {
            $message = "Erro: Especifique a nova data de destino.";
            $message_type = 'error';
        }
    }
    
    // 4. Alterar Perito em Lote
    elseif ($action === 'alterar_perito_lote') {
        $novo_perito = trim($_POST['novo_perito'] ?? '');
        $tipo = $_POST['perito_tipo'] ?? ''; // 'selecionados' ou 'dia'
        
        if (!empty($novo_perito)) {
            if ($tipo === 'selecionados' && isset($_POST['selecionados']) && is_array($_POST['selecionados'])) {
                $ids = array_map('intval', $_POST['selecionados']);
                if (!empty($ids)) {
                    $in_query = implode(',', $ids);
                    try {
                        $pst = $pdo->query("SELECT id, nome FROM atendimentos WHERE id IN ($in_query)");
                        while ($row = $pst->fetch()) {
                            registrarLog($pdo, 'Alterar Perito Lote', "Perito alterado para '$novo_perito' no paciente: {$row['nome']}", $row['id']);
                        }
                        $stmt = $pdo->prepare("UPDATE atendimentos SET perito = ? WHERE id IN ($in_query)");
                        $stmt->execute([$novo_perito]);
                        $message = "Sucesso! O perito de " . count($ids) . " atendimentos foi alterado para '$novo_perito'.";
                        $message_type = 'success';
                    } catch (PDOException $e) {
                        $message = "Erro ao alterar perito: " . $e->getMessage();
                        $message_type = 'error';
                    }
                } else {
                    $message = "Erro: Selecione ao menos um atendimento para alterar o perito.";
                    $message_type = 'error';
                }
            } elseif ($tipo === 'dia') {
                $data_origem = $_POST['perito_data_origem'] ?? '';
                if (!empty($data_origem)) {
                    try {
                        $stmt = $pdo->prepare("UPDATE atendimentos SET perito = ? WHERE data_pauta = ?");
                        $stmt->execute([$novo_perito, $data_origem]);
                        $count = $stmt->rowCount();
                        registrarLog($pdo, 'Alterar Perito Lote Dia', "Perito alterado para '$novo_perito' no dia $data_origem. Registros afetados: $count");
                        $message = "Sucesso! O perito de $count atendimentos do dia " . date('d/m/Y', strtotime($data_origem)) . " foi alterado para '$novo_perito'.";
                        $message_type = 'success';
                    } catch (PDOException $e) {
                        $message = "Erro ao alterar perito da pauta do dia: " . $e->getMessage();
                        $message_type = 'error';
                    }
                } else {
                    $message = "Erro: Especifique a data alvo.";
                    $message_type = 'error';
                }
            }
        } else {
            $message = "Erro: Informe o nome do novo perito.";
            $message_type = 'error';
        }
    }

    // 5. Confirmar Chegada (Individual ou Lote)
    elseif ($action === 'confirmar_chegada_lote') {
        $ids = [];
        if (isset($_POST['id'])) {
            $ids[] = (int)$_POST['id'];
        } elseif (isset($_POST['selecionados']) && is_array($_POST['selecionados'])) {
            $ids = array_map('intval', $_POST['selecionados']);
        }
        
        if (!empty($ids)) {
            $pdo->beginTransaction();
            $success_count = 0;
            try {
                $stmtCheck = $pdo->prepare("SELECT status, nome FROM atendimentos WHERE id = ?");
                $stmtUpdate = $pdo->prepare("UPDATE atendimentos SET status = 'presente', senha = ?, chegada_em = CURRENT_TIMESTAMP WHERE id = ?");
                
                foreach ($ids as $id) {
                    $stmtCheck->execute([$id]);
                    $row = $stmtCheck->fetch();
                    
                    if ($row && $row['status'] === 'agendado') {
                        $senha = getNextSenha($pdo);
                        $stmtUpdate->execute([$senha, $id]);
                        registrarLog($pdo, 'Confirmar Presença', "Paciente: {$row['nome']}, Senha: $senha (Lote Admin)", $id);
                        $success_count++;
                    }
                }
                $pdo->commit();
                $message = "Sucesso! Presença confirmada para $success_count registros.";
                $message_type = 'success';
            } catch (Exception $e) {
                $pdo->rollBack();
                $message = "Erro ao confirmar presença: " . $e->getMessage();
                $message_type = 'error';
            }
        } else {
            $message = "Erro: Nenhum agendamento selecionado.";
            $message_type = 'error';
        }
    }

    // 6. Voltar para Agendado (Individual ou Lote)
    elseif ($action === 'remover_chegada_lote') {
        $ids = [];
        if (isset($_POST['id'])) {
            $ids[] = (int)$_POST['id'];
        } elseif (isset($_POST['selecionados']) && is_array($_POST['selecionados'])) {
            $ids = array_map('intval', $_POST['selecionados']);
        }
        
        if (!empty($ids)) {
            $in_query = implode(',', $ids);
            try {
                $pst = $pdo->query("SELECT id, nome FROM atendimentos WHERE id IN ($in_query) AND status != 'agendado'");
                while ($row = $pst->fetch()) {
                    registrarLog($pdo, 'Retornar Agendamento', "Paciente: {$row['nome']} (Desfazer presença em Lote)", $row['id']);
                }
                $pdo->exec("UPDATE atendimentos SET status = 'agendado', senha = NULL, chegada_em = NULL, chamado_em = NULL, sala = NULL WHERE id IN ($in_query) AND status != 'agendado'");
                $message = "Sucesso! Registros voltaram para a lista de Agendados.";
                $message_type = 'success';
            } catch (PDOException $e) {
                $message = "Erro ao desfazer chegada: " . $e->getMessage();
                $message_type = 'error';
            }
        } else {
            $message = "Erro: Nenhum registro selecionado.";
            $message_type = 'error';
        }
    }
    
    // 7. Liberar Pauta para Recepção
    elseif ($action === 'liberar_pauta') {
        $data_alvo = $_POST['data_alvo'] ?? '';
        if (!empty($data_alvo)) {
            if (liberarPauta($pdo, $data_alvo, $_SESSION['usuario_id'])) {
                registrarLog($pdo, 'Liberar Pauta', "Pauta da data $data_alvo homologada e liberada para a recepção.");
                $message = "Sucesso! A pauta de " . date('d/m/Y', strtotime($data_alvo)) . " foi homologada e liberada para check-in na recepção.";
                $message_type = 'success';
            } else {
                $message = "Erro ao liberar pauta.";
                $message_type = 'error';
            }
        } else {
            $message = "Erro: Data alvo inválida.";
            $message_type = 'error';
        }
    }
    
    // 8. Bloquear Pauta (Revogar)
    elseif ($action === 'bloquear_pauta') {
        $data_alvo = $_POST['data_alvo'] ?? '';
        if (!empty($data_alvo)) {
            if (bloquearPauta($pdo, $data_alvo)) {
                registrarLog($pdo, 'Bloquear Pauta', "Pauta da data $data_alvo bloqueada/revogada para a recepção.");
                $message = "Sucesso! A liberação da pauta de " . date('d/m/Y', strtotime($data_alvo)) . " foi revogada.";
                $message_type = 'success';
            } else {
                $message = "Erro ao revogar liberação.";
                $message_type = 'error';
            }
        } else {
            $message = "Erro: Data alvo inválida.";
            $message_type = 'error';
        }
    }

    // 9. Importar Planilha Excel (.xlsx)
    elseif ($action === 'importar_excel') {
        if (isset($_FILES['arquivo_excel']) && $_FILES['arquivo_excel']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['arquivo_excel']['tmp_name'];
            $filename = htmlspecialchars($_FILES['arquivo_excel']['name'], ENT_QUOTES, 'UTF-8');
            $file_size = $_FILES['arquivo_excel']['size'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if ($ext !== 'xlsx') {
                $message = "Erro: Selecione um arquivo Excel (.xlsx) válido.";
                $message_type = "error";
            } elseif ($file_size > 5 * 1024 * 1024) { // 5MB
                $message = "Erro: O arquivo enviado excede o limite de tamanho permitido de 5MB.";
                $message_type = "error";
            } else {
                // Detecção inteligente do executável Python (compatível com Windows local e Linux/Docker de produção)
                $python_cmd = 'python3';
                if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                    $python_cmd = 'python';
                } else {
                    $check_py3 = shell_exec('which python3 2>/dev/null');
                    if (empty(trim($check_py3))) {
                        $python_cmd = 'python';
                    }
                }
                
                $cmd = $python_cmd . " " . escapeshellarg(__DIR__ . '/import_excel.py') . " " . escapeshellarg($file_tmp) . " 2>&1";
                
                $output = [];
                $return_var = 0;
                exec($cmd, $output, $return_var);
                
                if ($return_var === 0) {
                    $json_str = implode("\n", $output);
                    $records = json_decode($json_str, true);
                    
                    if (is_array($records) && !empty($records)) {
                        if (isset($records[0]['error'])) {
                            $message = "Erro no processamento: " . htmlspecialchars($records[0]['error']);
                            $message_type = "error";
                        } else {
                            $today = date('Y-m-d');
                            
                            $existingPeritos = [];
                            try {
                                $pst = $pdo->query("SELECT DISTINCT perito FROM atendimentos WHERE perito IS NOT NULL AND perito != ''");
                                while ($row = $pst->fetch()) {
                                    $p = $row['perito'];
                                    $existingPeritos[simplifyName($p)] = $p;
                                }
                            } catch (PDOException $e) {}

                            $existingAppointments = [];
                            try {
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
                            } catch (PDOException $e) {}

                            $existingUsers = [];
                            try {
                                $uStmt = $pdo->query("SELECT cpf, username FROM usuarios");
                                while ($row = $uStmt->fetch()) {
                                    if ($row['cpf']) $existingUsers[$row['cpf']] = true;
                                    if ($row['username']) $existingUsers[$row['username']] = true;
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
                                $dataPauta = $today;
                                if (isset($r['data_pericia']) && $r['data_pericia']) {
                                    $dParts = explode(" ", $r['data_pericia']);
                                    $dataPauta = $dParts[0];
                                }

                                // REGRA: Importar apenas do dia atual para frente
                                if ($dataPauta < $today) {
                                    continue;
                                }

                                $pName = corrigirNomeInteligente($r['perito']);
                                $pName = associarPeritoCadastrado($pName);
                                $pClean = simplifyName($pName);
                                if (isset($existingPeritos[$pClean])) {
                                    $pName = $existingPeritos[$pClean];
                                } elseif ($pClean) {
                                    $existingPeritos[$pClean] = $pName;
                                }

                                // Auto-cadastro do perito médico
                                $pCpf = isset($r['cpf_perito']) ? trim($r['cpf_perito']) : '';
                                if ($pName && $pCpf && strlen(preg_replace('/\D/', '', $pCpf)) === 11) {
                                    $uName = preg_replace('/\D/', '', $pCpf);
                                    if (!isset($existingUsers[$pCpf]) && !isset($existingUsers[$uName])) {
                                        $tempPwd = password_hash(substr($uName, 0, 6), PASSWORD_DEFAULT);
                                        try {
                                            $stmtUser->execute([$uName, $tempPwd, $pName, $pCpf]);
                                            $existingUsers[$pCpf] = true;
                                            $existingUsers[$uName] = true;
                                        } catch (PDOException $e) {}
                                    }
                                }

                                $periciadoNome = isset($r['periciado']) ? corrigirNomeInteligente($r['periciado']) : '';
                                if (!$periciadoNome) {
                                    $periciadoNome = (isset($r['cpf']) && $r['cpf']) ? "(Sem Nome) - CPF " . $r['cpf'] : "(Sem Nome)";
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
                                
                                if ($procClean) {
                                    $existingAppointments[$appKey] = [
                                        'id' => null,
                                        'nome' => $periciadoNome,
                                        'perito' => $pName,
                                        'cpf' => $cpf
                                    ];
                                }
                            }
                            unset($GLOBALS['sga_import_lote']);
                            $pdo->commit();
                            registrarLog($pdo, 'Importação de Pauta', "Arquivo: $filename, Registros importados: $count");
                            
                            unset($records);
                            unset($existingAppointments);
                            unset($existingPeritos);
                            unset($existingUsers);
                            gc_collect_cycles();
                            
                            $message = "Sucesso! $count novos registros importados da pauta.";
                            $message_type = "success";
                        }
                    } else {
                        $message = "Nenhum registro extraído.";
                        $message_type = "error";
                    }
                } else {
                    $err_msg = !empty($output) ? implode("<br>", array_map('htmlspecialchars', $output)) : "Erro no script Python.";
                    $message = "Erro de processamento:<br>" . $err_msg;
                    $message_type = "error";
                }
            }
        } else {
            $message = "Selecione um arquivo de pauta Excel válido.";
            $message_type = "error";
        }
    }
}

// Filtros de Visualização
$filtro_tipo = $_GET['filtro_tipo'] ?? 'dia'; // 'dia' ou 'mes'
$data_busca = $_GET['data_busca'] ?? date('Y-m-d');
$mes_busca = $_GET['mes_busca'] ?? date('Y-m');

// Consulta de Atendimentos
$atendimentos = [];
try {
    if ($filtro_tipo === 'dia') {
        $stmt = $pdo->prepare("SELECT * FROM atendimentos WHERE data_pauta = ? ORDER BY perito ASC, nome ASC, id ASC");
        $stmt->execute([$data_busca]);
        $atendimentos = $stmt->fetchAll();
    } else {
        $stmt = $pdo->prepare("SELECT * FROM atendimentos WHERE DATE_FORMAT(data_pauta, '%Y-%m') = ? ORDER BY data_pauta ASC, perito ASC, nome ASC, id ASC");
        $stmt->execute([$mes_busca]);
        $atendimentos = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    $message = "Erro ao buscar pauta: " . $e->getMessage();
    $message_type = 'error';
}

// Estatísticas Rápidas
$total = count($atendimentos);
$agendados = 0;
$fila = 0;
$finalizados = 0;
$cancelados = 0;
foreach ($atendimentos as $item) {
    if ($item['status'] === 'agendado') $agendados++;
    elseif ($item['status'] === 'presente' || $item['status'] === 'fila' || $item['status'] === 'chamado' || $item['status'] === 'atendimento') $fila++;
    elseif ($item['status'] === 'finalizado') $finalizados++;
    elseif ($item['status'] === 'cancelado') $cancelados++;
}

// Lista de peritos para remanejamento facilitado
$peritos_cadastrados = [];
try {
    $stmt = $pdo->query("SELECT nome FROM usuarios WHERE role = 'perito' AND nome IS NOT NULL AND nome != '' ORDER BY nome ASC");
    $peritos_cadastrados = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Pautas - <?= htmlspecialchars($sys_name) ?></title>
    <link rel="icon" type="image/png" href="assets/catavento-jfal.png">
    <script src="tema.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-950 text-gray-100 min-h-screen flex flex-col">
    <!-- Header -->
    <header class="bg-gray-900 border-b border-gray-800 px-6 py-4 flex items-center justify-between shadow-md shrink-0">
        <div class="flex items-center space-x-3">
            <a href="dashboard.php" class="flex items-center space-x-3 group hover:opacity-95 transition">
                <img src="assets/catavento-jfal.png" alt="JFAL" class="h-8 w-auto object-contain transition-transform duration-300 group-hover:scale-105">
                <div class="hidden sm:block">
                    <h1 class="text-xs font-black tracking-wide text-blue-500 uppercase leading-none group-hover:text-blue-400 transition-colors">Justiça Federal em Alagoas</h1>
                    <p class="text-[8px] text-gray-400 font-semibold tracking-widest uppercase mt-0.5">Seção Judiciária de Alagoas</p>
                </div>
            </a>
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

    <main class="flex-1 max-w-7xl w-full mx-auto p-6 grid grid-cols-1 lg:grid-cols-4 gap-6">
        <!-- Lado Esquerdo: Formulários de Ações em Lote e Importação -->
        <div class="space-y-6 lg:col-span-1">
            <!-- Importar Planilha Card -->
            <div class="bg-gray-900 p-5 rounded-2xl border border-gray-800 shadow-xl">
                <h2 class="text-xs font-semibold uppercase text-blue-400 tracking-wider mb-3">Importar Planilha (Excel)</h2>
                <form method="POST" enctype="multipart/form-data" class="space-y-4">
                    <input type="hidden" name="action" value="importar_excel">
                    
                    <div>
                        <label class="block text-xs text-gray-400 mb-1">Selecione o arquivo (.xlsx)</label>
                        <input type="file" name="arquivo_excel" accept=".xlsx" required class="w-full bg-gray-800 border border-gray-700 rounded-lg px-2 py-1.5 text-xs text-white file:bg-blue-600 file:border-none file:text-white file:text-xs file:font-semibold file:px-2.5 file:py-1 file:rounded-md file:mr-2 file:hover:bg-blue-750 file:cursor-pointer focus:outline-none">
                    </div>

                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-750 text-white text-xs font-semibold py-2 rounded-lg transition">
                        📥 Iniciar Importação
                    </button>
                </form>
            </div>

            <!-- Estatísticas do Período -->
            <div class="bg-gray-900 p-5 rounded-2xl border border-gray-800 shadow-xl">
                <h2 class="text-xs font-semibold uppercase text-gray-400 tracking-wider mb-3">Estatísticas Filtradas</h2>
                <div class="grid grid-cols-2 gap-3 text-center">
                    <div class="bg-gray-950/50 p-3 rounded-xl border border-gray-800/60">
                        <div class="text-lg font-black text-white"><?= $total ?></div>
                        <div class="text-[9px] uppercase tracking-wider text-gray-400 mt-1">Total</div>
                    </div>
                    <div class="bg-gray-950/50 p-3 rounded-xl border border-gray-800/60">
                        <div class="text-lg font-black text-blue-400"><?= $agendados ?></div>
                        <div class="text-[9px] uppercase tracking-wider text-gray-400 mt-1">Agendados</div>
                    </div>
                    <div class="bg-gray-950/50 p-3 rounded-xl border border-gray-800/60">
                        <div class="text-lg font-black text-emerald-400"><?= $fila ?></div>
                        <div class="text-[9px] uppercase tracking-wider text-gray-400 mt-1">Presenças</div>
                    </div>
                    <div class="bg-gray-950/50 p-3 rounded-xl border border-gray-800/60">
                        <div class="text-lg font-black text-gray-400"><?= $finalizados + $cancelados ?></div>
                        <div class="text-[9px] uppercase tracking-wider text-gray-400 mt-1">Concluídos</div>
                    </div>
                </div>
            </div>

            <!-- Transferir Pauta (Mover Data) -->
            <div class="bg-gray-900 p-5 rounded-2xl border border-gray-800 shadow-xl">
                <h2 class="text-xs font-semibold uppercase text-gray-400 tracking-wider mb-3">Transferir / Mover Pauta</h2>
                <form method="POST" class="space-y-4" onsubmit="return confirm('Confirmar o remanejamento da pauta de dados conforme os parâmetros informados?');">
                    <input type="hidden" name="action" value="mover_pauta">
                    
                    <div>
                        <label class="block text-xs text-gray-400 mb-1">Mover quem?</label>
                        <select name="mover_tipo" id="mover-tipo" onchange="toggleMoverInputs()" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-xs text-white focus:outline-none">
                            <option value="selecionados">Selecionados na Tabela</option>
                            <option value="dia">Todo um Dia Específico</option>
                            <option value="mes">Todo um Mês Inteiro</option>
                        </select>
                    </div>

                    <div id="mover-origem-dia" class="hidden">
                        <label class="block text-xs text-gray-400 mb-1">Dia de Origem</label>
                        <input type="date" name="mover_data_origem" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-xs text-white focus:outline-none">
                    </div>

                    <div id="mover-origem-mes" class="hidden">
                        <label class="block text-xs text-gray-400 mb-1">Mês de Origem</label>
                        <input type="month" name="mover_mes_origem" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-xs text-white focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs text-gray-400 mb-1" id="mover-destino-label">Nova Data de Destino</label>
                        <input type="date" name="nova_data" required class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-xs text-white focus:outline-none">
                    </div>

                    <div id="mover-selecionados-container"></div>

                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-750 text-white text-xs font-semibold py-2 rounded-lg transition">
                        Transferir Agendamentos
                    </button>
                </form>
            </div>

            <!-- Alterar Perito em Lote -->
            <div class="bg-gray-900 p-5 rounded-2xl border border-gray-800 shadow-xl">
                <h2 class="text-xs font-semibold uppercase text-gray-400 tracking-wider mb-3">Substituir Perito</h2>
                <form method="POST" class="space-y-4" onsubmit="return confirm('Substituir o perito associado aos registros selecionados?');">
                    <input type="hidden" name="action" value="alterar_perito_lote">
                    
                    <div>
                        <label class="block text-xs text-gray-400 mb-1">Alterar de onde?</label>
                        <select name="perito_tipo" id="perito-tipo" onchange="togglePeritoInputs()" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-xs text-white focus:outline-none">
                            <option value="selecionados">Selecionados na Tabela</option>
                            <option value="dia">De um Dia Inteiro</option>
                        </select>
                    </div>

                    <div id="perito-origem-dia" class="hidden">
                        <label class="block text-xs text-gray-400 mb-1">Dia Alvo</label>
                        <input type="date" name="perito_data_origem" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-xs text-white focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs text-gray-400 mb-1">Nome do Novo Perito</label>
                        <input type="text" name="novo_perito" list="lista-peritos" required placeholder="Nome do Médico/Perito" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-xs text-white focus:outline-none">
                        <datalist id="lista-peritos">
                            <?php foreach ($peritos_cadastrados as $pName): ?>
                                <option value="<?= htmlspecialchars($pName) ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>

                    <div id="perito-selecionados-container"></div>

                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-750 text-white text-xs font-semibold py-2 rounded-lg transition">
                        Substituir Perito
                    </button>
                </form>
            </div>

            <!-- Excluir Pauta em Lote -->
            <div class="bg-gray-900 p-5 rounded-2xl border border-gray-800 shadow-xl border-red-950/30">
                <h2 class="text-xs font-semibold uppercase text-red-400 tracking-wider mb-3">Excluir Pauta</h2>
                <form method="POST" class="space-y-4" onsubmit="return confirm('ATENÇÃO: Isso removerá TODOS os agendamentos do dia ou mês de forma PERMANENTE. Continuar?');">
                    <input type="hidden" name="action" value="excluir_periodo">
                    
                    <div>
                        <label class="block text-xs text-gray-400 mb-1">Excluir o quê?</label>
                        <select name="periodo_tipo" id="excluir-tipo" onchange="toggleExcluirInputs()" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-xs text-white focus:outline-none">
                            <option value="dia">Todo um Dia Específico</option>
                            <option value="mes">Todo um Mês Inteiro</option>
                        </select>
                    </div>

                    <div id="excluir-origem-dia">
                        <label class="block text-xs text-gray-400 mb-1">Data Alvo</label>
                        <input type="date" name="data_alvo" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-xs text-white focus:outline-none">
                    </div>

                    <div id="excluir-origem-mes" class="hidden">
                        <label class="block text-xs text-gray-400 mb-1">Mês Alvo</label>
                        <input type="month" name="mes_alvo" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-xs text-white focus:outline-none">
                    </div>

                    <button type="submit" class="w-full bg-red-950/40 hover:bg-red-900/40 border border-red-900/60 text-red-400 text-xs font-semibold py-2 rounded-lg transition">
                        Excluir Pauta Permanentemente
                    </button>
                </form>
            </div>
        </div>

        <!-- Lado Direito: Filtro e Tabela de Listagem -->
        <div class="lg:col-span-3 space-y-6">
            <!-- Mensagens -->
            <?php if (!empty($message)): ?>
                <div class="p-4 rounded-xl text-sm border <?= $message_type === 'success' ? 'bg-emerald-950/40 border-emerald-900/60 text-emerald-400' : 'bg-red-950/40 border-red-900/60 text-red-400' ?>">
                    <?= $message ?>
                </div>
            <?php endif; ?>

            <!-- Painel de Tabela -->
            <div class="bg-gray-900 p-6 rounded-2xl border border-gray-800 shadow-xl flex flex-col h-full">
                <?php if ($filtro_tipo === 'dia'): ?>
                    <?php
                    $pauta_liberada = isPautaLiberada($pdo, $data_busca);
                    ?>
                    <div class="mb-6 p-4 rounded-xl border flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 <?= $pauta_liberada ? 'bg-emerald-950/20 border-emerald-900/50 text-emerald-300' : 'bg-amber-950/20 border-amber-900/50 text-amber-300' ?>">
                        <div class="flex items-center gap-3">
                            <span class="text-xl"><?= $pauta_liberada ? '🟢' : '⚠️' ?></span>
                            <div>
                                <h4 class="text-xs uppercase font-bold tracking-wider">Status da Pauta - <?= date('d/m/Y', strtotime($data_busca)) ?></h4>
                                <p class="text-[11px] <?= $pauta_liberada ? 'text-emerald-400' : 'text-amber-400' ?> mt-0.5 font-medium">
                                    <?= $pauta_liberada ? 'Homologada e liberada. A recepção comum pode confirmar a chegada dos pacientes.' : 'Pendente de homologação. O check-in da recepção está bloqueado para este dia.' ?>
                                </p>
                            </div>
                        </div>
                        <form method="POST" action="gerenciar_pauta.php?filtro_tipo=dia&data_busca=<?= $data_busca ?>" class="w-full sm:w-auto">
                            <input type="hidden" name="data_alvo" value="<?= htmlspecialchars($data_busca) ?>">
                            <?php if ($pauta_liberada): ?>
                                <input type="hidden" name="action" value="bloquear_pauta">
                                <button type="submit" class="w-full sm:w-auto bg-amber-950/60 hover:bg-amber-900 border border-amber-900/50 text-amber-300 text-xs font-bold py-2 px-4 rounded-lg transition">
                                    🔒 Bloquear Pauta
                                </button>
                            <?php else: ?>
                                <input type="hidden" name="action" value="liberar_pauta">
                                <button type="submit" class="w-full sm:w-auto bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold py-2.5 px-4 rounded-lg shadow-lg transition">
                                    🔓 Homologar e Liberar Pauta
                                </button>
                            <?php endif; ?>
                        </form>
                    </div>
                <?php endif; ?>

                <!-- Filtros de Busca superiores -->
                <form method="GET" action="gerenciar_pauta.php" class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                    <div class="flex items-center gap-2">
                        <label class="text-xs font-semibold uppercase text-gray-400 tracking-wider">Filtrar por:</label>
                        <select name="filtro_tipo" id="filtro-tipo-busca" onchange="toggleFiltroBuscaInputs(); this.form.submit()" class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-1.5 text-xs text-white focus:outline-none">
                            <option value="dia" <?= $filtro_tipo === 'dia' ? 'selected' : '' ?>>Dia</option>
                            <option value="mes" <?= $filtro_tipo === 'mes' ? 'selected' : '' ?>>Mês</option>
                        </select>
                    </div>

                    <div class="flex items-center gap-2 w-full sm:w-auto">
                        <div id="busca-dia-container" class="<?= $filtro_tipo === 'dia' ? 'flex items-center gap-2' : 'hidden' ?> w-full sm:w-auto">
                            <input type="date" name="data_busca" id="data-busca-input" value="<?= htmlspecialchars($data_busca) ?>" onchange="this.form.submit()" class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-1.5 text-xs text-white focus:outline-none w-full">
                            <button type="button" onclick="irParaHoje()" class="bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold px-3 py-1.5 rounded-lg transition shrink-0">
                                Hoje
                            </button>
                        </div>
                        <div id="busca-mes-container" class="<?= $filtro_tipo === 'mes' ? '' : 'hidden' ?> w-full sm:w-auto">
                            <input type="month" name="mes_busca" value="<?= htmlspecialchars($mes_busca) ?>" onchange="this.form.submit()" class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-1.5 text-xs text-white focus:outline-none w-full">
                        </div>
                    </div>
                </form>

                <form method="POST" id="form-tabela-pauta" class="flex-1 flex flex-col justify-between">
                    <!-- Tabela de agendados -->
                    <div class="overflow-x-auto max-h-[720px] overflow-y-auto pr-1">
                        <table class="w-full text-left text-sm text-gray-300">
                            <thead>
                                <tr class="border-b border-gray-800 text-xs text-gray-400 uppercase">
                                    <th class="pb-3 w-8">
                                        <input type="checkbox" id="chk-select-all" class="rounded bg-gray-800 border-gray-700 text-blue-500 focus:ring-0 focus:ring-offset-0">
                                    </th>
                                    <th class="pb-3">Data</th>
                                    <th class="pb-3">Nome / Processo / CPF</th>
                                    <th class="pb-3">Perito</th>
                                    <th class="pb-3 text-center">Status / Senha</th>
                                    <th class="pb-3 text-center">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-800/50">
                                <?php if (empty($atendimentos)): ?>
                                    <tr>
                                        <td colspan="6" class="py-8 text-center text-xs text-gray-500">
                                            Nenhum agendamento encontrado para o período selecionado.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($atendimentos as $item): ?>
                                        <tr onclick="if (event.target.tagName !== 'INPUT' && event.target.tagName !== 'BUTTON' && !event.target.closest('button') && !event.target.closest('a')) mostrarModalDetalhes({ nome: '<?= htmlspecialchars($item['nome'], ENT_QUOTES) ?>', cpf: '<?= htmlspecialchars($item['cpf'] ?? '---', ENT_QUOTES) ?>', processo: '<?= htmlspecialchars($item['processo'], ENT_QUOTES) ?>', perito: '<?= htmlspecialchars($item['perito'], ENT_QUOTES) ?>', data_pauta: '<?= date('d/m/Y', strtotime($item['data_pauta'])) ?>', status: '<?= $item['status'] ?>', chegada_em: '<?= $item['chegada_em'] ? date('H:i:s', strtotime($item['chegada_em'])) : '---' ?>' })" class="hover:bg-gray-900/30 cursor-pointer transition">
                                            <td class="py-3">
                                                <input type="checkbox" name="selecionados[]" value="<?= $item['id'] ?>" class="chk-item rounded bg-gray-800 border-gray-700 text-blue-500 focus:ring-0 focus:ring-offset-0">
                                            </td>
                                            <td class="py-3 text-xs font-mono text-gray-400">
                                                <?= date('d/m/Y', strtotime($item['data_pauta'])) ?>
                                            </td>
                                            <td class="py-3">
                                                <div class="font-semibold text-white truncate max-w-[220px]"><?= htmlspecialchars($item['nome']) ?></div>
                                                <div class="text-[10px] text-gray-500 font-mono">
                                                    <?= htmlspecialchars($item['processo'] ?: 'Sem processo') ?>
                                                    <?php if (!empty($item['cpf'])): ?>
                                                        <span class="text-blue-400 ml-1.5">| CPF: <?= htmlspecialchars($item['cpf']) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="py-3 text-xs text-gray-300 truncate max-w-[150px]">
                                                <?= htmlspecialchars($item['perito'] ?: 'Não associado') ?>
                                            </td>
                                            <td class="py-3 text-center text-xs font-semibold">
                                                <?php
                                                $status = $item['status'];
                                                $senha_badge = !empty($item['senha']) && $item['senha'] !== '---' ? " <strong class='text-white ml-1'>({$item['senha']})</strong>" : "";
                                                if ($status === 'agendado') {
                                                    echo '<span class="text-blue-400 bg-blue-950/30 border border-blue-900/60 px-2 py-0.5 rounded-full text-[10px]">Agendado</span>';
                                                } elseif ($status === 'presente' || $status === 'fila') {
                                                    echo '<span class="text-emerald-400 bg-emerald-950/40 border border-emerald-900/60 px-2 py-0.5 rounded-full text-[10px]">Na Fila' . $senha_badge . '</span>';
                                                } elseif ($status === 'chamado') {
                                                    echo '<span class="text-amber-400 bg-amber-950/40 border border-amber-900/60 px-2 py-0.5 rounded-full text-[10px]">Chamado' . $senha_badge . '</span>';
                                                } elseif ($status === 'atendimento') {
                                                    echo '<span class="text-purple-400 bg-purple-950/40 border border-purple-900/60 px-2 py-0.5 rounded-full text-[10px]">Consulta' . $senha_badge . '</span>';
                                                } elseif ($status === 'finalizado') {
                                                    echo '<span class="text-gray-400 bg-gray-800 border border-gray-700 px-2 py-0.5 rounded-full text-[10px]">Finalizado</span>';
                                                } else {
                                                    echo '<span class="text-red-400 bg-red-950/40 border border-red-900/60 px-2 py-0.5 rounded-full text-[10px]">Cancelado</span>';
                                                }
                                                ?>
                                            </td>
                                            <td class="py-3 text-center">
                                                <div class="flex items-center justify-center gap-1.5">
                                                    <?php if ($status === 'agendado'): ?>
                                                        <button type="button" onclick="confirmarChegadaIndividual(<?= $item['id'] ?>)" title="Confirmar Presença" class="bg-emerald-950/40 hover:bg-emerald-900/40 border border-emerald-900/60 text-emerald-400 text-[10px] px-2 py-1 rounded font-bold transition">
                                                            ✔️ Confirmar
                                                        </button>
                                                    <?php else: ?>
                                                        <button type="button" onclick="removerChegadaIndividual(<?= $item['id'] ?>)" title="Retornar para Agendados" class="bg-amber-950/40 hover:bg-amber-900/40 border border-amber-900/60 text-amber-400 text-[10px] px-2 py-1 rounded font-bold transition">
                                                            ↩️ Desfazer
                                                        </button>
                                                    <?php endif; ?>
                                                    
                                                    <button type="button" onclick="excluirIndividual(<?= $item['id'] ?>)" title="Excluir Registro" class="bg-red-950/40 hover:bg-red-900/40 border border-red-900/60 text-red-400 text-[10px] px-2 py-1 rounded transition">
                                                        🗑️
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Botões de Ações Rápidas em Lote -->
                    <div class="mt-6 pt-4 border-t border-gray-800 flex flex-wrap gap-3 items-center justify-between">
                        <span class="text-xs text-gray-500 font-medium" id="lbl-selecionados">Nenhum registro selecionado.</span>
                        <div class="flex flex-wrap gap-2">
                            <button type="submit" name="action" value="confirmar_chegada_lote" id="btn-chegada-lote" disabled class="bg-emerald-950/40 hover:bg-emerald-900/40 border border-emerald-900/60 text-emerald-400 text-xs px-3 py-1.5 rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed font-medium">
                                ✔️ Confirmar Chegada
                            </button>
                            <button type="submit" name="action" value="remover_chegada_lote" id="btn-cancelar-lote" disabled class="bg-amber-950/40 hover:bg-amber-900/40 border border-amber-900/60 text-amber-400 text-xs px-3 py-1.5 rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed font-medium">
                                ↩️ Voltar para Agendado
                            </button>
                            <button type="submit" name="action" value="excluir_selecionados" id="btn-excluir-lote" disabled onclick="return confirm('Deseja excluir permanentemente todos os agendamentos selecionados na tabela?');" class="bg-red-950/40 hover:bg-red-900/40 border border-red-900/60 text-red-400 text-xs px-3 py-1.5 rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed font-medium">
                                🗑️ Excluir
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <!-- Formulários Ocultos Auxiliares para Ações Individuais (evita conflito de checkboxes) -->
    <form id="form-action-individual" method="POST" class="hidden">
        <input type="hidden" name="action" id="ind-action">
        <input type="hidden" name="id" id="ind-id">
        <input type="hidden" name="selecionados[]" id="ind-selecionados">
    </form>

    <!-- Footer -->
    <footer class="bg-gray-900 border-t border-gray-800 py-4 text-center text-[10px] text-gray-500 font-medium tracking-wide mt-6">
        <span>&copy; <?= date('Y') ?> - Desenvolvido pela DTI - Justiça Federal em Alagoas</span>
    </footer>

    <script>
        // Vai direto para o dia de hoje
        function irParaHoje() {
            const input = document.getElementById('data-busca-input');
            if (input) {
                input.value = '<?= date('Y-m-d') ?>';
                input.form.submit();
            }
        }

        // Funções para Ações Individuais rápidas
        function confirmarChegadaIndividual(id) {
            document.getElementById('ind-action').value = 'confirmar_chegada_lote';
            document.getElementById('ind-id').value = id;
            document.getElementById('form-action-individual').submit();
        }

        function removerChegadaIndividual(id) {
            document.getElementById('ind-action').value = 'remover_chegada_lote';
            document.getElementById('ind-id').value = id;
            document.getElementById('form-action-individual').submit();
        }

        function excluirIndividual(id) {
            if (confirm('Excluir este agendamento permanentemente?')) {
                document.getElementById('ind-action').value = 'excluir_selecionados';
                document.getElementById('ind-selecionados').value = id;
                document.getElementById('form-action-individual').submit();
            }
        }

        // Lógica de formulários adicionais
        function toggleMoverInputs() {
            const val = document.getElementById('mover-tipo').value;
            const origemDia = document.getElementById('mover-origem-dia');
            const origemMes = document.getElementById('mover-origem-mes');
            const labelDestino = document.getElementById('mover-destino-label');
            const inputDestino = labelDestino.nextElementSibling;
            
            origemDia.classList.add('hidden');
            origemMes.classList.add('hidden');
            
            if (val === 'dia') {
                origemDia.classList.remove('hidden');
                labelDestino.innerText = "Nova Data de Destino";
                inputDestino.type = 'date';
            } else if (val === 'mes') {
                origemMes.classList.remove('hidden');
                labelDestino.innerText = "Novo Mês de Destino";
                inputDestino.type = 'month';
            } else {
                labelDestino.innerText = "Nova Data de Destino";
                inputDestino.type = 'date';
            }
        }

        function togglePeritoInputs() {
            const val = document.getElementById('perito-tipo').value;
            const origemDia = document.getElementById('perito-origem-dia');
            origemDia.classList.add('hidden');
            if (val === 'dia') {
                origemDia.classList.remove('hidden');
            }
        }

        function toggleExcluirInputs() {
            const val = document.getElementById('excluir-tipo').value;
            const dia = document.getElementById('excluir-origem-dia');
            const mes = document.getElementById('excluir-origem-mes');
            
            dia.classList.add('hidden');
            mes.classList.add('hidden');
            
            if (val === 'dia') {
                dia.classList.remove('hidden');
            } else {
                mes.classList.remove('hidden');
            }
        }

        function toggleFiltroBuscaInputs() {
            const val = document.getElementById('filtro-tipo-busca').value;
            const diaContainer = document.getElementById('busca-dia-container');
            const mesContainer = document.getElementById('busca-mes-container');
            
            diaContainer.classList.add('hidden');
            mesContainer.classList.add('hidden');
            
            if (val === 'dia') {
                diaContainer.classList.remove('hidden');
            } else {
                mesContainer.classList.remove('hidden');
            }
        }

        // Checkbox "Selecionar Todos"
        const chkAll = document.getElementById('chk-select-all');
        const chks = document.querySelectorAll('.chk-item');
        
        const btnChegada = document.getElementById('btn-chegada-lote');
        const btnCancelar = document.getElementById('btn-cancelar-lote');
        const btnExcluir = document.getElementById('btn-excluir-lote');
        const lblSelecionados = document.getElementById('lbl-selecionados');
        
        const moverContainer = document.getElementById('mover-selecionados-container');
        const peritoContainer = document.getElementById('perito-selecionados-container');

        if (chkAll) {
            chkAll.addEventListener('change', function() {
                chks.forEach(chk => chk.checked = chkAll.checked);
                atualizarBotoesLote();
            });
        }

        chks.forEach(chk => {
            chk.addEventListener('change', atualizarBotoesLote);
        });

        function atualizarBotoesLote() {
            const selecionados = Array.from(chks).filter(chk => chk.checked).map(chk => chk.value);
            const count = selecionados.length;
            
            if (count > 0) {
                btnChegada.disabled = false;
                btnCancelar.disabled = false;
                btnExcluir.disabled = false;
                lblSelecionados.innerText = `${count} registro(s) selecionado(s).`;
            } else {
                btnChegada.disabled = true;
                btnCancelar.disabled = true;
                btnExcluir.disabled = true;
                lblSelecionados.innerText = 'Nenhum registro selecionado.';
            }

            // Injeta os inputs ocultos nos outros formulários
            moverContainer.innerHTML = '';
            peritoContainer.innerHTML = '';
            
            selecionados.forEach(id => {
                const inp1 = document.createElement('input');
                inp1.type = 'hidden';
                inp1.name = 'selecionados[]';
                inp1.value = id;
                moverContainer.appendChild(inp1);

                const inp2 = document.createElement('input');
                inp2.type = 'hidden';
                inp2.name = 'selecionados[]';
                inp2.value = id;
                peritoContainer.appendChild(inp2);
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
