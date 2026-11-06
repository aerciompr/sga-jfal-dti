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
        if (!in_array($acao, ['cadastrar', 'exigir_troca_todos_peritos'], true)) {
            $s=$pdo->prepare('SELECT id, username, role, nome, must_change_password FROM usuarios WHERE id=? FOR UPDATE'); $s->execute([$id]); $antes=$s->fetch();
            if (!$antes) throw new DomainException('Usuário inexistente.');
        }
        if ($acao === 'deletar') {
            if ($id === (int)$_SESSION['usuario_id']) throw new DomainException('Não é possível excluir a própria conta.');
            if ($antes['role']==='admin' && (int)$pdo->query("SELECT COUNT(*) FROM usuarios WHERE role='admin'")->fetchColumn() <= 1) throw new DomainException('Mantenha ao menos um administrador.');
            $s=$pdo->prepare('DELETE FROM usuarios WHERE id=?'); $s->execute([$id]);
        } elseif ($acao === 'exigir_troca') {
            $s=$pdo->prepare('UPDATE usuarios SET must_change_password = 1, session_version = session_version + 1 WHERE id = ?');
            $s->execute([$id]);
            registrarLog($pdo, 'Usuário: exigir_troca', "Exigida troca obrigatória de senha para o usuário ID $id ({$antes['username']}). Demais sessões revogadas.");
            $pdo->commit();
            return "Troca obrigatória de senha ativada para o usuário '{$antes['username']}'. Sessões ativas foram revogadas.";
        } elseif ($acao === 'exigir_troca_todos_peritos') {
            $s=$pdo->prepare("UPDATE usuarios SET must_change_password = 1, session_version = session_version + 1 WHERE role = 'perito'");
            $s->execute();
            $afetados = $s->rowCount();
            registrarLog($pdo, 'Usuários: exigir_troca_todos_peritos', "Exigida troca obrigatória de senha para todos os peritos médicos ($afetados profissionais atualizados).");
            $pdo->commit();
            return "Troca de senha obrigatória ativada com sucesso para todos os peritos médicos ($afetados profissionais atualizados).";
        } elseif (in_array($acao,['cadastrar','editar'],true)) {
            $username=trim((string)($dados['username']??'')); $nome=trim((string)($dados['nome']??'')); $role=$dados['role']??''; $senha=(string)($dados['password']??'');
            $exigirTroca = isset($dados['must_change_password']) ? ((int)$dados['must_change_password'] ? 1 : 0) : 1;
            if ($username==='' || strlen($username)>50 || mb_strlen($nome)>100 || !in_array($role,['admin','supervisor','recepcao','perito'],true)) throw new DomainException('Login, nome ou perfil inválido.');
            if ($role==='perito' && $nome==='') throw new DomainException('Preencha o nome completo do perito.');
            if (($acao==='cadastrar' || $senha!=='') && (strlen($senha)<12 || strlen($senha)>72)) throw new DomainException('Use uma senha individual de 12 a 72 bytes.');
            if ($antes && $antes['role']==='admin' && $role!=='admin' && (int)$pdo->query("SELECT COUNT(*) FROM usuarios WHERE role='admin'")->fetchColumn()<=1) throw new DomainException('Mantenha ao menos um administrador.');
            if ($acao==='cadastrar') {
                $s=$pdo->prepare('INSERT INTO usuarios (username,password,role,nome,must_change_password) VALUES (?,?,?,?,?)'); $s->execute([$username,password_hash($senha,PASSWORD_DEFAULT),$role,$nome,$exigirTroca]); $id=(int)$pdo->lastInsertId();
            } elseif ($senha!=='') {
                $s=$pdo->prepare('UPDATE usuarios SET username=?, password=?, role=?, nome=?, must_change_password=?, session_version=session_version+1 WHERE id=?'); $s->execute([$username,password_hash($senha,PASSWORD_DEFAULT),$role,$nome,$exigirTroca,$id]);
            } else {
                $s=$pdo->prepare('UPDATE usuarios SET username=?, role=?, nome=?, must_change_password=?, session_version=session_version+1 WHERE id=?'); $s->execute([$username,$role,$nome,$exigirTroca,$id]);
            }
        } else throw new DomainException('Ação inválida.');
        registrarLog($pdo,'Usuário: '.$acao,json_encode(['id'=>$id,'antes'=>$antes,'depois'=>array_intersect_key($dados,array_flip(['username','role','nome','must_change_password']))],JSON_UNESCAPED_UNICODE));
        $pdo->commit(); return 'Cadastro atualizado com sucesso. Sessões anteriores foram sincronizadas.';
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}
