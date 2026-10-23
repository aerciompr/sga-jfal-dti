# SGA — Justiça Federal em Alagoas

Sistema de gerenciamento de pautas, presença, filas e atendimentos de perícias. Esta documentação corresponde à revisão de segurança de outubro de 2026 e substitui a descrição anterior.

## Arquitetura

PHP 8.4 com PDO/MySQL, Apache, MySQL 8.4 e importação Excel em Python. CSS Tailwind compilado localmente, JavaScript para TV e temas. A implantação é feita por Docker Compose. Consulte [PRODUCAO.md](PRODUCAO.md) para migração, variáveis, backup, retorno e homologação.

- `banco.php`: conexão de runtime e utilitários; não executa migrações automáticas.
- `seguranca.php`: sessões, revogação, expiração, CSRF, cabeçalhos, datas e autorização de TV.
- `atendimento_service.php`: transições atômicas, bloqueios de profissional/sala e eventos de chamada.
- `pauta_service.php`: agendamento, edição, homologação, remanejamento e cancelamento com auditoria.
- `usuarios_service.php`: gestão de usuários, proteção do último administrador e revogação de sessões.
- `importacao_service.php` e `import_excel.py`: validação de planilha, prévia, confirmação e gravação transacional.
- `scripts/migrate.php`: criação/atualização aditiva do schema exclusivamente por CLI.
- `scripts/preflight.php`: diagnóstico sem alteração de dados.
- `scripts/backup.sh`: dump lógico com compactação e checksum.

## Perfis

| Perfil | Acesso |
|---|---|
| Administrador | Gestão completa de usuários, pautas, salas, vídeo, atendimento e auditoria |
| Supervisor | Pautas, importação, homologação, salas, recepção e auditoria |
| Recepção | Consulta e check-in da pauta homologada do próprio dia |
| Perito | Fila e ações dos atendimentos vinculados ao próprio ID |
| TV autorizada | Eventos de chamada e histórico visual, sem alterações de atendimento |

Alterações de credencial ou cadastro revogam sessões anteriores. Administradores atribuem credenciais temporárias individuais; a importação não usa senha derivada do CPF. Não há administrador com senha fixa criado automaticamente.

## Estados de atendimento

`agendado → presente → chamada → atendimento → concluido`

- `chamada → presente`: ausência antes do início, mantendo eventos e retornando ao fim da fila.
- `presente → agendado`: desfazer check-in administrativo somente se não houve chamada.
- `agendado → cancelado`: cancelamento administrativo, preservando registro e histórico.
- Check-in repetido não altera senha/horários.
- Atendimentos iniciados/concluídos são preservados por remanejamentos e cancelamentos comuns.
- Sala e profissional só podem ter um ativo por vez.

## Dados

Tabelas operacionais: `atendimentos`, `usuarios`, `salas`, `salas_peritos`, `pautas_liberadas`, `sequencia_diaria_senhas`, `configuracoes`. Apoio e controles: `eventos_chamada`, `logs_auditoria`, `locks_peritos`, `login_tentativas`, `schema_migrations`, `nomes_validados` (legado, sem correção automática de identidades).

`atendimentos.perito_id` vincula a pauta ao profissional. `primeira_chamada_em` preserva o marco para cálculo de espera; `chamado_em` mantém a última chamada. `chegada_em` preserva a chegada original; `fila_em` organiza a posição atual, inclusive depois de retorno por ausência. Eventos possuem ID monotônico independente do timestamp e do estado posterior do atendimento.

A TV consome eventos após seu cursor, anuncia um por vez e persiste o cursor ao terminar a exibição. Consulta a cada dois segundos após processar o lote. Conexão interrompida é sinalizada na tela. A atualização não depende de comparar timestamps.

## Importação

Usa apenas `.xlsx`, até 5 MB, 10.000 linhas, 100 colunas e 50 MB descompactados. Exige cabeçalhos identificáveis de processo, documento da parte, nome do perito e data da perícia. Nome da parte e CPF do perito são opcionais. Fórmulas, datas impossíveis e CPFs inválidos são rejeitados com indicação da linha. CPF numérico que perdeu zero à esquerda precisa ser corrigido na origem.

Agrupamento considera processo, data e profissional, permitindo juntar linhas complementares da mesma perícia. Dados conflitantes interrompem a importação. A prévia expira em 30 minutos; confirmação é necessária antes de gravar. Registros em andamento/concluídos são preservados. A importação não transforma nomes por aproximação nem envia nomes a serviços externos.

## Auditoria e interface

Mutação crítica e log são gravados na mesma transação. Logs incluem ator, ação, referência de requisição, IP e dados de contexto; senhas e CSRF não devem ser registrados. Relatórios têm paginação de 100 registros e exportam o filtro inteiro em streaming com proteção contra fórmulas CSV. Indicadores consideram o filtro completo, usando primeira chamada para espera.

Tema claro/escuro muda sem recarregar ou repetir POST. CSS é local; YouTube depende de rede externa. Há melhorias de labels, foco, teclado e modais, que ainda precisam de homologação visual e assistiva no ambiente real.

## Desenvolvimento e testes

`npm ci && npm run build:css` compila os estilos. `node --test tests/tv.test.cjs` valida a fila de anúncios. Os testes Python estão em `tests/`. Integração PHP e concorrência exigem exclusivamente `DB_NAME=sga_test` e `SGA_TEST=1`, pois limpam a base descartável.

O workflow em `.github/workflows/validate.yml` constrói a imagem e valida MySQL, Apache, migrações, controles HTTP e os testes. O código legado `import_pdf.py` e `test_layout.py` não faz parte do fluxo suportado de importação nem da imagem de produção.

## Usabilidade e navegador

Consulte [NAVEGADOR.md](NAVEGADOR.md). A revisão de 23/10 foi executada pelo MCP Playwright: login, busca, teclado/modais, navegação, telas móveis, fluxo recepção/perito e preparação da TV. A equipe usa somente Chrome/Edge atualizado, sem instalar Playwright. A interface fornece ajuda, mostrar senha, confirmação com senha gerada, saída em um clique e teste de áudio/tela cheia.
