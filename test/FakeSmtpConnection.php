<?php

declare(strict_types=1);

/**
 * Copyright 2013-2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Smtp\Test;

use Horde\Smtp\SmtpConnection;
use Horde\Smtp\SmtpException;
use Horde\Smtp\SmtpResponse;

/**
 * In-memory SmtpConnection for testing authenticators.
 *
 * Feed it a queue of responses; it records all writes.
 */
class FakeSmtpConnection implements SmtpConnection
{
    /** @var string[] */
    public array $written = [];

    /** @var SmtpResponse[] */
    private array $responseQueue = [];

    public function queueResponse(SmtpResponse $response): void
    {
        $this->responseQueue[] = $response;
    }

    public function write(string|array $data): void
    {
        foreach ((array) $data as $line) {
            $this->written[] = $line;
        }
    }

    public function writeStream(mixed $resource, ?int $size = null): void {}

    public function readResponse(int|array $expectedCodes): SmtpResponse
    {
        if ($this->responseQueue === []) {
            throw new SmtpException('No queued response');
        }

        $response = array_shift($this->responseQueue);
        $expected = (array) $expectedCodes;

        if (!in_array($response->code, $expected, true)) {
            throw new SmtpException(
                sprintf('Unexpected response code %d', $response->code),
                smtpCode: $response->code,
                enhancedCode: $response->enhancedCode,
            );
        }

        return $response;
    }
}
