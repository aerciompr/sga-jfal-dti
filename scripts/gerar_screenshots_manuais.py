import os
from playwright.sync_api import sync_playwright

assets_dir = os.path.abspath(r'c:\sga-dti\assets').replace('\\', '/')
output_dir = os.path.abspath(r'c:\sga-dti\docs\manuais\imagens')

# Helper to create HTML template
def wrap_html(body_content, title="SGA - Justiça Federal"):
    return f"""<!DOCTYPE html>
<html lang="pt-BR" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{title}</title>
    <link rel="stylesheet" href="file:///{assets_dir}/tailwind.css">
    <link rel="stylesheet" href="file:///{assets_dir}/usabilidade.css">
    <style>
        body {{ background-color: #030712; color: #f9fafb; font-family: ui-sans-serif, system-ui, sans-serif; }}
    </style>
</head>
<body class="bg-gray-950 text-white min-h-screen">
{body_content}
</body>
</html>"""

# 1. 02_recepcao_visao_geral.html
html_recepcao_visao = wrap_html(f"""
<header class="bg-gray-900 border-b border-gray-800 px-6 py-4 flex justify-between items-center shadow-md">
    <div class="flex items-center space-x-4">
        <img src="file:///{assets_dir}/catavento-jfal.png" alt="JFAL" class="h-8 w-auto">
        <div>
            <h1 class="text-base font-black tracking-wide text-blue-500 uppercase leading-none">Justiça Federal em Alagoas</h1>
            <p class="text-[10px] text-gray-400 font-semibold tracking-widest uppercase mt-0.5">SGA — Recepção das Perícias Judiciais</p>
        </div>
    </div>
    <div class="flex items-center gap-3">
        <a href="#" class="bg-gray-800 hover:bg-gray-700 text-gray-200 text-xs px-3 py-1.5 rounded-lg border border-gray-700">📺 Painel TV</a>
        <a href="#" class="bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold px-3 py-1.5 rounded-lg">📊 Dashboard</a>
        <span class="text-xs text-gray-400">Olá, <strong class="text-white">Mariana (Recepção)</strong></span>
        <button class="bg-red-950/40 border border-red-900/60 text-red-400 text-xs px-3 py-1.5 rounded-lg">Sair</button>
    </div>
</header>

<main class="max-w-7xl mx-auto p-6 space-y-6">
    <!-- Topo: Filtro de Data e Busca -->
    <div class="flex flex-col sm:flex-row justify-between items-center gap-4 bg-gray-900 p-4 rounded-2xl border border-gray-800">
        <div class="flex items-center gap-2">
            <span class="text-xs font-semibold uppercase text-gray-400">Visualizando Pauta de:</span>
            <input type="date" value="2026-11-06" class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-1.5 text-xs text-white">
            <span class="bg-emerald-950 text-emerald-400 border border-emerald-800 text-[10px] font-bold px-2 py-0.5 rounded">Pauta Homologada</span>
        </div>
        <div class="w-full sm:w-96">
            <input type="text" placeholder="Buscar por Nome, Processo ou CPF..." class="w-full bg-gray-800 border border-gray-700 rounded-xl px-4 py-2 text-xs text-white focus:outline-none focus:border-blue-500">
        </div>
    </div>

    <!-- Cards dos Peritos -->
    <div class="bg-gray-900 p-6 rounded-2xl border border-gray-800 shadow-xl">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-semibold uppercase text-blue-400 tracking-wider flex items-center gap-2">
                <span>🧑‍⚕️</span> Salas & Filas dos Peritos Médicos
            </h2>
            <span class="text-xs text-gray-500 font-medium">Tempo real</span>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- Card Dr. Carlos -->
            <div class="bg-gray-950 p-4 rounded-xl border border-gray-800 space-y-3">
                <div class="flex justify-between items-start">
                    <div>
                        <div class="font-bold text-white text-xs">Dr. Carlos Eduardo Silveira</div>
                        <span class="text-[10px] text-gray-400 font-medium mt-0.5 block">🚪 Sala 02 — Perícias</span>
                        <span class="text-[9px] font-semibold block mt-0.5 text-emerald-400">🏃 Fila: Ordem de Chegada</span>
                        <span class="text-[9px] font-semibold block mt-0.5 text-amber-400">♿ Fila com Prioridade Ativa</span>
                    </div>
                    <div class="text-right">
                        <span class="bg-blue-950/70 border border-blue-900/60 text-blue-400 text-[10px] font-bold px-2 py-0.5 rounded-full block">
                            Fila: 3
                        </span>
                        <span class="text-[9px] text-amber-400 font-bold block mt-0.5">⭐ 1 prioritário</span>
                    </div>
                </div>
                <div class="text-[11px] p-2.5 rounded-lg border bg-blue-950/30 border-blue-900/40 text-blue-300">
                    <div class="font-bold text-blue-400 flex items-center justify-between">
                        <span>🟢 Em Exame: PE-01</span>
                        <span class="text-[9px] text-gray-400">08:30:10</span>
                    </div>
                    <div class="truncate text-white text-[11px] font-medium mt-0.5">José Oliveira Santos</div>
                </div>
            </div>

            <!-- Card Dra. Renata -->
            <div class="bg-gray-950 p-4 rounded-xl border border-gray-800 space-y-3">
                <div class="flex justify-between items-start">
                    <div>
                        <div class="font-bold text-white text-xs">Dra. Renata Barros Cavalcanti</div>
                        <span class="text-[10px] text-gray-400 font-medium mt-0.5 block">🚪 Sala 03 — Perícias</span>
                        <span class="text-[9px] font-semibold block mt-0.5 text-amber-400">🕒 Fila: Horário PJe</span>
                        <span class="text-[9px] font-semibold block mt-0.5 text-gray-500">⚪ Prioridade Opcional</span>
                    </div>
                    <div class="text-right">
                        <span class="bg-blue-950/70 border border-blue-900/60 text-blue-400 text-[10px] font-bold px-2 py-0.5 rounded-full block">
                            Fila: 2
                        </span>
                    </div>
                </div>
                <div class="text-[11px] p-2.5 rounded-lg border bg-gray-900/40 border-gray-800/60 text-gray-400">
                    <span>⚪ Sala livre / Aguardando próxima chamada</span>
                </div>
            </div>

            <!-- Card Dr. Fernando -->
            <div class="bg-gray-950 p-4 rounded-xl border border-gray-800 space-y-3">
                <div class="flex justify-between items-start">
                    <div>
                        <div class="font-bold text-white text-xs">Dr. Fernando Antônio Mendonça</div>
                        <span class="text-[10px] text-gray-400 font-medium mt-0.5 block">🚪 Sala 04 — Perícias</span>
                        <span class="text-[9px] font-semibold block mt-0.5 text-emerald-400">🏃 Fila: Ordem de Chegada</span>
                    </div>
                    <div class="text-right">
                        <span class="bg-blue-950/70 border border-blue-900/60 text-blue-400 text-[10px] font-bold px-2 py-0.5 rounded-full block">
                            Fila: 4
                        </span>
                    </div>
                </div>
                <div class="text-[11px] p-2.5 rounded-lg border bg-blue-950/30 border-blue-900/40 text-blue-300">
                    <div class="font-bold text-blue-400 flex items-center justify-between">
                        <span>🔔 Chamando: PE-02</span>
                        <span class="text-[9px] text-gray-400">08:42:00</span>
                    </div>
                    <div class="truncate text-white text-[11px] font-medium mt-0.5">Ana Lúcia dos Santos</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabela 1: Presentes (Fila de Espera) -->
    <div class="bg-gray-900 p-6 rounded-2xl border border-gray-800 shadow-xl">
        <h2 class="text-sm font-semibold uppercase text-emerald-400 tracking-wider mb-4 flex justify-between items-center">
            <span>Aguardando Chamada (Fila de Espera Ativa) — 3 periciados</span>
            <span class="text-xs text-gray-400 font-normal">Sincronizado com os consultórios</span>
        </h2>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-gray-800 text-xs text-gray-400 uppercase">
                        <th class="pb-2">Posição na Fila</th>
                        <th class="pb-2">Senha</th>
                        <th class="pb-2">Periciado / Processo / CPF</th>
                        <th class="pb-2">Médico Perito</th>
                        <th class="pb-2">Chegada</th>
                        <th class="pb-2 text-right">Ação</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800/50 text-xs">
                    <tr class="hover:bg-gray-850/40 transition">
                        <td class="py-3">
                            <div class="font-bold text-amber-300">1º lugar</div>
                            <span class="text-emerald-400 font-semibold text-[10px]">Próximo (0 na frente)</span>
                        </td>
                        <td class="py-3 font-mono font-bold text-blue-400 text-sm">PE-03</td>
                        <td class="py-3">
                            <div class="font-semibold text-white flex items-center gap-1.5 flex-wrap">
                                <span>Maria das Graças Silva</span>
                                <span class="bg-amber-950/80 text-amber-300 border border-amber-600/60 text-[9px] px-1.5 py-0.2 rounded-full font-black uppercase">⭐ 80+ Superprioridade</span>
                            </div>
                            <div class="text-[10px] text-gray-500 font-mono">0801234-56.2026.4.05.8000 <span class="text-emerald-400 ml-1.5">| CPF: ***.456.789-**</span></div>
                        </td>
                        <td class="py-3 text-gray-300">Dr. Carlos Eduardo Silveira</td>
                        <td class="py-3 text-gray-400">08:15:20</td>
                        <td class="py-3 text-right">
                            <button class="bg-indigo-950/70 border border-indigo-700/60 text-indigo-300 text-xs px-2.5 py-1 rounded">🖨️ Declaração</button>
                        </td>
                    </tr>
                    <tr class="hover:bg-gray-850/40 transition">
                        <td class="py-3">
                            <div class="font-bold text-white">2º lugar</div>
                            <span class="text-gray-400 text-[10px]">1 pessoa na frente</span>
                        </td>
                        <td class="py-3 font-mono font-bold text-blue-400 text-sm">PE-04</td>
                        <td class="py-3">
                            <div class="font-semibold text-white flex items-center gap-1.5 flex-wrap">
                                <span>Sebastião Bezerra da Costa</span>
                                <span class="bg-sky-950/80 text-sky-300 border border-sky-600/60 text-[9px] px-1.5 py-0.2 rounded-full font-bold uppercase">♿ Prioridade (PCD)</span>
                            </div>
                            <div class="text-[10px] text-gray-500 font-mono">0802998-11.2026.4.05.8000 <span class="text-emerald-400 ml-1.5">| CPF: ***.889.112-**</span></div>
                        </td>
                        <td class="py-3 text-gray-300">Dr. Carlos Eduardo Silveira</td>
                        <td class="py-3 text-gray-400">08:22:15</td>
                        <td class="py-3 text-right">
                            <button class="bg-indigo-950/70 border border-indigo-700/60 text-indigo-300 text-xs px-2.5 py-1 rounded">🖨️ Declaração</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</main>
""")

