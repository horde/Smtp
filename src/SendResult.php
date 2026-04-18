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
 * Result of a send operation.
 *
 * For SMTP, all recipients succeed or the entire DATA fails.
 * For LMTP, each recipient has an individual success/failure status.
 */
final readonly class SendResult
{
    /**
     * @param array<string, true|SmtpException> $recipients
     *     Address → true on success, SmtpException on failure.
     */
    public function __construct(
        public array $recipients,
    ) {}

    public function allSuccessful(): bool
    {
        foreach ($this->recipients as $result) {
            if ($result !== true) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return string[]  Addresses that were accepted.
     */
    public function successful(): array
    {
        $out = [];
        foreach ($this->recipients as $address => $result) {
            if ($result === true) {
                $out[] = $address;
            }
        }

        return $out;
    }

    /**
     * @return array<string, SmtpException>  Addresses that failed, with their errors.
     */
    public function failed(): array
    {
        $out = [];
        foreach ($this->recipients as $address => $result) {
            if ($result instanceof SmtpException) {
                $out[$address] = $result;
            }
        }

        return $out;
    }
}
