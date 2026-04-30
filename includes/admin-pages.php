<?php
ob_start(); // Start buffering the output

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . 'setup-ups.php';
require_once plugin_dir_path(__FILE__) . 'students-functions.php';
require_once plugin_dir_path(__FILE__) . 'common.php';
require_once plugin_dir_path(__FILE__) . 'students-mosaic-grid.php';
require_once plugin_dir_path(__FILE__) . 'staff-functions.php';
require_once plugin_dir_path(__FILE__) . 'path-functions.php';
require_once plugin_dir_path(__FILE__) . 'education-management.php';
require_once plugin_dir_path(__FILE__) . 'courses-functions.php';
require_once plugin_dir_path(__FILE__) . 'module-management.php';
require_once plugin_dir_path(__FILE__) . 'career-paths-functions.php';
require_once plugin_dir_path(__FILE__) . 'course-management-ajax.php';
require_once plugin_dir_path(__FILE__) . 'applicants-management.php';
require_once plugin_dir_path(__FILE__) . 'calendar-functions.php';
require_once plugin_dir_path(__FILE__) . 'learner-dashboard.php';
require_once plugin_dir_path(__FILE__) . 'components/course-enrollments.php';

// AJAX handler for enrolling student in program (enrolls in all courses)
add_action('wp_ajax_nds_enroll_student_program', 'nds_ajax_enroll_student_program');
function nds_ajax_enroll_student_program() {
    check_ajax_referer('nds_enroll_student_program', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }
    
    $student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;
    $program_id = isset($_POST['program_id']) ? intval($_POST['program_id']) : 0;
    
    if (!$student_id || !$program_id) {
        wp_send_json_error('Invalid student or program ID');
    }
    
    global $wpdb;
    
    // Get active academic year and semester
    $active_year = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}nds_academic_years WHERE is_active = 1 ORDER BY id DESC LIMIT 1", ARRAY_A);
    $active_semester = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}nds_semesters WHERE is_active = 1 ORDER BY id DESC LIMIT 1", ARRAY_A);
    
    // Create default academic year and semester if they don't exist
    if (!$active_year) {
        $current_year = date('Y');
        $start_date = $current_year . '-01-01';
        $end_date = $current_year . '-12-31';
        
        // Check if year already exists (even if not active)
        $existing_year = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}nds_academic_years WHERE year_name = %s LIMIT 1",
            $current_year
        ));
        
        if ($existing_year) {
            // Activate existing year
            $wpdb->update(
                $wpdb->prefix . 'nds_academic_years',
                ['is_active' => 1],
                ['id' => $existing_year],
                ['%d'],
                ['%d']
            );
            $active_year_id = $existing_year;
        } else {
            // Create new year
            $wpdb->insert(
                $wpdb->prefix . 'nds_academic_years',
                [
                    'year_name' => $current_year,
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                    'is_active' => 1,
                    'created_at' => current_time('mysql')
                ],
                ['%s', '%s', '%s', '%d', '%s']
            );
            $active_year_id = $wpdb->insert_id;
        }
        
        // Check if semester already exists for this year
        $existing_semester = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}nds_semesters 
             WHERE academic_year_id = %d AND semester_number = 1 LIMIT 1",
            $active_year_id
        ));
        
        if ($existing_semester) {
            // Activate existing semester
            $wpdb->update(
                $wpdb->prefix . 'nds_semesters',
                ['is_active' => 1],
                ['id' => $existing_semester],
                ['%d'],
                ['%d']
            );
        } else {
            // Create default semester
            $wpdb->insert(
                $wpdb->prefix . 'nds_semesters',
                [
                    'academic_year_id' => $active_year_id,
                    'semester_name' => 'Semester 1',
                    'semester_number' => 1,
                    'start_date' => $start_date,
                    'end_date' => $current_year . '-06-30',
                    'is_active' => 1,
                    'created_at' => current_time('mysql')
                ],
                ['%d', '%s', '%d', '%s', '%s', '%d', '%s']
            );
        }
        
        // Refresh the query
        $active_year = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}nds_academic_years WHERE id = {$active_year_id}", ARRAY_A);
        $active_semester = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}nds_semesters WHERE academic_year_id = {$active_year_id} AND is_active = 1 LIMIT 1", ARRAY_A);
    } elseif (!$active_semester) {
        // Year exists but no active semester - check if semester exists first
        $existing_semester = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}nds_semesters 
             WHERE academic_year_id = %d AND semester_number = 1 LIMIT 1",
            $active_year['id']
        ));
        
        if ($existing_semester) {
            // Activate existing semester
            $wpdb->update(
                $wpdb->prefix . 'nds_semesters',
                ['is_active' => 1],
                ['id' => $existing_semester],
                ['%d'],
                ['%d']
            );
            $active_semester = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}nds_semesters WHERE id = {$existing_semester}", ARRAY_A);
        } else {
            // Create new semester
            $wpdb->insert(
                $wpdb->prefix . 'nds_semesters',
                [
                    'academic_year_id' => $active_year['id'],
                    'semester_name' => 'Semester 1',
                    'semester_number' => 1,
                    'start_date' => $active_year['start_date'],
                    'end_date' => date('Y-m-d', strtotime($active_year['start_date'] . ' +6 months')),
                    'is_active' => 1,
                    'created_at' => current_time('mysql')
                ],
                ['%d', '%s', '%d', '%s', '%s', '%d', '%s']
            );
            $active_semester = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}nds_semesters WHERE id = {$wpdb->insert_id}", ARRAY_A);
        }
    }
    
    if (!$active_year || !$active_semester) {
        wp_send_json_error('Failed to create or retrieve active academic year/semester');
    }
    
    // Get all courses in this program
    $courses = $wpdb->get_results($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}nds_courses 
         WHERE program_id = %d AND status = 'active'",
        $program_id
    ), ARRAY_A);
    
    if (empty($courses)) {
        wp_send_json_error('No active courses found in this program');
    }
    
    $enrollments_created = 0;
    $enrollments_table = $wpdb->prefix . 'nds_student_enrollments';
    
    foreach ($courses as $course) {
        $course_id = $course['id'];
        
        // Check if already enrolled (comprehensive duplicate check)
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$enrollments_table} 
             WHERE student_id = %d AND course_id = %d AND academic_year_id = %d AND semester_id = %d",
            $student_id, $course_id, $active_year['id'], $active_semester['id']
        ));
        
        if ($existing) {
            // Update status if enrollment exists but status is different
            $wpdb->update(
                $enrollments_table,
                ['status' => 'enrolled', 'updated_at' => current_time('mysql')],
                ['id' => $existing],
                ['%s', '%s'],
                ['%d']
            );
            continue; // Skip creating duplicate
        }
        
        // Double-check before insert (race condition protection)
        $double_check = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$enrollments_table} 
             WHERE student_id = %d AND course_id = %d AND academic_year_id = %d AND semester_id = %d",
            $student_id, $course_id, $active_year['id'], $active_semester['id']
        ));
        
        if ($double_check) {
            continue; // Skip if duplicate was created between checks
        }
        
        // Create enrollment
        $result = $wpdb->insert(
            $enrollments_table,
            [
                'student_id' => $student_id,
                'course_id' => $course_id,
                'academic_year_id' => $active_year['id'],
                'semester_id' => $active_semester['id'],
                'enrollment_date' => current_time('Y-m-d'),
                'status' => 'enrolled',
                'created_at' => current_time('mysql')
            ],
            ['%d', '%d', '%d', '%d', '%s', '%s', '%s']
        );
        
        if ($result) {
            $enrollments_created++;
        } elseif ($wpdb->last_error) {
            // Check if error is due to duplicate (unique constraint)
            if (strpos($wpdb->last_error, 'Duplicate') !== false || strpos($wpdb->last_error, 'UNIQUE') !== false) {
                // Duplicate was prevented by database constraint
                continue;
            }
        }
    }
    
    if ($enrollments_created > 0) {
        wp_send_json_success([
            'courses_enrolled' => $enrollments_created,
            'message' => "Enrolled in {$enrollments_created} course(s)"
        ]);
    } else {
        wp_send_json_error('No new enrollments created. Student may already be enrolled in all courses.');
    }
}

// AJAX handler for unenrolling student from a program
add_action('wp_ajax_nds_unenroll_student_program', 'nds_ajax_unenroll_student_program');
function nds_ajax_unenroll_student_program() {
    check_ajax_referer('nds_unenroll_student_program', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }
    
    $program_id = isset($_POST['program_id']) ? intval($_POST['program_id']) : 0;
    $student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;
    
    if (!$program_id || !$student_id) {
        wp_send_json_error('Invalid program or student ID');
    }
    
    global $wpdb;
    
    // Delete all enrollments for this student in courses from this program
    $deleted = $wpdb->query($wpdb->prepare(
        "DELETE e FROM {$wpdb->prefix}nds_student_enrollments e
         INNER JOIN {$wpdb->prefix}nds_courses c ON e.course_id = c.id
         WHERE e.student_id = %d AND c.program_id = %d",
        $student_id, $program_id
    ));
    
    if ($deleted !== false) {
        wp_send_json_success([
            'message' => 'Program unenrolled successfully',
            'courses_removed' => $deleted
        ]);
    } else {
        wp_send_json_error('Failed to unenroll from program: ' . ($wpdb->last_error ?: 'Database error'));
    }
}

// Keep admin_post handler for backward compatibility (redirects to AJAX version)
add_action('admin_post_nds_unenroll_student_program', 'nds_handle_unenroll_student_program');
function nds_handle_unenroll_student_program() {
    // Redirect to AJAX endpoint for consistency
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    $program_id = isset($_POST['program_id']) ? intval($_POST['program_id']) : 0;
    $student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;
    
    wp_redirect(admin_url('admin.php?page=nds-learner-dashboard&id=' . $student_id . '&tab=courses'));
    exit;
}

// AJAX handler for enrolling student in course via drag and drop
add_action('wp_ajax_nds_enroll_student_course', 'nds_ajax_enroll_student_course');
function nds_ajax_enroll_student_course() {
    check_ajax_referer('nds_enroll_student_course', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }
    
    $student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;
    $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
    $level_id = isset($_POST['level_id']) ? intval($_POST['level_id']) : null;
    
    if (!$student_id || !$course_id) {
        wp_send_json_error('Invalid student or course ID');
    }
    
    // Verify course belongs to the specified level if provided
    if ($level_id) {
        global $wpdb;
        $course_level = $wpdb->get_var($wpdb->prepare(
            "SELECT level_id FROM {$wpdb->prefix}nds_courses WHERE id = %d",
            $course_id
        ));
        if ($course_level != $level_id) {
            wp_send_json_error('Course does not belong to the specified level');
        }
    }
    
    global $wpdb;
    
    // Get active academic year and semester
    $active_year = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}nds_academic_years WHERE is_active = 1 ORDER BY id DESC LIMIT 1", ARRAY_A);
    $active_semester = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}nds_semesters WHERE is_active = 1 ORDER BY id DESC LIMIT 1", ARRAY_A);
    
    if (!$active_year || !$active_semester) {
        wp_send_json_error('Active academic year or semester not set');
    }
    
    // Check if already enrolled
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}nds_student_enrollments 
         WHERE student_id = %d AND course_id = %d AND academic_year_id = %d AND semester_id = %d",
        $student_id, $course_id, $active_year['id'], $active_semester['id']
    ));
    
    if ($existing) {
        wp_send_json_error('Student is already enrolled in this course');
    }
    
    // Create enrollment
    $result = $wpdb->insert(
        $wpdb->prefix . 'nds_student_enrollments',
        [
            'student_id' => $student_id,
            'course_id' => $course_id,
            'academic_year_id' => $active_year['id'],
            'semester_id' => $active_semester['id'],
            'enrollment_date' => current_time('Y-m-d'),
            'status' => 'enrolled',
            'created_at' => current_time('mysql')
        ],
        ['%d', '%d', '%d', '%d', '%s', '%s', '%s']
    );
    
    if ($result) {
        wp_send_json_success([
            'enrollment_id' => $wpdb->insert_id,
            'message' => 'Course enrolled successfully'
        ]);
    } else {
        wp_send_json_error('Failed to create enrollment');
    }
}

// AJAX handler for unenrolling student from course
add_action('wp_ajax_nds_unenroll_student_course', 'nds_ajax_unenroll_student_course');
function nds_ajax_unenroll_student_course() {
    check_ajax_referer('nds_unenroll_student_course', 'nonce');

    $student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;

    if (!current_user_can('manage_options')) {
        $current_student_id = function_exists('nds_portal_get_current_student_id') ? (int) nds_portal_get_current_student_id() : 0;
        if ($current_student_id <= 0 || $student_id <= 0 || $current_student_id !== $student_id) {
            wp_send_json_error('Unauthorized');
        }
    }
    
    $enrollment_id = isset($_POST['enrollment_id']) ? intval($_POST['enrollment_id']) : 0;
    $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
    
    if (!$enrollment_id || !$student_id || !$course_id) {
        wp_send_json_error('Invalid enrollment, student, or course ID');
    }
    
    global $wpdb;
    $enrollments_table = $wpdb->prefix . 'nds_student_enrollments';
    
    // Verify the enrollment belongs to this student and course
    $enrollment = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM {$enrollments_table} WHERE id = %d AND student_id = %d AND course_id = %d",
        $enrollment_id, $student_id, $course_id
    ), ARRAY_A);
    
    if (!$enrollment) {
        wp_send_json_error('Enrollment not found or does not match student/course');
    }
    
    // Delete the enrollment
    $deleted = $wpdb->delete(
        $enrollments_table,
        ['id' => $enrollment_id],
        ['%d']
    );
    
    if ($deleted) {
        wp_send_json_success([
            'message' => 'Course unenrolled successfully'
        ]);
    } else {
        wp_send_json_error('Failed to unenroll from course: ' . $wpdb->last_error);
    }
}

// Handler for unenrolling students from courses (legacy form handler)
add_action('admin_post_nds_unenroll_student', 'nds_handle_unenroll_student');
function nds_handle_unenroll_student() {
    if (!current_user_can('manage_options')) {
        wp_redirect(admin_url('admin.php?page=nds-all-learners&error=unauthorized'));
        exit;
    }
    
    // Verify nonce
    if (!isset($_POST['nds_unenroll_nonce']) || !wp_verify_nonce($_POST['nds_unenroll_nonce'], 'nds_unenroll_student')) {
        wp_redirect(admin_url('admin.php?page=nds-all-learners&error=security_check_failed'));
        exit;
    }
    
    $enrollment_id = isset($_POST['enrollment_id']) ? intval($_POST['enrollment_id']) : 0;
    $student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;
    
    if (!$enrollment_id || !$student_id) {
        wp_redirect(admin_url('admin.php?page=nds-learner-dashboard&id=' . $student_id . '&tab=courses&error=invalid_data'));
        exit;
    }
    
    global $wpdb;
    $enrollments_table = $wpdb->prefix . 'nds_student_enrollments';
    
    // Delete the enrollment
    $deleted = $wpdb->delete(
        $enrollments_table,
        ['id' => $enrollment_id],
        ['%d']
    );
    
    if ($deleted) {
        wp_redirect(admin_url('admin.php?page=nds-learner-dashboard&id=' . $student_id . '&tab=courses&success=unenrolled'));
    } else {
        wp_redirect(admin_url('admin.php?page=nds-learner-dashboard&id=' . $student_id . '&tab=courses&error=unenroll_failed'));
    }
    exit;
}

// Main NDS Academy Dashboard
function nds_school_dashboard() {
    // Load the comprehensive main dashboard
    require_once plugin_dir_path(__FILE__) . 'main-dashboard.php';
    nds_academy_main_dashboard();
}

// Modern Faculties Page (formerly Education Paths)
function nds_school_education_paths_page() {
    // Load the enhanced faculties page
    require_once plugin_dir_path(__FILE__) . 'education-paths-enhanced.php';
    nds_education_paths_enhanced();
}

// ================= Staff Dashboard & Pages =================
function nds_staff_dashboard() {
    // Load the improved dashboard with modern Tailwind UI
    try {
        $file_path = plugin_dir_path(__FILE__) . 'staff-dashboard-new.php';
        if (file_exists($file_path)) {
            require_once $file_path;
            if (function_exists('nds_staff_dashboard_improved')) {
    nds_staff_dashboard_improved();
            } else {
                echo '<div class="wrap"><h1>Staff Dashboard</h1><p>Function nds_staff_dashboard_improved not found.</p></div>';
            }
        } else {
            echo '<div class="wrap"><h1>Staff Dashboard</h1><p>File staff-dashboard-new.php not found at: ' . $file_path . '</p></div>';
        }
    } catch (Exception $e) {
        echo '<div class="wrap"><h1>Staff Dashboard</h1><p>Error: ' . $e->getMessage() . '</p></div>';
    }
}

/**
 * Assign Lecturers page wrapper
 */
function nds_assign_lecturers_page() {
    // Load the drag-and-drop assignment UI
    $file_path = plugin_dir_path(__FILE__) . 'assign-lecturers.php';
    if (file_exists($file_path)) {
        require_once $file_path;
        if (function_exists('nds_assign_lecturers_page_content')) {
            nds_assign_lecturers_page_content();
        } else {
            echo '<div class="wrap"><h1>Assign Lecturers</h1><p>Function nds_assign_lecturers_page_content not found.</p></div>';
        }
    } else {
        echo '<div class="wrap"><h1>Assign Lecturers</h1><p>File assign-lecturers.php not found at: ' . esc_html($file_path) . '</p></div>';
    }
}

function nds_add_staff_page() {
    // Redirect to main staff dashboard (integrated add functionality)
    wp_redirect(admin_url('admin.php?page=nds-staff-management'));
    exit;
}

function nds_edit_staff_page() {
    // Load the improved edit staff page
    require_once plugin_dir_path(__FILE__) . 'edit-staff-improved.php';
    nds_edit_staff_page_improved();
}

