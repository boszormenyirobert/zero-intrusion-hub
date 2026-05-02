<?php

namespace App\Service\Instance\HUB;

use App\DTO\JwtContextDTO;
use App\Logger\LogTrace;
use App\Service\JWT\JwtService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

class JwtContextService
{
    public function __construct(
        private JwtService $jwtService,
        private LoggerInterface $logger
    ) {
    }

    public function build(Request $request): JwtContextDTO
    {
        $route = $request->attributes->get('_route');
        $token = $this->jwtService->extractTokenFromRequest($request);

        if (!$token) {
            $this->logger->debug('JWT context built without JWT cookie', [
                'route' => $route,
            ]);

            return JwtContextDTO::invalid();
        }

        $payload = $this->jwtService->extractPayloadFromRequest($request);
        $isJwtValid = $payload !== null;
        $userPublicId = $isJwtValid ? ($payload['publicId'] ?? '') : '';
        $userEmail = $isJwtValid ? ($payload['username'] ?? '') : '';

        if ($isJwtValid) {
            $this->logger->debug('JWT context built with valid JWT', [
                'route' => $route,
                'user_public_id_hash' => LogTrace::fingerprint($userPublicId),
                'user_email_hash' => LogTrace::fingerprint($userEmail),
            ]);
        } else {
            $this->logger->warning('JWT context built with invalid JWT', [
                'route' => $route,
            ]);
        }

        return new JwtContextDTO(
            $isJwtValid,
            $userPublicId,
            $userEmail,
            $payload
        );
    }
}
