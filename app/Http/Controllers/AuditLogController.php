<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Notifications\HrisNotification;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Audit log (siapa mengubah apa, sebelum/sesudah) dan log pengiriman email. Hanya Administrator.
 */
class AuditLogController extends Controller
{
    /** Nama modul yang mudah dibaca untuk setiap model yang diaudit. */
    public const MODULES = [
        'User' => 'Akun pengguna',
        'Employee' => 'Karyawan',
        'BankAccount' => 'Rekening bank',
        'Department' => 'Departemen',
        'Position' => 'Jabatan',
        'WorkSchedule' => 'Jadwal kerja',
        'Holiday' => 'Hari libur',
        'AttendanceCorrection' => 'Koreksi absensi',
        'LeaveType' => 'Jenis cuti',
        'LeaveBalance' => 'Saldo cuti',
        'LeaveRequest' => 'Pengajuan cuti/izin',
        'OvertimeRequest' => 'Pengajuan lembur',
        'ApprovalStep' => 'Langkah persetujuan',
        'SalaryComponent' => 'Komponen gaji',
        'SalaryStructure' => 'Struktur gaji',
        'PayrollPeriod' => 'Periode payroll',
        'Payment' => 'Pembayaran gaji',
        'JobVacancy' => 'Lowongan',
        'JobApplication' => 'Lamaran',
        'Interview' => 'Wawancara',
        'OnboardingRecord' => 'Onboarding',
        'PerformancePeriod' => 'Periode penilaian',
        'PerformanceIndicator' => 'Indikator kinerja',
        'PerformanceReview' => 'Lembar penilaian',
        'DevelopmentPlan' => 'Rencana pengembangan',
        'SystemSetting' => 'Pengaturan sistem',
    ];

    public const EVENTS = ['created' => 'Membuat', 'updated' => 'Mengubah', 'deleted' => 'Menghapus'];

    /** Kunci yang tidak pernah ditampilkan, walau tersimpan di data lama sebelum penyaringan. */
    private const HIDDEN = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'account_number', 'qr_token'];

    public function index(Request $request): Response
    {
        $filters = [
            'user' => (string) $request->query('user', ''),
            'module' => (string) $request->query('module', ''),
            'event' => (string) $request->query('event', ''),
            'from' => (string) $request->query('from', ''),
            'to' => (string) $request->query('to', ''),
        ];
        $tz = config('app.display_timezone');

        $logs = AuditLog::query()
            ->with('user:id,name')
            ->when($filters['user'] === 'system', fn ($q) => $q->whereNull('user_id'))
            ->when(ctype_digit($filters['user']), fn ($q) => $q->where('user_id', (int) $filters['user']))
            ->when(isset(self::MODULES[$filters['module']]), fn ($q) => $q->where('auditable_type', 'App\\Models\\'.$filters['module']))
            ->when(isset(self::EVENTS[$filters['event']]), fn ($q) => $q->where('event', $filters['event']))
            ->when($this->date($filters['from']), fn ($q, $d) => $q->where('created_at', '>=', CarbonImmutable::parse($d, $tz)->startOfDay()->utc()))
            ->when($this->date($filters['to']), fn ($q, $d) => $q->where('created_at', '<=', CarbonImmutable::parse($d, $tz)->endOfDay()->utc()))
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (AuditLog $log) => [
                'id' => $log->id,
                'actor' => $log->user?->name ?? 'Sistem',
                'event' => $log->event,
                'event_label' => self::EVENTS[$log->event] ?? $log->event,
                'module' => self::MODULES[class_basename($log->auditable_type)] ?? class_basename($log->auditable_type),
                'record_id' => $log->auditable_id,
                'changes' => $this->changes($log),
                'ip' => $log->ip_address,
                'at' => $log->created_at?->toIso8601String(),
            ]);

        return Inertia::render('audit/index', [
            'logs' => $logs,
            'filters' => $filters,
            'userOptions' => User::query()->whereIn('id', AuditLog::query()->whereNotNull('user_id')->distinct()->select('user_id'))
                ->orderBy('name')->get(['id', 'name']),
            'moduleOptions' => collect(self::MODULES)->map(fn ($label, $key) => ['value' => $key, 'label' => $label])->sortBy('label')->values(),
            'eventOptions' => collect(self::EVENTS)->map(fn ($label, $key) => ['value' => $key, 'label' => $label])->values(),
        ]);
    }

    /** Log pengiriman email notifikasi: status apa adanya, termasuk yang gagal. */
    public function emails(Request $request): Response
    {
        $status = (string) $request->query('status', '');

        return Inertia::render('audit/emails', [
            'deliveries' => NotificationDelivery::query()
                ->when(isset(NotificationDelivery::STATUS_LABELS[$status]), fn ($q) => $q->where('status', $status))
                ->latest('id')
                ->paginate(20)
                ->withQueryString()
                ->through(fn (NotificationDelivery $d) => [
                    'id' => $d->id,
                    'recipient' => $d->recipient_name ? "{$d->recipient_name} <{$d->email}>" : $d->email,
                    'category' => HrisNotification::CATEGORIES[$d->category] ?? $d->category,
                    'subject' => $d->subject,
                    'status' => $d->status,
                    'status_label' => NotificationDelivery::STATUS_LABELS[$d->status] ?? $d->status,
                    'attempts' => $d->attempts,
                    'error' => $d->error,
                    'at' => $d->created_at?->toIso8601String(),
                    'sent_at' => $d->sent_at?->toIso8601String(),
                ]),
            'filters' => ['status' => $status],
            'counts' => NotificationDelivery::query()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    /**
     * Daftar perubahan field: [field, sebelum, sesudah]. Field sensitif disembunyikan.
     *
     * @return list<array{field: string, old: string|null, new: string|null}>
     */
    private function changes(AuditLog $log): array
    {
        $old = $log->old_values ?? [];
        $new = $log->new_values ?? [];
        $keys = array_unique([...array_keys($old), ...array_keys($new)]);

        return collect($keys)
            ->reject(fn ($k) => in_array($k, self::HIDDEN, true) || Str::contains($k, ['password', 'secret', 'token']))
            ->map(fn ($k) => ['field' => $k, 'old' => $this->stringify($old[$k] ?? null), 'new' => $this->stringify($new[$k] ?? null)])
            ->values()->all();
    }

    private function stringify(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        // Kolom ber-cast JSON tersimpan sebagai teks JSON; tampilkan isinya, bukan tanda kutipnya.
        if (is_string($value) && preg_match('/^\s*["\[{]/', $value)) {
            $decoded = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $value = $decoded;
            }
        }

        $text = is_scalar($value) ? (is_bool($value) ? ($value ? 'ya' : 'tidak') : (string) $value) : json_encode($value, JSON_UNESCAPED_UNICODE);

        return Str::limit($text, 300);
    }

    private function date(string $value): ?string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }
}
