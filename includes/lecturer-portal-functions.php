<?php
if (!defined('ABSPATH')) {
    exit;
}

function nds_staff_get_active_term_ids() {
    global $wpdb;

    $year_id = (int) $wpdb->get_var("SELECT id FROM {$wpdb->prefix}nds_academic_years WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
    $semester_id = 0;

    if ($year_id > 0) {
        $semester_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}nds_semesters WHERE academic_year_id = %d AND is_active = 1 ORDER BY id DESC LIMIT 1",
            $year_id
        ));
    }

    return array($year_id, $semester_id);
}

function nds_staff_get_lecturer_course_ids($staff_id) {
    global $wpdb;

    if ($staff_id <= 0) {
        return array();
    }

    $rows = $wpdb->get_col($wpdb->prepare(
        "SELECT course_id FROM {$wpdb->prefix}nds_course_lecturers WHERE lecturer_id = %d",
        $staff_id
    ));

    return array_values(array_unique(array_map('intval', $rows ?: array())));
}

function nds_staff_course_is_owned_by_lecturer($staff_id, $course_id) {
    if ($staff_id <= 0 || $course_id <= 0) {
        return false;
    }

    $course_ids = nds_staff_get_lecturer_course_ids($staff_id);
    return in_array((int) $course_id, $course_ids, true);
}

function nds_staff_require_lecturer() {
    if (!is_user_logged_in()) {
        wp_die('Unauthorized');
    }

    $staff_id = function_exists('nds_portal_get_current_staff_id') ? (int) nds_portal_get_current_staff_id() : 0;
    if ($staff_id <= 0) {
        wp_die('No staff profile linked to this account.');
    }

    global $wpdb;
    $role = (string) $wpdb->get_var($wpdb->prepare(
        "SELECT role FROM {$wpdb->prefix}nds_staff WHERE id = %d LIMIT 1",
        $staff_id
    ));

    if (strtolower(trim($role)) !== 'lecturer' && !current_user_can('manage_options')) {
        wp_die('Only lecturers can perform this action.');
    }

    return $staff_id;
}

function nds_staff_redirect_with_notice($key, $value, $default_tab = 'overview') {
    $allowed_tabs = array('overview', 'timetable', 'classes', 'marks', 'content', 'assessments', 'gradebook', 'communication', 'reports', 'enrollment', 'structure');
    $tab = in_array($default_tab, $allowed_tabs, true) ? $default_tab : 'overview';
    $safe_query = array();

    $posted_redirect = isset($_POST['redirect_url']) ? esc_url_raw(wp_unslash($_POST['redirect_url'])) : '';
    if (!empty($posted_redirect)) {
        $parts = wp_parse_url($posted_redirect);
        $staff_path = wp_parse_url(home_url('/staff-portal/'), PHP_URL_PATH);
        $posted_path = isset($parts['path']) ? $parts['path'] : '';

        if (!empty($posted_path) && !empty($staff_path) && strpos($posted_path, $staff_path) === 0 && !empty($parts['query'])) {
            parse_str($parts['query'], $query_args);
            if (!empty($query_args['tab']) && in_array($query_args['tab'], $allowed_tabs, true)) {
                $tab = $query_args['tab'];
            }

            foreach (array('course_id', 'assessment_id', 'student_id', 'edit_content_id') as $ctx_key) {
                if (isset($query_args[$ctx_key]) && (int) $query_args[$ctx_key] > 0) {
                    $safe_query[$ctx_key] = (int) $query_args[$ctx_key];
                }
            }
        }
    }

    $redirect_url = add_query_arg('tab', $tab, home_url('/staff-portal/'));
    if (!empty($safe_query)) {
        $redirect_url = add_query_arg($safe_query, $redirect_url);
    }
    $redirect_url = add_query_arg($key, $value, $redirect_url);
    wp_safe_redirect($redirect_url);
    exit;
}

function nds_staff_handle_upload($field_name = 'attachment_file') {
    if (empty($_FILES[$field_name]['name'])) {
        return '';
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    $uploaded = wp_handle_upload($_FILES[$field_name], array('test_form' => false));

    if (!empty($uploaded['error'])) {
        return new WP_Error('upload_failed', $uploaded['error']);
    }

    return isset($uploaded['url']) ? esc_url_raw($uploaded['url']) : '';
}

function nds_staff_normalize_datetime($value) {
    $value = is_string($value) ? trim($value) : '';
    if ($value === '') {
        return null;
    }

    $value = str_replace('T', ' ', $value);
    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return null;
    }

    return date('Y-m-d H:i:s', $timestamp);
}

function nds_staff_normalize_access_window($start_raw, $end_raw) {
    $access_start = !empty($start_raw) ? nds_staff_normalize_datetime(wp_unslash($start_raw)) : null;
    $access_end = !empty($end_raw) ? nds_staff_normalize_datetime(wp_unslash($end_raw)) : null;

    if (!empty($access_start) && !empty($access_end)) {
        $start_ts = strtotime($access_start);
        $end_ts = strtotime($access_end);

        // Avoid invisible content when the window is reversed or zero-length.
        if ($start_ts !== false && $end_ts !== false && $end_ts <= $start_ts) {
            $access_start = null;
            $access_end = null;
        }
    }

    return array($access_start, $access_end);
}

