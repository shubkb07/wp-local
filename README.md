# wp-local

Single-container local WordPress stack with Apache/PHP, Adminer, Mailpit, MariaDB, Redis, WP-CLI, cloudflared, and host-based site routing.

Images:

- `ghcr.io/shubkb07/wp-local:0.0.12-alpha`
- `shubkb07/wp-local:0.0.12-alpha`

## Requirements

- Docker with Compose v2
- Linux host
- Optional: nginx on the host for `https://*.local`
- Optional: local wildcard certificate at `~/certs/_wildcard.local.pem` and `~/certs/_wildcard.local-key.pem`

## Install

```sh
git clone git@github.com:shubkb07/wp-local.git
cd wp-local
cp .env.example .env
```

Edit `.env` before first start. At minimum, set:

```env
HOST_USERNAME=your-linux-username
SITES=apple.local,meow.local
```

Start:

```sh
docker compose pull
docker compose up -d
```

Without host nginx, open sites with the configured port:

```text
http://apple.local:8080
http://meow.local:8080
```

With host nginx configured, open:

```text
https://apple.local
https://meow.local
```

## Configuration

`.env` supports:

```env
APACHE_HTTP_PORT=8080
WEB_IMAGE=ghcr.io/shubkb07/wp-local:0.0.12-alpha

LOCAL_WP_DATA_PATH=./data
WP_SITES_PATH=./data/wp-sites
LOCAL_WP_ENV_FILE=./.env

MYSQL_USER=root
MYSQL_PASSWORD=local_root_password
PHP_MEMORY=512M
HOST_USERNAME=your-linux-username

CLOUDFLARED_API_KEY=
CLOUDFLARED_SITES=

SITES=apple.local,meow.local
NO_DELETE_SITES=
```

`SITES` is a comma-separated list of local hostnames. Each site gets its own database name by replacing `.` with `_` and `-` with `__`; for example, `neuro-ai.local` becomes `neuro__ai_local`.

`WP_SITES_PATH` stores each site's `wp-config.php` and `wp-content`. WordPress core is provided by the image. When a site's `wp-content` folder does not exist yet, wp-local creates `uploads`, `themes`, and `plugins`, then adds the bundled `twentytwentyfive` theme. Existing `wp-content` folders are left alone.

MariaDB and Redis are stored in Docker named volumes, so data survives image upgrades. They are removed only if you run a volume-removing command such as `docker compose down -v`.

`LOCAL_WP_DATA_PATH` stores generated helper files:

- `data/local-wildcard.conf`
- `data/make-changes.md`
- `data/cloudflared-urls.txt`
- `data/cloudflared/`

`PHP_MEMORY` controls PHP `memory_limit`; the image defaults to `512M`. PHP upload/import limits default to `1G`, with higher Apache/nginx/PHP/MariaDB timeouts for large WordPress/Adminer imports.

`HOST_USERNAME` is used when generating `data/local-wildcard.conf` so nginx points at `/home/{user}/certs/_wildcard.local.pem`. Set it to the output of `whoami` on the host.

## Host Setup

After the container starts, it generates `data/make-changes.md` with host-specific commands.

Apply hosts and nginx setup:

```sh
cat data/make-changes.md
```

The generated commands will:

- update `/etc/hosts`
- write `/etc/nginx/sites-available/local-wildcard.conf`
- reload nginx
- restart the container

If you want to do it manually, the important nginx include is:

```nginx
include /absolute/path/to/this/project/data/local-wildcard.conf;
```

After any `SITES` or `HOST_USERNAME` change:

```sh
docker compose restart web
sudo nginx -t && sudo systemctl reload nginx
```

## Site Lifecycle

Add a site:

```env
SITES=apple.local,meow.local,new-site.local
```

Then restart:

```sh
docker compose restart web
```

Remove a site by removing it from `SITES`. On startup, wp-local deletes matching `*_local` databases and `data/wp-sites/{host}` folders for removed sites.

Disable cleanup entirely:

```env
NO_DELETE_SITES=true
```

