<?php

namespace Database\Seeders;

use App\Models\Medicine;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use PhpOffice\PhpSpreadsheet\IOFactory;

class MedicineSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->command->info('Starting Medicine import from Excel...');

        // First, seed the existing hardcoded data (injectable medicines)
        $existingData = [
            ["name" => "Amoxicillin trihydrate", "dosage" => "1ml/10 Kg body weight", "route" => "IM"],
            ["name" => "Cephalexin+Ciprofloxacin+Dexamethasone", "dosage" => "1ml/10 Kg body weight", "route" => "IM"],
            ["name" => "Oxytetracycline dihydrate", "dosage" => "1ml/10 Kg body weight", "route" => "IM"],
            ["name" => "DUFA-PenStrep", "dosage" => "1ml/20 Kg body weight", "route" => "IM"],
            ["name" => "TMPS", "dosage" => "1ml/10 Kg body weight", "route" => "IM"],
            ["name" => "Ceftiofur HCl", "dosage" => "1ml/50 Kg body weight", "route" => "IM"],
            ["name" => "Dextrolyte (Multivits.+Electrolytes+A.A.)", "dosage" => "1ml/10 Kg body weight", "route" => "IM"],
            ["name" => "Dexamethasone", "dosage" => "1ml/10 Kg body weight", "route" => "IM"],
            ["name" => "Ivermectin", "dosage" => "1ml/20 Kg body weight", "route" => "SC"],
            ["name" => "Multivitamins+Liver Extract", "dosage" => "1ml/20 Kg body weight", "route" => "IM"],
            ["name" => "Oxytocin", "dosage" => "1ml/10 Kg body weight", "route" => "IM"],
            ["name" => "DCM (Dextrose+Calcium+Magnesium)", "dosage" => "1ml/10 Kg body weight", "route" => "IM"],
            ["name" => "CBG (Calcium borogluconate)", "dosage" => "1ml/10 Kg body weight", "route" => "IM"],
            ["name" => "Tolfenamic Acid", "dosage" => "1ml/20 Kg body weight", "route" => "IM"],
            ["name" => "Vit. ADE", "dosage" => "1ml/20 Kg body weight", "route" => "IM"],
            ["name" => "Iron Dextran", "dosage" => "1ml/20 Kg body weight", "route" => "IM"],
            ["name" => "Enrofloxacin", "dosage" => "1ml/20 Kg body weight", "route" => "IM"],
            ["name" => "Epinephrine HCl", "dosage" => "1ml/10 Kg body weight", "route" => "IM"],
            ["name" => "Paracetamol", "dosage" => "1ml/15 Kg body weight", "route" => "IM"],
            ["name" => "Florfenicol 10%", "dosage" => "1.5ml/10 Kg body weight", "route" => "IM"],
            ["name" => "Florfenicol 20%", "dosage" => "1ml/10 Kg body weight", "route" => "IM"],
            ["name" => "Florfenicol 30%", "dosage" => "1ml/15 Kg body weight", "route" => "IM"],
            ["name" => "PenStrep", "dosage" => "1ml/10 Kg body weight", "route" => "IM"],
            ["name" => "Praziquantel+Pyrantel Oral Suspension", "dosage" => "1ml/1 Kg body weight", "route" => "PO"],
            ["name" => "Albendazole", "dosage" => "1ml/2 Kg body weight", "route" => "PO"],
            ["name" => "Tetanus Antitoxin", "dosage" => "1ml/15 Kg body weight", "route" => "IM"],
        ];

        foreach ($existingData as $item) {
            $medicine = Medicine::where('name', $item['name'])->first();
            if (empty($medicine)) {
                Medicine::create([
                    'name' => $item['name'],
                    'dosage' => $item['dosage'],
                    'route' => $item['route'],
                    'stock' => rand(5, 20)
                ]);
            }
        }

        // Now import from Excel file
        $excelPath = public_path('dataset/Medecine.xlsx');

        if (!file_exists($excelPath)) {
            $this->command->warn("Excel file not found at: {$excelPath}. Skipping Excel import.");
            return;
        }

        try {
            $spreadsheet = IOFactory::load($excelPath);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            if (empty($rows) || count($rows) < 2) {
                $this->command->warn("Excel file is empty or has no data rows.");
                return;
            }

            // Skip header row (row 0)
            $importedCount = 0;
            $skippedCount = 0;

            for ($i = 1; $i < count($rows); $i++) {
                $row = $rows[$i];

                // Skip empty rows
                if (empty(array_filter($row, function($val) { 
                    return $val !== null && trim((string)$val) !== ''; 
                }))) {
                    continue;
                }

                // Extract data from columns
                // Column 0: PRESCRIPTION DRUGS (name)
                // Column 1: ROUTE
                // Column 2: DOSAGE
                $name = isset($row[0]) ? trim((string)$row[0]) : null;
                $route = isset($row[1]) ? trim((string)$row[1]) : null;
                $dosage = isset($row[2]) ? trim((string)$row[2]) : null;

                // Skip if name is empty
                if (empty($name)) {
                    $skippedCount++;
                    continue;
                }

                // Normalize route
                $normalizedRoute = $this->normalizeRoute($route);

                // Use dosage from Excel, or default if empty
                $finalDosage = !empty($dosage) ? $dosage : 'As prescribed';

                // Create or update medicine
                $medicine = Medicine::where('name', $name)->first();
                if (empty($medicine)) {
                    Medicine::create([
                        'name' => $name,
                        'dosage' => $finalDosage,
                        'route' => $normalizedRoute,
                        'stock' => rand(5, 20)
                    ]);
                    $importedCount++;
                } else {
                    // Update existing medicine if route or dosage is missing
                    $updated = false;
                    if (empty($medicine->route) && !empty($normalizedRoute)) {
                        $medicine->route = $normalizedRoute;
                        $updated = true;
                    }
                    if (empty($medicine->dosage) && !empty($finalDosage)) {
                        $medicine->dosage = $finalDosage;
                        $updated = true;
                    }
                    if ($updated) {
                        $medicine->save();
                    }
                }
            }

            $this->command->info("Imported {$importedCount} medicines from Excel.");
            if ($skippedCount > 0) {
                $this->command->warn("Skipped {$skippedCount} empty rows.");
            }

        } catch (\Exception $e) {
            $this->command->error("Error reading Excel file: " . $e->getMessage());
        }
    }

    /**
     * Normalize route from Excel format to standard format
     *
     * @param string|null $route
     * @return string
     */
    private function normalizeRoute(?string $route): string
    {
        if (empty($route)) {
            return 'PO'; // Default to oral
        }

        $route = strtolower(trim($route));

        // Map Excel route values to standard abbreviations
        $routeMap = [
            'give orally' => 'PO',
            'apply topically' => 'TOP',
            'apply' => 'TOP',
            'insert into anus' => 'PR',
        ];

        return $routeMap[$route] ?? 'PO'; // Default to PO if not found
    }
}

