function nds_edit_education_paths_page()
{
    // Check if the user has the capability to manage options (Admin only)
    if (!current_user_can('manage_options')) {
        wp_die(__('Unauthorized user', 'text-domain'));
    }
    if (isset($_GET['edit'])) {
        $path_id = $_GET['edit'];

        global $wpdb;
        $table_name = $wpdb->prefix . 'nds_faculties';
        $path = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $path_id));

        if ($path) {
            // Form to edit the faculty
    ?>
            <h1>Edit Faculty</h1>
            <form method="POST" action="<?php echo admin_url('admin-post.php'); ?>">
                <?php wp_nonce_field('nds_update_program_nonce', 'nds_update_program_nonce'); ?>
                <?php educationForm($path); ?>
                <input type="submit" name="submit_path" value="Update Path" class="w-full bg-blue-500 text-white py-2 rounded-md hover:bg-blue-600 cursor-pointer" />
                <input type="hidden" name="action" value="nds_update_education_path" /> <!-- Important hidden field -->
                <input type="hidden" name="edit_id" value="<?php echo $path_id; ?>" /> <!-- Important hidden field -->
            </form>
    <?php
        } else {
            echo '<p>Path not found!</p>';
        }
    } else {
        echo '<p>No path ID nds_edit_education_paths_page specified!</p>';
    }
}

// Modern Programs Page with Tailwind CSS
function nds_programs_page()
{
    // Load the modern programs page
    if (file_exists(plugin_dir_path(__FILE__) . 'programs-tailwind.php')) {
        require_once plugin_dir_path(__FILE__) . 'programs-tailwind.php';
        nds_programs_page_tailwind();
    } else {
        // Fallback to basic implementation
    ?>
    <div class="grid grid-cols-7 gap-4">
        <div class="col-span-2">
            <?php echo nds_add_program_form(); ?>
        </div>
        <div class="col-span-5 bg-white rounded p-4">
            <?php echo do_shortcode('[nds_programs_table]'); ?>
        </div>
    </div>
    <?php
    }
}

function nds_edit_program_page()
{
    // Handle delete action if the delete parameter is set in the URL
    if (isset($_GET['delete_program'])) {
        $delete_id = intval($_GET['delete_program']);

        // Call the delete function (ensure it's defined and included somewhere)
        nds_delete_program($delete_id);
    }

    if (isset($_GET['edit_program'])) {
        $program_id = $_GET['edit_program'];
        // Fetch the program details from the database
        $program = nds_get_program_by_id($program_id);

        if ($program) {
    ?>
            <div class="wrap grid grid-cols-3 gap-4">
                <?php
                $act = "update";
                // Show the edit form with the program's data
                echo program_form($act, null, $program);
                ?>
                <div class="col-span-2 space-y-4">
                    sdsd32
                    <div class="shadow rounded bg-white p-6">
                        <div class="flex justify-between items-center">
                            <h2 class="text-2xl font-bold"><?php echo $program['name']; ?></h2>
                            <?php echo do_shortcode('[universalCourseModal gama="add" pathid="' . $program_id . '"]'); ?>
                        </div>
                    </div>
                    <div class="bg-white shadow rounded">
                        <div class="grid grid-cols-3 gap-4 p-6">
                            <?php
                            $courses = nds_get_course_by_programid($_GET['edit_program']);
                            foreach ($courses as $key => $course) {
                                courseCard($course);
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
    <?php
        }
    }
}

// Modern Qualifications Page with Tailwind CSS
function nds_courses_page()
{
    // Load the modern qualifications page
    if (file_exists(plugin_dir_path(__FILE__) . 'courses-tailwind.php')) {
        require_once plugin_dir_path(__FILE__) . 'courses-tailwind.php';
        nds_courses_page_tailwind();
    } else {
        // Fallback to basic implementation
    if (isset($_GET['delete_course'])) {
        // Call the delete function
        nds_delete_course($_GET);
    }

    global $wpdb;
    $courses_table = $wpdb->prefix . "nds_courses";
    $programs_table = $wpdb->prefix . "nds_programs";

    // Fetch all qualifications (using join to get program name)
    $courses = $wpdb->get_results("
        SELECT c.*, p.id AS program_id, p.name AS program_name, p.faculty_id
        FROM $courses_table AS c
        INNER JOIN $programs_table AS p ON c.program_id = p.id
    ", ARRAY_A);
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline text-2xl font-semibold">Qualifications</h1>
        <a href="<?php echo admin_url('admin.php?page=nds-add-course'); ?>"
            class="page-title-action bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600 transition">Add New
            Qualification</a>
        <hr class="wp-header-end my-4">
        <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($courses as $course):
                courseCard($course);
            endforeach; ?>
        </div>
    </div>
    <?php
    }
}

// Qualification Overview Page
function nds_course_overview_page() {
    // Load the qualification overview page
    require_once plugin_dir_path(__FILE__) . 'course-overview.php';
    nds_course_overview_page_content();
}

function courseCard($course)
{

?>
    <div class="bg-white p-4 rounded-lg border">
        <h6 class="text-xl font-bold mb-2 truncate"><?php echo esc_html($course['name']); ?></h6>
        <p class="text-gray-600 mb-4 truncate"><?php echo esc_html($course['description']); ?></p>
        <div class="flex justify-center items-center mb-4">
            <a href="<?php echo admin_url('admin.php?page=nds-edit-program&edit_program=' . $course['program_id']); ?>"
                class="bg-gray-200 text-gray-800 px-3 py-1 rounded-md text-sm hover:bg-gray-300 transition">
                <?php echo esc_html(nds_get_program_type_name($course['program_id'])); ?>
            </a>
        </div>
        <div class="flex justify-center">
            <div class="inline-flex rounded-md shadow-xs" role="group">
                <a href="<?php echo admin_url('admin.php?page=nds-edit-course&edit_course=' . $course['id']); ?>"
                    class="px-4 py-2 text-sm font-medium text-gray-900 bg-white border border-gray-200 rounded-s-lg hover:bg-gray-100 hover:text-blue-700 focus:z-10 focus:ring-2 focus:ring-blue-700 focus:text-blue-700 dark:bg-gray-800 dark:border-gray-700 dark:text-white dark:hover:text-white dark:hover:bg-gray-700 dark:focus:ring-blue-500 dark:focus:text-white">
                    Edit
                </a>
                <a href="<?php echo admin_url('admin.php?page=nds-courses&url=nds-edit-program&edit_program=' . $course['program_id'] . '&delete_course=' . $course['id']); ?>"
                    class="px-4 py-2 text-sm font-medium text-gray-900 bg-white border-t border-b border-r border-gray-200 hover:bg-gray-100 hover:text-blue-700 focus:z-10 focus:ring-2 focus:ring-blue-700 focus:text-blue-700 dark:bg-gray-800 dark:border-gray-700 dark:text-white dark:hover:text-white dark:hover:bg-gray-700 dark:focus:ring-blue-500 dark:focus:text-white"
                    onclick="return confirm('Are you sure?')">
                    Delete
                </a>
            </div>
        </div>

    </div>
<?php

}
function course_form($typ, $course = null, $program_id = null, $url = null, $modal = false)
{
    global $wpdb;

    // Block adding a qualification if no programs exist yet
    if ($typ === 'add' && empty($program_id)) {
        $programs_check_table = $wpdb->prefix . 'nds_programs';
        $program_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM $programs_check_table");
        if ($program_count === 0) {
            $programs_url = admin_url('admin.php?page=nds-programs');
            echo '<div class="bg-yellow-50 border border-yellow-300 text-yellow-800 rounded-md p-4">';
            echo '<p class="font-semibold">No Program Found</p>';
            echo '<p class="text-sm mt-1">You must <a href="' . esc_url($programs_url) . '" class="underline font-medium">create a Program</a> before you can add a Qualification.</p>';
            echo '</div>';
            return;
        }
    }

    if (!$modal) {
?>
    <div class="space-y-6">
<?php } ?>
        <input type="hidden" name="code" value="null">
        <input type="hidden" name="currency" value="ZAR" />
        <fieldset class="space-y-4">
            <legend class="text-lg font-semibold text-gray-700">Qualification Details</legend>
            <hr>
            <div class="grid grid-cols-2 gap-6">
                <div class="flex flex-col justify-evenly space-y-4">
                    <div class="flex flex-col">
                        <label for="name" class="text-sm font-medium text-gray-700">Qualification Name:</label>
                        <input type="text" name="name" placeholder="Qualification Name"
                            value="<?php echo isset($course) && isset($course->name) ? esc_attr($course->name) : ''; ?>" required
                            class="mt-1 p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div class="flex flex-col">
                        <label for="description" class="text-sm font-medium text-gray-700">Description:</label>
                        <textarea name="description" placeholder="Qualification Description" rows="4"
                            class="mt-1 p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"><?php echo isset($course) && isset($course->description) ? esc_textarea($course->description) : ''; ?></textarea>
                    </div>
                </div>
                <div class="flex flex-col">
                    <div class="space-y-4">
                        <div class="flex flex-col">
                            <label for="nqf_level" class="text-sm font-medium text-gray-700">NQF Level:</label>
                            <select id="nqf_level" name="nqf_level" required
                                class="mt-1 p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                <?php 
                                $course_nqf = isset($course) && isset($course->nqf_level) ? intval($course->nqf_level) : '';
                                ?>
                                <option value="1" <?php echo $course_nqf == 1 ? 'selected' : ''; ?>>NQF Level 1
                                </option>
                                <option value="2" <?php echo $course_nqf == 2 ? 'selected' : ''; ?>>NQF Level 2
                                </option>
                                <option value="3" <?php echo $course_nqf == 3 ? 'selected' : ''; ?>>NQF Level 3
                                </option>
                                <option value="4" <?php echo $course_nqf == 4 ? 'selected' : ''; ?>>NQF Level 4
                                </option>
                                <option value="5" <?php echo $course_nqf == 5 ? 'selected' : ''; ?>>NQF Level 5
                                </option>
                            </select>
                        </div>
                        <?php if (!isset($program_id)): ?>
                            <div class="flex flex-col">
                                <label for="program_id" class="text-sm font-medium text-gray-700">Program:</label>
                                <select id="program_id" name="program_id" required
                                    class="mt-1 p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">Select Program</option>
                                    <?php
                                    global $wpdb;
                                    $programs_table = $wpdb->prefix . "nds_programs";
                                    $programs = $wpdb->get_results("SELECT id, name FROM $programs_table");

                                    foreach ($programs as $program) {
                                        $selected = isset($course) && isset($course->program_id) && $course->program_id == $program->id ? 'selected' : '';
                                        echo "<option value='{$program->id}' {$selected}>{$program->name}</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        <?php else: ?>
                            <input type="hidden" name="url" value="<?php echo $url; ?>">
                            <input type="hidden" name="program_id" value="<?php echo $program_id; ?>">
                        <?php endif; ?>
                        <div class="flex flex-col">
                            <label for="duration" class="text-sm font-medium text-gray-700">Qualification Duration:</label>
                            <select id="duration" name="duration" required
                                class="mt-1 p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                <?php 
                                $course_duration = isset($course) && isset($course->duration) ? intval($course->duration) : (isset($course) && isset($course->duration_weeks) ? intval($course->duration_weeks) : '');
                                ?>
                                <option value="6" <?php echo $course_duration == 6 ? 'selected' : ''; ?>>6 Months
                                </option>
                                <option value="12" <?php echo $course_duration == 12 ? 'selected' : ''; ?>>12 Months
                                </option>
                                <option value="24" <?php echo $course_duration == 24 ? 'selected' : ''; ?>>24 Months
                                </option>
                            </select>
                        </div>
                        <div class="flex flex-col">
                            <label for="credits" class="text-sm font-medium text-gray-700">Credits:</label>
                            <input type="number" name="credits" placeholder="Credits"
                                value="<?php echo isset($course) && isset($course->credits) ? esc_attr($course->credits) : ''; ?>" required
                                class="mt-1 p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>
                </div>
            </div>
        </fieldset>

        <div class="grid grid-cols-2 gap-4">
            <div class="flex flex-col">
                <?php
                // Fetch the accreditation bodies
                $accreditation_bodies = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}nds_accreditation_bodies");
                $accreditation_body_ids = (isset($course) && isset($course->accreditation_body)) ? $course->accreditation_body : ""; // This should come from your saved data

                if ($accreditation_bodies) {
                    echo '<fieldset class="space-y-4">';
                    echo '<legend class="text-lg font-semibold text-gray-700">Accreditation Bodies</legend>';
                    echo '<hr>';
                    echo '<div class="flex gap-4">';
                    // Convert saved accreditation body IDs to an array
                    $selected_bodies = explode(',', $accreditation_body_ids);

                    // Loop through each accreditation body and create a checkbox
                    foreach ($accreditation_bodies as $body) {
                        $is_checked = in_array($body->id, $selected_bodies) ? 'checked' : '';
                        echo '<div class="flex items-center">';
                        echo '<input type="checkbox" name="accreditation_bodies[]" value="' . $body->id . '" ' . $is_checked . ' class="mr-2 text-blue-500">';
                        echo '<label class="text-sm text-gray-700">' . esc_html($body->name) . '</label>';
                        echo '</div>';
                    }
                    echo '</div>';
                    echo '</fieldset>';
                } else {
                    echo 'No accreditation bodies found.';
                }
                ?>
            </div>




            <fieldset class="space-y-4">
                <legend class="text-lg font-semibold text-gray-700">Qualification Cost</legend>
                <hr>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">

                    <div class="flex flex-col">

                        <label for="price" class="text-sm font-medium text-gray-700">Qualification Price:</label>
                        <input type="number" name="price" placeholder="Qualification Price"
                            value="<?php echo isset($course) && isset($course->price) ? esc_attr($course->price) : ''; ?>" required
                            class="mt-1 p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>
            </fieldset>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-4 gap-6">
            <div class="flex flex-col">
                <label for="start_date" class="text-sm font-medium text-gray-700">Start Date:</label>
                <input type="date" name="start_date"
                    value="<?php echo isset($course) && isset($course->start_date) ? esc_attr($course->start_date) : ''; ?>" required
                    class="mt-1 p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="flex flex-col">
                <label for="end_date" class="text-sm font-medium text-gray-700">End Date:</label>
                <input type="date" name="end_date" value="<?php echo isset($course) && isset($course->end_date) ? esc_attr($course->end_date) : ''; ?>"
                    required class="mt-1 p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="flex flex-col">
                <label for="status" class="text-sm font-medium text-gray-700">Status:</label>
                <select id="status" name="status" required
                    class="mt-1 p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    <?php 
                    $course_status = isset($course) && isset($course->status) ? $course->status : 'active';
                    ?>
                    <option value="active" <?php echo $course_status == 'active' ? 'selected' : ''; ?>>
                        Active</option>
                    <option value="inactive"
                        <?php echo $course_status == 'inactive' ? 'selected' : ''; ?>>
                        Inactive</option>
                </select>
            </div>
            <div class="flex flex-col">
                <label for="max_students" class="text-sm font-medium text-gray-700">Max Students:</label>
                <input type="number" name="max_students" value="20"
                    value="<?php echo isset($course) && isset($course->max_students) ? esc_attr($course->max_students) : ''; ?>" required
                    class="mt-1 p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
            </div>
        </div>

        <div class="flex justify-end">
            <input type="submit" name="submit_<?php echo $typ; ?>_course"
                class="px-6 py-3 bg-blue-500 text-white rounded-md shadow-md hover:bg-blue-600 focus:outline-none"
                value="<?php echo ucwords($typ); ?> Qualification" />
            <?php if (!$modal): ?>
            <input type="hidden" name="action" value="nds_<?php echo $typ; ?>_course" />
            <?php endif; ?>
        </div>
    <?php if (!$modal): ?>
    </div>
    <?php endif; ?>

<?php
}

function nds_add_courses_page()
{
    // Check if the current user has permissions
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    global $wpdb;
    $program_id = isset($_GET['program_id']) ? intval($_GET['program_id']) : null;
    $invalid_program_in_url = false;
    $program_name = '';

    if (!empty($program_id)) {
        $program_name = (string) $wpdb->get_var(
            $wpdb->prepare("SELECT name FROM {$wpdb->prefix}nds_programs WHERE id = %d", $program_id)
        );
        if ($program_name === '') {
            $invalid_program_in_url = true;
            $program_id = null;
        }
    }
?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Add Qualification</h1>
        <?php if ($invalid_program_in_url): ?>
            <div class="notice notice-warning"><p>The selected Program was not found. Please choose a valid Program.</p></div>
        <?php endif; ?>

        <div class="mx-auto max-w-5xl mt-4">
            <div class="bg-white rounded-xl shadow-xl border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 flex items-center">
                    <span class="dashicons dashicons-plus-alt2 text-blue-600 mr-3 text-xl"></span>
                    <div class="min-w-0">
                        <h3 class="text-xl font-semibold text-gray-900">Add Course</h3>
                        <p class="text-sm text-gray-500 mt-0.5">
                            to <span class="font-medium text-gray-700"><?php echo $program_name ? esc_html($program_name) : 'Selected Program'; ?></span>
                        </p>
                    </div>
                </div>

                <div class="p-6">
                    <form method="POST" action="javascript:void(0);" onsubmit="event.preventDefault(); submitCourseForm(this);">
                        <?php
                        wp_nonce_field('nds_course_nonce', 'nds_course_nonce');
                        echo '<input type="hidden" name="action" value="nds_create_course_ajax">';
                        $typ = "add";
                        course_form($typ, null, $program_id, null, true);
                        ?>
                    </form>
                </div>
            </div>
        </div>

        <script>
            // Same submission flow as nds-programs Add Qualification modal.
            function submitCourseForm(form) {
                const formData = new FormData(form);
                const submitBtn = form.querySelector('input[type="submit"]');
                const originalText = submitBtn ? submitBtn.value : 'Add Qualification';

                formData.set('action', 'nds_create_course_ajax');
                if (!formData.get('nonce') && formData.get('nds_course_nonce')) {
                    formData.set('nonce', formData.get('nds_course_nonce'));
                }

                if (submitBtn) {
                    submitBtn.value = 'Creating...';
                    submitBtn.disabled = true;
                }

                const ajaxUrl = typeof ajaxurl !== 'undefined' ? ajaxurl : '<?php echo admin_url('admin-ajax.php'); ?>';

                fetch(ajaxUrl, {
                    method: 'POST',
                    body: formData
                })
                .then(async response => {
                    const raw = await response.text();
                    const trimmed = raw.trim();

                    if (trimmed === '0') {
                        throw new Error('Server returned 0 (AJAX action handler not resolved).');
                    }

                    try {
                        return JSON.parse(trimmed);
                    } catch (e) {
                        const firstBrace = trimmed.indexOf('{');
                        const lastBrace = trimmed.lastIndexOf('}');
                        if (firstBrace !== -1 && lastBrace !== -1 && lastBrace > firstBrace) {
                            const candidate = trimmed.substring(firstBrace, lastBrace + 1);
                            try {
                                return JSON.parse(candidate);
                            } catch (innerErr) {
                                // Fall through to detailed error below.
                            }
                        }

                        throw new Error('Invalid JSON response: ' + trimmed.substring(0, 300));
                    }
                })
                .then(data => {
                    if (data.success) {
                        if (typeof closeAddCourseModal === 'function') {
                            closeAddCourseModal();
                        }

                        if (typeof NDSNotification !== 'undefined') {
                            NDSNotification.success('Course created successfully!');
                        } else {
                            alert('Course created successfully!');
                        }

                        setTimeout(() => {
                            window.location.href = '<?php echo admin_url('admin.php?page=nds-courses'); ?>';
                        }, 500);
                    } else {
                        const errorMsg = data.data && data.data.message ? data.data.message : (data.data || 'Error creating course');
                        if (typeof NDSNotification !== 'undefined') {
                            NDSNotification.error(errorMsg);
                        } else {
                            alert('Error: ' + errorMsg);
                        }

                        if (submitBtn) {
                            submitBtn.value = originalText;
                            submitBtn.disabled = false;
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    const details = error && error.message ? (' ' + error.message) : '';
                    if (typeof NDSNotification !== 'undefined') {
                        NDSNotification.error('An error occurred. Please try again.' + details);
                    } else {
                        alert('An error occurred. Please try again.' + details);
                    }

                    if (submitBtn) {
                        submitBtn.value = originalText;
                        submitBtn.disabled = false;
                    }
                });
            }
        </script>
    </div>
    <?php
}

function nds_edit_courses_page()
{

    // Check if the current user has permissions
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    if (isset($_GET['edit_course'])) :

        $course_id = $_GET['edit_course'];

        global $wpdb;
        $table_name = $wpdb->prefix . 'nds_courses';
        $program_table = $wpdb->prefix . 'nds_programs';
        $course = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $course_id));
        $url = '';
        $program_name = '';

        if (isset($course)) {
            $program = $wpdb->get_row($wpdb->prepare("SELECT name, page_id FROM $program_table WHERE id = %d", $course->program_id));
            $program_name = $program ? $program->name : 'Program';
            $url = $program && $program->page_id ? get_permalink($program->page_id) : '?page=nds-programs';
        } else {
            $url = '?page=nds-courses';
            $program_name = 'Courses';
        }
        $breadlinks = [
            ["name" => "Home", "slug" => "?page=home"],
            ["name" => $program_name, "slug" => $url],
            ["name" => "$course->name", "slug" => "?page="],
        ];
        $breadlinks_json = urlencode(json_encode($breadlinks));

        if ($course): ?>
            <div class="nds-tailwind-wrapper bg-gray-50 pb-12" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; padding: 2rem; margin-left: -20px; padding-left: 20px; margin-top: -20px;">
                <!-- Header Section -->
                <div class="bg-white shadow-sm border-b border-gray-200 mb-6 rounded-lg">
                    <div class="max-w-7xl mx-auto px-6 py-6">
                        <div class="flex justify-between items-center">
                            <div class="flex items-center space-x-4">
                                <div class="w-12 h-12 bg-gradient-to-br from-blue-600 to-purple-600 rounded-xl flex items-center justify-center shadow-lg">
                                    <i class="fas fa-book text-white text-xl"></i>
                                </div>
                                <div>
                                    <h1 class="text-3xl font-bold text-gray-900 mb-1">Edit Qualification</h1>
                                    <p class="text-gray-600 text-sm">Update qualification information and settings</p>
                                </div>
                            </div>
                            <div class="flex items-center space-x-3">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Breadcrumbs -->
                <div class="max-w-7xl mx-auto mb-6">
                    <?php echo do_shortcode('[nds_breadcrumb data="' . $breadlinks_json . '"]'); ?>
                </div>

                <!-- Main Content -->
                <div class="max-w-7xl mx-auto">
                    <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
                        <form method="POST" action="<?php echo admin_url('admin-post.php'); ?>" class="p-8">
                            <input type="hidden" name="course_id" value="<?php echo esc_attr($course->id); ?>">
                            <?php
                            wp_nonce_field('nds_edit_course', 'nds_courseupdate_nonce');
                            $typ = "update";
                            course_form($typ, $course, $course->program_id, null); ?>
                        </form>
                    </div>
                </div>

                <!-- Success/Error Messages -->
                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const urlParams = new URLSearchParams(window.location.search);
                    const success = urlParams.get('success');
                    const error = urlParams.get('error');
                    
                    if (success === 'updated') {
                        if (window.Toastify) {
                            Toastify({
                                text: 'Qualification updated successfully!',
                                backgroundColor: '#10b981',
                                duration: 3000,
                                gravity: 'top',
                                position: 'right'
                            }).showToast();
                        }
                    } else if (error) {
                        if (window.Toastify) {
                            Toastify({
                                text: 'Error: ' + error.replace(/_/g, ' '),
                                backgroundColor: '#ef4444',
                                duration: 4000,
                                gravity: 'top',
                                position: 'right'
                            }).showToast();
                        }
                    }
                });
                </script>
            </div>

    <?php
        endif;
    endif;
}


