<?php
/**
 * HUB VIEW with API call
 */
namespace App\Controller\Business\HUB;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Attribute\JwtRequired;
use App\Service\Corporate\SubscriptionService;
use App\Form\BusinessRequesterType;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use App\Form\IdentityRequesterType;
use App\Repository\UserRepository;
use App\Service\JWT\JwtService;

class BusinessController extends AbstractController
{
    public function __construct(
        private JWTEncoderInterface $jwtEncoder,
        private UserRepository $userRepository,
    ) {}

    #[JwtRequired]
    #[Route('/business', name: 'business_registration')]
    public function business(
    Request $request,
    SubscriptionService $subscriptionService,
    JWTEncoderInterface $jwtEncoder,
    JwtService $jwtService
    ): Response 
    {   

        $jwtToken = $jwtService->jwtValidation($request);

        if($jwtToken && $user = $this->identifyUser($jwtToken)){
            $values = [
                'pswManager'    => 'psw_manager',
                'biometric'     => 'biometric',
                'businessBasic' => 'business_basic', 
                'businessPlus' => 'business_plus', 
                'businessPro' => 'business_pro'
            ];

            $forms = [];
            foreach ($values as $key => $val) {
                $forms[$key] = $this->createForm(BusinessRequesterType::class, ['businessModel' => $key], ['csrf_token_id' => $val]);
                $forms[$key]->handleRequest($request);
            }

            $process = "business_create";
            $subscriptionData = null;
            foreach ($forms as $form) {
                if ($form->isSubmitted() && $form->isValid()) {
                    $jwtTokenEncoded = $request->cookies->get('jwt_token') ?? '';   
                    $jwt_token = $jwtEncoder->decode($jwtTokenEncoded);

                    $validatedInput = $form->getData();
                    $subscriptionData = $subscriptionService->getSubscriptionData($process, $validatedInput['businessModel'],'external', $jwt_token['publicId']);
                    
                    return $this->redirect($this->generateUrl('account'));
                }
            }
        }

        return $this->render(
            'views/corporate/business-services.html.twig',
            [
                'is_jwt_valid' => $jwtToken,
                'form_psw_manager' =>  $jwtToken ? $forms['pswManager']->createView() : "",
                'form_biometric' => $jwtToken ? $forms['biometric']->createView() : "",
                'form_business_basic' => $jwtToken ? $forms['businessBasic']->createView() : "",
                'form_business_plus' => $jwtToken ? $forms['businessPlus']->createView() : "",
                'form_business_pro' => $jwtToken ? $forms['businessPro']->createView() : "",
                'service_auth_data' => $jwtToken ? $subscriptionData : null,
                'menuItem_instanceRegistration' => (bool)$this->getParameter('ZERO_INTRUSION_FRONTEND_ALLOW_INSTANCE_REGISTRATION')
            ]
        );
    }

        private function identifyUser($jwtToken){
        $userData = $this->userRepository->findOneBy([
            'email' => $jwtToken['username']
        ]);

        if($userData){
            return [
                'publicId' => $userData->getPublicId(),
                'email' => $userData->getEmail()
            ];
        }

        return false;
    }
}
