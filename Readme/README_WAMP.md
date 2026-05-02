# Local project installation WAMP native
```
.env.local
composer install
php bin/console doctrine:database:create --if-not-exists && php bin/console doctrine:migrations:migrate
npm install
npm run build
```
# Use alias. It is crucial to the smoothly network communication.
```
    hosts: (C:\Windows\System32\drivers\etc\hosts)
        127.0.0.0 hub.local
```

# Openssl (aes-256-cbc)
```
/config/jwt
    1. openssl genpkey -algorithm RSA -out config/jwt/private.pem -aes256 -pass pass:ZeroIntrusionLockAndLayeredEncryption -pkeyopt rsa_keygen_bits:4096
    2. openssl rsa -pubout -in config/jwt/private.pem -out config/jwt/public.pem -passin pass:ZeroIntrusionLockAndLayeredEncryption
    3. chmod 644 config/jwt/public.pem      chown www-data:www-data
    4. chmod 600 config/jwt/private.pem     chown www-data:www-data
```

# Communication between Mobile-Apps and WEB-Apps
```
    From localhost machine:
    ssh -N -T -R 0.0.0.0:8085:hub.local:8082 root@${REMOTE_SERVER_IP}
```

# Mobile App, Desktop App and Browser extension build until "Profile Selection is under development"
    0. Detailed descreption is in the API repositry readme
    1. Mobile App .env build with the: ${REMOTE_SERVER_IP}:8085
    2. Desktop App .env build with the: ${REMOTE_SERVER_IP}:8085
    3. Browser extension .env build with the: ${REMOTE_SERVER_IP}:8085

# Install and start Redis (Optional intstall Grafana+Loki)
```
    docker compose -f infrastructure/docker/compose/compose.monitoring.yml build app
    docker compose -f infrastructure/docker/compose/compose.monitoring.yml up -d --force-recreate app
```

# Install API
    Readme is in the API repository