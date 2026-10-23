<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\SystemSetting;
use App\Support\FilePreview;
use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Writer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class IdCardService
{
    /** QR berisi token acak karyawan (bukan NIK), dibaca oleh kios absensi di Fase 3. */
    public function qrPngDataUri(Employee $employee): string
    {
        $png = (new Writer(new GDLibRenderer(360, 1)))->writeString($employee->qr_token);

        return 'data:image/png;base64,'.base64_encode($png);
    }

    public function download(Employee $employee): Response
    {
        $employee->loadMissing(['department', 'position']);

        $photo = null;

        if ($employee->photo_path && Storage::disk(EmployeeService::PHOTO_DISK)->exists($employee->photo_path)) {
            $disk = Storage::disk(EmployeeService::PHOTO_DISK);
            $photo = 'data:'.$disk->mimeType($employee->photo_path).';base64,'.base64_encode($disk->get($employee->photo_path));
        }

        $pdf = Pdf::loadView('pdf.id-card', [
            'employee' => $employee,
            'qr' => $this->qrPngDataUri($employee),
            'photo' => $photo,
            'logo' => 'data:image/png;base64,'.base64_encode((string) file_get_contents(public_path('images/logo-humanix.png'))),
            'company' => SystemSetting::get('company_name', config('app.name')),
        ])
            ->setPaper([0, 0, 243, 153]) // ukuran kartu CR80: 85,6 × 54 mm
            ->setOption('isFontSubsettingEnabled', true);

        return FilePreview::pdf($pdf, "id-card-{$employee->nik}.pdf");
    }
}
