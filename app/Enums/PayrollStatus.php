<?php

namespace App\Enums;

/**
 * Status periode payroll. Transisi hanya maju: Draft → Calculated → Reviewed → Approved → Closed.
 */
enum PayrollStatus: string
{
    case Draft = 'draft';
    case Calculated = 'calculated';
    case Reviewed = 'reviewed';
    case Approved = 'approved';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Calculated => 'Sudah Dihitung',
            self::Reviewed => 'Sudah Direview',
            self::Approved => 'Disetujui',
            self::Closed => 'Ditutup',
        };
    }

    public function next(): ?self
    {
        return match ($this) {
            self::Draft => self::Calculated,
            self::Calculated => self::Reviewed,
            self::Reviewed => self::Approved,
            self::Approved => self::Closed,
            self::Closed => null,
        };
    }

    /** Angka & item gaji masih boleh berubah (hitung ulang, penyesuaian manual). */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Calculated], true);
    }

    /** Slip boleh dilihat karyawan. */
    public function isPublished(): bool
    {
        return in_array($this, [self::Approved, self::Closed], true);
    }
}
