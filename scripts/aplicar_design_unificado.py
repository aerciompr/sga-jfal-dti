# -*- coding: utf-8 -*-
"""
Script de Padronização Visual Institucional - SGA JFAL
Aplica o Design System homologado, elimina cores dispersas,
unifica cabeçalhos e menus, melhora o contraste e responsividade.
"""
import re
import sys

def atualizar_gerenciar_pauta():
    with open('gerenciar_pauta.php', 'r', encoding='utf-8') as f:
        content = f.read()

    header_old_re = r'    <!-- Header -->\s*<header class="bg-gray-900 border-b border-gray-800 px-6 py-4 flex items-center justify-between shadow-md shrink-0">.*?</header>'
    header_new = '''    <!-- Header -->
    <header class="bg-gray-900 border-b border-gray-800 px-6 py-4 flex items-center justify-between shadow-md shrink-0">
        <div class="flex items-center space-x-3 shrink-0">
            <a href="dashboard.php" class="flex items-center space-x-2.5 group hover:opacity-95 transition">
                <img src="assets/catavento-jfal.png" alt="JFAL" class="h-8 w-auto object-contain transition-transform duration-300 group-hover:scale-105">
                <div class="hidden sm:block leading-tight">
                    <h1 class="text-xs font-black tracking-wide text-blue-500 uppercase leading-none group-hover:text-blue-400 transition-colors">Justiça Federal em Alagoas</h1>
                    <p class="text-[8px] text-gray-400 font-semibold tracking-widest uppercase mt-0.5">Seção Judiciária de Alagoas</p>
                </div>
            </a>
            <span class="bg-gray-800 text-gray-400 text-[10px] px-2.5 py-0.5 rounded-full uppercase font-semibold">
                <?= escapar(ucfirst($_SESSION['usuario_role'])) ?>
            </span>
        </div>
        <div class="flex items-center space-x-1.5 sm:space-x-2 shrink-0 flex-nowrap overflow-x-auto">
            <a href="dashboard.php" class="text-xs text-gray-400 hover:text-white border border-gray-800 bg-gray-950/40 hover:bg-gray-800/60 px-2.5 py-1.5 rounded-lg transition shrink-0">Painel Principal</a>
            
            <?php if (in_array($_SESSION['usuario_role'], ['admin', 'supervisor', 'recepcao'])): ?>
                <a href="recepcao.php" class="text-xs text-gray-400 hover:text-white border border-gray-800 bg-gray-950/40 hover:bg-gray-800/60 px-2.5 py-1.5 rounded-lg transition shrink-0">Recepção</a>
            <?php endif; ?>

            <?php if (in_array($_SESSION['usuario_role'], ['admin', 'perito'])): ?>
                <a href="atendente.php" class="text-xs text-gray-400 hover:text-white border border-gray-800 bg-gray-950/40 hover:bg-gray-800/60 px-2.5 py-1.5 rounded-lg transition shrink-0">Atendimento</a>
            <?php endif; ?>

            <?php if (in_array($_SESSION['usuario_role'], ['admin', 'supervisor'])): ?>
                <a href="gerenciar_pauta.php" class="sga-nav-active text-xs text-white font-bold bg-[#002F6C] border border-blue-500 shadow-sm px-2.5 py-1.5 rounded-lg transition shrink-0">Pautas</a>
                <a href="salas.php" class="text-xs text-gray-400 hover:text-white border border-gray-800 bg-gray-950/40 hover:bg-gray-800/60 px-2.5 py-1.5 rounded-lg transition shrink-0">Salas</a>
                <a href="auditoria.php" class="text-xs text-gray-400 hover:text-white border border-gray-800 bg-gray-950/40 hover:bg-gray-800/60 px-2.5 py-1.5 rounded-lg transition shrink-0">Auditoria</a>
            <?php endif; ?>

            <?php if ($_SESSION['usuario_role'] === 'admin'): ?>
                <a href="usuarios.php" class="text-xs text-gray-400 hover:text-white border border-gray-800 bg-gray-950/40 hover:bg-gray-800/60 px-2.5 py-1.5 rounded-lg transition shrink-0">Perfis & Senhas</a>
            <?php endif; ?>

            <span class="text-xs text-gray-400 hidden sm:inline shrink-0">Olá, <strong class="text-white"><?= escapar($_SESSION['usuario_name']) ?></strong></span>
            <form method="POST" action="logout.php" class="sga-logout shrink-0"><?= csrfCampo() ?><button type="submit" class="bg-red-950/40 hover:bg-red-900/40 border border-red-900/60 text-red-400 text-xs px-2.5 py-1.5 rounded-lg transition">Sair</button></form>
        </div>
    </header>'''
    content = re.sub(header_old_re, header_new, content, flags=re.DOTALL)

    # Excel upload styling
    file_old = '''                    <div>
                        <label class="block text-xs text-gray-400 mb-1">Selecione o arquivo (.xlsx)</label>
                        <input type="file" name="arquivo_excel" accept=".xlsx" required class="w-full bg-gray-800 border border-gray-700 rounded-lg px-2 py-1.5 text-xs text-white file:bg-blue-600 file:border-none file:text-white file:text-xs file:font-semibold file:px-2.5 file:py-1 file:rounded-md file:mr-2 file:hover:bg-blue-750 file:cursor-pointer focus:outline-none">
                    </div>'''

    file_new = '''                    <div>
                        <label class="block text-xs text-gray-300 font-semibold mb-1.5">Selecione a Planilha (.xlsx)</label>
                        <div class="border border-dashed border-gray-700 hover:border-blue-500 rounded-xl p-3 text-center bg-gray-950/60 transition cursor-pointer group" onclick="document.getElementById('arquivo_excel_input').click()">
                            <span class="text-base block">📊</span>
                            <span id="excel_file_label" class="block text-xs text-gray-300 mt-1 font-medium truncate group-hover:text-blue-400 transition">Clique para escolher o arquivo</span>
                            <span class="block text-[10px] text-gray-500 mt-0.5">Formato oficial PJe (.xlsx)</span>
                            <input type="file" id="arquivo_excel_input" name="arquivo_excel" accept=".xlsx" required class="hidden" onchange="const l=document.getElementById('excel_file_label'); if(this.files && this.files[0]){ l.textContent=this.files[0].name; l.classList.add('text-blue-400','font-bold'); }">
                        </div>
                    </div>'''
    content = content.replace(file_old, file_new)

    # Status da Pauta banner
    banner_old_re = r'<div class="mb-6 p-4 rounded-xl border flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 <\?= \$pauta_liberada \? [^\n]+\n\s*<div class="flex items-center gap-3">.*?<\/form>\s*<\/div>'
    banner_new = '''<div class="mb-6 p-4 rounded-xl border flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 <?= $pauta_liberada ? 'bg-emerald-950/40 border-emerald-800/60 text-emerald-200' : 'bg-amber-950/40 border-amber-800/60 text-amber-200' ?> sga-pauta-status-banner">
                        <div class="flex items-center gap-3">
                            <span class="text-2xl"><?= $pauta_liberada ? '🟢' : '⚠️' ?></span>
                            <div>
                                <h4 class="text-xs uppercase font-black tracking-wider <?= $pauta_liberada ? 'text-emerald-300' : 'text-amber-300' ?>">Status da Pauta • <?= date('d/m/Y', strtotime($data_busca)) ?></h4>
                                <p class="text-xs <?= $pauta_liberada ? 'text-emerald-300' : 'text-amber-200' ?> mt-0.5 font-medium">
                                    <?= $pauta_liberada ? 'Homologada e liberada. A recepção comum pode confirmar a chegada dos pacientes.' : 'Pendente de homologação. O check-in da recepção está bloqueado para este dia.' ?>
                                </p>
                            </div>
                        </div>
                        <form method="POST" action="gerenciar_pauta.php?filtro_tipo=dia&data_busca=<?= $data_busca ?>" class="w-full sm:w-auto shrink-0"><?= csrfCampo() ?>
                            <input type="hidden" name="data_alvo" value="<?= escapar($data_busca) ?>">
                            <?php if ($pauta_liberada): ?>
                                <input type="hidden" name="action" value="bloquear_pauta">
                                <button type="submit" class="w-full sm:w-auto bg-amber-900/60 hover:bg-amber-800/80 border border-amber-700/80 text-amber-200 text-xs font-bold py-2 px-4 rounded-lg transition shadow-sm">
                                    🔒 Bloquear Pauta
                                </button>
                            <?php else: ?>
                                <input type="hidden" name="action" value="liberar_pauta">
                                <button type="submit" class="w-full sm:w-auto bg-[#007A33] hover:bg-[#006028] text-white text-xs font-bold py-2.5 px-5 rounded-lg shadow-md transition">
                                    🔓 Homologar e Liberar Pauta
                                </button>
                            <?php endif; ?>
                        </form>
                    </div>'''
    content = re.sub(banner_old_re, banner_new, content, flags=re.DOTALL)

    # Empty state
    empty_old_re = r'<\?php if \(empty\(\$atendimentos\)\): \?>\s*<tr>\s*<td colspan="6" class="py-8 text-center text-xs text-gray-500">.*?<\/td>\s*<\/tr>'
    empty_new = '''<?php if (empty($atendimentos)): ?>
                                    <tr>
                                        <td colspan="6" class="py-12 text-center">
                                            <div class="flex flex-col items-center justify-center space-y-2">
                                                <div class="w-10 h-10 rounded-full bg-gray-800/60 border border-gray-700/60 flex items-center justify-center text-lg text-gray-400">
                                                    📅
                                                </div>
                                                <p class="text-sm font-semibold text-gray-300">Nenhum agendamento encontrado para o período</p>
                                                <p class="text-xs text-gray-500">Utilize o formulário ao lado para importar a planilha (.xlsx) da pauta pericial.</p>
                                            </div>
                                        </td>
                                    </tr>'''
    content = re.sub(empty_old_re, empty_new, content, flags=re.DOTALL)

    with open('gerenciar_pauta.php', 'w', encoding='utf-8') as f:
        f.write(content)
    print('[OK] gerenciar_pauta.php atualizado')

