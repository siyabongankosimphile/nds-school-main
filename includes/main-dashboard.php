<?php
// Prevent direct access - this file should only be included by WordPress
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Ensure WordPress functions are available
if (!function_exists('current_user_can')) {
    return;
}

// NDS Academy Main Dashboard - Modern Tailwind CSS Implementation
function nds_academy_main_dashboard() {
    if (!current_user_can('manage_options')) { wp_die('Unauthorized'); }

    global $wpdb;

    // ---------------------------------------------------------------------
    // High-level statistics - OPTIMIZED: Combined queries for better performance
    // ---------------------------------------------------------------------
    // Cache key for dashboard stats (5 minute cache)
    $cache_key = 'nds_dashboard_stats';
    $stats = get_transient($cache_key);
    
    if (false === $stats) {
        // Get all student counts in one query
        $student_stats = $wpdb->get_row("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active
            FROM {$wpdb->prefix}nds_students
        ", ARRAY_A);
        
        // Get application counts in one query
        $app_stats = $wpdb->get_row("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status IN ('submitted', 'under_review', 'conditional_offer') THEN 1 ELSE 0 END) as pending
            FROM {$wpdb->prefix}nds_applications
        ", ARRAY_A);
        
        // Get all other counts in parallel queries (can't easily combine different tables)
        $stats = array(
            'total_students' => (int) ($student_stats['total'] ?? 0),
            'active_students' => (int) ($student_stats['active'] ?? 0),
            'total_staff' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}nds_staff"),
            'total_courses' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}nds_courses"),
            'total_programs' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}nds_programs"),
            'total_faculties' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}nds_faculties"),
            'total_recipes' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}nds_recipes"),
            'total_applications' => (int) ($app_stats['total'] ?? 0),
            'pending_applications' => (int) ($app_stats['pending'] ?? 0)
        );
        
        // Cache for 5 minutes
        set_transient($cache_key, $stats, 5 * MINUTE_IN_SECONDS);
    }
    
    // Extract cached values
    $total_students = $stats['total_students'];
    $active_students = $stats['active_students'];
    $total_staff = $stats['total_staff'];
    $total_courses = $stats['total_courses'];
    $total_programs = $stats['total_programs'];
    $total_faculties = $stats['total_faculties'];
    $total_recipes = $stats['total_recipes'];
    $total_applications = $stats['total_applications'];
    $pending_applications = $stats['pending_applications'];

    // --- NEW: Fetch lists for modals ---
    $all_students_list = $wpdb->get_results("SELECT id, first_name, last_name, student_number, status FROM {$wpdb->prefix}nds_students ORDER BY created_at DESC", ARRAY_A);
    $all_staff_list = $wpdb->get_results("SELECT id, first_name, last_name, role FROM {$wpdb->prefix}nds_staff ORDER BY created_at DESC", ARRAY_A);
    $all_programs_list = $wpdb->get_results("SELECT id, name, description FROM {$wpdb->prefix}nds_programs ORDER BY created_at DESC", ARRAY_A);
    $all_apps_list = $wpdb->get_results("
        SELECT a.id, a.application_no, af.full_name, a.status, a.created_at as submitted_at
        FROM {$wpdb->prefix}nds_applications a
        LEFT JOIN {$wpdb->prefix}nds_application_forms af ON a.id = af.application_id
        GROUP BY a.id
        ORDER BY a.created_at DESC
    ", ARRAY_A);

    // ---------------------------------------------------------------------
    // Recent activity
    // ---------------------------------------------------------------------
    $recent_students = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}nds_students ORDER BY created_at DESC LIMIT 5", ARRAY_A);
    $recent_staff = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}nds_staff ORDER BY created_at DESC LIMIT 5", ARRAY_A);
    $recent_applications = $wpdb->get_results("
        SELECT a.*, s.first_name, s.last_name, s.student_number
        FROM {$wpdb->prefix}nds_applications a
        LEFT JOIN {$wpdb->prefix}nds_students s ON a.student_id = s.id
        ORDER BY a.created_at DESC
        LIMIT 5
    ", ARRAY_A);

    // ---------------------------------------------------------------------
    // Quick calculations
    // ---------------------------------------------------------------------
    $enrollment_rate = $total_students > 0 ? round(($active_students / max(1, $total_students)) * 100) : 0;
    $staff_student_ratio = $total_students > 0 ? round($total_students / max(1, $total_staff), 1) : 0; // students per staff
    $pending_applications_pct = $total_applications > 0 ? round(($pending_applications / max(1, $total_applications)) * 100) : 0;
    ?>
    <style>
        /* Ensure the WordPress footer doesn't overlap our custom dashboard */
        body[class*="nds-academy"] #wpfooter { display: none !important; }
        .nds-tailwind-wrapper { position: relative; z-index: 1; }
    </style>
    <div class="nds-tailwind-wrapper bg-gray-50 pb-32" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin-left: -20px; padding-left: 20px; margin-top: -20px;">
        <!-- Header -->
        <div class="bg-white shadow-sm border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center py-6">
                    <div class="flex items-center space-x-4">
                        <div class="w-12 h-12 bg-gradient-to-br from-blue-600 to-purple-600 rounded-xl flex items-center justify-center">
                            <span class="dashicons dashicons-welcome-learn-more text-white text-2xl"></span>
                        </div>
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900">NDS Academy Dashboard</h1>
                            <p class="text-gray-600">High-level overview of your students, programs, courses, and applications.</p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-3">
                        <div class="text-right">
                            <p class="text-xs uppercase tracking-wide text-gray-500">Last updated</p>
                            <p class="text-sm font-medium text-gray-900"><?php echo esc_html(date_i18n('M j, Y \a\t g:i A')); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main content -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
            <!-- KPI cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Students -->
                <div onclick="openStatModal('students')" class="bg-white shadow-sm rounded-xl p-5 border border-gray-100 flex flex-col justify-between hover:bg-gray-50 transition-all duration-200 cursor-pointer group">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500 group-hover:text-gray-700">Total Students</p>
                            <p class="mt-2 text-2xl font-semibold text-gray-900">
                                <?php echo number_format_i18n($total_students); ?>
                            </p>
                        </div>
                        <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center">
                            <span class="dashicons dashicons-groups text-blue-600 text-xl"></span>
                        </div>
                    </div>
                    <p class="mt-3 text-xs text-gray-500">
                        Active: <span class="font-medium text-gray-800"><?php echo number_format_i18n($active_students); ?></span>
                        (<?php echo esc_html($enrollment_rate); ?>% of students)
                    </p>
                </div>

                <!-- Staff -->
                <div onclick="openStatModal('staff')" class="bg-white shadow-sm rounded-xl p-5 border border-gray-100 flex flex-col justify-between hover:bg-gray-50 transition-all duration-200 cursor-pointer group">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500 group-hover:text-gray-700">Staff</p>
                            <p class="mt-2 text-2xl font-semibold text-gray-900">
                                <?php echo number_format_i18n($total_staff); ?>
                            </p>
                        </div>
                        <div class="w-10 h-10 rounded-lg bg-emerald-50 flex items-center justify-center">
                            <span class="dashicons dashicons-businessperson text-emerald-600 text-xl"></span>
                        </div>
                    </div>
                    <p class="mt-3 text-xs text-gray-500">
                        Approx. <span class="font-medium text-gray-800"><?php echo esc_html($staff_student_ratio); ?></span>
                        students per staff member.
                    </p>
                </div>

                <!-- Programs & courses -->
                <div onclick="openStatModal('programs')" class="bg-white shadow-sm rounded-xl p-5 border border-gray-100 flex flex-col justify-between hover:bg-gray-50 transition-all duration-200 cursor-pointer group">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500 group-hover:text-gray-700">Programs &amp; Courses</p>
                            <p class="mt-2 text-2xl font-semibold text-gray-900">
                                <?php echo number_format_i18n($total_programs); ?>
                                <span class="text-sm font-normal text-gray-500">programs</span>
                            </p>
                        </div>
                        <div class="w-10 h-10 rounded-lg bg-indigo-50 flex items-center justify-center">
                            <span class="dashicons dashicons-welcome-learn-more text-indigo-600 text-xl"></span>
                        </div>
                    </div>
                    <p class="mt-3 text-xs text-gray-500">
                        <?php echo number_format_i18n($total_courses); ?> active courses across
                        <?php echo number_format_i18n($total_faculties); ?> faculties.
                    </p>
                </div>

                <!-- Applications -->
                <div onclick="openStatModal('apps')" class="bg-white shadow-sm rounded-xl p-5 border border-gray-100 flex flex-col justify-between hover:bg-gray-50 transition-all duration-200 cursor-pointer group">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500 group-hover:text-gray-700">Applications</p>
                            <p class="mt-2 text-2xl font-semibold text-gray-900">
                                <?php echo number_format_i18n($total_applications); ?>
                            </p>
                        </div>
                        <div class="w-10 h-10 rounded-lg bg-amber-50 flex items-center justify-center">
                            <span class="dashicons dashicons-clipboard text-amber-600 text-xl"></span>
                        </div>
                    </div>
                    <p class="mt-3 text-xs text-gray-500">
                        <?php echo number_format_i18n($pending_applications); ?> pending
                        (<?php echo esc_html($pending_applications_pct); ?>% of all applications).
                    </p>
                </div>
            </div>

            <!-- Activity & quick links -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Recent students -->
                <div class="lg:col-span-2 bg-white shadow-sm rounded-xl border border-gray-100">
                    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                        <div>
                            <h2 class="text-sm font-semibold text-gray-900">Recent students</h2>
                            <p class="text-xs text-gray-500">Latest student profiles created in the system.</p>
                        </div>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=nds-students')); ?>"
                           class="text-xs font-medium text-indigo-600 hover:text-indigo-700">
                            View all
                        </a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-5 py-2 text-left font-medium text-gray-500">Student</th>
                                    <th class="px-5 py-2 text-left font-medium text-gray-500">Email</th>
                                    <th class="px-5 py-2 text-left font-medium text-gray-500">Status</th>
                                    <th class="px-5 py-2 text-left font-medium text-gray-500">Created</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                <?php if (!empty($recent_students)) : ?>
                                    <?php foreach ($recent_students as $student) : ?>
                                        <tr>
                                            <td class="px-5 py-2 whitespace-nowrap">
                                                <div class="font-medium text-gray-900">
                                                    <?php
                                                    echo esc_html(trim(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? '')));
                                                    ?>
                                                </div>
                                                <?php if (!empty($student['student_number'])) : ?>
                                                    <div class="text-xs text-gray-500">
                                                        <?php echo esc_html($student['student_number']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-5 py-2 whitespace-nowrap text-gray-700">
                                                <?php echo isset($student['email']) ? esc_html($student['email']) : '&mdash;'; ?>
                                            </td>
                                            <td class="px-5 py-2 whitespace-nowrap">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                                    <?php echo ($student['status'] ?? '') === 'active'
                                                        ? 'bg-emerald-50 text-emerald-700'
                                                        : 'bg-gray-50 text-gray-700'; ?>">
                                                    <?php echo esc_html(ucfirst($student['status'] ?? 'unknown')); ?>
                                                </span>
                                            </td>
                                            <td class="px-5 py-2 whitespace-nowrap text-gray-500 text-xs">
                                                <?php
                                                $created = !empty($student['created_at']) ? strtotime($student['created_at']) : false;
                                                echo $created ? esc_html(date_i18n('M j, Y', $created)) : '&mdash;';
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <tr>
                                        <td colspan="4" class="px-5 py-4 text-center text-sm text-gray-500">
                                            No students found yet.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Recent staff & quick links -->
                <div class="space-y-6">
                    <!-- Recent staff -->
                    <div class="bg-white shadow-sm rounded-xl border border-gray-100">
                        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                            <div>
                                <h2 class="text-sm font-semibold text-gray-900">Recent staff</h2>
                                <p class="text-xs text-gray-500">New or updated staff members.</p>
                            </div>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=nds-staff')); ?>"
                               class="text-xs font-medium text-indigo-600 hover:text-indigo-700">
                                View all
                            </a>
                        </div>
                        <ul class="divide-y divide-gray-100">
                            <?php if (!empty($recent_staff)) : ?>
                                <?php foreach ($recent_staff as $member) : ?>
                                    <li class="px-5 py-3 flex items-center justify-between">
                                        <div>
                                            <p class="text-sm font-medium text-gray-900">
                                                <?php
                                                echo esc_html(trim(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? '')));
                                                ?>
                                            </p>
                                            <p class="text-xs text-gray-500">
                                                <?php echo esc_html($member['role'] ?? 'Staff'); ?>
                                            </p>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <li class="px-5 py-4 text-center text-sm text-gray-500">
                                    No staff records yet.
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>

                    <!-- Quick navigation -->
                    <div class="bg-white shadow-sm rounded-xl border border-gray-100">
                        <div class="px-5 py-4 border-b border-gray-100">
                            <h2 class="text-sm font-semibold text-gray-900">Quick actions</h2>
                            <p class="text-xs text-gray-500">Jump straight into key areas of the academy.</p>
                        </div>
                        <div class="px-5 py-4 grid grid-cols-2 gap-3 text-xs">
                            <a href="<?php echo esc_url(admin_url('admin.php?page=nds-faculties')); ?>"
                               class="flex items-center space-x-2 px-3 py-2 rounded-lg border border-gray-200 hover:border-indigo-400 hover:bg-indigo-50 transition-colors">
                                <span class="dashicons dashicons-networking text-indigo-600 text-base"></span>
                                <span class="font-medium text-gray-800">Faculties</span>
                            </a>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=nds-programs')); ?>"
                               class="flex items-center space-x-2 px-3 py-2 rounded-lg border border-gray-200 hover:border-indigo-400 hover:bg-indigo-50 transition-colors">
                                <span class="dashicons dashicons-admin-page text-indigo-600 text-base"></span>
                                <span class="font-medium text-gray-800">Programs</span>
                            </a>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=nds-courses')); ?>"
                               class="flex items-center space-x-2 px-3 py-2 rounded-lg border border-gray-200 hover:border-indigo-400 hover:bg-indigo-50 transition-colors">
                                <span class="dashicons dashicons-welcome-learn-more text-indigo-600 text-base"></span>
                                <span class="font-medium text-gray-800">Courses</span>
                            </a>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=nds-students')); ?>"
                               class="flex items-center space-x-2 px-3 py-2 rounded-lg border border-gray-200 hover:border-indigo-400 hover:bg-indigo-50 transition-colors">
                                <span class="dashicons dashicons-groups text-indigo-600 text-base"></span>
                                <span class="font-medium text-gray-800">Students</span>
                            </a>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=nds-applications')); ?>"
                               class="flex items-center space-x-2 px-3 py-2 rounded-lg border border-gray-200 hover:border-indigo-400 hover:bg-indigo-50 transition-colors">
                                <span class="dashicons dashicons-clipboard text-indigo-600 text-base"></span>
                                <span class="font-medium text-gray-800">Applications</span>
                            </a>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=nds-calendar')); ?>"
                               class="flex items-center space-x-2 px-3 py-2 rounded-lg border border-gray-200 hover:border-indigo-400 hover:bg-indigo-50 transition-colors">
                                <span class="dashicons dashicons-calendar-alt text-indigo-600 text-base"></span>
                                <span class="font-medium text-gray-800">Calendar</span>
                            </a>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=nds-content')); ?>"
                               class="flex items-center space-x-2 px-3 py-2 rounded-lg border border-gray-200 hover:border-indigo-400 hover:bg-indigo-50 transition-colors">
                                <span class="dashicons dashicons-media-document text-indigo-600 text-base"></span>
                                <span class="font-medium text-gray-800">Content</span>
                            </a>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=nds-migrate-db')); ?>"
                               class="flex items-center space-x-2 px-3 py-2 rounded-lg border border-gray-200 hover:border-yellow-400 hover:bg-yellow-50 transition-colors">
                                <span class="dashicons dashicons-database text-yellow-600 text-base"></span>
                                <span class="font-medium text-gray-800">DB Schema</span>
                            </a>
                        </div>
                    </div>

                    <!-- Recipes/extra -->
                    <div class="bg-white shadow-sm rounded-xl border border-gray-100">
                        <div class="px-5 py-4 flex items-center justify-between">
                            <div>
                                <h2 class="text-sm font-semibold text-gray-900">Recipe library</h2>
                                <p class="text-xs text-gray-500">
                                    <?php echo number_format_i18n($total_recipes); ?> recipes in the academy library.
                                </p>
                            </div>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=nds-recipes')); ?>"
                               class="text-xs font-medium text-indigo-600 hover:text-indigo-700">
                                Manage
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent applications -->
            <div class="bg-white shadow-sm rounded-xl border border-gray-100">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-semibold text-gray-900">Recent applications</h2>
                        <p class="text-xs text-gray-500">Latest applications and where they are in the pipeline.</p>
                    </div>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=nds-applications')); ?>"
                       class="text-xs font-medium text-indigo-600 hover:text-indigo-700">
                        View all
                    </a>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-5 py-2 text-left font-medium text-gray-500">Application #</th>
                                <th class="px-5 py-2 text-left font-medium text-gray-500">Student</th>
                                <th class="px-5 py-2 text-left font-medium text-gray-500">Status</th>
                                <th class="px-5 py-2 text-left font-medium text-gray-500">Submitted</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            <?php if (!empty($recent_applications)) : ?>
                                <?php foreach ($recent_applications as $app) : ?>
                                    <tr>
                                        <td class="px-5 py-2 whitespace-nowrap text-gray-900 font-medium">
                                            <?php echo esc_html($app['application_no']); ?>
                                        </td>
                                        <td class="px-5 py-2 whitespace-nowrap">
                                            <div class="text-gray-900">
                                                <?php
                                                $full_name = trim(($app['first_name'] ?? '') . ' ' . ($app['last_name'] ?? ''));
                                                echo $full_name ? esc_html($full_name) : esc_html__('Unknown student', 'nds-school');
                                                ?>
                                            </div>
                                            <?php if (!empty($app['student_number'])) : ?>
                                                <div class="text-xs text-gray-500">
                                                    <?php echo esc_html($app['student_number']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-5 py-2 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-50 text-blue-700">
                                                <?php echo esc_html(str_replace('_', ' ', ucfirst($app['status'] ?? 'unknown'))); ?>
                                            </span>
                                        </td>
                                        <td class="px-5 py-2 whitespace-nowrap text-gray-500 text-xs">
                                            <?php
                                            $submitted = !empty($app['submitted_at']) ? strtotime($app['submitted_at']) : false;
                                            echo $submitted ? esc_html(date_i18n('M j, Y', $submitted)) : '&mdash;';
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <tr>
                                    <td colspan="4" class="px-5 py-4 text-center text-sm text-gray-500">
                                        No applications found yet.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Drill-down Stat Modal -->
    <div id="drillDownModal" class="hidden" style="position:fixed; inset:0; z-index:999999; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
        <div style="position:fixed; inset:0; background:rgba(0,0,0,0.5);" onclick="closeStatModal()"></div>
        <div style="position:fixed; inset:0; display:flex; align-items:center; justify-content:center; padding:1rem;">
            <div style="background:#fff; border-radius:1rem; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25); width:100%; max-width:42rem; max-height:80vh; display:flex; flex-direction:column; position:relative;">
                <!-- Modal Header -->
                <div style="display:flex; align-items:center; justify-content:space-between; padding:1rem 1.5rem; border-bottom:1px solid #e5e7eb;">
                    <div style="display:flex; align-items:center; gap:0.75rem;">
                        <div id="modalIconBg" style="width:2.5rem; height:2.5rem; border-radius:0.5rem; display:flex; align-items:center; justify-content:center;">
                            <i id="modalIcon" style="font-size:1.25rem;"></i>
                        </div>
                        <div>
                            <h3 id="modalTitle" style="font-size:1.125rem; font-weight:700; color:#111827; margin:0;"></h3>
                            <p id="modalCount" style="font-size:0.875rem; color:#6b7280; margin:0;"></p>
                        </div>
                    </div>
                    <button onclick="closeStatModal()" style="color:#9ca3af; padding:0.5rem; border-radius:0.5rem; border:none; background:none; cursor:pointer;" onmouseover="this.style.color='#4b5563'; this.style.background='#f3f4f6'" onmouseout="this.style.color='#9ca3af'; this.style.background='none'">
                        <i class="fas fa-times" style="font-size:1.25rem;"></i>
                    </button>
                </div>
                <!-- Modal Body -->
                <div style="overflow-y:auto; flex:1; padding:0.5rem;">
                    <table style="width:100%; border-collapse:collapse;">
                        <thead style="background:#f9fafb; position:sticky; top:0; z-index:10;">
                            <tr>
                                <th id="col1Header" style="padding:0.75rem 1rem; text-align:left; font-size:0.75rem; font-weight:500; color:#6b7280; text-transform:uppercase;">Name</th>
                                <th id="col2Header" style="padding:0.75rem 1rem; text-align:left; font-size:0.75rem; font-weight:500; color:#6b7280; text-transform:uppercase;">Details</th>
                            </tr>
                        </thead>
                        <tbody id="modalBody"></tbody>
                    </table>
                </div>
                <!-- Modal Footer -->
                <div style="padding:0.75rem 1.5rem; border-top:1px solid #e5e7eb; background:#f9fafb; border-radius:0 0 1rem 1rem; text-align:right;">
                    <button onclick="closeStatModal()" style="padding:0.5rem 1rem; font-size:0.875rem; font-weight:500; color:#374151; background:#fff; border:1px solid #d1d5db; border-radius:0.5rem; cursor:pointer;" onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background='#fff'">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const statsData = {
            students: <?php echo json_encode($all_students_list); ?>,
            staff: <?php echo json_encode($all_staff_list); ?>,
            programs: <?php echo json_encode($all_programs_list); ?>,
            apps: <?php echo json_encode($all_apps_list); ?>
        };

        const modalConfig = {
            students: {
                title: 'All Students',
                icon: 'fas fa-users',
                iconColor: '#2563eb',
                iconBg: '#eff6ff',
                targetPage: 'nds-students',
                col1: 'Name',
                col2: 'ID'
            },
            staff: {
                title: 'Staff Members',
                icon: 'fas fa-id-badge',
                iconColor: '#059669',
                iconBg: '#ecfdf5',
                targetPage: 'nds-staff',
                col1: 'Name',
                col2: 'Role'
            },
            programs: {
                title: 'Academic Programs',
                icon: 'fas fa-graduation-cap',
                iconColor: '#4f46e5',
                iconBg: '#eef2ff',
                targetPage: 'nds-programs',
                col1: 'Program Name',
                col2: 'Description'
            },
            apps: {
                title: 'Recent Applications',
                icon: 'fas fa-clipboard-list',
                iconColor: '#d97706',
                iconBg: '#fffbeb',
                targetPage: 'nds-applicants',
                col1: 'Applicant',
                col2: 'Course'
            }
        };

        window.openStatModal = function(type) {
            const modal = document.getElementById('drillDownModal');
            const config = modalConfig[type];
            const data = statsData[type];
            
            if (!modal || !config || !data) return;

            document.getElementById('modalTitle').textContent = config.title;
            document.getElementById('modalCount').textContent = data.length + ' item' + (data.length !== 1 ? 's' : '');
            document.getElementById('col1Header').textContent = config.col1;
            document.getElementById('col2Header').textContent = config.col2;
            
            const modalIcon = document.getElementById('modalIcon');
            const modalIconBg = document.getElementById('modalIconBg');
            modalIcon.className = config.icon;
            modalIcon.style.color = config.iconColor;
            modalIconBg.style.backgroundColor = config.iconBg;

            const tbody = document.getElementById('modalBody');
            tbody.innerHTML = '';
            
            data.forEach(item => {
                const row = document.createElement('tr');
                row.style.cssText = 'border-bottom:1px solid #f3f4f6; transition: background 0.15s; cursor: pointer;';
                row.onclick = () => window.location.href = `admin.php?page=${config.targetPage}`;
                row.onmouseover = function() { this.style.background = '#f9fafb'; };
                row.onmouseout = function() { this.style.background = ''; };
                
                let name = '', details = '';
                if (type === 'students') {
                    name = `${item.first_name || ''} ${item.last_name || ''}`;
                    details = item.student_number || 'N/A';
                } else if (type === 'staff') {
                    name = `${item.first_name || ''} ${item.last_name || ''}`;
                    details = item.role || 'Staff';
                } else if (type === 'programs') {
                    name = item.name;
                    details = item.description ? item.description.substring(0, 60) + '...' : 'N/A';
                } else if (type === 'apps') {
                    name = item.full_name || 'N/A';
                    details = item.application_no || 'N/A';
                }

                row.innerHTML = `
                    <td style="padding:0.75rem 1rem; font-size:0.875rem; font-weight:600; color:#111827;">${name}</td>
                    <td style="padding:0.75rem 1rem; font-size:0.875rem; color:#4b5563;">${details}</td>
                `;
                tbody.appendChild(row);
            });

            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        };

        window.closeStatModal = function() {
            const modal = document.getElementById('drillDownModal');
            if (modal) {
                modal.classList.add('hidden');
                document.body.style.overflow = '';
            }
        };
    });
    </script>
    <?php
}
