<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_components', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 100);
            $table->string('type', 20); // earning | deduction
            $table->string('calculation', 20); // fixed | percentage (dari gaji pokok)
            $table->decimal('default_amount', 15, 2)->nullable();
            $table->decimal('default_rate', 7, 4)->nullable(); // persen, mis. 1.0000 = 1%
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('employee_salary_structures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->decimal('base_salary', 15, 2);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'effective_from']);
        });

        Schema::create('salary_structure_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('structure_id')->constrained('employee_salary_structures')->cascadeOnDelete();
            $table->foreignId('salary_component_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 15, 2)->nullable();
            $table->decimal('rate', 7, 4)->nullable();
            $table->timestamps();

            $table->unique(['structure_id', 'salary_component_id']);
        });

        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->id();
            $table->string('code', 7)->unique(); // YYYY-MM
            $table->date('start_date');
            $table->date('end_date');
            $table->date('pay_date');
            $table->string('status', 20)->index();
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('calculated_at')->nullable();
            $table->foreignId('calculated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('payroll_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            // Snapshot saat dihitung, agar slip tidak berubah bila data karyawan berubah kemudian.
            $table->string('employee_name', 150);
            $table->string('nik', 30);
            $table->string('department_name', 100)->nullable();
            $table->string('position_name', 100)->nullable();
            $table->string('bank_name', 100)->nullable();
            $table->text('bank_account_number')->nullable(); // terenkripsi
            $table->string('bank_account_holder', 150)->nullable();
            $table->decimal('base_salary', 15, 2);
            $table->decimal('total_earnings', 15, 2);
            $table->decimal('total_deductions', 15, 2);
            $table->decimal('net_pay', 15, 2);
            $table->unsignedInteger('overtime_minutes')->default(0);
            $table->unsignedSmallInteger('present_days')->default(0);
            $table->unsignedSmallInteger('absent_days')->default(0);
            $table->unsignedSmallInteger('leave_days')->default(0);
            $table->timestamps();

            $table->unique(['payroll_period_id', 'employee_id']);
        });

        Schema::create('payroll_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('salary_component_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 150);
            $table->string('type', 20); // earning | deduction
            $table->string('source', 20); // base | component | overtime | manual
            $table->decimal('amount', 15, 2);
            $table->string('note', 255)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_record_id')->constrained()->restrictOnDelete();
            $table->string('idempotency_key', 100)->unique();
            $table->decimal('amount', 15, 2);
            $table->string('status', 20)->index();
            $table->string('reference', 100)->nullable();
            $table->string('proof_path')->nullable();
            $table->string('failure_reason', 255)->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Lembur yang sudah masuk payroll ditautkan agar tidak dibayar dua kali.
        Schema::table('overtime_requests', function (Blueprint $table) {
            $table->foreignId('payroll_record_id')->nullable()->after('payable')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('overtime_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payroll_record_id');
        });
        Schema::dropIfExists('payments');
        Schema::dropIfExists('payroll_items');
        Schema::dropIfExists('payroll_records');
        Schema::dropIfExists('payroll_periods');
        Schema::dropIfExists('salary_structure_items');
        Schema::dropIfExists('employee_salary_structures');
        Schema::dropIfExists('salary_components');
    }
};