def atualizar_dashboard():
    with open('dashboard.php', 'r', encoding='utf-8') as f:
        content = f.read()

    # Header navbar
    nav_old_re = r'<div class="flex items-center space-x-3">\s*<a href="dashboard\.php" class="text-xs text-white font-bold bg-blue-900/60 border border-blue-500 shadow-sm ring-1 ring-blue-500/30 px-3 py-1\.5 rounded-lg transition">Painel Principal</a>.*?<\/form>\s*<\/div>'
    nav_new = '''<div class="flex items-center space-x-1.5 sm:space-x-2 shrink-0 flex-nowrap overflow-x-auto">
            <a href="dashboard.php" class="sga-nav-active text-xs text-white font-bold bg-[#002F6C] border border-blue-500 shadow-sm px-2.5 py-1.5 rounded-lg transition shrink-0">Painel Principal</a>
            
            <?php if (in_array($_SESSION['usuario_role'], ['admin', 'supervisor', 'recepcao'])): ?>
                <a href="recepcao.php" class="text-xs text-gray-400 hover:text-white border border-gray-800 bg-gray-950/40 hover:bg-gray-800/60 px-2.5 py-1.5 rounded-lg transition shrink-0">Recepção</a>
            <?php endif; ?>

            <?php if (in_array($_SESSION['usuario_role'], ['admin', 'perito'])): ?>
                <a href="atendente.php" class="text-xs text-gray-400 hover:text-white border border-gray-800 bg-gray-950/40 hover:bg-gray-800/60 px-2.5 py-1.5 rounded-lg transition shrink-0">Atendimento</a>
            <?php endif; ?>

            <?php if (in_array($_SESSION['usuario_role'], ['admin', 'supervisor'])): ?>
                <a href="gerenciar_pauta.php" class="text-xs text-gray-400 hover:text-white border border-gray-800 bg-gray-950/40 hover:bg-gray-800/60 px-2.5 py-1.5 rounded-lg transition shrink-0">Pautas</a>
                <a href="salas.php" class="text-xs text-gray-400 hover:text-white border border-gray-800 bg-gray-950/40 hover:bg-gray-800/60 px-2.5 py-1.5 rounded-lg transition shrink-0">Salas</a>
                <a href="auditoria.php" class="text-xs text-gray-400 hover:text-white border border-gray-800 bg-gray-950/40 hover:bg-gray-800/60 px-2.5 py-1.5 rounded-lg transition shrink-0">Auditoria</a>
            <?php endif; ?>

            <?php if ($_SESSION['usuario_role'] === 'admin'): ?>
                <a href="usuarios.php" class="text-xs text-gray-400 hover:text-white border border-gray-800 bg-gray-950/40 hover:bg-gray-800/60 px-2.5 py-1.5 rounded-lg transition shrink-0">Perfis & Senhas</a>
            <?php endif; ?>

            <span class="text-xs text-gray-400 hidden sm:inline shrink-0">Olá, <strong class="text-white"><?= escapar($_SESSION['usuario_name']) ?></strong></span>
            <a href="alterar_senha_obrigatorio.php" class="text-xs text-gray-400 hover:text-white underline shrink-0">Minha senha</a>
            <form method="POST" action="logout.php" class="sga-logout shrink-0"><?= csrfCampo() ?><button type="submit" class="bg-red-950/40 hover:bg-red-900/40 border border-red-900/60 text-red-400 text-xs px-2.5 py-1.5 rounded-lg transition">Sair</button></form>
        </div>'''
    content = re.sub(nav_old_re, nav_new, content, flags=re.DOTALL)

    # Remove animate-pulse from Sistema Ativo
    content = content.replace('bg-emerald-500 inline-block animate-pulse', 'bg-emerald-500 inline-block')

    # Unify the 7 cards with .sga-dashboard-card and top accent line
    # Card 1: Recepção
    card_rec_old = r'<a href="recepcao\.php" class="bg-gray-900 border border-gray-800 hover:border-emerald-500/50 p-6 rounded-3xl shadow-xl hover:shadow-emerald-500/5 transition transform hover:-translate-y-1 group">\s*<div class="w-12 h-12 bg-emerald-950/50 border border-emerald-900/40 rounded-xl flex items-center justify-center text-xl mb-4 group-hover:scale-110 transition-transform">\s*📋\s*</div>\s*<h3 class="text-base font-bold text-white group-hover:text-emerald-400 transition-colors">Recepção</h3>\s*<p class="text-xs text-gray-400 mt-2 leading-relaxed">Confirmar chegada de periciados e gerenciar agendamentos do dia\.</p>\s*<div class="text-xs text-emerald-500 font-semibold mt-4 flex items-center gap-1 group-hover:translate-x-1 transition-transform">\s*Acessar Fila <span>→</span>\s*</div>\s*</a>'
    card_rec_new = '''<a href="recepcao.php" class="sga-dashboard-card bg-gray-900 border border-gray-800 border-t-2 border-t-blue-700 hover:border-blue-500/50 p-6 rounded-2xl shadow-xl hover:shadow-blue-500/5 transition transform hover:-translate-y-1 group">
                    <div class="w-12 h-12 bg-blue-950/50 border border-blue-900/40 rounded-xl flex items-center justify-center text-xl mb-4 group-hover:scale-110 transition-transform">
                        📋
                    </div>
                    <h3 class="text-base font-bold text-white group-hover:text-blue-400 transition-colors">Recepção</h3>
                    <p class="text-xs text-gray-400 mt-2 leading-relaxed">Confirmar chegada de periciados e gerenciar agendamentos do dia.</p>
                    <div class="text-xs text-blue-400 font-semibold mt-4 flex items-center gap-1 group-hover:translate-x-1 transition-transform">
                        Acessar Fila <span>→</span>
                    </div>
                </a>'''
    content = re.sub(card_rec_old, card_rec_new, content, flags=re.DOTALL)

    # Card 2: Atendente (Perito)
    card_per_old = r'<a href="atendente\.php" class="bg-gray-900 border border-gray-800 hover:border-blue-500/50 p-6 rounded-3xl shadow-xl hover:shadow-blue-500/5 transition transform hover:-translate-y-1 group">\s*<div class="w-12 h-12 bg-blue-950/50 border border-blue-900/40 rounded-xl flex items-center justify-center text-xl mb-4 group-hover:scale-110 transition-transform">\s*🧑‍⚕️\s*</div>\s*<h3 class="text-base font-bold text-white group-hover:text-blue-400 transition-colors">Painel do Perito</h3>\s*<p class="text-xs text-gray-400 mt-2 leading-relaxed">Chamar periciados por ordem de chegada para a sua sala de atendimento\.</p>\s*<div class="text-xs text-blue-500 font-semibold mt-4 flex items-center gap-1 group-hover:translate-x-1 transition-transform">\s*Chamar Paciente <span>→</span>\s*</div>\s*</a>'
    card_per_new = '''<a href="atendente.php" class="sga-dashboard-card bg-gray-900 border border-gray-800 border-t-2 border-t-blue-700 hover:border-blue-500/50 p-6 rounded-2xl shadow-xl hover:shadow-blue-500/5 transition transform hover:-translate-y-1 group">
                    <div class="w-12 h-12 bg-blue-950/50 border border-blue-900/40 rounded-xl flex items-center justify-center text-xl mb-4 group-hover:scale-110 transition-transform">
                        🧑‍⚕️
                    </div>
                    <h3 class="text-base font-bold text-white group-hover:text-blue-400 transition-colors">Painel do Perito</h3>
                    <p class="text-xs text-gray-400 mt-2 leading-relaxed">Chamar periciados por ordem de chegada para a sua sala de atendimento.</p>
                    <div class="text-xs text-blue-400 font-semibold mt-4 flex items-center gap-1 group-hover:translate-x-1 transition-transform">
                        Chamar Paciente <span>→</span>
                    </div>
                </a>'''
    content = re.sub(card_per_old, card_per_new, content, flags=re.DOTALL)

    # Card 3: TV
    card_tv_old = r'<a href="painel\.php" target="_blank" class="bg-gray-900 border border-gray-800 hover:border-amber-500/50 p-6 rounded-3xl shadow-xl hover:shadow-amber-500/5 transition transform hover:-translate-y-1 group">\s*<div class="w-12 h-12 bg-amber-950/50 border border-amber-900/40 rounded-xl flex items-center justify-center text-xl mb-4 group-hover:scale-110 transition-transform">\s*📺\s*</div>\s*<h3 class="text-base font-bold text-white group-hover:text-amber-400 transition-colors">Painel da TV</h3>\s*<p class="text-xs text-gray-400 mt-2 leading-relaxed">Tela de TV da recepção para exibição das chamadas de senhas e vídeo\.</p>\s*<div class="text-xs text-amber-500 font-semibold mt-4 flex items-center gap-1 group-hover:translate-x-1 transition-transform">\s*Abrir TV <span>→</span>\s*</div>\s*</a>'
    card_tv_new = '''<a href="painel.php" target="_blank" class="sga-dashboard-card bg-gray-900 border border-gray-800 border-t-2 border-t-blue-700 hover:border-blue-500/50 p-6 rounded-2xl shadow-xl hover:shadow-blue-500/5 transition transform hover:-translate-y-1 group">
                    <div class="w-12 h-12 bg-blue-950/50 border border-blue-900/40 rounded-xl flex items-center justify-center text-xl mb-4 group-hover:scale-110 transition-transform">
                        📺
                    </div>
                    <h3 class="text-base font-bold text-white group-hover:text-blue-400 transition-colors">Painel da TV</h3>
                    <p class="text-xs text-gray-400 mt-2 leading-relaxed">Tela de TV da recepção para exibição das chamadas de senhas e vídeo.</p>
                    <div class="text-xs text-blue-400 font-semibold mt-4 flex items-center gap-1 group-hover:translate-x-1 transition-transform">
                        Abrir TV <span>→</span>
                    </div>
                </a>'''
    content = re.sub(card_tv_old, card_tv_new, content, flags=re.DOTALL)

    # Card 4: Pautas
    card_pau_old = r'<a href="gerenciar_pauta\.php" class="bg-gray-900 border border-gray-800 hover:border-sky-500/50 p-6 rounded-3xl shadow-xl hover:shadow-sky-500/5 transition transform hover:-translate-y-1 group">\s*<div class="w-12 h-12 bg-sky-950/50 border border-sky-900/40 rounded-xl flex items-center justify-center text-xl mb-4 group-hover:scale-110 transition-transform">\s*📅\s*</div>\s*<h3 class="text-base font-bold text-white group-hover:text-sky-400 transition-colors">Gerenciar Pautas</h3>\s*<p class="text-xs text-gray-400 mt-2 leading-relaxed">Filtrar, excluir e transferir agendamentos de forma massiva por dia ou mês inteiro\.</p>\s*<div class="text-xs text-sky-500 font-semibold mt-4 flex items-center gap-1 group-hover:translate-x-1 transition-transform">\s*Gerenciar Pauta <span>→</span>\s*</div>\s*</a>'
    card_pau_new = '''<a href="gerenciar_pauta.php" class="sga-dashboard-card bg-gray-900 border border-gray-800 border-t-2 border-t-blue-700 hover:border-blue-500/50 p-6 rounded-2xl shadow-xl hover:shadow-blue-500/5 transition transform hover:-translate-y-1 group">
                    <div class="w-12 h-12 bg-blue-950/50 border border-blue-900/40 rounded-xl flex items-center justify-center text-xl mb-4 group-hover:scale-110 transition-transform">
                        📅
                    </div>
                    <h3 class="text-base font-bold text-white group-hover:text-blue-400 transition-colors">Gerenciar Pautas</h3>
                    <p class="text-xs text-gray-400 mt-2 leading-relaxed">Filtrar, excluir e transferir agendamentos de forma massiva por dia ou mês inteiro.</p>
                    <div class="text-xs text-blue-400 font-semibold mt-4 flex items-center gap-1 group-hover:translate-x-1 transition-transform">
                        Gerenciar Pauta <span>→</span>
                    </div>
                </a>'''
    content = re.sub(card_pau_old, card_pau_new, content, flags=re.DOTALL)

    # Card 5: Salas
    card_sal_old = r'<a href="salas\.php" class="bg-gray-900 border border-gray-800 hover:border-pink-500/50 p-6 rounded-3xl shadow-xl hover:shadow-pink-500/5 transition transform hover:-translate-y-1 group">\s*<div class="w-12 h-12 bg-pink-950/50 border border-pink-900/40 rounded-xl flex items-center justify-center text-xl mb-4 group-hover:scale-110 transition-transform">\s*🚪\s*</div>\s*<h3 class="text-base font-bold text-white group-hover:text-pink-400 transition-colors">Salas de Atendimento</h3>\s*<p class="text-xs text-gray-400 mt-2 leading-relaxed">Cadastrar e gerenciar salas físicas que estarão disponíveis para escolha dos peritos\.</p>\s*<div class="text-xs text-pink-500 font-semibold mt-4 flex items-center gap-1 group-hover:translate-x-1 transition-transform">\s*Gerenciar Salas <span>→</span>\s*</div>\s*</a>'
    card_sal_new = '''<a href="salas.php" class="sga-dashboard-card bg-gray-900 border border-gray-800 border-t-2 border-t-blue-700 hover:border-blue-500/50 p-6 rounded-2xl shadow-xl hover:shadow-blue-500/5 transition transform hover:-translate-y-1 group">
                    <div class="w-12 h-12 bg-blue-950/50 border border-blue-900/40 rounded-xl flex items-center justify-center text-xl mb-4 group-hover:scale-110 transition-transform">
                        🚪
                    </div>
                    <h3 class="text-base font-bold text-white group-hover:text-blue-400 transition-colors">Salas de Atendimento</h3>
                    <p class="text-xs text-gray-400 mt-2 leading-relaxed">Cadastrar e gerenciar salas físicas com monitoramento de ocupação em tempo real.</p>
                    <div class="text-xs text-blue-400 font-semibold mt-4 flex items-center gap-1 group-hover:translate-x-1 transition-transform">
                        Gerenciar Salas <span>→</span>
                    </div>
                </a>'''
    content = re.sub(card_sal_old, card_sal_new, content, flags=re.DOTALL)

    # Card 6: Auditoria
    card_aud_old = r'<a href="auditoria\.php" class="bg-gray-900 border border-gray-800 hover:border-teal-500/50 p-6 rounded-3xl shadow-xl hover:shadow-teal-500/5 transition transform hover:-translate-y-1 group">\s*<div class="w-12 h-12 bg-teal-950/50 border border-teal-900/40 rounded-xl flex items-center justify-center text-xl mb-4 group-hover:scale-110 transition-transform">\s*🔎\s*</div>\s*<h3 class="text-base font-bold text-white group-hover:text-teal-400 transition-colors">Auditoria de Chamadas</h3>\s*<p class="text-xs text-gray-400 mt-2 leading-relaxed">Histórico completo de atendimentos, logs de chamadas, filtros por perito e exportação CSV\.</p>\s*<div class="text-xs text-teal-500 font-semibold mt-4 flex items-center gap-1 group-hover:translate-x-1 transition-transform">\s*Ver Auditoria <span>→</span>\s*</div>\s*</a>'
    card_aud_new = '''<a href="auditoria.php" class="sga-dashboard-card bg-gray-900 border border-gray-800 border-t-2 border-t-blue-700 hover:border-blue-500/50 p-6 rounded-2xl shadow-xl hover:shadow-blue-500/5 transition transform hover:-translate-y-1 group">
                    <div class="w-12 h-12 bg-blue-950/50 border border-blue-900/40 rounded-xl flex items-center justify-center text-xl mb-4 group-hover:scale-110 transition-transform">
                        🔎
                    </div>
                    <h3 class="text-base font-bold text-white group-hover:text-blue-400 transition-colors">Auditoria de Chamadas</h3>
                    <p class="text-xs text-gray-400 mt-2 leading-relaxed">Histórico completo de atendimentos, logs de chamadas, filtros por perito e exportação CSV.</p>
                    <div class="text-xs text-blue-400 font-semibold mt-4 flex items-center gap-1 group-hover:translate-x-1 transition-transform">
                        Ver Auditoria <span>→</span>
                    </div>
                </a>'''
    content = re.sub(card_aud_old, card_aud_new, content, flags=re.DOTALL)

    # Card 7: Perfis & Senhas
    card_usr_old = r'<a href="usuarios\.php" class="bg-gray-900 border border-gray-800 hover:border-indigo-500/50 p-6 rounded-3xl shadow-xl hover:shadow-indigo-500/5 transition transform hover:-translate-y-1 group">\s*<div class="w-12 h-12 bg-indigo-950/50 border border-indigo-900/40 rounded-xl flex items-center justify-center text-xl mb-4 group-hover:scale-110 transition-transform">\s*🔑\s*</div>\s*<h3 class="text-base font-bold text-white group-hover:text-indigo-400 transition-colors">Perfis e Senhas</h3>\s*<p class="text-xs text-gray-400 mt-2 leading-relaxed">Gerenciar contas de acesso, senhas e perfis de recepção e administrador\.</p>\s*<div class="text-xs text-indigo-500 font-semibold mt-4 flex items-center gap-1 group-hover:translate-x-1 transition-transform">\s*Gerenciar Contas <span>→</span>\s*</div>\s*</a>'
    card_usr_new = '''<a href="usuarios.php" class="sga-dashboard-card bg-gray-900 border border-gray-800 border-t-2 border-t-blue-700 hover:border-blue-500/50 p-6 rounded-2xl shadow-xl hover:shadow-blue-500/5 transition transform hover:-translate-y-1 group">
                    <div class="w-12 h-12 bg-blue-950/50 border border-blue-900/40 rounded-xl flex items-center justify-center text-xl mb-4 group-hover:scale-110 transition-transform">
                        🔑
                    </div>
                    <h3 class="text-base font-bold text-white group-hover:text-blue-400 transition-colors">Perfis e Senhas</h3>
                    <p class="text-xs text-gray-400 mt-2 leading-relaxed">Gerenciar contas de acesso, senhas e perfis de recepção e administrador.</p>
                    <div class="text-xs text-blue-400 font-semibold mt-4 flex items-center gap-1 group-hover:translate-x-1 transition-transform">
                        Gerenciar Contas <span>→</span>
                    </div>
                </a>'''
    content = re.sub(card_usr_old, card_usr_new, content, flags=re.DOTALL)

    with open('dashboard.php', 'w', encoding='utf-8') as f:
        f.write(content)
    print('[OK] dashboard.php atualizado')

