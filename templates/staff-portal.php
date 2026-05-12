<?php
if (!defined('ABSPATH')) {
    exit;
}

// -------------------------------------------------------------------
// 1. Data Fetching & Helpers
// -------------------------------------------------------------------
global $wpdb;

// Ensure we have the necessary table prefix
$prefix = $wpdb->prefix;

// Get current staff ID
$staff_id = (int) nds_portal_get_current_staff_id();
if ($staff_id <= 0) {
    echo '<div class="nds-tailwind-wrapper bg-gray-50 py-16"><div class="max-w-3xl mx-auto bg-white shadow-sm rounded-xl p-8 text-center text-gray-700">Staff profile not found. Please contact administrator.</div></div>';
    return;
}

$staff = nds_get_staff_by_id($staff_id);
if (!$staff) {
    echo '<div class="nds-tailwind-wrapper bg-gray-50 py-16"><div class="max-w-3xl mx-auto bg-white shadow-sm rounded-xl p-8 text-center text-gray-700">Unable to load staff data.</div></div>';
    return;
}
$staff_data = (array) $staff;
$full_name = trim(($staff_data['first_name'] ?? '') . ' ' . ($staff_data['last_name'] ?? ''));

// -------------------------------------------------------------------
// 2. Courses taught (course-level + module-level assignments)
// -------------------------------------------------------------------
$course_lecturer_columns = $wpdb->get_col("SHOW COLUMNS FROM {$prefix}nds_course_lecturers", 0);
$assigned_col = in_array('assigned_at', $course_lecturer_columns, true) ? 'assigned_at'
    : (in_array('assigned_date', $course_lecturer_columns, true) ? 'assigned_date' : null);
$assigned_expr = $assigned_col ? "cl.{$assigned_col} AS assigned_at" : 'NULL AS assigned_at';

$courses_taught = $wpdb->get_results($wpdb->prepare(
    "SELECT c.*, {$assigned_expr}
     FROM {$prefix}nds_course_lecturers cl
     INNER JOIN {$prefix}nds_courses c ON cl.course_id = c.id
     WHERE cl.lecturer_id = %d AND c.status = 'active'
     ORDER BY c.name ASC",
    $staff_id
), ARRAY_A);

// Include courses from module assignments
$module_course_ids = function_exists('nds_staff_get_lecturer_course_ids_from_modules')
    ? nds_staff_get_lecturer_course_ids_from_modules($staff_id)
    : [];
if (!empty($module_course_ids)) {
    $existing_ids = array_column($courses_taught, 'id');
    $new_ids = array_diff($module_course_ids, $existing_ids);
    if (!empty($new_ids)) {
        $placeholder = implode(',', array_fill(0, count($new_ids), '%d'));
        $extra_courses = $wpdb->get_results($wpdb->prepare(
            "SELECT *, NULL AS assigned_at FROM {$prefix}nds_courses WHERE id IN ({$placeholder}) AND status = 'active'",
            $new_ids
        ), ARRAY_A);
        $courses_taught = array_merge($courses_taught, $extra_courses);
    }
}
$course_ids = array_column($courses_taught, 'id');
$courses_count = count($courses_taught);

// -------------------------------------------------------------------
// 3. Active academic year & semester
// -------------------------------------------------------------------
$active_year_id = (int) $wpdb->get_var("SELECT id FROM {$prefix}nds_academic_years WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
$active_semester_id = $active_year_id ? (int) $wpdb->get_var($wpdb->prepare(
    "SELECT id FROM {$prefix}nds_semesters WHERE academic_year_id = %d AND is_active = 1 ORDER BY id DESC LIMIT 1",
    $active_year_id
)) : 0;
$active_year = $active_year_id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$prefix}nds_academic_years WHERE id = %d", $active_year_id), ARRAY_A) : null;
$active_semester = $active_semester_id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$prefix}nds_semesters WHERE id = %d", $active_semester_id), ARRAY_A) : null;

// -------------------------------------------------------------------
// 4. Course context (selected course)
// -------------------------------------------------------------------
$selected_course_id = isset($_GET['course_id']) ? (int) $_GET['course_id'] : 0;
$selected_course = null;
if ($selected_course_id > 0) {
    foreach ($courses_taught as $c) {
        if ((int) $c['id'] === $selected_course_id) {
            $selected_course = $c;
            break;
        }
    }
}
// If no valid course selected, default to first course (if any)
if (!$selected_course && !empty($courses_taught)) {
    $selected_course = $courses_taught[0];
    $selected_course_id = (int) $selected_course['id'];
}

