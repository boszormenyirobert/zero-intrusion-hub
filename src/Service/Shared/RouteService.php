<?php

namespace App\Service\Shared;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Psr\Log\LoggerInterface;
use App\Helper\UtilityHelper;
use App\Service\Shared\WhiteListed;

class RouteService {
    public function __construct(private LoggerInterface $logger,private ParameterBagInterface $params) {}

    public function mapRoute(array $dataIntegrity): string
    {
        $routes = $this->getDomainReadRoutes()
                + $this->getDomainDeleteRoutes()
                + $this->getSharedRegistrationRoutes()
                + $this->getVaultReadRoutes()
                + $this->getVaultEditRoutes()
                + $this->getVaultDeleteRoutes()
                + $this->getSystemHubRoutes()
                + $this->getAccountRoutes()
                + $this->getOneTouchRoutes()
                + $this->getCorporateSubscriptionRoute(); // OLD implementation

        $key = array_key_first($dataIntegrity);

        if (!isset($routes[$key])) {
            return '';
        }

        $domain = $this->params->get('ZERO_INTRUSION_DOMAIN');        
        $path1 =  $routes[$key][0];
        $path2 = $routes[$key][1] ?  $routes[$key][1] : '';

        return UtilityHelper::buildPath($domain, $path1, $path2);
    }

    private function getOneTouchRoutes(): array
    {
        $base = $this->params->get('ZERO_INTRUSION_ONE_TOUCH');
        return $this->buildRouteMap($base, [
            'one_touch_qr_identity' => WhiteListed::PATH['qrIdentity'],
            'one_touch_identifier' => 'identifier',
            'one_touch_state' => WhiteListed::PATH['state'],
        ]);
    }

    private function getDomainReadRoutes(): array
    {
        $base = $this->params->get('ZERO_INTRUSION_DOMAIN_READ_BASE');
        return $this->buildRouteMap($base, [
            'domain_read_qr_identity' => WhiteListed::PATH['qrIdentity'],
            'domain_read_credential' => WhiteListed::PATH['credential'],
            'domain_read_credential_encrypted' => 'credential/decrypted',
            'domain_read_state' => WhiteListed::PATH['state'],
        ]);
    }

    private function getDomainDeleteRoutes(): array
    {
        $base = $this->params->get('ZERO_INTRUSION_DOMAIN_DELETE_BASE');
        return $this->buildRouteMap($base, [
            'domain_delete_qr_identity' => WhiteListed::PATH['qrIdentity'],
            'domain_delete_credential' => WhiteListed::PATH['credential'],
            'domain_delete_state' => WhiteListed::PATH['state'],
        ]);
    }

    private function getSharedRegistrationRoutes(): array
    {
        $base = $this->params->get('ZERO_INTRUSION_SHARED_BASE');
        return $this->buildRouteMap($base, [
            'shared_registration_qr_identity' => WhiteListed::PATH['qrIdentity'],
            'shared_registration_new_to_encrypt' => WhiteListed::PATH['to_encrypt'],
            'shared_registration_new' => WhiteListed::PATH['new'],
            'shared_registration_state' => WhiteListed::PATH['state'],
        ]);
    }

    private function getVaultReadRoutes(): array {
        $base = $this->params->get('ZERO_INTRUSION_VAULT_READ_BASE');
        return $this->buildRouteMap($base, [
            'vault_read_qr_identity' => WhiteListed::PATH['qrIdentity'],
            'vault_read_credential' => WhiteListed::PATH['credential'],
            'vault_read_credential_encrypted' => 'credential/decrypted',
            'vault_read_state' => WhiteListed::PATH['state'],
        ]);
    }

    private function getVaultEditRoutes(): array {
        $base = $this->params->get('ZERO_INTRUSION_VAULT_EDIT_BASE');
        return $this->buildRouteMap($base, [
            'vault_edit_qr_identity' => WhiteListed::PATH['qrIdentity'],
            'vault_edit_credential' => WhiteListed::PATH['credential'],
            'vault_edit_state' => WhiteListed::PATH['state'],
        ]);
    }

