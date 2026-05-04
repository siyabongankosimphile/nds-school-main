<?php
if (!defined('ABSPATH')) {
    exit;
}
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html(get_bloginfo('name')); ?> - Timetable Coordinator Portal</title>
    <?php wp_head(); ?>
    <style>
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

$staff_id = (int) nds_portal_get_current_staff_id();
if ($staff_id <= 0) {
    echo '<div class="nds-tailwind-wrapper bg-gray-50 py-16"><div class="max-w-3xl mx-auto bg-white shadow-sm rounded-xl p-8 text-center text-gray-700">We could not find a staff profile linked to your account. Please contact the administrator.</div></div>';
    wp_footer();
    echo '</body></html>';
    exit;
}

$staff = nds_get_staff_by_id($staff_id);
if (!$staff) {
    echo '<div class="nds-tailwind-wrapper bg-gray-50 py-16"><div class="max-w-3xl mx-auto bg-white shadow-sm rounded-xl p-8 text-center text-gray-700">Your staff profile could not be loaded. Please contact the administrator.</div></div>';
    wp_footer();
    echo '</body></html>';
    exit;
}

$staff_data = (array) $staff;
$full_name = trim(($staff_data['first_name'] ?? '') . ' ' . ($staff_data['last_name'] ?? ''));
$role_name = (string) ($staff_data['role'] ?? 'Timetable Coordinator');

$current_tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'overview';
$valid_tabs = array('overview', 'schedule', 'rooms', 'calendar', 'profile');
if (!in_array($current_tab, $valid_tabs, true)) {
    $current_tab = 'overview';
}

if (!function_exists('nds_timetable_portal_tab_url')) {
    function nds_timetable_portal_tab_url($tab)
    {
        $base = home_url('/timetable-portal/');
        if ($tab === 'overview') {
            return $base;
        }
        return add_query_arg('tab', $tab, $base);
    }
}

$today = current_time('Y-m-d');
$total_schedules = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}nds_course_schedules WHERE is_active = 1");
$active_rooms = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}nds_rooms WHERE is_active = 1");
$total_rooms = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}nds_rooms");
$total_programs = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}nds_programs");
$upcoming_schedules = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*)
     FROM {$wpdb->prefix}nds_course_schedules
     WHERE is_active = 1
     AND (valid_to IS NULL OR valid_to = '' OR valid_to >= %s)",
    $today
));
?>

