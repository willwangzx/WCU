# Oracle API Deployment

This directory is the Oracle-hosted backend for WCU application submissions and the admissions admin panel.

Current production note, updated 2026-05-17: production is now on
`178.238.234.111` using Cloudflare proxied A records directly to the VM. Nginx
listens on `80/443`, serves `/srv/wcu-site`, and reverse proxies `/api` and
`/admin` to `wcu-backend.service` on `127.0.0.1:8080`. `cloudflared` is
not installed as an active systemd unit in the current production path. See
`docs/current-deployment.md` for the live topology.

## What lives here

- `public/api/application.php`: JSON submission endpoint for the public admissions form
- `public/admin/index.php`: password-protected admin dashboard for reviewing submissions
- `src/application.php`: validation and write logic for applications
- `src/admin.php`: admin authentication, filtering, and export helpers
- `sql/schema.sql`: database schema for application storage
- `sql/schema.sqlite.sql`: SQLite schema used by the low-memory PHP/Python deployments
- `config.example.php`: copy to `config.php` on the server and fill in real values
- `apache/wcu-api.conf.example`: Apache virtual host template
- `config/nginx-wcu.conf.example`: Nginx site template for low-memory deployments
- `scripts/install-oracle-vm.sh`: first-pass package installation for Oracle Linux 9
- `scripts/install-oracle-vm-lite.sh`: lower-memory install path using nginx + sqlite
- `front_proxy.py`: zero-extra-package HTTPS entrypoint for static pages plus `/api` and `/admin` reverse proxying
- `python_backend.py`: zero-extra-package Python backend for ultra-small VMs
- `config.python.example.json`: config template for the Python backend
- `scripts/hash-admin-password.py`: helper for generating the admin password hash
- `config/mariadb-low-memory.cnf`: conservative MariaDB tuning for a 2 GB VM
- `config/php-fpm-low-memory.conf`: conservative PHP-FPM pool limits for a 2 GB VM
- `config/php-fpm-very-low-memory.conf`: extra-conservative PHP-FPM pool for sub-1 GB VMs
- `config/cloudflared-front.yml.example`: locally-managed Cloudflare Tunnel example for the current single-node setup
- `config/wcu-backend-cloudflare.override.conf.example`: systemd override example to bind the Python service to `127.0.0.1:8080`
- `scripts/install-cloudflared.sh`: low-pressure `cloudflared` install helper for Oracle Linux
- `scripts/install-cloudflared-binary.sh`: lower-pressure single-binary `cloudflared` installer for ultra-small VMs
- `scripts/wcu-backend-healthcheck.sh`: tiny localhost watchdog that restarts the backend if `/api/application.php` stops responding
- `systemd/wcu-backend-watchdog.service.example`: oneshot unit template for the backend healthcheck
- `systemd/wcu-backend-watchdog.timer.example`: timer template that runs the healthcheck every minute

## Expected runtime layout

Deploy this folder to a location such as `/srv/wcu-api`, then expose:

- `/api` from `/srv/wcu-api/public/api`
- `/admin` from `/srv/wcu-api/public/admin`

The main website can continue to live in a separate document root such as `/srv/wcu-site`.

For very small Oracle VMs, you can skip MariaDB entirely and run the backend with SQLite by setting `database.driver` to `sqlite` and pointing `database.sqlite_path` at a writable file such as `/var/lib/wcu-data/wcu.sqlite`. The PHP backend will initialize the SQLite schema from `sql/schema.sqlite.sql` on first start if the database file is empty.

If the VM is too small to install PHP packages reliably, you can also run `python_backend.py` directly with the system Python. It serves the admissions API on `/api/application.php` and a simple password-protected admin panel on `/admin/`.

Historical tunnel deployment: Cloudflare Tunnel can sit directly in front of
`python_backend.py` and expose the static site, `/api`, and `/admin` together
from `127.0.0.1:8080`. This is not the current production path after the
2026-05-17 migration, but the files remain useful for rollback or small-VM
deployments.

For a two-VM split deployment, `front_proxy.py` can sit on the public VM with the Cloudflare origin certificate, serve the static site locally, and reverse proxy `/api` and `/admin` to the private backend VM.

## Before going live

1. Copy `config.example.php` to `config.php`.
2. Set a real database password and allowed frontend origins.
3. Add an `admin.password_hash` value for the admissions dashboard.
4. Import `sql/schema.sql` into MariaDB, or point SQLite at a writable path and let the backend initialize `sql/schema.sqlite.sql`.
5. Enable the Apache site and put the origin behind HTTPS.

## Change the admin password

Generate a new password hash:

```bash
php -r "echo password_hash('your-new-password', PASSWORD_DEFAULT), PHP_EOL;"
```

Then paste the output into `config.php` under `admin.password_hash` and reload Apache/PHP-FPM if needed.
