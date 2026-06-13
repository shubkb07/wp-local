# wp-local

Single-container local WordPress stack with Apache/PHP, Adminer, MariaDB, Redis, and host-based site routing.

Images:

- `ghcr.io/shubkb07/wp-local:0.0.8-alpha`
- `shubkb07/wp-local:0.0.8-alpha`

## Install

Install from the published image:

```sh
git clone git@github.com:shubkb07/wp-local.git
cd wp-local
docker compose pull
docker compose up -d
```

The repository includes a working `.env`. To customize it:

```sh
cp .env.example .env
# edit .env
docker compose pull
docker compose up -d
```

Open the configured hosts after your host machine resolves them to localhost. For the default config, use `http://apple.local:8080` and `http://meow.local:8080`, or put nginx in front if you want `https://apple.local`.

## Config

`.env` supports:

```env
APACHE_HTTP_PORT=8080
WEB_IMAGE=ghcr.io/shubkb07/wp-local:0.0.8-alpha
LOCAL_WP_DATA_PATH=./data
WP_SITES_PATH=./data/wp-sites
LOCAL_WP_ENV_FILE=./.env
MYSQL_USER=root
MYSQL_PASSWORD=local_root_password
PHP_MEMORY=512M
SITES=apple.local,meow.local
NO_DELETE_SITES=
```

`WP_SITES_PATH` stores each site's `wp-config.php` and `wp-content`. WordPress core is provided by the image. When a site's `wp-content` folder does not exist yet, wp-local creates `uploads`, `themes`, and `plugins`, then adds the bundled `twentytwentyfive` theme. Existing `wp-content` folders are left alone. MariaDB and Redis data are stored in Docker volumes managed by Compose.

`LOCAL_WP_DATA_PATH` stores generated local helper files:

- `data/local-wildcard.conf`
- `data/make-changes.md`

When `SITES` changes, restart the container to regenerate those files and clean removed-site `*_local` databases plus matching `data/wp-sites/{host}` folders.

Use `NO_DELETE_SITES=true` to disable removed-site cleanup entirely. Use a comma-separated list, such as `NO_DELETE_SITES=meow.local,apple.local`, to protect only those removed sites from database and folder deletion.

`PHP_MEMORY` controls PHP `memory_limit`; the image defaults to `512M`.

Adminer is available at `/adminer` on each configured host. It always opens the current host's database and fixes tampered `server`, `username`, and `db` query values.

WP-CLI is installed in the image:

```sh
docker compose exec web wp --info --allow-root
```

Clone a local site with `wpl`:

```sh
docker compose exec web wpl clone --from=neuro-ai.local --to=neuroai.local
docker compose exec web wpl clone --from=neuro-ai.local --to=neuroai.local --force
```

`wpl clone` copies the source database and `data/wp-sites/{host}` folder, appends the target host to `.env` `SITES`, refreshes generated local helper files, and runs WP-CLI search-replace when the cloned site is already installed.

## Local Image Build

Only use this when developing the image locally:

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

Useful commands:

```sh
docker compose logs -f web
docker compose down --remove-orphans
```
