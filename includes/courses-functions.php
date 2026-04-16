<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

global $wpdb;
$courses_table = $wpdb->prefix . "nds_courses"; // Updated table name

// ✅ Handle the repetitive logic for fetching staff data
function nds_handle_course_data($request_type = 'POST') {
    $request_data = ($request_type === 'POST') ? $_POST : $_GET;

    return [
        'program_id'     => isset($request_data['program_id']) ? intval($request_data['program_id']) : 0,
        'name'           => isset($request_data['name']) ? sanitize_text_field($request_data['name']) : (isset($request_data['course_name']) ? sanitize_text_field($request_data['course_name']) : ''),
        'nqf_level'      => isset($request_data['nqf_level']) ? intval($request_data['nqf_level']) : 0,
        'description'    => isset($request_data['description']) ? sanitize_textarea_field($request_data['description']) : '',
        // 'duration' removed - column doesn't exist in database (use duration_weeks if needed)
        'credits'        => isset($request_data['credits']) ? intval($request_data['credits']) : 0,
        'price'          => isset($request_data['price']) ? floatval($request_data['price']) : 0.0,
        'currency'       => "ZAR",  // Assuming currency is ZAR for now
        'start_date'     => isset($request_data['start_date']) && !empty($request_data['start_date']) ? sanitize_text_field($request_data['start_date']) : null,
        'end_date'       => isset($request_data['end_date']) && !empty($request_data['end_date']) ? sanitize_text_field($request_data['end_date']) : null,
        'status'         => isset($request_data['status']) ? sanitize_text_field($request_data['status']) : 'active',
        // 'max_students' removed - column doesn't exist in database
        // 'accreditation_body' removed - moved to M2M table (nds_course_accreditations)
    ];
}

// ✅ Check if course exists
function nds_course_exists($name, $program_id)
{
    global $wpdb;
    $courses_table = $wpdb->prefix . "nds_courses"; // Ensure correct table name

    $query = $wpdb->prepare("SELECT COUNT(*) FROM $courses_table WHERE name = %s AND program_id = %d", $name, $program_id);
    return ($wpdb->get_var($query) > 0);
}

/**
 * Check if a schedule overlaps with existing schedules in the same program
 * @param int $course_id Course ID (0 for new course)
 * @param int $program_id Program ID
 * @param string $days Comma-separated days (e.g., "Monday, Wednesday")
 * @param string $start_time Start time (HH:MM:SS format)
 * @param string $end_time End time (HH:MM:SS format)
 * @param int $exclude_schedule_id Schedule ID to exclude from check (for updates)
 * @return array Array with 'has_overlap' (bool) and 'conflicting_schedules' (array)
 */
function nds_check_schedule_overlap($course_id, $program_id, $days, $start_time, $end_time, $exclude_schedule_id = 0) {
    global $wpdb;
    
    if (empty($days) || empty($start_time) || empty($program_id)) {
        return ['has_overlap' => false, 'conflicting_schedules' => []];
    }
    
    // Normalize days - handle comma-separated string
    $days_array = array_map('trim', explode(',', $days));
    $days_normalized = array_map(function($day) {
        $day_lower = strtolower(trim($day));
        $day_map = [
            'monday' => 'Monday', 'tuesday' => 'Tuesday', 'wednesday' => 'Wednesday',
            'thursday' => 'Thursday', 'friday' => 'Friday', 'saturday' => 'Saturday', 'sunday' => 'Sunday',
            'mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday',
            'thu' => 'Thursday', 'fri' => 'Friday', 'sat' => 'Saturday', 'sun' => 'Sunday'
        ];
        return $day_map[$day_lower] ?? ucfirst($day_lower);
    }, $days_array);
    
    // Get all courses in the same program (excluding current course if updating)
    $courses_in_program = $wpdb->get_col($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}nds_courses 
         WHERE program_id = %d AND status = 'active'",
        $program_id
    ));
    
    if (empty($courses_in_program)) {
        return ['has_overlap' => false, 'conflicting_schedules' => []];
    }
    
    // Remove current course from check (when updating existing course)
    if ($course_id > 0) {
        $courses_in_program = array_filter($courses_in_program, function($id) use ($course_id) {
            return $id != $course_id;
        });
    }
    
    if (empty($courses_in_program)) {
        return ['has_overlap' => false, 'conflicting_schedules' => []];
    }
    
    // Get all schedules for courses in this program
    $placeholders = implode(',', array_fill(0, count($courses_in_program), '%d'));
    $query = $wpdb->prepare(
        "SELECT cs.*, c.name as course_name, c.code as course_code
         FROM {$wpdb->prefix}nds_course_schedules cs
         INNER JOIN {$wpdb->prefix}nds_courses c ON cs.course_id = c.id
         WHERE cs.course_id IN ($placeholders) 
         AND cs.is_active = 1",
        $courses_in_program
    );
    
    if ($exclude_schedule_id > 0) {
        $query .= $wpdb->prepare(" AND cs.id != %d", $exclude_schedule_id);
    }
    
    $existing_schedules = $wpdb->get_results($query, ARRAY_A);
    
    $conflicting_schedules = [];
    $new_start_ts = strtotime($start_time);
    $new_end_ts = strtotime($end_time);
    
    // Check each existing schedule for overlap
    foreach ($existing_schedules as $existing) {
        $existing_days = array_map('trim', explode(',', $existing['days'] ?? ''));
        $existing_days_normalized = array_map(function($day) {
            $day_lower = strtolower(trim($day));
            $day_map = [
                'monday' => 'Monday', 'tuesday' => 'Tuesday', 'wednesday' => 'Wednesday',
                'thursday' => 'Thursday', 'friday' => 'Friday', 'saturday' => 'Saturday', 'sunday' => 'Sunday',
                'mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday',
                'thu' => 'Thursday', 'fri' => 'Friday', 'sat' => 'Saturday', 'sun' => 'Sunday'
            ];
            return $day_map[$day_lower] ?? ucfirst($day_lower);
        }, $existing_days);
        
        // Check if days overlap
        $days_overlap = !empty(array_intersect($days_normalized, $existing_days_normalized));
        
        if ($days_overlap) {
            // Check if times overlap
            $existing_start_ts = strtotime($existing['start_time']);
            $existing_end_ts = strtotime($existing['end_time'] ?? $existing['start_time']);
            
            // Two time slots overlap if: (new_start < existing_end) AND (existing_start < new_end)
            if (($new_start_ts < $existing_end_ts) && ($existing_start_ts < $new_end_ts)) {
                $conflicting_schedules[] = [
                    'course_name' => $existing['course_name'],
                    'course_code' => $existing['course_code'],
                    'days' => $existing['days'],
                    'start_time' => $existing['start_time'],
                    'end_time' => $existing['end_time'],
                    'location' => $existing['location'] ?? ''
                ];
            }
        }
    }
    
    return [
        'has_overlap' => !empty($conflicting_schedules),
        'conflicting_schedules' => $conflicting_schedules
    ];
}

