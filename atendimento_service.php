<?php
declare(strict_types=1);

function getNextSenha(PDO $pdo, ?int $subsecaoId = null): string {
    if (!$pdo->inTransaction()) throw new LogicException('Sequência exige transação.');
    $subId = $subsecaoId ?? subsecaoAtivaId($pdo);
    if (colunaExiste($pdo, 'sequencia_diaria_senhas', 'subsecao_id')) {
        $stmtInit = $pdo->prepare("INSERT IGNORE INTO sequencia_diaria_senhas (data_pauta, subsecao_id, ultimo_numero) VALUES (CURRENT_DATE(), ?, 0)");
        $stmtInit->execute([$subId]);
        $stmtUp = $pdo->prepare('UPDATE sequencia_diaria_senhas SET ultimo_numero = LAST_INSERT_ID(ultimo_numero + 1) WHERE data_pauta = CURRENT_DATE() AND subsecao_id = ?');
        $stmtUp->execute([$subId]);
    } else {
        $pdo->exec("INSERT IGNORE INTO sequencia_diaria_senhas (data_pauta, ultimo_numero) VALUES (CURRENT_DATE(), 0)");
        $pdo->exec('UPDATE sequencia_diaria_senhas SET ultimo_numero = LAST_INSERT_ID(ultimo_numero + 1) WHERE data_pauta = CURRENT_DATE()');
    }
    return 'PE-' . sprintf('%03d', (int)$pdo->lastInsertId());
}

function alterarPrioridadeAtendimento(PDO $pdo, int $id, string $prioridade): void {
    if (!in_array($prioridade, ['normal', 'prioridade', 'superprioridade'], true)) {
        throw new DomainException('Tipo de prioridade inválido.');
    }
    $temPrioridade = colunaExiste($pdo, 'atendimentos', 'prioridade');
    if (!$temPrioridade) return;
    $stmt = $pdo->prepare("UPDATE atendimentos SET prioridade = ? WHERE id = ?");
    $stmt->execute([$prioridade, $id]);
    registrarLog($pdo, 'Alterar Prioridade', "Atendimento: $id; Prioridade: $prioridade", $id);
}

function confirmarPresencas(PDO $pdo, array $ids, string $role, ?string $prioridade = null): int {
    $ids = array_values(array_unique(array_map('intval', $ids))); sort($ids);
    if (empty($ids)) return 0;
    $pdo->beginTransaction();
    try {
        $total = 0;
        $check = $pdo->prepare('SELECT * FROM atendimentos WHERE id = ? FOR UPDATE');
        $temPrio = colunaExiste($pdo, 'atendimentos', 'prioridade');
        $comPrio = ($prioridade !== null && in_array($prioridade, ['normal', 'prioridade', 'superprioridade'], true) && $temPrio);
        $update = $comPrio
            ? $pdo->prepare("UPDATE atendimentos SET status = 'presente', senha = ?, prioridade = ?, chegada_em = CURRENT_TIMESTAMP, fila_em = CURRENT_TIMESTAMP(6) WHERE id = ? AND status = 'agendado'")
            : $pdo->prepare("UPDATE atendimentos SET status = 'presente', senha = ?, chegada_em = CURRENT_TIMESTAMP, fila_em = CURRENT_TIMESTAMP(6) WHERE id = ? AND status = 'agendado'");
        $pautaLiberadaCache = [];
        $isLote = count($ids) > 1;
        $motivosRejeicao = [];

        foreach ($ids as $id) {
            $check->execute([$id]); $item = $check->fetch();
            if (!$item) {
                if (!$isLote) throw new DomainException('Agendamento inexistente.');
                continue;
            }
            if ($item['data_pauta'] !== date('Y-m-d')) {
                if (!$isLote) throw new DomainException('Presença só pode ser confirmada no dia da pauta.');
                continue;
            }
            $itemSubId = isset($item['subsecao_id']) && $item['subsecao_id'] !== null ? (int)$item['subsecao_id'] : subsecaoAtivaId($pdo);
            if ($role === 'recepcao') {
                $dp = (string)$item['data_pauta'];
                $cacheKey = "{$dp}_{$itemSubId}";
                if (!isset($pautaLiberadaCache[$cacheKey])) {
                    $pautaLiberadaCache[$cacheKey] = isPautaLiberada($pdo, $dp, $itemSubId);
                }
                if (!$pautaLiberadaCache[$cacheKey]) {
                    if (!$isLote) throw new DomainException('Pauta ainda não homologada.');
                    $motivosRejeicao['nao_homologada'] = true;
                    continue;
                }
            }
            if ($item['status'] !== 'agendado') continue; // Repetição é idempotente.
            $senha = getNextSenha($pdo, $itemSubId);
            if ($comPrio) {
                $update->execute([$senha, $prioridade, $id]);
            } else {
                $update->execute([$senha, $id]);
            }
            registrarLog($pdo, 'Confirmar Presença', "Paciente: {$item['nome']}, Senha: $senha", $id, $itemSubId); $total++;
        }
        if ($total === 0 && $isLote && !empty($motivosRejeicao['nao_homologada'])) {
            throw new DomainException('A pauta desta data ainda não foi homologada pelo supervisor.');
        }
        $pdo->commit(); return $total;
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}

function eventoChamada(PDO $pdo, int $id): void {
    $temPrio = colunaExiste($pdo, 'eventos_chamada', 'prioridade');
    $temSub = colunaExiste($pdo, 'eventos_chamada', 'subsecao_id');
    if ($temSub && $temPrio) {
        $stmt = $pdo->prepare('INSERT INTO eventos_chamada (atendimento_id, nome, senha, sala, perito, prioridade, data_pauta, subsecao_id) SELECT id, nome, senha, sala, perito, COALESCE(prioridade, "normal"), data_pauta, COALESCE(subsecao_id, 1) FROM atendimentos WHERE id = ?');
    } elseif ($temPrio) {
        $stmt = $pdo->prepare('INSERT INTO eventos_chamada (atendimento_id, nome, senha, sala, perito, prioridade, data_pauta) SELECT id, nome, senha, sala, perito, COALESCE(prioridade, "normal"), data_pauta FROM atendimentos WHERE id = ?');
    } else {
        $stmt = $pdo->prepare('INSERT INTO eventos_chamada (atendimento_id, nome, senha, sala, perito, data_pauta) SELECT id, nome, senha, sala, perito, data_pauta FROM atendimentos WHERE id = ?');
    }
    $stmt->execute([$id]);
}

/**
 * Consulta quais salas já foram confirmadas ou possuem atendimento ativo hoje por outros peritos.
 */
function obterSalasOcupadasHoje(PDO $pdo, ?string $ignorarPerito = null, ?int $subsecaoId = null): array {
    $ocupadas = [];
    $ignorarNorm = $ignorarPerito !== null ? mb_strtolower(trim($ignorarPerito)) : null;
    $subId = $subsecaoId ?? subsecaoAtivaId($pdo);
    
    // 1. Peritos que confirmaram/travaram sala hoje (com expiração de inatividade de 4h)
    if (colunaExiste($pdo, 'salas_peritos', 'subsecao_id')) {
        $stmt = $pdo->prepare("SELECT perito, sala, atualizado_em FROM salas_peritos WHERE (subsecao_id = ? OR subsecao_id IS NULL OR ? IS NULL) AND DATE(atualizado_em) = CURRENT_DATE() AND atualizado_em >= (NOW() - INTERVAL 4 HOUR)");
        $stmt->execute([$subId, $subId]);
    } else {
        $stmt = $pdo->query("SELECT perito, sala, atualizado_em FROM salas_peritos WHERE DATE(atualizado_em) = CURRENT_DATE() AND atualizado_em >= (NOW() - INTERVAL 4 HOUR)");
    }
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($ignorarNorm !== null && mb_strtolower(trim((string)$row['perito'])) === $ignorarNorm) continue;
        $salaNome = trim((string)$row['sala']);
        if ($salaNome !== '') {
            $ocupadas[$salaNome] = [
                'perito' => $row['perito'],
                'origem' => 'confirmacao'
            ];
        }
    }
    
    // 2. Salas com perícia ativa hoje (chamada ou atendimento)
    if (colunaExiste($pdo, 'atendimentos', 'subsecao_id')) {
        $stmtAtivos = $pdo->prepare("SELECT sala, perito FROM atendimentos WHERE (subsecao_id = ? OR subsecao_id IS NULL OR ? IS NULL) AND data_pauta = CURRENT_DATE() AND status IN ('chamada', 'atendimento') AND sala IS NOT NULL AND sala != ''");
        $stmtAtivos->execute([$subId, $subId]);
    } else {
        $stmtAtivos = $pdo->query("SELECT sala, perito FROM atendimentos WHERE data_pauta = CURRENT_DATE() AND status IN ('chamada', 'atendimento') AND sala IS NOT NULL AND sala != ''");
    }
    while ($row = $stmtAtivos->fetch(PDO::FETCH_ASSOC)) {
        if ($ignorarNorm !== null && mb_strtolower(trim((string)$row['perito'])) === $ignorarNorm) continue;
        $salaNome = trim((string)$row['sala']);
        if ($salaNome !== '') {
            $ocupadas[$salaNome] = [
                'perito' => $row['perito'],
                'origem' => 'atendimento_ativo'
            ];
        }
    }
    
    return $ocupadas;
}

