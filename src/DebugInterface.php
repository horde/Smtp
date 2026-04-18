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
 * Debug output for SMTP protocol traces.
 *
 * @api-unstable Interface may change before 2.0 stable.
 *     Do not implement outside of horde packages.
 */
interface DebugInterface
{
    /**
     * Log a client command.
     */
    public function client(string $msg, bool $eol = true): void;

    /**
     * Log a server response.
     */
    public function server(string $msg): void;

    /**
     * Log an informational message.
     */
    public function info(string $msg): void;

    /**
     * Log raw data without prefix.
     */
    public function raw(string $msg): void;
}
