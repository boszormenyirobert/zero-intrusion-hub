<?php

namespace App\EventSubscriber;

use App\Service\Instance\HUB\InstanceRegistrationService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Psr\Log\LoggerInterface;

class InstanceRegistrationSubscriber implements EventSubscriberInterface
{

    public function __construct( 
        private LoggerInterface $logger, 
        private InstanceRegistrationService $service)
    {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $state = $this->service->getInitializationState();

        $event->getRequest()->attributes->set('InstanceRegistration', $state);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
        ];
    }
}