// Modern Recipes Page
function nds_recipes_dashboard() {
    // Load the modern content management page
    require_once plugin_dir_path(__FILE__) . 'content-management.php';
    nds_content_management_page();
}

// Modern Recipe Details Page with Tailwind CSS
function nds_recipe_details_page()
{
    // Force load CSS for recipe details page
    $plugin_dir = plugin_dir_path(dirname(__FILE__));
    $css_file = $plugin_dir . 'assets/css/frontend.css';
    if (file_exists($css_file)) {
        wp_enqueue_style('nds-tailwindcss-recipe', plugin_dir_url(dirname(__FILE__)) . 'assets/css/frontend.css', array(), filemtime($css_file), 'all');
        // Add comprehensive inline CSS to override WordPress admin styles
        wp_add_inline_style('nds-tailwindcss-recipe', '
            .nds-tailwind-wrapper {
                all: initial !important;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
                background-color: #f9fafb !important;
                min-height: 100vh !important;
                padding: 2rem !important;
            }
            .nds-tailwind-wrapper * {
                box-sizing: border-box !important;
            }
            .nds-tailwind-wrapper .bg-gray-50 { background-color: #f9fafb !important; }
            .nds-tailwind-wrapper .bg-white { background-color: #ffffff !important; }
            .nds-tailwind-wrapper .bg-purple-600 { background-color: #9333ea !important; }
            .nds-tailwind-wrapper .bg-red-600 { background-color: #dc2626 !important; }
            .nds-tailwind-wrapper .text-gray-900 { color: #111827 !important; }
            .nds-tailwind-wrapper .text-gray-600 { color: #4b5563 !important; }
            .nds-tailwind-wrapper .text-white { color: #ffffff !important; }
            .nds-tailwind-wrapper .text-purple-600 { color: #9333ea !important; }
            .nds-tailwind-wrapper .rounded-xl { border-radius: 0.75rem !important; }
            .nds-tailwind-wrapper .rounded-lg { border-radius: 0.5rem !important; }
            .nds-tailwind-wrapper .shadow-sm { box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05) !important; }
            .nds-tailwind-wrapper .shadow-md { box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1) !important; }
            .nds-tailwind-wrapper .border { border-width: 1px !important; }
            .nds-tailwind-wrapper .border-gray-200 { border-color: #e5e7eb !important; }
            .nds-tailwind-wrapper .p-8 { padding: 2rem !important; }
            .nds-tailwind-wrapper .p-6 { padding: 1.5rem !important; }
            .nds-tailwind-wrapper .p-4 { padding: 1rem !important; }
            .nds-tailwind-wrapper .px-6 { padding-left: 1.5rem !important; padding-right: 1.5rem !important; }
            .nds-tailwind-wrapper .py-4 { padding-top: 1rem !important; padding-bottom: 1rem !important; }
            .nds-tailwind-wrapper .py-3 { padding-top: 0.75rem !important; padding-bottom: 0.75rem !important; }
            .nds-tailwind-wrapper .mb-8 { margin-bottom: 2rem !important; }
            .nds-tailwind-wrapper .mb-6 { margin-bottom: 1.5rem !important; }
            .nds-tailwind-wrapper .mb-4 { margin-bottom: 1rem !important; }
            .nds-tailwind-wrapper .mb-2 { margin-bottom: 0.5rem !important; }
            .nds-tailwind-wrapper .text-4xl { font-size: 2.25rem !important; line-height: 2.5rem !important; }
            .nds-tailwind-wrapper .text-xl { font-size: 1.25rem !important; line-height: 1.75rem !important; }
            .nds-tailwind-wrapper .text-lg { font-size: 1.125rem !important; line-height: 1.75rem !important; }
            .nds-tailwind-wrapper .text-sm { font-size: 0.875rem !important; line-height: 1.25rem !important; }
            .nds-tailwind-wrapper .font-bold { font-weight: 700 !important; }
            .nds-tailwind-wrapper .font-semibold { font-weight: 600 !important; }
            .nds-tailwind-wrapper .font-medium { font-weight: 500 !important; }
            .nds-tailwind-wrapper .flex { display: flex !important; }
            .nds-tailwind-wrapper .items-center { align-items: center !important; }
            .nds-tailwind-wrapper .justify-between { justify-content: space-between !important; }
            .nds-tailwind-wrapper .space-x-2 > * + * { margin-left: 0.5rem !important; }
            .nds-tailwind-wrapper .space-x-3 > * + * { margin-left: 0.75rem !important; }
            .nds-tailwind-wrapper .space-x-4 > * + * { margin-left: 1rem !important; }
            .nds-tailwind-wrapper .gap-3 { gap: 0.75rem !important; }
            .nds-tailwind-wrapper .gap-8 { gap: 2rem !important; }
            .nds-tailwind-wrapper .max-w-7xl { max-width: 80rem !important; }
            .nds-tailwind-wrapper .mx-auto { margin-left: auto !important; margin-right: auto !important; }
            .nds-tailwind-wrapper .grid { display: grid !important; }
            .nds-tailwind-wrapper .grid-cols-1 { grid-template-columns: repeat(1, minmax(0, 1fr)) !important; }
            .nds-tailwind-wrapper .grid-cols-2 { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; }
            .nds-tailwind-wrapper .grid-cols-3 { grid-template-columns: repeat(3, minmax(0, 1fr)) !important; }
            .nds-tailwind-wrapper .lg\\:grid-cols-3 { grid-template-columns: repeat(3, minmax(0, 1fr)) !important; }
            .nds-tailwind-wrapper .lg\\:col-span-2 { grid-column: span 2 / span 2 !important; }
            .nds-tailwind-wrapper .overflow-hidden { overflow: hidden !important; }
            .nds-tailwind-wrapper .hover\\:bg-purple-700:hover { background-color: #7c3aed !important; }
            .nds-tailwind-wrapper .hover\\:bg-red-700:hover { background-color: #b91c1c !important; }
            .nds-tailwind-wrapper .hover\\:text-blue-600:hover { color: #2563eb !important; }
            .nds-tailwind-wrapper .transition-colors { 
                transition-property: color, background-color, border-color, text-decoration-color, fill, stroke !important;
                transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1) !important;
                transition-duration: 150ms !important;
            }
            .nds-tailwind-wrapper .duration-200 { transition-duration: 200ms !important; }
            .nds-tailwind-wrapper .bg-gradient-to-r { background-image: linear-gradient(to right, var(--tw-gradient-stops)) !important; }
            .nds-tailwind-wrapper .from-purple-600 { --tw-gradient-from: #9333ea !important; --tw-gradient-to: rgb(147 51 234 / 0) !important; --tw-gradient-stops: var(--tw-gradient-from), var(--tw-gradient-to) !important; }
            .nds-tailwind-wrapper .to-purple-700 { --tw-gradient-to: #7c3aed !important; }
            .nds-tailwind-wrapper .border-b { border-bottom-width: 1px !important; }
            .nds-tailwind-wrapper .border-gray-200 { border-color: #e5e7eb !important; }
            .nds-tailwind-wrapper .aspect-square { aspect-ratio: 1 / 1 !important; }
            .nds-tailwind-wrapper .object-cover { object-fit: cover !important; }
            .nds-tailwind-wrapper .hover\\:scale-105:hover { transform: scale(1.05) !important; }
            .nds-tailwind-wrapper .transition-transform { transition-property: transform !important; transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1) !important; transition-duration: 150ms !important; }
            .nds-tailwind-wrapper .cursor-pointer { cursor: pointer !important; }
            .nds-tailwind-wrapper .flex-shrink-0 { flex-shrink: 0 !important; }
            .nds-tailwind-wrapper .w-8 { width: 2rem !important; }
            .nds-tailwind-wrapper .h-8 { height: 2rem !important; }
            .nds-tailwind-wrapper .bg-blue-100 { background-color: #dbeafe !important; }
            .nds-tailwind-wrapper .text-blue-600 { color: #2563eb !important; }
            .nds-tailwind-wrapper .rounded-full { border-radius: 9999px !important; }
            .nds-tailwind-wrapper .flex-1 { flex: 1 1 0% !important; }
            .nds-tailwind-wrapper .space-y-4 > * + * { margin-top: 1rem !important; }
            .nds-tailwind-wrapper .space-y-6 > * + * { margin-top: 1.5rem !important; }
            .nds-tailwind-wrapper .col-span-full { grid-column: 1 / -1 !important; }
            .nds-tailwind-wrapper .py-8 { padding-top: 2rem !important; padding-bottom: 2rem !important; }
            .nds-tailwind-wrapper .text-4xl { font-size: 2.25rem !important; line-height: 2.5rem !important; }
            .nds-tailwind-wrapper .text-gray-300 { color: #d1d5db !important; }
            .nds-tailwind-wrapper .text-gray-500 { color: #6b7280 !important; }
            .nds-tailwind-wrapper .text-gray-700 { color: #374151 !important; }
            .nds-tailwind-wrapper .bg-gray-50 { background-color: #f9fafb !important; }
            .nds-tailwind-wrapper .text-green-600 { color: #16a34a !important; }
            .nds-tailwind-wrapper .w-full { width: 100% !important; }
            .nds-tailwind-wrapper .h-full { height: 100% !important; }
            .nds-tailwind-wrapper .backdrop-blur-sm { backdrop-filter: blur(4px) !important; }
            .nds-tailwind-wrapper .drop-shadow-lg { filter: drop-shadow(0 10px 8px rgb(0 0 0 / 0.04)) drop-shadow(0 4px 3px rgb(0 0 0 / 0.1)) !important; }
            .nds-tailwind-wrapper .rounded-2xl { border-radius: 1rem !important; }
            .nds-tailwind-wrapper .shadow-xl { box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 10px 10px -5px rgb(0 0 0 / 0.04) !important; }
            .nds-tailwind-wrapper .from-slate-50 { --tw-gradient-from: #f8fafc !important; --tw-gradient-to: rgb(248 250 252 / 0) !important; --tw-gradient-stops: var(--tw-gradient-from), var(--tw-gradient-to) !important; }
            .nds-tailwind-wrapper .via-blue-50 { --tw-gradient-to: rgb(239 246 255 / 0) !important; --tw-gradient-stops: var(--tw-gradient-from), #eff6ff, var(--tw-gradient-to) !important; }
            .nds-tailwind-wrapper .to-indigo-100 { --tw-gradient-to: #e0e7ff !important; }
            .nds-tailwind-wrapper .from-purple-600 { --tw-gradient-from: #9333ea !important; --tw-gradient-to: rgb(147 51 234 / 0) !important; --tw-gradient-stops: var(--tw-gradient-from), var(--tw-gradient-to) !important; }
            .nds-tailwind-wrapper .via-pink-600 { --tw-gradient-to: rgb(219 39 119 / 0) !important; --tw-gradient-stops: var(--tw-gradient-from), #db2777, var(--tw-gradient-to) !important; }
            .nds-tailwind-wrapper .to-red-500 { --tw-gradient-to: #ef4444 !important; }
            .nds-tailwind-wrapper .bg-opacity-20 { background-color: rgb(255 255 255 / 0.2) !important; }
            .nds-tailwind-wrapper .bg-opacity-30 { background-color: rgb(255 255 255 / 0.3) !important; }
            .nds-tailwind-wrapper .bg-opacity-40 { background-color: rgb(0 0 0 / 0.4) !important; }
            .nds-tailwind-wrapper .text-6xl { font-size: 3.75rem !important; line-height: 1 !important; }
            .nds-tailwind-wrapper .text-8xl { font-size: 6rem !important; line-height: 1 !important; }
            .nds-tailwind-wrapper .opacity-50 { opacity: 0.5 !important; }
            .nds-tailwind-wrapper .leading-relaxed { line-height: 1.625 !important; }
            .nds-tailwind-wrapper .from-green-50 { --tw-gradient-from: #f0fdf4 !important; --tw-gradient-to: rgb(240 253 244 / 0) !important; --tw-gradient-stops: var(--tw-gradient-from), var(--tw-gradient-to) !important; }
            .nds-tailwind-wrapper .to-emerald-50 { --tw-gradient-to: #ecfdf5 !important; }
            .nds-tailwind-wrapper .border-green-100 { border-color: #dcfce7 !important; }
            .nds-tailwind-wrapper .from-blue-50 { --tw-gradient-from: #eff6ff !important; --tw-gradient-to: rgb(239 246 255 / 0) !important; --tw-gradient-stops: var(--tw-gradient-from), var(--tw-gradient-to) !important; }
            .nds-tailwind-wrapper .to-cyan-50 { --tw-gradient-to: #ecfeff !important; }
            .nds-tailwind-wrapper .border-blue-100 { border-color: #dbeafe !important; }
            .nds-tailwind-wrapper .from-blue-500 { --tw-gradient-from: #3b82f6 !important; --tw-gradient-to: rgb(59 130 246 / 0) !important; --tw-gradient-stops: var(--tw-gradient-from), var(--tw-gradient-to) !important; }
            .nds-tailwind-wrapper .to-blue-600 { --tw-gradient-to: #2563eb !important; }
            .nds-tailwind-wrapper .w-10 { width: 2.5rem !important; }
            .nds-tailwind-wrapper .h-10 { height: 2.5rem !important; }
            .nds-tailwind-wrapper .w-12 { width: 3rem !important; }
            .nds-tailwind-wrapper .h-12 { height: 3rem !important; }
            .nds-tailwind-wrapper .text-lg { font-size: 1.125rem !important; line-height: 1.75rem !important; }
            .nds-tailwind-wrapper .space-y-6 > * + * { margin-top: 1.5rem !important; }
            .nds-tailwind-wrapper .py-12 { padding-top: 3rem !important; padding-bottom: 3rem !important; }
            .nds-tailwind-wrapper .text-6xl { font-size: 3.75rem !important; line-height: 1 !important; }
            .nds-tailwind-wrapper .from-purple-50 { --tw-gradient-from: #faf5ff !important; --tw-gradient-to: rgb(250 245 255 / 0) !important; --tw-gradient-stops: var(--tw-gradient-from), var(--tw-gradient-to) !important; }
            .nds-tailwind-wrapper .from-red-50 { --tw-gradient-from: #fef2f2 !important; --tw-gradient-to: rgb(254 242 242 / 0) !important; --tw-gradient-stops: var(--tw-gradient-from), var(--tw-gradient-to) !important; }
            .nds-tailwind-wrapper .text-purple-600 { color: #9333ea !important; }
            .nds-tailwind-wrapper .text-red-600 { color: #dc2626 !important; }
            .nds-tailwind-wrapper .text-blue-600 { color: #2563eb !important; }
            .nds-tailwind-wrapper .text-green-600 { color: #16a34a !important; }
            .nds-tailwind-wrapper .min-w-0 { min-width: 0px !important; }
            .nds-tailwind-wrapper .truncate { overflow: hidden !important; text-overflow: ellipsis !important; white-space: nowrap !important; }
            .nds-tailwind-wrapper .text-xs { font-size: 0.75rem !important; line-height: 1rem !important; }
            .nds-tailwind-wrapper .py-8 { padding-top: 2rem !important; padding-bottom: 2rem !important; }
            .nds-tailwind-wrapper .text-4xl { font-size: 2.25rem !important; line-height: 2.5rem !important; }
            .nds-tailwind-wrapper .fixed { position: fixed !important; }
            .nds-tailwind-wrapper .inset-0 { top: 0px !important; right: 0px !important; bottom: 0px !important; left: 0px !important; }
            .nds-tailwind-wrapper .bg-opacity-75 { background-color: rgb(0 0 0 / 0.75) !important; }
            .nds-tailwind-wrapper .z-50 { z-index: 50 !important; }
            .nds-tailwind-wrapper .max-w-4xl { max-width: 56rem !important; }
            .nds-tailwind-wrapper .max-h-full { max-height: 100% !important; }
            .nds-tailwind-wrapper .top-4 { top: 1rem !important; }
            .nds-tailwind-wrapper .right-4 { right: 1rem !important; }
            .nds-tailwind-wrapper .text-2xl { font-size: 1.5rem !important; line-height: 2rem !important; }
            .nds-tailwind-wrapper .text-gray-300 { color: #d1d5db !important; }
            .nds-tailwind-wrapper .z-10 { z-index: 10 !important; }
            .nds-tailwind-wrapper .max-w-full { max-width: 100% !important; }
        ');
    }
    wp_enqueue_style('nds-icons', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css', array(), null, 'all');

    // Load the modern Tailwind recipe details page
    $tailwind_file = plugin_dir_path(__FILE__) . 'recipe-details-tailwind.php';
    if (file_exists($tailwind_file)) {
        require_once $tailwind_file;
        nds_recipe_details_page_tailwind();
    } else {
        // Fallback to basic implementation
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    if (isset($_GET['id'])) {
            $id = intval($_GET['id']);
        nds_recipes_form('update', $id);
    } else {
        nds_recipes_form('add', null);
        }
    }
}

// Modern Add Recipe Page with Tailwind CSS
function nds_add_recipes_page()
{
    // Verify CSS is loaded
    if (!wp_style_is('nds-tailwindcss', 'enqueued')) {
        $plugin_dir = plugin_dir_path(dirname(__FILE__));
        $css_file = $plugin_dir . 'assets/css/frontend.css';
        if (file_exists($css_file)) {
            wp_enqueue_style('nds-tailwindcss', plugin_dir_url(dirname(__FILE__)) . 'assets/css/frontend.css', array(), filemtime($css_file), 'all');
        }
        wp_enqueue_style('nds-icons', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css', array(), null, 'all');
    }

    // Load the modern add recipe page
    if (file_exists(plugin_dir_path(__FILE__) . 'add-recipe-tailwind.php')) {
        require_once plugin_dir_path(__FILE__) . 'add-recipe-tailwind.php';
        nds_add_recipe_page_tailwind();
    } else {
        // Fallback to basic implementation
        echo '<div class="wrap"><h1>Add Recipe</h1><p>Tailwind implementation not found.</p></div>';
    }
}

// Student Dashboard
// Modern Learner Dashboard
function nds_students_dashboard() {
    // Load the modern learner management page
    try {
        $file_path = plugin_dir_path(__FILE__) . 'learner-management.php';
        if (file_exists($file_path)) {
            require_once $file_path;
            if (function_exists('nds_learner_management_page')) {
                nds_learner_management_page();
            } else {
                echo '<div class="wrap"><h1>Learner Dashboard</h1><p>Function nds_learner_management_page not found.</p></div>';
            }
        } else {
            echo '<div class="wrap"><h1>Learner Dashboard</h1><p>File learner-management.php not found at: ' . $file_path . '</p></div>';
        }
    } catch (Exception $e) {
        echo '<div class="wrap"><h1>Learner Dashboard</h1><p>Error: ' . $e->getMessage() . '</p></div>';
    }
}

// All Students Page
function nds_all_students_page() {
    // Load the modern learner management page
    require_once plugin_dir_path(__FILE__) . 'learner-management.php';
    nds_learner_management_page();
}

// Students Mosaic Grid Page - function provided by students-mosaic-grid.php (included at top of file)

// Add Student Page
function nds_add_student_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    nds_student_form('add', null);
}

// Edit Student Page
function nds_edit_student_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    $student_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if (!$student_id) {
        wp_die('Invalid student ID');
    }
    
    $student = nds_get_student($student_id);
    if (!$student) {
        wp_die('Student not found');
    }
    
    nds_student_form('edit', $student);
}

// Student Applications Page
function nds_student_applications_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    $applications = nds_get_students(['status' => 'prospect', 'limit' => 50]);
    ?>
    
    <div class="wrap">
        <h1 class="wp-heading-inline">Student Applications</h1>
        
        <div class="bg-white rounded-lg shadow-md mt-6">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Applicant</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Phone</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Applied</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (!empty($applications)): ?>
                            <?php foreach ($applications as $application): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo esc_html($application->first_name . ' ' . $application->last_name); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo esc_html($application->email); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo esc_html($application->phone); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo date('M j, Y', strtotime($application->created_at)); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="<?php echo admin_url('admin.php?page=nds-edit-learner&id=' . $application->id); ?>" 
                                           class="text-blue-600 hover:text-blue-900 mr-3">Review</a>
                                        <button onclick="approveApplication(<?php echo $application->id; ?>)" 
                                                class="text-green-600 hover:text-green-900 mr-3">Approve</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="px-6 py-4 text-center text-gray-500">No applications found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
    function approveApplication(studentId) {
        if (!confirm('Approve this application?')) return;
        const params = new URLSearchParams();
        params.append('action', 'nds_approve_student_application');
        params.append('student_id', studentId);
        params.append('nonce', '<?php echo wp_create_nonce('nds_approve_application_nonce'); ?>');

        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            credentials: 'same-origin',
            body: params.toString()
        })
        .then(r => r.json())
        .then(data => {
            if (data && data.success) {
                // Refresh to reflect updated counts and list
                window.location.reload();
            } else {
                alert('Failed to approve: ' + (data && data.data ? data.data : 'unknown error'));
            }
        })
        .catch(() => alert('Network error while approving'));
    }
    </script>
    <?php
}

// Assigned Students Page
function nds_assigned_students_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    // This will be implemented when we add enrollment functionality
    ?>
    
    <div class="wrap">
        <h1 class="wp-heading-inline">Assigned Students</h1>
        
        <div class="bg-white p-6 rounded-lg shadow-md mt-6">
            <p class="text-gray-500">This feature will show students assigned to specific courses and instructors.</p>
            <p class="text-gray-500 mt-2">Coming soon with the enrollment management system.</p>
        </div>
    </div>
    <?php
}

// Student Form
function nds_student_form($action, $student = null) {
    $is_edit = $action === 'edit';
    $student_data = $student ? (array) $student : [];
    ?>
    
    <div class="wrap" style="margin:0; padding:0; background:#f9fafb; min-height:100vh; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
        <!-- Header matching Student Management -->
        <div class="bg-white shadow-sm border-b border-gray-200">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center py-6">
                    <div class="flex items-center space-x-4">
                        <div class="w-12 h-12 bg-green-600 rounded-xl flex items-center justify-center shadow-lg">
                            <i class="fas fa-user-plus text-white text-2xl"></i>
                        </div>
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900" style="margin:0; padding:0;">
                                <?php echo $is_edit ? 'Edit Student' : 'Add New Student'; ?>
                            </h1>
                            <p class="text-sm text-gray-600 mt-1" style="margin:0.25rem 0 0 0;">
                                <?php echo $is_edit ? 'Update student information' : 'Fill in the details to register a new student'; ?>
                                <span id="step-indicator">— Step 1 of 4</span>
                            </p>
                        </div>
                    </div>
                    <a href="<?php echo admin_url('admin.php?page=nds-all-learners'); ?>" 
                       class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-2.5 px-5 rounded-lg flex items-center gap-2 transition-all duration-200 border border-gray-300"
                       style="text-decoration:none;">
                        <i class="fas fa-arrow-left"></i>
                        Back to Students
                    </a>
                </div>
            </div>
        </div>

        <!-- Breadcrumb Navigation -->
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 pt-6 pb-4">
            <nav class="flex items-center space-x-2 text-sm text-gray-600">
                <a href="<?php echo admin_url('admin.php?page=nds-academy'); ?>" class="hover:text-green-600 transition-colors flex items-center">
                    <i class="fas fa-home mr-1"></i>NDS Academy
                </a>
                <i class="fas fa-chevron-right text-gray-400 text-xs"></i>
                <a href="<?php echo admin_url('admin.php?page=nds-all-learners'); ?>" class="hover:text-green-600 transition-colors">Student Management</a>
                <i class="fas fa-chevron-right text-gray-400 text-xs"></i>
                <span class="text-gray-900 font-medium"><?php echo $is_edit ? 'Edit Student' : 'Add New Student'; ?></span>
            </nav>
        </div>
        
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 pb-8">
        <form method="POST" action="<?php echo admin_url('admin-post.php'); ?>" id="student-multistep-form" onsubmit="return validateFinalStep()">
            <?php wp_nonce_field('nds_' . $action . '_student_nonce_action', 'nds_' . $action . '_student_nonce'); ?>
            <input type="hidden" name="action" value="nds_<?php echo $action; ?>_student">
            <?php if ($is_edit && isset($student_data['id'])): ?>
                <input type="hidden" name="student_id" value="<?php echo intval($student_data['id']); ?>">
            <?php endif; ?>
            <input type="hidden" name="source" value="admin">
            
            <!-- Progress Indicator -->
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 mb-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4 flex-1">
                        <!-- Step 1 -->
                        <div class="flex items-center">
                            <div id="step-1-indicator" class="w-10 h-10 rounded-full bg-green-600 text-white flex items-center justify-center font-semibold transition-all">1</div>
                            <span class="ml-2 text-sm font-medium text-gray-700">Personal Info</span>
                        </div>
                        <div class="flex-1 h-1 bg-gray-200 rounded">
                            <div id="progress-1-2" class="h-full bg-gray-200 rounded transition-all"></div>
                        </div>
                        <!-- Step 2 -->
                        <div class="flex items-center">
                            <div id="step-2-indicator" class="w-10 h-10 rounded-full bg-gray-200 text-gray-600 flex items-center justify-center font-semibold transition-all">2</div>
                            <span class="ml-2 text-sm font-medium text-gray-500">Academic</span>
                        </div>
                        <div class="flex-1 h-1 bg-gray-200 rounded">
                            <div id="progress-2-3" class="h-full bg-gray-200 rounded transition-all"></div>
                        </div>
                        <!-- Step 3 -->
                        <div class="flex items-center">
                            <div id="step-3-indicator" class="w-10 h-10 rounded-full bg-gray-200 text-gray-600 flex items-center justify-center font-semibold transition-all">3</div>
                            <span class="ml-2 text-sm font-medium text-gray-500">Guardian</span>
                        </div>
                        <div class="flex-1 h-1 bg-gray-200 rounded">
                            <div id="progress-3-4" class="h-full bg-gray-200 rounded transition-all"></div>
                        </div>
                        <!-- Step 4 -->
                        <div class="flex items-center">
                            <div id="step-4-indicator" class="w-10 h-10 rounded-full bg-gray-200 text-gray-600 flex items-center justify-center font-semibold transition-all">4</div>
                            <span class="ml-2 text-sm font-medium text-gray-500">Additional</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Step 1: Personal Information -->
            <div id="step-1" class="step-container bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                <h2 class="text-lg font-semibold mb-4">Personal Information</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">First Name *</label>
                        <input type="text" name="first_name" value="<?php echo esc_attr($student_data['first_name'] ?? ''); ?>" 
                               required class="w-full border border-gray-300 rounded-md px-3 py-2">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Last Name *</label>
                        <input type="text" name="last_name" value="<?php echo esc_attr($student_data['last_name'] ?? ''); ?>" 
                               required class="w-full border border-gray-300 rounded-md px-3 py-2">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                        <input type="email" name="email" value="<?php echo esc_attr($student_data['email'] ?? ''); ?>" 
                               required class="w-full border border-gray-300 rounded-md px-3 py-2">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                        <input type="tel" name="phone" value="<?php echo esc_attr($student_data['phone'] ?? ''); ?>" 
                               class="w-full border border-gray-300 rounded-md px-3 py-2">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">National ID</label>
                        <input type="text" name="national_id" value="<?php echo esc_attr($student_data['national_id'] ?? ''); ?>" 
                               class="w-full border border-gray-300 rounded-md px-3 py-2">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Passport Number</label>
                        <input type="text" name="passport_number" value="<?php echo esc_attr($student_data['passport_number'] ?? ''); ?>" 
                               class="w-full border border-gray-300 rounded-md px-3 py-2">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date of Birth</label>
                        <input type="date" name="date_of_birth" value="<?php echo esc_attr($student_data['date_of_birth'] ?? ''); ?>" 
                               class="w-full border border-gray-300 rounded-md px-3 py-2">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Gender *</label>
                        <select name="gender" required class="w-full border border-gray-300 rounded-md px-3 py-2">
                            <option value="">Select Gender</option>
                            <option value="Male" <?php selected($student_data['gender'] ?? '', 'Male'); ?>>Male</option>
                            <option value="Female" <?php selected($student_data['gender'] ?? '', 'Female'); ?>>Female</option>
                            <option value="Other" <?php selected($student_data['gender'] ?? '', 'Other'); ?>>Other</option>
                        </select>
                    </div>
                </div>
                
                <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                        <textarea name="address" rows="3" class="w-full border border-gray-300 rounded-md px-3 py-2"><?php echo esc_textarea($student_data['address'] ?? ''); ?></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Province</label>
                        <select name="province" id="student_province" class="w-full border border-gray-300 rounded-md px-3 py-2" onchange="updateCohort()">
                            <option value="">Select Province</option>
                            <option value="EC" <?php selected($student_data['province'] ?? '', 'EC'); ?>>Eastern Cape</option>
                            <option value="FS" <?php selected($student_data['province'] ?? '', 'FS'); ?>>Free State</option>
                            <option value="GP" <?php selected($student_data['province'] ?? '', 'GP'); ?>>Gauteng</option>
                            <option value="KZN" <?php selected($student_data['province'] ?? '', 'KZN'); ?>>KwaZulu-Natal</option>
                            <option value="LP" <?php selected($student_data['province'] ?? '', 'LP'); ?>>Limpopo</option>
                            <option value="MP" <?php selected($student_data['province'] ?? '', 'MP'); ?>>Mpumalanga</option>
                            <option value="NC" <?php selected($student_data['province'] ?? '', 'NC'); ?>>Northern Cape</option>
                            <option value="NW" <?php selected($student_data['province'] ?? '', 'NW'); ?>>North West</option>
                            <option value="WC" <?php selected($student_data['province'] ?? '', 'WC'); ?>>Western Cape</option>
                        </select>
                    </div>
                </div>
                
                <div class="mt-6">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Profile Photo URL</label>
                    <input type="text" name="profile_photo" value="<?php echo esc_attr($student_data['profile_photo'] ?? ''); ?>" 
                           class="w-full border border-gray-300 rounded-md px-3 py-2">
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Faculty</label>
                        <?php KIT_Commons::FacultySelect('faculty_id', intval($student_data['faculty_id'] ?? 0), '', true, 'Unassigned', 'loadCourses()'); ?>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Qualification</label>
                        <select name="program_id" id="program_id" class="w-full border border-gray-300 rounded-md px-3 py-2">
                            <option value="">Select Faculty First</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">City</label>
                        <input type="text" name="city" value="<?php echo esc_attr($student_data['city'] ?? ''); ?>" 
                               class="w-full border border-gray-300 rounded-md px-3 py-2">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Country</label>
                        <input type="text" name="country" value="<?php echo esc_attr($student_data['country'] ?? 'South Africa'); ?>" 
                               class="w-full border border-gray-300 rounded-md px-3 py-2">
                    </div>
                </div>
            </div>
            
            <!-- Step 2: Academic & Enrollment -->
            <div id="step-2" class="step-container bg-white p-6 rounded-xl shadow-sm border border-gray-100 mt-6" style="display: none;">
                <h2 class="text-lg font-semibold mb-4">Academic & Enrollment Information</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Intake Year *</label>
                        <select name="intake_year" required class="w-full border border-gray-300 rounded-md px-3 py-2">
                            <?php for ($year = date('Y'); $year >= date('Y') - 5; $year--): ?>
                                <option value="<?php echo $year; ?>" <?php selected($student_data['intake_year'] ?? date('Y'), $year); ?>><?php echo $year; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Intake Semester *</label>
                        <select name="intake_semester" required class="w-full border border-gray-300 rounded-md px-3 py-2">
                            <option value="January" <?php selected($student_data['intake_semester'] ?? '', 'January'); ?>>January</option>
                            <option value="June" <?php selected($student_data['intake_semester'] ?? '', 'June'); ?>>June</option>
                            <option value="September" <?php selected($student_data['intake_semester'] ?? '', 'September'); ?>>September</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select name="status" class="w-full border border-gray-300 rounded-md px-3 py-2">
                            <option value="prospect" <?php selected($student_data['status'] ?? 'prospect', 'prospect'); ?>>Prospect</option>
                            <option value="active" <?php selected($student_data['status'] ?? '', 'active'); ?>>Active</option>
                            <option value="graduated" <?php selected($student_data['status'] ?? '', 'graduated'); ?>>Graduated</option>
                            <option value="alumni" <?php selected($student_data['status'] ?? '', 'alumni'); ?>>Alumni</option>
                            <option value="inactive" <?php selected($student_data['status'] ?? '', 'inactive'); ?>>Inactive</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Highest Qualification</label>
                        <input type="text" name="highest_qualification" value="<?php echo esc_attr($student_data['highest_qualification'] ?? ''); ?>" 
                               class="w-full border border-gray-300 rounded-md px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Provincial Cohort (Auto-generated)</label>
                        <input type="text" name="cohort" id="cohort_display" value="<?php echo esc_attr($student_data['cohort'] ?? ''); ?>" 
                               readonly class="w-full border border-gray-200 bg-gray-50 rounded-md px-3 py-2 text-gray-500 font-mono">
                    </div>
                </div>
            </div>
            
            <!-- Step 3: Guardian & Emergency Contact -->
            <div id="step-3" class="step-container bg-white p-6 rounded-xl shadow-sm border border-gray-100 mt-6" style="display: none;">
                <h2 class="text-lg font-semibold mb-4">Guardian Information</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Guardian Name</label>
                        <input type="text" name="guardian_name" value="<?php echo esc_attr($student_data['guardian_name'] ?? ''); ?>" 
                               class="w-full border border-gray-300 rounded-md px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Guardian Phone</label>
                        <input type="text" name="guardian_phone" value="<?php echo esc_attr($student_data['guardian_phone'] ?? ''); ?>" 
                               class="w-full border border-gray-300 rounded-md px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Guardian Email</label>
                        <input type="email" name="guardian_email" value="<?php echo esc_attr($student_data['guardian_email'] ?? ''); ?>" 
                               class="w-full border border-gray-300 rounded-md px-3 py-2">
                    </div>
                </div>
                
                <h2 class="text-lg font-semibold mb-4 mt-6">Emergency Contact</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Emergency Contact Name</label>
                        <input type="text" name="emergency_contact_name" value="<?php echo esc_attr($student_data['emergency_contact_name'] ?? ''); ?>" 
                               class="w-full border border-gray-300 rounded-md px-3 py-2">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Emergency Contact Phone</label>
                        <input type="tel" name="emergency_contact" value="<?php echo esc_attr($student_data['emergency_contact'] ?? ''); ?>" 
                               class="w-full border border-gray-300 rounded-md px-3 py-2">
                    </div>
                </div>
            </div>
            
            <!-- Step 4: Additional Information -->
            <div id="step-4" class="step-container bg-white p-6 rounded-xl shadow-sm border border-gray-100 mt-6" style="display: none;">
                <h2 class="text-lg font-semibold mb-4">Additional Information</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Dietary Restrictions</label>
                        <textarea name="dietary_restrictions" rows="3" class="w-full border border-gray-300 rounded-md px-3 py-2"><?php echo esc_textarea($student_data['dietary_restrictions'] ?? ''); ?></textarea>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Medical Notes</label>
                        <textarea name="medical_notes" rows="3" class="w-full border border-gray-300 rounded-md px-3 py-2"><?php echo esc_textarea($student_data['medical_notes'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <div class="mt-6">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                    <textarea name="notes" rows="3" class="w-full border border-gray-300 rounded-md px-3 py-2"><?php echo esc_textarea($student_data['notes'] ?? ''); ?></textarea>
                </div>
                
                <div class="mt-6">
                    <label class="flex items-center">
                        <input type="checkbox" name="gdpr_consent" value="1" <?php checked($student_data['gdpr_consent'] ?? 0, 1); ?> 
                               class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                        <span class="ml-2 text-sm text-gray-700">GDPR Consent</span>
                    </label>
                </div>
            </div>
            
            <!-- Step Navigation Buttons -->
            <div class="mt-6 flex justify-between items-center">
                <a href="<?php echo admin_url('admin.php?page=nds-all-learners'); ?>" 
                   class="inline-flex items-center px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium">
                    Cancel
                </a>
                
                <div class="flex space-x-3">
                    <button type="button" id="prev-btn" onclick="handlePrevious()" 
                            class="inline-flex items-center px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium" 
                            style="display: none;">
                        <span class="dashicons dashicons-arrow-left-alt2 mr-1 text-base"></span>
                        Previous
                    </button>
                    <button type="button" id="next-btn" onclick="handleNext()" 
                            class="inline-flex items-center px-4 py-2 rounded-lg bg-green-600 hover:bg-green-700 text-white text-sm font-medium">
                        Next
                        <span class="dashicons dashicons-arrow-right-alt2 ml-1 text-base"></span>
                    </button>
                    <button type="submit" id="submit-btn" name="submit_student" 
                            class="inline-flex items-center px-4 py-2 rounded-lg bg-green-600 hover:bg-green-700 text-white text-sm font-medium" 
                            style="display: none;">
                        <span class="dashicons dashicons-yes-alt mr-1 text-base"></span>
                        <?php echo $is_edit ? 'Update Student' : 'Add Student'; ?>
                    </button>
                </div>
            </div>
        </form>
        
        <script>
        // Multistep Form Management
        let currentStep = 1;
        const totalSteps = 4;
        
        function showStep(step) {
            // Hide all steps
            for (let i = 1; i <= totalSteps; i++) {
                const stepEl = document.getElementById('step-' + i);
                if (stepEl) {
                    stepEl.style.display = 'none';
                }
            }
            
            // Show current step
            const currentStepEl = document.getElementById('step-' + step);
            if (currentStepEl) {
                currentStepEl.style.display = 'block';
            }
            
            // Update progress indicator
            updateProgressBar(step);
            
            // Update step indicator text
            const stepIndicator = document.getElementById('step-indicator');
            if (stepIndicator) {
                stepIndicator.textContent = '— Step ' + step + ' of ' + totalSteps;
            }
            
            // Update navigation buttons
            updateNavigationButtons(step);
            
            // Scroll to top
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
        
        function updateProgressBar(step) {
            // Update step indicators
            for (let i = 1; i <= totalSteps; i++) {
                const indicator = document.getElementById('step-' + i + '-indicator');
                const progressBar = document.getElementById('progress-' + i + '-' + (i + 1));
                
                if (indicator) {
                    if (i < step) {
                        // Completed step
                        indicator.className = 'w-10 h-10 rounded-full bg-emerald-600 text-white flex items-center justify-center font-semibold transition-all';
                        indicator.innerHTML = '<span class="dashicons dashicons-yes-alt text-sm"></span>';
                    } else if (i === step) {
                        // Current step
                        indicator.className = 'w-10 h-10 rounded-full bg-green-600 text-white flex items-center justify-center font-semibold transition-all';
                        indicator.textContent = i;
                    } else {
                        // Future step
                        indicator.className = 'w-10 h-10 rounded-full bg-gray-200 text-gray-600 flex items-center justify-center font-semibold transition-all';
                        indicator.textContent = i;
                    }
                }
                
                if (progressBar && i < totalSteps) {
                    if (i < step) {
                        // Completed progress bar
                        progressBar.className = 'h-full bg-emerald-600 rounded transition-all';
                    } else {
                        // Incomplete progress bar
                        progressBar.className = 'h-full bg-gray-200 rounded transition-all';
                    }
                }
            }
        }
        
        function updateNavigationButtons(step) {
            const prevBtn = document.getElementById('prev-btn');
            const nextBtn = document.getElementById('next-btn');
            const submitBtn = document.getElementById('submit-btn');
            
            // Previous button
            if (prevBtn) {
                if (step === 1) {
                    prevBtn.style.display = 'none';
                } else {
                    prevBtn.style.display = 'inline-flex';
                }
            }
            
            // Next/Submit buttons
            if (nextBtn && submitBtn) {
                if (step === totalSteps) {
                    nextBtn.style.display = 'none';
                    submitBtn.style.display = 'inline-flex';
                } else {
                    nextBtn.style.display = 'inline-flex';
                    submitBtn.style.display = 'none';
                }
            }
        }
        
        function validateStep(step) {
            const stepEl = document.getElementById('step-' + step);
            if (!stepEl) return false;
            
            const requiredFields = stepEl.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                // Remove previous error styling
                field.classList.remove('border-red-500');
                const errorMsg = field.parentElement.querySelector('.error-message');
                if (errorMsg) {
                    errorMsg.remove();
                }
                
                // Validate field
                if (!field.value.trim()) {
                    isValid = false;
                    field.classList.add('border-red-500');
                    
                    // Add error message
                    const label = field.previousElementSibling;
                    if (label && label.tagName === 'LABEL') {
                        const errorDiv = document.createElement('div');
                        errorDiv.className = 'error-message text-red-600 text-xs mt-1';
                        errorDiv.textContent = 'This field is required';
                        field.parentElement.appendChild(errorDiv);
                    }
                }
            });
            
            // Step 1 specific validation
            if (step === 1) {
                const email = stepEl.querySelector('[name="email"]');
                if (email && email.value) {
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(email.value)) {
                        isValid = false;
                        email.classList.add('border-red-500');
                        const errorDiv = document.createElement('div');
                        errorDiv.className = 'error-message text-red-600 text-xs mt-1';
                        errorDiv.textContent = 'Please enter a valid email address';
                        email.parentElement.appendChild(errorDiv);
                    }
                }
            }
            
            return isValid;
        }
        
        function handleNext() {
            if (validateStep(currentStep)) {
                if (currentStep < totalSteps) {
                    currentStep++;
                    showStep(currentStep);
                }
            } else {
                // Scroll to first error
                const firstError = document.querySelector('#step-' + currentStep + ' .border-red-500');
                if (firstError) {
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    firstError.focus();
                }
            }
        }
        
        function handlePrevious() {
            if (currentStep > 1) {
                currentStep--;
                showStep(currentStep);
            }
        }
        
        function validateFinalStep() {
            // Validate all steps before submission
            for (let i = 1; i <= totalSteps; i++) {
                if (!validateStep(i)) {
                    // Show the step with errors
                    currentStep = i;
                    showStep(currentStep);
                    return false;
                }
            }
            return true;
        }
        
        function loadCourses() {
            const facultyId = document.getElementById('faculty_id').value;
            const programSelect = document.getElementById('program_id');
            
            // Clear current options
            programSelect.innerHTML = '<option value="">Loading...</option>';
            
            if (!facultyId) {
                programSelect.innerHTML = '<option value="">Select Faculty First</option>';
                return;
            }
            
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=nds_get_programs_by_faculty&faculty_id=' + facultyId + '&nonce=<?php echo wp_create_nonce('nds_get_courses_nonce'); ?>'
            })
            .then(response => response.json())
            .then(data => {
                programSelect.innerHTML = '<option value="">Select Qualification</option>';
                if (data.success && data.data) {
                    data.data.forEach(program => {
                        const option = document.createElement('option');
                        option.value = program.id;
                        option.textContent = program.name;
                        programSelect.appendChild(option);
                    });
                    
                    // Preserve selected program if editing
                    <?php if ($is_edit && isset($student_data['program_id'])): ?>
                    const selectedProgramId = <?php echo intval($student_data['program_id'] ?? 0); ?>;
                    if (selectedProgramId) {
                        programSelect.value = selectedProgramId;
                    }
                    <?php endif; ?>
                }
            })
            .catch(error => {
                console.error('Error loading programs:', error);
                programSelect.innerHTML = '<option value="">Error loading</option>';
            });
        }

        function updateCohort() {
            const province = document.getElementById('student_province').value;
            const cohortField = document.getElementById('cohort_display');
            if (!province) {
                cohortField.value = '';
                return;
            }
            const year = new Date().getFullYear();
            const random = Math.floor(1000 + Math.random() * 9000);
            cohortField.value = `${province}-${year}-${random}`;
        }

        // Initialize on load if needed
        document.addEventListener('DOMContentLoaded', function() {
            if (document.getElementById('faculty_id')?.value) {
                loadCourses();
            }
        });
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Show first step
            showStep(1);
            
            // Load courses on page load if faculty is already selected
            const facultyId = document.getElementById('faculty_id');
            if (facultyId && facultyId.value) {
                loadCourses();
            }
        });
        </script>
        </div><!-- close max-width container -->
    </div>
    <?php
}

// Simple Enrollment Dashboard - Thin Table for Student Enrollment
function nds_enrollments_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    global $wpdb;

    // Get all courses
    $all_courses = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}nds_courses ORDER BY name");

    // Get stats
    $total_students = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}nds_students");
    $enrolled_students = $wpdb->get_var("SELECT COUNT(DISTINCT student_id) FROM {$wpdb->prefix}nds_student_enrollments");
    $unassigned_students = $total_students - $enrolled_students;

    ?>
    <div class="wrap">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <!-- Header -->
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Course Enrollment</h1>
                        <p class="text-sm text-gray-600 mt-1">Manage student enrollment per course</p>
                    </div>
                    <div class="flex items-center space-x-4">
                        <input type="text" id="course-search" placeholder="Search courses..."
                               class="px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <span class="text-sm text-gray-500"><?php echo count($all_courses); ?> courses</span>
                    </div>
                </div>
            </div>

            <!-- Stats Row -->
            <div id="quick-stats-row" class="px-6 py-3 bg-gray-50 border-b border-gray-200">
                <div class="grid grid-cols-4 gap-4">
                    <div class="text-center">
                        <div id="stat-total-students" class="text-lg font-bold text-gray-900"><?php echo $total_students; ?></div>
                        <div class="text-xs text-gray-600">Total Students</div>
                    </div>
                    <div class="text-center">
                        <div id="stat-enrolled" class="text-lg font-bold text-green-600"><?php echo $enrolled_students; ?></div>
                        <div class="text-xs text-gray-600">Enrolled</div>
                    </div>
                    <div class="text-center">
                        <div id="stat-unassigned" class="text-lg font-bold text-amber-600"><?php echo $unassigned_students; ?></div>
                        <div class="text-xs text-gray-600">Unassigned</div>
                    </div>
                    <div class="text-center">
                        <div id="stat-courses" class="text-lg font-bold text-blue-600"><?php echo count($all_courses); ?></div>
                        <div class="text-xs text-gray-600">Courses</div>
                    </div>
                </div>
            </div>

            <!-- Thin Course Table -->
            <div id="coursesTable" class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Course</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Enrolled</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($all_courses as $course):
                            $enrolled_count = $wpdb->get_var($wpdb->prepare(
                                "SELECT COUNT(*) FROM {$wpdb->prefix}nds_student_enrollments WHERE course_id = %d",
                                $course->id
                            ));

                            $status = $enrolled_count > 0 ? 'Active' : 'Empty';
                            $status_color = $enrolled_count > 0 ? 'text-green-600 bg-green-50' : 'text-gray-600 bg-gray-50';
                        ?>
                        <tr class="hover:bg-gray-50 transition-colors duration-200" data-course-id="<?php echo $course->id; ?>">
                            <td class="px-4 py-3">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-8 w-8">
                                        <div class="h-8 w-8 rounded bg-blue-100 flex items-center justify-center border">
                                            <span class="text-xs font-bold text-blue-600"><?php echo strtoupper(substr($course->name, 0, 1)); ?></span>
                                        </div>
                                    </div>
                                    <div class="ml-3">
                                        <div class="text-sm font-medium text-gray-900"><?php echo esc_html($course->name); ?></div>
                                        <div class="text-xs text-gray-500">ID: <?php echo $course->id; ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span id="course-enrolled-<?php echo $course->id; ?>" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $status_color; ?>">
                                    <?php echo $enrolled_count; ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $status_color; ?>">
                                    <?php echo $status; ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <button onclick="openEnrollmentModal(<?php echo $course->id; ?>, '<?php echo esc_js($course->name); ?>')"
                                        class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    </svg>
                                    Manage
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Enrollment Modal -->
        <div id="enrollment-modal" class="enrollment-modal">
            <div class="modal-content">
                <!-- Enhanced Modal Header -->
                <div class="modal-header">
                    <div>
                        <h3 class="text-2xl font-bold text-gray-900" id="modal-course-title">Course Enrollment</h3>
                        <p class="text-gray-600 mt-1">Manage student enrollments by dragging and dropping</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <button onclick="simulateEnrollFirstThree()" class="px-4 py-2 text-sm rounded-lg bg-blue-600 text-white hover:bg-blue-700 transition-all duration-200 shadow-md hover:shadow-lg">
                            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            Enroll First 3
                        </button>
                        <button onclick="unenrollAllInModal()" class="px-4 py-2 text-sm rounded-lg bg-red-600 text-white hover:bg-red-700 transition-all duration-200 shadow-md hover:shadow-lg">
                            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"></path>
                            </svg>
                            Unenroll All
                        </button>
                        <button onclick="closeEnrollmentModal()" class="close-btn" title="Close">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                 </div>

                <!-- Enhanced Modal Body -->
                <div class="modal-body">
                    <!-- Drag Instruction -->
                    <div class="drag-instruction">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"></path>
                        </svg>
                        <strong>Drag & Drop:</strong> Click and drag students between the "Enrolled" and "Available" sections to manage enrollments
                             </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                        <!-- Enhanced Enrolled Students Section -->
                        <div class="enrollment-section">
                            <h3 class="text-lg">
                                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                Enrolled Students
                                <span class="student-count ml-auto" id="enrolled-count">0</span>
                            </h3>

                            <div class="table-container">
                                <div id="enrolled-drop-zone" class="drop-zone" data-drop-target="enrolled">
                                    <div class="drop-zone-label">Drag students here to enroll them</div>
                                    <table class="min-w-full">
                                        <thead>
                                            <tr>
                                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Student</th>
                                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">ID</th>
                                         </tr>
                                     </thead>
                                        <tbody id="enrolled-table-body" class="bg-white">
                                            <!-- Enrolled students will be populated here -->
                                        </tbody>
                                 </table>
                             </div>
                         </div>
                             </div>

                        <!-- Enhanced Available Students Section -->
                        <div class="enrollment-section">
                            <h3 class="text-lg">
                                <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                                Available Students
                                <span class="student-count ml-auto" id="available-count">0</span>
                            </h3>

                            <div class="table-container">
                                <div id="available-drop-zone" class="drop-zone" data-drop-target="available">
                                    <div class="drop-zone-label">Drag students here to unenroll them</div>
                                    <table class="min-w-full">
                                        <thead>
                                            <tr>
                                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Student</th>
                                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">ID</th>
                                         </tr>
                                     </thead>
                                        <tbody id="available-table-body" class="bg-white">
                                            <!-- Available students will be populated here -->
                                        </tbody>
                                 </table>
                                </div>
                             </div>
                         </div>
                     </div>

                    <!-- Enhanced Search Section -->
                    <div class="mt-8">
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                            </div>
                            <input type="text" id="modal-student-search" placeholder="Search students by name or ID..."
                                   class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm">
                        </div>
                     </div>
                 </div>

                                <!-- Enhanced Modal Footer -->
                <div class="modal-footer">
                    <div class="footer-info">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                        </svg>
                        Review changes before saving
                    </div>
                    <div class="flex gap-3">
                        <button id="save-changes-btn" onclick="saveAllChanges()"
                                class="px-6 py-3 bg-gradient-to-r from-green-500 to-green-600 text-white rounded-lg hover:from-green-600 hover:to-green-700 disabled:from-gray-400 disabled:to-gray-500 disabled:cursor-not-allowed transition-all duration-200 shadow-lg hover:shadow-xl font-medium transform hover:scale-105 disabled:hover:scale-100 disabled:shadow-md"
                                disabled>
                            <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            No Changes to Save
                        </button>
                        <button onclick="closeEnrollmentModal()" class="px-6 py-3 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-all duration-200 shadow-md hover:shadow-lg font-medium">
                            <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                         Close
                     </button>
                    </div>
                 </div>
             </div>
         </div>
    </div>

    <style>
        /* Enhanced Modal Styles */
        .enrollment-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background-color: rgba(0, 0, 0, 0.8);
            z-index: 9999;
            display: none;
            backdrop-filter: blur(8px);
        }

        .enrollment-modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .enrollment-modal .modal-content {
            background: white;
            border-radius: 20px;
            width: 95vw;
            max-width: 1400px;
            height: 90vh;
            max-height: 900px;
            overflow-y: auto;
            box-shadow: 0 32px 64px -12px rgba(0, 0, 0, 0.35);
            border: 1px solid rgba(255, 255, 255, 0.2);
            display: flex;
            flex-direction: column;
        }
        .enrollment-modal .modal-header {
            padding: 2rem;
            border-bottom: 1px solid rgba(229, 231, 235, 0.5);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(249, 250, 251, 0.95) 100%);
            backdrop-filter: blur(12px);
            border-radius: 20px 20px 0 0;
            flex-shrink: 0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }
        .enrollment-modal .modal-body {
            padding: 2rem;
            flex: 1;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }
        .enrollment-modal .close-btn {
            background: #f3f4f6;
            border: none;
            font-size: 1.25rem;
            cursor: pointer;
            color: #6b7280;
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        .enrollment-modal .close-btn:hover {
            background: #e5e7eb;
            color: #374151;
            transform: scale(1.05);
        }

        /* Enhanced Drag and Drop Styles */
        .drop-zone {
            min-height: 300px;
            max-height: 400px;
            border: 2px dashed rgba(156, 163, 175, 0.5);
            border-radius: 16px;
            background: linear-gradient(135deg, rgba(249, 250, 251, 0.8) 0%, rgba(243, 244, 246, 0.8) 100%);
            backdrop-filter: blur(8px);
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow-y: auto;
            overflow-x: hidden;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.1);
        }

        .drop-zone::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, transparent 40%, rgba(59, 130, 246, 0.06) 50%, transparent 60%);
            opacity: 0;
            transition: opacity 0.25s ease;
            pointer-events: none;
            border-radius: 16px;
        }

        .drop-zone.drag-over {
            border-color: rgba(59, 130, 246, 0.6);
            background: linear-gradient(135deg, rgba(239, 246, 255, 0.9) 0%, rgba(219, 234, 254, 0.9) 100%);
            transform: scale(1.01);
            box-shadow:
                0 20px 25px -5px rgba(59, 130, 246, 0.15),
                0 10px 10px -5px rgba(59, 130, 246, 0.08),
                inset 0 1px 0 rgba(255, 255, 255, 0.8);
        }

        .drop-zone.drag-over::before {
            opacity: 1;
            animation: shimmer 2s ease-in-out infinite;
        }

        .drop-zone.drag-over .drop-zone-label {
            color: #2563eb;
            transform: translateY(-2px);
            text-shadow: 0 1px 2px rgba(37, 99, 235, 0.1);
        }

        .drop-zone-label {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: rgba(107, 114, 128, 0.8);
            font-size: 1.125rem;
            font-weight: 500;
            text-align: center;
            pointer-events: none;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 1;
            letter-spacing: -0.01em;
        }

        .drop-zone.has-content .drop-zone-label {
            opacity: 0.7;
            top: 20px;
            transform: translateX(-50%);
            font-size: 0.875rem;
            font-weight: 400;
            color: rgba(75, 85, 99, 0.9);
        }

        /* Ensure table elements don't interfere with drag and drop */
        table, tbody, thead, tr, td, th {
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
        }

        /* Professional Drag and Drop Styling */
        .student-row {
            cursor: grab;
            transition: all 0.15s cubic-bezier(0.4, 0, 0.2, 1);
            border-radius: 12px;
            margin-bottom: 6px;
            border: 2px solid transparent;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(8px);
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
            position: relative;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        /* Subtle background pattern for depth */
        .student-row::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(0,0,0,0.02) 100%);
            opacity: 0;
            transition: opacity 0.15s ease;
            pointer-events: none;
        }

        .student-row:hover {
            background: rgba(59, 130, 246, 0.04);
            border-color: rgba(59, 130, 246, 0.2);
            transform: translateY(-2px) scale(1.01);
            box-shadow:
                0 8px 25px -5px rgba(0, 0, 0, 0.08),
                0 4px 10px -3px rgba(0, 0, 0, 0.04),
                inset 0 1px 0 rgba(255, 255, 255, 0.6);
        }

        .student-row:hover::after {
            opacity: 1;
        }

        .student-row:active {
            cursor: grabbing;
            transform: translateY(0) scale(0.98);
            transition-duration: 0.1s;
        }

        .student-row.dragging {
            opacity: 0.95;
            transform: rotate(2deg) scale(1.02);
            box-shadow:
                0 25px 50px -12px rgba(0, 0, 0, 0.25),
                0 0 0 1px rgba(59, 130, 246, 0.3),
                inset 0 1px 0 rgba(255, 255, 255, 0.8);
            z-index: 1000;
            border-color: rgba(59, 130, 246, 0.4);
        }

        .student-row.dragging::after {
            opacity: 1;
        }

        /* Enhanced unsaved change styling */
        .student-row.unsaved-change {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.08) 0%, rgba(251, 191, 36, 0.06) 100%);
            border-color: rgba(245, 158, 11, 0.3);
            position: relative;
            animation: pulse-unsaved 2s ease-in-out infinite;
        }

        .student-row.unsaved-change::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%);
            border-radius: 2px 0 0 2px;
            box-shadow: 0 0 10px rgba(245, 158, 11, 0.3);
        }

        .student-row.unsaved-change:hover {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.12) 0%, rgba(251, 191, 36, 0.08) 100%);
            border-color: rgba(245, 158, 11, 0.4);
        }

        @keyframes pulse-unsaved {
            0%, 100% {
                box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.2);
            }
            50% {
                box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.1);
            }
        }

        .student-row .drag-handle {
            opacity: 0.6;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            user-select: none;
            transform: scale(1);
            background: linear-gradient(135deg, rgba(243, 244, 246, 0.8) 0%, rgba(229, 231, 235, 0.8) 100%);
            border: 1px solid rgba(209, 213, 219, 0.5);
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            backdrop-filter: blur(4px);
        }

        .student-row:hover .drag-handle {
            opacity: 1;
            transform: scale(1.1);
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(243, 244, 246, 0.9) 100%);
            border-color: rgba(59, 130, 246, 0.3);
            box-shadow: 0 2px 4px rgba(59, 130, 246, 0.1);
        }

        .student-row .drag-handle:focus {
            outline: 2px solid rgba(59, 130, 246, 0.5);
            outline-offset: 2px;
        }

        .student-row:active .drag-handle {
            transform: scale(0.95);
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1) inset;
        }

        .student-row.dragging .drag-handle {
            opacity: 0.9;
            transform: scale(1.2);
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(147, 197, 253, 0.1) 100%);
            border-color: rgba(59, 130, 246, 0.4);
        }

        @keyframes shimmer {
            0%, 100% {
                transform: translateX(-100%);
            }
            50% {
                transform: translateX(100%);
            }
        }

        .student-count {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.875rem;
            box-shadow: 0 4px 6px -1px rgba(59, 130, 246, 0.1);
        }

        .enrollment-section {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            border: 1px solid #f3f4f6;
            display: flex;
            flex-direction: column;
            flex: 1;
        }

        .enrollment-section h3 {
            color: #1f2937;
            font-weight: 600;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .enrollment-section h3::before {
            content: '';
            width: 4px;
            height: 20px;
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            border-radius: 2px;
            flex-shrink: 0;
        }

        .table-container {
            position: relative;
            flex: 1;
            min-height: 0;
            display: flex;
            flex-direction: column;
        }

        .table-container table {
            width: 100%;
            flex: 1;
        }

        .table-container tbody {
            flex: 1;
        }

        .table-container tbody tr {
            transition: all 0.2s;
        }

        .table-container tbody tr:hover {
            background: rgba(59, 130, 246, 0.05);
        }

        .drag-instruction {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border: 1px solid #f59e0b;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            text-align: center;
            color: #92400e;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .drag-instruction svg {
            display: inline-block;
            margin-right: 0.5rem;
            vertical-align: middle;
        }

        /* Modal Footer Styles */
        .enrollment-modal .modal-footer {
            padding: 1.5rem 2rem;
            border-top: 1px solid rgba(229, 231, 235, 0.5);
            background: linear-gradient(135deg, rgba(249, 250, 251, 0.95) 0%, rgba(243, 244, 246, 0.95) 100%);
            backdrop-filter: blur(8px);
            border-radius: 0 0 20px 20px;
            flex-shrink: 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 -1px 3px rgba(0, 0, 0, 0.05);
        }

        .enrollment-modal .modal-footer .footer-info {
            color: rgba(75, 85, 99, 0.9);
            font-size: 0.875rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            letter-spacing: -0.01em;
        }

        .enrollment-modal .modal-footer .footer-info svg {
            display: inline-block;
            margin-right: 0.5rem;
            vertical-align: middle;
        }

        /* Success animation for moved students */
        @keyframes bounce {
            0%, 20%, 53%, 80%, 100% {
                transform: translate3d(0,0,0);
            }
            40%, 43% {
                transform: translate3d(0, -8px, 0);
            }
            70% {
                transform: translate3d(0, -4px, 0);
            }
            90% {
                transform: translate3d(0, -2px, 0);
            }
        }

        .student-row.bounce {
            animation: bounce 0.5s ease-in-out;
        }

        /* Loading states */
        .drop-zone.loading {
            opacity: 0.7;
            pointer-events: none;
        }

        .drop-zone.loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 40px;
            height: 40px;
            margin: -20px 0 0 -20px;
            border: 3px solid rgba(156, 163, 175, 0.3);
            border-top-color: rgba(59, 130, 246, 0.8);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            box-shadow:
                0 0 0 2px rgba(59, 130, 246, 0.1),
                0 4px 12px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(4px);
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>

    <script>
    let currentCourseId = null;
    let draggedStudentId = null;
    let isOperationInProgress = false; // Prevent rapid successive operations
    let pendingChanges = {}; // Track pending enrollment changes: {studentId: {action: 'enroll'|'unenroll', courseId: courseId}}

    // Debug function to test drag and drop
    window.testDragDrop = function() {
        console.log('=== DRAG AND DROP DEBUG ===');
        const studentRows = document.querySelectorAll('.student-row');
        console.log('Found student rows:', studentRows.length);

        studentRows.forEach((row, index) => {
            console.log(`Row ${index}:`, {
                element: row,
                draggable: row.draggable,
                dataStudentId: row.getAttribute('data-student-id'),
                classList: row.classList.value
            });
        });

        const enrolledZone = document.getElementById('enrolled-drop-zone');
        const availableZone = document.getElementById('available-drop-zone');
        console.log('Drop zones:', { enrolledZone, availableZone });

        const modal = document.getElementById('enrollment-modal');
        console.log('Modal visible:', modal && modal.classList.contains('show'));
    };

    // Simple test to manually trigger drag start
    window.testDragStart = function(studentId) {
        const row = document.querySelector(`[data-student-id="${studentId}"]`);
        if (row) {
            console.log('Manually triggering drag start on:', studentId);
            const event = new DragEvent('dragstart', { bubbles: true, cancelable: true });
            row.dispatchEvent(event);
        } else {
            console.log('Student row not found:', studentId);
        }
    };

    // Initialize drag and drop when modal opens
    function openEnrollmentModal(courseId, courseName) {
        console.log('Opening enrollment modal for course:', courseId, courseName);
        currentCourseId = courseId;

        // Clear any pending changes from previous sessions
        pendingChanges = {};

        document.getElementById('modal-course-title').textContent = courseName + ' - Enrollment Management';

        const modal = document.getElementById('enrollment-modal');
        modal.classList.remove('hidden');
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';

        // Load real data from DB via AJAX
        renderEnrolledStudentsTable([]);
        renderAvailableStudentsTable([]);
        setZoneLoading('enrolled-drop-zone', true);
        setZoneLoading('available-drop-zone', true);
        loadCourseStudents(courseId);
        loadAvailableStudents(courseId);

        // Initialize save button state
        updateSaveButton();
    }

    function closeEnrollmentModal() {
        // Check if there are unsaved changes
        if (Object.keys(pendingChanges).length > 0) {
            if (!confirm('You have unsaved changes. Are you sure you want to close without saving?')) {
                return;
            }
        }

        const modal = document.getElementById('enrollment-modal');
        modal.classList.remove('show');
        modal.classList.add('hidden');
        document.body.style.overflow = '';
        currentCourseId = null;

        // Clear pending changes and reset UI
        pendingChanges = {};

        // Remove all unsaved change indicators
        document.querySelectorAll('.unsaved-change').forEach(row => {
            row.classList.remove('unsaved-change');
            const indicator = row.querySelector('.pending-indicator');
            if (indicator) {
                indicator.remove();
            }
        });

        // Reset save button
        updateSaveButton();
    }

    // Removed hardcoded sample data. Data loads via AJAX.

    function updateDropZoneStates() {
        const enrolledZone = document.getElementById('enrolled-drop-zone');
        const availableZone = document.getElementById('available-drop-zone');

        if (enrolledZone) {
            const hasStudents = enrolledZone.querySelector('.student-row') !== null;
            enrolledZone.classList.toggle('has-content', hasStudents);
        }

        if (availableZone) {
            const hasStudents = availableZone.querySelector('.student-row') !== null;
            availableZone.classList.toggle('has-content', hasStudents);
        }
    }

    function setZoneLoading(zoneId, isLoading) {
        const zone = document.getElementById(zoneId);
        if (zone) zone.classList.toggle('loading', !!isLoading);
    }

    // Setup drag and drop functionality
    function setupDragAndDrop() {
        console.log('Setting up enhanced drag and drop...');

        // Check if student rows exist
        const studentRows = document.querySelectorAll('.student-row');
        console.log('Found student rows:', studentRows.length, studentRows);

        // Clear existing event listeners to avoid duplicates
        document.querySelectorAll('.student-row').forEach(row => {
            row.removeEventListener('dragstart', handleDragStart);
            row.removeEventListener('dragend', handleDragEnd);
        });

        const enrolledZone = document.getElementById('enrolled-drop-zone');
        const availableZone = document.getElementById('available-drop-zone');

        if (enrolledZone) {
            enrolledZone.removeEventListener('dragover', enrolledZone._handleDragOver);
            enrolledZone.removeEventListener('dragleave', enrolledZone._handleDragLeave);
            enrolledZone.removeEventListener('drop', enrolledZone._handleDrop);
        }

        if (availableZone) {
            availableZone.removeEventListener('dragover', availableZone._handleDragOver);
            availableZone.removeEventListener('dragleave', availableZone._handleDragLeave);
            availableZone.removeEventListener('drop', availableZone._handleDrop);
        }

        // Make all student rows draggable
        document.querySelectorAll('.student-row').forEach(row => {
            row.addEventListener('dragstart', handleDragStart);
            row.addEventListener('dragend', handleDragEnd);
            row.addEventListener('mousedown', (e) => {
                console.log('Mouse down on row:', e.target.closest('.student-row').getAttribute('data-student-id'));
            });
            console.log('Added listeners to row:', row.getAttribute('data-student-id'), row.draggable);
        });

        // Setup drop zones with bound handlers
        if (enrolledZone) {
            enrolledZone._handleDragOver = (e) => handleDragOver(e, enrolledZone);
            enrolledZone._handleDragLeave = (e) => handleDragLeave(e, enrolledZone);
            enrolledZone._handleDrop = (e) => handleDrop(e, 'enrolled');

            enrolledZone.addEventListener('dragover', enrolledZone._handleDragOver);
            enrolledZone.addEventListener('dragleave', enrolledZone._handleDragLeave);
            enrolledZone.addEventListener('drop', enrolledZone._handleDrop);
        }

        if (availableZone) {
            availableZone._handleDragOver = (e) => handleDragOver(e, availableZone);
            availableZone._handleDragLeave = (e) => handleDragLeave(e, availableZone);
            availableZone._handleDrop = (e) => handleDrop(e, 'available');

            availableZone.addEventListener('dragover', availableZone._handleDragOver);
            availableZone.addEventListener('dragleave', availableZone._handleDragLeave);
            availableZone.addEventListener('drop', availableZone._handleDrop);
        }
    }

    function handleDragStart(e) {
        console.log('handleDragStart called', e.target);

        // Find the student row element (in case the drag started from a child element)
        let studentRow = e.target;
        while (studentRow && !studentRow.classList.contains('student-row')) {
            studentRow = studentRow.parentElement;
        }

        if (!studentRow) {
            console.error('Could not find student row element', e.target);
            e.preventDefault();
            return;
        }

        draggedStudentId = studentRow.getAttribute('data-student-id');
        if (!draggedStudentId) {
            console.error('Student row missing data-student-id attribute', studentRow);
            e.preventDefault();
            return;
        }

        console.log('Found student row:', studentRow, 'ID:', draggedStudentId);

        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', draggedStudentId);

        // Add visual feedback for dragging
        studentRow.classList.add('dragging');

        // Update drop zone labels
        updateDropZoneLabels();

        console.log(`Started dragging student ${draggedStudentId}`);
    }

    function handleDragEnd(e) {
        // Find the student row element (in case the drag ended on a child element)
        let studentRow = e.target;
        while (studentRow && !studentRow.classList.contains('student-row')) {
            studentRow = studentRow.parentElement;
        }

        if (studentRow) {
            // Remove visual feedback
            studentRow.classList.remove('dragging');
        }

        // Reset drop zone styles
        resetDropZones();

        // Clear dragged student ID
        draggedStudentId = null;
    }

    function handleDragOver(e, zone) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';

        // Add visual feedback to the drop zone
        zone.classList.add('drag-over');

        // Update the label based on what's being dragged
        const label = zone.querySelector('.drop-zone-label');
        if (label) {
            const isEnrolled = zone.id === 'enrolled-drop-zone';
            label.textContent = isEnrolled ?
                '🎯 Drop here to move to enrolled (changes saved when you click Save)' :
                '🎯 Drop here to move to available (changes saved when you click Save)';
        }
    }

    function handleDragLeave(e, zone) {
        // Only remove the class if we're actually leaving the zone (not just moving within it)
        if (!zone.contains(e.relatedTarget)) {
            zone.classList.remove('drag-over');

            // Reset label
            const label = zone.querySelector('.drop-zone-label');
            if (label) {
                const isEnrolled = zone.id === 'enrolled-drop-zone';
                label.textContent = isEnrolled ?
                    'Drag students here to enroll them' :
                    'Drag students here to unenroll them';
            }
        }
    }

    function handleDrop(e, targetZone) {
        e.preventDefault();

        if (!draggedStudentId) return;

        // Prevent multiple simultaneous operations
        if (isOperationInProgress) {
            console.warn('Operation already in progress, ignoring drop');
            return;
        }

        const draggedElement = document.querySelector(`[data-student-id="${draggedStudentId}"]`);
        if (!draggedElement) return;

        // Determine origin and target containers
        const enrolledBody = document.getElementById('enrolled-table-body');
        const availableBody = document.getElementById('available-table-body');
        const originContainer = enrolledBody.contains(draggedElement) ? enrolledBody : availableBody;
        const targetContainer = targetZone === 'enrolled' ? enrolledBody : availableBody;
        const originId = originContainer && originContainer.id;
        const targetId = targetContainer && targetContainer.id;

        if (targetContainer && draggedElement) {
            // Check if student is already in the target zone
            const existingStudent = targetContainer.querySelector(`[data-student-id="${draggedStudentId}"]`);
            if (existingStudent) {
                console.log(`Student ${draggedStudentId} is already in ${targetZone}`);
                return;
            }

            // Remove from current location
            draggedElement.remove();

            // Add to new location
            targetContainer.appendChild(draggedElement);

            // Update counts
            updateStudentCounts();

            // Update drop zone states
            updateDropZoneStates();

            // Track the pending change instead of saving immediately
            const action = targetZone === 'enrolled' ? 'enroll' : 'unenroll';
            pendingChanges[draggedStudentId] = {
                action: action,
                courseId: currentCourseId,
                fromContainer: originId,
                toContainer: targetId
            };

            // Mark row as having unsaved changes
            draggedElement.classList.add('unsaved-change');

            // Show pending feedback
            showPendingFeedback(targetZone, draggedStudentId);

            console.log(`📝 Student ${draggedStudentId} moved to ${targetZone} (pending save)`);

            // Update save button state
            updateSaveButton();
        }

        // Reset visual feedback
        resetDropZones();
        draggedStudentId = null;
    }

    function rollbackMove(studentId, originContainerId, targetContainerId) {
        console.warn('Rolling back move for student', studentId);

        // Don't attempt DOM manipulation rollback - instead refresh data from server
        // This prevents inconsistencies when multiple operations happen simultaneously
        if (currentCourseId) {
            console.log('Refreshing data after rollback');
            loadCourseStudents(currentCourseId);
            loadAvailableStudents(currentCourseId);
            refreshCourseEnrolledCount(currentCourseId);
            refreshQuickStats();
        }
    }

    function ajaxEnroll(studentId, courseId) {
        const params = new URLSearchParams();
        params.append('action', 'nds_enroll_student');
        params.append('student_id', studentId);
        params.append('course_id', courseId);
        params.append('nonce', '<?php echo wp_create_nonce('nds_enroll_student_nonce'); ?>');

        return fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            credentials: 'same-origin',
            body: params.toString()
        })
        .then(function(r){ return r.json(); })
        .then(function(data){ return !!(data && data.success); })
        .catch(function(){ return false; });
    }

    function ajaxUnenroll(studentId, courseId) {
        const params = new URLSearchParams();
        params.append('action', 'nds_unenroll_student');
        params.append('student_id', studentId);
        params.append('course_id', courseId);
        params.append('nonce', '<?php echo wp_create_nonce('nds_unenroll_student_nonce'); ?>');

        return fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            credentials: 'same-origin',
            body: params.toString()
        })
        .then(function(r){ return r.json(); })
        .then(function(data){ return !!(data && data.success); })
        .catch(function(){ return false; });
    }

    // Update the enrolled count cell in the main courses table
    function refreshCourseEnrolledCount(courseId) {
        const params = new URLSearchParams();
        params.append('action', 'nds_get_course_enrolled_count');
        params.append('course_id', courseId);
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            credentials: 'same-origin',
            body: params.toString()
        })
        .then(r => r.json())
        .then(data => {
            if (data && data.success) {
                const el = document.getElementById('course-enrolled-' + courseId);
                if (el) {
                    el.textContent = data.data.count;
                }
            }
        })
        .catch(() => {});
    }

    function refreshQuickStats() {
        const params = new URLSearchParams();
        params.append('action', 'nds_get_enrollment_quick_stats');
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            credentials: 'same-origin',
            body: params.toString()
        })
        .then(r => r.json())
        .then(data => {
            if (data && data.success && data.data) {
                const s = data.data;
                const total = document.getElementById('stat-total-students');
                const enr = document.getElementById('stat-enrolled');
                const una = document.getElementById('stat-unassigned');
                const crs = document.getElementById('stat-courses');
                if (total) total.textContent = s.total;
                if (enr) enr.textContent = s.enrolled;
                if (una) una.textContent = s.unassigned;
                if (crs) crs.textContent = s.courses;
            }
        })
        .catch(() => {});
    }

    function updateDropZoneLabels() {
        const enrolledLabel = document.querySelector('#enrolled-drop-zone .drop-zone-label');
        const availableLabel = document.querySelector('#available-drop-zone .drop-zone-label');

        if (enrolledLabel) {
            enrolledLabel.textContent = 'Drop students here to enroll them';
        }
        if (availableLabel) {
            availableLabel.textContent = 'Drop students here to unenroll them';
        }
    }

    function resetDropZones() {
        document.querySelectorAll('.drop-zone').forEach(zone => {
            zone.classList.remove('drag-over');
        });
        updateDropZoneLabels();
    }

    function showSuccessFeedback(zone, studentId) {
        // Add a subtle success animation to the moved student
        const movedStudent = document.querySelector(`[data-student-id="${studentId}"]`);
        if (movedStudent) {
            movedStudent.style.animation = 'none';
            setTimeout(() => {
                movedStudent.style.animation = 'bounce 0.5s ease-in-out';
            }, 10);
        }
    }

    function showPendingFeedback(zone, studentId) {
        // Add a pending indicator to the moved student
        const movedStudent = document.querySelector(`[data-student-id="${studentId}"]`);
        if (movedStudent) {
            // Remove any existing pending indicator
            const existingIndicator = movedStudent.querySelector('.pending-indicator');
            if (existingIndicator) {
                existingIndicator.remove();
            }

            // Add new pending indicator
            const indicator = document.createElement('span');
            indicator.className = 'pending-indicator inline-flex items-center justify-center w-5 h-5 ml-2';
            indicator.title = 'Unsaved change';
            indicator.innerHTML = `
                <svg class="w-3 h-3 text-amber-500 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            `;

            const nameSpan = movedStudent.querySelector('span:last-child');
            if (nameSpan) {
                nameSpan.appendChild(indicator);
            }
        }
    }

    function updateSaveButton() {
        const saveButton = document.getElementById('save-changes-btn');
        const hasChanges = Object.keys(pendingChanges).length > 0;

        if (saveButton) {
            saveButton.disabled = !hasChanges;
            saveButton.textContent = hasChanges ? `Save Changes (${Object.keys(pendingChanges).length})` : 'No Changes to Save';
            saveButton.classList.toggle('opacity-50', !hasChanges);
            saveButton.classList.toggle('cursor-not-allowed', !hasChanges);
        }
    }

    function saveAllChanges() {
        if (Object.keys(pendingChanges).length === 0) {
            return;
        }

        const saveButton = document.getElementById('save-changes-btn');
        if (saveButton) {
            saveButton.disabled = true;
            saveButton.textContent = 'Saving...';
        }

        // Mark operation as in progress
        isOperationInProgress = true;

        // Process all pending changes
        const promises = Object.entries(pendingChanges).map(([studentId, change]) => {
            if (change.action === 'enroll') {
                return ajaxEnroll(studentId, change.courseId);
            } else {
                return ajaxUnenroll(studentId, change.courseId);
            }
        });

        Promise.all(promises).then(results => {
            const failures = results.filter(result => !result).length;

            if (failures === 0) {
                // All changes saved successfully
                console.log('✅ All changes saved successfully');

                // Clear pending changes
                pendingChanges = {};

                // Remove unsaved change indicators
                document.querySelectorAll('.unsaved-change').forEach(row => {
                    row.classList.remove('unsaved-change');
                    const indicator = row.querySelector('.pending-indicator');
                    if (indicator) {
                        indicator.remove();
                    }
                });

                // Refresh data from server
                loadCourseStudents(currentCourseId);
                loadAvailableStudents(currentCourseId);
                refreshCourseEnrolledCount(currentCourseId);
                refreshQuickStats();

                // Show success message
                showSuccessFeedback('modal', 'all');

                // Update save button
                updateSaveButton();

            } else {
                // Some changes failed
                console.error(`${failures} changes failed to save`);
                showErrorFeedback(`${failures} changes failed to save. Please try again.`);
            }
        }).catch(error => {
            console.error('Error saving changes:', error);
            showErrorFeedback('Network error while saving changes. Please try again.');
        }).finally(() => {
            isOperationInProgress = false;
            if (saveButton) {
                saveButton.disabled = false;
                updateSaveButton();
            }
        });
    }

    function showErrorFeedback(message) {
        // Create a simple error notification
        const notification = document.createElement('div');
        notification.className = 'fixed top-4 right-4 bg-red-500 text-white px-6 py-3 rounded-lg shadow-lg z-50';
        notification.textContent = message;

        document.body.appendChild(notification);

        // Remove after 5 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 5000);
    }

    function loadCourseStudents(courseId) {
        const params = new URLSearchParams();
        params.append('action', 'nds_get_enrolled_students');
        params.append('course_id', courseId);
        params.append('nonce', '<?php echo wp_create_nonce('nds_get_enrolled_students_nonce'); ?>');

        setZoneLoading('enrolled-drop-zone', true);

        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            credentials: 'same-origin',
            body: params.toString()
        })
        .then(response => response.json())
        .then(data => {
            try {
                const list = Array.isArray(data && data.data) ? (data.data) : Object.values((data && data.data) || {});
                renderEnrolledStudentsTable(list);
            } catch (err) {
                console.error('Parse enrolled list failed', err);
                renderEnrolledStudentsTable([]);
            }
            updateDropZoneStates();
            setupDragAndDrop();
            setZoneLoading('enrolled-drop-zone', false);
        })
        .catch(error => {
            console.error('Error loading enrolled students:', error);
            renderEnrolledStudentsTable([]);
            updateDropZoneStates();
            setupDragAndDrop();
            setZoneLoading('enrolled-drop-zone', false);
        });
    }

    function loadAvailableStudents(courseId) {
        const params = new URLSearchParams();
        params.append('action', 'nds_get_available_students');
        params.append('course_id', courseId);
        params.append('nonce', '<?php echo wp_create_nonce('nds_get_available_students_nonce'); ?>');

        setZoneLoading('available-drop-zone', true);

        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            credentials: 'same-origin',
            body: params.toString()
        })
        .then(response => response.json())
        .then(data => {
            try {
                const list = Array.isArray(data && data.data) ? (data.data) : Object.values((data && data.data) || {});
                renderAvailableStudentsTable(list);
            } catch (err) {
                console.error('Parse available list failed', err);
                renderAvailableStudentsTable([]);
            }
            updateDropZoneStates();
            setupDragAndDrop();
            setZoneLoading('available-drop-zone', false);
        })
        .catch(error => {
            console.error('Error loading available students:', error);
            renderAvailableStudentsTable([]);
            updateDropZoneStates();
            setupDragAndDrop();
            setZoneLoading('available-drop-zone', false);
        });
    }

        // Render enrolled students table
    function renderEnrolledStudentsTable(students) {
        const tbody = document.getElementById('enrolled-table-body');
        const count = document.getElementById('enrolled-count');

        if (!tbody) return;

        if (!students || students.length === 0) {
            tbody.innerHTML = '<tr data-placeholder="enrolled"><td colspan="2" class="px-4 py-6 text-center text-sm text-gray-500">No students enrolled</td></tr>';
            if (count) count.textContent = '0';
             return;
         }

         tbody.innerHTML = students.map(s => `
            <tr class="student-row" draggable="true" data-student-id="${s.id}">
                 <td class="px-4 py-2 text-sm text-gray-900">
                     <div class="flex items-center gap-2">
                        <span class="drag-handle inline-flex items-center justify-center w-6 h-6 rounded bg-gray-100 border cursor-move select-none" role="button" tabindex="0" aria-label="Drag to move student" title="Drag to move student">⋮⋮</span>
                         <span>${s.first_name} ${s.last_name}</span>
                     </div>
                 </td>
                 <td class="px-4 py-2 text-sm text-gray-500">${s.student_number}</td>
             </tr>
         `).join('');

        if (count) count.textContent = students.length.toString();
     }
 
    // Render available students table
     function renderAvailableStudentsTable(students) {
         const tbody = document.getElementById('available-table-body');
         const count = document.getElementById('available-count');

        if (!tbody) return;

        if (!students || students.length === 0) {
            tbody.innerHTML = '<tr data-placeholder="available"><td colspan="2" class="px-4 py-6 text-center text-sm text-gray-500">No available students</td></tr>';
            if (count) count.textContent = '0';
             return;
         }

         tbody.innerHTML = students.map(s => `
            <tr class="student-row" draggable="true" data-student-id="${s.id}">
                 <td class="px-4 py-2 text-sm text-gray-900">
                     <div class="flex items-center gap-2">
                        <span class="drag-handle inline-flex items-center justify-center w-6 h-6 rounded bg-gray-100 border cursor-move select-none" role="button" tabindex="0" aria-label="Drag to move student" title="Drag to move student">⋮⋮</span>
                         <span>${s.first_name} ${s.last_name}</span>
                     </div>
                 </td>
                 <td class="px-4 py-2 text-sm text-gray-500">${s.student_number}</td>
             </tr>
         `).join('');

        if (count) count.textContent = students.length.toString();
    }

    // Update student counts
    function updateStudentCounts() {
        const enrolledCount = document.querySelectorAll('#enrolled-table-body tr[data-student-id]').length;
        const availableCount = document.querySelectorAll('#available-table-body tr[data-student-id]').length;

        const enrolledCountEl = document.getElementById('enrolled-count');
        const availableCountEl = document.getElementById('available-count');

        if (enrolledCountEl) enrolledCountEl.textContent = enrolledCount.toString();
        if (availableCountEl) availableCountEl.textContent = availableCount.toString();
    }

    // Expose functions globally for inline onclick handlers
window.openEnrollmentModal = openEnrollmentModal;
window.closeEnrollmentModal = closeEnrollmentModal;
    </script>
    <?php
}

function nds_graduations_page() {
    echo '<div class="wrap"><h1>Graduations</h1><p>Coming soon...</p></div>';
}

function nds_alumni_page() {
    echo '<div class="wrap"><h1>Alumni</h1><p>Coming soon...</p></div>';
}

// Add AJAX actions for fetching and updating students
add_action('wp_ajax_nds_get_students', 'nds_ajax_get_students');
add_action('wp_ajax_nds_update_enrollment', 'nds_ajax_update_enrollment');

// AJAX handler to get students
function nds_ajax_get_students() {
    // Check nonce for security
    check_ajax_referer('nds_get_students_nonce', 'nonce');

    // Get students based on the request
    $students = nds_get_students($_POST);

    // Return students as JSON
    wp_send_json_success($students);
}

// AJAX handler to update student enrollment
function nds_ajax_update_enrollment() {
    // Check nonce for security
    check_ajax_referer('nds_update_enrollment_nonce', 'nonce');

    // Get student ID and target status
    $student_id = intval($_POST['student_id']);
    $target_status = sanitize_text_field($_POST['target_status']);

    // Update student status in the database
    global $wpdb;
    $result = $wpdb->update(
        $wpdb->prefix . 'nds_students',
        ['status' => $target_status],
        ['id' => $student_id],
        ['%s'],
        ['%d']
    );

    // Check if the update was successful
    if ($result !== false) {
        wp_send_json_success();
    } else {
        wp_send_json_error('Failed to update enrollment');
    }
}

/**
 * Wrapper for bulk "Revert to Applicant" action.
 * This ensures the implementation in learner-management.php is available
 * on admin-post.php requests.
 */
function nds_handle_bulk_revert_learners_to_applicants() {
    // Load learner-management implementation if not already loaded
    $file_path = plugin_dir_path(__FILE__) . 'learner-management.php';
    if (file_exists($file_path)) {
        require_once $file_path;
    }

    if (function_exists('nds_handle_bulk_revert_learners_to_applicants_impl')) {
        nds_handle_bulk_revert_learners_to_applicants_impl();
    } else {
        wp_die('Bulk revert implementation not available.');
    }
}
add_action('admin_post_nds_bulk_revert_learners_to_applicants', 'nds_handle_bulk_revert_learners_to_applicants');

// Export database as ZIP (CSVs + guide) - NDS Academy Settings
add_action('admin_post_nds_export_database', 'nds_handle_export_database');
function nds_handle_export_database() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    check_admin_referer('nds_export_database_nonce');

    $plugin_root = dirname(plugin_dir_path(__FILE__));
    $export_script = $plugin_root . '/export-database-to-excel.php';
    
    // FIX: Add proper error handling and validation
    if (!file_exists($export_script)) {
        error_log('[NDS Export] Script not found: ' . $export_script);
        wp_safe_redirect(admin_url('admin.php?page=nds-settings&import_export=error&msg=' . rawurlencode('Export script not found.')));
        exit;
    }

    // FIX: Ensure the function exists
    require_once $export_script;
    if (!function_exists('nds_run_export_to_directory')) {
        error_log('[NDS Export] Function nds_run_export_to_directory not found');
        wp_safe_redirect(admin_url('admin.php?page=nds-settings&import_export=error&msg=' . rawurlencode('Export function not available.')));
        exit;
    }

    $temp_dir = sys_get_temp_dir() . '/nds-export-' . time();
    $out_dir = nds_run_export_to_directory($temp_dir, true);
    
    if (!$out_dir || !is_dir($out_dir)) {
        wp_safe_redirect(admin_url('admin.php?page=nds-settings&import_export=error&msg=' . rawurlencode('Export failed.')));
        exit;
    }

    $zip_path = sys_get_temp_dir() . '/nds-database-export-' . date('Y-m-d-His') . '.zip';
    $zip = new ZipArchive();
    
    if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        wp_safe_redirect(admin_url('admin.php?page=nds-settings&import_export=error&msg=' . rawurlencode('Could not create ZIP.')));
        exit;
    }
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($out_dir, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::LEAVES_ONLY);
    foreach ($files as $file) {
        if ($file->isFile()) {
            $zip->addFile($file->getRealPath(), 'nds-database-export/' . $file->getFilename());
        }
    }
    $zip->close();

    // Remove temp export dir
    array_map('unlink', glob($out_dir . '/*'));
    @rmdir($out_dir);

    // FIX: Ensure proper headers and exit
    if (file_exists($zip_path)) {
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="nds-database-export-' . date('Y-m-d') . '.zip"');
        header('Content-Length: ' . filesize($zip_path));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        readfile($zip_path);
        @unlink($zip_path);
        exit;
    } else {
        error_log('[NDS Export] ZIP file not created: ' . $zip_path);
        wp_safe_redirect(admin_url('admin.php?page=nds-settings&import_export=error&msg=' . rawurlencode('Export file could not be created.')));
        exit;
    }
}

