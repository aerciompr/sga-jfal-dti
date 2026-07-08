# Documentação Técnica do Sistema de Gerenciamento de Atendimentos (SGA)
### Justiça Federal de Alagoas (JFAL)

Este documento contém a especificação técnica, arquitetura de software, estrutura de banco de dados, matriz de permissões e detalhes operacionais do SGA.

---

## 1. Visão Geral e Arquitetura do Sistema

O **SGA** é um sistema desenvolvido sob a arquitetura **PHP Puro (estruturado)** com banco de dados **MySQL/MariaDB**, concebido para gerenciar a fila de atendimentos e perícias médicas presenciais no âmbito da Justiça Federal.

### Stack Tecnológica:
* **Backend**: PHP 8.x (utilizando PDO para persistência de dados segura e blindagem contra SQL Injection).
* **Banco de Dados**: MySQL / MariaDB (gerenciado tipicamente via XAMPP local).
* **Frontend**: HTML5, JavaScript Puro (ES6) para AJAX/Long Polling e **Tailwind CSS** (carregado via CDN em tempo de execução).
* **Temas**: CSS Dinâmico (Light Mode da Justiça Federal alternável com o Tema Escuro Moderno via Cookie).
* **APIs**: Integração do backend com a API pública de Nomes do IBGE para auxílio ortográfico em tempo real.

---

## 2. Estrutura de Arquivos do Projeto

O sistema é modularizado de forma simples por arquivos de script na raiz do diretório `C:\\sga`:

| Arquivo | Função / Responsabilidade |
| :--- | :--- |
| **`config.php`** | Armazena variáveis de ambiente globais e dados de conexão ao banco de dados (HOST, USER, PASS, DBNAME). |
| **`banco.php`** | Centraliza a conexão PDO e executa migrações automáticas de tabelas, além de conter a inteligência de processamento de nomes. |
| **`index.php`** | Controla o fluxo de Login e Autenticação de sessões (`session_start`). |
| **`logout.php`** | Destrói a sessão do usuário e redireciona para a tela de login. |
| **`dashboard.php`** | Painel Central (Main Hub). Renderiza os cards de atalhos e menus com base na permissão do usuário logado. |
| **`recepcao.php`** | Módulo de Recepção. Gerencia upload de pauta (Excel), cadastro manual de periciados, confirmação de chegada na pauta do dia e limpeza de dados. |
| **`atendente.php`** | Módulo do Perito. Painel onde o perito escolhe sua sala física, visualiza os pacientes presentes e realiza/chama os atendimentos. |
| **`salas.php`** | Módulo de Salas. Cadastro e remoção das salas físicas de perícia. |
| **`usuarios.php`** | Módulo de Perfis. Criação, edição e exclusão das contas de usuário e atribuição de cargos (`admin`, `recepcao`, `perito`). |
| **`auditoria.php`** | Histórico e Auditoria. Permite rastrear chamadas anteriores, exportar relatórios em CSV e filtrar tempos de espera e atendimento. |
| **`painel.php`** | Painel da TV de Chamadas. Fica na recepção chamando senhas com alertas sonoros e vídeo do YouTube em segundo plano. |
| **`api_senhas.php`** | Endpoint consumido via AJAX pelo painel de chamadas para verificar senhas ativas chamadas na TV em tempo real. |
| **`api_senhas_recentes.php`**| Endpoint para recuperar o histórico recente de senhas chamadas para a TV. |
| **`tema.js`** | Script de injeção e persistência do tema (Light Mode da Justiça Federal vs. Tema Original Escuro). |
| **`gerenciar_pauta.php`**| Módulo de Gerenciamento de Pauta (Exclusivo Admin). Centraliza a importação da pauta (Excel), o remanejamento/transferência de datas e peritos em lote, e a conferência/confirmação rápida de presenças individuais ou em massa. |

---

## 3. Arquitetura de Dados (Banco de Dados)

O sistema opera com 5 tabelas no banco de dados MySQL (`justica_senhas`):

### Diagrama de Relacionamento de Tabelas
* **`atendimentos`**: Tabela principal.
  * Armazena os agendamentos e o status de cada atendimento (`agendado`, `fila`, `chamado`, `atendimento`, `finalizado`, `cancelado`).
  * `senha`: Senha sequencial de chamada gerada automaticamente no dia (ex: `A001`).
  * `data_pauta`: Permite isolar os agendamentos de cada dia, protegendo a integridade histórica.
  * Colunas: `id`, `processo`, `chegada_em`, `perito`, `data_pauta`, `cpf`, `nome`, `chamado_em`, `sala`, `status`, etc.
* **`usuarios`**: Contas de acesso do sistema.
  * `password`: Criptografada via `password_hash()` no padrão nativo do PHP.
  * `role`: Cargo do usuário (`admin` = Administrador / Validador, `recepcao` = Operador da Recepção, `perito` = Perito Médico).
  * `cpf`: CPF do usuário cadastrado, utilizado para login único e auto-cadastro.
  * **Auto-cadastro de Peritos**: Durante a importação de planilhas Excel na recepção, o sistema extrai a coluna `CPF Perito` e pré-cadastra automaticamente os médicos peritos inéditos na base com o cargo `perito`. O CPF (apenas dígitos) é definido como username e os 6 primeiros dígitos como senha padrão inicial.
