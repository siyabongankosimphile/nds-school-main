<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

global $wpdb;
$students_table = $wpdb->prefix . "nds_students";
$enrollments_table = $wpdb->prefix . "nds_student_enrollments";
$academic_years_table = $wpdb->prefix . "nds_academic_years";
$semesters_table = $wpdb->prefix . "nds_semesters";

// Generate unique student number
function nds_generate_student_number() {
    global $wpdb;
    $year = date('Y');
    $prefix = 'NDS' . $year;
    
    $last_number = $wpdb->get_var($wpdb->prepare(
        "SELECT student_number FROM {$wpdb->prefix}nds_students 
         WHERE student_number LIKE %s 
         ORDER BY student_number DESC LIMIT 1",
        $prefix . '%'
    ));
    
    if ($last_number) {
        $sequence = intval(substr($last_number, -4)) + 1;
    } else {
        $sequence = 1;
    }
    
    return $prefix . str_pad($sequence, 4, '0', STR_PAD_LEFT);
}

// Handle student data from form
function nds_handle_student_data($request_type = 'POST') {
    $request_data = ($request_type === 'POST') ? $_POST : $_GET;
    
    $national_id = isset($request_data['national_id']) ? trim(sanitize_text_field($request_data['national_id'])) : '';
    $passport_number = isset($request_data['passport_number']) ? trim(sanitize_text_field($request_data['passport_number'])) : '';
    $guardian_name = isset($request_data['guardian_name']) ? trim(sanitize_text_field($request_data['guardian_name'])) : '';
    $guardian_phone = isset($request_data['guardian_phone']) ? trim(sanitize_text_field($request_data['guardian_phone'])) : '';
    $guardian_email = isset($request_data['guardian_email']) ? trim(sanitize_email($request_data['guardian_email'])) : '';

    // Normalize IDs: treat empty string or '0' as NULL to avoid FK violations
    $faculty_id_raw = isset($request_data['faculty_id']) ? trim((string)$request_data['faculty_id']) : '';
    $faculty_id = ($faculty_id_raw === '' || $faculty_id_raw === '0') ? null : intval($faculty_id_raw);

    $program_id_raw = isset($request_data['program_id']) ? trim((string)$request_data['program_id']) : '';
    $program_id = ($program_id_raw === '' || $program_id_raw === '0') ? null : intval($program_id_raw);

    $course_id_raw = isset($request_data['course_id']) ? trim((string)$request_data['course_id']) : '';
    $course_id = ($course_id_raw === '' || $course_id_raw === '0') ? null : intval($course_id_raw);

    return [
        'faculty_id' => $faculty_id,
        'program_id' => $program_id,
        'first_name' => isset($request_data['first_name']) ? sanitize_text_field($request_data['first_name']) : '',
        'last_name' => isset($request_data['last_name']) ? sanitize_text_field($request_data['last_name']) : '',
        'email' => isset($request_data['email']) ? sanitize_email($request_data['email']) : '',
        'phone' => isset($request_data['phone']) ? sanitize_text_field($request_data['phone']) : '',
        'national_id' => ($national_id === '') ? null : $national_id,
        'passport_number' => ($passport_number === '') ? null : $passport_number,
        'date_of_birth' => isset($request_data['date_of_birth']) ? sanitize_text_field($request_data['date_of_birth']) : '',
        'gender' => isset($request_data['gender']) ? sanitize_text_field($request_data['gender']) : '',
        'address' => isset($request_data['address']) ? sanitize_textarea_field($request_data['address']) : '',
        'city' => isset($request_data['city']) ? sanitize_text_field($request_data['city']) : '',
        'country' => isset($request_data['country']) ? sanitize_text_field($request_data['country']) : 'South Africa',
        'profile_photo' => isset($request_data['profile_photo']) ? sanitize_text_field($request_data['profile_photo']) : '',
        'guardian_name' => ($guardian_name === '') ? null : $guardian_name,
        'guardian_phone' => ($guardian_phone === '') ? null : $guardian_phone,
        'guardian_email' => ($guardian_email === '') ? null : $guardian_email,
        'emergency_contact' => isset($request_data['emergency_contact']) ? sanitize_text_field($request_data['emergency_contact']) : '',
        'emergency_contact_name' => isset($request_data['emergency_contact_name']) ? sanitize_text_field($request_data['emergency_contact_name']) : '',
        'dietary_restrictions' => isset($request_data['dietary_restrictions']) ? sanitize_textarea_field($request_data['dietary_restrictions']) : '',
        'medical_notes' => isset($request_data['medical_notes']) ? sanitize_textarea_field($request_data['medical_notes']) : '',
        'highest_qualification' => isset($request_data['highest_qualification']) ? sanitize_text_field($request_data['highest_qualification']) : '',
        'intake_year' => isset($request_data['intake_year']) ? intval($request_data['intake_year']) : date('Y'),
        'intake_semester' => isset($request_data['intake_semester']) ? sanitize_text_field($request_data['intake_semester']) : 'January',
        'status' => isset($request_data['status']) ? sanitize_text_field($request_data['status']) : 'prospect',
        'source' => isset($request_data['source']) ? sanitize_text_field($request_data['source']) : 'admin',
        'gdpr_consent' => isset($request_data['gdpr_consent']) ? 1 : 0,
        'notes' => isset($request_data['notes']) ? sanitize_textarea_field($request_data['notes']) : '',
    ];
}

