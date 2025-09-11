<?php

namespace App\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Psr\Log\LoggerInterface;

#[AsEventListener(event: JWTCreatedEvent::class, method: 'onJWTCreated')]
class JwtCreatedListener
{
    public function __construct(
        private LoggerInterface $logger
    ) {}

    public function onJWTCreated(JWTCreatedEvent $event): void
    {
        $user = $event->getUser();
        $data = $event->getData();

        if ($user instanceof \App\Entity\User) {
            $data['email'] = $user->getEmail();
            $data['publicId'] = $user->getPublicId();
        }
        
        $this->logger->critical(json_encode($data));

        $event->setData($data);
    }
}
