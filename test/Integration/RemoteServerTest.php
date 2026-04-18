<?php

declare(strict_types=1);

namespace Horde\Smtp\Test\Integration;

use Horde_Smtp;
use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class RemoteServerTest extends TestCase
{
    private ?array $config = null;
    private ?Horde_Smtp $smtp = null;

    public function setUp(): void
    {
        $conf = @include __DIR__ . '/conf.php';
        if (!$conf || empty($conf['smtp'])) {
            $this->markTestSkipped('Remote server not configured.');
        }
        $this->config = $conf['smtp'];
    }

    public function tearDown(): void
    {
        $this->smtp = null;
    }

    public function testLoginNoAuth(): void
    {
        unset($this->config['pass'], $this->config['user']);
        $this->createSmtp();
        $this->smtp->login();
    }

    public function testLoginAuth(): void
    {
        if (!isset($this->config['pass']) || !isset($this->config['user'])) {
            $this->markTestSkipped('Authentication not configured.');
        }

        $this->createSmtp();
        $this->smtp->login();
    }

    public function test8bitmime(): void
    {
        $this->createSmtp();
        $this->assertEquals(
            $this->smtp->queryExtension('8BITMIME'),
            $this->smtp->data_8bit,
        );
    }

    public function testBinaryMime(): void
    {
        $this->createSmtp();
        $this->assertEquals(
            $this->smtp->queryExtension('BINARYMIME'),
            $this->smtp->data_binary,
        );
    }

    public function testSize(): void
    {
        $this->createSmtp();
        $this->assertEquals(
            $this->smtp->queryExtension('SIZE'),
            $this->smtp->size,
        );
    }

    public function testNoop(): void
    {
        $this->createSmtp();
        $this->smtp->noop();
    }

    public function testProcessQueue(): void
    {
        $this->createSmtp();
        $this->smtp->processQueue();
    }

    private function createSmtp(): void
    {
        $this->smtp = new Horde_Smtp($this->config);
    }
}
