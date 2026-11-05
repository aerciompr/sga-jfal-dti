# Plano de Implementação: Melhorias Aprovadas no SGA JFAL

> **Módulos Abrangidos:** Recepção, Painel do Perito, Autenticação/Salas, Importação/Banco e Dashboard Gerencial.
> **Destaque:** Sincronização em tempo real entre Perito e Recepção no cálculo de posição e pessoas na frente conforme o critério ativo (Chegada vs Horário PJe).

---

## 🎯 Visão Geral das Funcionalidades Aprovadas

| Item | Funcionalidade | Objetivo Operacional |
| :--- | :--- | :--- |
| **1** | **Declaração de Comparecimento (1 Clique)** | Emissão de comprovante oficial A4 com timbre do Poder Judiciário / JFAL para trabalhadores e periciados comprovarem presença ao empregador/órgão com horários de entrada e saída. |
| **2** | **Alerta Sonoro e Toast no Painel do Perito** | Aviso discreto por Web Audio API (chime suave de dois tons) e notificação flutuante no monitor do médico quando a recepção confirma a chegada de um periciado para sua fila. |
| **3** | **Alternador de Critério (Chegada vs Horário PJe) Sincronizado com a Recepção** | **Padrão:** Ordem de Chegada (`fila_em ASC`). **Opção do Médico:** Alternador na tela para ordenar por Horário do PJe (`hora_pericia ASC`). **Sincronização Total com a Recepção:** A recepção calcula o número exato de "pessoas na frente" com base no critério ativo do perito e exibe badge identificador. **Trava:** Só chama quem já chegou na recepção. |
| **4** | **Liberação Automática de Sala no Logout** | Desocupação automática da sala quando o perito encerra sua sessão (`logout.php`) e limpeza de travas obsoletas após inatividade, evitando salas bloqueadas entre turnos. |
| **5** | **Dashboard de Métricas Diárias e Produtividade** | Painel executivo em `dashboard.php` com indicadores em tempo real (agendados, presentes, concluídos, absenteísmo, tempo médio de espera e atendimento por perito) e exportação em CSV para a Diretoria do Foro. |

---

## 📋 Detalhamento das Tarefas de Implementação

### Tarefa 1: Declaração / Comprovante de Comparecimento à Perícia Judicial
**Arquivos:**
- Criar: `declaracao_comparecimento.php`
- Modificar: `recepcao.php` (adicionar botão na tabela e no modal de detalhes)
- Modificar: `atendente.php` (adicionar botão no atendimento ativo e na tabela)

**Especificação Técnica:**
1. Criar `declaracao_comparecimento.php`:
   - Validação de sessão (perfis `admin`, `supervisor`, `recepcao`, `perito`).
   - Parâmetro `?id={atendimento_id}`.
   - Carrega dados do atendimento: nome do periciado, CPF formatado, número do processo, nome do perito, sala, data da pauta, hora de chegada (`chegada_em`), hora de início (`iniciado_em`) e hora de conclusão (`finalizado_em` ou hora atual).
   - Layout formal e institucional:
     - Cabeçalho: Brasão/Logo da JFAL, "PODER JUDICIÁRIO — JUSTIÇA FEDERAL EM ALAGOAS — SEÇÃO JUDICIÁRIA DE ALAGOAS".
     - Título: **DECLARAÇÃO DE COMPARECIMENTO A PERÍCIA MÉDICA JUDICIAL**.
     - Texto padrão oficial da JFAL com os dados e horários de entrada e saída.
     - Campo de assinatura do servidor/perito e código de autenticidade/hash com data e hora de emissão.
     - Regra `@media print`: esconde botões, ajusta margens de folha A4 e invoca `window.print()` por atalho.
2. Integrar atalhos:
   - Em `recepcao.php`: botão `[ 🖨️ Declaração ]` no modal de detalhes do paciente e na coluna de ações quando `chegada_em IS NOT NULL`.
   - Em `atendente.php`: botão `[ 🖨️ Declaração ]` no card do atendimento em andamento e na lista de concluídos do dia.

---

