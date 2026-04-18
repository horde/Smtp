<?php

declare(strict_types=1);

namespace Horde\Smtp\Test\Unit;

use Horde\Smtp\Credentials;
use Horde\Smtp\DebugInterface;
use Horde\Smtp\NullDebug;
use Horde\Smtp\SmtpConfig;
use Horde\Socket\Client\SecureMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SmtpConfig::class)]
class SmtpConfigTest extends TestCase
{
    public function testDefaults(): void
    {
        $config = new SmtpConfig();

        $this->assertSame('localhost', $config->host);
        $this->assertSame(587, $config->port);
        $this->assertSame(SecureMode::Tls, $config->security);
        $this->assertSame(30, $config->timeout);
        $this->assertNull($config->localhost);
        $this->assertNull($config->credentials);
        $this->assertSame(1_048_576, $config->chunkSize);
        $this->assertSame([], $config->context);
        $this->assertNull($config->debug);
    }

    public function testCustomValues(): void
    {
        $debug = new NullDebug();
        $config = new SmtpConfig(
            host: 'mail.example.com',
            port: 465,
            security: SecureMode::Ssl,
            timeout: 60,
            localhost: 'myhost.local',
            chunkSize: 512,
            context: ['ssl' => ['verify_peer' => false]],
            debug: $debug,
        );

        $this->assertSame('mail.example.com', $config->host);
        $this->assertSame(465, $config->port);
        $this->assertSame(SecureMode::Ssl, $config->security);
        $this->assertSame(60, $config->timeout);
        $this->assertSame('myhost.local', $config->localhost);
        $this->assertSame(512, $config->chunkSize);
        $this->assertSame(['ssl' => ['verify_peer' => false]], $config->context);
        $this->assertSame($debug, $config->debug);
    }

    public function testSecurityNone(): void
    {
        $config = new SmtpConfig(security: SecureMode::None);

        $this->assertSame(SecureMode::None, $config->security);
    }
}
