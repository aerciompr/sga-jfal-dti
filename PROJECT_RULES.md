# REGRAS DO PROJETO SGA JFAL
### Sistema de Gerenciamento de Atendimento Pericial
**Justiça Federal em Alagoas — Seção Judiciária de Alagoas (TRF5)**

---

## ⚠️ REGRA DE OURO INVIARIANTE: SINCRONIZAÇÃO OBRIGATÓRIA DE MANUAIS E PDFs

> **DIRETRIZ EXPRESSA:** Sempre que qualquer funcionalidade, fluxo de tela, botão, permissão de usuário, regra de fila ou comportamento do sistema for adicionado, modificado ou removido, **É MANDATÓRIO E OBRIGATÓRIO** atualizar imediatamente os manuais de instrução e regenerar os arquivos PDF oficiais de documentação.
> 
> **O MODELO GRÁFICO E EDITORIAL DOS PDFs HOMOLOGADO NÃO PODE FUGIR EM NADA DO PADRÃO ESTABELECIDO.**

---

## 1. Padrão Gráfico e Editorial Obrigatório dos PDFs

Todos os manuais em PDF gerados para a Justiça Federal devem obedecer rigidamente à especificação implementada no script oficial `scripts/gerar_manuais_pdf.py`:

### 1.1. Dimensões e Margens de Impressão:
- Formato: **A4 Portrait** (Retrato).
- Margens: `@page { size: A4 portrait; margin: 22mm 16mm 22mm 16mm; }`.
- Motor de renderização: **Playwright Chromium** com `@media print` e `print_background=True`.

### 1.2. Capa Institucional Oficial:
Toda capa de manual deve conter:
1. **Cabeçalho Superior:** Ícone oficial do catavento/brasão da JFAL e identificação em duas linhas:
   - Linha 1: `Poder Judiciário • Justiça Federal`
   - Linha 2: `Tribunal Regional Federal da 5ª Região • Seção Judiciária de Alagoas`
2. **Badge do Manual:** Pílula azul com texto em caixa alta:
   - Ex: `MANUAL DO USUÁRIO • MÓDULO OPERACIONAL`
3. **Título Principal:** Tipografia em caixa alta/baixa com alto peso (`font-size: 28pt; font-weight: 900; color: #0f2942;`).
4. **Subtítulo e Descritivo:** Resumo das funções operacionais cobertas no manual.
5. **Rodapé da Capa (Grid em 2 Colunas):**
   - Coluna 1: Sistema, Versão, Público-Alvo e Área Responsável (DTI).
   - Coluna 2: Homologação (Coordenadoria dos JEFs), Data da Edição e Ambiente (Produção Institucional).

### 1.3. Cabeçalho e Rodapé Repetidos em Todas as Páginas:
- **Header Template:**
  `JUSTIÇA FEDERAL EM ALAGOAS — SGA vX.X | SISTEMA DE GERENCIAMENTO DE ATENDIMENTO PERICIAL` (Fonte 8px, cinza suave `#94a3b8`, com linha divisória inferior).
- **Footer Template:**
  `Seção Judiciária de Alagoas — Documento de Uso Oficial | Página <span class="pageNumber"></span> de <span class="totalPages"></span>` (com linha divisória superior).

### 1.4. Caixas de Destaque Padronizadas (Callouts):
Utilizar exclusivamente as classes visuais do CSS oficial:
- **`callout-info`** (Fundo `#eff6ff`, borda lateral `#3b82f6`): Contexto institucional e normas.
- **`callout-tip`** (Fundo `#f0fdf4`, borda lateral `#22c55e`): Dicas operacionais de usabilidade.
- **`callout-warning`** (Fundo `#fffbeb`, borda lateral `#f59e0b`): Alertas de conduta e conferência.
- **`callout-priority`** (Fundo `#fefce8`, borda lateral `#eab308`): Regras de prioridade legal (60+/PCD) e superprioridade (80+).
- **`callout-critical`** (Fundo `#fef2f2`, borda lateral `#ef4444`): Atenção crítica, segurança e trava anti-conflito.

### 1.5. Imagens e Capturas de Tela:
- Borda suave `#cbd5e1`, cantos arredondados (`border-radius: 8px`), sombra leve.
- Legenda centralizada obrigatória em itálico com numeração: `Figura X.X — Descrição objetiva da tela`.
- Quebra de página estritamente controlada: `page-break-inside: avoid` em imagens, tabelas e cards para nunca dividir elementos ao meio entre duas folhas.

---

## 2. Relação dos Manuais Oficiais Mantidos

O repositório mantém 4 manuais em duas versões (Web Markdown e PDF A4):

| Módulo do Sistema | Arquivo Markdown | Arquivo PDF Oficial |
| :--- | :--- | :--- |
| **Recepção e Triagem** | `docs/manuais/manual_recepcao.md` | `docs/manuais/Manual_Recepcao_SGA_JFAL.pdf` |
| **Médico Perito Judicial** | `docs/manuais/manual_perito.md` | `docs/manuais/Manual_Perito_Medico_SGA_JFAL.pdf` |
| **Gestão e Supervisão** | `docs/manuais/manual_supervisor.md` | `docs/manuais/Manual_Supervisor_Gestor_SGA_JFAL.pdf` |
| **Manual Geral Unificado** | `docs/manuais/README.md` | `docs/manuais/Manual_Geral_Completo_SGA_JFAL.pdf` |

---

## 3. Fluxo de Trabalho Obrigatório para o Desenvolvedor / IA

Sempre que efetuar uma mudança no código-fonte (`.php`, `.js`, `.css` ou banco de dados):

```
[ 1. Implementar Código / Migração ]
              │
[ 2. Validar Sintaxe PHP / Testes ]
              │
[ 3. Atualizar Manuais Markdown em docs/manuais/ ]
              │
[ 4. Se alterou a UI: Atualizar Capturas com scripts/gerar_screenshots_manuais.py ]
              │
[ 5. Compilar os 4 PDFs: python scripts/gerar_manuais_pdf.py ]
              │
[ 6. Git Commit com Código + Manuais MD + Manuais PDF ]
              │
[ 7. Git Push origin master ]
```

### Comandos Rápidos de Execução:
```powershell
# 1. Regenerar prints (caso haja alteração de interface visual):
python scripts/gerar_screenshots_manuais.py

# 2. Compilar os 4 PDFs no padrão exato homologado:
python scripts/gerar_manuais_pdf.py

# 3. Gravar/Regenerar o Vídeo Tutorial do Perito (Playwright + FFmpeg):
python scripts/gravar_tutorial_perito.py

# 4. Adicionar alterações e comitar:
git add .
git commit -m "feat/fix: descrição da alteração com atualização de manuais e PDFs"
git push origin master
```

---
*Regras homologadas pela Divisão de Tecnologia da Informação (DTI) — JFAL*
