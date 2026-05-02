<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\EventListener\ValidationListenerHelper;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class ValidationListenerHelperTest extends TestCase
{
    public function testDecodeJsonRequestRejectsInvalidJson(): void
    {
        $request = Request::create('/api/test', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: '{bad');
        $request->attributes->set('_route', 'api_test');
        $event = $this->createRequestEvent($request);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        self::assertNull(ValidationListenerHelper::decodeJsonRequest($event, $logger));
        self::assertSame(['error' => 'Invalid JSON payload.'], json_decode((string) $event->getResponse()?->getContent(), true));
    }

    public function testDecodeJsonRequestRejectsNonArrayPayload(): void
    {
        $event = $this->createRequestEvent(Request::create('/api/test', 'POST', content: '"scalar"'));

        self::assertNull(ValidationListenerHelper::decodeJsonRequest($event));
        self::assertSame(400, $event->getResponse()?->getStatusCode());
    }

    public function testDecodeJsonRequestReturnsDecodedArray(): void
    {
        $event = $this->createRequestEvent(Request::create('/api/test', 'POST', content: '{"ok":true}'));

        self::assertSame(['ok' => true], ValidationListenerHelper::decodeJsonRequest($event));
        self::assertNull($event->getResponse());
    }

    public function testValidatePrefixOnlyAddsErrorForUnexpectedPrefix(): void
    {
        $errors = [];

        ValidationListenerHelper::validatePrefix('bad-prefix', 'cid_', 'publicId', $errors);
        ValidationListenerHelper::validatePrefix('', 'cid_', 'emptyField', $errors);

        self::assertSame(['publicId' => 'Invalid publicId prefix.'], $errors);
    }

    public function testValidateSourceRequiresExactMatch(): void
    {
        $errors = [];

        ValidationListenerHelper::validateSource(' mobile ', 'extension', $errors);

        self::assertSame(['source' => 'Invalid source value.'], $errors);
    }

    public function testValidateSourceAcceptsExactMatchAfterTrim(): void
    {
        $errors = [];

        ValidationListenerHelper::validateSource(' extension ', 'extension', $errors);

        self::assertSame([], $errors);
    }

    /** @dataProvider invalidDomainProvider */
    public function testValidateDomainRejectsInvalidValues(array $data, string $expectedMessage): void
    {
        $errors = [];

        ValidationListenerHelper::validateDomain($data, true, $errors);

        self::assertSame($expectedMessage, $errors['domain']);
    }

    public function testValidateDomainAcceptsValidDomainAndPort(): void
    {
        $errors = [];

        ValidationListenerHelper::validateDomain(['domain' => 'example.test:443'], true, $errors);

        self::assertSame([], $errors);
    }

    public function testValidateUserPublicIdAndTargetIdRejectInvalidCharacters(): void
    {
        $errors = [];

        ValidationListenerHelper::validateUserPublicId(['userPublicId' => 'bad*value'], $errors);
        ValidationListenerHelper::validateTargetId(['targetId' => 'bad-value'], $errors);

        self::assertArrayHasKey('userPublicId', $errors);
        self::assertArrayHasKey('targetId', $errors);
    }

    public function testValidateUserPublicIdAndTargetIdAllowExpectedValues(): void
    {
        $errors = [];

        ValidationListenerHelper::validateUserPublicId(['userPublicId' => ''], $errors);
        ValidationListenerHelper::validateUserPublicId(['userPublicId' => 'goodValue-_=/+'], $errors);
        ValidationListenerHelper::validateTargetId(['targetId' => 'VGFyZ2V0SWQ='], $errors);

        self::assertSame([], $errors);
    }

    public function testValidateApplicationRejectsLongControlCharacterAndHtmlValues(): void
    {
        $errors = [];
        ValidationListenerHelper::validateApplication(['application' => str_repeat('a', 101)], $errors);
        self::assertSame('application too long', $errors['application']);

        $errors = [];
        ValidationListenerHelper::validateApplication(['application' => "bad\x01value"], $errors);
        self::assertSame('invalid control characters', $errors['application']);

        $errors = [];
        ValidationListenerHelper::validateApplication(['application' => '<b>bad</b>'], $errors);
        self::assertSame('html not allowed', $errors['application']);
    }

    public function testValidateApplicationAllowsMissingEmptyAndValidValues(): void
    {
        $errors = [];
        ValidationListenerHelper::validateApplication([], $errors);
        ValidationListenerHelper::validateApplication(['application' => '   '], $errors);
        ValidationListenerHelper::validateApplication(['application' => 'Valid App'], $errors);

        self::assertSame([], $errors);
    }

    public function testValidateDescriptionRejectsLongControlCharacterAndHtmlValues(): void
    {
        $errors = [];
        ValidationListenerHelper::validateDescription(['description' => str_repeat('a', 2001)], $errors);
        self::assertSame('description too long', $errors['description']);

        $errors = [];
        ValidationListenerHelper::validateDescription(['description' => "bad\x01value"], $errors);
        self::assertSame('invalid control characters', $errors['description']);

        $errors = [];
        ValidationListenerHelper::validateDescription(['description' => '<i>bad</i>'], $errors);
        self::assertSame('html not allowed', $errors['description']);
    }

    public function testValidateDescriptionAllowsMissingEmptyAndValidValues(): void
    {
        $errors = [];
        ValidationListenerHelper::validateDescription([], $errors);
        ValidationListenerHelper::validateDescription(['description' => '   '], $errors);
        ValidationListenerHelper::validateDescription(['description' => 'Valid description'], $errors);

        self::assertSame([], $errors);
    }

    public function testValidateRequiredFieldsHandlesSpecialUserPublicIdRule(): void
    {
        $errors = [];

        ValidationListenerHelper::validateRequiredFields(['email' => ''], ['email', 'userPublicId'], $errors);

        self::assertSame([
            'email' => 'Email is required and must be a non-empty string.',
            'userPublicId' => 'UserPublicId is required.',
        ], $errors);
    }

    public function testValidateRequiredFieldsAcceptsPresentValuesIncludingEmptyUserPublicId(): void
    {
        $errors = [];

        ValidationListenerHelper::validateRequiredFields([
            'email' => 'valid@example.test',
            'userPublicId' => '',
        ], ['email', 'userPublicId'], $errors);

        self::assertSame([], $errors);
    }

    public function testValidateIvRejectsMissingAndInvalidValues(): void
    {
        $errors = [];
        ValidationListenerHelper::validateIv([], $errors);
        self::assertSame('iv is required.', $errors['iv']);

        $errors = [];
        ValidationListenerHelper::validateIv(['iv' => base64_encode('short')], $errors);
        self::assertSame('Invalid IV.', $errors['iv']);
    }

    public function testValidateProcessIdRejectsMissingInvalidAndAcceptsValidValues(): void
    {
        $errors = [];
        ValidationListenerHelper::validateProcessId([], $errors);
        self::assertSame('processId is required.', $errors['processId']);

        $errors = [];
        ValidationListenerHelper::validateProcessId(['processId' => 'short'], $errors);
        self::assertSame('Invalid processId.', $errors['processId']);

        $errors = [];
        $validProcessId = rtrim(base64_encode(str_repeat('a', 16)), '=');
        ValidationListenerHelper::validateProcessId(['processId' => $validProcessId], $errors);
        self::assertSame([], $errors);
    }

    public function testValidateEmailRejectsInvalidFormsAndAcceptsValidEmail(): void
    {
        $errors = [];
        ValidationListenerHelper::validateEmail([], $errors);
        self::assertSame('Email is required and must be a non-empty string.', $errors['email']);

        $errors = [];
        ValidationListenerHelper::validateEmail(['email' => "bad\x01@example.test"], $errors);
        self::assertSame('Invalid characters in email.', $errors['email_chars']);

        $errors = [];
        ValidationListenerHelper::validateEmail(['email' => 'not-an-email'], $errors);
        self::assertSame('Invalid email format.', $errors['email_format']);

        $errors = [];
        ValidationListenerHelper::validateEmail(['email' => 'valid@example.test'], $errors);
        self::assertSame([], $errors);
    }

    public function testValidateEmailRejectsTooLongAddress(): void
    {
        $errors = [];
        $tooLong = str_repeat('a', 245) . '@example.test';

        ValidationListenerHelper::validateEmail(['email' => $tooLong], $errors);

        self::assertSame('Email too long.', $errors['email_length']);
    }

    public function testValidateNoControlCharsIgnoresMissingAndNonStringValues(): void
    {
        $errors = [];

        ValidationListenerHelper::validateNoControlChars([
            'description' => 123,
        ], ['description', 'application'], $errors);

        self::assertSame([], $errors);
    }

    public function testValidateNoControlCharsCollectsPerFieldErrors(): void
    {
        $errors = [];

        ValidationListenerHelper::validateNoControlChars([
            'description' => "bad\x01value",
            'application' => 'safe',
        ], ['description', 'application'], $errors);

        self::assertSame(['description' => 'Contains invalid control characters'], $errors);
    }

    /** @return iterable<string, array{array<string, string>, string}> */
    public function invalidDomainProvider(): iterable
    {
        yield 'invalid host' => [['domain' => '-bad.example'], 'Invalid domain.'];
        yield 'invalid port' => [['domain' => 'example.test:70000'], 'Invalid port number.'];
        yield 'too long' => [['domain' => str_repeat('a', 254)], 'Invalid domain.'];
    }

    private function createRequestEvent(Request $request): RequestEvent
    {
        return new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );
    }
}
