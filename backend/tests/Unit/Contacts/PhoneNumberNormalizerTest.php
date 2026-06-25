<?php

namespace Tests\Unit\Contacts;

use App\Exceptions\Telephony\TelephonyValidationException;
use App\Services\Contacts\PhoneNumberNormalizer;
use Tests\TestCase;

class PhoneNumberNormalizerTest extends TestCase
{
    public function test_it_normalizes_common_formats_and_extensions(): void
    {
        $normalizer = app(PhoneNumberNormalizer::class);

        $normalized = $normalizer->normalize('+1 (555) 010-1010 ext 44');
        $this->assertSame('+15550101010', $normalized['normalized_number']);
        $this->assertSame('44', $normalized['extension']);

        $local = $normalizer->normalize('050 123 45 67');
        $this->assertSame('0501234567', $local['normalized_number']);
        $this->assertNull($local['extension']);
    }

    public function test_it_rejects_clearly_invalid_values(): void
    {
        $normalizer = app(PhoneNumberNormalizer::class);

        $this->expectException(TelephonyValidationException::class);
        $normalizer->normalize('x');
    }
}
