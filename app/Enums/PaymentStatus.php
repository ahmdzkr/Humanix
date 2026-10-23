<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Paid = 'paid';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu',
            self::Processing => 'Diproses',
            self::Paid => 'Sudah Dibayar',
            self::Failed => 'Gagal',
            self::Cancelled => 'Dibatalkan',
        };
    }

    /** Masih "hidup": mencegah instruksi pembayaran ganda untuk slip yang sama. */
    public function isOpen(): bool
    {
        return in_array($this, [self::Pending, self::Processing, self::Paid], true);
    }

    /** @return list<self> */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Pending => [self::Processing, self::Paid, self::Failed, self::Cancelled],
            self::Processing => [self::Paid, self::Failed],
            self::Paid, self::Failed, self::Cancelled => [],
        };
    }
}
