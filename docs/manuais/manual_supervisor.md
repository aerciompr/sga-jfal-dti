# Manual do Usuário — Gestão e Supervisão de Perícias
### Sistema de Gerenciamento de Atendimento Pericial (SGA)
**Justiça Federal em Alagoas — Seção Judiciária de Alagoas**

---

## 1. Visão Geral e Finalidade

O módulo de **Supervisão e Gestão** do SGA destina-se aos Diretores de Secretaria, Supervisores de Perícias, Coordenadores do Juizado Especial Federal (JEF) e gestores judiciários.

Suas principais atribuições são:
1. **Monitoramento Executivo em Tempo Real:** Acompanhamento instantâneo da capacidade operacional de perícias do fórum.
2. **Indicadores de Gestão Judiciária (KPIs):** Análise de absenteísmo, tempos médios de espera dos cidadãos e tempos médios de exame pericial.
3. **Auditoria e Produtividade por Perito:** Avaliação do rendimento individual dos médicos peritos, salas ocupadas e critérios de fila utilizados.
4. **Carga e Homologação de Pautas do PJe:** Importação ágil de planilhas Excel (`.xlsx`) extraídas do Processo Judicial Eletrônico e homologação das agendas.
5. **Exportação de Relatórios e BI:** Geração de relatórios consolidados em formato CSV para subsidiar tomadas de decisão e prestações de contas à Corregedoria e Direção do Foro.
6. **Administração do Painel TV da Sala de Espera:** Supervisão da exibição e alertas sonoros da TV.

---

## 2. Acesso ao Painel de Supervisão

1. Acesse o sistema institucional através do navegador web: `http://atendimento.jfal.jus.br`.
2. Insira as credenciais autorizadas de **Supervisor / Gestor**.
3. No menu principal de navegação, selecione **📊 Dashboard Executivo** ou **📅 Gestão de Pautas**.

![Tela de Autenticação do SGA](imagens/01_login.png)

---

## 3. Dashboard Executivo e Indicadores Operacionais

O Dashboard Executivo condensa em uma única tela de alto impacto todos os dados essenciais para o acompanhamento dos trabalhos do dia:

![Dashboard Executivo com Indicadores de Gestão](imagens/11_dashboard_executivo.png)

### 3.1. Cartões de Indicadores Chave (KPIs):
- **📅 Total Agendado:** Volume total de perícias judiciais designadas para o dia selecionado.
- **🏃 Presentes (Check-in):** Quantidade e percentual de periciados que já compareceram ao balcão e retiraram senha.
- **⚠️ Taxa de Absenteísmo (%):** Percentual de ausência calculada automaticamente. Permite à gestão dimensionar o impacto do não-comparecimento de partes no cronograma das varas federais.
- **🕒 Tempo Médio de Espera:** Intervalo médio de tempo decorrido entre o check-in na recepção e o início do exame no consultório pericial.
- **⏱️ Tempo Médio de Perícia:** Duração média de cada consulta médica pericial realizada.

### 3.2. Gráficos de Distribuição do Atendimento:
- **Status do Dia:** Gráfico dinâmico dividindo os processos em:
  - `Aguardando Chegada` (ainda não compareceram)
  - `Fila de Espera` (presentes no prédio aguardando chamado)
  - `Em Atendimento` (atualmente dentro do consultório com o perito)
  - `Concluídas` (perícias já finalizadas)
- **Comparativo por Especialidade/Vara:** Visão segregada da demanda pericial por vara federal de origem.

---

## 4. Produtividade por Médico Perito e Exportação de Dados

Na seção inferior do Dashboard, a tabela de auditoria por perito apresenta o desempenho individualizado de cada médico escalado:

### Informações Consolidadas:
- **Médico Perito:** Nome completo e especialidade médica.
- **Sala Vinculada:** Consultório físico ocupado (ou status `Desconectado`).
- **Critério Ativo:** Informa se o perito está atendendo por `Ordem de Chegada` ou `Horário PJe`, e se a prioridade legal está `Ativa` ou `Opcional`.
- **Agendados vs Realizados:** Total de perícias na pauta do médico e quantas já foram finalizadas.
- **Faltas Registradas:** Casos de ausência confirmada.
- **Tempo Médio Individual:** Duração média das perícias daquele profissional específico.

