<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\KernelEvents;
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

class ExceptionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
    
        $message = 'Internal Server Error';
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
    
            case $exception instanceof HttpExceptionInterface:
            case $exception instanceof ClientExceptionInterface:
            case $exception instanceof ServerExceptionInterface:
            case $exception instanceof TransportExceptionInterface:
            case $exception instanceof RedirectionExceptionInterface:
            case $exception instanceof DecodingExceptionInterface:
                $message = $exception->getMessage();
                $statusCode = $exception->getCode() ?: 502;
                break;
    
            default:
                $message = $exception->getMessage();
                $statusCode = 500;
                break;
        }
    
        $response = new JsonResponse(['error' => $message], $statusCode);
        $event->setResponse($response);
    }    
}
