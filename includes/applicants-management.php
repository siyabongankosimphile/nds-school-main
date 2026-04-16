<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Applicants Management Dashboard
 */
function nds_applicants_dashboard() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    global $wpdb;
    
    // Start session for status messages
    if (!session_id()) {
        session_start();
    }
    
    // Handle bulk actions
    if (isset($_POST['bulk_action']) && isset($_POST['application_ids'])) {
        // Verify nonce for security
        if (!isset($_POST['nds_bulk_action_nonce']) || !wp_verify_nonce($_POST['nds_bulk_action_nonce'], 'nds_bulk_action')) {
            wp_die('Security check failed. Please try again.');
        }
        
        $action = sanitize_text_field($_POST['bulk_action']);
        $application_ids = array_map('intval', $_POST['application_ids']);
        
        switch ($action) {
            case 'delete':
                nds_bulk_delete_applications($application_ids);
                break;
            case 'update_status':
                $new_status = sanitize_text_field($_POST['new_status']);
                nds_bulk_update_application_status($application_ids, $new_status);
                break;
        }
    }
    
    // Handle single application actions
    if (isset($_GET['action']) && isset($_GET['id'])) {
        $action = sanitize_text_field($_GET['action']);
        $id = intval($_GET['id']);

        if (in_array($action, array('delete', 'convert_to_student'), true)) {
            $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
            if (!$nonce || !wp_verify_nonce($nonce, 'nds_applicants_action_' . $action . '_' . $id)) {
                wp_die('Security check failed. Please try again.');
            }
        }

        switch ($action) {
            case 'delete':
                nds_delete_application($id);
                wp_redirect(admin_url('admin.php?page=nds-applicants&deleted=1'));
                exit;
            case 'view':
                nds_view_application_details($id);
                return;
            case 'convert_to_student':
                nds_convert_application_to_student($id);
                break;
        }
    }
    
    // Handle status updates (from main Applications dashboard)
    if (isset($_POST['update_status']) && isset($_POST['application_id'])) {
        // Verify nonce for security
        if (!isset($_POST['nds_status_update_nonce']) || !wp_verify_nonce($_POST['nds_status_update_nonce'], 'nds_status_update')) {
            wp_die('Security check failed. Please try again.');
        }
        
        $application_id = intval($_POST['application_id']);
        $new_status     = sanitize_text_field($_POST['new_status']);
        $notes          = sanitize_textarea_field($_POST['notes'] ?? '');
        $notify_applicant = !empty($_POST['notify_applicant']);
        
        // Debug logging
        error_log("NDS Status Update (dashboard) - ID: $application_id, Status: $new_status, Notes: $notes, Notify: " . ($notify_applicant ? 'yes' : 'no'));
        
        $result = nds_update_application_status($application_id, $new_status, $notes, $notify_applicant);
        
        if ($result) {
            // Redirect to prevent form resubmission
            wp_redirect(admin_url('admin.php?page=nds-applicants&updated=1'));
            exit;
        }
    }
    
    // Get applications with pagination
    $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $per_page = 20;
    $offset = ($page - 1) * $per_page;
    
    // Get total count - must match the applications query exactly
    // Business rule:
    // - Exclude applications that have been fully accepted OR already converted to students.
    // - Active list only shows in‑flight applications.
    // - Use COUNT(DISTINCT) to handle potential JOIN duplicates
    $total_count = $wpdb->get_var("
        SELECT COUNT(DISTINCT a.id)
        FROM {$wpdb->prefix}nds_applications a
        LEFT JOIN {$wpdb->prefix}nds_application_forms af ON a.id = af.application_id
        WHERE a.status != 'converted_to_student'
          AND a.status != 'accepted'
    ");
    
    // Get applications with form data (same filter as total_count)
    // Use GROUP BY to prevent duplicates if multiple form records exist per application
    $applications = $wpdb->get_results($wpdb->prepare("
        SELECT 
            a.*,
            af.full_name,
            af.email,
            af.course_name,
            af.cell_no as phone,
            af.submitted_at as form_submitted_at
        FROM {$wpdb->prefix}nds_applications a
        LEFT JOIN {$wpdb->prefix}nds_application_forms af ON a.id = af.application_id
        WHERE a.status != 'converted_to_student'
          AND a.status != 'accepted'
        GROUP BY a.id
        ORDER BY a.submitted_at DESC
        LIMIT %d OFFSET %d
    ", $per_page, $offset), ARRAY_A);
    
    // Load Tailwind CSS (shared frontend bundle)
    $plugin_dir = plugin_dir_path(dirname(__FILE__));
    $css_file   = $plugin_dir . 'assets/css/frontend.css';
    if (file_exists($css_file)) {
        wp_enqueue_style(
            'nds-tailwindcss-applicants',
            plugin_dir_url(dirname(__FILE__)) . 'assets/css/frontend.css',
            array(),
            filemtime($css_file),
            'all'
        );
        wp_add_inline_style('nds-tailwindcss-applicants', '
            #wpcontent,
            #wpbody-content {
                background-color: #f9fafb !important;
            }
            .nds-tailwind-wrapper {
                all: initial !important;
                display: block !important;
                width: 100% !important;
                min-height: 100vh !important;
                background-color: #f9fafb !important;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
            }
            .nds-tailwind-wrapper * { box-sizing: border-box !important; }
            .nds-tailwind-wrapper .bg-white { background-color: #ffffff !important; }
            .nds-tailwind-wrapper .text-gray-900 { color: #111827 !important; }
            .nds-tailwind-wrapper .text-gray-600 { color: #4b5563 !important; }
            .nds-tailwind-wrapper .rounded-xl { border-radius: 0.75rem !important; }
            .nds-tailwind-wrapper .shadow-sm { box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05) !important; }
            .nds-tailwind-wrapper .border { border-width: 1px !important; }
            .nds-tailwind-wrapper .border-gray-100 { border-color: #f3f4f6 !important; }
            .nds-tailwind-wrapper .border-gray-200 { border-color: #e5e7eb !important; }
        ');
    }
    wp_enqueue_style('nds-icons', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css', array(), null, 'all');

    // Build status stats lists for modals
    $all_active_applications = $wpdb->get_results("
        SELECT a.id, a.application_no, af.full_name, af.course_name, a.status, a.submitted_at
        FROM {$wpdb->prefix}nds_applications a
        LEFT JOIN {$wpdb->prefix}nds_application_forms af ON a.id = af.application_id
        WHERE a.status != 'converted_to_student'
          AND a.status != 'accepted'
        GROUP BY a.id
        ORDER BY a.submitted_at DESC
    ", ARRAY_A);

    $submitted_list    = array_values(array_filter($all_active_applications, function($a) { return $a['status'] === 'submitted'; }));
    $under_review_list = array_values(array_filter($all_active_applications, function($a) { return $a['status'] === 'under_review'; }));
    $accepted_list     = array_values(array_filter($all_active_applications, function($a) { 
        return in_array($a['status'], array('accepted', 'offer_made', 'conditional_offer')); 
    }));

    $total_applications   = count($all_active_applications);
    $submitted_count      = count($submitted_list);
    $under_review_count   = count($under_review_list);
    $accepted_count       = count($accepted_list);
    ?>
    <style>
        /* Ensure the WordPress footer doesn't overlap our custom dashboard */
        body[class*="nds-applicants"] #wpfooter, body[class*="nds-applications"] #wpfooter { display: none !important; }
        .nds-tailwind-wrapper { position: relative; z-index: 1; }
    </style>
    <div class="nds-tailwind-wrapper bg-gray-50 pb-32" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin-left: -20px; padding-left: 20px; margin-top: -20px;">
        <!-- Header -->
        <div class="bg-white shadow-sm border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center py-6">
                    <div class="flex items-center space-x-4">
                        <div class="w-12 h-12 bg-gradient-to-br from-blue-600 to-purple-600 rounded-xl flex items-center justify-center">
                            <span class="dashicons dashicons-clipboard text-white text-2xl"></span>
                        </div>
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900">Applications Management</h1>
                            <p class="text-gray-600 text-sm sm:text-base">
                                Review, update, and convert applications into student records.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
            <!-- Application actions / quick controls -->
            <div class="bg-white shadow-sm rounded-xl border border-gray-100 p-5 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h2 class="text-sm font-semibold text-gray-900">Manage this application</h2>
                    <p class="text-xs text-gray-500">
                        Use the quick actions to move this application forward and optionally notify the applicant by email.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <p class="text-xs text-gray-500">Select applications from the list below to manage them.</p>
                </div>
            </div>
            <!-- Flash messages -->
        <?php if (isset($_SESSION['nds_status_update_success'])): ?>
                <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-4 flex items-center">
                    <span class="dashicons dashicons-yes-alt text-emerald-600 mr-3 text-xl"></span>
                    <p class="text-sm text-emerald-800"><?php echo esc_html($_SESSION['nds_status_update_success']); ?></p>
                </div>
            <?php unset($_SESSION['nds_status_update_success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['nds_status_update_error'])): ?>
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 flex items-center">
                    <span class="dashicons dashicons-warning text-red-600 mr-3 text-xl"></span>
                    <p class="text-sm text-red-800"><?php echo esc_html($_SESSION['nds_status_update_error']); ?></p>
                </div>
            <?php unset($_SESSION['nds_status_update_error']); ?>
        <?php endif; ?>
        
            <!-- KPI Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Total Applications -->
                <div onclick="openStatModal('total')" class="bg-white shadow-sm rounded-xl p-5 border border-gray-100 flex flex-col justify-between hover:bg-gray-50 transition-all duration-200 cursor-pointer group">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500 group-hover:text-gray-700">Total Applications</p>
                            <p class="mt-2 text-2xl font-semibold text-gray-900">
                                <?php echo number_format_i18n($total_applications); ?>
                            </p>
                        </div>
                        <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center">
                            <i class="fas fa-clipboard-list text-blue-600 text-xl"></i>
                        </div>
                    </div>
                </div>

                <!-- Submitted -->
                <div onclick="openStatModal('submitted')" class="bg-white shadow-sm rounded-xl p-5 border border-gray-100 flex flex-col justify-between hover:bg-gray-50 transition-all duration-200 cursor-pointer group">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500 group-hover:text-gray-700">Submitted</p>
                            <p class="mt-2 text-2xl font-semibold text-gray-900">
                                <?php echo number_format_i18n($submitted_count); ?>
                            </p>
                        </div>
                        <div class="w-10 h-10 rounded-lg bg-indigo-50 flex items-center justify-center">
                            <i class="fas fa-file-signature text-indigo-600 text-xl"></i>
                        </div>
                    </div>
                </div>

                <!-- Under Review -->
                <div onclick="openStatModal('review')" class="bg-white shadow-sm rounded-xl p-5 border border-gray-100 flex flex-col justify-between hover:bg-gray-50 transition-all duration-200 cursor-pointer group">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500 group-hover:text-gray-700">Under Review</p>
                            <p class="mt-2 text-2xl font-semibold text-gray-900">
                                <?php echo number_format_i18n($under_review_count); ?>
                            </p>
                        </div>
                        <div class="w-10 h-10 rounded-lg bg-amber-50 flex items-center justify-center">
                            <i class="fas fa-search text-amber-600 text-xl"></i>
                        </div>
                    </div>
                </div>
        
                <!-- Accepted -->
                <div onclick="openStatModal('accepted')" class="bg-white shadow-sm rounded-xl p-5 border border-gray-100 flex flex-col justify-between hover:bg-gray-50 transition-all duration-200 cursor-pointer group">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500 group-hover:text-gray-700">Accepted</p>
                            <p class="mt-2 text-2xl font-semibold text-gray-900">
                                <?php echo number_format_i18n($accepted_count); ?>
                            </p>
                        </div>
                        <div class="w-10 h-10 rounded-lg bg-emerald-50 flex items-center justify-center">
                            <i class="fas fa-check-double text-emerald-600 text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Applications table + filters -->
            <div class="bg-white shadow-sm rounded-xl border border-gray-100 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-semibold text-gray-900">Applications</h2>
                        <p class="text-xs text-gray-500">All active applications (excluding converted students).</p>
                    </div>
                </div>

                <div class="px-5 py-3 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <form method="post" id="bulk-actions-form" class="flex items-center gap-2">
                        <?php wp_nonce_field('nds_bulk_action', 'nds_bulk_action_nonce'); ?>
                        <select name="bulk_action" id="bulk-action-selector" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">Bulk actions</option>
                            <option value="update_status">Update status</option>
                            <option value="delete">Delete</option>
                        </select>
                        <button type="submit" id="bulk-action-apply"
                                class="inline-flex items-center px-3 py-2 rounded-lg bg-gray-100 text-gray-600 text-xs font-medium hover:bg-gray-200 disabled:opacity-50 disabled:cursor-not-allowed"
                                disabled>
                            Apply
                        </button>
                    </form>

                    <div class="text-xs text-gray-500">
                        Page <?php echo number_format_i18n($page); ?> of <?php echo number_format_i18n(max(1, ceil($total_count / $per_page))); ?>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-5 py-2">
                                    <input type="checkbox" id="select-all-applications" class="rounded border-gray-300">
                                </th>
                                <th class="px-5 py-2 text-left font-medium text-gray-500 text-xs uppercase tracking-wide">App #</th>
                                <th class="px-5 py-2 text-left font-medium text-gray-500 text-xs uppercase tracking-wide">Name</th>
                                <th class="px-5 py-2 text-left font-medium text-gray-500 text-xs uppercase tracking-wide">Email</th>
                                <th class="px-5 py-2 text-left font-medium text-gray-500 text-xs uppercase tracking-wide">Course</th>
                                <th class="px-5 py-2 text-left font-medium text-gray-500 text-xs uppercase tracking-wide">Status</th>
                                <th class="px-5 py-2 text-left font-medium text-gray-500 text-xs uppercase tracking-wide">Submitted</th>
                                <th class="px-5 py-2 text-right font-medium text-gray-500 text-xs uppercase tracking-wide">Actions</th>
                        </tr>
                    </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            <?php if (!empty($applications)): ?>
                        <?php foreach ($applications as $app): ?>
                        <tr>
                                        <td class="px-5 py-2 align-top">
                                            <input type="checkbox" name="application_ids[]" value="<?php echo $app['id']; ?>" class="application-checkbox rounded border-gray-300">
                                        </td>
                                        <td class="px-5 py-2 whitespace-nowrap text-gray-900 font-medium">
                                            <?php echo esc_html($app['application_no']); ?>
                                        </td>
                                        <td class="px-5 py-2 whitespace-nowrap text-gray-900">
                                            <?php echo esc_html($app['full_name']); ?>
                                        </td>
                                        <td class="px-5 py-2 whitespace-nowrap text-gray-700">
                                            <?php echo esc_html($app['email']); ?>
                                        </td>
                                        <td class="px-5 py-2 whitespace-nowrap text-gray-700">
                                            <?php echo esc_html($app['course_name']); ?>
                            </td>
                                        <td class="px-5 py-2 whitespace-nowrap">
                                            <?php
                                            $status = $app['status'];
                                            $status_label = ucfirst(str_replace('_', ' ', $status));
                                            $status_classes = 'bg-gray-50 text-gray-700';
                                            if ($status === 'submitted') {
                                                $status_classes = 'bg-blue-50 text-blue-700';
                                            } elseif ($status === 'under_review') {
                                                $status_classes = 'bg-amber-50 text-amber-700';
                                            } elseif ($status === 'accepted' || $status === 'offer_made' || $status === 'conditional_offer') {
                                                $status_classes = 'bg-emerald-50 text-emerald-700';
                                            } elseif (in_array($status, array('rejected', 'declined'), true)) {
                                                $status_classes = 'bg-red-50 text-red-700';
                                            } elseif (in_array($status, array('withdrawn', 'expired'), true)) {
                                                $status_classes = 'bg-gray-50 text-gray-600';
                                            }
                                            ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?php echo esc_attr($status_classes); ?>">
                                                <?php echo esc_html($status_label); ?>
                                </span>
                            </td>
                                        <td class="px-5 py-2 whitespace-nowrap text-xs text-gray-500">
                                            <?php echo esc_html(date_i18n('Y-m-d H:i', strtotime($app['submitted_at']))); ?>
                                        </td>
                                        <td class="px-5 py-2 whitespace-nowrap text-right">
                                            <div class="inline-flex items-center gap-2">
                                                <a href="<?php echo admin_url('admin.php?page=nds-applicants&action=view&id=' . $app['id']); ?>"
                                                   class="inline-flex items-center px-3 py-1.5 rounded-lg border border-gray-200 text-xs font-medium text-gray-700 hover:border-indigo-400 hover:bg-indigo-50">
                                                    <span class="dashicons dashicons-visibility text-xs mr-1"></span>
                                                    View
                                                </a>
                                                <button type="button"
                                                        class="inline-flex items-center px-3 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium update-status-btn"
                                                        data-id="<?php echo $app['id']; ?>"
                                                        data-status="<?php echo esc_attr($app['status']); ?>">
                                                    <span class="dashicons dashicons-edit text-xs mr-1"></span>
                                                    Update
                                                </button>
                                <?php if ($app['status'] === 'accepted'): ?>
                                                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=nds-applicants&action=convert_to_student&id=' . $app['id']), 'nds_applicants_action_convert_to_student_' . $app['id'])); ?>"
                                                       class="inline-flex items-center px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-medium"
                                                       onclick="return confirm('Convert this application to a student record? This will remove it from applications.');">
                                                        <span class="dashicons dashicons-migrate text-xs mr-1"></span>
                                                        Convert
                                                    </a>
                                <?php endif; ?>
                                                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=nds-applicants&action=delete&id=' . $app['id']), 'nds_applicants_action_delete_' . $app['id'])); ?>"
                                                   class="inline-flex items-center px-3 py-1.5 rounded-lg bg-red-50 hover:bg-red-100 text-red-700 text-xs font-medium"
                                                   onclick="return confirm('Are you sure you want to delete this application?');">
                                                    <span class="dashicons dashicons-trash text-xs mr-1"></span>
                                                    Delete
                                                </a>
                                            </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="px-5 py-6 text-center text-sm text-gray-500">
                                        No applications found.
                                    </td>
                                </tr>
                            <?php endif; ?>
                    </tbody>
                </table>
            </div>
        
        <!-- Pagination -->
        <?php
        $total_pages = ceil($total_count / $per_page);
                if ($total_pages > 1) :
                ?>
                    <div class="px-5 py-4 border-t border-gray-100 flex items-center justify-between text-xs text-gray-500">
                        <div>
                            Showing <?php echo number_format_i18n(min($per_page, $total_count)); ?> of <?php echo number_format_i18n($total_count); ?> applications
                        </div>
                        <div class="space-x-2">
                            <?php
                            echo paginate_links(array(
                                'base'      => add_query_arg('paged', '%#%'),
                                'format'    => '',
                'prev_text' => '&laquo;',
                'next_text' => '&raquo;',
                                'total'     => $total_pages,
                                'current'   => $page,
                            ));
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Status Update Modal -->
    <div id="status-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden" style="display: none;">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-xl shadow-xl max-w-lg w-full max-h-[90vh] overflow-y-auto">
                <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                    <h3 class="text-xl font-semibold text-gray-900">Update Application Status</h3>
                    <button type="button" class="text-gray-400 hover:text-gray-600" onclick="ndsCloseStatusModal()">
                        <span class="dashicons dashicons-no-alt text-xl"></span>
                    </button>
                </div>
                <div class="p-6">
                    <form method="post" class="space-y-4">
                        <?php wp_nonce_field('nds_status_update', 'nds_status_update_nonce'); ?>
                        <input type="hidden" name="application_id" id="modal-application-id">

                        <div>
                            <label for="new_status" class="block text-sm font-semibold text-gray-900 mb-2">New status</label>
                            <select name="new_status" id="new_status" required
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm">
                                <option value="submitted">Submitted</option>
                                <option value="under_review">Under Review</option>
                                <option value="accepted">Accepted</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>

                        <div>
                            <label for="notes" class="block text-sm font-semibold text-gray-900 mb-2">Notes</label>
                            <textarea name="notes" id="notes" rows="3"
                                      class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                                      placeholder="Add any review notes or context for this decision..."></textarea>
                        </div>

                        <div>
                            <label class="flex items-center space-x-3 cursor-pointer">
                                <input type="checkbox" name="notify_applicant" id="notify_applicant" value="1" checked
                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <span class="text-sm font-medium text-gray-900">Notify applicant via email</span>
                            </label>
                        </div>

                        <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-200">
                            <button type="button"
                                    class="px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium"
                                    onclick="ndsCloseStatusModal()">
                                Cancel
                            </button>
                            <button type="submit" name="update_status"
                                    class="inline-flex items-center px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium">
                                <span class="dashicons dashicons-yes-alt text-sm mr-1"></span>
                                Update status
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Drill-down Stat Modal -->
    <div id="drillDownModal" class="hidden" style="position:fixed; inset:0; z-index:999999; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
        <div style="position:fixed; inset:0; background:rgba(0,0,0,0.5);" onclick="closeDrillDownModal()"></div>
        <div style="position:fixed; inset:0; display:flex; align-items:center; justify-content:center; padding:1rem;">
            <div style="background:#fff; border-radius:1rem; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25); width:100%; max-width:42rem; max-height:80vh; display:flex; flex-direction:column; position:relative;">
                <!-- Modal Header -->
                <div style="display:flex; align-items:center; justify-content:space-between; padding:1rem 1.5rem; border-bottom:1px solid #e5e7eb;">
                    <div style="display:flex; align-items:center; gap:0.75rem;">
                        <div id="drillModalIconBg" style="width:2.5rem; height:2.5rem; border-radius:0.5rem; display:flex; align-items:center; justify-content:center;">
                            <i id="drillModalIcon" style="font-size:1.25rem;"></i>
                        </div>
                        <div>
                            <h3 id="drillModalTitle" style="font-size:1.125rem; font-weight:700; color:#111827; margin:0;"></h3>
                            <p id="drillModalCount" style="font-size:0.875rem; color:#6b7280; margin:0;"></p>
                        </div>
                    </div>
                    <button onclick="closeDrillDownModal()" style="color:#9ca3af; padding:0.5rem; border-radius:0.5rem; border:none; background:none; cursor:pointer;" onmouseover="this.style.color='#4b5563'; this.style.background='#f3f4f6'" onmouseout="this.style.color='#9ca3af'; this.style.background='none'">
                        <i class="fas fa-times" style="font-size:1.25rem;"></i>
                    </button>
                </div>
                <!-- Modal Body -->
                <div style="overflow-y:auto; flex:1; padding:0.5rem;">
                    <table style="width:100%; border-collapse:collapse;">
                        <thead style="background:#f9fafb; position:sticky; top:0; z-index:10;">
                            <tr>
                                <th style="padding:0.75rem 1rem; text-align:left; font-size:0.75rem; font-weight:500; color:#6b7280; text-transform:uppercase;">Applicant</th>
                                <th style="padding:0.75rem 1rem; text-align:left; font-size:0.75rem; font-weight:500; color:#6b7280; text-transform:uppercase;">Course</th>
                                <th style="padding:0.75rem 1rem; text-align:left; font-size:0.75rem; font-weight:500; color:#6b7280; text-transform:uppercase;">Date</th>
                            </tr>
                        </thead>
                        <tbody id="drillModalBody"></tbody>
                    </table>
                </div>
                <!-- Modal Footer -->
                <div style="padding:0.75rem 1.5rem; border-top:1px solid #e5e7eb; background:#f9fafb; border-radius:0 0 1rem 1rem; text-align:right;">
                    <button onclick="closeDrillDownModal()" style="padding:0.5rem 1rem; font-size:0.875rem; font-weight:500; color:#374151; background:#fff; border:1px solid #d1d5db; border-radius:0.5rem; cursor:pointer;" onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background='#fff'">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // --- DRILL-DOWN MODAL LOGIC ---
        const statsData = {
            total: <?php echo json_encode($all_active_applications); ?>,
            submitted: <?php echo json_encode($submitted_list); ?>,
            review: <?php echo json_encode($under_review_list); ?>,
            accepted: <?php echo json_encode($accepted_list); ?>
        };

        const modalConfig = {
            total: { title: 'All Applications', icon: 'fas fa-clipboard-list', iconColor: '#2563eb', iconBg: '#eff6ff' },
            submitted: { title: 'Submitted Applications', icon: 'fas fa-file-signature', iconColor: '#4f46e5', iconBg: '#eef2ff' },
            review: { title: 'Applications Under Review', icon: 'fas fa-search', iconColor: '#d97706', iconBg: '#fffbeb' },
            accepted: { title: 'Accepted Applications', icon: 'fas fa-check-double', iconColor: '#059669', iconBg: '#ecfdf5' }
        };

        window.openStatModal = function(type) {
            const modal = document.getElementById('drillDownModal');
            const config = modalConfig[type];
            const data = statsData[type];
            
            if (!modal || !config || !data) return;

            document.getElementById('drillModalTitle').textContent = config.title;
            document.getElementById('drillModalCount').textContent = data.length + ' application' + (data.length !== 1 ? 's' : '');
            
            const icon = document.getElementById('drillModalIcon');
            const iconBg = document.getElementById('drillModalIconBg');
            icon.className = config.icon;
            icon.style.color = config.iconColor;
            iconBg.style.backgroundColor = config.iconBg;

            const tbody = document.getElementById('drillModalBody');
            tbody.innerHTML = '';
            
            data.forEach(item => {
                const row = document.createElement('tr');
                row.style.cssText = 'border-bottom:1px solid #f3f4f6; transition: background 0.15s; cursor: pointer;';
                row.onclick = () => window.location.href = `<?php echo admin_url('admin.php?page=nds-applicants&action=view&id='); ?>${item.id}`;
                row.onmouseover = function() { this.style.background = '#f9fafb'; };
                row.onmouseout = function() { this.style.background = ''; };
                
                const date = new Date(item.submitted_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });

                row.innerHTML = `
                    <td style="padding:0.75rem 1rem;">
                        <div style="font-size:0.875rem; font-weight:600; color:#111827;">${item.full_name || 'N/A'}</div>
                        <div style="font-size:0.75rem; color:#6b7280;">${item.application_no}</div>
                    </td>
                    <td style="padding:0.75rem 1rem; font-size:0.875rem; color:#4b5563;">${item.course_name || 'N/A'}</td>
                    <td style="padding:0.75rem 1rem; font-size:0.875rem; color:#6b7280;">${date}</td>
                `;
                tbody.appendChild(row);
            });

            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        };

        window.closeDrillDownModal = function() {
            const modal = document.getElementById('drillDownModal');
            if (modal) {
                modal.classList.add('hidden');
                document.body.style.overflow = '';
            }
        };

        // --- EXISTING MODAL LOGIC ---
        const selectAllCheckbox = document.getElementById('select-all-applications');
        const bulkActionButton = document.getElementById('bulk-action-apply');
        const bulkActionSelector = document.getElementById('bulk-action-selector');
        const statusModal = document.getElementById('status-modal');

        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                const checkboxes = document.querySelectorAll('.application-checkbox');
                checkboxes.forEach(cb => cb.checked = this.checked);
                updateBulkActionButton();
            });
        }
        
        document.querySelectorAll('.application-checkbox').forEach(cb => {
            cb.addEventListener('change', updateBulkActionButton);
        });

        if (bulkActionSelector) {
            bulkActionSelector.addEventListener('change', updateBulkActionButton);
        }

        function updateBulkActionButton() {
            const checked = document.querySelectorAll('.application-checkbox:checked');
            const selectedAction = bulkActionSelector ? bulkActionSelector.value : '';
            if (bulkActionButton) {
                bulkActionButton.disabled = checked.length === 0 || !selectedAction;
            }
        }
        
        if (statusModal && statusModal.parentElement !== document.body) {
            document.body.appendChild(statusModal);
        }

        window.openStatusModal = function(id, status) {
            const modal = document.getElementById('status-modal');
            const idField = document.getElementById('modal-application-id');
            const statusField = document.getElementById('new_status');

            if (idField && id) idField.value = id;
            if (statusField && status) statusField.value = status;

            if (modal) {
                modal.classList.remove('hidden');
                modal.style.display = 'flex';
                document.body.style.overflow = 'hidden';
            }
        };
        
        document.querySelectorAll('.update-status-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                openStatusModal(this.getAttribute('data-id'), this.getAttribute('data-status'));
            });
        });
        
        if (statusModal) {
            statusModal.addEventListener('click', function(e) {
                if (e.target === statusModal) ndsCloseStatusModal();
            });
        }
    });

    function ndsCloseStatusModal() {
        const statusModal = document.getElementById('status-modal');
        if (statusModal) {
            statusModal.classList.add('hidden');
            statusModal.style.display = 'none';
            document.body.style.overflow = '';
        }
    }
    </script>
    <?php
}

/**
 * View Application Details
 */
function nds_view_application_details($application_id) {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    global $wpdb;

    // Handle status updates submitted from this details screen
    if (isset($_POST['update_status']) && isset($_POST['application_id'])) {
        // Verify nonce for security
        if (!isset($_POST['nds_status_update_nonce']) || !wp_verify_nonce($_POST['nds_status_update_nonce'], 'nds_status_update')) {
            wp_die('Security check failed. Please try again.');
        }

        $application_id = intval($_POST['application_id']);
        $new_status     = sanitize_text_field($_POST['new_status']);
        $notes          = sanitize_textarea_field($_POST['notes'] ?? '');
        $notify_applicant = !empty($_POST['notify_applicant']);

        // Debug logging
        error_log("NDS Status Update (details view) - ID: $application_id, Status: $new_status, Notes: $notes, Notify: " . ($notify_applicant ? 'yes' : 'no'));

        $result = nds_update_application_status($application_id, $new_status, $notes, $notify_applicant);

        if ($result) {
            // Redirect back to this details screen to prevent form resubmission
            wp_redirect(
                add_query_arg(
                    array(
                        'page'   => 'nds-applicants',
                        'action' => 'view',
                        'id'     => $application_id,
                        'updated' => 1,
                    ),
                    admin_url('admin.php')
                )
            );
            exit;
        }
    }
    
    $application = $wpdb->get_row($wpdb->prepare("
        SELECT 
            a.*,
            af.*
        FROM {$wpdb->prefix}nds_applications a
        LEFT JOIN {$wpdb->prefix}nds_application_forms af ON a.id = af.application_id
        WHERE a.id = %d
    ", $application_id), ARRAY_A);
    
    if (!$application) {
        wp_die('Application not found');
    }


    // Load Tailwind CSS (shared admin bundle) so this page matches the Applications dashboard
    $plugin_dir = plugin_dir_path(dirname(__FILE__));
    $css_file   = $plugin_dir . 'assets/css/frontend.css';
    if (file_exists($css_file)) {
        wp_enqueue_style(
            'nds-tailwindcss-applicants-details',
            plugin_dir_url(dirname(__FILE__)) . 'assets/css/frontend.css',
            array(),
            filemtime($css_file),
            'all'
        );
        wp_add_inline_style('nds-tailwindcss-applicants-details', '
            #wpcontent,
            #wpbody-content {
                background-color: #f9fafb !important;
            }
            .nds-tailwind-wrapper {
                all: initial !important;
                display: block !important;
                width: 100% !important;
                min-height: 100vh !important;
                background-color: #f9fafb !important;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
            }
            .nds-tailwind-wrapper * { box-sizing: border-box !important; }
        ');
    }
    wp_enqueue_style('nds-icons', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css', array(), null, 'all');

    // Helper for status badge
    $status        = $application['status'];
    $status_label  = ucfirst(str_replace('_', ' ', $status));
    $status_class  = 'bg-gray-50 text-gray-700';
    if ($status === 'submitted') {
        $status_class = 'bg-blue-50 text-blue-700';
    } elseif ($status === 'under_review') {
        $status_class = 'bg-amber-50 text-amber-700';
    } elseif (in_array($status, array('accepted', 'offer_made', 'conditional_offer'), true)) {
        $status_class = 'bg-emerald-50 text-emerald-700';
    } elseif (in_array($status, array('rejected', 'declined'), true)) {
        $status_class = 'bg-red-50 text-red-700';
    } elseif (in_array($status, array('withdrawn', 'expired'), true)) {
        $status_class = 'bg-gray-50 text-gray-600';
    }

    ?>
    <div class="nds-tailwind-wrapper bg-gray-50 pb-32" style="margin-left: -20px; padding-left: 20px; margin-top: -20px;">
        <!-- Header -->
        <div class="bg-white shadow-sm border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center py-6">
                    <div class="flex items-center space-x-4">
                        <div class="w-12 h-12 bg-gradient-to-br from-blue-600 to-purple-600 rounded-xl flex items-center justify-center">
                            <span class="dashicons dashicons-clipboard text-white text-2xl"></span>
                        </div>
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900">
                                Application <?php echo esc_html($application['application_no']); ?>
                            </h1>
                            <p class="text-gray-600 text-sm sm:text-base">
                                Full details for this application, including contact and emergency information.
                            </p>
                        </div>
                    </div>
                        <div class="flex items-center gap-3">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium <?php echo esc_attr($status_class); ?>">
                            <?php echo esc_html($status_label); ?>
                        </span>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=nds-applicants')); ?>"
                           class="inline-flex items-center px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium">
                            <span class="dashicons dashicons-arrow-left-alt2 text-sm mr-1"></span>
                            Back to applications
                        </a>
                        <button type="button"
                                class="inline-flex items-center px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium update-status-btn"
                                data-id="<?php echo esc_attr($application['id']); ?>"
                                data-status="<?php echo esc_attr($application['status']); ?>">
                            <span class="dashicons dashicons-edit text-sm mr-1"></span>
                            Update status
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
            <!-- Application actions / quick controls -->
            <div class="bg-white shadow-sm rounded-xl border border-gray-100 p-5 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h2 class="text-sm font-semibold text-gray-900">Manage this application</h2>
                    <p class="text-xs text-gray-500">
                        Use the quick actions to move this application forward and optionally notify the applicant by email.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button"
                            class="inline-flex items-center px-3 py-2 rounded-lg border border-gray-200 text-xs font-medium text-gray-700 hover:border-blue-500 hover:bg-blue-50 nds-quick-status-btn"
                            data-id="<?php echo esc_attr($application['id']); ?>"
                            data-status="under_review">
                        <span class="dashicons dashicons-visibility text-xs mr-1"></span>
                        Mark as Under Review
                    </button>
                    <button type="button"
                            class="inline-flex items-center px-3 py-2 rounded-lg border border-emerald-500 text-xs font-medium text-emerald-700 bg-emerald-50 hover:bg-emerald-100 nds-quick-status-btn"
                            data-id="<?php echo esc_attr($application['id']); ?>"
                            data-status="accepted">
                        <span class="dashicons dashicons-yes-alt text-xs mr-1"></span>
                        Accept Application
                    </button>
                    <?php
                    // Only show enrollment / revert actions once an application is accepted
                    if ($application['status'] === 'accepted') :
                        global $wpdb;
                        $student_id = !empty($application['student_id']) ? (int) $application['student_id'] : 0;
                        $has_enrollments = false;
                        if ($student_id > 0) {
                            $enrollment_count = $wpdb->get_var($wpdb->prepare(
                                "SELECT COUNT(*) FROM {$wpdb->prefix}nds_student_enrollments WHERE student_id = %d",
                                $student_id
                            ));
                            $has_enrollments = $enrollment_count > 0;
                        }

                        // If there is a learner but no enrollments yet, offer a manual "Enroll in Course" action
                        if ($student_id > 0 && !$has_enrollments) :
                    ?>
                            <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=nds_manual_enroll_from_application&application_id=' . intval($application['id'])), 'nds_manual_enroll_' . intval($application['id'])); ?>"
                               class="inline-flex items-center px-3 py-2 rounded-lg border border-blue-500 text-xs font-medium text-blue-700 bg-blue-50 hover:bg-blue-100"
                               onclick="return confirm('This will enroll the student in the course(s) from their application. Continue?');">
                                <span class="dashicons dashicons-groups text-xs mr-1"></span>
                                Enroll in Course
                            </a>
                    <?php
                        endif;

                        // If a learner record exists at all, offer a "Revert to Applicant" action
                        if ($student_id > 0) :
                    ?>
                            <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=nds_revert_student_from_application&application_id=' . intval($application['id'])), 'nds_revert_student_' . intval($application['id'])); ?>"
                               class="inline-flex items-center px-3 py-2 rounded-lg border border-red-500 text-xs font-medium text-red-700 bg-red-50 hover:bg-red-100"
                               onclick="return confirm('This will delete the learner record and any enrollments, and return this person to applicant-only. Continue?');">
                                <span class="dashicons dashicons-undo text-xs mr-1"></span>
                                Revert to Applicant
                            </a>
                    <?php
                        endif;
                    endif;
                    ?>
                    <button type="button"
                            class="inline-flex items-center px-3 py-2 rounded-lg border border-red-400 text-xs font-medium text-red-700 bg-red-50 hover:bg-red-100 nds-quick-status-btn"
                            data-id="<?php echo esc_attr($application['id']); ?>"
                            data-status="rejected">
                        <span class="dashicons dashicons-dismiss text-xs mr-1"></span>
                        Reject Application
                    </button>
                    <button type="button"
                            class="inline-flex items-center px-3 py-2 rounded-lg border border-amber-400 text-xs font-medium text-amber-700 bg-amber-50 hover:bg-amber-100 nds-quick-status-btn"
                            data-id="<?php echo esc_attr($application['id']); ?>"
                            data-status="waitlisted">
                        <span class="dashicons dashicons-clock text-xs mr-1"></span>
                        Waitlist
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Application Info -->
                <div class="bg-white shadow-sm rounded-xl border border-gray-100 overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                        <div>
                            <h2 class="text-sm font-semibold text-gray-900">Application Information</h2>
                            <p class="text-xs text-gray-500">Core metadata and course selection.</p>
                        </div>
                    </div>
                    <div class="p-5 space-y-3 text-sm text-gray-700">
                        <div class="flex justify-between">
                            <span class="text-gray-500">Application Number</span>
                            <span class="font-medium text-gray-900"><?php echo esc_html($application['application_no']); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Submitted</span>
                            <span class="text-xs text-gray-600">
                                <?php echo esc_html(date_i18n('Y-m-d H:i', strtotime($application['submitted_at']))); ?>
                    </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Course</span>
                            <span class="font-medium text-gray-900">
                                <?php echo esc_html($application['course_name']); ?>
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Level</span>
                            <span class="font-medium text-gray-900"><?php echo esc_html($application['level'] ?? 'Not provided'); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Source</span>
                            <span class="font-medium text-gray-900"><?php echo esc_html($application['source'] ?? 'Not provided'); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Program ID</span>
                            <span class="font-medium text-gray-900"><?php echo esc_html($application['program_id'] ?? 'Not linked'); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Student ID</span>
                            <span class="font-medium text-gray-900"><?php echo esc_html($application['student_id'] ?? 'Not assigned'); ?></span>
                        </div>
                        <?php if (!empty($application['notes'])) : ?>
                            <div class="pt-2 border-t border-gray-100">
                                <p class="text-xs font-semibold text-gray-500 mb-1">Internal Notes</p>
                                <p class="text-sm text-gray-700 whitespace-pre-line">
                                    <?php echo esc_html($application['notes']); ?>
                                </p>
                            </div>
                <?php endif; ?>
                    </div>
            </div>
            
            <!-- Personal Details -->
                <div class="bg-white shadow-sm rounded-xl border border-gray-100 overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                        <div>
                            <h2 class="text-sm font-semibold text-gray-900">Personal Details</h2>
                            <p class="text-xs text-gray-500">Key identifying information for the applicant.</p>
                        </div>
                    </div>
                    <div class="p-5 space-y-3 text-sm text-gray-700">
                        <div class="flex justify-between">
                            <span class="text-gray-500">Name</span>
                            <span class="font-medium text-gray-900"><?php echo esc_html($application['full_name']); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Email</span>
                            <span class="font-medium text-gray-900"><?php echo esc_html($application['email']); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Phone</span>
                            <span class="font-medium text-gray-900">
                                <?php
                                $phone_value = $application['phone'] ?? ($application['cell_no'] ?? '');
                                echo esc_html($phone_value);
                                ?>
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">ID Number</span>
                            <span class="font-medium text-gray-900"><?php echo esc_html($application['id_number']); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Date of Birth</span>
                            <span class="font-medium text-gray-900"><?php echo esc_html($application['date_of_birth']); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Gender</span>
                            <span class="font-medium text-gray-900"><?php echo esc_html($application['gender']); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Nationality</span>
                            <span class="font-medium text-gray-900"><?php echo esc_html($application['nationality'] ?? 'Not provided'); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Country of Birth</span>
                            <span class="font-medium text-gray-900"><?php echo esc_html($application['country_of_birth'] ?? 'Not provided'); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Marital Status</span>
                            <span class="font-medium text-gray-900"><?php echo esc_html($application['marital_status'] ?? 'Not provided'); ?></span>
                        </div>
                    </div>
            </div>
        </div>
        
            <!-- Address & Emergency Contact -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white shadow-sm rounded-xl border border-gray-100 overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-100">
                        <h2 class="text-sm font-semibold text-gray-900">Address Information</h2>
                    </div>
                    <div class="p-5 space-y-3 text-sm text-gray-700">
                        <div>
                            <p class="text-xs font-semibold text-gray-500 mb-1">Street Address</p>
                            <p class="text-sm text-gray-900"><?php echo esc_html($application['street_address']); ?></p>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-xs font-semibold text-gray-500 mb-1">City</p>
                                <p class="text-sm text-gray-900"><?php echo esc_html($application['city']); ?></p>
                            </div>
                            <div>
                                <p class="text-xs font-semibold text-gray-500 mb-1">Province</p>
                                <p class="text-sm text-gray-900"><?php echo esc_html($application['province']); ?></p>
                            </div>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-gray-500 mb-1">Postal Code</p>
                            <p class="text-sm text-gray-900"><?php echo esc_html($application['postal_code']); ?></p>
                        </div>
                    </div>
        </div>
        
                <div class="bg-white shadow-sm rounded-xl border border-gray-100 overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-100">
                        <h2 class="text-sm font-semibold text-gray-900">Emergency Contact</h2>
                    </div>
                    <div class="p-5 space-y-3 text-sm text-gray-700">
                        <div class="flex justify-between">
                            <span class="text-gray-500">Name</span>
                            <span class="font-medium text-gray-900"><?php echo esc_html($application['emergency_full_name']); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Relationship</span>
                            <span class="font-medium text-gray-900"><?php echo esc_html($application['emergency_relationship']); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Phone</span>
                            <span class="font-medium text-gray-900"><?php echo esc_html($application['emergency_phone']); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Email</span>
                            <span class="font-medium text-gray-900"><?php echo esc_html($application['emergency_email']); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Responsible Person, Education, Language, Medical, and Uploaded Documents -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white shadow-sm rounded-xl border border-gray-100 overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-100">
                        <h2 class="text-sm font-semibold text-gray-900">Responsible Person Details</h2>
                    </div>
                    <div class="p-5 space-y-3 text-sm text-gray-700">
                        <div class="flex justify-between"><span class="text-gray-500">Full Name</span><span class="font-medium text-gray-900"><?php echo esc_html($application['responsible_full_name'] ?? 'Not provided'); ?></span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Relationship</span><span class="font-medium text-gray-900"><?php echo esc_html($application['relationship'] ?? 'Not provided'); ?></span></div>
                        <div class="flex justify-between"><span class="text-gray-500">ID Number</span><span class="font-medium text-gray-900"><?php echo esc_html($application['responsible_id_number'] ?? 'Not provided'); ?></span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Phone</span><span class="font-medium text-gray-900"><?php echo esc_html($application['responsible_phone'] ?? 'Not provided'); ?></span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Email</span><span class="font-medium text-gray-900"><?php echo esc_html($application['responsible_email'] ?? 'Not provided'); ?></span></div>
                        <div class="pt-2 border-t border-gray-100">
                            <p class="text-xs font-semibold text-gray-500 mb-1">Address</p>
                            <p class="text-sm text-gray-900"><?php echo esc_html($application['responsible_street_address'] ?? ''); ?></p>
                            <p class="text-xs text-gray-600 mt-1">
                                <?php
                                $resp_city = (string) ($application['responsible_city'] ?? '');
                                $resp_prov = (string) ($application['responsible_province'] ?? '');
                                $resp_post = (string) ($application['responsible_postal_code'] ?? '');
                                $resp_loc = trim($resp_city . ($resp_city && $resp_prov ? ', ' : '') . $resp_prov . ($resp_post ? ' ' . $resp_post : ''));
                                echo esc_html($resp_loc !== '' ? $resp_loc : 'Not provided');
                                ?>
                            </p>
                        </div>
                        <div class="pt-2 border-t border-gray-100">
                            <p class="text-xs font-semibold text-gray-500 mb-1">Occupation</p>
                            <p class="text-sm text-gray-900"><?php echo esc_html($application['occupation'] ?? 'Not provided'); ?></p>
                            <?php if (!empty($application['company_name']) || !empty($application['work_telephone']) || !empty($application['work_email'])) : ?>
                                <p class="text-xs text-gray-600 mt-1">
                                    <?php
                                    $work_bits = array();
                                    if (!empty($application['company_name'])) { $work_bits[] = (string) $application['company_name']; }
                                    if (!empty($application['work_telephone'])) { $work_bits[] = 'Tel: ' . (string) $application['work_telephone']; }
                                    if (!empty($application['work_email'])) { $work_bits[] = 'Email: ' . (string) $application['work_email']; }
                                    echo esc_html(implode(' | ', $work_bits));
                                    ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="bg-white shadow-sm rounded-xl border border-gray-100 overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-100">
                        <h2 class="text-sm font-semibold text-gray-900">Education & Language</h2>
                    </div>
                    <div class="p-5 space-y-3 text-sm text-gray-700">
                        <div class="flex justify-between"><span class="text-gray-500">Highest Grade</span><span class="font-medium text-gray-900"><?php echo esc_html($application['highest_grade'] ?? 'Not provided'); ?></span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Year Passed</span><span class="font-medium text-gray-900"><?php echo esc_html($application['year_passed'] ?? 'Not provided'); ?></span></div>
                        <div class="flex justify-between"><span class="text-gray-500">School Attended</span><span class="font-medium text-gray-900"><?php echo esc_html($application['school_attended'] ?? 'Not provided'); ?></span></div>
                        <div class="flex justify-between"><span class="text-gray-500">School Location</span><span class="font-medium text-gray-900"><?php echo esc_html($application['school_location'] ?? 'Not provided'); ?></span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Other Qualifications</span><span class="font-medium text-gray-900"><?php echo esc_html($application['other_qualifications'] ?? 'Not provided'); ?></span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Year of Completion</span><span class="font-medium text-gray-900"><?php echo esc_html($application['year_completion'] ?? 'Not provided'); ?></span></div>

                        <div class="pt-2 border-t border-gray-100">
                            <p class="text-xs font-semibold text-gray-500 mb-2">Language Proficiency</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                                <div><span class="text-gray-500">Home Language:</span> <span class="text-gray-900 font-medium"><?php echo esc_html($application['home_language'] ?? 'Not provided'); ?></span></div>
                                <div><span class="text-gray-500">English (Write/Read/Speak):</span> <span class="text-gray-900 font-medium"><?php echo esc_html(($application['english_write'] ?? '-') . ' / ' . ($application['english_read'] ?? '-') . ' / ' . ($application['english_speak'] ?? '-')); ?></span></div>
                                <div><span class="text-gray-500">Other Language:</span> <span class="text-gray-900 font-medium"><?php echo esc_html($application['other_language'] ?? 'Not provided'); ?></span></div>
                                <div><span class="text-gray-500">Other (Write/Read/Speak):</span> <span class="text-gray-900 font-medium"><?php echo esc_html(($application['other_language_write'] ?? '-') . ' / ' . ($application['other_language_read'] ?? '-') . ' / ' . ($application['other_language_speak'] ?? '-')); ?></span></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white shadow-sm rounded-xl border border-gray-100 overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-100">
                        <h2 class="text-sm font-semibold text-gray-900">Medical Information</h2>
                    </div>
                    <div class="p-5 space-y-3 text-sm text-gray-700">
                        <div class="flex justify-between"><span class="text-gray-500">Physical Illness</span><span class="font-medium text-gray-900"><?php echo esc_html($application['physical_illness'] ?? 'Not provided'); ?></span></div>
                        <?php if (!empty($application['specify_physical_illness'])) : ?>
                            <div><p class="text-xs font-semibold text-gray-500 mb-1">Details</p><p class="text-sm text-gray-900 whitespace-pre-line"><?php echo esc_html($application['specify_physical_illness']); ?></p></div>
                        <?php endif; ?>
                        <div class="flex justify-between"><span class="text-gray-500">Food Allergies</span><span class="font-medium text-gray-900"><?php echo esc_html($application['food_allergies'] ?? 'Not provided'); ?></span></div>
                        <?php if (!empty($application['specify_food_allergies'])) : ?>
                            <div><p class="text-xs font-semibold text-gray-500 mb-1">Allergy Details</p><p class="text-sm text-gray-900 whitespace-pre-line"><?php echo esc_html($application['specify_food_allergies']); ?></p></div>
                        <?php endif; ?>
                        <div class="flex justify-between"><span class="text-gray-500">Chronic Medication</span><span class="font-medium text-gray-900"><?php echo esc_html($application['chronic_medication'] ?? 'Not provided'); ?></span></div>
                        <?php if (!empty($application['specify_chronic_medication'])) : ?>
                            <div><p class="text-xs font-semibold text-gray-500 mb-1">Medication Details</p><p class="text-sm text-gray-900 whitespace-pre-line"><?php echo esc_html($application['specify_chronic_medication']); ?></p></div>
                        <?php endif; ?>
                        <div class="flex justify-between"><span class="text-gray-500">Pregnant / Planning</span><span class="font-medium text-gray-900"><?php echo esc_html($application['pregnant_or_planning'] ?? 'Not provided'); ?></span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Smoke</span><span class="font-medium text-gray-900"><?php echo esc_html($application['smoke'] ?? 'Not provided'); ?></span></div>
                    </div>
                </div>

                <div class="bg-white shadow-sm rounded-xl border border-gray-100 overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                        <div>
                            <h2 class="text-sm font-semibold text-gray-900">Uploaded Documents</h2>
                            <p class="text-xs text-gray-500">All files submitted for this application.</p>
                        </div>
                    </div>
                    <div class="p-5 space-y-2">
                        <?php
                        $doc_field_labels = array(
                            'id_passport_applicant'    => 'Applicant ID/Passport',
                            'id_passport_responsible'  => 'Responsible Person ID/Passport',
                            'saqa_certificate'         => 'SAQA Certificate',
                            'study_permit'             => 'Study Permit',
                            'parent_spouse_id'         => 'Parent/Spouse ID',
                            'latest_results'           => 'Latest Results',
                            'proof_residence'          => 'Proof of Residence',
                            'highest_grade_cert'       => 'Highest Grade Certificate',
                            'proof_medical_aid'        => 'Proof of Medical Aid',
                        );

                        $public_base_url = plugin_dir_url(dirname(__FILE__)) . 'public/';
                        $uploaded_docs = array();

                        foreach ($doc_field_labels as $field_key => $field_label) {
                            $stored_path = trim((string) ($application[$field_key] ?? ''));
                            if ($stored_path === '') { continue; }
                            $file_url = preg_match('#^https?://#i', $stored_path)
                                ? $stored_path
                                : $public_base_url . ltrim($stored_path, '/');
                            $uploaded_docs[$stored_path] = array(
                                'label' => $field_label,
                                'url'   => $file_url,
                            );
                        }

                        $application_docs_table = $wpdb->prefix . 'nds_application_documents';
                        $docs_table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $application_docs_table));
                        if (!empty($docs_table_exists)) {
                            $extra_docs = $wpdb->get_results($wpdb->prepare(
                                "SELECT document_type, file_name, file_path FROM {$application_docs_table} WHERE application_id = %d ORDER BY uploaded_at DESC",
                                (int) $application_id
                            ), ARRAY_A);

                            foreach ($extra_docs as $extra_doc) {
                                $stored_path = trim((string) ($extra_doc['file_path'] ?? ''));
                                if ($stored_path === '') { continue; }
                                $file_url = preg_match('#^https?://#i', $stored_path)
                                    ? $stored_path
                                    : $public_base_url . ltrim($stored_path, '/');
                                $doc_label = !empty($extra_doc['file_name'])
                                    ? (string) $extra_doc['file_name']
                                    : ucfirst(str_replace('_', ' ', (string) ($extra_doc['document_type'] ?? 'document')));

                                if (!isset($uploaded_docs[$stored_path])) {
                                    $uploaded_docs[$stored_path] = array(
                                        'label' => $doc_label,
                                        'url'   => $file_url,
                                    );
                                }
                            }
                        }
                        ?>

                        <?php if (empty($uploaded_docs)) : ?>
                            <p class="text-sm text-gray-500">No uploaded documents were found for this application.</p>
                        <?php else : ?>
                            <?php foreach ($uploaded_docs as $doc_item) : ?>
                                <div class="flex items-center justify-between gap-2 rounded-lg border border-gray-100 p-3">
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-gray-900 truncate"><?php echo esc_html($doc_item['label']); ?></p>
                                        <p class="text-xs text-gray-500 truncate"><?php echo esc_html($doc_item['url']); ?></p>
                                    </div>
                                    <div class="flex items-center gap-2 flex-shrink-0">
                                        <a href="<?php echo esc_url($doc_item['url']); ?>" target="_blank" rel="noopener"
                                           class="inline-flex items-center px-3 py-1.5 rounded-md bg-blue-50 text-blue-700 text-xs font-medium hover:bg-blue-100">
                                            <span class="dashicons dashicons-visibility text-sm mr-1"></span>
                                            View
                                        </a>
                                        <a href="<?php echo esc_url($doc_item['url']); ?>" download
                                           class="inline-flex items-center px-3 py-1.5 rounded-md bg-emerald-50 text-emerald-700 text-xs font-medium hover:bg-emerald-100">
                                            <span class="dashicons dashicons-download text-sm mr-1"></span>
                                            Download
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow-sm rounded-xl border border-gray-100 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-900">Application Declaration & Motivation</h2>
                </div>
                <div class="p-5 grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div>
                        <p class="text-xs font-semibold text-gray-500 mb-2">Declaration</p>
                        <p class="text-sm font-medium text-gray-900"><?php echo !empty($application['declaration']) ? 'Accepted' : 'Not accepted'; ?></p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-gray-500 mb-2">Motivation Letter</p>
                        <p class="text-sm text-gray-900 whitespace-pre-line"><?php echo esc_html($application['motivation_letter'] ?? 'Not provided'); ?></p>
                    </div>
                </div>
            </div>
        </div>
        </div>
        
    <!-- Reuse the same Tailwind modal used on the Applications dashboard -->
    <div id="status-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden" style="display: none;">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-xl shadow-xl max-w-lg w-full max-h-[90vh] overflow-y-auto">
                <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                    <h3 class="text-xl font-semibold text-gray-900">Update Application Status</h3>
                    <button type="button" class="text-gray-400 hover:text-gray-600" onclick="ndsCloseStatusModal()">
                        <span class="dashicons dashicons-no-alt text-xl"></span>
                    </button>
        </div>
                <div class="p-6">
                    <form method="post" class="space-y-4">
                        <?php wp_nonce_field('nds_status_update', 'nds_status_update_nonce'); ?>
                        <input type="hidden" name="application_id" id="modal-application-id">

                        <div>
                            <label for="new_status" class="block text-sm font-semibold text-gray-900 mb-2">New status</label>
                            <select name="new_status" id="new_status" required
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm">
                                <option value="submitted">Submitted</option>
                                <option value="under_review">Under Review</option>
                                <option value="accepted">Accepted</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>

                        <div>
                            <label for="notes" class="block text-sm font-semibold text-gray-900 mb-2">Notes</label>
                            <textarea name="notes" id="notes" rows="3"
                                      class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                                      placeholder="Add any review notes or context for this decision..."></textarea>
                        </div>

                        <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-200">
                            <button type="button"
                                    class="px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium"
                                    onclick="ndsCloseStatusModal()">
                                Cancel
                            </button>
                            <button type="submit" name="update_status"
                                    class="inline-flex items-center px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium">
                                <span class="dashicons dashicons-yes-alt text-sm mr-1"></span>
                                Update status
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const statusModal = document.getElementById('status-modal');
        const updateButtons = document.querySelectorAll('.update-status-btn');
        const quickStatusButtons = document.querySelectorAll('.nds-quick-status-btn');

        // #region agent log: DOMContentLoaded modal check
        fetch('http://127.0.0.1:7247/ingest/dd126561-a5b5-4577-8b70-512cd5168604', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                sessionId: 'debug-session',
                runId: 'details-dom-ready',
                hypothesisId: 'H_modal_2',
                location: 'applicants-management.php:DOMContentLoaded',
                message: 'DOMContentLoaded - modal check',
                data: {
                    hasStatusModal: !!statusModal,
                    updateButtonsCount: updateButtons.length,
                    quickButtonsCount: quickStatusButtons.length
                },
                timestamp: Date.now()
            })
        }).catch(() => {});
        // #endregion

        // Ensure modal is attached directly to <body> so it centers over the full viewport
        if (statusModal && statusModal.parentElement !== document.body) {
            document.body.appendChild(statusModal);
        }

        function openStatusModal(id, status) {
            const idField = document.getElementById('modal-application-id');
            const statusField = document.getElementById('new_status');

            // #region agent log: openStatusModal called
            fetch('http://127.0.0.1:7247/ingest/dd126561-a5b5-4577-8b70-512cd5168604', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    sessionId: 'debug-session',
                    runId: 'details-open-modal',
                    hypothesisId: 'H_modal_3',
                    location: 'applicants-management.php:openStatusModal',
                    message: 'openStatusModal called',
                    data: {
                        id,
                        status,
                        hasStatusModal: !!statusModal,
                        hasIdField: !!idField,
                        hasStatusField: !!statusField
                    },
                    timestamp: Date.now()
                })
            }).catch(() => {});
            // #endregion

            if (idField) {
                idField.value = id;
            }
            if (statusField && status) {
                statusField.value = status;
            }

            if (statusModal) {
                statusModal.classList.remove('hidden');
                statusModal.classList.add('flex', 'items-center', 'justify-center');
                statusModal.style.display = 'flex';
                document.body.style.overflow = 'hidden';

                // #region agent log: modal shown attempt
                const computedStyle = window.getComputedStyle(statusModal);
                fetch('http://127.0.0.1:7247/ingest/dd126561-a5b5-4577-8b70-512cd5168604', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        sessionId: 'debug-session',
                        runId: 'details-open-modal',
                        hypothesisId: 'H_modal_4',
                        location: 'applicants-management.php:openStatusModal-after',
                        message: 'After trying to show modal',
                        data: {
                            display: computedStyle.display,
                            visibility: computedStyle.visibility,
                            zIndex: computedStyle.zIndex,
                            hasHiddenClass: statusModal.classList.contains('hidden')
                        },
                        timestamp: Date.now()
                    })
                }).catch(() => {});
                // #endregion
            } else {
                // #region agent log: modal not found
                fetch('http://127.0.0.1:7247/ingest/dd126561-a5b5-4577-8b70-512cd5168604', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        sessionId: 'debug-session',
                        runId: 'details-open-modal',
                        hypothesisId: 'H_modal_5',
                        location: 'applicants-management.php:openStatusModal',
                        message: 'statusModal is null',
                        data: { id, status },
                        timestamp: Date.now()
                    })
                }).catch(() => {});
                // #endregion
            }
        }

        updateButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                openStatusModal(this.dataset.id, this.dataset.status);
            });
        });

        quickStatusButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                openStatusModal(this.dataset.id, this.dataset.status);
            });
        });

        if (statusModal) {
            statusModal.addEventListener('click', function(e) {
                if (e.target === statusModal) {
                    ndsCloseStatusModal();
                }
            });
        }
    });

    function ndsCloseStatusModal() {
        const statusModal = document.getElementById('status-modal');
        if (statusModal) {
            statusModal.classList.add('hidden');
            statusModal.classList.remove('flex', 'items-center', 'justify-center');
            statusModal.style.display = 'none';
            document.body.style.overflow = '';
        }
    }
    </script>
    <?php
}

