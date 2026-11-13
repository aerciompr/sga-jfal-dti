<?php
declare(strict_types=1);

function operarUsuario(PDO $pdo, array $dados): string {
    $pdo->beginTransaction();
    try {
        // Serializa gestão de administradores para preservar ao menos um.
        $pdo->query("SELECT id FROM usuarios WHERE role = 'admin' ORDER BY id FOR UPDATE")->fetchAll();
        $acao = $dados['action'] ?? '';
        $id = (int)($dados['id'] ?? 0);
        $antes = null;

        $isSuperAdmin = !empty($_SESSION['is_superadmin']) || (($_SESSION['usuario_role'] ?? '') === 'admin' && empty($_SESSION['usuario_subsecao_id']));
        $adminSubsecaoId = (int)($_SESSION['usuario_subsecao_id'] ?? subsecaoAtivaId($pdo));
        $multiAtiva = multisubsecaoAtiva($pdo);
        $temSubUser = colunaExiste($pdo, 'usuarios', 'subsecao_id');

        if (!in_array($acao, ['cadastrar', 'exigir_troca_todos_peritos', 'redefinir_senhas_peritos_padrao'], true)) {
            $colSub = $temSubUser ? ', subsecao_id' : '';
            $s=$pdo->prepare("SELECT id, username, role, nome, must_change_password $colSub FROM usuarios WHERE id=? FOR UPDATE");
            $s->execute([$id]);
            $antes=$s->fetch();
            if (!$antes) throw new DomainException('Usuário inexistente.');

            // Restrição de Admin Local: não pode editar/excluir admins, peritos ou usuários de outras subseções
            if ($multiAtiva && !$isSuperAdmin) {
                if (in_array($antes['role'], ['admin', 'perito'], true)) {
                    throw new DomainException('Administradores locais não podem alterar contas de administradores ou peritos médicos.');
                }
                if ($temSubUser && (int)($antes['subsecao_id'] ?? 1) !== $adminSubsecaoId) {
                    throw new DomainException('Você só pode gerenciar usuários pertencentes à sua própria subseção.');
                }
            }
        }

        if ($acao === 'deletar') {
            if ($id === (int)$_SESSION['usuario_id']) throw new DomainException('Não é possível excluir a própria conta.');
            if ($antes['role']==='admin' && (int)$pdo->query("SELECT COUNT(*) FROM usuarios WHERE role='admin'")->fetchColumn() <= 1) throw new DomainException('Mantenha ao menos um administrador.');
            $s=$pdo->prepare('DELETE FROM usuarios WHERE id=?'); $s->execute([$id]);
        } elseif ($acao === 'exigir_troca') {
            $s=$pdo->prepare('UPDATE usuarios SET must_change_password = 1, session_version = session_version + 1 WHERE id = ?');
            $s->execute([$id]);
            registrarLog($pdo, 'Usuário: exigir_troca', "Exigida troca obrigatória de senha para o usuário ID $id ({$antes['username']}). Demais sessões revogadas.", null, $antes['subsecao_id'] ?? $adminSubsecaoId);
            $pdo->commit();
            return "Troca obrigatória de senha ativada para o usuário '{$antes['username']}'. Sessões ativas foram revogadas.";
        } elseif ($acao === 'exigir_troca_todos_peritos') {
            if (!$isSuperAdmin) throw new DomainException('Apenas o Administrador Geral da Sede pode gerenciar senhas globais de peritos.');
            $s=$pdo->prepare("UPDATE usuarios SET must_change_password = 1, session_version = session_version + 1 WHERE role = 'perito'");
            $s->execute();
            $afetados = $s->rowCount();
            registrarLog($pdo, 'Usuários: exigir_troca_todos_peritos', "Exigida troca obrigatória de senha para todos os peritos médicos ($afetados profissionais atualizados).");
            $pdo->commit();
            return "Troca de senha obrigatória ativada com sucesso para todos os peritos médicos ($afetados profissionais atualizados).";
        } elseif ($acao === 'redefinir_senhas_peritos_padrao') {
            if (!$isSuperAdmin) throw new DomainException('Apenas o Administrador Geral da Sede pode gerenciar senhas globais de peritos.');
            $hashPadrao = password_hash('123456', PASSWORD_DEFAULT);
            $s = $pdo->prepare("UPDATE usuarios SET password = ?, must_change_password = 1, session_version = session_version + 1 WHERE role = 'perito'");
            $s->execute([$hashPadrao]);
            $afetados = $s->rowCount();
            registrarLog($pdo, 'Usuários: redefinir_senhas_peritos_padrao', "Redefinida a senha de todos os peritos médicos ($afetados profissionais) para a padrão '123456' com troca obrigatória no próximo acesso.");
            $pdo->commit();
            return "Senha padrão '123456' definida para todos os peritos médicos ($afetados profissionais atualizados) com troca obrigatória no próximo acesso.";
        } elseif (in_array($acao,['cadastrar','editar'],true)) {
            $username=trim((string)($dados['username']??'')); $nome=trim((string)($dados['nome']??'')); $role=$dados['role']??''; $senha=(string)($dados['password']??'');
            $exigirTroca = isset($dados['must_change_password']) ? ((int)$dados['must_change_password'] ? 1 : 0) : 1;

            if ($multiAtiva && !$isSuperAdmin) {
                if (in_array($role, ['admin', 'perito'], true)) {
                    throw new DomainException('Administradores locais só podem cadastrar recepcionistas ou supervisores.');
                }
                $targetSubsecaoId = $adminSubsecaoId;
            } else {
                if ($role === 'perito' || ($role === 'admin' && empty($dados['subsecao_id']))) {
                    $targetSubsecaoId = null;
                } else {
                    $targetSubsecaoId = !empty($dados['subsecao_id']) ? (int)$dados['subsecao_id'] : 1;
                }
            }

            if ($username==='' || strlen($username)>50 || mb_strlen($nome)>100 || !in_array($role,['admin','supervisor','recepcao','perito'],true)) throw new DomainException('Login, nome ou perfil inválido.');
            if ($role==='perito' && $nome==='') throw new DomainException('Preencha o nome completo do perito.');
            if (($acao==='cadastrar' || $senha!=='') && (strlen($senha)<6 || strlen($senha)>72)) throw new DomainException('Use uma senha de 6 a 72 caracteres.');
            if ($antes && $antes['role']==='admin' && $role!=='admin' && (int)$pdo->query("SELECT COUNT(*) FROM usuarios WHERE role='admin'")->fetchColumn()<=1) throw new DomainException('Mantenha ao menos um administrador.');

            if ($acao==='cadastrar') {
                if ($temSubUser) {
                    $s=$pdo->prepare('INSERT INTO usuarios (username,password,role,nome,must_change_password,subsecao_id) VALUES (?,?,?,?,?,?)');
                    $s->execute([$username,password_hash($senha,PASSWORD_DEFAULT),$role,$nome,$exigirTroca,$targetSubsecaoId]);
                } else {
                    $s=$pdo->prepare('INSERT INTO usuarios (username,password,role,nome,must_change_password) VALUES (?,?,?,?,?)');
                    $s->execute([$username,password_hash($senha,PASSWORD_DEFAULT),$role,$nome,$exigirTroca]);
                }
                $id=(int)$pdo->lastInsertId();
            } elseif ($senha!=='') {
                if ($temSubUser) {
                    $s=$pdo->prepare('UPDATE usuarios SET username=?, password=?, role=?, nome=?, must_change_password=?, subsecao_id=?, session_version=session_version+1 WHERE id=?');
                    $s->execute([$username,password_hash($senha,PASSWORD_DEFAULT),$role,$nome,$exigirTroca,$targetSubsecaoId,$id]);
                } else {
                    $s=$pdo->prepare('UPDATE usuarios SET username=?, password=?, role=?, nome=?, must_change_password=?, session_version=session_version+1 WHERE id=?');
                    $s->execute([$username,password_hash($senha,PASSWORD_DEFAULT),$role,$nome,$exigirTroca,$id]);
                }
            } else {
                if ($temSubUser) {
                    $s=$pdo->prepare('UPDATE usuarios SET username=?, role=?, nome=?, must_change_password=?, subsecao_id=?, session_version=session_version+1 WHERE id=?');
                    $s->execute([$username,$role,$nome,$exigirTroca,$targetSubsecaoId,$id]);
                } else {
                    $s=$pdo->prepare('UPDATE usuarios SET username=?, role=?, nome=?, must_change_password=?, session_version=session_version+1 WHERE id=?');
                    $s->execute([$username,$role,$nome,$exigirTroca,$id]);
                }
            }
        } else throw new DomainException('Ação inválida.');

        registrarLog($pdo,'Usuário: '.$acao,json_encode(['id'=>$id,'antes'=>$antes,'depois'=>array_intersect_key($dados,array_flip(['username','role','nome','must_change_password','subsecao_id']))],JSON_UNESCAPED_UNICODE), null, $targetSubsecaoId ?? $adminSubsecaoId);
        $pdo->commit();
        return 'Cadastro atualizado com sucesso. Sessões anteriores foram sincronizadas.';
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