# 2. 03_confirmar_presenca.html (Focus on check-in action)
html_confirmar_presenca = wrap_html(f"""
<div class="max-w-4xl mx-auto p-8 mt-12 bg-gray-900 border border-gray-800 rounded-3xl shadow-2xl">
    <div class="flex items-center justify-between pb-4 border-b border-gray-800 mb-6">
        <div>
            <span class="text-xs uppercase font-bold text-emerald-400 tracking-wider">Passo Fundamental na Recepção</span>
            <h2 class="text-xl font-black text-white mt-0.5">Confirmar Presença do Periciado na Pauta</h2>
        </div>
        <span class="bg-emerald-950 text-emerald-300 border border-emerald-800 text-xs px-3 py-1 rounded-full font-bold">1 Clique</span>
    </div>

    <div class="space-y-4">
        <p class="text-sm text-gray-300 leading-relaxed">
            Ao se apresentar no balcão de atendimento, o periciado é localizado instantaneamente pelo nome ou CPF. A atendente confere o documento com foto e clica no botão verde <strong class="text-emerald-400">Confirmar Chegada</strong>.
        </p>

        <!-- Tabela demonstrativa com destaque no botão -->
        <div class="overflow-x-auto rounded-xl border border-gray-800 bg-gray-950 p-4">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-gray-800 text-gray-400 uppercase">
                        <th class="pb-2">Periciado / Processo</th>
                        <th class="pb-2">Horário Agendado</th>
                        <th class="pb-2">Perito Designado</th>
                        <th class="pb-2 text-right">Ação de Triagem</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="bg-gray-900/60 border-l-4 border-emerald-500">
                        <td class="py-3 pl-3">
                            <strong class="text-white text-sm block">Maria das Graças Silva</strong>
                            <span class="text-gray-400 font-mono">0801234-56.2026.4.05.8000 | CPF: 123.456.789-00</span>
                        </td>
                        <td class="py-3">
                            <span class="bg-blue-950 border border-blue-900 text-blue-300 text-xs font-mono font-bold px-2 py-0.5 rounded">🕒 08:30</span>
                        </td>
                        <td class="py-3 text-gray-300">Dr. Carlos Eduardo Silveira</td>
                        <td class="py-3 text-right">
                            <button class="bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs px-4 py-2 rounded-xl shadow-lg shadow-emerald-900/50 transform scale-105 border border-emerald-400 flex items-center gap-1.5 ml-auto">
                                ✓ Confirmar Chegada
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Banner com o resultado gerado -->
        <div class="bg-emerald-950/40 border border-emerald-800/60 p-4 rounded-2xl flex items-center justify-between">
            <div>
                <span class="text-[10px] uppercase font-bold text-emerald-400 tracking-wider">Senha Gerada pelo Sistema</span>
                <div class="text-2xl font-black text-white font-mono mt-0.5">Senha: PE-03</div>
                <span class="text-xs text-gray-300 block mt-0.5">Paciente inserido automaticamente na fila de espera do perito com timestamp oficial.</span>
            </div>
            <div class="text-right">
                <span class="text-xs bg-gray-900 border border-gray-700 text-gray-300 px-3 py-1.5 rounded-lg block font-mono">Entrada: 08:15:20</span>
            </div>
        </div>
    </div>
</div>
""")

