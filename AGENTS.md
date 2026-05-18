# AGENTS.md

This file is for coding agents working in this repository.


## Self-Recursion

Update this file when deployment topology, server paths, service names, DNS,
or security posture changes.

## Current Production Server

- Public IP: `178.238.234.111`.
- SSH user: `root`.
- SSH key: `keys/wcu-178.238.234.111-root-ed25519`.
- SSH command:
  ```powershell
  ssh -i .\keys\wcu-178.238.234.111-root-ed25519 root@178.238.234.111
  ```
- Do not store the root password in this file or anywhere in git. Use SSH key
  access and keep any break-glass password in an external password manager.

## Project Overview

- Project: William Chi-Chi University static site plus admissions backend.
- Static entry point: `index.html`.
- Shared assets: `assets/`.
- Content pages: `pages/`.
- Backend: `server/`.
- Current deployment docs: `docs/current-deployment.md`.
- Team development structure: `docs/development-structure.md`.
- Team development requirements: `docs/development-requirements.md`.
- Historical/alternative deployment docs: `docs/cloudflare-tunnel-deployment.md`,
  `docs/server-configuration.md`, and `server/README.md`.

## Team Cooperation

- Treat `AGENTS.md`, `docs/development-structure.md`, and
  `docs/development-requirements.md` as the shared operating contract for people
  and agents.
- Before editing, check `git status --short --branch` and avoid overwriting
  files with unrelated local changes.
- Keep work on a branch and keep each commit focused on one logical change.
- Do not reformat, rename, move, or delete unrelated files while doing a scoped
  task.
- If another person's change touches the same file, read the file carefully and
  preserve their work while making the smallest compatible edit.
- Use `TODO.md` for known bugs and `changelog.md` for fixed bugs. Create
  `changelog.md` if the first fix is recorded and it does not exist yet.
- Update `docs/development-structure.md` when folder layout, ownership
  boundaries, generated outputs, or local-only areas change.
- Update `docs/development-requirements.md` when required tools, versions,
  service ports, local setup commands, or development URLs change.
- Never commit machine-local credentials, database dumps with real data, SSH
  keys, API keys, WordPress admin passwords, or local config files.

## Common Commands

- Build static output: `npm run build`.
- Run local smoke tests: `.\scripts\run-tests.ps1`.
- Start local preview: `.\scripts\serve-site.ps1`.
- Verify local WordPress/wpForo forum:
  ```powershell
  curl.exe -I http://localhost:8081/community/
  ```

## Runtime Information

- Current production VM public IP: `178.238.234.111`.
- Previous Oracle VM public IP: `161.153.87.137` (old backend fallback only; do
  not route production traffic there).
- Public site domains: `wcuedu.net`, `www.wcuedu.net`, `api.wcuedu.net`.
- DNS mode: Cloudflare proxied A records directly to `178.238.234.111`
  (B方案). Cloudflare Tunnel is not part of the current production path.
- Frontend document root: `/srv/wcu-site`.
- Backend app directory: `/opt/wcu-backend`.
- SQLite data directory: `/var/lib/wcu-data`.
- Backend public paths: `/api` and `/admin`.
- Local backend target: `http://127.0.0.1:8080`.
- Local WordPress/wpForo development target: `http://localhost:8081/community/`
  with files at `/var/www/wcu-forum` inside WSL.
- Public edge: `nginx` listens on `80` and `443`, serves the static site, and
  reverse proxies `/api` and `/admin` to `127.0.0.1:8080`.
- Systemd services:
  - `nginx`: active/enabled.
  - `wcu-backend`: active/enabled and running as the dedicated `wcu` user.
  - `fail2ban`: active/enabled for SSH.
  - `cloudflared`: removed/not-found under the current DNS A-record setup.
- Server API key: keep the real value outside git. Use the deployment secret or environment variable named `WCU_SERVER_API_KEY`; do not paste the raw key into this file.
- Cloudflare Tunnel token: keep the real token outside git. Install it on the server with `sudo cloudflared service install <TUNNEL_TOKEN>`.

