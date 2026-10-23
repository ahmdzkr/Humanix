<?php

use App\Http\Controllers\Attendance\AttendanceController;
use App\Http\Controllers\Attendance\HolidayController;
use App\Http\Controllers\Attendance\KioskController;
use App\Http\Controllers\Attendance\WorkScheduleController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Employees\EmployeeController;
use App\Http\Controllers\Employees\OwnProfileController;
use App\Http\Controllers\Leave\LeaveController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Organization\DepartmentController;
use App\Http\Controllers\Organization\OrganizationStructureController;
use App\Http\Controllers\Organization\PositionController;
use App\Http\Controllers\Overtime\OvertimeController;
use App\Http\Controllers\Payroll\PayrollPeriodController;
use App\Http\Controllers\Payroll\PayrollRecordController;
use App\Http\Controllers\Payroll\SalarySetupController;
use App\Http\Controllers\Performance\PerformanceController;
use App\Http\Controllers\Recruitment\CareerController;
use App\Http\Controllers\Recruitment\RecruitmentController;
use App\Http\Controllers\Reports\ReportController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SettingsHubController;
use App\Http\Controllers\SystemSettingController;
use Illuminate\Support\Facades\Route;

// Tidak ada halaman publik: pengunjung langsung diarahkan ke login/dashboard.
Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'))
    ->name('home');

// Halaman karier publik (tanpa login). Kirim lamaran dibatasi 5 kali per menit per IP.
Route::prefix('karier')->name('careers.')->controller(CareerController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('{slug}', 'show')->name('show');
    Route::post('{slug}/lamar', 'apply')->middleware('throttle:5,1')->name('apply');
    Route::get('{slug}/terkirim', 'thanks')->name('thanks');
});

