<?php

use Illuminate\Support\Facades\Schedule;

// Tandai karyawan terjadwal yang tidak absen. Dijalankan setiap malam (WIB),
// setelah batas absen pulang default terlewati. Butuh cron `schedule:run` di server.
Schedule::command('humanix:mark-absent')
    ->dailyAt('23:55')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping();

// Pengingat penilaian kinerja yang belum diisi, setiap Senin pagi (WIB).
Schedule::command('humanix:remind-reviews')
    ->weeklyOn(1, '08:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping();

// Backup harian database + file privat (01:00 WIB); backup lama dihapus sesuai BACKUP_KEEP_DAYS.
Schedule::command('humanix:backup')
    ->dailyAt('01:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping();
