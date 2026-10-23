# Análise técnica do SGA — JFAL

> Registro histórico da auditoria do código original. As correções posteriores e a implantação estão descritas em PRODUCAO.md e VERIFICACAO.md.

Data: 22/10/2026. Código examinado: commit `f6629e7`, branch `master`.

## 1. Parecer executivo

O SGA tem um fluxo de negócio claro: importar e validar pautas, confirmar presença, organizar filas por perito, chamar pessoas nas salas e acompanhar o atendimento. A implementação contempla necessidades concretas, como homologação de pauta, supervisão, busca em outras datas, importação com pré-cadastro de peritos e histórico operacional.

A arquitetura PHP estruturado + PDO + MySQL é suficiente para esse tipo de sistema. Não há justificativa técnica, a partir desta análise, para uma reescrita completa. O investimento prioritário é corrigir segurança, integridade das transições, entrega das chamadas e operação do banco.

Há defeitos de alta prioridade demonstráveis por leitura do código e testes isolados. Isso não prova comprometimento, perda de dados ou exploração na produção. Também não permite afirmar que o servidor utiliza exatamente os arquivos Docker e configurações deste repositório.

## 2. Escopo e limites

- Revisão dos controladores PHP, consultas SQL, autenticação, permissões, importadores Python, JavaScript da TV e temas, documentação e arquivos Docker.
- Testes Python locais com dados inteiramente fictícios e arquivos temporários.
- Nenhum acesso ao servidor, banco, contas ou dados reais da JFAL; nenhuma ação sobre a produção.
- PHP e Docker não estão disponíveis no PATH deste ambiente. Não foram executados lint PHP, build Docker, integração MySQL, testes HTTP ou inspeção visual em navegador.
- Não foram verificados TLS, proxy, firewall, configuração PHP efetiva, backups, restore, versões efetivamente instaladas ou monitoramento do servidor.
- Referências `arquivo:linha` indicam o código deste commit. Não equivalem a exploração dinâmica validada.

## 3. Arquitetura e dados

Fluxo principal: login → dashboard → importação/homologação → recepção → fila do perito → TV → início/conclusão → auditoria.

Os perfis efetivos são `admin`, `supervisor`, `recepcao` e `perito`. A documentação descreve apenas três, estando desatualizada também quanto aos estados e tabelas.

O banco define dez tabelas: `atendimentos`, `usuarios`, `salas`, `salas_peritos`, `nomes_validados`, `logs_auditoria`, `pautas_liberadas`, `sequencia_diaria_senhas` e `configuracoes`.

Estados predominantes no código: `agendado`, `presente`, `chamada`, `atendimento`, `concluido`. Há compatibilidade pontual com `fila`. A documentação cita outros estados, como `chamado` e `finalizado`, que não correspondem ao fluxo principal implementado.

As páginas concentram autenticação, autorização, regras de negócio, SQL, HTML e JavaScript. `banco.php` concentra conexão, criação de schema, migrações, correções de dados, associação de nomes, consultas externas e utilitários. Essa concentração torna alterações pequenas mais difíceis de isolar.

## 4. Pontos positivos comprovados

- PDO com exceções, `utf8mb4` e emulação de prepared statements desativada.
- Uso amplo de consultas parametrizadas e conversão de IDs para inteiros em operações em lote.
- Senhas armazenadas por `password_hash` e verificadas por `password_verify`.
- Barreiras de perfil no servidor nos principais módulos; recepção valida data e homologação antes do check-in.
- Sequenciador diário usa incremento atômico com `LAST_INSERT_ID`; é uma base melhor que calcular a próxima senha por contagem de linhas.
- Importação possui transação, limite de arquivo e argumentos de subprocesso protegidos com `escapeshellarg`.
- Uso de `textContent` em partes importantes da TV e dos modais.
- Tempos de chegada, chamada, início e conclusão permitem construir indicadores úteis.
- Configuração do vídeo já migrou para banco, reduzindo dependência de escrita em arquivo.

Esses mecanismos são valiosos, mas sua cobertura é irregular nos pontos descritos abaixo.

## 5. Achados prioritários

### A01 — SQL injection no remanejamento selecionado — Alta

Evidência: `gerenciar_pauta.php:137` recebe `nova_data` do POST; em `:149`, interpola esse conteúdo diretamente no `UPDATE` executado por `PDO::exec`. A lista de IDs é convertida para inteiros, mas a data não é parametrizada nem validada.

