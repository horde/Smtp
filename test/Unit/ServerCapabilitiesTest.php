<?php

declare(strict_types=1);

namespace Horde\Smtp\Test\Unit;

use Horde\Smtp\ServerCapabilities;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ServerCapabilities::class)]
class ServerCapabilitiesTest extends TestCase
{
    private function typical(): ServerCapabilities
    {
        return new ServerCapabilities([
            '8BITMIME' => true,
            'SIZE' => '52428800',
            'PIPELINING' => true,
            'CHUNKING' => true,
            'STARTTLS' => true,
            'ENHANCEDSTATUSCODES' => true,
            'AUTH' => 'PLAIN LOGIN CRAM-MD5 XOAUTH2',
            'SMTPUTF8' => true,
        ]);
    }

    public function testSupportsKnownExtension(): void
    {
        $this->assertTrue($this->typical()->supports('8BITMIME'));
    }

    public function testSupportsCaseInsensitive(): void
    {
        $this->assertTrue($this->typical()->supports('pipelining'));
    }

    public function testDoesNotSupportUnknown(): void
    {
        $this->assertFalse($this->typical()->supports('BINARYMIME'));
    }

    public function testExtensionValueReturnsString(): void
    {
        $this->assertSame('52428800', $this->typical()->extensionValue('SIZE'));
    }

    public function testExtensionValueReturnsTrueForNoParam(): void
    {
        $this->assertTrue($this->typical()->extensionValue('PIPELINING'));
    }

    public function testExtensionValueReturnsFalseForMissing(): void
    {
        $this->assertFalse($this->typical()->extensionValue('BINARYMIME'));
    }

    public function testSupports8BitMime(): void
    {
        $this->assertTrue($this->typical()->supports8BitMime());
    }

    public function testSupportsBinaryMimeFalseWhenMissing(): void
    {
        $this->assertFalse($this->typical()->supportsBinaryMime());
    }

    public function testSupportsInternationalized(): void
    {
        $this->assertTrue($this->typical()->supportsInternationalized());
    }

    public function testMaxSizeReturnsInt(): void
    {
        $this->assertSame(52428800, $this->typical()->maxSize());
    }

    public function testMaxSizeReturnsNullWhenMissing(): void
    {
        $caps = new ServerCapabilities([]);

        $this->assertNull($caps->maxSize());
    }

    public function testMaxSizeReturnsNullWhenTrueOnly(): void
    {
        $caps = new ServerCapabilities(['SIZE' => true]);

        $this->assertNull($caps->maxSize());
    }

    public function testSupportsPipelining(): void
    {
        $this->assertTrue($this->typical()->supportsPipelining());
    }

    public function testSupportsChunking(): void
    {
        $this->assertTrue($this->typical()->supportsChunking());
    }

    public function testSupportsStartTls(): void
    {
        $this->assertTrue($this->typical()->supportsStartTls());
    }

    public function testSupportsEnhancedStatusCodes(): void
    {
        $this->assertTrue($this->typical()->supportsEnhancedStatusCodes());
    }

    public function testAuthMethods(): void
    {
        $methods = $this->typical()->authMethods();

        $this->assertSame(['PLAIN', 'LOGIN', 'CRAM-MD5', 'XOAUTH2'], $methods);
    }

    public function testAuthMethodsEmptyWhenMissing(): void
    {
        $caps = new ServerCapabilities([]);

        $this->assertSame([], $caps->authMethods());
    }

    public function testEmptyCapabilities(): void
    {
        $caps = new ServerCapabilities([]);

        $this->assertFalse($caps->supports8BitMime());
        $this->assertFalse($caps->supportsPipelining());
        $this->assertNull($caps->maxSize());
        $this->assertSame([], $caps->authMethods());
    }
}
