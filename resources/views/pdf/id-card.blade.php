<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>ID Card {{ $employee->nik }}</title>
    <style>
        @page { margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'DejaVu Sans', sans-serif; color: #111827; }
        .card { position: relative; width: 243pt; height: 153pt; overflow: hidden; background: #ffffff; }
        .band { position: absolute; top: 0; left: 0; width: 243pt; height: 34pt; background: #071B49; }
        .band-accent { position: absolute; top: 34pt; left: 0; width: 243pt; height: 3pt; background: #00C8F0; }
        .logo { position: absolute; top: 6pt; left: 8pt; height: 22pt; background: #ffffff; border-radius: 3pt; padding: 2pt 4pt; }
        .company { position: absolute; top: 12pt; right: 8pt; width: 110pt; text-align: right; color: #ffffff; font-size: 6.5pt; }
        .photo { position: absolute; top: 46pt; left: 10pt; width: 56pt; height: 70pt; border: 1pt solid #E5E7EB; border-radius: 4pt; background: #F5F8FF; }
        .photo img { width: 56pt; height: 70pt; border-radius: 4pt; }
        .initials { padding-top: 24pt; text-align: center; font-size: 16pt; font-weight: bold; color: #0057FF; }
        .info { position: absolute; top: 46pt; left: 74pt; width: 96pt; }
        .name { font-size: 9pt; font-weight: bold; color: #071B49; line-height: 1.15; }
        .position { margin-top: 3pt; font-size: 6.5pt; color: #0057FF; font-weight: bold; }
        .label { margin-top: 6pt; font-size: 5pt; color: #6B7280; text-transform: uppercase; }
        .value { font-size: 7pt; }
        .qr { position: absolute; top: 46pt; right: 8pt; width: 62pt; height: 62pt; }
        .qr-note { position: absolute; top: 110pt; right: 8pt; width: 62pt; text-align: center; font-size: 4.5pt; color: #6B7280; }
        .footer { position: absolute; bottom: 0; left: 0; width: 243pt; height: 14pt; background: #0057FF; color: #ffffff; font-size: 5.5pt; text-align: center; padding-top: 4pt; }
    </style>
</head>
<body>
<div class="card">
    <div class="band"></div>
    <div class="band-accent"></div>
    <img class="logo" src="{{ $logo }}" alt="HUMANIX">
    <div class="company">{{ $company }}</div>

    <div class="photo">
        @if ($photo)
            <img src="{{ $photo }}" alt="Foto">
        @else
            <div class="initials">{{ collect(explode(' ', $employee->full_name))->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->implode('') }}</div>
        @endif
    </div>

    <div class="info">
        <div class="name">{{ $employee->full_name }}</div>
        <div class="position">{{ $employee->position?->name }}</div>
        <div class="label">NIK</div>
        <div class="value">{{ $employee->nik }}</div>
        <div class="label">Departemen</div>
        <div class="value">{{ $employee->department?->name }}</div>
    </div>

    <img class="qr" src="{{ $qr }}" alt="QR">
    <div class="qr-note">Pindai untuk absensi</div>

    <div class="footer">Kartu ini milik perusahaan. Jika ditemukan, harap kembalikan ke bagian HRD.</div>
</div>
</body>
</html>
