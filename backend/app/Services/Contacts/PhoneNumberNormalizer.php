<?php

namespace App\Services\Contacts;

use App\Exceptions\Telephony\TelephonyValidationException;

class PhoneNumberNormalizer
{
    /**
     * @return array{normalized_number: string, extension: string|null}
     */
    public function normalize(string $rawNumber, ?string $extension = null): array
    {
        $trimmed = trim($rawNumber);
        if ($trimmed === '') {
            throw new TelephonyValidationException('Phone number is required.');
        }

        $detectedExtension = $extension;
        if ($detectedExtension === null && preg_match('/(?:ext\.?|x|#)\s*(\d{1,10})$/i', $trimmed, $matches) === 1) {
            $detectedExtension = $matches[1];
            $trimmed = trim((string) preg_replace('/(?:ext\.?|x|#)\s*\d{1,10}$/i', '', $trimmed));
        }

        $hasPlusPrefix = str_starts_with($trimmed, '+');
        $digits = preg_replace('/\D+/', '', $trimmed) ?? '';
        if ($digits === '' || strlen($digits) < 3) {
            throw new TelephonyValidationException('Phone number is invalid.');
        }

        $normalized = $hasPlusPrefix ? '+'.$digits : $digits;

        return [
            'normalized_number' => $normalized,
            'extension' => $detectedExtension !== null && $detectedExtension !== ''
                ? preg_replace('/\D+/', '', $detectedExtension) ?: null
                : null,
        ];
    }
}
