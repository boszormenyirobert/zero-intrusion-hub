<?php

namespace App\Controller\User\Registration\Api;

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

    #[Route('/api/user-registration', name: 'api-user-registration', methods: "POST")]
    public function apiUserRegistration(        
        Request $request
        ) 
    {       
        $headers =  $request->headers->all();

        $corporateIentification = json_decode($request->getContent(), true);       

        $process = "user_registration"; 

        $corporateIentification['hmac'] = $headers['x-client-auth'];

        $response = $this->userService->getQrCode($process, $corporateIentification);

        return $this->json($response);
    }    

    #[Route('/api/registration/callback', name: 'user_registration_callback', methods: ["POST"])]
    public function systemHubRegistrationCallback(Request $request): JsonResponse
    {
        try {
            $response = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
            $dto = RegistrationProcessDTO::mapFromArrayRegistration($response);

            $this->userService->createUser($dto);
            // and create process !!! to check from frontend

            return new JsonResponse(['status' => 'success', 'data' => 'callback success'], Response::HTTP_OK);
        } catch (\JsonException $e) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Invalid JSON payload: ' . $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Server error: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