/**
 * Generate a unique course code
 * Format: First 3 letters of name + program_id + counter if needed
 */
function nds_generate_course_code($name, $program_id, $wpdb = null, $courses_table = null)
{
    if (!$wpdb) {
        global $wpdb;
    }
    if (!$courses_table) {
        $courses_table = $wpdb->prefix . 'nds_courses';
    }

    // Get first 3 uppercase letters from name (remove spaces, special chars)
    $name_clean = preg_replace('/[^a-zA-Z0-9]/', '', $name);
    $prefix = strtoupper(substr($name_clean, 0, 3));
    if (empty($prefix)) {
        $prefix = 'CRS'; // Default prefix if name has no letters
    }

    // Base code: PREFIX-PROGRAMID
    $base_code = $prefix . '-' . $program_id;
    $code = $base_code;
    $counter = 1;

    // Check if code exists, if so, append a number
    while ($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $courses_table WHERE code = %s", $code)) > 0) {
        $code = $base_code . '-' . $counter;
        $counter++;
        
        // Safety limit to prevent infinite loop
        if ($counter > 999) {
            $code = $prefix . '-' . $program_id . '-' . time();
            break;
        }
    }

    return $code;
}

// Handle form submission for adding a new education course
add_action('admin_post_nds_add_course', 'nds_add_course');
function nds_add_course()
{
 
    // Check nonce and permissions
    if (!isset($_POST['nds_add_course_nonce']) || !wp_verify_nonce($_POST['nds_add_course_nonce'], 'nds_add_course_nonce')) {
        wp_die('Security check Add Course failed');
    }
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    // Get the form values
    global $wpdb;

    // Ensure color columns exist in both tables
    $programs_table = $wpdb->prefix . 'nds_programs';
    $courses_table = $wpdb->prefix . 'nds_courses';
    
    $programs_columns = $wpdb->get_col("DESCRIBE $programs_table");
    $courses_columns = $wpdb->get_col("DESCRIBE $courses_table");
    
    if (!in_array('color', $programs_columns)) {
        $wpdb->query("ALTER TABLE $programs_table ADD COLUMN color VARCHAR(7) NULL AFTER category_id");
    }
    if (!in_array('color', $courses_columns)) {
        $wpdb->query("ALTER TABLE $courses_table ADD COLUMN color VARCHAR(7) NULL AFTER end_date");
    }

    $data = nds_handle_course_data('POST', true); // Generate code automatically
    $data['code'] = isset($_POST['code']) ? sanitize_text_field($_POST['code']) : (isset($_POST['course_code']) ? sanitize_text_field($_POST['course_code']) : '');
    $data['duration'] = isset($_POST['duration']) ? intval($_POST['duration']) : (isset($_POST['duration_weeks']) ? intval($_POST['duration_weeks']) : 0);
    
    // Validate required fields
    if (empty($data['name']) || empty($data['program_id'])) {
        wp_die('Course name and program are required fields.');
    }

    // Verify at least one program exists and the selected program is valid
    $courses_programs_table = $wpdb->prefix . 'nds_programs';
    $prog_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM $courses_programs_table");
    if ($prog_count === 0) {
        wp_redirect(add_query_arg('error', 'no_program_exists', admin_url('admin.php?page=nds-programs')));
        exit;
    }
    $prog_valid = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $courses_programs_table WHERE id = %d", intval($data['program_id'])));
    if ($prog_valid === 0) {
        wp_redirect(add_query_arg('error', 'invalid_program', wp_get_referer()));
        exit;
    }

    // Ensure code is generated
    if (empty($data['code'])) {
        $data['code'] = nds_generate_course_code($data['name'], $data['program_id'], $wpdb, $wpdb->prefix . 'nds_courses');
    }
    
    // Create a WordPress post for the course first
    $post_id = wp_insert_post([
        'post_title'   => $data['name'],
        'post_content' => $data['description'],
        'post_status'  => 'publish',
        'post_type'    => 'post',
    ]);

    // Check if post creation was successful
    if (!$post_id || is_wp_error($post_id)) {
        wp_die('Failed to create course post. Please try again.');
    }

    // Get program color palette and generate course color
    require_once plugin_dir_path(__FILE__) . 'color-palette-generator.php';
    $color_generator = new NDS_ColorPaletteGenerator();
    
    $program = $wpdb->get_row($wpdb->prepare("SELECT color, color_palette FROM {$wpdb->prefix}nds_programs WHERE id = %d", $data['program_id']));
    $course_index = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}nds_courses WHERE program_id = %d", $data['program_id'])));
    
    // Get course color from program's palette
    if ($program && $program->color_palette) {
        $course_color = $color_generator->get_course_color_from_palette($program->color_palette, $course_index);
    } elseif ($program && $program->color) {
        // Fallback: generate course color from program color if no palette exists
        $course_palette = $color_generator->generate_course_palette($program->color, 20);
        $palette_index = $course_index % count($course_palette);
        $course_color = $course_palette[$palette_index]['hex'];
    } else {
        // Default fallback
        $course_color = '#607D8B';
    }

    // Prepare data for insert - only include fields that exist in the database schema
    $insert_data = [
        'program_id' => $data['program_id'],
        'code' => $data['code'],
        'name' => $data['name'],
        'description' => $data['description'],
        'nqf_level' => $data['nqf_level'] > 0 ? $data['nqf_level'] : null,
        'credits' => $data['credits'] > 0 ? $data['credits'] : 0,
        'price' => $data['price'],
        'currency' => $data['currency'],
        'start_date' => $data['start_date'],
        'end_date' => $data['end_date'],
        'color' => $course_color,
        'status' => $data['status']
    ];
    
    $format_array = ['%d', '%s', '%s', '%s', '%d', '%d', '%f', '%s', '%s', '%s', '%s', '%s'];
    
    // Add optional fields if they exist
    if (!empty($data['duration'])) {
        // Convert duration to weeks if needed, or store as-is
        $insert_data['duration_weeks'] = intval($data['duration']);
        $format_array[] = '%d';
    }
    
    // Insert course data into your custom table
    $result = $wpdb->insert(
        $wpdb->prefix . 'nds_courses',
        $insert_data,
        $format_array
    );
    
    // Get the last inserted ID
    $course_id = $wpdb->insert_id;

    // Check if insertion was successful
    if ($result === false) {
        error_log('Failed to insert course into database: ' . $wpdb->last_error);
        // Clean up the post if course insertion failed
        wp_delete_post($post_id, true);
        wp_die('Failed to create course. Please try again.');
    }

    // Handle course schedules (multiple schedules per course allowed)
    if (isset($_POST['schedule']) && is_array($_POST['schedule'])) {
        $schedules_table = $wpdb->prefix . 'nds_course_schedules';
        $overlap_errors = [];
        
        foreach ($_POST['schedule'] as $index => $schedule_data) {
            // Handle days - can be array (from checkboxes) or string (from hidden input)
            // Convert full day names to abbreviations for database SET column
            $day_name_to_abbr = array(
                'monday' => 'mon', 'tuesday' => 'tue', 'wednesday' => 'wed',
                'thursday' => 'thu', 'friday' => 'fri', 'saturday' => 'sat', 'sunday' => 'sun'
            );
            
            $days = '';
            if (isset($schedule_data['days'])) {
                if (is_array($schedule_data['days'])) {
                    // From checkboxes - convert full names to abbreviations
                    $day_abbrs = array();
                    foreach ($schedule_data['days'] as $day) {
                        $day_lower = strtolower(trim($day));
                        if (isset($day_name_to_abbr[$day_lower])) {
                            $day_abbrs[] = $day_name_to_abbr[$day_lower];
                        } elseif (in_array($day_lower, array_values($day_name_to_abbr))) {
                            // Already an abbreviation
                            $day_abbrs[] = $day_lower;
                        }
                    }
                    $days = implode(',', $day_abbrs); // SET columns use comma without spaces
                } else {
                    // From hidden input (comma-separated string) - convert full names to abbreviations
                    $day_parts = array_map('trim', explode(',', $schedule_data['days']));
                    $day_abbrs = array();
                    foreach ($day_parts as $day) {
                        $day_lower = strtolower(trim($day));
                        if (isset($day_name_to_abbr[$day_lower])) {
                            $day_abbrs[] = $day_name_to_abbr[$day_lower];
                        } elseif (in_array($day_lower, array_values($day_name_to_abbr))) {
                            // Already an abbreviation
                            $day_abbrs[] = $day_lower;
                        }
                    }
                    $days = implode(',', $day_abbrs); // SET columns use comma without spaces
                }
            }
            
            // Skip empty schedules
            if (empty($days) || empty($schedule_data['start_time'])) {
                continue;
            }
            
            $start_time = sanitize_text_field($schedule_data['start_time']);
            $end_time = !empty($schedule_data['end_time']) ? sanitize_text_field($schedule_data['end_time']) : null;
            
            // Default end time if not provided (1 hour after start)
            if (empty($end_time)) {
                $start_ts = strtotime($start_time);
                $end_time = date('H:i:s', $start_ts + 3600); // Add 1 hour
            }
            
            // Check for overlaps with other courses in the same program
            $overlap_check = nds_check_schedule_overlap(
                $course_id,
                $data['program_id'],
                $days,
                $start_time,
                $end_time
            );
            
            if ($overlap_check['has_overlap']) {
                $conflicts = [];
                foreach ($overlap_check['conflicting_schedules'] as $conflict) {
                    $conflicts[] = sprintf(
                        '%s (%s) on %s from %s to %s',
                        $conflict['course_name'],
                        $conflict['course_code'],
                        $conflict['days'],
                        date('H:i', strtotime($conflict['start_time'])),
                        date('H:i', strtotime($conflict['end_time']))
                    );
                }
                $overlap_errors[] = sprintf(
                    'Schedule %d (%s, %s-%s) overlaps with: %s',
                    $index + 1,
                    $days,
                    date('H:i', strtotime($start_time)),
                    date('H:i', strtotime($end_time)),
                    implode('; ', $conflicts)
                );
                continue; // Skip this schedule
            }
            
            // Prepare schedule data
            $schedule_insert = [
                'course_id' => $course_id,
                'lecturer_id' => !empty($schedule_data['lecturer_id']) ? intval($schedule_data['lecturer_id']) : null,
                'days' => $days,
                'start_time' => $start_time,
                'end_time' => $end_time,
                'day_hours' => !empty($schedule_data['day_hours']) ? floatval($schedule_data['day_hours']) : null,
                'session_type' => !empty($schedule_data['session_type']) ? sanitize_text_field($schedule_data['session_type']) : null,
                'location' => !empty($schedule_data['location']) ? sanitize_text_field($schedule_data['location']) : null,
                'is_active' => isset($schedule_data['is_active']) ? intval($schedule_data['is_active']) : 1
            ];
            
            // Calculate day_hours if not provided
            if (empty($schedule_insert['day_hours']) && $schedule_insert['start_time'] && $schedule_insert['end_time']) {
                $start_ts = strtotime($schedule_insert['start_time']);
                $end_ts = strtotime($schedule_insert['end_time']);
                $schedule_insert['day_hours'] = ($end_ts - $start_ts) / 3600;
            }
            
            $wpdb->insert(
                $schedules_table,
                $schedule_insert,
                ['%d', '%d', '%s', '%s', '%s', '%f', '%s', '%s', '%d']
            );
        }
        
        // If there were overlap errors, show them but don't fail the course creation
        if (!empty($overlap_errors)) {
            $error_message = 'Some schedules were not added due to overlaps with other courses in the program: ' . implode(' | ', $overlap_errors);
            error_log('Course schedule overlap: ' . $error_message);
            // Add to redirect URL as notice
            $redirect_url = add_query_arg('schedule_warnings', urlencode($error_message), $redirect_url);
        }
    }

    // Clear calendar cache so schedule changes appear immediately
    if (function_exists('nds_clear_calendar_cache')) {
        nds_clear_calendar_cache();
    }
    
    // Redirect back to courses page with success message
    $redirect_url = admin_url('admin.php?page=nds-courses');
    if (!empty($data['program_id'])) {
        $redirect_url = add_query_arg('program_id', $data['program_id'], $redirect_url);
    }
    $redirect_url = add_query_arg('course_created', 'success', $redirect_url);
    
    wp_redirect($redirect_url);
    exit;
}


