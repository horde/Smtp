<?php

declare(strict_types=1);

namespace Horde\Smtp\Test\Unit;

use Horde\Smtp\Authenticator;
use Horde\Smtp\AuthenticationException;
use Horde\Smtp\DigestMd5Authenticator;
use Horde\Smtp\PasswordCredentials;
use Horde\Smtp\Xoauth2Credentials;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Horde_Imap_Client_Auth_DigestMD5;

#[CoversClass(DigestMd5Authenticator::class)]
class DigestMd5AuthenticatorTest extends TestCase
{
    public function testMechanism(): void
    {
        $this->assertSame('DIGEST-MD5', (new DigestMd5Authenticator('localhost'))->mechanism());
    }

    public function testImplementsInterface(): void
    {
        $this->assertInstanceOf(Authenticator::class, new DigestMd5Authenticator('localhost'));
    }

    public function testDoesNotSupportXoauth2(): void
    {
        $auth = new DigestMd5Authenticator('localhost');
        $this->assertFalse($auth->supports(new Xoauth2Credentials('u', 't')));
    }

    public function testSupportsPasswordCredentialsDependsOnImapClient(): void
    {
        $auth = new DigestMd5Authenticator('localhost');
        $creds = new PasswordCredentials('u', 'p');

        $expected = class_exists(Horde_Imap_Client_Auth_DigestMD5::class);
        $this->assertSame($expected, $auth->supports($creds));
    }

    public function testAuthenticateWithoutImapClientThrows(): void
    {
        if (class_exists(Horde_Imap_Client_Auth_DigestMD5::class)) {
            $this->markTestSkipped('Horde_Imap_Client is installed');
        }

        $auth = new DigestMd5Authenticator('mail.example.com');

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('horde/imap_client');

        $auth->authenticate(
            new \Horde\Smtp\Test\FakeSmtpConnection(),
            new PasswordCredentials('u', 'p'),
            new \Horde\Smtp\Test\SpyDebug(),
        );
    }
}
