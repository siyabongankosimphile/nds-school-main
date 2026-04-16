<?php
/**
 * Calendar Functions
 * Handles schedule creation, updates, and deletions
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

global $wpdb;
$schedules_table = $wpdb->prefix . 'nds_course_schedules';

/**
 * Handle schedule data from form
 */
function nds_handle_schedule_data($request_type = 'POST') {
    $request_data = ($request_type === 'POST') ? $_POST : $_GET;

    $days = isset($request_data['days']) && is_array($request_data['days']) 
        ? implode(',', array_map('sanitize_text_field', $request_data['days']))
        : '';

    $pattern_type = isset($request_data['pattern_type']) && $request_data['pattern_type'] !== ''
        ? sanitize_text_field($request_data['pattern_type'])
        : 'every_week';

    $valid_from = !empty($request_data['valid_from']) ? sanitize_text_field($request_data['valid_from']) : null;
    $valid_to   = !empty($request_data['valid_to']) ? sanitize_text_field($request_data['valid_to']) : null;

    // pattern_meta can be arbitrary JSON or text describing complex patterns.
    // We store it as-is (sanitized) and interpret it when generating events.
    $pattern_meta_raw = isset($request_data['pattern_meta']) ? wp_unslash($request_data['pattern_meta']) : null;
    $pattern_meta     = $pattern_meta_raw !== null ? wp_kses_post($pattern_meta_raw) : null;

    return [
        'course_id'    => isset($request_data['course_id']) ? intval($request_data['course_id']) : 0,
        'lecturer_id'  => isset($request_data['lecturer_id']) ? intval($request_data['lecturer_id']) : null,
        'days'         => $days,
        'start_time'   => isset($request_data['start_time']) ? sanitize_text_field($request_data['start_time']) : '',
        'end_time'     => isset($request_data['end_time']) ? sanitize_text_field($request_data['end_time']) : '',
        'location'     => isset($request_data['location']) ? sanitize_text_field($request_data['location']) : '',
        'session_type' => isset($request_data['session_type']) ? sanitize_text_field($request_data['session_type']) : 'theory',
        'is_active'    => 1,
        'cohort_id'    => isset($request_data['cohort_id']) ? intval($request_data['cohort_id']) : null,
        'pattern_type' => $pattern_type,
        'pattern_meta' => $pattern_meta,
        'valid_from'   => $valid_from,
        'valid_to'     => $valid_to,
    ];
}

/**
 * Add new schedule
 */
add_action('admin_post_nds_add_schedule', 'nds_add_schedule');
function nds_add_schedule() {
    // Check nonce and permissions
    if (!isset($_POST['nds_add_schedule_nonce']) || !wp_verify_nonce($_POST['nds_add_schedule_nonce'], 'nds_add_schedule_nonce')) {
        wp_die('Security check failed');
    }
    
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    global $wpdb, $schedules_table;

    // Check if table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$schedules_table'") === $schedules_table;
    if (!$table_exists) {
        wp_redirect(add_query_arg('error', 'db_error', wp_get_referer()));
        exit;
    }

    $data = nds_handle_schedule_data('POST');
    
    // Validate required fields
    if (empty($data['course_id']) || empty($data['days']) || empty($data['start_time']) || empty($data['end_time'])) {
        wp_redirect(add_query_arg('error', 'missing_fields', wp_get_referer()));
        exit;
    }

    // Calculate day hours if needed
    $start = strtotime($data['start_time']);
    $end = strtotime($data['end_time']);
    $day_hours = ($end - $start) / 3600; // Convert seconds to hours

    $data['day_hours'] = round($day_hours, 2);

    // Insert schedule
    $result = $wpdb->insert(
        $schedules_table,
        $data,
        [
            '%d', // course_id
            '%d', // lecturer_id
            '%s', // days
            '%s', // start_time
            '%s', // end_time
            '%s', // location
            '%s', // session_type
            '%d', // is_active
            '%f', // day_hours
            '%d', // cohort_id
            '%s', // pattern_type
            '%s', // pattern_meta
            '%s', // valid_from
            '%s', // valid_to
        ]
    );

    if ($result === false) {
        wp_redirect(add_query_arg('error', 'db_error', wp_get_referer()));
        exit;
    }

    // Notify students of new schedule
    if (function_exists('nds_notify_enrolled_students')) {
        $course_name = $wpdb->get_var($wpdb->prepare("SELECT name FROM {$wpdb->prefix}nds_courses WHERE id = %d", $data['course_id']));
        nds_notify_enrolled_students(
            $data['course_id'],
            'New Schedule Added',
            'A new session has been added for ' . ($course_name ?: 'your course') . '. Please check the timetable.',
            'timetable'
        );
    }

    // Redirect with success message
    wp_redirect(add_query_arg('success', 'schedule_created', wp_get_referer()));
    exit;
}

/**
 * Get all schedules
 */
