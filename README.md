# Project installation

.env.local
composer install
php bin/console doctrine:migrations:migrate
npm install
npm run build

# create database
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate


# Data Integrity between easypublic and easylogin:

# openssl => aes-256-cbc




find .git/objects/ -type f -empty | xargs rm
git fetch -p
git fsck --full

    1. php bin/console make:entity
    2. php bin/console make:migration
    3. php bin/console doctrine:migrations:migrate


/config/jwt
    openssl genpkey -algorithm RSA -out config/jwt/private.pem -aes256 -pass pass:ZeroIntrusionLockAndLayeredEncryption -pkeyopt rsa_keygen_bits:4096
    openssl rsa -pubout -in config/jwt/private.pem -out config/jwt/public.pem -passin pass:ZeroIntrusionLockAndLayeredEncryption

chmod 644 config/jwt/public.pem      chown www-data:www-data
chmod 600 config/jwt/private.pem     chown www-data:www-data



In case of zero-intrusion-demo-page... use the same ip with different port, than copy the private.pem and public.pem from
this installation. In other case create new.