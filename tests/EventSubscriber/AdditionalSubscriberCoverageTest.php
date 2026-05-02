<?php

declare(strict_types=1);

namespace App\Tests\EventSubscriber;

use App\Exception\EntityNotFoundException;
use App\Exception\InvalidInputException;
use App\Exception\InvalidPropertyException;
use App\Exception\MissingKeyException;
use App\EventSubscriber\ExceptionSubscriber;
use App\EventSubscriber\InstanceRegistrationSubscriber;
use App\Service\Instance\HUB\InstanceRegistrationService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Component\HttpKernel\KernelEvents;

final class AdditionalSubscriberCoverageTest extends TestCase
{
    public function testExceptionSubscriberHandlesCustomDomainExceptions(): void
    {
        $subscriber = new ExceptionSubscriber();

        self::assertSame(404, $this->dispatchException($subscriber, new EntityNotFoundException())->getStatusCode());
        self::assertSame(422, $this->dispatchException($subscriber, new InvalidPropertyException())->getStatusCode());
        self::assertSame(400, $this->dispatchException($subscriber, new InvalidInputException())->getStatusCode());
        self::assertSame(400, $this->dispatchException($subscriber, new MissingKeyException())->getStatusCode());
    }

    public function testExceptionSubscriberUsesSafeFallbackMessages(): void
    {
        $subscriber = new ExceptionSubscriber();

        $notFoundResponse = $this->dispatchException($subscriber, new NotFoundHttpException(''));
        self::assertSame(['error' => 'Request failed'], json_decode((string) $notFoundResponse->getContent(), true));

        $upstreamResponse = $this->dispatchException($subscriber, new class ('upstream', 400) extends \RuntimeException implements ClientExceptionInterface {
            public function getResponse(): ResponseInterface
            {
                throw new \BadMethodCallException('Not used in this test.');
            }
        });
        self::assertSame(['error' => 'Request failed'], json_decode((string) $upstreamResponse->getContent(), true));
    }

    public function testExceptionSubscriberUsesInternalFallbackMessagesForServerSideExceptions(): void
    {
        $subscriber = new ExceptionSubscriber();

        $kernelResponse = $this->dispatchException($subscriber, new HttpException(500, 'sensitive message'));
        self::assertSame(['error' => 'Internal Server Error'], json_decode((string) $kernelResponse->getContent(), true));

        $upstreamResponse = $this->dispatchException($subscriber, new class ('upstream', 503) extends \RuntimeException implements ClientExceptionInterface {
            public function getResponse(): ResponseInterface
            {
                throw new \BadMethodCallException('Not used in this test.');
            }
        });
        self::assertSame(['error' => 'Upstream request failed'], json_decode((string) $upstreamResponse->getContent(), true));
    }

    public function testExceptionSubscriberDeclaresSubscribedEvents(): void
    {
        self::assertSame([KernelEvents::EXCEPTION => 'onKernelException'], ExceptionSubscriber::getSubscribedEvents());
    }

    public function testInstanceRegistrationSubscriberSkipsSubRequests(): void
    {
        $service = $this->createMock(InstanceRegistrationService::class);
        $service->expects(self::never())->method('getInitializationState');

        $subscriber = new InstanceRegistrationSubscriber($this->createMock(LoggerInterface::class), $service);
        $request = Request::create('/');
        $event = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::SUB_REQUEST
        );

        $subscriber->onKernelRequest($event);

        self::assertFalse($request->attributes->has('InstanceRegistration'));
    }

    public function testInstanceRegistrationSubscriberStoresInitializationStateOnMainRequest(): void
    {
        $service = $this->createMock(InstanceRegistrationService::class);
        $service->expects(self::once())->method('getInitializationState')->willReturn(true);

        $subscriber = new InstanceRegistrationSubscriber($this->createMock(LoggerInterface::class), $service);
        $request = Request::create('/');
        $event = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        $subscriber->onKernelRequest($event);

        self::assertTrue((bool) $request->attributes->get('InstanceRegistration'));
        self::assertSame([KernelEvents::REQUEST => 'onKernelRequest'], InstanceRegistrationSubscriber::getSubscribedEvents());
    }

    private function dispatchException(ExceptionSubscriber $subscriber, \Throwable $throwable): \Symfony\Component\HttpFoundation\JsonResponse
    {
        $event = new ExceptionEvent(
            $this->createMock(HttpKernelInterface::class),
            Request::create('/test'),
            HttpKernelInterface::MAIN_REQUEST,
            $throwable
        );

        $subscriber->onKernelException($event);

        return $event->getResponse();
    }
}
