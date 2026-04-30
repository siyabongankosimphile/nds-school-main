<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

global $wpdb;

/**
 * Check if current user is specifically a Timetable Coordinator staff member.
 */
function nds_is_timetable_coordinator() {
    if (!is_user_logged_in()) {
        return false;
    }

    if (current_user_can('manage_options')) {
        return false;
    }

    global $wpdb;
    $user_id = get_current_user_id();
    $user_email = wp_get_current_user()->user_email;
    if (!$user_id && !$user_email) {
        return false;
    }

    $role = '';
    if ($user_id) {
        $role = (string) $wpdb->get_var($wpdb->prepare(
            "SELECT role FROM {$wpdb->prefix}nds_staff WHERE user_id = %d LIMIT 1",
            $user_id
        ));
    }

    if ($role === '' && $user_email) {
        $role = (string) $wpdb->get_var($wpdb->prepare(
            "SELECT role FROM {$wpdb->prefix}nds_staff WHERE email = %s LIMIT 1",
            $user_email
        ));
    }

    return strtolower(trim($role)) === 'timetable coordinator';
}

/**
 * Check if current user can manage timetables
 * Admins can always manage, Timetable Coordinators can also manage
 */
function nds_can_manage_timetables() {
    if (current_user_can('manage_options')) {
        return true; // Admins
    }

    return nds_is_timetable_coordinator();
}

/**
 * Get all rooms/venues
 */
function nds_get_rooms($status = 'active') {
    global $wpdb;
    $query = "SELECT * FROM {$wpdb->prefix}nds_rooms";
    
    if ($status) {
        $query .= $wpdb->prepare(" WHERE is_active = %d", $status === 'active' ? 1 : 0);
    }
    
    $query .= " ORDER BY name ASC";
    return $wpdb->get_results($query);
}

/**
 * Get all course schedules for a program
 */
function nds_get_program_schedules($program_id) {
    global $wpdb;
    
    $query = $wpdb->prepare(
        "SELECT 
            cs.id,
            cs.course_id,
            cs.lecturer_id,
            cs.room_id,
            cs.days,
            cs.valid_from,
            cs.valid_to,
            cs.start_time,
            cs.end_time,
            cs.session_type,
            cs.location,
            c.name as course_name,
            c.code as course_code,
            s.first_name,
            s.last_name,
            s.email,
            r.name as room_name,
            r.code as room_code
         FROM {$wpdb->prefix}nds_course_schedules cs
         INNER JOIN {$wpdb->prefix}nds_courses c ON cs.course_id = c.id
         LEFT JOIN {$wpdb->prefix}nds_staff s ON cs.lecturer_id = s.id
         LEFT JOIN {$wpdb->prefix}nds_rooms r ON cs.room_id = r.id
         WHERE c.program_id = %d
         AND cs.is_active = 1
         ORDER BY cs.days, cs.start_time",
        $program_id
    );
    
    return $wpdb->get_results($query);
}

function nds_schedule_date_ranges_overlap($new_valid_from, $new_valid_to, $existing_valid_from, $existing_valid_to) {
    $new_start = !empty($new_valid_from) ? strtotime($new_valid_from) : null;
    $new_end = !empty($new_valid_to) ? strtotime($new_valid_to) : null;
    $existing_start = !empty($existing_valid_from) ? strtotime($existing_valid_from) : null;
    $existing_end = !empty($existing_valid_to) ? strtotime($existing_valid_to) : null;

    if ($new_start === false) {
        $new_start = null;
    }
    if ($new_end === false) {
        $new_end = null;
    }
    if ($existing_start === false) {
        $existing_start = null;
    }
    if ($existing_end === false) {
        $existing_end = null;
    }

    if ($new_start === null && $new_end === null) {
        return true;
    }

    if ($existing_start === null && $existing_end === null) {
        return true;
    }

    $new_start = $new_start ?? PHP_INT_MIN;
    $new_end = $new_end ?? PHP_INT_MAX;
    $existing_start = $existing_start ?? PHP_INT_MIN;
    $existing_end = $existing_end ?? PHP_INT_MAX;

    return $new_start <= $existing_end && $existing_start <= $new_end;
}

/**
 * Check for time clashes in same venue/room
 * @param int $room_id Room ID
 * @param string $days Comma-separated day abbreviations (mon,tue,etc)
 * @param string $start_time Start time (HH:MM:SS)
 * @param string $end_time End time (HH:MM:SS)
 * @param int $exclude_schedule_id Schedule to exclude from check (for updates)
 * @return array Conflicting schedules array
 */
