FROM wordpress:php8.3-apache

ARG VERSION=0.0.1-alpha

LABEL org.opencontainers.image.title="wp-local" \
      org.opencontainers.image.description="Single-container local WordPress stack with Apache/PHP, Adminer, MariaDB, Redis, and host-based site routing." \
      org.opencontainers.image.source="https://github.com/shubkb07/wp-local" \
      org.opencontainers.image.version="${VERSION}"

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends mariadb-server redis-server; \
    rm -rf /var/lib/apt/lists/*; \
    a2enmod rewrite headers; \
    printf 'ServerName localhost\n' > /etc/apache2/conf-enabled/server-name.conf; \
    mkdir -p /data/wp-sites /run/mysqld; \
    chown -R www-data:www-data /data/wp-sites; \
    chown -R mysql:mysql /run/mysqld /var/lib/mysql

ADD https://github.com/vrana/adminer/releases/download/v5.4.2/adminer-5.4.2.php /usr/src/wordpress/adminer/adminer.php
COPY data/wp-comman/adminer/ /usr/src/wordpress/adminer/
COPY data/wp-comman/wp-config.php /usr/src/wordpress/wp-config.php
COPY data/wp-comman/wp-resolve/ /usr/src/wordpress/wp-resolve/
COPY images/web/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
COPY images/web/entrypoint.sh /usr/local/bin/local-wp-web-entrypoint

RUN set -eux; \
    chmod +x /usr/local/bin/local-wp-web-entrypoint; \
    chown -R www-data:www-data /usr/src/wordpress/wp-config.php /usr/src/wordpress/wp-resolve /usr/src/wordpress/adminer /data/wp-sites; \
    chown -R mysql:mysql /run/mysqld /var/lib/mysql

ENTRYPOINT ["local-wp-web-entrypoint"]
CMD ["apache2-foreground"]
