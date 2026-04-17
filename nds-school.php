<?php

/**
 * nds-school.php
 * Plugin Name: NDS School
 * Plugin URI: https://kayiseit.co.za
 * Description: A modern school management system for WordPress.
 * Version: 1.0
 * Author: Thando Hlophe
 * Author URI: https://kayiseit.co.za
 * License: GPL2
 */

if (!defined('ABSPATH')) {
    exit; // Prevent direct access
}

// Define plugin directory constant
if (!defined('NDS_SCHOOL_PLUGIN_DIR')) {
    define('NDS_SCHOOL_PLUGIN_DIR', plugin_dir_path(__FILE__));
}

// Include calendar functions for AJAX handlers
require_once NDS_SCHOOL_PLUGIN_DIR . 'includes/calendar-functions.php';
require_once NDS_SCHOOL_PLUGIN_DIR . 'includes/notification-functions.php';
require_once NDS_SCHOOL_PLUGIN_DIR . 'includes/program-functions.php';
require_once NDS_SCHOOL_PLUGIN_DIR . 'includes/lecturer-portal-functions.php';

/**
 * Shared password policy for NDS user-facing authentication.
 */
function nds_is_strong_password($password) {
    if (!is_string($password) || strlen($password) < 8) {
        return false;
    }

    return preg_match('/[A-Z]/', $password)
        && preg_match('/[0-9]/', $password)
        && preg_match('/[^A-Za-z0-9]/', $password);
}

function nds_get_password_policy_message() {
    return 'Password must be at least 8 characters long and include at least 1 uppercase letter, 1 number, and 1 special character.';
}

function nds_add_password_policy_error($errors, $code = 'nds_weak_password', $message = '') {
    if (!($errors instanceof WP_Error)) {
        return;
    }

    $errors->add(
        $code,
        $message ?: nds_get_password_policy_message()
    );
}

add_filter('wp_authenticate_user', function ($user, $password) {
    if (is_wp_error($user)) {
        return $user;
    }

    // Never block privileged admin logins based on legacy password strength.
    if ($user instanceof WP_User && user_can($user, 'manage_options')) {
        return $user;
    }

    if (!is_string($password) || $password === '') {
        return $user;
    }

    if (!nds_is_strong_password($password)) {
        return new WP_Error(
            'nds_weak_password_login',
            __('Your password does not meet the current security requirements. Please reset your password and use a stronger one.', 'nds-school')
        );
    }

    return $user;
}, 10, 2);

add_action('validate_password_reset', function ($errors, $user) {
    if (!($errors instanceof WP_Error) || !($user instanceof WP_User)) {
        return;
    }

    $password = isset($_POST['pass1']) ? (string) wp_unslash($_POST['pass1']) : '';
    if ($password !== '' && !nds_is_strong_password($password)) {
        nds_add_password_policy_error($errors, 'nds_weak_password_reset');
    }
}, 10, 2);

add_action('user_profile_update_errors', function ($errors, $update, $user) {
    if (!($errors instanceof WP_Error)) {
        return;
    }

    $password = isset($_POST['pass1']) ? (string) wp_unslash($_POST['pass1']) : '';
    if ($password !== '' && !nds_is_strong_password($password)) {
        nds_add_password_policy_error($errors, 'nds_weak_password_profile');
    }
}, 10, 3);

add_filter('login_message', function ($message) {
    $policy = esc_html(nds_get_password_policy_message());
    $reset_url = esc_url(wp_lostpassword_url());

    $notice = '<div class="message"><p>' . $policy . '</p><p><a href="' . $reset_url . '">Forgot password?</a></p></div>';

    return $message . $notice;
});

add_filter('wp_login_errors', function ($errors) {
    if (!($errors instanceof WP_Error) || !$errors->get_error_code('nds_weak_password_login')) {
        return $errors;
    }

    $errors->remove('nds_weak_password_login');
    $errors->add(
        'nds_weak_password_login',
        sprintf(
            '%s <a href="%s">Reset your password</a>.',
            esc_html__('Your password does not meet the current security requirements.', 'nds-school'),
            esc_url(wp_lostpassword_url())
        )
    );

    return $errors;
});

/**
 * Restrict WordPress admin area for subscribers when this plugin is active.
 * - Blocks /wp-admin/ for users with only the 'subscriber' role
 * - Allows AJAX, cron, and login/logout to function normally
 * - Can be toggled on/off via Settings page
 */
add_action('init', function () {
    // Check if blocking is enabled (default: enabled)
    $block_subscribers = get_option('nds_block_subscribers_backend', '1');
    if ($block_subscribers !== '1') {
        return; // Feature is disabled
    }

    // Only care about admin-side requests
    if (!is_admin()) {
        return;
    }

    // Allow AJAX and CRON requests to pass through
    if ((defined('DOING_AJAX') && DOING_AJAX) || (defined('DOING_CRON') && DOING_CRON)) {
        return;
    }

    // Front-end forms post to wp-admin/admin-post.php; do not block those requests.
    $script_name = isset($_SERVER['SCRIPT_NAME']) ? wp_unslash($_SERVER['SCRIPT_NAME']) : '';
    $request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';
    if (($script_name !== '' && basename($script_name) === 'admin-post.php') || strpos($request_uri, '/wp-admin/admin-post.php') !== false) {
        return;
    }

    // Let WordPress handle non-logged-in users (login screen, etc.)
    if (!is_user_logged_in()) {
        return;
    }

    $user = wp_get_current_user();

    // If the user is a pure subscriber, keep them out of wp-admin
    if (in_array('subscriber', (array) $user->roles, true)) {
        // Option: send learners to the /portal/ dashboard instead of homepage
        $redirect_url = home_url('/portal/');
        wp_safe_redirect($redirect_url);
        exit;
    }
});

// Hide the admin bar on the front-end for subscribers (controlled by setting)
add_filter('show_admin_bar', function ($show) {
    $block_subscribers = get_option('nds_block_subscribers_backend', '1');
    $hide_admin_bar = get_option('nds_hide_subscriber_admin_bar', '0');
    
    if ($block_subscribers === '1' && $hide_admin_bar === '1') {
        if (is_user_logged_in() && current_user_can('subscriber')) {
            return false;
        }
    }
    return $show;
});

/**
 * Ensure learners (subscribers) are redirected to the site homepage on logout,
 * instead of being sent to the default wp-login.php screen.
 */
add_filter('logout_redirect', function ($redirect_to, $requested_redirect_to, $user) {
    // Normalize $user to a WP_User instance when possible
    if ($user && !($user instanceof WP_User)) {
        $user = get_user_by('id', (int) $user);
    }

    if ($user instanceof WP_User && in_array('subscriber', (array) $user->roles, true)) {
        return home_url('/');
    }

    return $redirect_to;
}, 10, 3);

/**
 * Redirect lecturers to the staff portal overview right after login.
 */
add_filter('login_redirect', function ($redirect_to, $requested_redirect_to, $user) {
    if (is_wp_error($user) || !($user instanceof WP_User)) {
        return $redirect_to;
    }

    // Keep admins on default/admin flow.
    if (user_can($user, 'manage_options')) {
        return $redirect_to;
    }

    global $wpdb;
    $staff_row = $wpdb->get_row($wpdb->prepare(
        "SELECT id, role FROM {$wpdb->prefix}nds_staff WHERE user_id = %d LIMIT 1",
        (int) $user->ID
    ), ARRAY_A);

    // Fallback by email when user_id mapping is not set yet.
    if (empty($staff_row) && !empty($user->user_email)) {
        $staff_row = $wpdb->get_row($wpdb->prepare(
            "SELECT id, role FROM {$wpdb->prefix}nds_staff WHERE email = %s LIMIT 1",
            $user->user_email
        ), ARRAY_A);
    }

    if (!empty($staff_row) && isset($staff_row['role']) && strtolower(trim((string) $staff_row['role'])) === 'lecturer') {
        return home_url('/staff-portal/?tab=overview');
    }

    return $redirect_to;
}, 10, 3);

/**
 * Add Student Portal link to frontend navigation for all visitors,
 * and keep Staff Portal visible for lecturer accounts.
 */
add_filter('wp_nav_menu_items', function ($items, $args) {
    if (is_admin()) {
        return $items;
    }

    $student_portal_url = esc_url(home_url('/portal/'));
    $student_label = esc_html__('Student Portal', 'nds-school');

    // Prevent duplicates if theme/menu already has this link.
    if (strpos($items, $student_portal_url) === false && stripos($items, 'menu-item-nds-student-portal') === false) {
        $items .= '<li class="menu-item menu-item-nds-student-portal"><a href="' . $student_portal_url . '">' . $student_label . '</a></li>';
    }

    if (!is_user_logged_in()) {
        return $items;
    }

    $user = wp_get_current_user();
    if (!($user instanceof WP_User) || empty($user->ID)) {
        return $items;
    }

    global $wpdb;
    $staff_row = $wpdb->get_row($wpdb->prepare(
        "SELECT role FROM {$wpdb->prefix}nds_staff WHERE user_id = %d LIMIT 1",
        (int) $user->ID
    ), ARRAY_A);

    if (empty($staff_row) && !empty($user->user_email)) {
        $staff_row = $wpdb->get_row($wpdb->prepare(
            "SELECT role FROM {$wpdb->prefix}nds_staff WHERE email = %s LIMIT 1",
            $user->user_email
        ), ARRAY_A);
    }

    if (empty($staff_row) || !isset($staff_row['role']) || strtolower(trim((string) $staff_row['role'])) !== 'lecturer') {
        return $items;
    }

    $staff_portal_url = esc_url(home_url('/staff-portal/?tab=overview'));
    $staff_label = esc_html__('Staff Portal', 'nds-school');

    // Prevent duplicates if theme/menu already has this link.
    if (strpos($items, $staff_portal_url) !== false || stripos($items, 'staff-portal') !== false) {
        return $items;
    }

    $items .= '<li class="menu-item menu-item-nds-staff-portal"><a href="' . $staff_portal_url . '">' . $staff_label . '</a></li>';
    return $items;
}, 10, 2);

function enqueue_custom_scripts() {
    // Only load on admin pages
    if (!is_admin()) {
        return;
    }
    
    $current_page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
    
    // Only load scripts on NDS plugin pages
    if (strpos($current_page, 'nds-') !== 0) {
        return;
    }
    
    // Media upload script - only load on pages that need it
    $media_pages = array('nds-add-student', 'nds-edit-student', 'nds-add-learner', 'nds-edit-learner', 'nds-hero-carousel', 'nds-add-recipe', 'nds-recipe-details', 'nds-content-management', 'nds-recipes', 'nds-content', 'nds-staff', 'nds-staff-management', 'nds-edit-staff', 'nds-add-staff');
    if (in_array($current_page, $media_pages)) {
        wp_enqueue_media();
        wp_enqueue_script('mediaqq-js', plugin_dir_url(__FILE__) . 'assets/js/media-upload.js', array('jquery'), null, true);
    }
    
    // Main JS - only load on pages that need interactive features
    $interactive_pages = array('nds-academy', 'nds-students', 'nds-courses', 'nds-programs', 'nds-faculties', 'nds-staff');
    if (in_array($current_page, $interactive_pages) || strpos($current_page, 'nds-edit-') === 0 || strpos($current_page, 'nds-add-') === 0) {
        wp_enqueue_script('ndsJSschool-js', plugin_dir_url(__FILE__) . 'assets/js/ndsJSschool.js', array('jquery'), null, true);
    }
    
    // SweetAlert2 - only load on pages that show alerts/confirmations
    $alert_pages = array('nds-students', 'nds-courses', 'nds-programs', 'nds-staff', 'nds-applications', 'nds-learner-management');
    if (in_array($current_page, $alert_pages) || strpos($current_page, 'nds-edit-') === 0 || strpos($current_page, 'nds-add-') === 0) {
        wp_enqueue_style('sweetalert2-css', 'https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css', array(), '11.0.0');
        wp_enqueue_script('sweetalert2-js', 'https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js', array(), '11.0.0', true);
        wp_enqueue_script('nds-sweetalert', plugin_dir_url(__FILE__) . 'assets/js/nds-sweetalert.js', array('sweetalert2-js'), filemtime(plugin_dir_path(__FILE__) . 'assets/js/nds-sweetalert.js'), true);
    }
    
    // Toasts - only load on pages that show toast notifications
    $toast_pages = array('nds-students', 'nds-courses', 'nds-programs', 'nds-learner-management');
    if (in_array($current_page, $toast_pages)) {
        wp_enqueue_style('toastify-css', 'https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css', array(), null, 'all');
        wp_enqueue_script('toastify-js', 'https://cdn.jsdelivr.net/npm/toastify-js', array(), null, true);
        wp_enqueue_script('nds-toasts', plugin_dir_url(__FILE__) . 'assets/js/nds-toasts.js', array('toastify-js'), filemtime(plugin_dir_path(__FILE__) . 'assets/js/nds-toasts.js'), true);
    }
}
add_action('admin_enqueue_scripts', 'enqueue_custom_scripts');