// Add new student
function nds_add_student() {
    global $wpdb;
    
    if (!isset($_POST['nds_add_student_nonce']) || !wp_verify_nonce($_POST['nds_add_student_nonce'], 'nds_add_student_nonce_action')) {
        wp_die('Security check failed.');
    }
    
    if (!current_user_can('manage_options')) {
        wp_die('Permission Denied');
    }
    
    $student_data = nds_handle_student_data('POST');
    
    if (empty($student_data['first_name']) || empty($student_data['last_name']) || empty($student_data['email'])) {
        wp_redirect(admin_url('admin.php?page=nds-add-learner&error=missing_required_fields&hint=' . urlencode('First name, last name and email are required')));
        exit;
    }
    
    // Check if email already exists
    $existing_email = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}nds_students WHERE email = %s",
        $student_data['email']
    ));
    
    if ($existing_email) {
        wp_redirect(admin_url('admin.php?page=nds-add-learner&error=email_exists&hint=' . urlencode('Email already exists in students table')));
        exit;
    }
    
    $student_data['student_number'] = nds_generate_student_number();
    // Debug: log keys being inserted
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[NDS] nds_add_student insert keys: ' . implode(',', array_keys($student_data)));
    }
    
    $inserted = $wpdb->insert(
        $wpdb->prefix . 'nds_students',
        $student_data,
        null // Let WordPress auto-detect the format
    );
    
    if ($inserted) {
        $student_id = $wpdb->insert_id;
        
        // Log activity
        nds_log_student_activity($student_id, get_current_user_id(), 'Student created', 'create', null, $student_data);
        
        wp_redirect(admin_url('admin.php?page=nds-learner-management&success=student_added&id=' . intval($student_id)));
        exit;
    } else {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[NDS] nds_add_student insert failed: ' . $wpdb->last_error);
        }
        $hint = $wpdb->last_error ? urlencode($wpdb->last_error) : urlencode('Unknown database error');
        wp_redirect(admin_url('admin.php?page=nds-add-learner&error=insert_failed&hint=' . $hint));
        exit;
    }
}
add_action('admin_post_nds_add_student', 'nds_add_student');

