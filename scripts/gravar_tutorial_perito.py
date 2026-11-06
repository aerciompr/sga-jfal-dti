import os
import subprocess
from playwright.sync_api import sync_playwright

repo_root = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
output_dir = os.path.join(repo_root, 'docs', 'manuais', 'videos')
os.makedirs(output_dir, exist_ok=True)
assets_dir = os.path.join(repo_root, 'assets').replace('\\', '/')

html_content = f"""<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Vídeo Tutorial — Painel do Médico Perito (SGA JFAL)</title>
    <link rel="stylesheet" href="file:///{assets_dir}/tailwind.css">
    <link rel="stylesheet" href="file:///{assets_dir}/usabilidade.css">
    <style>
        body {{
            background-color: #030712;
            color: #f9fafb;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            margin: 0;
            padding: 0;
            overflow: hidden;
            width: 1280px;
            height: 720px;
        }}
        
        #virtual-cursor {{
            position: fixed;
            width: 24px;
            height: 24px;
            pointer-events: none;
            z-index: 99999;
            transition: all 0.75s cubic-bezier(0.22, 1, 0.36, 1);
            transform: translate(-4px, -2px);
        }}
        #virtual-cursor svg {{
            filter: drop-shadow(0 2px 5px rgba(0,0,0,0.8));
        }}
        .cursor-click-ripple {{
            position: absolute;
            top: 2px;
            left: 2px;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: rgba(59, 130, 246, 0.6);
            transform: scale(0);
            animation: ripple 0.5s ease-out;
            pointer-events: none;
        }}
        @keyframes ripple {{
            0% {{ transform: scale(0.2); opacity: 1; }}
            100% {{ transform: scale(2.2); opacity: 0; }}
        }}
        
        .spotlight-active {{
            position: relative;
            z-index: 1000;
            box-shadow: 0 0 0 4px #3b82f6, 0 0 35px rgba(59, 130, 246, 0.6) !important;
            border-color: #60a5fa !important;
            transition: all 0.3s ease;
        }}
        .spotlight-amber {{
            box-shadow: 0 0 0 4px #f59e0b, 0 0 35px rgba(245, 158, 11, 0.6) !important;
            border-color: #fbbf24 !important;
        }}
        .spotlight-emerald {{
            box-shadow: 0 0 0 4px #10b981, 0 0 35px rgba(16, 185, 129, 0.6) !important;
            border-color: #34d399 !important;
        }}

        #tutorial-hud {{
            position: fixed;
            top: 16px;
            left: 50%;
            transform: translateX(-50%);
            width: 92%;
            max-width: 1180px;
            background: rgba(15, 23, 42, 0.95);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(59, 130, 246, 0.4);
            border-radius: 16px;
            padding: 12px 24px;
            z-index: 50000;
            box-shadow: 0 20px 40px rgba(0,0,0,0.8), 0 0 20px rgba(59, 130, 246, 0.2);
            transition: all 0.4s ease;
        }}
        
        .hud-badge {{
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #1e3a8a;
            color: #93c5fd;
            border: 1px solid #3b82f6;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 3px 10px;
            border-radius: 999px;
        }}
        
        .fullscreen-card {{
            position: fixed;
            inset: 0;
            z-index: 80000;
            background: radial-gradient(circle at center, #0f172a 0%, #030712 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px;
            text-align: center;
            transition: opacity 0.8s ease, transform 0.8s ease;
        }}
        .fullscreen-card.hidden-card {{
            opacity: 0;
            pointer-events: none;
            transform: scale(1.05);
        }}
        
        .queue-row {{
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }}
    </style>
</head>
<body class="bg-gray-950 text-white min-h-screen select-none">

    <div id="virtual-cursor" style="left: 640px; top: 360px;">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none">
            <path d="M4.5 3L11.5 21L14.5 13.5L22 10.5L4.5 3Z" fill="#facc15" stroke="#000" stroke-width="1.8" stroke-linejoin="round"/>
        </svg>
    </div>

    <div id="tutorial-hud">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <span id="hud-step-badge" class="hud-badge">VÍDEO TUTORIAL &bull; SGA JFAL</span>
                <h2 id="hud-title" class="text-base font-black text-white tracking-wide">Configurações e Operação do Médico Perito</h2>
            </div>
            <div class="flex items-center gap-2">
                <span id="hud-progress-text" class="text-xs text-blue-400 font-bold font-mono">1 / 6</span>
                <div class="w-24 bg-gray-800 h-2 rounded-full overflow-hidden border border-gray-700">
                    <div id="hud-progress-bar" class="bg-blue-500 h-full w-1/6 transition-all duration-500"></div>
                </div>
            </div>
        </div>
        <p id="hud-desc" class="text-xs text-gray-300 mt-1.5 leading-relaxed font-medium">
            Assista a este guia rápido para conhecer os campos e comandos essenciais do seu consultório pericial.
        </p>
    </div>

    <!-- TELA DE INTRODUÇÃO -->
    <div id="intro-card" class="fullscreen-card">
        <div class="inline-flex items-center gap-3 bg-blue-950/70 border border-blue-800 px-4 py-1.5 rounded-full mb-6">
            <img src="file:///{assets_dir}/catavento-jfal.png" class="h-6 w-auto" alt="JFAL">
            <span class="text-xs font-bold text-blue-300 uppercase tracking-widest">Justiça Federal em Alagoas &bull; TRF5</span>
        </div>
        <h1 class="text-3xl font-black text-white max-w-2xl leading-tight mb-4">
            Painel do Médico Perito Judicial
        </h1>
        <p class="text-base text-gray-300 max-w-xl font-medium mb-8">
            Tutorial Prático Oficial: Escolha de Salas, Critérios de Fila (Chegada vs PJe), Prioridade Legal e Chamadas na TV
        </p>
        <div class="grid grid-cols-3 gap-6 max-w-3xl text-left bg-gray-900/80 p-5 rounded-2xl border border-gray-800">
            <div class="p-3 bg-gray-950/60 rounded-xl border border-gray-800/80">
                <span class="text-xl mb-1 block">🚪</span>
                <strong class="text-xs text-white block">Trava de Sala</strong>
                <span class="text-[11px] text-gray-400">Proteção anti-conflito de consultório</span>
            </div>
            <div class="p-3 bg-gray-950/60 rounded-xl border border-gray-800/80">
                <span class="text-xl mb-1 block">🏃 vs 🕒</span>
                <strong class="text-xs text-white block">Critério de Fila</strong>
                <span class="text-[11px] text-gray-400">Ordem de Chegada ou Horário PJe</span>
            </div>
            <div class="p-3 bg-gray-950/60 rounded-xl border border-gray-800/80">
                <span class="text-xl mb-1 block">⭐ 80+</span>
                <strong class="text-xs text-white block">Prioridade Legal</strong>
                <span class="text-[11px] text-gray-400">Autonomia clínica para priorização</span>
            </div>
        </div>
    </div>

    <!-- TELA 1: SELEÇÃO DE SALA -->
    <div id="screen-sala" class="fullscreen-card hidden-card" style="background:#030712;">
        <div class="max-w-4xl w-full">
            <div class="text-center mb-8">
                <div class="inline-block bg-blue-950/80 text-blue-400 border border-blue-800/80 text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wider mb-2">
                    Passo 1: Vinculação de Consultório
                </div>
                <h2 class="text-2xl font-bold text-white">Selecione sua Sala de Atendimento</h2>
                <p class="text-xs text-gray-400 mt-1">O sistema bloqueia automaticamente salas já ocupadas por colegas</p>
            </div>
            
            <div class="grid grid-cols-3 gap-6">
                <!-- Sala 1: Ocupada -->
                <div class="bg-gray-900/60 border border-red-900/40 p-6 rounded-2xl opacity-60">
                    <div class="flex justify-between items-center mb-4">
                        <span class="text-sm font-bold text-white">🚪 Sala 01</span>
                        <span class="bg-red-950 text-red-400 text-[10px] font-bold px-2 py-0.5 rounded border border-red-800">Ocupada</span>
                    </div>
                    <p class="text-xs text-gray-400 mb-6">Em atendimento pericial com Dr. Roberto Souza</p>
                    <button disabled class="w-full bg-gray-800 text-gray-500 font-bold text-xs py-2.5 rounded-xl cursor-not-allowed">
                        Indisponível
                    </button>
                </div>
                
                <!-- Sala 2: Alvo (Livre) -->
                <div id="target-card-sala2" class="bg-gray-900 border border-gray-700 p-6 rounded-2xl transition-all">
                    <div class="flex justify-between items-center mb-4">
                        <span class="text-sm font-bold text-white">🚪 Sala 02 — Perícias</span>
                        <span class="bg-emerald-950 text-emerald-400 text-[10px] font-bold px-2 py-0.5 rounded border border-emerald-800">Disponível</span>
                    </div>
                    <p class="text-xs text-gray-400 mb-6">Consultório médico livre para vinculação imediata</p>
                    <button id="btn-entrar-sala2" class="w-full bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs py-2.5 rounded-xl shadow-lg transition">
                        Entrar na Sala 02
                    </button>
                </div>
                
                <!-- Sala 3: Livre -->
                <div class="bg-gray-900 border border-gray-800 p-6 rounded-2xl">
                    <div class="flex justify-between items-center mb-4">
                        <span class="text-sm font-bold text-white">🚪 Sala 03 — Perícias</span>
                        <span class="bg-emerald-950 text-emerald-400 text-[10px] font-bold px-2 py-0.5 rounded border border-emerald-800">Disponível</span>
                    </div>
                    <p class="text-xs text-gray-400 mb-6">Consultório médico livre</p>
                    <button class="w-full bg-gray-800 text-gray-300 font-bold text-xs py-2.5 rounded-xl">
                        Entrar na Sala 03
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- TELA 2: PAINEL PRINCIPAL DO PERITO -->
    <div id="main-panel" class="pt-24 px-8 pb-6 h-screen flex flex-col justify-between">
        <div>
            <div class="flex justify-between items-center bg-gray-900 p-4 rounded-2xl border border-gray-800 mb-4 shadow-xl">
                <div class="flex items-center gap-3">
                    <span class="text-xl">🚪</span>
                    <div>
                        <div class="text-sm font-bold text-white flex items-center gap-2">
                            <span>Sala 02 — Consultório Pericial</span>
                            <span class="bg-emerald-950 text-emerald-400 border border-emerald-800 text-[10px] font-bold px-2 py-0.2 rounded">Ativo</span>
                        </div>
                        <div class="text-[11px] text-gray-400 font-medium">Médico Perito: <strong>Dr. Carlos Eduardo Silveira</strong></div>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <!-- CAMPO 1: CRITÉRIO DA FILA -->
                    <div id="box-criterio" class="flex items-center bg-gray-950 p-1.5 rounded-xl border border-gray-800 transition-all">
                        <span class="text-[10px] uppercase font-bold text-gray-400 px-2">Fila por:</span>
                        <button id="btn-fila-chegada" class="bg-blue-600 text-white font-bold text-xs px-3 py-1.5 rounded-lg shadow transition flex items-center gap-1.5">
                            <span>🏃 Ordem de Chegada</span>
                        </button>
                        <button id="btn-fila-pje" class="text-gray-400 hover:text-white font-semibold text-xs px-3 py-1.5 rounded-lg transition flex items-center gap-1.5">
                            <span>🕒 Horário PJe</span>
                        </button>
                    </div>

                    <!-- CAMPO 2: PRIORIDADE LEGAL -->
                    <div id="box-prioridade" class="flex items-center bg-gray-950 p-1.5 rounded-xl border border-gray-800 transition-all">
                        <button id="btn-toggle-prio" class="bg-gray-800 text-gray-300 font-semibold text-xs px-3 py-1.5 rounded-lg transition flex items-center gap-2">
                            <span id="prio-icon" class="text-sm">⚪</span>
                            <span id="prio-text">Prioridade: Opcional</span>
                        </button>
                    </div>

                    <button class="bg-red-950/40 hover:bg-red-900/60 border border-red-900/60 text-red-400 text-xs px-3 py-1.5 rounded-xl font-bold transition">
                        Sair (Liberar Sala)
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-12 gap-5">
                <div class="col-span-5 space-y-4">
                    <div id="card-proximo" class="bg-gray-900 p-5 rounded-2xl border border-gray-800 shadow-xl space-y-4">
                        <div class="flex justify-between items-center">
                            <span class="text-xs font-bold uppercase tracking-wider text-blue-400 flex items-center gap-1.5">
                                <span>🎯</span> Próximo a Ser Atendido
                            </span>
                            <span id="badge-prio-destaque" class="hidden bg-amber-950 text-amber-300 border border-amber-600 text-[10px] font-black px-2 py-0.5 rounded-full uppercase">
                                ⭐ Superprioridade 80+
                            </span>
                        </div>

                        <div class="bg-gray-950 p-4 rounded-xl border border-gray-800">
                            <div class="flex justify-between items-start mb-2">
                                <div>
                                    <h3 id="paciente-nome" class="text-base font-bold text-white">José Oliveira Santos</h3>
                                    <span id="paciente-processo" class="text-[11px] text-gray-400 font-mono block">Proc: 0801234-56.2026.4.05.8000</span>
                                </div>
                                <span id="paciente-senha" class="text-xl font-black text-blue-400 font-mono bg-blue-950/80 border border-blue-800/80 px-2.5 py-0.5 rounded-lg">
                                    PE-01
                                </span>
                            </div>
                            <div class="grid grid-cols-2 gap-2 text-[10px] text-gray-400 pt-2 border-t border-gray-800/80">
                                <div>Chegada: <strong id="paciente-chegada" class="text-white">08:15:10</strong></div>
                                <div>Pauta PJe: <strong id="paciente-pje" class="text-white">08:30</strong></div>
                            </div>
                        </div>

                        <div class="space-y-2 pt-1">
                            <button id="btn-chamar" class="w-full bg-blue-600 hover:bg-blue-500 text-white font-bold text-sm py-3 px-4 rounded-xl shadow-lg transition flex items-center justify-center gap-2">
                                <span>🔔 Chamar Paciente na TV</span>
                            </button>

                            <div class="grid grid-cols-2 gap-2">
                                <button id="btn-iniciar" class="bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs py-2.5 px-3 rounded-xl transition flex items-center justify-center gap-1.5 opacity-60 cursor-not-allowed">
                                    <span>▶ Iniciar Perícia</span>
                                </button>
                                <button id="btn-ausente" class="bg-amber-900/40 hover:bg-amber-800/50 border border-amber-700/60 text-amber-300 font-semibold text-xs py-2.5 px-3 rounded-xl transition flex items-center justify-center gap-1">
                                    <span>⚠️ Ausente (Descer 2)</span>
                                </button>
                            </div>

                            <button id="btn-concluir" class="w-full bg-purple-900/30 border border-purple-800/40 text-purple-400 font-bold text-xs py-2 px-3 rounded-xl transition opacity-40 cursor-not-allowed">
                                ✓ Concluir Atendimento
                            </button>
                        </div>
                    </div>

                    <div class="bg-gray-900/60 p-3 rounded-xl border border-gray-800/80 flex items-center gap-3 text-xs text-gray-400">
                        <span class="text-lg">ℹ️</span>
                        <span>Ao concluir, oriente o paciente a retirar sua <strong>Declaração de Comparecimento</strong> diretamente no balcão da Recepção.</span>
                    </div>
                </div>

                <div class="col-span-7 bg-gray-900 p-5 rounded-2xl border border-gray-800 shadow-xl flex flex-col justify-between">
                    <div>
                        <div class="flex justify-between items-center mb-3">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-emerald-400 flex items-center gap-1.5">
                                <span>📋</span> Fila de Espera dos Presentes (<span id="qtd-fila">4</span> periciados)
                            </h3>
                            <span id="badge-criterio-ativo" class="text-[10px] text-gray-400 font-medium bg-gray-950 px-2.5 py-0.5 rounded-full border border-gray-800">
                                Critério: Ordem de Chegada
                            </span>
                        </div>

                        <div class="overflow-hidden rounded-xl border border-gray-800 bg-gray-950">
                            <table class="w-full text-left text-xs border-collapse">
                                <thead>
                                    <tr class="border-b border-gray-800 text-[10px] text-gray-500 uppercase">
                                        <th class="p-2.5">Posição</th>
                                        <th class="p-2.5">Senha</th>
                                        <th class="p-2.5">Periciado</th>
                                        <th class="p-2.5">Chegada</th>
                                        <th class="p-2.5">Horário PJe</th>
                                    </tr>
                                </thead>
                                <tbody id="queue-body" class="divide-y divide-gray-800/60">
                                    <tr id="row-p1" class="queue-row bg-blue-950/20 text-white font-medium">
                                        <td class="p-2.5 font-bold text-blue-400">1º</td>
                                        <td class="p-2.5 font-mono font-bold text-blue-400">PE-01</td>
                                        <td class="p-2.5">José Oliveira Santos</td>
                                        <td class="p-2.5 text-gray-400">08:15</td>
                                        <td class="p-2.5 text-gray-400">08:30</td>
                                    </tr>
                                    <tr id="row-p2" class="queue-row text-gray-300">
                                        <td class="p-2.5 font-bold">2º</td>
                                        <td class="p-2.5 font-mono font-bold text-gray-400">PE-02</td>
                                        <td class="p-2.5 flex items-center gap-1.5">
                                            <span>Sebastiana Gomes da Silva</span>
                                            <span class="bg-amber-950 text-amber-300 border border-amber-600 text-[8px] font-black px-1.5 py-0.2 rounded-full uppercase">80+</span>
                                        </td>
                                        <td class="p-2.5 text-gray-400">08:22</td>
                                        <td class="p-2.5 text-gray-400">09:15</td>
                                    </tr>
                                    <tr id="row-p3" class="queue-row text-gray-300">
                                        <td class="p-2.5 font-bold">3º</td>
                                        <td class="p-2.5 font-mono font-bold text-gray-400">PE-03</td>
                                        <td class="p-2.5 flex items-center gap-1.5">
                                            <span>Manoel Cavalcanti Neto</span>
                                            <span class="bg-sky-950 text-sky-300 border border-sky-600 text-[8px] font-bold px-1.5 py-0.2 rounded-full uppercase">PCD</span>
                                        </td>
                                        <td class="p-2.5 text-gray-400">08:25</td>
                                        <td class="p-2.5 text-gray-400">09:00</td>
                                    </tr>
                                    <tr id="row-p4" class="queue-row text-gray-300">
                                        <td class="p-2.5 font-bold">4º</td>
                                        <td class="p-2.5 font-mono font-bold text-gray-400">PE-04</td>
                                        <td class="p-2.5">Marcos Vinícius Melo</td>
                                        <td class="p-2.5 text-gray-400">08:28</td>
                                        <td class="p-2.5 text-gray-400">08:45</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="pt-3 border-t border-gray-800 flex justify-between items-center text-[10px] text-gray-500">
                        <span>Atualização automática a cada 4 segundos</span>
                        <span class="text-blue-400">Sincronizado com a Recepção Central</span>
                    </div>
                </div>
            </div>
        </div>

        <div id="tv-modal" class="hidden fixed bottom-6 right-8 bg-gray-900 border-2 border-blue-500 rounded-2xl p-4 shadow-2xl z-40 max-w-sm w-full animate-bounce">
            <div class="flex items-center gap-3">
                <div class="text-2xl">📺</div>
                <div>
                    <span class="text-[10px] uppercase font-bold text-blue-400 block tracking-wider">Chamada Disparada na TV da Recepção</span>
                    <strong class="text-sm text-white block">PE-01 &bull; José Oliveira Santos</strong>
                    <span class="text-[10px] text-gray-400">Campainha sonora e voz sintetizada emitidas</span>
                </div>
            </div>
        </div>
    </div>

    <!-- TELA DE ENCERRAMENTO -->
    <div id="outro-card" class="fullscreen-card hidden-card">
        <div class="inline-flex items-center gap-3 bg-emerald-950/70 border border-emerald-800 px-4 py-1.5 rounded-full mb-6">
            <span class="text-xs font-bold text-emerald-300 uppercase tracking-widest">Resumo Operacional &bull; Boas Práticas</span>
        </div>
        <h2 class="text-3xl font-black text-white max-w-xl leading-tight mb-4">
            Pronto para Atender com Excelência!
        </h2>
        <div class="max-w-2xl text-left bg-gray-900/90 p-6 rounded-2xl border border-gray-800 space-y-3 text-xs text-gray-300 mb-8">
            <div class="flex items-start gap-3">
                <span class="text-emerald-400 font-bold text-base">✓</span>
                <span><strong>Critério da Fila:</strong> Defina no topo se prefere atender pela ordem de chegada no fórum ou horário marcado no PJe.</span>
            </div>
            <div class="flex items-start gap-3">
                <span class="text-emerald-400 font-bold text-base">✓</span>
                <span><strong>Prioridade Legal:</strong> Ative a chave para trazer idosos 80+ e PCDs para o topo, conforme sua rotina clínica.</span>
            </div>
            <div class="flex items-start gap-3">
                <span class="text-emerald-400 font-bold text-base">✓</span>
                <span><strong>Ausência com Tolerância:</strong> O botão "Ausente" desce 2 posições sem cancelar o processo do cidadão.</span>
            </div>
            <div class="flex items-start gap-3">
                <span class="text-emerald-400 font-bold text-base">✓</span>
                <span><strong>Encerramento:</strong> Ao sair, o sistema desocupa sua sala automaticamente para o próximo turno.</span>
            </div>
        </div>
        <p class="text-xs text-gray-500">
            Dúvidas? Entre em contato com a Divisão de Tecnologia da Informação (DTI) &bull; Seção Judiciária de Alagoas
        </p>
    </div>

    <script>
        const cursor = document.getElementById('virtual-cursor');
        
        function moveCursor(x, y, duration = 750) {{
            cursor.style.transition = `all ${{duration}}ms cubic-bezier(0.22, 1, 0.36, 1)`;
            cursor.style.left = x + 'px';
            cursor.style.top = y + 'px';
        }}
        
        function clickEffect() {{
            const ripple = document.createElement('div');
            ripple.className = 'cursor-click-ripple';
            cursor.appendChild(ripple);
            setTimeout(() => ripple.remove(), 600);
        }}
        
        function updateHud(stepBadge, title, desc, currentStep, totalSteps) {{
            document.getElementById('hud-step-badge').textContent = stepBadge;
            document.getElementById('hud-title').textContent = title;
            document.getElementById('hud-desc').textContent = desc;
            document.getElementById('hud-progress-text').textContent = `${{currentStep}} / ${{totalSteps}}`;
            document.getElementById('hud-progress-bar').style.width = `${{(currentStep / totalSteps) * 100}}%`;
        }}

        window.addEventListener('load', () => {{
            setTimeout(() => {{
                document.getElementById('intro-card').classList.add('hidden-card');
                document.getElementById('screen-sala').classList.remove('hidden-card');
                updateHud('PASSO 1 DE 6', 'Seleção de Consultório Pericial', 'Escolha sua sala médica. Salas ocupadas por colegas ficam travadas automaticamente para evitar conflitos.', 1, 6);
                moveCursor(640, 420, 800);
            }}, 4500);

            setTimeout(() => {{
                const target = document.getElementById('target-card-sala2');
                target.classList.add('spotlight-active');
                moveCursor(640, 450, 700);
            }}, 6500);

            setTimeout(() => {{
                clickEffect();
                document.getElementById('btn-entrar-sala2').classList.add('scale-95');
                setTimeout(() => document.getElementById('btn-entrar-sala2').classList.remove('scale-95'), 150);
            }}, 7800);

            setTimeout(() => {{
                document.getElementById('screen-sala').classList.add('hidden-card');
                updateHud('PASSO 2 DE 6: CAMPO DE FILA', 'Critério da Fila: Chegada vs Horário PJe', 'O médico escolhe se atende na ordem de chegada ao balcão ou pelo horário oficial da pauta no PJe.', 2, 6);
                moveCursor(500, 110, 800);
            }}, 9000);

            setTimeout(() => {{
                const boxCriterio = document.getElementById('box-criterio');
                boxCriterio.classList.add('spotlight-active');
                moveCursor(570, 110, 700);
            }}, 11000);

            setTimeout(() => {{
                clickEffect();
                const btnChegada = document.getElementById('btn-fila-chegada');
                const btnPje = document.getElementById('btn-fila-pje');
                
                btnChegada.className = 'text-gray-400 hover:text-white font-semibold text-xs px-3 py-1.5 rounded-lg transition flex items-center gap-1.5';
                btnPje.className = 'bg-blue-600 text-white font-bold text-xs px-3 py-1.5 rounded-lg shadow transition flex items-center gap-1.5';
                
                document.getElementById('badge-criterio-ativo').textContent = 'Critério: Horário do PJe';
                
                const tbody = document.getElementById('queue-body');
                tbody.innerHTML = `
                    <tr id="row-p1" class="queue-row bg-blue-950/20 text-white font-medium">
                        <td class="p-2.5 font-bold text-blue-400">1º</td>
                        <td class="p-2.5 font-mono font-bold text-blue-400">PE-01</td>
                        <td class="p-2.5">José Oliveira Santos</td>
                        <td class="p-2.5 text-gray-400">08:15</td>
                        <td class="p-2.5 text-emerald-400 font-bold">08:30</td>
                    </tr>
                    <tr id="row-p4" class="queue-row text-gray-300">
                        <td class="p-2.5 font-bold">2º</td>
                        <td class="p-2.5 font-mono font-bold text-gray-400">PE-04</td>
                        <td class="p-2.5">Marcos Vinícius Melo</td>
                        <td class="p-2.5 text-gray-400">08:28</td>
                        <td class="p-2.5 text-emerald-400 font-bold">08:45</td>
                    </tr>
                    <tr id="row-p3" class="queue-row text-gray-300">
                        <td class="p-2.5 font-bold">3º</td>
                        <td class="p-2.5 font-mono font-bold text-gray-400">PE-03</td>
                        <td class="p-2.5 flex items-center gap-1.5">
                            <span>Manoel Cavalcanti Neto</span>
                            <span class="bg-sky-950 text-sky-300 border border-sky-600 text-[8px] font-bold px-1.5 py-0.2 rounded-full uppercase">PCD</span>
                        </td>
                        <td class="p-2.5 text-gray-400">08:25</td>
                        <td class="p-2.5 text-emerald-400 font-bold">09:00</td>
                    </tr>
                    <tr id="row-p2" class="queue-row text-gray-300">
                        <td class="p-2.5 font-bold">4º</td>
                        <td class="p-2.5 font-mono font-bold text-gray-400">PE-02</td>
                        <td class="p-2.5 flex items-center gap-1.5">
                            <span>Sebastiana Gomes da Silva</span>
                            <span class="bg-amber-950 text-amber-300 border border-amber-600 text-[8px] font-black px-1.5 py-0.2 rounded-full uppercase">80+</span>
                        </td>
                        <td class="p-2.5 text-gray-400">08:22</td>
                        <td class="p-2.5 text-emerald-400 font-bold">09:15</td>
                    </tr>
                `;
            }}, 13500);

            setTimeout(() => {{
                document.getElementById('box-criterio').classList.remove('spotlight-active');
                const boxPrio = document.getElementById('box-prioridade');
                boxPrio.classList.add('spotlight-amber');
                updateHud('PASSO 3 DE 6: PRIORIDADE LEGAL', 'Chave de Prioridade (Autonomia Médica)', 'Ative a chave para trazer automaticamente Superprioridades (80+) e PCDs para o topo da sua fila.', 3, 6);
                moveCursor(740, 110, 800);
            }}, 17500);

            setTimeout(() => {{
                clickEffect();
                const btnPrio = document.getElementById('btn-toggle-prio');
                btnPrio.className = 'bg-amber-950 text-amber-300 border border-amber-600 font-bold text-xs px-3 py-1.5 rounded-lg shadow-lg transition flex items-center gap-2';
                document.getElementById('prio-icon').textContent = '⭐';
                document.getElementById('prio-text').textContent = 'Prioridade: ATIVA';

                const tbody = document.getElementById('queue-body');
                tbody.innerHTML = `
                    <tr id="row-p2" class="queue-row bg-amber-950/40 border border-amber-800 text-white font-medium">
                        <td class="p-2.5 font-bold text-amber-300">⭐ 1º</td>
                        <td class="p-2.5 font-mono font-bold text-amber-400">PE-02</td>
                        <td class="p-2.5 flex items-center gap-1.5">
                            <strong class="text-white">Sebastiana Gomes da Silva</strong>
                            <span class="bg-amber-950 text-amber-300 border border-amber-600 text-[9px] font-black px-2 py-0.5 rounded-full uppercase">⭐ 80+ SUPER</span>
                        </td>
                        <td class="p-2.5 text-gray-300">08:22</td>
                        <td class="p-2.5 text-gray-300">09:15</td>
                    </tr>
                    <tr id="row-p3" class="queue-row bg-sky-950/20 text-gray-200">
                        <td class="p-2.5 font-bold text-sky-400">♿ 2º</td>
                        <td class="p-2.5 font-mono font-bold text-sky-400">PE-03</td>
                        <td class="p-2.5 flex items-center gap-1.5">
                            <span>Manoel Cavalcanti Neto</span>
                            <span class="bg-sky-950 text-sky-300 border border-sky-600 text-[8px] font-bold px-1.5 py-0.2 rounded-full uppercase">PCD</span>
                        </td>
                        <td class="p-2.5 text-gray-400">08:25</td>
                        <td class="p-2.5 text-gray-400">09:00</td>
                    </tr>
                    <tr id="row-p1" class="queue-row text-gray-300">
                        <td class="p-2.5 font-bold">3º</td>
                        <td class="p-2.5 font-mono font-bold text-gray-400">PE-01</td>
                        <td class="p-2.5">José Oliveira Santos</td>
                        <td class="p-2.5 text-gray-400">08:15</td>
                        <td class="p-2.5 text-gray-400">08:30</td>
                    </tr>
                    <tr id="row-p4" class="queue-row text-gray-300">
                        <td class="p-2.5 font-bold">4º</td>
                        <td class="p-2.5 font-mono font-bold text-gray-400">PE-04</td>
                        <td class="p-2.5">Marcos Vinícius Melo</td>
                        <td class="p-2.5 text-gray-400">08:28</td>
                        <td class="p-2.5 text-gray-400">08:45</td>
                    </tr>
                `;

                document.getElementById('paciente-nome').textContent = 'Sebastiana Gomes da Silva (82 anos)';
                document.getElementById('paciente-processo').textContent = 'Proc: 0803456-78.2026.4.05.8000';
                document.getElementById('paciente-senha').textContent = 'PE-02';
                document.getElementById('paciente-chegada').textContent = '08:22:15';
                document.getElementById('paciente-pje').textContent = '09:15';
                document.getElementById('badge-prio-destaque').classList.remove('hidden');
            }}, 20000);

            setTimeout(() => {{
                document.getElementById('box-prioridade').classList.remove('spotlight-amber');
                const btnChamar = document.getElementById('btn-chamar');
                btnChamar.classList.add('spotlight-active');
                updateHud('PASSO 4 DE 6: CHAMADA AUDITIONS', 'Chamar Paciente na TV da Sala de Espera', 'Clique para acionar o sinal sonoro de campainha e a voz sintetizada com o nome do periciado e a sala.', 4, 6);
                moveCursor(260, 420, 800);
            }}, 24500);

            setTimeout(() => {{
                clickEffect();
                const btnChamar = document.getElementById('btn-chamar');
                btnChamar.innerHTML = '<span>🔔 Chamar Novamente</span>';
                btnChamar.className = 'w-full bg-blue-700 text-white font-bold text-sm py-3 px-4 rounded-xl shadow-lg transition flex items-center justify-center gap-2';
                
                document.getElementById('btn-iniciar').className = 'bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs py-2.5 px-3 rounded-xl transition flex items-center justify-center gap-1.5 shadow-lg';
                document.getElementById('btn-iniciar').removeAttribute('disabled');
                document.getElementById('btn-iniciar').classList.remove('opacity-60', 'cursor-not-allowed');

                document.getElementById('tv-modal').classList.remove('hidden');
            }}, 27000);

            setTimeout(() => {{
                document.getElementById('btn-chamar').classList.remove('spotlight-active');
                document.getElementById('tv-modal').classList.add('hidden');
                const btnIniciar = document.getElementById('btn-iniciar');
                btnIniciar.classList.add('spotlight-emerald');
                updateHud('PASSO 5 DE 6: EXAME CLÍNICO', 'Iniciar o Atendimento Pericial', 'Quando o paciente adentrar a sala, inicie o exame para cronometrar a consulta e atualizar a recepção.', 5, 6);
                moveCursor(180, 475, 750);
            }}, 31000);

            setTimeout(() => {{
                clickEffect();
                const btnIniciar = document.getElementById('btn-iniciar');
                btnIniciar.innerHTML = '<span>🟢 Em Exame (00:01)</span>';
                btnIniciar.className = 'bg-emerald-950 text-emerald-300 border border-emerald-600 font-bold text-xs py-2.5 px-3 rounded-xl transition flex items-center justify-center gap-1.5';
                
                const btnConcluir = document.getElementById('btn-concluir');
                btnConcluir.className = 'w-full bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs py-2.5 px-3 rounded-xl transition shadow-lg';
                btnConcluir.classList.remove('opacity-40', 'cursor-not-allowed');
            }}, 33500);

            setTimeout(() => {{
                document.getElementById('btn-iniciar').classList.remove('spotlight-emerald');
                const btnAusente = document.getElementById('btn-ausente');
                btnAusente.classList.add('spotlight-amber');
                updateHud('PASSO 6 DE 6: AUSÊNCIA INTELIGENTE', 'Botão Ausente: Descer 2 Posições', 'Se o paciente não responder, este botão reposiciona-o 2 lugares para trás sem cancelar a perícia.', 6, 6);
                moveCursor(340, 475, 750);
            }}, 37000);

            setTimeout(() => {{
                document.getElementById('btn-ausente').classList.remove('spotlight-amber');
                const btnConcluir = document.getElementById('btn-concluir');
                btnConcluir.classList.add('spotlight-active');
                moveCursor(260, 520, 700);
            }}, 41000);

            setTimeout(() => {{
                clickEffect();
                btnConcluir.classList.add('scale-95');
                setTimeout(() => btnConcluir.classList.remove('scale-95'), 150);
            }}, 43000);

            setTimeout(() => {{
                document.getElementById('outro-card').classList.remove('hidden-card');
                updateHud('TUTORIAL CONCLUÍDO', 'Guia Prático Finalizado', 'Excelente! Agora você domina todas as ferramentas do painel pericial do SGA JFAL.', 6, 6);
                moveCursor(640, 360, 800);
            }}, 45000);
        }});
    </script>
</body>
</html>
"""