// Import from Excel - NDS Academy Settings
add_action('admin_post_nds_import_excel', 'nds_handle_import_excel');
function nds_handle_import_excel() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    check_admin_referer('nds_import_excel_nonce');

    // #region agent log
    $log_path = dirname(__DIR__) . '/.cursor/debug.log';
    $agent_log = function ($hypothesisId, $message, $data = []) use ($log_path) {
        $line = json_encode(array_merge(['sessionId' => 'debug-session', 'runId' => 'import-run', 'hypothesisId' => $hypothesisId, 'location' => 'admin-pages.php:nds_handle_import_excel', 'message' => $message, 'data' => $data, 'timestamp' => round(microtime(true) * 1000)])) . "\n";
        @file_put_contents($log_path, $line, FILE_APPEND | LOCK_EX);
    };
    // #endregion

    $file = isset($_FILES['nds_import_xlsx']) ? $_FILES['nds_import_xlsx'] : null;
    $upload_error = $file && isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;

    $agent_log('H1', 'Import handler entry', ['has_files_key' => isset($_FILES['nds_import_xlsx']), 'upload_error' => $upload_error, 'has_tmp_name' => $file && !empty($file['tmp_name']), 'name' => $file ? substr($file['name'] ?? '', -20) : null]);

    $plugin_root = dirname(plugin_dir_path(__FILE__));
    $default_xlsx = $plugin_root . '/assets/NDS Database System.xlsx';
    $use_path = null;

    if ($upload_error === UPLOAD_ERR_OK && !empty($file['tmp_name']) && is_uploaded_file($file['tmp_name'])) {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext === 'xlsx') {
            $use_path = $file['tmp_name'];
        }
    }
    if ($use_path === null && file_exists($default_xlsx)) {
        $use_path = $default_xlsx;
    }

    if ($use_path === null) {
        if ($upload_error !== UPLOAD_ERR_OK && $upload_error !== UPLOAD_ERR_NO_FILE) {
            $messages = [
                UPLOAD_ERR_INI_SIZE   => 'File exceeds upload_max_filesize.',
                UPLOAD_ERR_FORM_SIZE  => 'File exceeds form MAX_FILE_SIZE.',
                UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
                UPLOAD_ERR_NO_TMP_DIR=> 'Missing temp folder.',
                UPLOAD_ERR_CANT_WRITE=> 'Failed to write file to disk.',
                UPLOAD_ERR_EXTENSION => 'Upload blocked by server extension.',
            ];
            $msg = isset($messages[$upload_error]) ? $messages[$upload_error] : 'Upload error code: ' . $upload_error;
        } else {
            $msg = 'No Excel file selected and default file not found (assets/NDS Database System.xlsx). Please select an .xlsx file or add the default file to the plugin assets folder.';
        }
        error_log('[NDS Import] ' . $msg);
        
        // FIX: Use wp_safe_redirect instead of wp_redirect and ensure exit
        wp_safe_redirect(admin_url('admin.php?page=nds-settings&import_export=error&msg=' . rawurlencode($msg)));
        exit;
    }

    $import_script = $plugin_root . '/import-excel-to-database.php';
    if (!file_exists($import_script)) {
        error_log('[NDS Import] Script not found: ' . $import_script);
        wp_safe_redirect(admin_url('admin.php?page=nds-settings&import_export=error&msg=' . rawurlencode('Import script not found.')));
        exit;
    }

    $agent_log('H3', 'Before require import script', ['import_script_exists' => file_exists($import_script), 'use_path' => $use_path]);

    try {
        require_once $import_script;
        
        // FIX: Ensure the function exists before calling it
        if (!function_exists('nds_import_excel_run')) {
            error_log('[NDS Import] Function nds_import_excel_run not found in import script');
            wp_safe_redirect(admin_url('admin.php?page=nds-settings&import_export=error&msg=' . rawurlencode('Import function not available.')));
            exit;
        }
        $result = nds_import_excel_run($use_path, array('dry_run' => false));
    } catch (Exception $e) {
        $agent_log('H5', 'Exception in import', ['message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
        error_log('[NDS Import] Exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        wp_safe_redirect(admin_url('admin.php?page=nds-settings&import_export=error&msg=' . rawurlencode('Import error: ' . $e->getMessage())));
        exit;
    }

    if (isset($file['tmp_name']) && $use_path === $file['tmp_name']) {
        @unlink($use_path);
    }

    $agent_log('H4', 'After nds_import_excel_run', ['result_success' => !empty($result['success']), 'result_message_preview' => isset($result['message']) ? substr($result['message'], 0, 120) : null, 'errors_count' => isset($result['errors']) ? count($result['errors']) : 0, 'first_error' => isset($result['errors'][0]) ? $result['errors'][0] : null]);

    if (!empty($result['success'])) {
        $msg = isset($result['message']) ? $result['message'] : 'Import completed.';
        if (!empty($result['skipped_details'])) {
            set_transient('nds_import_skipped_details', $result['skipped_details'], 120);
        }
        wp_safe_redirect(admin_url('admin.php?page=nds-settings&import_export=success&msg=' . rawurlencode($msg)));
        exit;
    } else {
        $msg = isset($result['message']) ? $result['message'] : 'Import failed.';
        if (!empty($result['errors'])) {
            error_log('[NDS Import] Errors: ' . implode(' | ', array_slice($result['errors'], 0, 10)));
        }
        wp_safe_redirect(admin_url('admin.php?page=nds-settings&import_export=error&msg=' . rawurlencode($msg)));
        exit;
    }
}




















// AJAX Handlers for Programs Management
add_action('wp_ajax_nds_create_program_ajax', 'nds_create_program_ajax_handler');
add_action('wp_ajax_nds_create_course_ajax', 'nds_create_course_ajax_handler');
// Backward compatibility for stale/cached clients posting legacy action.
add_action('wp_ajax_nds_add_course', 'nds_create_course_ajax_handler');
add_action('wp_ajax_nds_delete_program_ajax', 'nds_delete_program_ajax_handler');
add_action('wp_ajax_nds_get_programs_data_ajax', 'nds_get_programs_data_ajax_handler');

function nds_create_program_ajax_handler() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'nds_program_nonce')) {
        wp_send_json_error('Security check failed');
    }
    
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    global $wpdb;
    $table_programs = $wpdb->prefix . 'nds_programs';
    
    // Sanitize data
    $program_name = sanitize_text_field($_POST['program_name']);
    $program_type = sanitize_text_field($_POST['program_type']);
    $level = sanitize_text_field($_POST['level']);
    $duration_months = intval($_POST['duration_months']);
    $certification_body = sanitize_text_field($_POST['certification_body']);
    $requirements = sanitize_textarea_field($_POST['requirements']);
    $description = sanitize_textarea_field($_POST['description']);
    
    // Validate required fields
    if (empty($program_name) || empty($program_type) || empty($level) || empty($duration_months)) {
        wp_send_json_error('Please fill in all required fields');
    }
    
    // Insert program
    $result = $wpdb->insert(
        $table_programs,
        array(
            'path_id' => 1, // Default path_id - you may need to adjust this
            'category_id' => 1, // Default category_id - you may need to adjust this
            'name' => $program_name,
            'program_type' => $program_type,
            'level' => $level,
            'duration_months' => $duration_months,
            'certification_body' => $certification_body,
            'requirements' => $requirements,
            'description' => $description,
            'created_at' => current_time('mysql')
        ),
        array('%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s')
    );
    
    if ($result === false) {
        wp_send_json_error('Database error occurred');
    }
    
    wp_send_json_success('Program created successfully');
}

