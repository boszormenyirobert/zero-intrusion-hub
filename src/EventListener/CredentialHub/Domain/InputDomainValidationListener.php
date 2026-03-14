<?php

namespace App\EventListener\CredentialHub\Domain;

use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use App\EventListener\ValidationListenerHelper;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 10)]
class InputDomainValidationListener
{
    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $path = $request->getPathInfo();
        $method = $request->getMethod();

        // /api/credential-hub/domain/read/qr-identity endpoint validation
        // /api/credential-hub/domain/delete/qr-identity
        if ( ($path === '/api/credential-hub/domain/read/qr-identity' 
        ||    $path === '/api/credential-hub/domain/delete/qr-identity') && $method === 'POST') {
            $data = json_decode($request->getContent(), true);
            $requiredFields = ['domain', 'userPublicId'];
            $errors = [];
            ValidationListenerHelper::validateRequiredFields($data, $requiredFields, $errors);

            ValidationListenerHelper::validateDomain($data, $errors);
            ValidationListenerHelper::validateUserPublicId($data, $errors);
            if (!empty($errors)) {
                $event->setResponse(new JsonResponse([
                    'error' => 'Invalid input.',
                    'validation_errors' => $errors
                ], 400));
            }
            return;
        }

        // /api/credential-hub/domain/read/state endpoint validation
        if ($path === '/api/credential-hub/domain/read/state' && $method === 'POST') {
            $data = json_decode($request->getContent(), true);
            $requiredFields = ['domain', 'iv', 'processId', 'type'];
            $errors = [];
            ValidationListenerHelper::validateRequiredFields($data, $requiredFields, $errors);
            
            ValidationListenerHelper::validateDomain($data, $errors);
            ValidationListenerHelper::validateIv($data, $errors);
            ValidationListenerHelper::validateProcessId($data, $errors);
            ValidationListenerHelper::validateSource($data['type'], 'extension', $errors);

            if (!empty($errors)) {
                $event->setResponse(new JsonResponse([
                    'error' => 'Invalid input.',
                    'validation_errors' => $errors
                ], 400));
            }
            return;
        }

        // /api/credential-hub/domain/delete/state
        if ($path === '/api/credential-hub/domain/delete/state' && $method === 'POST') {
            $data = json_decode($request->getContent(), true);
            $requiredFields = ['processId', 'type'];
            $errors = [];
            ValidationListenerHelper::validateRequiredFields($data, $requiredFields, $errors);
            
            ValidationListenerHelper::validateProcessId($data, $errors);
            ValidationListenerHelper::validateSource($data['type'], 'extension', $errors);

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
