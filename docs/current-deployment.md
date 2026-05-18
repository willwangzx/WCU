# WCU Current Production Deployment

Updated: 2026-05-18

This document records the current live deployment after the migration to the
new server.

## Topology

```text
Browser
  -> Cloudflare DNS/proxy
  -> 178.238.234.111
  -> nginx on ports 80/443
     -> static site from /srv/wcu-site
     -> /api and /admin reverse proxy to wcu-backend.service on 127.0.0.1:8080
     -> forum.wcuedu.net -> PHP-FPM -> WordPress/wpForo at /var/www/wcu-forum
        -> MySQL database wcu_forum
```

Cloudflare Tunnel is not used in the current production path. The old
`cloudflared` systemd unit has been removed from the active system.

## DNS

Cloudflare DNS should contain proxied A records:

| Type | Name | Value | Proxy |
| --- | --- | --- | --- |
| A | `@` | `178.238.234.111` | Proxied |
| A | `www` | `178.238.234.111` | Proxied |
| A | `api` | `178.238.234.111` | Proxied |
| A | `forum` | `178.238.234.111` | Proxied |

Cloudflare SSL/TLS mode should be `Full (strict)`.

## Server Paths

- Static site root: `/srv/wcu-site`
- Backend app root: `/opt/wcu-backend`
- Backend config: `/opt/wcu-backend/config.python.json`
- SQLite database: `/var/lib/wcu-data/wcu.sqlite`
- Nginx site config: `/etc/nginx/sites-available/wcu-site`
- Forum WordPress root: `/var/www/wcu-forum`
- Forum nginx site config: `/etc/nginx/sites-available/wcu-forum`
- Forum production secrets: `/root/.wcu-forum-prod.env`
- Forum database: MySQL database `wcu_forum`
- Forum theme source in repo: `server/wordpress/themes/wcu-forum/`
- Forum SMTP MU plugin source in repo:
  `server/wordpress/mu-plugins/wcu-forum-smtp.php`
- Origin certificate: `/etc/nginx/ssl/wcuedu-origin.crt`
- Origin private key: `/etc/nginx/ssl/wcuedu-origin.key`

Keep `config.python.json`, the SQLite database, SSH keys, forum credentials,
database dumps, and certificate private keys out of git.

## Services

Expected systemd state:

```bash
systemctl is-active nginx wcu-backend mysql php8.3-fpm fail2ban
systemctl is-enabled nginx wcu-backend mysql php8.3-fpm fail2ban
systemctl is-active cloudflared || true
systemctl is-enabled cloudflared || true
```

Expected result:

- `nginx`: active/enabled
- `wcu-backend`: active/enabled, running as the `wcu` user
- `mysql`: active/enabled, used by the WordPress/wpForo forum
- `php8.3-fpm`: active/enabled, used by the WordPress/wpForo forum
- `fail2ban`: active/enabled
- `cloudflared`: inactive/not-found

`wcu-backend` and MySQL should listen only on localhost; PHP-FPM should use its
Unix socket:

```bash
ss -ltnp | grep -E ':80|:443|:8080|:3306'
test -S /run/php/php8.3-fpm.sock
```

Expected:

- `0.0.0.0:80` and `0.0.0.0:443` owned by nginx
- `127.0.0.1:8080` owned by Python backend
- `127.0.0.1:3306` owned by MySQL

`ufw` should be active. SSH is allowed on `22/tcp`; `80/443` should be allowed
only from Cloudflare IP ranges so direct origin access to `178.238.234.111`
does not work.

## Deploy Static Site Updates

From the local workstation:

```powershell
npm run build
.\scripts\run-tests.ps1
```

Then upload the generated static output to `/srv/wcu-site` on
`178.238.234.111`. Do not edit `dist/` manually unless the task specifically
requires deployment output.

After upload, verify:

```powershell
curl.exe -I https://wcuedu.net/
curl.exe https://wcuedu.net/api/application.php
curl.exe -I https://wcuedu.net/admin/
curl.exe -I https://forum.wcuedu.net/community/
curl.exe -s -L https://forum.wcuedu.net/community/ | Select-String "wpforo"
```

Expected:

