# Monitoring Agent

A lightweight, self-hosted **Java monitoring agent** for collecting **system metrics** and **filtered process statistics**, stored directly in your own database.

This project is designed to be **simple, transparent, and extensible**, without any dependency on third-party SaaS platforms.


### ✨ Features

- 📊 **System metrics**
  - CPU usage
  - Memory usage
- 🧩 **Process monitoring**
  - Track only selected processes (e.g. `java`, `php`)
  - Capture CPU, memory, state, user, command, and start time
- ⏱ **Configurable interval**
  - Run every _N_ seconds (default: 5)
- 🗄 **Database-backed storage**
  - MySQL / MariaDB
  - Schema managed via Liquibase migrations
- 🔐 **Secure local configuration**
  - Credentials stored outside the repository
- ⚙️ **CLI-based setup**
  - One-time setup command
  - Normal start without setup
- 🧵 **Non-blocking scheduler**
  - Runs continuously in background threads
- 🖥 **Cross-platform**
  - Linux, macOS, Windows (Java-supported environments)

### 🧠 Why this project?

Most monitoring solutions today are:
- tightly coupled to SaaS platforms
- heavy on resources
- hard to customize at process level
- opaque in how data is collected

This project focuses on:
- **full control of your data**
- **simple architecture**
- **process-level visibility**
- **self-hosted monitoring**

It’s suitable for:
- learning how monitoring agents work internally
- building custom dashboards
- controlled or air-gapped environments
- experimentation and extensions

### 🛠 Tech Stack

- **Java**
- **Maven**
- **OSHI** (system & process metrics)
- **MySQL / MariaDB**
- **Liquibase**
- **Apache Commons CLI**

### Prerequisites

- Java 17+
- Maven 3.8+
- MySQL / MariaDB

---

### Build the project

```bash
mvn clean package
```

### Steps to install on server
Run setup **only once** on a machine.  
This will:
- validate database credentials
- create database tables using Liquibase
- save configuration locally
- configure polling interval
- configure process filters

```bash
java -jar agent.jar setup \
  -host localhost \
  -port 3306 \
  -db monitoring \
  -u root \
  -p 'password' \
  -interval 20 \
  -f java,php
```

### ⚙️ Setup Options
- -host	-> Database host
- -port ->	Database port
- -db ->	Database name
- -u ->	Database user
- -p ->	Database password
- -interval ->	Polling interval in seconds
- -f ->	Process filter (comma-separated list)


#### Example filters
```bash
-f java
-f java,php
-f nginx,mysqld
```

Only processes whose name or command line matches the filter will be tracked.


### 🧵 Running in Background
Linux / macOS
```bash
nohup java -jar agent.jar > agent.log 2>&1 &
```
Windows (PowerShell)
```bash
Start-Process java -ArgumentList "-jar agent.jar"
```
Production (Recommended) Run the agent as:
- a systemd service on Linux
- a Windows service

This enables auto-restart, logging, and startup on boot.

### ⚠️ Performance Notes

Tracking all processes frequently can generate a large volume of data.

Recommended best practices:
- Always use process filters
- Increase polling interval on busy systems
- Track only long-running or critical processes
- Implement data retention policies

### 🛣 Roadmap

Planned and potential improvements:

- Disk and network metrics
- JVM-level monitoring
- Log file tracking
- Config reload without restart
- Aggregation and retention jobs
- Alerting and thresholds
- systemd / Windows service installers
- REST / push-based reporting
- Dashboard integrations


### 🤝 Contributing

Contributions are welcome and appreciated.

You can help by:

- fixing bugs
- improving performance
- adding new collectors
- improving documentation
- discussing architecture and design

Please open an issue or submit a pull request.
