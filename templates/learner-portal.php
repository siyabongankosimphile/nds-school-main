<?php
if (!defined('ABSPATH')) {
    exit;
}

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html(get_bloginfo('name')); ?> — Student Portal</title>
    <?php wp_head(); ?>
</head>
<body <?php body_class('nds-portal-body'); ?>>
<?php function_exists('wp_body_open') && wp_body_open(); ?>
<?php

global $wpdb;

$modules_table = $wpdb->prefix . 'nds_modules';
$module_columns = $wpdb->get_col("SHOW COLUMNS FROM {$modules_table}");
$module_code_col = in_array('code', $module_columns, true) ? 'code' : (in_array('module_code', $module_columns, true) ? 'module_code' : '');
$module_type_col = in_array('type', $module_columns, true) ? 'type' : '';
$module_code_select = $module_code_col ? "m.{$module_code_col} AS module_code" : "'' AS module_code";
$module_type_select = $module_type_col ? "m.{$module_type_col} AS type" : "'' AS type";

// Resolve current learner from logged-in user
$student_id = (int) nds_portal_get_current_student_id();

// Allow administrators to override student_id via query parameter to view any student's portal
if (current_user_can('manage_options') && isset($_GET['student_id'])) {
    $student_id = intval($_GET['student_id']);
}

if ($student_id <= 0) {
    if (current_user_can('manage_options')) {
        // Admin viewing their own (empty) portal or just landing here
        $full_name = 'Administrator';
        $learner_data = [];
        $enrollments = [];
        $status = 'admin';
        $is_applicant = false;
        $has_no_enrollments = true;
    } else {
        echo '<div class="nds-tailwind-wrapper bg-gray-50 py-16"><div class="max-w-3xl mx-auto bg-white shadow-sm rounded-xl p-8 text-center text-gray-700">We could not find a learner profile linked to your account. Please contact the school.</div></div>';
        return;
    }
} else {
    $learner = nds_get_student($student_id);
    if (!$learner) {
        if (current_user_can('manage_options')) {
            echo '<div class="nds-tailwind-wrapper bg-gray-50 py-16"><div class="max-w-3xl mx-auto bg-white shadow-sm rounded-xl p-8 text-center text-gray-700">Learner with ID ' . $student_id . ' not found.</div></div>';
            return;
        }
        echo '<div class="nds-tailwind-wrapper bg-gray-50 py-16"><div class="max-w-3xl mx-auto bg-white shadow-sm rounded-xl p-8 text-center text-gray-700">Your learner profile could not be loaded. Please contact the school.</div></div>';
        return;
    }

    $learner_data = (array) $learner;
    $full_name    = trim(($learner_data['first_name'] ?? '') . ' ' . ($learner_data['last_name'] ?? ''));
}

// Enrollments (used for multiple sections)
$enrollments = $wpdb->get_results(
    $wpdb->prepare(
        "
        SELECT e.*, c.name as course_name, c.code as course_code,
               p.id as program_id, p.name as program_name,
               ay.year_name, s.semester_name
        FROM {$wpdb->prefix}nds_student_enrollments e
        LEFT JOIN {$wpdb->prefix}nds_courses c ON e.course_id = c.id
        LEFT JOIN {$wpdb->prefix}nds_programs p ON c.program_id = p.id
        LEFT JOIN {$wpdb->prefix}nds_academic_years ay ON e.academic_year_id = ay.id
        LEFT JOIN {$wpdb->prefix}nds_semesters s ON e.semester_id = s.id
        WHERE e.student_id = %d
        ORDER BY e.created_at DESC
        ",
        $student_id
    ),
    ARRAY_A
);

// Recent enrollments (for Overview tab)
$recent_enrollments = array_slice($enrollments, 0, 5);

// Faculty
$faculty = null;
if (!empty($learner_data['faculty_id'])) {
    $faculty = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}nds_faculties WHERE id = %d",
            $learner_data['faculty_id']
        ),
        ARRAY_A
    );
}

// Average grade
$avg_grade = $wpdb->get_var(
    $wpdb->prepare(
        "SELECT AVG(final_percentage) FROM {$wpdb->prefix}nds_student_enrollments 
         WHERE student_id = %d AND final_percentage IS NOT NULL",
        $student_id
    )
);

// Certificates count (placeholder - will be implemented when certificates table exists)
$certificates_count = 0;

// Latest accepted qualification linked to this user.
$latest_application = null;
$status = $learner_data['status'] ?? 'prospect';
$is_applicant = in_array($status, ['prospect', 'applicant'], true);
// Also show application for active students if they have no enrollments (to display course_name)
$has_no_enrollments = empty($enrollments);
$latest_application = function_exists('nds_portal_get_latest_application_for_current_user')
    ? nds_portal_get_latest_application_for_current_user(array('accepted', 'enrolled'))
    : null;

// Registration panel visibility: show only for latest accepted qualification.
$registration_allowed_statuses = array('accepted', 'enrolled');
$registration_application = null;

if (!empty($latest_application)
    && in_array(($latest_application['status'] ?? ''), $registration_allowed_statuses, true)
    && !empty($latest_application['course_id'])) {
    $registration_application = $latest_application;
} else {
    $apps_table  = $wpdb->prefix . 'nds_applications';
    $forms_table = $wpdb->prefix . 'nds_application_forms';
    $wp_user_id = get_current_user_id();

    if ($student_id > 0) {
        $placeholders = implode(',', array_fill(0, count($registration_allowed_statuses), '%s'));
        $sql = "
            SELECT a.id, a.application_no, a.status, a.submitted_at,
                   COALESCE(af.course_id, a.course_id) AS course_id,
                   a.program_id,
                   af.course_name, af.level
            FROM {$apps_table} a
            LEFT JOIN {$forms_table} af ON af.application_id = a.id
            WHERE (a.student_id = %d OR a.wp_user_id = %d)
              AND a.status IN ({$placeholders})
            ORDER BY a.submitted_at DESC
            LIMIT 1
        ";
        $params = array_merge(array($student_id, $wp_user_id), $registration_allowed_statuses);
        $registration_application = $wpdb->get_row($wpdb->prepare($sql, $params), ARRAY_A);
    } else {
        $placeholders = implode(',', array_fill(0, count($registration_allowed_statuses), '%s'));
        $sql = "
            SELECT a.id, a.application_no, a.status, a.submitted_at,
                   COALESCE(af.course_id, a.course_id) AS course_id,
                   a.program_id,
                   af.course_name, af.level
            FROM {$apps_table} a
            LEFT JOIN {$forms_table} af ON af.application_id = a.id
            WHERE a.wp_user_id = %d
              AND a.status IN ({$placeholders})
            ORDER BY a.submitted_at DESC
            LIMIT 1
        ";
        $params = array_merge(array($wp_user_id), $registration_allowed_statuses);
        $registration_application = $wpdb->get_row($wpdb->prepare($sql, $params), ARRAY_A);
    }
}

// --- Resolve course_id from course_name if missing ---
if (!empty($registration_application) && empty($registration_application['course_id'])) {
    $reg_course_name = trim($registration_application['course_name'] ?? '');
    if ($reg_course_name !== '') {
        // Try matching by exact name first, then strip NQF suffix
        $resolved_course_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}nds_courses WHERE name = %s AND status = 'active' LIMIT 1",
            $reg_course_name
        ));
        if (!$resolved_course_id) {
            $name_clean = preg_replace('/\s*\(NQF\s+\d+\)\s*$/i', '', $reg_course_name);
            $resolved_course_id = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}nds_courses WHERE name = %s AND status = 'active' LIMIT 1",
                $name_clean
            ));
        }
        if (!$resolved_course_id) {
            // Partial match
            $resolved_course_id = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}nds_courses WHERE name LIKE %s AND status = 'active' ORDER BY id DESC LIMIT 1",
                '%' . $wpdb->esc_like($reg_course_name) . '%'
            ));
        }
        if ($resolved_course_id > 0) {
            $registration_application['course_id'] = $resolved_course_id;
        }
    }
}
// Also resolve program_id from course if missing
if (!empty($registration_application) && empty($registration_application['program_id']) && !empty($registration_application['course_id'])) {
    $registration_application['program_id'] = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT program_id FROM {$wpdb->prefix}nds_courses WHERE id = %d",
        (int) $registration_application['course_id']
    ));
}
$can_show_registration_panel = !empty($registration_application)
    && (!empty($registration_application['course_id']) || !empty($registration_application['course_name']));
$registration_block_reason = '';

if ($can_show_registration_panel && function_exists('nds_portal_get_active_qualification_enrollment')) {
    $active_other_enrollment = nds_portal_get_active_qualification_enrollment(
        $student_id,
        (int) ($registration_application['course_id'] ?? 0)
    );

    if (!empty($active_other_enrollment)) {
        $active_name = !empty($active_other_enrollment['program_name'])
            ? $active_other_enrollment['program_name']
            : (!empty($active_other_enrollment['course_name']) ? $active_other_enrollment['course_name'] : 'your active qualification');

        $registration_block_reason = sprintf(
            'You are currently enrolled in %s. You can enroll in another qualification after its period ends.',
            $active_name
        );
        $can_show_registration_panel = false;
    }
}