// -------------------------------------------------------------------
// 5. Tab handling (Moodle-style left nav)
// -------------------------------------------------------------------
$current_tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'overview';
$valid_tabs = ['overview', 'workspace', 'timetable', 'classes', 'marks', 'gradebook', 'content', 'assessments', 'communication', 'reports', 'enrollment', 'structure', 'profile'];
if (!in_array($current_tab, $valid_tabs, true)) {
    $current_tab = 'overview';
}
$is_focus_view = ($current_tab !== 'overview');

if (!function_exists('nds_staff_tab_url')) {
    function nds_staff_tab_url($tab, $course_id = null) {
        $base = home_url('/staff-portal/');
        $args = ['tab' => $tab];
        if ($course_id && $tab !== 'overview') {
            $args['course_id'] = $course_id;
        }
        return add_query_arg($args, $base);
    }
}

// Grouped navigation for clearer scanning and task-based navigation.
$nav_groups = [
    [
        'title' => 'Home',
        'items' => [
            ['tab' => 'workspace', 'icon' => 'fa-bolt', 'label' => 'Workspace'],
        ],
    ],
    [
        'title' => 'Teaching',
        'items' => [
            ['tab' => 'classes', 'icon' => 'fa-chalkboard', 'label' => 'Classes'],
            ['tab' => 'timetable', 'icon' => 'fa-calendar-alt', 'label' => 'Calendar'],
            ['tab' => 'structure', 'icon' => 'fa-sitemap', 'label' => 'Course Layout'],
        ],
    ],
    [
        'title' => 'Assessment',
        'items' => [
            ['tab' => 'marks', 'icon' => 'fa-graduation-cap', 'label' => 'Grades'],
            ['tab' => 'gradebook', 'icon' => 'fa-table', 'label' => 'Gradebook'],
            ['tab' => 'assessments', 'icon' => 'fa-clipboard-list', 'label' => 'Assessments'],
            ['tab' => 'reports', 'icon' => 'fa-chart-line', 'label' => 'Reports'],
        ],
    ],
    [
        'title' => 'People',
        'items' => [
            ['tab' => 'communication', 'icon' => 'fa-comments', 'label' => 'Messages'],
            ['tab' => 'enrollment', 'icon' => 'fa-user-plus', 'label' => 'Enrollment'],
            ['tab' => 'profile', 'icon' => 'fa-user-cog', 'label' => 'Settings'],
        ],
    ],
];

$tab_labels = [];
foreach ($nav_groups as $group) {
    foreach ($group['items'] as $item) {
        $tab_labels[$item['tab']] = $item['label'];
    }
}
$tab_labels['overview'] = 'Dashboard';
$tab_labels['content'] = 'My Courses';

// -------------------------------------------------------------------
// 6. Overview widgets data (only when needed)
// -------------------------------------------------------------------
$recent_announcements = $upcoming_deadlines = $recent_activity = [];
if (!empty($course_ids) && $current_tab === 'overview') {
    $placeholders = implode(',', array_fill(0, count($course_ids), '%d'));
    $recent_announcements = $wpdb->get_results($wpdb->prepare(
        "SELECT title, course_id, created_at FROM {$prefix}nds_lecturer_content
         WHERE course_id IN ({$placeholders}) AND content_type = 'announcement'
         ORDER BY created_at DESC LIMIT 5",
        $course_ids
    ), ARRAY_A);
    if (empty($recent_announcements)) {
        $recent_announcements = $wpdb->get_results($wpdb->prepare(
            "SELECT title, course_id, created_at FROM {$prefix}nds_lecturer_content
             WHERE course_id IN ({$placeholders}) ORDER BY created_at DESC LIMIT 5",
            $course_ids
        ), ARRAY_A);
    }

    $upcoming_deadlines = $wpdb->get_results($wpdb->prepare(
        "SELECT title, course_id, access_end FROM {$prefix}nds_lecturer_content
         WHERE course_id IN ({$placeholders}) AND access_end IS NOT NULL AND access_end >= %s
         ORDER BY access_end ASC LIMIT 6",
        array_merge($course_ids, [current_time('mysql')])
    ), ARRAY_A);

    $recent_activity = $wpdb->get_results($wpdb->prepare(
        "SELECT title, content_type, COALESCE(updated_at, created_at) AS activity_at
         FROM {$prefix}nds_lecturer_content
         WHERE course_id IN ({$placeholders}) ORDER BY activity_at DESC LIMIT 6",
        $course_ids
    ), ARRAY_A);
}

