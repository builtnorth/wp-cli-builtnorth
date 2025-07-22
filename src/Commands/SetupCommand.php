<?php

namespace BuiltNorth\CLI\Commands;

use BuiltNorth\CLI\BaseCommand;
use WP_CLI;

/**
 * Standard setup command for WordPress projects
 */
class SetupCommand extends BaseCommand {
    
    /**
     * Command name
     * 
     * @var string
     */
    protected $command_name = 'setup';
    
    /**
     * Requires WordPress
     * 
     * @var bool
     */
    protected $requires_wp = false;
    
    /**
     * Get short description
     * 
     * @return string
     */
    protected function get_shortdesc() {
        return 'Standard WordPress project setup';
    }
    
    /**
     * Bootstrap a new WordPress project
     * 
     * ## OPTIONS
     * 
     * [--name=<name>]
     * : Project name (required)
     * 
     * [--username=<username>]
     * : WordPress admin username (default: admin)
     * 
     * [--password=<password>]
     * : WordPress admin password (required)
     * 
     * [--email=<email>]
     * : WordPress admin email (required)
     * 
     * ## EXAMPLES
     * 
     *     wp builtnorth setup --name="My Project" --email="admin@example.com" --password="secure123"
     * 
     * @when before_wp_load
     */
    public function __invoke($args, $assoc_args) {
        WP_CLI::line('WordPress Setup');
        WP_CLI::line('===============');
        WP_CLI::line('');
        
        // Get values from args
        $name = $assoc_args['name'] ?? WP_CLI::error('Missing --name parameter');
        $username = $assoc_args['username'] ?? 'admin';
        $email = $assoc_args['email'] ?? WP_CLI::error('Missing --email parameter');
        $password = $assoc_args['password'] ?? WP_CLI::error('Missing --password parameter');
        
        // Sanitize project name
        $sitename = strtolower(str_replace(' ', '-', $name));
        $url = "https://{$sitename}.lndo.site";
        
        // Step 1: Install WordPress
        WP_CLI::line('Installing WordPress...');
        
        // First ensure WordPress core files exist
        if (!file_exists('wp/index.php')) {
            WP_CLI::error('WordPress core files not found. Please ensure composer install has run successfully.');
        }
        
        $this->wait_for_db();
        
        // Check if WordPress is already installed
        $result = $this->exec('lando wp core is-installed', false);
        if ($result->return_code === 0) {
            WP_CLI::warning('WordPress appears to be already installed.');
            WP_CLI::line('');
            WP_CLI::line('⚠️  This will RESET the database and delete all existing data!');
            WP_CLI::line('');
            
            $response = $this->prompt('Do you want to continue? [y/N]', 'n');
            
            if (strtolower($response) !== 'y') {
                WP_CLI::line('Setup cancelled.');
                exit(0);
            }
            
            WP_CLI::line('Resetting database...');
            $reset_result = $this->exec('lando wp db reset --yes');
            if ($reset_result->return_code !== 0) {
                WP_CLI::error('Failed to reset database');
            }
        } else {
            // WordPress not installed, ensure database exists
            WP_CLI::line('Ensuring database exists...');
            $db_result = $this->exec('lando wp db create', false);
            if ($db_result->return_code === 0) {
                WP_CLI::success('Database created successfully');
            } else {
                // Check if it's just because database already exists
                $check_result = $this->exec('lando wp db check', false);
                if ($check_result->return_code === 0) {
                    WP_CLI::line('Database already exists and is accessible');
                } else {
                    WP_CLI::error('Database issue. Please check your configuration.');
                }
            }
        }
        
        $install_cmd = sprintf(
            'lando wp core install --url="%s" --title="%s" --admin_user="%s" --admin_password="%s" --admin_email="%s" --skip-email',
            $url,
            $name,
            $username,
            $password,
            $email
        );
        
        WP_CLI::line("Running: $install_cmd");
        
        // Use exec to capture output
        $result = $this->exec($install_cmd);
        
        if ($result->return_code !== 0) {
            WP_CLI::error('WordPress installation failed. Please check the configuration.');
        }
        
        // Verify installation
        WP_CLI::line('Verifying installation...');
        $verify_result = $this->exec('lando wp core is-installed', false);
        if ($verify_result->return_code === 0) {
            WP_CLI::success('WordPress is installed');
        } else {
            WP_CLI::error('WordPress installation verification failed');
        }
        
        // Step 2: Configure WordPress
        WP_CLI::line('Configuring WordPress...');
        
        // Handle theme installation
        $this->handle_theme_installation();
        
        // Activate plugins
        $this->activate_plugins();
        
        // Configure settings
        $this->configure_settings();
        
        // Import content
        $this->import_content();
        
        // Import media
        $this->import_media();
        
        // Setup pages
        $this->setup_pages();
        
        // Success!
        WP_CLI::line('');
        WP_CLI::success('Setup complete!');
        WP_CLI::line('');
        
        // Final verification
        $final_check = $this->exec('lando wp core is-installed', false);
        if ($final_check->return_code === 0) {
            WP_CLI::success('WordPress installation verified!');
            WP_CLI::line('');
            WP_CLI::line("Frontend: {$url}");
            WP_CLI::line("Admin: {$url}/wp/wp-admin");
            WP_CLI::line("Username: {$username}");
            WP_CLI::line("Password: {$password}");
        } else {
            WP_CLI::warning('WordPress may not be properly installed. Please check your site.');
            WP_CLI::line("Try visiting: {$url}");
        }
    }
    
