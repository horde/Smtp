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

use Throwable;

/**
 * Exception with details about which recipients failed.
 */
class RecipientsException extends SmtpException
{
    /** @var string[] */
    public readonly array $recipients;

    /**
     * @param string[] $recipients  Failed recipient addresses.
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        ?int $smtpCode = null,
        ?string $enhancedCode = null,
        array $recipients = [],
    ) {
        parent::__construct($message, $code, $previous, $smtpCode, $enhancedCode);
        $this->recipients = $recipients;
    }
}