# 3. 04_modal_prioridade_legal.html
html_modal_prio = wrap_html(f"""
<div class="min-h-screen flex items-center justify-center p-6 bg-gray-950/90 backdrop-blur-sm">
    <div class="bg-gray-900 border border-gray-800 rounded-3xl shadow-2xl max-w-xl w-full overflow-hidden">
        <div class="p-6 border-b border-gray-800 flex justify-between items-center bg-gray-900/50">
            <h3 class="text-sm font-bold uppercase tracking-wider text-blue-500 flex items-center gap-2">
                🔎 Detalhes do Agendamento & Gestão de Prioridade
            </h3>
            <button class="text-gray-400 hover:text-white text-lg">&times;</button>
        </div>
        <div class="p-6 space-y-4 text-sm text-gray-300">
            <!-- Box de Fila em Destaque -->
            <div class="bg-blue-950/40 border border-blue-900/60 p-4 rounded-2xl flex items-center justify-between">
                <div>
                    <span class="block text-[10px] uppercase font-bold text-blue-400 tracking-wider">Posição na Fila do Médico</span>
                    <div class="text-lg font-black text-white mt-0.5">1º lugar da fila</div>
                    <span class="text-xs text-gray-200 block mt-0.5 font-medium">👉 Próximo a ser chamado (0 pessoas na frente)</span>
                    <span class="text-[10px] text-gray-400 block mt-0.5 italic">🏃 Fila do médico organizada por Ordem de Chegada • ♿ Perito priorizando preferenciais</span>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <span class="block text-[10px] uppercase font-bold text-gray-500 tracking-wider">Nome do Periciado</span>
                    <strong class="text-base text-white font-black block mt-0.5">Maria das Graças Silva (82 anos)</strong>
                </div>
                <div>
                    <span class="block text-[10px] uppercase font-bold text-gray-500 tracking-wider">CPF</span>
                    <span class="font-mono text-white block mt-0.5">***.456.789-**</span>
                </div>
                <div>
                    <span class="block text-[10px] uppercase font-bold text-gray-500 tracking-wider">Número do Processo</span>
                    <span class="font-mono text-white block mt-0.5">0801234-56.2026.4.05.8000</span>
                </div>
                <div>
                    <span class="block text-[10px] uppercase font-bold text-gray-500 tracking-wider">Médico Perito</span>
                    <span class="text-white font-medium block mt-0.5">Dr. Carlos Eduardo Silveira</span>
                </div>
                <div>
                    <span class="block text-[10px] uppercase font-bold text-gray-500 tracking-wider">Status Atual</span>
                    <span class="bg-emerald-950 text-emerald-400 text-[10px] px-2.5 py-0.5 rounded-full border border-emerald-900/50 uppercase font-bold inline-block mt-0.5">Aguardando</span>
                </div>

                <!-- SELETOR DE PRIORIDADE LEGAL (1 CLIQUE) -->
                <div class="col-span-2 pt-4 border-t border-gray-800">
                    <span class="block text-xs uppercase font-extrabold text-amber-400 tracking-wider mb-2">
                        ⚖️ Classificação de Prioridade Legal (Selecione com 1 clique)
                    </span>
                    <div class="flex items-center gap-2 flex-wrap">
                        <button type="button" class="px-3 py-1.5 text-xs rounded-xl border border-gray-700 bg-gray-800 text-gray-400 hover:text-white transition">
                            ⚪ Normal (Padrão)
                        </button>
                        <button type="button" class="px-3 py-1.5 text-xs rounded-xl border border-sky-600/40 bg-sky-950/60 text-sky-400 hover:bg-sky-900/60 transition">
                            ♿ Prioridade (60+, PCD, Gestante)
                        </button>
                        <button type="button" class="px-3 py-1.5 text-xs rounded-xl border border-amber-400 bg-amber-900 text-white font-black transition shadow-lg shadow-amber-950 flex items-center gap-1.5">
                            ⭐ Superprioridade (80+ / Acamado)
                        </button>
                    </div>
                    <span class="text-[11px] text-gray-400 block mt-2">
                        Atualizado instantaneamente nos painéis do Perito, da Recepção e no anúncio sonoro da TV.
                    </span>
                </div>
            </div>
        </div>
        <div class="p-4 border-t border-gray-800 bg-gray-950/40 flex justify-between items-center">
            <button class="bg-indigo-950/80 border border-indigo-700/80 text-indigo-300 text-xs font-semibold px-4 py-2 rounded-xl flex items-center gap-1.5">
                🖨️ Declaração de Comparecimento A4
            </button>
            <button class="bg-gray-850 text-white text-xs font-semibold px-4 py-2 rounded-xl border border-gray-700">Fechar</button>
        </div>
    </div>
</div>
""")

