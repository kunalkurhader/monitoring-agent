#!/usr/bin/env bash

set -Eeuo pipefail

PHP_MIN_VERSION="8.3"
NODE_MAJOR="22"
APP_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
APP_USER="${SUDO_USER:-$(id -un)}"
SKIP_BUILD=false
INSTALL_MYSQL_SERVER=false
INSTALL_POSTGRESQL_SERVER=false
DISTRO_FAMILY=""
PACKAGE_MANAGER=""
APACHE_SERVICE=""
APACHE_COMMAND=""
WEB_GROUP=""
MYSQL_SERVICE=""
POSTGRESQL_SERVICE=""

usage() {
    cat <<'EOF'
Usage: sudo ./install.sh [options]

Installs the Linux dependencies required by the Laravel application and
Java monitoring agent, then installs and builds the project dependencies.

Options:
  --with-mysql-server       Install a local MySQL-compatible server
  --with-postgresql-server  Install a local PostgreSQL server
  --skip-build              Install system packages only
  --app-user USER           User that should own and build the application
  -h, --help                Show this help

No database server is installed by default. PHP drivers for MySQL and
PostgreSQL are always installed so remote services such as Amazon RDS work.

Supported families:
  Debian, Ubuntu, RHEL, CentOS Stream, Rocky Linux, AlmaLinux, Fedora,
  Amazon Linux 2023, and compatible APT/DNF derivatives.
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
        --skip-build) SKIP_BUILD=true ;;
        --with-mysql-server) INSTALL_MYSQL_SERVER=true ;;
        --with-postgresql-server) INSTALL_POSTGRESQL_SERVER=true ;;
        --app-user)
            shift
            (($#)) || die "--app-user requires a username"
            APP_USER="$1"
            ;;
        -h|--help) usage; exit 0 ;;
        *) die "Unknown option: $1 (use --help)" ;;
    esac
    shift
done

[[ "$(uname -s)" == "Linux" ]] || die "This installer is for Linux."
[[ "${EUID}" -eq 0 ]] || die "Run this script with sudo: sudo ./install.sh"
[[ -r /etc/os-release ]] || die "Cannot identify the Linux distribution."

# shellcheck disable=SC1091
source /etc/os-release
ID_LIKE_VALUE=" ${ID_LIKE:-} "
case "${ID:-}" in
    ubuntu|debian|linuxmint|pop) DISTRO_FAMILY="debian" ;;
    rhel|centos|rocky|almalinux|ol) DISTRO_FAMILY="rhel" ;;
    fedora) DISTRO_FAMILY="fedora" ;;
    amzn) DISTRO_FAMILY="amazon" ;;
    *)
        if [[ "$ID_LIKE_VALUE" == *" debian "* || "$ID_LIKE_VALUE" == *" ubuntu "* ]]; then
            DISTRO_FAMILY="debian"
        elif [[ "$ID_LIKE_VALUE" == *" rhel "* || "$ID_LIKE_VALUE" == *" fedora "* || "$ID_LIKE_VALUE" == *" centos "* ]]; then
            DISTRO_FAMILY="rhel"
        else
            die "Unsupported distribution '${PRETTY_NAME:-${ID:-unknown}}'. An APT- or DNF-compatible Linux distribution is required."
        fi
        ;;
esac

id "$APP_USER" >/dev/null 2>&1 || die "Application user '$APP_USER' does not exist."

version_at_least() {
    [[ "$(printf '%s\n' "$2" "$1" | sort -V | head -n 1)" == "$2" ]]
}

run_as_app_user() {
    if [[ "$APP_USER" == "root" ]]; then
        "$@"
    else
        runuser -u "$APP_USER" -- "$@"
    fi
}

apt_install() {
    apt-get install -y --no-install-recommends "$@"
}

dnf_install() {
    "$PACKAGE_MANAGER" install -y "$@"
}

