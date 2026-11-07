/**
 * SISTEMA DE TEMAS DE CORES INSTITUCIONAL - JFAL
 * tema.js: Injeta e gerencia as cores oficiais do manual da Justiça Federal ou o tema escuro original.
 */

// Estilos CSS de Override para o Tema da Justiça Federal (Light Mode) e regras de alto contraste
const themeStyles = `
    /* Variáveis Globais para o Tema Institucional da Justiça Federal */
    body.theme-jf {
        background-color: #f8fafc !important;
        color: #0f172a !important;
        --color-jf-blue: #002F6C;
        --color-jf-blue-hover: #001f47;
        --color-jf-blue-light: #003a85;
        --color-jf-green: #007A33;
        --color-jf-green-hover: #005c26;
        --color-jf-green-light: #166534;
        --color-jf-gray: #64748b;
    }

    /* Estrutura de Cores de Fundo (Light Mode) */
    body.theme-jf .bg-gray-950 { background-color: #f8fafc !important; }
    body.theme-jf .bg-gray-900 { background-color: #ffffff !important; }
    body.theme-jf .bg-gray-850 { background-color: #f1f5f9 !important; }
    body.theme-jf .bg-gray-800 { background-color: #f1f5f9 !important; }
    body.theme-jf .bg-gray-950\\/30 { background-color: #f1f5f9 !important; }
    body.theme-jf .bg-gray-950\\/40 { background-color: #f1f5f9 !important; }
    body.theme-jf .bg-gray-950\\/50 { background-color: #ffffff !important; }
    body.theme-jf .bg-gray-900\\/50 { background-color: #f8fafc !important; }
    body.theme-jf .bg-slate-900 { background-color: #ffffff !important; }
    body.theme-jf .bg-slate-900\\/60 { background-color: #ffffff !important; }
    body.theme-jf .bg-slate-950\\/40 { background-color: #ffffff !important; }

    /* Bordas Corporativas */
    body.theme-jf .border-gray-900 { border-color: #cbd5e1 !important; }
    body.theme-jf .border-gray-850 { border-color: #e2e8f0 !important; }
    body.theme-jf .border-gray-800 { border-color: #cbd5e1 !important; }
    body.theme-jf .border-gray-750 { border-color: #cbd5e1 !important; }
    body.theme-jf .border-gray-700 { border-color: #cbd5e1 !important; }
    body.theme-jf .border-slate-800 { border-color: #cbd5e1 !important; }
    body.theme-jf .border-slate-800\\/80 { border-color: #cbd5e1 !important; }

    /* Cores de Texto de Alto Contraste (WCAG AA / AAA) */
    body.theme-jf .text-gray-100 { color: #0f172a !important; }
    body.theme-jf .text-gray-200 { color: #1e293b !important; }
    body.theme-jf .text-gray-300 { color: #334155 !important; }
    body.theme-jf .text-gray-400 { color: #475569 !important; }
    body.theme-jf .text-white { color: #0f172a !important; }
    body.theme-jf .text-gray-500 { color: #475569 !important; }
    body.theme-jf .text-slate-400 { color: #334155 !important; }
    body.theme-jf .text-slate-500 { color: #475569 !important; }

    /* Inputs e Controles */
    body.theme-jf input,
    body.theme-jf select,
    body.theme-jf textarea {
        background-color: #ffffff !important;
        color: #0f172a !important;
        border-color: #cbd5e1 !important;
    }
    body.theme-jf input::placeholder {
        color: #94a3b8 !important;
    }
    body.theme-jf input:focus,
    body.theme-jf select:focus {
        border-color: var(--color-jf-blue) !important;
        box-shadow: 0 0 0 1px var(--color-jf-blue) !important;
        outline: none !important;
    }

    /* Tabelas e Hover de linhas */
    body.theme-jf .hover\\:bg-gray-900\\/50:hover,
    body.theme-jf .hover\\:bg-gray-800\\/30:hover,
    body.theme-jf .hover\\:bg-gray-800\\/50:hover { background-color: #f1f5f9 !important; }
    body.theme-jf tr:nth-child(even) { background-color: #f8fafc; }
    body.theme-jf thead tr.border-b { border-color: #cbd5e1 !important; }
    body.theme-jf thead th { color: #334155 !important; background-color: #f1f5f9 !important; }

    /* Checkboxes */
    body.theme-jf input[type="checkbox"] {
        background-color: #ffffff !important;
        border-color: #94a3b8 !important;
    }
    body.theme-jf input[type="checkbox"]:checked {
        background-color: var(--color-jf-blue) !important;
        border-color: var(--color-jf-blue) !important;
    }

    /* Títulos e Destaques Institucionais */
    body.theme-jf .text-blue-500,
    body.theme-jf .text-blue-400 {
        color: var(--color-jf-blue) !important;
    }
    body.theme-jf .text-amber-400,
    body.theme-jf .text-amber-500 {
        color: #92400e !important;
    }
    body.theme-jf .text-emerald-500,
    body.theme-jf .text-emerald-400 {
        color: #166534 !important;
    }

    /* Crachá e Badges Semânticos */
    body.theme-jf .bg-amber-950,
    body.theme-jf .bg-amber-950\\/40,
    body.theme-jf .bg-amber-950\\/60 {
        background-color: #fef3c7 !important;
        border-color: #fde68a !important;
        color: #92400e !important;
    }
    body.theme-jf .bg-emerald-950,
    body.theme-jf .bg-emerald-950\\/40,
    body.theme-jf .bg-emerald-950\\/60 {
        background-color: #dcfce7 !important;
        border-color: #86efac !important;
        color: #166534 !important;
    }
    body.theme-jf .bg-blue-950,
    body.theme-jf .bg-blue-950\\/40,
    body.theme-jf .bg-blue-950\\/50 {
        background-color: #eff6ff !important;
        border-color: #bfdbfe !important;
        color: #1e40af !important;
    }
    body.theme-jf .bg-red-950,
    body.theme-jf .bg-red-950\\/40 {
        background-color: #fee2e2 !important;
        border-color: #fca5a5 !important;
        color: #991b1b !important;
    }

    /* Botões Primários e Secundários */
    body.theme-jf .bg-blue-600 {
        background-color: var(--color-jf-blue) !important;
        color: #ffffff !important;
    }
    body.theme-jf .hover\\:bg-blue-700:hover {
        background-color: var(--color-jf-blue-hover) !important;
    }
    body.theme-jf .bg-emerald-600 {
        background-color: var(--color-jf-green) !important;
        color: #ffffff !important;
    }
    body.theme-jf .hover\\:bg-emerald-700:hover,
    body.theme-jf .hover\\:bg-emerald-500:hover {
        background-color: var(--color-jf-green-hover) !important;
    }

    /* Menu Superior Unificado */
    .sga-nav-active {
        background-color: #002F6C !important;
        color: #ffffff !important;
        border-color: #002F6C !important;
        font-weight: 700 !important;
    }

    /* Cards do Dashboard no Tema JF */
    body.theme-jf .sga-dashboard-card {
        background-color: #ffffff !important;
        border: 1px solid #cbd5e1 !important;
        border-top: 3px solid #002F6C !important;
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.06), 0 1px 2px -1px rgba(0, 0, 0, 0.06) !important;
    }
    body.theme-jf .sga-dashboard-card:hover {
        border-color: #002F6C !important;
        border-top-color: #002F6C !important;
        box-shadow: 0 10px 15px -3px rgba(0, 47, 108, 0.1) !important;
    }
    body.theme-jf .sga-dashboard-card .sga-icon-box {
        background-color: #eff6ff !important;
        border: 1px solid #bfdbfe !important;
        color: #002F6C !important;
    }
    body.theme-jf .sga-dashboard-card h3 {
        color: #0f172a !important;
    }
    body.theme-jf .sga-dashboard-card p {
        color: #475569 !important;
    }
    body.theme-jf .sga-dashboard-card .sga-card-link {
        color: #002F6C !important;
        font-weight: 700 !important;
    }

    /* Abas do Dashboard no Tema JF */
    body.theme-jf .tab-btn.tab-ativo {
        background-color: #002F6C !important;
        color: #ffffff !important;
        border-color: #002F6C !important;
    }
    body.theme-jf .tab-btn:not(.tab-ativo) {
        background-color: #ffffff !important;
        color: #475569 !important;
        border-color: #cbd5e1 !important;
    }

    /* Tela de Login (index.php) no Tema JF */
    body.theme-jf .login-card {
        background-color: #ffffff !important;
        border: 1px solid #cbd5e1 !important;
        box-shadow: 0 20px 25px -5px rgba(0, 47, 108, 0.08), 0 8px 10px -6px rgba(0, 47, 108, 0.04) !important;
    }
    body.theme-jf .login-title {
        color: #002F6C !important;
    }
    body.theme-jf .login-subtitle {
        color: #475569 !important;
    }
    body.theme-jf .login-aviso {
        background-color: #eff6ff !important;
        border: 1px solid #bfdbfe !important;
        color: #1e40af !important;
    }
    body.theme-jf .login-error {
        background-color: #fef2f2 !important;
        border: 1px solid #fca5a5 !important;
        color: #991b1b !important;
    }
    body.theme-jf .login-btn {
        background-color: #002F6C !important;
        background-image: none !important;
        color: #ffffff !important;
    }
    body.theme-jf .login-btn:hover {
        background-color: #001f47 !important;
    }
    body.theme-jf .login-helper {
        color: #475569 !important;
    }

    /* Eliminação do piscar (animate-pulse) em alertas e status */
    .animate-pulse,
    body.theme-jf .animate-pulse {
        animation: none !important;
    }

    /* TV Panel no Tema JF */
    body.theme-jf.sga-tv header,
    body.theme-jf.sga-tv footer {
        background-color: #ffffff !important;
        border-color: #cbd5e1 !important;
    }
    body.theme-jf.sga-tv footer h3 {
        color: #002F6C !important;
    }
    body.theme-jf.sga-tv footer p {
        color: #64748b !important;
    }
    body.theme-jf.sga-tv #rodape-senhas > div {
        background-color: #f8fafc !important;
        border-color: #cbd5e1 !important;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05) !important;
    }
    body.theme-jf.sga-tv #rodape-senhas > div:first-child:not(:empty) {
        background-color: #eff6ff !important;
        border-color: #002F6C !important;
        border-width: 2px !important;
    }
    body.theme-jf.sga-tv #rodape-senhas .text-white {
        color: #0f172a !important;
        font-weight: 800 !important;
    }
    body.theme-jf.sga-tv #rodape-senhas .text-emerald-400 {
        color: #007A33 !important;
        font-weight: 700 !important;
    }
    body.theme-jf.sga-tv #chamada-overlay .bg-gray-900 {
        background-color: #ffffff !important;
        border-color: #002F6C !important;
        box-shadow: 0 25px 50px -12px rgba(0, 47, 108, 0.25) !important;
    }
    body.theme-jf.sga-tv #chamada-overlay #overlay-nome {
        color: #002F6C !important;
    }
    body.theme-jf.sga-tv #chamada-overlay .bg-gray-800\\/50 {
        background-color: #f0fdf4 !important;
        border-color: #86efac !important;
    }
    body.theme-jf.sga-tv #chamada-overlay #overlay-sala {
        color: #007A33 !important;
    }
`;

