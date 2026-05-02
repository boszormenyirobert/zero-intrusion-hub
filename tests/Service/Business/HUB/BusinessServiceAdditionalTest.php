<?php

declare(strict_types=1);

namespace App\Tests\Service\Business\HUB;

use App\DTO\AuthenticatedUserDTO;
use App\DTO\BusinessContextDTO;
use App\DTO\BusinessFormsDTO;
use App\DTO\JwtContextDTO;
use App\Entity\User;
use App\Form\BusinessRequesterType;
use App\Repository\UserRepository;
use App\Service\Business\HUB\BusinessService;
use App\Service\Instance\HUB\JwtContextService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;

final class BusinessServiceAdditionalTest extends TestCase
{
    public function testResolveBusinessContextReturnsNullWhenUserCannotBeResolved(): void
    {
        $jwtContextService = $this->createMock(JwtContextService::class);
        $jwtContextService->expects(self::once())->method('build')->willReturn(new JwtContextDTO(true, 'public-id', 'user@example.test', ['username' => 'user@example.test']));

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->expects(self::once())->method('findOneBy')->with(['email' => 'user@example.test'])->willReturn(null);

        $service = new BusinessService(
            $jwtContextService,
            $userRepository,
            $this->createMock(LoggerInterface::class),
            $this->createMock(FormFactoryInterface::class)
        );

        self::assertNull($service->resolveBusinessContext(Request::create('/business')));
    }

    public function testBuildFormsCreatesAndHandlesAllBusinessForms(): void
    {
        $request = Request::create('/business', 'POST');
        $forms = [];

        for ($index = 0; $index < 5; ++$index) {
            $form = $this->createMock(FormInterface::class);
            $form->expects(self::once())->method('handleRequest')->with($request);
            $forms[] = $form;
        }

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory
            ->expects(self::exactly(5))
            ->method('create')
            ->withConsecutive(
                [BusinessRequesterType::class, self::callback(fn ($dto) => $dto->businessModel === 'pswManager'), ['csrf_token_id' => 'psw_manager']],
                [BusinessRequesterType::class, self::callback(fn ($dto) => $dto->businessModel === 'biometric'), ['csrf_token_id' => 'biometric']],
                [BusinessRequesterType::class, self::callback(fn ($dto) => $dto->businessModel === 'businessBasic'), ['csrf_token_id' => 'business_basic']],
                [BusinessRequesterType::class, self::callback(fn ($dto) => $dto->businessModel === 'businessPlus'), ['csrf_token_id' => 'business_plus']],
                [BusinessRequesterType::class, self::callback(fn ($dto) => $dto->businessModel === 'businessPro'), ['csrf_token_id' => 'business_pro']]
            )
            ->willReturnOnConsecutiveCalls(...$forms);

        $service = new BusinessService(
            $this->createMock(JwtContextService::class),
            $this->createMock(UserRepository::class),
            $this->createMock(LoggerInterface::class),
            $formFactory
        );

        $dto = $service->buildForms($request);

        self::assertInstanceOf(BusinessFormsDTO::class, $dto);
        self::assertSame($forms[0], $dto->pswManager);
        self::assertSame($forms[4], $dto->businessPro);
    }

    public function testBuildBusinessViewDataReturnsEmptyFormMarkersWithoutJwtPayload(): void
    {
        $service = new BusinessService(
            $this->createMock(JwtContextService::class),
            $this->createMock(UserRepository::class),
            $this->createMock(LoggerInterface::class),
            $this->createMock(FormFactoryInterface::class)
        );

        $forms = new BusinessFormsDTO(
            $this->createViewForm(),
            $this->createViewForm(),
            $this->createViewForm(),
            $this->createViewForm(),
            $this->createViewForm()
        );

        $viewData = $service->buildBusinessViewData(
            new BusinessContextDTO(new JwtContextDTO(false, '', '', null), new AuthenticatedUserDTO()),
            $forms,
            null,
            false
        );

        self::assertSame('', $viewData->formPswManager);
        self::assertNull($viewData->serviceAuthData);
        self::assertFalse($viewData->menuItemInstanceRegistration);
    }

    public function testBuildEmptyBusinessViewDataReturnsAnonymousDefaults(): void
    {
        $service = new BusinessService(
            $this->createMock(JwtContextService::class),
            $this->createMock(UserRepository::class),
            $this->createMock(LoggerInterface::class),
            $this->createMock(FormFactoryInterface::class)
        );

        $viewData = $service->buildEmptyBusinessViewData(true);

        self::assertFalse($viewData->isJwtValid);
        self::assertSame('', $viewData->user->publicId);
        self::assertTrue($viewData->menuItemInstanceRegistration);
    }

    private function createViewForm(): FormInterface
    {
        /** @var FormInterface&MockObject $form */
        $form = $this->createMock(FormInterface::class);
        $form->method('createView')->willReturn(new FormView());

        return $form;
    }
}
