#!/bin/sh
set -eu

allow_file="/etc/apache2/conf-enabled/local-sites-allow.conf"
mysql_password="${MYSQL_PASSWORD:-local_root_password}"
php_memory="${PHP_MEMORY:-512M}"
adminer_server="127.0.0.1"
adminer_user="${MYSQL_USER:-root}"
mkdir -p "$(dirname "$allow_file")" /data/wp-sites /run/mysqld /var/lib/mysql
: > "$allow_file"

case "$php_memory" in
	''|*[!0-9KkMmGg]*)
		php_memory='512M'
		;;
esac

cat > /usr/local/etc/php/conf.d/local-memory.ini <<INI
memory_limit=${php_memory}
upload_max_filesize=1024M
post_max_size=1024M
max_file_uploads=200
max_execution_time=600
max_input_time=600
max_input_vars=10000
default_socket_timeout=600
realpath_cache_size=4096K
realpath_cache_ttl=600
INI

mkdir -p /etc/mysql/mariadb.conf.d
cat > /etc/mysql/mariadb.conf.d/99-local-wp.cnf <<'CNF'
[mysqld]
max_allowed_packet=1G
net_read_timeout=600
net_write_timeout=600
wait_timeout=28800
interactive_timeout=28800
CNF

site_list() {
	if [ -n "${SITES:-}" ]; then
		printf '%s\n' "$SITES" | tr ',' '\n' | while IFS= read -r site; do
			site=$(printf '%s' "$site" | tr -d '[:space:]' | tr '[:upper:]' '[:lower:]')
			[ -n "$site" ] || continue

			case "$site" in
				*[!a-z0-9.-]*)
					continue
					;;
			esac

			printf '%s\n' "$site"
		done
	fi
}

site_hostnames() {
	site_list | paste -sd ' ' -
}

database_name_for_site() {
	printf '%s' "$1" | sed 's/-/__/g; s/\./_/g'
}

host_username() {
	username="${HOST_USERNAME:-}"

	if [ -z "$username" ] && [ -e /data/.env ]; then
		username=$(stat -c '%U' /data/.env 2>/dev/null || true)
	fi

	case "$username" in
		''|'UNKNOWN'|'root')
			username=$(whoami)
			;;
	esac

	printf '%s' "$username"
}

no_delete_all() {
	value=$(printf '%s' "${NO_DELETE_SITES:-}" | tr -d '[:space:]' | tr '[:upper:]' '[:lower:]')
	[ "$value" = "true" ] || [ "$value" = "1" ] || [ "$value" = "yes" ]
}

protected_site_list() {
	if [ -n "${NO_DELETE_SITES:-}" ] && ! no_delete_all; then
		printf '%s\n' "$NO_DELETE_SITES" | tr ',' '\n' | while IFS= read -r site; do
			site=$(printf '%s' "$site" | tr -d '[:space:]' | tr '[:upper:]' '[:lower:]')
			[ -n "$site" ] || continue

			case "$site" in
				*[!a-z0-9.-]*)
					continue
					;;
			esac

			printf '%s\n' "$site"
		done
	fi
}

contains_word() {
	case " $1 " in
		*" $2 "*)
			return 0
			;;
		*)
			return 1
			;;
	esac
}

generate_local_files() {
	hostnames=$(site_hostnames)
	username=$(host_username)
	nginx_include_path='$(pwd)/data/local-wildcard.conf'

	mkdir -p /data

	sed \
		-e "s/{{hostnames with space separated}}/${hostnames}/g" \
		-e "s/{{username}}/${username}/g" \
		/usr/src/local-wp/templates/local-wildcard.conf > /data/local-wildcard.conf

	sed \
		-e "s/{{hostnames with space separated}}/${hostnames}/g" \
		-e "s#{{nginx include path}}#${nginx_include_path}#g" \
		/usr/src/local-wp/templates/make-changes.md > /data/make-changes.md
}

sync_container_hosts() {
	hostnames=$(site_hostnames)
	[ -n "$hostnames" ] || return 0

	tmp_file=$(mktemp)
	sed '/# wp-local start/,/# wp-local end/d' /etc/hosts > "$tmp_file"
	{
		printf '\n# wp-local start\n'
		printf '127.0.0.1 %s\n' "$hostnames"
		printf '::1 %s\n' "$hostnames"
		printf '# wp-local end\n'
	} >> "$tmp_file"
	cat "$tmp_file" > /etc/hosts
	rm -f "$tmp_file"
}

