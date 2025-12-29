<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Disease;
use App\Models\Medicine;
use App\Models\Symptom;
use App\Models\PrescriptionDiagnosis;
use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DiseaseController extends Controller
{
    /**
     * Display a listing of diseases.
     */
    public function index(Request $request)
    {
        $query = Disease::withCount(['symptoms', 'medicines']);

        // Search functionality
        if ($request->has('search') && !empty($request->search)) {
            $query->where('name', 'LIKE', $request->search . '%');
        }

        // Sort functionality
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        
        // Validate sort_by to prevent SQL injection
        $allowedSortColumns = ['name', 'created_at'];
        if (!in_array($sortBy, $allowedSortColumns)) {
            $sortBy = 'created_at';
        }
        
        // Validate sort_direction
        $sortDirection = strtolower($sortDirection) === 'asc' ? 'asc' : 'desc';
        
        $query->orderBy($sortBy, $sortDirection);

        $diseases = $query->paginate(15);

        // Transform the data for Inertia
        $diseases->getCollection()->transform(function ($disease) {
            return [
                'id' => $disease->id,
                'name' => $disease->name,
                'symptoms_count' => $disease->symptoms_count ?? 0,
                'medicines_count' => $disease->medicines_count ?? 0,
                'home_remedy' => $disease->home_remedy ? substr($disease->home_remedy, 0, 50) . '...' : null,
                'created_at' => $disease->created_at->toISOString(),
            ];
        });

        return Inertia::render('Admin/Diseases/Index', [
            'diseases' => $diseases,
            'filters' => $request->only(['search', 'sort_by', 'sort_direction']),
        ]);
    }

    /**
     * Show the form for creating a new disease.
     */
    public function create()
    {
        // Exclude general "Diarrhea" and "Vomiting" when specific types exist
        $symptoms = Symptom::whereNotIn('name', ['Diarrhea', 'Vomiting'])
            ->orderBy('name')
            ->get(['id', 'name']);
        $medicines = Medicine::orderBy('name')->get(['id', 'name', 'dosage']);

        return Inertia::render('Admin/Diseases/Create', [
            'symptoms' => $symptoms,
            'medicines' => $medicines,
        ]);
    }

    /**
     * Store a newly created disease in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:diseases,name',
            'symptoms' => 'nullable|array',
            'symptoms.*' => 'exists:symptoms,id',
            'medicines' => 'nullable|array',
            'medicines.*' => 'exists:medicines,id',
            'home_remedy' => 'nullable|string|max:1825',
        ]);

        $disease = Disease::create([
            'name' => $validated['name'],
            'home_remedy' => $validated['home_remedy'] ?? null,
        ]);

        // Attach symptoms if provided
        if (!empty($validated['symptoms'])) {
            $disease->symptoms()->sync($validated['symptoms']);
        }

        // Attach medicines if provided
        if (!empty($validated['medicines'])) {
            $disease->medicines()->sync($validated['medicines']);
        }

        return redirect()->route('admin.diseases.show', $disease->id)
            ->with('success', 'Disease has been created successfully.');
    }

    /**
     * Display the specified disease.
     */
    public function show(Disease $disease)
    {
        $disease->load(['symptoms', 'medicines']);

        return Inertia::render('Admin/Diseases/Show', [
            'disease' => [
                'id' => $disease->id,
                'name' => $disease->name,
                'home_remedy' => $disease->home_remedy,
                'symptoms' => ($disease->symptoms ?? collect())->map(fn($s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                ]),
                'medicines' => ($disease->medicines ?? collect())->map(fn($m) => [
                    'id' => $m->id,
                    'name' => $m->name,
                    'dosage' => $m->dosage,
                    'stock' => $m->stock,
                ]),
                'created_at' => $disease->created_at->toISOString(),
                'updated_at' => $disease->updated_at->toISOString(),
            ],
        ]);
    }

    /**
     * Show the form for editing the specified disease.
     */
    public function edit(Disease $disease)
    {
        $disease->load(['symptoms', 'medicines']);
        
        // Exclude general "Diarrhea" and "Vomiting" when specific types exist
        $allSymptoms = Symptom::whereNotIn('name', ['Diarrhea', 'Vomiting'])
            ->orderBy('name')
            ->get(['id', 'name']);
        $allMedicines = Medicine::orderBy('name')->get(['id', 'name', 'dosage']);

        return Inertia::render('Admin/Diseases/Edit', [
            'disease' => [
                'id' => $disease->id,
                'name' => $disease->name,
                'home_remedy' => $disease->home_remedy,
                'symptoms' => ($disease->symptoms ?? collect())->pluck('id')->toArray(),
                'medicines' => ($disease->medicines ?? collect())->pluck('id')->toArray(),
            ],
            'allSymptoms' => $allSymptoms,
            'allMedicines' => $allMedicines,
        ]);
    }

    /**
     * Update the specified disease in storage.
     */
    public function update(Request $request, Disease $disease)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:diseases,name,' . $disease->id,
            'symptoms' => 'nullable|array',
            'symptoms.*' => 'exists:symptoms,id',
            'medicines' => 'nullable|array',
            'medicines.*' => 'exists:medicines,id',
            'home_remedy' => 'nullable|string|max:1825',
        ]);

        $disease->update([
            'name' => $validated['name'],
            'home_remedy' => $validated['home_remedy'] ?? null,
        ]);

        // Sync symptoms
        $disease->symptoms()->sync($validated['symptoms'] ?? []);

        // Sync medicines
        $disease->medicines()->sync($validated['medicines'] ?? []);

        return redirect()->route('admin.diseases.show', $disease->id)
            ->with('success', 'Disease has been updated successfully.');
    }

    /**
     * Remove the specified disease from storage.
     */
    public function destroy(Disease $disease)
    {
        // Detach related data
        $disease->symptoms()->detach();
        $disease->medicines()->detach();
        
        $disease->delete();

        return redirect()->route('admin.diseases.index')
            ->with('success', 'Disease deleted successfully.');
    }

    /**
     * Search diseases by keyword.
     */
    public function search(Request $request)
    {
        $keyword = $request->get('keyword', '');
        
        $diseases = Disease::where('name', 'LIKE', "%{$keyword}%")
            ->limit(20)
            ->get()
            ->map(function ($disease) {
                return [
                    'id' => $disease->id,
                    'name' => $disease->name,
                ];
            });
        
        return response()->json($diseases);
    }

    /**
     * Search diseases by symptoms (with accuracy prediction).
     */
    public function searchBySymptoms(Request $request)
    {
        // Check if KNN prediction is enabled
        $knnEnabled = \App\Models\Setting::get('enable_knn_prediction', true);
        
        if (!$knnEnabled) {
            // Return empty array if KNN is disabled
            return response()->json([]);
        }
        
        $symptoms = $request->get('symptoms', []);
        
        if (empty($symptoms)) {
            return response()->json([]);
        }
        
        // Get symptom IDs
        $symptomIds = Symptom::whereIn('name', $symptoms)
            ->pluck('id')
            ->toArray();
        
        if (empty($symptomIds)) {
            return response()->json([]);
        }
        
        // Count matching symptoms per disease
        $diseaseMatches = DB::table('disease_symptoms')
            ->whereIn('symptom_id', $symptomIds)
            ->select('disease_id', DB::raw('COUNT(*) as match_count'))
            ->groupBy('disease_id')
            ->get();
        
        // Get total symptoms per disease
        $diseaseTotals = DB::table('disease_symptoms')
            ->select('disease_id', DB::raw('COUNT(*) as total_symptoms'))
            ->groupBy('disease_id')
            ->get()
            ->keyBy('disease_id');
        
        $results = [];
        foreach ($diseaseMatches as $match) {
            $total = $diseaseTotals->get($match->disease_id);
            if ($total) {
                $accuracy = ($match->match_count / $total->total_symptoms) * 100;
                $disease = Disease::find($match->disease_id);
                
                if ($disease) {
                    $results[] = [
                        'id' => $disease->id,
                        'name' => $disease->name,
                        'accuracy' => round($accuracy, 2),
                        'match_count' => $match->match_count,
                        'total_symptoms' => $total->total_symptoms,
                    ];
                }
            }
        }
        
        // Sort by accuracy descending
        usort($results, function ($a, $b) {
            return $b['accuracy'] <=> $a['accuracy'];
        });
        
        return response()->json($results);
    }

    /**
     * Get suggested medicines for a disease.
     */
    public function getMedicines($id)
    {
        // Check if KNN prediction is enabled
        $knnEnabled = \App\Models\Setting::get('enable_knn_prediction', true);
        
        if (!$knnEnabled) {
            // Return empty array if KNN is disabled
            return response()->json([]);
        }
        
        $disease = Disease::findOrFail($id);
        
        $medicines = $disease->medicines()
            ->select('medicines.id', 'medicines.name', 'medicines.dosage', 'medicines.stock')
            ->get()
            ->map(function ($medicine) {
                return [
                    'id' => $medicine->id,
                    'name' => $medicine->name,
                    'dosage' => $medicine->dosage,
                    'stock' => $medicine->stock,
                ];
            });
        
        return response()->json($medicines);
    }

    /**
     * Display the disease map.
     */
    public function map()
    {
        // Get all prescription diagnoses with appointment and user data
        $cases = PrescriptionDiagnosis::with(['disease', 'appointment.user', 'appointment.patient'])
            ->whereHas('appointment.user')
            ->get()
            ->map(function ($diagnosis) {
                $user = $diagnosis->appointment->user;
                return [
                    'disease_id' => $diagnosis->disease_id,
                    'disease_name' => $diagnosis->disease->name,
                    'lat' => $user->lat ?? 7.322074145850032,
                    'lng' => $user->long ?? 125.6865978240967,
                    'address' => $user->address ?? 'Unknown',
                    'appointment_date' => $diagnosis->appointment->appointment_date ?? null,
                ];
            })
            ->filter(function ($case) {
                return $case['lat'] && $case['lng'];
            });

        // Group by address (barangay) to find outbreak zones
        $zones = $cases->groupBy('address')
            ->map(function ($zoneCases, $address) {
                $latSum = $zoneCases->sum('lat');
                $lngSum = $zoneCases->sum('lng');
                $count = $zoneCases->count();
                
                return [
                    'address' => $address,
                    'lat' => $latSum / $count,
                    'lng' => $lngSum / $count,
                    'count' => $count,
                ];
            })
            ->sortByDesc('count')
            ->take(5)
            ->values();

        // Get top 5 diseases
        $topDiseases = $cases->groupBy('disease_name')
            ->map(function ($diseaseCases, $name) {
                return [
                    'name' => $name,
                    'count' => $diseaseCases->count(),
                ];
            })
            ->sortByDesc('count')
            ->take(5)
            ->values();

        // Generate colors for diseases
        $diseaseColors = $topDiseases->mapWithKeys(function ($disease) {
            return [$disease['name'] => $this->generateColor($disease['name'])];
        })->toArray();

        return Inertia::render('Admin/Diseases/Map', [
            'outbreakZones' => $zones,
            'cases' => $cases->values(),
            'topDiseases' => $topDiseases,
            'diseaseColors' => $diseaseColors,
        ]);
    }

    /**
     * Get training data for disease-medicine ML model.
     */
    public function getMedicineTrainingData()
    {
        $data = DB::table('disease_medicines')
            ->join('diseases', 'disease_medicines.disease_id', '=', 'diseases.id')
            ->join('medicines', 'disease_medicines.medicine_id', '=', 'medicines.id')
            ->select(
                'diseases.id as disease_id',
                'diseases.name as disease_name',
                'medicines.id as medicine_id',
                'medicines.name as medicine_name',
                'medicines.dosage'
            )
            ->get();

        // Group by disease and create feature vectors
        $diseases = Disease::all()->keyBy('id');
        $medicines = Medicine::all()->keyBy('id');
        
        $trainingData = [];
        foreach ($data as $row) {
            $diseaseId = $row->disease_id;
            $medicineId = $row->medicine_id;
            
            if (!isset($trainingData[$diseaseId])) {
                $trainingData[$diseaseId] = [
                    'disease_id' => $diseaseId,
                    'disease_name' => $row->disease_name,
                    'medicines' => [],
                ];
            }
            
            $trainingData[$diseaseId]['medicines'][] = [
                'medicine_id' => $medicineId,
                'medicine_name' => $row->medicine_name,
                'dosage' => $row->dosage,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => array_values($trainingData),
            'total_diseases' => count($trainingData),
            'total_medicines' => $medicines->count(),
        ]);
    }

    /**
     * Get training data for disease-symptom ML model.
     */
    public function getSymptomTrainingData()
    {
        $data = DB::table('disease_symptoms')
            ->join('diseases', 'disease_symptoms.disease_id', '=', 'diseases.id')
            ->join('symptoms', 'disease_symptoms.symptom_id', '=', 'symptoms.id')
            ->select(
                'diseases.id as disease_id',
                'diseases.name as disease_name',
                'symptoms.id as symptom_id',
                'symptoms.name as symptom_name'
            )
            ->get();

        // Group by disease
        $symptoms = Symptom::all()->keyBy('id');
        
        $trainingData = [];
        foreach ($data as $row) {
            $diseaseId = $row->disease_id;
            $symptomId = $row->symptom_id;
            
            if (!isset($trainingData[$diseaseId])) {
                $trainingData[$diseaseId] = [
                    'disease_id' => $diseaseId,
                    'disease_name' => $row->disease_name,
                    'symptoms' => [],
                ];
            }
            
            $trainingData[$diseaseId]['symptoms'][] = [
                'symptom_id' => $symptomId,
                'symptom_name' => $row->symptom_name,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => array_values($trainingData),
            'total_diseases' => count($trainingData),
            'total_symptoms' => $symptoms->count(),
            'all_symptoms' => $symptoms->pluck('name', 'id')->toArray(),
        ]);
    }

    /**
     * Get disease statistics for dashboard.
     */
    public function statistics(Request $request)
    {
        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);

        // Get top diseases by month
        $topDiseases = PrescriptionDiagnosis::join('diseases', 'prescription_diagnoses.disease_id', '=', 'diseases.id')
            ->join('appointments', 'prescription_diagnoses.appointment_id', '=', 'appointments.id')
            ->whereYear('appointments.appointment_date', $year)
            ->whereMonth('appointments.appointment_date', $month)
            ->select('diseases.id', 'diseases.name', DB::raw('COUNT(*) as count'))
            ->groupBy('diseases.id', 'diseases.name')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'count' => $item->count,
                ];
            });

        // Get total number of diseases for the month
        $totalDiseases = PrescriptionDiagnosis::join('appointments', 'prescription_diagnoses.appointment_id', '=', 'appointments.id')
            ->whereYear('appointments.appointment_date', $year)
            ->whereMonth('appointments.appointment_date', $month)
            ->distinct()
            ->count('prescription_diagnoses.disease_id');

        // Get total disease cases for the month
        $totalCases = PrescriptionDiagnosis::join('appointments', 'prescription_diagnoses.appointment_id', '=', 'appointments.id')
            ->whereYear('appointments.appointment_date', $year)
            ->whereMonth('appointments.appointment_date', $month)
            ->count();

        return response()->json([
            'top_diseases' => $topDiseases,
            'total_diseases' => $totalDiseases,
            'total_cases' => $totalCases,
            'month' => $month,
            'year' => $year,
        ]);
    }

    /**
     * Generate a consistent color from text using MD5 hash.
     */
    private function generateColor($text)
    {
        $hash = md5($text);
        return '#' . substr($hash, 0, 6);
    }
}
