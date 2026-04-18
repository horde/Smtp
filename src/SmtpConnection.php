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
 * Protocol-level connection for SMTP command exchange.
 *
 * Abstracts the socket so authenticators (and tests) don't
 * depend on a live TCP connection.
 *
 * @api-unstable Interface may change before 2.0 stable.
 *     Do not implement outside of horde packages.
 */
interface SmtpConnection
{
    /**
     * Write one or more SMTP commands.
     *
     * @param string|string[] $data  Command line(s) — CRLF is appended automatically.
     */
    public function write(string|array $data): void;

    /**
     * Write raw stream data (for DATA/BDAT payloads).
     *
     * @param resource $resource  Readable stream.
     * @param int|null $size  Maximum bytes to send, or null for all.
     */
    public function writeStream(mixed $resource, ?int $size = null): void;

    /**
     * Read and parse the next server response.
     *
     * @param int|int[] $expectedCodes  Acceptable reply code(s).
     * @return SmtpResponse  On success.
     * @throws SmtpException  If the reply code is not in the expected set.
     */
    public function readResponse(int|array $expectedCodes): SmtpResponse;
}
