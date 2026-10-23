<?php
declare(strict_types=1);

function prepararImportacao(array $arquivo): array {
    if (($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($arquivo['tmp_name'] ?? '')) throw new DomainException('Envie uma planilha válida.');
    if (strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION)) !== 'xlsx' || $arquivo['size'] > 5 * 1024 * 1024) throw new DomainException('Use .xlsx de até 5 MB.');
    $python = getenv('PYTHON_BIN') ?: (PHP_OS_FAMILY === 'Windows' ? 'python' : '/opt/venv/bin/python');
    $comando = escapeshellarg($python) . ' ' . escapeshellarg(getenv('IMPORT_EXCEL_SCRIPT') ?: __DIR__ . '/import_excel.py') . ' ' . escapeshellarg($arquivo['tmp_name']);
    // Limites de CPU/memória do subprocesso são complementados pelos limites do container.
    $saida = []; $codigo = 0; exec($comando, $saida, $codigo);
    $registros = json_decode(implode("\n", $saida), true, 512, JSON_THROW_ON_ERROR);
    if (isset($registros[0]['error'])) throw new DomainException($registros[0]['error']);
    if ($codigo !== 0 || !$registros || count($registros) > 10000) throw new DomainException('Não foi possível interpretar a planilha.');
    $registros = array_values(array_filter($registros, static fn($r) => $r['data_pericia'] >= date('Y-m-d')));
    if (!$registros) throw new DomainException('A planilha não contém agendamentos de hoje em diante.');
    return $registros;
}

function importarRegistros(PDO $pdo, array $registros): array {
    $lock = $pdo->query("SELECT GET_LOCK('sga_importacao', 15)")->fetchColumn();
    if (!$lock) throw new DomainException('Outra importação está em andamento. Tente novamente.');
    $pdo->beginTransaction();
    try {
        $novos = $atualizados = $preservados = $usuarios = 0;
        $buscar = $pdo->prepare("SELECT * FROM atendimentos WHERE LOWER(REPLACE(REPLACE(REPLACE(REPLACE(processo, '.', ''), '-', ''), '/', ''), ' ', '')) = ? AND data_pauta = ? AND perito = ? AND status <> 'cancelado' FOR UPDATE");
        $inserir = $pdo->prepare("INSERT INTO atendimentos (nome, processo, perito, status, data_pauta, cpf) VALUES (?, ?, ?, 'agendado', ?, ?)");
        $atualizar = $pdo->prepare("UPDATE atendimentos SET nome = ?, cpf = ? WHERE id = ? AND status = 'agendado'");
        foreach ($registros as $r) {
            if (!dataValida($r['data_pericia']) || $r['data_pericia'] < date('Y-m-d')) throw new DomainException('Prévia expirada ou data inválida. Reimporte o arquivo.');
            $nome = $r['periciado'] ?: '(Nome pendente — conferir documento)';
            $buscar->execute([simplifyName($r['processo']), $r['data_pericia'], $r['perito']]); $existentes = $buscar->fetchAll();
            if (count($existentes) > 1) throw new DomainException('Há agendamentos duplicados no banco para um registro. Revise a pauta antes de importar.');
            if ($existentes) {
                $item = $existentes[0];
                $atendimentoId = (int)$item['id'];
                if ($item['status'] !== 'agendado') { $preservados++; continue; }
                if ($item['nome'] !== $nome || ($item['cpf'] ?? '') !== $r['cpf']) {
                    $atualizar->execute([$nome, $r['cpf'] ?: null, $item['id']]);
                    registrarLog($pdo, 'Atualizar Importação', json_encode(['antes' => $item, 'depois' => $r], JSON_UNESCAPED_UNICODE), $item['id']); $atualizados++;
                }
            } else {
                $inserir->execute([$nome, $r['processo'], $r['perito'], $r['data_pericia'], $r['cpf'] ?: null]);
                $atendimentoId = (int)$pdo->lastInsertId();
                registrarLog($pdo, 'Importar Agendamento', json_encode($r, JSON_UNESCAPED_UNICODE), $atendimentoId); $novos++;
            }
            $vinculo = peritoId($pdo, $r['perito']);
            if ($r['cpf_perito']) {
                $cpf = preg_replace('/\D/', '', $r['cpf_perito']);
                $u = $pdo->prepare("SELECT id, nome, role FROM usuarios WHERE username = ? OR REPLACE(REPLACE(cpf, '.', ''), '-', '') = ? FOR UPDATE");
                $u->execute([$cpf, $cpf]); $existe = $u->fetchAll();
                if (count($existe) > 1 || ($existe && ($existe[0]['role'] !== 'perito' || simplifyNameWithSpaces($existe[0]['nome'] ?? '') !== simplifyNameWithSpaces($r['perito'])))) throw new DomainException('CPF de perito associado a cadastro divergente. Revise o cadastro.');
                if ($existe) $vinculo = (int)$existe[0]['id'];
                if (!$existe) {
                    // Sem senha conhecida até um administrador atribuir credencial individual.
                    $u = $pdo->prepare("INSERT INTO usuarios (username, password, role, nome, cpf, must_change_password) VALUES (?, ?, 'perito', ?, ?, 1)");
                    $u->execute([$cpf, password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT), $r['perito'], $r['cpf_perito']]);
                    $vinculo = (int)$pdo->lastInsertId();
                    registrarLog($pdo, 'Pré-cadastrar Perito', 'Conta requer ativação individual pelo administrador.'); $usuarios++;
                }
            }
            if ($vinculo !== null) {
                $v = $pdo->prepare("UPDATE atendimentos SET perito_id = ? WHERE id = ? AND status = 'agendado' AND (perito_id IS NULL OR perito_id = ?)");
                $v->execute([$vinculo, $atendimentoId, $vinculo]);
                if ($existentes && $item['perito_id'] !== null && (int)$item['perito_id'] !== $vinculo) throw new DomainException('Agendamento já vinculado a outro profissional. Revise a pauta.');
            }
        }
        $pdo->commit(); return compact('novos', 'atualizados', 'preservados', 'usuarios');
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    finally { $pdo->query("SELECT RELEASE_LOCK('sga_importacao')"); }
}
