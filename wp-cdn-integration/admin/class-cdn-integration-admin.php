<?php
/**
 * Class for WordPress admin functionality.
 *
 * @since 1.0.0
 */
class CDN_Integration_Admin {

    /**
     * The helper instance.
     *
     * @since 1.0.0
     * @access protected
     * @var CDN_Integration_Helper $helper
     */
    protected $helper;

    /**
     * The GitHub API instance.
     *
     * @since 1.0.0
     * @access protected
     * @var CDN_Integration_Github_API $github_api
     */
    protected $github_api;

    /**
     * The jsDelivr API instance.
     *
     * @since 1.0.0
     * @access protected
     * @var CDN_Integration_Jsdelivr_API $jsdelivr_api
     */
    protected $jsdelivr_api;

    /**
     * The URL analyzer instance.
     *
     * @since 1.0.0
     * @access protected
     * @var CDN_Integration_URL_Analyzer $url_analyzer
     */
    protected $url_analyzer;

    /**
     * Initialize the admin.
     *
     * @since 1.0.0
     * @param CDN_Integration_Helper       $helper       The helper instance.
     * @param CDN_Integration_Github_API   $github_api   The GitHub API instance.
     * @param CDN_Integration_Jsdelivr_API $jsdelivr_api The jsDelivr API instance.
     * @param CDN_Integration_URL_Analyzer $url_analyzer The URL analyzer instance.
     */
    public function __construct($helper, $github_api, $jsdelivr_api, $url_analyzer) {
        $this->helper = $helper;
        $this->github_api = $github_api;
        $this->jsdelivr_api = $jsdelivr_api;
        $this->url_analyzer = $url_analyzer;
    }

    /**
     * Initialize admin functionality.
     *
     * @since 1.0.0
     */
    public function admin_init() {
        // Register settings
        $this->register_settings();
    }
	/**
     * Add admin menu pages.
     *
     * @since 1.0.0
     */
    public function add_menu_pages() {
        // Add main menu page
        add_menu_page(
            __('CDN Integration', 'wp-cdn-integration'),
            __('CDN Integration', 'wp-cdn-integration'),
            'manage_options',
            'wp-cdn-integration',
            array($this, 'render_main_page'),
            'dashicons-cloud',
            90
        );
        
        // Add submenu pages
        add_submenu_page(
            'wp-cdn-integration',
            __('Dashboard', 'wp-cdn-integration'),
            __('Dashboard', 'wp-cdn-integration'),
            'manage_options',
            'wp-cdn-integration',
            array($this, 'render_main_page')
        );
        
        add_submenu_page(
            'wp-cdn-integration',
            __('View Log', 'wp-cdn-integration'),
            __('View Log', 'wp-cdn-integration'),
            'manage_options',
            'wp-cdn-integration-log',
            array($this, 'render_log_page')
        );
    }

