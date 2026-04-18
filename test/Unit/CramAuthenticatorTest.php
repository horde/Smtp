<?php

declare(strict_types=1);

namespace Horde\Smtp\Test\Unit;

use Horde\Smtp\Authenticator;
use Horde\Smtp\CramAuthenticator;
use Horde\Smtp\PasswordCredentials;
use Horde\Smtp\SmtpResponse;
use Horde\Smtp\Test\FakeSmtpConnection;
use Horde\Smtp\Test\SpyDebug;
use Horde\Smtp\Xoauth2Credentials;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

#[CoversClass(CramAuthenticator::class)]
class CramAuthenticatorTest extends TestCase
{
    #[DataProvider('variantProvider')]
    public function testMechanism(string $variant): void
    {
        $this->assertSame($variant, (new CramAuthenticator($variant))->mechanism());
    }

    public static function variantProvider(): array
    {
        return [
            'CRAM-MD5' => ['CRAM-MD5'],
            'CRAM-SHA1' => ['CRAM-SHA1'],
            'CRAM-SHA256' => ['CRAM-SHA256'],
        ];
    }

    public function testDefaultIsCramMd5(): void
    {
        $this->assertSame('CRAM-MD5', (new CramAuthenticator())->mechanism());
    }

    public function testInvalidVariantThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CramAuthenticator('CRAM-SHA512');
    }

    public function testSupportsPasswordCredentials(): void
    {
        $this->assertTrue((new CramAuthenticator())->supports(new PasswordCredentials('u', 'p')));
    }

    public function testDoesNotSupportXoauth2(): void
    {
        $this->assertFalse((new CramAuthenticator())->supports(new Xoauth2Credentials('u', 't')));
    }

    public function testImplementsInterface(): void
    {
        $this->assertInstanceOf(Authenticator::class, new CramAuthenticator());
    }

    public function testCramMd5ProtocolFlow(): void
    {
        $challenge = '<1234@server.example.com>';
        $conn = new FakeSmtpConnection();
        $conn->queueResponse(new SmtpResponse(334, [base64_encode($challenge)]));
        $conn->queueResponse(new SmtpResponse(235));
        $debug = new SpyDebug();

        $auth = new CramAuthenticator('CRAM-MD5');
        $auth->authenticate(
            $conn,
            new PasswordCredentials('alice', 'secret'),
            $debug,
        );

        $this->assertSame('AUTH CRAM-MD5', $conn->written[0]);

        $expectedDigest = hash_hmac('md5', $challenge, 'secret');
        $expectedResponse = base64_encode('alice ' . $expectedDigest);
        $this->assertSame($expectedResponse, $conn->written[1]);
    }

    public function testCramSha256ProtocolFlow(): void
    {
        $challenge = '<test@example>';
        $conn = new FakeSmtpConnection();
        $conn->queueResponse(new SmtpResponse(334, [base64_encode($challenge)]));
        $conn->queueResponse(new SmtpResponse(235));

        $auth = new CramAuthenticator('CRAM-SHA256');
        $auth->authenticate(
            $conn,
            new PasswordCredentials('bob', 'pw'),
            new SpyDebug(),
        );

        $this->assertSame('AUTH CRAM-SHA256', $conn->written[0]);

        $expectedDigest = hash_hmac('sha256', $challenge, 'pw');
        $this->assertSame(base64_encode('bob ' . $expectedDigest), $conn->written[1]);
    }

    public function testDebugLogOmitsPassword(): void
    {
        $conn = new FakeSmtpConnection();
        $conn->queueResponse(new SmtpResponse(334, [base64_encode('challenge')]));
        $conn->queueResponse(new SmtpResponse(235));
        $debug = new SpyDebug();

        (new CramAuthenticator())->authenticate(
            $conn,
            new PasswordCredentials('alice', 'topsecret'),
            $debug,
        );

        $this->assertCount(1, $debug->messages);
        $this->assertStringContainsString('CRAM-MD5', $debug->messages[0]);
        $this->assertStringContainsString('alice', $debug->messages[0]);
        $this->assertStringNotContainsString('topsecret', $debug->messages[0]);
    }
}
