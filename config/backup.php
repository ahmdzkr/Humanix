<?php

return [
    /*
    | Lokasi folder backup. Default di luar storage/app/private agar backup tidak ikut
    | membackup dirinya sendiri. Di produksi sebaiknya disalin juga ke lokasi lain (server/cloud).
    */
    'path' => env('BACKUP_PATH', storage_path('backups')),

    // Koneksi database yang dibackup (kosong = koneksi default aplikasi).
    'connection' => env('BACKUP_CONNECTION'),

    // Berapa hari backup lama disimpan sebelum dihapus otomatis.
    'keep_days' => (int) env('BACKUP_KEEP_DAYS', 14),

    // Lokasi biner MySQL bila tidak ada di PATH (mis. XAMPP: C:\xampp\mysql\bin\mysqldump.exe).
    'mysqldump' => env('BACKUP_MYSQLDUMP', 'mysqldump'),
    'mysql' => env('BACKUP_MYSQL', 'mysql'),
];
