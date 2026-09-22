<?php

namespace BuiltNorth\CLI;

use WP_CLI;

/**
 * Base command class for BuiltNorth CLI
 * 
 * Provides common functionality and structure for all commands
 */
abstract class BaseCommand {
    
    /**
     * Command name
     * 
     * @var string
     */
    protected $command_name;
    
    /**
     * Whether this command requires WordPress to be loaded
     * 
     * @var bool
     */
    protected $requires_wp = true;
    
    /**
     * Get command registration args
     * 
     * @return array
     */
    public function get_registration_args() {
        $args = [
            'shortdesc' => $this->get_shortdesc(),
        ];
        
        if (!$this->requires_wp) {
            $args['when'] = 'before_wp_load';
        }
        
        return $args;
    }
    
    /**
     * Get command short description
     * 
     * @return string
     */
    abstract protected function get_shortdesc();
    
    /**
     * Execute a shell command with proper error handling
     * 
     * @param string $command Command to execute
     * @param bool $exit_on_error Whether to exit on error
     * @return array Command result
     */
    protected function exec($command, $exit_on_error = true) {
        WP_CLI::debug("Executing: $command");
        
        $result = WP_CLI::launch($command, false, true);
        
        if ($exit_on_error && $result->return_code !== 0) {
            WP_CLI::error("Command failed: $command");
        }
        
        return $result;
    }
    
    /**
     * Check if a file exists in current or parent directory
     * 
     * @param string $filename
     * @return string|false Full path if found, false otherwise
     */
    protected function find_file($filename) {
        if (file_exists($filename)) {
            return realpath($filename);
        }
        
        $parent_path = dirname(getcwd()) . '/' . $filename;
        if (file_exists($parent_path)) {
            return realpath($parent_path);
        }
        
        return false;
    }
    
    /**
     * Copy and process a template file
     * 
     * @param string $source Source file
     * @param string $dest Destination file
     * @param array $replacements Key-value pairs for replacements
     */
    protected function copy_template($source, $dest, $replacements = []) {
        $source_path = $this->find_file($source);
        
        if (!$source_path) {
            WP_CLI::error("Template file not found: $source");
        }
        
        $content = file_get_contents($source_path);
        
        foreach ($replacements as $search => $replace) {
            $content = str_replace($search, $replace, $content);
        }
        
        if (file_put_contents($dest, $content) === false) {
            WP_CLI::error("Failed to write file: $dest");
        }
        
        WP_CLI::success("Created $dest");
    }
    
    /**
     * Prompt for user input with validation
     * 
     * @param string $question
     * @param string $default
     * @param callable $validator Optional validation callback
     * @return string
     */
    protected function prompt($question, $default = '', $validator = null) {
        $prompt = $question;
        if ($default) {
            $prompt .= " [$default]";
        }
        $prompt .= ': ';
        
        do {
            $input = \cli\prompt($prompt, $default);
            
            if ($validator && !$validator($input)) {
                WP_CLI::warning("Invalid input. Please try again.");
                continue;
            }
            
            break;
        } while (true);
        
        return $input;
    }
    
    /**
     * Run a WP-CLI command
     * 
     * @param string $command
     * @param array $options
     * @return mixed
     */
    protected function run_wp_command($command, $options = []) {
        return WP_CLI::runcommand($command, $options);
    }
    
    /**
     * Display a progress bar
     * 
     * @param string $message
     * @param int $count
     * @return \cli\progress\Bar
     */
    protected function make_progress_bar($message, $count) {
        return WP_CLI\Utils\make_progress_bar($message, $count);
    }
}