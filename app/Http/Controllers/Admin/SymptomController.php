<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Symptom;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SymptomController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Symptom::withCount('diseases');

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

        $symptoms = $query->paginate(15);

        // Transform the data for Inertia
        $symptoms->getCollection()->transform(function ($symptom) {
            return [
                'id' => $symptom->id,
                'name' => $symptom->name,
                'diseases_count' => $symptom->diseases_count ?? 0,
                'created_at' => $symptom->created_at->toISOString(),
            ];
        });

        return Inertia::render('Admin/Symptoms/Index', [
            'symptoms' => $symptoms,
            'filters' => $request->only(['search', 'sort_by', 'sort_direction']),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('Admin/Symptoms/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:symptoms,name',
        ]);

        $symptom = Symptom::create([
            'name' => $validated['name'],
        ]);

        return redirect()->route('admin.symptoms.show', $symptom->id)
            ->with('success', 'New symptom has been created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Symptom $symptom)
    {
        $symptom->loadCount('diseases');
        $symptom->load('diseases');

        return Inertia::render('Admin/Symptoms/Show', [
            'symptom' => [
                'id' => $symptom->id,
                'name' => $symptom->name,
                'diseases_count' => $symptom->diseases_count ?? 0,
                'diseases' => $symptom->diseases->map(fn($disease) => [
                    'id' => $disease->id,
                    'name' => $disease->name,
                ]),
                'created_at' => $symptom->created_at->toISOString(),
                'updated_at' => $symptom->updated_at->toISOString(),
            ],
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Symptom $symptom)
    {
        return Inertia::render('Admin/Symptoms/Edit', [
            'symptom' => [
                'id' => $symptom->id,
                'name' => $symptom->name,
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Symptom $symptom)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:symptoms,name,' . $symptom->id,
        ]);

        $symptom->update([
            'name' => $validated['name'],
        ]);

        return redirect()->route('admin.symptoms.show', $symptom->id)
            ->with('success', 'Symptom has been updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Symptom $symptom)
    {
        $symptom->delete();

        return redirect()->route('admin.symptoms.index')
            ->with('success', 'Symptom deleted successfully.');
    }
}
