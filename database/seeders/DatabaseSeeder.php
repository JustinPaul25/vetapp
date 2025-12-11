<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed roles first
        $this->call(RoleSeeder::class);

        // Seed users
        $this->call(UserSeeder::class);

        // Seed pet types
        $this->call(PetTypesSeeder::class);

        // Seed appointment types
        $this->call(AppointmentTypesSeeder::class);

        // Seed diseases
        $this->call(DiseasesSeeder::class);

        // Seed medicines
        $this->call(MedicineSeeder::class);

        // Seed historical data (patients, appointments, prescriptions, etc.)
        $this->call(HistoricalDataSeeder::class);
    }
}
