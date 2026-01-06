<?php

namespace Database\Seeders;

use App\Models\Disease;
use App\Models\Medicine;
use App\Models\Symptom;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class PetMedicalRecordSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Starting Pet Medical Record import from Excel...');

        $excelPath = public_path('dataset/PetMedicalRecord.xlsx');

        if (!file_exists($excelPath)) {
            $this->command->error("Excel file not found at: {$excelPath}");
            return;
        }

        try {
            $spreadsheet = IOFactory::load($excelPath);
            
            // Get all sheet names
            $sheetNames = $spreadsheet->getSheetNames();
            $this->command->info('Found sheets: ' . implode(', ', $sheetNames));

            $symptomCount = 0;
            $diseaseCount = 0;
            $medicineCount = 0;
            $relationshipCount = 0;
            $skippedCount = 0;
            $totalRowsProcessed = 0;

            // Process each sheet
            foreach ($sheetNames as $sheetIndex => $sheetName) {
                $this->command->info("Processing sheet: {$sheetName}...");
                
                $worksheet = $spreadsheet->getSheet($sheetIndex);
                $rows = $worksheet->toArray();

                if (empty($rows)) {
                    $this->command->warn("Sheet '{$sheetName}' is empty, skipping...");
                    continue;
                }

                // Get headers (first row)
                $headers = array_map('trim', $rows[0]);
                $headersLower = array_map('strtolower', $headers);
                
                // Find column indices for symptom, diagnosis, and prescription columns
                $columnIndices = $this->findColumnIndices($headersLower);

                // Process data rows (skip header row)
                for ($i = 1; $i < count($rows); $i++) {
                    $row = $rows[$i];

                    // Skip empty rows
                    if (empty(array_filter($row, function($val) { return $val !== null && trim((string)$val) !== ''; }))) {
                        continue;
                    }

                    try {
                        // Extract symptoms from symptom_1 to symptom_9
                        $symptoms = $this->extractSymptoms($row, $columnIndices);
                        $symptomModels = [];
                        foreach ($symptoms as $symptomName) {
                            if (!empty($symptomName)) {
                                // Clean symptom name (remove leading numbers and extra spaces)
                                $cleanedSymptom = $this->cleanSymptomName($symptomName);
                                if (!empty($cleanedSymptom)) {
                                    $symptom = Symptom::firstOrCreate(['name' => $cleanedSymptom]);
                                    if ($symptom->wasRecentlyCreated) {
                                        $symptomCount++;
                                    }
                                    $symptomModels[] = $symptom;
                                }
                            }
                        }

                        // Extract diagnoses from diagnosis_1 to diagnosis_4
                        $diagnoses = $this->extractDiagnoses($row, $columnIndices);
                        $diseaseModels = [];
                        foreach ($diagnoses as $diagnosisName) {
                            if (!empty($diagnosisName)) {
                                $cleanedDiagnosis = trim($diagnosisName);
                                if (!empty($cleanedDiagnosis)) {
                                    $disease = Disease::firstOrCreate(['name' => $cleanedDiagnosis]);
                                    if ($disease->wasRecentlyCreated) {
                                        $diseaseCount++;
                                    }
                                    $diseaseModels[] = $disease;
                                }
                            }
                        }

                        // Extract prescriptions from prescription_1 to prescription_5
                        $prescriptions = $this->extractPrescriptions($row, $columnIndices);
                        $medicineModels = [];
                        foreach ($prescriptions as $prescriptionName) {
                            if (!empty($prescriptionName)) {
                                $cleanedPrescription = trim($prescriptionName);
                                if (!empty($cleanedPrescription)) {
                                    $medicine = Medicine::firstOrCreate(['name' => $cleanedPrescription], [
                                        'dosage' => 'As prescribed',
                                        'route' => 'PO',
                                        'stock' => 0,
                                    ]);
                                    if ($medicine->wasRecentlyCreated) {
                                        $medicineCount++;
                                    }
                                    $medicineModels[] = $medicine;
                                }
                            }
                        }

                        // Create relationships for ML training
                        // Link symptoms to diseases (disease_symptoms table)
                        foreach ($diseaseModels as $disease) {
                            foreach ($symptomModels as $symptom) {
                                $exists = DB::table('disease_symptoms')
                                    ->where('disease_id', $disease->id)
                                    ->where('symptom_id', $symptom->id)
                                    ->exists();
                                
                                if (!$exists) {
                                    DB::table('disease_symptoms')->insert([
                                        'disease_id' => $disease->id,
                                        'symptom_id' => $symptom->id,
                                        'created_at' => now(),
                                        'updated_at' => now(),
                                    ]);
                                    $relationshipCount++;
                                }
                            }
                        }

                        // Link medicines to diseases (disease_medicines table)
                        foreach ($diseaseModels as $disease) {
                            foreach ($medicineModels as $medicine) {
                                $exists = DB::table('disease_medicines')
                                    ->where('disease_id', $disease->id)
                                    ->where('medicine_id', $medicine->id)
                                    ->exists();
                                
                                if (!$exists) {
                                    DB::table('disease_medicines')->insert([
                                        'disease_id' => $disease->id,
                                        'medicine_id' => $medicine->id,
                                        'created_at' => now(),
                                        'updated_at' => now(),
                                    ]);
                                    $relationshipCount++;
                                }
                            }
                        }

                        $totalRowsProcessed++;
                        
                        if (($totalRowsProcessed % 50) == 0) {
                            $this->command->info("Processed {$totalRowsProcessed} rows... (Symptoms: {$symptomCount}, Diseases: {$diseaseCount}, Medicines: {$medicineCount}, Relationships: {$relationshipCount})");
                        }
                    } catch (\Exception $e) {
                        $this->command->warn("Error processing row " . ($i + 1) . " in sheet '{$sheetName}': " . $e->getMessage());
                        $skippedCount++;
                        continue;
                    }
                }

                $this->command->info("Completed sheet: {$sheetName}");
            }

            $this->command->info("Import completed!");
            $this->command->info("Total rows processed: {$totalRowsProcessed}");
            $this->command->info("New symptoms added: {$symptomCount}");
            $this->command->info("New diseases added: {$diseaseCount}");
            $this->command->info("New medicines added: {$medicineCount}");
            $this->command->info("New relationships created: {$relationshipCount} (for ML training)");
            $this->command->info("Rows skipped: {$skippedCount}");
            $this->command->info("");
            $this->command->info("✅ The imported data is now ready for ML model training!");
            $this->command->info("   - disease_symptoms relationships: " . DB::table('disease_symptoms')->count());
            $this->command->info("   - disease_medicines relationships: " . DB::table('disease_medicines')->count());
        } catch (\Exception $e) {
            $this->command->error("Error reading Excel file: " . $e->getMessage());
            $this->command->error($e->getTraceAsString());
        }
    }

    /**
     * Find column indices for symptom, diagnosis, and prescription columns
     */
    private function findColumnIndices(array $headersLower): array
    {
        $indices = [];

        // Find symptom columns (symptom_1 to symptom_9)
        for ($i = 1; $i <= 9; $i++) {
            $symptomKey = 'symptom_' . $i;
            $index = array_search($symptomKey, $headersLower);
            if ($index !== false) {
                $indices['symptom_' . $i] = $index;
            }
        }

        // Find diagnosis columns (diagnosis_1 to diagnosis_4)
        for ($i = 1; $i <= 4; $i++) {
            $diagnosisKey = 'diagnosis_' . $i;
            $index = array_search($diagnosisKey, $headersLower);
            if ($index !== false) {
                $indices['diagnosis_' . $i] = $index;
            }
        }

        // Find prescription columns (prescription_1 to prescription_5)
        for ($i = 1; $i <= 5; $i++) {
            $prescriptionKey = 'prescription_' . $i;
            $index = array_search($prescriptionKey, $headersLower);
            if ($index !== false) {
                $indices['prescription_' . $i] = $index;
            }
        }

        return $indices;
    }

    /**
     * Extract symptoms from symptom_1 to symptom_9
     */
    private function extractSymptoms(array $row, array $columnIndices): array
    {
        $symptoms = [];
        
        // Extract from symptom_1 to symptom_9
        for ($i = 1; $i <= 9; $i++) {
            $index = $columnIndices['symptom_' . $i] ?? null;
            if ($index !== null && isset($row[$index])) {
                $symptom = trim((string)$row[$index]);
                if (!empty($symptom) && $symptom !== '') {
                    $symptoms[] = $symptom;
                }
            }
        }

        return array_filter($symptoms);
    }

    /**
     * Extract diagnoses from diagnosis_1 to diagnosis_4
     */
    private function extractDiagnoses(array $row, array $columnIndices): array
    {
        $diagnoses = [];
        
        // Extract from diagnosis_1 to diagnosis_4
        for ($i = 1; $i <= 4; $i++) {
            $index = $columnIndices['diagnosis_' . $i] ?? null;
            if ($index !== null && isset($row[$index])) {
                $diagnosis = trim((string)$row[$index]);
                if (!empty($diagnosis) && $diagnosis !== '') {
                    $diagnoses[] = $diagnosis;
                }
            }
        }

        return array_filter($diagnoses);
    }

    /**
     * Extract prescriptions from prescription_1 to prescription_5
     */
    private function extractPrescriptions(array $row, array $columnIndices): array
    {
        $prescriptions = [];
        
        // Extract from prescription_1 to prescription_5
        for ($i = 1; $i <= 5; $i++) {
            $index = $columnIndices['prescription_' . $i] ?? null;
            if ($index !== null && isset($row[$index])) {
                $prescription = trim((string)$row[$index]);
                if (!empty($prescription) && $prescription !== '') {
                    $prescriptions[] = $prescription;
                }
            }
        }

        return array_filter($prescriptions);
    }

    /**
     * Clean symptom name by removing leading numbers and extra spaces
     * Example: "36 Anemia" -> "Anemia"
     */
    private function cleanSymptomName(string $symptomName): string
    {
        $cleaned = trim($symptomName);
        
        // Remove leading numbers followed by space
        // Pattern: one or more digits at the start, followed by a space
        $cleaned = preg_replace('/^\d+\s+/', '', $cleaned);
        
        // Remove any remaining leading/trailing whitespace
        $cleaned = trim($cleaned);
        
        // Capitalize first letter
        if (!empty($cleaned)) {
            $cleaned = ucfirst(strtolower($cleaned));
        }
        
        return $cleaned;
    }
}