    private function getVaultDeleteRoutes(): array {
        $base = $this->params->get('ZERO_INTRUSION_VAULT_DELETE_BASE');
        return $this->buildRouteMap($base, [
            'vault_delete_qr_identity' => WhiteListed::PATH['qrIdentity'],
            'vault_delete_credential' => WhiteListed::PATH['credential'],
            'vault_delete_state' => WhiteListed::PATH['state'],
        ]);
    }

    private function buildRouteMap(string $base, array $keyToPath): array
    {
        $result = [];
        foreach ($keyToPath as $key => $path) {
            $result[WhiteListed::ALLOWED_INTEGRITY_KEYS[$key]] = [$base, $path];
        }
        return $result;
    }

    private function getCorporateSubscriptionRoute(): array {
        return [
            WhiteListed::ALLOWED_INTEGRITY_KEYS['business_create'] => ["/api/registration/corporate","/business/create"],            
            WhiteListed::ALLOWED_INTEGRITY_KEYS['getIdentity'] => ["/api/registration/corporate","/identity/create/initialize"],     
            WhiteListed::ALLOWED_INTEGRITY_KEYS['updateIdentity'] => ["/api/registration/corporate","/identity/create/follow-up"],
            WhiteListed::ALLOWED_INTEGRITY_KEYS['firstSecret'] => ["/api/secret","/new"],
            WhiteListed::ALLOWED_INTEGRITY_KEYS['recoverySettings'] => ["/api/secret","/recovery-settings"],
            WhiteListed::ALLOWED_INTEGRITY_KEYS['replaceDevice'] => ["/api/device","/replace"],
            WhiteListed::ALLOWED_INTEGRITY_KEYS['restorePin'] => ["/api/device","/replace/pin"],
            WhiteListed::ALLOWED_INTEGRITY_KEYS['browserRegistrationVaultIdentity'] => ["/api/vault","/registration/browser-identity"]
        ];
    }
    
    private function getSystemHubRoutes(): array
    {
        $base = $this->params->get('ZERO_INTRUSION_SYSTEM_HUB_BASE');

        return [
            WhiteListed::ALLOWED_INTEGRITY_KEYS['user_registration'] => [$base, '/registration' . WhiteListed::PATH['qrIdentity']],
            WhiteListed::ALLOWED_INTEGRITY_KEYS['user_login'] => [$base, '/login' . WhiteListed::PATH['qrIdentity']],
            WhiteListed::ALLOWED_INTEGRITY_KEYS['api_nfc_users'] => ['/api/nfc' , WhiteListed::PATH['users']],
            WhiteListed::ALLOWED_INTEGRITY_KEYS['api_nfc_decrypt'] => ['/api/nfc' , 'decrypt']            
        ];
    }

    private function getAccountRoutes(): array
    {
        $base = $this->params->get('ZERO_INTRUSION_ACCOUNT');

        return [
            WhiteListed::ALLOWED_INTEGRITY_KEYS['get_registrated_business'] => [$base, '/all']           
        ];
    }    

    private function waitingForRefactoring(): array
    {
        return [
            // Corporate SubscriptionService
            'getIdentity' => ['EASYLOGIN_SERVICE_REGISTRATION_PARTNER', 'EASYLOGIN_SERVICE_IDENTITY'],
            'updateIdentity' => ['EASYLOGIN_SERVICE_REGISTRATION_PARTNER', 'EASYLOGIN_SERVICE_REGISTRATION_NEW'],
            'firstSecret' => ['EASYLOGIN_SERVICE_SECRETMANAGER', 'EASYLOGIN_SERVICE_SECRETMANAGER_NEW'],
            'recoverySettings' => ['EASYLOGIN_SERVICE_SECRETMANAGER', 'EASYLOGIN_SERVICE_SECRETMANAGER_RECOVER_SETTINGS'],
            'replaceDevice' => ['EASYLOGIN_SERVICE_DEVICE', 'EASYLOGIN_SERVICE_DEVICE_REPLACE'],
            'restorePin' => ['EASYLOGIN_SERVICE_DEVICE', 'EASYLOGIN_SERVICE_DEVICE_REPLACE_PIN']
        ];
    }
}
