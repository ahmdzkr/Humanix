<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Rekap cuti/izin & lembur per karyawan untuk satu bulan.
 */
class LeaveOvertimeExport implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    /** @param  list<array<int, string|int|float|null>>  $rows */
    public function __construct(private readonly array $rows, private readonly string $month) {}

    /** @return list<array<int, string|int|float|null>> */
    public function array(): array
    {
        return $this->rows;
    }

    /** @return list<string> */
    public function headings(): array
    {
        return ['NIK', 'Nama', 'Departemen', 'Hari Cuti/Izin Disetujui', 'Pengajuan Cuti/Izin', 'Jam Lembur Disetujui', 'Pengajuan Lembur'];
    }

    public function title(): string
    {
        return "Cuti & Lembur {$this->month}";
    }

    /** @return array<int, array<string, mixed>> */
    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
