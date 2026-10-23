# HUMANIX HRIS — Status Implementasi

Dokumen ini dicatat setiap kali satu fase selesai. Isinya hanya yang benar-benar
sudah dikerjakan dan diuji. Rencana lengkap ada di dokumen "HUMANIX HRIS — Alur
Pembuatan dari Awal hingga Selesai" (11 fase, Fase 0–10).

| Fase | Judul | Status |
| --- | --- | --- |
| 0 | Fondasi proyek dan autentikasi | ✅ Selesai (10 Okt 2026), dengan catatan di bawah |
| 1 | Database, role, permission, struktur organisasi | ✅ Selesai (10 Okt 2026) |
| 2 | Modul karyawan | ✅ Selesai (10 Okt 2026) |
| 3 | Absensi, cuti, izin, lembur | ✅ Selesai (10 Okt 2026) |
| 4 | Payroll | ✅ Selesai (10 Okt 2026) |
| 5 | Rekrutmen dan onboarding | ✅ Selesai (11 Okt 2026) |
| 6 | Penilaian kinerja | ✅ Selesai (11 Okt 2026) |
| 7 | Dashboard analitik | ✅ Selesai (11 Okt 2026) |
| 8 | Notifikasi dan audit log | ✅ Selesai (11 Okt 2026) |
| 9 | Pengujian, keamanan, kualitas | ✅ Selesai (11 Okt 2026), dengan catatan di bawah |
| 10 | Deployment dan dokumentasi | ✅ Selesai (11 Okt 2026), dengan catatan di bawah |

---

## Fase 10 — Deployment dan dokumentasi

### Yang dibangun

- **README.md** (spesifikasi bagian 44): persyaratan sistem, instalasi dependensi, `.env`, MySQL,
  migrasi & seeder, menjalankan backend/frontend, tes (Pest & Playwright), akun demo lokal, email,
  queue & scheduler, deployment, backup & restore, troubleshooting, struktur proyek.
- **docs/DEPLOYMENT.md** (bagian 43): konfigurasi env produksi, DB produksi, HTTPS, optimasi Laravel,
  build frontend, sesi, queue worker (Supervisor), scheduler (cron), logging, backup, uji restore,
  recovery, storage, email, manajemen rahasia, deploy & rollback (`migrate:rollback --step`, tag rilis),
  checklist go-live. Contoh konfigurasi di `deploy/` (Nginx, Supervisor, cron, `deploy.sh`) dan
  `.env.production.example` (tanpa rahasia; `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`).
- **Backup & restore** (`humanix:backup`, `humanix:restore`, terjadwal 01:00 WIB): dump MySQL (gzip) +
  file privat (zip) + manifest checksum SHA-256; restore menolak backup rusak & nama berbahaya; backup
  gagal tercatat gagal (exit code ≠ 0) dan folder setengah jadi dihapus; retensi `BACKUP_KEEP_DAYS`.
  Kredensial DB lewat file opsi sementara, bukan argumen perintah.
  **Uji restore otomatis** di `tests/Feature/Operations/BackupRestoreTest.php` (database terpisah
  `humanix_backup_test`), plus satu backup nyata database lokal berhasil (24 KB + 3 file privat).
- **Menutup celah kriteria 18** (tombol utama tanpa fungsi):
  - Menu **Laporan** kini berfungsi: ringkasan bulanan (karyawan aktif, tingkat kehadiran dengan
    definisi yang sama dengan dashboard, hari cuti, jam lembur), tabel rekap per karyawan, dan unduhan
    Excel sesuai izin (rekap cuti & lembur — baru, juga untuk Manager/timnya; rekap absensi bulanan,
    data karyawan, payroll per periode untuk HRD). Placeholder modul dihapus.
  - **Pencarian di header** kini berfungsi: halaman yang boleh dibuka + karyawan sesuai cakupan
    (`visibleTo`, sama dengan Data Karyawan), navigasi keyboard, dibatasi 60 permintaan/menit.
- **Halaman login baru** mengikuti desain "Login_humanix" (latar 3D gelap dari desainer, kartu form di
  posisi desain, berskala di semua ukuran layar; versi ponsel dengan logo di atas). Halaman auth lain
  ikut bertema sama.

### Verifikasi akhir (11 Okt 2026)

| Pemeriksaan | Hasil |
| --- | --- |
| `php vendor/bin/pest` (MySQL `humanix_test`) | ✅ **392 lulus**, 0 gagal |
| Playwright 5 lebar (360/414/768/1366/1920) | ✅ **28 lulus**, 0 gagal, 7 dilewati sesuai rancangan |
| `php artisan migrate:fresh --seed` (`humanix_test`, via global-setup Playwright) | ✅ |
| `pint --test`, `eslint`, `tsc --noEmit`, `prettier --check` | ✅ |
| `npm run build` | ✅ tanpa peringatan |
| `php artisan route:list` | ✅ |
| `.env` tidak ter-commit, `.env.example` & `.env.production.example` tanpa rahasia | ✅ (diuji di `SecurityBaselineTest`) |
| Debugbar hanya `require-dev`, produksi `APP_DEBUG=false` | ✅ (`composer install --no-dev` di panduan) |

### Spesifikasi bagian 40 — tes otomatis wajib

| # | Butir | Status | Tes |
| --- | --- | --- | --- |
| 1 | Login & logout | ✅ | `Auth/AuthenticationTest`, `Authorization/RoleAccessTest` (redirect per role) |
| 2 | Reset password | ✅ | `Auth/PasswordResetTest` |
| 3 | Akses role | ✅ | `Authorization/RoleAccessTest` (matriks 36 halaman × 4 role + tes penjaga) |
| 4 | CRUD karyawan | ✅ | `Employees/EmployeeManagementTest` |
| 5 | Validasi NIK & email | ✅ | `Employees/EmployeeManagementTest` ("NIK & email unik …"), `Employees/OwnProfileTest` |
| 6 | Check-in & check-out | ✅ | `Attendance/AttendanceScanTest` |
| 7 | Duplikasi absensi | ✅ | `Attendance/AttendanceScanTest` ("scan ganda ditolak …") |
| 8 | Pengajuan cuti | ✅ | `Leave/LeaveRequestTest` |
| 9 | Saldo cuti | ✅ | `Leave/LeaveRequestTest` (saldo berkurang, saldo tidak cukup termasuk yang menunggu) |
| 10 | Persetujuan & penolakan | ✅ | `Leave/LeaveRequestTest`, `Overtime/OvertimeRequestTest`, `Notifications/NotificationTest` |
| 11 | Pengajuan lembur | ✅ | `Overtime/OvertimeRequestTest` |
| 12 | Pengelolaan payroll | ✅ | `Payroll/PayrollTest` (hitung, status, pembayaran, tutup periode) |
| 13 | Akses informasi gaji | ✅ | `Payroll/PayrollTest`, `Authorization/HorizontalAccessTest` |
| 14 | Pembuatan lowongan | ✅ | `Recruitment/RecruitmentFlowTest` |
| 15 | Pendaftaran pelamar | ✅ | `Recruitment/RecruitmentFlowTest` (lamaran publik, duplikat, honeypot) |
| 16 | Unggah CV | ✅ | `Recruitment/RecruitmentFlowTest`, `Security/SecurityBaselineTest` |
| 17 | Seleksi administrasi | ✅ | `Recruitment/RecruitmentFlowTest` |
| 18 | Penjadwalan wawancara | ✅ | `Recruitment/RecruitmentFlowTest` (termasuk notifikasi interviewer & email pelamar) |
| 19 | Konversi kandidat → karyawan | ✅ | `Recruitment/RecruitmentFlowTest` (termasuk rollback & tidak dua kali) |
| 20 | Penilaian kinerja | ✅ | `Performance/PerformanceReviewTest` |
| 21 | Publikasi hasil | ✅ | `Performance/PerformanceReviewTest` (tersembunyi sampai dipublikasikan) |
| 22 | Notifikasi | ✅ | `Notifications/NotificationTest` + tambahan di tes payroll/rekrutmen/kinerja |
| 23 | Audit log | ✅ | `Audit/AuditLogTest`, `Organization/StructureAndAuditTest` |
| 24 | Akses data lintas pengguna | ✅ | `Authorization/HorizontalAccessTest` (gaji, cuti, lembur, kinerja, CV, profil), `Notifications/NotificationTest` (404) |

Semua butir punya tes otomatis yang lulus. (Daftar spesifikasi berisi 24 butir unik.)

### Bagian XIII — 20 kriteria penerimaan akhir

