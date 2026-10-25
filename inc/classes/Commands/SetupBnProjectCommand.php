<?php

namespace BuiltNorth\CLI\Commands;

use BuiltNorth\CLI\BaseCommand;
use BuiltNorth\CLI\Traits\FileOperationsTrait;
use BuiltNorth\CLI\Utils\StarterFeaturedImages;
use BuiltNorth\CLI\Utils\StarterImageTokens;
use BuiltNorth\CLI\Utils\StarterOptions;
use BuiltNorth\CLI\Utils\ThemeRenamer;
use WP_CLI;

/**
 * Setup command for initializing BuiltNorth-specific projects
 */
class SetupBnProjectCommand extends BaseCommand {
    
    use FileOperationsTrait;
    
    /**
     * Command name
     * 
     * @var string
     */
    protected $command_name = 'setup-bn-project';
    
    /**
     * Requires WordPress
     * 
     * @var bool
     */
    protected $requires_wp = false;

    /** Compass is cloned as editable source, then renamed for the project. */
    private const COMPASS_REPO = 'git@github.com:builtnorth/compass.git';

    /** Branch of Compass to start from. */
    private const COMPASS_BRANCH = 'main';

    /**
     * Composer package of a shared, released theme (--theme), or null to clone
     * and rename Compass.
     *
     * @var string|null
     */
    private $theme_package = null;

    /**
     * Folder of the shared theme once installed (wp-content/themes/<slug>).
     *
     * @var string|null
     */
    private $theme_slug = null;

    /** Private Composer index for Built North packages (the theme needs kitaris/framework). */
    private const REGISTRY_URL = 'https://raw.githubusercontent.com/builtnorth/registry/main';
    
    /**
     * Get short description
     * 
     * @return string
     */
    protected function get_shortdesc() {
        return 'BuiltNorth project setup';
    }
    
    /**
     * Initialize a new BuiltNorth-specific project with Compass theme
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
     * [--skip-content]
     * : Don't import the starter pages and example blocks (setup/data/content/)
     *
     * [--skip-media]
     * : Don't import the starter images, site logo and icon (setup/data/images/)
     *
     * [--yes]
     * : Don't ask before replacing an existing WordPress install
     *
     * [--theme=<package>]
     * : Install a shared, released theme from the Built North registry instead
     * of cloning Compass, e.g. builtnorth/kitaris-theme. The theme isn't renamed
     * and updates with `composer update`.
     * 
     * ## EXAMPLES
     * 
     *     wp builtnorth setup-bn-project --name="My Project"
     *     wp builtnorth setup-bn-project --name="My Project" --skip-wordpress
     *     wp builtnorth setup-bn-project --name="My Project" --skip-content --skip-media
     * 
     * @when before_wp_load
     */
    public function __invoke($args, $assoc_args) {
        WP_CLI::line($this->get_ascii_art());
        WP_CLI::success('Get started by entering WordPress setup info.');
        
        // Check if WordPress is already installed and warn user
        $reset_confirmed = false;
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
            $reset_confirmed = true;
            
            WP_CLI::line('');
        }
        
        if (!empty($assoc_args['theme'])) {
            $this->theme_package = $this->validate_theme_package((string) $assoc_args['theme']);
        }
        
        // Collect project information
        $config = $this->collect_setup_info($assoc_args);
        
        // Generate configuration files
        $this->generate_env_file($config);
        $this->generate_lando_file($config);
        $this->generate_auth_file();
        
        // Start Lando
        WP_CLI::line('Starting Lando environment...');
        $this->start_lando();
        
        // Install dependencies
        $this->install_dependencies();
        
        // Install WordPress unless skipped
        if (empty($assoc_args['skip-wordpress'])) {
            $this->install_wordpress($config, $reset_confirmed || !empty($assoc_args['yes']));
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
        
        $config['skip_content'] = !empty($assoc_args['skip-content']);
        $config['skip_media']   = !empty($assoc_args['skip-media']);
        
        return $config;
    }
    
