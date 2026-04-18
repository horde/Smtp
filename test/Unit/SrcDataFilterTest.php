<?php

declare(strict_types=1);

namespace Horde\Smtp\Test\Unit;

use Horde\Smtp\DataFilter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(DataFilter::class)]
class SrcDataFilterTest extends TestCase
{
    private static bool $registered = false;

    public function setUp(): void
    {
        if (!self::$registered) {
            stream_filter_register('horde.smtp.data', DataFilter::class);
            self::$registered = true;
        }
    }

    #[DataProvider('escapeProvider')]
    public function testEscape(string $in, string $expected): void
    {
        $stream = fopen('php://temp', 'r+');
        stream_filter_append($stream, 'horde.smtp.data', STREAM_FILTER_READ);

        fwrite($stream, $in);
        rewind($stream);

        $this->assertEquals($expected, stream_get_contents($stream));

        fclose($stream);
    }

    public static function escapeProvider(): array
    {
        return [
            'LF to CRLF' => [
                "Foo\nBar",
                "Foo\r\nBar",
            ],
            'CR to CRLF' => [
                "Foo\rBar",
                "Foo\r\nBar",
            ],
            'CRLF preserved' => [
                "Foo\r\nBar",
                "Foo\r\nBar",
            ],
            'period doubled' => [
                "Foo\r\n.\r\nBar\r\n",
                "Foo\r\n..\r\nBar\r\n",
            ],
            'mixed endings with period' => [
                "Foo\r.\r\n\n .Foo\n\r\nBaz",
                "Foo\r\n..\r\n\r\n .Foo\r\n\r\nBaz",
            ],
        ];
    }
}
