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
 * LOGIN SASL mechanism.
 *
 * Two-step challenge-response: server requests username, then password.
 * Non-standard but widely supported by legacy servers.
 */
final class LoginAuthenticator implements Authenticator
{
    public function mechanism(): string
    {
        return 'LOGIN';
    }

    public function supports(Credentials $credentials): bool
    {
        return $credentials instanceof PasswordCredentials;
    }

    public function authenticate(
        SmtpConnection $connection,
        Credentials $credentials,
        Debug $debug,
        string $serviceName = 'smtp',
    ): void {
        assert($credentials instanceof PasswordCredentials);

        $username = $credentials->username();

        $connection->write('AUTH LOGIN');
        $connection->readResponse(334);

        $connection->write(base64_encode($username));
        $connection->readResponse(334);

        $connection->write(base64_encode($credentials->password()));
        $debug->raw(sprintf("[AUTH Command - method: LOGIN; username: %s]\n", $username));
        $connection->readResponse(235);
    }
}
