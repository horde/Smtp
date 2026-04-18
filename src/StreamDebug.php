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
 * Debug output to a PHP stream (file path or resource).
 *
 * Writes prefixed protocol traces to the stream.
 * Logs slow commands (over SLOW_CMD seconds) automatically.
 */
final class StreamDebug implements Debug
{
    private const SLOW_CMD = 5;

    /** @var resource|null */
    private $stream;

    private ?float $lastTime = null;

    /**
     * @param string|resource $target  File path or open stream resource.
     */
    public function __construct(mixed $target)
    {
        $this->stream = is_resource($target)
            ? $target
            : @fopen($target, 'a');
    }

    public function __destruct()
    {
        if ($this->stream !== null && is_resource($this->stream)) {
            fflush($this->stream);
            fclose($this->stream);
        }
    }

    public function client(string $msg, bool $eol = true): void
    {
        $this->write($msg . ($eol ? "\n" : ''), 'C: ');
    }

    public function server(string $msg): void
    {
        $this->write($msg . "\n", 'S: ');
    }

    public function info(string $msg): void
    {
        $this->write($msg . "\n", '>> ');
    }

    public function raw(string $msg): void
    {
        if ($this->stream === null || !is_resource($this->stream)) {
            return;
        }
        fwrite($this->stream, $msg);
    }

    private function write(string $msg, string $prefix): void
    {
        if ($this->stream === null || !is_resource($this->stream)) {
            return;
        }

        $now = microtime(true);

        if ($this->lastTime === null) {
            fwrite(
                $this->stream,
                str_repeat('-', 30) . "\n" . '>> ' . date('r') . "\n",
            );
        } elseif (($diff = $now - $this->lastTime) > self::SLOW_CMD) {
            fwrite(
                $this->stream,
                '>> Slow Command: ' . round($diff, 3) . " seconds\n",
            );
        }

        $this->lastTime = $now;
        fwrite($this->stream, $prefix . $msg);
    }
}