    /**
     * Wait for database to be ready
     */
    private function wait_for_db() {
        $max_attempts = 60; // 2 minutes total
        $attempt = 0;
        
        WP_CLI::line('Waiting for database to be ready...');
        
        while ($attempt < $max_attempts) {
            $result = $this->exec('lando wp db query "SELECT 1"', false);
            
            if ($result->return_code === 0) {
                WP_CLI::success('Database is ready');
                return;
            }
            
            $attempt++;
            
            if ($attempt === 1) {
                WP_CLI::line("Database service is starting up...");
            } elseif ($attempt % 10 === 0) {
                WP_CLI::line("Still waiting... (attempt {$attempt}/{$max_attempts})");
            }
            
            if ($attempt >= $max_attempts) {
                WP_CLI::error('Database connection timeout after 2 minutes. Please check your configuration.');
            }
            
            sleep(2);
        }
    }
    
    /**
     * Handle theme installation
     */
    private function handle_theme_installation() {
        WP_CLI::line('Checking for themes...');
        
        $project_root = getcwd();
        $composer_file = $project_root . '/composer.json';
        $has_composer_themes = false;
        
        // Check if composer has theme packages
        if (file_exists($composer_file)) {
            $composer_json = json_decode(file_get_contents($composer_file), true);
            
            foreach (['require', 'require-dev'] as $section) {
                foreach ($composer_json[$section] ?? [] as $package => $version) {
                    if (strpos($package, '/theme') !== false || 
                        strpos($package, 'wp-content/themes/') !== false ||
                        preg_match('/themes?\//', $package)) {
                        $has_composer_themes = true;
                        WP_CLI::line("Found theme package in composer.json: {$package}");
                        break 2;
                    }
                }
            }
        }
        
        // Check for themes in setup/data/themes
        $themes_source_dir = $project_root . '/setup/data/themes';
        $themes_to_copy = [];
        
        if (is_dir($themes_source_dir)) {
            $themes_to_copy = glob($themes_source_dir . '/*', GLOB_ONLYDIR);
            if (!empty($themes_to_copy)) {
                WP_CLI::line('Found ' . count($themes_to_copy) . ' theme(s) in setup/data/themes/');
            }
        }
        
        // Copy themes from setup/data if found
        $themes_dest_dir = $project_root . '/wp-content/themes';
        foreach ($themes_to_copy as $theme_path) {
            $theme_name = basename($theme_path);
            $dest_path = $themes_dest_dir . '/' . $theme_name;
            
            WP_CLI::line("Copying theme: {$theme_name}");
            
            $copy_result = $this->exec("cp -r {$theme_path} {$dest_path}", false);
            
            if ($copy_result->return_code === 0) {
                WP_CLI::success("Copied theme: {$theme_name}");
            } else {
                WP_CLI::warning("Failed to copy theme {$theme_name}");
            }
        }
        
        // Check what themes are now available
        $available_themes = glob($themes_dest_dir . '/*', GLOB_ONLYDIR);
        $theme_count = count($available_themes);
        
        // Only install default theme if no themes exist
        if (!$has_composer_themes && empty($themes_to_copy) && $theme_count === 0) {
            WP_CLI::line('No themes found, installing default theme...');
            
            $themes_to_try = ['twentytwentyfive', 'twentytwentyfour', 'twentytwentythree'];
            $theme_installed = false;
            
            foreach ($themes_to_try as $theme) {
                $theme_result = $this->exec("lando wp theme install $theme --activate", false);
                if ($theme_result->return_code === 0) {
                    WP_CLI::success("Theme $theme installed and activated");
                    $theme_installed = true;
                    break;
                }
            }
            
            if (!$theme_installed) {
                WP_CLI::warning('Could not install any default theme. You may need to install one manually.');
            }
        } else {
            WP_CLI::line("Found {$theme_count} theme(s) in wp-content/themes/");
            
            // Activate the first available theme
            if ($theme_count > 0) {
                $first_theme = basename($available_themes[0]);
                $activate_result = $this->exec("lando wp theme activate {$first_theme}", false);
                
                if ($activate_result->return_code === 0) {
                    WP_CLI::success("Activated theme: {$first_theme}");
                } else {
                    WP_CLI::warning("Failed to activate theme {$first_theme}");
                }
            }
        }
    }
    
