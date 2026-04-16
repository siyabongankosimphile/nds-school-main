<?php
// Prevent direct access - this file should only be included by WordPress
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Ensure WordPress functions are available
if (!function_exists('current_user_can')) {
    return;
}

// Course Overview Page with Complete Path and Career Paths CRUD
function nds_course_overview_page_content() {
    if (!current_user_can('manage_options')) { wp_die('Unauthorized'); }
    global $wpdb;

    $course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;
    
    if (!$course_id) {
        wp_die('Invalid course ID');
    }

    $table_courses = $wpdb->prefix . 'nds_courses';
    $table_programs = $wpdb->prefix . 'nds_programs';
    $table_paths = $wpdb->prefix . 'nds_faculties';
    $table_employment = $wpdb->prefix . 'nds_possible_employment';
    $table_lecturers = $wpdb->prefix . 'nds_course_lecturers';
    $table_staff = $wpdb->prefix . 'nds_staff';
    $table_students = $wpdb->prefix . 'nds_students';
    $table_enrollments = $wpdb->prefix . 'nds_student_enrollments';

    // Get course details with full path information
    $course = $wpdb->get_row($wpdb->prepare("
        SELECT c.*, 
               p.name as program_name, p.description as program_description,
               ep.id as faculty_id, ep.name as faculty_name, ep.description as faculty_description
        FROM {$table_courses} c
        LEFT JOIN {$table_programs} p ON c.program_id = p.id
        LEFT JOIN {$table_paths} ep ON p.faculty_id = ep.id
        WHERE c.id = %d
    ", $course_id), ARRAY_A);

    if (!$course) {
        wp_die('Course not found');
    }

    // Get enrolled students
    $enrolled_students = $wpdb->get_results($wpdb->prepare("
        SELECT s.*, se.enrollment_date, se.status as enrollment_status
        FROM {$table_students} s
        INNER JOIN {$table_enrollments} se ON s.id = se.student_id
        WHERE se.course_id = %d
        ORDER BY s.last_name, s.first_name
    ", $course_id), ARRAY_A);

    // Get all active students for drag-and-drop assignment
    // Exclude students already enrolled in other courses
    $all_students = $wpdb->get_results($wpdb->prepare("
        SELECT s.* 
        FROM {$table_students} s
        WHERE s.status = 'active'
        AND s.id NOT IN (
            SELECT DISTINCT student_id 
            FROM {$table_enrollments} 
            WHERE course_id != %d AND status = 'active'
        )
        ORDER BY s.last_name, s.first_name
    ", $course_id), ARRAY_A);

    // Get lecturers assigned to this course
    $lecturers = $wpdb->get_results($wpdb->prepare("
        SELECT s.* FROM {$table_staff} s
        INNER JOIN {$table_lecturers} cl ON s.id = cl.lecturer_id
        WHERE cl.course_id = %d
    ", $course_id), ARRAY_A);

    // Get all available lecturers for assignment
    $available_lecturers = $wpdb->get_results("
        SELECT s.* FROM {$table_staff} s
        WHERE s.role = 'Lecturer'
        ORDER BY s.last_name, s.first_name
    ", ARRAY_A);

    // Debug: Check if we have any staff records at all
    if (empty($available_lecturers)) {
        // Try to get all staff records to see what's in the database
        $all_staff = $wpdb->get_results("SELECT * FROM {$table_staff} LIMIT 10", ARRAY_A);
        error_log('No lecturers found. All staff records: ' . print_r($all_staff, true));
        
        // Fallback: Get all staff members with Lecturer role if none found
        $available_lecturers = $wpdb->get_results("
            SELECT s.* FROM {$table_staff} s
            WHERE s.role = 'Lecturer'
            ORDER BY s.last_name, s.first_name
        ", ARRAY_A);
    }

    // Get next course in the program
    $next_course = $wpdb->get_row($wpdb->prepare("
        SELECT * FROM {$table_courses} 
        WHERE program_id = %d AND id > %d 
        ORDER BY id ASC 
        LIMIT 1
    ", $course['program_id'], $course_id), ARRAY_A);

    // Get career paths for this course
    $career_paths = $wpdb->get_results($wpdb->prepare("
        SELECT * FROM {$table_employment} WHERE course_id = %d ORDER BY id DESC
    ", $course_id), ARRAY_A);

    // Force-load Tailwind CSS
    $plugin_dir = plugin_dir_path(dirname(__FILE__));
    $css_file   = $plugin_dir . 'assets/css/frontend.css';
    if (file_exists($css_file)) {
        wp_enqueue_style(
            'nds-tailwindcss-course-overview',
            plugin_dir_url(dirname(__FILE__)) . 'assets/css/frontend.css',
            array(),
            filemtime($css_file),
            'all'
        );
        wp_add_inline_style('nds-tailwindcss-course-overview', '
            .nds-tailwind-wrapper { all: initial !important; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important; }
            .nds-tailwind-wrapper * { box-sizing: border-box !important; }
        ');
    }
    wp_enqueue_style('nds-icons', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css', array(), null, 'all');

    ?>
    <div class="wrap">
        <div class="nds-tailwind-wrapper bg-gray-50 min-h-screen p-8" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">

            <!-- UPDATED HEADER SECTION to match qualifications page -->
            <div class="max-w-7xl mx-auto">
                <!-- Header -->
                <div class="bg-white shadow-sm border-b border-gray-200">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div class="flex justify-between items-center py-6">
                            <div class="flex items-center space-x-4">
                                <div class="w-12 h-12 bg-blue-600 rounded-xl flex items-center justify-center shadow-lg">
                                    <span class="dashicons dashicons-welcome-learn-more text-white text-2xl"></span>
                                </div>
                                <div>
                                    <h1 class="text-3xl font-bold text-gray-900">
                                        <strong><?php echo esc_html($course['name']); ?></strong>
                                    </h1>
                                    <p class="text-sm text-gray-600 mt-1">Course overview and management</p>
                                </div>
                            </div>
                            <div class="flex items-center space-x-3">
                                <a href="<?php echo esc_url(admin_url('admin.php?page=nds-courses')); ?>"
                                    class="inline-flex items-center px-4 py-2 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 text-sm font-medium shadow-sm transition-all duration-200">
                                    <i class="fas fa-arrow-left mr-2"></i>
                                    Back to Qualifications
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Breadcrumb Navigation -->
                <div class="mt-6 mb-6">
                    <nav class="flex items-center space-x-2 text-sm text-gray-600">
                        <a href="<?php echo admin_url('admin.php?page=nds-academy'); ?>" class="hover:text-blue-600 transition-colors">
                            <i class="fas fa-home mr-1"></i>NDS Academy
                        </a>
                        <i class="fas fa-chevron-right text-gray-400"></i>
                        <a href="<?php echo admin_url('admin.php?page=nds-faculties'); ?>" class="hover:text-blue-600 transition-colors">
                            Faculties
                        </a>
                        <?php if (!empty($course['faculty_id'])): ?>
                            <i class="fas fa-chevron-right text-gray-400"></i>
                            <a href="<?php echo admin_url('admin.php?page=nds-faculties&action=edit&edit=' . intval($course['faculty_id'])); ?>" class="hover:text-blue-600 transition-colors">
                                <?php echo esc_html($course['faculty_name']); ?>
                            </a>
                        <?php endif; ?>
                        <i class="fas fa-chevron-right text-gray-400"></i>
                        <a href="<?php echo admin_url('admin.php?page=nds-programs&faculty_id=' . intval($course['faculty_id'])); ?>" class="hover:text-blue-600 transition-colors">
                            <?php echo esc_html($course['program_name']); ?>
                        </a>
                        <i class="fas fa-chevron-right text-gray-400"></i>
                        <span class="text-gray-900 font-medium"><?php echo esc_html($course['name']); ?></span>
                    </nav>
                </div>

            <!-- Success Messages -->
            <?php if (isset($_GET['career_path_saved']) && $_GET['career_path_saved'] === 'success'): ?>
                <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg">
                    <i class="fas fa-check-circle mr-2"></i>
                    <strong>Success!</strong> Career path has been saved successfully.
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['career_path_deleted']) && $_GET['career_path_deleted'] === 'success'): ?>
                <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg">
                    <i class="fas fa-trash mr-2"></i>
                    <strong>Success!</strong> Career path has been deleted successfully.
                </div>
            <?php endif; ?>

            <!-- Two Column Layout -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                
                <!-- Left Column - Students Table (col-md-5 equivalent) -->
                <div class="lg:col-span-9">
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="text-xl font-bold text-gray-900 flex items-center">
                                <i class="fas fa-users text-blue-600 mr-3"></i>Enrolled Students
                                <span class="ml-2 bg-blue-100 text-blue-800 text-sm font-medium px-2 py-1 rounded-full">
                                    <?php echo count($enrolled_students); ?>
                                </span>
                            </h3>
                            <button onclick="openStudentAssignmentModal()" class="button button-primary bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition-colors">
                                <i class="fas fa-plus mr-2"></i>Manage Students
                            </button>
                        </div>

                        <!-- Table removed as requested -->
                        <div class="table-responsive">
                            <div id="enrolled-students-table">
                                <?php if (!empty($enrolled_students)): ?>
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Enrolled</th>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Current Course</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php foreach ($enrolled_students as $student): 
                                                // Get student's current course (for verification)
                                                $current_course = $wpdb->get_var($wpdb->prepare("
                                                    SELECT c.name 
                                                    FROM {$table_enrollments} se
                                                    LEFT JOIN {$table_courses} c ON se.course_id = c.id
                                                    WHERE se.student_id = %d AND se.status = 'active'
                                                    LIMIT 1
                                                ", $student['id']));
                                            ?>
                                                <tr class="student-item">
                                                    <td class="px-4 py-2 flex items-center space-x-2">
                                                        <?php if (!empty($student['avatar_url'])): ?>
                                                            <img src="<?php echo esc_url($student['avatar_url']); ?>" alt="" class="w-8 h-8 rounded-full object-cover">
                                                        <?php else: ?>
                                                            <span class="w-8 h-8 rounded-full bg-gray-200 text-gray-500 flex items-center justify-center">
                                                                <i class="fas fa-user"></i>
                                                            </span>
                                                        <?php endif; ?>
                                                         <span><?php echo esc_html(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? '')); ?></span>
                                                     </td>
                                                     <td class="px-4 py-2 text-gray-700"><?php echo esc_html($student['student_number'] ?? 'N/A'); ?></td>
                                                    <td class="px-4 py-2">
                                                        <?php if (!empty($student['status'])): ?>
                                                            <span class="inline-block px-2 py-1 rounded-full text-xs font-semibold
                                                                <?php
                                                                    switch ($student['status']) {
                                                                        case 'active':
                                                                            echo 'bg-green-100 text-green-800';
                                                                            break;
                                                                        case 'inactive':
                                                                            echo 'bg-yellow-100 text-yellow-800';
                                                                            break;
                                                                        case 'suspended':
                                                                            echo 'bg-red-100 text-red-800';
                                                                            break;
                                                                        default:
                                                                            echo 'bg-gray-100 text-gray-800';
                                                                    }
                                                                ?>">
                                                                <?php echo ucfirst(esc_html($student['status'])); ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="text-gray-400">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                     <td class="px-4 py-2 text-gray-700">
                                                         <?php echo !empty($student['enrollment_date']) ? esc_html(date('Y-m-d', strtotime($student['enrollment_date']))) : '<span class="text-gray-400">-</span>'; ?>
                                                     </td>
                                                     <td class="px-4 py-2 text-gray-700">
                                                         <?php echo esc_html($current_course ?: 'This course'); ?>
                                                     </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php else: ?>
                                    <div class="text-gray-500 text-center py-8">
                                        <i class="fas fa-user-friends text-3xl mb-2"></i>
                                        <div>No students enrolled in this course yet.</div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column - Quick Actions (col-md-3 equivalent) -->
                <div class="lg:col-span-3">
                    <div class="space-y-6">
                        
                        <!-- Quick Actions -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                            <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                                <i class="fas fa-bolt text-yellow-600 mr-3"></i>Quick Actions
                            </h3>
                            <div class="space-y-3">
                                <button onclick="openAddCareerPathModal()" class="button w-full bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-lg transition-colors text-left">
                                    <i class="fas fa-briefcase mr-2"></i>Add Career Path
                                </button>
                                <button onclick="openLecturerAssignmentModal()" class="button w-full bg-purple-600 hover:bg-purple-700 text-white font-medium py-2 px-4 rounded-lg transition-colors text-left">
                                    <i class="fas fa-chalkboard-teacher mr-2"></i>Assign Lecturer
                                </button>
                                <a href="<?php echo admin_url('admin.php?page=nds-edit-course&edit_course=' . $course_id); ?>" class="button w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition-colors text-left block">
                                    <i class="fas fa-edit mr-2"></i>Edit Course
                                </a>
                            </div>
                        </div>

                        <!-- Next Course -->
                        <?php if ($next_course): ?>
                            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                                <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                                    <i class="fas fa-arrow-right text-blue-600 mr-3"></i>Next Course
                                </h3>
                                <div class="p-4 bg-blue-50 rounded-lg">
                                    <h4 class="font-semibold text-blue-900 mb-2"><?php echo esc_html($next_course['name']); ?></h4>
                                    <p class="text-sm text-blue-800 mb-3"><?php echo esc_html($next_course['duration'] ?: 'Duration not specified'); ?></p>
                                    <a href="<?php echo admin_url('admin.php?page=nds-course-overview&course_id=' . $next_course['id']); ?>" 
                                       class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                        View Course <i class="fas fa-external-link-alt ml-1"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Assigned Lecturers -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                            <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                                <i class="fas fa-chalkboard-teacher text-purple-600 mr-3"></i>Assigned Lecturers
                            </h3>
                            
                            <?php if (!empty($lecturers)): ?>
                                <div class="space-y-3">
                                    <?php foreach ($lecturers as $lecturer): ?>
                                        <div class="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg">
                                            <div class="w-10 h-10 bg-purple-500 rounded-full flex items-center justify-center">
                                                <i class="fas fa-user text-white"></i>
                                            </div>
                                            <div class="flex-1">
                                                <p class="font-medium text-gray-900"><?php echo esc_html($lecturer['first_name'] . ' ' . $lecturer['last_name']); ?></p>
                                                <p class="text-sm text-gray-600"><?php echo esc_html($lecturer['role']); ?></p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-gray-500 italic text-sm">No lecturers assigned yet.</p>
                            <?php endif; ?>
                        </div>

                        <!-- Course Info -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                            <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                                <i class="fas fa-info-circle text-gray-600 mr-3"></i>Course Info
                            </h3>
                            <div class="space-y-3 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">ID:</span>
                                    <span class="font-medium"><?php echo esc_html($course['id'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Duration:</span>
                                    <span class="font-medium"><?php echo esc_html($course['duration'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Credits:</span>
                                    <span class="font-medium"><?php echo esc_html($course['credits'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">NQF Level:</span>
                                    <span class="font-medium"><?php echo esc_html($course['nqf_level'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Max Students:</span>
                                    <span class="font-medium"><?php echo esc_html($course['max_students'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Status:</span>
                                    <span class="px-2 py-1 text-xs font-medium rounded-full
                                        <?php echo strtolower($course['status'] ?? '') === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                        <?php echo ucfirst($course['status'] ?? 'Unknown'); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Student Assignment Modal -->
        <div id="studentAssignmentModal" class="fixed top-0 left-0 right-0 bottom-0 bg-black bg-opacity-50 hidden z-50" style="left: 160px;">
            <div class="flex items-center justify-center h-full p-4">
                <div class="bg-white rounded-xl shadow-xl max-w-6xl w-full max-h-[90vh] overflow-y-auto">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-6">
                            <h2 class="text-2xl font-bold text-gray-900">Manage Students for <?php echo esc_html($course['name']); ?></h2>
                            <button type="button" onclick="closeStudentAssignmentModal()" class="text-gray-400 hover:text-gray-600">
                                <i class="fas fa-times text-xl"></i>
                            </button>
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                            <!-- Available Students -->
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900 mb-4">Available Students</h3>
                                <div id="available-students" class="h-[400px] space-y-2 overflow-y-auto border-2 border-dashed border-gray-300 rounded-lg p-4 bg-gray-50 transition-colors duration-200 hover:bg-gray-100 hover:border-gray-400">
                                    <?php 
                                    $available_count = 0;
                                    foreach ($all_students as $student): 
                                        if (!in_array($student['id'], array_column($enrolled_students, 'id'))): 
                                            $available_count++;
                                            // Check if student is already enrolled in another course
                                            $enrolled_in_other = $wpdb->get_var($wpdb->prepare("
                                                SELECT COUNT(*) 
                                                FROM {$table_enrollments} 
                                                WHERE student_id = %d AND course_id != %d AND status = 'active'
                                            ", $student['id'], $course_id));
                                            $enrolled_in_other_text = $enrolled_in_other ? ' (Already in another course)' : '';
                                    ?>
                                        <div class="student-item bg-white border border-gray-200 rounded-lg p-3 cursor-move hover:shadow-md transition-shadow" 
                                             draggable="true" data-student-id="<?php echo $student['id']; ?>"
                                             data-already-enrolled="<?php echo $enrolled_in_other ? 'true' : 'false'; ?>">
                                            <div class="flex items-center space-x-3">
                                                <div class="w-8 h-8 bg-gray-500 rounded-full flex items-center justify-center text-white text-sm font-medium">
                                                    <?php echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?>
                                                </div>
                                                <div class="flex-1">
                                                    <p class="font-medium text-gray-900"><?php echo esc_html($student['first_name'] . ' ' . $student['last_name'] . $enrolled_in_other_text); ?></p>
                                                    <p class="text-sm text-gray-500"><?php echo esc_html($student['student_number']); ?></p>
                                                    <?php if ($enrolled_in_other): ?>
                                                        <p class="text-xs text-amber-600 mt-1">
                                                            <i class="fas fa-exclamation-triangle mr-1"></i>
                                                            Student will be moved from their current course
                                                        </p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php 
                                        endif; 
                                    endforeach; 
                                    
                                    if ($available_count === 0): ?>
                                        <div class="flex flex-col items-center justify-center h-80 text-center">
                                            <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                                <i class="fas fa-user-plus text-gray-500 text-2xl"></i>
                                            </div>
                                            <h4 class="text-lg font-medium text-gray-900 mb-2">All Students Enrolled</h4>
                                            <p class="text-gray-600 text-sm max-w-xs">All available students are already enrolled in this course</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Enrolled Students -->
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900 mb-4">Enrolled Students</h3>
                                <div id="enrolled-students" class="h-[400px] space-y-2 overflow-y-auto border-2 border-dashed border-green-300 rounded-lg p-4 bg-green-50 transition-colors duration-200 hover:bg-green-100 hover:border-green-400">
                                    <?php if (!empty($enrolled_students)): ?>
                                        <?php foreach ($enrolled_students as $student): ?>
                                            <div class="student-item bg-white border border-gray-200 rounded-lg p-3 cursor-move hover:shadow-md transition-shadow" 
                                                 draggable="true" data-student-id="<?php echo $student['id']; ?>">
                                                <div class="flex items-center space-x-3">
                                                    <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center text-white text-sm font-medium">
                                                        <?php echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?>
                                                    </div>
                                                    <div class="flex-1">
                                                        <p class="font-medium text-gray-900"><?php echo esc_html($student['first_name'] . ' ' . $student['last_name']); ?></p>
                                                        <p class="text-sm text-gray-500"><?php echo esc_html($student['student_number']); ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="flex flex-col items-center justify-center h-80 text-center">
                                            <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mb-4">
                                                <i class="fas fa-users text-green-500 text-2xl"></i>
                                            </div>
                                            <h4 class="text-lg font-medium text-gray-900 mb-2">Drop Students Here</h4>
                                            <p class="text-gray-600 text-sm max-w-xs">Drag students from the left column to enroll them in this course</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 p-4 bg-blue-50 rounded-lg">
                            <div class="flex items-start">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-info-circle text-blue-500 mt-1"></i>
                                </div>
                                <div class="ml-3">
                                    <h4 class="text-sm font-medium text-blue-800">Important Notice</h4>
                                    <p class="text-sm text-blue-700 mt-1">
                                        <strong>One Course Per Student:</strong> Each student can only be enrolled in one qualification/course at a time. 
                                        If you assign a student who is already enrolled in another course, they will be automatically removed from their current course.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-end space-x-3 mt-8 pt-6 border-t border-gray-200">
                            <button type="button" onclick="closeStudentAssignmentModal()"
                                    class="button px-6 py-3 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                                Cancel
                            </button>
                            <button type="button" onclick="saveStudentAssignments()"
                                    class="button button-primary px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-medium">
                                Save Changes
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lecturer Assignment Modal -->
        <div id="lecturerAssignmentModal" class="fixed top-0 left-0 right-0 bottom-0 bg-black bg-opacity-50 hidden z-50" style="left: 160px;">
            <div class="flex items-center justify-center h-full p-4">
                <div class="bg-white rounded-xl shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-6">
                            <h2 class="text-2xl font-bold text-gray-900">Assign Lecturer to <?php echo esc_html($course['name']); ?></h2>
                            <button type="button" onclick="closeLecturerAssignmentModal()" class="text-gray-400 hover:text-gray-600">
                                <i class="fas fa-times text-xl"></i>
                            </button>
                        </div>

                        <form id="lecturerAssignmentForm">
                            <input type="hidden" name="action" value="nds_assign_lecturer">
                            <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">
                            <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('nds_lecturer_assignment_nonce'); ?>">

                            <div class="space-y-6">
                                <div>
                                    <label for="lecturer_id" class="block text-sm font-semibold text-gray-900 mb-2">
                                        Select Lecturer <span class="text-red-500">*</span>
                                    </label>
                                    <select id="lecturer_id" name="lecturer_id" required
                                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                                        <option value="">Choose a lecturer...</option>
                                        <?php if (!empty($available_lecturers)): ?>
                                        <?php foreach ($available_lecturers as $lecturer): ?>
                                            <option value="<?php echo $lecturer['id']; ?>">
                                                <?php echo esc_html($lecturer['first_name'] . ' ' . $lecturer['last_name'] . ' (' . $lecturer['role'] . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                        <?php else: ?>
                                            <option value="" disabled>No lecturers found. Please add lecturers first.</option>
                                        <?php endif; ?>
                                    </select>
                                    <?php if (empty($available_lecturers)): ?>
                                        <p class="mt-2 text-sm text-amber-600">
                                            <i class="fas fa-exclamation-triangle mr-1"></i>
                                            No lecturers found. Please add staff members with "Lecturer" role through the Staff Management section.
                                        </p>
                                    <?php else: ?>
                                        <p class="mt-2 text-sm text-gray-500">
                                            <i class="fas fa-info-circle mr-1"></i>
                                            <?php echo count($available_lecturers); ?> lecturer(s) available for assignment.
                                        </p>
                                    <?php endif; ?>
                                </div>

                                <div>
                                    <label for="assignment_notes" class="block text-sm font-semibold text-gray-900 mb-2">
                                        Assignment Notes (Optional)
                                    </label>
                                    <textarea id="assignment_notes" name="assignment_notes" rows="3"
                                              class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                                              placeholder="Any additional notes about this assignment..."></textarea>
                                </div>
                            </div>

                            <div class="flex items-center justify-end space-x-3 mt-8 pt-6 border-t border-gray-200">
                                <button type="button" onclick="closeLecturerAssignmentModal()"
                                        class="button px-6 py-3 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                                    Cancel
                                </button>
                                <button type="submit"
                                        class="button button-primary px-6 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors font-medium">
                                    Assign Lecturer
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Career Path Modal -->
        <div id="addCareerPathModal" class="fixed top-0 left-0 right-0 bottom-0 bg-black bg-opacity-50 hidden z-50" style="left: 160px;">
            <div class="flex items-center justify-center h-full p-4">
                <div class="bg-white rounded-xl shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-6">
                            <h2 class="text-2xl font-bold text-gray-900">Add Career Path</h2>
                            <button type="button" onclick="closeCareerPathModal()" class="text-gray-400 hover:text-gray-600">
                                <i class="fas fa-times text-xl"></i>
                            </button>
                        </div>

                        <form id="careerPathForm" method="POST" action="<?php echo admin_url('admin-post.php'); ?>">
                            <?php wp_nonce_field('nds_career_path_nonce', 'nds_career_path_nonce'); ?>
                            <input type="hidden" name="action" value="nds_add_career_path">
                            <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">
                            <input type="hidden" name="career_path_id" id="career_path_id" value="">

                            <div class="space-y-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label for="company_name" class="block text-sm font-semibold text-gray-900 mb-2">
                                            Company Name <span class="text-red-500">*</span>
                                        </label>
                                        <input type="text" id="company_name" name="company_name" required
                                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                               placeholder="e.g., Google, Microsoft">
                                    </div>

                                    <div>
                                        <label for="job_title" class="block text-sm font-semibold text-gray-900 mb-2">
                                            Job Title <span class="text-red-500">*</span>
                                        </label>
                                        <input type="text" id="job_title" name="job_title" required
                                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                               placeholder="e.g., Software Developer, Chef">
                                    </div>
                                </div>

                                <div>
                                    <label for="job_description" class="block text-sm font-semibold text-gray-900 mb-2">
                                        Job Description
                                    </label>
                                    <textarea id="job_description" name="job_description" rows="4"
                                              class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                              placeholder="Describe the role and responsibilities..."></textarea>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label for="location" class="block text-sm font-semibold text-gray-900 mb-2">
                                            Location
                                        </label>
                                        <input type="text" id="location" name="location"
                                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                               placeholder="e.g., Cape Town, Remote">
                                    </div>

                                    <div>
                                        <label for="salary_range" class="block text-sm font-semibold text-gray-900 mb-2">
                                            Salary Range
                                        </label>
                                        <input type="text" id="salary_range" name="salary_range"
                                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                               placeholder="e.g., R15,000 - R25,000">
                                    </div>
                                </div>

                                <div>
                                    <label for="employment_type" class="block text-sm font-semibold text-gray-900 mb-2">
                                        Employment Type
                                    </label>
                                    <select id="employment_type" name="employment_type" required
                                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                        <option value="">Select Type</option>
                                        <option value="Full-time">Full-time</option>
                                        <option value="Part-time">Part-time</option>
                                        <option value="Contract">Contract</option>
                                        <option value="Internship">Internship</option>
                                    </select>
                                </div>
                            </div>

                            <div class="flex items-center justify-end space-x-3 mt-8 pt-6 border-t border-gray-200">
                                <button type="button" onclick="closeCareerPathModal()"
                                        class="button px-6 py-3 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                                    Cancel
                                </button>
                                <button type="submit"
                                        class="button button-primary px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors font-medium">
                                    Save Career Path
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Hidden Delete Form -->
        <form id="deleteCareerPathForm" method="POST" action="<?php echo admin_url('admin-post.php'); ?>" style="display: none;">
            <input type="hidden" name="action" value="nds_delete_career_path">
            <input type="hidden" name="career_path_id" id="delete_career_path_id">
            <?php wp_nonce_field('nds_career_path_nonce', 'nds_career_path_nonce'); ?>
        </form>

        </div>
    </div>

    <!-- Page Loader Overlay -->
    <div id="pageLoader" class="fixed inset-0 bg-black bg-opacity-30 backdrop-blur-sm z-50 hidden transition-opacity duration-300">
        <div class="flex items-center justify-center h-full">
            <div class="bg-white rounded-2xl shadow-2xl p-8 max-w-sm w-full mx-4 transform transition-all duration-300 scale-95 opacity-0" id="loaderCard">
                <div class="text-center">
                    <!-- Spinner -->
                    <div class="inline-block animate-spin rounded-full h-16 w-16 border-4 border-blue-100 border-t-blue-600 mb-4"></div>
                    
                    <!-- Loading Text -->
                    <h3 class="text-lg font-semibold text-gray-900 mb-2 transition-all duration-200" id="loaderTitle">Processing...</h3>
                    <p class="text-gray-600 text-sm transition-all duration-200" id="loaderMessage">Please wait while we update the student enrollments</p>
                    
                    <!-- Progress Bar -->
                    <div class="mt-6 bg-gray-200 rounded-full h-2 overflow-hidden">
                        <div class="bg-gradient-to-r from-blue-500 to-blue-600 h-2 rounded-full animate-pulse transition-all duration-1000 ease-out" style="width: 60%"></div>
                    </div>
                    
                    <!-- Dots Animation -->
                    <div class="mt-4 flex justify-center space-x-1">
                        <div class="w-2 h-2 bg-blue-600 rounded-full animate-bounce"></div>
                        <div class="w-2 h-2 bg-blue-600 rounded-full animate-bounce" style="animation-delay: 0.1s"></div>
                        <div class="w-2 h-2 bg-blue-600 rounded-full animate-bounce" style="animation-delay: 0.2s"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Page Loader Functions
    function showPageLoader(title = 'Processing...', message = 'Please wait while we process your request') {
        const loader = document.getElementById('pageLoader');
        const loaderCard = document.getElementById('loaderCard');
        const loaderTitle = document.getElementById('loaderTitle');
        const loaderMessage = document.getElementById('loaderMessage');
        
        if (loaderTitle) loaderTitle.textContent = title;
        if (loaderMessage) loaderMessage.textContent = message;
        
        loader.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        
        // Trigger card animation
        setTimeout(() => {
            if (loaderCard) {
                loaderCard.classList.remove('scale-95', 'opacity-0');
                loaderCard.classList.add('scale-100', 'opacity-100');
            }
        }, 10);
    }
    
    function hidePageLoader() {
        const loader = document.getElementById('pageLoader');
        const loaderCard = document.getElementById('loaderCard');
        
        if (loaderCard) {
            loaderCard.classList.remove('scale-100', 'opacity-100');
            loaderCard.classList.add('scale-95', 'opacity-0');
        }
        
        // Wait for animation to complete before hiding
        setTimeout(() => {
            loader.classList.add('hidden');
            document.body.style.overflow = '';
        }, 300);
    }
    
    function updateLoaderMessage(title, message) {
        const loaderTitle = document.getElementById('loaderTitle');
        const loaderMessage = document.getElementById('loaderMessage');
        
        if (loaderTitle) loaderTitle.textContent = title;
        if (loaderMessage) loaderMessage.textContent = message;
    }

    // Career Path Modal Functions
    function openAddCareerPathModal() {
        document.getElementById('addCareerPathModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        
        // Show loader while modal opens
        showPageLoader('Loading Form', 'Preparing career path form...');
        
        resetCareerPathForm();
        
        // Hide loader after modal is ready
        setTimeout(() => {
            hidePageLoader();
        }, 200);
    }

    function closeCareerPathModal() {
        document.getElementById('addCareerPathModal').classList.add('hidden');
        document.body.style.overflow = '';
        resetCareerPathForm();
    }

    function resetCareerPathForm() {
        document.getElementById('careerPathForm').reset();
        document.getElementById('career_path_id').value = '';
        document.querySelector('#careerPathForm button[type="submit"]').textContent = 'Save Career Path';
    }

    function editCareerPath(careerId) {
        openAddCareerPathModal();
        document.getElementById('career_path_id').value = careerId;
        document.querySelector('#careerPathForm button[type="submit"]').textContent = 'Update Career Path';
    }

    function deleteCareerPath(careerId, jobTitle) {
        if (confirm(`Are you sure you want to delete the career path "${jobTitle}"?`)) {
            document.getElementById('delete_career_path_id').value = careerId;
            document.getElementById('deleteCareerPathForm').submit();
        }
    }

    // Student Assignment Modal Functions
    function openStudentAssignmentModal() {
        document.getElementById('studentAssignmentModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        
        // Show loader while initializing modal
        showPageLoader('Loading Students', 'Preparing student lists for assignment...');
        
        // Initialize drag and drop
        initializeDragAndDrop();
        
        // Hide loader after modal is ready
        setTimeout(() => {
            hidePageLoader();
        }, 300);
    }

    function closeStudentAssignmentModal() {
        document.getElementById('studentAssignmentModal').classList.add('hidden');
        document.body.style.overflow = '';
    }

    // Lecturer Assignment Modal Functions
    function openLecturerAssignmentModal() {
        document.getElementById('lecturerAssignmentModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        
        // Show loader while modal opens
        showPageLoader('Loading Lecturers', 'Preparing lecturer assignment form...');
        
        // Hide loader after modal is ready
        setTimeout(() => {
            hidePageLoader();
        }, 200);
    }

    function closeLecturerAssignmentModal() {
        document.getElementById('lecturerAssignmentModal').classList.add('hidden');
        document.body.style.overflow = '';
    }

    // Handle lecturer assignment form submission
    document.addEventListener('DOMContentLoaded', function() {
        const lecturerForm = document.getElementById('lecturerAssignmentForm');
        if (lecturerForm) {
            lecturerForm.addEventListener('submit', function(e) {
                e.preventDefault();
                assignLecturer();
            });
        }
    });

    function assignLecturer() {
        const form = document.getElementById('lecturerAssignmentForm');
        const formData = new FormData(form);
        
        // Show loader
        showPageLoader('Assigning Lecturer', 'Assigning lecturer to course...');
        
        // Show loading state on submit button
        const submitButton = form.querySelector('button[type="submit"]');
        const originalText = submitButton.textContent;
        submitButton.textContent = 'Assigning...';
        submitButton.disabled = true;

        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                hidePageLoader();
                closeLecturerAssignmentModal();
                showNotification('Lecturer assigned successfully!', 'success');
                
                // Reset form
                form.reset();
                
                // Refresh the page to show updated lecturer list
                setTimeout(() => {
                    location.reload();
                }, 1000);
            } else {
                hidePageLoader();
                showNotification('Error assigning lecturer: ' + (data.data || 'Unknown error'), 'error');
            }
        })
        .catch(error => {
            hidePageLoader();
            console.error('Fetch error:', error);
            showNotification('Error assigning lecturer. Please try again.', 'error');
        })
        .finally(() => {
            submitButton.textContent = originalText;
            submitButton.disabled = false;
        });
    }

    // Drag and Drop Functionality
    function initializeDragAndDrop() {
        // Remove existing event listeners to prevent duplicates
        const existingItems = document.querySelectorAll('.student-item');
        existingItems.forEach(item => {
            item.removeEventListener('dragstart', handleDragStart);
            item.removeEventListener('dragend', handleDragEnd);
        });

        const availableStudents = document.getElementById('available-students');
        const enrolledStudents = document.getElementById('enrolled-students');

        [availableStudents, enrolledStudents].forEach(container => {
            if (container) {
                container.removeEventListener('dragover', handleDragOver);
                container.removeEventListener('dragleave', handleDragLeave);
                container.removeEventListener('drop', handleDrop);
            }
        });

        // Add event listeners to all student items
        const studentItems = document.querySelectorAll('.student-item');
        studentItems.forEach(item => {
            item.addEventListener('dragstart', handleDragStart);
            item.addEventListener('dragend', handleDragEnd);
        });

        // Add event listeners to containers
        if (availableStudents) {
            availableStudents.addEventListener('dragover', handleDragOver);
            availableStudents.addEventListener('dragleave', handleDragLeave);
            availableStudents.addEventListener('drop', handleDrop);
        }

        if (enrolledStudents) {
            enrolledStudents.addEventListener('dragover', handleDragOver);
            enrolledStudents.addEventListener('dragleave', handleDragLeave);
            enrolledStudents.addEventListener('drop', handleDrop);
        }
    }

    let draggedElement = null;

    function handleDragStart(e) {
        draggedElement = this;
        this.style.opacity = '0.5';
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/html', this.outerHTML);
    }

    function handleDragEnd(e) {
        this.style.opacity = '';
        draggedElement = null;
    }

    function handleDragOver(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        
        // Add visual feedback for drop zones
        const container = e.currentTarget;
        container.classList.add('border-blue-400', 'bg-blue-50');
    }

    function handleDragLeave(e) {
        const container = e.currentTarget;
        container.classList.remove('border-blue-400', 'bg-blue-50');
    }

    function handleDrop(e) {
        e.preventDefault();
        
        // Remove visual feedback
        const container = e.currentTarget;
        container.classList.remove('border-blue-400', 'bg-blue-50');
        
        if (draggedElement) {
            const studentId = draggedElement.dataset.studentId;
            const targetContainer = e.currentTarget;
            
            // Check if student is already enrolled in another course (when moving to enrolled)
            if (targetContainer.id === 'enrolled-students') {
                const alreadyEnrolled = draggedElement.dataset.alreadyEnrolled === 'true';
                if (alreadyEnrolled) {
                    if (!confirm('This student is already enrolled in another course. Moving them will remove them from their current course. Continue?')) {
                        return;
                    }
                }
            }
            
            // Move the element to the new container
            targetContainer.appendChild(draggedElement);
            
            // Update the avatar color based on container
            const avatar = draggedElement.querySelector('.w-8.h-8');
            if (targetContainer.id === 'enrolled-students') {
                avatar.className = 'w-8 h-8 bg-green-500 rounded-full flex items-center justify-center text-white text-sm font-medium';
            } else {
                avatar.className = 'w-8 h-8 bg-gray-500 rounded-full flex items-center justify-center text-white text-sm font-medium';
            }
            
            // Hide the empty state message if it exists
            const emptyState = targetContainer.querySelector('.flex.flex-col.items-center.justify-center');
            if (emptyState) {
                emptyState.style.display = 'none';
            }
        }
    }

    // Save Student Assignments via AJAX
    function saveStudentAssignments() {
        const enrolledStudents = document.querySelectorAll('#enrolled-students .student-item');
        const enrolledStudentIds = Array.from(enrolledStudents).map(item => item.dataset.studentId);
        
        console.log('Saving student enrollments:', enrolledStudentIds);
        
        // Show page loader
        showPageLoader('Saving Enrollments', 'Updating student enrollments for this course...');
        
        const formData = new FormData();
        formData.append('action', 'nds_update_student_enrollments');
        formData.append('course_id', <?php echo $course_id; ?>);
        formData.append('enrolled_student_ids', JSON.stringify(enrolledStudentIds));
        formData.append('nonce', '<?php echo wp_create_nonce('nds_student_enrollment_nonce'); ?>');

        // Show loading state on button
        const saveButton = document.querySelector('button[onclick="saveStudentAssignments()"]');
        const originalText = saveButton.textContent;
        saveButton.textContent = 'Saving...';
        saveButton.disabled = true;

        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            console.log('Response status:', response.status);
            updateLoaderMessage('Processing Response', 'Analyzing enrollment changes...');
            return response.json();
        })
        .then(data => {
            console.log('AJAX response:', data);
            if (data.success) {
                updateLoaderMessage('Updating Interface', 'Refreshing student table and lists...');
                
                // Update the main table with AJAX
                updateStudentsTable();
                closeStudentAssignmentModal();
                
                // Show success message with more details
                const addedCount = data.data?.added_count || 0;
                const removedCount = data.data?.removed_count || 0;
                const movedCount = data.data?.moved_count || 0;
                let message = 'Student enrollments updated successfully!';
                let details = [];
                if (addedCount > 0) details.push(`${addedCount} added`);
                if (removedCount > 0) details.push(`${removedCount} removed`);
                if (movedCount > 0) details.push(`${movedCount} moved from other courses`);
                
                if (details.length > 0) {
                    message += ` (${details.join(', ')})`;
                }
                
                // Hide loader and show success
                hidePageLoader();
                showNotification(message, 'success');
            } else {
                console.error('AJAX error:', data);
                hidePageLoader();
                showNotification('Error updating enrollments: ' + (data.data || 'Unknown error'), 'error');
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            hidePageLoader();
            showNotification('Error updating enrollments. Please try again.', 'error');
        })
        .finally(() => {
            saveButton.textContent = originalText;
            saveButton.disabled = false;
        });
    }

    // Update Students Table and Modal via AJAX
    function updateStudentsTable() {
        // Show loader if not already showing
        const loader = document.getElementById('pageLoader');
        if (loader.classList.contains('hidden')) {
            showPageLoader('Refreshing Data', 'Updating student information...');
        } else {
            updateLoaderMessage('Refreshing Data', 'Updating student information...');
        }
        
        const formData = new FormData();
        formData.append('action', 'nds_get_course_students');
        formData.append('course_id', <?php echo $course_id; ?>);
        formData.append('nonce', '<?php echo wp_create_nonce('nds_get_students_nonce'); ?>');

        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            console.log('Table update response:', data);
            if (data.success) {
                // Update the main table content
                const tableContainer = document.getElementById('enrolled-students-table');
                if (tableContainer) {
                    if (data.data && data.data.data && data.data.data.trim() !== '') {
                        // If we have data, wrap it in a table structure
                        tableContainer.innerHTML = `
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Enrolled</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Current Course</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    ${data.data.data}
                                </tbody>
                            </table>
                        `;
                    } else {
                        // No students, show empty state
                        tableContainer.innerHTML = `
                            <div class="text-gray-500 text-center py-8">
                                <i class="fas fa-user-friends text-3xl mb-2"></i>
                                <div>No students enrolled in this course yet.</div>
                            </div>
                        `;
                    }
                }
                
                // Update the count badge next to "Enrolled Students"
                // Find the h3 with "Enrolled Students" text and get the next span sibling
                const enrolledHeader = Array.from(document.querySelectorAll('h3')).find(h3 => 
                    h3.textContent.includes('Enrolled Students')
                );
                if (enrolledHeader) {
                    const countBadge = enrolledHeader.nextElementSibling;
                    if (countBadge && countBadge.classList.contains('bg-blue-100')) {
                        countBadge.textContent = data.data.count || 0;
                    }
                }
                
                // Also try alternative selectors for the count badge
                const alternativeBadges = document.querySelectorAll('.bg-blue-100.text-blue-800');
                alternativeBadges.forEach(badge => {
                    badge.textContent = data.data.count || 0;
                });
                
                // Update modal student lists
                updateModalStudentLists();
                
                // Update any other counts or badges that might need updating
                updateAllCounts(data.data.count || 0);
                
                // Hide loader after all updates are complete
                setTimeout(() => {
                    hidePageLoader();
                }, 500); // Small delay to ensure all updates are visible
            }
        })
        .catch(error => {
            console.error('Error updating table:', error);
            hidePageLoader();
            showNotification('Error updating table. <a href="#" onclick="location.reload()" class="underline">Click here to refresh</a>', 'error');
        });
    }
    
    // Update the modal student lists (enrolled and available)
    function updateModalStudentLists() {
        updateLoaderMessage('Updating Lists', 'Refreshing student lists in modal...');
        
        const formData = new FormData();
        formData.append('action', 'nds_get_modal_student_lists');
        formData.append('course_id', <?php echo $course_id; ?>);
        formData.append('nonce', '<?php echo wp_create_nonce('nds_get_students_nonce'); ?>');

        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update enrolled students in modal
                const enrolledContainer = document.getElementById('enrolled-students');
                if (enrolledContainer && data.data.enrolled_html) {
                    enrolledContainer.innerHTML = data.data.enrolled_html;
                }
                
                // Update available students in modal
                const availableContainer = document.getElementById('available-students');
                if (availableContainer && data.data.available_html) {
                    availableContainer.innerHTML = data.data.available_html;
                }
                
                // Reinitialize drag and drop for new elements
                initializeDragAndDrop();
                
                // Update modal counts if available
                if (data.data.enrolled_count !== undefined) {
                    updateModalCounts(data.data.enrolled_count, data.data.available_count);
                }
            }
        })
        .catch(error => {
            console.error('Error updating modal lists:', error);
        });
    }
    
    // Update all counts throughout the interface
    function updateAllCounts(enrolledCount) {
        // Update any other count displays that might exist
        const allCountElements = document.querySelectorAll('[data-student-count]');
        allCountElements.forEach(element => {
            element.textContent = enrolledCount;
        });
        
        // Update any text that mentions student count
        const countTexts = document.querySelectorAll('.student-count-text');
        countTexts.forEach(element => {
            element.textContent = enrolledCount;
        });
    }
    
    // Update modal-specific counts
    function updateModalCounts(enrolledCount, availableCount) {
        // Update modal headers if they have count badges
        const modalEnrolledHeader = document.querySelector('#enrolled-students').previousElementSibling;
        if (modalEnrolledHeader && modalEnrolledHeader.querySelector('.count-badge')) {
            modalEnrolledHeader.querySelector('.count-badge').textContent = enrolledCount;
        }
        
        const modalAvailableHeader = document.querySelector('#available-students').previousElementSibling;
        if (modalAvailableHeader && modalAvailableHeader.querySelector('.count-badge')) {
            modalAvailableHeader.querySelector('.count-badge').textContent = availableCount;
        }
    }

    // Notification System
    function showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 z-50 px-6 py-3 rounded-lg shadow-lg text-white font-medium transition-all duration-300 ${
            type === 'success' ? 'bg-green-500' : 
            type === 'error' ? 'bg-red-500' : 
            'bg-blue-500'
        }`;
        
        // Check if message contains HTML
        if (message.includes('<')) {
            notification.innerHTML = message;
        } else {
        notification.textContent = message;
        }
        
        document.body.appendChild(notification);
        
        // Auto remove after 3 seconds
        setTimeout(() => {
            notification.style.opacity = '0';
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }, 3000);
    }

    // Initialize drag and drop when page loads
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize drag and drop for any existing elements
        if (document.querySelector('.student-item')) {
            initializeDragAndDrop();
        }
    });
    </script>
    <?php
}   