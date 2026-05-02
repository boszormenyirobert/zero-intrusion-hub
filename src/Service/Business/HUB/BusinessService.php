<?php

namespace App\Service\Business\HUB;

use App\DTO\AuthenticatedUserDTO;
use App\DTO\BackendPayloadDTO;
use App\DTO\BusinessContextDTO;
use App\DTO\BusinessFormsDTO;
use App\DTO\BusinessRequestDTO;
use App\DTO\BusinessViewDataDTO;
use App\Form\BusinessRequesterType;
use App\Logger\LogTrace;
use App\Repository\UserRepository;
use App\Service\Corporate\SubscriptionService;
use App\Service\Instance\HUB\JwtContextService;
use App\Service\Shared\ProcessKey;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;

class BusinessService
{
    private const PROCESS_BUSINESS_CREATE = ProcessKey::BUSINESS_CREATE;
    private const JWT_USERNAME_KEY = 'username';
    private const JWT_PUBLIC_ID_KEY = 'publicId';
    private const SUBSCRIPTION_SOURCE = 'external';

    private const FORM_CSRF_IDS = [
        'pswManager' => 'psw_manager',
        'biometric' => 'biometric',
        'businessBasic' => 'business_basic',
        'businessPlus' => 'business_plus',
        'businessPro' => 'business_pro',
    ];

    public function __construct(
        private JwtContextService $jwtContextService,
        private UserRepository $userRepository,
        private LoggerInterface $logger,
        private FormFactoryInterface $formFactory
    ) {
    }

    public function resolveBusinessContext(Request $request): ?BusinessContextDTO
    {
        $jwtContext = $this->jwtContextService->build($request);
        $jwtToken = $jwtContext->payload;

        if ($jwtToken === null) {
            $this->logBusinessUserIdentificationFailed($request, false);
            return null;
        }

        $user = $this->identifyUser($jwtToken);

        if ($user === null) {
            $this->logBusinessUserIdentificationFailed($request, true);
            return null;
        }

        $this->logger->info('Business page user identified from JWT', [
            'route' => $request->attributes->get('_route'),
            'user_public_id_hash' => LogTrace::fingerprint($user->publicId),
            'user_email_hash' => LogTrace::fingerprint($user->email),
        ]);

        return new BusinessContextDTO($jwtContext, $user);
    }

    public function buildForms(Request $request): BusinessFormsDTO
    {
        $forms = [];

        foreach (self::FORM_CSRF_IDS as $key => $csrfTokenId) {
            $form = $this->createBusinessForm($key, $csrfTokenId);
            $form->handleRequest($request);
            $forms[$key] = $form;
        }

        $this->logger->debug('Business subscription forms prepared', [
            'route' => $request->attributes->get('_route'),
            'form_keys' => array_keys($forms),
        ]);

        return new BusinessFormsDTO(
            $forms['pswManager'],
            $forms['biometric'],
            $forms['businessBasic'],
            $forms['businessPlus'],
            $forms['businessPro']
        );
    }

    public function handleSubmittedForm(
        BusinessFormsDTO $forms,
        BusinessContextDTO $businessContext,
        Request $request,
        SubscriptionService $subscriptionService
    ): ?BackendPayloadDTO {
        $process = self::PROCESS_BUSINESS_CREATE;

        foreach ($forms->all() as $form) {
            if (!$this->isSubmittedAndValid($form)) {
                continue;
            }

            if (!$this->hasBusinessUserPublicId($businessContext->user, $request, $process)) {
                return null;
            }

            /** @var BusinessRequestDTO $validatedInput */
            $validatedInput = $form->getData();

            if (!$this->isAllowedBusinessModel($validatedInput, $request)) {
                return null;
            }

            $this->logSubmittedBusinessRequest($request, $process, $validatedInput, $businessContext->user);

            $subscriptionData = $subscriptionService->getSubscriptionData(
                $process,
                $validatedInput->businessModel,
                self::SUBSCRIPTION_SOURCE,
                $businessContext->user->publicId
            );

            $this->logSubscriptionResponse($request, $process, $validatedInput, $subscriptionData);

            return $subscriptionData;
        }

        return null;
    }

