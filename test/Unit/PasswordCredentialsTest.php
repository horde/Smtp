<?php

declare(strict_types=1);

namespace Horde\Smtp\Test\Unit;

use Horde\Smtp\Credentials;
use Horde\Smtp\PasswordCredentials;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Stringable;

#[CoversClass(PasswordCredentials::class)]
class PasswordCredentialsTest extends TestCase
{
    public function testImplementsCredentials(): void
    {
        $cred = new PasswordCredentials('user', 'pass');

        $this->assertInstanceOf(Credentials::class, $cred);
    }

    public function testUsernameAndPassword(): void
    {
        $cred = new PasswordCredentials('alice@example.com', 's3cret');

        $this->assertSame('alice@example.com', $cred->username());
        $this->assertSame('s3cret', $cred->password());
    }

    public function testStringablePassword(): void
    {
        $stringable = new class implements Stringable {
            public function __toString(): string
            {
                return 'dynamic-pass';
            }
        };

        $cred = new PasswordCredentials('user', $stringable);

        $this->assertSame('dynamic-pass', $cred->password());
    }

    public function testCallablePassword(): void
    {
        $calls = 0;
        $cred = new PasswordCredentials('user', function () use (&$calls): string {
            $calls++;
            return 'generated-' . $calls;
        });

        $this->assertSame('generated-1', $cred->password());
        $this->assertSame('generated-2', $cred->password());
    }
}
