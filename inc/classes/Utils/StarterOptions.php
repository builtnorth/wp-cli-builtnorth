<?php

declare(strict_types=1);

namespace BuiltNorth\CLI\Utils;

/**
 * Site options a new project starts with, from the starter's setup/data/options.json.
 *
 * The file maps option names to values. Two placeholders are resolved before
 * the values are saved, since neither is known until the site exists:
 *
 * - `{"page": "<slug>"}` anywhere in a value becomes that page's ID (as a
 *   string, '' when there is no such page): page IDs change on import.
 * - `{{site_name}}` inside a string becomes the site's name.
 */
final class StarterOptions {

    /** The options file, relative to the project root. */
    public const FILE = 'setup/data/options.json';

    /**
     * The options in $path, or [] when the file is missing or isn't a JSON object.
     *
     * @return array<string, mixed>
     */
    public static function read(string $path): array {
        if (!is_readable($path)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) && !array_is_list($data) ? $data : [];
    }

    /**
     * The options with page references and the site name filled in.
     *
     * @param array<string, mixed>   $options   Option name => value.
     * @param callable(string): int  $page_id   Page ID for a slug, 0 when there is none.
     * @param string                 $site_name Replaces {{site_name}}.
     * @return array<string, mixed>
     */
    public static function resolve(array $options, callable $page_id, string $site_name): array {
        $resolve = static function ($value) use (&$resolve, $page_id, $site_name) {
            if (is_array($value) && array_keys($value) === ['page'] && is_string($value['page'])) {
                $id = $page_id($value['page']);

                return $id > 0 ? (string) $id : '';
            }

            if (is_array($value)) {
                return array_map($resolve, $value);
            }

            return is_string($value) ? str_replace('{{site_name}}', $site_name, $value) : $value;
        };

        return array_map($resolve, $options);
    }
}
