<?php

declare(strict_types=1);

namespace App\Tests\Service\JWT;

use App\Service\JWT\JwtService;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\Security\Core\User\UserInterface;

final class JwtServiceTest extends TestCase
{
    public function testExtractTokenFromRequestReturnsCookieValue(): void
    {
        $service = $this->createService();
        $request = new class () extends Request {
            public function getHost(): string
            {
                return '';
            }
        };
        $request->cookies->set('jwt_token', 'token-value');

        self::assertSame('token-value', $service->extractTokenFromRequest($request));
    }

    public function testGettersExposeConfiguredValues(): void
    {
        $service = $this->createService(jwtCookieName: 'custom_cookie', jwtTokenTtl: 7200, jwtClockSkew: 15);

        self::assertSame('custom_cookie', $service->getCookieName());
        self::assertSame(7200, $service->getTokenTtl());
        self::assertSame(15, $service->getClockSkew());
    }

    public function testExtractTokenFromRequestReturnsNullForEmptyCookie(): void
    {
        $service = $this->createService();
        $request = Request::create('/');
        $request->cookies->set('jwt_token', '');

        self::assertNull($service->extractTokenFromRequest($request));
    }

    public function testExtractPayloadFromRequestDelegatesToValidation(): void
    {
        $payload = [
            'iat' => time() - 1,
            'exp' => time() + 30,
        ];

        $encoder = $this->createMock(JWTEncoderInterface::class);
        $encoder->expects(self::once())->method('decode')->with('token-value')->willReturn($payload);

        $service = $this->createService(encoder: $encoder);
        $request = Request::create('/');
        $request->cookies->set('jwt_token', 'token-value');

        self::assertSame($payload, $service->extractPayloadFromRequest($request));
    }

    public function testJwtValidationReturnsNullForEmptyToken(): void
    {
        $encoder = $this->createMock(JWTEncoderInterface::class);
        $encoder->expects(self::never())->method('decode');

        $service = $this->createService(encoder: $encoder);

        self::assertNull($service->jwtValidation(null));
        self::assertNull($service->jwtValidation(''));
    }

    public function testJwtValidationReturnsPayloadForValidTemporalClaims(): void
    {
        $payload = [
            'username' => 'user@example.test',
            'publicId' => 'public-id',
            'iat' => time() - 10,
            'exp' => time() + 300,
        ];

        $encoder = $this->createMock(JWTEncoderInterface::class);
        $encoder
            ->expects(self::once())
            ->method('decode')
            ->with('valid-token')
            ->willReturn($payload);

        $service = $this->createService(encoder: $encoder);

        self::assertSame($payload, $service->jwtValidation('valid-token'));
    }

    public function testJwtValidationRejectsFutureIat(): void
    {
        $encoder = $this->createMock(JWTEncoderInterface::class);
        $encoder
            ->method('decode')
            ->willReturn([
                'iat' => time() + 1000,
                'exp' => time() + 2000,
            ]);

        $service = $this->createService(encoder: $encoder, jwtClockSkew: 0);

        self::assertNull($service->jwtValidation('future-token'));
    }

    public function testJwtValidationRejectsExpiredToken(): void
    {
        $encoder = $this->createMock(JWTEncoderInterface::class);
        $encoder
            ->method('decode')
            ->willReturn([
                'iat' => time() - 200,
                'exp' => time() - 100,
            ]);

        $service = $this->createService(encoder: $encoder, jwtClockSkew: 0);

        self::assertNull($service->jwtValidation('expired-token'));
    }

    public function testJwtValidationRejectsFutureNbf(): void
    {
        $encoder = $this->createMock(JWTEncoderInterface::class);
        $encoder
            ->method('decode')
            ->willReturn([
                'iat' => time() - 50,
                'exp' => time() + 300,
                'nbf' => time() + 100,
            ]);

        $service = $this->createService(encoder: $encoder, jwtClockSkew: 0);

        self::assertNull($service->jwtValidation('future-nbf-token'));
    }

    public function testJwtValidationReturnsNullWhenDecoderThrows(): void
    {
        $encoder = $this->createMock(JWTEncoderInterface::class);
        $encoder
            ->method('decode')
            ->willThrowException(new \RuntimeException('decode failed'));

        $service = $this->createService(encoder: $encoder);

        self::assertNull($service->jwtValidation('invalid-token'));
    }

