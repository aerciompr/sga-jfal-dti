# Revisão final de controles, rotas e campos

Revisão local em 23/10/2026, com PHP, MariaDB descartável `sga_test` e Chromium pelo MCP Playwright. Nenhum teste acessou a produção. O resultado cobre os controles e estados descritos, sem prometer todas as combinações possíveis ou ausência de defeitos.

## Correções concluídas

- Cancelamento individual em Pautas passou a enviar o ID correto; antes, o campo vazio fazia a operação atualizar zero registros.
- Transferência mensal aceita o mês/ano enviado pelo controle nativo e preserva o dia, limitado ao último dia do destino. Datas hostis continuam rejeitadas.
- Pautas preserva o filtro depois das operações. Registros cancelados/concluídos não oferecem ações incompatíveis; botões em lote respeitam os estados selecionados.
- Recepção não oferece edição de pessoa já presente nem desfaz presença que já teve chamada.
- Busca de usuários responde também à colagem. Editar outro usuário limpa senha residual do formulário. Limites dos campos acompanham o servidor.
- CPF ganha máscara, validação dos dígitos e normalização no servidor para o mesmo formato do importador. Processo completo com 20 dígitos ganha pontuação ao sair do campo; identificadores legados continuam aceitos.
- Senhas novas são verificadas em bytes UTF-8, de acordo com o servidor, sem modificar senha atual ou login. Datas/mês usam controles nativos, URLs e nomes não recebem máscaras restritivas.
- Prévia da importação mostra CPF e permite rolagem da tabela. Importação e reimportação foram testadas com pontuação e sem duplicidade.
- TV aceita vídeo ou playlist, mantém o toque Novo SGA e silencia o vídeo durante o anúncio, restaurando o estado anterior em término, erro ou timeout. Voz, texto e velocidade preservados.
- Preparação da TV concluída fica salva; após recarregar, há um botão para reativar a voz. As opções continuam disponíveis em Som e tela.
- Política de referência da TV permite identificar a origem ao YouTube sem enviar caminho ou parâmetros. A política anterior suprimia a referência externa e podia causar erro 153. O restante das páginas mantém `same-origin`. Referências: [identificação do player](https://developers.google.com/youtube/terms/required-minimum-functionality#api-client-identity-and-credentials), [códigos de erro](https://developers.google.com/youtube/iframe_api_reference#onError).
- `session.upload_progress.enabled=0` é aplicado no PHP da imagem, conforme orientação oficial de 23/10. Não é utilizado pelos formulários do SGA. [Aviso PHP](https://www.php.net/archive/2026.php#2026-10-23-1).

## Cobertura executada

| Área | Resultado local |
|---|---|
| Integração PHP, estados, auditoria, duplicidade e máscaras | 27 verificações passaram |
| Concorrência | Duas conexões: uma chamada aceita, segunda bloqueada |
| HTTP e importador Python | 25 testes passaram; 1 teste de Apache não executado nesta rodada |
| Matriz de rotas | Administrador, supervisor, recepção, perito e anônimo; páginas e APIs protegidas conforme perfil |
| JavaScript | 13 testes passaram, incluindo restauração do áudio em término/erro/timeout/exceção |
| Endereços de vídeo e playlist | 12 verificações PHP passaram |
| Usabilidade MCP | 15 verificações: login, busca, Enter, foco, Escape, tema, ajuda e logout |
| Fluxo operacional MCP | 10 verificações: check-in, chamada, início, conclusão e tela cheia |
| Controles administrativos MCP | 73 verificações, incluindo menus, usuários, salas, edição, transferência, homologação, vídeo/playlist, chamada manual e CSV |
| Operações em lote MCP | 11 verificações: presença/desfazer, cancelar, mover dia/mês e substituir perito |
| Importação por clique MCP | 8 verificações: Recepção e Pautas, prévia, cancelar, confirmar, Espaço nos detalhes e reimportar |
| Toques MCP | 14 verificações: sete opções, volume, persistência, silêncio e storage bloqueado |
| Voz nativa e preparação MCP | Fala terminou com duas vozes pt-BR; conclusão persistiu, reativação e reabertura passaram |
| Telas MCP | Oito páginas em 1366 e 390 px: 16 verificações sem corte externo ou erros JavaScript |
| Playlist MCP | API YouTube simulada confirmou troca vídeo → playlist → vídeo e repetição |
| Sintaxe/build | 32 arquivos PHP sem erro; JavaScript e compilação CSS passaram |

Rotas de interface verificadas: `index.php`, `dashboard.php`, `recepcao.php`, `atendente.php`, `gerenciar_pauta.php`, `salas.php`, `usuarios.php`, `auditoria.php`, `ajuda.php`, `alterar_senha_obrigatorio.php`, `preview_importacao.php`, `logout.php`, `painel.php`; APIs `api_senhas.php`, `api_senhas_recentes.php`, `api_verificar_outro_dia.php` e `health.php`. Incluídos redirecionamentos por perfil, rejeição de POST sem CSRF e prévia ausente/expirada. Arquivos de serviço não são rotas de interface; a regra Apache foi revisada para também bloquear `youtube.php`.

Atalhos testados: Enter na busca sem POST, Enter/Espaço nos detalhes, Escape com restauração de foco; botões de tela cheia e retorno. Zoom e F11 são funções nativas do navegador, sem interceptação pelo sistema. Recarregar foi testado na persistência da TV. A continuidade de áudio exige interação quando o navegador solicitar.

## Importação e máscaras

Não há nova migração de esquema nesta revisão. Pontuação não muda a chave de comparação de processo e não duplica registros. CPF manual e importado usam o mesmo formato. Os testes confirmaram CPF com/sem máscara, zero à esquerda quando preservado como texto, CPF numérico válido, data Excel e processo legado.

Planilhas devem manter CPF e número de processo como texto para preservar zeros e precisão. Não se tenta recuperar dígitos já perdidos no Excel. A validação do importador permanece independente do JavaScript da página.

## Limites para implantação

Nesta rodada foi usado o servidor PHP local, sem Apache/Docker em execução. O teste específico de bloqueio HTTP por `.htaccess` foi explicitamente pulado; o pipeline publicado verifica isso com Apache e precisa ter seu resultado conferido para o commit implantado. O MySQL 8.0.46 e o volume oficial não foram acessados. Os resultados Docker/MySQL de rodadas anteriores estão em VERIFICACAO.md e não equivalem a uma nova execução da imagem final.

A playlist institucional real não foi fornecida. Sua reprodução depende dos vídeos permitirem incorporação e da saída de internet da TV. Conferir toque/voz nos alto-falantes e um ciclo completo de chamada no equipamento físico após atualização. Preservar o volume e fazer backup antes de redeploy.

Os roteiros ficam em `tests/`. Para MCP, usar `SGA_TEST_URL` local e, se necessário, `SGA_PLAYWRIGHT_EXECUTABLE`. `tests/importacao.mcp.js` requer `SGA_TEST_XLSX` com a planilha fictícia; ela deve conter o registro Pessoa Importação Máscara, processo 0000002-00.2026.4.05.8000, CPF de teste 529.982.247-25, Perito Teste A e data 15/01/2032. Recriar a fixture com `tests/integration.php` antes dos roteiros que consomem ou cancelam a fila.
