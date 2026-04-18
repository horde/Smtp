<?php

declare(strict_types=1);

namespace Horde\Smtp\Test\Unit;

use Horde_Smtp_Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Horde_Smtp_Exception::class)]
class ExceptionTest extends TestCase
{
    public function testRawMsgProperty(): void
    {
        $e = new Horde_Smtp_Exception('Test error');

        $this->assertSame('Test error', $e->raw_msg);
    }

    public function testDefaultSmtpCodeIsZero(): void
    {
        $e = new Horde_Smtp_Exception('error');

        $this->assertSame(0, $e->getSmtpCode());
    }

    public function testGetSmtpCodeReturnsSetCode(): void
    {
        $e = new Horde_Smtp_Exception('error');
        $e->setSmtpCode(450);

        $this->assertSame(450, $e->getSmtpCode());
    }

    #[DataProvider('smtpCodeMappingProvider')]
    public function testSetSmtpCodeMapsToExceptionCode(int $smtpCode, int $expectedCode): void
    {
        $e = new Horde_Smtp_Exception('error');
        $e->setSmtpCode($smtpCode);

        $this->assertSame($expectedCode, $e->getCode());
    }

    public static function smtpCodeMappingProvider(): array
    {
        return [
            '450 → MAILBOX_UNAVAILABLE' => [450, Horde_Smtp_Exception::MAILBOX_UNAVAILABLE],
            '452 → INSUFFICIENT_STORAGE' => [452, Horde_Smtp_Exception::INSUFFICIENT_STORAGE],
            '454 → LOGIN_TLSFAILURE' => [454, Horde_Smtp_Exception::LOGIN_TLSFAILURE],
            '530 → LOGIN_REQUIREAUTHENTICATION' => [530, Horde_Smtp_Exception::LOGIN_REQUIREAUTHENTICATION],
            '550 → MAILBOX_UNAVAILABLE' => [550, Horde_Smtp_Exception::MAILBOX_UNAVAILABLE],
            '551 → UNKNOWN_LOCAL_USER' => [551, Horde_Smtp_Exception::UNKNOWN_LOCAL_USER],
            '552 → OVERQUOTA' => [552, Horde_Smtp_Exception::OVERQUOTA],
            '554 → DISCONNECT' => [554, Horde_Smtp_Exception::DISCONNECT],
        ];
    }

    #[DataProvider('smtpCategoryCodeProvider')]
    public function testSetSmtpCodeMapsCategoryBySecondDigit(int $smtpCode, int $expectedCode): void
    {
        $e = new Horde_Smtp_Exception('error');
        $e->setSmtpCode($smtpCode);

        $this->assertSame($expectedCode, $e->getCode());
    }

    public static function smtpCategoryCodeProvider(): array
    {
        return [
            'x0x → CATEGORY_SYNTAX' => [500, Horde_Smtp_Exception::CATEGORY_SYNTAX],
            'x1x → CATEGORY_INFORMATIONAL' => [214, Horde_Smtp_Exception::CATEGORY_INFORMATIONAL],
            'x2x → CATEGORY_CONNECTIONS' => [421, Horde_Smtp_Exception::CATEGORY_CONNECTIONS],
            'x5x → CATEGORY_MAILSYSTEM' => [250, Horde_Smtp_Exception::CATEGORY_MAILSYSTEM],
        ];
    }

    public function testGetEnhancedSmtpCodeReturnsSetCode(): void
    {
        $e = new Horde_Smtp_Exception('error');
        $e->setEnhancedSmtpCode('5.1.1');

        $this->assertSame('5.1.1', $e->getEnhancedSmtpCode());
    }

    public function testDefaultEnhancedCodeIsNull(): void
    {
        $e = new Horde_Smtp_Exception('error');

        $this->assertNull($e->getEnhancedSmtpCode());
    }

    #[DataProvider('enhancedCodeMappingProvider')]
    public function testSetEnhancedSmtpCodeMapsCategory(string $enhanced, int $expectedCode): void
    {
        $e = new Horde_Smtp_Exception('error');
        $e->setEnhancedSmtpCode($enhanced);

        $this->assertSame($expectedCode, $e->getCode());
    }

    public static function enhancedCodeMappingProvider(): array
    {
        return [
            '.1 → CATEGORY_ADDRESS' => ['5.1.1', Horde_Smtp_Exception::CATEGORY_ADDRESS],
            '.2 → CATEGORY_MAILBOX' => ['5.2.0', Horde_Smtp_Exception::CATEGORY_MAILBOX],
            '.3 → CATEGORY_MAILSYSTEM' => ['4.3.1', Horde_Smtp_Exception::CATEGORY_MAILSYSTEM],
            '.4 → CATEGORY_NETWORK' => ['4.4.0', Horde_Smtp_Exception::CATEGORY_NETWORK],
            '.5 → CATEGORY_DELIVERY' => ['4.5.0', Horde_Smtp_Exception::CATEGORY_DELIVERY],
            '.6 → CATEGORY_CONTENT' => ['5.6.0', Horde_Smtp_Exception::CATEGORY_CONTENT],
            '.7 → CATEGORY_SECURITY' => ['5.7.1', Horde_Smtp_Exception::CATEGORY_SECURITY],
        ];
    }

    public function testEnhancedCodeDoesNotOverrideSpecificErrorCode(): void
    {
        $e = new Horde_Smtp_Exception('error');
        $e->setSmtpCode(450);

        $this->assertSame(Horde_Smtp_Exception::MAILBOX_UNAVAILABLE, $e->getCode());

        $e->setEnhancedSmtpCode('4.7.1');

        $this->assertSame(Horde_Smtp_Exception::MAILBOX_UNAVAILABLE, $e->getCode());
    }

    public function testPermanentTrueFor5xxSmtpCode(): void
    {
        $e = new Horde_Smtp_Exception('error');
        $e->setSmtpCode(550);

        $this->assertTrue($e->permanent);
    }

    public function testPermanentFalseFor4xxSmtpCode(): void
    {
        $e = new Horde_Smtp_Exception('error');
        $e->setSmtpCode(450);

        $this->assertFalse($e->permanent);
    }

    public function testPermanentTrueFor5xxEnhancedCode(): void
    {
        $e = new Horde_Smtp_Exception('error');
        $e->setEnhancedSmtpCode('5.1.1');

        $this->assertTrue($e->permanent);
    }

    public function testPermanentFalseFor4xxEnhancedCode(): void
    {
        $e = new Horde_Smtp_Exception('error');
        $e->setEnhancedSmtpCode('4.1.1');

        $this->assertFalse($e->permanent);
    }

    public function testSetSmtpCodeResetsEnhancedCode(): void
    {
        $e = new Horde_Smtp_Exception('error');
        $e->setEnhancedSmtpCode('5.1.1');
        $e->setSmtpCode(450);

        $this->assertNull($e->getEnhancedSmtpCode());
    }
}