Pré-condição: sessão de administrador ou supervisor. Um formulário HTML com `type=date` não restringe requisições construídas diretamente. O desvio pode alterar a semântica da consulta e ampliar os registros afetados. Não é necessário que múltiplas instruções SQL estejam habilitadas para haver risco.

Correção: parametrizar a data, validar formato e existência no calendário, parametrizar os IDs e conferir quantidade/estados afetados. Prioridade imediata.

### A02 — Ações do perito autorizadas somente pela sala — Alta

Evidência: `atendente.php:208`, `:228`, `:248`, `:269` e `:312` consultam o atendimento pela sala e pelo dia, sem restringir ao perito autenticado. A sala pode ser escolhida entre todas as cadastradas (`:127`).

Impacto: um perito que selecione a sala usada por outro pode visualizar, rechamar, iniciar, concluir ou devolver à fila o atendimento do colega. O vínculo automático do nome na fila não protege essas ações.

Correção: autorizar cada atendimento por identificador e perito responsável; definir ocupação exclusiva de sala ou regra explícita para compartilhamento. O administrador pode ter exceção deliberada e auditada.

### A03 — Check-in permite regredir atendimento concluído — Alta

Evidência: `recepcao.php:377` e `:509`; os updates em `:394` e `:544` usam o ID sem exigir `status = 'agendado'`.

Impacto: repetição do POST ou chamada direta sobre registro já chamado, em exame ou concluído gera outra senha e retorna o estado a `presente`. Horários de início/fim podem permanecer, produzindo combinação inconsistente. A validação de dia e homologação da recepção não impede essa regressão.

Correção: transição atômica condicionada ao estado anterior, sem regenerar senha em requisição repetida; verificar `rowCount`, registrar o resultado e manter geração de senha e check-in na mesma transação.

### A04 — Entrega das chamadas da TV pode perder eventos — Alta operacional

Evidência: `api_senhas.php:19` retorna somente a última chamada ativa. `assets/app.js:183` compara apenas o timestamp; `:253` consulta a cada três segundos.

Consequências dedutíveis: duas chamadas entre consultas deixam apenas a mais recente disponível para anúncio; chamadas diferentes no mesmo segundo podem ser confundidas; uma chamada que passe a `atendimento` antes da consulta desaparece do endpoint. Ao sair do estado `chamada`, o sistema pode voltar a apresentar uma chamada antiga ainda ativa como se fosse nova.

Correção: persistir eventos de chamada com ID monotônico, consumir todos após o cursor de cada TV e enfileirar anúncios. Trocar polling por WebSocket, sozinho, não resolve a ausência de histórico de eventos.

### A05 — Banco executa migrações e reescreve dados a cada requisição — Alta operacional

Evidência: `banco.php:34` em diante executa DDL, tentativas de `ALTER TABLE`, correção de datas legadas (`:190`) e normalização histórica de peritos (`:195`, `:401`, `:438`). As páginas e APIs incluem esse arquivo antes de atender a requisição; muitas fazem isso antes da autorização.

Impacto: inclusive o polling público da TV provoca tentativas de alteração de schema e varreduras de dados. Aumentam latência, disputa por metadados e necessidade de privilégios excessivos. Normalização de nome pode mudar históricos sem uma operação de manutenção explícita ou log correspondente.

Correção: migrações versionadas e manutenção executadas no deploy, com credencial própria. A conexão de runtime deve fazer apenas o trabalho necessário e ter privilégios mínimos. Não estimar ganho percentual sem medições reais.

### A06 — XSS armazenado em busca e em atributos JavaScript — Alta

Evidência: `recepcao.php:1223` insere `item.nome` e `item.perito` do banco em `innerHTML` sem escape. Em `:901` e `:992`, `htmlspecialchars` é usado dentro de strings JavaScript em `onclick`; a entidade HTML de uma aspa é decodificada pelo navegador antes de o JavaScript ser interpretado.

Impacto: conteúdo malicioso armazenado por edição/importação pode executar no navegador de quem consulta ou clica, conforme o caminho e os tratamentos aplicados ao campo. A origem é autenticada, não uma entrada anônima demonstrada. Não foi realizada prova de execução em navegador.

Correção: construir os nós com `textContent`; remover eventos inline e usar listeners com dados serializados de forma apropriada. Escapar HTML não substitui escape de contexto JavaScript.

