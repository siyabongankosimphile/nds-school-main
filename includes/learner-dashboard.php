<?php
/**
 * Learner Dashboard - Comprehensive Management Interface
 * Manages everything about a learner: Courses, Finances, Results, Graduation, Certificates, etc.
 */

if (!defined('ABSPATH')) {
    exit;
}

function nds_learner_dashboard_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    $learner_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if (!$learner_id) {
        wp_die('Invalid learner ID');
    }

    global $wpdb;
    $learner = nds_get_student($learner_id);
    
    if (!$learner) {
        wp_die('Learner not found');
    }

    // Ensure we're on the correct page and prevent any content bleeding
    // This dashboard should only show learner-specific content

    // Get current tab
    $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'overview';

    // Get learner data
    $learner_data = (array) $learner;
    $full_name = trim(($learner_data['first_name'] ?? '') . ' ' . ($learner_data['last_name'] ?? ''));
    
    // Get enrollments
    $enrollments = $wpdb->get_results($wpdb->prepare("
        SELECT e.*, c.name as course_name, c.code as course_code, p.name as program_name,
               ay.year_name, s.semester_name
        FROM {$wpdb->prefix}nds_student_enrollments e
        LEFT JOIN {$wpdb->prefix}nds_courses c ON e.course_id = c.id
        LEFT JOIN {$wpdb->prefix}nds_programs p ON c.program_id = p.id
        LEFT JOIN {$wpdb->prefix}nds_academic_years ay ON e.academic_year_id = ay.id
        LEFT JOIN {$wpdb->prefix}nds_semesters s ON e.semester_id = s.id
        WHERE e.student_id = %d
        ORDER BY e.created_at DESC
    ", $learner_id), ARRAY_A);

    // Get faculty info
    $faculty = null;
    if (!empty($learner_data['faculty_id'])) {
        $faculty = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}nds_faculties WHERE id = %d",
            $learner_data['faculty_id']
        ), ARRAY_A);
    }

    // Load Tailwind CSS
    $plugin_dir = plugin_dir_path(__FILE__);
    $css_file = dirname($plugin_dir) . '/assets/css/frontend.css';
    if (file_exists($css_file)) {
        wp_enqueue_style(
            'nds-tailwindcss-learner-dashboard',
            plugin_dir_url(__FILE__) . '../assets/css/frontend.css',
            array(),
            filemtime($css_file),
            'all'
        );
    }
    wp_enqueue_style('nds-icons', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css', array(), null, 'all');
    ?>
    <style>
    /* Ensure dashboard takes full width and hides any conflicting content */
    body.wp-admin #wpcontent {
        padding: 0;
    }
    body.wp-admin #wpbody-content {
        padding: 0;
    }
    /* Remove extra space at bottom */
    body.wp-admin #wpfooter {
        display: none;
    }
    .nds-tailwind-wrapper {
        min-height: auto !important;
        padding-bottom: 0;
    }
    </style>
    <div class="nds-tailwind-wrapper bg-gray-50" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
        <!-- Header -->
        <div class="bg-white shadow-sm border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center py-6">
                    <div class="flex items-center space-x-4">
                        <a href="<?php echo admin_url('admin.php?page=nds-all-learners'); ?>" 
                           class="text-gray-500 hover:text-gray-700 transition-colors">
                            <i class="fas fa-arrow-left text-xl"></i>
                        </a>
                        <div class="w-16 h-16 bg-gradient-to-br from-blue-600 to-purple-600 rounded-xl flex items-center justify-center">
                            <i class="fas fa-user text-white text-3xl"></i>
                        </div>
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900"><?php echo esc_html($full_name ?: 'Unknown Learner'); ?></h1>
                            <p class="text-gray-600 text-sm">
                                <?php if (!empty($learner_data['student_number'])): ?>
                                    Student #<?php echo esc_html($learner_data['student_number']); ?>
                                <?php endif; ?>
                                <?php if ($faculty): ?>
                                    • <?php echo esc_html($faculty['name']); ?>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-3">
                        <a href="<?php echo admin_url('admin.php?page=nds-edit-learner&id=' . $learner_id); ?>"
                           class="inline-flex items-center px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium shadow-sm transition-colors">
                            <i class="fas fa-edit mr-2"></i>
                            Edit Profile
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <div class="bg-white shadow-sm rounded-xl p-4 border border-gray-100">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500">Enrolled Courses</p>
                            <p class="mt-1 text-2xl font-semibold text-gray-900">
                                <?php echo count($enrollments); ?>
                            </p>
                        </div>
                        <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center">
                            <i class="fas fa-book text-blue-600"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white shadow-sm rounded-xl p-4 border border-gray-100">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500">Status</p>
                            <p class="mt-1 text-lg font-semibold">
                                <span class="px-2 py-1 rounded-full text-xs font-medium <?php 
                                    echo ($learner_data['status'] ?? 'prospect') === 'active' ? 'bg-green-100 text-green-800' : 
                                        (($learner_data['status'] ?? 'prospect') === 'prospect' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800');
                                ?>">
                                    <?php echo esc_html(ucfirst($learner_data['status'] ?? 'prospect')); ?>
                                </span>
                            </p>
                        </div>
                        <div class="w-10 h-10 rounded-lg bg-emerald-50 flex items-center justify-center">
                            <i class="fas fa-user-check text-emerald-600"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white shadow-sm rounded-xl p-4 border border-gray-100">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500">Average Grade</p>
                            <p class="mt-1 text-2xl font-semibold text-gray-900">
                                <?php
                                $avg_grade = $wpdb->get_var($wpdb->prepare(
                                    "SELECT AVG(final_percentage) FROM {$wpdb->prefix}nds_student_enrollments 
                                     WHERE student_id = %d AND final_percentage IS NOT NULL",
                                    $learner_id
                                ));
                                echo $avg_grade ? number_format($avg_grade, 1) . '%' : 'N/A';
                                ?>
                            </p>
                        </div>
                        <div class="w-10 h-10 rounded-lg bg-purple-50 flex items-center justify-center">
                            <i class="fas fa-chart-line text-purple-600"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white shadow-sm rounded-xl p-4 border border-gray-100">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500">Certificates</p>
                            <p class="mt-1 text-2xl font-semibold text-gray-900">0</p>
                        </div>
                        <div class="w-10 h-10 rounded-lg bg-amber-50 flex items-center justify-center">
                            <i class="fas fa-certificate text-amber-600"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabs Navigation -->
            <div class="bg-white shadow-sm rounded-xl border border-gray-100 mb-6">
                <div class="border-b border-gray-200">
                    <nav class="flex -mb-px" aria-label="Tabs">
                        <?php
                        $tabs = [
                            'overview' => ['icon' => 'fa-home', 'label' => 'Overview'],
                            'courses' => ['icon' => 'fa-book', 'label' => 'Courses'],
                            'timetable' => ['icon' => 'fa-calendar-alt', 'label' => 'Timetable'],
                            'finances' => ['icon' => 'fa-dollar-sign', 'label' => 'Finances'],
                            'results' => ['icon' => 'fa-chart-bar', 'label' => 'Results'],
                            'graduation' => ['icon' => 'fa-graduation-cap', 'label' => 'Graduation'],
                            'certificates' => ['icon' => 'fa-certificate', 'label' => 'Certificates'],
                            'documents' => ['icon' => 'fa-file', 'label' => 'Documents'],
                            'activity' => ['icon' => 'fa-history', 'label' => 'Activity']
                        ];
                        
                        foreach ($tabs as $tab_key => $tab_info):
                            $is_active = $current_tab === $tab_key;
                            $url = admin_url('admin.php?page=nds-learner-dashboard&id=' . $learner_id . '&tab=' . $tab_key);
                        ?>
                            <a href="<?php echo esc_url($url); ?>"
                               class="<?php echo $is_active 
                                   ? 'border-blue-500 text-blue-600' 
                                   : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> 
                                   whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm flex items-center space-x-2 transition-colors">
                                <i class="fas <?php echo esc_attr($tab_info['icon']); ?>"></i>
                                <span><?php echo esc_html($tab_info['label']); ?></span>
                            </a>
                        <?php endforeach; ?>
                    </nav>
                </div>

                <!-- Tab Content -->
                <div class="p-6">
                    <?php
                    switch ($current_tab) {
                        case 'overview':
                            include plugin_dir_path(__FILE__) . 'partials/learner-dashboard-overview.php';
                            break;
                        case 'courses':
                            include plugin_dir_path(__FILE__) . 'partials/learner-dashboard-courses.php';
                            break;
                        case 'timetable':
                            include plugin_dir_path(__FILE__) . 'partials/learner-dashboard-timetable.php';
                            break;
                        case 'finances':
                            include plugin_dir_path(__FILE__) . 'partials/learner-dashboard-finances.php';
                            break;
                        case 'results':
                            include plugin_dir_path(__FILE__) . 'partials/learner-dashboard-results.php';
                            break;
                        case 'graduation':
                            include plugin_dir_path(__FILE__) . 'partials/learner-dashboard-graduation.php';
                            break;
                        case 'certificates':
                            include plugin_dir_path(__FILE__) . 'partials/learner-dashboard-certificates.php';
                            break;
                        case 'documents':
                            include plugin_dir_path(__FILE__) . 'partials/learner-dashboard-documents.php';
                            break;
                        case 'activity':
                            include plugin_dir_path(__FILE__) . 'partials/learner-dashboard-activity.php';
                            break;
                        default:
                            include plugin_dir_path(__FILE__) . 'partials/learner-dashboard-overview.php';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
    <style>
    /* Additional cleanup - remove any WordPress admin footer spacing */
    #wpfooter {
        position: relative !important;
        margin-top: 0 !important;
    }
    </style>
    <?php
}
