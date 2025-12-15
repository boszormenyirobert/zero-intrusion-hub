<?php

namespace App\Controller\Nfc;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use App\Controller\User\UserService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Cookie;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use App\DTO\RegistrationProcessDTO;
use Symfony\Component\HttpFoundation\Response;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use App\Service\JWT\JwtService;

class NfcController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger,
        private JWTEncoderInterface $jwtEncoder,
        private UserService $userService,
        JwtService $jwtService,
        private UserRepository $userRepository,
    ) {}

    #[Route('/api/nfc/users', name: 'api_nfc_users', methods: "POST")]
    public function getNfcUsers(
        Request $request,
        JwtService $jwtService
        ) {
            $headers =  $request->headers->all();

            $corporateIentification = json_decode($request->getContent(), true);       

            $process = "api_nfc_users"; 

            $corporateIentification['hmac'] = $headers['x-client-auth'];

                    /** @var Response $response */
        $response = $userRegistrationService->forwardRegistration(
            [
                $process => $corporateIentification,
                'X-Extension-Auth' => $corporateIentification['hmac']
            ]
        );

            return $this->json($response);
        }
}