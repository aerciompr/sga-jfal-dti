import os
import base64
from playwright.sync_api import sync_playwright

output_dir = os.path.abspath(r'c:\sga-dti\docs\manuais')
img_dir = os.path.join(output_dir, 'imagens')
assets_dir = os.path.abspath(r'c:\sga-dti\assets')

# Load and encode images
def get_b64(path):
    if not os.path.exists(path):
        return ""
    with open(path, 'rb') as f:
        return "data:image/png;base64," + base64.b64encode(f.read()).decode('utf-8')

img_login = get_b64(os.path.join(img_dir, '01_login.png'))
img_recepcao = get_b64(os.path.join(img_dir, '02_recepcao_visao_geral.png'))
img_checkin = get_b64(os.path.join(img_dir, '03_confirmar_presenca.png'))
img_prioridade = get_b64(os.path.join(img_dir, '04_modal_prioridade_legal.png'))
img_declaracao = get_b64(os.path.join(img_dir, '05_declaracao_comparecimento.png'))
img_sala = get_b64(os.path.join(img_dir, '06_perito_selecao_sala.png'))
img_perito = get_b64(os.path.join(img_dir, '07_perito_painel_principal.png'))
img_toast = get_b64(os.path.join(img_dir, '09_perito_alerta_toast.png'))
img_dashboard = get_b64(os.path.join(img_dir, '11_dashboard_executivo.png'))
img_pauta = get_b64(os.path.join(img_dir, '13_gerenciamento_pauta.png'))
img_tv = get_b64(os.path.join(img_dir, '14_painel_tv_chamada.png'))
img_logo_jfal = get_b64(os.path.join(assets_dir, 'logo-jfal-completo.png'))
img_catavento = get_b64(os.path.join(assets_dir, 'catavento-jfal.png'))

css_style = """
<style>
    @page {
        size: A4 portrait;
        margin: 22mm 16mm 22mm 16mm;
    }
    * {
        box-sizing: border-box;
    }
    body {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        color: #1e293b;
        background-color: #ffffff;
        font-size: 11.5pt;
        line-height: 1.55;
        margin: 0;
        padding: 0;
    }
    
    /* Cover Page */
    .cover-page {
        height: 100vh;
        max-height: 100vh;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        page-break-after: always;
        padding: 15mm 10mm 15mm 10mm;
        box-sizing: border-box;
    }
    .cover-header {
        border-bottom: 3px solid #1e3a8a;
        padding-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 20px;
    }
    .cover-header-text h3 {
        margin: 0;
        font-size: 13pt;
        color: #0f172a;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .cover-header-text h4 {
        margin: 4px 0 0 0;
        font-size: 10pt;
        color: #475569;
        font-weight: 500;
        text-transform: uppercase;
    }
    .cover-body {
        margin: auto 0;
        text-align: left;
        padding: 40px 0;
    }
    .badge-manual {
        display: inline-block;
        background-color: #eff6ff;
        color: #1d4ed8;
        border: 1px solid #bfdbfe;
        font-size: 9pt;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1.5px;
        padding: 6px 14px;
        border-radius: 9999px;
        margin-bottom: 20px;
    }
    .cover-title {
        font-size: 28pt;
        font-weight: 900;
        color: #0f2942;
        line-height: 1.15;
        margin: 0 0 14px 0;
    }
    .cover-subtitle {
        font-size: 14pt;
        color: #334155;
        font-weight: 600;
        line-height: 1.4;
        margin: 0 0 24px 0;
    }
    .cover-desc {
        font-size: 11pt;
        color: #64748b;
        max-width: 90%;
        line-height: 1.6;
    }
    .cover-footer {
        border-top: 1px solid #e2e8f0;
        padding-top: 20px;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
        font-size: 9pt;
        color: #64748b;
    }
    .cover-footer strong {
        color: #1e293b;
    }
    
    /* Document Elements */
    .section-title {
        color: #0f2942;
        font-size: 18pt;
        font-weight: 800;
        border-bottom: 2px solid #e2e8f0;
        padding-bottom: 6px;
        margin-top: 26px;
        margin-bottom: 14px;
        page-break-after: avoid;
    }
    .subsection-title {
        color: #1d4ed8;
        font-size: 13.5pt;
        font-weight: 700;
        margin-top: 20px;
        margin-bottom: 10px;
        page-break-after: avoid;
    }
    .subsubsection-title {
        color: #334155;
        font-size: 11.5pt;
        font-weight: 700;
        margin-top: 14px;
        margin-bottom: 8px;
        page-break-after: avoid;
    }
    
    p {
        margin: 0 0 10px 0;
        text-align: justify;
    }
    
    ul, ol {
        margin: 0 0 12px 0;
        padding-left: 24px;
    }
    li {
        margin-bottom: 6px;
    }
    
    /* Callout Boxes */
    .callout {
        border-radius: 8px;
        padding: 12px 16px;
        margin: 14px 0;
        font-size: 10.5pt;
        page-break-inside: avoid;
    }
    .callout-info {
        background-color: #eff6ff;
        border-left: 4px solid #3b82f6;
        color: #1e3a8a;
    }
    .callout-tip {
        background-color: #f0fdf4;
        border-left: 4px solid #22c55e;
        color: #14532d;
    }
    .callout-warning {
        background-color: #fffbeb;
        border-left: 4px solid #f59e0b;
        color: #78350f;
    }
    .callout-priority {
        background-color: #fefce8;
        border-left: 4px solid #eab308;
        color: #713f12;
    }
    .callout-critical {
        background-color: #fef2f2;
        border-left: 4px solid #ef4444;
        color: #7f1d1d;
    }
    .callout strong {
        display: block;
        margin-bottom: 4px;
        font-size: 11pt;
    }
    
    /* Screenshots & Figures */
    .figure-container {
        margin: 18px 0;
        text-align: center;
        page-break-inside: avoid;
    }
    .figure-img {
        max-width: 98%;
        max-height: 480px;
        object-fit: contain;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.08);
    }
    .figure-caption {
        font-size: 9pt;
        color: #64748b;
        font-style: italic;
        margin-top: 6px;
        text-align: center;
    }
    
    /* Tables */
    table {
        width: 100%;
        border-collapse: collapse;
        margin: 14px 0;
        font-size: 10pt;
        page-break-inside: avoid;
    }
    th {
        background-color: #0f2942;
        color: #ffffff;
        font-weight: 700;
        text-align: left;
        padding: 8px 12px;
        border: 1px solid #0f2942;
    }
    td {
        padding: 8px 12px;
        border: 1px solid #e2e8f0;
        vertical-align: top;
    }
    tr:nth-child(even) td {
        background-color: #f8fafc;
    }
    
    /* Table of contents */
    .toc {
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 16px 20px;
        margin-bottom: 24px;
        page-break-inside: avoid;
    }
    .toc-title {
        font-size: 13pt;
        font-weight: 800;
        color: #0f2942;
        margin-bottom: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .toc-item {
        display: flex;
        justify-content: space-between;
        margin-bottom: 6px;
        font-size: 10.5pt;
        color: #334155;
    }
    .toc-item span:first-child {
        font-weight: 600;
    }
    
    .page-break {
        page-break-before: always;
    }
    
    .step-badge {
        display: inline-block;
        background-color: #1d4ed8;
        color: white;
        font-weight: bold;
        font-size: 9pt;
        padding: 2px 8px;
        border-radius: 4px;
        margin-right: 6px;
    }
    
    .badge-code {
        font-family: monospace;
        background-color: #f1f5f9;
        color: #0f172a;
        padding: 1px 5px;
        border-radius: 4px;
        font-size: 9.5pt;
        border: 1px solid #cbd5e1;
    }
</style>
"""

header_tpl = """
<div style="font-size: 8px; color: #94a3b8; font-family: -apple-system, sans-serif; width: 100%; display: flex; justify-content: space-between; padding: 0 16mm; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px;">
    <span>JUSTIÇA FEDERAL EM ALAGOAS — SGA v2.4</span>
    <span>SISTEMA DE GERENCIAMENTO DE ATENDIMENTO PERICIAL</span>
</div>
"""

footer_tpl = """
<div style="font-size: 8px; color: #94a3b8; font-family: -apple-system, sans-serif; width: 100%; display: flex; justify-content: space-between; padding: 0 16mm; border-top: 1px solid #e2e8f0; padding-top: 4px;">
    <span>Seção Judiciária de Alagoas — Documento de Uso Oficial</span>
    <span>Página <span class="pageNumber"></span> de <span class="totalPages"></span></span>
</div>
"""

