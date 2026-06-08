#!/usr/bin/env bash
set -Eeuo pipefail

FORUM_DOMAIN="${FORUM_DOMAIN:-forum.wcuedu.net}"
FORUM_ROOT="${FORUM_ROOT:-/var/www/wcu-forum}"
FORUM_URL="https://${FORUM_DOMAIN}"
FORUM_PAGE_SLUG="${FORUM_PAGE_SLUG:-community}"
ENV_FILE="${ENV_FILE:-/root/.wcu-forum-prod.env}"
DB_NAME="${DB_NAME:-wcu_forum}"
DB_USER="${DB_USER:-wcu_forum}"
ADMIN_USER_DEFAULT="wcu_forum_admin"
ADMIN_EMAIL_DEFAULT="admin@wcuedu.net"
MAIL_FROM_DEFAULT="noreply@wcuedu.net"
MAIL_FROM_NAME_DEFAULT="William Chichi University Forum"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SEEDER_FILE="${SEEDER_FILE:-${SCRIPT_DIR}/seed-wpforo-forum.php}"
THEME_SOURCE="${THEME_SOURCE:-${SCRIPT_DIR}/../wordpress/themes/wcu-forum}"
MU_PLUGIN_SOURCE="${MU_PLUGIN_SOURCE:-${SCRIPT_DIR}/../wordpress/mu-plugins/wcu-forum-smtp.php}"
FORUM_THEME_SLUG="${FORUM_THEME_SLUG:-wcu-forum}"

if [[ "${EUID}" -ne 0 ]]; then
  echo "Run as root." >&2
  exit 1
fi

random_secret() {
  openssl rand -base64 36 | tr -d '\n'
}

write_env_if_missing() {
  if [[ -f "${ENV_FILE}" ]]; then
    chmod 600 "${ENV_FILE}"
    return
  fi

  install -m 600 /dev/null "${ENV_FILE}"
  {
    echo "WCU_FORUM_DB_NAME='${DB_NAME}'"
    echo "WCU_FORUM_DB_USER='${DB_USER}'"
    echo "WCU_FORUM_DB_PASSWORD='$(random_secret)'"
    echo "WCU_FORUM_ADMIN_USER='${ADMIN_USER_DEFAULT}'"
    echo "WCU_FORUM_ADMIN_EMAIL='${ADMIN_EMAIL_DEFAULT}'"
    echo "WCU_FORUM_ADMIN_PASSWORD='$(random_secret)'"
    echo "WCU_FORUM_SMTP_HOST=''"
    echo "WCU_FORUM_SMTP_PORT='587'"
    echo "WCU_FORUM_SMTP_SECURE='tls'"
    echo "WCU_FORUM_SMTP_USER=''"
    echo "WCU_FORUM_SMTP_PASSWORD=''"
    echo "WCU_FORUM_MAIL_FROM='${MAIL_FROM_DEFAULT}'"
    echo "WCU_FORUM_MAIL_FROM_NAME='${MAIL_FROM_NAME_DEFAULT}'"
    echo "WCU_FORUM_RECAPTCHA_SITE_KEY=''"
    echo "WCU_FORUM_RECAPTCHA_SECRET_KEY=''"
    echo "WCU_FORUM_RECAPTCHA_THEME='light'"
    echo "WCU_FORUM_RECAPTCHA_VERSION='v2_checkbox'"
    echo "WCU_FORUM_RECAPTCHA_SCORE_THRESHOLD='0.5'"
  } > "${ENV_FILE}"
  chmod 600 "${ENV_FILE}"
}

