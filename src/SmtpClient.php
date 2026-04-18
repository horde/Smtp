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

use Horde\Socket\Client\Client;
use Horde\Socket\Client\ConnectionConfig;
use Horde\Socket\Client\Exception\SocketException;
use Horde\Socket\Client\SecureMode;
use Throwable;
use stdClass;

/**
 * SMTP/LMTP client.
 *
 * Composes Connection + Authenticators + ServerCapabilities + Protocol
 * into a single public API. Replaces Horde_Smtp (lib/).
 *
 * @api-unstable Interface may change before 2.0 stable.
 */
final class SmtpClient
{
    private ?TcpSmtpConnection $ownConnection = null;
    private ?SmtpConnection $connection;
    private ?ServerCapabilities $capabilities = null;
    private DebugInterface $debug;

    /** @var Authenticator[] */
    private array $resolvedAuthenticators;

    /**
     * @param SmtpConfig $config  Connection and credential settings.
     * @param Protocol $protocol  SMTP or LMTP behavioral variant.
     * @param Authenticator[] $authenticators  Override auto-detection with explicit list.
     * @param SmtpConnection|null $connection  Inject a connection (for testing).
     *     If null, a TcpSmtpConnection is created on connect().
     */
    public function __construct(
        private readonly SmtpConfig $config,
        private readonly Protocol $protocol = Protocol::Smtp,
        private readonly array $authenticators = [],
        ?SmtpConnection $connection = null,
    ) {
        $protocol->validatePort($config->port);
        $this->debug = $config->debug ?? new NullDebug();
        $this->connection = $connection;
        $this->resolvedAuthenticators = $authenticators !== []
            ? $authenticators
            : $this->defaultAuthenticators();
    }

    public function __destruct()
    {
        try {
            $this->close();
        } catch (Throwable) {
        }
    }

    /**
     * Connect, negotiate TLS, discover capabilities, and authenticate.
     */
    public function connect(): ServerCapabilities
    {
        if ($this->capabilities !== null) {
            return $this->capabilities;
        }

        $this->ensureConnection();

        $this->connection->readResponse(220);

        $this->capabilities = $this->hello();

        if ($this->config->security === SecureMode::Tls && !$this->isSecureConnection()) {
            $this->negotiateStartTls();
            $this->capabilities = $this->hello();
        }

        $this->checkRequiredExtensions();

        if ($this->config->credentials !== null) {
            $this->authenticate();
        }

        return $this->capabilities;
    }

    /**
     * Send a message.
     *
     * @param string $from  Sender bare address (user@domain).
     * @param string|string[] $to  Recipient address(es).
     * @param string|resource $data  Message content (headers + body).
     */
    public function send(string $from, string|array $to, mixed $data): SendResult
    {
        $this->connect();

        $recipients = (array) $to;

        $stream = $this->prepareDataStream($data);
        $bodyEncoding = $this->detectBodyEncoding($stream);
        $size = (int) ftell($stream);
        rewind($stream);

        $mailCmd = $this->buildMailFrom($from, $size, $bodyEncoding);
        $recipCmds = [];
        foreach ($recipients as $addr) {
            $recipCmds[$addr] = 'RCPT TO:<' . $addr . '>';
        }

        $this->sendEnvelope($mailCmd, $recipCmds);

        $chunking = $this->capabilities->supportsChunking();
        $chunkSize = $this->config->chunkSize;
        $chunkForce = $bodyEncoding === 'binary';

        if ($chunking && $chunkSize > 0 && ($chunkForce || $size > $chunkSize)) {
            $this->sendBdat($stream, $size, $chunkSize);
        } else {
            $this->sendData($stream);
        }

        fclose($stream);

        return $this->readDataResponse($recipients);
    }

    public function capabilities(): ServerCapabilities
    {
        return $this->connect();
    }

    public function isSecureConnection(): bool
    {
        if ($this->connection instanceof TcpSmtpConnection) {
            return $this->connection->isSecure();
        }

        return false;
    }

    public function noop(): void
    {
        $this->connect();
        $this->connection->write('NOOP');
        $this->connection->readResponse(250);
    }

    public function reset(): void
    {
        $this->connect();
        $this->connection->write('RSET');
        $this->connection->readResponse(250);
    }

    public function close(): void
    {
        if ($this->connection === null) {
            return;
        }

        $connected = $this->connection instanceof TcpSmtpConnection
            ? $this->connection->isConnected()
            : true;

        if ($this->capabilities !== null && $connected) {
            try {
                $this->connection->write('QUIT');
                $this->connection->readResponse(221);
            } catch (Throwable) {
            }

            if ($this->connection instanceof TcpSmtpConnection) {
                $this->connection->close();
            }
        }

        if ($this->ownConnection !== null) {
            $this->ownConnection = null;
        }

        $this->connection = null;
        $this->capabilities = null;
    }

