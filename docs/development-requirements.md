# Development Requirements

This file defines the shared tools and local services expected for team
development. Keep it updated when required versions, ports, services, or setup
commands change.

## Baseline Tools

- Git
- Node.js and npm
- PowerShell on Windows
- WSL2 with Ubuntu 24.04 for Windows contributors

## Static Site Requirements

- Install Node.js and npm.
- Build static output with:
  ```powershell
  npm run build
  ```
- Run smoke tests with:
  ```powershell
  .\scripts\run-tests.ps1
  ```
- Start local static preview with:
  ```powershell
  .\scripts\serve-site.ps1
  ```

## Admissions Backend Requirements

- Python backend is the current production backend.
- Local backend target: `http://127.0.0.1:8080`.
- Use `server/config.python.example.json` as the template for local Python
  backend config.
- Keep real API keys and admin credentials outside git.
- Leave `window.WCU_CONFIG.recaptchaSiteKey` empty for normal local form
  testing. To test Google reCAPTCHA, place only the public site key in
  `assets/js/site-config.js` and set the backend secret with
  `WCU_RECAPTCHA_SECRET_KEY` or the local untracked backend config.
- Legacy PHP backend files remain in the repo for reference and overlap checks,
  but production currently uses Python plus SQLite.

## Forum Development Requirements

Forum development uses WordPress plus wpForo. The direct WSL development stack
should provide:

- Apache `2.4+`
- PHP `8.3+`
- MySQL `8.0+` or MariaDB `10.6+`
- Composer
- WP-CLI
- WordPress
- wpForo

Required PHP extensions:

- `mysqli`
- `mysqlnd`
- `pdo_mysql`
- `mbstring`
- `xml`
- `curl`
- `gd`
- `zip`
- `intl`
- `bcmath`
- `imagick`

Current local direct-install convention:

- WordPress root: `/var/www/wcu-forum`
- Local forum URL: `http://localhost:8081/community/`
- Local WordPress login: `http://localhost:8081/wp-login.php`
- Local credential file: `~/.wcu-forum-dev.env`
- Local database name: `wcu_forum`

The credential file is machine-local and must not be committed.

Production forum deployment uses the current VM rather than a managed
WordPress host:

- Production WordPress root: `/var/www/wcu-forum`
- Production forum URL: `https://forum.wcuedu.net/community/`
- Production database: MySQL database `wcu_forum`
- Production secret file: `/root/.wcu-forum-prod.env`
- Production nginx config: `/etc/nginx/sites-available/wcu-forum`

Production deployment artifacts:

- `server/scripts/install-wordpress-forum.sh`
- `server/scripts/seed-wpforo-forum.php`
- `server/config/nginx-forum.conf.example`
- `server/wordpress/themes/wcu-forum/`
- `server/wordpress/mu-plugins/wcu-forum-smtp.php`

Production forum mail is configured through `/root/.wcu-forum-prod.env` and
then written by the installer to local `wp-config.php` constants. Keep the real
values outside git:

- `WCU_FORUM_SMTP_HOST`
- `WCU_FORUM_SMTP_PORT` defaults to `587`
- `WCU_FORUM_SMTP_SECURE` defaults to `tls`
- `WCU_FORUM_SMTP_USER`
- `WCU_FORUM_SMTP_PASSWORD`
- `WCU_FORUM_MAIL_FROM` defaults to `noreply@wcuedu.net`
- `WCU_FORUM_MAIL_FROM_NAME` defaults to `William Chichi University Forum`

Production forum reCAPTCHA is configured through the same untracked env file
and seeded into wpForo's built-in `wpforo_recaptcha` option. Keep the secret
outside git:

- `WCU_FORUM_RECAPTCHA_SITE_KEY`
- `WCU_FORUM_RECAPTCHA_SECRET_KEY`
- `WCU_FORUM_RECAPTCHA_THEME` defaults to `light`
- `WCU_FORUM_RECAPTCHA_VERSION` defaults to `v2_checkbox`
- `WCU_FORUM_RECAPTCHA_SCORE_THRESHOLD` defaults to `0.5` for v3-compatible
  wpForo versions

Run the production installer on the VM as `root` after copying the scripts:

```bash
bash /root/wcu-forum-install/install-wordpress-forum.sh
```

The installer is intended to be idempotent. It installs the package stack,
creates WordPress config from `/root/.wcu-forum-prod.env`, installs wpForo,
creates the `/community/` page, installs the WCU child theme and SMTP MU
plugin, enables wpForo email confirmation for registration, seeds the forum
structure through wpForo APIs, writes the forum nginx site, validates nginx, and
reloads nginx.

## Verification Commands

From PowerShell:

```powershell
wsl -d Ubuntu-24.04 -- bash -lc "php -v | head -n1"
wsl -d Ubuntu-24.04 -- bash -lc "composer --version"
wsl -d Ubuntu-24.04 -- bash -lc "wp --info | head -n8"
wsl -d Ubuntu-24.04 -- bash -lc "systemctl is-active apache2; systemctl is-active mysql"
wsl -d Ubuntu-24.04 -- bash -lc "cd /var/www/wcu-forum && wp core version && wp plugin status wpforo"
wsl -d Ubuntu-24.04 -- bash -lc "wp --path=/var/www/wcu-forum theme list --allow-root | grep wcu-forum"
wsl -d Ubuntu-24.04 -- bash -lc "wp --path=/var/www/wcu-forum eval-file /mnt/c/path/to/WCU/server/scripts/seed-wpforo-forum.php"
curl.exe -I http://localhost:8081/community/
```

Use `localhost` for the forum URL. On WSL, `127.0.0.1:8081` may not forward the
same way as `localhost`.

## Service Ports

- Static preview: chosen by `scripts/serve-site.ps1`.
- Admissions backend: `127.0.0.1:8080`.
- WordPress/wpForo local development: `localhost:8081`.
- Production HTTP/HTTPS: `80` and `443` behind Cloudflare.
- Production MySQL: `127.0.0.1:3306` only.
- Production PHP-FPM: `/run/php/php8.3-fpm.sock`.

## Updating Requirements

When a new dependency, version floor, port, local service, or setup step becomes
required for team development, update this file and add the related operating
note to `AGENTS.md` if agents need to follow it.