// Update student
function nds_update_student($student_id = null) {
    global $wpdb;
    
    // Get student_id from POST if not provided (for admin-post calls)
    if ($student_id === null && isset($_POST['student_id'])) {
        $student_id = intval($_POST['student_id']);
    }
    
    if (!$student_id) {
        wp_die('Invalid student ID');
    }
    
    if (!isset($_POST['nds_update_student_nonce']) || !wp_verify_nonce($_POST['nds_update_student_nonce'], 'nds_update_student_nonce_action')) {
        wp_die('Security check failed.');
    }
    
    if (!current_user_can('manage_options')) {
        wp_die('Permission Denied');
    }
    
    $student_data = nds_handle_student_data('POST');
    
    // Get old values for logging
    $old_values = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}nds_students WHERE id = %d",
        $student_id
    ), ARRAY_A);
    
    $updated = $wpdb->update(
        $wpdb->prefix . 'nds_students',
        $student_data,
        ['id' => $student_id],
        null, // Let WordPress auto-detect the format
        ['%d']
    );
    
    if ($updated !== false) {
        // Log activity
        nds_log_student_activity($student_id, get_current_user_id(), 'Student updated', 'update', $old_values, $student_data);
        
        wp_redirect(admin_url("admin.php?page=nds-edit-learner&id=$student_id&success=student_updated"));
        exit;
    } else {
        wp_redirect(admin_url("admin.php?page=nds-edit-learner&id=$student_id&error=update_failed"));
        exit;
    }
}
add_action('admin_post_nds_update_student', 'nds_update_student');

// Get student by ID
function nds_get_student($student_id) {
    global $wpdb;
    
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}nds_students WHERE id = %d",
        $student_id
    ));
}

// Get all students with filters
function nds_get_students($args = []) {
    global $wpdb;
    
    $defaults = [
        'status' => '',
        'intake_year' => '',
        'search' => '',
        'limit' => 20,
        'offset' => 0,
        'orderby' => 'created_at',
        'order' => 'DESC'
    ];
    
    $args = wp_parse_args($args, $defaults);
    
    $where_conditions = [];
    $where_values = [];
    
    if (!empty($args['status'])) {
        $where_conditions[] = 'status = %s';
        $where_values[] = $args['status'];
    }
    
    if (!empty($args['intake_year'])) {
        $where_conditions[] = 'intake_year = %d';
        $where_values[] = $args['intake_year'];
    }
    
    if (!empty($args['search'])) {
        $where_conditions[] = '(first_name LIKE %s OR last_name LIKE %s OR email LIKE %s OR student_number LIKE %s)';
        $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
        $where_values[] = $search_term;
        $where_values[] = $search_term;
        $where_values[] = $search_term;
        $where_values[] = $search_term;
    }
    
    $where_clause = '';
    if (!empty($where_conditions)) {
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    }
    
    $order_clause = "ORDER BY {$args['orderby']} {$args['order']}";
    $limit_clause = "LIMIT {$args['limit']} OFFSET {$args['offset']}";
    
    $query = "SELECT * FROM {$wpdb->prefix}nds_students $where_clause $order_clause $limit_clause";
    
    if (!empty($where_values)) {
        $query = $wpdb->prepare($query, $where_values);
    }
    
    return $wpdb->get_results($query);
}

