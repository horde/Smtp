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
 * XOAUTH2 SASL mechanism (Google spec).
 *
 * On auth failure the server sends a 334 continuation with error
 * details; the client must send an empty line to cancel before the
 * server returns the final error code.
 */
final class Xoauth2Authenticator implements Authenticator
{
    public function mechanism(): string
    {
        return 'XOAUTH2';
    }

    public function supports(Credentials $credentials): bool
    {
        return $credentials instanceof Xoauth2Credentials;
    }

    public function authenticate(
        SmtpConnection $connection,
        Credentials $credentials,
        DebugInterface $debug,
        string $serviceName = 'smtp',
    ): void {
        assert($credentials instanceof Xoauth2Credentials);

        $username = $credentials->username();

        $connection->write('AUTH XOAUTH2 ' . $credentials->encodedToken());
        $debug->raw(sprintf("[AUTH Command - method: XOAUTH2; username: %s]\n", $username));

        try {
            $connection->readResponse(235);
        } catch (SmtpException $e) {
            if ($e->smtpCode === 334) {
                $connection->write('');
            }
            throw new AuthenticationException(
                'XOAUTH2 authentication failed',
                smtpCode: $e->smtpCode,
                enhancedCode: $e->enhancedCode,
                previous: $e,
            );
        }
    }
}
