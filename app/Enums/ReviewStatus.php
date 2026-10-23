<?php

namespace App\Enums;

/**
 * Status penilaian kinerja:
 * Draft (menunggu evaluasi diri) → Self Assessed → Manager Reviewed → HR Checked/Returned → Final → Published.
 * "HR Checked" diwakili oleh langkah finalisasi HRD; Returned mengembalikan ke atasan untuk revisi.
 */
enum ReviewStatus: string
{
    case Draft = 'draft';
    case SelfAssessed = 'self_assessed';
    case ManagerReviewed = 'manager_reviewed';
    case Returned = 'returned';
    case Final = 'final';
    case Published = 'published';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Menunggu Evaluasi Diri',
            self::SelfAssessed => 'Menunggu Penilaian Atasan',
            self::ManagerReviewed => 'Menunggu Pemeriksaan HRD',
            self::Returned => 'Dikembalikan untuk Revisi',
            self::Final => 'Final',
            self::Published => 'Dipublikasikan',
        };
    }

    /** Status ketika atasan boleh mengisi/merevisi nilai. */
    public function awaitsManager(): bool
    {
        return in_array($this, [self::SelfAssessed, self::Returned], true);
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $s) => ['value' => $s->value, 'label' => $s->label()], self::cases());
    }
}
