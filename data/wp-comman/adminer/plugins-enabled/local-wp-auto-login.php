<?php

class LocalWpAutoLogin extends Adminer\Plugin {
	private string $server;
	private string $username;
	private string $password;

	public function __construct() {
		$this->server = '127.0.0.1';
		$this->username = getenv('MYSQL_USER') ?: 'root';
		$this->password = getenv('MYSQL_PASSWORD') ?: 'local_root_password';

		$this->ensureDatabase();
		$this->redirectToCanonicalQuery();
		$this->seedSession();
	}

	public function credentials() {
		if (!$this->siteHost()) {
			return null;
		}

		return array($this->server, $this->username, $this->password);
	}

	public function database() {
		$host = $this->siteHost();

		return $host ? $this->databaseFromHost($host) : null;
	}

	public function login($login, $password) {
		if (!$this->siteHost()) {
			return null;
		}

		return $login === $this->username;
	}

	public function loginFormField($name, $heading, $value) {
		$host = $this->siteHost();

		if (!$host) {
			return null;
		}

		$dbName = $this->databaseFromHost($host);

		switch ($name) {
			case 'driver':
				return '<input type="hidden" name="auth[driver]" value="server">' . "\n";
			case 'server':
				return '<input type="hidden" name="auth[server]" value="' . $this->html($this->server) . '">' . "\n";
			case 'username':
				return '<input type="hidden" name="auth[username]" value="' . $this->html($this->username) . '">' . "\n";
			case 'password':
				return '<input type="hidden" name="auth[password]" value="local-wp-auto-login">' . "\n";
			case 'db':
				return '<input type="hidden" name="auth[db]" value="' . $this->html($dbName) . '">' . "\n"
					. '<p>Opening database <code>' . $this->html($dbName) . '</code>...</p>' . "\n"
					. '<script>document.currentScript.closest("form").submit();</script>' . "\n";
		}

		return null;
	}

	private function redirectToCanonicalQuery(): void {
		$host = $this->siteHost();

		if (!$host || !$this->shouldCanonicalize()) {
			return;
		}

		$dbName = $this->databaseFromHost($host);

		$query = $this->canonicalQuery($dbName);

		if ($_SERVER['QUERY_STRING'] === $query) {
			return;
		}

		header('Location: ?' . $query, true, 302);
		exit;
	}

	private function shouldCanonicalize(): bool {
		if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', array('GET', 'HEAD'), true)) {
			return false;
		}

		return !isset($_GET['file']);
	}

	private function canonicalQuery(string $dbName): string {
		$queryParams = array(
			'server' => $this->server,
			'username' => $this->username,
			'db' => $dbName,
		);

		foreach ($_GET as $key => $value) {
			if (in_array($key, array('server', 'username', 'db'), true)) {
				continue;
			}

			$queryParams[$key] = $value;
		}

		return http_build_query($queryParams);
	}

	private function ensureDatabase(): void {
		$host = $this->siteHost();

		if (!$host) {
			return;
		}

		$mysqli = mysqli_init();

		if (!$mysqli || !@$mysqli->real_connect($this->server, $this->username, $this->password, null, 3306)) {
			return;
		}

		$dbName = str_replace('`', '``', $this->databaseFromHost($host));
		$mysqli->query("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
		$mysqli->close();
	}

	private function seedSession(): void {
		$host = $this->siteHost();

		if (!$host || !$this->isAutoLoginUrl($host)) {
			return;
		}

		if (session_status() !== PHP_SESSION_ACTIVE) {
			session_start();
		}

		$dbName = $this->databaseFromHost($host);

		$_SESSION['pwds']['server'][$this->server][$this->username] = $this->password;
		$_SESSION['db']['server'][$this->server][$this->username][$dbName] = true;
	}

	private function isAutoLoginUrl(string $host): bool {
		return ($_GET['server'] ?? '') === $this->server
			&& ($_GET['username'] ?? '') === $this->username
			&& ($_GET['db'] ?? '') === $this->databaseFromHost($host);
	}

	private function siteHost(): ?string {
		$host = strtolower($_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? '');
		$host = preg_replace('/:\d+$/', '', $host);
		$host = trim($host);

		if ($host === '' || !preg_match('/^[a-z0-9.-]+$/', $host)) {
			return null;
		}

		$sites = getenv('SITES') ?: '';
		$sites = array_filter(
			array_map(
				static fn($site) => strtolower(trim((string) $site)),
				explode(',', $sites)
			)
		);

		if (!$sites) {
			return null;
		}

		return in_array($host, $sites, true) ? $host : null;
	}

	private function databaseFromHost(string $host): string {
		return strtr($host, array('.' => '_', '-' => '__'));
	}

	private function html(string $value): string {
		return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}
}

return new LocalWpAutoLogin();
