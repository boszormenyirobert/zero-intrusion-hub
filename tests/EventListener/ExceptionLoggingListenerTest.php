<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\EventListener\ExceptionLoggingListener;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class ExceptionLoggingListenerTest extends TestCase
{
    public function testExceptionListenerIgnoresSubRequests(): void
    {
        $request = Request::create('/missing', 'GET');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('error');

        $listener = new ExceptionLoggingListener($logger);
        $listener(new ExceptionEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::SUB_REQUEST,
            new NotFoundHttpException('Not found')
        ));
    }

    public function testExceptionListenerLogsUnhandledException(): void
    {
        $request = Request::create('/missing', 'GET');
        $request->attributes->set('_route', 'missing_route');
        $request->attributes->set('_request_id', 'req-789');

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('error')
            ->with(
                'Unhandled exception thrown during request',
                self::callback(static function (array $context): bool {
                    return ($context['request_id'] ?? null) === 'req-789'
                        && ($context['route'] ?? null) === 'missing_route'
                        && ($context['status_code'] ?? null) === 404
                        && ($context['exception_class'] ?? null) === NotFoundHttpException::class;
                })
            );

        $listener = new ExceptionLoggingListener($logger);
        $listener($this->createExceptionEvent($request, new NotFoundHttpException('Not found')));
    }

    private function createExceptionEvent(Request $request, \Throwable $throwable): ExceptionEvent
    {
        return new ExceptionEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $throwable
        );
    }
}
