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
 * Parsed SMTP server response.
 */
final readonly class SmtpResponse
{
    /**
     * @param int $code  3-digit SMTP reply code.
     * @param string[] $lines  Response text lines (without code prefix).
     * @param string|null $enhancedCode  RFC 3463 enhanced status code, if present.
     */
    public function __construct(
        public int $code,
        public array $lines = [],
        public ?string $enhancedCode = null,
    ) {}

    public function text(): string
    {
        return implode("\n", $this->lines);
    }
}
