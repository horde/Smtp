<?php

declare(strict_types=1);

namespace Horde\Smtp\Test\Unit\Event;

use Horde\Smtp\Event\AuthenticationFailed;
use Horde\Smtp\Event\AuthenticationSucceeded;
use Horde\Smtp\Event\ConnectionClosed;
use Horde\Smtp\Event\ConnectionEstablished;
use Horde\Smtp\Event\MessageSent;
use Horde\Smtp\Event\SmtpEvent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SmtpEvent::class)]
#[CoversClass(ConnectionEstablished::class)]
#[CoversClass(ConnectionClosed::class)]
#[CoversClass(AuthenticationSucceeded::class)]
#[CoversClass(AuthenticationFailed::class)]
#[CoversClass(MessageSent::class)]
class SmtpEventTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string<SmtpEvent>}>
     */
    public static function eventClassProvider(): iterable
    {
        yield 'ConnectionEstablished' => [ConnectionEstablished::class];
        yield 'ConnectionClosed' => [ConnectionClosed::class];
        yield 'AuthenticationSucceeded' => [AuthenticationSucceeded::class];
        yield 'AuthenticationFailed' => [AuthenticationFailed::class];
        yield 'MessageSent' => [MessageSent::class];
    }

    #[DataProvider('eventClassProvider')]
    public function testEventExtendsSmtpEvent(string $class): void
    {
        $event = new $class();

        $this->assertInstanceOf(SmtpEvent::class, $event);
    }

    #[DataProvider('eventClassProvider')]
    public function testDefaultMessageIsEmpty(string $class): void
    {
        $event = new $class();

        $this->assertSame('', $event->getMessage());
    }

    #[DataProvider('eventClassProvider')]
    public function testDefaultContextIsEmpty(string $class): void
    {
        $event = new $class();

        $this->assertSame([], $event->getContext());
    }

    #[DataProvider('eventClassProvider')]
    public function testMessageAndContextAreStored(string $class): void
    {
        $event = new $class('test message', ['key' => 'value']);

        $this->assertSame('test message', $event->getMessage());
        $this->assertSame(['key' => 'value'], $event->getContext());
    }

    public function testConnectionEstablishedTypicalContext(): void
    {
        $event = new ConnectionEstablished('Connected', [
            'host' => 'mail.example.com',
            'port' => 587,
            'secure' => true,
        ]);

        $ctx = $event->getContext();
        $this->assertSame('mail.example.com', $ctx['host']);
        $this->assertSame(587, $ctx['port']);
        $this->assertTrue($ctx['secure']);
    }

    public function testMessageSentTypicalContext(): void
    {
        $event = new MessageSent('Message sent', [
            'from' => 'sender@example.com',
            'recipients' => ['a@example.com', 'b@example.com'],
            'size' => 4096,
        ]);

        $ctx = $event->getContext();
        $this->assertSame('sender@example.com', $ctx['from']);
        $this->assertCount(2, $ctx['recipients']);
        $this->assertSame(4096, $ctx['size']);
    }
}
