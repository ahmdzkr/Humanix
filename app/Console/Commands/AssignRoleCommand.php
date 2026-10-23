<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Memberi role ke akun yang sudah ada, mis. untuk menyiapkan Administrator pertama di produksi.
 */
class AssignRoleCommand extends Command
{
    protected $signature = 'humanix:assign-role {email : Email akun} {role : Administrator, HRD, Manager, atau Karyawan}';

    protected $description = 'Berikan role HUMANIX ke sebuah akun (role lama diganti)';

    public function handle(): int
    {
        $role = Role::tryFrom((string) $this->argument('role'));

        if ($role === null) {
            $this->error('Role tidak dikenal. Pilihan: '.implode(', ', array_column(Role::cases(), 'value')));

            return self::FAILURE;
        }

        $user = User::query()->where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->error('Akun dengan email tersebut tidak ditemukan.');

            return self::FAILURE;
        }

        $user->syncRoles([$role->value]);
        $this->info("Role {$role->value} diberikan ke {$user->email}.");

        return self::SUCCESS;
    }
}