function nds_get_schedules($filters = []) {
    global $wpdb;
    $schedules_table = $wpdb->prefix . 'nds_course_schedules';
    $courses_table = $wpdb->prefix . 'nds_courses';
    $staff_table = $wpdb->prefix . 'nds_staff';

    $where = ['s.is_active = 1'];
    $params = [];

    if (!empty($filters['course_id'])) {
        $where[] = 's.course_id = %d';
        $params[] = intval($filters['course_id']);
    }

    if (!empty($filters['lecturer_id'])) {
        $where[] = 's.lecturer_id = %d';
        $params[] = intval($filters['lecturer_id']);
    }

    $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    $query = "
        SELECT s.*, 
               c.name as course_name,
               c.code as course_code,
               st.first_name, st.last_name
        FROM {$schedules_table} s
        LEFT JOIN {$courses_table} c ON s.course_id = c.id
        LEFT JOIN {$staff_table} st ON s.lecturer_id = st.id
        {$where_clause}
        ORDER BY s.days, s.start_time
    ";

    if (!empty($params)) {
        $query = $wpdb->prepare($query, $params);
    }

    return $wpdb->get_results($query, ARRAY_A);
}

/**
 * AJAX handler to fetch calendar events (PUBLIC - for frontend)
 * Returns custom calendar events, active programs, and course schedules
 * OPTIMIZED: Uses caching for better performance
 */
add_action('wp_ajax_nds_public_calendar_events', 'nds_get_public_calendar_events_ajax');
add_action('wp_ajax_nopriv_nds_public_calendar_events', 'nds_get_public_calendar_events_ajax');
function nds_get_public_calendar_events_ajax() {
    // Public endpoint - verify nonce if provided, but allow access without it for public calendar
    // This is safe because we're only reading public data (active events, programs, schedules)
    if (isset($_POST['nonce'])) {
        if (!wp_verify_nonce($_POST['nonce'], 'nds_public_calendar_nonce')) {
            wp_send_json_error('Invalid nonce');
            return;
        }
    }
    
    // Get student ID if logged in as a student (for filtering by enrolled courses)
    $student_id = 0;
    $enrolled_course_ids = array();
    
    if (is_user_logged_in() && function_exists('nds_portal_get_current_student_id')) {
        $student_id = nds_portal_get_current_student_id();
        
        // If student is logged in, get their enrolled courses
        if ($student_id > 0) {
            global $wpdb;
            $enrollments_table = $wpdb->prefix . 'nds_student_enrollments';
            
            // Get active academic year and semester
            $active_year_id = (int) $wpdb->get_var("SELECT id FROM {$wpdb->prefix}nds_academic_years WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
            $active_semester_id = $active_year_id ? (int) $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}nds_semesters WHERE academic_year_id = %d AND is_active = 1 ORDER BY id DESC LIMIT 1",
                $active_year_id
            )) : 0;
            
            // Get enrolled course IDs for this student in the active term
            if ($active_year_id && $active_semester_id) {
                $enrollments = $wpdb->get_results($wpdb->prepare(
                    "SELECT DISTINCT course_id 
                     FROM {$enrollments_table} 
                     WHERE student_id = %d 
                     AND academic_year_id = %d 
                     AND semester_id = %d 
                     AND status IN ('applied', 'enrolled', 'waitlisted')",
                    $student_id, $active_year_id, $active_semester_id
                ), ARRAY_A);
                
                $enrolled_course_ids = array_map('intval', array_column($enrollments, 'course_id'));
            }
        }
    }
    
    // Pass enrolled course IDs to filter function (if student) or null (if admin/public)
    nds_fetch_calendar_events_data($enrolled_course_ids);
}

/**
 * AJAX handler to fetch calendar events (ADMIN ONLY)
 * Returns custom calendar events, active programs, and course schedules
 * OPTIMIZED: Uses caching for better performance
 */
add_action('wp_ajax_nds_admin_calendar_events', 'nds_get_calendar_events_ajax');
function nds_get_calendar_events_ajax() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'nds_calendar_nonce')) {
        wp_send_json_error('Invalid nonce');
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }
    
    // Admin view - show all events (no filtering by enrolled courses or lecturer)
    nds_fetch_calendar_events_data(null, null);
}

/**
 * AJAX handler to fetch calendar events (STAFF PORTAL)
 * Returns calendar events filtered by lecturer_id for the logged-in staff member
 */
add_action('wp_ajax_nds_staff_calendar_events', 'nds_get_staff_calendar_events_ajax');
function nds_get_staff_calendar_events_ajax() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'nds_staff_calendar_nonce')) {
        wp_send_json_error('Invalid nonce');
    }
    
    // Must be logged in
    if (!is_user_logged_in()) {
        wp_send_json_error('Unauthorized');
    }
    
    // Get current staff ID
    if (!function_exists('nds_portal_get_current_staff_id')) {
        wp_send_json_error('Staff portal not available');
    }
    
    $staff_id = (int) nds_portal_get_current_staff_id();
    if ($staff_id <= 0) {
        wp_send_json_error('Staff profile not found');
    }
    
    // Staff view - filter by lecturer_id (show only their courses)
    nds_fetch_calendar_events_data(null, $staff_id);
}

/**
 * Shared function to fetch calendar events data
 * Used by both admin and public endpoints
 * @param array|null $enrolled_course_ids Array of course IDs to filter schedules (null = show all)
 * @param int|null $lecturer_id Filter schedules by lecturer ID (for staff portal)
 */
