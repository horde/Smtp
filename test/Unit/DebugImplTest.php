<?php

declare(strict_types=1);

namespace Horde\Smtp\Test\Unit;

use Horde\Smtp\DebugInterface;
use Horde\Smtp\NullDebug;
use Horde\Smtp\StreamDebug;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NullDebug::class)]
#[CoversClass(StreamDebug::class)]
class DebugImplTest extends TestCase
{
    public function testNullDebugImplementsInterface(): void
    {
        $debug = new NullDebug();

        $this->assertInstanceOf(DebugInterface::class, $debug);
    }

    public function testNullDebugNoOps(): void
    {
        $debug = new NullDebug();

        $debug->client('test');
        $debug->server('test');
        $debug->info('test');
        $debug->raw('test');

        $this->assertTrue(true);
    }

    public function testStreamDebugImplementsInterface(): void
    {
        $stream = fopen('php://memory', 'r+');
        $debug = new StreamDebug($stream);

        $this->assertInstanceOf(DebugInterface::class, $debug);

        fclose($stream);
    }

    public function testStreamDebugClientWritesPrefix(): void
    {
        $stream = fopen('php://memory', 'r+');
        $debug = new StreamDebug($stream);

        $debug->client('EHLO localhost');
        rewind($stream);
        $contents = stream_get_contents($stream);

        $this->assertStringContainsString('C: EHLO localhost', $contents);

        fclose($stream);
    }

    public function testStreamDebugServerWritesPrefix(): void
    {
        $stream = fopen('php://memory', 'r+');
        $debug = new StreamDebug($stream);

        $debug->server('250 OK');
        rewind($stream);
        $contents = stream_get_contents($stream);

        $this->assertStringContainsString('S: 250 OK', $contents);

        fclose($stream);
    }

    public function testStreamDebugInfoWritesPrefix(): void
    {
        $stream = fopen('php://memory', 'r+');
        $debug = new StreamDebug($stream);

        $debug->info('Connected');
        rewind($stream);
        $contents = stream_get_contents($stream);

        $this->assertStringContainsString('>> Connected', $contents);

        fclose($stream);
    }

    public function testStreamDebugRawWritesWithoutPrefix(): void
    {
        $stream = fopen('php://memory', 'r+');
        $debug = new StreamDebug($stream);

        $debug->raw('raw data');
        rewind($stream);
        $contents = stream_get_contents($stream);

        $this->assertSame('raw data', $contents);

        fclose($stream);
    }

    public function testStreamDebugFirstPrefixedWriteIncludesHeader(): void
    {
        $stream = fopen('php://memory', 'r+');
        $debug = new StreamDebug($stream);

        $debug->client('EHLO');
        rewind($stream);
        $contents = stream_get_contents($stream);

        $this->assertStringContainsString(str_repeat('-', 30), $contents);

        fclose($stream);
    }
}