function nds_admin_enqueue_styles()
{
    if (!is_admin()) return;

    // Detect any NDS plugin page globally (robust against menu slug/screen ID changes)
    $current_page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
    if (strpos($current_page, 'nds-') !== 0) {
        return; // not one of our plugin pages
    }

    wp_enqueue_style('nds-icons', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css', array(), null, 'all');

    // Load Tailwind CSS with high priority
    $tailwind_file = plugin_dir_path(__FILE__) . 'assets/css/frontend.css';
    if (file_exists($tailwind_file)) {
        wp_enqueue_style('nds-tailwindcss', plugin_dir_url(__FILE__) . 'assets/css/frontend.css', array(), filemtime($tailwind_file), 'all');
    }

    // Load additional styles if they exist
    $styles_file = plugin_dir_path(__FILE__) . 'assets/css/styles.css';
    if (file_exists($styles_file)) {
        wp_enqueue_style('nds-stylescss', plugin_dir_url(__FILE__) . 'assets/css/styles.css', array('nds-tailwindcss'), filemtime($styles_file), 'all');
    }
}
add_action('admin_enqueue_scripts', 'nds_admin_enqueue_styles');

// Lightweight schema guard to auto-heal missing tables/columns from third-party plugins
add_action('init', function () {
    // Run at most once every 10 minutes to avoid overhead
    if (get_transient('nds_schema_last_check')) {
        return;
    }
    set_transient('nds_schema_last_check', 1, 10 * MINUTE_IN_SECONDS);

    global $wpdb;
    $needs_migration = false;

    // Check Action Scheduler tables (often required by other plugins like Fluent Forms)
    $as_actions = $wpdb->prefix . 'actionscheduler_actions';
    $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $as_actions));
    if (empty($table_exists)) {
        $needs_migration = true;
    }

    // Check critical students table columns
    $students_table = $wpdb->prefix . 'nds_students';
    $students_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $students_table));
    if (!empty($students_exists)) {
        $cols = $wpdb->get_col("DESC {$students_table}", 0);
        if (!$cols || !in_array('id', $cols, true) || !in_array('email', $cols, true) || !in_array('student_number', $cols, true)) {
            $needs_migration = true;
        }
    } else {
        $needs_migration = true;
    }

    // Check applications core tables (tracking + detailed form)
    $applications_table = $wpdb->prefix . 'nds_applications';
    $applications_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $applications_table));
    if (empty($applications_exists)) {
        $needs_migration = true;
    }

    $application_forms_table = $wpdb->prefix . 'nds_application_forms';
    $application_forms_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $application_forms_table));
    if (empty($application_forms_exists)) {
        $needs_migration = true;
    }

    $activity_log_table = $wpdb->prefix . 'nds_student_activity_log';
    $activity_log_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $activity_log_table));
    if (empty($activity_log_exists)) {
        $needs_migration = true;
    }

    $notifications_table = $wpdb->prefix . 'nds_notifications';
    $notifications_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $notifications_table));
    if (empty($notifications_exists)) {
        $needs_migration = true;
    }

    if ($needs_migration) {
        require_once plugin_dir_path(__FILE__) . 'includes/database.php';
        if (function_exists('nds_school_create_tables')) {
            nds_school_create_tables();
        }
    }
}, 1);

function my_custom_plugin_enqueue_styles() {
    // Only load CSS in frontend or admin if needed
    wp_enqueue_style(
        'my-custom-plugin-style', // handle
        plugin_dir_url(__FILE__) . 'assets/css/custom-style.css', // path
        array(), // dependencies
        '1.0.0', // version
        'all' // media
    );
}
add_action('wp_enqueue_scripts', 'my_custom_plugin_enqueue_styles'); // For frontend
// add_action('admin_enqueue_scripts', 'my_custom_plugin_enqueue_styles'); // For admin panel



// Include necessary files
include_once plugin_dir_path(__FILE__) . 'includes/admin-menu.php';
include_once plugin_dir_path(__FILE__) . 'includes/admin-pages.php';
include_once plugin_dir_path(__FILE__) . 'includes/rooms-management.php';
include_once plugin_dir_path(__FILE__) . 'includes/seed.php';
$migration_schema_file = plugin_dir_path(__FILE__) . 'includes/migrate-to-university-schema.php';
if (file_exists($migration_schema_file)) {
    include_once $migration_schema_file;
}
include_once plugin_dir_path(__FILE__) . 'includes/hero-carousel.php';
include_once plugin_dir_path(__FILE__) . 'includes/hero-carousel-admin.php';
include_once plugin_dir_path(__FILE__) . 'includes/application-functions.php';
include_once plugin_dir_path(__FILE__) . 'includes/staff-roles.php';
include_once plugin_dir_path(__FILE__) . 'public/class-shortcodes.php';

// Initialize shortcodes
new NDS_Shortcodes();


// Activation Hook
function nds_school_activate() {
    require_once plugin_dir_path(__FILE__) . 'includes/database.php';
    nds_school_create_tables();
    
    // Run database migration
    require_once plugin_dir_path(__FILE__) . 'includes/database-migration.php';
    $migration = new NDS_Database_Migration();
    $migration->force_migration();
    
    // Add rewrite rules for recipes
    add_rewrite_rule('^recipe/([0-9]+)/?$', 'index.php?nds_recipe_id=$matches[1]', 'top');
    // Add rewrite rule for student portal
    add_rewrite_rule('^portal/?$', 'index.php?nds_portal=1', 'top');
    // Add rewrite rule for calendar
    add_rewrite_rule('^calendar/?$', 'index.php?nds_calendar=1', 'top');
    
    // Flush rewrite rules
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'nds_school_activate');

// Manual migration runner (one-time use): /wp-admin/admin-post.php?action=nds_run_migrations
add_action('admin_post_nds_run_migrations', 'nds_run_migrations_action');
function nds_run_migrations_action() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    // Rate limiting
    if (!nds_check_rate_limit('migration', 1, 3600)) {
        wp_die('Migration can only be run once per hour. Please wait.');
    }
    
    require_once plugin_dir_path(__FILE__) . 'includes/database-migration.php';
    $migration = new NDS_Database_Migration();
    $migration->force_migration();
    
    wp_redirect(admin_url('admin.php?page=nds-academy&migration=success'));
    exit;
}

// Rate limiting function to prevent abuse
function nds_check_rate_limit($action, $limit = 10, $window = 60) {
    $user_id = get_current_user_id();
    if (!$user_id) return true; // Allow for non-logged in users (though they shouldn't reach here)

    $transient_key = 'nds_rate_limit_' . $user_id . '_' . $action;
    $attempts = get_transient($transient_key);

    if ($attempts === false) {
        $attempts = 0;
    }

    if ($attempts >= $limit) {
        nds_log_error('Rate limit exceeded', array(
            'user_id' => $user_id,
            'action' => $action,
            'attempts' => $attempts
        ), 'warning');
        return false;
    }

    set_transient($transient_key, $attempts + 1, $window);
    return true;
}

// Comprehensive error logging function
function nds_log_error($message, $context = array(), $level = 'error') {
    $log_entry = array(
        'timestamp' => current_time('Y-m-d H:i:s'),
        'level' => $level,
        'message' => $message,
        'context' => $context,
        'user_id' => get_current_user_id(),
        'ip_address' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown',
        'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'unknown'
    );

    // Log to WordPress error log
    error_log('NDS Plugin [' . $level . ']: ' . $message . ' | Context: ' . json_encode($context));

    // Also log to database for better tracking (optional - comment out if not needed)
    global $wpdb;
    $wpdb->insert(
        $wpdb->prefix . 'nds_student_activity_log',
        array(
            'student_id' => 0, // Use 0 for system events
            'actor_id' => get_current_user_id(),
            'action' => 'system_error',
            'action_type' => 'create',
            'old_values' => null,
            'new_values' => json_encode($log_entry),
            'ip_address' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null,
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null
        ),
        array('%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s')
    );
}

function nds_school_run_migrations_action() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    require_once plugin_dir_path(__FILE__) . 'includes/database.php';
    nds_school_create_tables();

    wp_redirect(admin_url('admin.php?page=nds-learner-management&success=migrations_ran'));
    exit;
}
add_action('admin_post_nds_run_migrations', 'nds_school_run_migrations_action');

// Function to clean up expired rate limit transients (run daily)
function nds_cleanup_rate_limits() {
    global $wpdb;

    // Clean up expired transients from options table
    $wpdb->query($wpdb->prepare(
        "DELETE FROM {$wpdb->options}
         WHERE option_name LIKE %s
         AND option_value = '0'",
        $wpdb->esc_like('_transient_nds_rate_limit_') . '%'
    ));

    // Log cleanup
    nds_log_error('Rate limit cleanup completed', array(), 'info');
}

// Schedule daily cleanup of rate limits
if (!wp_next_scheduled('nds_daily_cleanup')) {
    wp_schedule_event(time(), 'daily', 'nds_daily_cleanup');
}
add_action('nds_daily_cleanup', 'nds_cleanup_rate_limits');

/**
 * Helper: get latest application for current student / user
 */
function nds_portal_get_latest_application_for_current_user($allowed_statuses = array()) {
    if (!is_user_logged_in()) {
        return null;
    }

    global $wpdb;
    $wp_user_id = get_current_user_id();
    $student_id = nds_portal_get_current_student_id();

    $apps_table  = $wpdb->prefix . 'nds_applications';
    $forms_table = $wpdb->prefix . 'nds_application_forms';

    $where_clauses = array();
    $params        = array();

    if ($student_id) {
        $where_clauses[] = 'a.student_id = %d';
        $params[]        = $student_id;
    }

    $where_clauses[] = 'a.wp_user_id = %d';
    $params[]        = $wp_user_id;

    $where_sql = implode(' OR ', $where_clauses);

    $status_sql = '';
    if (!empty($allowed_statuses) && is_array($allowed_statuses)) {
        $allowed_statuses = array_values(array_filter(array_map('sanitize_key', $allowed_statuses)));
        if (!empty($allowed_statuses)) {
            $status_placeholders = implode(',', array_fill(0, count($allowed_statuses), '%s'));
            $status_sql = " AND a.status IN ({$status_placeholders})";
            $params = array_merge($params, $allowed_statuses);
        }
    }

    $sql = "
         SELECT a.id, a.application_no, a.status, a.submitted_at,
             COALESCE(af.course_id, a.course_id) AS course_id,
             a.program_id,
             af.course_name, af.level
        FROM {$apps_table} a
        LEFT JOIN {$forms_table} af ON af.application_id = a.id
        WHERE {$where_sql}{$status_sql}
        ORDER BY a.submitted_at DESC
        LIMIT 1
    ";

    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    $row = $wpdb->get_row($wpdb->prepare($sql, $params), ARRAY_A);

    return $row ?: null;
}

/**
 * Helper: get current active qualification enrollment that has not ended yet.
 */
function nds_portal_get_active_qualification_enrollment($student_id, $exclude_course_id = 0) {
    global $wpdb;

    $student_id = (int) $student_id;
    $exclude_course_id = (int) $exclude_course_id;
    if ($student_id <= 0) {
        return null;
    }

    $exclude_sql = '';
    $params = array($student_id);
    if ($exclude_course_id > 0) {
        $exclude_sql = ' AND e.course_id != %d';
        $params[] = $exclude_course_id;
    }

    $sql = "
        SELECT e.id, e.course_id, e.status,
               c.name AS course_name,
               p.name AS program_name,
               s.semester_name,
               s.end_date AS semester_end_date,
               ay.end_date AS year_end_date
        FROM {$wpdb->prefix}nds_student_enrollments e
        LEFT JOIN {$wpdb->prefix}nds_courses c ON c.id = e.course_id
        LEFT JOIN {$wpdb->prefix}nds_programs p ON p.id = c.program_id
        LEFT JOIN {$wpdb->prefix}nds_semesters s ON s.id = e.semester_id
        LEFT JOIN {$wpdb->prefix}nds_academic_years ay ON ay.id = e.academic_year_id
        WHERE e.student_id = %d
          AND e.status IN ('applied','enrolled','waitlisted')
          {$exclude_sql}
          AND (
                (s.end_date IS NOT NULL AND s.end_date >= CURDATE())
                OR (s.end_date IS NULL AND ay.end_date IS NOT NULL AND ay.end_date >= CURDATE())
                OR (s.end_date IS NULL AND ay.end_date IS NULL AND (s.is_active = 1 OR ay.is_active = 1))
          )
        ORDER BY COALESCE(s.end_date, ay.end_date) DESC, e.updated_at DESC, e.id DESC
        LIMIT 1
    ";

    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    $row = $wpdb->get_row($wpdb->prepare($sql, $params), ARRAY_A);
    return $row ?: null;
}

/**
 * Ensure student module registrations table exists.
 */
function nds_portal_ensure_student_modules_table() {
    global $wpdb;

    $table = $wpdb->prefix . 'nds_student_modules';
    $charset_collate = $wpdb->get_charset_collate();

    // Create table if it doesn't exist.
    $sql = "CREATE TABLE IF NOT EXISTS {$table} (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        module_id INT NOT NULL,
        course_id INT NOT NULL DEFAULT 0,
        academic_year_id INT NOT NULL,
        semester_id INT NOT NULL,
        status ENUM('enrolled','withdrawn','completed','failed','cancelled') DEFAULT 'enrolled',
        final_grade VARCHAR(10) DEFAULT NULL,
        final_percentage DECIMAL(5,2) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_student_module_term (student_id, module_id, academic_year_id, semester_id),
        INDEX idx_student (student_id),
        INDEX idx_module (module_id),
        INDEX idx_course (course_id),
        INDEX idx_status (status)
    ) {$charset_collate}";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    // Schema migrations: add course_id if missing (existing installs).
    $col = $wpdb->get_row("SHOW COLUMNS FROM {$table} LIKE 'course_id'");
    if (!$col) {
        $wpdb->query("ALTER TABLE {$table} ADD COLUMN course_id INT NOT NULL DEFAULT 0 AFTER module_id, ADD INDEX idx_course (course_id)");
    }

    // Expand status enum to include 'cancelled' if not already present.
    $col_status = $wpdb->get_row("SHOW COLUMNS FROM {$table} LIKE 'status'");
    if ($col_status && strpos((string) $col_status->Type, 'cancelled') === false) {
        $wpdb->query("ALTER TABLE {$table} MODIFY COLUMN status ENUM('enrolled','withdrawn','completed','failed','cancelled') DEFAULT 'enrolled'");
    }

    return $table;
}

/**
 * Ensure learner quiz attempts table exists.
 */
function nds_portal_ensure_quiz_attempts_table() {
    global $wpdb;

    $table = $wpdb->prefix . 'nds_quiz_attempts';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS {$table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        content_id BIGINT UNSIGNED NOT NULL,
        student_id INT NOT NULL,
        module_id INT NOT NULL,
        course_id INT NOT NULL,
        attempt_no INT NOT NULL DEFAULT 1,
        total_questions INT NOT NULL DEFAULT 0,
        graded_questions INT NOT NULL DEFAULT 0,
        correct_answers INT NOT NULL DEFAULT 0,
        score_percent DECIMAL(5,2) NULL,
        answers_json LONGTEXT NULL,
        submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_content_student (content_id, student_id),
        KEY idx_student (student_id),
        KEY idx_module (module_id)
    ) {$charset_collate}";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    return $table;
}

/**
 * Ensure learner assignment submissions table exists.
 */
