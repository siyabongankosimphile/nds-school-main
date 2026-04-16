<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Application form table is now created and maintained centrally in `includes/database.php`

/**
 * Handle Application Form Submission
 */
function nds_handle_application_form_submission() {
    // Verify nonce
    if (!isset($_POST['nds_application_nonce']) || !wp_verify_nonce($_POST['nds_application_nonce'], 'nds_application_form')) {
        wp_die('Security check failed');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'nds_application_forms';

    // ---------------------------------------------------------------------
    // Safety net: ensure core application tables exist before inserting
    // This auto-heals installs where migrations/activation didn't fully run.
    // ---------------------------------------------------------------------
    $applications_table = $wpdb->prefix . 'nds_applications';
    $forms_table = $table_name;

    $apps_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $applications_table));
    $forms_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $forms_table));

    if (empty($apps_exists) || empty($forms_exists)) {
        // Run main table creator
        $plugin_dir = plugin_dir_path(__FILE__);
        $plugin_dir = dirname($plugin_dir); // from includes/ to plugin root
        require_once $plugin_dir . '/includes/database.php';
        if (function_exists('nds_school_create_tables')) {
            nds_school_create_tables();
        }
        // Re-check after migration
        $apps_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $applications_table));
        $forms_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $forms_table));

        // Final defensive fallback: create the two core tables directly if still missing.
        if (empty($apps_exists) || empty($forms_exists)) {
            $charset_collate = $wpdb->get_charset_collate();

            if (empty($forms_exists)) {
                $sql_forms = "CREATE TABLE IF NOT EXISTS {$forms_table} (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    application_id INT NULL,
                    level VARCHAR(50) NOT NULL,
                    course_id INT NOT NULL,
                    course_name VARCHAR(255) NOT NULL,
                    full_name VARCHAR(255) NOT NULL,
                    id_number VARCHAR(50) NOT NULL,
                    date_of_birth DATE NOT NULL,
                    gender VARCHAR(20) NOT NULL,
                    nationality VARCHAR(100) NOT NULL,
                    country_of_birth VARCHAR(100) NOT NULL,
                    marital_status VARCHAR(50) NOT NULL,
                    street_address TEXT NOT NULL,
                    city VARCHAR(100) NOT NULL,
                    postal_code VARCHAR(20) NOT NULL,
                    province VARCHAR(100) NOT NULL,
                    cell_no VARCHAR(20) NOT NULL,
                    email VARCHAR(255) NOT NULL,
                    responsible_full_name VARCHAR(255) NOT NULL,
                    relationship VARCHAR(100),
                    responsible_id_number VARCHAR(50) NOT NULL,
                    responsible_phone VARCHAR(20) NOT NULL,
                    responsible_email VARCHAR(255) NOT NULL,
                    responsible_street_address TEXT,
                    responsible_city VARCHAR(100),
                    responsible_postal_code VARCHAR(20),
                    responsible_province VARCHAR(100),
                    occupation VARCHAR(255) NOT NULL,
                    company_name VARCHAR(255),
                    work_telephone VARCHAR(20),
                    work_email VARCHAR(255),
                    emergency_full_name VARCHAR(255) NOT NULL,
                    emergency_relationship VARCHAR(100),
                    emergency_phone VARCHAR(20) NOT NULL,
                    emergency_email VARCHAR(255) NOT NULL,
                    emergency_street_address TEXT,
                    emergency_city VARCHAR(100),
                    emergency_postal_code VARCHAR(20),
                    emergency_province VARCHAR(100),
                    highest_grade VARCHAR(50) NOT NULL,
                    year_passed VARCHAR(10) NOT NULL,
                    school_attended VARCHAR(255) NOT NULL,
                    school_location VARCHAR(255),
                    other_qualifications TEXT,
                    year_completion VARCHAR(10),
                    home_language VARCHAR(100),
                    english_write VARCHAR(20),
                    english_read VARCHAR(20),
                    english_speak VARCHAR(20),
                    other_language VARCHAR(100),
                    other_language_write VARCHAR(20),
                    other_language_read VARCHAR(20),
                    other_language_speak VARCHAR(20),
                    physical_illness VARCHAR(10),
                    specify_physical_illness TEXT,
                    food_allergies VARCHAR(10),
                    specify_food_allergies TEXT,
                    chronic_medication VARCHAR(10),
                    specify_chronic_medication TEXT,
                    pregnant_or_planning VARCHAR(10),
                    smoke VARCHAR(10),
                    id_passport_applicant VARCHAR(500),
                    id_passport_responsible VARCHAR(500),
                    saqa_certificate VARCHAR(500),
                    study_permit VARCHAR(500),
                    parent_spouse_id VARCHAR(500),
                    latest_results VARCHAR(500),
                    proof_residence VARCHAR(500),
                    highest_grade_cert VARCHAR(500),
                    proof_medical_aid VARCHAR(500),
                    declaration BOOLEAN DEFAULT 0,
                    motivation_letter TEXT NOT NULL,
                    status ENUM('pending', 'reviewed', 'accepted', 'rejected', 'enrolled') DEFAULT 'pending',
                    reviewed_by INT NULL,
                    reviewed_at TIMESTAMP NULL,
                    notes TEXT,
                    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    KEY idx_email (email),
                    KEY idx_status (status),
                    KEY idx_course_id (course_id),
                    KEY idx_submitted_at (submitted_at)
                ) {$charset_collate};";
                $wpdb->query($sql_forms);
            }

            if (empty($apps_exists)) {
                $sql_apps = "CREATE TABLE IF NOT EXISTS {$applications_table} (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    application_no VARCHAR(30) NOT NULL UNIQUE,
                    wp_user_id BIGINT(20) UNSIGNED NULL,
                    student_id INT NULL,
                    program_id INT NULL,
                    course_id INT NULL,
                    academic_year_id INT NULL,
                    semester_id INT NULL,
                    intake_term ENUM('January','June','September') NULL,
                    source ENUM('web','admin','import','referral') DEFAULT 'web',
                    status ENUM(
                        'draft','submitted','under_review','waitlisted',
                        'conditional_offer','offer_made','accepted',
                        'declined','withdrawn','rejected','expired'
                    ) DEFAULT 'draft',
                    submitted_at DATETIME NULL,
                    decision_at DATETIME NULL,
                    decided_by INT NULL,
                    notes TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) {$charset_collate};";
                $wpdb->query($sql_apps);
            }

            // Final check
            $apps_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $applications_table));
            $forms_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $forms_table));

            if (empty($apps_exists) || empty($forms_exists)) {
                error_log('NDS Application Error: failed to create core application tables. wpdb error: ' . $wpdb->last_error);
                wp_die('Failed to submit application: core application tables could not be created.');
            }
        }
    }

    // Development mode: Use dummy PDF for all uploads
    // Enable by: 1) Setting WP_DEBUG=true in wp-config.php, OR
    //           2) Adding define('NDS_DEV_MODE', true); in wp-config.php, OR  
    //           3) If on localhost (nds.local), auto-enable if file exists
    $is_localhost = (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || strpos($_SERVER['HTTP_HOST'], '.local') !== false);
    $dev_mode = (defined('WP_DEBUG') && WP_DEBUG) || (defined('NDS_DEV_MODE') && NDS_DEV_MODE) || $is_localhost;
    $dummy_upload_pdf = '/Users/thandohlophe/Desktop/7. Tender Documents/Hardware Supply Proposal/Biding (17:2021) for supply and deliver – laptops and desktops.pdf';
    
    // Remove quotes from path if present and clean up path
    $dummy_upload_pdf = trim($dummy_upload_pdf, '"\'');
    
    // Check if dummy file exists
    if (!file_exists($dummy_upload_pdf)) {
        error_log('NDS DEV MODE: Dummy PDF file not found at: ' . $dummy_upload_pdf);
        $dev_mode = false;
    }
    
    // Debug logging
    if ($dev_mode) {
        error_log('NDS DEV MODE: Active - Using dummy PDF for all uploads');
    }
    
    // Store uploaded files temporarily (we'll move them to student folder after student is created)
    $plugin_dir = plugin_dir_path(__FILE__);
    $plugin_dir = dirname($plugin_dir); // Go up from includes/ to plugin root
    $temp_upload_dir = $plugin_dir . '/public/temp-uploads/';
    
    if (!file_exists($temp_upload_dir)) {
        wp_mkdir_p($temp_upload_dir);
    }

    $file_fields = [
        'id_passport_applicant',
        'id_passport_responsible',
        'saqa_certificate',
        'study_permit',
        'parent_spouse_id',
        'latest_results',
        'proof_residence',
        'highest_grade_cert',
        'proof_medical_aid'
    ];

    // Store temp file paths - we'll move them to student folder later
    $temp_uploaded_files = [];
    
    if ($dev_mode && file_exists($dummy_upload_pdf)) {
        // Development mode: Use dummy PDF for all file fields
        foreach ($file_fields as $field) {
            $unique_filename = $field . '_' . time() . '_' . uniqid() . '.pdf';
            $temp_file_path = $temp_upload_dir . $unique_filename;
            
            // Copy dummy PDF to temp location
            if (copy($dummy_upload_pdf, $temp_file_path)) {
                $temp_uploaded_files[$field] = [
                    'temp_path' => $temp_file_path,
                    'original_name' => pathinfo($dummy_upload_pdf, PATHINFO_FILENAME),
                    'unique_name' => $unique_filename
                ];
            }
        }
    } else {
        // Production mode: Process actual file uploads
        foreach ($file_fields as $field) {
            if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES[$field];
                $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                
                // Validate file type
                if ($file_ext !== 'pdf') {
                    continue;
                }
                
                // Generate unique filename for temp storage
                $filename = sanitize_file_name(pathinfo($file['name'], PATHINFO_FILENAME));
                $unique_filename = $field . '_' . time() . '_' . uniqid() . '.pdf';
                $temp_file_path = $temp_upload_dir . $unique_filename;
                
                if (move_uploaded_file($file['tmp_name'], $temp_file_path)) {
                    $temp_uploaded_files[$field] = [
                        'temp_path' => $temp_file_path,
                        'original_name' => $filename,
                        'unique_name' => $unique_filename
                    ];
                }
            }
        }
    }

    // Prepare data for insertion
    $data = [
        // Course Selection
        'level' => isset($_POST['level']) ? sanitize_text_field($_POST['level']) : '',
        'course_id' => intval($_POST['course_id']),
        'course_name' => sanitize_text_field($_POST['course_name']),
        
        // Personal Details
        'full_name' => sanitize_text_field($_POST['full_name']),
        'id_number' => sanitize_text_field($_POST['id_number']),
        'date_of_birth' => sanitize_text_field($_POST['date_of_birth']),
        'gender' => sanitize_text_field($_POST['gender']),
        // Clip nationality to DB column size (VARCHAR(100)) to avoid strict-mode errors
        'nationality' => isset($_POST['nationality'])
            ? mb_substr(sanitize_text_field($_POST['nationality']), 0, 100)
            : '',
        'country_of_birth' => sanitize_text_field($_POST['country_of_birth']),
        'marital_status' => sanitize_text_field($_POST['marital_status']),
        
        // Address
        'street_address' => sanitize_textarea_field($_POST['street_address']),
        'city' => sanitize_text_field($_POST['city']),
        'postal_code' => sanitize_text_field($_POST['postal_code']),
        'province' => sanitize_text_field($_POST['province']),
        
        // Contact
        'cell_no' => sanitize_text_field($_POST['cell_no']),
        'email' => sanitize_email($_POST['email']),
        
        // Person Responsible for Fees
        'responsible_full_name' => sanitize_text_field($_POST['responsible_full_name']),
        'relationship' => sanitize_text_field($_POST['relationship'] ?? ''),
        'responsible_id_number' => sanitize_text_field($_POST['responsible_id_number']),
        'responsible_phone' => sanitize_text_field($_POST['responsible_phone']),
        'responsible_email' => sanitize_email($_POST['responsible_email']),
        'responsible_street_address' => sanitize_textarea_field($_POST['responsible_street_address'] ?? ''),
        'responsible_city' => sanitize_text_field($_POST['responsible_city'] ?? ''),
        'responsible_postal_code' => sanitize_text_field($_POST['responsible_postal_code'] ?? ''),
        'responsible_province' => sanitize_text_field($_POST['responsible_province'] ?? ''),
        'occupation' => sanitize_text_field($_POST['occupation']),
        'company_name' => sanitize_text_field($_POST['company_name'] ?? ''),
        'work_telephone' => sanitize_text_field($_POST['work_telephone'] ?? ''),
        'work_email' => sanitize_email($_POST['work_email'] ?? ''),
        
        // Emergency Contact
        'emergency_full_name' => sanitize_text_field($_POST['emergency_full_name']),
        'emergency_relationship' => sanitize_text_field($_POST['emergency_relationship'] ?? ''),
        'emergency_phone' => sanitize_text_field($_POST['emergency_phone']),
        'emergency_email' => sanitize_email($_POST['emergency_email']),
        'emergency_street_address' => sanitize_textarea_field($_POST['emergency_street_address'] ?? ''),
        'emergency_city' => sanitize_text_field($_POST['emergency_city'] ?? ''),
        'emergency_postal_code' => sanitize_text_field($_POST['emergency_postal_code'] ?? ''),
        'emergency_province' => sanitize_text_field($_POST['emergency_province'] ?? ''),
        
        // Educational Background
        'highest_grade' => sanitize_text_field($_POST['highest_grade']),
        'year_passed' => sanitize_text_field($_POST['year_passed']),
        'school_attended' => sanitize_text_field($_POST['school_attended']),
        'school_location' => sanitize_text_field($_POST['school_location'] ?? ''),
        'other_qualifications' => sanitize_text_field($_POST['other_qualifications'] ?? ''),
        'year_completion' => sanitize_text_field($_POST['year_completion'] ?? ''),
        
        // Languages
        'home_language' => sanitize_text_field($_POST['home_language'] ?? ''),
        'english_write' => sanitize_text_field($_POST['english_write'] ?? 'Good'),
        'english_read' => sanitize_text_field($_POST['english_read'] ?? 'Good'),
        'english_speak' => sanitize_text_field($_POST['english_speak'] ?? 'Good'),
        'other_language' => sanitize_text_field($_POST['other_language'] ?? ''),
        'other_language_write' => sanitize_text_field($_POST['other_language_write'] ?? 'Good'),
        'other_language_read' => sanitize_text_field($_POST['other_language_read'] ?? 'Good'),
        'other_language_speak' => sanitize_text_field($_POST['other_language_speak'] ?? 'Good'),
        
        // Medical Questions
        'physical_illness' => sanitize_text_field($_POST['physical_illness'] ?? 'Yes'),
        'specify_physical_illness' => sanitize_textarea_field($_POST['specify_physical_illness'] ?? ''),
        'food_allergies' => sanitize_text_field($_POST['food_allergies'] ?? 'Yes'),
        'specify_food_allergies' => sanitize_textarea_field($_POST['specify_food_allergies'] ?? ''),
        'chronic_medication' => sanitize_text_field($_POST['chronic_medication'] ?? 'Yes'),
        'specify_chronic_medication' => sanitize_textarea_field($_POST['specify_chronic_medication'] ?? ''),
        'pregnant_or_planning' => sanitize_text_field($_POST['pregnant_or_planning'] ?? 'Yes'),
        'smoke' => sanitize_text_field($_POST['smoke'] ?? 'Yes'),
        
        // Supporting Documents (will be updated after files are moved to student folder)
        'id_passport_applicant' => '',
        'id_passport_responsible' => '',
        'saqa_certificate' => '',
        'study_permit' => '',
        'parent_spouse_id' => '',
        'latest_results' => '',
        'proof_residence' => '',
        'highest_grade_cert' => '',
        'proof_medical_aid' => '',
        
        // Declaration & Motivation
        'declaration' => isset($_POST['declaration']) ? 1 : 0,
        'motivation_letter' => sanitize_textarea_field($_POST['motivation_letter']),
        
        'status' => 'pending'
    ];

    // Start transaction for data integrity
    $wpdb->query('START TRANSACTION');
    
    try {
        // Insert into application_forms (detailed data)
        $inserted = $wpdb->insert($table_name, $data);
        
        if (!$inserted) {
            // Log the underlying DB error for troubleshooting
            if (function_exists('nds_log_wpdb_error')) {
                nds_log_wpdb_error('insert appslication form', $wpdb->last_query ?? null);
            }
            throw new Exception('Failed to insert applicateion form data: ' . $wpdb->last_error);
        }
        
        $application_form_id = $wpdb->insert_id;
        
        // Generate application number
        $application_no = 'APP-' . date('Y') . '-' . str_pad($application_form_id, 6, '0', STR_PAD_LEFT);
        
        // Insert into applications (workflow tracking)
        // Infer program_id and faculty_id from course_id for data integrity
        $course_info = $wpdb->get_row($wpdb->prepare(
            "SELECT c.program_id, p.faculty_id 
             FROM {$wpdb->prefix}nds_courses c
             JOIN {$wpdb->prefix}nds_programs p ON c.program_id = p.id
             WHERE c.id = %d",
            $data['course_id']
        ), ARRAY_A);

        $program_id = $course_info ? $course_info['program_id'] : null;
        $faculty_id = $course_info ? $course_info['faculty_id'] : null;

        $application_data = [
            'application_no' => $application_no,
            'wp_user_id' => get_current_user_id() ?: null,
            'student_id' => null, // Will be set when student is created
            'program_id' => $program_id,
            'course_id' => $data['course_id'],
            'academic_year_id' => null, // Can be set based on current year
            'semester_id' => null, // Can be set based on current semester
            'intake_term' => null, // Can be set based on current date
            'source' => 'web',
            'status' => 'submitted',
            'submitted_at' => current_time('mysql'),
            'notes' => 'Application submitted via online form',
            'created_at' => current_time('mysql')
        ];
        
        // Validate that selected course exists to avoid foreign key errors
        if (!empty($application_data['course_id'])) {
            $course_exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}nds_courses WHERE id = %d",
                $application_data['course_id']
            ));
            if (!$course_exists) {
                // If course no longer exists, store NULL so insert won’t violate FK
                $application_data['course_id'] = null;
                $application_data['program_id'] = null;
            }
        }
        
        $application_inserted = $wpdb->insert($wpdb->prefix . 'nds_applications', $application_data);
        
        if (!$application_inserted) {
            if (function_exists('nds_log_wpdb_error')) {
                nds_log_wpdb_error('insert application tracking', $wpdb->last_query ?? null);
            }
            throw new Exception('Failed to insert application tracking data: ' . $wpdb->last_error);
        }
        
        $application_id = $wpdb->insert_id;
        
        // Update application_forms with reference to applications table
        $wpdb->update(
            $table_name,
            ['application_id' => $application_id],
            ['id' => $application_form_id]
        );
        
        // Create or update student record
        $full_name_parts = explode(' ', trim($data['full_name']), 2);
        $first_name = $full_name_parts[0];
        $last_name = isset($full_name_parts[1]) ? $full_name_parts[1] : '';
        
        // Check if student already exists by email
        $existing_student = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}nds_students WHERE email = %s",
            $data['email']
        ), ARRAY_A);
        
        if ($existing_student) {
            // Student exists, update the application and student with student_id and faculty_id
            $student_id = $existing_student['id'];
            $wpdb->update(
                $wpdb->prefix . 'nds_applications',
                ['student_id' => $student_id],
                ['id' => $application_id]
            );
            
            // Optionally update student's faculty_id if it's currently NULL
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}nds_students 
                 SET faculty_id = COALESCE(faculty_id, %d) 
                 WHERE id = %d",
                $faculty_id, $student_id
            ));
            
            // Get student name for folder and file naming
            $student_info = $wpdb->get_row($wpdb->prepare(
                "SELECT first_name, last_name FROM {$wpdb->prefix}nds_students WHERE id = %d",
                $student_id
            ), ARRAY_A);
            if ($student_info) {
                $first_name = $student_info['first_name'];
                $last_name = $student_info['last_name'];
                $student_full_name = trim($first_name . ' ' . $last_name);
            } else {
                $student_full_name = $data['full_name'];
            }
        } else {
            // Create new student record as prospect
            // Generate student number (inline function if not available)
            if (!function_exists('nds_generate_student_number')) {
                // Inline student number generation
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
                $student_number = $prefix . str_pad($sequence, 4, '0', STR_PAD_LEFT);
            } else {
                $student_number = nds_generate_student_number();
            }
            
            $student_data = [
                'student_number' => $student_number,
                'wp_user_id' => get_current_user_id() ?: null,
                'faculty_id' => $faculty_id,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $data['email'],
                'phone' => $data['cell_no'],
                'date_of_birth' => $data['date_of_birth'] ?: null,
                'gender' => $data['gender'],
                'address' => $data['street_address'],
                'city' => $data['city'],
                'country' => $data['country_of_birth'] ?: 'South Africa',
                'status' => 'prospect',
                'created_at' => current_time('mysql')
            ];
            
            $student_inserted = $wpdb->insert($wpdb->prefix . 'nds_students', $student_data);
            
            if (!$student_inserted) {
                throw new Exception('Failed to create student record: ' . $wpdb->last_error);
            }
            
            $student_id = $wpdb->insert_id;
            
            // Update application with student_id
            $wpdb->update(
                $wpdb->prefix . 'nds_applications',
                ['student_id' => $student_id],
                ['id' => $application_id]
            );
            
            // Use form data for folder name
            $student_full_name = $data['full_name'];
        }
        
        // Create folder structure in plugin's public folder
        // Structure: /public/Students/{Year}/{(unique_id)student_name}/
        $plugin_dir = plugin_dir_path(__FILE__);
        $plugin_dir = dirname($plugin_dir); // Go up from includes/ to plugin root
        $current_year = date('Y');
        
        // Create student folder: /public/Students/{Year}/{(unique_id)student_name}/
        $student_folder_name = $student_id . '_' . sanitize_file_name(str_replace(' ', '-', strtolower($student_full_name)));
        $student_base_dir = $plugin_dir . '/public/Students/' . $current_year . '/';
        $student_upload_dir = $student_base_dir . $student_folder_name . '/';
        
        if (!file_exists($student_upload_dir)) {
            wp_mkdir_p($student_upload_dir);
        }
        
        // Create applicant folder: /public/Applicants/{Year}/{(unique_id)applicant_name}/
        $applicant_folder_name = $application_id . '_' . sanitize_file_name(str_replace(' ', '-', strtolower($data['full_name'])));
        $applicant_base_dir = $plugin_dir . '/public/Applicants/' . $current_year . '/';
        $applicant_upload_dir = $applicant_base_dir . $applicant_folder_name . '/';
        
        if (!file_exists($applicant_upload_dir)) {
            wp_mkdir_p($applicant_upload_dir);
        }
        
        // Move files from temp to student folder and update paths
        $final_file_paths = [];
        
        // Get first 3 characters of name and surname for file naming
        // Pad with 'x' if name is shorter than 3 characters
        $name_prefix = strtolower(substr($first_name . 'xxx', 0, 3));
        $surname_prefix = strtolower(substr($last_name . 'xxx', 0, 3));
        $id_prefix = $student_id; // Use student_id for file naming
        
        foreach ($temp_uploaded_files as $field => $file_info) {
            // Rename to: {field_name}_{id}_{first3chars_name}_{first3chars_surname}.pdf
            $final_filename = $field . '_' . $id_prefix . '_' . $name_prefix . '_' . $surname_prefix . '.pdf';
            
            // Copy file to both student and applicant folders
            $student_file_path = $student_upload_dir . $final_filename;
            $applicant_file_path = $applicant_upload_dir . $final_filename;
            
            // Copy to student folder
            if (file_exists($file_info['temp_path'])) {
                if (copy($file_info['temp_path'], $student_file_path)) {
                    // Also copy to applicant folder
                    copy($file_info['temp_path'], $applicant_file_path);
                    
                    // Store relative path for database (using applicant folder path)
                    $final_file_paths[$field] = 'Applicants/' . $current_year . '/' . $applicant_folder_name . '/' . $final_filename;
                    
                    // Clean up temp file
                    unlink($file_info['temp_path']);
                }
            }
        }
        
        // Clean up temp directory if empty
        if (is_dir($temp_upload_dir) && count(glob($temp_upload_dir . '*')) === 0) {
            @rmdir($temp_upload_dir);
        }
        
        // Update application_forms with final file paths
        if (!empty($final_file_paths)) {
            $wpdb->update(
                $table_name,
                $final_file_paths,
                ['id' => $application_form_id]
            );
        }
        
        // Commit transaction
        $wpdb->query('COMMIT');
        
        // Send notification email
        $admin_email = get_option('admin_email');
        $subject = 'New Application Submitted - ' . $data['full_name'];
        $message = "A new application has been submitted.\n\n";
        $message .= "Applicant: " . $data['full_name'] . "\n";
        $message .= "Email: " . $data['email'] . "\n";
        $message .= "Qualification: " . $data['course_name'] . "\n";
        $message .= "Application Number: " . $application_no . "\n";
        $message .= "Application ID: " . $application_id . "\n\n";
        $message .= "View application: " . admin_url('admin.php?page=nds-applications&id=' . $application_id);
        
        wp_mail($admin_email, $subject, $message);
        
        // Return success data (for AJAX) or redirect (for admin-post fallback)
        if (defined('DOING_AJAX') && DOING_AJAX) {
            wp_send_json_success(array(
                'application_id' => $application_id,
                'application_no' => $application_no,
                'redirect_url' => add_query_arg(
                    array(
                        'application' => 'success',
                        'id' => $application_id,
                    ),
                    home_url('/portal/')
                )
            ));
        } else {
            // Redirect learner to the student portal dashboard with their application ID
            $portal_url = home_url('/portal/');
            $redirect_url = add_query_arg(
                array(
                    'application' => 'success',
                    'id'          => $application_id,
                ),
                $portal_url
            );
            wp_redirect($redirect_url);
            exit;
        }
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $wpdb->query('ROLLBACK');

        if (defined('DOING_AJAX') && DOING_AJAX) {
            wp_send_json_error(array('message' => 'Failed to submit application: ' . $e->getMessage()));
        } else {
            wp_die('Failed to submit application: ' . $e->getMessage());
        }
    }
}

