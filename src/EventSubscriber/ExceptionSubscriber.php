<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface as KernelHttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use App\Exception\EntityNotFoundException;
use App\Exception\InvalidPropertyException;
use App\Exception\InvalidInputException;
use App\Exception\MissingKeyException;

/**
 * Event subscriber for handling and formatting exceptions globally.
 *
 * Converts thrown exceptions into JSON HTTP responses with appropriate status codes and messages.
 * Handles custom domain exceptions and HTTP client exceptions for unified error output.
 */
class ExceptionSubscriber implements EventSubscriberInterface
{
    private const ERROR_INTERNAL_SERVER = 'Internal Server Error';
    private const ERROR_UPSTREAM_REQUEST = 'Upstream request failed';

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        $message = self::ERROR_INTERNAL_SERVER;
        $statusCode = 500;

        switch (true) {
            case $exception instanceof EntityNotFoundException:
                $message = 'Entity not found';
                $statusCode = 404;
                break;

            case $exception instanceof InvalidPropertyException:
                $message = 'Entity has invalid property value';
                $statusCode = 422;
                break;

            case $exception instanceof InvalidInputException:
                $message = 'Invalid input';
                $statusCode = 400;
                break;

            case $exception instanceof MissingKeyException:
                $message = 'Missing expected key in array';
                $statusCode = 400;
                break;

            case $exception instanceof KernelHttpExceptionInterface:
                $statusCode = $exception->getStatusCode();
                $message = $statusCode >= 500
                    ? self::ERROR_INTERNAL_SERVER
                    : $this->resolveSafeHttpMessage($exception->getMessage(), $statusCode);
                break;

            case $exception instanceof HttpExceptionInterface:
            case $exception instanceof ClientExceptionInterface:
            case $exception instanceof ServerExceptionInterface:
            case $exception instanceof TransportExceptionInterface:
            case $exception instanceof RedirectionExceptionInterface:
            case $exception instanceof DecodingExceptionInterface:
                $statusCode = $exception->getCode() ?: 502;
                $message = $this->resolveSafeUpstreamMessage($statusCode);
                break;

            default:
                $message = self::ERROR_INTERNAL_SERVER;
                $statusCode = 500;
                break;
        }

        $response = new JsonResponse(['error' => $message], $statusCode);
        $event->setResponse($response);
    }

    private function resolveSafeHttpMessage(string $message, int $statusCode): string
    {
        if ($message !== '') {
            return $message;
        }

        return $statusCode >= 400 && $statusCode < 500
            ? 'Request failed'
            : self::ERROR_INTERNAL_SERVER;
    }

    private function resolveSafeUpstreamMessage(int $statusCode): string
    {
        if ($statusCode >= 400 && $statusCode < 500) {
            return 'Request failed';
        }

        return self::ERROR_UPSTREAM_REQUEST;
    }
}
