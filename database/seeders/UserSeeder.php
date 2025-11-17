<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Create Roles
        $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $adminRole = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        $validatorRole = Role::firstOrCreate(['name' => 'Validator', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Instructor', 'guard_name' => 'web']);

        // Super Admin
        $superAdminEmail = config('autorank.super_admin_email');
        $superAdminPassword = $this->getPassword(config('autorank.super_admin_password'), 'DEFAULT_SUPER_ADMIN_PASSWORD');

        if (!$superAdminEmail) {
            throw new \Exception('DEFAULT_SUPER_ADMIN_EMAIL is not set in your .env file. This is required to run the seeder.');
        }

        $superAdmin = User::firstOrCreate(
            ['email' => $superAdminEmail],
            [
                'name' => 'Super Admin',
                'password' => Hash::make($superAdminPassword),
            ]
        );
        $superAdmin->assignRole($superAdminRole);

        // Admin
        $adminEmail = config('autorank.admin_email');
        $adminPassword = $this->getPassword(config('autorank.admin_password'), 'DEFAULT_ADMIN_PASSWORD');

        if (!$adminEmail) {
            throw new \Exception('DEFAULT_ADMIN_EMAIL is not set in your .env file. This is required to run the seeder.');
        }

        $admin = User::firstOrCreate(
            ['email' => $adminEmail],
            [
                'name' => 'Admin',
                'password' => Hash::make($adminPassword),
            ]
        );
        $admin->assignRole($adminRole);

        // Validator
        $validatorEmail = config('autorank.validator_email');
        $validatorPassword = $this->getPassword(config('autorank.validator_password'), 'DEFAULT_VALIDATOR_PASSWORD');

        if (!$validatorEmail) {
            throw new \Exception('DEFAULT_VALIDATOR_EMAIL is not set in your .env file. This is required to run the seeder.');
        }

        $validator = User::firstOrCreate(
            ['email' => $validatorEmail],
            [
                'name' => 'Validator',
                'password' => Hash::make($validatorPassword),
            ]
        );
        $validator->assignRole($validatorRole);


        // Sync Permissions
        $allPermissions = Permission::all();
        $superAdminRole->syncPermissions($allPermissions);
    }

    /**
     * Get the password based on the environment.
     *
     * @param string|null $configPassword
     * @param string $envKey The name of the .env variable for the error message
     * @return string
     * @throws \Exception
     */
    private function getPassword(?string $configPassword, string $envKey): string
    {
        if (App::environment('local')) {
            return 'password';
        }

        if ($configPassword) {
            return $configPassword;
        }

        throw new \Exception(
            "In the production environment, you must set {$envKey} in your .env file."
        );
    }
}
