<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\DisabledDate;
use App\Models\Patient;
use App\Models\Prescription;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    /**
     * Display the dashboard.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $isAdmin = $user && $user->hasRole('admin');
        $isStaff = $user && $user->hasRole('staff');
        
        $appointments = [];
        
        // Fetch appointments for admin and staff
        if ($isAdmin || $isStaff) {
            $appointmentsQuery = Appointment::with([
                'appointment_type',
                'patient.petType',
                'patients.petType',
            ])
            ->where(function ($query) {
                $query->whereNull('is_canceled')
                    ->orWhere('is_canceled', false);
            })
            ->orderBy('appointment_date', 'asc')
            ->orderBy('appointment_time', 'asc');

            $appointmentsData = $appointmentsQuery->get();

            $appointments = $appointmentsData->map(function ($appointment) {
                // Calculate status
                $status = 'Pending';
                if ($appointment->is_canceled) {
                    $status = 'Canceled';
                } elseif ($appointment->is_completed) {
                    $status = 'Completed';
                } elseif ($appointment->is_approved) {
                    $status = 'Approved';
                }

                // Get pet names from patients relationship
                $patients = $appointment->patients;
                $petNames = $patients->pluck('pet_name')->filter()->join(', ');
                
                // Fallback to single patient if no patients in many-to-many
                if (empty($petNames) && $appointment->patient) {
                    $petNames = $appointment->patient->pet_name ?? 'N/A';
                }
                
                // Get pet types
                $petTypes = $patients->map(function ($patient) {
                    return $patient->petType->name ?? 'N/A';
                })->unique()->join(', ');
                
                if (empty($petTypes) && $appointment->patient && $appointment->patient->petType) {
                    $petTypes = $appointment->patient->petType->name ?? 'N/A';
                }

                // Get appointment type name
                $appointmentTypeName = $appointment->appointment_type->name ?? 'N/A';

                return [
                    'id' => $appointment->id,
                    'appointment_type' => $appointmentTypeName,
                    'appointment_date' => $appointment->appointment_date ? $appointment->appointment_date->format('Y-m-d') : null,
                    'appointment_time' => $appointment->appointment_time,
                    'status' => $status,
                    'pet_type' => $petTypes ?: 'N/A',
                    'pet_name' => $petNames ?: 'N/A',
                ];
            })->toArray();

            // Fetch prescriptions with follow-up dates
            $prescriptions = Prescription::whereNotNull('follow_up_date')
                ->whereDate('follow_up_date', '>=', Carbon::today())
                ->with(['patient.petType'])
                ->get();

            // Convert prescriptions to follow-up appointment format
            foreach ($prescriptions as $prescription) {
                $patient = $prescription->patient;
                if ($patient) {
                    $appointments[] = [
                        'id' => 'followup-' . $prescription->id,
                        'appointment_type' => 'Follow-up Check-up',
                        'appointment_date' => $prescription->follow_up_date->format('Y-m-d'),
                        'appointment_time' => null,
                        'status' => 'Follow-up',
                        'pet_type' => $patient->petType ? $patient->petType->name : 'N/A',
                        'pet_name' => $patient->pet_name,
                        'is_followup' => true,
                        'prescription_id' => $prescription->id,
                    ];
                }
            }
        }

        // Fetch disabled dates for admin and staff
        $disabledDates = [];
        if ($isAdmin || $isStaff) {
            $disabledDates = DisabledDate::orderBy('date', 'asc')
                ->get()
                ->map(function ($date) {
                    return [
                        'id' => $date->id,
                        'date' => $date->date->format('Y-m-d'),
                        'reason' => $date->reason,
                    ];
                })
                ->toArray();
        }

        // Fetch client data (pets and appointment statistics)
        $clientPets = [];
        $appointmentStats = [];
        if (!$isAdmin && !$isStaff && $user) {
            // Fetch client's pets
            $clientPets = Patient::where('user_id', $user->id)
                ->with('petType')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($pet) {
                    return [
                        'id' => $pet->id,
                        'pet_name' => $pet->pet_name,
                        'pet_breed' => $pet->pet_breed,
                        'pet_type' => $pet->petType->name ?? 'N/A',
                        'pet_gender' => $pet->pet_gender,
                        'created_at' => $pet->created_at->toISOString(),
                    ];
                })
                ->toArray();

            // Fetch appointment statistics for the client
            $appointmentsQuery = Appointment::where(function ($query) use ($user) {
                $query->whereHas('patient', function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                })
                ->orWhere('user_id', $user->id);
            });

            // Count by status
            $totalAppointments = (clone $appointmentsQuery)->count();
            $pendingCount = (clone $appointmentsQuery)
                ->where('is_approved', false)
                ->where(function ($q) {
                    $q->whereNull('is_completed')->orWhere('is_completed', false);
                })
                ->where(function ($q) {
                    $q->whereNull('is_canceled')->orWhere('is_canceled', false);
                })
                ->count();
            $approvedCount = (clone $appointmentsQuery)
                ->where('is_approved', true)
                ->where('is_completed', false)
                ->where(function ($q) {
                    $q->whereNull('is_canceled')->orWhere('is_canceled', false);
                })
                ->count();
            $completedCount = (clone $appointmentsQuery)
                ->where('is_completed', true)
                ->count();
            $canceledCount = (clone $appointmentsQuery)
                ->where('is_canceled', true)
                ->count();

            $appointmentStats = [
                'total' => $totalAppointments,
                'pending' => $pendingCount,
                'approved' => $approvedCount,
                'completed' => $completedCount,
                'canceled' => $canceledCount,
            ];
        }

        return Inertia::render('Dashboard', [
            'appointments' => $appointments,
            'disabledDates' => $disabledDates,
            'clientPets' => $clientPets,
            'appointmentStats' => $appointmentStats,
        ]);
    }
}









