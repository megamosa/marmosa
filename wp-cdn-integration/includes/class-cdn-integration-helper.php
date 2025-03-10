<?php
/**
 * The class that provides helper methods for the plugin.
 *
 * @since 1.0.0
 */
class CDN_Integration_Helper {

    /**
     * The logger instance.
     *
     * @since 1.0.0
     * @access protected
     * @var CDN_Integration_Logger $logger
     */
    protected $logger;

    /**
     * Settings array.
     *
     * @since 1.0.0
     * @access protected
     * @var array $settings
     */
    protected $settings;

    /**
     * URL cache for better performance.
     *
     * @since 1.0.0
     * @access protected
     * @var array $url_cache
     */
    protected $url_cache = array();

    /**
     * Initialize the helper.
     *
     * @since 1.0.0
     * @param CDN_Integration_Logger $logger The logger instance.
     */
    public function __construct($logger) {
        $this->logger = $logger;
        $this->settings = get_option('wp_cdn_integration_settings', array());
    }

    /**
     * Check if the plugin is enabled.
     *
     * @since 1.0.0
     * @return bool True if enabled, false otherwise.
     */
    public function is_enabled() {
        return (isset($this->settings['enabled']) && $this->settings['enabled'] === '1');
    }

    /**
     * Check if debug mode is enabled.
     *
     * @since 1.0.0
     * @return bool True if debug mode is enabled, false otherwise.
     */
    public function is_debug_enabled() {
        return (isset($this->settings['debug_mode']) && $this->settings['debug_mode'] === '1');
    }

    /**
     * Get GitHub username.
     *
     * @since 1.0.0
     * @return string GitHub username.
     */
    public function get_github_username() {
        return isset($this->settings['github_username']) ? trim($this->settings['github_username']) : '';
    }

    /**
     * Get GitHub repository name.
     *
     * @since 1.0.0
     * @return string GitHub repository name.
     */
    public function get_github_repository() {
        return isset($this->settings['github_repository']) ? trim($this->settings['github_repository']) : '';
    }

    /**
     * Get GitHub branch name.
     *
     * @since 1.0.0
     * @return string GitHub branch name.
     */
    public function get_github_branch() {
        $branch = isset($this->settings['github_branch']) ? trim($this->settings['github_branch']) : '';
        return $branch ?: 'main';
    }

    /**
     * Get GitHub token.
     *
     * @since 1.0.0
     * @return string GitHub token.
     */
    public function get_github_token() {
        return isset($this->settings['github_token']) ? trim($this->settings['github_token']) : '';
    }

    /**
     * Get file types to be served via CDN.
     *
     * @since 1.0.0
     * @return array File types.
     */
    public function get_file_types() {
        $types = isset($this->settings['file_types']) ? $this->settings['file_types'] : 'css,js';
        if (is_string($types)) {
            return explode(',', $types);
        }
        return $types;
    }

    /**
     * Get excluded paths.
     *
     * @since 1.0.0
     * @return array Excluded paths.
     */
    public function get_excluded_paths() {
        $paths = isset($this->settings['excluded_paths']) ? $this->settings['excluded_paths'] : '';
        if (empty($paths)) {
            return array();
        }
        return array_map('trim', explode("\n", $paths));
    }

    /**
     * Get CDN base URL.
     *
     * @since 1.0.0
     * @return string CDN base URL.
     */
    public function get_cdn_base_url() {
        $username = $this->get_github_username();
        $repository = $this->get_github_repository();
        $branch = $this->get_github_branch();
        
        if (empty($username) || empty($repository) || empty($branch)) {
            return '';
        }
        
        return sprintf(
            'https://cdn.jsdelivr.net/gh/%s/%s@%s/',
            $username,
            $repository,
            $branch
        );
    }

    /**
     * Get custom URLs to serve via CDN.
     *
     * @since 1.0.0
     * @return array Custom URLs.
     */
    public function get_custom_urls() {
        $url_string = isset($this->settings['custom_urls']) ? $this->settings['custom_urls'] : '';
        if (empty($url_string)) {
            return array();
        }
        
        // Split by newlines and preserve exact order
        $urls = preg_split('/\r\n|\r|\n/', $url_string);
        if (!is_array($urls)) {
            return array();
        }
        
        // Clean up the array while preserving order
        $result = array();
        foreach ($urls as $url) {
            $url = trim($url);
            if (!empty($url)) {
                $result[] = $url;
            }
        }
        
        return $result;
    }

    /**
     * Get WordPress uploads directory info.
     *
     * @since 1.0.0
     * @return array Uploads directory info.
     */
    public function get_uploads_info() {
        $upload_dir = wp_upload_dir();
        return array(
            'basedir' => $upload_dir['basedir'],
            'baseurl' => $upload_dir['baseurl'],
            'url' => $upload_dir['url'],
            'path' => $upload_dir['path']
        );
    }

    /**
     * Get WordPress content directory.
     *
     * @since 1.0.0
     * @return string Content directory path.
     */
    public function get_content_dir() {
        return WP_CONTENT_DIR;
    }

    /**
     * Get WordPress content URL.
     *
     * @since 1.0.0
     * @return string Content directory URL.
     */
    public function get_content_url() {
        return content_url();
    }

