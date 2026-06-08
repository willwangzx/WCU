# WSL Local Server Development Guide

This guide helps contributors use WSL2 Ubuntu to simulate the current
production server on a local Windows machine. The goal is a close-enough local
server stack for development and verification, without copying production
secrets or depending on Cloudflare.

## Local Topology

```text
Windows browser
  -> http://localhost:8088
  -> WSL nginx
     -> static site from /srv/wcu-site
     -> /api and /admin reverse proxy to Python backend on 127.0.0.1:8080

Windows browser
  -> http://localhost:8081/community/
  -> WSL WordPress/wpForo forum

Windows browser
  -> http://localhost:8081/tools/logic-lab/
  -> WSL nginx static files from /var/www/wcu-forum/tools/logic-lab
```

Production still uses Cloudflare, public DNS, nginx on `80/443`, the Python
admissions backend on `127.0.0.1:8080`, and WordPress/wpForo through PHP-FPM
and MySQL. Local development uses high ports so contributors do not need to
bind public web ports or install real certificates.

## Prerequisites

On Windows:

- Git
- Node.js and npm
- PowerShell
- WSL2 with Ubuntu 24.04

Inside WSL:

```bash
sudo apt update
sudo apt install -y nginx python3 python3-venv sqlite3 curl unzip less rsync \
  mysql-server php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring \
  php8.3-xml php8.3-curl php8.3-gd php8.3-zip php8.3-intl \
  php8.3-bcmath php-imagick
```

If `php8.3-*` packages are not available, confirm the WSL distro is Ubuntu
24.04 or install the PHP version used by the project requirements.

## Repo Path In WSL

From WSL, the Windows checkout is usually available under `/mnt/c`:

```bash
export WCU_REPO_WSL=/mnt/c/Users/giaos/Desktop/Projects/WCU
cd "$WCU_REPO_WSL"
```

Collaborators should replace `/mnt/c/Users/giaos/Desktop/Projects/WCU` with
their own Windows checkout path. The rest of this guide uses `$WCU_REPO_WSL`
for commands run inside WSL.

## Static Site

Build the static site from PowerShell:

```powershell
npm run build
```

Publish the generated output into the WSL server root:

```powershell
wsl -d Ubuntu-24.04 -- bash -lc "export WCU_REPO_WSL=/mnt/c/Users/giaos/Desktop/Projects/WCU; sudo mkdir -p /srv/wcu-site && sudo rsync -a --delete \"\$WCU_REPO_WSL/dist/\" /srv/wcu-site/"
```

If the repo lives somewhere else, update the `/mnt/c/.../WCU` path. Do not edit
files directly in `/srv/wcu-site`; treat it as generated local deployment
output.

## Admissions Backend

Create local data and config paths:

```bash
sudo mkdir -p /opt/wcu-backend /var/lib/wcu-data
sudo rsync -a --delete \
  --exclude config.python.json \
  "$WCU_REPO_WSL/server/" \
  /opt/wcu-backend/
sudo cp "$WCU_REPO_WSL/server/config.python.example.json" \
  /opt/wcu-backend/config.python.json
```

Generate a local admin password hash:

```bash
cd "$WCU_REPO_WSL"
python3 server/scripts/hash-admin-password.py
```

Edit `/opt/wcu-backend/config.python.json` and set:

- `database.path` to `/var/lib/wcu-data/wcu.sqlite`
- `admin.username` to the local admin username
- `admin.password_hash` to the generated hash
- `cors.allowed_origins` to include `http://localhost:8088`
- `recaptcha.enabled` to `false` for normal local testing

Run the backend in the foreground while developing:

```bash
cd /opt/wcu-backend
sudo WCU_BIND_HOST=127.0.0.1 WCU_BIND_PORT=8080 WCU_STATIC_ROOT=/srv/wcu-site \
  python3 python_backend.py
```

Expected health check:

```bash
curl http://127.0.0.1:8080/api/application.php
```

Expected response:

```json
{"ok": true, "service": "wcu-applications-api"}
```

## Local Nginx Edge

Create `/etc/nginx/sites-available/wcu-local`:

```nginx
server {
    listen 8088;
    listen [::]:8088;
    server_name localhost;

    root /srv/wcu-site;
    index index.html;

    location / {
        try_files $uri $uri/ =404;
    }

    location /api/ {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto http;
    }

    location /admin/ {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto http;
    }

    location ~ /\. {
        deny all;
    }
}
```

Enable and reload nginx:

```bash
sudo ln -sfn /etc/nginx/sites-available/wcu-local /etc/nginx/sites-enabled/wcu-local
sudo nginx -t
sudo systemctl enable --now nginx
sudo systemctl reload nginx
```

Verify from PowerShell:

```powershell
curl.exe -I http://localhost:8088/
curl.exe http://localhost:8088/api/application.php
curl.exe -I http://localhost:8088/admin/
```

Expected results:

- Homepage: `200 OK`
- API: `{"ok": true, "service": "wcu-applications-api"}`
- Admin: `401 Unauthorized` until valid Basic Auth credentials are supplied

## Forum Development

The forum uses WordPress plus wpForo in WSL. The repo contains the WCU child
theme, SMTP MU plugin, and forum seeder, while the WordPress install itself
lives outside the repo at `/var/www/wcu-forum`.

Install WP-CLI if it is not already present:

```bash
if ! command -v wp >/dev/null 2>&1; then
  curl -fsSL -o /tmp/wp-cli.phar https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
  sudo install -m 755 /tmp/wp-cli.phar /usr/local/bin/wp
fi
wp --info
```