| # | Kriteria | Status | Catatan |
| --- | --- | --- | --- |
| 1 | Laravel 12 + React + Inertia + MySQL | ✅ | Diuji di **MariaDB 10.4** (XAMPP); MySQL 8 belum diuji langsung, tetapi tidak ada fitur khusus MariaDB yang dipakai |
| 2 | Autentikasi berjalan | ✅ | Login, logout, reset, 2FA, batas percobaan |
| 3 | Empat role sesuai wewenang | ✅ | Matriks akses + akses horizontal |
| 4 | Semua modul utama tersedia & terhubung DB | ✅ | |
| 5 | CRUD dengan validasi | ✅ | |
| 6 | Absensi mengikuti jadwal & menolak catatan tidak valid | ✅ | Jendela waktu, libur, scan ganda |
| 7 | Cuti & lembur mengikuti alur persetujuan | ✅ | Atasan → HRD, tanpa persetujuan diri sendiri |
| 8 | Payroll: perhitungan, status, riwayat, proteksi akses | ✅ | |
| 9 | Rekrutmen dari lowongan sampai onboarding | ✅ | |
| 10 | Kinerja dari pengaturan periode sampai publikasi | ✅ | |
| 11 | Dashboard analitik dari data nyata | ✅ | Tanpa angka dummy |
| 12 | Notifikasi & audit log | ✅ | In-app teruji; email teruji dengan mailer `array` (lihat 20) |
| 13 | Data sensitif terlindungi | ✅ | Rekening dimasking, file privat, audit disaring, header keamanan |
| 14 | UI responsif & konsisten | ✅ | Playwright 5 lebar tanpa overflow/console error |
| 15 | Tes otomatis ada & hasilnya terdokumentasi | ✅ | Dokumen ini |
| 16 | Build frontend berhasil | ✅ | Tanpa peringatan |
| 17 | Dokumentasi instalasi & deployment | ✅ | README, docs/DEPLOYMENT.md, deploy/ |
| 18 | Tidak ada tombol utama yang tidak berfungsi | ✅ | Laporan & pencarian header kini berfungsi (sebelumnya placeholder/visual) |
| 19 | Tidak ada akses tanpa izin yang diketahui & belum ditangani | ✅ | Temuan "validasi sebelum otorisasi" (Fase 9) sudah diperbaiki |
| 20 | Tidak mengklaim integrasi eksternal berjalan tanpa dikonfigurasi & diuji | ⚠️ Dicatat | **SMTP belum dikonfigurasi** di lingkungan ini → pengiriman email sungguhan **belum** diuji (status kirim tetap dicatat jujur, termasuk "Tidak dikirim"). **Deployment produksi** (Nginx, Supervisor, cron, HTTPS) **belum** dijalankan di server nyata — baru tersedia panduan & contoh konfigurasi |

### Catatan

- Yang masih perlu dilakukan di luar komputer ini: isi SMTP lalu kirim satu notifikasi uji (cek status
  "Terkirim" di Audit Log → Pengiriman Email), deploy ke server mengikuti docs/DEPLOYMENT.md, dan
  lakukan uji restore pertama di server tersebut.
- Kartu "Butuh bantuan?" di sidebar hanya teks informasi (bukan tombol); kontak helpdesk belum diisi.
- File `public/images/login-bg.png` (1,7 MB, dari desainer) tidak di-commit; aplikasi memakai versi
  WebP 38 KB.

---

## Fase 9 — Pengujian, keamanan, kualitas

### Hasil pemeriksaan (11 Okt 2026)

| Pemeriksaan | Hasil |
| --- | --- |
| `php vendor/bin/pest` (MySQL `humanix_test`) | ✅ **376 lulus**, 2184 assertion, 0 gagal |
| Playwright (`npm run test:e2e`, Edge, 5 lebar: 360/414/768/1366/1920) | ✅ **28 lulus**, 7 dilewati sesuai rancangan (alur cuti hanya di 1366; tes menu ponsel hanya < 768), 0 gagal |
| Playwright terhadap **build produksi** (tanpa Vite dev server, 1366 & 360) | ✅ 12 lulus, 2 dilewati sesuai rancangan |
| `php artisan migrate:fresh --seed` (di `humanix_test`) | ✅ |
| `vendor/bin/pint --test` | ✅ |
| `npx eslint resources/js e2e playwright.config.ts` | ✅ 0 error |
| `npx tsc --noEmit` | ✅ |
| `npx prettier --check resources/js e2e` | ✅ |
| `npm run build` | ✅ **tanpa peringatan** (sebelumnya `app.js` 531 kB > 500 kB; kini React/Inertia/UI dipecah ke chunk vendor, terbesar 223 kB) |
| `php artisan route:list` | ✅ 155 route terdaftar tanpa galat |

### Yang dibangun / diperbaiki

- **Matriks otorisasi role × route** (`tests/Feature/Authorization/RoleAccessTest.php`): 34 halaman × 4
  role, dengan status eksplisit bila halaman sengaja mengalihkan (mis. Karyawan di `/gaji` → Slip Saya).
  Ada **tes penjaga**: setiap route GET baru yang butuh login wajib masuk matriks, dan tamu selalu
  diarahkan ke login.
- **Akses horizontal (ganti ID)** (`tests/Feature/Authorization/HorizontalAccessTest.php`): slip gaji,
  PDF slip, bukti bayar, periode payroll; cuti + lampiran + pembatalan; lembur; lembar penilaian
  (buka, evaluasi diri, penilaian atasan tim lain); lamaran + CV pelamar; profil, foto, ID Card, ubah
  data karyawan. Diuji untuk rekan kerja, rekan satu tim, atasan langsung (bila tak berhak), atasan tim
  lain, dan Administrator; isi file rahasia dipastikan tidak bocor.
- **Temuan & perbaikan keamanan**: beberapa aksi memvalidasi input **sebelum** memeriksa hak akses,
  sehingga pihak luar mendapat pesan validasi (302), bukan 403. Data tidak pernah berubah, tetapi
  urutannya salah. Diperbaiki untuk keputusan cuti, keputusan lembur, evaluasi diri, penilaian
  atasan, dan rencana pengembangan (`ApprovalService::isApproverOf` + pemeriksaan di controller).
  Tes baru memastikan payload kosong dari pihak luar selalu 403.
- **Baseline keamanan** (`tests/Feature/Security/SecurityBaselineTest.php`): batas login 5×/menit,
  header keamanan (middleware baru `SecurityHeaders`: nosniff, X-Frame-Options, Referrer-Policy,
  Permissions-Policy, HSTS hanya di HTTPS), cookie sesi httpOnly/SameSite, nomor rekening lengkap & token
  QR tidak pernah dikirim ke browser, unggahan berbahaya/terlalu besar ditolak, file privat di disk
  non-publik, `.env` tidak di-commit dan `.env.example` tanpa rahasia.
- **Playwright** (`e2e/`, `playwright.config.ts`): server terpisah di port 8010 memakai database
  `humanix_test` yang di-seed ulang setiap run, jadi data lokal `humanix` tidak tersentuh. Per role:
  login → halaman utama (Admin 7, HRD 9, Manager 6, Karyawan 8) di 5 lebar dengan cek status HTTP,
  judul halaman, **tanpa overflow horizontal**, dan **tanpa error console/JavaScript**. Ditambah:
  menu sidebar di ponsel, halaman 403, dan alur lintas role **Karyawan mengajukan cuti → atasan
  langsung (Agus, role Karyawan) menyetujui lewat notifikasi → HRD menyetujui lewat lonceng →
  Karyawan menerima notifikasi "disetujui"**.

### Cakupan tes per modul (Pest)

| Modul | File tes |
| --- | --- |
| Autentikasi & 2FA | `tests/Feature/Auth/*` |
| Role, matriks akses, akses horizontal | `tests/Feature/Authorization/*` |
| Keamanan dasar | `tests/Feature/Security/SecurityBaselineTest.php` |
| Organisasi & audit dasar | `tests/Feature/Organization/*` |
| Karyawan & profil sendiri | `tests/Feature/Employees/*` |
| Absensi (scan QR, rekap) | `tests/Feature/Attendance/*` |
| Cuti/izin & lembur | `tests/Feature/Leave/*`, `tests/Feature/Overtime/*` |
| Payroll | `tests/Feature/Payroll/PayrollTest.php` |
| Rekrutmen & onboarding | `tests/Feature/Recruitment/RecruitmentFlowTest.php` |
| Penilaian kinerja | `tests/Feature/Performance/PerformanceReviewTest.php` |
| Dashboard | `tests/Feature/Dashboard/DashboardMetricsTest.php` |
| Notifikasi & audit log | `tests/Feature/Notifications/*`, `tests/Feature/Audit/*` |
| Pengaturan sistem | `tests/Feature/Settings/*` |

### Catatan jujur

- **Daftar "spesifikasi bagian 40" dan "20 kriteria penerimaan Bagian XIII" tidak tersedia** di
  komputer ini maupun di dokumen alur pembuatan, jadi belum bisa dicocokkan satu per satu. Cakupan di
  atas disusun dari kebutuhan tiap fase. Bila dokumen spesifikasi aslinya dibagikan, daftar itu bisa
  dicek butir demi butir.
- Playwright memakai **Microsoft Edge** yang terpasang di Windows (`channel: msedge`), tanpa mengunduh
  browser tambahan. Di server/CI tanpa Edge: `npx playwright install chromium` lalu `PW_CHANNEL=chromium`.
- Kamera kios absensi (scan QR) tidak diuji di Playwright karena butuh kamera; logika scan diuji di Pest.
- Pest dan Playwright sama-sama memakai `humanix_test`; jalankan bergantian, jangan bersamaan.

---

## Fase 8 — Notifikasi dan audit log

### Yang dibangun

- **Notifikasi in-app** (Laravel database channel) lewat satu titik, `App\Services\Notifier`, dengan
  satu bentuk `App\Notifications\HrisNotification` (kategori, judul, isi, tautan). Isi notifikasi tidak
  pernah memuat nominal gaji, nomor rekening, atau nilai kinerja.
- **Email lewat queue** (`App\Jobs\SendNotificationMail`, 3 percobaan) hanya bila SMTP dikonfigurasi
  (`MAIL_MAILER` bukan `log`). Setiap email dicatat di `notification_deliveries`:
  `queued` → `sent`, atau `failed` beserta pesan galat dan jumlah percobaan. Email ke pelamar saat SMTP
  belum diatur dicatat `skipped` ("Tidak dikirim"), bukan dianggap terkirim. Job baru dijalankan
  setelah transaksi commit.
