<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Creates a development admin account for local use only.
     * Password is read via config() — never via env() directly.
     * Set NOVA_ADMIN_PASSWORD in your local .env file.
     */
    public function run(): void
    {
        $password = config('nova.seed_admin_password');

        if (empty($password)) {
            $this->command->warn(
                'NOVA_ADMIN_PASSWORD is not set. ' .
                'The admin account will be created without a usable password. ' .
                'Set NOVA_ADMIN_PASSWORD in your .env file and re-run: php artisan migrate:fresh --seed'
            );
        }

        User::updateOrCreate(
            ['email' => 'admin@novatech.com'],
            [
                'name'     => 'Nova Admin',
                'password' => Hash::make($password ?? ''),
            ]
        );

        $this->command->info('Development admin account: admin@novatech.com');
    }
}