$registration_modules = array();
$registration_selected_module_ids = array();
if ($can_show_registration_panel) {
    $registration_course_id = (int) ($registration_application['course_id'] ?? 0);
    $registration_program_id = (int) ($registration_application['program_id'] ?? 0);

    // Fetch modules linked directly to the course
    if ($registration_course_id > 0) {
        $registration_modules = $wpdb->get_results($wpdb->prepare(
            "SELECT m.id, {$module_code_select}, m.name, {$module_type_select}
             FROM {$modules_table} m
             WHERE m.course_id = %d
             ORDER BY m.name ASC",
            $registration_course_id
        ), ARRAY_A);
    }

    // Fallback: if no direct modules, pull modules from ALL courses in the program
    if (empty($registration_modules) && $registration_program_id > 0) {
        $registration_modules = $wpdb->get_results($wpdb->prepare(
            "SELECT m.id, {$module_code_select}, m.name, {$module_type_select}
             FROM {$modules_table} m
             INNER JOIN {$wpdb->prefix}nds_courses c ON c.id = m.course_id
             WHERE c.program_id = %d AND c.status = 'active'
             ORDER BY m.name ASC",
            $registration_program_id
        ), ARRAY_A);
    }

    $student_modules_table = $wpdb->prefix . 'nds_student_modules';
    $student_modules_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $student_modules_table));
    if (!empty($student_modules_exists)) {
        $active_year_id = (int) $wpdb->get_var("SELECT id FROM {$wpdb->prefix}nds_academic_years WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
        $active_semester_id = $active_year_id ? (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}nds_semesters WHERE academic_year_id = %d AND is_active = 1 ORDER BY id DESC LIMIT 1",
            $active_year_id
        )) : 0;

        if ($active_year_id > 0 && $active_semester_id > 0) {
            $registration_selected_module_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT module_id
                 FROM {$student_modules_table}
                 WHERE student_id = %d AND course_id = %d AND academic_year_id = %d AND semester_id = %d AND status = 'enrolled'",
                $student_id,
                $registration_course_id,
                $active_year_id,
                $active_semester_id
            ));
            $registration_selected_module_ids = array_map('intval', $registration_selected_module_ids ?: array());
        }
    }
}

// Registered modules in active term, plus module-matched learning content.
$active_year_id = (int) $wpdb->get_var("SELECT id FROM {$wpdb->prefix}nds_academic_years WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
$active_semester_id = $active_year_id ? (int) $wpdb->get_var($wpdb->prepare(
    "SELECT id FROM {$wpdb->prefix}nds_semesters WHERE academic_year_id = %d AND is_active = 1 ORDER BY id DESC LIMIT 1",
    $active_year_id
)) : 0;

$learner_registered_modules = array();
$module_content_by_module = array();
$module_assessments_by_module = array();

if ($student_id > 0) {
    if ($active_year_id > 0 && $active_semester_id > 0) {
        $learner_registered_modules = $wpdb->get_results($wpdb->prepare(
            "SELECT sm.module_id, sm.course_id,
                    m.name AS module_name, {$module_code_select},
                    c.name AS course_name, c.code AS course_code,
                    p.name AS program_name
             FROM {$wpdb->prefix}nds_student_modules sm
             INNER JOIN {$modules_table} m ON m.id = sm.module_id
             LEFT JOIN {$wpdb->prefix}nds_courses c ON c.id = sm.course_id
             LEFT JOIN {$wpdb->prefix}nds_programs p ON p.id = c.program_id
             WHERE sm.student_id = %d
               AND sm.academic_year_id = %d
               AND sm.semester_id = %d
               AND sm.status = 'enrolled'
             ORDER BY p.name ASC, c.name ASC, m.name ASC",
            $student_id,
            $active_year_id,
            $active_semester_id
        ), ARRAY_A);
    }

    if (empty($learner_registered_modules)) {
        $learner_registered_modules = $wpdb->get_results($wpdb->prepare(
            "SELECT sm.module_id, sm.course_id,
                    m.name AS module_name, {$module_code_select},
                    c.name AS course_name, c.code AS course_code,
                    p.name AS program_name
             FROM {$wpdb->prefix}nds_student_modules sm
             INNER JOIN {$modules_table} m ON m.id = sm.module_id
             LEFT JOIN {$wpdb->prefix}nds_courses c ON c.id = sm.course_id
             LEFT JOIN {$wpdb->prefix}nds_programs p ON p.id = c.program_id
             WHERE sm.student_id = %d
               AND sm.status = 'enrolled'
             ORDER BY sm.updated_at DESC, p.name ASC, c.name ASC, m.name ASC",
            $student_id
        ), ARRAY_A);
    }

    $module_ids_for_feed = array_values(array_unique(array_map('intval', wp_list_pluck($learner_registered_modules, 'module_id'))));
    if (!empty($module_ids_for_feed)) {
        $placeholders = implode(',', array_fill(0, count($module_ids_for_feed), '%d'));

        $module_content_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT lc.id, lc.module_id, lc.content_type, lc.title, lc.description,
                    lc.resource_url, lc.attachment_url, lc.due_date, lc.quiz_data, lc.created_at,
                    m.name AS module_name
             FROM {$wpdb->prefix}nds_lecturer_content lc
             INNER JOIN {$wpdb->prefix}nds_modules m ON m.id = lc.module_id
             WHERE lc.module_id IN ({$placeholders})
               AND lc.is_visible = 1
               AND lc.status = 'published'
               AND (lc.access_start IS NULL OR lc.access_start <= NOW())
               AND (lc.access_end IS NULL OR lc.access_end >= NOW())
             ORDER BY lc.created_at DESC",
            $module_ids_for_feed
        ), ARRAY_A);

        foreach ($module_content_rows as $content_row) {
            $mid = (int) ($content_row['module_id'] ?? 0);
            if (!isset($module_content_by_module[$mid])) {
                $module_content_by_module[$mid] = array();
            }
            $module_content_by_module[$mid][] = $content_row;
        }

        $module_assessment_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT a.id, a.module_id, a.assessment_type, a.title, a.instructions,
                    a.due_date, a.max_grade, a.attempts_allowed, a.status,
                    m.name AS module_name
             FROM {$wpdb->prefix}nds_assessments a
             INNER JOIN {$wpdb->prefix}nds_modules m ON m.id = a.module_id
             WHERE a.module_id IN ({$placeholders})
               AND a.status = 'published'
             ORDER BY a.due_date ASC, a.created_at DESC",
            $module_ids_for_feed
        ), ARRAY_A);

        foreach ($module_assessment_rows as $assessment_row) {
            $mid = (int) ($assessment_row['module_id'] ?? 0);
            if (!isset($module_assessments_by_module[$mid])) {
                $module_assessments_by_module[$mid] = array();
            }
            $module_assessments_by_module[$mid][] = $assessment_row;
        }
    }
}

// Learner-facing programme name (what the learner actually applied/enrolled for)
$display_program_name = '';
if (!empty($enrollments)) {
    foreach ($enrollments as $row) {
        if (!empty($row['program_name'])) {
            $display_program_name = $row['program_name'];
            break;
        }
    }
}
if (!$display_program_name && !empty($latest_application)) {
    if (!empty($latest_application['course_name'])) {
        $display_program_name = $latest_application['course_name'];
        if (!empty($latest_application['level'])) {
            $display_program_name .= ' (NQF ' . $latest_application['level'] . ')';
        }
    }
}

// Counts for quick stats
$enrolled_courses_count = count($enrollments);
$applied_courses_count  = 0;
if ($is_applicant && !empty($latest_application)) {
    // For now we show the latest application only; this can be expanded to count all active applications
    $applied_courses_count = 1;
}

// Current tab (frontend-safe, no admin links)
$current_tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'overview';
$valid_tabs  = $is_applicant
    ? array('overview')
    : array('overview', 'courses', 'timetable', 'finances', 'results', 'graduation', 'certificates', 'documents', 'activity');
if (!in_array($current_tab, $valid_tabs, true)) {
    $current_tab = 'overview';
}

// Helper to build tab URLs on the same /portal/ URL
function nds_learner_portal_tab_url($tab)
{
    $base = home_url('/portal/');
    if ($tab === 'overview') {
        return $base;
    }
    return add_query_arg('tab', $tab, $base);
}

// Fetch unread notifications for current student
$unread_notifications = nds_get_unread_notifications($student_id);
$unread_count = count($unread_notifications);
?>