- **Pemicu**:
  - Cuti/izin & lembur: diajukan → pemberi persetujuan langkah berjalan (atasan atau semua HRD);
    disetujui atasan → HRD; disetujui/ditolak akhir → pemilik (alasan penolakan ikut).
  - Rekrutmen: jadwal/pembatalan wawancara → interviewer (in-app + email) dan pelamar (email);
    tidak lolos seleksi / keputusan akhir → pelamar (email).
  - Payroll: periode **disetujui** → setiap karyawan "Slip gaji {periode} tersedia".
  - Penilaian kinerja: aktivasi periode (karyawan diminta evaluasi diri; penilai menerima satu
    ringkasan), evaluasi diri dikirim → atasan, penilaian atasan → HRD, dikembalikan → atasan,
    dipublikasikan → karyawan. **Pengingat mingguan** `humanix:remind-reviews` (Senin 08:00 WIB).
  - Data penting: HRD mengubah email, departemen, jabatan, atasan, status kepegawaian, akhir kontrak,
    jadwal, atau rekening → karyawan diberi tahu (hanya nama field); perubahan peran; struktur gaji baru.
  - Pelaku tidak menerima notifikasi atas tindakannya sendiri; karyawan nonaktif dilewati.
- **Lonceng header**: jumlah belum dibaca, dropdown 5 terbaru, "Tandai semua dibaca", tautan ke
  halaman **Notifikasi** (`/notifikasi`, filter Semua/Belum dibaca, pagination). Membuka notifikasi
  menandainya dibaca lalu menuju halaman terkait; hanya path internal yang diikuti (cegah open
  redirect); notifikasi milik orang lain → 404.
- **Audit Log** (Administrator, menu "Audit Log", `/audit-log`): filter pelaku, modul, aksi, rentang
  tanggal; detail sebelum/sesudah per field. Kata sandi, token, dan nomor rekening disaring saat
  ditulis **dan** saat ditampilkan. Tab **Pengiriman Email** (`/audit-log/email`) menampilkan status
  kirim apa adanya beserta galatnya.
- Audit ditambahkan ke aksi kunci yang sebelumnya belum tercatat: pengajuan cuti/izin & lembur,
  langkah persetujuan, koreksi absensi, lamaran, wawancara, onboarding. Nilai "sebelum" kini disimpan
  mentah seperti nilai "sesudah" agar sebanding (sebelumnya kolom JSON tampil dengan tanda kutip).

### Verifikasi

| Pemeriksaan | Hasil |
| --- | --- |
| `php vendor/bin/pest` (MySQL `humanix_test`) | ✅ 311 lulus, 1925 assertion |
| `php artisan migrate:fresh --seed` (di `humanix_test`) | ✅ |
| `npm run build`, `tsc`, `eslint`, `prettier`, `pint` | ✅ (peringatan chunk `app.js` > 500 kB masih ada) |
| Cek manual browser (Admin) | ✅ Lonceng menampilkan 1 notifikasi uji → klik → ditandai dibaca & menuju Audit Log; detail perubahan nama perusahaan; tab email; 360px tanpa overflow. Notifikasi uji dihapus setelahnya. |

Tes baru: `tests/Feature/Notifications/NotificationTest.php` (alur cuti atasan → HRD → karyawan dan
penerima yang salah tidak menerima; penolakan + alasan; lembur; **email gagal tercatat gagal**; tanpa
SMTP; karyawan nonaktif; rekening tanpa nomor; lonceng & tandai dibaca; 404 milik orang lain; open
redirect), `tests/Feature/Audit/AuditLogTest.php` (akses hanya Admin, filter, data sensitif tidak
tampil, aksi kunci tercatat, log email), serta tambahan di tes payroll, rekrutmen, dan penilaian
kinerja (termasuk pengingat mingguan).

### Catatan

- Email butuh **queue worker** berjalan: `composer run dev` sudah menjalankan `queue:listen`; di
  produksi gunakan Supervisor untuk `queue:work` (Fase 10). Pengingat mingguan butuh cron
  `schedule:run`.
- Notifikasi in-app tidak real-time (tanpa WebSocket); jumlah di lonceng diperbarui setiap kali
  halaman berpindah.
- Karena tes memakai `MAIL_MAILER=array` (dianggap SMTP aktif), jalur email ikut teruji di setiap tes.

---

## Fase 7 — Dashboard analitik

### Yang dibangun

- `App\Services\DashboardMetrics`: setiap metrik didefinisikan **sekali** dan dipakai semua role.
  - **Headcount** = karyawan aktif yang tanggal masuknya ≤ hari ini.
  - **Kehadiran harian**: *terjadwal* = karyawan yang punya jadwal, hari itu hari kerjanya, dan
    bukan hari libur; *hadir* = Hadir + Terlambat; *cuti* = cuti disetujui; *belum absen* =
    terjadwal tanpa catatan. **% hadir = hadir ÷ (terjadwal − cuti)**, 0 bila penyebut 0.
  - Tren kehadiran memuat seluruh rentang **sekali** (jumlah query tetap untuk 7 atau 30 hari).
  - Distribusi departemen & status kepegawaian, pengajuan menunggu, tren hari cuti & jam lembur
    disetujui (6 bulan), ringkasan payroll periode terbaru, rekrutmen (lowongan dibuka, pelamar
    bulan ini, tahap pelamar pada lowongan yang belum ditutup), progres penilaian kinerja,
    aktivitas terbaru.
- **Dashboard HRD**: sapaan + jumlah pengajuan menunggu, 4 KPI (Total Karyawan, Hadir Hari Ini,
  Cuti/Izin, Pengajuan Lembur), Tren Kehadiran (bar Hadir vs Tidak Hadir), Distribusi per
  Departemen (donut, total di tengah + legenda berangka), Pengajuan Menunggu Persetujuan (badge
  oranye "Menunggu", tombol "Lihat"), Ringkasan Payroll (Rupiah besar, "Sudah Diproses" bila
  disetujui/ditutup), grafik hari cuti & jam lembur (dua grafik terpisah, tanpa sumbu ganda),
  status kepegawaian, rekrutmen, penilaian kinerja, aktivitas terbaru. **Filter**: departemen dan
  rentang tren 7/30 hari.
- **Dashboard Manager**: hanya tim (bawahan langsung & tidak langsung) + bagian "Data Saya".
- **Dashboard Karyawan**: absensi hari ini & jadwal, tombol **Tampilkan QR Absen** (kartu ID),
  sisa cuti, lembur bulan ini, gaji bersih slip terakhir (hanya periode disetujui), rekap
  kehadiran bulan ini, hasil penilaian terakhir yang dipublikasikan.
- **Dashboard Administrator**: jumlah akun (dan akun tanpa peran), departemen/jabatan aktif,
  audit hari ini, distribusi peran, **status konfigurasi** (nama perusahaan, jadwal kerja, hari
  libur tahun berjalan, tarif BPJS/PPh di komponen atau struktur gaji, SMTP), log audit terbaru.
- Komponen grafik (`resources/js/components/charts`): palet kategorikal tervalidasi (lolos uji
  buta warna), tooltip saat hover, legenda, tombol **Tabel** sebagai tampilan alternatif, empty
  state di setiap panel. Tidak ada angka dummy: semua angka dari database.
- **Pengaturan Sistem** (Administrator, menu Pengaturan → Pengaturan Sistem, `/pengaturan/sistem`):
  nama perusahaan, toleransi keterlambatan default, maksimal lembur per hari, pembagi & pengali
  upah lembur, indikator wawancara. Tervalidasi, hanya nilai yang berubah disimpan, tercatat di
  audit log (`tests/Feature/Settings/SystemSettingTest.php`). Alur persetujuan cuti sengaja belum
  bisa diubah dari UI.
- Halaman `dashboard.tsx` lama (placeholder) dihapus; setiap role kini punya halaman sendiri
  (`dashboard/hr|manager|employee|admin`).

### Verifikasi

| Pemeriksaan | Hasil |
| --- | --- |
| `php vendor/bin/pest` (MySQL `humanix_test`) | ✅ 293 lulus, 1779 assertion |
| `php artisan migrate:fresh --seed` (di `humanix_test`) | ✅ |
| `npm run build`, `tsc`, `eslint`, `prettier`, `pint` | ✅ (peringatan chunk `app.js` > 500 kB masih ada) |
| Cek manual browser | ✅ Dashboard HRD, Manager, Karyawan, Admin di 1366px; HRD 30 hari di 360px tanpa overflow horizontal; tanpa console error |

Tes baru di `tests/Feature/Dashboard/DashboardMetricsTest.php` memakai data yang diketahui
(Rabu 7 Okt 2026): headcount 6, terjadwal 5, hadir 2 (1 terlambat), tidak hadir 1, cuti 1,
belum absen 1, **% hadir 50%**; hari libur → 0% tanpa error; distribusi departemen; tren
cuti/lembur; filter departemen & 30 hari; cakupan tim Manager; dashboard Karyawan; dashboard
Admin; jumlah query tren tidak bertambah per hari.

### Catatan

- Ringkasan payroll mengambil periode dengan tanggal mulai terbaru.
- Menu **Laporan** masih placeholder; laporan yang bisa diekspor tersedia per modul (absensi,
  payroll). Halaman laporan terpadu bisa ditambahkan bila dibutuhkan.
- Jumlah aktivitas terbaru dan log audit dibatasi (6 dan 8 item); halaman audit log lengkap
  menyusul di Fase 8.

---

## Fase 6 — Penilaian kinerja

### Yang dikerjakan

1. **Tabel**:
   - `performance_periods` (flag evaluasi diri, draft/aktif/ditutup).
   - `performance_indicators` (bobot %, skala min–maks, target).
   - `performance_reviews` (status, `version`, penilai, nilai akhir, predikat).
   - `performance_review_items` (nilai & komentar diri/atasan).
   - `self_assessments` (pencapaian, kendala).
   - `development_plans` (pelatihan/perbaikan/promosi).
   - `performance_review_logs` (jejak semua tindakan).
2. **Periode**:
   - HRD membuat periode dan indikator.
   - **Aktivasi ditolak bila total bobot ≠ 100%** (dicek presisi) atau tanpa indikator.
   - Saat aktif, lembar penilaian dibuat untuk semua karyawan aktif. Penilai = atasan langsung;
     bila tidak ada atasan, dinilai HRD.
   - Indikator terkunci setelah aktif.
   - Periode hanya bisa ditutup bila semua hasil sudah dipublikasikan.
