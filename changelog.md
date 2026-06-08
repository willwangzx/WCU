# Changelog

## 2026-05-18

- Added optional Google reCAPTCHA protection for admissions submissions, with a
  public site-key hook in the static application flow and server-side token
  verification in both Python and legacy PHP backend paths.
- Added installer/seeder support for wpForo's built-in forum reCAPTCHA settings
  using untracked `WCU_FORUM_RECAPTCHA_*` production environment values.
- Fixed the TODO security and form regressions: Python admin delete/export now
  require CSRF tokens, admin login attempts are throttled in both backend paths,
  Python admin passwords use scrypt-compatible hashes, unsupported API content
  types return 415, application email validation is stricter, split-form
  submissions include the honeypot and synced hidden fields, markdown headings
  keep their declared level, PHP CSV export requires CSRF, PHP portfolio links
  use `noopener`, and confirmation email headers omit malformed empty `From`
  mailboxes.
- Added installer-managed forum email verification and SMTP support, plus the
  WCU WordPress child theme and static forum entrance cleanup so the forum can
  share the main site's navigation, typography, color, and button styling.
- Fixed the public forum deployment blocker by installing WordPress/wpForo on
  the production VM, serving `https://forum.wcuedu.net/community/` through a
  dedicated nginx/PHP-FPM/MySQL stack, seeding the WCU forum structure, and
  verifying that the URL returns wpForo markup instead of the static homepage.
