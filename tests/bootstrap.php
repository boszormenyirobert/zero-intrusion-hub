<?php

use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\Request;

require dirname(__DIR__).'/vendor/autoload.php';

$_SERVER['APP_ENV'] ??= 'test';
$_ENV['APP_ENV'] ??= 'test';
$_SERVER['TRUSTED_HOSTS'] ??= '.*';
$_ENV['TRUSTED_HOSTS'] ??= '.*';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

Request::setTrustedHosts(['.*']);
