<?php

declare(strict_types=1);

namespace BuiltNorth\CLI\Tests\Utils;

use BuiltNorth\CLI\Tests\Support\TempDirectory;
use BuiltNorth\CLI\Utils\StarterOptions;
use PHPUnit\Framework\TestCase;

final class StarterOptionsTest extends TestCase
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

	public function test_read_returns_the_options_object(): void
	{
		$path = $this->tmp->path('options.json');
		file_put_contents($path, '{"blogdescription": "", "show_on_front": "page"}');

		$this->assertSame(['blogdescription' => '', 'show_on_front' => 'page'], StarterOptions::read($path));
	}

	public function test_read_returns_nothing_for_a_missing_list_or_broken_file(): void
	{
		$this->assertSame([], StarterOptions::read($this->tmp->path('missing.json')));

		$list = $this->tmp->path('list.json');
		file_put_contents($list, '["a", "b"]');
		$this->assertSame([], StarterOptions::read($list));

		$broken = $this->tmp->path('broken.json');
		file_put_contents($broken, '{not json');
		$this->assertSame([], StarterOptions::read($broken));
	}

	public function test_resolve_fills_page_ids_at_any_depth_and_the_site_name(): void
	{
		$ids = ['home' => 12, 'terms-and-conditions' => 34];

		$resolved = StarterOptions::resolve(
			[
				'page_on_front'         => ['page' => 'home'],
				'kitaris_site_policies' => ['terms_page' => ['page' => 'terms-and-conditions']],
				'kitaris_organization'  => ['information' => ['name' => '{{site_name}}', 'phone' => '(555) 555-0100']],
				'default_ping_status'   => 'closed',
				'location_lat'          => 40.7484,
			],
			static fn (string $slug): int => $ids[ $slug ] ?? 0,
			'Acme Co'
		);

		$this->assertSame('12', $resolved['page_on_front']);
		$this->assertSame(['terms_page' => '34'], $resolved['kitaris_site_policies']);
		$this->assertSame(['information' => ['name' => 'Acme Co', 'phone' => '(555) 555-0100']], $resolved['kitaris_organization']);
		$this->assertSame('closed', $resolved['default_ping_status']);
		$this->assertSame(40.7484, $resolved['location_lat']);
	}

	public function test_a_missing_page_resolves_to_an_empty_string(): void
	{
		$resolved = StarterOptions::resolve(['page_for_posts' => ['page' => 'blog']], static fn (string $slug): int => 0, 'Acme');

		$this->assertSame('', $resolved['page_for_posts']);
	}

	public function test_only_a_lone_page_key_is_a_page_reference(): void
	{
		$value = ['page' => 'home', 'label' => 'Home'];

		$resolved = StarterOptions::resolve(['menu' => $value], static fn (string $slug): int => 99, 'Acme');

		$this->assertSame($value, $resolved['menu']);
	}
}
