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

use RuntimeException;
use Throwable;

/**
 * Base exception for SMTP protocol errors.
 *
 * Carries the SMTP reply code, optional enhanced status code (RFC 3463),
 * and whether the error is permanent (5xx) or transient (4xx).
 */
class SmtpException extends RuntimeException
{
    public readonly ?int $smtpCode;
    public readonly ?string $enhancedCode;
    public readonly bool $permanent;
    public readonly string $rawMessage;

    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        ?int $smtpCode = null,
        ?string $enhancedCode = null,
    ) {
        parent::__construct($message, $code, $previous);

        $this->rawMessage = $message;
        $this->smtpCode = $smtpCode;
        $this->enhancedCode = $enhancedCode;

        if ($enhancedCode !== null) {
            $this->permanent = str_starts_with($enhancedCode, '5');
        } elseif ($smtpCode !== null) {
            $this->permanent = str_starts_with((string) $smtpCode, '5');
        } else {
            $this->permanent = false;
        }
    }
}
