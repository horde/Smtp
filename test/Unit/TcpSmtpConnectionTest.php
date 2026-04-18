<?php

declare(strict_types=1);

namespace Horde\Smtp\Test\Unit;

use Horde\Smtp\ConnectionException;
use Horde\Smtp\SmtpException;
use Horde\Smtp\SmtpResponse;
use Horde\Smtp\TcpSmtpConnection;
use Horde\Smtp\Test\FakeSocketClient;
use Horde\Smtp\Test\SpyDebug;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TcpSmtpConnection::class)]
class TcpSmtpConnectionTest extends TestCase
{
    private FakeSocketClient $client;
    private SpyDebug $debug;
    private TcpSmtpConnection $conn;

    protected function setUp(): void
    {
        $this->client = new FakeSocketClient();
        $this->debug = new SpyDebug();
        $this->conn = new TcpSmtpConnection($this->client, $this->debug);
    }

    public function testWriteStringAppendsCrlf(): void
    {
        $this->conn->write('EHLO localhost');

        $this->assertSame(["EHLO localhost\r\n"], $this->client->written);
    }

    public function testWriteArraySendsEachLine(): void
    {
        $this->conn->write(['MAIL FROM:<a@b>', 'RCPT TO:<c@d>']);

        $this->assertCount(2, $this->client->written);
        $this->assertSame("MAIL FROM:<a@b>\r\n", $this->client->written[0]);
        $this->assertSame("RCPT TO:<c@d>\r\n", $this->client->written[1]);
    }

    public function testWriteLogsToDebug(): void
    {
        $this->conn->write('NOOP');

        $this->assertSame(['C: NOOP'], $this->debug->messages);
    }

    public function testReadSingleLineResponse(): void
    {
        $this->client->queueLine('250 OK');

        $resp = $this->conn->readResponse(250);

        $this->assertSame(250, $resp->code);
        $this->assertSame(['OK'], $resp->lines);
        $this->assertNull($resp->enhancedCode);
    }

    public function testReadMultiLineResponse(): void
    {
        $this->client->queueLine('250-mail.example.com');
        $this->client->queueLine('250-SIZE 10240000');
        $this->client->queueLine('250 PIPELINING');

        $resp = $this->conn->readResponse(250);

        $this->assertSame(250, $resp->code);
        $this->assertCount(3, $resp->lines);
        $this->assertSame('mail.example.com', $resp->lines[0]);
        $this->assertSame('SIZE 10240000', $resp->lines[1]);
        $this->assertSame('PIPELINING', $resp->lines[2]);
    }

    public function testReadResponseExtractsEnhancedCode(): void
    {
        $this->client->queueLine('550 5.1.1 User unknown');

        try {
            $this->conn->readResponse(250);
            $this->fail('Expected SmtpException');
        } catch (SmtpException $e) {
            $this->assertSame(550, $e->smtpCode);
            $this->assertSame('5.1.1', $e->enhancedCode);
        }
    }

    public function testReadResponseEnhancedCodeOnSuccess(): void
    {
        $this->client->queueLine('250 2.0.0 OK');

        $resp = $this->conn->readResponse(250);

        $this->assertSame('2.0.0', $resp->enhancedCode);
        $this->assertSame('OK', $resp->lines[0]);
    }

    public function testReadResponseThrowsOnUnexpectedCode(): void
    {
        $this->client->queueLine('535 Authentication failed');

        $this->expectException(SmtpException::class);
        $this->conn->readResponse(235);
    }

    public function testReadResponseAcceptsMultipleExpectedCodes(): void
    {
        $this->client->queueLine('251 User forwarded');

        $resp = $this->conn->readResponse([250, 251]);

        $this->assertSame(251, $resp->code);
    }

    public function testReadResponseLogsToDebug(): void
    {
        $this->client->queueLine('220 Ready');

        $this->conn->readResponse(220);

        $this->assertStringContainsString('220 Ready', $this->debug->messages[0]);
    }

    public function testStartTlsDelegates(): void
    {
        $this->assertFalse($this->conn->isSecure());
        $this->assertTrue($this->conn->startTls());
        $this->assertTrue($this->conn->isSecure());
    }

    public function testCloseDelegates(): void
    {
        $this->assertTrue($this->conn->isConnected());
        $this->conn->close();
        $this->assertFalse($this->conn->isConnected());
    }

    public function testWriteStreamSendsData(): void
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, 'Hello world');
        rewind($stream);

        $this->conn->writeStream($stream);

        $combined = implode('', $this->client->written);
        $this->assertStringContainsString('Hello world', $combined);
        $this->assertStringEndsWith("\r\n", $combined);

        fclose($stream);
    }

    public function testWriteStreamWithSizeLimit(): void
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, str_repeat('A', 100));
        rewind($stream);

        $this->conn->writeStream($stream, 50);

        $combined = implode('', $this->client->written);
        $this->assertSame(50, strlen($combined));

        fclose($stream);
    }
}
