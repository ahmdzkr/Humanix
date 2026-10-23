@php($rp = fn ($v) => \App\Support\Format::uang($v))
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Slip Gaji {{ $slip['period'] }} — {{ $slip['employee_name'] }}</title>
    <style>
        @page { margin: 24pt 28pt; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 8.5pt; color: #111827; }
        .header { border-bottom: 2pt solid #0057FF; padding-bottom: 8pt; margin-bottom: 10pt; }
        .header img { height: 30pt; }
        .header .title { float: right; text-align: right; }
        .title h1 { margin: 0; font-size: 13pt; color: #071B49; }
        .title p { margin: 2pt 0 0; color: #6B7280; }
        .clear { clear: both; }
        table { width: 100%; border-collapse: collapse; }
        .info td { padding: 2pt 0; vertical-align: top; }
        .info .label { color: #6B7280; width: 28%; }
        h2 { font-size: 9pt; color: #071B49; margin: 12pt 0 4pt; text-transform: uppercase; letter-spacing: .5pt; }
        .lines td { padding: 3pt 0; border-bottom: .5pt solid #E5E7EB; }
        .lines .amount { text-align: right; white-space: nowrap; }
        .lines .note { color: #6B7280; font-size: 7pt; }
        .subtotal td { font-weight: bold; border-bottom: none; padding-top: 5pt; }
        .net { margin-top: 12pt; background: #071B49; color: #fff; padding: 8pt 10pt; }
        .net .amount { float: right; font-size: 13pt; font-weight: bold; }
        .footer { margin-top: 14pt; color: #6B7280; font-size: 7pt; }
    </style>
</head>
<body>
<div class="header">
    <div class="title">
        <h1>SLIP GAJI</h1>
        <p>Periode {{ $slip['period'] }}</p>
        <p>Tanggal bayar {{ \App\Support\Format::tanggal($slip['pay_date'].' 00:00:00') }}</p>
    </div>
    <img src="{{ $logo }}" alt="HUMANIX">
    <div>{{ $company }}</div>
    <div class="clear"></div>
</div>

<table class="info">
    <tr><td class="label">Nama</td><td>{{ $slip['employee_name'] }}</td><td class="label">NIK</td><td>{{ $slip['nik'] }}</td></tr>
    <tr><td class="label">Departemen</td><td>{{ $slip['department'] ?? '—' }}</td><td class="label">Jabatan</td><td>{{ $slip['position'] ?? '—' }}</td></tr>
    <tr><td class="label">Rekening</td><td colspan="3">{{ $slip['bank_name'] ? $slip['bank_name'].' '.$slip['bank_account'].' a.n. '.$slip['bank_account_holder'] : '—' }}</td></tr>
    <tr><td class="label">Kehadiran</td><td colspan="3">Hadir {{ $slip['present_days'] }} hari · Tidak hadir {{ $slip['absent_days'] }} hari · Cuti/izin {{ $slip['leave_days'] }} hari</td></tr>
</table>

<h2>Pendapatan</h2>
<table class="lines">
    @foreach ($slip['earnings'] as $item)
        <tr><td>{{ $item['name'] }} @if($item['note'])<span class="note">({{ $item['note'] }})</span>@endif</td><td class="amount">{{ $rp($item['amount']) }}</td></tr>
    @endforeach
    <tr class="subtotal"><td>Total pendapatan</td><td class="amount">{{ $rp($slip['total_earnings']) }}</td></tr>
</table>

<h2>Potongan</h2>
<table class="lines">
    @forelse ($slip['deductions'] as $item)
        <tr><td>{{ $item['name'] }} @if($item['note'])<span class="note">({{ $item['note'] }})</span>@endif</td><td class="amount">{{ $rp($item['amount']) }}</td></tr>
    @empty
        <tr><td colspan="2" class="note">Tidak ada potongan.</td></tr>
    @endforelse
    <tr class="subtotal"><td>Total potongan</td><td class="amount">{{ $rp($slip['total_deductions']) }}</td></tr>
</table>

<div class="net">GAJI BERSIH DITERIMA <span class="amount">{{ $rp($slip['net_pay']) }}</span><div class="clear"></div></div>

<p class="footer">Dokumen ini dibuat otomatis oleh HUMANIX dan sah tanpa tanda tangan. Bersifat rahasia — hanya untuk karyawan yang bersangkutan.</p>
</body>
</html>
