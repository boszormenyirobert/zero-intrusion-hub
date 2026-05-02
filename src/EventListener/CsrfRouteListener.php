<?php

namespace App\EventListener;

use App\Attribute\CsrfProtectedRoute;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[AsEventListener(event: KernelEvents::CONTROLLER)]
class CsrfRouteListener
{
    public function __construct(
        private CsrfTokenManagerInterface $csrfTokenManager,
        private UrlGeneratorInterface $urlGenerator,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ControllerEvent $event): void
    {
        $controller = $event->getController();

        if (!is_array($controller)) {
            return;
        }

        $method = new \ReflectionMethod($controller[0], $controller[1]);
        $attributes = $method->getAttributes(CsrfProtectedRoute::class);

        if ($attributes === []) {
            return;
        }

        /** @var CsrfProtectedRoute $policy */
        $policy = $attributes[0]->newInstance();
        $request = $event->getRequest();
        $route = $request->attributes->get('_route');
        $tokenId = $this->resolveTokenId($policy, $request->attributes->all());
        $tokenValue = $this->resolveTokenValue($request, $policy);
        $isValid = is_string($tokenValue)
            && $tokenId !== null
            && $this->csrfTokenManager->isTokenValid(new CsrfToken($tokenId, $tokenValue));

        if ($isValid) {
            $this->logger->info('Protected CSRF route accepted', [
                'route' => $route,
                'token_id' => $tokenId,
            ]);

            return;
        }

        $this->logger->warning('Protected CSRF route denied because token is invalid', [
            'route' => $route,
            'token_id' => $tokenId,
        ]);

        if ($policy->failureRoute !== null) {
            $event->setController(fn () => new RedirectResponse($this->urlGenerator->generate($policy->failureRoute)));

            return;
        }

        $failureMessage = $policy->failureMessage;

        $event->setController(static function () use ($failureMessage): never {
            throw new AccessDeniedHttpException($failureMessage);
        });
    }

    private function resolveTokenValue(Request $request, CsrfProtectedRoute $policy): mixed
    {
        if ($policy->tokenSource === 'header') {
            return $request->headers->get($policy->tokenField);
        }

        return $request->request->get($policy->tokenField);
    }

    /** @param array<string, mixed> $routeAttributes */
    private function resolveTokenId(CsrfProtectedRoute $policy, array $routeAttributes): ?string
    {
        if ($policy->tokenId === null || $policy->tokenId === '') {
            return null;
        }

        return (string) preg_replace_callback('/\{([^}]+)\}/', static function (array $matches) use ($routeAttributes): string {
            $value = $routeAttributes[$matches[1]] ?? '';

            return is_scalar($value) ? (string) $value : '';
        }, $policy->tokenId);
    }
}
