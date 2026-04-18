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
use Horde\EventDispatcher\NullEventDispatcher;
use Horde\Smtp\Event\AuthenticationFailed;
use Horde\Smtp\Event\AuthenticationSucceeded;
use Horde\Smtp\Event\ConnectionClosed;
use Horde\Smtp\Event\ConnectionEstablished;
use Horde\Smtp\Event\MessageSent;
use Psr\EventDispatcher\EventDispatcherInterface;
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
    private Debug $debug;
    private EventDispatcherInterface $eventDispatcher;
    private SendDataStrategy $sendDataStrategy;
    private ?SecureMode $securityOverride = null;

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
        $this->eventDispatcher = $config->eventDispatcher ?? new NullEventDispatcher();
        $this->connection = $connection;
        $this->resolvedAuthenticators = $authenticators !== []
            ? $authenticators
            : $this->defaultAuthenticators();

        $strategy = $config->strategy;
        $this->sendDataStrategy = $strategy instanceof SendDataStrategy
            ? $strategy
            : new DefaultSendDataStrategy();
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

        $security = $this->securityOverride ?? $this->config->security;
        if ($security === SecureMode::Tls && !$this->isSecureConnection()) {
            $this->negotiateStartTls();
            $this->capabilities = $this->hello();
        }

        $this->checkRequiredExtensions();

        if ($this->config->credentials !== null) {
            $this->authenticate();
        }

        $this->eventDispatcher->dispatch(new ConnectionEstablished(
            'Connected to ' . $this->config->host,
            [
                'host' => $this->config->host,
                'port' => $this->config->port,
                'secure' => $this->isSecureConnection(),
            ],
        ));

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

        $eai = $this->containsNonAscii($from, $recipients);
        if ($eai && !$this->capabilities->supportsInternationalized()) {
            throw new SmtpException(
                'Server does not support internationalized email addresses (SMTPUTF8)',
            );
        }

        $stream = $this->prepareDataStream($data);
        $bodyEncoding = $this->detectBodyEncoding($stream);
        $size = (int) ftell($stream);
        rewind($stream);

        $mailCmd = $this->buildMailFrom($from, $size, $bodyEncoding, $eai);
        $recipCmds = [];
        foreach ($recipients as $addr) {
            $recipCmds[$addr] = 'RCPT TO:<' . $addr . '>';
        }

        $envelope = new SendEnvelope($mailCmd, $recipCmds, $bodyEncoding);

        try {
            $result = $this->sendDataStrategy->send(
                $this,
                $envelope,
                $stream,
                $size,
                $recipients,
            );

            $this->eventDispatcher->dispatch(new MessageSent(
                'Message sent',
                [
                    'from' => $from,
                    'recipients' => $recipients,
                    'size' => $size,
                ],
            ));

            return $result;
        } finally {
            fclose($stream);
        }
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

    /**
     * Request the server to start processing its mail queue (RFC 1985 ETRN).
     *
     * No-op if the server does not advertise ETRN.
     *
     * @param ?string $host  Queue node name. Defaults to the configured localhost.
     */
    public function processQueue(?string $host = null): void
    {
        $this->connect();

        if (!$this->capabilities->supports('ETRN')) {
            return;
        }

        $host ??= $this->config->localhost ?? gethostname() ?: 'localhost';

        $this->connection->write('ETRN ' . $host);
        $this->connection->readResponse([250, 251, 252, 253]);
    }

    public function close(): void
    {
        if ($this->connection === null) {
            return;
        }

        $wasConnected = $this->capabilities !== null;

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

        if ($wasConnected) {
            $this->eventDispatcher->dispatch(new ConnectionClosed(
                'Disconnected from ' . $this->config->host,
                [
                    'host' => $this->config->host,
                    'port' => $this->config->port,
                ],
            ));
        }
    }

    /**
     * Switch security mode to TLS for the next connection.
     *
     * Used by strategies that need to reconnect with STARTTLS.
     * Call close() before this, then connect() after.
     */
    public function upgradeToTls(): void
    {
        $this->securityOverride = SecureMode::Tls;
    }

    /**
     * Send the MAIL FROM and RCPT TO commands.
     */
    public function sendEnvelope(SendEnvelope $envelope): void
    {
        if ($this->capabilities->supportsPipelining()) {
            $this->connection->write(
                array_merge([$envelope->mailCmd], array_values($envelope->recipientCmds)),
            );

            $this->connection->readResponse(250);

            $failedRecipients = [];
            foreach ($envelope->recipientCmds as $addr => $cmd) {
                try {
                    $this->connection->readResponse([250, 251]);
                } catch (SmtpException $e) {
                    $failedRecipients[$addr] = $e;
                }
            }

            if ($failedRecipients !== []) {
                $this->reset();
                throw new RecipientsException(
                    'Recipient(s) rejected',
                    recipients: array_keys($failedRecipients),
                );
            }
        } else {
            $this->connection->write($envelope->mailCmd);
            $this->connection->readResponse(250);

            foreach ($envelope->recipientCmds as $addr => $cmd) {
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
     * Transmit message data via DATA or BDAT as appropriate.
     *
     * @param resource $stream  Prepared message stream.
     * @param int $size  Total byte size.
     * @param ?string $bodyEncoding  Detected encoding (8bit, binary, null).
     */
    public function transmitData(mixed $stream, int $size, ?string $bodyEncoding): void
    {
        $chunking = $this->capabilities->supportsChunking();
        $chunkSize = $this->config->chunkSize;
        $chunkForce = $bodyEncoding === 'binary';

        if ($chunking && $chunkSize > 0 && ($chunkForce || $size > $chunkSize)) {
            $this->sendBdat($stream, $size, $chunkSize);
        } else {
            $this->sendData($stream);
        }
    }

    /**
     * Read the final response(s) after data transmission.
     *
     * @param string[] $recipients
     */
    public function readDataResponse(array $recipients): SendResult
    {
        return match ($this->protocol) {
            Protocol::Smtp => $this->readSmtpDataResponse($recipients),
            Protocol::Lmtp => $this->readLmtpDataResponse($recipients),
        };
    }

    // -- private helpers --

    private function ensureConnection(): void
    {
        if ($this->connection !== null) {
            return;
        }

        $secure = $this->securityOverride ?? $this->config->security;
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
        $username = $credentials->username();

        if ($serverMethods === []) {
            throw new AuthenticationException('Server does not advertise AUTH');
        }

        $mechanismsTried = [];
        $lastException = null;
        foreach ($this->resolvedAuthenticators as $auth) {
            if (!$auth->supports($credentials) || !in_array($auth->mechanism(), $serverMethods, true)) {
                continue;
            }

            $mechanismsTried[] = $auth->mechanism();

            try {
                $auth->authenticate($this->connection, $credentials, $this->debug);

                $this->eventDispatcher->dispatch(new AuthenticationSucceeded(
                    'Authenticated as ' . $username,
                    ['mechanism' => $auth->mechanism(), 'username' => $username],
                ));

                return;
            } catch (SmtpException $e) {
                $lastException = $e;
            }
        }

        $this->eventDispatcher->dispatch(new AuthenticationFailed(
            'Authentication failed for ' . $username,
            ['mechanisms_tried' => $mechanismsTried, 'username' => $username],
        ));

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

    private function buildMailFrom(string $from, int $size, ?string $bodyEncoding, bool $eai = false): string
    {
        $cmd = 'MAIL FROM:<' . $from . '>';

        if ($this->capabilities->supports('SIZE')) {
            $cmd .= ' SIZE=' . $size;
        }

        if ($eai) {
            $cmd .= ' SMTPUTF8';
        }

        if ($eai || $bodyEncoding === '8bit') {
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

    /**
     * @param string[] $recipients
     */
    private function containsNonAscii(string $from, array $recipients): bool
    {
        if (preg_match('/[^\x00-\x7F]/', $from)) {
            return true;
        }

        foreach ($recipients as $addr) {
            if (preg_match('/[^\x00-\x7F]/', $addr)) {
                return true;
            }
        }

        return false;
    }
}
