<?php

namespace BuiltNorth\CLI\Commands;

use BuiltNorth\CLI\BaseCommand;
use BuiltNorth\CLI\Utils\ContentGenerator;
use WP_CLI;

/**
 * Generate command for creating test content
 */
class GenerateCommand extends BaseCommand {
    
    /**
     * Command name
     * 
     * @var string
     */
    protected $command_name = 'generate';
    
    /**
     * Get short description
     * 
     * @return string
     */
    protected function get_shortdesc() {
        return 'Generate test content for development';
    }
    
    /**
     * Generate test posts with terms
     * 
     * ## OPTIONS
     * 
     * [--count=<count>]
     * : Number of posts to generate
     * ---
     * default: 10
     * ---
     * 
     * [--post-type=<post-type>]
     * : Post type to generate
     * ---
     * default: post
     * ---
     * 
     * [--title-prefix=<prefix>]
     * : Prefix for generated titles
     * ---
     * default: Generated
     * ---
     * 
     * [--title-type=<type>]
     * : Type of title generation (default, business, location, numbered)
     * ---
     * default: numbered
     * ---
     * 
     * [--status=<status>]
     * : Post status
     * ---
     * default: publish
     * ---
     * 
     * [--author=<author>]
     * : Author ID or username
     * 
     * [--taxonomy=<taxonomy>]
     * : Taxonomy to assign terms from
     * 
     * [--terms=<terms>]
     * : Comma-separated list of term slugs to create/assign
     * 
     * [--random-terms]
     * : Randomly assign terms to posts
     * 
     * [--with-content]
     * : Generate content for posts
     * 
     * [--with-excerpt]
     * : Generate excerpts for posts
     * 
     * [--with-featured-image]
     * : Add placeholder featured images
     * 
     * [--meta=<meta>]
     * : JSON string of meta key-value pairs to add
     * 
     * ## EXAMPLES
     * 
     *     # Generate 10 posts
     *     wp builtnorth generate
     * 
     *     # Generate listings with categories
     *     wp builtnorth generate --count=50 --post-type=polaris_listing --taxonomy=polaris_listing_category --terms=residential,commercial,industrial --random-terms
     * 
     *     # Generate posts with full content
     *     wp builtnorth generate --count=20 --with-content --with-excerpt --with-featured-image
     * 
     *     # Generate with custom meta
     *     wp builtnorth generate --post-type=property --meta='{"price":"price","bedrooms":"rooms","location":"address"}'
     * 
     * @when after_wp_load
     */
    public function __invoke($args, $assoc_args) {
        $count = (int) ($assoc_args['count'] ?? 10);
        $post_type = $assoc_args['post-type'] ?? 'post';
        $title_prefix = $assoc_args['title-prefix'] ?? 'Generated';
        $title_type = $assoc_args['title-type'] ?? 'numbered';
        $status = $assoc_args['status'] ?? 'publish';
        $taxonomy = $assoc_args['taxonomy'] ?? null;
        $terms = $assoc_args['terms'] ?? null;
        $random_terms = !empty($assoc_args['random-terms']);
        $with_content = !empty($assoc_args['with-content']);
        $with_excerpt = !empty($assoc_args['with-excerpt']);
        $with_featured = !empty($assoc_args['with-featured-image']);
        $meta_json = $assoc_args['meta'] ?? null;
        
        // Validate post type
        if (!post_type_exists($post_type)) {
            WP_CLI::error("Post type '$post_type' does not exist");
        }
        
        // Handle author
        $author_id = 1;
        if (!empty($assoc_args['author'])) {
            $user = is_numeric($assoc_args['author']) 
                ? get_user_by('id', $assoc_args['author'])
                : get_user_by('login', $assoc_args['author']);
            
            if (!$user) {
                WP_CLI::error("User '{$assoc_args['author']}' not found");
            }
            $author_id = $user->ID;
        }
        
        // Handle taxonomy and terms
        $term_ids = [];
        if ($taxonomy && $terms) {
            if (!taxonomy_exists($taxonomy)) {
                WP_CLI::error("Taxonomy '$taxonomy' does not exist");
            }
            
            $term_slugs = array_map('trim', explode(',', $terms));
            $term_ids = $this->ensure_terms_exist($taxonomy, $term_slugs);
        }
        
        // Parse meta configuration
        $meta_config = [];
        if ($meta_json) {
            $meta_config = json_decode($meta_json, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                WP_CLI::error('Invalid JSON in meta parameter');
            }
        }
        
        // Generate posts
        $progress = $this->make_progress_bar("Generating $count {$post_type}s", $count);
        $created_count = 0;
        
        for ($i = 1; $i <= $count; $i++) {
            $post_data = [
                'post_type' => $post_type,
                'post_title' => ContentGenerator::generate_title($title_prefix, $i, $title_type),
                'post_status' => $status,
                'post_author' => $author_id,
            ];
            
            if ($with_content) {
                $post_data['post_content'] = ContentGenerator::generate_content();
            }
            
            if ($with_excerpt) {
                $post_data['post_excerpt'] = ContentGenerator::generate_excerpt();
            }
            
            // Create post
            $post_id = wp_insert_post($post_data);
            
            if (is_wp_error($post_id)) {
                WP_CLI::warning("Failed to create post: " . $post_id->get_error_message());
                $progress->tick();
                continue;
            }
            
            // Assign terms
            if ($taxonomy && $term_ids) {
                if ($random_terms) {
                    // Randomly select 1-3 terms
                    $selected_terms = ContentGenerator::random_items($term_ids, rand(1, min(3, count($term_ids))));
                    wp_set_object_terms($post_id, $selected_terms, $taxonomy);
                } else {
                    // Assign all terms
                    wp_set_object_terms($post_id, $term_ids, $taxonomy);
                }
            }
            
            // Add meta data
            foreach ($meta_config as $meta_key => $meta_type) {
                $meta_value = ContentGenerator::generate_meta_value($meta_type);
                update_post_meta($post_id, $meta_key, $meta_value);
            }
            
            // Add featured image
            if ($with_featured) {
                $this->add_placeholder_featured_image($post_id);
            }
            
            $created_count++;
            $progress->tick();
        }
        
        $progress->finish();
        
        WP_CLI::success("Generated $created_count {$post_type}s");
        
        // Show summary
        if ($taxonomy) {
            $this->show_term_summary($post_type, $taxonomy);
        }
    }
    
