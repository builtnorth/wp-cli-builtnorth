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
			'skip_content'      => false,
			'skip_media'        => false,
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
			'skip_content'      => false,
			'skip_media'        => false,
		], $config);
	}

	public function test_privacy_policy_php_is_valid_php_that_publishes_the_page_once(): void
	{
		$php = SetupBnProjectCommand::privacy_policy_php(3);

		$file = tempnam(sys_get_temp_dir(), 'privacy');
		file_put_contents($file, "<?php\n" . $php);
		exec('php -l ' . escapeshellarg($file) . ' 2>&1', $output, $code);
		unlink($file);

		$this->assertSame(0, $code, implode("\n", $output));
		$this->assertStringContainsString("get_post_field('post_content', 3)", $php);
		$this->assertStringContainsString("strpos(\$content, 'wp:template-part') === false", $php);
		$this->assertStringContainsString('{"slug":"hero-compliance"}', $php);
		$this->assertStringContainsString("'post_status' => 'publish'", $php);
	}

	public function test_collect_setup_info_reads_the_skip_import_flags(): void
	{
		$config = $this->invoke($this->command, 'collect_setup_info', [[
			'name'           => 'Acme',
			'skip-wordpress' => true,
			'skip-content'   => true,
			'skip-media'     => true,
		]]);

		$this->assertTrue($config['skip_content']);
		$this->assertTrue($config['skip_media']);
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
		$this->assertSame([
			'name'         => 'custom/my-cool-theme',
			'type'         => 'wordpress-theme',
			'repositories' => [['type' => 'composer', 'url' => 'https://raw.githubusercontent.com/builtnorth/registry/main']],
		], $composer);

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

	// ---------------------------------------------------------------------
	// get_project_name / create_project_theme
	// ---------------------------------------------------------------------

	public function test_get_project_name_reads_lando_name(): void
	{
		file_put_contents($this->tmp->path('.lando.yml'), "name: client-site\nrecipe: wordpress\n");

		$this->assertSame('client-site', $this->in_tmp(fn () => $this->invoke($this->command, 'get_project_name')));
	}

	public function test_get_project_name_ignores_a_trailing_comment(): void
	{
		file_put_contents($this->tmp->path('.lando.yml'), "name: client-site # Name must be unique for each app!\nrecipe: wordpress\n");

		$this->assertSame('client-site', $this->in_tmp(fn () => $this->invoke($this->command, 'get_project_name')));
	}

	public function test_get_project_name_defaults_without_lando_file(): void
	{
		$this->assertSame('custom-theme', $this->in_tmp(fn () => $this->invoke($this->command, 'get_project_name')));
	}

	public function test_create_project_theme_never_overwrites_an_existing_theme(): void
	{
		file_put_contents($this->tmp->path('.lando.yml'), "name: client-site\n");
		mkdir($this->tmp->path('wp-content/themes/client-site'), 0755, true);
		file_put_contents($this->tmp->path('wp-content/themes/client-site/style.css'), 'keep');

		$this->expectException(\WP_CLI\ExitException::class);

		try {
			$this->in_tmp(fn () => $this->invoke($this->command, 'create_project_theme'));
		} finally {
			$this->assertSame('keep', file_get_contents($this->tmp->path('wp-content/themes/client-site/style.css')));
		}
	}

	public function test_generate_auth_file_keeps_an_existing_file(): void
	{
		file_put_contents($this->tmp->path('auth.json'), '{"keep":true}');

		$this->in_tmp(fn () => $this->invoke($this->command, 'generate_auth_file'));

		$this->assertSame('{"keep":true}', file_get_contents($this->tmp->path('auth.json')));
	}

	public function test_generate_salts_replaces_placeholders_only(): void
	{
		$env = $this->tmp->path('.env');
		file_put_contents($env, "WP_ENV=development\nAUTH_KEY=generate\nNONCE_SALT=generate\nSAMPLE_API='abc'\nSIRATIK_SECRET_CIPHER_KEY=generate\n");

		$this->invoke($this->command, 'generate_salts', [$env]);

		$lines = file($env, FILE_IGNORE_NEW_LINES);
		$this->assertSame('WP_ENV=development', $lines[0]);
		$this->assertMatchesRegularExpression('/^AUTH_KEY=[0-9a-f]{64}$/', $lines[1]);
		$this->assertMatchesRegularExpression('/^NONCE_SALT=[0-9a-f]{64}$/', $lines[2]);
		$this->assertNotSame($lines[1], 'AUTH_KEY=' . substr($lines[2], 11));
		$this->assertSame("SAMPLE_API='abc'", $lines[3]);
		$this->assertMatchesRegularExpression('/^SIRATIK_SECRET_CIPHER_KEY=[0-9a-f]{64}$/', $lines[4]);
	}

	public function test_project_relative_path_for_lando_commands(): void
	{
		$relative = $this->in_tmp(fn () => $this->invoke($this->command, 'project_relative_path', [$this->tmp->path('setup/data/content/content.xml')]));

		$this->assertSame('setup/data/content/content.xml', $relative);
		$this->assertSame('/elsewhere/file.xml', $this->in_tmp(fn () => $this->invoke($this->command, 'project_relative_path', ['/elsewhere/file.xml'])));
	}

	public function test_validate_theme_package_accepts_composer_names(): void
	{
		$this->assertSame('builtnorth/kitaris-theme', $this->invoke($this->command, 'validate_theme_package', [' BuiltNorth/Kitaris-Theme ']));
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function bad_theme_packages(): array
	{
		return [
			'no vendor'      => ['kitaris-theme'],
			'git url'        => ['git@github.com:builtnorth/kitaris-theme.git'],
			'shell chars'    => ['builtnorth/theme; rm -rf /'],
			'with version'   => ['builtnorth/kitaris-theme:^1.0'],
			'path traversal' => ['../theme'],
		];
	}

	/**
	 * @dataProvider bad_theme_packages
	 */
	public function test_validate_theme_package_rejects_anything_else(string $package): void
	{
		$this->expectException(\WP_CLI\ExitException::class);
		$this->invoke($this->command, 'validate_theme_package', [$package]);
	}

	public function test_theme_slug_from_show_path(): void
	{
		$this->assertSame('compass', $this->invoke($this->command, 'theme_slug_from_show_path', ["builtnorth/compass /app/wp-content/themes/compass\n"]));
		$this->assertNull($this->invoke($this->command, 'theme_slug_from_show_path', ["kitaris/seo /app/wp-content/plugins/kitaris-seo\n"]));
	}

	/**
	 * Run a callback with the temp dir as the working directory.
	 *
	 * @template T
	 * @param callable(): T $callback
	 * @return T
	 */
	private function in_tmp(callable $callback): mixed
	{
		$previous = getcwd();
		chdir($this->tmp->path());

		try {
			return $callback();
		} finally {
			chdir((string) $previous);
		}
	}
}
