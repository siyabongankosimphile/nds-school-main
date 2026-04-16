<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Centralized error and event logging for NDS plugin
 * - Writes line-delimited JSON to wp-uploads/nds-logs/nds.log
 * - Captures PHP errors, uncaught exceptions, and fatal shutdowns
 */

if (!function_exists('nds_get_log_file_path')) {
    function nds_get_log_file_path()
    {
        $uploads = wp_get_upload_dir();
        $dir = trailingslashit($uploads['basedir']) . 'nds-logs/';

        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }

        return $dir . 'nds.log';
    }
}

if (!function_exists('nds_log')) {
    function nds_log(string $level, string $message, array $context = []): void
    {
        // Avoid logging in CLI unit tests without WP loaded
        if (!function_exists('wp_json_encode')) {
            return;
        }

        $entry = [
            'ts' => gmdate('c'),
            'level' => strtoupper($level),
            'message' => $message,
            'context' => $context,
            'url' => isset($_SERVER['REQUEST_URI']) ? esc_url_raw($_SERVER['REQUEST_URI']) : null,
            'user' => get_current_user_id(),
        ];

        $line = wp_json_encode($entry) . "\n";
        $file = nds_get_log_file_path();
        // Silently ignore write failures
        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }
}

if (!function_exists('nds_log_wpdb_error')) {
    function nds_log_wpdb_error(string $operation, ?string $sql = null): void
    {
        global $wpdb;
        if (!empty($wpdb->last_error)) {
            nds_log('ERROR', 'wpdb error during ' . $operation, [
                'error' => $wpdb->last_error,
                'sql' => $sql ?: (property_exists($wpdb, 'last_query') ? $wpdb->last_query : null),
            ]);
        }
    }
}

// PHP error handler
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    // Respect @ operator
    if (!(error_reporting() & $errno)) {
        return false;
    }

    $levels = [
        E_ERROR => 'ERROR',
        E_WARNING => 'WARNING',
        E_PARSE => 'ERROR',
        E_NOTICE => 'NOTICE',
        E_CORE_ERROR => 'ERROR',
        E_CORE_WARNING => 'WARNING',
        E_COMPILE_ERROR => 'ERROR',
        E_COMPILE_WARNING => 'WARNING',
        E_USER_ERROR => 'ERROR',
        E_USER_WARNING => 'WARNING',
        E_USER_NOTICE => 'NOTICE',
        E_STRICT => 'NOTICE',
        E_RECOVERABLE_ERROR => 'ERROR',
        E_DEPRECATED => 'NOTICE',
        E_USER_DEPRECATED => 'NOTICE',
    ];

    $level = $levels[$errno] ?? 'ERROR';
    nds_log($level, $errstr, ['file' => $errfile, 'line' => $errline]);
    // Continue with PHP internal handler for fatal-like levels
    return false;
});

// Uncaught exception handler
set_exception_handler(function ($ex) {
    nds_log('ERROR', 'Uncaught exception: ' . get_class($ex), [
        'message' => $ex->getMessage(),
        'file' => $ex->getFile(),
        'line' => $ex->getLine(),
        'trace' => $ex->getTraceAsString(),
    ]);
});

// Fatal shutdown capture
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        nds_log('ERROR', 'Fatal shutdown', $error);
    }
});

/**
 * Convenience wrapper for DB writes; logs errors automatically.
 */
if (!function_exists('nds_db_insert')) {
    function nds_db_insert(string $table, array $data, ?array $format = null)
    {
        global $wpdb;
        $result = $wpdb->insert($table, $data, $format);
        nds_log_wpdb_error('insert', $wpdb->last_query ?? null);
        return $result;
    }
}

if (!function_exists('nds_db_update')) {
    function nds_db_update(string $table, array $data, array $where, ?array $format = null, ?array $where_format = null)
    {
        global $wpdb;
        $result = $wpdb->update($table, $data, $where, $format, $where_format);
        nds_log_wpdb_error('update', $wpdb->last_query ?? null);
        return $result;
    }
}

if (!function_exists('nds_db_query')) {
    function nds_db_query(string $sql)
    {
        global $wpdb;
        $result = $wpdb->query($sql);
        nds_log_wpdb_error('query', $sql);
        return $result;
    }
}


