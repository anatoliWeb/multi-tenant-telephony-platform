<?php

namespace Tests\Unit\Extensions;

use App\Exceptions\Telephony\TelephonyValidationException;
use App\Services\Extensions\ExtensionNumberNormalizer;
use PHPUnit\Framework\TestCase;

class ExtensionNumberNormalizerTest extends TestCase
{
    public function test_it_normalizes_extension_numbers(): void
    {
        $service = new ExtensionNumberNormalizer();

        $this->assertSame('2042', $service->normalize('20-42'));
        $this->assertSame('0042', $service->normalize('00 42'));
    }

    public function test_it_rejects_invalid_extension_numbers(): void
    {
        $service = new ExtensionNumberNormalizer();

        $this->expectException(TelephonyValidationException::class);
        $service->normalize('1');
    }
}
