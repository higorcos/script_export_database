import pandas as pd
from sqlalchemy import create_engine
import urllib.parse

# 1. Configuração (Mantenha seus dados que funcionaram)
usuario = "higor" # ou o usuário que você configurou
senha = "" 
host = "127.0.0.1"
porta = "3306"
banco = "sistema_vendas"

senha_escrita = urllib.parse.quote_plus(senha)
engine = create_engine(f"mysql+pymysql://{usuario}:{senha_escrita}@{host}:{porta}/{banco}")

# 2. Tabelas e Colunas
tabelas_para_exportar = ['vendas', 'produtos', 'estoque'] 
colunas_excluidas = {
    'vendas': ['valor_comissao', 'id_interno'],
    'produtos': ['custo_fornecedor']
}

def exportar_diario():
    for tabela in tabelas_para_exportar:
        try:
            print(f"Tentando exportar {tabela}...")
            
            # O Pandas lê a tabela. Se não existir, gera o ValueError
            df = pd.read_sql_table(tabela, engine)
            
            # Remove as colunas se estiverem no mapeamento
            if tabela in colunas_excluidas:
                df = df.drop(columns=colunas_excluidas[tabela], errors='ignore')
            
            # Salva o CSV
            nome_arquivo = f"export_{tabela}.csv"
            df.to_csv(nome_arquivo, index=False, encoding='utf-8')
            print(f"✅ Sucesso: {tabela} salvo como {nome_arquivo}.")
            
        except ValueError:
            print(f"⚠️ Aviso: A tabela '{tabela}' não existe no banco. Pulando...")
        except Exception as e:
            print(f"❌ Erro inesperado em '{tabela}': {e}")

if __name__ == "__main__":
    exportar_diario()