    // -- private helpers --

    private function ensureConnection(): void
    {
        if ($this->connection !== null) {
            return;
        }

        $secure = $this->config->security;
        if ($secure === SecureMode::Tls) {
            $secure = SecureMode::None;
        }

        try {
            $client = new Client(
                new ConnectionConfig(
                    host: $this->config->host,
                    port: $this->config->port,
                    secure: $secure,
                    connectTimeout: $this->config->timeout,
                    readTimeout: $this->config->timeout,
                    context: $this->config->context,
                ),
            );
        } catch (SocketException $e) {
            throw new ConnectionException(
                'Failed to connect to ' . $this->config->host . ':' . $this->config->port,
                previous: $e,
            );
        }

        $this->ownConnection = new TcpSmtpConnection($client, $this->debug);
        $this->connection = $this->ownConnection;
    }

    private function hello(): ServerCapabilities
    {
        $hostname = $this->config->localhost ?? gethostname() ?: 'localhost';

        $this->connection->write($this->protocol->greeting() . ' ' . $hostname);

        try {
            $resp = $this->connection->readResponse(250);
        } catch (SmtpException $e) {
            if ($e->smtpCode === 502 && $this->protocol === Protocol::Smtp) {
                $this->connection->write('HELO ' . $hostname);
                try {
                    $this->connection->readResponse(250);
                } catch (SmtpException) {
                    throw $e;
                }
                return new ServerCapabilities([]);
            }
            throw $e;
        }

        $extensions = [];
        foreach ($resp->lines as $line) {
            $parts = explode(' ', $line, 2);
            $name = strtoupper($parts[0]);
            $extensions[$name] = $parts[1] ?? true;
        }

        return new ServerCapabilities($extensions);
    }

    private function negotiateStartTls(): void
    {
        if (!$this->capabilities->supportsStartTls()) {
            throw new ConnectionException(
                'Server does not advertise STARTTLS',
                smtpCode: 454,
            );
        }

        $this->connection->write('STARTTLS');
        $this->connection->readResponse(220);

        if ($this->connection instanceof TcpSmtpConnection) {
            if (!$this->connection->startTls()) {
                throw new ConnectionException(
                    'TLS negotiation failed',
                    smtpCode: 454,
                );
            }
        }

        $this->debug->info('TLS negotiation successful');
    }

    private function checkRequiredExtensions(): void
    {
        foreach ($this->protocol->requiredExtensions() as $ext) {
            if (!$this->capabilities->supports($ext)) {
                throw new ConnectionException(
                    sprintf('Required extension %s not supported by server', $ext),
                );
            }
        }
    }

    private function authenticate(): void
    {
        $credentials = $this->config->credentials;
        $serverMethods = $this->capabilities->authMethods();

        if ($serverMethods === []) {
            throw new AuthenticationException('Server does not advertise AUTH');
        }

        $lastException = null;
        foreach ($this->resolvedAuthenticators as $auth) {
            if (!$auth->supports($credentials) || !in_array($auth->mechanism(), $serverMethods, true)) {
                continue;
            }

            try {
                $auth->authenticate($this->connection, $credentials, $this->debug);
                return;
            } catch (SmtpException $e) {
                $lastException = $e;
            }
        }

        throw new AuthenticationException(
            'All authentication methods failed',
            previous: $lastException,
        );
    }

    /**
     * @return Authenticator[]
     */
    private function defaultAuthenticators(): array
    {
        $creds = $this->config->credentials;
        if ($creds === null) {
            return [];
        }

        $list = [];

        if ($creds instanceof Xoauth2Credentials) {
            $list[] = new Xoauth2Authenticator();
        }

        if ($creds instanceof PasswordCredentials) {
            $list[] = new CramAuthenticator('CRAM-SHA256');
            $list[] = new CramAuthenticator('CRAM-MD5');
            $list[] = new DigestMd5Authenticator($this->config->host);
            $list[] = new PlainAuthenticator();
            $list[] = new LoginAuthenticator();
        }

        return $list;
    }

    /**
     * @return resource  Filtered temp stream ready for sending.
     */
    private function prepareDataStream(mixed $data): mixed
    {
        $stream = fopen('php://temp', 'r+');

        stream_filter_register('horde.smtp.data', DataFilter::class);
        $dataFilter = stream_filter_append($stream, 'horde.smtp.data', STREAM_FILTER_WRITE);

        if (is_resource($data)) {
            while (!feof($data)) {
                $chunk = fread($data, 8192);
                if ($chunk !== false && $chunk !== '') {
                    fwrite($stream, $chunk);
                }
            }
        } else {
            fwrite($stream, (string) $data);
        }

        stream_filter_remove($dataFilter);

        return $stream;
    }

