<?php

namespace App\Service\Shared;

final class RoutePath
{
    public const QR_IDENTITY = '/qr-identity';
    public const TO_ENCRYPT = '/new/to-encrypt';
    public const NEW = '/new';
    public const NEW_SAVE = '/new/save';
    public const STATE = '/state';
    public const CREDENTIAL = '/credential';
    public const CREDENTIAL_DECRYPTED = 'credential/decrypted';
    public const USERS = '/users';
    public const IDENTIFIER = 'identifier';
    public const DECRYPT = 'decrypt';
    public const ALL = '/all';
    public const BUSINESS_CREATE = '/business/create';
    public const IDENTITY_CREATE_INITIALIZE = '/identity/create/initialize';
    public const IDENTITY_CREATE_FOLLOW_UP = '/identity/create/follow-up';
    public const RECOVERY_SETTINGS = '/recovery-settings';
    public const REPLACE = '/replace';
    public const REPLACE_PIN = '/replace/pin';
    public const REGISTRATION_BROWSER_IDENTITY = '/registration/browser-identity';

    private function __construct()
    {
    }
}
