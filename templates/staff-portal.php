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
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
        }
        body.nds-portal-body {
            margin: 0;
            padding: 0;
        }
        
        /* Hide website header/navbar completely on portal */
        body.nds-portal-body header,
        body.nds-portal-body .site-header,
        body.nds-portal-body .main-header,
        body.nds-portal-body nav.site-navigation,
        body.nds-portal-body #site-header,
        body.nds-portal-body #masthead,
        body.nds-portal-body .ast-primary-header-bar,
        body.nds-portal-body .site-navigation,
        body.nds-portal-body #header,
        body.nds-portal-body .header {
            display: none !important;
            height: 0 !important;
            margin: 0 !important;
            padding: 0 !important;
            overflow: hidden !important;
        }
        
        /* Remove gap - use margin instead of top */
        .nds-portal-offset {
            margin-top: 0 !important;
            padding-top: 0 !important;
        }
        body.admin-bar .nds-portal-offset { 
            margin-top: 32px !important; 
        }
        @media screen and (max-width: 782px) {
            body.admin-bar .nds-portal-offset { 
                margin-top: 46px !important; 
            }
        }
    </style>
</head>
<body <?php body_class('nds-portal-body'); ?>>
<?php function_exists('wp_body_open') && wp_body_open(); ?>
<?php

global $wpdb;

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
        SELECT c.*, cl.assigned_date
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
$valid_tabs = array('overview', 'timetable', 'classes', 'marks', 'content', 'assessments', 'gradebook', 'communication', 'reports', 'enrollment', 'structure');
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

<div class="nds-tailwind-wrapper bg-gray-50 min-h-screen nds-portal-offset" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
    <!-- Header -->
    <div class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
            <div class="flex items-center justify-between flex-wrap gap-4">
                <div class="flex items-center space-x-4">
                    <div class="w-12 h-12 bg-blue-600 rounded-full flex items-center justify-center shadow-lg">
                        <i class="fas fa-user-tie text-white text-xl"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900"><?php echo esc_html($full_name); ?></h1>
                        <p class="text-sm text-gray-600">
                            <?php echo esc_html($staff_data['role'] ?? 'Staff'); ?> • <?php echo esc_html($staff_data['email'] ?? ''); ?>
                        </p>
                    </div>
                </div>
                <div class="flex items-center space-x-3">
                    <a href="<?php echo esc_url(home_url()); ?>" 
                       class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                        <i class="fas fa-globe mr-2"></i>Go to website
                    </a>
                    <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" 
                       class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 transition-colors">
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
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 mb-1">Courses Teaching</p>
                        <p class="text-3xl font-bold text-gray-900"><?php echo esc_html($courses_count); ?></p>
                    </div>
                    <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-book text-blue-600 text-xl"></i>
                    </div>
                </div>
                <p class="text-xs text-gray-500 mt-2">Total active courses you teach</p>
            </div>

            <!-- Role -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 mb-1">Role</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo esc_html($staff_data['role'] ?? 'Staff'); ?></p>
                    </div>
                    <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-user-check text-green-600 text-xl"></i>
                    </div>
                </div>
                <p class="text-xs text-gray-500 mt-2">Your current position</p>
            </div>

            <!-- Status -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 mb-1">Status</p>
                        <p class="text-2xl font-bold text-green-600">Active</p>
                    </div>
                    <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-check-circle text-green-600 text-xl"></i>
                    </div>
                </div>
                <p class="text-xs text-gray-500 mt-2">Your account status</p>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6">
            <nav class="flex space-x-1 p-2" aria-label="Tabs">
                <a href="<?php echo esc_url(nds_staff_portal_tab_url('overview')); ?>" 
                   class="<?php echo $current_tab === 'overview' ? 'bg-blue-600 text-white' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'; ?> px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center">
                    <i class="fas fa-home mr-2"></i>Overview
                </a>
                <a href="<?php echo esc_url(nds_staff_portal_tab_url('timetable')); ?>" 
                   class="<?php echo $current_tab === 'timetable' ? 'bg-blue-600 text-white' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'; ?> px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center">
                    <i class="fas fa-calendar-alt mr-2"></i>Timetable
                </a>
                <a href="<?php echo esc_url(nds_staff_portal_tab_url('classes')); ?>" 
                   class="<?php echo $current_tab === 'classes' ? 'bg-blue-600 text-white' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'; ?> px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center">
                    <i class="fas fa-chalkboard-teacher mr-2"></i>Classes
                </a>
                <a href="<?php echo esc_url(nds_staff_portal_tab_url('marks')); ?>" 
                   class="<?php echo $current_tab === 'marks' ? 'bg-blue-600 text-white' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'; ?> px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center">
                    <i class="fas fa-graduation-cap mr-2"></i>Marks
                </a>
                <a href="<?php echo esc_url(nds_staff_portal_tab_url('gradebook')); ?>" 
                   class="<?php echo $current_tab === 'gradebook' ? 'bg-blue-600 text-white' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'; ?> px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center">
                    <i class="fas fa-table mr-2"></i>Gradebook
                </a>
                <a href="<?php echo esc_url(nds_staff_portal_tab_url('content')); ?>" 
                   class="<?php echo $current_tab === 'content' ? 'bg-blue-600 text-white' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'; ?> px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center">
                    <i class="fas fa-folder-open mr-2"></i>Content
                </a>
                <a href="<?php echo esc_url(nds_staff_portal_tab_url('assessments')); ?>" 
                   class="<?php echo $current_tab === 'assessments' ? 'bg-blue-600 text-white' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'; ?> px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center">
                    <i class="fas fa-clipboard-check mr-2"></i>Assessments
                </a>
                <a href="<?php echo esc_url(nds_staff_portal_tab_url('communication')); ?>" 
                   class="<?php echo $current_tab === 'communication' ? 'bg-blue-600 text-white' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'; ?> px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center">
                    <i class="fas fa-bullhorn mr-2"></i>Communication
                </a>
                <a href="<?php echo esc_url(nds_staff_portal_tab_url('reports')); ?>" 
                   class="<?php echo $current_tab === 'reports' ? 'bg-blue-600 text-white' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'; ?> px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center">
                    <i class="fas fa-chart-line mr-2"></i>Reports
                </a>
                <a href="<?php echo esc_url(nds_staff_portal_tab_url('enrollment')); ?>" 
                   class="<?php echo $current_tab === 'enrollment' ? 'bg-blue-600 text-white' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'; ?> px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center">
                    <i class="fas fa-user-plus mr-2"></i>Enrollment
                </a>
                <a href="<?php echo esc_url(nds_staff_portal_tab_url('structure')); ?>" 
                   class="<?php echo $current_tab === 'structure' ? 'bg-blue-600 text-white' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'; ?> px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center">
                    <i class="fas fa-sitemap mr-2"></i>Structure
                </a>
            </nav>
        </div>

        <!-- Tab Content -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
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
            }
            ?>
        </div>
    </div>
</div>

<?php wp_footer(); ?>
</body>
</html>