def atualizar_atendente():
    with open('atendente.php', 'r', encoding='utf-8') as f:
        content = f.read()

    # Header navbar
    nav_old_re = r'<div class="flex items-center space-x-3">\s*<a href="dashboard\.php".*?<\/form>\s*<\/div>'
    nav_new = '''<div class="flex items-center space-x-1.5 sm:space-x-2 shrink-0 flex-nowrap overflow-x-auto">
            <a href="dashboard.php" class="text-xs text-gray-400 hover:text-white border border-gray-800 bg-gray-950/40 hover:bg-gray-800/60 px-2.5 py-1.5 rounded-lg transition shrink-0">Painel Principal</a>
            
            <?php if (in_array($_SESSION['usuario_role'], ['admin', 'supervisor', 'recepcao'])): ?>
                <a href="recepcao.php" class="text-xs text-gray-400 hover:text-white border border-gray-800 bg-gray-950/40 hover:bg-gray-800/60 px-2.5 py-1.5 rounded-lg transition shrink-0">Recepção</a>
            <?php endif; ?>

            <a href="atendente.php" class="sga-nav-active text-xs text-white font-bold bg-[#002F6C] border border-blue-500 shadow-sm px-2.5 py-1.5 rounded-lg transition shrink-0">Atendimento</a>

            <?php if (in_array($_SESSION['usuario_role'], ['admin', 'supervisor'])): ?>
                <a href="gerenciar_pauta.php" class="text-xs text-gray-400 hover:text-white border border-gray-800 bg-gray-950/40 hover:bg-gray-800/60 px-2.5 py-1.5 rounded-lg transition shrink-0">Pautas</a>
                <a href="salas.php" class="text-xs text-gray-400 hover:text-white border border-gray-800 bg-gray-950/40 hover:bg-gray-800/60 px-2.5 py-1.5 rounded-lg transition shrink-0">Salas</a>
                <a href="auditoria.php" class="text-xs text-gray-400 hover:text-white border border-gray-800 bg-gray-950/40 hover:bg-gray-800/60 px-2.5 py-1.5 rounded-lg transition shrink-0">Auditoria</a>
            <?php endif; ?>

            <?php if ($_SESSION['usuario_role'] === 'admin'): ?>
                <a href="usuarios.php" class="text-xs text-gray-400 hover:text-white border border-gray-800 bg-gray-950/40 hover:bg-gray-800/60 px-2.5 py-1.5 rounded-lg transition shrink-0">Perfis & Senhas</a>
            <?php endif; ?>

            <span class="text-xs text-gray-400 hidden sm:inline shrink-0">Olá, <strong class="text-white"><?= escapar($_SESSION['usuario_name']) ?></strong></span>
            <button type="button" id="btn-audio-perito" onclick="toggleAudioPerito()" class="text-xs text-blue-400 hover:text-blue-300 border border-blue-900/60 bg-blue-950/40 px-2.5 py-1.5 rounded-lg transition flex items-center gap-1 shrink-0">
                <span id="icone-audio-perito">🔊</span> <span id="texto-audio-perito">Som Ativo</span>
            </button>
            <form method="POST" action="logout.php" class="sga-logout shrink-0"><?= csrfCampo() ?><button type="submit" class="bg-red-950/40 hover:bg-red-900/40 border border-red-900/60 text-red-400 text-xs px-2.5 py-1.5 rounded-lg transition">Sair</button></form>
        </div>'''
    content = re.sub(nav_old_re, nav_new, content, flags=re.DOTALL)

    # Mobile ordering: swap layout order so Call Card is top on mobile
    content = content.replace(
        '<div class="space-y-6">\n            <!-- Seleção de Nome do Perito -->',
        '<div class="space-y-6 order-2 md:order-1">\n            <!-- Seleção de Nome do Perito -->'
    )
    content = content.replace(
        '<div class="md:col-span-2 space-y-6">\n            <!-- Alerta do Status da Sala -->',
        '<div class="md:col-span-2 space-y-6 order-1 md:order-2">\n            <!-- Alerta do Status da Sala -->'
    )

    # Remove animate-pulse on Sincronizada
    content = content.replace('bg-emerald-500 inline-block animate-pulse', 'bg-emerald-500 inline-block')

    # Mobile touch scroll on table
    content = content.replace(
        '<div class="overflow-x-auto max-h-[350px] overflow-y-auto">',
        '<div class="overflow-x-auto sga-touch-scroll max-h-[420px] overflow-y-auto">'
    )

    with open('atendente.php', 'w', encoding='utf-8') as f:
        f.write(content)
    print('[OK] atendente.php atualizado')