load_env() {
  # shellcheck disable=SC1090
  source "${ENV_FILE}"
  DB_NAME="${WCU_FORUM_DB_NAME:-${DB_NAME}}"
  DB_USER="${WCU_FORUM_DB_USER:-${DB_USER}}"
  DB_PASSWORD="${WCU_FORUM_DB_PASSWORD:?Missing WCU_FORUM_DB_PASSWORD in ${ENV_FILE}}"
  WP_ADMIN_USER="${WCU_FORUM_ADMIN_USER:-${ADMIN_USER_DEFAULT}}"
  WP_ADMIN_EMAIL="${WCU_FORUM_ADMIN_EMAIL:-${ADMIN_EMAIL_DEFAULT}}"
  WP_ADMIN_PASSWORD="${WCU_FORUM_ADMIN_PASSWORD:?Missing WCU_FORUM_ADMIN_PASSWORD in ${ENV_FILE}}"
  WP_SMTP_HOST="${WCU_FORUM_SMTP_HOST:-}"
  WP_SMTP_PORT="${WCU_FORUM_SMTP_PORT:-587}"
  WP_SMTP_SECURE="${WCU_FORUM_SMTP_SECURE:-tls}"
  WP_SMTP_USER="${WCU_FORUM_SMTP_USER:-}"
  WP_SMTP_PASSWORD="${WCU_FORUM_SMTP_PASSWORD:-}"
  WP_MAIL_FROM="${WCU_FORUM_MAIL_FROM:-${MAIL_FROM_DEFAULT}}"
  WP_MAIL_FROM_NAME="${WCU_FORUM_MAIL_FROM_NAME:-${MAIL_FROM_NAME_DEFAULT}}"
  WP_RECAPTCHA_SITE_KEY="${WCU_FORUM_RECAPTCHA_SITE_KEY:-}"
  WP_RECAPTCHA_SECRET_KEY="${WCU_FORUM_RECAPTCHA_SECRET_KEY:-}"
  WP_RECAPTCHA_THEME="${WCU_FORUM_RECAPTCHA_THEME:-light}"
  WP_RECAPTCHA_VERSION="${WCU_FORUM_RECAPTCHA_VERSION:-v2_checkbox}"
  WP_RECAPTCHA_SCORE_THRESHOLD="${WCU_FORUM_RECAPTCHA_SCORE_THRESHOLD:-0.5}"
}

install_packages() {
  export DEBIAN_FRONTEND=noninteractive
  apt-get update
  apt-get install -y \
    ca-certificates curl unzip less \
    nginx mysql-server \
    php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml \
    php8.3-curl php8.3-gd php8.3-zip php8.3-intl php8.3-bcmath \
    php-imagick
  systemctl enable --now mysql
  systemctl enable --now php8.3-fpm
}

install_wp_cli() {
  if command -v wp >/dev/null 2>&1; then
    return
  fi

  curl -fsSL -o /usr/local/bin/wp https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
  chmod 755 /usr/local/bin/wp
  php /usr/local/bin/wp --info >/dev/null
}

configure_database() {
  mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL
}

install_wordpress() {
  mkdir -p "${FORUM_ROOT}"

  if [[ ! -f "${FORUM_ROOT}/wp-load.php" ]]; then
    wp core download --path="${FORUM_ROOT}" --allow-root
  fi

  if [[ ! -f "${FORUM_ROOT}/wp-config.php" ]]; then
    wp config create \
      --path="${FORUM_ROOT}" \
      --dbname="${DB_NAME}" \
      --dbuser="${DB_USER}" \
      --dbpass="${DB_PASSWORD}" \
      --dbhost="localhost" \
      --skip-check \
      --allow-root
    wp config shuffle-salts --path="${FORUM_ROOT}" --allow-root
  fi

  if ! wp core is-installed --path="${FORUM_ROOT}" --allow-root >/dev/null 2>&1; then
    wp core install \
      --path="${FORUM_ROOT}" \
      --url="${FORUM_URL}" \
      --title="William Chichi University Forum" \
      --admin_user="${WP_ADMIN_USER}" \
      --admin_password="${WP_ADMIN_PASSWORD}" \
      --admin_email="${WP_ADMIN_EMAIL}" \
      --skip-email \
      --allow-root
  fi

  wp config set DISALLOW_FILE_EDIT true --raw --type=constant --path="${FORUM_ROOT}" --allow-root >/dev/null
  wp config set WP_AUTO_UPDATE_CORE minor --type=constant --path="${FORUM_ROOT}" --allow-root >/dev/null
  wp option update home "${FORUM_URL}" --path="${FORUM_ROOT}" --allow-root >/dev/null
  wp option update siteurl "${FORUM_URL}" --path="${FORUM_ROOT}" --allow-root >/dev/null
  wp option update users_can_register 1 --path="${FORUM_ROOT}" --allow-root >/dev/null
  wp option update default_role subscriber --path="${FORUM_ROOT}" --allow-root >/dev/null
  wp rewrite structure '/%postname%/' --hard --path="${FORUM_ROOT}" --allow-root >/dev/null
}

