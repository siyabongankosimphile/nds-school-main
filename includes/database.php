<?php

/**
 * NDS School Database Schema - University Structure
 * Clean, normalized database structure for Faculties, Programs, and Courses.
 *
 * TABLE SUMMARY & REPORTING LINKS
 * - Academic year / semester: NOT on students; link via nds_student_enrollments (academic_year_id, semester_id)
 *   and nds_student_cohorts → nds_cohorts (academic_year_id, semester_id).
 * - course_prerequisites: table only; no enrollment-time "must pass X before Y" logic in app.
 * - course_accreditations: M2M course ↔ accreditation body; used for data/display, not blocking logic.
 * - nds_rooms: structured venues; nds_course_schedules has room_id (FK) + optional location (text).
 * - program_types: used by nds_programs.program_type_id (Diploma, Short course, etc.).
 * - program_levels: used by nds_courses.level_id (Year 1, Year 2 within a program).
 * - Student start/end: intake_year, intake_semester (start); expected_completion_year, actual_completion_year (end).
 * See DATABASE_TABLES_EXPLAINED.md for full table descriptions and example reporting queries.
 */
function nds_school_create_tables()
{
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    // -------------------------------------------------------------------------
    // TABLE NAMES
    // -------------------------------------------------------------------------
    $t_faculties                = $wpdb->prefix . 'nds_faculties';
    $t_program_types            = $wpdb->prefix . 'nds_program_types';
    $t_accreditation_bodies     = $wpdb->prefix . 'nds_accreditation_bodies';
    $t_programs                 = $wpdb->prefix . 'nds_programs';
    $t_program_levels           = $wpdb->prefix . 'nds_program_levels';
    $t_course_categories        = $wpdb->prefix . 'nds_course_categories';
    $t_courses                  = $wpdb->prefix . 'nds_courses';
    $t_course_prerequisites     = $wpdb->prefix . 'nds_course_prerequisites';
    $t_program_accreditations   = $wpdb->prefix . 'nds_program_accreditations';
    $t_course_accreditations    = $wpdb->prefix . 'nds_course_accreditations';
    $t_modules                  = $wpdb->prefix . 'nds_modules';
    $t_timetable_sessions       = $wpdb->prefix . 'nds_timetable_sessions';
    $t_claimed_learners         = $wpdb->prefix . 'nds_claimed_learners';
    $t_staff                    = $wpdb->prefix . 'nds_staff';
    $t_rooms                    = $wpdb->prefix . 'nds_rooms';
    // Keep existing tables (students, enrollments, applications, etc.)
    $t_students                 = $wpdb->prefix . 'nds_students';
    $t_academic_years           = $wpdb->prefix . 'nds_academic_years';
    $t_semesters                = $wpdb->prefix . 'nds_semesters';
    $t_student_enrollments      = $wpdb->prefix . 'nds_student_enrollments';
    $t_cohorts                  = $wpdb->prefix . 'nds_cohorts';
    $t_student_cohorts          = $wpdb->prefix . 'nds_student_cohorts';
    $t_schedule_exceptions      = $wpdb->prefix . 'nds_schedule_exceptions';
    $t_applications             = $wpdb->prefix . 'nds_applications';
    $t_application_forms        = $wpdb->prefix . 'nds_application_forms';
    $t_application_docs         = $wpdb->prefix . 'nds_application_documents';
    $t_application_reviews      = $wpdb->prefix . 'nds_application_reviews';
    $t_application_payments     = $wpdb->prefix . 'nds_application_payments';
    $t_notifications            = $wpdb->prefix . 'nds_notifications';

    // -------------------------------------------------------------------------
    // 1. FACULTIES (Schools/Colleges)
    // -------------------------------------------------------------------------
    $sql_faculties = "CREATE TABLE IF NOT EXISTS $t_faculties (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(20) UNIQUE NOT NULL,
        name VARCHAR(255) NOT NULL,
        short_name VARCHAR(100),
        description TEXT,
        dean_name VARCHAR(255),
        dean_email VARCHAR(255),
        contact_phone VARCHAR(20),
        contact_email VARCHAR(255),
        website_url VARCHAR(255),
        page_id INT NULL,
        category_id INT NULL,
        color_primary VARCHAR(7) NULL,
        status ENUM('active', 'inactive', 'archived') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_status (status)
    ) $charset_collate;";
    dbDelta($sql_faculties);
    
    // Add color_primary column if it doesn't exist (for existing installations)
    $faculty_columns = $wpdb->get_col("SHOW COLUMNS FROM $t_faculties LIKE 'color_primary'");
    if (empty($faculty_columns)) {
        $wpdb->query("ALTER TABLE $t_faculties ADD COLUMN color_primary VARCHAR(7) NULL");
    }

    // -------------------------------------------------------------------------
    // 2. PROGRAM TYPES (Lookup Table)
    // -------------------------------------------------------------------------
    $sql_program_types = "CREATE TABLE IF NOT EXISTS $t_program_types (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(20) UNIQUE NOT NULL,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        typical_duration_years INT,
        level ENUM('undergraduate', 'postgraduate', 'professional') DEFAULT 'undergraduate',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) $charset_collate;";
    dbDelta($sql_program_types);

    // Seed Program Types
    $program_types = [
        ['diploma', 'Diploma', 1, 'undergraduate'],
        ['bachelor', 'Bachelor\'s Degree', 3, 'undergraduate'],
        ['honours', 'Honours Degree', 1, 'undergraduate'],
        ['masters', 'Master\'s Degree', 2, 'postgraduate'],
        ['phd', 'Doctor of Philosophy', 3, 'postgraduate'],
        ['certificate', 'Certificate', 0, 'professional'],
        ['short_course', 'Short Course', 0, 'professional']
    ];
    foreach ($program_types as $type) {
        $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO $t_program_types (code, name, typical_duration_years, level) VALUES (%s, %s, %d, %s)",
            $type[0],
            $type[1],
            $type[2],
            $type[3]
        ));
    }

    // -------------------------------------------------------------------------
    // 3. ACCREDITATION BODIES
    // -------------------------------------------------------------------------
    $sql_accreditation_bodies = "CREATE TABLE IF NOT EXISTS $t_accreditation_bodies (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(20) UNIQUE NOT NULL,
        name VARCHAR(255) NOT NULL,
        short_name VARCHAR(100),
        description TEXT,
        logo_url VARCHAR(255),
        website_url VARCHAR(255),
        contact_email VARCHAR(255),
        contact_phone VARCHAR(20),
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_status (status)
    ) $charset_collate;";
    dbDelta($sql_accreditation_bodies);

    // -------------------------------------------------------------------------
    // 4. PROGRAMS (Degrees/Diplomas)
    // -------------------------------------------------------------------------
    $sql_programs = "CREATE TABLE IF NOT EXISTS $t_programs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        faculty_id INT NOT NULL,
        program_type_id INT NOT NULL,
        code VARCHAR(50) UNIQUE NOT NULL,
        name VARCHAR(255) NOT NULL,
        short_name VARCHAR(100),
        description TEXT,
        nqf_level INT,
        total_credits INT,
        duration_years DECIMAL(3,1),
        duration_months INT,
        accreditation_body_id INT NULL,
        accreditation_number VARCHAR(100),
        accreditation_expiry DATE,
        entry_requirements TEXT,
        prerequisites TEXT,
        page_id INT NULL,
        category_id INT NULL,
        color VARCHAR(7) NULL,
        color_palette JSON NULL,
        status ENUM('active', 'inactive', 'archived', 'draft') DEFAULT 'active',
        intake_periods JSON,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (faculty_id) REFERENCES $t_faculties(id) ON DELETE RESTRICT,
        FOREIGN KEY (program_type_id) REFERENCES $t_program_types(id) ON DELETE RESTRICT,
        FOREIGN KEY (accreditation_body_id) REFERENCES $t_accreditation_bodies(id) ON DELETE SET NULL,
        INDEX idx_faculty (faculty_id),
        INDEX idx_program_type (program_type_id),
        INDEX idx_status (status)
    ) $charset_collate;";
    dbDelta($sql_programs);
    
    // Add color_palette column if it doesn't exist (for existing installations)
    $program_columns = $wpdb->get_col("SHOW COLUMNS FROM $t_programs LIKE 'color_palette'");
    if (empty($program_columns)) {
        $wpdb->query("ALTER TABLE $t_programs ADD COLUMN color_palette JSON NULL");
    }

    // -------------------------------------------------------------------------
    // 5. PROGRAM LEVELS (Year 1, Year 2, etc.)
    // -------------------------------------------------------------------------
    $sql_program_levels = "CREATE TABLE IF NOT EXISTS $t_program_levels (
        id INT AUTO_INCREMENT PRIMARY KEY,
        program_id INT NOT NULL,
        level_number INT NOT NULL,
        name VARCHAR(100),
        description TEXT,
        required_credits INT,
        min_gpa DECIMAL(3,2),
        prerequisites_level_id INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (program_id) REFERENCES $t_programs(id) ON DELETE CASCADE,
        FOREIGN KEY (prerequisites_level_id) REFERENCES $t_program_levels(id) ON DELETE SET NULL,
        UNIQUE KEY unique_program_level (program_id, level_number)
    ) $charset_collate;";
    dbDelta($sql_program_levels);

    // -------------------------------------------------------------------------
    // 6. COURSE CATEGORIES (Lookup)
    // -------------------------------------------------------------------------
    $sql_course_categories = "CREATE TABLE IF NOT EXISTS $t_course_categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(20) UNIQUE NOT NULL,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) $charset_collate;";
    dbDelta($sql_course_categories);

    // Seed Course Categories
    $categories = [
        ['core', 'Core Course'],
        ['elective', 'Elective Course'],
        ['foundation', 'Foundation Course'],
        ['capstone', 'Capstone Project'],
        ['internship', 'Internship/Practicum']
    ];
    foreach ($categories as $cat) {
        $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO $t_course_categories (code, name) VALUES (%s, %s)",
            $cat[0],
            $cat[1]
        ));
    }

    // -------------------------------------------------------------------------
    // 7. COURSES (Subjects/Modules)
    // -------------------------------------------------------------------------
    $sql_courses = "CREATE TABLE IF NOT EXISTS $t_courses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        program_id INT NOT NULL,
        level_id INT NULL,
        code VARCHAR(50) NOT NULL,
        name VARCHAR(255) NOT NULL,
        short_name VARCHAR(100),
        description TEXT,
        nqf_level INT,
        credits INT NOT NULL DEFAULT 0,
        contact_hours INT,
        self_study_hours INT,
        duration_weeks INT,
        category_id INT NULL,
        is_required BOOLEAN DEFAULT TRUE,
        assessment_method TEXT,
        pass_percentage DECIMAL(5,2) DEFAULT 50.00,
        price DECIMAL(10,2) DEFAULT 0.00,
        currency VARCHAR(3) DEFAULT 'ZAR',
        start_date DATE NULL,
        end_date DATE NULL,
        color VARCHAR(7) NULL,
        status ENUM('active', 'inactive', 'archived', 'draft') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (program_id) REFERENCES $t_programs(id) ON DELETE CASCADE,
        FOREIGN KEY (level_id) REFERENCES $t_program_levels(id) ON DELETE SET NULL,
        FOREIGN KEY (category_id) REFERENCES $t_course_categories(id) ON DELETE SET NULL,
        UNIQUE KEY unique_course_code (code),
        INDEX idx_program (program_id),
        INDEX idx_level (level_id),
        INDEX idx_category (category_id),
        INDEX idx_status (status)
    ) $charset_collate;";
    dbDelta($sql_courses);

    // -------------------------------------------------------------------------
    // 8. COURSE PREREQUISITES (M2M)
    // -------------------------------------------------------------------------
    $sql_course_prerequisites = "CREATE TABLE IF NOT EXISTS $t_course_prerequisites (
        id INT AUTO_INCREMENT PRIMARY KEY,
        course_id INT NOT NULL,
        prerequisite_course_id INT NOT NULL,
        is_mandatory BOOLEAN DEFAULT TRUE,
        min_grade VARCHAR(10) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (course_id) REFERENCES $t_courses(id) ON DELETE CASCADE,
        FOREIGN KEY (prerequisite_course_id) REFERENCES $t_courses(id) ON DELETE CASCADE,
        UNIQUE KEY unique_prerequisite (course_id, prerequisite_course_id),
        INDEX idx_prerequisite (prerequisite_course_id)
    ) $charset_collate;";
    dbDelta($sql_course_prerequisites);

    // -------------------------------------------------------------------------
    // 9. PROGRAM ACCREDITATIONS (M2M)
    // -------------------------------------------------------------------------
    $sql_program_accreditations = "CREATE TABLE IF NOT EXISTS $t_program_accreditations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        program_id INT NOT NULL,
        accreditation_body_id INT NOT NULL,
        accreditation_number VARCHAR(100),
        accreditation_date DATE,
        expiry_date DATE,
        status ENUM('active', 'expired', 'pending') DEFAULT 'active',
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (program_id) REFERENCES $t_programs(id) ON DELETE CASCADE,
        FOREIGN KEY (accreditation_body_id) REFERENCES $t_accreditation_bodies(id) ON DELETE CASCADE,
        UNIQUE KEY unique_program_accreditation (program_id, accreditation_body_id),
        INDEX idx_program (program_id),
        INDEX idx_accreditation_body (accreditation_body_id)
    ) $charset_collate;";
    dbDelta($sql_program_accreditations);

    // -------------------------------------------------------------------------
    // 10. COURSE ACCREDITATIONS (M2M)
    // -------------------------------------------------------------------------
    $sql_course_accreditations = "CREATE TABLE IF NOT EXISTS $t_course_accreditations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        course_id INT NOT NULL,
        accreditation_body_id INT NULL DEFAULT NULL,
        accreditation_number VARCHAR(100),
        accreditation_date DATE,
        expiry_date DATE,
        status ENUM('active', 'expired', 'pending') DEFAULT 'active',
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (course_id) REFERENCES $t_courses(id) ON DELETE CASCADE,
        FOREIGN KEY (accreditation_body_id) REFERENCES $t_accreditation_bodies(id) ON DELETE SET NULL,
        UNIQUE KEY unique_course_accreditation (course_id, accreditation_body_id),
        INDEX idx_accreditation_body (accreditation_body_id)
    ) $charset_collate;";
    dbDelta($sql_course_accreditations);

    // -------------------------------------------------------------------------
    // 11. MODULES (Course Modules/Components)
    // -------------------------------------------------------------------------
    $sql_modules = "CREATE TABLE IF NOT EXISTS $t_modules (
        id INT AUTO_INCREMENT PRIMARY KEY,
        module_code VARCHAR(20) UNIQUE NOT NULL,
        name VARCHAR(255) NOT NULL,
        course_id INT NOT NULL,
        type ENUM('theory','practical','workplace','assessment') DEFAULT 'theory',
        hours INT,
        nqf_level INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (course_id) REFERENCES $t_courses(id) ON DELETE CASCADE,
        INDEX idx_course (course_id),
        INDEX idx_module_code (module_code),
        INDEX idx_type (type)
    ) $charset_collate;";
    dbDelta($sql_modules);

    // -------------------------------------------------------------------------
    // EXISTING TABLES (Keep these - Students, Enrollments, Applications)
    // -------------------------------------------------------------------------

    // STUDENTS (Updated to reference new faculties table and claim/import metadata)
    $sql_students = "CREATE TABLE IF NOT EXISTS $t_students (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_number VARCHAR(20) UNIQUE NOT NULL,
        wp_user_id BIGINT(20) UNSIGNED NULL,
        source ENUM('application','manual_import','admin') DEFAULT 'application',
        faculty_id INT NULL,
        program_id INT NULL,
        first_name VARCHAR(100) NOT NULL,
        last_name VARCHAR(100) NOT NULL,
        email VARCHAR(255) UNIQUE NOT NULL,
        phone VARCHAR(20),
        date_of_birth DATE,
        gender ENUM('Male','Female','Other') NOT NULL,
        address TEXT,
        city VARCHAR(100),
        country VARCHAR(100) DEFAULT 'South Africa',
        profile_photo VARCHAR(255),
        status ENUM('prospect','active','graduated','alumni','inactive','withdrawn') DEFAULT 'prospect',
        intake_year INT NULL,
        intake_semester VARCHAR(50) NULL,
        expected_completion_year INT NULL,
        actual_completion_year INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (wp_user_id) REFERENCES {$wpdb->prefix}users(ID) ON DELETE SET NULL,
        claim_token VARCHAR(64) NULL,
        claim_expiry DATETIME NULL,
        is_claimed TINYINT(1) DEFAULT 0,
        claimed_at DATETIME NULL,
        claimed_by_user_id BIGINT(20) UNSIGNED NULL,
        FOREIGN KEY (faculty_id) REFERENCES $t_faculties(id) ON DELETE SET NULL,
        FOREIGN KEY (program_id) REFERENCES $t_programs(id) ON DELETE SET NULL,
        FOREIGN KEY (claimed_by_user_id) REFERENCES {$wpdb->prefix}users(ID) ON DELETE SET NULL,
        INDEX idx_source (source),
        INDEX idx_is_claimed (is_claimed),
        INDEX idx_faculty (faculty_id),
        INDEX idx_program (program_id),
        INDEX idx_intake_year (intake_year),
        INDEX idx_intake_semester (intake_semester),
        INDEX idx_expected_completion_year (expected_completion_year),
        INDEX idx_actual_completion_year (actual_completion_year)
    ) $charset_collate;";
    dbDelta($sql_students);

    // Backward-compatible migration: ensure expected/actual completion year columns exist
    $student_columns = $wpdb->get_col("SHOW COLUMNS FROM $t_students LIKE 'expected_completion_year'");
    if (empty($student_columns)) {
        $wpdb->query("ALTER TABLE $t_students ADD COLUMN expected_completion_year INT NULL AFTER intake_semester");
        $wpdb->query("ALTER TABLE $t_students ADD INDEX idx_expected_completion_year (expected_completion_year)");
    }
    $student_columns = $wpdb->get_col("SHOW COLUMNS FROM $t_students LIKE 'actual_completion_year'");
    if (empty($student_columns)) {
        $wpdb->query("ALTER TABLE $t_students ADD COLUMN actual_completion_year INT NULL AFTER expected_completion_year");
        $wpdb->query("ALTER TABLE $t_students ADD INDEX idx_actual_completion_year (actual_completion_year)");
    }

    // Backward-compatible migration: ensure new intake snapshot columns exist on existing installations
    // Some databases may have been created before intake_year/intake_semester were added to the schema.
    $student_columns = $wpdb->get_col( "SHOW COLUMNS FROM $t_students LIKE 'intake_year'" );
    if (empty($student_columns)) {
        // Add intake_year and index if missing
        $wpdb->query("ALTER TABLE $t_students ADD COLUMN intake_year INT NULL");
        $wpdb->query("ALTER TABLE $t_students ADD INDEX idx_intake_year (intake_year)");
    }
    $student_columns = $wpdb->get_col( "SHOW COLUMNS FROM $t_students LIKE 'intake_semester'" );
    if (empty($student_columns)) {
        // Add intake_semester and index if missing
        $wpdb->query("ALTER TABLE $t_students ADD COLUMN intake_semester VARCHAR(50) NULL");
        $wpdb->query("ALTER TABLE $t_students ADD INDEX idx_intake_semester (intake_semester)");
    }
    // Backward-compatible migration: ensure program_id column exists
    $student_columns = $wpdb->get_col("SHOW COLUMNS FROM $t_students LIKE 'program_id'");
    if (empty($student_columns)) {
        $wpdb->query("ALTER TABLE $t_students ADD COLUMN program_id INT NULL AFTER faculty_id");
        $wpdb->query("ALTER TABLE $t_students ADD INDEX idx_program (program_id)");
        // Try to add FK as well
        $wpdb->query("ALTER TABLE $t_students ADD CONSTRAINT fk_students_program FOREIGN KEY (program_id) REFERENCES $t_programs(id) ON DELETE SET NULL");
    }


    // ACADEMIC YEARS
    $sql_academic_years = "CREATE TABLE IF NOT EXISTS $t_academic_years (
        id INT AUTO_INCREMENT PRIMARY KEY,
        year_name VARCHAR(20) NOT NULL UNIQUE,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        is_active BOOLEAN DEFAULT FALSE
    ) $charset_collate;";
    dbDelta($sql_academic_years);

    // SEMESTERS
    $sql_semesters = "CREATE TABLE IF NOT EXISTS $t_semesters (
        id INT AUTO_INCREMENT PRIMARY KEY,
        academic_year_id INT NOT NULL,
        semester_name VARCHAR(50) NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        is_active BOOLEAN DEFAULT FALSE,
        FOREIGN KEY (academic_year_id) REFERENCES $t_academic_years(id) ON DELETE CASCADE
    ) $charset_collate;";
    dbDelta($sql_semesters);

    // STUDENT ENROLLMENTS
    $sql_enrollments = "CREATE TABLE IF NOT EXISTS $t_student_enrollments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        course_id INT NOT NULL,
        academic_year_id INT NOT NULL,
        semester_id INT NOT NULL,
        enrollment_date DATE NOT NULL,
        status ENUM('applied','enrolled','waitlisted','withdrawn','completed','failed') DEFAULT 'applied',
        delivery_mode ENUM('in-person','online','hybrid') DEFAULT 'in-person',
        final_grade VARCHAR(10),
        final_percentage DECIMAL(5,2),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES $t_students(id) ON DELETE CASCADE,
        FOREIGN KEY (course_id) REFERENCES $t_courses(id) ON DELETE CASCADE,
        FOREIGN KEY (academic_year_id) REFERENCES $t_academic_years(id) ON DELETE CASCADE,
        FOREIGN KEY (semester_id) REFERENCES $t_semesters(id) ON DELETE CASCADE,
        UNIQUE KEY unique_enrollment (student_id, course_id, academic_year_id, semester_id),
        INDEX idx_course (course_id),
        INDEX idx_academic_year (academic_year_id),
        INDEX idx_semester (semester_id),
        INDEX idx_status (status)
    ) $charset_collate;";
    dbDelta($sql_enrollments);

    // COHORTS (Groups of students within a program/year/semester)
    $sql_cohorts = "CREATE TABLE IF NOT EXISTS $t_cohorts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        program_id INT NOT NULL,
        academic_year_id INT NOT NULL,
        semester_id INT NULL,
        code VARCHAR(50) NOT NULL,
        name VARCHAR(255) NOT NULL,
        notes TEXT,
        status ENUM('active','inactive','archived') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (program_id) REFERENCES $t_programs(id) ON DELETE CASCADE,
        FOREIGN KEY (academic_year_id) REFERENCES $t_academic_years(id) ON DELETE CASCADE,
        FOREIGN KEY (semester_id) REFERENCES $t_semesters(id) ON DELETE SET NULL,
        UNIQUE KEY unique_cohort (program_id, academic_year_id, semester_id, code),
        INDEX idx_academic_year (academic_year_id),
        INDEX idx_semester (semester_id),
        INDEX idx_status (status)
    ) $charset_collate;";
    dbDelta($sql_cohorts);

    // STUDENT ↔ COHORTS (M2M: A student can belong to multiple cohorts over time)
    $sql_student_cohorts = "CREATE TABLE IF NOT EXISTS $t_student_cohorts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        cohort_id INT NOT NULL,
        start_date DATE NULL,
        end_date DATE NULL,
        status ENUM('active','inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES $t_students(id) ON DELETE CASCADE,
        FOREIGN KEY (cohort_id) REFERENCES $t_cohorts(id) ON DELETE CASCADE,
        UNIQUE KEY unique_student_cohort (student_id, cohort_id),
        INDEX idx_cohort (cohort_id)
    ) $charset_collate;";
    dbDelta($sql_student_cohorts);

    // APPLICATIONS (created before application_forms so FK application_forms.application_id can reference it)
    $sql_applications = "CREATE TABLE IF NOT EXISTS $t_applications (
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
        decided_by BIGINT(20) UNSIGNED NULL,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (wp_user_id) REFERENCES {$wpdb->prefix}users(ID) ON DELETE SET NULL,
        FOREIGN KEY (student_id) REFERENCES $t_students(id) ON DELETE SET NULL,
        FOREIGN KEY (program_id) REFERENCES $t_programs(id) ON DELETE SET NULL,
        FOREIGN KEY (course_id) REFERENCES $t_courses(id) ON DELETE SET NULL,
        FOREIGN KEY (academic_year_id) REFERENCES $t_academic_years(id) ON DELETE SET NULL,
        FOREIGN KEY (semester_id) REFERENCES $t_semesters(id) ON DELETE SET NULL,
        FOREIGN KEY (decided_by) REFERENCES {$wpdb->prefix}users(ID) ON DELETE SET NULL
    ) $charset_collate;";
    dbDelta($sql_applications);

    // APPLICATION FORMS (detailed applicant data; application_id and course_id FKs reference tables created above)
    $sql_application_forms = "CREATE TABLE IF NOT EXISTS $t_application_forms (
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
        FOREIGN KEY (application_id) REFERENCES $t_applications(id) ON DELETE SET NULL,
        FOREIGN KEY (course_id) REFERENCES $t_courses(id) ON DELETE RESTRICT,
        KEY idx_application_id (application_id),
        KEY idx_email (email),
        KEY idx_status (status),
        KEY idx_course_id (course_id),
        KEY idx_submitted_at (submitted_at)
    ) $charset_collate;";
    dbDelta($sql_application_forms);

    $sql_application_docs = "CREATE TABLE IF NOT EXISTS $t_application_docs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        application_id INT NOT NULL,
        document_type ENUM('id_document','academic_record','medical_certificate','contract','certificate','portfolio','motivation','other') NOT NULL,
        file_name VARCHAR(255) NOT NULL,
        file_path VARCHAR(500) NOT NULL,
        file_size INT NULL,
        mime_type VARCHAR(100) NULL,
        uploaded_by INT NULL,
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (application_id) REFERENCES $t_applications(id) ON DELETE CASCADE
    ) $charset_collate;";
    dbDelta($sql_application_docs);

    $sql_application_reviews = "CREATE TABLE IF NOT EXISTS $t_application_reviews (
        id INT AUTO_INCREMENT PRIMARY KEY,
        application_id INT NOT NULL,
        reviewer_id INT NOT NULL,
        stage ENUM('eligibility','academic','finance','final') DEFAULT 'eligibility',
        score DECIMAL(5,2) NULL,
        recommendation ENUM('proceed','waitlist','reject','conditional') NULL,
        comments TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (application_id) REFERENCES $t_applications(id) ON DELETE CASCADE,
        FOREIGN KEY (reviewer_id) REFERENCES {$wpdb->prefix}users(ID) ON DELETE CASCADE
    ) $charset_collate;";
    dbDelta($sql_application_reviews);

    $sql_application_payments = "CREATE TABLE IF NOT EXISTS $t_application_payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        application_id INT NOT NULL,
        reference VARCHAR(100) NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        currency VARCHAR(10) DEFAULT 'ZAR',
        type ENUM('application_fee','testing_fee','rpl_fee','other') DEFAULT 'application_fee',
        status ENUM('initiated','paid','failed','refunded') DEFAULT 'initiated',
        paid_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (application_id) REFERENCES $t_applications(id) ON DELETE CASCADE
    ) $charset_collate;";
    dbDelta($sql_application_payments);

    // -------------------------------------------------------------------------
    // 11. NOTIFICATIONS
    // -------------------------------------------------------------------------
    $t_notifications = $wpdb->prefix . 'nds_notifications';
    $sql_notifications = "CREATE TABLE IF NOT EXISTS $t_notifications (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        student_id bigint(20) NOT NULL,
        title varchar(255) NOT NULL,
        message text NOT NULL,
        type varchar(50) DEFAULT 'info',
        link varchar(255) DEFAULT '',
        is_read tinyint(1) DEFAULT 0,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY student_id (student_id)
    ) $charset_collate;";
    dbDelta($sql_notifications);

    // -------------------------------------------------------------------------
    // ADDITIONAL TABLES (Still in use - Keep these!)
    // -------------------------------------------------------------------------

    // STAFF ($t_staff already defined at top)
    $sql_staff = "CREATE TABLE IF NOT EXISTS $t_staff (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT(20) UNSIGNED NULL,
        first_name VARCHAR(100) NOT NULL,
        last_name VARCHAR(100) NOT NULL,
        profile_picture VARCHAR(255),
        email VARCHAR(255),
        phone VARCHAR(20),
        role VARCHAR(100),
        address TEXT,
        dob DATE,
        gender ENUM('Male','Female','Other'),
        faculty_id INT NULL,
        program_id INT NULL,
        course_id INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES {$wpdb->prefix}users(ID) ON DELETE SET NULL,
        INDEX idx_user (user_id),
        INDEX idx_email (email),
        INDEX idx_faculty (faculty_id),
        INDEX idx_program (program_id),
        INDEX idx_course (course_id)
    ) $charset_collate;";
    dbDelta($sql_staff);

    // Backward compatibility for existing installations.
    $staff_extra_columns = [
        'faculty_id' => 'INT NULL',
        'program_id' => 'INT NULL',
        'course_id' => 'INT NULL',
    ];
    foreach ($staff_extra_columns as $column => $definition) {
        $exists = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM $t_staff LIKE %s", $column));
        if (empty($exists)) {
            $wpdb->query("ALTER TABLE $t_staff ADD COLUMN $column $definition");
        }
    }

    // COURSE LECTURERS (M2M: Courses ↔ Staff)
    $t_course_lecturers = $wpdb->prefix . 'nds_course_lecturers';
    $sql_course_lecturers = "CREATE TABLE IF NOT EXISTS $t_course_lecturers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        course_id INT NOT NULL,
        lecturer_id INT NOT NULL,
        assigned_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (course_id) REFERENCES $t_courses(id) ON DELETE CASCADE,
        FOREIGN KEY (lecturer_id) REFERENCES $t_staff(id) ON DELETE CASCADE,
        UNIQUE KEY unique_course_lecturer (course_id, lecturer_id),
        INDEX idx_lecturer (lecturer_id)
    ) $charset_collate;";
    dbDelta($sql_course_lecturers);

    // COURSE SCHEDULES (Timetable)
    // -------------------------------------------------------------------------
    // ROOMS/VENUES (Halls, Classes, Kitchens) ($t_rooms already defined at top)
    // -------------------------------------------------------------------------
    $sql_rooms = "CREATE TABLE IF NOT EXISTS $t_rooms (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(20) UNIQUE NOT NULL,
        name VARCHAR(255) NOT NULL,
        type ENUM('hall','classroom','kitchen','lab','workshop','other') NOT NULL,
        capacity INT DEFAULT 0,
        location VARCHAR(255),
        equipment TEXT,
        amenities TEXT,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_type (type),
        INDEX idx_active (is_active)
    ) $charset_collate;";
    dbDelta($sql_rooms);

    $t_course_schedules = $wpdb->prefix . 'nds_course_schedules';
    $sql_course_schedules = "CREATE TABLE IF NOT EXISTS $t_course_schedules (
        id INT AUTO_INCREMENT PRIMARY KEY,
        course_id INT NOT NULL,
        lecturer_id INT NULL,
        room_id INT NULL,
        days VARCHAR(50),
        start_time TIME,
        end_time TIME,
        day_hours DECIMAL(4,2),
        session_type ENUM('lecture','practical','trade_test','workshop','seminar','exam') DEFAULT 'lecture',
        location VARCHAR(255),
        cohort_id INT NULL,
        pattern_type VARCHAR(50) DEFAULT 'every_week',
        pattern_meta TEXT NULL,
        valid_from DATE NULL,
        valid_to DATE NULL,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (course_id) REFERENCES $t_courses(id) ON DELETE CASCADE,
        FOREIGN KEY (lecturer_id) REFERENCES $t_staff(id) ON DELETE SET NULL,
        FOREIGN KEY (room_id) REFERENCES $t_rooms(id) ON DELETE SET NULL,
        FOREIGN KEY (cohort_id) REFERENCES $t_cohorts(id) ON DELETE SET NULL,
        INDEX idx_course (course_id),
        INDEX idx_lecturer (lecturer_id),
        INDEX idx_room (room_id),
        INDEX idx_cohort (cohort_id),
        INDEX idx_active (is_active),
        INDEX idx_session_type (session_type)
    ) $charset_collate;";
    dbDelta($sql_course_schedules);

    // -------------------------------------------------------------------------
    // TIMETABLE SESSIONS (Detailed Session Scheduling - after staff & cohorts exist for FK)
    // -------------------------------------------------------------------------
    $sql_timetable_sessions = "CREATE TABLE IF NOT EXISTS $t_timetable_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_date DATE NOT NULL,
        course_id INT NOT NULL,
        module_ids JSON,
        lecturer_id INT,
        start_time TIME,
        end_time TIME,
        room VARCHAR(100),
        session_type VARCHAR(50),
        cohort_id INT NOT NULL,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (course_id) REFERENCES $t_courses(id) ON DELETE CASCADE,
        FOREIGN KEY (lecturer_id) REFERENCES $t_staff(id) ON DELETE SET NULL,
        FOREIGN KEY (cohort_id) REFERENCES $t_cohorts(id) ON DELETE CASCADE,
        INDEX idx_session_date (session_date),
        INDEX idx_course (course_id),
        INDEX idx_lecturer (lecturer_id),
        INDEX idx_cohort (cohort_id),
        INDEX idx_session_type (session_type)
    ) $charset_collate;";
    dbDelta($sql_timetable_sessions);

    // STUDENT EVENTS (Calendar)
    $t_student_events = $wpdb->prefix . 'nds_student_events';
    $sql_student_events = "CREATE TABLE IF NOT EXISTS $t_student_events (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        source_schedule_id INT NULL,
        title VARCHAR(255) NOT NULL,
        start DATETIME NOT NULL,
        end DATETIME NOT NULL,
        all_day BOOLEAN DEFAULT FALSE,
        type VARCHAR(50),
        status VARCHAR(50),
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES $t_students(id) ON DELETE CASCADE,
        FOREIGN KEY (source_schedule_id) REFERENCES $t_course_schedules(id) ON DELETE SET NULL,
        INDEX idx_student (student_id),
        INDEX idx_start (start),
        INDEX idx_type (type)
    ) $charset_collate;";
    dbDelta($sql_student_events);

    // SCHEDULE EXCEPTIONS (Overrides/cancellations/additional sessions)
    $sql_schedule_exceptions = "CREATE TABLE IF NOT EXISTS $t_schedule_exceptions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        schedule_id INT NOT NULL,
        cohort_id INT NULL,
        date DATE NOT NULL,
        action ENUM('cancel','move','extra') NOT NULL DEFAULT 'cancel',
        new_start_time TIME NULL,
        new_end_time TIME NULL,
        new_location VARCHAR(255) NULL,
        notes TEXT,
        created_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (schedule_id) REFERENCES $t_course_schedules(id) ON DELETE CASCADE,
        FOREIGN KEY (cohort_id) REFERENCES $t_cohorts(id) ON DELETE SET NULL,
        INDEX idx_schedule_date (schedule_id, date),
        INDEX idx_cohort (cohort_id)
    ) $charset_collate;";
    dbDelta($sql_schedule_exceptions);

    // CUSTOM CALENDAR EVENTS (Global calendar events)
    $t_calendar_events = $wpdb->prefix . 'nds_calendar_events';
    $sql_calendar_events = "CREATE TABLE IF NOT EXISTS $t_calendar_events (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        start_date DATETIME NOT NULL,
        end_date DATETIME NULL,
        all_day BOOLEAN DEFAULT FALSE,
        event_type VARCHAR(50) DEFAULT 'event',
        color VARCHAR(7) DEFAULT '#3788d8',
        location VARCHAR(255),
        audience VARCHAR(255) DEFAULT 'all',
        created_by INT NULL,
        status ENUM('active', 'cancelled') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_start_date (start_date),
        INDEX idx_status (status),
        INDEX idx_event_type (event_type)
    ) $charset_collate;";
    dbDelta($sql_calendar_events);

    // RECIPES (Content Management - separate feature)
    $t_recipes = $wpdb->prefix . 'nds_recipes';
    $sql_recipes = "CREATE TABLE IF NOT EXISTS $t_recipes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        post_id INT NULL,
        recipe_name VARCHAR(255) NOT NULL,
        image VARCHAR(255),
        gallery TEXT,
        the_recipe TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_post (post_id)
    ) $charset_collate;";
    dbDelta($sql_recipes);

    // -------------------------------------------------------------------------
    // CLAIMED LEARNERS TABLE (For profile claim management)
    // -------------------------------------------------------------------------
    
    $sql_claimed_learners = "CREATE TABLE IF NOT EXISTS $t_claimed_learners (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    student_number VARCHAR(20) UNIQUE NOT NULL,
    id_number VARCHAR(50) UNIQUE NOT NULL,
    surname VARCHAR(100) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    email VARCHAR(255),
    phone VARCHAR(20),
    province VARCHAR(50) NOT NULL,
    claim_token VARCHAR(64) UNIQUE NOT NULL,
    claim_link VARCHAR(500),
    claim_expiry DATETIME NOT NULL,
    is_claimed BOOLEAN DEFAULT FALSE,
    claimed_at DATETIME NULL,
    claimed_by_user_id BIGINT(20) UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES $t_students(id) ON DELETE CASCADE,
    FOREIGN KEY (claimed_by_user_id) REFERENCES {$wpdb->prefix}users(ID) ON DELETE SET NULL,
    INDEX idx_student_number (student_number),
    INDEX idx_id_number (id_number),
    INDEX idx_claim_token (claim_token),
    INDEX idx_is_claimed (is_claimed),
    INDEX idx_claim_expiry (claim_expiry),
    INDEX idx_province (province)
) $charset_collate;";
    dbDelta($sql_claimed_learners);

    // STUDENT ACTIVITY LOG
    $t_activity_log = $wpdb->prefix . 'nds_student_activity_log';
    $sql_activity_log = "CREATE TABLE IF NOT EXISTS $t_activity_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        actor_id BIGINT(20) UNSIGNED NULL,
        action VARCHAR(255) NOT NULL,
        action_type VARCHAR(50),
        old_values LONGTEXT NULL,
        new_values LONGTEXT NULL,
        ip_address VARCHAR(45),
        user_agent TEXT,
        timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES $t_students(id) ON DELETE CASCADE,
        FOREIGN KEY (actor_id) REFERENCES {$wpdb->prefix}users(ID) ON DELETE SET NULL,
        INDEX idx_student (student_id),
        INDEX idx_timestamp (timestamp)
    ) $charset_collate;";
    dbDelta($sql_activity_log);

    // STUDENT NOTIFICATIONS
    $t_notifications = $wpdb->prefix . 'nds_notifications';
    $sql_notifications = "CREATE TABLE IF NOT EXISTS $t_notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        type ENUM('info', 'success', 'warning', 'error', 'timetable') DEFAULT 'info',
        link VARCHAR(255) NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES $t_students(id) ON DELETE CASCADE,
        INDEX idx_student (student_id),
        INDEX idx_is_read (is_read),
        INDEX idx_created_at (created_at)
    ) $charset_collate;";
    dbDelta($sql_notifications);

    // Backfill claim/import metadata from claimed_learners into students (idempotent)
    $backfill_sql = "
        UPDATE $t_students s
        INNER JOIN $t_claimed_learners c ON c.student_id = s.id
        SET
            s.source = CASE
                WHEN s.source IS NULL OR s.source = 'application' THEN 'manual_import'
                ELSE s.source
            END,
            s.claim_token = COALESCE(s.claim_token, c.claim_token),
            s.claim_expiry = COALESCE(s.claim_expiry, c.claim_expiry),
            s.is_claimed = CASE
                WHEN (s.is_claimed IS NULL OR s.is_claimed = 0) AND c.is_claimed = TRUE THEN 1
                ELSE s.is_claimed
            END,
            s.claimed_at = COALESCE(s.claimed_at, c.claimed_at),
            s.claimed_by_user_id = COALESCE(s.claimed_by_user_id, c.claimed_by_user_id)
        WHERE
            (s.source IS NULL OR s.source = 'application')
            AND (s.claim_token IS NULL OR s.claim_token = '')
    ";
    $wpdb->query($backfill_sql);
}
