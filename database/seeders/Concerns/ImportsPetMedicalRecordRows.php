<?php

namespace Database\Seeders\Concerns;

use App\Models\Disease;
use App\Models\Medicine;
use App\Models\Symptom;
use Illuminate\Support\Facades\DB;

trait ImportsPetMedicalRecordRows
{
    /**
     * @var array<string, \App\Models\Symptom>
     */
    protected array $symptomCache = [];

    /**
     * @var array<string, \App\Models\Disease>
     */
    protected array $diseaseCache = [];

    /**
     * Build a normalization key so near-identical labels map together.
     */
    protected function canonicalKey(string $value): string
    {
        $normalized = preg_replace('/\s+/u', ' ', trim($value)) ?? '';
        $normalized = str_replace(['–', '—', '_'], '-', $normalized);
        $normalized = preg_replace('/\s*-\s*/u', '-', $normalized) ?? $normalized;

        // Compare by alphanumeric-only key to catch punctuation/case variants.
        return preg_replace('/[^a-z0-9]+/u', '', mb_strtolower($normalized)) ?? '';
    }

    /**
     * Import one tabular row into symptoms, diseases, medicines, and pivot tables for ML training.
     *
     * @return array{
     *     symptoms_new: int,
     *     diseases_new: int,
     *     medicines_new: int,
     *     relationships_new: int,
     *     processed: bool
     * }
     */
    protected function processMedicalRecordRow(array $row, array $columnIndices): array
    {
        $result = [
            'symptoms_new' => 0,
            'diseases_new' => 0,
            'medicines_new' => 0,
            'relationships_new' => 0,
            'processed' => false,
        ];

        $symptoms = $this->extractSymptoms($row, $columnIndices);
        $symptomModels = [];
        foreach ($symptoms as $symptomName) {
            if (! empty($symptomName)) {
                $cleanedSymptom = $this->cleanSymptomName($symptomName);
                if (! empty($cleanedSymptom)) {
                    $symptom = $this->findOrCreateNormalizedSymptom($cleanedSymptom);
                    if ($symptom->wasRecentlyCreated) {
                        $result['symptoms_new']++;
                    }
                    $symptomModels[] = $symptom;
                }
            }
        }

        $diagnoses = $this->extractDiagnoses($row, $columnIndices);
        $diseaseModels = [];
        foreach ($diagnoses as $diagnosisName) {
            if (! empty($diagnosisName)) {
                $cleanedDiagnosis = $this->cleanDiagnosisName($diagnosisName);
                if (! empty($cleanedDiagnosis)) {
                    $disease = $this->findOrCreateNormalizedDisease($cleanedDiagnosis);
                    if ($disease->wasRecentlyCreated) {
                        $result['diseases_new']++;
                    }
                    $diseaseModels[] = $disease;
                }
            }
        }

        if ($symptomModels === [] || $diseaseModels === []) {
            return $result;
        }

        $prescriptions = $this->extractPrescriptions($row, $columnIndices);
        $medicineModels = [];
        foreach ($prescriptions as $prescriptionName) {
            if (! empty($prescriptionName)) {
                $cleanedPrescription = $this->cleanMedicineName($prescriptionName);
                if (! empty($cleanedPrescription)) {
                    $medicine = Medicine::firstOrCreate(['name' => $cleanedPrescription], [
                        'dosage' => 'As prescribed',
                        'route' => 'PO',
                        'stock' => 0,
                    ]);
                    if ($medicine->wasRecentlyCreated) {
                        $result['medicines_new']++;
                    }
                    $medicineModels[] = $medicine;
                }
            }
        }

        foreach ($diseaseModels as $disease) {
            foreach ($symptomModels as $symptom) {
                $exists = DB::table('disease_symptoms')
                    ->where('disease_id', $disease->id)
                    ->where('symptom_id', $symptom->id)
                    ->exists();

                if (! $exists) {
                    DB::table('disease_symptoms')->insert([
                        'disease_id' => $disease->id,
                        'symptom_id' => $symptom->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $result['relationships_new']++;
                }
            }
        }

        foreach ($diseaseModels as $disease) {
            foreach ($medicineModels as $medicine) {
                $exists = DB::table('disease_medicines')
                    ->where('disease_id', $disease->id)
                    ->where('medicine_id', $medicine->id)
                    ->exists();

                if (! $exists) {
                    DB::table('disease_medicines')->insert([
                        'disease_id' => $disease->id,
                        'medicine_id' => $medicine->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $result['relationships_new']++;
                }
            }
        }

        $result['processed'] = true;

        return $result;
    }

    /**
     * Find column indices for symptom, diagnosis, prescription, and optional tentative diagnosis columns.
     */
    protected function findColumnIndices(array $headersLower): array
    {
        $indices = [];

        for ($i = 1; $i <= 9; $i++) {
            $symptomKey = 'symptom_'.$i;
            $index = array_search($symptomKey, $headersLower, true);
            if ($index !== false) {
                $indices['symptom_'.$i] = $index;
            }
        }

        for ($i = 1; $i <= 4; $i++) {
            $diagnosisKey = 'diagnosis_'.$i;
            $index = array_search($diagnosisKey, $headersLower, true);
            if ($index !== false) {
                $indices['diagnosis_'.$i] = $index;
            }
        }

        for ($i = 1; $i <= 5; $i++) {
            $prescriptionKey = 'prescription_'.$i;
            $index = array_search($prescriptionKey, $headersLower, true);
            if ($index !== false) {
                $indices['prescription_'.$i] = $index;
            }
        }

        $tIdx = array_search('tentative diagnosis', $headersLower, true);
        if ($tIdx === false) {
            $tIdx = array_search('tentative_diagnosis', $headersLower, true);
        }
        if ($tIdx !== false) {
            $indices['tentative_diagnosis'] = $tIdx;
        }

        return $indices;
    }

    protected function extractSymptoms(array $row, array $columnIndices): array
    {
        $symptoms = [];
        $seen = [];

        for ($i = 1; $i <= 9; $i++) {
            $index = $columnIndices['symptom_'.$i] ?? null;
            if ($index !== null && isset($row[$index])) {
                $symptom = trim((string) $row[$index]);
                if ($symptom !== '') {
                    $cleaned = $this->cleanSymptomName($symptom);
                    $key = $this->canonicalKey($cleaned);
                    if ($cleaned !== '' && $key !== '' && ! isset($seen[$key])) {
                        $seen[$key] = true;
                        $symptoms[] = $cleaned;
                    }
                }
            }
        }

        return array_values(array_filter($symptoms));
    }

    protected function extractDiagnoses(array $row, array $columnIndices): array
    {
        $diagnoses = [];

        for ($i = 1; $i <= 4; $i++) {
            $index = $columnIndices['diagnosis_'.$i] ?? null;
            if ($index !== null && isset($row[$index])) {
                $diagnosis = trim((string) $row[$index]);
                if ($diagnosis !== '') {
                    $diagnoses[] = $this->cleanDiagnosisName($diagnosis);
                }
            }
        }

        $tIdx = $columnIndices['tentative_diagnosis'] ?? null;
        if ($tIdx !== null && isset($row[$tIdx])) {
            $tentative = trim((string) $row[$tIdx]);
            if ($tentative !== '') {
                foreach (preg_split('/[,;]/', $tentative) as $part) {
                    $part = trim($part);
                    if ($part !== '') {
                        $diagnoses[] = $this->cleanDiagnosisName($part);
                    }
                }
            }
        }

        $seen = [];
        $unique = [];
        foreach ($diagnoses as $d) {
            $key = $this->canonicalKey($d);
            if ($key !== '' && ! isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $d;
            }
        }

        return $unique;
    }

    protected function extractPrescriptions(array $row, array $columnIndices): array
    {
        $prescriptions = [];
        $seen = [];

        for ($i = 1; $i <= 5; $i++) {
            $index = $columnIndices['prescription_'.$i] ?? null;
            if ($index !== null && isset($row[$index])) {
                $prescription = trim((string) $row[$index]);
                if ($prescription !== '') {
                    $cleaned = $this->cleanMedicineName($prescription);
                    $key = $this->canonicalKey($cleaned);
                    if ($cleaned !== '' && $key !== '' && ! isset($seen[$key])) {
                        $seen[$key] = true;
                        $prescriptions[] = $cleaned;
                    }
                }
            }
        }

        return array_values(array_filter($prescriptions));
    }

    protected function cleanSymptomName(string $symptomName): string
    {
        $cleaned = trim($symptomName);
        $cleaned = preg_replace('/^\d+\s+/', '', $cleaned);
        $cleaned = preg_replace('/\s+/u', ' ', $cleaned) ?? $cleaned;
        $cleaned = str_replace(['–', '—', '_'], '-', $cleaned);
        $cleaned = preg_replace('/\s*-\s*/u', ' - ', $cleaned) ?? $cleaned;

        return trim($cleaned);
    }

    protected function cleanDiagnosisName(string $diagnosisName): string
    {
        $cleaned = preg_replace('/\s+/u', ' ', trim($diagnosisName)) ?? '';
        $cleaned = str_replace(['–', '—', '_'], '-', $cleaned);
        $cleaned = preg_replace('/\s*-\s*/u', ' - ', $cleaned) ?? $cleaned;

        return trim($cleaned);
    }

    protected function cleanMedicineName(string $medicineName): string
    {
        return preg_replace('/\s+/u', ' ', trim($medicineName)) ?? '';
    }

    protected function findOrCreateNormalizedSymptom(string $name): Symptom
    {
        if (empty($this->symptomCache)) {
            foreach (Symptom::all() as $existingSymptom) {
                $this->symptomCache[$this->canonicalKey((string) $existingSymptom->name)] = $existingSymptom;
            }
        }

        $key = $this->canonicalKey($name);
        if ($key !== '' && isset($this->symptomCache[$key])) {
            return $this->symptomCache[$key];
        }

        $symptom = Symptom::create(['name' => $name]);
        if ($key !== '') {
            $this->symptomCache[$key] = $symptom;
        }

        return $symptom;
    }

    protected function findOrCreateNormalizedDisease(string $name): Disease
    {
        if (empty($this->diseaseCache)) {
            foreach (Disease::all() as $existingDisease) {
                $this->diseaseCache[$this->canonicalKey((string) $existingDisease->name)] = $existingDisease;
            }
        }

        $key = $this->canonicalKey($name);
        if ($key !== '' && isset($this->diseaseCache[$key])) {
            return $this->diseaseCache[$key];
        }

        $disease = Disease::create(['name' => $name]);
        if ($key !== '') {
            $this->diseaseCache[$key] = $disease;
        }

        return $disease;
    }
}
