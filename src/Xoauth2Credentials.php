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

use Closure;

/**
 * OAuth2 credentials for the XOAUTH2 SASL mechanism.
 *
 * The token format is identical across SMTP, IMAP, and other
 * SASL-capable protocols (Google XOAUTH2 spec).
 *
 * @api-unstable Interface may change before 2.0 stable.
 */
final class Xoauth2Credentials implements Credentials
{
    private string $username;
    private string $accessTokenValue;
    private ?Closure $accessTokenCallback;

    /**
     * @param string $username
     * @param string|callable $accessToken  Literal token or callable(): string.
     */
    public function __construct(
        string $username,
        string|callable $accessToken,
    ) {
        $this->username = $username;
        if (is_callable($accessToken) && !is_string($accessToken)) {
            $this->accessTokenValue = '';
            $this->accessTokenCallback = $accessToken(...);
        } else {
            $this->accessTokenValue = $accessToken;
            $this->accessTokenCallback = null;
        }
    }

    public function username(): string
    {
        return $this->username;
    }

    public function accessToken(): string
    {
        if ($this->accessTokenCallback !== null) {
            return ($this->accessTokenCallback)();
        }

        return $this->accessTokenValue;
    }

    /**
     * Encode per XOAUTH2 spec: base64("user=" username "\x01auth=Bearer " token "\x01\x01")
     */
    public function encodedToken(): string
    {
        return base64_encode(
            'user=' . $this->username . "\x01"
            . 'auth=Bearer ' . $this->accessToken() . "\x01\x01",
        );
    }
}
