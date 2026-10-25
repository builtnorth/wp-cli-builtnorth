<?php

declare(strict_types=1);

namespace BuiltNorth\CLI\Tests\Utils;

use BuiltNorth\CLI\Tests\Support\TempDirectory;
use BuiltNorth\CLI\Utils\StarterFeaturedImages;
use PHPUnit\Framework\TestCase;

final class StarterFeaturedImagesTest extends TestCase
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

	public function test_read_maps_content_to_attachment_slugs(): void
	{
		$path = $this->tmp->path('featured-images.json');
		file_put_contents($path, '{"home": "placeholder-image-canyon.jpg", "post:sample-post-one": "Team Photo.PNG"}');

		$this->assertSame(
			[
				['post_type' => 'page', 'slug' => 'home', 'attachment' => 'placeholder-image-canyon'],
				['post_type' => 'post', 'slug' => 'sample-post-one', 'attachment' => 'team-photo'],
			],
			StarterFeaturedImages::read($path)
		);
	}

	public function test_read_skips_paths_empty_files_and_bad_post_types(): void
	{
		$path = $this->tmp->path('featured-images.json');
		file_put_contents($path, '{"home": "../secret.jpg", "about": "", "contact": 5, "blog": "images/x.jpg", "Bad Type!:x": "a.jpg", "post:": "b.jpg"}');

		$this->assertSame([], StarterFeaturedImages::read($path));
	}

	public function test_read_returns_nothing_without_a_file(): void
	{
		$this->assertSame([], StarterFeaturedImages::read($this->tmp->path('missing.json')));
	}
}
