<?php

namespace App\Http\Controllers;

use App\Constants\Components\PhilippineHolidays;
use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\DisabledDate;
use App\Models\Patient;
use App\Models\PetBreed;
use App\Models\PetType;
use App\Models\User;
use App\Notifications\DatabaseNotification;
use App\Services\AblyService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;

class ClientController extends Controller
{
    /**
     * Display a listing of the client's appointments.
     */
    public function appointments(Request $request)
    {
        $pets = Patient::where('user_id', auth()->id())->with('petType')->get();
        $appointment_types = AppointmentType::all();

        // Check if it's an AJAX request but NOT an Inertia request
        // Also check if Accept header wants JSON or if it's explicitly an API request
        $isApiRequest = ($request->ajax() || $request->wantsJson()) && !$request->header('X-Inertia');
        
        if ($isApiRequest) {
            // Handle search parameter - it comes as search[value] from Vue component
            // Laravel parses search[value] as search.value
            $searchInput = $request->input('search');
            if (is_array($searchInput) && isset($searchInput['value'])) {
                $keyword = trim((string) $searchInput['value']);
            } else {
                $keyword = trim((string) ($request->input('search.value') ?? ''));
            }

            // Primary check: appointments belong to the authenticated user
            // This is the most reliable since we set user_id when creating appointments
            $appointments = Appointment::where('appointments.user_id', auth()->id());

            if (!empty($keyword)) {
                $appointments->where(function ($q) use ($keyword) {
                    // Search in patients via pivot table
                    $q->whereHas('patients.petType', function ($subQ) use ($keyword) {
                        $subQ->where('pet_types.name', 'LIKE', "%{$keyword}%");
                    })
                    ->orWhereHas('patients', function ($subQ) use ($keyword) {
                        $subQ->where('patients.pet_name', 'LIKE', "%{$keyword}%");
                    })
                    // Fallback to legacy patient relationship
                    ->orWhereHas('patient.petType', function ($subQ) use ($keyword) {
                        $subQ->where('pet_types.name', 'LIKE', "%{$keyword}%");
                    })
                    ->orWhereHas('patient', function ($subQ) use ($keyword) {
                        $subQ->where('patients.pet_name', 'LIKE', "%{$keyword}%");
                    })
                    // Search in appointment types
                    ->orWhereHas('appointment_type', function ($subQ) use ($keyword) {
                        $subQ->where('appointment_types.name', 'LIKE', "%{$keyword}%");
                    })
                    // Search in prescriptions and diseases
                    ->orWhereHas('prescription.diagnoses.disease', function ($subQ) use ($keyword) {
                        $subQ->where('diseases.name', 'LIKE', "%{$keyword}%");
                    });
                });
            }

            // Status filtering
            if ($request->has('status') && !empty($request->status) && $request->status !== 'all') {
                $status = strtolower($request->status);
                switch ($status) {
                    case 'pending':
                        $appointments->where('appointments.is_approved', false)
                              ->where('appointments.is_completed', false)
                              ->where(function ($q) {
                                  $q->whereNull('appointments.is_canceled')->orWhere('appointments.is_canceled', false);
                              });
                        break;
                    case 'approved':
                        $appointments->where('appointments.is_approved', true)
                              ->where('appointments.is_completed', false)
                              ->where(function ($q) {
                                  $q->whereNull('appointments.is_canceled')->orWhere('appointments.is_canceled', false);
                              });
                        break;
                    case 'completed':
                        $appointments->where('appointments.is_completed', true);
                        break;
                    case 'canceled':
                        $appointments->where('appointments.is_canceled', true);
                        break;
                }
            }

            // Order appointments and load relationships
            $appointments = $appointments
                ->orderBy('appointments.appointment_date', 'desc')
                ->orderBy('appointments.appointment_time', 'desc')
                ->orderBy('appointments.created_at', 'desc')
                ->with('patients.petType', 'appointment_type', 'appointment_types')
                ->get();

            // Build result array - ONE appointment can have multiple pets
            $result = [];
            foreach ($appointments as $appointment) {
                // Get all patients for this appointment
                $patients = $appointment->patients;
                
                // If no patients in pivot table, fallback to single patient
                if ($patients->isEmpty() && $appointment->patient) {
                    $patients = collect([$appointment->patient]);
                    // Load petType for the single patient if not loaded
                    if ($patients->first() && !$patients->first()->relationLoaded('petType')) {
                        $patients->first()->load('petType');
                    }
                }

                // Calculate status
                $status = 'Pending';
                if ($appointment->is_canceled) {
                    $status = 'Canceled';
                } elseif ($appointment->is_approved) {
                    $status = $appointment->is_completed ? 'Completed' : 'Approved';
                }

                // Get appointment type name(s) from relationship
                // Check if appointment has multiple appointment types via many-to-many
                $appointmentTypes = [];
                if ($appointment->relationLoaded('appointment_types') && $appointment->appointment_types->isNotEmpty()) {
                    $appointmentTypes = $appointment->appointment_types->pluck('name')->toArray();
                } elseif ($appointment->relationLoaded('appointment_type') && $appointment->appointment_type) {
                    $appointmentTypes = [$appointment->appointment_type->name];
                } elseif ($appointment->appointment_type_id) {
                    // Fallback: load if not already loaded
                    if (!$appointment->relationLoaded('appointment_types')) {
                        $appointment->load('appointment_types');
                    }
                    if ($appointment->appointment_types->isNotEmpty()) {
                        $appointmentTypes = $appointment->appointment_types->pluck('name')->toArray();
                    } else {
                        $appointment->load('appointment_type');
                        $appointmentTypes = $appointment->appointment_type ? [$appointment->appointment_type->name] : ['N/A'];
                    }
                } else {
                    $appointmentTypes = ['N/A'];
                }
                
                // Create display string for appointment types
                $appointmentTypeName = count($appointmentTypes) > 1 
                    ? implode(', ', $appointmentTypes) 
                    : ($appointmentTypes[0] ?? 'N/A');

                $petCount = $patients->count();
                
                // Build pet items for this appointment
                // Get each pet's individual appointment type(s) from the pivot table
                $petItems = $patients->map(function ($pet) use ($appointment) {
                    // Get appointment types for this specific pet from the pivot table
                    $petAppointmentTypes = \DB::table('appointment_patient')
                        ->where('appointment_id', $appointment->id)
                        ->where('patient_id', $pet->id)
                        ->join('appointment_types', 'appointment_patient.appointment_type_id', '=', 'appointment_types.id')
                        ->pluck('appointment_types.name')
                        ->toArray();
                    
                    // If no appointment types found in pivot, fallback to appointment's primary type
                    if (empty($petAppointmentTypes)) {
                        if ($appointment->relationLoaded('appointment_type') && $appointment->appointment_type) {
                            $petAppointmentTypes = [$appointment->appointment_type->name];
                        } else {
                            $petAppointmentTypes = ['N/A'];
                        }
                    }
                    
                    $petAppointmentTypeDisplay = count($petAppointmentTypes) > 1 
                        ? implode(', ', $petAppointmentTypes) 
                        : ($petAppointmentTypes[0] ?? 'N/A');
                    
                    return [
                        'id' => $pet->id,
                        'appointment_type' => $petAppointmentTypeDisplay,
                        'pet_type' => $pet->petType ? $pet->petType->name : 'N/A',
                        'pet_name' => $pet->pet_name,
                    ];
                })->toArray();

                // Get unique pet types for display
                $petTypes = $patients->map(function ($pet) {
                    return $pet->petType ? $pet->petType->name : 'N/A';
                })->unique()->values()->toArray();
                $petTypeDisplay = $petCount > 1 
                    ? (count($petTypes) > 1 ? implode(', ', $petTypes) : ($petTypes[0] ?? 'N/A'))
                    : ($patients->first() && $patients->first()->petType ? $patients->first()->petType->name : 'N/A');

                // Always return as ONE appointment (not grouped)
                // If multiple pets, show pet count badge
                $result[] = [
                    'id' => $appointment->id,
                    'appointment_date' => $appointment->appointment_date ? $appointment->appointment_date->format('Y-m-d') : null,
                    'appointment_time' => $appointment->appointment_time,
                    'status' => $status,
                    'pet_count' => $petCount,
                    'appointments' => $petItems, // All pets in this appointment
                    'appointment_type' => $appointmentTypeName,
                    // For display purposes
                    'pet_name' => $petCount > 1 
                        ? ($patients->first() ? $patients->first()->pet_name . ' (+' . ($petCount - 1) . ' more)' : 'N/A')
                        : ($patients->first() ? $patients->first()->pet_name : 'N/A'),
                    'pet_type' => $petTypeDisplay,
                ];
            }

            return response()->json([
                'data' => $result,
            ]);
        }

        // Get pet types and breeds for creating new pets
        $pet_types = PetType::all()->map(function ($pet_type) {
            return [
                'id' => $pet_type->id,
                'name' => $pet_type->name,
            ];
        });

        // Build pet breeds mapping from database
        $pet_breeds = [];
        foreach ($pet_types as $pet_type) {
            $breeds = PetBreed::where('pet_type_id', $pet_type['id'])
                ->orderBy('name')
                ->pluck('name')
                ->toArray();
            $pet_breeds[$pet_type['name']] = $breeds;
        }

        // Get Philippine holidays for current year and next year
        $holidays = PhilippineHolidays::getCurrentAndNextYearHolidays();

        return Inertia::render('Client/Appointments/Index', [
            'pets' => $pets->map(function ($pet) {
                return [
                    'id' => $pet->id,
                    'pet_name' => $pet->pet_name,
                    'pet_type' => $pet->petType->name ?? 'N/A',
                ];
            }),
            'appointment_types' => $appointment_types->map(function ($type) {
                return [
                    'id' => $type->id,
                    'name' => $type->name,
                ];
            }),
            'pet_types' => $pet_types,
            'pet_breeds' => $pet_breeds,
            'philippine_holidays' => $holidays,
        ]);
    }

