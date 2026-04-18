<?php

declare(strict_types=1);

namespace Horde\Smtp\Test\Unit;

use Horde\Smtp\Protocol;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

#[CoversClass(Protocol::class)]
class ProtocolTest extends TestCase
{
    public function testSmtpGreeting(): void
    {
        $this->assertSame('EHLO', Protocol::Smtp->greeting());
    }

    public function testLmtpGreeting(): void
    {
        $this->assertSame('LHLO', Protocol::Lmtp->greeting());
    }

    public function testSmtpRequiredExtensionsEmpty(): void
    {
        $this->assertSame([], Protocol::Smtp->requiredExtensions());
    }

    public function testLmtpRequiredExtensions(): void
    {
        $required = Protocol::Lmtp->requiredExtensions();

        $this->assertContains('ENHANCEDSTATUSCODES', $required);
        $this->assertContains('PIPELINING', $required);
    }

    public function testSmtpAllowsPort25(): void
    {
        Protocol::Smtp->validatePort(25);
        $this->assertTrue(true);
    }

    public function testLmtpRejectsPort25(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Protocol::Lmtp->validatePort(25);
    }

    public function testLmtpAllowsOtherPorts(): void
    {
        Protocol::Lmtp->validatePort(24);
        $this->assertTrue(true);
    }
}