3. **Alur status**:
   - Menunggu Evaluasi Diri → Menunggu Penilaian Atasan → Menunggu Pemeriksaan HRD → Final →
     Dipublikasikan. HRD bisa **mengembalikan** ke atasan (catatan wajib) untuk revisi.
   - Jika evaluasi diri dimatikan, lembar langsung menunggu atasan.
   - Setiap aksi wajib membawa `version` (versi lama ditolak) dan tercatat di log.
4. **Validasi & hak**:
   - Skor harus bilangan bulat dalam skala indikator; di luar skala ditolak dengan pesan per
     indikator.
   - **Atasan hanya bisa menilai bawahan dalam scope-nya** (langsung/tidak langsung) yang
     ditugaskan kepadanya. Dicek ulang saat kirim, jadi atasan lama tidak bisa menilai lagi bila
     karyawan pindah tim.
   - HRD tidak memfinalkan penilaiannya sendiri; tidak ada yang menilai dirinya sendiri sebagai
     atasan.
   - **Sebelum dipublikasikan, karyawan tidak melihat** nilai atasan, nilai akhir, komentar,
     rencana pengembangan, maupun log. Hanya evaluasi dirinya yang terlihat.
5. **Nilai akhir 0–100** = Σ bobot × skor ternormalisasi ((skor − min) ÷ (maks − min) × 100),
   dihitung presisi (bcmath). Predikat: ≥90 Istimewa, ≥75 Baik, ≥60 Cukup, ≥40 Kurang,
   <40 Buruk.
6. **Rencana pengembangan** ditambahkan HRD atau penilai setelah penilaian final; terlihat
   karyawan setelah dipublikasikan.
7. **Halaman**:
   - `/penilaian-kinerja`: periode (HRD), "Perlu Saya Nilai", "Penilaian Saya".
   - `/penilaian-kinerja/periode/{id}`: indikator + total bobot, aktifkan, daftar lembar per
     status, publikasikan semua yang final, tutup.
   - `/penilaian-kinerja/lembar/{id}`: form evaluasi diri, form penilaian atasan, rincian nilai,
     kartu nilai akhir, keputusan HRD, publikasi, rencana pengembangan, riwayat.
8. **Seeder lokal**: periode "Semester 2 2026" aktif dengan 4 indikator (40/25/15/20).

### Tes dan pemeriksaan (11 Okt 2026)

| Pemeriksaan | Hasil |
| --- | --- |
| `php vendor/bin/pest` (MySQL `humanix_test`) | ✅ 278 lulus, 1602 assertion |
| `php artisan migrate:fresh --seed` (di `humanix_test`) | ✅ |
| `npm run build`, `tsc`, `eslint`, `prettier`, `pint` | ✅ (peringatan chunk `app.js` > 500 kB masih ada) |
| Cek manual browser (HRD) | ✅ Halaman periode (total bobot 100% ✓) dan lembar evaluasi diri; tanpa console error |

Tes baru di `tests/Feature/Performance/PerformanceReviewTest.php`:
- **Bobot ≠ 100% ditolak**.
- Alur lengkap dengan nilai akhir 90 (Istimewa); **hasil tersembunyi sebelum publikasi**.
- **Skor di luar skala ditolak** (diri & atasan, termasuk desimal).
- **Atasan di luar tim ditolak** (juga setelah karyawan pindah tim).
- HRD mengembalikan untuk revisi; versi lama ditolak; HRD tidak memfinalkan penilaiannya
  sendiri.
- Periode tanpa evaluasi diri; rencana pengembangan setelah final; syarat tutup periode;
  akses halaman.

### Catatan

- Satu karyawan, satu penilai (atasan langsung saat periode diaktifkan). Penilaian 360° atau
  multi-penilai belum didukung.
- Bila atasan berubah setelah periode aktif, HRD perlu menyesuaikan penilai. Penggantian
  penilai lewat UI belum ada.
- Pengingat penilaian via notifikasi menyusul di Fase 8.

---

## Fase 5 — Rekrutmen dan onboarding

### Yang dikerjakan

1. **Tabel**:
   - `job_vacancies` (draft/published/closed, kuota, tenggat, slug publik).
   - `applicants` (unik per email).
   - `job_applications`: unik pelamar + lowongan, CV, bukti persetujuan data (waktu & IP), tahap,
     dan `employee_id` **unik** sebagai pencegah konversi ganda.
   - `application_status_histories`.
   - `interviews` (online/offline, interviewer, rekomendasi, ringkasan).
   - `interview_scores` (unik per indikator).
   - `onboarding_records` (checklist).
2. **Halaman karier publik `/karier`** (tanpa login):
   - Hanya lowongan Dipublikasikan yang tenggatnya belum lewat (WIB).
   - Formulir lamaran: nama, email, telepon, pendidikan, alamat, surat lamaran singkat, **CV
     PDF maks. 5 MB**, dan **persetujuan pemrosesan data wajib**.
   - Honeypot anti-spam dan batas 5 kirim/menit per IP.
   - CV disimpan di **disk privat** (`storage/app/private/cv`) dan hanya bisa diunduh lewat route
     berizin.
   - Email yang sama tidak bisa melamar lowongan yang sama dua kali.
   - Login page kini punya tautan "Lihat lowongan kerja".
3. **Tahapan lamaran** (`RecruitmentService`): Lamaran Masuk → Lolos Administrasi / Ditolak →
   Wawancara → Diterima / Ditolak → Menjadi Karyawan. Setiap perubahan tercatat di riwayat
   (siapa, kapan, catatan); penolakan wajib bercatatan.
   - Wawancara hanya untuk yang lolos administrasi. Jadwal harus di masa depan. Interviewer harus
     pengguna aktif berrole HRD/Manager. Wawancara bisa dibatalkan selama belum dinilai.
   - Penilaian hanya oleh **interviewer yang ditugaskan**: skor 1–5 untuk setiap indikator
     (indikator diatur di `system_settings.interview_indicators`), plus rekomendasi dan ringkasan.
     Skor di luar skala ditolak.
   - Keputusan akhir butuh minimal satu wawancara yang sudah dinilai, dan **Diterima dibatasi
     kuota lowongan**.
4. **Onboarding** (`OnboardingService`):
   - Pelamar Diterima dijadikan karyawan + akun login (role Karyawan) + catatan onboarding dalam
     **satu transaksi**. Data karyawan divalidasi dengan aturan yang sama persis dengan form
     karyawan (`OnboardRequest extends EmployeeRequest`).
   - Bila gagal di tengah jalan, akun ikut dibatalkan.
   - Email atur kata sandi baru dikirim setelah commit.
   - Konversi kedua ditolak.
   - Checklist onboarding (kontrak, dokumen, rekening, ID Card, perangkat, orientasi) bisa
     dicentang HRD.
5. **Halaman internal**:
   - `/rekruitmen`: HRD melihat kartu lowongan (jumlah pelamar/baru/diterima, buat/ubah,
     publikasikan/tutup); interviewer melihat "Wawancara Saya".
   - `/rekruitmen/lowongan/{id}`: daftar pelamar per tahap dengan filter & pencarian.
   - `/rekruitmen/lamaran/{id}`: data pelamar, CV, wawancara & skor, seleksi, jadwalkan
     wawancara, form penilaian, keputusan akhir, form onboarding, checklist, riwayat.
6. **Akses**:
   - HRD (`recruitment.manage`) mengelola semua.
   - Manager (`recruitment.view`) **hanya melihat kandidat yang wawancaranya ditugaskan
     kepadanya** (Policy), termasuk CV. Riwayat internal tidak ditampilkan ke Manager.
   - Karyawan tidak punya akses. CV tidak bisa diakses tanpa login.
7. **Seeder**: indikator wawancara (semua lingkungan); 2 lowongan + 2 pelamar contoh dengan CV
   placeholder (hanya lokal).

### Tes dan pemeriksaan (11 Okt 2026)

| Pemeriksaan | Hasil |
| --- | --- |
| `php vendor/bin/pest` (MySQL `humanix_test`) | ✅ 270 lulus, 1461 assertion |
| `php artisan migrate:fresh --seed` (di `humanix_test`) | ✅ |
| `npm run build`, `tsc`, `eslint`, `prettier`, `pint` | ✅ (peringatan chunk `app.js` > 500 kB masih ada) |
| Cek manual browser | ✅ Halaman karier di 1366px & 360px (tanpa overflow/console error); HRD: daftar lowongan, seleksi "Lolos", jadwalkan wawancara (tampil 10:00 WIB) |

Tes baru di `tests/Feature/Recruitment/RecruitmentFlowTest.php`:
- **End-to-end**: lowongan → publikasi → lamaran publik → seleksi → wawancara → penilaian →
  diterima → karyawan + akun + email.
- Konversi ganda ditolak; rollback saat onboarding gagal (validasi, dan kegagalan database
  setelah akun dibuat).
- Validasi lamaran (CV PDF/5 MB, persetujuan, duplikat, lowongan lewat tenggat); honeypot.
- **Skor di luar skala** dan interviewer yang tidak ditugaskan ditolak.
- Manager hanya melihat kandidat yang ditugaskan (termasuk CV); urutan tahap; batas kuota;
  publikasi menolak tenggat lewat.

### Catatan / batasan

- Notifikasi email ke pelamar (jadwal wawancara, hasil) dan ke interviewer menyusul di Fase 8.
- Belum ada fitur penghapusan data pelamar sesuai permintaan (hak subjek data); bisa
  ditambahkan bila dibutuhkan kebijakan privasi perusahaan.
