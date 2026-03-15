<?php

namespace App\EventListener\CredentialHub\Vault;

use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use App\EventListener\ValidationListenerHelper;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 10)]
class InputVaultValidationListener
{
    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $path = $request->getPathInfo();
        $method = $request->getMethod();

        // /api/credential-hub/vault/read/qr-identity
        if ( ($path === '/api/credential-hub/vault/read/qr-identity') && $method === 'POST') {
            $data = json_decode($request->getContent(), true);
            $requiredFields = ['domain', 'source', 'type'];
            $errors = [];
            ValidationListenerHelper::validateRequiredFields($data, $requiredFields, $errors);     
                   
            ValidationListenerHelper::validateSource($data['source'], 'extension', $errors);
            ValidationListenerHelper::validateSource($data['type'], 'applications', $errors);
        //    ValidationListenerHelper::validateUserPublicId($data, $errors);
            if (!empty($errors)) {
                $event->setResponse(new JsonResponse([
                    'error' => 'Invalid input.',
                    'validation_errors' => $errors
                ], 400));
            }
            return;
        }
        // api/credential-hub/vault/read/state
        if ( ($path === '/api/credential-hub/vault/read/state') && $method === 'POST') {
            $data = json_decode($request->getContent(), true);
            $requiredFields = ['domain', 'iv', 'processId', 'type'];
            $errors = [];
            ValidationListenerHelper::validateRequiredFields($data, $requiredFields, $errors);            
            
            // domain validation is unnecessary, it can be valid domain or empty
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

        // /api/credential-hub/vault/edit/qr-identity
        if ( ($path === '/api/credential-hub/vault/edit/qr-identity') && $method === 'POST') {
            $data = json_decode($request->getContent(), true);
            $requiredFields = [
                'application', 'description', 'source', 'targetId', 
                'type', 'userName', 'userPassword', 'userPublicId'];
            $errors = [];
            ValidationListenerHelper::validateRequiredFields($data, $requiredFields, $errors);            
            
            ValidationListenerHelper::validateSource($data['type'], 'update-applications', $errors);            
            ValidationListenerHelper::validateSource($data['source'], 'extension', $errors);            
            ValidationListenerHelper::validateUserPublicId($data, $errors);

            if (!empty($errors)) {
                $event->setResponse(new JsonResponse([
                    'error' => 'Invalid input.',
                    'validation_errors' => $errors
                ], 400));
            }
            return;
        }        

        // /api/credential-hub/vault/delete/qr-identity
        if ( ($path === '/api/credential-hub/vault/delete/qr-identity') && $method === 'POST') {
            $data = json_decode($request->getContent(), true);
            $requiredFields = ['source', 'targetId', 'type', 'userPublicId'];
            $errors = [];
            ValidationListenerHelper::validateRequiredFields($data, $requiredFields, $errors);            
            
            ValidationListenerHelper::validateSource($data['type'], 'delete-applications', $errors);            
            ValidationListenerHelper::validateSource($data['source'], 'extension', $errors);            
            ValidationListenerHelper::validateUserPublicId($data, $errors);

            if (!empty($errors)) {
                $event->setResponse(new JsonResponse([
                    'error' => 'Invalid input.',
                    'validation_errors' => $errors
                ], 400));
            }
            return;
        }
        
        // /api/credential-hub/vault/edit/state
        // /api/credential-hub/vault/delete/state
        if ( ($path === '/api/credential-hub/vault/edit/state' || $path === '/api/credential-hub/vault/delete/state') && $method === 'POST') {
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
