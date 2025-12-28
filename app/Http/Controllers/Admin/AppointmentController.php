<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\Disease;
use App\Models\Medicine;
use App\Models\Patient;
use App\Models\PatientWeightHistory;
use App\Models\Prescription;
use App\Models\PrescriptionDiagnosis;
use App\Models\PrescriptionMedicine;
use App\Models\Setting;
use App\Models\Symptom;
use App\Models\User;
use App\Notifications\ClientEmailNotification;
use App\Notifications\PrescriptionEmailNotification;
use App\Services\AblyService;
use App\Services\AppointmentLimitService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;

class AppointmentController extends Controller
{
    /**
     * Display a listing of appointments.
     */
    public function index(Request $request)
    {
        $query = Appointment::with([
            'appointment_type',
            'patient.petType',
            'patient.user',
            'patients.petType',
            'patients.user',
            'prescription.diagnoses.disease'
        ]);

        // Status filtering
        if ($request->has('status') && !empty($request->status) && $request->status !== 'all') {
            $status = strtolower($request->status);
            switch ($status) {
                case 'pending':
                    $query->where('is_approved', false)
                          ->where('is_completed', false)
                          ->where(function ($q) {
                              $q->whereNull('is_canceled')->orWhere('is_canceled', false);
                          });
                    break;
                case 'approved':
                    $query->where('is_approved', true)
                          ->where('is_completed', false)
                          ->where(function ($q) {
                              $q->whereNull('is_canceled')->orWhere('is_canceled', false);
                          });
                    break;
                case 'completed':
                    $query->where('is_completed', true);
                    break;
                case 'canceled':
                    $query->where('is_canceled', true);
                    break;
            }
        }

        // Search functionality
        if ($request->has('search') && !empty($request->search)) {
            $keyword = $request->search;
            $query->where(function ($q) use ($keyword) {
                $q->whereHas('patients.petType', function ($q) use ($keyword) {
                    $q->where('name', 'LIKE', "%{$keyword}%");
                })
                ->orWhereHas('patients.user', function ($q) use ($keyword) {
                    $q->where(DB::raw("CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, ''))"), 'LIKE', "%{$keyword}%")
                        ->orWhere('name', 'LIKE', "%{$keyword}%");
                })
                ->orWhereHas('prescription.diagnoses.disease', function ($q) use ($keyword) {
                    $q->where('name', 'LIKE', "%{$keyword}%");
                });
            });
        }

        // Sort functionality
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        
        $allowedSortColumns = ['appointment_date', 'appointment_time', 'created_at'];
        if (!in_array($sortBy, $allowedSortColumns)) {
            $sortBy = 'created_at';
        }
        
        $sortDirection = strtolower($sortDirection) === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortDirection);

        $appointments = $query->paginate(15);

        // Transform the data for Inertia
        $appointments->getCollection()->transform(function ($appointment) {
            $status = 'Pending';
            if ($appointment->is_canceled) {
                $status = 'Canceled';
            } elseif ($appointment->is_completed) {
                $status = 'Completed';
            } elseif ($appointment->is_approved) {
                $status = 'Approved';
            }

            // Get all patients for this appointment
            $patients = $appointment->patients;
            $petTypes = $patients->map(function ($patient) {
                return $patient->petType->name ?? 'N/A';
            })->unique()->join(', ');
            $petBreeds = $patients->pluck('pet_breed')->filter()->unique()->join(', ');
            
            // Get owner info from first patient (all should belong to same user)
            $firstPatient = $patients->first() ?? $appointment->patient;
            $ownerName = $firstPatient && $firstPatient->user ? 
                trim(($firstPatient->user->first_name ?? '') . ' ' . ($firstPatient->user->last_name ?? '')) ?: $firstPatient->user->name : 'N/A';
            $ownerEmail = $firstPatient && $firstPatient->user ? $firstPatient->user->email ?? 'N/A' : 'N/A';
            $ownerMobile = $firstPatient && $firstPatient->user ? $firstPatient->user->mobile_number ?? 'N/A' : 'N/A';
            
            return [
                'id' => $appointment->id,
                'appointment_type' => $appointment->appointment_type->name ?? 'N/A',
                'appointment_date' => $appointment->appointment_date ? $appointment->appointment_date->format('Y-m-d') : null,
                'appointment_time' => $appointment->appointment_time,
                'status' => $status,
                'pet_type' => $petTypes ?: ($appointment->patient->petType->name ?? 'N/A'),
                'pet_breed' => $petBreeds ?: ($appointment->patient->pet_breed ?? 'N/A'),
                'owner_name' => $ownerName,
                'owner_email' => $ownerEmail,
                'owner_mobile' => $ownerMobile,
                'disease' => $appointment->prescription && $appointment->prescription->diagnoses->isNotEmpty() 
                    ? $appointment->prescription->diagnoses->first()->disease->name ?? 'N/A'
                    : 'N/A',
                'created_at' => $appointment->created_at->toISOString(),
                'updated_at' => $appointment->updated_at->toISOString(),
            ];
        });

