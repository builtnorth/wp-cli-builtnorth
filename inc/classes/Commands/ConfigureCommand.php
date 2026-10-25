<?php

namespace BuiltNorth\CLI\Commands;

use BuiltNorth\CLI\BaseCommand;
use BuiltNorth\CLI\Traits\FileOperationsTrait;
use BuiltNorth\CLI\Utils\StarterFeaturedImages;
use BuiltNorth\CLI\Utils\StarterOptions;
use WP_CLI;

/**
 * Configure command for setting up WordPress after installation
 */
class ConfigureCommand extends BaseCommand {
    
    use FileOperationsTrait;
    
    /**
     * Command name
     * 
     * @var string
     */
    protected $command_name = 'configure';
    
    /**
     * Get short description
     * 
     * @return string
     */
    protected function get_shortdesc() {
        return 'Configure WordPress for BuiltNorth projects';
    }
    
    /**
     * Configure WordPress installation
     * 
     * ## OPTIONS
     * 
     * [--skip-content]
     * : Skip content import
     * 
     * [--skip-media]
     * : Skip media import
     * 
     * [--site-url=<url>]
     * : Replace the site's current URL with this one (search-replace)
     * 
     * ## EXAMPLES
     * 
     *     wp builtnorth configure
     *     wp builtnorth configure --skip-content
     * 
     * @when after_wp_load
     */
    public function __invoke($args, $assoc_args) {
        WP_CLI::line('Configuring WordPress for BuiltNorth...');
        
        // Warn about data changes
        $will_modify_content = empty($assoc_args['skip-content']) || empty($assoc_args['skip-media']);
        $will_delete_defaults = true; // We always delete default content
        
        if ($will_modify_content || $will_delete_defaults) {
            WP_CLI::warning('This command will make the following changes:');
            
            if ($will_delete_defaults) {
                WP_CLI::line('  - Delete default WordPress content (Hello World post, Sample Page)');
            }
            
            if (empty($assoc_args['skip-content'])) {
                WP_CLI::line('  - Import pages and content from setup/data/content/*.xml');
            }
            
            if (empty($assoc_args['skip-media'])) {
                WP_CLI::line('  - Import media files (logo, icon, placeholder images)');
            }
            
            if (!empty($assoc_args['site-url'])) {
                WP_CLI::line("  - Replace URLs in database with: {$assoc_args['site-url']}");
            }
            
            WP_CLI::line('  - Activate compass theme (if available)');
            WP_CLI::line('  - Activate all plugins');
            WP_CLI::line('  - Update WordPress settings and permalinks');
            WP_CLI::line('  - Apply setup/data/options.json (organization, site policies, discussion, reading)');
            
            WP_CLI::line('');
            
            // Add --yes flag support
            if (empty($assoc_args['yes'])) {
                $confirm = $this->prompt('Do you want to proceed? [y/N]', 'n');
                if (strtolower($confirm) !== 'y') {
                    WP_CLI::error('Configuration cancelled by user.');
                }
            }
            
            WP_CLI::line('');
        }
        
        $steps = $this->get_configuration_steps($assoc_args);
        $progress = $this->make_progress_bar('Configuring WordPress', count($steps));
        
        foreach ($steps as $step => $method) {
            $progress->tick();
            $this->$method($assoc_args);
        }
        
        $progress->finish();
        WP_CLI::success('WordPress configuration complete!');
    }
    
    /**
     * Get configuration steps based on options
     * 
     * @param array $assoc_args
     * @return array
     */
    private function get_configuration_steps($assoc_args) {
        $steps = [
            'Activating theme' => 'activate_theme',
            'Installing plugins' => 'install_plugins',
            'Activating plugins' => 'activate_plugins',
            'Configuring settings' => 'configure_settings',
            'Setting up permalinks' => 'setup_permalinks',
        ];
        
        if (empty($assoc_args['skip-media'])) {
            $steps['Importing media'] = 'import_media';
        }
        
        if (empty($assoc_args['skip-content'])) {
            $steps['Importing content'] = 'import_content';
        }
        
        $steps['Setting up pages'] = 'setup_pages';
        $steps['Cleaning default content'] = 'clean_default_content';
        $steps['Applying starter options'] = 'apply_starter_options';
        $steps['Setting featured images'] = 'apply_featured_images';
        
        if (!empty($assoc_args['site-url'])) {
            $steps['Updating URLs'] = 'update_urls';
        }
        
        return $steps;
    }
    
    /**
     * Activate theme
     */
    private function activate_theme($assoc_args) {
        $theme = 'compass';
        
        if (wp_get_theme($theme)->exists()) {
            $this->run_wp_command("theme activate $theme");
        } else {
            WP_CLI::warning("Theme '$theme' not found, skipping activation");
        }
    }
    
    /**
     * Install required plugins
     */
    private function install_plugins($assoc_args) {
        $plugins_to_install = ['wordpress-importer'];
        
        foreach ($plugins_to_install as $plugin) {
            if (!is_plugin_active($plugin) && !file_exists(WP_PLUGIN_DIR . '/' . $plugin)) {
                $this->run_wp_command("plugin install $plugin");
            }
        }
    }
    