Route::middleware(['auth', 'verified'])->group(function () {
    // Tujuan default Fortify setelah login; diteruskan ke dashboard sesuai role.
    Route::get('dashboard', [DashboardController::class, 'redirect'])->name('dashboard');

    Route::get('admin/dashboard', [DashboardController::class, 'admin'])
        ->middleware('role:Administrator')->name('admin.dashboard');

    // Pencarian global di header (halaman + karyawan yang boleh dilihat).
    Route::get('cari', SearchController::class)->middleware('throttle:60,1')->name('search');

    // Notifikasi milik pengguna yang login.
    Route::get('notifikasi', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifikasi/baca-semua', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('notifikasi/{id}/baca', [NotificationController::class, 'read'])->name('notifications.read');

    // Audit log & log pengiriman email (Administrator).
    Route::middleware('permission:audit.view')->group(function () {
        Route::get('audit-log', [AuditLogController::class, 'index'])->name('audit.index');
        Route::get('audit-log/email', [AuditLogController::class, 'emails'])->name('audit.emails');
    });
    Route::get('hr/dashboard', [DashboardController::class, 'hr'])
        ->middleware('role:HRD')->name('hr.dashboard');
    Route::get('manager/dashboard', [DashboardController::class, 'manager'])
        ->middleware('role:Manager')->name('manager.dashboard');
    Route::get('me/dashboard', [DashboardController::class, 'employee'])
        ->middleware('role:Karyawan')->name('me.dashboard');

    // Pengaturan & struktur organisasi.
    Route::get('pengaturan', SettingsHubController::class)
        ->middleware('permission:settings.manage|departments.manage|positions.manage|attendance.manage')
        ->name('pengaturan.index');

    Route::middleware('permission:settings.manage')->group(function () {
        Route::get('pengaturan/sistem', [SystemSettingController::class, 'index'])->name('system-settings.index');
        Route::put('pengaturan/sistem', [SystemSettingController::class, 'update'])->name('system-settings.update');
    });

    Route::resource('pengaturan/departemen', DepartmentController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->parameters(['departemen' => 'department'])
        ->names('departments');

    Route::resource('pengaturan/jabatan', PositionController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->parameters(['jabatan' => 'position'])
        ->names('positions');

    Route::get('organisasi/struktur', OrganizationStructureController::class)
        ->middleware('permission:organization.view')
        ->name('organization.structure');

    // Data karyawan (otorisasi per aksi lewat EmployeePolicy).
    Route::prefix('karyawan')->name('employees.')->controller(EmployeeController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('tambah', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('ekspor', 'export')->name('export');
        Route::get('{employee}', 'show')->name('show');
        Route::get('{employee}/ubah', 'edit')->name('edit');
        // POST (bukan PUT) karena form bisa membawa file foto.
        Route::post('{employee}', 'update')->name('update');
        Route::patch('{employee}/status', 'updateStatus')->name('status');
        Route::post('{employee}/qr', 'rotateQr')->name('rotate-qr');
        Route::put('{employee}/role', 'changeRole')->name('role');
        Route::post('{employee}/tautan-kata-sandi', 'sendPasswordLink')->middleware('throttle:6,1')->name('password-link');
        Route::get('{employee}/id-card', 'idCard')->name('id-card');
        Route::get('{employee}/foto', 'photo')->name('photo');
    });

    Route::get('profil-saya', [OwnProfileController::class, 'show'])->name('my-profile.show');
    Route::post('profil-saya', [OwnProfileController::class, 'update'])->name('my-profile.update');
    Route::get('profil-saya/id-card', [OwnProfileController::class, 'idCard'])->name('my-profile.id-card');

    // Absensi.
    Route::prefix('absensi')->name('attendance.')->group(function () {
        Route::get('/', [AttendanceController::class, 'index'])
            ->middleware('permission:attendance.view|attendance.self')->name('index');
        Route::get('saya', [AttendanceController::class, 'mine'])
            ->middleware('permission:attendance.self')->name('mine');
        Route::get('ekspor', [AttendanceController::class, 'export'])
            ->middleware('permission:attendance.manage')->name('export');
        Route::post('koreksi', [AttendanceController::class, 'correct'])
            ->middleware('permission:attendance.manage')->name('correct');
        Route::get('kios', [KioskController::class, 'show'])
            ->middleware('permission:attendance.kiosk')->name('kiosk');
        Route::post('scan', [KioskController::class, 'scan'])
            ->middleware(['permission:attendance.kiosk', 'throttle:120,1'])->name('scan');
    });

    Route::middleware('permission:attendance.manage')->group(function () {
        Route::resource('pengaturan/jadwal-kerja', WorkScheduleController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['jadwal-kerja' => 'schedule'])
            ->names('work-schedules');
        Route::post('pengaturan/jadwal-kerja/{schedule}/terapkan', [WorkScheduleController::class, 'assign'])
            ->name('work-schedules.assign');
        Route::resource('pengaturan/hari-libur', HolidayController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['hari-libur' => 'holiday'])
            ->names('holidays');
    });

    // Cuti & izin (otorisasi per aksi lewat LeaveRequestPolicy).
    Route::prefix('cuti-izin')->name('leave.')->controller(LeaveController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::get('{leave}', 'show')->name('show');
        Route::post('{leave}/keputusan', 'decide')->name('decide');
        Route::post('{leave}/batal', 'cancel')->name('cancel');
        Route::get('{leave}/lampiran', 'attachment')->name('attachment');
    });

    // Lembur (otorisasi per aksi lewat OvertimeRequestPolicy).
    Route::prefix('lembur')->name('overtime.')->controller(OvertimeController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::get('{overtime}', 'show')->name('show');
        Route::post('{overtime}/keputusan', 'decide')->name('decide');
        Route::post('{overtime}/batal', 'cancel')->name('cancel');
    });

    // Payroll. Slip karyawan sendiri: payroll.self; semua yang lain: izin payroll HRD.
    Route::prefix('gaji')->name('payroll.')->group(function () {
        Route::get('/', [PayrollPeriodController::class, 'index'])
            ->middleware('permission:payroll.view|payroll.self')->name('index');
        Route::get('slip-saya', [PayrollRecordController::class, 'mine'])
            ->middleware('permission:payroll.self')->name('my-slips');
        Route::get('slip/{record}', [PayrollRecordController::class, 'show'])->name('records.show');
        Route::get('slip/{record}/pdf', [PayrollRecordController::class, 'pdf'])->name('records.pdf');

        Route::middleware('permission:payroll.view')->group(function () {
            Route::get('periode/{period}', [PayrollPeriodController::class, 'show'])->name('periods.show');
            Route::get('periode/{period}/ekspor', [PayrollPeriodController::class, 'export'])->name('periods.export');
            Route::get('pembayaran/{payment}/bukti', [PayrollRecordController::class, 'paymentProof'])->name('payments.proof');
        });

        Route::middleware('permission:payroll.manage')->group(function () {
            Route::post('periode', [PayrollPeriodController::class, 'store'])->name('periods.store');
            Route::post('periode/{period}/hitung', [PayrollPeriodController::class, 'calculate'])->name('periods.calculate');
            Route::post('periode/{period}/pembayaran', [PayrollPeriodController::class, 'createPayments'])->name('periods.payments');
            Route::post('slip/{record}/penyesuaian', [PayrollRecordController::class, 'addItem'])->name('records.items.store');
            Route::delete('slip/{record}/penyesuaian/{item}', [PayrollRecordController::class, 'removeItem'])->name('records.items.destroy');
            Route::post('pembayaran/{payment}', [PayrollRecordController::class, 'updatePayment'])->name('payments.update');

            Route::get('komponen', [SalarySetupController::class, 'components'])->name('components.index');
            Route::post('komponen', [SalarySetupController::class, 'storeComponent'])->name('components.store');
            Route::put('komponen/{component}', [SalarySetupController::class, 'updateComponent'])->name('components.update');
            Route::get('struktur', [SalarySetupController::class, 'structures'])->name('structures.index');
            Route::post('struktur/{employee}', [SalarySetupController::class, 'storeStructure'])->name('structures.store');
        });

        // Transisi status; izin per target dicek di controller (approve butuh payroll.approve).
        Route::post('periode/{period}/status', [PayrollPeriodController::class, 'advance'])
            ->middleware('permission:payroll.manage|payroll.approve')->name('periods.advance');
    });

    // Rekrutmen. Melihat: recruitment.view (interviewer dibatasi Policy); mengelola: recruitment.manage.
    Route::prefix('rekruitmen')->name('recruitment.')->middleware('permission:recruitment.view')->controller(RecruitmentController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('lamaran/{application}', 'showApplication')->name('applications.show');
        Route::get('lamaran/{application}/cv', 'cv')->name('applications.cv');
        Route::post('wawancara/{interview}/nilai', 'scoreInterview')->name('interviews.score');

        Route::middleware('permission:recruitment.manage')->group(function () {
            Route::post('lowongan', 'storeVacancy')->name('vacancies.store');
            Route::get('lowongan/{vacancy}', 'showVacancy')->name('vacancies.show');
            Route::put('lowongan/{vacancy}', 'updateVacancy')->name('vacancies.update');
            Route::post('lowongan/{vacancy}/status', 'setVacancyStatus')->name('vacancies.status');
            Route::post('lamaran/{application}/seleksi', 'screen')->name('applications.screen');
            Route::post('lamaran/{application}/wawancara', 'scheduleInterview')->name('applications.interview');
            Route::post('wawancara/{interview}/batal', 'cancelInterview')->name('interviews.cancel');
            Route::post('lamaran/{application}/keputusan', 'decide')->name('applications.decide');
            Route::post('lamaran/{application}/onboarding', 'onboard')->name('applications.onboard');
            Route::post('onboarding/{onboarding}/checklist', 'toggleChecklist')->name('onboarding.checklist');
        });
    });

    // Penilaian kinerja. Akses per lembar penilaian dicek di PerformanceService (pemilik / penilai / HRD).
    Route::prefix('penilaian-kinerja')->name('performance.')->middleware('permission:performance.view|performance.self')
        ->controller(PerformanceController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('lembar/{review}', 'showReview')->name('reviews.show');
            Route::post('lembar/{review}/evaluasi-diri', 'submitSelf')->name('reviews.self');
            Route::post('lembar/{review}/penilaian-atasan', 'submitManager')->name('reviews.manager');
            Route::post('lembar/{review}/rencana', 'addPlan')->name('reviews.plans');

            Route::middleware('permission:performance.manage')->group(function () {
                Route::post('periode', 'storePeriod')->name('periods.store');
                Route::get('periode/{period}', 'showPeriod')->name('periods.show');
                Route::post('periode/{period}/indikator', 'storeIndicator')->name('indicators.store');
                Route::put('periode/{period}/indikator/{indicator}', 'updateIndicator')->name('indicators.update');
                Route::delete('periode/{period}/indikator/{indicator}', 'destroyIndicator')->name('indicators.destroy');
                Route::post('periode/{period}/aktifkan', 'activate')->name('periods.activate');
                Route::post('periode/{period}/tutup', 'close')->name('periods.close');
                Route::post('periode/{period}/publikasikan', 'publishAll')->name('periods.publish');
                Route::post('lembar/{review}/keputusan-hrd', 'hrDecision')->name('reviews.hr');
                Route::post('lembar/{review}/publikasikan', 'publish')->name('reviews.publish');
            });
        });

    // Laporan bulanan (HRD: seluruh perusahaan, Manager: timnya).
    Route::middleware('permission:reports.view')->group(function () {
        Route::get('laporan', [ReportController::class, 'index'])->name('reports.index');
        Route::get('laporan/cuti-lembur', [ReportController::class, 'leaveOvertime'])->name('reports.leave-overtime');
    });
});

require __DIR__.'/settings.php';
