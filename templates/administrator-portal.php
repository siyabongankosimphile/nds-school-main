<?php
if (!defined('ABSPATH')) {
    exit;
}
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html(get_bloginfo('name')); ?> - Administrator Portal</title>
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
$role_name = (string) ($staff_data['role'] ?? 'Administrator');

$current_tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'overview';
$valid_tabs = array('overview', 'applications', 'profile');
if (!in_array($current_tab, $valid_tabs, true)) {
    $current_tab = 'overview';
}

if (!function_exists('nds_administrator_portal_tab_url')) {
    function nds_administrator_portal_tab_url($tab, array $args = array())
    {
        $base = home_url('/administrator-portal/');
        if ($tab !== 'overview') {
            $base = add_query_arg('tab', $tab, $base);
        }
        if (!empty($args)) {
            $base = add_query_arg($args, $base);
        }
        return $base;
    }
}

$status_filter = isset($_GET['app_status']) ? sanitize_text_field(wp_unslash($_GET['app_status'])) : 'all';
$filterable_statuses = array('all', 'submitted', 'under_review', 'waitlisted', 'conditional_offer', 'offer_made', 'accepted', 'declined', 'withdrawn', 'rejected', 'expired');
if (!in_array($status_filter, $filterable_statuses, true)) {
    $status_filter = 'all';
}

$app_page = isset($_GET['app_page']) ? max(1, (int) $_GET['app_page']) : 1;
$per_page = 25;

$app_notice = isset($_GET['app_notice']) ? sanitize_text_field(wp_unslash($_GET['app_notice'])) : '';
$app_error = isset($_GET['app_error']) ? sanitize_text_field(wp_unslash($_GET['app_error'])) : '';

$active_applications = $wpdb->get_results(
    "SELECT a.id, a.application_no, a.status, a.submitted_at, a.created_at, af.full_name, af.email, af.course_name
     FROM {$wpdb->prefix}nds_applications a
     LEFT JOIN {$wpdb->prefix}nds_application_forms af ON a.id = af.application_id
     WHERE a.status != 'converted_to_student'
     GROUP BY a.id
     ORDER BY COALESCE(a.submitted_at, a.created_at) DESC",
    ARRAY_A
);

$status_counts = array(
    'total' => count($active_applications),
    'submitted' => 0,
    'under_review' => 0,
    'accepted' => 0,
);

foreach ($active_applications as $application_row) {
    $row_status = (string) ($application_row['status'] ?? 'draft');
    if ($row_status === 'submitted') {
        $status_counts['submitted']++;
    }
    if ($row_status === 'under_review') {
        $status_counts['under_review']++;
    }
    if (in_array($row_status, array('accepted', 'offer_made', 'conditional_offer'), true)) {
        $status_counts['accepted']++;
    }
}

$applications_to_display = array_values(array_filter($active_applications, function ($application_row) use ($status_filter) {
    if ($status_filter === 'all') {
        return true;
    }
    return (string) ($application_row['status'] ?? '') === $status_filter;
}));
$filtered_total = count($applications_to_display);
$total_pages = max(1, (int) ceil($filtered_total / $per_page));
if ($app_page > $total_pages) {
    $app_page = $total_pages;
}
$offset = ($app_page - 1) * $per_page;
$applications_to_display = array_slice($applications_to_display, $offset, $per_page);

$status_labels = array(
    'submitted' => 'Submitted',
    'under_review' => 'Under Review',
    'waitlisted' => 'Waitlisted',
    'conditional_offer' => 'Conditional Offer',
    'offer_made' => 'Offer Made',
    'accepted' => 'Accepted',
    'declined' => 'Declined',
    'withdrawn' => 'Withdrawn',
    'rejected' => 'Rejected',
    'expired' => 'Expired',
    'draft' => 'Draft'
);
?>

