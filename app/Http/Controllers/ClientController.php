<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\Patient;
use App\Models\PetBreed;
use App\Models\PetType;
use App\Models\User;
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

            $appointments = Appointment::select(
                'appointments.*',
                'appointment_types.name as appointment_type',
                'patients.pet_name',
                'pt.name as pet_type',
                DB::raw("IF(appointments.is_canceled = 1, 'Canceled', IF(appointments.is_approved = 0, 'Pending', IF(appointments.is_completed = 1, 'Completed', 'Approved'))) as status")
            )
                ->join('appointment_types', 'appointments.appointment_type_id', 'appointment_types.id')
                ->leftJoin('patients', 'patients.id', 'appointments.patient_id')
                ->leftJoin('pet_types as pt', 'pt.id', 'patients.pet_type_id')
                ->leftJoin('prescriptions', 'prescriptions.appointment_id', 'appointments.id')
                ->leftJoin('prescription_diagnoses', 'prescription_diagnoses.prescription_id', 'prescriptions.id')
                ->leftJoin('diseases', 'diseases.id', 'prescription_diagnoses.disease_id')
                ->where(function ($query) {
                    $query->where('patients.user_id', auth()->id())
                        ->orWhere('appointments.user_id', auth()->id());
                });

            if (!empty($keyword)) {
                $appointments->where(function ($q) use ($keyword) {
                    $q->where('pt.name', 'LIKE', "%{$keyword}%")
                        ->orWhere(DB::raw("CONCAT(COALESCE(patients.owner_first_name, ''), ' ', COALESCE(patients.owner_last_name, ''))"), 'LIKE', "%{$keyword}%")
                        ->orWhere('diseases.name', 'LIKE', "%{$keyword}%")
                        ->orWhere('patients.pet_name', 'LIKE', "%{$keyword}%");
                });
            }

            $appointments = $appointments->orderBy('appointments.appointment_date', 'desc')
                ->orderBy('appointments.appointment_time', 'desc')
                ->get();

            return response()->json([
                'data' => $appointments->map(function ($appointment) {
                    return [
                        'id' => $appointment->id,
                        'appointment_type' => $appointment->appointment_type,
                        'pet_type' => $appointment->pet_type,
                        'pet_name' => $appointment->pet_name,
                        'appointment_date' => $appointment->appointment_date ? $appointment->appointment_date->format('Y-m-d') : null,
                        'appointment_time' => $appointment->appointment_time,
                        'status' => $appointment->status,
                    ];
                }),
            ]);
        }

        $user = auth()->user();
        $hasLocationPin = !!($user->lat && $user->long);

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
            'has_location_pin' => $hasLocationPin,
        ]);
    }

    /**
     * Store a newly created appointment.
     */
    public function bookAppointment(Request $request)
    {
        $request->validate([
            'pet_id' => 'required|exists:patients,id',
            'appointment_type_id' => 'required|exists:appointment_types,id',
            'appointment_date' => 'required|date|after:today',
            'appointment_time' => 'required|string',
            'symptoms' => 'nullable|string|max:1825',
        ]);

        // Check if user has location pin set
        $user = Auth::user();
        if (!$user->lat || !$user->long) {
            return back()->withErrors([
                'location_pin' => 'Please set your home address location pin in settings before booking an appointment.',
            ])->withInput();
        }

        // Verify the pet belongs to the authenticated user
        $pet = Patient::where('id', $request->pet_id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        // Convert time from 12-hour format (h:i A) to 24-hour format (H:i)
        $time = Carbon::createFromFormat('h:i A', $request->appointment_time);

        // Validate timeslot restrictions
        $this->validateTimeslotRestrictions($request->appointment_date, $time->format('H:i'));

        // Create appointment (initially not approved)
        $appointment = Appointment::with('appointment_type')->create([
            'patient_id' => $request->pet_id,
            'appointment_type_id' => $request->appointment_type_id,
            'appointment_date' => $request->appointment_date,
            'symptoms' => $request->symptoms ?? '',
            'is_approved' => false, // Client appointments start as pending
            'appointment_time' => $time->format('H:i'), // Store in 24-hour format
            'user_id' => Auth::id(),
        ]);

        // Reload appointment with relationships
        $appointment->load('appointment_type', 'patient.petType', 'patient.user');

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

        $patient_owner_full_name = $pet->user ? 
            trim(($pet->user->first_name ?? '') . ' ' . ($pet->user->last_name ?? '')) ?: $pet->user->name : 'N/A';
        $appointmentTypeName = $appointment->appointment_type->name ?? 'N/A';

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
            "Appointment Date: " . $request->appointment_date . "<br>" .
            "Appointment Time: " . $request->appointment_time . "<br>" .
            "<p style='text-align:center'><a href='" . $link . "' style='background-color: #4CAF50; border: none; color: white; padding: 15px 32px; text-align: center; text-decoration: none; font-size: 12px; border-radius: 15px;'>View Appointment</a></p>";

        $ablyService = app(AblyService::class);
        $appointmentMessage = $appointmentTypeName . ' appointment scheduled for ' . $request->appointment_date . ' at ' . $request->appointment_time;

        // Send notifications via database, email, and Ably to admins
        foreach ($adminUsers as $user) {
            $user->notify(new \App\Notifications\DefaultNotification($subject, $message, $link));
            
            // Send real-time notification via Ably
            $ablyService->publishToUser($user->id, 'appointment.created', [
                'appointment_id' => $appointment->id,
                'subject' => $subject,
                'message' => $appointmentMessage,
                'link' => $link,
                'patient_name' => $pet->pet_name,
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
                'patient_name' => $pet->pet_name,
                'owner_name' => $patient_owner_full_name,
            ]);
        }

        // Also publish to admin channel for all admins
        $ablyService->publishToAdmins('appointment.created', [
            'appointment_id' => $appointment->id,
            'subject' => $subject,
            'message' => $appointmentMessage,
            'link' => $link,
            'patient_name' => $pet->pet_name,
            'owner_name' => $patient_owner_full_name,
        ]);

        // Also publish to staff channel for all staff
        $ablyService->publishToStaff('appointment.created', [
            'appointment_id' => $appointment->id,
            'subject' => $subject,
            'message' => $appointmentMessage,
            'link' => $link,
            'patient_name' => $pet->pet_name,
            'owner_name' => $patient_owner_full_name,
        ]);

        return redirect()->route('client.appointments.index')
            ->with('success', 'Appointment created successfully.');
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
            ],
            'patient' => $appointment->patient ? [
                'id' => $appointment->patient->id,
                'pet_name' => $appointment->patient->pet_name,
                'pet_breed' => $appointment->patient->pet_breed,
                'pet_gender' => $appointment->patient->pet_gender,
                'pet_birth_date' => $appointment->patient->pet_birth_date ? $appointment->patient->pet_birth_date->format('Y-m-d') : null,
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
        // Check if this is an API request (not Inertia)
        $isApiRequest = ($request->ajax() || $request->wantsJson()) && !$request->header('X-Inertia');
        
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

            // Mark appointment as canceled instead of deleting
            $appointment->update(['is_canceled' => true]);

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

            // Only allow rescheduling appointments that are not canceled or completed
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
            ]);

            // Reload appointment with relationships
            $appointment->load('appointment_type', 'patient.petType', 'patient.user');

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

            $pet = $appointment->patient;
            $patient_owner_full_name = $pet->user ? 
                trim(($pet->user->first_name ?? '') . ' ' . ($pet->user->last_name ?? '')) ?: $pet->user->name : 'N/A';
            $appointmentTypeName = $appointment->appointment_type->name ?? 'N/A';

            $link = config('app.url') . '/admin/appointments/' . $appointment->id;
            $subject = sprintf("%s has rescheduled an appointment.", $patient_owner_full_name ?? '');
            $message = "Hi, an appointment has been rescheduled<br><br>" .
                "Appointment Details.<br><br>" .
                "Full Name: " . $patient_owner_full_name . "<br>" .
                "Mobile Number: " . ($pet->user ? ($pet->user->mobile_number ?? 'N/A') : 'N/A') . "<br>" .
                "Email Address: " . ($pet->user ? ($pet->user->email ?? 'N/A') : 'N/A') . "<br>" .
                "Pet Type: " . ($pet->petType->name ?? 'N/A') . "<br>" .
                "Breed: " . ($pet->pet_breed ?? 'N/A') . "<br>" .
                "Appointment Type: " . $appointmentTypeName . "<br>" .
                "Previous Date: " . $oldDate . "<br>" .
                "Previous Time: " . $oldTime . "<br>" .
                "New Date: " . $request->appointment_date . "<br>" .
                "New Time: " . $request->appointment_time . "<br>" .
                "<p style='text-align:center'><a href='" . $link . "' style='background-color: #4CAF50; border: none; color: white; padding: 15px 32px; text-align: center; text-decoration: none; font-size: 12px; border-radius: 15px;'>View Appointment</a></p>";

            $ablyService = app(AblyService::class);
            $appointmentMessage = $appointmentTypeName . ' appointment rescheduled from ' . $oldDate . ' at ' . $oldTime . ' to ' . $request->appointment_date . ' at ' . $request->appointment_time;

            // Send notifications via database, email, and Ably to admins
            foreach ($adminUsers as $user) {
                $user->notify(new \App\Notifications\DefaultNotification($subject, $message, $link));
                
                // Send real-time notification via Ably
                $ablyService->publishToUser($user->id, 'appointment.rescheduled', [
                    'appointment_id' => $appointment->id,
                    'subject' => $subject,
                    'message' => $appointmentMessage,
                    'link' => $link,
                    'patient_name' => $pet->pet_name,
                    'owner_name' => $patient_owner_full_name,
                    'old_date' => $oldDate,
                    'old_time' => $oldTime,
                    'new_date' => $request->appointment_date,
                    'new_time' => $request->appointment_time,
                ]);
            }

            // Send real-time notifications via Ably to staff
            foreach ($staffUsers as $user) {
                $ablyService->publishToUser($user->id, 'appointment.rescheduled', [
                    'appointment_id' => $appointment->id,
                    'subject' => $subject,
                    'message' => $appointmentMessage,
                    'link' => $link,
                    'patient_name' => $pet->pet_name,
                    'owner_name' => $patient_owner_full_name,
                    'old_date' => $oldDate,
                    'old_time' => $oldTime,
                    'new_date' => $request->appointment_date,
                    'new_time' => $request->appointment_time,
                ]);
            }

            // Also publish to admin channel for all admins
            $ablyService->publishToAdmins('appointment.rescheduled', [
                'appointment_id' => $appointment->id,
                'subject' => $subject,
                'message' => $appointmentMessage,
                'link' => $link,
                'patient_name' => $pet->pet_name,
                'owner_name' => $patient_owner_full_name,
                'old_date' => $oldDate,
                'old_time' => $oldTime,
                'new_date' => $request->appointment_date,
                'new_time' => $request->appointment_time,
            ]);

            // Also publish to staff channel for all staff
            $ablyService->publishToStaff('appointment.rescheduled', [
                'appointment_id' => $appointment->id,
                'subject' => $subject,
                'message' => $appointmentMessage,
                'link' => $link,
                'patient_name' => $pet->pet_name,
                'owner_name' => $patient_owner_full_name,
                'old_date' => $oldDate,
                'old_time' => $oldTime,
                'new_date' => $request->appointment_date,
                'new_time' => $request->appointment_time,
            ]);

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

        return DB::transaction(function () use ($validated) {
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