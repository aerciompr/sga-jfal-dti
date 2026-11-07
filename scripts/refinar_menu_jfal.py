# -*- coding: utf-8 -*-
"""
Script de Refinamento Final do Menu Institucional - SGA JFAL
Aplica a barra segmentada perfeita, ordem lógica de utilitários,
e suporte responsivo em todos os arquivos.
"""
import re
import os

PAGES = {
    'dashboard.php': 'dashboard.php',
    'recepcao.php': 'recepcao.php',
    'atendente.php': 'atendente.php',
    'gerenciar_pauta.php': 'gerenciar_pauta.php',
    'salas.php': 'salas.php',
    'auditoria.php': 'auditoria.php',
    'usuarios.php': 'usuarios.php'
}

def gerar_header(pagina_atual):
    som_button = ""
    if pagina_atual == 'atendente.php':
        som_button = '''
            <button type="button" id="btn-audio-perito" onclick="toggleAudioPerito()" class="text-xs text-blue-400 hover:text-blue-300 border border-blue-900/60 bg-blue-950/40 px-2.5 py-1.5 rounded-lg transition flex items-center gap-1 shrink-0">
                <span id="icone-audio-perito">🔊</span> <span id="texto-audio-perito">Som Ativo</span>
            </button>'''

    return f'''    <!-- Header Institucional Unificado JFAL -->
    <header class="bg-gray-900 border-b border-gray-800 px-6 py-3 flex items-center justify-between shadow-md shrink-0">
        <!-- 1. Identidade Oficial JFAL -->
        <div class="flex items-center space-x-3 shrink-0">
            <a href="dashboard.php" class="sga-brand-link flex items-center space-x-2.5 group hover:opacity-95 transition">
                <img src="assets/catavento-jfal.png" alt="JFAL" class="h-8 w-auto object-contain transition-transform duration-300 group-hover:scale-105">
                <div class="hidden sm:block leading-tight">
                    <h1 class="text-xs font-black tracking-wide text-blue-500 uppercase leading-none group-hover:text-blue-400 transition-colors">Justiça Federal em Alagoas</h1>
                    <p class="text-[8px] text-gray-400 font-semibold tracking-widest uppercase mt-0.5">Seção Judiciária de Alagoas</p>
                </div>
            </a>
            <span class="sga-role-badge bg-gray-800 text-gray-400 text-[10px] px-2.5 py-0.5 rounded-full uppercase font-bold tracking-wider border border-gray-700">
                <?= escapar(ucfirst($_SESSION['usuario_role'])) ?>
            </span>
        </div>

        <!-- 2. Barra de Navegação Segmentada (Centro) -->
        <nav class="sga-nav-bar flex items-center gap-1 shrink-0 overflow-x-auto">
            <a href="dashboard.php" class="sga-nav-item{' sga-nav-active' if pagina_atual == 'dashboard.php' else ''}">Painel Principal</a>
            
            <?php if (in_array($_SESSION['usuario_role'], ['admin', 'supervisor', 'recepcao'])): ?>
                <a href="recepcao.php" class="sga-nav-item{' sga-nav-active' if pagina_atual == 'recepcao.php' else ''}">Recepção</a>
            <?php endif; ?>

            <?php if (in_array($_SESSION['usuario_role'], ['admin', 'perito'])): ?>
                <a href="atendente.php" class="sga-nav-item{' sga-nav-active' if pagina_atual == 'atendente.php' else ''}">Atendimento</a>
            <?php endif; ?>

            <?php if (in_array($_SESSION['usuario_role'], ['admin', 'supervisor'])): ?>
                <a href="gerenciar_pauta.php" class="sga-nav-item{' sga-nav-active' if pagina_atual == 'gerenciar_pauta.php' else ''}">Pautas</a>
                <a href="salas.php" class="sga-nav-item{' sga-nav-active' if pagina_atual == 'salas.php' else ''}">Salas</a>
                <a href="auditoria.php" class="sga-nav-item{' sga-nav-active' if pagina_atual == 'auditoria.php' else ''}">Auditoria</a>
            <?php endif; ?>

            <?php if ($_SESSION['usuario_role'] === 'admin'): ?>
                <a href="usuarios.php" class="sga-nav-item{' sga-nav-active' if pagina_atual == 'usuarios.php' else ''}">Perfis & Senhas</a>
            <?php endif; ?>
        </nav>

        <!-- 3. Grupo de Utilitários (Direita) -->
        <div class="sga-header-utils flex items-center space-x-2 shrink-0">{som_button}
            <div class="h-4 w-px bg-gray-700/60 mx-1 hidden sm:block sga-divider"></div>

            <div class="sga-header-user flex items-center gap-1.5 shrink-0 text-xs text-gray-400">
                <span>Olá, <strong class="text-white"><?= escapar($_SESSION['usuario_name']) ?></strong></span>
                <span class="text-gray-600 sga-bullet">•</span>
                <a href="alterar_senha_obrigatorio.php" class="text-gray-400 hover:text-white underline text-xs">Minha senha</a>
            </div>

            <form method="POST" action="logout.php" class="sga-logout shrink-0">
                <?= csrfCampo() ?>
                <button type="submit" class="sga-btn-sair bg-red-950/40 hover:bg-red-900/40 border border-red-900/60 text-red-400 text-xs px-2.5 py-1.5 rounded-lg transition font-medium">Sair</button>
            </form>
        </div>
    </header>'''

