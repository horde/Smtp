<?php

declare(strict_types=1);

namespace Horde\Smtp\Test;

use Horde\Socket\Client\ClientInterface;
use Horde\Socket\Client\StreamStatus;

/**
 * In-memory ClientInterface for testing TcpSmtpConnection.
 *
 * Queue server responses via queueLine(), inspect written data via $written.
 */
class FakeSocketClient implements ClientInterface
{
    /** @var string[] */
    public array $written = [];

    private string $readBuffer = '';
    private bool $connected = true;
    private bool $secure = false;

    public function queueLine(string $line): void
    {
        $this->readBuffer .= $line . "\r\n";
    }

    public function isConnected(): bool
    {
        return $this->connected;
    }

    public function isSecure(): bool
    {
        return $this->secure;
    }

    public function startTls(): bool
    {
        $this->secure = true;
        return true;
    }

    public function close(): void
    {
        $this->connected = false;
    }

    public function getStatus(): StreamStatus
    {
        return new StreamStatus(
            timedOut: false,
            blocked: false,
            eof: $this->readBuffer === '',
            unreadBytes: strlen($this->readBuffer),
        );
    }

    public function gets(int $size): string
    {
        $pos = strpos($this->readBuffer, "\n");
        if ($pos === false) {
            $line = substr($this->readBuffer, 0, $size - 1);
            $this->readBuffer = substr($this->readBuffer, strlen($line));
            return $line;
        }

        $line = substr($this->readBuffer, 0, min($pos + 1, $size - 1));
        $this->readBuffer = substr($this->readBuffer, strlen($line));
        return $line;
    }

    public function read(int $size): string
    {
        $data = substr($this->readBuffer, 0, $size);
        $this->readBuffer = substr($this->readBuffer, $size);
        return $data;
    }

    public function write(string $data): void
    {
        $this->written[] = $data;
    }
}
