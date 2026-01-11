<?php

namespace Database\Seeders;

use App\Models\Disease;
use App\Models\Medicine;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DiseaseMedicineSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * This seeder creates disease-medicine relationships for ALL diseases.
     * Each disease gets 2-4 randomly assigned medicines.
     */
    public function run(): void
    {
        $this->command->info('Creating disease-medicine relationships...');
        
        $diseases = Disease::all();
        $medicines = Medicine::all();
        
        if ($medicines->isEmpty()) {
            $this->command->error('No medicines found. Please run MedicineSeeder first.');
            return;
        }
        
        if ($diseases->isEmpty()) {
            $this->command->error('No diseases found. Please run DiseasesSeeder first.');
            return;
        }
        
        $count = 0;
        $diseasesWithMedicines = 0;
        
        foreach ($diseases as $disease) {
            // Check if disease already has medicines
            $existingMedicinesCount = DB::table('disease_medicines')
                ->where('disease_id', $disease->id)
                ->count();
            
            if ($existingMedicinesCount > 0) {
                // Skip diseases that already have medicines
                $diseasesWithMedicines++;
                continue;
            }
            
            // Assign 2-4 medicines per disease for better coverage
            $medicineCount = rand(2, 4);
            $selectedMedicines = $medicines->random(min($medicineCount, $medicines->count()));
            
            foreach ($selectedMedicines as $medicine) {
                DB::table('disease_medicines')->insertOrIgnore([
                    'disease_id' => $disease->id,
                    'medicine_id' => $medicine->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $count++;
            }
        }
        
        $this->command->info("✓ Created {$count} new disease-medicine relationships.");
        $this->command->info("✓ {$diseasesWithMedicines} diseases already had medicines.");
        $this->command->info("✓ Total diseases: {$diseases->count()}");
        
        // Verify all diseases now have medicines
        $diseasesWithoutMedicines = Disease::doesntHave('medicines')->count();
        if ($diseasesWithoutMedicines > 0) {
            $this->command->warn("⚠ Warning: {$diseasesWithoutMedicines} diseases still have no medicines!");
        } else {
            $this->command->info("✓ All diseases now have associated medicines.");
        }
    }
}
