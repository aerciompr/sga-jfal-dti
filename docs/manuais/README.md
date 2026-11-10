# Documentação Oficial de Usuário — SGA JFAL
### Sistema de Gerenciamento de Atendimento Pericial
**Justiça Federal em Alagoas — Seção Judiciária de Alagoas (TRF5)**

---

## 📖 Apresentação

O **Sistema de Gerenciamento de Atendimento Pericial (SGA)** é a plataforma oficial da Seção Judiciária de Alagoas desenvolvida para modernizar, humanizar e dar máxima celeridade à realização de perícias médicas judiciais previdenciárias e assistenciais.

Esta suíte de documentação reúne os manuais práticos de operação do sistema, ilustrados com capturas de tela reais de cada perfil de uso.

---

## 📚 Manuais Disponíveis (Versões Web e PDF para Impressão)

| Perfil de Usuário | Versão Web (Markdown) | Versão PDF Oficial (A4 Impressão) | Principais Atribuições |
| :--- | :--- | :--- | :--- |
| **👩‍💼 Recepção & Triagem** | [**Manual da Recepção**](manual_recepcao.md) | [📥 **Manual_Recepcao_SGA_JFAL.pdf**](Manual_Recepcao_SGA_JFAL.pdf) | Check-in, geração de senhas (`PE-XX`), classificação de prioridades (Normal, 60+/PCD, 80+ Superprioridade), inclusão manual, edição de periciados, configuração do vídeo da TV e emissão da Declaração de Comparecimento A4. |
| **👨‍⚕️ Médico Perito Judicial** | [**Manual do Perito Médico**](manual_perito.md) | [📥 **Manual_Perito_Medico_SGA_JFAL.pdf**](Manual_Perito_Medico_SGA_JFAL.pdf) | Primeiro acesso com troca obrigatória de credencial inicial, seleção de consultório com trava anti-conflito, chamadas sonoras na TV, autonomia de critério (Chegada vs Horário PJe), alternador de prioridade e tolerância de ausência. |
| **📊 Supervisão & Gestão** | [**Manual do Supervisor**](manual_supervisor.md) | [📥 **Manual_Supervisor_Gestor_SGA_JFAL.pdf**](Manual_Supervisor_Gestor_SGA_JFAL.pdf) | Dashboard executivo, KPIs de tempo de espera e absenteísmo, gestão de segurança e imposição de troca de senha para peritos (individual e coletiva), importação de pauta Excel (.xlsx) do PJe e auditoria CSV. |
| **🏛️ Edição Geral Consolidada** | *Consolidação Integral* | [📥 **Manual_Geral_Completo_SGA_JFAL.pdf**](Manual_Geral_Completo_SGA_JFAL.pdf) | Manual institucional unificado abrangendo todos os módulos, fluxo ponta a ponta, governança de credenciais e marco legal das prioridades federais. |

---

## 🎥 Vídeos Tutoriais e Demonstrações Audiovisuais

Para facilitar o treinamento do corpo clínico e da equipe de apoio judiciário:

| Recurso Multimídia | Formato | Como Assistir / Baixar |
| :--- | :--- | :--- |
| **🎬 Vídeo Tutorial do Médico Perito** | MP4 (H.264 HD 720p — 2.6 MB) | [📥 **Baixar tutorial_painel_perito_sga.mp4**](videos/tutorial_painel_perito_sga.mp4) |
| **🌐 Player Web Interativo com Capítulos** | Página HTML5 Interativa | [▶️ **Abrir assistir_tutorial.html**](videos/assistir_tutorial.html) |
| **🎞️ Gravação Original em Alta Fidelidade** | WebM nativo do navegador | [📥 **Baixar tutorial_painel_perito_sga.webm**](videos/tutorial_painel_perito_sga.webm) |

*Tópicos abordados no vídeo (52 segundos):*
1. Trava anti-conflito de salas periciais.
2. Como alternar o critério da fila (**Ordem de Chegada** vs **Horário do PJe**).
3. Como acionar a chave de **Prioridade Legal** e seu efeito imediato (Superprioridade 80+ no topo da fila).
4. Disparo de chamada na TV da sala de espera (áudio e voz sintetizada).
5. Início do atendimento pericial e cronômetro.
6. Aplicação da **Tolerância de Ausência** (descer 2 posições sem cancelar perícia).

---