// Reusable Recent Students component (admin/frontend-safe)
function nds_render_recent_students($args = []) {
    $defaults = [
        'limit' => 5,
        'wrapper_class' => 'bg-white p-6 rounded-lg shadow-md mb-8',
        'show_wrapper' => true,
    ];
    $args = wp_parse_args($args, $defaults);

    $students = nds_get_students(['limit' => intval($args['limit'])]);

    ob_start();
    if (!!$args['show_wrapper']) {
        echo '<div class="' . esc_attr($args['wrapper_class']) . '">';
        echo '<h3 class="text-lg font-semibold mb-4">Recent Students</h3>';
    }

    if (!empty($students)) {
        ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Joined</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($students as $student): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="text-sm font-medium text-gray-900"><?php echo esc_html($student->first_name . ' ' . $student->last_name); ?></div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo esc_html($student->email); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                <?php echo ($student->status === 'active' ? 'bg-green-100 text-green-800' : 
                                    ($student->status === 'prospect' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800')); ?>">
                                <?php echo ucfirst($student->status); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('M j, Y', strtotime($student->created_at)); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    } else {
        echo '<p class="text-gray-500">No students found.</p>';
    }

    if (!!$args['show_wrapper']) {
        echo '</div>';
    }

    return ob_get_clean();
}

// Shortcode to use anywhere: [nds_recent_students limit="5"]
function nds_recent_students_shortcode($atts = []) {
    $atts = shortcode_atts([
        'limit' => 5,
    ], $atts);
    return nds_render_recent_students(['limit' => intval($atts['limit'])]);
}
add_shortcode('nds_recent_students', 'nds_recent_students_shortcode');

// --- Contact Form 7 integration: Save submissions as Student Applications (prospects) ---
if (function_exists('add_action')) {
    // Only attach if CF7 is active
    if (defined('WPCF7_VERSION')) {
        add_action('wpcf7_mail_sent', 'nds_cf7_save_student_application');
    }
}

function nds_cf7_save_student_application($contact_form) {
    if (!class_exists('WPCF7_Submission')) return;
    $submission = WPCF7_Submission::get_instance();
    if (!$submission) return;

    // Target specific CF7 form only
    $form_title = method_exists($contact_form, 'title') ? (string) $contact_form->title() : '';
    if (strtolower(trim($form_title)) !== strtolower('Contact form 1')) {
        return;
    }

    $data = $submission->get_posted_data();
    if (empty($data) || !is_array($data)) return;

    global $wpdb;

    $full_name = isset($data['full_name']) ? trim(sanitize_text_field($data['full_name'])) : '';
    $first_name = $full_name;
    $last_name = '';
    if ($full_name !== '') {
        $parts = preg_split('/\s+/', $full_name);
        $first_name = array_shift($parts);
        $last_name = trim(implode(' ', $parts));
    }

    $email = isset($data['email']) ? sanitize_email($data['email']) : '';
    if ($email === '') return; // ignore invalid submissions

    $phone = isset($data['cell_no']) ? sanitize_text_field($data['cell_no']) : '';
    $national_id = isset($data['id_number']) ? sanitize_text_field($data['id_number']) : '';
    $date_of_birth = isset($data['date_of_birth']) ? sanitize_text_field($data['date_of_birth']) : '';
    $gender = isset($data['gender']) ? sanitize_text_field($data['gender']) : '';
    $address = isset($data['street_address']) ? sanitize_text_field($data['street_address']) : '';
    $city = isset($data['city']) ? sanitize_text_field($data['city']) : '';
    $country = 'South Africa';

    // Guardian/responsible party
    $guardian_name = isset($data['responsible_full_name']) ? sanitize_text_field($data['responsible_full_name']) : '';
    $guardian_phone = isset($data['responsible_phone']) ? sanitize_text_field($data['responsible_phone']) : '';
    $guardian_email = isset($data['responsible_email']) ? sanitize_email($data['responsible_email']) : '';

    // Emergency contact
    $emergency_contact_name = isset($data['emergency_full_name']) ? sanitize_text_field($data['emergency_full_name']) : '';
    $emergency_contact = isset($data['emergency_phone']) ? sanitize_text_field($data['emergency_phone']) : '';

    // Desired course (store in notes for now)
    $level = isset($data['level']) ? sanitize_text_field($data['level']) : '';
    $course = isset($data['course']) ? sanitize_text_field($data['course']) : '';
    $notes = '';
    if ($course !== '' || $level !== '') {
        $notes = trim('Desired Level: ' . $level . '; Desired Course: ' . $course);
    }

    // Determine intake
    $year = (int) date('Y');
    $m = (int) date('n');
    $semester = ($m <= 4) ? 'January' : (($m <= 8) ? 'June' : 'September');

    // If student with email exists, update basic info; else insert
    $exists_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}nds_students WHERE email = %s", $email));

    $row = [
        'faculty_id' => null,
        'first_name' => $first_name,
        'last_name' => $last_name,
        'email' => $email,
        'phone' => $phone,
        'national_id' => ($national_id === '' ? null : $national_id),
        'passport_number' => null,
        'date_of_birth' => $date_of_birth,
        'gender' => $gender,
        'address' => $address,
        'city' => $city,
        'country' => $country,
        'profile_photo' => '',
        'guardian_name' => ($guardian_name === '' ? null : $guardian_name),
        'guardian_phone' => ($guardian_phone === '' ? null : $guardian_phone),
        'guardian_email' => ($guardian_email === '' ? null : $guardian_email),
        'emergency_contact' => $emergency_contact,
        'emergency_contact_name' => $emergency_contact_name,
        'dietary_restrictions' => '',
        'medical_notes' => '',
        'highest_qualification' => '',
        'intake_year' => $year,
        'intake_semester' => $semester,
        'status' => 'prospect',
        'source' => 'web',
        'gdpr_consent' => 0,
        'notes' => $notes,
    ];

    if ($exists_id) {
        $wpdb->update($wpdb->prefix . 'nds_students', $row, ['id' => $exists_id]);
    } else {
        $row['student_number'] = nds_generate_student_number();
        $wpdb->insert($wpdb->prefix . 'nds_students', $row);
    }
}

// Get student count by status
function nds_get_student_count_by_status() {
    global $wpdb;
    
    return $wpdb->get_results(
        "SELECT status, COUNT(*) as count 
         FROM {$wpdb->prefix}nds_students 
         GROUP BY status"
    );
}

// Get student count by year level (enrollments)
function nds_get_student_count_by_year_level() {
    global $wpdb;
    
    return $wpdb->get_results(
        "SELECT sp.year_level, COUNT(DISTINCT sp.student_id) as count 
         FROM {$wpdb->prefix}nds_student_progression sp 
         WHERE sp.is_current = 1 
         GROUP BY sp.year_level 
         ORDER BY sp.year_level"
    );
}

// Log student activity
function nds_log_student_activity($student_id, $actor_id, $action, $action_type, $old_values = null, $new_values = null) {
    global $wpdb;
    // Skip if log table is missing (e.g., before migrations ran)
    $log_table = $wpdb->prefix . 'nds_student_activity_log';
    $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $log_table));
    if (empty($exists)) {
        return false;
    }

    return $wpdb->insert(
        $log_table,
        [
            'student_id' => $student_id,
            'actor_id' => $actor_id,
            'action' => $action,
            'action_type' => $action_type,
            'old_values' => $old_values ? json_encode($old_values) : null,
            'new_values' => $new_values ? json_encode($new_values) : null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ],
        ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s']
    );
}

