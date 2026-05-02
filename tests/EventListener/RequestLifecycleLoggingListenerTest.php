<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\EventListener\RequestLifecycleLoggingListener;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class RequestLifecycleLoggingListenerTest extends TestCase
{
    public function testKernelRequestIgnoresSubRequests(): void
    {
        $request = Request::create('/api/example', 'POST');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('info');

        $listener = new RequestLifecycleLoggingListener($logger);
        $listener->onKernelRequest(new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::SUB_REQUEST
        ));
    }

    public function testKernelRequestSetsRequestIdAndLogsIncomingRequest(): void
    {
        $request = Request::create('/api/example', 'POST', server: [
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_USER_AGENT' => 'SecurityTest/1.0',
        ]);
        $request->attributes->set('_route', 'example_route');

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('info')
            ->with(
                'Incoming request received',
                self::callback(static function (array $context): bool {
                    return ($context['route'] ?? null) === 'example_route'
                        && ($context['method'] ?? null) === 'POST'
                        && ($context['path'] ?? null) === '/api/example'
                        && is_string($context['request_id'] ?? null)
                        && $context['request_id'] !== ''
                        && !array_key_exists('client_ip', $context)
                        && !array_key_exists('user_agent', $context)
                        && is_string($context['client_ip_hash'] ?? null)
                        && is_string($context['user_agent_hash'] ?? null);
                })
            );

        $listener = new RequestLifecycleLoggingListener($logger);
        $listener->onKernelRequest($this->createRequestEvent($request));

        self::assertIsString($request->attributes->get('_request_id'));
        self::assertNotNull($request->attributes->get('_request_started_at'));
    }

    public function testKernelControllerLogsResolvedController(): void
    {
        $request = Request::create('/login', 'GET');
        $request->attributes->set('_route', 'instance_login');
        $request->attributes->set('_request_id', 'req-123');

        $controller = [new class () {
            public function __invoke(): void
            {
            }

            public function login(): void
            {
            }
        }, 'login'];

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('info')
            ->with(
                'Controller resolved for request',
                self::callback(static function (array $context): bool {
                    return ($context['request_id'] ?? null) === 'req-123'
                        && ($context['route'] ?? null) === 'instance_login'
                        && str_contains((string) ($context['controller'] ?? ''), '::login');
                })
            );

        $listener = new RequestLifecycleLoggingListener($logger);
        $listener->onKernelController($this->createControllerEvent($request, $controller));
    }

    public function testKernelControllerLogsInvokableObjectControllerName(): void
    {
        $request = Request::create('/login', 'GET');
        $request->attributes->set('_route', 'instance_login');
        $request->attributes->set('_request_id', 'req-234');

        $controller = new class () {
            public function __invoke(): void
            {
            }
        };

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('info')
            ->with('Controller resolved for request', self::callback(static fn (array $context): bool => is_string($context['controller'] ?? null) && ($context['controller'] ?? '') !== ''));

        $listener = new RequestLifecycleLoggingListener($logger);
        $listener->onKernelController($this->createControllerEvent($request, $controller));
    }

    public function testKernelControllerIgnoresSubRequests(): void
    {
        $request = Request::create('/login', 'GET');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('info');

        $listener = new RequestLifecycleLoggingListener($logger);
        $listener->onKernelController(new ControllerEvent(
            $this->createMock(HttpKernelInterface::class),
            [new class () {
                public function __invoke(): void
                {
                }
            }, '__invoke'],
            $request,
            HttpKernelInterface::SUB_REQUEST
        ));
    }

    public function testKernelResponseLogsCompletedRequest(): void
    {
        $request = Request::create('/account', 'GET');
        $request->attributes->set('_route', 'account');
        $request->attributes->set('_request_id', 'req-456');
        $request->attributes->set('_request_started_at', microtime(true) - 0.05);

        $response = new Response('ok', 200, ['Content-Type' => 'application/json']);

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('info')
            ->with(
                'Request completed',
                self::callback(static function (array $context): bool {
                    return ($context['request_id'] ?? null) === 'req-456'
                        && ($context['route'] ?? null) === 'account'
                        && ($context['status_code'] ?? null) === 200
                        && is_numeric($context['duration_ms'] ?? null);
                })
            );

        $listener = new RequestLifecycleLoggingListener($logger);
        $listener->onKernelResponse($this->createResponseEvent($request, $response));
    }

    public function testKernelResponseLogsNullDurationWhenStartTimeMissing(): void
    {
        $request = Request::create('/account', 'GET');
        $request->attributes->set('_route', 'account');
        $request->attributes->set('_request_id', 'req-999');

        $response = new Response('ok', 204);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('info')
            ->with('Request completed', self::callback(static fn (array $context): bool => array_key_exists('duration_ms', $context) && $context['duration_ms'] === null));

        $listener = new RequestLifecycleLoggingListener($logger);
        $listener->onKernelResponse($this->createResponseEvent($request, $response));
    }

    public function testKernelResponseIgnoresSubRequests(): void
    {
        $request = Request::create('/account', 'GET');
        $response = new Response('ok', 200);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('info');

        $listener = new RequestLifecycleLoggingListener($logger);
        $listener->onKernelResponse(new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::SUB_REQUEST,
            $response
        ));
    }

    public function testFormatControllerHandlesStringAndArrayClassNames(): void
    {
        $listener = new RequestLifecycleLoggingListener($this->createMock(LoggerInterface::class));
        $method = new \ReflectionMethod($listener, 'formatController');
        $method->setAccessible(true);

        self::assertSame('app.controller.service', $method->invoke($listener, 'app.controller.service'));
        self::assertSame('StaticClass::run', $method->invoke($listener, ['StaticClass', 'run']));
    }

    private function createRequestEvent(Request $request): RequestEvent
    {
        return new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );
    }

    private function createControllerEvent(Request $request, callable|array|object $controller): ControllerEvent
    {
        return new ControllerEvent(
            $this->createMock(HttpKernelInterface::class),
            $controller,
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );
    }

    private function createResponseEvent(Request $request, Response $response): ResponseEvent
    {
        return new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response
        );
    }
}