function nds_staff_ensure_portal_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $t_content = $wpdb->prefix . 'nds_lecturer_content';
    $sql_content = "CREATE TABLE IF NOT EXISTS $t_content (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        staff_id INT NOT NULL,
        course_id INT NOT NULL,
        module_id INT NULL,
        section_id BIGINT UNSIGNED NULL,
        content_type VARCHAR(50) NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT NULL,
        quiz_data LONGTEXT NULL,
        resource_url VARCHAR(500) NULL,
        attachment_url VARCHAR(500) NULL,
        due_date DATE NULL,
        is_visible TINYINT(1) DEFAULT 1,
        access_start DATETIME NULL,
        access_end DATETIME NULL,
        completion_required TINYINT(1) DEFAULT 0,
        min_grade_required DECIMAL(5,2) NULL,
        sort_order INT DEFAULT 0,
        status VARCHAR(20) DEFAULT 'published',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_staff (staff_id),
        KEY idx_course (course_id),
        KEY idx_type (content_type),
        KEY idx_status (status)
    ) $charset_collate;";
    dbDelta($sql_content);

    // Migrate: add any columns that may be missing from older installs
    $existing_cols = $wpdb->get_col("SHOW COLUMNS FROM {$t_content}");
    $migrations = array(
        'module_id'           => "ALTER TABLE {$t_content} ADD COLUMN module_id INT NULL AFTER course_id",
        'section_id'          => "ALTER TABLE {$t_content} ADD COLUMN section_id BIGINT UNSIGNED NULL AFTER module_id",
        'quiz_data'           => "ALTER TABLE {$t_content} ADD COLUMN quiz_data LONGTEXT NULL AFTER description",
        'attachment_url'      => "ALTER TABLE {$t_content} ADD COLUMN attachment_url VARCHAR(500) NULL AFTER resource_url",
        'due_date'            => "ALTER TABLE {$t_content} ADD COLUMN due_date DATE NULL AFTER attachment_url",
        'is_visible'          => "ALTER TABLE {$t_content} ADD COLUMN is_visible TINYINT(1) DEFAULT 1 AFTER due_date",
        'access_start'        => "ALTER TABLE {$t_content} ADD COLUMN access_start DATETIME NULL AFTER is_visible",
        'access_end'          => "ALTER TABLE {$t_content} ADD COLUMN access_end DATETIME NULL AFTER access_start",
        'completion_required' => "ALTER TABLE {$t_content} ADD COLUMN completion_required TINYINT(1) DEFAULT 0 AFTER access_end",
        'min_grade_required'  => "ALTER TABLE {$t_content} ADD COLUMN min_grade_required DECIMAL(5,2) NULL AFTER completion_required",
        'sort_order'          => "ALTER TABLE {$t_content} ADD COLUMN sort_order INT DEFAULT 0 AFTER min_grade_required",
    );
    foreach ($migrations as $col => $sql) {
        if (!in_array($col, $existing_cols, true)) {
            $wpdb->query($sql);
        }
    }

    $t_assessments = $wpdb->prefix . 'nds_assessments';
    $sql_assessments = "CREATE TABLE IF NOT EXISTS $t_assessments (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        staff_id INT NOT NULL,
        course_id INT NOT NULL,
        module_id INT NULL,
        assessment_type VARCHAR(30) NOT NULL,
        title VARCHAR(255) NOT NULL,
        instructions TEXT NULL,
        max_grade DECIMAL(6,2) DEFAULT 100,
        due_date DATETIME NULL,
        time_limit_minutes INT NULL,
        attempts_allowed INT DEFAULT 1,
        shuffle_questions TINYINT(1) DEFAULT 0,
        pass_percentage DECIMAL(5,2) NULL,
        password_protect VARCHAR(100) NULL,
        late_penalty_percent DECIMAL(5,2) NULL,
        status VARCHAR(20) DEFAULT 'published',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_staff (staff_id),
        KEY idx_course (course_id),
        KEY idx_type (assessment_type),
        KEY idx_due_date (due_date)
    ) $charset_collate;";
    dbDelta($sql_assessments);

    $t_question_bank = $wpdb->prefix . 'nds_question_bank';
    $sql_question_bank = "CREATE TABLE IF NOT EXISTS $t_question_bank (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        staff_id INT NOT NULL,
        assessment_id BIGINT UNSIGNED NULL,
        category VARCHAR(100) NULL,
        question_type VARCHAR(50) NOT NULL,
        question_text TEXT NOT NULL,
        options_json LONGTEXT NULL,
        correct_answer LONGTEXT NULL,
        marks DECIMAL(6,2) DEFAULT 1,
        is_active TINYINT(1) DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_staff (staff_id),
        KEY idx_assessment (assessment_id),
        KEY idx_category (category)
    ) $charset_collate;";
    dbDelta($sql_question_bank);

    $t_submissions = $wpdb->prefix . 'nds_assessment_submissions';
    $sql_submissions = "CREATE TABLE IF NOT EXISTS $t_submissions (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        assessment_id BIGINT UNSIGNED NOT NULL,
        student_id INT NOT NULL,
        attempt_no INT DEFAULT 1,
        submitted_text LONGTEXT NULL,
        file_url VARCHAR(500) NULL,
        score DECIMAL(6,2) NULL,
        feedback LONGTEXT NULL,
        status VARCHAR(20) DEFAULT 'submitted',
        submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        graded_at DATETIME NULL,
        graded_by INT NULL,
        PRIMARY KEY (id),
        KEY idx_assessment (assessment_id),
        KEY idx_student (student_id),
        KEY idx_status (status)
    ) $charset_collate;";
    dbDelta($sql_submissions);

    $t_announcements = $wpdb->prefix . 'nds_lecturer_announcements';
    $sql_announcements = "CREATE TABLE IF NOT EXISTS $t_announcements (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        staff_id INT NOT NULL,
        course_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        message LONGTEXT NOT NULL,
        status VARCHAR(20) DEFAULT 'published',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_staff (staff_id),
        KEY idx_course (course_id)
    ) $charset_collate;";
    dbDelta($sql_announcements);

    $t_messages = $wpdb->prefix . 'nds_lecturer_messages';
    $sql_messages = "CREATE TABLE IF NOT EXISTS $t_messages (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        staff_id INT NOT NULL,
        student_id INT NOT NULL,
        course_id INT NULL,
        subject VARCHAR(255) NOT NULL,
        message LONGTEXT NOT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_staff (staff_id),
        KEY idx_student (student_id),
        KEY idx_course (course_id)
    ) $charset_collate;";
    dbDelta($sql_messages);

    $t_sections = $wpdb->prefix . 'nds_course_sections';
    $sql_sections = "CREATE TABLE IF NOT EXISTS $t_sections (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        course_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        description LONGTEXT NULL,
        position INT DEFAULT 0,
        is_visible TINYINT(1) DEFAULT 1,
        access_start DATETIME NULL,
        access_end DATETIME NULL,
        completion_required TINYINT(1) DEFAULT 0,
        min_grade_required DECIMAL(5,2) NULL,
        created_by INT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_course (course_id),
        KEY idx_position (position)
    ) $charset_collate;";
    dbDelta($sql_sections);
}