# 4. 06_perito_selecao_sala.html
html_perito_sala = wrap_html(f"""
<div class="max-w-2xl mx-auto p-8 mt-16 bg-gray-900 border border-gray-800 rounded-3xl shadow-2xl space-y-6">
    <div class="text-center pb-4 border-b border-gray-800">
        <img src="file:///{assets_dir}/catavento-jfal.png" alt="JFAL" class="h-10 mx-auto mb-2">
        <h2 class="text-lg font-black text-white uppercase tracking-wider">Abertura de Consultório & Seleção de Sala</h2>
        <p class="text-xs text-gray-400 mt-1">Trava de segurança anti-conflito para atendimento pericial</p>
    </div>

    <div class="bg-blue-950/30 border border-blue-900/50 p-4 rounded-2xl text-xs text-blue-300 leading-relaxed">
        <strong>⚠️ Regra de Segurança:</strong> Cada sala só pode ser confirmada por um médico por vez. Ao confirmar a sala no início do seu turno, ela fica travada exclusivamente para suas chamadas, evitando que outro perito chame pacientes para a mesma porta.
    </div>

    <div class="space-y-4">
        <div>
            <label class="block text-xs uppercase font-bold text-gray-400 mb-1.5">Identificação do Perito Logado</label>
            <input type="text" value="Dr. Carlos Eduardo Silveira (CRM/AL 4321)" disabled class="w-full bg-gray-800 border border-gray-700 rounded-xl px-4 py-2.5 text-xs text-white font-semibold">
        </div>

        <div>
            <label class="block text-xs uppercase font-bold text-gray-400 mb-1.5">Selecione o Consultório Médico</label>
            <select class="w-full bg-gray-800 border border-gray-700 rounded-xl px-4 py-2.5 text-xs text-white font-bold">
                <option value="Sala 2" selected>🚪 Sala 02 — Disponível</option>
                <option value="Sala 3" disabled>🚪 Sala 03 — Ocupada por Dra. Renata Barros</option>
                <option value="Sala 4">🚪 Sala 04 — Disponível</option>
                <option value="Sala 5">🚪 Sala 05 — Disponível</option>
            </select>
        </div>

        <button class="w-full bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-sm py-3.5 px-4 rounded-2xl shadow-lg transition flex items-center justify-center gap-2">
            🔒 Confirmar e Travar Sala 02 para Meus Atendimentos
        </button>
    </div>
</div>
""")

# 5. 07_perito_painel_principal.html
html_perito_painel = wrap_html(f"""
<header class="bg-gray-900 border-b border-gray-800 px-6 py-4 flex justify-between items-center shadow-md">
    <div class="flex items-center space-x-4">
        <img src="file:///{assets_dir}/catavento-jfal.png" alt="JFAL" class="h-8 w-auto">
        <div>
            <h1 class="text-base font-black tracking-wide text-blue-500 uppercase leading-none">Justiça Federal em Alagoas</h1>
            <p class="text-[10px] text-gray-400 font-semibold tracking-widest uppercase mt-0.5">Painel de Atendimento do Perito Médico</p>
        </div>
    </div>
    <div class="flex items-center gap-3">
        <button class="bg-gray-800 border border-gray-700 text-emerald-400 text-xs px-3 py-1.5 rounded-lg flex items-center gap-1 font-semibold">
            🔊 Som Ativo
        </button>
        <span class="text-xs text-gray-300">Perito: <strong class="text-white">Dr. Carlos Eduardo</strong> • <span class="text-emerald-400 font-bold">Sala 02</span></span>
        <button class="bg-red-950/40 border border-red-900/60 text-red-400 text-xs px-3 py-1.5 rounded-lg">Sair & Liberar Sala</button>
    </div>
</header>

<main class="max-w-7xl mx-auto p-6 space-y-6">
    <!-- Card Principal: Periciado Atual / Chamar Próximo -->
    <div class="bg-gray-900 border border-gray-800 rounded-3xl p-8 shadow-2xl text-center relative">
        <span class="text-xs uppercase font-extrabold text-blue-400 tracking-wider block mb-2">Periciado em Atendimento</span>
        
        <div class="flex items-center justify-center gap-2 mb-2 flex-wrap">
            <span class="text-4xl font-black text-white">Maria das Graças Silva</span>
            <span class="bg-amber-950/80 text-amber-300 border border-amber-600/60 text-xs px-2.5 py-1 rounded-full font-black uppercase tracking-wider">⭐ Superprioridade (80+)</span>
        </div>
        <div class="text-xl font-bold text-blue-400 font-mono mb-1">Senha: PE-03</div>
        <div class="text-xs text-gray-400 mb-6 font-mono">Processo: 0801234-56.2026.4.05.8000 | 7ª Vara Federal</div>

        <!-- Ações do Perito -->
        <div class="flex flex-col sm:flex-row flex-wrap gap-3 max-w-2xl mx-auto justify-center mb-6">
            <button class="bg-amber-600 hover:bg-amber-700 text-white font-semibold py-3 px-4 rounded-xl text-xs transition">
                📢 Chamar Novamente (1)
            </button>
            <button class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-5 rounded-xl text-xs transition shadow-lg">
                ▶ Iniciar Perícia Médica
            </button>
            <button class="bg-gray-800 hover:bg-gray-750 text-gray-300 font-semibold py-3 px-4 rounded-xl text-xs border border-gray-700 transition" title="Desce o paciente 2 posições na fila caso ele demore a entrar">
                ⏳ Ausente (Tolerar 2 posições)
            </button>
            <button class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3 px-5 rounded-xl text-xs transition">
                ✓ Concluir Perícia
            </button>
        </div>

        <a href="#" class="bg-indigo-950/70 hover:bg-indigo-900 border border-indigo-700/60 text-indigo-300 font-semibold py-2 px-4 rounded-xl text-xs inline-flex items-center gap-2">
            🖨️ Emitir Declaração de Comparecimento A4
        </a>
    </div>

    <!-- Barra de Ferramentas: Critérios e Prioridade -->
    <div class="flex items-center justify-between flex-wrap gap-4 bg-gray-900 border border-gray-800 p-4 rounded-2xl shadow-xl">
        <div class="flex items-center gap-3">
            <span class="text-xs uppercase font-bold text-gray-400">Critério da Fila:</span>
            <div class="inline-flex rounded-lg border border-gray-750 bg-gray-950 p-0.5">
                <button class="px-3 py-1.5 text-xs font-bold rounded-md bg-blue-600 text-white">🏃 Ordem de Chegada (Padrão)</button>
                <button class="px-3 py-1.5 text-xs font-medium rounded-md text-gray-400 hover:text-white">🕒 Horário PJe</button>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <span class="text-xs uppercase font-bold text-gray-400">Priorizar Fila:</span>
            <div class="inline-flex rounded-lg border border-gray-750 bg-gray-950 p-0.5">
                <button class="px-3 py-1.5 text-xs font-medium rounded-md text-gray-400">⚪ Não (Ordem Normal)</button>
                <button class="px-3 py-1.5 text-xs font-bold rounded-md bg-amber-600 text-white shadow-md">♿ Sim (Prioridades no Topo)</button>
            </div>
        </div>

        <div class="text-xs text-gray-400 flex items-center gap-1.5">
            <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span> Fila sincronizada com a Recepção
        </div>
    </div>
</main>
""")

