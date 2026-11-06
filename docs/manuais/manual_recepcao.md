# Manual do Usuário — Módulo de Recepção e Triagem
### Sistema de Gerenciamento de Atendimento Pericial (SGA)
**Justiça Federal em Alagoas — Seção Judiciária de Alagoas**

---

## 1. Visão Geral e Finalidade

O módulo de **Recepção** do SGA é a porta de entrada dos cidadãos que comparecem ao Fórum da Justiça Federal para realização de perícias médicas judiciais. 

Suas principais funções operacionais incluem:
1. **Identificação e Check-in:** Localizar o processo e confirmar a presença física do periciado.
2. **Emissão de Senha:** Geração automática da senha de atendimento vinculada ao médico perito (`PE-01`, `PE-02`, etc.).
3. **Classificação de Prioridade Legal:** Identificar e registrar formalmente casos de prioridade (60+ anos, PCD, gestantes) e superprioridade (80+ anos).
4. **Orientação ao Jurisdicionado:** Informar com precisão a quantidade de pessoas que aguardam na frente na fila de cada consultório.
5. **Monitoramento em Tempo Real:** Acompanhar o status de cada sala médica (sala livre, perito chamando ou em exame).
6. **Emissão de Declaração de Comparecimento:** Emitir documento oficial em folha A4 para comprovação perante empregadores ou órgãos públicos.

---

## 2. Acesso ao Sistema e Autenticação

Para acessar o módulo de recepção:
1. Abra o navegador web (Google Chrome, Microsoft Edge ou Mozilla Firefox) no computador da recepção.
2. Acesse o endereço institucional do SGA: `http://atendimento.jfal.jus.br`.
3. Na tela de autenticação, insira seu usuário e senha institucionais.
4. Selecione o perfil **Recepção** (ou perfil atribuído pela DTI/Supervisão).

![Tela de Login do SGA](imagens/01_login.png)

> [!TIP]
> Caso esqueça sua senha ou necessite de cadastramento de novos recepcionistas, solicite abertura de chamado junto à DTI ou ao Supervisor do SGA.

---

## 3. Navegação e Painel Principal da Recepção

Após o login, o operador é direcionado à tela principal da Recepção:

![Visão Geral da Recepção](imagens/02_recepcao_visao_geral.png)

### 3.1. Barra de Ferramentas Superior
- **Logomarca da JFAL:** Identificação da seção judiciária.
- **📺 Painel TV:** Abre a tela do painel de chamadas da sala de espera em uma nova aba (ideal para projetar na TV da recepção).
- **📊 Dashboard:** Atalho para visualização das estatísticas gerais do dia.
- **Identificação do Usuário:** Exibe o nome do operador logado e o botão **Sair** (encerra a sessão com segurança).

### 3.2. Filtro de Data e Busca Rápida
- **Visualizando Pauta de:** Permite selecionar o dia da pauta. Por padrão, exibe a data corrente (`Hoje`). Pautas já liberadas para atendimento recebem a etiqueta verde `Pauta Homologada`.
- **Campo de Busca:** Digite qualquer trecho do **Nome do Periciado**, **Número do Processo Judicial** ou **CPF** para filtrar a listagem instantaneamente sem recarregar a página.

### 3.3. Cards de Salas e Filas dos Médicos Peritos
Na parte superior da tela, os cards apresentam o panorama ao vivo de cada consultório ativo:
- **Identificação do Médico e Sala:** Nome do perito e sala vinculada (ex: `Dr. Carlos Eduardo Silveira — Sala 02`).
- **Critério de Fila Ativo:**
  - `🏃 Fila: Ordem de Chegada`: O perito atende prioritariamente quem chegou primeiro ao fórum.
  - `🕒 Fila: Horário PJe`: O perito optou por seguir o horário oficial agendado na pauta judicial.
- **Status da Prioridade no Consultório:**
  - `♿ Fila com Prioridade Ativa`: O médico ativou o atendimento preferencial; pacientes prioritários e superprioritários vão ao topo da fila dele.
  - `⚪ Prioridade Opcional`: O médico está atendendo na ordem cronológica direta.
- **Contador de Espera:** Quantidade total de pessoas aguardando aquele médico e quantos são prioritários (ex: `Fila: 3 | ⭐ 1 prioritário`).
- **Estado Atual da Sala:**
  - `🟢 Em Exame: PE-01 (José Oliveira Santos)`: O perito está realizando o exame pericial.
  - `🔔 Chamando: PE-02 (Ana Lúcia dos Santos)`: O perito acionou o chamado na TV e aguarda a entrada do paciente.
  - `⚪ Sala livre`: Consultório desocupado aguardando o médico acionar o próximo da fila.

---

## 4. Procedimento de Check-in (Confirmação de Presença)

Ao chegar ao balcão, o cidadão deve apresentar seu documento oficial de identificação com foto e/ou número do processo.

![Confirmar Presença do Periciado](imagens/03_confirmar_presenca.png)

### Passo a Passo:
1. **Localizar o Periciado:**
   - Localize o nome do periciado na seção **Aguardando Chegada (Pauta do Dia)**.
   - Dica: Utilize a barra de busca rápida digitando os primeiros dígitos do CPF ou o primeiro nome.
2. **Confirmar Dados:**
   - Confira o nome do periciado, o número do processo e o nome do médico perito responsável.
3. **Acionar a Confirmação:**
   - Clique no botão verde **✓ Confirmar Chegada**.
