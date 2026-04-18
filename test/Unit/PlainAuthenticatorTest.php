<?php

declare(strict_types=1);

namespace Horde\Smtp\Test\Unit;

use Horde\Smtp\Authenticator;
use Horde\Smtp\PasswordCredentials;
use Horde\Smtp\PlainAuthenticator;
use Horde\Smtp\SmtpResponse;
use Horde\Smtp\Test\FakeSmtpConnection;
use Horde\Smtp\Test\SpyDebug;
use Horde\Smtp\Xoauth2Credentials;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PlainAuthenticator::class)]
class PlainAuthenticatorTest extends TestCase
{
    public function testMechanism(): void
    {
        $auth = new PlainAuthenticator();
        $this->assertSame('PLAIN', $auth->mechanism());
    }

    public function testSupportsPasswordCredentials(): void
    {
        $auth = new PlainAuthenticator();
        $this->assertTrue($auth->supports(new PasswordCredentials('u', 'p')));
    }

    public function testDoesNotSupportXoauth2Credentials(): void
    {
        $auth = new PlainAuthenticator();
        $this->assertFalse($auth->supports(new Xoauth2Credentials('u', 'tok')));
    }

    public function testImplementsInterface(): void
    {
        $this->assertInstanceOf(Authenticator::class, new PlainAuthenticator());
    }

    public function testAuthenticate(): void
    {
        $conn = new FakeSmtpConnection();
        $conn->queueResponse(new SmtpResponse(235, ['Authentication successful']));
        $debug = new SpyDebug();

        $auth = new PlainAuthenticator();
        $auth->authenticate(
            $conn,
            new PasswordCredentials('testuser', 'testpass'),
            $debug,
        );

        $this->assertCount(1, $conn->written);
        $expected = 'AUTH PLAIN ' . base64_encode("testuser\0testuser\0testpass");
        $this->assertSame($expected, $conn->written[0]);
    }

    public function testDebugLogOmitsCredentials(): void
    {
        $conn = new FakeSmtpConnection();
        $conn->queueResponse(new SmtpResponse(235));
        $debug = new SpyDebug();

        $auth = new PlainAuthenticator();
        $auth->authenticate(
            $conn,
            new PasswordCredentials('alice', 'secret'),
            $debug,
        );

        $this->assertCount(1, $debug->messages);
        $this->assertStringContainsString('PLAIN', $debug->messages[0]);
        $this->assertStringContainsString('alice', $debug->messages[0]);
        $this->assertStringNotContainsString('secret', $debug->messages[0]);
    }

    public function testAuthFailureThrows(): void
    {
        $conn = new FakeSmtpConnection();
        $conn->queueResponse(new SmtpResponse(535, ['Authentication failed']));

        $auth = new PlainAuthenticator();

        $this->expectException(\Horde\Smtp\SmtpException::class);
        $auth->authenticate(
            $conn,
            new PasswordCredentials('u', 'p'),
            new SpyDebug(),
        );
    }
}
