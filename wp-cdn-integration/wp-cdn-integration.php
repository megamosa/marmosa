<?php
/**
 * Plugin Name: WordPress CDN Integration
 * Plugin URI: https://github.com/magoarab/wordpress-cdn-integration
 * Description: Integrate WordPress with GitHub and jsDelivr CDN for serving static files
 * Version: 1.0.0
 * Author: MagoArab
 * Author URI: https://github.com/magoarab
 * Text Domain: wp-cdn-integration
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('WP_CDN_INTEGRATION_VERSION', '1.0.0');
define('WP_CDN_INTEGRATION_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WP_CDN_INTEGRATION_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WP_CDN_INTEGRATION_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('WP_CDN_INTEGRATION_LOG_FILE', WP_CONTENT_DIR . '/wp-cdn-integration-log.log');

// Include required files
require_once WP_CDN_INTEGRATION_PLUGIN_DIR . 'includes/class-cdn-integration-loader.php';
require_once WP_CDN_INTEGRATION_PLUGIN_DIR . 'includes/class-cdn-integration-logger.php';
require_once WP_CDN_INTEGRATION_PLUGIN_DIR . 'includes/class-cdn-integration-helper.php';
require_once WP_CDN_INTEGRATION_PLUGIN_DIR . 'includes/class-cdn-integration-github-api.php';
require_once WP_CDN_INTEGRATION_PLUGIN_DIR . 'includes/class-cdn-integration-jsdelivr-api.php';
require_once WP_CDN_INTEGRATION_PLUGIN_DIR . 'includes/class-cdn-integration-url-analyzer.php';
require_once WP_CDN_INTEGRATION_PLUGIN_DIR . 'includes/class-cdn-integration-url-rewriter.php';
require_once WP_CDN_INTEGRATION_PLUGIN_DIR . 'admin/class-cdn-integration-admin.php';

/**
 * Begin execution of the plugin
 *
 * @since 1.0.0
 */
function run_wp_cdn_integration() {
    // Initialize the loader
    $plugin_loader = new CDN_Integration_Loader();
    
    // Initialize the logger
    $plugin_logger = new CDN_Integration_Logger();
    
    // Initialize the helper
    $plugin_helper = new CDN_Integration_Helper($plugin_logger);
    
    // Initialize GitHub API
    $github_api = new CDN_Integration_Github_API($plugin_helper);
    
    // Initialize jsDelivr API
    $jsdelivr_api = new CDN_Integration_Jsdelivr_API($plugin_helper);
    
    // Initialize URL analyzer
    $url_analyzer = new CDN_Integration_URL_Analyzer($plugin_helper);
    
    // Initialize URL rewriter
    $url_rewriter = new CDN_Integration_URL_Rewriter($plugin_helper);
    
    // Initialize admin functions if in admin
    if (is_admin()) {
        $plugin_admin = new CDN_Integration_Admin(
            $plugin_helper,
            $github_api,
            $jsdelivr_api,
            $url_analyzer
        );
        
        // Hook the admin init
        $plugin_loader->add_action('admin_init', $plugin_admin, 'admin_init');
        $plugin_loader->add_action('admin_menu', $plugin_admin, 'add_menu_pages');
        $plugin_loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_admin_assets');
        
        // Ajax hooks
        $plugin_loader->add_action('wp_ajax_cdn_test_connection', $plugin_admin, 'ajax_test_connection');
        $plugin_loader->add_action('wp_ajax_cdn_purge_cache', $plugin_admin, 'ajax_purge_cache');
        $plugin_loader->add_action('wp_ajax_cdn_analyze_urls', $plugin_admin, 'ajax_analyze_urls');
        $plugin_loader->add_action('wp_ajax_cdn_upload_to_github', $plugin_admin, 'ajax_upload_to_github');
        $plugin_loader->add_action('wp_ajax_cdn_validate_urls', $plugin_admin, 'ajax_validate_urls');
        $plugin_loader->add_action('wp_ajax_cdn_direct_analyze_urls', $plugin_admin, 'ajax_direct_analyze_urls');
        $plugin_loader->add_action('wp_ajax_cdn_view_log', $plugin_admin, 'ajax_view_log');
        $plugin_loader->add_action('wp_ajax_cdn_update_custom_urls', $plugin_admin, 'ajax_update_custom_urls');
        
        // Settings API hooks
        $plugin_loader->add_action('admin_init', $plugin_admin, 'register_settings');
    }
    
    // Front-end hooks - only apply when not in admin
    if ($plugin_helper->is_enabled() && !is_admin()) {
        $plugin_loader->add_action('wp_head', $url_rewriter, 'add_cdn_config_script', 5);
        
        // Low priority to catch all URLs
        $plugin_loader->add_filter('style_loader_src', $url_rewriter, 'rewrite_url', 9999);
        $plugin_loader->add_filter('script_loader_src', $url_rewriter, 'rewrite_url', 9999);
        $plugin_loader->add_filter('the_content', $url_rewriter, 'rewrite_content_urls', 9999);
        
        // Images and other media
        $plugin_loader->add_filter('wp_get_attachment_url', $url_rewriter, 'rewrite_url', 9999);
        $plugin_loader->add_filter('wp_get_attachment_image_src', $url_rewriter, 'rewrite_image_src', 9999);
        $plugin_loader->add_filter('wp_calculate_image_srcset', $url_rewriter, 'rewrite_image_srcset', 9999);
    }
    
    // Run all the hooks
    $plugin_loader->run();
}

// Fire up the plugin
run_wp_cdn_integration();

// Activation hook
register_activation_hook(__FILE__, 'wp_cdn_integration_activate');
function wp_cdn_integration_activate() {
    // Add default options if not exist
    if (!get_option('wp_cdn_integration_settings')) {
        add_option('wp_cdn_integration_settings', array(
            'enabled' => '0',            // Disabled by default to prevent issues
            'debug_mode' => '0',
            'github_username' => '',
            'github_repository' => '',
            'github_branch' => 'main',
            'github_token' => '',
            'file_types' => 'js,css,png,jpg,jpeg,gif,svg,woff,woff2,ttf,eot',  // More file types by default
            'excluded_paths' => '/wp-admin/*, /wp-login.php', // Exclude admin by default
            'custom_urls' => ''
        ));
    }
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'wp_cdn_integration_deactivate');
function wp_cdn_integration_deactivate() {
    // Clean up if needed
}

/**
 * Helper function to check if we're in an admin page.
 * More comprehensive than the built-in is_admin() which 
 * returns true for AJAX requests.
 *
 * @since 1.0.0
 * @return boolean True if in an admin page, false otherwise.
 */
function wp_cdn_is_admin_page() {
    // Check if it's the admin area
    if (!is_admin()) {
        return false;
    }
    
    // Check if this is an AJAX request from the frontend
    if (defined('DOING_AJAX') && DOING_AJAX) {
        $referer = wp_get_referer();
        if ($referer) {
            $referer_path = parse_url($referer, PHP_URL_PATH);
            if (strpos($referer_path, '/wp-admin/') === false) {
                return false;
            }
        }
    }
    
    return true;
}