<?php

namespace App\Console\Commands;

use App\Models\Disease;
use App\Models\Medicine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AssociateDiseasesWithMedicines extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'diseases:associate-medicines {--force : Force re-association even for diseases with existing medicines}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Associate all diseases with medicines (fixes diseases that have no medicines)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting disease-medicine association...');
        $this->newLine();
        
        $diseases = Disease::all();
        $medicines = Medicine::all();
        
        if ($medicines->isEmpty()) {
            $this->error('No medicines found in the database. Please seed medicines first.');
            return Command::FAILURE;
        }
        
        if ($diseases->isEmpty()) {
            $this->error('No diseases found in the database. Please seed diseases first.');
            return Command::FAILURE;
        }
        
        $this->info("Found {$diseases->count()} diseases and {$medicines->count()} medicines.");
        $this->newLine();
        
        $force = $this->option('force');
        $count = 0;
        $skipped = 0;
        $updated = 0;
        
        $progressBar = $this->output->createProgressBar($diseases->count());
        $progressBar->start();
        
        foreach ($diseases as $disease) {
            // Check if disease already has medicines
            $existingMedicinesCount = DB::table('disease_medicines')
                ->where('disease_id', $disease->id)
                ->count();
            
            if ($existingMedicinesCount > 0 && !$force) {
                // Skip diseases that already have medicines (unless force option is used)
                $skipped++;
                $progressBar->advance();
                continue;
            }
            
            if ($force && $existingMedicinesCount > 0) {
                // If force option is used, clear existing medicines first
                DB::table('disease_medicines')
                    ->where('disease_id', $disease->id)
                    ->delete();
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
            
            $updated++;
            $progressBar->advance();
        }
        
        $progressBar->finish();
        $this->newLine(2);
        
        $this->info("✓ Created {$count} disease-medicine relationships.");
        $this->info("✓ Updated {$updated} diseases.");
        $this->info("✓ Skipped {$skipped} diseases (already had medicines).");
        $this->newLine();
        
        // Verify all diseases now have medicines
        $diseasesWithoutMedicines = Disease::doesntHave('medicines')->count();
        if ($diseasesWithoutMedicines > 0) {
            $this->warn("⚠ Warning: {$diseasesWithoutMedicines} diseases still have no medicines!");
            
            // Show which diseases have no medicines
            $diseasesWithoutMedicinesList = Disease::doesntHave('medicines')->pluck('name')->take(10);
            $this->warn("First 10 diseases without medicines:");
            foreach ($diseasesWithoutMedicinesList as $diseaseName) {
                $this->line("  - {$diseaseName}");
            }
            
            return Command::FAILURE;
        } else {
            $this->info("✓ All diseases now have associated medicines!");
            return Command::SUCCESS;
        }
    }
}
