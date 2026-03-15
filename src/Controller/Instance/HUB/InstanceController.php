<?php

namespace App\Controller\Instance\HUB;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Psr\Log\LoggerInterface;
use App\Service\JWT\JwtService;

class InstanceController extends AbstractController
{
    /*
    * Home page for the HUB instance, shows the Instance Registration process if the .env variable 
    * ZERO_INTRUSION_FRONTEND_ALLOW_INSTANCE_REGISTRATION is set to true.
    * If JWT token is present in cookies, decodes it to check if it's valid and passes this info to the template.
    */
    #[Route('/', name: 'home')]
    public function home(        
        Request $request,
        JwtService $jwtService,
        LoggerInterface $logger,
        JWTEncoderInterface $jwtEncoder
    ): Response
    {
        $token = $request->cookies->get('jwt_token') ?? '';      
        $payload =  $jwtService->jwtValidation($token);

        $isJwtValid = $payload !== false;
        $userPublicId = $isJwtValid ? ($payload['publicId'] ?? '') : '';
        $userEmail = $isJwtValid ? ($payload['username'] ?? '') : '';

        return $this->render('views/containers/container-home.html.twig',[
            'is_jwt_valid' => $isJwtValid,
            'userPublicId' => $userPublicId,
            'userEmail' => $userEmail,
            'menuItem_instanceRegistration' => (bool)$this->getParameter('ZERO_INTRUSION_FRONTEND_ALLOW_INSTANCE_REGISTRATION')
        ]);
    } 
}