// ✅ Get course by program ID
function nds_get_course_by_programid($id)
{
    global $wpdb;
    $courses_table = $wpdb->prefix . "nds_courses"; // Ensure correct table name

    return $wpdb->get_results(
        $wpdb->prepare("SELECT * FROM $courses_table WHERE program_id = %d", intval($id)),
        ARRAY_A
    );
}

// ✅ Get course by ID
function nds_get_course_by_id($id)
{
    global $wpdb;
    $courses_table = $wpdb->prefix . "nds_courses"; // Ensure correct table name

    return $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM $courses_table WHERE id = %d", intval($id)),
        ARRAY_A
    );
}

// ✅ Update course

add_action('admin_post_nds_update_course', 'nds_handle_update_course');
function nds_handle_update_course()
{
    global $wpdb; // Declare global $wpdb

    // Check nonce and permissions
    if (!isset($_POST['nds_courseupdate_nonce']) || !wp_verify_nonce($_POST['nds_courseupdate_nonce'], 'nds_edit_course')) {
        wp_die('Security check Updated Course f failed');
    }
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    if (isset($_POST['submit_update_course'])) {
        // Get sanitized data using the function
        $data = nds_handle_course_data('POST');
        $course_id = intval($_POST['course_id']); // Get course ID from POST data

        // Prepare the WHERE clause for updating the specific course
        $where = ['id' => intval($_POST['course_id'])]; // Assuming 'course_id' is passed in the form

        // Ensure start_date and end_date columns exist (add if missing)
        $table_name = $wpdb->prefix . 'nds_courses';
        $columns = $wpdb->get_col("DESCRIBE $table_name");
        if (!in_array('start_date', $columns)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN start_date DATE NULL AFTER currency");
        }
        if (!in_array('end_date', $columns)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN end_date DATE NULL AFTER start_date");
        }

        // Filter out null values for dates (only include if they have values)
        $update_data = $data;
        if (is_null($update_data['start_date']) || empty($update_data['start_date'])) {
            unset($update_data['start_date']);
        }
        if (is_null($update_data['end_date']) || empty($update_data['end_date'])) {
            unset($update_data['end_date']);
        }

        // Build format specifiers dynamically based on what's in update_data
        $format_specifiers = [];
        foreach ($update_data as $key => $value) {
            if ($key === 'program_id' || $key === 'nqf_level' || $key === 'credits') {
                $format_specifiers[] = '%d';
            } elseif ($key === 'price') {
                $format_specifiers[] = '%f';
            } else {
                $format_specifiers[] = '%s';
            }
        }

        // Update the course data
        // Format specifiers: program_id(%d), name(%s), nqf_level(%d), description(%s), credits(%d), price(%f), currency(%s), start_date(%s), end_date(%s), status(%s)
        $updated = $wpdb->update(
            $table_name,
            $update_data,    // Data to update
            $where,          // Where condition (update the specific course)
            $format_specifiers, // Format specifiers for data
            ['%d'] // Format specifier for the WHERE condition
        );

        if ($updated !== false) {
            // Handle course schedules update (delete old, insert new)
            if (isset($_POST['schedule']) && is_array($_POST['schedule'])) {
                $schedules_table = $wpdb->prefix . 'nds_course_schedules';
                $overlap_errors = [];
                
                // Get existing schedule IDs before deletion (for overlap checking)
                $existing_schedule_ids = $wpdb->get_col($wpdb->prepare(
                    "SELECT id FROM {$schedules_table} WHERE course_id = %d",
                    $course_id
                ));
                
                // Delete existing schedules for this course
                $wpdb->delete(
                    $schedules_table,
                    ['course_id' => $course_id],
                    ['%d']
                );
                
                // Get program_id for overlap checking
                $program_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT program_id FROM {$wpdb->prefix}nds_courses WHERE id = %d",
                    $course_id
                ));
                
                // Insert new schedules
                foreach ($_POST['schedule'] as $index => $schedule_data) {
                    // Handle days - can be array (from checkboxes) or string (from hidden input)
                    // Convert full day names to abbreviations for database SET column
                    $day_name_to_abbr = array(
                        'monday' => 'mon', 'tuesday' => 'tue', 'wednesday' => 'wed',
                        'thursday' => 'thu', 'friday' => 'fri', 'saturday' => 'sat', 'sunday' => 'sun'
                    );
                    
                    $days = '';
                    if (isset($schedule_data['days'])) {
                        if (is_array($schedule_data['days'])) {
                            // From checkboxes - convert full names to abbreviations
                            $day_abbrs = array();
                            foreach ($schedule_data['days'] as $day) {
                                $day_lower = strtolower(trim($day));
                                if (isset($day_name_to_abbr[$day_lower])) {
                                    $day_abbrs[] = $day_name_to_abbr[$day_lower];
                                } elseif (in_array($day_lower, array_values($day_name_to_abbr))) {
                                    // Already an abbreviation
                                    $day_abbrs[] = $day_lower;
                                }
                            }
                            $days = implode(',', $day_abbrs); // SET columns use comma without spaces
                        } else {
                            // From hidden input (comma-separated string) - convert full names to abbreviations
                            $day_parts = array_map('trim', explode(',', $schedule_data['days']));
                            $day_abbrs = array();
                            foreach ($day_parts as $day) {
                                $day_lower = strtolower(trim($day));
                                if (isset($day_name_to_abbr[$day_lower])) {
                                    $day_abbrs[] = $day_name_to_abbr[$day_lower];
                                } elseif (in_array($day_lower, array_values($day_name_to_abbr))) {
                                    // Already an abbreviation
                                    $day_abbrs[] = $day_lower;
                                }
                            }
                            $days = implode(',', $day_abbrs); // SET columns use comma without spaces
                        }
                    }
                    
                    // Skip empty schedules
                    if (empty($days) || empty($schedule_data['start_time'])) {
                        continue;
                    }
                    
                    $start_time = sanitize_text_field($schedule_data['start_time']);
                    $end_time = !empty($schedule_data['end_time']) ? sanitize_text_field($schedule_data['end_time']) : null;
                    
                    // Default end time if not provided (1 hour after start)
                    if (empty($end_time)) {
                        $start_ts = strtotime($start_time);
                        $end_time = date('H:i:s', $start_ts + 3600); // Add 1 hour
                    }
                    
                    // Check for overlaps with other courses in the same program
                    if ($program_id) {
                        $overlap_check = nds_check_schedule_overlap(
                            $course_id,
                            $program_id,
                            $days,
                            $start_time,
                            $end_time
                        );
                        
                        if ($overlap_check['has_overlap']) {
                            $conflicts = [];
                            foreach ($overlap_check['conflicting_schedules'] as $conflict) {
                                $conflicts[] = sprintf(
                                    '%s (%s) on %s from %s to %s',
                                    $conflict['course_name'],
                                    $conflict['course_code'],
                                    $conflict['days'],
                                    date('H:i', strtotime($conflict['start_time'])),
                                    date('H:i', strtotime($conflict['end_time']))
                                );
                            }
                            $overlap_errors[] = sprintf(
                                'Schedule %d (%s, %s-%s) overlaps with: %s',
                                $index + 1,
                                $days,
                                date('H:i', strtotime($start_time)),
                                date('H:i', strtotime($end_time)),
                                implode('; ', $conflicts)
                            );
                            continue; // Skip this schedule
                        }
                    }
                    
                    // Prepare schedule data
                    $schedule_insert = [
                        'course_id' => $course_id,
                        'lecturer_id' => !empty($schedule_data['lecturer_id']) ? intval($schedule_data['lecturer_id']) : null,
                        'days' => $days,
                        'start_time' => $start_time,
                        'end_time' => $end_time,
                        'day_hours' => !empty($schedule_data['day_hours']) ? floatval($schedule_data['day_hours']) : null,
                        'session_type' => !empty($schedule_data['session_type']) ? sanitize_text_field($schedule_data['session_type']) : null,
                        'location' => !empty($schedule_data['location']) ? sanitize_text_field($schedule_data['location']) : null,
                        'is_active' => isset($schedule_data['is_active']) ? intval($schedule_data['is_active']) : 1
                    ];
                    
                    // Calculate day_hours if not provided
                    if (empty($schedule_insert['day_hours']) && $schedule_insert['start_time'] && $schedule_insert['end_time']) {
                        $start_ts = strtotime($schedule_insert['start_time']);
                        $end_ts = strtotime($schedule_insert['end_time']);
                        $schedule_insert['day_hours'] = ($end_ts - $start_ts) / 3600;
                    }
                    
                    $wpdb->insert(
                        $schedules_table,
                        $schedule_insert,
                        ['%d', '%d', '%s', '%s', '%s', '%f', '%s', '%s', '%d']
                    );
                }
                
                // If there were overlap errors, add notice
                if (!empty($overlap_errors)) {
                    $error_message = 'Some schedules were not updated due to overlaps: ' . implode(' | ', $overlap_errors);
                    error_log('Course schedule overlap: ' . $error_message);
                    // Store in transient for display
                    set_transient('nds_schedule_overlap_warning_' . $course_id, $error_message, 30);
                }
            }
            
            // Update the WordPress post associated with this course
            if (!empty($data['name'])) {
                $post = get_page_by_title($data['name'], OBJECT, 'post');
                if ($post) {
                    // Update the post title to match the new course name
                    wp_update_post(array(
                        'ID' => $post->ID,
                        'post_title' => $data['name'],
                    ));
                }
            }

            // Clear calendar cache so schedule changes appear immediately
            if (function_exists('nds_clear_calendar_cache')) {
                nds_clear_calendar_cache();
            }
            
            // Redirect back to the edit page with success message
            wp_redirect(admin_url('admin.php?page=nds-edit-course&edit_course=' . $course_id . '&success=updated'));
            exit;
        } else {
            // If update fails, show an error
            wp_die('Error updating the education course!');
        }
    } else {
        wp_die('Invalid data received!');
    }
}


