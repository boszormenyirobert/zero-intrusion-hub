<?php
/**
 * HUB VIEW with API call
 */
namespace App\Controller\Instance;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Attribute\JwtRequired;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;

class InstanceController extends AbstractController
{
    public function __construct(
        private JWTEncoderInterface $jwtEncoder
    ) {}

//    #[JwtRequired]    
    #[Route('/', name: 'home')]
    public function contractRequest(        
        Request $request
    ): Response
    {
        $jwt_token = $request->cookies->get('jwt_token') ?? '';      
        if($jwt_token){  
            $payload = $this->jwtEncoder->decode($jwt_token);
        }

        return $this->render('views/containers/container-home.html.twig',[
            'is_jwt_valid' => $payload ?? false,
            'menuItem_instanceRegistration' => (bool)$this->getParameter('ZERO_INTRUSION_FRONTEND_ALLOW_INSTANCE_REGISTRATION')
        ]);
    } 

//    #[JwtRequired]
    #[Route('/instance', name: 'instance')]
    public function instance(): Response
    {        
        return $this->render('partials/instance-description.html.twig');
    } 
}