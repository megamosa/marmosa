<?php
/**
 * The class responsible for logging plugin activities.
 *
 * @since 1.0.0
 */
class CDN_Integration_Logger {

    /**
     * Log file path.
     *
     * @since 1.0.0
     * @access protected
     * @var string $log_file Path to log file.
     */
    protected $log_file;

    /**
     * Initialize the logger.
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->log_file = WP_CDN_INTEGRATION_LOG_FILE;
    }

    /**
     * Log a message.
     *
     * @since 1.0.0
     * @param string $message The message to log.
     * @param string $level   Optional. The log level. Default is 'info'. Accepts 'info', 'debug', 'warning', 'error'.
     */
    public function log($message, $level = 'info') {
        if (empty($message)) {
            return;
        }

        // Format log entry
        $timestamp = current_time('mysql');
        $level_uppercase = strtoupper($level);
        $log_entry = "[{$timestamp}] [{$level_uppercase}] {$message}" . PHP_EOL;

        // Append to log file
        $this->write_to_log($log_entry);
    }

    /**
     * Write a message to the log file.
     *
     * @since 1.0.0
     * @access protected
     * @param string $entry The log entry.
     */
    protected function write_to_log($entry) {
        // Create log file if it doesn't exist
        if (!file_exists($this->log_file)) {
            // Create directory if it doesn't exist
            $log_dir = dirname($this->log_file);
            if (!is_dir($log_dir)) {
                wp_mkdir_p($log_dir);
            }

            // Create log file
            touch($this->log_file);
        }

        // Append to log file
        error_log($entry, 3, $this->log_file);
    }

    /**
     * Get last n lines from log file.
     *
     * @since 1.0.0
     * @param int $lines Optional. Number of lines to get. Default is 100.
     * @return array Array of log lines.
     */
    public function get_log_lines($lines = 100) {
        $result = array();
        
        if (!file_exists($this->log_file)) {
            return $result;
        }
        
        $file = new SplFileObject($this->log_file, 'r');
        $file->seek(PHP_INT_MAX);
        $total_lines = $file->key();
        
        $start_line = ($total_lines > $lines) ? $total_lines - $lines : 0;
        
        $file->seek($start_line);
        
        while (!$file->eof()) {
            $line = $file->current();
            $file->next();
            
            if ($line !== false) {
                $result[] = htmlspecialchars($line);
            }
        }
        
        return $result;
    }

    /**
     * Clear the log file.
     *
     * @since 1.0.0
     * @return bool True on success, false on failure.
     */
    public function clear_log() {
        if (file_exists($this->log_file)) {
            return file_put_contents($this->log_file, '');
        }
        
        return false;
    }
}