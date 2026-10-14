<?php

declare(strict_types=1);

namespace BuiltNorth\CLI\Tests\Utils;

use BuiltNorth\CLI\Utils\ContentGenerator;
use PHPUnit\Framework\TestCase;

final class ContentGeneratorTest extends TestCase
{
	/**
	 * Random generators are sampled this many times so range checks hit both ends.
	 */
	private const SAMPLES = 300;

	private const LOCATIONS = 'Downtown|Uptown|Midtown|Riverside|Lakeside|Hillside|Westside|Eastside|North End|South End|City Center|Business District|Tech Park|Industrial Zone|Harbor District';

	private const BUSINESSES = 'Acme Corporation|Global Industries|Premier Solutions|Elite Enterprises|Summit Group|Apex Systems|Pinnacle Holdings|Zenith Partners|Prime Ventures|Alpha Innovations|Omega Services|Delta Technologies|Sigma Consulting|Phoenix Development|Horizon Properties';

	// ---------------------------------------------------------------------
	// generate_title
	// ---------------------------------------------------------------------

	public function test_numbered_title_is_prefix_space_index(): void
	{
		$this->assertSame('Listing 7', ContentGenerator::generate_title('Listing', 7, 'numbered'));
	}

	public function test_default_title_uses_hash_index(): void
	{
		$this->assertSame('Listing #7', ContentGenerator::generate_title('Listing', 7));
	}

	public function test_unknown_title_type_falls_back_to_default(): void
	{
		$this->assertSame('Listing #3', ContentGenerator::generate_title('Listing', 3, 'not-a-type'));
	}

	public function test_title_keeps_unicode_prefix_intact(): void
	{
		$this->assertSame('Café Ünïcødé 1', ContentGenerator::generate_title('Café Ünïcødé', 1, 'numbered'));
	}

	public function test_location_title_uses_known_location_and_index(): void
	{
		for ($i = 0; $i < 50; $i++) {
			$title = ContentGenerator::generate_title('Office', 12, 'location');
			$this->assertMatchesRegularExpression('/^Office at (' . self::LOCATIONS . ') #12$/', $title);
		}
	}

	public function test_business_title_ignores_prefix_and_index(): void
	{
		for ($i = 0; $i < 50; $i++) {
			$title = ContentGenerator::generate_title('Ignored', 99, 'business');
			$this->assertMatchesRegularExpression('/^(' . self::BUSINESSES . ') - (' . self::LOCATIONS . ')$/', $title);
		}
	}

	// ---------------------------------------------------------------------
	// generate_content
	// ---------------------------------------------------------------------

	public function test_default_content_has_three_paragraphs_and_both_headings(): void
	{
		$content = ContentGenerator::generate_content();

		$this->assertSame(3, substr_count($content, "<!-- wp:paragraph -->\n"));
		$this->assertSame(3, substr_count($content, "<!-- /wp:paragraph -->\n"));
		$this->assertSame(1, substr_count($content, '<h2>Overview</h2>'));
		$this->assertSame(1, substr_count($content, '<h3>Details</h3>'));
		$this->assertStringStartsWith("<!-- wp:heading -->\n<h2>Overview</h2>\n<!-- /wp:heading -->\n\n", $content);
	}

	public function test_details_heading_sits_after_middle_paragraph(): void
	{
		// 3 paragraphs: floor(3/2) = 1, so Details follows the 2nd paragraph (index 1).
		$content = ContentGenerator::generate_content(3, true);
		$parts   = explode('<h3>Details</h3>', $content);

		$this->assertCount(2, $parts);
		$this->assertSame(2, substr_count($parts[0], '<!-- wp:paragraph -->'));
		$this->assertSame(1, substr_count($parts[1], '<!-- wp:paragraph -->'));
		$this->assertStringContainsString('<!-- wp:heading {"level":3} -->', $content);
	}

	public function test_content_without_headings_has_only_paragraphs(): void
	{
		$content = ContentGenerator::generate_content(4, false);

		$this->assertSame(4, substr_count($content, '<!-- wp:paragraph -->'));
		$this->assertStringNotContainsString('wp:heading', $content);
		$this->assertStringStartsWith("<!-- wp:paragraph -->\n<p>Lorem ipsum", $content);
	}

	public function test_zero_paragraphs_with_headings_yields_only_overview(): void
	{
		$content = ContentGenerator::generate_content(0, true);

		$this->assertSame("<!-- wp:heading -->\n<h2>Overview</h2>\n<!-- /wp:heading -->\n\n", $content);
	}

	public function test_zero_paragraphs_without_headings_is_empty(): void
	{
		$this->assertSame('', ContentGenerator::generate_content(0, false));
	}

	public function test_single_paragraph_still_gets_details_heading(): void
	{
		// floor(1/2) = 0, so Details follows the only paragraph.
		$content = ContentGenerator::generate_content(1, true);

		$this->assertSame(1, substr_count($content, '<!-- wp:paragraph -->'));
		$this->assertSame(1, substr_count($content, '<h3>Details</h3>'));
		$this->assertGreaterThan(strpos($content, '<!-- /wp:paragraph -->'), strpos($content, '<h3>Details</h3>'));
	}

	public function test_paragraphs_cycle_past_the_lorem_pool(): void
	{
		// The pool has 5 paragraphs; the 6th wraps to the 1st.
		$content = ContentGenerator::generate_content(6, false);
		preg_match_all('#<p>(.*?)</p>#', $content, $matches);

		$this->assertCount(6, $matches[1]);
		$this->assertSame($matches[1][0], $matches[1][5]);
		$this->assertCount(5, array_unique(array_slice($matches[1], 0, 5)));
	}

