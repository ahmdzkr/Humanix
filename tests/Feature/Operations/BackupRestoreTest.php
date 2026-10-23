<?php

use App\Services\BackupService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/*
| Uji backup → ubah data → restore → data kembali, memakai database terpisah
| (humanix_backup_test) dan folder sementara agar tidak menyentuh data lain.
| Butuh biner mysqldump & mysql (PATH, atau BACKUP_MYSQLDUMP/BACKUP_MYSQL, atau XAMPP).
*/
function mysqlBinary(string $name): ?string
{
    foreach ([env('BACKUP_'.strtoupper($name)), $name, "C:\\xampp\\mysql\\bin\\{$name}.exe", "/usr/bin/{$name}"] as $candidate) {
        if (! $candidate) {
            continue;
        }

        $probe = new Process([$candidate, '--version']);
        $probe->run();

        if ($probe->isSuccessful()) {
            return $candidate;
        }
    }

    return null;
}

beforeEach(function () {
    $this->dump = mysqlBinary('mysqldump');
    $this->client = mysqlBinary('mysql');

    if (! $this->dump || ! $this->client) {
        $this->markTestSkipped('mysqldump/mysql tidak ditemukan.');
    }

    $this->tmp = sys_get_temp_dir().DIRECTORY_SEPARATOR.'humanix-backup-test-'.uniqid();
    File::ensureDirectoryExists($this->tmp.'/private/cv');
    File::put($this->tmp.'/private/cv/rizky.pdf', 'CV-ASLI');

    DB::statement('CREATE DATABASE IF NOT EXISTS humanix_backup_test');
    config([
        'database.connections.backup_test' => [...config('database.connections.mysql'), 'database' => 'humanix_backup_test'],
        'backup.connection' => 'backup_test',
        'backup.path' => $this->tmp.'/backups',
        'backup.mysqldump' => $this->dump,
        'backup.mysql' => $this->client,
        'filesystems.disks.local.root' => $this->tmp.'/private',
    ]);

    $db = DB::connection('backup_test');
    $db->statement('DROP TABLE IF EXISTS probe');
    $db->statement('CREATE TABLE probe (id INT PRIMARY KEY, name VARCHAR(50))');
    $db->table('probe')->insert(['id' => 1, 'name' => 'Rina Hartati']);
});

afterEach(function () {
    if (isset($this->tmp)) {
        DB::connection('backup_test')->statement('DROP DATABASE IF EXISTS humanix_backup_test');
        DB::purge('backup_test');
        File::deleteDirectory($this->tmp);
    }
});

test('backup lalu restore mengembalikan database dan file privat', function () {
    $backups = app(BackupService::class);
    $result = $backups->backup();
    $name = basename($result['path']);

    expect(File::exists("{$result['path']}/database.sql.gz"))->toBeTrue()
        ->and(File::exists("{$result['path']}/private-files.zip"))->toBeTrue()
        ->and($result['files_count'])->toBe(1);

    // Data berubah setelah backup (salah hapus, salah ubah, file tertimpa).
    $db = DB::connection('backup_test');
    $db->table('probe')->update(['name' => 'RUSAK']);
    $db->table('probe')->insert(['id' => 2, 'name' => 'Baris baru']);
    File::put($this->tmp.'/private/cv/rizky.pdf', 'TERTIMPA');
    File::put($this->tmp.'/private/sampah.txt', 'x');

    $this->artisan('humanix:restore', ['name' => $name, '--force' => true])->assertSuccessful();

    expect($db->table('probe')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all())->toBe([['id' => 1, 'name' => 'Rina Hartati']])
        ->and(File::get($this->tmp.'/private/cv/rizky.pdf'))->toBe('CV-ASLI')
        ->and(File::exists($this->tmp.'/private/sampah.txt'))->toBeFalse();
});

test('restore menolak backup yang rusak (checksum tidak cocok) tanpa mengubah data', function () {
    $result = app(BackupService::class)->backup();
    File::append("{$result['path']}/database.sql.gz", 'korup');
    DB::connection('backup_test')->table('probe')->update(['name' => 'Data terbaru']);

    $this->artisan('humanix:restore', ['name' => basename($result['path']), '--force' => true])
        ->expectsOutputToContain('Checksum database.sql.gz tidak cocok')
        ->assertFailed();

    expect(DB::connection('backup_test')->table('probe')->value('name'))->toBe('Data terbaru');
});

test('nama backup tidak valid ditolak (cegah path traversal) dan gagal backup tercatat gagal', function () {
    $this->artisan('humanix:restore', ['name' => '../../etc', '--force' => true])->assertFailed();

    config(['backup.mysqldump' => $this->tmp.'/tidak-ada-mysqldump']);
    $this->artisan('humanix:backup')->expectsOutputToContain('Backup GAGAL')->assertFailed();
    expect(app(BackupService::class)->list())->toBe([]); // backup setengah jadi dihapus
});

test('backup lama melewati batas simpan dihapus', function () {
    $backups = app(BackupService::class);
    $result = $backups->backup();
    $manifest = json_decode(File::get("{$result['path']}/manifest.json"), true);
    $manifest['created_at'] = now()->subDays(30)->toIso8601String();
    File::put("{$result['path']}/manifest.json", json_encode($manifest));

    expect($backups->prune(14))->toBe([basename($result['path'])])
        ->and($backups->list())->toBe([]);
});
