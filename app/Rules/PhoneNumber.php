<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PhoneNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('Enter a phone number as text.');

            return;
        }
        if (! preg_match('/^\+[1-9][0-9]{7,14}$/', (string) $value)) {
            $fail('Use an international phone number, for example +923001234567.');

            return;
        }if (str_starts_with((string) $value, '+92') && ! preg_match('/^\+92(?:3[0-9]{9}|[124-9][0-9]{8,9})$/', (string) $value)) {
            $fail('Enter a valid Pakistan mobile or landline number.');
        }
    }
}
