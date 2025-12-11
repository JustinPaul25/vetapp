<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Medicine;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MedicineController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Medicine::query();

        // Search functionality
        if ($request->has('search') && !empty($request->search)) {
            $query->where('name', 'LIKE', $request->search . '%');
        }

        // Sort functionality
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        
        // Validate sort_by to prevent SQL injection
        $allowedSortColumns = ['name', 'stock', 'dosage', 'route', 'created_at'];
        if (!in_array($sortBy, $allowedSortColumns)) {
            $sortBy = 'created_at';
        }
        
        // Validate sort_direction
        $sortDirection = strtolower($sortDirection) === 'asc' ? 'asc' : 'desc';
        
        $query->orderBy($sortBy, $sortDirection);

        $medicines = $query->paginate(15);

        // Transform the data for Inertia
        $medicines->getCollection()->transform(function ($medicine) {
            return [
                'id' => $medicine->id,
                'name' => $medicine->name,
                'stock' => $medicine->stock,
                'dosage' => $medicine->dosage,
                'route' => $medicine->route,
                'created_at' => $medicine->created_at->toISOString(),
            ];
        });

        return Inertia::render('Admin/Medicines/Index', [
            'medicines' => $medicines,
            'filters' => $request->only(['search', 'sort_by', 'sort_direction']),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('Admin/Medicines/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:medicines,name',
            'stock' => 'required|integer|min:0',
            'dosage' => 'required|string|max:255',
            'route' => 'required|string|max:255',
        ]);

        $medicine = Medicine::create([
            'name' => $validated['name'],
            'stock' => $validated['stock'],
            'dosage' => $validated['dosage'],
            'route' => $validated['route'],
        ]);

        return redirect()->route('admin.medicines.show', $medicine->id)
            ->with('success', 'New medicine has been created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Medicine $medicine)
    {
        return Inertia::render('Admin/Medicines/Show', [
            'medicine' => [
                'id' => $medicine->id,
                'name' => $medicine->name,
                'stock' => $medicine->stock,
                'dosage' => $medicine->dosage,
                'route' => $medicine->route,
                'created_at' => $medicine->created_at->toISOString(),
                'updated_at' => $medicine->updated_at->toISOString(),
            ],
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Medicine $medicine)
    {
        return Inertia::render('Admin/Medicines/Edit', [
            'medicine' => [
                'id' => $medicine->id,
                'name' => $medicine->name,
                'stock' => $medicine->stock,
                'dosage' => $medicine->dosage,
                'route' => $medicine->route,
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Medicine $medicine)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:medicines,name,' . $medicine->id,
            'stock' => 'required|integer|min:0',
            'dosage' => 'required|string|max:255',
            'route' => 'required|string|max:255',
        ]);

        $medicine->update([
            'name' => $validated['name'],
            'stock' => $validated['stock'],
            'dosage' => $validated['dosage'],
            'route' => $validated['route'],
        ]);

        return redirect()->route('admin.medicines.show', $medicine->id)
            ->with('success', 'Medicine has been updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Medicine $medicine)
    {
        $medicine->delete();

        return redirect()->route('admin.medicines.index')
            ->with('success', 'Medicine deleted successfully.');
    }
}
