# easypublic


# Data Integrity between easypublic and easylogin:

# openssl => aes-256-cbc




find .git/objects/ -type f -empty | xargs rm
git fetch -p
git fsck --full

    1. php bin/console make:entity
    2. php bin/console make:migration
    3. php bin/console doctrine:migrations:migrate