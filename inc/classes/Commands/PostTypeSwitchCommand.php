<?php

namespace BuiltNorth\CLI\Commands;

use BuiltNorth\CLI\BaseCommand;
use WP_CLI;

/**
 * Post Type Switch command for converting posts between types
 */
class PostTypeSwitchCommand extends BaseCommand {
    
    /**
     * Command name
     * 
     * @var string
     */
    protected $command_name = 'post-type-switch';
    
    /**
     * Get short description
     * 
     * @return string
     */
    protected function get_shortdesc() {
        return 'Convert posts from one post type to another';
    }
    
    /**
     * Switch posts from one post type to another
     * 
     * ## OPTIONS
     * 
     * --from=<post-type>
     * : The source post type
     * 
     * --to=<post-type>
     * : The target post type
     * 
     * [--status=<status>]
     * : Only convert posts with this status (default: any)
     * 
     * [--limit=<number>]
     * : Limit the number of posts to convert
     * 
     * [--dry-run]
     * : Preview what would be changed without making changes
     * 
     * [--include-taxonomies]
     * : Attempt to map taxonomies between post types
     * 
     * [--yes]
     * : Skip confirmation prompt
     * 
     * ## EXAMPLES
     * 
     *     # Convert all posts to a custom post type
     *     wp builtnorth post-type-switch --from=post --to=article
     * 
     *     # Dry run to see what would change
     *     wp builtnorth post-type-switch --from=post --to=news --dry-run
     * 
     *     # Convert only published posts
     *     wp builtnorth post-type-switch --from=post --to=resource --status=publish
     * 
     *     # Convert with taxonomy mapping
     *     wp builtnorth post-type-switch --from=post --to=product --include-taxonomies
     * 
     * @when after_wp_load
     */
    public function __invoke($args, $assoc_args) {
        // Validate required arguments
        if (empty($assoc_args['from']) || empty($assoc_args['to'])) {
            WP_CLI::error('Both --from and --to post types are required');
        }
        
        $from_type = $assoc_args['from'];
        $to_type = $assoc_args['to'];
        $dry_run = !empty($assoc_args['dry-run']);
        $status = $assoc_args['status'] ?? 'any';
        $limit = isset($assoc_args['limit']) ? intval($assoc_args['limit']) : -1;
        $include_taxonomies = !empty($assoc_args['include-taxonomies']);
        
        // Validate post types
        if (!post_type_exists($from_type)) {
            WP_CLI::error("Source post type '{$from_type}' does not exist");
        }
        
        if (!post_type_exists($to_type)) {
            WP_CLI::error("Target post type '{$to_type}' does not exist");
        }
        
        if ($from_type === $to_type) {
            WP_CLI::error('Source and target post types cannot be the same');
        }
        
        // Get posts to convert
        $query_args = [
            'post_type' => $from_type,
            'post_status' => $status,
            'posts_per_page' => $limit,
            'fields' => 'ids',
            'no_found_rows' => true,
        ];
        
        $post_ids = get_posts($query_args);
        $total_posts = count($post_ids);
        
        if ($total_posts === 0) {
            WP_CLI::success("No posts found with type '{$from_type}'" . ($status !== 'any' ? " and status '{$status}'" : ''));
            return;
        }
        
        // Show what will be done
        WP_CLI::line("Found {$total_posts} post(s) to convert from '{$from_type}' to '{$to_type}'");
        
        if ($include_taxonomies) {
            $this->show_taxonomy_mapping($from_type, $to_type);
        }
        
        // Confirm unless --yes or --dry-run
        if (!$dry_run && empty($assoc_args['yes'])) {
            WP_CLI::warning('This will permanently change the post type of the selected posts.');
            $confirm = $this->prompt('Are you sure you want to continue? [y/N]', 'n');
            if (strtolower($confirm) !== 'y') {
                WP_CLI::error('Operation cancelled');
            }
        }
        
        if ($dry_run) {
            WP_CLI::line('');
            WP_CLI::line('DRY RUN MODE - No changes will be made');
            WP_CLI::line('');
        }
        
        // Convert posts
        $progress = $this->make_progress_bar('Converting posts', $total_posts);
        $converted = 0;
        $errors = 0;
        
        foreach ($post_ids as $post_id) {
            $progress->tick();
            
            if ($dry_run) {
                $post = get_post($post_id);
                WP_CLI::debug("Would convert: {$post->post_title} (ID: {$post_id})");
                $converted++;
                continue;
            }
            
            // Convert the post type
            $result = $this->convert_post_type($post_id, $to_type, $include_taxonomies, $from_type);
            
            if ($result) {
                $converted++;
            } else {
                $errors++;
                WP_CLI::warning("Failed to convert post ID: {$post_id}");
            }
        }
        
        $progress->finish();
        
        // Show results
        WP_CLI::line('');
        if ($dry_run) {
            WP_CLI::success("Dry run complete. Would convert {$converted} post(s)");
        } else {
            WP_CLI::success("Converted {$converted} post(s) from '{$from_type}' to '{$to_type}'");
            if ($errors > 0) {
                WP_CLI::warning("Failed to convert {$errors} post(s)");
            }
            
            // Clear caches
            WP_CLI::line('Clearing caches...');
            wp_cache_flush();
            
            // Flush rewrite rules
            WP_CLI::line('Flushing rewrite rules...');
            flush_rewrite_rules();
            WP_CLI::success('Rewrite rules flushed');
        }
    }
    
