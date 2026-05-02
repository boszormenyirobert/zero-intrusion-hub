<?php

declare(strict_types=1);

namespace App\Tests\Service\Account\HUB;

use App\DTO\AccountContextDTO;
use App\DTO\BusinessSubscriptionDataDTO;
use App\DTO\AuthenticatedUserDTO;
use App\DTO\JwtContextDTO;
use App\Repository\UserRepository;
use App\Service\Account\HUB\AccountService;
use App\Service\Instance\HUB\JwtContextService;
use App\Service\User\BackendForwardingService;
use App\Entity\User;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class AccountServiceTest extends TestCase
{
    public function testResolveAccountContextReturnsNullWhenJwtPayloadMissingUsername(): void
    {
        $jwtContextService = $this->createMock(JwtContextService::class);
        $jwtContextService
            ->expects(self::once())
            ->method('build')
            ->willReturn(new JwtContextDTO(false, 'public-id', '', [
                'publicId' => 'public-id',
            ]));

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->expects(self::never())->method('findOneBy');

        $service = new AccountService(
            $jwtContextService,
            $userRepository,
            $this->createMock(LoggerInterface::class)
        );

        self::assertNull($service->resolveAccountContext(new Request()));
    }

    public function testResolveAccountContextUsesJwtPayloadInsteadOfJwtValidityFlag(): void
    {
        $jwtContextService = $this->createMock(JwtContextService::class);
        $jwtContextService
            ->expects(self::once())
            ->method('build')
            ->willReturn(new JwtContextDTO(false, 'public-id', 'user@example.test', [
                'username' => 'user@example.test',
                'publicId' => 'public-id',
            ]));

        $user = (new User())
            ->setEmail('user@example.test')
            ->setPublicId('public-id');

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['email' => 'user@example.test'])
            ->willReturn($user);

        $request = new Request();
        $request->attributes->set('_route', 'account');

        $service = new AccountService(
            $jwtContextService,
            $userRepository,
            $this->createMock(LoggerInterface::class)
        );

        $context = $service->resolveAccountContext($request);

        self::assertInstanceOf(AccountContextDTO::class, $context);
        self::assertSame('public-id', $context->user->publicId);
    }

    public function testResolveAccountContextReturnsUserWhenJwtIsValid(): void
    {
        $jwtContextService = $this->createMock(JwtContextService::class);
        $jwtContextService
            ->expects(self::once())
            ->method('build')
            ->willReturn(new JwtContextDTO(true, 'public-id', 'user@example.test', [
                'username' => 'user@example.test',
                'publicId' => 'public-id',
            ]));

        $user = (new User())
            ->setEmail('user@example.test')
            ->setPublicId('public-id');

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['email' => 'user@example.test'])
            ->willReturn($user);

        $request = new Request();
        $request->attributes->set('_route', 'account');

        $service = new AccountService(
            $jwtContextService,
            $userRepository,
            $this->createMock(LoggerInterface::class)
        );

        $context = $service->resolveAccountContext($request);

        self::assertInstanceOf(AccountContextDTO::class, $context);
        self::assertSame('public-id', $context->user->publicId);
        self::assertSame('user@example.test', $context->user->email);
    }

    public function testLoadBusinessSubscriptionReturnsEmptyDtoForInvalidBackendJson(): void
    {
        $forwardingService = $this->createMock(BackendForwardingService::class);
        $forwardingService
            ->expects(self::once())
            ->method('forwardRegistration')
            ->with([
                'get_registrated_business' => [
                    'publicId' => 'public-id',
                    'email' => 'user@example.test',
                ],
            ])
            ->willReturn(new JsonResponse('{invalid', 200, [], true));

        $service = new AccountService(
            $this->createMock(JwtContextService::class),
            $this->createMock(UserRepository::class),
            $this->createMock(LoggerInterface::class)
        );

        $result = $service->loadBusinessSubscription(
            $forwardingService,
            new AuthenticatedUserDTO('public-id', 'user@example.test')
        );

        self::assertSame([], $result->accounts);
        self::assertSame([], $result->businessSubscription);
    }

    public function testLoadBusinessSubscriptionMapsValidBackendPayload(): void
    {
        $forwardingService = $this->createMock(BackendForwardingService::class);
        $forwardingService
            ->expects(self::once())
            ->method('forwardRegistration')
            ->willReturn(new JsonResponse([
                'accounts' => [['id' => 1]],
                'businessSubscription' => ['id' => 9, 'pro' => true],
            ]));

        $service = new AccountService(
            $this->createMock(JwtContextService::class),
            $this->createMock(UserRepository::class),
            $this->createMock(LoggerInterface::class)
        );

        $result = $service->loadBusinessSubscription(
            $forwardingService,
            new AuthenticatedUserDTO('public-id', 'user@example.test')
        );

        self::assertSame([['id' => 1]], $result->accounts);
        self::assertSame(['id' => 9, 'pro' => true], $result->businessSubscription);
    }

    public function testBuildAccountViewDataResolvesSelectedSubscription(): void
    {
        $service = new AccountService(
            $this->createMock(JwtContextService::class),
            $this->createMock(UserRepository::class),
            $this->createMock(LoggerInterface::class)
        );

        $subscriptionData = BusinessSubscriptionDataDTO::fromArray([
            'accounts' => [['id' => 1]],
            'businessSubscription' => ['id' => 9, 'plus' => true],
        ]);

        $viewData = $service->buildAccountViewData(
            new AccountContextDTO(
                new JwtContextDTO(true, 'public-id', 'user@example.test', [
                    'username' => 'user@example.test',
                    'publicId' => 'public-id',
                ]),
                new AuthenticatedUserDTO('public-id', 'user@example.test')
            ),
            $subscriptionData,
            true
        );

        self::assertSame('plus', $viewData->businessSubscription?->subscription);
        self::assertSame(9, $viewData->businessSubscription?->id);
        self::assertSame('Business Plus', $viewData->pills['plus']);
    }
}