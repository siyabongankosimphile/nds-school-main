<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

global $wpdb;
$employment_table = $wpdb->prefix . "nds_possible_employment";

// Handle adding/updating career paths
add_action('admin_post_nds_add_career_path', 'nds_add_career_path');
function nds_add_career_path() {
    // Check nonce and permissions
    if (!isset($_POST['nds_career_path_nonce']) || !wp_verify_nonce($_POST['nds_career_path_nonce'], 'nds_career_path_nonce')) {
        wp_die('Security check failed');
    }
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    global $wpdb;
    
    // Get form data
    $course_id = intval($_POST['course_id']);
    $career_path_id = intval($_POST['career_path_id']);
    $company_name = sanitize_text_field($_POST['company_name']);
    $job_title = sanitize_text_field($_POST['job_title']);
    $job_description = sanitize_textarea_field($_POST['job_description']);
    $location = sanitize_text_field($_POST['location']);
    $salary_range = sanitize_text_field($_POST['salary_range']);
    $employment_type = sanitize_text_field($_POST['employment_type']);

    // Validate required fields
    if (empty($company_name) || empty($job_title) || empty($employment_type)) {
        wp_die('Company name, job title, and employment type are required.');
    }

    $data = [
        'course_id' => $course_id,
        'company_name' => $company_name,
        'job_title' => $job_title,
        'job_description' => $job_description,
        'location' => $location,
        'salary_range' => $salary_range,
        'employment_type' => $employment_type
    ];

    $format = ['%d', '%s', '%s', '%s', '%s', '%s', '%s'];

    if ($career_path_id > 0) {
        // Update existing career path
        $result = $wpdb->update(
            $wpdb->prefix . 'nds_possible_employment',
            $data,
            ['id' => $career_path_id],
            $format,
            ['%d']
        );

        if ($result === false) {
            wp_die('Failed to update career path.');
        }
    } else {
        // Insert new career path
        $result = $wpdb->insert(
            $wpdb->prefix . 'nds_possible_employment',
            $data,
            $format
        );

        if ($result === false) {
            wp_die('Failed to create career path.');
        }
    }

    // Redirect back to course overview
    $redirect_url = admin_url('admin.php?page=nds-course-overview&course_id=' . $course_id);
    $redirect_url = add_query_arg('career_path_saved', 'success', $redirect_url);
    wp_redirect($redirect_url);
    exit;
}

// Handle deleting career paths
add_action('admin_post_nds_delete_career_path', 'nds_delete_career_path');
function nds_delete_career_path() {
    $redirect_url = admin_url('admin.php?page=nds-course-overview');

    // Check nonce and permissions
    if (!isset($_POST['nds_career_path_nonce']) || !wp_verify_nonce($_POST['nds_career_path_nonce'], 'nds_career_path_nonce')) {
        wp_redirect(add_query_arg('career_path_deleted', 'security_failed', $redirect_url));
        exit;
    }
    if (!current_user_can('manage_options')) {
        wp_redirect(add_query_arg('career_path_deleted', 'unauthorized', $redirect_url));
        exit;
    }

    global $wpdb;
    
    $career_path_id = intval($_POST['career_path_id']);
    
    if (!$career_path_id) {
        wp_redirect(add_query_arg('career_path_deleted', 'invalid_id', $redirect_url));
        exit;
    }

    // Get course_id for redirect
    $career_path = $wpdb->get_row($wpdb->prepare("SELECT course_id FROM {$wpdb->prefix}nds_possible_employment WHERE id = %d", $career_path_id));
    
    if (!$career_path) {
        wp_redirect(add_query_arg('career_path_deleted', 'not_found', $redirect_url));
        exit;
    }

    $redirect_url = admin_url('admin.php?page=nds-course-overview&course_id=' . intval($career_path->course_id));

    // Delete the career path
    $result = $wpdb->delete(
        $wpdb->prefix . 'nds_possible_employment',
        ['id' => $career_path_id],
        ['%d']
    );

    if ($result === false) {
        wp_redirect(add_query_arg('career_path_deleted', 'failed', $redirect_url));
        exit;
    }

    // Redirect back to course overview
    $redirect_url = add_query_arg('career_path_deleted', 'success', $redirect_url);
    wp_redirect($redirect_url);
    exit;
}

// Get career paths for a course
function nds_get_career_paths($course_id) {
    global $wpdb;
    
    return $wpdb->get_results($wpdb->prepare("
        SELECT * FROM {$wpdb->prefix}nds_possible_employment 
        WHERE course_id = %d 
        ORDER BY id DESC
    ", $course_id), ARRAY_A);
}

// Get single career path
function nds_get_career_path($career_path_id) {
    global $wpdb;
    
    return $wpdb->get_row($wpdb->prepare("
        SELECT * FROM {$wpdb->prefix}nds_possible_employment 
        WHERE id = %d
    ", $career_path_id), ARRAY_A);
}
?>
