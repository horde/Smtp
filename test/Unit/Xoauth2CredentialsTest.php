<?php

declare(strict_types=1);

namespace Horde\Smtp\Test\Unit;

use Horde\Smtp\Credentials;
use Horde\Smtp\Xoauth2Credentials;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Xoauth2Credentials::class)]
class Xoauth2CredentialsTest extends TestCase
{
    public function testImplementsCredentials(): void
    {
        $cred = new Xoauth2Credentials('user@example.com', 'token');

        $this->assertInstanceOf(Credentials::class, $cred);
    }

    public function testUsernameAndToken(): void
    {
        $cred = new Xoauth2Credentials('alice@example.com', 'mytoken');

        $this->assertSame('alice@example.com', $cred->username());
        $this->assertSame('mytoken', $cred->accessToken());
    }

    public function testEncodedTokenMatchesSpec(): void
    {
        $cred = new Xoauth2Credentials(
            'someuser@example.com',
            'vF9dft4qmTc2Nvb3RlckBhdHRhdmlzdGEuY29tCg==',
        );

        $this->assertSame(
            'dXNlcj1zb21ldXNlckBleGFtcGxlLmNvbQFhdXRoPUJlYXJlciB2RjlkZnQ0cW1UYzJOdmIzUmxja0JoZEhSaGRtbHpkR0V1WTI5dENnPT0BAQ==',
            $cred->encodedToken(),
        );
    }

    public function testCallableToken(): void
    {
        $calls = 0;
        $cred = new Xoauth2Credentials('user@example.com', function () use (&$calls): string {
            $calls++;
            return 'token-' . $calls;
        });

        $this->assertSame('token-1', $cred->accessToken());
        $this->assertSame('token-2', $cred->accessToken());
    }
}