// Helper para ler cookies
function getCookie(name) {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) return parts.pop().split(';').shift();
    return null;
}

// Helper para setar cookies
function setCookie(name, value, days = 365) {
    const date = new Date();
    date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
    document.cookie = `${name}=${value};expires=${date.toUTCString()};path=/`;
}

// Carrega o tema atual
let currentTheme = getCookie('tema_sga') || 'modern';
if (currentTheme !== 'modern' && currentTheme !== 'jf') {
    currentTheme = 'modern';
}

// Injeta os estilos CSS de override no head do documento
const styleTag = document.createElement('style');
styleTag.id = 'theme-override-styles';
styleTag.innerHTML = themeStyles;
document.head.appendChild(styleTag);

// Injeta o botão de alternância no header e aplica a classe no body
document.addEventListener('DOMContentLoaded', () => {
    // Aplica a classe correspondente ao body
    if (currentTheme === 'jf') {
        document.body.classList.add('theme-jf');
    } else {
        document.body.classList.remove('theme-jf');
    }

    const headerRight = document.body.classList.contains('sga-tv') ? null : document.querySelector('header > div:last-child');
    if (headerRight) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.id = 'btn-toggle-theme';
        btn.className = 'text-xs border px-2.5 py-1.5 rounded-lg transition flex items-center gap-1.5 font-semibold shrink-0';
        
        if (currentTheme === 'modern') {
            btn.innerHTML = '🎨 Cores JF';
            btn.className += ' text-gray-300 border-gray-750 bg-gray-900 hover:bg-gray-800';
            btn.title = 'Mudar para o padrão claro institucional da Justiça Federal';
        } else {
            btn.innerHTML = '🎨 Tema Original';
            btn.className += ' text-gray-700 border-gray-300 bg-white hover:bg-gray-100 shadow-sm';
            btn.title = 'Mudar para o padrão moderno escuro';
        }
        
        btn.addEventListener('click', () => {
            const nextTheme = currentTheme === 'modern' ? 'jf' : 'modern';
            setCookie('tema_sga', nextTheme);
            currentTheme = nextTheme;
            document.body.classList.toggle('theme-jf', nextTheme === 'jf');
            btn.textContent = nextTheme === 'jf' ? '🎨 Tema Original' : '🎨 Cores JF';
        });
        
        if (!headerRight.querySelector('a[href="ajuda.php"]') && location.pathname.split('/').pop() !== 'ajuda.php') {
            const ajuda = document.createElement('a');
            ajuda.href = 'ajuda.php';
            ajuda.textContent = 'Ajuda';
            ajuda.className = 'text-xs text-gray-400 hover:text-white border border-gray-800 bg-gray-950/40 hover:bg-gray-800/60 px-2.5 py-1.5 rounded-lg transition shrink-0';
            headerRight.appendChild(ajuda);
        }
        // Insere o botão de alternar tema como primeiro item do cabeçalho direito
        headerRight.insertBefore(btn, headerRight.firstChild);
    }
});
