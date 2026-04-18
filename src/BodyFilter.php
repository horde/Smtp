<?php

declare(strict_types=1);

/**
 * Copyright 2014-2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author Michael Slusarz <slusarz@horde.org>
 */

namespace Horde\Smtp;

use php_user_filter;

/**
 * Stream filter that detects whether message body is 7-bit, 8-bit (RFC 6152),
 * or binary (RFC 3030).
 *
 * Attach as a WRITE filter. After closing the stream, inspect
 * $params->body for the result: false (7-bit), '8bit', or 'binary'.
 *
 * Register with stream_filter_register() before use:
 *     stream_filter_register('horde.smtp.body', BodyFilter::class);
 */
class BodyFilter extends php_user_filter
{
    private int $lineLength = 0;

    public function onCreate(): bool
    {
        $this->params->body = false;

        return true;
    }

    public function filter($in, $out, &$consumed, bool $closing): int
    {
        $skip = ($this->params->body !== false);

        while ($bucket = stream_bucket_make_writeable($in)) {
            if (!$skip) {
                $len = $bucket->datalen;
                $str = $bucket->data;

                for ($i = 0; $i < $len; ++$i) {
                    $chr = ord($str[$i]);

                    switch ($chr) {
                        case 0:
                            $this->params->body = 'binary';
                            $skip = true;
                            break 2;

                        case 10:
                        case 13:
                            $this->lineLength = 0;
                            break;

                        default:
                            if (++$this->lineLength > 998) {
                                $this->params->body = 'binary';
                                $skip = true;
                                break 2;
                            } elseif ($chr > 127) {
                                $this->params->body = '8bit';
                            }
                            break;
                    }
                }
            }

            $consumed += $bucket->datalen;
            stream_bucket_append($out, $bucket);
        }

        return PSFS_PASS_ON;
    }
}
