# Changelog

## 2026-05-18

- Added installer-managed forum email verification and SMTP support, plus the
  WCU WordPress child theme and static forum entrance cleanup so the forum can
  share the main site's navigation, typography, color, and button styling.
- Fixed the public forum deployment blocker by installing WordPress/wpForo on
  the production VM, serving `https://forum.wcuedu.net/community/` through a
  dedicated nginx/PHP-FPM/MySQL stack, seeding the WCU forum structure, and
  verifying that the URL returns wpForo markup instead of the static homepage.
