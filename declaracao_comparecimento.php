<?php
declare(strict_types=1);

require_once __DIR__ . '/banco.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    die('Identificador de atendimento inválido.');
}

$stmt = $pdo->prepare("SELECT * FROM atendimentos WHERE id = ?");
$stmt->execute([$id]);
$atendimento = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$atendimento) {
    http_response_code(404);
    die('Atendimento não encontrado.');
}

// Perito só acessa seus próprios atendimentos (ou geral se admin/supervisor/recepcao)
if ($_SESSION['usuario_role'] === 'perito') {
    $peritoNomeLogado = (string)($_SESSION['usuario_name'] ?? '');
    $peritoIdLogado = (int)($_SESSION['usuario_id'] ?? 0);
    $peritoAtendimento = (string)($atendimento['perito'] ?? '');
    $peritoIdAtendimento = !empty($atendimento['perito_id']) ? (int)$atendimento['perito_id'] : null;

    if ($peritoIdAtendimento !== null && $peritoIdAtendimento !== $peritoIdLogado) {
        if (simplifyNameWithSpaces($peritoAtendimento) !== simplifyNameWithSpaces($peritoNomeLogado)) {
            http_response_code(403);
            die('Acesso não autorizado a este atendimento.');
        }
    }
}

$nome = trim((string)($atendimento['nome'] ?? ''));
if ($nome === '') {
    $nome = 'Não informado';
}

$cpf = trim((string)($atendimento['cpf'] ?? ''));
if ($cpf !== '') {
    $cpfLimpo = preg_replace('/\D/', '', $cpf);
    if (strlen($cpfLimpo) === 11) {
        $cpfFormatado = preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $cpfLimpo);
    } else {
        $cpfFormatado = $cpf;
    }
} else {
    $cpfFormatado = 'Não informado';
}

$processo = trim((string)($atendimento['processo'] ?? ''));
if ($processo === '') {
    $processo = 'Processo não informado';
}

$perito = trim((string)($atendimento['perito'] ?? ''));
if ($perito === '') {
    $perito = 'Perito Judicial Designado';
}

$dataPauta = !empty($atendimento['data_pauta']) ? $atendimento['data_pauta'] : date('Y-m-d');
$dataFormatada = date('d/m/Y', strtotime($dataPauta));

// Horários
$horaChegada = !empty($atendimento['chegada_em']) ? date('H:i', strtotime($atendimento['chegada_em'])) : null;
$horaInicio = !empty($atendimento['iniciado_em']) ? date('H:i', strtotime($atendimento['iniciado_em'])) : null;
$horaFim = !empty($atendimento['finalizado_em']) ? date('H:i', strtotime($atendimento['finalizado_em'])) : null;

// Se ainda não finalizou, considera a hora atual como término da permanência
$horaSaida = $horaFim ?: date('H:i');
$horaEntrada = $horaChegada ?: ($horaInicio ?: '08:00');

// Mês por extenso em português
$meses = [
    1 => 'janeiro', 2 => 'fevereiro', 3 => 'março', 4 => 'abril',
    5 => 'maio', 6 => 'junho', 7 => 'julho', 8 => 'agosto',
    9 => 'setembro', 10 => 'outubro', 11 => 'novembro', 12 => 'dezembro'
];
$diaAtual = (int)date('d');
$mesAtual = $meses[(int)date('m')] ?? date('F');
$anoAtual = date('Y');