function nds_create_course_ajax_handler() {
    // Prevent accidental output corruption from notices/warnings before JSON response.
    if (function_exists('ob_get_level')) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }

    // Verify nonce — field is named 'nds_course_nonce' by wp_nonce_field()
    $nonce = isset($_POST['nds_course_nonce']) ? $_POST['nds_course_nonce'] : (isset($_POST['nonce']) ? $_POST['nonce'] : '');
    if (!wp_verify_nonce($nonce, 'nds_course_nonce')) {
        wp_send_json_error(['message' => 'Security check failed']);
    }
    
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions']);
    }
    
    global $wpdb;
    $table_courses = $wpdb->prefix . 'nds_courses';
    
    // Sanitize data
    $program_id = isset($_POST['program_id']) ? intval($_POST['program_id']) : 0;
    $course_name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
    $course_code = isset($_POST['code']) ? sanitize_text_field($_POST['code']) : '';
    $nqf_level = isset($_POST['nqf_level']) ? intval($_POST['nqf_level']) : 0;
    $duration = isset($_POST['duration']) ? sanitize_text_field($_POST['duration']) : '';
    $credits = isset($_POST['credits']) ? intval($_POST['credits']) : 0;
    $price = isset($_POST['price']) ? floatval($_POST['price']) : 0.0;
    $description = isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '';
    $start_date = (isset($_POST['start_date']) && !empty($_POST['start_date'])) ? sanitize_text_field($_POST['start_date']) : null;
    $end_date = (isset($_POST['end_date']) && !empty($_POST['end_date'])) ? sanitize_text_field($_POST['end_date']) : null;
    $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'active';
    $max_students = isset($_POST['max_students']) ? intval($_POST['max_students']) : 20;
    
    // Validate required fields
    if (empty($program_id) || empty($course_name)) {
        wp_send_json_error(['message' => 'Please fill in all required fields (program and course name)']);
    }
    
    // Generate code if not provided
    if (empty($course_code)) {
        require_once(plugin_dir_path(__FILE__) . 'courses-functions.php');
        $course_code = nds_generate_course_code($course_name, $program_id, $wpdb, $table_courses);
    }
    
    // Prepare data for insert - only include fields that exist in the database schema
    $insert_data = [
        'program_id' => $program_id,
        'code' => $course_code,
        'name' => $course_name,
        'description' => $description,
        'nqf_level' => $nqf_level > 0 ? $nqf_level : null,
        'credits' => $credits > 0 ? $credits : 0,
        'price' => $price,
        'currency' => 'ZAR',
        'start_date' => $start_date,
        'end_date' => $end_date,
        'status' => $status
    ];
    
    $format_array = ['%d', '%s', '%s', '%s', '%d', '%d', '%f', '%s', '%s', '%s', '%s'];
    
    // Add optional fields if they exist
    if (!empty($duration)) {
        $insert_data['duration_weeks'] = intval($duration);
        $format_array[] = '%d';
    }
    
    // Insert course
    $result = $wpdb->insert(
        $table_courses,
        $insert_data,
        $format_array
    );
    
    if ($result === false) {
        $error_message = $wpdb->last_error;
        error_log('NDS Course Creation Failed: ' . $error_message);
        
        // Check for duplicate code error and retry
        if (strpos($error_message, 'Duplicate entry') !== false && strpos($error_message, 'code') !== false) {
            $course_code = nds_generate_course_code($course_name, $program_id, $wpdb, $table_courses);
            $insert_data['code'] = $course_code;
            $result = $wpdb->insert(
                $table_courses,
                $insert_data,
                ['%d', '%s', '%s', '%s', '%d', '%d', '%f', '%s', '%s', '%s', '%s', '%d']
            );
        }
        
        if ($result === false) {
            wp_send_json_error(['message' => 'Database error: ' . ($wpdb->last_error ?: 'Unknown error')]);
        }
    }
    
    wp_send_json_success('Course created successfully');
}

