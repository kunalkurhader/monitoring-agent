#!/usr/bin/env bash

set -Eeuo pipefail

PHP_MIN_VERSION="8.3"
NODE_MAJOR="22"
APP_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
APP_USER="${SUDO_USER:-$(id -un)}"
SKIP_BUILD=false
INSTALL_MYSQL_SERVER=false
INSTALL_POSTGRESQL_SERVER=false

usage() {
    cat <<'EOF'
Usage: sudo ./install.sh [options]

Installs the Linux dependencies required by the Laravel application and
Java monitoring agent, then installs and builds the project dependencies.

Options:
  --with-mysql-server       Install a local MySQL server
  --with-postgresql-server  Install a local PostgreSQL server
  --skip-build              Install system packages only
  --app-user USER           User that should own and build the application
  -h, --help                Show this help

No database server is installed by default. PHP drivers for MySQL and
PostgreSQL are always installed so remote services such as Amazon RDS work.

Supported distributions: Ubuntu and Debian (APT-based).
EOF
}

log() {
    printf '\n\033[1;32m==>\033[0m %s\n' "$*"
}

die() {
    printf '\nError: %s\n' "$*" >&2
    exit 1
}

while (($#)); do
    case "$1" in
        --skip-build)
            SKIP_BUILD=true
            ;;
        --with-mysql-server)
            INSTALL_MYSQL_SERVER=true
            ;;
        --with-postgresql-server)
            INSTALL_POSTGRESQL_SERVER=true
            ;;
        --app-user)
            shift
            (($#)) || die "--app-user requires a username"
            APP_USER="$1"
            ;;
        -h|--help)
            usage
            exit 0
            ;;
        *)
            die "Unknown option: $1 (use --help)"
            ;;
    esac
    shift
done

[[ "$(uname -s)" == "Linux" ]] || die "This installer is for Linux."
[[ "${EUID}" -eq 0 ]] || die "Run this script with sudo: sudo ./install.sh"
[[ -r /etc/os-release ]] || die "Cannot identify the Linux distribution."

# shellcheck disable=SC1091
source /etc/os-release
case "${ID:-}" in
    ubuntu|debian) ;;
    *) die "Unsupported distribution '${ID:-unknown}'. Ubuntu or Debian is required." ;;
esac

id "$APP_USER" >/dev/null 2>&1 || die "Application user '$APP_USER' does not exist."

export DEBIAN_FRONTEND=noninteractive

apt_install() {
    apt-get install -y --no-install-recommends "$@"
}

version_at_least() {
    dpkg --compare-versions "$1" ge "$2"
}

run_as_app_user() {
    if [[ "$APP_USER" == "root" ]]; then
        "$@"
    else
        runuser -u "$APP_USER" -- "$@"
    fi
}

log "Refreshing APT package metadata"
apt-get update
apt_install ca-certificates curl gnupg unzip git software-properties-common

if ! apt-cache show php8.3-cli >/dev/null 2>&1; then
    if [[ "$ID" == "ubuntu" ]]; then
        log "Enabling the PHP packages repository (PHP ${PHP_MIN_VERSION}+ is required)"
        add-apt-repository -y ppa:ondrej/php
        apt-get update
    else
        die "PHP 8.3 packages are unavailable in the configured Debian repositories. Enable a PHP 8.3+ repository and rerun this script."
    fi
fi

log "Installing Apache, PHP, and Laravel PHP database drivers"
apt_install \
    apache2 \
    php8.3 php8.3-cli libapache2-mod-php8.3 \
    php8.3-bcmath php8.3-curl php8.3-gd php8.3-intl php8.3-mbstring \
    php8.3-mysql php8.3-pgsql php8.3-opcache php8.3-readline php8.3-soap \
    php8.3-sqlite3 php8.3-xml php8.3-zip \
    composer

if [[ "$INSTALL_MYSQL_SERVER" == true ]]; then
    log "Installing the optional local MySQL server"
    apt_install mysql-server
fi

if [[ "$INSTALL_POSTGRESQL_SERVER" == true ]]; then
    log "Installing the optional local PostgreSQL server"
    apt_install postgresql
fi

log "Installing Java 17 and Maven"
apt_install openjdk-17-jdk-headless maven

installed_node_major=0
if command -v node >/dev/null 2>&1; then
    installed_node_major="$(node --version | sed -E 's/^v([0-9]+).*/\1/')"
