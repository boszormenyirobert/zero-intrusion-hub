<?php

namespace App\Service\JWT;

use Psr\Log\LoggerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;

class JwtService
{
    public function __construct(
        private JWTEncoderInterface $jwtEncoder,
        private LoggerInterface $logger
    ) {}

    public function jwtValidation(?string $token): array|false
    {
        if (!$token) {
            return false;
        }

        try {
            $payload = $this->jwtEncoder->decode($token);

            return $payload ?: false;

        } catch (\Throwable $e) {
            $this->logger->warning('JWT decode failed', [
                'exception' => $e,
                'has_token' => $token !== null
            ]);

            return false;
        }
    }
}