    /**
     * Activate all plugins
     */
    private function activate_plugins() {
        $plugin_count_result = $this->exec('lando wp plugin list --format=count', false);
        $plugin_count = 0;
        if ($plugin_count_result->return_code === 0 && !empty($plugin_count_result->stdout)) {
            $plugin_count = intval(trim($plugin_count_result->stdout));
        }
        
        if ($plugin_count > 0) {
            WP_CLI::line('Activating plugins...');
            $activate_result = $this->exec('lando wp plugin activate --all', false);
            if ($activate_result->return_code !== 0) {
                WP_CLI::warning('Some plugins may not have activated');
            }
        } else {
            WP_CLI::line('No plugins to activate');
        }
    }
    
    /**
     * Configure WordPress settings
     */
    private function configure_settings() {
        WP_CLI::line('Configuring settings...');
        
        $this->exec('lando wp option update timezone_string "America/New_York"', false);
        $this->exec('lando wp option update uploads_use_yearmonth_folders "0"', false);
        $this->exec('lando wp rewrite structure "/%postname%/"', false);
        $this->exec('lando wp rewrite flush', false);
    }
    
    /**
     * Import content from XML files
     */
    private function import_content() {
        $project_root = getcwd();
        $content_dir = $project_root . '/setup/data/content';
        $content_files = [];
        
        if (is_dir($content_dir)) {
            $content_files = glob($content_dir . '/*.xml');
        }
        
        if (!empty($content_files)) {
            WP_CLI::line('Found ' . count($content_files) . ' content file(s) to import...');
            
            // Ensure importer is installed
            $importer_check = $this->exec('lando wp plugin is-installed wordpress-importer', false);
            if ($importer_check->return_code !== 0) {
                $install_result = $this->exec('lando wp plugin install wordpress-importer --activate', false);
                if ($install_result->return_code !== 0) {
                    WP_CLI::warning('Failed to install importer. Skipping content import');
                    return;
                }
            } else {
                $this->exec('lando wp plugin activate wordpress-importer', false);
            }
            
            // Import each XML file
            foreach ($content_files as $file) {
                WP_CLI::line('Importing: ' . basename($file));
                $import_result = $this->exec("lando wp import {$file} --authors=create", false);
                if ($import_result->return_code !== 0) {
                    WP_CLI::warning('Failed to import ' . basename($file));
                } else {
                    WP_CLI::success('Imported ' . basename($file));
                }
            }
            
            // Clean default content
            WP_CLI::line('Cleaning default content...');
            $this->exec('lando wp post delete 1 --force', false); // Hello World
            $this->exec('lando wp post delete 2 --force', false); // Sample Page
            $this->exec('lando wp comment delete 1 --force', false); // Default comment
        } else {
            WP_CLI::line('No content files found in setup/data/content/');
        }
    }
    
