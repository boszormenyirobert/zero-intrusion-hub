<?php

declare(strict_types=1);

namespace App\Tests\Service\Business\HUB;

use App\DTO\AuthenticatedUserDTO;
use App\DTO\BackendPayloadDTO;
use App\DTO\BusinessContextDTO;
use App\DTO\BusinessFormsDTO;
use App\DTO\BusinessRequestDTO;
use App\DTO\JwtContextDTO;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\Business\HUB\BusinessService;
use App\Service\Corporate\SubscriptionService;
use App\Service\Instance\HUB\JwtContextService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;

final class BusinessServiceTest extends TestCase
{
    public function testResolveBusinessContextReturnsNullWhenJwtPayloadMissing(): void
    {
        $jwtContextService = $this->createMock(JwtContextService::class);
        $jwtContextService
            ->expects(self::once())
            ->method('build')
            ->willReturn(JwtContextDTO::invalid());

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->expects(self::never())->method('findOneBy');

        $request = new Request();
        $request->attributes->set('_route', 'business');

        $service = $this->createService(
            jwtContextService: $jwtContextService,
            userRepository: $userRepository
        );

        self::assertNull($service->resolveBusinessContext($request));
    }

    public function testResolveBusinessContextReturnsUserWhenJwtPayloadValid(): void
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
        $request->attributes->set('_route', 'business');

        $service = $this->createService(
            jwtContextService: $jwtContextService,
            userRepository: $userRepository
        );

        $context = $service->resolveBusinessContext($request);