    /**
     * Activate all plugins
     */
    private function activate_plugins($assoc_args) {
        $this->run_wp_command('plugin activate --all');
    }
    
    /**
     * Configure WordPress settings
     */
    private function configure_settings($assoc_args) {
        $settings = [
            'timezone_string' => 'America/New_York',
            'start_of_week' => '0',
            'uploads_use_yearmonth_folders' => '0',
        ];
        
        foreach ($settings as $option => $value) {
            update_option($option, $value);
        }
        
        // Update default category
        wp_update_term(1, 'category', [
            'name' => 'General',
            'slug' => 'general'
        ]);
    }
    
    /**
     * Setup permalinks
     */
    private function setup_permalinks($assoc_args) {
        $this->run_wp_command("rewrite structure '/%postname%/'");
        $this->run_wp_command('rewrite flush');
    }
    
    /**
     * Import media files
     */
    private function import_media($assoc_args) {
        $media_files = [
            'logo' => './setup/data/images/logo.png',
            'icon' => './setup/data/images/icon.png',
            'placeholder' => './setup/data/images/placeholder-image-canyon.jpg'
        ];
        
        foreach ($media_files as $type => $file) {
            if (!file_exists($file)) {
                WP_CLI::warning("Media file not found: $file");
                continue;
            }
            
            $result = $this->run_wp_command("media import $file --porcelain", [
                'return' => true,
                'launch' => false
            ]);
            
            if ($result && is_numeric(trim($result))) {
                $attachment_id = trim($result);
                
                switch ($type) {
                    case 'logo':
                        update_option('site_logo', $attachment_id);
                        WP_CLI::debug("Logo imported (ID: $attachment_id)");
                        break;
                    case 'icon':
                        update_option('site_icon', $attachment_id);
                        WP_CLI::debug("Icon imported (ID: $attachment_id)");
                        break;
                }
            }
        }
    }
    
    /**
     * Import content from XML files
     */
    private function import_content($assoc_args) {
        // Same folder setup-bn-project imports from.
        $import_files = glob('./setup/data/content/*.xml') ?: [];
        
        if ($import_files === []) {
            WP_CLI::warning('No content files found in setup/data/content/');
            return;
        }
        
        foreach ($import_files as $file) {
            $this->run_wp_command('import ' . escapeshellarg($file) . ' --authors=create');
        }
    }
    
    /**
     * Save the starter's setup/data/options.json, with page slugs and the site
     * name filled in.
     */
    private function apply_starter_options($assoc_args) {
        $options = StarterOptions::read('./' . StarterOptions::FILE);
        
        $page_id = static function (string $slug): int {
            $page = get_page_by_path($slug);
            
            return $page ? (int) $page->ID : 0;
        };
        
        foreach (StarterOptions::resolve($options, $page_id, (string) get_option('blogname')) as $name => $value) {
            update_option((string) $name, $value);
        }
    }
    
    /**
     * Give content the featured images in setup/data/featured-images.json.
     */
    private function apply_featured_images($assoc_args) {
        foreach (StarterFeaturedImages::read('./' . StarterFeaturedImages::FILE) as $entry) {
            $post       = get_page_by_path($entry['slug'], OBJECT, $entry['post_type']);
            $attachment = get_page_by_path($entry['attachment'], OBJECT, 'attachment');
            
            if ($post && $attachment) {
                set_post_thumbnail($post->ID, $attachment->ID);
            }
        }
    }
    
    /**
     * Setup home and blog pages
     */
    private function setup_pages($assoc_args) {
        // Find blog page
        $blog_page = get_page_by_path('blog');
        $home_page = get_page_by_path('home');
        
        if ($blog_page && $home_page) {
            update_option('page_for_posts', $blog_page->ID);
            update_option('page_on_front', $home_page->ID);
            update_option('show_on_front', 'page');
            
            // Update home page menu order
            wp_update_post([
                'ID' => $home_page->ID,
                'menu_order' => -99
            ]);
            
            WP_CLI::debug("Set home page (ID: {$home_page->ID}) and blog page (ID: {$blog_page->ID})");
        } else {
            WP_CLI::warning('Could not find home and/or blog pages');
        }
    }
    
    /**
     * Clean default WordPress content
     */
    private function clean_default_content($assoc_args) {
        // Delete "Hello World" post
        $hello_world = get_page_by_path('hello-world', OBJECT, 'post');
        if ($hello_world) {
            wp_delete_post($hello_world->ID, true);
            WP_CLI::debug('Deleted Hello World post');
        }
        
        // Delete "Sample Page"
        $sample_page = get_page_by_path('sample-page', OBJECT, 'page');
        if ($sample_page) {
            wp_delete_post($sample_page->ID, true);
            WP_CLI::debug('Deleted Sample Page');
        }
    }
    
    /**
     * Update URLs in database
     */
    private function update_urls($assoc_args) {
        $new_url = untrailingslashit((string) $assoc_args['site-url']);
        $old_url = untrailingslashit((string) get_option('home'));

        if ($old_url === '' || $old_url === $new_url) {
            return;
        }

        // Arguments as an array: nothing is re-parsed, so URLs are passed verbatim.
        WP_CLI::run_command(['search-replace', $old_url, $new_url], ['all-tables' => true]);
    }
}