- Di database lokal, pelamar demo **Rizky Pratama** berada di tahap Wawancara (jadwal 12 Okt
  10:00 WIB, interviewer Bayu Pratama) dari hasil pengecekan. Login sebagai
  `manager@humanix.test` untuk mencoba penilaian.

---

## Fase 4 — Payroll

### Yang dikerjakan

1. **Tabel**:
   - `salary_components`: pendapatan/potongan, nominal tetap/persen dari gaji pokok.
   - `employee_salary_structures` + `salary_structure_items`: gaji pokok & komponen per
     karyawan, berlaku sejak tanggal tertentu, dengan riwayat.
   - `payroll_periods`: status, versi, dan siapa/kapan tiap langkah.
   - `payroll_records`: snapshot nama/jabatan/rekening terenkripsi, total, kehadiran.
   - `payroll_items`.
   - `payments`: `idempotency_key` unik, referensi, bukti.
   - Kolom `overtime_requests.payroll_record_id` agar lembur tidak dibayar dua kali.
2. **Uang presisi** dengan `App\Support\Money` (bcmath, string 2 desimal, pembulatan setengah ke
   atas). Tidak ada float dalam perhitungan. Kolom uang `DECIMAL(15,2)`.
3. **`PayrollService`**:
   - **Perhitungan**: gaji pokok + komponen pendapatan + lembur disetujui dalam periode −
     komponen potongan ± penyesuaian manual.
   - **Lembur**: gaji pokok ÷ pembagi (173) × (jam pertama × 1,5 + jam berikutnya × 2). Ketiga
     angka diambil dari `system_settings` dan bisa diubah.
   - **Pajak/BPJS tidak diasumsikan**: dibuat sebagai komponen kosong (BPJS Kesehatan, JHT, JP,
     PPh 21) yang tarifnya diisi HRD.
   - Karyawan tanpa struktur gaji dilewati dan namanya dilaporkan. Karyawan yang nonaktif
     sebelum periode tidak dihitung.
   - Hitung ulang mempertahankan penyesuaian manual dan tidak menggandakan lembur.
   - **Status hanya maju**: Draft → Dihitung → Direview → Disetujui → Ditutup.
     - Setelah Direview, angka terkunci (tidak bisa dihitung ulang/disesuaikan).
     - Persetujuan butuh `payroll.approve` dan ditolak bila ada gaji bersih negatif.
     - Penutupan hanya bila semua slip bergaji > 0 sudah Dibayar.
     - Setiap aksi wajib membawa `version`; versi lama ditolak.
   - **Pembayaran**: instruksi dibuat untuk slip yang belum punya pembayaran aktif. Kunci
     idempotensi deterministik, jadi aman diulang.
     - Alur: Menunggu → Diproses → Dibayar/Gagal; Menunggu → Dibatalkan.
     - **Dibayar wajib nomor referensi atau bukti**; Gagal wajib alasan.
     - Pembayaran gagal → percobaan berikutnya dibuat dengan kunci baru.
     - Bukti disimpan privat.
4. **Halaman**:
   - `/gaji`: daftar periode + buat periode (HRD); pengguna lain diarahkan ke Slip Gaji Saya.
   - `/gaji/periode/{id}`: stepper status, tombol aksi sesuai status & izin, ringkasan total,
     daftar slip, ekspor Excel.
   - `/gaji/slip/{id}`: rincian pendapatan/potongan, penyesuaian manual (saat masih bisa
     diubah), pembayaran & bukti (HRD), unduh PDF.
   - `/gaji/slip-saya`: slip milik karyawan, hanya periode Disetujui/Ditutup.
   - `/gaji/komponen` dan `/gaji/struktur`: kelola komponen dan struktur gaji per karyawan.
5. **Privasi gaji**:
   - Hanya HRD (`payroll.view`) yang melihat semua slip.
   - Karyawan hanya slip miliknya, dan hanya setelah disetujui.
   - Manager dan Administrator tidak bisa melihat gaji orang lain (diuji termasuk dengan
     mengganti ID).
   - Rekening selalu tersamar (`****7890`) di UI, PDF, dan Excel.
6. **Slip gaji PDF** (A5): snapshot data karyawan, rincian, gaji bersih; nominal presisi
   dengan sen.
7. **Ekspor Excel** per periode.
8. **Seeder**: komponen gaji awal (semua lingkungan); struktur gaji demo untuk 6 karyawan demo
   (hanya lokal, angka BPJS demo bukan tarif resmi).

### Tes dan pemeriksaan (10 Okt 2026)

| Pemeriksaan | Hasil |
| --- | --- |
| `php vendor/bin/pest` (MySQL `humanix_test`) | ✅ 261 lulus, 1293 assertion |
| `php artisan migrate:fresh --seed` (di `humanix_test`) | ✅ |
| `npm run build`, `tsc`, `eslint`, `prettier`, `pint` | ✅ (peringatan chunk `app.js` > 500 kB masih ada) |
| Cek manual browser (HRD) | ✅ Buat periode September 2026 → Hitung: 6 slip, total pendapatan Rp65.600.000, potongan Rp1.596.000, bersih Rp64.004.000 (cocok dengan hitungan manual); halaman slip; PDF slip dirender dan dicek visual |

Tes baru:
- `tests/Unit/MoneyTest.php`: pembulatan & presisi.
- `tests/Feature/Payroll/PayrollTest.php`:
  - Perhitungan persis: lembur 120 menit = Rp202.312,14; bersih Rp10.452.312,14.
  - Hitung ulang, karyawan tanpa struktur, transisi maju saja, penguncian setelah review.
  - Versi lama ditolak, gaji negatif, pembayaran idempoten / bukti wajib / percobaan ulang,
    dan penutupan.
  - Slip hanya terlihat setelah disetujui; **karyawan lain, atasan, dan Admin tidak bisa
    melihat gaji orang lain**.
  - Ekspor dan struktur gaji.
- Matriks akses mencakup `/gaji/slip-saya`, `/gaji/komponen`, `/gaji/struktur`.

### Masalah yang ditemukan & diperbaiki

- Nama foreign key terlalu panjang untuk MySQL (batas 64 karakter): tabel item struktur
  dinamai `salary_structure_items`.
- Slip PDF awalnya memakai format Rupiah yang membulatkan ke rupiah penuh, sehingga baris
  bersen tidak cocok dengan total. Kini memakai `Format::uang()` yang mempertahankan sen.
- Nominal besar di kartu KPI terpotong di antara "Rp" dan angka. Kini memakai spasi tak
  terputus.

### Catatan / batasan

- **Tarif PPh 21 & BPJS belum diisi** (sesuai spesifikasi: tidak diasumsikan). HRD perlu
  mengisi di Gaji → Komponen / Struktur Gaji. Perhitungan PPh 21 progresif/TER belum
  diotomatisasi; untuk sekarang diisi sebagai nominal per karyawan.
- Gaji pokok tidak diprorata untuk karyawan yang masuk/keluar di tengah bulan; gunakan
  penyesuaian manual.
- Aturan lembur hari libur/istirahat (pengali berbeda) belum dimodelkan; semua lembur memakai
  rumus jam pertama/berikutnya.
- Notifikasi "Slip gaji tersedia" menyusul di Fase 8.
- Di database lokal sudah ada periode **September 2026** berstatus Dihitung (dibuat saat
  pengecekan). Bisa dilanjutkan untuk mencoba alur review → setujui → bayar → tutup.

---

## Fase 3 — Absensi, cuti, izin, lembur

Dikerjakan dalam tiga commit: 3a absensi, 3b cuti/izin, 3c lembur.

### 3a — Absensi

- **Tabel**: `work_schedules` (jam WIB, hari kerja, toleransi, jendela absen), `employee_schedules`
  (berlaku sejak/sampai), `holidays`, `attendance_records` (unik karyawan + tanggal),
  `attendance_corrections`.
- **Kios absensi** (`/absensi/kios`, izin `attendance.kiosk` milik HRD):
  - Kamera memakai `html5-qrcode`, ditambah input manual / scanner USB.
  - Waktu server yang dipakai, bukan jam perangkat.
  - Scan yang sama dalam 6 detik diabaikan.
- **Aturan `AttendanceService`**:
  - Scan pertama = masuk, scan berikutnya = pulang.
  - Ditolak bila: sebelum jendela masuk dibuka, setelah jam kerja tanpa absen masuk, scan ganda
    dalam 30 menit, sudah masuk & pulang, lewat batas absen pulang, hari libur/akhir pekan, tanpa
    jadwal, QR tidak dikenal/lama, karyawan nonaktif, atau karyawan sedang cuti yang disetujui.
  - Terlambat dihitung dari jam masuk bila melewati toleransi (per jadwal, atau default
    `system_settings`).
  - Baris karyawan dikunci saat scan, dengan unique index sebagai pengaman terakhir.
- **`humanix:mark-absent`** dijadwalkan setiap hari pukul 23:55 WIB. Karyawan terjadwal tanpa
  catatan ditandai Tidak Hadir, atau Cuti/Izin bila cutinya disetujui. Hari libur, akhir pekan,
  karyawan nonaktif, dan karyawan yang belum masuk kerja dilewati. Aman dijalankan ulang.
  **Di server perlu cron `php artisan schedule:run` tiap menit.**
- **Rekap** `/absensi`:
  - Harian berisi ringkasan + "terjadwal belum absen"; bulanan per karyawan.
  - HRD melihat semua, Manager hanya timnya; Karyawan diarahkan ke `/absensi/saya`.
  - Ekspor Excel tersedia untuk HRD.
- **Koreksi HRD**: alasan wajib, nilai lama/baru tersimpan, riwayat koreksi bisa dilihat. Juga
  bisa mencatat absensi manual.
