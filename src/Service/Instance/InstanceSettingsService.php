<?php

namespace App\Service\Instance;

use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use App\Repository\OwnClientRepository;
/**
 * Service for managing and providing instance-level configuration and credentials.
 *
 * Loads and exposes corporate identity, domain, and API secrets for secure server communication.
 * Used throughout the application for accessing instance-specific settings.
 */
class InstanceSettingsService
{
    private ?string $publicId = null;
    private ?string $secret = null;
    private ?string $corporateKey = null;

    public function __construct(
        private ContainerBagInterface $params,
        private OwnClientRepository $repository
    ) {
        // Service registration
        $ownClient = $repository->findOneBy([], ['id' => 'ASC']); 
        if($ownClient){
            $this->publicId = $ownClient->getCorporateId();
            $this->secret = $ownClient->getCorporateIdSecret();
            $this->corporateKey = $ownClient->getCorporateIdKey();
        }
    }

    public function getInstancePublicId(): ?string {
        return $this->publicId;
    }
  
    public function getSecret(): ?string
    {
        return $this->secret;
    }

    public function getCorporateKey(): ?string
    {
        return $this->corporateKey;
    }

    public function getServiceApiKey(): string { return $this->params->get('SERVICE_API_KEY'); }
    public function getInstanceDomain(): string { return $this->params->get('ZERO_INTRUSION_HUB_DOMAIN'); }
    public function getServiceApiSecret(): string { return $this->params->get('SERVICE_API_SECRET'); }
}