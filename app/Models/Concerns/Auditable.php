<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Mencatat create/update/delete model ke audit_logs.
 * Field sensitif (password, token, rekening, dll.) tidak pernah disimpan nilainya.
 */
trait Auditable
{
    /** Field yang selalu disaring, ditambah properti $auditExclude pada model. */
    protected static array $auditAlwaysExcluded = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'account_number',
        'qr_token',
        'created_at',
        'updated_at',
    ];

    public static function bootAuditable(): void
    {
        static::created(fn (Model $model) => $model->writeAudit('created', [], $model->auditableAttributes($model->getAttributes())));

        static::updated(function (Model $model) {
            $changes = $model->auditableAttributes($model->getChanges());

            if ($changes === []) {
                return;
            }

            // Nilai mentah untuk sebelum & sesudah (getChanges juga mentah) agar keduanya sebanding.
            $old = array_intersect_key($model->auditableAttributes($model->getRawOriginal()), $changes);
            $model->writeAudit('updated', $old, $changes);
        });

        static::deleted(fn (Model $model) => $model->writeAudit('deleted', $model->auditableAttributes($model->getOriginal()), []));
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function auditableAttributes(array $attributes): array
    {
        $excluded = array_merge(static::$auditAlwaysExcluded, $this->auditExclude ?? []);

        return collect($attributes)
            ->reject(fn ($value, string $key) => in_array($key, $excluded, true)
                || Str::contains($key, ['password', 'secret', 'token']))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    protected function writeAudit(string $event, array $old, array $new): void
    {
        // Hanya request HTTP yang punya route; perintah artisan/queue tidak punya IP pengguna.
        $request = request()->route() !== null ? request() : null;

        AuditLog::create([
            'user_id' => Auth::id(),
            'event' => $event,
            'auditable_type' => $this->getMorphClass(),
            'auditable_id' => $this->getKey(),
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? Str::limit((string) $request->userAgent(), 250, '') : null,
        ]);
    }
}
