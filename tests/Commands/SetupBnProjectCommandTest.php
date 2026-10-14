<?php

declare(strict_types=1);

namespace BuiltNorth\CLI\Tests\Commands;

use BuiltNorth\CLI\Commands\SetupBnProjectCommand;
use BuiltNorth\CLI\Tests\Support\CliTestCase;
use BuiltNorth\CLI\Tests\Support\TempDirectory;

final class SetupBnProjectCommandTest extends CliTestCase
{
	private TempDirectory $tmp;

	private SetupBnProjectCommand $command;

	public function setUp(): void
	{
		parent::setUp();
		$this->tmp     = new TempDirectory();
		$this->command = new SetupBnProjectCommand();
	}

	public function tearDown(): void
	{
		$this->tmp->remove();
		parent::tearDown();
	}

	// ---------------------------------------------------------------------
	// sanitize_site_name
	// ---------------------------------------------------------------------

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function site_names(): array
	{
		return [
			'spaces to hyphens'    => ['My Project', 'my-project'],
			'already slug'         => ['acme', 'acme'],
			'mixed case'           => ['AcMe Site', 'acme-site'],
			'empty'                => ['', ''],
			'apostrophe'           => ["Bob's Shop", 'bob-s-shop'],
			'outer spaces'         => [' Acme ', 'acme'],
			'ampersand'            => ['Smith & Sons', 'smith-sons'],
			'non-ASCII letters'    => ['Café Ünïcødé', 'caf-n-c-d'],
			'existing hyphens kept' => ['Big-Box Store', 'big-box-store'],
		];
	}

	/**
	 * @dataProvider site_names
	 */
	public function test_sanitize_site_name(string $input, string $expected): void
	{
		$this->assertSame($expected, $this->invoke($this->command, 'sanitize_site_name', [$input]));
	}

	// ---------------------------------------------------------------------
	// collect_setup_info (all values passed as flags, so no prompts)
	// ---------------------------------------------------------------------

	public function test_collect_setup_info_from_flags(): void
	{
		$config = $this->invoke($this->command, 'collect_setup_info', [[
			'name'     => 'My Project',
			'username' => 'boss',
			'password' => 'secret',
			'email'    => 'boss@example.com',
		]]);

		$this->assertSame([
			'sitename_original' => 'My Project',
			'sitename'          => 'my-project',
			'username'          => 'boss',
			'password'          => 'secret',
			'email'             => 'boss@example.com',
			'url'               => 'https://my-project.lndo.site',
		], $config);
	}

	public function test_collect_setup_info_skip_wordpress_omits_credentials(): void
	{
		$config = $this->invoke($this->command, 'collect_setup_info', [[
			'name'           => 'Acme',
			'skip-wordpress' => true,
		]]);

		$this->assertSame([
			'sitename_original' => 'Acme',
			'sitename'          => 'acme',
			'url'               => 'https://acme.lndo.site',
		], $config);
	}

	// ---------------------------------------------------------------------
	// update_theme_metadata
	// ---------------------------------------------------------------------

	public function test_update_theme_metadata_rewrites_style_package_and_composer(): void
	{
		$theme = $this->tmp->path('my-theme');
		mkdir($theme);
		file_put_contents($theme . '/style.css', "/*\nTheme Name: Compass\nAuthor: BuiltNorth\nText Domain: compass\nVersion: 1.0\n*/\n");
		file_put_contents($theme . '/package.json', json_encode([
			'name'       => 'compass',
			'version'    => '1.0.0',
			'repository' => ['type' => 'git', 'url' => 'https://example.test/compass.git'],
		]));
		file_put_contents($theme . '/composer.json', json_encode([
			'name'         => 'builtnorth/compass',
			'type'         => 'wordpress-theme',
			'repositories' => [['type' => 'vcs', 'url' => 'https://example.test']],
			'support'      => ['issues' => 'https://example.test/issues'],
			'homepage'     => 'https://example.test',
		]));

		$this->capture_output(fn () => $this->invoke($this->command, 'update_theme_metadata', [$theme, 'my-cool-theme']));

		$this->assertSame(
			"/*\nTheme Name: My Cool Theme\nAuthor: BuiltNorth\nText Domain: my-cool-theme\nVersion: 1.0\n*/\n",
			file_get_contents($theme . '/style.css')
		);

		$package = json_decode((string) file_get_contents($theme . '/package.json'), true);
		$this->assertSame(['name' => 'my-cool-theme', 'version' => '1.0.0'], $package);
		$this->assertStringEndsWith("}\n", (string) file_get_contents($theme . '/package.json'));

		$composer = json_decode((string) file_get_contents($theme . '/composer.json'), true);
		$this->assertSame(['name' => 'custom/my-cool-theme', 'type' => 'wordpress-theme'], $composer);

		$this->assertStringContainsString('Success: Theme metadata updated', $this->logger->stdout);
	}

	public function test_update_theme_metadata_tolerates_missing_files(): void
	{
		$theme = $this->tmp->path('bare-theme');
		mkdir($theme);

		$this->capture_output(fn () => $this->invoke($this->command, 'update_theme_metadata', [$theme, 'bare']));

		$this->assertSame(['.', '..'], scandir($theme));
		$this->assertStringContainsString('Success: Theme metadata updated', $this->logger->stdout);
	}

	public function test_update_theme_metadata_leaves_unparseable_json_untouched(): void
	{
		$theme = $this->tmp->path('broken-theme');
		mkdir($theme);
		file_put_contents($theme . '/package.json', '{not json');
		file_put_contents($theme . '/composer.json', '');

		$this->capture_output(fn () => $this->invoke($this->command, 'update_theme_metadata', [$theme, 'broken']));

		$this->assertSame('{not json', file_get_contents($theme . '/package.json'));
		$this->assertSame('', file_get_contents($theme . '/composer.json'));
	}
}