html_path = os.path.join(output_dir, 'tutorial_perito.html')
with open(html_path, 'w', encoding='utf-8') as f:
    f.write(html_content)

video_temp_dir = os.path.join(output_dir, 'temp_recording')
os.makedirs(video_temp_dir, exist_ok=True)

with sync_playwright() as p:
    browser = p.chromium.launch(headless=True)
    context = browser.new_context(
        record_video_dir=video_temp_dir,
        record_video_size={'width': 1280, 'height': 720},
        viewport={'width': 1280, 'height': 720}
    )
    page = context.new_page()
    page.goto('file:///' + html_path.replace('\\', '/'))
    page.wait_for_timeout(53000)
    page.close()
    context.close()
    browser.close()

webm_files = [f for f in os.listdir(video_temp_dir) if f.endswith('.webm')]
if not webm_files:
    raise RuntimeError("No WebM file recorded!")

recorded_webm = os.path.join(video_temp_dir, webm_files[0])
target_webm = os.path.join(output_dir, 'tutorial_painel_perito_sga.webm')
target_mp4 = os.path.join(output_dir, 'tutorial_painel_perito_sga.mp4')

if os.path.exists(target_webm):
    os.remove(target_webm)
os.rename(recorded_webm, target_webm)
os.rmdir(video_temp_dir)

cmd = [
    'ffmpeg', '-y',
    '-i', target_webm,
    '-c:v', 'libx264',
    '-pix_fmt', 'yuv420p',
    '-crf', '18',
    '-preset', 'fast',
    target_mp4
]
subprocess.run(cmd, check=True)
print(f"Recorded and converted: {target_mp4}")
