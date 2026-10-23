<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Number;

/**
 * Backup harian: database + file privat, lalu hapus backup yang melewati batas simpan.
 */
class BackupCommand extends Command
{
    protected $signature = 'humanix:backup {--list : Tampilkan daftar backup yang ada} {--no-prune : Jangan hapus backup lama}';

    protected $description = 'Backup database MySQL dan file privat (storage/app/private)';

    public function handle(BackupService $backups): int
    {
        if ($this->option('list')) {
            $rows = collect($backups->list())->map(fn ($b) => [$b['name'], $b['created_at'], Number::fileSize($b['bytes'], 1)]);
            $rows->isEmpty()
                ? $this->warn("Belum ada backup di {$backups->root()}.")
                : $this->table(['Nama', 'Dibuat', 'Ukuran'], $rows->all());

            return self::SUCCESS;
        }

        try {
            $result = $backups->backup();
        } catch (\Throwable $e) {
            // Gagal harus terlihat gagal (exit code ≠ 0) agar cron/monitoring bisa memberi tahu.
            $this->error('Backup GAGAL: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Backup selesai: {$result['path']}");
        $this->line('  Database     : '.Number::fileSize($result['database_bytes'], 1));
        $this->line("  File privat  : {$result['files_count']} file (".Number::fileSize($result['files_bytes'], 1).')');

        if (! $this->option('no-prune')) {
            $deleted = $backups->prune();
            $this->line('  Dihapus (lama): '.($deleted === [] ? '-' : implode(', ', $deleted)));
        }

        return self::SUCCESS;
    }
}
