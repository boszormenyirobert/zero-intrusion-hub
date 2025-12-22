<?php

namespace App\Controller\User\Registration\HUB;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Controller\User\UserService;
use Psr\Log\LoggerInterface;
use App\DTO\RegistrationProcessDTO;
use App\Attribute\JwtRequired;
use App\Service\User\UserRegistrationService;

class UserRegistrationController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger,
        private UserService $userService
    ) {}

    #[Route('/user-registration', name: 'user-registration', methods: "GET")]
    public function userRegistration() 
    {
        $process = "user_registration"; 

        return $this->render('views/users/user-registration.html.twig', [
            'qrCode' => $this->userService->getQrCode($process, []),
            'menuItem_instanceRegistration' => true
        ]);
    }
}