function nds_portal_ensure_assignment_submissions_table() {
    global $wpdb;

    $table = $wpdb->prefix . 'nds_assignment_submissions';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS {$table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        content_id BIGINT UNSIGNED NOT NULL,
        student_id INT NOT NULL,
        module_id INT NOT NULL,
        course_id INT NOT NULL,
        attempt_no INT NOT NULL DEFAULT 1,
        submitted_text LONGTEXT NULL,
        submission_link VARCHAR(500) NULL,
        file_url VARCHAR(500) NULL,
        file_name VARCHAR(255) NULL,
        file_size BIGINT UNSIGNED NULL,
        status VARCHAR(20) DEFAULT 'submitted',
        feedback LONGTEXT NULL,
        score DECIMAL(6,2) NULL,
        submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        graded_at DATETIME NULL,
        graded_by INT NULL,
        PRIMARY KEY (id),
        KEY idx_content_student (content_id, student_id),
        KEY idx_student (student_id),
        KEY idx_module (module_id),
        KEY idx_status (status)
    ) {$charset_collate}";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    return $table;
}

add_action('wp_ajax_nds_portal_submit_quiz_attempt', function () {
    if (!is_user_logged_in()) {
        wp_send_json_error('Unauthorized', 401);
    }

    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if ($nonce === '' || !wp_verify_nonce($nonce, 'nds_portal_quiz_nonce')) {
        wp_send_json_error('Invalid request token.', 403);
    }

    $student_id = (int) nds_portal_get_current_student_id();
    if ($student_id <= 0) {
        wp_send_json_error('Student profile not found for current user.');
    }

    $content_id = isset($_POST['content_id']) ? (int) $_POST['content_id'] : 0;
    if ($content_id <= 0) {
        wp_send_json_error('Invalid quiz content selected.');
    }

    global $wpdb;
    $content = $wpdb->get_row($wpdb->prepare(
                "SELECT id, module_id, course_id, title, quiz_data, min_grade_required
         FROM {$wpdb->prefix}nds_lecturer_content
         WHERE id = %d
           AND content_type = 'quiz'
           AND is_visible = 1
           AND status = 'published'
           AND (access_start IS NULL OR access_start <= NOW())
           AND (access_end IS NULL OR access_end >= NOW())
         LIMIT 1",
        $content_id
    ), ARRAY_A);

    if (empty($content)) {
        wp_send_json_error('Quiz is not available anymore.');
    }

    $module_id = (int) ($content['module_id'] ?? 0);
    $course_id = (int) ($content['course_id'] ?? 0);
    if ($module_id <= 0 || $course_id <= 0) {
        wp_send_json_error('Quiz has invalid module/course mapping.');
    }

    $student_modules_table = nds_portal_ensure_student_modules_table();
    $has_module_access = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$student_modules_table}
         WHERE student_id = %d
           AND module_id = %d
           AND (status IN ('enrolled','active','registered') OR status IS NULL)
         ORDER BY id DESC
         LIMIT 1",
        $student_id,
        $module_id
    ));

    if ($has_module_access <= 0) {
        $has_course_access = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}nds_student_enrollments
             WHERE student_id = %d
               AND course_id = %d
               AND status IN ('enrolled','applied','waitlisted')
             ORDER BY id DESC
             LIMIT 1",
            $student_id,
            $course_id
        ));

        if ($has_course_access <= 0) {
            wp_send_json_error('You do not have access to this quiz.');
        }
    }

    $questions = json_decode((string) ($content['quiz_data'] ?? ''), true);
    if (!is_array($questions) || empty($questions)) {
        wp_send_json_error('Quiz questions could not be loaded.');
    }

    $answers_raw = isset($_POST['answers']) ? wp_unslash($_POST['answers']) : array();
    if (!is_array($answers_raw)) {
        $answers_raw = array();
    }

    $total_questions = count($questions);
    $graded_questions = 0;
    $correct_answers = 0;
    $normalized_answers = array();

    foreach ($questions as $idx => $question) {
        $q_type = sanitize_key((string) ($question['type'] ?? 'multiple_choice'));
        $answer_raw = isset($answers_raw[$idx]) ? (string) $answers_raw[$idx] : '';

        if ($q_type === 'multiple_choice') {
            $answer = strtoupper(substr(sanitize_text_field($answer_raw), 0, 1));
            $expected = strtoupper(substr(sanitize_text_field((string) ($question['correct'] ?? '')), 0, 1));

            $normalized_answers[$idx] = $answer;
            $graded_questions++;
            if ($answer !== '' && $expected !== '' && $answer === $expected) {
                $correct_answers++;
            }
            continue;
        }

        $normalized_answers[$idx] = sanitize_textarea_field($answer_raw);
    }

    $score_percent = $graded_questions > 0
        ? round(($correct_answers / $graded_questions) * 100, 2)
        : null;
    $pass_threshold = isset($content['min_grade_required']) && $content['min_grade_required'] !== null
        ? max(0.0, min(100.0, (float) $content['min_grade_required']))
        : 50.0;
    $passed = $score_percent !== null ? ($score_percent >= $pass_threshold) : null;

    $quiz_attempts_table = nds_portal_ensure_quiz_attempts_table();
    $attempt_no = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COALESCE(MAX(attempt_no), 0) + 1
         FROM {$quiz_attempts_table}
         WHERE content_id = %d AND student_id = %d",
        $content_id,
        $student_id
    ));
    if ($attempt_no <= 0) {
        $attempt_no = 1;
    }

    $saved = $wpdb->insert(
        $quiz_attempts_table,
        array(
            'content_id' => $content_id,
            'student_id' => $student_id,
            'module_id' => $module_id,
            'course_id' => $course_id,
            'attempt_no' => $attempt_no,
            'total_questions' => $total_questions,
            'graded_questions' => $graded_questions,
            'correct_answers' => $correct_answers,
            'score_percent' => $score_percent,
            'answers_json' => wp_json_encode($normalized_answers),
            'submitted_at' => current_time('mysql'),
        ),
        array('%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%f', '%s', '%s')
    );

    if ($saved === false) {
        wp_send_json_error('Could not save your quiz attempt. Please try again.');
    }

    wp_send_json_success(array(
        'message' => 'Quiz submitted successfully.',
        'attempt_no' => $attempt_no,
        'total_questions' => $total_questions,
        'graded_questions' => $graded_questions,
        'correct_answers' => $correct_answers,
        'score_percent' => $score_percent,
        'pass_threshold' => $pass_threshold,
        'passed' => $passed,
    ));
});

add_action('wp_ajax_nds_portal_submit_assignment', function () {
    if (!is_user_logged_in()) {
        wp_send_json_error('Unauthorized', 401);
    }

    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if ($nonce === '' || !wp_verify_nonce($nonce, 'nds_portal_assignment_nonce')) {
        wp_send_json_error('Invalid request token.', 403);
    }

    $student_id = (int) nds_portal_get_current_student_id();
    if ($student_id <= 0) {
        wp_send_json_error('Student profile not found for current user.');
    }

    $content_id = isset($_POST['content_id']) ? (int) $_POST['content_id'] : 0;
    if ($content_id <= 0) {
        wp_send_json_error('Invalid assignment selected.');
    }

    global $wpdb;
    $content = $wpdb->get_row($wpdb->prepare(
        "SELECT id, module_id, course_id, title, due_date
         FROM {$wpdb->prefix}nds_lecturer_content
         WHERE id = %d
           AND content_type = 'assignment'
           AND is_visible = 1
           AND status = 'published'
           AND (access_start IS NULL OR access_start <= NOW())
           AND (access_end IS NULL OR access_end >= NOW())
         LIMIT 1",
        $content_id
    ), ARRAY_A);

    if (empty($content)) {
        wp_send_json_error('Assignment is not available anymore.');
    }

    $module_id = (int) ($content['module_id'] ?? 0);
    $course_id = (int) ($content['course_id'] ?? 0);
    if ($course_id <= 0) {
        wp_send_json_error('Assignment has invalid course mapping.');
    }

    $has_module_access = 0;
    if ($module_id > 0) {
        $student_modules_table = nds_portal_ensure_student_modules_table();
        $has_module_access = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$student_modules_table}
             WHERE student_id = %d
               AND module_id = %d
               AND (status IN ('enrolled','active','registered') OR status IS NULL)
             ORDER BY id DESC
             LIMIT 1",
            $student_id,
            $module_id
        ));
    }

    if ($has_module_access <= 0) {
        $has_course_access = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}nds_student_enrollments
             WHERE student_id = %d
               AND course_id = %d
               AND status IN ('enrolled','applied','waitlisted')
             ORDER BY id DESC
             LIMIT 1",
            $student_id,
            $course_id
        ));

        if ($has_course_access <= 0) {
            wp_send_json_error('You do not have access to this assignment.');
        }
    }

    $submitted_text = isset($_POST['submitted_text'])
        ? sanitize_textarea_field(wp_unslash($_POST['submitted_text']))
        : '';
    $submission_link = isset($_POST['submission_link'])
        ? esc_url_raw(wp_unslash($_POST['submission_link']))
        : '';

    $file_url = '';
    $file_name = '';
    $file_size = null;

    if (!empty($_FILES['assignment_file']) && (int) ($_FILES['assignment_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        if ((int) $_FILES['assignment_file']['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error('Could not upload the file. Please try again.');
        }

        $max_file_size = 20 * 1024 * 1024;
        $uploaded_size = (int) ($_FILES['assignment_file']['size'] ?? 0);
        if ($uploaded_size > $max_file_size) {
            wp_send_json_error('File exceeds 20MB limit.');
        }

        $file_ext = strtolower(pathinfo((string) ($_FILES['assignment_file']['name'] ?? ''), PATHINFO_EXTENSION));
        $allowed_ext = array('pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'zip', 'jpg', 'jpeg', 'png');
        if (!in_array($file_ext, $allowed_ext, true)) {
            wp_send_json_error('Unsupported file type.');
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        $uploaded = wp_handle_upload($_FILES['assignment_file'], array('test_form' => false));
        if (!empty($uploaded['error'])) {
            wp_send_json_error('File upload failed: ' . $uploaded['error']);
        }

        $file_url = isset($uploaded['url']) ? esc_url_raw($uploaded['url']) : '';
        $file_name = isset($_FILES['assignment_file']['name']) ? sanitize_file_name(wp_unslash($_FILES['assignment_file']['name'])) : '';
        $file_size = $uploaded_size > 0 ? $uploaded_size : null;
    }

    if ($submitted_text === '' && $submission_link === '' && $file_url === '') {
        wp_send_json_error('Add a message, link, or file before submitting.');
    }

    $assignment_submissions_table = nds_portal_ensure_assignment_submissions_table();
    $existing_attempts = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*)
         FROM {$assignment_submissions_table}
         WHERE content_id = %d AND student_id = %d",
        $content_id,
        $student_id
    ));

    $attempt_limit = (int) apply_filters('nds_portal_assignment_attempt_limit', 3, $content, $student_id);
    if ($attempt_limit > 0 && $existing_attempts >= $attempt_limit) {
        wp_send_json_error('Attempt limit reached for this assignment.');
    }

    $is_late_submission = false;
    if (!empty($content['due_date'])) {
        $due_value = (string) $content['due_date'];
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $due_value)) {
            $due_value .= ' 23:59:59';
        }
        $due_timestamp = strtotime($due_value);
        if ($due_timestamp !== false) {
            $is_late_submission = (time() > $due_timestamp);
        }
    }

    $attempt_no = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COALESCE(MAX(attempt_no), 0) + 1
         FROM {$assignment_submissions_table}
         WHERE content_id = %d AND student_id = %d",
        $content_id,
        $student_id
    ));
    if ($attempt_no <= 0) {
        $attempt_no = 1;
    }

    $saved = $wpdb->insert(
        $assignment_submissions_table,
        array(
            'content_id' => $content_id,
            'student_id' => $student_id,
            'module_id' => $module_id,
            'course_id' => $course_id,
            'attempt_no' => $attempt_no,
            'submitted_text' => $submitted_text !== '' ? $submitted_text : null,
            'submission_link' => $submission_link !== '' ? $submission_link : null,
            'file_url' => $file_url !== '' ? $file_url : null,
            'file_name' => $file_name !== '' ? $file_name : null,
            'file_size' => $file_size,
            'status' => $is_late_submission ? 'late' : 'submitted',
            'submitted_at' => current_time('mysql'),
        ),
        array('%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s')
    );

    if ($saved === false) {
        wp_send_json_error('Could not save your assignment submission. Please try again.');
    }

    wp_send_json_success(array(
        'message' => $is_late_submission ? 'Assignment submitted (late).' : 'Assignment submitted successfully.',
        'attempt_no' => $attempt_no,
        'status' => $is_late_submission ? 'late' : 'submitted',
        'is_late' => $is_late_submission,
        'attempt_limit' => $attempt_limit,
        'submitted_at' => current_time('mysql'),
        'submitted_text' => $submitted_text,
        'submission_link' => $submission_link,
        'file_url' => $file_url,
        'file_name' => $file_name,
    ));
});

