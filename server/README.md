# Oracle API Deployment

This directory is the Oracle-hosted backend for WCU application submissions and the admissions admin panel.

## What lives here

- `public/api/application.php`: JSON submission endpoint for the public admissions form
- `public/admin/index.php`: password-protected admin dashboard for reviewing submissions
- `src/application.php`: validation and write logic for applications
- `src/admin.php`: admin authentication, filtering, and export helpers
- `sql/schema.sql`: database schema for application storage
- `config.example.php`: copy to `config.php` on the server and fill in real values
- `apache/wcu-api.conf.example`: Apache virtual host template
- `scripts/install-oracle-vm.sh`: first-pass package installation for Oracle Linux 9
- `config/mariadb-low-memory.cnf`: conservative MariaDB tuning for a 2 GB VM
- `config/php-fpm-low-memory.conf`: conservative PHP-FPM pool limits for a 2 GB VM

## Expected runtime layout

Deploy this folder to a location such as `/srv/wcu-api`, then expose:

- `/api` from `/srv/wcu-api/public/api`
- `/admin` from `/srv/wcu-api/public/admin`

The main website can continue to live in a separate document root such as `/srv/wcu-site`.

## Before going live

1. Copy `config.example.php` to `config.php`.
2. Set a real database password and allowed frontend origins.
3. Add an `admin.password_hash` value for the admissions dashboard.
4. Import `sql/schema.sql` into MariaDB.
5. Enable the Apache site and put the origin behind HTTPS.

## Change the admin password

Generate a new password hash:

```bash
php -r "echo password_hash('your-new-password', PASSWORD_DEFAULT), PHP_EOL;"
```

Then paste the output into `config.php` under `admin.password_hash` and reload Apache/PHP-FPM if needed.
