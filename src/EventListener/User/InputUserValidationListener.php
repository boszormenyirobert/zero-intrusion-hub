<?php

namespace App\EventListener\User;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use App\EventListener\ValidationListenerHelper;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 10)]
class InputUserValidationListener
{
    private const METHOD_POST = 'POST';
    private const PATH_API_LOGIN = '/api/user-login';
    private const PATH_API_LOGIN_CALLBACK = '/api/user-login/callback';
    private const PATH_API_LOGIN_NEW_QR = '/api/user-login/new-qr';
    private const PATH_API_LOGIN_CHECK = '/api/user-login/check';

    public function __construct(
        private LoggerInterface $logger
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $path = $request->getPathInfo();
        $method = $request->getMethod();

        if ($this->matches($path, $method, self::PATH_API_LOGIN)) {
            $this->handleApiLogin($event);
            return;
        }

        if ($this->matches($path, $method, self::PATH_API_LOGIN_CALLBACK)) {
            $this->handleLoginCallback($event);
            return;
        }

        if ($this->matches($path, $method, self::PATH_API_LOGIN_NEW_QR)
            || $this->matches($path, $method, self::PATH_API_LOGIN_CHECK)) {
            $this->handleDomainProcessPayload($event);
            return;
        }
    }

    private function handleApiLogin(RequestEvent $event): void
    {
        $header = $event->getRequest()->headers->get('x-client-auth');

        if (!$header) {
            $event->setResponse(new JsonResponse([
                'error' => 'Missing x-client-auth header!'
            ], 401));

            return;
        }

        $data = ValidationListenerHelper::decodeJsonRequest($event, $this->logger);

        if ($data === null) {
            return;
        }

        $errors = [];
        ValidationListenerHelper::validateRequiredFields($data, ['publicId', 'message', 'userPublicId'], $errors);
        ValidationListenerHelper::validatePrefix($data['publicId'] ?? null, 'cid_', 'publicId', $errors);
        ValidationListenerHelper::validatePrefix($data['message'] ?? null, 'ckey', 'message', $errors);

        $this->setValidationErrors($event, $errors);
    }

    private function handleLoginCallback(RequestEvent $event): void
    {
        $data = ValidationListenerHelper::decodeJsonRequest($event, $this->logger);

        if ($data === null) {
            return;
        }

        $errors = [];
        ValidationListenerHelper::validateRequiredFields($data, ['signature', 'publicId', 'email', 'processId'], $errors);
        ValidationListenerHelper::validateEmail($data, $errors);
        ValidationListenerHelper::validateProcessId($data, $errors);

        $this->setValidationErrors($event, $errors);
    }

    private function handleDomainProcessPayload(RequestEvent $event): void
    {
        $data = ValidationListenerHelper::decodeJsonRequest($event, $this->logger);

        if ($data === null) {
            return;
        }

        $errors = [];
        ValidationListenerHelper::validateRequiredFields($data, ['domainProcessId'], $errors);

        $this->setValidationErrors($event, $errors);
    }

    private function setValidationErrors(RequestEvent $event, array $errors): void
    {
        if (empty($errors)) {
            return;
        }

        $event->setResponse(new JsonResponse([
            'error' => 'Invalid input.',
            'validation_errors' => $errors
        ], 400));
    }

    private function matches(string $path, string $method, string $expectedPath): bool
    {
        return $path === $expectedPath && $method === self::METHOD_POST;
    }
}
