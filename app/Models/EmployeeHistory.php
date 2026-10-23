<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Riwayat perubahan data karyawan yang ditampilkan di profil (berbeda dengan audit_logs teknis).
 */
class EmployeeHistory extends Model
{
    public const UPDATED_AT = null;

    public const TYPE_LABELS = [
        'created' => 'Data karyawan dibuat',
        'updated' => 'Data karyawan diubah',
        'deactivated' => 'Karyawan dinonaktifkan',
        'activated' => 'Karyawan diaktifkan kembali',
        'qr_rotated' => 'QR ID Card diganti',
        'role_changed' => 'Role akun diubah',
        'profile_updated' => 'Profil diperbarui oleh karyawan',
    ];

    protected $fillable = ['employee_id', 'user_id', 'type', 'changes', 'note'];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