enable_debian_php_repository() {
    if apt-cache show php8.3-cli >/dev/null 2>&1; then
        return
    fi

    log "Enabling a PHP ${PHP_MIN_VERSION} package repository"
    if [[ "${ID:-}" == "ubuntu" || "${ID_LIKE:-}" == *ubuntu* ]]; then
        apt_install software-properties-common
        add-apt-repository -y ppa:ondrej/php
    else
        apt_install ca-certificates curl lsb-release
        install -d -m 0755 /etc/apt/keyrings
        curl --fail --silent --show-error --location https://packages.sury.org/php/apt.gpg -o /etc/apt/keyrings/sury-php.gpg
        codename="${VERSION_CODENAME:-$(lsb_release -sc)}"
        printf 'deb [signed-by=/etc/apt/keyrings/sury-php.gpg] https://packages.sury.org/php/ %s main\n' "$codename" >/etc/apt/sources.list.d/sury-php.list
    fi
    apt-get update
    apt-cache show php8.3-cli >/dev/null 2>&1 || die "PHP ${PHP_MIN_VERSION} packages are unavailable for ${PRETTY_NAME:-this distribution}."
}

enable_remi_php_repository() {
    if command -v php >/dev/null 2>&1 && version_at_least "$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')" "$PHP_MIN_VERSION"; then
        return
    fi

    local major="${VERSION_ID%%.*}"
    [[ "$major" =~ ^[89]$ ]] || die "${PRETTY_NAME:-RHEL-compatible Linux} requires an available PHP ${PHP_MIN_VERSION}+ repository. Supported major releases are 8 and 9."
    log "Enabling Remi PHP ${PHP_MIN_VERSION} packages"
    dnf_install "https://dl.fedoraproject.org/pub/epel/epel-release-latest-${major}.noarch.rpm"
    dnf_install "https://rpms.remirepo.net/enterprise/remi-release-${major}.rpm"
    "$PACKAGE_MANAGER" module reset -y php
    "$PACKAGE_MANAGER" module enable -y php:remi-8.3
}

install_composer() {
    command -v composer >/dev/null 2>&1 && return
    log "Installing Composer"
    local installer signature expected
    installer="$(mktemp)"
    signature="$(mktemp)"
    curl --fail --silent --show-error --location https://getcomposer.org/installer -o "$installer"
    curl --fail --silent --show-error --location https://composer.github.io/installer.sig -o "$signature"
    expected="$(php -r "echo hash_file('sha384', '$installer');")"
    [[ "$expected" == "$(<"$signature")" ]] || die "Composer installer signature verification failed."
    php "$installer" --quiet --install-dir=/usr/local/bin --filename=composer
    rm -f "$installer" "$signature"
}

install_nodesource_node() {
    local installed_node_major=0 setup_script
    if command -v node >/dev/null 2>&1; then
        installed_node_major="$(node --version | sed -E 's/^v([0-9]+).*/\1/')"
    fi
    ((installed_node_major >= NODE_MAJOR)) && return

    log "Installing Node.js ${NODE_MAJOR}.x"
    setup_script="$(mktemp)"
    if [[ "$DISTRO_FAMILY" == "debian" ]]; then
        node_setup_url="https://deb.nodesource.com/setup_${NODE_MAJOR}.x"
    else
        node_setup_url="https://rpm.nodesource.com/setup_${NODE_MAJOR}.x"
    fi
    curl --fail --silent --show-error --location "$node_setup_url" -o "$setup_script"
    bash "$setup_script"
    rm -f "$setup_script"
    if [[ "$DISTRO_FAMILY" == "debian" ]]; then apt_install nodejs; else dnf_install nodejs; fi
}