- **Pengaturan**: Jadwal Kerja (CRUD, terapkan ke satu departemen, tidak bisa dihapus bila
  pernah dipakai) dan Hari Libur. Jadwal juga bisa dipilih di form karyawan dan tercatat di
  riwayat karyawan.
- **Seeder** (semua lingkungan): jadwal "Reguler" 08:00–17:00 Senin–Jumat dan libur nasional
  bertanggal tetap. **Libur bergerak (Idulfitri, Iduladha, Nyepi, Waisak, Imlek, dll.) dan cuti
  bersama harus ditambahkan HRD sesuai SKB pemerintah.**

### 3b — Cuti & izin

- **Tabel**: `leave_types` (bersaldo/tidak, kuota default, wajib lampiran), `leave_balances`
  (per tahun), `leave_requests` (kolom `version`), dan `approval_steps` (polimorfik, dipakai
  juga oleh lembur).
- **`ApprovalService`** — alur dari `system_settings.approval_flow` (default Atasan → HRD):
  - Langkah atasan dilewati bila tidak ada atasan aktif.
  - Langkah atasan diotorisasi oleh struktur (atasan langsung yang ditugaskan), sehingga
    supervisor berrole Karyawan tetap bisa menyetujui. Langkah HRD butuh role HRD + izin.
  - Tidak ada yang bisa menyetujui pengajuannya sendiri.
  - Keputusan memakai `lockForUpdate` dan wajib membawa `version`; versi lama ditolak dengan
    pesan "sudah diproses".
- **`LeaveService`**:
  - Hari dihitung dari hari kerja (jadwal + hari libur).
  - Validasi: tanggal bentrok dengan pengajuan Menunggu/Disetujui, saldo cukup (pengajuan yang
    masih menunggu ikut dihitung), cuti bersaldo tidak boleh lintas tahun, lampiran wajib
    (mis. Sakit), PDF/JPG/PNG maks. 2 MB.
  - Saldo dipotong dalam transaksi yang sama dengan persetujuan akhir. Absensi "Tidak Hadir" di
    rentang cuti diubah menjadi "Cuti/Izin".
  - Pembatalan hanya oleh pemilik saat Menunggu, dan tetap tersimpan.
- **Halaman** `/cuti-izin`: tab Pengajuan Saya (dengan kartu saldo), Perlu Persetujuan (dengan
  badge jumlah), dan Semua Pengajuan (HRD semua, Manager timnya). Halaman detail menampilkan
  timeline persetujuan, panel Setujui/Tolak (penolakan wajib beralasan), dan Batalkan.
  Lampiran disimpan privat.
- **Jenis awal**: Cuti Tahunan (12 hari), Sakit (wajib lampiran), Izin Pribadi, Cuti Penting.

### 3c — Lembur

- **Tabel** `overtime_requests`: tanggal WIB, jam mulai/selesai (UTC), durasi, `version`, dan
  `payable`.
- **Validasi**:
  - Jam selesai harus setelah jam mulai (lintas tengah malam belum didukung); minimal 30 menit.
  - Di hari kerja, lembur tidak boleh berada di dalam jam kerja jadwal.
  - **Tidak boleh tumpang tindih** dengan pengajuan Menunggu/Disetujui.
  - Total per hari ≤ `system_settings.max_minutes_per_day` (240).
  - Tanggal maksimal ±30 hari.
- Memakai alur persetujuan yang sama. Disetujui → `payable = true`, siap dibaca payroll di Fase 4.
- **Halaman** `/lembur`: tab seperti Cuti/Izin; ringkasan menit disetujui bulan ini dan yang
  masih menunggu.

### Perbaikan selama fase ini

- **Zona waktu**: Carbon berzona WIB yang disimpan ke kolom waktu tersimpan apa adanya
  (08:00 WIB terbaca 15:00 WIB). Kini model absensi & lembur selalu mengonversi ke UTC. Data
  absensi demo lokal sudah dibuat ulang. Bug ini ditemukan oleh tes.
- **MariaDB 10.4** menolak kolom `timestamp` NOT NULL tanpa default. Kolom lembur diubah ke
  `dateTime` (perbaikan oleh Budi, dirapikan).
- **Kios** sempat blank di mode pengembangan (React StrictMode memanggil start/stop kamera
  bersamaan). Kini transisi kamera diantrikan.
- **DataTable** mengirim request ganda saat halaman dibuka, dan **toast** tampil dua kali
  (keduanya akibat StrictMode). Keduanya diperbaiki.

### Tes dan pemeriksaan (10 Okt 2026)

| Pemeriksaan | Hasil |
| --- | --- |
| `php vendor/bin/pest` (MySQL `humanix_test`) | ✅ 240 lulus, 1138 assertion |
| `php artisan migrate:fresh --seed` (di `humanix_test`) | ✅ |
| `npm run build`, `tsc`, `eslint`, `prettier`, `pint` | ✅ (peringatan chunk `app.js` > 500 kB masih ada) |
| Cek manual browser | ✅ Rekap absensi; kios (kamera diblokir di pane → pesan jelas, input manual berjalan); Cuti/Izin; ajukan & batalkan lembur lewat UI |

Tes baru:
- `tests/Feature/Attendance/AttendanceScanTest.php`: aturan scan, jendela waktu, terlambat
  vs toleransi, scan ganda, libur/akhir pekan, QR lama, tanggal kerja WIB vs UTC, izin kios,
  dan penandaan malam.
- `tests/Feature/Attendance/AttendanceRecapTest.php`: rekap HRD/Manager/Karyawan, bulanan,
  koreksi & validasinya, ekspor, jadwal kerja, hari libur, dan ganti jadwal karyawan.
- `tests/Feature/Leave/LeaveRequestTest.php`: hitung hari kerja, langkah persetujuan, alur
  penuh + saldo + absensi, penolakan, **versi lama ditolak**, larangan menyetujui diri sendiri,
  supervisor, bentrok/saldo/lintas tahun, lampiran privat, pembatalan, akses horizontal, dan
  integrasi kios.
- `tests/Feature/Overtime/OvertimeRequestTest.php`: durasi & UTC, validasi jam kerja,
  **duplikasi/tumpang tindih**, batas harian, alur persetujuan → payable, dan akses horizontal.
- Matriks akses kini memakai akun yang punya data karyawan dan mencakup halaman absensi baru.

### Catatan / batasan

- Shift lintas tengah malam (absensi & lembur) belum didukung.
- Belum ada UI untuk mengelola jenis cuti dan menyesuaikan saldo per karyawan (sementara lewat
  seeder/database). Saldo dibuat otomatis dari kuota default saat pertama dipakai.
- Notifikasi ke penyetuju/pemohon menyusul di Fase 8.
- Di Windows, proses `pail` di `composer run dev` gagal karena butuh ekstensi `pcntl`; proses
  lain tetap berjalan.

---

## Fase 2 — Modul karyawan

### Yang dikerjakan

1. **Tabel** (`2026_10_10_300000_create_employee_tables`):
   - `employees`: NIK unik, `user_id` unik, departemen, jabatan, `manager_id` (atasan), status
     kepegawaian (Tetap/Kontrak/Masa Percobaan/Magang), tanggal masuk, akhir kontrak, foto,
     `qr_token` acak 48 karakter (unik), `is_active`, `deactivated_at`, soft delete
   - `bank_accounts`: nomor rekening dengan cast `encrypted` (terenkripsi di database)
   - `employee_histories`: riwayat perubahan yang dibaca manusia (pelaku, nilai lama → baru)
   - FK `departments.head_employee_id` → `employees`
2. **`EmployeeService`** menjadi tempat aturan bisnis:
   - Membuat karyawan dan akun login (role Karyawan) dalam **satu transaksi**. Jika gagal, akun
     dan foto ikut dibatalkan (diuji). Tautan atur kata sandi dikirim lewat email (token
     Fortify 60 menit, bisa dikirim ulang oleh HRD).
   - Mengubah data dengan riwayat diff. Foto dan rekening hanya dicatat "diperbarui", nilainya
     tidak disimpan. Nomor rekening kosong saat ubah berarti tetap memakai yang lama.
   - Nonaktifkan/aktifkan (bukan hapus), ganti QR, ganti role, dan update profil sendiri.
3. **Data Karyawan (`/karyawan`)**: daftar dengan pencarian (nama/NIK/email), filter
   departemen/kepegawaian/status, pagination, kartu di ponsel, dan **ekspor Excel** sesuai
   filter (tanpa rekening/token). Ada form tambah/ubah (data pribadi, kepegawaian, rekening;
   jabatan difilter sesuai departemen) dan halaman detail (data, rekening tersamar `****1234`,
   riwayat, aksi akun).
4. **Validasi** (`EmployeeRequest`):
   - NIK dan email unik.
   - Akhir kontrak wajib untuk status kontrak.
   - Jabatan harus milik departemen yang dipilih.
   - Departemen/jabatan/atasan harus aktif.
   - Atasan tidak boleh diri sendiri atau bawahannya (cegah siklus).
   - Foto JPG/PNG maks. 2 MB; rekening 6–20 digit.
5. **Otorisasi** (`EmployeePolicy` + scope `visibleTo($user)`):
   - HRD: semua akses.
   - Administrator: lihat semua dan ganti role akun, tetapi tidak bisa mengubah data atau
     melihat rekening.
   - Manager: hanya bawahan langsung dan tidak langsung, baca saja.
   - Karyawan: hanya dirinya sendiri.
   - Foto disimpan di disk privat dan diambil lewat route berizin.
6. **Profil Saya (`/profil-saya`)**: karyawan hanya bisa mengubah telepon, alamat, dan foto.
   NIK, jabatan, departemen, status, dll. **ditolak di server** dengan pesan
   "… hanya dapat diubah oleh HRD." Bisa mengunduh ID Card sendiri. Tautan ada di menu profil.
