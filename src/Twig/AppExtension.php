<?php

namespace App\Twig;

use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use App\Repository\OwnClientRepository;

class AppExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private RequestStack $requestStack,
        private OwnClientRepository $ownClientRepository)
    {       
    }

    public function getGlobals(): array
    {
        $request = $this->requestStack->getCurrentRequest();
        $ownClient = $this->ownClientRepository->findAll();

        return [
            'is_jwt_valid' => $request ? $request->attributes->get('is_jwt_valid', false) : false,
            'own_client_exist' => $ownClient ? true : false
        ];
    }
}
