<?php
/**
 * Class for analyzing URLs in a WordPress site.
 *
 * @since 1.0.0
 */
class CDN_Integration_URL_Analyzer {

    /**
     * The helper instance.
     *
     * @since 1.0.0
     * @access protected
     * @var CDN_Integration_Helper $helper
     */
    protected $helper;

    /**
     * Visited URLs cache.
     *
     * @since 1.0.0
     * @access protected
     * @var array $visited_urls
     */
    protected $visited_urls = array();

    /**
     * Discovered assets.
     *
     * @since 1.0.0
     * @access protected
     * @var array $discovered_assets
     */
    protected $discovered_assets = array();

    /**
     * Maximum pages to visit.
     *
     * @since 1.0.0
     * @access protected
     * @var int $max_pages
     */
    protected $max_pages = 5;

    /**
     * Visited pages count.
     *
     * @since 1.0.0
     * @access protected
     * @var int $visited_count
     */
    protected $visited_count = 0;

    /**
     * Initialize the URL analyzer.
     *
     * @since 1.0.0
     * @param CDN_Integration_Helper $helper The helper instance.
     */
    public function __construct($helper) {
        $this->helper = $helper;
    }

    /**
     * Analyze URLs from the site.
     *
     * @since 1.0.0
     * @param string $start_url Optional. Starting URL. If empty, the site URL will be used.
     * @param int    $max_pages Optional. Maximum pages to visit. Default is 5.
     * @return array Array of discovered asset URLs.
     */
    public function analyze($start_url = '', $max_pages = 5) {
        // Reset state
        $this->visited_urls = array();
        $this->discovered_assets = array();
        $this->visited_count = 0;
        $this->max_pages = $max_pages;
        
        if (empty($start_url)) {
            $start_url = home_url('/');
        }
        
        $this->helper->log("Starting URL analysis from: {$start_url}", 'info');
        
        // Start with the homepage
        $this->crawl_page($start_url);
        
        $this->helper->log("Analysis complete. Visited {$this->visited_count} pages, found " . 
            count($this->discovered_assets) . " unique static assets.", 'info');
        
        // Return unique, sorted list of discovered assets
        return $this->get_discovered_assets();
    }

 /**
 * Process URLs pasted directly by the user.
 *
 * @since 1.0.0
 * @param string $urls_text List of URLs, one per line.
 * @return array Array of processed URLs.
 */
public function process_pasted_urls($urls_text) {
    if (empty($urls_text)) {
        return array();
    }
    
    // Split by newlines
    $lines = preg_split('/\r\n|\r|\n/', $urls_text);
    $urls = array();
    
    // Get site URL and path information
    $site_url = site_url();
    $site_host = parse_url($site_url, PHP_URL_HOST);
    $site_path = parse_url($site_url, PHP_URL_PATH);
    $site_path = $site_path ? $site_path : '';
    
    foreach ($lines as $line) {
        $url = trim($line);
        if (!empty($url)) {
            $normalized_url = $this->normalize_url($url, $site_url, $site_host, $site_path);
            if ($normalized_url) {
                $urls[] = $normalized_url;
            } else {
                // If the URL couldn't be normalized but seems to be a local path, add it anyway
                if (strpos($url, '/') === 0 || strpos($url, 'wp-') !== false) {
                    // Make sure URL starts with a slash
                    if (strpos($url, '/') !== 0) {
                        $url = '/' . $url;
                    }
                    $urls[] = $url;
                }
            }
        }
    }
    
    // Remove duplicates and sort
    $urls = array_unique($urls);
    sort($urls);
    
    return $urls;
}

