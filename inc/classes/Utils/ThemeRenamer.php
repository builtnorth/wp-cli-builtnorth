<?php

declare(strict_types=1);

namespace BuiltNorth\CLI\Utils;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Renames a fresh clone of the Compass theme to a project theme.
 *
 * Rewrites every form of the Compass name found in the theme source:
 * the PHP namespace, COMPASS_* constants, the text domain, pattern and block
 * slugs, asset handles, the theme name in comments and docs, and file names.
 *
 * Left alone on purpose: build/, vendor/ and node_modules/ (regenerated), the
 * icon library under src/assets/icons/ (icons named after a compass), the
 * compass-waas theme name, and .gitignore / lock files.
 */
class ThemeRenamer {

    /** Directories (relative to the theme root) that are never touched. */
    private const SKIP_DIRS = [ '.git', 'build', 'vendor', 'node_modules', 'src/assets/icons' ];

    /** Files that are never touched. */
    private const SKIP_FILES = [ '.gitignore', 'composer.lock', 'package-lock.json' ];

    /** Text files the rename applies to. */
    private const EXTENSIONS = [ 'php', 'html', 'json', 'js', 'mjs', 'cjs', 'jsx', 'ts', 'tsx', 'css', 'scss', 'md', 'txt', 'yml', 'yaml', 'xml', 'pot' ];

    private string $slug;

    private string $pascal;

    private string $upper;

    private string $name;

    /**
     * @param string $slug Project slug, e.g. "client-site" (lowercase, hyphens).
     */
    public function __construct( string $slug ) {
        $this->slug = $slug;

        $words = array_values( array_filter( explode( '-', $slug ), 'strlen' ) );

        $pascal = implode( '', array_map( 'ucfirst', $words ) );
        // Namespaces and constants can't start with a digit.
        $this->pascal = preg_match( '/^[A-Za-z]/', $pascal ) ? $pascal : 'Theme' . $pascal;

        $upper       = strtoupper( implode( '_', $words ) );
        $this->upper = preg_match( '/^[A-Z]/', $upper ) ? $upper : 'THEME_' . $upper;

        $this->name = ucwords( implode( ' ', $words ) );
    }

    public function slug(): string {
        return $this->slug;
    }

    public function pascal(): string {
        return $this->pascal;
    }

    public function upper(): string {
        return $this->upper;
    }

    public function name(): string {
        return $this->name;
    }

    /**
     * Rename the theme in place.
     *
     * @param string $theme_path Theme root.
     * @return list<string> Lines still mentioning "compass" ("file:line: text"),
     *                      for the caller to report.
     */
    public function rename( string $theme_path ): array {
        $theme_path = rtrim( $theme_path, '/' );

        foreach ( $this->files( $theme_path ) as $file ) {
            $content = (string) file_get_contents( $file );
            $renamed = $this->rename_content( $content );
            if ( $renamed !== $content ) {
                file_put_contents( $file, $renamed );
            }
        }

        $this->rename_files( $theme_path );

        return $this->leftovers( $theme_path );
    }

    /**
     * Apply the rename rules to one file's content. Order matters: specific
     * forms first, the bare theme name last.
     */
    public function rename_content( string $content ): string {
        $rules = [
            // Docblock tags: "@package Compass" must stay one word.
            '/@(package|since|subpackage)(\s+)Compass\b/'       => '@$1$2' . $this->pascal,
            // Namespace, also escaped in JSON ("Compass\\Configs").
            '/\bCompass\\\\/'                                    => $this->pascal . '\\',
            // Top-level namespace ("namespace Compass;") and static calls.
            '/\bnamespace(\s+)Compass\b/'                        => 'namespace$1' . $this->pascal,
            '/\bCompass(?=::)/'                                  => $this->pascal,
            // Constants.
            '/\bCOMPASS_/'                                       => $this->upper . '_',
            // Composer package name.
            '#\bbuiltnorth/compass\b#'                           => 'builtnorth/' . $this->slug,
            // Icon generator script name.
            '/\bgenerate-compass-icons\b/'                       => 'generate-' . $this->slug . '-icons',
            // Pattern and block slugs: compass/..., not plugins/compass or compass-waas/.
            '#(?<![\w/-])compass/#'                              => $this->slug . '/',
            // Variable holding the app instance ($compass->boot()). Before the
            // handle rule, which would otherwise read "compass->" as a handle.
            '/\$compass\b/'                                      => '$theme_app',
            // Handles and classes: compass-editor, not compass-waas / icon names.
            '/(?<![\w\/$-])compass-(?!waas|rose|tool|>)/'        => $this->slug . '-',
            // style.css header: "Text Domain: compass".
            '/(Text Domain:\s*)compass\b/i'                      => '${1}' . $this->slug,
            // Text domain and slug strings: 'compass', "compass".
            '/([\'"])compass\1/'                                 => '$1' . $this->slug . '$1',
            // Hook and function prefixes: compass_before_init, compass_register_*.
            '/(?<![\w-])compass_/'                               => str_replace( '-', '_', $this->slug ) . '_',
            // The theme name in prose ("Compass theme"), not CompassWaas...
            '/\bCompass\b(?!\\\\)/'                              => $this->name,
        ];

        return (string) preg_replace( array_keys( $rules ), array_values( $rules ), $content );
    }

    /**
     * Rename files whose name contains "compass" (e.g. generate-compass-icons.mjs,
     * theme-config/styles/blocks/compass.js).
     */
    private function rename_files( string $theme_path ): void {
        foreach ( $this->files( $theme_path, false ) as $file ) {
            $base = basename( $file );
            if ( stripos( $base, 'compass' ) === false ) {
                continue;
            }
            $new = dirname( $file ) . '/' . str_ireplace( 'compass', $this->slug, $base );
            if ( ! file_exists( $new ) ) {
                rename( $file, $new );
            }
        }
    }

    /**
     * @return list<string>
     */
    private function leftovers( string $theme_path ): array {
        $left = [];
        foreach ( $this->files( $theme_path ) as $file ) {
            $lines = file( $file, FILE_IGNORE_NEW_LINES ) ?: [];
            foreach ( $lines as $number => $line ) {
                if ( stripos( $line, 'compass' ) !== false ) {
                    $left[] = substr( $file, strlen( $theme_path ) + 1 ) . ':' . ( $number + 1 ) . ': ' . trim( $line );
                }
            }
        }

        return $left;
    }

    /**
     * Text files under the theme, minus skipped dirs and files.
     *
     * @param bool $text_only Only files with a known text extension.
     * @return list<string>
     */
    private function files( string $theme_path, bool $text_only = true ): array {
        $files    = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $theme_path, FilesystemIterator::SKIP_DOTS )
        );

        foreach ( $iterator as $item ) {
            if ( ! $item->isFile() ) {
                continue;
            }

            $relative = substr( $item->getPathname(), strlen( $theme_path ) + 1 );
            if ( $this->is_skipped( $relative ) ) {
                continue;
            }

            if ( $text_only && ! in_array( strtolower( $item->getExtension() ), self::EXTENSIONS, true ) ) {
                continue;
            }

            $files[] = $item->getPathname();
        }

        sort( $files );

        return $files;
    }

    private function is_skipped( string $relative ): bool {
        if ( in_array( basename( $relative ), self::SKIP_FILES, true ) ) {
            return true;
        }

        foreach ( self::SKIP_DIRS as $dir ) {
            if ( $relative === $dir || str_starts_with( $relative, $dir . '/' ) ) {
                return true;
            }
        }

        return false;
    }
}
