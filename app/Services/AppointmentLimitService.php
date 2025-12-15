<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AppointmentType;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AppointmentLimitService
{
    /**
     * Check if the daily limit for an appointment type has been reached.
     *
     * @param string|int $appointmentTypeIdOrName The appointment type ID or name
     * @param string $date The appointment date (Y-m-d format)
     * @param int|null $excludeAppointmentId Optional appointment ID to exclude from count (for updates)
     * @return array ['available' => bool, 'current_count' => int, 'limit' => int, 'remaining' => int]
     */
    public function checkDailyLimit($appointmentTypeIdOrName, string $date, ?int $excludeAppointmentId = null): array
    {
        // Get appointment type
        $appointmentType = is_numeric($appointmentTypeIdOrName)
            ? AppointmentType::find($appointmentTypeIdOrName)
            : AppointmentType::where('name', $appointmentTypeIdOrName)->first();

        if (!$appointmentType) {
            return [
                'available' => false,
                'current_count' => 0,
                'limit' => 0,
                'remaining' => 0,
                'error' => 'Appointment type not found',
            ];
        }

        // Get the limit for this appointment type
        $limit = $this->getLimitForType($appointmentType->name);

        // Count appointments for this date and type
        // Include both booked and walk-in appointments
        // Exclude canceled appointments
        // Handle both single appointment_type_id and many-to-many relationship
        
        $query = DB::table('appointments')
            ->whereDate('appointments.appointment_date', $date)
            ->where(function ($q) {
                $q->whereNull('appointments.is_canceled')
                  ->orWhere('appointments.is_canceled', false);
            })
            ->where(function ($q) use ($appointmentType) {
                // Check single appointment_type_id (backward compatibility)
                $q->where('appointments.appointment_type_id', $appointmentType->id)
                  // Also check many-to-many relationship
                  ->orWhereExists(function ($subQuery) use ($appointmentType) {
                      $subQuery->select(DB::raw(1))
                          ->from('appointment_appointment_type')
                          ->whereColumn('appointment_appointment_type.appointment_id', 'appointments.id')
                          ->where('appointment_appointment_type.appointment_type_id', $appointmentType->id);
                  });
            });

        // Exclude specific appointment if provided (for updates)
        if ($excludeAppointmentId) {
            $query->where('appointments.id', '!=', $excludeAppointmentId);
        }

        // Count distinct appointments (an appointment can have multiple types, but we count it once per type)
        $currentCount = $query->distinct()->count('appointments.id');

        $remaining = max(0, $limit - $currentCount);

        return [
            'available' => $currentCount < $limit,
            'current_count' => $currentCount,
            'limit' => $limit,
            'remaining' => $remaining,
            'appointment_type' => $appointmentType->name,
        ];
    }

    /**
     * Check daily limits for multiple appointment types.
     *
     * @param array $appointmentTypeIds Array of appointment type IDs
     * @param string $date The appointment date (Y-m-d format)
     * @param int|null $excludeAppointmentId Optional appointment ID to exclude from count
     * @return array ['available' => bool, 'types' => array, 'errors' => array]
     */
    public function checkMultipleDailyLimits(array $appointmentTypeIds, string $date, ?int $excludeAppointmentId = null): array
    {
        $results = [];
        $allAvailable = true;
        $errors = [];

        foreach ($appointmentTypeIds as $typeId) {
            $result = $this->checkDailyLimit($typeId, $date, $excludeAppointmentId);
            $results[] = $result;

            if (!$result['available']) {
                $allAvailable = false;
                $errors[] = sprintf(
                    '%s appointment limit reached. Current: %d/%d',
                    $result['appointment_type'],
                    $result['current_count'],
                    $result['limit']
                );
            }
        }

        return [
            'available' => $allAvailable,
            'types' => $results,
            'errors' => $errors,
        ];
    }

    /**
     * Get the daily limit for a specific appointment type.
     *
     * @param string $typeName The appointment type name
     * @return int The daily limit
     */
    protected function getLimitForType(string $typeName): int
    {
        $limits = config('appointments.daily_limits', []);
        
        // Check for exact match first
        if (isset($limits[$typeName])) {
            return (int) $limits[$typeName];
        }

        // Check for case-insensitive match
        foreach ($limits as $key => $value) {
            if (strcasecmp($key, $typeName) === 0) {
                return (int) $value;
            }
        }

        // Return default limit
        return (int) ($limits['default'] ?? 40);
    }

    /**
     * Get current count for an appointment type on a specific date.
     *
     * @param string|int $appointmentTypeIdOrName The appointment type ID or name
     * @param string $date The appointment date (Y-m-d format)
     * @return int Current count
     */
    public function getCurrentCount($appointmentTypeIdOrName, string $date): int
    {
        $result = $this->checkDailyLimit($appointmentTypeIdOrName, $date);
        return $result['current_count'];
    }

    /**
     * Validate if appointment can be created/updated for given types and date.
     *
     * @param array $appointmentTypeIds Array of appointment type IDs
     * @param string $date The appointment date (Y-m-d format)
     * @param int|null $excludeAppointmentId Optional appointment ID to exclude (for updates)
     * @throws \Illuminate\Validation\ValidationException
     */
    public function validateDailyLimits(array $appointmentTypeIds, string $date, ?int $excludeAppointmentId = null): void
    {
        $result = $this->checkMultipleDailyLimits($appointmentTypeIds, $date, $excludeAppointmentId);

        if (!$result['available']) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'appointment_date' => implode(' ', $result['errors']),
            ]);
        }
    }
}
