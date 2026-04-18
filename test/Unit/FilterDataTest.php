<?php

declare(strict_types=1);

namespace Horde\Smtp\Test\Unit;

use Horde_Smtp_Filter_Data;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Horde_Smtp_Filter_Data::class)]
class FilterDataTest extends TestCase
{
    private const FILTER_ID = 'horde_smtp_data';

    public function setUp(): void
    {
        if (!in_array(self::FILTER_ID, stream_get_filters())) {
            stream_filter_register(self::FILTER_ID, Horde_Smtp_Filter_Data::class);
        }
    }

    #[DataProvider('escapeProvider')]
    public function testEscape(string $in, string $expected): void
    {
        $stream = fopen('php://temp', 'r+');
        stream_filter_append($stream, self::FILTER_ID, STREAM_FILTER_READ);

        fwrite($stream, $in);
        rewind($stream);

        $this->assertEquals(
            $expected,
            stream_get_contents($stream),
        );

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
