<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\PhilippineMobileNumber;
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
     * Show the form for creating a new walk-in client.
     */
    public function create()
    {
        return Inertia::render('Admin/WalkInClients/Create');
    }

    /**
     * Store a newly created walk-in client in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'name' => 'nullable|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'mobile_number' => ['nullable', new PhilippineMobileNumber()],
            'address' => 'nullable|string|max:500',
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
        ]);

        // Generate a random password for walk-in clients (they can reset it if needed)
        $password = bcrypt(uniqid('walkin_', true));

        $user = User::create([
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
        $user->assignRole('walk_in_client');

        return redirect()->route('admin.walk_in_clients.index')
            ->with('success', 'Walk-in client created successfully.');
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
