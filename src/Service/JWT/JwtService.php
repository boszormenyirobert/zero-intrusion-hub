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
            $this->logger->info('JWT validation skipped because token is empty');

            return null;
        }

        try {
            $payload = $this->jwtEncoder->decode($token);

            if (!is_array($payload)) {
                $this->logger->warning('JWT validation returned non-array payload');

                return null;
            }

            $this->logger->info('JWT validation succeeded', [
                'username' => $payload['username'] ?? null,
                'public_id' => $payload['publicId'] ?? null,
            ]);

            return $payload;

        } catch (\Throwable $e) {
            $this->logger->warning('JWT decode failed', [
                'exception' => $e->getMessage()
            ]);

            return null;
        }
    }
}