add_action('wp_ajax_nds_portal_registration_action', function () {
    if (!is_user_logged_in()) {
        wp_send_json_error('Unauthorized', 401);
    }
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field($_POST['nonce']), 'nds_portal_nonce')) {
        wp_send_json_error('Bad nonce', 403);
    }

    global $wpdb;
    $student_id = (int) nds_portal_get_current_student_id();
    if ($student_id <= 0) {
        wp_send_json_error('Student profile not found for current user.');
    }

    $registration_action = isset($_POST['registration_action'])
        ? sanitize_key(wp_unslash($_POST['registration_action']))
        : '';
    $course_id = isset($_POST['course_id']) ? (int) $_POST['course_id'] : 0;

    if (!$registration_action || $course_id <= 0) {
        wp_send_json_error('Invalid registration request.');
    }

    $latest_application = nds_portal_get_latest_application_for_current_user(array('accepted', 'enrolled'));
    $allowed_statuses = array('accepted', 'enrolled');

    if (empty($latest_application) || !in_array($latest_application['status'], $allowed_statuses, true)) {
        wp_send_json_error('Registration actions are only available for accepted applications.');
    }

    // Resolve course_id from the application; fall back to name lookup if missing.
    $app_course_id = (int) ($latest_application['course_id'] ?? 0);
    if ($app_course_id <= 0) {
        $course_name_raw = trim($latest_application['course_name'] ?? '');
        if ($course_name_raw !== '') {
            $app_course_id = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}nds_courses WHERE name = %s ORDER BY id DESC LIMIT 1",
                $course_name_raw
            ));
            if (!$app_course_id) {
                $name_clean = preg_replace('/\s*\(NQF\s+\d+\)\s*$/i', '', $course_name_raw);
                $app_course_id = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}nds_courses WHERE name = %s ORDER BY id DESC LIMIT 1",
                    $name_clean
                ));
            }
            if (!$app_course_id) {
                $app_course_id = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}nds_courses WHERE name LIKE %s ORDER BY id DESC LIMIT 1",
                    '%' . $wpdb->esc_like($course_name_raw) . '%'
                ));
            }
        }
    }

    // Get program_id for fallback module validation.
    $app_program_id = (int) ($latest_application['program_id'] ?? 0);
    if ($app_program_id <= 0 && $app_course_id > 0) {
        $app_program_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT program_id FROM {$wpdb->prefix}nds_courses WHERE id = %d",
            $app_course_id
        ));
    }

    // The submitted course_id must match the resolved application course_id,
    // OR belong to the same program (for cases where modules span courses in a program).
    $course_matches = ($app_course_id > 0 && $app_course_id === $course_id);
    if (!$course_matches && $app_program_id > 0) {
        $submitted_program_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT program_id FROM {$wpdb->prefix}nds_courses WHERE id = %d",
            $course_id
        ));
        $course_matches = ($submitted_program_id > 0 && $submitted_program_id === $app_program_id);
    }
    if (!$course_matches) {
        wp_send_json_error('Registration actions are only available for accepted applications.');
    }

    $active_year_id = (int) $wpdb->get_var("SELECT id FROM {$wpdb->prefix}nds_academic_years WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
    $active_semester_id = $active_year_id ? (int) $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}nds_semesters WHERE academic_year_id = %d AND is_active = 1 ORDER BY id DESC LIMIT 1",
        $active_year_id
    )) : 0;

    if ($active_year_id <= 0 || $active_semester_id <= 0) {
        wp_send_json_error('No active academic year/semester configured yet.');
    }

    $enrollments_table = $wpdb->prefix . 'nds_student_enrollments';
    $modules_table = $wpdb->prefix . 'nds_modules';
    $student_modules_table = nds_portal_ensure_student_modules_table();

    if ($registration_action === 'submit_registration') {
        $active_other_enrollment = nds_portal_get_active_qualification_enrollment($student_id, $course_id);
        if (!empty($active_other_enrollment)) {
            $active_name = !empty($active_other_enrollment['program_name'])
                ? $active_other_enrollment['program_name']
                : (!empty($active_other_enrollment['course_name']) ? $active_other_enrollment['course_name'] : 'your current qualification');

            wp_send_json_error('You are already enrolled in an active qualification (' . $active_name . '). You can enroll in another qualification once the current period has ended.');
        }

        $module_ids_raw = isset($_POST['module_ids']) ? wp_unslash($_POST['module_ids']) : array();
        if (!is_array($module_ids_raw)) {
            $module_ids_raw = explode(',', (string) $module_ids_raw);
        }
        $module_ids = array_values(array_filter(array_map('intval', $module_ids_raw)));

        if (empty($module_ids)) {
            wp_send_json_error('Please select at least one module before submitting registration.');
        }

        $placeholders = implode(',', array_fill(0, count($module_ids), '%d'));
        // Validate modules belong to the course directly, or to any course in the same program.
        if ($app_program_id > 0) {
            $params = array_merge(array($app_program_id), $module_ids);
            $valid_module_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT m.id FROM {$modules_table} m
                 INNER JOIN {$wpdb->prefix}nds_courses c ON c.id = m.course_id
                 WHERE c.program_id = %d AND m.id IN ({$placeholders})",
                $params
            ));
        } else {
            $params = array_merge(array($course_id), $module_ids);
            $valid_module_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT id FROM {$modules_table} WHERE course_id = %d AND id IN ({$placeholders})",
                $params
            ));
        }
        $valid_module_ids = array_values(array_map('intval', $valid_module_ids));

        if (empty($valid_module_ids)) {
            wp_send_json_error('No valid modules selected for this course.');
        }

        $existing_enrollment_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$enrollments_table}
             WHERE student_id = %d AND course_id = %d AND academic_year_id = %d AND semester_id = %d
             LIMIT 1",
            $student_id,
            $course_id,
            $active_year_id,
            $active_semester_id
        ));

        if ($existing_enrollment_id > 0) {
            $wpdb->update(
                $enrollments_table,
                array(
                    'status' => 'enrolled',
                    'enrollment_date' => current_time('Y-m-d'),
                ),
                array('id' => $existing_enrollment_id),
                array('%s', '%s'),
                array('%d')
            );
        } else {
            $inserted = $wpdb->insert(
                $enrollments_table,
                array(
                    'student_id' => $student_id,
                    'course_id' => $course_id,
                    'academic_year_id' => $active_year_id,
                    'semester_id' => $active_semester_id,
                    'enrollment_date' => current_time('Y-m-d'),
                    'status' => 'enrolled',
                    'created_at' => current_time('mysql'),
                ),
                array('%d', '%d', '%d', '%d', '%s', '%s', '%s')
            );

            if (!$inserted) {
                wp_send_json_error('Failed to submit registration. Please try again.');
            }

            $existing_enrollment_id = (int) $wpdb->insert_id;
        }

        foreach ($valid_module_ids as $module_id) {
            $exists_id = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$student_modules_table}
                 WHERE student_id = %d AND module_id = %d AND academic_year_id = %d AND semester_id = %d
                 LIMIT 1",
                $student_id,
                $module_id,
                $active_year_id,
                $active_semester_id
            ));

            if ($exists_id > 0) {
                $wpdb->update(
                    $student_modules_table,
                    array('status' => 'enrolled', 'course_id' => $course_id),
                    array('id' => $exists_id),
                    array('%s', '%d'),
                    array('%d')
                );
            } else {
                $wpdb->insert(
                    $student_modules_table,
                    array(
                        'student_id' => $student_id,
                        'module_id' => $module_id,
                        'course_id' => $course_id,
                        'academic_year_id' => $active_year_id,
                        'semester_id' => $active_semester_id,
                        'status' => 'enrolled',
                        'created_at' => current_time('mysql'),
                    ),
                    array('%d', '%d', '%d', '%d', '%d', '%s', '%s')
                );
            }
        }

        // Keep only the selected modules active for this term and course.
        $cancel_placeholders = implode(',', array_fill(0, count($valid_module_ids), '%d'));
        $cancel_params = array_merge(array($student_id, $course_id, $active_year_id, $active_semester_id), $valid_module_ids);
        $wpdb->query($wpdb->prepare(
            "UPDATE {$student_modules_table}
             SET status = 'cancelled'
             WHERE student_id = %d AND course_id = %d AND academic_year_id = %d AND semester_id = %d
               AND module_id NOT IN ({$cancel_placeholders})",
            $cancel_params
        ));

        wp_send_json_success(array(
            'message' => 'Registration submitted successfully.',
            'enrollment_id' => $existing_enrollment_id,
            'enrolled_module_ids' => $valid_module_ids,
        ));
    }

    if ($registration_action === 'download_proof') {
        $course_name = (string) $wpdb->get_var($wpdb->prepare(
            "SELECT name FROM {$wpdb->prefix}nds_courses WHERE id = %d LIMIT 1",
            $course_id
        ));

        $student = nds_get_student($student_id);
        $student_name = '';
        if ($student) {
            $student_arr = (array) $student;
            $student_name = trim(($student_arr['first_name'] ?? '') . ' ' . ($student_arr['last_name'] ?? ''));
        }

        $proof_lines = array(
            'NDS Academy - Proof of Registration',
            'Generated: ' . current_time('mysql'),
            'Application No: ' . ($latest_application['application_no'] ?? ''),
            'Student: ' . ($student_name ?: 'Learner'),
            'Course: ' . ($course_name ?: ($latest_application['course_name'] ?? '')),
            'Status: Registered',
            'Academic Year ID: ' . $active_year_id,
            'Semester ID: ' . $active_semester_id,
        );

        wp_send_json_success(array(
            'message' => 'Proof of registration is ready.',
            'proof_filename' => 'proof-of-registration-' . (int) $student_id . '.txt',
            'proof_content' => implode("\n", $proof_lines),
        ));
    }

    if ($registration_action === 'add_module' || $registration_action === 'cancel_module') {
        $module_ids_raw = isset($_POST['module_ids']) ? wp_unslash($_POST['module_ids']) : array();
        if (!is_array($module_ids_raw)) {
            $module_ids_raw = explode(',', (string) $module_ids_raw);
        }
        $module_ids = array_values(array_filter(array_map('intval', $module_ids_raw)));

        if (empty($module_ids)) {
            wp_send_json_error('Please select at least one module.');
        }

        // Only allow modules belonging to this accepted course.
        $placeholders = implode(',', array_fill(0, count($module_ids), '%d'));
        $params = array_merge(array($course_id), $module_ids);
        $valid_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$modules_table} WHERE course_id = %d AND id IN ({$placeholders})",
            $params
        ));
        $valid_ids = array_values(array_map('intval', $valid_ids));

        if (empty($valid_ids)) {
            wp_send_json_error('No valid modules selected for this course.');
        }

        foreach ($valid_ids as $module_id) {
            if ($registration_action === 'add_module') {
                $exists_id = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$student_modules_table}
                     WHERE student_id = %d AND module_id = %d AND academic_year_id = %d AND semester_id = %d
                     LIMIT 1",
                    $student_id,
                    $module_id,
                    $active_year_id,
                    $active_semester_id
                ));

                if ($exists_id > 0) {
                    $wpdb->update(
                        $student_modules_table,
                        array('status' => 'enrolled', 'course_id' => $course_id),
                        array('id' => $exists_id),
                        array('%s', '%d'),
                        array('%d')
                    );
                } else {
                    $wpdb->insert(
                        $student_modules_table,
                        array(
                            'student_id' => $student_id,
                            'module_id' => $module_id,
                            'course_id' => $course_id,
                            'academic_year_id' => $active_year_id,
                            'semester_id' => $active_semester_id,
                            'status' => 'enrolled',
                            'created_at' => current_time('mysql'),
                        ),
                        array('%d', '%d', '%d', '%d', '%d', '%s', '%s')
                    );
                }
            } else {
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$student_modules_table}
                     SET status = 'cancelled'
                     WHERE student_id = %d AND module_id = %d AND academic_year_id = %d AND semester_id = %d",
                    $student_id,
                    $module_id,
                    $active_year_id,
                    $active_semester_id
                ));
            }
        }

        $enrolled_module_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT module_id FROM {$student_modules_table}
             WHERE student_id = %d AND course_id = %d AND academic_year_id = %d AND semester_id = %d AND status = 'enrolled'",
            $student_id,
            $course_id,
            $active_year_id,
            $active_semester_id
        ));

        wp_send_json_success(array(
            'message' => $registration_action === 'add_module'
                ? 'Selected modules have been added.'
                : 'Selected modules have been cancelled.',
            'enrolled_module_ids' => array_values(array_map('intval', $enrolled_module_ids)),
        ));
    }

    wp_send_json_error('Unsupported registration action.');
});

// One-off: add FK students.faculty_id -> faculties.id
function nds_add_faculty_fk_action() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    global $wpdb;
    $students = $wpdb->prefix . 'nds_students';
    $paths = $wpdb->prefix . 'nds_faculties';
    // Attempt to add FK; ignore errors if it already exists
    $wpdb->query("ALTER TABLE {$students} ADD CONSTRAINT fk_students_faculty FOREIGN KEY (faculty_id) REFERENCES {$paths}(id) ON DELETE SET NULL");
    $notice = $wpdb->last_error ? 'error=' . rawurlencode($wpdb->last_error) : 'success=faculty_fk_added';
    wp_redirect(admin_url('admin.php?page=nds-all-learners&' . $notice));
    exit;
}
add_action('admin_post_nds_add_faculty_fk', 'nds_add_faculty_fk_action');

// AJAX handler for getting courses by faculty
function nds_get_programs_by_faculty() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'nds_get_courses_nonce')) {
        wp_send_json_error('Security check failed');
    }

    $faculty_id = isset($_POST['faculty_id']) ? intval($_POST['faculty_id']) : 0;
    if ($faculty_id <= 0) {
        wp_send_json_error('Invalid faculty ID');
    }

    global $wpdb;

    // Fetch all programs for a given faculty
    $programs = $wpdb->get_results(
        $wpdb->prepare(
            "
            SELECT id, name
            FROM {$wpdb->prefix}nds_programs
            WHERE faculty_id = %d
            AND status = 'active'
            ORDER BY name ASC
            ",
            $faculty_id
        )
    );

    if ($programs === null) {
        wp_send_json_error('Failed to load programs: ' . $wpdb->last_error);
    }

    wp_send_json_success($programs);
}
add_action('wp_ajax_nds_get_programs_by_faculty', 'nds_get_programs_by_faculty');

function nds_get_courses_by_faculty() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'nds_get_courses_nonce')) {
        wp_send_json_error('Security check failed');
    }

    $faculty_id = isset($_POST['faculty_id']) ? intval($_POST['faculty_id']) : 0;
    if ($faculty_id <= 0) {
        wp_send_json_error('Invalid faculty ID');
    }

    global $wpdb;

    // Courses are linked to programs; programs belong to a faculty.
    // Join through programs to get all courses for a given faculty.
    $courses = $wpdb->get_results(
        $wpdb->prepare(
            "
            SELECT c.id, c.name
            FROM {$wpdb->prefix}nds_courses c
            INNER JOIN {$wpdb->prefix}nds_programs p ON c.program_id = p.id
            WHERE p.faculty_id = %d
            ORDER BY c.name ASC
            ",
            $faculty_id
        )
    );

    if ($courses === null) {
        wp_send_json_error('Failed to load courses: ' . $wpdb->last_error);
    }

    wp_send_json_success($courses);
}
add_action('wp_ajax_nds_get_courses_by_faculty', 'nds_get_courses_by_faculty');

