<?php

namespace App\Service\Shared;

class WhiteListed {
    public const PATH = [
        'qrIdentity' => '/qr-identity',
        'to_encrypt' => '/new/to-encrypt',
        'new' => '/new',
        'state' => '/state',
        'credential' => '/credential',
        'getIdentity' => '/identity/create/initialize',
        'users' => '/users'
    ];

    public const ALLOWED_INTEGRITY_KEYS = [        
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
        'vault_read_credential_encrypted' => 'vault_read_credential_encrypted',
        'vault_delete_state' => 'vault_delete_state',        

        'vault_edit_qr_identity' => 'vault_edit_qr_identity',
        'vault_edit_credential' => 'vault_edit_credential',
        'vault_edit_state' => 'vault_edit_state',     

        'shared_registration_qr_identity' => 'shared_registration_qr_identity',
        'shared_registration_new_to_encrypt' => 'shared_registration_new_to_encrypt',
        'shared_registration_new' => 'shared_registration_new',
        'shared_registration_state' => 'shared_registration_state',

        'user_registration' => 'user_registration',
        'user_login' => 'user_login',
        'api_nfc_users' => 'api_nfc_users',
        'api_nfc_decrypt' => 'api_nfc_decrypt',

        'get_registrated_business' => 'get_registrated_business',
        'business_create' => 'business_create',

        'one_touch_qr_identity' => 'one_touch_qr_identity',
        'one_touch_identifier' => 'one_touch_identifier',
        'one_touch_state' => 'one_touch_state',

        // TODO: Missing refactoring
        'getIdentity' => 'getIdentity',
        'updateIdentity' => 'updateIdentity',
        'firstSecret' => 'firstSecret',
        'recoverySettings' => 'recoverySettings',
        'replaceDevice' => 'replaceDevice',
        'restorePin' => 'restorePin',
        'browserRegistrationVaultIdentity' => 'browserRegistrationVaultIdentity'
    ];

    public const NEW_ALLOWED_INTEGRITY_KEYS = [
      // ?? Can I delete after test  'userRegistrationHash' => 'shared_registration_state'
    ];
}
