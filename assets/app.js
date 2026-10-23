/**
 * SCRIPT CLIENT-SIDE DO PAINEL DA TV - JFAL
 * 
 * Controla a integração com a API do IFrame do YouTube, sintetiza sons de chamada (chime),
 * gerencia a síntese de voz nativa da Web Speech API e atualiza os elementos em tempo real (Polling).
 */

let audioAtivado = false;
let ytPlayer = null;
let currentVideoId = window.initialVideoId || '5qap5aO4i9A';

// Método de callback acionado automaticamente pela API do IFrame do YouTube
window.onYouTubeIframeAPIReady = function() {
    ytPlayer = new YT.Player('player', {
        height: '100%',
        width: '100%',
        videoId: currentVideoId,
        playerVars: {
            'autoplay': 1,
            'mute': 1,       // Começa mutado por exigência de segurança dos navegadores modernos
            'loop': 1,       // Loop infinito do vídeo de relaxamento
            'playlist': currentVideoId,
            'controls': 0,   // Remove barra de controles
            'showinfo': 0,
            'rel': 0,
            'modestbranding': 1,
            'iv_load_policy': 3
        },
        events: {
            'onReady': onPlayerReady,
            'onError': videoIndisponivel
        }
    });
};

function videoIndisponivel() {
    const player = document.getElementById('player');
    if (player) player.hidden = true;
    const info = document.getElementById('video-info');
    if (info) info.textContent = 'Vídeo indisponível. As chamadas continuam normalmente.';
}

// Evento disparado quando o player de vídeo do YouTube está pronto
function onPlayerReady(event) {
    const player = document.getElementById('player');
    if (player) player.hidden = false;
    event.target.playVideo();
    if (audioAtivado) {
        event.target.unMute();
        event.target.setVolume(50);
    }
}

// Ativa o áudio do painel (acionado no botão de consentimento do usuário na interface)
function ativarAudio() {
    if (!('speechSynthesis' in window) || !('SpeechSynthesisUtterance' in window)) {
        const aviso = document.getElementById('audio-status');
        if (aviso) aviso.textContent = 'Voz indisponível neste navegador. Use Chrome ou Edge atualizado. As chamadas continuam na tela.';
        return;
    }
    audioAtivado = true;
    const btn = document.getElementById('btn-audio');
    if (btn) {
        btn.textContent = "🔊 Voz Ativa";
        btn.className = "bg-emerald-600/20 hover:bg-emerald-600/30 text-emerald-400 border border-emerald-500/30 px-3 py-1.5 rounded-xl text-xs font-semibold transition backdrop-blur-sm";
    }
    
    // Toca som vazio para destravar o sintetizador de voz dos navegadores
    try {
        const u = new SpeechSynthesisUtterance("");
        window.speechSynthesis.speak(u);
    } catch(e) {
        console.error("Erro voz:", e);
    }

    // Desmuta o vídeo do YouTube
    if (ytPlayer && typeof ytPlayer.unMute === 'function') {
        ytPlayer.unMute();
        ytPlayer.setVolume(50);
    }
    
    // Toca o gongo de chamada
    playChime();
    const aviso = document.getElementById('audio-status');
    if (aviso) aviso.textContent = 'Voz ativada. Clique em “Testar som” para conferir os alto-falantes.';
}

async function testarSom() {
    ativarAudio();
    if (!audioAtivado) return;
    const falou = await falarSenha('Teste de som do SGA', 'recepção');
    const aviso = document.getElementById('audio-status');
    if (aviso) aviso.textContent = falou ? 'Teste concluído. Se ouviu a frase, conclua a preparação.' : 'Não foi possível reproduzir a voz. Confira o volume, a voz em português e as permissões do site.';
    const concluir = document.getElementById('fechar-preparo-tv');
    if (concluir) concluir.hidden = !falou;
}

async function alternarTelaCheia() {
    try {
        if (document.fullscreenElement) await document.exitFullscreen();
        else await document.documentElement.requestFullscreen();
    } catch (_) {
        const aviso = document.getElementById('audio-status');
        if (aviso) aviso.textContent = 'Use F11 no teclado para ativar a tela cheia.';
    }
}
document.addEventListener('fullscreenchange', () => {
    const botao = document.getElementById('btn-tela-cheia');
    if (botao) botao.textContent = document.fullscreenElement ? 'Sair da tela cheia' : 'Tela cheia';
});

