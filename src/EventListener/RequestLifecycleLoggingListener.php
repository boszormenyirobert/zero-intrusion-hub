<?php

namespace App\EventListener;

use App\Logger\LogTrace;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::REQUEST, method: 'onKernelRequest')]
#[AsEventListener(event: KernelEvents::CONTROLLER, method: 'onKernelController')]
#[AsEventListener(event: KernelEvents::RESPONSE, method: 'onKernelResponse')]
class RequestLifecycleLoggingListener
{
    public function __construct(
        private LoggerInterface $logger
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $requestId = $request->attributes->get('_request_id');

        if (!is_string($requestId) || $requestId === '') {
            $requestId = bin2hex(random_bytes(8));
            $request->attributes->set('_request_id', $requestId);
        }

        $request->attributes->set('_request_started_at', microtime(true));

        $this->logger->info('Incoming request received', [
            'request_id' => $requestId,
            'route' => $request->attributes->get('_route'),
            'method' => $request->getMethod(),
            'path' => $request->getPathInfo(),
            'query_keys' => array_keys($request->query->all()),
            'content_type' => $request->headers->get('Content-Type'),
            'client_ip_hash' => LogTrace::fingerprint($request->getClientIp()),
            'user_agent_hash' => LogTrace::fingerprint($request->headers->get('User-Agent')),
            'is_api' => str_starts_with($request->getPathInfo(), '/api/'),
        ]);
    }

    public function onKernelController(ControllerEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $controller = $event->getController();

        $this->logger->info('Controller resolved for request', [
            'request_id' => $request->attributes->get('_request_id'),
            'route' => $request->attributes->get('_route'),
            'controller' => $this->formatController($controller),
            'method' => $request->getMethod(),
            'path' => $request->getPathInfo(),
        ]);
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();
        $startedAt = $request->attributes->get('_request_started_at');

        $this->logger->info('Request completed', [
            'request_id' => $request->attributes->get('_request_id'),
            'route' => $request->attributes->get('_route'),
            'method' => $request->getMethod(),
            'path' => $request->getPathInfo(),
            'status_code' => $response->getStatusCode(),
            'response_content_type' => $response->headers->get('Content-Type'),
            'duration_ms' => is_numeric($startedAt)
                ? round((microtime(true) - (float) $startedAt) * 1000, 2)
                : null,
        ]);
    }

    private function formatController(callable|array|object|string $controller): string
    {
        if (is_array($controller)) {
            $class = is_object($controller[0]) ? $controller[0]::class : (string) $controller[0];

            return $class . '::' . (string) ($controller[1] ?? '__invoke');
        }

        if (is_object($controller)) {
            return $controller::class;
        }

        return is_string($controller) ? $controller : 'unknown';
    }
}