    /**
     * Process and normalize URLs.
     *
     * @since 1.0.0
     * @param array $urls Array of URLs.
     * @return array Array of processed URLs.
     */
    public function process_urls(array $urls) {
        $result = array();
        $site_url = home_url();
        $site_url_parsed = parse_url($site_url);
        $site_domain = isset($site_url_parsed['host']) ? $site_url_parsed['host'] : '';
        
        foreach ($urls as $url) {
            // Skip empty URLs
            if (empty($url)) {
                continue;
            }
            
            // Check if URL is from our domain
            if (strpos($url, 'http') === 0) {
                $url_parsed = parse_url($url);
                
                // Skip URLs from different domains
                if (isset($url_parsed['host']) && $url_parsed['host'] !== $site_domain) {
                    $this->helper->log("Skipping URL from different domain: {$url}", 'debug');
                    continue;
                }
                
                // Extract the path
                if (isset($url_parsed['path'])) {
                    $url = $url_parsed['path'];
                } else {
                    continue;
                }
            }
            
            // Ensure URL starts with a slash
            if (strpos($url, '/') !== 0) {
                $url = '/' . $url;
            }
            
            // Filter to keep only relevant static assets
            if ($this->is_static_asset_url($url)) {
                $result[] = $url;
            }
        }
        
        // Remove duplicates and sort
        $result = array_unique($result);
        sort($result);
        
        return $result;
    }

    /**
     * Crawl a page and extract assets and links.
     *
     * @since 1.0.0
     * @param string $url Page URL to crawl.
     */
    protected function crawl_page($url) {
        // Skip if we've already visited this URL or reached the limit
        if (in_array($url, $this->visited_urls) || $this->visited_count >= $this->max_pages) {
            return;
        }
        
        $this->helper->log("Crawling page: {$url}", 'debug');
        $this->visited_urls[] = $url;
        $this->visited_count++;
        
        // Fetch the page content
        $content = $this->fetch_url($url);
        if (empty($content)) {
            $this->helper->log("Failed to fetch content from: {$url}", 'warning');
            return;
        }
        
        // Extract and store static assets
        $assets = $this->extract_assets($content);
        foreach ($assets as $asset) {
            if (!in_array($asset, $this->discovered_assets)) {
                $this->discovered_assets[] = $asset;
            }
        }
        
        // Extract links to other pages on the same domain
        $links = $this->extract_links($content, $url);
        
        // Visit each link (depth-first)
        foreach ($links as $link) {
            if ($this->visited_count < $this->max_pages) {
                $this->crawl_page($link);
            } else {
                break;
            }
        }
    }

    /**
     * Fetch URL content.
     *
     * @since 1.0.0
     * @param string $url URL to fetch.
     * @return string Page content.
     */
    protected function fetch_url($url) {
        $args = array(
            'timeout'     => 30,
            'redirection' => 5,
            'sslverify'   => false,
            'user-agent'  => 'WordPress-CDN-Integration/1.0.0',
        );
        
        $response = wp_remote_get($url, $args);
        
        if (is_wp_error($response)) {
            $this->helper->log("Error fetching URL {$url}: " . $response->get_error_message(), 'warning');
            return '';
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code >= 200 && $status_code < 300) {
            return wp_remote_retrieve_body($response);
        }
        
        $this->helper->log("Error fetching URL {$url}: HTTP status {$status_code}", 'warning');
        return '';
    }

