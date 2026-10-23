<?php

namespace App\Support;

use Barryvdh\DomPDF\PDF;
use DateTimeInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Respons file yang bisa dipratinjau di aplikasi sebelum diunduh.
 * PDF & file unggahan tampil inline (dibuka di iframe/modal); `?download=1` memaksa unduhan.
 * Ekspor Excel tetap mengunduh .xlsx; `?preview=1` mengembalikan tabel JSON dari data ekspor yang sama.
 * Otorisasi tetap di controller pemanggil, jadi pratinjau dan unduhan memakai aturan akses yang sama.
 */
class FilePreview
{
    /** Batas baris pratinjau Excel agar modal tetap ringan; file .xlsx tetap berisi semua baris. */
    public const PREVIEW_ROWS = 500;

    public static function pdf(PDF $pdf, string $filename): Response
    {
        return request()->boolean('download') ? $pdf->download($filename) : $pdf->stream($filename);
    }

    public static function stored(string $disk, string $path, ?string $filename = null): StreamedResponse
    {
        $storage = Storage::disk($disk);

        return request()->boolean('download')
            ? $storage->download($path, $filename)
            : $storage->response($path, $filename, ['Cache-Control' => 'private, no-store']);
    }

    public static function excel(object $export, string $filename): BinaryFileResponse|JsonResponse
    {
        if (! request()->boolean('preview')) {
            return Excel::download($export, $filename);
        }

        [$rows, $total] = self::rows($export);

        return response()->json([
            'title' => $export instanceof WithTitle ? $export->title() : pathinfo($filename, PATHINFO_FILENAME),
            'filename' => $filename,
            'headings' => $export instanceof WithHeadings ? $export->headings() : [],
            'rows' => $rows,
            'total' => $total,
            'truncated' => $total > count($rows),
        ], headers: ['Cache-Control' => 'private, no-store']);
    }

    /** Jenis pratinjau untuk file unggahan: gambar ditampilkan sebagai gambar, lainnya (PDF) di iframe. */
    public static function kind(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp'], true) ? 'image' : 'pdf';
    }

    /** @return array{0: list<list<string|int|float|null>>, 1: int} */
    private static function rows(object $export): array
    {
        if ($export instanceof FromQuery) {
            $query = $export->query();
            $total = (clone $query)->count();
            $items = $query->limit(self::PREVIEW_ROWS)->get()->all();
        } elseif ($export instanceof FromCollection) {
            $items = $export->collection()->all();
            $total = count($items);
        } elseif ($export instanceof FromArray) {
            $items = $export->array();
            $total = count($items);
        } else {
            $items = [];
            $total = 0;
        }

        $rows = [];

        foreach (array_slice(array_values($items), 0, self::PREVIEW_ROWS) as $item) {
            $row = $export instanceof WithMapping ? $export->map($item) : $item;
            $rows[] = array_map(self::cell(...), array_values((array) $row));
        }

        return [$rows, $total];
    }

    private static function cell(mixed $value): string|int|float|null
    {
        return match (true) {
            $value === null, is_int($value), is_float($value), is_string($value) => $value,
            is_bool($value) => $value ? 'Ya' : 'Tidak',
            $value instanceof DateTimeInterface => $value->format('d/m/Y'),
            default => (string) $value,
        };
    }
}