    /**
     * Check if a path is excluded from CDN.
     *
     * @since 1.0.0
     * @param string $path The path to check.
     * @return bool True if excluded, false otherwise.
     */
    public function is_excluded_path($path) {
        $excluded_paths = $this->get_excluded_paths();
        if (empty($excluded_paths)) {
            return false;
        }
        
        foreach ($excluded_paths as $excluded_path) {
            if (empty($excluded_path)) {
                continue;
            }
            
            // Check for wildcard at the end
            if (substr($excluded_path, -1) === '*') {
                $base_path = substr($excluded_path, 0, -1);
                if (strpos($path, $base_path) === 0) {
                    return true;
                }
            } elseif ($path === $excluded_path) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if file extension should be served via CDN.
     *
     * @since 1.0.0
     * @param string $file The file path.
     * @return bool True if should be served via CDN, false otherwise.
     */
    public function is_valid_file_type($file) {
        $file_types = $this->get_file_types();
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        
        return in_array(strtolower($ext), $file_types);
    }

    /**
     * Log messages if debug mode is enabled.
     *
     * @since 1.0.0
     * @param string $message The message to log.
     * @param string $level   Optional. The log level. Default is 'info'. Accepts 'info', 'debug', 'warning', 'error'.
     */
    public function log($message, $level = 'info') {
        if ($this->is_debug_enabled() || $level === 'error') {
            $this->logger->log($message, $level);
        }
    }

    /**
     * Get local file path for a URL.
     *
     * @since 1.0.0
     * @param string $url The URL.
     * @return string Local file path.
     */
    public function get_local_path_for_url($url) {
        // Normalize URL by removing domain if present
        $normalized_url = $url;
        if (strpos($url, 'http') === 0) {
            $parsed_url = parse_url($url);
            if (isset($parsed_url['path'])) {
                $normalized_url = $parsed_url['path'];
            }
        }
        
        // Get site URL parts
        $site_url = trailingslashit(site_url());
        $parsed_site_url = parse_url($site_url);
        $site_path = isset($parsed_site_url['path']) ? $parsed_site_url['path'] : '/';
        
        // Remove site path prefix if present
        if ($site_path !== '/' && strpos($normalized_url, $site_path) === 0) {
            $normalized_url = substr($normalized_url, strlen($site_path) - 1);
        }
        
        // Get uploads info
        $uploads = $this->get_uploads_info();
        $upload_dir_baseurl = trailingslashit($uploads['baseurl']);
        $upload_dir_basedir = trailingslashit($uploads['basedir']);
        
        // Check for uploads URL
        $upload_url_pattern = parse_url($upload_dir_baseurl, PHP_URL_PATH);
        if (!empty($upload_url_pattern) && strpos($normalized_url, $upload_url_pattern) === 0) {
            $rel_path = substr($normalized_url, strlen($upload_url_pattern));
            return $upload_dir_basedir . $rel_path;
        }
        
        // Check for wp-content
        $content_url = parse_url(content_url('/'), PHP_URL_PATH);
        if (strpos($normalized_url, $content_url) === 0) {
            $rel_path = substr($normalized_url, strlen($content_url));
            return WP_CONTENT_DIR . '/' . $rel_path;
        }
        
        // Check for wp-includes
        $includes_url = parse_url(includes_url('/'), PHP_URL_PATH);
        if (strpos($normalized_url, $includes_url) === 0) {
            $rel_path = substr($normalized_url, strlen($includes_url));
            return ABSPATH . WPINC . '/' . $rel_path;
        }
        
        // Check for theme files
        $theme_url = parse_url(get_template_directory_uri() . '/', PHP_URL_PATH);
        if (strpos($normalized_url, $theme_url) === 0) {
            $rel_path = substr($normalized_url, strlen($theme_url));
            return get_template_directory() . '/' . $rel_path;
        }
        
        // Child theme check
        if (get_stylesheet_directory() !== get_template_directory()) {
            $child_theme_url = parse_url(get_stylesheet_directory_uri() . '/', PHP_URL_PATH);
            if (strpos($normalized_url, $child_theme_url) === 0) {
                $rel_path = substr($normalized_url, strlen($child_theme_url));
                return get_stylesheet_directory() . '/' . $rel_path;
            }
        }
        
        // Check plugins directory
        $plugins_url = parse_url(plugins_url('/'), PHP_URL_PATH);
        if (strpos($normalized_url, $plugins_url) === 0) {
            $rel_path = substr($normalized_url, strlen($plugins_url));
            return WP_PLUGIN_DIR . '/' . $rel_path;
        }
        
        // Fallback to ABSPATH
        return ABSPATH . ltrim($normalized_url, '/');
    }

    /**
     * Get the remote path for a URL.
     *
     * @since 1.0.0
     * @param string $url The URL.
     * @return string Remote path.
     */
    public function get_remote_path_for_url($url) {
        // Normalize URL
        $normalized_url = $url;
        if (strpos($url, 'http') === 0) {
            $parsed_url = parse_url($url);
            if (isset($parsed_url['path'])) {
                $normalized_url = $parsed_url['path'];
            }
        }
        
        // Ensure normalized URL has a leading slash
        if (substr($normalized_url, 0, 1) !== '/') {
            $normalized_url = '/' . $normalized_url;
        }
        
        $site_url = trailingslashit(site_url());
        $parsed_site_url = parse_url($site_url);
        $site_path = isset($parsed_site_url['path']) ? $parsed_site_url['path'] : '/';
        
        // Remove site path prefix if present
        if ($site_path !== '/' && strpos($normalized_url, $site_path) === 0) {
            $normalized_url = substr($normalized_url, strlen($site_path) - 1);
        }
        
        return ltrim($normalized_url, '/');
    }
}