## 🗺️ Fluxo Integrado de Atendimento Ponta a Ponta

O fluxo operacional do SGA conecta todas as etapas do processo judicial de perícias:

```
[ 1. PJe TRF5 ] ────── Exportação da Pauta Diária (.xlsx)
      │
      ▼
[ 2. SGA Recepção ] ── Check-in do Periciado + Classificação Legal (Normal / 60+ / 80+)
      │
      ├──────────────────────────────┐
      ▼                              ▼
[ 3. Painel TV ] ───── Chamada Audiovisual na Sala de Espera (Voz + Campainha)
      ▲
      │ (Chamada acionada pelo médico)
[ 4. Consultório Médico ] ── Realização do Exame Pericial (Início e Conclusão)
      │
      ▼
[ 5. Declaração A4 ] ─ Emissão do Comprovante Oficial com Brasão da República
      │
      ▼
[ 6. Dashboard BI ] ── Indicadores Consolidados, Auditoria e Exportação CSV
```

---

## 🖼️ Galeria de Telas do Sistema

Todas as telas foram projetadas com foco em usabilidade, conformidade com a legislação de acessibilidade e diretrizes de design institucional da Justiça Federal:

1. **[Tela de Autenticação / Login](imagens/01_login.png):** Acesso individualizado por perfil com validação de credenciais.
2. **[Painel Geral da Recepção](imagens/02_recepcao_visao_geral.png):** Visão completa das salas ativas, filas e pauta diária.
3. **[Confirmação de Presença](imagens/03_confirmar_presenca.png):** Diálogo de check-in com geração de senha instantânea.
4. **[Classificação de Prioridade Legal](imagens/04_modal_prioridade_legal.png):** Adequação às Leis nº 10.048/2000, 10.741/2003 e 13.466/2017.
5. **[Declaração Oficial de Comparecimento](imagens/05_declaracao_comparecimento.png):** Modelo A4 para impressão imediata.
6. **[Seleção de Consultório Pericial](imagens/06_perito_selecao_sala.png):** Trava inteligente contra choque de horários e duplicidade de sala.
7. **[Painel Operacional do Médico](imagens/07_perito_painel_principal.png):** Gestão dinâmica do atendimento clínico.
8. **[Notificação Toast no Consultório](imagens/09_perito_alerta_toast.png):** Sinalização silenciosa de novos periciados na sala de espera.
9. **[Dashboard Executivo de Gestão](imagens/11_dashboard_executivo.png):** Painel estratégico com métricas e produtividade.
10. **[Importação de Pauta Excel PJe](imagens/13_gerenciamento_pauta.png):** Upload em lote de planilhas judiciais do TRF5.
11. **[Painel TV de Chamadas](imagens/14_painel_tv_chamada.png):** Interface pública de alta visibilidade com alerta sonoro.
12. **[Alteração Obrigatória de Senha](imagens/15_alterar_senha_obrigatorio.png):** Política de segurança para primeiro acesso e substituição de credenciais provisórias.
13. **[Gestão de Perfis e Senhas](imagens/16_usuarios_gerenciamento.png):** Painel do administrador para monitoramento de segurança e imposição de troca coletiva/individual de senhas.

---

## ⚙️ Manutenção e Compilação dos Manuais

Em conformidade com as regras do projeto ([PROJECT_RULES.md](../../PROJECT_RULES.md)), **qualquer alteração no sistema exige a atualização dos manuais e a regeneração imediata dos PDFs oficiais**, mantendo estritamente o modelo homologado pela JFAL.

### Scripts Oficiais de Compilação:
- **Gerar/Atualizar os 4 PDFs:**
  ```powershell
  python scripts/gerar_manuais_pdf.py
  ```
- **Gerar/Atualizar as Capturas de Tela:**
  ```powershell
  python scripts/gerar_screenshots_manuais.py
  ```

---

## 💻 Suporte e Informações Técnicas

- **Plataforma:** Sistema SGA JFAL (PHP 8.2 / Tailwind CSS / MariaDB)
- **Hospedagem:** Infraestrutura local da Seção Judiciária de Alagoas
- **Contato DTI / Suporte:** Divisão de Tecnologia da Informação — JFAL
- **Repositório de Código:** `https://github.com/aerciompr/sga-jfal-dti`

---
*Documentação técnica e operacional produzida para a Justiça Federal em Alagoas — 2026*