    /**
     * Store a newly created appointment.
     */
    public function bookAppointment(Request $request)
    {
        $appointmentTimes = $request->has('appointment_times') && is_array($request->appointment_times) ? $request->appointment_times : 
                           ($request->has('appointment_time') ? [$request->appointment_time] : []);

        // Check if using new format (pet_appointments) or legacy format (pet_ids + appointment_type_ids)
        $petAppointments = $request->has('pet_appointments') && is_array($request->pet_appointments) ? $request->pet_appointments : null;
        
        if ($petAppointments) {
            // New format: pet_appointments array with pet_id and appointment_type_id pairs
            $request->validate([
                'pet_appointments' => 'required|array|min:1',
                'pet_appointments.*.pet_id' => 'required|exists:patients,id',
                'pet_appointments.*.appointment_type_id' => 'required|exists:appointment_types,id',
                'appointment_date' => 'required|date|after:today',
                'appointment_times' => 'sometimes|array|min:1',
                'appointment_times.*' => 'required_with:appointment_times|string',
                'appointment_time' => 'sometimes|required_without:appointment_times|string',
                'symptoms' => 'nullable|string|max:1825',
            ]);

            if (empty($appointmentTimes)) {
                return back()->withErrors([
                    'appointment_times' => ['At least one appointment time must be selected.'],
                ])->withInput();
            }

            // Extract unique pet IDs and verify all pets belong to the authenticated user
            $petIds = array_unique(array_column($petAppointments, 'pet_id'));
            $pets = Patient::whereIn('id', $petIds)
                ->where('user_id', auth()->id())
                ->get();

            if ($pets->count() !== count($petIds)) {
                return back()->withErrors([
                    'pet_appointments' => ['One or more selected pets do not belong to you.'],
                ])->withInput();
            }

            $appointmentDate = $request->appointment_date;
            $symptoms = $request->symptoms ?? '';
            $createdAppointments = [];

            // All pets use the first (and only) selected time slot
            $appointmentTime = $appointmentTimes[0];

            // Convert time from 12-hour format (h:i A) to 24-hour format (H:i)
            $time = Carbon::createFromFormat('h:i A', $appointmentTime);

            // Validate timeslot restrictions once before creating appointments
            try {
                $this->validateTimeslotRestrictions($appointmentDate, $time->format('H:i'));
            } catch (\Illuminate\Validation\ValidationException $e) {
                return back()->withErrors($e->errors())->withInput();
            }

            // Group ALL pets together into ONE appointment regardless of appointment type
            // IMPORTANT: All selected pets will be grouped into ONE appointment, even if they have different appointment types
            // Example: Pet 1 with "Check-up" + Pet 2 with "Vaccination" = ONE appointment with 2 pets and 2 appointment types
            $allPetIds = [];
            $allAppointmentTypeIds = [];
            $petAppointmentTypeMap = []; // Track which pet has which appointment type(s)
            
            foreach ($petAppointments as $pair) {
                // Validate pair structure
                if (!isset($pair['pet_id']) || !isset($pair['appointment_type_id'])) {
                    continue; // Skip invalid pairs
                }
                
                $petId = (int) $pair['pet_id']; // Ensure integer
                $appointmentTypeId = (int) $pair['appointment_type_id']; // Ensure integer

                // Verify pet belongs to user
                $pet = $pets->firstWhere('id', $petId);
                if (!$pet) {
                    Log::warning("Pet {$petId} not found or doesn't belong to user " . auth()->id());
                    continue;
                }

                // Collect all unique pet IDs
                if (!in_array($petId, $allPetIds)) {
                    $allPetIds[] = $petId;
                }

                // Collect all unique appointment type IDs
                if (!in_array($appointmentTypeId, $allAppointmentTypeIds)) {
                    $allAppointmentTypeIds[] = $appointmentTypeId;
                }

                // Track which appointment types each pet has
                if (!isset($petAppointmentTypeMap[$petId])) {
                    $petAppointmentTypeMap[$petId] = [];
                }
                if (!in_array($appointmentTypeId, $petAppointmentTypeMap[$petId])) {
                    $petAppointmentTypeMap[$petId][] = $appointmentTypeId;
                }
            }
            
            // Log grouping for debugging
            Log::info('Appointment grouping', [
                'user_id' => auth()->id(),
                'input_pairs' => $petAppointments,
                'all_pet_ids' => $allPetIds,
                'all_appointment_type_ids' => $allAppointmentTypeIds,
                'pet_appointment_type_map' => $petAppointmentTypeMap,
            ]);

            // Create ONE appointment for ALL pets with ALL appointment types
            // Use database transaction to ensure atomicity
            \DB::transaction(function () use ($allPetIds, $allAppointmentTypeIds, $petAppointmentTypeMap, $appointmentDate, $symptoms, $time, &$createdAppointments) {
                // Ensure we have at least one pet
                $allPetIds = array_unique($allPetIds);
                if (empty($allPetIds)) {
                    Log::warning("No valid pets found for appointment creation");
                    return;
                }

                // Get the first pet as the primary patient_id (for backward compatibility)
                $firstPetId = $allPetIds[0];
                
                // Get the first appointment type as the primary appointment_type_id (for backward compatibility)
                $firstAppointmentTypeId = !empty($allAppointmentTypeIds) ? $allAppointmentTypeIds[0] : null;
                if (!$firstAppointmentTypeId) {
                    Log::warning("No appointment types found for appointment creation");
                    return;
                }

                // Create ONE appointment for ALL pets
                $appointment = Appointment::create([
                    'patient_id' => $firstPetId, // Keep for backward compatibility
                    'appointment_type_id' => $firstAppointmentTypeId, // Primary appointment type for backward compatibility
                    'appointment_date' => $appointmentDate,
                    'symptoms' => $symptoms,
                    'is_approved' => false, // Client appointments start as pending
                    'is_completed' => false, // Explicitly set to false for pending status
                    'appointment_time' => $time->format('H:i'), // Store in 24-hour format
                    'user_id' => Auth::id(),
                ]);

                // Attach ALL pets to this ONE appointment via pivot table with their individual appointment types
                // Each pet can have multiple appointment types, so we need to attach each combination
                foreach ($petAppointmentTypeMap as $petId => $appointmentTypeIds) {
                    foreach ($appointmentTypeIds as $appointmentTypeId) {
                        // Check if this combination already exists to avoid duplicates
                        $exists = \DB::table('appointment_patient')
                            ->where('appointment_id', $appointment->id)
                            ->where('patient_id', $petId)
                            ->where('appointment_type_id', $appointmentTypeId)
                            ->exists();
                        
                        if (!$exists) {
                            $appointment->patients()->attach($petId, [
                                'appointment_type_id' => $appointmentTypeId,
                            ]);
                        }
                    }
                }
                
                // Attach ALL appointment types to this appointment via many-to-many relationship
                if (!empty($allAppointmentTypeIds)) {
                    $appointment->appointment_types()->sync($allAppointmentTypeIds);
                }
                
                // Reload the appointment with relationships to ensure data is correct
                $appointment->load('patients', 'appointment_type', 'appointment_types');

                // Log for debugging
                Log::info('Appointment created', [
                    'appointment_id' => $appointment->id,
                    'primary_appointment_type_id' => $firstAppointmentTypeId,
                    'all_appointment_type_ids' => $allAppointmentTypeIds,
                    'pet_count' => count($allPetIds),
                    'pet_ids' => $allPetIds,
                    'pet_appointment_type_map' => $petAppointmentTypeMap,
                ]);

                $createdAppointments[] = $appointment;
            });
        } else {
            // Legacy format: separate pet_ids and appointment_type_ids arrays (creates cartesian product)
            $petIds = $request->has('pet_ids') && is_array($request->pet_ids) ? $request->pet_ids : 
                      ($request->has('pet_id') ? [$request->pet_id] : []);
            $appointmentTypeIds = $request->has('appointment_type_ids') && is_array($request->appointment_type_ids) ? $request->appointment_type_ids : 
                                  ($request->has('appointment_type_id') ? [$request->appointment_type_id] : []);

            // Validate that we have at least one of each required field
            $errors = [];
            if (empty($petIds)) {
                $errors['pet_ids'] = ['At least one pet must be selected.'];
            }
            if (empty($appointmentTypeIds)) {
                $errors['appointment_type_ids'] = ['At least one appointment type must be selected.'];
            }
            if (empty($appointmentTimes)) {
                $errors['appointment_times'] = ['At least one appointment time must be selected.'];
            }
            if (!empty($errors)) {
                return back()->withErrors($errors)->withInput();
            }

            $request->validate([
                'pet_ids' => 'sometimes|array|min:1',
                'pet_ids.*' => 'required_with:pet_ids|exists:patients,id',
                'pet_id' => 'sometimes|required_without:pet_ids|exists:patients,id', // Legacy support
                'appointment_type_ids' => 'sometimes|array|min:1',
                'appointment_type_ids.*' => 'required_with:appointment_type_ids|exists:appointment_types,id',
                'appointment_type_id' => 'sometimes|required_without:appointment_type_ids|exists:appointment_types,id', // Legacy support
                'appointment_date' => 'required|date|after:today',
                'appointment_times' => 'sometimes|array|min:1',
                'appointment_times.*' => 'required_with:appointment_times|string',
                'appointment_time' => 'sometimes|required_without:appointment_times|string', // Legacy support
                'symptoms' => 'nullable|string|max:1825',
            ]);

            // Multiple pets can share the same time slot - only require at least one time slot
            if (empty($appointmentTimes)) {
                return back()->withErrors([
                    'appointment_times' => ['At least one appointment time must be selected.'],
                ])->withInput();
            }

            // Verify all pets belong to the authenticated user
            $pets = Patient::whereIn('id', $petIds)
                ->where('user_id', auth()->id())
                ->get();

            if ($pets->count() !== count($petIds)) {
                return back()->withErrors([
                    'pet_ids' => ['One or more selected pets do not belong to you.'],
                ])->withInput();
            }

            $appointmentDate = $request->appointment_date;
            $symptoms = $request->symptoms ?? '';
            $createdAppointments = [];

            // All pets use the first (and only) selected time slot
            $appointmentTime = $appointmentTimes[0];

            // Convert time from 12-hour format (h:i A) to 24-hour format (H:i)
            $time = Carbon::createFromFormat('h:i A', $appointmentTime);

            // Validate timeslot restrictions once before creating appointments for all pets
            // Multiple pets can share the same time slot, so validation should happen before the loop
            try {
                $this->validateTimeslotRestrictions($appointmentDate, $time->format('H:i'));
            } catch (\Illuminate\Validation\ValidationException $e) {
                return back()->withErrors($e->errors())->withInput();
            }

            // Group by appointment type - create ONE appointment per type with all pets attached
            // Use database transaction to ensure atomicity
            // Group ALL pets together into ONE appointment with ALL appointment types
            \DB::transaction(function () use ($appointmentTypeIds, $petIds, $appointmentDate, $symptoms, $time, &$createdAppointments) {
                // Ensure unique pet IDs and appointment type IDs
                $uniquePetIds = array_unique($petIds);
                $uniqueAppointmentTypeIds = array_unique($appointmentTypeIds);
                
                if (empty($uniquePetIds)) {
                    Log::warning("No valid pets found for appointment creation (legacy format)");
                    return;
                }
                
                if (empty($uniqueAppointmentTypeIds)) {
                    Log::warning("No appointment types found for appointment creation (legacy format)");
                    return;
                }

                // Get the first pet as the primary patient_id (for backward compatibility)
                $firstPetId = $uniquePetIds[0];
                
                // Get the first appointment type as the primary appointment_type_id (for backward compatibility)
                $firstAppointmentTypeId = $uniqueAppointmentTypeIds[0];

                // Create ONE appointment for ALL pets with ALL appointment types
                $appointment = Appointment::create([
                    'patient_id' => $firstPetId, // Keep for backward compatibility
                    'appointment_type_id' => $firstAppointmentTypeId, // Primary appointment type for backward compatibility
                    'appointment_date' => $appointmentDate,
                    'symptoms' => $symptoms,
                    'is_approved' => false, // Client appointments start as pending
                    'is_completed' => false, // Explicitly set to false for pending status
                    'appointment_time' => $time->format('H:i'), // Store in 24-hour format
                    'user_id' => Auth::id(),
                ]);

                // Attach ALL pets to this ONE appointment via pivot table with ALL appointment types
                // Legacy format: each pet gets all appointment types (cartesian product)
                foreach ($uniquePetIds as $petId) {
                    foreach ($uniqueAppointmentTypeIds as $appointmentTypeId) {
                        // Check if this combination already exists to avoid duplicates
                        $exists = \DB::table('appointment_patient')
                            ->where('appointment_id', $appointment->id)
                            ->where('patient_id', $petId)
                            ->where('appointment_type_id', $appointmentTypeId)
                            ->exists();
                        
                        if (!$exists) {
                            $appointment->patients()->attach($petId, [
                                'appointment_type_id' => $appointmentTypeId,
                            ]);
                        }
                    }
                }
                
                // Attach ALL appointment types to this appointment via many-to-many relationship
                $appointment->appointment_types()->sync($uniqueAppointmentTypeIds);
                
                // Reload the appointment with relationships
                $appointment->load('patients', 'appointment_type', 'appointment_types');

                // Log for debugging
                Log::info('Appointment created (legacy format)', [
                    'appointment_id' => $appointment->id,
                    'primary_appointment_type_id' => $firstAppointmentTypeId,
                    'all_appointment_type_ids' => $uniqueAppointmentTypeIds,
                    'pet_count' => count($uniquePetIds),
                    'pet_ids' => $uniquePetIds,
                ]);

                $createdAppointments[] = $appointment;
            });
        }

        // Reload appointments with relationships
        foreach ($createdAppointments as $appointment) {
            $appointment->load('appointment_type', 'appointment_types', 'patients.petType', 'patient.petType', 'patient.user');
        }

        // Notify Super Admins
        $adminUsers = User::select('users.*')
            ->leftJoin('model_has_roles as mhr', 'mhr.model_id', 'users.id')
            ->leftJoin('roles', 'roles.id', 'mhr.role_id')
            ->where('roles.name', 'admin')
            ->distinct()
            ->get();

        // Notify Staff
        $staffUsers = User::select('users.*')
            ->leftJoin('model_has_roles as mhr', 'mhr.model_id', 'users.id')
            ->leftJoin('roles', 'roles.id', 'mhr.role_id')
            ->where('roles.name', 'staff')
            ->distinct()
            ->get();

        $ablyService = app(AblyService::class);

        // Send notifications for each created appointment
        foreach ($createdAppointments as $appointment) {
            $pet = $appointment->patient;
            $patient_owner_full_name = $pet->user ? 
                trim(($pet->user->first_name ?? '') . ' ' . ($pet->user->last_name ?? '')) ?: $pet->user->name : 'N/A';
            $appointmentTypeName = $appointment->appointment_type->name ?? 'N/A';
            $appointmentTime12Hour = Carbon::createFromFormat('H:i', $appointment->appointment_time)->format('h:i A');

            $link = config('app.url') . '/admin/appointments/' . $appointment->id;
            $subject = sprintf("%s has submitted new appointment.", $patient_owner_full_name ?? '');
            $message = "Hi, new appointment has been submitted<br><br>" .
                "Appointment Details.<br><br>" .
                "Full Name: " . $patient_owner_full_name . "<br>" .
                "Mobile Number: " . ($pet->user ? ($pet->user->mobile_number ?? 'N/A') : 'N/A') . "<br>" .
                "Email Address: " . ($pet->user ? ($pet->user->email ?? 'N/A') : 'N/A') . "<br>" .
                "Pet Type: " . ($pet->petType->name ?? 'N/A') . "<br>" .
                "Breed: " . ($pet->pet_breed ?? 'N/A') . "<br>" .
                "Appointment Type: " . $appointmentTypeName . "<br>" .
                "Appointment Date: " . $appointmentDate . "<br>" .
                "Appointment Time: " . $appointmentTime12Hour . "<br>" .
                "<p style='text-align:center'><a href='" . $link . "' style='background-color: #4CAF50; border: none; color: white; padding: 15px 32px; text-align: center; text-decoration: none; font-size: 12px; border-radius: 15px;'>View Appointment</a></p>";

            $appointmentMessage = $appointmentTypeName . ' appointment scheduled for ' . $appointmentDate . ' at ' . $appointmentTime12Hour;

            // Send notifications via database, email, and Ably to admins
            foreach ($adminUsers as $adminUser) {
                $adminUser->notify(new \App\Notifications\DefaultNotification($subject, $message, $link));
                
                // Send real-time notification via Ably
                $ablyService->publishToUser($adminUser->id, 'appointment.created', [
                    'appointment_id' => $appointment->id,
                    'subject' => $subject,
                    'message' => $appointmentMessage,
                    'link' => $link,
                    'patient_name' => $pet->pet_name,
                    'owner_name' => $patient_owner_full_name,
                ]);
            }

            // Send notifications via database and Ably to staff
            foreach ($staffUsers as $staffUser) {
                $staffUser->notify(new \App\Notifications\DefaultNotification($subject, $message, $link));
                
                // Send real-time notification via Ably
                $ablyService->publishToUser($staffUser->id, 'appointment.created', [
                    'appointment_id' => $appointment->id,
                    'subject' => $subject,
                    'message' => $appointmentMessage,
                    'link' => $link,
                    'patient_name' => $pet->pet_name,
                    'owner_name' => $patient_owner_full_name,
                ]);
            }
        }

        // Also publish to admin channel for all admins (once for all appointments)
        if (count($createdAppointments) > 0) {
            $firstAppointment = $createdAppointments[0];
            $firstPet = $firstAppointment->patient;
            $patient_owner_full_name = $firstPet->user ? 
                trim(($firstPet->user->first_name ?? '') . ' ' . ($firstPet->user->last_name ?? '')) ?: $firstPet->user->name : 'N/A';
            
            $ablyService->publishToAdmins('appointment.created', [
                'appointment_count' => count($createdAppointments),
                'owner_name' => $patient_owner_full_name,
            ]);

            // Also publish to staff channel for all staff
            $ablyService->publishToStaff('appointment.created', [
                'appointment_count' => count($createdAppointments),
                'owner_name' => $patient_owner_full_name,
            ]);
        }

        $appointmentCount = count($createdAppointments);
        $successMessage = $appointmentCount === 1 
            ? 'Appointment created successfully.'
            : sprintf('%d appointments created successfully.', $appointmentCount);

        return redirect()->route('client.appointments.index')
            ->with('success', $successMessage);
    }

