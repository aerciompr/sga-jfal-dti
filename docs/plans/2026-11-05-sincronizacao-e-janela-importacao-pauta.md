# Sincronização e Janela de Importação de Pauta Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Permitir a importação filtrada exclusivamente para o dia atual (Hoje) ou no máximo até 3 dias (bloqueando categoricamente 30 dias ou todas as datas), sincronizando a pauta diária removendo/cancelando agendamentos que saíram da planilha oficial sem afetar atendimentos já iniciados.

**Architecture:** 
1. **Trava Rígida de Janela Operacional (Máximo 3 dias):** Na submissão da planilha em `gerenciar_pauta.php`, o sistema oferece estritamente duas opções: `dia` (Apenas a pauta de hoje - padrão) ou `3dias` (Hoje + 2 dias - teto máximo). Fica terminantemente vetada qualquer opção de importar 30 dias ou todas as datas. Qualquer linha na planilha cuja data exceda o limite de 3 dias é sumariamente descartada durante o processamento.
2. **Reconciliação e Auditoria:** O algoritmo de importação em `importacao_service.php` compara os agendamentos no banco com os registros do arquivo para as datas do escopo. Os registros ausentes com status `'agendado'` são atualizados para `'cancelado'` com log de auditoria dedicado (`Cancelar por Atualização de Pauta`), preservando intactos todos os atendimentos já presentes, em fila, em atendimento ou concluídos.
3. **Prévia Analítica com Transparência:** A tela `preview_importacao.php` exibe um dashboard antes da efetivação: novos agendamentos a incluir, existentes a atualizar, registros ausentes a cancelar e atendimentos em andamento protegidos.

**Tech Stack:** PHP 8.2+, MySQL (InnoDB com transactions e GET_LOCK), Python 3.12+ (openpyxl), Tailwind CSS.

---

### Diretriz de Negócio: Como Agir na Reconciliação Diária?

No fluxo de varas federais e perícias médicas (PJe / JFAL), o arquivo diário representa a pauta oficial atualizada. Três regras inegociáveis devem reger o sistema:

1. **Rastreabilidade sem exclusão física (No-Delete):** O banco do SGA restringe privilégios (`SELECT, INSERT, UPDATE`). Registros nunca são deletados fisicamente; são marcados com `status = 'cancelado'`, saindo da fila de chamada da recepção e das TVs, mas preservando o histórico para a Corregedoria e auditorias.
2. **Escopo estrito por data:** A reconciliação só pode atuar nas datas pertencentes à janela importada. Se o usuário importar a pauta de "Hoje", o sistema **jamais** tocará em agendamentos de amanhã ou de semanas seguintes.
3. **Imunidade de atendimentos em andamento:** Se o cidadão já compareceu ao prédio (`presente`, `fila`, `chamada`, `atendimento`, `concluido`), uma reimportação de pauta feita no meio do dia **nunca** cancelará esse atendimento. Apenas itens puramente `'agendado'` (que ainda não chegaram) são canceláveis por ausência.

---

### Task 1: Função de Janela de Datas em `importacao_service.php`

**Files:**
- Modify: `c:/sga-dti/importacao_service.php:4-18`
- Test: `c:/sga-dti/tests/integration.php`

**Step 1: Write test for date window filtering in integration test**
Add verification in `tests/integration.php` testing that `prepararImportacaoComFiltro` or date window logic restricts imported entries to today or today+2 days.

**Step 2: Implement date window calculation and filtering**
Update `prepararImportacao($arquivo, $janela = 'dia')`:
- Calculate `$dataInicio = date('Y-m-d')`.
- Calculate `$dataFim`:
  - `'dia'`: `date('Y-m-d')`
  - `'3dias'`: `date('Y-m-d', strtotime('+2 days'))`
  - `'7dias'`: `date('Y-m-d', strtotime('+6 days'))`
  - `'todas'`: null (todas `>= hoje`)
- Filter `$registros` by `data_pericia >= $dataInicio` and (if `$dataFim`) `data_pericia <= $dataFim`.
- Return structured payload with `registros`, `janela`, `datas_escopo`, `total_arquivo`.

