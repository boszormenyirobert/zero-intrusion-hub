<?php
/**
 * HUB: Handles secure device interactions, including "One Touch Activation"
 * for users with valid JWT tokens.
 */
namespace App\Controller\User\HUB;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use App\Controller\User\UserService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use App\Repository\UserRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use App\Service\JWT\JwtService;

class SecureDeviceController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger,
        private JWTEncoderInterface $jwtEncoder,
        private UserService $userService,
        private JwtService $jwtService,
        private UserRepository $userRepository,
    ) {}

    /*
    * HUB endpoint for handling "One Touch Activation".
    * If the user has a valid JWT token and clicks the activation link,
    * the frontend is informed about the result of the activation.
    */
    #[Route('/secure-device', name: 'secure_device', methods: "GET")]
    public function secureDevice(
        Request $request
        ) {  
        $token = $request->cookies->get('jwt_token');
        $payload =  $this->jwtService->jwtValidation($token);
        
        $userPublicId = null;
        $userEmail = null;
                 
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