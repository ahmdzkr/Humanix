<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Alamat yang benar-benar dituju bila email ke alamat dummy dialihkan (null = dikirim ke `email`).
        Schema::table('notification_deliveries', function (Blueprint $table) {
            $table->string('sent_to')->nullable()->after('email');
        });

        // Catatan HRD untuk pelamar, ikut dikirim di email undangan wawancara.
        Schema::table('interviews', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('location');
        });

        // Akun dari onboarding memakai kata sandi awal; wajib diganti saat login pertama.
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('must_change_password')->default(false)->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('must_change_password'));
        Schema::table('interviews', fn (Blueprint $table) => $table->dropColumn('notes'));
        Schema::table('notification_deliveries', fn (Blueprint $table) => $table->dropColumn('sent_to'));
    }
};
