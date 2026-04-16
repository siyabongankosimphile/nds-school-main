<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Create a new notification for a student.
 *
 * @param int    $student_id ID of the student.
 * @param string $title      Title of the notification.
 * @param string $message    Detailed message.
 * @param string $type       Type: info, success, warning, error, timetable.
 * @param string $link       Optional link to redirect the student.
 * @return int|bool          The notification ID or false on failure.
 */
function nds_create_notification($student_id, $title, $message, $type = 'info', $link = '') {
    global $wpdb;
    $table_name = $wpdb->prefix . 'nds_notifications';

    $result = $wpdb->insert(
        $table_name,
        array(
            'student_id' => $student_id,
            'title'      => $title,
            'message'    => $message,
            'type'       => $type,
            'link'       => $link,
            'is_read'    => 0,
            'created_at' => current_time('mysql'),
        ),
        array('%d', '%s', '%s', '%s', '%s', '%d', '%s')
    );

    return $result ? $wpdb->insert_id : false;
}

/**
 * Get unread notifications for a student.
 *
 * @param int $student_id ID of the student.
 * @return array          List of unread notifications.
 */
function nds_get_unread_notifications($student_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'nds_notifications';

    return $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM $table_name WHERE student_id = %d AND is_read = 0 ORDER BY created_at DESC",
            $student_id
        ),
        ARRAY_A
    );
}

/**
 * Mark a notification as read.
 *
 * @param int $notification_id ID of the notification.
 * @return bool                True on success.
 */
function nds_mark_notification_as_read($notification_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'nds_notifications';

    return $wpdb->update(
        $table_name,
        array('is_read' => 1),
        array('id' => $notification_id),
        array('%d'),
        array('%d')
    );
}

/**
 * Mark all notifications as read for a student.
 *
 * @param int $student_id ID of the student.
 * @return bool          True on success.
 */
function nds_mark_all_notifications_as_read($student_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'nds_notifications';

    return $wpdb->update(
        $table_name,
        array('is_read' => 1),
        array('student_id' => $student_id),
        array('%d'),
        array('%d')
    );
}

/**
 * AJAX handler to mark a single notification as read.
 */
add_action('wp_ajax_nds_mark_notification_read', 'nds_ajax_mark_notification_read');
function nds_ajax_mark_notification_read() {
    check_ajax_referer('nds_notifications', 'nonce');
    
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if ($id > 0) {
        nds_mark_notification_as_read($id);
        wp_send_json_success();
    }
    wp_send_json_error('Invalid notification ID');
}

/**
 * AJAX handler to mark all notifications as read for a student.
 */
add_action('wp_ajax_nds_mark_all_notifications_read', 'nds_ajax_mark_all_notifications_read');
function nds_ajax_mark_all_notifications_read() {
    check_ajax_referer('nds_notifications', 'nonce');
    
    $student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;
    if ($student_id > 0) {
        nds_mark_all_notifications_as_read($student_id);
        wp_send_json_success();
    }
    wp_send_json_error('Invalid student ID');
}

/**
 * Notify all students enrolled in a specific course.
 * 
 * @param int    $course_id The ID of the course/qualification.
 * @param string $title     The notification title.
 * @param string $message   The notification message.
 * @param string $type      The notification type (default: info).
 * @return void
 */
function nds_notify_enrolled_students($course_id, $title, $message, $type = 'info') {
    global $wpdb;
    
    $enrollments_table = $wpdb->prefix . 'nds_student_enrollments';
    
    // Get all students enrolled in this course
    $student_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT student_id FROM $enrollments_table WHERE course_id = %d AND status IN ('enrolled', 'applied')",
        $course_id
    ));
    
    if (!empty($student_ids)) {
        foreach ($student_ids as $student_id) {
            nds_create_notification($student_id, $title, $message, $type, '/portal/');
        }
    }
}
