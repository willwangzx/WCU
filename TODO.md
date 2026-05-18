# TODO / Bug Tracker

No active bug items are currently tracked. Fixed issues are recorded in
`changelog.md`.

---

## Dead Code / Useless Files

Current admissions production uses **nginx + Python backend + SQLite**. The
production forum now separately uses **WordPress/wpForo + PHP-FPM + MySQL**.
Apache, `front_proxy.py`, and Cloudflare Tunnel are not part of the current
production path.

### Unused Admissions Backend Files (PHP)

- `server/public/api/application.php` — PHP admissions API endpoint, unused
- `server/public/admin/index.php` — PHP admissions admin dashboard, unused
- `server/public/index.php` — PHP front controller, unused
- `server/src/bootstrap.php` — PHP config/helpers, unused
- `server/src/application.php` — PHP application logic, unused
- `server/src/admin.php` — PHP admin logic, unused
- `server/config.example.php` — PHP config template, unused
- `server/sql/schema.sql` — legacy admissions MySQL/MariaDB schema, unused
- `pages/install.php` — PHP installer page, unused

### Unused Proxy / Infrastructure Files

- `server/front_proxy.py` — Historical custom HTTPS proxy; nginx replaced it
- `server/scripts/install-cloudflared.sh` — Cloudflare Tunnel not in use
- `server/scripts/install-cloudflared-binary.sh` — Same
- `server/scripts/install-front-lighttpd.sh` — Lighttpd not deployed
- `server/systemd/` — systemd units for cloudflared/lighttpd services not in use
- `server/config/cloudflared-front.yml.example` — Cloudflare Tunnel config template
- `server/config/lighttpd-front-proxy.conf.example` — Lighttpd config template

### Stale Documentation

- `docs/server-configuration.md` — Documents old two-VM/front_proxy setup (flagged historical in AGENTS.md)
- `docs/cloudflare-tunnel-deployment.md` — Cloudflare Tunnel removed from production (flagged historical)
- `server/README.md` — Documents deployment architectures that aren't current
