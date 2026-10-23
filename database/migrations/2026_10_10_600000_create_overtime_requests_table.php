<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('overtime_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            // Tanggal lembur menurut WIB; jam mulai/selesai disimpan sebagai UTC jam satuan.
            $table->date('work_date');
            // Menggunakan Datetime
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->unsignedSmallInteger('duration_minutes');
            $table->text('reason');
            $table->string('status', 20)->index();
            $table->unsignedInteger('version')->default(1);
            // Lembur disetujui siap dibaca payroll
            $table->boolean('payable')->default(false)->index();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'work_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overtime_requests');
    }
};