### A07 — Operações mutáveis sem token CSRF — Alta, condicionada ao ambiente

Evidência: formulários e handlers de usuários, pautas, salas, senha e atendimento não possuem geração/validação de token CSRF. Não há validação central de origem.

Impacto: o risco efetivo de requisições forjadas depende de cookies, SameSite, origem e navegador. A ausência do controle no código é confirmada; exploração externa não foi testada.

Correção: token vinculado à sessão em todas as mutações, validação de origem como camada adicional e cookies de sessão adequados. Logout também deve ser uma ação POST protegida.

### A08 — Sessões não revogadas após exclusão, mudança de perfil ou senha — Alta

Evidência: `index.php:26` grava identidade/perfil na sessão. Páginas autorizam com esses valores. As consultas adicionais de usuário não invalidam a sessão quando `fetch()` retorna falso nem atualizam sistematicamente o perfil. `usuarios.php:115` só atualiza a sessão quando a própria conta é editada.

Impacto: usuário excluído ou rebaixado pode manter acesso com sessão já aberta. Redefinição de senha não encerra outras sessões.

Correção: conferir usuário ativo e perfil atual em middleware; usar versão de sessão/credencial ou repositório de sessões para revogação. Implementar expiração por inatividade e absoluta.

### A09 — Autenticação e credenciais iniciais frágeis — Alta

Evidência: `index.php` não regenera o ID de sessão após login nem limita tentativas no aplicativo. Importações em `recepcao.php:292` e `gerenciar_pauta.php:472` criam senha previsível derivada dos seis primeiros dígitos do CPF. `banco.php:60` contém tentativa de criar administrador com senha fixa conhecida.

A troca obrigatória existe para peritos com senha derivada do CPF, mas não substitui um processo seguro de ativação. A página de troca aceita seis caracteres e não solicita a senha anterior; convém separar ativação de troca normal. Não foram inspecionados controles de tentativas no proxy.

Correção: ativação individual com segredo aleatório e validade, flag explícita de troca, regeneração de sessão, política de senha e limitação de tentativas. Configurar cookies Secure/HttpOnly/SameSite e modo estrito. Referência: https://www.php.net/manual/en/session.security.ini.php.

### A10 — Instalação inicial do administrador falha na primeira execução — Média

Evidência: `banco.php:46` cria `usuarios` sem coluna `nome`; `:61` tenta inserir administrador incluindo `nome`; a coluna só é criada em `:120`. O erro é engolido.

Impacto esperado em banco novo: primeira requisição não cria o administrador; uma requisição posterior pode criá-lo com senha padrão. É um defeito de ordem de inicialização, não evidência de indisponibilidade atual.

Correção: schema completo e bootstrap explícito com credencial segura, fora do tráfego HTTP. O fallback para `install.php` também aponta para arquivo inexistente no repositório.

### A11 — Importador elimina agendamentos distintos do mesmo processo — Alta para integridade

Evidência: `import_excel.py:120` agrupa por processo; data e perito não fazem parte da chave. Teste local confirmou duas linhas do mesmo processo em datas diferentes resultando em um único registro.

É legítimo juntar linhas de partes do mesmo agendamento; não é seguro presumir que todas as ocorrências de um processo representam a mesma perícia.

Correção: definir identidade do agendamento com a área usuária e agrupar pela chave adequada, preservando datas/peritos distintos e apresentando conflitos antes de gravar.

### A12 — Validação da planilha e documentos incompleta — Média/alta

Evidência: índices de fallback e acesso direto às células no importador; `clean_cpf` preserva textos inválidos e não verifica dígitos; datas são remontadas por regex sem validação de calendário. `load_workbook` não usa `data_only=True`.

Testes confirmaram: planilha curta retorna erro genérico `tuple index out of range`; `31/02/2026` vira `2026-02-31`; fórmula em campo de nome permanece `=1+1`; CPF numérico cujo zero inicial foi perdido permanece com dez dígitos.

Correção: schema de cabeçalho obrigatório, erro por linha/campo, validação real de datas, CPF e processo conforme regra definida, política para fórmulas, limites de linhas e tamanho descompactado, prévia e confirmação dos conflitos. Não completar documentos ambíguos silenciosamente.

### A13 — Concorrência nas transições e múltiplos ativos por sala — Alta operacional

