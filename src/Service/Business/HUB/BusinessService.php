<?php

namespace App\Service\Business\HUB;

use App\Form\BusinessRequesterType;
use App\Repository\UserRepository;
use App\Service\Corporate\SubscriptionService;
use App\Service\Instance\HUB\JwtContextService;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;

class BusinessService
{
    private const FORM_CSRF_IDS = [
        'pswManager' => 'psw_manager',
        'biometric' => 'biometric',
        'businessBasic' => 'business_basic',
        'businessPlus' => 'business_plus',
        'businessPro' => 'business_pro',
    ];

    public function __construct(
        private JwtContextService $jwtContextService,
        private JWTEncoderInterface $jwtEncoder,
        private UserRepository $userRepository,
        private LoggerInterface $logger,
        private FormFactoryInterface $formFactory
    ) {}

    public function resolveBusinessContext(Request $request): ?array
    {
        $jwtContext = $this->jwtContextService->build($request);
        $jwtToken = $jwtContext['payload'];

        if ($jwtToken === null) {
            $this->logger->warning('Business page user identification failed', [
                'route' => $request->attributes->get('_route'),
                'has_jwt_token' => false,
            ]);

            return null;
        }

        $user = $this->identifyUser($jwtToken);

        if ($user === null) {
            $this->logger->warning('Business page user identification failed', [
                'route' => $request->attributes->get('_route'),
                'has_jwt_token' => true,
            ]);

            return null;
        }

        $this->logger->info('Business page user identified from JWT', [
            'route' => $request->attributes->get('_route'),
            'user_public_id' => $user['publicId'] ?? null,
            'user_email' => $user['email'] ?? null,
        ]);

        return [
            'jwt' => $jwtContext,
            'user' => $user,
        ];
    }

    public function buildForms(Request $request): array
    {
        $forms = [];

        foreach (self::FORM_CSRF_IDS as $key => $csrfTokenId) {
            $form = $this->formFactory->create(
                BusinessRequesterType::class,
                ['businessModel' => $key],
                ['csrf_token_id' => $csrfTokenId]
            );
            $form->handleRequest($request);
            $forms[$key] = $form;
        }

        $this->logger->debug('Business subscription forms prepared', [
            'route' => $request->attributes->get('_route'),
            'form_keys' => array_keys($forms),
        ]);

        return $forms;
    }

    public function handleSubmittedForm(
        array $forms,
        Request $request,
        SubscriptionService $subscriptionService
    ): ?array {
        $process = 'business_create';

        foreach ($forms as $form) {
            if (!$form->isSubmitted() || !$form->isValid()) {
                continue;
            }

            $jwtTokenEncoded = $request->cookies->get('jwt_token') ?? '';
            $jwtToken = $this->jwtEncoder->decode($jwtTokenEncoded);
            $validatedInput = $form->getData();

            $this->logger->info('Business subscription request submitted', [
                'route' => $request->attributes->get('_route'),
                'process' => $process,
                'business_model' => $validatedInput['businessModel'] ?? null,
                'user_public_id' => $jwtToken['publicId'] ?? null,
            ]);

            $subscriptionData = $subscriptionService->getSubscriptionData(
                $process,
                $validatedInput['businessModel'],
                'external',
                $jwtToken['publicId']
            );

            $this->logger->info('Business subscription response received', [
                'route' => $request->attributes->get('_route'),
                'process' => $process,
                'business_model' => $validatedInput['businessModel'] ?? null,
                'response_keys' => is_array($subscriptionData) ? array_keys($subscriptionData) : [],
            ]);

            return $subscriptionData;
        }

        return null;
    }

    public function buildBusinessViewData(
        array $businessContext,
        array $forms,
        mixed $subscriptionData,
        bool $menuItemInstanceRegistration
    ): array {
        $jwtContext = $businessContext['jwt'];
        $jwtToken = $jwtContext['payload'];

        return [
            'is_jwt_valid' => $jwtContext['isJwtValid'],
            'user' => [
                'userPublicId' => $jwtContext['userPublicId'],
                'userEmail' => $jwtContext['userEmail'],
            ],
            'form_psw_manager' => $jwtToken ? $forms['pswManager']->createView() : '',
            'form_biometric' => $jwtToken ? $forms['biometric']->createView() : '',
            'form_business_basic' => $jwtToken ? $forms['businessBasic']->createView() : '',
            'form_business_plus' => $jwtToken ? $forms['businessPlus']->createView() : '',
            'form_business_pro' => $jwtToken ? $forms['businessPro']->createView() : '',
            'service_auth_data' => $jwtToken ? $subscriptionData : null,
            'menuItem_instanceRegistration' => $menuItemInstanceRegistration,
        ];
    }

    public function buildEmptyBusinessViewData(bool $menuItemInstanceRegistration): array
    {
        $this->logger->info('Rendering empty business view data due to missing business context', [
            'menu_item_instance_registration' => $menuItemInstanceRegistration,
        ]);

        return [
            'is_jwt_valid' => false,
            'user' => [
                'userPublicId' => '',
                'userEmail' => '',
            ],
            'form_psw_manager' => '',
            'form_biometric' => '',
            'form_business_basic' => '',
            'form_business_plus' => '',
            'form_business_pro' => '',
            'service_auth_data' => null,
            'menuItem_instanceRegistration' => $menuItemInstanceRegistration,
        ];
    }

    private function identifyUser(?array $jwtToken): ?array
    {
        if (!isset($jwtToken['username'])) {
            $this->logger->warning('JWT token missing username during business user identification');

            return null;
        }

        $userData = $this->userRepository->findOneBy([
            'email' => $jwtToken['username'],
        ]);

        if (!$userData) {
            $this->logger->warning('No business user found for JWT username', [
                'username' => $jwtToken['username'],
            ]);

            return null;
        }

        return [
            'publicId' => $userData->getPublicId(),
            'email' => $userData->getEmail(),
        ];
    }
}