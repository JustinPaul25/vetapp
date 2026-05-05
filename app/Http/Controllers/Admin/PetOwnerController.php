<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\PhilippineMobileNumber;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PetOwnerController extends Controller
{
    /** Roles that identify someone as a pet-owning customer (registered app users and walk-ins). */
    private const PET_OWNER_ROLES = ['client', 'walk_in_client'];

    private function assertPetOwnerUser(User $user): void
    {
        abort_unless($user->hasAnyRole(self::PET_OWNER_ROLES), 404);
    }

    /**
     * Display a listing of pet owners (registered clients and walk-in clients).
     */
    public function index(Request $request)
    {
        $query = User::role(self::PET_OWNER_ROLES)
            ->with(['patients.petType', 'roles'])
            ->withCount('patients')
            ->withMin('appointments', 'appointment_date');

        // Search functionality
        if ($request->has('search') && ! empty($request->search)) {
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
        if (! in_array($sortBy, $allowedSortColumns)) {
            $sortBy = 'created_at';
        }

        // Validate sort_direction
        $sortDirection = strtolower($sortDirection) === 'asc' ? 'asc' : 'desc';

        if ($sortBy === 'created_at') {
            $query->orderByRaw("COALESCE(appointments_min_appointment_date, users.created_at) {$sortDirection}");
        } else {
            $query->orderBy($sortBy, $sortDirection);
        }

        $petOwners = $query->paginate(15);

        // Transform the data for Inertia
        $petOwners->getCollection()->transform(function ($user) {
            $firstVisit = $user->appointments_min_appointment_date ?? null;
            $effectiveCreatedAt = $firstVisit
                ? Carbon::parse($firstVisit)->toISOString()
                : $user->created_at->toISOString();
            $isWalkIn = $user->hasRole('walk_in_client');

            return [
                'id' => $user->id,
                'name' => trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: $user->name,
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
                'created_at' => $effectiveCreatedAt,
                'list_date' => $effectiveCreatedAt,
                'is_walk_in_client' => $isWalkIn,
            ];
        });

        return Inertia::render('Admin/PetOwners/Index', [
            'petOwners' => $petOwners,
            'filters' => $request->only(['search', 'sort_by', 'sort_direction']),
        ]);
    }

    /**
     * Show the form for creating a new pet owner.
     */
    public function create()
    {
        return Inertia::render('Admin/PetOwners/Create');
    }

    /**
     * Store a newly created pet owner in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'name' => 'nullable|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'mobile_number' => ['nullable', new PhilippineMobileNumber],
            'address' => 'nullable|string|max:500',
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'first_name' => $validated['first_name'] ?? null,
            'last_name' => $validated['last_name'] ?? null,
            'name' => $validated['name'] ?? trim(($validated['first_name'] ?? '').' '.($validated['last_name'] ?? '')),
            'email' => $validated['email'],
            'mobile_number' => $validated['mobile_number'] ?? null,
            'address' => $validated['address'] ?? null,
            'lat' => $validated['lat'] ?? null,
            'long' => $validated['lng'] ?? null,
            'password' => bcrypt($validated['password']),
        ]);

        // Assign client role to the pet owner
        $user->assignRole('client');

        // Send email verification notification
        $user->sendEmailVerificationNotification();

        return redirect()->route('admin.pet_owners.index')
            ->with('success', 'Pet owner created successfully. Verification email has been sent.');
    }

    /**
     * Display the specified pet owner (registered client or walk-in client).
     */
    public function show(User $petOwner)
    {
        $this->assertPetOwnerUser($petOwner);

        $petOwner->load(['patients.petType', 'appointments']);

        $firstVisitRaw = $petOwner->appointments()->min('appointment_date');
        $firstVisitDate = $firstVisitRaw ? Carbon::parse($firstVisitRaw)->format('Y-m-d') : null;

        return Inertia::render('Admin/PetOwners/Show', [
            'petOwner' => [
                'id' => $petOwner->id,
                'name' => trim(($petOwner->first_name ?? '').' '.($petOwner->last_name ?? '')) ?: $petOwner->name,
                'first_name' => $petOwner->first_name,
                'last_name' => $petOwner->last_name,
                'email' => $petOwner->email,
                'mobile_number' => $petOwner->mobile_number ?? null,
                'address' => $petOwner->address ?? null,
                'lat' => $petOwner->lat ? (float) $petOwner->lat : null,
                'lng' => $petOwner->long ? (float) $petOwner->long : null,
                'patients_count' => $petOwner->patients->count(),
                'patients' => $petOwner->patients->map(function ($patient) {
                    return [
                        'id' => $patient->id,
                        'pet_name' => $patient->pet_name,
                        'pet_breed' => $patient->pet_breed,
                        'pet_gender' => $patient->pet_gender,
                        'pet_birth_date' => $patient->pet_birth_date ? $patient->pet_birth_date->toDateString() : null,
                        'pet_type' => [
                            'id' => $patient->petType->id ?? null,
                            'name' => $patient->petType->name ?? null,
                        ],
                        'created_at' => $patient->created_at->toISOString(),
                    ];
                }),
                'appointments_count' => $petOwner->appointments->count(),
                'created_at' => $petOwner->created_at->toISOString(),
                'first_visit_date' => $firstVisitDate,
                'updated_at' => $petOwner->updated_at->toISOString(),
                'is_walk_in_client' => $petOwner->hasRole('walk_in_client'),
            ],
        ]);
    }

    /**
     * Show the form for editing the specified pet owner (registered client or walk-in client).
     */
    public function edit(User $petOwner)
    {
        $this->assertPetOwnerUser($petOwner);

        return Inertia::render('Admin/PetOwners/Edit', [
            'petOwner' => [
                'id' => $petOwner->id,
                'first_name' => $petOwner->first_name,
                'last_name' => $petOwner->last_name,
                'name' => $petOwner->name,
                'email' => $petOwner->email,
                'mobile_number' => $petOwner->mobile_number ?? null,
                'address' => $petOwner->address ?? null,
                'lat' => $petOwner->lat ? (float) $petOwner->lat : null,
                'lng' => $petOwner->long ? (float) $petOwner->long : null,
            ],
        ]);
    }

    /**
     * Update the specified pet owner (registered client or walk-in client) in storage.
     */
    public function update(Request $request, User $petOwner)
    {
        $this->assertPetOwnerUser($petOwner);

        $validated = $request->validate([
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'name' => 'nullable|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,'.$petOwner->id,
            'mobile_number' => ['nullable', new PhilippineMobileNumber],
            'address' => 'nullable|string|max:500',
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        $updateData = [
            'first_name' => $validated['first_name'] ?? null,
            'last_name' => $validated['last_name'] ?? null,
            'name' => $validated['name'] ?? trim(($validated['first_name'] ?? '').' '.($validated['last_name'] ?? '')),
            'email' => $validated['email'],
            'mobile_number' => $validated['mobile_number'] ?? null,
            'address' => $validated['address'] ?? null,
            'lat' => $validated['lat'] ?? null,
            'long' => $validated['lng'] ?? null,
        ];

        if (! empty($validated['password'])) {
            $updateData['password'] = bcrypt($validated['password']);
        }

        $petOwner->update($updateData);

        return redirect()->route('admin.pet_owners.show', $petOwner->id)
            ->with('success', 'Pet owner updated successfully.');
    }

    /**
     * Remove the specified pet owner (registered client or walk-in client) from storage.
     */
    public function destroy(User $petOwner)
    {
        $this->assertPetOwnerUser($petOwner);

        $petOwner->delete();

        return redirect()->route('admin.pet_owners.index')
            ->with('success', 'Pet owner deleted successfully.');
    }
}
