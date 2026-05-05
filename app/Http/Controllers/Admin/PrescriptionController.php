<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Prescription;
use App\Traits\HasDateFiltering;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

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
            'patient.user',
            'diagnoses.disease',
        ]);

        // Search functionality
        if ($request->has('search') && ! empty($request->search)) {
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

        // Date filtering uses visit date (appointment), not when the prescription was saved
        $this->applyPrescriptionVisitDateFilter($query, $request);

        // Sort functionality
        $sortBy = $request->get('sort_by', 'appointment_date');
        $sortDirection = $request->get('sort_direction', 'desc');

        $allowedSortColumns = ['created_at', 'appointment_date'];
        if (! in_array($sortBy, $allowedSortColumns, true)) {
            $sortBy = 'appointment_date';
        }

        $sortDirection = strtolower($sortDirection) === 'asc' ? 'asc' : 'desc';

        if ($sortBy === 'appointment_date') {
            $query->join('appointments', 'prescriptions.appointment_id', '=', 'appointments.id')
                ->select('prescriptions.*')
                ->orderBy('appointments.appointment_date', $sortDirection)
                ->orderBy('appointments.appointment_time', $sortDirection);
        } else {
            $query->orderBy('prescriptions.'.$sortBy, $sortDirection);
        }

        $prescriptions = $query->paginate(15);

        // Transform the data for Inertia
        $prescriptions->getCollection()->transform(function ($prescription) {
            $appointment = $prescription->appointment;
            $patient = $prescription->patient;
            $user = $prescription->ownerUser();

            return [
                'id' => $prescription->id,
                'appointment_id' => $prescription->appointment_id,
                'appointment_type' => $appointment->appointment_type->name ?? 'N/A',
                'pet_type' => $patient->petType->name ?? 'N/A',
                'pet_breed' => $patient->pet_breed ?? 'N/A',
                'owner_name' => $prescription->ownerDisplayName(),
                'owner_mobile' => $user?->mobile_number ?? 'N/A',
                'owner_email' => $user?->email ?? 'N/A',
                'issued_on' => $prescription->issuedOnDisplay(),
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
            'medicines.medicine',
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
                'appointment_time' => $this->formatAppointmentTime($prescription->appointment->appointment_time),
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
            'owner' => ($ownerUser = $prescription->ownerUser()) ? [
                'id' => $ownerUser->id,
                'name' => trim(($ownerUser->first_name ?? '').' '.($ownerUser->last_name ?? '')) ?: ($ownerUser->name ?? ''),
                'email' => $ownerUser->email,
                'mobile_number' => $ownerUser->mobile_number ?? null,
                'address' => $ownerUser->address ?? null,
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
            'patient.user',
            'diagnoses.disease',
        ]);

        if ($request->has('search') && ! empty($request->search)) {
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

        $this->applyPrescriptionVisitDateFilter($query, $request);

        $prescriptions = $query
            ->join('appointments', 'prescriptions.appointment_id', '=', 'appointments.id')
            ->select('prescriptions.*')
            ->orderBy('appointments.appointment_date', 'desc')
            ->orderBy('appointments.appointment_time', 'desc')
            ->get();

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
            $user = $prescription->ownerUser();

            return [
                'appointment_type' => $appointment->appointment_type->name ?? 'N/A',
                'pet_type' => $patient->petType->name ?? 'N/A',
                'pet_breed' => $patient->pet_breed ?? 'N/A',
                'owner_name' => $prescription->ownerDisplayName(),
                'owner_phone' => $user?->mobile_number ?? 'N/A',
                'symptoms' => $prescription->symptoms ?? 'N/A',
                'issued_on' => $prescription->issuedOnDisplay(),
            ];
        });

        $filterInfo = $this->getFilterInfo($request);

        $base64Logo = 'data:image/png;base64,'.base64_encode(file_get_contents(public_path('media/logo_for_print.png')));
        $base64PanaboLogo = 'data:image/png;base64,'.base64_encode(file_get_contents(public_path('media/panabo.png')));

        $pdf = Pdf::loadView('admin.reports.prescriptions', [
            'prescriptions' => $data,
            'title' => 'Prescriptions Report',
            'filterInfo' => $filterInfo,
            'total' => $data->count(),
            'base64Logo' => $base64Logo,
            'base64PanaboLogo' => $base64PanaboLogo,
            'reportDate' => now()->format('F d, Y'),
        ])
            ->setPaper('a4', 'portrait');

        return $pdf->stream('prescriptions-report-'.date('Y-m-d').'.pdf');
    }

    private function exportCsv($prescriptions)
    {
        $filename = 'prescriptions-report-'.date('Y-m-d').'.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($prescriptions) {
            $file = fopen('php://output', 'w');

            fputcsv($file, ['Appointment Type', 'Pet Type', 'Breed', 'Owner Name', 'Phone Number', 'Symptoms', 'Issued On']);

            foreach ($prescriptions as $prescription) {
                $appointment = $prescription->appointment;
                $patient = $prescription->patient;
                $user = $prescription->ownerUser();

                fputcsv($file, [
                    $appointment->appointment_type->name ?? 'N/A',
                    $patient->petType->name ?? 'N/A',
                    $patient->pet_breed ?? 'N/A',
                    $prescription->ownerDisplayName(),
                    $user?->mobile_number ?? 'N/A',
                    $prescription->symptoms ?? 'N/A',
                    $prescription->issuedOnDisplay(),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Filter prescriptions by the linked appointment's visit date (not prescription created_at).
     */
    private function applyPrescriptionVisitDateFilter($query, Request $request): void
    {
        $filterType = $request->get('filter_type');

        switch ($filterType) {
            case 'date':
                if ($request->filled('date')) {
                    $date = Carbon::parse($request->date);
                    $query->whereHas('appointment', fn ($q) => $q->whereDate('appointment_date', $date));
                }
                break;

            case 'month':
                if ($request->filled('month') && $request->filled('year')) {
                    $month = (int) $request->month;
                    $year = (int) $request->year;
                    $query->whereHas('appointment', function ($q) use ($month, $year) {
                        $q->whereYear('appointment_date', $year)
                            ->whereMonth('appointment_date', $month);
                    });
                }
                break;

            case 'year':
                if ($request->filled('year')) {
                    $year = (int) $request->year;
                    $query->whereHas('appointment', fn ($q) => $q->whereYear('appointment_date', $year));
                }
                break;

            case 'range':
                if ($request->filled('date_from') && $request->filled('date_to')) {
                    $dateFrom = Carbon::parse($request->date_from)->toDateString();
                    $dateTo = Carbon::parse($request->date_to)->toDateString();
                    $query->whereHas('appointment', fn ($q) => $q->whereBetween('appointment_date', [$dateFrom, $dateTo]));
                }
                break;
        }
    }

    private function getFilterInfo($request)
    {
        $filterType = $request->get('filter_type');

        switch ($filterType) {
            case 'date':
                return 'Date: '.$request->get('date');
            case 'month':
                $month = $request->get('month');
                $year = $request->get('year');
                $monthName = date('F', mktime(0, 0, 0, (int) $month, 1));

                return "Month: {$monthName} {$year}";
            case 'year':
                return 'Year: '.$request->get('year');
            case 'range':
                return 'Range: '.$request->get('date_from').' to '.$request->get('date_to');
            default:
                return 'All Records';
        }
    }

    /**
     * Format appointment time to 12-hour format.
     */
    private function formatAppointmentTime($time)
    {
        try {
            return \Carbon\Carbon::createFromFormat('H:i:s', $time)->format('g:i A');
        } catch (\Exception $e) {
            try {
                return \Carbon\Carbon::createFromFormat('H:i', $time)->format('g:i A');
            } catch (\Exception $e) {
                return $time;
            }
        }
    }
}
