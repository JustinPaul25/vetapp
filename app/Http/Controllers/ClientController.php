<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\Patient;
use App\Models\PetType;
use App\Models\User;
use App\Services\AblyService;
use App\Services\AppointmentLimitService;
use App\Constants\Components\PetBreeds;
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

            $appointments = Appointment::select(
                'appointments.*',
                DB::raw("IF(appointments.is_canceled = 1, 'Canceled', IF(appointments.is_approved = 0, 'Pending', IF(appointments.is_completed = 1, 'Completed', 'Approved'))) as status")
            )
                ->leftJoin('prescriptions', 'prescriptions.appointment_id', 'appointments.id')
                ->leftJoin('prescription_diagnoses', 'prescription_diagnoses.prescription_id', 'prescriptions.id')
                ->leftJoin('diseases', 'diseases.id', 'prescription_diagnoses.disease_id')
                ->with(['appointment_types', 'patients.petType']) // Load many-to-many relationships
                ->where('appointments.user_id', auth()->id());

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

            if (!empty($keyword)) {
                $appointments->where(function ($q) use ($keyword) {
                    $q->where('diseases.name', 'LIKE', "%{$keyword}%")
                        ->orWhereHas('patients', function ($patientQuery) use ($keyword) {
                            $patientQuery->where('patients.pet_name', 'LIKE', "%{$keyword}%")
                                ->orWhereHas('petType', function ($typeQuery) use ($keyword) {
                                    $typeQuery->where('pet_types.name', 'LIKE', "%{$keyword}%");
                                });
                        });
                });
            }

            $appointments = $appointments->orderBy('appointments.appointment_date', 'desc')
                ->orderBy('appointments.appointment_time', 'desc')
                ->get();

            return response()->json([
                'data' => $appointments->map(function ($appointment) {
                    // Get all appointment types as comma-separated string
                    $appointmentTypes = $appointment->appointment_types->pluck('name')->join(', ') 
                                      ?: ($appointment->appointment_type->name ?? 'N/A');
                    
                    // Get all pets for this appointment
                    $pets = $appointment->patients;
                    $petNames = $pets->pluck('pet_name')->join(', ');
                    $petTypes = $pets->map(function ($pet) {
                        return $pet->petType->name ?? 'N/A';
                    })->unique()->join(', ');
                    
                    return [
                        'id' => $appointment->id,
                        'appointment_type' => $appointmentTypes,
                        'pet_type' => $petTypes ?: 'N/A',
                        'pet_name' => $petNames ?: 'N/A',
                        'appointment_date' => $appointment->appointment_date ? $appointment->appointment_date->format('Y-m-d') : null,
                        'appointment_time' => $appointment->appointment_time,
                        'status' => $appointment->status,
                    ];
                }),
            ]);
        }

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
        ]);
    }

    /**
     * Store a newly created appointment.
     */
    public function bookAppointment(Request $request)
    {
        $request->validate([
            'pet_ids' => 'required|array|min:1',
            'pet_ids.*' => 'required|exists:patients,id',
            'appointment_type_ids' => 'required|array|min:1',
            'appointment_type_ids.*' => 'required|exists:appointment_types,id',
            'appointment_date' => 'required|date|after:today',
            'appointment_times' => 'required|array|min:1',
            'appointment_times.*' => 'required|string',
            'symptoms' => 'nullable|string|max:1825',
        ]);

        // Verify all pets belong to the authenticated user
        $petIds = $request->pet_ids;
        $pets = Patient::whereIn('id', $petIds)
            ->where('user_id', auth()->id())
            ->get();
        
        if ($pets->count() !== count($petIds)) {
            return back()->withErrors(['pet_ids' => 'One or more pets do not belong to you.']);
        }

        // Validate that number of time slots matches number of pets
        $appointmentTimes = $request->appointment_times;
        if (count($appointmentTimes) !== count($petIds)) {
            return back()->withErrors(['appointment_times' => 'The number of time slots must match the number of pets selected.']);
        }

        // Get appointment type IDs array
        $appointmentTypeIds = $request->appointment_type_ids;
        
        // Use first appointment type ID for backward compatibility (appointment_type_id column)
        $firstAppointmentTypeId = is_array($appointmentTypeIds) ? $appointmentTypeIds[0] : $appointmentTypeIds;

        // Validate daily appointment limits
        $limitService = app(AppointmentLimitService::class);
        $numberOfAppointments = count($petIds);
        
        // Check if we have enough slots for all appointments being created
        foreach ($appointmentTypeIds as $typeId) {
            $limitCheck = $limitService->checkDailyLimit($typeId, $request->appointment_date);
            
            if (!$limitCheck['available'] || $limitCheck['remaining'] < $numberOfAppointments) {
                $appointmentType = AppointmentType::find($typeId);
                $typeName = $appointmentType ? $appointmentType->name : 'Unknown';
                $remaining = $limitCheck['remaining'];
                
                return back()->withErrors([
                    'appointment_date' => sprintf(
                        'Daily limit reached for %s appointments. Only %d slot(s) remaining, but %d appointment(s) requested.',
                        $typeName,
                        $remaining,
                        $numberOfAppointments
                    ),
                ]);
            }
        }

        // Create one appointment per pet-time combination
        $createdAppointments = [];
        
        foreach ($petIds as $index => $petId) {
            $timeString = $appointmentTimes[$index];
            
            // Convert time from 12-hour format (h:i A) to 24-hour format (H:i)
            $time = Carbon::createFromFormat('h:i A', $timeString);

            // Validate timeslot restrictions
            $this->validateTimeslotRestrictions($request->appointment_date, $time->format('H:i'));

            // Create appointment (initially not approved)
            $appointment = Appointment::create([
                'patient_id' => $petId, // Keep for backward compatibility
                'appointment_type_id' => $firstAppointmentTypeId, // Keep for backward compatibility
                'appointment_date' => $request->appointment_date,
                'symptoms' => $request->symptoms ?? '',
                'is_approved' => false, // Client appointments start as pending
                'appointment_time' => $time->format('H:i'), // Store in 24-hour format
                'user_id' => Auth::id(),
            ]);

            // Sync many-to-many relationship for multiple appointment types
            $appointment->appointment_types()->sync($appointmentTypeIds);
            
            // Sync many-to-many relationship for single patient (this appointment is for one pet)
            $appointment->patients()->sync([$petId]);
            
            $createdAppointments[] = $appointment;
        }
        
        // Use first appointment for notifications and relationships loading
        $appointment = $createdAppointments[0];

        // Reload appointment with relationships
        $appointment->load('appointment_type', 'appointment_types', 'patient.petType', 'patient.user', 'patients.petType', 'patients.user');

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

        // Get first pet for owner info (all pets should belong to same user)
        $firstPet = $pets->first();
        $patient_owner_full_name = trim(($firstPet->user->first_name ?? '') . ' ' . ($firstPet->user->last_name ?? '')) ?: $firstPet->user->name;
        
        // Get all appointment type names
        $appointmentTypeNames = $appointment->appointment_types->pluck('name')->join(', ') ?: 
                               ($appointment->appointment_type->name ?? 'N/A');
        
        // Get all pet names
        $petNames = $pets->pluck('pet_name')->join(', ');

        // Build appointment times string for notification
        $appointmentTimesString = collect($appointmentTimes)->map(function($time) {
            return $time;
        })->join(', ');

        $ablyService = app(AblyService::class);
        
        // Send notifications for each appointment
        foreach ($createdAppointments as $appointment) {
            $appointment->load('appointment_type', 'appointment_types', 'patient.petType', 'patient.user');
            
            $petName = $appointment->patient->pet_name;
            $appointmentTime = Carbon::createFromFormat('H:i', $appointment->appointment_time)->format('h:i A');
            
            $link = config('app.url') . '/admin/appointments/' . $appointment->id;
            $subject = sprintf("%s has submitted new appointment.", $patient_owner_full_name ?? '');
            $message = "Hi, new appointment has been submitted<br><br>" .
                "Appointment Details.<br><br>" .
                "Full Name: " . $patient_owner_full_name . "<br>" .
                "Mobile Number: " . ($firstPet->user->mobile_number ?? 'N/A') . "<br>" .
                "Email Address: " . ($firstPet->user->email ?? 'N/A') . "<br>" .
                "Pet: " . $petName . "<br>" .
                "Appointment Type: " . $appointmentTypeNames . "<br>" .
                "Appointment Date: " . $request->appointment_date . "<br>" .
                "Appointment Time: " . $appointmentTime . "<br>" .
                "<p style='text-align:center'><a href='" . $link . "' style='background-color: #4CAF50; border: none; color: white; padding: 15px 32px; text-align: center; text-decoration: none; font-size: 12px; border-radius: 15px;'>View Appointment</a></p>";

            $appointmentMessage = $appointmentTypeNames . ' appointment scheduled for ' . $request->appointment_date . ' at ' . $appointmentTime . ' for ' . $petName;

            // Send notifications via database, email, and Ably to admins
            foreach ($adminUsers as $user) {
                $user->notify(new \App\Notifications\DefaultNotification($subject, $message, $link));
                
                // Send real-time notification via Ably
                $ablyService->publishToUser($user->id, 'appointment.created', [
                    'appointment_id' => $appointment->id,
                    'subject' => $subject,
                    'message' => $appointmentMessage,
                    'link' => $link,
                    'patient_name' => $petName,
                    'owner_name' => $patient_owner_full_name,
                ]);
            }

            // Send real-time notifications via Ably to staff
            foreach ($staffUsers as $user) {
                $ablyService->publishToUser($user->id, 'appointment.created', [
                    'appointment_id' => $appointment->id,
                    'subject' => $subject,
                    'message' => $appointmentMessage,
                    'link' => $link,
                    'patient_name' => $petName,
                    'owner_name' => $patient_owner_full_name,
                ]);
            }
        }

        // Also publish to admin and staff channels for each appointment
        foreach ($createdAppointments as $appointment) {
            $appointment->load('patient');
            $petName = $appointment->patient->pet_name;
            $appointmentTime = Carbon::createFromFormat('H:i', $appointment->appointment_time)->format('h:i A');
            $appointmentMessage = $appointmentTypeNames . ' appointment scheduled for ' . $request->appointment_date . ' at ' . $appointmentTime . ' for ' . $petName;
            $link = config('app.url') . '/admin/appointments/' . $appointment->id;
            $subject = sprintf("%s has submitted new appointment.", $patient_owner_full_name ?? '');

            // Publish to admin channel
            $ablyService->publishToAdmins('appointment.created', [
                'appointment_id' => $appointment->id,
                'subject' => $subject,
                'message' => $appointmentMessage,
                'link' => $link,
                'patient_name' => $petName,
                'owner_name' => $patient_owner_full_name,
            ]);

            // Publish to staff channel
            $ablyService->publishToStaff('appointment.created', [
                'appointment_id' => $appointment->id,
                'subject' => $subject,
                'message' => $appointmentMessage,
                'link' => $link,
                'patient_name' => $petName,
                'owner_name' => $patient_owner_full_name,
            ]);
        }

        $appointmentCount = count($createdAppointments);
        $message = $appointmentCount > 1 
            ? "{$appointmentCount} appointments created successfully."
            : 'Appointment created successfully.';

        return redirect()->route('client.appointments.index')
            ->with('message', $message);
    }

    /**
     * Display the specified appointment.
     */
    public function showAppointments($id)
    {
        $appointment = Appointment::with(['appointment_type', 'patient.petType'])
            ->where('id', $id)
            ->where(function ($query) {
                $query->whereHas('patient', function ($q) {
                    $q->where('user_id', auth()->id());
                })
                ->orWhere('user_id', auth()->id());
            })
            ->firstOrFail();
        
        // Ensure relationships are loaded
        if (!$appointment->relationLoaded('appointment_type')) {
            $appointment->load('appointment_type');
        }
        if (!$appointment->relationLoaded('appointment_types')) {
            $appointment->load('appointment_types');
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

        // Load prescription if exists and patients
        $appointment->load(['prescription.diagnoses.disease', 'prescription.medicines.medicine', 'patients.petType', 'patients.user']);

        // Get all appointment types as comma-separated string
        $appointmentTypes = $appointment->appointment_types->pluck('name')->join(', ') 
                          ?: ($appointment->appointment_type ? $appointment->appointment_type->name : 'N/A');
        
        // Get all patients for this appointment
        $patients = $appointment->patients;
        
        return Inertia::render('Client/Appointments/Show', [
            'appointment' => [
                'id' => $appointment->id,
                'appointment_type' => $appointmentTypes,
                'appointment_date' => $appointment->appointment_date ? $appointment->appointment_date->format('Y-m-d') : null,
                'appointment_time' => $appointment->appointment_time,
                'symptoms' => $appointment->symptoms,
                'is_approved' => $appointment->is_approved,
                'is_completed' => $appointment->is_completed,
                'is_canceled' => $appointment->is_canceled ?? false,
                'remarks' => $appointment->remarks,
                'created_at' => $appointment->created_at->toISOString(),
                'updated_at' => $appointment->updated_at->toISOString(),
            ],
            'patients' => $patients->map(function ($patient) {
                return [
                    'id' => $patient->id,
                    'pet_name' => $patient->pet_name,
                    'pet_breed' => $patient->pet_breed,
                    'pet_gender' => $patient->pet_gender,
                    'pet_birth_date' => $patient->pet_birth_date ? $patient->pet_birth_date->format('Y-m-d') : null,
                    'microchip_number' => $patient->microchip_number,
                    'pet_allergies' => $patient->pet_allergies,
                    'pet_type' => $patient->petType ? $patient->petType->name : 'N/A',
                ];
            }),
            // Keep backward compatibility with single patient
            'patient' => $appointment->patient ? [
                'id' => $appointment->patient->id,
                'pet_name' => $appointment->patient->pet_name,
                'pet_breed' => $appointment->patient->pet_breed,
                'pet_gender' => $appointment->patient->pet_gender,
                'pet_birth_date' => $appointment->patient->pet_birth_date ? $appointment->patient->pet_birth_date->format('Y-m-d') : null,
                'microchip_number' => $appointment->patient->microchip_number,
                'pet_allergies' => $appointment->patient->pet_allergies,
                'pet_type' => $appointment->patient->petType ? $appointment->patient->petType->name : 'N/A',
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
        ]);
    }

    /**
     * Cancel a pending appointment.
     */
    public function cancelAppointment(Request $request, $id)
    {
        try {
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
                if ($request->wantsJson() || $request->ajax()) {
                    return response()->json(['error' => 'This appointment is already canceled.'], 403);
                }
                return redirect()->route('client.appointments.show', $id)
                    ->with('error', 'This appointment is already canceled.');
            }

            if ($appointment->is_approved || $appointment->is_completed) {
                if ($request->wantsJson() || $request->ajax()) {
                    return response()->json(['error' => 'Only pending appointments can be canceled.'], 403);
                }
                return redirect()->route('client.appointments.show', $id)
                    ->with('error', 'Only pending appointments can be canceled.');
            }

            // Mark appointment as canceled instead of deleting
            $appointment->update(['is_canceled' => true]);

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['message' => 'Appointment canceled successfully.']);
            }

            return redirect()->route('client.appointments.index')
                ->with('message', 'Appointment canceled successfully.');
        } catch (\Exception $e) {
            Log::error('Error canceling appointment: ' . $e->getMessage());
            
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['error' => 'Failed to cancel appointment.'], 500);
            }
            
            return redirect()->route('client.appointments.show', $id)
                ->with('error', 'Failed to cancel appointment. Please try again.');
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
            'working_hours_end' => config('appointments.working_hours_end', '16:00'),
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
        while ($current->lt($end)) {
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

        return $time->gte($start) && $time->lt($end);
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
                    ->orWhere('microchip_number', 'LIKE', "%{$keyword}%")
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
                'microchip_number' => $pet->microchip_number,
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

        // Create a mapping of pet type names to their breeds
        $pet_breeds = [];
        foreach ($pet_types as $pet_type) {
            $pet_breeds[$pet_type['name']] = PetBreeds::getBreedsForPetType($pet_type['name']);
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
            'pet_type_id' => 'required|exists:pet_types,id',
            'pet_name' => 'nullable|string|max:100',
            'pet_breed' => 'required|string|max:100',
            'pet_gender' => 'nullable|in:Male,Female',
            'pet_birth_date' => 'nullable|date',
            'microchip_number' => 'nullable|string|max:100',
            'pet_allergies' => 'nullable|string',
        ]);

        $patient = Patient::create([
            'pet_type_id' => $validated['pet_type_id'],
            'pet_name' => $validated['pet_name'] ?? null,
            'pet_breed' => $validated['pet_breed'],
            'pet_gender' => $validated['pet_gender'] ?? null,
            'pet_birth_date' => $validated['pet_birth_date'] ?? null,
            'microchip_number' => $validated['microchip_number'] ?? null,
            'pet_allergies' => $validated['pet_allergies'] ?? null,
            'user_id' => auth()->id(), // Automatically assign to authenticated user
        ]);

        return redirect()->route('client.pets.index')
            ->with('message', 'Pet registered successfully.');
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

        return Inertia::render('Client/Pets/Edit', [
            'pet' => [
                'id' => $pet->id,
                'pet_type_id' => $pet->pet_type_id,
                'pet_name' => $pet->pet_name,
                'pet_breed' => $pet->pet_breed,
                'pet_gender' => $pet->pet_gender,
                'pet_birth_date' => $pet->pet_birth_date ? $pet->pet_birth_date->toDateString() : null,
                'microchip_number' => $pet->microchip_number,
                'pet_allergies' => $pet->pet_allergies,
            ],
            'pet_types' => $pet_types,
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
            'microchip_number' => 'nullable|string|max:100',
            'pet_allergies' => 'nullable|string',
        ]);

        $pet->update([
            'pet_type_id' => $validated['pet_type_id'],
            'pet_name' => $validated['pet_name'] ?? null,
            'pet_breed' => $validated['pet_breed'],
            'pet_gender' => $validated['pet_gender'] ?? null,
            'pet_birth_date' => $validated['pet_birth_date'] ?? null,
            'microchip_number' => $validated['microchip_number'] ?? null,
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
