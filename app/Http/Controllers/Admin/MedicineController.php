<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Medicine;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Traits\HasDateFiltering;
use Barryvdh\DomPDF\Facade\Pdf;

class MedicineController extends Controller
{
    use HasDateFiltering;
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

        // Date filtering
        $this->applyDateFilter($query, $request, 'created_at');

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

    /**
     * Bulk update stock for multiple medicines with individual values.
     */
    public function bulkUpdateStock(Request $request)
    {
        $validated = $request->validate([
            'updates' => 'required|array|min:1',
            'updates.*.id' => 'required|integer|exists:medicines,id',
            'updates.*.stock' => 'required|integer|min:0',
        ]);

        $updates = $validated['updates'];
        $updatedCount = 0;

        // Update each medicine with its individual stock value
        foreach ($updates as $update) {
            Medicine::where('id', $update['id'])->update([
                'stock' => $update['stock'],
                'updated_at' => now(),
            ]);
            $updatedCount++;
        }

        return redirect()->route('admin.medicines.index')
            ->with('success', "Stock updated successfully for {$updatedCount} medicine(s).");
    }

    /**
     * Export medicines report.
     */
    public function export(Request $request)
    {
        $query = Medicine::query();

        if ($request->has('search') && !empty($request->search)) {
            $query->where('name', 'LIKE', $request->search . '%');
        }

        // No date filtering for medicines report - print all records
        $medicines = $query->orderBy('created_at', 'desc')->get();

        $format = $request->get('format', 'pdf');

        if ($format === 'csv') {
            return $this->exportCsv($medicines);
        }

        return $this->exportPdf($medicines, $request);
    }

    private function exportPdf($medicines, $request)
    {
        $data = $medicines->map(function ($medicine) {
            return [
                'name' => $medicine->name,
                'stock' => $medicine->stock,
                'dosage' => $medicine->dosage,
                'route' => $medicine->route,
                'created_at' => $medicine->created_at->format('Y-m-d'),
            ];
        });

        $filterInfo = $this->getFilterInfo($request);

        $pdf = Pdf::loadView('admin.reports.medicines', [
            'medicines' => $data,
            'title' => 'Medicines Report',
            'filterInfo' => $filterInfo,
            'total' => $data->count(),
        ]);

        return $pdf->stream('medicines-report-' . date('Y-m-d') . '.pdf');
    }

    private function exportCsv($medicines)
    {
        $filename = 'medicines-report-' . date('Y-m-d') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($medicines) {
            $file = fopen('php://output', 'w');
            
            fputcsv($file, ['Name', 'Stock', 'Dosage', 'Route', 'Created At']);

            foreach ($medicines as $medicine) {
                fputcsv($file, [
                    $medicine->name,
                    $medicine->stock,
                    $medicine->dosage,
                    $medicine->route,
                    $medicine->created_at->format('Y-m-d'),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function getFilterInfo($request)
    {
        // Medicines report always shows all records without date filtering
        return 'All Records';
    }
}