### Tarefa 2: Alerta Sonoro e Notificação Toast no Painel do Médico
**Arquivos:**
- Modificar: `atendente.php`

**Especificação Técnica:**
1. Sintetizador de Som via Web Audio API (100% nativo do navegador, sem dependência de arquivos de áudio externos):
   - Criar função JS `tocarChimeChegada()`:
     - Gera uma sequência harmônica suave de dois tons (Nota C5 523Hz por 150ms + Nota G5 784Hz por 300ms) com curva de decaimento exponencial de ganho (*smooth decay*).
     - Extremamente discreto e não estridente, ideal para ambiente de consultório médico.
2. Notificação Visual (Toast):
   - Componente flutuante fixado no canto superior direito:
     - Ícone `🔔`, nome do periciado em destaque, horário/senha e botão para fechar.
     - Auto-dismiss após 8 segundos.
3. Gatilho de Detecção:
   - No loop de polling (`api_perito_fila.php`), comparar `totais.aguardando` atual com o anterior.
   - Se `novoAguardando > anteriorAguardando` e a página já tiver sido inicializada, disparar alerta sonoro e toast.
4. Controle de Som pelo Médico:
   - Botão no cabeçalho: `[ 🔊 Som Ativo ]` / `[ 🔇 Mudo ]`.
   - Estado persistido em `localStorage.getItem('sga_som_perito')`.

---

### Tarefa 3: Sincronização de Critério (Chegada vs Horário PJe) entre Perito e Recepção
**Arquivos:**
- Modificar: `import_excel.py`
- Modificar: `scripts/migrate.php`
- Modificar: `importacao_service.php`
- Modificar: `atendimento_service.php`
- Modificar: `api_perito_fila.php`
- Modificar: `api_recepcao_peritos.php`
- Modificar: `atendente.php`
- Modificar: `recepcao.php`

**Especificação Técnica:**
1. Extração de Hora da Planilha:
   - Em `import_excel.py`: a coluna `Data da Perícia` contém data e hora. Extrair `hora_pericia` (`HH:MM`).
2. Banco de Dados:
   - Adicionar coluna `hora_pericia TIME NULL DEFAULT NULL` em `atendimentos`.
   - Adicionar coluna `criterio_fila VARCHAR(20) NOT NULL DEFAULT 'chegada'` em `salas_peritos`.
3. Alternador de Critério no Perito:
   - No topo do painel do médico:
     - `Critério de Atendimento: [ 🏃 Ordem de Chegada (Padrão) ] | [ 🕒 Horário PJe ]`
   - Ao alterar, envia requisição rápida para salvar a preferência em `salas_peritos.criterio_fila`.
4. **Cálculo da Fila Sincronizado com a Recepção:**
   - Em `atendimento_service.php` na função `obterResumoFilasPeritos`:
     - Ler o `criterio_fila` de cada perito.
     - Se `criterio_fila === 'horario'`: a fila de presentes é ordenada por `COALESCE(hora_pericia, '23:59:59') ASC, fila_em ASC, id ASC`.
     - Se `criterio_fila === 'chegada'`: a fila de presentes é ordenada por `fila_em ASC, id ASC`.
   - Dessa forma, o cálculo de **`posicao`** e **`pessoas_na_frente`** na Recepção passa a bater 100% com a ordem exata do médico!
5. Visualização na Recepção:
   - No Card do Perito: badge indicativo `[ 🏃 Chegada ]` ou `[ 🕒 Horário PJe ]`.
   - No Modal de Detalhes da Recepção:
     - Exemplo se o médico usa Horário PJe: *"Fila do Dr. X organizada por horário do PJe. Horário agendado: 10:20 (2 pessoas na frente com horário anterior)."*
6. Trava Inviolável de Chamada:
   - Em qualquer critério, o botão `[ 📢 Chamar ]` só é habilitado para quem tiver presença confirmada pela recepção (`status = 'presente'`). Quem não chegou fica com `[ ⏳ Aguardando Recepção ]` bloqueado.

---

### Tarefa 4: Desconexão e Liberação de Sala no Logout do Perito
**Arquivos:**
- Modificar: `logout.php`
- Modificar: `atendimento_service.php`