    /**
     * Import media files
     */
    private function import_media() {
        $project_root = getcwd();
        $images_dir = $project_root . '/setup/data/images';
        $logo_id = null;
        $icon_id = null;
        
        if (is_dir($images_dir)) {
            $image_extensions = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
            $media_files = [];
            
            foreach ($image_extensions as $ext) {
                $media_files = array_merge($media_files, glob($images_dir . '/*.' . $ext));
            }
            
            if (!empty($media_files)) {
                WP_CLI::line('Importing ' . count($media_files) . ' media file(s)...');
                
                foreach ($media_files as $file) {
                    $media_result = $this->exec("lando wp media import $file --porcelain", false);
                    if ($media_result->return_code !== 0) {
                        WP_CLI::warning('Failed to import media ' . basename($file));
                    } else {
                        $attachment_id = trim($media_result->stdout);
                        $filename = basename($file);
                        
                        // Track logo and icon IDs by filename
                        if (strpos(strtolower($filename), 'logo') !== false && $logo_id === null) {
                            $logo_id = $attachment_id;
                            WP_CLI::success("Imported {$filename} as site logo (ID: {$logo_id})");
                        } elseif (strpos(strtolower($filename), 'icon') !== false && $icon_id === null) {
                            $icon_id = $attachment_id;
                            WP_CLI::success("Imported {$filename} as site icon (ID: {$icon_id})");
                        } else {
                            WP_CLI::success("Imported {$filename} (ID: {$attachment_id})");
                        }
                    }
                }
                
                // Set site logo and icon
                if ($logo_id) {
                    $this->exec("lando wp option update site_logo {$logo_id}", false);
                    WP_CLI::success('Set site logo');
                }
                
                if ($icon_id) {
                    $this->exec("lando wp option update site_icon {$icon_id}", false);
                    WP_CLI::success('Set site icon');
                }
            }
        } else {
            WP_CLI::line('No images directory found at setup/data/images/');
        }
    }
    
    /**
     * Setup home and blog pages
     */
    private function setup_pages() {
        WP_CLI::line('Checking for home and blog pages...');
        $pages_result = $this->exec('lando wp post list --post_type=page --format=json', false);
        
        if ($pages_result->return_code === 0) {
            $pages = json_decode($pages_result->stdout, true);
            $home_id = null;
            $blog_id = null;
            
            foreach ($pages as $page) {
                $slug = strtolower($page['post_name']);
                $title = strtolower($page['post_title']);
                
                if (!$home_id && ($slug === 'home' || $slug === 'homepage' || $title === 'home' || $title === 'homepage')) {
                    $home_id = $page['ID'];
                }
                
                if (!$blog_id && ($slug === 'blog' || $slug === 'news' || $title === 'blog' || $title === 'news')) {
                    $blog_id = $page['ID'];
                }
            }
            
            if ($home_id || $blog_id) {
                WP_CLI::line('Configuring page settings...');
                
                if ($home_id && $blog_id) {
                    $this->exec("lando wp option update page_for_posts {$blog_id}", false);
                    $this->exec("lando wp option update page_on_front {$home_id}", false);
                    $this->exec("lando wp option update show_on_front page", false);
                    WP_CLI::success("Set home page (ID: {$home_id}) and blog page (ID: {$blog_id})");
                } elseif ($home_id) {
                    $this->exec("lando wp option update page_on_front {$home_id}", false);
                    $this->exec("lando wp option update show_on_front page", false);
                    WP_CLI::success("Set home page (ID: {$home_id})");
                }
            }
        }
    }
}