# ==============================================================================
# 1. MANUAL DA RECEPÇÃO
# ==============================================================================
html_recepcao = f"""<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Manual do Usuário — Recepção e Triagem (SGA JFAL)</title>
    {css_style}
</head>
<body>

<!-- CAPA -->
<div class="cover-page">
    <div class="cover-header">
        <img src="{img_catavento}" alt="JFAL" style="height: 50px; width: auto;">
        <div class="cover-header-text">
            <h3>Poder Judiciário &bull; Justiça Federal</h3>
            <h4>Tribunal Regional Federal da 5ª Região &bull; Seção Judiciária de Alagoas</h4>
        </div>
    </div>
    
    <div class="cover-body">
        <div class="badge-manual">Manual do Usuário &bull; Módulo Operacional</div>
        <h1 class="cover-title">Recepção e Triagem de Perícias Judiciais</h1>
        <h2 class="cover-subtitle">Guia Prático Passo a Passo: Check-in, Emissão de Senhas, Prioridades Legais e Declarações</h2>
        <p class="cover-desc">
            Instruções completas para operação do balcão de atendimento, identificação de partes, aplicação das leis federais de acessibilidade e superprioridade (80+), acompanhamento do status dos consultórios médicos e orientação aos jurisdicionados.
        </p>
    </div>
    
    <div class="cover-footer">
        <div>
            <strong>Sistema:</strong> SGA JFAL (Versão 2.4)<br>
            <strong>Público-Alvo:</strong> Equipe de Recepção e Atendimento ao Cidadão<br>
            <strong>Área Responsável:</strong> Divisão de Tecnologia da Informação (DTI)
        </div>
        <div>
            <strong>Homologação:</strong> Coordenadoria dos Juizados Especiais Federais<br>
            <strong>Data da Edição:</strong> Novembro de 2026<br>
            <strong>Ambiente:</strong> Produção Institucional (TRF5 / JFAL)
        </div>
    </div>
</div>

<!-- SUMÁRIO -->
<div class="toc">
    <div class="toc-title">Sumário do Manual</div>
    <div class="toc-item"><span>1. Finalidade e Papel da Recepção</span><span>Pág. 2</span></div>
    <div class="toc-item"><span>2. Acesso ao Sistema e Autenticação</span><span>Pág. 2</span></div>
    <div class="toc-item"><span>3. Visão Geral da Interface e Monitoramento de Salas</span><span>Pág. 3</span></div>
    <div class="toc-item"><span>4. Procedimento Operacional: Check-in e Emissão de Senha</span><span>Pág. 4</span></div>
    <div class="toc-item"><span>5. Gestão de Prioridade Legal e Superprioridade (80+)</span><span>Pág. 5</span></div>
    <div class="toc-item"><span>6. Informando o Jurisdicionado: Posição na Fila e Tempo de Espera</span><span>Pág. 6</span></div>
    <div class="toc-item"><span>7. Emissão da Declaração Oficial de Comparecimento A4</span><span>Pág. 7</span></div>
    <div class="toc-item"><span>8. Resolução de Incidentes e Dúvidas Frequentes (FAQ)</span><span>Pág. 8</span></div>
    <div class="toc-item"><span>9. Checklist Operacional do Turno</span><span>Pág. 8</span></div>
</div>

<!-- SEÇÃO 1 -->
<h2 class="section-title">1. Finalidade e Papel da Recepção</h2>
<p>
    O módulo de <strong>Recepção e Triagem</strong> do Sistema de Gerenciamento de Atendimento Pericial (SGA) é a ferramenta fundamental para recepcionar com dignidade, celeridade e organização os jurisdicionados que comparecem à sede da Justiça Federal em Alagoas para realização de perícias médicas.
</p>
<p>
    O papel do operador de recepção transcende a simples emissão de senhas: é a porta de entrada que garante a correta aplicação das leis de prioridade, orienta o cidadão sobre o tempo de espera real e mantém os consultórios médicos continuamente alimentados com pontualidade.
</p>

<!-- SEÇÃO 2 -->
<h2 class="section-title">2. Acesso ao Sistema e Autenticação</h2>
<p>
    Para acessar o sistema em qualquer terminal da recepção:
</p>
<ol>
    <li>Abra o navegador de internet (Google Chrome, Microsoft Edge ou Mozilla Firefox).</li>
    <li>Digite no campo de endereço: <span class="badge-code">http://atendimento.jfal.jus.br</span>.</li>
    <li>Na tela de autenticação, digite seu <strong>Usuário</strong> e <strong>Senha</strong> de rede cadastrados.</li>
    <li>Selecione o perfil <strong>Recepção</strong> e clique no botão azul <strong>Entrar no Sistema</strong>.</li>
</ol>

<div class="figure-container">
    <img src="{img_login}" class="figure-img" alt="Tela de Autenticação">
    <div class="figure-caption">Figura 2.1 — Tela de Autenticação do SGA com identificação institucional da JFAL.</div>
</div>

<div class="callout callout-tip">
    <strong>💡 Dica de Acesso Rápido</strong>
    Adicione a página aos favoritos da barra de navegação do navegador clicando na estrela (Ctrl + D) para facilitar o acesso diário ao iniciar o expediente.
</div>

<!-- SEÇÃO 3 -->
<div class="page-break"></div>
<h2 class="section-title">3. Visão Geral da Interface e Monitoramento de Salas</h2>
<p>
    Ao ingressar no sistema, o operador visualiza o painel central da recepção, concebido com design ergonômico de alto contraste:
</p>

<div class="figure-container">
    <img src="{img_recepcao}" class="figure-img" alt="Visão Geral da Recepção">
    <div class="figure-caption">Figura 3.1 — Painel Principal da Recepção exibindo cards de salas ativas, filtros e lista de pauta.</div>
</div>

<h3 class="subsection-title">3.1. Entendendo os Cards dos Médicos Peritos</h3>
<p>
    No topo do painel, cada cartão retangular representa um consultório pericial ativo em tempo real. Observe atentamente as informações fornecidas em cada card:
</p>
<ul>
    <li><strong>Identificação do Médico e Sala:</strong> Nome do perito e o número da sala física (ex: <em>Dr. Carlos Eduardo Silveira &bull; Sala 02</em>).</li>
    <li><strong>Critério da Fila Escolhido pelo Perito:</strong>
        <ul>
            <li><span class="badge-code">🏃 Fila: Ordem de Chegada</span>: O médico está chamando os pacientes pela sequência exata em que deram entrada no balcão da recepção.</li>
            <li><span class="badge-code">🕒 Fila: Horário PJe</span>: O médico optou por organizar os presentes pelo horário oficial agendado na pauta do processo judicial.</li>
        </ul>
    </li>
    <li><strong>Status da Prioridade no Consultório:</strong>
        <ul>
            <li><span class="badge-code">♿ Fila com Prioridade Ativa</span>: O médico determinou que pacientes prioritários (60+/PCD) e superprioritários (80+) passem para o topo imediato da fila.</li>
            <li><span class="badge-code">⚪ Prioridade Opcional</span>: O médico está seguindo a ordem cronológica direta dos presentes.</li>
        </ul>
    </li>
    <li><strong>Volume de Espera:</strong> Informa quantas pessoas estão na fila daquele médico e quantas possuem prioridade (ex: <em>Fila: 3 | ⭐ 1 prioritário</em>).</li>
    <li><strong>Status da Sala em Tempo Real:</strong>
        <ul>
            <li><span class="badge-code">🟢 Em Exame: PE-01 (Nome)</span>: Perícia médica em andamento.</li>
            <li><span class="badge-code">🔔 Chamando: PE-02 (Nome)</span>: O perito acionou o chamado na TV da sala de espera.</li>
            <li><span class="badge-code">⚪ Sala livre</span>: O médico concluiu o exame anterior e a sala está disponível para a próxima chamada.</li>
        </ul>
    </li>
</ul>

<!-- SEÇÃO 4 -->
<div class="page-break"></div>
<h2 class="section-title">4. Procedimento Operacional: Check-in e Emissão de Senha</h2>
<p>
    Quando o cidadão se apresenta no balcão da recepção, siga rigorosamente o seguinte fluxo de atendimento:
</p>

<div class="figure-container">
    <img src="{img_checkin}" class="figure-img" alt="Confirmar Presença">
    <div class="figure-caption">Figura 4.1 — Janela de confirmação de presença com conferência de dados e geração de senha.</div>
</div>

<ol>
    <li>
        <strong>Solicitação do Documento:</strong> Solicite ao cidadão um documento oficial de identidade com foto (RG, CNH, CTPS ou Carteira Profissional) e o número do processo, caso possua.
    </li>
    <li>
        <strong>Busca Rápida na Pauta:</strong> Utilize o campo de busca no canto superior da tela digitando os primeiros dígitos do CPF ou o nome do periciado. A listagem filtra os dados instantaneamente.
    </li>
    <li>
        <strong>Conferência dos Dados:</strong> Verifique se a vara, o médico perito designado e o processo conferem com a intimação judicial apresentada.
    </li>
    <li>
        <strong>Confirmação de Chegada:</strong> Clique no botão verde <strong>✓ Confirmar Chegada</strong>.
    </li>
    <li>
        <strong>Geração de Senha e Horário:</strong> O sistema gera automaticamente a senha sequencial vinculada àquele médico (ex: <span class="badge-code">PE-03</span>) e crava o horário exato da chegada para cômputo dos tempos médios.
    </li>
    <li>
        <strong>Orientação ao Cidadão:</strong> Informe cordialmente a senha ao jurisdicionado:
        <br>
        <em>"Sr(a). José, sua presença foi confirmada sob a senha <strong>PE-03</strong> com o Dr. Carlos na Sala 02. Por favor, aguarde nos assentos da sala de espera que seu nome e senha serão anunciados na TV com sinal sonoro."</em>
    </li>
</ol>

<!-- SEÇÃO 5 -->
<div class="page-break"></div>
<h2 class="section-title">5. Gestão de Prioridade Legal e Superprioridade (80+)</h2>
<p>
    O atendimento prioritário no âmbito da Justiça Federal é regulamentado por normas federais de observância obrigatória. O SGA possui triagem especializada para garantir os direitos legais de cada faixa de cidadãos.
</p>

<div class="figure-container">
    <img src="{img_prioridade}" class="figure-img" alt="Modal de Classificação de Prioridade">
    <div class="figure-caption">Figura 5.1 — Modal de classificação de prioridade legal e superprioridade (80+).</div>
</div>

<h3 class="subsection-title">5.1. Categorias Previstas no SGA</h3>
<table>
    <thead>
        <tr>
            <th style="width: 25%;">Categoria</th>
            <th style="width: 40%;">Público Amparado / Requisitos</th>
            <th style="width: 35%;">Efeito Prático no Sistema</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td><strong>Normal</strong></td>
            <td>Jurisdicionados em geral que não se enquadram nas hipóteses legais de prioridade.</td>
            <td>Segue a ordem natural de chegada ou horário marcado.</td>
        </tr>
        <tr>
            <td><strong>Prioridade Legal</strong><br><span class="badge-code">Lei 10.048/00 e 10.741/03</span></td>
            <td>Pessoas idosas entre 60 e 79 anos, pessoas com deficiência (PCD), gestantes, lactantes, pessoas com crianças de colo e autistas.</td>
            <td>Recebe destaque em amarelo com selo <span class="badge-code">PRIORIDADE</span> e sobe na fila nos médicos com prioridade ativa.</td>
        </tr>
        <tr>
            <td><strong>Superprioridade</strong><br><span class="badge-code">Lei 13.466/2017 (80+)</span></td>
            <td>Pessoas idosas a partir de <strong>80 anos de idade</strong>.</td>
            <td>Recebe destaque dourado <span class="badge-code">⭐ SUPERPRIORIDADE (80+)</span> com preferência absoluta sobre as demais prioridades.</td>
        </tr>
    </tbody>
</table>

<div class="callout callout-warning">
    <strong>⚠️ Conferência Obrigatória de Idade na Recepção</strong>
    Ao receber um cidadão idoso, consulte a data de nascimento no documento oficial. Se tiver 80 anos ou mais, selecione obrigatoriamente a opção <strong>Superprioridade (80+)</strong> para cumprimento estrito da Lei Federal nº 13.466/2017.
</div>

<!-- SEÇÃO 6 -->
<h2 class="section-title">6. Informando o Jurisdicionado: Posição na Fila e Tempo de Espera</h2>
<p>
    Uma das demandas mais comuns no balcão de triagem é o pedido de informações sobre a demora para ser chamado. O SGA soluciona isso exibindo na tabela <strong>Aguardando Chamada</strong> a posição exata de cada paciente:
</p>
<ul>
    <li><span class="badge-code">1º na Fila</span>: O cidadão é o próximo a ser convocado assim que o perito desocupar o consultório.</li>
    <li><span class="badge-code">2º na Fila (1 na frente)</span>: Há apenas um paciente na frente deste periciado.</li>
    <li><span class="badge-code">3º na Fila (2 na frente)</span>: Há dois pacientes aguardando antes dele.</li>
</ul>
<p>
    O cálculo dessa posição leva em consideração a sala do médico perito designado e se o consultório está atendendo prioritários no momento, garantindo que o operador da recepção responda com segurança e precisão cirúrgica.
</p>

<!-- SEÇÃO 7 -->
<div class="page-break"></div>
<h2 class="section-title">7. Emissão da Declaração de Comparecimento A4 (Atribuição Central da Recepção)</h2>
<p>
    A emissão e entrega da <strong>Declaração de Comparecimento</strong> é atribuição primordial da equipe de Recepção. O documento comprova formalmente que a parte periciada compareceu ao Fórum Federal, servindo de justificativa funcional e trabalhista.
</p>

<div class="figure-container">
    <img src="{img_declaracao}" class="figure-img" alt="Declaração de Comparecimento A4">
    <div class="figure-caption">Figura 7.1 — Modelo oficial da Declaração de Comparecimento A4 formatada para impressão.</div>
</div>

<h3 class="subsection-title">7.1. Três Formas Rápidas de Emissão pela Recepção:</h3>
<ul>
    <li><strong>1. Ao Término do Exame (Fluxo Padrão):</strong> Após o perito concluir a perícia médica no consultório, o cidadão dirige-se ao balcão. Na lista lateral de <strong>Finalizados</strong>, a recepcionista visualiza o botão azul <span class="badge-code">🖨️ Declaração</span> ao lado do nome do paciente. O documento já abre com o horário de chegada e o horário exato de término registrado pelo perito.</li>
    <li><strong>2. Durante a Fila de Espera:</strong> Caso o cidadão precise comprovar que já se encontra nas dependências do Fórum aguardando a chamada, clique em <span class="badge-code">🖨️ Declaração</span> na coluna Ação da tabela de espera.</li>
    <li><strong>3. Pelo Modal de Detalhes:</strong> Clicando sobre a linha de qualquer agendamento, o modal exibe o botão <span class="badge-code">🖨️ Declaração de Comparecimento</span>.</li>
</ul>
<p>
    Basta pressionar o atalho <span class="badge-code">Ctrl + P</span> no teclado para enviar o documento à impressora do balcão.
</p>

<!-- SEÇÃO 8 -->
<div class="page-break"></div>
<h2 class="section-title">8. Resolução de Incidentes e Dúvidas Frequentes (FAQ)</h2>
<table>
    <thead>
        <tr>
            <th style="width: 30%;">Incidente / Situação</th>
            <th style="width: 70%;">Conduta Recomendada para a Recepção</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td><strong>Nome do cidadão não aparece na pauta</strong></td>
            <td>1. Confira se a data no topo da página está em "Hoje".<br>2. Verifique se o processo não está agendado para outra data ou outro fórum.<br>3. Contate o Supervisor do SGA ou a Secretaria da Vara para verificar se a pauta foi devidamente homologada.</td>
        </tr>
        <tr>
            <td><strong>Check-in realizado para a pessoa errada</strong></td>
            <td>Na linha do paciente na lista de espera, clique no botão de ações e selecione <strong>Desfazer Presença</strong>. A senha emitida será cancelada e o processo retornará à lista de "Aguardando Chegada".</td>
        </tr>
        <tr>
            <td><strong>O periciado precisa se ausentar do prédio</strong></td>
            <td>Oriente-o a permanecer na sala de espera. Caso o médico perito chame a senha na TV e o paciente não esteja presente, ele poderá ser colocado no final da tolerância de ausência.</td>
        </tr>
        <tr>
            <td><strong>A TV da sala de espera travou ou perdeu conexão</strong></td>
            <td>Pressione a tecla <strong>F5</strong> no computador que comanda a TV para recarregar o painel. Verifique se o cabo HDMI e a rede local estão firmes.</td>
        </tr>
    </tbody>
</table>

<!-- SEÇÃO 9 -->
<h2 class="section-title">9. Checklist Operacional do Turno da Recepção</h2>
<ul>
    <li>[ ] <strong>07:45 — Abertura dos Balcões:</strong> Ligar os computadores, abrir o navegador e fazer login com perfil de Recepção.</li>
    <li>[ ] <strong>07:50 — Ativação da TV:</strong> Abrir o Painel TV no monitor da sala de espera, colocar em tela cheia (F11) e checar o volume.</li>
    <li>[ ] <strong>08:00 — Conferência da Pauta:</strong> Confirmar se a pauta do dia está com o selo verde <span class="badge-code">Pauta Homologada</span>.</li>
    <li>[ ] <strong>Durante o Turno:</strong> Realizar os check-ins, identificar prioridades e emitir declarações de comparecimento.</li>
    <li>[ ] <strong>17:00 — Fechamento:</strong> Conferir se todos os cidadãos foram atendidos e fazer logout seguro do sistema.</li>
</ul>

</body>
</html>
"""