set_wp_config_constant() {
  local key="$1"
  local value="$2"

  if wp config has "${key}" --path="${FORUM_ROOT}" --allow-root >/dev/null 2>&1; then
    wp config set "${key}" "${value}" --type=constant --path="${FORUM_ROOT}" --allow-root >/dev/null
  else
    wp config set "${key}" "${value}" --type=constant --path="${FORUM_ROOT}" --allow-root >/dev/null
  fi
}

configure_mail() {
  set_wp_config_constant WCU_FORUM_MAIL_FROM "${WP_MAIL_FROM}"
  set_wp_config_constant WCU_FORUM_MAIL_FROM_NAME "${WP_MAIL_FROM_NAME}"

  if [[ -n "${WP_SMTP_HOST}" ]]; then
    set_wp_config_constant WCU_FORUM_SMTP_HOST "${WP_SMTP_HOST}"
    set_wp_config_constant WCU_FORUM_SMTP_PORT "${WP_SMTP_PORT}"
    set_wp_config_constant WCU_FORUM_SMTP_SECURE "${WP_SMTP_SECURE}"
    set_wp_config_constant WCU_FORUM_SMTP_USER "${WP_SMTP_USER}"
    set_wp_config_constant WCU_FORUM_SMTP_PASSWORD "${WP_SMTP_PASSWORD}"
  else
    echo "WCU_FORUM_SMTP_HOST is empty; WordPress will use its default mail transport until SMTP is configured." >&2
  fi

  wp option update admin_email "${WP_ADMIN_EMAIL}" --path="${FORUM_ROOT}" --allow-root >/dev/null
  wp option patch update wpforo_email from_name "${WP_MAIL_FROM_NAME}" --path="${FORUM_ROOT}" --allow-root >/dev/null || true
  wp option patch update wpforo_email from_email "${WP_MAIL_FROM}" --path="${FORUM_ROOT}" --allow-root >/dev/null || true
  wp option patch update wpforo_email admin_emails "[\"${WP_ADMIN_EMAIL}\"]" --format=json --path="${FORUM_ROOT}" --allow-root >/dev/null || true
}

install_wpforo() {
  if ! wp plugin is-installed wpforo --path="${FORUM_ROOT}" --allow-root >/dev/null 2>&1; then
    wp plugin install wpforo --path="${FORUM_ROOT}" --allow-root
  fi
  wp plugin activate wpforo --path="${FORUM_ROOT}" --allow-root >/dev/null

  local page_id
  page_id="$(wp post list --path="${FORUM_ROOT}" --post_type=page --name="${FORUM_PAGE_SLUG}" --field=ID --allow-root | head -n 1 || true)"
  if [[ -z "${page_id}" ]]; then
    page_id="$(wp post create \
      --path="${FORUM_ROOT}" \
      --post_type=page \
      --post_status=publish \
      --post_title="Community" \
      --post_name="${FORUM_PAGE_SLUG}" \
      --post_content='[wpforo]' \
      --porcelain \
      --allow-root)"
  else
    wp post update "${page_id}" \
      --path="${FORUM_ROOT}" \
      --post_status=publish \
      --post_title="Community" \
      --post_name="${FORUM_PAGE_SLUG}" \
      --post_content='[wpforo]' \
      --allow-root >/dev/null
  fi
  wp option update wpforo_pageid "${page_id}" --path="${FORUM_ROOT}" --allow-root >/dev/null
}

install_forum_theme() {
  if [[ ! -d "${THEME_SOURCE}" ]]; then
    echo "Theme source not found: ${THEME_SOURCE}" >&2
    exit 1
  fi

  if [[ ! -f "${MU_PLUGIN_SOURCE}" ]]; then
    echo "MU plugin source not found: ${MU_PLUGIN_SOURCE}" >&2
    exit 1
  fi

  if ! wp theme is-installed twentytwentyfive --path="${FORUM_ROOT}" --allow-root >/dev/null 2>&1; then
    wp theme install twentytwentyfive --path="${FORUM_ROOT}" --allow-root >/dev/null
  fi

  mkdir -p "${FORUM_ROOT}/wp-content/themes/${FORUM_THEME_SLUG}"
  find "${FORUM_ROOT}/wp-content/themes/${FORUM_THEME_SLUG}" -mindepth 1 -maxdepth 1 -exec rm -rf {} +
  cp -a "${THEME_SOURCE}/." "${FORUM_ROOT}/wp-content/themes/${FORUM_THEME_SLUG}/"

  mkdir -p "${FORUM_ROOT}/wp-content/mu-plugins"
  cp "${MU_PLUGIN_SOURCE}" "${FORUM_ROOT}/wp-content/mu-plugins/wcu-forum-smtp.php"

  wp theme activate "${FORUM_THEME_SLUG}" --path="${FORUM_ROOT}" --allow-root >/dev/null
}