def atualizar_headers_php():
    for fname, p_atual in PAGES.items():
        if not os.path.exists(fname):
            continue
        with open(fname, 'r', encoding='utf-8') as f:
            content = f.read()

        # Substitui tag header inteira
        header_pattern = r'    <!-- Header.*?-->\s*<header.*?</header>'
        novo_hdr = gerar_header(p_atual)
        if re.search(header_pattern, content, flags=re.DOTALL):
            content = re.sub(header_pattern, novo_hdr, content, flags=re.DOTALL)
        else:
            content = re.sub(r'<header class="bg-gray-900.*?</header>', novo_hdr, content, count=1, flags=re.DOTALL)

        # Adiciona cache-busting ?v=2.2 nas chamadas de assets
        content = re.sub(r'href="assets/tailwind\.css(?:\?v=[^"]*)?"', 'href="assets/tailwind.css?v=2.2"', content)
        content = re.sub(r'href="assets/usabilidade\.css(?:\?v=[^"]*)?"', 'href="assets/usabilidade.css?v=2.2"', content)
        content = re.sub(r'src="assets/usabilidade\.js(?:\?v=[^"]*)?"', 'src="assets/usabilidade.js?v=2.2"', content)
        content = re.sub(r'src="tema\.js(?:\?v=[^"]*)?"', 'src="tema.js?v=2.2"', content)

        with open(fname, 'w', encoding='utf-8') as f:
            f.write(content)
        print(f'[OK] Header e cache-busting atualizados em {fname}')

