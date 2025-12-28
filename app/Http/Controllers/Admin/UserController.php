<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;
use App\Traits\HasDateFiltering;
use Barryvdh\DomPDF\Facade\Pdf;

class UserController extends Controller
{
    use HasDateFiltering;
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = User::with('roles');

        // Search functionality
        if ($request->has('search') && !empty($request->search)) {
            $keyword = $request->search;
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'LIKE', "%{$keyword}%")
                    ->orWhere('email', 'LIKE', "%{$keyword}%");
            });
        }

        // Date filtering
        $this->applyDateFilter($query, $request, 'created_at');

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

        $users = $query->paginate(15);

        // Transform the data for Inertia
        $users->getCollection()->transform(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles->map(fn($role) => ['name' => $role->name]),
                'created_at' => $user->created_at->toISOString(),
            ];
        });

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => $request->only(['search', 'sort_by', 'sort_direction']),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $roles = Role::all();

        return Inertia::render('Admin/Users/Create', [
            'roles' => $roles,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'roles' => 'nullable|array',
            'roles.*' => 'exists:roles,name',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => bcrypt($validated['password']),
        ]);

        if (isset($validated['roles'])) {
            $user->syncRoles($validated['roles']);
        }

        // Send email verification notification
        $user->sendEmailVerificationNotification();

        return redirect()->route('admin.users.index')
            ->with('success', 'User created successfully. Verification email has been sent.');
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        $user->load('roles', 'permissions');
        $roles = Role::all();

        return Inertia::render('Admin/Users/Show', [
            'user' => $user,
            'roles' => $roles,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user)
    {
        $user->load('roles');
        $roles = Role::all();

        return Inertia::render('Admin/Users/Edit', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles->map(fn($role) => ['name' => $role->name]),
            ],
            'roles' => $roles,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:8|confirmed',
            'roles' => 'nullable|array',
            'roles.*' => 'exists:roles,name',
        ]);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);

        if (!empty($validated['password'])) {
            $user->update([
                'password' => bcrypt($validated['password']),
            ]);
        }

        if (isset($validated['roles'])) {
            $user->syncRoles($validated['roles']);
        }

        return redirect()->route('admin.users.index')
            ->with('success', 'User updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('success', 'User deleted successfully.');
    }

    /**
     * Export users report.
     */
    public function export(Request $request)
    {
        $query = User::with('roles');

        if ($request->has('search') && !empty($request->search)) {
            $keyword = $request->search;
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'LIKE', "%{$keyword}%")
                    ->orWhere('email', 'LIKE', "%{$keyword}%");
            });
        }

        $this->applyDateFilter($query, $request, 'created_at');

        $users = $query->orderBy('created_at', 'desc')->get();

        $format = $request->get('format', 'pdf');

        if ($format === 'csv') {
            return $this->exportCsv($users);
        }

        return $this->exportPdf($users, $request);
    }

    private function exportPdf($users, $request)
    {
        $data = $users->map(function ($user) {
            return [
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles->pluck('name')->join(', ') ?: 'No roles',
                'created_at' => $user->created_at->format('Y-m-d'),
            ];
        });

        $filterInfo = $this->getFilterInfo($request);

        $pdf = Pdf::loadView('admin.reports.users', [
            'users' => $data,
            'title' => 'Users Report',
            'filterInfo' => $filterInfo,
            'total' => $data->count(),
        ]);

        return $pdf->stream('users-report-' . date('Y-m-d') . '.pdf');
    }

    private function exportCsv($users)
    {
        $filename = 'users-report-' . date('Y-m-d') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($users) {
            $file = fopen('php://output', 'w');
            
            fputcsv($file, ['Name', 'Email', 'Roles', 'Created At']);

            foreach ($users as $user) {
                fputcsv($file, [
                    $user->name,
                    $user->email,
                    $user->roles->pluck('name')->join(', ') ?: 'No roles',
                    $user->created_at->format('Y-m-d'),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function getFilterInfo($request)
    {
        $filterType = $request->get('filter_type');
        
        switch ($filterType) {
            case 'date':
                return 'Date: ' . $request->get('date');
            case 'month':
                $month = $request->get('month');
                $year = $request->get('year');
                $monthName = date('F', mktime(0, 0, 0, (int)$month, 1));
                return "Month: {$monthName} {$year}";
            case 'year':
                return 'Year: ' . $request->get('year');
            case 'range':
                return 'Range: ' . $request->get('date_from') . ' to ' . $request->get('date_to');
            default:
                return 'All Records';
        }
    }
}
