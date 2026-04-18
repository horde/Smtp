# Upgrading Horde_Smtp

Contact: dev@lists.horde.org

This lists the API changes between releases of the package.

## Upgrading to 2.0 (src/ PSR-4 Rewrite)

The `src/` namespace `Horde\Smtp` is a complete rewrite of the PSR-0
`lib/` classes. Both currently coexist. The old `lib/` model remains functional and unchanged.
Integrators are encouraged to transition to the new model at their own pace.
Note that this means putting lib/ deliberately on conservative life support.
Interfaces have been covered with returntypewillchange attributes to silence PHP 8 deprecations.
This will break with PHP 9.

### Architecture

`Horde_Smtp` (single god-class) has been decomposed into:

- `SmtpClient` — public facade (connect, send, close, noop, reset, processQueue)
- `SmtpConfig` — immutable configuration VO (replaces untyped array)
- `TcpSmtpConnection` — connection over `Horde\Socket\Client`
- `ServerCapabilities` — typed EHLO response
- `Protocol` enum — SMTP vs LMTP (replaces `Horde_Smtp_Lmtp extends Horde_Smtp`)
- `SendResult` — typed return value from `send()`

### Authentication

Extracted into pluggable `Authenticator` implementations:

- `PlainAuthenticator`, `LoginAuthenticator`
- `CramAuthenticator` (supports CRAM-MD5, CRAM-SHA1, CRAM-SHA256)
- `DigestMd5Authenticator`
- `Xoauth2Authenticator`

Credentials are typed: `PasswordCredentials`, `Xoauth2Credentials`.

### Debug / Logging

`Debug` interface replaces the old `Horde_Smtp_Debug` class.
Implementations: `NullDebug`, `StreamDebug`.
Interface naming convention: bare name for interface, descriptive prefix
for implementations.

### Strategy Pattern

Pluggable `Strategy` marker interface with aspect sub-interfaces.
`SendDataStrategy` orchestrates the envelope + DATA/BDAT phase:

- `DefaultSendDataStrategy` — straight-through, errors propagate
- `TlsSendDataStrategy` — catches 530, reconnects with TLS, retries

Configured via `SmtpConfig::$strategy`. When no strategy is set,
`DefaultSendDataStrategy` is used automatically.

### send() Signature

Currently accepts plain strings only (`string $from`, `string|array $to`).
This is a temporary compromise — typed address object support is planned.
See `doc/TODO.md`.

### EAI / SMTPUTF8

Internationalized email addresses (RFC 6531) are detected automatically.
Non-ASCII addresses trigger `SMTPUTF8` MAIL parameter and require
`8BITMIME`. Throws if server does not advertise `SMTPUTF8`.

### ETRN / processQueue

`SmtpClient::processQueue(?string $host)` sends the RFC 1985 ETRN
command. No-op if the server does not advertise ETRN.

### Stream Filters

`DataFilter` and `BodyFilter` replace `Horde_Smtp_Filter_Data` and
`Horde_Smtp_Filter_Body` with identical logic and PHP 8.2+ compatibility.

### PSR-14 Lifecycle Events

Optional `EventDispatcherInterface` in `SmtpConfig` for observability,
audit and orchestration hooks. Five lifecycle events:

- `ConnectionEstablished` — after full connect() (greeting + EHLO + TLS + auth)
- `ConnectionClosed` — after QUIT / teardown
- `AuthenticationSucceeded` — after successful SASL auth
- `AuthenticationFailed` — when all auth methods exhausted
- `MessageSent` — after successful send()

Events carry structured context (host, port, mechanism, from, recipients, etc.).
Uses `Horde\EventDispatcher\NullEventDispatcher` when no dispatcher configured.
Debug interface remains separate for protocol-level tracing.

### Exceptions

- `SmtpException` — base, carries `smtpCode` and `enhancedCode`
- `ConnectionException` — transport-level failures
- `AuthenticationException` — auth failures
- `RecipientsException` — rejected recipients with address list

## Upgrading to 1.9.0

- **Horde_Smtp — Constructor**: Added the `context` parameter.

## Upgrading to 1.8.0

- **Horde_Smtp — send()**: Failed recipients now cause a
  `Horde_Smtp_Exception_Recipients` exception to be thrown, which
  contains the list of recipients that failed.
- **Horde_Smtp_Exception_Recipients**: This exception class has been added.

## Upgrading to 1.7.0

- **Horde_Smtp**: Added the `$data_binary` property.
  - **Constructor**: Added the `chunk_size` parameter.
  - **send()**: Deprecated the `8bit` option. The correct encoding mode
    to use is now automatically determined.
- **Horde_Smtp_Exception**: Added the `$category` property. Added the
  `CATEGORY_*` constants to provide more general error categories if
  an error code is not handled specifically.
- **Horde_Smtp_Filter_Body**: This class was added.

## Upgrading to 1.6.0

- **Horde_Smtp**: Added the `$data_intl` property.
  - **send()**: Added the `intl` option.

## Upgrading to 1.5.0

- **Horde_Smtp — send()**: Now returns an array of information on
  successful send to at least one recipient.
- **Horde_Smtp_Exception**: Added the `LOGIN_MISSINGEXTENSION` error code.
- **Horde_Smtp_Lmtp**: Added the LMTP driver (RFC 2033).

## Upgrading to 1.4.0

- **Horde_Smtp_Exception**: Added the `$raw_msg` parameter.

## Upgrading to 1.3.0

- **Horde_Smtp — Constructor**: Added `tlsv1` option for the `secure`
  configuration parameter.
- **Horde_Smtp_Password_Xoauth2**: Added class to abstract production of
  the necessary token for XOAUTH2 SASL authentication.

## Upgrading to 1.2.0

- **Horde_Smtp — Constructor**: Added the `true` option to the `secure`
  parameter (and has become the default option).

## Upgrading to 1.1.0

- **Horde_Smtp — Constructor**: Added the `xoauth2_token` parameter.
  Added the ability to pass in a `Horde_Smtp_Password` object to the
  `password` parameter. The `password_encrypt` parameter has been deprecated.