// Register AJAX handlers (PRIMARY METHOD - more reliable than admin-post)
add_action('wp_ajax_nds_submit_application', 'nds_handle_application_form_submission');
add_action('wp_ajax_nopriv_nds_submit_application', 'nds_handle_application_form_submission');

// Register admin-post handlers (FALLBACK - for backward compatibility)
add_action('admin_post_nds_application_form_submission', 'nds_handle_application_form_submission', 1);
add_action('admin_post_nopriv_nds_application_form_submission', 'nds_handle_application_form_submission', 1);

// Legacy function nds_handle_application_success() removed in favour of an in-portal
// success modal rendered by the learner dashboard template.

/**
 * Get file path from relative path stored in database
 * @param string $relative_path Relative path like 'Applicants/2025/123_john-doe/file.pdf'
 * @return string Full file system path
 */
function nds_get_application_file_path($relative_path) {
    if (empty($relative_path)) {
        return '';
    }
    
    $plugin_dir = plugin_dir_path(dirname(__FILE__));
    return $plugin_dir . 'public/' . $relative_path;
}

/**
 * Move an application's uploaded files from Applicants/ to Students/
 * once they are accepted, and update the stored paths.
 *
 * This keeps the Applicants area focused on in-flight applications only.
 *
 * @param int $application_id
 * @return void
 */
