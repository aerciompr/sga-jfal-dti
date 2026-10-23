# Uso do SGA no navegador

## Para os operadores

- Use **Google Chrome ou Microsoft Edge atualizado**.
- Permita JavaScript e os cookies do endereço institucional do SGA.
- Não é necessário instalar extensão, Playwright ou qualquer aplicativo adicional para acessar o sistema.
- Salve o endereço oficial nos favoritos. Use a rede institucional e o endereço HTTPS informado pela DTI.
- A recepção entra diretamente na pauta após o login. Os peritos entram diretamente no painel de atendimento.
- Use **Ajuda**, no cabeçalho, para consultar o passo a passo. **Minha senha** permite trocar a credencial; **Sair** encerra a sessão em um clique.
- Aumentar a leitura: **Ctrl + “+”**. Tamanho original: **Ctrl + 0**. Tela cheia: **F11**.

## Preparar a TV no início do expediente

1. Abra o endereço oficial do painel no computador conectado à TV.
2. O painel abre diretamente pela intranet, sem código de autorização.
3. Clique em **Ativar voz** e depois em **Testar som**.
4. Confira se a frase e o aviso sonoro saem nos alto-falantes corretos. O SGA usa a saída de áudio, sem precisar de microfone.
5. Se ouviu o teste, clique em **Concluir preparação** e ative **Tela cheia**. **Som e tela** reabre esses controles.
6. Mantenha o computador ligado, a aba aberta e a suspensão automática desativada durante o expediente.

Se não houver voz, confira o volume do computador, a saída HDMI/alto-falantes, o som permitido para o site e a voz em português do navegador/sistema. O botão de teste informa quando a síntese não consegue reproduzir a fala. Depois de corrigir, teste novamente.

Em **Som e tela → Toque de alerta**, escolha entre Original, Campainha suave, Ding-dong, Dois bipes discretos, Três notas e Cristal. **Ouvir só o toque** permite comparar sem reproduzir a voz. A escolha e o volume do toque ficam salvos naquele navegador. O volume e a configuração da voz permanecem iguais; colocar o toque em 0% silencia somente o aviso sonoro.

O vídeo do YouTube precisa de internet e de permitir incorporação. Se estiver indisponível, o painel exibe a identidade da JFAL e continua recebendo chamadas pela rede do SGA.

## Validação de usabilidade em 23/10/2026

Foram usados **MCP Playwright e Chromium**, com banco e contas fictícias locais. Passaram os testes de login por clique, mostrar/ocultar senha, busca/limpeza, Enter sem POST indevido, modal por teclado, Escape/foco, tema, ajuda, saída, check-in com senha visível, chamada, início, conclusão, autorização da TV e tela cheia.

Oito páginas foram verificadas em 1366 px e 390 px, sem corte horizontal externo. No celular, os agendamentos são apresentados como cartões e a fila aparece antes das operações administrativas. Tabelas largas restantes têm indicação de rolagem interna.

O teste nativo de voz carregou duas vozes `pt-BR` e recebeu confirmação de término da fala. Isso valida a chamada da API, mas não substitui ouvir o som nos alto-falantes da TV oficial. A lista de vozes pode levar um instante para carregar após a ativação.

Os roteiros reproduzíveis estão em `tests/usabilidade.mcp.js`, `tests/fluxo_operacional.mcp.js`, `tests/telas.mcp.js` e `tests/tv_audio.mcp.js`. São executados pelo MCP Playwright, exclusivamente no ambiente local de teste, após preparar `tests/integration.php`. `SGA_TEST_URL` permite definir o endereço local e `SGA_PLAYWRIGHT_EXECUTABLE` o Chromium já instalado.

As capturas geradas durante a homologação ficam em `.playwright-mcp/`, fora do Git e da imagem Docker. Nenhuma operação de teste foi realizada sobre a produção da JFAL.

O toque padrão passou a ser **Novo SGA**, com o arquivo original `ekiga-vm.wav` servido localmente. Se este navegador já tinha uma escolha salva, selecione **Novo SGA (padrão)** em Som e tela. A fala começa após o toque completo, preservando voz, texto e velocidade.

## Vídeo ou playlist institucional

Na Recepção, como administrador, use **Vídeo ou Playlist da TV**. Cole um link HTTPS de vídeo ou playlist do YouTube e clique em **Atualizar conteúdo**. O painel reconhece automaticamente; links com `list=` usam a playlist inteira. Sem `list=`, o vídeo único se repete. A lista toca em sequência e repete ao final. A alteração chega à TV pela atualização automática, sem reiniciar o sistema.

A playlist deve ser pública ou não listada e seus vídeos devem permitir incorporação, sem exigir login. O computador da TV precisa acessar o YouTube. O vídeo fica mudo durante o anúncio e recupera o estado anterior depois. Não há alteração do esquema do banco: usa a configuração existente `youtube_url`.

## Campos e importação

O CPF aceita digitação ou colagem com e sem pontuação e confere os dígitos verificadores. É opcional e mantém zeros à esquerda. Processos com 20 dígitos são pontuados ao sair do campo; identificadores antigos continuam aceitos até 50 caracteres. Datas usam os controles nativos do navegador.

A importação aceita CPF com ou sem máscara e os dois formatos de processo sem duplicar o mesmo agendamento. Na planilha, mantenha CPFs e números de processo como **texto**, para preservar zeros e todos os dígitos; a máscara não recupera números já alterados pelo Excel. A prévia permite conferir CPF antes de confirmar. Não é necessário aplicar máscara na planilha.

Após **Concluir preparação**, recarregar a TV não reabre os controles. O botão **Reativar voz** permite habilitar o áudio novamente; **Som e tela** reabre as opções a qualquer momento. A escolha do toque e seu volume permanecem salvos no mesmo navegador.