   /**
 * Extract static assets from HTML content.
 *
 * @since 1.0.0
 * @param string $content HTML content.
 * @return array Array of asset URLs.
 */
protected function extract_assets($content) {
    $assets = array();
    
    // Get site URL and path information
    $site_url = site_url();
    $site_host = parse_url($site_url, PHP_URL_HOST);
    $site_path = parse_url($site_url, PHP_URL_PATH);
    $site_path = $site_path ? $site_path : '';
    
    // Define common file types to extract
    $extensions = array(
        'js', 'css', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'ico',
        'woff', 'woff2', 'ttf', 'eot', 'otf'
    );
    
    $extensions_pattern = implode('|', $extensions);
    
    // Comprehensive patterns to find all types of static assets
    $patterns = array(
        // CSS links
        '/<link[^>]*href=[\'"]([^\'"]+\.(' . $extensions_pattern . ')(?:\?[^\'"]*)?)[\'"][^>]*>/i',
        
        // JavaScript files
        '/<script[^>]*src=[\'"]([^\'"]+\.(' . $extensions_pattern . ')(?:\?[^\'"]*)?)[\'"][^>]*>/i',
        
        // Images
        '/<img[^>]*src=[\'"]([^\'"]+\.(' . $extensions_pattern . ')(?:\?[^\'"]*)?)[\'"][^>]*>/i',
        
        // Images in srcset attributes (IMPORTANT for responsive images)
        '/<img[^>]*srcset=[\'"]([^\'"]+)[\'"][^>]*>/i',
        
        // Background images in inline styles
        '/style=[\'"][^"\']*background(?:-image)?:\s*url\([\'"]?([^\'")+\s]+)[\'"]?\)[^"\']*[\'"]/',
        
        // Fonts and other assets in CSS url()
        '/url\([\'"]?([^\'")\s]+\.(' . $extensions_pattern . ')(?:\?[^\'"]*)?)[\'"]?\)/i',
        
        // Video and audio sources
        '/<(?:video|audio)[^>]*>.*?<source[^>]*src=[\'"]([^\'"]+\.(' . $extensions_pattern . ')(?:\?[^\'"]*)?)[\'"].*?<\/(?:video|audio)>/is',
        
        // Media in object/embed tags
        '/<(?:object|embed)[^>]*(?:data|src)=[\'"]([^\'"]+\.(' . $extensions_pattern . ')(?:\?[^\'"]*)?)[\'"][^>]*>/i',
        
        // Source in picture tags (IMPORTANT for modern image handling)
        '/<picture[^>]*>.*?<source[^>]*srcset=[\'"]([^\'"]+)[\'"].*?<\/picture>/is',
        
        // Data attributes for modern frameworks (Elementor, Beaver Builder, etc.)
        '/ data-[^=]*=[\'"]([^\'"]+\.(' . $extensions_pattern . ')(?:\?[^\'"]*)?)[\'"][^>]*>/i',
        
        // Preload links
        '/<link[^>]*rel=[\'"]preload[\'"][^>]*href=[\'"]([^\'"]+\.(' . $extensions_pattern . ')(?:\?[^\'"]*)?)[\'"][^>]*>/i',
        
        // WordPress-specific patterns
        '/wp-content\/themes\/[^\/]+\/([^\'"\s]+\.(' . $extensions_pattern . '))/i',
        '/wp-content\/plugins\/[^\/]+\/([^\'"\s]+\.(' . $extensions_pattern . '))/i',
        '/wp-content\/uploads\/([^\'"\s]+\.(' . $extensions_pattern . '))/i',
        '/wp-includes\/([^\'"\s]+\.(' . $extensions_pattern . '))/i',
        
        // General URL patterns in JavaScript (catches dynamic loading)
        '/[\'"]([^\'"\s]+\.(' . $extensions_pattern . ')(?:\?[^\'"]*)?)[\'"]/'
    );
    
    foreach ($patterns as $pattern) {
        preg_match_all($pattern, $content, $matches);
        
        if (isset($matches[1]) && !empty($matches[1])) {
            foreach ($matches[1] as $asset) {
                // Skip data URLs
                if (strpos($asset, 'data:') === 0) {
                    continue;
                }
                
                // Skip blob URLs
                if (strpos($asset, 'blob:') === 0) {
                    continue;
                }
                
                // Handle srcset attributes (multiple URLs)
                if (strpos($pattern, 'srcset') !== false) {
                    $srcset_assets = $this->parse_srcset($asset);
                    foreach ($srcset_assets as $srcset_asset) {
                        $normalized_url = $this->normalize_url($srcset_asset, $site_url, $site_host, $site_path);
                        if ($normalized_url) {
                            $assets[] = $normalized_url;
                        }
                    }
                    continue;
                }
                
                // Normalize the URL
                $normalized_url = $this->normalize_url($asset, $site_url, $site_host, $site_path);
                if ($normalized_url) {
                    $assets[] = $normalized_url;
                }
            }
        }
    }
    
    // Process inline styles for more URLs
    preg_match_all('/<style[^>]*>(.*?)<\/style>/is', $content, $style_matches);
    if (isset($style_matches[1]) && !empty($style_matches[1])) {
        foreach ($style_matches[1] as $style_content) {
            // Find all url() in CSS
            preg_match_all('/url\([\'"]?([^\'")\s]+)[\'"]?\)/i', $style_content, $url_matches);
            if (isset($url_matches[1]) && !empty($url_matches[1])) {
                foreach ($url_matches[1] as $asset) {
                    // Skip data URLs
                    if (strpos($asset, 'data:') === 0) {
                        continue;
                    }
                    
                    // Normalize the URL
                    $normalized_url = $this->normalize_url($asset, $site_url, $site_host, $site_path);
                    if ($normalized_url) {
                        $assets[] = $normalized_url;
                    }
                }
            }
        }
    }
    
    // Process JavaScript for dynamically loaded assets
    preg_match_all('/<script[^>]*>(.*?)<\/script>/is', $content, $script_matches);
    if (isset($script_matches[1]) && !empty($script_matches[1])) {
        foreach ($script_matches[1] as $script_content) {
            // Look for common patterns of loading assets
            $extensions_js_pattern = implode('|', $extensions);
            $js_patterns = array(
                // URL in quotes with file extensions
                '/[\'"]([^\'"]+\.(' . $extensions_js_pattern . ')(?:\?[^\'"]*)?)[\'"]/',
                
                // Common JavaScript methods for loading assets
                '/\.(?:src|href)\s*=\s*[\'"]([^\'"]+\.(' . $extensions_js_pattern . ')(?:\?[^\'"]*)?)[\'"]/',
                
                // Modern frameworks asset loading (Avada Fusion Builder, etc.)
                '/(?:load|fetch|get|ajax)\s*\(\s*[\'"]([^\'"]+\.(' . $extensions_js_pattern . ')(?:\?[^\'"]*)?)[\'"]/'
            );
            
            foreach ($js_patterns as $pattern) {
                preg_match_all($pattern, $script_content, $js_matches);
                if (isset($js_matches[1]) && !empty($js_matches[1])) {
                    foreach ($js_matches[1] as $asset) {
                        // Normalize the URL
                        $normalized_url = $this->normalize_url($asset, $site_url, $site_host, $site_path);
                        if ($normalized_url) {
                            $assets[] = $normalized_url;
                        }
                    }
                }
            }
        }
    }
    
    // Remove duplicates and sort
    $assets = array_unique($assets);
    sort($assets);
    
    return $assets;
}

/**
 * Normalize URL to a consistent format.
 *
 * @since 1.0.0
 * @param string $url The URL to normalize.
 * @param string $site_url The site URL.
 * @param string $site_host The site host.
 * @param string $site_path The site path.
 * @return string|false Normalized URL or false if invalid.
 */
protected function normalize_url($url, $site_url, $site_host, $site_path) {
    // Skip empty URLs
    if (empty($url)) {
        return false;
    }
    
    // Skip data URLs
    if (strpos($url, 'data:') === 0) {
        return false;
    }
    
    // Skip blob URLs
    if (strpos($url, 'blob:') === 0) {
        return false;
    }
    
    // Handle protocol-relative URLs (//example.com/path)
    if (strpos($url, '//') === 0) {
        // If it's a local resource, convert to absolute path
        if (strpos($url, '//' . $site_host) === 0) {
            $url = substr($url, strlen('//' . $site_host));
        } else {
            // External resource, skip
            return false;
        }
    }
    
    // Handle absolute URLs (https://example.com/path)
    if (strpos($url, 'http') === 0) {
        $parsed_url = parse_url($url);
        
        // Skip if from a different domain
        if (!isset($parsed_url['host']) || $parsed_url['host'] !== $site_host) {
            return false;
        }
        
        // Extract the path
        if (isset($parsed_url['path'])) {
            $url = $parsed_url['path'];
        } else {
            return false;
        }
    }
    
    // Handle site subfolder installations
    if (!empty($site_path) && strpos($url, $site_path) === 0) {
        $url = substr($url, strlen($site_path));
    }
    
    // Handle various path formats specific to your case
    // Fix paths like /wor/wp-includes/...
    $install_dir = basename(ABSPATH);
    if (strpos($url, '/' . $install_dir . '/') === 0) {
        $url = substr($url, strlen('/' . $install_dir));
    }
    
    // Ensure URL starts with a slash
    if (strpos($url, '/') !== 0) {
        $url = '/' . $url;
    }
    
    // Check if the URL is a static asset
    $file_extensions = implode('|', $this->helper->get_file_types());
    if (!preg_match('/\.(' . $file_extensions . ')(\?.*)?$/i', $url)) {
        return false;
    }
    
    return $url;
}

/**
 * Parse srcset attribute into individual URLs.
 *
 * @since 1.0.0
 * @param string $srcset The srcset attribute value.
 * @return array Array of image URLs.
 */
protected function parse_srcset($srcset) {
    $urls = array();
    
    // Split the srcset by commas
    $srcset_parts = explode(',', $srcset);
    
    foreach ($srcset_parts as $part) {
        // Extract the URL (ignoring size descriptors)
        preg_match('/^\s*([^\s]+)/', trim($part), $matches);
        
        if (isset($matches[1])) {
            $urls[] = $matches[1];
        }
    }
    
    return $urls;
}
    /**
     * Add asset to the array, normalizing the URL.
     *
     * @since 1.0.0
     * @param string $asset  The asset URL.
     * @param array  &$assets The assets array.
     */
    protected function add_asset($asset, &$assets) {
        // Skip empty URLs, data URLs, and blob URLs
        if (empty($asset) || strpos($asset, 'data:') === 0 || strpos($asset, 'blob:') === 0) {
            return;
        }
        
        // Skip absolute URLs to other domains
        if (strpos($asset, 'http') === 0) {
            $parsed_url = parse_url($asset);
            $site_url = parse_url(home_url());
            
            if (isset($parsed_url['host']) && isset($site_url['host']) && $parsed_url['host'] !== $site_url['host']) {
                return;
            }
            
            // Convert absolute URLs to relative paths
            if (isset($parsed_url['path'])) {
                $asset = $parsed_url['path'];
            } else {
                return;
            }
        }
        
        // Ensure URL starts with a slash
        if (strpos($asset, '/') !== 0) {
            $asset = '/' . $asset;
        }
        
        // Keep only relevant static assets
        if ($this->is_static_asset_url($asset)) {
            $assets[] = $asset;
        }
    }

