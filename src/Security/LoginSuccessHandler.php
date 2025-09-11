<?php
// src/Security/LoginSuccessHandler.php
namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\HttpFoundation\Request;

class LoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function onAuthenticationSuccess(Request $request, TokenInterface $token): JsonResponse
    {
        return new JsonResponse(['status' => 'ok', 'user' => $token->getUser()->getUserIdentifier()]);
    }
}