// ✅ Delete course - Action handler for admin-post.php
add_action('admin_post_nds_delete_course', 'nds_handle_delete_course');
function nds_handle_delete_course()
{
    $redirect_url = admin_url('admin.php?page=nds-courses');
    if (isset($_POST['program_id']) && !empty($_POST['program_id'])) {
        $redirect_url = add_query_arg('program_id', intval($_POST['program_id']), $redirect_url);
    }

    // Check nonce and permissions
    if (!isset($_POST['nds_delete_course_nonce']) || !wp_verify_nonce($_POST['nds_delete_course_nonce'], 'nds_delete_course_nonce')) {
        wp_redirect(add_query_arg('error', 'security_check_failed', $redirect_url));
        exit;
    }
    
    if (!current_user_can('manage_options')) {
        wp_redirect(add_query_arg('error', 'unauthorized', $redirect_url));
        exit;
    }

    $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
    
    if ($course_id <= 0) {
        wp_redirect(add_query_arg('error', 'invalid_id', $redirect_url));
        exit;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'nds_courses';
    
    // Check if course exists
    $course = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $course_id));
    
    if (!$course) {
        wp_redirect(add_query_arg('error', 'not_found', $redirect_url));
        exit;
    }

    // Delete related records first (due to foreign key constraints)
    // Delete course lecturers
    $wpdb->delete(
        $wpdb->prefix . 'nds_course_lecturers',
        ['course_id' => $course_id],
        ['%d']
    );
    
    // Delete course schedules
    $wpdb->delete(
        $wpdb->prefix . 'nds_course_schedules',
        ['course_id' => $course_id],
        ['%d']
    );
    
    // Delete course prerequisites
    $wpdb->delete(
        $wpdb->prefix . 'nds_course_prerequisites',
        ['course_id' => $course_id],
        ['%d']
    );
    
    // Delete course accreditations
    $wpdb->delete(
        $wpdb->prefix . 'nds_course_accreditations',
        ['course_id' => $course_id],
        ['%d']
    );
    
    // Delete student enrollments (if table exists)
    $enrollments_table = $wpdb->prefix . 'nds_student_enrollments';
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$enrollments_table'");
    if ($table_exists) {
        $wpdb->delete(
            $enrollments_table,
            ['course_id' => $course_id],
            ['%d']
        );
    }

    // Now delete the course
    $deleted = $wpdb->delete($table_name, ['id' => $course_id], ['%d']);
    
    if ($deleted === false) {
        error_log('NDS Course Deletion Failed: ' . $wpdb->last_error);
        error_log('Course ID: ' . $course_id);
        wp_redirect(add_query_arg('error', 'delete_failed', $redirect_url));
        exit;
    }

    // Redirect back to courses page with success message
    $redirect_url = add_query_arg('course_deleted', 'success', $redirect_url);
    
    wp_redirect($redirect_url);
    exit;
}

