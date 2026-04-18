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
 * Credentials for authenticating to a mail server.
 *
 * Designed to be protocol-agnostic so it can lift to a shared
 * horde/sasl or horde/auth package in the future. Implementations
 * ship in horde/Smtp for now.
 *
 * @api-unstable Interface may change before 2.0 stable.
 *     Do not implement outside of horde packages.
 */
interface Credentials
{
    public function username(): string;
}