// For current selected course - get enrolled students (used in multiple tabs)
$enrolled_students = [];
if ($selected_course_id && function_exists('nds_get_course_enrollments')) {
    $enrolled_students = nds_get_course_enrollments($selected_course_id);
} elseif ($selected_course_id) {
    // Fallback query
    $enrolled_students = $wpdb->get_results($wpdb->prepare(
        "SELECT s.id, s.first_name, s.last_name, s.email
         FROM {$prefix}nds_enrollments e
         JOIN {$prefix}nds_students s ON e.student_id = s.id
         WHERE e.course_id = %d AND e.status = 'enrolled'",
        $selected_course_id
    ), ARRAY_A);
}

// -------------------------------------------------------------------
// 7. Output starts
// -------------------------------------------------------------------
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html(get_bloginfo('name')); ?> - Lecturer Dashboard</title>
    <?php wp_head(); ?>
    <style>
        :root {
            --nds-shell-offset: 0px;
        }
        body.admin-bar {
            --nds-shell-offset: 32px;
        }
        @media (max-width: 782px) {
            body.admin-bar {
                --nds-shell-offset: 46px;
            }
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
        /* Sidebar scroll */
        .nds-sidebar { max-height: calc(100vh - 80px); overflow-y: auto; }
        .nds-sidebar-mobile {
            position: fixed;
            top: var(--nds-shell-offset);
            left: 0;
            height: calc(100vh - var(--nds-shell-offset));
            width: 18rem;
            z-index: 50;
            transform: translateX(-100%);
            transition: transform 0.22s ease-out;
            will-change: transform;
            overscroll-behavior: contain;
        }
        .nds-sidebar-mobile.is-open { transform: translateX(0); }
        .nds-sidebar-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.45);
            z-index: 40;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.18s ease-out;
        }
        .nds-sidebar-overlay.is-open {
            opacity: 1;
            pointer-events: auto;
        }
        #nds-sidebar-toggle,
        #nds-sidebar-close {
            display: inline-flex;
        }
        /* Toggle button state indicator */
        #nds-sidebar-toggle[aria-expanded="true"] {
            background-color: #eff6ff;
            border-color: #3b82f6;
            color: #3b82f6;
        }
        #nds-sidebar-toggle[aria-expanded="true"] i {
            color: #3b82f6;
        }
        .nds-sidebar::-webkit-scrollbar { width: 6px; }
        .nds-sidebar::-webkit-scrollbar-track { background: transparent; }
        .nds-sidebar::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 3px; }
        .nds-sidebar::-webkit-scrollbar-thumb:hover { background: #9ca3af; }
        /* Smooth transitions */
        a, button, select { transition: color 0.2s ease, background-color 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease; }
        /* Card hover effect */
        .nds-card-hover { transition: box-shadow 0.3s ease, transform 0.2s ease; }
        .nds-card-hover:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.1); transform: translateY(-2px); }
        .nds-top-bar {
            position: sticky;
            top: 0;
            z-index: 20;
            margin-bottom: 0.75rem;
            border-radius: 0.75rem;
        }
        .nds-focus-mode #nds-sidebar,
        .nds-focus-mode .nds-top-bar,
        .nds-focus-mode #nds-sidebar-overlay,
        .nds-focus-mode #nds-sidebar-toggle {
            display: none !important;
        }
        .nds-focus-mode main {
            width: 100%;
            max-width: 100%;
            padding: 0 !important;
        }
        .nds-focus-mode .flex-1 {
            padding: 1rem 1.25rem !important;
        }
        /* Main content area with proper responsive spacing */
        main {
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
            min-width: 0;
        }
        .flex-1 {
            margin-top: 0;
            word-wrap: break-word;
            overflow-wrap: break-word;
            min-width: 0;
        }
        /* Content padding responsive */
        @media (max-width: 767px) {
            .nds-sidebar { max-height: none; }
            main { 
                padding: 0 !important;
                min-height: calc(100vh - 140px);
            }
            .nds-top-bar {
                margin: 0.75rem;
                margin-top: calc(0.75rem + env(safe-area-inset-top, 0px));
            }
            .flex-1 {
                padding: 1rem !important;
            }
        }
        @media (max-width: 640px) {
            .flex-1 {
                padding: 0.75rem !important;
            }
            .nds-card-hover { 
                margin-bottom: 1rem;
                word-break: break-word;
            }
        }
        @media (min-width: 768px) {
            .nds-sidebar-mobile {
                position: static;
                height: auto;
                width: 20rem;
                transform: none;
                z-index: auto;
            }
            .nds-sidebar-overlay {
                display: none;
            }
            #nds-sidebar-toggle,
            #nds-sidebar-close {
                display: none !important;
            }
            main { 
                padding: 0 !important;
            }
            .flex-1 {
                padding: 1.5rem !important;
            }
            .nds-top-bar {
                margin: 1rem 1.5rem 0.75rem;
            }
        }
        @media (prefers-reduced-motion: reduce) {
            .nds-sidebar-mobile,
            .nds-sidebar-overlay,
            .nds-card-hover,
            a,
            button,
            select {
                transition: none !important;
            }
        }
    </style>