    /**
     * Extract links to other pages on the same domain.
     *
     * @since 1.0.0
     * @param string $content HTML content.
     * @param string $base_url Base URL for relative links.
     * @return array Array of URLs.
     */
    protected function extract_links($content, $base_url) {
        $links = array();
        $base_url_parsed = parse_url($base_url);
        $base_domain = isset($base_url_parsed['host']) ? $base_url_parsed['host'] : '';
        
        // Find all links
        preg_match_all('/<a[^>]*href=[\'"]([^\'"#]+)[\'"][^>]*>/i', $content, $matches);
        
        if (isset($matches[1]) && !empty($matches[1])) {
            foreach ($matches[1] as $link) {
                // Skip JavaScript links, mailto, tel, etc.
                if (strpos($link, 'javascript:') === 0 || 
                    strpos($link, 'mailto:') === 0 || 
                    strpos($link, 'tel:') === 0 ||
                    strpos($link, '#') === 0) {
                    continue;
                }
                
                // Skip admin, login, and wp-json URLs
                if (strpos($link, '/wp-admin') !== false || 
                    strpos($link, '/wp-login') !== false || 
                    strpos($link, '/wp-json') !== false) {
                    continue;
                }
                
                // Handle relative URLs
                if (strpos($link, 'http') !== 0) {
                    // Handle different relative path formats
                    if (strpos($link, '/') === 0) {
                        // Absolute path relative to domain
                        $domain = isset($base_url_parsed['scheme']) ? $base_url_parsed['scheme'] . '://' . $base_domain : 'http://' . $base_domain;
                        $link = $domain . $link;
                    } else {
                        // Relative to current path
                        $base_path = dirname($base_url);
                        if ($base_path === '.') {
                            $base_path = $base_url;
                        }
                        $link = rtrim($base_path, '/') . '/' . $link;
                    }
                }
                
                // Only include links to the same domain
                $link_parsed = parse_url($link);
                $link_domain = isset($link_parsed['host']) ? $link_parsed['host'] : '';
                
                if ($link_domain === $base_domain) {
                    // Skip if already in visited URLs
                    if (!in_array($link, $this->visited_urls)) {
                        $links[] = $link;
                    }
                }
            }
        }
        
        return $links;
    }