// AJAX: Add staff role
function nds_add_staff_role_ajax() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'nds_manage_roles')) {
        wp_send_json_error('Security check failed');
    }
    
    $role = isset($_POST['role']) ? sanitize_text_field($_POST['role']) : '';
    if (empty($role)) {
        wp_send_json_error('Role name is required');
    }
    
    $result = nds_add_staff_role($role);
    
    if ($result['success']) {
        wp_send_json_success($result['message']);
    } else {
        wp_send_json_error($result['message']);
    }
}
add_action('wp_ajax_nds_add_staff_role', 'nds_add_staff_role_ajax');

// AJAX: Delete staff role
function nds_delete_staff_role_ajax() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'nds_manage_roles')) {
        wp_send_json_error('Security check failed');
    }
    
    $role = isset($_POST['role']) ? sanitize_text_field($_POST['role']) : '';
    if (empty($role)) {
        wp_send_json_error('Role name is required');
    }
    
    $result = nds_delete_staff_role($role);
    
    if ($result['success']) {
        wp_send_json_success($result['message']);
    } else {
        wp_send_json_error($result['message']);
    }
}
add_action('wp_ajax_nds_delete_staff_role', 'nds_delete_staff_role_ajax');

// AJAX: Restore roles from backup
function nds_restore_roles_backup_ajax() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'nds_manage_roles')) {
        wp_send_json_error('Security check failed');
    }
    
    $result = nds_restore_roles_from_backup();
    
    if ($result['success']) {
        wp_send_json_success($result['message']);
    } else {
        wp_send_json_error($result['message']);
    }
}
add_action('wp_ajax_nds_restore_roles_backup', 'nds_restore_roles_backup_ajax');

// AJAX: enroll student to a course (create or update enrollment)
function nds_enroll_student_ajax() {
    // Enhanced security checks
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized access');
    }
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'nds_enroll_student_nonce')) {
        wp_send_json_error('Security check failed');
    }

    // Rate limiting to prevent abuse
    if (!nds_check_rate_limit('enroll_student', 20, 60)) { // 20 enrollments per minute
        wp_send_json_error('Too many enrollment attempts. Please wait before trying again.');
    }

    // Sanitize and validate inputs
    $student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;
    $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;

    // Validate input ranges and types
    if ($student_id <= 0 || $course_id <= 0) {
        wp_send_json_error('Invalid student or course ID');
    }

    // Additional validation - ensure student and course exist
    global $wpdb;
    $student_exists = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}nds_students WHERE id = %d",
        $student_id
    ));
    $course_exists = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}nds_courses WHERE id = %d",
        $course_id
    ));

    if (!$student_exists) {
        wp_send_json_error('Student not found');
    }
    if (!$course_exists) {
        wp_send_json_error('Course not found');
    }
    $enrollments_table = $wpdb->prefix . 'nds_student_enrollments';
    $academic_years_table = $wpdb->prefix . 'nds_academic_years';
    $semesters_table = $wpdb->prefix . 'nds_semesters';

    // Ensure table exists
    $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $enrollments_table));
    if (!$exists) {
        wp_send_json_error('Enrollments table missing');
    }

    // Determine active academic year and semester
    $active_year_id = (int) $wpdb->get_var("SELECT id FROM {$academic_years_table} WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
    $active_semester_id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$semesters_table} WHERE academic_year_id = %d AND is_active = 1 ORDER BY id DESC LIMIT 1", $active_year_id));
    if (!$active_year_id || !$active_semester_id) {
        wp_send_json_error('Active academic year/semester not set');
    }

    // Check for existing enrollment in this course for active term
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$enrollments_table} WHERE student_id = %d AND course_id = %d AND academic_year_id = %d AND semester_id = %d",
        $student_id, $course_id, $active_year_id, $active_semester_id
    ));

    if ($existing) {
        // Update existing enrollment
        $ok = $wpdb->update(
            $enrollments_table,
            ['status' => 'enrolled', 'updated_at' => current_time('mysql')],
            ['id' => $existing],
            ['%s','%s'],
            ['%d']
        );
            if ($ok === false) {
            nds_log_error('Failed to update student enrollment', array(
                'student_id' => $student_id,
                'course_id' => $course_id,
                'error' => $wpdb->last_error
            ));
            wp_send_json_error($wpdb->last_error ?: 'Update failed');
        }
    } else {
        // Check if student is already enrolled in another course this term (business rule)
        $other_enrollment = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$enrollments_table} WHERE student_id = %d AND academic_year_id = %d AND semester_id = %d AND course_id != %d AND status IN ('applied','enrolled','waitlisted')",
            $student_id, $active_year_id, $active_semester_id, $course_id
        ));

        if ($other_enrollment) {
            nds_log_error('Attempted to enroll student already enrolled in another course', array(
                'student_id' => $student_id,
                'course_id' => $course_id,
                'existing_enrollment_id' => $other_enrollment
            ), 'warning');
            wp_send_json_error('Student is already enrolled in another course for this term');
        }

        // Create new enrollment
        $ok = $wpdb->insert(
            $enrollments_table,
            [
                'student_id' => $student_id,
                'course_id' => $course_id,
                'academic_year_id' => $active_year_id,
                'semester_id' => $active_semester_id,
                'enrollment_date' => current_time('mysql'),
                'status' => 'enrolled',
            ],
            ['%d','%d','%d','%d','%s','%s']
        );
        if ($ok === false) {
            nds_log_error('Failed to create student enrollment', array(
                'student_id' => $student_id,
                'course_id' => $course_id,
                'error' => $wpdb->last_error
            ));
            wp_send_json_error($wpdb->last_error ?: 'Insert failed');
        }
    }

    // Log successful enrollment
    nds_log_error('Student successfully enrolled', array(
        'student_id' => $student_id,
        'course_id' => $course_id,
        'academic_year_id' => $active_year_id,
        'semester_id' => $active_semester_id
    ), 'info');

    wp_send_json_success(true);
}
add_action('wp_ajax_nds_enroll_student', 'nds_enroll_student_ajax');

// AJAX handler to get enrolled students for a course
function nds_get_enrolled_students_ajax() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'nds_get_enrolled_students_nonce')) {
        wp_send_json_error('Security check failed');
        return;
    }

    if (!isset($_POST['course_id'])) {
        wp_send_json_error('Course ID is required');
        return;
    }

    $course_id = intval($_POST['course_id']);

    global $wpdb;

    // Limit to active term
    $active_year_id = (int) $wpdb->get_var("SELECT id FROM {$wpdb->prefix}nds_academic_years WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
    $active_semester_id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}nds_semesters WHERE academic_year_id = %d AND is_active = 1 ORDER BY id DESC LIMIT 1", $active_year_id));

    $enrolled_students = $wpdb->get_results($wpdb->prepare(
        "SELECT s.id, s.first_name, s.last_name, s.student_number\n        FROM {$wpdb->prefix}nds_students s\n        JOIN {$wpdb->prefix}nds_student_enrollments e ON s.id = e.student_id\n        WHERE e.course_id = %d\n          AND e.academic_year_id = %d\n          AND e.semester_id = %d\n          AND e.status IN ('applied','enrolled','waitlisted')\n        ORDER BY s.first_name, s.last_name",
        $course_id, $active_year_id, $active_semester_id
    ));

    wp_send_json_success($enrolled_students);
}
add_action('wp_ajax_nds_get_enrolled_students', 'nds_get_enrolled_students_ajax');

// AJAX handler to get available students for a course
function nds_get_available_students_ajax() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'nds_get_available_students_nonce')) {
        wp_send_json_error('Security check failed');
        return;
    }

    if (!isset($_POST['course_id'])) {
        wp_send_json_error('Course ID is required');
        return;
    }

    $course_id = intval($_POST['course_id']);

    global $wpdb;

    $active_year_id = (int) $wpdb->get_var("SELECT id FROM {$wpdb->prefix}nds_academic_years WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
    $active_semester_id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}nds_semesters WHERE academic_year_id = %d AND is_active = 1 ORDER BY id DESC LIMIT 1", $active_year_id));

    // Frontend rule: a student can belong to only ONE course in the active term.
    // Optimized query using LEFT JOIN instead of subquery for better performance
    $available_students = $wpdb->get_results($wpdb->prepare(
        "SELECT s.id, s.first_name, s.last_name, s.student_number
        FROM {$wpdb->prefix}nds_students s
        LEFT JOIN {$wpdb->prefix}nds_student_enrollments e ON (
            s.id = e.student_id
            AND e.academic_year_id = %d
            AND e.semester_id = %d
            AND e.status IN ('applied','enrolled','waitlisted')
        )
        WHERE e.student_id IS NULL
        AND s.status IN ('active', 'prospect')
        ORDER BY s.first_name, s.last_name",
        $active_year_id, $active_semester_id
    ));

    wp_send_json_success($available_students);
}
add_action('wp_ajax_nds_get_available_students', 'nds_get_available_students_ajax');

// AJAX handler to unenroll a student from a course
function nds_unenroll_student_ajax() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized access');
    }
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'nds_unenroll_student_nonce')) {
        wp_send_json_error('Security check failed');
    }

    // Rate limiting to prevent abuse
    if (!nds_check_rate_limit('unenroll_student', 30, 60)) { // 30 unenrollments per minute
        wp_send_json_error('Too many unenrollment attempts. Please wait before trying again.');
    }

    // Sanitize and validate inputs
    $student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;
    $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;

    // Validate input ranges and types
    if ($student_id <= 0 || $course_id <= 0) {
        wp_send_json_error('Invalid student or course ID');
    }

    // Additional validation - ensure student and course exist
    global $wpdb;
    $student_exists = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}nds_students WHERE id = %d",
        $student_id
    ));
    $course_exists = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}nds_courses WHERE id = %d",
        $course_id
    ));

    if (!$student_exists) {
        wp_send_json_error('Student not found');
    }
    if (!$course_exists) {
        wp_send_json_error('Course not found');
    }
    $enrollments_table = $wpdb->prefix . 'nds_student_enrollments';
    $academic_years_table = $wpdb->prefix . 'nds_academic_years';
    $semesters_table = $wpdb->prefix . 'nds_semesters';

    // Get active academic year and semester
    $active_year_id = (int) $wpdb->get_var("SELECT id FROM {$academic_years_table} WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
    $active_semester_id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$semesters_table} WHERE academic_year_id = %d AND is_active = 1 ORDER BY id DESC LIMIT 1", $active_year_id));

    if (!$active_year_id || !$active_semester_id) {
        wp_send_json_error('Active academic year/semester not set');
    }

    // Only delete enrollment for the ACTIVE term (prevents accidental deletion of historical records)
    $deleted = $wpdb->delete(
        $enrollments_table,
        array(
            'student_id' => $student_id,
            'course_id' => $course_id,
            'academic_year_id' => $active_year_id,
            'semester_id' => $active_semester_id
        ),
        array('%d', '%d', '%d', '%d')
    );

    if ($deleted === false) {
        nds_log_error('Failed to unenroll student', array(
            'student_id' => $student_id,
            'course_id' => $course_id,
            'academic_year_id' => $active_year_id,
            'semester_id' => $active_semester_id,
            'error' => $wpdb->last_error
        ));
        wp_send_json_error('Failed to unenroll student: ' . $wpdb->last_error);
    } elseif ($deleted === 0) {
        nds_log_error('Attempted to unenroll student not enrolled in course', array(
            'student_id' => $student_id,
            'course_id' => $course_id,
            'academic_year_id' => $active_year_id,
            'semester_id' => $active_semester_id
        ), 'warning');
        wp_send_json_error('Student was not enrolled in this course for the active term');
    } else {
        // Log successful unenrollment
        nds_log_error('Student successfully unenrolled', array(
            'student_id' => $student_id,
            'course_id' => $course_id,
            'academic_year_id' => $active_year_id,
            'semester_id' => $active_semester_id
        ), 'info');
        wp_send_json_success(true);
    }
}
add_action('wp_ajax_nds_unenroll_student', 'nds_unenroll_student_ajax');

// AJAX: approve student application (set status to active)
function nds_approve_student_application_ajax() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'nds_approve_application_nonce')) {
        wp_send_json_error('Bad nonce');
    }
    $student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;
    if ($student_id <= 0) {
        wp_send_json_error('Invalid student');
    }
    global $wpdb;
    $ok = $wpdb->update(
        $wpdb->prefix . 'nds_students',
        ['status' => 'active'],
        ['id' => $student_id],
        ['%s'],
        ['%d']
    );
    if ($ok === false) {
        wp_send_json_error($wpdb->last_error ?: 'Update failed');
    }
    wp_send_json_success(true);
}
add_action('wp_ajax_nds_approve_student_application', 'nds_approve_student_application_ajax');

// AJAX: get enrolled count for a course (active term)
function nds_get_course_enrolled_count_ajax() {
    if (!isset($_POST['course_id'])) {
        wp_send_json_error('Missing course_id');
    }
    global $wpdb;
    $course_id = intval($_POST['course_id']);
    $active_year_id = (int) $wpdb->get_var("SELECT id FROM {$wpdb->prefix}nds_academic_years WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
    $active_semester_id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}nds_semesters WHERE academic_year_id = %d AND is_active = 1 ORDER BY id DESC LIMIT 1", $active_year_id));
    $count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}nds_student_enrollments e WHERE e.course_id=%d AND e.academic_year_id=%d AND e.semester_id=%d AND e.status IN ('applied','enrolled','waitlisted')",
        $course_id, $active_year_id, $active_semester_id
    ));
    wp_send_json_success(['count' => $count]);
}
add_action('wp_ajax_nds_get_course_enrolled_count', 'nds_get_course_enrolled_count_ajax');

// AJAX: get overall enrollment quick stats
function nds_get_enrollment_quick_stats_ajax() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }
    global $wpdb;
    $total_students = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}nds_students");
    $enrolled_students = (int) $wpdb->get_var("SELECT COUNT(DISTINCT student_id) FROM {$wpdb->prefix}nds_student_enrollments");
    $courses = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}nds_courses");
    $unassigned = $total_students - $enrolled_students;
    wp_send_json_success([
        'total' => $total_students,
        'enrolled' => $enrolled_students,
        'unassigned' => max(0,$unassigned),
        'courses' => $courses,
    ]);
}
add_action('wp_ajax_nds_get_enrollment_quick_stats', 'nds_get_enrollment_quick_stats_ajax');

