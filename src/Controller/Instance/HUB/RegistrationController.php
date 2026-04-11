<?php
/*
 * HUB instance
 * Task:
 * 1. HUB own Instance Registration with 2 steps: request identity and finalize registration.
 *  - /instance-registration
 *  - /instance-registration-follow-up
 * 2. External Domain-Instance Registration with 2 steps: request identity and finalize registration.
 *  - /instance-registration-external
 */
namespace App\Controller\Instance\HUB;

use App\Form\IdentityRequesterType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Form\CorporateType;
use App\Service\Instance\HUB\ExternalInstanceRegistrationHandler;
use App\Service\Instance\HUB\InstanceRegistrationFollowUpHandler;
use App\Service\Instance\HUB\InstanceRegistrationService;
use App\Service\Instance\HUB\InternalInstanceRegistrationHandler;
use App\Service\Instance\HUB\RegistrationMenuAvailabilityService;

class RegistrationController extends AbstractController
{
    public function __construct(
        private InstanceRegistrationService $instanceRegistrationService
    ) {}

    /*
    * Home page for the HUB instance, shows the Instance Registration process if the .env variable 
    * ZERO_INTRUSION_FRONTEND_ALLOW_INSTANCE_REGISTRATION is set to true.   
    * If JWT token is present in cookies, decodes it to check if it's valid and passes this info to the template.
    *
    * This is the first step of the Instance Registration process, where the user can request an identity for their HUB instance
    */
    #[Route('/instance-registration', name: 'instance_registration')]
    public function instanceRegistration(
        Request $request,
        InternalInstanceRegistrationHandler $internalInstanceRegistrationHandler,
        RegistrationMenuAvailabilityService $registrationMenuAvailabilityService
    ): Response
    {
        $formIdentity = $this->createForm(IdentityRequesterType::class);
        $formIdentity->handleRequest($request);

        $subscriptionData = $internalInstanceRegistrationHandler->handle($formIdentity);
        $availabilities = $registrationMenuAvailabilityService->getAvailability($request);

        if ( $availabilities['availability_instance'] === false ) {
            return $this->redirectToRoute('home');
        }

        return $this->render('views/containers/container-instance-registration.html.twig', [
            'form_identity_requester' => $formIdentity->createView(),
            'service_auth_data' => $subscriptionData ?? null,
            'path' => 'instance_registration',
            'availabilities' => $availabilities    
        ]);
    }    
    
    /*
    * Domaine-Instance registration process for external domain
    * The user get the identity for their Domaine-Instance and then can finalize the registration with the follow-up step.
    * The get: corporateIdKey, corporateIdSecret, iv, corporateId and sslPublicKey
    * Through the form data: "domain, callback user login and callback user registration " the user can finalize the registration of 
    * their domain instance in the follow-up step
    */
    #[Route('/instance-registration-external', name: 'instance_registration_external')]
    public function instanceRegistrationExternal(
        Request $request,
        ExternalInstanceRegistrationHandler $externalInstanceRegistrationHandler,
        RegistrationMenuAvailabilityService $registrationMenuAvailabilityService
    ): Response
    {
        $formIdentity = $this->createForm(IdentityRequesterType::class);
        $formIdentity->handleRequest($request);

        if ($externalInstanceRegistrationHandler->handle($formIdentity, $request)) {
            return $this->redirectToRoute('account');
        }

        $availabilities = $registrationMenuAvailabilityService->getAvailability($request);

        if ( $availabilities['availability_instance'] === false ) {
            return $this->redirectToRoute('home');
        }
        
        return $this->render('views/containers/container-instance-registration.html.twig', [
            'form_identity_requester' => $formIdentity->createView(),
            'service_auth_data' => null,
            'path' => 'instance_registration_external',
            'availabilities' => $availabilities
        ]);
    }        

    /**
     * Follow-up step for the Instance Registration process, where the user can finalize their HUB instance registration.
     * This step is only accessible if the initial registration step has been completed.
     */
    #[Route('/instance-registration-follow-up', name: 'instance_registration_follow_up')]
    public function instanceRegistrationFollowUp(
        Request $request,
        InstanceRegistrationFollowUpHandler $instanceRegistrationFollowUpHandler,
        RegistrationMenuAvailabilityService $registrationMenuAvailabilityService
    ): Response
    {
        $formSystemRegistration = $this->createForm(CorporateType::class);
        $formSystemRegistration->handleRequest($request);

        if ($instanceRegistrationFollowUpHandler->handle($formSystemRegistration)) {

            return $this->redirectToRoute('account');
        }

        $availabilities = $registrationMenuAvailabilityService->getAvailability($request);

        if ( $availabilities['availability_instance'] === false ) {
            return $this->redirectToRoute('home');
        }

        return $this->render('views/containers/container-subscription-final.html.twig', [
            'form_identity_followup' =>  $formSystemRegistration->createView(),
            'availabilities' => $availabilities
          ]);
    }
}