    public function testJwtValidationRejectsNonArrayPayloadAndInvalidClaims(): void
    {
        $encoder = $this->createMock(JWTEncoderInterface::class);
        $encoder
            ->expects(self::exactly(4))
            ->method('decode')
            ->willReturnOnConsecutiveCalls(
                'not-an-array',
                ['iat' => time() - 10],
                ['exp' => time() + 10],
                ['iat' => time() + 10, 'exp' => time()]
            );

        $service = $this->createService(encoder: $encoder, jwtClockSkew: 0);

        self::assertNull($service->jwtValidation('scalar-payload'));
        self::assertNull($service->jwtValidation('missing-exp'));
        self::assertNull($service->jwtValidation('missing-iat'));
        self::assertNull($service->jwtValidation('iat-after-exp'));
    }

    public function testJwtValidationRejectsInvalidNbfClaimType(): void
    {
        $encoder = $this->createMock(JWTEncoderInterface::class);
        $encoder->method('decode')->willReturn([
            'iat' => time() - 10,
            'exp' => time() + 300,
            'nbf' => 'invalid',
        ]);

        $service = $this->createService(encoder: $encoder, jwtClockSkew: 0);

        self::assertNull($service->jwtValidation('invalid-nbf-token'));
    }

    public function testCreateAuthenticationCookieDelegatesToTokenManager(): void
    {
        $user = $this->createMock(UserInterface::class);
        $tokenManager = $this->createMock(JWTTokenManagerInterface::class);
        $tokenManager
            ->expects(self::once())
            ->method('create')
            ->with($user)
            ->willReturn('token-value');

        $service = $this->createService(tokenManager: $tokenManager);
        $cookie = $service->createAuthenticationCookie($user);

        self::assertSame('jwt_token', $cookie->getName());
        self::assertSame('token-value', $cookie->getValue());
        self::assertSame('/', $cookie->getPath());
    }

    public function testCreateCookieFromTokenUsesConfiguredCookieSettings(): void
    {
        $service = $this->createService(
            jwtCookieName: 'jwt_cookie',
            jwtTokenTtl: 60,
            jwtCookiePath: '/secure',
            jwtCookieSameSite: Cookie::SAMESITE_LAX,
            jwtCookieSecure: true,
            jwtCookieHttpOnly: false
        );

        $cookie = $service->createCookieFromToken('token-value');

        self::assertSame('jwt_cookie', $cookie->getName());
        self::assertSame('/secure', $cookie->getPath());
        self::assertTrue($cookie->isSecure());
        self::assertFalse($cookie->isHttpOnly());
        self::assertSame(Cookie::SAMESITE_LAX, $cookie->getSameSite());
    }

    public function testClearAuthenticationCookieAddsExpiredCookie(): void
    {
        $service = $this->createService();
        $response = new Response();

        $service->clearAuthenticationCookie($response);

        $cookies = $response->headers->getCookies();
        self::assertNotEmpty($cookies);
        self::assertSame('jwt_token', $cookies[array_key_last($cookies)]->getName());
        self::assertSame('', $cookies[array_key_last($cookies)]->getValue());
    }

    public function testClearAuthenticationCookieAlsoTargetsRequestHostWhenAvailable(): void
    {
        $service = $this->createService();
        $response = new Response();
        $request = Request::create('https://hub.example/account');

        $service->clearAuthenticationCookie($response, $request);

        $cookies = $response->headers->getCookies();
        self::assertCount(2, $cookies);
        self::assertSame('hub.example', $cookies[1]->getDomain());
    }

    private function createService(
        ?JWTEncoderInterface $encoder = null,
        ?JWTTokenManagerInterface $tokenManager = null,
        ?LoggerInterface $logger = null,
        string $jwtCookieName = 'jwt_token',
        int $jwtTokenTtl = 3600,
        int $jwtClockSkew = 30,
        string $jwtCookiePath = '/',
        string $jwtCookieSameSite = 'Strict',
        bool $jwtCookieSecure = false,
        bool $jwtCookieHttpOnly = true
    ): JwtService {
        return new JwtService(
            $encoder ?? $this->createMock(JWTEncoderInterface::class),
            $tokenManager ?? $this->createMock(JWTTokenManagerInterface::class),
            $jwtCookieName,
            $jwtTokenTtl,
            $jwtClockSkew,
            $jwtCookiePath,
            $jwtCookieSameSite,
            $jwtCookieSecure,
            $jwtCookieHttpOnly,
            $logger ?? $this->createMock(LoggerInterface::class)
        );
    }
}