# 6. 09_perito_alerta_toast.html
html_perito_toast = wrap_html(f"""
<div class="min-h-screen relative p-8">
    <div class="max-w-4xl mx-auto bg-gray-900 border border-gray-800 rounded-3xl p-8 opacity-40">
        <h2 class="text-xl font-bold text-white mb-4">Painel do Perito Médico (Em Espera)</h2>
        <p class="text-sm text-gray-400">O perito aguarda no consultório. Quando um paciente confirma presença na recepção, um aviso sonoro discreto toca e um cartão flutuante surge no canto superior direito.</p>
    </div>

    <!-- TOAST NOTIFICATION EM DESTAQUE -->
    <div class="fixed top-8 right-8 z-50 max-w-sm w-full">
        <div class="bg-gray-900 border-2 border-emerald-500 text-white shadow-2xl rounded-2xl p-4 flex items-start gap-3 transform scale-105 transition-all">
            <div class="text-2xl mt-0.5">🔔</div>
            <div class="flex-1 min-w-0">
                <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-400 block">Novo Periciado na Recepção</span>
                <div class="text-sm font-bold truncate text-white mt-0.5">Sebastião Bezerra da Costa</div>
                <div class="text-xs text-gray-400 mt-1 flex items-center gap-2">
                    <span class="font-mono text-blue-400 font-semibold bg-gray-800 px-1.5 py-0.5 rounded">Senha: PE-04</span>
                    <span class="bg-sky-950 text-sky-300 border border-sky-800 text-[10px] px-1.5 py-0.5 rounded font-bold">♿ Prioridade (PCD)</span>
                    <span class="bg-gray-800 text-gray-300 px-1.5 py-0.5 rounded text-[10px] font-mono">🕒 08:45</span>
                </div>
            </div>
            <button class="text-gray-400 hover:text-white text-base leading-none">&times;</button>
        </div>
        <div class="mt-2 text-right">
            <span class="text-[11px] text-gray-400 italic">Dispara sinal sonoro suave de 2 tons (Web Audio)</span>
        </div>
    </div>
</div>
""")

