<?php

namespace BuiltNorth\CLI\Commands;

use BuiltNorth\CLI\BaseCommand;
use BuiltNorth\CLI\Traits\FileOperationsTrait;
use WP_CLI;

/**
 * Setup command for initializing BuiltNorth projects
 */
class SetupCommand extends BaseCommand {
    
    use FileOperationsTrait;
    
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
        return 'Initialize a new BuiltNorth project';
    }
    
    /**
     * Initialize a new BuiltNorth project
     * 
     * ## OPTIONS
     * 
     * [--name=<name>]
     * : Project name
     * 
     * [--username=<username>]
     * : WordPress admin username
     * 
     * [--password=<password>]
     * : WordPress admin password
     * 
     * [--email=<email>]
     * : WordPress admin email
     * 
     * [--skip-wordpress]
     * : Skip WordPress installation
     * 
     * ## EXAMPLES
     * 
     *     wp builtnorth setup --name="My Project"
     *     wp builtnorth setup --name="My Project" --skip-wordpress
     * 
     * @when before_wp_load
     */
    public function __invoke($args, $assoc_args) {
        WP_CLI::line($this->get_ascii_art());
        WP_CLI::success('Get started by entering WordPress setup info.');
        
        // Check if WordPress is already installed and warn user
        if (empty($assoc_args['skip-wordpress']) && $this->is_wordpress_installed()) {
            WP_CLI::warning('WordPress appears to be already installed at this location.');
            WP_CLI::warning('Running setup will DELETE all existing data and create a fresh installation.');
            
            // Support --yes flag to skip confirmation
            if (empty($assoc_args['yes'])) {
                $confirm = $this->prompt('Are you sure you want to continue? All data will be lost! [y/N]', 'n');
                if (strtolower($confirm) !== 'y') {
                    WP_CLI::error('Setup cancelled by user.');
                }
            }
            
            WP_CLI::line('');
        }
        
        // Collect project information
        $config = $this->collect_setup_info($assoc_args);
        
        // Generate configuration files
        $this->generate_env_file($config);
        $this->generate_lando_file($config);
        
        // Start Lando
        WP_CLI::line('Starting Lando environment...');
        $this->start_lando();
        
        // Install dependencies
        $this->install_dependencies();
        
        // Install WordPress unless skipped
        if (empty($assoc_args['skip-wordpress'])) {
            $this->install_wordpress($config);
        }
        
        // Display completion message
        $this->display_completion_message($config);
    }
    
    /**
     * Collect setup information
     * 
     * @param array $assoc_args
     * @return array
     */
    private function collect_setup_info($assoc_args) {
        $config = [];
        
        // Site name
        $config['sitename_original'] = $assoc_args['name'] ?? $this->prompt('Site Name');
        $config['sitename'] = $this->sanitize_site_name($config['sitename_original']);
        
        // WordPress credentials
        if (empty($assoc_args['skip-wordpress'])) {
            $config['username'] = $assoc_args['username'] ?? $this->prompt('Username', 'admin');
            $config['password'] = $assoc_args['password'] ?? $this->prompt('Password');
            $config['email'] = $assoc_args['email'] ?? $this->prompt('Email Address', '', function($email) {
                return filter_var($email, FILTER_VALIDATE_EMAIL);
            });
        }
        
        // URL
        $config['url'] = "https://{$config['sitename']}.lndo.site";
        
        return $config;
    }
    
    /**
     * Sanitize site name for use in URLs
     * 
     * @param string $name
     * @return string
     */
    private function sanitize_site_name($name) {
        return strtolower(str_replace(' ', '-', $name));
    }
    
    /**
     * Generate .env file
     * 
     * @param array $config
     */
    private function generate_env_file($config) {
        WP_CLI::line('Generating .env file...');
        
        $this->copy_template(
            '.env.example',
            '.env',
            ['project-name' => $config['sitename']]
        );
    }
    
    /**
     * Generate .lando.yml file
     * 
     * @param array $config
     */
    private function generate_lando_file($config) {
        WP_CLI::line('Generating .lando.yml file...');
        
        $this->copy_template(
            '.lando.example.yml',
            '.lando.yml',
            ['project-name' => $config['sitename']]
        );
    }
    
    /**
     * Start Lando
     */
    private function start_lando() {
        $result = $this->exec('lando start');
        
        if ($result->return_code !== 0) {
            WP_CLI::error('Failed to start Lando. Please check your Docker and Lando installation.');
        }
        
        WP_CLI::success('Lando started successfully');
    }
    
    /**
     * Install dependencies
     */
    private function install_dependencies() {
        $progress = $this->make_progress_bar('Installing dependencies', 3);
        
        // Composer install (this will get the compass theme)
        $progress->tick();
        WP_CLI::line('Installing Composer dependencies (including Compass theme)...');
        $this->exec('lando composer install');
        
        // Theme & plugin composer
        $progress->tick();
        WP_CLI::line('Installing theme and plugin dependencies...');
        $result = $this->exec('lando composer-all-install', false);
        if ($result->return_code !== 0) {
            WP_CLI::warning('Some theme/plugin dependencies may not have installed. This is normal if composer-all-install is not configured.');
        }
        
        // NPM install and build
        $progress->tick();
        WP_CLI::line('Installing and building NPM dependencies...');
        $npm_result = $this->exec('lando npm install', false);
        if ($npm_result->return_code === 0) {
            $this->exec('lando npm run build', false);
        } else {
            WP_CLI::warning('NPM install skipped. You may need to run it manually.');
        }
        
        $progress->finish();
        WP_CLI::success('Dependencies installation completed');
    }
    
    /**
     * Install WordPress
     * 
     * @param array $config
     */
    private function install_wordpress($config) {
        $this->wait_for_database();
        
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
        
        // Install WordPress
        WP_CLI::line('Installing WordPress...');
        $install_command = sprintf(
            'lando wp core install --url="%s" --title="%s" --admin_user="%s" --admin_password="%s" --admin_email="%s" --skip-email',
            $config['url'],
            $config['sitename_original'],
            $config['username'],
            $config['password'],
            $config['email']
        );
        
        $result = $this->exec($install_command);
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
        
        // Configure WordPress
        $this->configure_wordpress($config);
    }
    
    /**
     * Display completion message
     * 
     * @param array $config
     */
    private function display_completion_message($config) {
        WP_CLI::line('');
        WP_CLI::success('Setup complete!');
        
        if (!empty($config['username'])) {
            WP_CLI::line('');
            WP_CLI::line('WordPress login info:');
            WP_CLI::line("URL: http://{$config['sitename']}.lndo.site/wp/wp-admin");
            WP_CLI::line("Username: {$config['username']}");
            WP_CLI::line("Password: {$config['password']}");
            WP_CLI::line("Email: {$config['email']}");
        }
    }
    
    /**
     * Check if WordPress is already installed
     * 
     * @return bool
     */
    private function is_wordpress_installed() {
        // Check if wp-config.php exists
        if (!file_exists('wp-config.php') && !file_exists('../wp-config.php')) {
            return false;
        }
        
        // Try to check if WordPress is installed via WP-CLI
        $result = $this->exec('lando wp core is-installed', false);
        return $result->return_code === 0;
    }
    
    /**
     * Wait for database to be ready
     */
    private function wait_for_database() {
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
                WP_CLI::line('Database service is starting up...');
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
     * Configure WordPress after installation
     * 
     * @param array $config
     */
    private function configure_wordpress($config) {
        WP_CLI::line('Configuring WordPress...');
        
        // Handle theme installation
        $this->handle_theme_installation();
        
        // Activate plugins
        $this->activate_plugins();
        
        // Configure settings
        $this->configure_settings();
        
        // Import content and media
        $this->import_content_and_media();
        
        // Setup home and blog pages
        $this->setup_pages();
        
        // Clean default content
        $this->clean_default_content();
    }
    
    /**
     * Handle theme installation and activation
     */
    private function handle_theme_installation() {
        WP_CLI::line('Setting up theme...');
        
        $project_root = getcwd();
        $themes_dest_dir = $project_root . '/wp-content/themes';
        $compass_theme_path = $themes_dest_dir . '/compass';
        
        // Get the project name from lando config
        $lando_config_path = $project_root . '/.lando.yml';
        $project_name = 'custom-theme'; // default
        
        if (file_exists($lando_config_path)) {
            $lando_config = file_get_contents($lando_config_path);
            if (preg_match('/^name:\s*(.+)$/m', $lando_config, $matches)) {
                $project_name = trim($matches[1]);
            }
        }
        
        $new_theme_path = $themes_dest_dir . '/' . $project_name;
        
        // Check if compass theme exists (should be installed by composer)
        if (is_dir($compass_theme_path)) {
            WP_CLI::line('Found Compass theme. Creating project-specific theme...');
            
            // Copy compass to new theme directory
            if ($compass_theme_path !== $new_theme_path) {
                WP_CLI::line("Copying Compass theme to {$project_name}...");
                $copy_result = $this->exec("cp -r {$compass_theme_path} {$new_theme_path}", false);
                
                if ($copy_result->return_code === 0) {
                    WP_CLI::success("Created theme: {$project_name}");
                    
                    // Remove git references
                    $this->remove_git_references($new_theme_path);
                    
                    // Update theme metadata
                    $this->update_theme_metadata($new_theme_path, $project_name);
                    
                    // Remove original compass theme
                    $this->exec("rm -rf {$compass_theme_path}", false);
                    
                    // Remove compass from composer.json
                    $this->remove_compass_dependency();
                    
                    // Add theme to npm workspaces
                    $this->add_theme_to_workspaces($project_name);
                } else {
                    WP_CLI::warning('Failed to copy Compass theme');
                    $new_theme_path = $compass_theme_path;
                    $project_name = 'compass';
                }
            }
            
            // Activate the theme
            WP_CLI::line("Activating {$project_name} theme...");
            $activate_result = $this->exec("lando wp theme activate {$project_name}", false);
            
            if ($activate_result->return_code === 0) {
                WP_CLI::success("Theme {$project_name} activated successfully");
                return;
            } else {
                WP_CLI::warning("Failed to activate {$project_name} theme");
            }
        } else {
            WP_CLI::warning('Compass theme not found. Please ensure "builtnorth/compass" is in your composer.json');
        }
        
        // Fallback: Check for themes in setup/data/themes
        $themes_source_dir = $project_root . '/setup/data/themes';
        if (is_dir($themes_source_dir)) {
            $themes_to_copy = glob($themes_source_dir . '/*', GLOB_ONLYDIR);
            if (!empty($themes_to_copy)) {
                WP_CLI::line('Copying themes from setup/data/themes...');
                
                foreach ($themes_to_copy as $theme_path) {
                    $theme_name = basename($theme_path);
                    $dest_path = $themes_dest_dir . '/' . $theme_name;
                    
                    if (!is_dir($dest_path)) {
                        $copy_result = $this->exec("cp -r {$theme_path} {$dest_path}", false);
                        if ($copy_result->return_code === 0) {
                            WP_CLI::success("Copied theme: {$theme_name}");
                        }
                    }
                }
            }
        }
        
        // Try to activate any available theme
        $available_themes = glob($themes_dest_dir . '/*', GLOB_ONLYDIR);
        if (!empty($available_themes)) {
            $theme_to_activate = basename($available_themes[0]);
            
            $activate_result = $this->exec("lando wp theme activate {$theme_to_activate}", false);
            if ($activate_result->return_code === 0) {
                WP_CLI::success("Activated theme: {$theme_to_activate}");
            }
        } else {
            WP_CLI::error('No themes found. The Compass theme should be installed via composer.');
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
     * Import content and media
     */
    private function import_content_and_media() {
        $project_root = getcwd();
        
        // Check for content files to import
        $content_dir = $project_root . '/setup/data/content';
        $content_files = [];
        
        if (is_dir($content_dir)) {
            $content_files = glob($content_dir . '/*.xml');
        }
        
        // Import content if XML files exist
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
        } else {
            WP_CLI::line('No content files found in setup/data/content/');
        }
        
        // Import media
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
    
    /**
     * Clean default WordPress content
     */
    private function clean_default_content() {
        WP_CLI::line('Cleaning default content...');
        
        // Delete default posts and pages
        $this->exec('lando wp post delete 1 --force', false); // Hello World post
        $this->exec('lando wp post delete 2 --force', false); // Sample Page
        $this->exec('lando wp comment delete 1 --force', false); // Default comment
    }
    
    /**
     * Remove git references from theme
     * 
     * @param string $theme_path
     */
    private function remove_git_references($theme_path) {
        WP_CLI::line('Removing git references from theme...');
        
        // Remove .git directory
        if (is_dir($theme_path . '/.git')) {
            $this->exec("rm -rf {$theme_path}/.git", false);
        }
        
        // Remove .gitattributes if it exists
        if (file_exists($theme_path . '/.gitattributes')) {
            unlink($theme_path . '/.gitattributes');
        }
        
        // Keep .gitignore - it has important rules for the theme
        
        WP_CLI::success('Git references removed');
    }
    
    /**
     * Update theme metadata
     * 
     * @param string $theme_path
     * @param string $theme_name
     */
    private function update_theme_metadata($theme_path, $theme_name) {
        WP_CLI::line('Updating theme metadata...');
        
        // Update style.css
        $style_css_path = $theme_path . '/style.css';
        if (file_exists($style_css_path)) {
            $style_content = file_get_contents($style_css_path);
            
            // Update theme name
            $style_content = preg_replace(
                '/Theme Name:\s*.+/i',
                'Theme Name: ' . ucwords(str_replace('-', ' ', $theme_name)),
                $style_content
            );
            
            // Update text domain
            $style_content = preg_replace(
                '/Text Domain:\s*.+/i',
                'Text Domain: ' . $theme_name,
                $style_content
            );
            
            file_put_contents($style_css_path, $style_content);
        }
        
        // Update package.json if it exists
        $package_json_path = $theme_path . '/package.json';
        if (file_exists($package_json_path)) {
            $package_content = file_get_contents($package_json_path);
            $package_data = json_decode($package_content, true);
            
            if ($package_data) {
                $package_data['name'] = $theme_name;
                
                // Remove repository field if it exists
                unset($package_data['repository']);
                
                file_put_contents(
                    $package_json_path,
                    json_encode($package_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
                );
            }
        }
        
        // Update composer.json if it exists
        $composer_json_path = $theme_path . '/composer.json';
        if (file_exists($composer_json_path)) {
            $composer_content = file_get_contents($composer_json_path);
            $composer_data = json_decode($composer_content, true);
            
            if ($composer_data) {
                $composer_data['name'] = 'custom/' . $theme_name;
                
                // Remove repository fields
                unset($composer_data['repositories']);
                unset($composer_data['support']);
                unset($composer_data['homepage']);
                
                file_put_contents(
                    $composer_json_path,
                    json_encode($composer_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
                );
            }
        }
        
        WP_CLI::success('Theme metadata updated');
    }
    
    /**
     * Remove compass dependency from composer.json
     */
    private function remove_compass_dependency() {
        WP_CLI::line('Removing compass dependency from composer.json...');
        
        $composer_json_path = getcwd() . '/composer.json';
        if (file_exists($composer_json_path)) {
            $composer_content = file_get_contents($composer_json_path);
            $composer_data = json_decode($composer_content, true);
            
            if ($composer_data) {
                // Remove from require
                if (isset($composer_data['require']['builtnorth/compass'])) {
                    unset($composer_data['require']['builtnorth/compass']);
                }
                
                // Remove from require-dev
                if (isset($composer_data['require-dev']['builtnorth/compass'])) {
                    unset($composer_data['require-dev']['builtnorth/compass']);
                }
                
                file_put_contents(
                    $composer_json_path,
                    json_encode($composer_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
                );
                
                WP_CLI::success('Removed compass dependency');
                
                // Update composer lock
                WP_CLI::line('Updating composer.lock...');
                $this->exec('lando composer update --lock', false);
            }
        }
    }
    
    /**
     * Add theme to npm workspaces
     * 
     * @param string $theme_name
     */
    private function add_theme_to_workspaces($theme_name) {
        WP_CLI::line('Adding theme to npm workspaces...');
        
        $package_json_path = getcwd() . '/package.json';
        if (file_exists($package_json_path)) {
            $package_content = file_get_contents($package_json_path);
            $package_data = json_decode($package_content, true);
            
            if ($package_data) {
                // Initialize workspaces if not exists
                if (!isset($package_data['workspaces'])) {
                    $package_data['workspaces'] = [];
                }
                
                // Add the new theme to workspaces
                $theme_workspace_path = 'wp-content/themes/' . $theme_name;
                
                // Check if it's already in workspaces
                if (!in_array($theme_workspace_path, $package_data['workspaces'])) {
                    // Remove any existing compass reference
                    $package_data['workspaces'] = array_filter($package_data['workspaces'], function($workspace) {
                        return !str_contains($workspace, 'themes/compass');
                    });
                    
                    // Add the new theme
                    $package_data['workspaces'][] = $theme_workspace_path;
                    
                    // Sort workspaces for consistency
                    sort($package_data['workspaces']);
                    
                    // Update scripts section
                    if (isset($package_data['scripts'])) {
                        // Update watch scripts
                        if (isset($package_data['scripts']['watch:compass-directory'])) {
                            unset($package_data['scripts']['watch:compass-directory']);
                            $package_data['scripts']['watch:' . $theme_name] = 'npm run start -w wp-content/themes/' . $theme_name;
                        }
                        
                        // Update build scripts
                        if (isset($package_data['scripts']['build:compass-directory'])) {
                            unset($package_data['scripts']['build:compass-directory']);
                            $package_data['scripts']['build:' . $theme_name] = 'npm run build -w wp-content/themes/' . $theme_name;
                        }
                        
                        // Update theme-json scripts
                        foreach (['theme-json:compile', 'theme-json:split', 'theme-json:watch'] as $script) {
                            if (isset($package_data['scripts'][$script])) {
                                $package_data['scripts'][$script] = str_replace(
                                    'compass-directory',
                                    $theme_name,
                                    $package_data['scripts'][$script]
                                );
                            }
                        }
                    }
                    
                    // Save the updated package.json
                    file_put_contents(
                        $package_json_path,
                        json_encode($package_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
                    );
                    
                    WP_CLI::success("Added {$theme_workspace_path} to npm workspaces");
                    
                    // Run npm install to setup the workspace
                    WP_CLI::line('Running npm install to setup workspace...');
                    $npm_result = $this->exec('lando npm install', false);
                    
                    if ($npm_result->return_code === 0) {
                        WP_CLI::success('Theme workspace setup complete');
                    } else {
                        WP_CLI::warning('npm install failed. You may need to run it manually.');
                    }
                } else {
                    WP_CLI::line('Theme already in workspaces');
                }
            }
        } else {
            WP_CLI::warning('No package.json found in project root');
        }
    }
    
    /**
     * Get ASCII art
     * 
     * @return string
     */
    private function get_ascii_art() {
        return '
  ____        _ _ _   _   _            _   _     
 |  _ \      (_) | | | \ | |          | | | |    
 | |_) |_   _ _| | |_|  \| | ___  _ __| |_| |__  
 |  _ <| | | | | | __| . ` |/ _ \| \'__| __| \'_ \ 
 | |_) | |_| | | | |_| |\  | (_) | |  | |_| | | |
 |____/ \__,_|_|_|\__|_| \_|\___/|_|   \__|_| |_|
        ';
    }
}