    /**
     * Display the specified appointment.
     */
    public function showAppointments($id)
    {
        $appointment = Appointment::with(['appointment_type', 'patients.petType', 'patient.petType'])
            ->where('id', $id)
            ->where(function ($query) {
                $query->whereHas('patients', function ($q) {
                    $q->where('user_id', auth()->id());
                })
                ->orWhereHas('patient', function ($q) {
                    $q->where('user_id', auth()->id());
                })
                ->orWhere('user_id', auth()->id());
            })
            ->firstOrFail();
        
        // Ensure relationships are loaded
        if (!$appointment->relationLoaded('appointment_type')) {
            $appointment->load('appointment_type');
        }
        if (!$appointment->relationLoaded('patients')) {
            $appointment->load('patients.petType');
        }
        if (!$appointment->relationLoaded('patient')) {
            $appointment->load('patient.petType');
        }

        // Calculate status
        $status = 'Pending';
        if ($appointment->is_canceled) {
            $status = 'Canceled';
        } elseif ($appointment->is_approved) {
            $status = $appointment->is_completed ? 'Completed' : 'Approved';
        }

        // Build patients array from the appointment's patients relationship
        // Use patients() relationship (many-to-many) if available, fallback to patient() for backward compatibility
        $patients = [];
        if ($appointment->patients && $appointment->patients->isNotEmpty()) {
            // New structure: multiple pets via pivot table
            $patients = $appointment->patients->map(function ($pet) use ($appointment) {
                // Get appointment types for this specific pet from the pivot table
                $petAppointmentTypes = \DB::table('appointment_patient')
                    ->where('appointment_id', $appointment->id)
                    ->where('patient_id', $pet->id)
                    ->join('appointment_types', 'appointment_patient.appointment_type_id', '=', 'appointment_types.id')
                    ->pluck('appointment_types.name')
                    ->toArray();
                
                // If no appointment types found in pivot, fallback to appointment's primary type
                if (empty($petAppointmentTypes)) {
                    if ($appointment->relationLoaded('appointment_type') && $appointment->appointment_type) {
                        $petAppointmentTypes = [$appointment->appointment_type->name];
                    } else {
                        $petAppointmentTypes = ['N/A'];
                    }
                }
                
                return [
                    'id' => $pet->id,
                    'pet_name' => $pet->pet_name,
                    'pet_breed' => $pet->pet_breed,
                    'pet_gender' => $pet->pet_gender,
                    'pet_birth_date' => $pet->pet_birth_date ? $pet->pet_birth_date->format('Y-m-d') : null,
                    'pet_allergies' => $pet->pet_allergies,
                    'pet_type' => $pet->petType ? $pet->petType->name : 'N/A',
                    'appointment_types' => $petAppointmentTypes,
                ];
            })->toArray();
        } elseif ($appointment->patient) {
            // Fallback: single pet via patient_id (backward compatibility)
            $petAppointmentTypes = \DB::table('appointment_patient')
                ->where('appointment_id', $appointment->id)
                ->where('patient_id', $appointment->patient->id)
                ->join('appointment_types', 'appointment_patient.appointment_type_id', '=', 'appointment_types.id')
                ->pluck('appointment_types.name')
                ->toArray();
            
            if (empty($petAppointmentTypes) && $appointment->appointment_type) {
                $petAppointmentTypes = [$appointment->appointment_type->name];
            } elseif (empty($petAppointmentTypes)) {
                $petAppointmentTypes = ['N/A'];
            }
            
            $patients = [[
                'id' => $appointment->patient->id,
                'pet_name' => $appointment->patient->pet_name,
                'pet_breed' => $appointment->patient->pet_breed,
                'pet_gender' => $appointment->patient->pet_gender,
                'pet_birth_date' => $appointment->patient->pet_birth_date ? $appointment->patient->pet_birth_date->format('Y-m-d') : null,
                'pet_allergies' => $appointment->patient->pet_allergies,
                'pet_type' => $appointment->patient->petType ? $appointment->patient->petType->name : 'N/A',
                'appointment_types' => $petAppointmentTypes,
            ]];
        }

        // Load prescription if exists
        $appointment->load(['prescription.diagnoses.disease', 'prescription.medicines.medicine']);

        return Inertia::render('Client/Appointments/Show', [
            'appointment' => [
                'id' => $appointment->id,
                'appointment_type' => $appointment->appointment_type ? $appointment->appointment_type->name : 'N/A',
                'appointment_date' => $appointment->appointment_date ? $appointment->appointment_date->format('Y-m-d') : null,
                'appointment_time' => $appointment->appointment_time,
                'symptoms' => $appointment->symptoms,
                'is_approved' => $appointment->is_approved,
                'is_completed' => $appointment->is_completed,
                'is_canceled' => $appointment->is_canceled ?? false,
                'remarks' => $appointment->remarks,
                'summary' => $appointment->summary,
                'created_at' => $appointment->created_at->toISOString(),
                'updated_at' => $appointment->updated_at->toISOString(),
                'pet_count' => count($patients),
            ],
            'patient' => count($patients) === 1 ? $patients[0] : null, // For backward compatibility
            'patients' => count($patients) > 1 ? $patients : null, // Multiple patients
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
        ]);
    }

