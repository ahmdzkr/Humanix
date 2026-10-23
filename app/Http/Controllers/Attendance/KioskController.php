<?php

namespace App\Http\Controllers\Attendance;

use App\Exceptions\AttendanceScanRejected;
use App\Http\Controllers\Controller;
use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Kios absensi: perangkat bersama (login dengan akun berizin attendance.kiosk) yang memindai ID Card.
 */
class KioskController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('attendance/kiosk');
    }

    public function scan(Request $request, AttendanceService $attendance): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:100']]);
        $tz = config('app.display_timezone');

        try {
            $result = $attendance->scan($data['token']);
        } catch (AttendanceScanRejected $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
                'employee' => $e->employeeName,
            ], 422);
        }

        $record = $result['record'];
        $isIn = $result['type'] === 'check_in';
        $time = ($isIn ? $record->check_in_at : $record->check_out_at)->setTimezone($tz)->format('H:i');

        return response()->json([
            'ok' => true,
            'type' => $result['type'],
            'employee' => $result['employee']->full_name,
            'time' => $time,
            'late_minutes' => $isIn ? $record->late_minutes : null,
            'message' => $isIn
                ? ($record->late_minutes > 0 ? "Absen masuk {$time} WIB — terlambat {$record->late_minutes} menit." : "Absen masuk {$time} WIB — tepat waktu.")
                : "Absen pulang {$time} WIB. Hati-hati di jalan!",
        ]);
    }
}
