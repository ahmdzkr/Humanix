<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Pencarian global di header: halaman yang boleh dibuka pengguna + karyawan yang boleh dilihatnya.
 * Hasil karyawan memakai scope visibleTo yang sama dengan halaman Data Karyawan (tidak ada kebocoran).
 */
class SearchController extends Controller
{
    /** Halaman: [judul, path, izin (salah satu; kosong = semua), kata kunci tambahan]. */
    private const PAGES = [
        ['Dashboard', '/dashboard', [], 'beranda ringkasan'],
        ['Data Karyawan', '/karyawan', ['employees.view'], 'pegawai staf'],
        ['Tambah Karyawan', '/karyawan/tambah', ['employees.manage'], 'pegawai baru'],
        ['Rekrutmen', '/rekruitmen', ['recruitment.view'], 'lowongan pelamar wawancara'],
        ['Absensi', '/absensi', ['attendance.view'], 'kehadiran rekap'],
        ['Absensi Saya', '/absensi/saya', ['attendance.self'], 'kehadiran riwayat'],
        ['Kios Absensi', '/absensi/kios', ['attendance.kiosk'], 'scan qr kamera'],
        ['Cuti/Izin', '/cuti-izin', ['leave.view', 'leave.self'], 'cuti izin sakit'],
        ['Lembur', '/lembur', ['overtime.view', 'overtime.self'], 'overtime'],
        ['Gaji & Payroll', '/gaji', ['payroll.view'], 'payroll periode'],
        ['Slip Gaji Saya', '/gaji/slip-saya', ['payroll.self'], 'gaji slip'],
        ['Komponen Gaji', '/gaji/komponen', ['payroll.manage'], 'bpjs pph tunjangan potongan'],
        ['Struktur Gaji', '/gaji/struktur', ['payroll.manage'], 'gaji pokok'],
        ['Penilaian Kinerja', '/penilaian-kinerja', ['performance.view', 'performance.self'], 'kpi evaluasi'],
        ['Laporan', '/laporan', ['reports.view'], 'ekspor excel rekap'],
        ['Notifikasi', '/notifikasi', [], 'pemberitahuan'],
        ['Profil Saya', '/profil-saya', [], 'id card qr'],
        ['Pengaturan', '/pengaturan', ['settings.manage', 'departments.manage', 'positions.manage', 'attendance.manage'], 'setting'],
        ['Pengaturan Sistem', '/pengaturan/sistem', ['settings.manage'], 'nama perusahaan toleransi'],
        ['Departemen', '/pengaturan/departemen', ['departments.manage'], 'divisi'],
        ['Jabatan', '/pengaturan/jabatan', ['positions.manage'], 'posisi'],
        ['Jadwal Kerja', '/pengaturan/jadwal-kerja', ['attendance.manage'], 'shift jam kerja'],
        ['Hari Libur', '/pengaturan/hari-libur', ['attendance.manage'], 'libur nasional cuti bersama'],
        ['Struktur Organisasi', '/organisasi/struktur', ['organization.view'], 'bagan'],
        ['Audit Log', '/audit-log', ['audit.view'], 'riwayat perubahan log'],
    ];

    public function __invoke(Request $request): JsonResponse
    {
        $q = Str::of((string) $request->query('q', ''))->trim()->limit(60, '')->toString();
        $user = $request->user();

        if (mb_strlen($q) < 2) {
            return response()->json(['pages' => [], 'employees' => []]);
        }

        $needle = mb_strtolower($q);
        $pages = collect(self::PAGES)
            ->filter(fn ($p) => $p[2] === [] || collect($p[2])->contains(fn ($perm) => $user->can($perm)))
            ->filter(fn ($p) => str_contains(mb_strtolower("{$p[0]} {$p[3]}"), $needle))
            ->take(5)
            ->map(fn ($p) => ['title' => $p[0], 'url' => $p[1]])
            ->values();

        $employees = $user->can('employees.view')
            ? Employee::query()->visibleTo($user)
                ->where(fn ($w) => $w->where('full_name', 'like', "%{$q}%")->orWhere('nik', 'like', "%{$q}%"))
                ->with('position:id,name')->orderBy('full_name')->limit(6)
                ->get(['id', 'nik', 'full_name', 'position_id', 'is_active'])
                ->map(fn (Employee $e) => [
                    'title' => $e->full_name,
                    'subtitle' => trim($e->nik.' · '.($e->position?->name ?? '').($e->is_active ? '' : ' · Nonaktif'), ' ·'),
                    'url' => route('employees.show', $e, absolute: false),
                ])
            : collect();

        return response()->json(['pages' => $pages, 'employees' => $employees]);
    }
}
