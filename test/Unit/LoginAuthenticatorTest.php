<?php

declare(strict_types=1);

namespace Horde\Smtp\Test\Unit;

use Horde\Smtp\Authenticator;
use Horde\Smtp\LoginAuthenticator;
use Horde\Smtp\PasswordCredentials;
use Horde\Smtp\SmtpResponse;
use Horde\Smtp\Test\FakeSmtpConnection;
use Horde\Smtp\Test\SpyDebug;
use Horde\Smtp\Xoauth2Credentials;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LoginAuthenticator::class)]
class LoginAuthenticatorTest extends TestCase
{
    public function testMechanism(): void
    {
        $this->assertSame('LOGIN', (new LoginAuthenticator())->mechanism());
    }

    public function testSupportsPasswordCredentials(): void
    {
        $this->assertTrue((new LoginAuthenticator())->supports(new PasswordCredentials('u', 'p')));
    }

    public function testDoesNotSupportXoauth2(): void
    {
        $this->assertFalse((new LoginAuthenticator())->supports(new Xoauth2Credentials('u', 't')));
    }

    public function testImplementsInterface(): void
    {
        $this->assertInstanceOf(Authenticator::class, new LoginAuthenticator());
    }

    public function testAuthenticateProtocolFlow(): void
    {
        $conn = new FakeSmtpConnection();
        $conn->queueResponse(new SmtpResponse(334, [base64_encode('Username:')]));
        $conn->queueResponse(new SmtpResponse(334, [base64_encode('Password:')]));
        $conn->queueResponse(new SmtpResponse(235, ['OK']));
        $debug = new SpyDebug();

        $auth = new LoginAuthenticator();
        $auth->authenticate(
            $conn,
            new PasswordCredentials('bob', 'pass123'),
            $debug,
        );

        $this->assertSame('AUTH LOGIN', $conn->written[0]);
        $this->assertSame(base64_encode('bob'), $conn->written[1]);
        $this->assertSame(base64_encode('pass123'), $conn->written[2]);
    }

    public function testDebugLogOmitsPassword(): void
    {
        $conn = new FakeSmtpConnection();
        $conn->queueResponse(new SmtpResponse(334));
        $conn->queueResponse(new SmtpResponse(334));
        $conn->queueResponse(new SmtpResponse(235));
        $debug = new SpyDebug();

        (new LoginAuthenticator())->authenticate(
            $conn,
            new PasswordCredentials('bob', 'supersecret'),
            $debug,
        );

        $this->assertCount(1, $debug->messages);
        $this->assertStringContainsString('LOGIN', $debug->messages[0]);
        $this->assertStringContainsString('bob', $debug->messages[0]);
        $this->assertStringNotContainsString('supersecret', $debug->messages[0]);
    }

    public function testAuthFailureOnUsernameStepThrows(): void
    {
        $conn = new FakeSmtpConnection();
        $conn->queueResponse(new SmtpResponse(504, ['Unrecognized']));

        $this->expectException(\Horde\Smtp\SmtpException::class);
        (new LoginAuthenticator())->authenticate(
            $conn,
            new PasswordCredentials('u', 'p'),
            new SpyDebug(),
        );
    }
}