    private function detectBodyEncoding(mixed $stream): ?string
    {
        rewind($stream);

        stream_filter_register('horde.smtp.body', BodyFilter::class);
        $params = new stdClass();
        $bodyFilter = stream_filter_append($stream, 'horde.smtp.body', STREAM_FILTER_READ, $params);

        while (!feof($stream)) {
            fread($stream, 8192);
        }

        stream_filter_remove($bodyFilter);

        $body = $params->body ?? false;

        return $body === false ? null : $body;
    }

    private function buildMailFrom(string $from, int $size, ?string $bodyEncoding): string
    {
        $cmd = 'MAIL FROM:<' . $from . '>';

        if ($this->capabilities->supports('SIZE')) {
            $cmd .= ' SIZE=' . $size;
        }

        if ($bodyEncoding === '8bit') {
            if ($this->capabilities->supports8BitMime()) {
                $cmd .= ' BODY=8BITMIME';
            } else {
                throw new SmtpException('Server does not support 8-bit message data');
            }
        } elseif ($bodyEncoding === 'binary') {
            if ($this->capabilities->supportsBinaryMime()) {
                $cmd .= ' BODY=BINARYMIME';
            } else {
                throw new SmtpException('Server does not support binary message data');
            }
        }

        return $cmd;
    }

    /**
     * @param array<string, string> $recipCmds
     */
    private function sendEnvelope(string $mailCmd, array $recipCmds): void
    {
        if ($this->capabilities->supportsPipelining()) {
            $this->connection->write(
                array_merge([$mailCmd], array_values($recipCmds)),
            );

            $this->connection->readResponse(250);

            $failedRecipients = [];
            foreach ($recipCmds as $addr => $cmd) {
                try {
                    $this->connection->readResponse([250, 251]);
                } catch (SmtpException $e) {
                    $failedRecipients[$addr] = $e;
                }
            }

            if ($failedRecipients !== []) {
                $this->reset();
                $ex = new RecipientsException(
                    'Recipient(s) rejected',
                    recipients: array_keys($failedRecipients),
                );
                throw $ex;
            }
        } else {
            $this->connection->write($mailCmd);
            $this->connection->readResponse(250);

            foreach ($recipCmds as $addr => $cmd) {
                $this->connection->write($cmd);
                try {
                    $this->connection->readResponse([250, 251]);
                } catch (SmtpException $e) {
                    $this->reset();
                    throw new RecipientsException(
                        'Recipient rejected: ' . $addr,
                        smtpCode: $e->smtpCode,
                        enhancedCode: $e->enhancedCode,
                        recipients: [$addr],
                        previous: $e,
                    );
                }
            }
        }
    }

    /**
     * @param resource $stream
     */
    private function sendBdat(mixed $stream, int $totalSize, int $chunkSize): void
    {
        $remaining = $totalSize;
        while ($remaining > 0) {
            $c = min($chunkSize, $remaining);
            $remaining -= $c;

            $this->connection->write('BDAT ' . $c . ($remaining === 0 ? ' LAST' : ''));
            $this->connection->writeStream($stream, $c);

            if ($remaining > 0) {
                $this->connection->readResponse(250);
            }
        }
    }

    /**
     * @param resource $stream
     */
    private function sendData(mixed $stream): void
    {
        $this->connection->write('DATA');
        $this->connection->readResponse(354);

        $this->connection->writeStream($stream);
        $this->connection->write('.');
    }

    /**
     * @param string[] $recipients
     */
    private function readDataResponse(array $recipients): SendResult
    {
        return match ($this->protocol) {
            Protocol::Smtp => $this->readSmtpDataResponse($recipients),
            Protocol::Lmtp => $this->readLmtpDataResponse($recipients),
        };
    }

    /**
     * @param string[] $recipients
     */
    private function readSmtpDataResponse(array $recipients): SendResult
    {
        $this->connection->readResponse(250);

        return new SendResult(array_fill_keys($recipients, true));
    }

    /**
     * @param string[] $recipients
     */
    private function readLmtpDataResponse(array $recipients): SendResult
    {
        $results = [];
        $anySuccess = false;

        foreach ($recipients as $addr) {
            try {
                $this->connection->readResponse(250);
                $results[$addr] = true;
                $anySuccess = true;
            } catch (SmtpException $e) {
                $results[$addr] = $e;
            }
        }

        if (!$anySuccess) {
            throw new RecipientsException(
                'Delivery to all recipients failed',
                smtpCode: 550,
                recipients: array_keys($results),
            );
        }

        return new SendResult($results);
    }
}
