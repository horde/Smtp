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

use php_user_filter;

/**
 * Stream filter that escapes output for the SMTP DATA command (RFC 5321 §4.1.1.4).
 *
 * - All line endings converted to CRLF.
 * - A period at the start of a line is doubled.
 *
 * Register with stream_filter_register() before use:
 *     stream_filter_register('horde.smtp.data', DataFilter::class);
 */
class DataFilter extends php_user_filter
{
    private ?string $lastChar = null;

    public function filter($in, $out, &$consumed, bool $closing): int
    {
        while ($bucket = stream_bucket_make_writeable($in)) {
            $consumed += $bucket->datalen;

            if ($bucket->data[$bucket->datalen - 1] === "\r") {
                $bucket->data = substr($bucket->data, 0, -1);
            }

            if (($bucket->data[0] === '.')
                && ($this->lastChar === null || $this->lastChar === "\n")) {
                $bucket->data = '.' . $bucket->data;
            }

            $bucket->data = str_replace(
                ["\r\n", "\r", "\n", "\n."],
                ["\n", "\n", "\r\n", "\n.."],
                $bucket->data,
            );

            $this->lastChar = substr($bucket->data, -1);

            stream_bucket_append($out, $bucket);
        }

        return PSFS_PASS_ON;
    }
}
