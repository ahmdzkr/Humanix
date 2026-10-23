<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Scan absensi ditolak karena aturan bisnis. Pesannya aman ditampilkan ke pengguna.
 */
class AttendanceScanRejected extends RuntimeException
{
    public function __construct(string $message, public readonly ?string $employeeName = null)
    {
        parent::__construct($message);
    }
}