Use the shared local convention:

- Local forum root: `/var/www/wcu-forum`
- Local forum URL: `http://localhost:8081/community/`
- Local WordPress login: `http://localhost:8081/wp-login.php`
- Local credential file: `~/.wcu-forum-dev.env`
- Local database name: `wcu_forum`

Keep `~/.wcu-forum-dev.env` outside git. Do not copy
`/root/.wcu-forum-prod.env` from production.

For a production-like local nginx/PHP-FPM route, create a site that listens on
`8081` and points at `/var/www/wcu-forum`. The exact WordPress installation can
be created manually with WP-CLI or by adapting the project installer with local
environment values; keep the generated credentials in `~/.wcu-forum-dev.env`.

```nginx
server {
    listen 8081;
    listen [::]:8081;
    server_name localhost;

    root /var/www/wcu-forum;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$args;
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

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }

    location ~ /\. {
        deny all;
    }
}
```

After enabling the site, run `sudo nginx -t` and `sudo systemctl reload nginx`.

After setup or theme changes, verify:

```powershell
wsl -d Ubuntu-24.04 -- bash -lc "systemctl is-active mysql php8.3-fpm nginx"
wsl -d Ubuntu-24.04 -- bash -lc "wp --path=/var/www/wcu-forum plugin status wpforo --allow-root"
wsl -d Ubuntu-24.04 -- bash -lc "wp --path=/var/www/wcu-forum theme list --allow-root | grep wcu-forum"
curl.exe -I http://localhost:8081/community/
```

Use `localhost` for the browser and curl URL. On WSL, `127.0.0.1:8081` may not
forward from Windows the same way.

## Forum Tool Static Apps

Logic Lab stays in its own project at `D:\Projects\Logic gate`, but local and
production forum nginx serve its built output from `/tools/logic-lab/`.

Build from PowerShell:

```powershell
cd "D:\Projects\Logic gate"
npm test
npm run build -- --base /tools/logic-lab/
```

Publish the generated output into the WSL forum root:

```powershell
wsl -d Ubuntu-24.04 -- bash -lc "sudo mkdir -p /var/www/wcu-forum/tools/logic-lab && sudo rsync -a --delete '/mnt/d/Projects/Logic gate/dist/' /var/www/wcu-forum/tools/logic-lab/"
```

Verify:

```powershell
curl.exe -I http://localhost:8081/tools/logic-lab/
curl.exe -I http://localhost:8081/community/
```

## Daily Development Loop

1. Choose the integration branch and create a topic branch from it. For forum
   work, always branch from `forum`:
   ```powershell
   git switch forum
   git pull --ff-only
   git switch -c feature/forum-short-description
   ```
   For non-forum work, use the branch that will receive the merge as the base.
2. Check local changes before editing:
   ```powershell
   git status --short --branch
   ```
3. Review `TODO.md` before adding features.
4. Edit source files in the repo, not `/srv/wcu-site` or `/var/www/wcu-forum`
   unless you are intentionally debugging the local WordPress install.
5. Build and publish static files:
   ```powershell
   npm run build
   wsl -d Ubuntu-24.04 -- bash -lc "export WCU_REPO_WSL=/mnt/c/Users/giaos/Desktop/Projects/WCU; sudo rsync -a --delete \"\$WCU_REPO_WSL/dist/\" /srv/wcu-site/"
   ```
6. Restart the foreground backend if backend files changed, or re-sync
   `/opt/wcu-backend` before restarting it.
7. For forum changes, sync the changed theme, MU plugin, or seeder files into
   the local WSL WordPress install as needed, then verify the local forum before
   merging back to `forum`.
8. Run the smoke tests:
   ```powershell
   .\scripts\run-tests.ps1
   ```
9. Verify the local server:
   ```powershell
   curl.exe -I http://localhost:8088/
   curl.exe http://localhost:8088/api/application.php
   curl.exe -I http://localhost:8088/admin/
   curl.exe -I http://localhost:8081/community/
   curl.exe -I http://localhost:8081/tools/logic-lab/
   ```
10. Merge the topic branch back into its integration branch only after the
    relevant local checks pass. Forum topic branches merge back into `forum`.

## Safety Rules

- Never commit local secrets, database dumps, SSH keys, API keys, WordPress
  admin passwords, or local config files.
- Keep `server/config.python.json`, `server/config.php`,
  `~/.wcu-forum-dev.env`, `keys/`, `origincertificate.txt`, and
  `privatekey.txt` untracked.
- Leave local admissions reCAPTCHA disabled unless specifically testing it.
  When testing reCAPTCHA, commit only public site-key changes that are intended
  for source control, and keep secret keys in local environment variables or
  untracked config.
- Do not point local nginx at production databases, production WordPress files,
  or production secret files.
- Treat `/srv/wcu-site` and `/opt/wcu-backend` as local deployment copies. The
  source of truth is the repo.

## Troubleshooting

Check listening ports:

```bash
ss -ltnp | grep -E ':8080|:8081|:8088|:3306'
```

Check nginx:

```bash
sudo nginx -t
sudo journalctl -u nginx -n 100 --no-pager
```

Check PHP-FPM and MySQL:

```bash
systemctl is-active php8.3-fpm mysql
sudo journalctl -u php8.3-fpm -u mysql -n 100 --no-pager
```

If Windows cannot reach a WSL port, restart WSL:

```powershell
wsl --shutdown
```

Then open Ubuntu again, restart the WSL services, and rerun the curl checks.