# 7. 11_dashboard_executivo.html
html_dashboard = wrap_html(f"""
<header class="bg-gray-900 border-b border-gray-800 px-6 py-4 flex justify-between items-center shadow-md">
    <div class="flex items-center space-x-4">
        <img src="file:///{assets_dir}/catavento-jfal.png" alt="JFAL" class="h-8 w-auto">
        <div>
            <h1 class="text-base font-black tracking-wide text-blue-500 uppercase leading-none">Justiça Federal em Alagoas</h1>
            <p class="text-[10px] text-gray-400 font-semibold tracking-widest uppercase mt-0.5">Painel Executivo & Gestão de Produtividade</p>
        </div>
    </div>
    <div class="flex items-center gap-3">
        <a href="#" class="bg-emerald-950/80 hover:bg-emerald-900 border border-emerald-700/80 text-emerald-300 text-xs font-semibold px-3 py-1.5 rounded-xl transition flex items-center gap-1.5">
            📥 Exportar Relatório (.CSV)
        </a>
        <span class="text-xs text-gray-400">Usuário: <strong class="text-white">Supervisor da Vara</strong></span>
    </div>
</header>

<main class="max-w-7xl mx-auto p-6 space-y-6">
    <!-- Seção de Indicadores Executivos do Dia -->
    <section class="bg-gray-900 border border-gray-800 rounded-3xl p-6 shadow-2xl space-y-6">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 pb-4 border-b border-gray-800">
            <div>
                <h2 class="text-lg font-black text-white flex items-center gap-2">
                    <span>📊</span> Indicadores Estratégicos das Perícias Judiciais
                </h2>
                <p class="text-xs text-gray-400 mt-0.5">Dados consolidados do Fórum em 06/11/2026</p>
            </div>
            <div class="flex items-center gap-2">
                <input type="date" value="2026-11-06" class="bg-gray-950 border border-gray-700 rounded-xl px-3 py-1.5 text-xs text-white font-mono">
                <button class="bg-blue-600 text-white text-xs font-semibold px-3 py-1.5 rounded-xl">Filtrar</button>
            </div>
        </div>

        <!-- 4 Cards de Topo (KPIs) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-gray-950 p-4 rounded-2xl border border-gray-800">
                <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400 block">Pauta & Comparecimento</span>
                <div class="text-2xl font-black text-white mt-1">28 <span class="text-sm font-normal text-gray-400">/ 32</span></div>
                <div class="text-[11px] text-emerald-400 mt-1 font-semibold">
                    <span>🟢 87.5% de presença</span>
                </div>
            </div>

            <div class="bg-gray-950 p-4 rounded-2xl border border-gray-800">
                <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400 block">Perícias Realizadas</span>
                <div class="text-2xl font-black text-emerald-400 mt-1">19 concluídas</div>
                <div class="text-[11px] text-gray-400 mt-1 flex items-center gap-2">
                    <span>Aguardando: <strong class="text-white">6</strong></span>
                    <span>•</span>
                    <span>Em exame: <strong class="text-blue-400">3</strong></span>
                </div>
            </div>

            <div class="bg-gray-950 p-4 rounded-2xl border border-gray-800">
                <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400 block">Tempo Médio de Espera</span>
                <div class="text-2xl font-black text-amber-400 mt-1">18 <span class="text-xs font-normal text-gray-400">minutos</span></div>
                <span class="text-[10px] text-gray-400 mt-1 block">Chegada na recepção até o exame</span>
            </div>

            <div class="bg-gray-950 p-4 rounded-2xl border border-gray-800">
                <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400 block">Duração & Absenteísmo</span>
                <div class="text-2xl font-black text-blue-400 mt-1">24 <span class="text-xs font-normal text-gray-400">min/exame</span></div>
                <div class="text-[11px] text-rose-400 mt-1 font-medium">
                    <span>Absenteísmo: 12.5% (4 faltas)</span>
                </div>
            </div>
        </div>

        <!-- Tabela de Produtividade dos Médicos -->
        <div>
            <h3 class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-3">Produtividade Individual por Médico Perito</h3>
            <div class="overflow-x-auto rounded-xl border border-gray-800">
                <table class="w-full text-left text-xs text-gray-300">
                    <thead class="bg-gray-950 text-gray-400 uppercase font-bold border-b border-gray-800">
                        <tr>
                            <th class="py-2.5 px-3">Médico Perito</th>
                            <th class="py-2.5 px-3">Sala</th>
                            <th class="py-2.5 px-3 text-center">Agendados</th>
                            <th class="py-2.5 px-3 text-center">Presentes</th>
                            <th class="py-2.5 px-3 text-center">Concluídos</th>
                            <th class="py-2.5 px-3 text-center">Na Fila</th>
                            <th class="py-2.5 px-3 text-center">Ausentes</th>
                            <th class="py-2.5 px-3 text-right">Tempo Médio</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/60 bg-gray-950/40">
                        <tr>
                            <td class="py-2.5 px-3 font-semibold text-white">Dr. Carlos Eduardo Silveira</td>
                            <td class="py-2.5 px-3 text-gray-400">Sala 02</td>
                            <td class="py-2.5 px-3 text-center">12</td>
                            <td class="py-2.5 px-3 text-center text-emerald-400 font-bold">11 (91%)</td>
                            <td class="py-2.5 px-3 text-center text-blue-400 font-bold">8</td>
                            <td class="py-2.5 px-3 text-center">2</td>
                            <td class="py-2.5 px-3 text-center text-rose-400">1</td>
                            <td class="py-2.5 px-3 text-right font-mono">22 min</td>
                        </tr>
                        <tr>
                            <td class="py-2.5 px-3 font-semibold text-white">Dra. Renata Barros Cavalcanti</td>
                            <td class="py-2.5 px-3 text-gray-400">Sala 03</td>
                            <td class="py-2.5 px-3 text-center">10</td>
                            <td class="py-2.5 px-3 text-center text-emerald-400 font-bold">9 (90%)</td>
                            <td class="py-2.5 px-3 text-center text-blue-400 font-bold">6</td>
                            <td class="py-2.5 px-3 text-center">2</td>
                            <td class="py-2.5 px-3 text-center text-rose-400">1</td>
                            <td class="py-2.5 px-3 text-right font-mono">26 min</td>
                        </tr>
                        <tr>
                            <td class="py-2.5 px-3 font-semibold text-white">Dr. Fernando Antônio Mendonça</td>
                            <td class="py-2.5 px-3 text-gray-400">Sala 04</td>
                            <td class="py-2.5 px-3 text-center">10</td>
                            <td class="py-2.5 px-3 text-center text-emerald-400 font-bold">8 (80%)</td>
                            <td class="py-2.5 px-3 text-center text-blue-400 font-bold">5</td>
                            <td class="py-2.5 px-3 text-center">2</td>
                            <td class="py-2.5 px-3 text-center text-rose-400">2</td>
                            <td class="py-2.5 px-3 text-right font-mono">25 min</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</main>
""")

# 8. 13_gerenciamento_pauta.html
html_pauta = wrap_html(f"""
<header class="bg-gray-900 border-b border-gray-800 px-6 py-4 flex justify-between items-center shadow-md">
    <div class="flex items-center space-x-4">
        <img src="file:///{assets_dir}/catavento-jfal.png" alt="JFAL" class="h-8 w-auto">
        <div>
            <h1 class="text-base font-black tracking-wide text-blue-500 uppercase leading-none">Justiça Federal em Alagoas</h1>
            <p class="text-[10px] text-gray-400 font-semibold tracking-widest uppercase mt-0.5">Gerenciador de Pauta Judicial & Homologação</p>
        </div>
    </div>
    <div class="flex items-center gap-3">
        <span class="text-xs text-gray-400">Módulo: <strong class="text-white">Supervisor de Perícias</strong></span>
    </div>
</header>

<main class="max-w-5xl mx-auto p-8 space-y-6">
    <div class="bg-gray-900 border border-gray-800 rounded-3xl p-6 shadow-2xl space-y-6">
        <div class="pb-4 border-b border-gray-800">
            <span class="text-xs uppercase font-bold text-blue-400 tracking-wider">Carga de Dados do PJe</span>
            <h2 class="text-xl font-black text-white mt-1">Importação de Pauta em 1 Passo (.xlsx)</h2>
            <p class="text-xs text-gray-400 mt-1">O sistema extrai automaticamente periciados, processos, peritos e os horários agendados do PJe.</p>
        </div>

        <div class="border-2 border-dashed border-gray-700 hover:border-blue-500 rounded-2xl p-8 text-center bg-gray-950/60 transition">
            <div class="text-4xl mb-2">📁</div>
            <h3 class="text-sm font-bold text-white mb-1">Arraste a planilha de pauta (.xlsx) do PJe aqui</h3>
            <p class="text-xs text-gray-500 mb-4">Suporta arquivos gerados pelo sistema PJe do TRF5</p>
            <button class="bg-blue-600 hover:bg-blue-700 text-white font-semibold text-xs py-2 px-4 rounded-xl">
                Selecionar Arquivo no Computador
            </button>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="bg-gray-950 p-4 rounded-xl border border-gray-800">
                <span class="text-[10px] uppercase font-bold text-gray-400 block">Janela de Importação</span>
                <select class="w-full bg-gray-800 border border-gray-700 rounded-lg p-2 text-xs text-white mt-1">
                    <option selected>Somente o Dia Selecionado (Hoje)</option>
                    <option>Próximos 3 Dias</option>
                    <option>Mês Inteiro</option>
                </select>
            </div>
            <div class="bg-gray-950 p-4 rounded-xl border border-gray-800 flex items-center justify-between">
                <div>
                    <span class="text-xs font-bold text-white block">Homologação Automática</span>
                    <span class="text-[10px] text-gray-400">Libera a pauta imediatamente para a recepção</span>
                </div>
                <input type="checkbox" checked class="w-4 h-4 text-blue-600 rounded">
            </div>
        </div>

        <button class="w-full bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-sm py-3 px-4 rounded-2xl shadow-lg transition">
            🚀 Processar e Importar Pauta para o SGA
        </button>
    </div>
</main>
""")

