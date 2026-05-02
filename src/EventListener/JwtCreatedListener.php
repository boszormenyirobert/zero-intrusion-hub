<?php

namespace App\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Psr\Log\LoggerInterface;

/**
 * Event listener for customizing JWT payloads on creation.
 *
 * Listens to the JWT creation event and adds user-specific data (email, publicId)
 * to the JWT payload if available. Used to enrich tokens with extra claims.
 */
#[AsEventListener(
    event: 'lexik_jwt_authentication.on_jwt_created',
    method: 'onJWTCreated'
)]
class JwtCreatedListener
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function onJWTCreated(JWTCreatedEvent $event): void
    {
        $user = $event->getUser();
        $data = $event->getData();

        if (method_exists($user, 'getEmail')) {
            $data['email'] = $user->getEmail();
        }
        if (method_exists($user, 'getPublicId')) {
            $data['publicId'] = $user->getPublicId();
        }

        $event->setData($data);
    }
}