/**
 * Update Application Status
 */
function nds_update_application_status($application_id, $new_status, $notes = '', $notify_applicant = false) {
    global $wpdb;
    
    // #region agent log: status update entry - capture course_name BEFORE update
    $forms_table = $wpdb->prefix . 'nds_application_forms';
    $course_name_before = $wpdb->get_var($wpdb->prepare(
        "SELECT course_name FROM {$forms_table} WHERE application_id = %d",
        $application_id
    ));
    @file_put_contents(
        __DIR__ . '/../.cursor/debug.log',
        json_encode(array(
            'sessionId' => 'debug-session',
            'runId' => 'course-destroy-pre',
            'hypothesisId' => 'H_course_1',
            'location' => 'applicants-management.php:status_update_entry',
            'message' => 'Status update entry - course_name BEFORE',
            'data' => array(
                'application_id' => $application_id,
                'new_status' => $new_status,
                'course_name_before' => $course_name_before ?? '',
            ),
            'timestamp' => round(microtime(true) * 1000),
        )) . PHP_EOL,
        FILE_APPEND
    );
    // #endregion
    
    // Get current status to check if we need to run enrollment
    $current_status = $wpdb->get_var($wpdb->prepare(
        "SELECT status FROM {$wpdb->prefix}nds_applications WHERE id = %d",
        $application_id
    ));
    
    // When an application is accepted, determine and set academic_year_id and semester_id
    $academic_year_id = null;
    $semester_id = null;
    
    if ($new_status === 'accepted') {
        // Get active academic year and semester (or create defaults)
        $active_year = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}nds_academic_years WHERE is_active = 1 ORDER BY id DESC LIMIT 1", ARRAY_A);
        $active_semester = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}nds_semesters WHERE is_active = 1 ORDER BY id DESC LIMIT 1", ARRAY_A);

        // Fallback: if no explicitly active year/semester, use the most recent ones
        if (!$active_year) {
            $active_year = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}nds_academic_years ORDER BY id DESC LIMIT 1", ARRAY_A);
        }
        if ($active_year && !$active_semester) {
            $active_semester = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}nds_semesters WHERE academic_year_id = %d ORDER BY id DESC LIMIT 1",
                    (int) $active_year['id']
                ),
                ARRAY_A
            );
        }

        // If we still don't have a year/semester, auto-create a sensible default
        if (!$active_year) {
            $current_year = (int) date('Y');
            $year_name    = $current_year . '/' . ($current_year + 1);
            $year_insert  = $wpdb->insert(
                "{$wpdb->prefix}nds_academic_years",
                array(
                    'year_name'  => $year_name,
                    'start_date' => $current_year . '-01-01',
                    'end_date'   => ($current_year + 1) . '-12-31',
                    'is_active'  => 1,
                ),
                array('%s', '%s', '%s', '%d')
            );

            if ($year_insert !== false) {
                $active_year = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT * FROM {$wpdb->prefix}nds_academic_years WHERE id = %d",
                        (int) $wpdb->insert_id
                    ),
                    ARRAY_A
                );
            }
        }

        if ($active_year && !$active_semester) {
            $semester_insert = $wpdb->insert(
                "{$wpdb->prefix}nds_semesters",
                array(
                    'academic_year_id' => (int) $active_year['id'],
                    'semester_name'    => 'Default',
                    'start_date'       => $active_year['start_date'],
                    'end_date'         => $active_year['end_date'],
                    'is_active'        => 1,
                ),
                array('%d', '%s', '%s', '%s', '%d')
            );

            if ($semester_insert !== false) {
                $active_semester = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT * FROM {$wpdb->prefix}nds_semesters WHERE id = %d",
                        (int) $wpdb->insert_id
                    ),
                    ARRAY_A
                );
            }
        }

        if ($active_year && $active_semester) {
            $academic_year_id = (int) $active_year['id'];
            $semester_id = (int) $active_semester['id'];
        }

        // Ensure accepted applications keep a concrete course/program mapping for registration.
        $app_course_program = $wpdb->get_row(
            $wpdb->prepare(
                "
                SELECT a.course_id AS app_course_id,
                       a.program_id AS app_program_id,
                       af.course_id AS form_course_id,
                       af.course_name AS form_course_name
                FROM {$wpdb->prefix}nds_applications a
                LEFT JOIN {$forms_table} af ON af.application_id = a.id
                WHERE a.id = %d
                LIMIT 1
                ",
                $application_id
            ),
            ARRAY_A
        );

        if (!empty($app_course_program)) {
            $resolved_course_id = (int) ($app_course_program['app_course_id'] ?? 0);
            $resolved_program_id = (int) ($app_course_program['app_program_id'] ?? 0);

            if ($resolved_course_id <= 0) {
                $resolved_course_id = (int) ($app_course_program['form_course_id'] ?? 0);
            }

            if ($resolved_course_id <= 0 && !empty($app_course_program['form_course_name'])) {
                $course_name = trim((string) $app_course_program['form_course_name']);
                $course_name_clean = preg_replace('/\s*\(NQF\s+\d+\)\s*$/i', '', $course_name);

                $resolved_course_id = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}nds_courses WHERE name = %s OR name = %s ORDER BY id DESC LIMIT 1",
                    $course_name_clean,
                    $course_name
                ));

                if (!$resolved_course_id) {
                    $resolved_course_id = (int) $wpdb->get_var($wpdb->prepare(
                        "SELECT id FROM {$wpdb->prefix}nds_courses WHERE name LIKE %s ORDER BY id DESC LIMIT 1",
                        '%' . $wpdb->esc_like($course_name) . '%'
                    ));
                }
            }

            if ($resolved_program_id <= 0 && $resolved_course_id > 0) {
                $resolved_program_id = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT program_id FROM {$wpdb->prefix}nds_courses WHERE id = %d",
                    $resolved_course_id
                ));
            }

            if ($resolved_course_id > 0) {
                $update_data['course_id'] = $resolved_course_id;
                $update_formats[] = '%d';
            }

            if ($resolved_program_id > 0) {
                $update_data['program_id'] = $resolved_program_id;
                $update_formats[] = '%d';
            }
        }
    }
    
    // Build update data
    $update_data = [
        'status' => $new_status,
        'notes' => $notes,
        'decided_by' => get_current_user_id(),
        'decision_at' => current_time('mysql'),
        'updated_at' => current_time('mysql')
    ];
    
    // Build format specifiers
    $update_formats = ['%s', '%s', '%d', '%s', '%s'];
    
    // Add academic_year_id and semester_id if status is accepted
    if ($new_status === 'accepted' && $academic_year_id && $semester_id) {
        $update_data['academic_year_id'] = $academic_year_id;
        $update_data['semester_id'] = $semester_id;
        $update_formats[] = '%d'; // for academic_year_id
        $update_formats[] = '%d'; // for semester_id
    }
    
    $result = $wpdb->update(
        $wpdb->prefix . 'nds_applications',
        $update_data,
        ['id' => $application_id],
        $update_formats,
        ['%d']
    );

    // Fetch student_id for logging if it's already linked
    $student_id_for_log = $wpdb->get_var($wpdb->prepare(
        "SELECT student_id FROM {$wpdb->prefix}nds_applications WHERE id = %d",
        $application_id
    ));
    if ($student_id_for_log && function_exists('nds_log_student_activity')) {
        nds_log_student_activity(
            $student_id_for_log,
            get_current_user_id(),
            "Application status updated to " . str_replace('_', ' ', $new_status),
            'update',
            ['old_status' => $current_status],
            ['new_status' => $new_status, 'notes' => $notes]
        );
    }
    
    // Always run enrollment logic if status is 'accepted' (even if already accepted - to handle missed enrollments)
    $should_enroll = ($new_status === 'accepted');
    
    if ($result !== false) {
        // When an application is accepted, ensure the linked learner is marked as an active student
        if ($should_enroll) {
            $apps_table   = $wpdb->prefix . 'nds_applications';
            $forms_table  = $wpdb->prefix . 'nds_application_forms';
            $students_tbl = $wpdb->prefix . 'nds_students';

            // Try to find the related student either via explicit student_id mapping or by email
            $app_student = $wpdb->get_row(
                $wpdb->prepare(
                    "
                    SELECT a.student_id, af.email
                    FROM {$apps_table} a
                    LEFT JOIN {$forms_table} af ON af.application_id = a.id
                    WHERE a.id = %d
                    ",
                    $application_id
                ),
                ARRAY_A
            );

            // #region agent log: accepted application base info (no PII beyond ids)
            @file_put_contents(
                __DIR__ . '/../.cursor/debug.log',
                json_encode(array(
                    'sessionId'   => 'debug-session',
                    'runId'       => 'enroll-pre',
                    'hypothesisId'=> 'H_accept_1',
                    'location'    => 'applicants-management.php:accepted_base',
                    'message'     => 'Accepted application base info',
                    'data'        => array(
                        'application_id' => $application_id,
                        'app_student'    => array(
                            'student_id' => isset($app_student['student_id']) ? (int) $app_student['student_id'] : 0,
                            'has_email'  => !empty($app_student['email']),
                        ),
                    ),
                    'timestamp'   => round(microtime(true) * 1000),
                )) . PHP_EOL,
                FILE_APPEND
            );
            // #endregion

            if ($app_student) {
                $student_id = (int) ($app_student['student_id'] ?? 0);
                
                if ($student_id > 0) {
                    // Direct mapping from application to student
                    $wpdb->update(
                        $students_tbl,
                        ['status' => 'active'],
                        ['id' => $student_id],
                        ['%s'],
                        ['%d']
                    );
                } elseif (!empty($app_student['email'])) {
                    // Fallback: match student by email address
                    $student_id = (int) $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT id FROM {$students_tbl} WHERE email = %s ORDER BY id DESC LIMIT 1",
                            $app_student['email']
                        )
                    );
                    
                    if ($student_id > 0) {
                        $wpdb->update(
                            $students_tbl,
                            ['status' => 'active'],
                            ['id' => $student_id],
                            ['%s'],
                            ['%d']
                        );
                    }
                }

                // If we still don't have a student, create a new learner profile from the application form
                if ($student_id <= 0 && !empty($app_student['email'])) {
                    $app_form = $wpdb->get_row(
                        $wpdb->prepare(
                            "SELECT * FROM {$forms_table} WHERE application_id = %d LIMIT 1",
                            $application_id
                        ),
                        ARRAY_A
                    );

                    if ($app_form) {
                        // Generate a student number based on application id (simple, deterministic)
                        $student_number = 'NDS' . date('Y') . str_pad($application_id, 4, '0', STR_PAD_LEFT);

                        // Try to link to existing WP user by email
                        $wp_user_id = null;
                        $existing_user = get_user_by('email', $app_form['email']);
                        if ($existing_user) {
                            $wp_user_id = $existing_user->ID;
                        }

                        // Split full name into first/last as best we can
                        $full_name = trim($app_form['full_name']);
                        $first_name = $full_name;
                        $last_name  = '';
                        if (strpos($full_name, ' ') !== false) {
                            $parts = explode(' ', $full_name, 2);
                            $first_name = $parts[0];
                            $last_name  = $parts[1];
                        }

                        $student_data = array(
                            'student_number' => $student_number,
                            'wp_user_id'     => $wp_user_id,
                            'first_name'     => $first_name,
                            'last_name'      => $last_name ?: $first_name,
                            'email'          => $app_form['email'],
                            'phone'          => $app_form['cell_no'],
                            'date_of_birth'  => $app_form['date_of_birth'],
                            'gender'         => $app_form['gender'] ?: 'Other',
                            'address'        => $app_form['street_address'],
                            'city'           => $app_form['city'],
                            'country'        => $app_form['country_of_birth'],
                            'status'         => 'active',
                            'created_at'     => current_time('mysql'),
                        );

                        $inserted = $wpdb->insert($students_tbl, $student_data, array(
                            '%s','%d','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s'
                        ));

                        if ($inserted) {
                            $student_id = (int) $wpdb->insert_id;

                            // Link application to this new student
                            $wpdb->update(
                                $apps_table,
                                array('student_id' => $student_id),
                                array('id' => $application_id),
                                array('%d'),
                                array('%d')
                            );
                        }
                    }
                }
                
                // Student profile created/activated. Enrollment is handled by the student
                // via manual registration submission in the learner portal.
            }
        }
    } elseif ($should_enroll && $current_status === 'accepted') {
        // Status is already 'accepted' but update returned false/0 - still try to enroll (might have been missed)
        // This handles the case where enrollment failed previously
        $apps_table   = $wpdb->prefix . 'nds_applications';
        $forms_table  = $wpdb->prefix . 'nds_application_forms';
        $students_tbl = $wpdb->prefix . 'nds_students';

        $app_student = $wpdb->get_row(
            $wpdb->prepare(
                "
                SELECT a.student_id, af.email
                FROM {$apps_table} a
                LEFT JOIN {$forms_table} af ON af.application_id = a.id
                WHERE a.id = %d
                ",
                $application_id
            ),
            ARRAY_A
        );

        if ($app_student) {
            $student_id = (int) ($app_student['student_id'] ?? 0);

            if ($student_id <= 0 && !empty($app_student['email'])) {
                $student_id = (int) $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT id FROM {$students_tbl} WHERE email = %s ORDER BY id DESC LIMIT 1",
                        $app_student['email']
                    )
                );
            }

            // Enrollment is handled by the student via manual registration in the learner portal.
        }
        
        // #region agent log: course_name AFTER status update but before enrollment
        $course_name_after_update = $wpdb->get_var($wpdb->prepare(
            "SELECT course_name FROM {$forms_table} WHERE application_id = %d",
            $application_id
        ));
        @file_put_contents(
            __DIR__ . '/../.cursor/debug.log',
            json_encode(array(
                'sessionId' => 'debug-session',
                'runId' => 'course-destroy-pre',
                'hypothesisId' => 'H_course_2',
                'location' => 'applicants-management.php:status_update_after',
                'message' => 'Status update completed - course_name AFTER update',
                'data' => array(
                    'application_id' => $application_id,
                    'course_name_before' => $course_name_before ?? '',
                    'course_name_after_update' => $course_name_after_update ?? '',
                    'course_name_changed' => ($course_name_before ?? '') !== ($course_name_after_update ?? ''),
                ),
                'timestamp' => round(microtime(true) * 1000),
            )) . PHP_EOL,
            FILE_APPEND
        );
        // #endregion
        
        // When an application is accepted, move its files from Applicants/ into the Student folder
        if ($new_status === 'accepted' && function_exists('nds_move_application_files_to_student')) {
            nds_move_application_files_to_student($application_id);
        }

        // Optionally notify the applicant via email about this status change
        if ($notify_applicant) {
            $application = $wpdb->get_row($wpdb->prepare("
                SELECT 
                    a.application_no,
                    a.status,
                    af.full_name,
                    af.email
                FROM {$wpdb->prefix}nds_applications a
                LEFT JOIN {$wpdb->prefix}nds_application_forms af ON a.id = af.application_id
                WHERE a.id = %d
            ", $application_id), ARRAY_A);

            if ($application && !empty($application['email'])) {
                $site_name   = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
                $status_label = ucfirst(str_replace('_', ' ', $new_status));
                $subject    = sprintf(
                    '[%s] Your application %s status has been updated',
                    $site_name,
                    $application['application_no']
                );

                $message  = "Hi " . ($application['full_name'] ?: 'there') . ",\n\n";
                $message .= "The status of your application (" . $application['application_no'] . ") has been updated to: " . $status_label . ".\n\n";
                if (!empty($notes)) {
                    $message .= "Notes from our admissions team:\n" . $notes . "\n\n";
                }
                $message .= "If you have any questions, please reply to this email or contact the admissions office.\n\n";
                $message .= "Regards,\n" . $site_name;

                wp_mail($application['email'], $subject, $message);
            }
        }
        
        // #region agent log: course_name FINAL check after all operations
        $course_name_final = $wpdb->get_var($wpdb->prepare(
            "SELECT course_name FROM {$forms_table} WHERE application_id = %d",
            $application_id
        ));
        @file_put_contents(
            __DIR__ . '/../.cursor/debug.log',
            json_encode(array(
                'sessionId' => 'debug-session',
                'runId' => 'course-destroy-pre',
                'hypothesisId' => 'H_course_4',
                'location' => 'applicants-management.php:status_update_final',
                'message' => 'Status update FINAL - course_name after all operations',
                'data' => array(
                    'application_id' => $application_id,
                    'course_name_before' => $course_name_before ?? '',
                    'course_name_final' => $course_name_final ?? '',
                    'course_name_destroyed' => !empty($course_name_before) && empty($course_name_final),
                ),
                'timestamp' => round(microtime(true) * 1000),
            )) . PHP_EOL,
            FILE_APPEND
        );
        // #endregion
        
        // Set a success message that will be displayed
        $_SESSION['nds_status_update_success'] = $notify_applicant
            ? 'Application status updated and the applicant has been emailed.'
            : 'Application status updated successfully!';
        return true;
    } else {
        $_SESSION['nds_status_update_error'] = 'Failed to update application status: ' . $wpdb->last_error;
        return false;
    }
}

