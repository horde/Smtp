<?php

declare(strict_types=1);

namespace Horde\Smtp\Test\Unit;

use Horde\Smtp\Authenticator;
use Horde\Smtp\AuthenticationException;
use Horde\Smtp\PasswordCredentials;
use Horde\Smtp\SmtpResponse;
use Horde\Smtp\Test\FakeSmtpConnection;
use Horde\Smtp\Test\SpyDebug;
use Horde\Smtp\Xoauth2Authenticator;
use Horde\Smtp\Xoauth2Credentials;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Xoauth2Authenticator::class)]
class Xoauth2AuthenticatorTest extends TestCase
{
    public function testMechanism(): void
    {
        $this->assertSame('XOAUTH2', (new Xoauth2Authenticator())->mechanism());
    }

    public function testSupportsXoauth2Credentials(): void
    {
        $this->assertTrue(
            (new Xoauth2Authenticator())->supports(new Xoauth2Credentials('u', 'tok')),
        );
    }

    public function testDoesNotSupportPasswordCredentials(): void
    {
        $this->assertFalse(
            (new Xoauth2Authenticator())->supports(new PasswordCredentials('u', 'p')),
        );
    }

    public function testImplementsInterface(): void
    {
        $this->assertInstanceOf(Authenticator::class, new Xoauth2Authenticator());
    }

    public function testAuthenticateSuccess(): void
    {
        $conn = new FakeSmtpConnection();
        $conn->queueResponse(new SmtpResponse(235));
        $debug = new SpyDebug();

        $creds = new Xoauth2Credentials('user@example.com', 'ya29.token');
        $auth = new Xoauth2Authenticator();
        $auth->authenticate($conn, $creds, $debug);

        $this->assertCount(1, $conn->written);
        $this->assertSame('AUTH XOAUTH2 ' . $creds->encodedToken(), $conn->written[0]);
    }

    public function testDebugLogOmitsToken(): void
    {
        $conn = new FakeSmtpConnection();
        $conn->queueResponse(new SmtpResponse(235));
        $debug = new SpyDebug();

        (new Xoauth2Authenticator())->authenticate(
            $conn,
            new Xoauth2Credentials('user@example.com', 'ya29.secrettoken'),
            $debug,
        );

        $this->assertCount(1, $debug->messages);
        $this->assertStringContainsString('XOAUTH2', $debug->messages[0]);
        $this->assertStringContainsString('user@example.com', $debug->messages[0]);
        $this->assertStringNotContainsString('ya29.secrettoken', $debug->messages[0]);
    }

    public function testAuthFailureSendsCancelAndThrows(): void
    {
        $conn = new FakeSmtpConnection();
        $conn->queueResponse(new SmtpResponse(334, ['eyJlcnJvciI6ImludmFsaWQifQ==']));

        $auth = new Xoauth2Authenticator();

        try {
            $auth->authenticate(
                $conn,
                new Xoauth2Credentials('user@example.com', 'bad-token'),
                new SpyDebug(),
            );
            $this->fail('Expected AuthenticationException');
        } catch (AuthenticationException $e) {
            $this->assertSame(334, $e->smtpCode);
            $this->assertCount(2, $conn->written);
            $this->assertSame('', $conn->written[1]);
        }
    }
}
