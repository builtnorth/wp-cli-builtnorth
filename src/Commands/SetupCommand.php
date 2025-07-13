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
            
            // Run WordPress configuration
            WP_CLI::line('Configuring WordPress...');
            $this->exec('lando wp builtnorth configure');
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
        
        // Composer install
        $progress->tick();
        WP_CLI::line('Installing Composer dependencies...');
        $this->exec('lando composer install');
        
        // Theme & plugin composer
        $progress->tick();
        WP_CLI::line('Installing theme and plugin dependencies...');
        $this->exec('lando composer-all-install');
        
        // NPM install and build
        $progress->tick();
        WP_CLI::line('Installing and building NPM dependencies...');
        $this->exec('lando npm install');
        $this->exec('lando npm start');
        
        $progress->finish();
        WP_CLI::success('All dependencies installed');
    }
    
    /**
     * Install WordPress
     * 
     * @param array $config
     */
    private function install_wordpress($config) {
        WP_CLI::line('Waiting for database to be ready...');
        
        // Wait for database
        $max_attempts = 12;
        $attempt = 0;
        
        while ($attempt < $max_attempts) {
            $result = $this->exec('lando wp db check', false);
            
            if ($result->return_code === 0) {
                WP_CLI::success('Database is ready');
                break;
            }
            
            $attempt++;
            if ($attempt >= $max_attempts) {
                WP_CLI::error('Database connection timeout. Please check your Lando configuration.');
            }
            
            WP_CLI::line('Database not ready yet, waiting...');
            sleep(5);
        }
        
        // Install WordPress
        WP_CLI::line('Installing WordPress...');
        $install_command = sprintf(
            'lando wp core install --url="%s" --title="%s" --admin_user="%s" --admin_password="%s" --admin_email="%s"',
            $config['url'],
            $config['sitename_original'],
            $config['username'],
            $config['password'],
            $config['email']
        );
        
        $this->exec($install_command);
        WP_CLI::success('WordPress installed successfully');
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