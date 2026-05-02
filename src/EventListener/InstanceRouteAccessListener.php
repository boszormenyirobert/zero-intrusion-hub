<?php

namespace App\EventListener;

use App\Attribute\InitializationOnlyRoute;
use App\Attribute\InitializationOrJwtRoute;
use App\Service\Instance\HUB\RegistrationMenuAvailabilityService;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsEventListener(event: KernelEvents::CONTROLLER)]
class InstanceRouteAccessListener
{
    public function __construct(
        private RegistrationMenuAvailabilityService $registrationMenuAvailabilityService,
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
        $request = $event->getRequest();
        $route = $request->attributes->get('_route');

        if (!empty($method->getAttributes(InitializationOnlyRoute::class))) {
            if ($this->registrationMenuAvailabilityService->canAccessManagementRoute($request)) {
                $this->logger->info('Initialization-only route accepted', [
                    'route' => $route,
                ]);

                return;
            }

            $this->logger->warning('Initialization-only route denied', [
                'route' => $route,
            ]);

            $event->setController(fn () => new RedirectResponse($this->urlGenerator->generate('instance_login')));

            return;
        }

        if (!empty($method->getAttributes(InitializationOrJwtRoute::class))) {
            if ($this->registrationMenuAvailabilityService->canAccessUsersRoute($request)) {
                $this->logger->info('Initialization-or-JWT route accepted', [
                    'route' => $route,
                ]);

                return;
            }

            $this->logger->warning('Initialization-or-JWT route denied', [
                'route' => $route,
            ]);

            $event->setController(fn () => new RedirectResponse($this->urlGenerator->generate('instance_login')));
        }
    }
}