function nds_fetch_calendar_events_data($enrolled_course_ids = null, $lecturer_id = null) {
    
    global $wpdb;
    $events = array();
    
    $start = isset($_POST['start']) ? sanitize_text_field($_POST['start']) : date('Y-m-d');
    $end = isset($_POST['end']) ? sanitize_text_field($_POST['end']) : date('Y-m-d', strtotime('+1 month'));
    $view_type = isset($_POST['view']) ? sanitize_text_field($_POST['view']) : 'dayGridMonth';
    $is_year_view = ($view_type === 'dayGridYear');

    // Optional cohort filter (for student/cohort-specific views)
    $cohort_id = isset($_POST['cohort_id']) ? intval($_POST['cohort_id']) : 0;
    
    // Include enrolled course IDs and lecturer ID in cache key for student/staff-specific caching
    $cache_key_suffix = $cohort_id . '|' . ($enrolled_course_ids !== null ? implode(',', $enrolled_course_ids) : 'all') . '|' . ($lecturer_id ?: 'all');
    $cache_key = 'nds_calendar_events_' . md5($start . $end . '|' . $cache_key_suffix);
    $cached_events = get_transient($cache_key);
    
    if (false !== $cached_events) {
        wp_send_json_success($cached_events);
        return;
    }
    
    // 1. Get custom calendar events
    $calendar_events_table = $wpdb->prefix . 'nds_calendar_events';
    $events_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$calendar_events_table'") === $calendar_events_table;
    
    if ($events_table_exists) {
        // Get current user roles for audience filtering
        $current_user = wp_get_current_user();
        $user_roles = $current_user->roles;
        $audience_condition = "AND (audience = 'all'";
        
        if (!empty($user_roles)) {
            foreach ($user_roles as $role) {
                $audience_condition .= $wpdb->prepare(" OR audience LIKE %s", '%' . $wpdb->esc_like($role) . '%');
            }
        }
        $audience_condition .= ")";
        
        $custom_events = $wpdb->get_results($wpdb->prepare("
            SELECT id, title, description, start_date, end_date, all_day, color, location, event_type, audience
            FROM {$calendar_events_table}
            WHERE status = 'active'
            AND start_date >= %s
            AND start_date <= %s
            {$audience_condition}
            ORDER BY start_date ASC
        ", $start, $end), ARRAY_A);
        
        foreach ($custom_events as $event) {
            $event_start = $event['start_date'];
            $event_end = $event['end_date'] ?: $event['start_date'];
            
            // If all_day, set time to start/end of day
            if ($event['all_day']) {
                $event_start = date('Y-m-d', strtotime($event_start)) . 'T00:00:00';
                $event_end = date('Y-m-d', strtotime($event_end)) . 'T23:59:59';
            }
            
            $events[] = array(
                'id' => 'event_' . $event['id'],
                'title' => $event['title'],
                'start' => $event_start,
                'end' => $event_end,
                'allDay' => (bool) $event['all_day'],
                'color' => $event['color'] ?: '#3788d8',
                'interactive' => true,
                'extendedProps' => array(
                    'type' => 'custom_event',
                    'description' => $event['description'],
                    'location' => $event['location'],
                    'event_type' => $event['event_type']
                )
            );
        }
    }
    
    // 2. Get active programs (as events - using created_at as start date)
    // Only show programs for admin users, not for students
    // In year view, group programs by month instead of showing individual dates
    $programs_table = $wpdb->prefix . 'nds_programs';
    
    // Skip programs if filtering by enrolled courses (student view)
    $show_programs = ($enrolled_course_ids === null);
    
    if ($show_programs) {
    if ($is_year_view) {
        // For year view, get all active programs and group by month
        $programs = $wpdb->get_results("
            SELECT id, name, description, created_at, status
            FROM {$programs_table}
            WHERE status = 'active'
            ORDER BY name ASC
        ", ARRAY_A);
        
        // Group programs by the month of their created_at date
        $programs_by_month = array();
        foreach ($programs as $program) {
            if ($program['created_at']) {
                $month_key = date('Y-m', strtotime($program['created_at']));
                $month_start = $month_key . '-01';
                
                // Only include if month is within date range
                if ($month_start >= $start && $month_start <= $end) {
                    if (!isset($programs_by_month[$month_key])) {
                        $programs_by_month[$month_key] = array();
                    }
                    $programs_by_month[$month_key][] = $program;
                }
            }
        }
        
        // Create grouped events for each month
        foreach ($programs_by_month as $month_key => $month_programs) {
            $month_start_date = $month_key . '-01';
            foreach ($month_programs as $program) {
                $events[] = array(
                    'id' => 'program_' . $program['id'] . '_' . $month_key,
                    'title' => 'Program: ' . $program['name'],
                    'start' => $month_start_date,
                    'allDay' => true,
                    'color' => '#10b981', // Green color for programs
                    'interactive' => true,
                    'extendedProps' => array(
                        'type' => 'program',
                        'description' => $program['description'],
                        'program_id' => $program['id'],
                        'month' => $month_key
                    )
                );
            }
        }
    } else {
        // For other views, show programs on their created_at date
        $programs = $wpdb->get_results($wpdb->prepare("
            SELECT id, name, description, created_at, status
            FROM {$programs_table}
            WHERE status = 'active'
            AND created_at >= %s
            AND created_at <= %s
            ORDER BY created_at ASC
        ", $start, $end), ARRAY_A);
        
        foreach ($programs as $program) {
            $events[] = array(
                'id' => 'program_' . $program['id'],
                'title' => 'Program: ' . $program['name'],
                'start' => $program['created_at'],
                'allDay' => true,
                'color' => '#10b981', // Green color for programs
                'interactive' => true,
                'extendedProps' => array(
                    'type' => 'program',
                    'description' => $program['description'],
                    'program_id' => $program['id']
                )
            );
        }
        }
    }
    
    // 3. Get course schedules (convert recurring schedules to events for the date range)
    $schedules_table = $wpdb->prefix . 'nds_course_schedules';
    $courses_table = $wpdb->prefix . 'nds_courses';
    $staff_table = $wpdb->prefix . 'nds_staff';
    
    $schedules_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$schedules_table'") === $schedules_table;
    
    if ($schedules_table_exists) {
        // Build schedule query with optional cohort, enrolled course, and lecturer filtering
        $cohort_condition = '';
        $course_condition = '';
        $lecturer_condition = '';
        $params = array();
        
        if ($cohort_id > 0) {
            // Show schedules that apply to this cohort specifically or are global (cohort_id IS NULL)
            $cohort_condition = ' AND (s.cohort_id IS NULL OR s.cohort_id = %d)';
            $params[] = $cohort_id;
        }
        
        // Filter by lecturer if staff-specific view
        if ($lecturer_id > 0) {
            $lecturer_condition = ' AND s.lecturer_id = %d';
            $params[] = $lecturer_id;
        }
        
        // Filter by enrolled courses if student-specific view
        if ($enrolled_course_ids !== null && !empty($enrolled_course_ids)) {
            $placeholders = implode(',', array_fill(0, count($enrolled_course_ids), '%d'));
            $course_condition = " AND s.course_id IN ($placeholders)";
            $params = array_merge($params, $enrolled_course_ids);
        } elseif ($enrolled_course_ids !== null && empty($enrolled_course_ids)) {
            // Student has no enrolled courses - return empty schedules
            $course_condition = ' AND 1=0'; // This will return no results
        }

        $schedule_sql = "
            SELECT s.*, 
                   c.name as course_name,
                   c.code as course_code,
                   c.color as course_color,
                   st.first_name, st.last_name
            FROM {$schedules_table} s
            LEFT JOIN {$courses_table} c ON s.course_id = c.id
            LEFT JOIN {$staff_table} st ON s.lecturer_id = st.id
            WHERE s.is_active = 1
            {$cohort_condition}
            {$course_condition}
            {$lecturer_condition}
            ORDER BY s.days, s.start_time
        ";

        if (!empty($params)) {
            $schedule_sql = $wpdb->prepare($schedule_sql, $params);
        }

        $schedules = $wpdb->get_results($schedule_sql, ARRAY_A);

        // Preload schedule exceptions for the date range
        $schedule_exceptions = array();
        $exceptions_table = $wpdb->prefix . 'nds_schedule_exceptions';
        $exceptions_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$exceptions_table'") === $exceptions_table;

        if ($exceptions_table_exists) {
            $exceptions = $wpdb->get_results(
                $wpdb->prepare(
                    "
                    SELECT *
                    FROM {$exceptions_table}
                    WHERE date >= %s
                      AND date <= %s
                    ",
                    $start,
                    $end
                ),
                ARRAY_A
            );

            foreach ($exceptions as $exception) {
                $key = $exception['schedule_id'] . '|' . $exception['date'];
                if (!isset($schedule_exceptions[$key])) {
                    $schedule_exceptions[$key] = array();
                }
                $schedule_exceptions[$key][] = $exception;
            }
        }

        // Helper to check if a given date matches the pattern for a schedule
        $matches_pattern = function($schedule, $current_ts) {
            $pattern_type = !empty($schedule['pattern_type']) ? $schedule['pattern_type'] : 'every_week';
            $pattern_meta = !empty($schedule['pattern_meta']) ? json_decode($schedule['pattern_meta'], true) : null;

            $date_str = date('Y-m-d', $current_ts);
            $week_num = (int) date('W', $current_ts); // ISO week number

            switch ($pattern_type) {
                case 'odd_weeks':
                    return ($week_num % 2) === 1;
                case 'even_weeks':
                    return ($week_num % 2) === 0;
                case 'week_1':
                    // Treat week_1 as the first week in a two-week cycle (similar to odd weeks)
                    return (($week_num - 1) % 2) === 0;
                case 'week_2':
                    // Treat week_2 as the second week in a two-week cycle (similar to even weeks)
                    return (($week_num - 1) % 2) === 1;
                case 'block_range':
                    if (is_array($pattern_meta)) {
                        $block_start = isset($pattern_meta['start_date']) ? $pattern_meta['start_date'] : null;
                        $block_end   = isset($pattern_meta['end_date']) ? $pattern_meta['end_date'] : null;
                        if ($block_start && $date_str < $block_start) {
                            return false;
                        }
                        if ($block_end && $date_str > $block_end) {
                            return false;
                        }
                    }
                    return true;
                case 'custom':
                    if (is_array($pattern_meta) && !empty($pattern_meta['dates']) && is_array($pattern_meta['dates'])) {
                        return in_array($date_str, $pattern_meta['dates'], true);
                    }
                    return true;
                case 'every_week':
                default:
                    return true;
            }
        };

        // Convert schedules to events for the date range
        $start_ts = strtotime($start);
        $end_ts = strtotime($end);
        
        // Map both full day names and abbreviations to day numbers (0=Sunday, 1=Monday, etc.)
        $day_map = array(
            // Abbreviations
            'mon' => 1, 'tue' => 2, 'wed' => 3, 'thu' => 4, 'fri' => 5, 'sat' => 6, 'sun' => 0,
            // Full names
            'monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4, 
            'friday' => 5, 'saturday' => 6, 'sunday' => 0
        );
        
        foreach ($schedules as $schedule) {
            // Get course info to check start_date and end_date
            $course_info = $wpdb->get_row($wpdb->prepare(
                "SELECT start_date, end_date, status FROM {$courses_table} WHERE id = %d",
                $schedule['course_id']
            ), ARRAY_A);
            
            // Skip if course is not active
            if (!$course_info || $course_info['status'] !== 'active') {
                continue;
            }
            
            // Handle SET column - MySQL returns SET values as comma-separated string
            // Can be "mon,tue" or "Monday,Tuesday" depending on how it was stored
            $days_raw = $schedule['days'];
            // If it's stored as SET, MySQL returns it as comma-separated without spaces
            // If it was stored incorrectly with full names, handle that too
            $days = array_map('trim', explode(',', $days_raw));
            $current_ts = $start_ts;
            
            while ($current_ts <= $end_ts) {
                $day_of_week = date('w', $current_ts); // 0 = Sunday, 1 = Monday, etc.
                $event_date = date('Y-m-d', $current_ts);

                // Respect course start_date and end_date
                // Allow viewing past schedules - only filter future dates before course start
                // This ensures historical schedules are visible when viewing previous months
                if (!empty($course_info['start_date']) && $event_date < $course_info['start_date']) {
                    // Only skip if the event date is in the future relative to today
                    // This allows past schedules to be visible even if they're before course start_date
                    $today = date('Y-m-d');
                    if ($event_date >= $today) {
                        // Future date before course start - skip it
                        $current_ts = strtotime('+1 day', $current_ts);
                        continue;
                    }
                    // Past date - show it even if before course start_date (for historical viewing)
                }
                if (!empty($course_info['end_date']) && $event_date > $course_info['end_date']) {
                    $current_ts = strtotime('+1 day', $current_ts);
                    continue;
                }

                // Respect schedule valid_from / valid_to if set
                if (!empty($schedule['valid_from']) && $event_date < $schedule['valid_from']) {
                    $current_ts = strtotime('+1 day', $current_ts);
                    continue;
                }
                if (!empty($schedule['valid_to']) && $event_date > $schedule['valid_to']) {
                    $current_ts = strtotime('+1 day', $current_ts);
                    continue;
                }

                // Check if this date matches the schedule's day(s) of week
                $matches_day = false;
                foreach ($days as $day) {
                    $day = trim(strtolower($day));
                    if (isset($day_map[$day]) && $day_map[$day] == $day_of_week) {
                        $matches_day = true;
                        break;
                    }
                }

                if ($matches_day && $matches_pattern($schedule, $current_ts)) {
                    $start_datetime = $event_date . ' ' . $schedule['start_time'];
                    $end_datetime = $event_date . ' ' . $schedule['end_time'];

                    $base_location = $schedule['location'];

                    // Apply schedule exceptions for this schedule/date (and cohort, if provided)
                    $exception_key = $schedule['id'] . '|' . $event_date;
                    $exceptions_for_day = isset($schedule_exceptions[$exception_key]) ? $schedule_exceptions[$exception_key] : array();

                    // Filter exceptions by cohort if a cohort is specified
                    $filtered_exceptions = array();
                    if (!empty($exceptions_for_day)) {
                        foreach ($exceptions_for_day as $ex) {
                            if ($cohort_id > 0) {
                                // Apply if exception is for this cohort or global (NULL)
                                if (empty($ex['cohort_id']) || intval($ex['cohort_id']) === $cohort_id) {
                                    $filtered_exceptions[] = $ex;
                                }
                            } else {
                                // No cohort filter, include all
                                $filtered_exceptions[] = $ex;
                            }
                        }
                    }

                    $cancel_main_event = false;
                    $extra_events = array();

                    foreach ($filtered_exceptions as $ex) {
                        $action = isset($ex['action']) ? $ex['action'] : 'cancel';
                        switch ($action) {
                            case 'cancel':
                                $cancel_main_event = true;
                                break;
                            case 'move':
                                if (!empty($ex['new_start_time'])) {
                                    $start_datetime = $event_date . ' ' . $ex['new_start_time'];
                                }
                                if (!empty($ex['new_end_time'])) {
                                    $end_datetime = $event_date . ' ' . $ex['new_end_time'];
                                }
                                if (!empty($ex['new_location'])) {
                                    $base_location = $ex['new_location'];
                                }
                                break;
                            case 'extra':
                                $extra_start = !empty($ex['new_start_time']) ? $event_date . ' ' . $ex['new_start_time'] : $start_datetime;
                                $extra_end   = !empty($ex['new_end_time']) ? $event_date . ' ' . $ex['new_end_time'] : $end_datetime;
                                $extra_location = !empty($ex['new_location']) ? $ex['new_location'] : $base_location;

                                $extra_events[] = array(
                                    'id' => 'schedule_' . $schedule['id'] . '_' . $event_date . '_extra_' . $ex['id'],
                                    'title' => $schedule['course_name'] . ($schedule['course_code'] ? ' (' . $schedule['course_code'] . ')' : ''),
                                    'start' => $extra_start,
                                    'end' => $extra_end,
                                    'allDay' => false,
                                    'color' => $schedule['course_color'] ?: '#8b5cf6', // Use course color or fallback to purple
                                    'interactive' => true,
                                    'extendedProps' => array(
                                        'type' => 'schedule',
                                        'lecturer' => trim(($schedule['first_name'] ?? '') . ' ' . ($schedule['last_name'] ?? '')),
                                        'location' => $extra_location,
                                        'schedule_id' => $schedule['id'],
                                        'exception_id' => $ex['id'],
                                    )
                                );
                                break;
                        }
                    }

                    // Add main event unless it's been cancelled by an exception
                    // Skip individual schedules in year view - only show grouped courses
                    if (!$cancel_main_event && !$is_year_view) {
                        $events[] = array(
                            'id' => 'schedule_' . $schedule['id'] . '_' . $event_date,
                            'title' => $schedule['course_name'] . ($schedule['course_code'] ? ' (' . $schedule['course_code'] . ')' : ''),
                            'start' => $start_datetime,
                            'end' => $end_datetime,
                            'allDay' => false,
                            'color' => $schedule['course_color'] ?: '#8b5cf6', // Use course color or fallback to purple
                            'interactive' => true,
                            'extendedProps' => array(
                                'type' => 'schedule',
                                'lecturer' => trim(($schedule['first_name'] ?? '') . ' ' . ($schedule['last_name'] ?? '')),
                                'location' => $base_location,
                                'schedule_id' => $schedule['id'],
                            )
                        );
                    }

                    // Add any extra events defined by exceptions (skip in year view)
                    if (!$is_year_view) {
                        foreach ($extra_events as $extra_event) {
                            $events[] = $extra_event;
                        }
                    }
                }
                
                $current_ts = strtotime('+1 day', $current_ts);
            }
        }
        
        // For year view, add grouped course events by month instead of individual schedules
        if ($is_year_view) {
            // Build course filter condition based on enrolled courses (for students)
            $course_filter = '';
            $course_params = array($end, $start);
            if ($enrolled_course_ids !== null && !empty($enrolled_course_ids)) {
                $placeholders = implode(',', array_fill(0, count($enrolled_course_ids), '%d'));
                $course_filter = " AND c.id IN ($placeholders)";
                $course_params = array_merge($enrolled_course_ids, $course_params);
            } elseif ($enrolled_course_ids !== null && empty($enrolled_course_ids)) {
                // Student has no enrolled courses - skip year view courses
                $course_filter = ' AND 1=0';
            }
            
            // Get all active courses and group them by month
            $courses_query = "
                SELECT DISTINCT c.id, c.name, c.code, c.start_date, c.end_date
                FROM {$courses_table} c
                INNER JOIN {$schedules_table} s ON c.id = s.course_id
                WHERE c.status = 'active'
                AND s.is_active = 1
                AND (
                    (c.start_date IS NULL OR c.start_date <= %s)
                    AND (c.end_date IS NULL OR c.end_date >= %s)
                )
                {$course_filter}
                ORDER BY c.name
            ";
            $courses = $wpdb->get_results($wpdb->prepare($courses_query, $course_params), ARRAY_A);
            
            // Group courses by month
            $courses_by_month = array();
            foreach ($courses as $course) {
                $course_start = $course['start_date'] ?: $start;
                $course_end = $course['end_date'] ?: $end;
                
                // Create events for each month the course spans
                $current_month_ts = strtotime(date('Y-m-01', strtotime($course_start)));
                $end_month_ts = strtotime(date('Y-m-t', strtotime($course_end)));
                
                while ($current_month_ts <= $end_month_ts) {
                    $month_key = date('Y-m', $current_month_ts);
                    $month_start = date('Y-m-01', $current_month_ts);
                    $month_end = date('Y-m-t', $current_month_ts);
                    
                    // Only include if month overlaps with requested date range
                    if ($month_end >= $start && $month_start <= $end) {
                        if (!isset($courses_by_month[$month_key])) {
                            $courses_by_month[$month_key] = array();
                        }
                        $course_key = $course['id'];
                        if (!isset($courses_by_month[$month_key][$course_key])) {
                            $courses_by_month[$month_key][$course_key] = $course;
                        }
                    }
                    
                    $current_month_ts = strtotime('+1 month', $current_month_ts);
                }
            }
            
            // Create all-day events for each course in each month
            foreach ($courses_by_month as $month_key => $month_courses) {
                $month_start_date = $month_key . '-01';
                foreach ($month_courses as $course) {
                    $events[] = array(
                        'id' => 'course_month_' . $course['id'] . '_' . $month_key,
                        'title' => $course['name'] . ($course['code'] ? ' (' . $course['code'] . ')' : ''),
                        'start' => $month_start_date,
                        'allDay' => true,
                        'color' => '#8b5cf6', // Purple color for courses
                        'extendedProps' => array(
                            'type' => 'course',
                            'course_id' => $course['id'],
                            'month' => $month_key
                        )
                    );
                }
            }
        }
    }
    
    // Cache the results for 2 minutes
    set_transient($cache_key, $events, 2 * MINUTE_IN_SECONDS);
    
    wp_send_json_success($events);
}

/**
 * Clear calendar cache when events are added/updated/deleted
 */
function nds_clear_calendar_cache() {
    global $wpdb;
    // Use prepared queries to safely remove matching transients from options table
    $like_events = '_transient_nds_calendar_events_%';
    $like_timeouts = '_transient_timeout_nds_calendar_events_%';

    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            $like_events
        )
    );
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            $like_timeouts
        )
    );
}

// Clear cache when calendar events or schedules are modified (add/update/delete)
add_action('admin_post_nds_add_calendar_event', 'nds_clear_calendar_cache', 20);
add_action('admin_post_nds_update_calendar_event', 'nds_clear_calendar_cache', 20);
add_action('admin_post_nds_delete_calendar_event', 'nds_clear_calendar_cache', 20);

add_action('admin_post_nds_add_schedule', 'nds_clear_calendar_cache', 20);
add_action('admin_post_nds_update_schedule', 'nds_clear_calendar_cache', 20);
add_action('admin_post_nds_delete_schedule', 'nds_clear_calendar_cache', 20);

/**
 * Add new calendar event
 */
add_action('admin_post_nds_add_calendar_event', 'nds_add_calendar_event');
function nds_add_calendar_event() {
    // Check nonce and permissions
    if (!isset($_POST['nds_add_calendar_event_nonce']) || !wp_verify_nonce($_POST['nds_add_calendar_event_nonce'], 'nds_add_calendar_event_nonce')) {
        wp_die('Security check failed');
    }
    
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    global $wpdb;
    $calendar_events_table = $wpdb->prefix . 'nds_calendar_events';

    // Check if table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$calendar_events_table'") === $calendar_events_table;
    if (!$table_exists) {
        wp_redirect(add_query_arg('error', 'db_error', wp_get_referer()));
        exit;
    }

    // Validate required fields
    if (empty($_POST['event_title']) || empty($_POST['event_start_date'])) {
        wp_redirect(add_query_arg('error', 'missing_fields', wp_get_referer()));
        exit;
    }

    $all_day = isset($_POST['event_all_day']) && $_POST['event_all_day'] == '1' ? 1 : 0;
    $start_date = sanitize_text_field($_POST['event_start_date']);
    $end_date = !empty($_POST['event_end_date']) ? sanitize_text_field($_POST['event_end_date']) : $start_date;
    
    // Handle audience
    $audience = 'all'; // default
    if (isset($_POST['event_audience']) && is_array($_POST['event_audience'])) {
        $audience_array = array_map('sanitize_text_field', $_POST['event_audience']);
        if (in_array('all', $audience_array)) {
            $audience = 'all';
        } else {
            $audience = json_encode($audience_array);
        }
    }
    
    // If all day, adjust times
    if ($all_day) {
        $start_date = date('Y-m-d', strtotime($start_date)) . ' 00:00:00';
        $end_date = date('Y-m-d', strtotime($end_date)) . ' 23:59:59';
    }

    $data = array(
        'title' => sanitize_text_field($_POST['event_title']),
        'description' => isset($_POST['event_description']) ? sanitize_textarea_field($_POST['event_description']) : '',
        'start_date' => $start_date,
        'end_date' => $end_date,
        'all_day' => $all_day,
        'color' => isset($_POST['event_color']) ? sanitize_hex_color($_POST['event_color']) : '#3788d8',
        'location' => isset($_POST['event_location']) ? sanitize_text_field($_POST['event_location']) : '',
        'audience' => $audience,
        'event_type' => 'custom',
        'created_by' => get_current_user_id(),
        'status' => 'active'
    );

    // Insert event
    $result = $wpdb->insert(
        $calendar_events_table,
        $data,
        array('%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s')
    );

    if ($result === false) {
        wp_redirect(add_query_arg('error', 'db_error', wp_get_referer()));
        exit;
    }

    // Clear calendar cache
    nds_clear_calendar_cache();
    
    // Redirect with success message
    wp_redirect(add_query_arg('success', 'event_created', wp_get_referer()));
    exit;
}

/**
 * AJAX handler to fetch event details for modal (PUBLIC - for frontend)
 * Returns details for a specific event ID
 */
add_action('wp_ajax_nds_public_event_details', 'nds_get_public_event_details_ajax');
add_action('wp_ajax_nopriv_nds_public_event_details', 'nds_get_public_event_details_ajax');
function nds_get_public_event_details_ajax() {
    // Public endpoint - allow access without strict nonce for public calendar
    if (isset($_POST['nonce'])) {
        if (!wp_verify_nonce($_POST['nonce'], 'nds_public_calendar_nonce')) {
            wp_send_json_error('Invalid nonce');
            return;
        }
    }

    $event_id = isset($_POST['event_id']) ? sanitize_text_field($_POST['event_id']) : '';
    if (empty($event_id)) {
        wp_send_json_error('Missing event ID');
        return;
    }

    global $wpdb;
    $details = array();

    // Parse event ID to determine type (e.g., 'event_123', 'schedule_456_2023-10-01', etc.)
    if (strpos($event_id, 'event_') === 0) {
        // Custom calendar event
        $id = str_replace('event_', '', $event_id);
        $calendar_events_table = $wpdb->prefix . 'nds_calendar_events';
        $event = $wpdb->get_row($wpdb->prepare("
            SELECT id, title, description, start_date, end_date, all_day, color, location, event_type, audience
            FROM {$calendar_events_table}
            WHERE id = %d AND status = 'active'
        ", intval($id)), ARRAY_A);

        if ($event) {
            $details = array(
                'type' => 'custom_event',
                'title' => $event['title'],
                'description' => $event['description'],
                'start' => $event['start_date'],
                'end' => $event['end_date'] ?: $event['start_date'],
                'all_day' => (bool) $event['all_day'],
                'location' => $event['location'],
                'color' => $event['color'] ?: '#3788d8',
                'event_type' => $event['event_type']
            );
        }
    } elseif (strpos($event_id, 'schedule_') === 0) {
        // Schedule event
        $parts = explode('_', $event_id);
        if (count($parts) >= 3) {
            $schedule_id = intval($parts[1]);
            $date = $parts[2];
            $schedules_table = $wpdb->prefix . 'nds_course_schedules';
            $courses_table = $wpdb->prefix . 'nds_courses';
            $staff_table = $wpdb->prefix . 'nds_staff';

            $schedule = $wpdb->get_row($wpdb->prepare("
                SELECT s.*, c.name as course_name, c.code as course_code, st.first_name, st.last_name
                FROM {$schedules_table} s
                LEFT JOIN {$courses_table} c ON s.course_id = c.id
                LEFT JOIN {$staff_table} st ON s.lecturer_id = st.id
                WHERE s.id = %d AND s.is_active = 1
            ", $schedule_id), ARRAY_A);

            if ($schedule) {
                $details = array(
                    'type' => 'schedule',
                    'title' => $schedule['course_name'] . ($schedule['course_code'] ? ' (' . $schedule['course_code'] . ')' : ''),
                    'start' => $date . ' ' . $schedule['start_time'],
                    'end' => $date . ' ' . $schedule['end_time'],
                    'all_day' => false,
                    'location' => $schedule['location'],
                    'lecturer' => trim(($schedule['first_name'] ?? '') . ' ' . ($schedule['last_name'] ?? '')),
                    'session_type' => $schedule['session_type']
                );
            }
        }
    } elseif (strpos($event_id, 'program_') === 0) {
        // Program event
        $id = str_replace('program_', '', $event_id);
        $programs_table = $wpdb->prefix . 'nds_programs';
        $program = $wpdb->get_row($wpdb->prepare("
            SELECT id, name, description, created_at, status
            FROM {$programs_table}
            WHERE id = %d AND status = 'active'
        ", intval($id)), ARRAY_A);

        if ($program) {
            $details = array(
                'type' => 'program',
                'title' => 'Program: ' . $program['name'],
                'description' => $program['description'],
                'start' => $program['created_at'],
                'all_day' => true,
                'color' => '#10b981'
            );
        }
    }

    if (empty($details)) {
        wp_send_json_error('Event not found');
    } else {
        wp_send_json_success($details);
    }
}

/**
 * Enqueue custom styles for student portal to adjust layout after disabling navbar
 */
function nds_enqueue_student_portal_styles() {
    // Only enqueue on the student portal page (adjust condition as needed, e.g., based on page slug or query var)
    if (is_page('student-portal') || (isset($_GET['page']) && $_GET['page'] === 'student-portal')) {
        $custom_css = "
            /* Remove space left by disabled navbar (adjust height if needed) */
            body, .nds-calendar-container, .fc {
                margin-top: 0 !important;
                padding-top: 0 !important;
            }
            
            /* Move calendar header to the top */
            .fc-header-toolbar {
                position: sticky;
                top: 0;
                z-index: 1000;
                background: #fff;
                border-bottom: 1px solid #ddd;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            
            /* Adjust main content to account for sticky header */
            .fc-view-container {
                margin-top: 10px;
            }
        ";
        
        wp_add_inline_style('wp-block-library', $custom_css); // Attach to a core style or use your own handle if available
    }
}
add_action('wp_enqueue_scripts', 'nds_enqueue_student_portal_styles');



