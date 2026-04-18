<?php

declare(strict_types=1);

namespace Horde\Smtp\Test\Unit;

use Horde\Smtp\SendResult;
use Horde\Smtp\SmtpException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SendResult::class)]
class SendResultTest extends TestCase
{
    public function testAllSuccessful(): void
    {
        $result = new SendResult([
            'a@example.com' => true,
            'b@example.com' => true,
        ]);

        $this->assertTrue($result->allSuccessful());
    }

    public function testNotAllSuccessful(): void
    {
        $result = new SendResult([
            'a@example.com' => true,
            'b@example.com' => new SmtpException('rejected', smtpCode: 550),
        ]);

        $this->assertFalse($result->allSuccessful());
    }

    public function testSuccessfulReturnsAddresses(): void
    {
        $result = new SendResult([
            'a@example.com' => true,
            'b@example.com' => new SmtpException('rejected'),
            'c@example.com' => true,
        ]);

        $this->assertSame(['a@example.com', 'c@example.com'], $result->successful());
    }

    public function testFailedReturnsExceptions(): void
    {
        $ex = new SmtpException('rejected', smtpCode: 550);
        $result = new SendResult([
            'a@example.com' => true,
            'b@example.com' => $ex,
        ]);

        $failed = $result->failed();
        $this->assertCount(1, $failed);
        $this->assertSame($ex, $failed['b@example.com']);
    }

    public function testEmptyRecipients(): void
    {
        $result = new SendResult([]);
        $this->assertTrue($result->allSuccessful());
        $this->assertSame([], $result->successful());
        $this->assertSame([], $result->failed());
    }
}
