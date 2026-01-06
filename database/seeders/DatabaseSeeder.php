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

        // Seed pet breeds
        $this->call(PetBreedsSeeder::class);

        // Seed appointment types
        $this->call(AppointmentTypesSeeder::class);

        // Seed diseases
        $this->call(DiseasesSeeder::class);

        // Seed medicines
        $this->call(MedicineSeeder::class);

        // Seed symptoms (including specific vomiting types)
        $this->call(SymptomsSeeder::class);

        // Associate vomiting types with relevant diseases for ML training
        $this->call(VomitingTypesDiseaseAssociationSeeder::class);

        // Seed historical data (patients, appointments, prescriptions, etc.)
        $this->call(HistoricalDataSeeder::class);

        // Seed settings (ML prediction toggles, veterinarian info, etc.)
        $this->call(SettingsSeeder::class);

        // Optional: Import medical records from Excel file
        // Uncomment the line below to import data from public/dataset/PetMedicalRecord.xlsx
        // Make sure the Excel file exists and SymptomsSeeder has been run first
        $this->call(PetMedicalRecordSeeder::class);
    }
}
