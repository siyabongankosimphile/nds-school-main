<?php
/**
 * NDS School Database Migration System
 * Handles automatic database structure updates and data migration
 */

if (!defined('ABSPATH')) {
    exit;
}

class NDS_Database_Migration {
    
    private $version = '2.1.0';
    private $current_version;
    
    public function __construct() {
        $this->current_version = get_option('nds_db_version', '1.0.0');
        add_action('admin_init', array($this, 'check_migration'));
    }
    
    public function check_migration() {
        if (version_compare($this->current_version, $this->version, '<')) {
            $this->run_migration();
        }
    }
    
    public function run_migration() {
        global $wpdb;
        
        // Start migration
        $this->log('Starting database migration from ' . $this->current_version . ' to ' . $this->version);
        
        try {
            // Step 1: Update program types table structure
            $this->migrate_program_types_table();
            
            // Step 2: Update courses table structure  
            $this->migrate_courses_table();
            
            // Step 3: Update education paths table
            $this->migrate_education_paths_table();
            
            // Step 4: Migrate existing data
            $this->migrate_existing_data();
            
            // Step 5: Ensure timetable tables exist
            $this->migrate_timetable_tables();

            // Step 6: Clean up obsolete tables/columns
            $this->cleanup_obsolete_structure();
            
            // Update version
            update_option('nds_db_version', $this->version);
            
            $this->log('Database migration completed successfully');
            
        } catch (Exception $e) {
            $this->log('Migration failed: ' . $e->getMessage());
            wp_die('Database migration failed. Please contact support.');
        }
    }
    
