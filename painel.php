<?php
/**
 * SISTEMA DE CHAMADA DE SENHAS - JFAL
 * 
 * painel.php: Tela da TV da Recepção.
 * Exibe um vídeo do YouTube em tela cheia com um rodapé contendo as últimas chamadas.
 * Quando um novo paciente é chamado, um modal (overlay) surge por cima do vídeo,
 * o volume do vídeo é reduzido e a chamada é anunciada por voz.
 */

require_once __DIR__ . '/banco.php';

$recentes = $pdo->query('SELECT nome, sala, criado_em AS chamado_em FROM eventos_chamada WHERE data_pauta = CURRENT_DATE() ORDER BY id DESC LIMIT 4')->fetchAll();
$ultima = $recentes[0] ?? null;
$nome = $ultima['nome'] ?? 'Aguardando Atendimento';
$sala = $ultima['sala'] ?? '---';
$video_id = videoId(get_config($pdo, 'youtube_url'));
$cursorInicial = (int)$pdo->query('SELECT COALESCE(MAX(id), 0) FROM eventos_chamada')->fetchColumn();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=device-width, initial-scale=1.0">
    <title>Painel de Chamadas - JFAL</title>
    <link rel="icon" type="image/png" href="assets/catavento-jfal.png">
    <!-- Tailwind CSS para estilização moderna -->
    <link rel="stylesheet" href="assets/tailwind.css">
    <link rel="stylesheet" href="assets/usabilidade.css">
    <script src="tema.js"></script>
    <style>
        /* Animação suave para surgimento do modal de chamada */
        @keyframes slide-in {
            0% { transform: translateY(-50px) scale(0.95); opacity: 0; }
            100% { transform: translateY(0) scale(1); opacity: 1; }
        }
        .animate-slide-in {
            animation: slide-in 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }
        /* Player de vídeo posicionado de forma absoluta para preencher a tela */
        #player {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
        }
    </style>
