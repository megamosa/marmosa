<?php
/**
 * Class for rewriting URLs to use the CDN.
 *
 * @since 1.0.0
 */
class CDN_Integration_URL_Rewriter {

    /**
     * The helper instance.
     *
     * @since 1.0.0
     * @access protected
     * @var CDN_Integration_Helper $helper
     */
    protected $helper;

    /**
     * URL cache for better performance.
     *
     * @since 1.0.0
     * @access protected
     * @var array $url_cache
     */
    protected $url_cache = array();

    /**
     * Initialize the URL rewriter.
     *
     * @since 1.0.0
     * @param CDN_Integration_Helper $helper The helper instance.
     */
    public function __construct($helper) {
        $this->helper = $helper;
    }

    /**
     * Add CDN configuration script to the head.
     *
     * @since 1.0.0
     */
    public function add_cdn_config_script() {
        // Do not add to admin area
        if (is_admin()) {
            return;
        }
        
        if (!$this->helper->is_enabled()) {
            return;
        }
        
        $cdn_base_url = $this->helper->get_cdn_base_url();
        if (empty($cdn_base_url)) {
            return;
        }
        
        $config = array(
            'baseUrl' => site_url(),
            'cdnBaseUrl' => $cdn_base_url,
            'fileTypes' => $this->helper->get_file_types(),
            'excludedPaths' => $this->helper->get_excluded_paths()
        );
        
        ?>
        <script type="text/javascript">
        /* WordPress CDN Integration Config */
        window.wpCdnConfig = <?php echo json_encode($config); ?>;
        
        /* Dynamic URL rewriting for late-loaded resources */
        (function() {
            var cdnBaseUrl = "<?php echo esc_js($cdn_base_url); ?>";
            if (!cdnBaseUrl) return;
            
            // Helper function to check if a URL should be rewritten
            function shouldRewriteUrl(url) {
                if (!url) return false;
                
                // Skip if not from our domain
                if (url.indexOf('http') === 0 && url.indexOf(window.wpCdnConfig.baseUrl) !== 0) {
                    return false;
                }
                
                // Skip data URLs
                if (url.indexOf('data:') === 0) {
                    return false;
                }
                
                // Skip admin URLs
                if (url.indexOf('/wp-admin') !== -1 || url.indexOf('/wp-login') !== -1) {
                    return false;
                }
                
                // Get the path part of the URL
                var path = url;
                if (url.indexOf('http') === 0) {
                    var parser = document.createElement('a');
                    parser.href = url;
                    path = parser.pathname;
                }
                
                // Check if path is excluded
                for (var i = 0; i < window.wpCdnConfig.excludedPaths.length; i++) {
                    var excludedPath = window.wpCdnConfig.excludedPaths[i];
                    
                    // Check for wildcard at the end
                    if (excludedPath.slice(-1) === '*') {
                        var basePath = excludedPath.slice(0, -1);
                        if (path.indexOf(basePath) === 0) {
                            return false;
                        }
                    } else if (path === excludedPath) {
                        return false;
                    }
                }
                
                // Check if it's a WordPress content file we should rewrite
                var wpContentRegex = /\/(wp-content|wp-includes)\/.+\.(js|css|png|jpg|jpeg|gif|svg|woff|woff2|ttf|eot)(\?.*)?$/i;
                return wpContentRegex.test(path);
            }
            
            // Helper function to rewrite a URL
            function rewriteUrl(url) {
                if (!shouldRewriteUrl(url)) return url;
                
                var path = url;
                if (url.indexOf('http') === 0) {
                    var parser = document.createElement('a');
                    parser.href = url;
                    path = parser.pathname;
                }
                
                // Remove leading slash for jsDelivr
                path = path.replace(/^\//, '');
                
                return cdnBaseUrl + path;
            }
            
            // Patch for dynamically added script/link elements
            var originalCreateElement = document.createElement;
            document.createElement = function(tagName) {
                var element = originalCreateElement.apply(document, arguments);
                
                if (tagName.toLowerCase() === 'script' || tagName.toLowerCase() === 'link') {
                    var originalSetAttribute = element.setAttribute;
                    element.setAttribute = function(name, value) {
                        if ((name === 'src' || name === 'href') && value && shouldRewriteUrl(value)) {
                            value = rewriteUrl(value);
                        }
                        return originalSetAttribute.call(this, name, value);
                    };
                }
                
                return element;
            };
            
            // Also observe DOM changes to rewrite URLs in dynamically added elements
            if (window.MutationObserver) {
                new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        if (mutation.type === 'childList') {
                            mutation.addedNodes.forEach(function(node) {
                                if (node.tagName) {
                                    // For script and link tags
                                    if (node.tagName.toLowerCase() === 'script' && node.src) {
                                        if (shouldRewriteUrl(node.src)) {
                                            node.src = rewriteUrl(node.src);
                                        }
                                    } else if (node.tagName.toLowerCase() === 'link' && node.href) {
                                        if (shouldRewriteUrl(node.href)) {
                                            node.href = rewriteUrl(node.href);
                                        }
                                    }
                                    
                                    // For img tags
                                    if (node.tagName.toLowerCase() === 'img' && node.src) {
                                        if (shouldRewriteUrl(node.src)) {
                                            node.src = rewriteUrl(node.src);
                                        }
                                    }
                                }
                            });
                        }
                    });
                }).observe(document, { childList: true, subtree: true });
            }
        })();
        </script>
        <?php
    }

    /**
     * Rewrite URLs to use the CDN.
     *
     * @since 1.0.0
     * @param string $url URL to potentially rewrite.
     * @return string Rewritten URL.
     */
    public function rewrite_url($url) {
        // Skip if in admin area
        if (is_admin()) {
            return $url;
        }

        if (!$this->helper->is_enabled()) {
            return $url;
        }
        
        if (empty($url)) {
            return $url;
        }
        
        // Skip admin URLs
        if (strpos($url, '/wp-admin') !== false || strpos($url, '/wp-login') !== false) {
            return $url;
        }
        
        // Check URL cache first
        $cache_key = md5($url);
        if (isset($this->url_cache[$cache_key])) {
            return $this->url_cache[$cache_key];
        }
        
        $cdn_base_url = $this->helper->get_cdn_base_url();
        if (empty($cdn_base_url)) {
            $this->url_cache[$cache_key] = $url;
            return $url;
        }
        
        // Determine if this URL should be served from CDN
        $should_use_cdn = $this->should_use_cdn($url);
        
        if (!$should_use_cdn) {
            $this->url_cache[$cache_key] = $url;
            return $url;
        }
        
        // Get remote path
        $remote_path = $this->helper->get_remote_path_for_url($url);
        
        // Create CDN URL
        $cdn_url = rtrim($cdn_base_url, '/') . '/' . ltrim($remote_path, '/');
        
        // Debug log
        if ($this->helper->is_debug_enabled()) {
            $this->helper->log("Rewrote URL: {$url} to {$cdn_url}", 'debug');
        }
        
        // Cache and return
        $this->url_cache[$cache_key] = $cdn_url;
        return $cdn_url;
    }

    /**
     * Rewrite URLs in content.
     *
     * @since 1.0.0
     * @param string $content Content to rewrite URLs in.
     * @return string Content with rewritten URLs.
     */
    public function rewrite_content_urls($content) {
        // Skip if in admin area
        if (is_admin()) {
            return $content;
        }

        if (!$this->helper->is_enabled() || empty($content)) {
            return $content;
        }
        
        $cdn_base_url = $this->helper->get_cdn_base_url();
        if (empty($cdn_base_url)) {
            return $content;
        }
        
        // Define patterns to find URLs in content
        $patterns = array(
            // Images
            '/<img[^>]*src=[\'"]([^\'"]+)[\'"][^>]*>/i',
            
            // CSS and JS
            '/<link[^>]*href=[\'"]([^\'"]+)[\'"][^>]*>/i',
            '/<script[^>]*src=[\'"]([^\'"]+)[\'"][^>]*>/i',
            
            // Background in style attributes
            '/style=[\'"][^"\']*background(-image)?:\s*url\([\'"]?([^\'")\s]+)[\'"]?\)[^"\']*[\'"]/',
            
            // CSS url() in style tags
            '/<style[^>]*>(.*?)<\/style>/is'
        );
        
        foreach ($patterns as $pattern) {
            // Special handling for CSS styles
            if (strpos($pattern, '<style') === 0) {
                preg_match_all($pattern, $content, $style_matches);
                if (!empty($style_matches[1])) {
                    foreach ($style_matches[1] as $i => $style_content) {
                        // Find all url() in CSS
                        preg_match_all('/url\([\'"]?([^\'")\s]+)[\'"]?\)/i', $style_content, $url_matches);
                        if (!empty($url_matches[1])) {
                            $new_style = $style_content;
                            foreach ($url_matches[1] as $url) {
                                $new_url = $this->rewrite_url($url);
                                if ($new_url !== $url) {
                                    $new_style = str_replace($url, $new_url, $new_style);
                                }
                            }
                            if ($new_style !== $style_content) {
                                $content = str_replace($style_content, $new_style, $content);
                            }
                        }
                    }
                }
                continue;
            }
            
            // Handle background URLs in style attributes
            if (strpos($pattern, 'background') !== false) {
                preg_match_all($pattern, $content, $matches);
                if (!empty($matches[2])) {
                    foreach ($matches[2] as $i => $url) {
                        $new_url = $this->rewrite_url($url);
                        if ($new_url !== $url) {
                            $old_style = $matches[0][$i];
                            $new_style = str_replace($url, $new_url, $old_style);
                            $content = str_replace($old_style, $new_style, $content);
                        }
                    }
                }
                continue;
            }
            
            // Handle standard URL attributes (src, href)
            preg_match_all($pattern, $content, $matches);
            if (!empty($matches[1])) {
                foreach ($matches[1] as $i => $url) {
                    $new_url = $this->rewrite_url($url);
                    if ($new_url !== $url) {
                        $content = str_replace($url, $new_url, $content);
                    }
                }
            }
        }
        
        return $content;
    }

    /**
     * Rewrite image source.
     *
     * @since 1.0.0
     * @param array $image Image data from wp_get_attachment_image_src.
     * @return array Modified image data.
     */
    public function rewrite_image_src($image) {
        // Skip if in admin area
        if (is_admin()) {
            return $image;
        }

        if (!$this->helper->is_enabled() || !is_array($image) || empty($image[0])) {
            return $image;
        }
        
        $image[0] = $this->rewrite_url($image[0]);
        return $image;
    }

    /**
     * Rewrite image srcset.
     *
     * @since 1.0.0
     * @param array $sources Array of image sources.
     * @return array Modified sources.
     */
    public function rewrite_image_srcset($sources) {
        // Skip if in admin area
        if (is_admin()) {
            return $sources;
        }

        if (!$this->helper->is_enabled() || !is_array($sources)) {
            return $sources;
        }
        
        foreach ($sources as &$source) {
            if (isset($source['url'])) {
                $source['url'] = $this->rewrite_url($source['url']);
            }
        }
        
        return $sources;
    }

    /**
     * Determine if a URL should be served from CDN.
     *
     * @since 1.0.0
     * @param string $url URL to check.
     * @return bool True if the URL should be served from CDN, false otherwise.
     */
    protected function should_use_cdn($url) {
        // Skip empty URLs and data URLs
        if (empty($url) || strpos($url, 'data:') === 0) {
            return false;
        }
        
        // Skip admin URLs
        if (strpos($url, '/wp-admin') !== false || strpos($url, '/wp-login') !== false) {
            return false;
        }
        
        // Normalize URL
        $normalized_url = $url;
        if (strpos($url, 'http') === 0) {
            $site_url = site_url();
            
            // Skip external URLs
            if (strpos($url, $site_url) !== 0) {
                return false;
            }
            
            // Extract path from URL
            $parsed_url = parse_url($url);
            if (isset($parsed_url['path'])) {
                $normalized_url = $parsed_url['path'];
            } else {
                return false;
            }
        }
        
        // Check excluded paths
        if ($this->helper->is_excluded_path($normalized_url)) {
            return false;
        }
        
        // Check file extension
        $extension = pathinfo($normalized_url, PATHINFO_EXTENSION);
        if (empty($extension)) {
            return false;
        }
        
        $file_types = $this->helper->get_file_types();
        return in_array(strtolower($extension), $file_types);
    }
}