add_action('init', function () {
    if (get_transient('nds_staff_portal_tables_checked')) {
        return;
    }
    nds_staff_ensure_portal_tables();
    set_transient('nds_staff_portal_tables_checked', 1, 10 * MINUTE_IN_SECONDS);
}, 2);

add_action('admin_post_nds_staff_create_content', function () {
    $staff_id = nds_staff_require_lecturer();

    if (!isset($_POST['nds_staff_create_content_nonce']) || !wp_verify_nonce($_POST['nds_staff_create_content_nonce'], 'nds_staff_create_content')) {
        nds_staff_redirect_with_notice('content_error', 'security', 'content');
    }

    $module_id = isset($_POST['module_id']) ? (int) $_POST['module_id'] : 0;
    if ($module_id <= 0) {
        nds_staff_redirect_with_notice('content_error', 'missing_fields', 'content');
    }
    global $wpdb;
    $course_id = isset($_POST['course_id']) ? (int) $_POST['course_id'] : 0;
    $module_row = $wpdb->get_row($wpdb->prepare(
        "SELECT id, course_id FROM {$wpdb->prefix}nds_modules WHERE id = %d LIMIT 1",
        $module_id
    ), ARRAY_A);
    if (empty($module_row['id'])) {
        nds_staff_redirect_with_notice('content_error', 'invalid_module', 'content');
    }
    $module_course_id = (int) $module_row['course_id'];
    if (!nds_staff_course_is_owned_by_lecturer($staff_id, $module_course_id)) {
        nds_staff_redirect_with_notice('content_error', 'permission', 'content');
    }
    $course_id = $module_course_id;

    $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
    $description = isset($_POST['description']) ? wp_kses_post(wp_unslash($_POST['description'])) : '';
    $content_type = isset($_POST['content_type']) ? sanitize_text_field(wp_unslash($_POST['content_type'])) : 'study_material';
    $resource_url = isset($_POST['resource_url']) ? esc_url_raw(wp_unslash($_POST['resource_url'])) : '';
    $due_date = isset($_POST['due_date']) ? sanitize_text_field(wp_unslash($_POST['due_date'])) : null;
    $is_visible = !empty($_POST['is_visible']) ? 1 : 0;
    list($access_start, $access_end) = nds_staff_normalize_access_window(
        $_POST['access_start'] ?? '',
        $_POST['access_end'] ?? ''
    );
    $completion_required = !empty($_POST['completion_required']) ? 1 : 0;
    $min_grade_required = isset($_POST['min_grade_required']) && $_POST['min_grade_required'] !== '' ? (float) $_POST['min_grade_required'] : null;

    // Sanitize and store quiz questions when content type is quiz
    $quiz_data = null;
    if ($content_type === 'quiz' && !empty($_POST['quiz_data'])) {
        $decoded = json_decode(wp_unslash($_POST['quiz_data']), true);
        if (is_array($decoded)) {
            $sanitized_questions = array();
            foreach ($decoded as $q) {
                if (!is_array($q) || empty(trim($q['text'] ?? ''))) {
                    continue;
                }
                $sanitized_questions[] = array(
                    'type'         => sanitize_text_field($q['type'] ?? 'multiple_choice'),
                    'text'         => sanitize_textarea_field($q['text']),
                    'options'      => array_map('sanitize_text_field', array_slice((array)($q['options'] ?? array()), 0, 4)),
                    'correct'      => sanitize_text_field($q['correct'] ?? 'A'),
                    'model_answer' => sanitize_textarea_field($q['model_answer'] ?? ''),
                );
            }
            if (!empty($sanitized_questions)) {
                $quiz_data = wp_json_encode($sanitized_questions);
            }
        }
    }

    if ($content_type === 'quiz' && empty($quiz_data)) {
        nds_staff_redirect_with_notice('content_error', 'missing_fields', 'content');
    }

    // Description is required except for quizzes (which use the question builder)
    if ($title === '' || ($description === '' && $content_type !== 'quiz')) {
        nds_staff_redirect_with_notice('content_error', 'missing_fields', 'content');
    }

    $attachment_url = nds_staff_handle_upload('attachment_file');
    if (is_wp_error($attachment_url)) {
        nds_staff_redirect_with_notice('content_error', 'upload_failed', 'content');
    }

    $result = $wpdb->insert(
        $wpdb->prefix . 'nds_lecturer_content',
        array(
            'staff_id' => $staff_id,
            'course_id' => $course_id,
            'module_id' => $module_id > 0 ? $module_id : null,
            'content_type' => $content_type,
            'title' => $title,
            'description' => $description,
            'quiz_data' => $quiz_data,
            'resource_url' => $resource_url,
            'attachment_url' => $attachment_url,
            'due_date' => !empty($due_date) ? $due_date : null,
            'is_visible' => $is_visible,
            'access_start' => $access_start,
            'access_end' => $access_end,
            'completion_required' => $completion_required,
            'min_grade_required' => $min_grade_required,
            'status' => 'published',
        ),
        array('%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%f', '%s')
    );

    if ($result === false) {
        nds_staff_redirect_with_notice('content_error', 'save_failed', 'content');
    }

    if (!empty($_POST['notify_students'])) {
        list($active_year_id, $active_semester_id) = nds_staff_get_active_term_ids();
        if ($active_year_id > 0 && $active_semester_id > 0) {
            $student_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT student_id FROM {$wpdb->prefix}nds_student_enrollments
                 WHERE course_id = %d AND academic_year_id = %d AND semester_id = %d AND status IN ('applied','enrolled','waitlisted')",
                $course_id,
                $active_year_id,
                $active_semester_id
            ));

            foreach ($student_ids as $student_id) {
                $wpdb->insert(
                    $wpdb->prefix . 'nds_notifications',
                    array(
                        'student_id' => (int) $student_id,
                        'title' => $title,
                        'message' => wp_strip_all_tags($description),
                        'type' => 'study_material',
                        'link' => home_url('/portal/'),
                        'is_read' => 0,
                        'created_at' => current_time('mysql'),
                    ),
                    array('%d', '%s', '%s', '%s', '%s', '%d', '%s')
                );
            }
        }
    }

    nds_staff_redirect_with_notice('content_notice', 'created', 'content');
});

