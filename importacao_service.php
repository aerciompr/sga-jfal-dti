<?php
declare(strict_types=1);

function encontrarAtendimentoCorrespondente(array $existentes, array $r, string $nome): ?array {
    $cpfLimpo = preg_replace('/\D/', '', $r['cpf'] ?? '');
    $nomeSimplificado = simplifyName($nome);
    $correspondentes = [];

    if ($cpfLimpo !== '') {
        foreach ($existentes as $c) {
            $cCpf = preg_replace('/\D/', '', $c['cpf'] ?? '');
            if ($cCpf !== '' && $cCpf === $cpfLimpo) {
                $correspondentes[] = $c;
            }
        }
    }

    if (!$correspondentes) {
        foreach ($existentes as $c) {
            if (simplifyName($c['nome'] ?? '') === $nomeSimplificado) {
                $correspondentes[] = $c;
            }
        }
    }

    if (!$correspondentes && count($existentes) === 1 && empty($existentes[0]['cpf']) && ($existentes[0]['nome'] === '(Nome pendente — conferir documento)' || $nome === '(Nome pendente — conferir documento)')) {
        $correspondentes[] = $existentes[0];
    }

    return $correspondentes[0] ?? null;
}

function prepararImportacao(array $arquivo, string $janela = 'dia'): array {
    if (($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || (!is_uploaded_file($arquivo['tmp_name'] ?? '') && (getenv('SGA_TEST') !== '1' || !file_exists($arquivo['tmp_name'] ?? '')))) {
        throw new DomainException('Envie uma planilha válida.');
    }
    if (strtolower(pathinfo($arquivo['name'] ?? '', PATHINFO_EXTENSION)) !== 'xlsx' || ($arquivo['size'] ?? 0) > 5 * 1024 * 1024) {
        throw new DomainException('Use .xlsx de até 5 MB.');
    }
    $python = getenv('PYTHON_BIN') ?: (PHP_OS_FAMILY === 'Windows' ? 'python' : '/opt/venv/bin/python');
    $comando = escapeshellarg($python) . ' ' . escapeshellarg(getenv('IMPORT_EXCEL_SCRIPT') ?: __DIR__ . '/import_excel.py') . ' ' . escapeshellarg($arquivo['tmp_name']);
    // Limites de CPU/memória do subprocesso são complementados pelos limites do container.
    $saida = []; $codigo = 0; exec($comando, $saida, $codigo);
    $registros = json_decode(implode("\n", $saida), true, 512, JSON_THROW_ON_ERROR);
    if (isset($registros[0]['error'])) throw new DomainException($registros[0]['error']);
    if ($codigo !== 0 || !$registros || count($registros) > 10000) throw new DomainException('Não foi possível interpretar a planilha.');

    // Janela estrita: apenas 'dia' ou '3dias' (proibido importar 30 dias ou indefinido)
    $janela = ($janela === '3dias') ? '3dias' : 'dia';
    $hoje = date('Y-m-d');
    $diasLimite = ($janela === '3dias') ? 2 : 0;
    $dtLimite = (new DateTimeImmutable($hoje))->modify("+{$diasLimite} days");
    $dataLimite = $dtLimite->format('Y-m-d');

    $datasEscopo = [];
    $periodo = new DatePeriod(
        new DateTimeImmutable($hoje),
        new DateInterval('P1D'),
        $dtLimite->modify('+1 day')
    );
    foreach ($periodo as $d) {
        $datasEscopo[] = $d->format('Y-m-d');
    }

    $registrosFiltrados = array_values(array_filter($registros, static function ($r) use ($hoje, $dataLimite) {
        $dp = $r['data_pericia'] ?? '';
        return $dp >= $hoje && $dp <= $dataLimite;
    }));

    if (!$registrosFiltrados) {
        if ($janela === 'dia') {
            throw new DomainException('A planilha não contém agendamentos para a data de hoje (' . date('d/m/Y') . ').');
        }
        throw new DomainException('A planilha não contém agendamentos para a janela selecionada (' . date('d/m/Y') . ' a ' . $dtLimite->format('d/m/Y') . ').');
    }

    return [
        'registros' => $registrosFiltrados,
        'janela' => $janela,
        'datas_escopo' => $datasEscopo,
        'total_linhas' => count($registrosFiltrados),
    ];
}

function calcularResumoPrevia(PDO $pdo, array $registros, array $datasEscopo, bool $sincronizar = true): array {
    if (empty($datasEscopo)) {
        $datasEscopo = array_values(array_unique(array_filter(array_column($registros, 'data_pericia'))));
    }
    if (empty($datasEscopo)) {
        return [
            'novos' => 0,
            'mantidos' => 0,
            'preservados_andamento' => 0,
            'cancelar' => [],
            'total_cancelar' => 0,
            'datas_escopo' => [],
        ];
    }

    $inPlaceholders = implode(',', array_fill(0, count($datasEscopo), '?'));
    $stmt = $pdo->prepare("SELECT * FROM atendimentos WHERE data_pauta IN ($inPlaceholders) AND status <> 'cancelado'");
    $stmt->execute($datasEscopo);
    $existentesNoBanco = $stmt->fetchAll();

    $bancoIndexado = [];
    foreach ($existentesNoBanco as $ex) {
        $chave = simplifyName($ex['processo'] ?? '') . '|' . $ex['data_pauta'] . '|' . trim((string)$ex['perito']);
        $bancoIndexado[$chave][] = $ex;
    }

    $novos = 0;
    $mantidos = 0;
    $preservadosAndamento = 0;
    $processadosIds = [];

    foreach ($registros as $r) {
        $nome = $r['periciado'] ?: '(Nome pendente — conferir documento)';
        $chave = simplifyName($r['processo'] ?? '') . '|' . ($r['data_pericia'] ?? '') . '|' . trim((string)($r['perito'] ?? ''));
        $candidatos = $bancoIndexado[$chave] ?? [];
        $item = encontrarAtendimentoCorrespondente($candidatos, $r, $nome);

        if ($item) {
            $itemId = (int)$item['id'];
            $processadosIds[$itemId] = true;
            if ($item['status'] !== 'agendado') {
                $preservadosAndamento++;
            } else {
                $mantidos++;
            }
        } else {
            $novos++;
        }
    }

    $paraCancelar = [];
    if ($sincronizar) {
        foreach ($existentesNoBanco as $antigo) {
            $antigoId = (int)$antigo['id'];
            if (!isset($processadosIds[$antigoId])) {
                if ($antigo['status'] === 'agendado') {
                    $paraCancelar[] = $antigo;
                } else {
                    $preservadosAndamento++;
                }
            }
        }
    }

    return [
        'novos' => $novos,
        'mantidos' => $mantidos,
        'preservados_andamento' => $preservadosAndamento,
        'cancelar' => $paraCancelar,
        'total_cancelar' => count($paraCancelar),
        'datas_escopo' => $datasEscopo,
    ];
}

function importarRegistros(PDO $pdo, array $registros, array $datasEscopo = [], bool $sincronizar = true): array {
    @set_time_limit(300);
    $lock = $pdo->query("SELECT GET_LOCK('sga_importacao', 15)")->fetchColumn();
    if (!$lock) throw new DomainException('Outra importação está em andamento. Tente novamente.');
    $pdo->beginTransaction();
    try {
        $novos = $atualizados = $preservados = $usuarios = $cancelados = 0;
        $processadosIds = [];

        $buscar = $pdo->prepare("SELECT * FROM atendimentos WHERE LOWER(REPLACE(REPLACE(REPLACE(REPLACE(processo, '.', ''), '-', ''), '/', ''), ' ', '')) = ? AND data_pauta = ? AND perito = ? AND status <> 'cancelado' FOR UPDATE");
        $temColunaHora = colunaExiste($pdo, 'atendimentos', 'hora_pericia');
        if ($temColunaHora) {
            $inserir = $pdo->prepare("INSERT INTO atendimentos (nome, processo, perito, status, data_pauta, cpf, hora_pericia) VALUES (?, ?, ?, 'agendado', ?, ?, ?)");
            $atualizar = $pdo->prepare("UPDATE atendimentos SET nome = ?, cpf = ?, hora_pericia = COALESCE(?, hora_pericia) WHERE id = ? AND status = 'agendado'");
        } else {
            $inserir = $pdo->prepare("INSERT INTO atendimentos (nome, processo, perito, status, data_pauta, cpf) VALUES (?, ?, ?, 'agendado', ?, ?)");
            $atualizar = $pdo->prepare("UPDATE atendimentos SET nome = ?, cpf = ? WHERE id = ? AND status = 'agendado'");
        }
        $vincularAtendimento = $pdo->prepare("UPDATE atendimentos SET perito_id = ? WHERE id = ? AND status = 'agendado'");
        $buscarUsuarioCpf = $pdo->prepare("SELECT id, nome, role, cpf FROM usuarios WHERE username = ? OR REPLACE(REPLACE(REPLACE(cpf, '.', ''), '-', ''), ' ', '') = ? FOR UPDATE");
        $inserirUsuarioPerito = $pdo->prepare("INSERT INTO usuarios (username, password, role, nome, cpf, must_change_password) VALUES (?, ?, 'perito', ?, ?, 1)");
        $atualizarCpfUsuario = $pdo->prepare("UPDATE usuarios SET cpf = ? WHERE id = ? AND (cpf IS NULL OR cpf = '')");
        $cachePeritos = [];

        foreach ($registros as $r) {
            $item = null;
            if (!dataValida($r['data_pericia']) || $r['data_pericia'] < date('Y-m-d')) throw new DomainException('Data inválida no agendamento. Reimporte o arquivo.');
            $nome = $r['periciado'] ?: '(Nome pendente — conferir documento)';
            $buscar->execute([simplifyName($r['processo']), $r['data_pericia'], $r['perito']]);
            $existentes = $buscar->fetchAll();
            $item = encontrarAtendimentoCorrespondente($existentes, $r, $nome);

            if ($item) {
                $atendimentoId = (int)$item['id'];
                $processadosIds[$atendimentoId] = true;
                if ($item['status'] !== 'agendado') {
                    $preservados++;
                    continue;
                }
                $hora = !empty($r['hora_pericia']) ? $r['hora_pericia'] : null;
                if ($item['nome'] !== $nome || ($item['cpf'] ?? '') !== $r['cpf'] || ($temColunaHora && !empty($hora) && ($item['hora_pericia'] ?? null) !== $hora)) {
                    if ($temColunaHora) {
                        $atualizar->execute([$nome, $r['cpf'] ?: null, $hora, $item['id']]);
                    } else {
                        $atualizar->execute([$nome, $r['cpf'] ?: null, $item['id']]);
                    }
                    registrarLog($pdo, 'Atualizar Importação', json_encode(['antes' => $item, 'depois' => $r], JSON_UNESCAPED_UNICODE), $item['id']);
                    $atualizados++;
                }
            } else {
                $hora = !empty($r['hora_pericia']) ? $r['hora_pericia'] : null;
                if ($temColunaHora) {
                    $inserir->execute([$nome, $r['processo'], $r['perito'], $r['data_pericia'], $r['cpf'] ?: null, $hora]);
                } else {
                    $inserir->execute([$nome, $r['processo'], $r['perito'], $r['data_pericia'], $r['cpf'] ?: null]);
                }
                $atendimentoId = (int)$pdo->lastInsertId();
                $processadosIds[$atendimentoId] = true;
                registrarLog($pdo, 'Importar Agendamento', json_encode($r, JSON_UNESCAPED_UNICODE), $atendimentoId);
                $novos++;
            }

            // Gerenciamento e vínculo com o Perito
            $chavePerito = trim((string)$r['perito']) . '|' . ($r['cpf_perito'] ?? '');
            if (!array_key_exists($chavePerito, $cachePeritos)) {
                $vinculo = null;
                $cpfLimpo = !empty($r['cpf_perito']) ? preg_replace('/\D/', '', $r['cpf_perito']) : '';

                // 1. Tentar por CPF ou username do perito
                if ($cpfLimpo !== '') {
                    $buscarUsuarioCpf->execute([$cpfLimpo, $cpfLimpo]);
                    $usuariosEncontrados = $buscarUsuarioCpf->fetchAll();
                    if (!empty($usuariosEncontrados)) {
                        $peritoUsuario = null;
                        foreach ($usuariosEncontrados as $u) {
                            if ($u['role'] === 'perito') {
                                $peritoUsuario = $u;
                                break;
                            }
                        }
                        if (!$peritoUsuario) {
                            $peritoUsuario = $usuariosEncontrados[0];
                        }
                        $vinculo = (int)$peritoUsuario['id'];
                        if (empty($peritoUsuario['cpf']) && !empty($r['cpf_perito'])) {
                            $atualizarCpfUsuario->execute([$r['cpf_perito'], $vinculo]);
                        }
                    }
                }

                // 2. Se não achou por CPF, tentar por Nome Exato
                if ($vinculo === null) {
                    $vinculo = peritoId($pdo, $r['perito']);
                    if ($vinculo !== null && $cpfLimpo !== '') {
                        $atualizarCpfUsuario->execute([$r['cpf_perito'], $vinculo]);
                    }
                }

                // 3. Se não existe e tem CPF válido, pré-cadastrar automaticamente
                if ($vinculo === null && $cpfLimpo !== '') {
                    try {
                        $inserirUsuarioPerito->execute([
                            $cpfLimpo,
                            password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
                            $r['perito'],
                            $r['cpf_perito']
                        ]);
                        $vinculo = (int)$pdo->lastInsertId();
                        registrarLog($pdo, 'Pré-cadastrar Perito', "Perito pré-cadastrado via importação: {$r['perito']}", $atendimentoId);
                        $usuarios++;
                    } catch (PDOException $e) {
                        $buscarUsuarioCpf->execute([$cpfLimpo, $cpfLimpo]);
                        $uRec = $buscarUsuarioCpf->fetch();
                        if ($uRec) {
                            $vinculo = (int)$uRec['id'];
                        }
                    }
                }

                $cachePeritos[$chavePerito] = $vinculo;
            }
            $vinculo = $cachePeritos[$chavePerito];

            if ($vinculo !== null) {
                $vincularAtendimento->execute([$vinculo, $atendimentoId]);
            }
        }

        // Sincronização da pauta: cancelar agendamentos ausentes no escopo
        if (empty($datasEscopo)) {
            $datasEscopo = array_values(array_unique(array_filter(array_column($registros, 'data_pericia'))));
        }

        if ($sincronizar && !empty($datasEscopo)) {
            $inPlaceholders = implode(',', array_fill(0, count($datasEscopo), '?'));
            $buscarEscopo = $pdo->prepare("SELECT id, nome, processo, perito, data_pauta, status FROM atendimentos WHERE data_pauta IN ($inPlaceholders) AND status <> 'cancelado' FOR UPDATE");
            $buscarEscopo->execute($datasEscopo);
            $existentesEscopo = $buscarEscopo->fetchAll();

            $cancelar = $pdo->prepare("UPDATE atendimentos SET status = 'cancelado' WHERE id = ? AND status = 'agendado'");

            foreach ($existentesEscopo as $antigo) {
                $antigoId = (int)$antigo['id'];
                if (isset($processadosIds[$antigoId])) {
                    continue;
                }

                if ($antigo['status'] === 'agendado') {
                    $cancelar->execute([$antigoId]);
                    if ($cancelar->rowCount() > 0) {
                        $cancelados++;
                        $detalhe = sprintf(
                            'Removido da planilha oficial (%s - %s, perito %s, pauta %s)',
                            $antigo['processo'] ?: 'Sem processo',
                            $antigo['nome'] ?: 'Sem nome',
                            $antigo['perito'] ?: 'Sem perito',
                            $antigo['data_pauta']
                        );
                        registrarLog($pdo, 'Cancelar por Atualização de Pauta', $detalhe, $antigoId);
                    }
                } else {
                    // Atendimento em andamento ou já concluído: preservado com imunidade operacional
                    $preservados++;
                }
            }
        }

        $pdo->commit();
        return compact('novos', 'atualizados', 'preservados', 'usuarios', 'cancelados');
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    finally { $pdo->query("SELECT RELEASE_LOCK('sga_importacao')"); }
}
