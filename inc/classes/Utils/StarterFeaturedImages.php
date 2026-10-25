<?php

declare(strict_types=1);

namespace BuiltNorth\CLI\Utils;

/**
 * Featured images a new project's content starts with, from the starter's
 * setup/data/featured-images.json: "[post type:]slug" => image file in
 * setup/data/images/. A key without a post type is a page ("home"); others
 * name theirs ("post:sample-post-one").
 *
 * Setup and configure import those images; this maps each page to the slug the
 * image's attachment gets (WordPress names an attachment after its file).
 */
final class StarterFeaturedImages {

    /** The featured images file, relative to the project root. */
    public const FILE = 'setup/data/featured-images.json';

    /**
     * One entry per valid line of $path: the post type and slug of the content,
     * and the slug of the image's attachment.
     *
     * @return list<array{post_type: string, slug: string, attachment: string}>
     */
    public static function read(string $path): array {
        $entries = [];

        foreach (StarterOptions::read($path) as $key => $file) {
            if (!is_string($file) || $file === '' || basename($file) !== $file) {
                continue;
            }

            [$post_type, $slug] = str_contains((string) $key, ':')
                ? explode(':', (string) $key, 2)
                : ['page', (string) $key];
            $attachment = self::attachment_slug($file);

            if (preg_match('/^[a-z0-9_-]+$/', $post_type) && $slug !== '' && $attachment !== '') {
                $entries[] = ['post_type' => $post_type, 'slug' => $slug, 'attachment' => $attachment];
            }
        }

        return $entries;
    }

    /**
     * The slug WordPress gives an attachment imported from $file
     * ("placeholder-image-canyon.jpg" => "placeholder-image-canyon").
     */
    public static function attachment_slug(string $file): string {
        $name = strtolower(pathinfo($file, PATHINFO_FILENAME));

        return trim((string) preg_replace('/[^a-z0-9]+/', '-', $name), '-');
    }
}