function nds_check_venue_clash($room_id, $days, $start_time, $end_time, $exclude_schedule_id = 0, $valid_from = null, $valid_to = null) {
    global $wpdb;
    
    if (!$room_id || empty($days) || empty($start_time)) {
        return [];
    }
    
    // Parse days
    $days_array = array_map('trim', explode(',', $days));
    
    // Get all active schedules for this room
    $query = $wpdb->prepare(
        "SELECT 
            cs.id,
            cs.start_time,
            cs.end_time,
            cs.days,
            cs.valid_from,
            cs.valid_to,
            c.name as course_name,
            c.code as course_code,
            s.first_name,
            s.last_name
         FROM {$wpdb->prefix}nds_course_schedules cs
         INNER JOIN {$wpdb->prefix}nds_courses c ON cs.course_id = c.id
         LEFT JOIN {$wpdb->prefix}nds_staff s ON cs.lecturer_id = s.id
         WHERE cs.room_id = %d
         AND cs.is_active = 1",
        $room_id
    );
    
    if ($exclude_schedule_id > 0) {
        $query .= $wpdb->prepare(" AND cs.id != %d", $exclude_schedule_id);
    }
    
    $existing_schedules = $wpdb->get_results($query);
    $conflicts = [];
    
    $new_start_ts = strtotime($start_time);
    $new_end_ts = strtotime($end_time);
    
    foreach ($existing_schedules as $existing) {
        $existing_days = array_map('trim', explode(',', $existing->days ?? ''));
        
        // Check if days overlap
        $day_overlap = false;
        foreach ($days_array as $day) {
            if (in_array($day, $existing_days)) {
                $day_overlap = true;
                break;
            }
        }
        
        if (!$day_overlap) {
            continue;
        }

        if (!nds_schedule_date_ranges_overlap($valid_from, $valid_to, $existing->valid_from ?? null, $existing->valid_to ?? null)) {
            continue;
        }
        
        // Check if times overlap
        $existing_start_ts = strtotime($existing->start_time);
        $existing_end_ts = strtotime($existing->end_time);
        
        // Two time slots overlap if: (new_start < existing_end) AND (existing_start < new_end)
        if (($new_start_ts < $existing_end_ts) && ($existing_start_ts < $new_end_ts)) {
            $conflicts[] = [
                'schedule_id' => $existing->id,
                'course_name' => $existing->course_name,
                'course_code' => $existing->course_code,
                'lecturer' => $existing->first_name . ' ' . $existing->last_name,
                'days' => $existing->days,
                'valid_from' => $existing->valid_from,
                'valid_to' => $existing->valid_to,
                'start_time' => $existing->start_time,
                'end_time' => $existing->end_time
            ];
        }
    }
    
    return $conflicts;
}

/**
 * Check for lecturer time clashes
 * @param int $lecturer_id Staff ID
 * @param string $days Comma-separated day abbreviations
 * @param string $start_time Start time (HH:MM:SS)
 * @param string $end_time End time (HH:MM:SS)
 * @param int $exclude_schedule_id Schedule to exclude from check (for updates)
 * @return array Conflicting schedules array
 */
function nds_check_lecturer_clash($lecturer_id, $days, $start_time, $end_time, $exclude_schedule_id = 0, $valid_from = null, $valid_to = null) {
    global $wpdb;
    
    if (!$lecturer_id || empty($days) || empty($start_time)) {
        return [];
    }
    
    $days_array = array_map('trim', explode(',', $days));
    
    // Get all active schedules for this lecturer
    $query = $wpdb->prepare(
        "SELECT 
            cs.id,
            cs.start_time,
            cs.end_time,
            cs.days,
            cs.valid_from,
            cs.valid_to,
            cs.room_id,
            c.name as course_name,
            c.code as course_code,
            r.name as room_name
         FROM {$wpdb->prefix}nds_course_schedules cs
         INNER JOIN {$wpdb->prefix}nds_courses c ON cs.course_id = c.id
         LEFT JOIN {$wpdb->prefix}nds_rooms r ON cs.room_id = r.id
         WHERE cs.lecturer_id = %d
         AND cs.is_active = 1",
        $lecturer_id
    );
    
    if ($exclude_schedule_id > 0) {
        $query .= $wpdb->prepare(" AND cs.id != %d", $exclude_schedule_id);
    }
    
    $existing_schedules = $wpdb->get_results($query);
    $conflicts = [];
    
    $new_start_ts = strtotime($start_time);
    $new_end_ts = strtotime($end_time);
    
    foreach ($existing_schedules as $existing) {
        $existing_days = array_map('trim', explode(',', $existing->days ?? ''));
        
        // Check if days overlap
        $day_overlap = false;
        foreach ($days_array as $day) {
            if (in_array($day, $existing_days)) {
                $day_overlap = true;
                break;
            }
        }
        
        if (!$day_overlap) {
            continue;
        }

        if (!nds_schedule_date_ranges_overlap($valid_from, $valid_to, $existing->valid_from ?? null, $existing->valid_to ?? null)) {
            continue;
        }
        
        // Check if times overlap
        $existing_start_ts = strtotime($existing->start_time);
        $existing_end_ts = strtotime($existing->end_time);
        
        if (($new_start_ts < $existing_end_ts) && ($existing_start_ts < $new_end_ts)) {
            $conflicts[] = [
                'schedule_id' => $existing->id,
                'course_name' => $existing->course_name,
                'course_code' => $existing->course_code,
                'days' => $existing->days,
                'valid_from' => $existing->valid_from,
                'valid_to' => $existing->valid_to,
                'room' => $existing->room_name ?? $existing->room_id,
                'start_time' => $existing->start_time,
                'end_time' => $existing->end_time
            ];
        }
    }
    
    return $conflicts;
}

