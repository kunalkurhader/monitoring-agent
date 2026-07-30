# Pulsewatch

Pulsewatch is a self-hosted infrastructure monitoring application. It combines
a Laravel web application with a lightweight Java agent that collects system
and process metrics and writes them directly to your database.

## Current features

- Guided browser-based installation
- MySQL, PostgreSQL, and Oracle database options
- Support for local databases and remote services such as Amazon RDS
- Connection validation before installation
- Secure administrator account creation
- Light and dark setup themes
- Responsive Tailwind CSS interface
- Java agent for system and process monitoring
- Laravel-managed database schema
- Protection against running the setup wizard again after installation

## Technology

- Laravel 13
- PHP 8.3 or later
- Apache
- Tailwind CSS 4 and Vite
- Java 17
- Maven
- MySQL, PostgreSQL, or Oracle

## Linux installation

Clone the repository and run:

```bash
git clone <repository-url> monitoring-agent
cd monitoring-agent
sudo ./install.sh
```

The default installation includes Apache, PHP, Composer, Java, Maven, Node.js,
and the PHP drivers needed to connect to MySQL and PostgreSQL. It does not
install a local database server, allowing the application to use a managed or
remote database.

### Optional local database servers

Install MySQL locally:

```bash
sudo ./install.sh --with-mysql-server
```

Install PostgreSQL locally:

```bash
sudo ./install.sh --with-postgresql-server
```

Install both:

```bash
sudo ./install.sh --with-mysql-server --with-postgresql-server
```

Install system packages without building the application:

```bash
sudo ./install.sh --skip-build
```

Use a specific application owner:

```bash
sudo ./install.sh --app-user deploy
```

The installer is designed for Ubuntu and Debian-based Linux distributions.

## Apache

Configure the Apache virtual host so its document root points to the Laravel
`public` directory. Ensure Apache can write to:

- `storage`
- `bootstrap/cache`
- `.env` during the initial web setup

The installation script enables Apache's rewrite module and applies the
required Laravel directory permissions.

## Web setup wizard

Open the application base URL after the Linux installation. Pulsewatch will
redirect to the setup wizard automatically.

The wizard performs three steps:

1. Select MySQL, PostgreSQL, or Oracle.
2. Enter and test the database host, port, database, username, and password.
3. Enter an administrator email and confirm a secure password.

After validation, Pulsewatch:

- runs all Laravel migrations;
- creates the first administrator account;
- saves the database configuration to `.env`;
- creates an installation marker to disable further setup access.

Temporary database credentials are kept in an encrypted, file-backed session
during setup.

### Oracle requirement

Oracle connections require Oracle Instant Client and the PHP OCI8 extension on
the web server. The Laravel Oracle driver is already included in Composer
dependencies.

## Database schema

Laravel migrations manage both application and monitoring tables:

- `users`
- `system_stats`
- `process_stats`
- Laravel session, cache, and queue tables

The Java agent no longer executes Liquibase or SQL migration files. Run the web
setup before configuring the agent so the monitoring tables already exist.

## Java monitoring agent

The agent collects:

- CPU usage
- total and free memory
- filtered process CPU and memory usage
- process name, command, user, state, PID, and start time

Build the agent:

```bash
mvn -f agent/pom.xml clean package
```

Configure it after completing the Laravel web setup:

```bash
java -jar agent/target/agent-1.0.0.jar setup \
  -host database.example.com \
  -port 3306 \
  -db monitoring \
  -u monitoring_agent \
  -p 'database-password' \
  -interval 20 \
  -f java,php
```

Run it:

```bash
java -jar agent/target/agent-1.0.0.jar
```

For production, run the agent as a systemd service so it starts on boot and is
restarted automatically.

## Local development

Install dependencies and create a local environment:

```bash
composer install --ignore-platform-req=ext-oci8
npm install
cp .env.example .env
php artisan key:generate
npm run build
```

Start Laravel:

```bash
php artisan serve
```

Then open `http://127.0.0.1:8000`.

## Testing

Run the PHP test suite:

```bash
php artisan test
```

Build production frontend assets:

```bash
npm run build
```

Validate the Java agent:

```bash
mvn -f agent/pom.xml clean package
```

## Installation reset

The completed installer is locked using:

```text
storage/app/installed
```

Removing this marker re-enables the setup wizard. Only do this intentionally;
it does not remove existing tables, users, or database configuration.
