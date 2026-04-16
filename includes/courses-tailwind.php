<?php
// Prevent direct access - this file should only be included by WordPress
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Ensure WordPress functions are available
if (!function_exists('current_user_can')) {
    return;
}

function nds_course_overview($course, $option = 1)
{
    if ($option == 1) {
?>
        <div class="border border-gray-200 rounded-lg p-6 hover:shadow-md transition-shadow duration-200">
            <div class="flex items-start justify-between">
                <div class="flex-1">
                    <div class="flex items-center space-x-3 mb-3">
                        <h4 class="text-lg font-semibold text-gray-900">
                            <a href="<?php echo admin_url('admin.php?page=nds-course-overview&course_id=' . $course['id']); ?>"
                                class="hover:text-blue-600 transition-colors">
                                <?php echo esc_html($course['name']); ?>
                            </a>
                        </h4>
                        <span class="px-2 py-1 rounded-full text-xs font-medium
                                                            <?php echo strtolower($course['status']) === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                            <?php echo ucfirst($course['status']); ?>
                        </span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div class="flex items-center text-sm text-gray-600">
                            <i class="fas fa-graduation-cap mr-2"></i>
                            <?php echo esc_html($course['program_name'] ?: 'No Program'); ?>
                        </div>
                        <div class="flex items-center text-sm text-gray-600">
                            <i class="fas fa-clock mr-2"></i>
                            <?php echo esc_html(isset($course['duration']) && $course['duration'] ? $course['duration'] : 'N/A'); ?> <?php echo isset($course['duration']) && $course['duration'] ? 'weeks' : ''; ?>
                        </div>
                    </div>

                    <?php if (!empty($course['description'])): ?>
                        <p class="text-gray-600 mb-4 text-sm"><?php echo esc_html(substr($course['description'], 0, 150)) . (strlen($course['description']) > 150 ? '...' : ''); ?></p>
                    <?php endif; ?>

                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div class="flex items-center text-gray-500">
                            <i class="fas fa-chalkboard-teacher mr-2 text-gray-700"></i>
                            <?php echo intval($course['lecturer_count']); ?> Instructors
                        </div>
                        <div class="flex items-center text-gray-500">
                            <i class="fas fa-users mr-2 text-gray-700"></i>
                            <?php echo intval($course['student_count']); ?> Students
                        </div>
                    </div>
                </div>

                <div class="flex items-center space-x-2 ml-4">
                    <a href="<?php echo admin_url('admin.php?page=nds-edit-course&edit_course=' . $course['id']); ?>"
                        class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                        <i class="fas fa-edit mr-1"></i>Edit
                    </a>
                    <button type="button" onclick="confirmDelete(<?php echo $course['id']; ?>, '<?php echo esc_js($course['name']); ?>')"
                        class="inline-flex items-center px-3 py-2 border border-red-300 rounded-lg text-sm font-medium text-red-700 bg-white hover:bg-red-50 transition-colors">
                        <i class="fas fa-trash mr-1"></i>Delete
                    </button>
                </div>
            </div>
        </div>
    <?php
    }
    if ($option == 2) {
    ?>
        <div class="course-card-wrapper courseCard">
            <a href="<?php echo admin_url('admin.php?page=nds-course-overview&course_id=' . $course['id']); ?>"
                class="block courseCard">
                <!-- Program and Duration Info -->
                <div class="mb-3">
                    <div class="flex items-center text-sm text-gray-600 mb-1">
                        <i class="fas fa-graduation-cap mr-2 text-gray-700"></i>
                        <span class="truncate"><?php echo esc_html($course['program_name'] ?: 'No Program'); ?></span>
                    </div>
                    <div class="flex items-center text-sm text-gray-600">
                        <i class="fas fa-clock mr-2 text-gray-700"></i>
                        <span><?php echo esc_html(isset($course['duration']) && $course['duration'] ? $course['duration'] : 'N/A'); ?> <?php echo isset($course['duration']) && $course['duration'] ? 'weeks' : ''; ?></span>
                    </div>
                </div>

                <!-- Description -->
                <?php if (!empty($course['description'])): ?>
                    <p class="text-gray-600 text-sm mb-3 line-clamp-2"><?php echo esc_html(substr($course['description'], 0, 100)) . (strlen($course['description']) > 100 ? '...' : ''); ?></p>
                <?php endif; ?>

                <!-- Stats -->
                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div class="flex items-center text-gray-600">
                        <div class="w-6 h-6 bg-purple-100 rounded flex items-center justify-center mr-2">
                            <i class="fas fa-chalkboard-teacher text-purple-600 text-xs"></i>
                        </div>
                        <div>
                            <div class="font-medium"><?php echo intval($course['lecturer_count']); ?></div>
                            <div class="text-xs text-gray-500">Instructors</div>
                        </div>
                    </div>
                    <div class="flex items-center text-gray-600">
                        <div class="w-6 h-6 bg-orange-100 rounded flex items-center justify-center mr-2">
                            <i class="fas fa-users text-orange-600 text-xs"></i>
                        </div>
                        <div>
                            <div class="font-medium"><?php echo intval($course['student_count']); ?></div>
                            <div class="text-xs text-gray-500">Students</div>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    <?php
    }
}