// Hash de autenticidade documental SGA
$hashAutenticidade = 'JFAL-' . strtoupper(substr(hash('sha256', $atendimento['id'] . $dataPauta . 'SGA_JFAL_2026'), 0, 10));
$emitidoEm = date('d/m/Y \à\s H:i:s');
$usuarioEmissor = (string)($_SESSION['usuario_name'] ?? 'Servidor Responsável');
$autoPrint = isset($_GET['print']) && $_GET['print'] === '1';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Declaração de Comparecimento — <?= htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="icon" type="image/png" href="assets/catavento-jfal.png">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Times New Roman', Times, Georgia, serif;
            color: #111;
            background: #e2e8f0;
            padding: 24px;
            font-size: 12pt;
            line-height: 1.5;
        }
        .folha {
            background: #fff;
            width: 210mm;
            min-height: 285mm;
            max-height: 297mm;
            margin: 0 auto;
            padding: 18mm 20mm 15mm 20mm;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            page-break-inside: avoid;
        }
        .barra-acoes {
            max-width: 210mm;
            margin: 0 auto 16px auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            font-size: 13px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            border: none;
            transition: opacity 0.2s;
        }
        .btn:hover { opacity: 0.9; }
        .btn-imprimir { background: #1e3a8a; color: #fff; }
        .btn-fechar { background: #475569; color: #fff; }
        
        /* Cabeçalho */
        .cabecalho {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 8px;
            margin-bottom: 18px;
        }
        .cabecalho img {
            height: 52px;
            width: auto;
            margin-bottom: 6px;
        }
        .cabecalho h1 {
            font-size: 12pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }
        .cabecalho h2 {
            font-size: 10.5pt;
            font-weight: normal;
            text-transform: uppercase;
            margin-bottom: 2px;
        }
        .cabecalho h3 {
            font-size: 9.5pt;
            font-weight: normal;
            text-transform: uppercase;
            color: #333;
        }

        /* Título do Documento */
        .titulo-doc {
            text-align: center;
            font-size: 14pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 20px 0 20px 0;
            text-decoration: underline;
        }

        /* Corpo do Texto */
        .corpo {
            text-align: justify;
            text-indent: 40px;
            margin-bottom: 16px;
            font-size: 12pt;
            line-height: 1.55;
        }
        .destaque {
            font-weight: bold;
        }
        
        /* Quadro com Dados Resumidos */
        .resumo-dados {
            margin: 16px 0;
            border: 1px solid #444;
            border-radius: 4px;
            padding: 10px 14px;
            background: #fafafa;
            font-size: 10.5pt;
            line-height: 1.45;
        }
        .resumo-dados table {
            width: 100%;
            border-collapse: collapse;
        }
        .resumo-dados td {
            padding: 3px 6px;
            vertical-align: top;
        }
        .resumo-dados td.label {
            font-weight: bold;
            width: 32%;
            color: #222;
        }

        .nota-legal {
            font-size: 8.5pt;
            color: #555;
            font-style: italic;
            text-align: justify;
            margin-top: 14px;
            line-height: 1.35;
        }

        /* Data e Assinatura */
        .fechamento {
            margin-top: 22px;
            text-align: right;
            font-size: 11pt;
        }
        .bloco-assinaturas {
            margin-top: 32px;
            display: flex;
            justify-content: space-around;
            text-align: center;
        }
        .linha-assinatura {
            width: 250px;
            border-top: 1px solid #000;
            padding-top: 4px;
            font-size: 10pt;
        }
        .linha-assinatura .cargo {
            font-size: 8.5pt;
            color: #444;
        }

        /* Rodapé de Autenticidade */
        .rodape {
            border-top: 1px solid #999;
            padding-top: 6px;
            margin-top: 20px;
            font-size: 8pt;
            color: #444;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            page-break-inside: avoid;
        }

        @media print {
            html, body {
                background: #fff;
                padding: 0;
                margin: 0;
                height: 100%;
                overflow: hidden;
            }
            .barra-acoes, .no-print {
                display: none !important;
            }
            .folha {
                box-shadow: none;
                margin: 0;
                width: 100%;
                min-height: 100%;
                height: 100%;
                max-height: 297mm;
                padding: 10mm 16mm 10mm 16mm;
                page-break-inside: avoid;
                page-break-after: avoid;
                justify-content: space-between;
            }
            @page {
                size: A4 portrait;
                margin: 0;
            }
        }
    </style>
</head>
<body>

    <div class="barra-acoes no-print">
        <a href="javascript:window.close();" class="btn btn-fechar">← Fechar</a>
        <button onclick="window.print();" class="btn btn-imprimir">🖨️ Imprimir Declaração (A4)</button>
    </div>

    <div class="folha">
        <div>
            <!-- Cabeçalho Oficial -->
            <div class="cabecalho">
                <img src="assets/catavento-jfal.png" alt="Brasão / Logo JFAL">
                <h1>Poder Judiciário</h1>
                <h2>Justiça Federal de Primeiro Grau em Alagoas</h2>
                <h3>Seção Judiciária de Alagoas — Subseção Judiciária de Maceió</h3>
                <h3>Setor de Perícias Médicas Judiciais</h3>
            </div>

            <!-- Título -->
            <div class="titulo-doc">
                Declaração de Comparecimento
            </div>

            <!-- Texto Principal -->
            <div class="corpo">
                Declara-se, para os devidos fins de comprovação de comparecimento junto a empregador, instituição de ensino ou autoridade competente, que o(a) Sr.(a) <span class="destaque"><?= htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') ?></span>, inscrito(a) no CPF sob o nº <span class="destaque"><?= htmlspecialchars($cpfFormatado, ENT_QUOTES, 'UTF-8') ?></span>, compareceu a esta sede da Justiça Federal em Alagoas no dia <span class="destaque"><?= $dataFormatada ?></span>, tendo permanecido nas dependências deste órgão no período das <span class="destaque"><?= $horaEntrada ?>h</span> às <span class="destaque"><?= $horaSaida ?>h</span>, para realização de ato pericial médico judicial.
            </div>

            <!-- Detalhes do Ato -->
            <div class="resumo-dados">
                <table>
                    <tr>
                        <td class="label">Processo Judicial nº:</td>
                        <td><span class="destaque"><?= htmlspecialchars($processo, ENT_QUOTES, 'UTF-8') ?></span></td>
                    </tr>
                    <tr>
                        <td class="label">Perito(a) Responsável:</td>
                        <td><?= htmlspecialchars($perito, ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                    <tr>
                        <td class="label">Horário de Entrada:</td>
                        <td><?= $horaEntrada ?> horas</td>
                    </tr>
                    <tr>
                        <td class="label">Horário de Saída:</td>
                        <td><?= $horaSaida ?> horas</td>
                    </tr>
                    <tr>
                        <td class="label">Senha / Protocolo SGA:</td>
                        <td><?= htmlspecialchars((string)($atendimento['senha'] ?? '---'), ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                </table>
            </div>

            <div class="nota-legal">
                * A presente certidão limita-se exclusivamente a atestar a presença do periciado nesta Seção Judiciária para a prática de ato processual indispensável à instrução da ação judicial epigrafada, sendo vedada qualquer inferência sobre o resultado técnico do laudo pericial.
            </div>

            <!-- Fechamento com Data por Extenso -->
            <div class="fechamento">
                Maceió/AL, <?= sprintf('%02d de %s de %04d', $diaAtual, $mesAtual, $anoAtual) ?>.
            </div>

            <!-- Assinatura -->
            <div class="bloco-assinaturas">
                <div class="linha-assinatura">
                    <strong><?= htmlspecialchars($usuarioEmissor, ENT_QUOTES, 'UTF-8') ?></strong><br>
                    <span class="cargo">Servidor(a) / Atendimento SGA<br>Justiça Federal em Alagoas</span>
                </div>
                <div class="linha-assinatura">
                    <strong><?= htmlspecialchars($perito, ENT_QUOTES, 'UTF-8') ?></strong><br>
                    <span class="cargo">Perito(a) Judicial<br>Justiça Federal em Alagoas</span>
                </div>
            </div>
        </div>

        <!-- Rodapé de Validação -->
        <div class="rodape">
            <div>
                Av. Menino Marcelo, s/n - Serraria, Maceió - AL, CEP 57046-000 • Tel: (82) 2122-4100
            </div>
            <div>
                <strong>Autenticação SGA:</strong> <?= $hashAutenticidade ?> • Emitido em <?= $emitidoEm ?>
            </div>
        </div>
    </div>

    <?php if ($autoPrint): ?>
    <script>
        window.addEventListener('load', function() {
            window.print();
        });
    </script>
    <?php endif; ?>
</body>
</html>