# ==============================================================================
# 2. MANUAL DO PERITO MÉDICO
# ==============================================================================
html_perito = f"""<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Manual do Usuário — Médico Perito Judicial (SGA JFAL)</title>
    {css_style}
</head>
<body>

<!-- CAPA -->
<div class="cover-page">
    <div class="cover-header">
        <img src="{img_catavento}" alt="JFAL" style="height: 50px; width: auto;">
        <div class="cover-header-text">
            <h3>Poder Judiciário &bull; Justiça Federal</h3>
            <h4>Tribunal Regional Federal da 5ª Região &bull; Seção Judiciária de Alagoas</h4>
        </div>
    </div>
    
    <div class="cover-body">
        <div class="badge-manual">Manual do Usuário &bull; Módulo Clínico-Pericial</div>
        <h1 class="cover-title">Painel do Médico Perito Judicial</h1>
        <h2 class="cover-subtitle">Guia Operacional: Chamadas na TV, Autonomia de Critérios, Tolerância Inteligente e Emissão de Laudos</h2>
        <p class="cover-desc">
            Instruções detalhadas para o corpo pericial da Justiça Federal: seleção de consultório com trava anti-conflito, chamadas audiovisuais no painel da sala de espera, alternância dinâmica de critérios de fila (Chegada vs Horário PJe), chave de prioridade legal e encerramento de turno.
        </p>
    </div>
    
    <div class="cover-footer">
        <div>
            <strong>Sistema:</strong> SGA JFAL (Versão 2.4)<br>
            <strong>Público-Alvo:</strong> Médicos Peritos Judiciais Federais<br>
            <strong>Área Responsável:</strong> Divisão de Tecnologia da Informação (DTI)
        </div>
        <div>
            <strong>Homologação:</strong> Coordenadoria dos Juizados Especiais Federais<br>
            <strong>Data da Edição:</strong> Novembro de 2026<br>
            <strong>Ambiente:</strong> Produção Institucional (TRF5 / JFAL)
        </div>
    </div>
</div>

<!-- SUMÁRIO -->
<div class="toc">
    <div class="toc-title">Sumário do Manual</div>
    <div class="toc-item"><span>1. Apresentação e Filosofia do Painel Pericial</span><span>Pág. 2</span></div>
    <div class="toc-item"><span>2. Acesso e Seleção de Sala com Trava Anti-Conflito</span><span>Pág. 2</span></div>
    <div class="toc-item"><span>3. Anatomia do Painel de Atendimento do Consultório</span><span>Pág. 3</span></div>
    <div class="toc-item"><span>4. Autonomia de Critérios: Ordem de Chegada vs Horário PJe</span><span>Pág. 4</span></div>
    <div class="toc-item"><span>5. Gestão de Prioridades Legais (Superprioridade 80+ e 60+/PCD)</span><span>Pág. 4</span></div>
    <div class="toc-item"><span>6. O Ciclo de Atendimento em 3 Etapas</span><span>Pág. 5</span></div>
    <div class="toc-item"><span>7. Ausência e Tolerância Inteligente: Regra de Descer 2 Posições</span><span>Pág. 6</span></div>
    <div class="toc-item"><span>8. Alertas em Tempo Real: Notificações Toast</span><span>Pág. 7</span></div>
    <div class="toc-item"><span>9. Emissão de Declaração Direto do Consultório</span><span>Pág. 7</span></div>
    <div class="toc-item"><span>10. Encerramento do Expediente e Liberação Automática de Sala</span><span>Pág. 8</span></div>
</div>

<!-- SEÇÃO 1 -->
<h2 class="section-title">1. Apresentação e Filosofia do Painel Pericial</h2>
<p>
    O módulo do <strong>Médico Perito Judicial</strong> no SGA foi concebido para entregar total soberania ao profissional de saúde durante a condução de sua pauta pericial, eliminando atritos administrativos, reduzindo interrupções no consultório e conferindo transparência absoluta ao jurisdicionado.
</p>
<p>
    Com poucos cliques na tela, o perito convoca os pacientes na TV da sala de espera com sonorização de alta visibilidade, controla o tempo dos exames periciais e administra casos de tolerância ou prioridades de forma descomplicada.
</p>

<!-- SEÇÃO 2 -->
<h2 class="section-title">2. Acesso e Seleção de Sala com Trava Anti-Conflito</h2>
<p>
    Ao realizar o login com seu usuário individual no endereço <span class="badge-code">http://atendimento.jfal.jus.br</span>, o sistema apresentará a tela de seleção de salas:
</p>

<div class="figure-container">
    <img src="{img_sala}" class="figure-img" alt="Seleção de Consultório">
    <div class="figure-caption">Figura 2.1 — Tela de Seleção de Consultório Pericial com trava inteligente anti-conflito.</div>
</div>

<h3 class="subsection-title">2.1. Como Funciona a Trava Anti-Conflito:</h3>
<ul>
    <li><strong>Salas Disponíveis (Verde):</strong> Apresentam o botão <span class="badge-code">Entrar na Sala</span> liberado. Ao clicar, aquela sala física é vinculada imediatamente à sua sessão no banco de dados.</li>
    <li><strong>Salas Ocupadas (Cinza/Bloqueada):</strong> Exibem uma tarja com o nome do colega médico que já está atendendo naquele ambiente (ex: <em>"Ocupada pelo Dr. Roberto Souza"</em>). O botão fica bloqueado, impossibilitando que dois médicos convoquem pacientes por engano para a mesma sala.</li>
    <li><strong>Troca de Sala:</strong> Caso precise mudar de consultório ao longo do dia, não é preciso deslogar: basta clicar no botão <strong>Trocar de Sala</strong> localizado no canto superior direito do painel.</li>
</ul>

<!-- SEÇÃO 3 -->
<div class="page-break"></div>
<h2 class="section-title">3. Anatomia do Painel de Atendimento do Consultório</h2>
<p>
    Após ingressar na sala, o perito visualiza a tela operacional unificada:
</p>

<div class="figure-container">
    <img src="{img_perito}" class="figure-img" alt="Painel do Médico Perito">
    <div class="figure-caption">Figura 3.1 — Painel do Consultório com alternadores de critérios, próximo da fila e lista de espera.</div>
</div>

<h3 class="subsection-title">3.1. Seções Estruturais do Painel:</h3>
<ul>
    <li><strong>Cabeçalho do Consultório:</strong> Informa a sala ativa (ex: <em>🚪 Sala 02 — Perícias Médicas</em>), com atalhos para <strong>Liberar Sala</strong> e <strong>Sair</strong>.</li>
    <li><strong>Barra de Autonomia Clínica:</strong> Botões seletores para alternar entre <strong>Ordem de Chegada</strong> e <strong>Horário PJe</strong>, além da chave de <strong>Prioridade Legal</strong>.</li>
    <li><strong>Card do Próximo da Fila:</strong> Destaque visual contendo a senha, o nome completo do autor, número do processo judicial, CPF, hora em que deu entrada na recepção e selo de prioridade (se houver).</li>
    <li><strong>Botoeira de Ação:</strong> Botões de comando direto: <em>Chamar Paciente (Azul)</em>, <em>Iniciar Perícia (Verde)</em> e <em>Ausente / Descer 2 posições (Âmbar)</em>.</li>
    <li><strong>Tabela de Fila de Espera:</strong> Relação de todos os periciados da sua pauta que já deram entrada na recepção e aguardam chamada.</li>
</ul>

<!-- SEÇÃO 4 & 5 -->
<div class="page-break"></div>
<h2 class="section-title">4. Autonomia de Critérios: Ordem de Chegada vs Horário PJe</h2>
<p>
    Diferentes peritos possuem diferentes metodologias de condução de suas pautas. O SGA respeita essa individualidade através de um seletor instantâneo:
</p>

<table>
    <thead>
        <tr>
            <th style="width: 25%;">Critério Selecionado</th>
            <th style="width: 45%;">Comportamento da Fila</th>
            <th style="width: 30%;">Indicação de Uso</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td><strong>🏃 Ordem de Chegada</strong><br><em>(Padrão do Sistema)</em></td>
            <td>Ordena os periciados presentes rigorosamente pelo horário em que realizaram o check-in físico na recepção.</td>
            <td>Recomendado para evitar que pacientes que chegaram muito cedo aguardem além do necessário.</td>
        </tr>
        <tr>
            <td><strong>🕒 Horário do PJe</strong></td>
            <td>Reorganiza a fila dos periciados presentes tomando por base o horário de agendamento original constante na pauta do processo.</td>
            <td>Recomendado para peritos que preferem seguir à risca o cronograma formal das intimações judiciais.</td>
        </tr>
    </tbody>
</table>

<h2 class="section-title">5. Gestão de Prioridades Legais (Superprioridade 80+ e 60+/PCD)</h2>
<p>
    A legislação federal estabelece prioridades especiais para idosos e pessoas com deficiência. No SGA, o médico possui total discernimento sobre o momento de aplicar a priorização:
</p>

<div class="callout callout-priority">
    <strong>⭐ Chave de Atendimento Prioritário (Autonomia Médica)</strong>
    <p>
        &bull; <strong>Ativada:</strong> Os periciados classificados pela recepção como <strong>Superprioridade (80+)</strong> e <strong>Prioridade Legal (60+/PCD)</strong> são içados automaticamente para as primeiras colocações da sua fila de espera.<br>
        &bull; <strong>Desativada:</strong> A fila segue a ordem cronológica direta (seja por chegada ou horário PJe), cabendo ao médico chamar o prioritário no momento mais oportuno de sua rotina.
    </p>
</div>

<p>
    <em>Nota de Transparência:</em> A recepção visualiza em tempo real no painel dela se a prioridade do médico está ativada ou opcional, permitindo esclarecer qualquer questionamento do jurisdicionado com total clareza.
</p>

<!-- SEÇÃO 6 -->
<div class="page-break"></div>
<h2 class="section-title">6. O Ciclo de Atendimento em 3 Etapas</h2>
<p>
    O atendimento pericial desenrola-se em um ciclo intuitivo de três comandos fundamentais:
</p>

<div class="figure-container">
    <img src="{img_tv}" class="figure-img" alt="Chamada na TV da Sala de Espera">
    <div class="figure-caption">Figura 6.1 — Chamada disparada para a TV da sala de espera com sonorização e destaque do selo de prioridade.</div>
</div>

<h3 class="subsection-title">Passo 1: Chamar o Paciente</h3>
<ol>
    <li>Confira os dados do periciado em destaque no card <strong>Próximo da Fila</strong>.</li>
    <li>Clique no botão azul <strong>🔔 Chamar Paciente</strong>.</li>
    <li>O sistema dispara o comando para a TV da sala de espera:
        <ul>
            <li>Toca um sinal sonoro claro de campainha.</li>
            <li>A voz sintetizada anuncia no alto-falante: <em>"Senha PE-01, Dr. Carlos Eduardo Silveira, Sala 02"</em>.</li>
            <li>O monitor exibe o modal em tela cheia com a senha, o nome completo e o selo de prioridade.</li>
        </ul>
    </li>
    <li>O status do botão muda para <strong>Chamar Novamente</strong>, permitindo repetir o aviso caso o paciente demore a comparecer à porta.</li>
</ol>

<h3 class="subsection-title">Passo 2: Iniciar a Perícia</h3>
<ol>
    <li>Quando o paciente adentrar o consultório, clique no botão verde <strong>▶ Iniciar Perícia</strong>.</li>
    <li>O cronômetro do atendimento é acionado na tela.</li>
    <li>O status do médico nos painéis da recepção e supervisão muda instantaneamente para: <span class="badge-code">🟢 Em Exame: PE-01 (Nome)</span>.</li>
</ol>

<h3 class="subsection-title">Passo 3: Concluir o Atendimento</h3>
<ol>
    <li>Ao concluir o exame físico e as anotações periciais, clique no botão roxo <strong>✓ Concluir Atendimento</strong>.</li>
    <li>O sistema registra o término, computa o tempo total de consulta para os indicadores estatísticos e já posiciona automaticamente o próximo paciente da fila em destaque!</li>
</ol>

<!-- SEÇÃO 7 & 8 -->
<div class="page-break"></div>
<h2 class="section-title">7. Ausência e Tolerância Inteligente: Regra de Descer 2 Posições</h2>
<p>
    Um dos maiores diferenciais do SGA é o mecanismo de <strong>Tolerância Inteligente de Ausência</strong>.
</p>
<p>
    Se o perito acionar a chamada repetidas vezes e o periciado não comparecer à porta do consultório:
</p>
<ol>
    <li>Clique no botão âmbar <strong>⚠️ Ausente (Descer 2 posições)</strong>.</li>
    <li><strong>O que o sistema faz:</strong>
        <ul>
            <li>Em vez de registrar falta imediata ou cancelar o processo (o cidadão pode ter ido momentaneamente ao sanitário ou estar com dificuldade de locomoção), o SGA <strong>reposiciona o paciente 2 posições para trás</strong> na fila dos presentes.</li>
            <li>O próximo periciado da fila assume a liderança imediatamente, permitindo ao médico continuar atendendo sem perder tempo.</li>
            <li>Após o atendimento de dois outros pacientes, o periciado ausente volta ao topo da fila para nova chamada.</li>
        </ul>
    </li>
</ol>

<div class="callout callout-tip">
    <strong>💡 Eficiência com Humanização</strong>
    Essa regra evita o cancelamento traumático de perícias médicas previdenciárias e elimina o tempo ocioso do consultório.
</div>

<h2 class="section-title">8. Alertas em Tempo Real: Notificações Toast</h2>
<p>
    Durante o exame físico de um paciente, o médico não precisa atualizar a página para saber se os demais pacientes do dia estão chegando.
</p>

<div class="figure-container">
    <img src="{img_toast}" class="figure-img" alt="Notificação Toast">
    <div class="figure-caption">Figura 8.1 — Alerta toast discreto informando a chegada de um novo periciado no balcão da recepção.</div>
</div>

<p>
    Sempre que a recepção realiza o check-in de um novo periciado da sua pauta, um suave sinal sonoro é emitido e uma notificação flutuante (*toast*) surge no canto da tela informando o nome e a senha do recém-chegado. A lista de espera é reorganizada em segundo plano sem qualquer interrupção.
</p>

<!-- SEÇÃO 9 & 10 -->
<div class="page-break"></div>
<h2 class="section-title">9. Emissão de Declaração de Comparecimento (Rotina e Atalho Opcional)</h2>
<div class="callout callout-info">
    <strong>ℹ️ Fluxo Padrão Institucional da JFAL</strong>
    <p>
        Na rotina padrão dos Juizados Especiais Federais, <strong>a emissão e entrega da Declaração de Comparecimento é atribuição da equipe de Recepção</strong>. O médico perito dedica-se exclusivamente ao exame clínico pericial. Ao término da perícia, o periciado é simplesmente orientado a retirar sua declaração carimbada diretamente no balcão da Recepção.
    </p>
</div>
<p>
    Caso o jurisdicionado solicite a comprovação de presença de imediato no consultório e o perito médico deseje imprimi-la, o sistema disponibiliza um atalho direto de conveniência:
</p>
<ol>
    <li>No card do atendimento atual ou no histórico de atendidos na lateral do painel, clique no atalho <strong>🖨️ Declaração</strong>.</li>
    <li>O formulário oficial A4 da Justiça Federal será aberto com todos os campos preenchidos (nome do perito, horários de início e término e processo).</li>
    <li>Pressione <span class="badge-code">Ctrl + P</span> para enviar à impressora do consultório.</li>
</ol>

<div class="figure-container">
    <img src="{img_declaracao}" class="figure-img" alt="Declaração de Comparecimento">
    <div class="figure-caption">Figura 9.1 — Comprovante oficial de comparecimento formatado para folha A4.</div>
</div>

<h2 class="section-title">10. Encerramento do Expediente e Liberação Automática de Sala</h2>
<p>
    Ao finalizar todos os atendimentos da pauta do dia:
</p>
<ol>
    <li>Certifique-se de que o último atendimento foi concluído no botão roxo.</li>
    <li>Clique no botão vermelho <strong>Sair</strong> localizado no cabeçalho superior.</li>
    <li><strong>Ação Automática de Governança:</strong> O sistema encerra sua sessão e <strong>desvincula imediatamente a sala física no banco de dados</strong>, deixando-a desocupada e disponível para que outro perito médico possa utilizá-la no turno seguinte sem qualquer conflito de agendamento.</li>
</ol>

</body>
</html>
"""

