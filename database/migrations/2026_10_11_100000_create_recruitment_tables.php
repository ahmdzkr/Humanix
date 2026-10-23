<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_vacancies', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->string('title', 150);
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->foreignId('position_id')->constrained()->restrictOnDelete();
            $table->string('employment_status', 20);
            $table->string('location', 150)->nullable();
            $table->text('description');
            $table->text('requirements');
            $table->unsignedSmallInteger('quota')->default(1);
            $table->date('deadline');
            $table->string('status', 20)->index(); // draft | published | closed
            $table->dateTime('published_at')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('applicants', function (Blueprint $table) {
            $table->id();
            $table->string('full_name', 150);
            $table->string('email')->unique();
            $table->string('phone', 20);
            $table->text('address')->nullable();
            $table->string('last_education', 100)->nullable();
            $table->timestamps();
        });

        Schema::create('job_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_vacancy_id')->constrained()->restrictOnDelete();
            $table->foreignId('applicant_id')->constrained()->restrictOnDelete();
            $table->string('cv_path');
            $table->text('cover_letter')->nullable();
            // Bukti persetujuan pemrosesan data pribadi.
            $table->dateTime('consent_at');
            $table->string('consent_ip', 45)->nullable();
            $table->string('status', 20)->index();
            $table->string('decision_note', 255)->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('decided_at')->nullable();
            // Unik: satu lamaran hanya bisa dikonversi menjadi satu karyawan.
            $table->foreignId('employee_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['job_vacancy_id', 'applicant_id']);
        });

        Schema::create('application_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_application_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->string('note', 255)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('interviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_application_id')->constrained()->cascadeOnDelete();
            $table->dateTime('scheduled_at');
            $table->unsignedSmallInteger('duration_minutes')->default(60);
            $table->string('mode', 10); // online | offline
            $table->string('location', 255);
            $table->foreignId('interviewer_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('scheduled'); // scheduled | completed | cancelled
            $table->string('recommendation', 20)->nullable(); // hire | consider | reject
            $table->text('summary')->nullable();
            $table->dateTime('scored_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('interview_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('interview_id')->constrained()->cascadeOnDelete();
            $table->string('indicator', 100);
            $table->unsignedTinyInteger('score');
            $table->string('comment', 255)->nullable();
            $table->timestamps();

            $table->unique(['interview_id', 'indicator']);
        });

        Schema::create('onboarding_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_application_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->unique()->constrained()->restrictOnDelete();
            $table->date('start_date');
            $table->json('checklist');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_records');
        Schema::dropIfExists('interview_scores');
        Schema::dropIfExists('interviews');
        Schema::dropIfExists('application_status_histories');
        Schema::dropIfExists('job_applications');
        Schema::dropIfExists('applicants');
        Schema::dropIfExists('job_vacancies');
    }
};
