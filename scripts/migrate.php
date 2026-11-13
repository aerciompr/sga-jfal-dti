<?php
// Execute com credencial de migração. Runtime não deve ter ALTER/CREATE/DROP.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/banco.php';
$lock = $pdo->query("SELECT GET_LOCK('sga_schema_migration', 30)")->fetchColumn();
if (!$lock) throw new RuntimeException('Outra migração está em execução.');
function tabelaExiste(PDO $pdo, string $tabela): bool {
    $s = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $s->execute([$tabela]); return (bool)$s->fetchColumn();
}
function colunaExiste(PDO $pdo, string $tabela, string $coluna): bool {
    $s = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $s->execute([$tabela, $coluna]); return (bool)$s->fetchColumn();
}
function indiceExiste(PDO $pdo, string $tabela, string $indice): bool {
    $s = $pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?');
    $s->execute([$tabela, $indice]); return (bool)$s->fetchColumn();
}
function chavePrimariaContem(PDO $pdo, string $tabela, string $coluna): bool {
    $s = $pdo->prepare("SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = 'PRIMARY' AND COLUMN_NAME = ?");
    $s->execute([$tabela, $coluna]); return (bool)$s->fetchColumn();
}
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS subsecoes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(100) NOT NULL,
            cidade VARCHAR(100) NOT NULL,
            sigla VARCHAR(10) NOT NULL UNIQUE,
            endereco VARCHAR(255) NOT NULL,
            telefone VARCHAR(50) NOT NULL,
            token_painel VARCHAR(64) NOT NULL UNIQUE,
            ativo TINYINT NOT NULL DEFAULT 1,
            criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS atendimentos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(100) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'agendado',
            senha VARCHAR(20) DEFAULT '---',
            chamado_em TIMESTAMP NULL DEFAULT NULL,
            sala VARCHAR(50) DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS usuarios (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role VARCHAR(20) NOT NULL DEFAULT 'recepcao'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS salas_peritos (
            perito VARCHAR(100) NOT NULL PRIMARY KEY,
            sala VARCHAR(50) NOT NULL,
            atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS salas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(50) NOT NULL UNIQUE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS nomes_validados (
            nome VARCHAR(100) NOT NULL PRIMARY KEY,
            freq INT NOT NULL,
            atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS logs_auditoria (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT NULL,
            usuario_nome VARCHAR(100) NOT NULL,
            acao VARCHAR(50) NOT NULL,
            detalhes TEXT,
            atendimento_id INT NULL,
            criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_atendimento (atendimento_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS pautas_liberadas (
            data_pauta DATE PRIMARY KEY,
            liberado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            usuario_id INT NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS sequencia_diaria_senhas (
            data_pauta DATE PRIMARY KEY,
            ultimo_numero INT NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS configuracoes (
            chave VARCHAR(100) PRIMARY KEY,
            valor TEXT,
            atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS eventos_chamada (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, atendimento_id INT NOT NULL,
            nome VARCHAR(100) NOT NULL, senha VARCHAR(20), sala VARCHAR(50) NOT NULL,
            perito VARCHAR(100), prioridade VARCHAR(20) NOT NULL DEFAULT 'normal', data_pauta DATE NOT NULL, criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_evento_data (data_pauta, id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS locks_peritos (perito VARCHAR(100) PRIMARY KEY) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS login_tentativas (
            chave CHAR(64) PRIMARY KEY, tentativas INT NOT NULL DEFAULT 0,
            janela DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    if (!colunaExiste($pdo, 'atendimentos', 'processo')) $pdo->exec("ALTER TABLE atendimentos ADD COLUMN processo VARCHAR(50) DEFAULT NULL");
    if (!colunaExiste($pdo, 'atendimentos', 'chegada_em')) $pdo->exec("ALTER TABLE atendimentos ADD COLUMN chegada_em TIMESTAMP NULL DEFAULT NULL");
    if (!colunaExiste($pdo, 'atendimentos', 'perito')) $pdo->exec("ALTER TABLE atendimentos ADD COLUMN perito VARCHAR(100) DEFAULT NULL");
    if (!colunaExiste($pdo, 'atendimentos', 'data_pauta')) $pdo->exec("ALTER TABLE atendimentos ADD COLUMN data_pauta DATE DEFAULT NULL");
    if (!colunaExiste($pdo, 'atendimentos', 'cpf')) $pdo->exec("ALTER TABLE atendimentos ADD COLUMN cpf VARCHAR(20) DEFAULT NULL");
    if (!colunaExiste($pdo, 'usuarios', 'nome')) $pdo->exec("ALTER TABLE usuarios ADD COLUMN nome VARCHAR(100) DEFAULT NULL");
    if (!colunaExiste($pdo, 'usuarios', 'cpf')) $pdo->exec("ALTER TABLE usuarios ADD COLUMN cpf VARCHAR(20) DEFAULT NULL");
    if (!colunaExiste($pdo, 'atendimentos', 'iniciado_em')) $pdo->exec("ALTER TABLE atendimentos ADD COLUMN iniciado_em TIMESTAMP NULL DEFAULT NULL");
    if (!colunaExiste($pdo, 'atendimentos', 'finalizado_em')) $pdo->exec("ALTER TABLE atendimentos ADD COLUMN finalizado_em TIMESTAMP NULL DEFAULT NULL");
    if (!colunaExiste($pdo, 'atendimentos', 'chamadas_count')) $pdo->exec("ALTER TABLE atendimentos ADD COLUMN chamadas_count INT DEFAULT 0");
    if (!colunaExiste($pdo, 'logs_auditoria', 'atendimento_id')) $pdo->exec("ALTER TABLE logs_auditoria ADD COLUMN atendimento_id INT NULL DEFAULT NULL");
    if (!indiceExiste($pdo, 'logs_auditoria', 'idx_atendimento')) $pdo->exec("ALTER TABLE logs_auditoria ADD INDEX idx_atendimento (atendimento_id)");
    $add = [
        ['usuarios','session_version','INT NOT NULL DEFAULT 1'],
        ['usuarios','must_change_password','TINYINT NOT NULL DEFAULT 0'],
        ['atendimentos','perito_id','INT NULL'],
        ['atendimentos','fila_em','TIMESTAMP(6) NULL DEFAULT NULL'],
        ['atendimentos','primeira_chamada_em','TIMESTAMP NULL DEFAULT NULL'],
        ['logs_auditoria','ip','VARCHAR(45) NULL'],
        ['logs_auditoria','request_id','VARCHAR(32) NULL'],
        ['atendimentos','hora_pericia','TIME NULL DEFAULT NULL'],
        ['salas_peritos','criterio_fila',"VARCHAR(20) NOT NULL DEFAULT 'chegada'"],
        ['atendimentos','prioridade',"VARCHAR(20) NOT NULL DEFAULT 'normal'"],
        ['salas_peritos','atender_prioridade','TINYINT(1) NOT NULL DEFAULT 0'],
        ['eventos_chamada','prioridade',"VARCHAR(20) NOT NULL DEFAULT 'normal'"],
        ['atendimentos','subsecao_id','INT NOT NULL DEFAULT 1'],
        ['usuarios','subsecao_id','INT NULL DEFAULT 1'],
        ['salas','subsecao_id','INT NOT NULL DEFAULT 1'],
        ['salas_peritos','subsecao_id','INT NOT NULL DEFAULT 1'],
        ['locks_peritos','subsecao_id','INT NOT NULL DEFAULT 1'],
        ['sequencia_diaria_senhas','subsecao_id','INT NOT NULL DEFAULT 1'],
        ['pautas_liberadas','subsecao_id','INT NOT NULL DEFAULT 1'],
        ['eventos_chamada','subsecao_id','INT NOT NULL DEFAULT 1'],
        ['logs_auditoria','subsecao_id','INT NULL DEFAULT 1'],
    ];
    foreach ($add as [$table, $column, $definition]) {
        if (tabelaExiste($pdo, $table) && !colunaExiste($pdo, $table, $column)) {
            $pdo->exec("ALTER TABLE $table ADD COLUMN $column $definition");
        }
    }
    $pdo->exec("UPDATE atendimentos a JOIN (SELECT MIN(id) AS id, nome FROM usuarios WHERE role='perito' AND nome IS NOT NULL AND nome<>'' GROUP BY nome HAVING COUNT(*)=1) u ON a.perito=u.nome SET a.perito_id=u.id WHERE a.perito_id IS NULL");
    $indices = [
        ['atendimentos','idx_fila','data_pauta, perito, status, chegada_em, id'],
        ['atendimentos','idx_sala','data_pauta, sala, status, chamado_em'],
        ['atendimentos','idx_perito_id','perito_id, data_pauta, status, chegada_em'],
        ['atendimentos','idx_processo','processo, data_pauta'],
        ['logs_auditoria','idx_log_data','criado_em, id'],
        ['atendimentos','idx_sub_data_status','subsecao_id, data_pauta, status'],
        ['eventos_chamada','idx_sub_evento','subsecao_id, data_pauta, id'],
    ];
    foreach ($indices as [$table, $name, $columns]) {
        if (tabelaExiste($pdo, $table) && !indiceExiste($pdo, $table, $name)) {
            $pdo->exec("ALTER TABLE $table ADD INDEX $name ($columns)");
        }
    }
    // Chaves compostas e constraints multi-subseção
    if (tabelaExiste($pdo, 'salas_peritos') && colunaExiste($pdo, 'salas_peritos', 'subsecao_id') && !chavePrimariaContem($pdo, 'salas_peritos', 'subsecao_id')) {
        $pdo->exec("ALTER TABLE salas_peritos DROP PRIMARY KEY, ADD PRIMARY KEY (perito, subsecao_id)");
    }
    if (tabelaExiste($pdo, 'locks_peritos') && colunaExiste($pdo, 'locks_peritos', 'subsecao_id') && !chavePrimariaContem($pdo, 'locks_peritos', 'subsecao_id')) {
        $pdo->exec("ALTER TABLE locks_peritos DROP PRIMARY KEY, ADD PRIMARY KEY (perito, subsecao_id)");
    }
    if (tabelaExiste($pdo, 'sequencia_diaria_senhas') && colunaExiste($pdo, 'sequencia_diaria_senhas', 'subsecao_id') && !chavePrimariaContem($pdo, 'sequencia_diaria_senhas', 'subsecao_id')) {
        $pdo->exec("ALTER TABLE sequencia_diaria_senhas DROP PRIMARY KEY, ADD PRIMARY KEY (data_pauta, subsecao_id)");
    }
    if (tabelaExiste($pdo, 'pautas_liberadas') && colunaExiste($pdo, 'pautas_liberadas', 'subsecao_id') && !chavePrimariaContem($pdo, 'pautas_liberadas', 'subsecao_id')) {
        $pdo->exec("ALTER TABLE pautas_liberadas DROP PRIMARY KEY, ADD PRIMARY KEY (data_pauta, subsecao_id)");
    }
    if (tabelaExiste($pdo, 'salas') && colunaExiste($pdo, 'salas', 'subsecao_id')) {
        if (indiceExiste($pdo, 'salas', 'nome')) {
            $pdo->exec("ALTER TABLE salas DROP INDEX nome");
        }
        if (!indiceExiste($pdo, 'salas', 'idx_sala_subsecao')) {
            $pdo->exec("ALTER TABLE salas ADD UNIQUE idx_sala_subsecao (subsecao_id, nome)");
        }
    }
    // Carga inicial de subseções (Maceió Sede e União dos Palmares)
    if (tabelaExiste($pdo, 'subsecoes') && (int)$pdo->query('SELECT COUNT(*) FROM subsecoes')->fetchColumn() === 0) {
        $pdo->exec("INSERT INTO subsecoes (id, nome, cidade, sigla, endereco, telefone, token_painel) VALUES
            (1, 'Subseção Judiciária de Maceió (Sede)', 'Maceió', 'MCZ', 'Av. Menino Marcelo, s/n - Serraria, Maceió - AL, CEP 57046-000', '(82) 2122-4100', 'painel_maceio_jfal_default'),
            (2, 'Subseção Judiciária de União dos Palmares', 'União dos Palmares', 'UPA', 'Rua Santa Sofia, s/n - Centro, União dos Palmares - AL, CEP 57800-000', '(82) 2122-4500', 'painel_uniao_jfal_default')
        ");
    }
    // Flag de controle: multisubsecao_ativa (0 = desligada / legado, 1 = ativa)
    $pdo->exec("INSERT IGNORE INTO configuracoes (chave, valor) VALUES ('multisubsecao_ativa', '0')");
    // Peritos e Super-Admin têm escopo global (subsecao_id = NULL)
    $pdo->exec("UPDATE usuarios SET subsecao_id = NULL WHERE (username = 'admin' AND role = 'admin') OR role = 'perito'");
    if ((int)$pdo->query('SELECT COUNT(*) FROM salas')->fetchColumn()===0) $pdo->exec("INSERT INTO salas (nome) VALUES ('Sala 1'), ('Sala 2'), ('Sala 3'), ('Sala 4')");
    $pdo->exec('UPDATE atendimentos SET fila_em=chegada_em WHERE fila_em IS NULL AND chegada_em IS NOT NULL');
    $pdo->exec("INSERT INTO sequencia_diaria_senhas (data_pauta,ultimo_numero) SELECT data_pauta,MAX(CAST(SUBSTRING(senha,4) AS UNSIGNED)) FROM atendimentos WHERE data_pauta IS NOT NULL AND senha REGEXP '^PE-[0-9]+$' GROUP BY data_pauta ON DUPLICATE KEY UPDATE ultimo_numero=GREATEST(ultimo_numero,VALUES(ultimo_numero))");
    $pdo->exec("UPDATE atendimentos SET primeira_chamada_em = chamado_em WHERE primeira_chamada_em IS NULL AND chamado_em IS NOT NULL");
    // Marca credenciais legadas previsíveis; não divulga CPF ou senha no output.
    $senhasPadraoIniciais = [
        '123456', '12345678', '123456789', 'admin', 'admin123',
        'jfal123', 'jfal@123', 'mudar123', 'mudar@123', 'perito123',
        'perito@123', 'senha123', 'senha@123', 'trocar123', 'trocar@123',
        'primeiroacesso', 'trocarsenha'
    ];
    foreach ($pdo->query('SELECT id, username, password, cpf, role FROM usuarios')->fetchAll() as $u) {
        $cpf = preg_replace('/\D/', '', $u['cpf'] ?? '');
        $ehPrevisivel = false;
        if (password_verify('admin', $u['password']) || password_verify($u['username'], $u['password'])) {
            $ehPrevisivel = true;
        }
        if ($cpf !== '') {
            if (password_verify($cpf, $u['password']) || (strlen($cpf) >= 6 && password_verify(substr($cpf, 0, 6), $u['password']))) {
                $ehPrevisivel = true;
            }
        }
        foreach ($senhasPadraoIniciais as $sp) {
            if (password_verify($sp, $u['password'])) {
                $ehPrevisivel = true;
                break;
            }
        }
        if ($ehPrevisivel) {
            $s = $pdo->prepare('UPDATE usuarios SET must_change_password = 1 WHERE id = ?');
            $s->execute([$u['id']]);
        }
    }
    // Assegura que peritos com credenciais iniciais pendentes troquem a senha no próximo acesso (executado apenas UMA vez)
    $pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (versao VARCHAR(50) PRIMARY KEY, aplicado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
    $migracaoJaAplicada = false;
    try {
        $stmtMig = $pdo->prepare("SELECT 1 FROM schema_migrations WHERE versao = ?");
        $stmtMig->execute(['20261113_redefinir_senhas_peritos_123456']);
        $migracaoJaAplicada = (bool)$stmtMig->fetchColumn();
    } catch (Throwable $e) {
        $migracaoJaAplicada = false;
    }
    if (!$migracaoJaAplicada) {
        $hashPadraoPerito = password_hash('123456', PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE usuarios SET password = ?, must_change_password = 1, session_version = session_version + 1 WHERE role = 'perito'")->execute([$hashPadraoPerito]);
        $pdo->exec("INSERT IGNORE INTO schema_migrations (versao) VALUES ('20261113_redefinir_senhas_peritos_123456')");
    }
    if ((int)$pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn() === 0) {
        $password = getenv('BOOTSTRAP_ADMIN_PASSWORD') ?: '';
        if (strlen($password) < 12) throw new RuntimeException('Banco novo exige BOOTSTRAP_ADMIN_PASSWORD de 12 caracteres ou mais.');
        $s = $pdo->prepare("INSERT INTO usuarios (username, password, role, nome, must_change_password) VALUES ('admin', ?, 'admin', 'Administrador', 1)");
        $s->execute([password_hash($password, PASSWORD_DEFAULT)]);
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (versao VARCHAR(50) PRIMARY KEY, aplicado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
    $pdo->exec("INSERT IGNORE INTO schema_migrations (versao) VALUES ('20261022_hardening'), ('20261113_suporte_multisubsecao_schema')");
    $runtimeUser = getenv('RUNTIME_DB_USER') ?: '';
    if ($runtimeUser !== '') {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $runtimeUser) || !preg_match('/^[a-zA-Z0-9_]+$/', DB_NAME) || $runtimeUser === 'root') throw new RuntimeException('Usuário de runtime inválido.');
        $account = $pdo->quote($runtimeUser) . "@'%'";
        // Conta dedicada: remove privilégios amplos, inclusive grants de banco com '_' escapado.
        $pdo->exec('REVOKE ALL PRIVILEGES, GRANT OPTION FROM ' . $account);
        $permissoes = [
            'subsecoes'=>'SELECT, INSERT, UPDATE',
            'atendimentos'=>'SELECT, INSERT, UPDATE', 'usuarios'=>'SELECT, INSERT, UPDATE, DELETE',
            'salas'=>'SELECT, INSERT, UPDATE, DELETE', 'salas_peritos'=>'SELECT, INSERT, UPDATE, DELETE',
            'pautas_liberadas'=>'SELECT, INSERT, DELETE', 'sequencia_diaria_senhas'=>'SELECT, INSERT, UPDATE',
            'configuracoes'=>'SELECT, INSERT, UPDATE', 'logs_auditoria'=>'SELECT, INSERT',
            'eventos_chamada'=>'SELECT, INSERT', 'locks_peritos'=>'SELECT, INSERT',
            'login_tentativas'=>'SELECT, INSERT, UPDATE, DELETE', 'schema_migrations'=>'SELECT',
            'nomes_validados'=>'SELECT',
        ];
        foreach ($permissoes as $tabela=>$privilegios) $pdo->exec('GRANT ' . $privilegios . ' ON `' . DB_NAME . '`.`' . $tabela . '` TO ' . $account);
    }
    echo "Migração concluída. Nenhuma pauta foi removida ou normalizada.\n";
} finally { $pdo->query("SELECT RELEASE_LOCK('sga_schema_migration')"); }
