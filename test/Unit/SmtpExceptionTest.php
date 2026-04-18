<?php

declare(strict_types=1);

namespace Horde\Smtp\Test\Unit;

use Horde\Smtp\AuthenticationException;
use Horde\Smtp\ConnectionException;
use Horde\Smtp\RecipientsException;
use Horde\Smtp\SmtpException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(SmtpException::class)]
#[CoversClass(RecipientsException::class)]
#[CoversClass(AuthenticationException::class)]
#[CoversClass(ConnectionException::class)]
class SmtpExceptionTest extends TestCase
{
    public function testBasicProperties(): void
    {
        $e = new SmtpException('test error', 5, null, 550, '5.1.1');

        $this->assertSame('test error', $e->getMessage());
        $this->assertSame('test error', $e->rawMessage);
        $this->assertSame(5, $e->getCode());
        $this->assertSame(550, $e->smtpCode);
        $this->assertSame('5.1.1', $e->enhancedCode);
    }

    public function testPermanentTrueFor5xxSmtpCode(): void
    {
        $e = new SmtpException('error', smtpCode: 550);

        $this->assertTrue($e->permanent);
    }

    public function testPermanentFalseFor4xxSmtpCode(): void
    {
        $e = new SmtpException('error', smtpCode: 450);

        $this->assertFalse($e->permanent);
    }

    public function testPermanentTrueFor5xxEnhancedCode(): void
    {
        $e = new SmtpException('error', enhancedCode: '5.1.1');

        $this->assertTrue($e->permanent);
    }

    public function testPermanentFalseFor4xxEnhancedCode(): void
    {
        $e = new SmtpException('error', enhancedCode: '4.1.1');

        $this->assertFalse($e->permanent);
    }

    public function testEnhancedCodeTakesPrecedenceOverSmtpCode(): void
    {
        $e = new SmtpException('error', smtpCode: 550, enhancedCode: '4.1.1');

        $this->assertFalse($e->permanent);
    }

    public function testDefaultPermanentIsFalse(): void
    {
        $e = new SmtpException('error');

        $this->assertFalse($e->permanent);
    }

    public function testDefaultSmtpCodeIsNull(): void
    {
        $e = new SmtpException('error');

        $this->assertNull($e->smtpCode);
        $this->assertNull($e->enhancedCode);
    }

    public function testRecipientsException(): void
    {
        $e = new RecipientsException(
            'delivery failed',
            smtpCode: 550,
            recipients: ['bad@example.com', 'worse@example.com'],
        );

        $this->assertInstanceOf(SmtpException::class, $e);
        $this->assertSame(['bad@example.com', 'worse@example.com'], $e->recipients);
    }

    public function testRecipientsDefaultEmpty(): void
    {
        $e = new RecipientsException('error');

        $this->assertSame([], $e->recipients);
    }

    public function testAuthenticationExceptionIsSmtpException(): void
    {
        $e = new AuthenticationException('auth failed', smtpCode: 535);

        $this->assertInstanceOf(SmtpException::class, $e);
        $this->assertSame(535, $e->smtpCode);
    }

    public function testConnectionExceptionIsSmtpException(): void
    {
        $e = new ConnectionException('connect failed');

        $this->assertInstanceOf(SmtpException::class, $e);
    }

    public function testPreviousException(): void
    {
        $prev = new RuntimeException('socket error');
        $e = new ConnectionException('connect failed', previous: $prev);

        $this->assertSame($prev, $e->getPrevious());
    }
}