Evidência: `atendente.php:158` faz SELECT seguido de UPDATE por ID, sem transação/bloqueio ou condição de estado no UPDATE. Chamar próximo não verifica se já existe atendimento ativo. A UI pode apresentar apenas o último enquanto outros ficam ativos.

Correção: transições condicionais atômicas, bloqueio conforme necessidade, desempate por ID na ordem FIFO, verificação de ocupação e controle de duplo envio. Duas sessões do mesmo perito podem disputar o mesmo registro.

O sequenciador diário tem caminho principal atômico, mas seu fallback volta a calcular pelo último registro (`recepcao.php:80`), sem garantia concorrente. Falha de infraestrutura deve produzir erro controlado e repetível, não enfraquecer a unicidade.

### A14 — Remanejamentos podem alterar históricos e criar inconsistências — Média/alta

Evidência: `gerenciar_pauta.php:149`, `:163`, `:181`, `:219`, `:234` operam sem restringir estado. A transferência mensal preserva o dia por concatenação, podendo criar datas inválidas ao mover um dia 31 para mês menor. Retornos a agendado (`:314`; `recepcao.php:423`) não limpam todos os horários/contadores.

Correção: definir estados elegíveis, validar calendário e colisões, manter trilha de antes/depois e separar correção histórica da operação diária. Não alterar atendimento concluído por remanejamento comum.

### A15 — Identidade do perito depende de nome e associação aproximada — Média/alta

Evidência: `banco.php:646` associa usuários por nome parcial/Levenshtein; a condição aceita até uma única palavra significativa coincidente em nomes curtos. `atendente.php:50` também adapta identidade por nome parcial. Relações usam textos, sem `perito_id`.

Impacto: associação ao primeiro nome compatível pode ligar uma pauta ao profissional errado; alteração de grafia pode separar ou mesclar filas e históricos.

Correção: chave estável de usuário/perito, CPF validado quando apropriado e revisão humana de correspondências aproximadas. Preservar nome original importado e sugestão de correção separadamente. A heurística de nomes também pode juntar conectores legítimos em nomes não cobertos pela lista.

### A16 — Auditoria incompleta e sem garantia de persistência — Média/alta

Evidência: `banco.php:792` captura falhas de log silenciosamente. CRUD de usuários/salas e edição de dados em `recepcao.php:608` não têm cobertura uniforme. Há logs antes de operações sem transação conjunta; exclusões são físicas, inclusive por dia/mês. O export de logs usa a lista limitada a 500 registros em `auditoria.php:123`.

Impacto: ação bem-sucedida pode ficar sem trilha; tentativa pode parecer concluída; CSV pode parecer completo e estar truncado. IP, correlação de requisição e valores antes/depois não são registrados de forma estruturada.

Correção: trilha de eventos com ator, alvo, antes/depois e resultado; mutação e registro críticos na mesma transação, logs técnicos externos e export completo ou explicitamente limitado. Definir política de cancelamento/exclusão/retensão com os responsáveis.

### A17 — Indicadores de espera perdem a primeira chamada — Média

Evidência: rechamada sobrescreve `chamado_em` em `atendente.php:214`; auditoria calcula espera por esse campo. Ausência zera contador e horários.

Impacto: a espera medida passa a ser até a última chamada; tentativas anteriores deixam de existir no registro principal. O sistema não distingue claramente espera inicial, rechamadas e retorno à fila.

Correção: preservar primeira chamada, última chamada e eventos separados; documentar o significado de cada indicador.

### A18 — Docker e configuração não garantem ambiente reproduzível — Alta operacional

Evidência: Dockerfile instala somente `pdo_mysql`, mas o código exige funções `mb_*` e usa cURL. `mbstring` precisa de habilitação explícita; a disponibilidade exata da imagem não foi testada. Referência: https://www.php.net/manual/en/mbstring.installation.php.

Compose usa aplicação com root do MySQL, senha fixa no repositório, porta do banco publicada, rede externa preexistente e `depends_on` sem healthcheck. Tags de imagens e dependências pip não estão fixadas por digest/versão. Não há procedimento de restore ou backup versionado no repositório.

Correção: verificar e instalar extensões necessárias, fixar dependências, usuário de aplicação com privilégios mínimos, segredos externos, banco sem publicação desnecessária, readiness/retry, healthchecks, backup e restauração testada. A senha do Compose só deve ser tratada como comprometimento de produção se for comprovado que foi reutilizada ali.

