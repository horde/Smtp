# Horde\Smtp — Known Gaps and Future Work

## send() Signature — String-Only Addresses

**Status:** Temporary compromise, not intentional design.

The current `SmtpClient::send()` accepts bare strings for `$from` and `$to`:

```php
public function send(string $from, string|array $to, mixed $data): SendResult
```

The legacy `Horde_Smtp::send()` accepted `Horde_Mail_Rfc822_Address` and
`Horde_Mail_Rfc822_List` objects, which provided structured access to EAI
status, IDN encoding and address validation.

**What's missing:**
- No address validation at the SMTP layer
- EAI detection relies on byte-level non-ASCII check rather than structured address parsing
- No IDN fallback for non-SMTPUTF8 servers (caller must provide IDN-encoded addresses)

**Planned resolution:** Accept `Horde\Mail\Address` or equivalent typed
address objects alongside strings. This requires the modernized mail
address library to be available first.

## PSR-14 Event-Based Observability

**Status:** MVP implemented. 5 lifecycle events dispatched.

The `Debug` interface handles protocol-level tracing (C:/S: prefixed output).
PSR-14 EventDispatcher provides higher-level observability, audit and
lateral orchestration. This matches the event language of horde/imap.

**Key distinction:** Debug is for verbose protocol traces. PSR-14 events
are for lifecycle observability.

**Current events:** `ConnectionEstablished`, `ConnectionClosed`,
`AuthenticationSucceeded`, `AuthenticationFailed`, `MessageSent`.

**Future additions (when consumers need them):**
- `SlowCommand` — structured slow-command data (StreamDebug handles this for traces)
- `DiagnosticEvent` tier — verbose events with FilteredEventDispatcher suppression
- `CapabilityNegotiated` — if consumers need EHLO response details as events