* **`salas`**: Lista de salas físicas (ex: "Sala 1", "Sala de Perícia A").
* **`salas_peritos`**: Armazena em cache a última sala vinculada a um perito, evitando que ele precise selecionar a sala toda vez que logar.
* **`nomes_validados`**: Tabela de cache de palavras e nomes checados na API do IBGE, acelerando a importação do Excel.

---

## 4. Matriz de Permissões e Segurança de Níveis de Acesso

O sistema possui duas camadas de validação (Restrita na interface e Bloqueada no backend) para separar os perfis de **Administrador / Validador** e **Operador da Recepção (Restrito)**:

### 1. Perfil Validador (`admin`):
* Acesso completo à visualização de pautas futuras e passadas.
* Permissão total de importação de arquivos Excel (.xlsx) e cadastros manuais.
* Edição e remoção de registros e limpeza geral da pauta.

### 2. Perfil Recepção (`recepcao`):
* **Filtro de Data**: Consegue consultar agendamentos futuros/passados, porém **só pode realizar ações (Confirmar Chegada) na pauta do dia corrente**.
* **Travas de UI (Frontend)**:
  * Painel de upload do Excel, formulário de cadastro manual e controle do vídeo do YouTube são ocultados e a tabela de pauta se expande a 100% da tela.
  * Botões de Exclusão individual, exclusão em lote e limpeza de pauta são removidos da interface.
* **Travas de Segurança (Backend em `recepcao.php`)**:
  * Qualquer requisição direta (POST) tentando cadastrar periciados, importar pautas, apagar ou alterar registros por um usuário com a role `recepcao` é abortada imediatamente no PHP com resposta de status `Acesso Negado (403)`.
  * A confirmação de presença (em lote ou única) valida se a data do agendamento é idêntica à data de hoje no servidor.

---

## 5. Corretor Ortográfico Inteligente de Nomes (IBGE + Heurística)

Devido às quebras de linha encontradas nas pautas oficiais de perícia, os nomes de periciados e peritos frequentemente sofrem quebras de sílabas e inserção de espaços indevidos (ex: `EDUARD O` em vez de `EDUARDO` ou `SEVERI NA` em vez de `SEVERINA`). 

O SGA implementa um algoritmo automático de tratamento contido em `banco.php` (`smartCorrectName` e `checkNomeIBGE`):

1. **API do IBGE**: O sistema consulta a API de Nomes do Censo do IBGE (`https://servicodados.ibge.gov.br`) para verificar a probabilidade de uma palavra unificada ser um nome próprio de fato.
2. **Banco de Cache Local (`nomes_validados`)**: Para evitar latência de rede no upload de planilhas com centenas de linhas, as consultas do IBGE são gravadas em cache local. O sistema só consulta a API externa se a palavra for inédita.
3. **Algoritmo Heurístico de Apoio**: Caso a API esteja fora do ar ou o resultado seja inconclusivo, o sistema mescla as palavras com base em heurísticas de comprimento (ex: mescla fragmentos de 1 ou 2 letras nas pontas das palavras caso não pertençam a preposições consagradas como "DE", "DOS", etc.).

---

## 6. Sistema de Temas Alternáveis (Original vs. Justiça Federal)

O tema do sistema é mutável em tempo real sem qualquer delay de carregamento através do arquivo [C:\\sga\\tema.js](file:///C:/sga/tema.js):

### Temas Disponíveis:
* **Tema Original (Moderno Escuro)**: Interface com tons escuros (cinza profundo `#090f1d`), azul vibrante e verde esmeralda.
* **Tema Justiça Federal (Light Mode)**: 
  * Fundo claro institucional (`#f3f4f6`), cards e cabeçalhos em branco puro (`#ffffff`), com textos e dados em cinza escuro/preto de alto contraste.
  * Sobrescrita total das cores padrão do Tailwind para o **Azul Oficial da Justiça Federal** (`#002F6C`) em botões e bordas de preenchimento, e **Verde Oficial da JF** (`#007A33`) em botões de sucesso e confirmação.
  * Remoção completa de cores amarelas ou laranjas por tons sóbrios corporativos (Cinza e Azul JF Suave).

### Mecanismo Técnico:
1. O JS síncrono injeta uma tag `<style>` no `<head>` com os overrides de classes de layout (`body.theme-jf .bg-gray-900 { background-color: #ffffff !important; }`, etc.) quando a classe `theme-jf` é ativada no `<body>`.
2. A preferência do tema é salva localmente e enviada ao servidor através do cookie `tema_sga`, persistindo mesmo quando a página é atualizada ou recarregada.
3. Um botão reativo (**🎨 Cores JF** ou **🎨 Tema Original**) é injetado dinamicamente no canto superior direito de todas as telas contendo a classe `.flex.items-center.space-x-4`.

---

## 7. Instruções de Instalação e Execução

1. **Requisitos**:
   * Servidor PHP 8.x + MySQL (recomenda-se XAMPP no Windows).
   * Banco de dados MySQL criado com o nome `justica_senhas`.
2. **Instalação**:
   * Copie a pasta do projeto para a raiz do servidor web (`C:\\xampp\\htdocs\\sga`).
   * Configure os dados de acesso ao banco de dados no arquivo `config.php`.
   * Acesse `http://localhost/sga/` no navegador.
   * O arquivo `banco.php` rodará as migrações automáticas de tabelas na primeira execução de qualquer página.
