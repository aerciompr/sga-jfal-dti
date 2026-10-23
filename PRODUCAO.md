# Implantação oficial — SGA/JFAL

## Stack existente no Portainer (ajuste posterior)

A configuração Compose foi adaptada à instalação existente informada pelo responsável: MySQL **8.0.46**, volume externo **sga-dti_db_data**, acesso HTTP na intranet pela porta 8085. Não há atualização do mecanismo para 8.4 neste Compose. O volume é externo: o Compose falha se ele não existir, em vez de criar silenciosamente uma base vazia.

No Portainer, informar DB_NAME, DB_USER, DB_PASS e MYSQL_ROOT_PASSWORD com os valores atuais. Para o ambiente HTTP informado, SESSION_SECURE=0; com HTTPS, usar 1. DB_VOLUME_NAME permite selecionar outro volume existente, sem criar nem copiar dados automaticamente. Não usar o caminho interno `_data` como bind mount.

O serviço migrate executa antes de web, que só inicia após sucesso da migração. RUNTIME_DB_USER fica vazio por padrão para preservar as permissões do usuário legado root; nunca definir RUNTIME_DB_USER=root. Uma conta restrita deverá ser criada e validada separadamente antes de migrar a aplicação para ela. O Compose não cria usuário de runtime automaticamente em volumes existentes.

Fazer backup e ensaiar em cópia isolada antes do redeploy. O Compose usa build local; o Portainer precisa construir a imagem a partir do repositório, como na stack anterior. Nenhuma imagem sga-jfal:local é exigida de um registry.

## Situação da entrega

As correções são versionadas e publicadas no repositório. A execução no Portainer e no banco oficial é feita pelo responsável; os testes automatizados descritos aqui usam somente bases fictícias locais. A previsão informada é segunda-feira, **26/10/2026**.

Antes da liberação: executar o pipeline Docker/MySQL, restaurar uma cópia do banco em homologação, conferir as pendências do preflight, validar o fluxo com os operadores e testar imagem/áudio no navegador físico da TV. Testes locais com PHP e MariaDB não substituem essa etapa.

## Mudanças que a equipe precisa conhecer

- Exclusão de pautas passa a cancelar somente agendamentos ainda não confirmados; histórico não é apagado. Atendimentos iniciados e concluídos não são remanejados pela operação comum.
- Check-in somente no próprio dia, em estado agendado; repetição preserva a senha. Recepção depende de pauta homologada.
- Uma sala e um profissional só podem ter um atendimento ativo. Iniciar vem antes de concluir. Ausência só antes de iniciar e devolve ao fim da fila, preservando eventos.
- Contas antigas com senha inicial conhecida exigem troca. Todas as sessões antigas precisam fazer login novamente após esta versão.
- Peritos pré-cadastrados pela planilha não têm mais senha derivada do CPF. O administrador atribui senha individual temporária em Usuários e a entrega por canal institucional. O primeiro acesso exige outra senha de 12 a 72 bytes.
- As pautas usam `perito_id`. Migração vincula somente nomes exatos sem ambiguidade; preflight lista a quantidade sem vínculo. Corrigir esses agendamentos antes do início da operação.
- Planilhas têm validação de cabeçalho, data real, CPF e conflitos; fórmulas não são aceitas. Há prévia antes de confirmar. Linhas anteriores ao dia atual são descartadas.
- A TV abre diretamente em `http(s)://SEU_HOST/painel.php`, sem código ou autorização, para uso exclusivo na intranet. Ative a voz e teste o som no início do expediente.
- A TV anuncia eventos em ordem. Após falha de rede, busca eventos posteriores ao cursor. Em reinício durante um anúncio, ele pode ser repetido; na primeira abertura em sessão nova, inicia nos eventos atuais, sem reproduzir todo o histórico.
- CSS é servido localmente. YouTube continua opcionalmente dependente de internet. Confirmar voz instalada e funcionamento de áudio no equipamento real.
- Recepção entra diretamente na pauta. A interface inclui busca com/sem acentos, botão para limpar, controles móveis, ajuda e preparação guiada da TV. Consulte [NAVEGADOR.md](NAVEGADOR.md) para orientar a equipe.

## Preparação do Compose

1. Copiar `.env.example` para `.env` e preencher credenciais atuais da instalação existente, nome do banco, volume e configuração HTTP/HTTPS. Em uma instalação nova, gerar senhas individuais fortes. Não commitar `.env`.
2. `SESSION_SECURE=1` na produção HTTPS. Usar `0` no acesso HTTP da intranet informado pelo responsável. O proxy deve encaminhar o host e preservar os cookies; não se confia automaticamente em `X-Forwarded-*` do cliente. Se houver proxy, configurar seus IPs exatos em `TRUSTED_PROXY_IPS` e fazê-lo sobrescrever `X-Real-IP` com a origem real. Isso preserva auditoria e limita tentativas por origem, sem tratar todos os operadores como um único IP.
3. Por padrão, a aplicação publica `0.0.0.0:8085`, para o acesso direto na intranet. Com proxy no host, ajustar `WEB_BIND=127.0.0.1`. Se o proxy estiver em outro container, configurar uma rede compartilhada somente para `web` e o proxy; não expor o MySQL.
4. Registrar o commit implantado e preservar a imagem anterior antes de reconstruir web/migrate.
5. O banco usa explicitamente o volume externo `sga-dti_db_data`. `DB_VOLUME_NAME` permite selecionar outro volume existente. Não substituir ou remover o volume da produção.

