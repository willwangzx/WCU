# Changelog

## 2026-05-18

- Fixed the public forum deployment blocker by installing WordPress/wpForo on
  the production VM, serving `https://forum.wcuedu.net/community/` through a
  dedicated nginx/PHP-FPM/MySQL stack, seeding the WCU forum structure, and
  verifying that the URL returns wpForo markup instead of the static homepage.
