<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
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
        }

        return Inertia::render('Dashboard', [
            'appointments' => $appointments,
        ]);
    }
}








