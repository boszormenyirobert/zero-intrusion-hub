<?php

namespace App\Controller\User;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use App\Controller\User\UserService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Cookie;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use App\DTO\RegistrationProcessDTO;
use Symfony\Component\HttpFoundation\Response;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use App\Service\JWT\JwtService;

class SecureDeviceController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger,
        private JWTEncoderInterface $jwtEncoder,
        private UserService $userService,
        JwtService $jwtService,
        private UserRepository $userRepository,
    ) {}

    #[Route('/secure-device', name: 'secure_device', methods: "GET")]
    public function secureDevice(
        Request $request,
        JwtService $jwtService
        ) {  
        $userPublicId = null;

         $jwtToken = $jwtService->jwtValidation($request);
         
         if($jwtToken && $user = $this->identifyUser($jwtToken)){
            $userPublicId = $user['publicId'];
         }

        $jwt_token = $request->cookies->get('jwt_token') ?? '';      
        if($jwt_token){  
            $payload = $this->jwtEncoder->decode($jwt_token);
        }         

        return $this->render('views/users/secure-device.html.twig', [
            'is_jwt_valid' => $payload ?? false,
            'userPublicId' => $userPublicId,
            'menuItem_instanceRegistration' => (bool)$this->getParameter('ZERO_INTRUSION_FRONTEND_ALLOW_INSTANCE_REGISTRATION')
        ]);
    }

    private function identifyUser($jwtToken){
        $userData = $this->userRepository->findOneBy([
            'email' => $jwtToken['username']
        ]);

        if($userData){
            return [
                'publicId' => $userData->getPublicId()
            ];
        }

        return false;
    }    
}