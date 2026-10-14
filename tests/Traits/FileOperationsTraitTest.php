<?php

declare(strict_types=1);

namespace BuiltNorth\CLI\Tests\Traits;

use BuiltNorth\CLI\Tests\Support\CliTestCase;
use BuiltNorth\CLI\Tests\Support\TempDirectory;
use BuiltNorth\CLI\Traits\FileOperationsTrait;
use WP_CLI\ExitException;

final class FileOperationsTraitTest extends CliTestCase
{
	private TempDirectory $tmp;

	private object $subject;

	public function setUp(): void
	{
		parent::setUp();

		$this->tmp     = new TempDirectory();
		$this->subject = new class () {
			use FileOperationsTrait {
				ensure_directory as public;
				safe_file_write as public;
				read_json_file as public;
				write_json_file as public;
			}
		};
	}

	public function tearDown(): void
	{
		$this->tmp->remove();
		parent::tearDown();
	}

	// ---------------------------------------------------------------------
	// ensure_directory
	// ---------------------------------------------------------------------

	public function test_ensure_directory_creates_nested_path(): void
	{
		$path = $this->tmp->path('a/b/c');

		$this->assertTrue($this->subject->ensure_directory($path));
		$this->assertDirectoryExists($path);
		$this->assertSame(0755 & ~umask(), fileperms($path) & 0777);
	}

	public function test_ensure_directory_accepts_existing_directory(): void
	{
		$this->assertTrue($this->subject->ensure_directory($this->tmp->path()));
	}

	public function test_ensure_directory_errors_when_path_is_blocked_by_file(): void
	{
		$blocker = $this->tmp->path('blocker');
		file_put_contents($blocker, 'x');

		$this->expectException(ExitException::class);
		try {
			$this->without_php_warnings(fn () => $this->subject->ensure_directory($blocker . '/child'));
		} finally {
			$this->assertStringContainsString('Error: Failed to create directory: ' . $blocker . '/child', $this->logger->stderr);
		}
	}

	// ---------------------------------------------------------------------
	// safe_file_write
	// ---------------------------------------------------------------------

	public function test_safe_file_write_creates_new_file_without_backup(): void
	{
		$file = $this->tmp->path('new.txt');

		$this->assertTrue($this->subject->safe_file_write($file, 'hello'));
		$this->assertSame('hello', file_get_contents($file));
		$this->assertSame([], glob($file . '.backup-*'));
	}

	public function test_safe_file_write_backs_up_existing_file_before_overwrite(): void
	{
		$file = $this->tmp->path('config.php');
		file_put_contents($file, 'original');

		$before = gmdate('Y-m-d-H-i-s');
		$this->assertTrue($this->subject->safe_file_write($file, 'updated'));
		$after = gmdate('Y-m-d-H-i-s');

		$this->assertSame('updated', file_get_contents($file));

		$backups = glob($file . '.backup-*');
		$this->assertCount(1, $backups);
		$this->assertSame('original', file_get_contents($backups[0]));

		$stamp = substr($backups[0], strlen($file . '.backup-'));
		$this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}-\d{2}-\d{2}-\d{2}$/', $stamp);
		$this->assertContains($stamp, array_unique([$before, $after]), 'Backup suffix should be the UTC write time.');