add_action('admin_post_nds_staff_update_content', function () {
    $staff_id = nds_staff_require_lecturer();

    if (!isset($_POST['nds_staff_update_content_nonce']) || !wp_verify_nonce($_POST['nds_staff_update_content_nonce'], 'nds_staff_update_content')) {
        nds_staff_redirect_with_notice('content_error', 'security', 'content');
    }

    global $wpdb;
    $content_id = isset($_POST['content_id']) ? (int) $_POST['content_id'] : 0;
    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}nds_lecturer_content WHERE id = %d AND staff_id = %d LIMIT 1",
        $content_id,
        $staff_id
    ), ARRAY_A);
    if (empty($existing)) {
        nds_staff_redirect_with_notice('content_error', 'permission', 'content');
    }

    $module_id = isset($_POST['module_id']) ? (int) $_POST['module_id'] : 0;
    if ($module_id <= 0) {
        nds_staff_redirect_with_notice('content_error', 'missing_fields', 'content');
    }
    $course_id = isset($_POST['course_id']) ? (int) $_POST['course_id'] : 0;
    $module_row = $wpdb->get_row($wpdb->prepare(
        "SELECT id, course_id FROM {$wpdb->prefix}nds_modules WHERE id = %d LIMIT 1",
        $module_id
    ), ARRAY_A);
    if (empty($module_row['id'])) {
        nds_staff_redirect_with_notice('content_error', 'invalid_module', 'content');
    }
    $module_course_id = (int) $module_row['course_id'];
    if (!nds_staff_course_is_owned_by_lecturer($staff_id, $module_course_id)) {
        nds_staff_redirect_with_notice('content_error', 'permission', 'content');
    }
    $course_id = $module_course_id;

    $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
    $description = isset($_POST['description']) ? wp_kses_post(wp_unslash($_POST['description'])) : '';
    $content_type = isset($_POST['content_type']) ? sanitize_text_field(wp_unslash($_POST['content_type'])) : 'study_material';
    $resource_url = isset($_POST['resource_url']) ? esc_url_raw(wp_unslash($_POST['resource_url'])) : '';
    $due_date = isset($_POST['due_date']) ? sanitize_text_field(wp_unslash($_POST['due_date'])) : null;
    $is_visible = !empty($_POST['is_visible']) ? 1 : 0;
    list($access_start, $access_end) = nds_staff_normalize_access_window(
        $_POST['access_start'] ?? '',
        $_POST['access_end'] ?? ''
    );
    $completion_required = !empty($_POST['completion_required']) ? 1 : 0;
    $min_grade_required = isset($_POST['min_grade_required']) && $_POST['min_grade_required'] !== '' ? (float) $_POST['min_grade_required'] : null;

    $quiz_data = $existing['quiz_data'];
    if ($content_type === 'quiz' && !empty($_POST['quiz_data'])) {
        $decoded = json_decode(wp_unslash($_POST['quiz_data']), true);
        if (is_array($decoded)) {
            $sanitized_questions = array();
            foreach ($decoded as $q) {
                if (!is_array($q) || empty(trim($q['text'] ?? ''))) {
                    continue;
                }
                $sanitized_questions[] = array(
                    'type'         => sanitize_text_field($q['type'] ?? 'multiple_choice'),
                    'text'         => sanitize_textarea_field($q['text']),
                    'options'      => array_map('sanitize_text_field', array_slice((array)($q['options'] ?? array()), 0, 4)),
                    'correct'      => sanitize_text_field($q['correct'] ?? 'A'),
                    'model_answer' => sanitize_textarea_field($q['model_answer'] ?? ''),
                );
            }
            if (!empty($sanitized_questions)) {
                $quiz_data = wp_json_encode($sanitized_questions);
            }
        }
    }
    if ($content_type !== 'quiz') {
        $quiz_data = null;
    }

    if ($content_type === 'quiz' && empty($quiz_data)) {
        nds_staff_redirect_with_notice('content_error', 'missing_fields', 'content');
    }

    if ($title === '' || ($description === '' && $content_type !== 'quiz')) {
        nds_staff_redirect_with_notice('content_error', 'missing_fields', 'content');
    }

    $attachment_url = nds_staff_handle_upload('attachment_file');
    if (is_wp_error($attachment_url)) {
        nds_staff_redirect_with_notice('content_error', 'upload_failed', 'content');
    }
    if ($attachment_url === '') {
        $attachment_url = $existing['attachment_url'];
    }

    $updated = $wpdb->update(
        $wpdb->prefix . 'nds_lecturer_content',
        array(
            'course_id' => $course_id,
            'module_id' => $module_id,
            'content_type' => $content_type,
            'title' => $title,
            'description' => $description,
            'quiz_data' => $quiz_data,
            'resource_url' => $resource_url,
            'attachment_url' => $attachment_url,
            'due_date' => !empty($due_date) ? $due_date : null,
            'is_visible' => $is_visible,
            'access_start' => $access_start,
            'access_end' => $access_end,
            'completion_required' => $completion_required,
            'min_grade_required' => $min_grade_required,
        ),
        array(
            'id' => $content_id,
            'staff_id' => $staff_id,
        ),
        array('%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%f'),
        array('%d', '%d')
    );

    if ($updated === false) {
        nds_staff_redirect_with_notice('content_error', 'save_failed', 'content');
    }

    nds_staff_redirect_with_notice('content_notice', 'updated', 'content');
});

add_action('admin_post_nds_staff_delete_content', function () {
    $staff_id = nds_staff_require_lecturer();

    if (!isset($_POST['nds_staff_delete_content_nonce']) || !wp_verify_nonce($_POST['nds_staff_delete_content_nonce'], 'nds_staff_delete_content')) {
        nds_staff_redirect_with_notice('content_error', 'security', 'content');
    }

    $content_id = isset($_POST['content_id']) ? (int) $_POST['content_id'] : 0;
    if ($content_id <= 0) {
        nds_staff_redirect_with_notice('content_error', 'missing_fields', 'content');
    }

    global $wpdb;
    $deleted = $wpdb->delete(
        $wpdb->prefix . 'nds_lecturer_content',
        array(
            'id' => $content_id,
            'staff_id' => $staff_id,
        ),
        array('%d', '%d')
    );

    if ($deleted === false) {
        nds_staff_redirect_with_notice('content_error', 'save_failed', 'content');
    }

    nds_staff_redirect_with_notice('content_notice', 'deleted', 'content');
});

