# Oracle API Deployment

This directory is the Oracle-hosted backend for WCU application submissions.

## What lives here

- `public/api/application.php`: JSON submission endpoint for the Cloudflare-hosted frontend
- `sql/schema.sql`: database schema for application storage
- `config.example.php`: copy to `config.php` on the server and fill in real values
- `apache/wcu-api.conf.example`: Apache virtual host template
- `scripts/install-oracle-vm.sh`: first-pass package installation for Oracle Linux 9
- `config/mariadb-low-memory.cnf`: conservative MariaDB tuning for a 2 GB VM
- `config/php-fpm-low-memory.conf`: conservative PHP-FPM pool limits for a 2 GB VM

## Expected runtime layout

Deploy the backend to a location such as `/srv/wcu-api`. When the website is hosted on the same server, point Apache at:

```text
/srv/wcu-site
```

Then expose the API with an Apache alias such as `/api -> /srv/wcu-api/public/api`.

## Before going live

1. Copy `config.example.php` to `config.php`.
2. Set a real database password and allowed frontend origins.
3. Import `sql/schema.sql` into MariaDB.
4. Point `assets/js/site-config.js` on the frontend to your API origin.
5. Enable the Apache site and put the API behind HTTPS before connecting it to a Cloudflare-hosted site.
