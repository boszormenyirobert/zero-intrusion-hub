<?php

namespace App\Service\Instance\HUB;

use App\Service\JWT\JwtService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

class JwtContextService
{
    public function __construct(
        private JwtService $jwtService,
        private LoggerInterface $logger
    ) {}

    public function build(Request $request): array
    {
        $token = $request->cookies->get('jwt_token');
        $route = $request->attributes->get('_route');

        if (!$token) {
            $this->logger->debug('JWT context built without JWT cookie', [
                'route' => $route,
            ]);

            return [
                'isJwtValid' => false,
                'userPublicId' => '',
                'userEmail' => '',
            ];
        }

        $payload = $this->jwtService->jwtValidation($token);
        $isJwtValid = $payload !== null;
        $userPublicId = $isJwtValid ? ($payload['publicId'] ?? '') : '';
        $userEmail = $isJwtValid ? ($payload['username'] ?? '') : '';

        if ($isJwtValid) {
            $this->logger->debug('JWT context built with valid JWT', [
                'route' => $route,
                'user_public_id' => $userPublicId,
                'user_email' => $userEmail,
            ]);
        } else {
            $this->logger->warning('JWT context built with invalid JWT', [
                'route' => $route,
            ]);
        }

        return [
            'isJwtValid' => $isJwtValid,
            'userPublicId' => $userPublicId,
            'userEmail' => $userEmail,
            'payload' => $payload,
        ];
    }
}