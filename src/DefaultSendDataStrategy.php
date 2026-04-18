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
 * Default send strategy: straight-through, errors propagate.
 */
final class DefaultSendDataStrategy implements SendDataStrategy
{
    public function send(
        SmtpClient $client,
        SendEnvelope $envelope,
        mixed $stream,
        int $size,
        array $recipients,
    ): SendResult {
        $client->sendEnvelope($envelope);
        $client->transmitData($stream, $size, $envelope->bodyEncoding);
        return $client->readDataResponse($recipients);
    }
}