A imagem PHP está fixada por digest (PHP 8.4.27), as bibliotecas têm versões/lockfile e o MySQL usa a versão exata 8.0.46. Não alterar a versão do mecanismo do banco sobre o volume existente sem validar o caminho de upgrade. Para uma futura atualização do MySQL, preferir dump lógico e restauração em volume separado, ensaiados previamente.

## Sequência para banco novo ou homologação

Para homologação isolada, criar previamente um volume externo próprio e configurar `DB_VOLUME_NAME` para ele; nunca apontar o teste para `sga-dti_db_data`. O Compose atual não cria usuários em volumes existentes.

```bash
docker compose config --quiet
docker compose build --pull
docker compose up -d db
docker compose run --rm migrate
docker compose up -d web
docker compose ps
curl --fail http://127.0.0.1:8085/health.php
```

Banco novo exige `BOOTSTRAP_ADMIN_PASSWORD` de pelo menos 12 caracteres. O usuário inicial é `admin`, com troca obrigatória. Remover essa variável do ambiente depois do bootstrap. No ambiente legado, aplicação e migração usam root. Para usar conta restrita, criá-la previamente e validar seus privilégios; definir `RUNTIME_DB_USER` somente para uma conta dedicada, nunca root. O banco não tem porta publicada.

Se restaurar um banco antigo em volume novo, criar o usuário de aplicação e suas permissões antes de subir web. A inicialização automática do MySQL só cria usuário/senhas na primeira criação do volume; editar `.env` não altera usuários de um volume já inicializado.

## Troca de versão em banco existente

1. Ensaiar todo o procedimento em cópia isolada do banco e registrar contagens, tamanho e tempo da migração.
2. Preservar a imagem anterior, `.env` protegido e identificação dos volumes. Registrar a versão de origem do MySQL.
3. Pausar entrada de dados, encerrar atendimentos ativos e parar somente `web`. Se houver mudança de MySQL, usar implantação paralela e dump lógico validado; não trocar o mecanismo sobre o volume antigo por improviso.
4. Executar o backup e verificar uma restauração separada. O backup deve ficar também fora do servidor, cifrado e com acesso institucional restrito.
5. Construir a imagem da versão; executar `docker compose run --rm migrate` antes de subir web. A migração é aditiva e repetível, mas DDL MySQL não oferece rollback transacional: falha exige inspeção e repetição controlada.
6. Subir web; executar `docker compose exec -T web php scripts/preflight.php`. Corrigir vínculos, datas e duplicidades indicados em homologação antes do corte e conferir novamente na base oficial.
7. Conferir `/health.php`, login por perfil, homologação, check-in, chamada de duas salas, rechamada, início, conclusão, exportação e TV.
8. Liberar operadores e acompanhar logs, fila e anúncios na primeira manhã.

Não executar `docker compose down -v`: isso remove os volumes e os dados. O backup não faz parte da imagem ou do Git.

## Backup e restore de ensaio

```bash
bash scripts/backup.sh
```

O script produz dump consistente InnoDB compactado e checksum, sem colocar a senha na linha de comando. Só informa sucesso após concluir a cadeia e validar gzip. Agendar diariamente e antes de mudança; retenção e cópia externa devem seguir a política da instituição.

Para ensaiar restore, usar **outra implantação** com novo projeto/volume e banco vazio. Conferir checksum (`sha256sum -c ARQUIVO.sha256`), validar gzip, importar o SQL via `docker compose exec -T db ... mysql` nesse ambiente separado, rodar migração/preflight e comparar contagens. Nunca testar restore sobre a produção. Restaurar somente código não recupera dados.

## Retorno da versão

Se houver falha antes da liberação aos usuários: parar web, manter o banco e seus volumes intactos, coletar logs com o ID de referência e voltar a imagem anterior, desde que sua compatibilidade com as colunas aditivas tenha sido ensaiada. Em mudança do MySQL, retornar o tráfego para a implantação antiga preservada.

Se houver gravações após a liberação, não restaurar automaticamente um dump antigo: isso perde o trabalho posterior. Avaliar conciliação dos eventos e atendimentos com a equipe responsável. Registrar a decisão e a janela de dados envolvida.

## Validação automatizada

O workflow `.github/workflows/validate.yml` constrói a imagem, usa MySQL 8.4 descartável, executa migração duas vezes, lint PHP, testes de integração, concorrência, importador, cliente da TV, HTTP e bloqueio de arquivos privados pelo Apache. A execução em GitHub depende de enviar o código e habilitar Actions; consultar o resultado da execução no GitHub Actions após o push.

`tests/integration.php` apaga dados **somente** se `SGA_TEST=1` e `DB_NAME=sga_test`. Não usar a base oficial em testes. Os dados e credenciais de fixture são exclusivos de ambiente descartável.

## Melhorias sugeridas depois da estabilização

1. Monitorar healthcheck, idade da última atualização da TV, erros PHP, tempo de resposta e execução/idade dos backups; definir responsáveis pelos alertas.
2. Integrar autenticação institucional/SSO e MFA para administração, conforme infraestrutura disponível.
3. Homologar acessibilidade e contraste com teclado, leitor de tela, zoom e TV; a entrega adiciona labels, foco e navegação de modais, mas não certifica conformidade.
4. Criar relatório de absenteísmo e tempos por etapa, com indicadores definidos pela operação e eventos preservados.
5. Definir retenção/minimização de nomes e documentos e os perfis autorizados a consultar cada período, com a governança da JFAL.
6. Planejar revisão de pauta importada com comparação visual de antes/depois e tratamento assistido de vínculos ambíguos. O sistema rejeita conflitos, sem corrigir nomes por adivinhação.

Essas evoluções devem ser priorizadas após a homologação da correção, evitando acrescentar mudanças de regra na véspera da entrada em produção.
