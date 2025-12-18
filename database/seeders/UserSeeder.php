<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

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

        // Create verified client user (for testing verified users)
        $client = User::firstOrCreate(
            ['email' => 'client@example.com'],
            [
                'name' => 'Client User',
                'first_name' => 'Client',
                'last_name' => 'User',
                'email' => 'client@example.com',
                'password' => Hash::make('password'),
                'mobile_number' => '+639123456791',
                'address' => '789 Client Road, Panabo City',
                'active' => true,
                'email_verified_at' => now(),
                'lat' => 7.3045,    // Barangay Gredu area
                'long' => 125.6912,
            ]
        );
        if (!$client->hasRole('client')) {
            $client->assignRole('client');
        }

        // Create additional verified test users
        $verifiedUsers = [
            [
                'name' => 'John Doe',
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'john@example.com',
                'password' => Hash::make('password'),
                'mobile_number' => '+639123456792',
                'address' => '321 Oak Street, Panabo City',
                'active' => true,
                'role' => 'client',
                'email_verified_at' => now(),
                'lat' => 7.2956,    // Barangay San Francisco area
                'long' => 125.6798,
            ],
            [
                'name' => 'Jane Smith',
                'first_name' => 'Jane',
                'last_name' => 'Smith',
                'email' => 'jane@example.com',
                'password' => Hash::make('password'),
                'mobile_number' => '+639123456793',
                'address' => '654 Pine Avenue, Panabo City',
                'active' => true,
                'role' => 'staff',
                'email_verified_at' => now(),
                'lat' => 7.3125,    // Barangay J.P. Laurel area
                'long' => 125.6756,
            ],
        ];

        foreach ($verifiedUsers as $userData) {
            $role = $userData['role'];
            unset($userData['role']);

            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                $userData
            );
            if (!$user->hasRole($role)) {
                $user->assignRole($role);
            }
        }

        // Create unverified test users (to test email verification flow)
        $unverifiedUsers = [
            [
                'name' => 'Bob Johnson',
                'first_name' => 'Bob',
                'last_name' => 'Johnson',
                'email' => 'bob@example.com',
                'password' => Hash::make('password'),
                'mobile_number' => '+639123456794',
                'address' => '987 Elm Street, Panabo City',
                'active' => true,
                'role' => 'client',
                'email_verified_at' => null, // Unverified
                'lat' => 7.3198,    // Barangay New Pandan area
                'long' => 125.6889,
            ],
            [
                'name' => 'Alice Williams',
                'first_name' => 'Alice',
                'last_name' => 'Williams',
                'email' => 'alice@example.com',
                'password' => Hash::make('password'),
                'mobile_number' => '+639123456795',
                'address' => '147 Maple Drive, Panabo City',
                'active' => true,
                'role' => 'client',
                'email_verified_at' => null, // Unverified
                'lat' => 7.2889,    // Barangay Cagangohan area
                'long' => 125.6934,
            ],
        ];

        foreach ($unverifiedUsers as $userData) {
            $role = $userData['role'];
            unset($userData['role']);

            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                $userData
            );
            if (!$user->hasRole($role)) {
                $user->assignRole($role);
            }
        }

        $this->command->info('Users seeded successfully!');
        $this->command->info('');
        $this->command->info('=== Verified Users (for testing) ===');
        $this->command->info('Admin: admin@example.com / password');
        $this->command->info('Staff: staff@example.com / password');
        $this->command->info('Client: client@example.com / password');
        $this->command->info('John Doe: john@example.com / password');
        $this->command->info('Jane Smith: jane@example.com / password');
        $this->command->info('');
        $this->command->info('=== Unverified Users (to test email verification) ===');
        $this->command->info('Bob Johnson: bob@example.com / password');
        $this->command->info('Alice Williams: alice@example.com / password');
    }
}
