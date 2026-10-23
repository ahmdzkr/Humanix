<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\Holiday;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class HolidayController extends Controller
{
    public function index(Request $request): Response
    {
        $year = (int) $request->integer('year') ?: (int) now(config('app.display_timezone'))->year;

        return Inertia::render('attendance/holidays', [
            'year' => $year,
            'holidays' => Holiday::query()->whereYear('date', $year)->orderBy('date')->get()
                ->map(fn (Holiday $h) => ['id' => $h->id, 'date' => $h->date->format('Y-m-d'), 'name' => $h->name]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Holiday::create($this->validated($request));

        return back()->with('success', 'Hari libur berhasil ditambahkan.');
    }

    public function update(Request $request, Holiday $holiday): RedirectResponse
    {
        $holiday->update($this->validated($request, $holiday));

        return back()->with('success', 'Hari libur berhasil diperbarui.');
    }

    public function destroy(Holiday $holiday): RedirectResponse
    {
        $holiday->delete();

        return back()->with('success', 'Hari libur berhasil dihapus.');
    }

    /** @return array{date: string, name: string} */
    private function validated(Request $request, ?Holiday $holiday = null): array
    {
        return $request->validate([
            'date' => ['required', 'date', Rule::unique('holidays', 'date')->ignore($holiday?->id)],
            'name' => ['required', 'string', 'max:150'],
        ], ['date.unique' => 'Tanggal ini sudah terdaftar sebagai hari libur.'], ['date' => 'tanggal', 'name' => 'nama hari libur']);
    }
}
