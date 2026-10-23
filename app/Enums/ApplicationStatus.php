<?php

namespace App\Enums;

/**
 * Tahap lamaran: Masuk → Lolos Administrasi → Wawancara → Diterima → Menjadi Karyawan.
 * Ditolak bisa terjadi di tahap seleksi administrasi atau keputusan akhir.
 */
enum ApplicationStatus: string
{
    case Applied = 'applied';
    case Shortlisted = 'shortlisted';
    case Interview = 'interview';
    case Accepted = 'accepted';
    case Hired = 'hired';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Applied => 'Lamaran Masuk',
            self::Shortlisted => 'Lolos Administrasi',
            self::Interview => 'Wawancara',
            self::Accepted => 'Diterima',
            self::Hired => 'Menjadi Karyawan',
            self::Rejected => 'Ditolak',
        };
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $s) => ['value' => $s->value, 'label' => $s->label()], self::cases());
    }
}
