<?php

declare(strict_types=1);

namespace Horde\Smtp\Test\Unit;

use Horde_Smtp_Debug;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Horde_Smtp_Debug::class)]
class DebugTest extends TestCase
{
    private function createDebugWithStream(): array
    {
        $stream = fopen('php://memory', 'r+');
        $debug = new Horde_Smtp_Debug($stream);

        return [$debug, $stream];
    }

    private function getStreamContents($stream): string
    {
        rewind($stream);
        return stream_get_contents($stream);
    }

    public function testClientWritesWithPrefix(): void
    {
        [$debug, $stream] = $this->createDebugWithStream();

        $debug->client('EHLO localhost');
        $contents = $this->getStreamContents($stream);

        $this->assertStringContainsString('C: EHLO localhost', $contents);
    }

    public function testServerWritesWithPrefix(): void
    {
        [$debug, $stream] = $this->createDebugWithStream();

        $debug->server('250 OK');
        $contents = $this->getStreamContents($stream);

        $this->assertStringContainsString('S: 250 OK', $contents);
    }

    public function testInfoWritesWithPrefix(): void
    {
        [$debug, $stream] = $this->createDebugWithStream();

        $debug->info('Connection established');
        $contents = $this->getStreamContents($stream);

        $this->assertStringContainsString('>> Connection established', $contents);
    }

    public function testRawWritesWithoutPrefix(): void
    {
        [$debug, $stream] = $this->createDebugWithStream();

        $debug->raw('raw data');
        $contents = $this->getStreamContents($stream);

        $this->assertSame('raw data', $contents);
    }

    public function testFirstPrefixedWriteIncludesSessionHeader(): void
    {
        [$debug, $stream] = $this->createDebugWithStream();

        $debug->client('EHLO');
        $contents = $this->getStreamContents($stream);

        $this->assertStringContainsString(str_repeat('-', 30), $contents);
        $this->assertStringContainsString('>> ', $contents);
    }

    public function testInactiveSuppressesOutput(): void
    {
        [$debug, $stream] = $this->createDebugWithStream();

        $debug->active = false;
        $debug->client('EHLO');
        $debug->server('250 OK');
        $debug->info('test');
        $debug->raw('test');
        $contents = $this->getStreamContents($stream);

        $this->assertSame('', $contents);
    }

    public function testClientAppendsNewline(): void
    {
        [$debug, $stream] = $this->createDebugWithStream();

        $debug->client('EHLO');
        $contents = $this->getStreamContents($stream);

        $this->assertStringEndsWith("EHLO\n", $contents);
    }

    public function testClientWithoutEol(): void
    {
        [$debug, $stream] = $this->createDebugWithStream();

        $debug->client('partial', false);
        $contents = $this->getStreamContents($stream);

        $this->assertStringEndsWith('C: partial', $contents);
    }
}
