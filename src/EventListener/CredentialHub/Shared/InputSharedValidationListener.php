<?php

namespace App\EventListener\CredentialHub\Shared;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use App\EventListener\ValidationListenerHelper;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 10)]
class InputSharedValidationListener
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

        if ($path === '/api/credential-hub/shared/registration/qr-identity' && $method === 'POST') {
            $errors = [];
            $data = ValidationListenerHelper::decodeJsonRequest($event, $this->logger);

            if ($data === null) {
                return;
            }

            $requiredFields = ['description', 'isNew', 'source', 'type', 'userName', 'userPassword', 'userPublicId'];

            ValidationListenerHelper::validateRequiredFields($data, $requiredFields, $errors);
            ValidationListenerHelper::validateDescription($data, $errors);
            ValidationListenerHelper::validateSource($data['source'] ?? '', 'extension', $errors);
            ValidationListenerHelper::validateUserPublicId($data, $errors);

            ValidationListenerHelper::validateNoControlChars($data, $requiredFields, $errors);

            // optional: domain || application
            if (array_key_exists('domain', $data)) {
                ValidationListenerHelper::validateDomain($data, true, $errors);
                ValidationListenerHelper::validateTargetId($data, $errors);
                ValidationListenerHelper::validateSource($data['type'] ?? '', 'registration-domain', $errors);
            }
            if (array_key_exists('application', $data)) {
                ValidationListenerHelper::validateApplication($data, $errors);
                ValidationListenerHelper::validateSource($data['type'] ?? '', 'registration-application', $errors);
            }

            if (!empty($errors)) {
                $event->setResponse(new JsonResponse([
                    'error' => 'Invalid input.',
                    'validation_errors' => $errors
                ], 400));
            }
            return;
        }

        if ($path === '/api/credential-hub/shared/registration/state'  && $method === 'POST') {
            $data = ValidationListenerHelper::decodeJsonRequest($event, $this->logger);

            if ($data === null) {
                return;
            }

            $requiredFields = ['processId', 'type'];
            $errors = [];
            ValidationListenerHelper::validateRequiredFields($data, $requiredFields, $errors);

            ValidationListenerHelper::validateSource($data['type'] ?? '', 'extension', $errors);
            ValidationListenerHelper::validateProcessId($data, $errors);

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
