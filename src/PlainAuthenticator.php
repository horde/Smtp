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

/**
 * PLAIN SASL mechanism (RFC 4616).
 *
 * Sends credentials in a single base64-encoded command.
 * Requires a secure (TLS) connection in practice.
 */
final class PlainAuthenticator implements Authenticator
{
    public function mechanism(): string
    {
        return 'PLAIN';
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
        $token = base64_encode(
            $username . "\0" . $username . "\0" . $credentials->password(),
        );

        $connection->write('AUTH PLAIN ' . $token);
        $debug->raw(sprintf("[AUTH Command - method: PLAIN; username: %s]\n", $username));
        $connection->readResponse(235);
    }
}
