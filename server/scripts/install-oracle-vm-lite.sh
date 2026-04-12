#!/usr/bin/env bash
set -euo pipefail

DNF_FLAGS=(
  -y
  --setopt=install_weak_deps=False
  --setopt=tsflags=nodocs
  --setopt=keepcache=0
)

if [ ! -f /swapfile-wcu ]; then
  sudo dd if=/dev/zero of=/swapfile-wcu bs=1M count=1024 status=progress
  sudo chmod 600 /swapfile-wcu
  sudo mkswap /swapfile-wcu >/dev/null
fi

if ! sudo /usr/sbin/swapon --show=NAME --noheadings | grep -qx '/swapfile-wcu'; then
  sudo /usr/sbin/swapon /swapfile-wcu
fi

if ! grep -q '^/swapfile-wcu ' /etc/fstab; then
  echo '/swapfile-wcu none swap sw 0 0' | sudo tee -a /etc/fstab >/dev/null
fi

sudo systemctl disable --now pmcd pmlogger pmlogger_farm || true
sudo dnf install "${DNF_FLAGS[@]}" nginx php-fpm php-cli php-mbstring php-sqlite3

sudo mkdir -p /var/www/wcu-site /var/www/wcu-api /var/lib/wcu-data
sudo chown -R opc:opc /var/www/wcu-site /var/www/wcu-api /var/lib/wcu-data

if systemctl is-active --quiet firewalld; then
  sudo firewall-cmd --permanent --add-service=http
  sudo firewall-cmd --reload
fi

echo "Lite Oracle VM base packages are ready."