	// ---------------------------------------------------------------------
	// generate_excerpt
	// ---------------------------------------------------------------------

	public function test_excerpt_is_first_sentence_with_period(): void
	{
		$this->assertSame(
			'Lorem ipsum dolor sit amet, consectetur adipiscing elit.',
			ContentGenerator::generate_excerpt()
		);
	}

	// ---------------------------------------------------------------------
	// generate_meta_value
	// ---------------------------------------------------------------------

	/**
	 * @return array<string, array{string, int, int}>
	 */
	public static function numeric_ranges(): array
	{
		return [
			'price'  => ['price', 100000, 5000000],
			'area'   => ['area', 1000, 50000],
			'rooms'  => ['rooms', 1, 20],
			'rating' => ['rating', 1, 5],
		];
	}

	/**
	 * @dataProvider numeric_ranges
	 */
	public function test_numeric_meta_values_are_ints_within_range(string $type, int $min, int $max): void
	{
		for ($i = 0; $i < self::SAMPLES; $i++) {
			$value = ContentGenerator::generate_meta_value($type);
			$this->assertIsInt($value);
			$this->assertGreaterThanOrEqual($min, $value);
			$this->assertLessThanOrEqual($max, $value);
		}
	}

	public function test_year_is_int_between_1990_and_current_utc_year(): void
	{
		$current = (int) gmdate('Y');

		for ($i = 0; $i < self::SAMPLES; $i++) {
			$value = ContentGenerator::generate_meta_value('year');
			$this->assertIsInt($value);
			$this->assertGreaterThanOrEqual(1990, $value);
			$this->assertLessThanOrEqual($current, $value);
		}
	}

	public function test_boolean_is_yes_or_no_string(): void
	{
		$seen = [];
		for ($i = 0; $i < self::SAMPLES; $i++) {
			$value = ContentGenerator::generate_meta_value('boolean');
			$this->assertContains($value, ['yes', 'no']);
			$seen[$value] = true;
		}

		$this->assertCount(2, $seen, 'Both yes and no should appear across samples.');
	}

	public function test_email_uses_known_local_parts_and_domains(): void
	{
		for ($i = 0; $i < 50; $i++) {
			$value = ContentGenerator::generate_meta_value('email');
			$this->assertMatchesRegularExpression('/^(info|contact|admin|support|sales)@(example|test|demo)\.com$/', $value);
			$this->assertNotFalse(filter_var($value, FILTER_VALIDATE_EMAIL));
		}
	}

	public function test_phone_is_nanp_style_without_leading_zero_or_one(): void
	{
		for ($i = 0; $i < self::SAMPLES; $i++) {
			$value = ContentGenerator::generate_meta_value('phone');
			$this->assertMatchesRegularExpression('/^\([2-9]\d{2}\) [2-9]\d{2}-\d{4}$/', $value);
		}
	}

	public function test_address_is_number_then_known_street(): void
	{
		for ($i = 0; $i < 50; $i++) {
			$value = ContentGenerator::generate_meta_value('address');
			$this->assertMatchesRegularExpression('/^\d{3,4} (Main St|Oak Ave|Elm Dr|Park Blvd|Market St)$/', $value);
			$this->assertGreaterThanOrEqual(100, (int) $value);
		}
	}

	public function test_unknown_meta_type_returns_sample_string(): void
	{
		for ($i = 0; $i < 50; $i++) {
			$value = ContentGenerator::generate_meta_value('color');
			$this->assertMatchesRegularExpression('/^Sample color (\d{1,3})$/', $value);
			$number = (int) substr($value, strlen('Sample color '));
			$this->assertGreaterThanOrEqual(1, $number);
			$this->assertLessThanOrEqual(100, $number);
		}
	}

	public function test_empty_meta_type_returns_sample_string(): void
	{
		$this->assertMatchesRegularExpression('/^Sample  \d{1,3}$/', ContentGenerator::generate_meta_value(''));
	}

	// ---------------------------------------------------------------------
	// random_items
	// ---------------------------------------------------------------------

	public function test_random_items_returns_all_when_count_meets_size(): void
	{
		$items = ['a' => 1, 'b' => 2, 'c' => 3];

		$this->assertSame($items, ContentGenerator::random_items($items, 3));
		$this->assertSame($items, ContentGenerator::random_items($items, 10));
	}

	public function test_random_items_single_item_pool_keeps_its_key(): void
	{
		$this->assertSame(['only' => 'x'], ContentGenerator::random_items(['only' => 'x'], 1));
	}

	public function test_random_items_on_empty_array_returns_empty(): void
	{
		$this->assertSame([], ContentGenerator::random_items([], 1));
	}

	public function test_random_items_single_returns_list_with_one_member(): void
	{
		$items = [10, 20, 30, 40];

		for ($i = 0; $i < 50; $i++) {
			$picked = ContentGenerator::random_items($items);
			$this->assertCount(1, $picked);
			$this->assertSame([0], array_keys($picked));
			$this->assertContains($picked[0], $items);
		}
	}

	public function test_random_items_many_returns_distinct_subset_with_original_keys(): void
	{
		$items = [5 => 'e', 6 => 'f', 7 => 'g', 8 => 'h', 9 => 'i'];

		for ($i = 0; $i < 50; $i++) {
			$picked = ContentGenerator::random_items($items, 3);
			$this->assertCount(3, $picked);
			foreach ($picked as $key => $value) {
				$this->assertArrayHasKey($key, $items);
				$this->assertSame($items[$key], $value);
			}
		}
	}
}
