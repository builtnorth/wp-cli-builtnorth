<?php
/**
 * BuiltNorth WP-CLI Command Registration
 * 
 * @package BuiltNorth\CLI
 */

if (!class_exists('WP_CLI')) {
    return;
}

use BuiltNorth\CLI\Commands\SetupCommand;
use BuiltNorth\CLI\Commands\SetupBnProjectCommand;
use BuiltNorth\CLI\Commands\ConfigureCommand;
use BuiltNorth\CLI\Commands\GenerateCommand;
use BuiltNorth\CLI\Commands\PostTypeSwitchCommand;

// Only register if not already registered
if (!class_exists('BuiltNorth_Command')) {
    
    /**
     * BuiltNorth CLI commands for WordPress development
     *
     * ## EXAMPLES
     *
     *     # Initialize a new project
     *     $ wp builtnorth setup --name="My Project"
     *
     *     # Configure WordPress
     *     $ wp builtnorth configure
     *
     *     # Generate test content
     *     $ wp builtnorth generate --count=20
     *
     * @when before_wp_load
     */
    class BuiltNorth_Command extends WP_CLI_Command {
        
        /**
         * Standard WordPress project setup
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
         * ## EXAMPLES
         *
         *     wp builtnorth setup --name="My Project" --email="admin@example.com" --password="secure123"
         *
         * @when before_wp_load
         */
        public function setup($args, $assoc_args) {
            $command = new SetupCommand();
            $command->__invoke($args, $assoc_args);
        }
        
        /**
         * Initialize a BuiltNorth-specific project with Compass theme
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
         * [--yes]
         * : Skip confirmation prompt for existing installations
         *
         * ## EXAMPLES
         *
         *     wp builtnorth setup-bn-project --name="My Project"
         *     wp builtnorth setup-bn-project --name="My Project" --yes
         *
         * @when before_wp_load
         * @subcommand setup-bn-project
         */
        public function setup_bn_project($args, $assoc_args) {
            $command = new SetupBnProjectCommand();
            $command->__invoke($args, $assoc_args);
        }
        
        /**
         * Configure WordPress for BuiltNorth projects
         *
         * ## OPTIONS
         *
         * [--skip-content]
         * : Skip content import
         *
         * [--skip-media]
         * : Skip media import
         *
         * [--url=<url>]
         * : Override the site URL for search-replace
         *
         * [--yes]
         * : Skip confirmation prompt
         *
         * ## EXAMPLES
         *
         *     wp builtnorth configure
         *     wp builtnorth configure --yes
         *
         * @when after_wp_load
         */
        public function configure($args, $assoc_args) {
            $command = new ConfigureCommand();
            $command->__invoke($args, $assoc_args);
        }
        
        /**
         * Generate test content for development
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
         * ## EXAMPLES
         *
         *     wp builtnorth generate --count=20 --post-type=post
         *
         * @when after_wp_load
         */
        public function generate($args, $assoc_args) {
            $command = new GenerateCommand();
            $command->__invoke($args, $assoc_args);
        }
        
        /**
         * Convert posts from one post type to another
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
         *     wp builtnorth post-type-switch --from=post --to=article
         *     wp builtnorth post-type-switch --from=post --to=news --dry-run
         *
         * @when after_wp_load
         * @subcommand post-type-switch
         */
        public function post_type_switch($args, $assoc_args) {
            $command = new PostTypeSwitchCommand();
            $command->__invoke($args, $assoc_args);
        }
    }
    
    // Register the main command with subcommands
    WP_CLI::add_command('builtnorth', 'BuiltNorth_Command');
}