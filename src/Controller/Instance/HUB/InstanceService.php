<?php

namespace App\Controller\Instance\HUB;

use App\Service\JWT\JwtService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

class InstanceService
{
    public function __construct(
        private JwtService $jwtService,
        private LoggerInterface $logger,
    ) {}

    public function buildHomeViewData(Request $request, bool $menuItemInstanceRegistration): array
    {
        $jwtContext = $this->buildJwtContext($request);

        return [
            'is_jwt_valid' => $jwtContext['isJwtValid'],
            'user' => [
                'userPublicId' => $jwtContext['userPublicId'],
                'userEmail' => $jwtContext['userEmail'],
            ],
            'menuItem_instanceRegistration' => $menuItemInstanceRegistration,
        ];
    }

    private function buildJwtContext(Request $request): array
    {
        $token = $request->cookies->get('jwt_token');

        if (!$token) {
            $this->logger->info('Home page accessed without JWT cookie', [
                'route' => 'home',
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
            $this->logger->info('Home page accessed with valid JWT', [
                'route' => 'home',
                'user_public_id' => $userPublicId,
                'user_email' => $userEmail,
            ]);
        } else {
            $this->logger->warning('Home page accessed with invalid JWT', [
                'route' => 'home',
            ]);
        }

        return [
            'isJwtValid' => $isJwtValid,
            'userPublicId' => $userPublicId,
            'userEmail' => $userEmail,
        ];
    }
}