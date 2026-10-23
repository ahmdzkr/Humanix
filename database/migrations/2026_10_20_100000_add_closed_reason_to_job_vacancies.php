<?php

use App\Enums\ApplicationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Alasan penutupan: "quota" (otomatis saat kuota terpenuhi) atau "manual" (ditutup HRD).
        Schema::table('job_vacancies', function (Blueprint $table) {
            $table->string('closed_reason', 20)->nullable()->after('closed_at');
        });

        DB::table('job_vacancies')->where('status', 'closed')->update(['closed_reason' => 'manual']);

        // Lowongan yang sudah memenuhi kuota sebelum aturan ini ada ikut ditutup.
        $accepted = [ApplicationStatus::Accepted->value, ApplicationStatus::Hired->value];

        DB::table('job_vacancies')->where('status', '!=', 'closed')->orderBy('id')->get(['id', 'quota'])
            ->each(function ($v) use ($accepted) {
                $filled = DB::table('job_applications')->where('job_vacancy_id', $v->id)->whereIn('status', $accepted)->count();

                if ($filled >= $v->quota) {
                    DB::table('job_vacancies')->where('id', $v->id)
                        ->update(['status' => 'closed', 'closed_at' => now(), 'closed_reason' => 'quota', 'updated_at' => now()]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('job_vacancies', fn (Blueprint $table) => $table->dropColumn('closed_reason'));
    }
};