function nds_school_deactivate()
{
    global $wpdb;

    // Temporarily disable foreign key checks
    $wpdb->query("SET FOREIGN_KEY_CHECKS = 0;");

    // All NDS tables to drop (in order to handle dependencies)
    $tables = [
        // Application related (drop first due to foreign keys)
        $wpdb->prefix . "nds_application_documents",
        $wpdb->prefix . "nds_application_reviews",
        $wpdb->prefix . "nds_application_payments",
        $wpdb->prefix . "nds_applications",
        $wpdb->prefix . "nds_application_forms",
        
        // Enrollment and student related
        $wpdb->prefix . "nds_student_enrollments",
        $wpdb->prefix . "nds_student_events",
        $wpdb->prefix . "nds_student_progression",
        $wpdb->prefix . "nds_students",
        
        // Course related
        $wpdb->prefix . "nds_course_accreditations",
        $wpdb->prefix . "nds_course_prerequisites",
        $wpdb->prefix . "nds_course_lecturers",
        $wpdb->prefix . "nds_course_schedules",
        $wpdb->prefix . "nds_courses",
        
        // Program related
        $wpdb->prefix . "nds_program_accreditations",
        $wpdb->prefix . "nds_program_levels",
        $wpdb->prefix . "nds_programs",
        
        // Academic calendar
        $wpdb->prefix . "nds_semesters",
        $wpdb->prefix . "nds_academic_years",
        
        // Lookup and reference tables
        $wpdb->prefix . "nds_course_categories",
        $wpdb->prefix . "nds_program_types_lookup",
        $wpdb->prefix . "nds_program_types",
        $wpdb->prefix . "nds_accreditation_bodies",
        $wpdb->prefix . "nds_faculties",
        
        // Staff and other
        $wpdb->prefix . "nds_staff",
        $wpdb->prefix . "nds_recipes",
        $wpdb->prefix . "nds_hero_carousel",
        $wpdb->prefix . "nds_trade_tests",
        
        // Legacy tables (if they still exist)
        $wpdb->prefix . "nds_education_paths",
        $wpdb->prefix . "nds_possible_employment",
        $wpdb->prefix . "nds_duration_breakdown",
    ];

    // Drop all tables
    foreach ($tables as $table) {
        $result = $wpdb->query("DROP TABLE IF EXISTS {$table}");

        if ($result === false) {
            error_log("NDS Plugin Deactivation: Failed to drop table: {$table} - Error: " . $wpdb->last_error);
        } else {
            error_log("NDS Plugin Deactivation: Successfully dropped table: {$table}");
        }
    }

    // Re-enable foreign key checks
    $wpdb->query("SET FOREIGN_KEY_CHECKS = 1;");
    
    // Clear any cached data
    delete_option('nds_portal_rules_flushed');
    flush_rewrite_rules();
}

function nds_add_rewrite_rules() {
    add_rewrite_rule('^academy/([^/]+)-([0-9]+)/?', 'index.php?nds_education_path_id=$matches[2]', 'top');
    add_rewrite_rule('^recipe/([0-9]+)/?$', 'index.php?nds_recipe_id=$matches[1]', 'top');
}
add_action('init', 'nds_add_rewrite_rules');

function nds_add_query_vars($vars) {
    $vars[] = 'nds_education_path_id';
    $vars[] = 'nds_recipe_id';
    return $vars;
}
add_filter('query_vars', 'nds_add_query_vars');

function nds_template_redirect() {
    if (get_query_var('nds_education_path_id')) {
        include plugin_dir_path(__FILE__) . 'templates/education-path-single.php';
        exit;
    }
    
    if (get_query_var('nds_recipe_id')) {
        include plugin_dir_path(__FILE__) . 'templates/recipe-single.php';
        exit;
    }
}
add_action('template_redirect', 'nds_template_redirect');

function nds_page_template_filter($template) {
    if (is_page()) {
        $page_template = get_post_meta(get_the_ID(), '_wp_page_template', true);
        if ($page_template === 'education-path-single.php') {
            $template = plugin_dir_path(__FILE__) . 'templates/education-path-single.php';
        } elseif ($page_template === 'program-single.php') {
            $template = plugin_dir_path(__FILE__) . 'templates/program-single.php';
        }
    }
    return $template;
}
add_filter('page_template', 'nds_page_template_filter');

// Add /programs/slug-id URL
add_action('init', 'nds_add_program_rewrite_rule');
function nds_add_program_rewrite_rule() {
    add_rewrite_rule('^programs/([^/]+)-([0-9]+)/?', 'index.php?nds_program_id=$matches[2]', 'top');
    // Add rewrite rule for calendar
    add_rewrite_rule('^calendar/?$', 'index.php?nds_calendar=1', 'top');
}

// Register query var
add_filter('query_vars', function ($vars) {
    $vars[] = 'nds_program_id';
    // Portal query var for /portal/
    $vars[] = 'nds_portal';
    // Staff portal query var for /staff-portal/
    $vars[] = 'nds_staff_portal';
    // Calendar query var for /calendar/
    $vars[] = 'nds_calendar';
    return $vars;
});

// Redirect to custom template
add_action('template_redirect', 'nds_program_template_redirect');
function nds_program_template_redirect() {
    $program_id = get_query_var('nds_program_id');
    if ($program_id) {
        include plugin_dir_path(__FILE__) . 'templates/program-single.php';
        exit;
    }
    
    // Handle calendar page
    $calendar = get_query_var('nds_calendar');
    if ($calendar == '1') {
        // Enqueue calendar assets
        // FullCalendar CSS
        wp_enqueue_style(
            'fullcalendar-css',
            'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css',
            array(),
            '6.1.10'
        );
        
        // FullCalendar JS
        wp_enqueue_script(
            'fullcalendar-js',
            'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js',
            array('jquery'),
            '6.1.10',
            true
        );
        
        // Use unified calendar component for both admin and frontend
        $calendar_js_path = plugin_dir_path(__FILE__) . 'assets/js/admin-calendar.js';
        if (file_exists($calendar_js_path)) {
            wp_enqueue_script(
                'nds-frontend-calendar',
                plugin_dir_url(__FILE__) . 'assets/js/admin-calendar.js',
                array('jquery', 'fullcalendar-js'),
                filemtime($calendar_js_path),
                true
            );
            
            // Localize script for AJAX (use ndsFrontendCalendar name for compatibility)
            wp_localize_script('nds-frontend-calendar', 'ndsFrontendCalendar', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('nds_public_calendar_nonce')
            ));
        }
        
        // Enqueue Tailwind CSS if available
        $css_file = plugin_dir_path(__FILE__) . 'assets/css/frontend.css';
        if (file_exists($css_file)) {
            wp_enqueue_style(
                'nds-tailwindcss-calendar',
                plugin_dir_url(__FILE__) . 'assets/css/frontend.css',
                array(),
                filemtime($css_file),
                'all'
            );
        }
        
        // Font Awesome icons
        wp_enqueue_style('nds-icons', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css', array(), null, 'all');
        
        // Create a simple page that displays the calendar shortcode
        get_header();
        echo do_shortcode('[nds_calendar]');
        get_footer();
        exit;
    }
}


register_deactivation_hook(__FILE__, 'nds_school_deactivate');

// -----------------------------
// Learner Frontend Dashboard: /portal/
// -----------------------------
add_action('init', function () {
    // Keep the existing /portal/ rewrite, but route it to a lean, learner-only dashboard
    add_rewrite_rule('^portal/?$', 'index.php?nds_portal=1', 'top');
    // Staff portal route
    add_rewrite_rule('^staff-portal/?$', 'index.php?nds_staff_portal=1', 'top');
});

add_action('template_redirect', function () {
    $is_portal = (int) get_query_var('nds_portal');
    if ($is_portal !== 1) {
        return;
    }

    // Require login – learners will use their normal WP account
    if (!is_user_logged_in()) {
        wp_safe_redirect(wp_login_url(home_url('/portal/')));
        exit;
    }

    // Map current WP user to an NDS student/learner record
    if (!function_exists('nds_portal_get_current_student_id')) {
        // Safety guard – if helper is missing, fail gracefully
        wp_die(__('Student portal is not available right now.', 'nds-school'));
    }

    $student_id = (int) nds_portal_get_current_student_id();
    if ($student_id <= 0) {
        // Allow admins to view the portal template (which handles missing student ID gracefully)
        if (current_user_can('manage_options')) {
            // Do nothing, let it fall through to include the template
        } else {
            // If this account is staff (especially lecturer), keep them in staff portal and avoid student application flow
            $staff_id = function_exists('nds_portal_get_current_staff_id') ? (int) nds_portal_get_current_staff_id() : 0;
            if ($staff_id > 0) {
                global $wpdb;
                $role = (string) $wpdb->get_var($wpdb->prepare(
                    "SELECT role FROM {$wpdb->prefix}nds_staff WHERE id = %d LIMIT 1",
                    $staff_id
                ));
                if ($role !== '') {
                    wp_safe_redirect(home_url('/staff-portal/?tab=overview'));
                    exit;
                }
            }

            // No learner profile for regular non-staff user – send them to the online application form
            $application_url = home_url('/online-application/');
            wp_safe_redirect($application_url);
            exit;
        }
    }

    // Render a standalone full-screen learner dashboard (no theme header/nav)
    include plugin_dir_path(__FILE__) . 'templates/learner-portal.php';
    exit;
});

// Allow front-end staff portal forms to reuse existing admin_post handlers without going through /wp-admin/admin-post.php.
add_action('template_redirect', function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    $is_staff_portal = (int) get_query_var('nds_staff_portal');
    if ($is_staff_portal !== 1) {
        return;
    }

    $action = isset($_POST['action']) ? sanitize_key(wp_unslash($_POST['action'])) : '';
    if ($action === '' || strpos($action, 'nds_staff_') !== 0) {
        return;
    }

    $hook_name = 'admin_post_' . $action;
    if (!has_action($hook_name)) {
        return;
    }

    do_action($hook_name);
    exit;
}, 1);

// Staff Portal Route Handler
add_action('template_redirect', function () {
    $is_staff_portal = (int) get_query_var('nds_staff_portal');
    if ($is_staff_portal !== 1) {
        return;
    }

    // Require login – staff will use their normal WP account
    if (!is_user_logged_in()) {
        wp_safe_redirect(wp_login_url(home_url('/staff-portal/')));
        exit;
    }

    // Map current WP user to an NDS staff record
    if (!function_exists('nds_portal_get_current_staff_id')) {
        wp_die(__('Staff portal is not available right now.', 'nds-school'));
    }

    $staff_id = (int) nds_portal_get_current_staff_id();
    if ($staff_id <= 0) {
        // No staff profile yet for this WP account
        wp_die(__('No staff profile found for your account. Please contact the administrator.', 'nds-school'));
    }

    // Render a standalone full-screen staff dashboard (no theme header/nav)
    include plugin_dir_path(__FILE__) . 'templates/staff-portal.php';
    exit;
});

// Prevent canonical redirects from stripping staff portal query args like ?tab=content
add_filter('redirect_canonical', function ($redirect_url, $requested_url) {
    if ((int) get_query_var('nds_staff_portal') === 1) {
        return false;
    }

    $request_path = wp_parse_url($requested_url, PHP_URL_PATH);
    $staff_path = wp_parse_url(home_url('/staff-portal/'), PHP_URL_PATH);
    if (!empty($request_path) && !empty($staff_path) && strpos($request_path, $staff_path) === 0) {
        return false;
    }

    return $redirect_url;
}, 10, 2);

// Enqueue learner dashboard assets only on /portal/
add_action('wp_enqueue_scripts', function () {
    $is_portal = (int) get_query_var('nds_portal');
    if ($is_portal !== 1) {
        return;
    }

    // Tailwind-style utility CSS used across the plugin
    $frontend_css = plugin_dir_path(__FILE__) . 'assets/css/frontend.css';
    if (file_exists($frontend_css)) {
        wp_enqueue_style(
            'nds-learner-portal-frontend',
            plugin_dir_url(__FILE__) . 'assets/css/frontend.css',
            array(),
            filemtime($frontend_css),
            'all'
        );
    }

    // Additional component styles (cards, layouts, etc.)
    $styles_css = plugin_dir_path(__FILE__) . 'assets/css/styles.css';
    if (file_exists($styles_css)) {
        wp_enqueue_style(
            'nds-learner-portal-styles',
            plugin_dir_url(__FILE__) . 'assets/css/styles.css',
            array('nds-learner-portal-frontend'),
            filemtime($styles_css),
            'all'
        );
    }

    // Student Portal Layout CSS (overrides theme headers/footers)
    $layout_css = plugin_dir_path(__FILE__) . 'assets/css/student-portal-layout.css';
    if (file_exists($layout_css)) {
        wp_enqueue_style(
            'nds-student-portal-layout',
            plugin_dir_url(__FILE__) . 'assets/css/student-portal-layout.css',
            array('nds-learner-portal-frontend', 'nds-learner-portal-styles'),
            filemtime($layout_css),
            'all'
        );
    }

    // Icons for the dashboard
    wp_enqueue_style(
        'nds-learner-portal-icons',
        'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css',
        array(),
        null,
        'all'
    );
    
    // Calendar scripts for timetable tab
    // FullCalendar CSS
    wp_enqueue_style(
        'fullcalendar-css',
        'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css',
        array(),
        '6.1.10'
    );
    
    // FullCalendar JS
    wp_enqueue_script(
        'fullcalendar-js',
        'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js',
        array('jquery'),
        '6.1.10',
        true
    );
    
    // Unified calendar component (works for both admin and frontend)
    $calendar_js_path = plugin_dir_path(__FILE__) . 'assets/js/admin-calendar.js';
    if (file_exists($calendar_js_path)) {
        wp_enqueue_script(
            'nds-frontend-calendar',
            plugin_dir_url(__FILE__) . 'assets/js/admin-calendar.js',
            array('jquery', 'fullcalendar-js'),
            filemtime($calendar_js_path),
            true
        );
        
        // Localize script for AJAX
        wp_localize_script('nds-frontend-calendar', 'ndsFrontendCalendar', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('nds_public_calendar_nonce')
        ));
    }
});