seed_forum() {
  if [[ ! -f "${SEEDER_FILE}" ]]; then
    echo "Seeder not found: ${SEEDER_FILE}" >&2
    exit 1
  fi

  WCU_FORUM_ADMIN_USER="${WP_ADMIN_USER}" \
    WCU_FORUM_RECAPTCHA_SITE_KEY="${WP_RECAPTCHA_SITE_KEY}" \
    WCU_FORUM_RECAPTCHA_SECRET_KEY="${WP_RECAPTCHA_SECRET_KEY}" \
    WCU_FORUM_RECAPTCHA_THEME="${WP_RECAPTCHA_THEME}" \
    WCU_FORUM_RECAPTCHA_VERSION="${WP_RECAPTCHA_VERSION}" \
    WCU_FORUM_RECAPTCHA_SCORE_THRESHOLD="${WP_RECAPTCHA_SCORE_THRESHOLD}" \
    wp eval-file "${SEEDER_FILE}" --path="${FORUM_ROOT}" --allow-root
}

configure_permissions() {
  chown -R www-data:www-data "${FORUM_ROOT}"
  find "${FORUM_ROOT}" -type d -exec chmod 755 {} +
  find "${FORUM_ROOT}" -type f -exec chmod 644 {} +
  chmod 640 "${FORUM_ROOT}/wp-config.php"
}

configure_nginx() {
  cat >/etc/nginx/conf.d/wcu-forum-rate-limit.conf <<'NGINX'
limit_req_zone $binary_remote_addr zone=wcu_forum_login:10m rate=10r/m;
NGINX

  cat >/etc/nginx/sites-available/wcu-forum <<'NGINX'
server {
    listen 80;
    listen [::]:80;
    server_name forum.wcuedu.net;

    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name forum.wcuedu.net;

    root /var/www/wcu-forum;
    index index.php index.html;

    ssl_certificate /etc/nginx/ssl/wcuedu-origin.crt;
    ssl_certificate_key /etc/nginx/ssl/wcuedu-origin.key;

    add_header Strict-Transport-Security "max-age=15552000; includeSubDomains" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header Permissions-Policy "geolocation=(), microphone=(), camera=()" always;

    client_max_body_size 128m;

    location = / {
        return 302 /community/;
    }

    location = /xmlrpc.php {
        return 403;
    }

    location ^~ /tools/logic-lab/assets/ {
        expires 30d;
        access_log off;
        try_files $uri =404;
    }

    location ^~ /tools/logic-lab/ {
        index index.html;
        try_files $uri $uri/ /tools/logic-lab/index.html;
    }

    location = /wp-login.php {
        limit_req zone=wcu_forum_login burst=10 nodelay;
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }

    location / {
        try_files $uri $uri/ /index.php?$args;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }

    location ~* \.(?:css|js|jpg|jpeg|gif|png|svg|webp|ico|woff2?)$ {
        expires 30d;
        access_log off;
        try_files $uri =404;
    }

    location ~ /\. {
        deny all;
    }
}
NGINX

  ln -sfn /etc/nginx/sites-available/wcu-forum /etc/nginx/sites-enabled/wcu-forum
  nginx -t
  systemctl reload nginx
}

main() {
  write_env_if_missing
  load_env
  install_packages
  install_wp_cli
  configure_database
  install_wordpress
  configure_mail
  install_wpforo
  install_forum_theme
  seed_forum
  configure_permissions
  configure_nginx

  echo "WordPress/wpForo forum installed at ${FORUM_URL}/${FORUM_PAGE_SLUG}/"
  echo "Production credentials are stored in ${ENV_FILE}"
}

main "$@"
