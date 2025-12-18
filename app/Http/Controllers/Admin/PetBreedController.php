<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PetBreed;
use App\Models\PetType;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PetBreedController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = PetBreed::with('petType');

        // Search functionality
        if ($request->has('search') && !empty($request->search)) {
            $query->where('name', 'LIKE', $request->search . '%');
        }

        // Filter by pet type
        if ($request->has('pet_type_id') && !empty($request->pet_type_id)) {
            $query->where('pet_type_id', $request->pet_type_id);
        }

        // Sort functionality
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        
        // Validate sort_by to prevent SQL injection
        $allowedSortColumns = ['name', 'created_at', 'pet_type_id'];
        if (!in_array($sortBy, $allowedSortColumns)) {
            $sortBy = 'created_at';
        }
        
        // Validate sort_direction
        $sortDirection = strtolower($sortDirection) === 'asc' ? 'asc' : 'desc';
        
        $query->orderBy($sortBy, $sortDirection);

        $pet_breeds = $query->paginate(15);

        // Transform the data for Inertia
        $pet_breeds->getCollection()->transform(function ($pet_breed) {
            return [
                'id' => $pet_breed->id,
                'name' => $pet_breed->name,
                'pet_type_id' => $pet_breed->pet_type_id,
                'pet_type_name' => $pet_breed->petType->name ?? 'Unknown',
                'created_at' => $pet_breed->created_at->toISOString(),
            ];
        });

        return Inertia::render('Admin/PetBreeds/Index', [
            'pet_breeds' => $pet_breeds,
            'pet_types' => PetType::orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['search', 'pet_type_id', 'sort_by', 'sort_direction']),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('Admin/PetBreeds/Create', [
            'pet_types' => PetType::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'pet_type_id' => 'required|exists:pet_types,id',
        ]);

        // Check for unique breed name within pet type
        $exists = PetBreed::where('name', $validated['name'])
            ->where('pet_type_id', $validated['pet_type_id'])
            ->exists();

        if ($exists) {
            return back()->withErrors([
                'name' => 'This breed already exists for the selected pet type.',
            ]);
        }

        $pet_breed = PetBreed::create([
            'name' => $validated['name'],
            'pet_type_id' => $validated['pet_type_id'],
        ]);

        return redirect()->route('admin.pet_breeds.show', $pet_breed->id)
            ->with('success', 'New pet breed has been created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(PetBreed $pet_breed)
    {
        $pet_breed->load('petType');

        return Inertia::render('Admin/PetBreeds/Show', [
            'pet_breed' => [
                'id' => $pet_breed->id,
                'name' => $pet_breed->name,
                'pet_type_id' => $pet_breed->pet_type_id,
                'pet_type_name' => $pet_breed->petType->name ?? 'Unknown',
                'created_at' => $pet_breed->created_at->toISOString(),
                'updated_at' => $pet_breed->updated_at->toISOString(),
            ],
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(PetBreed $pet_breed)
    {
        return Inertia::render('Admin/PetBreeds/Edit', [
            'pet_breed' => [
                'id' => $pet_breed->id,
                'name' => $pet_breed->name,
                'pet_type_id' => $pet_breed->pet_type_id,
            ],
            'pet_types' => PetType::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, PetBreed $pet_breed)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'pet_type_id' => 'required|exists:pet_types,id',
        ]);

        // Check for unique breed name within pet type (excluding current breed)
        $exists = PetBreed::where('name', $validated['name'])
            ->where('pet_type_id', $validated['pet_type_id'])
            ->where('id', '!=', $pet_breed->id)
            ->exists();

        if ($exists) {
            return back()->withErrors([
                'name' => 'This breed already exists for the selected pet type.',
            ]);
        }

        $pet_breed->update([
            'name' => $validated['name'],
            'pet_type_id' => $validated['pet_type_id'],
        ]);

        return redirect()->route('admin.pet_breeds.show', $pet_breed->id)
            ->with('success', 'Pet breed has been updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PetBreed $pet_breed)
    {
        $pet_breed->delete();

        return redirect()->route('admin.pet_breeds.index')
            ->with('success', 'Pet breed deleted successfully.');
    }
}