</head>
<body <?php body_class('nds-portal-body bg-gray-50'); ?>>
<?php function_exists('wp_body_open') && wp_body_open(); ?>

<div class="nds-layout-root flex min-h-screen bg-gray-50 relative <?php echo $is_focus_view ? 'nds-focus-mode' : ''; ?>" style="padding-top: var(--nds-shell-offset);">
    <div id="nds-sidebar-overlay" class="nds-sidebar-overlay"></div>
    <!-- LEFT SIDEBAR (Moodle style) -->
    <aside id="nds-sidebar" class="nds-sidebar nds-sidebar-mobile bg-white border-r border-gray-200 shadow-sm">
        <div class="p-5 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center shadow">
                        <i class="fas fa-chalkboard-teacher text-white text-xl"></i>
                    </div>
                    <div>
                        <h2 class="font-bold text-gray-800">Lecturer Portal</h2>
                        <p class="text-xs text-gray-500"><?php echo esc_html($full_name); ?></p>
                    </div>
                </div>
                <button type="button" id="nds-sidebar-close" class="md:hidden inline-flex items-center justify-center w-8 h-8 rounded hover:bg-gray-100 text-gray-600" aria-label="Close menu">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <nav class="p-3 space-y-3">
            <?php foreach ($nav_groups as $group) : ?>
                <div>
                    <p class="px-3 pb-1 text-[11px] font-semibold uppercase tracking-wide text-gray-400"><?php echo esc_html($group['title']); ?></p>
                    <div class="space-y-1">
                        <?php foreach ($group['items'] as $item) :
                            $is_active = ($current_tab === $item['tab']);
                            $url = nds_staff_tab_url($item['tab'], ($item['tab'] !== 'overview' && $selected_course_id) ? $selected_course_id : null);
                        ?>
                        <a href="<?php echo esc_url($url); ?>"
                           class="flex items-center px-4 py-3 rounded-lg transition-all duration-200 <?php echo $is_active ? 'bg-blue-50 text-blue-700 font-medium shadow-sm border-l-4 border-blue-500' : 'text-gray-700 hover:bg-gray-100'; ?>">
                            <i class="fas <?php echo esc_attr($item['icon']); ?> w-5 mr-3 text-center"></i>
                            <span><?php echo esc_html($item['label']); ?></span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </nav>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="flex-1 min-w-0 flex flex-col">
        <div class="nds-top-bar bg-white border border-gray-200 shadow-sm px-3 sm:px-5 py-2 sm:py-3">
            <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                <button type="button" id="nds-sidebar-toggle" class="md:hidden inline-flex items-center justify-center flex-shrink-0 px-4 h-10 rounded-lg border-2 border-gray-300 text-gray-700 bg-white hover:bg-blue-50 hover:border-blue-500" aria-controls="nds-sidebar" aria-expanded="false" aria-label="Toggle navigation menu">
                    <span class="text-sm font-medium">Menu</span>
                </button>
                <a href="<?php echo esc_url(home_url('/')); ?>" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-100 border border-transparent">
                    <i class="fas fa-home"></i>
                    <span>Home</span>
                </a>
                <a href="<?php echo esc_url(nds_staff_tab_url('overview')); ?>" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium <?php echo $current_tab === 'overview' ? 'bg-blue-50 text-blue-700 border border-blue-200' : 'text-gray-700 hover:bg-gray-100 border border-transparent'; ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                <?php $my_courses_url = nds_staff_tab_url('content', $selected_course_id ?: null); ?>
                <a href="<?php echo esc_url($my_courses_url); ?>" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium <?php echo $current_tab === 'content' ? 'bg-blue-50 text-blue-700 border border-blue-200' : 'text-gray-700 hover:bg-gray-100 border border-transparent'; ?>">
                    <i class="fas fa-book-open"></i>
                    <span>My Courses</span>
                </a>
            </div>
        </div>

        <!-- Dashboard content -->
        <div class="flex-1 p-3 sm:p-6 lg:p-8">
            <?php if ($current_tab === 'overview') : ?>
                <!-- === OVERVIEW: Moodle-style widgets === -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6 mb-6 sm:mb-8">
                    <!-- Summary Cards -->
                    <div class="bg-white rounded-xl shadow-sm p-5 border border-gray-100 nds-card-hover">
                        <div class="flex justify-between items-start">
                            <div><p class="text-gray-500 text-sm">Courses Teaching</p><p class="text-3xl font-bold"><?php echo $courses_count; ?></p></div>
                            <div class="bg-blue-100 p-3 rounded-lg"><i class="fas fa-book-open text-blue-600 text-xl"></i></div>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm p-5 border border-gray-100 nds-card-hover">
                        <div class="flex justify-between items-start">
                            <div><p class="text-gray-500 text-sm">Active Students</p><p class="text-3xl font-bold"><?php
                                $total_students = 0;
                                foreach ($courses_taught as $c) {
                                    $total_students += $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$prefix}nds_enrollments WHERE course_id = %d AND status = 'enrolled'", $c['id']));
                                }
                                echo $total_students;
                            ?></p></div>
                            <div class="bg-green-100 p-3 rounded-lg"><i class="fas fa-users text-green-600 text-xl"></i></div>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm p-5 border border-gray-100 nds-card-hover">
                        <div class="flex justify-between items-start">
                            <div><p class="text-gray-500 text-sm">Role</p><p class="text-xl font-semibold"><?php echo esc_html($staff_data['role'] ?? 'Lecturer'); ?></p></div>
                            <div class="bg-purple-100 p-3 rounded-lg"><i class="fas fa-user-tag text-purple-600 text-xl"></i></div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6">
                    <!-- Upcoming Deadlines -->
                    <div class="bg-white rounded-xl shadow-sm p-5 nds-card-hover">
                        <h3 class="font-semibold text-gray-800 flex items-center"><i class="fas fa-hourglass-half text-orange-500 mr-2"></i> Upcoming Deadlines</h3>
                        <?php if (empty($upcoming_deadlines)) : ?>
                            <p class="text-gray-500 text-sm mt-3">No upcoming deadlines.</p>
                        <?php else : ?>
                            <ul class="mt-3 space-y-2">
                                <?php foreach ($upcoming_deadlines as $dl) : ?>
                                <li class="border-b pb-2 flex justify-between">
                                    <span class="text-sm"><?php echo esc_html($dl['title']); ?></span>
                                    <span class="text-xs text-gray-500"><?php echo date_i18n(get_option('date_format'), strtotime($dl['access_end'])); ?></span>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                    <!-- Recent Announcements -->
                    <div class="bg-white rounded-xl shadow-sm p-5 nds-card-hover">
                        <h3 class="font-semibold text-gray-800 flex items-center"><i class="fas fa-bullhorn text-blue-500 mr-2"></i> Recent Announcements</h3>
                        <?php if (empty($recent_announcements)) : ?>
                            <p class="text-gray-500 text-sm mt-3">No announcements yet.</p>
                        <?php else : ?>
                            <ul class="mt-3 space-y-2">
                                <?php foreach ($recent_announcements as $ann) : ?>
                                <li class="border-b pb-2">
                                    <p class="text-sm font-medium"><?php echo esc_html($ann['title']); ?></p>
                                    <p class="text-xs text-gray-400"><?php echo date_i18n(get_option('date_format'), strtotime($ann['created_at'])); ?></p>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                    <!-- My Courses (recent) -->
                    <div class="bg-white rounded-xl shadow-sm p-5 lg:col-span-2 nds-card-hover">
                        <h3 class="font-semibold text-gray-800 flex items-center"><i class="fas fa-chalkboard-teacher mr-2"></i> My Active Courses</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-3">
                            <?php foreach (array_slice($courses_taught, 0, 4) as $course) : ?>
                            <a href="<?php echo esc_url(nds_staff_tab_url('content', $course['id'])); ?>" class="block border rounded-lg p-3 hover:bg-gray-50 transition">
                                <p class="font-medium"><?php echo esc_html($course['name']); ?></p>
                                <p class="text-xs text-gray-500 mt-1"><?php echo esc_html($course['code'] ?? ''); ?></p>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

            <?php else : ?>
                <!-- Include tab partials, passing $selected_course and $enrolled_students -->
                <?php
                $partial_path = plugin_dir_path(__FILE__) . "../includes/partials/staff-dashboard-{$current_tab}.php";
                if (file_exists($partial_path)) {
                    // Partials are included in this scope and can access context variables directly.
                    include $partial_path;
                } else {
                    echo '<div class="bg-white rounded-xl shadow-sm p-8 text-center text-gray-500">Content for "' . esc_html($current_tab) . '" is being developed.</div>';
                }
                ?>
            <?php endif; ?>
        </div>
    </main>
