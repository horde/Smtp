<?php

declare(strict_types=1);

namespace Horde\Smtp\Test\Unit;

use Horde\Smtp\AuthenticationException;
use Horde\Smtp\ConnectionException;
use Horde\Smtp\DefaultSendDataStrategy;
use Horde\Smtp\NullDebug;
use Horde\Smtp\PasswordCredentials;
use Horde\Smtp\PlainAuthenticator;
use Horde\Smtp\Protocol;
use Horde\Smtp\SendResult;
use Horde\Smtp\ServerCapabilities;
use Horde\Smtp\SmtpClient;
use Horde\Smtp\SmtpConfig;
use Horde\Smtp\SmtpException;
use Horde\Smtp\SmtpResponse;
use Horde\Smtp\TlsSendDataStrategy;
use Horde\Smtp\Test\FakeSmtpConnection;
use Horde\Smtp\Xoauth2Authenticator;
use Horde\Smtp\Xoauth2Credentials;
use Horde\Socket\Client\SecureMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

#[CoversClass(SmtpClient::class)]
class SmtpClientTest extends TestCase
{
    private function makeConnection(array $ehloLines = [], int $greeting = 220): FakeSmtpConnection
    {
        $conn = new FakeSmtpConnection();
        $conn->queueResponse(new SmtpResponse($greeting, ['ESMTP ready']));
        $conn->queueResponse(new SmtpResponse(250, $ehloLines));
        return $conn;
    }

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

    public function testConstructorValidatesPort(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SmtpClient(
            new SmtpConfig(port: 25, security: SecureMode::None),
            Protocol::Lmtp,
        );
    }

    public function testConnectSendsEhloAndReturnsCapabilities(): void
    {
        $conn = $this->makeConnection($this->defaultEhloLines());
        $client = new SmtpClient(
            new SmtpConfig(security: SecureMode::None),
            connection: $conn,
        );

        $caps = $client->connect();

        $this->assertInstanceOf(ServerCapabilities::class, $caps);
        $this->assertTrue($caps->supportsPipelining());
        $this->assertTrue($caps->supports8BitMime());
        $this->assertSame('EHLO', substr($conn->written[0], 0, 4));
    }

    public function testConnectLmtpSendsLhlo(): void
    {
        $conn = new FakeSmtpConnection();
        $conn->queueResponse(new SmtpResponse(220));
        $conn->queueResponse(new SmtpResponse(250, [
            'lmtp.local',
            'ENHANCEDSTATUSCODES',
            'PIPELINING',
        ]));

        $client = new SmtpClient(
            new SmtpConfig(port: 24, security: SecureMode::None),
            Protocol::Lmtp,
            connection: $conn,
        );

        $client->connect();

        $this->assertStringStartsWith('LHLO', $conn->written[0]);
    }

    public function testConnectIsIdempotent(): void
    {
        $conn = $this->makeConnection($this->defaultEhloLines());
        $client = new SmtpClient(
            new SmtpConfig(security: SecureMode::None),
            connection: $conn,
        );

        $caps1 = $client->connect();
        $caps2 = $client->connect();

        $this->assertSame($caps1, $caps2);
    }

    public function testConnectAuthenticatesWhenCredentialsProvided(): void
    {
        $conn = new FakeSmtpConnection();
        $conn->queueResponse(new SmtpResponse(220));
        $conn->queueResponse(new SmtpResponse(250, $this->defaultEhloLines()));
        $conn->queueResponse(new SmtpResponse(235, ['Authenticated']));

        $client = new SmtpClient(
            new SmtpConfig(
                security: SecureMode::None,
                credentials: new PasswordCredentials('user', 'pass'),
            ),
            authenticators: [new PlainAuthenticator()],
            connection: $conn,
        );

        $client->connect();

        $hasAuth = false;
        foreach ($conn->written as $line) {
            if (str_starts_with($line, 'AUTH PLAIN')) {
                $hasAuth = true;
                break;
            }
        }
        $this->assertTrue($hasAuth);
    }

    public function testConnectThrowsWhenNoAuthAdvertised(): void
    {
        $conn = new FakeSmtpConnection();
        $conn->queueResponse(new SmtpResponse(220));
        $conn->queueResponse(new SmtpResponse(250, [
            'mail.example.com',
            'SIZE 10240000',
        ]));

        $client = new SmtpClient(
            new SmtpConfig(
                security: SecureMode::None,
                credentials: new Xoauth2Credentials('user', 'token'),
            ),
            authenticators: [new Xoauth2Authenticator()],
            connection: $conn,
        );

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('does not advertise AUTH');
        $client->connect();
    }

