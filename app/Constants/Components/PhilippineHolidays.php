<?php

namespace App\Constants\Components;

use Carbon\Carbon;

class PhilippineHolidays
{
    /**
     * Get all Philippine holidays for a given year.
     * Returns an array of date strings in 'Y-m-d' format.
     * 
     * @param int $year The year to get holidays for
     * @return array Array of holiday date strings
     */
    public static function getHolidays(int $year): array
    {
        $holidays = [];

        // Fixed holidays (same date every year)
        $fixedHolidays = [
            // Regular holidays
            "{$year}-01-01", // New Year's Day
            "{$year}-04-09", // Day of Valor (Araw ng Kagitingan)
            "{$year}-05-01", // Labor Day
            "{$year}-06-12", // Independence Day
            "{$year}-08-30", // National Heroes' Day (last Monday of August, but officially observed on Aug 30)
            "{$year}-11-30", // Bonifacio Day
            "{$year}-12-25", // Christmas Day
            "{$year}-12-30", // Rizal Day
            
            // Special non-working days
            "{$year}-01-09", // Seminar day (as mentioned by user)
            "{$year}-02-25", // People Power Revolution Anniversary
            "{$year}-04-10", // Eid'l Fitr (approximate, actual date varies)
            "{$year}-06-17", // Eid'l Adha (approximate, actual date varies)
            "{$year}-08-21", // Ninoy Aquino Day
            "{$year}-11-01", // All Saints' Day
            "{$year}-12-08", // Feast of the Immaculate Conception
            "{$year}-12-24", // Christmas Eve
            "{$year}-12-31", // New Year's Eve
        ];

        // Add fixed holidays
        foreach ($fixedHolidays as $date) {
            $holidays[] = $date;
        }

        // Calculate movable holidays (these are approximate and may need adjustment)
        // Note: For exact dates, you may need to check the official holiday calendar
        
        // Maundy Thursday and Good Friday (Easter-based, approximately March/April)
        $easter = self::calculateEaster($year);
        $goodFriday = Carbon::instance($easter)->subDays(2)->format('Y-m-d');
        $maundyThursday = Carbon::instance($easter)->subDays(3)->format('Y-m-d');
        $holidays[] = $maundyThursday;
        $holidays[] = $goodFriday;

        // Black Saturday (day before Easter)
        $blackSaturday = Carbon::instance($easter)->subDay()->format('Y-m-d');
        $holidays[] = $blackSaturday;

        // Sort and remove duplicates
        $holidays = array_unique($holidays);
        sort($holidays);

        return $holidays;
    }

    /**
     * Get all Philippine holidays for the current year and next year.
     * Useful for calendar components that may display dates from both years.
     * 
     * @return array Array of holiday date strings
     */
    public static function getCurrentAndNextYearHolidays(): array
    {
        $currentYear = (int) date('Y');
        $nextYear = $currentYear + 1;
        
        return array_merge(
            self::getHolidays($currentYear),
            self::getHolidays($nextYear)
        );
    }

    /**
     * Check if a given date is a Philippine holiday.
     * 
     * @param string|Carbon $date Date to check (Y-m-d format or Carbon instance)
     * @return bool True if the date is a holiday
     */
    public static function isHoliday($date): bool
    {
        if ($date instanceof Carbon) {
            $year = $date->year;
            $dateString = $date->format('Y-m-d');
        } else {
            $carbon = Carbon::parse($date);
            $year = $carbon->year;
            $dateString = $carbon->format('Y-m-d');
        }

        $holidays = self::getHolidays($year);
        return in_array($dateString, $holidays);
    }

    /**
     * Calculate Easter Sunday date using the algorithm by J.M. Oudin (1940).
     * This is an approximation and may differ from actual dates for some years.
     * 
     * @param int $year The year
     * @return \DateTime DateTime instance for Easter Sunday
     */
    private static function calculateEaster(int $year): \DateTime
    {
        $a = $year % 19;
        $b = (int) ($year / 100);
        $c = $year % 100;
        $d = (int) ($b / 4);
        $e = $b % 4;
        $f = (int) (($b + 8) / 25);
        $g = (int) (($b - $f + 1) / 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = (int) ($c / 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = (int) (($a + 11 * $h + 22 * $l) / 451);
        $month = (int) (($h + $l - 7 * $m + 114) / 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return new \DateTime("{$year}-{$month}-{$day}");
    }

    /**
     * Get holidays for multiple years (useful for calendar views).
     * 
     * @param int $startYear Starting year
     * @param int $endYear Ending year (inclusive)
     * @return array Array of holiday date strings
     */
    public static function getHolidaysForRange(int $startYear, int $endYear): array
    {
        $holidays = [];
        
        for ($year = $startYear; $year <= $endYear; $year++) {
            $holidays = array_merge($holidays, self::getHolidays($year));
        }
        
        $holidays = array_unique($holidays);
        sort($holidays);
        
        return $holidays;
    }
}