<div class="nds-tailwind-wrapper bg-gray-50 min-h-screen nds-portal-offset nds-portal-theme" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
    <div class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-6">
                <div class="flex items-center space-x-4">
                    <div class="w-12 h-12 bg-blue-700 rounded-xl flex items-center justify-center shadow-lg">
                        <i class="fas fa-user-shield text-white text-2xl"></i>
                    </div>
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900"><?php echo esc_html($full_name !== '' ? $full_name : 'Administrator'); ?></h1>
                        <p class="text-sm text-gray-600 mt-1"><?php echo esc_html($role_name); ?> • <?php echo esc_html($staff_data['email'] ?? ''); ?></p>
                    </div>
                </div>
                <div class="flex items-center space-x-3">
                    <a href="<?php echo esc_url(home_url('/online-application/')); ?>" class="inline-flex items-center px-4 py-2 rounded-lg border border-blue-200 bg-blue-50 hover:bg-blue-100 text-blue-700 text-sm font-medium shadow-sm transition-all duration-200">
                        <i class="fas fa-file-signature mr-2"></i>Application Form
                    </a>
                    <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" class="inline-flex items-center px-4 py-2 rounded-lg bg-red-50 hover:bg-red-100 text-red-700 text-sm font-medium shadow-sm transition-all duration-200">
                        <i class="fas fa-sign-out-alt mr-2"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
            <div class="bg-white shadow-sm rounded-xl p-5 border border-gray-100">
                <p class="text-sm font-medium text-gray-500">Total Active Applications</p>
                <p class="mt-2 text-2xl font-semibold text-gray-900"><?php echo esc_html((string) $status_counts['total']); ?></p>
            </div>
            <div class="bg-white shadow-sm rounded-xl p-5 border border-gray-100">
                <p class="text-sm font-medium text-gray-500">Submitted</p>
                <p class="mt-2 text-2xl font-semibold text-amber-600"><?php echo esc_html((string) $status_counts['submitted']); ?></p>
            </div>
            <div class="bg-white shadow-sm rounded-xl p-5 border border-gray-100">
                <p class="text-sm font-medium text-gray-500">Under Review</p>
                <p class="mt-2 text-2xl font-semibold text-blue-700"><?php echo esc_html((string) $status_counts['under_review']); ?></p>
            </div>
            <div class="bg-white shadow-sm rounded-xl p-5 border border-gray-100">
                <p class="text-sm font-medium text-gray-500">Accepted / Offered</p>
                <p class="mt-2 text-2xl font-semibold text-emerald-700"><?php echo esc_html((string) $status_counts['accepted']); ?></p>
            </div>
        </div>

        <div class="bg-white shadow-sm rounded-xl border border-gray-100 mb-6">
            <div class="border-b border-gray-200">
                <nav class="flex -mb-px overflow-x-auto" aria-label="Tabs">
                    <a href="<?php echo esc_url(nds_administrator_portal_tab_url('overview')); ?>" class="<?php echo $current_tab === 'overview' ? 'border-blue-600 text-blue-700' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm transition-colors">
                        <i class="fas fa-home mr-2"></i>Overview
                    </a>
                    <a href="<?php echo esc_url(nds_administrator_portal_tab_url('applications')); ?>" class="<?php echo $current_tab === 'applications' ? 'border-blue-600 text-blue-700' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm transition-colors">
                        <i class="fas fa-clipboard-list mr-2"></i>Applications
                    </a>
                    <a href="<?php echo esc_url(nds_administrator_portal_tab_url('profile')); ?>" class="<?php echo $current_tab === 'profile' ? 'border-blue-600 text-blue-700' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm transition-colors">
                        <i class="fas fa-user-cog mr-2"></i>Profile
                    </a>
                </nav>
            </div>

            <div class="p-6 nds-content-area">
                <?php if ($current_tab === 'overview') : ?>
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <div class="lg:col-span-2 bg-gray-50 rounded-xl p-6 border border-gray-200">
                            <h2 class="text-lg font-semibold text-gray-900 mb-3">Administrator Dashboard</h2>
                            <p class="text-sm text-gray-700 mb-4">Manage admission applications from one place: review submissions, update statuses, and keep application notes current.</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <a href="<?php echo esc_url(nds_administrator_portal_tab_url('applications')); ?>" class="inline-flex items-center justify-center rounded-lg border border-blue-200 bg-blue-50 text-blue-700 px-4 py-3 text-sm font-medium hover:bg-blue-100 transition-colors">
                                    <i class="fas fa-clipboard-check mr-2"></i>Open Applications Queue
                                </a>
                                <a href="<?php echo esc_url(nds_administrator_portal_tab_url('applications', array('app_status' => 'under_review'))); ?>" class="inline-flex items-center justify-center rounded-lg border border-indigo-200 bg-indigo-50 text-indigo-700 px-4 py-3 text-sm font-medium hover:bg-indigo-100 transition-colors">
                                    <i class="fas fa-search mr-2"></i>Review Under Review
                                </a>
                                <a href="<?php echo esc_url(nds_administrator_portal_tab_url('applications', array('app_status' => 'submitted'))); ?>" class="inline-flex items-center justify-center rounded-lg border border-amber-200 bg-amber-50 text-amber-700 px-4 py-3 text-sm font-medium hover:bg-amber-100 transition-colors">
                                    <i class="fas fa-inbox mr-2"></i>Handle New Submissions
                                </a>
                                <a href="<?php echo esc_url(nds_administrator_portal_tab_url('profile')); ?>" class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white text-gray-700 px-4 py-3 text-sm font-medium hover:bg-gray-50 transition-colors">
                                    <i class="fas fa-user-edit mr-2"></i>Update Profile
                                </a>
                            </div>
                        </div>
                        <div class="bg-white rounded-xl p-6 border border-gray-200">
                            <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-600 mb-3">Queue Snapshot</h3>
                            <ul class="space-y-2 text-sm text-gray-700">
                                <li class="flex items-center justify-between"><span>Total active</span><strong><?php echo esc_html((string) $status_counts['total']); ?></strong></li>
                                <li class="flex items-center justify-between"><span>Submitted</span><strong><?php echo esc_html((string) $status_counts['submitted']); ?></strong></li>
                                <li class="flex items-center justify-between"><span>Under review</span><strong><?php echo esc_html((string) $status_counts['under_review']); ?></strong></li>
                                <li class="flex items-center justify-between"><span>Accepted / offered</span><strong><?php echo esc_html((string) $status_counts['accepted']); ?></strong></li>
                            </ul>
                        </div>
                    </div>
                <?php elseif ($current_tab === 'applications') : ?>
                    <?php if ($app_notice !== '') : ?>
                        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 mb-4"><?php echo esc_html($app_notice); ?></div>
                    <?php endif; ?>
                    <?php if ($app_error !== '') : ?>
                        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 mb-4"><?php echo esc_html($app_error); ?></div>
                    <?php endif; ?>

                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="nds-admin-bulk-form" class="mb-4 p-4 border border-gray-200 rounded-xl bg-gray-50">
                        <input type="hidden" name="action" value="nds_portal_admin_bulk_application_action">
                        <?php wp_nonce_field('nds_admin_bulk_action', 'nds_admin_bulk_nonce'); ?>
                        <div class="flex flex-wrap items-end gap-3">
                            <label class="block">
                                <span class="block text-sm font-medium text-gray-700 mb-1">Bulk action</span>
                                <select name="bulk_action" id="nds-bulk-action" class="rounded-lg border border-gray-300 px-3 py-2 text-sm bg-white" required>
                                    <option value="">Select action</option>
                                    <option value="update_status">Update status</option>
                                    <option value="delete">Delete selected</option>
                                </select>
                            </label>
                            <label class="block" id="nds-bulk-status-wrap">
                                <span class="block text-sm font-medium text-gray-700 mb-1">New status</span>
                                <select name="new_status" id="nds-bulk-status" class="rounded-lg border border-gray-300 px-3 py-2 text-sm bg-white">
                                    <?php foreach ($filterable_statuses as $status_option) : ?>
                                        <?php if ($status_option === 'all') { continue; } ?>
                                        <option value="<?php echo esc_attr($status_option); ?>"><?php echo esc_html($status_labels[$status_option] ?? ucwords(str_replace('_', ' ', $status_option))); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <button type="submit" class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Apply to selected</button>
                            <span class="text-xs text-gray-500">Select rows below first.</span>
                        </div>
                    </form>

                    <form method="get" action="<?php echo esc_url(home_url('/administrator-portal/')); ?>" class="mb-4 flex flex-wrap items-end gap-3">
                        <input type="hidden" name="tab" value="applications">
                        <label class="block">
                            <span class="block text-sm font-medium text-gray-700 mb-1">Filter by status</span>
                            <select name="app_status" class="rounded-lg border border-gray-300 px-3 py-2 text-sm bg-white">
                                <?php foreach ($filterable_statuses as $status_option) : ?>
                                    <option value="<?php echo esc_attr($status_option); ?>" <?php selected($status_filter, $status_option); ?>><?php echo esc_html($status_option === 'all' ? 'All statuses' : ($status_labels[$status_option] ?? ucwords(str_replace('_', ' ', $status_option)))); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <button type="submit" class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Apply Filter</button>
                        <span class="text-xs text-gray-500">Showing <?php echo esc_html((string) count($applications_to_display)); ?> of <?php echo esc_html((string) $filtered_total); ?> filtered applications.</span>
                    </form>

                    <div class="overflow-x-auto border border-gray-200 rounded-xl">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                                        <label class="inline-flex items-center">
                                            <input type="checkbox" id="nds-select-all-applications" class="mr-2">
                                            Select
                                        </label>
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Application</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Applicant</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Course</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Submitted</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Current Status</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Update</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                <?php if (empty($applications_to_display)) : ?>
                                    <tr><td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500">No applications found for this filter.</td></tr>
                                <?php else : ?>
                                    <?php foreach ($applications_to_display as $application_row) : ?>
                                        <?php
                                        $row_status = (string) ($application_row['status'] ?? 'draft');
                                        $row_date = (string) ($application_row['submitted_at'] ?: $application_row['created_at']);
                                        ?>
                                        <tr>
                                            <td class="px-4 py-3 text-sm text-gray-700">
                                                <input type="checkbox" name="application_ids[]" value="<?php echo esc_attr((string) $application_row['id']); ?>" form="nds-admin-bulk-form" class="nds-application-checkbox">
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-900 font-medium"><?php echo esc_html($application_row['application_no'] ?: ('APP-' . (int) $application_row['id'])); ?></td>
                                            <td class="px-4 py-3 text-sm text-gray-700">
                                                <div class="font-medium text-gray-900"><?php echo esc_html($application_row['full_name'] ?: 'Unknown'); ?></div>
                                                <div class="text-xs text-gray-500"><?php echo esc_html($application_row['email'] ?: ''); ?></div>
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-700"><?php echo esc_html($application_row['course_name'] ?: 'Not set'); ?></td>
                                            <td class="px-4 py-3 text-sm text-gray-700"><?php echo esc_html($row_date !== '' ? date_i18n('Y-m-d H:i', strtotime($row_date)) : '-'); ?></td>
                                            <td class="px-4 py-3 text-sm"><span class="inline-flex rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700"><?php echo esc_html($status_labels[$row_status] ?? ucwords(str_replace('_', ' ', $row_status))); ?></span></td>
                                            <td class="px-4 py-3 text-sm text-gray-700">
                                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="space-y-2">
                                                    <input type="hidden" name="action" value="nds_portal_admin_update_application_status">
                                                    <input type="hidden" name="application_id" value="<?php echo esc_attr((string) $application_row['id']); ?>">
                                                    <?php wp_nonce_field('nds_admin_application_action', 'nds_admin_application_nonce'); ?>
                                                    <select name="new_status" class="w-full rounded-lg border border-gray-300 px-2 py-1 text-xs bg-white">
                                                        <?php foreach ($filterable_statuses as $status_option) : ?>
                                                            <?php if ($status_option === 'all') { continue; } ?>
                                                            <option value="<?php echo esc_attr($status_option); ?>" <?php selected($row_status, $status_option); ?>><?php echo esc_html($status_labels[$status_option] ?? ucwords(str_replace('_', ' ', $status_option))); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <textarea name="notes" rows="2" class="w-full rounded-lg border border-gray-300 px-2 py-1 text-xs" placeholder="Internal note (optional)"></textarea>
                                                    <label class="inline-flex items-center text-xs text-gray-600">
                                                        <input type="checkbox" name="notify_applicant" value="1" class="mr-2">Notify applicant by email
                                                    </label>
                                                    <button type="submit" class="inline-flex items-center rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-700">Update</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($total_pages > 1) : ?>
                        <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                            <p class="text-xs text-gray-500">
                                Page <?php echo esc_html((string) $app_page); ?> of <?php echo esc_html((string) $total_pages); ?>
                            </p>
                            <div class="flex flex-wrap items-center gap-2">
                                <?php
                                $prev_page = max(1, $app_page - 1);
                                $next_page = min($total_pages, $app_page + 1);
                                ?>
                                <a href="<?php echo esc_url(nds_administrator_portal_tab_url('applications', array('app_status' => $status_filter, 'app_page' => $prev_page))); ?>"
                                   class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 <?php echo $app_page <= 1 ? 'pointer-events-none opacity-50' : ''; ?>">
                                    Previous
                                </a>

                                <?php
                                $window_start = max(1, $app_page - 2);
                                $window_end = min($total_pages, $app_page + 2);
                                for ($page_no = $window_start; $page_no <= $window_end; $page_no++) :
                                ?>
                                    <a href="<?php echo esc_url(nds_administrator_portal_tab_url('applications', array('app_status' => $status_filter, 'app_page' => $page_no))); ?>"
                                       class="inline-flex items-center rounded-lg px-3 py-1.5 text-xs font-medium <?php echo $page_no === $app_page ? 'bg-blue-600 text-white' : 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50'; ?>">
                                        <?php echo esc_html((string) $page_no); ?>
                                    </a>
                                <?php endfor; ?>

                                <a href="<?php echo esc_url(nds_administrator_portal_tab_url('applications', array('app_status' => $status_filter, 'app_page' => $next_page))); ?>"
                                   class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 <?php echo $app_page >= $total_pages ? 'pointer-events-none opacity-50' : ''; ?>">
                                    Next
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <script>
                    (function () {
                        const selectAll = document.getElementById('nds-select-all-applications');
                        const rowChecks = document.querySelectorAll('.nds-application-checkbox');
                        const bulkAction = document.getElementById('nds-bulk-action');
                        const bulkStatusWrap = document.getElementById('nds-bulk-status-wrap');
                        const bulkForm = document.getElementById('nds-admin-bulk-form');

                        if (selectAll) {
                            selectAll.addEventListener('change', function () {
                                rowChecks.forEach(function (cb) {
                                    cb.checked = selectAll.checked;
                                });
                            });
                        }

                        rowChecks.forEach(function (cb) {
                            cb.addEventListener('change', function () {
                                if (!selectAll) {
                                    return;
                                }
                                const allChecked = Array.from(rowChecks).length > 0 && Array.from(rowChecks).every(function (item) { return item.checked; });
                                selectAll.checked = allChecked;
                            });
                        });

                        function syncBulkActionUI() {
                            if (!bulkAction || !bulkStatusWrap) {
                                return;
                            }
                            bulkStatusWrap.style.display = bulkAction.value === 'update_status' ? '' : 'none';
                        }

                        if (bulkAction) {
                            bulkAction.addEventListener('change', syncBulkActionUI);
                            syncBulkActionUI();
                        }

                        if (bulkForm) {
                            bulkForm.addEventListener('submit', function (event) {
                                const anyChecked = Array.from(rowChecks).some(function (cb) { return cb.checked; });
                                if (!anyChecked) {
                                    event.preventDefault();
                                    alert('Select at least one application.');
                                    return;
                                }

                                if (bulkAction && bulkAction.value === 'delete') {
                                    if (!window.confirm('Delete selected applications? This cannot be undone.')) {
                                        event.preventDefault();
                                    }
                                }
                            });
                        }
                    })();
                    </script>
                <?php elseif ($current_tab === 'profile') : ?>
                    <?php $profile_form_action = 'nds_portal_update_administrator_profile'; ?>
                    <?php include plugin_dir_path(__FILE__) . '../includes/partials/staff-dashboard-profile.php'; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php wp_footer(); ?>
</body>
</html>