    private function migrate_program_types_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'nds_program_types';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            $this->log('Creating program_types table...');
            $this->create_program_types_table();
            return;
        }
        
        $this->log('Updating program_types table structure...');
        
        // Get existing columns
        $columns = $wpdb->get_col("DESCRIBE $table_name");
        
        // Add new columns if they don't exist
        $new_columns = [
            'program_type' => "ALTER TABLE $table_name ADD COLUMN program_type ENUM('diploma', 'certificate', 'short_course', 'trade_test', 'workshop', 'masterclass') NOT NULL DEFAULT 'diploma' AFTER name",
            'level' => "ALTER TABLE $table_name ADD COLUMN level ENUM('beginner', 'intermediate', 'advanced', 'professional') NOT NULL DEFAULT 'beginner' AFTER program_type",
            'duration_months' => "ALTER TABLE $table_name ADD COLUMN duration_months INT DEFAULT 12 AFTER level",
            'certification_body' => "ALTER TABLE $table_name ADD COLUMN certification_body VARCHAR(255) AFTER duration_months",
            'requirements' => "ALTER TABLE $table_name ADD COLUMN requirements TEXT AFTER certification_body",
            'created_at' => "ALTER TABLE $table_name ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER requirements"
        ];
        
        foreach ($new_columns as $column => $sql) {
            if (!in_array($column, $columns)) {
                $result = $wpdb->query($sql);
                if ($result === false) {
                    throw new Exception("Failed to add column $column: " . $wpdb->last_error);
                }
                $this->log("Added column: $column");
            }
        }
    }
    
    private function migrate_courses_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'nds_courses';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            $this->log('Creating courses table...');
            $this->create_courses_table();
            return;
        }
        
        $this->log('Updating courses table structure...');
        
        // Get existing columns
        $columns = $wpdb->get_col("DESCRIBE $table_name");
        
        // Add new columns if they don't exist
        $new_columns = [
            'course_code' => "ALTER TABLE $table_name ADD COLUMN course_code VARCHAR(50) AFTER name",
            'prerequisites' => "ALTER TABLE $table_name ADD COLUMN prerequisites TEXT AFTER course_code",
            'learning_outcomes' => "ALTER TABLE $table_name ADD COLUMN learning_outcomes TEXT AFTER prerequisites",
            'assessment_method' => "ALTER TABLE $table_name ADD COLUMN assessment_method TEXT AFTER learning_outcomes",
            'updated_at' => "ALTER TABLE $table_name ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at"
        ];
        
        foreach ($new_columns as $column => $sql) {
            if (!in_array($column, $columns)) {
                $result = $wpdb->query($sql);
                if ($result === false) {
                    throw new Exception("Failed to add column $column: " . $wpdb->last_error);
                }
                $this->log("Added column: $column");
            }
        }
    }
    
    private function migrate_education_paths_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'nds_education_paths';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            $this->log('Creating education_paths table...');
            $this->create_education_paths_table();
            return;
        }
        
        $this->log('Updating education_paths table structure...');
        
        // Get existing columns
        $columns = $wpdb->get_col("DESCRIBE $table_name");
        
        // Add new columns if they don't exist
        $new_columns = [
            'path_type' => "ALTER TABLE $table_name ADD COLUMN path_type ENUM('culinary', 'hospitality', 'tourism', 'management') NOT NULL DEFAULT 'culinary' AFTER name",
            'duration_years' => "ALTER TABLE $table_name ADD COLUMN duration_years DECIMAL(3,1) DEFAULT 1.0 AFTER path_type",
            'career_outcomes' => "ALTER TABLE $table_name ADD COLUMN career_outcomes TEXT AFTER duration_years",
            'created_at' => "ALTER TABLE $table_name ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER career_outcomes"
        ];
        
        foreach ($new_columns as $column => $sql) {
            if (!in_array($column, $columns)) {
                $result = $wpdb->query($sql);
                if ($result === false) {
                    throw new Exception("Failed to add column $column: " . $wpdb->last_error);
                }
                $this->log("Added column: $column");
            }
        }
    }

    private function migrate_timetable_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $courses = $wpdb->prefix . 'nds_courses';
        $staff = $wpdb->prefix . 'nds_staff';

        $table_course_schedules = $wpdb->prefix . 'nds_course_schedules';
        $sql_course_schedules = "CREATE TABLE IF NOT EXISTS $table_course_schedules (
            id INT AUTO_INCREMENT PRIMARY KEY,
            course_id INT NOT NULL,
            lecturer_id INT NULL,
            days SET('mon','tue','wed','thu','fri','sat','sun') NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            day_hours DECIMAL(4,2) NULL,
            session_type ENUM('theory','practical') NOT NULL DEFAULT 'theory',
            location VARCHAR(255) NULL,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (course_id) REFERENCES $courses(id) ON DELETE CASCADE,
            FOREIGN KEY (lecturer_id) REFERENCES $staff(id) ON DELETE SET NULL,
            KEY idx_course (course_id),
            KEY idx_lecturer (lecturer_id)
        ) $charset_collate;";

        $table_student_events = $wpdb->prefix . 'nds_student_events';
        $sql_student_events = "CREATE TABLE IF NOT EXISTS $table_student_events (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            source_schedule_id INT NULL,
            title VARCHAR(255) NOT NULL,
            start DATETIME NOT NULL,
            end DATETIME NULL,
            all_day BOOLEAN DEFAULT FALSE,
            type ENUM('class','exam','event','personal') NOT NULL DEFAULT 'class',
            status ENUM('scheduled','cancelled','moved') NOT NULL DEFAULT 'scheduled',
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (student_id) REFERENCES {$wpdb->prefix}nds_students(id) ON DELETE CASCADE,
            FOREIGN KEY (source_schedule_id) REFERENCES $table_course_schedules(id) ON DELETE SET NULL,
            KEY idx_student (student_id),
            KEY idx_start (start)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_course_schedules);
        dbDelta($sql_student_events);
    }
    
    private function migrate_existing_data() {
        global $wpdb;
        
        $this->log('Migrating existing data...');
        
        // Update existing program types with default values
        $program_table = $wpdb->prefix . 'nds_program_types';
        if ($wpdb->get_var("SHOW TABLES LIKE '$program_table'") == $program_table) {
            $wpdb->query("UPDATE $program_table SET program_type = 'diploma' WHERE program_type IS NULL OR program_type = ''");
            $wpdb->query("UPDATE $program_table SET level = 'beginner' WHERE level IS NULL OR level = ''");
            $wpdb->query("UPDATE $program_table SET duration_months = 12 WHERE duration_months IS NULL OR duration_months = 0");
            $this->log('Updated program types with default values');
        }
        
        // Update existing education paths with default values
        $paths_table = $wpdb->prefix . 'nds_education_paths';
        if ($wpdb->get_var("SHOW TABLES LIKE '$paths_table'") == $paths_table) {
            $wpdb->query("UPDATE $paths_table SET path_type = 'culinary' WHERE path_type IS NULL OR path_type = ''");
            $wpdb->query("UPDATE $paths_table SET duration_years = 1.0 WHERE duration_years IS NULL OR duration_years = 0");
            $this->log('Updated education paths with default values');
        }
        
        // Update existing courses with default values
        $courses_table = $wpdb->prefix . 'nds_courses';
        if ($wpdb->get_var("SHOW TABLES LIKE '$courses_table'") == $courses_table) {
            // Generate course codes for existing courses
            $courses = $wpdb->get_results("SELECT id, name FROM $courses_table WHERE course_code IS NULL OR course_code = ''");
            foreach ($courses as $course) {
                $course_code = 'CUL' . str_pad($course->id, 3, '0', STR_PAD_LEFT);
                $wpdb->update($courses_table, ['course_code' => $course_code], ['id' => $course->id]);
            }
            $this->log('Generated course codes for existing courses');
        }
    }
    
    private function cleanup_obsolete_structure() {
        global $wpdb;
        
        $this->log('Cleaning up obsolete structure...');
        
        // Remove obsolete columns if they exist
        $obsolete_columns = [
            'faculty_id' => $wpdb->prefix . 'nds_courses',
            'department' => $wpdb->prefix . 'nds_program_types'
        ];
        
        foreach ($obsolete_columns as $column => $table) {
            if ($wpdb->get_var("SHOW TABLES LIKE '$table'") == $table) {
                $columns = $wpdb->get_col("DESCRIBE $table");
                if (in_array($column, $columns)) {
                    $wpdb->query("ALTER TABLE $table DROP COLUMN $column");
                    $this->log("Removed obsolete column: $column from $table");
                }
            }
        }
    }
    
    private function create_program_types_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        $table_name = $wpdb->prefix . 'nds_program_types';
        $paths_table = $wpdb->prefix . 'nds_education_paths';
        
        $sql = "CREATE TABLE $table_name (
            id INT AUTO_INCREMENT PRIMARY KEY,
            path_id INT NOT NULL,
            category_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            program_type ENUM('diploma', 'certificate', 'short_course', 'trade_test', 'workshop', 'masterclass') NOT NULL DEFAULT 'diploma',
            level ENUM('beginner', 'intermediate', 'advanced', 'professional') NOT NULL DEFAULT 'beginner',
            duration_months INT DEFAULT 12,
            certification_body VARCHAR(255),
            requirements TEXT,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (path_id) REFERENCES $paths_table(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    private function create_courses_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        $table_name = $wpdb->prefix . 'nds_courses';
        $program_table = $wpdb->prefix . 'nds_program_types';
        
        $sql = "CREATE TABLE $table_name (
            id INT AUTO_INCREMENT PRIMARY KEY,
            post_id INT NOT NULL,
            program_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            course_code VARCHAR(50),
            accreditation_body VARCHAR(255) NOT NULL,
            nqf_level INT,
            description TEXT,
            prerequisites TEXT,
            learning_outcomes TEXT,
            assessment_method TEXT,
            duration VARCHAR(50),
            credits INT,
            price DECIMAL(10,2) DEFAULT 0.00,
            currency VARCHAR(40) DEFAULT 'ZAR',
            start_date DATE,
            end_date DATE,
            status ENUM('Active', 'Inactive', 'Archived') NOT NULL DEFAULT 'Active',
            max_students INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (program_id) REFERENCES $program_table(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    private function create_education_paths_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        $table_name = $wpdb->prefix . 'nds_education_paths';
        
        $sql = "CREATE TABLE $table_name (
            id INT AUTO_INCREMENT PRIMARY KEY,
            post_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            path_type ENUM('culinary', 'hospitality', 'tourism', 'management') NOT NULL DEFAULT 'culinary',
            duration_years DECIMAL(3,1) DEFAULT 1.0,
            career_outcomes TEXT,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    private function log($message) {
        error_log('NDS Migration: ' . $message);
    }
    
    public function force_migration() {
        $this->run_migration();
    }
}

// Initialize migration system
new NDS_Database_Migration();