7. **ID Card PDF** ukuran CR80 (85,6 × 54 mm). Isinya logo, foto/inisial, nama, jabatan, NIK,
   departemen, dan QR berisi token acak (bukan NIK). QR dibuat dengan `bacon/bacon-qr-code` (GD).
   Font subsetting aktif, sehingga ukuran file turun dari ~900 KB ke ~38 KB. Sudah dicek
   visual di browser.
8. **Karyawan nonaktif tidak bisa login**: login ditolak dengan pesan yang jelas, dan sesi yang
   masih terbuka diakhiri (`EnsureEmployeeIsActive`). HRD tidak bisa menonaktifkan dirinya sendiri.
9. **Penghapusan akun mandiri dimatikan.** Fitur bawaan starter kit di Pengaturan Profil
   dihapus, karena akan menghapus akun yang terhubung ke data karyawan.
10. **Organisasi**: kepala departemen kini bisa dipilih (harus karyawan aktif di departemen
    itu). Departemen dan jabatan yang masih dipakai karyawan tidak bisa dihapus.
11. **Seeder demo lokal**: 6 karyawan dengan rantai atasan Manager → Supervisor → Staff. Akun
    demo HRD/Manager/Karyawan terhubung ke data karyawan; Administrator sengaja tidak.

### Akun demo lokal tambahan (kata sandi `password`)

`agus@humanix.test` (supervisor, bawahan Manager), `dewi@humanix.test`, `eko@humanix.test`.

### Tes dan pemeriksaan yang dijalankan (10 Okt 2026)

| Pemeriksaan | Hasil |
| --- | --- |
| `php vendor/bin/pest` (MySQL `humanix_test`) | ✅ 182 lulus, 787 assertion |
| `php artisan migrate:fresh --seed` (di `humanix_test`) | ✅ |
| `npm run build`, `tsc`, `eslint`, `prettier`, `pint` | ✅ (peringatan chunk `app.js` > 500 kB masih ada) |
| Cek manual browser (HRD: daftar, detail, form di 360px; ID Card PDF) | ✅ tanpa console error / overflow |

Tes baru di `tests/Feature/Employees/`:
- `EmployeeManagementTest`: buat karyawan + akun + email, rollback transaksi, validasi,
  riwayat, nonaktif → login ditolak & sesi diakhiri, ganti QR, PDF, Excel, ganti role
  (hanya Admin), dan kepala departemen.
- `EmployeeAccessTest`: akses horizontal dengan mengganti ID di URL. Diuji untuk Manager
  (luar tim → 403), Karyawan (data/foto/ID Card orang lain → 403), Administrator (tanpa
  rekening/ubah), rekening tersamar, dan foto yang tidak bisa diakses publik.
- `OwnProfileTest`: ubah telepon/alamat/foto, field terkunci ditolak, dan ID Card sendiri.

Bug yang ditemukan tes lalu diperbaiki: riwayat perubahan gagal (error 500) karena tanggal
memakai `CarbonImmutable`.

### Catatan

- Pilihan kepala departemen mengirim semua karyawan aktif ke halaman Departemen. Untuk ribuan
  karyawan nanti sebaiknya diganti pencarian (autocomplete).
- Notifikasi ke atasan/karyawan dan halaman audit log masih menunggu Fase 8.
- Email tautan kata sandi memakai `MAIL_MAILER=log` di lokal (lihat `storage/logs/laravel.log`);
  SMTP produksi diatur di Fase 10.

---

## Fase 1 — Database, role, permission, struktur organisasi

### Yang dikerjakan

1. **Logo asli HUMANIX.** File `public/images/logo-humanix.png` dipakai di sidebar (logo
   lengkap) dan di login versi ponsel. Ikonnya dipotong otomatis menjadi
   `public/images/humanix-icon.png`, dipakai untuk sidebar yang diciutkan, panel login,
   halaman error, `favicon.png`, dan `apple-touch-icon.png`. Placeholder SVG dihapus.
2. **Tabel baru** (`2026_10_10_200000_create_organization_tables`):
   - `departments`: kode/nama unik, `parent_id` ke dirinya sendiri, `head_employee_id`
     (FK ke employees menyusul di Fase 2), `is_active`
   - `positions`: kode unik, nama unik per departemen, `department_id` boleh kosong untuk
     jabatan lintas departemen, level 1–10, `is_active`
   - `system_settings`: key/value JSON, dengan cache
   - `audit_logs`: pelaku, event, model, nilai lama/baru, IP, user agent
   - Tabel spatie (`roles`, `permissions`, pivot) dari Fase 0.
3. **Role & permission** (`app/Enums/Role.php`, `RolesAndPermissionsSeeder`, idempotent dan
   aman untuk produksi). Ada 4 role dan 28 permission per modul, misalnya
   `employees.view`, `payroll.approve`, `settings.manage`, `organization.view`.
   **Administrator tidak diberi izin `payroll.*` dan `performance.*`.**
4. **Middleware `role:`, `permission:`, `role_or_permission:`** didaftarkan. Semua halaman
   (termasuk placeholder modul) sudah dijaga izinnya. Akses ditolak memakai halaman 403 HUMANIX.
5. **Policy** `DepartmentPolicy` dan `PositionPolicy`, dipanggil dari Form Request dan
   controller (`Gate::authorize`).
6. **Redirect per role:** Fortify tetap ke `/dashboard`, lalu diteruskan ke `/admin/dashboard`,
   `/hr/dashboard`, `/manager/dashboard`, atau `/me/dashboard`. Akun tanpa role mendapat 403
   dengan pesan untuk menghubungi Administrator.
7. **Dashboard per role.** Admin menampilkan angka nyata: jumlah akun, departemen aktif,
   jabatan aktif, dan aktivitas audit hari ini. HRD/Manager/Karyawan menampilkan KPI sesuai
   perannya dengan empty state sampai datanya ada.
8. **Sidebar mengikuti izin.** Menu disaring dari `auth.permissions`; menu Dashboard menuju
   dashboard role. Header menampilkan role asli. Data user ke browser hanya field terpilih,
   bukan model mentah.
9. **Halaman Pengaturan** (hub) → **Departemen** dan **Jabatan** (CRUD lewat dialog,
   pencarian, filter status/departemen, pagination, tampilan kartu di ponsel, konfirmasi
   hapus) → **Struktur Organisasi** (pohon departemen aktif + jabatan, bisa diciutkan;
   bisa dilihat semua role di `/organisasi/struktur`).
   - Validasi: kode huruf kapital/angka/strip, kode & nama unik, induk tidak boleh diri
     sendiri atau turunannya (cek siklus).
   - Departemen yang masih punya sub-departemen atau jabatan tidak bisa dihapus; opsinya
     dinonaktifkan.
10. **Trait `Auditable`** pada User, Department, Position, SystemSetting. Field sensitif
    disaring (`password`, `remember_token`, `two_factor_*`, `account_number`, `qr_token`,
    serta field apa pun yang mengandung password/secret/token). IP hanya dicatat untuk
    request HTTP.
11. **Komponen baru:** `DataTable` (search, filter, pagination, empty state, kartu ponsel),
    `ConfirmDialog`, `Toaster` (flash success/error), `FormField`.
12. **Perintah** `php artisan humanix:assign-role {email} {role}` untuk menyiapkan
    Administrator pertama di produksi.
13. **Seeder lokal:** 4 akun demo (lihat bawah), 6 departemen, dan 11 jabatan contoh. Hanya
    berjalan saat `APP_ENV=local`.
14. Pesan validasi Indonesia kini diawali huruf kapital ("Kode wajib diisi.").

### Akun demo lokal (kata sandi `password`, hanya `APP_ENV=local`)

| Role | Email |
| --- | --- |
| Administrator | admin@humanix.test |
| HRD | hrd@humanix.test |
| Manager | manager@humanix.test |
| Karyawan | karyawan@humanix.test |

### Tes dan pemeriksaan yang dijalankan (10 Okt 2026)

| Pemeriksaan | Hasil |
| --- | --- |
| `php vendor/bin/pest` (MySQL `humanix_test`) | ✅ 159 lulus, 580 assertion |
| `php artisan migrate:fresh --seed` (di `humanix_test`) | ✅ 4 user, 6 departemen, 11 jabatan, 28 permission |
| `npm run build` | ✅ sukses (peringatan chunk `app.js` > 500 kB masih ada) |
| `tsc --noEmit`, `eslint`, `prettier --check`, `pint --test` | ✅ bersih |
| Cek manual browser (Admin & HRD; dashboard, Departemen, dialog validasi, Struktur) | ✅ tidak ada console error; Departemen di 360px tanpa overflow horizontal |

Tes baru:
- `tests/Feature/Authorization/RoleAccessTest.php`: matriks 4 role × 16 halaman (64 kasus),
  redirect login per role, Admin tanpa izin gaji/kinerja, izin yang dikirim ke frontend,
  Manager/Karyawan tidak bisa POST/PUT/DELETE data master, dan perintah assign-role.
- `tests/Feature/Organization/DepartmentTest.php`, `PositionTest.php`: CRUD, validasi,
  keunikan, cek siklus, dan larangan hapus.
- `tests/Feature/Organization/StructureAndAuditTest.php`: pohon struktur, audit
  create/update/delete beserta pelaku, password/2FA tidak pernah tercatat, dan
  system settings.

Bug yang ditemukan tes lalu diperbaiki:
- Departemen/jabatan yang dibuat tanpa field `is_active` tersimpan sebagai **nonaktif**.
  Sekarang defaultnya aktif, dan update tanpa field tidak mengubah status.
- Audit log tidak mencatat IP karena pengecekan konsol. Sekarang berdasarkan ada/tidaknya
  route HTTP.

### Catatan

