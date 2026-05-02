<?php

declare(strict_types=1);

namespace App\Tests\Service\Instance\HUB;

use App\DTO\CorporateDataDTO;
use App\DTO\MenuAvailabilityDTO;
use App\DTO\QrCodeResponseDTO;
use App\DTO\WhitelistedUserInputDTO;
use App\Entity\InstanceSettings;
use App\Form\WhiteListedUserType;
use App\Repository\InstanceSettingsRepository;
use App\Service\Corporate\SubscriptionService;
use App\Service\Device\Identity\FirstSecretInstanceSettingsHandler;
use App\Service\Instance\HUB\InstanceRegistrationFollowUpHandler;
use App\Service\Instance\HUB\InstanceRegistrationService;
use App\Service\Instance\HUB\InstanceService;
use App\Service\Instance\HUB\InternalInstanceRegistrationHandler;
use App\Service\Instance\HUB\JwtContextService;
use App\Service\Instance\HUB\RegistrationMenuAvailabilityService;
use App\Service\Instance\HUB\SettingsFormHandler;
use App\Service\User\Registration\HUB\RegistrationService;
use App\Service\User\UserQrCodeService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\FormFactoryBuilder;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;

final class AdditionalInstanceServicesTest extends TestCase
{
    public function testInstanceServiceBuildsHomeSettingsAndUsersViewData(): void
    {
        $request = Request::create('/');
        $jwtContextService = $this->createMock(JwtContextService::class);
        $jwtContextService->expects(self::exactly(4))->method('build')->willReturn(new \App\DTO\JwtContextDTO(true, 'public-id', 'user@example.test'));

        $availabilityService = $this->createMock(RegistrationMenuAvailabilityService::class);
        $availabilityService->expects(self::once())->method('getAvailability')->with($request)->willReturn(new MenuAvailabilityDTO(true, false, true));

        $service = new InstanceService($jwtContextService, $availabilityService);

        $home = $service->buildHomeViewData($request);
        $settings = $service->buildSettingsViewData($request, new MenuAvailabilityDTO(true, true, false), new FormView());
        $users = $service->buildUsersViewData($request, new MenuAvailabilityDTO(false, true, true), new FormView(), [new WhitelistedUserInputDTO()]);

        self::assertTrue($home->isJwtValid);
        self::assertTrue($settings->isJwtValid);
        self::assertTrue($users->isJwtValid);
        self::assertSame('public-id', $service->buildJwtContext($request)->userPublicId);
    }

    public function testFirstSecretInstanceSettingsHandlerPersistsPublicIdOnlyWhenInitializationIsActive(): void
    {
        $settings = (new InstanceSettings())
            ->setInitialization(true)
            ->setPublicId(null);

        $repository = $this->createMock(InstanceSettingsRepository::class);
        $repository->expects(self::once())->method('findCurrentSettings')->willReturn($settings);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')->with($settings);
        $entityManager->expects(self::once())->method('flush');

        $handler = new FirstSecretInstanceSettingsHandler($repository, $entityManager, $this->createMock(LoggerInterface::class));
        $handler->handle('registrator-public-id');

        self::assertSame('registrator-public-id', $settings->getPublicId());
    }

    public function testFirstSecretInstanceSettingsHandlerIgnoresInactiveOrMissingData(): void
    {
        $settings = (new InstanceSettings())->setInitialization(false);

        $repository = $this->createMock(InstanceSettingsRepository::class);
        $repository->expects(self::once())->method('findCurrentSettings')->willReturn($settings);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');

        $handler = new FirstSecretInstanceSettingsHandler($repository, $entityManager, $this->createMock(LoggerInterface::class));
        $handler->handle('');

        self::assertNull($settings->getPublicId());
    }

    public function testSettingsFormHandlerReturnsFalseWhenFormIsNotSubmittable(): void
    {
        $form = $this->createMock(FormInterface::class);
        $form->expects(self::once())->method('isSubmitted')->willReturn(false);

        $handler = new SettingsFormHandler(
            $this->createMock(InstanceSettingsRepository::class),
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(LoggerInterface::class)
        );

        self::assertFalse($handler->handle($form));
    }

    public function testSettingsFormHandlerThrowsWhenSettingsAreMissing(): void
    {
        $form = $this->createSettingsFormMock(true, true, true);
        $repository = $this->createMock(InstanceSettingsRepository::class);
        $repository->expects(self::once())->method('findCurrentSettings')->willReturn(null);

        $handler = new SettingsFormHandler(
            $repository,
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(LoggerInterface::class)
        );

        $this->expectException(\LogicException::class);
        $handler->handle($form);
    }

    public function testSettingsFormHandlerPersistsInvertedInitializationState(): void
    {
        $settings = (new InstanceSettings())->setInitialization(true);
        $form = $this->createSettingsFormMock(true, true, false);

        $repository = $this->createMock(InstanceSettingsRepository::class);
        $repository->expects(self::once())->method('findCurrentSettings')->willReturn($settings);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')->with($settings);
        $entityManager->expects(self::once())->method('flush');

        $handler = new SettingsFormHandler($repository, $entityManager, $this->createMock(LoggerInterface::class));

        self::assertTrue($handler->handle($form));
        self::assertTrue((bool) $settings->isInitialization());
    }

