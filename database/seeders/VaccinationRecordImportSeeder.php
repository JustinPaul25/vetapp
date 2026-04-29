<?php

namespace Database\Seeders;

use App\Models\VaccinationRecord;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class VaccinationRecordImportSeeder extends Seeder
{
    private const EXCEL_PATH = 'dataset/vaccinationRecord.xlsx';

    public function run(): void
    {
        $path = public_path(self::EXCEL_PATH);

        if (! file_exists($path)) {
            $this->command->error("Excel file not found: {$path}");

            return;
        }

        $spreadsheet = IOFactory::load($path);
        $rows = $spreadsheet->getActiveSheet()->toArray();

        if (count($rows) < 2) {
            $this->command->warn('No data rows after header.');

            return;
        }

        $skippedDuplicates = 0;
        $registryRowsCreated = 0;
        $skippedInvalid = 0;
        $errors = 0;

        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];

            if ($this->rowIsEmpty($row)) {
                continue;
            }

            try {
                $vaxDate = $this->parseSpreadsheetDate($row[10] ?? null);

                if ($vaxDate === null) {
                    $skippedInvalid++;

                    continue;
                }

                $ownerNameCell = trim((string) ($row[0] ?? ''));
                $petNameRaw = trim((string) ($row[5] ?? ''));
                $petName = $petNameRaw !== '' ? $petNameRaw : null;
                $color = trim((string) ($row[9] ?? ''));
                $breedCell = trim((string) ($row[8] ?? ''));
                $breedVal = $breedCell !== '' ? $breedCell : null;
                $petDob = $this->parseDogBirthDate($row[7] ?? null);
                $petSexVal = $this->mapGender($row[6] ?? null);
                $notes = 'Imported from vaccinationRecord.xlsx.';
                if ($color !== '') {
                    $notes .= ' Coat/color (sheet): '.$color.'.';
                }

                $registryPayload = [
                    'user_id' => null,
                    'patient_id' => null,
                    'owner_name' => $ownerNameCell !== '' ? $ownerNameCell : null,
                    'pet_name' => $petName,
                    'pet_sex' => $petSexVal,
                    'pet_date_of_birth' => $petDob?->format('Y-m-d'),
                    'pet_breed' => $breedVal,
                    'pet_color' => $color !== '' ? $color : null,
                    'vaccine_name' => 'Anti-Rabies (historical import)',
                    'administered_at' => $vaxDate->format('Y-m-d'),
                    'next_due_at' => $vaxDate->copy()->addYear()->format('Y-m-d'),
                    'notes' => $notes,
                    'source' => 'vax-xlsx-import',
                ];

                if ($this->registryImportDuplicateExists($registryPayload, $vaxDate)) {
                    $skippedDuplicates++;

                    continue;
                }

                VaccinationRecord::create($registryPayload);
                $registryRowsCreated++;
            } catch (\Throwable $e) {
                $errors++;
                $this->command->warn('Row '.($i + 1).': '.$e->getMessage());
            }
        }

        $this->command->info('Vaccination import finished (registry snapshots only; no users/pets created).');
        $this->command->table(
            ['Metric', 'Count'],
            [
                ['Vaccination registry rows created', $registryRowsCreated],
                ['Skipped (duplicate snapshot row)', $skippedDuplicates],
                ['Skipped (missing vaccination date)', $skippedInvalid],
                ['Row errors', $errors],
            ]
        );
    }

    private function rowIsEmpty(array $row): bool
    {
        foreach ($row as $val) {
            if ($val !== null && trim((string) $val) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function registryImportDuplicateExists(array $payload, Carbon $vaxDate): bool
    {
        $q = VaccinationRecord::query()
            ->whereNull('patient_id')
            ->whereNull('user_id')
            ->whereDate('administered_at', $vaxDate->format('Y-m-d'))
            ->where('source', 'vax-xlsx-import');

        foreach (['owner_name', 'pet_name', 'pet_breed', 'pet_color'] as $col) {
            $val = $payload[$col] ?? null;
            if ($val === null || $val === '') {
                $q->where(function ($sub) use ($col) {
                    $sub->whereNull($col)->orWhere($col, '');
                });
            } else {
                $q->where($col, $val);
            }
        }

        return $q->exists();
    }

    private function mapGender(mixed $cell): ?string
    {
        $g = strtoupper(trim((string) $cell));

        return match ($g) {
            'M', 'MALE' => 'Male',
            'F', 'FEMALE' => 'Female',
            default => null,
        };
    }

    private function parseDogBirthDate(mixed $cell): ?Carbon
    {
        if ($cell === null || $cell === '') {
            return null;
        }

        $s = trim((string) $cell);
        if (preg_match('/^\d+\s*months?$/i', $s)) {
            return null;
        }

        return $this->parseSpreadsheetDate($cell);
    }

    private function parseSpreadsheetDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance(\DateTimeImmutable::createFromInterface($value));
        }

        if (is_numeric($value)) {
            try {
                $dt = ExcelDate::excelToDateTimeObject((float) $value);

                return Carbon::instance($dt);
            } catch (\Throwable) {
                // fall through
            }
        }

        $s = trim((string) $value);
        if ($s === '') {
            return null;
        }

        foreach (['n/j/Y', 'm/d/Y', 'Y-m-d'] as $fmt) {
            try {
                return Carbon::createFromFormat($fmt, $s)->startOfDay();
            } catch (\Throwable) {
                continue;
            }
        }

        try {
            return Carbon::parse($s)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}