// Enqueue staff portal assets only on /staff-portal/
add_action('wp_enqueue_scripts', function () {
    $is_staff_portal = (int) get_query_var('nds_staff_portal');
    if ($is_staff_portal !== 1) {
        return;
    }

    // Tailwind-style utility CSS used across the plugin
    $frontend_css = plugin_dir_path(__FILE__) . 'assets/css/frontend.css';
    if (file_exists($frontend_css)) {
        wp_enqueue_style(
            'nds-staff-portal-frontend',
            plugin_dir_url(__FILE__) . 'assets/css/frontend.css',
            array(),
            filemtime($frontend_css),
            'all'
        );
    }

    // Additional component styles (cards, layouts, etc.)
    $styles_css = plugin_dir_path(__FILE__) . 'assets/css/styles.css';
    if (file_exists($styles_css)) {
        wp_enqueue_style(
            'nds-staff-portal-styles',
            plugin_dir_url(__FILE__) . 'assets/css/styles.css',
            array('nds-staff-portal-frontend'),
            filemtime($styles_css),
            'all'
        );
    }

    // Shared Portal Layout CSS (same shell behavior as learner portal)
    $layout_css = plugin_dir_path(__FILE__) . 'assets/css/student-portal-layout.css';
    if (file_exists($layout_css)) {
        wp_enqueue_style(
            'nds-staff-portal-layout',
            plugin_dir_url(__FILE__) . 'assets/css/student-portal-layout.css',
            array('nds-staff-portal-frontend', 'nds-staff-portal-styles'),
            filemtime($layout_css),
            'all'
        );
    }

    // Icons for the dashboard
    wp_enqueue_style(
        'nds-staff-portal-icons',
        'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css',
        array(),
        null,
        'all'
    );
    
    // Calendar scripts for timetable tab
    // FullCalendar CSS
    wp_enqueue_style(
        'fullcalendar-css',
        'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css',
        array(),
        '6.1.10'
    );
    
    // FullCalendar JS
    wp_enqueue_script(
        'fullcalendar-js',
        'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js',
        array('jquery'),
        '6.1.10',
        true
    );
    
    // Unified calendar component (works for both admin and frontend)
    $calendar_js_path = plugin_dir_path(__FILE__) . 'assets/js/admin-calendar.js';
    if (file_exists($calendar_js_path)) {
        wp_enqueue_script(
            'nds-staff-calendar',
            plugin_dir_url(__FILE__) . 'assets/js/admin-calendar.js',
            array('jquery', 'fullcalendar-js'),
            filemtime($calendar_js_path),
            true
        );
        
        // Localize script for AJAX
        wp_localize_script('nds-staff-calendar', 'ndsStaffCalendar', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('nds_staff_calendar_nonce')
        ));
    }
});

// One-time rewrite flush to activate /portal/ and /staff-portal/ without manual permalinks save
add_action('init', function () {
    if (!get_option('nds_portal_rules_flushed')) {
        flush_rewrite_rules(false);
        update_option('nds_portal_rules_flushed', 1);
    }
}, 99);

// Force flush rewrite rules on next page load (one-time for staff portal)
add_action('init', function () {
    if (!get_option('nds_staff_portal_rules_flushed')) {
        flush_rewrite_rules(false);
        update_option('nds_staff_portal_rules_flushed', 1);
    }
}, 100);

// -----------------------------
// Student Portal helpers & AJAX
// -----------------------------
function nds_portal_get_current_student_id() {
    if (!is_user_logged_in()) {
        return 0;
    }
    $wp_user_id = get_current_user_id();
    $user = get_userdata($wp_user_id);
    if (!$user) return 0;

    global $wpdb;
    // Prefer explicit mapping via wp_user_id
    $student_id = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}nds_students WHERE wp_user_id = %d",
        $wp_user_id
    ));
    if ($student_id) return $student_id;

    // Fallback by email match (in case mapping not set yet)
    $student_id = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}nds_students WHERE email = %s",
        $user->user_email
    ));
    return $student_id ?: 0;
}

// -----------------------------
// Staff Portal helpers
// -----------------------------
function nds_portal_get_current_staff_id() {
    if (!is_user_logged_in()) {
        return 0;
    }
    $wp_user_id = get_current_user_id();
    $user = get_userdata($wp_user_id);
    if (!$user) return 0;

    global $wpdb;
    // Get staff ID via user_id mapping (staff table uses 'user_id' field)
    $staff_id = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}nds_staff WHERE user_id = %d",
        $wp_user_id
    ));
    
    // Fallback: try email match if no direct user_id mapping
    if (!$staff_id && $user->user_email) {
        $staff_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}nds_staff WHERE email = %s",
            $user->user_email
        ));
    }
    
    return $staff_id ?: 0;
}

function nds_portal_build_redirect_url($portal_path, $tab, array $query_args = array()) {
    $base_url = home_url($portal_path);
    if (!empty($tab) && $tab !== 'overview') {
        $base_url = add_query_arg('tab', $tab, $base_url);
    }

    if (!empty($query_args)) {
        $base_url = add_query_arg($query_args, $base_url);
    }

    return $base_url;
}

function nds_portal_validate_profile_password_change(WP_User $user, array $request_data) {
    $current_password = isset($request_data['current_password']) ? (string) $request_data['current_password'] : '';
    $new_password = isset($request_data['new_password']) ? (string) $request_data['new_password'] : '';
    $confirm_password = isset($request_data['confirm_password']) ? (string) $request_data['confirm_password'] : '';

    if ($current_password === '' && $new_password === '' && $confirm_password === '') {
        return array('should_update' => false);
    }

    if ($current_password === '' || $new_password === '' || $confirm_password === '') {
        return new WP_Error('missing_password_fields', 'Complete all password fields to change your password.');
    }

    if (!wp_check_password($current_password, $user->user_pass, $user->ID)) {
        return new WP_Error('invalid_current_password', 'Your current password is incorrect.');
    }

    if (!nds_is_strong_password($new_password)) {
        return new WP_Error('weak_password', nds_get_password_policy_message());
    }

    if ($new_password !== $confirm_password) {
        return new WP_Error('password_mismatch', 'New password and confirmation do not match.');
    }

    return array(
        'should_update' => true,
        'new_password' => $new_password,
    );
}

function nds_portal_sync_wp_user_profile(WP_User $user, array $profile_data) {
    $user_email = isset($profile_data['email']) ? sanitize_email($profile_data['email']) : $user->user_email;
    if (empty($user_email) || !is_email($user_email)) {
        return new WP_Error('invalid_email', 'Please enter a valid email address.');
    }

    $existing_user = get_user_by('email', $user_email);
    if ($existing_user && (int) $existing_user->ID !== (int) $user->ID) {
        return new WP_Error('email_exists', 'That email address is already used by another account.');
    }

    $first_name = isset($profile_data['first_name']) ? sanitize_text_field($profile_data['first_name']) : '';
    $last_name = isset($profile_data['last_name']) ? sanitize_text_field($profile_data['last_name']) : '';

    $user_args = array(
        'ID' => $user->ID,
        'user_email' => $user_email,
        'first_name' => $first_name,
        'last_name' => $last_name,
        'display_name' => trim($first_name . ' ' . $last_name),
    );

    $password_result = nds_portal_validate_profile_password_change($user, $profile_data);
    if (is_wp_error($password_result)) {
        return $password_result;
    }

    if (!empty($password_result['should_update'])) {
        $user_args['user_pass'] = $password_result['new_password'];
    }

    $updated_user_id = wp_update_user($user_args);
    if (is_wp_error($updated_user_id)) {
        return $updated_user_id;
    }

    if (!empty($password_result['should_update'])) {
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, true);
    }

    return array(
        'email' => $user_email,
        'password_updated' => !empty($password_result['should_update']),
    );
}

function nds_portal_handle_student_profile_update() {
    if (!is_user_logged_in()) {
        wp_die('Unauthorized');
    }

    if (!isset($_POST['nds_student_profile_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nds_student_profile_nonce'])), 'nds_student_profile_action')) {
        wp_die('Security check failed.');
    }

    $student_id = function_exists('nds_portal_get_current_student_id') ? (int) nds_portal_get_current_student_id() : 0;
    $user = wp_get_current_user();
    if ($student_id <= 0 || !$user || !$user->exists()) {
        wp_safe_redirect(nds_portal_build_redirect_url('/portal/', 'profile', array('profile_error' => rawurlencode('Student profile not found.'))));
        exit;
    }

    global $wpdb;
    $student_table = $wpdb->prefix . 'nds_students';

    $profile_data = array(
        'first_name' => isset($_POST['first_name']) ? sanitize_text_field(wp_unslash($_POST['first_name'])) : '',
        'last_name' => isset($_POST['last_name']) ? sanitize_text_field(wp_unslash($_POST['last_name'])) : '',
        'email' => isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '',
        'phone' => isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '',
        'address' => isset($_POST['address']) ? sanitize_textarea_field(wp_unslash($_POST['address'])) : '',
        'city' => isset($_POST['city']) ? sanitize_text_field(wp_unslash($_POST['city'])) : '',
        'country' => isset($_POST['country']) ? sanitize_text_field(wp_unslash($_POST['country'])) : 'South Africa',
        'date_of_birth' => isset($_POST['date_of_birth']) ? sanitize_text_field(wp_unslash($_POST['date_of_birth'])) : '',
        'gender' => isset($_POST['gender']) ? sanitize_text_field(wp_unslash($_POST['gender'])) : '',
        'profile_photo' => isset($_POST['profile_photo']) ? esc_url_raw(wp_unslash($_POST['profile_photo'])) : '',
        'current_password' => isset($_POST['current_password']) ? (string) wp_unslash($_POST['current_password']) : '',
        'new_password' => isset($_POST['new_password']) ? (string) wp_unslash($_POST['new_password']) : '',
        'confirm_password' => isset($_POST['confirm_password']) ? (string) wp_unslash($_POST['confirm_password']) : '',
    );

    if ($profile_data['first_name'] === '' || $profile_data['last_name'] === '' || $profile_data['email'] === '') {
        wp_safe_redirect(nds_portal_build_redirect_url('/portal/', 'profile', array('profile_error' => rawurlencode('First name, last name, and email are required.'))));
        exit;
    }

    $user_sync = nds_portal_sync_wp_user_profile($user, $profile_data);
    if (is_wp_error($user_sync)) {
        wp_safe_redirect(nds_portal_build_redirect_url('/portal/', 'profile', array('profile_error' => rawurlencode($user_sync->get_error_message()))));
        exit;
    }

    $student_update = array(
        'first_name' => $profile_data['first_name'],
        'last_name' => $profile_data['last_name'],
        'email' => $user_sync['email'],
        'phone' => $profile_data['phone'],
        'address' => $profile_data['address'],
        'city' => $profile_data['city'],
        'country' => $profile_data['country'],
        'date_of_birth' => $profile_data['date_of_birth'] ?: null,
        'gender' => $profile_data['gender'],
        'profile_photo' => $profile_data['profile_photo'],
    );

    $wpdb->update($student_table, $student_update, array('id' => $student_id));

    $notice = !empty($user_sync['password_updated'])
        ? 'Profile updated and password changed successfully.'
        : 'Profile updated successfully.';
    wp_safe_redirect(nds_portal_build_redirect_url('/portal/', 'profile', array('profile_notice' => rawurlencode($notice))));
    exit;
}
add_action('admin_post_nds_portal_update_student_profile', 'nds_portal_handle_student_profile_update');

function nds_portal_handle_staff_profile_update() {
    if (!is_user_logged_in()) {
        wp_die('Unauthorized');
    }

    if (!isset($_POST['nds_staff_profile_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nds_staff_profile_nonce'])), 'nds_staff_profile_action')) {
        wp_die('Security check failed.');
    }

    $staff_id = function_exists('nds_portal_get_current_staff_id') ? (int) nds_portal_get_current_staff_id() : 0;
    $user = wp_get_current_user();
    if ($staff_id <= 0 || !$user || !$user->exists()) {
        wp_safe_redirect(nds_portal_build_redirect_url('/staff-portal/', 'profile', array('profile_error' => rawurlencode('Staff profile not found.'))));
        exit;
    }

    global $wpdb;
    $staff_table = $wpdb->prefix . 'nds_staff';

    $profile_data = array(
        'first_name' => isset($_POST['first_name']) ? sanitize_text_field(wp_unslash($_POST['first_name'])) : '',
        'last_name' => isset($_POST['last_name']) ? sanitize_text_field(wp_unslash($_POST['last_name'])) : '',
        'email' => isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '',
        'phone' => isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '',
        'address' => isset($_POST['address']) ? sanitize_textarea_field(wp_unslash($_POST['address'])) : '',
        'dob' => isset($_POST['dob']) ? sanitize_text_field(wp_unslash($_POST['dob'])) : '',
        'gender' => isset($_POST['gender']) ? sanitize_text_field(wp_unslash($_POST['gender'])) : '',
        'profile_picture' => isset($_POST['profile_picture']) ? esc_url_raw(wp_unslash($_POST['profile_picture'])) : '',
        'current_password' => isset($_POST['current_password']) ? (string) wp_unslash($_POST['current_password']) : '',
        'new_password' => isset($_POST['new_password']) ? (string) wp_unslash($_POST['new_password']) : '',
        'confirm_password' => isset($_POST['confirm_password']) ? (string) wp_unslash($_POST['confirm_password']) : '',
    );

    if ($profile_data['first_name'] === '' || $profile_data['last_name'] === '' || $profile_data['email'] === '') {
        wp_safe_redirect(nds_portal_build_redirect_url('/staff-portal/', 'profile', array('profile_error' => rawurlencode('First name, last name, and email are required.'))));
        exit;
    }

    $user_sync = nds_portal_sync_wp_user_profile($user, $profile_data);
    if (is_wp_error($user_sync)) {
        wp_safe_redirect(nds_portal_build_redirect_url('/staff-portal/', 'profile', array('profile_error' => rawurlencode($user_sync->get_error_message()))));
        exit;
    }

    $staff_update = array(
        'first_name' => $profile_data['first_name'],
        'last_name' => $profile_data['last_name'],
        'email' => $user_sync['email'],
        'phone' => $profile_data['phone'],
        'address' => $profile_data['address'],
        'dob' => $profile_data['dob'] ?: null,
        'gender' => $profile_data['gender'],
        'profile_picture' => $profile_data['profile_picture'],
    );

    $wpdb->update($staff_table, $staff_update, array('id' => $staff_id));

    $notice = !empty($user_sync['password_updated'])
        ? 'Profile updated and password changed successfully.'
        : 'Profile updated successfully.';
    wp_safe_redirect(nds_portal_build_redirect_url('/staff-portal/', 'profile', array('profile_notice' => rawurlencode($notice))));
    exit;
}
add_action('admin_post_nds_portal_update_staff_profile', 'nds_portal_handle_staff_profile_update');

