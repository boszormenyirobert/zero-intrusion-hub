<?php

declare(strict_types=1);

namespace App\Tests\EventSubscriber;

use App\EventSubscriber\ExceptionSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class ExceptionSubscriberTest extends TestCase
{
    public function testInternalExceptionsAreSanitized(): void
    {
        $subscriber = new ExceptionSubscriber();
        $event = $this->createExceptionEvent(new \RuntimeException('db password=secret'));

        $subscriber->onKernelException($event);

        self::assertSame(500, $event->getResponse()->getStatusCode());
        self::assertSame([
            'error' => 'Internal Server Error',
        ], json_decode((string) $event->getResponse()->getContent(), true));
    }

    public function testClientBadRequestMessagesRemainReadable(): void
    {
        $subscriber = new ExceptionSubscriber();
        $event = $this->createExceptionEvent(new BadRequestHttpException('Invalid JSON payload'));

        $subscriber->onKernelException($event);

        self::assertSame(400, $event->getResponse()->getStatusCode());
        self::assertSame([
            'error' => 'Invalid JSON payload',
        ], json_decode((string) $event->getResponse()->getContent(), true));
    }

    private function createExceptionEvent(\Throwable $throwable): ExceptionEvent
    {
        return new ExceptionEvent(
            $this->createMock(HttpKernelInterface::class),
            Request::create('/test', 'GET'),
            HttpKernelInterface::MAIN_REQUEST,
            $throwable
        );
    }
}