<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\ImportsPetMedicalRecordRows;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PetMedicalRecordCsvSeeder extends Seeder
{
    use ImportsPetMedicalRecordRows;

    /**
     * Import Pet Medical Record CSV (same shape as Excel export) into disease_symptoms / disease_medicines for ML diagnosis training.
     * Duplicate pivot pairs are skipped automatically.
     */
    public function run(): void
    {
        $csvPath = public_path('dataset/2025-2026-pet-medical-record.csv');

        if (! file_exists($csvPath)) {
            $this->command->error("CSV file not found at: {$csvPath}");
            $this->command->comment('Place your export at public/dataset/2025-2026-pet-medical-record.csv or adjust the path in PetMedicalRecordCsvSeeder.');

            return;
        }

        $this->command->info("Starting Pet Medical Record import from CSV: {$csvPath}");

        $handle = fopen($csvPath, 'rb');
        if ($handle === false) {
            $this->command->error("Could not open CSV: {$csvPath}");

            return;
        }

        $symptomCount = 0;
        $diseaseCount = 0;
        $medicineCount = 0;
        $relationshipCount = 0;
        $skippedCount = 0;
        $totalRowsProcessed = 0;
        $rowNum = 1;

        try {
            $headers = fgetcsv($handle);
            if ($headers === false) {
                $this->command->error('Could not read CSV header row.');

                return;
            }

            if (isset($headers[0])) {
                $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
            }

            $headers = array_map(fn ($h) => trim((string) $h), $headers);
            $headersLower = array_map('strtolower', $headers);

            $columnIndices = $this->findColumnIndices($headersLower);
            $columnCount = count($headers);

            while (($row = fgetcsv($handle)) !== false) {
                $rowNum++;

                if (empty(array_filter($row, fn ($val) => $val !== null && trim((string) $val) !== ''))) {
                    continue;
                }

                try {
                    while (count($row) < $columnCount) {
                        $row[] = '';
                    }

                    $res = $this->processMedicalRecordRow($row, $columnIndices);
                    if (! $res['processed']) {
                        continue;
                    }
                    $symptomCount += $res['symptoms_new'];
                    $diseaseCount += $res['diseases_new'];
                    $medicineCount += $res['medicines_new'];
                    $relationshipCount += $res['relationships_new'];
                    $totalRowsProcessed++;

                    if (($totalRowsProcessed % 100) === 0) {
                        $this->command->info("Processed {$totalRowsProcessed} training rows... (new symptom/disease/medicine records: {$symptomCount}/{$diseaseCount}/{$medicineCount}, new pivots: {$relationshipCount})");
                    }
                } catch (\Exception $e) {
                    $this->command->warn("Error CSV row {$rowNum}: ".$e->getMessage());
                    $skippedCount++;
                }
            }
        } finally {
            fclose($handle);
        }

        $this->command->info('CSV import completed!');
        $this->command->info("Training rows applied: {$totalRowsProcessed}");
        $this->command->info("New symptoms added: {$symptomCount}");
        $this->command->info("New diseases added: {$diseaseCount}");
        $this->command->info("New medicines added: {$medicineCount}");
        $this->command->info("New relationships created: {$relationshipCount}");
        $this->command->info("Rows skipped (errors): {$skippedCount}");
        $this->command->info('   - disease_symptoms relationships: '.DB::table('disease_symptoms')->count());
        $this->command->info('   - disease_medicines relationships: '.DB::table('disease_medicines')->count());
    }
}
