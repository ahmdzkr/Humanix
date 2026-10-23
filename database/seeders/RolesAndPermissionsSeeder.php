<?php

namespace Database\Seeders;

use App\Enums\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;

/**
 * Role dan permission dasar. Aman dijalankan ulang (idempotent) dan dipakai di produksi.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Role::allPermissions() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach (Role::cases() as $role) {
            RoleModel::findOrCreate($role->value, 'web')
                ->syncPermissions($role->defaultPermissions());
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
