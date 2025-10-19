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

class UserController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger,
        private UserService $userService
    ) {}

    #[Route('/user-login', name: 'instance_login', methods: "GET")]
    public function login(
        CsrfTokenManagerInterface $csrfTokenManager, 
        Request $request
        ) {  

        $token = $csrfTokenManager->getToken('userLoginCsrf')->getValue();
        
        $userPublicId = null;
        if ($request->query->has('userPublicId')) {
            $userPublicId = $request->query->get('userPublicId');
        }
dd($userPublicId);
        return $this->render('views/users/user-login.html.twig', [
            'authentication' => $this->userService->getQrCode('user_login', [],  $userPublicId),
            'userLoginCsrf' => $token,
            'menuItem_instanceRegistration' => (bool)$this->getParameter('ZERO_INTRUSION_FRONTEND_ALLOW_INSTANCE_REGISTRATION')
        ]);
    }

    #[Route('/api/user-login', name: 'api_instance_login', methods: "POST")]
    public function apiLogin(
        Request $request
    ) {   
        $headers =  $request->headers->all();

        $corporateIentification = json_decode($request->getContent(), true);   

        $process = 'user_login';
        $response = $this->userService->getQrCode($process, $corporateIentification, $corporateIentification['userPublicId'] ?? null);

        return $this->json($response);
    }    

    #[Route('/api/user-login/callback', name: 'user_login_callback', methods: ["POST"])]
    public function systemHubLoginCallback(
        Request $request
        ): JsonResponse
    {
        $response = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $dto = RegistrationProcessDTO::mapFromArrayLogin($response);

        return new JsonResponse([
            'status' => 'ok',
            'success' => $this->userService->allowSetUserLoginProcess($dto),
        ]);

    }
    
    #[Route('/api/user-login/new-qr', name: 'user_login_new_qr', methods: "POST")]
    public function userLoginNewQr(
        Request $request,
        CsrfTokenManagerInterface $csrfTokenManager
        ) {  

        $tokenFromHeader = $request->headers->get('X-CSRF-TOKEN');
        $token = $csrfTokenManager->getToken('userLoginCsrf')->getValue();

        if (!$csrfTokenManager->isTokenValid(new CsrfToken('userLoginCsrf', $tokenFromHeader))) {
            return new JsonResponse(['error' => 'Invalid CSRF token'], 403);
        }

        $userPublicId = "6ebIY8srJAJ+AeXzcuYknCr3OCBi89rVt///NdG50oYyZWE=";

        return $this->json([
            'authentication' => $this->userService->getQrCode('user_login', [], $userPublicId),
            'userLoginCsrf' => $token
        ]);
    }

    #[Route('/api/user-login/check', name: 'user_login_check', methods: "POST")]
    public function userLoginCheck(
        Request $request,
        CsrfTokenManagerInterface $csrfTokenManager,
        UserRepository $userRepository,
        JWTTokenManagerInterface $jwtManager
    ): JsonResponse
    {
        $tokenFromHeader = $request->headers->get('X-CSRF-TOKEN');

        if (!$csrfTokenManager->isTokenValid(new CsrfToken('userLoginCsrf', $tokenFromHeader))) {
            return new JsonResponse(['error' => 'Invalid CSRF token'], 403);
        }

        $processData = json_decode($request->getContent(), true);

        $user = $userRepository->findOneBy([
            'process' => $processData['domainProcessId']
        ]);
        
        if($user && $user->isAllowed()){            
            $token = $jwtManager->create($user);
            $response = new JsonResponse([
                'message' => 'Authentication is success',
                'jwt_token' => $token
            ]);

            $response->headers->setCookie(
                // TODO  set the first false > true in prod
                new Cookie('jwt_token', $token, time() + 3600, '/', null, false, true, false, 'Strict')
            );

            return $response;
        }

        return $this->json(['message' => 'Unsuccess authentication. The QR code validity period(10 seconds) has expired.']);
    }    

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

            //$response = $this->redirectToRoute('home');

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