    /**
     * Cancel a pending appointment.
     */
    public function cancelAppointment(Request $request, $id)
    {
        // Check if this is an API request (not Inertia)
        $isApiRequest = ($request->ajax() || $request->wantsJson()) && !$request->header('X-Inertia');
        
        try {
            $request->validate([
                'cancel_reason' => 'required|string|in:Personal reason,Emergency,Health related,Booked incorrect date/time,Other/Prefer not to say',
            ]);

            $appointment = Appointment::where('id', $id)
                ->where(function ($query) {
                    $query->whereHas('patient', function ($q) {
                        $q->where('user_id', auth()->id());
                    })
                    ->orWhere('user_id', auth()->id());
                })
                ->firstOrFail();

            // Only allow canceling pending appointments that are not already canceled
            if ($appointment->is_canceled) {
                if ($isApiRequest) {
                    return response()->json(['error' => 'This appointment is already canceled.'], 403);
                }
                return redirect()->route('client.appointments.show', $id)
                    ->with('error', 'This appointment is already canceled.');
            }

            if ($appointment->is_approved || $appointment->is_completed) {
                if ($isApiRequest) {
                    return response()->json(['error' => 'Only pending appointments can be canceled.'], 403);
                }
                return redirect()->route('client.appointments.show', $id)
                    ->with('error', 'Only pending appointments can be canceled.');
            }

            // Reload appointment with relationships for notification
            $appointment->load('appointment_type', 'patient.petType', 'patient.user');

            // Mark appointment as canceled instead of deleting
            $appointment->update([
                'is_canceled' => true,
                'cancel_reason' => $request->cancel_reason,
            ]);

            // Prepare notification data
            $pet = $appointment->patient;
            $patient_owner_full_name = $pet->user ? 
                trim(($pet->user->first_name ?? '') . ' ' . ($pet->user->last_name ?? '')) ?: $pet->user->name : 'N/A';
            $appointmentTypeName = $appointment->appointment_type->name ?? 'N/A';
            $appointmentDate = $appointment->appointment_date->format('Y-m-d');
            $appointmentTime = Carbon::createFromFormat('H:i', $appointment->appointment_time)->format('h:i A');

            // Notify Super Admins
            $adminUsers = User::select('users.*')
                ->leftJoin('model_has_roles as mhr', 'mhr.model_id', 'users.id')
                ->leftJoin('roles', 'roles.id', 'mhr.role_id')
                ->where('roles.name', 'admin')
                ->distinct()
                ->get();

            // Notify Staff
            $staffUsers = User::select('users.*')
                ->leftJoin('model_has_roles as mhr', 'mhr.model_id', 'users.id')
                ->leftJoin('roles', 'roles.id', 'mhr.role_id')
                ->where('roles.name', 'staff')
                ->distinct()
                ->get();

            $adminLink = config('app.url') . '/admin/appointments/' . $appointment->id;
            $adminSubject = sprintf("%s has canceled an appointment.", $patient_owner_full_name ?? '');
            $adminMessage = "Hi, an appointment has been canceled<br><br>" .
                "Appointment Details.<br><br>" .
                "Full Name: " . $patient_owner_full_name . "<br>" .
                "Mobile Number: " . ($pet->user ? ($pet->user->mobile_number ?? 'N/A') : 'N/A') . "<br>" .
                "Email Address: " . ($pet->user ? ($pet->user->email ?? 'N/A') : 'N/A') . "<br>" .
                "Pet Type: " . ($pet->petType->name ?? 'N/A') . "<br>" .
                "Breed: " . ($pet->pet_breed ?? 'N/A') . "<br>" .
                "Appointment Type: " . $appointmentTypeName . "<br>" .
                "Appointment Date: " . $appointmentDate . "<br>" .
                "Appointment Time: " . $appointmentTime . "<br>" .
                "<p style='text-align:center'><a href='" . $adminLink . "' style='background-color: #4CAF50; border: none; color: white; padding: 15px 32px; text-align: center; text-decoration: none; font-size: 12px; border-radius: 15px;'>View Appointment</a></p>";

            $ablyService = app(AblyService::class);
            $appointmentMessage = $appointmentTypeName . ' appointment scheduled for ' . $appointmentDate . ' at ' . $appointmentTime . ' has been canceled';

            // Send notifications via database, email, and Ably to admins
            foreach ($adminUsers as $user) {
                $user->notify(new \App\Notifications\DefaultNotification($adminSubject, $adminMessage, $adminLink));
                
                // Send real-time notification via Ably
                $ablyService->publishToUser($user->id, 'appointment.canceled', [
                    'appointment_id' => $appointment->id,
                    'subject' => $adminSubject,
                    'message' => $appointmentMessage,
                    'link' => $adminLink,
                    'patient_name' => $pet->pet_name,
                    'owner_name' => $patient_owner_full_name,
                    'appointment_date' => $appointmentDate,
                    'appointment_time' => $appointmentTime,
                ]);
            }

            // Send real-time notifications via Ably to staff
            foreach ($staffUsers as $user) {
                $ablyService->publishToUser($user->id, 'appointment.canceled', [
                    'appointment_id' => $appointment->id,
                    'subject' => $adminSubject,
                    'message' => $appointmentMessage,
                    'link' => $adminLink,
                    'patient_name' => $pet->pet_name,
                    'owner_name' => $patient_owner_full_name,
                    'appointment_date' => $appointmentDate,
                    'appointment_time' => $appointmentTime,
                ]);
            }

            // Also publish to admin channel for all admins
            $ablyService->publishToAdmins('appointment.canceled', [
                'appointment_id' => $appointment->id,
                'subject' => $adminSubject,
                'message' => $appointmentMessage,
                'link' => $adminLink,
                'patient_name' => $pet->pet_name,
                'owner_name' => $patient_owner_full_name,
                'appointment_date' => $appointmentDate,
                'appointment_time' => $appointmentTime,
            ]);

            // Also publish to staff channel for all staff
            $ablyService->publishToStaff('appointment.canceled', [
                'appointment_id' => $appointment->id,
                'subject' => $adminSubject,
                'message' => $appointmentMessage,
                'link' => $adminLink,
                'patient_name' => $pet->pet_name,
                'owner_name' => $patient_owner_full_name,
                'appointment_date' => $appointmentDate,
                'appointment_time' => $appointmentTime,
            ]);

            // Send notification to client confirming cancellation
            if ($pet->user) {
                $clientLink = config('app.url') . '/appointments/' . $appointment->id;
                $clientSubject = 'Your appointment has been canceled';
                $clientMessage = "Hi {$patient_owner_full_name},<br><br>" .
                    "Your appointment has been successfully canceled.<br><br>" .
                    "Appointment Details:<br><br>" .
                    "Pet Name: {$pet->pet_name}<br>" .
                    "Appointment Type: {$appointmentTypeName}<br>" .
                    "Appointment Date: {$appointmentDate}<br>" .
                    "Appointment Time: {$appointmentTime}<br><br>" .
                    "If you need to schedule a new appointment, please visit our appointment booking page.<br><br>" .
                    "<p style='text-align:center'><a href='" . $clientLink . "' style='background-color: #4CAF50; border: none; color: white; padding: 15px 32px; text-align: center; text-decoration: none; font-size: 12px; border-radius: 15px;'>View Appointment</a></p>";

                // Send email notification to client
                if ($pet->user->email) {
                    Notification::route('mail', $pet->user->email)
                        ->notify(new \App\Notifications\ClientEmailNotification([
                            'subject' => $clientSubject,
                            'body' => $clientMessage,
                        ]));
                }

                // Send real-time notification to client via Ably
                $clientAppointmentMessage = "Your {$appointmentTypeName} appointment scheduled for {$appointmentDate} at {$appointmentTime} has been canceled";
                $ablyService->publishToUser($pet->user->id, 'appointment.canceled', [
                    'appointment_id' => $appointment->id,
                    'subject' => $clientSubject,
                    'message' => $clientAppointmentMessage,
                    'link' => $clientLink,
                    'patient_name' => $pet->pet_name,
                    'appointment_date' => $appointmentDate,
                    'appointment_time' => $appointmentTime,
                    'appointment_type' => $appointmentTypeName,
                ]);
            }

            if ($isApiRequest) {
                return response()->json(['message' => 'Appointment canceled successfully.']);
            }

            return redirect()->route('client.appointments.index')
                ->with('success', 'Appointment canceled successfully.');
        } catch (\Exception $e) {
            Log::error('Error canceling appointment: ' . $e->getMessage());
            
            if ($isApiRequest) {
                return response()->json(['error' => 'Failed to cancel appointment.'], 500);
            }
            
            return redirect()->route('client.appointments.show', $id)
                ->with('error', 'Failed to cancel appointment. Please try again.');
        }
    }

