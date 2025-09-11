<?php

namespace App\Service\JWT;


use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Attribute\JwtRequired;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;

class JwtService
{
    public function __construct(
        private JWTEncoderInterface $jwtEncoder
    ) {}

    public function jwtValidation(        
        Request $request
    )
    {
        $jwt_token = $request->cookies->get('jwt_token') ?? '';      
        if(!$jwt_token){
            return false;
        }
        
        return $this->jwtEncoder->decode($jwt_token);
    }    
}