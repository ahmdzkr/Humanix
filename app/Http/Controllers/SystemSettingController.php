<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pengaturan sistem yang boleh diubah Administrator. Setiap perubahan tercatat di audit log
 * (model SystemSetting memakai Auditable); nilai yang tidak berubah tidak disimpan ulang.
 */
class SystemSettingController extends Controller
{
    /** Kunci yang dapat diubah lewat halaman ini, dengan aturan validasinya. */
    private const RULES = [
        'company_name' => ['required', 'string', 'max:150'],
        'late_tolerance_minutes' => ['required', 'integer', 'min:0', 'max:180'],
        'max_minutes_per_day' => ['required', 'integer', 'min:30', 'max:720'],
        'overtime_hourly_divisor' => ['required', 'integer', 'min:1', 'max:400'],
        'overtime_first_hour_multiplier' => ['required', 'numeric', 'min:1', 'max:5', 'decimal:0,2'],
        'overtime_next_hour_multiplier' => ['required', 'numeric', 'min:1', 'max:5', 'decimal:0,2'],
        'interview_indicators' => ['required', 'array', 'min:1', 'max:10'],
        'interview_indicators.*' => ['required', 'string', 'max:80', 'distinct'],
    ];

    private const ATTRIBUTES = [
        'company_name' => 'nama perusahaan',
        'late_tolerance_minutes' => 'toleransi keterlambatan',
        'max_minutes_per_day' => 'maksimal lembur per hari',
        'overtime_hourly_divisor' => 'pembagi upah per jam',
        'overtime_first_hour_multiplier' => 'pengali jam pertama',
        'overtime_next_hour_multiplier' => 'pengali jam berikutnya',
        'interview_indicators' => 'indikator wawancara',
        'interview_indicators.*' => 'indikator wawancara',
    ];

    public function index(): Response
    {
        $keys = array_filter(array_keys(self::RULES), fn ($k) => ! str_contains($k, '.'));

        return Inertia::render('settings/system', [
            'settings' => collect($keys)->mapWithKeys(fn ($k) => [$k => SystemSetting::get($k)]),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(self::RULES, [], self::ATTRIBUTES);

        $data['company_name'] = trim($data['company_name']);
        $data['late_tolerance_minutes'] = (int) $data['late_tolerance_minutes'];
        $data['max_minutes_per_day'] = (int) $data['max_minutes_per_day'];
        $data['overtime_hourly_divisor'] = (int) $data['overtime_hourly_divisor'];
        // Pengali disimpan sebagai string agar perhitungan bcmath tetap presisi.
        $data['overtime_first_hour_multiplier'] = (string) (0 + $data['overtime_first_hour_multiplier']);
        $data['overtime_next_hour_multiplier'] = (string) (0 + $data['overtime_next_hour_multiplier']);
        $data['interview_indicators'] = array_values(array_map('trim', $data['interview_indicators']));

        DB::transaction(function () use ($data) {
            foreach ($data as $key => $value) {
                $setting = SystemSetting::query()->where('key', $key)->firstOrFail();

                if ($setting->value !== $value) {
                    $setting->update(['value' => $value]);
                }
            }
        });

        return back()->with('success', 'Pengaturan sistem berhasil disimpan.');
    }
}
