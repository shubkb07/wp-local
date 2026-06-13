FROM wordpress:php8.3-apache

ARG VERSION=0.0.12-alpha

LABEL org.opencontainers.image.title="wp-local" \
      org.opencontainers.image.description="Single-container local WordPress stack with Apache/PHP, Adminer, MariaDB, Redis, and host-based site routing." \
      org.opencontainers.image.source="https://github.com/shubkb07/wp-local" \
      org.opencontainers.image.version="${VERSION}"

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends mariadb-server redis-server less default-mysql-client msmtp; \
    rm -rf /var/lib/apt/lists/*; \
    a2enmod rewrite headers proxy proxy_http; \
    printf 'ServerName localhost\n' > /etc/apache2/conf-enabled/server-name.conf; \
    mkdir -p /data/wp-sites /run/mysqld; \
    chown -R www-data:www-data /data/wp-sites; \
    chown -R mysql:mysql /run/mysqld /var/lib/mysql

ADD https://github.com/vrana/adminer/releases/download/v5.4.2/adminer-5.4.2.php /usr/src/wordpress/adminer/adminer.php
ADD https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar /usr/local/bin/wp
ADD https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-amd64 /usr/local/bin/cloudflared
ADD https://github.com/axllent/mailpit/releases/latest/download/mailpit-linux-amd64.tar.gz /tmp/mailpit-linux-amd64.tar.gz
COPY data/edits/local-wildcard.conf /usr/src/local-wp/templates/local-wildcard.conf
COPY data/edits/make-changes.md /usr/src/local-wp/templates/make-changes.md
COPY data/wp-comman/adminer/ /usr/src/wordpress/adminer/
COPY data/wp-comman/wp-config.php /usr/src/wordpress/wp-config.php
COPY data/wp-comman/wp-resolve/ /usr/src/wordpress/wp-resolve/
COPY data/edits/twentytwentyfive/ /usr/src/local-wp/themes/twentytwentyfive/
COPY images/web/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
COPY images/web/entrypoint.sh /usr/local/bin/local-wp-web-entrypoint
COPY images/web/cloudflared.sh /usr/local/bin/local-wp-cloudflared
COPY images/web/wpl /usr/local/bin/wpl

RUN set -eux; \
    tar -xzf /tmp/mailpit-linux-amd64.tar.gz -C /usr/local/bin mailpit; \
    rm -f /tmp/mailpit-linux-amd64.tar.gz; \
    chmod +x /usr/local/bin/local-wp-web-entrypoint /usr/local/bin/local-wp-cloudflared /usr/local/bin/wp /usr/local/bin/cloudflared /usr/local/bin/mailpit /usr/local/bin/wpl; \
    sed "s/{{hostnames with space separated}}/apple.local meow.local/g; s/{{username}}/host-user/g" /usr/src/local-wp/templates/local-wildcard.conf > /usr/src/local-wp/local-wildcard.conf; \
    sed 's/{{hostnames with space separated}}/apple.local meow.local/g; s#{{nginx include path}}#$(pwd)/data/local-wildcard.conf#g' /usr/src/local-wp/templates/make-changes.md > /usr/src/local-wp/make-changes.md; \
    chown -R www-data:www-data /usr/src/wordpress/wp-config.php /usr/src/wordpress/wp-resolve /usr/src/wordpress/adminer /usr/src/local-wp /data/wp-sites; \
    chown -R mysql:mysql /run/mysqld /var/lib/mysql

ENTRYPOINT ["local-wp-web-entrypoint"]
CMD ["apache2-foreground"]