   /**
 * Check if a URL is a static asset that should be processed.
 *
 * @since 1.0.0
 * @param string $url URL to check.
 * @return bool True if it's a static asset URL, false otherwise.
 */
protected function is_static_asset_url($url) {
    // Get supported file types from settings
    $supported_types = $this->helper->get_file_types();
    
    // Check file extension
    $extension = strtolower(pathinfo($url, PATHINFO_EXTENSION));
    
    // Remove query string from extension if present
    if (strpos($extension, '?') !== false) {
        $extension = substr($extension, 0, strpos($extension, '?'));
    }
    
    // Check if the extension is in our supported list
    if (in_array($extension, $supported_types)) {
        return true;
    }
    
    // If no specific extension found, check for common static asset locations
    $asset_patterns = array(
        // WordPress core
        '/wp-content\/themes\/.+\.(' . implode('|', $supported_types) . ')(\?.*)?$/i',
        '/wp-content\/plugins\/.+\.(' . implode('|', $supported_types) . ')(\?.*)?$/i',
        '/wp-content\/uploads\/.+\.(' . implode('|', $supported_types) . ')(\?.*)?$/i',
        '/wp-includes\/.+\.(' . implode('|', $supported_types) . ')(\?.*)?$/i',
        
        // Common asset directories (used by page builders)
        '/assets\/.+\.(' . implode('|', $supported_types) . ')(\?.*)?$/i',
        '/js\/.+\.(' . implode('|', $supported_types) . ')(\?.*)?$/i',
        '/css\/.+\.(' . implode('|', $supported_types) . ')(\?.*)?$/i',
        '/images\/.+\.(' . implode('|', $supported_types) . ')(\?.*)?$/i',
        '/fonts\/.+\.(' . implode('|', $supported_types) . ')(\?.*)?$/i',
        
        // Theme-specific (Avada, etc.)
        '/fusion-styles\/.+\.(' . implode('|', $supported_types) . ')(\?.*)?$/i',
        '/fusion-scripts\/.+\.(' . implode('|', $supported_types) . ')(\?.*)?$/i',
        '/fusion-icons\/.+\.(' . implode('|', $supported_types) . ')(\?.*)?$/i',
        
        // Elementor
        '/elementor\/.+\.(' . implode('|', $supported_types) . ')(\?.*)?$/i',
        
        // WPBakery
        '/js_composer\/.+\.(' . implode('|', $supported_types) . ')(\?.*)?$/i',
        
        // Beaver Builder
        '/bb-plugin\/.+\.(' . implode('|', $supported_types) . ')(\?.*)?$/i',
        
        // General module/block patterns
        '/modules\/.+\.(' . implode('|', $supported_types) . ')(\?.*)?$/i',
        '/blocks\/.+\.(' . implode('|', $supported_types) . ')(\?.*)?$/i'
    );
    
    foreach ($asset_patterns as $pattern) {
        if (preg_match($pattern, $url)) {
            return true;
        }
    }
    
    return false;
}
/**
 * Extract image assets specifically from HTML content.
 * This is a dedicated method to find all image files used in the page.
 *
 * @since 1.0.0
 * @param string $content HTML content.
 * @return array Array of image URLs.
 */
protected function extract_image_assets($content) {
    $images = array();
    $site_url = site_url();
    $site_host = parse_url($site_url, PHP_URL_HOST);
    $site_path = parse_url($site_url, PHP_URL_PATH);
    $site_path = $site_path ? $site_path : '';
    
    // Image file extensions
    $image_extensions = array('jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'ico', 'avif');
    $extensions_pattern = implode('|', $image_extensions);
    
    // Image patterns - more comprehensive than general asset extraction
    $patterns = array(
        // Standard img tags
        '/<img[^>]*src=[\'"]([^\'"]+\.(' . $extensions_pattern . ')(?:\?[^\'"]*)?)[\'"][^>]*>/i',
        
        // Images in srcset attributes
        '/<img[^>]*srcset=[\'"]([^\'"]+)[\'"][^>]*>/i',
        
        // Picture sources
        '/<picture[^>]*>.*?<source[^>]*srcset=[\'"]([^\'"]+)[\'"].*?<\/picture>/is',
        
        // Background images in inline styles
        '/style=[\'"][^"\']*background(?:-image)?:\s*url\([\'"]?([^\'")+\s]+\.(' . $extensions_pattern . ')(?:\?[^\'"]*)?)[\'"]?\)/i',
        
        // CSS url() patterns for images
        '/url\([\'"]?([^\'")\s]+\.(' . $extensions_pattern . ')(?:\?[^\'"]*)?)[\'"]?\)/i',
        
        // Theme customizer images
        '/wp-content\/uploads\/.*\.(' . $extensions_pattern . ')(\?.*)?$/i',
        
        // Page builder specific image patterns (Elementor)
        '/data-settings=[\'"][^\'"]*&quot;url&quot;:&quot;([^&]+\.(' . $extensions_pattern . ')(?:\?[^\'"]*)?)[\'"]/',
        
        // Fusion Builder (Avada) image patterns
        '/data-bg=[\'"]([^\'"]+\.(' . $extensions_pattern . ')(?:\?[^\'"]*)?)[\'"]/',
        
        // WooCommerce product images
        '/attachment_id=["\'](\\d+)["\']/',
        
        // WordPress featured images
        '/wp-post-image/',
        
        // Responsive image sources
        '/<source[^>]*srcset=[\'"]([^\'"]+\.(' . $extensions_pattern . ')(?:\?[^\'"]*)?)[\'"][^>]*>/i',
        
        // Lazy-loaded images (common in themes)
        '/data-(?:src|original|lazy-src|lazy)=[\'"]([^\'"]+\.(' . $extensions_pattern . ')(?:\?[^\'"]*)?)[\'"]/'
    );
    
    foreach ($patterns as $pattern) {
        preg_match_all($pattern, $content, $matches);
        
        if (isset($matches[1]) && !empty($matches[1])) {
            foreach ($matches[1] as $image) {
                // Skip data URLs
                if (strpos($image, 'data:') === 0) {
                    continue;
                }
                
                // Handle srcset attributes
                if (strpos($pattern, 'srcset') !== false) {
                    $srcset_images = $this->parse_srcset($image);
                    foreach ($srcset_images as $srcset_image) {
                        $normalized_url = $this->normalize_url($srcset_image, $site_url, $site_host, $site_path);
                        if ($normalized_url) {
                            $images[] = $normalized_url;
                        }
                    }
                    continue;
                }
                
                // Handle regular images
                $normalized_url = $this->normalize_url($image, $site_url, $site_host, $site_path);
                if ($normalized_url) {
                    $images[] = $normalized_url;
                }
            }
        }
    }
    
    // Look for attachment IDs in the content (common in WordPress)
    preg_match_all('/attachment_id=["\'](\\d+)["\']/', $content, $attachment_matches);
    if (!empty($attachment_matches[1])) {
        foreach ($attachment_matches[1] as $attachment_id) {
            $attachment_url = wp_get_attachment_url($attachment_id);
            if ($attachment_url) {
                $normalized_url = $this->normalize_url($attachment_url, $site_url, $site_host, $site_path);
                if ($normalized_url) {
                    $images[] = $normalized_url;
                }
            }
        }
    }
    
    // Special handling for Elementor-specific images
    if (strpos($content, 'elementor') !== false) {
        preg_match_all('/elementor-widget-image/', $content, $elementor_widgets);
        if (!empty($elementor_widgets[0])) {
            // If Elementor is detected, try to get all images from the page via WP functions
            if (function_exists('get_post_meta')) {
                global $post;
                if (isset($post->ID)) {
                    $elementor_data = get_post_meta($post->ID, '_elementor_data', true);
                    if ($elementor_data) {
                        $elementor_data = json_decode($elementor_data, true);
                        $this->extract_elementor_images($elementor_data, $images, $site_url, $site_host, $site_path);
                    }
                }
            }
        }
    }
    
    // Special handling for Fusion Builder (Avada) images
    if (strpos($content, 'fusion-builder') !== false) {
        preg_match_all('/fusion-builder-row/', $content, $fusion_rows);
        if (!empty($fusion_rows[0])) {
            // If Fusion Builder is detected, try to get all images from the page via WP functions
            if (function_exists('get_post_meta')) {
                global $post;
                if (isset($post->ID)) {
                    $fusion_data = get_post_meta($post->ID, '_fusion', true);
                    if ($fusion_data) {
                        $this->extract_fusion_images($fusion_data, $images, $site_url, $site_host, $site_path);
                    }
                }
            }
        }
    }
    
    // Remove duplicates and sort
    $images = array_unique($images);
    sort($images);
    
    return $images;
}

/**
 * Extract images from Elementor data.
 *
 * @since 1.0.0
 * @param array $data Elementor data.
 * @param array &$images Array to add images to.
 * @param string $site_url The site URL.
 * @param string $site_host The site host.
 * @param string $site_path The site path.
 */
protected function extract_elementor_images($data, &$images, $site_url, $site_host, $site_path) {
    if (!is_array($data)) {
        return;
    }
    
    foreach ($data as $element) {
        // Check for image widgets
        if (isset($element['widgetType']) && $element['widgetType'] === 'image') {
            if (isset($element['settings']['image']['url'])) {
                $normalized_url = $this->normalize_url($element['settings']['image']['url'], $site_url, $site_host, $site_path);
                if ($normalized_url) {
                    $images[] = $normalized_url;
                }
            }
        }
        
        // Check for background images
        if (isset($element['settings']['background_image']['url'])) {
            $normalized_url = $this->normalize_url($element['settings']['background_image']['url'], $site_url, $site_host, $site_path);
            if ($normalized_url) {
                $images[] = $normalized_url;
            }
        }
        
        // Recursively check for nested elements
        if (isset($element['elements']) && is_array($element['elements'])) {
            $this->extract_elementor_images($element['elements'], $images, $site_url, $site_host, $site_path);
        }
    }
}

/**
 * Extract images from Fusion Builder (Avada) data.
 *
 * @since 1.0.0
 * @param array $data Fusion Builder data.
 * @param array &$images Array to add images to.
 * @param string $site_url The site URL.
 * @param string $site_host The site host.
 * @param string $site_path The site path.
 */
protected function extract_fusion_images($data, &$images, $site_url, $site_host, $site_path) {
    if (!is_array($data)) {
        return;
    }
    
    // Fusion Builder stores data in various formats, we'll check common patterns
    $image_keys = array(
        'image', 'background_image', 'picture', 'logo', 'icon', 'thumbnail',
        'before_image', 'after_image', 'featured_image'
    );
    
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            $this->extract_fusion_images($value, $images, $site_url, $site_host, $site_path);
        } else if (is_string($value)) {
            // Check if this is an image URL
            if (in_array($key, $image_keys) || strpos($key, 'image') !== false) {
                $normalized_url = $this->normalize_url($value, $site_url, $site_host, $site_path);
                if ($normalized_url) {
                    $images[] = $normalized_url;
                }
            }
        }
    }
}
    /**
     * Get discovered assets.
     *
     * @since 1.0.0
     * @return array Array of discovered asset URLs.
     */
    public function get_discovered_assets() {
        return $this->discovered_assets;
    }

    /**
     * Validate and check if a list of URLs exist.
     *
     * @since 1.0.0
     * @param array $urls Array of URLs to validate.
     * @return array Validation results.
     */
    public function validate_urls(array $urls) {
        $results = array(
            'total' => count($urls),
            'valid' => 0,
            'invalid' => 0,
            'details' => array()
        );
        
        if (empty($urls)) {
            return $results;
        }
        
        foreach ($urls as $url) {
            $local_path = $this->helper->get_local_path_for_url($url);
            
            if (file_exists($local_path)) {
                $results['valid']++;
                $results['details'][] = array(
                    'url' => $url,
                    'status' => 'valid',
                    'path' => $local_path
                );
            } else {
                $results['invalid']++;
                $results['details'][] = array(
                    'url' => $url,
                    'status' => 'invalid',
                    'message' => 'File not found: ' . $local_path
                );
            }
        }
        
        return $results;
    }
}