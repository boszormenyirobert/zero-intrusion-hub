<?php

namespace App\Service\Shared;

final class ProcessKey
{
    public const DOMAIN_READ_QR_IDENTITY = 'domain_read_qr_identity';
    public const DOMAIN_READ_CREDENTIAL = 'domain_read_credential';
    public const DOMAIN_READ_CREDENTIAL_ENCRYPTED = 'domain_read_credential_encrypted';
    public const DOMAIN_READ_STATE = 'domain_read_state';
    public const DOMAIN_DELETE_QR_IDENTITY = 'domain_delete_qr_identity';
    public const DOMAIN_DELETE_CREDENTIAL = 'domain_delete_credential';
    public const DOMAIN_DELETE_STATE = 'domain_delete_state';
    public const VAULT_READ_QR_IDENTITY = 'vault_read_qr_identity';
    public const VAULT_READ_CREDENTIAL = 'vault_read_credential';
    public const VAULT_READ_CREDENTIAL_ENCRYPTED = 'vault_read_credential_encrypted';
    public const VAULT_READ_STATE = 'vault_read_state';
    public const VAULT_EDIT_QR_IDENTITY = 'vault_edit_qr_identity';
    public const VAULT_EDIT_CREDENTIAL = 'vault_edit_credential';
    public const VAULT_EDIT_STATE = 'vault_edit_state';
    public const VAULT_DELETE_QR_IDENTITY = 'vault_delete_qr_identity';
    public const VAULT_DELETE_CREDENTIAL = 'vault_delete_credential';
    public const VAULT_DELETE_STATE = 'vault_delete_state';
    public const SHARED_REGISTRATION_QR_IDENTITY = 'shared_registration_qr_identity';
    public const SHARED_REGISTRATION_NEW_TO_ENCRYPT = 'shared_registration_new_to_encrypt';
    public const SHARED_REGISTRATION_NEW = 'shared_registration_new';
    public const SHARED_REGISTRATION_STATE = 'shared_registration_state';
    public const USER_REGISTRATION = 'user_registration';
    public const USER_LOGIN = 'user_login';
    public const API_NFC_USERS = 'api_nfc_users';
    public const API_NFC_DECRYPT = 'api_nfc_decrypt';
    public const GET_REGISTRATED_BUSINESS = 'get_registrated_business';
    public const BUSINESS_CREATE = 'business_create';
    public const ONE_TOUCH_QR_IDENTITY = 'one_touch_qr_identity';
    public const ONE_TOUCH_IDENTIFIER = 'one_touch_identifier';
    public const ONE_TOUCH_STATE = 'one_touch_state';
    public const GET_IDENTITY = 'getIdentity';
    public const UPDATE_IDENTITY = 'updateIdentity';
    public const FIRST_SECRET = 'firstSecret';
    public const RECOVERY_SETTINGS = 'recoverySettings';
    public const REPLACE_DEVICE = 'replaceDevice';
    public const RESTORE_PIN = 'restorePin';
    public const BROWSER_REGISTRATION_VAULT_IDENTITY = 'browserRegistrationVaultIdentity';

    private function __construct()
    {
    }
}
