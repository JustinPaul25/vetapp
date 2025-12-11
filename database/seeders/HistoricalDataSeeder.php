<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\Disease;
use App\Models\Medicine;
use App\Models\Patient;
use App\Models\PetType;
use App\Models\Prescription;
use App\Models\PrescriptionDiagnosis;
use App\Models\PrescriptionMedicine;
use App\Models\Symptom;
use App\Models\UniqueLink;
use App\Models\UniqueLinkType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class HistoricalDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Starting historical data seeding...');

        // Get existing data
        $petTypes = PetType::all();
        $appointmentTypes = AppointmentType::all();
        $diseases = Disease::all();
        $medicines = Medicine::all();
        $users = User::whereHas('roles', function ($query) {
            $query->whereIn('name', ['client', 'staff']);
        })->get();

        // If no users exist, create some historical users first
        if ($users->isEmpty()) {
            $this->command->info('Creating historical users...');
            $users = $this->createHistoricalUsers();
        }

        // Seed symptoms if they don't exist
        $symptoms = $this->seedSymptoms();

        // Seed unique link types if they don't exist
        $uniqueLinkTypes = $this->seedUniqueLinkTypes();

        // Create historical patients
        $this->command->info('Creating historical patients...');
        $patients = $this->createHistoricalPatients($users, $petTypes);

        // Create historical appointments
        $this->command->info('Creating historical appointments...');
        $appointments = $this->createHistoricalAppointments($patients, $appointmentTypes, $users);

        // Create historical prescriptions
        $this->command->info('Creating historical prescriptions...');
        $prescriptions = $this->createHistoricalPrescriptions($appointments, $patients, $diseases);

        // Create prescription medicines
        $this->command->info('Creating prescription medicines...');
        $this->createPrescriptionMedicines($prescriptions, $medicines);

        // Create prescription diagnoses
        $this->command->info('Creating prescription diagnoses...');
        $this->createPrescriptionDiagnoses($prescriptions, $appointments, $diseases);

        // Create disease-symptom relationships
        $this->command->info('Creating disease-symptom relationships...');
        $this->createDiseaseSymptoms($diseases, $symptoms);

        // Create disease-medicine relationships
        $this->command->info('Creating disease-medicine relationships...');
        $this->createDiseaseMedicines($diseases, $medicines);

        // Create historical unique links
        $this->command->info('Creating historical unique links...');
        $this->createHistoricalUniqueLinks($users, $uniqueLinkTypes);

        $this->command->info('Historical data seeding completed!');
    }

    /**
     * Create historical users with past dates
     */
    private function createHistoricalUsers(): \Illuminate\Database\Eloquent\Collection
    {
        $users = collect();
        $startDate = Carbon::now()->subYears(2);

        // Create additional client users
        $clientData = [
            ['name' => 'Sarah Johnson', 'email' => 'sarah.johnson@example.com', 'first_name' => 'Sarah', 'last_name' => 'Johnson', 'mobile_number' => '555-0101'],
            ['name' => 'Michael Chen', 'email' => 'michael.chen@example.com', 'first_name' => 'Michael', 'last_name' => 'Chen', 'mobile_number' => '555-0102'],
            ['name' => 'Emily Rodriguez', 'email' => 'emily.rodriguez@example.com', 'first_name' => 'Emily', 'last_name' => 'Rodriguez', 'mobile_number' => '555-0103'],
            ['name' => 'David Williams', 'email' => 'david.williams@example.com', 'first_name' => 'David', 'last_name' => 'Williams', 'mobile_number' => '555-0104'],
            ['name' => 'Lisa Anderson', 'email' => 'lisa.anderson@example.com', 'first_name' => 'Lisa', 'last_name' => 'Anderson', 'mobile_number' => '555-0105'],
            ['name' => 'Robert Taylor', 'email' => 'robert.taylor@example.com', 'first_name' => 'Robert', 'last_name' => 'Taylor', 'mobile_number' => '555-0106'],
            ['name' => 'Jennifer Martinez', 'email' => 'jennifer.martinez@example.com', 'first_name' => 'Jennifer', 'last_name' => 'Martinez', 'mobile_number' => '555-0107'],
            ['name' => 'James Brown', 'email' => 'james.brown@example.com', 'first_name' => 'James', 'last_name' => 'Brown', 'mobile_number' => '555-0108'],
        ];

        foreach ($clientData as $index => $data) {
            $createdAt = $startDate->copy()->addMonths($index)->addDays(rand(1, 28));
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'username' => strtolower(str_replace(' ', '.', $data['name'])),
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'mobile_number' => $data['mobile_number'],
                    'address' => $this->generateAddress(),
                    'password' => Hash::make('password'),
                    'active' => 1,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]
            );
            $user->assignRole('client');
            $users->push($user);
        }

        // Create staff users
        $staffData = [
            ['name' => 'Dr. Amanda White', 'email' => 'amanda.white@vetclinic.com', 'first_name' => 'Amanda', 'last_name' => 'White'],
            ['name' => 'Dr. Mark Thompson', 'email' => 'mark.thompson@vetclinic.com', 'first_name' => 'Mark', 'last_name' => 'Thompson'],
        ];

        foreach ($staffData as $index => $data) {
            $createdAt = $startDate->copy()->addMonths($index);
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'username' => strtolower(str_replace(' ', '.', $data['name'])),
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'mobile_number' => '555-' . str_pad(2000 + $index, 4, '0', STR_PAD_LEFT),
                    'address' => $this->generateAddress(),
                    'password' => Hash::make('password'),
                    'active' => 1,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]
            );
            $user->assignRole('staff');
            $users->push($user);
        }

        return new \Illuminate\Database\Eloquent\Collection($users->all());
    }

    /**
     * Seed symptoms
     */
    private function seedSymptoms(): \Illuminate\Database\Eloquent\Collection
    {
        $symptoms = [
            'Fever', 'Coughing', 'Sneezing', 'Vomiting', 'Diarrhea', 'Lethargy',
            'Loss of Appetite', 'Weight Loss', 'Excessive Thirst', 'Frequent Urination',
            'Difficulty Breathing', 'Limping', 'Swelling', 'Discharge from Eyes',
            'Discharge from Nose', 'Ear Scratching', 'Skin Irritation', 'Hair Loss',
            'Itching', 'Pain', 'Tremors', 'Seizures', 'Confusion', 'Aggression',
            'Excessive Drooling', 'Bad Breath', 'Difficulty Swallowing', 'Constipation',
            'Blood in Urine', 'Blood in Stool', 'Pale Gums', 'Jaundice',
            'Rapid Heart Rate', 'Irregular Heartbeat', 'Chest Pain', 'Back Pain',
        ];

        $symptomModels = collect();
        foreach ($symptoms as $symptomName) {
            $symptom = Symptom::firstOrCreate(['name' => $symptomName]);
            $symptomModels->push($symptom);
        }

        return new \Illuminate\Database\Eloquent\Collection($symptomModels->all());
    }

    /**
     * Seed unique link types
     */
    private function seedUniqueLinkTypes(): \Illuminate\Database\Eloquent\Collection
    {
        $types = ['appointment', 'prescription', 'report', 'payment'];

        $linkTypes = collect();
        foreach ($types as $type) {
            $linkType = UniqueLinkType::firstOrCreate(['type' => $type]);
            $linkTypes->push($linkType);
        }

        return new \Illuminate\Database\Eloquent\Collection($linkTypes->all());
    }

    /**
     * Create historical patients
     */
    private function createHistoricalPatients($users, $petTypes): \Illuminate\Database\Eloquent\Collection
    {
        $patients = collect();
        $clientUsers = $users->filter(function ($user) {
            return $user->hasRole('client');
        });

        $petNames = [
            'Max', 'Bella', 'Charlie', 'Lucy', 'Cooper', 'Daisy', 'Buddy', 'Luna',
            'Rocky', 'Sadie', 'Milo', 'Molly', 'Bear', 'Stella', 'Duke', 'Penny',
            'Tucker', 'Zoey', 'Jack', 'Lily', 'Oliver', 'Chloe', 'Bentley', 'Sophie',
            'Zeus', 'Ruby', 'Oscar', 'Maggie', 'Jake', 'Rosie', 'Leo', 'Coco',
            'Rex', 'Ginger', 'Toby', 'Pepper', 'Gus', 'Princess', 'Murphy', 'Nala',
        ];

        $breeds = [
            'Golden Retriever', 'Labrador Retriever', 'German Shepherd', 'Bulldog',
            'Beagle', 'Poodle', 'Rottweiler', 'Yorkshire Terrier', 'Dachshund',
            'Siberian Husky', 'Shih Tzu', 'Boxer', 'French Bulldog', 'Great Dane',
            'Persian', 'Maine Coon', 'British Shorthair', 'Ragdoll', 'Bengal',
            'Siamese', 'American Shorthair', 'Scottish Fold', 'Sphynx', 'Abyssinian',
        ];

        $genders = ['Male', 'Female'];

        $startDate = Carbon::now()->subYears(2);
        $patientCount = 0;

        foreach ($clientUsers as $user) {
            // Each user has 1-3 pets
            $petCount = rand(1, 3);
            for ($i = 0; $i < $petCount; $i++) {
                $petName = $petNames[array_rand($petNames)];
                $breed = $breeds[array_rand($breeds)];
                $gender = $genders[array_rand($genders)];
                $birthDate = Carbon::now()->subYears(rand(1, 12))->subMonths(rand(0, 11))->subDays(rand(1, 28));
                $createdAt = $startDate->copy()->addMonths(rand(0, 18))->addDays(rand(1, 28));

                $patient = Patient::create([
                    'pet_type_id' => $petTypes->random()->id,
                    'user_id' => $user->id,
                    'pet_name' => $petName,
                    'pet_breed' => $breed,
                    'pet_gender' => $gender,
                    'pet_birth_date' => $birthDate,
                    'microchip_number' => rand(100000, 999999) . '-' . rand(100000, 999999),
                    'pet_allergies' => rand(0, 1) ? $this->generateAllergies() : null,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);

                $patients->push($patient);
                $patientCount++;
            }
        }

        $this->command->info("Created {$patientCount} historical patients.");
        return new \Illuminate\Database\Eloquent\Collection($patients->all());
    }

    /**
     * Create historical appointments
     */
    private function createHistoricalAppointments($patients, $appointmentTypes, $users): \Illuminate\Database\Eloquent\Collection
    {
        $appointments = collect();
        $startDate = Carbon::now()->subYears(1);
        $endDate = Carbon::now();

        // Generate appointments over the past year
        $appointmentCount = 0;
        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            // Create 2-5 appointments per week
            $appointmentsPerWeek = rand(2, 5);
            
            for ($i = 0; $i < $appointmentsPerWeek; $i++) {
                $patient = $patients->random();
                $appointmentType = $appointmentTypes->random();
                $user = $users->where('id', $patient->user_id)->first() ?? $users->random();

                $appointmentDate = $currentDate->copy()->addDays(rand(0, 6));
                $appointmentTime = sprintf('%02d:00', rand(8, 17)); // 8 AM to 5 PM

                // Some appointments are in the past (completed/approved)
                $isApproved = $appointmentDate->isPast() ? (rand(0, 10) > 1) : (rand(0, 10) > 3);
                $isCompleted = $appointmentDate->isPast() && $isApproved ? (rand(0, 10) > 2) : false;

                $appointment = Appointment::create([
                    'appointment_type_id' => $appointmentType->id,
                    'patient_id' => $patient->id,
                    'user_id' => $user->id,
                    'appointment_date' => $appointmentDate,
                    'appointment_time' => $appointmentTime,
                    'symptoms' => rand(0, 1) ? $this->generateSymptoms() : null,
                    'is_approved' => $isApproved,
                    'is_completed' => $isCompleted,
                    'remarks' => rand(0, 1) ? $this->generateRemarks() : null,
                    'created_at' => $appointmentDate->copy()->subDays(rand(1, 7)),
                    'updated_at' => $isCompleted ? $appointmentDate->copy()->addHours(rand(1, 3)) : $appointmentDate->copy()->subDays(rand(1, 7)),
                ]);

                $appointments->push($appointment);
                $appointmentCount++;
            }

            $currentDate->addWeek();
        }

        $this->command->info("Created {$appointmentCount} historical appointments.");
        return new \Illuminate\Database\Eloquent\Collection($appointments->all());
    }

    /**
     * Create historical prescriptions
     */
    private function createHistoricalPrescriptions($appointments, $patients, $diseases): \Illuminate\Database\Eloquent\Collection
    {
        $prescriptions = collect();
        $completedAppointments = $appointments->filter(function ($appointment) {
            return $appointment->is_completed === true;
        });

        foreach ($completedAppointments as $appointment) {
            // Not all completed appointments have prescriptions
            if (rand(0, 10) > 2) {
                $disease = $diseases->random();

                $prescription = Prescription::create([
                    'patient_id' => $appointment->patient_id,
                    'appointment_id' => $appointment->id,
                    'disease_id' => $disease->id,
                    'pet_weight' => rand(5, 50) . ' kg',
                    'symptoms' => $appointment->symptoms ?? $this->generateSymptoms(),
                    'notes' => $this->generatePrescriptionNotes(),
                    'created_at' => $appointment->updated_at->copy()->addMinutes(rand(30, 120)),
                    'updated_at' => $appointment->updated_at->copy()->addMinutes(rand(30, 120)),
                ]);

                $prescriptions->push($prescription);
            }
        }

        $this->command->info("Created {$prescriptions->count()} historical prescriptions.");
        return new \Illuminate\Database\Eloquent\Collection($prescriptions->all());
    }

    /**
     * Create prescription medicines
     */
    private function createPrescriptionMedicines($prescriptions, $medicines): void
    {
        $count = 0;
        foreach ($prescriptions as $prescription) {
            // Each prescription has 1-3 medicines
            $medicineCount = rand(1, 3);
            $selectedMedicines = $medicines->random($medicineCount);

            foreach ($selectedMedicines as $medicine) {
                PrescriptionMedicine::create([
                    'prescription_id' => $prescription->id,
                    'medicine_id' => $medicine->id,
                    'dosage' => $medicine->dosage,
                    'instructions' => $this->generateInstructions(),
                    'quantity' => rand(1, 3) . ' ' . $this->getQuantityUnit(),
                    'created_at' => $prescription->created_at,
                    'updated_at' => $prescription->updated_at,
                ]);
                $count++;
            }
        }

        $this->command->info("Created {$count} prescription medicine records.");
    }

    /**
     * Create prescription diagnoses
     */
    private function createPrescriptionDiagnoses($prescriptions, $appointments, $diseases): void
    {
        $count = 0;
        foreach ($prescriptions as $prescription) {
            $appointment = $appointments->firstWhere('id', $prescription->appointment_id);
            $disease = $diseases->firstWhere('id', $prescription->disease_id);

            if ($appointment && $disease) {
                PrescriptionDiagnosis::create([
                    'appointment_id' => $appointment->id,
                    'prescription_id' => $prescription->id,
                    'disease_id' => $disease->id,
                    'created_at' => $prescription->created_at,
                    'updated_at' => $prescription->updated_at,
                ]);
                $count++;
            }
        }

        $this->command->info("Created {$count} prescription diagnosis records.");
    }

    /**
     * Create disease-symptom relationships
     */
    private function createDiseaseSymptoms($diseases, $symptoms): void
    {
        $count = 0;
        foreach ($diseases->take(50) as $disease) { // Link first 50 diseases
            $symptomCount = rand(2, 5);
            $selectedSymptoms = $symptoms->random($symptomCount);

            foreach ($selectedSymptoms as $symptom) {
                DB::table('disease_symptoms')->insertOrIgnore([
                    'disease_id' => $disease->id,
                    'symptom_id' => $symptom->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $count++;
            }
        }

        $this->command->info("Created {$count} disease-symptom relationships.");
    }

    /**
     * Create disease-medicine relationships
     */
    private function createDiseaseMedicines($diseases, $medicines): void
    {
        $count = 0;
        foreach ($diseases->take(50) as $disease) { // Link first 50 diseases
            $medicineCount = rand(1, 3);
            $selectedMedicines = $medicines->random($medicineCount);

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

        $this->command->info("Created {$count} disease-medicine relationships.");
    }

    /**
     * Create historical unique links
     */
    private function createHistoricalUniqueLinks($users, $uniqueLinkTypes): void
    {
        $count = 0;
        $startDate = Carbon::now()->subMonths(6);

        foreach ($users as $user) {
            // Each user has 0-5 unique links
            $linkCount = rand(0, 5);

            for ($i = 0; $i < $linkCount; $i++) {
                $linkType = $uniqueLinkTypes->random();
                $createdAt = $startDate->copy()->addDays(rand(0, 180));
                $expiryDate = $createdAt->copy()->addDays(rand(7, 30));
                $dateProcessed = rand(0, 1) && $expiryDate->isPast() ? $createdAt->copy()->addDays(rand(1, 7)) : null;

                UniqueLink::create([
                    'code' => bin2hex(random_bytes(32)),
                    'date_expiry' => $expiryDate,
                    'date_processed' => $dateProcessed,
                    'link_type_id' => $linkType->id,
                    'user_id' => $user->id,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);
                $count++;
            }
        }

        $this->command->info("Created {$count} historical unique links.");
    }

    /**
     * Generate random address
     */
    private function generateAddress(): string
    {
        $streets = ['Main St', 'Oak Ave', 'Park Blvd', 'Elm St', 'Cedar Ln', 'Maple Dr', 'Pine Rd'];
        $numbers = rand(100, 9999);
        $street = $streets[array_rand($streets)];
        return $numbers . ' ' . $street . ', City, State ' . rand(10000, 99999);
    }

    /**
     * Generate random allergies
     */
    private function generateAllergies(): string
    {
        $allergies = [
            'Chicken', 'Beef', 'Dairy', 'Wheat', 'Corn', 'Soy', 'Eggs',
            'Pollen', 'Dust', 'Grass', 'Flea bites', 'Certain medications'
        ];
        $count = rand(1, 3);
        $selected = array_rand(array_flip($allergies), $count);
        $selected = is_array($selected) ? $selected : [$selected];
        return implode(', ', $selected);
    }

    /**
     * Generate random symptoms
     */
    private function generateSymptoms(): string
    {
        $symptoms = [
            'Mild coughing and sneezing',
            'Loss of appetite and lethargy',
            'Vomiting and diarrhea',
            'Excessive scratching',
            'Difficulty breathing',
            'Limping on front leg',
            'Discharge from eyes and nose',
            'Frequent urination',
            'Weight loss',
            'Excessive thirst',
        ];
        return $symptoms[array_rand($symptoms)];
    }

    /**
     * Generate random remarks
     */
    private function generateRemarks(): string
    {
        $remarks = [
            'Regular check-up',
            'Follow-up appointment',
            'Vaccination due',
            'Emergency visit',
            'Routine examination',
            'Post-surgery check',
            'Behavioral consultation',
        ];
        return $remarks[array_rand($remarks)];
    }

    /**
     * Generate prescription notes
     */
    private function generatePrescriptionNotes(): string
    {
        $notes = [
            'Patient responded well to treatment. Continue medication as prescribed.',
            'Monitor for any adverse reactions. Return if symptoms worsen.',
            'Complete full course of medication. Follow-up in 2 weeks.',
            'Patient is recovering well. Maintain current treatment plan.',
            'Watch for improvement in next 3-5 days. Contact if no improvement.',
        ];
        return $notes[array_rand($notes)];
    }

    /**
     * Generate medication instructions
     */
    private function generateInstructions(): string
    {
        $instructions = [
            'Give with food twice daily',
            'Administer once daily in the morning',
            'Give every 8 hours with meals',
            'Apply to affected area twice daily',
            'Give 30 minutes before meals',
            'Administer as needed for pain',
        ];
        return $instructions[array_rand($instructions)];
    }

    /**
     * Get quantity unit
     */
    private function getQuantityUnit(): string
    {
        $units = ['tablets', 'capsules', 'ml', 'bottles', 'tubes'];
        return $units[array_rand($units)];
    }
}

