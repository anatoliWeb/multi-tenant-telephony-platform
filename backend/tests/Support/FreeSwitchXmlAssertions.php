<?php

namespace Tests\Support;

use PHPUnit\Framework\Assert;
use SimpleXMLElement;

final class FreeSwitchXmlAssertions
{
    public static function parse(string $xml, string $message = 'FreeSWITCH XML is not parseable.'): SimpleXMLElement
    {
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $parsed = simplexml_load_string($xml);
        } finally {
            libxml_use_internal_errors($previous);
        }

        Assert::assertNotFalse($parsed, $message);

        return $parsed;
    }

    /**
     * @return array<int, SimpleXMLElement>
     */
    public static function assertHasXPath(SimpleXMLElement $xml, string $xpath, string $message = ''): array
    {
        $nodes = $xml->xpath($xpath);

        Assert::assertNotFalse($nodes, $message !== '' ? $message : sprintf('Expected XML XPath to exist: %s', $xpath));
        Assert::assertNotEmpty($nodes, $message !== '' ? $message : sprintf('Expected XML XPath to match at least one node: %s', $xpath));

        return $nodes;
    }

    public static function assertXPathAttribute(SimpleXMLElement $xml, string $xpath, string $attribute, string $expected, string $message = ''): void
    {
        $nodes = self::assertHasXPath($xml, $xpath, $message);
        $attributes = $nodes[0]->attributes();
        $actual = $attributes->{$attribute} ?? null;

        Assert::assertTrue(isset($actual), $message !== '' ? $message : sprintf('Expected XML attribute "%s" at XPath %s', $attribute, $xpath));
        Assert::assertSame($expected, (string) $actual, $message !== '' ? $message : sprintf('Expected XML attribute "%s" at XPath %s to equal "%s"', $attribute, $xpath, $expected));
    }

    public static function assertDoesNotContain(string $xml, string $needle, string $message = ''): void
    {
        Assert::assertStringNotContainsString($needle, $xml, $message !== '' ? $message : sprintf('Unexpected XML content found: %s', $needle));
    }
}