    public function testConnectWithXoauth2Authenticates(): void
    {
        $conn = new FakeSmtpConnection();
        $conn->queueResponse(new SmtpResponse(220));
        $conn->queueResponse(new SmtpResponse(250, [
            'mail.example.com',
            'AUTH XOAUTH2',
        ]));
        $conn->queueResponse(new SmtpResponse(235));

        $client = new SmtpClient(
            new SmtpConfig(
                security: SecureMode::None,
                credentials: new Xoauth2Credentials('user@example.com', 'ya29.tok'),
            ),
            authenticators: [new Xoauth2Authenticator()],
            connection: $conn,
        );

        $client->connect();

        $hasAuth = false;
        foreach ($conn->written as $line) {
            if (str_starts_with($line, 'AUTH XOAUTH2')) {
                $hasAuth = true;
                break;
            }
        }
        $this->assertTrue($hasAuth);
    }

    public function testNoop(): void
    {
        $conn = $this->makeConnection($this->defaultEhloLines());
        $conn->queueResponse(new SmtpResponse(250));

        $client = new SmtpClient(
            new SmtpConfig(security: SecureMode::None),
            connection: $conn,
        );

        $client->noop();

        $this->assertSame('NOOP', $conn->written[1]);
    }

    public function testReset(): void
    {
        $conn = $this->makeConnection($this->defaultEhloLines());
        $conn->queueResponse(new SmtpResponse(250));

        $client = new SmtpClient(
            new SmtpConfig(security: SecureMode::None),
            connection: $conn,
        );

        $client->reset();

        $this->assertSame('RSET', $conn->written[1]);
    }

    public function testClose(): void
    {
        $conn = $this->makeConnection($this->defaultEhloLines());
        $conn->queueResponse(new SmtpResponse(221));

        $client = new SmtpClient(
            new SmtpConfig(security: SecureMode::None),
            connection: $conn,
        );

        $client->connect();
        $client->close();

        $this->assertSame('QUIT', $conn->written[1]);
    }

    public function testCloseBeforeConnectIsNoop(): void
    {
        $conn = new FakeSmtpConnection();
        $client = new SmtpClient(
            new SmtpConfig(security: SecureMode::None),
            connection: $conn,
        );

        $client->close();

        $this->assertSame([], $conn->written);
    }

    public function testSendSmtp(): void
    {
        $conn = $this->makeConnection($this->defaultEhloLines());
        $conn->queueResponse(new SmtpResponse(250));
        $conn->queueResponse(new SmtpResponse(250));
        $conn->queueResponse(new SmtpResponse(354));
        $conn->queueResponse(new SmtpResponse(250));

        $client = new SmtpClient(
            new SmtpConfig(security: SecureMode::None),
            connection: $conn,
        );

        $result = $client->send('from@example.com', 'to@example.com', "Subject: Test\r\n\r\nHello");

        $this->assertInstanceOf(SendResult::class, $result);
        $this->assertTrue($result->allSuccessful());
        $this->assertSame(['to@example.com'], $result->successful());

        $hasMailFrom = false;
        $hasRcptTo = false;
        $hasData = false;
        foreach ($conn->written as $line) {
            if (str_starts_with($line, 'MAIL FROM:<from@example.com>')) {
                $hasMailFrom = true;
            }
            if ($line === 'RCPT TO:<to@example.com>') {
                $hasRcptTo = true;
            }
            if ($line === 'DATA') {
                $hasData = true;
            }
        }
        $this->assertTrue($hasMailFrom);
        $this->assertTrue($hasRcptTo);
        $this->assertTrue($hasData);
    }

    public function testSendLmtpPerRecipient(): void
    {
        $conn = new FakeSmtpConnection();
        $conn->queueResponse(new SmtpResponse(220));
        $conn->queueResponse(new SmtpResponse(250, [
            'lmtp.local',
            'ENHANCEDSTATUSCODES',
            'PIPELINING',
        ]));
        $conn->queueResponse(new SmtpResponse(250));
        $conn->queueResponse(new SmtpResponse(250));
        $conn->queueResponse(new SmtpResponse(250));
        $conn->queueResponse(new SmtpResponse(354));
        $conn->queueResponse(new SmtpResponse(250));
        $conn->queueResponse(new SmtpResponse(550, ['User unknown'], '5.1.1'));

        $client = new SmtpClient(
            new SmtpConfig(port: 24, security: SecureMode::None),
            Protocol::Lmtp,
            connection: $conn,
        );

        $result = $client->send(
            'from@example.com',
            ['a@example.com', 'b@example.com'],
            "Subject: Test\r\n\r\nHello",
        );

        $this->assertFalse($result->allSuccessful());
        $this->assertSame(['a@example.com'], $result->successful());
        $this->assertArrayHasKey('b@example.com', $result->failed());
    }

