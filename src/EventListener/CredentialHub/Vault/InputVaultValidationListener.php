<?php

namespace App\EventListener\CredentialHub\Vault;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use App\EventListener\ValidationListenerHelper;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 10)]
class InputVaultValidationListener
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

        if (($path === '/api/credential-hub/vault/read/qr-identity') && $method === 'POST') {
            $data = ValidationListenerHelper::decodeJsonRequest($event, $this->logger);

            if ($data === null) {
                return;
            }

            $requiredFields = ['domain', 'source', 'type', 'userPublicId'];
            $errors = [];
            ValidationListenerHelper::validateRequiredFields($data, $requiredFields, $errors);

            ValidationListenerHelper::validateSource($data['source'] ?? '', 'extension', $errors);
            ValidationListenerHelper::validateSource($data['type'] ?? '', 'applications', $errors);
            ValidationListenerHelper::validateUserPublicId($data, $errors);
            if (!empty($errors)) {
                $event->setResponse(new JsonResponse([
                    'error' => 'Invalid input.',
                    'validation_errors' => $errors
                ], 400));
            }
            return;
        }

        if (($path === '/api/credential-hub/vault/read/state') && $method === 'POST') {
            $data = ValidationListenerHelper::decodeJsonRequest($event, $this->logger);

            if ($data === null) {
                return;
            }

            $requiredFields = ['domain', 'iv', 'processId', 'type'];
            $errors = [];
            ValidationListenerHelper::validateRequiredFields($data, $requiredFields, $errors);

            ValidationListenerHelper::validateDomain($data, false, $errors);
            ValidationListenerHelper::validateIv($data, $errors);
            ValidationListenerHelper::validateProcessId($data, $errors);
            ValidationListenerHelper::validateSource($data['type'] ?? '', 'extension', $errors);

            if (!empty($errors)) {
                $event->setResponse(new JsonResponse([
                    'error' => 'Invalid input.',
                    'validation_errors' => $errors
                ], 400));
            }
            return;
        }

        if (($path === '/api/credential-hub/vault/edit/qr-identity') && $method === 'POST') {
            $data = ValidationListenerHelper::decodeJsonRequest($event, $this->logger);

            if ($data === null) {
                return;
            }

            $requiredFields = [
                'application', 'description', 'source', 'targetId',
                'type', 'userName', 'userPassword', 'userPublicId'];
            $errors = [];
            ValidationListenerHelper::validateRequiredFields($data, $requiredFields, $errors);

            ValidationListenerHelper::validateApplication($data, $errors);
            ValidationListenerHelper::validateDescription($data, $errors);
            ValidationListenerHelper::validateTargetId($data, $errors);
            ValidationListenerHelper::validateSource($data['type'] ?? '', 'update-applications', $errors);
            ValidationListenerHelper::validateSource($data['source'] ?? '', 'extension', $errors);
            ValidationListenerHelper::validateUserPublicId($data, $errors);

            if (!empty($errors)) {
                $event->setResponse(new JsonResponse([
                    'error' => 'Invalid input.',
                    'validation_errors' => $errors
                ], 400));
            }
            return;
        }

        if (($path === '/api/credential-hub/vault/delete/qr-identity') && $method === 'POST') {
            $data = ValidationListenerHelper::decodeJsonRequest($event, $this->logger);

            if ($data === null) {
                return;
            }

            $requiredFields = ['source', 'targetId', 'type', 'userPublicId'];
            $errors = [];
            ValidationListenerHelper::validateRequiredFields($data, $requiredFields, $errors);

            ValidationListenerHelper::validateSource($data['source'] ?? '', 'extension', $errors);
            ValidationListenerHelper::validateTargetId($data, $errors);
            ValidationListenerHelper::validateSource($data['type'] ?? '', 'delete-applications', $errors);
            ValidationListenerHelper::validateUserPublicId($data, $errors);

            if (!empty($errors)) {
                $event->setResponse(new JsonResponse([
                    'error' => 'Invalid input.',
                    'validation_errors' => $errors
                ], 400));
            }
            return;
        }

        if (($path === '/api/credential-hub/vault/edit/state' || $path === '/api/credential-hub/vault/delete/state') && $method === 'POST') {
            $data = ValidationListenerHelper::decodeJsonRequest($event, $this->logger);

            if ($data === null) {
                return;
            }

            $requiredFields = ['processId', 'type'];
            $errors = [];
            ValidationListenerHelper::validateRequiredFields($data, $requiredFields, $errors);

            ValidationListenerHelper::validateProcessId($data, $errors);
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