// Modern Qualifications Management with Tailwind CSS - UNLIMITED QUALIFICATIONS VERSION
function nds_courses_page_tailwind()
{
    // Prevent any global variable conflicts
    if (isset($GLOBALS['courses'])) unset($GLOBALS['courses']);
    if (isset($GLOBALS['paginated_courses'])) unset($GLOBALS['paginated_courses']);
    if (isset($GLOBALS['all_courses_query'])) unset($GLOBALS['all_courses_query']);
    
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    global $wpdb;

    $table_courses = $wpdb->prefix . 'nds_courses';
    $table_programs = $wpdb->prefix . 'nds_programs';
    $table_staff = $wpdb->prefix . 'nds_staff';
    $table_assignments = $wpdb->prefix . 'nds_course_lecturers';
    $table_modules = $wpdb->prefix . 'nds_modules';

    // ============================================
    // PAGINATION SETUP - 10 PER PAGE
    // ============================================
    $per_page = 10;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $per_page;
    
    // Get program_id from URL for filtering
    $filter_program_id = isset($_GET['program_id']) ? intval($_GET['program_id']) : 0;

    // Build WHERE clause for filtering
    $where_clause = '';
    $where_values = array();
    if ($filter_program_id > 0) {
        $where_clause = "WHERE program_id = %d";
        $where_values[] = $filter_program_id;
    }

    // Get total courses count for pagination
    $count_query = "SELECT COUNT(*) FROM {$table_courses} c";
    if (!empty($where_clause)) {
        $count_query .= " " . $where_clause;
    }
    
    if (!empty($where_values)) {
        $total_courses = $wpdb->get_var($wpdb->prepare($count_query, $where_values));
    } else {
        $total_courses = $wpdb->get_var($count_query);
    }
    
    $total_pages = ceil($total_courses / $per_page);

    // ============================================
    // 1. GET PAGINATED COURSES FOR MAIN TABLE (ONLY)
    // ============================================
    $query = "SELECT c.*, c.duration_weeks as duration FROM {$table_courses} c";
    if (!empty($where_clause)) {
        $query .= " " . $where_clause;
    }
    $query .= " ORDER BY c.name ASC LIMIT %d OFFSET %d";
    
    // Add pagination parameters to the prepare statement
    if (!empty($where_values)) {
        $paginated_params = array_merge($where_values, array($per_page, $offset));
        $paginated_courses = $wpdb->get_results($wpdb->prepare($query, $paginated_params), ARRAY_A);
    } else {
        $paginated_courses = $wpdb->get_results($wpdb->prepare($query, $per_page, $offset), ARRAY_A);
    }

    if (empty($paginated_courses)) {
        $paginated_courses = array();
    } else {
        // Get program names in a separate query
        $program_ids = array_unique(array_filter(array_column($paginated_courses, 'program_id')));
        $programs_data = array();
        
        if (!empty($program_ids)) {
            $program_ids_placeholder = implode(',', array_fill(0, count($program_ids), '%d'));
            $programs_results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, name FROM {$table_programs} WHERE id IN ({$program_ids_placeholder})",
                    $program_ids
                ),
                ARRAY_A
            );
            
            foreach ($programs_results as $p) {
                $programs_data[$p['id']] = $p['name'];
            }
        }

        // Get lecturer counts for paginated courses
        $course_ids = array_column($paginated_courses, 'id');
        $lecturer_counts_by_course = array();
        $student_counts_by_course = array();
        
        if (!empty($course_ids)) {
            $course_ids_placeholder = implode(',', array_fill(0, count($course_ids), '%d'));
            
            $lecturer_counts = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT course_id, COUNT(DISTINCT lecturer_id) as count 
                    FROM {$table_assignments} 
                    WHERE course_id IN ({$course_ids_placeholder})
                    GROUP BY course_id",
                    $course_ids
                ),
                ARRAY_A
            );
            
            foreach ($lecturer_counts as $lc) {
                $lecturer_counts_by_course[$lc['course_id']] = $lc['count'];
            }

            // Get student counts for paginated courses
            $student_counts = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT se.course_id, COUNT(DISTINCT se.student_id) as count 
                    FROM {$wpdb->prefix}nds_student_enrollments se 
                    WHERE se.course_id IN ({$course_ids_placeholder})
                    GROUP BY se.course_id",
                    $course_ids
                ),
                ARRAY_A
            );
            
            foreach ($student_counts as $sc) {
                $student_counts_by_course[$sc['course_id']] = $sc['count'];
            }
        }

        // Combine all data for paginated courses
        foreach ($paginated_courses as &$course) {
            $course['program_name'] = isset($programs_data[$course['program_id']]) ? $programs_data[$course['program_id']] : null;
            $course['lecturer_count'] = isset($lecturer_counts_by_course[$course['id']]) ? $lecturer_counts_by_course[$course['id']] : 0;
            $course['student_count'] = isset($student_counts_by_course[$course['id']]) ? $student_counts_by_course[$course['id']] : 0;
        }
        unset($course); // Break the reference
    }

    // ============================================
    // 2. GET ALL COURSES FOR STATISTICS AND DROPDOWNS (NO PAGINATION)
    // ============================================
    $all_query = "SELECT c.*, c.duration_weeks as duration, p.name as program_name
                  FROM {$table_courses} c
                  LEFT JOIN {$table_programs} p ON c.program_id = p.id";
    
    if (!empty($where_clause)) {
        $all_query .= " " . str_replace('program_id', 'c.program_id', $where_clause);
    }
    $all_query .= " ORDER BY c.name ASC";
    
    if (!empty($where_values)) {
        // Use only the filter values, not pagination values
        $all_courses_query = $wpdb->get_results($wpdb->prepare($all_query, $where_values), ARRAY_A);
    } else {
        $all_courses_query = $wpdb->get_results($all_query, ARRAY_A);
    }

    // Calculate statistics from ALL courses
    $total_courses_all = count($all_courses_query);
    $active_courses = 0;
    foreach ($all_courses_query as $c) {
        if (strtolower($c['status'] ?? '') === 'active') {
            $active_courses++;
        }
    }
    
    // Get total lecturers and students stats from ALL courses
    $total_lecturers = 0;
    $total_students = 0;
    
    if (!empty($all_courses_query)) {
        $all_course_ids = array_column($all_courses_query, 'id');
        if (!empty($all_course_ids)) {
            $all_course_ids_placeholder = implode(',', array_fill(0, count($all_course_ids), '%d'));
            
            $total_lecturers = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(DISTINCT lecturer_id) 
                    FROM {$table_assignments} 
                    WHERE course_id IN ({$all_course_ids_placeholder})",
                    $all_course_ids
                )
            ) ?: 0;
            
            $total_students = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(DISTINCT student_id) 
                    FROM {$wpdb->prefix}nds_student_enrollments 
                    WHERE course_id IN ({$all_course_ids_placeholder})",
                    $all_course_ids
                )
            ) ?: 0;
        }
    }

    // Get the current program info if filtering by program_id
    $current_program = null;
    if ($filter_program_id) {
        $current_program = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_programs} WHERE id = %d", $filter_program_id), ARRAY_A);
    }

    // Get programs for dropdown (cached)
    $programs = wp_cache_get('nds_programs_list', 'nds');
    if (false === $programs) {
        $programs = $wpdb->get_results("SELECT id, name as program_name FROM {$table_programs} ORDER BY name LIMIT 100", ARRAY_A);
        wp_cache_set('nds_programs_list', $programs, 'nds', 300);
    }

    // Get staff for lecturer assignment (limited to prevent timeout)
    $staff = $wpdb->get_results("
        SELECT id, first_name, last_name, role 
        FROM {$table_staff} 
        WHERE role LIKE '%instructor%' 
           OR role LIKE '%chef%' 
           OR role LIKE '%lecturer%' 
        ORDER BY first_name, last_name 
        LIMIT 200
    ", ARRAY_A);

    // Get recent qualifications (limited to 5 for sidebar) - ALWAYS get all, not filtered by program
    $recent_courses = $wpdb->get_results("
        SELECT c.id, c.name, c.created_at, p.name as program_name
        FROM {$table_courses} c
        LEFT JOIN {$table_programs} p ON c.program_id = p.id
        ORDER BY c.created_at DESC
        LIMIT 5
    ", ARRAY_A);

    // Get program_id from URL for auto-selection
    $selected_program_id = $filter_program_id;

    // Force-load Tailwind CSS for this screen to avoid WP admin CSS conflicts
    $plugin_dir = plugin_dir_path(dirname(__FILE__));
    $css_file   = $plugin_dir . 'assets/css/frontend.css';
    if (file_exists($css_file)) {
        wp_enqueue_style(
            'nds-tailwindcss-qualifications',
            plugin_dir_url(dirname(__FILE__)) . 'assets/css/frontend.css',
            array(),
            filemtime($css_file),
            'all'
        );
        wp_add_inline_style('nds-tailwindcss-qualifications', '
            .wrap.nds-courses-wrap { padding-bottom: 64px; }
            body.admin_page_nds-courses #wpfooter { display: none; }
            
            /* Sidebar Container - Single Scroll Bar for entire sidebar */
            .sidebar-card {
                display: flex;
                flex-direction: column;
                height: 800px;
                max-height: 800px;
            }
            
            /* Single scrollable content area for entire sidebar */
            .sidebar-scrollable-content {
                flex: 1;
                overflow-y: auto;
                overflow-x: hidden;
                scrollbar-width: thin;
                scrollbar-color: #cbd5e0 #f1f5f9;
                padding: 1rem;
            }
            
            /* Scrollbar Styling for main sidebar */
            .sidebar-scrollable-content::-webkit-scrollbar {
                width: 6px;
            }
            .sidebar-scrollable-content::-webkit-scrollbar-track {
                background: #f1f5f9;
                border-radius: 10px;
            }
            .sidebar-scrollable-content::-webkit-scrollbar-thumb {
                background: #cbd5e0;
                border-radius: 10px;
            }
            .sidebar-scrollable-content::-webkit-scrollbar-thumb:hover {
                background: #94a3b8;
            }
            
            /* Section Scrollable Containers - Each section has its own scroll */
            .section-scrollable {
                max-height: 200px;
                overflow-y: auto;
                overflow-x: hidden;
                padding-right: 4px;
                scrollbar-width: thin;
                scrollbar-color: #cbd5e0 #f1f5f9;
            }
            
            /* Scrollbar Styling for section containers */
            .section-scrollable::-webkit-scrollbar {
                width: 4px;
            }
            .section-scrollable::-webkit-scrollbar-track {
                background: #f1f5f9;
                border-radius: 10px;
            }
            .section-scrollable::-webkit-scrollbar-thumb {
                background: #cbd5e0;
                border-radius: 10px;
            }
            .section-scrollable::-webkit-scrollbar-thumb:hover {
                background: #94a3b8;
            }
            
            /* Specific height for each section scrollable */
            .recent-scrollable {
                max-height: 220px;
            }
            
            .assign-scrollable {
                max-height: 280px;
            }
            
            .actions-scrollable {
                max-height: 120px;
            }
            
            .programs-scrollable {
                max-height: 180px;
            }
            
            /* Sidebar sections spacing */
            .nds-sidebar-section {
                margin-bottom: 1.75rem;
            }
            .nds-sidebar-section:last-child {
                margin-bottom: 0;
            }
            
            .qualifications-table th {
                white-space: nowrap;
            }
            
            /* Table container with scrolling for paginated rows */
            .qualifications-table-container {
                overflow-x: auto;
                border-radius: 0.5rem;
            }
            
            /* Sticky header for table */
            .qualifications-table thead th {
                background-color: #f9fafb;
                border-bottom: 1px solid #e5e7eb;
            }
            
            /* Pagination styling */
            .nds-pagination {
                display: flex;
                align-items: center;
                justify-content: flex-end;
                gap: 0.5rem;
                margin-top: 1rem;
            }
            .nds-pagination .page-numbers {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-width: 36px;
                height: 36px;
                padding: 0 0.5rem;
                border-radius: 0.375rem;
                font-size: 0.875rem;
                font-weight: 500;
                color: #374151;
                background-color: #ffffff;
                border: 1px solid #d1d5db;
                transition: all 0.2s;
            }
            .nds-pagination .page-numbers:hover {
                background-color: #f3f4f6;
                border-color: #9ca3af;
            }
            .nds-pagination .page-numbers.current {
                background-color: #3b82f6;
                border-color: #3b82f6;
                color: white;
            }
            .nds-pagination .page-numbers.disabled {
                opacity: 0.5;
                pointer-events: none;
            }
        ');
    }
    wp_enqueue_style('nds-icons', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css', array(), null, 'all');

    ?>
    <div class="wrap nds-courses-wrap">
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
                                <?php echo $current_program ? esc_html($current_program['name']) : '<strong>Qualifications Management</strong>'; ?>
                            </h1>
                            <p class="text-sm text-gray-600 mt-1">Manage Qualifications, assign instructors, and track student enrollment</p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-3">
                        <?php if ($current_program): ?>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=nds-courses')); ?>"
                                class="inline-flex items-center px-4 py-2 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 text-sm font-medium shadow-sm transition-all duration-200">
                                <i class="fas fa-arrow-left mr-2"></i>
                                Back to Qualifications
                            </a>
                        <?php endif; ?>
                        <a href="#addCourseModal" class="inline-flex items-center px-4 py-2 rounded-lg bg-green-600 hover:bg-green-700 text-white text-sm font-medium shadow-md hover:shadow-lg transition-all duration-200">
                            <i class="fas fa-plus mr-2"></i>
                            Add Qualification
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

            <!-- Success/Error Messages -->
            <?php if (isset($_GET['course_created']) && $_GET['course_created'] === 'success'): ?>
                <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-4 flex items-center">
                    <span class="dashicons dashicons-yes-alt text-emerald-600 mr-3 text-xl"></span>
                    <div>
                        <h3 class="text-sm font-semibold text-emerald-800">Success</h3>
                        <p class="text-sm text-emerald-700">Qualification created successfully!</p>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['module_created']) && $_GET['module_created'] === 'success'): ?>
                <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-4 flex items-center">
                    <span class="dashicons dashicons-yes-alt text-emerald-600 mr-3 text-xl"></span>
                    <div>
                        <h3 class="text-sm font-semibold text-emerald-800">Success</h3>
                        <p class="text-sm text-emerald-700">Module added successfully!</p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['success']) && $_GET['success'] === 'instructor_assigned'): ?>
                <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-4 flex items-center">
                    <span class="dashicons dashicons-yes-alt text-emerald-600 mr-3 text-xl"></span>
                    <div>
                        <h3 class="text-sm font-semibold text-emerald-800">Success</h3>
                        <p class="text-sm text-emerald-700">Instructor assigned successfully!</p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['error'])): ?>
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 flex items-center">
                    <span class="dashicons dashicons-warning text-red-600 mr-3 text-xl"></span>
                    <div>
                        <h3 class="text-sm font-semibold text-red-800">Error</h3>
                        <p class="text-sm text-red-700">
                    <?php
                    switch ($_GET['error']) {
                        case 'missing_fields':
                            echo 'Please fill in all required fields.';
                            break;
                        case 'delete_failed':
                            echo 'Failed to delete qualification. Please check the error logs.';
                            break;
                        case 'invalid_params':
                            echo 'Invalid parameters provided.';
                            break;
                        case 'already_assigned':
                            echo 'This instructor is already assigned to this qualification.';
                            break;
                        case 'assign_failed':
                            echo 'Failed to assign instructor. Please try again.';
                            break;
                        case 'create_failed':
                            echo 'Failed to create module. Please check required fields and try again.';
                            break;
                        default:
                            echo 'An error occurred. Please try again.';
                    }
                    ?>
                        </p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['schedule_warnings'])): ?>
                <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 px-4 py-3 rounded-lg mb-4">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    <strong>Schedule Warning:</strong> <?php echo esc_html(urldecode($_GET['schedule_warnings'])); ?>
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="bg-white shadow-sm rounded-xl p-5 border border-gray-100 flex flex-col justify-between">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500"><?php echo $current_program ? 'Program Qualifications' : 'Qualifications'; ?></p>
                            <p class="mt-2 text-2xl font-semibold text-gray-900">
                                <?php echo number_format_i18n($total_courses_all ?: 0); ?>
                            </p>
                        </div>
                        <div class="w-10 h-10 rounded-lg bg-green-50 flex items-center justify-center">
                            <span class="dashicons dashicons-book text-green-600 text-xl"></span>
                        </div>
                    </div>
                    <p class="mt-3 text-xs text-gray-500">
                        <?php echo $current_program ? 'Qualifications in this program.' : 'Total qualifications available.'; ?>
                    </p>
                </div>

                <div class="bg-white shadow-sm rounded-xl p-5 border border-gray-100 flex flex-col justify-between">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500"><?php echo $current_program ? 'Active in Program' : 'Active Qualifications'; ?></p>
                            <p class="mt-2 text-2xl font-semibold text-gray-900">
                                <?php echo number_format_i18n($active_courses ?: 0); ?>
                            </p>
                        </div>
                        <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center">
                            <span class="dashicons dashicons-yes-alt text-blue-600 text-xl"></span>
                        </div>
                    </div>
                    <p class="mt-3 text-xs text-gray-500">
                        <?php echo $active_courses > 0 && $total_courses_all > 0 ? round(($active_courses / $total_courses_all) * 100) : 0; ?>% of qualifications are active.
                    </p>
                </div>

                <div class="bg-white shadow-sm rounded-xl p-5 border border-gray-100 flex flex-col justify-between">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500"><?php echo $current_program ? 'Program Instructors' : 'Instructors'; ?></p>
                            <p class="mt-2 text-2xl font-semibold text-gray-900">
                                <?php echo number_format_i18n($total_lecturers ?: 0); ?>
                            </p>
                        </div>
                        <div class="w-10 h-10 rounded-lg bg-purple-50 flex items-center justify-center">
                            <span class="dashicons dashicons-businessperson text-purple-600 text-xl"></span>
                        </div>
                    </div>
                    <p class="mt-3 text-xs text-gray-500">
                        Instructors assigned to qualifications.
                    </p>
                </div>

                <div class="bg-white shadow-sm rounded-xl p-5 border border-gray-100 flex flex-col justify-between">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500"><?php echo $current_program ? 'Program Students' : 'Students'; ?></p>
                            <p class="mt-2 text-2xl font-semibold text-gray-900">
                                <?php echo number_format_i18n($total_students ?: 0); ?>
                            </p>
                        </div>
                        <div class="w-10 h-10 rounded-lg bg-orange-50 flex items-center justify-center">
                            <span class="dashicons dashicons-groups text-orange-600 text-xl"></span>
                        </div>
                    </div>
                    <p class="mt-3 text-xs text-gray-500">
                        Students enrolled in qualifications.
                    </p>
                </div>
            </div>

            <!-- Program Filter -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Filter by Program</h3>
                        <p class="text-sm text-gray-500">Select a program to view only its qualifications</p>
                    </div>
                    <div class="flex items-center space-x-3">
                        <form method="GET" action="" class="flex items-center space-x-3">
                            <input type="hidden" name="page" value="nds-courses">
                            <select name="program_id" onchange="this.form.submit()" class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                                <option value="">All Programs</option>
                                <?php foreach ($programs as $program): ?>
                                    <option value="<?php echo esc_attr($program['id']); ?>" <?php echo $filter_program_id == $program['id'] ? 'selected' : ''; ?>>
                                        <?php echo esc_html($program['program_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Main Content Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">

                <!-- Qualifications List - 3/4 width with paginated table -->
                <div class="lg:col-span-3">
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                            <div>
                                <h2 class="text-sm font-semibold text-gray-900">
                                    <?php echo $current_program && isset($current_program['name']) ? esc_html($current_program['name']) . ' Qualifications' : 'All Qualifications'; ?>
                                </h2>
                                <p class="text-xs text-gray-500">Showing <?php echo count($paginated_courses); ?> of <?php echo $total_courses_all; ?> qualifications</p>
                            </div>
                        </div>

                        <div class="p-6" id="qualificationsContainer">
                            <?php if (empty($paginated_courses)): ?>
                                <div class="text-center py-12" id="emptyState">
                                    <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                        <span class="dashicons dashicons-welcome-learn-more text-gray-400 text-3xl"></span>
                                    </div>
                                    <h4 class="text-lg font-semibold text-gray-900 mb-2">No Qualifications Found</h4>
                                    <p class="text-sm text-gray-500 mb-6">Get started by creating your first qualification in this program.</p>
                                    <a href="#addCourseModal" class="inline-flex items-center px-4 py-2 rounded-lg bg-green-600 hover:bg-green-700 text-white text-sm font-medium transition-colors">
                                        <span class="dashicons dashicons-plus-alt2 mr-2 text-base"></span>Create Qualification
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="qualifications-table-container">
                                    <table id="qualificationsTable" class="qualifications-table min-w-full w-full divide-y divide-gray-200 text-sm">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th scope="col" class="px-4 py-3 text-left font-semibold text-gray-700">Qualification</th>
                                                <th scope="col" class="px-4 py-3 text-left font-semibold text-gray-700">Program</th>
                                                <th scope="col" class="px-4 py-3 text-left font-semibold text-gray-700">Instructors</th>
                                                <th scope="col" class="px-4 py-3 text-left font-semibold text-gray-700">Students</th>
                                                <th scope="col" class="px-4 py-3 text-left font-semibold text-gray-700">Duration</th>
                                                <th scope="col" class="px-4 py-3 text-left font-semibold text-gray-700">Status</th>
                                                <th scope="col" class="px-4 py-3 text-right font-semibold text-gray-700">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-100">
                                            <?php foreach ($paginated_courses as $course): ?>
                                                <tr class="hover:bg-gray-50">
                                                    <td class="px-4 py-3 align-middle">
                                                        <div class="font-medium text-gray-900">
                                                            <a href="<?php echo admin_url('admin.php?page=nds-course-overview&course_id=' . $course['id']); ?>" class="hover:text-blue-600 transition-colors">
                                                                <?php echo esc_html($course['name']); ?>
                                                            </a>
                                                        </div>
                                                    </td>
                                                    <td class="px-4 py-3 align-middle text-gray-700">
                                                        <?php echo esc_html($course['program_name'] ?? '—'); ?>
                                                    </td>
                                                    <td class="px-4 py-3 align-middle text-gray-700">
                                                        <?php echo intval($course['lecturer_count']); ?>
                                                    </td>
                                                    <td class="px-4 py-3 align-middle text-gray-700">
                                                        <?php echo intval($course['student_count']); ?>
                                                    </td>
                                                    <td class="px-4 py-3 align-middle text-gray-700">
                                                        <?php echo esc_html(isset($course['duration']) && $course['duration'] ? $course['duration'] . ' weeks' : 'N/A'); ?>
                                                    </td>
                                                    <td class="px-4 py-3 align-middle">
                                                        <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium <?php echo strtolower($course['status']) === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                                            <?php echo ucfirst($course['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-4 py-3 align-middle text-right">
                                                        <div class="inline-flex items-center gap-2">
                                                            <a href="<?php echo admin_url('admin.php?page=nds-edit-course&edit_course=' . $course['id']); ?>"
                                                               class="inline-flex items-center px-3 py-1.5 rounded-lg bg-gray-600 hover:bg-gray-700 text-white text-xs font-medium">
                                                                <span class="dashicons dashicons-edit mr-1 text-sm"></span>
                                                                Edit
                                                            </a>
                                                            <button type="button"
                                                                onclick="openAddModuleModal(<?php echo $course['id']; ?>, '<?php echo esc_js($course['name']); ?>')"
                                                                class="inline-flex items-center px-3 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium">
                                                                <span class="dashicons dashicons-plus-alt mr-1 text-sm"></span>
                                                                Module
                                                            </button>
                                                            <button type="button"
                                                                onclick="confirmDelete(<?php echo $course['id']; ?>, '<?php echo esc_js($course['name']); ?>')"
                                                                class="inline-flex items-center px-2 py-1.5 rounded-lg bg-red-50 hover:bg-red-100 text-red-700 text-xs font-medium">
                                                                <span class="dashicons dashicons-trash text-sm"></span>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <!-- Pagination -->
                                <?php if ($total_pages > 1): ?>
                                <div class="nds-pagination">
                                    <?php
                                    $pagination_args = array(
                                        'base' => add_query_arg('paged', '%#%'),
                                        'format' => '',
                                        'current' => $current_page,
                                        'total' => $total_pages,
                                        'prev_text' => '&laquo; Prev',
                                        'next_text' => 'Next &raquo;',
                                        'type' => 'array'
                                    );
                                    
                                    if ($filter_program_id) {
                                        $pagination_args['add_args'] = array('program_id' => $filter_program_id);
                                    }
                                    
                                    $pagination_links = paginate_links($pagination_args);
                                    
                                    if (is_array($pagination_links)) {
                                        foreach ($pagination_links as $link) {
                                            echo str_replace('page-numbers', 'page-numbers', $link);
                                        }
                                    }
                                    ?>
                                </div>
                                <?php endif; ?>
                                
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Sidebar - 1/4 width with SINGLE SCROLL BAR for main sidebar -->
                <div class="lg:col-span-1">
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden sidebar-card">
                        <div class="px-5 py-4 border-b border-gray-100">
                            <h2 class="text-sm font-semibold text-gray-900">Quick Access</h2>
                            <p class="text-xs text-gray-500">Recent qualifications & tools</p>
                        </div>
                        
                        <!-- SINGLE SCROLLABLE CONTENT AREA FOR ENTIRE SIDEBAR -->
                        <div class="sidebar-scrollable-content">
                            
                            <!-- Recent Qualifications - with its own scrollable container -->
                            <div class="nds-sidebar-section">
                                <h3 class="text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2 flex items-center">
                                    <span class="dashicons dashicons-clock mr-1 text-gray-500 text-sm"></span>
                                    Recent Qualifications
                                </h3>
                                <p class="text-xs text-gray-500 mb-3">Latest 5 qualifications</p>
                                
                                <div class="section-scrollable recent-scrollable">
                                    <?php if (empty($recent_courses)): ?>
                                        <p class="text-gray-500 text-sm py-2">No recent qualifications</p>
                                    <?php else: ?>
                                        <div class="space-y-3">
                                            <?php foreach ($recent_courses as $course): ?>
                                                <div class="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                                                    <div class="w-8 h-8 bg-emerald-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                                        <span class="dashicons dashicons-welcome-learn-more text-emerald-600 text-base"></span>
                                                    </div>
                                                    <div class="flex-1 min-w-0">
                                                        <h5 class="text-sm font-medium text-gray-900 truncate"><?php echo esc_html($course['name']); ?></h5>
                                                        <p class="text-xs text-gray-500 truncate"><?php echo esc_html($course['program_name'] ?? 'No Program'); ?></p>
                                                        <p class="text-xs text-gray-400"><?php echo date('M j, Y', strtotime($course['created_at'] ?? 'now')); ?></p>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <hr class="my-4 border-gray-200">
                            
                            <!-- Assign Chef Instructor - with its own scrollable container -->
                            <div class="nds-sidebar-section">
                                <h3 class="text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2 flex items-center">
                                    <span class="dashicons dashicons-businessperson mr-1 text-gray-500 text-sm"></span>
                                    Assign Chef Instructor
                                </h3>
                                <p class="text-xs text-gray-500 mb-3">Assign instructors to qualifications</p>
                                
                                <div class="section-scrollable assign-scrollable">
                                    <form id="assignInstructorForm" method="POST" action="<?php echo admin_url('admin-post.php'); ?>">
                                        <?php wp_nonce_field('nds_assign_lecturer', '_wpnonce'); ?>
                                        <input type="hidden" name="action" value="nds_assign_lecturer">
                                        <input type="hidden" name="redirect_to" value="<?php echo admin_url('admin.php?page=nds-courses'); ?>">
                                        <?php if ($filter_program_id): ?>
                                            <input type="hidden" name="program_id" value="<?php echo esc_attr($filter_program_id); ?>">
                                        <?php endif; ?>
                                        
                                        <div class="space-y-4 pr-1">
                                            <div>
                                                <label for="assign_course_id" class="block text-sm font-medium text-gray-700 mb-1">
                                                    Select Qualification <span class="text-red-500">*</span>
                                                </label>
                                                <select id="assign_course_id" name="course_id" required
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                                                    <option value="">Choose a qualification</option>
                                                    <?php foreach ($all_courses_query as $course): ?>
                                                        <option value="<?php echo intval($course['id']); ?>">
                                                            <?php echo esc_html($course['name']); ?> (<?php echo esc_html($course['program_name'] ?? 'No Program'); ?>)
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div>
                                                <label for="assign_lecturer_id" class="block text-sm font-medium text-gray-700 mb-1">
                                                    Select Chef Instructor <span class="text-red-500">*</span>
                                                </label>
                                                <select id="assign_lecturer_id" name="lecturer_id" required
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                                                    <option value="">Choose an instructor</option>
                                                    <?php if (!empty($staff)): ?>
                                                        <?php foreach ($staff as $instructor): ?>
                                                            <option value="<?php echo intval($instructor['id']); ?>">
                                                                <?php echo esc_html($instructor['first_name'] . ' ' . $instructor['last_name']); ?> (<?php echo esc_html($instructor['role']); ?>)
                                                            </option>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <option value="" disabled>No instructors available</option>
                                                    <?php endif; ?>
                                                </select>
                                            </div>
                                            
                                            <div class="pt-2">
                                                <button type="submit"
                                                    class="w-full inline-flex items-center justify-center px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors text-sm font-medium">
                                                    <i class="fas fa-user-plus mr-2"></i>
                                                    Assign Instructor
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            
                            <hr class="my-4 border-gray-200">
                            
                            <!-- Quick Actions - with its own scrollable container -->
                            <div class="nds-sidebar-section">
                                <h3 class="text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2 flex items-center">
                                    <span class="dashicons dashicons-admin-tools mr-1 text-gray-500 text-sm"></span>
                                    Quick Actions
                                </h3>
                                <p class="text-xs text-gray-500 mb-3">Navigate to related sections</p>
                                
                                <div class="section-scrollable actions-scrollable">
                                    <div class="space-y-3 pr-1">
                                        <a href="<?php echo admin_url('admin.php?page=nds-programs'); ?>"
                                            class="w-full inline-flex items-center justify-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm font-medium">
                                            <span class="dashicons dashicons-book-alt mr-2 text-base"></span> Program Management
                                        </a>
                                        <button type="button" onclick="exportQualifications()"
                                            class="w-full inline-flex items-center justify-center px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors text-sm font-medium">
                                            <span class="dashicons dashicons-download mr-2 text-base"></span>Export Data
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Quick Program Access - with its own scrollable container -->
                            <?php if (!empty($programs)): ?>
                                <hr class="my-4 border-gray-200">
                                
                                <div class="nds-sidebar-section">
                                    <h3 class="text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2 flex items-center">
                                        <span class="dashicons dashicons-filter mr-1 text-gray-500 text-sm"></span>
                                        Quick Program Access
                                    </h3>
                                    <p class="text-xs text-gray-500 mb-3">Jump to specific programs</p>
                                    
                                    <div class="section-scrollable programs-scrollable">
                                        <div class="space-y-2 pr-1">
                                            <?php foreach ($programs as $program): ?>
                                                <a href="?page=nds-courses&program_id=<?php echo $program['id']; ?>"
                                                   class="block px-3 py-2 rounded-lg hover:bg-gray-50 transition-colors text-sm text-gray-700 hover:text-gray-900">
                                                    <div class="flex items-center justify-between">
                                                        <span class="truncate"><?php echo esc_html($program['program_name']); ?></span>
                                                        <i class="fas fa-chevron-right text-gray-400 text-xs flex-shrink-0 ml-2"></i>
                                                    </div>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                        </div> <!-- End Single Scrollable Content -->
                        
                    </div> <!-- End Sidebar Card -->
                </div> <!-- End Sidebar Column -->
            </div> <!-- End Main Content Grid -->
        </div> <!-- End Container -->

        <!-- Add Qualification Modal -->
        <div id="addCourseModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999;" onclick="if(event.target === this) closeModal();">
            <div class="flex items-center justify-center min-h-screen p-4">
                <div class="bg-white rounded-xl shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto" onclick="event.stopPropagation();">
                    <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                        <div class="flex items-center">
                            <span class="dashicons dashicons-plus-alt2 text-blue-600 mr-3 text-xl"></span>
                            <h2 class="text-xl font-semibold text-gray-900">Add New Qualification</h2>
                        </div>
                        <button type="button" onclick="closeModal()" class="text-gray-400 hover:text-gray-600 transition-colors p-2 rounded-lg hover:bg-gray-100">
                            <span class="dashicons dashicons-no-alt text-xl"></span>
                        </button>
                    </div>
                    <div class="p-6">
                        <form method="POST" action="javascript:void(0);" onsubmit="event.preventDefault(); submitCourseForm(this);">
                            <?php wp_nonce_field('nds_course_nonce', 'nds_course_nonce'); ?>
                            <input type="hidden" name="action" value="nds_create_course_ajax">

                            <div class="space-y-6">
                                <div>
                                    <label for="course_name" class="block text-sm font-semibold text-gray-900 mb-2">
                                        Qualification Name <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" id="course_name" name="name"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                        placeholder="e.g., French Cuisine Fundamentals" required>
                                </div>

                                <div>
                                    <label for="program_id" class="block text-sm font-semibold text-gray-900 mb-2">
                                        Program <span class="text-red-500">*</span>
                                    </label>
                                    <select id="program_id" name="program_id" data-auto-select="program_id"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent" required>
                                        <option value="">Select Program</option>
                                        <?php foreach ($programs as $program): ?>
                                            <?php $selected = ($selected_program_id && $program['id'] == $selected_program_id) ? ' selected' : ''; ?>
                                            <option value="<?php echo $program['id']; ?>" <?php echo $selected; ?>><?php echo esc_html($program['program_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label for="course_code" class="block text-sm font-semibold text-gray-900 mb-2">
                                            Qualification Code
                                        </label>
                                        <input type="text" id="course_code" name="code"
                                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                            placeholder="e.g., CUL101">
                                    </div>

                                    <div>
                                        <label for="duration" class="block text-sm font-semibold text-gray-900 mb-2">
                                            Duration (weeks)
                                        </label>
                                        <input type="number" id="duration" name="duration"
                                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                            placeholder="12" min="1">
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label for="start_date" class="block text-sm font-semibold text-gray-900 mb-2">
                                            Start Date
                                        </label>
                                        <input type="date" id="start_date" name="start_date"
                                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                    </div>

                                    <div>
                                        <label for="end_date" class="block text-sm font-semibold text-gray-900 mb-2">
                                            End Date
                                        </label>
                                        <input type="date" id="end_date" name="end_date"
                                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                    </div>
                                </div>

                                <div>
                                    <label for="description" class="block text-sm font-semibold text-gray-900 mb-2">
                                        Description
                                    </label>
                                    <textarea id="description" name="description" rows="4"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                        placeholder="Describe the skills, techniques, and knowledge students will learn..."></textarea>
                                </div>

                                <!-- Schedule / Timetable (reusable component) -->
                                <?php
                                $schedule_fields_path = plugin_dir_path(__FILE__) . 'partials/schedule-fields.php';
                                if (file_exists($schedule_fields_path)) {
                                    require_once $schedule_fields_path;
                                    if (function_exists('nds_render_schedule_fields')) {
                                        nds_render_schedule_fields(array(
                                            'lecturers' => $staff ?? array(),
                                            'prefix' => 'schedule'
                                        ));
                                    }
                                }
                                ?>

                                <div>
                                    <label for="status" class="block text-sm font-semibold text-gray-900 mb-2">
                                        Status
                                    </label>
                                    <select id="status" name="status"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                        <option value="draft">Draft</option>
                                    </select>
                                </div>
                            </div>

                            <div class="flex items-center justify-end space-x-3 mt-8 pt-6 border-t border-gray-200">
                                <button type="button" onclick="closeModal()"
                                    class="px-6 py-3 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                                    Cancel
                                </button>
                                <button type="submit"
                                    class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-all duration-200 font-medium shadow-md hover:shadow-lg">
                                    Create Qualification
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Module Modal -->
        <div id="addModuleModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000;" onclick="if(event.target === this) closeModuleModal();">
            <div class="flex items-center justify-center min-h-screen p-4">
                <div class="bg-white rounded-xl shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto" onclick="event.stopPropagation();">
                    <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                        <div class="flex items-center">
                            <span class="dashicons dashicons-welcome-learn-more text-blue-600 mr-3 text-xl"></span>
                            <h2 class="text-xl font-semibold text-gray-900">Add New Module</h2>
                        </div>
                        <button type="button" onclick="closeModuleModal()" class="text-gray-400 hover:text-gray-600 transition-colors p-2 rounded-lg hover:bg-gray-100">
                            <span class="dashicons dashicons-no-alt text-xl"></span>
                        </button>
                    </div>
                    <div class="p-6">
                        <form method="POST" action="<?php echo admin_url('admin-post.php'); ?>">
                            <?php wp_nonce_field('nds_add_module_nonce', 'nds_add_module_nonce'); ?>
                            <input type="hidden" name="action" value="nds_add_module">
                            <input type="hidden" name="course_id" id="module_course_id" value="">

                            <div class="space-y-6">
                                <div>
                                    <label for="module_name" class="block text-sm font-semibold text-gray-900 mb-2">
                                        Module Name <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" id="module_name" name="module_name"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                        placeholder="e.g., Knife Skills, Sauce Making, etc." required>
                                </div>

                                <div>
                                    <label for="module_code" class="block text-sm font-semibold text-gray-900 mb-2">
                                        Module Code
                                    </label>
                                    <input type="text" id="module_code" name="module_code"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                        placeholder="e.g., MOD101">
                                </div>

                                <div>
                                    <label for="module_duration" class="block text-sm font-semibold text-gray-900 mb-2">
                                        Duration (hours) <span class="text-red-500">*</span>
                                    </label>
                                    <input type="number" id="module_duration" name="module_duration"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                        placeholder="e.g., 40" min="1" required>
                                </div>

                                <div>
                                    <label for="module_description" class="block text-sm font-semibold text-gray-900 mb-2">
                                        Description
                                    </label>
                                    <textarea id="module_description" name="module_description" rows="4"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                        placeholder="Describe what this module covers..."></textarea>
                                </div>

                                <div>
                                    <label for="module_status" class="block text-sm font-semibold text-gray-900 mb-2">
                                        Status
                                    </label>
                                    <select id="module_status" name="module_status"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                        <option value="draft">Draft</option>
                                    </select>
                                </div>

                                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                    <p class="text-sm text-blue-700">
                                        <span class="dashicons dashicons-info-outline text-blue-600 mr-1"></span>
                                        This module will be added to: <strong id="selected_course_name"></strong>
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-center justify-end space-x-3 mt-8 pt-6 border-t border-gray-200">
                                <button type="button" onclick="closeModuleModal()"
                                    class="px-6 py-3 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                                    Cancel
                                </button>
                                <button type="submit"
                                    class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-all duration-200 font-medium shadow-md hover:shadow-lg">
                                    Add Module
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Hidden Delete Form -->
        <form id="deleteCourseForm" method="POST" action="<?php echo admin_url('admin-post.php'); ?>" style="display: none;">
            <input type="hidden" name="action" value="nds_delete_course">
            <input type="hidden" name="course_id" id="delete_course_id">
            <?php if (isset($filter_program_id) && $filter_program_id > 0): ?>
                <input type="hidden" name="program_id" value="<?php echo esc_attr($filter_program_id); ?>">
            <?php endif; ?>
            <?php wp_nonce_field('nds_delete_course_nonce', 'nds_delete_course_nonce'); ?>
        </form>

        <!-- Include Auto-Select Helper -->
        <?php if (file_exists(plugin_dir_path(__FILE__) . 'modal-auto-select.js')): ?>
            <script src="<?php echo plugin_dir_url(__FILE__); ?>modal-auto-select.js"></script>
        <?php endif; ?>

        <script>
            function closeModal() {
                const modal = document.getElementById('addCourseModal');
                if (modal) {
                    modal.style.display = 'none';
                    document.body.style.overflow = '';
                }
            }

            function closeModuleModal() {
                const modal = document.getElementById('addModuleModal');
                if (modal) {
                    modal.style.display = 'none';
                    document.body.style.overflow = '';
                }
            }

            function openAddModuleModal(courseId, courseName) {
                document.getElementById('module_course_id').value = courseId;
                document.getElementById('selected_course_name').textContent = courseName;
                
                const modal = document.getElementById('addModuleModal');
                if (modal) {
                    modal.style.display = 'block';
                    document.body.style.overflow = 'hidden';
                }
            }

            function confirmDelete(courseId, courseName) {
                if (confirm(`Are you sure you want to delete "${courseName}"? This will also remove all instructor assignments, modules, and student enrollments.`)) {
                    document.getElementById('delete_course_id').value = courseId;
                    document.getElementById('deleteCourseForm').submit();
                }
            }

            function exportQualifications() {
                alert('Export functionality will be implemented soon.');
            }

            function submitCourseForm(form) {
                const formData = new FormData(form);
                const submitBtn = form.querySelector('button[type="submit"]');
                const originalText = submitBtn ? submitBtn.textContent.trim() : 'Create Qualification';

                formData.set('action', 'nds_create_course_ajax');
                if (!formData.get('nonce') && formData.get('nds_course_nonce')) {
                    formData.set('nonce', formData.get('nds_course_nonce'));
                }

                if (submitBtn) {
                    submitBtn.textContent = 'Creating...';
                    submitBtn.disabled = true;
                }

                const ajaxUrl = (typeof ajaxurl !== 'undefined') ? ajaxurl : '<?php echo admin_url('admin-ajax.php'); ?>';

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
                            return JSON.parse(candidate);
                        }
                        throw new Error('Invalid JSON response: ' + trimmed.substring(0, 300));
                    }
                })
                .then(data => {
                    if (data.success) {
                        closeModal();
                        alert('Qualification created successfully!');

                        const selectedProgram = formData.get('program_id');
                        let redirectUrl = '<?php echo admin_url('admin.php?page=nds-courses'); ?>';
                        if (selectedProgram) {
                            redirectUrl += '&program_id=' + encodeURIComponent(selectedProgram);
                        }
                        window.location.href = redirectUrl;
                        return;
                    }

                    const errorMsg = data && data.data && data.data.message
                        ? data.data.message
                        : 'Error creating qualification';
                    alert('Error: ' + errorMsg);

                    if (submitBtn) {
                        submitBtn.textContent = originalText;
                        submitBtn.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred. Please try again. ' + (error.message || ''));
                    if (submitBtn) {
                        submitBtn.textContent = originalText;
                        submitBtn.disabled = false;
                    }
                });
            }

            // Modal trigger for Add Qualification
            document.addEventListener('DOMContentLoaded', function() {
                // Handle links with href="#addCourseModal"
                const modalLinks = document.querySelectorAll('a[href="#addCourseModal"]');
                modalLinks.forEach(link => {
                    link.addEventListener('click', function(e) {
                        e.preventDefault();
                        const modal = document.getElementById('addCourseModal');
                        if (modal) {
                            modal.style.display = 'block';
                            document.body.style.overflow = 'hidden';
                        }
                    });
                });

                // Close modal when clicking outside
                const courseModal = document.getElementById('addCourseModal');
                if (courseModal) {
                    courseModal.addEventListener('click', function(e) {
                        if (e.target === this) {
                            closeModal();
                        }
                    });
                }

                const moduleModal = document.getElementById('addModuleModal');
                if (moduleModal) {
                    moduleModal.addEventListener('click', function(e) {
                        if (e.target === this) {
                            closeModuleModal();
                        }
                    });
                }

                // Handle instructor assignment form submission
                const assignForm = document.getElementById('assignInstructorForm');
                if (assignForm) {
                    assignForm.addEventListener('submit', function(e) {
                        const courseSelect = document.getElementById('assign_course_id');
                        const instructorSelect = document.getElementById('assign_lecturer_id');
                        
                        if (!courseSelect.value || !instructorSelect.value) {
                            e.preventDefault();
                            alert('Please select both a qualification and an instructor.');
                            return false;
                        }
                        
                        // Show loading state
                        const submitBtn = assignForm.querySelector('button[type="submit"]');
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Assigning...';
                        submitBtn.disabled = true;
                    });
                }
            });
        </script>
    </div>
<?php
}

// NOTE: nds_assign_lecturer_to_course() and nds_unassign_lecturer_from_course() 
// are already defined in staff-functions.php - we're not redeclaring them here
?>