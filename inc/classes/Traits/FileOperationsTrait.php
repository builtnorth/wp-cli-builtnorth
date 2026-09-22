<?php

namespace BuiltNorth\CLI\Traits;

use WP_CLI;

/**
 * Trait for common file operations
 */
trait FileOperationsTrait {
    
    /**
     * Ensure a directory exists
     * 
     * @param string $path
     * @return bool
     */
    protected function ensure_directory($path) {
        if (!is_dir($path)) {
            if (!mkdir($path, 0755, true)) {
                WP_CLI::error("Failed to create directory: $path");
                return false;
            }
        }
        return true;
    }
    
    /**
     * Safe file write with backup
     * 
     * @param string $file
     * @param string $content
     * @param bool $backup
     * @return bool
     */
    protected function safe_file_write($file, $content, $backup = true) {
        if ($backup && file_exists($file)) {
            $backup_file = $file . '.backup-' . date('Y-m-d-H-i-s');
            if (!copy($file, $backup_file)) {
                WP_CLI::warning("Failed to create backup of $file");
            } else {
                WP_CLI::debug("Created backup: $backup_file");
            }
        }
        
        if (file_put_contents($file, $content) === false) {
            WP_CLI::error("Failed to write file: $file");
            return false;
        }
        
        return true;
    }
    
    /**
     * Read JSON file
     * 
     * @param string $file
     * @return array|null
     */
    protected function read_json_file($file) {
        if (!file_exists($file)) {
            return null;
        }
        
        $content = file_get_contents($file);
        $data = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            WP_CLI::error("Invalid JSON in file: $file");
            return null;
        }
        
        return $data;
    }
    
    /**
     * Write JSON file
     * 
     * @param string $file
     * @param array $data
     * @param bool $pretty
     * @return bool
     */
    protected function write_json_file($file, $data, $pretty = true) {
        $flags = $pretty ? JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES : 0;
        $content = json_encode($data, $flags);
        
        if ($content === false) {
            WP_CLI::error("Failed to encode JSON data");
            return false;
        }
        
        return $this->safe_file_write($file, $content);
    }
}