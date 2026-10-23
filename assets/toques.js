// Toques próprios do projeto, gerados localmente. Não controla síntese de voz.
(() => {
    'use strict';
    const catalogo = [
        {id:'novosga', nome:'Novo SGA (padrão)'},
        {id:'original', nome:'Original (atual)'},
        {id:'suave', nome:'Campainha suave'},
        {id:'dingdong', nome:'Ding-dong'},
        {id:'bipes', nome:'Dois bipes discretos'},
        {id:'notas', nome:'Três notas'},
        {id:'cristal', nome:'Cristal'},
    ];
    // Cada nota: frequência, início, duração, intensidade e forma de onda.
    // Os novos toques terminam antes do início da fala já existente (400 ms).
    const notas = {
        suave: [[659.25,0,.36,.34,'sine'],[1318.5,0,.32,.12,'sine']],
        dingdong: [[783.99,0,.17,.40,'triangle'],[523.25,.18,.18,.40,'triangle']],
        bipes: [[880,0,.13,.42,'triangle'],[880,.21,.13,.42,'triangle']],
        notas: [[523.25,0,.11,.40,'triangle'],[659.25,.12,.11,.40,'triangle'],[783.99,.24,.12,.40,'triangle']],
        cristal: [[1046.5,0,.35,.32,'sine'],[2093,0,.28,.13,'sine'],[1567.98,0,.32,.10,'sine']],
    };
    // Teto conjunto próximo aos dois osciladores do original (0,3 cada).
    // Considera notas simultâneas para evitar saturação ao reforçar o volume.
    const picoConjunto = sequencia => Math.max(...sequencia.map(([,inicio]) =>
        sequencia.reduce((soma,[,atraso,duracao,pico]) => soma+(atraso<=inicio && inicio<atraso+duracao ? pico : 0),0)));
    const chave='sga-tv-toque';
    const chaveVolume='sga-tv-toque-volume';
    let selecionado='novosga', volume=100, contexto=null, ativos=[], audioNovo=null;
    const valido=id=>catalogo.some(t=>t.id===id);
    try {
        const salvo=localStorage.getItem(chave);
        if (valido(salvo)) selecionado=salvo;
        const salvoVolume=localStorage.getItem(chaveVolume);
        if (salvoVolume!==null && salvoVolume.trim()!=='' && Number.isFinite(Number(salvoVolume))) volume=Math.max(0,Math.min(100,Number(salvoVolume)));
    } catch (_) { /* Sem storage, a preferência permanece em memória. */ }

    function salvar(id, intensidade) {
        if (!valido(id)) return false;
        const numero=Number(intensidade);
        if (!Number.isFinite(numero)) return false;
        selecionado=id; volume=Math.max(0,Math.min(100,numero));
        try {
            localStorage.setItem(chave,selecionado); localStorage.setItem(chaveVolume,String(volume));
            return true;
        } catch (_) { return false; }
    }

    function tocar() {
        if (selecionado==='novosga') {
            audioNovo ??= new window.Audio('assets/sons/ekiga-vm.wav');
            audioNovo.pause(); audioNovo.currentTime=0; audioNovo.volume=volume/100;
            if (volume===0) return;
            return audioNovo.play();
        }
        const sequencia=notas[selecionado];
        if (!sequencia || volume===0) return;
        const Audio=window.AudioContext || window.webkitAudioContext;
        if (!Audio) throw new Error('Áudio indisponível neste navegador.');
        contexto ??= new Audio();
        if (contexto.state==='suspended') contexto.resume();
        for (const oscilador of ativos) { try { oscilador.stop(); } catch (_) {} }
        ativos=[];
        const inicio=contexto.currentTime+.015;
        const ajuste=.55/picoConjunto(sequencia);
        for (const [frequencia,atraso,duracao,pico,tipo] of sequencia) {
            const osc=contexto.createOscillator(), ganho=contexto.createGain();
            const comeco=inicio+atraso, fim=comeco+duracao;
            osc.type=tipo; osc.frequency.setValueAtTime(frequencia,comeco);
            ganho.gain.setValueAtTime(.0001,comeco);
            ganho.gain.linearRampToValueAtTime(pico*ajuste*volume/100,comeco+.012);
            ganho.gain.setValueAtTime(pico*ajuste*volume/100,comeco+duracao*.55);
            ganho.gain.exponentialRampToValueAtTime(.0001,fim);
            osc.connect(ganho); ganho.connect(contexto.destination);
            osc.onended=()=>{osc.disconnect();ganho.disconnect();ativos=ativos.filter(a=>a!==osc);};
            ativos.push(osc); osc.start(comeco); osc.stop(fim);
        }
    }

    window.sgaToques={selecionado:()=>selecionado,volume:()=>volume/100,salvar,tocar,espera:()=>selecionado==='novosga' && volume>0 ? 1850 : 400,listar:()=>catalogo.map(t=>({...t}))};
    document.addEventListener('DOMContentLoaded',()=>{
        const select=document.getElementById('toque-alerta'), controle=document.getElementById('volume-toque');
        const valor=document.getElementById('volume-toque-valor'), status=document.getElementById('toque-status');
        const ouvir=document.getElementById('ouvir-toque');
        if (!select || !controle || !valor || !status || !ouvir) return;
        select.value=selecionado; controle.value=String(volume); valor.textContent=`${volume}%`;
        const atualizar=()=>{
            const salvo=salvar(select.value,controle.value);
            valor.textContent=`${volume}%`;
            status.textContent=salvo ? 'Preferência salva neste navegador. O volume da voz não muda.' : 'Selecionado nesta sessão. O navegador não permitiu salvar a preferência.';
        };
        select.addEventListener('change',atualizar);
        controle.addEventListener('input',atualizar);
        ouvir.addEventListener('click',async()=>{
            try {
                if (await window.playChime()===false && volume>0) throw new Error('Falha no áudio');
                status.textContent=volume===0 ? 'Volume do toque em 0%. A voz continua com seu volume atual.' : `Ouvindo: ${catalogo.find(t=>t.id===selecionado).nome}. Este teste não reproduz a voz.`;
            } catch (_) { status.textContent='Não foi possível tocar o alerta. Confira o som do navegador.'; }
        });
    });
})();

