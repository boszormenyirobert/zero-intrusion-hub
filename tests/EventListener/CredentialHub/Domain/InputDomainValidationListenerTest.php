<?php

declare(strict_types=1);

namespace App\Tests\EventListener\CredentialHub\Domain;

use App\EventListener\CredentialHub\Domain\InputDomainValidationListener;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class InputDomainValidationListenerTest extends TestCase
{
    public function testDomainReadRejectsInvalidJsonPayload(): void
    {
        $request = Request::create(
            '/api/credential-hub/domain/read/qr-identity',
            'POST',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: '{invalid'
        );

        $event = $this->createRequestEvent($request);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        $listener = new InputDomainValidationListener($logger);
        $listener($event);

        $response = $event->getResponse();

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(400, $response->getStatusCode());
        self::assertSame([
            'error' => 'Invalid JSON payload.',
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