document.addEventListener('DOMContentLoaded', () => {
    // Os atributos já existem no HTML principal; estes controles também atendem formulários de gestão.
    for (const input of document.querySelectorAll('input[type="password"]')) {
        const wrapper = document.createElement('div'); wrapper.className = 'sga-password';
        const labelPai = input.closest('label');
        if (labelPai) labelPai.after(wrapper); else input.before(wrapper);
        wrapper.appendChild(input);
        const toggle = document.createElement('button'); toggle.type = 'button'; toggle.textContent = 'Mostrar';
        const nome = input.labels?.[0]?.textContent.trim() || 'senha';
        toggle.setAttribute('aria-label', `Mostrar ${nome.toLowerCase()}`); toggle.setAttribute('aria-pressed','false');
        toggle.addEventListener('click', () => {
            const mostrar = input.type === 'password'; input.type = mostrar ? 'text' : 'password';
            toggle.textContent = mostrar ? 'Ocultar' : 'Mostrar'; toggle.setAttribute('aria-pressed',String(mostrar));
            toggle.setAttribute('aria-label', `${mostrar ? 'Ocultar' : 'Mostrar'} ${nome.toLowerCase()}`);
        }); wrapper.appendChild(toggle);
        const caps = document.createElement('span'); caps.className = 'sga-helper sga-caps'; caps.textContent = 'Caps Lock está ativado.'; caps.hidden = true; caps.setAttribute('role','status');
        wrapper.after(caps);
        input.addEventListener('keyup', e => { caps.hidden = !e.getModifierState?.('CapsLock'); });
        input.addEventListener('blur', () => { caps.hidden = true; });
    }
    const pagina = location.pathname.split('/').pop() || 'index.php';
    for (const link of document.querySelectorAll('header a[href]')) {
        if (link.getAttribute('href') === pagina) link.setAttribute('aria-current','page');
    }
    for (const form of document.querySelectorAll('form[method="POST"],form[method="post"]')) {
        form.addEventListener('submit', event => {
            if (event.defaultPrevented) return;
            if (form.dataset.enviando === '1') { event.preventDefault(); return; }
            form.dataset.enviando = '1'; form.setAttribute('aria-busy','true');
            // Não desabilitar o submitter: seu name/action precisa integrar o POST.
            if (event.submitter) { event.submitter.setAttribute('aria-disabled','true'); event.submitter.textContent = 'Aguarde…'; }
        });
    }
    for (const area of document.querySelectorAll('.overflow-x-auto')) {
        const dica=document.createElement('p'); dica.className='sga-helper'; dica.textContent='Deslize a tabela para ver todas as colunas.';
        area.after(dica);
        const atualizar=()=>{ dica.hidden=area.scrollWidth<=area.clientWidth+1; };
        new ResizeObserver(atualizar).observe(area); atualizar();
    }
    window.addEventListener('pageshow', event => { if(event.persisted) location.reload(); });
    document.querySelector('.sga-feedback')?.scrollIntoView({block:'nearest'});
});
