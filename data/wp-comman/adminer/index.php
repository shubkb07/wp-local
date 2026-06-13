<?php

namespace LocalWp\Adminer {
	function adminer_object() {
		$plugins = array();

		foreach (glob(__DIR__ . '/plugins-enabled/*.php') as $plugin) {
			$plugins[] = require $plugin;
		}

		return new \Adminer\Plugins($plugins);
	}
}

namespace {
	function adminer_object() {
		return \LocalWp\Adminer\adminer_object();
	}

	require __DIR__ . '/adminer.php';
}
