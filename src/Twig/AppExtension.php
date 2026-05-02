<?php

namespace App\Twig;

use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use App\Repository\OwnClientRepository;
use App\Service\JWT\JwtService;

class AppExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private RequestStack $requestStack,
        private OwnClientRepository $ownClientRepository,
        private JwtService $jwtService
    ) {
    }

    public function getGlobals(): array
    {
        $request = $this->requestStack->getCurrentRequest();
        $ownClient = $this->ownClientRepository->findAll();
        $jwtPayload = $request !== null ? $this->jwtService->extractPayloadFromRequest($request) : null;

        return [
            'is_jwt_valid' => $jwtPayload !== null,
            'own_client_exist' => $ownClient ? true : false
        ];
    }
}
