<?php
/**
 * Handling an registrated Corporate account
 * 
 */
namespace App\Controller\Account;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\User\UserRegistrationService;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use App\Repository\UserRepository;
use App\Service\JWT\JwtService;

class AccountController extends AbstractController
{
    public function __construct(
        private JWTEncoderInterface $jwtEncoder,
        private UserRepository $userRepository,
    ) {}

    #[Route('/account', name: 'account')]
    public function business(
        Request $request,
        UserRegistrationService $userRegistrationService,
        JwtService $jwtService
    ): Response 
    { 
        $jwtToken = $jwtService->jwtValidation($request);
        
        if($jwtToken && $user = $this->identifyUser($jwtToken)){

            $process = "get_registrated_business";

            /** @var Response $response */
            $response = $userRegistrationService->forwardRegistration(
                [$process =>  $user]
            );
            
            $encodedContent = $response->getContent();
            $businessSubscription = \json_decode($encodedContent, true);
            if(!$businessSubscription || !isset($businessSubscription['businessSubscription'])){
               $businessSubscription['accounts'] = [];
               $businessSubscription['businessSubscription'] = [];
            }
        } else {
            return $this->redirect($this->generateUrl('instance_login'));
        }

        $pills = [
            'pswManager'    => 'Password Manager',
            'biometric'     => 'Secure biometric',
            'basic' => 'Business Basic', 
            'plus' => 'Business Plus', 
            'pro' => 'Business Pro'
        ];

        return $this->render(
            'views/containers/container-account.html.twig',
            [                
                'is_jwt_valid' => $jwtToken,
                'accounts' => $businessSubscription['accounts'],
                'businessSubscription' => $this->getSelectedSubscription($businessSubscription['businessSubscription']),
                'menuItem_instanceRegistration' => (bool)$this->getParameter('ZERO_INTRUSION_FRONTEND_ALLOW_INSTANCE_REGISTRATION'),
                'pills' => $pills
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

    private function getSelectedSubscription($businessSubscription){   
       
        foreach($businessSubscription as $key => $value){
            if($value === true){
                $subscription['subscription'] = $key;
                $subscription['id'] = $businessSubscription['id'];

                return $subscription;
            }
        }        
    }
}