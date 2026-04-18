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

use InvalidArgumentException;

/**
 * Protocol variant for SMTP (RFC 5321) vs LMTP (RFC 2033).
 *
 * Encodes the behavioral differences between the two protocols
 * so a single client class can handle both without inheritance.
 */
enum Protocol
{
    case Smtp;
    case Lmtp;

    public function greeting(): string
    {
        return match ($this) {
            self::Smtp => 'EHLO',
            self::Lmtp => 'LHLO',
        };
    }

    /**
     * Extensions the protocol mandates.
     *
     * @return string[]
     */
    public function requiredExtensions(): array
    {
        return match ($this) {
            self::Smtp => [],
            self::Lmtp => ['ENHANCEDSTATUSCODES', 'PIPELINING'],
        };
    }

    /**
     * @throws InvalidArgumentException If port is invalid for this protocol.
     */
    public function validatePort(int $port): void
    {
        if ($this === self::Lmtp && $port === 25) {
            throw new InvalidArgumentException(
                'LMTP must not use port 25 (RFC 2033 §5).',
            );
        }
    }
}
