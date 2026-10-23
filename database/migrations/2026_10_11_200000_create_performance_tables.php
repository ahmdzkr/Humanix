<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_periods', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('self_assessment_enabled')->default(true);
            $table->string('status', 20)->index(); // draft | active | closed
            $table->dateTime('activated_at')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('performance_indicators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('performance_period_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->decimal('weight', 5, 2); // persen; total per periode harus 100
            $table->unsignedTinyInteger('scale_min')->default(1);
            $table->unsignedTinyInteger('scale_max')->default(5);
            $table->string('target', 255)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('performance_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('performance_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            // Penilai = atasan langsung saat periode diaktifkan; null → dinilai HRD.
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->index();
            $table->unsignedInteger('version')->default(1);
            $table->text('manager_comment')->nullable();
            $table->text('hr_comment')->nullable();
            $table->decimal('final_score', 5, 2)->nullable(); // 0–100
            $table->string('rating', 30)->nullable();
            $table->dateTime('published_at')->nullable();
            $table->timestamps();

            $table->unique(['performance_period_id', 'employee_id']);
        });

        Schema::create('performance_review_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('performance_review_id')->constrained()->cascadeOnDelete();
            $table->foreignId('performance_indicator_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('self_score')->nullable();
            $table->string('self_comment', 500)->nullable();
            $table->unsignedTinyInteger('manager_score')->nullable();
            $table->string('manager_comment', 500)->nullable();
            $table->timestamps();

            $table->unique(['performance_review_id', 'performance_indicator_id'], 'review_indicator_unique');
        });

        Schema::create('self_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('performance_review_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('achievements');
            $table->text('challenges')->nullable();
            $table->dateTime('submitted_at');
            $table->timestamps();
        });

        Schema::create('development_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('performance_review_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // training | improvement | promotion | other
            $table->text('description');
            $table->date('target_date')->nullable();
            $table->string('status', 20)->default('planned'); // planned | in_progress | done
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Jejak setiap tindakan pada penilaian (siapa, apa, catatan).
        Schema::create('performance_review_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('performance_review_id')->constrained()->cascadeOnDelete();
            $table->string('action', 30);
            $table->string('note', 500)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_review_logs');
        Schema::dropIfExists('development_plans');
        Schema::dropIfExists('self_assessments');
        Schema::dropIfExists('performance_review_items');
        Schema::dropIfExists('performance_reviews');
        Schema::dropIfExists('performance_indicators');
        Schema::dropIfExists('performance_periods');
    }
};
