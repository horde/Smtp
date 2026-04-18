<?php

declare(strict_types=1);

/**
 * Copyright 2013-2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author Michael Slusarz <slusarz@horde.org>
 */

namespace Horde\Smtp;

use Horde\Socket\Client\ClientInterface;
use Horde\Socket\Client\Exception\StreamException;
use Horde\Socket\Client\Exception\TimeoutException;

/**
 * SmtpConnection implementation over a live TCP socket.
 *
 * Wraps Horde\Socket\Client\Client with SMTP-specific framing:
 * CRLF line termination, multi-line response parsing, enhanced
 * status code extraction, and debug logging.
 */
final class TcpSmtpConnection implements SmtpConnection
{
    public function __construct(
        private readonly ClientInterface $client,
        private readonly DebugInterface $debug,
    ) {}

    public function write(string|array $data): void
    {
        $lines = (array) $data;

        try {
            foreach ($lines as $line) {
                $this->debug->client($line);
                $this->client->write($line . "\r\n");
            }
        } catch (StreamException|TimeoutException $e) {
            throw new ConnectionException(
                'Write failed: ' . $e->getMessage(),
                previous: $e,
            );
        }
    }

    public function writeStream(mixed $resource, ?int $size = null): void
    {
        try {
            $written = 0;
            while (!feof($resource)) {
                if ($size !== null && $written >= $size) {
                    break;
                }

                $chunkSize = 65536;
                if ($size !== null) {
                    $chunkSize = min($chunkSize, $size - $written);
                }

                $chunk = fread($resource, $chunkSize);
                if ($chunk === false || $chunk === '') {
                    break;
                }

                $this->client->write($chunk);
                $written += strlen($chunk);
            }

            if ($size === null) {
                $this->client->write("\r\n");
            }
        } catch (StreamException|TimeoutException $e) {
            throw new ConnectionException(
                'Stream write failed: ' . $e->getMessage(),
                previous: $e,
            );
        }
    }

    public function readResponse(int|array $expectedCodes): SmtpResponse
    {
        $expected = (array) $expectedCodes;
        $textLines = [];
        $code = 0;

        try {
            while (true) {
                $line = $this->client->gets(8192);
                $line = rtrim($line, "\r\n");
                $this->debug->server($line);

                $code = (int) substr($line, 0, 3);
                $text = substr($line, 4);
                if ($text === false) {
                    $text = '';
                }
                $textLines[] = $text;

                if (!isset($line[3]) || $line[3] !== '-') {
                    break;
                }
            }
        } catch (StreamException|TimeoutException $e) {
            throw new ConnectionException(
                'Read failed: ' . $e->getMessage(),
                previous: $e,
            );
        }

        $enhancedCode = null;
        if (isset($textLines[0]) && preg_match('/^(\d\.\d+\.\d+)\s/', $textLines[0], $m)) {
            $enhancedCode = $m[1];
            $textLines[0] = ltrim(substr($textLines[0], strlen($m[1])));
        }

        $response = new SmtpResponse($code, $textLines, $enhancedCode);

        if (!in_array($code, $expected, true)) {
            throw new SmtpException(
                $response->text(),
                smtpCode: $code,
                enhancedCode: $enhancedCode,
            );
        }

        return $response;
    }

    public function isSecure(): bool
    {
        return $this->client->isSecure();
    }

    public function startTls(): bool
    {
        return $this->client->startTls();
    }

    public function close(): void
    {
        $this->client->close();
    }

    public function isConnected(): bool
    {
        return $this->client->isConnected();
    }
}
