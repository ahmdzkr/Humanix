<?php

namespace Database\Seeders;

use App\Models\PerformancePeriod;
use App\Services\PerformanceService;
use Illuminate\Database\Seeder;

/**
 * Periode penilaian contoh yang sudah aktif (hanya lokal).
 */
class PerformanceDemoSeeder extends Seeder
{
    public function run(PerformanceService $performance): void
    {
        if (PerformancePeriod::query()->where('name', 'Semester 2 2026')->exists()) {
            return;
        }

        $period = PerformancePeriod::create([
            'name' => 'Semester 2 2026',
            'start_date' => '2026-07-01',
            'end_date' => '2026-12-31',
            'self_assessment_enabled' => true,
            'status' => 'draft',
        ]);

        $indicators = [
            ['Pencapaian target kerja', 40, 'Target individu tercapai ≥ 100%'],
            ['Kualitas hasil kerja', 25, 'Minim revisi & kesalahan'],
            ['Kedisiplinan & kehadiran', 15, 'Kehadiran ≥ 95%'],
            ['Kerja sama tim & komunikasi', 20, null],
        ];

        foreach ($indicators as $i => [$name, $weight, $target]) {
            $period->indicators()->create([
                'name' => $name, 'weight' => $weight, 'scale_min' => 1, 'scale_max' => 5,
                'target' => $target, 'sort_order' => $i,
            ]);
        }

        $performance->activate($period);
    }
}
