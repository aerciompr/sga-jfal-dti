// Complementa os controles existentes sem alterar as regras operacionais.
document.addEventListener('DOMContentLoaded', () => {
    let sequencia = 0;
    for (const label of document.querySelectorAll('label:not([for])')) {
        const campo = label.querySelector('input,select,textarea') || label.nextElementSibling;
        if (!campo?.matches('input,select,textarea')) continue;
        campo.id ||= `sga-campo-${++sequencia}`;
        label.htmlFor = campo.id;
    }
    for (const linha of document.querySelectorAll('tr[onclick]')) {
        linha.tabIndex = 0;
        linha.setAttribute('aria-label', linha.dataset.nome ? `Ver detalhes de ${linha.dataset.nome}` : 'Ver detalhes do agendamento');
        linha.addEventListener('keydown', event => {
            if (event.target === linha && ['Enter', ' '].includes(event.key)) {
                event.preventDefault(); linha.click();
            }
        });
    }
    const modais = [...document.querySelectorAll('[id^="modal-"].fixed')];
    const anteriores = new WeakMap();
    for (const modal of modais) {
        modal.setAttribute('role', 'dialog'); modal.setAttribute('aria-modal', 'true');
        modal.setAttribute('aria-label', modal.querySelector('h2,h3')?.textContent.trim() || 'Detalhes');
        const mudou = () => {
            const oculto = modal.classList.contains('hidden');
            modal.setAttribute('aria-hidden', String(oculto));
            if (!oculto && !modal.contains(document.activeElement)) {
                anteriores.set(modal, document.activeElement);
                modal.querySelector('input,select,button,[tabindex]')?.focus();
            } else if (oculto && modal.contains(document.activeElement)) anteriores.get(modal)?.focus();
        };
        new MutationObserver(mudou).observe(modal, {attributes: true, attributeFilter: ['class']}); mudou();
        modal.addEventListener('keydown', event => {
            if (event.key === 'Escape') {
                modal.classList.add('hidden'); anteriores.get(modal)?.focus(); return;
            }
            if (event.key !== 'Tab') return;
            const itens=[...modal.querySelectorAll('button,input,select,textarea,a[href],[tabindex="0"]')].filter(e=>!e.disabled && e.getClientRects().length);
            const primeiro=itens[0], ultimo=itens[itens.length-1];
            if (event.shiftKey && document.activeElement===primeiro) {event.preventDefault();ultimo?.focus();}
            else if (!event.shiftKey && document.activeElement===ultimo) {event.preventDefault();primeiro?.focus();}
        });
    }
});