    public function testRegistrationServiceBuildsRegistrationViewData(): void
    {
        $request = Request::create('/user-registration');
        $request->attributes->set('_route', 'user-registration');

        $qrCodeService = $this->createMock(UserQrCodeService::class);
        $qrCodeService
            ->expects(self::once())
            ->method('getQrCode')
            ->with('user_registration')
            ->willReturn(QrCodeResponseDTO::fromArray([
                'registrationProcessId' => 'registration-123',
                'qrCode' => 'qr-code',
            ]));

        $availabilityService = $this->createMock(RegistrationMenuAvailabilityService::class);
        $availabilityService->expects(self::once())->method('getAvailability')->with($request)->willReturn(new MenuAvailabilityDTO(true, true, true));

        $service = new RegistrationService($this->createMock(LoggerInterface::class), $qrCodeService, $availabilityService);
        $viewData = $service->buildRegistrationViewData($request);

        self::assertSame('registration', $viewData->action);
        self::assertTrue($viewData->menuItemInstanceRegistration);
        self::assertSame('registration-123', $viewData->qrCode->getRegistrationProcessId());
    }

    public function testInstanceRegistrationServiceCreatesDefaultSettingsWhenMissing(): void
    {
        $repository = $this->createMock(InstanceSettingsRepository::class);
        $repository->expects(self::exactly(3))->method('findCurrentSettings')->willReturnOnConsecutiveCalls(null, (new InstanceSettings())->setInitialization(true), (new InstanceSettings())->setInitialization(true));

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')->with(self::isInstanceOf(InstanceSettings::class));
        $entityManager->expects(self::once())->method('flush');

        $service = new InstanceRegistrationService($this->createMock(LoggerInterface::class), $repository, $entityManager);

        self::assertTrue($service->ensureInitialized());
        self::assertTrue($service->getInitializationState());
    }

    public function testInstanceRegistrationServiceReturnsExistingInitializationState(): void
    {
        $settings = (new InstanceSettings())->setInitialization(false);
        $repository = $this->createMock(InstanceSettingsRepository::class);
        $repository->expects(self::exactly(3))->method('findCurrentSettings')->willReturn($settings);

        $service = new InstanceRegistrationService(
            $this->createMock(LoggerInterface::class),
            $repository,
            $this->createMock(EntityManagerInterface::class)
        );

        self::assertFalse($service->ensureInitialized());
        self::assertFalse($service->getInitializationState());
    }

    public function testInstanceRegistrationServiceReturnsFalseWhenSettingsStillMissingAfterInitializationAttempt(): void
    {
        $repository = $this->createMock(InstanceSettingsRepository::class);
        $repository->expects(self::exactly(2))->method('findCurrentSettings')->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')->with(self::isInstanceOf(InstanceSettings::class));
        $entityManager->expects(self::once())->method('flush');

        $service = new InstanceRegistrationService(
            $this->createMock(LoggerInterface::class),
            $repository,
            $entityManager
        );

        self::assertFalse($service->getInitializationState());
    }

    public function testInstanceRegistrationFollowUpHandlerHandlesSuccessfulSubmission(): void
    {
        $data = CorporateDataDTO::fromArray([
            'domain' => 'https://example.test',
            'callbackUserLogin' => 'https://example.test/api/user-login/callback',
            'callbackUserRegistration' => 'https://example.test/api/registration/callback',
            'corporateId' => 'corporate-id',
        ]);

        $form = $this->createMock(FormInterface::class);
        $form->expects(self::once())->method('isSubmitted')->willReturn(true);
        $form->expects(self::once())->method('isValid')->willReturn(true);
        $form->expects(self::once())->method('getData')->willReturn($data);

        $subscriptionService = $this->createMock(SubscriptionService::class);
        $subscriptionService->expects(self::once())->method('updateOwnClient')->with($data);
        $subscriptionService->expects(self::once())->method('finalizeSubscription')->with($data);

        $handler = new InstanceRegistrationFollowUpHandler($subscriptionService, $this->createMock(LoggerInterface::class));

        self::assertTrue($handler->handle($form));
    }

    public function testInstanceRegistrationFollowUpHandlerReturnsFalseWhenFormIsNotSubmittable(): void
    {
        $form = $this->createMock(FormInterface::class);
        $form->expects(self::once())->method('isSubmitted')->willReturn(false);
        $form->expects(self::never())->method('isValid');

        $subscriptionService = $this->createMock(SubscriptionService::class);
        $subscriptionService->expects(self::never())->method('updateOwnClient');
        $subscriptionService->expects(self::never())->method('finalizeSubscription');

        $handler = new InstanceRegistrationFollowUpHandler($subscriptionService, $this->createMock(LoggerInterface::class));

        self::assertFalse($handler->handle($form));
    }

