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

use InvalidArgumentException;

/**
 * CRAM-based SASL mechanisms (CRAM-MD5, CRAM-SHA1, CRAM-SHA256).
 *
 * RFC 2195 defines CRAM-MD5. CRAM-SHA1 and CRAM-SHA256 follow the
 * same protocol flow with different hash algorithms (supported by
 * Courier SASL library and others).
 *
 * The constructor takes the specific variant to use.
 */
final class CramAuthenticator implements Authenticator
{
    private string $mechanism;
    private string $hashAlgo;

    /**
     * @param string $variant  One of 'CRAM-MD5', 'CRAM-SHA1', 'CRAM-SHA256'.
     */
    public function __construct(string $variant = 'CRAM-MD5')
    {
        $allowed = ['CRAM-MD5', 'CRAM-SHA1', 'CRAM-SHA256'];
        if (!in_array($variant, $allowed, true)) {
            throw new InvalidArgumentException(
                sprintf('Unknown CRAM variant "%s", expected one of: %s', $variant, implode(', ', $allowed)),
            );
        }

        $this->mechanism = $variant;
        $this->hashAlgo = strtolower(substr($variant, 5));
    }

    public function mechanism(): string
    {
        return $this->mechanism;
    }

    public function supports(Credentials $credentials): bool
    {
        return $credentials instanceof PasswordCredentials;
    }

    public function authenticate(
        SmtpConnection $connection,
        Credentials $credentials,
        DebugInterface $debug,
        string $serviceName = 'smtp',
    ): void {
        assert($credentials instanceof PasswordCredentials);

        $username = $credentials->username();

        $connection->write('AUTH ' . $this->mechanism);
        $resp = $connection->readResponse(334);

        $challenge = base64_decode($resp->lines[0] ?? '');
        $digest = hash_hmac($this->hashAlgo, $challenge, $credentials->password());

        $connection->write(base64_encode($username . ' ' . $digest));
        $debug->raw(sprintf("[AUTH Command - method: %s; username: %s]\n", $this->mechanism, $username));
        $connection->readResponse(235);
    }
}
