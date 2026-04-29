<?php
if (!defined('ABSPATH')) {
    exit;
}

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html(get_bloginfo('name')); ?> — Staff Portal</title>
    <?php wp_head(); ?>
    <style>
        /* Must come after wp_head() to override WP's inline admin-bar margin injection */
        html, html.admin-bar {
            margin-top: 0 !important;
            padding-top: 0 !important;
        }
        body.nds-portal-body,
        body.nds-portal-body #page,
        body.nds-portal-body #content,
        body.nds-portal-body main,
        body.nds-portal-body .site,
        body.nds-portal-body .site-content,
        body.nds-portal-body .ast-container,
        body.nds-portal-body .ast-plain-container,
        body.nds-portal-body .ast-builder-grid-row,
        body.nds-portal-body .ast-site-content-wrap,
        body.nds-portal-body .content-area,
        body.nds-portal-body article,
        body.nds-portal-body .hentry,
        body.nds-portal-body .entry-content {
            margin-top: 0 !important;
            padding-top: 0 !important;
        }
    </style>
</head>
<body <?php body_class('nds-portal-body'); ?>>
<?php function_exists('wp_body_open') && wp_body_open(); ?>
<?php

global $wpdb;

$course_lecturer_columns = $wpdb->get_col("SHOW COLUMNS FROM {$wpdb->prefix}nds_course_lecturers", 0);
$assigned_col = in_array('assigned_at', $course_lecturer_columns, true)
    ? 'assigned_at'
    : (in_array('assigned_date', $course_lecturer_columns, true) ? 'assigned_date' : null);
$assigned_expr = $assigned_col ? "cl.{$assigned_col} AS assigned_at" : 'NULL AS assigned_at';

// Resolve current staff from logged-in user
$staff_id = (int) nds_portal_get_current_staff_id();
if ($staff_id <= 0) {
    echo '<div class="nds-tailwind-wrapper bg-gray-50 py-16"><div class="max-w-3xl mx-auto bg-white shadow-sm rounded-xl p-8 text-center text-gray-700">We could not find a staff profile linked to your account. Please contact the administrator.</div></div>';
    return;
}

$staff = nds_get_staff_by_id($staff_id);
if (!$staff) {
    echo '<div class="nds-tailwind-wrapper bg-gray-50 py-16"><div class="max-w-3xl mx-auto bg-white shadow-sm rounded-xl p-8 text-center text-gray-700">Your staff profile could not be loaded. Please contact the administrator.</div></div>';
    return;
}

$staff_data = (array) $staff;
$full_name = trim(($staff_data['first_name'] ?? '') . ' ' . ($staff_data['last_name'] ?? ''));

// Get courses this staff member teaches
$courses_taught = $wpdb->get_results(
    $wpdb->prepare(
        "
        SELECT c.*, {$assigned_expr}
        FROM {$wpdb->prefix}nds_course_lecturers cl
        INNER JOIN {$wpdb->prefix}nds_courses c ON cl.course_id = c.id
        WHERE cl.lecturer_id = %d
        AND c.status = 'active'
        ORDER BY c.name ASC
        ",
        $staff_id
    ),
    ARRAY_A
);

// Get course IDs for filtering
$course_ids = array_column($courses_taught, 'id');
$courses_count = count($courses_taught);

// Get active academic year and semester
$active_year_id = (int) $wpdb->get_var("SELECT id FROM {$wpdb->prefix}nds_academic_years WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
$active_semester_id = $active_year_id ? (int) $wpdb->get_var($wpdb->prepare(
    "SELECT id FROM {$wpdb->prefix}nds_semesters WHERE academic_year_id = %d AND is_active = 1 ORDER BY id DESC LIMIT 1",
    $active_year_id
)) : 0;

$active_year = $active_year_id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}nds_academic_years WHERE id = %d", $active_year_id), ARRAY_A) : null;
$active_semester = $active_semester_id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}nds_semesters WHERE id = %d", $active_semester_id), ARRAY_A) : null;

// Current tab
$current_tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'overview';
$valid_tabs = array('overview', 'timetable', 'classes', 'marks', 'content', 'assessments', 'gradebook', 'communication', 'reports', 'enrollment', 'structure', 'profile');
if (!in_array($current_tab, $valid_tabs, true)) {
    $current_tab = 'overview';
}

// Helper to build tab URLs
function nds_staff_portal_tab_url($tab)
{
    $base = home_url('/staff-portal/');
    if ($tab === 'overview') {
        return $base;
    }
    return add_query_arg('tab', $tab, $base);
}
?>

