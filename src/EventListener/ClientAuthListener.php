<?php

namespace App\EventListener;

use App\Attribute\ClientAuthRequired;
use App\Logger\LogTrace;
use App\Service\Security\ApiClientAuthGuard;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::CONTROLLER)]
class ClientAuthListener
{
    /**
     * Enforces presence/policy checks for routes marked with `#[ClientAuthRequired]`.
     *
     * This listener intentionally does not claim final cryptographic validation of
     * client-facing HMAC values that originate from the upstream API. For API-issued
     * QR/HMAC flows, the HUB acts as a controlled proxy/orchestrator and the upstream
     * API remains the validation authority for the returned client-auth material.
     */
    public function __construct(
        private ApiClientAuthGuard $apiClientAuthGuard,
        private LoggerInterface $logger
    ) {
    }

    public function __invoke(ControllerEvent $event): void
    {
        $controller = $event->getController();

        if (!is_array($controller)) {
            return;
        }

        $method = new \ReflectionMethod($controller[0], $controller[1]);
        $attributes = $method->getAttributes(ClientAuthRequired::class);

        if (empty($attributes)) {
            return;
        }

        $request = $event->getRequest();
        $header = $this->apiClientAuthGuard->resolveHeader($request);
        $route = $request->attributes->get('_route');

        if ($header === null) {
            $this->logger->warning('Protected client-auth route denied because header is missing', [
                'route' => $route,
            ]);

            $event->setController(fn () => $this->apiClientAuthGuard->createMissingHeaderResponse());

            return;
        }

        $this->apiClientAuthGuard->storeValidatedHeader($request, $header);

        $this->logger->info('Protected client-auth route accepted', [
            'route' => $route,
            'client_auth_hash' => LogTrace::fingerprint($header),
        ]);
    }
}
