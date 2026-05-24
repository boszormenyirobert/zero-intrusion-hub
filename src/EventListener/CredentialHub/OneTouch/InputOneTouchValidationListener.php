<?php

namespace App\EventListener\CredentialHub\OneTouch;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use App\EventListener\ValidationListenerHelper;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 10)]
class InputOneTouchValidationListener
{
    public function __construct(
        private LoggerInterface $logger
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $path = $request->getPathInfo();
        $method = $request->getMethod();

        if ($path === '/api/credential-hub/one-touch/qr-identity' && $method === 'POST') {
            $errors = [];
            $data = ValidationListenerHelper::decodeJsonRequest($event, $this->logger);

            if ($data === null) {
                return;
            }

            $requiredFields = ['source', 'type'];

            ValidationListenerHelper::validateRequiredFields($data, $requiredFields, $errors);

            ValidationListenerHelper::validateSource($data['source'] ?? '', 'extension', $errors);
            ValidationListenerHelper::validateSource($data['type'] ?? '', 'secure', $errors);

            if (!empty($errors)) {
                $event->setResponse(new JsonResponse([
                    'error' => 'Invalid input.',
                    'validation_errors' => $errors
                ], 400));
            }
            return;
        }

        if ($path === '/api/credential-hub/one-touch/state'  && $method === 'POST') {
            $data = ValidationListenerHelper::decodeJsonRequest($event, $this->logger);

            if ($data === null) {
                return;
            }

            $errors = [];

            $requiredFields = ['iv', 'type'];

            ValidationListenerHelper::validateRequiredFields($data, $requiredFields, $errors);

            ValidationListenerHelper::validateIv($data, $errors);
            //    ValidationListenerHelper::validateProcessId($data, $errors);
            ValidationListenerHelper::validateSource($data['type'] ?? '', 'extension', $errors);

            if (!empty($errors)) {
                $event->setResponse(new JsonResponse([
                    'error' => 'Invalid input.',
                    'validation_errors' => $errors
                ], 400));
            }
            return;
        }
    }
}
