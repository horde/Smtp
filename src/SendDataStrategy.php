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
 * Strategy aspect for the send (envelope + DATA/BDAT) phase.
 *
 * SmtpClient always delegates the send sequence to this strategy.
 * The strategy calls the building-block methods on SmtpClient
 * (sendEnvelope, transmitData, readDataResponse) to compose the
 * desired behavior.
 *
 * @see DefaultSendDataStrategy  Straight-through, errors propagate.
 * @see TlsSendDataStrategy      Catches 530 and retries with TLS.
 *
 * @api-unstable Interface may change before 2.0 stable.
 *     Do not implement outside of horde packages.
 */
interface SendDataStrategy extends Strategy
{
    /**
     * Orchestrate the send sequence.
     *
     * @param SmtpClient $client  The client with public send building blocks.
     * @param SendEnvelope $envelope  Prepared envelope (MAIL FROM + RCPT TO commands).
     * @param resource $stream  The prepared message data stream.
     * @param int $size  Total byte size of the stream.
     * @param string[] $recipients  Recipient addresses for response parsing.
     */
    public function send(
        SmtpClient $client,
        SendEnvelope $envelope,
        mixed $stream,
        int $size,
        array $recipients,
    ): SendResult;
}