// Delete student (soft delete)
function nds_delete_student($student_id) {
    global $wpdb;
    
    if (!current_user_can('manage_options')) {
        return false;
    }
    
    // Check if student has enrollments
    $enrollments = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}nds_student_enrollments WHERE student_id = %d",
        $student_id
    ));
    
    if ($enrollments > 0) {
        // Soft delete - change status to inactive
        $result = $wpdb->update(
            $wpdb->prefix . 'nds_students',
            ['status' => 'inactive'],
            ['id' => $student_id],
            ['%s'],
            ['%d']
        );
    } else {
        // Hard delete if no enrollments
        $result = $wpdb->delete(
            $wpdb->prefix . 'nds_students',
            ['id' => $student_id],
            ['%d']
        );
    }
    
    if ($result !== false) {
        nds_log_student_activity($student_id, get_current_user_id(), 'Student deleted', 'delete');
        return true;
    }
    
    return false;
}

// TEMP: Seed a dummy student for testing via admin action
function nds_seed_student_action() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    global $wpdb;

    $student_number = nds_generate_student_number();
    $unique = time();

    $data = [
        'student_number' => $student_number,
        'first_name' => 'Dummy',
        'last_name' => 'Student',
        'email' => 'dummy' . $unique . '@example.com',
        'phone' => '0123456789',
        'gender' => 'Male',
        'intake_year' => intval(date('Y')),
        'intake_semester' => 'January',
        'status' => 'active',
        'source' => 'admin',
        'gdpr_consent' => 1,
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql'),
    ];

    $inserted = $wpdb->insert(
        $wpdb->prefix . 'nds_students',
        $data,
        ['%s','%s','%s','%s','%s','%s','%d','%s','%s','%s','%s']
    );

    if ($inserted) {
        $student_id = $wpdb->insert_id;
        nds_log_student_activity($student_id, get_current_user_id(), 'Dummy student created', 'create', null, $data);
        wp_redirect(admin_url('admin.php?page=nds-all-learners&success=dummy_inserted'));
        exit;
    }

    wp_redirect(admin_url('admin.php?page=nds-all-learners&error=dummy_insert_failed'));
    exit;
}
add_action('admin_post_nds_seed_student', 'nds_seed_student_action');

