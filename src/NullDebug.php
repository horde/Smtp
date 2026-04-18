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
 * No-op debug implementation. Replaces the Horde_Support_Stub usage.
 */
final class NullDebug implements DebugInterface
{
    public function client(string $msg, bool $eol = true): void {}

    public function server(string $msg): void {}

    public function info(string $msg): void {}

    public function raw(string $msg): void {}
}
