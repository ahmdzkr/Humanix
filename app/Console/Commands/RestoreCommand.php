<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

/**
 * Restore dari satu backup. MENIMPA database dan file privat saat ini, jadi wajib konfirmasi.
 */
class RestoreCommand extends Command
{
    protected $signature = 'humanix:restore {name : Nama folder backup, mis. 20261011-010000} {--force : Lewati konfirmasi}';

    protected $description = 'Pulihkan database dan file privat dari backup (menimpa data saat ini)';

    public function handle(BackupService $backups): int
    {
        $name = (string) $this->argument('name');
        $database = $backups->connection()['database'];

        if (! $this->option('force') && ! $this->confirm("Database \"{$database}\" dan file privat akan DITIMPA dengan backup {$name}. Lanjutkan?")) {
            $this->warn('Restore dibatalkan.');

            return self::FAILURE;
        }

        try {
            $result = $backups->restore($name);
        } catch (\Throwable $e) {
            $this->error('Restore GAGAL: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Restore selesai ke database \"{$result['database']}\" ({$result['files_count']} file privat).");
        $this->line('Jalankan "php artisan optimize:clear" lalu "php artisan queue:restart" setelah restore.');

        return self::SUCCESS;
    }
}
