<?php

namespace App\EventListener\CredentialHub\OneTouch;

use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use App\EventListener\ValidationListenerHelper;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 10)]
class InputOneTouchValidationListener
{
    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $path = $request->getPathInfo();
        $method = $request->getMethod();

        // /api/credential-hub/one-touch/state
        if ($path === '/api/credential-hub/one-touch/state'  && $method === 'POST') {
            $data = json_decode($request->getContent(), true);
            $errors = [];

            $requiredFields = ['processId', 'type', 'iv'];                        

            ValidationListenerHelper::validateRequiredFields($data, $requiredFields, $errors);

            ValidationListenerHelper::validateSource($data['type'], 'extension', $errors);
            ValidationListenerHelper::validateProcessId($data, $errors);
            ValidationListenerHelper::validateIv($data, $errors);

            if (!empty($errors)) {
                $event->setResponse(new JsonResponse([
                    'error' => 'Invalid input.',
                    'validation_errors' => $errors
                ], 400));
            }
            return;
        }

        // /api/credential-hub/one-touch/qr-identity
        if ($path === '/api/credential-hub/one-touch/qr-identity' && $method === 'POST') {
            $errors = [];
            $data = json_decode($request->getContent(), true);  

            $requiredFields = ['source', 'type'];

            ValidationListenerHelper::validateRequiredFields($data, $requiredFields, $errors);

            ValidationListenerHelper::validateSource($data['source'], 'extension', $errors);
            ValidationListenerHelper::validateSource($data['type'], 'secure', $errors);

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
