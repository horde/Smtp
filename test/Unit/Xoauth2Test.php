<?php

declare(strict_types=1);

namespace Horde\Smtp\Test\Unit;

use Horde_Smtp_Password_Xoauth2;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Horde_Smtp_Password_Xoauth2::class)]
class Xoauth2Test extends TestCase
{
    public function testTokenGeneration(): void
    {
        $xoauth2 = new Horde_Smtp_Password_Xoauth2(
            'someuser@example.com',
            'vF9dft4qmTc2Nvb3RlckBhdHRhdmlzdGEuY29tCg==',
        );

        $this->assertEquals(
            'dXNlcj1zb21ldXNlckBleGFtcGxlLmNvbQFhdXRoPUJlYXJlciB2RjlkZnQ0cW1UYzJOdmIzUmxja0JoZEhSaGRtbHpkR0V1WTI5dENnPT0BAQ==',
            $xoauth2->getPassword(),
        );
    }

    public function testImplementsPasswordInterface(): void
    {
        $xoauth2 = new Horde_Smtp_Password_Xoauth2('user@example.com', 'token');

        $this->assertInstanceOf(\Horde_Smtp_Password::class, $xoauth2);
    }

    public function testUsernameIsAccessible(): void
    {
        $xoauth2 = new Horde_Smtp_Password_Xoauth2('user@example.com', 'token');

        $this->assertSame('user@example.com', $xoauth2->username);
    }

    public function testAccessTokenIsAccessible(): void
    {
        $xoauth2 = new Horde_Smtp_Password_Xoauth2('user@example.com', 'mytoken');

        $this->assertSame('mytoken', $xoauth2->access_token);
    }
}