add_action('admin_post_nds_staff_create_assessment', function () {
    $staff_id = nds_staff_require_lecturer();

    if (!isset($_POST['nds_staff_create_assessment_nonce']) || !wp_verify_nonce($_POST['nds_staff_create_assessment_nonce'], 'nds_staff_create_assessment')) {
        nds_staff_redirect_with_notice('assessment_error', 'security', 'assessments');
    }

    $course_id = isset($_POST['course_id']) ? (int) $_POST['course_id'] : 0;
    if (!nds_staff_course_is_owned_by_lecturer($staff_id, $course_id)) {
        nds_staff_redirect_with_notice('assessment_error', 'permission', 'assessments');
    }

    $module_id = isset($_POST['module_id']) ? (int) $_POST['module_id'] : 0;
    if ($module_id > 0) {
        global $wpdb;
        $module_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}nds_modules WHERE id = %d AND course_id = %d LIMIT 1",
            $module_id,
            $course_id
        ));

        if (empty($module_exists)) {
            nds_staff_redirect_with_notice('assessment_error', 'invalid_module', 'assessments');
        }
    }

    $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
    $assessment_type = isset($_POST['assessment_type']) ? sanitize_text_field(wp_unslash($_POST['assessment_type'])) : 'assignment';
    $instructions = isset($_POST['instructions']) ? wp_kses_post(wp_unslash($_POST['instructions'])) : '';

    if ($title === '') {
        nds_staff_redirect_with_notice('assessment_error', 'missing_fields', 'assessments');
    }

    $ok = $wpdb->insert(
        $wpdb->prefix . 'nds_assessments',
        array(
            'staff_id' => $staff_id,
            'course_id' => $course_id,
            'module_id' => $module_id > 0 ? $module_id : null,
            'assessment_type' => $assessment_type,
            'title' => $title,
            'instructions' => $instructions,
            'max_grade' => isset($_POST['max_grade']) ? (float) $_POST['max_grade'] : 100,
            'due_date' => !empty($_POST['due_date']) ? sanitize_text_field(wp_unslash($_POST['due_date'])) : null,
            'time_limit_minutes' => !empty($_POST['time_limit_minutes']) ? (int) $_POST['time_limit_minutes'] : null,
            'attempts_allowed' => !empty($_POST['attempts_allowed']) ? (int) $_POST['attempts_allowed'] : 1,
            'shuffle_questions' => !empty($_POST['shuffle_questions']) ? 1 : 0,
            'pass_percentage' => !empty($_POST['pass_percentage']) ? (float) $_POST['pass_percentage'] : null,
            'password_protect' => !empty($_POST['password_protect']) ? sanitize_text_field(wp_unslash($_POST['password_protect'])) : null,
            'late_penalty_percent' => !empty($_POST['late_penalty_percent']) ? (float) $_POST['late_penalty_percent'] : null,
            'status' => 'published',
        ),
        array('%d', '%d', '%d', '%s', '%s', '%s', '%f', '%s', '%d', '%d', '%d', '%f', '%s', '%f', '%s')
    );

    if ($ok === false) {
        nds_staff_redirect_with_notice('assessment_error', 'save_failed', 'assessments');
    }

    nds_staff_redirect_with_notice('assessment_notice', 'created', 'assessments');
});

add_action('admin_post_nds_staff_create_question', function () {
    $staff_id = nds_staff_require_lecturer();

    if (!isset($_POST['nds_staff_create_question_nonce']) || !wp_verify_nonce($_POST['nds_staff_create_question_nonce'], 'nds_staff_create_question')) {
        nds_staff_redirect_with_notice('assessment_error', 'security', 'assessments');
    }

    $question_text = isset($_POST['question_text']) ? sanitize_textarea_field(wp_unslash($_POST['question_text'])) : '';
    if ($question_text === '') {
        nds_staff_redirect_with_notice('assessment_error', 'missing_fields', 'assessments');
    }

    global $wpdb;
    $wpdb->insert(
        $wpdb->prefix . 'nds_question_bank',
        array(
            'staff_id' => $staff_id,
            'assessment_id' => !empty($_POST['assessment_id']) ? (int) $_POST['assessment_id'] : null,
            'category' => !empty($_POST['category']) ? sanitize_text_field(wp_unslash($_POST['category'])) : 'General',
            'question_type' => !empty($_POST['question_type']) ? sanitize_text_field(wp_unslash($_POST['question_type'])) : 'multiple_choice',
            'question_text' => $question_text,
            'options_json' => !empty($_POST['options_json']) ? wp_json_encode(array_map('trim', explode("\n", (string) wp_unslash($_POST['options_json'])))) : null,
            'correct_answer' => !empty($_POST['correct_answer']) ? sanitize_textarea_field(wp_unslash($_POST['correct_answer'])) : null,
            'marks' => !empty($_POST['marks']) ? (float) $_POST['marks'] : 1,
        ),
        array('%d', '%d', '%s', '%s', '%s', '%s', '%s', '%f')
    );

    nds_staff_redirect_with_notice('assessment_notice', 'question_created', 'assessments');
});

