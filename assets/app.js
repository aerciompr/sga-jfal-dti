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
            'onReady': onPlayerReady
        }
    });
};

// Evento disparado quando o player de vídeo do YouTube está pronto
function onPlayerReady(event) {
    event.target.playVideo();
    if (audioAtivado) {
        event.target.unMute();
        event.target.setVolume(50);
    }
}

// Ativa o áudio do painel (acionado no botão de consentimento do usuário na interface)
function ativarAudio() {
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
}

// Sintetiza um som de gongo (chime) profissional usando a Web Audio API nativa
function playChime() {
    try {
        const AudioContext = window.AudioContext || window.webkitAudioContext;
        const ctx = new AudioContext();
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
        gainNode.gain.linearRampToValueAtTime(0.3, ctx.currentTime + 0.05);
        gainNode.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.8);
        
        osc1.connect(gainNode);
        osc2.connect(gainNode);
        gainNode.connect(ctx.destination);
        
        osc1.start(ctx.currentTime);
        osc2.start(ctx.currentTime);
        osc1.stop(ctx.currentTime + 0.8);
        osc2.stop(ctx.currentTime + 0.8);
    } catch (e) {
        console.error("Erro chime:", e);
    }
}

// Realiza a chamada falada do paciente e sala
function falarSenha(nome, sala) {
    // Reduz o volume do vídeo do YouTube temporariamente para 10%
    if (ytPlayer && typeof ytPlayer.setVolume === 'function') {
        ytPlayer.setVolume(10);
    }

    // Toca sinal sonoro chamando atenção
    playChime();

    // Inicia a fala em português do Brasil
    setTimeout(() => {
        let msg = nome ? `Atenção: ${nome}. Dirija-se à ${sala}` : `Atenção. Dirija-se à ${sala}`;
        
        const utterance = new SpeechSynthesisUtterance(msg);
        utterance.lang = 'pt-BR';
        utterance.rate = 0.95; // Fala ligeiramente mais pausada
        
        // Define voz brasileira se houver no dispositivo
        const voices = window.speechSynthesis.getVoices();
        const ptVoice = voices.find(v => v.lang.startsWith('pt-'));
        if (ptVoice) {
            utterance.voice = ptVoice;
        }

        // Restaura volume ao finalizar
        const originalOnEnd = function() {
            setTimeout(() => {
                if (ytPlayer && typeof ytPlayer.setVolume === 'function') {
                    ytPlayer.setVolume(50);
                }
                const overlay = document.getElementById('chamada-overlay');
                if (overlay) overlay.classList.add('hidden');
            }, 1000);
        };

        // Timeout de segurança se a voz falhar ou demorar para responder
        let safetyTimeout = setTimeout(() => {
            if (ytPlayer && typeof ytPlayer.setVolume === 'function') {
                ytPlayer.setVolume(50);
            }
            const overlay = document.getElementById('chamada-overlay');
            if (overlay) overlay.classList.add('hidden');
        }, 12000);

        utterance.onend = function() {
            clearTimeout(safetyTimeout);
            originalOnEnd();
        };
        
        window.speechSynthesis.speak(utterance);
    }, 400);
}

// Faz requisições HTTP em tempo real (Polling) para buscar novas chamadas
async function verificarNovaSenha() {
    try {
        const response = await fetch('api_senhas.php');
        const data = await response.json();
        
        if (data.success) {
            const novoNome = data.nome;
            const novaSala = data.sala;
            const novoVideoId = data.video_id;
            const novoChamadoEm = data.chamado_em;

            // Recarrega o vídeo se a URL cadastrada na recepção tiver mudado
            if (novoVideoId !== currentVideoId) {
                currentVideoId = novoVideoId;
                if (ytPlayer && typeof ytPlayer.loadVideoById === 'function') {
                    ytPlayer.loadVideoById({
                        videoId: currentVideoId,
                        playlist: currentVideoId
                    });
                }
            }

            // Identifica se há uma nova chamada real comparando o timestamp
            let isNewCall = false;
            if (novoChamadoEm) {
                if (window.currentChamadoEm !== novoChamadoEm) {
                    isNewCall = true;
                }
            } else {
                // Caso as pautas tenham sido resetadas ou apagadas do banco
                if (window.currentChamadoEm) {
                    window.currentNome = "";
                    window.currentSala = "";
                    window.currentChamadoEm = "";

                    const overlay = document.getElementById('chamada-overlay');
                    if (overlay) overlay.classList.add('hidden');

                    atualizarRodape();
                }
            }

            if (isNewCall) {
                window.currentNome = novoNome;
                window.currentSala = novaSala;
                window.currentChamadoEm = novoChamadoEm;

                // Mostra o Modal de Chamada (Overlay) por cima do vídeo
                const overlay = document.getElementById('chamada-overlay');
                const overlayNome = document.getElementById('overlay-nome');
                const overlaySala = document.getElementById('overlay-sala');

                if (overlay && overlayNome && overlaySala) {
                    overlayNome.textContent = novoNome || "Atendimento";
                    overlaySala.textContent = novaSala;
                    
                    overlay.classList.remove('hidden');

                    if (audioAtivado) {
                        falarSenha(novoNome, novaSala);
                    } else {
                        // Fecha automaticamente depois de 6 segundos caso o som não esteja ativado
                        setTimeout(() => {
                            overlay.classList.add('hidden');
                        }, 6000);
                    }
                }

                // Atualiza o histórico de chamadas no rodapé
                atualizarRodape();
            }
        }
    } catch (e) {
        console.error("Erro polling:", e);
    }
}

// Atualiza o fragmento HTML do histórico do rodapé de forma assíncrona
async function atualizarRodape() {
    try {
        const response = await fetch('api_senhas_recentes.php');
        if (response.ok) {
            const html = await response.text();
            const el = document.getElementById('rodape-senhas');
            if (el) el.innerHTML = html;
        }
    } catch(e) {
        console.error("Erro rodape:", e);
    }
}

window.ativarAudio = ativarAudio;
// Verifica novas chamadas a cada 3 segundos para otimizar consumo de CPU e conexões de rede
setInterval(verificarNovaSenha, 3000);

if (typeof speechSynthesis !== 'undefined' && speechSynthesis.onvoiceschanged !== undefined) {
    speechSynthesis.onvoiceschanged = () => {};
}

// Carrega a API do IFrame do YouTube dinamicamente de forma segura
(function() {
    if (!window.YT) {
        const tag = document.createElement('script');
        tag.src = "https://www.youtube.com/iframe_api";
        const firstScriptTag = document.getElementsByTagName('script')[0];
        if (firstScriptTag) {
            firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);
        } else {
            document.head.appendChild(tag);
        }
    }
})();