    /**
     * Convert a single post type
     * 
     * @param int $post_id
     * @param string $new_type
     * @param bool $include_taxonomies
     * @param string $old_type
     * @return bool
     */
    private function convert_post_type($post_id, $new_type, $include_taxonomies, $old_type) {
        global $wpdb;
        
        // Update post type
        $updated = $wpdb->update(
            $wpdb->posts,
            ['post_type' => $new_type],
            ['ID' => $post_id],
            ['%s'],
            ['%d']
        );
        
        if ($updated === false) {
            return false;
        }
        
        // Handle taxonomies if requested
        if ($include_taxonomies) {
            $this->migrate_taxonomies($post_id, $old_type, $new_type);
        }
        
        // Clean post cache
        clean_post_cache($post_id);
        
        return true;
    }
    
    /**
     * Show taxonomy mapping between post types
     * 
     * @param string $from_type
     * @param string $to_type
     */
    private function show_taxonomy_mapping($from_type, $to_type) {
        $from_taxonomies = get_object_taxonomies($from_type);
        $to_taxonomies = get_object_taxonomies($to_type);
        
        $common_taxonomies = array_intersect($from_taxonomies, $to_taxonomies);
        $unique_from = array_diff($from_taxonomies, $to_taxonomies);
        $unique_to = array_diff($to_taxonomies, $from_taxonomies);
        
        WP_CLI::line('');
        WP_CLI::line('Taxonomy Analysis:');
        
        if (!empty($common_taxonomies)) {
            WP_CLI::line('  Shared taxonomies (will be preserved): ' . implode(', ', $common_taxonomies));
        }
        
        if (!empty($unique_from)) {
            WP_CLI::line('  Taxonomies only in source (will be removed): ' . implode(', ', $unique_from));
        }
        
        if (!empty($unique_to)) {
            WP_CLI::line('  Taxonomies only in target (will be empty): ' . implode(', ', $unique_to));
        }
        
        WP_CLI::line('');
    }
    
    /**
     * Migrate taxonomies between post types
     * 
     * @param int $post_id
     * @param string $old_type
     * @param string $new_type
     */
    private function migrate_taxonomies($post_id, $old_type, $new_type) {
        $old_taxonomies = get_object_taxonomies($old_type);
        $new_taxonomies = get_object_taxonomies($new_type);
        
        // Remove terms from taxonomies not supported by new post type
        $taxonomies_to_remove = array_diff($old_taxonomies, $new_taxonomies);
        foreach ($taxonomies_to_remove as $taxonomy) {
            wp_delete_object_term_relationships($post_id, $taxonomy);
        }
        
        // Common taxonomies will remain associated automatically
        // since we're only changing the post type, not the term relationships
    }
}