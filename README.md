# wp-local

Single-container local WordPress stack with Apache/PHP, Adminer, MariaDB, Redis, and host-based site routing.

Image: `ghcr.io/shubkb07/wp-local:0.0.1-alpha`

## Install

```sh
git clone git@github.com:shubkb07/wp-local.git
cd wp-local
docker compose up -d
```

If you want to change hostnames, port, site-data path, or the local database password:

```sh
cp .env.example .env
# edit .env
docker compose up -d
```

## Config

`.env` supports:

```env
APACHE_HTTP_PORT=8080
WEB_IMAGE=ghcr.io/shubkb07/wp-local:0.0.1-alpha
WP_SITES_PATH=./data/wp-sites
MYSQL_USER=root
MYSQL_PASSWORD=local_root_password
SITES=apple.local,meow.local
```

`WP_SITES_PATH` stores each site's `wp-config.php` and `wp-content`. MariaDB and Redis data are stored in Docker volumes managed by Compose.

Adminer is available at `/adminer` on each configured host. It always opens the current host's database and fixes tampered `server`, `username`, and `db` query values.

## Local Image Build

```sh
docker compose -f docker-compose.yml -f docker-compose.build.yml build web
docker compose up -d
```

## Host Setup

Host/nginx helper files are in `data/edits/`.

For the default sites, the host machine must resolve:

```text
127.0.0.1 apple.local
127.0.0.1 meow.local
```
