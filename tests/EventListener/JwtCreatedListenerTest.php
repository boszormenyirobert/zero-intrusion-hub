<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\EventListener\JwtCreatedListener;
use App\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class JwtCreatedListenerTest extends TestCase
{
    public function testJwtCreatedListenerAddsEmailAndPublicIdClaimsWhenAvailable(): void
    {
        $user = (new User())
            ->setEmail('user@example.test')
            ->setPublicId('public-id');

        $event = $this->createMock(JWTCreatedEvent::class);
        $event->expects(self::once())->method('getUser')->willReturn($user);
        $event->expects(self::once())->method('getData')->willReturn(['existing' => 'claim']);
        $event->expects(self::once())->method('setData')->with([
            'existing' => 'claim',
            'email' => 'user@example.test',
            'publicId' => 'public-id',
        ]);

        $listener = new JwtCreatedListener($this->createMock(LoggerInterface::class));
        $listener->onJWTCreated($event);
    }
}
