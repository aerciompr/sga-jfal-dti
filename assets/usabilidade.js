document.addEventListener('DOMContentLoaded', () => {
    const limites={username:50,nome:location.pathname.endsWith('/salas.php')?50:100,perito:100,novo_perito:100,nome_avulso:100,yt_url:500};
    for(const [nome,limite] of Object.entries(limites))
        for(const campo of document.querySelectorAll(`input[name="${nome}"]`)) campo.maxLength=limite;
    // CPF usa a mesma pontuação do importador; nenhum caractere inválido é descartado.
    const cpfValido = digitos => {
        if (!/^\d{11}$/.test(digitos) || /^(\d)\1{10}$/.test(digitos)) return false;
        for (let n=9;n<11;n++) {
            let soma=0; for(let i=0;i<n;i++) soma+=Number(digitos[i])*(n+1-i);
            if (Number(digitos[n])!==(soma*10%11)%10) return false;
        }
        return true;
    };
    for (const campo of document.querySelectorAll('input[name="cpf"]')) {
        campo.inputMode='numeric'; campo.maxLength=14;
        const atualizar=()=>{
            const texto=campo.value, digitos=texto.replace(/\D/g,'');
            const permitido=/^[\d.\-\s]*$/.test(texto);
            const posicao=campo.selectionStart;
            const antes=texto.slice(0,posicao??texto.length).replace(/\D/g,'').length;
            if (permitido && digitos.length<=11) {
                campo.value=digitos.replace(/^(\d{3})(\d)/,'$1.$2').replace(/^(\d{3}\.\d{3})(\d)/,'$1.$2').replace(/(\d{3}\.\d{3}\.\d{3})(\d)/,'$1-$2');
                if(document.activeElement===campo && posicao!==null) {
                    let pos=0, vistos=0;
                    while(pos<campo.value.length && vistos<antes) {if(/\d/.test(campo.value[pos])) vistos++; pos++;}
                    campo.setSelectionRange(pos,pos);
                }
            }
            campo.setCustomValidity(texto.trim()==='' || (permitido && cpfValido(digitos)) ? '' : 'Informe um CPF válido com 11 dígitos ou deixe o campo vazio.');
        };
        campo.addEventListener('input',atualizar); campo.addEventListener('blur',atualizar);
        atualizar();
    }
    // Só formata processos com 20 dígitos completos; identificadores legados continuam aceitos.
    for (const campo of document.querySelectorAll('input[name="processo"]')) {
        campo.maxLength=50;
        campo.addEventListener('blur',()=>{
            const texto=campo.value.trim(), digitos=texto.replace(/\D/g,'');
            if (/^[\d.\-\s]+$/.test(texto) && digitos.length===20)
                campo.value=digitos.replace(/^(\d{7})(\d{2})(\d{4})(\d)(\d{2})(\d{4})$/,'$1-$2.$3.$4.$5.$6');
        });
    }
    for (const campo of document.querySelectorAll('input[name="password"],input[name="nova_senha"],input[name="confirma_senha"]')) {
        if (location.pathname.endsWith('/index.php')) continue;
        const validar=()=>{
            const bytes=new TextEncoder().encode(campo.value).length;
            campo.setCustomValidity(campo.value==='' || (bytes>=12 && bytes<=72) ? '' : 'Use uma senha de 12 a 72 bytes; letras acentuadas podem ocupar mais de um byte.');
        };
        campo.addEventListener('input',validar);
    }
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