    /**
     * Reschedule a pending appointment.
     */
    public function rescheduleAppointment(Request $request, $id)
    {
        // Check if this is an API request (not Inertia)
        $isApiRequest = ($request->ajax() || $request->wantsJson()) && !$request->header('X-Inertia');
        
        try {
            $request->validate([
                'appointment_date' => 'required|date|after:today',
                'appointment_time' => 'required|string',
                'reschedule_reason' => 'required|string|in:Personal reason,Emergency,Health related,Booked incorrect date/time,Other/Prefer not to say',
            ]);

            $appointment = Appointment::where('id', $id)
                ->where(function ($query) {
                    $query->whereHas('patient', function ($q) {
                        $q->where('user_id', auth()->id());
                    })
                    ->orWhere('user_id', auth()->id());
                })
                ->with(['appointment_type', 'patient.petType', 'patient.user'])
                ->firstOrFail();

            // Only allow rescheduling appointments that are pending (not approved, not completed, not canceled)
            if ($appointment->is_canceled) {
                if ($isApiRequest) {
                    return response()->json(['error' => 'Canceled appointments cannot be rescheduled.'], 403);
                }
                return redirect()->route('client.appointments.show', $id)
                    ->with('error', 'Canceled appointments cannot be rescheduled.');
            }

            if ($appointment->is_completed) {
                if ($isApiRequest) {
                    return response()->json(['error' => 'Completed appointments cannot be rescheduled.'], 403);
                }
                return redirect()->route('client.appointments.show', $id)
                    ->with('error', 'Completed appointments cannot be rescheduled.');
            }

            if ($appointment->is_approved) {
                if ($isApiRequest) {
                    return response()->json(['error' => 'Only pending appointments can be rescheduled.'], 403);
                }
                return redirect()->route('client.appointments.show', $id)
                    ->with('error', 'Only pending appointments can be rescheduled.');
            }

            // Convert time from 12-hour format (h:i A) to 24-hour format (H:i)
            $time = Carbon::createFromFormat('h:i A', $request->appointment_time);

            // Validate timeslot restrictions (excluding current appointment)
            $this->validateTimeslotRestrictionsForReschedule(
                $request->appointment_date,
                $time->format('H:i'),
                $id
            );

            // Store old date and time for notification
            $oldDate = $appointment->appointment_date->format('Y-m-d');
            $oldTime = Carbon::createFromFormat('H:i', $appointment->appointment_time)->format('h:i A');

            // Update appointment date and time
            $appointment->update([
                'appointment_date' => $request->appointment_date,
                'appointment_time' => $time->format('H:i'), // Store in 24-hour format
                'is_approved' => false, // Reset approval status since it's a new time
                'reschedule_reason' => $request->reschedule_reason,
            ]);

            // Reload appointment with relationships including all patients
            $appointment->load('appointment_type', 'patient.petType', 'patient.user', 'patients.petType', 'patients.user');

            // Get all patients for this appointment
            $patients = $appointment->patients;
            if ($patients->isEmpty() && $appointment->patient) {
                $patients = collect([$appointment->patient]);
            }
            
            // Get the first patient's user for notifications (all pets should belong to the same user)
            $firstPatient = $patients->first();
            if (!$firstPatient || !$firstPatient->user) {
                if ($isApiRequest) {
                    return response()->json(['error' => 'Pet owner not found.'], 404);
                }
                return redirect()->route('client.appointments.show', $id)
                    ->with('error', 'Pet owner not found.');
            }

            $patient_owner_full_name = $firstPatient->user ? 
                trim(($firstPatient->user->first_name ?? '') . ' ' . ($firstPatient->user->last_name ?? '')) ?: $firstPatient->user->name : 'N/A';

            // Build pet details with their appointment types for admin/staff notifications
            $petDetailsHtml = '';
            $petNamesList = [];
            $allAppointmentTypes = [];
            
            foreach ($patients as $pet) {
                $petNamesList[] = $pet->pet_name ?? 'Unnamed Pet';
                
                // Get appointment types for this specific pet from the pivot table
                $petAppointmentTypes = \DB::table('appointment_patient')
                    ->where('appointment_id', $appointment->id)
                    ->where('patient_id', $pet->id)
                    ->join('appointment_types', 'appointment_patient.appointment_type_id', '=', 'appointment_types.id')
                    ->pluck('appointment_types.name')
                    ->toArray();
                
                // If no appointment types found in pivot, fallback to appointment's primary type
                if (empty($petAppointmentTypes)) {
                    if ($appointment->appointment_type) {
                        $petAppointmentTypes = [$appointment->appointment_type->name];
                    } else {
                        $petAppointmentTypes = ['N/A'];
                    }
                }
                
                $allAppointmentTypes = array_merge($allAppointmentTypes, $petAppointmentTypes);
                $appointmentTypesList = implode(', ', $petAppointmentTypes);
                
                $petDetailsHtml .= "Pet Name: " . ($pet->pet_name ?? 'Unnamed Pet') . "<br>" .
                    "Pet Type: " . ($pet->petType->name ?? 'N/A') . "<br>" .
                    "Breed: " . ($pet->pet_breed ?? 'N/A') . "<br>" .
                    "Appointment Type(s): {$appointmentTypesList}<br><br>";
            }
            
            $petNamesListText = implode(', ', $petNamesList);
            $allAppointmentTypes = array_unique($allAppointmentTypes);
            $appointmentTypeName = implode(', ', $allAppointmentTypes);
            if (empty($appointmentTypeName)) {
                $appointmentTypeName = 'N/A';
            }

            // Format dates and times for user-friendly display
            $oldDateFormatted = Carbon::createFromFormat('Y-m-d', $oldDate)->format('M d, Y');
            $oldTimeFormatted = $oldTime; // Already in 12-hour format
            $newTimeFormatted = Carbon::createFromFormat('H:i', $request->appointment_time)->format('h:i A');
            $newDateFormatted = Carbon::createFromFormat('Y-m-d', $request->appointment_date)->format('M d, Y');

            // Notify Super Admins
            $adminUsers = User::select('users.*')
                ->leftJoin('model_has_roles as mhr', 'mhr.model_id', 'users.id')
                ->leftJoin('roles', 'roles.id', 'mhr.role_id')
                ->where('roles.name', 'admin')
                ->distinct()
                ->get();

            // Notify Staff
            $staffUsers = User::select('users.*')
                ->leftJoin('model_has_roles as mhr', 'mhr.model_id', 'users.id')
                ->leftJoin('roles', 'roles.id', 'mhr.role_id')
                ->where('roles.name', 'staff')
                ->distinct()
                ->get();

            $link = config('app.url') . '/admin/appointments/' . $appointment->id;
            $subject = sprintf("%s has rescheduled an appointment.", $patient_owner_full_name ?? '');
            $message = "Hi, an appointment has been rescheduled<br><br>" .
                "Appointment Details.<br><br>" .
                "Full Name: " . $patient_owner_full_name . "<br>" .
                "Mobile Number: " . ($firstPatient->user ? ($firstPatient->user->mobile_number ?? 'N/A') : 'N/A') . "<br>" .
                "Email Address: " . ($firstPatient->user ? ($firstPatient->user->email ?? 'N/A') : 'N/A') . "<br><br>" .
                $petDetailsHtml .
                "Previous Date: " . $oldDateFormatted . "<br>" .
                "Previous Time: " . $oldTimeFormatted . "<br>" .
                "New Date: " . $newDateFormatted . "<br>" .
                "New Time: " . $newTimeFormatted . "<br>" .
                "<p style='text-align:center'><a href='" . $link . "' style='background-color: #4CAF50; border: none; color: white; padding: 15px 32px; text-align: center; text-decoration: none; font-size: 12px; border-radius: 15px;'>View Appointment</a></p>";

            $ablyService = app(AblyService::class);
            $appointmentMessage = $appointmentTypeName . ' appointment rescheduled from ' . $oldDateFormatted . ' at ' . $oldTimeFormatted . ' to ' . $newDateFormatted . ' at ' . $newTimeFormatted;

            // Send notifications via database, email, and Ably to admins
            foreach ($adminUsers as $user) {
                $user->notify(new \App\Notifications\DefaultNotification($subject, $message, $link));
                
                // Send real-time notification via Ably
                $ablyService->publishToUser($user->id, 'appointment.rescheduled', [
                    'appointment_id' => $appointment->id,
                    'subject' => $subject,
                    'message' => $appointmentMessage,
                    'link' => $link,
                    'patient_name' => count($petNamesList) > 1 ? $petNamesListText : ($petNamesList[0] ?? 'N/A'),
                    'owner_name' => $patient_owner_full_name,
                    'old_date' => $oldDateFormatted,
                    'old_time' => $oldTimeFormatted,
                    'new_date' => $newDateFormatted,
                    'new_time' => $newTimeFormatted,
                ]);
            }

            // Send notifications via database and Ably to staff
            foreach ($staffUsers as $user) {
                $user->notify(new \App\Notifications\DefaultNotification($subject, $message, $link));
                
                // Send real-time notification via Ably
                $ablyService->publishToUser($user->id, 'appointment.rescheduled', [
                    'appointment_id' => $appointment->id,
                    'subject' => $subject,
                    'message' => $appointmentMessage,
                    'link' => $link,
                    'patient_name' => count($petNamesList) > 1 ? $petNamesListText : ($petNamesList[0] ?? 'N/A'),
                    'owner_name' => $patient_owner_full_name,
                    'old_date' => $oldDateFormatted,
                    'old_time' => $oldTimeFormatted,
                    'new_date' => $newDateFormatted,
                    'new_time' => $newTimeFormatted,
                ]);
            }

            // Also publish to admin channel for all admins
            $ablyService->publishToAdmins('appointment.rescheduled', [
                'appointment_id' => $appointment->id,
                'subject' => $subject,
                'message' => $appointmentMessage,
                'link' => $link,
                'patient_name' => count($petNamesList) > 1 ? $petNamesListText : ($petNamesList[0] ?? 'N/A'),
                'owner_name' => $patient_owner_full_name,
                'old_date' => $oldDateFormatted,
                'old_time' => $oldTimeFormatted,
                'new_date' => $newDateFormatted,
                'new_time' => $newTimeFormatted,
            ]);

            // Also publish to staff channel for all staff
            $ablyService->publishToStaff('appointment.rescheduled', [
                'appointment_id' => $appointment->id,
                'subject' => $subject,
                'message' => $appointmentMessage,
                'link' => $link,
                'patient_name' => count($petNamesList) > 1 ? $petNamesListText : ($petNamesList[0] ?? 'N/A'),
                'owner_name' => $patient_owner_full_name,
                'old_date' => $oldDateFormatted,
                'old_time' => $oldTimeFormatted,
                'new_date' => $newDateFormatted,
                'new_time' => $newTimeFormatted,
            ]);

            // Send notification to client confirming reschedule
            if ($firstPatient->user) {
                $clientLink = config('app.url') . '/appointments/' . $appointment->id;
                $clientSubject = 'Your appointment has been rescheduled';
                $rescheduleReason = $request->reschedule_reason;
                
                // Build pet details HTML for client email
                $clientPetDetailsHtml = '';
                foreach ($patients as $pet) {
                    // Get appointment types for this specific pet from the pivot table
                    $petAppointmentTypes = \DB::table('appointment_patient')
                        ->where('appointment_id', $appointment->id)
                        ->where('patient_id', $pet->id)
                        ->join('appointment_types', 'appointment_patient.appointment_type_id', '=', 'appointment_types.id')
                        ->pluck('appointment_types.name')
                        ->toArray();
                    
                    // If no appointment types found in pivot, fallback to appointment's primary type
                    if (empty($petAppointmentTypes)) {
                        if ($appointment->appointment_type) {
                            $petAppointmentTypes = [$appointment->appointment_type->name];
                        } else {
                            $petAppointmentTypes = ['N/A'];
                        }
                    }
                    
                    $appointmentTypesList = implode(', ', $petAppointmentTypes);
                    $clientPetDetailsHtml .= "Pet Name: " . ($pet->pet_name ?? 'Unnamed Pet') . "<br>" .
                        "Appointment Type(s): {$appointmentTypesList}<br><br>";
                }
                
                $clientMessage = "Hi {$patient_owner_full_name},<br><br>" .
                    "Your appointment has been successfully rescheduled.<br><br>" .
                    "Reason for Rescheduling: {$rescheduleReason}<br><br>" .
                    "Appointment Details:<br><br>" .
                    $clientPetDetailsHtml .
                    "Previous Date: {$oldDateFormatted}<br>" .
                    "Previous Time: {$oldTimeFormatted}<br>" .
                    "New Date: {$newDateFormatted}<br>" .
                    "New Time: {$newTimeFormatted}<br><br>" .
                    "Please note that your appointment status has been reset to pending and will need to be approved again.<br><br>" .
                    "<p style='text-align:center'><a href='" . $clientLink . "' style='background-color: #4CAF50; border: none; color: white; padding: 15px 32px; text-align: center; text-decoration: none; font-size: 12px; border-radius: 15px;'>View Appointment</a></p>";

                // Send email notification to client
                if ($firstPatient->user->email) {
                    Notification::route('mail', $firstPatient->user->email)
                        ->notify(new \App\Notifications\ClientEmailNotification([
                            'subject' => $clientSubject,
                            'body' => $clientMessage,
                        ]));
                }

                // Send database notification (in-app notification) to client
                $databaseMessage = "Your {$appointmentTypeName} appointment has been rescheduled from {$oldDateFormatted} at {$oldTimeFormatted} to {$newDateFormatted} at {$newTimeFormatted}.";
                $firstPatient->user->notify(new \App\Notifications\DatabaseNotification($clientSubject, $databaseMessage, $clientLink));

                // Send real-time notification to client via Ably
                $clientAppointmentMessage = "Your {$appointmentTypeName} appointment has been rescheduled from {$oldDateFormatted} at {$oldTimeFormatted} to {$newDateFormatted} at {$newTimeFormatted}";
                $ablyService->publishToUser($firstPatient->user->id, 'appointment.rescheduled', [
                    'appointment_id' => $appointment->id,
                    'subject' => $clientSubject,
                    'message' => $clientAppointmentMessage,
                    'link' => $clientLink,
                    'patient_name' => count($petNamesList) > 1 ? $petNamesListText : ($petNamesList[0] ?? 'N/A'),
                    'old_date' => $oldDateFormatted,
                    'old_time' => $oldTimeFormatted,
                    'new_date' => $newDateFormatted,
                    'new_time' => $newTimeFormatted,
                    'appointment_type' => $appointmentTypeName,
                ]);
            }

            if ($isApiRequest) {
                return response()->json(['message' => 'Appointment rescheduled successfully.']);
            }

            return redirect()->route('client.appointments.show', $id)
                ->with('success', 'Appointment rescheduled successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($isApiRequest) {
                return response()->json(['errors' => $e->errors()], 422);
            }
            throw $e;
        } catch (\Exception $e) {
            Log::error('Error rescheduling appointment: ' . $e->getMessage());
            
            if ($isApiRequest) {
                return response()->json(['error' => 'Failed to reschedule appointment: ' . $e->getMessage()], 500);
            }
            
            return redirect()->route('client.appointments.show', $id)
                ->with('error', 'Failed to reschedule appointment. Please try again.');
        }
    }

