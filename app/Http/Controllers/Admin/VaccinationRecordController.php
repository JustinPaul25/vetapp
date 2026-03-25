<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\User;
use App\Models\VaccinationRecord;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class VaccinationRecordController extends Controller
{
    public function index(Request $request)
    {
        $query = VaccinationRecord::query()->with(['user', 'patient.petType']);

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('vaccine_name', 'like', '%'.$search.'%')
                    ->orWhere('batch_lot_number', 'like', '%'.$search.'%')
                    ->orWhere('notes', 'like', '%'.$search.'%')
                    ->orWhere('owner_name', 'like', '%'.$search.'%')
                    ->orWhere('pet_name', 'like', '%'.$search.'%')
                    ->orWhere('pet_breed', 'like', '%'.$search.'%')
                    ->orWhere('pet_color', 'like', '%'.$search.'%');
            });
        }

        $sortBy = $request->get('sort_by', 'administered_at');
        $sortDirection = strtolower($request->get('sort_direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSort = ['administered_at', 'vaccine_name', 'created_at'];
        if (! in_array($sortBy, $allowedSort, true)) {
            $sortBy = 'administered_at';
        }
        $query->orderBy($sortBy, $sortDirection)->orderByDesc('id');

        $records = $query->paginate(15)->through(function (VaccinationRecord $record) {
            $ownerLabel = null;
            if ($record->user) {
                $ownerLabel = trim(($record->user->first_name ?? '').' '.($record->user->last_name ?? '')) ?: $record->user->name;
            }
            if ($ownerLabel === null || $ownerLabel === '') {
                $ownerLabel = $record->owner_name;
            }

            $petLabel = null;
            if ($record->patient) {
                $petLabel = trim(($record->patient->pet_name ?: 'Unnamed').' ('.($record->patient->petType->name ?? 'Pet').')');
            } elseif ($record->pet_name) {
                $petLabel = $record->pet_breed
                    ? $record->pet_name.' ('.$record->pet_breed.')'
                    : $record->pet_name;
            }

            return [
                'id' => $record->id,
                'vaccine_name' => $record->vaccine_name,
                'administered_at' => $record->administered_at?->toDateString(),
                'next_due_at' => $record->next_due_at?->toDateString(),
                'user_id' => $record->user_id,
                'owner_label' => $ownerLabel,
                'patient_id' => $record->patient_id,
                'pet_label' => $petLabel,
                'source' => $record->source,
                'created_at' => $record->created_at->toISOString(),
            ];
        });

        return Inertia::render('Admin/VaccinationRecords/Index', [
            'records' => $records,
            'filters' => $request->only(['search', 'sort_by', 'sort_direction']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/VaccinationRecords/Create', $this->formOptions());
    }

    public function store(Request $request)
    {
        $validated = $this->validateRecord($request);
        $validated = $this->normalizeOwnerPatientLinks($validated);

        $validated['source'] = $validated['source'] ?? 'manual';

        VaccinationRecord::create($validated);

        return redirect()->route('admin.vaccination_records.index')
            ->with('success', 'Vaccination record created.');
    }

    public function show(VaccinationRecord $vaccination_record)
    {
        $vaccination_record->load(['user', 'patient.petType']);

        $owner = null;
        if ($vaccination_record->user) {
            $owner = [
                'id' => $vaccination_record->user->id,
                'name' => trim(($vaccination_record->user->first_name ?? '').' '.($vaccination_record->user->last_name ?? '')) ?: $vaccination_record->user->name,
                'email' => $vaccination_record->user->email,
            ];
        }

        $patient = null;
        if ($vaccination_record->patient) {
            $patient = [
                'id' => $vaccination_record->patient->id,
                'pet_name' => $vaccination_record->patient->pet_name,
                'pet_breed' => $vaccination_record->patient->pet_breed,
                'pet_type' => $vaccination_record->patient->petType->name ?? null,
            ];
        }

        return Inertia::render('Admin/VaccinationRecords/Show', [
            'record' => [
                'id' => $vaccination_record->id,
                'vaccine_name' => $vaccination_record->vaccine_name,
                'administered_at' => $vaccination_record->administered_at?->toDateString(),
                'next_due_at' => $vaccination_record->next_due_at?->toDateString(),
                'owner_name' => $vaccination_record->owner_name,
                'pet_name' => $vaccination_record->pet_name,
                'pet_sex' => $vaccination_record->pet_sex,
                'pet_date_of_birth' => $vaccination_record->pet_date_of_birth?->toDateString(),
                'pet_breed' => $vaccination_record->pet_breed,
                'pet_color' => $vaccination_record->pet_color,
                'batch_lot_number' => $vaccination_record->batch_lot_number,
                'veterinarian' => $vaccination_record->veterinarian,
                'notes' => $vaccination_record->notes,
                'source' => $vaccination_record->source,
                'owner' => $owner,
                'patient' => $patient,
                'created_at' => $vaccination_record->created_at->toISOString(),
                'updated_at' => $vaccination_record->updated_at->toISOString(),
            ],
        ]);
    }

    public function edit(VaccinationRecord $vaccination_record)
    {
        return Inertia::render('Admin/VaccinationRecords/Edit', array_merge($this->formOptions(), [
            'record' => [
                'id' => $vaccination_record->id,
                'user_id' => $vaccination_record->user_id,
                'patient_id' => $vaccination_record->patient_id,
                'vaccine_name' => $vaccination_record->vaccine_name,
                'administered_at' => $vaccination_record->administered_at?->toDateString(),
                'next_due_at' => $vaccination_record->next_due_at?->toDateString(),
                'owner_name' => $vaccination_record->owner_name,
                'pet_name' => $vaccination_record->pet_name,
                'pet_sex' => $vaccination_record->pet_sex,
                'pet_date_of_birth' => $vaccination_record->pet_date_of_birth?->toDateString(),
                'pet_breed' => $vaccination_record->pet_breed,
                'pet_color' => $vaccination_record->pet_color,
                'batch_lot_number' => $vaccination_record->batch_lot_number,
                'veterinarian' => $vaccination_record->veterinarian,
                'notes' => $vaccination_record->notes,
                'source' => $vaccination_record->source,
            ],
        ]));
    }

    public function update(Request $request, VaccinationRecord $vaccination_record)
    {
        $validated = $this->validateRecord($request);
        $validated = $this->normalizeOwnerPatientLinks($validated);

        if (empty($validated['source'])) {
            $validated['source'] = $vaccination_record->source ?? 'manual';
        }

        $vaccination_record->update($validated);

        return redirect()->route('admin.vaccination_records.show', $vaccination_record)
            ->with('success', 'Vaccination record updated.');
    }

    public function destroy(VaccinationRecord $vaccination_record)
    {
        $vaccination_record->delete();

        return redirect()->route('admin.vaccination_records.index')
            ->with('success', 'Vaccination record deleted.');
    }

    private function formOptions(): array
    {
        $users = User::query()
            ->whereDoesntHave('roles', function ($query) {
                $query->whereIn('name', ['admin', 'staff']);
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: $user->name,
                'email' => $user->email,
            ]);

        $patients = Patient::query()
            ->with('petType', 'user')
            ->orderBy('pet_name')
            ->get()
            ->map(fn (Patient $patient) => [
                'id' => $patient->id,
                'pet_name' => $patient->pet_name,
                'pet_breed' => $patient->pet_breed,
                'user_id' => $patient->user_id,
                'label' => trim(($patient->pet_name ?: 'Unnamed').' — '.($patient->petType->name ?? 'Pet').($patient->user ? ' ('.(trim(($patient->user->first_name ?? '').' '.($patient->user->last_name ?? '')) ?: $patient->user->name).')' : '')),
            ]);

        return [
            'users' => $users,
            'patients' => $patients,
        ];
    }

    private function validateRecord(Request $request): array
    {
        return $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'patient_id' => 'nullable|exists:patients,id',
            'owner_name' => 'nullable|string|max:255',
            'pet_name' => 'nullable|string|max:150',
            'pet_sex' => 'nullable|string|max:50',
            'pet_date_of_birth' => 'nullable|date',
            'pet_breed' => 'nullable|string|max:150',
            'pet_color' => 'nullable|string|max:150',
            'vaccine_name' => 'nullable|string|max:255',
            'administered_at' => 'nullable|date',
            'next_due_at' => 'nullable|date',
            'batch_lot_number' => 'nullable|string|max:150',
            'veterinarian' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'source' => 'nullable|string|max:50',
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function normalizeOwnerPatientLinks(array $validated): array
    {
        $patientId = $validated['patient_id'] ?? null;
        if (! $patientId) {
            return $validated;
        }

        $patient = Patient::query()->find($patientId);
        if (! $patient) {
            return $validated;
        }

        if (empty($validated['user_id'])) {
            $validated['user_id'] = $patient->user_id;

            return $validated;
        }

        if ($patient->user_id && (int) $patient->user_id !== (int) $validated['user_id']) {
            throw ValidationException::withMessages([
                'user_id' => 'The selected owner does not match this pet\'s registered owner.',
            ]);
        }

        return $validated;
    }
}