    /**
     * Enqueue admin assets.
     *
     * @since 1.0.0
     */
    public function enqueue_admin_assets() {
        // Only enqueue on plugin pages
        $current_screen = get_current_screen();
        if (strpos($current_screen->id, 'wp-cdn-integration') === false) {
            return;
        }
        
        // Add jQuery explicitly
        wp_enqueue_script('jquery');
        
        // Add Dashicons
        wp_enqueue_style('dashicons');
        
        // Add admin styles
        wp_enqueue_style(
            'wp-cdn-integration-admin',
            WP_CDN_INTEGRATION_PLUGIN_URL . 'admin/css/admin.css',
            array(),
            WP_CDN_INTEGRATION_VERSION
        );
        
        // Add admin scripts
        wp_enqueue_script(
            'wp-cdn-integration-admin',
            WP_CDN_INTEGRATION_PLUGIN_URL . 'admin/js/admin.js',
            array('jquery'),
            WP_CDN_INTEGRATION_VERSION,
            false
        );
        
        // Localize script
        wp_localize_script('wp-cdn-integration-admin', 'wpCdnAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wp-cdn-integration-nonce'),
            'i18n' => array(
                'testingConnection' => __('Testing connection...', 'wp-cdn-integration'),
                'purgingCache' => __('Purging cache...', 'wp-cdn-integration'),
                'analyzingUrls' => __('Analyzing URLs...', 'wp-cdn-integration'),
                'uploadingFiles' => __('Uploading files...', 'wp-cdn-integration'),
                'validatingUrls' => __('Validating URLs...', 'wp-cdn-integration'),
                'success' => __('Success', 'wp-cdn-integration'),
                'error' => __('Error', 'wp-cdn-integration'),
                'confirmUpload' => __('Are you sure you want to upload these files to GitHub?', 'wp-cdn-integration'),
                'confirmPurge' => __('Are you sure you want to purge the CDN cache?', 'wp-cdn-integration'),
                'viewDetails' => __('View Details', 'wp-cdn-integration'),
                'hideDetails' => __('Hide Details', 'wp-cdn-integration'),
                'selectAll' => __('Select All', 'wp-cdn-integration'),
                'deselectAll' => __('Deselect All', 'wp-cdn-integration'),
                'noUrlsFound' => __('No URLs found matching your criteria.', 'wp-cdn-integration'),
                'analyzing' => __('Analyzing...', 'wp-cdn-integration'),
                'uploadComplete' => __('Upload Complete', 'wp-cdn-integration'),
                'addToCustomUrls' => __('Would you like to add these URLs to your custom URL list?', 'wp-cdn-integration'),
                'urlsAdded' => __('URLs have been added to your custom URL list.', 'wp-cdn-integration'),
                'enableWarning' => __('Warning: Enabling CDN integration before uploading files can break your site. Please analyze and upload your files first.', 'wp-cdn-integration')
            )
        ));
    }
	/**
     * Render the main plugin page with all settings and tools combined.
     *
     * @since 1.0.0
     */
    public function render_main_page() {
        // Get system status
        $github_configured = (!empty($this->helper->get_github_username()) && 
                             !empty($this->helper->get_github_repository()) && 
                             !empty($this->helper->get_github_token()));
        
        $cdn_enabled = $this->helper->is_enabled();
        $debug_enabled = $this->helper->is_debug_enabled();
        
        // Get stats
        $file_types = $this->helper->get_file_types();
        $custom_urls = $this->helper->get_custom_urls();
        $custom_url_count = count($custom_urls);
        
        // Check GitHub connection if configured
        $github_status = array(
            'status' => 'unknown',
            'message' => __('GitHub connection not tested', 'wp-cdn-integration')
        );
        
        if ($github_configured) {
            $connection_test = $this->github_api->test_connection();
            if ($connection_test) {
                $github_status = array(
                    'status' => 'success',
                    'message' => __('GitHub connection successful', 'wp-cdn-integration')
                );
            } else {
                $github_status = array(
                    'status' => 'error',
                    'message' => __('GitHub connection failed', 'wp-cdn-integration')
                );
            }
        }
        
        ?>
        <div class="wrap cdn-integration-dashboard">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="dashboard-intro">
                <p><?php esc_html_e('Welcome to WordPress CDN Integration! This plugin allows you to serve your static assets through jsDelivr CDN by leveraging GitHub as a storage repository.', 'wp-cdn-integration'); ?></p>
            </div>
            
            <?php if ($cdn_enabled && empty($custom_urls)): ?>
            <div class="notice notice-warning">
                <p>
                    <strong><?php esc_html_e('Warning:', 'wp-cdn-integration'); ?></strong>
                    <?php esc_html_e('CDN Integration is enabled but no files have been analyzed or uploaded. This could cause issues with your site. Please analyze and upload files before enabling CDN Integration.', 'wp-cdn-integration'); ?>
                </p>
            </div>
            <?php endif; ?>
            
            <div class="cdn-tabs">
                <div class="cdn-tab-nav">
                    <button class="cdn-tab-button active" data-tab="status"><?php esc_html_e('Status', 'wp-cdn-integration'); ?></button>
                    <button class="cdn-tab-button" data-tab="github"><?php esc_html_e('GitHub Settings', 'wp-cdn-integration'); ?></button>
                    <button class="cdn-tab-button" data-tab="cdn"><?php esc_html_e('CDN Settings', 'wp-cdn-integration'); ?></button>
                    <button class="cdn-tab-button" data-tab="analyzer"><?php esc_html_e('URL Analyzer', 'wp-cdn-integration'); ?></button>
                    <button class="cdn-tab-button" data-tab="custom"><?php esc_html_e('Custom URLs', 'wp-cdn-integration'); ?></button>
                </div>
                
                <div class="cdn-tab-content">
                    <!-- Status Tab -->
                    <div class="cdn-tab-pane active" id="status-tab">
                        <div class="dashboard-cards">
                            <!-- Status Card -->
                            <div class="dashboard-card">
                                <div class="card-header">
                                    <h2><?php esc_html_e('System Status', 'wp-cdn-integration'); ?></h2>
                                </div>
                                <div class="card-content">
                                    <ul class="status-list">
                                        <li>
                                            <span class="status-label"><?php esc_html_e('GitHub Configuration:', 'wp-cdn-integration'); ?></span>
                                            <span class="status-value <?php echo $github_configured ? 'status-success' : 'status-warning'; ?>">
                                                <?php echo $github_configured ? esc_html__('Configured', 'wp-cdn-integration') : esc_html__('Not Configured', 'wp-cdn-integration'); ?>
                                            </span>
                                        </li>
                                        <li>
                                            <span class="status-label"><?php esc_html_e('GitHub Connection:', 'wp-cdn-integration'); ?></span>
                                            <span class="status-value status-<?php echo esc_attr($github_status['status']); ?>">
                                                <?php echo esc_html($github_status['message']); ?>
                                            </span>
                                        </li>
                                        <li>
                                            <span class="status-label"><?php esc_html_e('CDN Integration:', 'wp-cdn-integration'); ?></span>
                                            <span class="status-value <?php echo $cdn_enabled ? 'status-success' : 'status-inactive'; ?>">
                                                <?php echo $cdn_enabled ? esc_html__('Enabled', 'wp-cdn-integration') : esc_html__('Disabled', 'wp-cdn-integration'); ?>
                                            </span>
                                        </li>
                                        <li>
                                            <span class="status-label"><?php esc_html_e('Debug Mode:', 'wp-cdn-integration'); ?></span>
                                            <span class="status-value <?php echo $debug_enabled ? 'status-warning' : 'status-inactive'; ?>">
                                                <?php echo $debug_enabled ? esc_html__('Enabled', 'wp-cdn-integration') : esc_html__('Disabled', 'wp-cdn-integration'); ?>
                                            </span>
                                        </li>
                                        <li>
                                            <span class="status-label"><?php esc_html_e('Custom URLs:', 'wp-cdn-integration'); ?></span>
                                            <span class="status-value">
                                                <?php echo esc_html($custom_url_count); ?>
                                            </span>
                                        </li>
                                        <li>
                                            <span class="status-label"><?php esc_html_e('File Types:', 'wp-cdn-integration'); ?></span>
                                            <span class="status-value">
                                                <?php echo esc_html(implode(', ', $file_types)); ?>
                                            </span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            
                            <!-- Workflow Card -->
                            <div class="dashboard-card">
                                <div class="card-header">
                                    <h2><?php esc_html_e('Setup Workflow', 'wp-cdn-integration'); ?></h2>
                                </div>
                                <div class="card-content">
                                    <ol class="workflow-steps">
                                        <li class="<?php echo $github_configured ? 'step-completed' : 'step-current'; ?>">
                                            <h3><?php esc_html_e('1. Configure GitHub', 'wp-cdn-integration'); ?></h3>
                                            <p><?php esc_html_e('Set up your GitHub repository information in the GitHub Settings tab.', 'wp-cdn-integration'); ?></p>
                                            <?php if (!$github_configured): ?>
                                            <button type="button" class="button button-primary cdn-tab-link" data-tab="github"><?php esc_html_e('Configure Now', 'wp-cdn-integration'); ?></button>
                                            <?php endif; ?>
                                        </li>
                                        
                                        <li class="<?php echo (!$github_configured) ? 'step-disabled' : (($github_status['status'] === 'success') ? 'step-completed' : 'step-current'); ?>">
                                            <h3><?php esc_html_e('2. Test GitHub Connection', 'wp-cdn-integration'); ?></h3>
                                            <p><?php esc_html_e('Verify that your GitHub credentials are working correctly.', 'wp-cdn-integration'); ?></p>
                                            <?php if ($github_configured && $github_status['status'] !== 'success'): ?>
                                            <button type="button" class="button button-primary" id="dashboard-test-connection"><?php esc_html_e('Test Connection', 'wp-cdn-integration'); ?></button>
                                            <span class="connection-status"></span>
                                            <?php endif; ?>
                                        </li>
                                        
                                        <li class="<?php echo ($github_status['status'] !== 'success') ? 'step-disabled' : (($custom_url_count > 0) ? 'step-completed' : 'step-current'); ?>">
                                            <h3><?php esc_html_e('3. Analyze Your Site', 'wp-cdn-integration'); ?></h3>
                                            <p><?php esc_html_e('Find static assets on your site that can be served via CDN.', 'wp-cdn-integration'); ?></p>
                                            <?php if ($github_status['status'] === 'success' && $custom_url_count === 0): ?>
                                            <button type="button" class="button button-primary cdn-tab-link" data-tab="analyzer"><?php esc_html_e('Analyze Now', 'wp-cdn-integration'); ?></button>
                                            <?php endif; ?>
                                        </li>
                                        
                                        <li class="<?php echo ($custom_url_count === 0) ? 'step-disabled' : (($cdn_enabled) ? 'step-completed' : 'step-current'); ?>">
                                            <h3><?php esc_html_e('4. Enable CDN Integration', 'wp-cdn-integration'); ?></h3>
                                            <p><?php esc_html_e('Turn on CDN integration to serve assets through jsDelivr.', 'wp-cdn-integration'); ?></p>
                                            <?php if ($custom_url_count > 0 && !$cdn_enabled): ?>
                                            <form method="post" action="options.php" class="inline-form">
                                                <?php
                                                settings_fields('wp_cdn_integration_settings');
                                                $options = get_option('wp_cdn_integration_settings');
                                                $options['enabled'] = '1';
                                                ?>
                                                <input type="hidden" name="wp_cdn_integration_settings[enabled]" value="1">
                                                <input type="hidden" name="wp_cdn_integration_settings[debug_mode]" value="<?php echo isset($options['debug_mode']) ? $options['debug_mode'] : '0'; ?>">
                                                <input type="hidden" name="wp_cdn_integration_settings[github_username]" value="<?php echo isset($options['github_username']) ? $options['github_username'] : ''; ?>">
                                                <input type="hidden" name="wp_cdn_integration_settings[github_repository]" value="<?php echo isset($options['github_repository']) ? $options['github_repository'] : ''; ?>">
                                                <input type="hidden" name="wp_cdn_integration_settings[github_branch]" value="<?php echo isset($options['github_branch']) ? $options['github_branch'] : 'main'; ?>">
                                                <input type="hidden" name="wp_cdn_integration_settings[github_token]" value="<?php echo isset($options['github_token']) ? $options['github_token'] : ''; ?>">
                                                <input type="hidden" name="wp_cdn_integration_settings[file_types]" value="<?php echo isset($options['file_types']) ? $options['file_types'] : 'css,js'; ?>">
                                                <input type="hidden" name="wp_cdn_integration_settings[excluded_paths]" value="<?php echo isset($options['excluded_paths']) ? $options['excluded_paths'] : ''; ?>">
                                                <input type="hidden" name="wp_cdn_integration_settings[custom_urls]" value="<?php echo isset($options['custom_urls']) ? $options['custom_urls'] : ''; ?>">
                                                <?php submit_button(__('Enable CDN Integration', 'wp-cdn-integration'), 'primary', 'submit', false); ?>
                                            </form>
                                            <?php endif; ?>
                                        </li>
                                    </ol>
                                </div>
                            </div>
							<!-- Quick Actions Card -->
                            <div class="dashboard-card">
                                <div class="card-header">
                                    <h2><?php esc_html_e('Quick Actions', 'wp-cdn-integration'); ?></h2>
                                </div>
                                <div class="card-content">
                                    <div class="quick-actions">
                                        <button type="button" class="action-button cdn-tab-link" data-tab="analyzer">
                                            <span class="dashicons dashicons-search"></span>
                                            <span class="action-label"><?php esc_html_e('Analyze Site', 'wp-cdn-integration'); ?></span>
                                        </button>
                                        <button type="button" class="action-button cdn-tab-link" data-tab="github">
                                            <span class="dashicons dashicons-admin-settings"></span>
                                            <span class="action-label"><?php esc_html_e('GitHub Settings', 'wp-cdn-integration'); ?></span>
                                        </button>
                                        <button type="button" id="quick-purge-cdn" class="action-button <?php echo $cdn_enabled ? '' : 'disabled'; ?>">
                                            <span class="dashicons dashicons-update"></span>
                                            <span class="action-label"><?php esc_html_e('Purge Cache', 'wp-cdn-integration'); ?></span>
                                        </button>
                                        <a href="<?php echo admin_url('admin.php?page=wp-cdn-integration-log'); ?>" class="action-button">
                                            <span class="dashicons dashicons-list-view"></span>
                                            <span class="action-label"><?php esc_html_e('View Logs', 'wp-cdn-integration'); ?></span>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($cdn_enabled): ?>
                        <div class="dashboard-cards">
                            <!-- CDN Status Card -->
                            <div class="dashboard-card">
                                <div class="card-header">
                                    <h2><?php esc_html_e('CDN Status', 'wp-cdn-integration'); ?></h2>
                                </div>
                                <div class="card-content">
                                    <div class="cdn-status">
                                        <p class="status-message success">
                                            <span class="dashicons dashicons-yes-alt"></span>
                                            <?php esc_html_e('Your static assets are being served through jsDelivr CDN!', 'wp-cdn-integration'); ?>
                                        </p>
                                        <p><?php esc_html_e('CDN Base URL:', 'wp-cdn-integration'); ?> <code><?php echo esc_html($this->helper->get_cdn_base_url()); ?></code></p>
                                        
                                        <div class="cdn-actions">
                                            <form method="post" action="options.php" class="inline-form">
                                                <?php
                                                settings_fields('wp_cdn_integration_settings');
                                                $options = get_option('wp_cdn_integration_settings');
                                                $options['enabled'] = '0';
                                                ?>
                                                <input type="hidden" name="wp_cdn_integration_settings[enabled]" value="0">
                                                <input type="hidden" name="wp_cdn_integration_settings[debug_mode]" value="<?php echo isset($options['debug_mode']) ? $options['debug_mode'] : '0'; ?>">
                                                <input type="hidden" name="wp_cdn_integration_settings[github_username]" value="<?php echo isset($options['github_username']) ? $options['github_username'] : ''; ?>">
                                                <input type="hidden" name="wp_cdn_integration_settings[github_repository]" value="<?php echo isset($options['github_repository']) ? $options['github_repository'] : ''; ?>">
                                                <input type="hidden" name="wp_cdn_integration_settings[github_branch]" value="<?php echo isset($options['github_branch']) ? $options['github_branch'] : 'main'; ?>">
                                                <input type="hidden" name="wp_cdn_integration_settings[github_token]" value="<?php echo isset($options['github_token']) ? $options['github_token'] : ''; ?>">
                                                <input type="hidden" name="wp_cdn_integration_settings[file_types]" value="<?php echo isset($options['file_types']) ? $options['file_types'] : 'css,js'; ?>">
                                                <input type="hidden" name="wp_cdn_integration_settings[excluded_paths]" value="<?php echo isset($options['excluded_paths']) ? $options['excluded_paths'] : ''; ?>">
                                                <input type="hidden" name="wp_cdn_integration_settings[custom_urls]" value="<?php echo isset($options['custom_urls']) ? $options['custom_urls'] : ''; ?>">
                                                <?php submit_button(__('Disable CDN Integration', 'wp-cdn-integration'), 'secondary', 'submit', false); ?>
                                            </form>
                                            <button type="button" id="dashboard-purge-cdn" class="button"><?php esc_html_e('Purge CDN Cache', 'wp-cdn-integration'); ?></button>
                                            <span class="purge-status"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- GitHub Settings Tab -->
                    <div class="cdn-tab-pane" id="github-tab">
                        <div class="dashboard-card">
                            <div class="card-header">
                                <h2><?php esc_html_e('GitHub Repository Settings', 'wp-cdn-integration'); ?></h2>
                            </div>
                            <div class="card-content">
                                <p><?php esc_html_e('Configure the GitHub repository that will host your static assets.', 'wp-cdn-integration'); ?></p>
                                
                                <form method="post" action="options.php" class="cdn-settings-form">
                                    <?php settings_fields('wp_cdn_integration_settings'); ?>
                                    
                                    <table class="form-table">
                                        <tr>
                                            <th scope="row"><?php esc_html_e('GitHub Username', 'wp-cdn-integration'); ?></th>
                                            <td>
                                                <?php
                                                $options = get_option('wp_cdn_integration_settings');
                                                $username = isset($options['github_username']) ? $options['github_username'] : '';
                                                ?>
                                                <input type="text" id="wp-cdn-github-username" name="wp_cdn_integration_settings[github_username]" value="<?php echo esc_attr($username); ?>" class="regular-text">
                                                <p class="description">
                                                    <?php esc_html_e('Your GitHub username.', 'wp-cdn-integration'); ?>
                                                </p>
                                            </td>
                                        </tr>
                                        
                                        <tr>
                                            <th scope="row"><?php esc_html_e('Repository Name', 'wp-cdn-integration'); ?></th>
                                            <td>
                                                <?php
                                                $repository = isset($options['github_repository']) ? $options['github_repository'] : '';
                                                ?>
                                                <input type="text" id="wp-cdn-github-repository" name="wp_cdn_integration_settings[github_repository]" value="<?php echo esc_attr($repository); ?>" class="regular-text">
                                                <p class="description">
                                                    <?php esc_html_e('The name of your GitHub repository.', 'wp-cdn-integration'); ?>
                                                </p>
                                            </td>
                                        </tr>
                                        
                                        <tr>
                                            <th scope="row"><?php esc_html_e('Branch Name', 'wp-cdn-integration'); ?></th>
                                            <td>
                                                <?php
                                                $branch = isset($options['github_branch']) ? $options['github_branch'] : 'main';
                                                ?>
                                                <input type="text" id="wp-cdn-github-branch" name="wp_cdn_integration_settings[github_branch]" value="<?php echo esc_attr($branch); ?>" class="regular-text">
                                                <p class="description">
                                                    <?php esc_html_e('The branch to use (default: main).', 'wp-cdn-integration'); ?>
                                                </p>
                                            </td>
                                        </tr>
                                        
                                        <tr>
                                            <th scope="row"><?php esc_html_e('GitHub Personal Access Token', 'wp-cdn-integration'); ?></th>
                                            <td>
                                              <?php
                                                $token = isset($options['github_token']) ? $options['github_token'] : '';
                                                ?>
                                                <input type="password" id="wp-cdn-github-token" name="wp_cdn_integration_settings[github_token]" value="<?php echo esc_attr($token); ?>" class="regular-text">
                                                <p class="description">
                                                    <?php esc_html_e('Your GitHub Personal Access Token with repository access. Generate one at GitHub Settings > Developer settings > Personal access tokens.', 'wp-cdn-integration'); ?>
                                                </p>
                                            </td>
                                        </tr>
                                        
                                        <tr>
                                            <th scope="row"><?php esc_html_e('Test Connection', 'wp-cdn-integration'); ?></th>
                                            <td>
                                                <button type="button" class="button" id="test-connection-button">
                                                    <?php esc_html_e('Test Connection', 'wp-cdn-integration'); ?>
                                                </button>
                                                <span class="connection-status"></span>
                                            </td>
                                        </tr>
                                    </table>
                                    
                                    <?php submit_button(__('Save GitHub Settings', 'wp-cdn-integration')); ?>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- CDN Settings Tab -->
                    <div class="cdn-tab-pane" id="cdn-tab">
                        <div class="dashboard-card">
                            <div class="card-header">
                                <h2><?php esc_html_e('CDN Settings', 'wp-cdn-integration'); ?></h2>
                            </div>
                            <div class="card-content">
                                <p><?php esc_html_e('Configure which files should be served via the CDN.', 'wp-cdn-integration'); ?></p>
                                
                                <form method="post" action="options.php" class="cdn-settings-form">
                                    <?php settings_fields('wp_cdn_integration_settings'); ?>
                                    
                                    <table class="form-table">
                                        <tr>
                                            <th scope="row"><?php esc_html_e('Enable CDN Integration', 'wp-cdn-integration'); ?></th>
                                            <td>
                                                <?php
                                                $options = get_option('wp_cdn_integration_settings');
                                                $enabled = isset($options['enabled']) ? $options['enabled'] : '0';
                                                
                                                // Check if we have custom URLs configured
                                                $custom_urls = isset($options['custom_urls']) ? $options['custom_urls'] : '';
                                                $has_custom_urls = !empty(trim($custom_urls));
                                                
                                                // Check GitHub configuration
                                                $github_configured = (!empty($this->helper->get_github_username()) && 
                                                                    !empty($this->helper->get_github_repository()) && 
                                                                    !empty($this->helper->get_github_token()));
                                                
                                                // Show warning if enabling without custom URLs or GitHub config
                                                $show_warning = !$enabled && (!$has_custom_urls || !$github_configured);
                                                ?>
                                                <input type="checkbox" id="wp-cdn-enabled" name="wp_cdn_integration_settings[enabled]" value="1" <?php checked('1', $enabled); ?>>
                                                <label for="wp-cdn-enabled"><?php esc_html_e('Enable CDN Integration', 'wp-cdn-integration'); ?></label>
                                                <p class="description">
                                                    <?php esc_html_e('Turn on to serve your static assets through jsDelivr CDN.', 'wp-cdn-integration'); ?>
                                                </p>
                                                
                                                <?php if ($show_warning): ?>
                                                <div class="notice notice-warning inline" style="margin: 10px 0 0 0; padding: 10px;">
                                                    <p>
                                                        <strong><?php esc_html_e('Warning:', 'wp-cdn-integration'); ?></strong>
                                                        <?php esc_html_e('Enabling CDN Integration before analyzing and uploading your files to GitHub could break your site. Please use the URL Analyzer to find and upload your files first.', 'wp-cdn-integration'); ?>
                                                    </p>
                                                    <p>
                                                        <button type="button" class="button cdn-tab-link" data-tab="analyzer">
                                                            <?php esc_html_e('Go to URL Analyzer', 'wp-cdn-integration'); ?>
                                                        </button>
                                                    </p>
                                                </div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        
                                        <tr>
                                            <th scope="row"><?php esc_html_e('Debug Mode', 'wp-cdn-integration'); ?></th>
                                            <td>
                                                <?php
                                                $debug_mode = isset($options['debug_mode']) ? $options['debug_mode'] : '0';
                                                ?>
                                                <input type="checkbox" id="wp-cdn-debug-mode" name="wp_cdn_integration_settings[debug_mode]" value="1" <?php checked('1', $debug_mode); ?>>
                                                <label for="wp-cdn-debug-mode"><?php esc_html_e('Enable Debug Mode', 'wp-cdn-integration'); ?></label>
                                                <p class="description">
                                                    <?php esc_html_e('Turn on to enable detailed logging of CDN operations.', 'wp-cdn-integration'); ?>
                                                </p>
                                            </td>
                                        </tr>
                                        
                                        <tr>
                                            <th scope="row"><?php esc_html_e('File Types to Serve via CDN', 'wp-cdn-integration'); ?></th>
                                            <td>
                                                <?php
                                                $file_types = isset($options['file_types']) ? $options['file_types'] : 'css,js';
                                                
                                                if (!is_array($file_types)) {
                                                    $file_types = explode(',', $file_types);
                                                }
                                                
                                                $available_types = array(
                                                    'css' => __('CSS Files', 'wp-cdn-integration'),
                                                    'js' => __('JavaScript Files', 'wp-cdn-integration'),
                                                    'png' => __('PNG Images', 'wp-cdn-integration'),
                                                    'jpg' => __('JPG Images', 'wp-cdn-integration'),
                                                    'jpeg' => __('JPEG Images', 'wp-cdn-integration'),
                                                    'gif' => __('GIF Images', 'wp-cdn-integration'),
                                                    'svg' => __('SVG Images', 'wp-cdn-integration'),
                                                    'webp' => __('WebP Images', 'wp-cdn-integration'),
                                                    'ico' => __('Icon Files', 'wp-cdn-integration'),
                                                    'woff' => __('WOFF Fonts', 'wp-cdn-integration'),
                                                    'woff2' => __('WOFF2 Fonts', 'wp-cdn-integration'),
                                                    'ttf' => __('TTF Fonts', 'wp-cdn-integration'),
                                                    'eot' => __('EOT Fonts', 'wp-cdn-integration')
                                                );
                                                ?>
                                                <div class="file-types-container" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 5px;">
                                                    <?php foreach ($available_types as $type => $label): ?>
                                                    <label for="wp-cdn-file-type-<?php echo esc_attr($type); ?>" style="margin-bottom: 8px;">
                                                        <input type="checkbox" id="wp-cdn-file-type-<?php echo esc_attr($type); ?>" name="wp_cdn_integration_settings[file_types][]" value="<?php echo esc_attr($type); ?>" <?php checked(in_array($type, $file_types), true); ?>>
                                                        <?php echo esc_html($label); ?>
                                                    </label>
                                                    <?php endforeach; ?>
                                                </div>
                                                <p class="description">
                                                    <?php esc_html_e('Select which file types should be served via the CDN.', 'wp-cdn-integration'); ?>
                                                </p>
                                            </td>
                                        </tr>
                                        
                                        <tr>
                                            <th scope="row"><?php esc_html_e('Exclude Paths', 'wp-cdn-integration'); ?></th>
                                            <td>
                                                <?php
                                                $excluded_paths = isset($options['excluded_paths']) ? $options['excluded_paths'] : '/wp-admin/*, /wp-login.php';
                                                ?>
                                                <textarea id="wp-cdn-excluded-paths" name="wp_cdn_integration_settings[excluded_paths]" class="large-text" rows="5"><?php echo esc_textarea($excluded_paths); ?></textarea>
                                                <p class="description">
                                                    <?php esc_html_e('Enter paths to exclude from CDN (one per line). Example: /wp-content/plugins/plugin-name/*', 'wp-cdn-integration'); ?>
                                                </p>
                                            </td>
                                        </tr>
                                        
                                        <tr>
                                            <th scope="row"><?php esc_html_e('Purge jsDelivr CDN Cache', 'wp-cdn-integration'); ?></th>
                                            <td>
                                                <button type="button" class="button" id="purge-cdn-button">
                                                    <?php esc_html_e('Purge Cache', 'wp-cdn-integration'); ?>
                                                </button>
                                                <span class="purge-status"></span>
                                                <p class="description">
                                                    <?php esc_html_e('Click to purge the jsDelivr CDN cache after updating static files.', 'wp-cdn-integration'); ?>
                                                </p>
                                            </td>
                                        </tr>
                                    </table>
                                    
                                    <?php submit_button(__('Save CDN Settings', 'wp-cdn-integration')); ?>
                                </form>
                            </div>
                        </div>
                    </div>
					<!-- URL Analyzer Tab -->
                    <div class="cdn-tab-pane" id="analyzer-tab">
                        <div class="dashboard-card">
                            <div class="card-header">
                                <h2><?php esc_html_e('URL Analyzer', 'wp-cdn-integration'); ?></h2>
                            </div>
                            <div class="card-content">
                                <?php
                                // Check GitHub configuration
                                $github_configured = (!empty($this->helper->get_github_username()) && 
                                                    !empty($this->helper->get_github_repository()) && 
                                                    !empty($this->helper->get_github_token()));
                                
                                // Check GitHub connection
                                $connection_status = false;
                                if ($github_configured) {
                                    $connection_status = $this->github_api->test_connection();
                                }
                                
                                if (!$github_configured || !$connection_status): 
                                ?>
                                <div class="notice notice-warning">
                                    <p>
                                        <?php if (!$github_configured): ?>
                                            <strong><?php esc_html_e('GitHub configuration is incomplete.', 'wp-cdn-integration'); ?></strong>
                                            <?php esc_html_e('Please configure your GitHub settings before analyzing URLs.', 'wp-cdn-integration'); ?>
                                        <?php else: ?>
                                            <strong><?php esc_html_e('GitHub connection failed.', 'wp-cdn-integration'); ?></strong>
                                            <?php esc_html_e('Please verify your GitHub settings before analyzing URLs.', 'wp-cdn-integration'); ?>
                                        <?php endif; ?>
                                    </p>
                                    <p>
                                        <button type="button" class="button button-primary cdn-tab-link" data-tab="github">
                                            <?php esc_html_e('Go to GitHub Settings', 'wp-cdn-integration'); ?>
                                        </button>
                                    </p>
                                </div>
                                <?php endif; ?>
                                
                                <p>
                                    <?php esc_html_e('Analyze your website to find static assets that can be served through the CDN.', 'wp-cdn-integration'); ?>
                                </p>
                                
                                <div class="cdn-analyzer-container">
                                    <div class="cdn-analyzer-tabs">
                                        <button class="cdn-analyzer-tab active" data-tab="quick-analyze">
                                            <?php esc_html_e('Quick Analyze', 'wp-cdn-integration'); ?>
                                        </button>
                                        <button class="cdn-analyzer-tab" data-tab="deep-analyze">
                                            <?php esc_html_e('Deep Analyze', 'wp-cdn-integration'); ?>
                                        </button>
                                        <button class="cdn-analyzer-tab" data-tab="paste-urls">
                                            <?php esc_html_e('Paste URLs', 'wp-cdn-integration'); ?>
                                        </button>
                                    </div>
                                    
                                    <div class="cdn-analyzer-content">
                                        <div class="cdn-analyzer-tab-content active" id="quick-analyze">
                                            <h3><?php esc_html_e('Quick Analysis', 'wp-cdn-integration'); ?></h3>
                                            <p><?php esc_html_e('Analyze your homepage to find static assets.', 'wp-cdn-integration'); ?></p>
                                            
                                            <div class="cdn-analyze-actions">
                                                <button type="button" class="button button-primary" id="quick-analyze-button" <?php echo (!$github_configured || !$connection_status) ? 'disabled' : ''; ?>>
                                                    <?php esc_html_e('Analyze Homepage', 'wp-cdn-integration'); ?>
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <div class="cdn-analyzer-tab-content" id="deep-analyze">
                                            <h3><?php esc_html_e('Deep Analysis', 'wp-cdn-integration'); ?></h3>
                                            <p><?php esc_html_e('Crawl multiple pages to find static assets.', 'wp-cdn-integration'); ?></p>
                                            
                                            <div class="cdn-analyze-settings">
                                                <div class="cdn-analyze-field">
                                                    <label for="start-url"><?php esc_html_e('Start URL:', 'wp-cdn-integration'); ?></label>
                                                    <input type="text" id="start-url" value="<?php echo esc_url(home_url('/')); ?>" class="regular-text">
                                                </div>
                                                
                                                <div class="cdn-analyze-field">
                                                    <label for="max-pages"><?php esc_html_e('Maximum Pages:', 'wp-cdn-integration'); ?></label>
                                                    <input type="number" id="max-pages" value="5" min="1" max="20">
                                                    <p class="description">
                                                        <?php esc_html_e('More pages will find more URLs but will take longer to complete.', 'wp-cdn-integration'); ?>
                                                    </p>
                                                </div>
                                            </div>
                                            
                                            <div class="cdn-analyze-actions">
                                                <button type="button" class="button button-primary" id="deep-analyze-button" <?php echo (!$github_configured || !$connection_status) ? 'disabled' : ''; ?>>
                                                    <?php esc_html_e('Start Deep Analysis', 'wp-cdn-integration'); ?>
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <div class="cdn-analyzer-tab-content" id="paste-urls">
                                            <h3><?php esc_html_e('Paste URLs', 'wp-cdn-integration'); ?></h3>
                                            <p><?php esc_html_e('Paste URLs that you want to analyze and potentially serve via CDN.', 'wp-cdn-integration'); ?></p>
                                            
                                            <div class="cdn-analyze-field">
                                                <label for="pasted-urls"><?php esc_html_e('URLs (one per line):', 'wp-cdn-integration'); ?></label>
                                                <textarea id="pasted-urls" class="large-text" rows="10"></textarea>
                                            </div>
                                            
                                            <div class="cdn-analyze-actions">
                                                <button type="button" class="button button-primary" id="analyze-pasted-urls-button" <?php echo (!$github_configured || !$connection_status) ? 'disabled' : ''; ?>>
                                                    <?php esc_html_e('Analyze Pasted URLs', 'wp-cdn-integration'); ?>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="cdn-analyzer-results" style="display: none;">
                                        <h3><?php esc_html_e('Analysis Results', 'wp-cdn-integration'); ?></h3>
                                        
                                        <div class="cdn-analyzer-results-summary">
                                            <span id="url-count"></span>
                                        </div>
                                        
                                        <div class="cdn-analyzer-filter">
                                            <input type="text" id="url-filter" placeholder="<?php esc_attr_e('Filter URLs...', 'wp-cdn-integration'); ?>" class="regular-text">
                                            
                                            <select id="url-type-filter">
                                                <option value="all"><?php esc_html_e('All Types', 'wp-cdn-integration'); ?></option>
                                                <option value="js"><?php esc_html_e('JavaScript (.js)', 'wp-cdn-integration'); ?></option>
                                                <option value="css"><?php esc_html_e('CSS (.css)', 'wp-cdn-integration'); ?></option>
                                                <option value="images"><?php esc_html_e('Images', 'wp-cdn-integration'); ?></option>
                                                <option value="fonts"><?php esc_html_e('Fonts', 'wp-cdn-integration'); ?></option>
                                                <option value="other"><?php esc_html_e('Other', 'wp-cdn-integration'); ?></option>
                                            </select>
                                            
                                            <span class="filter-status"></span>
                                        </div>
                                        
                                        <div class="cdn-analyzer-url-list">
                                            <!-- URLs will be populated here -->
                                        </div>
                                        
                                        <div class="cdn-analyzer-actions">
                                            <button type="button" class="button" id="select-all-urls">
                                                <?php esc_html_e('Select All', 'wp-cdn-integration'); ?>
                                            </button>
                                            
                                            <button type="button" class="button" id="add-selected-urls">
                                                <?php esc_html_e('Add Selected URLs', 'wp-cdn-integration'); ?>
                                            </button>
                                            
                                            <button type="button" class="button button-primary" id="upload-to-github-button">
                                                <?php esc_html_e('Upload to GitHub', 'wp-cdn-integration'); ?>
                                            </button>
                                        </div>
                                        
                                        <div class="cdn-upload-progress" style="display: none;">
                                            <div class="progress-status"></div>
                                            <div class="progress-bar-container">
                                                <div class="progress-bar"></div>
                                            </div>
                                        </div>
                                        
                                        <div class="cdn-upload-result" style="display: none;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
					<!-- Custom URLs Tab -->
                    <div class="cdn-tab-pane" id="custom-tab">
                        <div class="dashboard-card">
                            <div class="card-header">
                                <h2><?php esc_html_e('Custom URLs', 'wp-cdn-integration'); ?></h2>
                            </div>
                            <div class="card-content">
                                <p><?php esc_html_e('Specify custom URLs that should be served via the CDN.', 'wp-cdn-integration'); ?></p>
                                
                                <form method="post" action="options.php" class="cdn-settings-form">
                                    <?php settings_fields('wp_cdn_integration_settings'); ?>
                                    
                                    <table class="form-table">
                                        <tr>
                                            <th scope="row"><?php esc_html_e('Custom URLs to Serve via CDN', 'wp-cdn-integration'); ?></th>
                                            <td>
                                                <?php
                                                $options = get_option('wp_cdn_integration_settings');
                                                $custom_urls = isset($options['custom_urls']) ? $options['custom_urls'] : '';
                                                ?>
                                                <textarea id="wp-cdn-custom-urls" name="wp_cdn_integration_settings[custom_urls]" class="large-text" rows="10"><?php echo esc_textarea($custom_urls); ?></textarea>
                                                <p class="description">
                                                    <?php esc_html_e('Enter URLs (one per line) to be served via the CDN. Example: /wp-content/themes/your-theme/style.css', 'wp-cdn-integration'); ?>
                                                </p>
                                            </td>
                                        </tr>
                                        
                                        <tr>
                                            <th scope="row"><?php esc_html_e('Validate Custom URLs', 'wp-cdn-integration'); ?></th>
                                            <td>
                                                <button type="button" class="button" id="validate-urls-button">
                                                    <?php esc_html_e('Validate & Auto-Upload', 'wp-cdn-integration'); ?>
                                                </button>
                                                <span class="validate-status"></span>
                                                <p class="description">
                                                    <?php esc_html_e('Check if custom URLs exist on GitHub and auto-upload missing files.', 'wp-cdn-integration'); ?>
                                                </p>
                                                <div id="validation-progress" style="display: none; margin-top: 10px;">
                                                    <div class="progress-status"></div>
                                                    <div class="progress-bar-container">
                                                        <div class="progress-bar"></div>
                                                    </div>
                                                </div>
                                                <div id="validation-results" style="display: none; margin-top: 10px;"></div>
                                            </td>
                                        </tr>
                                    </table>
                                    
                                    <?php submit_button(__('Save Custom URLs', 'wp-cdn-integration')); ?>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render the log page.
     *
     * @since 1.0.0
     */
    public function render_log_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <p>
                <?php esc_html_e('View the CDN Integration log file.', 'wp-cdn-integration'); ?>
            </p>
            
            <div class="cdn-log-container">
                <div class="cdn-log-actions">
                    <button type="button" class="button button-primary" id="view-log-button">
                        <?php esc_html_e('View Log', 'wp-cdn-integration'); ?>
                    </button>
                    
                    <button type="button" class="button" id="refresh-log-button">
                        <?php esc_html_e('Refresh', 'wp-cdn-integration'); ?>
                    </button>
                    
                    <button type="button" class="button" id="clear-log-button">
                        <?php esc_html_e('Clear Log', 'wp-cdn-integration'); ?>
                    </button>
                </div>
                
                <div class="cdn-log-content">
                    <pre id="log-content"></pre>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * AJAX handler for updating custom URLs.
     *
     * @since 1.0.0
     */
    public function ajax_update_custom_urls() {
        // Check nonce
        if (!check_ajax_referer('wp-cdn-integration-nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'wp-cdn-integration')));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'wp-cdn-integration')));
        }
        
        $custom_urls = isset($_POST['custom_urls']) ? sanitize_textarea_field($_POST['custom_urls']) : '';
        
        // Get current settings
        $options = get_option('wp_cdn_integration_settings', array());
        
        // Update custom URLs
        $options['custom_urls'] = $custom_urls;
        
        // Save updated settings
        update_option('wp_cdn_integration_settings', $options);
        
        wp_send_json_success(array(
            'message' => __('Custom URLs updated successfully.', 'wp-cdn-integration')
        ));
    }
     /**
     * Register plugin settings.
     *
     * @since 1.0.0
     */
    public function register_settings() {
        register_setting(
            'wp_cdn_integration_settings',
            'wp_cdn_integration_settings',
            array($this, 'sanitize_settings')
        );
    }

    /**
     * Sanitize settings.
     *
     * @since 1.0.0
     * @param array $input The settings input.
     * @return array Sanitized settings.
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        if (isset($input['enabled'])) {
            $sanitized['enabled'] = $input['enabled'] ? '1' : '0';
        }
        
        if (isset($input['debug_mode'])) {
            $sanitized['debug_mode'] = $input['debug_mode'] ? '1' : '0';
        }
        
        if (isset($input['github_username'])) {
            $sanitized['github_username'] = sanitize_text_field($input['github_username']);
        }
        
        if (isset($input['github_repository'])) {
            $sanitized['github_repository'] = sanitize_text_field($input['github_repository']);
        }
        
        if (isset($input['github_branch'])) {
            $sanitized['github_branch'] = sanitize_text_field($input['github_branch']);
        }
        
        if (isset($input['github_token'])) {
            $sanitized['github_token'] = sanitize_text_field($input['github_token']);
        }
        
        if (isset($input['file_types'])) {
            if (is_array($input['file_types'])) {
                $sanitized['file_types'] = implode(',', array_map('sanitize_text_field', $input['file_types']));
            } else {
                $sanitized['file_types'] = sanitize_text_field($input['file_types']);
            }
        }
        
        if (isset($input['excluded_paths'])) {
            $sanitized['excluded_paths'] = sanitize_textarea_field($input['excluded_paths']);
        }
        
        if (isset($input['custom_urls'])) {
            $sanitized['custom_urls'] = sanitize_textarea_field($input['custom_urls']);
        }
        
        return $sanitized;
    }                                         
	/**
     * AJAX handler for testing GitHub connection.
     *
     * @since 1.0.0
     */
    public function ajax_test_connection() {
        // Check nonce
        if (!check_ajax_referer('wp-cdn-integration-nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'wp-cdn-integration')));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'wp-cdn-integration')));
        }
        
        $success = $this->github_api->test_connection();
        
        if ($success) {
            wp_send_json_success(array(
                'message' => __('GitHub connection test successful. Your credentials are correct and you have proper access to the repository.', 'wp-cdn-integration')
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('GitHub connection test failed. Please check if your username, repository name, and personal access token are correct. Also ensure the token has proper permissions (repo scope).', 'wp-cdn-integration')
            ));
        }
    }

    /**
     * AJAX handler for purging CDN cache.
     *
     * @since 1.0.0
     */
    public function ajax_purge_cache() {
        // Check nonce
        if (!check_ajax_referer('wp-cdn-integration-nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'wp-cdn-integration')));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'wp-cdn-integration')));
        }
        
        $success = $this->jsdelivr_api->purge_all();
        
        if ($success) {
            wp_send_json_success(array(
                'message' => __('jsDelivr CDN cache has been purged successfully.', 'wp-cdn-integration')
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Failed to purge jsDelivr CDN cache. Please check the logs for details.', 'wp-cdn-integration')
            ));
        }
    }

    /**
     * AJAX handler for analyzing URLs.
     *
     * @since 1.0.0
     */
    public function ajax_analyze_urls() {
        // Check nonce
        if (!check_ajax_referer('wp-cdn-integration-nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'wp-cdn-integration')));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'wp-cdn-integration')));
        }
        
        $is_deep_analyze = isset($_POST['deep_analyze']) && $_POST['deep_analyze'] === 'true';
        
        if ($is_deep_analyze) {
            $start_url = isset($_POST['start_url']) ? esc_url_raw($_POST['start_url']) : home_url('/');
            $max_pages = isset($_POST['max_pages']) ? intval($_POST['max_pages']) : 5;
            
            // Limit max pages to prevent excessive crawling
            $max_pages = min($max_pages, 20);
            
            $urls = $this->url_analyzer->analyze($start_url, $max_pages);
        } else {
            // Quick analyze - just analyze homepage
            $urls = $this->url_analyzer->analyze(home_url('/'), 1);
        }
        
        if (empty($urls)) {
            wp_send_json_error(array(
                'message' => __('No suitable URLs found to analyze.', 'wp-cdn-integration')
            ));
        }
        
        wp_send_json_success(array(
            'urls' => $urls,
            'count' => count($urls),
            'message' => sprintf(
                _n('Found %d URL to analyze.', 'Found %d URLs to analyze.', count($urls), 'wp-cdn-integration'),
                count($urls)
            )
        ));
    }

    /**
     * AJAX handler for direct URL analysis.
     *
     * @since 1.0.0
     */
    public function ajax_direct_analyze_urls() {
        // Check nonce
        if (!check_ajax_referer('wp-cdn-integration-nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'wp-cdn-integration')));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'wp-cdn-integration')));
        }
        
        $pasted_urls = isset($_POST['pasted_urls']) ? sanitize_textarea_field($_POST['pasted_urls']) : '';
        
        if (empty($pasted_urls)) {
            wp_send_json_error(array(
                'message' => __('No URLs provided.', 'wp-cdn-integration')
            ));
        }
        
        $urls = $this->url_analyzer->process_pasted_urls($pasted_urls);
        
        if (empty($urls)) {
            wp_send_json_error(array(
                'message' => __('No suitable URLs found after processing your input.', 'wp-cdn-integration')
            ));
        }
        
        wp_send_json_success(array(
            'urls' => $urls,
            'count' => count($urls),
            'message' => sprintf(
                _n('Found %d valid URL from your input.', 'Found %d valid URLs from your input.', count($urls), 'wp-cdn-integration'),
                count($urls)
            )
        ));
    }
	/**
     * AJAX handler for uploading files to GitHub.
     *
     * @since 1.0.0
     */
    public function ajax_upload_to_github() {
        // Check nonce
        if (!check_ajax_referer('wp-cdn-integration-nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'wp-cdn-integration')));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'wp-cdn-integration')));
        }
        
        $urls = isset($_POST['urls']) ? json_decode(stripslashes($_POST['urls']), true) : array();
        
        if (empty($urls) || !is_array($urls)) {
            wp_send_json_error(array(
                'message' => __('No URLs provided for upload.', 'wp-cdn-integration')
            ));
        }
        
        $this->helper->log('Received URLs for upload: ' . implode(', ', array_slice($urls, 0, 5)) . (count($urls) > 5 ? '...' : ''), 'info');
        
        // Initialize results
        $results = array(
            'total' => count($urls),
            'success' => 0,
            'failed' => 0,
            'exists' => 0,
            'details' => array()
        );
        
        // Process each URL
        foreach ($urls as $url) {
            $this->helper->log("Processing URL: {$url}", 'debug');
            
            try {
                // Get local file path
                $local_path = $this->helper->get_local_path_for_url($url);
                
                if (empty($local_path)) {
                    $results['failed']++;
                    $results['details'][] = array(
                        'url' => $url,
                        'success' => false,
                        'message' => __('Could not determine local path for URL', 'wp-cdn-integration')
                    );
                    continue;
                }
                
                // Check if file exists
                if (!file_exists($local_path)) {
                    $results['failed']++;
                    $results['details'][] = array(
                        'url' => $url,
                        'success' => false,
                        'message' => __('File not found: ', 'wp-cdn-integration') . $local_path
                    );
                    continue;
                }
                
                // Get remote path
                $remote_path = $this->helper->get_remote_path_for_url($url);
                
                // Check if file already exists on GitHub
                $exists = $this->github_api->get_contents($remote_path);
                
                if ($exists && isset($exists['sha'])) {
                    $results['exists']++;
                    $results['details'][] = array(
                        'url' => $url,
                        'success' => true,
                        'message' => __('File already exists on GitHub', 'wp-cdn-integration')
                    );
                    continue;
                }
                
                // Upload file to GitHub
                $success = $this->github_api->upload_file($local_path, $remote_path);
                
                if ($success) {
                    $results['success']++;
                    $results['details'][] = array(
                        'url' => $url,
                        'success' => true,
                        'message' => __('Successfully uploaded to GitHub', 'wp-cdn-integration')
                    );
                } else {
                    $results['failed']++;
                    $results['details'][] = array(
                        'url' => $url,
                        'success' => false,
                        'message' => __('Failed to upload to GitHub', 'wp-cdn-integration')
                    );
                }
            } catch (Exception $e) {
                $results['failed']++;
                $results['details'][] = array(
                    'url' => $url,
                    'success' => false,
                    'message' => $e->getMessage()
                );
            }
        }
        
        // Create success or failure message
        if ($results['failed'] > 0 && $results['success'] > 0) {
            $message = sprintf(
                __('Upload completed with issues: %1$d successful, %2$d already on GitHub, %3$d failed, %4$d total.', 'wp-cdn-integration'),
                $results['success'],
                $results['exists'],
                $results['failed'],
                $results['total']
            );
        } else if ($results['failed'] > 0 && $results['success'] === 0 && $results['exists'] === 0) {
            $message = __('Upload failed for all files. Check details for more information.', 'wp-cdn-integration');
        } else if ($results['exists'] === $results['total']) {
            $message = __('All files already exist on GitHub.', 'wp-cdn-integration');
        } else {
            $message = sprintf(
                __('All %d files were successfully uploaded to GitHub.', 'wp-cdn-integration'),
                $results['success']
            );
        }
        
        wp_send_json_success(array(
            'results' => $results,
            'message' => $message
        ));
    }

    /**
     * AJAX handler for validating URLs.
     *
     * @since 1.0.0
     */
    public function ajax_validate_urls() {
        // Check nonce
        if (!check_ajax_referer('wp-cdn-integration-nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'wp-cdn-integration')));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'wp-cdn-integration')));
        }
        
        // Check for batch processing
        $batch_mode = isset($_POST['batch_mode']) && $_POST['batch_mode'] === 'true';
        
        if ($batch_mode) {
            $batch_urls = isset($_POST['batch_urls']) ? json_decode(stripslashes($_POST['batch_urls']), true) : array();
            $batch_index = isset($_POST['batch_index']) ? intval($_POST['batch_index']) : 0;
            $total_batches = isset($_POST['total_batches']) ? intval($_POST['total_batches']) : 1;
            
            if (empty($batch_urls) || !is_array($batch_urls)) {
                wp_send_json_error(array(
                    'message' => __('Invalid URL format in batch.', 'wp-cdn-integration')
                ));
            }
            
            $batch_results = $this->validate_url_batch($batch_urls);
            
            wp_send_json_success(array(
                'batch_index' => $batch_index,
                'total_batches' => $total_batches,
                'batch_results' => $batch_results
            ));
        } else {
            // Process all URLs
            $custom_urls = $this->helper->get_custom_urls();
            
            if (empty($custom_urls)) {
                wp_send_json_error(array(
                    'message' => __('No custom URLs defined.', 'wp-cdn-integration')
                ));
            }
            
            // Break into batches
            $batch_size = 5;
            $batches = array_chunk($custom_urls, $batch_size);
            
            wp_send_json_success(array(
                'total_urls' => count($custom_urls),
                'batches' => count($batches),
                'batch_size' => $batch_size,
                'message' => sprintf(
                    __('Processing %d URLs in %d batches...', 'wp-cdn-integration'),
                    count($custom_urls),
                    count($batches)
                )
            ));
        }
    }

    /**
     * Validate a batch of URLs.
     *
     * @since 1.0.0
     * @param array $urls Array of URLs to validate.
     * @return array Validation results.
     */
    private function validate_url_batch($urls) {
        $results = array(
            'processed' => 0,
            'success' => 0,
            'failed' => 0,
            'exists' => 0,
            'details' => array()
        );
        
        foreach ($urls as $url) {
            $results['processed']++;
            
            try {
                // Get local file path
                $local_path = $this->helper->get_local_path_for_url($url);
                
                if (empty($local_path)) {
                    $results['failed']++;
                    $results['details'][] = array(
                        'url' => $url,
                        'status' => 'failed',
                        'message' => __('Could not determine local path for URL', 'wp-cdn-integration')
                    );
                    continue;
                }
                
                // Check if file exists
                if (!file_exists($local_path)) {
                    $results['failed']++;
                    $results['details'][] = array(
                        'url' => $url,
                        'status' => 'failed',
                        'message' => __('File not found: ', 'wp-cdn-integration') . $local_path
                    );
                    continue;
                }
                
                // Get remote path
                $remote_path = $this->helper->get_remote_path_for_url($url);
                
                // Check if file already exists on GitHub
                $exists = $this->github_api->get_contents($remote_path);
                
                if ($exists && isset($exists['sha'])) {
                    $results['exists']++;
                    $results['details'][] = array(
                        'url' => $url,
                        'status' => 'exists',
                        'message' => __('File already exists on GitHub', 'wp-cdn-integration')
                    );
                    continue;
                }
                
                // Upload file to GitHub
                $success = $this->github_api->upload_file($local_path, $remote_path);
                
                if ($success) {
                    $results['success']++;
                    $results['details'][] = array(
                        'url' => $url,
                        'status' => 'uploaded',
                        'message' => __('Successfully uploaded to GitHub', 'wp-cdn-integration')
                    );
                } else {
                    $results['failed']++;
                    $results['details'][] = array(
                        'url' => $url,
                        'status' => 'failed',
                        'message' => __('Failed to upload to GitHub', 'wp-cdn-integration')
                    );
                }
            } catch (Exception $e) {
                $results['failed']++;
                $results['details'][] = array(
                    'url' => $url,
                    'status' => 'failed',
                    'message' => $e->getMessage()
                );
            }
        }
        
        return $results;
    }

    /**
     * AJAX handler for viewing log.
     *
     * @since 1.0.0
     */
    public function ajax_view_log() {
        // Check nonce
        if (!check_ajax_referer('wp-cdn-integration-nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'wp-cdn-integration')));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'wp-cdn-integration')));
        }
        
        $logger = new CDN_Integration_Logger();
        $log_lines = $logger->get_log_lines(1000);
        
        if (empty($log_lines)) {
            wp_send_json_success(array(
                'content' => __('Log file is empty or does not exist.', 'wp-cdn-integration')
            ));
        } else {
            wp_send_json_success(array(
                'content' => implode("\n", $log_lines)
            ));
        }
    }
}