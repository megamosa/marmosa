<?php
/**
 * Class for GitHub API interactions.
 *
 * @since 1.0.0
 */
class CDN_Integration_Github_API {

    /**
     * GitHub API base URL.
     */
    const API_URL = 'https://api.github.com';

    /**
     * The helper instance.
     *
     * @since 1.0.0
     * @access protected
     * @var CDN_Integration_Helper $helper
     */
    protected $helper;

    /**
     * Initialize the GitHub API.
     *
     * @since 1.0.0
     * @param CDN_Integration_Helper $helper The helper instance.
     */
    public function __construct($helper) {
        $this->helper = $helper;
    }

    /**
     * Test GitHub connection.
     *
     * @since 1.0.0
     * @return bool True if connection successful, false otherwise.
     */
    public function test_connection() {
        try {
            $username = $this->helper->get_github_username();
            $repository = $this->helper->get_github_repository();
            
            $this->helper->log("Testing GitHub connection for {$username}/{$repository}", 'info');
            
            if (empty($username) || empty($repository)) {
                $this->helper->log('GitHub username or repository is not configured', 'error');
                return false;
            }
            
            // Build URL to check repository permissions directly
            $url = self::API_URL . "/repos/{$username}/{$repository}";
            $this->helper->log("Testing GitHub connection: GET {$url}", 'info');
            
            $response = $this->make_request($url);
            
            if (is_wp_error($response)) {
                $this->helper->log('GitHub API error: ' . $response->get_error_message(), 'error');
                return false;
            }
            
            $status_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            
            $this->helper->log("GitHub connection test response code: {$status_code}", 'info');
            
            if ($status_code >= 200 && $status_code < 300) {
                $response_data = json_decode($body, true);
                
                // Log repository details
                $this->helper->log("Repository info fetched successfully", 'debug');
                
                // Check if we have push permission directly from the repository data
                if (isset($response_data['permissions']) && 
                    isset($response_data['permissions']['push']) && 
                    $response_data['permissions']['push'] === true) {
                    
                    $this->helper->log("GitHub permissions test successful: Read and Write access confirmed", 'info');
                    return true;
                } else {
                    $this->helper->log("GitHub permissions test failed: Write access not confirmed", 'error');
                    return false;
                }
            }
            
            $this->helper->log("GitHub API error: Status {$status_code}, Response: {$body}", 'error');
            return false;
        } catch (Exception $e) {
            $this->helper->log("GitHub connection test exception: " . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Get repository contents for a specific file or path.
     *
     * @since 1.0.0
     * @param string $path File or directory path in the repository.
     * @return array|bool Response data on success, false on failure.
     */
    public function get_contents($path = '') {
        try {
            $username = $this->helper->get_github_username();
            $repository = $this->helper->get_github_repository();
            $branch = $this->helper->get_github_branch();
            
            $this->helper->log("Getting repository contents for path: {$path}", 'debug');
            
            if (empty($username) || empty($repository)) {
                $this->helper->log('GitHub username or repository is not configured', 'error');
                return false;
            }
            
            $url = self::API_URL . "/repos/{$username}/{$repository}/contents/{$path}";
            if (!empty($branch)) {
                $url .= "?ref={$branch}";
            }
            
            $this->helper->log("GitHub API URL: {$url}", 'debug');
            
            $response = $this->make_request($url);
            
            if (is_wp_error($response)) {
                $this->helper->log('GitHub API error: ' . $response->get_error_message(), 'error');
                return false;
            }
            
            $status_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            
            if ($status_code >= 200 && $status_code < 300) {
                $this->helper->log("Successfully got contents from GitHub", 'debug');
                return json_decode($body, true);
            } else if ($status_code == 404) {
                // File doesn't exist, not an error
                $this->helper->log("Path not found on GitHub: {$path}", 'debug');
                return false;
            }
            
            $this->helper->log("Failed to get contents. Status: {$status_code}, Response: {$body}", 'error');
            return false;
        } catch (Exception $e) {
            $this->helper->log("Exception when getting contents for path: {$path}. Error: " . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Upload a file to the repository.
     *
     * @since 1.0.0
     * @param string $local_path  Local file path.
     * @param string $remote_path Remote file path in the repository.
     * @return bool True on success, false on failure.
     */
    public function upload_file($local_path, $remote_path) {
        try {
            $this->helper->log("Starting upload of file: {$local_path} to {$remote_path}", 'info');
            
            if (!file_exists($local_path)) {
                $this->helper->log("Local file does not exist: {$local_path}", 'error');
                return false;
            }
            
            // Log file metadata
            $file_size = filesize($local_path);
            $file_type = mime_content_type($local_path);
            $this->helper->log("File size: {$file_size} bytes, File type: {$file_type}", 'debug');
            
            // Read file content with detailed error handling
            $file_content = @file_get_contents($local_path);
            if ($file_content === false) {
                $error = error_get_last();
                $this->helper->log("Failed to read local file: {$local_path}. Error: " . print_r($error, true), 'error');
                return false;
            }
            
            $this->helper->log("Successfully read file content, length: " . strlen($file_content) . " bytes", 'debug');
            
            // Make sure remotePath doesn't start with a slash
            $remote_path = ltrim($remote_path, '/');
            $this->helper->log("Normalized remote path: {$remote_path}", 'debug');
            
            // Check if the file already exists in the repository
            $this->helper->log("Checking if file already exists in repository", 'debug');
            $existing_file = $this->get_contents($remote_path);
            $sha = null;
            
            if ($existing_file && isset($existing_file['sha'])) {
                $sha = $existing_file['sha'];
                $this->helper->log("File already exists with SHA: {$sha}", 'debug');
            } else {
                $this->helper->log("File does not exist in repository", 'debug');
            }
            
            // Create the commit message
            $file_name = basename($local_path);
            $message = $sha 
                ? "Update {$file_name} via WordPress CDN Integration" 
                : "Add {$file_name} via WordPress CDN Integration";
                
            $this->helper->log("Commit message: {$message}", 'debug');
            
            return $this->create_or_update_file($remote_path, $file_content, $message, $sha);
        } catch (Exception $e) {
            $this->helper->log("Exception when uploading file: {$local_path} to {$remote_path}. Error: " . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Create or update a file in the repository.
     *
     * @since 1.0.0
     * @param string $path     File path in the repository.
     * @param string $content  File content.
     * @param string $message  Commit message.
     * @param string $sha      Optional. Existing file SHA for updates.
     * @return bool True on success, false on failure.
     */
    public function create_or_update_file($path, $content, $message, $sha = null) {
        try {
            $username = $this->helper->get_github_username();
            $repository = $this->helper->get_github_repository();
            $branch = $this->helper->get_github_branch();
            
            $this->helper->log("Creating/updating file on GitHub: {$path}", 'debug');
            
            if (empty($username) || empty($repository)) {
                $this->helper->log('GitHub username or repository is not configured', 'error');
                return false;
            }
            
            $url = self::API_URL . "/repos/{$username}/{$repository}/contents/{$path}";
            $this->helper->log("GitHub API URL: {$url}", 'debug');
            
            // Base64 encode content
            $encoded_content = base64_encode($content);
            $this->helper->log("Base64 encoded content length: " . strlen($encoded_content), 'debug');
            
            if (empty($encoded_content) && !empty($content)) {
                $this->helper->log("Failed to base64 encode file content", 'error');
                return false;
            }
            
            $data = array(
                'message' => $message,
                'content' => $encoded_content,
                'branch' => $branch
            );
            
            // If SHA is provided, it means we're updating an existing file
            if ($sha) {
                $data['sha'] = $sha;
                $this->helper->log("Updating existing file with SHA: {$sha}", 'debug');
            } else {
                $this->helper->log("Creating new file", 'debug');
            }
            
            $response = $this->make_request($url, 'PUT', $data);
            
            if (is_wp_error($response)) {
                $this->helper->log('GitHub API error: ' . $response->get_error_message(), 'error');
                return false;
            }
            
            $status_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            
            $this->helper->log("GitHub API create/update status: {$status_code}", 'debug');
            
            if ($status_code >= 200 && $status_code < 300) {
                $this->helper->log("Successfully created/updated file on GitHub", 'info');
                return true;
            }
            
            // Log detailed error information
            $this->helper->log("Failed to create/update file. Status: {$status_code}, Response: {$body}", 'error');
            
            if (!empty($body)) {
                $response_data = json_decode($body, true);
                if (isset($response_data['message'])) {
                    $this->helper->log("GitHub error message: " . $response_data['message'], 'error');
                }
            }
            
            return false;
        } catch (Exception $e) {
            $this->helper->log("Exception when creating/updating file: {$path}. Error: " . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Make an HTTP request to the GitHub API.
     *
     * @since 1.0.0
     * @param string $url    API endpoint URL.
     * @param string $method Optional. Request method. Default is 'GET'.
     * @param array  $data   Optional. Request data.
     * @return array|WP_Error Response array or WP_Error.
     */
    protected function make_request($url, $method = 'GET', $data = null) {
        $args = array(
            'timeout'     => 30,
            'redirection' => 5,
            'method'      => $method,
            'headers'     => array(
                'Authorization' => 'token ' . $this->helper->get_github_token(),
                'User-Agent'    => 'WordPress-CDN-Integration/1.0.0',
                'Accept'        => 'application/vnd.github.v3+json',
                'Content-Type'  => 'application/json',
            ),
        );
        
        if (!is_null($data) && ($method === 'POST' || $method === 'PUT')) {
            $args['body'] = json_encode($data);
        }
        
        return wp_remote_request($url, $args);
    }
}