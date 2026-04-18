<?php

declare(strict_types=1);

/**
 * Copyright 2013-2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author Michael Slusarz <slusarz@horde.org>
 * @author Ralf Lang <ralf.lang@ralf-lang.de>
 */

namespace Horde\Smtp;

use Horde\Socket\Client\SecureMode;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Immutable configuration for an SMTP or LMTP connection.
 *
 * Replaces the untyped parameter array previously passed to Horde_Smtp.
 *
 * @api-unstable Interface may change before 2.0 stable.
 */
final readonly class SmtpConfig
{
    public function __construct(
        public string $host = 'localhost',
        public int $port = 587,
        public SecureMode $security = SecureMode::Tls,
        public int $timeout = 30,
        public ?string $localhost = null,
        public ?Credentials $credentials = null,
        public int $chunkSize = 1_048_576,
        public array $context = [],
        public ?Debug $debug = null,
        public ?Strategy $strategy = null,
        public ?EventDispatcherInterface $eventDispatcher = null,
    ) {}
}
