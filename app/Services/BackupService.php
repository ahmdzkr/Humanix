<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;

/**
 * Backup & restore: dump database MySQL (gzip) + file privat (storage/app/private, zip),
 * dengan manifest berisi checksum SHA-256 agar restore menolak file yang rusak/berubah.
 *
 * Kredensial database diberikan ke mysqldump/mysql lewat file opsi sementara (bukan argumen
 * baris perintah) supaya tidak terlihat di daftar proses.
 */
class BackupService
{
    /** @return array{path: string, database_bytes: int, files_bytes: int, files_count: int} */
    public function backup(): array
    {
        $name = CarbonImmutable::now(config('app.display_timezone'))->format('Ymd-His');
        $dir = $this->root().DIRECTORY_SEPARATOR.$name;
        File::ensureDirectoryExists($dir);

        try {
            $sqlPath = $dir.DIRECTORY_SEPARATOR.'database.sql.gz';
            $this->dumpDatabase($sqlPath);

            $zipPath = $dir.DIRECTORY_SEPARATOR.'private-files.zip';
            $filesCount = $this->zipPrivateFiles($zipPath);

            $manifest = [
                'created_at' => CarbonImmutable::now()->toIso8601String(),
                'app' => config('app.name'),
                'database' => $this->connection()['database'],
                'files' => [
                    'database.sql.gz' => hash_file('sha256', $sqlPath),
                    'private-files.zip' => hash_file('sha256', $zipPath),
                ],
                'private_files_count' => $filesCount,
            ];
            File::put($dir.DIRECTORY_SEPARATOR.'manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } catch (\Throwable $e) {
            // Backup setengah jadi tidak boleh terlihat seperti backup yang sah.
            File::deleteDirectory($dir);

            throw $e;
        }

        return [
            'path' => $dir,
            'database_bytes' => filesize($sqlPath),
            'files_bytes' => filesize($zipPath),
            'files_count' => $filesCount,
        ];
    }

    /**
     * Pulihkan database dan file privat dari satu folder backup.
     *
     * @return array{database: string, files_count: int}
     */
    public function restore(string $name): array
    {
        $dir = $this->resolve($name);
        $manifest = json_decode((string) @file_get_contents($dir.DIRECTORY_SEPARATOR.'manifest.json'), true);

        if (! is_array($manifest) || ! isset($manifest['files'])) {
            throw new RuntimeException("manifest.json tidak ditemukan atau rusak di {$dir}.");
        }

        foreach ($manifest['files'] as $file => $hash) {
            $path = $dir.DIRECTORY_SEPARATOR.$file;

            if (! is_file($path) || hash_file('sha256', $path) !== $hash) {
                throw new RuntimeException("Checksum {$file} tidak cocok: file backup rusak atau diubah. Restore dibatalkan.");
            }
        }

        $this->restoreDatabase($dir.DIRECTORY_SEPARATOR.'database.sql.gz');
        $count = $this->restorePrivateFiles($dir.DIRECTORY_SEPARATOR.'private-files.zip');

        return ['database' => (string) $this->connection()['database'], 'files_count' => $count];
    }

    /** Hapus backup yang lebih tua dari batas simpan. @return list<string> nama folder yang dihapus */
    public function prune(?int $keepDays = null): array
    {
        $limit = CarbonImmutable::now()->subDays($keepDays ?? (int) config('backup.keep_days'));
        $deleted = [];

        foreach ($this->list() as $backup) {
            if (CarbonImmutable::parse($backup['created_at'])->lt($limit)) {
                File::deleteDirectory($backup['path']);
                $deleted[] = $backup['name'];
            }
        }

        return $deleted;
    }

    /** @return list<array{name: string, path: string, created_at: string, bytes: int}> terbaru dulu */
    public function list(): array
    {
        if (! is_dir($this->root())) {
            return [];
        }

        return collect(File::directories($this->root()))
            ->filter(fn ($dir) => is_file($dir.DIRECTORY_SEPARATOR.'manifest.json'))
            ->map(function ($dir) {
                $manifest = json_decode((string) file_get_contents($dir.DIRECTORY_SEPARATOR.'manifest.json'), true);

                return [
                    'name' => basename($dir),
                    'path' => $dir,
                    'created_at' => $manifest['created_at'] ?? '',
                    'bytes' => collect(File::files($dir))->sum(fn ($f) => $f->getSize()),
                ];
            })
            ->sortByDesc('name')->values()->all();
    }

    /** Konfigurasi koneksi database yang dibackup (default: koneksi utama aplikasi). @return array<string, mixed> */
    public function connection(): array
    {
        return (array) config('database.connections.'.(config('backup.connection') ?: config('database.default')));
    }

    public function root(): string
    {
        return rtrim((string) config('backup.path'), '/\\');
    }

    private function resolve(string $name): string
    {
        // Hanya nama folder di dalam root backup (cegah path traversal).
        if (! preg_match('/^[0-9]{8}-[0-9]{6}$/', $name)) {
            throw new RuntimeException('Nama backup tidak valid. Gunakan nama folder seperti 20261011-010000 (lihat humanix:backup --list).');
        }

        $dir = $this->root().DIRECTORY_SEPARATOR.$name;

        if (! is_dir($dir)) {
            throw new RuntimeException("Backup {$name} tidak ditemukan di {$this->root()}.");
        }

        return $dir;
    }

    private function dumpDatabase(string $target): void
    {
        $options = $this->credentialsFile();

        try {
            $process = new Process([
                config('backup.mysqldump'), "--defaults-extra-file={$options}",
                '--single-transaction', '--routines', '--triggers', '--no-tablespaces', '--default-character-set=utf8mb4',
                (string) $this->connection()['database'],
            ]);
            $process->setTimeout(3600);

            $gz = gzopen($target, 'wb6');
            $errors = '';
            $process->run(function ($type, $buffer) use ($gz, &$errors) {
                $type === Process::OUT ? gzwrite($gz, $buffer) : $errors .= $buffer;
            });
            gzclose($gz);

            if (! $process->isSuccessful()) {
                throw new RuntimeException('mysqldump gagal: '.trim($errors ?: $process->getErrorOutput()));
            }
        } finally {
            @unlink($options);
        }
    }

    private function restoreDatabase(string $source): void
    {
        $options = $this->credentialsFile();
        $sqlFile = tempnam(sys_get_temp_dir(), 'humanix-restore-');

        try {
            $in = gzopen($source, 'rb');
            $out = fopen($sqlFile, 'wb');

            while (! gzeof($in)) {
                fwrite($out, gzread($in, 1024 * 1024));
            }

            gzclose($in);
            fclose($out);

            $process = new Process([
                config('backup.mysql'), "--defaults-extra-file={$options}", '--default-character-set=utf8mb4',
                (string) $this->connection()['database'],
            ]);
            $process->setTimeout(3600);
            $process->setInput(fopen($sqlFile, 'rb'));
            $process->run();

            if (! $process->isSuccessful()) {
                throw new RuntimeException('Restore database gagal: '.trim($process->getErrorOutput()));
            }
        } finally {
            @unlink($options);
            @unlink($sqlFile);
        }
    }

    private function zipPrivateFiles(string $target): int
    {
        $source = (string) config('filesystems.disks.local.root');
        $zip = new ZipArchive;

        if ($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Tidak bisa membuat {$target}.");
        }

        $count = 0;

        if (is_dir($source)) {
            foreach (File::allFiles($source, true) as $file) {
                $zip->addFile($file->getPathname(), str_replace('\\', '/', $file->getRelativePathname()));
                $count++;
            }
        }

        // Arsip kosong tetap ditulis agar struktur backup selalu lengkap.
        if ($count === 0) {
            $zip->addFromString('.keep', '');
        }

        $zip->close();

        return $count;
    }

    private function restorePrivateFiles(string $source): int
    {
        $target = (string) config('filesystems.disks.local.root');
        $staging = $target.'.restore-'.uniqid();
        $zip = new ZipArchive;

        if ($zip->open($source) !== true) {
            throw new RuntimeException('Arsip file privat tidak bisa dibuka.');
        }

        // Tolak entri berbahaya (path absolut / ..) sebelum mengekstrak.
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = (string) $zip->getNameIndex($i);

            if (str_contains($entry, '..') || str_starts_with($entry, '/') || preg_match('/^[A-Za-z]:/', $entry)) {
                $zip->close();

                throw new RuntimeException("Entri arsip tidak aman: {$entry}");
            }
        }

        $zip->extractTo($staging);
        $count = $zip->numFiles - ($zip->locateName('.keep') !== false ? 1 : 0);
        $zip->close();
        @unlink($staging.DIRECTORY_SEPARATOR.'.keep');

        // Ganti folder lama setelah ekstraksi berhasil; folder lama disimpan sementara lalu dihapus.
        $old = $target.'.old-'.uniqid();

        if (is_dir($target)) {
            File::moveDirectory($target, $old);
        }

        File::moveDirectory($staging, $target);
        File::deleteDirectory($old);

        return $count;
    }

    /** File opsi MySQL sementara berisi kredensial (hanya bisa dibaca pemilik proses). */
    private function credentialsFile(): string
    {
        $c = $this->connection();
        $path = tempnam(sys_get_temp_dir(), 'humanix-my-');
        $escape = fn ($v) => str_replace(['\\', '"'], ['\\\\', '\\"'], (string) $v);

        $lines = ['[client]', 'user="'.$escape($c['username']).'"', 'password="'.$escape($c['password']).'"'];

        if (! empty($c['unix_socket'])) {
            $lines[] = 'socket="'.$escape($c['unix_socket']).'"';
        } else {
            $lines[] = 'host="'.$escape($c['host']).'"';
            $lines[] = 'port='.(int) $c['port'];
        }

        file_put_contents($path, implode(PHP_EOL, $lines).PHP_EOL);
        @chmod($path, 0600);

        return $path;
    }
}