    public function buildBusinessViewData(
        BusinessContextDTO $businessContext,
        BusinessFormsDTO $forms,
        ?BackendPayloadDTO $subscriptionData,
        bool $menuItemInstanceRegistration
    ): BusinessViewDataDTO {
        $jwtContext = $businessContext->jwt;
        $hasJwtToken = $jwtContext->payload !== null;

        return new BusinessViewDataDTO(
            $jwtContext->isJwtValid,
            $jwtContext->toUserDto(),
            $hasJwtToken ? $forms->pswManager->createView() : '',
            $hasJwtToken ? $forms->biometric->createView() : '',
            $hasJwtToken ? $forms->businessBasic->createView() : '',
            $hasJwtToken ? $forms->businessPlus->createView() : '',
            $hasJwtToken ? $forms->businessPro->createView() : '',
            $hasJwtToken ? $subscriptionData?->toArray() : null,
            $menuItemInstanceRegistration,
        );
    }

    public function buildEmptyBusinessViewData(bool $menuItemInstanceRegistration): BusinessViewDataDTO
    {
        $this->logger->info('Rendering empty business view data due to missing business context', [
            'menu_item_instance_registration' => $menuItemInstanceRegistration,
        ]);

        return new BusinessViewDataDTO(
            false,
            new AuthenticatedUserDTO(),
            '',
            '',
            '',
            '',
            '',
            null,
            $menuItemInstanceRegistration,
        );
    }

    private function identifyUser(?array $jwtToken): ?AuthenticatedUserDTO
    {
        if (!isset($jwtToken[self::JWT_USERNAME_KEY])) {
            $this->logger->warning('JWT token missing username during business user identification');

            return null;
        }

        $userData = $this->userRepository->findOneBy([
            'email' => $jwtToken[self::JWT_USERNAME_KEY],
        ]);

        if (!$userData) {
            $this->logger->warning('No business user found for JWT username', [
                'username_hash' => is_string($jwtToken[self::JWT_USERNAME_KEY]) ? LogTrace::fingerprint($jwtToken[self::JWT_USERNAME_KEY]) : null,
            ]);

            return null;
        }

        return new AuthenticatedUserDTO(
            $userData->getPublicId(),
            $userData->getEmail(),
        );
    }

    private function createBusinessForm(string $key, string $csrfTokenId)
    {
        return $this->formFactory->create(
            BusinessRequesterType::class,
            new BusinessRequestDTO($key),
            ['csrf_token_id' => $csrfTokenId]
        );
    }

    private function isSubmittedAndValid($form): bool
    {
        return $form->isSubmitted() && $form->isValid();
    }

    private function hasBusinessUserPublicId(AuthenticatedUserDTO $user, Request $request, string $process): bool
    {
        if ($user->publicId === '') {
            $this->logger->warning('Business subscription request skipped because authenticated user public id is missing', [
                'route' => $request->attributes->get('_route'),
                'process' => $process,
            ]);

            return false;
        }

        return true;
    }

    private function logSubmittedBusinessRequest(Request $request, string $process, BusinessRequestDTO $validatedInput, AuthenticatedUserDTO $user): void
    {
        $this->logger->info('Business subscription request submitted', [
            'route' => $request->attributes->get('_route'),
            'process' => $process,
            'business_model' => $validatedInput->businessModel,
            'user_public_id_hash' => LogTrace::fingerprint($user->publicId),
        ]);
    }

    private function logSubscriptionResponse(Request $request, string $process, BusinessRequestDTO $validatedInput, BackendPayloadDTO $subscriptionData): void
    {
        $this->logger->info('Business subscription response received', [
            'route' => $request->attributes->get('_route'),
            'process' => $process,
            'business_model' => $validatedInput->businessModel,
            'response_keys' => $subscriptionData->keys(),
        ]);
    }

    private function logBusinessUserIdentificationFailed(Request $request, bool $hasJwtToken): void
    {
        $this->logger->warning('Business page user identification failed', [
            'route' => $request->attributes->get('_route'),
            'has_jwt_token' => $hasJwtToken,
        ]);
    }

    private function isAllowedBusinessModel(BusinessRequestDTO $validatedInput, Request $request): bool
    {
        if (BusinessRequestDTO::isAllowedBusinessModel($validatedInput->businessModel)) {
            return true;
        }

        $this->logger->warning('Business subscription request rejected because business model is not allowed', [
            'route' => $request->attributes->get('_route'),
            'business_model' => $validatedInput->businessModel,
            'allowed_business_models' => BusinessRequestDTO::allowedBusinessModels(),
        ]);

        return false;
    }
}
