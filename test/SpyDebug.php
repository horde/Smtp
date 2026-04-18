<?php

declare(strict_types=1);

/**
 * Copyright 2013-2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Smtp\Test;

use Horde\Smtp\Debug;

/**
 * Captures debug output for test assertions.
 */
class SpyDebug implements Debug
{
    /** @var string[] */
    public array $messages = [];

    public function client(string $msg, bool $eol = true): void
    {
        $this->messages[] = 'C: ' . $msg;
    }

    public function server(string $msg): void
    {
        $this->messages[] = 'S: ' . $msg;
    }

    public function info(string $msg): void
    {
        $this->messages[] = '>> ' . $msg;
    }

    public function raw(string $msg): void
    {
        $this->messages[] = $msg;
    }
}
