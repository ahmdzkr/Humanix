# HUMANIX — Human Resource Information System

Aplikasi HRIS berbasis web untuk mengelola karyawan, absensi QR, cuti/izin, lembur, payroll,
rekrutmen hingga onboarding, penilaian kinerja, dashboard analitik, notifikasi, dan audit log.

- **Backend**: PHP 8.2+, Laravel 12, Fortify (login sesi + 2FA), spatie/laravel-permission
- **Frontend**: React 19 + TypeScript lewat Inertia 2, Tailwind CSS 4, Vite, Recharts
- **Database**: MySQL 8 atau MariaDB 10.4+ (dikembangkan & diuji di MariaDB 10.4 XAMPP)
- **Lainnya**: dompdf (slip gaji & ID Card PDF), maatwebsite/excel (ekspor), bacon-qr-code, html5-qrcode
- **Tes**: Pest 3 (feature) dan Playwright (UI & responsif)

Status per fase dan hasil pemeriksaan lengkap ada di [IMPLEMENTATION_STATUS.md](IMPLEMENTATION_STATUS.md).
Panduan produksi ada di [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md).

---

## Daftar isi

1. [Persyaratan sistem](#1-persyaratan-sistem)
2. [Instalasi dependensi](#2-instalasi-dependensi)
3. [Konfigurasi `.env`](#3-konfigurasi-env)
4. [Menyiapkan MySQL](#4-menyiapkan-mysql)
5. [Migrasi dan seeder](#5-migrasi-dan-seeder)
6. [Menjalankan aplikasi (backend & frontend)](#6-menjalankan-aplikasi)
7. [Akun demo lokal](#7-akun-demo-lokal)
8. [Menjalankan tes](#8-menjalankan-tes)
9. [Email (SMTP)](#9-email-smtp)
10. [Queue dan scheduler](#10-queue-dan-scheduler)
11. [Deployment](#11-deployment)
12. [Backup dan restore](#12-backup-dan-restore)
13. [Troubleshooting](#13-troubleshooting)
14. [Struktur proyek](#14-struktur-proyek)

---

## 1. Persyaratan sistem

| Komponen | Versi | Catatan |
| --- | --- | --- |
| PHP | 8.2 atau lebih baru | Ekstensi: `bcmath`, `gd`, `intl`, `mbstring`, `openssl`, `pdo_mysql`, `zip`, `fileinfo` |
| Composer | 2.x | |
| Node.js | 20 atau lebih baru (dikembangkan dengan 24) | npm 10+ |
| MySQL / MariaDB | MySQL 8.0+ atau MariaDB 10.4+ | Klien `mysqldump` & `mysql` dibutuhkan untuk backup |
| Browser | Chrome, Edge, Firefox, Safari versi terbaru | Kios absensi butuh kamera dan HTTPS (atau `localhost`) |

Di Windows, paket **XAMPP** (PHP 8.2 + MariaDB 10.4) sudah memenuhi kebutuhan backend. Pastikan
ekstensi `gd`, `intl`, dan `zip` aktif di `C:\xampp\php\php.ini` (hapus tanda `;` di depan
`extension=gd`, `extension=intl`, `extension=zip`), lalu restart Apache/terminal.

Cek cepat:

```bash
php -v && php -m && composer -V && node -v && npm -v
```

## 2. Instalasi dependensi

```bash
git clone <url-repo> humanix_hris
cd humanix_hris
composer install
npm install
```

## 3. Konfigurasi `.env`

```bash
cp .env.example .env
php artisan key:generate
```

Nilai penting di `.env`:

| Kunci | Contoh lokal | Keterangan |
| --- | --- | --- |
| `APP_URL` | `http://localhost:8000` | URL yang dipakai di email & tautan |
| `APP_ENV` / `APP_DEBUG` | `local` / `true` | Produksi: `production` / `false` |
| `APP_TIMEZONE` | `UTC` | Waktu disimpan UTC di database — **jangan diubah** |
| `APP_DISPLAY_TIMEZONE` | `Asia/Jakarta` | Zona waktu tampilan (WIB) |
| `DB_*` | `humanix`, `root`, kosong | Koneksi MySQL |
| `SESSION_DRIVER`, `CACHE_STORE`, `QUEUE_CONNECTION` | `database` | Semua memakai tabel MySQL |
| `MAIL_MAILER` | `log` | `log` = email hanya ditulis ke log (lihat bagian 9) |
| `BACKUP_MYSQLDUMP`, `BACKUP_MYSQL` | `C:/xampp/mysql/bin/mysqldump.exe` | Hanya bila `mysqldump`/`mysql` tidak ada di PATH |
| `DEBUGBAR_ENABLED` | `false` | Isi `true` bila ingin Debugbar saat pengembangan |

`.env` **tidak boleh di-commit** (sudah ada di `.gitignore`). `.env.example` tidak berisi rahasia apa pun.

## 4. Menyiapkan MySQL

Buat database aplikasi dan database khusus tes:

```sql
CREATE DATABASE humanix CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE humanix_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Untuk produksi, buat pengguna khusus (jangan pakai `root`):

```sql
CREATE USER 'humanix'@'localhost' IDENTIFIED BY 'kata-sandi-kuat';
GRANT ALL PRIVILEGES ON humanix.* TO 'humanix'@'localhost';
FLUSH PRIVILEGES;
```

## 5. Migrasi dan seeder

```bash
php artisan migrate --seed
php artisan wayfinder:generate --with-form
```

- **Data wajib** (selalu di-seed, juga di produksi): role & izin, pengaturan sistem, jadwal kerja
  bawaan, jenis cuti, komponen gaji.
- **Data demo** (karyawan, absensi, payroll, rekrutmen, penilaian) **hanya** di-seed bila
  `APP_ENV=local`.
- Memulai ulang dari awal (menghapus semua data!): `php artisan migrate:fresh --seed`.
- Memberi peran ke akun tertentu: `php artisan humanix:assign-role email@contoh.com HRD`.

## 6. Menjalankan aplikasi

**Cara cepat (semua sekaligus):**

```bash
composer run dev
```

Perintah ini menjalankan server Laravel (`http://localhost:8000`), queue worker, log viewer, dan
Vite. Bila salah satu proses gagal di Windows (mis. `pail`), jalankan terpisah di 3 terminal:

```bash
php artisan serve          # backend  → http://localhost:8000
php artisan queue:listen   # queue (email notifikasi)
npm run dev                # frontend (Vite, hot reload)
```

Build frontend untuk produksi atau tanpa Vite dev server:

```bash
npm run build
```

Dengan XAMPP/Apache, arahkan virtual host ke folder `public/` (bukan ke root proyek).

## 7. Akun demo lokal

Hanya tersedia setelah `php artisan migrate --seed` dengan `APP_ENV=local`. Kata sandi semua akun
demo: **`password`** — jangan pernah dipakai di produksi.

| Peran | Email | Catatan |
| --- | --- | --- |
| Administrator | `admin@humanix.test` | Akun, pengaturan sistem, audit log (tanpa akses gaji & kinerja) |
| HRD | `hrd@humanix.test` | Seluruh modul HR |
| Manager | `manager@humanix.test` | Data & persetujuan tim |
| Karyawan | `karyawan@humanix.test` | Data pribadi (atasan langsungnya: Agus) |
| Karyawan (supervisor) | `agus@humanix.test` | Berperan Karyawan, menyetujui bawahan langsungnya |

## 8. Menjalankan tes

Tes memakai database MySQL **`humanix_test`** (lihat `phpunit.xml`), bukan database aplikasi.

```bash
php artisan test                 # seluruh Pest (feature)
php artisan test --filter=Payroll
vendor/bin/pint --test           # gaya kode PHP
npm run lint:check               # ESLint
npm run types:check              # TypeScript
npm run format:check             # Prettier
npm run build                    # build frontend
```

**Playwright (UI di 5 lebar layar: 360/414/768/1366/1920 px):**

```bash
npm run test:e2e
npx playwright show-report       # laporan HTML
```

Playwright menjalankan server terpisah di port 8010 dengan database `humanix_test` yang di-seed
ulang setiap kali, jadi data lokal tidak tersentuh. Browser default: Microsoft Edge bawaan Windows.
Di Linux/CI: `npx playwright install chromium` lalu `PW_CHANNEL=chromium npm run test:e2e`.
Jangan menjalankan Pest dan Playwright bersamaan (keduanya memakai `humanix_test`).

## 9. Email (SMTP)

Secara bawaan `MAIL_MAILER=log`: notifikasi tetap muncul di aplikasi (lonceng), tetapi email
**tidak dikirim** — isinya ditulis ke `storage/logs/laravel.log`, dan email ke pelamar dicatat
"Tidak dikirim" di **Audit Log → Pengiriman Email**.

Untuk mengirim email sungguhan, isi di `.env` (contoh Gmail; untuk hosting/kantor gunakan data dari
penyedia Anda):

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=null
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=alamat@gmail.com
MAIL_PASSWORD=app-password-16-karakter
MAIL_FROM_ADDRESS="alamat@gmail.com"
MAIL_FROM_NAME="${APP_NAME}"
```

- Gmail wajib memakai **App Password** (Google Account → Security → 2-Step Verification → App passwords).
- Port 465 → `MAIL_SCHEME=smtps`.
- Setelah mengubah `.env`: `php artisan config:clear`, lalu restart server dan queue worker.
- Email dikirim lewat **queue** — worker harus berjalan (bagian 10).
- Status setiap email (terkirim / gagal beserta galatnya) tercatat di **Audit Log → Pengiriman Email**.

## 10. Queue dan scheduler

**Queue** (`QUEUE_CONNECTION=database`) dipakai untuk email notifikasi.

```bash
php artisan queue:work --tries=3      # produksi: dijalankan Supervisor (docs/DEPLOYMENT.md)
php artisan queue:failed              # daftar job gagal
php artisan queue:retry all           # coba ulang job gagal
php artisan queue:restart             # wajib setelah deploy kode baru
```

**Scheduler** — tugas terjadwal (lihat `routes/console.php`):

| Perintah | Jadwal (WIB) | Fungsi |
| --- | --- | --- |
| `humanix:mark-absent` | Setiap hari 23:55 | Tandai karyawan terjadwal yang tidak absen sebagai Tidak Hadir/Cuti |
| `humanix:remind-reviews` | Senin 08:00 | Pengingat penilaian kinerja yang belum diisi |
| `humanix:backup` | Setiap hari 01:00 | Backup database + file privat |

Di server, tambahkan satu baris cron:

```cron
* * * * * cd /var/www/humanix && php artisan schedule:run >> /dev/null 2>&1
```

Di Windows (lokal), uji manual dengan `php artisan schedule:run` atau `php artisan schedule:work`.

## 11. Deployment

Ringkasan (detail lengkap, contoh Nginx/Supervisor/cron, dan rollback ada di
[docs/DEPLOYMENT.md](docs/DEPLOYMENT.md)):

```bash
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan optimize
php artisan queue:restart
```

Wajib di produksi: `APP_ENV=production`, `APP_DEBUG=false`, HTTPS, `SESSION_SECURE_COOKIE=true`,
Supervisor untuk `queue:work`, cron `schedule:run`, dan backup harian yang **sudah diuji restore-nya**.

## 12. Backup dan restore

```bash
php artisan humanix:backup             # backup sekarang (database .sql.gz + file privat .zip)
php artisan humanix:backup --list      # daftar backup
php artisan humanix:restore 20261011-010000   # pulihkan (MENIMPA data saat ini, ada konfirmasi)
```

- Lokasi: `storage/backups/<tanggal-jam>/` (ubah dengan `BACKUP_PATH`), disimpan
  `BACKUP_KEEP_DAYS` hari (default 14).
- Isi: `database.sql.gz`, `private-files.zip` (foto, CV, lampiran cuti, bukti bayar), dan
  `manifest.json` berisi checksum SHA-256. Restore **menolak** backup yang checksum-nya tidak cocok.
- Kredensial database tidak pernah muncul di argumen perintah (dipakai lewat file opsi sementara).
- Salin backup ke lokasi lain (server/cloud terpisah) — backup di server yang sama tidak melindungi
  dari kerusakan disk. Panduan uji restore & rollback: [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md).

## 13. Troubleshooting

| Gejala | Penyebab & solusi |
| --- | --- |
| `Vite manifest not found` / halaman putih | Jalankan `npm run dev` atau `npm run build`. Hapus `public/hot` bila Vite dev server sudah dimatikan. |
| `SQLSTATE[HY000] [2002]` | MySQL belum jalan atau `DB_HOST`/`DB_PORT` salah. Di XAMPP, start MySQL dari Control Panel. |
| `Invalid default value` / kolom `timestamp` saat migrasi | MariaDB lama; pastikan memakai migrasi terbaru (kolom wajib memakai `dateTime`). |
| `Call to undefined function imagecreatefrompng` / ID Card gagal | Ekstensi `gd` belum aktif di `php.ini`. |
| `Class "NumberFormatter" not found` | Ekstensi `intl` belum aktif. |
| Email tidak terkirim | `MAIL_MAILER=log`, queue worker tidak jalan, atau kredensial salah — cek **Audit Log → Pengiriman Email** dan `php artisan queue:failed`. |
| Notifikasi muncul tetapi email "Dalam antrean" terus | Queue worker tidak berjalan: `php artisan queue:work`. |
| Kamera kios tidak menyala | Browser hanya mengizinkan kamera di HTTPS atau `localhost`; izinkan akses kamera di browser. |
| Waktu absensi bergeser 7 jam | Jangan ubah `APP_TIMEZONE` (harus `UTC`); zona tampilan diatur `APP_DISPLAY_TIMEZONE`. |
| 419 Page Expired | Sesi habis; muat ulang halaman. Pastikan `APP_URL`/`SESSION_DOMAIN` sesuai domain. |
| 403 di semua menu | Akun belum punya peran: `php artisan humanix:assign-role email@contoh.com HRD`. |
| Route/helper frontend tidak ditemukan | `php artisan wayfinder:generate --with-form`. |
| Perubahan `.env` tidak berlaku | `php artisan config:clear` (atau `php artisan optimize` ulang di produksi). |
| `humanix:backup` gagal: mysqldump tidak ditemukan | Isi `BACKUP_MYSQLDUMP` dan `BACKUP_MYSQL` dengan path lengkap biner MySQL. |

Log aplikasi: `storage/logs/laravel.log` (lokal: `php artisan pail` untuk melihat langsung).

## 14. Struktur proyek

```
app/
  Console/Commands/   perintah humanix:* (absen, pengingat, backup/restore, assign-role)
  Http/Controllers/   controller tipis per modul (Attendance, Leave, Payroll, Recruitment, ...)
  Models/             model Eloquent (Auditable mencatat perubahan ke audit_logs)
  Notifications/      HrisNotification (satu bentuk notifikasi untuk semua modul)
  Services/           aturan bisnis multi-tabel (ApprovalService, PayrollService, Notifier, ...)
database/migrations, database/seeders
resources/js/
  pages/              halaman Inertia per modul
  components/         komponen UI (KpiCard, DataTable, grafik, lonceng notifikasi, ...)
  lib/                format tanggal/uang WIB, navigasi & izin, helper modul
routes/web.php        semua route (dilindungi middleware role/permission)
routes/console.php    jadwal tugas
tests/Feature         tes Pest per modul + matriks otorisasi + keamanan
e2e/                  tes Playwright
docs/                 panduan deployment, backup, rollback
deploy/               contoh konfigurasi Nginx, Supervisor, cron
```
