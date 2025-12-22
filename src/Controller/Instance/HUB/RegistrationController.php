<?php

namespace App\Controller\Instance\HUB;

use App\Form\IdentityRequesterType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\Corporate\SubscriptionService;
use App\Form\CorporateType;
use Psr\Log\LoggerInterface;
use App\Attribute\JwtRequired;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

class RegistrationController extends AbstractController
{
    public function __construct(
        private ContainerBagInterface $params,
        private LoggerInterface $logger
    ) {}

  //  #[JwtRequired]
    #[Route('/instance-registration', name: 'instance_registration')]
    public function instanceRegistration(
        Request $request,
        SubscriptionService $subscriptionService,
    JWTEncoderInterface $jwtEncoder
    ): Response
    {
        $formIdentity = $this->createForm(IdentityRequesterType::class);
        $formIdentity->handleRequest($request);
        
        $process = "getIdentity";
        $businessModel = 'businessPro';

        if ($formIdentity->isSubmitted() && $formIdentity->isValid()) {
            // $jwtTokenEncoded = $request->cookies->get('jwt_token') ?? '';   
            // $jwt_token = $jwtEncoder->decode($jwtTokenEncoded);
        
            $publicId = $this->params->get('INSTALLATION_PUBLIC_ID');
            $this->logger->critical('PublicId for instance registration', ['publicId' => $publicId]);   

            $subscriptionData = $subscriptionService->getSubscriptionData($process, $businessModel, 'internal', $publicId);            
        }
        
        return $this->render('views/containers/container-instance-registration.html.twig', [
            'form_identity_requester' => $formIdentity->createView(),
            'service_auth_data' => $subscriptionData ?? null,
            'path' => 'instance_registration',
            'menuItem_instanceRegistration' => (bool)$this->getParameter('ZERO_INTRUSION_FRONTEND_ALLOW_INSTANCE_REGISTRATION')        
        ]);
    }    
    
    #[Route('/instance-registration-external', name: 'instance_registration_external')]
    public function instanceRegistrationExternal(
        Request $request,
        SubscriptionService $subscriptionService,
    JWTEncoderInterface $jwtEncoder
    ): Response
    {
        $formIdentity = $this->createForm(IdentityRequesterType::class);
        $formIdentity->handleRequest($request);
        
        $process = "getIdentity";
        $businessModel = 'businessPro';

        if ($formIdentity->isSubmitted() && $formIdentity->isValid()) {
            $jwtTokenEncoded = $request->cookies->get('jwt_token') ?? '';   
            $jwt_token = $jwtEncoder->decode($jwtTokenEncoded);
            $subscriptionData = $subscriptionService->getSubscriptionData($process, $businessModel, 'external', $jwt_token['publicId']);
                        
            return $this->redirectToRoute('account');
        }
        
        return $this->render('views/containers/container-instance-registration.html.twig', [
            'form_identity_requester' => $formIdentity->createView(),
            'service_auth_data' => $subscriptionData ?? null,
            'path' => 'instance_registration_external',
            'menuItem_instanceRegistration' => (bool)$this->getParameter('ZERO_INTRUSION_FRONTEND_ALLOW_INSTANCE_REGISTRATION')
        ]);
    }        

//    #[JwtRequired]    
    #[Route('/instance-registration-follow-up', name: 'instance_registration_follow_up')]
    public function instanceRegistrationFollowUp(
        Request $request,
        SubscriptionService $subscriptionService
    ): Response
    {
        $formSystemRegistration = $this->createForm(CorporateType::class);
        $formSystemRegistration->handleRequest($request);

        if ($formSystemRegistration->isSubmitted() && $formSystemRegistration->isValid()) {
            $userInputs = $formSystemRegistration->getData();
            $subscriptionService->updateOwnClient($userInputs);
            $subscriptionService->finalizeSubscription($userInputs);

            return $this->redirectToRoute('account');
        }

        return $this->render('views/containers/container-subscription-final.html.twig', [
            'form_identity_followup' =>  $formSystemRegistration->createView(),
            'menuItem_instanceRegistration' => (bool)$this->getParameter('ZERO_INTRUSION_FRONTEND_ALLOW_INSTANCE_REGISTRATION')
          ]);
    }
}