def atualizar_recepcao():
    with open('recepcao.php', 'r', encoding='utf-8') as f:
        content = f.read()

    # Header navbar
    nav_old_re = r'<div class="flex items-center space-x-3">\s*<a href="dashboard\.php".*?<\/form>\s*<\/div>'
    nav_new = '''<div class="flex items-center space-x-1.5 sm:space-x-2 shrink-0 flex-nowrap overflow-x-auto">
            <a href="dashboard.php" class="text-xs text-gray-400 hover:text-white border border-gray-800 bg-gray-950/40 hover:bg-gray-800/60 px-2.5 py-1.5 rounded-lg transition shrink-0">Painel Principal</a>
            
            <?php if (in_array($_SESSION['usuario_role'], ['admin', 'supervisor', 'recepcao'])): ?>
                <a href="recepcao.php" class="sga-nav-active text-xs text-white font-bold bg-[#002F6C] border border-blue-500 shadow-sm px-2.5 py-1.5 rounded-lg transition shrink-0">Recepção</a>
            <?php endif; ?>

            <?php if (in_array($_SESSION['usuario_role'], ['admin', 'perito'])): ?>
                <a href="atendente.php" class="text-xs text-gray-400 hover:text-white border border-gray-800 bg-gray-950/40 hover:bg-gray-800/60 px-2.5 py-1.5 rounded-lg transition shrink-0">Atendimento</a>
            <?php endif; ?>

            <?php if (in_array($_SESSION['usuario_role'], ['admin', 'supervisor'])): ?>
                <a href="gerenciar_pauta.php" class="text-xs text-gray-400 hover:text-white border border-gray-800 bg-gray-950/40 hover:bg-gray-800/60 px-2.5 py-1.5 rounded-lg transition shrink-0">Pautas</a>
                <a href="salas.php" class="text-xs text-gray-400 hover:text-white border border-gray-800 bg-gray-950/40 hover:bg-gray-800/60 px-2.5 py-1.5 rounded-lg transition shrink-0">Salas</a>
                <a href="auditoria.php" class="text-xs text-gray-400 hover:text-white border border-gray-800 bg-gray-950/40 hover:bg-gray-800/60 px-2.5 py-1.5 rounded-lg transition shrink-0">Auditoria</a>
            <?php endif; ?>

            <?php if ($_SESSION['usuario_role'] === 'admin'): ?>
                <a href="usuarios.php" class="text-xs text-gray-400 hover:text-white border border-gray-800 bg-gray-950/40 hover:bg-gray-800/60 px-2.5 py-1.5 rounded-lg transition shrink-0">Perfis & Senhas</a>
            <?php endif; ?>

            <span class="text-xs text-gray-400 hidden sm:inline shrink-0">Olá, <strong class="text-white"><?= escapar($_SESSION['usuario_name']) ?></strong></span>
            <form method="POST" action="logout.php" class="sga-logout shrink-0"><?= csrfCampo() ?><button type="submit" class="bg-red-950/40 hover:bg-red-900/40 border border-red-900/60 text-red-400 text-xs px-2.5 py-1.5 rounded-lg transition">Sair</button></form>
        </div>'''
    content = re.sub(nav_old_re, nav_new, content, flags=re.DOTALL)

    # Neutral CPF (no emerald green on document numbers)
    content = content.replace(
        '<span class="text-emerald-400 ml-1.5">| CPF: <?= escapar($item[\'cpf\']) ?></span>',
        '<span class="text-gray-400 ml-1.5 font-mono">| CPF: <?= escapar($item[\'cpf\']) ?></span>'
    )

    # Title of Aguardando Chamada
    content = content.replace(
        '<h2 class="text-sm font-semibold uppercase text-emerald-400 tracking-wider flex items-center gap-2">',
        '<h2 class="text-sm font-semibold uppercase text-white tracking-wider flex items-center gap-2">'
    )
    content = content.replace(
        '<span class="bg-emerald-950 text-emerald-400 text-xs px-2.5 py-0.5 rounded-full" id="contador-presentes"><?= count($presentes) ?></span>',
        '<span class="bg-blue-950/70 border border-blue-900/60 text-blue-300 text-xs px-2.5 py-0.5 rounded-full font-bold" id="contador-presentes"><?= count($presentes) ?></span>'
    )

    # "Próximo (0 na frente)" contrast
    content = content.replace(
        '? \'<span class="text-emerald-400 font-semibold">Próximo (0 na frente)</span>\'',
        '? \'<span class="text-blue-400 font-semibold">Próximo (0 na frente)</span>\''
    )

    # Replace purple Declaração buttons with Azul JFAL
    content = content.replace(
        'class="bg-indigo-950/70 hover:bg-indigo-900 border border-indigo-700/60 text-indigo-300 text-xs px-2 py-1 rounded transition flex items-center gap-1"',
        'class="bg-blue-950/70 hover:bg-blue-900 border border-blue-800/60 text-blue-300 text-xs px-2.5 py-1 rounded-lg transition flex items-center gap-1 font-semibold"'
    )
    content = content.replace(
        'class="bg-indigo-950/80 hover:bg-indigo-900 border border-indigo-700/70 text-indigo-300 text-xs px-2.5 py-1 rounded-lg transition flex items-center gap-1 font-semibold"',
        'class="bg-blue-950/70 hover:bg-blue-900 border border-blue-800/60 text-blue-300 text-xs px-2.5 py-1 rounded-lg transition flex items-center gap-1 font-semibold"'
    )

    # Add dynamic AJAX handler for batch check-in and batch return without page reload
    ajax_batch_script = '''
        // Intercepta confirmação em lote via AJAX (sem recarregar página)
        document.getElementById('form-agendados')?.addEventListener('submit', async function(e) {
            const submitter = e.submitter;
            if (submitter && submitter.value === 'confirmar_multiplos') {
                e.preventDefault();
                const chks = Array.from(this.querySelectorAll('.chk-item:checked'));
                if (chks.length === 0) return;
                
                submitter.disabled = true;
                const originalText = submitter.innerHTML;
                submitter.innerHTML = '⏳ Confirmando...';
                
                const formData = new FormData(this);
                formData.set('action', 'confirmar_multiplos');
                
                try {
                    const resp = await fetch(window.location.href, {
                        method: 'POST',
                        body: formData,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    const res = await resp.json();
                    if (res.csrf_token) {
                        document.querySelectorAll('input[name="csrf"]').forEach(i => i.value = res.csrf_token);
                    }
                    if (res.success) {
                        exibirToastRecepcao(res.message, 'success');
                        chks.forEach(chk => {
                            const r = chk.closest('tr');
                            if (r) r.remove();
                        });
                        const badgeAgendados = document.querySelector('#form-agendados h2 span:last-child');
                        if (badgeAgendados) {
                            const val = Math.max(0, (parseInt(badgeAgendados.textContent.trim(), 10) || 0) - chks.length);
                            badgeAgendados.textContent = val;
                        }
                        if (typeof recarregarFilaPresentes === 'function') await recarregarFilaPresentes();
                        if (typeof sincronizarPeritosRecepcao === 'function') sincronizarPeritosRecepcao();
                    } else {
                        exibirToastRecepcao(res.message || 'Erro ao confirmar.', 'error');
                    }
                } catch(err) {
                    this.submit();
                } finally {
                    submitter.disabled = false;
                    submitter.innerHTML = originalText;
                }
            }
        });

        // Intercepta retorno em lote da fila via AJAX
        document.getElementById('form-presentes')?.addEventListener('submit', async function(e) {
            const submitter = e.submitter;
            if (submitter && submitter.value === 'remover_multiplos') {
                e.preventDefault();
                const chks = Array.from(this.querySelectorAll('.chk-presente-item:checked'));
                if (chks.length === 0) return;
                
                submitter.disabled = true;
                const originalText = submitter.innerHTML;
                submitter.innerHTML = '⏳ Retornando...';
                
                const formData = new FormData(this);
                formData.set('action', 'remover_multiplos');
                
                try {
                    const resp = await fetch(window.location.href, {
                        method: 'POST',
                        body: formData,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    const res = await resp.json();
                    if (res.csrf_token) {
                        document.querySelectorAll('input[name="csrf"]').forEach(i => i.value = res.csrf_token);
                    }
                    if (res.success) {
                        exibirToastRecepcao(res.message, 'info');
                        chks.forEach(chk => {
                            const r = chk.closest('tr');
                            if (r) r.remove();
                        });
                        if (typeof recarregarFilaPresentes === 'function') await recarregarFilaPresentes();
                        if (typeof sincronizarPeritosRecepcao === 'function') sincronizarPeritosRecepcao();
                    } else {
                        exibirToastRecepcao(res.message || 'Erro ao retornar.', 'error');
                    }
                } catch(err) {
                    this.submit();
                } finally {
                    submitter.disabled = false;
                    submitter.innerHTML = originalText;
                }
            }
        });
'''
    if 'document.getElementById(\'form-agendados\')?.addEventListener(\'submit\'' not in content:
        content = content.replace('// Confirma chegada individual via AJAX', ajax_batch_script + '\n        // Confirma chegada individual via AJAX')

    with open('recepcao.php', 'w', encoding='utf-8') as f:
        f.write(content)
    print('[OK] recepcao.php atualizado')