Protect only specific removed sites:

```env
NO_DELETE_SITES=meow.local,apple.local
```

## Adminer

Adminer is available on every configured host:

```text
https://apple.local/adminer
```

It automatically opens the current host's database and fixes tampered `server`, `username`, and `db` query values.

Large imports are supported up to `1G`. If you get `413 Request Entity Too Large`, regenerate/reload host nginx:

```sh
docker compose restart web
sudo nginx -t && sudo systemctl reload nginx
```

## Mailpit

Mailpit is available on every configured host:

```text
https://apple.local/mailpit
```

All PHP `mail()` traffic is routed to Mailpit through local SMTP on `127.0.0.1:1025`. WordPress password resets, plugin emails, WooCommerce emails, and any other PHP mail sent by any configured site are captured in the same local Mailpit dashboard.

Mailpit storage is in-memory. Messages stay available while the container is running and are cleared on every container restart.

No login is required. This is intentionally open for local development only.

## WP-CLI

Run WP-CLI inside the container:

```sh
docker compose exec web wp --path=/var/www/html --url=neuro-ai.local --allow-root option get siteurl
```

Create an admin user:

```sh
docker compose exec web wp --path=/var/www/html --url=neuro-ai.local --allow-root user create shub shubkb07@gmail.com --user_pass=shub --role=administrator
```

Import a SQL dump from a site folder:

```sh
docker compose exec -T web wp --path=/var/www/html --url=neuro-ai.local --allow-root db import - < data/wp-sites/neuro-ai.local/wp-content/neuro.sql
```

Replace production URLs:

```sh
docker compose exec web wp --path=/var/www/html --url=neuro-ai.local --allow-root search-replace 'https://neuro-ai.com.au' 'https://neuro-ai.local' --all-tables --precise
```

## Clone Sites

Clone a local site with `wpl`:

```sh
docker compose exec web wpl clone --from=neuro-ai.local --to=neuroai.local
docker compose exec web wpl clone --from=neuro-ai.local --to=neuroai.local --force
```

`wpl clone` copies the source database and `data/wp-sites/{host}` folder, appends the target host to `.env` `SITES`, refreshes generated local helper files, and runs WP-CLI search-replace when the cloned site is already installed.

If the target exists, `wpl clone` does nothing unless `--force` is passed.

## Cloudflared

`CLOUDFLARED_SITES` maps one-to-one with `SITES`.

- Empty entry: skip that site
- `-`: create a random `trycloudflare.com` tunnel
- Hostname: request that hostname

Example:

```env
SITES=apple.local,meow.local,blog.local
CLOUDFLARED_SITES=apple.example.com,,-
```

`CLOUDFLARED_API_KEY` is passed to cloudflared as token environment for authenticated tunnel setups. Random `-` tunnels do not require it.

Tunnel logs:

```text
data/cloudflared/
```

Discovered URLs:

```text
data/cloudflared-urls.txt
```

## Upgrade

Change `WEB_IMAGE` in `.env`, then run:

```sh
docker compose pull
docker compose up -d
```

Database and Redis data remain in Docker named volumes. Site content remains in `data/wp-sites`.

## Local Image Build

Only use this when developing the image locally:

```sh
docker compose -f docker-compose.yml -f docker-compose.build.yml build web
docker compose up -d
```

## Troubleshooting

If nginx fails with `/home/root/certs/...`, set `HOST_USERNAME` in `.env` to your host username, then regenerate:

```sh
docker compose restart web
sudo nginx -t && sudo systemctl reload nginx
```

If Git reports dubious ownership inside a site folder:

```sh
git config --global --add safe.directory /path/to/data/wp-sites/site.local/wp-content
```

If host file permissions block editing `data/`:

```sh
sudo setfacl -R -m u:$(whoami):rwx data
sudo setfacl -R -d -m u:$(whoami):rwx data
```

Useful commands:

```sh
docker compose ps
docker compose logs -f web
docker compose restart web
docker compose down --remove-orphans
```