fi

if ((installed_node_major < NODE_MAJOR)); then
    log "Installing Node.js ${NODE_MAJOR}.x for the Vite asset build"
    nodesource_setup="$(mktemp)"
    curl --fail --silent --show-error --location \
        "https://deb.nodesource.com/setup_${NODE_MAJOR}.x" \
        --output "$nodesource_setup"
    bash "$nodesource_setup"
    rm -f "$nodesource_setup"
    apt_install nodejs
fi

log "Enabling and starting Apache"
a2enmod rewrite
systemctl enable --now apache2

if [[ "$INSTALL_MYSQL_SERVER" == true ]]; then
    systemctl enable --now mysql
fi

if [[ "$INSTALL_POSTGRESQL_SERVER" == true ]]; then
    systemctl enable --now postgresql
fi

php_version="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
version_at_least "$php_version" "$PHP_MIN_VERSION" \
    || die "PHP ${PHP_MIN_VERSION}+ is required; installed version is $php_version."

if [[ "$SKIP_BUILD" == false ]]; then
    log "Installing Laravel PHP dependencies"
    cd "$APP_DIR"
    # OCI8 requires Oracle Instant Client, which is installed separately only
    # on hosts that use Oracle. MySQL/PostgreSQL installs must remain usable.
    run_as_app_user composer install --no-interaction --prefer-dist --optimize-autoloader \
        --ignore-platform-req=ext-oci8

    if [[ ! -f .env && -f .env.example ]]; then
        run_as_app_user cp .env.example .env
    fi

    if [[ -f .env ]] && ! grep -Eq '^APP_KEY=base64:.+' .env; then
        run_as_app_user php artisan key:generate --force
    fi

    log "Installing and building frontend dependencies"
    if [[ -f package-lock.json ]]; then
        run_as_app_user npm ci
    else
        run_as_app_user npm install
    fi
    run_as_app_user npm run build

    log "Building the Java monitoring agent"
    run_as_app_user mvn -f agent/pom.xml clean package

    install -d -o "$APP_USER" -g www-data \
        storage/framework/cache/data storage/framework/sessions \
        storage/framework/views storage/logs bootstrap/cache
    chown -R "$APP_USER":www-data storage bootstrap/cache
    chmod -R ug+rwX storage bootstrap/cache

    log "Installing the Laravel scheduler for one-minute website checks"
    scheduler_file="/etc/cron.d/monitoring-agent-scheduler"
    printf '* * * * * %s cd %s && /usr/bin/php artisan schedule:run >/dev/null 2>&1\n' "$APP_USER" "$APP_DIR" >"$scheduler_file"
    chmod 0644 "$scheduler_file"
fi

log "Installation complete"
database_summary="  Database servers: none (connect to a remote service)"
database_next_step="  1. Enter your remote database endpoint in the web setup wizard"

if [[ "$INSTALL_MYSQL_SERVER" == true && "$INSTALL_POSTGRESQL_SERVER" == true ]]; then
    database_summary="  Local database servers: MySQL and PostgreSQL"
    database_next_step="  1. Secure MySQL and create the required local database/user"
elif [[ "$INSTALL_MYSQL_SERVER" == true ]]; then
    database_summary="  Local database server: $(mysql --version)"
    database_next_step="  1. Secure MySQL: sudo mysql_secure_installation"
elif [[ "$INSTALL_POSTGRESQL_SERVER" == true ]]; then
    database_summary="  Local database server: $(psql --version)"
    database_next_step="  1. Create the required PostgreSQL database and user"
fi

cat <<EOF

Installed:
  PHP $(php -r 'echo PHP_VERSION;') and Laravel extensions
  Apache $(apache2 -v | sed -n '1s/.*Apache\\///p')
$database_summary
  Java $(java -version 2>&1 | sed -n '1p')
  Maven $(mvn -version | sed -n '1p')
  Node.js $(node --version)
  Composer $(composer --version 2>/dev/null)

Next steps:
$database_next_step
  2. Point the Apache VirtualHost DocumentRoot to ${APP_DIR}/public
  3. Open the Laravel base URL and complete the web setup wizard
  4. For Oracle, install Oracle Instant Client and PHP OCI8 before opening setup
  5. Configure the agent using the command documented in agent/README.md
  6. Laravel scheduler: installed in /etc/cron.d/monitoring-agent-scheduler
EOF