<div class="nds-tailwind-wrapper bg-gray-50 min-h-screen nds-portal-offset nds-portal-theme" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
    <!-- Header -->
    <div class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-6">
                <div class="flex items-center space-x-4">
                    <div class="w-12 h-12 bg-blue-600 rounded-xl flex items-center justify-center shadow-lg">
                        <i class="fas fa-user-tie text-white text-2xl"></i>
                    </div>
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900"><?php echo esc_html($full_name); ?></h1>
                        <p class="text-sm text-gray-600 mt-1">
                            <?php echo esc_html($staff_data['role'] ?? 'Staff'); ?> • <?php echo esc_html($staff_data['email'] ?? ''); ?>
                        </p>
                    </div>
                </div>
                <div class="flex items-center space-x-3">
                    <a href="<?php echo esc_url(home_url()); ?>" 
                       class="inline-flex items-center px-4 py-2 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 text-sm font-medium shadow-sm transition-all duration-200">
                        <i class="fas fa-globe mr-2"></i>Go to website
                    </a>
                    <a href="<?php echo esc_url(nds_staff_portal_tab_url('profile')); ?>" 
                       class="inline-flex items-center px-4 py-2 rounded-lg border border-blue-200 bg-blue-50 hover:bg-blue-100 text-blue-700 text-sm font-medium shadow-sm transition-all duration-200">
                        <i class="fas fa-user-cog mr-2"></i>Profile
                    </a>
                    <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" 
                       class="inline-flex items-center px-4 py-2 rounded-lg bg-red-50 hover:bg-red-100 text-red-700 text-sm font-medium shadow-sm transition-all duration-200">
                        <i class="fas fa-sign-out-alt mr-2"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <!-- Courses Taught -->
            <div class="bg-white shadow-sm rounded-xl p-5 border border-gray-100 flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Courses Teaching</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900"><?php echo esc_html($courses_count); ?></p>
                    </div>
                    <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center">
                        <i class="fas fa-book text-blue-600 text-xl"></i>
                    </div>
                </div>
                <p class="mt-3 text-xs text-gray-500">Total active courses you teach.</p>
            </div>

            <!-- Role -->
            <div class="bg-white shadow-sm rounded-xl p-5 border border-gray-100 flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Role</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900"><?php echo esc_html($staff_data['role'] ?? 'Staff'); ?></p>
                    </div>
                    <div class="w-10 h-10 rounded-lg bg-emerald-50 flex items-center justify-center">
                        <i class="fas fa-user-check text-emerald-600 text-xl"></i>
                    </div>
                </div>
                <p class="mt-3 text-xs text-gray-500">Your current staff position.</p>
            </div>

            <!-- Status -->
            <div class="bg-white shadow-sm rounded-xl p-5 border border-gray-100 flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Status</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900">
                            <span class="px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">Active</span>
                        </p>
                    </div>
                    <div class="w-10 h-10 rounded-lg bg-purple-50 flex items-center justify-center">
                        <i class="fas fa-check-circle text-purple-600 text-xl"></i>
                    </div>
                </div>
                <p class="mt-3 text-xs text-gray-500">Your current account status.</p>
            </div>
        </div>

        <!-- Tabs -->
        <div class="bg-white shadow-sm rounded-xl border border-gray-100 mb-6">
            <div class="border-b border-gray-200">
                <nav class="flex -mb-px overflow-x-auto" aria-label="Tabs">
                <a href="<?php echo esc_url(nds_staff_portal_tab_url('overview')); ?>" 
                   class="<?php echo $current_tab === 'overview' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm flex items-center space-x-2 transition-colors">
                    <i class="fas fa-home mr-2"></i>Overview
                </a>
                <a href="<?php echo esc_url(nds_staff_portal_tab_url('timetable')); ?>" 
                   class="<?php echo $current_tab === 'timetable' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm flex items-center space-x-2 transition-colors">
                    <i class="fas fa-calendar-alt mr-2"></i>Timetable
                </a>
                <a href="<?php echo esc_url(nds_staff_portal_tab_url('classes')); ?>" 
                   class="<?php echo $current_tab === 'classes' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm flex items-center space-x-2 transition-colors">
                    <i class="fas fa-chalkboard-teacher mr-2"></i>Classes
                </a>
                <a href="<?php echo esc_url(nds_staff_portal_tab_url('marks')); ?>" 
                   class="<?php echo $current_tab === 'marks' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm flex items-center space-x-2 transition-colors">
                    <i class="fas fa-graduation-cap mr-2"></i>Marks
                </a>
                <a href="<?php echo esc_url(nds_staff_portal_tab_url('gradebook')); ?>" 
                   class="<?php echo $current_tab === 'gradebook' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm flex items-center space-x-2 transition-colors">
                    <i class="fas fa-table mr-2"></i>Gradebook
                </a>
                <a href="<?php echo esc_url(nds_staff_portal_tab_url('content')); ?>" 
                   class="<?php echo $current_tab === 'content' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm flex items-center space-x-2 transition-colors">
                    <i class="fas fa-folder-open mr-2"></i>Content
                </a>
                <a href="<?php echo esc_url(nds_staff_portal_tab_url('assessments')); ?>" 
                   class="<?php echo $current_tab === 'assessments' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm flex items-center space-x-2 transition-colors">
                    <i class="fas fa-clipboard-check mr-2"></i>Assessments
                </a>
                <a href="<?php echo esc_url(nds_staff_portal_tab_url('communication')); ?>" 
                   class="<?php echo $current_tab === 'communication' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm flex items-center space-x-2 transition-colors">
                    <i class="fas fa-bullhorn mr-2"></i>Communication
                </a>
                <a href="<?php echo esc_url(nds_staff_portal_tab_url('reports')); ?>" 
                   class="<?php echo $current_tab === 'reports' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm flex items-center space-x-2 transition-colors">
                    <i class="fas fa-chart-line mr-2"></i>Reports
                </a>
                <a href="<?php echo esc_url(nds_staff_portal_tab_url('enrollment')); ?>" 
                   class="<?php echo $current_tab === 'enrollment' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm flex items-center space-x-2 transition-colors">
                    <i class="fas fa-user-plus mr-2"></i>Enrollment
                </a>
                <a href="<?php echo esc_url(nds_staff_portal_tab_url('structure')); ?>" 
                   class="<?php echo $current_tab === 'structure' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm flex items-center space-x-2 transition-colors">
                    <i class="fas fa-sitemap mr-2"></i>Structure
                </a>
                <a href="<?php echo esc_url(nds_staff_portal_tab_url('profile')); ?>" 
                   class="<?php echo $current_tab === 'profile' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm flex items-center space-x-2 transition-colors">
                    <i class="fas fa-user-cog mr-2"></i>Profile
                </a>
                </nav>
            </div>

            <!-- Tab Content -->
            <div class="p-6 nds-content-area">
                <?php
                if ($current_tab === 'overview') {
                    include plugin_dir_path(__FILE__) . '../includes/partials/staff-dashboard-overview.php';
                } elseif ($current_tab === 'timetable') {
                    include plugin_dir_path(__FILE__) . '../includes/partials/staff-dashboard-timetable.php';
                } elseif ($current_tab === 'classes') {
                    include plugin_dir_path(__FILE__) . '../includes/partials/staff-dashboard-classes.php';
                } elseif ($current_tab === 'marks') {
                    include plugin_dir_path(__FILE__) . '../includes/partials/staff-dashboard-marks.php';
                } elseif ($current_tab === 'gradebook') {
                    include plugin_dir_path(__FILE__) . '../includes/partials/staff-dashboard-gradebook.php';
                } elseif ($current_tab === 'content') {
                    include plugin_dir_path(__FILE__) . '../includes/partials/staff-dashboard-content.php';
                } elseif ($current_tab === 'assessments') {
                    include plugin_dir_path(__FILE__) . '../includes/partials/staff-dashboard-assessments.php';
                } elseif ($current_tab === 'communication') {
                    include plugin_dir_path(__FILE__) . '../includes/partials/staff-dashboard-communication.php';
                } elseif ($current_tab === 'reports') {
                    include plugin_dir_path(__FILE__) . '../includes/partials/staff-dashboard-reports.php';
                } elseif ($current_tab === 'enrollment') {
                    include plugin_dir_path(__FILE__) . '../includes/partials/staff-dashboard-enrollment.php';
                } elseif ($current_tab === 'structure') {
                    include plugin_dir_path(__FILE__) . '../includes/partials/staff-dashboard-structure.php';
                } elseif ($current_tab === 'profile') {
                    include plugin_dir_path(__FILE__) . '../includes/partials/staff-dashboard-profile.php';
                }
                ?>
            </div>
        </div>
    </div>
</div>

<?php wp_footer(); ?>
</body>
</html>
