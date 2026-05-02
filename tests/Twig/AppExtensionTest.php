<?php

declare(strict_types=1);

namespace App\Tests\Twig;

use App\Repository\OwnClientRepository;
use App\Service\JWT\JwtService;
use App\Twig\AppExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class AppExtensionTest extends TestCase
{
    public function testGetGlobalsReflectsCurrentRequestJwtAndOwnClientPresence(): void
    {
        $requestStack = new RequestStack();
        $request = Request::create('/');
        $requestStack->push($request);

        $repository = $this->createMock(OwnClientRepository::class);
        $repository->expects(self::once())->method('findAll')->willReturn([new \stdClass()]);

        $jwtService = $this->createMock(JwtService::class);
        $jwtService->expects(self::once())->method('extractPayloadFromRequest')->with($request)->willReturn(['username' => 'user@example.test']);

        $extension = new AppExtension($requestStack, $repository, $jwtService);

        self::assertSame([
            'is_jwt_valid' => true,
            'own_client_exist' => true,
        ], $extension->getGlobals());
    }

    public function testGetGlobalsReturnsAnonymousDefaultsWithoutRequest(): void
    {
        $repository = $this->createMock(OwnClientRepository::class);
        $repository->expects(self::once())->method('findAll')->willReturn([]);

        $jwtService = $this->createMock(JwtService::class);
        $jwtService->expects(self::never())->method('extractPayloadFromRequest');

        $extension = new AppExtension(new RequestStack(), $repository, $jwtService);

        self::assertSame([
            'is_jwt_valid' => false,
            'own_client_exist' => false,
        ], $extension->getGlobals());
    }
}
