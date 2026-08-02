#!/usr/bin/env bash
set -euo pipefail

download_url=""
api_url=""
api_token=""
hostname=""
interval="5"
process_filter=""
log_files=()

while [[ $# -gt 0 ]]; do
    case "$1" in
        --download-url) download_url="${2:-}"; shift 2 ;;
        --url) api_url="${2:-}"; shift 2 ;;
        --token) api_token="${2:-}"; shift 2 ;;
        --name) hostname="${2:-}"; shift 2 ;;
        --interval) interval="${2:-}"; shift 2 ;;
        --filter) process_filter="${2:-}"; shift 2 ;;
        --log) log_files+=("${2:-}"); shift 2 ;;
        *) echo "Unknown option: $1" >&2; exit 2 ;;
    esac
done

if [[ -z "$download_url" || -z "$api_url" || -z "$api_token" ]]; then
    echo "Required options: --download-url, --url, and --token" >&2
    exit 2
fi

if ! [[ "$interval" =~ ^[1-9][0-9]*$ ]]; then
    echo "Interval must be a positive number of seconds." >&2
    exit 2
fi

for command_name in curl java install systemctl; do
    if ! command -v "$command_name" >/dev/null 2>&1; then
        echo "Required command is missing: $command_name" >&2
        exit 1
    fi
done

if [[ ${EUID} -ne 0 ]]; then
    echo "Run this installer with sudo." >&2
    exit 1
fi

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

install -d -m 0700 /root/.monitoring-agent
cat >/etc/systemd/system/monitoring-agent.service <<'UNIT'
[Unit]
Description=Monitoring Agent service
After=network-online.target
Wants=network-online.target

[Service]
Type=simple
User=root
Environment=HOME=/root
ExecStart=/usr/bin/java -jar /opt/monitoring-agent/agent.jar start
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
UNIT

systemctl daemon-reload
systemctl enable --now monitoring-agent.service

echo "Monitoring Agent installed and running."
