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

use Horde_Imap_Client_Auth_DigestMD5;

/**
 * DIGEST-MD5 SASL mechanism (RFC 2831, obsoleted by RFC 6331).
 *
 * Delegates the digest computation to Horde_Imap_Client_Auth_DigestMD5.
 * Only available when horde/imap_client is installed.
 */
final class DigestMd5Authenticator implements Authenticator
{
    public function __construct(
        private readonly string $hostspec,
    ) {}

    public function mechanism(): string
    {
        return 'DIGEST-MD5';
    }

    public function supports(Credentials $credentials): bool
    {
        if (!$credentials instanceof PasswordCredentials) {
            return false;
        }

        return class_exists(Horde_Imap_Client_Auth_DigestMD5::class);
    }

    public function authenticate(
        SmtpConnection $connection,
        Credentials $credentials,
        DebugInterface $debug,
        string $serviceName = 'smtp',
    ): void {
        assert($credentials instanceof PasswordCredentials);

        if (!class_exists(Horde_Imap_Client_Auth_DigestMD5::class)) {
            throw new AuthenticationException('DIGEST-MD5 requires horde/imap_client');
        }

        $username = $credentials->username();

        $connection->write('AUTH DIGEST-MD5');
        $resp = $connection->readResponse(334);

        $challenge = base64_decode($resp->lines[0] ?? '');
        $digestResponse = new Horde_Imap_Client_Auth_DigestMD5(
            $username,
            $credentials->password(),
            $challenge,
            $this->hostspec,
            $serviceName,
        );

        $connection->write(base64_encode((string) $digestResponse));
        $debug->raw(sprintf("[AUTH Command - method: DIGEST-MD5; username: %s]\n", $username));

        $connection->readResponse(334);
        $connection->write('');
        $connection->readResponse(235);
    }
}
