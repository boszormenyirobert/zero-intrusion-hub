<?php

namespace App\Controller\Shared;

use App\Controller\CredentialHub\BackendForwarder;
use App\Service\User\BackendForwardingService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Service\CredentialHub\SharedSSE;

abstract class AbstractBackendForwardingController extends AbstractController
{
    protected function forwardProcessRequest(
        Request $request,
        BackendForwardingService $backendForwardingService,
        LoggerInterface $logger,        
        string $process
    ): JsonResponse {
        return BackendForwarder::forward($request, $backendForwardingService, $logger, $process);
    }

    protected function forwardProcessWithHmac(
        Request $request,
        BackendForwardingService $backendForwardingService,
        LoggerInterface $logger,
        string $process,
        ?string $hmac,
        bool $decodeBody = true
    ): JsonResponse {
        return BackendForwarder::forwardWithHmac($request, $backendForwardingService, $logger, $process, $hmac, $decodeBody);
    }

    protected function forwardProcessSSE(string $key, SharedSSE $sharedSSE): StreamedResponse
    {
        return BackendForwarder::forwardSSE($key, $sharedSSE);
    }
}
