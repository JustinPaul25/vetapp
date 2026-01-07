<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Prescription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Traits\HasDateFiltering;
use Barryvdh\DomPDF\Facade\Pdf;

class PrescriptionController extends Controller
{
    use HasDateFiltering;
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

        // Date filtering
        $this->applyDateFilter($query, $request, 'created_at');

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
                'owner_name' => $user ? (trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: $user->name) : 'N/A',
                'owner_mobile' => $user->mobile_number ?? 'N/A',
                'owner_email' => $user->email ?? 'N/A',
                'issued_on' => $prescription->created_at->format('Y-m-d H:i'),
                'created_at' => $prescription->created_at->toISOString(),
                'follow_up_date' => $prescription->follow_up_date ? $prescription->follow_up_date->format('Y-m-d') : null,
                'follow_up_notified_at' => $prescription->follow_up_notified_at ? $prescription->follow_up_notified_at->format('Y-m-d H:i') : null,
            ];
        });

        return Inertia::render('Admin/Prescriptions/Index', [
            'prescriptions' => $prescriptions,
            'filters' => $request->only(['search', 'sort_by', 'sort_direction']),
        ]);
    }

    /**
     * Display the specified prescription.
     */
    public function show($id)
    {
        $prescription = Prescription::with([
            'appointment.appointment_type',
            'appointment.user',
            'patient.petType',
            'patient.user',
            'diagnoses.disease',
            'medicines.medicine'
        ])->findOrFail($id);

        return Inertia::render('Admin/Prescriptions/Show', [
            'prescription' => [
                'id' => $prescription->id,
                'appointment_id' => $prescription->appointment_id,
                'symptoms' => $prescription->symptoms,
                'notes' => $prescription->notes,
                'pet_weight' => $prescription->pet_weight,
                'follow_up_date' => $prescription->follow_up_date ? $prescription->follow_up_date->format('Y-m-d') : null,
                'created_at' => $prescription->created_at->toISOString(),
                'updated_at' => $prescription->updated_at->toISOString(),
            ],
            'appointment' => [
                'id' => $prescription->appointment->id,
                'appointment_type' => $prescription->appointment->appointment_type->name ?? 'N/A',
                'appointment_date' => $prescription->appointment->appointment_date->format('Y-m-d'),
                'appointment_time' => $prescription->appointment->appointment_time,
                'created_at' => $prescription->appointment->created_at->toISOString(),
            ],
            'patient' => [
                'id' => $prescription->patient->id,
                'pet_name' => $prescription->patient->pet_name,
                'pet_breed' => $prescription->patient->pet_breed,
                'pet_gender' => $prescription->patient->pet_gender,
                'pet_birth_date' => $prescription->patient->pet_birth_date ? $prescription->patient->pet_birth_date->format('Y-m-d') : null,
                'pet_allergies' => $prescription->patient->pet_allergies,
                'pet_type' => $prescription->patient->petType->name ?? 'N/A',
            ],
            'owner' => $prescription->appointment->user ? [
                'id' => $prescription->appointment->user->id,
                'name' => trim(($prescription->appointment->user->first_name ?? '') . ' ' . ($prescription->appointment->user->last_name ?? '')) ?: $prescription->appointment->user->name,
                'email' => $prescription->appointment->user->email,
                'mobile_number' => $prescription->appointment->user->mobile_number ?? null,
                'address' => $prescription->appointment->user->address ?? null,
            ] : null,
            'diagnoses' => $prescription->diagnoses->map(function ($diagnosis) {
                return [
                    'id' => $diagnosis->id,
                    'disease' => $diagnosis->disease->name ?? 'N/A',
                ];
            }),
            'medicines' => $prescription->medicines->map(function ($prescriptionMedicine) {
                return [
                    'id' => $prescriptionMedicine->id,
                    'medicine' => $prescriptionMedicine->medicine->name ?? 'N/A',
                    'dosage' => $prescriptionMedicine->dosage,
                    'instructions' => $prescriptionMedicine->instructions,
                    'quantity' => $prescriptionMedicine->quantity,
                ];
            }),
        ]);
    }

    /**
     * Export prescriptions report.
     */
    public function export(Request $request)
    {
        $query = Prescription::with([
            'appointment.appointment_type',
            'appointment.user',
            'patient.petType',
            'diagnoses.disease'
        ]);

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

        $this->applyDateFilter($query, $request, 'created_at');

        $prescriptions = $query->orderBy('created_at', 'desc')->get();

        $format = $request->get('format', 'pdf');

        if ($format === 'csv') {
            return $this->exportCsv($prescriptions);
        }

        return $this->exportPdf($prescriptions, $request);
    }

    private function exportPdf($prescriptions, $request)
    {
        $data = $prescriptions->map(function ($prescription) {
            $appointment = $prescription->appointment;
            $patient = $prescription->patient;
            $user = $appointment->user ?? null;
            
            return [
                'appointment_type' => $appointment->appointment_type->name ?? 'N/A',
                'pet_type' => $patient->petType->name ?? 'N/A',
                'pet_breed' => $patient->pet_breed ?? 'N/A',
                'owner_name' => $user ? (trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: $user->name) : 'N/A',
                'owner_email' => $user->email ?? 'N/A',
                'symptoms' => $prescription->symptoms ?? 'N/A',
                'issued_on' => $prescription->created_at->format('Y-m-d H:i'),
            ];
        });

        $filterInfo = $this->getFilterInfo($request);

        $pdf = Pdf::loadView('admin.reports.prescriptions', [
            'prescriptions' => $data,
            'title' => 'Prescriptions Report',
            'filterInfo' => $filterInfo,
            'total' => $data->count(),
        ])
        ->setPaper('a4', 'portrait');

        return $pdf->stream('prescriptions-report-' . date('Y-m-d') . '.pdf');
    }

    private function exportCsv($prescriptions)
    {
        $filename = 'prescriptions-report-' . date('Y-m-d') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($prescriptions) {
            $file = fopen('php://output', 'w');
            
            fputcsv($file, ['Appointment Type', 'Pet Type', 'Breed', 'Owner Name', 'Owner Email', 'Symptoms', 'Issued On']);

            foreach ($prescriptions as $prescription) {
                $appointment = $prescription->appointment;
                $patient = $prescription->patient;
                $user = $appointment->user ?? null;
                
                fputcsv($file, [
                    $appointment->appointment_type->name ?? 'N/A',
                    $patient->petType->name ?? 'N/A',
                    $patient->pet_breed ?? 'N/A',
                    $user ? (trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: $user->name) : 'N/A',
                    $user->email ?? 'N/A',
                    $prescription->symptoms ?? 'N/A',
                    $prescription->created_at->format('Y-m-d H:i'),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function getFilterInfo($request)
    {
        $filterType = $request->get('filter_type');
        
        switch ($filterType) {
            case 'date':
                return 'Date: ' . $request->get('date');
            case 'month':
                $month = $request->get('month');
                $year = $request->get('year');
                $monthName = date('F', mktime(0, 0, 0, (int)$month, 1));
                return "Month: {$monthName} {$year}";
            case 'year':
                return 'Year: ' . $request->get('year');
            case 'range':
                return 'Range: ' . $request->get('date_from') . ' to ' . $request->get('date_to');
            default:
                return 'All Records';
        }
    }
}
