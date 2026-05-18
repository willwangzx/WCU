# TODO / Bug Tracker

## 🔴 Critical

- **[security] CSRF protection missing in Python backend DELETE handler** — [server/python_backend.py:289-303](server/python_backend.py#L289-L303)
  The `/admin/delete` POST handler accepts a plain `id` field with no CSRF token. If the browser has cached Basic Auth credentials, cross-origin form submissions can delete applications without consent. The PHP version uses session-based CSRF tokens — this is a regression.

- **[security] PHP admin CSV export has no CSRF check** — [server/public/admin/index.php:62-69](server/public/admin/index.php#L62-L69)
  An authenticated admin can be tricked into triggering a CSV export via `<img src="/admin/?action=export">` — data exfiltration vector.

- **[security] Python backend uses SHA-256 instead of proper password hash** — [server/python_backend.py:189-193](server/python_backend.py#L189-L193)
  SHA-256 can be brute-forced at billions of guesses/second. PHP backend uses `password_verify()` (bcrypt/argon2). Switch to `hashlib.scrypt` or `bcrypt`.

## 🟠 High

- **[security] JavaScript honeypot bypass** — [assets/js/script.js:254-264](assets/js/script.js#L254-L264)
  `submitSplitApplication()` sends JSON POST without the `website` honeypot field. The Python backend defaults missing keys to `""` so the honeypot never fires during JS-based submissions. Fix: add `"website": ""` to the payload.

- **[bug] Hidden fields in apply-writing.html never populated** — [pages/apply-writing.html:61-72](pages/apply-writing.html#L61-L72)
  Hidden fields (`splitFirstName`, `splitLastName`, etc.) initialize empty and are never filled by JavaScript. If JS fails or the form submits natively, the API receives empty basic-info fields.

## 🟡 Medium

- **[bug] Email From header malformed when from_address is empty** — [server/src/application.php:265-266](server/src/application.php#L265-L266)
  `From: WCU Admissions Office <>` is syntactically invalid. Many MTAs will reject it.

- **[bug] Markdown heading levels off by one** — [assets/js/content-format.js:92](assets/js/content-format.js#L92)
  `level + 1` means `# heading` becomes `<h2>`, `##` → `<h3>`, `###` → `<h4>`. No way to produce `<h1>`. Also, `####+` falls through to paragraph text.

- **[security] Missing `rel="noopener"` in PHP admin portfolio link** — [server/public/admin/index.php:600](server/public/admin/index.php#L600)
  `rel="noreferrer"` without `noopener` lets the opened page access `window.opener` in older browsers. Python version has `noopener noreferrer`.

- **[bug] Inconsistent email validation between backends** — [server/python_backend.py:133](server/python_backend.py#L133)
  Python uses a simple `"@" in email and "." in domain` check. PHP uses `FILTER_VALIDATE_EMAIL`. E.g. `user@example` passes Python, fails PHP.

- **[security] No lockout / rate limiting on admin login** — both backends
  Brute-force attacks against admin credentials have no throttling.

## ℹ️ Low

- **[security] Password comparison not constant-time** — [server/python_backend.py:193](server/python_backend.py#L193)
  Python `==` short-circuits on first differing byte. Use `hmac.compare_digest()`.

- **[robustness] No explicit rejection of unsupported Content-Types** — [server/python_backend.py:226-230](server/python_backend.py#L226-L230)
  `multipart/form-data` and other types are blindly fed to `parse_qs()`. Should return 415 for unsupported types.

---

## Dead Code / Useless Files

Current admissions production uses **nginx + Python backend + SQLite**. The
production forum now separately uses **WordPress/wpForo + PHP-FPM + MySQL**.
Apache, `front_proxy.py`, and Cloudflare Tunnel are not part of the current
production path.

### In Active Files

- **[dead code] 12 hidden fields in apply-writing.html never read** — [pages/apply-writing.html:61-72](pages/apply-writing.html#L61-L72)
  `splitFirstName`, `splitLastName`, `splitEmail`, `splitPhone`, `splitBirthMonth`, `splitBirthDay`, `splitBirthYear`, `splitGender`, `splitCitizenship`, `splitEntryTerm`, `splitProgram`, `splitSchoolName` — none are populated or referenced by any JavaScript. The `submitSplitApplication()` function reads from `sessionStorage` directly.

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