/**
 * Enroll student from application (extracted for reuse)
 * This function can be called manually for already-accepted applications
 */
function nds_enroll_student_from_application($application_id, $student_id = null) {
    global $wpdb;
    
    $apps_table   = $wpdb->prefix . 'nds_applications';
    $forms_table  = $wpdb->prefix . 'nds_application_forms';
    $students_tbl = $wpdb->prefix . 'nds_students';
    
    // If student_id not provided, try to find it from the application
    if (!$student_id) {
        $app_student = $wpdb->get_row(
            $wpdb->prepare(
                "
                SELECT a.student_id, af.email
                FROM {$apps_table} a
                LEFT JOIN {$forms_table} af ON af.application_id = a.id
                WHERE a.id = %d
                ",
                $application_id
            ),
            ARRAY_A
        );
        
        if ($app_student) {
            $student_id = (int) ($app_student['student_id'] ?? 0);
            
            if ($student_id <= 0 && !empty($app_student['email'])) {
                // Fallback: match student by email address
                $student_id = (int) $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT id FROM {$students_tbl} WHERE email = %s ORDER BY id DESC LIMIT 1",
                        $app_student['email']
                    )
                );
            }
        }
    }
    
    if ($student_id <= 0) {
        // #region agent log: no student_id found
        @file_put_contents(
            __DIR__ . '/../.cursor/debug.log',
            json_encode(array(
                'sessionId' => 'debug-session',
                'runId' => 'enroll-manual',
                'hypothesisId' => 'H_enroll_manual_1',
                'location' => 'applicants-management.php:enroll_manual_no_student',
                'message' => 'No student_id found for enrollment',
                'data' => array(
                    'application_id' => $application_id,
                ),
                'timestamp' => round(microtime(true) * 1000),
            )) . PHP_EOL,
            FILE_APPEND
        );
        // #endregion
        return false;
    }
    
    // 1. Get IDs directly from the applications table (preferred)
    $app_data = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT program_id, course_id FROM {$apps_table} WHERE id = %d",
            $application_id
        ),
        ARRAY_A
    );

    $program_id = !empty($app_data['program_id']) ? (int) $app_data['program_id'] : 0;
    $course_id  = !empty($app_data['course_id']) ? (int) $app_data['course_id'] : 0;

    // 2. Fallback to name-based lookup for legacy records or missing IDs
    if ($program_id <= 0 && $course_id <= 0) {
        $app_form = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT course_name, course_id FROM {$forms_table} WHERE application_id = %d",
                $application_id
            ),
            ARRAY_A
        );

        if (!empty($app_form['course_id'])) {
            $course_id = (int) $app_form['course_id'];
        }

        if ($program_id <= 0 && $course_id <= 0 && !empty($app_form['course_name'])) {
            $course_name = trim($app_form['course_name']);
            $course_name_clean = preg_replace('/\s*\(NQF\s+\d+\)\s*$/i', '', $course_name);
            
            // Try program name
            $program_id = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}nds_programs WHERE name = %s OR name = %s AND status = 'active' LIMIT 1",
                $course_name_clean, $course_name
            ));

            if (!$program_id) {
                // Try course name
                $course_id = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}nds_courses WHERE name = %s OR name = %s AND status = 'active' LIMIT 1",
                    $course_name_clean, $course_name
                ));
            }
        }
    }

    // Resolve program_id if we only have course_id
    if ($program_id <= 0 && $course_id > 0) {
        $program_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT program_id FROM {$wpdb->prefix}nds_courses WHERE id = %d",
            $course_id
        ));
    }

    if ($program_id <= 0 && $course_id > 0) {
        $program_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT program_id FROM {$wpdb->prefix}nds_courses WHERE id = %d",
            $course_id
        ));
    }

    if ($course_id <= 0) {
        // Log failure
        return false;
    }

    // 3. Get Active Year and Semester
    $app_year_semester = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT academic_year_id, semester_id FROM {$apps_table} WHERE id = %d",
            $application_id
        ),
        ARRAY_A
    );

    $active_year_id = !empty($app_year_semester['academic_year_id']) ? (int) $app_year_semester['academic_year_id'] : 0;
    $active_semester_id = !empty($app_year_semester['semester_id']) ? (int) $app_year_semester['semester_id'] : 0;

    if (!$active_year_id) {
        $active_year_id = (int) $wpdb->get_var("SELECT id FROM {$wpdb->prefix}nds_academic_years WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
    }
    if (!$active_semester_id) {
        $active_semester_id = (int) $wpdb->get_var("SELECT id FROM {$wpdb->prefix}nds_semesters WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
    }

    // Fallback to latest if no active
    if (!$active_year_id) $active_year_id = (int) $wpdb->get_var("SELECT id FROM {$wpdb->prefix}nds_academic_years ORDER BY id DESC LIMIT 1");
    if (!$active_semester_id) $active_semester_id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}nds_semesters WHERE academic_year_id = %d ORDER BY id DESC LIMIT 1", $active_year_id));

    if (!$active_year_id || !$active_semester_id) {
        return false;
    }

    // 4. Enroll in the specific accepted course
    $enrollments_table = $wpdb->prefix . 'nds_student_enrollments';
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$enrollments_table} WHERE student_id = %d AND course_id = %d AND academic_year_id = %d AND semester_id = %d",
        $student_id,
        $course_id,
        $active_year_id,
        $active_semester_id
    ));

    if (!$exists) {
        $wpdb->insert($enrollments_table, [
            'student_id' => $student_id,
            'course_id' => $course_id,
            'academic_year_id' => $active_year_id,
            'semester_id' => $active_semester_id,
            'enrollment_date' => current_time('Y-m-d'),
            'status' => 'enrolled',
            'created_at' => current_time('mysql')
        ]);
    }

    // 5. Pre-link learner to modules in the accepted course for this term.
    if (function_exists('nds_portal_ensure_student_modules_table')) {
        $student_modules_table = nds_portal_ensure_student_modules_table();
        $module_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}nds_modules WHERE course_id = %d",
            $course_id
        ));

        if (!empty($module_ids)) {
            foreach ($module_ids as $module_id_raw) {
                $module_id = (int) $module_id_raw;
                if ($module_id <= 0) {
                    continue;
                }

                $module_exists = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$student_modules_table} WHERE student_id = %d AND module_id = %d AND academic_year_id = %d AND semester_id = %d",
                    $student_id,
                    $module_id,
                    $active_year_id,
                    $active_semester_id
                ));

                if ($module_exists > 0) {
                    $wpdb->update(
                        $student_modules_table,
                        array(
                            'course_id' => $course_id,
                            'status' => 'enrolled',
                        ),
                        array('id' => $module_exists),
                        array('%d', '%s'),
                        array('%d')
                    );
                } else {
                    $wpdb->insert(
                        $student_modules_table,
                        array(
                            'student_id' => $student_id,
                            'module_id' => $module_id,
                            'course_id' => $course_id,
                            'academic_year_id' => $active_year_id,
                            'semester_id' => $active_semester_id,
                            'status' => 'enrolled',
                            'created_at' => current_time('mysql'),
                        ),
                        array('%d', '%d', '%d', '%d', '%d', '%s', '%s')
                    );
                }
            }
        }
    }

    return true;
}