def atualizar_usabilidade_css():
    with open('assets/usabilidade.css', 'r', encoding='utf-8') as f:
        css = f.read()

    novo_header_css = '''/* Cabeçalho Corporativo Institucional JFAL */
body:not(.sga-tv) header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: nowrap;
  gap: 1rem;
  padding: 0.75rem 1.5rem !important;
}

/* Imunidade total para o logotipo: nunca ter bordas, contornos ou outlines */
.sga-brand-link,
.sga-brand-link:focus,
.sga-brand-link:focus-visible,
.sga-brand-link:hover,
header a:has(img) {
  outline: none !important;
  border: none !important;
  box-shadow: none !important;
  background: transparent !important;
  text-decoration: none !important;
}

/* Barra Segmentada de Navegação (Tema Escuro Padrão) */
.sga-nav-bar {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  padding: 0.25rem;
  border-radius: 0.75rem;
  background-color: rgba(15, 23, 42, 0.7);
  border: 1px solid rgba(51, 65, 85, 0.6);
  flex-shrink: 0;
}

.sga-nav-item {
  font-size: 0.8125rem; /* 13px */
  font-weight: 500;
  padding: 0.375rem 0.75rem;
  border-radius: 0.5rem;
  color: #94a3b8;
  background: transparent;
  border: 1px solid transparent;
  text-decoration: none;
  transition: all 0.15s ease-in-out;
  white-space: nowrap;
}

.sga-nav-item:hover {
  color: #ffffff;
  background-color: rgba(30, 41, 59, 0.8);
}

.sga-nav-item.sga-nav-active {
  color: #ffffff !important;
  background-color: #1e40af !important;
  border-color: #3b82f6 !important;
  font-weight: 700 !important;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
}

/* Foco acessível apenas na navegação por teclado */
.sga-nav-item:focus-visible { outline: 2px solid #3b82f6; outline-offset: 1px; }

/* ========================================================
   REGRAS ESPECÍFICAS PARA O TEMA CLARO INSTITUCIONAL (JFAL)
   ======================================================== */
body.theme-jf header {
  background-color: #ffffff !important;
  border-bottom: 1px solid #e2e8f0 !important;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05) !important;
}

body.theme-jf .sga-nav-bar {
  background-color: #f1f5f9 !important;
  border: 1px solid #cbd5e1 !important;
}

body.theme-jf .sga-nav-item {
  color: #475569 !important;
  background: transparent !important;
  border: 1px solid transparent !important;
  font-weight: 600 !important;
}

body.theme-jf .sga-nav-item:hover {
  color: #002F6C !important;
  background-color: #ffffff !important;
  border-color: #cbd5e1 !important;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.06) !important;
}

body.theme-jf .sga-nav-item.sga-nav-active {
  color: #ffffff !important;
  background-color: #002F6C !important;
  border-color: #002F6C !important;
  font-weight: 700 !important;
  box-shadow: 0 1px 3px rgba(0, 47, 108, 0.3) !important;
}

/* Role Badge no Tema Claro */
body.theme-jf .sga-role-badge {
  background-color: #f1f5f9 !important;
  border-color: #cbd5e1 !important;
  color: #475569 !important;
}

/* Utilitários no Tema Claro */
body.theme-jf .sga-divider {
  background-color: #cbd5e1 !important;
}

body.theme-jf .sga-header-user {
  color: #475569 !important;
}

body.theme-jf .sga-header-user strong {
  color: #0f172a !important;
}

body.theme-jf .sga-header-user a {
  color: #64748b !important;
}
body.theme-jf .sga-header-user a:hover {
  color: #002F6C !important;
}

body.theme-jf .sga-btn-sair {
  color: #991b1b !important;
  background-color: #fee2e2 !important;
  border-color: #fca5a5 !important;
  font-weight: 600 !important;
}
body.theme-jf .sga-btn-sair:hover {
  background-color: #fecaca !important;
}

body.theme-jf #btn-toggle-theme {
  color: #334155 !important;
  background-color: #ffffff !important;
  border-color: #cbd5e1 !important;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05) !important;
}
body.theme-jf #btn-toggle-theme:hover {
  background-color: #f8fafc !important;
  color: #002F6C !important;
}

body.theme-jf .sga-nav-ajuda {
  color: #334155 !important;
  background-color: #ffffff !important;
  border-color: #cbd5e1 !important;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05) !important;
}
body.theme-jf .sga-nav-ajuda:hover {
  background-color: #f8fafc !important;
  color: #002F6C !important;
}

@media (max-width: 860px) {
  .sga-header-user { display: none !important; }
}'''

    sec_old_re = r'/\* Cabeçalho Corporativo.*?(?=body:not\(\.sga-tv\) main)'
    if re.search(sec_old_re, css, flags=re.DOTALL):
        css = re.sub(sec_old_re, novo_header_css + '\n\n', css, flags=re.DOTALL)

    css = re.sub(r'/\* Navegação Institucional Sóbria no Tema JF \*/.*?\.theme-jf main button', '.theme-jf main button', css, flags=re.DOTALL)

    with open('assets/usabilidade.css', 'w', encoding='utf-8') as f:
        f.write(css)
    print('[OK] assets/usabilidade.css atualizado')

