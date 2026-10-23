<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Ekspor rekap absensi harian atau bulanan.
 */
class AttendanceExport implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    /**
     * @param  list<string>  $headings
     * @param  list<list<mixed>>  $rows
     */
    private function __construct(private array $headings, private array $rows, private string $title) {}

    /** @param  list<array<string, mixed>>  $rows */
    public static function daily(array $rows, string $date): self
    {
        return new self(
            ['NIK', 'Nama', 'Departemen', 'Masuk', 'Pulang', 'Status', 'Terlambat (menit)', 'Sumber', 'Catatan'],
            array_map(fn ($r) => [
                $r['nik'], $r['full_name'], $r['department'], $r['check_in'], $r['check_out'],
                $r['status_label'], $r['late_minutes'], $r['source'], $r['note'],
            ], $rows),
            "Harian {$date}",
        );
    }

    /** @param  list<array<string, mixed>>  $rows */
    public static function monthly(array $rows, string $month): self
    {
        return new self(
            ['NIK', 'Nama', 'Departemen', 'Hadir', 'Terlambat', 'Tidak Hadir', 'Cuti/Izin', 'Total Menit Terlambat'],
            array_map(fn ($r) => [
                $r['nik'], $r['full_name'], $r['department'], $r['present'], $r['late'],
                $r['absent'], $r['leave'], $r['late_minutes'],
            ], $rows),
            "Bulanan {$month}",
        );
    }

    /** @return list<list<mixed>> */
    public function array(): array
    {
        return $this->rows;
    }

    /** @return list<string> */
    public function headings(): array
    {
        return $this->headings;
    }

    public function title(): string
    {
        return $this->title;
    }

    /** @return array<int, array<string, mixed>> */
    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