/**
 * Trava uma sala para uso exclusivo do perito no dia.
 */
function travarSalaPerito(PDO $pdo, string $perito, string $sala, ?int $subsecaoId = null): void {
    $perito = trim($perito);
    $sala = trim($sala);
    if ($perito === '' || $sala === '') throw new DomainException('Perito e sala são obrigatórios.');
    $subId = $subsecaoId ?? subsecaoAtivaId($pdo);

    $ocupadas = obterSalasOcupadasHoje($pdo, $perito, $subId);
    if (isset($ocupadas[$sala])) {
        $ocupante = $ocupadas[$sala]['perito'];
        throw new DomainException("A {$sala} já está ocupada hoje pelo perito {$ocupante}. Escolha outra sala disponível.");
    }

    if (colunaExiste($pdo, 'salas_peritos', 'subsecao_id')) {
        $stmt = $pdo->prepare("INSERT INTO salas_peritos (perito, sala, subsecao_id, atualizado_em) VALUES (?, ?, ?, CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE sala = VALUES(sala), atualizado_em = CURRENT_TIMESTAMP");
        $stmt->execute([$perito, $sala, $subId]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO salas_peritos (perito, sala, atualizado_em) VALUES (?, ?, CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE sala = VALUES(sala), atualizado_em = CURRENT_TIMESTAMP");
        $stmt->execute([$perito, $sala]);
    }
    registrarLog($pdo, 'Travar Sala', "Perito: $perito; Sala: $sala", null, $subId);
}

/**
 * Libera a sala que estava travada pelo perito.
 */
function liberarSalaPerito(PDO $pdo, string $perito, ?string $sala = null, ?int $subsecaoId = null): void {
    $perito = trim($perito);
    if ($perito === '') return;
    $subId = $subsecaoId ?? subsecaoAtivaId($pdo);
    
    if ($sala === null || $sala === '') {
        if (colunaExiste($pdo, 'salas_peritos', 'subsecao_id')) {
            $stmtSala = $pdo->prepare("SELECT sala FROM salas_peritos WHERE (perito = ? OR LOWER(TRIM(perito)) = LOWER(TRIM(?))) AND (subsecao_id = ? OR subsecao_id IS NULL OR ? IS NULL)");
            $stmtSala->execute([$perito, $perito, $subId, $subId]);
        } else {
            $stmtSala = $pdo->prepare("SELECT sala FROM salas_peritos WHERE perito = ? OR LOWER(TRIM(perito)) = LOWER(TRIM(?))");
            $stmtSala->execute([$perito, $perito]);
        }
        $sala = (string)$stmtSala->fetchColumn();
    }
    
    $sala = trim($sala);
    if ($sala !== '') {
        // Se houver perícia já iniciada (em atendimento), exige conclusão médica antes de trocar
        if (colunaExiste($pdo, 'atendimentos', 'subsecao_id')) {
            $stmtAtend = $pdo->prepare("SELECT COUNT(*) FROM atendimentos WHERE (subsecao_id = ? OR subsecao_id IS NULL OR ? IS NULL) AND data_pauta = CURRENT_DATE() AND (sala = ? OR LOWER(TRIM(sala)) = LOWER(TRIM(?))) AND (perito = ? OR LOWER(TRIM(perito)) = LOWER(TRIM(?))) AND status = 'atendimento'");
            $stmtAtend->execute([$subId, $subId, $sala, $sala, $perito, $perito]);
        } else {
            $stmtAtend = $pdo->prepare("SELECT COUNT(*) FROM atendimentos WHERE data_pauta = CURRENT_DATE() AND (sala = ? OR LOWER(TRIM(sala)) = LOWER(TRIM(?))) AND (perito = ? OR LOWER(TRIM(perito)) = LOWER(TRIM(?))) AND status = 'atendimento'");
            $stmtAtend->execute([$sala, $sala, $perito, $perito]);
        }
        if ((int)$stmtAtend->fetchColumn() > 0) {
            throw new DomainException('Não é possível liberar a sala com perícia em andamento. Conclua o atendimento antes.');
        }

        // Se houver atendimento apenas chamado (em 'chamada'), retorna para a fila da recepção para não prender a sala
        if (colunaExiste($pdo, 'atendimentos', 'subsecao_id')) {
            $stmtCham = $pdo->prepare("SELECT id, nome FROM atendimentos WHERE (subsecao_id = ? OR subsecao_id IS NULL OR ? IS NULL) AND data_pauta = CURRENT_DATE() AND (sala = ? OR LOWER(TRIM(sala)) = LOWER(TRIM(?))) AND (perito = ? OR LOWER(TRIM(perito)) = LOWER(TRIM(?))) AND status = 'chamada'");
            $stmtCham->execute([$subId, $subId, $sala, $sala, $perito, $perito]);
        } else {
            $stmtCham = $pdo->prepare("SELECT id, nome FROM atendimentos WHERE data_pauta = CURRENT_DATE() AND (sala = ? OR LOWER(TRIM(sala)) = LOWER(TRIM(?))) AND (perito = ? OR LOWER(TRIM(perito)) = LOWER(TRIM(?))) AND status = 'chamada'");
            $stmtCham->execute([$sala, $sala, $perito, $perito]);
        }
        $chamados = $stmtCham->fetchAll(PDO::FETCH_ASSOC);
        foreach ($chamados as $ch) {
            $pdo->prepare("UPDATE atendimentos SET status = 'presente', sala = NULL WHERE id = ?")->execute([$ch['id']]);
            registrarLog($pdo, 'Liberar Sala: Retorno à Fila', "Paciente {$ch['nome']} (ID {$ch['id']}) retornado à fila da recepção ao liberar a sala $sala", (int)$ch['id'], $subId);
        }
    }
    // Remove qualquer registro em salas_peritos por perito ou por sala
    if (colunaExiste($pdo, 'salas_peritos', 'subsecao_id')) {
        $stmtDel = $pdo->prepare("DELETE FROM salas_peritos WHERE (perito = ? OR LOWER(TRIM(perito)) = LOWER(TRIM(?)) OR (? != '' AND (sala = ? OR LOWER(TRIM(sala)) = LOWER(TRIM(?))))) AND (subsecao_id = ? OR subsecao_id IS NULL OR ? IS NULL)");
        $stmtDel->execute([$perito, $perito, $sala, $sala, $sala, $subId, $subId]);
    } else {
        $stmtDel = $pdo->prepare("DELETE FROM salas_peritos WHERE perito = ? OR LOWER(TRIM(perito)) = LOWER(TRIM(?)) OR (? != '' AND (sala = ? OR LOWER(TRIM(sala)) = LOWER(TRIM(?))))");
        $stmtDel->execute([$perito, $perito, $sala, $sala, $sala]);
    }
    registrarLog($pdo, 'Liberar Sala', "Perito: $perito; Sala: $sala", null, $subId);
}

/**
 * Desbloqueio administrativo forçado de uma sala travada (Uso de Admin/Supervisor).
 * Remove o lock e devolve pacientes presos para a fila da recepção.
 */
function desbloquearSalaAdministrativa(PDO $pdo, string $sala, string $usuarioNome, string $motivo = 'Desbloqueio administrativo', ?int $subsecaoId = null): array {
    $sala = trim($sala);
    if ($sala === '') throw new DomainException('Informe a sala a ser desbloqueada.');
    $subId = $subsecaoId ?? subsecaoAtivaId($pdo);

    $pdo->beginTransaction();
    try {
        // 1. Remove trava da tabela salas_peritos (case-insensitive e tolerante a espaços)
        if (colunaExiste($pdo, 'salas_peritos', 'subsecao_id')) {
            $stmtDel = $pdo->prepare("DELETE FROM salas_peritos WHERE (sala = ? OR LOWER(TRIM(sala)) = LOWER(TRIM(?))) AND (subsecao_id = ? OR subsecao_id IS NULL OR ? IS NULL)");
            $stmtDel->execute([$sala, $sala, $subId, $subId]);
        } else {
            $stmtDel = $pdo->prepare("DELETE FROM salas_peritos WHERE sala = ? OR LOWER(TRIM(sala)) = LOWER(TRIM(?))");
            $stmtDel->execute([$sala, $sala]);
        }
        $travasRemovidas = $stmtDel->rowCount();

        // 2. Identifica atendimentos presos na sala em chamada ou atendimento
        if (colunaExiste($pdo, 'atendimentos', 'subsecao_id')) {
            $stmtAtend = $pdo->prepare("SELECT id, nome, perito, status FROM atendimentos WHERE (subsecao_id = ? OR subsecao_id IS NULL OR ? IS NULL) AND data_pauta = CURRENT_DATE() AND (sala = ? OR LOWER(TRIM(sala)) = LOWER(TRIM(?))) AND status IN ('chamada', 'atendimento') FOR UPDATE");
            $stmtAtend->execute([$subId, $subId, $sala, $sala]);
        } else {
            $stmtAtend = $pdo->prepare("SELECT id, nome, perito, status FROM atendimentos WHERE data_pauta = CURRENT_DATE() AND (sala = ? OR LOWER(TRIM(sala)) = LOWER(TRIM(?))) AND status IN ('chamada', 'atendimento') FOR UPDATE");
            $stmtAtend->execute([$sala, $sala]);
        }
        $presos = $stmtAtend->fetchAll(PDO::FETCH_ASSOC);

        $atendimentosLiberados = 0;
        foreach ($presos as $p) {
            $stmtReset = $pdo->prepare("UPDATE atendimentos SET status = 'presente', sala = NULL WHERE id = ?");
            $stmtReset->execute([$p['id']]);
            $atendimentosLiberados++;
            registrarLog($pdo, 'Desbloqueio Sala: Liberar Paciente', "Paciente: {$p['nome']} (ID {$p['id']}) retornado para a fila de espera. Estava em {$p['status']} na sala {$sala}.", (int)$p['id'], $subId);
        }

        registrarLog($pdo, 'Desbloqueio Forçado de Sala', "Sala: $sala desbloqueada por $usuarioNome. Motivo: $motivo. Travas: $travasRemovidas; Atendimentos devolvidos à fila: $atendimentosLiberados.", null, $subId);
        $pdo->commit();

        return [
            'sala' => $sala,
            'travas_removidas' => $travasRemovidas,
            'atendimentos_liberados' => $atendimentosLiberados,
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function obterCriterioFilaPerito(PDO $pdo, string $perito): string {
    $temCriterio = colunaExiste($pdo, 'salas_peritos', 'criterio_fila');
    if (!$temCriterio) return 'chegada';
    $stmt = $pdo->prepare("SELECT criterio_fila FROM salas_peritos WHERE perito = ?");
    $stmt->execute([$perito]);
    $crit = (string)$stmt->fetchColumn();
    return in_array($crit, ['horario', 'chegada'], true) ? $crit : 'chegada';
}

function salvarCriterioFilaPerito(PDO $pdo, string $perito, string $criterio): void {
    $criterio = in_array($criterio, ['horario', 'chegada'], true) ? $criterio : 'chegada';
    $temCriterio = colunaExiste($pdo, 'salas_peritos', 'criterio_fila');
    if (!$temCriterio) return;
    $stmt = $pdo->prepare("INSERT INTO salas_peritos (perito, sala, criterio_fila, atualizado_em) VALUES (?, 'A definir', ?, CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE criterio_fila = VALUES(criterio_fila), atualizado_em = CURRENT_TIMESTAMP");
    $stmt->execute([$perito, $criterio]);
    registrarLog($pdo, 'Alterar Critério de Fila', "Perito: $perito; Critério: $criterio");
}

function obterAtenderPrioridadePerito(PDO $pdo, string $perito): bool {
    $temCol = colunaExiste($pdo, 'salas_peritos', 'atender_prioridade');
    if (!$temCol) return false;
    $stmt = $pdo->prepare("SELECT atender_prioridade FROM salas_peritos WHERE perito = ?");
    $stmt->execute([$perito]);
    return (bool)$stmt->fetchColumn();
}

function salvarAtenderPrioridadePerito(PDO $pdo, string $perito, bool $atender): void {
    $temCol = colunaExiste($pdo, 'salas_peritos', 'atender_prioridade');
    if (!$temCol) return;
    $valor = $atender ? 1 : 0;
    $stmt = $pdo->prepare("INSERT INTO salas_peritos (perito, sala, atender_prioridade, atualizado_em) VALUES (?, 'A definir', ?, CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE atender_prioridade = VALUES(atender_prioridade), atualizado_em = CURRENT_TIMESTAMP");
    $stmt->execute([$perito, $valor]);
    $desc = $atender ? 'Ativado' : 'Desativado';
    registrarLog($pdo, 'Alterar Priorizacao Perito', "Perito: $perito; Atender Prioridade: $desc");
}

function agirAtendimento(PDO $pdo, string $acao, string $perito, string $sala, array $dados): string {
    if ($perito === '') throw new DomainException('Selecione o perito.');
    $profissionalId = ($_SESSION['usuario_role'] ?? '') === 'perito' ? (int)$_SESSION['usuario_id'] : peritoId($pdo, $perito);
    
    // Tratamento direto de trava / liberação de sala
    if ($acao === 'travar_sala') {
        travarSalaPerito($pdo, $perito, $sala);
        return "Sala {$sala} confirmada e travada com sucesso para seus atendimentos de hoje.";
    }
    if ($acao === 'liberar_sala') {
        liberarSalaPerito($pdo, $perito, $sala);
        return "Sala {$sala} liberada com sucesso.";
    }
    if ($acao === 'alternar_criterio_fila') {
        $criterio = (string)($dados['criterio_fila'] ?? 'chegada');
        salvarCriterioFilaPerito($pdo, $perito, $criterio);
        return $criterio === 'horario' ? 'Fila alternada para ordenação por Horário Agendado (PJe).' : 'Fila alternada para ordenação por Ordem de Chegada.';
    }
    if ($acao === 'alternar_atender_prioridade') {
        $atual = obterAtenderPrioridadePerito($pdo, $perito);
        $novo = isset($dados['atender_prioridade']) ? (bool)$dados['atender_prioridade'] : !$atual;
        salvarAtenderPrioridadePerito($pdo, $perito, $novo);
        return $novo ? 'Fila configurada para priorizar atendimentos preferenciais no topo.' : 'Fila configurada para seguir a ordem normal (priorização automática desativada).';
    }

    $subId = subsecaoAtivaId($pdo);
    if (colunaExiste($pdo, 'locks_peritos', 'subsecao_id')) {
        $pdo->prepare('INSERT IGNORE INTO locks_peritos (perito, subsecao_id) VALUES (?, ?)')->execute([$perito, $subId]);
    } else {
        $pdo->prepare('INSERT IGNORE INTO locks_peritos (perito) VALUES (?)')->execute([$perito]);
    }

    for ($tentativa = 0; $tentativa < 3; $tentativa++) {
        $pdo->beginTransaction();
        try {
            // Ordem estável de locks: profissional, sala, atendimento.
            if (colunaExiste($pdo, 'locks_peritos', 'subsecao_id')) {
                $lock = $pdo->prepare('SELECT perito FROM locks_peritos WHERE perito = ? AND subsecao_id = ? FOR UPDATE'); $lock->execute([$perito, $subId]);
            } else {
                $lock = $pdo->prepare('SELECT perito FROM locks_peritos WHERE perito = ? FOR UPDATE'); $lock->execute([$perito]);
            }
            if (colunaExiste($pdo, 'salas', 'subsecao_id')) {
                $lock = $pdo->prepare('SELECT id FROM salas WHERE nome = ? AND subsecao_id = ? FOR UPDATE'); $lock->execute([$sala, $subId]);
            } else {
                $lock = $pdo->prepare('SELECT id FROM salas WHERE nome = ? FOR UPDATE'); $lock->execute([$sala]);
            }
            if (!$lock->fetchColumn()) throw new DomainException('Sala inválida.');

        if (colunaExiste($pdo, 'atendimentos', 'subsecao_id')) {
            $stmt = $pdo->prepare("SELECT * FROM atendimentos WHERE subsecao_id = ? AND data_pauta = CURRENT_DATE() AND status IN ('chamada','atendimento') AND (sala = ? OR perito = ? OR perito_id = ?) ORDER BY id FOR UPDATE");
            $stmt->execute([$subId, $sala, $perito, $profissionalId]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM atendimentos WHERE data_pauta = CURRENT_DATE() AND status IN ('chamada','atendimento') AND (sala = ? OR perito = ? OR perito_id = ?) ORDER BY id FOR UPDATE");
            $stmt->execute([$sala, $perito, $profissionalId]);
        }
        $ativos = $stmt->fetchAll();
        $novo = in_array($acao, ['chamar_proximo', 'chamar_especifico', 'chamar_avulsa'], true);
        if ($novo) {
            // Validação anti-conflito de sala apenas ao tentar chamar novo paciente
            $ocupadas = obterSalasOcupadasHoje($pdo, $perito, $subId);
            if (isset($ocupadas[$sala])) {
                $ocupante = $ocupadas[$sala]['perito'];
                throw new DomainException("A {$sala} já está ocupada hoje pelo perito {$ocupante}. Escolha outra sala.");
            }

            if ($ativos) throw new DomainException('A sala ou o perito já possui atendimento ativo. Conclua-o antes de chamar outro.');
            if ($acao === 'chamar_avulsa') {
                $nome = trim((string)($dados['nome_avulso'] ?? ''));
                if ($nome === '' || mb_strlen($nome) > 100) throw new DomainException('Informe o nome (até 100 caracteres).');
                $senha = getNextSenha($pdo, $subId);
                if (colunaExiste($pdo, 'atendimentos', 'subsecao_id')) {
                    $stmt = $pdo->prepare("INSERT INTO atendimentos (nome, senha, sala, perito, perito_id, status, data_pauta, chegada_em, chamado_em, primeira_chamada_em, chamadas_count, subsecao_id) VALUES (?, ?, ?, ?, ?, 'chamada', CURRENT_DATE(), CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 1, ?)");
                    $stmt->execute([$nome, $senha, $sala, $perito, $profissionalId, $subId]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO atendimentos (nome, senha, sala, perito, perito_id, status, data_pauta, chegada_em, chamado_em, primeira_chamada_em, chamadas_count) VALUES (?, ?, ?, ?, ?, 'chamada', CURRENT_DATE(), CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 1)");
                    $stmt->execute([$nome, $senha, $sala, $perito, $profissionalId]);
                }
                $id = (int)$pdo->lastInsertId();
            } else {
                if (colunaExiste($pdo, 'atendimentos', 'subsecao_id')) {
                    $sql = "SELECT * FROM atendimentos WHERE subsecao_id = ? AND data_pauta = CURRENT_DATE() AND status = 'presente'";
                    $params = [$subId];
                } else {
                    $sql = "SELECT * FROM atendimentos WHERE data_pauta = CURRENT_DATE() AND status = 'presente'";
                    $params = [];
                }
                if (($_SESSION['usuario_role'] ?? '') === 'perito') {
                    if ($profissionalId) {
                        $sql .= ' AND (perito_id = ? OR perito = ?)';
                        $params[] = $profissionalId;
                        $params[] = $perito;
                    } else {
                        $sql .= ' AND perito = ?';
                        $params[] = $perito;
                    }
                } else {
                    $sql .= ' AND perito = ?';
                    $params[] = $perito;
                }
                if ($acao === 'chamar_especifico') { $sql .= ' AND id = ?'; $params[] = (int)($dados['id'] ?? 0); }
                $critPerito = obterCriterioFilaPerito($pdo, $perito);
                $temHora = colunaExiste($pdo, 'atendimentos', 'hora_pericia');
                $atendePrioridade = obterAtenderPrioridadePerito($pdo, $perito);
                $temPrio = colunaExiste($pdo, 'atendimentos', 'prioridade');
                $orderBase = ($critPerito === 'horario' && $temHora) ? "COALESCE(hora_pericia, '23:59:59') ASC, fila_em ASC, id ASC" : "fila_em ASC, id ASC";
                $orderProximo = ($atendePrioridade && $temPrio)
                    ? "ORDER BY CASE WHEN prioridade = 'superprioridade' THEN 1 WHEN prioridade = 'prioridade' THEN 2 ELSE 3 END ASC, " . $orderBase
                    : "ORDER BY " . $orderBase;
                $stmt = $pdo->prepare($sql . ' ' . $orderProximo . ' LIMIT 1 FOR UPDATE'); $stmt->execute($params); $item = $stmt->fetch();
                if (!$item) throw new DomainException('Não há paciente elegível na fila deste perito.');
                $id = (int)$item['id'];
                $stmt = $pdo->prepare("UPDATE atendimentos SET status = 'chamada', sala = ?, chamado_em = CURRENT_TIMESTAMP, primeira_chamada_em = COALESCE(primeira_chamada_em, CURRENT_TIMESTAMP), chamadas_count = chamadas_count + 1, perito_id = COALESCE(perito_id, ?) WHERE id = ? AND status = 'presente'");
                $stmt->execute([$sala, $profissionalId, $id]);
            }
            eventoChamada($pdo, $id); $mensagem = 'Paciente chamado.';
        } else {
            $proprios = array_values(array_filter($ativos, static function($a) use ($sala, $perito, $profissionalId) {
                if ($a['sala'] !== $sala) return false;
                $role = $_SESSION['usuario_role'] ?? '';
                if ($role === 'perito') {
                    $idMatch = $profissionalId && (int)($a['perito_id'] ?? 0) === (int)$_SESSION['usuario_id'];
                    $nomeMatch = mb_strtolower(trim((string)($a['perito'] ?? ''))) === mb_strtolower(trim($perito));
                    return $idMatch || $nomeMatch;
                }
                return mb_strtolower(trim((string)($a['perito'] ?? ''))) === mb_strtolower(trim($perito));
            }));
            if (count($proprios) !== 1) throw new DomainException('Nenhum atendimento único deste perito nesta sala.');
            $item = $proprios[0]; $id = (int)$item['id'];
            if (empty($item['perito_id']) && $profissionalId) {
                $pdo->prepare("UPDATE atendimentos SET perito_id = ? WHERE id = ?")->execute([$profissionalId, $id]);
            }
            // Impede que formulário antigo opere sobre o próximo paciente da sala.
            if ((int)($dados['atendimento_id'] ?? 0) !== $id) throw new DomainException('O atendimento mudou. Atualize a página.');
            if ($acao === 'chamar_novamente' && $item['status'] === 'chamada') {
                $stmt = $pdo->prepare('UPDATE atendimentos SET chamado_em = CURRENT_TIMESTAMP, chamadas_count = chamadas_count + 1 WHERE id = ?');
                $stmt->execute([$id]); eventoChamada($pdo, $id); $mensagem = 'Chamada repetida.';
            } elseif ($acao === 'iniciar_atendimento' && $item['status'] === 'chamada') {
                $stmt = $pdo->prepare("UPDATE atendimentos SET status = 'atendimento', iniciado_em = CURRENT_TIMESTAMP WHERE id = ?"); $stmt->execute([$id]); $mensagem = 'Perícia iniciada.';
            } elseif ($acao === 'concluir' && $item['status'] === 'atendimento') {
                $stmt = $pdo->prepare("UPDATE atendimentos SET status = 'concluido', finalizado_em = CURRENT_TIMESTAMP WHERE id = ?"); $stmt->execute([$id]); $mensagem = 'Perícia concluída.';
            } elseif ($acao === 'nao_compareceu' && $item['status'] === 'chamada') {
                // Reposicionamento com tolerância inteligente (descer 2 posições na fila)
                $sqlFila = "SELECT id, fila_em FROM atendimentos WHERE data_pauta = CURRENT_DATE() AND status = 'presente' AND id != ?";
                if (($_SESSION['usuario_role'] ?? '') === 'perito') {
                    if ($profissionalId) {
                        $sqlFila .= ' AND (perito_id = ? OR perito = ?)';
                        $paramsFila = [$id, $profissionalId, $perito];
                    } else {
                        $sqlFila .= ' AND perito = ?';
                        $paramsFila = [$id, $perito];
                    }
                } else {
                    $sqlFila .= ' AND perito = ?';
                    $paramsFila = [$id, $perito];
                }
                $sqlFila .= ' ORDER BY fila_em ASC, id ASC LIMIT 3';
                $stmtFila = $pdo->prepare($sqlFila);
                $stmtFila->execute($paramsFila);
                $proximos = $stmtFila->fetchAll(PDO::FETCH_ASSOC);

                if (empty($proximos)) {
                    $novaFilaEm = date('Y-m-d H:i:s') . '.000000';
                } elseif (count($proximos) === 1) {
                    $t1 = strtotime($proximos[0]['fila_em'] ?: date('Y-m-d H:i:s'));
                    $novaFilaEm = date('Y-m-d H:i:s', $t1 + 1) . '.000000';
                } else {
                    $fila2 = $proximos[1]['fila_em'] ?: date('Y-m-d H:i:s');
                    $t2 = strtotime($fila2);
                    if (isset($proximos[2])) {
                        $fila3 = $proximos[2]['fila_em'] ?: date('Y-m-d H:i:s');
                        $t3 = strtotime($fila3);
                        if ($t3 > $t2) {
                            $diff = $t3 - $t2;
                            $novaFilaEm = date('Y-m-d H:i:s', $t2 + max(1, (int)($diff / 2))) . '.500000';
                        } else {
                            $novaFilaEm = date('Y-m-d H:i:s', $t2 + 1) . '.000000';
                        }
                    } else {
                        $novaFilaEm = date('Y-m-d H:i:s', $t2 + 1) . '.000000';
                    }
                }
                $stmt = $pdo->prepare("UPDATE atendimentos SET status = 'presente', sala = NULL, fila_em = ? WHERE id = ?");
                $stmt->execute([$novaFilaEm, $id]);
                $mensagem = 'Paciente ausente reposicionado com tolerância (desceu 2 posições na fila).';
            } elseif (in_array($acao, ['desistencia', 'marcar_desistencia'], true)) {
                $stmt = $pdo->prepare("UPDATE atendimentos SET status = 'desistente', sala = NULL, finalizado_em = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$id]);
                $mensagem = 'Desistência registrada. O periciado foi retirado da fila.';
            } else throw new DomainException('Transição não permitida para o estado atual.');
        }
        registrarLog($pdo, $acao, "Perito: $perito; Sala: $sala", $id);
        $pdo->commit(); return $mensagem;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if (($e->getCode() === '40001' || ($e->errorInfo[1] ?? 0) === 1213) && $tentativa < 2) {
                usleep(25000 * ($tentativa + 1));
                continue;
            }
            throw $e;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
    throw new DomainException('Não foi possível processar a operação devido à concorrência. Tente novamente.');
}

/**
 * Retorna o resumo consolidado dos peritos ativos, salas, contadores e filas do dia.
 */
function obterResumoFilasPeritos(PDO $pdo, string $data, ?int $subsecaoId = null): array {
    $subId = $subsecaoId ?? subsecaoAtivaId($pdo);
    $temSubAtend = colunaExiste($pdo, 'atendimentos', 'subsecao_id');
    $temSubSalasP = colunaExiste($pdo, 'salas_peritos', 'subsecao_id');

    if ($temSubAtend) {
        $stmtPeritos = $pdo->prepare("
            SELECT DISTINCT perito, perito_id 
            FROM atendimentos 
            WHERE subsecao_id = ? AND data_pauta = ? AND perito IS NOT NULL AND perito != ''
            ORDER BY perito ASC
        ");
        $stmtPeritos->execute([$subId, $data]);
    } else {
        $stmtPeritos = $pdo->prepare("
            SELECT DISTINCT perito, perito_id 
            FROM atendimentos 
            WHERE data_pauta = ? AND perito IS NOT NULL AND perito != ''
            ORDER BY perito ASC
        ");
        $stmtPeritos->execute([$data]);
    }
    $peritos = $stmtPeritos->fetchAll(PDO::FETCH_ASSOC);

    $temCriterio = colunaExiste($pdo, 'salas_peritos', 'criterio_fila');
    $salasMap = [];
    $criteriosMap = [];
    $prioridadesConfigMap = [];
    $temPrioridadeConfig = colunaExiste($pdo, 'salas_peritos', 'atender_prioridade');
    if ($temCriterio) {
        $colPrioConfig = $temPrioridadeConfig ? ", atender_prioridade" : "";
        if ($temSubSalasP) {
            $stmtSalas = $pdo->prepare("SELECT perito, sala, criterio_fila $colPrioConfig FROM salas_peritos WHERE subsecao_id = ?");
            $stmtSalas->execute([$subId]);
        } else {
            $stmtSalas = $pdo->query("SELECT perito, sala, criterio_fila $colPrioConfig FROM salas_peritos");
        }
        while ($r = $stmtSalas->fetch(PDO::FETCH_ASSOC)) {
            $salasMap[$r['perito']] = $r['sala'];
            $criteriosMap[$r['perito']] = $r['criterio_fila'];
            if ($temPrioridadeConfig) {
                $prioridadesConfigMap[$r['perito']] = (bool)($r['atender_prioridade'] ?? false);
            }
        }
    } else {
        if ($temSubSalasP) {
            $stmtSalas = $pdo->prepare("SELECT perito, sala FROM salas_peritos WHERE subsecao_id = ?");
            $stmtSalas->execute([$subId]);
            $salasMap = $stmtSalas->fetchAll(PDO::FETCH_KEY_PAIR);
        } else {
            $salasMap = $pdo->query("SELECT perito, sala FROM salas_peritos")->fetchAll(PDO::FETCH_KEY_PAIR);
        }
    }

    $temHora = colunaExiste($pdo, 'atendimentos', 'hora_pericia');
    $resultado = [];

    foreach ($peritos as $p) {
        $peritoNome = (string)$p['perito'];
        $peritoId = !empty($p['perito_id']) ? (int)$p['perito_id'] : null;

        $sala = $salasMap[$peritoNome] ?? 'A definir';
        $criterioFila = $criteriosMap[$peritoNome] ?? 'chegada';

        // Paciente em atendimento ou chamada ativa
        $whereSubAtend = $temSubAtend ? "subsecao_id = ? AND " : "";
        $paramsAtivo = $temSubAtend ? [$subId, $data, $peritoId, $peritoId, $peritoNome] : [$data, $peritoId, $peritoId, $peritoNome];
        $stmtAtivo = $pdo->prepare("
            SELECT id, nome, processo, senha, sala, status, chamadas_count, chamado_em, iniciado_em
            FROM atendimentos
            WHERE $whereSubAtend data_pauta = ? AND status IN ('chamada', 'atendimento') 
              AND (perito_id = ? OR (? IS NULL AND perito = ?))
            ORDER BY chamado_em DESC LIMIT 1
        ");
        $stmtAtivo->execute($paramsAtivo);
        $ativo = $stmtAtivo->fetch(PDO::FETCH_ASSOC) ?: null;

        if ($ativo && !empty($ativo['sala'])) {
            $sala = $ativo['sala'];
        }

        // Contadores
        $paramsCont = $temSubAtend ? [$subId, $data, $peritoId, $peritoId, $peritoNome] : [$data, $peritoId, $peritoId, $peritoNome];
        $stmtCont = $pdo->prepare("
            SELECT status, COUNT(*) as qtd
            FROM atendimentos
            WHERE $whereSubAtend data_pauta = ? AND (perito_id = ? OR (? IS NULL AND perito = ?))
            GROUP BY status
        ");
        $stmtCont->execute($paramsCont);
        $contagens = $stmtCont->fetchAll(PDO::FETCH_KEY_PAIR);

        $totAgendados = (int)($contagens['agendado'] ?? 0);
        $totPresentes = (int)($contagens['presente'] ?? 0);
        $totConcluidos = (int)($contagens['concluido'] ?? 0);
        $totDesistentes = (int)($contagens['desistente'] ?? 0);
        $totEmAtendimento = (int)($contagens['chamada'] ?? 0) + (int)($contagens['atendimento'] ?? 0);

        $atendePrioridade = $prioridadesConfigMap[$peritoNome] ?? false;
        $temPrio = colunaExiste($pdo, 'atendimentos', 'prioridade');
        $orderBase = ($criterioFila === 'horario' && $temHora) ? "COALESCE(hora_pericia, '23:59:59') ASC, fila_em ASC, id ASC" : "fila_em ASC, id ASC";
        $orderFila = ($atendePrioridade && $temPrio) ? "CASE WHEN prioridade = 'superprioridade' THEN 1 WHEN prioridade = 'prioridade' THEN 2 ELSE 3 END ASC, " . $orderBase : $orderBase;
        $colsHora = ($temHora ? ", hora_pericia" : "") . ($temPrio ? ", prioridade" : "");

        $paramsFila = $temSubAtend ? [$subId, $data, $peritoId, $peritoId, $peritoNome] : [$data, $peritoId, $peritoId, $peritoNome];
        $stmtFila = $pdo->prepare("
            SELECT id, nome, processo, cpf, senha, status, chegada_em, fila_em, chamadas_count $colsHora
            FROM atendimentos
            WHERE $whereSubAtend data_pauta = ? AND status = 'presente'
              AND (perito_id = ? OR (? IS NULL AND perito = ?))
            ORDER BY $orderFila
        ");
        $stmtFila->execute($paramsFila);
        $filaRaw = $stmtFila->fetchAll(PDO::FETCH_ASSOC);

        $fila = [];
        $pos = 1;
        $totPrioritarios = 0;
        foreach ($filaRaw as $f) {
            $fCount = (int)$f['chamadas_count'];
            $fPrio = (string)($f['prioridade'] ?? 'normal');
            if (in_array($fPrio, ['prioridade', 'superprioridade'], true)) {
                $totPrioritarios++;
            }
            $horaFormatada = (!empty($f['hora_pericia'])) ? date('H:i', strtotime($f['hora_pericia'])) : null;
            $fila[] = [
                'id' => (int)$f['id'],
                'posicao' => $pos,
                'pessoas_na_frente' => $pos - 1,
                'nome' => (string)($f['nome'] ?: 'Sem Nome'),
                'processo' => (string)($f['processo'] ?: 'Sem processo'),
                'cpf' => (string)($f['cpf'] ?? ''),
                'senha' => (string)$f['senha'],
                'prioridade' => $fPrio,
                'chegada_em' => !empty($f['chegada_em']) ? date('H:i:s', strtotime($f['chegada_em'])) : '--:--:--',
                'hora_pericia' => $horaFormatada,
                'chamadas_count' => $fCount,
                'ja_chamado' => $fCount > 0,
            ];
            $pos++;
        }

        $resultado[] = [
            'perito' => $peritoNome,
            'perito_id' => $peritoId,
            'sala' => $sala,
            'criterio_fila' => $criterioFila,
            'atender_prioridade' => $atendePrioridade,
            'totais' => [
                'agendados' => $totAgendados,
                'presentes' => $totPresentes,
                'concluidos' => $totConcluidos,
                'desistentes' => $totDesistentes,
                'em_atendimento' => $totEmAtendimento,
                'prioritarios' => $totPrioritarios,
                'total' => $totAgendados + $totPresentes + $totConcluidos + $totDesistentes + $totEmAtendimento
            ],
            'atendimento_ativo' => $ativo,
            'fila' => $fila,
        ];
    }

    return $resultado;
}

/**
 * Consulta indicadores executivos e detalhamento de produtividade por médico para o Dashboard.
 */
function obterMetricasDia(PDO $pdo, string $data, ?int $subsecaoId = null): array {
    $subId = $subsecaoId ?? subsecaoAtivaId($pdo);
    $temSubAtend = colunaExiste($pdo, 'atendimentos', 'subsecao_id');
    $temSubSalasP = colunaExiste($pdo, 'salas_peritos', 'subsecao_id');
    $multiAtiva = multisubsecaoAtiva($pdo);

    $whereSub = ($temSubAtend && $multiAtiva) ? " AND subsecao_id = ?" : "";
    $paramsSub = ($temSubAtend && $multiAtiva) ? [$data, $subId] : [$data];

    $stmtGeral = $pdo->prepare("
        SELECT
            COUNT(*) as total_agendados,
            SUM(CASE WHEN chegada_em IS NOT NULL THEN 1 ELSE 0 END) as presentes,
            SUM(CASE WHEN status = 'concluido' THEN 1 ELSE 0 END) as concluidos,
            SUM(CASE WHEN status IN ('chamada', 'atendimento') THEN 1 ELSE 0 END) as em_atendimento,
            SUM(CASE WHEN status = 'presente' THEN 1 ELSE 0 END) as aguardando,
            SUM(CASE WHEN status IN ('nao_compareceu', 'desistente') THEN 1 ELSE 0 END) as ausentes,
            AVG(CASE WHEN chegada_em IS NOT NULL AND (primeira_chamada_em IS NOT NULL OR iniciado_em IS NOT NULL) 
                     THEN TIMESTAMPDIFF(MINUTE, chegada_em, COALESCE(primeira_chamada_em, iniciado_em)) END) as media_espera_min,
            AVG(CASE WHEN iniciado_em IS NOT NULL AND finalizado_em IS NOT NULL 
                     THEN TIMESTAMPDIFF(MINUTE, iniciado_em, finalizado_em) END) as media_pericia_min
        FROM atendimentos
        WHERE data_pauta = ? AND status <> 'cancelado' $whereSub
    ");
    $stmtGeral->execute($paramsSub);
    $geral = $stmtGeral->fetch(PDO::FETCH_ASSOC) ?: [];
    
    $totalAgendados = (int)($geral['total_agendados'] ?? 0);
    $presentes = (int)($geral['presentes'] ?? 0);
    $concluidos = (int)($geral['concluidos'] ?? 0);
    $emAtendimento = (int)($geral['em_atendimento'] ?? 0);
    $aguardando = (int)($geral['aguardando'] ?? 0);
    $ausentes = (int)($geral['ausentes'] ?? 0);
    $mediaEspera = round((float)($geral['media_espera_min'] ?? 0), 0);
    $mediaPericia = round((float)($geral['media_pericia_min'] ?? 0), 0);
    
    $taxaAbsenteismo = $totalAgendados > 0 ? round(($ausentes / $totalAgendados) * 100, 1) : 0.0;
    $taxaComparecimento = $totalAgendados > 0 ? round(($presentes / $totalAgendados) * 100, 1) : 0.0;
    
    $stmtPeritos = $pdo->prepare("
        SELECT
            perito,
            COUNT(*) as agendados,
            SUM(CASE WHEN chegada_em IS NOT NULL THEN 1 ELSE 0 END) as presentes,
            SUM(CASE WHEN status = 'concluido' THEN 1 ELSE 0 END) as concluidos,
            SUM(CASE WHEN status IN ('chamada', 'atendimento') THEN 1 ELSE 0 END) as em_atendimento,
            SUM(CASE WHEN status = 'presente' THEN 1 ELSE 0 END) as aguardando,
            SUM(CASE WHEN status IN ('nao_compareceu', 'desistente') THEN 1 ELSE 0 END) as ausentes,
            ROUND(AVG(CASE WHEN iniciado_em IS NOT NULL AND finalizado_em IS NOT NULL 
                           THEN TIMESTAMPDIFF(MINUTE, iniciado_em, finalizado_em) END), 0) as tempo_medio_min
        FROM atendimentos
        WHERE data_pauta = ? AND status <> 'cancelado' AND perito IS NOT NULL AND perito <> '' $whereSub
        GROUP BY perito
        ORDER BY perito ASC
    ");
    $stmtPeritos->execute($paramsSub);
    $peritos = $stmtPeritos->fetchAll(PDO::FETCH_ASSOC);
    
    if ($temSubSalasP && $multiAtiva) {
        $sSalas = $pdo->prepare("SELECT perito, sala FROM salas_peritos WHERE subsecao_id = ?");
        $sSalas->execute([$subId]);
        $salasMap = $sSalas->fetchAll(PDO::FETCH_KEY_PAIR);
    } else {
        $salasMap = $pdo->query("SELECT perito, sala FROM salas_peritos")->fetchAll(PDO::FETCH_KEY_PAIR);
    }
    foreach ($peritos as &$p) {
        $p['sala'] = $salasMap[$p['perito']] ?? 'Não definida';
    }
    unset($p);
    
    return [
        'data' => $data,
        'totais' => [
            'agendados' => $totalAgendados,
            'presentes' => $presentes,
            'concluidos' => $concluidos,
            'em_atendimento' => $emAtendimento,
            'aguardando' => $aguardando,
            'ausentes' => $ausentes,
            'taxa_absenteismo' => $taxaAbsenteismo,
            'taxa_comparecimento' => $taxaComparecimento,
            'media_espera_min' => $mediaEspera,
            'media_pericia_min' => $mediaPericia,
        ],
        'peritos' => $peritos,
    ];
}