def atualizar_auditoria():
    with open('auditoria.php', 'r', encoding='utf-8') as f:
        content = f.read()

    header_old_re = r'    <!-- Header -->\s*<header class="bg-gray-900 border-b border-gray-800 px-6 py-4 flex items-center justify-between shadow-md shrink-0">.*?</header>'
    header_new = '''    <!-- Header -->
    <header class="bg-gray-900 border-b border-gray-800 px-6 py-4 flex items-center justify-between shadow-md shrink-0">
        <div class="flex items-center space-x-3 shrink-0">
            <a href="dashboard.php" class="flex items-center space-x-2.5 group hover:opacity-95 transition">
                <img src="assets/catavento-jfal.png" alt="JFAL" class="h-8 w-auto object-contain transition-transform duration-300 group-hover:scale-105">
                <div class="hidden sm:block leading-tight">
                    <h1 class="text-xs font-black tracking-wide text-blue-500 uppercase leading-none group-hover:text-blue-400 transition-colors">Justiça Federal em Alagoas</h1>
                    <p class="text-[8px] text-gray-400 font-semibold tracking-widest uppercase mt-0.5">Seção Judiciária de Alagoas</p>
                </div>
            </a>
            <span class="bg-gray-800 text-gray-400 text-[10px] px-2.5 py-0.5 rounded-full uppercase font-semibold">
                <?= escapar(ucfirst($_SESSION['usuario_role'])) ?>
            </span>
        </div>
        <div class="flex items-center space-x-1.5 sm:space-x-2 shrink-0 flex-nowrap overflow-x-auto">
            <a href="dashboard.php" class="text-xs text-gray-400 hover:text-white border border-gray-800 bg-gray-950/40 hover:bg-gray-800/60 px-2.5 py-1.5 rounded-lg transition shrink-0">Painel Principal</a>
            
            <?php if (in_array($_SESSION['usuario_role'], ['admin', 'supervisor', 'recepcao'])): ?>
                <a href="recepcao.php" class="text-xs text-gray-400 hover:text-white border border-gray-800 bg-gray-950/40 hover:bg-gray-800/60 px-2.5 py-1.5 rounded-lg transition shrink-0">Recepção</a>
            <?php endif; ?>

            <?php if (in_array($_SESSION['usuario_role'], ['admin', 'perito'])): ?>
                <a href="atendente.php" class="text-xs text-gray-400 hover:text-white border border-gray-800 bg-gray-950/40 hover:bg-gray-800/60 px-2.5 py-1.5 rounded-lg transition shrink-0">Atendimento</a>
            <?php endif; ?>

            <?php if (in_array($_SESSION['usuario_role'], ['admin', 'supervisor'])): ?>
                <a href="gerenciar_pauta.php" class="text-xs text-gray-400 hover:text-white border border-gray-800 bg-gray-950/40 hover:bg-gray-800/60 px-2.5 py-1.5 rounded-lg transition shrink-0">Pautas</a>
                <a href="salas.php" class="text-xs text-gray-400 hover:text-white border border-gray-800 bg-gray-950/40 hover:bg-gray-800/60 px-2.5 py-1.5 rounded-lg transition shrink-0">Salas</a>
                <a href="auditoria.php" class="sga-nav-active text-xs text-white font-bold bg-[#002F6C] border border-blue-500 shadow-sm px-2.5 py-1.5 rounded-lg transition shrink-0">Auditoria</a>
            <?php endif; ?>

            <?php if ($_SESSION['usuario_role'] === 'admin'): ?>
                <a href="usuarios.php" class="text-xs text-gray-400 hover:text-white border border-gray-800 bg-gray-950/40 hover:bg-gray-800/60 px-2.5 py-1.5 rounded-lg transition shrink-0">Perfis & Senhas</a>
            <?php endif; ?>

            <span class="text-xs text-gray-400 hidden sm:inline shrink-0">Olá, <strong class="text-white"><?= escapar($_SESSION['usuario_name']) ?></strong></span>
            <form method="POST" action="logout.php" class="sga-logout shrink-0"><?= csrfCampo() ?><button type="submit" class="bg-red-950/40 hover:bg-red-900/40 border border-red-900/60 text-red-400 text-xs px-2.5 py-1.5 rounded-lg transition">Sair</button></form>
        </div>
    </header>'''
    content = re.sub(header_old_re, header_new, content, flags=re.DOTALL)

    with open('auditoria.php', 'w', encoding='utf-8') as f:
        f.write(content)
    print('[OK] auditoria.php atualizado')