<div class="nds-tailwind-wrapper bg-gray-50 min-h-screen nds-portal-offset" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
    <?php
    // Show success modal if redirected from application form and we have an application record
    $show_success_modal = isset($_GET['application'], $_GET['id'])
        && $_GET['application'] === 'success'
        && !empty($latest_application)
        && intval($_GET['id']) === intval($latest_application['id'] ?? 0);
    ?>
    <?php if ($show_success_modal && !empty($latest_application)) : ?>
        <div
            id="nds-app-success-modal"
            class="fixed inset-0 z-40 flex items-center justify-center px-4"
            style="background-color: rgba(15, 23, 42, 0.35); backdrop-filter: blur(6px);"
        >
            <!-- Compact centered dialog, with a hard max-width so it never spans the full viewport -->
            <div
                class="bg-white rounded-2xl shadow-2xl p-6 sm:p-7 md:p-8"
                style="max-width: 640px; width: 100%; margin: 1.5rem auto;"
            >
                <div class=" items-center justify-between mb-4">
                    <h2 class="text-lg sm:text-xl font-semibold text-emerald-800 flex items-center gap-2">
                        <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-emerald-100 text-emerald-700">
                            ✓
                        </span>
                        Application submitted successfully
                    </h2>
                </div>
                <div class="space-y-4 text-sm text-gray-800">
                    <div class="bg-emerald-50 border border-emerald-100 rounded-lg p-4">
                        <div class="text-xs font-semibold tracking-wide text-emerald-700 uppercase mb-1">Application details</div>
                        <p><span class="font-medium">Application number:</span>
                            <span class="font-mono text-emerald-900">
                                <?php echo esc_html($latest_application['application_no'] ?? ''); ?>
                            </span>
                        </p>
                        <p><span class="font-medium">Course:</span>
                            <?php echo esc_html($latest_application['course_name'] ?? ''); ?>
                            <?php if (!empty($latest_application['level'])) : ?>
                                (NQF <?php echo esc_html($latest_application['level']); ?>)
                            <?php endif; ?>
                        </p>
                        <p><span class="font-medium">Status:</span>
                            <?php
                            $status_label = isset($latest_application['status'])
                                ? str_replace('_', ' ', $latest_application['status'])
                                : 'submitted';
                            echo esc_html(ucfirst($status_label));
                            ?>
                        </p>
                    </div>
                    <p class="text-gray-700">
                        Your application has been received and is being reviewed. You will be contacted via email with
                        updates on your application status.
                    </p>
                    <p class="text-xs text-gray-500">
                        Note: Please keep your application number
                        <span class="font-mono">
                            <?php echo esc_html($latest_application['application_no'] ?? ''); ?>
                        </span>
                        for your records.
                    </p>
                </div>
                <div class="mt-6 flex justify-end">
                    <button
                        id="nds-app-success-close"
                        type="button"
                        class="inline-flex items-center justify-center px-4 py-2 rounded-full text-sm font-semibold leading-snug transition-colors"
                        style="background-color:#2563eb;color:#ffffff;"
                    >
                        Go to dashboard
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <!-- Header -->
    <?php if (current_user_can('manage_options')) : ?>
        <div class="bg-amber-50 border-b border-amber-200 py-2 px-4 shadow-sm relative z-50">
            <div class="max-w-7xl mx-auto flex items-center justify-between text-amber-800 text-sm font-medium">
                <div class="flex items-center">
                    <i class="fas fa-user-shield mr-2"></i>
                    <span>Viewing as Administrator</span>
                    <?php if ($student_id > 0 && !empty($learner_data)): ?>
                        <span class="mx-2">•</span>
                        <span>Viewing profile: <strong><?php echo esc_html($full_name); ?></strong> (ID: <?php echo $student_id; ?>)</span>
                    <?php endif; ?>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="<?php echo admin_url('admin.php?page=nds-all-learners'); ?>" class="hover:underline">
                        Return to Admin Dashboard
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-6">
                <div class="flex items-center space-x-4">
                    <div class="w-12 h-12 bg-blue-600 rounded-xl flex items-center justify-center shadow-lg">
                        <i class="fas fa-user text-white text-2xl"></i>
                    </div>
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">
                            <?php echo esc_html($full_name ?: 'Learner'); ?>
                        </h1>
                        <p class="text-sm text-gray-600 mt-1">
                            <?php if (!empty($learner_data['student_number'])) : ?>
                                Student #<?php echo esc_html($learner_data['student_number']); ?>
                            <?php endif; ?>
                            <?php if ($display_program_name) : ?>
                                <?php echo !empty($learner_data['student_number']) ? ' • ' : ''; ?>Programme: <?php echo esc_html($display_program_name); ?>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                <div class="flex items-center space-x-3">
                    <!-- Notification Bell -->
                    <div class="relative mr-2" id="nds-notification-wrapper">
                        <button id="nds-notification-bell" class="relative p-2.5 text-gray-500 hover:text-blue-600 hover:bg-blue-50 rounded-xl transition-all duration-300 group">
                            <i class="fas fa-bell text-xl"></i>
                            <?php if ($unread_count > 0) : ?>
                                <span id="nds-notification-badge" class="absolute top-0 right-0 flex h-4 w-4 translate-x-1/3 -translate-y-1/3">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                                    <span class="relative inline-flex items-center justify-center rounded-full h-4 w-4 bg-red-600 text-[9px] font-bold text-white shadow-sm ring-1 ring-white">
                                        <?php echo $unread_count; ?>
                                    </span>
                                </span>
                            <?php endif; ?>
                        </button>

                        <!-- Notification Dropdown -->
                        <div id="nds-notification-dropdown" class="hidden absolute right-0 mt-3 w-85 sm:w-96 bg-white rounded-2xl shadow-[0_20px_50px_rgba(0,0,0,0.15)] border border-gray-100 z-[100] transform origin-top-right transition-all duration-300">
                            <div class="p-5 border-b border-gray-100 flex justify-between items-center bg-gray-50/50 rounded-t-2xl">
                                <div>
                                    <h3 class="text-base font-bold text-gray-900">Notifications</h3>
                                    <?php if ($unread_count > 0) : ?>
                                        <p class="text-xs text-gray-500 mt-0.5">You have <?php echo $unread_count; ?> unread messages</p>
                                    <?php endif; ?>
                                </div>
                                <?php if ($unread_count > 0) : ?>
                                    <button id="nds-mark-all-read" class="text-xs text-blue-600 hover:text-blue-700 font-bold px-3 py-1.5 bg-blue-50 rounded-lg transition-colors">Mark all read</button>
                                <?php endif; ?>
                            </div>

                            <div class="max-h-[400px] overflow-y-auto" id="nds-notification-list">
                                <?php if ($unread_count > 0) : ?>
                                    <?php foreach ($unread_notifications as $notif) : 
                                        $icon = 'fa-info-circle text-blue-500 bg-blue-50';
                                        if ($notif['type'] === 'timetable' || $notif['type'] === 'calendar') {
                                            $icon = 'fa-calendar-alt text-indigo-500 bg-indigo-50';
                                        } elseif ($notif['type'] === 'warning') {
                                            $icon = 'fa-exclamation-triangle text-amber-500 bg-amber-50';
                                        } elseif ($notif['type'] === 'success') {
                                            $icon = 'fa-check-circle text-emerald-500 bg-emerald-50';
                                        } elseif ($notif['type'] === 'error') {
                                            $icon = 'fa-times-circle text-rose-500 bg-rose-50';
                                        }
                                    ?>
                                        <div class="p-5 border-b border-gray-50 hover:bg-blue-50/30 transition-all relative group cursor-pointer" data-id="<?php echo $notif['id']; ?>">
                                            <div class="flex items-start gap-4">
                                                <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 <?php echo $icon; ?> group-hover:scale-110 transition-transform duration-300 shadow-sm">
                                                    <i class="fas <?php echo explode(' ', $icon)[0]; ?> text-lg"></i>
                                                </div>
                                                <div class="flex-1 min-w-0">
                                                    <div class="flex justify-between items-start mb-1">
                                                        <p class="text-sm font-bold text-gray-900 leading-snug truncate pr-6 mt-0.5"><?php echo esc_html($notif['title']); ?></p>
                                                        <span class="text-[10px] text-gray-400 font-medium whitespace-nowrap mt-1"><?php echo human_time_diff(strtotime($notif['created_at']), current_time('timestamp')); ?></span>
                                                    </div>
                                                    <p class="text-xs text-gray-600 leading-relaxed line-clamp-2"><?php echo esc_html($notif['message']); ?></p>
                                                </div>
                                            </div>
                                            <button class="nds-mark-read absolute top-5 right-5 w-7 h-7 flex items-center justify-center rounded-lg text-gray-300 hover:text-blue-600 hover:bg-white hover:shadow-sm opacity-0 group-hover:opacity-100 transition-all" title="Mark as read">
                                                <i class="fas fa-check text-xs"></i>
                                            </button>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <div class="p-12 text-center">
                                        <div class="w-20 h-20 bg-gray-50 rounded-3xl flex items-center justify-center mx-auto mb-5 shadow-inner">
                                            <i class="fas fa-bell-slash text-gray-300 text-3xl"></i>
                                        </div>
                                        <h4 class="text-base font-bold text-gray-800">All caught up!</h4>
                                        <p class="text-sm text-gray-500 mt-2">No new notifications for you right now.</p>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="p-4 text-center border-t border-gray-50 bg-gray-50/30 rounded-b-2xl">
                                <a href="<?php echo esc_url(nds_learner_portal_tab_url('activity')); ?>" class="group inline-flex items-center text-xs font-bold text-gray-500 hover:text-blue-600 transition-colors">
                                    <span>View all portal activity</span>
                                    <i class="fas fa-chevron-right ml-1.5 transform group-hover:translate-x-1 transition-transform"></i>
                                </a>
                            </div>
                        </div>
                    </div>

                    <a href="<?php echo esc_url(home_url('/')); ?>"
                       class="inline-flex items-center px-4 py-2 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 text-sm font-medium shadow-sm transition-all duration-200">
                        <i class="fas fa-globe mr-2"></i>
                        Go to website
                    </a>
                    <a href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>"
                       class="inline-flex items-center px-4 py-2 rounded-lg bg-red-50 hover:bg-red-100 text-red-700 text-sm font-medium shadow-sm transition-all duration-200">
                        <i class="fas fa-sign-out-alt mr-2"></i>
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <!-- Quick Stats -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
            <div class="bg-white shadow-sm rounded-xl p-5 border border-gray-100 flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">
                            <?php echo $is_applicant ? 'Applied Courses' : 'Enrolled Courses'; ?>
                        </p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900">
                            <?php echo $is_applicant ? $applied_courses_count : $enrolled_courses_count; ?>
                        </p>
                    </div>
                    <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center">
                        <i class="fas fa-book text-blue-600 text-xl"></i>
                    </div>
                </div>
                <p class="mt-3 text-xs text-gray-500">
                    <?php echo $is_applicant ? 'Courses you have applied for.' : 'Courses you are currently enrolled in.'; ?>
                </p>
            </div>

            <div class="bg-white shadow-sm rounded-xl p-5 border border-gray-100 flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Status</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900">
                            <span class="px-2 py-1 rounded-full text-xs font-medium
                                <?php
                                echo $status === 'active'
                                    ? 'bg-green-100 text-green-800'
                                    : ($status === 'prospect'
                                        ? 'bg-yellow-100 text-yellow-800'
                                        : 'bg-gray-100 text-gray-800');
                                ?>">
                                <?php echo esc_html(ucfirst($status)); ?>
                            </span>
                        </p>
                    </div>
                    <div class="w-10 h-10 rounded-lg bg-emerald-50 flex items-center justify-center">
                        <i class="fas fa-user-check text-emerald-600 text-xl"></i>
                    </div>
                </div>
                <?php if (!empty($latest_application)) : ?>
                <button type="button" id="nds-status-panel-toggle"
                    class="mt-3 flex items-center gap-1 text-xs font-medium text-emerald-600 hover:text-emerald-800 transition-colors">
                    <i class="fas fa-chevron-down text-xs transition-transform duration-200" id="nds-status-panel-chevron"></i>
                    Check your status &amp; registration
                </button>
                <?php else : ?>
                <p class="mt-3 text-xs text-gray-500">Your current learner status.</p>
                <?php endif; ?>
            </div>

            <div class="bg-white shadow-sm rounded-xl p-5 border border-gray-100 flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Average Grade</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900">
                            <?php echo $avg_grade ? number_format((float) $avg_grade, 1) . '%' : 'N/A'; ?>
                        </p>
                    </div>
                    <div class="w-10 h-10 rounded-lg bg-purple-50 flex items-center justify-center">
                        <i class="fas fa-chart-line text-purple-600 text-xl"></i>
                    </div>
                </div>
                <p class="mt-3 text-xs text-gray-500">
                    <?php echo $avg_grade ? 'Your overall academic performance.' : 'No grades recorded yet.'; ?>
                </p>
            </div>

            <div class="bg-white shadow-sm rounded-xl p-5 border border-gray-100 flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Certificates</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900">
                            <?php echo $certificates_count; ?>
                        </p>
                    </div>
                    <div class="w-10 h-10 rounded-lg bg-amber-50 flex items-center justify-center">
                        <i class="fas fa-certificate text-amber-600 text-xl"></i>
                    </div>
                </div>
                <p class="mt-3 text-xs text-gray-500">
                    Certificates earned.
                </p>
            </div>
        </div>

        <?php if (!empty($latest_application)) : ?>
        <!-- Status & Registration Dropdown Panel -->
        <div id="nds-status-panel" class="hidden mb-6 bg-emerald-50 rounded-xl border border-emerald-100 p-5">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Application Status -->
                <div>
                    <div class="text-xs font-semibold tracking-wide text-emerald-700 uppercase mb-1">Application Status</div>
                    <div class="text-lg font-semibold text-emerald-900">
                        <?php
                        $status_label = isset($latest_application['status'])
                            ? str_replace('_', ' ', $latest_application['status'])
                            : 'submitted';
                        echo esc_html(ucfirst($status_label));
                        ?>
                    </div>
                    <div class="mt-1 text-sm text-emerald-800">
                        <?php if (!empty($latest_application['course_name'])) : ?>
                            Applied for: <?php echo esc_html($latest_application['course_name']); ?>
                            <?php if (!empty($latest_application['level'])) : ?>
                                (NQF <?php echo esc_html($latest_application['level']); ?>)
                            <?php endif; ?>
                        <?php else : ?>
                            Your course choice will appear here.
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($latest_application['application_no'])) : ?>
                        <div class="mt-2 text-xs text-emerald-900/80">
                            Application number: <span class="font-mono"><?php echo esc_html($latest_application['application_no']); ?></span>
                        </div>
                    <?php endif; ?>
                    <p class="mt-3 text-xs text-emerald-900/80">While your application is being reviewed, some dashboard features may be limited.</p>
                </div>

                <!-- Registration -->
                <div>
                    <?php if ($can_show_registration_panel) : ?>
                        <div class="rounded-lg border border-emerald-200 bg-white p-4" id="nds-registration-panel"
                             data-course-id="<?php echo esc_attr((int) $registration_application['course_id']); ?>"
                             data-nonce="<?php echo esc_attr(wp_create_nonce('nds_portal_nonce')); ?>">
                            <div class="text-xs font-semibold tracking-wide text-emerald-700 uppercase mb-2">Registration</div>
                            <div class="flex flex-col sm:flex-row gap-2 mb-3">
                                <select id="nds-registration-action" class="border border-gray-300 rounded-lg px-3 py-2 text-sm flex-1">
                                    <option value="">Registration actions</option>
                                    <option value="submit_registration">Submit registration</option>
                                    <option value="download_proof">Download proof of registration</option>
                                    <option value="add_module">Add module</option>
                                    <option value="cancel_module">Cancel module</option>
                                </select>
                                <button id="nds-registration-run" type="button" class="inline-flex items-center justify-center px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-700">Apply</button>
                            </div>
                            <div id="nds-registration-module-wrap" class="hidden">
                                <div class="text-xs text-gray-600 mb-2">Modules for your accepted course:</div>
                                <?php if (!empty($registration_modules)) : ?>
                                    <label class="inline-flex items-center text-xs text-gray-700 mb-2">
                                        <input type="checkbox" id="nds-modules-select-all" class="mr-2">Select all modules
                                    </label>
                                    <div class="max-h-40 overflow-y-auto space-y-2 pr-1" id="nds-registration-modules">
                                        <?php foreach ($registration_modules as $module_row) : ?>
                                            <?php
                                            $module_id = (int) ($module_row['id'] ?? 0);
                                            $checked = in_array($module_id, $registration_selected_module_ids, true);
                                            ?>
                                            <label class="flex items-center gap-2 text-sm text-gray-800">
                                                <input type="checkbox" class="nds-module-pick" value="<?php echo esc_attr($module_id); ?>" <?php checked($checked); ?>>
                                                <span><?php echo esc_html($module_row['name'] ?? 'Module'); ?><?php if (!empty($module_row['module_code'])) : ?> (<?php echo esc_html($module_row['module_code']); ?>)<?php endif; ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else : ?>
                                    <p class="text-sm text-gray-600">No modules are configured for this accepted course yet.</p>
                                <?php endif; ?>
                            </div>
                            <div id="nds-registration-feedback" class="mt-3 text-sm" style="display:none;"></div>
                        </div>
                    <?php elseif (!empty($registration_block_reason)) : ?>
                        <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                            <?php echo esc_html($registration_block_reason); ?>
                        </div>
                    <?php else : ?>
                        <p class="text-sm text-emerald-700">Registration will be available once your application is accepted.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <script>
        (function () {
            var btn = document.getElementById('nds-status-panel-toggle');
            var panel = document.getElementById('nds-status-panel');
            var chevron = document.getElementById('nds-status-panel-chevron');
            if (btn && panel) {
                btn.addEventListener('click', function () {
                    var hidden = panel.classList.toggle('hidden');
                    chevron.style.transform = hidden ? '' : 'rotate(180deg)';
                });
            }
        })();
        </script>
        <?php endif; ?>

        <!-- Tabs -->
        <div class="bg-white shadow-sm rounded-xl border border-gray-100 mb-6">
            <div class="border-b border-gray-200">
                <nav class="flex -mb-px overflow-x-auto" aria-label="Tabs">
                    <?php
                    $tabs = array(
                        'overview'     => array('icon' => 'fa-home', 'label' => 'Overview'),
                        'courses'      => array('icon' => 'fa-book', 'label' => 'Courses'),
                        'timetable'   => array('icon' => 'fa-calendar-alt', 'label' => 'Timetable'),
                        'finances'    => array('icon' => 'fa-dollar-sign', 'label' => '$ Finances'),
                        'results'     => array('icon' => 'fa-chart-bar', 'label' => 'Results'),
                        'graduation'  => array('icon' => 'fa-graduation-cap', 'label' => 'Graduation'),
                        'certificates' => array('icon' => 'fa-certificate', 'label' => 'Certificates'),
                        'documents'   => array('icon' => 'fa-file', 'label' => 'Documents'),
                        'activity'    => array('icon' => 'fa-history', 'label' => 'Activity'),
                    );

                    // Applicants only see a simplified overview (no extra tabs)
                    if ($is_applicant) {
                        $tabs = array(
                            'overview' => $tabs['overview'],
                        );
                    }

                    foreach ($tabs as $tab_key => $tab_info) :
                        $is_active = ($current_tab === $tab_key);
                        $url       = nds_learner_portal_tab_url($tab_key);
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
                // Set learner_id for partials (they expect $_GET['id'], but we'll override)
                $_GET['id'] = $student_id;

                // Build course_modules and timeline data (used by overview and courses tabs)
                $course_modules = array();
                foreach ($learner_registered_modules as $lrm_row) {
                    $lrm_mid = (int) ($lrm_row['module_id'] ?? 0);
                    if ($lrm_mid <= 0) { continue; }
                    if (!isset($course_modules[$lrm_mid])) {
                        $course_modules[$lrm_mid] = array(
                            'module_id'       => $lrm_mid,
                            'module_name'     => $lrm_row['module_name'] ?? 'Module',
                            'module_code'     => $lrm_row['module_code'] ?? '',
                            'course_name'     => $lrm_row['course_name'] ?? 'Course',
                            'program_name'    => $lrm_row['program_name'] ?? '',
                            'content_rows'    => $module_content_by_module[$lrm_mid] ?? array(),
                            'assessment_rows' => $module_assessments_by_module[$lrm_mid] ?? array(),
                        );
                    }
                }

                $courses_tab_url    = nds_learner_portal_tab_url('courses');
                $selected_module_id = isset($_GET['module_id']) ? (int) $_GET['module_id'] : 0;
                $selected_module    = ($selected_module_id > 0 && isset($course_modules[$selected_module_id]))
                    ? $course_modules[$selected_module_id] : null;

                $timeline_query = isset($_GET['timeline_q'])     ? sanitize_text_field(wp_unslash($_GET['timeline_q']))    : '';
                $timeline_range = isset($_GET['timeline_range']) ? sanitize_key(wp_unslash($_GET['timeline_range']))       : 'next7';
                $timeline_sort  = isset($_GET['timeline_sort'])  ? sanitize_key(wp_unslash($_GET['timeline_sort']))        : 'asc';
                if (!in_array($timeline_range, array('next7', 'next30', 'all'), true)) { $timeline_range = 'next7'; }
                if (!in_array($timeline_sort,  array('asc', 'desc'), true))            { $timeline_sort  = 'asc';  }

                $timeline_rows = array();
                foreach ($course_modules as $tl_mod) {
                    foreach ($tl_mod['assessment_rows'] as $tl_asmt) {
                        $due_raw = !empty($tl_asmt['due_date'])
                            ? (string) $tl_asmt['due_date']
                            : (!empty($tl_asmt['created_at']) ? (string) $tl_asmt['created_at'] : current_time('mysql'));
                        $due_ts = strtotime($due_raw) ?: time();
                        $atype  = strtolower((string) ($tl_asmt['assessment_type'] ?? 'assessment'));
                        $timeline_rows[] = array(
                            'due_ts'       => $due_ts,
                            'time'         => !empty($tl_asmt['due_date']) ? date_i18n('H:i', $due_ts) : '--:--',
                            'date_label'   => !empty($tl_asmt['due_date']) ? date_i18n('l, j F Y', $due_ts) : 'No due date',
                            'title'        => (string) ($tl_asmt['title'] ?? 'Assessment'),
                            'subtitle'     => ucfirst($atype) . (!empty($tl_asmt['due_date']) ? ' is due' : ' opens'),
                            'course_name'  => (string) ($tl_mod['course_name'] ?? ''),
                            'module_name'  => (string) ($tl_mod['module_name'] ?? ''),
                            'type'         => $atype,
                            'module_id'    => (int) $tl_mod['module_id'],
                            'has_due_date' => !empty($tl_asmt['due_date']),
                        );
                    }
                    foreach ($tl_mod['content_rows'] as $tl_cnt) {
                        $ctype = strtolower((string) ($tl_cnt['content_type'] ?? 'study_material'));
                        if (!in_array($ctype, array('quiz', 'assignment'), true)) { continue; }
                        $due_raw = !empty($tl_cnt['due_date'])
                            ? (string) $tl_cnt['due_date']
                            : (!empty($tl_cnt['created_at']) ? (string) $tl_cnt['created_at'] : current_time('mysql'));
                        $due_ts = strtotime($due_raw) ?: time();
                        $timeline_rows[] = array(
                            'due_ts'       => $due_ts,
                            'time'         => !empty($tl_cnt['due_date']) ? date_i18n('H:i', $due_ts) : '--:--',
                            'date_label'   => !empty($tl_cnt['due_date']) ? date_i18n('l, j F Y', $due_ts) : 'No due date',
                            'title'        => (string) ($tl_cnt['title'] ?? ucfirst($ctype)),
                            'subtitle'     => ucfirst($ctype) . (!empty($tl_cnt['due_date']) ? ' is due' : ' opens'),
                            'course_name'  => (string) ($tl_mod['course_name'] ?? ''),
                            'module_name'  => (string) ($tl_mod['module_name'] ?? ''),
                            'type'         => $ctype,
                            'module_id'    => (int) $tl_mod['module_id'],
                            'has_due_date' => !empty($tl_cnt['due_date']),
                        );
                    }
                }

                $timeline_query_lc = strtolower(trim($timeline_query));
                $timeline_rows = array_values(array_filter($timeline_rows, static function ($row) use ($timeline_query_lc, $timeline_range) {
                    if ($timeline_query_lc !== '') {
                        $haystack = strtolower(
                            (string) ($row['title'] ?? '') . ' ' .
                            (string) ($row['type'] ?? '') . ' ' .
                            (string) ($row['course_name'] ?? '') . ' ' .
                            (string) ($row['module_name'] ?? '')
                        );
                        if (strpos($haystack, $timeline_query_lc) === false) { return false; }
                    }
                    if ($timeline_range === 'all') { return true; }
                    if (empty($row['has_due_date'])) { return false; }
                    $now = time();
                    $ts  = (int) ($row['due_ts'] ?? 0);
                    if ($timeline_range === 'next7')  { return ($ts >= $now && $ts <= ($now + 7 * DAY_IN_SECONDS)); }
                    return ($ts >= $now && $ts <= ($now + 30 * DAY_IN_SECONDS));
                }));

                usort($timeline_rows, static function ($a, $b) use ($timeline_sort) {
                    if ($timeline_sort === 'desc') { return (int) $b['due_ts'] <=> (int) $a['due_ts']; }
                    return (int) $a['due_ts'] <=> (int) $b['due_ts'];
                });

                $timeline_course_name = '';
                foreach ($course_modules as $tl_item) {
                    if (!empty($tl_item['course_name'])) { $timeline_course_name = (string) $tl_item['course_name']; break; }
                }

                switch ($current_tab) {
                    case 'overview':
                        ?>
                        <div class="space-y-16">
                            <div class="bg-white border-2 border-slate-300 rounded-xl p-5">
                                <?php if ($timeline_course_name !== '') : ?>
                                    <div class="text-sm font-semibold text-slate-500 uppercase tracking-wide mb-1">Course</div>
                                    <div class="text-2xl font-semibold text-slate-900 mb-3"><?php echo esc_html($timeline_course_name); ?></div>
                                <?php endif; ?>
                                <h2 class="text-3xl font-semibold text-slate-800 mb-4">Timeline</h2>

                                <form method="get" action="<?php echo esc_url(home_url('/portal/')); ?>" class="grid grid-cols-1 lg:grid-cols-3 gap-3 mb-5">
                                    <div class="flex gap-3 lg:col-span-2">
                                        <select id="nds-timeline-range" name="timeline_range" class="border border-slate-300 rounded-full px-4 py-2 text-slate-700 bg-white text-sm">
                                            <option value="next7" <?php selected($timeline_range, 'next7'); ?>>Next 7 days</option>
                                            <option value="next30" <?php selected($timeline_range, 'next30'); ?>>Next 30 days</option>
                                            <option value="all" <?php selected($timeline_range, 'all'); ?>>All dates</option>
                                        </select>
                                        <select id="nds-timeline-sort" name="timeline_sort" class="border border-slate-300 rounded-full px-4 py-2 text-slate-700 bg-white text-sm">
                                            <option value="asc" <?php selected($timeline_sort, 'asc'); ?>>Sort by dates</option>
                                            <option value="desc" <?php selected($timeline_sort, 'desc'); ?>>Sort by latest</option>
                                        </select>
                                        <button type="submit" class="border border-slate-300 rounded-full px-4 py-2 text-slate-700 bg-white text-sm hover:bg-slate-50">Apply</button>
                                    </div>
                                    <input id="nds-timeline-search"
                                           name="timeline_q"
                                           value="<?php echo esc_attr($timeline_query); ?>"
                                           type="text"
                                           placeholder="Search by activity type or name"
                                           class="w-full border border-slate-300 rounded-xl px-4 py-2 text-sm text-slate-700">
                                </form>

                                <div id="nds-timeline-list" class="divide-y divide-slate-200 border-t border-slate-200">
                                    <?php if (empty($timeline_rows)) : ?>
                                        <div class="py-6 text-sm text-slate-500">No timeline activities found for your current filters.</div>
                                    <?php else : ?>
                                        <?php foreach ($timeline_rows as $timeline_row) : ?>
                                            <?php
                                            $event_type = (string) ($timeline_row['type'] ?? 'assessment');
                                            $event_css  = $event_type === 'assignment' ? 'text-orange-700' : ($event_type === 'quiz' ? 'text-indigo-700' : 'text-slate-700');
                                            $event_icon = $event_type === 'assignment' ? 'fa-file-alt' : ($event_type === 'quiz' ? 'fa-question-circle' : 'fa-book');
                                            $module_link = add_query_arg(
                                                array('tab' => 'courses', 'module_id' => (int) $timeline_row['module_id']),
                                                home_url('/portal/')
                                            );
                                            ?>
                                            <div class="nds-timeline-item py-4"
                                                 data-ts="<?php echo esc_attr((int) $timeline_row['due_ts']); ?>"
                                                 data-text="<?php echo esc_attr(strtolower($timeline_row['title'] . ' ' . $timeline_row['type'] . ' ' . $timeline_row['course_name'] . ' ' . $timeline_row['module_name'])); ?>">
                                                <div class="text-sm font-semibold text-slate-800 mb-3"><?php echo esc_html($timeline_row['date_label']); ?></div>
                                                <div class="grid grid-cols-1 md:grid-cols-[70px_1fr_auto] gap-3 items-center">
                                                    <div class="text-xl text-slate-700"><?php echo esc_html($timeline_row['time']); ?></div>
                                                    <div class="flex items-start gap-3">
                                                        <i class="fas <?php echo esc_attr($event_icon); ?> mt-1 text-lg <?php echo esc_attr($event_css); ?>"></i>
                                                        <div>
                                                            <p class="text-2xl font-semibold text-slate-900 leading-tight"><?php echo esc_html(ucfirst($event_type) . ': ' . $timeline_row['title']); ?></p>
                                                            <p class="text-lg text-slate-700"><?php echo esc_html($timeline_row['subtitle']); ?> &middot; <?php echo esc_html($timeline_row['course_name']); ?></p>
                                                        </div>
                                                    </div>
                                                    <a href="<?php echo esc_url($module_link); ?>"
                                                       class="inline-flex items-center border border-slate-400 rounded-md px-4 py-2 text-slate-700 hover:bg-slate-50 text-lg">
                                                        <?php echo esc_html($event_type === 'assignment' ? 'Add submission' : 'Open'); ?>
                                                    </a>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <div id="nds-timeline-empty" class="hidden pt-4 text-sm text-slate-500">No timeline activities match your current filter.</div>
                            </div>

                            <div class="mt-6 bg-white border-2 border-slate-300 rounded-xl p-5">
                                <h2 class="text-3xl font-semibold text-slate-800 mb-4">My courses</h2>
                                <?php if (empty($course_modules)) : ?>
                                    <p class="text-sm text-slate-600">No registered modules found yet.</p>
                                <?php else : ?>
                                    <div class="space-y-2 mb-4">
                                        <?php foreach ($course_modules as $module_item) : ?>
                                            <?php
                                            $module_link = add_query_arg(
                                                array('tab' => 'courses', 'module_id' => (int) $module_item['module_id']),
                                                home_url('/portal/')
                                            );
                                            ?>
                                            <a href="<?php echo esc_url($module_link); ?>" class="w-full text-left flex items-center gap-2 text-lg font-medium text-black hover:text-slate-700">
                                                <i class="fas fa-graduation-cap text-xl text-slate-700"></i>
                                                <span>
                                                    <?php echo esc_html($module_item['module_name']); ?>
                                                    <?php if (!empty($module_item['module_code'])) : ?>
                                                        (<?php echo esc_html($module_item['module_code']); ?>)
                                                    <?php endif; ?>
                                                </span>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php
                            $cal_events_by_date = array();
                            foreach ($course_modules as $cal_mod) {
                                foreach (($cal_mod['assessment_rows'] ?? array()) as $cal_asmt) {
                                    if (empty($cal_asmt['due_date'])) { continue; }
                                    $due_ts = strtotime((string) $cal_asmt['due_date']);
                                    if (!$due_ts) { continue; }
                                    $key = date('Y-m-d', $due_ts);
                                    $cal_events_by_date[$key][] = array(
                                        'title' => (string) ($cal_asmt['title'] ?? 'Assessment'),
                                        'type'  => strtolower((string) ($cal_asmt['assessment_type'] ?? 'assessment')),
                                    );
                                }
                                foreach (($cal_mod['content_rows'] ?? array()) as $cal_cnt) {
                                    $ctype = strtolower((string) ($cal_cnt['content_type'] ?? ''));
                                    if (!in_array($ctype, array('quiz', 'assignment'), true)) { continue; }
                                    if (empty($cal_cnt['due_date'])) { continue; }
                                    $due_ts = strtotime((string) $cal_cnt['due_date']);
                                    if (!$due_ts) { continue; }
                                    $key = date('Y-m-d', $due_ts);
                                    $cal_events_by_date[$key][] = array(
                                        'title' => (string) ($cal_cnt['title'] ?? ucfirst($ctype)),
                                        'type'  => $ctype,
                                    );
                                }
                            }

                            $cal_now_y = (int) date('Y');
                            $cal_now_m = (int) date('n');
                            $cal_y = isset($_GET['cal_y']) ? max(2020, min(2040, (int) $_GET['cal_y'])) : $cal_now_y;
                            $cal_m = isset($_GET['cal_m']) ? max(1, min(12, (int) $_GET['cal_m'])) : $cal_now_m;

                            $cal_first_day = mktime(0, 0, 0, $cal_m, 1, $cal_y);
                            $cal_days_in_month = (int) date('t', $cal_first_day);
                            $cal_start_dow = (int) date('N', $cal_first_day);
                            $cal_month_label = date_i18n('F Y', $cal_first_day);

                            $cal_prev_m = $cal_m - 1;
                            $cal_prev_y = $cal_y;
                            if ($cal_prev_m < 1) {
                                $cal_prev_m = 12;
                                $cal_prev_y--;
                            }
                            $cal_next_m = $cal_m + 1;
                            $cal_next_y = $cal_y;
                            if ($cal_next_m > 12) {
                                $cal_next_m = 1;
                                $cal_next_y++;
                            }

                            $base_url = home_url('/portal/');
                            $cal_prev_url = add_query_arg(array('tab' => 'overview', 'cal_y' => $cal_prev_y, 'cal_m' => $cal_prev_m), $base_url);
                            $cal_next_url = add_query_arg(array('tab' => 'overview', 'cal_y' => $cal_next_y, 'cal_m' => $cal_next_m), $base_url);

                            $dot_colors = array(
                                'quiz' => '#6366f1',
                                'assignment' => '#f97316',
                                'exam' => '#ef4444',
                                'practical' => '#14b8a6',
                            );
                            ?>
                            <div class="mt-6 bg-white border-2 border-slate-300 rounded-xl p-5">
                                <div class="flex items-center justify-between mb-4">
                                    <h2 class="text-2xl font-semibold text-slate-800">Calendar</h2>
                                    <div class="flex items-center gap-2">
                                        <a href="<?php echo esc_url($cal_prev_url); ?>" class="w-8 h-8 flex items-center justify-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-100 transition-colors">
                                            <i class="fas fa-chevron-left text-xs"></i>
                                        </a>
                                        <span class="text-sm font-semibold text-slate-700 min-w-[120px] text-center"><?php echo esc_html($cal_month_label); ?></span>
                                        <a href="<?php echo esc_url($cal_next_url); ?>" class="w-8 h-8 flex items-center justify-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-100 transition-colors">
                                            <i class="fas fa-chevron-right text-xs"></i>
                                        </a>
                                    </div>
                                </div>

                                <div class="grid grid-cols-7 mb-1">
                                    <?php foreach (array('Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun') as $dh) : ?>
                                        <div class="text-center text-[10px] font-bold uppercase text-slate-400 py-1"><?php echo esc_html($dh); ?></div>
                                    <?php endforeach; ?>
                                </div>

                                <div class="grid grid-cols-7 gap-y-1">
                                    <?php
                                    for ($blank = 1; $blank < $cal_start_dow; $blank++) {
                                        echo '<div></div>';
                                    }
                                    for ($day = 1; $day <= $cal_days_in_month; $day++) {
                                        $date_key = sprintf('%04d-%02d-%02d', $cal_y, $cal_m, $day);
                                        $is_today = ($cal_y === $cal_now_y && $cal_m === $cal_now_m && $day === (int) date('j'));
                                        $day_events = $cal_events_by_date[$date_key] ?? array();
                                        $has_events = !empty($day_events);
                                        $num_cls = $is_today
                                            ? 'w-7 h-7 rounded-full bg-blue-600 text-white font-bold text-xs flex items-center justify-center mx-auto'
                                            : 'w-7 h-7 rounded-full text-xs flex items-center justify-center mx-auto ' . ($has_events ? 'font-semibold text-slate-800' : 'text-slate-500');
                                        ?>
                                        <div class="flex flex-col items-center py-0.5 group relative">
                                            <span class="<?php echo esc_attr($num_cls); ?>"><?php echo $day; ?></span>
                                            <?php if ($has_events) : ?>
                                                <div class="flex gap-0.5 mt-0.5 flex-wrap justify-center max-w-[28px]">
                                                    <?php foreach (array_slice($day_events, 0, 3) as $de) : ?>
                                                        <?php $dot_color = $dot_colors[$de['type']] ?? '#64748b'; ?>
                                                        <span class="w-1.5 h-1.5 rounded-full flex-shrink-0" style="background-color:<?php echo esc_attr($dot_color); ?>;"></span>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <?php
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>

                        <script>
                        (function () {
                            var searchInput = document.getElementById('nds-timeline-search');
                            if (!searchInput) { return; }
                            searchInput.addEventListener('keydown', function (event) {
                                if (event.key === 'Enter') { searchInput.form.submit(); }
                            });
                        })();
                        </script>
                        <?php
                        break;

                    case 'courses':
                        if (!empty($selected_module)) {
                            $module_content_rows    = $selected_module['content_rows']    ?? array();
                            $module_assessment_rows = $selected_module['assessment_rows'] ?? array();
                            ?>
                            <div class="space-y-6">
                                <div>
                                    <a href="<?php echo esc_url($courses_tab_url); ?>" class="inline-flex items-center text-sm font-medium text-blue-700 hover:text-blue-900">
                                        <i class="fas fa-arrow-left mr-2"></i> Back to modules
                                    </a>
                                </div>

                                <div class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm">
                                    <!-- Module header banner -->
                                    <?php
                                    $banner_colors = array(
                                        'bg-blue-600','bg-teal-600','bg-emerald-600','bg-indigo-600',
                                        'bg-violet-600','bg-rose-600','bg-amber-500','bg-cyan-600',
                                    );
                                    $banner_bg = $banner_colors[$selected_module_id % count($banner_colors)];
                                    ?>
                                    <div class="<?php echo esc_attr($banner_bg); ?> px-6 py-8 relative overflow-hidden">
                                        <div class="absolute inset-0 opacity-10" style="background-image:radial-gradient(circle at 20% 50%,#fff 1px,transparent 1px),radial-gradient(circle at 80% 20%,#fff 1px,transparent 1px);background-size:40px 40px;"></div>
                                        <div class="relative">
                                            <span class="inline-block text-xs font-semibold uppercase tracking-widest text-white/70 mb-1">Module</span>
                                            <h2 class="text-2xl font-bold text-white"><?php echo esc_html($selected_module['module_name'] ?? 'Module'); ?></h2>
                                            <p class="text-sm text-white/80 mt-1">
                                                <?php echo esc_html($selected_module['course_name'] ?? ''); ?>
                                                <?php if (!empty($selected_module['module_code'])) : ?>
                                                    &nbsp;·&nbsp;<?php echo esc_html($selected_module['module_code']); ?>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                    </div>

                                    <div class="p-6 grid grid-cols-1 lg:grid-cols-2 gap-5">
                                        <!-- Study Materials -->
                                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                                            <h3 class="text-xs font-bold uppercase tracking-wide text-slate-500 mb-3 flex items-center gap-2">
                                                <i class="fas fa-book-open text-blue-500"></i> Study Materials &amp; Content
                                            </h3>
                                            <?php if (empty($module_content_rows)) : ?>
                                                <p class="text-sm text-slate-400 italic">No content published yet.</p>
                                            <?php else : ?>
                                                <div class="space-y-2">
                                                    <?php foreach ($module_content_rows as $content_item) :
                                                        $ctype = strtolower((string)($content_item['content_type'] ?? 'study_material'));
                                                        $ctype_icons = array('quiz'=>'fa-question-circle text-indigo-500','assignment'=>'fa-file-alt text-orange-500','video'=>'fa-play-circle text-red-500','online_course'=>'fa-laptop text-emerald-500','announcement'=>'fa-bullhorn text-gray-500');
                                                        $ctype_icon = $ctype_icons[$ctype] ?? 'fa-file text-slate-400';
                                                    ?>
                                                        <div class="bg-white rounded-md border border-slate-200 p-3 flex items-start gap-3">
                                                            <i class="fas <?php echo esc_attr($ctype_icon); ?> mt-0.5 text-sm flex-shrink-0"></i>
                                                            <div class="min-w-0">
                                                                <p class="text-sm font-semibold text-slate-800 leading-tight"><?php echo esc_html($content_item['title'] ?? 'Content'); ?></p>
                                                                <p class="text-xs text-slate-500 mt-0.5"><?php echo esc_html(ucwords(str_replace('_',' ',$ctype))); ?></p>
                                                                <?php if (!empty($content_item['description'])) : ?>
                                                                    <p class="text-xs text-slate-600 mt-1"><?php echo esc_html(wp_trim_words($content_item['description'],20)); ?></p>
                                                                <?php endif; ?>
                                                                <?php if (!empty($content_item['due_date'])) : ?>
                                                                    <p class="text-xs text-slate-400 mt-1"><i class="fas fa-clock mr-1"></i>Due: <?php echo esc_html(date_i18n('j M Y', strtotime($content_item['due_date']))); ?></p>
                                                                <?php endif; ?>
                                                                <div class="mt-1.5 flex gap-3 text-xs">
                                                                    <?php if (!empty($content_item['resource_url'])) : ?>
                                                                        <a href="<?php echo esc_url($content_item['resource_url']); ?>" target="_blank" rel="noopener" class="text-blue-600 hover:underline font-medium">Open link</a>
                                                                    <?php endif; ?>
                                                                    <?php if (!empty($content_item['attachment_url'])) : ?>
                                                                        <a href="<?php echo esc_url($content_item['attachment_url']); ?>" target="_blank" rel="noopener" class="text-blue-600 hover:underline font-medium">Download</a>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Assessments -->
                                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                                            <h3 class="text-xs font-bold uppercase tracking-wide text-slate-500 mb-3 flex items-center gap-2">
                                                <i class="fas fa-tasks text-orange-500"></i> Assessments
                                            </h3>
                                            <?php if (empty($module_assessment_rows)) : ?>
                                                <p class="text-sm text-slate-400 italic">No assessments published yet.</p>
                                            <?php else : ?>
                                                <div class="space-y-2">
                                                    <?php foreach ($module_assessment_rows as $assessment_item) :
                                                        $atype = strtolower((string)($assessment_item['assessment_type'] ?? 'assessment'));
                                                        $atype_colors = array('quiz'=>'text-indigo-600 bg-indigo-50','assignment'=>'text-orange-600 bg-orange-50','exam'=>'text-red-600 bg-red-50','practical'=>'text-teal-600 bg-teal-50');
                                                        [$at_text_cls, $at_bg_cls] = array_values($atype_colors[$atype] ?? array('text-gray-600','bg-gray-50'));
                                                    ?>
                                                        <div class="bg-white rounded-md border border-slate-200 p-3">
                                                            <div class="flex items-start justify-between gap-2">
                                                                <p class="text-sm font-semibold text-slate-800 leading-tight"><?php echo esc_html($assessment_item['title'] ?? 'Assessment'); ?></p>
                                                                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded <?php echo esc_attr("$at_text_cls $at_bg_cls"); ?> flex-shrink-0"><?php echo esc_html(ucfirst($atype)); ?></span>
                                                            </div>
                                                            <?php if (!empty($assessment_item['instructions'])) : ?>
                                                                <p class="text-xs text-slate-500 mt-1"><?php echo esc_html(wp_trim_words($assessment_item['instructions'],20)); ?></p>
                                                            <?php endif; ?>
                                                            <div class="mt-1.5 flex items-center gap-3 text-xs text-slate-400 flex-wrap">
                                                                <?php if (!empty($assessment_item['due_date'])) : ?>
                                                                    <span><i class="fas fa-clock mr-1"></i>Due: <?php echo esc_html(date_i18n('j M Y H:i', strtotime($assessment_item['due_date']))); ?></span>
                                                                <?php endif; ?>
                                                                <?php if (!empty($assessment_item['max_grade'])) : ?>
                                                                    <span><i class="fas fa-star mr-1"></i>Max: <?php echo esc_html($assessment_item['max_grade']); ?></span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php
                            break;
                        }

                        // ── Module cards grid (no module selected) ──
                        ?>
                        <div>
                            <h2 class="text-xl font-bold text-gray-800 mb-5">My modules</h2>

                            <?php if (empty($course_modules)) : ?>
                                <div class="text-center py-12 text-gray-400">
                                    <i class="fas fa-book-open text-5xl mb-3 block"></i>
                                    <p class="text-sm">No modules registered yet.</p>
                                </div>
                            <?php else : ?>
                                <?php
                                // Group by course
                                $modules_by_course = array();
                                foreach ($course_modules as $cm) {
                                    $cname = $cm['course_name'] ?: 'Course';
                                    if (!isset($modules_by_course[$cname])) {
                                        $modules_by_course[$cname] = array();
                                    }
                                    $modules_by_course[$cname][] = $cm;
                                }

                                $card_banner_colors = array(
                                    'bg-blue-600','bg-teal-600','bg-emerald-600','bg-indigo-600',
                                    'bg-violet-600','bg-rose-500','bg-amber-500','bg-cyan-600',
                                    'bg-sky-600','bg-pink-600','bg-lime-600','bg-orange-500',
                                );
                                $card_idx = 0;
                                ?>

                                <?php foreach ($modules_by_course as $course_heading => $course_mods) : ?>
                                    <div class="mb-12">
                                        <div class="flex items-center gap-2 mb-3">
                                            <i class="fas fa-graduation-cap text-slate-500 text-sm"></i>
                                            <h3 class="text-sm font-semibold text-slate-600 uppercase tracking-wide"><?php echo esc_html($course_heading); ?></h3>
                                        </div>

                                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                                            <?php foreach ($course_mods as $card_mod) :
                                                $card_link = add_query_arg(array('tab'=>'courses','module_id'=>(int)$card_mod['module_id']), home_url('/portal/'));
                                                $banner    = $card_banner_colors[$card_idx % count($card_banner_colors)];
                                                $n_content = count($card_mod['content_rows'] ?? array());
                                                $n_assess  = count($card_mod['assessment_rows'] ?? array());
                                                $card_idx++;
                                            ?>
                                                <a href="<?php echo esc_url($card_link); ?>"
                                                   class="group block bg-white rounded-xl border border-gray-200 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 overflow-hidden">
                                                    <!-- Coloured banner -->
                                                    <div class="<?php echo esc_attr($banner); ?> h-28 relative overflow-hidden">
                                                        <div class="absolute inset-0 opacity-20" style="background-image:radial-gradient(circle,#fff 1px,transparent 1px);background-size:22px 22px;"></div>
                                                        <div class="absolute bottom-3 right-3 opacity-30">
                                                            <i class="fas fa-book text-white text-4xl"></i>
                                                        </div>
                                                    </div>

                                                    <!-- Card body -->
                                                    <div class="p-5">
                                                        <p class="text-sm font-bold text-blue-900 leading-snug group-hover:text-blue-700 line-clamp-2">
                                                            <?php echo esc_html(strtoupper($card_mod['module_name'])); ?>
                                                            <?php if (!empty($card_mod['module_code'])) : ?>
                                                                (<?php echo esc_html($card_mod['module_code']); ?>)
                                                            <?php endif; ?>
                                                        </p>
                                                        <?php if (!empty($card_mod['program_name'])) : ?>
                                                            <p class="text-xs text-gray-500 mt-1.5 truncate"><?php echo esc_html($card_mod['program_name']); ?></p>
                                                        <?php endif; ?>
                                                    </div>

                                                    <!-- Card footer -->
                                                    <div class="px-5 pb-4 flex items-center justify-between border-t border-gray-100 pt-3">
                                                        <div class="flex items-center gap-3 text-xs text-gray-400">
                                                            <?php if ($n_content > 0) : ?>
                                                                <span><i class="fas fa-file-alt mr-1"></i><?php echo $n_content; ?></span>
                                                            <?php endif; ?>
                                                            <?php if ($n_assess > 0) : ?>
                                                                <span><i class="fas fa-tasks mr-1"></i><?php echo $n_assess; ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <i class="fas fa-ellipsis-v text-gray-300 group-hover:text-gray-500 transition-colors"></i>
                                                    </div>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <?php
                        break;

                    case 'timetable':
                        include plugin_dir_path(__FILE__) . '../includes/partials/learner-dashboard-timetable.php';
                        break;

                    case 'finances':
                        include plugin_dir_path(__FILE__) . '../includes/partials/learner-dashboard-finances.php';
                        break;

                    case 'results':
                        include plugin_dir_path(__FILE__) . '../includes/partials/learner-dashboard-results.php';
                        break;

                    case 'graduation':
                        include plugin_dir_path(__FILE__) . '../includes/partials/learner-dashboard-graduation.php';
                        break;

                    case 'certificates':
                        include plugin_dir_path(__FILE__) . '../includes/partials/learner-dashboard-certificates.php';
                        break;

                    case 'documents':
                        include plugin_dir_path(__FILE__) . '../includes/partials/learner-dashboard-documents.php';
                        break;

                    case 'activity':
                        include plugin_dir_path(__FILE__) . '../includes/partials/learner-dashboard-activity.php';
                        break;

                    default:
                        // Fallback to overview
                        break;
                }
                ?>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($show_success_modal) && $show_success_modal) : ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var modal = document.getElementById('nds-app-success-modal');
        var closeBtn = document.getElementById('nds-app-success-close');
        var dashboardUrl = <?php echo wp_json_encode(home_url('/portal/')); ?>;
        if (modal && closeBtn) {
            closeBtn.addEventListener('click', function () {
                window.location.href = dashboardUrl;
            });
        }
    });
    </script>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const bell = document.getElementById('nds-notification-bell');
    const dropdown = document.getElementById('nds-notification-dropdown');
    const markAllBtn = document.getElementById('nds-mark-all-read');
    
    if (bell && dropdown) {
        bell.addEventListener('click', function(e) {
            e.stopPropagation();
            dropdown.classList.toggle('hidden');
        });

        document.addEventListener('click', function(e) {
            if (!dropdown.contains(e.target) && !bell.contains(e.target)) {
                dropdown.classList.add('hidden');
            }
        });
    }

    // Mark as read click
    document.querySelectorAll('.nds-mark-read').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const parent = this.closest('[data-id]');
            const id = parent.dataset.id;
            
            // Simple visual removal and badge update (AJAX can be added later or integrated now)
            parent.style.opacity = '0.5';
            parent.style.pointerEvents = 'none';
            
            // Call AJAX to mark as read
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'nds_mark_notification_read',
                    id: id,
                    nonce: '<?php echo wp_create_nonce("nds_notifications"); ?>'
                })
            }).then(() => {
                parent.remove();
                updateBadge();
            });
        });
    });

    if (markAllBtn) {
        markAllBtn.addEventListener('click', function() {
             fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'nds_mark_all_notifications_read',
                    student_id: '<?php echo $student_id; ?>',
                    nonce: '<?php echo wp_create_nonce("nds_notifications"); ?>'
                })
            }).then(() => {
                document.getElementById('nds-notification-list').innerHTML = `
                    <div class="p-12 text-center">
                        <div class="w-20 h-20 bg-gray-50 rounded-3xl flex items-center justify-center mx-auto mb-5 shadow-inner">
                            <i class="fas fa-bell-slash text-gray-300 text-3xl"></i>
                        </div>
                        <h4 class="text-base font-bold text-gray-800">All caught up!</h4>
                        <p class="text-sm text-gray-500 mt-2">No new notifications for you right now.</p>
                    </div>
                `;
                updateBadge(true);
                markAllBtn.remove();
            });
        });
    }

    function updateBadge(clear = false) {
        const badge = document.getElementById('nds-notification-badge');
        if (!badge) return;
        if (clear) {
            badge.remove();
            return;
        }
        const current = parseInt(badge.textContent.trim());
        if (current > 1) {
            badge.textContent = current - 1;
        } else {
            badge.remove();
        }
    }

    const registrationPanel = document.getElementById('nds-registration-panel');
    const registrationAction = document.getElementById('nds-registration-action');
    const registrationRun = document.getElementById('nds-registration-run');
    const registrationFeedback = document.getElementById('nds-registration-feedback');
    const registrationModuleWrap = document.getElementById('nds-registration-module-wrap');
    const selectAllModules = document.getElementById('nds-modules-select-all');

    function syncSelectAllState() {
        if (!selectAllModules) return;
        const allModuleCheckboxes = Array.from(document.querySelectorAll('.nds-module-pick'));
        if (allModuleCheckboxes.length === 0) {
            selectAllModules.checked = false;
            selectAllModules.indeterminate = false;
            return;
        }
        const checkedCount = allModuleCheckboxes.filter(function (el) { return el.checked; }).length;
        selectAllModules.checked = checkedCount === allModuleCheckboxes.length;
        selectAllModules.indeterminate = checkedCount > 0 && checkedCount < allModuleCheckboxes.length;
    }

    function syncRegistrationActionUI() {
        if (!registrationAction || !registrationModuleWrap) return;
        const actionNeedsModules = ['submit_registration', 'add_module', 'cancel_module'].indexOf(registrationAction.value) !== -1;
        registrationModuleWrap.classList.toggle('hidden', !actionNeedsModules);
    }

    function setRegistrationFeedback(message, isError) {
        if (!registrationFeedback) return;
        registrationFeedback.style.display = 'block';
        registrationFeedback.textContent = message;
        registrationFeedback.className = isError
            ? 'mt-3 text-sm text-red-700 bg-red-50 border border-red-200 rounded px-3 py-2'
            : 'mt-3 text-sm text-emerald-700 bg-emerald-50 border border-emerald-200 rounded px-3 py-2';
    }

    if (registrationPanel && registrationAction && registrationRun) {
        syncRegistrationActionUI();
        syncSelectAllState();

        registrationAction.addEventListener('change', syncRegistrationActionUI);

        if (selectAllModules) {
            selectAllModules.addEventListener('change', function () {
                const checked = !!selectAllModules.checked;
                document.querySelectorAll('.nds-module-pick').forEach(function (el) {
                    el.checked = checked;
                });
                syncSelectAllState();
            });
        }

        document.querySelectorAll('.nds-module-pick').forEach(function (el) {
            el.addEventListener('change', syncSelectAllState);
        });

        registrationRun.addEventListener('click', function () {
            const selectedAction = registrationAction.value;
            const courseId = registrationPanel.getAttribute('data-course-id');
            const nonce = registrationPanel.getAttribute('data-nonce');

            if (!selectedAction) {
                setRegistrationFeedback('Please choose a registration action first.', true);
                return;
            }

            const selectedModuleInputs = Array.from(document.querySelectorAll('.nds-module-pick:checked'));
            const selectedModuleIds = selectedModuleInputs.map(function (el) { return el.value; });

            if ((selectedAction === 'submit_registration' || selectedAction === 'add_module' || selectedAction === 'cancel_module') && selectedModuleIds.length === 0) {
                setRegistrationFeedback('Please select at least one module for this action.', true);
                return;
            }

            registrationRun.disabled = true;
            registrationRun.textContent = 'Working...';

            const payload = new URLSearchParams();
            payload.append('action', 'nds_portal_registration_action');
            payload.append('nonce', nonce);
            payload.append('registration_action', selectedAction);
            payload.append('course_id', String(courseId || ''));
            selectedModuleIds.forEach(function (id) {
                payload.append('module_ids[]', id);
            });

            fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: payload.toString()
            })
            .then(function (res) { return res.json(); })
            .then(function (json) {
                if (!json || !json.success) {
                    const msg = (json && json.data) ? json.data : 'Registration action failed.';
                    setRegistrationFeedback(msg, true);
                    return;
                }

                const data = json.data || {};
                setRegistrationFeedback(data.message || 'Action completed successfully.', false);

                if (selectedAction === 'download_proof' && data.proof_content) {
                    const blob = new Blob([data.proof_content], { type: 'text/plain;charset=utf-8' });
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = data.proof_filename || 'proof-of-registration.txt';
                    document.body.appendChild(a);
                    a.click();
                    a.remove();
                    URL.revokeObjectURL(url);
                }

                if ((selectedAction === 'add_module' || selectedAction === 'cancel_module') && Array.isArray(data.enrolled_module_ids)) {
                    const enrolled = data.enrolled_module_ids.map(function (v) { return parseInt(v, 10); });
                    document.querySelectorAll('.nds-module-pick').forEach(function (el) {
                        const id = parseInt(el.value, 10);
                        el.checked = enrolled.indexOf(id) !== -1;
                    });
                    syncSelectAllState();
                }

                if (selectedAction === 'submit_registration' && Array.isArray(data.enrolled_module_ids)) {
                    const enrolledAfterSubmit = data.enrolled_module_ids.map(function (v) { return parseInt(v, 10); });
                    document.querySelectorAll('.nds-module-pick').forEach(function (el) {
                        const id = parseInt(el.value, 10);
                        el.checked = enrolledAfterSubmit.indexOf(id) !== -1;
                    });
                    syncSelectAllState();
                }
            })
            .catch(function () {
                setRegistrationFeedback('Something went wrong while processing the action. Please try again.', true);
            })
            .finally(function () {
                registrationRun.disabled = false;
                registrationRun.textContent = 'Apply';
            });
        });
    }
});
</script>

<?php wp_footer(); ?>
</body>
</html>