    /**
     * Validate timeslot restrictions for rescheduling (excluding current appointment).
     */
    private function validateTimeslotRestrictionsForReschedule($date, $time, $excludeAppointmentId)
    {
        $restrictions = $this->getTimeslotRestrictions();
        $timeCarbon = Carbon::createFromFormat('H:i', $time);
        $time12Hour = $timeCarbon->format('h:i A');

        // Check if date is disabled (from database)
        $isDisabled = DisabledDate::where('date', $date)->exists();
        if ($isDisabled) {
            throw new \Illuminate\Validation\ValidationException(
                validator([], []),
                ['appointment_date' => ['This date is not available for booking. The veterinarian is not available on this date.']]
            );
        }

        // Check if date is a Philippine holiday
        if (PhilippineHolidays::isHoliday($date)) {
            throw new \Illuminate\Validation\ValidationException(
                validator([], []),
                ['appointment_date' => ['This date is not available for booking. The clinic is closed on this holiday.']]
            );
        }

        // Check minimum notice time
        $appointmentDateTime = Carbon::parse($date . ' ' . $time);
        $minimumNotice = Carbon::now()->addHours($restrictions['minimum_notice_hours']);
        if ($appointmentDateTime->lt($minimumNotice)) {
            throw new \Illuminate\Validation\ValidationException(
                validator([], []),
                ['appointment_time' => ['Appointments must be rescheduled at least ' . $restrictions['minimum_notice_hours'] . ' hours in advance.']]
            );
        }

        // Check working hours
        if (!$this->isWithinWorkingHours($time12Hour, $restrictions)) {
            throw new \Illuminate\Validation\ValidationException(
                validator([], []),
                ['appointment_time' => ['Appointments can only be rescheduled during working hours.']]
            );
        }

        // Check lunch break
        if ($this->isDuringLunchBreak($time12Hour, $restrictions)) {
            throw new \Illuminate\Validation\ValidationException(
                validator([], []),
                ['appointment_time' => ['Appointments cannot be rescheduled during lunch break.']]
            );
        }

        // Check if already booked (excluding current appointment)
        $existing = Appointment::where('appointment_date', $date)
            ->where('appointment_time', $time)
            ->where('id', '!=', $excludeAppointmentId)
            ->exists();
        if ($existing) {
            throw new \Illuminate\Validation\ValidationException(
                validator([], []),
                ['appointment_time' => ['This time slot is already booked.']]
            );
        }

        // Check daily limit (excluding current appointment)
        $dailyCount = Appointment::where('appointment_date', $date)
            ->where('id', '!=', $excludeAppointmentId)
            ->count();
        if ($dailyCount >= $restrictions['max_appointments_per_day']) {
            throw new \Illuminate\Validation\ValidationException(
                validator([], []),
                ['appointment_date' => ['Maximum number of appointments for this day has been reached.']]
            );
        }

        // Check buffer time (excluding current appointment)
        $bookedTimes = Appointment::where('appointment_date', $date)
            ->where('id', '!=', $excludeAppointmentId)
            ->pluck('appointment_time')
            ->map(function ($t) {
                return Carbon::createFromFormat('H:i', $t)->format('h:i A');
            })
            ->toArray();
        if ($this->violatesBufferTime($time12Hour, $bookedTimes, $restrictions)) {
            throw new \Illuminate\Validation\ValidationException(
                validator([], []),
                ['appointment_time' => ['This time slot violates the buffer time restriction.']]
            );
        }
    }