function nds_delete_program_ajax_handler() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'nds_program_nonce')) {
        wp_send_json_error('Security check failed');
    }
    
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    global $wpdb;
    $table_programs = $wpdb->prefix . 'nds_programs';
    $program_id = intval($_POST['program_id']);
    
    // Delete program
    $result = $wpdb->delete(
        $table_programs,
        array('id' => $program_id),
        array('%d')
    );
    
    if ($result === false) {
        wp_send_json_error('Database error occurred');
    }
    
    wp_send_json_success('Program deleted successfully');
}

function nds_get_programs_data_ajax_handler() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'nds_program_nonce')) {
        wp_send_json_error('Security check failed');
    }
    
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    global $wpdb;
    $table_programs = $wpdb->prefix . 'nds_programs';
    $table_courses = $wpdb->prefix . 'nds_courses';
    $table_paths = $wpdb->prefix . 'nds_education_paths';
    
    // Get programs with course counts
    $programs = $wpdb->get_results("
        SELECT p.*,
               COUNT(c.id) as course_count,
               COUNT(DISTINCT ep.id) as path_count
        FROM {$table_programs} p
        LEFT JOIN {$table_courses} c ON p.id = c.program_id
        LEFT JOIN {$table_paths} ep ON p.id = ep.program_id
        GROUP BY p.id
        ORDER BY p.name
    ", ARRAY_A);
    
    // Get recent programs
    $recent_programs = $wpdb->get_results("
        SELECT * FROM {$table_programs}
        ORDER BY created_at DESC
        LIMIT 5
    ", ARRAY_A);
    
    // Calculate statistics
    $total_programs = count($programs);
    $active_programs = count(array_filter($programs, function($p) { return $p['status'] === 'active'; }));
    $total_courses = array_sum(array_column($programs, 'course_count'));
    $total_paths = array_sum(array_column($programs, 'path_count'));
    
    wp_send_json_success(array(
        'programs' => $programs,
        'recent_programs' => $recent_programs,
        'stats' => array(
            'total_programs' => $total_programs,
            'active_programs' => $active_programs,
            'total_courses' => $total_courses,
            'total_paths' => $total_paths
        )
    ));
}

// Settings Page
function nds_settings_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    $seed_status          = isset($_GET['seed']) ? sanitize_text_field($_GET['seed']) : '';
    $wipe_status          = isset($_GET['wipe']) ? sanitize_text_field($_GET['wipe']) : '';
    $wipe_tables_status   = isset($_GET['wipe_tables']) ? sanitize_text_field($_GET['wipe_tables']) : '';
    $import_export_status = isset($_GET['import_export']) ? sanitize_text_field($_GET['import_export']) : '';
    $msg                  = isset($_GET['msg']) ? sanitize_text_field($_GET['msg']) : '';
    include plugin_dir_path(__FILE__) . 'partials/admin-settings-page.php';
}

