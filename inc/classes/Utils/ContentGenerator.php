<?php

namespace BuiltNorth\CLI\Utils;

/**
 * Content generation utilities
 */
class ContentGenerator {
    
    /**
     * Lorem ipsum paragraphs
     * 
     * @var array
     */
    private static $lorem_paragraphs = [
        'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.',
        'Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.',
        'Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque laudantium, totam rem aperiam, eaque ipsa quae ab illo inventore veritatis et quasi architecto beatae vitae dicta sunt explicabo.',
        'Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit, sed quia consequuntur magni dolores eos qui ratione voluptatem sequi nesciunt.',
        'Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet, consectetur, adipisci velit, sed quia non numquam eius modi tempora incidunt ut labore et dolore magnam aliquam quaerat voluptatem.',
    ];
    
    /**
     * Business names for realistic content
     * 
     * @var array
     */
    private static $business_names = [
        'Acme Corporation', 'Global Industries', 'Premier Solutions', 'Elite Enterprises', 'Summit Group',
        'Apex Systems', 'Pinnacle Holdings', 'Zenith Partners', 'Prime Ventures', 'Alpha Innovations',
        'Omega Services', 'Delta Technologies', 'Sigma Consulting', 'Phoenix Development', 'Horizon Properties'
    ];
    
    /**
     * Location names
     * 
     * @var array
     */
    private static $locations = [
        'Downtown', 'Uptown', 'Midtown', 'Riverside', 'Lakeside', 'Hillside', 'Westside', 'Eastside',
        'North End', 'South End', 'City Center', 'Business District', 'Tech Park', 'Industrial Zone', 'Harbor District'
    ];
    
    /**
     * Generate a title
     * 
     * @param string $prefix
     * @param int $index
     * @param string $type
     * @return string
     */
    public static function generate_title($prefix, $index, $type = 'default') {
        switch ($type) {
            case 'business':
                return self::$business_names[array_rand(self::$business_names)] . ' - ' . self::$locations[array_rand(self::$locations)];
            
            case 'location':
                return $prefix . ' at ' . self::$locations[array_rand(self::$locations)] . ' #' . $index;
            
            case 'numbered':
                return $prefix . ' ' . $index;
            
            default:
                return $prefix . ' #' . $index;
        }
    }
    
    /**
     * Generate content
     * 
     * @param int $paragraphs
     * @param bool $include_headings
     * @return string
     */
    public static function generate_content($paragraphs = 3, $include_headings = true) {
        $content = '';
        
        if ($include_headings) {
            $content .= "<!-- wp:heading -->\n";
            $content .= "<h2>Overview</h2>\n";
            $content .= "<!-- /wp:heading -->\n\n";
        }
        
        // Add paragraphs
        for ($i = 0; $i < $paragraphs; $i++) {
            $content .= "<!-- wp:paragraph -->\n";
            $content .= "<p>" . self::$lorem_paragraphs[$i % count(self::$lorem_paragraphs)] . "</p>\n";
            $content .= "<!-- /wp:paragraph -->\n\n";
            
            // Add subheading between paragraphs
            if ($include_headings && $i == floor($paragraphs / 2)) {
                $content .= "<!-- wp:heading {\"level\":3} -->\n";
                $content .= "<h3>Details</h3>\n";
                $content .= "<!-- /wp:heading -->\n\n";
            }
        }
        
        return $content;
    }
    
    /**
     * Generate excerpt
     * 
     * @return string
     */
    public static function generate_excerpt() {
        $sentences = explode('. ', self::$lorem_paragraphs[0]);
        return $sentences[0] . '.';
    }
    
    /**
     * Generate random meta value
     * 
     * @param string $type
     * @return mixed
     */
    public static function generate_meta_value($type) {
        switch ($type) {
            case 'price':
                return rand(100000, 5000000);
            
            case 'area':
                return rand(1000, 50000);
            
            case 'rooms':
                return rand(1, 20);
            
            case 'year':
                return rand(1990, date('Y'));
            
            case 'boolean':
                return rand(0, 1) ? 'yes' : 'no';
            
            case 'rating':
                return rand(1, 5);
            
            case 'email':
                $names = ['info', 'contact', 'admin', 'support', 'sales'];
                $domains = ['example.com', 'test.com', 'demo.com'];
                return $names[array_rand($names)] . '@' . $domains[array_rand($domains)];
            
            case 'phone':
                return sprintf('(%03d) %03d-%04d', rand(200, 999), rand(200, 999), rand(1000, 9999));
            
            case 'address':
                $streets = ['Main St', 'Oak Ave', 'Elm Dr', 'Park Blvd', 'Market St'];
                return rand(100, 9999) . ' ' . $streets[array_rand($streets)];
            
            default:
                return 'Sample ' . $type . ' ' . rand(1, 100);
        }
    }
    
    /**
     * Get random items from array
     * 
     * @param array $items
     * @param int $count
     * @return array
     */
    public static function random_items($items, $count = 1) {
        if ($count >= count($items)) {
            return $items;
        }
        
        $keys = array_rand($items, $count);
        if ($count === 1) {
            return [$items[$keys]];
        }
        
        return array_intersect_key($items, array_flip($keys));
    }
}