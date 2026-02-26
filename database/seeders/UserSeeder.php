<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create admin user (verified for easy access)
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'first_name' => 'Admin',
                'last_name' => 'User',
                'email' => 'admin@example.com',
                'password' => Hash::make('password'),
                'mobile_number' => '+639123456789',
                'address' => '123 Admin Street, Panabo City',
                'active' => true,
                'email_verified_at' => now(),
                'lat' => 7.3083,    // Panabo City Center
                'long' => 125.6844,
            ]
        );
        if (!$admin->hasRole('admin')) {
            $admin->assignRole('admin');
        }

        // Create staff user (verified for easy access)
        $staff = User::firstOrCreate(
            ['email' => 'staff@example.com'],
            [
                'name' => 'Staff User',
                'first_name' => 'Staff',
                'last_name' => 'User',
                'email' => 'staff@example.com',
                'password' => Hash::make('password'),
                'mobile_number' => '+639123456790',
                'address' => '456 Staff Avenue, Panabo City',
                'active' => true,
                'email_verified_at' => now(),
                'lat' => 7.3102,    // Near Panabo Public Market
                'long' => 125.6821,
            ]
        );
        if (!$staff->hasRole('staff')) {
            $staff->assignRole('staff');
        }

        $this->command->info('Users seeded successfully!');
        $this->command->info('');
        $this->command->info('=== Seeded users ===');
        $this->command->info('Admin: admin@example.com / password');
        $this->command->info('Staff: staff@example.com / password');
    }
}