// Sintetiza um som de gongo (chime) profissional usando a Web Audio API nativa
function playChime() {
    try {
        const toques=window.sgaToques;
        const volumeToque=toques?.volume() ?? 1;
        if (volumeToque===0) return false;
        if (toques && toques.selecionado()!=='original') { return toques.tocar(); }
        const AudioContext = window.AudioContext || window.webkitAudioContext;
        audioContext ??= new AudioContext();
        const ctx = audioContext;
        if (ctx.state === 'suspended') ctx.resume();
        const osc1 = ctx.createOscillator();
        const osc2 = ctx.createOscillator();
        const gainNode = ctx.createGain();
        
        osc1.type = 'sine';
        osc1.frequency.setValueAtTime(523.25, ctx.currentTime); // C5
        osc1.frequency.exponentialRampToValueAtTime(880, ctx.currentTime + 0.15); // A5
        
        osc2.type = 'triangle';
        osc2.frequency.setValueAtTime(659.25, ctx.currentTime); // E5
        osc2.frequency.exponentialRampToValueAtTime(1046.50, ctx.currentTime + 0.15); // C6
        
        gainNode.gain.setValueAtTime(0, ctx.currentTime);
        gainNode.gain.linearRampToValueAtTime(0.3 * volumeToque, ctx.currentTime + 0.05);
        gainNode.gain.exponentialRampToValueAtTime(0.001 * volumeToque, ctx.currentTime + 0.8);
        
        osc1.connect(gainNode);
        osc2.connect(gainNode);
        gainNode.connect(ctx.destination);
        
        osc1.start(ctx.currentTime);
        osc2.start(ctx.currentTime);
        osc1.stop(ctx.currentTime + 0.8);
        osc2.stop(ctx.currentTime + 0.8);
        return true;
    } catch (e) {
        console.error("Erro chime:", e);
        return false;
    }
}

// Entrega em ordem por evento persistido, sem comparar relógios ou estados mutáveis.
let audioContext = null;
const cursorKey = `sga-tv-cursor-${window.eventDay}`;
let cursor = Number(window.initialCursor ?? 0);
try { cursor = Number(sessionStorage.getItem(cursorKey) ?? cursor); } catch (_) { /* Cursor em memória caso o navegador bloqueie armazenamento. */ }
if (!Number.isSafeInteger(cursor) || cursor < 0) cursor = Number(window.initialCursor ?? 0);
let processando = false;
const esperar = ms => new Promise(resolve => setTimeout(resolve, ms));

function falarSenha(nome, sala) {
    return new Promise(resolve => {
        if (!audioAtivado || !window.speechSynthesis) { esperar(6000).then(() => resolve(false)); return; }
        if (ytPlayer?.setVolume) ytPlayer.setVolume(10);
        playChime();
        setTimeout(() => {
            const fala = new SpeechSynthesisUtterance(`Atenção: ${nome || 'Atendimento'}. Dirija-se à ${sala}`);
            fala.lang = 'pt-BR'; fala.rate = 0.95;
            const voz = speechSynthesis.getVoices().find(v => v.lang.startsWith('pt-'));
            if (voz) fala.voice = voz;
            let concluido = false;
            const fim = (sucesso) => {
                if (concluido) return;
                concluido = true; clearTimeout(timeout);
                if (ytPlayer?.setVolume) ytPlayer.setVolume(50);
                resolve(sucesso);
            };
            const timeout = setTimeout(() => { speechSynthesis.cancel(); fim(false); }, 20000);
            fala.onend = () => fim(true); fala.onerror = () => fim(false);
            speechSynthesis.speak(fala);
        }, window.sgaToques?.espera() ?? 400);
    });
}

async function atualizarRodape() {
    const resposta = await fetch('api_senhas_recentes.php', { cache: 'no-store' });
    if (!resposta.ok) throw new Error('Histórico indisponível');
    document.getElementById('rodape-senhas').innerHTML = await resposta.text();
}

async function verificarNovaSenha() {
    if (processando) return;
    processando = true;
    const indicador = document.getElementById('conexao-tv');
    try {
        const resposta = await fetch(`api_senhas.php?apos=${cursor}`, { cache: 'no-store', signal: AbortSignal.timeout(10000) });
        if (!resposta.ok) throw new Error('Painel sem autorização ou conexão');
        const dados = await resposta.json();
        if (!dados.success) throw new Error('Resposta inválida');
        indicador.textContent = `Conectado • ${new Date().toLocaleTimeString('pt-BR')}`;
        if (dados.video_id !== currentVideoId) {
            currentVideoId = dados.video_id;
            const player = document.getElementById('player');
            if (player) player.hidden = false;
            if (ytPlayer?.loadVideoById) ytPlayer.loadVideoById(currentVideoId);
        }
        for (const evento of dados.eventos) {
            if (Number(evento.id) <= cursor) continue;
            const overlay = document.getElementById('chamada-overlay');
            document.getElementById('overlay-nome').textContent = evento.nome || 'Atendimento';
            document.getElementById('overlay-sala').textContent = evento.sala;
            overlay.classList.remove('hidden');
            const falou = await falarSenha(evento.nome, evento.sala);
            if (!falou && audioAtivado) await esperar(5000);
            await esperar(1000); overlay.classList.add('hidden');
            cursor = Number(evento.id);
            try { sessionStorage.setItem(cursorKey, String(cursor)); } catch (_) { /* Mantém a fila funcionando sem storage. */ }
        }
        await atualizarRodape();
    } catch (erro) {
        indicador.textContent = 'Sem atualização — verifique conexão/autorização';
        console.error(erro);
    } finally {
        processando = false; setTimeout(verificarNovaSenha, 2000);
    }
}
window.ativarAudio = ativarAudio;
verificarNovaSenha();
(function() {
    if (!window.YT) {
        const tag = document.createElement('script'); tag.src = 'https://www.youtube.com/iframe_api'; tag.onerror = videoIndisponivel; document.head.appendChild(tag);
    }
})();