/**
 * Get all lecturers available for scheduling
 */
function nds_get_lecturers() {
    global $wpdb;
    return $wpdb->get_results("
        SELECT id, first_name, last_name, email
        FROM {$wpdb->prefix}nds_staff
        WHERE LOWER(role) LIKE '%lecturer%'
        OR LOWER(role) LIKE '%instructor%'
        OR LOWER(role) LIKE '%teacher%'
        AND status = 'active'
        ORDER BY first_name, last_name
    ");
}

/**
 * Create a course schedule
 */
function nds_create_schedule($schedule_data) {
    global $wpdb;
    
    // Validate required fields
    if (empty($schedule_data['course_id']) || empty($schedule_data['days']) || empty($schedule_data['start_time']) || empty($schedule_data['valid_from'])) {
        return [
            'success' => false,
            'message' => 'Course, days, start time, and start date are required.'
        ];
    }

    if (!empty($schedule_data['valid_to']) && $schedule_data['valid_to'] < $schedule_data['valid_from']) {
        return [
            'success' => false,
            'message' => 'End date cannot be before start date.'
        ];
    }
    
    // Check venue clash
    if (!empty($schedule_data['room_id'])) {
        $venue_conflicts = nds_check_venue_clash(
            $schedule_data['room_id'],
            $schedule_data['days'],
            $schedule_data['start_time'],
            $schedule_data['end_time'] ?? $schedule_data['start_time'],
            0,
            $schedule_data['valid_from'] ?? null,
            $schedule_data['valid_to'] ?? null
        );
        
        if (!empty($venue_conflicts)) {
            return [
                'success' => false,
                'message' => 'Venue is already booked for this time slot.',
                'conflicts' => $venue_conflicts
            ];
        }
    }
    
    // Check lecturer clash
    if (!empty($schedule_data['lecturer_id'])) {
        $lecturer_conflicts = nds_check_lecturer_clash(
            $schedule_data['lecturer_id'],
            $schedule_data['days'],
            $schedule_data['start_time'],
            $schedule_data['end_time'] ?? $schedule_data['start_time'],
            0,
            $schedule_data['valid_from'] ?? null,
            $schedule_data['valid_to'] ?? null
        );
        
        if (!empty($lecturer_conflicts)) {
            return [
                'success' => false,
                'message' => 'Lecturer has conflicting schedule.',
                'conflicts' => $lecturer_conflicts
            ];
        }
    }
    
    // Insert schedule
    $insert_data = [
        'course_id' => intval($schedule_data['course_id']),
        'lecturer_id' => !empty($schedule_data['lecturer_id']) ? intval($schedule_data['lecturer_id']) : null,
        'room_id' => !empty($schedule_data['room_id']) ? intval($schedule_data['room_id']) : null,
        'days' => sanitize_text_field($schedule_data['days']),
        'valid_from' => !empty($schedule_data['valid_from']) ? sanitize_text_field($schedule_data['valid_from']) : null,
        'valid_to' => !empty($schedule_data['valid_to']) ? sanitize_text_field($schedule_data['valid_to']) : null,
        'start_time' => sanitize_text_field($schedule_data['start_time']),
        'end_time' => sanitize_text_field($schedule_data['end_time'] ?? $schedule_data['start_time']),
        'session_type' => sanitize_text_field($schedule_data['session_type'] ?? 'lecture'),
        'location' => sanitize_text_field($schedule_data['location'] ?? ''),
        'is_active' => 1
    ];
    
    $result = $wpdb->insert("{$wpdb->prefix}nds_course_schedules", $insert_data);
    
    if ($result === false) {
        return [
            'success' => false,
            'message' => 'Error creating schedule: ' . $wpdb->last_error
        ];
    }
    
    return [
        'success' => true,
        'message' => 'Schedule created successfully!',
        'schedule_id' => $wpdb->insert_id
    ];
}

/**
 * Update a course schedule
 */
function nds_update_schedule($schedule_id, $schedule_data) {
    global $wpdb;
    
    $schedule = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}nds_course_schedules WHERE id = %d",
        $schedule_id
    ));
    
    if (!$schedule) {
        return [
            'success' => false,
            'message' => 'Schedule not found.'
        ];
    }

    if (!empty($schedule_data['valid_from']) && !empty($schedule_data['valid_to']) && $schedule_data['valid_to'] < $schedule_data['valid_from']) {
        return [
            'success' => false,
            'message' => 'End date cannot be before start date.'
        ];
    }
    
    // Check venue clash
    if (!empty($schedule_data['room_id'])) {
        $venue_conflicts = nds_check_venue_clash(
            $schedule_data['room_id'],
            $schedule_data['days'],
            $schedule_data['start_time'],
            $schedule_data['end_time'] ?? $schedule_data['start_time'],
            $schedule_id,
            $schedule_data['valid_from'] ?? null,
            $schedule_data['valid_to'] ?? null
        );
        
        if (!empty($venue_conflicts)) {
            return [
                'success' => false,
                'message' => 'Venue is already booked for this time slot.',
                'conflicts' => $venue_conflicts
            ];
        }
    }
    
    // Check lecturer clash
    if (!empty($schedule_data['lecturer_id'])) {
        $lecturer_conflicts = nds_check_lecturer_clash(
            $schedule_data['lecturer_id'],
            $schedule_data['days'],
            $schedule_data['start_time'],
            $schedule_data['end_time'] ?? $schedule_data['start_time'],
            $schedule_id,
            $schedule_data['valid_from'] ?? null,
            $schedule_data['valid_to'] ?? null
        );
        
        if (!empty($lecturer_conflicts)) {
            return [
                'success' => false,
                'message' => 'Lecturer has conflicting schedule.',
                'conflicts' => $lecturer_conflicts
            ];
        }
    }
    
    // Update schedule
    $update_data = [
        'course_id' => intval($schedule_data['course_id']),
        'lecturer_id' => !empty($schedule_data['lecturer_id']) ? intval($schedule_data['lecturer_id']) : null,
        'room_id' => !empty($schedule_data['room_id']) ? intval($schedule_data['room_id']) : null,
        'days' => sanitize_text_field($schedule_data['days']),
        'valid_from' => !empty($schedule_data['valid_from']) ? sanitize_text_field($schedule_data['valid_from']) : null,
        'valid_to' => !empty($schedule_data['valid_to']) ? sanitize_text_field($schedule_data['valid_to']) : null,
        'start_time' => sanitize_text_field($schedule_data['start_time']),
        'end_time' => sanitize_text_field($schedule_data['end_time'] ?? $schedule_data['start_time']),
        'session_type' => sanitize_text_field($schedule_data['session_type'] ?? 'lecture'),
        'location' => sanitize_text_field($schedule_data['location'] ?? '')
    ];
    
    $result = $wpdb->update(
        "{$wpdb->prefix}nds_course_schedules",
        $update_data,
        ['id' => $schedule_id]
    );
    
    if ($result === false) {
        return [
            'success' => false,
            'message' => 'Error updating schedule: ' . $wpdb->last_error
        ];
    }
    
    return [
        'success' => true,
        'message' => 'Schedule updated successfully!'
    ];
}

/**
 * Delete a course schedule
 */
function nds_delete_schedule($schedule_id) {
    global $wpdb;
    
    $result = $wpdb->update(
        "{$wpdb->prefix}nds_course_schedules",
        ['is_active' => 0],
        ['id' => $schedule_id]
    );
    
    if ($result === false) {
        return [
            'success' => false,
            'message' => 'Error deleting schedule: ' . $wpdb->last_error
        ];
    }
    
    return [
        'success' => true,
        'message' => 'Schedule deleted successfully!'
    ];
}
