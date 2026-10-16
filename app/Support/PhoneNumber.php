<?php

namespace App\Support;

class PhoneNumber
{
    /**
     * Turn any way of typing a Pakistani phone number into one standard form.
     *
     *   0300-1234567, 0300 1234567, +92 300 1234567,
     *   00923001234567, 3001234567   ->   03001234567
     *
     * If the text has letters or other strange characters, it is returned
     * as typed (trimmed) so the validation rule can reject it.
     */
    public static function normalize(string $value): string
    {
        $value = trim($value);

        if ($value === '' || preg_match('/[^\d\s\+\-\(\)\.]/', $value)) {
            return $value;
        }

        $digits = preg_replace('/\D+/', '', $value);

        if ($digits === '') {
            return $value;
        }

        // 0092300... -> 0300...
        if (str_starts_with($digits, '0092')) {
            $rest = substr($digits, 4);

            return str_starts_with($rest, '0') ? $rest : '0' . $rest;
        }

        // 92300... (from +92 300 ...) -> 0300...
        if (str_starts_with($digits, '92') && strlen($digits) >= 11) {
            $rest = substr($digits, 2);

            return str_starts_with($rest, '0') ? $rest : '0' . $rest;
        }

        // 3001234567 (mobile without the leading 0) -> 03001234567
        if (strlen($digits) === 10 && str_starts_with($digits, '3')) {
            return '0' . $digits;
        }

        return $digits;
    }
}
