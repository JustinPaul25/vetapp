<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PetType;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PetTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = PetType::query();

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

        $pet_types = $query->paginate(15);

        // Transform the data for Inertia
        $pet_types->getCollection()->transform(function ($pet_type) {
            return [
                'id' => $pet_type->id,
                'name' => $pet_type->name,
                'created_at' => $pet_type->created_at->toISOString(),
            ];
        });

        return Inertia::render('Admin/PetTypes/Index', [
            'pet_types' => $pet_types,
            'filters' => $request->only(['search', 'sort_by', 'sort_direction']),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('Admin/PetTypes/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:pet_types,name',
        ]);

        $pet_type = PetType::create([
            'name' => $validated['name'],
        ]);

        return redirect()->route('admin.pet_types.show', $pet_type->id)
            ->with('success', 'New pet type has been created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(PetType $pet_type)
    {
        return Inertia::render('Admin/PetTypes/Show', [
            'pet_type' => [
                'id' => $pet_type->id,
                'name' => $pet_type->name,
                'created_at' => $pet_type->created_at->toISOString(),
                'updated_at' => $pet_type->updated_at->toISOString(),
            ],
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(PetType $pet_type)
    {
        return Inertia::render('Admin/PetTypes/Edit', [
            'pet_type' => [
                'id' => $pet_type->id,
                'name' => $pet_type->name,
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, PetType $pet_type)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:pet_types,name,' . $pet_type->id,
        ]);

        $pet_type->update([
            'name' => $validated['name'],
        ]);

        return redirect()->route('admin.pet_types.show', $pet_type->id)
            ->with('success', 'Pet type has been updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PetType $pet_type)
    {
        $pet_type->delete();

        return redirect()->route('admin.pet_types.index')
            ->with('success', 'Pet type deleted successfully.');
    }
}
