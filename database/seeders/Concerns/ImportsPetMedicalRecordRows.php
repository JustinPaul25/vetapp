<?php

namespace Database\Seeders\Concerns;

use App\Models\Disease;
use App\Models\Medicine;
use App\Models\Symptom;
use Illuminate\Support\Facades\DB;

trait ImportsPetMedicalRecordRows
{
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
                    $symptom = Symptom::firstOrCreate(['name' => $cleanedSymptom]);
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
                $cleanedDiagnosis = trim($diagnosisName);
                if (! empty($cleanedDiagnosis)) {
                    $disease = Disease::firstOrCreate(['name' => $cleanedDiagnosis]);
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
                $cleanedPrescription = trim($prescriptionName);
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

        for ($i = 1; $i <= 9; $i++) {
            $index = $columnIndices['symptom_'.$i] ?? null;
            if ($index !== null && isset($row[$index])) {
                $symptom = trim((string) $row[$index]);
                if ($symptom !== '') {
                    $symptoms[] = $symptom;
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
                    $diagnoses[] = $diagnosis;
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
                        $diagnoses[] = $part;
                    }
                }
            }
        }

        $seen = [];
        $unique = [];
        foreach ($diagnoses as $d) {
            $key = strtolower($d);
            if (! isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $d;
            }
        }

        return $unique;
    }

    protected function extractPrescriptions(array $row, array $columnIndices): array
    {
        $prescriptions = [];

        for ($i = 1; $i <= 5; $i++) {
            $index = $columnIndices['prescription_'.$i] ?? null;
            if ($index !== null && isset($row[$index])) {
                $prescription = trim((string) $row[$index]);
                if ($prescription !== '') {
                    $prescriptions[] = $prescription;
                }
            }
        }

        return array_values(array_filter($prescriptions));
    }

    protected function cleanSymptomName(string $symptomName): string
    {
        $cleaned = trim($symptomName);
        $cleaned = preg_replace('/^\d+\s+/', '', $cleaned);
        $cleaned = trim($cleaned);

        if ($cleaned !== '') {
            $cleaned = ucfirst(strtolower($cleaned));
        }

        return $cleaned;
    }
}