function nds_move_application_files_to_student($application_id) {
    global $wpdb;

    $application_id = (int) $application_id;
    if ($application_id <= 0) {
        return;
    }

    // Load application, linked form and student
    $application = $wpdb->get_row($wpdb->prepare("
        SELECT 
            a.id,
            a.student_id,
            a.submitted_at,
            af.id   AS form_id,
            af.full_name,
            af.id_passport_applicant,
            af.id_passport_responsible,
            af.saqa_certificate,
            af.study_permit,
            af.parent_spouse_id,
            af.latest_results,
            af.proof_residence,
            af.highest_grade_cert,
            af.proof_medical_aid,
            s.first_name,
            s.last_name
        FROM {$wpdb->prefix}nds_applications a
        LEFT JOIN {$wpdb->prefix}nds_application_forms af ON af.application_id = a.id
        LEFT JOIN {$wpdb->prefix}nds_students s ON s.id = a.student_id
        WHERE a.id = %d
    ", $application_id), ARRAY_A);

    if (!$application || empty($application['form_id']) || empty($application['student_id'])) {
        // Nothing to move or no student yet
        return;
    }

    $form_id   = (int) $application['form_id'];
    $student_id = (int) $application['student_id'];

    // Determine year folder from submitted_at (fallback to current year)
    $year = !empty($application['submitted_at'])
        ? date('Y', strtotime($application['submitted_at']))
        : date('Y');

    // Build student folder name using student record (fallback to form full_name)
    $first_name = !empty($application['first_name']) ? $application['first_name'] : '';
    $last_name  = !empty($application['last_name']) ? $application['last_name'] : '';
    $student_full_name = trim($first_name . ' ' . $last_name);
    if ($student_full_name === '' && !empty($application['full_name'])) {
        $student_full_name = $application['full_name'];
    }

    if ($student_full_name === '') {
        return;
    }

    $student_folder_name = $student_id . '_' . sanitize_file_name(str_replace(' ', '-', strtolower($student_full_name)));

    $plugin_dir      = plugin_dir_path(dirname(__FILE__));
    $student_baseDir = $plugin_dir . 'public/Students/' . $year . '/';
    $student_dir     = $student_baseDir . $student_folder_name . '/';

    if (!file_exists($student_dir)) {
        wp_mkdir_p($student_dir);
    }

    $file_fields = [
        'id_passport_applicant',
        'id_passport_responsible',
        'saqa_certificate',
        'study_permit',
        'parent_spouse_id',
        'latest_results',
        'proof_residence',
        'highest_grade_cert',
        'proof_medical_aid',
    ];

    $updates = [];

    foreach ($file_fields as $field) {
        if (empty($application[$field])) {
            continue;
        }

        $relative_path = $application[$field];

        // If it's already under Students/, nothing to do
        if (strpos($relative_path, 'Students/') === 0) {
            continue;
        }

        $source_path = nds_get_application_file_path($relative_path);
        if (!file_exists($source_path)) {
            continue;
        }

        $filename        = basename($relative_path);
        $dest_relative   = 'Students/' . $year . '/' . $student_folder_name . '/' . $filename;
        $dest_path       = $plugin_dir . 'public/' . $dest_relative;

        // Ensure destination directory exists (defensive)
        $dest_dir = dirname($dest_path);
        if (!file_exists($dest_dir)) {
            wp_mkdir_p($dest_dir);
        }

        if (@rename($source_path, $dest_path) || @copy($source_path, $dest_path)) {
            // If we copied, try to remove original
            if (file_exists($source_path)) {
                @unlink($source_path);
            }
            $updates[$field] = $dest_relative;
        }
    }

    if (!empty($updates)) {
        $wpdb->update(
            $wpdb->prefix . 'nds_application_forms',
            $updates,
            ['id' => $form_id]
        );
    }
}