# ==============================================================================
# 3. MANUAL DO SUPERVISOR E GESTOR
# ==============================================================================
html_supervisor = f"""<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Manual do Usuário — Gestão e Supervisão de Perícias (SGA JFAL)</title>
    {css_style}
</head>
<body>

<!-- CAPA -->
<div class="cover-page">
    <div class="cover-header">
        <img src="{img_catavento}" alt="JFAL" style="height: 50px; width: auto;">
        <div class="cover-header-text">
            <h3>Poder Judiciário &bull; Justiça Federal</h3>
            <h4>Tribunal Regional Federal da 5ª Região &bull; Seção Judiciária de Alagoas</h4>
        </div>
    </div>
    
    <div class="cover-body">
        <div class="badge-manual">Manual do Usuário &bull; Módulo de Gestão Estratégica</div>
        <h1 class="cover-title">Gestão e Supervisão de Perícias</h1>
        <h2 class="cover-subtitle">Dashboard Executivo, KPIs em Tempo Real, Importação de Pautas PJe (.xlsx) e Auditoria</h2>
        <p class="cover-desc">
            Manual completo para Diretores de Secretaria, Supervisores de Perícias e Coordenadores dos Juizados Especiais Federais: monitoramento de indicadores de espera e absenteísmo, controle de produtividade médica por sala, exportação de relatórios em CSV e homologação de pautas processuais.
        </p>
    </div>
    
    <div class="cover-footer">
        <div>
            <strong>Sistema:</strong> SGA JFAL (Versão 2.4)<br>
            <strong>Público-Alvo:</strong> Diretores, Supervisores e Gestores Judiciários<br>
            <strong>Área Responsável:</strong> Divisão de Tecnologia da Informação (DTI)
        </div>
        <div>
            <strong>Homologação:</strong> Coordenadoria dos Juizados Especiais Federais<br>
            <strong>Data da Edição:</strong> Novembro de 2026<br>
            <strong>Ambiente:</strong> Produção Institucional (TRF5 / JFAL)
        </div>
    </div>
</div>

<!-- SUMÁRIO -->
<div class="toc">
    <div class="toc-title">Sumário do Manual</div>
    <div class="toc-item"><span>1. Papel da Supervisão e Governança Pericial</span><span>Pág. 2</span></div>
    <div class="toc-item"><span>2. Acesso ao Painel Executivo e Níveis de Permissão</span><span>Pág. 2</span></div>
    <div class="toc-item"><span>3. Dashboard Executivo e Indicadores Chave (KPIs)</span><span>Pág. 3</span></div>
    <div class="toc-item"><span>4. Auditoria e Produtividade Individual por Médico Perito</span><span>Pág. 4</span></div>
    <div class="toc-item"><span>5. Exportação de Relatórios Gerenciais em CSV</span><span>Pág. 5</span></div>
    <div class="toc-item"><span>6. Importação e Homologação de Pautas do PJe (.xlsx)</span><span>Pág. 6</span></div>
    <div class="toc-item"><span>7. Monitoramento e Parâmetros do Painel TV da Sala de Espera</span><span>Pág. 7</span></div>
    <div class="toc-item"><span>8. Rotina Diária Recomendada do Gestor</span><span>Pág. 8</span></div>
</div>

<!-- SEÇÃO 1 & 2 -->
<h2 class="section-title">1. Papel da Supervisão e Governança Pericial</h2>
<p>
    A condução de perícias médicas nos Juizados Especiais Federais envolve alta complexidade logística: confluência de múltiplos peritos especialistas, centenas de cidadãos em situação de vulnerabilidade de saúde, prazos processuais estritos e necessidade de auditoria permanente.
</p>
<p>
    O módulo de <strong>Supervisão e Gestão</strong> do SGA consolida todos esses fluxos em um painel executivo com métricas em tempo real, fornecendo aos gestores as ferramentas necessárias para intervir proativamente, corrigir gargalos de atendimento e gerar relatórios probatórios para a Corregedoria Regional do TRF5.
</p>

<h2 class="section-title">2. Acesso ao Painel Executivo e Níveis de Permissão</h2>
<p>
    O acesso ao módulo de supervisão é restrito aos servidores designados para a gestão de perícias e diretores de vara:
</p>
<ol>
    <li>Acesse o portal: <span class="badge-code">http://atendimento.jfal.jus.br</span>.</li>
    <li>Insira o usuário e a senha institucionais autorizados com o perfil de <strong>Supervisor</strong> ou <strong>Administrador</strong>.</li>
    <li>No menu superior de navegação, selecione o item <strong>📊 Dashboard</strong> ou <strong>📅 Gestão de Pauta</strong>.</li>
</ol>

<div class="figure-container">
    <img src="{img_login}" class="figure-img" alt="Login do Supervisor">
    <div class="figure-caption">Figura 2.1 — Tela de Autenticação segura do SGA JFAL.</div>
</div>

<!-- SEÇÃO 3 -->
<div class="page-break"></div>
<h2 class="section-title">3. Dashboard Executivo e Indicadores Chave (KPIs)</h2>
<p>
    O Dashboard Executivo oferece uma visão panorâmica e instantânea da capacidade de atendimento de todo o fórum federal:
</p>

<div class="figure-container">
    <img src="{img_dashboard}" class="figure-img" alt="Dashboard Executivo">
    <div class="figure-caption">Figura 3.1 — Dashboard Executivo com cartões de indicadores, gráficos de status e métricas de desempenho.</div>
</div>

<h3 class="subsection-title">3.1. Detalhamento dos Indicadores Operacionais (KPIs):</h3>
<table>
    <thead>
        <tr>
            <th style="width: 25%;">Indicador</th>
            <th style="width: 45%;">Conceito e Forma de Cálculo</th>
            <th style="width: 30%;">Aplicação Gerencial</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td><strong>📅 Total Agendado</strong></td>
            <td>Volume global de perícias judiciais designadas para a data selecionada na pauta homologada.</td>
            <td>Dimensionamento da carga diária do fórum.</td>
        </tr>
        <tr>
            <td><strong>🏃 Presentes (Check-in)</strong></td>
            <td>Total de jurisdicionados que já compareceram ao balcão e emitiram senha de espera.</td>
            <td>Acompanhamento da taxa de chegada em tempo real.</td>
        </tr>
        <tr>
            <td><strong>⚠️ Taxa de Absenteísmo</strong></td>
            <td>Percentual de periciados que não compareceram no horário designado em relação ao total agendado.</td>
            <td>Identificação de índices anormais de não-comparecimento para comunicação aos juizados.</td>
        </tr>
        <tr>
            <td><strong>🕒 Tempo Médio de Espera</strong></td>
            <td>Diferença de tempo média entre o check-in na recepção e o momento em que o perito inicia o exame pericial.</td>
            <td>Controle de metas de humanização e prevenção de filas excessivas.</td>
        </tr>
        <tr>
            <td><strong>⏱️ Tempo Médio de Perícia</strong></td>
            <td>Duração média de realização dos exames clínicos nos consultórios médicos.</td>
            <td>Avaliação da fluidez dos exames por especialidade.</td>
        </tr>
    </tbody>
</table>

<!-- SEÇÃO 4 & 5 -->
<div class="page-break"></div>
<h2 class="section-title">4. Auditoria e Produtividade Individual por Médico Perito</h2>
<p>
    Na parte inferior do Dashboard Executivo, a tabela de auditoria operacional discrimina a atuação de cada perito médico escalado para o expediente do dia:
</p>

<h3 class="subsection-title">4.1. Colunas de Monitoramento:</h3>
<ul>
    <li><strong>Médico Perito:</strong> Nome do profissional e especialidade médica cadastrada.</li>
    <li><strong>Sala Ocupada:</strong> Indica o consultório ativo ou a informação <span class="badge-code">Desconectado</span> caso o perito ainda não tenha aberto sala.</li>
    <li><strong>Critério Adotado:</strong> Informa com total transparência se o perito está chamando por <em>Ordem de Chegada</em> ou <em>Horário PJe</em>, e se a prioridade legal está <em>Ativa</em> ou <em>Opcional</em>.</li>
    <li><strong>Agendados vs Realizados:</strong> Total de processos previstos na pauta do profissional e quantos exames já foram concluídos.</li>
    <li><strong>Faltas Confirmadas:</strong> Processos em que o paciente não compareceu.</li>
    <li><strong>Tempo Médio de Atendimento:</strong> Duração média das perícias realizadas especificamente por aquele perito.</li>
</ul>

<h2 class="section-title">5. Exportação de Relatórios Gerenciais em CSV</h2>
<p>
    Para envio de relatórios semanais ou mensais à Diretoria do Foro, aos magistrados titulares das Varas Federais e à Corregedoria Regional do TRF5, o gestor conta com a exportação de dados em formato aberto:
</p>

<div class="callout callout-info">
    <strong>📥 Como Exportar o Relatório do Dia</strong>
    <ol style="margin-bottom: 0;">
        <li>Selecione a data ou período desejado no filtro de calendário no topo do Dashboard.</li>
        <li>No canto superior direito da tabela de peritos, clique no botão <strong>📥 Exportar Relatório (CSV)</strong>.</li>
        <li>O arquivo será baixado instantaneamente em formato CSV delimitado por vírgula, 100% compatível com Microsoft Excel, LibreOffice Calc e Power BI.</li>
        <li>O relatório contém colunas detalhadas com carimbo de data/hora de chegada, início do exame, conclusão, tempos de espera, número do processo, perito e vara de origem.</li>
    </ol>
</div>

<!-- SEÇÃO 6 -->
<div class="page-break"></div>
<h2 class="section-title">6. Importação e Homologação de Pautas do PJe (.xlsx)</h2>
<p>
    O SGA elimina completamente a necessidade de digitação manual de pautas de audiência através de seu motor de ingestão de planilhas Excel geradas diretamente pelo sistema PJe do TRF5.
</p>

<div class="figure-container">
    <img src="{img_pauta}" class="figure-img" alt="Gerenciamento de Pauta">
    <div class="figure-caption">Figura 6.1 — Módulo de importação e homologação de pautas Excel (.xlsx) extraídas do PJe.</div>
</div>

<h3 class="subsection-title">6.1. Procedimento de Carga de Pauta:</h3>
<ol>
    <li>No sistema PJe, exporte a pauta de perícias no formato padrão Excel (<span class="badge-code">.xlsx</span>).</li>
    <li>No menu do SGA, acesse <strong>📅 Gestão de Pauta &bull; Importar Nova Pauta</strong>.</li>
    <li>Arraste o arquivo para a área demarcada ou clique em <strong>Selecionar Arquivo no Computador</strong>.</li>
    <li>Selecione a <strong>Janela de Importação</strong> desejada:
        <ul>
            <li><em>Somente o Dia Selecionado (Hoje)</em>: Para a rotina diária de atendimento.</li>
            <li><em>Próximos 3 Dias</em>: Para adiantar cargas antes de feriados ou fins de semana.</li>
            <li><em>Mês Inteiro</em>: Para mutirões concentrados de benefícios previdenciários e assistenciais.</li>
        </ul>
    </li>
    <li><strong>Homologação Automática:</strong>
        <ul>
            <li>Deixe marcada caso deseje que a pauta seja liberada imediatamente nos balcões da recepção.</li>
            <li>Desmarque caso queira inspecionar a listagem antes de autorizar os check-ins.</li>
        </ul>
    </li>
    <li>Clique no botão verde <strong>🚀 Processar e Importar Pauta para o SGA</strong>.</li>
</ol>

<!-- SEÇÃO 7 & 8 -->
<div class="page-break"></div>
<h2 class="section-title">7. Monitoramento e Parâmetros do Painel TV da Sala de Espera</h2>
<p>
    O painel de chamada pública é exibido em televisores de grande porte instalados na sala de espera:
</p>

<div class="figure-container">
    <img src="{img_tv}" class="figure-img" alt="Painel TV de Chamadas">
    <div class="figure-caption">Figura 7.1 — Interface do Painel TV com sinalização audiovisual e histórico de chamados.</div>
</div>

<h3 class="subsection-title">7.1. Diretrizes de Operação Técnica:</h3>
<ul>
    <li><strong>Endereço da TV:</strong> <span class="badge-code">http://atendimento.jfal.jus.br/painel.php</span>.</li>
    <li><strong>Modo Tela Cheia:</strong> Pressione a tecla <strong>F11</strong> no teclado do computador da TV para ocultar barras de endereço e guias do navegador.</li>
    <li><strong>Áudio e Sintetização de Voz:</strong> Os navegadores modernos exigem ao menos uma interação física (clique com o mouse) após carregar a página para liberar a reprodução de áudio. Assegure-se de clicar uma vez na tela da TV após abri-la.</li>
    <li><strong>Histórico Permanente:</strong> O painel exibe na lateral direita as últimas 5 senhas chamadas, garantindo que mesmo cidadãos com deficiência auditiva ou que não tenham ouvido a campainha possam consultar a chamada.</li>
</ul>

<h2 class="section-title">8. Rotina Diária Recomendada do Gestor</h2>
<table>
    <thead>
        <tr>
            <th style="width: 20%;">Horário</th>
            <th style="width: 80%;">Ações Operacionais de Supervisão</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td><strong>07:45</strong></td>
            <td>Importar e homologar a planilha do PJe no módulo de pauta. Conferir se a pauta está marcada como homologada.</td>
        </tr>
        <tr>
            <td><strong>08:15</strong></td>
            <td>Acompanhar a abertura de salas pelos peritos médicos e verificar se a recepção iniciou os check-ins.</td>
        </tr>
        <tr>
            <td><strong>11:30</strong></td>
            <td>Verificar no Dashboard os tempos médios de espera e a taxa de absenteísmo matutina.</td>
        </tr>
        <tr>
            <td><strong>16:45</strong></td>
            <td>Conferir encerramento das salas periciais e exportar o relatório consolidado em CSV do dia.</td>
        </tr>
    </tbody>
</table>

</body>
</html>
"""

