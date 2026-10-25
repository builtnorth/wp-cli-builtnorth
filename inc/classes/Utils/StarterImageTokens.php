<?php

declare(strict_types=1);

namespace BuiltNorth\CLI\Utils;

/**
 * Image placeholders in the starter content.
 *
 * Exported content can't keep the source site's image URLs or attachment IDs,
 * so images inside blocks are saved as `{{image:<attachment-slug>}}` (the slug
 * WordPress gives a file in setup/data/images/, e.g. placeholder-image-dunes).
 * After setup imports those images, each placeholder becomes the image's URL.
 * An attachment ID a block stores is saved as the JSON string
 * `"{{image-id:<attachment-slug>}}"`, which becomes the image's numeric ID.
 */
final class StarterImageTokens {

    /** One placeholder; group 1 is the attachment slug. */
    public const PATTERN = '/\{\{image:([a-z0-9-]+)\}\}/';

    /** One ID placeholder, quotes included (it stands in for a JSON number). */
    public const ID_PATTERN = '/"\{\{image-id:([a-z0-9-]+)\}\}"/';

    /**
     * $content with every placeholder replaced by its image URL. A placeholder
     * whose image doesn't exist is left as it is.
     *
     * @param string                     $content
     * @param callable(string): string   $url_for URL for an attachment slug, '' when there is none.
     * @param callable(string): int|null $id_for  Attachment ID for a slug, 0 when there is none.
     */
    public static function replace(string $content, callable $url_for, ?callable $id_for = null): string {
        $content = (string) preg_replace_callback(
            self::PATTERN,
            static function (array $match) use ($url_for): string {
                $url = $url_for($match[1]);

                return $url !== '' ? $url : $match[0];
            },
            $content
        );

        if ($id_for === null) {
            return $content;
        }

        return (string) preg_replace_callback(
            self::ID_PATTERN,
            static function (array $match) use ($id_for): string {
                $id = $id_for($match[1]);

                return $id > 0 ? (string) $id : $match[0];
            },
            $content
        );
    }
}