def atualizar_painel():
    with open('painel.php', 'r', encoding='utf-8') as f:
        content = f.read()

    # Room in overlay modal
    content = content.replace(
        '<div id="overlay-sala" class="text-4xl md:text-5xl font-black text-emerald-400">--</div>',
        '<div id="overlay-sala" class="text-4xl md:text-5xl font-black text-blue-400">--</div>'
    )

    # Room in footer
    content = content.replace(
        '<span class="text-[10px] text-emerald-400 font-semibold truncate"><?= escapar($item[\'sala\']) ?></span>',
        '<span class="text-[10px] text-blue-400 font-semibold truncate"><?= escapar($item[\'sala\']) ?></span>'
    )

    with open('painel.php', 'w', encoding='utf-8') as f:
        f.write(content)
    print('[OK] painel.php atualizado')

def atualizar_declaracao():
    with open('declaracao_comparecimento.php', 'r', encoding='utf-8') as f:
        content = f.read()

    # Button print style
    content = content.replace('background: #1e3a8a; color: #fff;', 'background: #002F6C; color: #fff;')

    # Spacing adjustments so it never spills over 1 A4 page
    content = content.replace('padding: 18mm 20mm 15mm 20mm;', 'padding: 12mm 16mm 10mm 16mm;')
    content = content.replace('margin-bottom: 18px;', 'margin-bottom: 10px;')
    content = content.replace('margin: 20px 0 20px 0;', 'margin: 12px 0 12px 0;')
    content = content.replace('margin-bottom: 16px;\n            font-size: 12pt;', 'margin-bottom: 12px;\n            font-size: 11pt;')
    content = content.replace('margin: 16px 0;\n            border: 1px solid #444;', 'margin: 10px 0;\n            border: 1px solid #444;')
    content = content.replace('margin-top: 22px;\n            text-align: right;', 'margin-top: 14px;\n            text-align: right;')
    content = content.replace('margin-top: 32px;\n            display: flex;', 'margin-top: 20px;\n            display: flex;')
    content = content.replace('margin-top: 20px;\n            font-size: 8pt;', 'margin-top: 12px;\n            font-size: 7.5pt;')

    # @media print calibration
    print_old_re = r'@media print \{.*?@page \{.*?\}\s*\}'
    print_new = '''@media print {
            html, body {
                background: #fff !important;
                padding: 0 !important;
                margin: 0 !important;
                height: auto !important;
                overflow: visible !important;
            }
            .barra-acoes, .no-print {
                display: none !important;
            }
            .folha {
                box-shadow: none !important;
                margin: 0 !important;
                width: 100% !important;
                min-height: auto !important;
                height: auto !important;
                max-height: none !important;
                padding: 0 !important;
                page-break-inside: avoid !important;
                page-break-after: avoid !important;
                justify-content: flex-start !important;
            }
            @page {
                size: A4 portrait;
                margin: 12mm 15mm 10mm 15mm;
            }
        }'''
    content = re.sub(print_old_re, print_new, content, flags=re.DOTALL)

    with open('declaracao_comparecimento.php', 'w', encoding='utf-8') as f:
        f.write(content)
    print('[OK] declaracao_comparecimento.php atualizado')

if __name__ == '__main__':
    atualizar_gerenciar_pauta()
    atualizar_dashboard()
    atualizar_atendente()
    atualizar_recepcao()
    atualizar_auditoria()
    atualizar_painel()
    atualizar_declaracao()
    print('Todas as atualizacoes foram concluidas com sucesso.')
