<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use ZipArchive;
use Throwable;

class ExportDataToHtml extends Command
{
    /**
     * O nome e a assinatura do comando que você usará no terminal e no Schedule.
     */
    protected $signature = 'export:html';

    /**
     * A descrição do comando.
     */
    protected $description = 'Exporta tabelas gigantes para HTML via Stream e mantém os últimos 7 backups.';

    public function handle()
    {
        $disk = Storage::disk('public');
        $tempFolderName = 'exports_temp';
        $tempPath = storage_path("app/public/$tempFolderName");
        $zipFileName = 'backup_sistema_' . now()->format('Y-m-d_H-i-s') . '.zip';
        $zipFullPath = $disk->path($zipFileName);

        // 1. Definição das Tabelas e Colunas (Personalize aqui as 40 tabelas)
        $tabelas = [
            'vendas'   => ['id', 'produto_id', 'usuario_id', 'quantidade', 'data_venda'],
            'produtos' => ['id', 'nome', 'categoria', 'preco_venda'],
            'estoque'  => ['id', 'produto_id', 'quantidade_atual', 'localizacao_corredor'],
            // Adicione as outras 37 tabelas seguindo o padrão ['coluna1', 'coluna2']
        ];

        try {
            $this->info("- Iniciando exportação de alta performance...");

            // Limpa e recria diretório temporário
            File::deleteDirectory($tempPath);
            File::makeDirectory($tempPath, 0755, true);

            foreach ($tabelas as $nomeTabela => $colunas) {
                $this->line("Processing: $nomeTabela...");
                
                $filePath = "$tempPath/$nomeTabela.html";
                $fileHandle = fopen($filePath, 'w');

                // Escreve o Header do HTML inicial
                fwrite($fileHandle, $this->getHtmlHeader($nomeTabela));
                
                // Escreve o cabeçalho da tabela (TH)
                fwrite($fileHandle, "<thead><tr>");
                foreach ($colunas as $col) {
                    fwrite($fileHandle, "<th>" . strtoupper($col) . "</th>");
                }
                fwrite($fileHandle, "</tr></thead><tbody>");

                // 2. BUSCA EFICIENTE: Cursor não carrega a tabela toda na RAM
                DB::table($nomeTabela)
                    ->select($colunas)
                    ->orderBy('id') // Importante para o cursor ser estável
                    ->cursor()
                    ->each(function ($linha) use ($fileHandle) {
                        fwrite($fileHandle, "<tr>");
                        foreach ((array)$linha as $valor) {
                            fwrite($fileHandle, "<td>" . htmlspecialchars($valor ?? '') . "</td>");
                        }
                        fwrite($fileHandle, "</tr>");
                    });

                // Fecha as tags e o arquivo
                fwrite($fileHandle, "</tbody></table></body></html>");
                fclose($fileHandle);
                
                $this->info("- $nomeTabela exportada.");
            }

            // 3. COMPACTAÇÃO
            $this->info("- Criando arquivo ZIP...");
            if ($this->createZip($tempPath, $zipFullPath)) {
                
                // Só limpa a pasta temporária se o ZIP deu certo
                File::deleteDirectory($tempPath);

                // 4. ROTAÇÃO: Mantém apenas os 7 últimos
                $this->cleanupOldBackups($disk);

                $this->info("- Sucesso total! Arquivo gerado: $zipFileName");
            } else {
                throw new \Exception("Erro ao gerar o arquivo ZIP.");
            }

        } catch (Throwable $e) {
            $this->error("- Falha crítica: " . $e->getMessage());
            Log::error("Erro no Agendamento ExportDataToHtml: " . $e->getMessage());
            
            // Em caso de erro, mantemos o que já estava lá (não rodamos o cleanup)
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Gera o CSS e cabeçalho do HTML
     */
    private function getHtmlHeader($titulo)
    {
        return "<!DOCTYPE html><html lang='pt-br'><head><meta charset='UTF-8'><title>$titulo</title>
                <style>
                    body { font-family: Arial, sans-serif; margin: 20px; color: #333; }
                    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                    th, td { border: 1px solid #ccc; padding: 10px; text-align: left; font-size: 12px; }
                    th { background-color: #f4f4f4; position: sticky; top: 0; }
                    tr:nth-child(even) { background-color: #fafafa; }
                    h2 { color: #2c3e50; border-bottom: 2px solid #2c3e50; padding-bottom: 10px; }
                </style></head><body><h2>Relatório: $titulo</h2><table>";
    }

    /**
     * Cria o ZIP de forma segura
     */
    private function createZip($folderPath, $zipPath)
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            $files = File::files($folderPath);
            foreach ($files as $file) {
                $zip->addFile($file->getRealPath(), $file->getFilename());
            }
            return $zip->close();
        }
        return false;
    }

    /**
     * Remove backups antigos deixando apenas os 7 mais recentes
     */
    private function cleanupOldBackups($disk)
    {
        $allFiles = collect($disk->files())
            ->filter(fn($file) => str_starts_with($file, 'backup_sistema_') && str_ends_with($file, '.zip'))
            ->sort() // Ordena por data (nome do arquivo)
            ->values();

        if ($allFiles->count() > 7) {
            $filesToDelete = $allFiles->take($allFiles->count() - 7);
            foreach ($filesToDelete as $oldFile) {
                $disk->delete($oldFile);
                $this->line("- Backup antigo removido: $oldFile");
            }
        }
    }
}