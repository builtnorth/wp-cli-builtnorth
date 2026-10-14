<?php

declare(strict_types=1);

/**
 * PHPUnit bootstrap.
 *
 * The commands talk to WP-CLI through the real `WP_CLI` class (wp-cli/wp-cli is a
 * runtime dependency, so it is always installed). Outside a `wp` process its
 * `add_command()` bails early, so loading command.php is harmless; tests swap in
 * WP-CLI's own Execution logger to capture output (see Support\CliTestCase).
 * WordPress functions, where a test needs them, come from WP_Mock plus the shared
 * core-class stand-ins from builtnorth/wp-mock-stand-ins below.
 */

// Suppress PHP 8.4 deprecation warnings from WP_Mock (vendor) until upstream fixes signatures.
error_reporting(E_ALL & ~E_DEPRECATED);

// Falls back to the monorepo root's autoloader when this package has no
// standalone vendor/ install (the normal dev setup — see "Autoloader
// Architecture" in the root CLAUDE.md).
$autoload = __DIR__ . '/../vendor/autoload.php';
$using_root_autoloader = ! file_exists($autoload);
if ($using_root_autoloader) {
	$autoload = __DIR__ . '/../../../vendor/autoload.php';
}

if (! file_exists($autoload)) {
	fwrite(STDERR, "Run `composer install` before the test suite.\n");
	exit(1);
}

require $autoload;

// The root autoloader only carries this package's own runtime `autoload`
// PSR-4 mapping, never a dependency's `autoload-dev` — register the Tests
// namespace by hand when running under the root autoloader.
if ($using_root_autoloader) {
	spl_autoload_register(static function (string $class): void {
		$prefix = 'BuiltNorth\\CLI\\Tests\\';
		if (! str_starts_with($class, $prefix)) {
			return;
		}
		$relative = substr($class, strlen($prefix));
		$file     = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';
		if (file_exists($file)) {
			require $file;
		}
	});
}

// Shared WP core class stand-ins (WP_Error, WP_Post, etc.) — lives in whichever
// vendor/ $autoload above actually resolved to (local or root).
require_once dirname($autoload) . '/builtnorth/wp-mock-stand-ins/inc/stand-ins.php';

WP_Mock::bootstrap();
