<?php

namespace Database\Seeders;

use App\Models\Symptom;
use Illuminate\Database\Seeder;

class SymptomsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $symptoms = [
            // General Symptoms
            'Fever',
            'Coughing',
            'Sneezing',
            'Lethargy',
            'Loss of Appetite',
            'Weight Loss',
            'Excessive Thirst',
            'Frequent Urination',
            'Difficulty Breathing',
            'Limping',
            'Swelling',
            'Discharge from Eyes',
            'Discharge from Nose',
            'Ear Scratching',
            'Skin Irritation',
            'Hair Loss',
            'Itching',
            'Pain',
            'Tremors',
            'Seizures',
            'Confusion',
            'Aggression',
            'Excessive Drooling',
            'Bad Breath',
            'Difficulty Swallowing',
            'Constipation',
            'Blood in Urine',
            'Blood in Stool',
            'Pale Gums',
            'Jaundice',
            'Rapid Heart Rate',
            'Irregular Heartbeat',
            'Chest Pain',
            'Back Pain',
            'Abdominal Pain',
            'Nasal Discharge',
            'Ocular Discharge',
            'Dyspnea',
            'Ataxia',
            'Paralysis',
            'Behavioral Changes',
            'Anxiety',
            'Depression',
            'Anorexia',
            'Polyphagia',
            'Polydipsia',
            'Polyuria',
            'Dysuria',
            'Hematuria',
            
            // Vomiting Types (Specific)
            'Vomiting - Clear/Watery',
            'Vomiting - White Foam',
            'Vomiting - Yellow/Green',
            'Vomiting - Brown',
            'Vomiting - Chunky/Undigested Food',
            'Vomiting - Slimy/Mucus',
            'Vomiting - Bloody (Red)',
            'Vomiting - Bloody (Black)',
            'Vomiting - Worms',
            
            // Diarrhea Types (Specific)
            'Diarrhea - Watery/Osmotic',
            'Diarrhea - Bloody (Red)',
            'Diarrhea - Bloody (Black/Melena)',
            'Diarrhea - Mucus-Filled',
            'Diarrhea - Fatty/Pale (Steatorrhea)',
            'Diarrhea - Frequent/Urgent',
        ];

        foreach ($symptoms as $symptomName) {
            Symptom::firstOrCreate(['name' => $symptomName]);
        }

        $this->command->info('Seeded ' . count($symptoms) . ' symptoms including vomiting and diarrhea types.');
    }
}