def atualizar_tema_js():
    with open('tema.js', 'r', encoding='utf-8') as f:
        js = f.read()

    js_inject_new = '''    const utilsGroup = document.body.classList.contains('sga-tv') ? null : (document.querySelector('.sga-header-utils') || document.querySelector('header > div:last-child'));
    if (utilsGroup) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.id = 'btn-toggle-theme';
        btn.className = 'text-xs border px-2.5 py-1.5 rounded-lg transition flex items-center gap-1.5 font-semibold shrink-0';
        
        if (currentTheme === 'modern') {
            btn.innerHTML = '☀️ Tema Claro';
            btn.className += ' text-gray-300 border-gray-700 bg-gray-800 hover:bg-gray-750';
            btn.title = 'Mudar para o tema claro institucional da Justiça Federal';
        } else {
            btn.innerHTML = '🌙 Tema Escuro';
            btn.className += ' text-slate-600 border-slate-300 bg-white hover:bg-slate-50 shadow-xs';
            btn.title = 'Mudar para o tema escuro moderno';
        }
        
        btn.addEventListener('click', () => {
            const nextTheme = currentTheme === 'modern' ? 'jf' : 'modern';
            setCookie('tema_sga', nextTheme);
            try { localStorage.setItem('tema_sga', nextTheme); } catch(e){}
            currentTheme = nextTheme;
            document.body.classList.toggle('theme-jf', nextTheme === 'jf');
            if (document.documentElement) document.documentElement.classList.toggle('theme-jf', nextTheme === 'jf');
            btn.innerHTML = nextTheme === 'jf' ? '🌙 Tema Escuro' : '☀️ Tema Claro';
            btn.title = nextTheme === 'jf' ? 'Mudar para o tema escuro moderno' : 'Mudar para o tema claro institucional da Justiça Federal';
        });
        
        // Insere o botão de alternar tema no início dos utilitários
        utilsGroup.insertBefore(btn, utilsGroup.firstChild);

        // Insere o link de Ajuda logo após o botão de tema (antes do divisor)
        if (!utilsGroup.querySelector('a[href="ajuda.php"]') && location.pathname.split('/').pop() !== 'ajuda.php') {
            const ajuda = document.createElement('a');
            ajuda.href = 'ajuda.php';
            ajuda.textContent = 'Ajuda';
            ajuda.className = 'text-xs text-gray-400 hover:text-white border border-gray-800 bg-gray-950/40 hover:bg-gray-800/60 px-2.5 py-1.5 rounded-lg transition shrink-0 sga-nav-ajuda';
            btn.after(ajuda);
        }
    }'''

    # Substitui a injeção do botão
    js = re.sub(r'const utilsGroup =.*?(?=\}\);?\s*$)', js_inject_new + '\n', js, flags=re.DOTALL)

    with open('tema.js', 'w', encoding='utf-8') as f:
        f.write(js)
    print('[OK] tema.js atualizado')

def atualizar_usabilidade_js():
    with open('assets/usabilidade.js', 'r', encoding='utf-8') as f:
        ujs = f.read()

    ujs = re.sub(
        r'for \(const link of document\.querySelectorAll\(\'header a\[href\]\'\)\)',
        'for (const link of document.querySelectorAll(\'header .sga-nav-bar a[href]\'))',
        ujs
    )

    with open('assets/usabilidade.js', 'w', encoding='utf-8') as f:
        f.write(ujs)
    print('[OK] assets/usabilidade.js atualizado')

if __name__ == '__main__':
    atualizar_headers_php()
    atualizar_usabilidade_css()
    atualizar_tema_js()
    atualizar_usabilidade_js()
    print('Refinamento visual concluido com sucesso!')
