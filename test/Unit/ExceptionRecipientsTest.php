<?php

declare(strict_types=1);

namespace Horde\Smtp\Test\Unit;

use Horde_Smtp_Exception;
use Horde_Smtp_Exception_Recipients;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Horde_Smtp_Exception_Recipients::class)]
class ExceptionRecipientsTest extends TestCase
{
    public function testExtendsSmtpException(): void
    {
        $e = new Horde_Smtp_Exception_Recipients('error');

        $this->assertInstanceOf(Horde_Smtp_Exception::class, $e);
    }

    public function testDefaultRecipientsIsEmptyArray(): void
    {
        $e = new Horde_Smtp_Exception_Recipients('error');

        $this->assertSame([], $e->recipients);
    }

    public function testRecipientsCanBeSet(): void
    {
        $e = new Horde_Smtp_Exception_Recipients('error');
        $e->recipients = ['bad@example.com', 'worse@example.com'];

        $this->assertSame(
            ['bad@example.com', 'worse@example.com'],
            $e->recipients,
        );
    }
}
