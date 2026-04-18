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

namespace Horde\Smtp;

/**
 * Prepared SMTP envelope: MAIL FROM command and RCPT TO commands.
 *
 * Passed to SendDataStrategy so it can invoke SmtpClient::sendEnvelope()
 * without needing to know how commands are built.
 */
final readonly class SendEnvelope
{
    /**
     * @param string $mailCmd  The MAIL FROM command string.
     * @param array<string, string> $recipientCmds  Address => RCPT TO command.
     * @param ?string $bodyEncoding  Detected encoding (8bit, binary, or null for 7bit).
     */
    public function __construct(
        public string $mailCmd,
        public array $recipientCmds,
        public ?string $bodyEncoding,
    ) {}
}
