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
    private bool $loaded = false;

    public function __construct(
        private ContainerBagInterface $params,
        private OwnClientRepository $repository
    ) {
    }

    public function getInstancePublicId(): ?string
    {
        $this->loadOwnClientConfiguration();

        return $this->publicId;
    }

    public function getSecret(): ?string
    {
        $this->loadOwnClientConfiguration();

        return $this->secret;
    }

    public function getCorporateKey(): ?string
    {
        $this->loadOwnClientConfiguration();

        return $this->corporateKey;
    }

    public function getServiceApiKey(): string
    {
        return $this->params->get('SERVICE_API_KEY');
    }
    public function getInstanceDomain(): string
    {
        return $this->params->get('ZERO_INTRUSION_HUB_DOMAIN');
    }
    public function getServiceApiSecret(): string
    {
        return $this->params->get('SERVICE_API_SECRET');
    }

    private function loadOwnClientConfiguration(): void
    {
        if ($this->loaded) {
            return;
        }

        $ownClient = $this->repository->findOneBy([], ['id' => 'ASC']);

        if ($ownClient !== null) {
            $this->publicId = $ownClient->getCorporateId();
            $this->secret = $ownClient->getCorporateIdSecret();
            $this->corporateKey = $ownClient->getCorporateIdKey();
        }

        $this->loaded = true;
    }
}
