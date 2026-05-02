<?php

declare(strict_types=1);

namespace App\Tests\EventListener\User;

use App\EventListener\User\InputUserValidationListener;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class InputUserValidationListenerTest extends TestCase
{
    public function testApiLoginRejectsMissingClientAuthHeader(): void
    {
        $request = Request::create(
            '/api/user-login',
            'POST',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'publicId' => 'cid_example',
                'message' => 'ckey_example',
                'userPublicId' => '',
            ], JSON_THROW_ON_ERROR)
        );

        $event = $this->createRequestEvent($request);

        $listener = new InputUserValidationListener($this->createMock(LoggerInterface::class));
        $listener($event);

        $response = $event->getResponse();

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(401, $response->getStatusCode());
        self::assertSame([
            'error' => 'Missing x-client-auth header!',
        ], json_decode((string) $response->getContent(), true));
    }

    public function testApiLoginRejectsInvalidJsonPayload(): void
    {
        $request = Request::create('/api/user-login', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: '{invalid');
        $request->headers->set('x-client-auth', 'header-value');

        $event = $this->createRequestEvent($request);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        $listener = new InputUserValidationListener($logger);
        $listener($event);

        $response = $event->getResponse();

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(400, $response->getStatusCode());
        self::assertSame([
            'error' => 'Invalid JSON payload.',
        ], json_decode((string) $response->getContent(), true));
    }

    public function testApiLoginRejectsInvalidCorporatePrefixes(): void
    {
        $request = Request::create(
            '/api/user-login',
            'POST',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'publicId' => 'bad-public-id',
                'message' => 'bad-message',
                'userPublicId' => 'user-public-id',
            ], JSON_THROW_ON_ERROR)
        );
        $request->headers->set('x-client-auth', 'header-value');

        $event = $this->createRequestEvent($request);

        $listener = new InputUserValidationListener($this->createMock(LoggerInterface::class));
        $listener($event);

        $response = $event->getResponse();

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(400, $response->getStatusCode());
        self::assertSame([
            'error' => 'Invalid input.',
            'validation_errors' => [
                'publicId' => 'Invalid publicId prefix.',
                'message' => 'Invalid message prefix.',
            ],
        ], json_decode((string) $response->getContent(), true));
    }

    public function testLoginCallbackRejectsInvalidEmailAndProcessId(): void
    {
        $request = Request::create(
            '/api/user-login/callback',
            'POST',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'signature' => 'signature-value',
                'publicId' => 'cid_public',
                'email' => 'bad-email',
                'processId' => 'short',
            ], JSON_THROW_ON_ERROR)
        );

        $event = $this->createRequestEvent($request);

        $listener = new InputUserValidationListener($this->createMock(LoggerInterface::class));
        $listener($event);

        $response = $event->getResponse();

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(400, $response->getStatusCode());
        self::assertSame([
            'error' => 'Invalid input.',
            'validation_errors' => [
                'email_format' => 'Invalid email format.',
                'processId' => 'Invalid processId.',
            ],
        ], json_decode((string) $response->getContent(), true));
    }

    public function testLoginCheckRejectsMissingDomainProcessId(): void
    {
        $request = Request::create(
            '/api/user-login/check',
            'POST',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([], JSON_THROW_ON_ERROR)
        );

        $event = $this->createRequestEvent($request);

        $listener = new InputUserValidationListener($this->createMock(LoggerInterface::class));
        $listener($event);

        $response = $event->getResponse();

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(400, $response->getStatusCode());
        self::assertSame([
            'error' => 'Invalid input.',
            'validation_errors' => [
                'domainProcessId' => 'DomainProcessId is required and must be a non-empty string.',
            ],
        ], json_decode((string) $response->getContent(), true));
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