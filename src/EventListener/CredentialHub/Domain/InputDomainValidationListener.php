<?php

namespace App\EventListener\CredentialHub\Domain;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use App\EventListener\ValidationListenerHelper;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 10)]
class InputDomainValidationListener
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

        if ($path === '/api/credential-hub/domain/read/qr-identity' && $method === 'POST') {
            $data = ValidationListenerHelper::decodeJsonRequest($event, $this->logger);

            if ($data === null) {
                return;
            }

            $requiredFields = ['domain', 'userPublicId'];
            $errors = [];
            ValidationListenerHelper::validateRequiredFields($data, $requiredFields, $errors);

            ValidationListenerHelper::validateDomain($data, true, $errors);
            ValidationListenerHelper::validateUserPublicId($data, $errors);
            if (!empty($errors)) {
                $event->setResponse(new JsonResponse([
                    'error' => 'Invalid input.',
                    'validation_errors' => $errors
                ], 400));
            }
            return;
        }

        if ($path === '/api/credential-hub/domain/delete/qr-identity' && $method === 'POST') {
            $data = ValidationListenerHelper::decodeJsonRequest($event, $this->logger);

            if ($data === null) {
                return;
            }

            $requiredFields = ['domain', 'source', 'targetId', 'type', 'userPublicId'];
            $errors = [];
            ValidationListenerHelper::validateRequiredFields($data, $requiredFields, $errors);

            ValidationListenerHelper::validateDomain($data, true, $errors);
            ValidationListenerHelper::validateSource($data['source'] ?? '', 'extension', $errors);
            ValidationListenerHelper::validateTargetId($data, $errors);
            ValidationListenerHelper::validateSource($data['type'] ?? '', 'delete-domain', $errors);
            ValidationListenerHelper::validateUserPublicId($data, $errors);

            if (!empty($errors)) {
                $event->setResponse(new JsonResponse([
                    'error' => 'Invalid input.',
                    'validation_errors' => $errors
                ], 400));
            }
            return;
        }

        if ($path === '/api/credential-hub/domain/read/state' && $method === 'POST') {
            $data = ValidationListenerHelper::decodeJsonRequest($event, $this->logger);

            if ($data === null) {
                return;
            }

            $requiredFields = ['domain', 'iv', 'type'];
            $errors = [];
            ValidationListenerHelper::validateRequiredFields($data, $requiredFields, $errors);

            ValidationListenerHelper::validateDomain($data, true, $errors);
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

        if ($path === '/api/credential-hub/domain/delete/state' && $method === 'POST') {
            $data = ValidationListenerHelper::decodeJsonRequest($event, $this->logger);

            if ($data === null) {
                return;
            }

            $requiredFields = [ 'type'];
            $errors = [];
            ValidationListenerHelper::validateRequiredFields($data, $requiredFields, $errors);

            //     ValidationListenerHelper::validateProcessId($data, $errors);
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
