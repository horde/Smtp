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
 * Typed representation of server capabilities advertised via EHLO/LHLO.
 *
 * Constructed from the parsed EHLO response — only exists after
 * a successful greeting, so no lazy-login side effects.
 */
final readonly class ServerCapabilities
{
    /**
     * @param array<string, string|true> $extensions  Extension name → value
     *     (true if the extension has no parameter).
     */
    public function __construct(
        private array $extensions,
    ) {}

    /**
     * Check if a named extension is supported.
     */
    public function supports(string $extension): bool
    {
        return isset($this->extensions[strtoupper($extension)]);
    }

    /**
     * Get the value advertised for an extension.
     *
     * @return string|true|false  The value string, true (no params), or false (unsupported).
     */
    public function extensionValue(string $extension): string|bool
    {
        return $this->extensions[strtoupper($extension)] ?? false;
    }

    /** RFC 6152 — 8BITMIME */
    public function supports8BitMime(): bool
    {
        return $this->supports('8BITMIME');
    }

    /** RFC 3030 — BINARYMIME */
    public function supportsBinaryMime(): bool
    {
        return $this->supports('BINARYMIME');
    }

    /** RFC 6531 — SMTPUTF8 (internationalized email) */
    public function supportsInternationalized(): bool
    {
        return $this->supports('SMTPUTF8');
    }

    /** RFC 1870 — maximum message size in bytes, or null if not advertised. */
    public function maxSize(): ?int
    {
        $val = $this->extensionValue('SIZE');
        if ($val === false || $val === true) {
            return null;
        }

        $size = (int) $val;
        return $size > 0 ? $size : null;
    }

    /** RFC 2920 — PIPELINING */
    public function supportsPipelining(): bool
    {
        return $this->supports('PIPELINING');
    }

    /** RFC 3030 — CHUNKING */
    public function supportsChunking(): bool
    {
        return $this->supports('CHUNKING');
    }

    /** RFC 3207 — STARTTLS */
    public function supportsStartTls(): bool
    {
        return $this->supports('STARTTLS');
    }

    /** RFC 2034 — Enhanced status codes */
    public function supportsEnhancedStatusCodes(): bool
    {
        return $this->supports('ENHANCEDSTATUSCODES');
    }

    /**
     * AUTH mechanisms advertised by the server.
     *
     * @return string[]  Mechanism names (e.g. ['PLAIN', 'LOGIN', 'CRAM-MD5']).
     */
    public function authMethods(): array
    {
        $val = $this->extensionValue('AUTH');
        if ($val === false || $val === true) {
            return [];
        }

        return array_map('trim', explode(' ', $val));
    }
}
