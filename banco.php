<?php
/**
 * CONEXÃO E MIGRAÇÃO DO BANCO DE DADOS - JFAL
 * 
 * banco.php: Estabelece a conexão com a base de dados MySQL utilizando PDO.
 * Realiza a criação automática e segura de colunas necessárias para o sistema de perícias
 * e executa a limpeza retroativa de peritos cadastrados sob grafias diferentes devido a quebras de linhas no PDF.
 */

$config_file = __DIR__ . '/config.php';

// Redireciona para o instalador se o arquivo de configuração não existir
if (!file_exists($config_file)) {
    header("Location: install.php");
    exit;
}

require_once $config_file;

try {
    // Inicialização da conexão PDO com utf8mb4 para preservar caracteres especiais e acentos
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // Lança exceções em caso de erros
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // Retorna dados como array associativo
            PDO::ATTR_EMULATE_PREPARES => false, // Desativa emulação para evitar injeção SQL
        ]
    );

    // Sincroniza o fuso horário do PHP e do banco de dados (MySQL) para o fuso local de Alagoas
    date_default_timezone_set('America/Maceio');
    $pdo->exec("SET time_zone = '-03:00'");

    // Criação das tabelas fundamentais de produção caso o banco esteja vazio
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS atendimentos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(100) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'agendado',
            senha VARCHAR(20) DEFAULT '---',
            chamado_em TIMESTAMP NULL DEFAULT NULL,
            sala VARCHAR(50) DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (PDOException $e) {}

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS usuarios (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role VARCHAR(20) NOT NULL DEFAULT 'recepcao'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        // Insere o administrador padrão se a tabela de usuários estiver vazia
        $stmt = $pdo->query("SELECT COUNT(*) FROM usuarios");
        if ($stmt->fetchColumn() == 0) {
            $adminPassword = password_hash('admin', PASSWORD_DEFAULT);
            $stmtInsert = $pdo->prepare("INSERT INTO usuarios (username, password, role, nome) VALUES ('admin', ?, 'admin', 'Administrador')");
            $stmtInsert->execute([$adminPassword]);
        }
    } catch (PDOException $e) {}

    // Migrações automáticas e seguras: adiciona as colunas caso ainda não existam no banco
    try {
        $pdo->exec("ALTER TABLE atendimentos ADD COLUMN processo VARCHAR(50) DEFAULT NULL");
    } catch (PDOException $e) {}
    
    try {
        $pdo->exec("ALTER TABLE atendimentos ADD COLUMN chegada_em TIMESTAMP NULL DEFAULT NULL");
    } catch (PDOException $e) {}
    
    try {
        $pdo->exec("ALTER TABLE atendimentos ADD COLUMN perito VARCHAR(100) DEFAULT NULL");
    } catch (PDOException $e) {}
    
    try {
        $pdo->exec("ALTER TABLE atendimentos ADD COLUMN data_pauta DATE DEFAULT NULL");
    } catch (PDOException $e) {}

    try {
        $pdo->exec("ALTER TABLE atendimentos ADD COLUMN cpf VARCHAR(20) DEFAULT NULL");
    } catch (PDOException $e) {}

    // Criação da tabela para persistência de salas por perito
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS salas_peritos (
            perito VARCHAR(100) NOT NULL PRIMARY KEY,
            sala VARCHAR(50) NOT NULL,
            atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (PDOException $e) {}

    // Criação da tabela de cadastro de salas
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS salas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(50) NOT NULL UNIQUE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM salas");
        if ($stmt->fetchColumn() == 0) {
            $pdo->exec("INSERT INTO salas (nome) VALUES ('Sala 1'), ('Sala 2'), ('Sala 3'), ('Sala 4')");
        }
    } catch (PDOException $e) {}

    // Criação da tabela para cache de nomes validados
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS nomes_validados (
            nome VARCHAR(100) NOT NULL PRIMARY KEY,
            freq INT NOT NULL,
            atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (PDOException $e) {}

    // Adiciona coluna nome na tabela usuarios se não existir
    try {
        $pdo->exec("ALTER TABLE usuarios ADD COLUMN nome VARCHAR(100) DEFAULT NULL");
    } catch (PDOException $e) {}

    // Adiciona coluna cpf na tabela usuarios se não existir
    try {
        $pdo->exec("ALTER TABLE usuarios ADD COLUMN cpf VARCHAR(20) DEFAULT NULL");
    } catch (PDOException $e) {}

    // Adiciona colunas de tempo e chamadas na tabela atendimentos se não existirem
    try {
        $pdo->exec("ALTER TABLE atendimentos ADD COLUMN iniciado_em TIMESTAMP NULL DEFAULT NULL");
    } catch (PDOException $e) {}
    try {
        $pdo->exec("ALTER TABLE atendimentos ADD COLUMN finalizado_em TIMESTAMP NULL DEFAULT NULL");
    } catch (PDOException $e) {}
    try {
        $pdo->exec("ALTER TABLE atendimentos ADD COLUMN chamadas_count INT DEFAULT 0");
    } catch (PDOException $e) {}

    // Criação da tabela de logs de auditoria detalhados
    try {
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
    } catch (PDOException $e) {}

    // Criação da tabela de liberação de pauta por data
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS pautas_liberadas (
            data_pauta DATE PRIMARY KEY,
            liberado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            usuario_id INT NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (PDOException $e) {}

    // Criação da tabela de sequenciador diário de senhas
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS sequencia_diaria_senhas (
            data_pauta DATE PRIMARY KEY,
            ultimo_numero INT NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (PDOException $e) {}

    // Criação da tabela de configurações gerais
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS configuracoes (
            chave VARCHAR(100) PRIMARY KEY,
            valor TEXT,
            atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (PDOException $e) {}

    // Adiciona coluna atendimento_id na tabela logs_auditoria se não existir
    try {
        $pdo->exec("ALTER TABLE logs_auditoria ADD COLUMN atendimento_id INT NULL DEFAULT NULL");
    } catch (PDOException $e) {}
    try {
        $pdo->exec("ALTER TABLE logs_auditoria ADD INDEX idx_atendimento (atendimento_id)");
    } catch (PDOException $e) {}

    // Inicializa a data_pauta para atendimentos legados se eles estiverem nulos
    try {
        $pdo->exec("UPDATE atendimentos SET data_pauta = DATE(COALESCE(chegada_em, chamado_em, CURRENT_DATE())) WHERE data_pauta IS NULL");
    } catch (PDOException $e) {}

    // AUTOCORREÇÃO DE NOMES DE PERITOS: limpa grafias com quebras de sílaba salvas anteriormente no banco
    try {
        $stmt = $pdo->query("SELECT DISTINCT perito FROM atendimentos WHERE perito IS NOT NULL AND perito != ''");
        $dbPeritos = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $replacements = [
            "ADALT O" => "ADALTO",
            "ADEILD O" => "ADEILDO",
            "ADEILT ON" => "ADEILTON",
            "ADRIAN A" => "ADRIANA",
            "ADRIAN O" => "ADRIANO",
            "ALBERT O" => "ALBERTO",
            "ALBUQ UERQU E" => "ALBUQUERQUE",
            "ALMEID A" => "ALMEIDA",
            "AMILSO N" => "AMILSON",
            "ANDER LY" => "ANDERLY",
            "ANDRE ZA" => "ANDREZA",
            "ANERE S" => "ANERES",
            "ANGEL O" => "ANGELO",
            "ANTONI O" => "ANTONIO",
            "ANTUN ES" => "ANTUNES",
            "APARE CIDA" => "APARECIDA",
            "ARACE LY" => "ARACELY",
            "ARAUJ O" => "ARAUJO",
            "ARTHU R" => "ARTHUR",
            "AUGUS TO" => "AUGUSTO",
            "BALBIN O" => "BALBINO",
            "BARBO SA" => "BARBOSA",
            "BARRO S" => "BARROS",
            "BENEDI TA" => "BENEDITA",
            "BENEDI TO" => "BENEDITO",
            "BENEV AL" => "BENEVAL",
            "BENILT ON" => "BENILTON",
            "BETANI A" => "BETANIA",
            "BEZER RA" => "BEZERRA",
            "BORGE S" => "BORGES",
            "CAETA NO" => "CAETANO",
            "CANDID O" => "CANDIDO",
            "CARDO SO" => "CARDOSO",
            "CARLO S" => "CARLOS",
            "CARME M" => "CARMEM",
            "CASAD O" => "CASADO",
            "CASEIR A" => "CASEIRA",
            "CAVAL CANTE" => "CAVALCANTE",
            "CHRYS TINA" => "CHRYSTINA",
            "CICER A" => "CICERA",
            "CLARIC E" => "CLARICE",
            "CLAUDI A" => "CLAUDIA",
            "CLEBS ON" => "CLEBSON",
            "CONCEI CAO" => "CONCEICAO",
            "CORCI NO" => "CORCINO",
            "CORDEI RO" => "CORDEIRO",
            "CORRE I" => "CORREI",
            "COUTIN HO" => "COUTINHO",
            "CRISTI NA" => "CRISTINA",
            "DAMIA O" => "DAMIAO",
            "DANIEL L" => "DANIEL",
            "DANTA S" => "DANTAS",
            "DANUBI A" => "DANUBIA",
            "DEODA TO" => "DEODATO",
            "DIONIZI O" => "DIONIZIO",
            "DJALD O" => "DJALDO",
            "DOMIN GOS" => "DOMINGOS",
            "DONAT O" => "DONATO",
            "DOUGL AS" => "DOUGLAS",
            "EDUAR DO" => "EDUARDO",
            "EDUARD O" => "EDUARDO",
            "EDVAL DO" => "EDVALDO",
            "EMIDI O" => "EMIDIO",
            "EUGENI O" => "EUGENIO",
            "EZEQUI EL" => "EZEQUIEL",
            "FALCA O" => "FALCAO",
            "FAUST O" => "FAUSTO",
            "FERNA NDES" => "FERNANDES",
            "FERNA NDO" => "FERNANDO",
            "FERREI RA" => "FERREIRA",
            "FIRMIN O" => "FIRMINO",
            "FRAGO SO" => "FRAGOSO",
            "GABRIE L" => "GABRIEL",
            "GABRIE LLA" => "GABRIELLA",
            "GALDIN O" => "GALDINO",
            "GEORG E" => "GEORGE",
            "GERAL DO" => "GERALDO",
            "GERLIA NO" => "GERLIANO",
            "GILVA N" => "GILVAN",
            "GILVAN ECE" => "GILVANECE",
            "GIRLEN E" => "GIRLENE",
            "GRACA S" => "GRACAS",
            "GUEDE S" => "GUEDES",
            "HAMILT ON" => "HAMILTON",
            "HELEN A" => "HELENA",
            "HENRIQ UE" => "HENRIQUE",
            "HERCIL IO" => "HERCILIO",
            "HOLAN DA" => "HOLANDA",
            "HONOR IO" => "HONORIO",
            "HORACI O" => "HORACIO",
            "HUMBE RTO" => "HUMBERTO",
            "IDALIN O" => "IDALINO",
            "IVANILD O" => "IVANILDO",
            "IVONE TE" => "IVONETE",
            "IVONET E" => "IVONETE",
            "JAILTO N" => "JAILTON",
            "JOAQUI M" => "JOAQUIM",
            "JOELS ON" => "JOELSON",
            "JOSEA NE" => "JOSEANE",
            "JOSEF A" => "JOSEFA",
            "JOSINE TE" => "JOSINETE",
            "JOSIVA NIO" => "JOSIVANIO",
            "JOZILE NE" => "JOZILENE",
            "JULIAN A" => "JULIANA",
            "JURAC Y" => "JURACY",
            "JUSTIN O" => "JUSTINO",
            "KERSE VANI" => "KERSEVANI",
            "KETHY L" => "KETHYL",
            "LEONI A" => "LEONIA",
            "LINALD O" => "LINALDO",
            "LOURD ES" => "LOURDES",
            "LOURE NCO" => "LOURENCO",
            "LUCIAN O" => "LUCIANO",
            "LUCIEN E" => "LUCIENE",
            "MACED O" => "MACEDO",
            "MANOE L" => "MANOEL",
            "MARCE LO" => "MARCELO",
            "MARCI O" => "MARCIO",
            "MARCIA NA" => "MARCIANA",
            "MARCO LINO" => "MARCOLINO",
            "MARCO S" => "MARCOS",
            "MARIAN O" => "MARIANO",
            "MARILE NE" => "MARILENE",
            "MARILU ZE" => "MARILUZE",
            "MARIN HO" => "MARINHO",
            "MARQU ES" => "MARQUES",
            "MARTIN S" => "MARTINS",
            "MAXSU EL" => "MAXSUEL",
            "MAYCO N" => "MAYCON",
            "MEDEIR OS" => "MEDEIROS",
            "MEIREL ES" => "MEIRELES",
            "MENDE S" => "MENDES",
            "MENEZ ES" => "MENEZES",
            "MESSIA S" => "MESSIAS",
            "MICHEL LE" => "MICHELLE",
            "MONTA LVAN" => "MONTALVAN",
            "MONTEI RO" => "MONTEIRO",
            "MORAE S" => "MORAES",
            "NASCIM ENTO" => "NASCIMENTO",
            "NCELO S" => "NCELOS",
            "NEYLT ON" => "NEYLTON",
            "NIVAL DO" => "NIVALDO",
            "NOGUE IRA" => "NOGUEIRA",
            "OLIVEI RA" => "OLIVEIRA",
            "PACELL I" => "PACELLI",
            "PACHE CO" => "PACHECO",
            "PALMEI RA" => "PALMEIRA",
            "PASTO R" => "PASTOR",
            "PAULIN O" => "PAULINO",
            "PEREIR A" => "PEREIRA",
            "PIMENT EL" => "PIMENTEL",
            "PORFI RIO" => "PORFIRIO",
            "PORFIR IO" => "PORFIRIO",
            "QUEIR OZ" => "QUEIROZ",
            "QUINTI NO" => "QUINTINO",
            "QUIRIN O" => "QUIRINO",
            "QUITER IA" => "QUITERIA",
            "RIBEIR O" => "RIBEIRO",
            "RICARD O" => "RICARDO",
            "ROBER TA" => "ROBERTA",
            "ROBSO N" => "ROBSON",
            "RODRI GUES" => "RODRIGUES",
            "ROLLE MBERG" => "ROLLEMBERG",
            "ROSEA NE" => "ROSEANE",
            "ROSIET H" => "ROSIETH",
            "ROSINE IDE" => "ROSINEIDE",
            "SANDR O" => "SANDRO",
            "SANTA NA" => "SANTANA",
            "SANTO S" => "SANTOS",
            "SARAD A" => "SARADA",
            "SEBAS TIAO" => "SEBASTIAO",
            "SERGI O" => "SERGIO",
            "SEVERI NA" => "SEVERINA",
            "SEVERI NO" => "SEVERINO",
            "SILVIN O" => "SILVINO",
            "SOARE S" => "SOARES",
            "SOCOR RO" => "SOCORRO",
            "SOLAN GE" => "SOLANGE",
            "SORIAN O" => "SORIANO",
            "TENOR IO" => "TENORIO",
            "TENORI O" => "TENORIO",
            "TIBURC IO" => "TIBURCIO",
            "TORRE S" => "TORRES",
            "VALERI A" => "VALERIA",
            "VALTE R" => "VALTER",
            "VASCO NCELO S" => "VASCONCELOS",
            "VASCO NCELOS" => "VASCONCELOS",
            "VERUS CA" => "VERUSCA",
            "VIEIR A" => "VIEIRA",
            "VITORI NO" => "VITORINO",
            "WAGNE R" => "WAGNER",
            "WELLIT ON" => "WELLITON",
            "ZENILD A" => "ZENILDA"
        ];
        
        foreach ($dbPeritos as $p) {
            $cleaned = $p;
            foreach ($replacements as $split => $merge) {
                $cleaned = str_replace($split, $merge, $cleaned);
            }
            $cleaned = preg_replace('/\s+/', ' ', trim($cleaned));
            
            if ($cleaned !== $p) {
                $updateStmt = $pdo->prepare("UPDATE atendimentos SET perito = ? WHERE perito = ?");
                $updateStmt->execute([$cleaned, $p]);
                
                $updateStmt2 = $pdo->prepare("UPDATE salas_peritos SET perito = ? WHERE perito = ?");
                $updateStmt2->execute([$cleaned, $p]);
            }
        }
    } catch (Exception $e) {}

    // DEDUPLICAÇÃO DE PERITOS: mescla registros duplicados com grafias incorretas (ex: "EDUARD O")
    try {
        $stmt = $pdo->query("SELECT DISTINCT perito FROM atendimentos WHERE perito IS NOT NULL AND perito != ''");
        $allPeritos = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $groups = [];
        
        // Agrupa os nomes pelo seu equivalente simplificado (sem espaços e acentos)
        foreach ($allPeritos as $p) {
            $simplified = simplifyName($p);
            if (!isset($groups[$simplified])) {
                $groups[$simplified] = [];
            }
            $groups[$simplified][] = $p;
        }
        
        // Verifica se algum grupo possui mais de uma grafia para o mesmo nome
        foreach ($groups as $simplified => $names) {
            if (count($names) > 1) {
                // Encontra a melhor versão da grafia (aquela com o menor número de espaços duplicados/cortados)
                $cleanest = $names[0];
                foreach ($names as $n) {
                    if (substr_count($n, ' ') < substr_count($cleanest, ' ')) {
                        $cleanest = $n;
                    }
                }
                
                // Mescla os atendimentos para a melhor grafia
                $placeholders = implode(',', array_fill(0, count($names), '?'));
                $updateStmt = $pdo->prepare("UPDATE atendimentos SET perito = ? WHERE perito IN ($placeholders)");
                $params = array_merge([$cleanest], $names);
                $updateStmt->execute($params);
            }
        }
    } catch (Exception $e) {}

} catch (PDOException $e) {
    die("Erro de conexão: " . $e->getMessage());
}

/**
 * Função Auxiliar Global: simplifyName
 * 
 * Transforma uma string removendo espaços, acentos, pontuação e convertendo para minúsculas.
 * Usada para comparar e agrupar nomes de forma idêntica e sem ruídos de formatação.
 *
 * @param string $name Nome a ser simplificado.
 * @return string Nome normalizado.
 */
function simplifyName($name) {
    $name = mb_strtolower($name, 'UTF-8');
    // Mapeamento de caracteres acentuados para suas versões limpas
    $utf8 = [
        '/[áàâãä]/u'   =>   'a',
        '/[éèêë]/u'    =>   'e',
        '/[íìîï]/u'    =>   'i',
        '/[óòôõö]/u'   =>   'o',
        '/[úùûü]/u'    =>   'u',
        '/[ç]/u'       =>   'c',
        '/[ñ]/u'       =>   'n',
    ];
    $name = preg_replace(array_keys($utf8), array_values($utf8), $name);
    // Remove todos os caracteres não-alfanuméricos e espaços
    $name = preg_replace('/[^a-z0-9]/', '', $name);
    return $name;
}

/**
 * Simplifica uma string mantendo os espaços para separar as palavras.
 */
function simplifyNameWithSpaces($name) {
    $name = mb_strtolower($name, 'UTF-8');
    $utf8 = [
        '/[áàâãä]/u'   =>   'a',
        '/[éèêë]/u'    =>   'e',
        '/[íìîï]/u'    =>   'i',
        '/[óòôõö]/u'   =>   'o',
        '/[úùûü]/u'    =>   'u',
        '/[ç]/u'       =>   'c',
        '/[ñ]/u'       =>   'n',
    ];
    $name = preg_replace(array_keys($utf8), array_values($utf8), $name);
    // Substitui caracteres não-alfanuméricos e espaços extras por um único espaço
    $name = preg_replace('/[^a-z0-9\s]/', '', $name);
    $name = preg_replace('/\s+/', ' ', trim($name));
    return $name;
}

/**
 * Verifica se um nome parcial (como "Eduardo Barbosa") é correspondente
 * a um nome completo (como "EDUARDO LIMA BARBOSA").
 */
function matchPartialName($partial, $full) {
    if (!$partial || !$full) return false;
    
    $pClean = simplifyNameWithSpaces($partial);
    $fClean = simplifyNameWithSpaces($full);
    
    if ($pClean === $fClean) {
        return true;
    }
    
    $pWords = explode(' ', $pClean);
    $fWords = explode(' ', $fClean);
    
    // Verifica se todas as palavras do nome parcial existem no nome completo, na mesma ordem
    $fIdx = 0;
    $matchedCount = 0;
    foreach ($pWords as $pWord) {
        while ($fIdx < count($fWords)) {
            if ($fWords[$fIdx] === $pWord) {
                $matchedCount++;
                $fIdx++;
                break;
            }
            $fIdx++;
        }
    }
    
    return $matchedCount === count($pWords);
}

/**
 * Consulta a frequência de um nome na API do IBGE, com cache local no banco de dados.
 *
 * @param string $word Palavra/nome a ser consultado.
 * @return int Frequência total do nome (0 se não for nome válido, -1 se falhar).
 */
function checkNomeIBGE($word) {
    global $pdo;
    
    static $apiOffline = false;
    static $localCache = null;
    
    if ($apiOffline || (isset($GLOBALS['sga_import_lote']) && $GLOBALS['sga_import_lote'] === true)) {
        return -1;
    }
    
    $word = mb_strtoupper(trim($word), 'UTF-8');
    
    // Remove acentos e caracteres não-alfabéticos para a consulta
    $utf8 = [
        '/[ÁÀÂÃÄ]/u'   =>   'A',
        '/[ÉÈÊË]/u'    =>   'E',
        '/[ÍÌÎÏ]/u'    =>   'I',
        '/[ÓÒÔÕÖ]/u'   =>   'O',
        '/[ÚÙÛÜ]/u'    =>   'U',
        '/[Ç]/u'       =>   'C',
        '/[Ñ]/u'       =>   'N',
    ];
    $cleanWord = preg_replace(array_keys($utf8), array_values($utf8), $word);
    $cleanWord = preg_replace('/[^A-Z]/', '', $cleanWord);
    
    if (strlen($cleanWord) < 2) {
        return 0;
    }
    
    // Inicializa o cache local em memória na primeira execução (lê tudo de uma vez do banco)
    if ($localCache === null) {
        $localCache = [];
        try {
            $stmt = $pdo->query("SELECT nome, freq FROM nomes_validados");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $localCache[$row['nome']] = (int)$row['freq'];
            }
        } catch (PDOException $e) {
            // Ignora erros de inicialização de cache
        }
    }
    
    // 1. Consulta o cache local na memória do PHP
    if (isset($localCache[$cleanWord])) {
        return $localCache[$cleanWord];
    }
    
    // 2. Consulta a API do IBGE
    $url = "https://servicodados.ibge.gov.br/api/v2/censos/nomes/" . urlencode($cleanWord);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1); // 1 segundo para conectar
    curl_setopt($ch, CURLOPT_TIMEOUT, 2); // 2 segundos de timeout total
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Evita falhas de certificado localmente
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_errno($ch);
    curl_close($ch);
    
    if ($curlError !== 0 || $httpCode === 0 || $httpCode >= 500) {
        $apiOffline = true;
        return -1;
    }
    
    $freq = 0;
    $success = false;
    
    if ($httpCode === 200 && $response) {
        $data = json_decode($response, true);
        $success = true;
        if (is_array($data) && !empty($data)) {
            // Soma as frequências de todos os períodos
            if (isset($data[0]['res']) && is_array($data[0]['res'])) {
                foreach ($data[0]['res'] as $periodo) {
                    $freq += (int)($periodo['frequencia'] ?? 0);
                }
            }
        }
    } elseif ($httpCode === 404) {
        // Nome não existente na base do IBGE
        $freq = 0;
        $success = true;
    }
    
    // 3. Salva no cache se a consulta foi bem sucedida
    if ($success) {
        try {
            $stmt = $pdo->prepare("INSERT INTO nomes_validados (nome, freq) VALUES (?, ?) ON DUPLICATE KEY UPDATE freq = ?");
            $stmt->execute([$cleanWord, $freq, $freq]);
            $localCache[$cleanWord] = $freq; // Atualiza o cache em memória
        } catch (PDOException $e) {
            // Ignora erros de cache
        }
        return $freq;
    }
    
    return -1; // Indica que a API/rede falhou
}

/**
 * Tenta encontrar um perito cadastrado no sistema que corresponda ao nome importado.
 * Suporta correspondência parcial, tolerância a acentos, caixa alta e pequenos erros de grafia (Levenshtein).
 *
 * @param string $importedName Nome do perito extraído do PDF.
 * @return string O nome oficial cadastrado no banco, ou o próprio nome importado se não houver correspondência.
 */
function associarPeritoCadastrado($importedName) {
    global $pdo;
    if (!$importedName) return $importedName;
    
    // Busca todos os peritos cadastrados com nome completo preenchido (Cache estático na memória)
    static $peritos = null;
    if ($peritos === null) {
        try {
            $stmt = $pdo->query("SELECT nome FROM usuarios WHERE role = 'perito' AND nome IS NOT NULL AND nome != ''");
            $peritos = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            $peritos = [];
        }
    }
    
    if (empty($peritos)) {
        return $importedName;
    }
    
    $importedClean = simplifyNameWithSpaces($importedName);
    $importedWords = explode(' ', $importedClean);
    
    // 1. Tenta correspondência exata ou parcial direta (ex: "Eduardo Barbosa" -> "EDUARDO LIMA BARBOSA")
    foreach ($peritos as $pNome) {
        if (matchPartialName($importedName, $pNome) || matchPartialName($pNome, $importedName)) {
            return $pNome;
        }
    }
    
    // 2. Se não encontrou, tenta correspondência fuzzy por Levenshtein palavra a palavra
    // Isso ajuda a resolver erros de digitação como "GEROG E" -> "GEROGE" -> "GEORGE"
    foreach ($peritos as $pNome) {
        $pClean = simplifyNameWithSpaces($pNome);
        $pWords = explode(' ', $pClean);
        
        $matchedWords = 0;
        foreach ($importedWords as $impWord) {
            if (strlen($impWord) < 3) continue; // Ignora conectores muito curtos
            
            foreach ($pWords as $pWord) {
                if (strlen($pWord) < 3) continue;
                
                // Se a palavra for idêntica ou muito semelhante (Levenshtein <= 2)
                if ($impWord === $pWord || levenshtein($impWord, $pWord) <= 2) {
                    $matchedWords++;
                    break;
                }
            }
        }
        
        // Se conseguimos associar pelo menos a maior parte das palavras significativas do nome importado,
        // consideramos uma associação bem-sucedida.
        $significantWordsCount = count(array_filter($importedWords, function($w) { return strlen($w) >= 3; }));
        if ($significantWordsCount > 0 && $matchedWords >= max(1, $significantWordsCount - 1)) {
            return $pNome;
        }
    }
    
    return $importedName;
}

/**
 * Corrige de forma inteligente nomes que foram partidos por sílabas/espaços
 * na conversão do PDF (ex: "SEVERI NA" -> "SEVERINA", "MARIN HO" -> "MARINHO", "LUCIEN E" -> "LUCIENE").
 * Integra chamadas à API do IBGE com cache local e heurísticas baseadas em tamanho de palavra.
 *
 * @param string $name Nome completo bruto.
 * @return string Nome corrigido.
 */
function corrigirNomeInteligente($name) {
    if (!$name) return '';
    $name = mb_strtoupper(trim($name), 'UTF-8');
    $name = preg_replace('/\s+/', ' ', $name);
    $parts = explode(' ', $name);
    if (count($parts) <= 1) {
        return $name;
    }
    
    // Lista de palavras consagradas da língua e nomes comuns completos que não devem ser mesclados
    $palavras_completas = [
        'DE', 'DO', 'DA', 'DOS', 'DAS', 'E', 'EM', 'ANA', 'ANNA', 'MARIA', 'JOSE', 'JOAO', 
        'LUIZ', 'LUIS', 'CARLOS', 'ANTONIO', 'FRANCISCO', 'PAULO', 'PEDRO', 'LIMA', 'SILVA', 
        'SOUZA', 'SOUSA', 'ALVES', 'RODRIGUES', 'GOMES', 'SANTOS', 'OLIVEIRA', 'COSTA', 
        'PEREIRA', 'MELO', 'MELLO', 'BARBOSA', 'FERREIRA', 'NASCIMENTO', 'FERNANDO', 
        'ROBERTO', 'MARCOS', 'PAULA', 'ALEXANDRE', 'EDUARDO', 'RICARDO', 'CLAUDIO', 'VALDEIR',
        'VILMA', 'CARLA', 'ELIANE', 'REGINA', 'SANDRA', 'CRISTINA', 'PATRICIA', 'ADRIANA'
    ];
    
    $i = 1;
    while ($i < count($parts)) {
        $prev = $parts[$i - 1];
        $curr = $parts[$i];
        
        if ($prev === '' || $curr === '') {
            $i++;
            continue;
        }
        
        $deve_mesclar = false;
        
        // 1. Tenta consulta inteligente via IBGE API com cache local
        $merged = $prev . $curr;
        $freqPrev = checkNomeIBGE($prev);
        $freqCurr = checkNomeIBGE($curr);
        $freqMerged = checkNomeIBGE($merged);
        
        if ($freqMerged > 0 && $freqPrev !== -1 && $freqCurr !== -1 && $freqMerged !== -1) {
            if ($freqPrev === 0 || $freqCurr === 0) {
                // Se um dos fragmentos sozinhos não for um nome válido, mas juntos formam, mescla!
                $deve_mesclar = true;
            } elseif (mb_strlen($curr) <= 2 && $freqMerged > $freqPrev) {
                // Se a parte atual for curta e a frequência da versão unida for maior, mescla!
                $deve_mesclar = true;
            } elseif ($freqMerged > $freqPrev * 2 && $freqMerged > $freqCurr * 2) {
                // Se a frequência da versão unida for consideravelmente maior, mescla!
                $deve_mesclar = true;
            }
        }
        
        // 2. Se a API falhou (offline) ou não houve decisão conclusiva pela frequência, usa a heurística local por tamanho
        if (!$deve_mesclar) {
            // Caso 1: A parte atual é de 1 letra (ex: 'E' em 'LUCIEN E')
            if (mb_strlen($curr) === 1 && !in_array($prev, $palavras_completas)) {
                $deve_mesclar = true;
            }
            // Caso 2: A parte atual é de 2 letras (ex: 'HO' em 'MARIN HO', 'NA' em 'SEVERI NA')
            // E a parte anterior não está na lista de palavras completas consagradas
            elseif (mb_strlen($curr) === 2 && !in_array($prev, $palavras_completas)) {
                $deve_mesclar = true;
            }
        }
        
        if ($deve_mesclar) {
            $parts[$i - 1] = $prev . $curr;
            array_splice($parts, $i, 1);
        } else {
            $i++;
        }
    }
    
    return implode(' ', $parts);
}

/**
 * Função para registrar ações no log de auditoria
 */
function registrarLog($pdo, $acao, $detalhes, $atendimentoId = null) {
    $userId = isset($_SESSION['usuario_id']) ? $_SESSION['usuario_id'] : null;
    $userName = isset($_SESSION['usuario_name']) ? $_SESSION['usuario_name'] : 'sistema';
    try {
        $stmt = $pdo->prepare("INSERT INTO logs_auditoria (usuario_id, usuario_nome, acao, detalhes, atendimento_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $userName, $acao, $detalhes, $atendimentoId]);
    } catch (PDOException $e) {
        // Ignora falhas silenciosamente para não travar a aplicação principal
    }
}

/**
 * Verifica se a pauta de uma determinada data está liberada
 */
function isPautaLiberada($pdo, $data) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM pautas_liberadas WHERE data_pauta = ?");
        $stmt->execute([$data]);
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Libera a pauta de uma determinada data
 */
function liberarPauta($pdo, $data, $usuarioId) {
    try {
        $stmt = $pdo->prepare("INSERT IGNORE INTO pautas_liberadas (data_pauta, usuario_id) VALUES (?, ?)");
        return $stmt->execute([$data, $usuarioId]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Bloqueia a pauta de uma determinada data (revoga liberação)
 */
function bloquearPauta($pdo, $data) {
    try {
        $stmt = $pdo->prepare("DELETE FROM pautas_liberadas WHERE data_pauta = ?");
        return $stmt->execute([$data]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Recupera um valor de configuração global do banco
 */
function get_config($pdo, $chave, $padrao = '') {
    try {
        $stmt = $pdo->prepare("SELECT valor FROM configuracoes WHERE chave = ?");
        $stmt->execute([$chave]);
        $val = $stmt->fetchColumn();
        return $val !== false ? $val : $padrao;
    } catch (Exception $e) {
        return $padrao;
    }
}

/**
 * Define ou atualiza um valor de configuração global no banco
 */
function set_config($pdo, $chave, $valor) {
    try {
        $stmt = $pdo->prepare("INSERT INTO configuracoes (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = ?");
        return $stmt->execute([$chave, $valor, $valor]);
    } catch (Exception $e) {
        return false;
    }
}

