<?php

declare(strict_types=1);

namespace BuiltNorth\CLI\Tests;

use BuiltNorth\CLI\BaseCommand;
use BuiltNorth\CLI\Commands\ConfigureCommand;
use BuiltNorth\CLI\Commands\GenerateCommand;
use BuiltNorth\CLI\Commands\PostTypeSwitchCommand;
use BuiltNorth\CLI\Commands\SetupBnProjectCommand;
use BuiltNorth\CLI\Commands\SetupCommand;
use BuiltNorth\CLI\Tests\Support\CliTestCase;
use BuiltNorth\CLI\Tests\Support\TempDirectory;
use WP_CLI\ExitException;

final class BaseCommandTest extends CliTestCase
{
	private TempDirectory $tmp;

	private string $original_cwd;

	public function setUp(): void
	{
		parent::setUp();
		$this->tmp          = new TempDirectory();
		$this->original_cwd = (string) getcwd();
	}

	public function tearDown(): void
	{
		chdir($this->original_cwd);
		$this->tmp->remove();
		parent::tearDown();
	}

	// ---------------------------------------------------------------------
	// get_registration_args
	// ---------------------------------------------------------------------

	/**
	 * @return array<string, array{class-string<BaseCommand>, array<string, string>}>
	 */
	public static function registration_args(): array
	{
		return [
			'configure (needs WP)'         => [ConfigureCommand::class, ['shortdesc' => 'Configure WordPress for BuiltNorth projects']],
			'generate (needs WP)'          => [GenerateCommand::class, ['shortdesc' => 'Generate test content for development']],
			'post-type-switch (needs WP)'  => [PostTypeSwitchCommand::class, ['shortdesc' => 'Convert posts from one post type to another']],
			'setup (before WP load)'       => [SetupCommand::class, ['shortdesc' => 'Standard WordPress project setup', 'when' => 'before_wp_load']],
			'setup-bn-project (before WP)' => [SetupBnProjectCommand::class, ['shortdesc' => 'BuiltNorth project setup', 'when' => 'before_wp_load']],
		];
	}

	/**
	 * @dataProvider registration_args
	 *
	 * @param class-string<BaseCommand> $class
	 * @param array<string, string>     $expected
	 */
	public function test_registration_args_reflect_wp_requirement(string $class, array $expected): void
	{
		$this->assertSame($expected, (new $class())->get_registration_args());
	}

	// ---------------------------------------------------------------------
	// find_file
	// ---------------------------------------------------------------------

	public function test_find_file_prefers_current_directory(): void
	{
		mkdir($this->tmp->path('project'));
		file_put_contents($this->tmp->path('project/.env.example'), 'cwd');
		file_put_contents($this->tmp->path('.env.example'), 'parent');
		chdir($this->tmp->path('project'));

		$this->assertSame($this->tmp->path('project/.env.example'), $this->invoke($this->command(), 'find_file', ['.env.example']));
	}

	public function test_find_file_falls_back_to_parent_directory(): void
	{
		mkdir($this->tmp->path('project'));
		file_put_contents($this->tmp->path('.lando.example.yml'), 'parent');
		chdir($this->tmp->path('project'));

		$this->assertSame($this->tmp->path('.lando.example.yml'), $this->invoke($this->command(), 'find_file', ['.lando.example.yml']));
	}

	public function test_find_file_returns_false_when_missing_everywhere(): void
	{
		mkdir($this->tmp->path('project'));
		chdir($this->tmp->path('project'));

		$this->assertFalse($this->invoke($this->command(), 'find_file', ['does-not-exist.txt']));
	}

	// ---------------------------------------------------------------------
	// copy_template
	// ---------------------------------------------------------------------

	public function test_copy_template_applies_every_replacement(): void
	{
		chdir($this->tmp->path());
		file_put_contents('.env.example', "DB=project-name\nURL=https://project-name.lndo.site\nKEEP=1\n");

		$this->invoke($this->command(), 'copy_template', ['.env.example', '.env', ['project-name' => 'acme', 'KEEP' => 'KEPT']]);

		$this->assertSame("DB=acme\nURL=https://acme.lndo.site\nKEPT=1\n", file_get_contents($this->tmp->path('.env')));
		$this->assertStringContainsString('Success: Created .env', $this->logger->stdout);
	}

	public function test_copy_template_without_replacements_copies_verbatim(): void
	{
		chdir($this->tmp->path());
		file_put_contents('tpl.txt', 'unchanged project-name');

		$this->invoke($this->command(), 'copy_template', ['tpl.txt', 'out.txt']);

		$this->assertSame('unchanged project-name', file_get_contents($this->tmp->path('out.txt')));
	}

	public function test_copy_template_errors_when_template_missing(): void
	{
		chdir($this->tmp->path());

		$this->expectException(ExitException::class);
		try {
			$this->invoke($this->command(), 'copy_template', ['missing.example', 'out.txt']);
		} finally {
			$this->assertStringContainsString('Error: Template file not found: missing.example', $this->logger->stderr);
			$this->assertFileDoesNotExist($this->tmp->path('out.txt'));
		}
	}

	public function test_with_path_hands_the_callers_path_to_the_shell(): void
	{
		$previous = getenv('PATH');
		putenv('PATH=/opt/example/bin:/usr/bin:/bin');

		try {
			$command = $this->invoke($this->command(), 'with_path', ['echo "$PATH"']);
		} finally {
			putenv('PATH=' . $previous);
		}

		// Run it the way WP_CLI::launch does when $_ENV is empty: no PATH set.
		$output = shell_exec('env -i /bin/sh -c ' . escapeshellarg($command));

		$this->assertSame('/opt/example/bin:/usr/bin:/bin', trim((string) $output));
	}

	public function test_with_path_hands_the_callers_home_to_the_shell(): void
	{
		$previous = getenv('HOME');
		putenv('HOME=/Users/example');

		try {
			$command = $this->invoke($this->command(), 'with_path', ['echo "$HOME"']);
		} finally {
			putenv('HOME=' . $previous);
		}

		// No HOME in the child (as WP_CLI::launch runs it) would make tools such as gh
		// write their state into the project folder.
		$output = shell_exec('env -i /bin/sh -c ' . escapeshellarg($command));

		$this->assertSame('/Users/example', trim((string) $output));
	}

	private function command(): BaseCommand
	{
		return new class () extends BaseCommand {
			protected function get_shortdesc()
			{
				return 'test';
			}
		};
	}
}