/**
 * Public AJAX: register a basic WordPress user for applicants
 * Used by the multi-step online application form before submission.
 */
function nds_register_applicant_user() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field($_POST['nonce']), 'nds_applicant_reg')) {
        wp_send_json_error('Security check failed.');
    }

    $first_name = isset($_POST['first_name']) ? sanitize_text_field(wp_unslash($_POST['first_name'])) : '';
    $last_name  = isset($_POST['last_name']) ? sanitize_text_field(wp_unslash($_POST['last_name'])) : '';
    $email      = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
    $password   = isset($_POST['password']) ? (string) wp_unslash($_POST['password']) : '';

    if (empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
        wp_send_json_error('All fields are required.');
    }
    if (!is_email($email)) {
        wp_send_json_error('Please enter a valid email address.');
    }
    if (email_exists($email)) {
        wp_send_json_error('An account with this email already exists. Please log in instead.');
    }
    if (!nds_is_strong_password($password)) {
        wp_send_json_error(nds_get_password_policy_message());
    }

    error_log("NDS Registration Attempt: Email=$email, FirstName=$first_name, LastName=$last_name");

    $username = sanitize_user($email, true);
    if (username_exists($username)) {
        // Fallback: append random suffix
        $username = $username . '_' . wp_generate_password(4, false);
    }

    $user_id = wp_create_user($username, $password, $email);
    if (is_wp_error($user_id)) {
        error_log('NDS Registration Error: ' . $user_id->get_error_message());
        wp_send_json_error($user_id->get_error_message());
    }

    // Update basic profile
    wp_update_user(array(
        'ID'         => $user_id,
        'first_name' => $first_name,
        'last_name'  => $last_name,
    ));

    // Log the user in immediately
    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id, true);

    error_log('NDS Registration Success: User ID ' . $user_id);
    wp_send_json_success(array(
        'user_id' => $user_id,
    ));
}
add_action('wp_ajax_nopriv_nds_register_applicant_user', 'nds_register_applicant_user');
add_action('wp_ajax_nds_register_applicant_user', 'nds_register_applicant_user');

add_action('wp_ajax_nds_portal_overview', function () {
    if (!is_user_logged_in()) {
        wp_send_json_error('Unauthorized', 401);
    }
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field($_POST['nonce']), 'nds_portal_nonce')) {
        wp_send_json_error('Bad nonce', 403);
    }

    global $wpdb;
    $student_id = nds_portal_get_current_student_id();
    if (!$student_id) {
        wp_send_json_error('Student not found for current user');
    }

    // Active term
    $active_year_id = (int) $wpdb->get_var("SELECT id FROM {$wpdb->prefix}nds_academic_years WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
    $active_semester_id = $active_year_id ? (int) $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}nds_semesters WHERE academic_year_id = %d AND is_active = 1 ORDER BY id DESC LIMIT 1",
        $active_year_id
    )) : 0;

    // KPIs
    $enrolled_count = 0;
    if ($active_year_id && $active_semester_id) {
        $enrolled_count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}nds_student_enrollments
             WHERE student_id = %d AND academic_year_id = %d AND semester_id = %d AND status IN ('applied','enrolled','waitlisted')",
            $student_id, $active_year_id, $active_semester_id
        ));
    }

    $avg_percentage = (float) $wpdb->get_var($wpdb->prepare(
        "SELECT AVG(final_percentage) FROM {$wpdb->prefix}nds_student_enrollments
         WHERE student_id = %d AND final_percentage IS NOT NULL",
        $student_id
    ));
    $avg_percentage = $avg_percentage ? round($avg_percentage, 1) : 0.0;

    // Notifications (simple recent activity count last 30 days)
    $notifications = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}nds_student_activity_log WHERE student_id = %d AND timestamp > (NOW() - INTERVAL 30 DAY)",
        $student_id
    ));

    // Latest application (if any)
    $latest_app = nds_portal_get_latest_application_for_current_user();

    $mode = 'learner';
    if ($latest_app && $enrolled_count === 0 && in_array($latest_app['status'], array('submitted','under_review','waitlisted','draft','conditional_offer'), true)) {
        $mode = 'applicant';
    }

    wp_send_json_success(array(
        'enrolledCount' => $enrolled_count,
        'average'       => $avg_percentage,
        'notifications' => $notifications,
        'mode'          => $mode,
        'application'   => $latest_app,
    ));
});

add_action('wp_ajax_nds_portal_courses', function () {
    if (!is_user_logged_in()) {
        wp_send_json_error('Unauthorized', 401);
    }
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field($_POST['nonce']), 'nds_portal_nonce')) {
        wp_send_json_error('Bad nonce', 403);
    }

    global $wpdb;
    $student_id = nds_portal_get_current_student_id();
    if (!$student_id) {
        wp_send_json_error('Student not found for current user');
    }

    // Prefer active term courses first
    $active_year_id = (int) $wpdb->get_var("SELECT id FROM {$wpdb->prefix}nds_academic_years WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
    $active_semester_id = $active_year_id ? (int) $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}nds_semesters WHERE academic_year_id = %d AND is_active = 1 ORDER BY id DESC LIMIT 1",
        $active_year_id
    )) : 0;

    $courses = array();
    if ($active_year_id && $active_semester_id) {
        $courses = $wpdb->get_results($wpdb->prepare(
            "SELECT e.id as enrollment_id, c.id as course_id, c.name as course_name, e.status, e.enrollment_date,
                    e.final_percentage, e.final_grade
             FROM {$wpdb->prefix}nds_student_enrollments e
             JOIN {$wpdb->prefix}nds_courses c ON c.id = e.course_id
             WHERE e.student_id = %d AND e.academic_year_id = %d AND e.semester_id = %d
             ORDER BY c.name ASC",
            $student_id, $active_year_id, $active_semester_id
        ), ARRAY_A);
    }

    // Fallback to all-time if none in active term
    if (!$courses) {
        $courses = $wpdb->get_results($wpdb->prepare(
            "SELECT e.id as enrollment_id, c.id as course_id, c.name as course_name, e.status, e.enrollment_date,
                    e.final_percentage, e.final_grade
             FROM {$wpdb->prefix}nds_student_enrollments e
             JOIN {$wpdb->prefix}nds_courses c ON c.id = e.course_id
             WHERE e.student_id = %d
             ORDER BY e.updated_at DESC",
            $student_id
        ), ARRAY_A);
    }

    wp_send_json_success($courses ?: array());
});

add_action('wp_ajax_nds_portal_marks', function () {
    if (!is_user_logged_in()) {
        wp_send_json_error('Unauthorized', 401);
    }
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field($_POST['nonce']), 'nds_portal_nonce')) {
        wp_send_json_error('Bad nonce', 403);
    }

    global $wpdb;
    $student_id = nds_portal_get_current_student_id();
    if (!$student_id) {
        wp_send_json_error('Student not found for current user');
    }

    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT c.name as course_name, e.final_percentage, e.final_grade, e.updated_at
         FROM {$wpdb->prefix}nds_student_enrollments e
         JOIN {$wpdb->prefix}nds_courses c ON c.id = e.course_id
         WHERE e.student_id = %d AND (e.final_percentage IS NOT NULL OR e.final_grade IS NOT NULL)
         ORDER BY e.updated_at DESC",
        $student_id
    ), ARRAY_A);

    wp_send_json_success($rows ?: array());
});

// Ensure nds_student_documents table exists (lazy creation)
function nds_ensure_student_documents_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'nds_student_documents';
    $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
    if (!empty($exists)) {
        return $table;
    }
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS {$table} (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        document_type VARCHAR(100) NOT NULL,
        document_label VARCHAR(200) NOT NULL,
        file_name VARCHAR(255) NOT NULL,
        file_path VARCHAR(500) NOT NULL,
        file_size INT DEFAULT 0,
        file_ext VARCHAR(10),
        uploaded_by INT DEFAULT 0,
        uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        notes TEXT,
        INDEX idx_student (student_id),
        INDEX idx_doc_type (document_type)
    ) {$charset_collate}";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
    return $table;
}

// AJAX handler for uploading learner documents
add_action('wp_ajax_nds_upload_learner_document', 'nds_upload_learner_document_ajax');
function nds_upload_learner_document_ajax() {
    if (!is_user_logged_in()) {
        wp_send_json_error('Unauthorized', 401);
    }

    if (!isset($_POST['nds_upload_document_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nds_upload_document_nonce'])), 'nds_upload_learner_document')) {
        wp_send_json_error('Security check failed', 403);
    }

    $learner_id = isset($_POST['learner_id']) ? intval($_POST['learner_id']) : 0;
    if ($learner_id <= 0) {
        wp_send_json_error('Invalid learner ID');
    }

    $current_student_id = nds_portal_get_current_student_id();
    if ($current_student_id > 0 && $current_student_id !== $learner_id) {
        wp_send_json_error('Unauthorized - you can only upload documents for your own account');
    }
    if ($current_student_id <= 0 && !current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    if (empty($_FILES['document_file']) || $_FILES['document_file']['error'] !== UPLOAD_ERR_OK) {
        wp_send_json_error('No file uploaded or upload error');
    }

    $file = $_FILES['document_file'];
    $allowed_extensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($file_ext, $allowed_extensions, true)) {
        wp_send_json_error('Invalid file type. Allowed: PDF, DOC, DOCX, JPG, PNG');
    }
    if ($file['size'] > 10 * 1024 * 1024) {
        wp_send_json_error('File size exceeds 10MB limit');
    }

    global $wpdb;
    $learner = nds_get_student($learner_id);
    if (!$learner) {
        wp_send_json_error('Learner not found');
    }

    $learner_data   = (array) $learner;
    $learner_name   = trim(($learner_data['first_name'] ?? '') . ' ' . ($learner_data['last_name'] ?? ''));
    $current_year   = date('Y');
    $plugin_dir     = plugin_dir_path(__FILE__);
    $folder_name    = $learner_id . '_' . sanitize_file_name(str_replace(' ', '-', strtolower($learner_name)));
    $upload_dir     = $plugin_dir . 'public/Students/' . $current_year . '/' . $folder_name . '/';

    if (!file_exists($upload_dir)) {
        wp_mkdir_p($upload_dir);
    }

    $document_type  = isset($_POST['document_type'])  ? sanitize_key(wp_unslash($_POST['document_type']))         : 'other';
    $document_label = isset($_POST['document_label']) ? sanitize_text_field(wp_unslash($_POST['document_label'])) : 'Document';
    $notes          = isset($_POST['document_notes']) ? sanitize_textarea_field(wp_unslash($_POST['document_notes'])) : '';

    $unique_filename = sanitize_file_name($document_type) . '_' . time() . '_' . wp_generate_password(6, false) . '.' . $file_ext;
    $dest_path       = $upload_dir . $unique_filename;

    if (!move_uploaded_file($file['tmp_name'], $dest_path)) {
        wp_send_json_error('Failed to save file');
    }

    $relative_path = 'Students/' . $current_year . '/' . $folder_name . '/' . $unique_filename;

    $table = nds_ensure_student_documents_table();

    // Replace any existing record for the same student + document_type
    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT id, file_path FROM {$table} WHERE student_id = %d AND document_type = %s",
        $learner_id, $document_type
    ));
    if ($existing) {
        // Delete old file
        $old_file = $plugin_dir . 'public/' . $existing->file_path;
        if (file_exists($old_file)) {
            wp_delete_file($old_file);
        }
        $wpdb->update(
            $table,
            [
                'document_label' => $document_label,
                'file_name'      => $unique_filename,
                'file_path'      => $relative_path,
                'file_size'      => intval($file['size']),
                'file_ext'       => $file_ext,
                'uploaded_by'    => get_current_user_id(),
                'uploaded_at'    => current_time('mysql'),
                'notes'          => $notes,
            ],
            ['id' => intval($existing->id)],
            ['%s','%s','%s','%d','%s','%d','%s','%s'],
            ['%d']
        );
        $doc_id = intval($existing->id);
    } else {
        $wpdb->insert(
            $table,
            [
                'student_id'     => $learner_id,
                'document_type'  => $document_type,
                'document_label' => $document_label,
                'file_name'      => $unique_filename,
                'file_path'      => $relative_path,
                'file_size'      => intval($file['size']),
                'file_ext'       => $file_ext,
                'uploaded_by'    => get_current_user_id(),
                'uploaded_at'    => current_time('mysql'),
                'notes'          => $notes,
            ],
            ['%d','%s','%s','%s','%s','%d','%s','%d','%s','%s']
        );
        $doc_id = $wpdb->insert_id;
    }

    wp_send_json_success([
        'message'      => 'Document uploaded successfully',
        'doc_id'       => $doc_id,
        'path'         => $relative_path,
        'uploaded_at'  => current_time('mysql'),
    ]);
}

// AJAX handler for deleting a learner document
add_action('wp_ajax_nds_delete_learner_document', 'nds_delete_learner_document_ajax');
function nds_delete_learner_document_ajax() {
    if (!is_user_logged_in()) {
        wp_send_json_error('Unauthorized', 401);
    }

    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'nds_delete_learner_document')) {
        wp_send_json_error('Security check failed', 403);
    }

    $doc_id = isset($_POST['doc_id']) ? intval($_POST['doc_id']) : 0;
    if ($doc_id <= 0) {
        wp_send_json_error('Invalid document ID');
    }

    global $wpdb;
    $table = nds_ensure_student_documents_table();
    $doc   = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $doc_id));
    if (!$doc) {
        wp_send_json_error('Document not found');
    }

    $current_student_id = nds_portal_get_current_student_id();
    if ($current_student_id > 0 && $current_student_id !== intval($doc->student_id)) {
        wp_send_json_error('Unauthorized');
    }
    if ($current_student_id <= 0 && !current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    $file_path = plugin_dir_path(__FILE__) . 'public/' . $doc->file_path;
    if (file_exists($file_path)) {
        wp_delete_file($file_path);
    }

    $wpdb->delete($table, ['id' => $doc_id], ['%d']);

    wp_send_json_success(['message' => 'Document deleted successfully']);
}