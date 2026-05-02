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

use App\Attribute\CsrfProtectedRoute;
use App\Attribute\PublicRoute;
use App\Service\User\Login\HUB\LoginService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class LoginController extends AbstractController
{
    public function __construct(
        private LoginService $loginService
    ) {
    }

    /*
    * The endpoint called from the HUB frontend to generate a QR code for login.
    * The userPublicId is optional and can be used to notify a specific user via Firebase for auto-login.
    * Firebase notification is handled by the API.
    */
    #[PublicRoute('Public login page used to bootstrap the HUB login flow.')]
    #[Route('login', name: 'instance_login', methods: ["GET"])]
    public function login(
        Request $request
    ): Response {
        $viewData = $this->loginService->buildLoginViewData($request);

        return $this->render('views/users/user-login.html.twig', $viewData->toArray());
    }

    /*
    * Polls the database to check if the user has confirmed the login via JavaScript.
    * action can be 'login' or 'registration' to differentiate between login and registration processes.
    * If login is confirmed, generates a JWT token, sets it in a cookie, and resets the user's allowed status to prevent reuse of the same QR code.
     * If registration is confirmed, simply returns a success message.
    */
    #[PublicRoute('Public polling endpoint for the browser login flow; protected by route-specific logic and CSRF-adjacent flow controls.')]
    #[Route('/login/check', name: 'user_login_check_hub', methods: "GET")]
    public function pollStateByFrontend(
        Request $request
    ): Response {
        return $this->loginService->buildFrontendPollResponse($request);
    }

    /*
    * Logs out the user and clears the JWT cookie when the logout link is clicked.
    * Public at the route layer, but only meaningful when the request carries a valid logout CSRF token and an existing authentication cookie.
    */
    #[PublicRoute('Public logout endpoint; effective only with a valid logout CSRF token and an existing JWT cookie.')]
    #[CsrfProtectedRoute('Mutating browser logout route requires a valid logout CSRF token.', tokenId: 'userLogout')]
    #[Route('/user-logout', name: 'instance_logout', methods: ["POST"])]
    public function logout(CsrfTokenManagerInterface $csrfTokenManager, Request $request): Response
    {
        $csrfTokenManager->removeToken('userLogout');

        $response = $this->redirectToRoute('home');
        $this->loginService->prepareLogoutResponse($response, $request);

        return $response;
    }
}