    /**
     * Ensure terms exist and return their IDs
     * 
     * @param string $taxonomy
     * @param array $term_slugs
     * @return array Term IDs
     */
    private function ensure_terms_exist($taxonomy, $term_slugs) {
        $term_ids = [];
        
        foreach ($term_slugs as $slug) {
            $term = get_term_by('slug', $slug, $taxonomy);
            
            if (!$term) {
                // Create term
                $name = ucwords(str_replace('-', ' ', $slug));
                $result = wp_insert_term($name, $taxonomy, ['slug' => $slug]);
                
                if (is_wp_error($result)) {
                    WP_CLI::warning("Failed to create term '$slug': " . $result->get_error_message());
                    continue;
                }
                
                $term_ids[] = $result['term_id'];
                WP_CLI::debug("Created term '$name' (slug: $slug)");
            } else {
                $term_ids[] = $term->term_id;
            }
        }
        
        return $term_ids;
    }
    
    /**
     * Add placeholder featured image
     * 
     * @param int $post_id
     */
    private function add_placeholder_featured_image($post_id) {
        // Check if we have a placeholder image in media library
        $placeholder = get_page_by_title('placeholder-image-canyon', OBJECT, 'attachment');
        
        if ($placeholder) {
            set_post_thumbnail($post_id, $placeholder->ID);
        } else {
            // Try to find any image
            $images = get_posts([
                'post_type' => 'attachment',
                'post_mime_type' => 'image',
                'posts_per_page' => 1,
            ]);
            
            if ($images) {
                set_post_thumbnail($post_id, $images[0]->ID);
            }
        }
    }
    
    /**
     * Show term assignment summary
     * 
     * @param string $post_type
     * @param string $taxonomy
     */
    private function show_term_summary($post_type, $taxonomy) {
        $terms = get_terms([
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
        ]);
        
        if (empty($terms)) {
            return;
        }
        
        WP_CLI::line('');
        WP_CLI::line('Term distribution:');
        
        foreach ($terms as $term) {
            $count = $term->count;
            WP_CLI::line(sprintf('  %s: %d %s', $term->name, $count, $post_type . ($count !== 1 ? 's' : '')));
        }
    }
}