    public function testLmtpRequiresEnhancedStatusCodes(): void
    {
        $conn = new FakeSmtpConnection();
        $conn->queueResponse(new SmtpResponse(220));
        $conn->queueResponse(new SmtpResponse(250, ['lmtp.local', 'PIPELINING']));

        $client = new SmtpClient(
            new SmtpConfig(port: 24, security: SecureMode::None),
            Protocol::Lmtp,
            connection: $conn,
        );

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('ENHANCEDSTATUSCODES');
        $client->connect();
    }

    public function testDefaultAuthenticatorsForPassword(): void
    {
        $conn = new FakeSmtpConnection();
        $conn->queueResponse(new SmtpResponse(220));
        $conn->queueResponse(new SmtpResponse(250, [
            'mail.example.com',
            'AUTH PLAIN LOGIN CRAM-MD5',
        ]));
        // CRAM-MD5 attempt: 334 challenge, then 535 fail
        $conn->queueResponse(new SmtpResponse(334, [base64_encode('<challenge@server>')]));
        $conn->queueResponse(new SmtpResponse(535, ['Bad']));
        // PLAIN attempt: 235 success
        $conn->queueResponse(new SmtpResponse(235, ['OK']));

        $client = new SmtpClient(
            new SmtpConfig(
                security: SecureMode::None,
                credentials: new PasswordCredentials('user', 'pass'),
            ),
            connection: $conn,
        );

        $client->connect();

        $authCommands = array_filter($conn->written, fn($l) => str_starts_with($l, 'AUTH '));
        $this->assertNotEmpty($authCommands);
    }

    public function testCapabilitiesCallsConnect(): void
    {
        $conn = $this->makeConnection($this->defaultEhloLines());
        $client = new SmtpClient(
            new SmtpConfig(security: SecureMode::None),
            connection: $conn,
        );

        $caps = $client->capabilities();

        $this->assertInstanceOf(ServerCapabilities::class, $caps);
        $this->assertNotEmpty($conn->written);
    }

    public function testSendWithPipelining(): void
    {
        $conn = $this->makeConnection($this->defaultEhloLines());
        $conn->queueResponse(new SmtpResponse(250));
        $conn->queueResponse(new SmtpResponse(250));
        $conn->queueResponse(new SmtpResponse(250));
        $conn->queueResponse(new SmtpResponse(354));
        $conn->queueResponse(new SmtpResponse(250));

        $client = new SmtpClient(
            new SmtpConfig(security: SecureMode::None),
            connection: $conn,
        );

        $result = $client->send(
            'from@example.com',
            ['a@example.com', 'b@example.com'],
            "Subject: Test\r\n\r\nBody",
        );

        $this->assertTrue($result->allSuccessful());
    }

    public function testSendWithSizeExtension(): void
    {
        $conn = $this->makeConnection($this->defaultEhloLines());
        $conn->queueResponse(new SmtpResponse(250));
        $conn->queueResponse(new SmtpResponse(250));
        $conn->queueResponse(new SmtpResponse(354));
        $conn->queueResponse(new SmtpResponse(250));

        $client = new SmtpClient(
            new SmtpConfig(security: SecureMode::None),
            connection: $conn,
        );

        $client->send('from@example.com', 'to@example.com', "Subject: Test\r\n\r\nBody");

        $mailFrom = null;
        foreach ($conn->written as $line) {
            if (str_starts_with($line, 'MAIL FROM:')) {
                $mailFrom = $line;
                break;
            }
        }

        $this->assertNotNull($mailFrom);
        $this->assertStringContainsString('SIZE=', $mailFrom);
    }

    public function testProcessQueueSendsEtrn(): void
    {
        $conn = $this->makeConnection([
            'mail.example.com',
            'ETRN',
            'SIZE 10240000',
        ]);
        $conn->queueResponse(new SmtpResponse(250, ['OK']));

        $client = new SmtpClient(
            new SmtpConfig(security: SecureMode::None, localhost: 'myhost.local'),
            connection: $conn,
        );

        $client->processQueue();

        $this->assertSame('ETRN myhost.local', $conn->written[1]);
    }