### A19 — Superfície pública e dependências externas — Média, exposição a confirmar

Evidência: painel e APIs de chamadas não exigem autenticação; devolvem nomes, salas e peritos. Docker copia o projeto inteiro para o document root e dá posse ao usuário web. Scripts Python, documentação e YAML não têm regra explícita de bloqueio neste projeto. API de busca em outras datas exige login, mas não delimita resultados por perfil/perito. `checkNomeIBGE` desabilita validação TLS em `banco.php:592`.

Impacto depende de proxy/ACL: acesso público aos nomes de pessoas associadas às perícias e download de arquivos de apoio. Não há evidência de exposição do código PHP em texto, nem de acesso à internet pública. Tailwind por CDN, fontes e YouTube tornam partes da experiência dependentes de serviços externos.

Correção: definir acesso próprio de TV ou ACL, minimizar payload, separar arquivos públicos/privados e tornar código somente leitura; ativar validação de certificado; servir CSS e fontes localmente quando necessário. Avaliação de privacidade deve considerar o contexto real e os responsáveis da instituição; esta análise não é parecer jurídico.

### A20 — Exportação CSV e mensagens de erro — Média

Evidência: `auditoria.php:157` exporta valores sem neutralização de fórmulas. Teste do parser confirmou preservação de conteúdo iniciado por `=`. `banco.php:446` e APIs exibem mensagens internas de exceções.

Impacto: programas de planilha podem interpretar células como fórmulas, conforme conteúdo e configurações. Erros podem revelar schema, caminhos e detalhes internos.

Correção: neutralizar entradas perigosas na exportação mantendo uma política documentada; retornar erro genérico ao cliente e registrar detalhes no servidor com identificador de correlação.

## 6. Desempenho e capacidade

O maior problema estrutural é trabalho de manutenção em `banco.php` por requisição. Antes de trocar a tecnologia, medir e retirar esse trabalho do caminho crítico.

O schema versionado não cria índices de consulta em `atendimentos` além da chave primária. Consultas por dia, perito, status, sala e horário tendem a aumentar de custo com o histórico. Índices adicionais podem existir no servidor real; é necessário consultar `SHOW INDEX` e executar `EXPLAIN` em uma cópia antes de decidir.

Candidatos a avaliar: `(data_pauta, perito, status, chegada_em, id)` para fila, `(data_pauta, sala, status, chamado_em)` para atendimentos por sala e índices alinhados ao modelo futuro de eventos de chamada. Não criar todos indiscriminadamente: há custo de escrita e limites associados a campos textuais.

Filtros `DATE(coluna)` e `DATE_FORMAT(coluna)` dificultam o aproveitamento de índices simples; preferir intervalos. Auditoria usa `fetchAll` e carrega o conjunto completo de atendimentos filtrados. Importação carrega histórico de processos e peritos inteiros. Priorizar paginação, exportação por lotes e consultas delimitadas ao conjunto importado.

O polling atual representa aproximadamente 20 requisições/minuto por TV, fora atualizações do rodapé. Polling pode ser adequado para esta escala; o problema principal é o custo de cada requisição e a entrega sem perda, não o nome da tecnologia.

## 7. Interface, acessibilidade e manutenção

O sistema tem identidade institucional, navegação por perfil, filtros e modais que atendem o trabalho diário. Há, porém, texto de 8–11px em várias telas, labels sem associação explícita a inputs e ações em linhas clicáveis que precisam de alternativa por teclado. Modais devem ter foco controlado, retorno do foco, Escape e semântica acessível. Contraste, zoom e uso em TV precisam de inspeção visual; não foram certificados nesta análise.

A TV só registra falhas de polling no console. Recomenda-se indicador de conexão e idade da última atualização, tentativa de recuperação e teste de voz no navegador real. `playChime` cria um AudioContext por execução sem fechamento explícito; reutilizar/liberar o recurso em uma TV que permanece dias aberta.

O tema depende de sobrescritas de classes Tailwind e localização de elementos por classes de layout, o que torna mudanças visuais sensíveis à estrutura HTML. O tema recarrega a página (`tema.js:307`); em páginas pós-POST, adotar Post/Redirect/Get evita repetição acidental de ações.

Há regras duplicadas de autenticação, troca de senha, importação e geração de senha. Extrair incrementalmente bootstrap, autorização, serviços de atendimento/importação, repositórios e templates comuns. Não é necessário adotar framework para corrigir os achados prioritários.

