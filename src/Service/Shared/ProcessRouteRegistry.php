<?php

namespace App\Service\Shared;

use App\DTO\BackendRouteDTO;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class ProcessRouteRegistry
{
    public function __construct(
        private ParameterBagInterface $params
    ) {
    }

    public function resolve(string $processKey): ?BackendRouteDTO
    {
        return $this->all()[$processKey] ?? null;
    }

    /**
     * @return array<string, BackendRouteDTO>
     */
    public function all(): array
    {
        return [
            ...$this->domainReadRoutes(),
            ...$this->domainDeleteRoutes(),
            ...$this->sharedRegistrationRoutes(),
            ...$this->vaultReadRoutes(),
            ...$this->vaultEditRoutes(),
            ...$this->vaultDeleteRoutes(),
            ...$this->systemHubRoutes(),
            ...$this->accountRoutes(),
            ...$this->oneTouchRoutes(),
            ...$this->corporateSubscriptionRoutes(),
        ];
    }

    /** @return array<string, BackendRouteDTO> */
    private function oneTouchRoutes(): array
    {
        $base = $this->params->get('ZERO_INTRUSION_ONE_TOUCH');

        return [
            ProcessKey::ONE_TOUCH_QR_IDENTITY => new BackendRouteDTO($base, RoutePath::QR_IDENTITY),
            ProcessKey::ONE_TOUCH_IDENTIFIER => new BackendRouteDTO($base, RoutePath::IDENTIFIER),
            ProcessKey::ONE_TOUCH_STATE => new BackendRouteDTO($base, RoutePath::STATE),
        ];
    }

    /** @return array<string, BackendRouteDTO> */
    private function domainReadRoutes(): array
    {
        $base = $this->params->get('ZERO_INTRUSION_DOMAIN_READ_BASE');

        return [
            ProcessKey::DOMAIN_READ_QR_IDENTITY => new BackendRouteDTO($base, RoutePath::QR_IDENTITY),
            ProcessKey::DOMAIN_READ_CREDENTIAL => new BackendRouteDTO($base, RoutePath::CREDENTIAL),
            ProcessKey::DOMAIN_READ_CREDENTIAL_ENCRYPTED => new BackendRouteDTO($base, RoutePath::CREDENTIAL_DECRYPTED),
            ProcessKey::DOMAIN_READ_STATE => new BackendRouteDTO($base, RoutePath::STATE),
        ];
    }

    /** @return array<string, BackendRouteDTO> */
    private function domainDeleteRoutes(): array
    {
        $base = $this->params->get('ZERO_INTRUSION_DOMAIN_DELETE_BASE');

        return [
            ProcessKey::DOMAIN_DELETE_QR_IDENTITY => new BackendRouteDTO($base, RoutePath::QR_IDENTITY),
            ProcessKey::DOMAIN_DELETE_CREDENTIAL => new BackendRouteDTO($base, RoutePath::CREDENTIAL),
            ProcessKey::DOMAIN_DELETE_STATE => new BackendRouteDTO($base, RoutePath::STATE),
        ];
    }

    /** @return array<string, BackendRouteDTO> */
    private function sharedRegistrationRoutes(): array
    {
        $base = $this->params->get('ZERO_INTRUSION_SHARED_BASE');

        return [
            ProcessKey::SHARED_REGISTRATION_QR_IDENTITY => new BackendRouteDTO($base, RoutePath::QR_IDENTITY),
            ProcessKey::SHARED_REGISTRATION_NEW_TO_ENCRYPT => new BackendRouteDTO($base, RoutePath::TO_ENCRYPT),
            ProcessKey::SHARED_REGISTRATION_NEW => new BackendRouteDTO($base, RoutePath::NEW),
            ProcessKey::SHARED_REGISTRATION_STATE => new BackendRouteDTO($base, RoutePath::STATE),
        ];
    }

    /** @return array<string, BackendRouteDTO> */
    private function vaultReadRoutes(): array
    {
        $base = $this->params->get('ZERO_INTRUSION_VAULT_READ_BASE');

        return [
            ProcessKey::VAULT_READ_QR_IDENTITY => new BackendRouteDTO($base, RoutePath::QR_IDENTITY),
            ProcessKey::VAULT_READ_CREDENTIAL => new BackendRouteDTO($base, RoutePath::CREDENTIAL),
            ProcessKey::VAULT_READ_CREDENTIAL_ENCRYPTED => new BackendRouteDTO($base, RoutePath::CREDENTIAL_DECRYPTED),
            ProcessKey::VAULT_READ_STATE => new BackendRouteDTO($base, RoutePath::STATE),
        ];
    }

    /** @return array<string, BackendRouteDTO> */
    private function vaultEditRoutes(): array
    {
        $base = $this->params->get('ZERO_INTRUSION_VAULT_EDIT_BASE');

        return [
            ProcessKey::VAULT_EDIT_QR_IDENTITY => new BackendRouteDTO($base, RoutePath::QR_IDENTITY),
            ProcessKey::VAULT_EDIT_CREDENTIAL => new BackendRouteDTO($base, RoutePath::CREDENTIAL),
            ProcessKey::VAULT_EDIT_STATE => new BackendRouteDTO($base, RoutePath::STATE),
        ];
    }

    /** @return array<string, BackendRouteDTO> */
    private function vaultDeleteRoutes(): array
    {
        $base = $this->params->get('ZERO_INTRUSION_VAULT_DELETE_BASE');

        return [
            ProcessKey::VAULT_DELETE_QR_IDENTITY => new BackendRouteDTO($base, RoutePath::QR_IDENTITY),
            ProcessKey::VAULT_DELETE_CREDENTIAL => new BackendRouteDTO($base, RoutePath::CREDENTIAL),
            ProcessKey::VAULT_DELETE_STATE => new BackendRouteDTO($base, RoutePath::STATE),
        ];
    }

    /** @return array<string, BackendRouteDTO> */
    private function corporateSubscriptionRoutes(): array
    {
        return [
            ProcessKey::BUSINESS_CREATE => new BackendRouteDTO('/api/registration/corporate', RoutePath::BUSINESS_CREATE),
            ProcessKey::GET_IDENTITY => new BackendRouteDTO('/api/registration/corporate', RoutePath::IDENTITY_CREATE_INITIALIZE),
            ProcessKey::UPDATE_IDENTITY => new BackendRouteDTO('/api/registration/corporate', RoutePath::IDENTITY_CREATE_FOLLOW_UP),
            ProcessKey::FIRST_SECRET => new BackendRouteDTO('/api/secret', RoutePath::NEW),
            ProcessKey::RECOVERY_SETTINGS => new BackendRouteDTO('/api/secret', RoutePath::RECOVERY_SETTINGS),
            ProcessKey::REPLACE_DEVICE => new BackendRouteDTO('/api/device', RoutePath::REPLACE),
            ProcessKey::RESTORE_PIN => new BackendRouteDTO('/api/device', RoutePath::REPLACE_PIN),
            ProcessKey::BROWSER_REGISTRATION_VAULT_IDENTITY => new BackendRouteDTO('/api/vault', RoutePath::REGISTRATION_BROWSER_IDENTITY),
        ];
    }

    /** @return array<string, BackendRouteDTO> */
    private function systemHubRoutes(): array
    {
        $base = $this->params->get('ZERO_INTRUSION_SYSTEM_HUB_BASE');

        return [
            ProcessKey::USER_REGISTRATION => new BackendRouteDTO($base, '/registration' . RoutePath::QR_IDENTITY),
            ProcessKey::USER_LOGIN => new BackendRouteDTO($base, '/login' . RoutePath::QR_IDENTITY),
            ProcessKey::API_NFC_USERS => new BackendRouteDTO('/api/nfc', RoutePath::USERS),
            ProcessKey::API_NFC_DECRYPT => new BackendRouteDTO('/api/nfc', RoutePath::DECRYPT),
        ];
    }

    /** @return array<string, BackendRouteDTO> */
    private function accountRoutes(): array
    {
        $base = $this->params->get('ZERO_INTRUSION_ACCOUNT');

        return [
            ProcessKey::GET_REGISTRATED_BUSINESS => new BackendRouteDTO($base, RoutePath::ALL),
        ];
    }
}
