<?php

namespace Database\Seeders;

use App\Models\Medicine;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MedicineSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $data = [
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

        foreach ($data as $item) {
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
    }
}

















