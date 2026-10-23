<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            // Jam lokal (WIB), bukan UTC.
            $table->time('start_time');
            $table->time('end_time');
            // ISO weekday: 1 = Senin ... 7 = Minggu.
            $table->json('work_days');
            $table->unsignedSmallInteger('late_tolerance_minutes')->nullable();
            $table->unsignedSmallInteger('check_in_opens_minutes')->default(120);
            $table->unsignedSmallInteger('check_out_closes_minutes')->default(360);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('employee_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_schedule_id')->constrained()->restrictOnDelete();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'effective_from']);
        });

        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('name', 150);
            $table->timestamps();
        });

        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            // Tanggal kerja menurut WIB.
            $table->date('work_date');
            $table->foreignId('work_schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('check_in_at')->nullable();
            $table->timestamp('check_out_at')->nullable();
            $table->string('status', 20)->index();
            $table->unsignedSmallInteger('late_minutes')->default(0);
            $table->string('source', 20)->default('scan');
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'work_date']);
            $table->index('work_date');
        });

        Schema::create('attendance_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->json('old_values')->nullable();
            $table->json('new_values');
            $table->string('reason', 255);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_corrections');
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('employee_schedules');
        Schema::dropIfExists('work_schedules');
    }
};
