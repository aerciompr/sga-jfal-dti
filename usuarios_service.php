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
        if ($acao !== 'cadastrar') {
            $s=$pdo->prepare('SELECT id, username, role, nome FROM usuarios WHERE id=? FOR UPDATE'); $s->execute([$id]); $antes=$s->fetch();
            if (!$antes) throw new DomainException('Usuário inexistente.');
        }
        if ($acao === 'deletar') {
            if ($id === (int)$_SESSION['usuario_id']) throw new DomainException('Não é possível excluir a própria conta.');
            if ($antes['role']==='admin' && (int)$pdo->query("SELECT COUNT(*) FROM usuarios WHERE role='admin'")->fetchColumn() <= 1) throw new DomainException('Mantenha ao menos um administrador.');
            $s=$pdo->prepare('DELETE FROM usuarios WHERE id=?'); $s->execute([$id]);
        } elseif (in_array($acao,['cadastrar','editar'],true)) {
            $username=trim((string)($dados['username']??'')); $nome=trim((string)($dados['nome']??'')); $role=$dados['role']??''; $senha=(string)($dados['password']??'');
            if ($username==='' || strlen($username)>50 || mb_strlen($nome)>100 || !in_array($role,['admin','supervisor','recepcao','perito'],true)) throw new DomainException('Login, nome ou perfil inválido.');
            if ($role==='perito' && $nome==='') throw new DomainException('Preencha o nome completo do perito.');
            if (($acao==='cadastrar' || $senha!=='') && (strlen($senha)<12 || strlen($senha)>72)) throw new DomainException('Use uma senha individual de 12 a 72 bytes.');
            if ($antes && $antes['role']==='admin' && $role!=='admin' && (int)$pdo->query("SELECT COUNT(*) FROM usuarios WHERE role='admin'")->fetchColumn()<=1) throw new DomainException('Mantenha ao menos um administrador.');
            if ($acao==='cadastrar') {
                $s=$pdo->prepare('INSERT INTO usuarios (username,password,role,nome,must_change_password) VALUES (?,?,?,?,1)'); $s->execute([$username,password_hash($senha,PASSWORD_DEFAULT),$role,$nome]); $id=(int)$pdo->lastInsertId();
            } elseif ($senha!=='') {
                $s=$pdo->prepare('UPDATE usuarios SET username=?, password=?, role=?, nome=?, must_change_password=1, session_version=session_version+1 WHERE id=?'); $s->execute([$username,password_hash($senha,PASSWORD_DEFAULT),$role,$nome,$id]);
            } else {
                $s=$pdo->prepare('UPDATE usuarios SET username=?, role=?, nome=?, session_version=session_version+1 WHERE id=?'); $s->execute([$username,$role,$nome,$id]);
            }
        } else throw new DomainException('Ação inválida.');
        registrarLog($pdo,'Usuário: '.$acao,json_encode(['id'=>$id,'antes'=>$antes,'depois'=>array_intersect_key($dados,array_flip(['username','role','nome']))],JSON_UNESCAPED_UNICODE));
        $pdo->commit(); return 'Cadastro atualizado. Senhas redefinidas exigem troca no primeiro acesso; sessões anteriores foram revogadas.';
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}