# 15_alterar_senha_obrigatorio.html
html_alterar_senha = wrap_html(f"""
<div class="min-h-screen flex items-center justify-center p-6 bg-gray-950">
    <div class="max-w-md w-full bg-gray-900 rounded-2xl border border-gray-800 p-8 shadow-2xl relative">
        <div class="text-center mb-6 flex flex-col items-center">
            <div class="bg-white p-3 rounded-xl shadow-md border border-slate-200 mb-3 inline-block">
                <img src="file:///{assets_dir}/logo-jfal-completo.png" alt="Justiça Federal" class="h-10 object-contain">
            </div>
            <h1 class="text-lg font-black text-blue-500 uppercase leading-none">Segurança da Informação</h1>
            <p class="text-[9px] text-gray-400 uppercase tracking-widest font-semibold mt-1">Alteração de Senha Obrigatória</p>
        </div>

        <div class="bg-blue-950/40 border border-blue-900/60 rounded-xl p-4 mb-6">
            <p class="text-xs text-blue-300 leading-relaxed">
                Olá, <strong class="text-white">Dr. Carlos Eduardo Silveira</strong>. Identificamos que este é o seu primeiro acesso com a credencial inicial do sistema.
                Para garantir a integridade dos atendimentos periciais, defina agora sua nova senha pessoal individual (mínimo de 12 caracteres).
            </p>
        </div>

        <form class="space-y-4">
            <div>
                <label class="block text-xs text-gray-400 uppercase font-semibold mb-1">Nova Senha Individual</label>
                <input type="password" value="••••••••••••••••" readonly class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white">
                <span class="text-[10px] text-gray-500 mt-1 block">Mínimo de 12 caracteres (diferente do CPF e do login).</span>
            </div>

            <div>
                <label class="block text-xs text-gray-400 uppercase font-semibold mb-1">Confirme a Nova Senha</label>
                <input type="password" value="••••••••••••••••" readonly class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white">
            </div>

            <button type="button" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold text-sm py-2.5 rounded-lg shadow transition">
                Salvar Nova Senha Institucional
            </button>
        </form>
    </div>
</div>
""")