    public function testInternalInstanceRegistrationHandlerReturnsNullWhenSettingsMissingOrPublicIdEmpty(): void
    {
        $repository = $this->createMock(InstanceSettingsRepository::class);
        $repository->expects(self::exactly(2))->method('findCurrentSettings')->willReturnOnConsecutiveCalls(null, (new InstanceSettings())->setInitialization(true));

        $subscriptionService = $this->createMock(SubscriptionService::class);
        $subscriptionService->expects(self::never())->method('getSubscriptionData');

        $handler = new InternalInstanceRegistrationHandler($repository, $subscriptionService, $this->createMock(LoggerInterface::class));

        $form = $this->createMock(FormInterface::class);
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);

        self::assertNull($handler->handle($form, Request::create('https://hub.example/instance-registration')));
        self::assertNull($handler->handle($form, Request::create('https://hub.example/instance-registration')));
    }

    public function testInternalInstanceRegistrationHandlerReturnsNullWhenFormIsNotSubmitted(): void
    {
        $repository = $this->createMock(InstanceSettingsRepository::class);
        $repository->expects(self::never())->method('findCurrentSettings');

        $subscriptionService = $this->createMock(SubscriptionService::class);
        $subscriptionService->expects(self::never())->method('getSubscriptionData');

        $handler = new InternalInstanceRegistrationHandler($repository, $subscriptionService, $this->createMock(LoggerInterface::class));

        $form = $this->createMock(FormInterface::class);
        $form->expects(self::once())->method('isSubmitted')->willReturn(false);
        $form->expects(self::never())->method('isValid');

        self::assertNull($handler->handle($form, Request::create('https://hub.example/instance-registration')));
    }

    public function testInternalInstanceRegistrationHandlerFinalizesRegistrationWhenCorporateIdExists(): void
    {
        $settings = (new InstanceSettings())->setInitialization(true)->setPublicId('public-id');

        $repository = $this->createMock(InstanceSettingsRepository::class);
        $repository->expects(self::once())->method('findCurrentSettings')->willReturn($settings);

        $subscriptionPayload = \App\DTO\BackendPayloadDTO::fromArray(['corporate_id' => 'corporate-id']);
        $subscriptionService = $this->createMock(SubscriptionService::class);
        $subscriptionService
            ->expects(self::once())
            ->method('getSubscriptionData')
            ->with('getIdentity', 'businessPro', 'internal', 'public-id')
            ->willReturn($subscriptionPayload);
        $subscriptionService->expects(self::exactly(2))->method('updateOwnClient')->with(self::callback(static function (CorporateDataDTO $dto): bool {
            return $dto->domain === 'https://hub.example'
                && $dto->callbackUserLogin === 'https://hub.example/api/user-login/callback'
                && $dto->callbackUserRegistration === 'https://hub.example/api/registration/callback'
                && $dto->corporateId === 'corporate-id';
        }));
        $subscriptionService->expects(self::exactly(2))->method('finalizeSubscription')->with(self::isInstanceOf(CorporateDataDTO::class));

        $form = $this->createMock(FormInterface::class);
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);

        \Symfony\Component\HttpFoundation\Request::setTrustedHosts(['^hub\.example$']);
        $handler = new InternalInstanceRegistrationHandler($repository, $subscriptionService, $this->createMock(LoggerInterface::class));
        $result = $handler->handle($form, Request::create('https://hub.example/instance-registration'));

        self::assertSame($subscriptionPayload, $result);
        self::assertTrue($handler->finalizeRegistration('corporate-id', Request::create('https://hub.example/instance-registration')));
    }

    public function testInternalInstanceRegistrationHandlerReturnsPayloadWhenCorporateIdMissing(): void
    {
        $settings = (new InstanceSettings())->setInitialization(true)->setPublicId('public-id');

        $repository = $this->createMock(InstanceSettingsRepository::class);
        $repository->expects(self::once())->method('findCurrentSettings')->willReturn($settings);

        $subscriptionPayload = \App\DTO\BackendPayloadDTO::fromArray(['status' => 'pending']);
        $subscriptionService = $this->createMock(SubscriptionService::class);
        $subscriptionService
            ->expects(self::once())
            ->method('getSubscriptionData')
            ->with('getIdentity', 'businessPro', 'internal', 'public-id')
            ->willReturn($subscriptionPayload);
        $subscriptionService->expects(self::never())->method('updateOwnClient');
        $subscriptionService->expects(self::never())->method('finalizeSubscription');

        $form = $this->createMock(FormInterface::class);
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);

        $handler = new InternalInstanceRegistrationHandler($repository, $subscriptionService, $this->createMock(LoggerInterface::class));
        $result = $handler->handle($form, Request::create('https://hub.example/instance-registration'));

        self::assertSame($subscriptionPayload, $result);
    }

    private function createSettingsFormMock(bool $submitted, bool $valid, bool $initialization): FormInterface
    {
        $data = new \App\DTO\InstanceSettingsInputDTO();
        $data->initialization = $initialization;

        $field = $this->createMock(FormInterface::class);
        $field->method('getData')->willReturn($initialization);

        $form = $this->createMock(FormInterface::class);
        $form->method('isSubmitted')->willReturn($submitted);
        $form->method('isValid')->willReturn($valid);
        $form->method('getData')->willReturn($data);
        $form->method('get')->with('initialization')->willReturn($field);

        return $form;
    }
}
