<?php

/**
 * HUB view: fetches business subscription data from the backend and decodes the response.
 */

namespace App\Controller\Business\HUB;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Attribute\JwtRequired;
use App\Service\Corporate\SubscriptionService;
use App\Service\Business\HUB\BusinessService;
use App\Service\Instance\HUB\RegistrationMenuAvailabilityService;

class BusinessController extends AbstractController
{
    public function __construct(
        private BusinessService $businessService,
    ) {
    }

    /**
     * Handles authenticated business registration requests.
     * Validates JWT and identifies user by email.
     * Fetches business subscription/account data from backend and decodes the response.
     * Renders the business registration view with subscription and account info.
     * Redirects to login if JWT is invalid or user not found.
     */
    #[JwtRequired]
    #[Route('/business', name: 'business_registration')]
    public function business(
        Request $request,
        SubscriptionService $subscriptionService,
        RegistrationMenuAvailabilityService $registrationMenuAvailabilityService
    ): Response {
        $availabilities = $registrationMenuAvailabilityService->getAvailability($request);
        $businessContext = $this->businessService->resolveBusinessContext($request);

        if ($businessContext === null) {
            return $this->render(
                'views/corporate/business-services.html.twig',
                $this->businessService->buildEmptyBusinessViewData(
                    $availabilities->availabilitySettings
                )->toArray()
            );
        }

        $forms = $this->businessService->buildForms($request);

        $subscriptionData = $this->businessService->handleSubmittedForm(
            $forms,
            $businessContext,
            $request,
            $subscriptionService
        );

        if ($subscriptionData !== null) {
            return $this->redirect($this->generateUrl('account'));
        }

        return $this->render(
            'views/corporate/business-services.html.twig',
            $this->businessService->buildBusinessViewData(
                $businessContext,
                $forms,
                null,
                $availabilities->availabilitySettings
            )->toArray()
        );
    }
}
