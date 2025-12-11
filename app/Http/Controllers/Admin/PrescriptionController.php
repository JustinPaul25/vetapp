<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Prescription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PrescriptionController extends Controller
{
    /**
     * Display a listing of all prescriptions.
     */
    public function index(Request $request)
    {
        $query = Prescription::with([
            'appointment.appointment_type',
            'appointment.user',
            'patient.petType',
            'diagnoses.disease'
        ]);

        // Search functionality
        if ($request->has('search') && !empty($request->search)) {
            $keyword = $request->search;
            $query->where(function ($q) use ($keyword) {
                $q->whereHas('patient.petType', function ($q) use ($keyword) {
                    $q->where('name', 'LIKE', "%{$keyword}%");
                })
                ->orWhereHas('appointment.user', function ($q) use ($keyword) {
                    $q->whereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$keyword}%"]);
                })
                ->orWhereHas('diagnoses.disease', function ($q) use ($keyword) {
                    $q->where('name', 'LIKE', "%{$keyword}%");
                });
            });
        }

        // Sort functionality
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        
        // Validate sort_by to prevent SQL injection
        $allowedSortColumns = ['created_at'];
        if (!in_array($sortBy, $allowedSortColumns)) {
            $sortBy = 'created_at';
        }
        
        // Validate sort_direction
        $sortDirection = strtolower($sortDirection) === 'asc' ? 'asc' : 'desc';
        
        $query->orderBy($sortBy, $sortDirection);

        $prescriptions = $query->paginate(15);

        // Transform the data for Inertia
        $prescriptions->getCollection()->transform(function ($prescription) {
            $appointment = $prescription->appointment;
            $patient = $prescription->patient;
            $user = $appointment->user ?? null;
            
            return [
                'id' => $prescription->id,
                'appointment_id' => $prescription->appointment_id,
                'appointment_type' => $appointment->appointment_type->name ?? 'N/A',
                'pet_type' => $patient->petType->name ?? 'N/A',
                'pet_breed' => $patient->pet_breed ?? 'N/A',
                'owner_name' => $user ? (trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''))) : 'N/A',
                'owner_mobile' => $user->mobile_number ?? 'N/A',
                'owner_email' => $user->email ?? 'N/A',
                'issued_on' => $prescription->created_at->format('Y-m-d H:i'),
                'created_at' => $prescription->created_at->toISOString(),
            ];
        });

        return Inertia::render('Admin/Prescriptions/Index', [
            'prescriptions' => $prescriptions,
            'filters' => $request->only(['search', 'sort_by', 'sort_direction']),
        ]);
    }
}
