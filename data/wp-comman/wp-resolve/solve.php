<?php
/**
 * Resolve Pre WP Request
 *
 * @package resolve
 */

$host = strtolower($_SERVER['HTTP_HOST'] ?? '');
$host = preg_replace('/:\d+$/', '', $host);
$host = trim($host);

if ($host === '' || !preg_match('/^[a-z0-9.-]+$/', $host)) {
    http_response_code(400);
    exit('Invalid host.');
}

$sites = getenv('SITES') ?: '';
$allowed_sites = array_filter(
	array_map(
		static function ($site) {
			return strtolower(trim((string) $site));
		},
		explode(',', $sites)
	)
);

if (!$allowed_sites) {
    http_response_code(500);
    exit('No sites are configured.');
}

if (!in_array($host, $allowed_sites, true)) {
    http_response_code(404);
    exit('Site is not configured.');
}

$is_https = (
    (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
);

if ($is_https) {
    $_SERVER['HTTPS'] = 'on';
    $_SERVER['SERVER_PORT'] = '443';
}

$scheme = $is_https ? 'https' : 'http';
$db_name = strtr($host, array('.' => '_', '-' => '__'));
$db_user = getenv('MYSQL_USER') ?: 'root';
$db_password = getenv('MYSQL_PASSWORD') ?: 'local_root_password';
$db_host = '127.0.0.1:3306';
$db_root_user = getenv('WORDPRESS_DB_ROOT_USER') ?: $db_user;
$db_root_password = getenv('WORDPRESS_DB_ROOT_PASSWORD') ?: $db_password;
$db_server_host = $db_host;
$db_server_port = 3306;

if (str_contains($db_server_host, ':')) {
    $db_host_parts = explode(':', $db_server_host, 2);
    $db_server_host = $db_host_parts[0];
    $db_server_port = (int) $db_host_parts[1];
}

if ($db_server_host === '') {
    $db_server_host = 'mysql';
}

if ($db_server_port < 1 || $db_server_port > 65535) {
    $db_server_port = 3306;
}

$mysqli = mysqli_init();

if (!$mysqli || !@$mysqli->real_connect($db_server_host, $db_root_user, $db_root_password, null, $db_server_port)) {
    http_response_code(500);
    exit('Unable to connect to database server.');
}

$escaped_db_name = str_replace('`', '``', $db_name);
$escaped_db_user = $mysqli->real_escape_string($db_user);
$escaped_db_password = $mysqli->real_escape_string($db_password);

$mysqli->query("CREATE DATABASE IF NOT EXISTS `{$escaped_db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

if ($db_user !== $db_root_user) {
    $mysqli->query("CREATE USER IF NOT EXISTS '{$escaped_db_user}'@'%' IDENTIFIED BY '{$escaped_db_password}'");
    $mysqli->query("ALTER USER '{$escaped_db_user}'@'%' IDENTIFIED BY '{$escaped_db_password}'");
    $mysqli->query("GRANT ALL PRIVILEGES ON `{$escaped_db_name}`.* TO '{$escaped_db_user}'@'%'");
}

$mysqli->close();

$site_dir = "/data/wp-sites/{$host}";
$wp_content_dir = "{$site_dir}/wp-content";

foreach (array($site_dir, $wp_content_dir, "{$wp_content_dir}/uploads", "{$wp_content_dir}/themes", "{$wp_content_dir}/plugins") as $directory) {
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        http_response_code(500);
        exit("Unable to create {$directory}.");
    }
}

$default_theme_source = '/usr/src/local-wp/themes/twentytwentyfive';
$default_theme_target = "{$wp_content_dir}/themes/twentytwentyfive";

if (is_dir($default_theme_source) && !is_dir($default_theme_target)) {
    copy_directory($default_theme_source, $default_theme_target);
}

$site_wp_config = "{$site_dir}/wp-config.php";

if (!is_file($site_wp_config)) {
    $config = <<<PHP
<?php
// Site-specific overrides loaded by /data/wp-comman/wp-resolve/solve.php.
// Define per-site constants here before WordPress finishes loading.

PHP;

    if (file_put_contents($site_wp_config, $config, LOCK_EX) === false) {
        http_response_code(500);
        exit('Unable to create site wp-config.php.');
    }
}

require_once $site_wp_config;

/** The name of the database for WordPress */
define('DB_NAME', $db_name);

/** Database username */
define('DB_USER', $db_user);

/** Database password */
define('DB_PASSWORD', $db_password);

/** Database hostname */
define('DB_HOST', $db_host);

/** Database charset to use in creating database tables. */
define('DB_CHARSET', 'utf8mb4');

/** The database collate type. Don't change this if in doubt. */
define('DB_COLLATE', '');

define('WP_CONTENT_DIR', $wp_content_dir);
define('WP_CONTENT_URL', "{$scheme}://{$host}/wp-content");
define('WP_SITEURL', "{$scheme}://{$host}");
define('WP_HOME', "{$scheme}://{$host}");
define('DISALLOW_FILE_MODS', true);
define('AUTOMATIC_UPDATER_DISABLED', true);
define('WP_AUTO_UPDATE_CORE', false);
define('WP_REDIS_HOST', '127.0.0.1');

function copy_directory(string $source, string $target): void {
    if (!is_dir($target) && !mkdir($target, 0775, true) && !is_dir($target)) {
        http_response_code(500);
        exit("Unable to create {$target}.");
    }

    $items = scandir($source);

    if ($items === false) {
        http_response_code(500);
        exit("Unable to read {$source}.");
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $source_path = "{$source}/{$item}";
        $target_path = "{$target}/{$item}";

        if (is_dir($source_path)) {
            copy_directory($source_path, $target_path);
            continue;
        }

        if (!is_file($target_path) && !copy($source_path, $target_path)) {
            http_response_code(500);
            exit("Unable to copy {$source_path}.");
        }
    }
}