/**
 * Admin action to manually trigger enrollment for an accepted application
 */
function nds_manual_enroll_from_application_action() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    if (!isset($_GET['application_id']) || !isset($_GET['_wpnonce'])) {
        wp_die('Missing parameters');
    }
    
    if (!wp_verify_nonce($_GET['_wpnonce'], 'nds_manual_enroll_' . intval($_GET['application_id']))) {
        wp_die('Security check failed');
    }
    
    $application_id = intval($_GET['application_id']);
    
    // #region agent log: manual enrollment trigger
    @file_put_contents(
        __DIR__ . '/../.cursor/debug.log',
        json_encode(array(
            'sessionId' => 'debug-session',
            'runId' => 'enroll-manual',
            'hypothesisId' => 'H_manual_enroll',
            'location' => 'applicants-management.php:manual_enroll_action',
            'message' => 'Manual enrollment triggered',
            'data' => array(
                'application_id' => $application_id,
            ),
            'timestamp' => round(microtime(true) * 1000),
        )) . PHP_EOL,
        FILE_APPEND
    );
    // #endregion
    
    $result = nds_enroll_student_from_application($application_id);
    
    if ($result) {
        wp_redirect(admin_url('admin.php?page=nds-applicants&enrolled=1'));
    } else {
        wp_redirect(admin_url('admin.php?page=nds-applicants&enroll_failed=1'));
    }
    exit;
}
add_action('admin_post_nds_manual_enroll_from_application', 'nds_manual_enroll_from_application_action');