### Exportação em Formato CSV:
No topo direito da tabela de produtividade, clique no botão **📥 Exportar Relatório (CSV)**:
- O sistema gera instantaneamente uma planilha compatível com Microsoft Excel, LibreOffice Calc e Power BI contendo todos os dados abertos com carimbo de data/hora, número de processo, perito, tempos e status.
- Ideal para envio de relatórios semanais/mensais à Coordenadoria dos JEFs.

---

## 5. Gerenciamento e Homologação de Pautas do PJe

A rotina de carga de pautas permite que a supervisão alimente o SGA a partir dos relatórios extraídos diretamente do PJe (Processo Judicial Eletrônico do TRF5).

![Módulo de Gerenciamento e Importação de Pauta](imagens/13_gerenciamento_pauta.png)

### Passo a Passo da Importação de Pauta:
1. **Extração no PJe:** No sistema PJe, exporte o relatório da pauta de perícias no formato de planilha Excel (`.xlsx`).
2. **Acesso ao Módulo:** No menu do SGA, acesse **📅 Gestão de Pautas / Importar Pauta**.
3. **Seleção do Arquivo:**
   - Arraste o arquivo `.xlsx` para a área de upload tracejada ou clique em **Selecionar Arquivo no Computador**.
4. **Definição da Janela de Importação:**
   - `Somente o Dia Selecionado (Hoje)`: Recomendado para cargas diárias matinais.
   - `Próximos 3 Dias`: Para adiantar agendamentos de feriados ou fins de semana.
   - `Mês Inteiro`: Para carga massiva de mutirões previdenciários e assistenciais.
5. **Homologação da Pauta:**
   - Deixe marcada a opção **Homologação Automática** se desejar que a recepção comece a atender imediatamente os periciados assim que o upload for concluído.
   - Caso prefira conferir a lista antes de liberar aos balcões, desmarque a opção e clique em **Homologar** manualmente na listagem.
6. **Processamento:**
   - Clique no botão verde **🚀 Processar e Importar Pauta para o SGA**.
   - O sistema valida a consistência de CPF, número de processo, nomes e horários, informando um resumo com a quantidade de perícias importadas com sucesso.

> [!TIP]
> O SGA possui mapeamento flexível de cabeçalhos de colunas, aceitando automaticamente variações como "Nome da Parte", "Autor", "Periciado", "Processo Judicial" e "Número do Processo".

---

## 6. Monitoramento da Sala de Espera e Painel TV

O painel de TV (`painel.php`) é o canal direto de comunicação visual e sonora com os jurisdicionados presentes na sala de espera do Fórum.

![Painel da TV da Sala de Espera](imagens/14_painel_tv_chamada.png)

### Recomendações Técnicas para a TV:
- **Dispositivo:** Smart TV ou computador conectado a monitor via cabo HDMI/rede local.
- **Navegador:** Iniciar o navegador em modo tela cheia (tecla **F11**).
- **Áudio:** Assegurar que as caixas acústicas da recepção ou os alto-falantes da TV estejam ligados com volume audível. O sistema utiliza a API nativa Web Speech para sintetização de voz em português claro e toques de campainha de alta frequência.
- **Histórico Lateral:** O painel mantém permanentemente visíveis as últimas 5 senhas chamadas com indicação do médico e da sala, permitindo ao cidadão conferir mesmo após o término do aviso sonoro.

---

## 7. Rotina Diária Recomendada para o Supervisor

Para garantir a excelência operacional do atendimento pericial, recomenda-se a seguinte rotina diária:

```
[ 07:45 ] - Carga e Homologação da Pauta do Dia (.xlsx do PJe)
    │
[ 08:00 ] - Abertura da TV na Recepção (F11 + Conferência do Som)
    │
[ 08:30 ] - Verificação da Entrada dos Médicos e Ocupação de Salas
    │
[ 12:00 ] - Análise Intermediária no Dashboard (Tempos de Espera e Fila)
    │
[ 17:00 ] - Fechamento Operacional: Exportação do Relatório CSV do Dia
```

### Planos de Contingência:
- **Médico necessita trocar de consultório:** Oriente o perito a clicar em **Trocar de Sala** no seu painel. A sala anterior fica livre de imediato.
- **Periciado convocado com urgência:** A recepção pode registrar o check-in e a prioridade legal; o perito pode puxá-lo ao topo com um clique na chave de prioridade.
- **Falha de rede local momentânea:** Os dados ficam salvos com segurança no banco de dados central; assim que a conexão se restabelecer, o sistema retoma o estado exato da fila.

---
*Manual homologado para o SGA JFAL — Seção Judiciária de Alagoas — Versão 2.4*
