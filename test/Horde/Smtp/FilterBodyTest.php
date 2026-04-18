<?php

/**
 * Copyright 2014-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category   Horde
 * @copyright  2014-2016 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Smtp
 * @subpackage UnitTests
 */

namespace Horde\Smtp;

use Horde_Test_Case;
use stdClass;

/**
 * Test for the SMTP BODY filter.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @category   Horde
 * @copyright  2014-2016 Horde LLC
 * @ignore
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Smtp
 * @subpackage UnitTests
 * @coversNothing
 */
class FilterBodyTest extends Horde_Test_Case
{
    /**
     * @dataProvider bodyFilterProvider()
     */
    public function testBodyFilter($data, $result)
    {
        $params = new stdClass();

        $stream = fopen('php://temp', 'r+');
        stream_filter_register('horde_smtp_body', 'Horde_Smtp_Filter_Body');
        stream_filter_append(
            $stream,
            'horde_smtp_body',
            STREAM_FILTER_WRITE,
            $params
        );

        fwrite($stream, $data);
        fclose($stream);

        $this->assertEquals(
            $result,
            $params->body
        );
    }

    public function bodyFilterProvider()
    {
        return [
            [
                "This is 7-bit\r\ndata.",
                false,
            ],
            [
                str_repeat('A', 900) . "This is also 7-bit\r\ndata.",
                false,
            ],
            [
                "This is 8-bit åå\r\ndata.",
                '8bit',
            ],
            [
                str_repeat('A', 900) . "This is also 8-bit åå\r\ndata.",
                '8bit',
            ],
            [
                "This is binary \0\r\ndata.",
                'binary',
            ],
            [
                str_repeat('A', 1500) . "This is also binary åå\r\ndata.",
                'binary',
            ],
        ];
    }

}