/**
 * Admin action to revert a learner created from an application
 * This:
 * - deletes the student record (and any enrollments via FK / explicit delete)
 * - clears the student link on the application
 * - resets the application status back to 'submitted'
 */
function nds_revert_student_from_application_action() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    if (!isset($_GET['application_id']) || !isset($_GET['_wpnonce'])) {
        wp_die('Missing parameters');
    }

    $application_id = intval($_GET['application_id']);

    if (!wp_verify_nonce($_GET['_wpnonce'], 'nds_revert_student_' . $application_id)) {
        wp_die('Security check failed');
    }

    global $wpdb;
    $apps_table        = $wpdb->prefix . 'nds_applications';
    $students_table    = $wpdb->prefix . 'nds_students';
    $enrollments_table = $wpdb->prefix . 'nds_student_enrollments';

    // Look up the linked student
    $student_id = (int) $wpdb->get_var(
        $wpdb->prepare("SELECT student_id FROM {$apps_table} WHERE id = %d", $application_id)
    );

    if ($student_id > 0) {
        // Remove any enrollments for this learner (defensive; FKs may also cascade)
        $wpdb->delete(
            $enrollments_table,
            array('student_id' => $student_id),
            array('%d')
        );

        // Delete the learner profile
        $wpdb->delete(
            $students_table,
            array('id' => $student_id),
            array('%d')
        );

        // Reset this specific application back to an un-decided submitted state and unlink the student
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$apps_table}
                 SET student_id = NULL,
                     status = %s,
                     decision_at = NULL,
                     decided_by = NULL
                 WHERE id = %d",
                'submitted',
                $application_id
            )
        );
    }

    // Redirect back to the applications dashboard with a flag
    wp_redirect(admin_url('admin.php?page=nds-applicants&reverted=1'));
    exit;
}
add_action('admin_post_nds_revert_student_from_application', 'nds_revert_student_from_application_action');

