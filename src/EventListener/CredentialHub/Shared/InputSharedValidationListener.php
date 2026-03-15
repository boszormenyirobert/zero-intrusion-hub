<?php

namespace App\EventListener\CredentialHub\Shared;

use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use App\EventListener\ValidationListenerHelper;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 10)]
class InputSharedValidationListener
{
    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $path = $request->getPathInfo();
        $method = $request->getMethod();

        // /api/credential-hub/shared/registration/state
        if ($path === '/api/credential-hub/shared/registration/state'  && $method === 'POST') {
            $data = json_decode($request->getContent(), true);
            $requiredFields = ['processId', 'type'];                        
            $errors = [];
            ValidationListenerHelper::validateRequiredFields($data, $requiredFields, $errors);
            
            ValidationListenerHelper::validateSource($data['type'], 'extension', $errors);
            ValidationListenerHelper::validateProcessId($data, $errors);

            if (!empty($errors)) {
                $event->setResponse(new JsonResponse([
                    'error' => 'Invalid input.',
                    'validation_errors' => $errors
                ], 400));
            }
            return;
        }

        // /api/credential-hub/shared/registration/qr-identity
        if ($path === '/api/credential-hub/shared/registration/qr-identity' && $method === 'POST') {
            $errors = [];
            $data = json_decode($request->getContent(), true);
            // optional: domain || application => domain credentail or "vault" credential
            if (array_key_exists('domain', $data)) {
                ValidationListenerHelper::validateDomain($data, $errors);
            }
            if (array_key_exists('application', $data)) {
                // ValidationListenerHelper::validateApplication($data, $errors);
            }      

            $requiredFields = ['description', 'isNew', 'source', 'type', 'userName', 'userPassword'];

            ValidationListenerHelper::validateNoControlChars($data,$requiredFields,$errors);            
            ValidationListenerHelper::validateRequiredFields($data, $requiredFields, $errors);
            
        //    ValidationListenerHelper::validateUserPublicId($data, $errors);
            ValidationListenerHelper::validateSource($data['source'], 'extension', $errors);
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
