#!/bin/sh
set -eu

allow_file="/etc/apache2/conf-enabled/local-sites-allow.conf"
mysql_password="${MYSQL_PASSWORD:-local_root_password}"
adminer_server="127.0.0.1"
adminer_user="${MYSQL_USER:-root}"
mkdir -p "$(dirname "$allow_file")" /data/wp-sites /run/mysqld /var/lib/mysql
: > "$allow_file"

if [ -n "${SITES:-}" ]; then
	printf '%s\n' "$SITES" | tr ',' '\n' | while IFS= read -r site; do
		site=$(printf '%s' "$site" | tr -d '[:space:]')
		[ -n "$site" ] || continue

		case "$site" in
			*[!A-Za-z0-9.-]*)
				continue
				;;
		esac

		escaped_site=$(printf '%s' "$site" | sed 's/\./\\./g')
		db_name=$(printf '%s' "$site" | sed 's/-/__/g; s/\./_/g')
		printf 'SetEnvIfNoCase Host "^%s(:[0-9]+)?$" ALLOWED_SITE=1 ADMINER_SERVER=%s ADMINER_USER=%s ADMINER_DB=%s\n' "$escaped_site" "$adminer_server" "$adminer_user" "$db_name" >> "$allow_file"
	done
fi

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

redis-server --daemonize yes

mkdir -p /var/www/html
cp -a /usr/src/wordpress/wp-config.php /var/www/html/wp-config.php
rm -rf /var/www/html/wp-resolve /var/www/html/adminer
cp -a /usr/src/wordpress/wp-resolve /var/www/html/wp-resolve
cp -a /usr/src/wordpress/adminer /var/www/html/adminer
chown -R www-data:www-data /var/www/html/wp-config.php /var/www/html/wp-resolve /var/www/html/adminer

exec docker-entrypoint.sh "$@"