generate_local_files
sync_container_hosts

site_list | while IFS= read -r site; do
		site=$(printf '%s' "$site" | tr -d '[:space:]')
		[ -n "$site" ] || continue

		case "$site" in
			*[!a-z0-9.-]*)
				continue
				;;
		esac

		escaped_site=$(printf '%s' "$site" | sed 's/\./\\./g')
		db_name=$(database_name_for_site "$site")
		printf 'SetEnvIfNoCase Host "^%s(:[0-9]+)?$" ALLOWED_SITE=1 ADMINER_SERVER=%s ADMINER_USER=%s ADMINER_DB=%s\n' "$escaped_site" "$adminer_server" "$adminer_user" "$db_name" >> "$allow_file"
	done

chown -R www-data:www-data /data/wp-sites
chown -R mysql:mysql /run/mysqld /var/lib/mysql

if [ ! -d /var/lib/mysql/mysql ]; then
	mariadb-install-db --user=mysql --datadir=/var/lib/mysql --skip-test-db >/dev/null
fi

mysqld_safe --datadir=/var/lib/mysql --socket=/run/mysqld/mysqld.sock --pid-file=/run/mysqld/mysqld.pid &
mysql_pid="$!"

for _ in $(seq 1 60); do
	if mysqladmin ping --socket=/run/mysqld/mysqld.sock --silent >/dev/null 2>&1; then
		break
	fi
	sleep 1
done

if mysql --socket=/run/mysqld/mysqld.sock -e "SELECT 1" >/dev/null 2>&1; then
	mysql_auth_args=""
else
	mysql_auth_args="-uroot -p${mysql_password}"
fi

mysql --socket=/run/mysqld/mysqld.sock $mysql_auth_args <<SQL
ALTER USER 'root'@'localhost' IDENTIFIED BY '${mysql_password}';
CREATE USER IF NOT EXISTS 'root'@'%' IDENTIFIED BY '${mysql_password}';
ALTER USER 'root'@'%' IDENTIFIED BY '${mysql_password}';
GRANT ALL PRIVILEGES ON *.* TO 'root'@'%' WITH GRANT OPTION;
FLUSH PRIVILEGES;
SQL

allowed_databases=" "
allowed_sites=" "
for site in $(site_list); do
	allowed_sites="${allowed_sites}${site} "
	allowed_databases="${allowed_databases}$(database_name_for_site "$site") "
done

protected_sites=" "
protected_databases=" "
for site in $(protected_site_list); do
	protected_sites="${protected_sites}${site} "
	protected_databases="${protected_databases}$(database_name_for_site "$site") "
done

if ! no_delete_all; then
	mysql --socket=/run/mysqld/mysqld.sock $mysql_auth_args -N -B -e "SHOW DATABASES LIKE '%local';" | while IFS= read -r database; do
		[ -n "$database" ] || continue

		case "$database" in
			*[!A-Za-z0-9_]*)
				continue
				;;
		esac

		if ! contains_word "$allowed_databases" "$database" && ! contains_word "$protected_databases" "$database"; then
			escaped_database=$(printf '%s' "$database" | sed 's/`/``/g')
			mysql --socket=/run/mysqld/mysqld.sock $mysql_auth_args -e "DROP DATABASE IF EXISTS \`${escaped_database}\`;"
		fi
	done

	find /data/wp-sites -mindepth 1 -maxdepth 1 -type d | while IFS= read -r site_path; do
		site=$(basename "$site_path" | tr '[:upper:]' '[:lower:]')

		case "$site" in
			*[!a-z0-9.-]*)
				continue
				;;
		esac

		if ! contains_word "$allowed_sites" "$site" && ! contains_word "$protected_sites" "$site"; then
			rm -rf "$site_path"
		fi
	done
fi

redis-server --daemonize yes
local-wp-cloudflared

mkdir -p /var/www/html
cp -a /usr/src/wordpress/wp-config.php /var/www/html/wp-config.php
rm -rf /var/www/html/wp-resolve /var/www/html/adminer
cp -a /usr/src/wordpress/wp-resolve /var/www/html/wp-resolve
cp -a /usr/src/wordpress/adminer /var/www/html/adminer
chown -R www-data:www-data /var/www/html/wp-config.php /var/www/html/wp-resolve /var/www/html/adminer

exec docker-entrypoint.sh "$@"
