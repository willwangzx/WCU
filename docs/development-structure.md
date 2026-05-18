# Development Structure

This file defines the shared repository structure for team development. Keep it
updated when folders, ownership boundaries, generated outputs, or local-only
development areas change.

## Canonical Layout

```text
.
|-- index.html                 Static site entry point
|-- assets/                    Shared frontend assets
|   |-- css/                   Global styles
|   |-- img/                   Shared image assets
|   `-- js/                    Browser JavaScript and site config
|-- pages/                     Static content pages
|   `-- schools/               School detail pages
|-- server/                    Admissions backend and historical backend assets
|-- scripts/                   Local build, preview, and smoke-test scripts
|-- tests/                     Test fixtures and checks
|-- docs/                      Team docs, deployment notes, and planning docs
|-- AGENTS.md                  Agent and contributor operating rules
|-- README.md                  Project overview for humans
|-- TODO.md                    Known bugs and prioritized maintenance work
`-- dist/                      Generated static output; do not edit by hand
```

## Ownership Boundaries

- Static site work belongs in `index.html`, `pages/`, `assets/css/`,
  `assets/img/`, and `assets/js/`.
- Reusable UI, visual design, homepage section, and asset-use patterns are
  documented in `docs/ui-design-patterns.md`.
- Admissions backend work belongs in `server/`. Check both Python and legacy PHP
  paths when behavior overlaps.
- Deployment and operational changes belong in `docs/current-deployment.md` and
  `AGENTS.md`.
- Historical deployment notes stay in `docs/cloudflare-tunnel-deployment.md`,
  `docs/server-configuration.md`, and `server/README.md` unless the task is
  explicitly about cleanup or history.
- Forum development uses a local WordPress/wpForo install outside the static
  build output. The current direct WSL development path is `/var/www/wcu-forum`.

## Team Workflow

- Work on a branch for every non-trivial change.
- Check `git status --short --branch` before editing so you do not overwrite
  another person's local work.
- Keep commits scoped to one logical change.
- Do not reformat unrelated files.
- Do not edit generated `dist/` files unless the task specifically asks for
  deployment output.
- Update `TODO.md` when a new bug is discovered. Remove fixed bugs from
  `TODO.md` and record the fix in `changelog.md`.
- Update this file when the repository structure or ownership boundaries change.
- Update `docs/ui-design-patterns.md` when shared visual design patterns change.

## Local-Only Files

The following must remain local to each developer and must not be committed:

- `keys/`
- `server/config.php`
- `server/config.python.json`
- `origincertificate.txt`
- `privatekey.txt`
- local WordPress credentials such as `~/.wcu-forum-dev.env`
- local database dumps that contain real user or admin data
