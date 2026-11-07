# -*- coding: utf-8 -*-
"""
Script Definitivo de Atualização do Cabeçalho e Menu Institucional JFAL
- Padrão 3 zonas (Identidade, Navegação Limpa, Utilitários)
- Imunidade visual total à logo (zero bordas/outlines)
- Navegação em abas limpas (sem cápsula cinza pesada)
- Responsividade inteligente de 375px a 1920px+
- Cache-busting v2.3 em todos os assets
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
            <button type="button" id="btn-audio-perito" onclick="toggleAudioPerito()" class="text-xs text-blue-400 hover:text-blue-300 border border-blue-900/60 bg-blue-950/40 px-2 py-1 rounded-md transition flex items-center gap-1 shrink-0 sga-btn-audio">
                <span id="icone-audio-perito">🔊</span> <span id="texto-audio-perito" class="hidden sm:inline">Som Ativo</span>
            </button>'''

    return f'''    <!-- Header Institucional Unificado JFAL -->
    <header class="sga-header bg-gray-900 border-b border-gray-800 px-4 py-2.5 flex items-center justify-between shadow-xs shrink-0">
        <!-- 1. Identidade Oficial JFAL -->
        <div class="sga-brand-area flex items-center space-x-2 shrink-0">
            <a href="dashboard.php" class="sga-brand-link flex items-center space-x-2 group">
                <img src="assets/catavento-jfal.png" alt="JFAL" class="h-7 w-auto object-contain transition-transform duration-200 group-hover:scale-105">
                <div class="sga-brand-text leading-tight hidden sm:block">
                    <h1 class="text-xs font-black tracking-wide sga-brand-title uppercase leading-none">Justiça Federal em Alagoas</h1>
                    <p class="text-[8px] sga-brand-sub font-semibold tracking-widest uppercase mt-0.5">Seção Judiciária de Alagoas</p>
                </div>
            </a>
            <span class="sga-role-badge bg-gray-800 text-gray-400 text-[10px] px-2 py-0.5 rounded-full uppercase font-bold tracking-wider border border-gray-700">
                <?= escapar(ucfirst($_SESSION['usuario_role'])) ?>
            </span>
        </div>

        <!-- 2. Barra de Navegação Central (Abas Limpas) -->
        <nav class="sga-nav-bar sga-nav-bar-clean flex items-center gap-0.5 shrink-1 min-w-0 overflow-x-auto">
            <a href="dashboard.php" class="sga-nav-link sga-nav-item{' sga-nav-active' if pagina_atual == 'dashboard.php' else ''}">Painel Principal</a>
            
            <?php if (in_array($_SESSION['usuario_role'], ['admin', 'supervisor', 'recepcao'])): ?>
                <a href="recepcao.php" class="sga-nav-link sga-nav-item{' sga-nav-active' if pagina_atual == 'recepcao.php' else ''}">Recepção</a>
            <?php endif; ?>

            <?php if (in_array($_SESSION['usuario_role'], ['admin', 'perito'])): ?>
                <a href="atendente.php" class="sga-nav-link sga-nav-item{' sga-nav-active' if pagina_atual == 'atendente.php' else ''}">Atendimento</a>
            <?php endif; ?>

            <?php if (in_array($_SESSION['usuario_role'], ['admin', 'supervisor'])): ?>
                <a href="gerenciar_pauta.php" class="sga-nav-link sga-nav-item{' sga-nav-active' if pagina_atual == 'gerenciar_pauta.php' else ''}">Pautas</a>
                <a href="salas.php" class="sga-nav-link sga-nav-item{' sga-nav-active' if pagina_atual == 'salas.php' else ''}">Salas</a>
                <a href="auditoria.php" class="sga-nav-link sga-nav-item{' sga-nav-active' if pagina_atual == 'auditoria.php' else ''}">Auditoria</a>
            <?php endif; ?>

            <?php if ($_SESSION['usuario_role'] === 'admin'): ?>
                <a href="usuarios.php" class="sga-nav-link sga-nav-item{' sga-nav-active' if pagina_atual == 'usuarios.php' else ''}">Perfis & Senhas</a>
            <?php endif; ?>
        </nav>

        <!-- 3. Grupo de Utilitários (Direita) -->
        <div class="sga-header-utils flex items-center space-x-1.5 shrink-0">{som_button}
            <div class="h-4 w-px bg-gray-700/60 mx-1 hidden md:block sga-divider"></div>

            <div class="sga-header-user flex items-center gap-1.5 shrink-0 text-xs text-gray-400">
                <span>Olá, <strong class="text-white"><?= escapar($_SESSION['usuario_name']) ?></strong></span>
                <span class="text-gray-600 sga-bullet">•</span>
                <a href="alterar_senha_obrigatorio.php" class="sga-user-link text-gray-400 hover:text-white underline text-xs">Minha senha</a>
            </div>

            <form method="POST" action="logout.php" class="sga-logout shrink-0 m-0">
                <?= csrfCampo() ?>
                <button type="submit" class="sga-btn-sair">Sair</button>
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
            content = re.sub(r'<header class="sga-header.*?</header>', novo_hdr, content, count=1, flags=re.DOTALL)

        # Adiciona cache-busting ?v=2.3 em todos os links de assets
        content = re.sub(r'href="assets/tailwind\.css(?:\?v=[^"]*)?"', 'href="assets/tailwind.css?v=2.3"', content)
        content = re.sub(r'href="assets/usabilidade\.css(?:\?v=[^"]*)?"', 'href="assets/usabilidade.css?v=2.3"', content)
        content = re.sub(r'src="assets/usabilidade\.js(?:\?v=[^"]*)?"', 'src="assets/usabilidade.js?v=2.3"', content)
        content = re.sub(r'src="tema\.js(?:\?v=[^"]*)?"', 'src="tema.js?v=2.3"', content)

        with open(fname, 'w', encoding='utf-8') as f:
            f.write(content)
        print(f'[OK] Header e cache-busting v2.3 atualizados em {fname}')

def atualizar_usabilidade_css():
    with open('assets/usabilidade.css', 'r', encoding='utf-8') as f:
        css = f.read()

    novo_header_css = '''/* ========================================================
   CABEÇALHO CORPORATIVO INSTITUCIONAL JFAL (3 ZONAS)
   ======================================================== */
.sga-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  width: 100%;
  max-width: 100vw;
  box-sizing: border-box;
  padding: 0.5rem 1rem !important;
}

/* 1. Imunidade total para o logotipo: NUNCA ter bordas, contornos ou outlines */
.sga-brand-area {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-shrink: 0;
}

.sga-brand-link,
.sga-brand-link:focus,
.sga-brand-link:focus-visible,
.sga-brand-link:hover,
.sga-brand-link:active,
header a:has(img) {
  outline: none !important;
  border: none !important;
  box-shadow: none !important;
  background: transparent !important;
  text-decoration: none !important;
}

/* 2. Barra de Navegação Central (Abas Limpas e Modernas) */
.sga-nav-bar-clean {
  display: flex;
  align-items: center;
  gap: 0.125rem;
  flex-shrink: 1;
  min-width: 0;
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
  scrollbar-width: none;
}
.sga-nav-bar-clean::-webkit-scrollbar {
  display: none;
}

.sga-nav-bar-clean .sga-nav-link {
  font-size: 0.75rem; /* 12px */
  font-weight: 500;
  padding: 0.35rem 0.55rem;
  border-radius: 0.375rem;
  text-decoration: none;
  transition: all 0.15s ease-in-out;
  white-space: nowrap;
  flex-shrink: 0;
  border: 1px solid transparent;
}

/* Foco acessível por teclado */
.sga-nav-bar-clean .sga-nav-link:focus-visible {
  outline: 2px solid #002F6C;
  outline-offset: 1px;
}

/* 3. Utilitários (Direita) */
.sga-header-utils {
  display: flex;
  align-items: center;
  gap: 0.375rem;
  flex-shrink: 0;
}

.sga-btn-util {
  font-size: 0.6875rem; /* 11px */
  font-weight: 500;
  padding: 0.25rem 0.5rem;
  border-radius: 0.375rem;
  text-decoration: none;
  transition: all 0.15s ease;
  white-space: nowrap;
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
}

.sga-btn-sair {
  font-size: 0.6875rem; /* 11px */
  font-weight: 600;
  padding: 0.25rem 0.55rem;
  border-radius: 0.375rem;
  transition: all 0.15s ease;
  cursor: pointer;
}

/* ========================================================
   REGRAS ESPECÍFICAS PARA O TEMA CLARO INSTITUCIONAL (JFAL)
   ======================================================== */
body.theme-jf .sga-header {
  background-color: #ffffff !important;
  border-bottom: 1px solid #e2e8f0 !important;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03) !important;
}

body.theme-jf .sga-brand-title {
  color: #002F6C !important;
  font-size: 0.75rem;
}

body.theme-jf .sga-brand-sub {
  color: #64748b !important;
  font-size: 0.5625rem;
}

body.theme-jf .sga-role-badge {
  background-color: #eff6ff !important;
  color: #1d4ed8 !important;
  border: 1px solid #bfdbfe !important;
  font-size: 0.5625rem;
  padding: 0.125rem 0.5rem;
}

/* Abas de Navegação no Tema Claro */
body.theme-jf .sga-nav-bar-clean .sga-nav-link {
  color: #475569 !important;
  background: transparent !important;
}

body.theme-jf .sga-nav-bar-clean .sga-nav-link:hover {
  color: #002F6C !important;
  background-color: #f1f5f9 !important;
}

body.theme-jf .sga-nav-bar-clean .sga-nav-link.sga-nav-active {
  color: #ffffff !important;
  background-color: #002F6C !important;
  border-color: #002F6C !important;
  font-weight: 700 !important;
  box-shadow: 0 1px 2px rgba(0, 47, 108, 0.25) !important;
}

/* Utilitários no Tema Claro */
body.theme-jf .sga-btn-util {
  border: 1px solid #cbd5e1 !important;
  background-color: #ffffff !important;
  color: #334155 !important;
}

body.theme-jf .sga-btn-util:hover {
  background-color: #f8fafc !important;
  border-color: #94a3b8 !important;
  color: #002F6C !important;
}

body.theme-jf .sga-divider {
  background-color: #e2e8f0 !important;
}

body.theme-jf .sga-header-user {
  color: #64748b !important;
  font-size: 0.75rem;
}

body.theme-jf .sga-header-user strong {
  color: #0f172a !important;
  font-weight: 600;
}

body.theme-jf .sga-user-link {
  color: #002F6C !important;
  text-decoration: none !important;
  font-weight: 500;
}

body.theme-jf .sga-user-link:hover {
  text-decoration: underline !important;
  color: #1d4ed8 !important;
}

body.theme-jf .sga-btn-sair {
  border: 1px solid #fca5a5 !important;
  background-color: #fef2f2 !important;
  color: #991b1b !important;
}

body.theme-jf .sga-btn-sair:hover {
  background-color: #fee2e2 !important;
  border-color: #f87171 !important;
}

body.theme-jf .sga-btn-audio {
  border-color: #bfdbfe !important;
  background-color: #eff6ff !important;
  color: #1d4ed8 !important;
}

/* ========================================================
   REGRAS ESPECÍFICAS PARA O TEMA ESCURO MODERNO
   ======================================================== */
body:not(.theme-jf) .sga-header {
  background-color: #0f172a !important;
  border-bottom: 1px solid #1e293b !important;
}

body:not(.theme-jf) .sga-brand-title {
  color: #3b82f6 !important;
}

body:not(.theme-jf) .sga-brand-sub {
  color: #94a3b8 !important;
}

body:not(.theme-jf) .sga-role-badge {
  background-color: #1e293b !important;
  color: #94a3b8 !important;
  border: 1px solid #334155 !important;
}

body:not(.theme-jf) .sga-nav-bar-clean .sga-nav-link {
  color: #94a3b8 !important;
  background: transparent !important;
}

body:not(.theme-jf) .sga-nav-bar-clean .sga-nav-link:hover {
  color: #ffffff !important;
  background-color: #1e293b !important;
}

body:not(.theme-jf) .sga-nav-bar-clean .sga-nav-link.sga-nav-active {
  color: #ffffff !important;
  background-color: #2563eb !important;
  border-color: #3b82f6 !important;
  font-weight: 700 !important;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.3) !important;
}

body:not(.theme-jf) .sga-btn-util {
  border: 1px solid #334155 !important;
  background-color: #1e293b !important;
  color: #cbd5e1 !important;
}

body:not(.theme-jf) .sga-btn-util:hover {
  background-color: #334155 !important;
  color: #ffffff !important;
}

body:not(.theme-jf) .sga-divider {
  background-color: #334155 !important;
}

body:not(.theme-jf) .sga-header-user {
  color: #94a3b8 !important;
}

body:not(.theme-jf) .sga-header-user strong {
  color: #ffffff !important;
}

body:not(.theme-jf) .sga-user-link {
  color: #60a5fa !important;
}

body:not(.theme-jf) .sga-btn-sair {
  border: 1px solid #7f1d1d !important;
  background-color: #450a0a !important;
  color: #f87171 !important;
}

body:not(.theme-jf) .sga-btn-sair:hover {
  background-color: #5b0f0f !important;
}

/* ========================================================
   REGRAS DE RESPONSIVIDADE DO CABEÇALHO
   ======================================================== */
@media (max-width: 1100px) {
  .sga-header-user { display: none !important; }
  .sga-divider { display: none !important; }
}

@media (max-width: 768px) {
  .sga-brand-text { display: none !important; }
  .sga-header { padding: 0.4rem 0.75rem !important; }
}

@media (max-width: 640px) {
  .sga-role-badge { display: none !important; }
  .sga-nav-ajuda { display: none !important; }
  .sga-theme-label { display: none !important; }
  .sga-header { padding: 0.35rem 0.5rem !important; gap: 0.25rem !important; }
}'''

    sec_old_re = r'/\* =+.*?CABEÇALHO CORPORATIVO.*?(?=body:not\(\.sga-tv\) main)'
    if re.search(sec_old_re, css, flags=re.DOTALL):
        css = re.sub(sec_old_re, novo_header_css + '\n\n', css, flags=re.DOTALL)
    else:
        # Se não casou a seção inteira, substitui da primeira regra de header até main
        css = re.sub(r'body:not\(\.sga-tv\) header.*?(?=body:not\(\.sga-tv\) main)', novo_header_css + '\n\n', css, flags=re.DOTALL)

    with open('assets/usabilidade.css', 'w', encoding='utf-8') as f:
        f.write(css)
    print('[OK] assets/usabilidade.css atualizado com design institucional')

def atualizar_tema_js():
    with open('tema.js', 'r', encoding='utf-8') as f:
        js = f.read()

    js_inject_new = '''    const utilsGroup = document.body.classList.contains('sga-tv') ? null : (document.querySelector('.sga-header-utils') || document.querySelector('header > div:last-child'));
    if (utilsGroup) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.id = 'btn-toggle-theme';
        btn.className = 'sga-btn-util shrink-0 cursor-pointer';
        
        if (currentTheme === 'modern') {
            btn.innerHTML = '☀️ <span class="sga-theme-label">Claro</span>';
            btn.title = 'Mudar para o tema claro institucional da Justiça Federal';
        } else {
            btn.innerHTML = '🌙 <span class="sga-theme-label">Escuro</span>';
            btn.title = 'Mudar para o tema escuro moderno';
        }
        
        btn.addEventListener('click', () => {
            const nextTheme = currentTheme === 'modern' ? 'jf' : 'modern';
            setCookie('tema_sga', nextTheme);
            try { localStorage.setItem('tema_sga', nextTheme); } catch(e){}
            currentTheme = nextTheme;
            document.body.classList.toggle('theme-jf', nextTheme === 'jf');
            if (document.documentElement) document.documentElement.classList.toggle('theme-jf', nextTheme === 'jf');
            btn.innerHTML = nextTheme === 'jf' ? '🌙 <span class="sga-theme-label">Escuro</span>' : '☀️ <span class="sga-theme-label">Claro</span>';
            btn.title = nextTheme === 'jf' ? 'Mudar para o tema escuro moderno' : 'Mudar para o tema claro institucional da Justiça Federal';
        });
        
        // Insere o botão de alternar tema no início dos utilitários
        utilsGroup.insertBefore(btn, utilsGroup.firstChild);

        // Insere o link de Ajuda logo após o botão de tema (antes do divisor)
        if (!utilsGroup.querySelector('a[href="ajuda.php"]') && location.pathname.split('/').pop() !== 'ajuda.php') {
            const ajuda = document.createElement('a');
            ajuda.href = 'ajuda.php';
            ajuda.textContent = 'Ajuda';
            ajuda.className = 'sga-btn-util sga-nav-ajuda shrink-0';
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
    print('Refinamento definitivo do menu institucional aplicado com sucesso!')
