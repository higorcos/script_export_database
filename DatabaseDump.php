<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use ZipArchive;

class DatabaseDump extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:database-dump';
    protected $description = 'Gera SQL Dump customizado (estrutura + dados filtrados)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $customTables = config('exportTables.custom_tables');
        $database = DB::getDatabaseName();

        $timestamp = now()->format('Y-m-d_H-i-s');
        $fileName = "backup_custom_{$timestamp}.sql";
        $path = storage_path("app/public/backup/{$fileName}");
        // Caminho completo do ZIP (trocamos .sql por .zip)
        $zipPath = str_replace('.sql', '.zip', $path);
        // Pasta onde os arquivos ficam
        $directory = dirname($path);

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $handle = fopen($path, 'w');
        fwrite($handle, "SET foreign_key_checks = 0;\n\n");

        foreach ($customTables as $table => $allowedColumns) {

            $this->info("Processando tabela: {$table}");

            // ==========================
            // 1 BUSCAR COLUNAS
            // ==========================
            $columns = DB::table('information_schema.COLUMNS')
                ->where('TABLE_SCHEMA', $database)
                ->where('TABLE_NAME', $table)
                ->whereIn('COLUMN_NAME', $allowedColumns)
                ->orderBy('ORDINAL_POSITION')
                ->get();

            if ($columns->isEmpty()) {
                $this->warn("Nenhuma coluna encontrada para {$table}");
                continue;
            }

            $createParts = [];

            foreach ($columns as $column) {

                $line = "`{$column->COLUMN_NAME}` {$column->COLUMN_TYPE}";

                if ($column->IS_NULLABLE === 'NO') {
                    $line .= " NOT NULL";
                }

                if (!is_null($column->COLUMN_DEFAULT)) {
                    $default = addslashes($column->COLUMN_DEFAULT);
                    $line .= " DEFAULT '{$default}'";
                }

                if ($column->EXTRA) {
                    $line .= " {$column->EXTRA}";
                }

                $createParts[] = $line;
            }

            // ==========================
            // 2 BUSCAR ÍNDICES
            // ==========================
            $indexes = DB::table('information_schema.STATISTICS')
                ->where('TABLE_SCHEMA', $database)
                ->where('TABLE_NAME', $table)
                ->orderBy('SEQ_IN_INDEX')
                ->get()
                ->groupBy('INDEX_NAME');

            foreach ($indexes as $indexName => $indexCols) {

                $cols = $indexCols->pluck('COLUMN_NAME')->toArray();

                // Só inclui índice se todas as colunas existirem no filtro
                if (count(array_diff($cols, $allowedColumns)) === 0) {

                    $colList = collect($cols)->map(fn($c) => "`$c`")->implode(',');

                    if ($indexName === 'PRIMARY') {
                        $createParts[] = "PRIMARY KEY ({$colList})";
                    } else {
                        $createParts[] = "KEY `{$indexName}` ({$colList})";
                    }
                }
            }

            // ==========================
            // 3 ESCREVER CREATE TABLE
            // ==========================
            fwrite($handle, "DROP TABLE IF EXISTS `$table`;\n");
            fwrite($handle, "CREATE TABLE `$table` (\n");
            fwrite($handle, "  " . implode(",\n  ", $createParts));
            fwrite($handle, "\n) ENGINE=InnoDB;\n\n");

            // ==========================
            // 4 DADOS (USANDO CURSOR)
            // ==========================
            $columnList = collect($allowedColumns)
                ->map(fn($c) => "`$c`")
                ->implode(',');

            foreach (DB::table($table)->select($allowedColumns)->cursor() as $row) {

                $values = array_map(function ($value) {
                    if (is_null($value)) return 'NULL';
                    return "'" . addslashes($value) . "'";
                }, (array)$row);

                $sql = "INSERT INTO `$table` ($columnList) VALUES (" . implode(',', $values) . ");\n";
                fwrite($handle, $sql);
            }

            fwrite($handle, "\n\n");
        }

        fwrite($handle, "SET foreign_key_checks = 1;\n");
        fclose($handle);

        // Agora chamamos o ZIP
        $this->info("Compactando arquivo...");

        // Gera ZIP
        $zipCreated = $this->createZip($directory, $zipPath);

        if (!$zipCreated) {
            $this->error("Erro ao gerar ZIP. Backup abortado.");
            File::delete($path);
            return 1;
        }

        $currentDir = storage_path('app/public/backup');
        $oldDir = storage_path('app/public/backupsOld');

        // 1 Move apenas o zip antigo (se existir)
        $this->moveCurrentBackupToOld($currentDir, $oldDir);

        // 2 Agora gera o ZIP novo
        $this->info("Compactando arquivo...");

        $zipCreated = $this->createZip($directory, $zipPath);

        if (!$zipCreated) {
            $this->error("Erro ao gerar ZIP. Backup abortado.");
            File::delete($path);
            return 1;
        }

        // 3 Limita backupsOld para 7 arquivos
        $this->limitOldBackups($oldDir, 7);

        // 4 Remove .sql
        File::delete($path);
        $this->clearSqlFiles($currentDir);

        $this->info("✓ Backup finalizado com sucesso!");
    }

    /**
     * Compacta os arquivos da pasta
     */
    private function createZip($folder, $zipPath): bool
    {
        $zip = new \ZipArchive;

        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== TRUE) {
            return false;
        }

        foreach (File::files($folder) as $file) {
            if ($file->getExtension() === 'sql') {
                $zip->addFile($file->getRealPath(), $file->getFilename());
            }
        }

        $zip->close();
        return true;
    }

    private function moveCurrentBackupToOld(string $currentDir, string $oldDir)
    {
        if (!is_dir($oldDir)) {
            mkdir($oldDir, 0755, true);
        }

        $existingZip = collect(File::files($currentDir))
            ->first(fn($file) => $file->getExtension() === 'zip');

        if ($existingZip) {
            $newPath = $oldDir . '/' . $existingZip->getFilename();
            File::move($existingZip->getRealPath(), $newPath);
        }
    }

    private function limitOldBackups(string $oldDir, int $limit = 7)
    {
        $files = collect(File::files($oldDir))
            ->filter(fn($f) => $f->getExtension() === 'zip')
            ->sortByDesc(fn($f) => $f->getMTime());

        if ($files->count() > $limit) {
            $files->slice($limit)->each(function ($file) {
                File::delete($file->getRealPath());
            });
        }
    }

    private function clearSqlFiles(string $directory)
    {
        collect(File::files($directory))
            ->filter(fn($file) => $file->getExtension() === 'sql')
            ->each(fn($file) => File::delete($file->getRealPath()));
    }
}