- Homepage: `200 OK`
- API: `{"ok": true, "service": "wcu-applications-api"}`
- Admin: `401 Unauthorized` before valid Basic Auth credentials are supplied
- Forum: `200 OK` with wpForo markup from WordPress

Forum service checks:

```bash
nginx -t
systemctl is-active nginx php8.3-fpm mysql
wp --path=/var/www/wcu-forum db check --allow-root
wp --path=/var/www/wcu-forum plugin status wpforo --allow-root
wp --path=/var/www/wcu-forum theme list --allow-root | grep wcu-forum
```

Expected:

- `nginx -t`: syntax is ok and test is successful
- `nginx`, `php8.3-fpm`, and `mysql`: active
- WordPress database check: all tables OK
- wpForo plugin: active

## Backend Notes

The live backend is the Python deployment path:

- Entry point: `/opt/wcu-backend/python_backend.py`
- Local bind: `127.0.0.1:8080`
- Data store: SQLite at `/var/lib/wcu-data/wcu.sqlite`
- Admin password hashes use the `scrypt$...` format. Legacy `sha256$...`
  values can be migrated without knowing the raw password with:
  `python3 /opt/wcu-backend/scripts/hash-admin-password.py --from-sha256 'sha256$...'`

The admissions backend remains Python plus SQLite. The production forum is a
separate WordPress/wpForo service using PHP 8.3-FPM and MySQL.

## Forum Notes

- Public URL: `https://forum.wcuedu.net/community/`
- Root `/` on `forum.wcuedu.net` redirects to `/community/`
- WordPress root: `/var/www/wcu-forum`
- WordPress `home` and `siteurl`: `https://forum.wcuedu.net`
- WordPress registration is enabled with default role `subscriber`
- wpForo email confirmation is enabled for new registrations; manual account
  approval is disabled.
- Forum email is routed through the installer-managed SMTP MU plugin when
  `WCU_FORUM_SMTP_HOST` and related `WCU_FORUM_SMTP_*` values are set in
  `/root/.wcu-forum-prod.env`.
- The active forum theme is the WCU child theme copied from
  `server/wordpress/themes/wcu-forum/`.
- Permalinks use `/%postname%/`
- Initial admin and database credentials are stored only in
  `/root/.wcu-forum-prod.env`
- wpForo is seeded as a hybrid hub for start-here posts, projects,
  academic Q&A, resources, admissions, campus life, and collaboration

## Security State

Hardening applied on 2026-05-17:

- SSH password authentication is disabled.
- Root password is locked.
- Root SSH login is key-only.
- `ufw` is active; `80/443` are restricted to Cloudflare IP ranges.
- Direct origin access to `178.238.234.111:80/443` is blocked.
- `fail2ban` is active for SSH and has already banned a brute-force source.
- Static files are root-owned with directories `0755` and files `0644`.
- Backend config is `0640 root:wcu`.
- SQLite database is `0600 wcu:wcu`.
- `wcu-backend` runs as the dedicated non-root `wcu` service user with systemd
  hardening options.
- Backend CORS allows only `https://wcuedu.net` and
  `https://www.wcuedu.net`.
- Admissions admin delete and CSV export actions require CSRF tokens.
- Admissions admin password verification uses scrypt-compatible hashes, and
  admin login failures are throttled.
- Admissions API requests with unsupported content types return `415
  Unsupported Media Type`.
- Nginx sends HSTS, `X-Content-Type-Options`, `Referrer-Policy`,
  `X-Frame-Options`, and `Permissions-Policy`; `/admin` has basic rate
  limiting.
- Forum nginx blocks `xmlrpc.php` and rate-limits `wp-login.php`.
- Forum permissions allow public reading, subscriber topics/replies, and no
  guest posting. New user registration requires email confirmation.
- wpForo spam controls moderate new users, apply flood protection, and block
  links/attachments until the user has at least 3 approved posts.
- wpForo AI usergroup capabilities are disabled until a real AI service key is
  intentionally configured outside git.
- The old `cloudflared` systemd token unit has been removed.

Remaining maintenance:

- Reboot when convenient; `/var/run/reboot-required` was still present after
  hardening.
- Refresh the Cloudflare IP allowlist in `ufw` if Cloudflare changes its
  published ranges.
- Set valid production `WCU_FORUM_SMTP_*` values and verify one real forum
  registration email before relying on password reset or notification emails.
