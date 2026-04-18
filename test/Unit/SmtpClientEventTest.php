<?php

declare(strict_types=1);

namespace Horde\Smtp\Test\Unit;

use Horde\Smtp\AuthenticationException;
use Horde\Smtp\Event\AuthenticationFailed;
use Horde\Smtp\Event\AuthenticationSucceeded;
use Horde\Smtp\Event\ConnectionClosed;
use Horde\Smtp\Event\ConnectionEstablished;
use Horde\Smtp\Event\MessageSent;
use Horde\Smtp\PasswordCredentials;
use Horde\Smtp\PlainAuthenticator;
use Horde\Smtp\SmtpClient;
use Horde\Smtp\SmtpConfig;
use Horde\Smtp\SmtpResponse;
use Horde\Smtp\Test\FakeSmtpConnection;
use Horde\Smtp\Test\RecordingEventDispatcher;
use Horde\Socket\Client\SecureMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SmtpClient::class)]
class SmtpClientEventTest extends TestCase
{
    private function defaultEhloLines(): array
    {
        return [
            'mail.example.com',
            'SIZE 10240000',
            'PIPELINING',
            '8BITMIME',
            'AUTH PLAIN LOGIN',
        ];
    }

    private function makeConnection(array $ehloLines = []): FakeSmtpConnection
    {
        $conn = new FakeSmtpConnection();
        $conn->queueResponse(new SmtpResponse(220, ['ESMTP ready']));
        $conn->queueResponse(new SmtpResponse(250, $ehloLines));

        return $conn;
    }

    public function testConnectDispatchesConnectionEstablished(): void
    {
        $recorder = new RecordingEventDispatcher();
        $conn = $this->makeConnection($this->defaultEhloLines());

        $client = new SmtpClient(
            new SmtpConfig(
                security: SecureMode::None,
                eventDispatcher: $recorder,
            ),
            connection: $conn,
        );

        $client->connect();

        $this->assertCount(1, $recorder->events);
        $this->assertInstanceOf(ConnectionEstablished::class, $recorder->events[0]);
        $this->assertSame('localhost', $recorder->events[0]->getContext()['host']);
    }

    public function testConnectWithAuthDispatchesBothEvents(): void
    {
        $recorder = new RecordingEventDispatcher();
        $conn = $this->makeConnection($this->defaultEhloLines());
        $conn->queueResponse(new SmtpResponse(235, ['OK']));

        $client = new SmtpClient(
            new SmtpConfig(
                security: SecureMode::None,
                credentials: new PasswordCredentials('user', 'pass'),
                eventDispatcher: $recorder,
            ),
            authenticators: [new PlainAuthenticator()],
            connection: $conn,
        );

        $client->connect();

        $this->assertCount(2, $recorder->events);
        $this->assertInstanceOf(AuthenticationSucceeded::class, $recorder->events[0]);
        $this->assertSame('PLAIN', $recorder->events[0]->getContext()['mechanism']);
        $this->assertSame('user', $recorder->events[0]->getContext()['username']);
        $this->assertInstanceOf(ConnectionEstablished::class, $recorder->events[1]);
    }

    public function testFailedAuthDispatchesAuthenticationFailed(): void
    {
        $recorder = new RecordingEventDispatcher();
        $conn = $this->makeConnection($this->defaultEhloLines());
        $conn->queueResponse(new SmtpResponse(535, ['Bad credentials']));

        $client = new SmtpClient(
            new SmtpConfig(
                security: SecureMode::None,
                credentials: new PasswordCredentials('user', 'wrong'),
                eventDispatcher: $recorder,
            ),
            authenticators: [new PlainAuthenticator()],
            connection: $conn,
        );

        try {
            $client->connect();
            $this->fail('Expected AuthenticationException');
        } catch (AuthenticationException) {
        }

        $this->assertCount(1, $recorder->events);
        $this->assertInstanceOf(AuthenticationFailed::class, $recorder->events[0]);
        $this->assertSame(['PLAIN'], $recorder->events[0]->getContext()['mechanisms_tried']);
        $this->assertSame('user', $recorder->events[0]->getContext()['username']);
    }

    public function testSendDispatchesMessageSent(): void
    {
        $recorder = new RecordingEventDispatcher();
        $conn = $this->makeConnection($this->defaultEhloLines());
        $conn->queueResponse(new SmtpResponse(250));
        $conn->queueResponse(new SmtpResponse(250));
        $conn->queueResponse(new SmtpResponse(354));
        $conn->queueResponse(new SmtpResponse(250));

        $client = new SmtpClient(
            new SmtpConfig(
                security: SecureMode::None,
                eventDispatcher: $recorder,
            ),
            connection: $conn,
        );

        $client->send('from@example.com', 'to@example.com', "Subject: Test\r\n\r\nBody");

        $messageSentEvents = array_filter(
            $recorder->events,
            fn($e) => $e instanceof MessageSent,
        );
        $this->assertCount(1, $messageSentEvents);

        $event = reset($messageSentEvents);
        $this->assertSame('from@example.com', $event->getContext()['from']);
        $this->assertSame(['to@example.com'], $event->getContext()['recipients']);
        $this->assertArrayHasKey('size', $event->getContext());
    }

    public function testCloseDispatchesConnectionClosed(): void
    {
        $recorder = new RecordingEventDispatcher();
        $conn = $this->makeConnection($this->defaultEhloLines());
        $conn->queueResponse(new SmtpResponse(221));

        $client = new SmtpClient(
            new SmtpConfig(
                security: SecureMode::None,
                eventDispatcher: $recorder,
            ),
            connection: $conn,
        );

        $client->connect();
        $recorder->events = [];

        $client->close();

        $this->assertCount(1, $recorder->events);
        $this->assertInstanceOf(ConnectionClosed::class, $recorder->events[0]);
        $this->assertSame('localhost', $recorder->events[0]->getContext()['host']);
    }

    public function testCloseBeforeConnectDoesNotDispatch(): void
    {
        $recorder = new RecordingEventDispatcher();
        $conn = new FakeSmtpConnection();

        $client = new SmtpClient(
            new SmtpConfig(
                security: SecureMode::None,
                eventDispatcher: $recorder,
            ),
            connection: $conn,
        );

        $client->close();

        $this->assertEmpty($recorder->events);
    }

    public function testNoDispatcherConfiguredIsHarmless(): void
    {
        $conn = $this->makeConnection($this->defaultEhloLines());
        $conn->queueResponse(new SmtpResponse(221));

        $client = new SmtpClient(
            new SmtpConfig(security: SecureMode::None),
            connection: $conn,
        );

        $client->connect();
        $client->close();

        $this->assertTrue(true);
    }
}
