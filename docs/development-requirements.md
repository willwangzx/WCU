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

## Verification Commands

From PowerShell:

```powershell
wsl -d Ubuntu-24.04 -- bash -lc "php -v | head -n1"
wsl -d Ubuntu-24.04 -- bash -lc "composer --version"
wsl -d Ubuntu-24.04 -- bash -lc "wp --info | head -n8"
wsl -d Ubuntu-24.04 -- bash -lc "systemctl is-active apache2; systemctl is-active mysql"
wsl -d Ubuntu-24.04 -- bash -lc "cd /var/www/wcu-forum && wp core version && wp plugin status wpforo"
curl.exe -I http://localhost:8081/community/
```

Use `localhost` for the forum URL. On WSL, `127.0.0.1:8081` may not forward the
same way as `localhost`.

## Service Ports

- Static preview: chosen by `scripts/serve-site.ps1`.
- Admissions backend: `127.0.0.1:8080`.
- WordPress/wpForo local development: `localhost:8081`.
- Production HTTP/HTTPS: `80` and `443` behind Cloudflare.

## Updating Requirements

When a new dependency, version floor, port, local service, or setup step becomes
required for team development, update this file and add the related operating
note to `AGENTS.md` if agents need to follow it.
