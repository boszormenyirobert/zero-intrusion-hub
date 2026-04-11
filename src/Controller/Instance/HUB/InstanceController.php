<?php
/*
 * HUB instance
 * Task: Home page for the HUB instance
 */
namespace App\Controller\Instance\HUB;

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
    ) {}

    /*
    * Home page for the HUB instance, shows the Instance Registration process if the .env variable 
    * If JWT token is present in cookies, decodes it to check if it's valid and passes this info to the template.
    */
    #[Route('/', name: 'home')]
    public function home(        
        Request $request
    ): Response
    {
        return $this->render(
            'views/containers/container-home.html.twig',
            $this->instanceService->buildHomeViewData(
                $request                
            )
        );
    } 

    /*
    * Settings page for the HUB instance, allows to enable/disable the Instance Registration process by the initialization state
    */
    #[Route('/settings', name: 'settings')]
    public function settings(        
        Request $request,
        RegistrationMenuAvailabilityService $registrationMenuAvailabilityService,
        SettingsFormHandler $settingsFormHandler
    ): Response
    {       
        $availabilities = $registrationMenuAvailabilityService->getAvailability($request);

        if ( $availabilities['availability_settings'] === false ) {
            return $this->redirectToRoute('home');
        }

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

        return $this->render('views/containers/container-settings.html.twig', $viewData);

    }
    
    /*
    * Access page for the HUB instance, add/remove "whitelisted" users to allow them to register in the instance
    */
    #[Route('/access', name: 'access')]
    public function access(        
        Request $request,
        RegistrationMenuAvailabilityService $registrationMenuAvailabilityService,
        WhitelistedUserFormHandler $whitelistedUserFormHandler
    ): Response
    {
        $availabilities = $registrationMenuAvailabilityService->getAvailability($request);
       
        if ( $availabilities['availability_users'] === false ) {
            return $this->redirectToRoute('home');
        }

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
            )
        );
    }

    #[Route('/access/{id}/status', name: 'access_user_status', methods: ['POST'])]
    public function updateWhitelistedUserStatus(
        int $id,
        Request $request,
        RegistrationMenuAvailabilityService $registrationMenuAvailabilityService,
        WhitelistedUserFormHandler $whitelistedUserFormHandler
    ): Response
    {
        $availabilities = $registrationMenuAvailabilityService->getAvailability($request);

        if ($availabilities['availability_users'] === false) {
            return $this->redirectToRoute('home');
        }

        if (!$this->isCsrfTokenValid('whitelisted_user_status_' . $id, (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('access');
        }

        $whitelistedUserFormHandler->updateStatus($id, $request->request->getBoolean('active'));

        return $this->redirectToRoute('access');
    }

    #[Route('/access/{id}/delete', name: 'access_user_delete', methods: ['POST'])]
    public function deleteWhitelistedUser(
        int $id,
        Request $request,
        RegistrationMenuAvailabilityService $registrationMenuAvailabilityService,
        WhitelistedUserFormHandler $whitelistedUserFormHandler
    ): Response
    {
        $availabilities = $registrationMenuAvailabilityService->getAvailability($request);

        if ($availabilities['availability_users'] === false) {
            return $this->redirectToRoute('home');
        }

        if (!$this->isCsrfTokenValid('whitelisted_user_delete_' . $id, (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('access');
        }

        $whitelistedUserFormHandler->delete($id);

        return $this->redirectToRoute('access');
    }
}