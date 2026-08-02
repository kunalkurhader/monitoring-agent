#!/usr/bin/env bash
set -euo pipefail

download_url=""
expires_at=""
api_url=""
api_token=""
hostname=""
interval="5"
process_filter=""
log_files=()

while [[ $# -gt 0 ]]; do
    case "$1" in
        --download-url) download_url="${2:-}"; shift 2 ;;
        --expires-at) expires_at="${2:-}"; shift 2 ;;
        --url) api_url="${2:-}"; shift 2 ;;
        --token) api_token="${2:-}"; shift 2 ;;
        --name) hostname="${2:-}"; shift 2 ;;
        --interval) interval="${2:-}"; shift 2 ;;
        --filter) process_filter="${2:-}"; shift 2 ;;
        --log) log_files+=("${2:-}"); shift 2 ;;
        *) echo "Unknown option: $1" >&2; exit 2 ;;
    esac
done

if [[ -z "$download_url" || -z "$expires_at" || -z "$api_url" || -z "$api_token" ]]; then
    echo "Required options: --download-url, --expires-at, --url, and --token" >&2
    exit 2
fi

expiry_epoch="$(date -d "$expires_at" +%s 2>/dev/null || true)"
if [[ -z "$expiry_epoch" ]]; then
    echo "The temporary agent expiry timestamp is invalid. Build a fresh command in Settings." >&2
    exit 2
fi
if (( $(date +%s) >= expiry_epoch )); then
    echo "This temporary agent installation command has expired. Build a fresh JAR in Settings → Server Agent." >&2
    exit 2
fi
echo "Temporary agent download is valid for another $((expiry_epoch - $(date +%s))) seconds."

if ! [[ "$interval" =~ ^[1-9][0-9]*$ ]]; then
    echo "Interval must be a positive number of seconds." >&2
    exit 2
fi

if [[ ! -r /etc/os-release ]]; then
    echo "A Linux distribution with systemd is required." >&2
    exit 1
fi

if [[ ${EUID} -ne 0 ]]; then
    echo "Run this installer with sudo." >&2
    exit 1
fi

# shellcheck disable=SC1091
source /etc/os-release
java_major=0
if command -v java >/dev/null 2>&1; then
    java_major="$(java -version 2>&1 | sed -nE '1s/.*version "([0-9]+).*/\1/p')"
    java_major="${java_major:-0}"
fi
if ((java_major < 17)); then
    echo "Java 17+ is missing; installing a Java 17 runtime for ${PRETTY_NAME:-Linux}."
    case "${ID:-}" in
        ubuntu|debian|linuxmint|pop)
            apt-get update
            DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends openjdk-17-jre-headless
            ;;
        amzn)
            package_manager="$(command -v dnf || command -v yum || true)"
            [[ -n "$package_manager" ]] || { echo "DNF or YUM is required." >&2; exit 1; }
            "$package_manager" install -y java-17-amazon-corretto-headless
            ;;
        rhel|centos|rocky|almalinux|ol|fedora)
            package_manager="$(command -v dnf || command -v yum || true)"
            [[ -n "$package_manager" ]] || { echo "DNF or YUM is required." >&2; exit 1; }
            "$package_manager" install -y java-17-openjdk-headless
            ;;
        *)
            if [[ " ${ID_LIKE:-} " == *" debian "* ]]; then
                apt-get update
                DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends openjdk-17-jre-headless
            elif [[ " ${ID_LIKE:-} " == *" rhel "* || " ${ID_LIKE:-} " == *" fedora "* ]]; then
                package_manager="$(command -v dnf || command -v yum || true)"
                [[ -n "$package_manager" ]] || { echo "DNF or YUM is required." >&2; exit 1; }
                "$package_manager" install -y java-17-openjdk-headless
            else
                echo "Install Java 17, then rerun this script." >&2
                exit 1
            fi
            ;;
    esac
fi

java_major="$(java -version 2>&1 | sed -nE '1s/.*version "([0-9]+).*/\1/p')"
java_major="${java_major:-0}"
((java_major >= 17)) || { echo "Java 17 or newer is required." >&2; exit 1; }

for command_name in curl java install systemctl; do
    if ! command -v "$command_name" >/dev/null 2>&1; then
        echo "Required command is missing: $command_name" >&2
        exit 1
    fi
done

install -d -m 0755 /opt/monitoring-agent
curl --fail --location --silent --show-error "$download_url" -o /opt/monitoring-agent/agent.jar
chmod 0644 /opt/monitoring-agent/agent.jar

setup_args=(setup -url "$api_url" -token "$api_token" -interval "$interval")
if [[ -n "$hostname" ]]; then setup_args+=(-name "$hostname"); fi
if [[ -n "$process_filter" ]]; then setup_args+=(-f "$process_filter"); fi
for log_file in "${log_files[@]}"; do
    if [[ "$log_file" != /* ]]; then echo "Log path must be absolute: $log_file" >&2; exit 2; fi
    setup_args+=(-log "$log_file")
done

HOME=/root java -jar /opt/monitoring-agent/agent.jar "${setup_args[@]}"
java_bin="$(command -v java)"

install -d -m 0700 /root/.monitoring-agent
cat >/etc/systemd/system/monitoring-agent.service <<UNIT
[Unit]
Description=Monitoring Agent service
After=network-online.target
Wants=network-online.target

[Service]
Type=simple
User=root
Environment=HOME=/root
ExecStart=$java_bin -jar /opt/monitoring-agent/agent.jar start
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
UNIT

systemctl daemon-reload
systemctl enable --now monitoring-agent.service

echo "Monitoring Agent installed and running."