        self::assertInstanceOf(BusinessContextDTO::class, $context);
        self::assertSame('public-id', $context->user->publicId);
        self::assertSame('user@example.test', $context->user->email);
    }

    public function testHandleSubmittedFormReturnsNullWhenNoSubmittedValidFormExists(): void
    {
        $forms = new BusinessFormsDTO(
            $this->createFormMock(false, false),
            $this->createFormMock(false, false),
            $this->createFormMock(false, false),
            $this->createFormMock(false, false),
            $this->createFormMock(false, false)
        );

        $subscriptionService = $this->createMock(SubscriptionService::class);
        $subscriptionService->expects(self::never())->method('getSubscriptionData');

        $service = $this->createService();

        self::assertNull($service->handleSubmittedForm($forms, $this->createBusinessContext(), new Request(), $subscriptionService));
    }

    public function testHandleSubmittedFormReturnsNullWhenAuthenticatedUserPublicIdMissing(): void
    {
        $submittedForm = $this->createFormMock(true, true, new BusinessRequestDTO('businessPlus'));
        $forms = new BusinessFormsDTO(
            $submittedForm,
            $this->createFormMock(false, false),
            $this->createFormMock(false, false),
            $this->createFormMock(false, false),
            $this->createFormMock(false, false)
        );

        $subscriptionService = $this->createMock(SubscriptionService::class);
        $subscriptionService->expects(self::never())->method('getSubscriptionData');

        $request = new Request();
        $request->attributes->set('_route', 'business');

        $service = $this->createService();

        self::assertNull($service->handleSubmittedForm(
            $forms,
            new BusinessContextDTO(
                new JwtContextDTO(true, '', 'user@example.test', ['publicId' => '']),
                new AuthenticatedUserDTO('', 'user@example.test')
            ),
            $request,
            $subscriptionService
        ));
    }

    public function testHandleSubmittedFormReturnsSubscriptionDataForFirstValidSubmittedForm(): void
    {
        $submittedForm = $this->createFormMock(true, true, new BusinessRequestDTO('businessPlus'));
        $forms = new BusinessFormsDTO(
            $this->createFormMock(false, false),
            $submittedForm,
            $this->createFormMock(false, false),
            $this->createFormMock(false, false),
            $this->createFormMock(false, false)
        );

        $expectedPayload = BackendPayloadDTO::fromArray(['service_auth_data' => 'ok']);

        $subscriptionService = $this->createMock(SubscriptionService::class);
        $subscriptionService
            ->expects(self::once())
            ->method('getSubscriptionData')
            ->with('business_create', 'businessPlus', 'external', 'public-id')
            ->willReturn($expectedPayload);

        $request = new Request();
        $request->attributes->set('_route', 'business');

        $service = $this->createService();

        self::assertSame($expectedPayload, $service->handleSubmittedForm($forms, $this->createBusinessContext(), $request, $subscriptionService));
    }

    public function testHandleSubmittedFormRejectsInvalidBusinessModelBeforeCallingSubscriptionService(): void
    {
        $submittedForm = $this->createFormMock(true, true, new BusinessRequestDTO('enterpriseRoot'));
        $forms = new BusinessFormsDTO(
            $submittedForm,
            $this->createFormMock(false, false),
            $this->createFormMock(false, false),
            $this->createFormMock(false, false),
            $this->createFormMock(false, false)
        );

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('warning')
            ->with(
                'Business subscription request rejected because business model is not allowed',
                self::arrayHasKey('business_model')
            );

        $subscriptionService = $this->createMock(SubscriptionService::class);
        $subscriptionService->expects(self::never())->method('getSubscriptionData');

        $request = new Request();
        $request->attributes->set('_route', 'business');

        $service = $this->createService(logger: $logger);

        self::assertNull($service->handleSubmittedForm($forms, $this->createBusinessContext(), $request, $subscriptionService));
    }

    public function testBuildBusinessViewDataReturnsFormViewsWhenJwtPayloadExists(): void
    {
        $forms = new BusinessFormsDTO(
            $this->createViewFormMock(),
            $this->createViewFormMock(),
            $this->createViewFormMock(),
            $this->createViewFormMock(),
            $this->createViewFormMock()
        );

        $service = $this->createService();
        $payload = BackendPayloadDTO::fromArray(['service_auth_data' => ['ok' => true]]);

        $viewData = $service->buildBusinessViewData(
            new BusinessContextDTO(
                new JwtContextDTO(true, 'public-id', 'user@example.test', ['publicId' => 'public-id']),
                new AuthenticatedUserDTO('public-id', 'user@example.test')
            ),
            $forms,
            $payload,
            true
        );

        self::assertInstanceOf(FormView::class, $viewData->formPswManager);
        self::assertSame(['service_auth_data' => ['ok' => true]], $viewData->serviceAuthData);
        self::assertTrue($viewData->menuItemInstanceRegistration);
    }

    private function createService(
        ?JwtContextService $jwtContextService = null,
        ?UserRepository $userRepository = null,
        ?LoggerInterface $logger = null,
        ?FormFactoryInterface $formFactory = null
    ): BusinessService {
        return new BusinessService(
            $jwtContextService ?? $this->createMock(JwtContextService::class),
            $userRepository ?? $this->createMock(UserRepository::class),
            $logger ?? $this->createMock(LoggerInterface::class),
            $formFactory ?? $this->createMock(FormFactoryInterface::class)
        );
    }

    private function createBusinessContext(): BusinessContextDTO
    {
        return new BusinessContextDTO(
            new JwtContextDTO(true, 'public-id', 'user@example.test', [
                'username' => 'user@example.test',
                'publicId' => 'public-id',
            ]),
            new AuthenticatedUserDTO('public-id', 'user@example.test')
        );
    }

    private function createFormMock(bool $submitted, bool $valid, ?BusinessRequestDTO $data = null): FormInterface
    {
        /** @var FormInterface&MockObject $form */
        $form = $this->createMock(FormInterface::class);
        $form->method('isSubmitted')->willReturn($submitted);
        $form->method('isValid')->willReturn($valid);

        if ($data !== null) {
            $form->method('getData')->willReturn($data);
        }

        return $form;
    }

    private function createViewFormMock(): FormInterface
    {
        /** @var FormInterface&MockObject $form */
        $form = $this->createMock(FormInterface::class);
        $form->method('createView')->willReturn(new FormView());

        return $form;
    }
}
