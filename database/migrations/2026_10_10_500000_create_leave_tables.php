<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 100);
            // Jenis bersaldo (mis. Cuti Tahunan) mengurangi kuota per tahun.
            $table->boolean('uses_balance')->default(false);
            $table->unsignedSmallInteger('default_quota')->default(0);
            $table->boolean('requires_attachment')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('leave_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('year');
            $table->decimal('quota', 5, 1);
            $table->decimal('used', 5, 1)->default(0);
            $table->timestamps();

            $table->unique(['employee_id', 'leave_type_id', 'year']);
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->restrictOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            // Jumlah hari kerja (tanpa akhir pekan sesuai jadwal & hari libur).
            $table->unsignedSmallInteger('days');
            $table->text('reason');
            $table->string('attachment_path')->nullable();
            $table->string('status', 20)->index();
            // Optimistic locking: dicek & dinaikkan setiap aksi persetujuan.
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'start_date', 'end_date']);
        });

        // Langkah persetujuan dipakai bersama oleh cuti dan lembur.
        Schema::create('approval_steps', function (Blueprint $table) {
            $table->id();
            $table->morphs('approvable');
            $table->unsignedTinyInteger('step');
            $table->string('approver_role', 20);
            // Untuk langkah atasan: user yang berhak menyetujui.
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('pending');
            $table->string('note', 255)->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->unique(['approvable_type', 'approvable_id', 'step']);
            $table->index(['status', 'approver_role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_steps');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('leave_balances');
        Schema::dropIfExists('leave_types');
    }
};