**Especificação Técnica:**
1. Em `logout.php`:
   - Antes de destruir a sessão:
     - Se `$_SESSION['usuario_role'] === 'perito'`, verificar se o perito possui registro ativo em `salas_peritos`.
     - Executar `liberarSalaPerito($pdo, $_SESSION['usuario_name'])`.
     - Gravar evento em `logs_auditoria`: `"Liberar Sala por Logout"`.
2. Em `atendimento_service.php` na função `obterSalasOcupadasHoje`:
   - Expirador de Inatividade: considerar liberadas salas cujo `atualizado_em` tenha mais de 4 horas sem nenhum atendimento com `status = 'em_atendimento'`.
   - No polling de `api_perito_fila.php`: atualizar `atualizado_em` da sala ocupada pelo perito, assegurando que enquanto o médico estiver navegando com o painel aberto a trava continue ativa.

---

### Tarefa 5: Dashboard de Produtividade, Métricas Diárias e Exportação
**Arquivos:**
- Modificar: `atendimento_service.php` (adicionar `obterMetricasDia`)
- Modificar: `dashboard.php` (incorporar os cards gerenciais e tabela)

**Especificação Técnica:**
1. Função `obterMetricasDia(PDO $pdo, string $data): array`:
   - Consulta agregada na data:
     - `total_agendados`: total na pauta.
     - `presentes`: quantos compareceram (`chegada_em IS NOT NULL`).
     - `concluidos`: perícias realizadas com sucesso (`status = 'concluido'`).
     - `em_atendimento`: perícias acontecendo agora.
     - `ausentes_desistentes`: `nao_compareceu` + `desistente`.
     - `taxa_absenteismo`: percentual de não comparecimento sobre a pauta do dia.
     - `tempo_medio_espera_min`: média em minutos entre `chegada_em` e a primeira chamada (`primeira_chamada_em` ou `iniciado_em`).
     - `tempo_medio_pericia_min`: média em minutos entre `iniciado_em` e `finalizado_em`.
   - Detalhamento por perito do dia:
     - Perito, Sala em uso, Agendados, Presentes, Concluídos, Aguardando na fila, Tempo médio de atendimento.
2. Interface do `dashboard.php`:
   - Para perfis `admin` e `supervisor`:
     - Seletor de data (padrão: hoje).
     - Grid com 4 cards de topo: **Atendimentos do Dia**, **Taxa de Comparecimento vs Absenteísmo**, **Tempo Médio de Espera**, **Tempo Médio de Perícia**.
     - Tabela dinâmica de produtividade por perito.
     - Botão `[ 📥 Exportar Relatório Diário (.CSV) ]` com codificação UTF-8 BOM e delimitadores formatados para Excel.
   - Para perfil `recepcao`: mantém o menu enxuto e direto.

---

## 🔒 Testes e Critérios de Aceite

1. **Sincronia de Critério (Perito & Recepção):**
   - Perito A está em "Ordem de Chegada": fila do Perito A na recepção ordena por chegada.
   - Perito B muda para "Horário PJe": a fila do Perito B na recepção reordena automaticamente por horário agendado.
   - O contador de "pessoas na frente" informado na recepção bate perfeitamente com a ordem do médico.
2. **Declaração de Comparecimento:**
   - Acesso seguro apenas para usuários logados.
   - Impressão A4 sai limpa, sem menus pretos, cabeçalho institucional e dados precisos de horários de entrada e saída.
3. **Alerta do Perito:**
   - Recepção confirma presença: perito ouve o chime harmônico suave e vê o toast `Novo periciado na recepção`.
   - Botão liga/desliga de som preserva o estado no navegador.
4. **Liberação no Logout:**
   - Perito sai pelo botão `Sair`: sala é liberada imediatamente em `salas_peritos`.
5. **Dashboard:**
   - Cálculos de absenteísmo e tempos médios batem com os registros de `atendimentos`.
   - Download de CSV abre no Excel do Windows sem caracteres corrompidos.
6. **Sintaxe e Regressão:**
   - Todos os arquivos PHP validados com `php.exe -l`.
   - Testes unitários do repositório executados e validados.
