<?php

namespace App\EventListener;

use App\Attribute\ExtensionAuthRequired;
use App\Logger\LogTrace;
use App\Service\Security\ExtensionAuthGuard;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::CONTROLLER)]
class ExtensionAuthListener
{
    public function __construct(
        private ExtensionAuthGuard $extensionAuthGuard,
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
        $attributes = $method->getAttributes(ExtensionAuthRequired::class);

        if (empty($attributes)) {
            return;
        }

        $request = $event->getRequest();
        $header = $this->extensionAuthGuard->resolveHeader($request);
        $route = $request->attributes->get('_route');

        if ($header === null) {
            $this->logger->warning('Protected extension-auth route denied because header is missing', [
                'route' => $route,
            ]);

            $event->setController(fn () => $this->extensionAuthGuard->createMissingHeaderResponse());

            return;
        }

        $this->extensionAuthGuard->storeValidatedHeader($request, $header);

        $this->logger->info('Protected extension-auth route accepted', [
            'route' => $route,
            'extension_auth_hash' => LogTrace::fingerprint($header),
        ]);
    }
}