install_debian_dependencies() {
    export DEBIAN_FRONTEND=noninteractive
    PACKAGE_MANAGER="apt-get"
    APACHE_SERVICE="apache2"
    APACHE_COMMAND="apache2"
    WEB_GROUP="www-data"
    MYSQL_SERVICE="mysql"
    POSTGRESQL_SERVICE="postgresql"

    log "Refreshing APT package metadata"
    apt-get update
    apt_install ca-certificates curl gnupg unzip git cron
    enable_debian_php_repository
    log "Installing Apache, PHP, database drivers, Java, and Maven"
    apt_install apache2 php8.3 php8.3-cli libapache2-mod-php8.3 \
        php8.3-bcmath php8.3-curl php8.3-gd php8.3-intl php8.3-mbstring \
        php8.3-mysql php8.3-pgsql php8.3-opcache php8.3-readline php8.3-soap \
        php8.3-sqlite3 php8.3-xml php8.3-zip openjdk-17-jdk-headless maven
    if [[ "$INSTALL_MYSQL_SERVER" == true ]]; then
        if [[ "${ID:-}" == "ubuntu" ]]; then
            apt_install mysql-server
            MYSQL_SERVICE="mysql"
        else
            apt_install default-mysql-server
            MYSQL_SERVICE="mariadb"
        fi
    fi
    if [[ "$INSTALL_POSTGRESQL_SERVER" == true ]]; then apt_install postgresql; fi
}

install_rpm_dependencies() {
    PACKAGE_MANAGER="$(command -v dnf || command -v yum || true)"
    [[ -n "$PACKAGE_MANAGER" ]] || die "DNF or YUM is required."
    APACHE_SERVICE="httpd"
    APACHE_COMMAND="httpd"
    WEB_GROUP="apache"
    MYSQL_SERVICE="mariadb"
    POSTGRESQL_SERVICE="postgresql"

    log "Refreshing RPM package metadata"
    "$PACKAGE_MANAGER" makecache -y
    dnf_install ca-certificates curl unzip git cronie

    if [[ "$DISTRO_FAMILY" == "amazon" ]]; then
        [[ "${VERSION_ID%%.*}" == "2023" ]] || die "Amazon Linux 2023 is required for PHP ${PHP_MIN_VERSION}+ support."
        log "Installing Apache, PHP, database drivers, Java, and Maven"
        dnf_install httpd php8.3 php8.3-cli php8.3-bcmath php8.3-fpm php8.3-gd php8.3-intl \
            php8.3-mbstring php8.3-mysqlnd php8.3-opcache php8.3-pgsql php8.3-process \
            php8.3-soap php8.3-xml php8.3-zip java-17-amazon-corretto-devel maven
        if [[ "$INSTALL_MYSQL_SERVER" == true ]]; then dnf_install mariadb105-server; fi
        if [[ "$INSTALL_POSTGRESQL_SERVER" == true ]]; then
            dnf_install postgresql15-server
            POSTGRESQL_SERVICE="postgresql"
        fi
    else
        if [[ "$DISTRO_FAMILY" == "rhel" ]]; then enable_remi_php_repository; fi
        log "Installing Apache, PHP, database drivers, Java, and Maven"
        dnf_install httpd php php-cli php-bcmath php-gd php-intl php-mbstring php-mysqlnd \
            php-opcache php-pgsql php-process php-soap php-xml php-zip java-17-openjdk-devel maven
        if [[ "$INSTALL_MYSQL_SERVER" == true ]]; then dnf_install mariadb-server; fi
        if [[ "$INSTALL_POSTGRESQL_SERVER" == true ]]; then dnf_install postgresql-server; fi
    fi
}

if [[ "$DISTRO_FAMILY" == "debian" ]]; then
    install_debian_dependencies
else
    install_rpm_dependencies
fi

install_composer
install_nodesource_node

php_version="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
version_at_least "$php_version" "$PHP_MIN_VERSION" || die "PHP ${PHP_MIN_VERSION}+ is required; installed version is $php_version."

log "Enabling and starting Apache"
if [[ "$DISTRO_FAMILY" == "debian" ]]; then a2enmod rewrite; fi
systemctl enable --now "$APACHE_SERVICE"
systemctl enable --now "$(command -v crond >/dev/null 2>&1 && echo crond || echo cron)"

