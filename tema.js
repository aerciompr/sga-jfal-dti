/**
* SISTEMA DE TEMAS DE CORES - JFAL
* tema.js: Injeta e gerencia as cores oficiais do manual da Justiça Federal ou o tema escuro original.
*/

// 1. Estilos CSS de Override para o Tema Claro (Light Mode) da Justiça Federal
// De acordo com o Manual de Identidade Visual da JF (Página 42):
// - Azul JF: #002F6C (usado em botões, preenchimentos e bordas)
// - Azul JF Light: #003a85 (usado em links e títulos)
// - Verde JF: #007A33 (usado em botões de sucesso)
// - Verde JF Light: #00913d (usado em textos verdes)
// - Cinza JF: #97999F (Padrão de apoio institucional)
const themeStyles = `
    /* Estilos de Override para o Tema da Justiça Federal (Light Mode) */
    body.theme-jf {
        background-color: #f3f4f6 !important;
        color: #1e293b !important;
        --color-jf-blue: #002F6C;
        --color-jf-blue-hover: #001f47;
        --color-jf-blue-light: #003a85;
        --color-jf-green: #007A33;
        --color-jf-green-hover: #005c26;
        --color-jf-green-light: #00913d;
        --color-jf-gray: #97999F;
    }

    /* Estrutura de Cores de Fundo (Light Mode) */
    body.theme-jf .bg-gray-950 { background-color: #f3f4f6 !important; }
    body.theme-jf .bg-gray-900 { background-color: #ffffff !important; }
    body.theme-jf .bg-gray-850 { background-color: #f8fafc !important; }
    body.theme-jf .bg-gray-800 { background-color: #f1f5f9 !important; }
    body.theme-jf .bg-gray-950\\/30 { background-color: #f1f5f9 !important; }
    body.theme-jf .bg-gray-950\\/50 { background-color: #ffffff !important; }
    body.theme-jf .bg-gray-900\\/50 { background-color: #f8fafc !important; }

    /* Bordas */
    body.theme-jf .border-gray-900 { border-color: #cbd5e1 !important; }
    body.theme-jf .border-gray-850 { border-color: #e2e8f0 !important; }
    body.theme-jf .border-gray-800 { border-color: #cbd5e1 !important; }
    body.theme-jf .border-gray-750 { border-color: #cbd5e1 !important; }
    body.theme-jf .border-gray-700 { border-color: #cbd5e1 !important; }

    /* Cores de Texto (Light Mode) */
    body.theme-jf .text-gray-100 { color: #1e293b !important; }
    body.theme-jf .text-gray-200 { color: #334155 !important; }
    body.theme-jf .text-gray-300 { color: #475569 !important; }
    body.theme-jf .text-gray-400 { color: #64748b !important; }
    body.theme-jf .text-white { color: #0f172a !important; }
    body.theme-jf .text-gray-500 { color: #475569 !important; }

    /* Inputs e Controles */
    body.theme-jf input,
    body.theme-jf select,
    body.theme-jf textarea {
        background-color: #ffffff !important;
        color: #1e293b !important;
        border-color: #cbd5e1 !important;
    }
    body.theme-jf input::placeholder {
        color: #94a3b8 !important;
    }
    body.theme-jf input:focus,
    body.theme-jf select:focus {
        border-color: var(--color-jf-blue) !important;
        outline: none !important;
    }

    /* Tabelas e Hover de linhas */
    body.theme-jf .hover\\:bg-gray-900\\/50:hover { background-color: #f1f5f9 !important; }
    body.theme-jf tr:nth-child(even) { background-color: #f8fafc; }
    body.theme-jf thead tr.border-b { border-color: #cbd5e1 !important; }
    body.theme-jf thead th { color: #475569 !important; }

    /* Checkboxes */
    body.theme-jf input[type="checkbox"] {
        background-color: #ffffff !important;
        border-color: #cbd5e1 !important;
    }
    body.theme-jf input[type="checkbox"]:checked {
        background-color: var(--color-jf-blue) !important;
        border-color: var(--color-jf-blue) !important;
    }

    /* Correções de Títulos da Recepção (Remoção do Amarelo feio no tema JF) */
    body.theme-jf .text-amber-400 { color: var(--color-jf-blue) !important; }
    body.theme-jf .text-amber-500 { color: var(--color-jf-blue) !important; }
    body.theme-jf .text-emerald-500 { color: var(--color-jf-green) !important; }

    /* Crachá de contagem superior */
    body.theme-jf .bg-amber-950 {
        background-color: #e0f2fe !important;
        color: var(--color-jf-blue-light) !important;
    }

    /* Ajustes Sóbrios para Botões de Ação em Lote (Sem amarelo/laranja) */
    /* 1. Confirmar Selecionados (Verde JF) */
    body.theme-jf .bg-emerald-950\\/40 {
        background-color: #dcfce7 !important;
        border-color: #86efac !important;
        color: #166534 !important;
        opacity: 1 !important;
    }
    body.theme-jf .bg-emerald-950\\/40:hover:not(:disabled) {
        background-color: #bbf7d0 !important;
    }
    
    /* 2. Excluir Selecionados (Vermelho de Perigo) */
    body.theme-jf .bg-red-950\\/40 {
        background-color: #fee2e2 !important;
        border-color: #fca5a5 !important;
        color: #991b1b !important;
        opacity: 1 !important;
    }
    body.theme-jf .bg-red-950\\/40:hover:not(:disabled) {
        background-color: #fecaca !important;
    }
    
    /* 3. Limpar Pauta (Substituído por Cinza Corporativo Sóbrio) */
    body.theme-jf .bg-orange-950\\/40 {
        background-color: #f1f5f9 !important;
        border-color: #cbd5e1 !important;
        color: #334155 !important;
        opacity: 1 !important;
    }
    body.theme-jf .bg-orange-950\\/40:hover:not(:disabled) {
        background-color: #e2e8f0 !important;
    }
    
    /* 4. Voltar Selecionados para Agendados (Substituído por Azul JF Suave) */
    body.theme-jf .bg-amber-950\\/40 {
        background-color: #e0f2fe !important;
        border-color: #bae6fd !important;
        color: var(--color-jf-blue-light) !important;
        opacity: 1 !important;
    }
    body.theme-jf .bg-amber-950\\/40:hover:not(:disabled) {
        background-color: #bae6fd !important;
    }

    /* Overrides de classes de destaque azul do Tailwind */
    body.theme-jf .bg-blue-600 { background-color: var(--color-jf-blue) !important; color: #ffffff !important; }
    body.theme-jf .hover\\:bg-blue-700:hover { background-color: var(--color-jf-blue-hover) !important; }
    body.theme-jf .text-blue-500 { color: var(--color-jf-blue-light) !important; }
    body.theme-jf .text-blue-400 { color: var(--color-jf-blue-light) !important; }
    body.theme-jf .bg-blue-950\\/30 { background-color: #e0f2fe !important; }
    body.theme-jf .bg-blue-950\\/40 { background-color: #bae6fd !important; }
    body.theme-jf .bg-blue-950 { background-color: #f0f9ff !important; }
    body.theme-jf .border-blue-900\\/60 { border-color: #bae6fd !important; }
    body.theme-jf .border-blue-900\\/50 { border-color: #bae6fd !important; }
    body.theme-jf .border-blue-900 { border-color: var(--color-jf-blue) !important; }

    /* Overrides de classes de destaque verde (emerald) do Tailwind */
    body.theme-jf .bg-emerald-600 { background-color: var(--color-jf-green) !important; color: #ffffff !important; }
    body.theme-jf .hover\\:bg-emerald-700:hover { background-color: var(--color-jf-green-hover) !important; }
    body.theme-jf .text-emerald-400 { color: var(--color-jf-green-light) !important; }
    body.theme-jf .bg-emerald-950\\/40 { background-color: #dcfce7 !important; }
    body.theme-jf .border-emerald-900\\/60 { border-color: #bbf7d0 !important; }

    /* Overrides de botões secundários do cabeçalho (indigo, pink) */
    body.theme-jf .text-indigo-400 { color: var(--color-jf-blue-light) !important; }
    body.theme-jf .border-indigo-900\\/60 { border-color: #cbd5e1 !important; }
    body.theme-jf .bg-indigo-950\\/30 { background-color: #f1f5f9 !important; }

    body.theme-jf .text-pink-400 { color: var(--color-jf-green-light) !important; }
    body.theme-jf .border-pink-900\\/60 { border-color: #cbd5e1 !important; }
    body.theme-jf .bg-pink-950\\/30 { background-color: #f1f5f9 !important; }

    /* --- OVERRIDES DA DASHBOARD (dashboard.php) --- */
    /* 1. Cards de estilo Verde (Recepção e Salas) -> Verde JF */
    body.theme-jf .hover\\:border-emerald-500\\/50:hover,
    body.theme-jf .hover\\:border-pink-500\\/50:hover {
        border-color: var(--color-jf-green) !important;
    }
    body.theme-jf .hover\\:shadow-emerald-500\\/5:hover,
    body.theme-jf .hover\\:shadow-pink-500\\/5:hover {
        box-shadow: 0 10px 15px -3px rgba(0, 122, 51, 0.1) !important;
    }
    body.theme-jf .bg-emerald-950\\/50,
    body.theme-jf .bg-pink-950\\/50 {
        background-color: #f0fdf4 !important;
    }
    body.theme-jf .border-emerald-900\\/40,
    body.theme-jf .border-pink-900\\/40 {
        border-color: #bbf7d0 !important;
    }
    body.theme-jf .group:hover .group-hover\\:text-emerald-400,
    body.theme-jf .group:hover .group-hover\\:text-pink-400 {
        color: var(--color-jf-green-light) !important;
    }
    body.theme-jf .text-emerald-500,
    body.theme-jf .text-pink-500 {
        color: var(--color-jf-green-light) !important;
    }

    /* 2. Cards de estilo Azul (Painel do Perito e Perfis) -> Azul JF */
    body.theme-jf .hover\\:border-blue-500\\/50:hover,
    body.theme-jf .hover\\:border-indigo-500\\/50:hover {
        border-color: var(--color-jf-blue) !important;
    }
    body.theme-jf .hover\\:shadow-blue-500\\/5:hover,
    body.theme-jf .hover\\:shadow-indigo-500\\/5:hover {
        box-shadow: 0 10px 15px -3px rgba(0, 47, 108, 0.1) !important;
    }
    body.theme-jf .bg-blue-950\\/50,
    body.theme-jf .bg-indigo-950\\/50 {
        background-color: #f0f9ff !important;
    }
    body.theme-jf .border-blue-900\\/40,
    body.theme-jf .border-indigo-900\\/40 {
        border-color: #bae6fd !important;
    }
    body.theme-jf .group:hover .group-hover\\:text-blue-400,
    body.theme-jf .group:hover .group-hover\\:text-indigo-400 {
        color: var(--color-jf-blue-light) !important;
    }

    /* 3. Cards secundários (TV e Auditoria) -> Cinza JF */
    body.theme-jf .hover\\:border-amber-500\\/50:hover,
    body.theme-jf .hover\\:border-teal-500\\/50:hover {
        border-color: var(--color-jf-gray) !important;
    }
    body.theme-jf .hover\\:shadow-amber-500\\/5:hover,
    body.theme-jf .hover\\:shadow-teal-500\\/5:hover {
        box-shadow: 0 10px 15px -3px rgba(151, 153, 159, 0.1) !important;
    }
    body.theme-jf .bg-amber-950\\/50,
    body.theme-jf .bg-teal-950\\/50 {
        background-color: #f8fafc !important;
    }
    body.theme-jf .border-amber-900\\/40,
    body.theme-jf .border-teal-900\\/40 {
        border-color: #cbd5e1 !important;
    }
    body.theme-jf .group:hover .group-hover\\:text-amber-400,
    body.theme-jf .group:hover .group-hover\\:text-teal-400 {
        color: var(--color-jf-gray) !important;
    }
    body.theme-jf .text-amber-500,
    body.theme-jf .text-teal-500 {
        color: var(--color-jf-gray) !important;
    }
    
    #logo-menu {
        filter: brightness(0) invert(1) !important;
    }
    body.theme-jf #logo-menu {
        filter: none !important;
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

    const headerRight = document.querySelector('.flex.items-center.space-x-4');
    if (headerRight) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.id = 'btn-toggle-theme';
        btn.className = 'text-xs border px-3 py-1.5 rounded-lg transition flex items-center gap-1.5 font-semibold';
        
        if (currentTheme === 'modern') {
            btn.innerHTML = '🎨 Cores JF';
            btn.className += ' text-gray-300 border-gray-750 bg-gray-900 hover:bg-gray-800';
            btn.title = 'Mudar para o padrão claro institucional da Justiça Federal';
        } else {
            btn.innerHTML = '🎨 Tema Original';
            btn.className += ' text-gray-750 border-gray-300 bg-gray-100 hover:bg-gray-200 shadow-sm';
            btn.title = 'Mudar para o padrão moderno escuro';
        }
        
        btn.addEventListener('click', () => {
            const nextTheme = currentTheme === 'modern' ? 'jf' : 'modern';
            setCookie('tema_sga', nextTheme);
            window.location.reload();
        });
        
        // Insere o botão de alternar tema como primeiro item do cabeçalho direito
        headerRight.insertBefore(btn, headerRight.firstChild);
    }
});
