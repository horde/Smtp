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
 * A SASL authentication mechanism for SMTP/LMTP.
 *
 * Each implementation handles one mechanism (PLAIN, LOGIN, CRAM-MD5, etc.).
 * The client tries mechanisms in priority order until one succeeds.
 *
 * The `$serviceName` parameter allows the same mechanism implementation
 * to work across protocols (smtp, imap, sieve) — relevant for
 * DIGEST-MD5 and SCRAM where the service name is part of the challenge.
 *
 * @api-unstable Interface may change before 2.0 stable.
 *     Do not implement outside of horde packages.
 */
interface Authenticator
{
    /**
     * SASL mechanism name as sent in the AUTH command.
     */
    public function mechanism(): string;

    /**
     * Whether this authenticator can handle the given credentials type.
     */
    public function supports(Credentials $credentials): bool;

    /**
     * Execute the authentication exchange.
     *
     * @param SmtpConnection $connection  Protocol connection for challenge-response.
     * @param Credentials $credentials  User credentials.
     * @param Debug $debug  Debug output (authenticators suppress credential logging).
     * @param string $serviceName  SASL service name ('smtp', 'imap', 'sieve').
     * @throws AuthenticationException  On failure.
     */
    public function authenticate(
        SmtpConnection $connection,
        Credentials $credentials,
        Debug $debug,
        string $serviceName = 'smtp',
    ): void;
}
