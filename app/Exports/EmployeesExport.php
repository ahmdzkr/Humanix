<?php

namespace App\Exports;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Ekspor daftar karyawan sesuai filter. Nomor rekening dan token QR tidak ikut diekspor.
 *
 * @implements WithMapping<Employee>
 */
class EmployeesExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    /**
     * @param  Builder<Employee>  $query
     */
    public function __construct(private readonly Builder $query) {}

    /** @return Builder<Employee> */
    public function query(): Builder
    {
        return $this->query->with(['user:id,email', 'department:id,name', 'position:id,name', 'manager:id,full_name']);
    }

    /** @return list<string> */
    public function headings(): array
    {
        return [
            'NIK', 'Nama Lengkap', 'Email', 'Telepon', 'Departemen', 'Jabatan', 'Atasan',
            'Status Kepegawaian', 'Tanggal Masuk', 'Akhir Kontrak', 'Status',
        ];
    }

    /**
     * @param  Employee  $employee
     * @return list<string|null>
     */
    public function map($employee): array
    {
        return [
            $employee->nik,
            $employee->full_name,
            $employee->user?->email,
            $employee->phone,
            $employee->department?->name,
            $employee->position?->name,
            $employee->manager?->full_name,
            $employee->employment_status?->label(),
            $employee->join_date?->format('d/m/Y'),
            $employee->contract_end_date?->format('d/m/Y'),
            $employee->is_active ? 'Aktif' : 'Nonaktif',
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
