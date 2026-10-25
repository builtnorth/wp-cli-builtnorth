<?php

declare(strict_types=1);

namespace BuiltNorth\CLI\Tests\Utils;

use BuiltNorth\CLI\Utils\StarterImageTokens;
use PHPUnit\Framework\TestCase;

final class StarterImageTokensTest extends TestCase
{
	public function test_placeholders_become_image_urls_in_attributes_and_markup(): void
	{
		$content = '<!-- wp:image {"url":"{{image:placeholder-image-dunes}}"} --><figure><img src="{{image:placeholder-image-dunes}}" alt=""/></figure><!-- /wp:image -->';
		$urls    = ['placeholder-image-dunes' => 'https://example.test/wp-content/uploads/placeholder-image-dunes.jpg'];

		$this->assertSame(
			str_replace('{{image:placeholder-image-dunes}}', $urls['placeholder-image-dunes'], $content),
			StarterImageTokens::replace($content, static fn (string $slug): string => $urls[ $slug ] ?? '')
		);
	}

	public function test_a_placeholder_without_an_image_is_left_as_is(): void
	{
		$content = '<img src="{{image:missing}}">';

		$this->assertSame($content, StarterImageTokens::replace($content, static fn (string $slug): string => ''));
	}

	public function test_only_lowercase_slug_placeholders_match(): void
	{
		$content = '{{image:Bad Slug}} {{img:placeholder}}';

		$this->assertSame($content, StarterImageTokens::replace($content, static fn (string $slug): string => 'X'));
	}

	public function test_id_placeholders_become_numbers_in_block_json(): void
	{
		$content = '<!-- wp:kitaris/logo {"mediaID":"{{image-id:placeholder-logo-01}}"} /--><!-- wp:kitaris/logo {"mediaID":"{{image-id:missing}}"} /-->';

		$this->assertSame(
			'<!-- wp:kitaris/logo {"mediaID":42} /--><!-- wp:kitaris/logo {"mediaID":"{{image-id:missing}}"} /-->',
			StarterImageTokens::replace($content, static fn (string $s): string => '', static fn (string $s): int => $s === 'placeholder-logo-01' ? 42 : 0)
		);
	}
}
