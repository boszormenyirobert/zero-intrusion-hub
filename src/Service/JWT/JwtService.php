<?php

namespace App\Service\JWT;

use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use App\Logger\LogTrace;
use Psr\Log\LoggerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Lightweight service responsible for decoding and validating JWT tokens using the configured encoder.
 * It safely handles invalid or missing tokens, logs decode failures,
 * and returns the token payload as an array or `null` when validation is not successful.
 */
class JwtService
{
    public function __construct(
        private readonly JWTEncoderInterface $jwtEncoder,
        private readonly JWTTokenManagerInterface $jwtTokenManager,
        private readonly string $jwtCookieName,
        private readonly int $jwtTokenTtl,
        private readonly int $jwtClockSkew,
        private readonly string $jwtCookiePath,
        private readonly string $jwtCookieSameSite,
        private readonly bool $jwtCookieSecure,
        private readonly bool $jwtCookieHttpOnly,
        private LoggerInterface $logger
    ) {
    }

    public function getCookieName(): string
    {
        return $this->jwtCookieName;
    }

    public function getTokenTtl(): int
    {
        return $this->jwtTokenTtl;
    }

    public function getClockSkew(): int
    {
        return $this->jwtClockSkew;
    }

    public function createToken(UserInterface $user): string
    {
        return $this->jwtTokenManager->create($user);
    }

    public function extractTokenFromRequest(Request $request): ?string
    {
        $token = $request->cookies->get($this->jwtCookieName);

        return is_string($token) && $token !== '' ? $token : null;
    }

    public function extractPayloadFromRequest(Request $request): ?array
    {
        return $this->jwtValidation($this->extractTokenFromRequest($request));
    }

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

            if (!$this->validateTemporalClaims($payload)) {
                return null;
            }

            $this->logger->info('JWT validation succeeded', [
                'username_hash' => isset($payload['username']) && is_string($payload['username']) ? LogTrace::fingerprint($payload['username']) : null,
                'public_id_hash' => isset($payload['publicId']) && is_string($payload['publicId']) ? LogTrace::fingerprint($payload['publicId']) : null,
            ]);

            return $payload;

        } catch (\Throwable $e) {
            $this->logger->error('JWT decode failed', [
                'exception' => $e->getMessage()
            ]);

            return null;
        }
    }

    public function createCookieFromToken(string $token): Cookie
    {
        return new Cookie(
            $this->jwtCookieName,
            $token,
            time() + $this->jwtTokenTtl,
            $this->jwtCookiePath,
            null,
            $this->jwtCookieSecure,
            $this->jwtCookieHttpOnly,
            false,
            $this->jwtCookieSameSite
        );
    }

    public function createAuthenticationCookie(UserInterface $user): Cookie
    {
        return $this->createCookieFromToken($this->createToken($user));
    }

    public function clearAuthenticationCookie(Response $response, ?Request $request = null): void
    {
        $response->headers->setCookie($this->createExpiredCookie());

        if ($request !== null && $request->getHost() !== '') {
            $response->headers->setCookie($this->createExpiredCookie($request->getHost()));
        }
    }

    private function validateTemporalClaims(array $payload): bool
    {
        $now = time();

        if (!$this->isValidNumericClaim($payload, 'exp')) {
            $this->logger->warning('JWT validation failed because exp claim is missing or invalid');

            return false;
        }

        if (!$this->isValidNumericClaim($payload, 'iat')) {
            $this->logger->warning('JWT validation failed because iat claim is missing or invalid');

            return false;
        }

        $exp = (int) $payload['exp'];
        $iat = (int) $payload['iat'];

        if ($exp < ($now - $this->jwtClockSkew)) {
            $this->logger->warning('JWT validation failed because token is expired', [
                'exp' => $exp,
                'now' => $now,
                'clock_skew' => $this->jwtClockSkew,
            ]);

            return false;
        }

        if ($iat > ($now + $this->jwtClockSkew)) {
            $this->logger->warning('JWT validation failed because iat is in the future', [
                'iat' => $iat,
                'now' => $now,
                'clock_skew' => $this->jwtClockSkew,
            ]);

            return false;
        }

        if (isset($payload['nbf'])) {
            if (!$this->isValidNumericClaim($payload, 'nbf')) {
                $this->logger->warning('JWT validation failed because nbf claim is invalid');

                return false;
            }

            $nbf = (int) $payload['nbf'];

            if ($nbf > ($now + $this->jwtClockSkew)) {
                $this->logger->warning('JWT validation failed because token is not active yet', [
                    'nbf' => $nbf,
                    'now' => $now,
                    'clock_skew' => $this->jwtClockSkew,
                ]);

                return false;
            }
        }

        if ($iat > $exp) {
            $this->logger->warning('JWT validation failed because iat is later than exp', [
                'iat' => $iat,
                'exp' => $exp,
            ]);

            return false;
        }

        return true;
    }

    private function isValidNumericClaim(array $payload, string $claim): bool
    {
        if (!array_key_exists($claim, $payload)) {
            return false;
        }

        return is_int($payload[$claim]) || (is_string($payload[$claim]) && ctype_digit($payload[$claim]));
    }

    private function createExpiredCookie(?string $domain = null): Cookie
    {
        return new Cookie(
            $this->jwtCookieName,
            '',
            time() - 3600,
            $this->jwtCookiePath,
            $domain,
            $this->jwtCookieSecure,
            $this->jwtCookieHttpOnly,
            false,
            $this->jwtCookieSameSite
        );
    }
}