    public function testProcessQueueWithExplicitHost(): void
    {
        $conn = $this->makeConnection([
            'mail.example.com',
            'ETRN',
        ]);
        $conn->queueResponse(new SmtpResponse(252, ['OK']));

        $client = new SmtpClient(
            new SmtpConfig(security: SecureMode::None),
            connection: $conn,
        );

        $client->processQueue('remote.example.com');

        $this->assertSame('ETRN remote.example.com', $conn->written[1]);
    }

    public function testProcessQueueNoopWithoutEtrn(): void
    {
        $conn = $this->makeConnection($this->defaultEhloLines());

        $client = new SmtpClient(
            new SmtpConfig(security: SecureMode::None),
            connection: $conn,
        );

        $client->processQueue();

        $this->assertCount(1, $conn->written);
    }

    public function testSendWithEaiAddressIncludesSmtputf8(): void
    {
        $conn = $this->makeConnection([
            'mail.example.com',
            'SIZE 10240000',
            'PIPELINING',
            '8BITMIME',
            'SMTPUTF8',
        ]);
        $conn->queueResponse(new SmtpResponse(250));
        $conn->queueResponse(new SmtpResponse(250));
        $conn->queueResponse(new SmtpResponse(354));
        $conn->queueResponse(new SmtpResponse(250));

        $client = new SmtpClient(
            new SmtpConfig(security: SecureMode::None),
            connection: $conn,
        );

        $client->send('ünîcödé@example.com', 'to@example.com', "Subject: Test\r\n\r\nBody");

        $mailFrom = null;
        foreach ($conn->written as $line) {
            if (str_starts_with($line, 'MAIL FROM:')) {
                $mailFrom = $line;
                break;
            }
        }

        $this->assertNotNull($mailFrom);
        $this->assertStringContainsString('SMTPUTF8', $mailFrom);
        $this->assertStringContainsString('BODY=8BITMIME', $mailFrom);
    }

    public function testSendWithEaiRecipientIncludesSmtputf8(): void
    {
        $conn = $this->makeConnection([
            'mail.example.com',
            'SIZE 10240000',
            '8BITMIME',
            'SMTPUTF8',
        ]);
        $conn->queueResponse(new SmtpResponse(250));
        $conn->queueResponse(new SmtpResponse(250));
        $conn->queueResponse(new SmtpResponse(354));
        $conn->queueResponse(new SmtpResponse(250));

        $client = new SmtpClient(
            new SmtpConfig(security: SecureMode::None),
            connection: $conn,
        );

        $client->send('from@example.com', 'tö@example.com', "Subject: Test\r\n\r\nBody");

        $mailFrom = null;
        foreach ($conn->written as $line) {
            if (str_starts_with($line, 'MAIL FROM:')) {
                $mailFrom = $line;
                break;
            }
        }

        $this->assertNotNull($mailFrom);
        $this->assertStringContainsString('SMTPUTF8', $mailFrom);
    }

    public function testSendWithEaiThrowsWithoutServerSupport(): void
    {
        $conn = $this->makeConnection($this->defaultEhloLines());

        $client = new SmtpClient(
            new SmtpConfig(security: SecureMode::None),
            connection: $conn,
        );

        $this->expectException(SmtpException::class);
        $this->expectExceptionMessage('SMTPUTF8');
        $client->send('ünîcödé@example.com', 'to@example.com', "Subject: Test\r\n\r\nBody");
    }

    public function testSendDelegatesToDefaultStrategy(): void
    {
        $conn = $this->makeConnection($this->defaultEhloLines());
        $conn->queueResponse(new SmtpResponse(250));
        $conn->queueResponse(new SmtpResponse(250));
        $conn->queueResponse(new SmtpResponse(354));
        $conn->queueResponse(new SmtpResponse(250));

        $client = new SmtpClient(
            new SmtpConfig(
                security: SecureMode::None,
                strategy: new DefaultSendDataStrategy(),
            ),
            connection: $conn,
        );

        $result = $client->send('from@example.com', 'to@example.com', "Subject: Test\r\n\r\nBody");

        $this->assertTrue($result->allSuccessful());
    }

    public function testSendWithoutExplicitStrategyUsesDefault(): void
    {
        $conn = $this->makeConnection($this->defaultEhloLines());
        $conn->queueResponse(new SmtpResponse(250));
        $conn->queueResponse(new SmtpResponse(250));
        $conn->queueResponse(new SmtpResponse(354));
        $conn->queueResponse(new SmtpResponse(250));

        $client = new SmtpClient(
            new SmtpConfig(security: SecureMode::None),
            connection: $conn,
        );

        $result = $client->send('from@example.com', 'to@example.com', "Subject: Test\r\n\r\nBody");

        $this->assertTrue($result->allSuccessful());
    }
}
