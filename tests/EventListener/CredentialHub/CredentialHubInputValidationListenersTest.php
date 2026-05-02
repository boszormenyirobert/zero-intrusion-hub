<?php

declare(strict_types=1);

namespace App\Tests\EventListener\CredentialHub;

use App\EventListener\CredentialHub\Domain\InputDomainValidationListener;
use App\EventListener\CredentialHub\OneTouch\InputOneTouchValidationListener;
use App\EventListener\CredentialHub\Shared\InputSharedValidationListener;
use App\EventListener\CredentialHub\Vault\InputVaultValidationListener;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class CredentialHubInputValidationListenersTest extends TestCase
{
    public function testDomainReadQrIdentityAllowsValidPayload(): void
    {
        $response = $this->dispatch(
            new InputDomainValidationListener($this->createMock(LoggerInterface::class)),
            '/api/credential-hub/domain/read/qr-identity',
            [
                'domain' => 'example.test',
                'userPublicId' => 'goodValue',
            ]
        );

        self::assertNull($response);
    }

    public function testDomainDeleteQrIdentityRejectsInvalidFields(): void
    {
        $response = $this->dispatch(
            new InputDomainValidationListener($this->createMock(LoggerInterface::class)),
            '/api/credential-hub/domain/delete/qr-identity',
            [
                'domain' => 'bad-domain-',
                'source' => 'mobile',
                'targetId' => 'bad-value',
                'type' => 'wrong-type',
                'userPublicId' => 'bad*value',
            ]
        );

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(400, $response->getStatusCode());
        self::assertSame('Invalid input.', json_decode((string) $response->getContent(), true)['error']);
    }

    public function testDomainDeleteStateRejectsInvalidProcessPayload(): void
    {
        $response = $this->dispatch(
            new InputDomainValidationListener($this->createMock(LoggerInterface::class)),
            '/api/credential-hub/domain/delete/state',
            [
                'processId' => 'short',
                'type' => 'wrong',
            ]
        );

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(400, $response->getStatusCode());
    }

    public function testDomainDeleteQrIdentityAllowsValidPayload(): void
    {
        $response = $this->dispatch(
            new InputDomainValidationListener($this->createMock(LoggerInterface::class)),
            '/api/credential-hub/domain/delete/qr-identity',
            [
                'domain' => 'example.test',
                'source' => 'extension',
                'targetId' => 'VGFyZ2V0SWQ=',
                'type' => 'delete-domain',
                'userPublicId' => 'goodValue',
            ]
        );

        self::assertNull($response);
    }

    public function testDomainDeleteStateAllowsValidPayload(): void
    {
        $response = $this->dispatch(
            new InputDomainValidationListener($this->createMock(LoggerInterface::class)),
            '/api/credential-hub/domain/delete/state',
            [
                'processId' => rtrim(base64_encode(str_repeat('h', 16)), '='),
                'type' => 'extension',
            ]
        );

        self::assertNull($response);
    }

    public function testDomainListenerIgnoresUnhandledRoute(): void
    {
        $response = $this->dispatch(
            new InputDomainValidationListener($this->createMock(LoggerInterface::class)),
            '/api/credential-hub/domain/unhandled',
            ['noop' => true]
        );

        self::assertNull($response);
    }

    public function testDomainReadStateAllowsValidPayload(): void
    {
        $response = $this->dispatch(
            new InputDomainValidationListener($this->createMock(LoggerInterface::class)),
            '/api/credential-hub/domain/read/state',
            [
                'domain' => 'example.test',
                'iv' => base64_encode(str_repeat('a', 16)),
                'processId' => rtrim(base64_encode(str_repeat('b', 16)), '='),
                'type' => 'extension',
            ]
        );

        self::assertNull($response);
    }

    public function testOneTouchQrIdentityRejectsWrongSourceAndType(): void
    {
        $response = $this->dispatch(
            new InputOneTouchValidationListener($this->createMock(LoggerInterface::class)),
            '/api/credential-hub/one-touch/qr-identity',
            [
                'source' => 'mobile',
                'type' => 'unsafe',
            ]
        );

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(400, $response->getStatusCode());
    }

    public function testOneTouchQrIdentityAllowsValidPayload(): void
    {
        $response = $this->dispatch(
            new InputOneTouchValidationListener($this->createMock(LoggerInterface::class)),
            '/api/credential-hub/one-touch/qr-identity',
            [
                'source' => 'extension',
                'type' => 'secure',
            ]
        );

        self::assertNull($response);
    }

    public function testOneTouchStateAllowsValidPayload(): void
    {
        $response = $this->dispatch(
            new InputOneTouchValidationListener($this->createMock(LoggerInterface::class)),
            '/api/credential-hub/one-touch/state',
            [
                'iv' => base64_encode(str_repeat('a', 16)),
                'processId' => rtrim(base64_encode(str_repeat('b', 16)), '='),
                'type' => 'extension',
            ]
        );

        self::assertNull($response);
    }

    public function testOneTouchListenerIgnoresUnhandledRoute(): void
    {
        $response = $this->dispatch(
            new InputOneTouchValidationListener($this->createMock(LoggerInterface::class)),
            '/api/credential-hub/one-touch/unhandled',
            ['noop' => true]
        );

        self::assertNull($response);
    }

    public function testOneTouchStateRejectsInvalidPayload(): void
    {
        $response = $this->dispatch(
            new InputOneTouchValidationListener($this->createMock(LoggerInterface::class)),
            '/api/credential-hub/one-touch/state',
            [
                'iv' => 'invalid',
                'processId' => 'short',
                'type' => 'wrong',
            ]
        );

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(400, $response->getStatusCode());
    }

    public function testSharedRegistrationQrIdentityRejectsInvalidDescriptionAndApplication(): void
    {
        $response = $this->dispatch(
            new InputSharedValidationListener($this->createMock(LoggerInterface::class)),
            '/api/credential-hub/shared/registration/qr-identity',
            [
                'description' => '<b>bad</b>',
                'isNew' => 'true',
                'source' => 'extension',
                'type' => 'registration-application',
                'userName' => 'tester',
                'userPassword' => 'secret',
                'userPublicId' => 'bad*value',
                'application' => 'ok-app',
            ]
        );

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(400, $response->getStatusCode());
    }

    public function testSharedRegistrationQrIdentityAllowsValidDomainPayload(): void
    {
        $response = $this->dispatch(
            new InputSharedValidationListener($this->createMock(LoggerInterface::class)),
            '/api/credential-hub/shared/registration/qr-identity',
            [
                'description' => 'Shared credential',
                'isNew' => 'true',
                'source' => 'extension',
                'type' => 'registration-domain',
                'userName' => 'tester',
                'userPassword' => 'secret',
                'userPublicId' => 'goodValue',
                'domain' => 'example.test',
                'targetId' => 'VGFyZ2V0SWQ=',
            ]
        );

        self::assertNull($response);
    }

    public function testSharedRegistrationQrIdentityAllowsValidApplicationPayload(): void
    {
        $response = $this->dispatch(
            new InputSharedValidationListener($this->createMock(LoggerInterface::class)),
            '/api/credential-hub/shared/registration/qr-identity',
            [
                'description' => 'Shared credential',
                'isNew' => 'true',
                'source' => 'extension',
                'type' => 'registration-application',
                'userName' => 'tester',
                'userPassword' => 'secret',
                'userPublicId' => 'goodValue',
                'application' => 'Example App',
            ]
        );

        self::assertNull($response);
    }

    public function testSharedRegistrationStateAllowsValidPayload(): void
    {
        $response = $this->dispatch(
            new InputSharedValidationListener($this->createMock(LoggerInterface::class)),
            '/api/credential-hub/shared/registration/state',
            [
                'processId' => rtrim(base64_encode(str_repeat('c', 16)), '='),
                'type' => 'extension',
            ]
        );

        self::assertNull($response);
    }

    public function testSharedListenerIgnoresUnhandledRoute(): void
    {
        $response = $this->dispatch(
            new InputSharedValidationListener($this->createMock(LoggerInterface::class)),
            '/api/credential-hub/shared/unhandled',
            ['noop' => true]
        );

        self::assertNull($response);
    }

    public function testSharedRegistrationStateRejectsInvalidPayload(): void
    {
        $response = $this->dispatch(
            new InputSharedValidationListener($this->createMock(LoggerInterface::class)),
            '/api/credential-hub/shared/registration/state',
            [
                'processId' => 'short',
                'type' => 'wrong',
            ]
        );

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(400, $response->getStatusCode());
    }

    public function testVaultReadQrIdentityRejectsInvalidSourceTypeAndUserPublicId(): void
    {
        $response = $this->dispatch(
            new InputVaultValidationListener($this->createMock(LoggerInterface::class)),
            '/api/credential-hub/vault/read/qr-identity',
            [
                'domain' => 'example.test',
                'source' => 'mobile',
                'type' => 'wrong',
                'userPublicId' => 'bad*value',
            ]
        );

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(400, $response->getStatusCode());
    }

    public function testVaultReadQrIdentityAllowsValidPayload(): void
    {
        $response = $this->dispatch(
            new InputVaultValidationListener($this->createMock(LoggerInterface::class)),
            '/api/credential-hub/vault/read/qr-identity',
            [
                'domain' => 'example.test',
                'source' => 'extension',
                'type' => 'applications',
                'userPublicId' => 'goodValue',
            ]
        );

        self::assertNull($response);
    }

    public function testVaultReadStateAllowsValidPayload(): void
    {
        $response = $this->dispatch(
            new InputVaultValidationListener($this->createMock(LoggerInterface::class)),
            '/api/credential-hub/vault/read/state',
            [
                'domain' => 'example.test',
                'iv' => base64_encode(str_repeat('d', 16)),
                'processId' => rtrim(base64_encode(str_repeat('e', 16)), '='),
                'type' => 'extension',
            ]
        );

        self::assertNull($response);
    }

    public function testVaultEditQrIdentityRejectsInvalidApplicationDescriptionAndTargetId(): void
    {
        $response = $this->dispatch(
            new InputVaultValidationListener($this->createMock(LoggerInterface::class)),
            '/api/credential-hub/vault/edit/qr-identity',
            [
                'application' => '<b>bad</b>',
                'description' => "bad\x01value",
                'source' => 'extension',
                'targetId' => 'bad-value',
                'type' => 'update-applications',
                'userName' => 'tester',
                'userPassword' => 'secret',
                'userPublicId' => 'goodValue',
            ]
        );

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(400, $response->getStatusCode());
    }

    public function testVaultDeleteQrIdentityRejectsInvalidSourceAndType(): void
    {
        $response = $this->dispatch(
            new InputVaultValidationListener($this->createMock(LoggerInterface::class)),
            '/api/credential-hub/vault/delete/qr-identity',
            [
                'source' => 'desktop',
                'targetId' => 'bad-value',
                'type' => 'wrong',
                'userPublicId' => 'bad*value',
            ]
        );

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(400, $response->getStatusCode());
    }

    public function testVaultDeleteQrIdentityAllowsValidPayload(): void
    {
        $response = $this->dispatch(
            new InputVaultValidationListener($this->createMock(LoggerInterface::class)),
            '/api/credential-hub/vault/delete/qr-identity',
            [
                'source' => 'extension',
                'targetId' => 'VGFyZ2V0SWQ=',
                'type' => 'delete-applications',
                'userPublicId' => 'goodValue',
            ]
        );

        self::assertNull($response);
    }

    public function testVaultEditQrIdentityAllowsValidPayload(): void
    {
        $response = $this->dispatch(
            new InputVaultValidationListener($this->createMock(LoggerInterface::class)),
            '/api/credential-hub/vault/edit/qr-identity',
            [
                'application' => 'Example App',
                'description' => 'Credential update',
                'source' => 'extension',
                'targetId' => 'VGFyZ2V0SWQ=',
                'type' => 'update-applications',
                'userName' => 'tester',
                'userPassword' => 'secret',
                'userPublicId' => 'goodValue',
            ]
        );

        self::assertNull($response);
    }

    public function testVaultStateRejectsInvalidPayload(): void
    {
        $listener = new InputVaultValidationListener($this->createMock(LoggerInterface::class));

        $editResponse = $this->dispatch($listener, '/api/credential-hub/vault/edit/state', [
            'processId' => 'short',
            'type' => 'wrong',
        ]);
        $deleteResponse = $this->dispatch($listener, '/api/credential-hub/vault/delete/state', [
            'processId' => 'short',
            'type' => 'wrong',
        ]);

        self::assertInstanceOf(Response::class, $editResponse);
        self::assertSame(400, $editResponse->getStatusCode());
        self::assertInstanceOf(Response::class, $deleteResponse);
        self::assertSame(400, $deleteResponse->getStatusCode());
    }

    public function testVaultEditAndDeleteStateAllowValidPayloads(): void
    {
        $listener = new InputVaultValidationListener($this->createMock(LoggerInterface::class));

        $editResponse = $this->dispatch($listener, '/api/credential-hub/vault/edit/state', [
            'processId' => rtrim(base64_encode(str_repeat('f', 16)), '='),
            'type' => 'extension',
        ]);
        $deleteResponse = $this->dispatch($listener, '/api/credential-hub/vault/delete/state', [
            'processId' => rtrim(base64_encode(str_repeat('g', 16)), '='),
            'type' => 'extension',
        ]);

        self::assertNull($editResponse);
        self::assertNull($deleteResponse);
    }

    public function testVaultListenerIgnoresUnhandledRoute(): void
    {
        $response = $this->dispatch(
            new InputVaultValidationListener($this->createMock(LoggerInterface::class)),
            '/api/credential-hub/vault/unhandled',
            ['noop' => true]
        );

        self::assertNull($response);
    }

    private function dispatch(object $listener, string $path, array $payload): ?Response
    {
        $request = Request::create(
            $path,
            'POST',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($payload, JSON_THROW_ON_ERROR)
        );

        $event = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        $listener($event);

        return $event->getResponse();
    }
}