- `head_employee_id` (kepala departemen) belum bisa diisi dari UI; menunggu tabel karyawan di Fase 2.
- Penghapusan jabatan belum mengecek pemakaian oleh karyawan; ditambahkan di Fase 2.
- Halaman Audit Log untuk Admin dan UI Pengaturan Sistem (`system_settings`) belum dibuat
  (sesuai rencana: audit log di Fase 8; pengaturan sistem diedit bertahap di fase modulnya).
- Manajemen akun pengguna (buat akun + pilih role) via UI menyusul bersama Fase 2. Untuk saat
  ini role diberikan lewat seeder atau `humanix:assign-role`.
- Matriks akses Manager untuk "data tim saja" (scope bawahan) baru bisa diterapkan setelah
  tabel karyawan ada (Fase 2, `visibleTo($user)`).

---

## Fase 0 — Fondasi proyek dan autentikasi

### Lingkungan (laptop pengembang)

- PHP 8.2.12 (XAMPP), ekstensi `gd`, `intl`, `pdo_mysql`, `zip`, `mbstring`, `fileinfo` aktif
- Composer 2.10.0, Node 24.21.0, npm 11.19.0, Git 2.54
- Database: MariaDB 10.4.32 bawaan XAMPP (bukan MySQL 8; lihat catatan)
- Database `humanix` (pengembangan) dan `humanix_test` (tes), utf8mb4

### Yang dikerjakan

1. **Proyek dasar.** Laravel 12 + React Starter Kit resmi (Inertia 2, React 19,
   TypeScript, Tailwind 4, Vite 7, autentikasi Fortify, Wayfinder). Breeze tidak dipasang.
   Kit diambil dari commit terakhir yang masih Laravel 12 (`laravel/react-starter-kit@8f79a71`,
   16 Mar 2026), karena versi `main` sekarang butuh PHP 8.3 + Laravel 13, sedangkan versi
   di Packagist masih rilis lama tanpa Fortify. Skeleton Laravel polos yang sebelumnya ada
   di folder ini (belum pernah diubah) diganti.
2. **Konfigurasi.** `.env`: MySQL `humanix`, `APP_LOCALE=id`, `APP_FAKER_LOCALE=id_ID`,
   `APP_TIMEZONE=UTC` (penyimpanan) dan `APP_DISPLAY_TIMEZONE=Asia/Jakarta` (tampilan).
   `.env.example` tanpa rahasia (APP_KEY kosong). `.env` ada di `.gitignore`.
   `phpunit.xml` memakai MySQL `humanix_test` dan mematikan Debugbar.
3. **Paket.**
   - Composer: `spatie/laravel-permission` 6.25 (config dan migrasi sudah dipublikasikan),
     `barryvdh/laravel-dompdf` 3.1, `maatwebsite/excel` 3.1, `bacon/bacon-qr-code` 3.0,
     dev: `barryvdh/laravel-debugbar` 3.16 (hanya lokal), `pestphp/pest` 3.8 +
     `pest-plugin-laravel` 3.2
   - npm: `recharts` 3, `html5-qrcode` 2.3.8, `@fontsource-variable/plus-jakarta-sans`
4. **Token desain** di `resources/css/app.css`: navy `#071B49`, primary `#0057FF`,
   primary-bright `#007BFF`, cyan `#00C8F0`, background `#F5F8FF`, surface `#FFFFFF`,
   teks `#111827`/`#6B7280`, success/warning/danger `#16A34A`/`#F59E0B`/`#DC2626`,
   border `#E5E7EB`, radius kartu 12px dan kontrol 10px. Font Plus Jakarta Sans
   (self-hosted, tanpa CDN). Tema terang menjadi default.
5. **AppLayout.**
   - Sidebar putih: logo HUMANIX, menu Dashboard, Data Karyawan, Rekruitmen, Absensi,
     Cuti/Izin, Lembur, Gaji, Penilaian Kinerja, Laporan, Pengaturan. Item aktif biru
     `#0057FF` dengan ikon putih. Kotak helpdesk biru-cyan di bawah. Bisa diciutkan ke ikon,
     dan menjadi drawer di ponsel.
   - Header: pencarian global, lonceng notifikasi dengan badge (muncul jika > 0), kartu
     profil (avatar, nama, role) dengan dropdown Profil & Keamanan / Keluar.
   - Konfigurasi menu ada di `resources/js/lib/navigation.ts`.
6. **Komponen dasar:** `KpiCard`, `PageHeader`, `StatusBadge`, `Breadcrumbs` (bawaan kit),
   halaman error Inertia 403/404/419/500/503 (`pages/error.tsx`, dirender dari
   `bootstrap/app.php`; 500/503 tetap memakai halaman debug saat `APP_DEBUG=true`).
7. **Dashboard awal:** sapaan dengan tanggal dan jam WIB, 4 KPI card, dan 2 kartu grafik
   dengan empty state. Belum ada angka dummy.
8. **Menu modul:** setiap menu selain Dashboard membuka halaman placeholder
   "Modul sedang disiapkan — dibangun pada Fase N" (`routes/web.php`, nama route `modules.*`).
9. **Autentikasi (Bahasa Indonesia).**
   - Halaman login HUMANIX: email + kata sandi dengan tombol tampil/sembunyi, Ingat saya,
     Lupa kata sandi?
   - Pesan error generik (email tidak terdaftar dan kata sandi salah memberi pesan sama).
   - Rate limit login 5x/menit per email+IP.
   - Reset kata sandi memakai token Fortify, kedaluwarsa 60 menit (`config/auth.php`).
   - Halaman lupa/atur ulang kata sandi sudah diterjemahkan.
   - File bahasa `lang/id/{auth,passwords,validation,pagination}.php`.
   - Registrasi publik dimatikan (`config/fortify.php`): akun dibuat oleh HRD/Admin.
   - `/` mengarahkan ke login atau dashboard.
10. **Helper format Indonesia:** `resources/js/lib/format.ts` dan `app/Support/Format.php`,
    misalnya "Rabu, 7 Oktober 2026", "08:00 WIB", "Rp 650.000.000", dan sapaan
    Pagi/Siang/Sore/Malam.
11. **Seeder demo lokal:** `DatabaseSeeder` membuat `admin@humanix.test` (kata sandi factory
    `password`) **hanya jika `APP_ENV=local`**.

### Tes dan pemeriksaan yang dijalankan (10 Okt 2026)

| Pemeriksaan | Hasil |
| --- | --- |
| `php vendor/bin/pest` (MySQL `humanix_test`) | ✅ 68 lulus, 331 assertion |
| `npm run build` | ✅ sukses; peringatan: chunk `app.js` 521 kB (> 500 kB) |
| `npx tsc --noEmit` | ✅ bersih |
| `npx eslint resources/js` | ✅ bersih (2 error bawaan kit ikut diperbaiki dengan `--fix`) |
| `php vendor/bin/pint --test` | ✅ bersih |
| Cek manual di browser (login → dashboard → menu Gaji) | ✅ di 1366px dan 360px tidak ada overflow horizontal dan tidak ada console error |

Tes baru: `tests/Feature/FoundationTest.php` (redirect beranda, login berbahasa Indonesia,
registrasi mati, pesan error generik, rate limit, logout, menu modul butuh login dan bisa
dibuka, shared props header, halaman 404) dan `tests/Unit/FormatTest.php`. Tes bawaan kit
(login, reset kata sandi, verifikasi email, 2FA, profil, keamanan) tetap lulus.

### Catatan dan penyimpangan dari spesifikasi

- **simple-qrcode tidak bisa dipasang.** `simplesoftwareio/simple-qrcode` (versi terbaru 4.2)
  butuh `bacon/bacon-qr-code` ^2, sedangkan Fortify (2FA) butuh ^3. Penggantinya
  `bacon/bacon-qr-code` ^3 langsung, yaitu mesin yang sama dengan yang dibungkus simple-qrcode,
  untuk QR di ID Card (Fase 2).
- **Versi paket menyesuaikan PHP 8.2:** spatie/laravel-permission 6.x (8.x butuh PHP 8.3),
  maatwebsite/excel 3.1 (4.x butuh PHP 8.3), Pest 3 (Pest 4 butuh PHP 8.3).
- **Database lokal MariaDB 10.4,** bukan MySQL 8 seperti di spesifikasi. Migrasi dan tes
  berjalan, tetapi sebaiknya produksi/CI memakai MySQL 8 dan diuji ulang di sana.
- **Workflow GitHub bawaan kit** (`.github/workflows/tests.yml`, `lint.yml`) belum
  disesuaikan dengan MySQL dan PHP 8.2; perlu diperbarui sebelum repo di-push (Fase 9).
- **Belum dibuat di Fase 0** (dipindah ke fase saat pertama dibutuhkan): `DataTable` (search,
  filter, sort, pagination), `ConfirmDialog`, `Toast`, `FormField`, skeleton loading khusus,
  bottom nav ponsel, dan pencarian global yang berfungsi (saat ini kotaknya baru tampilan).
- Label role di header masih "Pengguna" dan badge notifikasi bernilai 0 sampai Fase 1 (role)
  dan Fase 8 (notifikasi).
- Logo masih placeholder (`public/images/logo-humanix.svg`, juga `favicon.svg`); ganti dengan
  file logo asli.

### Cara menjalankan lokal

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate   # sesuaikan DB_* bila perlu
php artisan migrate --seed
composer run dev        # atau: php artisan serve + npm run dev
php vendor/bin/pest     # butuh database humanix_test
```

## Langkah berikutnya

Seluruh 11 fase selesai. Disarankan berikutnya:

1. Konfigurasi SMTP dan uji pengiriman email sungguhan.
2. Deploy ke server staging/produksi mengikuti `docs/DEPLOYMENT.md`, lalu uji restore pertama.
3. Uji di MySQL 8 (server produksi) dengan `php artisan test` terhadap database tes di server tersebut.
