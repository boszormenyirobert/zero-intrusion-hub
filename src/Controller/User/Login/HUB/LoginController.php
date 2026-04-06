<?php
/**
 * Handles user login and logout processes via the HUB frontend.
 *
 * Responsibilities:
 * - Generates QR codes for login and supports optional userPublicId for auto-login via Firebase.
 * - Polls the database to confirm login status via JavaScript.
 * - Logs out users and clears JWT cookies.
 * - Manages CSRF token validation for secure login workflows.
 */
namespace App\Controller\User\Login\HUB;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use App\Repository\UserRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

class LoginController extends AbstractController
{
    public function __construct(
        private LoginService $loginService
    ) {}

    /*
    * The endpoint called from the HUB frontend to generate a QR code for login.
    * The userPublicId is optional and can be used to notify a specific user via Firebase for auto-login.
    * Firebase notification is handled by the API.
    */
    #[Route('login', name: 'instance_login', methods: ["GET"] )]
    public function login(
        Request $request
        ) {  
        $viewData = $this->loginService->buildLoginViewData(
            $request,
            (bool) $this->getParameter('ZERO_INTRUSION_FRONTEND_ALLOW_INSTANCE_REGISTRATION')
        );

        if ($viewData === null) {
            return null;
        }

        return $this->render('views/users/user-login.html.twig', $viewData);
    }

    /*
    * Polls the database to check if the user has confirmed the login via JavaScript.
    * action can be 'login' or 'registration' to differentiate between login and registration processes.
    * If login is confirmed, generates a JWT token, sets it in a cookie, and resets the user's allowed status to prevent reuse of the same QR code.
     * If registration is confirmed, simply returns a success message.
    */
    #[Route('/login/check', name: 'user_login_check', methods: "GET")]
    public function pollStateByFrontend(
        Request $request,
        UserRepository $userRepository,
        JWTTokenManagerInterface $jwtManager
    )
    {
        return $this->loginService->buildFrontendPollResponse(
            $request,
            $userRepository,
            $jwtManager
        );
    }

    /*
    * Logs out the user and clears the JWT cookie when the logout link is clicked.
    */
    #[Route('/user-logout', name: 'instance_logout', methods: "GET")]
    public function logout(CsrfTokenManagerInterface $csrfTokenManager, Request $request) {
        $csrfTokenManager->removeToken('userLoginCsrf');

        $response = $this->redirectToRoute('home');
        $this->loginService->prepareLogoutResponse($response, $request);

        return $response;
    }    
}
