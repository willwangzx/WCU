#!/usr/bin/env bash
set -euo pipefail

DNF_FLAGS=(
  -y
  --setopt=install_weak_deps=False
  --setopt=tsflags=nodocs
  --setopt=keepcache=0
)

sudo systemctl disable --now pmcd pmlogger pmlogger_farm || true
sudo dnf install "${DNF_FLAGS[@]}" lighttpd

sudo mkdir -p /var/www/wcu-site /opt/wcu-front/certs /var/log/lighttpd
sudo chown -R opc:opc /var/www/wcu-site /opt/wcu-front /var/log/lighttpd

if systemctl is-active --quiet firewalld; then
  sudo firewall-cmd --permanent --add-service=http
  sudo firewall-cmd --reload
fi

echo "Lighttpd front proxy base packages are ready."
