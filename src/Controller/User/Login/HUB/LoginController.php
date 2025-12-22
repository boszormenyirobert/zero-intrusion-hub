<?php

namespace App\Controller\User\Login\HUB;

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

class LoginController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger,
        private UserService $userService
    ) {}

    /**
     * Called from the HUB FE to get a QR code for login
     * The userPublicId is optional and can be used to notify a specific user via firebase for auto login
     * Firebase notification is handled in the API 
     */
    #[Route('/user-login', name: 'instance_login', methods: "GET")]
    public function login(
        CsrfTokenManagerInterface $csrfTokenManager, 
        Request $request
        ) {  

        $token = $csrfTokenManager->getToken('userLoginCsrf')->getValue();
        
        // Optional userPublicId for firebase auto login notification
        $userPublicId = null;
        if ($request->query->has('userPublicId')) {
            $userPublicId = $request->query->get('userPublicId');
        }

        return $this->render('views/users/user-login.html.twig', [
            'authentication' => $this->userService->getQrCode('user_login', [],  $userPublicId),
            'userLoginCsrf' => $token,
            'menuItem_instanceRegistration' => (bool)$this->getParameter('ZERO_INTRUSION_FRONTEND_ALLOW_INSTANCE_REGISTRATION')
        ]);
    }

    // Pollling database to check if user confirmed the login via JS
    #[Route('/user-login/check', name: 'user_login_check', methods: "GET")]
    public function userJSCheck(
        Request $request,
        CsrfTokenManagerInterface $csrfTokenManager,
        UserRepository $userRepository,
        JWTTokenManagerInterface $jwtManager
    )
    {
        $processId = $request->query->get('processId');
        $user = $userRepository->findOneBy([
            'process' => $processId
        ]);
       
        if($user && $user->isAllowed()){            
            $token = $jwtManager->create($user);
            $response = new JsonResponse([
                'message' => 'Authentication is success',
                'jwt_token' => $token
            ]);

            $cookie = new Cookie(
                'jwt_token',
                $token,
                time() + 3600, // expire in 1h
                '/',
                null,
                false,  // secure (set to true on HTTPS)
                true,   // httpOnly
                false,
                'Strict'
            );

            $response = $this->json([
                'message' => 'Authentication success.'
            ]);

            $response->headers->setCookie($cookie);

            return $response;
        }    
    }

    // Logout user and clear JWT cookie clicked on logout link
    #[Route('/user-logout', name: 'instance_logout', methods: "GET")]
    public function logout(CsrfTokenManagerInterface $csrfTokenManager) {       
        $csrfTokenManager->removeToken('userLoginCsrf');
    
        $response = new Response();
        $response->headers->clearCookie('jwt_token');

        $html = $this->renderView('views/users/user-logged-out.html.twig', [
            'logout' => true,
            'menuItem_instanceRegistration' => (bool)$this->getParameter('ZERO_INTRUSION_FRONTEND_ALLOW_INSTANCE_REGISTRATION')
        ]);

        $response->setContent($html);

        return $response;
    }    
}