4. **Emissão de Senha:**
   - O sistema gera a senha sequencial correspondente (ex: `PE-03`) e registra a hora exata da chegada.
   - O paciente passa imediatamente para a tabela **Aguardando Chamada (Fila de Espera Ativa)**.
5. **Orientação ao Cidadão:**
   - Informe a senha ao cidadão: *"Sua senha é PE-03 com o Dr. Carlos na Sala 02. Por favor, aguarde na sala de espera que seu nome e senha serão chamados na TV com sinal sonoro."*

---

## 5. Gestão de Prioridade Legal e Superprioridade

O SGA cumpre rigorosamente as normas federais de acessibilidade e prioridade de atendimento (Lei nº 10.048/2000, Lei nº 10.741/2003 - Estatuto da Pessoa Idosa, e Lei nº 13.466/2017).

![Modal de Classificação de Prioridade Legal](imagens/04_modal_prioridade_legal.png)

### Categorias de Prioridade:
1. **Normal (Padrão):**
   - Atendimento regular seguindo a ordem cronológica de chegada ou horário marcado.
2. **Prioridade Legal (60+ Anos, PCD, Gestantes, Lactantes, Autistas):**
   - Destinada a pessoas idosas a partir de 60 anos, pessoas com deficiência, gestantes, lactantes e pessoas com mobilidade reduzida ou Transtorno do Espectro Autista.
   - Sinalizada visualmente pelo selo amarelo `PRIORIDADE`.
3. **Superprioridade (80+ Anos):**
   - Garantida por lei para pessoas com 80 anos ou mais, tendo preferência especial até mesmo sobre as demais prioridades.
   - Sinalizada pelo selo especial dourado `⭐ SUPERPRIORIDADE (80+)`.

### Como aplicar ou alterar a prioridade:
1. No momento do check-in ou na lista de espera, clique no botão de prioridade do periciado (ou em **Definir Prioridade**).
2. O modal interativo se abrirá.
3. Selecione a opção correspondente (**Normal**, **Prioridade Legal** ou **Superprioridade**).
4. Clique em **Salvar Classificação**.
5. A fila de espera e o painel do consultório médico são atualizados automaticamente em tempo real!

> [!IMPORTANT]
> A recepção deve sempre conferir a data de nascimento no documento oficial (RG, CNH) para conceder automaticamente a **Superprioridade (80+)** quando aplicável.

---

## 6. Consulta de Posição na Fila e "Pessoas na Frente"

Uma das perguntas mais recorrentes na recepção é: *"Quantas pessoas ainda estão na minha frente?"*

O SGA calcula essa informação dinamicamente, considerando:
- A sala e o médico perito específico daquele paciente.
- O critério que o médico está utilizando (Ordem de Chegada ou Horário PJe).
- A política de prioridade do médico (se ativada, os prioritários passam à frente dos normais).

Na tabela **Aguardando Chamada**, a coluna **Posição na Fila** informa a colocação exata de cada cidadão:
- `1º na Fila (Próximo a ser chamado)`
- `2º na Fila (1 pessoa na frente)`
- `3º na Fila (2 pessoas na frente)`

O recepcionista pode responder de imediato e com total segurança ao jurisdicionado.

---

## 7. Emissão da Declaração de Comparecimento A4

A Declaração de Comparecimento é emitida para comprovar que o periciado ou seu acompanhante esteve na sede da Justiça Federal para a perícia.

![Declaração Oficial de Comparecimento A4](imagens/05_declaracao_comparecimento.png)

### Como Emitir:
1. Na linha do periciado (seja na lista de espera ou na lista de concluídos), clique no botão **📄 Declaração**.
2. A janela de impressão abre automaticamente em formato A4 padronizado da Justiça Federal, contendo:
   - Brasão da República e cabeçalho oficial do Tribunal Regional Federal da 5ª Região / Seção Judiciária de Alagoas.
   - Nome completo e CPF do periciado.
   - Número do processo e vara de origem.
   - Nome do médico perito responsável.
   - Horário de registro da entrada no fórum e campo para validação.
3. Clique em **Imprimir** (ou utilize o atalho de teclado `Ctrl + P`).
4. Entregue o documento assinado ou carimbado ao cidadão.

> [!TIP]
> A declaração possui CSS otimizado para impressão: todos os botões, cabeçalhos do navegador e menus do sistema são automaticamente ocultados, gerando um documento limpo e formal.

---

## 8. Dúvidas Frequentes da Recepção (FAQ)

| Situação | Ação Recomendada |
| :--- | :--- |
| **O periciado não está na pauta do dia** | Verifique se a data selecionada no topo da tela corresponde ao dia correto. Se a pauta ainda não foi homologada, avise o Supervisor da Vara. |
| **Check-in realizado por engano** | Caso tenha confirmado a presença do paciente errado, clique no botão de opções e selecione **Desfazer Presença**. A senha é cancelada e o registro volta para "Aguardando Chegada". |
| **Periciado precisa sair temporariamente** | Oriente o periciado a não se ausentar do prédio, pois caso o perito chame na TV e ele não esteja, poderá ser reposicionado no final da tolerância. |
| **A TV da recepção não está chamando com som** | Verifique se a aba do Painel TV está aberta e se o volume do televisor ou caixa de som está ligado. O navegador necessita de ao menos um clique na página para habilitar o som automático. |

---
*Manual homologado para o SGA JFAL — Seção Judiciária de Alagoas — Versão 2.4*