// ✅ Delete course - Legacy function for backward compatibility
function nds_delete_course($course)
{
    $redirect_url = admin_url('admin.php?page=nds-courses');
    if (isset($course['edit_program']) && !empty($course['edit_program'])) {
        $redirect_url = add_query_arg('program_id', intval($course['edit_program']), $redirect_url);
    }

    // Handle old format where course data is passed as array
    if (isset($course['delete_course'])) {
        $course_id = intval($course['delete_course']);
    } elseif (isset($course['course_id'])) {
        $course_id = intval($course['course_id']);
    } else {
        wp_redirect(add_query_arg('error', 'invalid_id', $redirect_url));
        exit;
    }

    if ($course_id <= 0) {
        wp_redirect(add_query_arg('error', 'invalid_id', $redirect_url));
        exit;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'nds_courses';
    
    // Check if course exists
    $course_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $course_id));
    
    if (!$course_data) {
        wp_redirect(add_query_arg('error', 'not_found', $redirect_url));
        exit;
    }

    // Delete related records first (due to foreign key constraints)
    // Delete course lecturers
    $wpdb->delete(
        $wpdb->prefix . 'nds_course_lecturers',
        ['course_id' => $course_id],
        ['%d']
    );
    
    // Delete course schedules
    $wpdb->delete(
        $wpdb->prefix . 'nds_course_schedules',
        ['course_id' => $course_id],
        ['%d']
    );
    
    // Delete course prerequisites
    $wpdb->delete(
        $wpdb->prefix . 'nds_course_prerequisites',
        ['course_id' => $course_id],
        ['%d']
    );
    
    // Delete course accreditations
    $wpdb->delete(
        $wpdb->prefix . 'nds_course_accreditations',
        ['course_id' => $course_id],
        ['%d']
    );
    
    // Delete student enrollments (if table exists)
    $enrollments_table = $wpdb->prefix . 'nds_student_enrollments';
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$enrollments_table'");
    if ($table_exists) {
        $wpdb->delete(
            $enrollments_table,
            ['course_id' => $course_id],
            ['%d']
        );
    }

    // Now delete the course
    $deleted = $wpdb->delete($table_name, ['id' => $course_id], ['%d']);
    
    if ($deleted === false) {
        error_log('NDS Course Deletion Failed: ' . $wpdb->last_error);
        error_log('Course ID: ' . $course_id);
        wp_redirect(add_query_arg('error', 'delete_failed', $redirect_url));
        exit;
    }

    // Redirect back to courses page
    $redirect_url = add_query_arg('course_deleted', 'success', $redirect_url);
    
    wp_redirect($redirect_url);
    exit;
}

