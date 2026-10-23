# Verificação das correções — 22/10/2026

Código baseado em `f6629e7`, com correções locais posteriores. Nenhum acesso ou alteração na produção da JFAL. Nenhum commit/push/deploy foi feito automaticamente.

## Resultado dos controles

| Área | Correção entregue |
|---|---|
| SQL e formulários | Parâmetros SQL, validação real de datas, CSRF em POST, erros internos somente nos logs |
| Sessões | Renovação no login, expiração, perfil atual no banco, revogação por alteração/exclusão, cookies seguros configuráveis |
| Credenciais | Sem senha inicial fixa/derivada do CPF para novas contas; troca obrigatória e limite de tentativas |
| Autorização do perito | Vínculo por ID, conferência do paciente e bloqueio de sala/profissional |
| Estados e concorrência | Check-in idempotente, transições atômicas, impedimento de dois ativos e de formulário antigo operar no próximo atendimento |
| TV | Eventos persistidos com cursor, fila de anúncios, retomada de consulta e autorização própria |
| Importação | Validação de conteúdo, preservação de datas distintas, prévia/confirmar, transação, rejeição de conflitos e associação sem aproximação de nome |
| Dados históricos | Cancelamento em vez de apagar atendimento; concluídos preservados; horários de primeira chamada e chegada original preservados |
| Auditoria | Logs críticos na mesma transação, ator/IP/referência, paginação e exportação integral em streaming com proteção de fórmulas |
| Banco e implantação | Migração por CLI, bootstrap explícito, índices, sequenciador inicializado pelo legado, usuário de aplicação sem DDL ou exclusão de auditoria/eventos |
| Arquivos e dependências | Apache bloqueia arquivos privados; CSS local; imagens fixadas por digest e bibliotecas por versões/lockfile |
| Operação | Healthchecks, preflight, backup validado, roteiro de restauração/retorno e workflow de CI |
| Interface | Escape por contexto HTML/JS/URL, labels, foco, teclado e modais; tema muda sem reenviar POST |

## Validação executada

- Build real da imagem Docker com PHP 8.4 e Apache: passou.
- MySQL 8.4 em container e MariaDB 11.4 local, ambos descartáveis: utilizados na validação.
- Migração em banco novo e repetição no mesmo banco: passaram.
- 24 verificações de integração PHP, incluindo reversão de mutação quando a auditoria falha: passaram.
- Duas conexões PHP concorrentes: somente uma chamada criada e segunda tentativa bloqueada.
- 14 testes HTTP contra Apache, incluindo importação pela tela, conta excluída, XSS em filtros, escopo de recepção, arquivos privados, autorização da TV por formulário e CSV com mais de 500 registros: passaram; pacote reconstruído e validado novamente em 23/10.
- Oito testes Python de planilhas: passaram.
- Teste JavaScript da fila de eventos e falha HTTP: passou.
- Conta restrita: DDL e DELETE de eventos/auditoria negados, operação permitida de UPDATE confirmada.
- Compose: configuração validada, banco e web com healthcheck saudável, migração pelo serviço próprio e aplicação com usuário restrito.
- Backup pelo script entregue: gzip/checksum válidos, restore em outro banco e contagens de atendimentos/logs iguais às da origem do teste.
- Preflight na base fictícia, sem alterar dados: passou.
- `npm audit`: zero vulnerabilidades reportadas; `pip-audit -r requirements.txt`: nenhuma vulnerabilidade conhecida reportada na data da execução.
- Lint PHP/JavaScript, compilação CSS e conferência de whitespace no diff: passaram.

O Docker foi iniciado temporariamente no WSL em diretório isolado. Seu daemon estava sem encaminhamento/DNS de containers; o ensaio Compose usou um override temporário com endereço do banco em `extra_hosts`. Esse override não faz parte da configuração entregue. A rede/DNS padrão do Compose deve ser conferida no host oficial. Os testes HTTP foram executados dentro da rede Linux para não depender do encaminhamento localhost entre Windows e WSL.

## Limites e aceite institucional

Toques da TV (23/10): cinco alternativas ao original, prévia sem fala e volume independente. Passaram cinco testes Node (incluindo a fila da TV) e 13 verificações pelo MCP Playwright, cobrindo reprodução das seis opções, persistência, silêncio em 0% e armazenamento bloqueado. A função de voz foi comparada por SHA-256 e permanece idêntica à versão anterior desta alteração. As alternativas terminam antes do início da fala. Roteiros: `tests/toques.test.cjs` e `tests/toques.mcp.js`.

Em 22/10, o navegador integrado não conseguiu iniciar. Em **23/10**, a revisão visual e os fluxos passaram a ser executados pelo **MCP Playwright com Chromium**: 15 verificações de usabilidade, dez do fluxo operacional e 16 verificações de telas em duas larguras. A síntese nativa concluiu o teste com duas vozes `pt-BR` carregadas. Detalhes em [NAVEGADOR.md](NAVEGADOR.md).

Ainda é necessário ouvir o som na TV física e homologar a experiência com os operadores. A revisão não constitui certificação de acessibilidade, auditoria jurídica ou garantia de ausência de vulnerabilidades.

Ainda precisam ocorrer no ambiente da instituição: restore de uma cópia controlada da base real, conferência de vínculos/estados legados com preflight, TLS/proxy, segredos, acesso de TV, backup externo/retensão, janela de mudança e teste pelos operadores. O workflow está publicado; o resultado no GitHub Actions deve ser conferido para o commit implantado.

Consulte [PRODUCAO.md](PRODUCAO.md) para a sequência de implantação, mudanças operacionais e melhorias sugeridas. O relatório `ANALISE_TECNICA_2026-10-22.md` registra os achados anteriores às correções.

## Ajuste para intranet exclusiva (23/10)

Por solicitação do responsável, o painel e suas duas APIs de consulta abrem diretamente, sem sessão de TV, código ou expiração. O login dos operadores e a proteção das operações permanecem. Removida a exigência de TV_TOKEN do Compose e atualizado o roteiro HTTP e MCP. Os registros de autorização acima descrevem a versão anterior.

## Vídeo e playlist institucional

Validação de 12 endereços válidos/inválidos em `tests/youtube.php`. No MCP Playwright, com API YouTube simulada, confirmadas troca de vídeo para playlist e retorno ao vídeo, com repetição habilitada (`tests/playlist.mcp.js`). A reprodução real da playlist institucional depende de seu link e da liberação de incorporação de seus vídeos. Permanecem os 13 testes JavaScript de fila, toques e restauração de áudio passando. Sem alteração do esquema do banco.

## Encerramento da revisão de controles e campos — 23/10/2026

Ver [REVISAO_FINAL_2026-10-23.md](REVISAO_FINAL_2026-10-23.md): controles por clique e teclado, menus, rotas por perfil, ações em lote, máscaras e compatibilidade de importação. Esta revisão final é local; não representa teste no servidor oficial.
