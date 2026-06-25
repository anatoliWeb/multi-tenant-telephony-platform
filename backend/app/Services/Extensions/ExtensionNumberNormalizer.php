<?php

namespace App\Services\Extensions;

use App\Exceptions\Telephony\TelephonyValidationException;

class ExtensionNumberNormalizer
{
    public function normalize(string $number): string
    {
        $normalized = preg_replace('/\D+/', '', trim($number)) ?? '';

        if ($normalized === '' || strlen($normalized) < 2 || strlen($normalized) > 6) {
            throw new TelephonyValidationException('Extension numbers must contain 2 to 6 digits.');
        }

        if (str_starts_with($normalized, '0') && strlen($normalized) > 1) {
            return $normalized;
        }

        return ltrim($normalized, ' ') ?: $normalized;
    }
}