<div class="nds-tailwind-wrapper bg-gray-50 min-h-screen nds-portal-offset nds-portal-theme" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
    <div class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-6">
                <div class="flex items-center space-x-4">
                    <div class="w-12 h-12 bg-indigo-600 rounded-xl flex items-center justify-center shadow-lg">
                        <i class="fas fa-calendar-alt text-white text-2xl"></i>
                    </div>
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900"><?php echo esc_html($full_name !== '' ? $full_name : 'Timetable Coordinator'); ?></h1>
                        <p class="text-sm text-gray-600 mt-1">
                            <?php echo esc_html($role_name); ?> • <?php echo esc_html($staff_data['email'] ?? ''); ?>
                        </p>
                    </div>
                </div>
                <div class="flex items-center space-x-3">
                    <a href="<?php echo esc_url(home_url()); ?>"
                       class="inline-flex items-center px-4 py-2 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 text-sm font-medium shadow-sm transition-all duration-200">
                        <i class="fas fa-globe mr-2"></i>Go to website
                    </a>
                    <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>"
                       class="inline-flex items-center px-4 py-2 rounded-lg bg-red-50 hover:bg-red-100 text-red-700 text-sm font-medium shadow-sm transition-all duration-200">
                        <i class="fas fa-sign-out-alt mr-2"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
            <div class="bg-white shadow-sm rounded-xl p-5 border border-gray-100 flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Active Schedules</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900"><?php echo esc_html($total_schedules); ?></p>
                    </div>
                    <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center">
                        <i class="fas fa-calendar-check text-blue-600 text-xl"></i>
                    </div>
                </div>
                <p class="mt-3 text-xs text-gray-500">Current timetable entries in the system.</p>
            </div>
            <div class="bg-white shadow-sm rounded-xl p-5 border border-gray-100 flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Active Rooms</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900"><?php echo esc_html($active_rooms); ?></p>
                    </div>
                    <div class="w-10 h-10 rounded-lg bg-emerald-50 flex items-center justify-center">
                        <i class="fas fa-door-open text-emerald-600 text-xl"></i>
                    </div>
                </div>
                <p class="mt-3 text-xs text-gray-500">Rooms currently available for allocation.</p>
            </div>
            <div class="bg-white shadow-sm rounded-xl p-5 border border-gray-100 flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Total Rooms</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900"><?php echo esc_html($total_rooms); ?></p>
                    </div>
                    <div class="w-10 h-10 rounded-lg bg-amber-50 flex items-center justify-center">
                        <i class="fas fa-building text-amber-600 text-xl"></i>
                    </div>
                </div>
                <p class="mt-3 text-xs text-gray-500">All rooms and venues recorded in NDS.</p>
            </div>
            <div class="bg-white shadow-sm rounded-xl p-5 border border-gray-100 flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Programs</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900"><?php echo esc_html($total_programs); ?></p>
                    </div>
                    <div class="w-10 h-10 rounded-lg bg-purple-50 flex items-center justify-center">
                        <i class="fas fa-sitemap text-purple-600 text-xl"></i>
                    </div>
                </div>
                <p class="mt-3 text-xs text-gray-500">Academic programs currently configured.</p>
            </div>
        </div>

        <div class="bg-white shadow-sm rounded-xl border border-gray-100 mb-6">
            <div class="border-b border-gray-200">
                <nav class="flex -mb-px overflow-x-auto" aria-label="Tabs">
                    <a href="<?php echo esc_url(nds_timetable_portal_tab_url('overview')); ?>"
                       class="<?php echo $current_tab === 'overview' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm flex items-center space-x-2 transition-colors">
                        <i class="fas fa-home mr-2"></i>Overview
                    </a>
                    <a href="<?php echo esc_url(nds_timetable_portal_tab_url('schedule')); ?>"
                       class="<?php echo $current_tab === 'schedule' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm flex items-center space-x-2 transition-colors">
                        <i class="fas fa-calendar-alt mr-2"></i>Schedule
                    </a>
                    <a href="<?php echo esc_url(nds_timetable_portal_tab_url('rooms')); ?>"
                       class="<?php echo $current_tab === 'rooms' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm flex items-center space-x-2 transition-colors">
                        <i class="fas fa-building mr-2"></i>Rooms & Venues
                    </a>
                    <a href="<?php echo esc_url(nds_timetable_portal_tab_url('calendar')); ?>"
                       class="<?php echo $current_tab === 'calendar' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm flex items-center space-x-2 transition-colors">
                        <i class="fas fa-calendar-day mr-2"></i>Calendar
                    </a>
                    <a href="<?php echo esc_url(nds_timetable_portal_tab_url('profile')); ?>"
                       class="<?php echo $current_tab === 'profile' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm flex items-center space-x-2 transition-colors">
                        <i class="fas fa-user-cog mr-2"></i>Profile
                    </a>
                </nav>
            </div>

            <div class="p-6 nds-content-area">
                <?php if ($current_tab === 'overview') : ?>
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <div class="lg:col-span-2 bg-gray-50 rounded-xl p-6 border border-gray-200">
                            <h2 class="text-lg font-semibold text-gray-900 mb-3">Coordinator Dashboard</h2>
                            <p class="text-sm text-gray-700 mb-4">Use this portal to manage timetable schedules, venue allocations, room availability, and calendar events from one place.</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <a href="<?php echo esc_url(nds_timetable_portal_tab_url('schedule')); ?>" class="inline-flex items-center justify-center rounded-lg border border-indigo-200 bg-indigo-50 text-indigo-700 px-4 py-3 text-sm font-medium hover:bg-indigo-100 transition-colors">
                                    <i class="fas fa-calendar-check mr-2"></i>Manage Schedules
                                </a>
                                <a href="<?php echo esc_url(nds_timetable_portal_tab_url('rooms')); ?>" class="inline-flex items-center justify-center rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-700 px-4 py-3 text-sm font-medium hover:bg-emerald-100 transition-colors">
                                    <i class="fas fa-door-open mr-2"></i>Manage Rooms
                                </a>
                                <a href="<?php echo esc_url(nds_timetable_portal_tab_url('calendar')); ?>" class="inline-flex items-center justify-center rounded-lg border border-blue-200 bg-blue-50 text-blue-700 px-4 py-3 text-sm font-medium hover:bg-blue-100 transition-colors">
                                    <i class="fas fa-calendar mr-2"></i>Open Calendar
                                </a>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=nds-timetable')); ?>" class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white text-gray-700 px-4 py-3 text-sm font-medium hover:bg-gray-50 transition-colors">
                                    <i class="fas fa-tools mr-2"></i>Open Admin Tools
                                </a>
                            </div>
                        </div>
                        <div class="bg-white rounded-xl p-6 border border-gray-200">
                            <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-600 mb-3">Operational Snapshot</h3>
                            <ul class="space-y-2 text-sm text-gray-700">
                                <li class="flex items-center justify-between"><span>Upcoming sessions</span><strong><?php echo esc_html($upcoming_schedules); ?></strong></li>
                                <li class="flex items-center justify-between"><span>Active schedules</span><strong><?php echo esc_html($total_schedules); ?></strong></li>
                                <li class="flex items-center justify-between"><span>Active rooms</span><strong><?php echo esc_html($active_rooms); ?></strong></li>
                                <li class="flex items-center justify-between"><span>Total programs</span><strong><?php echo esc_html($total_programs); ?></strong></li>
                            </ul>
                        </div>
                    </div>
                <?php elseif ($current_tab === 'schedule') : ?>
                    <?php include plugin_dir_path(__FILE__) . '../includes/timetable-schedule-management.php'; ?>
                <?php elseif ($current_tab === 'rooms') : ?>
                    <?php nds_rooms_page(); ?>
                <?php elseif ($current_tab === 'calendar') : ?>
                    <?php nds_calendar_page(); ?>
                <?php elseif ($current_tab === 'profile') : ?>
                    <?php $profile_form_action = 'nds_portal_update_timetable_profile'; ?>
                    <?php include plugin_dir_path(__FILE__) . '../includes/partials/staff-dashboard-profile.php'; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php wp_footer(); ?>
</body>
</html>