add_action('admin_post_nds_staff_grade_submission', function () {
    $staff_id = nds_staff_require_lecturer();

    if (!isset($_POST['nds_staff_grade_submission_nonce']) || !wp_verify_nonce($_POST['nds_staff_grade_submission_nonce'], 'nds_staff_grade_submission')) {
        nds_staff_redirect_with_notice('assessment_error', 'security', 'assessments');
    }

    $submission_id = isset($_POST['submission_id']) ? (int) $_POST['submission_id'] : 0;
    if ($submission_id <= 0) {
        nds_staff_redirect_with_notice('assessment_error', 'missing_fields', 'assessments');
    }

    global $wpdb;
    $submission = $wpdb->get_row($wpdb->prepare(
        "SELECT sub.id, sub.assessment_id, a.course_id
         FROM {$wpdb->prefix}nds_assessment_submissions sub
         INNER JOIN {$wpdb->prefix}nds_assessments a ON a.id = sub.assessment_id
         WHERE sub.id = %d",
        $submission_id
    ), ARRAY_A);

    if (empty($submission) || !nds_staff_course_is_owned_by_lecturer($staff_id, (int) $submission['course_id'])) {
        nds_staff_redirect_with_notice('assessment_error', 'permission', 'assessments');
    }

    $score = isset($_POST['score']) && $_POST['score'] !== '' ? (float) $_POST['score'] : null;
    $feedback = isset($_POST['feedback']) ? sanitize_textarea_field(wp_unslash($_POST['feedback'])) : '';

    $wpdb->update(
        $wpdb->prefix . 'nds_assessment_submissions',
        array(
            'score' => $score,
            'feedback' => $feedback,
            'status' => 'graded',
            'graded_at' => current_time('mysql'),
            'graded_by' => $staff_id,
        ),
        array('id' => $submission_id),
        array('%f', '%s', '%s', '%s', '%d'),
        array('%d')
    );

    nds_staff_redirect_with_notice('assessment_notice', 'graded', 'assessments');
});

add_action('admin_post_nds_staff_grade_content_assignment_submission', function () {
    $staff_id = nds_staff_require_lecturer();

    if (!isset($_POST['nds_staff_grade_content_assignment_submission_nonce']) || !wp_verify_nonce($_POST['nds_staff_grade_content_assignment_submission_nonce'], 'nds_staff_grade_content_assignment_submission')) {
        nds_staff_redirect_with_notice('assessment_error', 'security', 'assessments');
    }

    $submission_id = isset($_POST['content_submission_id']) ? (int) $_POST['content_submission_id'] : 0;
    if ($submission_id <= 0) {
        nds_staff_redirect_with_notice('assessment_error', 'missing_fields', 'assessments');
    }

    if (!function_exists('nds_portal_ensure_assignment_submissions_table')) {
        nds_staff_redirect_with_notice('assessment_error', 'save_failed', 'assessments');
    }

    global $wpdb;
    $assignment_submissions_table = nds_portal_ensure_assignment_submissions_table();
    $submission = $wpdb->get_row($wpdb->prepare(
        "SELECT cs.id, cs.content_id, cs.course_id, cs.module_id, cs.student_id, lc.staff_id, lc.title AS assignment_title
         FROM {$assignment_submissions_table} cs
         INNER JOIN {$wpdb->prefix}nds_lecturer_content lc ON lc.id = cs.content_id
         WHERE cs.id = %d
         LIMIT 1",
        $submission_id
    ), ARRAY_A);

    if (empty($submission) || (int) ($submission['staff_id'] ?? 0) !== (int) $staff_id || !nds_staff_course_is_owned_by_lecturer($staff_id, (int) ($submission['course_id'] ?? 0))) {
        nds_staff_redirect_with_notice('assessment_error', 'permission', 'assessments');
    }

    $score = isset($_POST['score']) && $_POST['score'] !== '' ? (float) $_POST['score'] : null;
    $feedback = isset($_POST['feedback']) ? sanitize_textarea_field(wp_unslash($_POST['feedback'])) : '';

    $update_data = array(
        'score' => $score,
        'feedback' => $feedback,
        'status' => 'graded',
        'graded_at' => current_time('mysql'),
        'graded_by' => $staff_id,
    );

    $updated = $wpdb->update(
        $assignment_submissions_table,
        $update_data,
        array('id' => $submission_id),
        array('%f', '%s', '%s', '%s', '%d'),
        array('%d')
    );

    if ($updated === false) {
        nds_staff_redirect_with_notice('assessment_error', 'save_failed', 'assessments');
    }

    if (function_exists('nds_create_notification')) {
        $portal_link = home_url('/portal/?tab=courses');
        if ((int) ($submission['module_id'] ?? 0) > 0) {
            $portal_link = add_query_arg(
                array(
                    'tab' => 'courses',
                    'module_id' => (int) $submission['module_id'],
                    'assignment_content_id' => (int) $submission['content_id'],
                ),
                home_url('/portal/')
            );
        }

        $assignment_title = trim((string) ($submission['assignment_title'] ?? 'Assignment'));
        $score_text = $score !== null ? (' Score: ' . number_format((float) $score, 2) . '.') : '';
        nds_create_notification(
            (int) ($submission['student_id'] ?? 0),
            'Assignment reviewed',
            $assignment_title . ' has been reviewed.' . $score_text,
            'success',
            $portal_link
        );
    }

    nds_staff_redirect_with_notice('assessment_notice', 'graded', 'assessments');
});

add_action('admin_post_nds_staff_save_gradebook', function () {
    $staff_id = nds_staff_require_lecturer();

    if (!isset($_POST['nds_staff_save_gradebook_nonce']) || !wp_verify_nonce($_POST['nds_staff_save_gradebook_nonce'], 'nds_staff_save_gradebook')) {
        nds_staff_redirect_with_notice('gradebook_error', 'security', 'gradebook');
    }

    $course_id = isset($_POST['course_id']) ? (int) $_POST['course_id'] : 0;
    if (!nds_staff_course_is_owned_by_lecturer($staff_id, $course_id)) {
        nds_staff_redirect_with_notice('gradebook_error', 'permission', 'gradebook');
    }

    $enrollment_ids = isset($_POST['enrollment_id']) && is_array($_POST['enrollment_id']) ? array_map('intval', $_POST['enrollment_id']) : array();
    $percentages = isset($_POST['final_percentage']) && is_array($_POST['final_percentage']) ? $_POST['final_percentage'] : array();
    $grades = isset($_POST['final_grade']) && is_array($_POST['final_grade']) ? $_POST['final_grade'] : array();

    global $wpdb;
    foreach ($enrollment_ids as $i => $enrollment_id) {
        if ($enrollment_id <= 0) {
            continue;
        }

        $percentage = isset($percentages[$i]) && $percentages[$i] !== '' ? (float) $percentages[$i] : null;
        $grade = isset($grades[$i]) ? sanitize_text_field(wp_unslash($grades[$i])) : '';

        $wpdb->update(
            $wpdb->prefix . 'nds_student_enrollments',
            array(
                'final_percentage' => $percentage,
                'final_grade' => $grade,
                'updated_at' => current_time('mysql'),
            ),
            array(
                'id' => $enrollment_id,
                'course_id' => $course_id,
            ),
            array('%f', '%s', '%s'),
            array('%d', '%d')
        );
    }

    nds_staff_redirect_with_notice('gradebook_notice', 'saved', 'gradebook');
});