    /**
     * Get available time slots for a given date.
     */
    public function getAvailableTimes(Request $request)
    {
        $request->validate([
            'selectedDate' => 'required|date|after:today',
        ]);

        $selectedDate = $request->input('selectedDate');
        $restrictions = $this->getTimeslotRestrictions();

        // Check if date is disabled (from database)
        $isDisabled = DisabledDate::where('date', $selectedDate)->exists();
        if ($isDisabled) {
            return response()->json([
                'availableTimes' => [],
                'disabledTimes' => [],
                'isDateDisabled' => true,
                'message' => 'This date is not available for booking. The veterinarian is not available on this date.',
            ]);
        }

        // Check if date is a Philippine holiday
        if (PhilippineHolidays::isHoliday($selectedDate)) {
            return response()->json([
                'availableTimes' => [],
                'disabledTimes' => [],
                'isDateDisabled' => true,
                'message' => 'This date is not available for booking. The clinic is closed on this holiday.',
            ]);
        }

        // Get all booked times for the selected date
        $bookedTimes = Appointment::where('appointment_date', $selectedDate)
            ->pluck('appointment_time')
            ->map(function ($time) {
                return Carbon::createFromFormat('H:i', $time)->format('h:i A');
            })
            ->toArray();

        // Generate all possible time slots
        $allTimeSlots = $this->generateTimeSlots($restrictions);

        // Filter out unavailable slots
        $availableSlots = [];
        foreach ($allTimeSlots as $slot) {
            // Check if slot is within working hours
            if (!$this->isWithinWorkingHours($slot, $restrictions)) {
                continue;
            }

            // Check if slot is during lunch break
            if ($this->isDuringLunchBreak($slot, $restrictions)) {
                continue;
            }

            // Check if slot is already booked
            if (in_array($slot, $bookedTimes)) {
                continue;
            }

            // Check if slot violates buffer time
            if ($this->violatesBufferTime($slot, $bookedTimes, $restrictions)) {
                continue;
            }

            // Check daily appointment limit
            $dailyCount = Appointment::where('appointment_date', $selectedDate)->count();
            if ($dailyCount >= $restrictions['max_appointments_per_day']) {
                continue;
            }

            $availableSlots[] = $slot;
        }

        return response()->json([
            'availableTimes' => $availableSlots,
            'disabledTimes' => $bookedTimes,
        ]);
    }

    /**
     * Validate timeslot restrictions before booking.
     */
    private function validateTimeslotRestrictions($date, $time)
    {
        $restrictions = $this->getTimeslotRestrictions();
        $timeCarbon = Carbon::createFromFormat('H:i', $time);
        $time12Hour = $timeCarbon->format('h:i A');

        // Check if date is disabled (from database)
        $isDisabled = DisabledDate::where('date', $date)->exists();
        if ($isDisabled) {
            throw new \Illuminate\Validation\ValidationException(
                validator([], []),
                ['appointment_date' => ['This date is not available for booking. The veterinarian is not available on this date.']]
            );
        }

        // Check if date is a Philippine holiday
        if (PhilippineHolidays::isHoliday($date)) {
            throw new \Illuminate\Validation\ValidationException(
                validator([], []),
                ['appointment_date' => ['This date is not available for booking. The clinic is closed on this holiday.']]
            );
        }

        // Check minimum notice time
        $appointmentDateTime = Carbon::parse($date . ' ' . $time);
        $minimumNotice = Carbon::now()->addHours($restrictions['minimum_notice_hours']);
        if ($appointmentDateTime->lt($minimumNotice)) {
            throw new \Illuminate\Validation\ValidationException(
                validator([], []),
                ['appointment_time' => ['Appointments must be booked at least ' . $restrictions['minimum_notice_hours'] . ' hours in advance.']]
            );
        }

        // Check working hours
        if (!$this->isWithinWorkingHours($time12Hour, $restrictions)) {
            throw new \Illuminate\Validation\ValidationException(
                validator([], []),
                ['appointment_time' => ['Appointments can only be booked during working hours.']]
            );
        }

        // Check lunch break
        if ($this->isDuringLunchBreak($time12Hour, $restrictions)) {
            throw new \Illuminate\Validation\ValidationException(
                validator([], []),
                ['appointment_time' => ['Appointments cannot be booked during lunch break.']]
            );
        }

        // Check if already booked
        $existing = Appointment::where('appointment_date', $date)
            ->where('appointment_time', $time)
            ->exists();
        if ($existing) {
            throw new \Illuminate\Validation\ValidationException(
                validator([], []),
                ['appointment_time' => ['This time slot is already booked.']]
            );
        }

        // Check daily limit
        $dailyCount = Appointment::where('appointment_date', $date)->count();
        if ($dailyCount >= $restrictions['max_appointments_per_day']) {
            throw new \Illuminate\Validation\ValidationException(
                validator([], []),
                ['appointment_date' => ['Maximum number of appointments for this day has been reached.']]
            );
        }

        // Check buffer time
        $bookedTimes = Appointment::where('appointment_date', $date)
            ->pluck('appointment_time')
            ->map(function ($t) {
                return Carbon::createFromFormat('H:i', $t)->format('h:i A');
            })
            ->toArray();
        if ($this->violatesBufferTime($time12Hour, $bookedTimes, $restrictions)) {
            throw new \Illuminate\Validation\ValidationException(
                validator([], []),
                ['appointment_time' => ['This time slot violates the buffer time restriction.']]
            );
        }
    }

    /**
     * Get timeslot restrictions configuration.
     */
    private function getTimeslotRestrictions()
    {
        return [
            'working_hours_start' => config('appointments.working_hours_start', '09:00'),
            'working_hours_end' => config('appointments.working_hours_end', '16:30'),
            'lunch_break_start' => config('appointments.lunch_break_start', '12:00'),
            'lunch_break_end' => config('appointments.lunch_break_end', '13:00'),
            'slot_duration_minutes' => config('appointments.slot_duration_minutes', 30),
            'buffer_time_minutes' => config('appointments.buffer_time_minutes', 15),
            'max_appointments_per_day' => config('appointments.max_appointments_per_day', 10),
            'minimum_notice_hours' => config('appointments.minimum_notice_hours', 24),
        ];
    }

    /**
     * Generate all possible time slots based on restrictions.
     */
    private function generateTimeSlots($restrictions)
    {
        $slots = [];
        $start = Carbon::createFromFormat('H:i', $restrictions['working_hours_start']);
        $end = Carbon::createFromFormat('H:i', $restrictions['working_hours_end']);
        $duration = $restrictions['slot_duration_minutes'];

        $current = $start->copy();
        while ($current->lte($end)) {
            $slots[] = $current->format('h:i A');
            $current->addMinutes($duration);
        }

        return $slots;
    }

    /**
     * Check if time slot is within working hours.
     */
    private function isWithinWorkingHours($time12Hour, $restrictions)
    {
        $time = Carbon::createFromFormat('h:i A', $time12Hour);
        $start = Carbon::createFromFormat('H:i', $restrictions['working_hours_start']);
        $end = Carbon::createFromFormat('H:i', $restrictions['working_hours_end']);

        return $time->gte($start) && $time->lte($end);
    }

    /**
     * Check if time slot is during lunch break.
     */
    private function isDuringLunchBreak($time12Hour, $restrictions)
    {
        $time = Carbon::createFromFormat('h:i A', $time12Hour);
        $lunchStart = Carbon::createFromFormat('H:i', $restrictions['lunch_break_start']);
        $lunchEnd = Carbon::createFromFormat('H:i', $restrictions['lunch_break_end']);

        return $time->gte($lunchStart) && $time->lt($lunchEnd);
    }

