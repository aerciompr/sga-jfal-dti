# Instruções para o Assistente IA — SGA JFAL

Consulte e siga rigorosamente as diretrizes estabelecidas em [PROJECT_RULES.md](PROJECT_RULES.md).

## ⚠️ DIRETRIZ FUNDAMENTAL DE SINCRONIZAÇÃO DE DOCUMENTAÇÃO:
Sempre que você alterar, criar ou refatorar qualquer tela, regra de negócio, permissão ou fluxo de atendimento no sistema SGA JFAL:
1. **Atualize os manuais em Markdown** correspondentes em `docs/manuais/`.
2. **Atualize as capturas de tela** se houver mudança de interface utilizando `python scripts/gerar_screenshots_manuais.py`.
3. **Regenere os 4 arquivos PDF oficiais** executando:
   ```powershell
   python scripts/gerar_manuais_pdf.py
   ```
4. **Regenere o Vídeo Tutorial do Perito** se houver alteração nos campos do consultório:
   ```powershell
   python scripts/gravar_tutorial_perito.py
   ```
5. **NÃO MUDE O MODELO DOS PDFs:** O modelo homologado para os PDFs já está fixado e codificado em `scripts/gerar_manuais_pdf.py`. Ele inclui capa institucional oficial com brasão, cabeçalho e rodapé em todas as páginas com numeração automática, callouts institucionais (azul, verde, amarelo, dourado e vermelho), tipografia do sistema e layout de alta fidelidade A4. **Não deve fugir em nada desse padrão.**
5. Faça o commit de todo o conjunto (código + manuais `.md` + manuais `.pdf`) e o envio para o repositório.
