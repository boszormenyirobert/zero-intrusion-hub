<?php

namespace App\Controller\Business;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Attribute\JwtRequired;
use App\Service\Corporate\SubscriptionService;
use App\Form\BusinessRequesterType;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use App\Form\IdentityRequesterType;

class BusinessBasicController extends AbstractController
{
    #[JwtRequired]
    #[Route('/business', name: 'business_registration')]
    public function business(
    Request $request,
    SubscriptionService $subscriptionService,
    JWTEncoderInterface $jwtEncoder
    ): Response 
    {   

        $values = [
            'pswManager'    => 'psw_manager',
            'biometric'     => 'biometric',
            'businessBasic' => 'business_basic', 
            'businessPlus' => 'business_plus', 
            'businessPro' => 'business_pro'
        ];

        $forms = [];
        foreach ($values as $key => $val) {
            $forms[$key] = $this->createForm(BusinessRequesterType::class, ['businessModel' => $key], ['csrf_token_id' => $val]);
            $forms[$key]->handleRequest($request);
        }

        $process = "business_create";
        $subscriptionData = null;
        foreach ($forms as $form) {
            if ($form->isSubmitted() && $form->isValid()) {
                $jwtTokenEncoded = $request->cookies->get('jwt_token') ?? '';   
                $jwt_token = $jwtEncoder->decode($jwtTokenEncoded);

                $validatedInput = $form->getData();
                $subscriptionData = $subscriptionService->getSubscriptionData($process, $validatedInput['businessModel'],'external', $jwt_token['publicId']);
                
                return $this->redirect($this->generateUrl('account'));
            }
        }

        return $this->render(
            'views/corporate/business-services.html.twig',
            [
                'form_psw_manager' => $forms['pswManager']->createView(),
                'form_biometric' => $forms['biometric']->createView(),
                'form_business_basic' => $forms['businessBasic']->createView(),
                'form_business_plus' => $forms['businessPlus']->createView(),
                'form_business_pro' => $forms['businessPro']->createView(),
                'service_auth_data' => $subscriptionData,
                'menuItem_instanceRegistration' => (bool)$this->getParameter('ZERO_INTRUSION_FRONTEND_ALLOW_INSTANCE_REGISTRATION')
            ]
        );
    }
}