/**
 * Admin action to manually trigger enrollment for a student by student ID
 * Usage: /wp-admin/admin-post.php?action=nds_manual_enroll_student&student_id=1&_wpnonce=...
 */
function nds_manual_enroll_student_action() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    if (!isset($_GET['student_id']) || !isset($_GET['_wpnonce'])) {
        wp_die('Missing parameters');
    }
    
    $student_id = intval($_GET['student_id']);
    
    if (!wp_verify_nonce($_GET['_wpnonce'], 'nds_manual_enroll_student_' . $student_id)) {
        wp_die('Security check failed');
    }
    
    global $wpdb;
    
    // Find the most recent accepted application for this student
    $application = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}nds_applications 
             WHERE student_id = %d AND status = 'accepted' 
             ORDER BY id DESC LIMIT 1",
            $student_id
        ),
        ARRAY_A
    );
    
    if (!$application) {
        // Try by email
        $student = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT email FROM {$wpdb->prefix}nds_students WHERE id = %d",
                $student_id
            ),
            ARRAY_A
        );
        
        if ($student && !empty($student['email'])) {
            $application = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT a.id FROM {$wpdb->prefix}nds_applications a
                     LEFT JOIN {$wpdb->prefix}nds_application_forms af ON a.id = af.application_id
                     WHERE af.email = %s AND a.status = 'accepted'
                     ORDER BY a.id DESC LIMIT 1",
                    $student['email']
                ),
                ARRAY_A
            );
        }
    }
    
    if (!$application || empty($application['id'])) {
        wp_die('No accepted application found for this student');
    }
    
    // #region agent log: manual enrollment trigger by student_id
    @file_put_contents(
        __DIR__ . '/../.cursor/debug.log',
        json_encode(array(
            'sessionId' => 'debug-session',
            'runId' => 'enroll-manual',
            'hypothesisId' => 'H_manual_enroll_student',
            'location' => 'applicants-management.php:manual_enroll_student_action',
            'message' => 'Manual enrollment triggered by student_id',
            'data' => array(
                'student_id' => $student_id,
                'application_id' => (int) $application['id'],
            ),
            'timestamp' => round(microtime(true) * 1000),
        )) . PHP_EOL,
        FILE_APPEND
    );
    // #endregion
    
    $result = nds_enroll_student_from_application((int) $application['id'], $student_id);
    
    if ($result) {
        wp_redirect(admin_url('admin.php?page=nds-applicants&enrolled=1'));
    } else {
        wp_redirect(admin_url('admin.php?page=nds-applicants&enroll_failed=1'));
    }
    exit;
}
add_action('admin_post_nds_manual_enroll_student', 'nds_manual_enroll_student_action');