add_action('admin_post_nds_staff_post_announcement', function () {
    $staff_id = nds_staff_require_lecturer();

    if (!isset($_POST['nds_staff_post_announcement_nonce']) || !wp_verify_nonce($_POST['nds_staff_post_announcement_nonce'], 'nds_staff_post_announcement')) {
        nds_staff_redirect_with_notice('comm_error', 'security', 'communication');
    }

    $course_id = isset($_POST['course_id']) ? (int) $_POST['course_id'] : 0;
    if (!nds_staff_course_is_owned_by_lecturer($staff_id, $course_id)) {
        nds_staff_redirect_with_notice('comm_error', 'permission', 'communication');
    }

    $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
    $message = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';
    if ($title === '' || $message === '') {
        nds_staff_redirect_with_notice('comm_error', 'missing_fields', 'communication');
    }

    global $wpdb;
    $wpdb->insert(
        $wpdb->prefix . 'nds_lecturer_announcements',
        array(
            'staff_id' => $staff_id,
            'course_id' => $course_id,
            'title' => $title,
            'message' => $message,
            'status' => 'published',
        ),
        array('%d', '%d', '%s', '%s', '%s')
    );

    if (!empty($_POST['notify_students'])) {
        list($active_year_id, $active_semester_id) = nds_staff_get_active_term_ids();
        if ($active_year_id > 0 && $active_semester_id > 0) {
            $student_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT student_id FROM {$wpdb->prefix}nds_student_enrollments
                 WHERE course_id = %d AND academic_year_id = %d AND semester_id = %d AND status IN ('applied','enrolled','waitlisted')",
                $course_id,
                $active_year_id,
                $active_semester_id
            ));

            foreach ($student_ids as $student_id) {
                $wpdb->insert(
                    $wpdb->prefix . 'nds_notifications',
                    array(
                        'student_id' => (int) $student_id,
                        'title' => $title,
                        'message' => $message,
                        'type' => 'announcement',
                        'link' => home_url('/portal/'),
                        'is_read' => 0,
                        'created_at' => current_time('mysql'),
                    ),
                    array('%d', '%s', '%s', '%s', '%s', '%d', '%s')
                );
            }
        }
    }

    nds_staff_redirect_with_notice('comm_notice', 'announcement_posted', 'communication');
});

add_action('admin_post_nds_staff_send_message', function () {
    $staff_id = nds_staff_require_lecturer();

    if (!isset($_POST['nds_staff_send_message_nonce']) || !wp_verify_nonce($_POST['nds_staff_send_message_nonce'], 'nds_staff_send_message')) {
        nds_staff_redirect_with_notice('comm_error', 'security', 'communication');
    }

    $student_id = isset($_POST['student_id']) ? (int) $_POST['student_id'] : 0;
    $course_id = isset($_POST['course_id']) ? (int) $_POST['course_id'] : 0;

    if ($student_id <= 0 || !nds_staff_course_is_owned_by_lecturer($staff_id, $course_id)) {
        nds_staff_redirect_with_notice('comm_error', 'permission', 'communication');
    }

    $subject = isset($_POST['subject']) ? sanitize_text_field(wp_unslash($_POST['subject'])) : '';
    $message = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';

    if ($subject === '' || $message === '') {
        nds_staff_redirect_with_notice('comm_error', 'missing_fields', 'communication');
    }

    global $wpdb;
    $wpdb->insert(
        $wpdb->prefix . 'nds_lecturer_messages',
        array(
            'staff_id' => $staff_id,
            'student_id' => $student_id,
            'course_id' => $course_id,
            'subject' => $subject,
            'message' => $message,
            'is_read' => 0,
        ),
        array('%d', '%d', '%d', '%s', '%s', '%d')
    );

    $wpdb->insert(
        $wpdb->prefix . 'nds_notifications',
        array(
            'student_id' => $student_id,
            'title' => $subject,
            'message' => $message,
            'type' => 'message',
            'link' => home_url('/portal/'),
            'is_read' => 0,
            'created_at' => current_time('mysql'),
        ),
        array('%d', '%s', '%s', '%s', '%s', '%d', '%s')
    );

    nds_staff_redirect_with_notice('comm_notice', 'message_sent', 'communication');
});

