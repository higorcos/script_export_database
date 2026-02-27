🚀 Database Export Tool 

Repositório para automação de exportação de dados do MySQL (sistema_vendas), com suporte à exclusão de colunas sensíveis e geração de relatórios compactados.

📋 Pré-requisitos (Linux/Ubuntu)

Antes de começar, certifique-se de ter o cliente MySQL e o utilitário zip instalados no sistema:

sudo apt update && sudo apt install mysql-client zip -y
🐍 1. Versão Python (dbExport.py)

Utiliza Pandas e SQLAlchemy para uma exportação limpa em .csv, permitindo remover colunas programaticamente.

🔧 Configurando o Ambiente (Virtualenv)

Siga estes passos para isolar as dependências:

1️⃣ Criar o ambiente virtual
python3 -m venv venv
2️⃣ Ativar o ambiente
source venv/bin/activate
3️⃣ Instalar dependências necessárias
pip install pandas sqlalchemy pymysql cryptography

Nota: A biblioteca cryptography é necessária para autenticação segura no MySQL 8.0+.

🏃 Como Executar
python3 dbExport.py
🐚 2. Versão Shell (dbExport.sh)

Focada em performance e geração de relatórios nativos em HTML.

⚙️ Diferenciais

Utiliza a flag -H do MySQL Client para criar tabelas HTML automaticamente.

Compacta os resultados em um arquivo .zip ao final da execução.

🏃 Como Executar
1️⃣ Dar permissão de execução
chmod +x dbExport.sh
2️⃣ Executar o script
./dbExport.sh
🛠️ Customização e Segurança
🔐 Exclusão de Colunas
🐍 Python

Edite o dicionário colunas_excluidas no script.

🐚 Shell

Edite a query SELECT, listando apenas as colunas desejadas:

SELECT id, nome FROM tabela;
📦 Resultado

Exportação em .csv (Python)

Relatórios em .html (Shell)

Arquivo final compactado em .zip