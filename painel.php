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

// Obtém os atendimentos chamados no dia de hoje para exibir no rodapé
$today = date('Y-m-d');
$stmt = $pdo->prepare("SELECT * FROM atendimentos WHERE status IN ('chamada', 'concluido') AND data_pauta = ? ORDER BY chamado_em DESC LIMIT 5");
$stmt->execute([$today]);
$recentes = $stmt->fetchAll();

$ultima = $recentes[0] ?? null;
$nome = $ultima ? $ultima['nome'] : 'Aguardando Atendimento';
$sala = $ultima ? $ultima['sala'] : '---';

// Carrega e decodifica a URL do YouTube configurada (prioriza banco de dados com fallback para arquivo)
$yt_url = get_config($pdo, 'youtube_url');
if (empty($yt_url)) {
    $yt_file = __DIR__ . '/youtube_url.txt';
    $yt_url = file_exists($yt_file) ? trim(file_get_contents($yt_file)) : 'https://www.youtube.com/watch?v=5qap5aO4i9A';
}
$video_id = '5qap5aO4i9A';
if (preg_match('%(?:youtube\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $yt_url, $match)) {
    $video_id = $match[1];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=device-width, initial-scale=1.0">
    <title>Painel de Chamadas - JFAL</title>
    <link rel="icon" type="image/png" href="assets/catavento-jfal.png">
    <!-- Tailwind CSS para estilização moderna -->
    <script src="https://cdn.tailwindcss.com"></script>
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
<body class="bg-gray-950 text-white min-h-screen flex flex-col overflow-hidden">
    
    <!-- Cabeçalho Principal (Logo, data e relógio) -->
    <header class="bg-gray-900 border-b border-gray-800 px-6 py-3 flex justify-between items-center shadow-md z-10">
        <div class="flex items-center space-x-4">
            <img src="assets/catavento-jfal.png" alt="JFAL" class="h-8 w-auto object-contain">
            <div>
                <h1 class="text-base font-black tracking-wide text-blue-500 uppercase leading-none">Justiça Federal em Alagoas</h1>
                <p class="text-[10px] text-gray-400 font-semibold tracking-widest uppercase mt-0.5">Seção Judiciária de Alagoas</p>
            </div>
        </div>
        <div class="flex items-center space-x-6">
            <div id="data-atual" class="text-sm text-gray-400 font-medium uppercase">--/--/----</div>
            <div id="relogio" class="text-lg font-bold font-mono text-gray-200">00:00:00</div>
        </div>
    </header>

    <!-- Área Central: Vídeo em Tela Cheia e modal de sobreposição -->
    <main class="flex-1 bg-black overflow-hidden relative">
        
        <!-- Contêiner do reprodutor do YouTube -->
        <div id="player" class="w-full h-full pointer-events-none"></div>

        <!-- Botão flutuante para liberar áudio (exigência de segurança dos navegadores) -->
        <div class="absolute top-4 right-4 z-40">
            <button onclick="ativarAudio()" id="btn-audio" class="bg-blue-600/20 hover:bg-blue-600/35 text-blue-400 border border-blue-500/30 px-3 py-1.5 rounded-xl text-xs font-semibold transition backdrop-blur-sm shadow-lg">
                🔊 Ativar Áudio (Voz)
            </button>
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
                                <div class="text-sm font-black text-white truncate"><?= htmlspecialchars($item['nome'] ?: 'Sem Nome') ?></div>
                                <div class="text-[10px] text-emerald-400 font-semibold truncate mt-0.5"><?= htmlspecialchars($item['sala']) ?></div>
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
        window.initialVideoId = "<?= htmlspecialchars($video_id) ?>";
        window.currentSenha = "";
        window.currentNome = "<?= htmlspecialchars($nome) ?>";
        window.currentSala = "<?= htmlspecialchars($sala) ?>";
        window.currentChamadoEm = "<?= htmlspecialchars($ultima ? $ultima['chamado_em'] : '') ?>";

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
    <script src="assets/app.js"></script>
</body>
</html>
