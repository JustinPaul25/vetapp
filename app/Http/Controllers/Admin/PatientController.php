<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\PatientWeightHistory;
use App\Models\PetType;
use App\Models\PetBreed;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class PatientController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Patient::with(['petType', 'user']);

        // Search functionality
        if ($request->has('search') && !empty($request->search)) {
            $keyword = $request->search;
            $query->where(function ($q) use ($keyword) {
                $q->where('pet_name', 'LIKE', "%{$keyword}%")
                    ->orWhere('pet_breed', 'LIKE', "%{$keyword}%")
                    ->orWhereHas('petType', function ($q) use ($keyword) {
                        $q->where('name', 'LIKE', "%{$keyword}%");
                    })
                    ->orWhereHas('user', function ($q) use ($keyword) {
                        $q->where(DB::raw("CONCAT(first_name, ' ', last_name)"), 'LIKE', "%{$keyword}%")
                            ->orWhere('email', 'LIKE', "%{$keyword}%")
                            ->orWhere('name', 'LIKE', "%{$keyword}%");
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

        $patients = $query->paginate(15);

        // Transform the data for Inertia
        $patients->getCollection()->transform(function ($patient) {
            return [
                'id' => $patient->id,
                'pet_name' => $patient->pet_name,
                'pet_breed' => $patient->pet_breed,
                'pet_gender' => $patient->pet_gender,
                'pet_birth_date' => $patient->pet_birth_date ? $patient->pet_birth_date->toDateString() : null,
                'microchip_number' => $patient->microchip_number,
                'pet_allergies' => $patient->pet_allergies,
                'pet_type' => [
                    'id' => $patient->petType->id ?? null,
                    'name' => $patient->petType->name ?? null,
                ],
                'owner' => $patient->user ? [
                    'id' => $patient->user->id,
                    'name' => trim(($patient->user->first_name ?? '') . ' ' . ($patient->user->last_name ?? '')) ?: $patient->user->name,
                    'email' => $patient->user->email,
                    'mobile_number' => $patient->user->mobile_number ?? null,
                ] : null,
                'created_at' => $patient->created_at->toISOString(),
            ];
        });

        return Inertia::render('Admin/Patients/Index', [
            'patients' => $patients,
            'filters' => $request->only(['search', 'sort_by', 'sort_direction']),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
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

        // Exclude admin and staff users from owner selection
        $users = User::whereDoesntHave('roles', function ($query) {
            $query->whereIn('name', ['admin', 'staff']);
        })->get()->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: $user->name,
                'email' => $user->email,
            ];
        });

        return Inertia::render('Admin/Patients/Create', [
            'pet_types' => $pet_types,
            'pet_breeds' => $pet_breeds,
            'users' => $users,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'pet_type_id' => 'required_without:custom_pet_type_name',
            'custom_pet_type_name' => 'nullable|string|max:100',
            'pet_name' => 'nullable|string|max:100',
            'pet_breed' => 'required_without:custom_pet_breed_name|nullable|string|max:100',
            'custom_pet_breed_name' => 'nullable|string|max:100',
            'pet_gender' => 'nullable|in:Male,Female',
            'pet_birth_date' => 'nullable|date',
            'microchip_number' => 'nullable|string|max:100',
            'pet_allergies' => 'nullable|string',
            'user_id' => 'nullable|exists:users,id',
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
                'microchip_number' => $validated['microchip_number'] ?? null,
                'pet_allergies' => $validated['pet_allergies'] ?? null,
                'user_id' => $validated['user_id'] ?? null,
            ]);

            return redirect()->route('admin.patients.show', $patient->id)
                ->with('success', 'New patient has been created successfully.');
        });
    }

    /**
     * Display the specified resource.
     */
    public function show(Patient $patient)
    {
        $patient->load(['petType', 'user', 'appointments.appointment_type', 'prescriptions', 'weightHistory']);

        return Inertia::render('Admin/Patients/Show', [
            'patient' => [
                'id' => $patient->id,
                'pet_name' => $patient->pet_name,
                'pet_breed' => $patient->pet_breed,
                'pet_gender' => $patient->pet_gender,
                'pet_birth_date' => $patient->pet_birth_date ? $patient->pet_birth_date->toDateString() : null,
                'microchip_number' => $patient->microchip_number,
                'pet_allergies' => $patient->pet_allergies,
                'pet_type' => [
                    'id' => $patient->petType->id ?? null,
                    'name' => $patient->petType->name ?? null,
                ],
                'owner' => $patient->user ? [
                    'id' => $patient->user->id,
                    'name' => trim(($patient->user->first_name ?? '') . ' ' . ($patient->user->last_name ?? '')) ?: $patient->user->name,
                    'email' => $patient->user->email,
                    'mobile_number' => $patient->user->mobile_number ?? null,
                    'address' => $patient->user->address ?? null,
                ] : null,
                'appointments' => $patient->appointments->map(function ($appointment) {
                    return [
                        'id' => $appointment->id,
                        'appointment_type' => $appointment->appointment_type->name ?? null,
                        'appointment_date' => $appointment->appointment_date ? $appointment->appointment_date->toDateString() : null,
                        'appointment_time' => $appointment->appointment_time,
                        'created_at' => $appointment->created_at->toISOString(),
                    ];
                }),
                'prescriptions' => $patient->prescriptions->map(function ($prescription) {
                    return [
                        'id' => $prescription->id,
                        'created_at' => $prescription->created_at->toISOString(),
                    ];
                }),
                'weight_history' => $patient->weightHistory->map(function ($entry) {
                    return [
                        'id' => $entry->id,
                        'weight' => (float) $entry->weight,
                        'recorded_at' => $entry->recorded_at->toISOString(),
                        'notes' => $entry->notes,
                        'prescription_id' => $entry->prescription_id,
                    ];
                }),
                'created_at' => $patient->created_at->toISOString(),
                'updated_at' => $patient->updated_at->toISOString(),
            ],
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Patient $patient)
    {
        $pet_types = PetType::all()->map(function ($pet_type) {
            return [
                'id' => $pet_type->id,
                'name' => $pet_type->name,
            ];
        });

        // Exclude admin and staff users from owner selection
        $users = User::whereDoesntHave('roles', function ($query) {
            $query->whereIn('name', ['admin', 'staff']);
        })->get()->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: $user->name,
                'email' => $user->email,
            ];
        });

        return Inertia::render('Admin/Patients/Edit', [
            'patient' => [
                'id' => $patient->id,
                'pet_type_id' => $patient->pet_type_id,
                'pet_name' => $patient->pet_name,
                'pet_breed' => $patient->pet_breed,
                'pet_gender' => $patient->pet_gender,
                'pet_birth_date' => $patient->pet_birth_date ? $patient->pet_birth_date->toDateString() : null,
                'microchip_number' => $patient->microchip_number,
                'pet_allergies' => $patient->pet_allergies,
                'user_id' => $patient->user_id,
            ],
            'pet_types' => $pet_types,
            'users' => $users,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Patient $patient)
    {
        $validated = $request->validate([
            'pet_type_id' => 'required|exists:pet_types,id',
            'pet_name' => 'nullable|string|max:100',
            'pet_breed' => 'required|string|max:100',
            'pet_gender' => 'nullable|in:Male,Female',
            'pet_birth_date' => 'nullable|date',
            'microchip_number' => 'nullable|string|max:100',
            'pet_allergies' => 'nullable|string',
            'user_id' => 'nullable|exists:users,id',
        ]);

        $patient->update([
            'pet_type_id' => $validated['pet_type_id'],
            'pet_name' => $validated['pet_name'] ?? null,
            'pet_breed' => $validated['pet_breed'],
            'pet_gender' => $validated['pet_gender'] ?? null,
            'pet_birth_date' => $validated['pet_birth_date'] ?? null,
            'microchip_number' => $validated['microchip_number'] ?? null,
            'pet_allergies' => $validated['pet_allergies'] ?? null,
            'user_id' => $validated['user_id'] ?? null,
        ]);

        return redirect()->route('admin.patients.show', $patient->id)
            ->with('success', 'Patient has been updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Patient $patient)
    {
        $patient->delete();

        return redirect()->route('admin.patients.index')
            ->with('success', 'Patient deleted successfully.');
    }

    /**
     * Get weight history for a patient.
     */
    public function getWeightHistory(Patient $patient)
    {
        $weightHistory = $patient->weightHistory()->get()->map(function ($entry) {
            return [
                'id' => $entry->id,
                'weight' => (float) $entry->weight,
                'recorded_at' => $entry->recorded_at->toISOString(),
                'notes' => $entry->notes,
                'prescription_id' => $entry->prescription_id,
            ];
        });

        return response()->json($weightHistory);
    }

    /**
     * Store a new weight entry for a patient.
     */
    public function storeWeightHistory(Request $request, Patient $patient)
    {
        $validated = $request->validate([
            'weight' => 'required|numeric|min:0',
            'recorded_at' => 'nullable|date',
            'notes' => 'nullable|string|max:500',
        ]);

        $weightHistory = PatientWeightHistory::create([
            'patient_id' => $patient->id,
            'weight' => $validated['weight'],
            'recorded_at' => $validated['recorded_at'] ?? now(),
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Weight entry added successfully.',
            'data' => [
                'id' => $weightHistory->id,
                'weight' => (float) $weightHistory->weight,
                'recorded_at' => $weightHistory->recorded_at->toISOString(),
                'notes' => $weightHistory->notes,
            ],
        ]);
    }
}
