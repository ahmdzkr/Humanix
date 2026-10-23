<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Halaman menu Pengaturan: daftar pengaturan yang boleh dibuka pengguna.
 */
class SettingsHubController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        $items = collect([
            [
                'key' => 'system',
                'title' => 'Pengaturan Sistem',
                'description' => 'Nama perusahaan, toleransi terlambat, aturan lembur, indikator wawancara.',
                'href' => route('system-settings.index', absolute: false),
                'allowed' => $user->can('settings.manage'),
            ],
            [
                'key' => 'departments',
                'title' => 'Departemen',
                'description' => 'Kelola departemen dan hierarki induk-anak.',
                'href' => route('departments.index', absolute: false),
                'allowed' => $user->can('departments.manage'),
            ],
            [
                'key' => 'positions',
                'title' => 'Jabatan',
                'description' => 'Kelola jabatan, level, dan departemennya.',
                'href' => route('positions.index', absolute: false),
                'allowed' => $user->can('positions.manage'),
            ],
            [
                'key' => 'structure',
                'title' => 'Struktur Organisasi',
                'description' => 'Lihat bagan departemen dan jabatan aktif.',
                'href' => route('organization.structure', absolute: false),
                'allowed' => $user->can('organization.view'),
            ],
            [
                'key' => 'schedules',
                'title' => 'Jadwal Kerja',
                'description' => 'Jam kerja, hari kerja, toleransi terlambat.',
                'href' => route('work-schedules.index', absolute: false),
                'allowed' => $user->can('attendance.manage'),
            ],
            [
                'key' => 'holidays',
                'title' => 'Hari Libur',
                'description' => 'Hari libur nasional dan cuti bersama.',
                'href' => route('holidays.index', absolute: false),
                'allowed' => $user->can('attendance.manage'),
            ],
        ])->where('allowed', true)->map(fn ($item) => collect($item)->except('allowed'))->values();

        return Inertia::render('settings-hub', ['items' => $items]);
    }
}
