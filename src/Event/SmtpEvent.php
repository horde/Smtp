<?php

declare(strict_types=1);

/**
 * Copyright 2013-2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author Michael Slusarz <slusarz@horde.org>
 * @author Ralf Lang <ralf.lang@ralf-lang.de>
 */

namespace Horde\Smtp\Event;

/**
 * Base event for all SMTP/LMTP client lifecycle events.
 *
 * Listeners can type-hint this class to catch every event, or
 * specific subclasses to handle individual signals.
 */
abstract class SmtpEvent
{
    public function __construct(
        private readonly string $message = '',
        private readonly array $context = [],
    ) {}

    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }
}
