<?php
/**
 * Class for jsDelivr API interactions.
 *
 * @since 1.0.0
 */
class CDN_Integration_Jsdelivr_API {

    /**
     * jsDelivr Purge API URL.
     */
    const PURGE_API_URL = 'https://purge.jsdelivr.net';

    /**
     * The helper instance.
     *
     * @since 1.0.0
     * @access protected
     * @var CDN_Integration_Helper $helper
     */
    protected $helper;

    /**
     * Initialize the jsDelivr API.
     *
     * @since 1.0.0
     * @param CDN_Integration_Helper $helper The helper instance.
     */
    public function __construct($helper) {
        $this->helper = $helper;
    }

    /**
     * Purge all files in the repository from jsDelivr cache.
     *
     * @since 1.0.0
     * @return bool True on success, false on failure.
     */
    public function purge_all() {
        try {
            $username = $this->helper->get_github_username();
            $repository = $this->helper->get_github_repository();
            $branch = $this->helper->get_github_branch();
            
            if (!$username || !$repository) {
                $this->helper->log('GitHub configuration is incomplete', 'error');
                throw new Exception('GitHub configuration is incomplete');
            }
            
            // Create the URL to purge everything in the repository
            // Format: https://purge.jsdelivr.net/gh/username/repository@branch/
            $purge_url = self::PURGE_API_URL . "/gh/{$username}/{$repository}@{$branch}/";
            
            $this->helper->log("Purging all files from jsDelivr using URL: {$purge_url}", 'info');
            
            $response = wp_remote_get($purge_url, array(
                'timeout'     => 30,
                'headers'     => array(
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                    'User-Agent'    => 'WordPress-CDN-Integration/1.0.0'
                )
            ));
            
            if (is_wp_error($response)) {
                $this->helper->log('jsDelivr API error: ' . $response->get_error_message(), 'error');
                return false;
            }
            
            $status_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            
            $this->helper->log("jsDelivr Purge response code: {$status_code}", 'debug');
            $this->helper->log("jsDelivr Purge response: {$body}", 'debug');
            
            $success = ($status_code >= 200 && $status_code < 300);
            
            if ($success) {
                $this->helper->log("Successfully purged all files from jsDelivr", 'info');
                
                // Also purge by file type to ensure complete purge
                $this->purge_file_types(array('js', 'css', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'woff', 'woff2', 'ttf'));
                
                return true;
            }
            
            $this->helper->log("Failed to purge files. Status: {$status_code}, Response: {$body}", 'error');
            
            return false;
        } catch (Exception $e) {
            $this->helper->log("Exception when purging files: " . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Purge specific file types.
     *
     * @since 1.0.0
     * @param array $file_types Array of file extensions to purge.
     * @return bool True on success, false on failure.
     */
    public function purge_file_types(array $file_types = array('js', 'css')) {
        $username = $this->helper->get_github_username();
        $repository = $this->helper->get_github_repository();
        $branch = $this->helper->get_github_branch();
        
        if (!$username || !$repository) {
            $this->helper->log('GitHub configuration is incomplete', 'error');
            return false;
        }
        
        $success = true;
        
        foreach ($file_types as $type) {
            // Purge by file type using the jsDelivr wildcard syntax
            $purge_url = self::PURGE_API_URL . "/gh/{$username}/{$repository}@{$branch}/**/*.{$type}";
            
            $this->helper->log("Purging all .{$type} files from jsDelivr", 'info');
            
            try {
                $response = wp_remote_get($purge_url, array(
                    'timeout'     => 30,
                    'headers'     => array(
                        'Content-Type'  => 'application/json',
                        'Accept'        => 'application/json',
                        'User-Agent'    => 'WordPress-CDN-Integration/1.0.0'
                    )
                ));
                
                if (is_wp_error($response)) {
                    $success = false;
                    $this->helper->log('Error purging ' . $type . ' files: ' . $response->get_error_message(), 'error');
                    continue;
                }
                
                $status_code = wp_remote_retrieve_response_code($response);
                $body = wp_remote_retrieve_body($response);
                
                if ($status_code >= 200 && $status_code < 300) {
                    $this->helper->log("Successfully purged all .{$type} files from jsDelivr", 'info');
                } else {
                    $success = false;
                    $this->helper->log("Failed to purge .{$type} files. Status: {$status_code}, Response: {$body}", 'error');
                }
            } catch (Exception $e) {
                $success = false;
                $this->helper->log("Exception when purging .{$type} files: " . $e->getMessage(), 'error');
            }
        }
        
        return $success;
    }

    /**
     * Purge a specific file.
     *
     * @since 1.0.0
     * @param string $file_path File path in the repository.
     * @return bool True on success, false on failure.
     */
    public function purge_file($file_path) {
        try {
            $username = $this->helper->get_github_username();
            $repository = $this->helper->get_github_repository();
            $branch = $this->helper->get_github_branch();
            
            if (!$username || !$repository) {
                $this->helper->log('GitHub configuration is incomplete', 'error');
                return false;
            }
            
            // Ensure file path starts without a slash
            $file_path = ltrim($file_path, '/');
            
            // Create the URL to purge the specific file
            $purge_url = self::PURGE_API_URL . "/gh/{$username}/{$repository}@{$branch}/{$file_path}";
            
            $this->helper->log("Purging specific file from jsDelivr: {$file_path}", 'info');
            
            $response = wp_remote_get($purge_url, array(
                'timeout'     => 30,
                'headers'     => array(
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                    'User-Agent'    => 'WordPress-CDN-Integration/1.0.0'
                )
            ));
            
            if (is_wp_error($response)) {
                $this->helper->log('jsDelivr API error: ' . $response->get_error_message(), 'error');
                return false;
            }
            
            $status_code = wp_remote_retrieve_response_code($response);
            
            if ($status_code >= 200 && $status_code < 300) {
                $this->helper->log("Successfully purged file from jsDelivr: {$file_path}", 'info');
                return true;
            }
            
            $body = wp_remote_retrieve_body($response);
            $this->helper->log("Failed to purge file. Status: {$status_code}, Response: {$body}", 'error');
            
            return false;
        } catch (Exception $e) {
            $this->helper->log("Exception when purging file: " . $e->getMessage(), 'error');
            return false;
        }
    }
}