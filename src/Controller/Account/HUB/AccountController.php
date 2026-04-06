<?php
/**
 * HUB VIEW: Fetches account data
 */
namespace App\Controller\Account\HUB;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\User\UserRegistrationService;

class AccountController extends AbstractController
{
    public function __construct(
        private AccountService $accountService
    ) {}

    /**
     * Handles authenticated account requests.
     * Validates JWT and identifies user by email.
     * Fetches business subscription/account data from backend and decodes the response.
     * Renders the account view with subscription and account info.
     * If JWT is invalid or user not found, redirects to login (used only once by HUB initialization).
     */
    #[Route('/account', name: 'account')]
    public function account(
        Request $request,
        UserRegistrationService $userRegistrationService
    ): Response 
    { 
        $accountContext = $this->accountService->resolveAccountContext($request);

        if ($accountContext === null) {
            return $this->redirectToLogin();
        }

        $businessSubscription = $this->accountService->loadBusinessSubscription(
            $userRegistrationService,
            $accountContext['user']
        );

        return $this->render(
            'views/containers/container-account.html.twig',
            $this->accountService->buildAccountViewData(
                $accountContext,
                $businessSubscription,
                (bool) $this->getParameter('ZERO_INTRUSION_FRONTEND_ALLOW_INSTANCE_REGISTRATION')
            )
        );
    }

    private function redirectToLogin(): Response
    {
        return $this->redirect($this->generateUrl('instance_login'));
    }
}