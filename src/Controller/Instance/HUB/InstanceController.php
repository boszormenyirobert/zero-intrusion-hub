<?php

/*
 * HUB instance
 * Task: Home page for the HUB instance
 */

namespace App\Controller\Instance\HUB;

use App\Attribute\CsrfProtectedRoute;
use App\Attribute\InitializationOnlyRoute;
use App\Attribute\InitializationOrJwtRoute;
use App\Attribute\PublicRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Form\InstanceSettingsType;
use App\Form\WhiteListedUserType;
use App\Service\Instance\HUB\InstanceService;
use App\Service\Instance\HUB\RegistrationMenuAvailabilityService;
use App\Service\Instance\HUB\SettingsFormHandler;
use App\Service\Instance\HUB\WhitelistedUserFormHandler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class InstanceController extends AbstractController
{
    public function __construct(
        private InstanceService $instanceService
    ) {
    }

    /*
    * Home page for the HUB instance, shows the Instance Registration process if the .env variable
    * If JWT token is present in cookies, decodes it to check if it's valid and passes this info to the template.
    */
    #[PublicRoute('Public HUB home page used to bootstrap instance flows and render state that may vary depending on JWT presence.')]
    #[Route('/', name: 'home')]
    public function home(
        Request $request
    ): Response {
        return $this->render(
            'views/containers/container-home.html.twig',
            $this->instanceService->buildHomeViewData(
                $request
            )->toArray()
        );
    }

    /*
    * Settings page for the HUB instance, allows to enable/disable the Instance Registration process by the initialization state
    */
    #[InitializationOnlyRoute('Available only while HUB initialization is active; denied after initialization is completed.')]
    #[Route('/settings', name: 'settings')]
    public function settings(
        Request $request,
        RegistrationMenuAvailabilityService $registrationMenuAvailabilityService,
        SettingsFormHandler $settingsFormHandler
    ): Response {
        $availabilities = $registrationMenuAvailabilityService->getAvailability($request);

        $form = $this->createForm(InstanceSettingsType::class);
        $form->handleRequest($request);

        if ($settingsFormHandler->handle($form)) {
            return $this->redirectToRoute('home');
        }

        $viewData = $this->instanceService->buildSettingsViewData(
            $request,
            $availabilities,
            $form->createView()
        );

        return $this->render('views/containers/container-settings.html.twig', $viewData->toArray());

    }

    /*
    * Access page for the HUB instance, add/remove "whitelisted" users to allow them to register in the instance
    */
    #[InitializationOrJwtRoute('Available during initialization, and after initialization only with a valid JWT-backed user context.')]
    #[Route('/access', name: 'access')]
    public function access(
        Request $request,
        RegistrationMenuAvailabilityService $registrationMenuAvailabilityService,
        WhitelistedUserFormHandler $whitelistedUserFormHandler
    ): Response {
        $availabilities = $registrationMenuAvailabilityService->getAvailability($request);

        $form = $this->createForm(WhiteListedUserType::class);
        $form->handleRequest($request);

        if ($whitelistedUserFormHandler->handle($form)) {
            return $this->redirectToRoute('access');
        }

        return $this->render(
            'views/containers/container-users.html.twig',
            $this->instanceService->buildUsersViewData(
                $request,
                $availabilities,
                $form->createView(),
                $whitelistedUserFormHandler->getAll()
            )->toArray()
        );
    }

    #[InitializationOrJwtRoute('Available during initialization, and after initialization only with a valid JWT-backed user context.')]
    #[CsrfProtectedRoute('Mutating route requires a valid CSRF token in addition to the access policy.', tokenId: 'whitelisted_user_status_{id}', failureRoute: 'access')]
    #[Route('/access/{id}/status', name: 'access_user_status', methods: ['POST'])]
    public function updateWhitelistedUserStatus(
        int $id,
        Request $request,
        WhitelistedUserFormHandler $whitelistedUserFormHandler
    ): Response {
        $whitelistedUserFormHandler->updateStatus($id, $request->request->getBoolean('active'));

        return $this->redirectToRoute('access');
    }

    #[InitializationOrJwtRoute('Available during initialization, and after initialization only with a valid JWT-backed user context.')]
    #[CsrfProtectedRoute('Mutating route requires a valid CSRF token in addition to the access policy.', tokenId: 'whitelisted_user_delete_{id}', failureRoute: 'access')]
    #[Route('/access/{id}/delete', name: 'access_user_delete', methods: ['POST'])]
    public function deleteWhitelistedUser(
        int $id,
        Request $request,
        WhitelistedUserFormHandler $whitelistedUserFormHandler
    ): Response {
        $whitelistedUserFormHandler->delete($id);

        return $this->redirectToRoute('access');
    }
}
