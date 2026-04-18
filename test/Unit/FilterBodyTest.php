<?php

declare(strict_types=1);

namespace Horde\Smtp\Test\Unit;

use Horde_Smtp_Filter_Body;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(Horde_Smtp_Filter_Body::class)]
class FilterBodyTest extends TestCase
{
    #[DataProvider('bodyFilterProvider')]
    public function testBodyFilter(string $data, string|false $result): void
    {
        $params = new stdClass();

        $stream = fopen('php://temp', 'r+');
        $filterId = 'horde_smtp_body_' . bin2hex(random_bytes(4));
        stream_filter_register($filterId, Horde_Smtp_Filter_Body::class);
        stream_filter_append(
            $stream,
            $filterId,
            STREAM_FILTER_WRITE,
            $params,
        );

        fwrite($stream, $data);
        fclose($stream);

        $this->assertEquals($result, $params->body);
    }

    public static function bodyFilterProvider(): array
    {
        return [
            '7-bit ASCII' => [
                "This is 7-bit\r\ndata.",
                false,
            ],
            '7-bit ASCII long' => [
                str_repeat('A', 900) . "This is also 7-bit\r\ndata.",
                false,
            ],
            '8-bit UTF-8' => [
                "This is 8-bit \xC3\xA5\xC3\xA5\r\ndata.",
                '8bit',
            ],
            '8-bit UTF-8 long' => [
                str_repeat('A', 900) . "This is also 8-bit \xC3\xA5\xC3\xA5\r\ndata.",
                '8bit',
            ],
            'binary NULL byte' => [
                "This is binary \0\r\ndata.",
                'binary',
            ],
            'binary long line' => [
                str_repeat('A', 1500) . "This is also binary \xC3\xA5\xC3\xA5\r\ndata.",
                'binary',
            ],
        ];
    }
}