# ==============================================================================
# 4. MANUAL GERAL COMPLETO UNIFICADO
# ==============================================================================
html_geral = f"""<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Manual Geral Completo — SGA JFAL</title>
    {css_style}
</head>
<body>

<!-- CAPA GERAL -->
<div class="cover-page">
    <div class="cover-header">
        <img src="{img_catavento}" alt="JFAL" style="height: 50px; width: auto;">
        <div class="cover-header-text">
            <h3>Poder Judiciário &bull; Justiça Federal</h3>
            <h4>Tribunal Regional Federal da 5ª Região &bull; Seção Judiciária de Alagoas</h4>
        </div>
    </div>
    
    <div class="cover-body">
        <div class="badge-manual">Manual Institucional Completo &bull; Edição Unificada</div>
        <h1 class="cover-title">Sistema de Gerenciamento de Atendimento Pericial</h1>
        <h2 class="cover-subtitle">Guia Integral de Operação, Governança, Procedimentos Clínicos e Legislação de Prioridades</h2>
        <p class="cover-desc">
            Documento consolidado abrangendo a totalidade dos módulos do SGA JFAL: recepção e triagem, consultório do médico perito judicial, gestão e supervisão executiva, integração com o PJe (.xlsx), chamadas audiovisuais na TV e conformidade estrita com o Estatuto da Pessoa Idosa e Leis nº 10.048/00 e 13.466/17.
        </p>
    </div>
    
    <div class="cover-footer">
        <div>
            <strong>Sistema:</strong> SGA JFAL (Versão 2.4 — Produção)<br>
            <strong>Abrangência:</strong> Recepção, Corpo Médico Pericial e Supervisão<br>
            <strong>Área Responsável:</strong> Divisão de Tecnologia da Informação (DTI)
        </div>
        <div>
            <strong>Homologação:</strong> Coordenadoria dos Juizados Especiais Federais<br>
            <strong>Data da Edição:</strong> Novembro de 2026<br>
            <strong>Ambiente:</strong> Produção Institucional (TRF5 / JFAL)
        </div>
    </div>
</div>

<!-- SUMÁRIO GERAL -->
<div class="toc">
    <div class="toc-title">Sumário do Manual Consolidado</div>
    <div class="toc-item"><span>Parte I — Arquitetura Institucional e Fluxo Ponta a Ponta</span><span>Pág. 2</span></div>
    <div class="toc-item"><span>Parte II — Módulo de Recepção e Triagem</span><span>Pág. 3</span></div>
    <div class="toc-item"><span>Parte III — Módulo do Médico Perito Judicial</span><span>Pág. 5</span></div>
    <div class="toc-item"><span>Parte IV — Módulo de Gestão e Supervisão Executiva</span><span>Pág. 7</span></div>
    <div class="toc-item"><span>Parte V — Painel TV da Sala de Espera</span><span>Pág. 9</span></div>
    <div class="toc-item"><span>Parte VI — Marco Legal de Prioridades e Anexos</span><span>Pág. 10</span></div>
</div>

<!-- PARTE I -->
<h2 class="section-title">Parte I — Arquitetura Institucional e Fluxo Ponta a Ponta</h2>
<p>
    O <strong>Sistema de Gerenciamento de Atendimento Pericial (SGA)</strong> da Seção Judiciária de Alagoas foi estruturado para conferir excelência e rastreabilidade total ao ciclo de perícias médicas da Justiça Federal.
</p>

<div class="figure-container">
    <img src="{img_dashboard}" class="figure-img" alt="Visão Geral SGA">
    <div class="figure-caption">Figura I.1 — Painel Integrado de Governança e Métricas Operacionais do SGA JFAL.</div>
</div>

<h3 class="subsection-title">O Ciclo Ponta a Ponta do Jurisdicionado:</h3>
<ol>
    <li><strong>Carga da Pauta do PJe:</strong> A supervisão importa a planilha de pauta extraída do Processo Judicial Eletrônico (.xlsx) e homologa os agendamentos do dia.</li>
    <li><strong>Acolhimento e Check-in:</strong> O cidadão chega ao fórum e se apresenta à recepção, que confere o documento, classifica eventual prioridade legal e emite a senha sequencial.</li>
    <li><strong>Espera Informada:</strong> O cidadão acompanha sua posição exata na fila e sabe quantas pessoas estão em sua frente.</li>
    <li><strong>Convocação Audiovisual na TV:</strong> O médico perito clica em "Chamar" e a TV da sala de espera emite campainha, sintetização de voz e destaque do paciente.</li>
    <li><strong>Exame Clínico-Pericial:</strong> O médico realiza o exame, podendo alternar critérios de fila ou tolerar ausências por até 2 posições.</li>
    <li><strong>Declaração Oficial:</strong> Emissão da declaração padronizada em folha A4 com brasão oficial da República para fins trabalhistas ou previdenciários.</li>
    <li><strong>Auditoria em Tempo Real:</strong> Todos os tempos e atos são registrados para fins estatísticos e prestação de contas aos magistrados e à Corregedoria.</li>
</ol>

<!-- PARTE II -->
<div class="page-break"></div>
<h2 class="section-title">Parte II — Módulo de Recepção e Triagem</h2>
<p>
    A recepção opera como o ponto focal de interação direta com os jurisdicionados. Seus procedimentos incluem a busca rápida, emissão de senhas e aplicação estrita das prioridades legais.
</p>

<div class="figure-container">
    <img src="{img_recepcao}" class="figure-img" alt="Painel da Recepção">
    <div class="figure-caption">Figura II.1 — Painel Geral da Recepção com status ao vivo de médicos, salas e lista de pauta.</div>
</div>

<h3 class="subsection-title">Principais Comandos da Recepção:</h3>
<ul>
    <li><strong>Confirmar Chegada:</strong> Registra a presença, emite senha sequencial (<span class="badge-code">PE-XX</span>) e grava a hora exata da chegada.</li>
    <li><strong>Classificar Prioridade:</strong> Modal interativo para definir entre <em>Normal</em>, <em>Prioridade Legal (60+/PCD)</em> ou <em>Superprioridade (80+)</em>.</li>
    <li><strong>Orientar Posição na Fila:</strong> Visualização da colocação do paciente (ex: <em>2º na Fila — 1 pessoa na frente</em>).</li>
    <li><strong>Emitir Declaração A4:</strong> Geração instantânea do documento oficial para impressão.</li>
</ul>

<div class="figure-container">
    <img src="{img_prioridade}" class="figure-img" alt="Modal Prioridade">
    <div class="figure-caption">Figura II.2 — Modal de seleção de prioridades legais em conformidade com as Leis 10.048/00 e 13.466/17.</div>
</div>

<!-- PARTE III -->
<div class="page-break"></div>
<h2 class="section-title">Parte III — Módulo do Médico Perito Judicial</h2>
<p>
    O consultório pericial é dotado de autonomia e agilidade para condução da pauta médica com alto padrão técnico.
</p>

<div class="figure-container">
    <img src="{img_perito}" class="figure-img" alt="Painel do Perito">
    <div class="figure-caption">Figura III.1 — Painel Operacional do Consultório Pericial.</div>
</div>

<h3 class="subsection-title">Recursos Centrais do Médico:</h3>
<ul>
    <li><strong>Trava Anti-Conflito de Sala:</strong> Prevenção de duplicidade na ocupação dos consultórios físicos.</li>
    <li><strong>Alternador de Critério:</strong> Liberdade para alternar entre <em>Ordem de Chegada</em> e <em>Horário PJe</em> com reorganização imediata da fila.</li>
    <li><strong>Chave de Prioridade Legal:</strong> Autonomia médica para ativar a priorização automática no topo da fila ou seguir a ordem cronológica direta.</li>
    <li><strong>Ciclo em 3 Passos:</strong> <em>Chamar Paciente (TV)</em> &rarr; <em>Iniciar Perícia</em> &rarr; <em>Concluir Exame</em>.</li>
    <li><strong>Regra de Ausência Inteligente:</strong> Ao clicar em <em>Ausente</em>, o paciente é reposicionado 2 posições para trás na fila dos presentes, evitando cancelamentos desnecessários e mantendo a produtividade do consultório.</li>
    <li><strong>Alertas Toast:</strong> Notificação silenciosa na tela a cada novo paciente registrado no balcão.</li>
    <li><strong>Liberação Automática de Sala:</strong> Desocupação imediata no banco de dados ao fazer logout.</li>
</ul>

<div class="figure-container">
    <img src="{img_sala}" class="figure-img" alt="Seleção de Sala">
    <div class="figure-caption">Figura III.2 — Tela de escolha de sala com indicação de salas livres e ocupadas.</div>
</div>

<!-- PARTE IV -->
<div class="page-break"></div>
<h2 class="section-title">Parte IV — Módulo de Gestão e Supervisão Executiva</h2>
<p>
    Destinado a Diretores de Secretaria, Juízes Coordenadores dos Juizados e Supervisores de Perícias para acompanhamento global da produtividade judiciária.
</p>

<div class="figure-container">
    <img src="{img_pauta}" class="figure-img" alt="Gestão de Pautas">
    <div class="figure-caption">Figura IV.1 — Módulo de Carga e Homologação de Pautas do PJe (.xlsx).</div>
</div>

<h3 class="subsection-title">Atribuições da Gestão:</h3>
<ul>
    <li><strong>Indicadores Executivos:</strong> Monitoramento de absenteísmo, volume de perícias agendadas, concluídas e em andamento.</li>
    <li><strong>Tempos Médios:</strong> Acompanhamento de metas de tempo de espera e duração dos exames periciais.</li>
    <li><strong>Auditoria Individual:</strong> Produtividade médica detalhada por profissional e consultório.</li>
    <li><strong>Exportação CSV:</strong> Geração de dados abertos para prestação de contas institucional e análise em BI.</li>
    <li><strong>Importação PJe:</strong> Leitura automática de planilhas Excel (.xlsx) com mapeamento flexível de colunas e homologação em 1 clique.</li>
</ul>

<!-- PARTE V -->
<div class="page-break"></div>
<h2 class="section-title">Parte V — Painel TV da Sala de Espera</h2>
<p>
    Canal oficial de comunicação pública do Fórum, projetado para máxima acessibilidade visual e auditiva.
</p>

<div class="figure-container">
    <img src="{img_tv}" class="figure-img" alt="Painel TV Chamadas">
    <div class="figure-caption">Figura V.1 — Interface pública da TV com sinal sonoro de campainha, voz sintetizada e histórico.</div>
</div>

<ul>
    <li><strong>Campainha Sonora e Voz:</strong> Sintetização por voz com anúncio de senha, médico perito e sala de exame.</li>
    <li><strong>Destaque de Prioridade:</strong> Exibição do selo de prioridade legal ou superprioridade (80+) em destaque dourado.</li>
    <li><strong>Histórico Lateral:</strong> Relação permanente dos últimos 5 periciados chamados.</li>
</ul>

<!-- PARTE VI -->
<div class="page-break"></div>
<h2 class="section-title">Parte VI — Marco Legal de Prioridades e Anexos</h2>
<p>
    O SGA JFAL está em estrita conformidade com o arcabouço legislativo federal vigente:
</p>

<table>
    <thead>
        <tr>
            <th style="width: 25%;">Diploma Legal</th>
            <th style="width: 45%;">Dispositivo Aplicável</th>
            <th style="width: 30%;">Implementação no SGA</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td><strong>Lei nº 10.048/2000</strong></td>
            <td>Dá prioridade de atendimento às pessoas com deficiência, idosos a partir de 60 anos, gestantes, lactantes, pessoas com crianças de colo e obesos.</td>
            <td>Classificação como <strong>Prioridade Legal</strong> com destaque visual amarelo e priorização no consultório.</td>
        </tr>
        <tr>
            <td><strong>Lei nº 10.741/2003</strong><br><em>(Estatuto da Pessoa Idosa)</em></td>
            <td>Garante prioridade especial e preferencial aos cidadãos com idade igual ou superior a 60 anos.</td>
            <td>Reconhecimento obrigatório na triagem de entrada do balcão.</td>
        </tr>
        <tr>
            <td><strong>Lei nº 13.466/2017</strong><br><em>(Superprioridade 80+)</em></td>
            <td>Institui a prioridade especial para pessoas com mais de 80 anos, com <strong>preferência sobre os demais idosos</strong>.</td>
            <td>Classificação como <strong>Superprioridade (80+)</strong> com destaque dourado e primazia no topo da fila.</td>
        </tr>
        <tr>
            <td><strong>Lei nº 13.146/2015</strong><br><em>(Estatuto da Pessoa com Deficiência)</em></td>
            <td>Assegura acessibilidade e atendimento prioritário em todos os serviços judiciários.</td>
            <td>Inclusão expressa no rol de prioridades da triagem.</td>
        </tr>
    </tbody>
</table>

<div class="callout callout-tip">
    <strong>📄 Modelo da Declaração de Comparecimento A4</strong>
    <p>
        Abaixo apresenta-se o padrão da declaração oficial emitida pelo sistema para fins probatórios de comparecimento perante empregadores, faculdades e órgãos públicos:
    </p>
</div>

<div class="figure-container">
    <img src="{img_declaracao}" class="figure-img" alt="Declaração A4">
    <div class="figure-caption">Figura VI.1 — Declaração Oficial de Comparecimento em conformidade com o padrão institucional do TRF5.</div>
</div>

<div class="callout callout-info" style="margin-top: 20px;">
    <strong>Informações Técnicas e Suporte</strong><br>
    Sistema de Gerenciamento de Atendimento Pericial (SGA)<br>
    Desenvolvido e Mantido pela <strong>Divisão de Tecnologia da Informação (DTI) — Seção Judiciária de Alagoas</strong><br>
    Repositório de Código-Fonte: <span class="badge-code">https://github.com/aerciompr/sga-jfal-dti</span>
</div>

</body>
</html>
"""