## Security Rules

- Do not commit real API keys, tunnel tokens, database passwords, SSH passwords,
  private keys, origin certificates, or machine-local config files.
- The following local files must stay untracked: `keys/`, `server/config.php`, `server/config.python.json`, `origincertificate.txt`, and `privatekey.txt`.
- Local WordPress development credentials such as `~/.wcu-forum-dev.env`
  must stay outside git.
- Use `server/config.example.php` and `server/config.python.example.json` as templates only.
- If a task needs a secret, reference the expected variable or config key instead of writing the secret into source control.

## Deployment Notes

- Current production path is Cloudflare DNS proxied A records to the new VM:
  - `wcuedu.net` -> `178.238.234.111`
  - `www.wcuedu.net` -> `178.238.234.111`
  - `api.wcuedu.net` -> `178.238.234.111`
- Cloudflare SSL/TLS mode should be `Full (strict)`.
- Nginx has a Cloudflare Origin Certificate installed at:
  - certificate: `/etc/nginx/ssl/wcuedu-origin.crt`
  - private key: `/etc/nginx/ssl/wcuedu-origin.key`
- Do not edit generated `dist/` files unless the task specifically asks for deployment output.
- Build locally with `npm run build`, upload the contents of `dist/` to
  `/srv/wcu-site`, then verify:
  ```powershell
  curl.exe -I https://wcuedu.net/
  curl.exe https://wcuedu.net/api/application.php
  curl.exe -I https://wcuedu.net/admin/
  ```
- Expected verification:
  - Homepage: `200 OK`.
  - API: `{"ok": true, "service": "wcu-applications-api"}`.
  - Admin: `401 Unauthorized` until valid Basic Auth credentials are supplied.

## Security State

Hardening applied on 2026-05-17:

- SSH key login verified.
- SSH password authentication disabled.
- Root password locked.
- `PermitRootLogin` is effectively `without-password` / key-only.
- `ufw` is active.
- SSH is allowed on `22/tcp`.
- HTTP/HTTPS on `80/443` are allowed only from Cloudflare IP ranges.
- Direct origin access to `178.238.234.111:80/443` is blocked by firewall.
- `fail2ban` is active/enabled for SSH.
- `/srv/wcu-site` permissions are directories `0755`, files `0644`.
- `wcu-backend` runs as the dedicated non-root `wcu` service user.
- Backend CORS only allows `https://wcuedu.net` and `https://www.wcuedu.net`.
- Nginx sends basic security headers and rate-limits `/admin`.
- The old `cloudflared` systemd token unit was removed.

Remaining maintenance:

- Schedule a reboot; `/var/run/reboot-required` still existed after hardening.
- Keep Cloudflare IP allowlists in `ufw` updated if Cloudflare changes its
  published ranges.

## Coding Guidelines

- Keep changes scoped to the requested area.
- Preserve the current static site layout unless a task explicitly changes it.
- Prefer existing scripts and config examples over inventing new deployment paths.
- Do not edit generated `dist/` files unless the task specifically asks for deployment output.
- For backend changes, check both PHP and Python deployment paths when behavior may overlap.

## Workflow

1. record bugs that need to be fixed in todo list (TODO.md), and delete the ones that are fixed and record the fixed bugs in the changelog (changelog.md)
2. Before making any code changes, check the TODO.md file to see if there are any existing bugs that need to be fixed. If there are, prioritize fixing those bugs before adding new features or making other changes. Before applying the change, think why this approach is better than the existing one, and if it is not, then do not apply the change.
3. After making code changes, run all the tests to ensure that the changes do not break any existing functionality. If any tests fail, investigate the cause and fix the issue before proceeding.
4. After applying changes to the code, ensure that you update the documentation in this file to reflect the changes made. This includes updating any relevant sections such as architecture, testing, or known limitations.