/**
 * Quick enrollment trigger - finds student by email and enrolls them
 * Usage: /wp-admin/admin-post.php?action=nds_quick_enroll&email=wuxas@mailinator.com&_wpnonce=...
 */
function nds_quick_enroll_by_email_action() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    if (!isset($_GET['email']) || !isset($_GET['_wpnonce'])) {
        wp_die('Missing parameters. Usage: ?action=nds_quick_enroll&email=student@email.com&_wpnonce=...');
    }
    
    $email = sanitize_email($_GET['email']);
    
    if (!wp_verify_nonce($_GET['_wpnonce'], 'nds_quick_enroll_' . md5($email))) {
        wp_die('Security check failed');
    }
    
    global $wpdb;
    
    // Find student by email
    $student = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}nds_students WHERE email = %s LIMIT 1",
            $email
        ),
        ARRAY_A
    );
    
    if (!$student || empty($student['id'])) {
        wp_die('Student not found with email: ' . esc_html($email));
    }
    
    $student_id = (int) $student['id'];
    
    // Find accepted application for this student
    $application = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT a.id FROM {$wpdb->prefix}nds_applications a
             LEFT JOIN {$wpdb->prefix}nds_application_forms af ON a.id = af.application_id
             WHERE (a.student_id = %d OR af.email = %s) AND a.status = 'accepted'
             ORDER BY a.id DESC LIMIT 1",
            $student_id, $email
        ),
        ARRAY_A
    );
    
    if (!$application || empty($application['id'])) {
        wp_die('No accepted application found for student: ' . esc_html($email));
    }
    
    $application_id = (int) $application['id'];
    
    // #region agent log: quick enrollment trigger
    @file_put_contents(
        __DIR__ . '/../.cursor/debug.log',
        json_encode(array(
            'sessionId' => 'debug-session',
            'runId' => 'enroll-quick',
            'hypothesisId' => 'H_quick_enroll',
            'location' => 'applicants-management.php:quick_enroll_action',
            'message' => 'Quick enrollment triggered by email',
            'data' => array(
                'email' => $email,
                'student_id' => $student_id,
                'application_id' => $application_id,
            ),
            'timestamp' => round(microtime(true) * 1000),
        )) . PHP_EOL,
        FILE_APPEND
    );
    // #endregion
    
    $result = nds_enroll_student_from_application($application_id, $student_id);
    
    if ($result) {
        wp_redirect(admin_url('admin.php?page=nds-applicants&enrolled=1&student=' . urlencode($email)));
    } else {
        wp_redirect(admin_url('admin.php?page=nds-applicants&enroll_failed=1&student=' . urlencode($email)));
    }
    exit;
}
add_action('admin_post_nds_quick_enroll', 'nds_quick_enroll_by_email_action');