// Bulk seed up to 100 students with realistic data
function nds_seed_students_action() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    @set_time_limit(0);

    $firstNamesMale = ['Liam','Noah','Oliver','Elijah','James','William','Benjamin','Lucas','Henry','Themba','Siyabonga','Thabo','Sipho','Kabelo','Kwame'];
    $firstNamesFemale = ['Olivia','Emma','Ava','Sophia','Amelia','Isabella','Mia','Evelyn','Harper','Naledi','Ayanda','Zanele','Thandi','Ama','Aisha'];
    $lastNames = ['Smith','Johnson','Williams','Brown','Jones','Garcia','Miller','Davis','Rodriguez','Khumalo','Mokoena','Naidoo','Pillay','Botha','van der Merwe','Nkosi','Dlamini'];
    $cities = ['Johannesburg','Cape Town','Durban','Pretoria','Gqeberha','Bloemfontein','Polokwane','Mbombela'];
    $countries = ['South Africa','Zimbabwe','Botswana','Namibia','Lesotho','Eswatini','Zambia'];
    $semesters = ['January','June','September'];
    $statuses = ['prospect','active','inactive'];

    $success = 0;
    $attempts = 0;
    $requested = isset($_GET['count']) ? intval($_GET['count']) : 100;
    $maxSuccess = max(1, min(100, $requested));
    $maxAttempts = $maxSuccess * 3; // keep going through failures, but cap to avoid infinite loops

    while ($success < $maxSuccess && $attempts < $maxAttempts) {
        $attempts++;
        $gender = (rand(0,1) === 1) ? 'Male' : 'Female';
        $first = $gender === 'Male' ? $firstNamesMale[array_rand($firstNamesMale)] : $firstNamesFemale[array_rand($firstNamesFemale)];
        $last = $lastNames[array_rand($lastNames)];
        $email = strtolower($first . '.' . $last . '.' . wp_generate_password(6,false,false)) . '@example.com';
        $phone = '+27 ' . rand(60,89) . ' ' . rand(100,999) . ' ' . rand(1000,9999);
        $city = $cities[array_rand($cities)];
        $country = $countries[array_rand($countries)];
        $status = $statuses[array_rand($statuses)];
        $intakeYear = (int)date('Y') - rand(0,4);
        $intakeSemester = $semesters[array_rand($semesters)];
        $dobYear = (int)date('Y') - rand(18,45);
        $dobMonth = rand(1,12);
        $dobDay = rand(1,28);
        $dob = sprintf('%04d-%02d-%02d', $dobYear, $dobMonth, $dobDay);

        $data = [
            'student_number' => nds_generate_student_number(),
            'first_name' => $first,
            'last_name' => $last,
            'email' => $email,
            'phone' => $phone,
            'national_id' => null,
            'passport_number' => null,
            'date_of_birth' => $dob,
            'gender' => $gender,
            'address' => $city . ' central',
            'city' => $city,
            'country' => $country,
            'profile_photo' => '',
            'guardian_name' => '',
            'guardian_phone' => '',
            'guardian_email' => '',
            'emergency_contact' => '',
            'emergency_contact_name' => '',
            'dietary_restrictions' => '',
            'medical_notes' => '',
            'highest_qualification' => 'Matric',
            'intake_year' => $intakeYear,
            'intake_semester' => $intakeSemester,
            'status' => $status,
            'source' => 'admin',
            'gdpr_consent' => 1,
            'notes' => '',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ];

        global $wpdb;
        $inserted = $wpdb->insert($wpdb->prefix . 'nds_students', $data, null);
        if ($inserted) {
            $success++;
            nds_log_student_activity($wpdb->insert_id, get_current_user_id(), 'Seeded student created', 'create', null, $data);
        } else {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[NDS] bulk seed failed: ' . $wpdb->last_error);
            }
            // continue
        }
    }

    wp_redirect(admin_url('admin.php?page=nds-all-learners&success=seeded_' . $success . '_students'));
    exit;
}
add_action('admin_post_nds_seed_students', 'nds_seed_students_action');

