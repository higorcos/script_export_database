#!/bin/bash

#Configurações de Conexão
USER="higor"
PASS=""
HOST="127.0.0.1"
DB="sistema_vendas"

#Pasta de destino
DESTINO="./exports_html"
mkdir -p $DESTINO

echo "Iniciando exportação para HTML..."

# ------Exportação de Tabelas Específicas (Selecionando apenas as colunas desejadas)

# Tabela: Vendas (Excluindo valor_comissao)
mysql -u$USER -p$PASS -h$HOST -H $DB -e \
"SELECT id, produto_id, usuario_id, quantidade, data_venda FROM vendas;" > "$DESTINO/vendas.html"
echo "- Vendas exportada."

# Tabela: Produtos (Excluindo custo_fornecedor)
mysql -u$USER -p$PASS -h$HOST -H $DB -e \
"SELECT id, nome, categoria, preco_venda FROM produtos;" > "$DESTINO/produtos.html"
echo "- Produtos exportada."

# Tabela: Estoque (Excluindo custo_unitario)
mysql -u$USER -p$PASS -h$HOST -H $DB -e \
"SELECT id, produto_id, quantidade_atual, localizacao_corredor FROM estoque;" > "$DESTINO/estoque.html"
echo "- Estoque exportada."

echo "Exportação concluída em $DESTINO"

### ZIPAR 
DATA_ATUAL=$(date +%Y-%m-%d)
NOME_ZIP="backup_tabelas_$DATA_ATUAL.zip"

echo "Compactando arquivos..."
zip -r "$NOME_ZIP" "$DESTINO"

if [ $? -eq 0 ]; then
    echo "- Sucesso! Arquivo $NOME_ZIP criado."
    # Opcional: remover a pasta original para limpar o discoecho "- Produtos exportada."

# Tabela: Estoque (Excluindo custo_unitario)
mysql -u$USER -p$PASS -h$HOST -H $DB -e \
"SELECT id, produto_id, quantidade_atual, localizacao_corredor FROM estoque;" > "$DESTINO/estoque.html"
echo "- Estoque exportada."

    # rm -rf "$DESTINO"
else
    echo "- Erro ao compactar a pasta."
fi
