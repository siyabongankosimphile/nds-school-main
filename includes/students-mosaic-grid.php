<?php
// Prevent direct access - this file should only be included by WordPress
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Ensure WordPress functions are available
if (!function_exists('current_user_can')) {
    return;
}

// Students Mosaic Grid Layout
function nds_students_mosaic_grid_page() {
    if (!current_user_can('manage_options')) { wp_die('Unauthorized'); }
    global $wpdb;

    $table_students = $wpdb->prefix . 'nds_students';
    $table_enrollments = $wpdb->prefix . 'nds_student_enrollments';
    $table_courses = $wpdb->prefix . 'nds_courses';

    // Get filter parameters
    $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
    $search_query = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
    $intake_year = isset($_GET['intake_year']) ? intval($_GET['intake_year']) : 0;

    // Build query with filters
    $where_conditions = [];
    $where_params = [];

    if (!empty($status_filter)) {
        $where_conditions[] = "status = %s";
        $where_params[] = $status_filter;
    }

    if (!empty($search_query)) {
        $where_conditions[] = "(first_name LIKE %s OR last_name LIKE %s OR email LIKE %s)";
        $search_param = '%' . $wpdb->esc_like($search_query) . '%';
        $where_params[] = $search_param;
        $where_params[] = $search_param;
        $where_params[] = $search_param;
    }

    if ($intake_year > 0) {
        $where_conditions[] = "intake_year = %d";
        $where_params[] = $intake_year;
    }

    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Get students with enrollment count
    $students_query = "
        SELECT s.*, 
               COUNT(se.id) as enrollment_count,
               GROUP_CONCAT(DISTINCT c.name) as enrolled_courses
        FROM {$table_students} s
        LEFT JOIN {$table_enrollments} se ON s.id = se.student_id
        LEFT JOIN {$table_courses} c ON se.course_id = c.id
        {$where_clause}
        GROUP BY s.id
        ORDER BY s.created_at DESC
    ";

    if (!empty($where_params)) {
        $students = $wpdb->get_results($wpdb->prepare($students_query, $where_params), ARRAY_A);
    } else {
        $students = $wpdb->get_results($students_query, ARRAY_A);
    }

    // Get statistics
    $total_students = count($students);
    $active_students = count(array_filter($students, function($s) { return $s['status'] === 'active'; }));
    $prospect_students = count(array_filter($students, function($s) { return $s['status'] === 'prospect'; }));
    $graduated_students = count(array_filter($students, function($s) { return $s['status'] === 'graduated'; }));

    // Get unique intake years for filter
    $intake_years = $wpdb->get_col("SELECT DISTINCT intake_year FROM {$table_students} ORDER BY intake_year DESC");

    // Force-load Tailwind CSS
    $plugin_dir = plugin_dir_path(dirname(__FILE__));
    $css_file   = $plugin_dir . 'assets/css/frontend.css';
    if (file_exists($css_file)) {
        wp_enqueue_style(
            'nds-tailwindcss-students-grid',
            plugin_dir_url(dirname(__FILE__)) . 'assets/css/frontend.css',
            array(),
            filemtime($css_file),
            'all'
        );
        wp_add_inline_style('nds-tailwindcss-students-grid', '
            .nds-tailwind-wrapper { all: initial !important; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important; }
            .nds-tailwind-wrapper * { box-sizing: border-box !important; }
        ');
    }
    wp_enqueue_style('nds-icons', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css', array(), null, 'all');

    ?>
    <div class="wrap">
        <div class="nds-tailwind-wrapper bg-gray-50 pb-12 p-8" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin-left: -20px; padding-left: 20px; margin-top: -20px;">

            <!-- Breadcrumb Navigation -->
            <div class="max-w-7xl mx-auto mb-6">
                <nav class="flex items-center space-x-2 text-sm text-gray-600">
                    <a href="<?php echo admin_url('admin.php?page=nds-academy'); ?>" class="hover:text-blue-600 transition-colors">
                        <i class="fas fa-home mr-1"></i>NDS Academy
                    </a>
                    <i class="fas fa-chevron-right text-gray-400"></i>
                    <a href="<?php echo admin_url('admin.php?page=nds-learner-management'); ?>" class="hover:text-blue-600 transition-colors">
                        Learner Management
                    </a>
                    <i class="fas fa-chevron-right text-gray-400"></i>
                    <span class="text-gray-900 font-medium">Students Mosaic Grid</span>
                </nav>
            </div>

            <!-- Header Section -->
            <div class="max-w-7xl mx-auto">
                <div class="flex justify-between items-center mb-8">
                    <div>
                        <h1 class="text-4xl font-bold text-gray-900 mb-2">
                            <i class="fas fa-users text-blue-600 mr-4"></i>Students Mosaic Grid
                        </h1>
                        <p class="text-gray-600 text-lg">Beautiful mosaic layout showcasing all students</p>
                    </div>
                    <div class="flex gap-3">
                        <a href="<?php echo admin_url('admin.php?page=nds-all-learners'); ?>" 
                           class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-3 px-6 rounded-lg flex items-center gap-2 transition-colors duration-200">
                            <i class="fas fa-list mr-2"></i>
                            Table View
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=nds-add-learner'); ?>" 
                           class="bg-green-600 hover:bg-green-700 text-white font-semibold py-3 px-6 rounded-lg flex items-center gap-2 transition-colors duration-200 shadow-md hover:shadow-lg">
                            <i class="fas fa-plus mr-2"></i>
                            Add Student
                        </a>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 hover:shadow-md transition-shadow duration-200">
                        <div class="flex items-center">
                            <div class="w-12 h-12 bg-blue-500 rounded-lg flex items-center justify-center mr-4">
                                <i class="fas fa-users text-white text-xl"></i>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-600 mb-1">Total Students</p>
                                <p class="text-3xl font-bold text-gray-900"><?php echo number_format($total_students); ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 hover:shadow-md transition-shadow duration-200">
                        <div class="flex items-center">
                            <div class="w-12 h-12 bg-green-500 rounded-lg flex items-center justify-center mr-4">
                                <i class="fas fa-user-check text-white text-xl"></i>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-600 mb-1">Active Students</p>
                                <p class="text-3xl font-bold text-gray-900"><?php echo number_format($active_students); ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 hover:shadow-md transition-shadow duration-200">
                        <div class="flex items-center">
                            <div class="w-12 h-12 bg-yellow-500 rounded-lg flex items-center justify-center mr-4">
                                <i class="fas fa-user-clock text-white text-xl"></i>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-600 mb-1">Prospects</p>
                                <p class="text-3xl font-bold text-gray-900"><?php echo number_format($prospect_students); ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 hover:shadow-md transition-shadow duration-200">
                        <div class="flex items-center">
                            <div class="w-12 h-12 bg-purple-500 rounded-lg flex items-center justify-center mr-4">
                                <i class="fas fa-graduation-cap text-white text-xl"></i>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-600 mb-1">Graduated</p>
                                <p class="text-3xl font-bold text-gray-900"><?php echo number_format($graduated_students); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-8">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Filters</h3>
                    <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <input type="hidden" name="page" value="nds-students-mosaic-grid">
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                            <input type="text" name="search" value="<?php echo esc_attr($search_query); ?>" 
                                   placeholder="Search by name or email..."
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                            <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="">All Status</option>
                                <option value="active" <?php selected($status_filter, 'active'); ?>>Active</option>
                                <option value="prospect" <?php selected($status_filter, 'prospect'); ?>>Prospect</option>
                                <option value="graduated" <?php selected($status_filter, 'graduated'); ?>>Graduated</option>
                                <option value="inactive" <?php selected($status_filter, 'inactive'); ?>>Inactive</option>
                                <option value="withdrawn" <?php selected($status_filter, 'withdrawn'); ?>>Withdrawn</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Intake Year</label>
                            <select name="intake_year" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="">All Years</option>
                                <?php foreach ($intake_years as $year): ?>
                                    <option value="<?php echo $year; ?>" <?php selected($intake_year, $year); ?>><?php echo $year; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="flex items-end">
                            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition-colors">
                                <i class="fas fa-search mr-2"></i>Filter
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Mosaic Grid -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-xl font-semibold text-gray-900">Students Mosaic Grid</h3>
                        <p class="text-sm text-gray-600"><?php echo $total_students; ?> students found</p>
                    </div>

                    <?php if (empty($students)): ?>
                        <div class="text-center py-12">
                            <i class="fas fa-users text-4xl text-gray-300 mb-4"></i>
                            <h4 class="text-lg font-medium text-gray-900 mb-2">No Students Found</h4>
                            <p class="text-gray-500 mb-4">No students match your current filters.</p>
                            <a href="<?php echo admin_url('admin.php?page=nds-students-mosaic-grid'); ?>" 
                               class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition-colors">
                                Clear Filters
                            </a>
                        </div>
                    <?php else: ?>
                        <!-- Mosaic Grid Layout -->
                        <div class="mosaic-grid">
                            <?php foreach ($students as $index => $student): ?>
                                <?php 
                                // Determine card size based on index for mosaic effect
                                $card_classes = [
                                    'mosaic-card',
                                    'bg-white rounded-xl shadow-sm border border-gray-200 p-6 hover:shadow-lg transition-all duration-300 cursor-pointer group'
                                ];
                                
                                // Alternate between different sizes
                                if ($index % 12 === 0 || $index % 12 === 7) {
                                    $card_classes[] = 'col-span-2 row-span-2'; // Large cards
                                } elseif ($index % 12 === 3 || $index % 12 === 8 || $index % 12 === 11) {
                                    $card_classes[] = 'col-span-1 row-span-2'; // Tall cards
                                } elseif ($index % 12 === 1 || $index % 12 === 6 || $index % 12 === 9) {
                                    $card_classes[] = 'col-span-2 row-span-1'; // Wide cards
                                } else {
                                    $card_classes[] = 'col-span-1 row-span-1'; // Regular cards
                                }
                                
                                // Status-based color accents
                                $status_colors = [
                                    'active' => 'border-l-4 border-l-green-500',
                                    'prospect' => 'border-l-4 border-l-yellow-500',
                                    'graduated' => 'border-l-4 border-l-purple-500',
                                    'inactive' => 'border-l-4 border-l-red-500',
                                    'withdrawn' => 'border-l-4 border-l-gray-500'
                                ];
                                $card_classes[] = $status_colors[$student['status']] ?? 'border-l-4 border-l-gray-300';
                                
                                $card_class = implode(' ', $card_classes);
                                ?>
                                
                                <div class="<?php echo $card_class; ?>" onclick="viewStudentDetails(<?php echo $student['id']; ?>)">
                                    <!-- Student Avatar -->
                                    <div class="flex items-center mb-4">
                                        <div class="w-16 h-16 bg-gradient-to-br from-blue-400 to-purple-500 rounded-full flex items-center justify-center text-white text-xl font-bold mr-4">
                                            <?php echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?>
                                        </div>
                                        <div class="flex-1">
                                            <h4 class="text-lg font-semibold text-gray-900 group-hover:text-blue-600 transition-colors">
                                                <?php echo esc_html($student['first_name'] . ' ' . $student['last_name']); ?>
                                            </h4>
                                            <p class="text-sm text-gray-600"><?php echo esc_html($student['email']); ?></p>
                                        </div>
                                    </div>

                                    <!-- Student Details -->
                                    <div class="space-y-3">
                                        <div class="flex items-center justify-between">
                                            <span class="text-sm text-gray-600">Student ID:</span>
                                            <span class="text-sm font-medium text-gray-900"><?php echo esc_html($student['student_number']); ?></span>
                                        </div>
                                        
                                        <div class="flex items-center justify-between">
                                            <span class="text-sm text-gray-600">Status:</span>
                                            <span class="px-2 py-1 rounded-full text-xs font-medium
                                                <?php 
                                                $status_classes = [
                                                    'active' => 'bg-green-100 text-green-800',
                                                    'prospect' => 'bg-yellow-100 text-yellow-800',
                                                    'graduated' => 'bg-purple-100 text-purple-800',
                                                    'inactive' => 'bg-red-100 text-red-800',
                                                    'withdrawn' => 'bg-gray-100 text-gray-800'
                                                ];
                                                echo $status_classes[$student['status']] ?? 'bg-gray-100 text-gray-800';
                                                ?>">
                                                <?php echo ucfirst($student['status']); ?>
                                            </span>
                                        </div>

                                        <div class="flex items-center justify-between">
                                            <span class="text-sm text-gray-600">Intake Year:</span>
                                            <span class="text-sm font-medium text-gray-900"><?php echo esc_html($student['intake_year']); ?></span>
                                        </div>

                                        <?php if ($student['enrollment_count'] > 0): ?>
                                            <div class="flex items-center justify-between">
                                                <span class="text-sm text-gray-600">Enrollments:</span>
                                                <span class="text-sm font-medium text-gray-900"><?php echo $student['enrollment_count']; ?></span>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($student['phone'])): ?>
                                            <div class="flex items-center justify-between">
                                                <span class="text-sm text-gray-600">Phone:</span>
                                                <span class="text-sm font-medium text-gray-900"><?php echo esc_html($student['phone']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Quick Actions -->
                                    <div class="mt-4 pt-4 border-t border-gray-200 flex space-x-2">
                                        <button onclick="event.stopPropagation(); editStudent(<?php echo $student['id']; ?>)" 
                                                class="flex-1 bg-blue-50 hover:bg-blue-100 text-blue-600 text-xs font-medium py-2 px-3 rounded-lg transition-colors">
                                            <i class="fas fa-edit mr-1"></i>Edit
                                        </button>
                                        <button onclick="event.stopPropagation(); viewStudentDetails(<?php echo $student['id']; ?>)" 
                                                class="flex-1 bg-green-50 hover:bg-green-100 text-green-600 text-xs font-medium py-2 px-3 rounded-lg transition-colors">
                                            <i class="fas fa-eye mr-1"></i>View
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <style>
    .mosaic-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        grid-auto-rows: 120px;
        gap: 1.5rem;
        grid-auto-flow: dense;
    }

    .mosaic-card {
        min-height: 120px;
    }

    .mosaic-card.col-span-2 {
        grid-column: span 2;
    }

    .mosaic-card.row-span-2 {
        grid-row: span 2;
    }

    .mosaic-card.col-span-1 {
        grid-column: span 1;
    }

    .mosaic-card.row-span-1 {
        grid-row: span 1;
    }

    @media (max-width: 768px) {
        .mosaic-grid {
            grid-template-columns: 1fr;
        }
        
        .mosaic-card.col-span-2,
        .mosaic-card.row-span-2,
        .mosaic-card.col-span-1,
        .mosaic-card.row-span-1 {
            grid-column: span 1;
            grid-row: span 1;
        }
    }
    </style>

    <script>
    function viewStudentDetails(studentId) {
        window.location.href = '<?php echo admin_url('admin.php?page=nds-edit-learner&edit_student='); ?>' + studentId;
    }

    function editStudent(studentId) {
        window.location.href = '<?php echo admin_url('admin.php?page=nds-edit-learner&edit_student='); ?>' + studentId;
    }
    </script>
    <?php
}
?>