add_action('admin_post_nds_staff_manage_enrollment', function () {
    $staff_id = nds_staff_require_lecturer();

    if (!isset($_POST['nds_staff_manage_enrollment_nonce']) || !wp_verify_nonce($_POST['nds_staff_manage_enrollment_nonce'], 'nds_staff_manage_enrollment')) {
        nds_staff_redirect_with_notice('enroll_error', 'security', 'enrollment');
    }

    $course_id = isset($_POST['course_id']) ? (int) $_POST['course_id'] : 0;
    if (!nds_staff_course_is_owned_by_lecturer($staff_id, $course_id)) {
        nds_staff_redirect_with_notice('enroll_error', 'permission', 'enrollment');
    }

    list($active_year_id, $active_semester_id) = nds_staff_get_active_term_ids();
    if ($active_year_id <= 0 || $active_semester_id <= 0) {
        nds_staff_redirect_with_notice('enroll_error', 'no_active_term', 'enrollment');
    }

    $intent = isset($_POST['intent']) ? sanitize_text_field(wp_unslash($_POST['intent'])) : 'enroll';

    global $wpdb;
    if ($intent === 'unenroll') {
        $student_id = isset($_POST['student_id']) ? (int) $_POST['student_id'] : 0;
        if ($student_id > 0) {
            $wpdb->delete(
                $wpdb->prefix . 'nds_student_enrollments',
                array(
                    'student_id' => $student_id,
                    'course_id' => $course_id,
                    'academic_year_id' => $active_year_id,
                    'semester_id' => $active_semester_id,
                ),
                array('%d', '%d', '%d', '%d')
            );
        }
        nds_staff_redirect_with_notice('enroll_notice', 'unenrolled', 'enrollment');
    }

    $identifier = isset($_POST['student_identifier']) ? sanitize_text_field(wp_unslash($_POST['student_identifier'])) : '';
    if ($identifier === '') {
        nds_staff_redirect_with_notice('enroll_error', 'missing_fields', 'enrollment');
    }

    $student = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}nds_students WHERE student_number = %s OR email = %s LIMIT 1",
        $identifier,
        $identifier
    ), ARRAY_A);

    if (empty($student)) {
        nds_staff_redirect_with_notice('enroll_error', 'student_not_found', 'enrollment');
    }

    $student_id = (int) $student['id'];
    $existing = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}nds_student_enrollments
         WHERE student_id = %d AND course_id = %d AND academic_year_id = %d AND semester_id = %d",
        $student_id,
        $course_id,
        $active_year_id,
        $active_semester_id
    ));

    if ($existing > 0) {
        $wpdb->update(
            $wpdb->prefix . 'nds_student_enrollments',
            array('status' => 'enrolled', 'updated_at' => current_time('mysql')),
            array('id' => $existing),
            array('%s', '%s'),
            array('%d')
        );
    } else {
        $wpdb->insert(
            $wpdb->prefix . 'nds_student_enrollments',
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
    }

    nds_staff_redirect_with_notice('enroll_notice', 'enrolled', 'enrollment');
});

add_action('admin_post_nds_staff_add_section', function () {
    $staff_id = nds_staff_require_lecturer();

    if (!isset($_POST['nds_staff_add_section_nonce']) || !wp_verify_nonce($_POST['nds_staff_add_section_nonce'], 'nds_staff_add_section')) {
        nds_staff_redirect_with_notice('structure_error', 'security', 'structure');
    }

    $course_id = isset($_POST['course_id']) ? (int) $_POST['course_id'] : 0;
    if (!nds_staff_course_is_owned_by_lecturer($staff_id, $course_id)) {
        nds_staff_redirect_with_notice('structure_error', 'permission', 'structure');
    }

    $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
    if ($title === '') {
        nds_staff_redirect_with_notice('structure_error', 'missing_fields', 'structure');
    }

    global $wpdb;
    $wpdb->insert(
        $wpdb->prefix . 'nds_course_sections',
        array(
            'course_id' => $course_id,
            'title' => $title,
            'description' => isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '',
            'position' => isset($_POST['position']) ? (int) $_POST['position'] : 0,
            'is_visible' => !empty($_POST['is_visible']) ? 1 : 0,
            'access_start' => !empty($_POST['access_start']) ? sanitize_text_field(wp_unslash($_POST['access_start'])) : null,
            'access_end' => !empty($_POST['access_end']) ? sanitize_text_field(wp_unslash($_POST['access_end'])) : null,
            'completion_required' => !empty($_POST['completion_required']) ? 1 : 0,
            'min_grade_required' => !empty($_POST['min_grade_required']) ? (float) $_POST['min_grade_required'] : null,
            'created_by' => $staff_id,
        ),
        array('%d', '%s', '%s', '%d', '%d', '%s', '%s', '%d', '%f', '%d')
    );

    nds_staff_redirect_with_notice('structure_notice', 'section_created', 'structure');
});

add_action('admin_post_nds_staff_update_section_visibility', function () {
    $staff_id = nds_staff_require_lecturer();

    if (!isset($_POST['nds_staff_update_section_visibility_nonce']) || !wp_verify_nonce($_POST['nds_staff_update_section_visibility_nonce'], 'nds_staff_update_section_visibility')) {
        nds_staff_redirect_with_notice('structure_error', 'security', 'structure');
    }

    $section_id = isset($_POST['section_id']) ? (int) $_POST['section_id'] : 0;
    $is_visible = !empty($_POST['is_visible']) ? 1 : 0;

    global $wpdb;
    $section = $wpdb->get_row($wpdb->prepare(
        "SELECT id, course_id FROM {$wpdb->prefix}nds_course_sections WHERE id = %d",
        $section_id
    ), ARRAY_A);

    if (empty($section) || !nds_staff_course_is_owned_by_lecturer($staff_id, (int) $section['course_id'])) {
        nds_staff_redirect_with_notice('structure_error', 'permission', 'structure');
    }

    $wpdb->update(
        $wpdb->prefix . 'nds_course_sections',
        array('is_visible' => $is_visible),
        array('id' => $section_id),
        array('%d'),
        array('%d')
    );

    nds_staff_redirect_with_notice('structure_notice', 'section_updated', 'structure');
});

add_action('admin_post_nds_staff_export_gradebook', function () {
    $staff_id = nds_staff_require_lecturer();

    if (!isset($_GET['course_id'])) {
        wp_die('Missing course');
    }

    $course_id = (int) $_GET['course_id'];
    if (!nds_staff_course_is_owned_by_lecturer($staff_id, $course_id)) {
        wp_die('Unauthorized course');
    }

    list($active_year_id, $active_semester_id) = nds_staff_get_active_term_ids();

    global $wpdb;
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT s.student_number, s.first_name, s.last_name, s.email, e.final_percentage, e.final_grade, e.status
         FROM {$wpdb->prefix}nds_student_enrollments e
         INNER JOIN {$wpdb->prefix}nds_students s ON s.id = e.student_id
         WHERE e.course_id = %d AND e.academic_year_id = %d AND e.semester_id = %d
         ORDER BY s.last_name, s.first_name",
        $course_id,
        $active_year_id,
        $active_semester_id
    ), ARRAY_A);

    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="gradebook-course-' . $course_id . '.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, array('Student Number', 'First Name', 'Last Name', 'Email', 'Final Percentage', 'Final Grade', 'Status'));
    foreach ($rows as $row) {
        fputcsv($output, array(
            $row['student_number'],
            $row['first_name'],
            $row['last_name'],
            $row['email'],
            $row['final_percentage'],
            $row['final_grade'],
            $row['status'],
        ));
    }
    fclose($output);
    exit;
});
