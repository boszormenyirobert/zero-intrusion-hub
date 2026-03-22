<?php

namespace App\Service\JWT;

use Psr\Log\LoggerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;

/**
 * Lightweight service responsible for decoding and validating JWT tokens using the configured encoder.
 * It safely handles invalid or missing tokens, logs decode failures, 
 * and returns the token payload as an array or `null` when validation is not successful.
 */
class JwtService
{
    public function __construct(
        private readonly JWTEncoderInterface $jwtEncoder,
        private LoggerInterface $logger
    ) {}

    public function jwtValidation(?string $token): ?array
    {
        if (empty($token)) {
            return null;
        }

        try {
            $payload = $this->jwtEncoder->decode($token);

            return is_array($payload) ? $payload : null;

        } catch (\Throwable $e) {
            $this->logger->warning('JWT decode failed', [
                'exception' => $e->getMessage()
            ]);

            return null;
        }
    }
}