**Step 3: Run integration test**
Run: `python -m unittest tests/test_import_excel.py` and integration tests.

---

### Task 2: Algoritmo de Reconciliação (Inserir Novos, Atualizar Existentes, Cancelar Ausentes)

**Files:**
- Modify: `c:/sga-dti/importacao_service.php:19-105`
- Test: `c:/sga-dti/tests/integration.php`

**Step 1: Write failing test in `tests/integration.php`**
Simulate:
- Database has Appointment A (`agendado`) and Appointment B (`presente`) for today.
- New spreadsheet for today only contains Appointment C (new) and Appointment B (existing). Appointment A was removed from the official schedule.
- Run `importarRegistros` with reconciliation enabled.
- Assert:
  - Appointment C inserted (`agendado`).
  - Appointment B preserved (`presente`).
  - Appointment A updated to `cancelado` with audit log.
  - Return stats: `novos = 1, atualizados = 0, cancelados = 1, preservados = 1`.

**Step 2: Implement reconciliation in `importarRegistros()`**
- Identify all `datas_escopo` present in the imported batch.
- For each date in `datas_escopo`:
  - Fetch all appointments for that date where `status <> 'cancelado'`.
  - Check matched IDs during the loop of `$registros`.
  - Any appointment in the database for those dates that was NOT matched:
    - If `status === 'agendado'`: update to `status = 'cancelado'`, increment `$cancelados`, call `registrarLog($pdo, 'Cancelar por Atualização de Pauta', 'Agendamento não consta na planilha oficial importada', $id)`.
    - If `status !== 'agendado'`: keep intact, increment `$preservados`.
- Return `compact('novos', 'atualizados', 'preservados', 'usuarios', 'cancelados')`.

**Step 3: Run integration tests to verify passes**

---

### Task 3: Atualizar UI do Formulário em `gerenciar_pauta.php`

**Files:**
- Modify: `c:/sga-dti/gerenciar_pauta.php:25-30`
- Modify: `c:/sga-dti/gerenciar_pauta.php:150-165`

**Step 1: Add "Janela de Importação" select in modal/card**
Add dropdown com trava rígida:
- `dia` (Padrão e Recomendado): "Apenas a pauta de hoje (1 dia)"
- `3dias`: "Hoje e próximos 2 dias (Limite máximo de 3 dias)"
*(Nenhuma opção de 7 dias, 30 dias ou 'todas' será permitida no sistema).*
- Checkbox: `[x] Sincronizar pauta (retirar agendamentos do período que não constam no novo arquivo)` (default: checked).

**Step 2: Receive parameters in POST handler**
Pass `janela` and `sincronizar` into `prepararImportacao($file, $janela)` and save options in `$_SESSION['importacao_previa']`.

---

### Task 4: Atualizar Tela de Conferência em `preview_importacao.php`

**Files:**
- Modify: `c:/sga-dti/preview_importacao.php:1-33`

**Step 1: Display reconciliation badges and date scope**
- Banner explaining:
  - 📅 **Período da importação:** Hoje (DD/MM/AAAA) ou Intervalo (DD/MM a DD/MM).
  - 🟢 **X novos agendamentos** a incluir.
  - 🔄 **Y agendamentos existentes** a manter/atualizar.
  - 🛑 **Z agendamentos a retirar/cancelar** (não constam mais no arquivo deste período).
  - 🛡️ **W atendimentos em andamento/concluídos** (100% protegidos).
- Allow user to uncheck automatic cancellation if they only want to append/update without removing.

**Step 2: Test complete flow in browser / Playwright / integration**
Verify that importing a spreadsheet with window filtering and cancellation works seamlessly end-to-end.

---

### Task 5: Validação e Commits Finais

- Run all suites (`tests/test_import_excel.py`, `tests/integration.php`).
- Test with real user spreadsheet `f3a04cdc-7717-4cb6-8d85-16dd1d74a8d7.xlsx` restricted to "Hoje".
- Create git commit with clean changelog.
