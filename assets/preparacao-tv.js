// Guarda apenas a conclusão da preparação; a permissão de áudio não é presumida.
document.addEventListener('DOMContentLoaded', () => {
    const painel=document.getElementById('preparar-tv');
    const concluir=document.getElementById('fechar-preparo-tv');
    const reativar=document.getElementById('reativar-voz-tv');
    if (!painel || !concluir || !reativar) return;
    const chave='sga-tv-preparada';
    try {
        if (localStorage.getItem(chave)==='1') {
            painel.classList.add('hidden');
            reativar.hidden=false;
        }
    } catch (_) { /* Sem armazenamento, mantém a preparação visível. */ }
    concluir.addEventListener('click', () => {
        try { localStorage.setItem(chave,'1'); } catch (_) {}
        reativar.hidden=true;
    });
    reativar.addEventListener('click', () => {
        ativarAudio();
        reativar.hidden=document.getElementById('btn-audio')?.textContent.includes('Voz Ativa')===true;
    });
    document.getElementById('btn-audio')?.addEventListener('click', () => {
        reativar.hidden=document.getElementById('btn-audio').textContent.includes('Voz Ativa');
    });
});
