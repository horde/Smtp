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
use Stringable;

/**
 * Username + password credentials for SASL mechanisms
 * that require a shared secret (PLAIN, LOGIN, CRAM-*, DIGEST-MD5).
 *
 * The password may be a string, a Stringable, or a callable that
 * returns the password on demand (for dynamic/token-based passwords).
 *
 * @api-unstable Interface may change before 2.0 stable.
 */
final class PasswordCredentials implements Credentials
{
    private string|Stringable $password;
    private ?Closure $passwordCallback;
    private string $username;

    /**
     * @param string $username
     * @param string|Stringable|callable $password  A literal password,
     *     a Stringable that produces one, or a callable(): string.
     */
    public function __construct(
        string $username,
        string|Stringable|callable $password,
    ) {
        $this->username = $username;
        if (is_callable($password) && !($password instanceof Stringable)) {
            $this->password = '';
            $this->passwordCallback = $password(...);
        } else {
            $this->password = $password;
            $this->passwordCallback = null;
        }
    }

    public function username(): string
    {
        return $this->username;
    }

    public function password(): string
    {
        if ($this->passwordCallback !== null) {
            return ($this->passwordCallback)();
        }

        return (string) $this->password;
    }
}
