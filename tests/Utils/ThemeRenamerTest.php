<?php

declare(strict_types=1);

namespace BuiltNorth\CLI\Tests\Utils;

use BuiltNorth\CLI\Tests\Support\TempDirectory;
use BuiltNorth\CLI\Utils\ThemeRenamer;
use PHPUnit\Framework\TestCase;

final class ThemeRenamerTest extends TestCase
{
	private TempDirectory $tmp;

	public function setUp(): void
	{
		parent::setUp();
		$this->tmp = new TempDirectory();
	}

	public function tearDown(): void
	{
		$this->tmp->remove();
		parent::tearDown();
	}

	public function test_name_variants(): void
	{
		$renamer = new ThemeRenamer('client-site');

		$this->assertSame('ClientSite', $renamer->pascal());
		$this->assertSame('CLIENT_SITE', $renamer->upper());
		$this->assertSame('Client Site', $renamer->name());
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function reserved_slugs(): array
	{
		return [
			'framework name'   => ['kitaris', 'kitaris-theme'],
			'vendor name'      => ['siratik', 'siratik-theme'],
			'kitaris plugin'   => ['kitaris-store', 'kitaris-store-theme'],
			'siratik package'  => ['siratik-utility', 'siratik-utility-theme'],
			'wordpress'        => ['wp', 'wp-theme'],
			'ordinary name'    => ['client-site', 'client-site'],
			'contains kitaris' => ['my-kitaris', 'my-kitaris'],
		];
	}

	/**
	 * @dataProvider reserved_slugs
	 */
	public function test_identifier_slug_avoids_framework_names(string $slug, string $expected): void
	{
		$this->assertSame($expected, ThemeRenamer::identifier_slug($slug));
	}

	public function test_reserved_name_never_produces_the_framework_namespace(): void
	{
		$renamer = new ThemeRenamer('kitaris');

		$this->assertSame('KitarisTheme', $renamer->pascal());
		$this->assertSame('KITARIS_THEME', $renamer->upper());
		$this->assertSame("namespace KitarisTheme;\ndefine('KITARIS_THEME_URL', 1);", $renamer->rename_content("namespace Compass;\ndefine('COMPASS_URL', 1);"));
	}

	public function test_name_variants_never_start_with_a_digit(): void
	{
		$renamer = new ThemeRenamer('360-media');

		$this->assertSame('Theme360Media', $renamer->pascal());
		$this->assertSame('THEME_360_MEDIA', $renamer->upper());
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function renames(): array
	{
		return [
			'namespace'         => ['namespace Compass;', 'namespace ClientSite;'],
			'sub namespace'     => ['use Compass\\Configs\\Foo;', 'use ClientSite\\Configs\\Foo;'],
			'json namespace'    => ['"Compass\\\\": ["inc/classes/"]', '"ClientSite\\\\": ["inc/classes/"]'],
			'constant'          => ["define('COMPASS_PATH', 1);", "define('CLIENT_SITE_PATH', 1);"],
			'text domain'       => ["__('Dots', 'compass')", "__('Dots', 'client-site')"],
			'double quoted'     => ['"name": "compass"', '"name": "client-site"'],
			'package name'      => ['"name": "builtnorth/compass"', '"name": "builtnorth/client-site"'],
			'pattern slug'      => ['"slug": "compass/001-hero-standard"', '"slug": "client-site/001-hero-standard"'],
			'block comment'     => ['<!-- wp:pattern {"slug":"compass/search-bar"} /-->', '<!-- wp:pattern {"slug":"client-site/search-bar"} /-->'],
			'handle'            => ["'compass-editor'", "'client-site-editor'"],
			'docblock package'  => [' * @package Compass', ' * @package ClientSite'],
			'docblock since'    => [' * @since Compass 1.0.0', ' * @since ClientSite 1.0.0'],
			'prose'             => [' * Main Application Class for Compass theme.', ' * Main Application Class for Client Site theme.'],
			'heading'           => ['# Compass Directory Theme', '# Client Site Directory Theme'],
			'variable'          => ['$compass = App::get_instance();', '$theme_app = App::get_instance();'],
			'method call'       => ['$compass->boot();', '$theme_app->boot();'],
			'script name'       => ['node scripts/generate-compass-icons.mjs', 'node scripts/generate-client-site-icons.mjs'],
			'hook prefix'       => ["do_action('compass_before_init', \$this);", "do_action('client_site_before_init', \$this);"],
			'function prefix'   => ["__NAMESPACE__ . '\\compass_register_config'", "__NAMESPACE__ . '\\client_site_register_config'"],
			'text domain header' => ['Text Domain: compass', 'Text Domain: client-site'],
		];
	}

	/**
	 * @dataProvider renames
	 */
	public function test_rename_content(string $input, string $expected): void
	{
		$this->assertSame($expected, (new ThemeRenamer('client-site'))->rename_content($input));
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function kept(): array
	{
		return [
			'other theme pattern' => ['"patternName":"compass-waas/header-01"'],
			'other theme comment' => ['// the approach already used in compass-waas.'],
			'icon compass-rose'   => ['name: "compass-rose",'],
			'icon compass-tool'   => ['name: "compass-tool",'],
			'gitignore rule'      => ['!wp-content/plugins/compass*'],
			'other namespace'     => ["// 'CompassWaasTrades\\\\Configs\\\\cb'"],
		];
	}

	/**
	 * @dataProvider kept
	 */
	public function test_leaves_other_names_alone(string $input): void
	{
		$this->assertSame($input, (new ThemeRenamer('client-site'))->rename_content($input));
	}

	public function test_rename_skips_build_vendor_and_icons_and_renames_files(): void
	{
		$this->write('functions.php', "<?php\nnamespace Compass;\n");
		$this->write('style.css', "Theme Name: Compass\nText Domain: compass\n");
		$this->write('scripts/generate-compass-icons.mjs', "// Run: node scripts/generate-compass-icons.mjs\n");
		$this->write('theme-config/styles/blocks/compass.js', "export default { 'compass/single-details': {} };\n");
		$this->write('build/index.js', "'compass-editor'\n");
		$this->write('vendor/autoload.php', "<?php // Compass\\\n");
		$this->write('src/assets/icons/icon-library.js', "name: \"compass\",\n");
		$this->write('.gitignore', "!wp-content/plugins/compass*\n");

		$left = (new ThemeRenamer('client-site'))->rename($this->tmp->path());

		$this->assertSame("<?php\nnamespace ClientSite;\n", $this->read('functions.php'));
		$this->assertSame("Theme Name: Client Site\nText Domain: client-site\n", $this->read('style.css'));
		$this->assertFileExists($this->tmp->path('scripts/generate-client-site-icons.mjs'));
		$this->assertFileDoesNotExist($this->tmp->path('scripts/generate-compass-icons.mjs'));
		$this->assertSame(
			"// Run: node scripts/generate-client-site-icons.mjs\n",
			$this->read('scripts/generate-client-site-icons.mjs')
		);
		$this->assertSame(
			"export default { 'client-site/single-details': {} };\n",
			$this->read('theme-config/styles/blocks/client-site.js')
		);

		// Untouched.
		$this->assertSame("'compass-editor'\n", $this->read('build/index.js'));
		$this->assertSame("<?php // Compass\\\n", $this->read('vendor/autoload.php'));
		$this->assertSame("name: \"compass\",\n", $this->read('src/assets/icons/icon-library.js'));
		$this->assertSame("!wp-content/plugins/compass*\n", $this->read('.gitignore'));

		$this->assertSame([], $left);
	}

	public function test_rename_reports_leftovers(): void
	{
		$this->write('inc/notes.php', "<?php\n// Generated compass icon set\n");

		$left = (new ThemeRenamer('client-site'))->rename($this->tmp->path());

		$this->assertSame(['inc/notes.php:2: // Generated compass icon set'], $left);
	}

	private function write(string $relative, string $content): void
	{
		$path = $this->tmp->path($relative);
		if (! is_dir(dirname($path))) {
			mkdir(dirname($path), 0755, true);
		}
		file_put_contents($path, $content);
	}

	private function read(string $relative): string
	{
		return (string) file_get_contents($this->tmp->path($relative));
	}
}
