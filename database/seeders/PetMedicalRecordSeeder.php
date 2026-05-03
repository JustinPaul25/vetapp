<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\ImportsPetMedicalRecordRows;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class PetMedicalRecordSeeder extends Seeder
{
    use ImportsPetMedicalRecordRows;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Starting Pet Medical Record import from Excel...');

        $excelPath = public_path('dataset/PetMedicalRecord.xlsx');

        if (! file_exists($excelPath)) {
            $this->command->error("Excel file not found at: {$excelPath}");

            return;
        }

        try {
            $spreadsheet = IOFactory::load($excelPath);

            $sheetNames = $spreadsheet->getSheetNames();
            $this->command->info('Found sheets: '.implode(', ', $sheetNames));

            $symptomCount = 0;
            $diseaseCount = 0;
            $medicineCount = 0;
            $relationshipCount = 0;
            $skippedCount = 0;
            $totalRowsProcessed = 0;

            foreach ($sheetNames as $sheetIndex => $sheetName) {
                $this->command->info("Processing sheet: {$sheetName}...");

                $worksheet = $spreadsheet->getSheet($sheetIndex);
                $rows = $worksheet->toArray();

                if (empty($rows)) {
                    $this->command->warn("Sheet '{$sheetName}' is empty, skipping...");

                    continue;
                }

                $headers = array_map('trim', $rows[0]);
                $headersLower = array_map('strtolower', $headers);

                $columnIndices = $this->findColumnIndices($headersLower);

                for ($i = 1; $i < count($rows); $i++) {
                    $row = $rows[$i];

                    if (empty(array_filter($row, function ($val) {
                        return $val !== null && trim((string) $val) !== '';
                    }))) {
                        continue;
                    }

                    try {
                        $res = $this->processMedicalRecordRow($row, $columnIndices);
                        if (! $res['processed']) {
                            continue;
                        }
                        $symptomCount += $res['symptoms_new'];
                        $diseaseCount += $res['diseases_new'];
                        $medicineCount += $res['medicines_new'];
                        $relationshipCount += $res['relationships_new'];
                        $totalRowsProcessed++;

                        if (($totalRowsProcessed % 50) == 0) {
                            $this->command->info("Processed {$totalRowsProcessed} rows... (Symptoms: {$symptomCount}, Diseases: {$diseaseCount}, Medicines: {$medicineCount}, Relationships: {$relationshipCount})");
                        }
                    } catch (\Exception $e) {
                        $this->command->warn('Error processing row '.($i + 1)." in sheet '{$sheetName}': ".$e->getMessage());
                        $skippedCount++;
                    }
                }

                $this->command->info("Completed sheet: {$sheetName}");
            }

            $this->command->info('Import completed!');
            $this->command->info("Total rows processed: {$totalRowsProcessed}");
            $this->command->info("New symptoms added: {$symptomCount}");
            $this->command->info("New diseases added: {$diseaseCount}");
            $this->command->info("New medicines added: {$medicineCount}");
            $this->command->info("New relationships created: {$relationshipCount} (for ML training)");
            $this->command->info("Rows skipped: {$skippedCount}");
            $this->command->info('');
            $this->command->info('✅ The imported data is now ready for ML model training!');
            $this->command->info('   - disease_symptoms relationships: '.DB::table('disease_symptoms')->count());
            $this->command->info('   - disease_medicines relationships: '.DB::table('disease_medicines')->count());
        } catch (\Exception $e) {
            $this->command->error('Error reading Excel file: '.$e->getMessage());
            $this->command->error($e->getTraceAsString());
        }
    }
}
