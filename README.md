# Monitoring Agent

Monitoring Agent is a self-hosted observability platform built with Laravel. It
combines server telemetry, logs, AWS inventory, website uptime checks, and
browser monitoring in one responsive dashboard.

## What it monitors

- Servers: CPU, memory, processes, disks, and selected log files through a
  lightweight Java 17 agent.
- AWS: EC2, EBS, RDS, S3, VPC, CloudWatch metrics, Performance Insights, and
  read-only security and cost recommendations.
- Uptime: HTTP availability, response time, optional SSL expiry checks, outage
  and recovery notifications, and administrator-triggered manual checks.
- Browsers: page loads, Web Vitals, AJAX/HTMX traffic, network failures, and
  JavaScript errors through a small browser SDK.
- Operations: team roles, SMTP, branding, retention, dark mode, and a guided
  installation wizard.

## Requirements

- PHP 8.3+
- Composer
- Node.js 22+ and npm
- MySQL, PostgreSQL, or Oracle
- Java 17 and Maven when building the server agent

Oracle connections additionally require Oracle Instant Client and the PHP OCI8
extension. For MySQL and PostgreSQL development, OCI8 can be skipped during
Composer installation.

## Local development

Clone the repository and install dependencies:

```bash
git clone <repository-url> monitoring-agent
cd monitoring-agent
composer install --ignore-platform-req=ext-oci8
npm install
cp .env.example .env
php artisan key:generate
npm run build
```

Configure a database in `.env`, run the migrations, and start the application:

```bash
php artisan migrate
php artisan serve
```

Open `http://127.0.0.1:8000`. If the installation has not been completed, the
setup wizard will validate the database connection and create the first
administrator.

For frontend development, run `npm run dev` in another terminal. To run uptime,
AWS synchronization, cleanup, and retention jobs locally, also run:

```bash
php artisan schedule:work
```

## Demo data

The repeatable demo seeders generate recent data for each dashboard:

```bash
php artisan db:seed --class=DashboardDemoSeeder --force
php artisan db:seed --class=BrowserMonitoringDemoSeeder --force
php artisan db:seed --class=AwsCloudDemoSeeder --force
php artisan db:seed --class=WebsiteMonitoringDemoSeeder --force
```

The uptime dataset includes operational, unavailable, paused, SSL-enabled,
SSL-disabled, and expiring-certificate examples. The server dashboard seeder
creates this local administrator:

```text
Email: demo@monitoring-agent.local
Password: MonitoringAgentDemo123!
```

Use demo credentials and seed data only in local or disposable environments.

## Main routes

| Area | Route |
| --- | --- |
| Dashboard | `/dashboard` |
| Servers | `/monitors` |
| AWS Cloud | `/cloud` |
| Uptime | `/website-monitors` |
| Browser monitoring | `/browser-monitoring` |
| Settings | `/settings` |

Settings and mutating monitoring actions are restricted to administrators.

## Useful commands

```bash
# Run all tests
php artisan test

# Format PHP
vendor/bin/pint

# Build frontend assets
npm run build

# Build and test the Java agent
mvn -f agent/pom.xml clean package

# Check all active uptime monitors immediately
php artisan monitors:check

# Synchronize due AWS connections immediately
php artisan cloud:sync --force

# Inspect scheduled jobs
php artisan schedule:list

# Apply retention cleanup immediately
php artisan data:prune
```

## Uptime and SSL checks

Laravel schedules active uptime monitors every minute. Administrators can also
use **Check now** on an uptime card. Manual checks are limited to ten requests
per minute.

SSL inspection is configured per monitor and enabled by default. When it is
disabled, certificate inspection is skipped and stored SSL check/expiry values
are cleared. SSL alerts are deduplicated at 30, 15, 7, and 0 days. Outage and
recovery emails are sent once per incident.

## Production installation

On a supported Debian-, Ubuntu-, RHEL-, Fedora-, or Amazon Linux-based server:

```bash
git clone <repository-url> monitoring-agent
cd monitoring-agent
sudo ./install.sh
```

The installer adds PHP, Composer, Node.js, Java, Maven, Apache integration, and
Laravel's scheduler. It installs database client drivers but no database server
by default. Optional local servers can be installed with:

```bash
sudo ./install.sh --with-mysql-server
sudo ./install.sh --with-postgresql-server
```

Point the web server document root at `public/`, allow writes to `storage/` and
`bootstrap/cache/`, complete the web setup wizard, and confirm the scheduler:

```bash
php artisan schedule:list
```

Production deployments should use HTTPS, `APP_ENV=production`,
`APP_DEBUG=false`, a dedicated database account, configured SMTP, and normal
database and application backups.

## Contributing

Contributions are welcome. A good first contribution is a focused bug fix,
test, documentation improvement, or small feature that matches the existing
Laravel and Tailwind patterns.

1. Fork the repository and clone your fork.
2. Create a branch from the latest default branch:

   ```bash
   git checkout -b feature/short-description
   ```

3. Follow the [local development](#local-development) steps.
4. Keep changes focused and add or update tests for behavior changes.
5. Before opening a pull request, run:

   ```bash
   vendor/bin/pint --test
   php artisan test
   npm run build
   ```

   If the Java agent changed, also run:

   ```bash
   mvn -f agent/pom.xml clean package
   ```

6. Commit with a clear, imperative message and open a pull request describing:
   the problem, the approach, testing performed, UI screenshots when relevant,
   and any migration or deployment impact.

Please avoid committing `.env`, credentials, generated build artifacts, or
production data. For larger features or schema/API changes, open an issue first
so the design can be discussed before implementation.

## Security

Do not open a public issue containing credentials, tokens, private URLs, or
exploit details. Share sensitive reports privately with the project maintainers.

## License

This project is distributed under the MIT license declared in `composer.json`.