    /**
     * Sanitize site name for use in URLs
     *
     * The result is a hostname label (`{name}.lndo.site`): lowercase ASCII
     * letters, digits and single hyphens, with no hyphen at either end.
     *
     * @param string $name
     * @return string
     */
    private function sanitize_site_name($name) {
        $slug = strtolower(trim((string) $name));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        return trim((string) $slug, '-');
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
        
        $this->generate_salts(getcwd() . '/.env');
    }
    
    /**
     * Replace "KEY=generate" placeholders (salts, SIRATIK_SECRET_CIPHER_KEY)
     * with random values.
     *
     * 64 hex characters: safe unquoted for both phpdotenv and Docker env files.
     *
     * @param string $env_path
     */
    private function generate_salts($env_path) {
        if (!file_exists($env_path)) {
            return;
        }
        
        $content = (string) file_get_contents($env_path);
        $content = (string) preg_replace_callback(
            '/^((?:AUTH|SECURE_AUTH|LOGGED_IN|NONCE)_(?:KEY|SALT)|SIRATIK_SECRET_CIPHER_KEY)=generate$/m',
            static fn ($matches) => $matches[1] . '=' . bin2hex(random_bytes(32)),
            $content
        );
        
        file_put_contents($env_path, $content);
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
     * Generate auth.json for private Built North packages.
     *
     * Composer needs a GitHub token for the private registry and plugin
     * releases. Built from the GitHub CLI login and read by Composer from the
     * project root, which is also the web root: the starter's .gitignore and
     * nginx config must keep auth.json out of git and unserved. Kept (and
     * made owner-only) if it already exists.
     */
    private function generate_auth_file() {
        $auth_path = getcwd() . '/auth.json';

        if (file_exists($auth_path)) {
            chmod($auth_path, 0600);
            WP_CLI::line('Using existing auth.json');
            return;
        }
        
        WP_CLI::line('Generating auth.json from your GitHub CLI login...');
        $result = $this->exec('gh auth token', false);
        $token  = trim((string) $result->stdout);
        
        if ($result->return_code !== 0 || $token === '') {
            WP_CLI::error('Could not read a GitHub token. Run `gh auth login`, or create auth.json yourself (see README).');
        }
        
        $auth = [
            'github-oauth' => ['github.com' => $token],
            'http-basic'   => [
                'raw.githubusercontent.com' => [
                    'username' => 'x-access-token',
                    'password' => $token,
                ],
            ],
        ];
        
        // Owner-only from the moment it exists, not after a world-readable write.
        $umask = umask(0077);
        file_put_contents($auth_path, json_encode($auth, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        umask($umask);
        chmod($auth_path, 0600);

        WP_CLI::success('auth.json created (owner-only; keep it out of git)');
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
        
        // Composer install (WordPress, plugins)
        $progress->tick();
        WP_CLI::line('Installing Composer dependencies...');
        $this->exec('lando composer install');

        // Theme: a shared released theme (--theme), or Compass cloned and renamed
        if ($this->theme_package !== null) {
            $this->install_shared_theme($this->theme_package);
        } else {
            $this->create_project_theme();
        }
        
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
     * @param bool  $reset_confirmed The database reset was already confirmed (prompt or --yes).
     */
    private function install_wordpress($config, $reset_confirmed = false) {
        $this->wait_for_database();
        
        // Check if WordPress is already installed
        $result = $this->exec('lando wp core is-installed', false);
        if ($result->return_code === 0) {
            WP_CLI::warning('WordPress appears to be already installed.');
            WP_CLI::line('');
            WP_CLI::line('⚠️  This will RESET the database and delete all existing data!');
            WP_CLI::line('');
            
            $response = $reset_confirmed ? 'y' : $this->prompt('Do you want to continue? [y/N]', 'n');
            
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
                // Check if it's just because database already exists. `wp db
                // query` uses the mysql client; `wp db check` needs mysqlcheck,
                // which Lando's PHP 8.4 image ships from MariaDB and which fails
                // TLS verification against MySQL's self-signed certificate.
                $check_result = $this->exec('lando wp db query "SELECT 1"', false);
                if ($check_result->return_code === 0) {
                    WP_CLI::line('Database already exists and is accessible');
                } else {
                    WP_CLI::error('Database issue. Please check your configuration.');
                }
            }
        }
        
        // Install WordPress
        WP_CLI::line('Installing WordPress...');
        // Every value is shell-escaped: a "$" or quote in a password or site
        // name would otherwise be expanded or break the command.
        $install_command = sprintf(
            'lando wp core install --url=%s --title=%s --admin_user=%s --admin_password=%s --admin_email=%s --skip-email',
            escapeshellarg($config['url']),
            escapeshellarg($config['sitename_original']),
            escapeshellarg($config['username']),
            escapeshellarg($config['password']),
            escapeshellarg($config['email'])
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
        
        // The admin account's public name (author archives, bylines).
        $display_name = self::admin_display_name((string) $config['sitename_original']);
        $this->exec(sprintf(
            'lando wp user update %s --display_name=%s --nickname=%s',
            escapeshellarg((string) $config['username']),
            escapeshellarg($display_name),
            escapeshellarg($display_name)
        ), false);
        
        // Configure WordPress
        $this->configure_wordpress($config);
    }
    
    /**
     * The admin account's display name: "{Site Name} Team".
     *
     * @param string $site_name
     * @return string
     */
    public static function admin_display_name($site_name) {
        return trim($site_name) . ' Team';
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
            WP_CLI::line("URL: {$config['url']}/app/wp-admin");
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
            // Ask MySQL directly: `wp db query` loads WordPress and fails with
            // "site not installed" until the install that runs right after this.
            $result = $this->exec('lando mysql -e "SELECT 1"', false);
            
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
        $this->import_content_and_media($config['skip_content'], $config['skip_media'], (string) ($config['username'] ?? ''));
        
        // Setup home and blog pages
        $this->setup_pages();
        
        // Clean default content
        $this->clean_default_content();
        
        // Organization, site policies, discussion and reading settings
        $this->apply_starter_options((string) $config['sitename_original']);
    }
    
    /**
     * Handle theme installation and activation
     */
    private function handle_theme_installation() {
        WP_CLI::line('Setting up theme...');
        
        $project_root    = getcwd();
        $themes_dest_dir = $project_root . '/wp-content/themes';
        $project_name    = $this->theme_slug ?? $this->get_project_name();
        
        // The theme was installed in install_dependencies(): a shared theme from
        // Composer (--theme) or the project theme cloned from Compass
        if (is_dir($themes_dest_dir . '/' . $project_name)) {
            WP_CLI::line("Activating {$project_name} theme...");
            $activate_result = $this->exec('lando wp theme activate ' . escapeshellarg($project_name), false);
            
            if ($activate_result->return_code === 0) {
                WP_CLI::success("Theme {$project_name} activated successfully");
                return;
            }
            
            WP_CLI::warning("Failed to activate {$project_name} theme");
        } else {
            WP_CLI::warning("Project theme {$project_name} not found.");
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
                        $copy_result = $this->exec('cp -r ' . escapeshellarg($theme_path) . ' ' . escapeshellarg($dest_path), false);
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
            
            $activate_result = $this->exec('lando wp theme activate ' . escapeshellarg($theme_to_activate), false);
            if ($activate_result->return_code === 0) {
                WP_CLI::success("Activated theme: {$theme_to_activate}");
            }
        } else {
            WP_CLI::error('No themes found. The project theme is cloned from Compass during setup.');
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
     *
     * @param bool   $skip_content Skip setup/data/content/ (--skip-content).
     * @param bool   $skip_media   Skip setup/data/images/ (--skip-media).
     * @param string $username     Admin account the imported content is credited to.
     */
    private function import_content_and_media($skip_content = false, $skip_media = false, $username = '') {
        $project_root = getcwd();
        
        // Check for content files to import
        $content_dir = $project_root . '/setup/data/content';
        $content_files = [];
        
        if ($skip_content) {
            WP_CLI::line('Skipping content import (--skip-content)');
        } elseif (is_dir($content_dir)) {
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
            
            // Credit the content to the admin account setup created, not to the
            // export's author (which would add a second account).
            $authors = $username !== ''
                ? ' --authors=skip --user=' . escapeshellarg($username)
                : ' --authors=create';
            
            // Import each XML file
            foreach ($content_files as $file) {
                WP_CLI::line('Importing: ' . basename($file));
                $import_result = $this->exec('lando wp import ' . escapeshellarg($this->project_relative_path($file)) . $authors, false);
                if ($import_result->return_code !== 0) {
                    WP_CLI::warning('Failed to import ' . basename($file));
                } else {
                    WP_CLI::success('Imported ' . basename($file));
                }
            }
            
            // Only needed for the import.
            $this->exec('lando wp plugin deactivate wordpress-importer', false);
            $this->exec('lando wp plugin delete wordpress-importer', false);
            
            // Core creates this draft on install, with the site's own URL in it:
            // publish it under the compliance hero, its text inside a section.
            $privacy_id = $this->page_id_by_slug('privacy-policy');
            if ($privacy_id > 0) {
                // As the admin: without a user, saving filters the block markup.
                $as_admin = $username !== '' ? ' --user=' . escapeshellarg($username) : '';
                $this->exec('lando wp eval ' . escapeshellarg(self::privacy_policy_php($privacy_id)) . $as_admin, false);
            }
        } elseif (!$skip_content) {
            WP_CLI::line('No content files found in setup/data/content/');
        }
        
        // Import media
        $images_dir = $project_root . '/setup/data/images';
        $logo_id = null;
        $icon_id = null;
        
        if ($skip_media) {
            WP_CLI::line('Skipping media import (--skip-media)');
        } elseif (is_dir($images_dir)) {
            // json: Lottie animation files for the Lottie block.
            $image_extensions = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'json'];
            $media_files = [];
            
            foreach ($image_extensions as $ext) {
                $media_files = array_merge($media_files, glob($images_dir . '/*.' . $ext));
            }
            
            if (!empty($media_files)) {
                WP_CLI::line('Importing ' . count($media_files) . ' media file(s)...');
                
                foreach ($media_files as $file) {
                    $media_result = $this->exec('lando wp media import ' . escapeshellarg($this->project_relative_path($file)) . ' --porcelain', false);
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
                
                $this->apply_featured_images();
                
                // Images inside blocks: {{image:<slug>}} placeholders become URLs.
                $this->exec('lando wp eval ' . escapeshellarg(self::image_tokens_php()), false);
            }
        }
    }
    
    /**
     * Give content the featured images in setup/data/featured-images.json
     * ("[post type:]slug" => image file), once both are imported.
     */
    private function apply_featured_images() {
        foreach (StarterFeaturedImages::read(getcwd() . '/' . StarterFeaturedImages::FILE) as $entry) {
            $post_id       = $this->post_id_by_slug($entry['post_type'], $entry['slug']);
            $attachment_id = $this->post_id_by_slug('attachment', $entry['attachment'], 'inherit');
            
            if ($post_id > 0 && $attachment_id > 0) {
                $this->exec("lando wp post meta update {$post_id} _thumbnail_id {$attachment_id}", false);
                WP_CLI::success("Set the featured image of {$entry['slug']}");
            }
        }
    }
    
    /**
     * PHP (for `wp eval`) that replaces the {{image:<slug>}} placeholders in
     * every post with the imported image's URL (StarterImageTokens), written
     * straight to the posts table so the block markup isn't filtered.
     *
     * @return string
     */
    public static function image_tokens_php() {
        $pattern    = var_export(StarterImageTokens::PATTERN, true);
        $id_pattern = var_export(StarterImageTokens::ID_PATTERN, true);
        
        return <<<PHP
global \$wpdb;
\$like = '%' . \$wpdb->esc_like('{{image') . '%';
\$attachment = static function (\$slug) { \$found = get_posts(['post_type' => 'attachment', 'name' => \$slug, 'post_status' => 'inherit', 'numberposts' => 1]); return \$found ? \$found[0] : null; };
foreach (\$wpdb->get_col(\$wpdb->prepare("SELECT ID FROM {\$wpdb->posts} WHERE post_content LIKE %s", \$like)) as \$id) {
    \$content = (string) get_post_field('post_content', (int) \$id, 'raw');
    \$updated = (string) preg_replace_callback({$pattern}, static function (\$m) use (\$attachment) {
        \$a   = \$attachment(\$m[1]);
        \$url = \$a ? wp_get_attachment_url(\$a->ID) : '';
        return \$url ? \$url : \$m[0];
    }, \$content);
    \$updated = (string) preg_replace_callback({$id_pattern}, static function (\$m) use (\$attachment) {
        \$a = \$attachment(\$m[1]);
        return \$a ? (string) \$a->ID : \$m[0];
    }, \$updated);
    if (\$updated !== \$content) {
        \$wpdb->update(\$wpdb->posts, ['post_content' => \$updated], ['ID' => (int) \$id]);
        clean_post_cache((int) \$id);
    }
}
PHP;
    }
    
    /**
     * PHP (for `wp eval`) that publishes core's Privacy Policy draft with the
     * compliance hero part first and core's text inside a section. Runs once:
     * content that already has a template part is left alone.
     *
     * @param int $page_id
     * @return string
     */
    public static function privacy_policy_php($page_id) {
        $id = (int) $page_id;
        
        return <<<PHP
\$content = (string) get_post_field('post_content', {$id});
if (strpos(\$content, 'wp:template-part') === false) {
    \$content = '<!-- wp:template-part {"slug":"hero-compliance"} /-->' . "\n\n"
        . '<!-- wp:kitaris/section -->' . "\n" . '<div class="wp-block-kitaris-section__inner">' . trim(\$content) . '</div>' . "\n" . '<!-- /wp:kitaris/section -->';
}
wp_update_post(['ID' => {$id}, 'post_content' => \$content, 'post_status' => 'publish']);
PHP;
    }
    
    /**
     * ID of the page with this slug (any status but trash), or 0.
     *
     * @param string $slug
     * @return int
     */
    private function page_id_by_slug($slug) {
        return $this->post_id_by_slug('page', $slug);
    }
    
    /**
     * ID of the post of this type and slug, or 0.
     *
     * @param string $post_type
     * @param string $slug
     * @param string $statuses Comma-separated post statuses.
     * @return int
     */
    private function post_id_by_slug($post_type, $slug, $statuses = 'publish,draft,private,pending') {
        $result = $this->exec(sprintf(
            'lando wp post list --post_type=%s --name=%s --post_status=%s --field=ID --posts_per_page=1',
            escapeshellarg($post_type),
            escapeshellarg($slug),
            escapeshellarg($statuses)
        ), false);
        
        return $result->return_code === 0 ? (int) trim((string) $result->stdout) : 0;
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
            $this->exec('rm -rf ' . escapeshellarg($theme_path . '/.git'), false);
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
                
                // Repositories are root-only: the theme installs as its own root,
                // so it lists the registry itself (for kitaris/framework).
                $composer_data['repositories'] = [
                    ['type' => 'composer', 'url' => self::REGISTRY_URL],
                ];
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
     * Path relative to the project root, for commands that run inside Lando.
     *
     * The container mounts the project at /app and starts there, so a host
     * path (/Users/...) doesn't exist inside it; a relative path does.
     *
     * @param string $path Absolute host path under the project root.
     * @return string
     */
    private function project_relative_path($path) {
        $root = rtrim((string) getcwd(), '/') . '/';
        
        return str_starts_with($path, $root) ? substr($path, strlen($root)) : $path;
    }
    
    /**
     * Project name (theme folder) from .lando.yml.
     *
     * @return string
     */
    private function get_project_name() {
        $lando_config_path = getcwd() . '/.lando.yml';
        
        if (file_exists($lando_config_path)) {
            $lando_config = (string) file_get_contents($lando_config_path);
            // The value only: stop at whitespace or a trailing "# comment".
            if (preg_match('/^name:\s*([^\s#]+)/m', $lando_config, $matches)) {
                return $matches[1];
            }
        }
        
        return 'custom-theme';
    }
    
    /**
     * Validate a --theme value: a Composer package name (vendor/name).
     *
     * @param string $package
     * @return string
     */
    private function validate_theme_package($package) {
        $package = strtolower(trim($package));
        
        if (!preg_match('#^[a-z0-9]([_.-]?[a-z0-9]+)*/[a-z0-9]([_.-]?[a-z0-9]+)*$#', $package)) {
            WP_CLI::error("--theme must be a Composer package name like builtnorth/kitaris-theme (got \"{$package}\").");
        }
        
        return $package;
    }
    
    /**
     * Install a shared, released theme with Composer.
     *
     * The theme comes from its release zip (bundled vendor/) through the
     * registry, into the folder its installer-name sets. It isn't renamed or
     * added to npm workspaces: it ships built and updates with the site.
     *
     * @param string $package Composer package, e.g. builtnorth/kitaris-theme.
     */
    private function install_shared_theme($package) {
        WP_CLI::line("Installing shared theme {$package}...");
        $this->exec('lando composer require ' . escapeshellarg($package . ':*') . ' --no-interaction');
        
        $result = $this->exec('lando composer show ' . escapeshellarg($package) . ' --path', false);
        $this->theme_slug = $this->theme_slug_from_show_path((string) $result->stdout);
        
        if ($this->theme_slug === null) {
            WP_CLI::error("Installed {$package} but couldn't find its theme folder.");
        }
        
        WP_CLI::success("Installed theme: {$this->theme_slug}");
    }
    
    /**
     * Theme folder from `composer show <package> --path` output
     * ("builtnorth/compass /app/wp-content/themes/compass").
     *
     * @param string $output
     * @return string|null
     */
    private function theme_slug_from_show_path($output) {
        if (preg_match('#/wp-content/themes/([^/\s]+)/?\s*$#m', $output, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
    
    /**
     * Create the project theme from Compass.
     *
     * Clones Compass as editable source into wp-content/themes/<project>,
     * removes its git history, renames every form of the Compass name, adds the
     * theme to npm workspaces and installs its Composer dependencies. Never
     * overwrites an existing theme.
     */
    private function create_project_theme() {
        $project_name = $this->get_project_name();
        $theme_path   = getcwd() . '/wp-content/themes/' . $project_name;
        
        if (file_exists($theme_path)) {
            WP_CLI::error("wp-content/themes/{$project_name} already exists. Remove it or choose another project name; setup never overwrites a theme.");
        }
        
        WP_CLI::line('Cloning Compass (' . self::COMPASS_BRANCH . ") into wp-content/themes/{$project_name}...");
        $this->exec(sprintf(
            'git clone --depth 1 --branch %s %s %s',
            escapeshellarg(self::COMPASS_BRANCH),
            escapeshellarg(self::COMPASS_REPO),
            escapeshellarg($theme_path)
        ));
        
        $this->remove_git_references($theme_path);
        
        WP_CLI::line("Renaming Compass to {$project_name}...");
        $renamer   = new ThemeRenamer($project_name);
        $leftovers = $renamer->rename($theme_path);
        // The renamer's slug, not the project name: a reserved name such as
        // "kitaris" becomes "kitaris-theme" so the text domain and package
        // names don't collide with the framework's.
        $this->update_theme_metadata($theme_path, $renamer->slug());
        if ($renamer->slug() !== $project_name) {
            WP_CLI::line("\"{$project_name}\" is reserved by the framework; theme code uses \"{$renamer->slug()}\" (namespace {$renamer->pascal()}, constants {$renamer->upper()}_*).");
        }
        
        if ($leftovers) {
            WP_CLI::warning('These lines still mention "compass" (other themes, icon names or prose). Review them:');
            foreach ($leftovers as $line) {
                WP_CLI::line('  ' . $line);
            }
        }
        WP_CLI::success("Created theme: {$project_name}");
        
        $this->add_theme_to_workspaces($project_name);
        
        WP_CLI::line('Installing theme Composer dependencies...');
        $result = $this->exec('lando composer install --working-dir=' . escapeshellarg('wp-content/themes/' . $project_name), false);
        if ($result->return_code !== 0) {
            WP_CLI::warning("Theme Composer install failed. Run it in wp-content/themes/{$project_name} manually.");
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
                        // Point the starter's compass watch/build scripts at the renamed theme
                        if (isset($package_data['scripts']['watch:compass'])) {
                            unset($package_data['scripts']['watch:compass']);
                            $package_data['scripts']['watch:' . $theme_name] = 'npm run start -w wp-content/themes/' . $theme_name;
                        }
                        
                        // Update build scripts
                        if (isset($package_data['scripts']['build:compass'])) {
                            unset($package_data['scripts']['build:compass']);
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