Não há suíte automatizada de regressão, pipeline CI ou manifesto com versões de dependências Python. `test_layout.py` é um script manual de extração de PDF com caminho absoluto da máquina do autor, não teste do sistema. Documentação e comentários estão defasados: exemplo, o cabeçalho de `atendente.php` fala em acesso sem login, embora o código exija autenticação.

## 8. Verificações executadas

| Verificação | Resultado |
|---|---|
| Sintaxe dos três scripts Python via AST | Aprovada |
| Mesmo processo em duas datas, planilha fictícia | Duas entradas se tornam uma: perda de distinção confirmada |
| Planilha com apenas Processo e Documento | Erro genérico de índice confirmado |
| Data textual 31/02/2026 | Retorna 2026-02-31, sem rejeição |
| Nome com fórmula =1+1 | Fórmula preservada como texto pelo parser |
| CPF numérico de dez dígitos | Permanece inválido, sem rejeição |
| Sintaxe PHP / build Docker / integração MySQL | Não executados: ferramentas indisponíveis no PATH |
| Exploração HTTP / concorrência / execução XSS em navegador | Não executadas; achados respectivos baseados em código |

Os testes não usaram nomes, CPFs ou processos reais. Nenhum pacote foi instalado. A importação local do módulo gerou cache Python ignorado pelo Git.

## 9. Sequência recomendada de trabalho

**Primeiro ciclo — conter os riscos mais próximos:** corrigir A01, A02, A03 e A06; proteger mutações com CSRF; revogar sessões de contas alteradas; confirmar configuração de cookies, contas e acesso de TV; validar backup restaurável antes de mudanças de schema.

**Segundo ciclo — preservar atendimento e chamadas:** máquina de estados, atualizações atômicas, unicidade/ocupação de sala, eventos de chamada com cursor e fila de voz; corrigir identidade do agendamento importado e adicionar prévia de conflitos.

**Terceiro ciclo — operação sustentável:** retirar migrações e normalização automática das requisições, corrigir bootstrap, usar usuário restrito no banco, verificar extensões da imagem, fixar dependências, medir consultas, criar índices e paginação conforme evidência.

**Quarto ciclo — manutenção e qualidade:** centralizar regras, completar auditoria, corrigir indicadores, acessibilidade, documentação e integração contínua.

Para cada ciclo: homologar em banco separado e com dados fictícios ou anonimizados, testar com recepção/peritos/supervisão, gerar backup com procedimento de restore, preparar rollback de código e schema e publicar em janela acordada. Não há estimativa confiável de prazo sem conhecer infraestrutura, volume, equipe e disponibilidade para homologação.

## 10. Critérios mínimos de aceite

1. Perito A não altera nem consulta atendimento exclusivo do perito B selecionando outra sala.
2. POST repetido de check-in preserva senha, estado e horários; concluído nunca retorna à fila pelo check-in.
3. Dois navegadores concorrentes não chamam o mesmo paciente nem deixam dois ativos indevidos numa sala.
4. Duas chamadas no mesmo segundo e várias entre polls são todas anunciadas uma vez, na ordem prevista.
5. Mesmo processo em datas diferentes sobrevive à importação; erro de planilha não provoca gravação parcial silenciosa.
6. Dados com aspas/HTML são exibidos literalmente; valores de data não alteram a consulta SQL.
7. Mutação sem CSRF falha; conta excluída/rebaixada perde os privilégios na sessão já aberta.
8. Instalação limpa cria schema e administrador de forma segura e determinística.
9. Requisições de login/TV não executam DDL nem correção histórica de dados.
10. Backup restaurado em ambiente separado reproduz o conjunto esperado; logs e exportações têm escopo explicitado.

## 11. Informações necessárias para complementar a auditoria de produção

Topologia e acesso ao sistema (intranet/VPN/internet), versão efetiva do PHP/MySQL e extensões, configuração do proxy e cookies, schema e índices reais, volumes de atendimentos e acessos simultâneos, política e evidência de restore, logs técnicos, inventário de usuários/sessões e procedimento de deploy. O levantamento deve ser feito por acesso controlado, sem publicar credenciais ou dados pessoais no relatório.

O parecer sobre o repositório é suficiente para iniciar correções locais. A validação do ambiente é necessária para concluir sobre exposição real, capacidade, recuperação e controles externos.

