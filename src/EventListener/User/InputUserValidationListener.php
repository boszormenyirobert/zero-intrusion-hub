<?php

namespace App\EventListener\User;

use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use App\EventListener\ValidationListenerHelper;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 10)]
class InputUserValidationListener
{
    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $path = $request->getPathInfo();
        $method = $request->getMethod();

        // /api/user-login
        if ( $path === '/api/user-login'  && $method === 'POST') {
            $header = $request->headers->get('x-client-auth');
                
            if (!$header) {
                $event->setResponse(new JsonResponse([
                    'error' => 'Missing x-client-auth header!'
                ], 401));
                return;
            }

            $data = json_decode($request->getContent(), true);
            // publicId => corporate public id
            // domain control removed, local development with port usage is not alloed in the validator function

            $requiredFields = ['publicId', 'message'];
            $errors = [];
            ValidationListenerHelper::validateRequiredFields($data, $requiredFields, $errors);           
        //    ValidationListenerHelper::validateUserPublicId($data, $errors);
        
            $this->checkCorporate('cid_', $data['publicId'] ?? '', $errors);
            $this->checkCorporate('ckey', $data['message'] ?? '', $errors);

            if (!empty($errors)) {
                $event->setResponse(new JsonResponse([
                    'error' => 'Invalid input.',
                    'validation_errors' => $errors
                ], 400));
            }
            return;
        }

       // /api/user-login/callback
        if ( $path === '/api/user-login/callback'  && $method === 'POST') {

            $data = json_decode($request->getContent(), true);

            $requiredFields = ['signature', 'publicId', 'email', 'processId'];
            $errors = [];
            ValidationListenerHelper::validateRequiredFields($data, $requiredFields, $errors);           

            if (!empty($errors)) {
                $event->setResponse(new JsonResponse([
                    'error' => 'Invalid input.',
                    'validation_errors' => $errors
                ], 400));
            }
            return;
        }    
        
       // /api/user-login/new-qr , /api/user-login/check
        if ( ($path === '/api/user-login/new-qr' || $path === '/api/user-login/check') && $method === 'POST') {

            $data = json_decode($request->getContent(), true);

            $requiredFields = ['domainProcessId'];
            $errors = [];
            ValidationListenerHelper::validateRequiredFields($data, $requiredFields, $errors);           

            if (!empty($errors)) {
                $event->setResponse(new JsonResponse([
                    'error' => 'Invalid input.',
                    'validation_errors' => $errors
                ], 400));
            }
            return;
        }          
   }   

    private function checkCorporate(string $key, $value, array &$errors): JsonResponse
    {
        if (strncmp($value, $key, 4) === 0) {
            return new JsonResponse([
                'valid' => true,
                'message' => 'Key starts with ' . $key
            ]);
        }

        return new JsonResponse([
            'valid' => false,
            'message' => 'Invalid key prefix'
        ], 400); 
    }
}
