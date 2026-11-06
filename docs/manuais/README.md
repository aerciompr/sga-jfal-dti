# Documentação Oficial de Usuário — SGA JFAL
### Sistema de Gerenciamento de Atendimento Pericial
**Justiça Federal em Alagoas — Seção Judiciária de Alagoas (TRF5)**

---

## 📖 Apresentação

O **Sistema de Gerenciamento de Atendimento Pericial (SGA)** é a plataforma oficial da Seção Judiciária de Alagoas desenvolvida para modernizar, humanizar e dar máxima celeridade à realização de perícias médicas judiciais previdenciárias e assistenciais.

Esta suíte de documentação reúne os manuais práticos de operação do sistema, ilustrados com capturas de tela reais de cada perfil de uso.

---

## 📚 Manuais Disponíveis

| Perfil de Usuário | Manual de Operação | Principais Atribuições |
| :--- | :--- | :--- |
| **👩‍💼 Recepção & Triagem** | [**Manual da Recepção**](manual_recepcao.md) | Check-in, geração de senhas (`PE-XX`), classificação de prioridades (Normal, 60+/PCD, 80+ Superprioridade), monitoramento das salas e emissão da Declaração de Comparecimento A4. |
| **👨‍⚕️ Médico Perito Judicial** | [**Manual do Perito Médico**](manual_perito.md) | Seleção de consultório com trava anti-conflito, chamadas sonoras na TV, autonomia de critério (Chegada vs Horário PJe), alternador de prioridade, tolerância de ausência (2 posições) e liberação de sala. |
| **📊 Supervisão & Gestão** | [**Manual do Supervisor**](manual_supervisor.md) | Dashboard executivo, KPIs de tempo de espera e absenteísmo, auditoria de produtividade médica, exportação CSV, importação e homologação de pauta Excel (`.xlsx`) do PJe. |

---

## 🗺️ Fluxo Integrado de Atendimento Ponta a Ponta

O fluxo operacional do SGA conecta todas as etapas do processo judicial de perícias:

```
[ PJe — TRF5 ]
      │ (Exportação da Pauta de Perícias .xlsx)
      ▼
[ 1. Supervisão SGA ] ── Homologação da Pauta Diária
      │
      ▼
[ 2. Recepção SGA ] ── Identificação do Cidadão + Classificação de Prioridade Legal
      │                Geração da Senha de Atendimento (ex: PE-01)
      ▼
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

---

## 💻 Suporte e Informações Técnicas

- **Plataforma:** Sistema SGA JFAL (PHP 8.2 / Tailwind CSS / MariaDB)
- **Hospedagem:** Infraestrutura local da Seção Judiciária de Alagoas
- **Contato DTI / Suporte:** Divisão de Tecnologia da Informação — JFAL
- **Repositório de Código:** `https://github.com/aerciompr/sga-jfal-dti`

---
*Documentação técnica e operacional produzida para a Justiça Federal em Alagoas — 2026*
