<?php

namespace App\EventListener;

use App\Attribute\JwtRequired;
use App\Logger\LogTrace;
use App\Service\JWT\JwtService;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Psr\Log\LoggerInterface;

/**
 * Event listener for JWT authentication on controller actions.
 *
 * Intercepts controller events, checks for JwtRequired attribute,
 * validates the JWT token from cookies, and redirects to login if invalid.
 * Sets a request attribute 'is_jwt_valid' for downstream use.
 */
#[AsEventListener(event: KernelEvents::CONTROLLER)]
class JwtAuthListener
{
    public function __construct(
        private JwtService $jwtService,
        private UrlGeneratorInterface $urlGenerator,
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
        $attributes = $method->getAttributes(JwtRequired::class);

        if (empty($attributes)) {
            return;
        }

        $request = $event->getRequest();
        $route = $request->attributes->get('_route');

        $payload = $this->jwtService->extractPayloadFromRequest($request);
        $isValid = $payload !== null;

        $this->logger->info('Protected route JWT evaluated', [
            'route' => $route,
            'is_jwt_valid' => $isValid,
            'username_hash' => isset($payload['username']) && is_string($payload['username']) ? LogTrace::fingerprint($payload['username']) : null,
        ]);

        if (!$isValid) {
            $this->logger->warning('Protected route denied because JWT is invalid', [
                'route' => $route,
            ]);

            $event->setController(function () {
                return new RedirectResponse(
                    $this->urlGenerator->generate('instance_login')
                );
            });
        }

        $request->attributes->set('is_jwt_valid', $isValid);
    }
}
