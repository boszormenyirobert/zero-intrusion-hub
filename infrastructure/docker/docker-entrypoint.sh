#!/bin/bash
set -e

load_env_file() {
    if [ -z "$ENV_FILE" ]; then
        return 0
    fi

    if [ ! -f "$ENV_FILE" ]; then
        echo "Environment file not found: $ENV_FILE"
        return 1
    fi

    echo "Loading environment file: $ENV_FILE"
    local sanitized_env_file
    sanitized_env_file=$(mktemp)
    tr -d '\r' < "$ENV_FILE" > "$sanitized_env_file"

    set -a
    # shellcheck disable=SC1090
    . "$sanitized_env_file"
    set +a

    rm -f "$sanitized_env_file"
}

database_url_part() {
    php -r '$url = getenv("DATABASE_URL"); $parts = parse_url($url ?: ""); $key = $argv[1] ?? ""; if ($key === "path") { echo isset($parts["path"]) ? ltrim($parts["path"], "/") : ""; return; } echo $parts[$key] ?? "";' "$1"
}

database_probe() {
    php -r 'try {
        $host = getenv("DATABASE_HOST");
        $port = getenv("DATABASE_PORT") ?: "3306";
        $dbname = getenv("DATABASE_NAME");
        $user = getenv("DATABASE_USER");
        $password = getenv("DATABASE_PASSWORD");
        $dsn = "mysql:host={$host};port={$port}";
        if ($dbname !== false && $dbname !== "") {
            $dsn .= ";dbname={$dbname}";
        }
        new PDO($dsn, $user, $password, [PDO::ATTR_TIMEOUT => 2]);
    } catch (Throwable $exception) {
        fwrite(STDERR, get_class($exception) . ": " . $exception->getMessage());
        exit(1);
    }'
}

wait_for_database() {
    if [ -z "$DATABASE_URL" ]; then
        return 0
    fi

    echo "Waiting for database from DATABASE_URL..."

    local attempts=0
    local max_attempts=30
    local probe_output=""

    while true; do
        attempts=$((attempts + 1))

        set +e
        probe_output=$(DATABASE_HOST=$(database_url_part host) \
            DATABASE_PORT=$(database_url_part port) \
            DATABASE_NAME=$(database_url_part path) \
            DATABASE_USER=$(php -r '$url = getenv("DATABASE_URL"); $parts = parse_url($url ?: ""); echo isset($parts["user"]) ? rawurldecode($parts["user"]) : "";') \
            DATABASE_PASSWORD=$(php -r '$url = getenv("DATABASE_URL"); $parts = parse_url($url ?: ""); echo isset($parts["pass"]) ? rawurldecode($parts["pass"]) : "";') \
            database_probe 2>&1)
        local status=$?
        set -e

        if [ "$status" -eq 0 ]; then
            echo "Database is ready!"
            return 0
        fi

        if printf '%s' "$probe_output" | grep -q 'SQLSTATE\[HY000\] \[1045\]'; then
            echo "Database credentials rejected for user '$(php -r '$url = getenv("DATABASE_URL"); $parts = parse_url($url ?: ""); echo isset($parts["user"]) ? rawurldecode($parts["user"]) : "";')' on host '$(database_url_part host)'."
            echo "$probe_output"
            return 1
        fi

        if [ "$attempts" -ge "$max_attempts" ]; then
            echo "Timed out waiting for database after $max_attempts attempts."
            echo "$probe_output"
            return 1
        fi

        sleep 2
    done
}

load_env_file

# Generate JWT keys if not exist
if [ ! -f config/jwt/private.pem ]; then
    echo "Generating JWT private key..."
    openssl genpkey -algorithm RSA -out config/jwt/private.pem -aes256 -pass pass:ZeroIntrusionLockAndLayeredEncryption -pkeyopt rsa_keygen_bits:4096
    chown www-data:www-data config/jwt/private.pem
    chmod 600 config/jwt/private.pem
fi
if [ ! -f config/jwt/public.pem ]; then
    echo "Generating JWT public key..."
    openssl rsa -pubout -in config/jwt/private.pem -out config/jwt/public.pem -passin pass:ZeroIntrusionLockAndLayeredEncryption
    chown www-data:www-data config/jwt/public.pem
    chmod 644 config/jwt/public.pem
fi

# Wait for MySQL to be ready before running migrations
if [ -f bin/console ]; then
    wait_for_database
    echo "Running doctrine migrations..."
    # Fix permissions before migrations and cache clear
    chown -R www-data:www-data var || true
    chmod -R 775 var || true
    php bin/console doctrine:migrations:migrate --no-interaction
fi

exec "$@"
