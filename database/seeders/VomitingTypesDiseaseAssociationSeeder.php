<?php

namespace Database\Seeders;

use App\Models\Disease;
use App\Models\Symptom;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VomitingTypesDiseaseAssociationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Associates specific vomiting and diarrhea types with diseases that commonly cause them.
     */
    public function run(): void
    {
        // Get vomiting type symptoms
        $vomitingTypes = [
            'Vomiting - Clear/Watery',
            'Vomiting - White Foam',
            'Vomiting - Yellow/Green',
            'Vomiting - Brown',
            'Vomiting - Chunky/Undigested Food',
            'Vomiting - Slimy/Mucus',
            'Vomiting - Bloody (Red)',
            'Vomiting - Bloody (Black)',
            'Vomiting - Worms',
        ];

        // Diseases commonly associated with vomiting
        $gastrointestinalDiseases = [
            'Gastritis',
            'Pancreatitis',
            'Enteritis',
            'Colitis',
            'Inflammatory Bowel Disease',
            'Gastric Dilatation-Volvulus',
            'Hepatitis',
            'Liver Disease',
            'Kidney Disease',
        ];

        // Parasitic diseases (for worm vomiting)
        $parasiticDiseases = [
            'Hookworm Infection',
            'Roundworm Infection',
            'Tapeworm Infection',
            'Giardiasis',
            'Coccidiosis',
            'Toxoplasmosis',
        ];

        // Infectious diseases that can cause vomiting
        $infectiousDiseases = [
            'Parvoviral Infection',
            'Canine Distemper',
            'Canine Coronavirus',
            'Leptospirosis',
            'Bacterial Infection',
            'Viral Infection',
        ];

        // Emergency/Serious conditions (for bloody vomiting)
        $seriousConditions = [
            'Gastric Dilatation-Volvulus',
            'Foreign Body Ingestion',
            'Toxicosis',
            'Poisoning',
            'Sepsis',
            'Shock',
        ];

        $count = 0;

        // Associate general vomiting types with gastrointestinal diseases
        foreach ($gastrointestinalDiseases as $diseaseName) {
            $disease = Disease::where('name', $diseaseName)->first();
            if (!$disease) {
                continue;
            }

            // Most GI diseases can cause various vomiting types
            $applicableTypes = [
                'Vomiting - Clear/Watery',
                'Vomiting - White Foam',
                'Vomiting - Yellow/Green',
                'Vomiting - Brown',
                'Vomiting - Chunky/Undigested Food',
                'Vomiting - Slimy/Mucus',
            ];

            foreach ($applicableTypes as $vomitingType) {
                $symptom = Symptom::where('name', $vomitingType)->first();
                if ($symptom) {
                    DB::table('disease_symptoms')->updateOrInsert(
                        [
                            'disease_id' => $disease->id,
                            'symptom_id' => $symptom->id,
                        ],
                        [
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                    $count++;
                }
            }
        }

        // Associate bloody vomiting with serious conditions
        foreach ($seriousConditions as $diseaseName) {
            $disease = Disease::where('name', $diseaseName)->first();
            if (!$disease) {
                continue;
            }

            $bloodyTypes = [
                'Vomiting - Bloody (Red)',
                'Vomiting - Bloody (Black)',
            ];

            foreach ($bloodyTypes as $vomitingType) {
                $symptom = Symptom::where('name', $vomitingType)->first();
                if ($symptom) {
                    DB::table('disease_symptoms')->updateOrInsert(
                        [
                            'disease_id' => $disease->id,
                            'symptom_id' => $symptom->id,
                        ],
                        [
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                    $count++;
                }
            }
        }

        // Associate worm vomiting with parasitic diseases
        foreach ($parasiticDiseases as $diseaseName) {
            $disease = Disease::where('name', $diseaseName)->first();
            if (!$disease) {
                continue;
            }

            $symptom = Symptom::where('name', 'Vomiting - Worms')->first();
            if ($symptom) {
                DB::table('disease_symptoms')->updateOrInsert(
                    [
                        'disease_id' => $disease->id,
                        'symptom_id' => $symptom->id,
                    ],
                    [
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
                $count++;
            }
        }

        // Associate vomiting types with infectious diseases
        foreach ($infectiousDiseases as $diseaseName) {
            $disease = Disease::where('name', $diseaseName)->first();
            if (!$disease) {
                continue;
            }

            $applicableTypes = [
                'Vomiting - Clear/Watery',
                'Vomiting - White Foam',
                'Vomiting - Yellow/Green',
                'Vomiting - Brown',
            ];

            foreach ($applicableTypes as $vomitingType) {
                $symptom = Symptom::where('name', $vomitingType)->first();
                if ($symptom) {
                    DB::table('disease_symptoms')->updateOrInsert(
                        [
                            'disease_id' => $disease->id,
                            'symptom_id' => $symptom->id,
                        ],
                        [
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                    $count++;
                }
            }
        }

        // ========== DIARRHEA TYPES ASSOCIATIONS ==========
        
        // Diarrhea types
        $diarrheaTypes = [
            'Diarrhea - Watery/Osmotic',
            'Diarrhea - Bloody (Red)',
            'Diarrhea - Bloody (Black/Melena)',
            'Diarrhea - Mucus-Filled',
            'Diarrhea - Fatty/Pale (Steatorrhea)',
            'Diarrhea - Frequent/Urgent',
        ];

        // Diseases commonly associated with watery/osmotic diarrhea
        $wateryDiarrheaDiseases = [
            'Enteritis',
            'Colitis',
            'Inflammatory Bowel Disease',
            'Parvoviral Infection',
            'Canine Coronavirus',
            'Giardiasis',
            'Coccidiosis',
            'Bacterial Infection',
            'Viral Infection',
            'Food Allergy',
        ];

        // Associate watery/osmotic diarrhea
        foreach ($wateryDiarrheaDiseases as $diseaseName) {
            $disease = Disease::where('name', $diseaseName)->first();
            if (!$disease) {
                continue;
            }

            $symptom = Symptom::where('name', 'Diarrhea - Watery/Osmotic')->first();
            if ($symptom) {
                DB::table('disease_symptoms')->updateOrInsert(
                    [
                        'disease_id' => $disease->id,
                        'symptom_id' => $symptom->id,
                    ],
                    [
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
                $count++;
            }
        }

        // Diseases associated with bloody diarrhea
        $bloodyDiarrheaDiseases = [
            'Colitis',
            'Inflammatory Bowel Disease',
            'Parvoviral Infection',
            'Foreign Body Ingestion',
            'Toxicosis',
            'Poisoning',
            'Hookworm Infection',
            'Bacterial Infection',
        ];

        // Associate bloody diarrhea (red)
        foreach ($bloodyDiarrheaDiseases as $diseaseName) {
            $disease = Disease::where('name', $diseaseName)->first();
            if (!$disease) {
                continue;
            }

            $symptom = Symptom::where('name', 'Diarrhea - Bloody (Red)')->first();
            if ($symptom) {
                DB::table('disease_symptoms')->updateOrInsert(
                    [
                        'disease_id' => $disease->id,
                        'symptom_id' => $symptom->id,
                    ],
                    [
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
                $count++;
            }
        }

        // Associate bloody diarrhea (black/melena) - upper GI issues
        $melenaDiseases = [
            'Gastritis',
            'Gastric Dilatation-Volvulus',
            'Foreign Body Ingestion',
            'Toxicosis',
            'Poisoning',
        ];

        foreach ($melenaDiseases as $diseaseName) {
            $disease = Disease::where('name', $diseaseName)->first();
            if (!$disease) {
                continue;
            }

            $symptom = Symptom::where('name', 'Diarrhea - Bloody (Black/Melena)')->first();
            if ($symptom) {
                DB::table('disease_symptoms')->updateOrInsert(
                    [
                        'disease_id' => $disease->id,
                        'symptom_id' => $symptom->id,
                    ],
                    [
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
                $count++;
            }
        }

        // Diseases associated with mucus-filled diarrhea (colitis)
        $mucusDiarrheaDiseases = [
            'Colitis',
            'Inflammatory Bowel Disease',
            'Parasitic Infestation',
            'Bacterial Infection',
        ];

        foreach ($mucusDiarrheaDiseases as $diseaseName) {
            $disease = Disease::where('name', $diseaseName)->first();
            if (!$disease) {
                continue;
            }

            $symptom = Symptom::where('name', 'Diarrhea - Mucus-Filled')->first();
            if ($symptom) {
                DB::table('disease_symptoms')->updateOrInsert(
                    [
                        'disease_id' => $disease->id,
                        'symptom_id' => $symptom->id,
                    ],
                    [
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
                $count++;
            }
        }

        // Diseases associated with fatty/pale diarrhea (steatorrhea) - pancreatic issues
        $steatorrheaDiseases = [
            'Pancreatitis',
            'Liver Disease',
            'Hepatitis',
        ];

        foreach ($steatorrheaDiseases as $diseaseName) {
            $disease = Disease::where('name', $diseaseName)->first();
            if (!$disease) {
                continue;
            }

            $symptom = Symptom::where('name', 'Diarrhea - Fatty/Pale (Steatorrhea)')->first();
            if ($symptom) {
                DB::table('disease_symptoms')->updateOrInsert(
                    [
                        'disease_id' => $disease->id,
                        'symptom_id' => $symptom->id,
                    ],
                    [
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
                $count++;
            }
        }

        // Diseases associated with frequent/urgent diarrhea (large bowel issues)
        $frequentUrgentDiseases = [
            'Colitis',
            'Inflammatory Bowel Disease',
            'Parasitic Infestation',
            'Food Allergy',
        ];

        foreach ($frequentUrgentDiseases as $diseaseName) {
            $disease = Disease::where('name', $diseaseName)->first();
            if (!$disease) {
                continue;
            }

            $symptom = Symptom::where('name', 'Diarrhea - Frequent/Urgent')->first();
            if ($symptom) {
                DB::table('disease_symptoms')->updateOrInsert(
                    [
                        'disease_id' => $disease->id,
                        'symptom_id' => $symptom->id,
                    ],
                    [
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
                $count++;
            }
        }

        $this->command->info("Created {$count} disease-vomiting and diarrhea type associations.");
    }
}

