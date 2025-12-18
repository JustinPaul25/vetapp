<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PhilippineMobileNumber implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($value)) {
            return; // Allow empty values if field is nullable
        }

        // Remove spaces, dashes, and parentheses
        $cleaned = preg_replace('/[\s\-\(\)]/', '', $value);

        // Philippine mobile number patterns:
        // - 09XX XXX XXXX (11 digits starting with 09)
        // - +639XX XXX XXXX (with country code)
        // - 639XX XXX XXXX (without + but with country code)
        // - 9XX XXX XXXX (10 digits starting with 9, but less common)

        // Check if it starts with +63 or 63
        if (preg_match('/^(\+?63)?9\d{9}$/', $cleaned)) {
            return;
        }

        // Check if it's 11 digits starting with 09
        if (preg_match('/^09\d{9}$/', $cleaned)) {
            return;
        }

        // Check if it's 10 digits starting with 9 (less common format)
        if (preg_match('/^9\d{9}$/', $cleaned)) {
            return;
        }

        $fail('The :attribute must be a valid Philippine mobile number (e.g., 09123456789, +639123456789).');
    }
}