// Seed 30 students with random faculty and course assignments
function nds_seed_students_with_enrollments_action() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    @set_time_limit(0);

    global $wpdb;
    
    // Get all faculties
    $faculties = $wpdb->get_results("SELECT id FROM {$wpdb->prefix}nds_faculties ORDER BY id ASC", ARRAY_A);
    if (empty($faculties)) {
        wp_redirect(admin_url('admin.php?page=nds-all-learners&error=no_faculties'));
        exit;
    }
    
    // Get all active courses
    $courses = $wpdb->get_results("SELECT id, program_id FROM {$wpdb->prefix}nds_courses WHERE status = 'active' ORDER BY id ASC", ARRAY_A);
    if (empty($courses)) {
        wp_redirect(admin_url('admin.php?page=nds-all-learners&error=no_courses'));
        exit;
    }
    
    // Get or create active academic year
    $active_year_id = (int) $wpdb->get_var("SELECT id FROM {$wpdb->prefix}nds_academic_years WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
    if (!$active_year_id) {
        // Create a default academic year if none exists
        $current_year = date('Y');
        $wpdb->insert(
            $wpdb->prefix . 'nds_academic_years',
            [
                'year_name' => $current_year,
                'start_date' => $current_year . '-01-01',
                'end_date' => $current_year . '-12-31',
                'is_active' => 1
            ]
        );
        $active_year_id = $wpdb->insert_id;
    }
    
    // Get or create active semester
    $active_semester_id = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}nds_semesters WHERE academic_year_id = %d AND is_active = 1 ORDER BY id DESC LIMIT 1",
        $active_year_id
    ));
    if (!$active_semester_id) {
        // Create a default semester if none exists
        $wpdb->insert(
            $wpdb->prefix . 'nds_semesters',
            [
                'academic_year_id' => $active_year_id,
                'semester_name' => 'Semester 1',
                'start_date' => date('Y') . '-01-01',
                'end_date' => date('Y') . '-06-30',
                'is_active' => 1
            ]
        );
        $active_semester_id = $wpdb->insert_id;
    }

    $firstNamesMale = ['Liam','Noah','Oliver','Elijah','James','William','Benjamin','Lucas','Henry','Themba','Siyabonga','Thabo','Sipho','Kabelo','Kwame','David','Michael','John','Daniel','Matthew'];
    $firstNamesFemale = ['Olivia','Emma','Ava','Sophia','Amelia','Isabella','Mia','Evelyn','Harper','Naledi','Ayanda','Zanele','Thandi','Ama','Aisha','Sarah','Emily','Jessica','Ashley','Michelle'];
    $lastNames = ['Smith','Johnson','Williams','Brown','Jones','Garcia','Miller','Davis','Rodriguez','Khumalo','Mokoena','Naidoo','Pillay','Botha','van der Merwe','Nkosi','Dlamini','Mthembu','Zulu','Ndlovu'];
    $cities = ['Johannesburg','Cape Town','Durban','Pretoria','Gqeberha','Bloemfontein','Polokwane','Mbombela'];
    $countries = ['South Africa','Zimbabwe','Botswana','Namibia','Lesotho','Eswatini','Zambia'];
    $semesters = ['January','June','September'];
    $statuses = ['active']; // Only create active students

    $success = 0;
    $enrollments_created = 0;
    $attempts = 0;
    $maxStudents = 30;
    $maxAttempts = $maxStudents * 3;

    while ($success < $maxStudents && $attempts < $maxAttempts) {
        $attempts++;
        $gender = (rand(0,1) === 1) ? 'Male' : 'Female';
        $first = $gender === 'Male' ? $firstNamesMale[array_rand($firstNamesMale)] : $firstNamesFemale[array_rand($firstNamesFemale)];
        $last = $lastNames[array_rand($lastNames)];
        $email = strtolower($first . '.' . $last . '.' . wp_generate_password(6,false,false)) . '@example.com';
        $phone = '+27 ' . rand(60,89) . ' ' . rand(100,999) . ' ' . rand(1000,9999);
        $city = $cities[array_rand($cities)];
        $country = $countries[array_rand($countries)];
        $status = $statuses[array_rand($statuses)];
        $intakeYear = (int)date('Y');
        $intakeSemester = $semesters[array_rand($semesters)];
        $dobYear = (int)date('Y') - rand(18,35);
        $dobMonth = rand(1,12);
        $dobDay = rand(1,28);
        $dob = sprintf('%04d-%02d-%02d', $dobYear, $dobMonth, $dobDay);
        
        // Random faculty assignment
        $random_faculty = $faculties[array_rand($faculties)];
        $faculty_id = (int) $random_faculty['id'];

        $data = [
            'student_number' => nds_generate_student_number(),
            'faculty_id' => $faculty_id,
            'first_name' => $first,
            'last_name' => $last,
            'email' => $email,
            'phone' => $phone,
            'national_id' => null,
            'passport_number' => null,
            'date_of_birth' => $dob,
            'gender' => $gender,
            'address' => $city . ' central',
            'city' => $city,
            'country' => $country,
            'profile_photo' => '',
            'guardian_name' => '',
            'guardian_phone' => '',
            'guardian_email' => '',
            'emergency_contact' => '',
            'emergency_contact_name' => '',
            'dietary_restrictions' => '',
            'medical_notes' => '',
            'highest_qualification' => 'Matric',
            'intake_year' => $intakeYear,
            'intake_semester' => $intakeSemester,
            'status' => $status,
            'source' => 'admin',
            'gdpr_consent' => 1,
            'notes' => 'Seeded student with enrollment',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ];

        $inserted = $wpdb->insert($wpdb->prefix . 'nds_students', $data, null);
        if ($inserted) {
            $student_id = $wpdb->insert_id;
            $success++;
            
            // Log activity
            nds_log_student_activity($student_id, get_current_user_id(), 'Seeded student created with enrollment', 'create', null, $data);
            
            // Enroll in a random course
            $random_course = $courses[array_rand($courses)];
            $course_id = (int) $random_course['id'];
            
            // Check if already enrolled in this course for this term
            $existing_enrollment = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}nds_student_enrollments 
                 WHERE student_id = %d AND course_id = %d AND academic_year_id = %d AND semester_id = %d",
                $student_id, $course_id, $active_year_id, $active_semester_id
            ));
            
            if (!$existing_enrollment) {
                // Create enrollment
                $enrollment_inserted = $wpdb->insert(
                    $wpdb->prefix . 'nds_student_enrollments',
                    [
                        'student_id' => $student_id,
                        'course_id' => $course_id,
                        'academic_year_id' => $active_year_id,
                        'semester_id' => $active_semester_id,
                        'enrollment_date' => current_time('Y-m-d'),
                        'status' => 'enrolled',
                        'created_at' => current_time('mysql')
                    ],
                    ['%d', '%d', '%d', '%d', '%s', '%s', '%s']
                );
                
                if ($enrollment_inserted) {
                    $enrollments_created++;
                }
            }
        } else {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[NDS] bulk seed failed: ' . $wpdb->last_error);
            }
        }
    }

    wp_redirect(admin_url('admin.php?page=nds-all-learners&success=seeded_' . $success . '_students_with_' . $enrollments_created . '_enrollments'));
    exit;
}
add_action('admin_post_nds_seed_students_with_enrollments', 'nds_seed_students_with_enrollments_action');
