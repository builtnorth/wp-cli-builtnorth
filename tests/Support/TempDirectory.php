<?php

declare(strict_types=1);

namespace BuiltNorth\CLI\Tests\Support;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/**
 * Throwaway directory under the system temp dir, removed by remove().
 */
final class TempDirectory
{
	private string $root;

	public function __construct()
	{
		$root = sys_get_temp_dir() . '/builtnorth-cli-test-' . bin2hex(random_bytes(6));
		if (! mkdir($root, 0755, true)) {
			throw new RuntimeException("Could not create temp dir $root");
		}
		$this->root = realpath($root) ?: $root;
	}

	public function path(string $relative = ''): string
	{
		return '' === $relative ? $this->root : $this->root . '/' . $relative;
	}

	public function remove(): void
	{
		if (! is_dir($this->root)) {
			return;
		}

		$items = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ($items as $item) {
			$item->isDir() && ! $item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
		}
		rmdir($this->root);
	}
}
