<?php

namespace App\Service\Shared;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Psr\Log\LoggerInterface;

use App\Helper\UtilityHelper;

class RouteService
    {

    const PATH = [
        'qrIdentity' => '/qr-identity',
        'new' => '/new',
        'state' => '/state',
        'credential' => '/credential',
        'getIdentity' => '/identity/create/initialize'
    ];

    const ALLOWED_INTEGRITY_KEYS = [
        'domain_read_qr_identity' =>'domain_read_qr_identity',
        'domain_read_credential' => 'domain_read_credential', 
        'domain_read_credential_encrypted' => 'domain_read_credential_encrypted',
        'domain_read_state' => 'domain_read_state',

        'vault_read_qr_identity' => 'vault_read_qr_identity',
        'vault_read_credential' => 'vault_read_credential',
        'vault_read_state' => 'vault_read_state',
        
        'domain_delete_qr_identity' => 'domain_delete_qr_identity',
        'domain_delete_credential' => 'domain_delete_credential',
        'domain_delete_state' => 'domain_delete_state',

        'vault_delete_qr_identity' => 'vault_delete_qr_identity',
        'vault_delete_credential' => 'vault_delete_credential',
        'vault_delete_state' => 'vault_delete_state',        

        'vault_edit_qr_identity' => 'vault_edit_qr_identity',
        'vault_edit_credential' => 'vault_edit_credential',
        'vault_edit_state' => 'vault_edit_state',     

        'shared_registration_qr_identity' => 'shared_registration_qr_identity',
        'shared_registration_new' => 'shared_registration_new',
        'shared_registration_state' => 'shared_registration_state',

        'user_registration' => 'user_registration',
        'user_login' => 'user_login',

        'get_registrated_business' => 'get_registrated_business',
        'business_create' => 'business_create',

        // TODO: Missing refactoring
        'getIdentity' => 'getIdentity',
        'updateIdentity' => 'updateIdentity',
        'firstSecret' => 'firstSecret',
        'recoverySettings' => 'recoverySettings',
        'replaceDevice' => 'replaceDevice',
        'restorePin' => 'restorePin',
        'browserRegistrationVaultIdentity' => 'browserRegistrationVaultIdentity'
    ];


    const NEW_ALLOWED_INTEGRITY_KEYS = [
      // ?? Törölhetö teszteles utan  'userRegistrationHash' => 'shared_registration_state'
    ];

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
                + $this->geAccountRoutes()
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

    private function getDomainReadRoutes(): array
    {
        $base = $this->params->get('ZERO_INTRUSION_DOMAIN_READ_BASE');

        return [
            RouteService::ALLOWED_INTEGRITY_KEYS['domain_read_qr_identity'] => [$base, RouteService::PATH['qrIdentity']],
            RouteService::ALLOWED_INTEGRITY_KEYS['domain_read_credential'] => [$base, RouteService::PATH['credential']],
            RouteService::ALLOWED_INTEGRITY_KEYS['domain_read_credential_encrypted'] => [$base, RouteService::PATH['credential/decrypted']],
            RouteService::ALLOWED_INTEGRITY_KEYS['domain_read_state'] => [$base, RouteService::PATH['state']],
        ];
    }   

    private function getDomainDeleteRoutes(): array
    {
        $base = $this->params->get('ZERO_INTRUSION_DOMAIN_DELETE_BASE');

        return [
            RouteService::ALLOWED_INTEGRITY_KEYS['domain_delete_qr_identity'] => [$base, RouteService::PATH['qrIdentity']],
            RouteService::ALLOWED_INTEGRITY_KEYS['domain_delete_credential'] => [$base, RouteService::PATH['credential']],
            RouteService::ALLOWED_INTEGRITY_KEYS['domain_delete_state'] => [$base, RouteService::PATH['state']],
        ];
    }   

    private function getSharedRegistrationRoutes(): array
    {
        $base = $this->params->get('ZERO_INTRUSION_SHARED_BASE');

        return [
            RouteService::ALLOWED_INTEGRITY_KEYS['shared_registration_qr_identity'] => [$base, RouteService::PATH['qrIdentity']],
            RouteService::ALLOWED_INTEGRITY_KEYS['shared_registration_new'] => [$base, RouteService::PATH['new']],
            RouteService::ALLOWED_INTEGRITY_KEYS['shared_registration_state'] => [$base, RouteService::PATH['state']],
        ];
    }

    private function getVaultReadRoutes(){
        $base = $this->params->get('ZERO_INTRUSION_VAULT_READ_BASE');

        return [
            RouteService::ALLOWED_INTEGRITY_KEYS['vault_read_qr_identity'] => [$base, RouteService::PATH['qrIdentity']],
            RouteService::ALLOWED_INTEGRITY_KEYS['vault_read_credential'] => [$base, RouteService::PATH['credential']],
            RouteService::ALLOWED_INTEGRITY_KEYS['vault_read_state'] => [$base, RouteService::PATH['state']],
        ];
    }
    private function getVaultEditRoutes(){
        $base = $this->params->get('ZERO_INTRUSION_VAULT_EDIT_BASE');

        return [
            RouteService::ALLOWED_INTEGRITY_KEYS['vault_edit_qr_identity'] => [$base, RouteService::PATH['qrIdentity']],
            RouteService::ALLOWED_INTEGRITY_KEYS['vault_edit_credential'] => [$base, RouteService::PATH['credential']],
            RouteService::ALLOWED_INTEGRITY_KEYS['vault_edit_state'] => [$base, RouteService::PATH['state']],
        ];
    }    
    private function getVaultDeleteRoutes(){
        $base = $this->params->get('ZERO_INTRUSION_VAULT_DELETE_BASE');

        return [
            RouteService::ALLOWED_INTEGRITY_KEYS['vault_delete_qr_identity'] => [$base, RouteService::PATH['qrIdentity']],
            RouteService::ALLOWED_INTEGRITY_KEYS['vault_delete_credential'] => [$base, RouteService::PATH['credential']],
            RouteService::ALLOWED_INTEGRITY_KEYS['vault_delete_state'] => [$base, RouteService::PATH['state']],
        ];
    }    

    private function getCorporateSubscriptionRoute(){
        return [
            RouteService::ALLOWED_INTEGRITY_KEYS['business_create'] => ["/api/registration/corporate","/business/create"],            
            RouteService::ALLOWED_INTEGRITY_KEYS['getIdentity'] => ["/api/registration/corporate","/identity/create/initialize"],     
            RouteService::ALLOWED_INTEGRITY_KEYS['updateIdentity'] => ["/api/registration/corporate","/identity/create/follow-up"],
            RouteService::ALLOWED_INTEGRITY_KEYS['firstSecret'] => ["/api/secret","/new"],
            RouteService::ALLOWED_INTEGRITY_KEYS['recoverySettings'] => ["/api/secret","/recovery-settings"],
            RouteService::ALLOWED_INTEGRITY_KEYS['replaceDevice'] => ["/api/device","/replace"],
            RouteService::ALLOWED_INTEGRITY_KEYS['restorePin'] => ["/api/device","/replace/pin"],
            RouteService::ALLOWED_INTEGRITY_KEYS['browserRegistrationVaultIdentity'] => ["/api/vault","/registration/browser-identity"]
        ];
    }
    
    private function getSystemHubRoutes(): array
    {
        $base = $this->params->get('ZERO_INTRUSION_SYSTEM_HUB_BASE');

        return [
            RouteService::ALLOWED_INTEGRITY_KEYS['user_registration'] => [$base, '/registration' . RouteService::PATH['qrIdentity']],
            RouteService::ALLOWED_INTEGRITY_KEYS['user_login'] => [$base, '/login' . RouteService::PATH['qrIdentity']],
        ];
    }

    private function geAccountRoutes(): array
    {
        $base = $this->params->get('ZERO_INTRUSION_ACCOUNT');

        return [
            RouteService::ALLOWED_INTEGRITY_KEYS['get_registrated_business'] => [$base, '/all']           
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
