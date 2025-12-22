<?php

namespace App\Controller\User\Login\Api;

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
     * Called from the any registrated domain to get a QR code for login
     */
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

    /**
     * Called from the api to confirm the login
     */
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
    
    /**
     * Called from any registrated domain to get a new QR code for login
     */
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

    /**
     * Called from any registrated domain to poll the login status
     */
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
}