manuals = [
    ("Manual_Recepcao_SGA_JFAL.pdf", html_recepcao, "Manual da Recepção e Triagem"),
    ("Manual_Perito_Medico_SGA_JFAL.pdf", html_perito, "Manual do Médico Perito Judicial"),
    ("Manual_Supervisor_Gestor_SGA_JFAL.pdf", html_supervisor, "Manual de Gestão e Supervisão"),
    ("Manual_Geral_Completo_SGA_JFAL.pdf", html_geral, "Manual Geral Completo do SGA"),
]

print("Starting PDF generation with Playwright Chromium...")

with sync_playwright() as p:
    browser = p.chromium.launch()
    for filename, html_content, title in manuals:
        pdf_path = os.path.join(output_dir, filename)
        page = browser.new_page()
        page.set_content(html_content, wait_until="networkidle")
        page.pdf(
            path=pdf_path,
            format="A4",
            print_background=True,
            display_header_footer=True,
            header_template=header_tpl,
            footer_template=footer_tpl,
            margin={"top": "22mm", "bottom": "22mm", "left": "16mm", "right": "16mm"}
        )
        page.close()
        size_kb = os.path.getsize(pdf_path) / 1024
        print(f"Generated: {filename} ({size_kb:.1f} KB) - {title}")
    browser.close()

print("All 4 PDF manuals successfully generated!")
