# Panduan Deployment Produksi HUMANIX

Panduan ini untuk server Linux (Ubuntu 22.04/24.04) dengan Nginx + PHP-FPM + MySQL. Contoh file
konfigurasi ada di folder [`deploy/`](../deploy). Ganti `humanix.perusahaan.co.id` dan
`/var/www/humanix` sesuai lingkungan Anda.

> Prinsip: rahasia hanya di `.env` server (tidak pernah di git), `APP_DEBUG=false`, HTTPS wajib,
> backup harian yang sudah diuji restore-nya, dan setiap rilis bisa dikembalikan (rollback).

---

## 1. Kebutuhan server

- PHP 8.2+ dengan PHP-FPM dan ekstensi `bcmath gd intl mbstring mysql zip xml curl fileinfo`
- MySQL 8.0+ (atau MariaDB 10.4+) beserta klien `mysqldump`/`mysql`
- Nginx, Composer 2, Node.js 20+ (hanya untuk build aset; bisa juga build di CI lalu salin `public/build`)
- Supervisor (queue worker) dan cron (scheduler)
- Sertifikat TLS (mis. Let's Encrypt / certbot)

```bash
sudo apt install nginx mysql-server supervisor php8.3-fpm php8.3-{bcmath,gd,intl,mbstring,mysql,zip,xml,curl}
```

## 2. Database produksi

```sql
CREATE DATABASE humanix CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'humanix'@'localhost' IDENTIFIED BY '<kata-sandi-kuat-acak>';
GRANT ALL PRIVILEGES ON humanix.* TO 'humanix'@'localhost';
FLUSH PRIVILEGES;
```

- Pengguna khusus aplikasi, bukan `root`; hanya bisa diakses dari `localhost` (atau IP server aplikasi).
- Port 3306 jangan dibuka ke internet.

## 3. Konfigurasi lingkungan (`.env`)

Mulai dari [`.env.production.example`](../.env.production.example) (tanpa rahasia), lalu isi di server:

```bash
cp .env.production.example .env
php artisan key:generate        # SEKALI saja; mengganti APP_KEY membuat sesi & data terenkripsi tidak terbaca
chmod 640 .env && chown www-data:www-data .env
```

Wajib:

| Kunci | Nilai produksi |
| --- | --- |
| `APP_ENV` | `production` (seeder demo & akun `password` **tidak** dibuat) |
| `APP_DEBUG` | `false` — galat tidak menampilkan stack trace; Debugbar tidak terpasang (`composer install --no-dev`) |
| `APP_URL` | `https://humanix.perusahaan.co.id` |
| `SESSION_SECURE_COOKIE` | `true` (cookie sesi hanya lewat HTTPS) |
| `SESSION_DRIVER` / `CACHE_STORE` / `QUEUE_CONNECTION` | `database` (atau `redis` bila tersedia) |
| `LOG_CHANNEL` / `LOG_LEVEL` | `daily` / `warning` |
| `MAIL_*` | Kredensial SMTP asli (lihat README bagian 9) |
| `BACKUP_PATH` / `BACKUP_KEEP_DAYS` | Lokasi backup (disarankan disk terpisah) / 14 |

**Manajemen rahasia**: `.env` hanya ada di server, hak akses `640`, tidak ikut backup kode/git.
Simpan salinan `APP_KEY` dan kata sandi DB/SMTP di password manager perusahaan. Ganti kata sandi
SMTP/DB bila ada staf yang keluar. Jangan menaruh rahasia di `.env.example`, kode, atau tiket.

## 4. Instalasi pertama

```bash
sudo mkdir -p /var/www/humanix && sudo chown $USER:www-data /var/www/humanix
git clone <url-repo> /var/www/humanix && cd /var/www/humanix
composer install --no-dev --optimize-autoloader
npm ci && npm run build
cp .env.production.example .env && php artisan key:generate   # lalu isi .env
php artisan migrate --force --seed       # data wajib saja (APP_ENV=production)
php artisan humanix:assign-role admin@perusahaan.co.id Administrator   # setelah akun dibuat
php artisan optimize
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R ug+rwX storage bootstrap/cache
```

Akun Administrator pertama: buat lewat `php artisan tinker` (`User::create([...])`) lalu beri peran
dengan `humanix:assign-role`; selanjutnya HRD membuat akun karyawan dari aplikasi (tautan atur kata
sandi dikirim lewat email).

**Storage**: semua file sensitif (foto, CV, lampiran, bukti bayar, slip) disimpan di
`storage/app/private` dan hanya diunduh lewat route berizin. `php artisan storage:link` **tidak
diperlukan** — aplikasi tidak menyajikan file dari disk publik. Jangan pernah membuat symlink ke
`storage/app/private`.

## 5. Web server & HTTPS

Contoh Nginx: [`deploy/nginx/humanix.conf`](../deploy/nginx/humanix.conf) — root ke `public/`,
HTTP dialihkan ke HTTPS, batas unggah 10 MB, file dotfile diblokir.

```bash
sudo cp deploy/nginx/humanix.conf /etc/nginx/sites-available/humanix
sudo ln -s /etc/nginx/sites-available/humanix /etc/nginx/sites-enabled/
sudo certbot --nginx -d humanix.perusahaan.co.id
sudo nginx -t && sudo systemctl reload nginx
```

Aplikasi mengirim header keamanan (nosniff, X-Frame-Options, Referrer-Policy, Permissions-Policy)
dan HSTS bila diakses lewat HTTPS. Bila di belakang load balancer/proxy, atur trusted proxies agar
`https` terdeteksi (Laravel: `$middleware->trustProxies(at: '*')` di `bootstrap/app.php`).

## 6. Optimasi Laravel

```bash
php artisan optimize        # cache config, route, view, event
php artisan optimize:clear  # bila ada masalah cache / setelah mengubah .env
```

Aktifkan OPcache di PHP-FPM (`opcache.enable=1`, `opcache.validate_timestamps=0` lalu reload FPM
setiap deploy).

## 7. Queue worker (Supervisor)

Email notifikasi dikirim lewat queue. Contoh: [`deploy/supervisor/humanix-worker.conf`](../deploy/supervisor/humanix-worker.conf)

```bash
sudo cp deploy/supervisor/humanix-worker.conf /etc/supervisor/conf.d/
sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl start humanix-worker:*
```

Setelah setiap deploy: `php artisan queue:restart` (worker memuat kode baru). Job yang gagal
3× tercatat di `failed_jobs` (`php artisan queue:failed`) dan statusnya "Gagal" di
**Audit Log → Pengiriman Email**.

## 8. Scheduler (cron)

```bash
sudo crontab -u www-data -e
# tambahkan isi deploy/cron/humanix:
* * * * * cd /var/www/humanix && php artisan schedule:run >> /dev/null 2>&1
```

Tugas: tandai tidak hadir (23:55 WIB), pengingat penilaian (Senin 08:00), backup (01:00).
Cek: `php artisan schedule:list`.

## 9. Logging & pemantauan

- `LOG_CHANNEL=daily` → `storage/logs/laravel-YYYY-MM-DD.log`, disimpan 14 hari (`LOG_DAILY_DAYS`).
- Pantau: galat 500 di log, `php artisan queue:failed`, hasil backup harian (perintah gagal keluar
  dengan kode ≠ 0 — arahkan output cron ke email/monitoring bila perlu), ruang disk `storage/backups`.
- Audit perubahan data oleh pengguna: menu **Audit Log** (Administrator).

## 10. Backup harian

`php artisan humanix:backup` (otomatis 01:00) membuat `database.sql.gz`, `private-files.zip`, dan
`manifest.json` (checksum SHA-256) di `BACKUP_PATH/<tanggal-jam>/`, lalu menghapus backup yang
lebih tua dari `BACKUP_KEEP_DAYS`.

**Salin ke luar server** (minimal harian), contoh:

```bash
rsync -a /var/backups/humanix/ backup@server-cadangan:/srv/humanix-backups/
```

`.env` tidak termasuk backup otomatis — simpan `APP_KEY` dan rahasia lain di password manager.

## 11. Uji restore (wajib, minimal sebulan sekali)

Backup yang belum pernah diuji restore-nya belum bisa diandalkan. Uji di server/DB staging:

```bash
# di server staging dengan .env menunjuk ke database staging
php artisan humanix:backup --list
php artisan humanix:restore 20261011-010000      # konfirmasi "yes"
php artisan optimize:clear && php artisan queue:restart
```

Lalu cek: login HRD, jumlah karyawan sama, slip gaji periode terakhir bisa dibuka (PDF), foto
karyawan & CV pelamar tampil. Catat tanggal & hasil uji.

Logika backup/restore diuji otomatis di `tests/Feature/Operations/BackupRestoreTest.php`
(backup → ubah data → restore → data kembali; backup rusak ditolak; nama berbahaya ditolak).

## 12. Pemulihan (recovery) setelah insiden

1. Aktifkan mode pemeliharaan: `php artisan down --secret="kode-rahasia"` (admin tetap bisa masuk
   lewat `https://domain/kode-rahasia`).
2. Tentukan backup terakhir yang baik: `php artisan humanix:backup --list`.
3. (Disarankan) backup kondisi sekarang dulu: `php artisan humanix:backup --no-prune`.
4. Restore: `php artisan humanix:restore <nama>`.
5. `php artisan optimize && php artisan queue:restart`, cek fungsi utama, lalu `php artisan up`.

## 13. Deploy rilis baru & rollback

**Deploy** (contoh skrip: [`deploy/deploy.sh`](../deploy/deploy.sh)):

```bash
php artisan down --retry=60
php artisan humanix:backup --no-prune          # titik pulih sebelum migrasi
git fetch --tags && git checkout <tag-rilis>   # mis. v1.2.0
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan optimize
php artisan queue:restart
php artisan up
```

**Rollback** bila rilis bermasalah:

1. `php artisan down`
2. Kembalikan migrasi rilis tersebut saja: `php artisan migrate:rollback --step=<jumlah migrasi baru>`
   (lihat `php artisan migrate:status`; jumlah = migrasi yang ditambahkan rilis itu).
3. Kembali ke kode rilis sebelumnya: `git checkout <tag-sebelumnya>`, lalu
   `composer install --no-dev --optimize-autoloader && npm ci && npm run build`.
4. `php artisan optimize && php artisan queue:restart && php artisan up`.
5. Bila migrasi tidak bisa di-rollback dengan aman (data sudah berubah bentuk), pulihkan dari backup
   yang dibuat tepat sebelum deploy (`humanix:restore`), lalu ulangi langkah 3–4.

Selalu beri tag setiap rilis (`git tag v1.2.0 && git push --tags`) agar rollback jelas tujuannya.

## 14. Checklist sebelum go-live

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` HTTPS
- [ ] `composer install --no-dev` (Debugbar tidak terpasang)
- [ ] HTTPS aktif, HTTP dialihkan, `SESSION_SECURE_COOKIE=true`
- [ ] Pengguna DB khusus (bukan root), port DB tertutup dari internet
- [ ] `.env` hak akses 640, tidak ada di git; `APP_KEY` tersimpan aman
- [ ] Supervisor `humanix-worker` berjalan; cron `schedule:run` terpasang
- [ ] SMTP diuji (kirim notifikasi → status "Terkirim" di Audit Log → Pengiriman Email)
- [ ] Backup pertama dibuat, disalin ke luar server, dan **restore sudah diuji**
- [ ] Nama perusahaan, jadwal kerja, hari libur, tarif BPJS/PPh diisi (lihat "Status Konfigurasi"
      di dashboard Administrator)
- [ ] Tidak ada akun demo (`*@humanix.test`) di database produksi
