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
 * Send strategy that upgrades to TLS on 530 and retries.
 *
 * Matches the behavior of the legacy Horde_Smtp library, which
 * reconnected with STARTTLS when the server demanded it mid-transaction.
 */
final class TlsSendDataStrategy implements SendDataStrategy
{
    public function send(
        SmtpClient $client,
        SendEnvelope $envelope,
        mixed $stream,
        int $size,
        array $recipients,
    ): SendResult {
        try {
            $client->sendEnvelope($envelope);
            $client->transmitData($stream, $size, $envelope->bodyEncoding);
            return $client->readDataResponse($recipients);
        } catch (SmtpException $e) {
            if ($e->smtpCode !== 530) {
                throw $e;
            }

            $client->close();
            $client->upgradeToTls();
            $client->connect();

            rewind($stream);
            $client->sendEnvelope($envelope);
            $client->transmitData($stream, $size, $envelope->bodyEncoding);
            return $client->readDataResponse($recipients);
        }
    }
}