if [[ "$INSTALL_MYSQL_SERVER" == true ]]; then systemctl enable --now "$MYSQL_SERVICE"; fi
if [[ "$INSTALL_POSTGRESQL_SERVER" == true ]]; then
    if [[ "$DISTRO_FAMILY" != "debian" && -x "$(command -v postgresql-setup || true)" && ! -s /var/lib/pgsql/data/PG_VERSION ]]; then
        postgresql-setup --initdb
    fi
    systemctl enable --now "$POSTGRESQL_SERVICE"
fi

if [[ "$SKIP_BUILD" == false ]]; then
    log "Installing Laravel PHP dependencies"
    cd "$APP_DIR"
    run_as_app_user composer install --no-interaction --prefer-dist --optimize-autoloader --ignore-platform-req=ext-oci8

    if [[ ! -f .env && -f .env.example ]]; then run_as_app_user cp .env.example .env; fi
    if [[ -f .env ]] && ! grep -Eq '^APP_KEY=base64:.+' .env; then run_as_app_user php artisan key:generate --force; fi

    log "Installing and building frontend dependencies"
    if [[ -f package-lock.json ]]; then run_as_app_user npm ci; else run_as_app_user npm install; fi
    run_as_app_user npm run build

    log "Building the Java monitoring agent"
    run_as_app_user mvn -f agent/pom.xml clean package

    install -d -o "$APP_USER" -g "$WEB_GROUP" storage/framework/cache/data \
        storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
    chown -R "$APP_USER:$WEB_GROUP" storage bootstrap/cache
    chmod -R ug+rwX storage bootstrap/cache

    log "Installing the Laravel scheduler for one-minute website checks"
    scheduler_file="/etc/cron.d/monitoring-agent-scheduler"
    printf -v app_dir_escaped '%q' "$APP_DIR"
    printf 'SHELL=/bin/bash\n* * * * * %s cd %s && %s artisan schedule:run >/dev/null 2>&1\n' \
        "$APP_USER" "$app_dir_escaped" "$(command -v php)" >"$scheduler_file"
    chmod 0644 "$scheduler_file"
fi

log "Installation complete"
database_summary="  Database servers: none (connect to a remote service)"
database_next_step="  1. Enter your remote database endpoint in the web setup wizard"
if [[ "$INSTALL_MYSQL_SERVER" == true && "$INSTALL_POSTGRESQL_SERVER" == true ]]; then
    database_summary="  Local database servers: MySQL-compatible server and PostgreSQL"
    database_next_step="  1. Secure and create the required local databases/users"
elif [[ "$INSTALL_MYSQL_SERVER" == true ]]; then
    database_summary="  Local database server: MySQL-compatible server"
    database_next_step="  1. Secure the database and create the required local database/user"
elif [[ "$INSTALL_POSTGRESQL_SERVER" == true ]]; then
    database_summary="  Local database server: PostgreSQL"
    database_next_step="  1. Create the required PostgreSQL database and user"
fi

cat <<EOF

Installed on ${PRETTY_NAME:-Linux}:
  PHP $(php -r 'echo PHP_VERSION;') and Laravel extensions
  Apache $($APACHE_COMMAND -v 2>&1 | sed -n '1p')
$database_summary
  Java $(java -version 2>&1 | sed -n '1p')
  Maven $(mvn -version | sed -n '1p')
  Node.js $(node --version)
  Composer $(composer --version 2>/dev/null)

Next steps:
$database_next_step
  2. Point the Apache VirtualHost DocumentRoot to ${APP_DIR}/public
  3. Allow .htaccess overrides (AllowOverride All) for the public directory
  4. Open the Laravel base URL and complete the web setup wizard
  5. For Oracle, install Oracle Instant Client and PHP OCI8 before opening setup
  6. Configure the agent using the command documented in agent/README.md
  7. Laravel scheduler: installed in /etc/cron.d/monitoring-agent-scheduler
EOF