/**
 * Bulk Update Application Status
 */
function nds_bulk_update_application_status($application_ids, $new_status) {
    global $wpdb;
    
    $placeholders = implode(',', array_fill(0, count($application_ids), '%d'));
    $application_ids[] = $new_status;
    $application_ids[] = get_current_user_id();
    $application_ids[] = current_time('mysql');
    $application_ids[] = current_time('mysql');
    
    $result = $wpdb->query($wpdb->prepare("
        UPDATE {$wpdb->prefix}nds_applications 
        SET status = %s, decided_by = %d, decision_at = %s, updated_at = %s
        WHERE id IN ($placeholders)
    ", $application_ids));
    
    if ($result !== false) {
        // For bulk accepted updates, move files for each application
        if ($new_status === 'accepted' && function_exists('nds_move_application_files_to_student')) {
            foreach ($application_ids as $maybe_id) {
                // Only the first N entries are IDs; later entries are metadata (status, user, timestamps)
                if (!is_int($maybe_id)) {
                    break;
                }
                nds_move_application_files_to_student($maybe_id);
            }
        }

        $_SESSION['nds_status_update_success'] = 'Successfully updated ' . $result . ' application(s)!';
        return true;
    } else {
        $_SESSION['nds_status_update_error'] = 'Failed to update applications: ' . $wpdb->last_error;
        return false;
    }
}

/**
 * Delete Application
 */
function nds_delete_application($application_id) {
    global $wpdb;
    
    // Start transaction
    $wpdb->query('START TRANSACTION');
    
    try {
        // Delete from applications table
        $wpdb->delete($wpdb->prefix . 'nds_applications', ['id' => $application_id]);
        
        // Delete from application_forms table
        $wpdb->delete($wpdb->prefix . 'nds_application_forms', ['application_id' => $application_id]);
        
        $wpdb->query('COMMIT');
        
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success"><p>Application deleted successfully!</p></div>';
        });
        
    } catch (Exception $e) {
        $wpdb->query('ROLLBACK');
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>Failed to delete application!</p></div>';
        });
    }
}

/**
 * Bulk Delete Applications
 */
function nds_bulk_delete_applications($application_ids) {
    global $wpdb;
    
    $placeholders = implode(',', array_fill(0, count($application_ids), '%d'));
    
    // Start transaction
    $wpdb->query('START TRANSACTION');
    
    try {
        // Delete from applications table
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}nds_applications WHERE id IN ($placeholders)", $application_ids));
        
        // Delete from application_forms table
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}nds_application_forms WHERE application_id IN ($placeholders)", $application_ids));
        
        $wpdb->query('COMMIT');
        
        add_action('admin_notices', function() use ($application_ids) {
            echo '<div class="notice notice-success"><p>Successfully deleted ' . count($application_ids) . ' application(s)!</p></div>';
        });
        
    } catch (Exception $e) {
        $wpdb->query('ROLLBACK');
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>Failed to delete applications!</p></div>';
        });
    }
}

/**
 * Convert Application to Student
 */
function nds_convert_application_to_student($application_id) {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    global $wpdb;
    
    // Start transaction
    $wpdb->query('START TRANSACTION');
    
    try {
        // Get application data
        $application = $wpdb->get_row($wpdb->prepare("
            SELECT 
                a.*,
                af.*
            FROM {$wpdb->prefix}nds_applications a
            LEFT JOIN {$wpdb->prefix}nds_application_forms af ON a.id = af.application_id
            WHERE a.id = %d AND a.status = 'accepted'
        ", $application_id), ARRAY_A);
        
        if (!$application) {
            throw new Exception('Application not found or not accepted');
        }
        
        // Generate student number
        $student_number = 'STU-' . date('Y') . '-' . str_pad($application_id, 4, '0', STR_PAD_LEFT);
        
        // Check if student already exists
        $existing_student = $wpdb->get_row($wpdb->prepare("
            SELECT id FROM {$wpdb->prefix}nds_students 
            WHERE email = %s OR student_number = %s
        ", $application['email'], $student_number), ARRAY_A);
        
        if ($existing_student) {
            throw new Exception('Student with this email or student number already exists');
        }
        
        // Create WordPress user if needed
        $wp_user_id = null;
        $existing_user = get_user_by('email', $application['email']);
        
        if (!$existing_user) {
            $username = sanitize_user($application['full_name']);
            $username = $wpdb->get_var($wpdb->prepare("SELECT user_login FROM {$wpdb->prefix}users WHERE user_login = %s", $username));
            if ($username) {
                $username = $username . '_' . time();
            }
            
            $user_id = wp_create_user($username, wp_generate_password(), $application['email']);
            if (is_wp_error($user_id)) {
                throw new Exception('Failed to create WordPress user: ' . $user_id->get_error_message());
            }
            $wp_user_id = $user_id;
        } else {
            $wp_user_id = $existing_user->ID;
        }
        
        // Create student record
        $student_data = [
            'student_number' => $student_number,
            'wp_user_id' => $wp_user_id,
            'first_name' => explode(' ', $application['full_name'])[0],
            'last_name' => substr($application['full_name'], strpos($application['full_name'], ' ') + 1),
            'email' => $application['email'],
            'phone' => $application['cell_no'],
            'date_of_birth' => $application['date_of_birth'],
            'gender' => $application['gender'],
            'address' => $application['street_address'],
            'city' => $application['city'],
            'country' => $application['country_of_birth'],
            'status' => 'active',
            'created_at' => current_time('mysql')
        ];
        
        $student_inserted = $wpdb->insert($wpdb->prefix . 'nds_students', $student_data);
        
        if (!$student_inserted) {
            throw new Exception('Failed to create student record');
        }
        
        $student_id = $wpdb->insert_id;
        
        // Create enrollment if course is specified
        if ($application['course_id']) {
            // Get active academic year and semester
            $active_year_id = (int) $wpdb->get_var("SELECT id FROM {$wpdb->prefix}nds_academic_years WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
            $active_semester_id = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}nds_semesters WHERE academic_year_id = %d AND is_active = 1 ORDER BY id DESC LIMIT 1",
                $active_year_id
            ));
            
            // Check for duplicate enrollment before inserting
            $existing_enrollment = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}nds_student_enrollments 
                 WHERE student_id = %d AND course_id = %d AND academic_year_id = %d AND semester_id = %d",
                $student_id, $application['course_id'], $active_year_id, $active_semester_id
            ));
            
            if (!$existing_enrollment) {
                $enrollment_data = [
                    'student_id' => $student_id,
                    'course_id' => $application['course_id'],
                    'academic_year_id' => $active_year_id ?: 1,
                    'semester_id' => $active_semester_id ?: 1,
                    'enrollment_date' => current_time('Y-m-d'),
                    'status' => 'enrolled',
                    'created_at' => current_time('mysql')
                ];
                
                $wpdb->insert($wpdb->prefix . 'nds_student_enrollments', $enrollment_data);
            } else {
                // Update existing enrollment status
                $wpdb->update(
                    $wpdb->prefix . 'nds_student_enrollments',
                    ['status' => 'enrolled', 'updated_at' => current_time('mysql')],
                    ['id' => $existing_enrollment],
                    ['%s', '%s'],
                    ['%d']
                );
            }
        }
        
        // Update application to mark as converted
        $wpdb->update(
            $wpdb->prefix . 'nds_applications',
            [
                'status' => 'converted_to_student',
                'student_id' => $student_id,
                'notes' => $application['notes'] . "\n\nConverted to student on " . current_time('Y-m-d H:i'),
                'updated_at' => current_time('mysql')
            ],
            ['id' => $application_id]
        );
        
        // Commit transaction
        $wpdb->query('COMMIT');
        
        // Success message
        add_action('admin_notices', function() use ($student_number, $application) {
            echo '<div class="notice notice-success"><p>';
            echo '<strong>Success!</strong> Application converted to student. ';
            echo 'Student Number: <strong>' . $student_number . '</strong><br>';
            echo 'Student: ' . $application['full_name'] . ' (' . $application['email'] . ')';
            echo '</p></div>';
        });
        
        // Redirect to students page
        wp_redirect(admin_url('admin.php?page=nds-all-learners&converted=1&student_id=' . $student_id));
        exit;
        
    } catch (Exception $e) {
        // Rollback on error
        $wpdb->query('ROLLBACK');
        
        add_action('admin_notices', function() use ($e) {
            echo '<div class="notice notice-error"><p><strong>Error:</strong> ' . $e->getMessage() . '</p></div>';
        });
    }
}