// Handle module creation via admin-post.php (must be in globally loaded file)
add_action('admin_post_nds_add_module', 'nds_handle_add_module');
function nds_handle_add_module()
{
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    if (!isset($_POST['nds_add_module_nonce']) || !wp_verify_nonce($_POST['nds_add_module_nonce'], 'nds_add_module_nonce')) {
        wp_die('Security check failed');
    }

    global $wpdb;
    $table_modules = $wpdb->prefix . 'nds_modules';

    $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
    $module_name = isset($_POST['module_name']) ? sanitize_text_field($_POST['module_name']) : '';
    $module_code = isset($_POST['module_code']) ? sanitize_text_field($_POST['module_code']) : '';
    $module_duration = isset($_POST['module_duration']) ? intval($_POST['module_duration']) : 0;
    $module_description = isset($_POST['module_description']) ? sanitize_textarea_field($_POST['module_description']) : '';
    $module_status = isset($_POST['module_status']) ? sanitize_text_field($_POST['module_status']) : 'active';

    $redirect_url = admin_url('admin.php?page=nds-courses');
    if (isset($_POST['program_id']) && intval($_POST['program_id']) > 0) {
        $redirect_url = add_query_arg('program_id', intval($_POST['program_id']), $redirect_url);
    }

    if (empty($module_name) || empty($module_duration) || empty($course_id)) {
        wp_redirect(add_query_arg('error', 'missing_fields', $redirect_url));
        exit;
    }

    // Keep the user in the same program view when possible.
    if (!isset($_POST['program_id']) || intval($_POST['program_id']) <= 0) {
        $program_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT program_id FROM {$wpdb->prefix}nds_courses WHERE id = %d",
            $course_id
        ));
        if ($program_id > 0) {
            $redirect_url = add_query_arg('program_id', $program_id, $redirect_url);
        }
    }

    // Support both legacy and university-schema module table variants.
    $module_columns = $wpdb->get_col("DESCRIBE {$table_modules}");
    if (empty($module_columns)) {
        wp_redirect(add_query_arg('error', 'create_failed', $redirect_url));
        exit;
    }

    $insert_data = array(
        'course_id' => $course_id,
        'name' => $module_name,
    );
    $insert_format = array('%d', '%s');

    // Module code column differs across schemas: code vs module_code.
    $code_column = in_array('code', $module_columns, true) ? 'code' : (in_array('module_code', $module_columns, true) ? 'module_code' : '');
    if (!empty($code_column)) {
        if (empty($module_code)) {
            $base = strtoupper(preg_replace('/[^A-Z0-9]/', '', substr($module_name, 0, 6)));
            if (empty($base)) {
                $base = 'MOD';
            }
            $module_code = $base . '-' . $course_id;
        }

        // Ensure uniqueness if the code column is unique.
        $candidate = $module_code;
        $counter = 1;
        while ((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table_modules} WHERE {$code_column} = %s", $candidate)) > 0) {
            $candidate = $module_code . '-' . $counter;
            $counter++;
            if ($counter > 1000) {
                $candidate = $module_code . '-' . time();
                break;
            }
        }
        $insert_data[$code_column] = $candidate;
        $insert_format[] = '%s';
    }

    // Duration column differs across schemas: duration_hours vs hours.
    if (in_array('duration_hours', $module_columns, true)) {
        $insert_data['duration_hours'] = $module_duration;
        $insert_format[] = '%d';
    } elseif (in_array('hours', $module_columns, true)) {
        $insert_data['hours'] = $module_duration;
        $insert_format[] = '%d';
    }

    if (in_array('description', $module_columns, true)) {
        $insert_data['description'] = $module_description;
        $insert_format[] = '%s';
    }
    if (in_array('status', $module_columns, true)) {
        $insert_data['status'] = $module_status;
        $insert_format[] = '%s';
    }
    if (in_array('type', $module_columns, true)) {
        $insert_data['type'] = 'theory';
        $insert_format[] = '%s';
    }
    if (in_array('created_at', $module_columns, true)) {
        $insert_data['created_at'] = current_time('mysql');
        $insert_format[] = '%s';
    }
    if (in_array('updated_at', $module_columns, true)) {
        $insert_data['updated_at'] = current_time('mysql');
        $insert_format[] = '%s';
    }

    $result = $wpdb->insert($table_modules, $insert_data, $insert_format);

    if ($result) {
        wp_redirect(add_query_arg('module_created', 'success', $redirect_url));
        exit;
    }

    error_log('NDS Module Creation Failed: ' . $wpdb->last_error);
    wp_redirect(add_query_arg('error', 'create_failed', $redirect_url));
    exit;
}


// ✅ Get program type name by ID (for displaying in courses list)
function nds_get_program_type_name($program_id)
{
    global $wpdb;
    $table_program_types = $wpdb->prefix . "nds_program_types";

    return $wpdb->get_var($wpdb->prepare("SELECT name FROM $table_program_types WHERE id = %d", intval($program_id)));
}

// ✅ Get all the courses 
function nds_get_courses()
{
    global $wpdb;
    $courses_table = $wpdb->prefix . "nds_courses";
    // FIX: Remove the unnecessary prepare() call
    return $wpdb->get_results("SELECT * FROM {$courses_table} ORDER BY name ASC", ARRAY_A);
}