</div>

<script>
// Course selector live switch (only if element exists)
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('nds-sidebar');
    const overlay = document.getElementById('nds-sidebar-overlay');
    const toggleBtn = document.getElementById('nds-sidebar-toggle');
    const closeBtn = document.getElementById('nds-sidebar-close');

    if (!sidebar || !overlay) {
        return;
    }

    function setExpandedState(isExpanded) {
        if (toggleBtn) {
            toggleBtn.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
        }
    }

    function closeSidebar() {
        sidebar.classList.remove('is-open');
        overlay.classList.remove('is-open');
        document.body.classList.remove('overflow-hidden');
        setExpandedState(false);
    }

    function openSidebar() {
        sidebar.classList.add('is-open');
        overlay.classList.add('is-open');
        document.body.classList.add('overflow-hidden');
        setExpandedState(true);
    }

    function toggleSidebar(event) {
        if (event) {
            event.preventDefault();
        }
        if (sidebar.classList.contains('is-open')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    }

    if (toggleBtn) {
        toggleBtn.addEventListener('click', toggleSidebar);
    }
    if (closeBtn) {
        closeBtn.addEventListener('click', function(event) {
            event.preventDefault();
            closeSidebar();
        });
    }
    if (overlay) {
        overlay.addEventListener('click', function(event) {
            event.preventDefault();
            closeSidebar();
        });
    }

    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeSidebar();
        }
    });

    window.addEventListener('resize', function() {
        if (window.innerWidth >= 768) {
            closeSidebar();
        }
    });

    // Close sidebar on scroll (mobile UX improvement)
    const main = document.querySelector('main');
    const contentArea = document.querySelector('.flex-1');
    let scrollTimeout;
    let scrollStartY = 0;
    
    function handleScroll() {
        // Only auto-close on mobile to avoid desktop side effects.
        if (window.innerWidth < 768 && sidebar.classList.contains('is-open')) {
            closeSidebar();
        }
        // Debounce scroll handler
        clearTimeout(scrollTimeout);
        scrollTimeout = setTimeout(() => {
            scrollTimeout = null;
        }, 100);
    }

    function onTouchStart() {
        scrollStartY = window.scrollY || document.documentElement.scrollTop || 0;
    }

    function onTouchMove() {
        const currentY = window.scrollY || document.documentElement.scrollTop || 0;
        if (Math.abs(currentY - scrollStartY) > 8) {
            handleScroll();
        }
    }

    if (main) {
        main.addEventListener('scroll', handleScroll, { passive: true });
    }
    if (contentArea) {
        contentArea.addEventListener('scroll', handleScroll, { passive: true });
    }
    // Also listen on window scroll
    window.addEventListener('scroll', handleScroll, { passive: true });
    window.addEventListener('touchstart', onTouchStart, { passive: true });
    window.addEventListener('touchmove', onTouchMove, { passive: true });

    const selector = document.getElementById('course-selector');
    if (selector) {
        selector.addEventListener('change', function(e) {
            const courseId = e.target.value;
            const currentTab = '<?php echo esc_js($current_tab); ?>';
            let url = '<?php echo esc_url(nds_staff_tab_url($current_tab, 0)); ?>';
            url = url.replace(/course_id=\d+/, 'course_id=' + courseId);
            if (!url.includes('course_id=')) {
                url += (url.includes('?') ? '&' : '?') + 'course_id=' + courseId;
            }
            window.location.href = url;
        });
    }
});
</script>

<?php wp_footer(); ?>
</body>
</html>