#!/usr/bin/env bash
set -euo pipefail

DNF_FLAGS=(
  -y
  --setopt=install_weak_deps=False
  --setopt=tsflags=nodocs
)

sudo dnf makecache --refresh
sudo dnf install "${DNF_FLAGS[@]}" mariadb-server
sudo dnf install "${DNF_FLAGS[@]}" php-fpm php-cli php-mbstring php-mysqlnd
sudo dnf install "${DNF_FLAGS[@]}" httpd

sudo systemctl enable --now mariadb
sudo systemctl enable --now php-fpm
sudo systemctl enable --now httpd

sudo firewall-cmd --permanent --add-service=http
sudo firewall-cmd --permanent --add-service=https
sudo firewall-cmd --reload

sudo mkdir -p /srv/wcu-api
sudo chown -R opc:opc /srv/wcu-api

echo "Oracle VM base packages are ready."