// ================= Hero Carousel Admin Pages =================

/**
 * Hero Carousel Management Page
 */
function nds_hero_carousel_management_page() {
    $carousel_admin = new NDS_Hero_Carousel_Admin();
    $carousel_admin->carousel_management_page();
}

/**
 * Add Carousel Slide Page
 */
function nds_add_carousel_slide_page() {
    $carousel_admin = new NDS_Hero_Carousel_Admin();
    $carousel_admin->add_carousel_slide_page();
}

/**
 * Edit Carousel Slide Page
 */
function nds_edit_carousel_slide_page() {
    $carousel_admin = new NDS_Hero_Carousel_Admin();
    $carousel_admin->edit_carousel_slide_page();
}

// ================= Timetable & Venue Management =================
function nds_timetable_management_page() {
    // Check permissions
    if (!nds_can_manage_timetables()) {
        wp_die('You do not have permission to manage timetables.');
    }
    
    // Load the timetable management page
    require_once plugin_dir_path(__FILE__) . 'timetable-schedule-management.php';
}

// ================= Calendar / Timetable =================
function nds_calendar_page() {
    if (!current_user_can('manage_options') && !nds_can_manage_timetables()) {
        wp_die('You do not have permission to view the calendar.');
    }

    // Load the calendar page
    require_once plugin_dir_path(__FILE__) . 'calendar.php';
    $calendar = new NDS_Calendar();
    $calendar->render_page();
}
