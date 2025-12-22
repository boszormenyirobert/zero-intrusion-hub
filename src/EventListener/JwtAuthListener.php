<?php

namespace App\EventListener;

use App\Attribute\JwtRequired;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Psr\Log\LoggerInterface;


#[AsEventListener(event: KernelEvents::CONTROLLER)]
class JwtAuthListener
{
    public function __construct(
        private JWTEncoderInterface $jwtEncoder,
        private UrlGeneratorInterface $urlGenerator,
        private LoggerInterface $logger
    ) {}

    public function __invoke(ControllerEvent $event): void
    {
        $controller = $event->getController();

        if (!is_array($controller)) {
            return; // nem controller osztály
        }

        $method = new \ReflectionMethod($controller[0], $controller[1]);
        $attributes = $method->getAttributes(JwtRequired::class);

        if (empty($attributes)) {
            return; // nincs #[JwtRequired], nincs teendő
        }

        $request = $event->getRequest();
        $jwtToken = $request->cookies->get('jwt_token') ?? '';

        $isValid = false;

        try {
            $payload = $this->jwtEncoder->decode($jwtToken);

            $isValid = $payload !== false;

            if (!$isValid && !empty($attributes)) {
                $event->setController(function () {
                    return new RedirectResponse(
                        $this->urlGenerator->generate('instance_login')
                    );
                });
            }
        } catch (\Exception $e) {
            if (!empty($attributes)) {
                $event->setController(function () {
                    return new RedirectResponse(
                        $this->urlGenerator->generate('instance_login')
                    );
                });
            }
        }

        $request->attributes->set('is_jwt_valid', $isValid);
    }
}