    /**
     * Check if time slot violates buffer time with existing appointments.
     */
    private function violatesBufferTime($time12Hour, $bookedTimes, $restrictions)
    {
        $time = Carbon::createFromFormat('h:i A', $time12Hour);
        $bufferMinutes = $restrictions['buffer_time_minutes'];

        foreach ($bookedTimes as $bookedTime) {
            $booked = Carbon::createFromFormat('h:i A', $bookedTime);
            $diffMinutes = abs($time->diffInMinutes($booked));

            if ($diffMinutes < $bufferMinutes) {
                return true;
            }
        }

        return false;
    }

    /**
     * Display a listing of the client's pets.
     */
    public function pets(Request $request)
    {
        $query = Patient::where('user_id', auth()->id())
            ->with('petType');

        // Search functionality
        if ($request->has('search') && !empty($request->search)) {
            $keyword = $request->search;
            $query->where(function ($q) use ($keyword) {
                $q->where('pet_name', 'LIKE', "%{$keyword}%")
                    ->orWhere('pet_breed', 'LIKE', "%{$keyword}%")
                    ->orWhereHas('petType', function ($q) use ($keyword) {
                        $q->where('name', 'LIKE', "%{$keyword}%");
                    });
            });
        }

        // Sort functionality
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        
        // Validate sort_by to prevent SQL injection
        $allowedSortColumns = ['pet_name', 'pet_breed', 'pet_gender', 'created_at'];
        if (!in_array($sortBy, $allowedSortColumns)) {
            $sortBy = 'created_at';
        }
        
        // Validate sort_direction
        $sortDirection = strtolower($sortDirection) === 'asc' ? 'asc' : 'desc';
        
        $query->orderBy($sortBy, $sortDirection);

        $pets = $query->paginate(15);

        // Transform the data for Inertia
        $pets->getCollection()->transform(function ($pet) {
            return [
                'id' => $pet->id,
                'pet_name' => $pet->pet_name,
                'pet_breed' => $pet->pet_breed,
                'pet_gender' => $pet->pet_gender,
                'pet_birth_date' => $pet->pet_birth_date ? $pet->pet_birth_date->toDateString() : null,
                'pet_allergies' => $pet->pet_allergies,
                'pet_type' => [
                    'id' => $pet->petType->id ?? null,
                    'name' => $pet->petType->name ?? null,
                ],
                'created_at' => $pet->created_at->toISOString(),
            ];
        });

        $pet_types = PetType::all()->map(function ($pet_type) {
            return [
                'id' => $pet_type->id,
                'name' => $pet_type->name,
            ];
        });

        return Inertia::render('Client/Pets/Index', [
            'pets' => $pets,
            'pet_types' => $pet_types,
            'filters' => $request->only(['search', 'sort_by', 'sort_direction']),
        ]);
    }

    /**
     * Show the form for creating a new pet.
     */
    public function createPet()
    {
        $pet_types = PetType::all()->map(function ($pet_type) {
            return [
                'id' => $pet_type->id,
                'name' => $pet_type->name,
            ];
        });

        // Build pet breeds mapping from database
        $pet_breeds = [];
        foreach ($pet_types as $pet_type) {
            $breeds = PetBreed::where('pet_type_id', $pet_type['id'])
                ->orderBy('name')
                ->pluck('name')
                ->toArray();
            $pet_breeds[$pet_type['name']] = $breeds;
        }

        return Inertia::render('Client/Pets/Create', [
            'pet_types' => $pet_types,
            'pet_breeds' => $pet_breeds,
        ]);
    }

    /**
     * Store a newly created pet.
     */
    public function storePet(Request $request)
    {
        $validated = $request->validate([
            'pet_type_id' => 'required_without:custom_pet_type_name',
            'custom_pet_type_name' => 'nullable|string|max:100',
            'pet_name' => 'nullable|string|max:100',
            'pet_breed' => 'required_without:custom_pet_breed_name|nullable|string|max:100',
            'custom_pet_breed_name' => 'nullable|string|max:100',
            'pet_gender' => 'nullable|in:Male,Female',
            'pet_birth_date' => 'nullable|date',
            'pet_allergies' => 'nullable|string',
        ]);

        // Validate pet_type_id exists if not creating new
        if (!empty($validated['pet_type_id']) && $validated['pet_type_id'] !== '__new__') {
            $exists = PetType::where('id', $validated['pet_type_id'])->exists();
            if (!$exists) {
                return back()->withErrors(['pet_type_id' => 'The selected pet type is invalid.'])->withInput();
            }
        }

        return DB::transaction(function () use ($validated, $request) {
            // Handle custom pet type creation
            $petTypeId = $validated['pet_type_id'];
            if (!empty($validated['custom_pet_type_name']) && ($petTypeId === '__new__' || empty($petTypeId))) {
                // Check if pet type with this name already exists (case-insensitive)
                $existingPetType = PetType::whereRaw('LOWER(name) = ?', [strtolower($validated['custom_pet_type_name'])])->first();
                
                if ($existingPetType) {
                    $petTypeId = $existingPetType->id;
                } else {
                    // Create new pet type
                    $newPetType = PetType::create([
                        'name' => ucfirst($validated['custom_pet_type_name']),
                    ]);
                    $petTypeId = $newPetType->id;
                }
            }

            // Handle custom breed creation
            $petBreed = $validated['pet_breed'];
            if (!empty($validated['custom_pet_breed_name']) && ($petBreed === '__new__' || empty($petBreed))) {
                $breedName = ucfirst($validated['custom_pet_breed_name']);
                
                // Check if breed with this name already exists for this pet type (case-insensitive)
                $existingBreed = PetBreed::where('pet_type_id', $petTypeId)
                    ->whereRaw('LOWER(name) = ?', [strtolower($breedName)])
                    ->first();
                
                if (!$existingBreed) {
                    // Create new breed
                    PetBreed::create([
                        'name' => $breedName,
                        'pet_type_id' => $petTypeId,
                    ]);
                }
                
                $petBreed = $breedName;
            }

            $patient = Patient::create([
                'pet_type_id' => $petTypeId,
                'pet_name' => $validated['pet_name'] ?? null,
                'pet_breed' => $petBreed,
                'pet_gender' => $validated['pet_gender'] ?? null,
                'pet_birth_date' => $validated['pet_birth_date'] ?? null,
                'pet_allergies' => $validated['pet_allergies'] ?? null,
                'user_id' => auth()->id(), // Automatically assign to authenticated user
            ]);

            // Load relationships for response
            $patient->load('petType');

            // Check if it's an API request (not Inertia)
            $isApiRequest = ($request->ajax() || $request->wantsJson()) && !$request->header('X-Inertia');
            
            if ($isApiRequest) {
                return response()->json([
                    'success' => true,
                    'message' => 'Pet registered successfully.',
                    'pet' => [
                        'id' => $patient->id,
                        'pet_name' => $patient->pet_name,
                        'pet_type' => $patient->petType->name ?? 'N/A',
                    ],
                ]);
            }

            return redirect()->route('client.pets.index')
                ->with('message', 'Pet registered successfully.');
        });
    }

    /**
     * Show the form for editing the specified pet.
     */
    public function editPet(Patient $pet)
    {
        // Verify the pet belongs to the authenticated user
        if ($pet->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }

        $pet_types = PetType::all()->map(function ($pet_type) {
            return [
                'id' => $pet_type->id,
                'name' => $pet_type->name,
            ];
        });

        // Build pet breeds mapping from database
        $pet_breeds = [];
        foreach ($pet_types as $pet_type) {
            $breeds = PetBreed::where('pet_type_id', $pet_type['id'])
                ->orderBy('name')
                ->pluck('name')
                ->toArray();
            $pet_breeds[$pet_type['name']] = $breeds;
        }

        return Inertia::render('Client/Pets/Edit', [
            'pet' => [
                'id' => $pet->id,
                'pet_type_id' => $pet->pet_type_id,
                'pet_name' => $pet->pet_name,
                'pet_breed' => $pet->pet_breed,
                'pet_gender' => $pet->pet_gender,
                'pet_birth_date' => $pet->pet_birth_date ? $pet->pet_birth_date->toDateString() : null,
                'pet_allergies' => $pet->pet_allergies,
            ],
            'pet_types' => $pet_types,
            'pet_breeds' => $pet_breeds,
        ]);
    }

    /**
     * Update the specified pet.
     */
    public function updatePet(Request $request, Patient $pet)
    {
        // Verify the pet belongs to the authenticated user
        if ($pet->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'pet_type_id' => 'required|exists:pet_types,id',
            'pet_name' => 'nullable|string|max:100',
            'pet_breed' => 'required|string|max:100',
            'pet_gender' => 'nullable|in:Male,Female',
            'pet_birth_date' => 'nullable|date',
            'pet_allergies' => 'nullable|string',
        ]);

        $pet->update([
            'pet_type_id' => $validated['pet_type_id'],
            'pet_name' => $validated['pet_name'] ?? null,
            'pet_breed' => $validated['pet_breed'],
            'pet_gender' => $validated['pet_gender'] ?? null,
            'pet_birth_date' => $validated['pet_birth_date'] ?? null,
            'pet_allergies' => $validated['pet_allergies'] ?? null,
        ]);

        return redirect()->route('client.pets.index')
            ->with('message', 'Pet updated successfully.');
    }

    /**
     * Remove the specified pet from storage.
     */
    public function destroyPet(Patient $pet)
    {
        // Verify the pet belongs to the authenticated user
        if ($pet->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }

        $pet->delete();

        return redirect()->route('client.pets.index')
            ->with('message', 'Pet deleted successfully.');
    }
}