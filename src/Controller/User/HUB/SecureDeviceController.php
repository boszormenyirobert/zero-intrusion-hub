<?php

namespace App\Controller\User\HUB;

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

    /** 
     * If the user has a valid JWT token and clicks the "One Touch Activation" link, 
     * the frontend is informed about the result of the activation. 
     */
    #[Route('/secure-device', name: 'secure_device', methods: "GET")]
    public function secureDevice(
        Request $request,
        JwtService $jwtService
        ) {  
        $userPublicId = null;
        $userEmail = null;

        dd($request->cookies->all());
        $payload = $jwtService->jwtValidation($request);
         
        if($payload){                   
            $userPublicId = $payload['publicId'] ?? null;
            $userEmail = $payload['email'] ?? null;
        }         

        return $this->render('views/users/secure-device.html.twig', [
            'is_jwt_valid' => $payload ?? false,
            'userPublicId' => $userPublicId,
            'userEmail' => $userEmail,
            'oneTouchActivation' => true,
            'menuItem_instanceRegistration' => (bool)$this->getParameter('ZERO_INTRUSION_FRONTEND_ALLOW_INSTANCE_REGISTRATION')
        ]);
    }
}