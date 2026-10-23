<?php

namespace App\Exports;

use App\Models\PayrollPeriod;
use App\Models\PayrollRecord;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Laporan payroll per periode. Nomor rekening ditampilkan tersamar.
 *
 * @implements WithMapping<PayrollRecord>
 */
class PayrollExport implements FromQuery, ShouldAutoSize, WithColumnFormatting, WithHeadings, WithMapping, WithStyles
{
    public function __construct(private readonly PayrollPeriod $period) {}

    /** @return Builder<PayrollRecord> */
    public function query(): Builder
    {
        return PayrollRecord::query()->where('payroll_period_id', $this->period->id)->with('payments')->orderBy('employee_name');
    }

    /** @return list<string> */
    public function headings(): array
    {
        return ['NIK', 'Nama', 'Departemen', 'Jabatan', 'Gaji Pokok', 'Total Pendapatan', 'Total Potongan', 'Gaji Bersih', 'Lembur (menit)', 'Bank', 'Rekening', 'Status Bayar'];
    }

    /**
     * @param  PayrollRecord  $record
     * @return list<mixed>
     */
    public function map($record): array
    {
        return [
            $record->nik,
            $record->employee_name,
            $record->department_name,
            $record->position_name,
            (float) $record->base_salary,
            (float) $record->total_earnings,
            (float) $record->total_deductions,
            (float) $record->net_pay,
            $record->overtime_minutes,
            $record->bank_name,
            $record->maskedAccount(),
            $record->payments->first()?->status->label() ?? 'Belum dibuat',
        ];
    }

    /** @return array<string, string> */
    public function columnFormats(): array
    {
        return ['E' => '#,##0.00', 'F' => '#,##0.00', 'G' => '#,##0.00', 'H' => '#,##0.00'];
    }

    /** @return array<int, array<string, mixed>> */
    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