</head>
<body class="sga-tv bg-gray-950 text-white h-screen flex flex-col overflow-hidden">
    
    <!-- Cabeçalho Principal (Logo, data e relógio) -->
    <header class="bg-gray-900 border-b border-gray-800 px-6 py-3 flex justify-between items-center shadow-md z-10">
        <div class="flex items-center space-x-4">
            <img src="assets/catavento-jfal.png" alt="JFAL" class="h-8 w-auto object-contain">
            <div>
                <h1 class="text-base font-black tracking-wide text-blue-500 uppercase leading-none">Justiça Federal em Alagoas</h1>
                <p class="text-[10px] text-gray-400 font-semibold tracking-widest uppercase mt-0.5">Seção Judiciária de Alagoas</p>
            </div>
        </div>
        <div class="flex items-center gap-4"><span id="conexao-tv" role="status">Conectando…</span><button type="button" onclick="document.getElementById('preparar-tv').classList.remove('hidden')" class="sga-neutral">Som e tela</button>
            <div id="data-atual" class="text-sm text-gray-400 font-medium uppercase">--/--/----</div>
            <div id="relogio" class="text-lg font-bold font-mono text-gray-200">00:00:00</div>
        </div>
    </header>

    <!-- Área Central: Vídeo em Tela Cheia e modal de sobreposição -->
    <main class="flex-1 bg-black overflow-hidden relative">
        <div class="sga-tv-fallback">
            <img src="assets/catavento-jfal.png" alt="Justiça Federal em Alagoas" class="h-24 mb-6">
            <h2>Aguardando próximas chamadas</h2>
            <p id="video-info">Acompanhe o nome e a sala indicados na tela.</p>
        </div>
        
        <!-- Contêiner do reprodutor do YouTube -->
        <div id="player" class="w-full h-full pointer-events-none"></div>

        <div class="sga-tv-instructions" id="preparar-tv">
            <h2 class="font-bold mb-2">Antes de iniciar os atendimentos</h2>
            <p id="audio-status" role="status" class="text-sm mb-3">Ative a voz e teste o som nos alto-falantes da TV.</p>
            <div class="sga-toques">
                <label for="toque-alerta">Toque de alerta</label>
                <div class="sga-toolbar">
                    <select id="toque-alerta" class="sga-toque-select">
                        <option value="novosga">Novo SGA (padrão)</option>
                        <option value="original">Original (atual)</option>
                        <option value="suave">Campainha suave</option>
                        <option value="dingdong">Ding-dong</option>
                        <option value="bipes">Dois bipes discretos</option>
                        <option value="notas">Três notas</option>
                        <option value="cristal">Cristal</option>
                    </select>
                    <button type="button" id="ouvir-toque" class="sga-neutral">Ouvir só o toque</button>
                </div>
                <div class="sga-toolbar">
                    <label for="volume-toque">Volume do toque</label>
                    <input type="range" id="volume-toque" min="0" max="100" step="5" value="100" aria-describedby="toque-status">
                    <output id="volume-toque-valor" for="volume-toque">100%</output>
                </div>
                <p id="toque-status" role="status">A escolha fica salva neste navegador. A voz permanece igual.</p>
            </div>
            <div class="sga-toolbar">
                <button type="button" onclick="ativarAudio()" id="btn-audio" class="sga-neutral">Ativar voz</button>
                <button type="button" onclick="testarSom()" class="sga-neutral">Testar som</button>
                <button type="button" onclick="alternarTelaCheia()" id="btn-tela-cheia" class="sga-neutral">Tela cheia</button>
                <button type="button" onclick="document.getElementById('preparar-tv').classList.add('hidden')" id="fechar-preparo-tv" class="sga-neutral" hidden>Concluir preparação</button>
            </div>
        </div>

        <!-- Modal de Chamada (Escondido por padrão, aparece quando um paciente é chamado) -->
        <div id="chamada-overlay" class="absolute inset-0 bg-black/85 flex items-center justify-center hidden z-50">
            <div class="bg-gray-900 border border-blue-500/30 rounded-3xl p-12 max-w-2xl w-full mx-4 shadow-2xl text-center animate-slide-in relative">
                <div class="absolute inset-x-0 top-0 h-2 bg-gradient-to-r from-blue-500 to-indigo-600 rounded-t-3xl"></div>
                <div class="flex flex-col items-center mb-6">
                    <img src="assets/catavento-jfal.png" alt="JFAL" class="h-12 w-auto object-contain mb-3">
                    <div class="text-blue-500 font-extrabold text-sm uppercase tracking-widest">🔔 Atenção! Chamando...</div>
                </div>
                
                <!-- Nome do Paciente Chamado -->
                <h2 id="overlay-nome" class="text-5xl md:text-6xl font-black text-white mb-10 leading-tight">--</h2>
                
                <!-- Sala de Destino -->
                <div class="bg-gray-800/50 p-8 rounded-2xl border border-gray-700/50">
                    <div class="text-xs text-gray-400 font-semibold uppercase tracking-wider mb-2">Dirija-se ao Local</div>
                    <div id="overlay-sala" class="text-4xl md:text-5xl font-black text-emerald-400">--</div>
                </div>
            </div>
        </div>
    </main>

    <!-- Rodapé: Grid com as últimas 4 chamadas do dia -->
    <footer class="bg-gray-900 border-t border-gray-800 p-4 z-10">
        <div class="max-w-7xl mx-auto flex items-center justify-between">
            <div class="flex items-center space-x-3 w-1/5 border-r border-gray-800 pr-4">
                <span class="text-lg font-bold">📢</span>
                <div>
                    <h3 class="text-xs font-black uppercase text-gray-400 tracking-wider">Últimas</h3>
                    <p class="text-[9px] text-gray-500 uppercase font-semibold leading-none">Chamadas de hoje</p>
                </div>
            </div>
            
            <div id="rodape-senhas" class="flex-1 grid grid-cols-4 gap-4 pl-4 text-center">
                <!-- Populado dinamicamente via JS e renderizado inicialmente pelo PHP -->
                <?php for ($i = 0; $i < 4; $i++): ?>
                    <?php 
                    $item = $recentes[$i] ?? null;
                    if ($item):
                    ?>
                        <div class="bg-gray-950/60 border border-gray-800/50 rounded-xl py-2.5 px-4 flex justify-between items-center <?= $i === 0 ? 'border-blue-500/30 bg-blue-950/20' : '' ?>">
                            <div class="text-left truncate flex-1">
                                <div class="text-sm font-black text-white truncate"><?= escapar($item['nome'] ?: 'Sem Nome') ?></div>
                                <div class="text-[10px] text-emerald-400 font-semibold truncate mt-0.5"><?= escapar($item['sala']) ?></div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="bg-gray-950/20 border border-gray-800/20 border-dashed rounded-xl py-2 px-3 flex items-center justify-center text-xs text-gray-600">
                            ---
                        </div>
                    <?php endif; ?>
                <?php endfor; ?>
            </div>
        </div>
    </footer>

    <script>
        // Variáveis globais compartilhadas com assets/app.js
        window.initialVideoId = <?= json_encode($video_id, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        window.initialCursor = <?= $cursorInicial ?>;
        window.eventDay = '<?= date('Y-m-d') ?>';

        // Relógio e Data do painel
        function updateClock() {
            const now = new Date();
            document.getElementById('relogio').textContent = now.toLocaleTimeString('pt-BR');
            document.getElementById('data-atual').textContent = now.toLocaleDateString('pt-BR', {
                day: '2-digit', month: '2-digit', year: 'numeric'
            });
        }
        setInterval(updateClock, 1000);
        updateClock();
    </script>

    <link rel="preload" href="assets/sons/ekiga-vm.wav" as="fetch" crossorigin>
    <script src="assets/toques.js"></script>
    <script src="assets/app.js"></script>
<script src="assets/acessibilidade.js" defer></script>
<script src="assets/usabilidade.js" defer></script>
</body>
</html>