# 16_usuarios_gerenciamento.html
html_usuarios = wrap_html(f"""
<header class="bg-gray-900 border-b border-gray-800 px-6 py-4 flex items-center justify-between shadow-md">
    <div class="flex items-center space-x-3">
        <img src="file:///{assets_dir}/catavento-jfal.png" alt="JFAL" class="h-8 w-auto">
        <div>
            <h1 class="text-xs font-black tracking-wide text-blue-500 uppercase leading-none">Justiça Federal em Alagoas</h1>
            <p class="text-[8px] text-gray-400 font-semibold tracking-widest uppercase mt-0.5">SGA — Gestão de Perfis e Segurança</p>
        </div>
        <span class="bg-gray-800 text-gray-400 text-[10px] px-2.5 py-0.5 rounded-full uppercase font-semibold">Administrador</span>
    </div>
    <div class="flex items-center space-x-3">
        <a href="#" class="text-xs text-gray-400 border border-gray-800 bg-gray-950/30 px-3 py-1.5 rounded-lg">Painel Principal</a>
        <a href="#" class="text-xs text-emerald-400 border border-emerald-900/60 bg-emerald-950/30 px-3 py-1.5 rounded-lg">Recepção</a>
        <span class="text-xs text-gray-400">Olá, <strong class="text-white">Admin DTI</strong></span>
        <button class="bg-red-950/40 border border-red-900/60 text-red-400 text-xs px-3 py-1.5 rounded-lg">Sair</button>
    </div>
</header>

<main class="max-w-6xl mx-auto p-6 grid grid-cols-1 md:grid-cols-3 gap-6">
    <!-- Formulário Lateral -->
    <div class="md:col-span-1 space-y-6">
        <div class="bg-gray-900 p-6 rounded-2xl border border-gray-800 shadow-xl">
            <h2 class="text-sm font-semibold uppercase text-blue-500 tracking-wider mb-4">🆕 Cadastrar Novo Usuário</h2>
            <form class="space-y-4">
                <div>
                    <label class="block text-xs text-gray-400 mb-1 font-semibold uppercase">Nome de Usuário (Login)</label>
                    <input type="text" value="carlos.eduardo" readonly class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white">
                </div>
                <div>
                    <label class="block text-xs text-gray-400 mb-1 font-semibold uppercase">Nome Completo</label>
                    <input type="text" value="DR. CARLOS EDUARDO SILVEIRA" readonly class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white">
                </div>
                <div>
                    <label class="block text-xs text-gray-400 mb-1 font-semibold uppercase">Senha Inicial Temporária</label>
                    <input type="password" value="••••••••••••" readonly class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white">
                </div>
                <div>
                    <label class="block text-xs text-gray-400 mb-1 font-semibold uppercase">Perfil / Permissão</label>
                    <select class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white">
                        <option selected>Perito Médico</option>
                        <option>Recepção</option>
                        <option>Supervisor da Recepção</option>
                        <option>Administrador</option>
                    </select>
                </div>
                <div class="flex items-center gap-2 pt-1 bg-gray-800/40 p-2.5 rounded-lg border border-gray-750">
                    <input type="checkbox" checked readonly class="h-4 w-4 rounded border-gray-600 bg-gray-700 text-blue-600">
                    <label class="text-xs text-gray-300 font-medium">Exigir troca de senha no próximo acesso</label>
                </div>
                <button type="button" class="w-full bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold py-2.5 rounded-lg">
                    Cadastrar Usuário
                </button>
            </form>
        </div>
    </div>

    <!-- Tabela de Usuários -->
    <div class="md:col-span-2 space-y-6">
        <div class="bg-gray-900 p-6 rounded-2xl border border-gray-800 shadow-xl">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-4">
                <h2 class="text-sm font-semibold uppercase text-gray-400 tracking-wider flex items-center gap-2">
                    <span>Usuários Cadastrados</span>
                    <span class="bg-gray-800 text-gray-400 text-xs px-2.5 py-0.5 rounded-full font-bold">4</span>
                </h2>
                <div class="flex flex-wrap items-center gap-2">
                    <button class="bg-amber-950/40 hover:bg-amber-900/50 border border-amber-800/70 text-amber-300 text-xs font-semibold px-3 py-1.5 rounded-lg flex items-center gap-1.5">
                        <span>🔒 Exigir Troca de Todos os Peritos</span>
                    </button>
                    <div class="w-48">
                        <input type="text" placeholder="🔍 Filtrar..." class="w-full bg-gray-950 border border-gray-800 rounded-lg px-3 py-1.5 text-xs text-white">
                    </div>
                </div>
            </div>

            <table class="w-full text-left text-sm text-gray-300">
                <thead>
                    <tr class="border-b border-gray-800 text-xs text-gray-400 uppercase">
                        <th class="pb-2">Usuário & Segurança</th>
                        <th class="pb-2">Nome Completo</th>
                        <th class="pb-2">Perfil</th>
                        <th class="pb-2 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800/50">
                    <tr>
                        <td class="py-3 font-bold text-white">
                            <div>carlos.eduardo</div>
                            <span class="inline-flex items-center gap-1 text-[10px] text-amber-400 bg-amber-950/40 border border-amber-900/50 px-2 py-0.5 rounded font-medium mt-0.5">⚠️ Troca pendente</span>
                        </td>
                        <td class="py-3 text-gray-300 text-xs">DR. CARLOS EDUARDO SILVEIRA</td>
                        <td class="py-3"><span class="bg-purple-950 text-purple-400 text-[10px] px-2.5 py-0.5 rounded-full border border-purple-900/50 uppercase font-semibold">Perito Médico</span></td>
                        <td class="py-3 text-right">
                            <button class="bg-gray-800 text-gray-300 text-xs px-2.5 py-1 rounded border border-gray-750">Editar</button>
                        </td>
                    </tr>
                    <tr>
                        <td class="py-3 font-bold text-white">
                            <div>roberto.souza</div>
                            <span class="inline-flex items-center gap-1 text-[10px] text-emerald-400 bg-emerald-950/30 border border-emerald-900/40 px-2 py-0.5 rounded font-medium mt-0.5">✓ Senha individual ativa</span>
                        </td>
                        <td class="py-3 text-gray-300 text-xs">DR. ROBERTO SOUZA LIMA</td>
                        <td class="py-3"><span class="bg-purple-950 text-purple-400 text-[10px] px-2.5 py-0.5 rounded-full border border-purple-900/50 uppercase font-semibold">Perito Médico</span></td>
                        <td class="py-3 text-right">
                            <button class="bg-gray-800 text-gray-300 text-xs px-2.5 py-1 rounded border border-gray-750 mr-1">Editar</button>
                            <button class="bg-amber-950/40 border border-amber-900/60 text-amber-300 text-xs px-2.5 py-1 rounded">Exigir Troca</button>
                        </td>
                    </tr>
                    <tr>
                        <td class="py-3 font-bold text-white">
                            <div>mariana.recepcao</div>
                            <span class="inline-flex items-center gap-1 text-[10px] text-emerald-400 bg-emerald-950/30 border border-emerald-900/40 px-2 py-0.5 rounded font-medium mt-0.5">✓ Senha individual ativa</span>
                        </td>
                        <td class="py-3 text-gray-300 text-xs">MARIANA SILVA OLIVEIRA</td>
                        <td class="py-3"><span class="bg-emerald-950 text-emerald-400 text-[10px] px-2.5 py-0.5 rounded-full border border-emerald-900/50 uppercase font-semibold">Recepção</span></td>
                        <td class="py-3 text-right">
                            <button class="bg-gray-800 text-gray-300 text-xs px-2.5 py-1 rounded border border-gray-750 mr-1">Editar</button>
                            <button class="bg-amber-950/40 border border-amber-900/60 text-amber-300 text-xs px-2.5 py-1 rounded">Exigir Troca</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</main>
""")

shots = [
    ('02_recepcao_visao_geral.html', '02_recepcao_visao_geral.png', 1440, 1000, html_recepcao_visao),
    ('03_confirmar_presenca.html', '03_confirmar_presenca.png', 1100, 750, html_confirmar_presenca),
    ('04_modal_prioridade_legal.html', '04_modal_prioridade_legal.png', 1100, 850, html_modal_prio),
    ('06_perito_selecao_sala.html', '06_perito_selecao_sala.png', 1000, 750, html_perito_sala),
    ('07_perito_painel_principal.html', '07_perito_painel_principal.png', 1440, 950, html_perito_painel),
    ('09_perito_alerta_toast.html', '09_perito_alerta_toast.png', 1200, 700, html_perito_toast),
    ('11_dashboard_executivo.html', '11_dashboard_executivo.png', 1440, 1050, html_dashboard),
    ('13_gerenciamento_pauta.html', '13_gerenciamento_pauta.png', 1100, 850, html_pauta),
    ('15_alterar_senha_obrigatorio.html', '15_alterar_senha_obrigatorio.png', 1000, 800, html_alterar_senha),
    ('16_usuarios_gerenciamento.html', '16_usuarios_gerenciamento.png', 1300, 850, html_usuarios),
]

with sync_playwright() as p:
    browser = p.chromium.launch()
    for html_file, png_file, width, height, content in shots:
        temp_path = os.path.join(output_dir, html_file)
        target_png = os.path.join(output_dir, png_file)
        with open(temp_path, 'w', encoding='utf-8') as f:
            f.write(content)
        
        page = browser.new_page(viewport={'width': width, 'height': height}, device_scale_factor=1.5)
        page.goto('file:///' + temp_path.replace('\\', '/'))
        page.wait_for_timeout(400)
        page.screenshot(path=target_png)
        page.close()
        
        if os.path.exists(temp_path):
            os.remove(temp_path)
        print(f"Captured: {png_file}")
    browser.close()

print("All screenshots captured successfully!")
