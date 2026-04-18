<?php

declare(strict_types=1);

namespace Horde\Smtp\Test\Unit;

use Horde\Smtp\DefaultSendDataStrategy;
use Horde\Smtp\SendDataStrategy;
use Horde\Smtp\SendEnvelope;
use Horde\Smtp\Strategy;
use Horde\Smtp\TlsSendDataStrategy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DefaultSendDataStrategy::class)]
#[CoversClass(TlsSendDataStrategy::class)]
#[CoversClass(SendEnvelope::class)]
class StrategyTest extends TestCase
{
    public function testDefaultSendDataStrategyImplementsInterfaces(): void
    {
        $strategy = new DefaultSendDataStrategy();

        $this->assertInstanceOf(Strategy::class, $strategy);
        $this->assertInstanceOf(SendDataStrategy::class, $strategy);
    }

    public function testTlsSendDataStrategyImplementsInterfaces(): void
    {
        $strategy = new TlsSendDataStrategy();

        $this->assertInstanceOf(Strategy::class, $strategy);
        $this->assertInstanceOf(SendDataStrategy::class, $strategy);
    }

    public function testSendEnvelopeHoldsValues(): void
    {
        $envelope = new SendEnvelope(
            mailCmd: 'MAIL FROM:<from@example.com> SIZE=100',
            recipientCmds: [
                'a@example.com' => 'RCPT TO:<a@example.com>',
                'b@example.com' => 'RCPT TO:<b@example.com>',
            ],
            bodyEncoding: '8bit',
        );

        $this->assertSame('MAIL FROM:<from@example.com> SIZE=100', $envelope->mailCmd);
        $this->assertCount(2, $envelope->recipientCmds);
        $this->assertSame('8bit', $envelope->bodyEncoding);
    }

    public function testSendEnvelopeNullEncoding(): void
    {
        $envelope = new SendEnvelope(
            mailCmd: 'MAIL FROM:<from@example.com>',
            recipientCmds: ['to@example.com' => 'RCPT TO:<to@example.com>'],
            bodyEncoding: null,
        );

        $this->assertNull($envelope->bodyEncoding);
    }
}
