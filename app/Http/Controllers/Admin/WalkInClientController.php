<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Patient;
use App\Models\PetType;
use App\Models\PetBreed;
use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Rules\PhilippineMobileNumber;
use App\Services\AppointmentLimitService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class WalkInClientController extends Controller
{
    /**
     * Display a listing of walk-in clients (users with walk_in_client role).
     */
    public function index(Request $request)
    {
        $query = User::role('walk_in_client')
            ->with(['patients.petType', 'roles'])
            ->withCount('patients');

        // Search functionality
        if ($request->has('search') && !empty($request->search)) {
            $keyword = $request->search;
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'LIKE', "%{$keyword}%")
                    ->orWhere('email', 'LIKE', "%{$keyword}%")
                    ->orWhere('first_name', 'LIKE', "%{$keyword}%")
                    ->orWhere('last_name', 'LIKE', "%{$keyword}%")
                    ->orWhere('mobile_number', 'LIKE', "%{$keyword}%")
                    ->orWhere(DB::raw("CONCAT(first_name, ' ', last_name)"), 'LIKE', "%{$keyword}%");
            });
        }

        // Sort functionality
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        
        // Validate sort_by to prevent SQL injection
        $allowedSortColumns = ['name', 'email', 'created_at'];
        if (!in_array($sortBy, $allowedSortColumns)) {
            $sortBy = 'created_at';
        }
        
        // Validate sort_direction
        $sortDirection = strtolower($sortDirection) === 'asc' ? 'asc' : 'desc';
        
        $query->orderBy($sortBy, $sortDirection);

        $walkInClients = $query->paginate(15);

        // Transform the data for Inertia
        $walkInClients->getCollection()->transform(function ($user) {
            return [
                'id' => $user->id,
                'name' => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: $user->name,
                'email' => $user->email,
                'mobile_number' => $user->mobile_number ?? null,
                'address' => $user->address ?? null,
                'patients_count' => $user->patients_count,
                'patients' => $user->patients->take(3)->map(function ($patient) {
                    return [
                        'id' => $patient->id,
                        'pet_name' => $patient->pet_name,
                        'pet_breed' => $patient->pet_breed,
                        'pet_type' => $patient->petType->name ?? null,
                    ];
                }),
                'created_at' => $user->created_at->toISOString(),
            ];
        });

        return Inertia::render('Admin/WalkInClients/Index', [
            'walkInClients' => $walkInClients,
            'filters' => $request->only(['search', 'sort_by', 'sort_direction']),
        ]);
    }

    /**
     * Lookup client by email for autofill.
     */
    public function lookupByEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $email = $request->input('email');
        
        $user = User::where('email', $email)->first();

        if (!$user) {
            return response()->json([
                'found' => false,
                'client' => null,
            ]);
        }

        return response()->json([
            'found' => true,
            'client' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'name' => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: $user->name,
                'email' => $user->email,
                'mobile_number' => $user->mobile_number,
                'address' => $user->address,
                'lat' => $user->lat ? (float) $user->lat : null,
                'lng' => $user->long ? (float) $user->long : null,
            ],
        ]);
    }

    /**
     * Search for pets by name.
     */
    public function searchPets(Request $request)
    {
        $request->validate([
            'pet_name' => 'required|string|min:1',
        ]);

        $petName = $request->input('pet_name');
        
        $pets = Patient::where('pet_name', 'LIKE', "%{$petName}%")
            ->with(['user', 'petType'])
            ->get()
            ->map(function ($pet) {
                return [
                    'id' => $pet->id,
                    'pet_name' => $pet->pet_name,
                    'pet_breed' => $pet->pet_breed,
                    'pet_gender' => $pet->pet_gender,
                    'pet_birth_date' => $pet->pet_birth_date ? $pet->pet_birth_date->format('Y-m-d') : null,
                    'microchip_number' => $pet->microchip_number,
                    'pet_allergies' => $pet->pet_allergies,
                    'pet_type' => [
                        'id' => $pet->petType->id ?? null,
                        'name' => $pet->petType->name ?? null,
                    ],
                    'owner' => $pet->user ? [
                        'id' => $pet->user->id,
                        'name' => trim(($pet->user->first_name ?? '') . ' ' . ($pet->user->last_name ?? '')) ?: $pet->user->name,
                        'first_name' => $pet->user->first_name,
                        'last_name' => $pet->user->last_name,
                        'email' => $pet->user->email,
                        'mobile_number' => $pet->user->mobile_number,
                        'address' => $pet->user->address,
                        'lat' => $pet->user->lat ? (float) $pet->user->lat : null,
                        'lng' => $pet->user->long ? (float) $pet->user->long : null,
                    ] : null,
                ];
            });

        return response()->json([
            'pets' => $pets,
        ]);
    }

    /**
     * Show the form for creating a new walk-in client.
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

        $appointment_types = AppointmentType::all()->map(function ($type) {
            return [
                'id' => $type->id,
                'name' => $type->name,
            ];
        });

        return Inertia::render('Admin/WalkInClients/Create', [
            'pet_types' => $pet_types,
            'pet_breeds' => $pet_breeds,
            'appointment_types' => $appointment_types,
        ]);
    }

    /**
     * Store a newly created walk-in client in storage.
     * 
     * If existing_pet_id and existing_owner_id are provided, use those records.
     * Otherwise:
     * Step 1: Check if client already exists
     * Step 1.2: If client doesn't exist, create the client
     * Step 2: Check if pet already exists for the client
     * Step 2.2: If pet doesn't exist, create the pet
     */
    public function store(Request $request)
    {
        // Check if using existing pet and owner
        $usingExistingRecords = $request->filled('existing_pet_id') && $request->filled('existing_owner_id');

        if ($usingExistingRecords) {
            // Simplified validation for existing records
            $validated = $request->validate([
                'existing_owner_id' => 'required|exists:users,id',
                'existing_pet_id' => 'required|exists:patients,id',
                'appointment_type_id' => 'required|exists:appointment_types,id',
                'symptoms' => 'nullable|string|max:1825',
            ]);

            return DB::transaction(function () use ($validated) {
                $existingClient = User::findOrFail($validated['existing_owner_id']);
                $existingPet = Patient::findOrFail($validated['existing_pet_id']);

                // Ensure owner has walk_in_client role
                if (!$existingClient->hasRole('walk_in_client')) {
                    $existingClient->assignRole('walk_in_client');
                }

                // Create appointment
                $appointmentDate = now()->toDateString();
                $appointmentTime = now()->format('H:i');

                // Validate daily appointment limits
                $limitService = app(AppointmentLimitService::class);
                $limitCheck = $limitService->checkDailyLimit($validated['appointment_type_id'], $appointmentDate);
                
                if (!$limitCheck['available']) {
                    return back()->withErrors([
                        'appointment_type_id' => sprintf(
                            'Daily limit reached for %s appointments. Current: %d/%d',
                            $limitCheck['appointment_type'],
                            $limitCheck['current_count'],
                            $limitCheck['limit']
                        ),
                    ])->withInput();
                }

                $appointment = Appointment::create([
                    'patient_id' => $existingPet->id,
                    'appointment_type_id' => $validated['appointment_type_id'],
                    'appointment_date' => $appointmentDate,
                    'appointment_time' => $appointmentTime,
                    'symptoms' => $validated['symptoms'] ?? '',
                    'is_approved' => true,
                    'user_id' => $existingClient->id,
                ]);

                $appointment->patients()->sync([$existingPet->id]);
                $appointment->appointment_types()->sync([$validated['appointment_type_id']]);

                return redirect()->route('admin.appointments.prescription.create', $appointment->id)
                    ->with('success', 'Appointment created successfully for existing client and pet.');
            });
        }

        // Full validation for new records
        $validated = $request->validate([
            // Client fields
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'name' => 'nullable|string|max:255',
            'email' => 'required|string|email|max:255',
            'mobile_number' => ['nullable', new PhilippineMobileNumber()],
            'address' => 'nullable|string|max:500',
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
            // Pet fields - pet_type_id is required unless custom_pet_type_name is provided
            'pet_type_id' => 'required_without:custom_pet_type_name',
            'custom_pet_type_name' => 'nullable|string|max:100',
            'pet_name' => 'nullable|string|max:100',
            'pet_breed' => 'required_without:custom_pet_breed_name|nullable|string|max:100',
            'custom_pet_breed_name' => 'nullable|string|max:100',
            'pet_gender' => 'nullable|in:Male,Female',
            'pet_birth_date' => 'nullable|date',
            'microchip_number' => 'nullable|string|max:100',
            'pet_allergies' => 'nullable|string',
            // Appointment fields (always required for walk-in)
            'appointment_type_id' => 'required|exists:appointment_types,id',
            'symptoms' => 'nullable|string|max:1825',
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
            
            // Update validated array with resolved pet_type_id
            $validated['pet_type_id'] = $petTypeId;

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
            
            // Update validated array with resolved breed
            $validated['pet_breed'] = $petBreed;
            // Step 1: Check if client already exists
            // Check by email first (primary identifier)
            $existingClient = User::where('email', $validated['email'])->first();

            // If client doesn't exist, check by mobile number as secondary check
            if (!$existingClient && !empty($validated['mobile_number'])) {
                $existingClient = User::where('mobile_number', $validated['mobile_number'])->first();
            }

            $clientCreated = false;

            if (!$existingClient) {
                // Step 1.2: Client doesn't exist - create the client
                // Generate a random password for walk-in clients (they can reset it if needed)
                $password = bcrypt(uniqid('walkin_', true));

                $existingClient = User::create([
                    'first_name' => $validated['first_name'] ?? null,
                    'last_name' => $validated['last_name'] ?? null,
                    'name' => $validated['name'] ?? trim(($validated['first_name'] ?? '') . ' ' . ($validated['last_name'] ?? '')),
                    'email' => $validated['email'],
                    'mobile_number' => $validated['mobile_number'] ?? null,
                    'address' => $validated['address'] ?? null,
                    'lat' => $validated['lat'] ?? null,
                    'long' => $validated['lng'] ?? null,
                    'password' => $password,
                    'email_verified_at' => now(), // Auto-verify walk-in clients
                ]);

                // Assign walk_in_client role to the user
                $existingClient->assignRole('walk_in_client');
                $clientCreated = true;
            } else {
                // Client exists - ensure they have the walk_in_client role
                if (!$existingClient->hasRole('walk_in_client')) {
                    $existingClient->assignRole('walk_in_client');
                }
            }

            // Step 2: After client check, check if pet already exists for this client
            $existingPet = null;
            $petQuery = Patient::where('user_id', $existingClient->id)
                ->where('pet_breed', $validated['pet_breed'])
                ->where('pet_type_id', $validated['pet_type_id']);

            // If pet_name is provided, also check by name
            if (!empty($validated['pet_name'])) {
                $petQuery->where('pet_name', $validated['pet_name']);
            }

            $existingPet = $petQuery->first();

            $petCreated = false;
            if (!$existingPet) {
                // Step 2.2: Pet doesn't exist - create pet for the client
                $existingPet = Patient::create([
                    'pet_type_id' => $validated['pet_type_id'],
                    'pet_name' => $validated['pet_name'] ?? null,
                    'pet_breed' => $validated['pet_breed'],
                    'pet_gender' => $validated['pet_gender'] ?? null,
                    'pet_birth_date' => $validated['pet_birth_date'] ?? null,
                    'microchip_number' => $validated['microchip_number'] ?? null,
                    'pet_allergies' => $validated['pet_allergies'] ?? null,
                    'user_id' => $existingClient->id,
                ]);
                $petCreated = true;
            }

            // Step 3: Create appointment (always for walk-in clients)
            // Auto-fill date and time with current values
            $appointmentDate = now()->toDateString();
            $appointmentTime = now()->format('H:i');

            // Validate daily appointment limits
            $limitService = app(AppointmentLimitService::class);
            $limitCheck = $limitService->checkDailyLimit($validated['appointment_type_id'], $appointmentDate);
            
            if (!$limitCheck['available']) {
                return back()->withErrors([
                    'appointment_type_id' => sprintf(
                        'Daily limit reached for %s appointments. Current: %d/%d',
                        $limitCheck['appointment_type'],
                        $limitCheck['current_count'],
                        $limitCheck['limit']
                    ),
                ])->withInput();
            }

            $appointment = Appointment::create([
                'patient_id' => $existingPet->id,
                'appointment_type_id' => $validated['appointment_type_id'],
                'appointment_date' => $appointmentDate,
                'appointment_time' => $appointmentTime,
                'symptoms' => $validated['symptoms'] ?? '',
                'is_approved' => true, // Walk-in appointments are auto-approved
                'user_id' => $existingClient->id,
            ]);

            // Sync many-to-many relationship for patients
            $appointment->patients()->sync([$existingPet->id]);
            
            // Sync many-to-many relationship for appointment types
            $appointment->appointment_types()->sync([$validated['appointment_type_id']]);
            
            $appointmentCreated = true;

            // Prepare success message
            $messages = [];
            if ($clientCreated) {
                $messages[] = 'Walk-in client created successfully.';
            } else {
                $messages[] = 'Client already exists.';
            }
            if ($petCreated) {
                $messages[] = 'Pet registered successfully.';
            } else {
                $messages[] = 'Pet already registered for this client.';
            }
            if ($appointmentCreated) {
                $messages[] = 'Appointment created successfully.';
            }

            return redirect()->route('admin.appointments.prescription.create', $appointment->id)
                ->with('success', implode(' ', $messages));
        });
    }

    /**
     * Display the specified walk-in client.
     */
    public function show(User $walkInClient)
    {
        // Verify this is a walk-in client
        if (!$walkInClient->hasRole('walk_in_client')) {
            abort(404);
        }

        $walkInClient->load(['patients.petType', 'appointments']);
        
        return Inertia::render('Admin/WalkInClients/Show', [
            'walkInClient' => [
                'id' => $walkInClient->id,
                'name' => trim(($walkInClient->first_name ?? '') . ' ' . ($walkInClient->last_name ?? '')) ?: $walkInClient->name,
                'first_name' => $walkInClient->first_name,
                'last_name' => $walkInClient->last_name,
                'email' => $walkInClient->email,
                'mobile_number' => $walkInClient->mobile_number ?? null,
                'address' => $walkInClient->address ?? null,
                'lat' => $walkInClient->lat ? (float) $walkInClient->lat : null,
                'lng' => $walkInClient->long ? (float) $walkInClient->long : null,
                'patients_count' => $walkInClient->patients->count(),
                'patients' => $walkInClient->patients->map(function ($patient) {
                    return [
                        'id' => $patient->id,
                        'pet_name' => $patient->pet_name,
                        'pet_breed' => $patient->pet_breed,
                        'pet_gender' => $patient->pet_gender,
                        'pet_birth_date' => $patient->pet_birth_date ? $patient->pet_birth_date->toDateString() : null,
                        'microchip_number' => $patient->microchip_number,
                        'pet_type' => [
                            'id' => $patient->petType->id ?? null,
                            'name' => $patient->petType->name ?? null,
                        ],
                        'created_at' => $patient->created_at->toISOString(),
                    ];
                }),
                'appointments_count' => $walkInClient->appointments->count(),
                'created_at' => $walkInClient->created_at->toISOString(),
                'updated_at' => $walkInClient->updated_at->toISOString(),
            ],
        ]);
    }

    /**
     * Show the form for editing the specified walk-in client.
     */
    public function edit(User $walkInClient)
    {
        // Verify this is a walk-in client
        if (!$walkInClient->hasRole('walk_in_client')) {
            abort(404);
        }

        return Inertia::render('Admin/WalkInClients/Edit', [
            'walkInClient' => [
                'id' => $walkInClient->id,
                'first_name' => $walkInClient->first_name,
                'last_name' => $walkInClient->last_name,
                'name' => $walkInClient->name,
                'email' => $walkInClient->email,
                'mobile_number' => $walkInClient->mobile_number ?? null,
                'address' => $walkInClient->address ?? null,
                'lat' => $walkInClient->lat ? (float) $walkInClient->lat : null,
                'lng' => $walkInClient->long ? (float) $walkInClient->long : null,
            ],
        ]);
    }

    /**
     * Update the specified walk-in client in storage.
     */
    public function update(Request $request, User $walkInClient)
    {
        // Verify this is a walk-in client
        if (!$walkInClient->hasRole('walk_in_client')) {
            abort(404);
        }

        $validated = $request->validate([
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'name' => 'nullable|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $walkInClient->id,
            'mobile_number' => ['nullable', new PhilippineMobileNumber()],
            'address' => 'nullable|string|max:500',
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
        ]);

        $updateData = [
            'first_name' => $validated['first_name'] ?? null,
            'last_name' => $validated['last_name'] ?? null,
            'name' => $validated['name'] ?? trim(($validated['first_name'] ?? '') . ' ' . ($validated['last_name'] ?? '')),
            'email' => $validated['email'],
            'mobile_number' => $validated['mobile_number'] ?? null,
            'address' => $validated['address'] ?? null,
            'lat' => $validated['lat'] ?? null,
            'long' => $validated['lng'] ?? null,
        ];

        $walkInClient->update($updateData);

        return redirect()->route('admin.walk_in_clients.show', $walkInClient->id)
            ->with('success', 'Walk-in client updated successfully.');
    }

    /**
     * Remove the specified walk-in client from storage.
     */
    public function destroy(User $walkInClient)
    {
        // Verify this is a walk-in client
        if (!$walkInClient->hasRole('walk_in_client')) {
            abort(404);
        }

        $walkInClient->delete();

        return redirect()->route('admin.walk_in_clients.index')
            ->with('success', 'Walk-in client deleted successfully.');
    }
}