		$this->assertStringContainsString('Created backup: ' . $backups[0], $this->logger->stderr);
	}

	public function test_safe_file_write_skips_backup_when_disabled(): void
	{
		$file = $this->tmp->path('config.php');
		file_put_contents($file, 'original');

		$this->assertTrue($this->subject->safe_file_write($file, 'updated', false));
		$this->assertSame('updated', file_get_contents($file));
		$this->assertSame([], glob($file . '.backup-*'));
	}

	public function test_safe_file_write_writes_empty_content(): void
	{
		$file = $this->tmp->path('empty.txt');

		$this->assertTrue($this->subject->safe_file_write($file, ''));
		$this->assertFileExists($file);
		$this->assertSame('', file_get_contents($file));
	}

	public function test_safe_file_write_errors_when_target_cannot_be_written(): void
	{
		$file = $this->tmp->path('missing-dir/file.txt');

		$this->expectException(ExitException::class);
		try {
			$this->without_php_warnings(fn () => $this->subject->safe_file_write($file, 'x'));
		} finally {
			$this->assertStringContainsString('Error: Failed to write file: ' . $file, $this->logger->stderr);
		}
	}

	// ---------------------------------------------------------------------
	// read_json_file
	// ---------------------------------------------------------------------

	public function test_read_json_file_returns_null_for_missing_file(): void
	{
		$this->assertNull($this->subject->read_json_file($this->tmp->path('nope.json')));
		$this->assertSame('', $this->logger->stderr);
	}

	public function test_read_json_file_decodes_to_associative_array(): void
	{
		$file = $this->tmp->path('data.json');
		file_put_contents($file, '{"name":"Ünïcødé","nested":{"list":[1,2]}}');

		$this->assertSame(
			['name' => 'Ünïcødé', 'nested' => ['list' => [1, 2]]],
			$this->subject->read_json_file($file)
		);
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function malformed_json(): array
	{
		return [
			'empty file'     => [''],
			'trailing comma' => ['{"a":1,}'],
			'not json'       => ['hello'],
		];
	}

	/**
	 * @dataProvider malformed_json
	 */
	public function test_read_json_file_errors_on_malformed_json(string $raw): void
	{
		$file = $this->tmp->path('bad.json');
		file_put_contents($file, $raw);

		$this->expectException(ExitException::class);
		try {
			$this->subject->read_json_file($file);
		} finally {
			$this->assertStringContainsString('Error: Invalid JSON in file: ' . $file, $this->logger->stderr);
		}
	}

	// ---------------------------------------------------------------------
	// write_json_file
	// ---------------------------------------------------------------------

	public function test_write_json_file_pretty_prints_with_unescaped_slashes(): void
	{
		$file = $this->tmp->path('out.json');

		$this->assertTrue($this->subject->write_json_file($file, ['path' => 'a/b', 'n' => 1]));
		$this->assertSame("{\n    \"path\": \"a/b\",\n    \"n\": 1\n}", file_get_contents($file));
	}

	public function test_write_json_file_compact_escapes_slashes(): void
	{
		$file = $this->tmp->path('out.json');

		$this->assertTrue($this->subject->write_json_file($file, ['path' => 'a/b'], false));
		$this->assertSame('{"path":"a\/b"}', file_get_contents($file));
	}

	public function test_write_json_file_round_trips_through_read(): void
	{
		$file = $this->tmp->path('round.json');
		$data = ['workspaces' => ['wp-content/themes/x'], 'empty' => [], 'flag' => false];

		$this->subject->write_json_file($file, $data);

		$this->assertSame($data, $this->subject->read_json_file($file));
	}

	public function test_write_json_file_backs_up_existing_file(): void
	{
		$file = $this->tmp->path('out.json');
		file_put_contents($file, '{"old":true}');

		$this->subject->write_json_file($file, ['new' => true]);

		$backups = glob($file . '.backup-*');
		$this->assertCount(1, $backups);
		$this->assertSame('{"old":true}', file_get_contents($backups[0]));
	}

	public function test_write_json_file_errors_on_unencodable_data(): void
	{
		$file = $this->tmp->path('out.json');

		$this->expectException(ExitException::class);
		try {
			$this->subject->write_json_file($file, ['bad' => "\xB1\x31"]);
		} finally {
			$this->assertStringContainsString('Error: Failed to encode JSON data', $this->logger->stderr);
			$this->assertFileDoesNotExist($file);
		}
	}

	/**
	 * Run a callable with PHP warnings swallowed, for paths where the source lets a
	 * native warning (mkdir, file_put_contents) fire before it reports via WP_CLI.
	 */
	private function without_php_warnings(callable $callback): mixed
	{
		set_error_handler(static fn (): bool => true, E_WARNING);
		try {
			return $callback();
		} finally {
			restore_error_handler();
		}
	}
}
