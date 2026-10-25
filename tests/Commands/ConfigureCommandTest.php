<?php

declare(strict_types=1);

namespace BuiltNorth\CLI\Tests\Commands;

use BuiltNorth\CLI\Commands\ConfigureCommand;
use BuiltNorth\CLI\Tests\Support\CliTestCase;

final class ConfigureCommandTest extends CliTestCase
{
	private const BASE_HEAD = [
		'Activating theme'      => 'activate_theme',
		'Installing plugins'    => 'install_plugins',
		'Activating plugins'    => 'activate_plugins',
		'Configuring settings'  => 'configure_settings',
		'Setting up permalinks' => 'setup_permalinks',
	];

	private const BASE_TAIL = [
		'Setting up pages'         => 'setup_pages',
		'Cleaning default content' => 'clean_default_content',
		'Applying starter options' => 'apply_starter_options',
		'Setting featured images'  => 'apply_featured_images',
		'Placing images in content' => 'apply_image_tokens',
	];

	public function test_default_steps_include_media_and_content_in_order(): void
	{
		$this->assertSame(
			self::BASE_HEAD + ['Importing media' => 'import_media', 'Importing content' => 'import_content'] + self::BASE_TAIL,
			$this->steps([])
		);
	}

	public function test_skip_media_and_skip_content_drop_their_steps(): void
	{
		$this->assertSame(self::BASE_HEAD + self::BASE_TAIL, $this->steps(['skip-media' => true, 'skip-content' => true]));
	}

	public function test_skip_media_only_keeps_content(): void
	{
		$steps = $this->steps(['skip-media' => true]);

		$this->assertArrayNotHasKey('Importing media', $steps);
		$this->assertSame('import_content', $steps['Importing content']);
	}

	public function test_site_url_appends_url_update_as_last_step(): void
	{
		$steps = $this->steps(['site-url' => 'https://example.test']);

		$this->assertSame('update_urls', end($steps));
		$this->assertSame('Updating URLs', array_key_last($steps));
	}

	public function test_empty_site_url_does_not_add_url_update(): void
	{
		$this->assertArrayNotHasKey('Updating URLs', $this->steps(['site-url' => '']));
	}

	public function test_every_step_maps_to_a_real_method(): void
	{
		$steps = $this->steps(['site-url' => 'https://example.test']);

		foreach ($steps as $label => $method) {
			$this->assertTrue(method_exists(ConfigureCommand::class, $method), "Step '$label' points at missing method $method");
		}
	}

	/**
	 * @param array<string, mixed> $assoc_args
	 * @return array<string, string>
	 */
	private function steps(array $assoc_args): array
	{
		return $this->invoke(new ConfigureCommand(), 'get_configuration_steps', [$assoc_args]);
	}
}