        return Inertia::render('Admin/Appointments/Index', [
            'appointments' => $appointments,
            'filters' => [
                'search' => $request->search,
                'status' => $request->status ?? 'all',
                'sort_by' => $sortBy,
                'sort_direction' => $sortDirection,
            ],
        ]);
    }

    /**
     * Show the form for creating a new appointment.
     * 
     * @deprecated Only clients can create appointments. This method is kept for backward compatibility but returns 403.
     */
    public function create()
    {
        abort(403, 'Only clients can create appointments.');
        $patients = Patient::with('petType', 'user')->get()->map(function ($patient) {
            return [
                'id' => $patient->id,
                'pet_name' => $patient->pet_name,
                'pet_breed' => $patient->pet_breed,
                'pet_type' => $patient->petType->name ?? 'N/A',
                'owner_name' => $patient->user ? 
                    trim(($patient->user->first_name ?? '') . ' ' . ($patient->user->last_name ?? '')) ?: $patient->user->name : 'N/A',
            ];
        });

        $appointment_types = AppointmentType::all()->map(function ($type) {
            return [
                'id' => $type->id,
                'name' => $type->name,
            ];
        });

        return Inertia::render('Admin/Appointments/Create', [
            'patients' => $patients,
            'appointment_types' => $appointment_types,
        ]);
    }

    /**
     * Store a newly created appointment.
     * 
     * @deprecated Only clients can create appointments. This method is kept for backward compatibility but returns 403.
     */
    public function store(Request $request)
    {
        abort(403, 'Only clients can create appointments.');
        $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'appointment_type' => 'required|exists:appointment_types,id',
            'appointment_date' => 'required|date|after_or_equal:today',
            'appointment_time' => 'required|string',
        ]);

        // Validate daily appointment limits
        $limitService = app(AppointmentLimitService::class);
        $limitCheck = $limitService->checkDailyLimit($request->appointment_type, $request->appointment_date);
        
        if (!$limitCheck['available']) {
            return back()->withErrors([
                'appointment_date' => sprintf(
                    'Daily limit reached for %s appointments. Current: %d/%d',
                    $limitCheck['appointment_type'],
                    $limitCheck['current_count'],
                    $limitCheck['limit']
                ),
            ]);
        }

        // Get the patient to find the owner's user_id
        $patient = Patient::findOrFail($request->patient_id);
        
        $appointment = Appointment::create([
            'patient_id' => $request->patient_id,
            'appointment_type_id' => $request->appointment_type,
            'appointment_date' => $request->appointment_date,
            'appointment_time' => $request->appointment_time,
            'symptoms' => '',
            'is_approved' => true, // Admin-created appointments are auto-approved
            'user_id' => $patient->user_id, // Set the user_id from the patient's owner
        ]);

        // Sync many-to-many relationship for patients
        $appointment->patients()->sync([$request->patient_id]);
        
        // Sync many-to-many relationship for appointment types (for consistency)
        $appointment->appointment_types()->sync([$request->appointment_type]);
        
        // Reload appointment with relationships
        $appointment->load('appointment_type', 'appointment_types', 'patient.petType', 'patient.user', 'patients.petType', 'patients.user');

        // Notify Staff users via Ably
        $staffUsers = User::select('users.*')
            ->leftJoin('model_has_roles as mhr', 'mhr.model_id', 'users.id')
            ->leftJoin('roles', 'roles.id', 'mhr.role_id')
            ->where('roles.name', 'staff')
            ->distinct()
            ->get();

        $pet = $appointment->patient;
        $patient_owner_full_name = trim(($pet->user->first_name ?? '') . ' ' . ($pet->user->last_name ?? '')) ?: ($pet->user->name ?? 'N/A');
        $appointmentTypeName = $appointment->appointment_type->name ?? 'N/A';

        $link = config('app.url') . '/admin/appointments/' . $appointment->id;
        $subject = sprintf("New appointment created for %s", $pet->pet_name ?? 'patient');
        $message = $appointmentTypeName . ' appointment scheduled for ' . $request->appointment_date . ' at ' . $request->appointment_time;

        // Send real-time notifications to staff via Ably
        $ablyService = app(AblyService::class);
        foreach ($staffUsers as $user) {
            $ablyService->publishToUser($user->id, 'appointment.created', [
                'appointment_id' => $appointment->id,
                'subject' => $subject,
                'message' => $message,
                'link' => $link,
                'patient_name' => $pet->pet_name ?? 'N/A',
                'owner_name' => $patient_owner_full_name,
            ]);
        }

        // Also publish to staff channel for all staff
        $ablyService->publishToStaff('appointment.created', [
            'appointment_id' => $appointment->id,
            'subject' => $subject,
            'message' => $message,
            'link' => $link,
            'patient_name' => $pet->pet_name ?? 'N/A',
            'owner_name' => $patient_owner_full_name,
        ]);

        return redirect()->route('admin.appointments.show', $appointment->id)
            ->with('success', 'New appointment has been created successfully.');
    }

    /**
     * Display the specified appointment.
     */
    public function show($id)
    {
        $appointment = Appointment::with([
            'patient.petType',
            'patient.user',
            'patients.petType',
            'patients.user',
            'appointment_type',
            'prescription.diagnoses.disease',
            'prescription.medicines.medicine'
        ])->findOrFail($id);

        $medicines = Medicine::all()->map(function ($medicine) {
            return [
                'id' => $medicine->id,
                'name' => $medicine->name,
                'dosage' => $medicine->dosage,
                'stock' => $medicine->stock,
            ];
        });

        return Inertia::render('Admin/Appointments/Show', [
            'appointment' => [
                'id' => $appointment->id,
                'appointment_type' => $appointment->appointment_type->name ?? 'N/A',
                'appointment_date' => $appointment->appointment_date->format('Y-m-d'),
                'appointment_time' => $appointment->appointment_time,
                'symptoms' => $appointment->symptoms,
                'is_approved' => $appointment->is_approved,
                'is_completed' => $appointment->is_completed,
                'remarks' => $appointment->remarks,
                'summary' => $appointment->summary,
                'created_at' => $appointment->created_at->toISOString(),
                'updated_at' => $appointment->updated_at->toISOString(),
            ],
            'patients' => $appointment->patients->map(function ($patient) {
                return [
                    'id' => $patient->id,
                    'pet_name' => $patient->pet_name,
                    'pet_breed' => $patient->pet_breed,
                    'pet_gender' => $patient->pet_gender,
                    'pet_birth_date' => $patient->pet_birth_date ? $patient->pet_birth_date->format('Y-m-d') : null,
                    'pet_allergies' => $patient->pet_allergies,
                    'pet_type' => $patient->petType->name ?? 'N/A',
                    'owner' => $patient->user ? [
                        'id' => $patient->user->id,
                        'name' => trim(($patient->user->first_name ?? '') . ' ' . ($patient->user->last_name ?? '')) ?: $patient->user->name,
                        'email' => $patient->user->email,
                        'mobile_number' => $patient->user->mobile_number ?? null,
                    ] : null,
                ];
            }),
            // Keep backward compatibility with single patient
            'patient' => $appointment->patient ? [
                'id' => $appointment->patient->id,
                'pet_name' => $appointment->patient->pet_name,
                'pet_breed' => $appointment->patient->pet_breed,
                'pet_gender' => $appointment->patient->pet_gender,
                'pet_birth_date' => $appointment->patient->pet_birth_date ? $appointment->patient->pet_birth_date->format('Y-m-d') : null,
                'pet_allergies' => $appointment->patient->pet_allergies,
                'pet_type' => $appointment->patient->petType->name ?? 'N/A',
                'owner' => $appointment->patient->user ? [
                    'id' => $appointment->patient->user->id,
                    'name' => trim(($appointment->patient->user->first_name ?? '') . ' ' . ($appointment->patient->user->last_name ?? '')) ?: $appointment->patient->user->name,
                    'email' => $appointment->patient->user->email,
                    'mobile_number' => $appointment->patient->user->mobile_number ?? null,
                ] : null,
            ] : null,
            'prescription' => $appointment->prescription ? [
                'id' => $appointment->prescription->id,
                'symptoms' => $appointment->prescription->symptoms,
                'notes' => $appointment->prescription->notes,
                'pet_weight' => $appointment->prescription->pet_weight,
                'diagnoses' => $appointment->prescription->diagnoses->map(function ($diagnosis) {
                    return [
                        'id' => $diagnosis->id,
                        'disease' => $diagnosis->disease->name ?? 'N/A',
                    ];
                }),
                'medicines' => $appointment->prescription->medicines->map(function ($prescriptionMedicine) {
                    return [
                        'id' => $prescriptionMedicine->id,
                        'medicine' => $prescriptionMedicine->medicine->name ?? 'N/A',
                        'dosage' => $prescriptionMedicine->dosage,
                        'instructions' => $prescriptionMedicine->instructions,
                        'quantity' => $prescriptionMedicine->quantity,
                    ];
                }),
            ] : null,
            'medicines' => $medicines,
        ]);
    }

    /**
     * Approve/reschedule an appointment.
     */
    public function approve(Request $request, $id)
    {
        $request->validate([
            'appointment_date' => 'required|date|after_or_equal:today',
            'appointment_time' => 'required|string',
            'pet_gender' => 'nullable|string',
            'pet_allergies' => 'nullable|string',
        ]);

        $appointment = Appointment::with('patient.user')->findOrFail($id);
        
        // If date is changing, validate daily limits for the new date (excluding current appointment)
        if ($appointment->appointment_date->format('Y-m-d') !== $request->appointment_date) {
            $limitService = app(AppointmentLimitService::class);
            
            // Get all appointment types for this appointment
            $appointmentTypeIds = $appointment->appointment_types->pluck('id')->toArray();
            if (empty($appointmentTypeIds)) {
                // Fallback to single appointment_type_id if many-to-many is empty
                $appointmentTypeIds = [$appointment->appointment_type_id];
            }
            
            foreach ($appointmentTypeIds as $typeId) {
                $limitCheck = $limitService->checkDailyLimit($typeId, $request->appointment_date, $appointment->id);
                
                if (!$limitCheck['available']) {
                    $appointmentType = AppointmentType::find($typeId);
                    $typeName = $appointmentType ? $appointmentType->name : 'Unknown';
                    
                    return back()->withErrors([
                        'appointment_date' => sprintf(
                            'Daily limit reached for %s appointments on the selected date. Current: %d/%d',
                            $typeName,
                            $limitCheck['current_count'],
                            $limitCheck['limit']
                        ),
                    ]);
                }
            }
        }
        
        $appointment->appointment_date = $request->appointment_date;
        $appointment->appointment_time = $request->appointment_time;
        $appointment->is_approved = true;
        $appointment->save();

        // Update patient info
        $patient = $appointment->patient;
        if ($request->has('pet_gender')) {
            $patient->pet_gender = $request->pet_gender ?? '';
        }
        if ($request->has('pet_allergies')) {
            $patient->pet_allergies = $request->pet_allergies ?? '';
        }
        $patient->save();

        // Reload appointment with relationships for notification
        $appointment->load('appointment_type', 'patient.petType');
        $appointmentTypeName = $appointment->appointment_type->name ?? 'N/A';

        // Send email notification
        if ($patient->user && $patient->user->email) {
            $ownerName = trim(($patient->user->first_name ?? '') . ' ' . ($patient->user->last_name ?? '')) ?: $patient->user->name;
            $details = [
                'subject' => 'Your appointment has been approved!',
                'body' => "Hi {$ownerName},<br><br>Your appointment has been approved.<br><br>" .
                    "Appointment Date: {$request->appointment_date}<br>" .
                    "Appointment Time: {$request->appointment_time}"
            ];

            Notification::route('mail', $patient->user->email)
                ->notify(new ClientEmailNotification($details));
        }

        // Send real-time notification to client via Ably
        if ($patient->user) {
            $ablyService = app(AblyService::class);
            $link = config('app.url') . '/appointments/' . $appointment->id;
            $subject = 'Your appointment has been approved!';
            $message = "Your {$appointmentTypeName} appointment scheduled for {$request->appointment_date} at {$request->appointment_time} has been approved.";

            // Send to client's user channel
            $ablyService->publishToUser($patient->user->id, 'appointment.approved', [
                'appointment_id' => $appointment->id,
                'subject' => $subject,
                'message' => $message,
                'link' => $link,
                'appointment_date' => $request->appointment_date,
                'appointment_time' => $request->appointment_time,
                'appointment_type' => $appointmentTypeName,
                'patient_name' => $patient->pet_name ?? 'N/A',
            ]);
        }

        return redirect()->back()->with('success', 'Appointment has been approved successfully.');
    }
    /**
     * Show the prescription creation form.
     */
    public function createPrescription($id)
    {
        // Exclude general "Diarrhea" and "Vomiting" when specific types exist
        $symptoms = Symptom::whereNotIn('name', ['Diarrhea', 'Vomiting'])
            ->get()
            ->map(function ($symptom) {
                return [
                    'id' => $symptom->id,
                    'name' => $symptom->name,
                ];
            });
        
        $instructions = PrescriptionMedicine::selectRaw('DISTINCT(instructions) as instructions')
            ->whereNotNull('instructions')
            ->where('instructions', '!=', '')
            ->pluck('instructions');
        
        $appointment = Appointment::with(['patient.petType', 'appointment_type', 'user'])
            ->where('is_approved', 1)
            ->doesntHave('prescription')
            ->where('id', $id)
            ->firstOrFail();
        
        $patient = $appointment->patient;
        $medicines = Medicine::all()->map(function ($medicine) {
            return [
                'id' => $medicine->id,
                'name' => $medicine->name,
                'dosage' => $medicine->dosage,
                'stock' => $medicine->stock,
            ];
        });
        
        return Inertia::render('Admin/Prescriptions/Create', [
            'appointment' => [
                'id' => $appointment->id,
                'appointment_date' => $appointment->appointment_date->format('Y-m-d'),
                'appointment_time' => $appointment->appointment_time,
                'appointment_type' => $appointment->appointment_type->name ?? 'N/A',
            ],
            'patient' => [
                'id' => $patient->id,
                'pet_name' => $patient->pet_name,
                'pet_breed' => $patient->pet_breed,
                'pet_type' => $patient->petType->name ?? 'N/A',
                'pet_birth_date' => $patient->pet_birth_date ? $patient->pet_birth_date->format('Y-m-d') : null,
            ],
            'medicines' => $medicines,
            'symptoms' => $symptoms,
            'instructions' => $instructions,
        ]);
    }

    /**
     * Store prescription data.
     */
    public function prescribe(Request $request, $id)
    {
        $request->validate([
            'pet_current_weight' => 'required|numeric|min:0',
            'symptoms' => 'required|array|min:1',
            'symptoms.*' => 'required|string',
            'disease_ids' => 'required|array|min:1',
            'disease_ids.*' => 'required|exists:diseases,id',
            'medicines' => 'required|array|min:1',
            'medicines.*.id' => 'required|exists:medicines,id',
            'medicines.*.dosage' => 'required|string',
            'medicines.*.instructions' => 'required|string',
            'medicines.*.quantity' => 'required|string',
            'notes' => 'nullable|string',
            'follow_up_date' => 'nullable|date|after:today',
        ]);

        $appointment = Appointment::with(['patient.user', 'patient.petType', 'appointment_type'])
            ->where('is_approved', 1)
            ->doesntHave('prescription')
            ->where('id', $id)
            ->firstOrFail();
        
        return DB::transaction(function () use ($appointment, $request) {
            $prescription = Prescription::create([
                'appointment_id' => $appointment->id,
                'patient_id' => $appointment->patient_id,
                'symptoms' => $request->symptoms ? implode(', ', array_map('ucwords', $request->symptoms)) : '',
                'notes' => $request->notes ?? '',
                'pet_weight' => $request->pet_current_weight,
                'follow_up_date' => $request->follow_up_date,
            ]);

            // Save weight history
            PatientWeightHistory::create([
                'patient_id' => $appointment->patient_id,
                'weight' => $request->pet_current_weight,
                'recorded_at' => now(),
                'prescription_id' => $prescription->id,
                'notes' => 'Recorded during appointment',
            ]);

            // Create prescription diagnoses
            foreach ($request->disease_ids as $disease_id) {
                PrescriptionDiagnosis::create([
                    'appointment_id' => $appointment->id,
                    'prescription_id' => $prescription->id,
                    'disease_id' => $disease_id,
                ]);

                // Create disease-medicine relationships for machine learning
                foreach ($request->medicines as $medicine) {
                    DB::table('disease_medicines')->updateOrInsert(
                        [
                            'medicine_id' => $medicine['id'],
                            'disease_id' => $disease_id,
                        ],
                        [
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                }
                
                // Create disease-symptom relationships for machine learning
                $data_symptoms = $request->symptoms;
                foreach ($data_symptoms as $symptom) {
                    $symptom_data = ucfirst(trim($symptom));
                    $symptomModel = Symptom::firstOrCreate(
                        ['name' => $symptom_data],
                        ['name' => $symptom_data]
                    );

                    // Create record for machine learning
                    DB::table('disease_symptoms')->updateOrInsert(
                        [
                            'disease_id' => $disease_id,
                            'symptom_id' => $symptomModel->id,
                        ],
                        [
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                }
            }
            
            // Create prescription medicines
            foreach ($request->medicines as $medicine) {
                PrescriptionMedicine::create([
                    'prescription_id' => $prescription->id,
                    'medicine_id' => $medicine['id'],
                    'dosage' => $medicine['dosage'],
                    'instructions' => $medicine['instructions'],
                    'quantity' => $medicine['quantity'],
                ]);
            }

            // Generate appointment summary
            $summary = $this->generateAppointmentSummary($appointment, $request, $prescription);
            
            $appointment->is_completed = true;
            $appointment->summary = $summary;
            $appointment->save();

            // Send prescription email notification to the owner (queued)
            $patient = $appointment->patient;
            if ($patient && $patient->user && $patient->user->email) {
                $patient->user->notify(new PrescriptionEmailNotification($prescription));
            }

            return redirect()->route('admin.prescriptions.all')
                ->with('success', 'Prescription created successfully.');
        });
    }

    /**
     * Generate appointment summary text.
     */
    private function generateAppointmentSummary($appointment, $request, $prescription)
    {
        $summaryParts = [];
        
        // Appointment Information
        $summaryParts[] = "APPOINTMENT SUMMARY";
        $summaryParts[] = "Date: " . $appointment->appointment_date->format('F d, Y');
        
        // Format time from 24-hour to 12-hour format
        $timeParts = explode(':', $appointment->appointment_time);
        $hour = (int)$timeParts[0];
        $minute = $timeParts[1] ?? '00';
        $ampm = $hour >= 12 ? 'PM' : 'AM';
        $hour12 = $hour % 12;
        if ($hour12 == 0) $hour12 = 12;
        $formattedTime = sprintf('%d:%s %s', $hour12, $minute, $ampm);
        $summaryParts[] = "Time: " . $formattedTime;
        $summaryParts[] = "Type: " . ($appointment->appointment_type ? $appointment->appointment_type->name : 'N/A');
        
        // Patient Information
        $patient = $appointment->patient;
        if ($patient) {
            $summaryParts[] = "";
            $summaryParts[] = "PATIENT INFORMATION";
            $summaryParts[] = "Pet Name: " . $patient->pet_name;
            $summaryParts[] = "Pet Type: " . ($patient->petType ? $patient->petType->name : 'N/A');
            $summaryParts[] = "Breed: " . ($patient->pet_breed ?? 'N/A');
            $summaryParts[] = "Current Weight: " . $request->pet_current_weight . " kg";
        }
        
        // Symptoms
        if (!empty($request->symptoms)) {
            $summaryParts[] = "";
            $summaryParts[] = "SYMPTOMS OBSERVED";
            $summaryParts[] = implode(', ', array_map('ucwords', $request->symptoms));
        }
        
        // Diagnoses
        if (!empty($request->disease_ids)) {
            $diseases = Disease::whereIn('id', $request->disease_ids)->pluck('name')->toArray();
            $summaryParts[] = "";
            $summaryParts[] = "DIAGNOSES";
            $summaryParts[] = implode(', ', $diseases);
        }
        
        // Prescribed Medicines
        if (!empty($request->medicines)) {
            $summaryParts[] = "";
            $summaryParts[] = "PRESCRIBED MEDICINES";
            foreach ($request->medicines as $medicine) {
                $medicineModel = Medicine::find($medicine['id']);
                $medicineName = $medicineModel ? $medicineModel->name : 'Unknown';
                $summaryParts[] = "- " . $medicineName . " (" . $medicine['dosage'] . ") - " . $medicine['instructions'] . " - Quantity: " . $medicine['quantity'];
            }
        }
        
        // Notes
        if (!empty($request->notes)) {
            $summaryParts[] = "";
            $summaryParts[] = "ADDITIONAL NOTES";
            $summaryParts[] = $request->notes;
        }
        
        // Follow-up Date
        if (!empty($request->follow_up_date)) {
            $summaryParts[] = "";
            $summaryParts[] = "FOLLOW-UP APPOINTMENT";
            $summaryParts[] = "Scheduled for: " . date('F d, Y', strtotime($request->follow_up_date));
        }
        
        $summaryParts[] = "";
        $summaryParts[] = "Appointment completed on " . now()->format('F d, Y \a\t g:i A');
        
        return implode("\n", $summaryParts);
    }

    /**
     * Download prescription PDF.
     */
    public function downloadPrescription($id)
    {
        // Custom paper size: 5.5" × 8.5" (half-letter)
        // 1 inch = 72 points, so 5.5" = 396pt, 8.5" = 612pt
        $customPaper = [0, 0, 396, 612];
        
        $prescription = Prescription::with(
            'medicines.medicine',
            'appointment',
            'patient',
            'diagnoses.disease'
        )->where('appointment_id', $id)->firstOrFail();

        $base64Logo = 'data:image/png;base64,' . base64_encode(file_get_contents(public_path('media/logo_for_print.png')));
        $base64PanaboLogo = 'data:image/png;base64,' . base64_encode(file_get_contents(public_path('media/panabo.png')));
        $base64PrescriptionLogo = 'data:image/png;base64,' . base64_encode(file_get_contents(public_path('media/prescription.png')));

        // Get veterinarian information from settings
        $veterinarianName = Setting::get('veterinarian_name', '');
        $veterinarianLicense = Setting::get('veterinarian_license_number', '');

        return Pdf::setOptions([
            'isRemoteEnabled' => true,
            'isHtml5ParserEnabled' => true,
            'isPhpEnabled' => true,
        ])
        ->loadView('admin.appointments.pdf', compact(
            'prescription',
            'base64Logo',
            'base64PanaboLogo',
            'base64PrescriptionLogo',
            'veterinarianName',
            'veterinarianLicense'
        ))
        ->setPaper($customPaper, 'portrait')
        ->stream('prescription-' . $prescription->id . '.pdf');
    }

    /**
     * Debug HTML view for prescription.
     */
    public function debugPrescription($id)
    {
        $prescription = Prescription::with(
            'medicines.medicine',
            'appointment',
            'patient',
            'diagnoses.disease'
        )->where('appointment_id', $id)->firstOrFail();

        $base64Logo = 'data:image/png;base64,' . base64_encode(file_get_contents(public_path('media/logo_for_print.png')));
        $base64PanaboLogo = 'data:image/png;base64,' . base64_encode(file_get_contents(public_path('media/panabo.png')));
        $base64PrescriptionLogo = 'data:image/png;base64,' . base64_encode(file_get_contents(public_path('media/prescription.png')));

        // Get veterinarian information from settings
        $veterinarianName = Setting::get('veterinarian_name', '');
        $veterinarianLicense = Setting::get('veterinarian_license_number', '');

        return view('admin.appointments.pdf-debug', compact(
            'prescription',
            'base64Logo',
            'base64PanaboLogo',
            'base64PrescriptionLogo',
            'veterinarianName',
            'veterinarianLicense'
        ));
    }
}
