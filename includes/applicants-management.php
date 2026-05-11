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
        
        $action = sanitize_text_field(wp_unslash($_POST['bulk_action']));
        $application_ids = array_map('intval', (array) wp_unslash($_POST['application_ids']));
        
        switch ($action) {
            case 'delete':
                nds_bulk_delete_applications($application_ids);
                break;
            case 'update_status':
                $new_status = isset($_POST['new_status']) ? sanitize_text_field(wp_unslash($_POST['new_status'])) : '';
                $allowed_statuses = array(
                    'submitted',
                    'under_review',
                    'waitlisted',
                    'conditional_offer',
                    'offer_made',
                    'accepted',
                    'declined',
                    'withdrawn',
                    'rejected',
                    'expired',
                );

                if (!in_array($new_status, $allowed_statuses, true)) {
                    $_SESSION['nds_status_update_error'] = 'Select a valid status for bulk update.';
                    wp_redirect(admin_url('admin.php?page=nds-applicants'));
                    exit;
                }

                nds_bulk_update_application_status($application_ids, $new_status);
                break;
        }
    }
    
    // Handle "New Application" action (admin creates application on behalf of student)
    if (isset($_GET['action']) && $_GET['action'] === 'new') {
        nds_render_admin_new_application_form();
        return;
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
                $deleted = nds_delete_application($id);
                wp_redirect(admin_url('admin.php?page=nds-applicants&' . ($deleted ? 'deleted=1' : 'delete_blocked=1')));
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
        $reject_reason  = sanitize_text_field($_POST['reject_reason'] ?? '');
        $notify_applicant = !empty($_POST['notify_applicant']);

        if ($new_status === 'rejected') {
            $reason_options = nds_get_rejection_reason_options();
            if (empty($reject_reason) || !isset($reason_options[$reject_reason])) {
                $_SESSION['nds_status_update_error'] = 'Please select a rejection reason before saving.';
                wp_redirect(admin_url('admin.php?page=nds-applicants'));
                exit;
            }

            $reason_note = 'Rejection reason: ' . $reason_options[$reject_reason];
            $notes = $notes !== '' ? ($reason_note . "\n" . $notes) : $reason_note;
        } else {
            $reason_options = nds_get_reason_options_for_status($new_status);
            if (!empty($reason_options)) {
                if (empty($reject_reason) || !isset($reason_options[$reject_reason])) {
                    $_SESSION['nds_status_update_error'] = 'Please select a reason for the chosen status before saving.';
                    wp_redirect(admin_url('admin.php?page=nds-applicants'));
                    exit;
                }
                $reason_note = ucfirst(str_replace('_', ' ', $new_status)) . ' reason: ' . $reason_options[$reject_reason];
                $notes = $notes !== '' ? ($reason_note . "\n" . $notes) : $reason_note;
            }
        }
        
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

    $filter_program_id = isset($_GET['filter_program_id']) ? max(0, intval($_GET['filter_program_id'])) : 0;
    $filter_faculty_id = isset($_GET['filter_faculty_id']) ? max(0, intval($_GET['filter_faculty_id'])) : 0;
    $filter_course_id = isset($_GET['filter_course_id']) ? max(0, intval($_GET['filter_course_id'])) : 0;
    $filter_status    = isset($_GET['filter_status']) ? sanitize_text_field(wp_unslash($_GET['filter_status'])) : '';

    $status_filter_options = array(
        'submitted'         => 'Submitted',
        'under_review'      => 'Under Review',
        'waitlisted'        => 'Waitlisted',
        'conditional_offer' => 'Conditional Offer',
        'offer_made'        => 'Offer Made',
        'accepted'          => 'Accepted',
        'accepted_group'    => 'Accepted (Accepted + Offers)',
        'declined'          => 'Declined',
        'withdrawn'         => 'Withdrawn',
        'rejected'          => 'Rejected',
        'expired'           => 'Expired',
    );

    if ($filter_status !== '' && !isset($status_filter_options[$filter_status])) {
        $filter_status = '';
    }

    $where_clauses = array("a.status != 'converted_to_student'");
    $where_values = array();

    if ($filter_program_id > 0) {
        $where_clauses[] = 'a.program_id = %d';
        $where_values[] = $filter_program_id;
    }

    if ($filter_faculty_id > 0) {
        $where_clauses[] = 'p.faculty_id = %d';
        $where_values[] = $filter_faculty_id;
    }

    if ($filter_course_id > 0) {
        $where_clauses[] = '(a.course_id = %d OR af.course_id = %d)';
        $where_values[] = $filter_course_id;
        $where_values[] = $filter_course_id;
    }

    if ($filter_status === 'accepted_group') {
        $where_clauses[] = "a.status IN ('accepted','offer_made','conditional_offer')";
    } elseif ($filter_status !== '') {
        $where_clauses[] = 'a.status = %s';
        $where_values[] = $filter_status;
    }

    $where_sql = implode(' AND ', $where_clauses);
    
    // Get total count - must match the applications query exactly
    // Business rule:
    // - Exclude only applications that have been converted to students.
    // - Active list shows all in-flight and accepted applications.
    // - Use COUNT(DISTINCT) to handle potential JOIN duplicates
    $total_count_sql = "
        SELECT COUNT(DISTINCT a.id)
        FROM {$wpdb->prefix}nds_applications a
        LEFT JOIN {$wpdb->prefix}nds_application_forms af ON a.id = af.application_id
        LEFT JOIN {$wpdb->prefix}nds_programs p ON p.id = a.program_id
        WHERE {$where_sql}
    ";

    if (!empty($where_values)) {
        $total_count = $wpdb->get_var($wpdb->prepare($total_count_sql, $where_values));
    } else {
        $total_count = $wpdb->get_var($total_count_sql);
    }
    
    // Get applications with form data (same filter as total_count)
    // Use GROUP BY to prevent duplicates if multiple form records exist per application
    $applications_sql = "
        SELECT 
            a.*,
            af.full_name,
            af.email,
            af.course_name,
            af.cell_no as phone,
            af.submitted_at as form_submitted_at,
            p.name as program_name,
            p.faculty_id,
            f.name as faculty_name,
            c.name as qualification_name
        FROM {$wpdb->prefix}nds_applications a
        LEFT JOIN {$wpdb->prefix}nds_application_forms af ON a.id = af.application_id
        LEFT JOIN {$wpdb->prefix}nds_programs p ON p.id = a.program_id
        LEFT JOIN {$wpdb->prefix}nds_faculties f ON f.id = p.faculty_id
        LEFT JOIN {$wpdb->prefix}nds_courses c ON c.id = COALESCE(a.course_id, af.course_id)
        WHERE {$where_sql}
        GROUP BY a.id
        ORDER BY a.submitted_at DESC
        LIMIT %d OFFSET %d
    ";

    $applications_query_values = $where_values;
    $applications_query_values[] = $per_page;
    $applications_query_values[] = $offset;
    $applications = $wpdb->get_results($wpdb->prepare($applications_sql, $applications_query_values), ARRAY_A);

    $filter_programs = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}nds_programs WHERE status = 'active' ORDER BY name ASC", ARRAY_A);
    $filter_faculties = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}nds_faculties WHERE status = 'active' ORDER BY name ASC", ARRAY_A);
    $filter_qualifications = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}nds_courses WHERE status = 'active' ORDER BY name ASC", ARRAY_A);
    
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

    // Per-status counts for tabs
    $status_counts = array();
    foreach ($all_active_applications as $row) {
        $st = $row['status'];
        if (!isset($status_counts[$st])) { $status_counts[$st] = 0; }
        $status_counts[$st]++;
    }
    $count_for = function($key) use ($status_counts) {
        if ($key === '') { return array_sum($status_counts); }
        if ($key === 'accepted_group') {
            return (isset($status_counts['accepted']) ? $status_counts['accepted'] : 0)
                 + (isset($status_counts['offer_made']) ? $status_counts['offer_made'] : 0)
                 + (isset($status_counts['conditional_offer']) ? $status_counts['conditional_offer'] : 0);
        }
        return isset($status_counts[$key]) ? $status_counts[$key] : 0;
    };

    // Tabs definition (order matters)
    $status_tabs = array(
        ''               => array('label' => 'All',           'color' => 'blue'),
        'submitted'      => array('label' => 'Submitted',     'color' => 'indigo'),
        'under_review'   => array('label' => 'Under Review',  'color' => 'amber'),
        'accepted_group' => array('label' => 'Accepted',      'color' => 'emerald'),
        'waitlisted'     => array('label' => 'Waitlisted',    'color' => 'sky'),
        'rejected'       => array('label' => 'Rejected',      'color' => 'red'),
        'declined'       => array('label' => 'Declined',      'color' => 'rose'),
        'withdrawn'      => array('label' => 'Withdrawn',     'color' => 'gray'),
        'expired'        => array('label' => 'Expired',       'color' => 'gray'),
    );

    $tab_base_args = array_filter(array(
        'page'              => 'nds-applicants',
        'filter_program_id' => $filter_program_id ?: null,
        'filter_faculty_id' => $filter_faculty_id ?: null,
        'filter_course_id'  => $filter_course_id ?: null,
    ));
    $tab_url = function($status) use ($tab_base_args) {
        $args = $tab_base_args;
        if ($status !== '') { $args['filter_status'] = $status; }
        return admin_url('admin.php?' . http_build_query($args));
    };
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
                                <?php if ($filter_status !== ''): ?>
                                    <span class="inline-flex items-center ml-2 px-2 py-0.5 rounded-full text-xs font-medium bg-blue-50 text-blue-700">
                                        Filtered: <?php echo esc_html($status_filter_options[$filter_status]); ?>
                                    </span>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=nds-applicants&action=new')); ?>"
                           class="inline-flex items-center px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium shadow-sm">
                            <span class="dashicons dashicons-plus-alt2 text-sm mr-1"></span>
                            New Application
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
            <!-- Flash messages -->
        <?php if (isset($_GET['app_created']) && $_GET['app_created'] === '1'): ?>
                <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-4 flex items-center">
                    <span class="dashicons dashicons-yes-alt text-emerald-600 mr-3 text-xl"></span>
                    <p class="text-sm text-emerald-800">Application created successfully!</p>
                </div>
        <?php endif; ?>
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

            <!-- Status Tabs -->
            <style>
                .nds-tab { display:inline-flex; align-items:center; gap:0.5rem; padding:0.625rem 1rem; font-size:0.875rem; font-weight:500; color:#4b5563; border-bottom:2px solid transparent; white-space:nowrap; text-decoration:none; transition: color .15s, border-color .15s; }
                .nds-tab:hover { color:#111827; border-bottom-color:#d1d5db; }
                .nds-tab.is-active { color:#1d4ed8; border-bottom-color:#2563eb; }
                .nds-tab-badge { display:inline-flex; align-items:center; justify-content:center; min-width:1.5rem; height:1.25rem; padding:0 0.5rem; border-radius:9999px; font-size:0.6875rem; font-weight:600; background:#f3f4f6; color:#374151; }
                .nds-tab.is-active .nds-tab-badge { background:#dbeafe; color:#1d4ed8; }
                .nds-tabs-wrap { background:#fff; border:1px solid #f3f4f6; border-radius:0.75rem; box-shadow:0 1px 2px 0 rgb(0 0 0 / 0.05); padding:0 0.5rem; overflow-x:auto; }
                .nds-tabs-inner { display:flex; align-items:center; gap:0.25rem; min-width:max-content; }
            </style>
            <nav class="nds-tabs-wrap" aria-label="Filter by status">
                <div class="nds-tabs-inner">
                    <?php foreach ($status_tabs as $tab_key => $tab): ?>
                        <?php $is_active = ($filter_status === $tab_key); ?>
                        <a href="<?php echo esc_url($tab_url($tab_key)); ?>"
                           class="nds-tab<?php echo $is_active ? ' is-active' : ''; ?>"
                           aria-current="<?php echo $is_active ? 'page' : 'false'; ?>">
                            <span><?php echo esc_html($tab['label']); ?></span>
                            <span class="nds-tab-badge"><?php echo number_format_i18n($count_for($tab_key)); ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </nav>
        
            <!-- Applications table + filters -->
            <div class="bg-white shadow-sm rounded-xl border border-gray-100 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-semibold text-gray-900">Applications</h2>
                        <p class="text-xs text-gray-500">All active applications (excluding converted students).</p>
                    </div>
                </div>

                <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-end">
                    <div class="text-xs text-gray-500">
                        Page <?php echo number_format_i18n($page); ?> of <?php echo number_format_i18n(max(1, ceil($total_count / $per_page))); ?>
                    </div>
                </div>

                <div class="px-5 py-3 border-b border-gray-100 bg-gray-50">
                    <form method="get" class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
                        <input type="hidden" name="page" value="nds-applicants">
                        <?php if ($filter_status !== ''): ?>
                            <input type="hidden" name="filter_status" value="<?php echo esc_attr($filter_status); ?>">
                        <?php endif; ?>
                        <div>
                            <label for="filter_program_id" class="block text-xs font-medium text-gray-600 mb-1">Program</label>
                            <select id="filter_program_id" name="filter_program_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                                <option value="">All programs</option>
                                <?php foreach ($filter_programs as $program_option): ?>
                                    <option value="<?php echo esc_attr($program_option['id']); ?>" <?php selected($filter_program_id, (int) $program_option['id']); ?>>
                                        <?php echo esc_html($program_option['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="filter_faculty_id" class="block text-xs font-medium text-gray-600 mb-1">Faculty</label>
                            <select id="filter_faculty_id" name="filter_faculty_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                                <option value="">All faculties</option>
                                <?php foreach ($filter_faculties as $faculty_option): ?>
                                    <option value="<?php echo esc_attr($faculty_option['id']); ?>" <?php selected($filter_faculty_id, (int) $faculty_option['id']); ?>>
                                        <?php echo esc_html($faculty_option['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="filter_course_id" class="block text-xs font-medium text-gray-600 mb-1">Qualification</label>
                            <select id="filter_course_id" name="filter_course_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                                <option value="">All qualifications</option>
                                <?php foreach ($filter_qualifications as $qualification_option): ?>
                                    <option value="<?php echo esc_attr($qualification_option['id']); ?>" <?php selected($filter_course_id, (int) $qualification_option['id']); ?>>
                                        <?php echo esc_html($qualification_option['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="flex gap-2">
                            <button type="submit" class="inline-flex items-center px-3 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium">Apply filters</button>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=nds-applicants')); ?>" class="inline-flex items-center px-3 py-2 rounded-lg bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-xs font-medium">Reset</a>
                        </div>
                    </form>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-5 py-2 text-left font-medium text-gray-500 text-xs uppercase tracking-wide">App #</th>
                                <th class="px-5 py-2 text-left font-medium text-gray-500 text-xs uppercase tracking-wide">Name</th>
                                <th class="px-5 py-2 text-left font-medium text-gray-500 text-xs uppercase tracking-wide">Email</th>
                                <th class="px-5 py-2 text-left font-medium text-gray-500 text-xs uppercase tracking-wide">Program</th>
                                <th class="px-5 py-2 text-left font-medium text-gray-500 text-xs uppercase tracking-wide">Faculty</th>
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
                                            <?php echo esc_html($app['program_name'] ?: 'Not linked'); ?>
                                        </td>
                                        <td class="px-5 py-2 whitespace-nowrap text-gray-700">
                                            <?php echo esc_html($app['faculty_name'] ?: 'Not linked'); ?>
                                        </td>
                                        <td class="px-5 py-2 whitespace-nowrap text-gray-700">
                                            <?php echo esc_html($app['qualification_name'] ?: $app['course_name']); ?>
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
                                            </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="px-5 py-6 text-center text-sm text-gray-500">
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
                                'add_args'  => array_filter(array(
                                    'filter_program_id' => $filter_program_id,
                                    'filter_faculty_id' => $filter_faculty_id,
                                    'filter_course_id'  => $filter_course_id,
                                    'filter_status'     => $filter_status,
                                )),
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
                                <option value="waitlisted">Waitlisted</option>
                                <option value="conditional_offer">Conditional Offer</option>
                                <option value="offer_made">Offer Made</option>
                                <option value="accepted">Accepted</option>
                                <option value="declined">Declined</option>
                                <option value="withdrawn">Withdrawn</option>
                                <option value="rejected">Rejected</option>
                                <option value="expired">Expired</option>
                            </select>
                        </div>

                        <div id="rejection-reason-wrap" class="hidden">
                            <label for="reject_reason" id="reason-label" class="block text-sm font-semibold text-gray-900 mb-2">Reason</label>
                            <select name="reject_reason" id="reject_reason"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm">
                                <option value="">Select reason</option>
                            </select>
                        </div>

                        <div>
                            <label for="notes" class="block text-sm font-semibold text-gray-900 mb-2">Notes</label>
                            <select name="notes" id="notes"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm">
                                <option value="">Select a note</option>
                            </select>
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
    window.NDS_STATUS_REASONS = <?php echo wp_json_encode(nds_get_status_reason_options_map()); ?>;
    window.NDS_STATUS_NOTES   = <?php echo wp_json_encode(nds_get_status_note_options_map()); ?>;
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
        const bulkStatusSelector = document.getElementById('bulk-new-status');
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

        if (bulkStatusSelector) {
            bulkStatusSelector.addEventListener('change', updateBulkActionButton);
        }

        const bulkActionsForm = document.getElementById('bulk-actions-form');
        if (bulkActionsForm) {
            bulkActionsForm.addEventListener('submit', function(event) {
                const checked = document.querySelectorAll('.application-checkbox:checked');
                const selectedAction = bulkActionSelector ? bulkActionSelector.value : '';
                const selectedStatus = bulkStatusSelector ? bulkStatusSelector.value : '';

                if (!checked.length) {
                    event.preventDefault();
                    alert('Select at least one application.');
                    return;
                }

                if (!selectedAction) {
                    event.preventDefault();
                    alert('Select a bulk action.');
                    return;
                }

                if (selectedAction === 'update_status' && !selectedStatus) {
                    event.preventDefault();
                    alert('Select a new status for bulk update.');
                    return;
                }

                if (selectedAction === 'delete' && !window.confirm('Delete selected applications? This cannot be undone.')) {
                    event.preventDefault();
                }
            });
        }

        function updateBulkActionButton() {
            const checked = document.querySelectorAll('.application-checkbox:checked');
            const selectedAction = bulkActionSelector ? bulkActionSelector.value : '';
            const requiresStatus = selectedAction === 'update_status';

            if (bulkStatusSelector) {
                bulkStatusSelector.classList.toggle('hidden', !requiresStatus);
            }

            const hasValidStatus = !requiresStatus || (bulkStatusSelector && bulkStatusSelector.value);
            if (bulkActionButton) {
                bulkActionButton.disabled = checked.length === 0 || !selectedAction || !hasValidStatus;
            }
        }

        updateBulkActionButton();
        
        if (statusModal && statusModal.parentElement !== document.body) {
            document.body.appendChild(statusModal);
        }

        function toggleRejectReasonField() {
            const statusField = document.getElementById('new_status');
            const reasonWrap = document.getElementById('rejection-reason-wrap');
            const reasonField = document.getElementById('reject_reason');
            const reasonLabel = document.getElementById('reason-label');
            const notesField = document.getElementById('notes');

            if (!statusField || !reasonWrap || !reasonField) return;

            const reasonMap = (window.NDS_STATUS_REASONS || {});
            const opts = reasonMap[statusField.value];

            if (opts && Object.keys(opts).length) {
                // Rebuild options
                reasonField.innerHTML = '<option value="">Select reason</option>';
                Object.keys(opts).forEach(function(key) {
                    const o = document.createElement('option');
                    o.value = key;
                    o.textContent = opts[key];
                    reasonField.appendChild(o);
                });
                if (reasonLabel) {
                    const labelMap = { rejected:'Rejection reason', declined:'Decline reason', waitlisted:'Waitlist reason', withdrawn:'Withdrawal reason', expired:'Expiry reason' };
                    reasonLabel.textContent = labelMap[statusField.value] || 'Reason';
                }
                reasonWrap.classList.remove('hidden');
                reasonField.required = true;
            } else {
                reasonWrap.classList.add('hidden');
                reasonField.required = false;
                reasonField.value = '';
                reasonField.innerHTML = '<option value="">Select reason</option>';
            }

            // Populate notes dropdown for current status
            if (notesField && notesField.tagName === 'SELECT') {
                const notesMap = (window.NDS_STATUS_NOTES || {});
                const noteOpts = notesMap[statusField.value] || {};
                notesField.innerHTML = '<option value="">Select a note</option>';
                Object.keys(noteOpts).forEach(function(key) {
                    const o = document.createElement('option');
                    o.value = noteOpts[key];
                    o.textContent = noteOpts[key];
                    notesField.appendChild(o);
                });
            }
        }

        window.openStatusModal = function(id, status) {
            const modal = document.getElementById('status-modal');
            const idField = document.getElementById('modal-application-id');
            const statusField = document.getElementById('new_status');

            if (idField && id) idField.value = id;
            if (statusField && status) statusField.value = status;

            toggleRejectReasonField();

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

        const newStatusField = document.getElementById('new_status');
        if (newStatusField) {
            newStatusField.addEventListener('change', toggleRejectReasonField);
            toggleRejectReasonField();
        }
        
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
        $reject_reason  = sanitize_text_field($_POST['reject_reason'] ?? '');
        $notify_applicant = !empty($_POST['notify_applicant']);

        if ($new_status === 'rejected') {
            $reason_options = nds_get_rejection_reason_options();
            if (empty($reject_reason) || !isset($reason_options[$reject_reason])) {
                $_SESSION['nds_status_update_error'] = 'Please select a rejection reason before saving.';
                wp_redirect(
                    add_query_arg(
                        array(
                            'page'   => 'nds-applicants',
                            'action' => 'view',
                            'id'     => $application_id,
                        ),
                        admin_url('admin.php')
                    )
                );
                exit;
            }

            $reason_note = 'Rejection reason: ' . $reason_options[$reject_reason];
            $notes = $notes !== '' ? ($reason_note . "\n" . $notes) : $reason_note;
        } else {
            $reason_options = nds_get_reason_options_for_status($new_status);
            if (!empty($reason_options)) {
                if (empty($reject_reason) || !isset($reason_options[$reject_reason])) {
                    $_SESSION['nds_status_update_error'] = 'Please select a reason for the chosen status before saving.';
                    wp_redirect(
                        add_query_arg(
                            array(
                                'page'   => 'nds-applicants',
                                'action' => 'view',
                                'id'     => $application_id,
                            ),
                            admin_url('admin.php')
                        )
                    );
                    exit;
                }
                $reason_note = ucfirst(str_replace('_', ' ', $new_status)) . ' reason: ' . $reason_options[$reject_reason];
                $notes = $notes !== '' ? ($reason_note . "\n" . $notes) : $reason_note;
            }
        }

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
                        <?php
                        $delete_block_reason = '';
                        $can_delete = nds_can_delete_application((int) $application['id'], $delete_block_reason);
                        $delete_url = wp_nonce_url(
                            admin_url('admin.php?page=nds-applicants&action=delete&id=' . (int) $application['id']),
                            'nds_applicants_action_delete_' . (int) $application['id']
                        );
                        ?>
                        <?php if ($can_delete): ?>
                            <a href="<?php echo esc_url($delete_url); ?>"
                               onclick="return confirm('Permanently delete application <?php echo esc_js($application['application_no']); ?>? This cannot be undone.');"
                               style="background-color:#dc2626 !important; color:#ffffff !important; border:1px solid #b91c1c !important;"
                               onmouseover="this.style.backgroundColor='#b91c1c';"
                               onmouseout="this.style.backgroundColor='#dc2626';"
                               class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium no-underline">
                                <span class="dashicons dashicons-trash text-sm mr-1"></span>
                                Delete
                            </a>
                        <?php else: ?>
                            <button type="button" disabled
                                    title="<?php echo esc_attr($delete_block_reason); ?>"
                                    style="background-color:#fecaca !important; color:#7f1d1d !important; border:1px solid #fca5a5 !important; opacity:0.7;"
                                    class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium cursor-not-allowed">
                                <span class="dashicons dashicons-trash text-sm mr-1"></span>
                                Delete
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
            <?php
            // Show Enroll / Revert actions only when relevant (after acceptance)
            $show_post_accept_actions = false;
            $student_id = !empty($application['student_id']) ? (int) $application['student_id'] : 0;
            $has_enrollments = false;
            if ($application['status'] === 'accepted' && $student_id > 0) {
                global $wpdb;
                $enrollment_count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}nds_student_enrollments WHERE student_id = %d",
                    $student_id
                ));
                $has_enrollments = $enrollment_count > 0;
                $show_post_accept_actions = true;
            }
            ?>
            <?php if ($show_post_accept_actions): ?>
            <div class="bg-white shadow-sm rounded-xl border border-gray-100 p-5 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h2 class="text-sm font-semibold text-gray-900">Post-acceptance actions</h2>
                    <p class="text-xs text-gray-500">Enroll the learner in their course or revert this acceptance.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <?php if (!$has_enrollments): ?>
                        <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=nds_manual_enroll_from_application&application_id=' . intval($application['id'])), 'nds_manual_enroll_' . intval($application['id'])); ?>"
                           class="inline-flex items-center px-3 py-2 rounded-lg border border-blue-500 text-xs font-medium text-blue-700 bg-blue-50 hover:bg-blue-100"
                           onclick="return confirm('This will enroll the student in the course(s) from their application. Continue?');">
                            <span class="dashicons dashicons-groups text-xs mr-1"></span>
                            Enroll in Course
                        </a>
                    <?php endif; ?>
                    <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=nds_revert_student_from_application&application_id=' . intval($application['id'])), 'nds_revert_student_' . intval($application['id'])); ?>"
                       class="inline-flex items-center px-3 py-2 rounded-lg border border-red-500 text-xs font-medium text-red-700 bg-red-50 hover:bg-red-100"
                       onclick="return confirm('This will delete the learner record and any enrollments, and return this person to applicant-only. Continue?');">
                        <span class="dashicons dashicons-undo text-xs mr-1"></span>
                        Revert to Applicant
                    </a>
                </div>
            </div>
            <?php endif; ?>

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
                                <option value="waitlisted">Waitlisted</option>
                                <option value="conditional_offer">Conditional Offer</option>
                                <option value="offer_made">Offer Made</option>
                                <option value="accepted">Accepted</option>
                                <option value="declined">Declined</option>
                                <option value="withdrawn">Withdrawn</option>
                                <option value="rejected">Rejected</option>
                                <option value="expired">Expired</option>
                            </select>
                        </div>

                        <div id="rejection-reason-wrap" class="hidden">
                            <label for="reject_reason" id="reason-label" class="block text-sm font-semibold text-gray-900 mb-2">Reason</label>
                            <select name="reject_reason" id="reject_reason"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm">
                                <option value="">Select reason</option>
                            </select>
                        </div>

                        <div>
                            <label for="notes" class="block text-sm font-semibold text-gray-900 mb-2">Notes</label>
                            <select name="notes" id="notes"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm">
                                <option value="">Select a note</option>
                            </select>
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
    window.NDS_STATUS_REASONS = <?php echo wp_json_encode(nds_get_status_reason_options_map()); ?>;
    window.NDS_STATUS_NOTES   = <?php echo wp_json_encode(nds_get_status_note_options_map()); ?>;
    document.addEventListener('DOMContentLoaded', function() {
        const statusModal = document.getElementById('status-modal');
        const updateButtons = document.querySelectorAll('.update-status-btn');
        const quickStatusButtons = document.querySelectorAll('.nds-quick-status-btn');

        // Ensure modal is attached directly to <body> so it centers over the full viewport
        if (statusModal && statusModal.parentElement !== document.body) {
            document.body.appendChild(statusModal);
        }

        function applyReasonField() {
            const statusField = document.getElementById('new_status');
            const reasonWrap = document.getElementById('rejection-reason-wrap');
            const reasonField = document.getElementById('reject_reason');
            const reasonLabel = document.getElementById('reason-label');
            const notesField = document.getElementById('notes');
            if (!statusField || !reasonWrap || !reasonField) return;

            const reasonMap = (window.NDS_STATUS_REASONS || {});
            const opts = reasonMap[statusField.value];

            if (opts && Object.keys(opts).length) {
                reasonField.innerHTML = '<option value="">Select reason</option>';
                Object.keys(opts).forEach(function(key) {
                    const o = document.createElement('option');
                    o.value = key;
                    o.textContent = opts[key];
                    reasonField.appendChild(o);
                });
                if (reasonLabel) {
                    const labelMap = { rejected:'Rejection reason', declined:'Decline reason', waitlisted:'Waitlist reason', withdrawn:'Withdrawal reason', expired:'Expiry reason' };
                    reasonLabel.textContent = labelMap[statusField.value] || 'Reason';
                }
                reasonWrap.classList.remove('hidden');
                reasonField.required = true;
            } else {
                reasonWrap.classList.add('hidden');
                reasonField.required = false;
                reasonField.value = '';
                reasonField.innerHTML = '<option value="">Select reason</option>';
            }

            // Populate notes dropdown for current status
            if (notesField && notesField.tagName === 'SELECT') {
                const notesMap = (window.NDS_STATUS_NOTES || {});
                const noteOpts = notesMap[statusField.value] || {};
                notesField.innerHTML = '<option value="">Select a note</option>';
                Object.keys(noteOpts).forEach(function(key) {
                    const o = document.createElement('option');
                    o.value = noteOpts[key];
                    o.textContent = noteOpts[key];
                    notesField.appendChild(o);
                });
            }
        }

        function openStatusModal(id, status) {
            const idField = document.getElementById('modal-application-id');
            const statusField = document.getElementById('new_status');

            if (idField) {
                idField.value = id;
            }
            if (statusField && status) {
                statusField.value = status;
            }

            applyReasonField();

            if (statusModal) {
                statusModal.classList.remove('hidden');
                statusModal.classList.add('flex', 'items-center', 'justify-center');
                statusModal.style.display = 'flex';
                document.body.style.overflow = 'hidden';
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

        const newStatusField = document.getElementById('new_status');
        if (newStatusField) {
            newStatusField.addEventListener('change', applyReasonField);
        }

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

    // Values resolved during accepted-flow to append to application update safely
    $resolved_course_id_for_update = 0;
    $resolved_program_id_for_update = 0;
    
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
                $resolved_course_id_for_update = $resolved_course_id;
            }

            if ($resolved_program_id > 0) {
                $resolved_program_id_for_update = $resolved_program_id;
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

    if ($resolved_course_id_for_update > 0) {
        $update_data['course_id'] = $resolved_course_id_for_update;
        $update_formats[] = '%d';
    }

    if ($resolved_program_id_for_update > 0) {
        $update_data['program_id'] = $resolved_program_id_for_update;
        $update_formats[] = '%d';
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

        // Keep legacy form status aligned with workflow status used by admin/staff dashboards.
        $forms_status_map = array(
            'submitted'         => 'pending',
            'under_review'      => 'reviewed',
            'waitlisted'        => 'reviewed',
            'conditional_offer' => 'reviewed',
            'offer_made'        => 'reviewed',
            'accepted'          => 'accepted',
            'declined'          => 'rejected',
            'withdrawn'         => 'rejected',
            'rejected'          => 'rejected',
            'expired'           => 'rejected',
        );

        if (isset($forms_status_map[$new_status])) {
            $wpdb->update(
                $wpdb->prefix . 'nds_application_forms',
                array('status' => $forms_status_map[$new_status]),
                array('application_id' => $application_id),
                array('%s'),
                array('%d')
            );
        }

        if ($new_status === 'accepted' && function_exists('nds_move_application_files_to_student')) {
            nds_move_application_files_to_student($application_id);
        }

        // Optionally notify the applicant via email about this status change
        if ($notify_applicant) {
            $application = $wpdb->get_row($wpdb->prepare("\n                SELECT \n                    a.application_no,\n                    a.status,\n                    af.full_name,\n                    af.email\n                FROM {$wpdb->prefix}nds_applications a\n                LEFT JOIN {$wpdb->prefix}nds_application_forms af ON a.id = af.application_id\n                WHERE a.id = %d\n            ", $application_id), ARRAY_A);

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

        $_SESSION['nds_status_update_success'] = $notify_applicant
            ? 'Application status updated and the applicant has been emailed.'
            : 'Application status updated successfully!';
        return true;
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
 * Rejection reason options used in status update modals.
 */
function nds_get_rejection_reason_options() {
    return array(
        'academic_requirements' => 'Academic requirements not met',
        'incomplete_documents' => 'Incomplete supporting documents',
        'capacity_full' => 'Program capacity is full',
        'fee_payment' => 'Application fee/payment issue',
        'eligibility_criteria' => 'Eligibility criteria not met',
        'other' => 'Other reason',
    );
}

/**
 * Reason options keyed by status. Statuses not present here do not require a reason.
 */
function nds_get_status_reason_options_map() {
    return array(
        'rejected' => nds_get_rejection_reason_options(),
        'declined' => array(
            'accepted_other_offer' => 'Applicant accepted another offer',
            'fees_too_high'        => 'Fees too high / financial reasons',
            'changed_mind'         => 'Changed mind about studying',
            'personal_reasons'     => 'Personal reasons',
            'other'                => 'Other reason',
        ),
        'waitlisted' => array(
            'capacity_full'        => 'Program capacity is full',
            'pending_documents'    => 'Awaiting supporting documents',
            'pending_review'       => 'Awaiting further review',
            'other'                => 'Other reason',
        ),
        'withdrawn' => array(
            'applicant_request'    => 'Withdrawn at applicant request',
            'no_response'          => 'No response from applicant',
            'duplicate'            => 'Duplicate application',
            'other'                => 'Other reason',
        ),
        'expired' => array(
            'deadline_passed'      => 'Application deadline passed',
            'no_response'          => 'No response from applicant',
            'other'                => 'Other reason',
        ),
    );
}

function nds_get_reason_options_for_status($status) {
    $map = nds_get_status_reason_options_map();
    return isset($map[$status]) ? $map[$status] : array();
}

/**
 * Predefined note templates per status. JS populates the notes dropdown from this map.
 */
function nds_get_status_note_options_map() {
    $generic = array(
        'reviewed_documents'   => 'Reviewed all submitted documents.',
        'awaiting_documents'   => 'Awaiting outstanding documents from applicant.',
        'contacted_applicant'  => 'Contacted applicant for follow-up.',
        'no_response'          => 'No response received from applicant.',
        'manual_decision'      => 'Decision made after manual review.',
    );

    return array(
        'submitted'         => $generic,
        'under_review'      => array(
            'initial_review'      => 'Initial review in progress.',
            'awaiting_documents'  => 'Awaiting outstanding documents from applicant.',
            'verifying_records'   => 'Verifying academic records.',
            'panel_review'        => 'Forwarded to selection panel for review.',
        ),
        'waitlisted'        => array(
            'capacity_full'       => 'Program capacity is full — placed on waitlist.',
            'pending_documents'   => 'Waitlisted pending receipt of documents.',
            'pending_review'      => 'Waitlisted pending further review.',
        ),
        'conditional_offer' => array(
            'pending_results'     => 'Conditional offer pending final results.',
            'pending_documents'   => 'Conditional offer pending submission of documents.',
            'pending_payment'     => 'Conditional offer pending payment.',
        ),
        'offer_made'        => array(
            'standard_offer'      => 'Standard offer made — awaiting acceptance.',
            'with_scholarship'    => 'Offer made with scholarship/financial aid.',
            'with_deposit'        => 'Offer made — deposit required to confirm.',
        ),
        'accepted'          => array(
            'meets_requirements'  => 'Applicant meets all entry requirements.',
            'documents_verified'  => 'All supporting documents verified.',
            'panel_approved'      => 'Approved by selection panel.',
        ),
        'declined'          => array(
            'applicant_declined'  => 'Applicant declined the offer.',
            'accepted_other'      => 'Applicant accepted offer elsewhere.',
            'no_response'         => 'No response received from applicant.',
        ),
        'withdrawn'         => array(
            'applicant_request'   => 'Withdrawn at applicant request.',
            'duplicate'           => 'Duplicate application withdrawn.',
            'no_response'         => 'No response received from applicant.',
        ),
        'rejected'          => array(
            'requirements_not_met' => 'Applicant does not meet entry requirements.',
            'documents_incomplete' => 'Supporting documents incomplete.',
            'capacity_full'        => 'Rejected due to capacity constraints.',
            'panel_decision'       => 'Rejection decided by selection panel.',
        ),
        'expired'           => array(
            'deadline_passed'     => 'Application deadline passed without action.',
            'no_response'         => 'No response received from applicant.',
        ),
    );
}

function nds_get_note_options_for_status($status) {
    $map = nds_get_status_note_options_map();
    return isset($map[$status]) ? $map[$status] : array();
}

/**
 * Deletion guard for applications.
 * Only unlinked, non-progressed applications may be deleted.
 */
function nds_can_delete_application($application_id, &$failure_reason = '') {
    global $wpdb;

    $application = $wpdb->get_row($wpdb->prepare(
        "SELECT id, status, student_id FROM {$wpdb->prefix}nds_applications WHERE id = %d",
        $application_id
    ), ARRAY_A);

    if (empty($application)) {
        $failure_reason = 'Application does not exist.';
        return false;
    }

    if (!empty($application['student_id'])) {
        $failure_reason = 'Delete blocked: this application is linked to a student record.';
        return false;
    }

    $allowed_statuses = array('draft', 'submitted', 'rejected', 'declined', 'withdrawn', 'expired');
    if (!in_array($application['status'], $allowed_statuses, true)) {
        $failure_reason = 'Delete blocked: only draft, submitted, rejected, declined, withdrawn, or expired applications can be deleted.';
        return false;
    }

    return true;
}

/**
 * Bulk Update Application Status
 */
function nds_bulk_update_application_status($application_ids, $new_status) {
    global $wpdb;

    $ids = array_values(array_filter(array_map('intval', (array) $application_ids), function ($id) {
        return $id > 0;
    }));

    if (empty($ids)) {
        $_SESSION['nds_status_update_error'] = 'No valid applications selected for bulk update.';
        return false;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '%d'));
    $params = array_merge(
        array($new_status, get_current_user_id(), current_time('mysql'), current_time('mysql')),
        $ids
    );

    $result = $wpdb->query($wpdb->prepare(
        "UPDATE {$wpdb->prefix}nds_applications
         SET status = %s, decided_by = %d, decision_at = %s, updated_at = %s
         WHERE id IN ($placeholders)",
        $params
    ));

    if ($result !== false) {
        // Keep legacy application_forms status aligned for views that still read it.
        $forms_status_map = array(
            'submitted'         => 'pending',
            'under_review'      => 'reviewed',
            'waitlisted'        => 'reviewed',
            'conditional_offer' => 'reviewed',
            'offer_made'        => 'reviewed',
            'accepted'          => 'accepted',
            'declined'          => 'rejected',
            'withdrawn'         => 'rejected',
            'rejected'          => 'rejected',
            'expired'           => 'rejected',
        );

        if (isset($forms_status_map[$new_status])) {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}nds_application_forms SET status = %s WHERE application_id IN ($placeholders)",
                array_merge(array($forms_status_map[$new_status]), $ids)
            ));
        }

        // For bulk accepted updates, move files for each application
        if ($new_status === 'accepted' && function_exists('nds_move_application_files_to_student')) {
            foreach ($ids as $application_id) {
                nds_move_application_files_to_student((int) $application_id);
            }
        }

        $_SESSION['nds_status_update_success'] = 'Successfully updated ' . count($ids) . ' application(s)!';
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

    if (!session_id()) {
        session_start();
    }

    $failure_reason = '';
    if (!nds_can_delete_application((int) $application_id, $failure_reason)) {
        $_SESSION['nds_status_update_error'] = $failure_reason;
        return false;
    }
    
    // Start transaction
    $wpdb->query('START TRANSACTION');
    
    try {
        // Delete from applications table
        $wpdb->delete($wpdb->prefix . 'nds_applications', ['id' => $application_id]);
        
        // Delete from application_forms table
        $wpdb->delete($wpdb->prefix . 'nds_application_forms', ['application_id' => $application_id]);
        
        $wpdb->query('COMMIT');
        $_SESSION['nds_status_update_success'] = 'Application deleted successfully!';
        return true;
        
    } catch (Exception $e) {
        $wpdb->query('ROLLBACK');
        $_SESSION['nds_status_update_error'] = 'Failed to delete application!';
        return false;
    }
}

/**
 * Bulk Delete Applications
 */
function nds_bulk_delete_applications($application_ids) {
    global $wpdb;

    if (!session_id()) {
        session_start();
    }

    $allowed_ids = array();
    $blocked_count = 0;

    foreach ($application_ids as $application_id) {
        $failure_reason = '';
        if (nds_can_delete_application((int) $application_id, $failure_reason)) {
            $allowed_ids[] = (int) $application_id;
        } else {
            $blocked_count++;
        }
    }

    if (empty($allowed_ids)) {
        $_SESSION['nds_status_update_error'] = 'No selected applications met the delete conditions.';
        return false;
    }

    $placeholders = implode(',', array_fill(0, count($allowed_ids), '%d'));
    
    // Start transaction
    $wpdb->query('START TRANSACTION');
    
    try {
        // Delete from applications table
        $deleted_apps = $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}nds_applications WHERE id IN ($placeholders)", $allowed_ids));
        
        // Delete from application_forms table
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}nds_application_forms WHERE application_id IN ($placeholders)", $allowed_ids));
        
        $wpdb->query('COMMIT');

        $message = 'Successfully deleted ' . (int) $deleted_apps . ' application(s).';
        if ($blocked_count > 0) {
            $message .= ' ' . (int) $blocked_count . ' application(s) were skipped due to delete conditions.';
        }
        $_SESSION['nds_status_update_success'] = $message;
        return true;
        
    } catch (Exception $e) {
        $wpdb->query('ROLLBACK');
        $_SESSION['nds_status_update_error'] = 'Failed to delete applications!';
        return false;
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

// =============================================================================
// ADMIN: NEW APPLICATION FORM
// =============================================================================

/**
 * Render the "New Application" form for admins to submit on behalf of a student.
 * Accessible at: admin.php?page=nds-applicants&action=new
 */
function nds_render_admin_new_application_form() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    global $wpdb;

    // Enqueue styles
    $plugin_dir = plugin_dir_path(dirname(__FILE__));
    $css_file   = $plugin_dir . 'assets/css/frontend.css';
    if (file_exists($css_file)) {
        wp_enqueue_style(
            'nds-tailwindcss-new-app',
            plugin_dir_url(dirname(__FILE__)) . 'assets/css/frontend.css',
            array(), filemtime($css_file), 'all'
        );
    }

    // Load dropdowns
    $faculties       = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}nds_faculties ORDER BY name ASC", ARRAY_A);
    $programs        = $wpdb->get_results("SELECT id, name, faculty_id FROM {$wpdb->prefix}nds_programs ORDER BY name ASC", ARRAY_A);
    $qualifications  = $wpdb->get_results("SELECT id, name, program_id FROM {$wpdb->prefix}nds_courses ORDER BY name ASC", ARRAY_A);

    $nonce = wp_create_nonce('nds_admin_create_application');
    ?>
    <div class="wrap nds-tailwind-wrapper bg-gray-50 pb-32" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin-left: -20px; padding-left: 20px; margin-top: -20px;">
        <!-- Header -->
        <div class="bg-white shadow-sm border-b border-gray-200">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center py-6">
                    <div class="flex items-center space-x-4">
                        <div class="w-12 h-12 bg-gradient-to-br from-blue-600 to-purple-600 rounded-xl flex items-center justify-center">
                            <span class="dashicons dashicons-clipboard text-white text-2xl"></span>
                        </div>
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900">New Application</h1>
                            <p class="text-gray-600 text-sm">Submit an application on behalf of a student.</p>
                        </div>
                    </div>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=nds-applicants')); ?>"
                       class="inline-flex items-center px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium">
                        <span class="dashicons dashicons-arrow-left-alt2 text-sm mr-1"></span>
                        Back to applications
                    </a>
                </div>
            </div>
        </div>

        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" class="space-y-6">
                <?php wp_nonce_field('nds_admin_create_application', 'nds_admin_app_nonce'); ?>
                <input type="hidden" name="action" value="nds_admin_create_application">

                <!-- Course Selection -->
                <div class="bg-white shadow-sm rounded-xl border border-gray-100 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 bg-blue-50">
                        <h2 class="text-sm font-semibold text-blue-900">Course Selection</h2>
                    </div>
                    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Faculty <span class="text-red-500">*</span></label>
                            <select name="faculty_id" id="adm_faculty_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                                <option value="">Select faculty</option>
                                <?php foreach ($faculties as $fac): ?>
                                    <option value="<?php echo esc_attr($fac['id']); ?>"><?php echo esc_html($fac['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Program <span class="text-red-500">*</span></label>
                            <select name="program_id" id="adm_program_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                                <option value="">Select program</option>
                                <?php foreach ($programs as $prog): ?>
                                    <option value="<?php echo esc_attr($prog['id']); ?>" data-faculty="<?php echo esc_attr($prog['faculty_id']); ?>">
                                        <?php echo esc_html($prog['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Qualification (Course) <span class="text-red-500">*</span></label>
                            <select name="course_id" id="adm_course_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                                <option value="">Select qualification</option>
                                <?php foreach ($qualifications as $qual): ?>
                                    <option value="<?php echo esc_attr($qual['id']); ?>" data-program="<?php echo esc_attr($qual['program_id']); ?>"
                                        data-name="<?php echo esc_attr($qual['name']); ?>">
                                        <?php echo esc_html($qual['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="course_name" id="adm_course_name">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Level</label>
                            <select name="level" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                                <option value="">Select level</option>
                                <option value="NQF Level 1">NQF Level 1</option>
                                <option value="NQF Level 2">NQF Level 2</option>
                                <option value="NQF Level 3">NQF Level 3</option>
                                <option value="NQF Level 4">NQF Level 4</option>
                                <option value="NQF Level 5">NQF Level 5</option>
                                <option value="NQF Level 6">NQF Level 6</option>
                                <option value="NQF Level 7">NQF Level 7</option>
                                <option value="NQF Level 8">NQF Level 8</option>
                                <option value="NQF Level 9">NQF Level 9</option>
                                <option value="NQF Level 10">NQF Level 10</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Personal Details -->
                <div class="bg-white shadow-sm rounded-xl border border-gray-100 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 bg-blue-50">
                        <h2 class="text-sm font-semibold text-blue-900">Personal Details</h2>
                    </div>
                    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Full Name <span class="text-red-500">*</span></label>
                            <input type="text" name="full_name" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">ID / Passport Number <span class="text-red-500">*</span></label>
                            <input type="text" name="id_number" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Date of Birth <span class="text-red-500">*</span></label>
                            <input type="date" name="date_of_birth" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Gender <span class="text-red-500">*</span></label>
                            <select name="gender" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                                <option value="">Select gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                                <option value="Prefer not to say">Prefer not to say</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Marital Status</label>
                            <select name="marital_status" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                                <option value="">Select status</option>
                                <option value="Single">Single</option>
                                <option value="Married">Married</option>
                                <option value="Divorced">Divorced</option>
                                <option value="Widowed">Widowed</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nationality</label>
                            <input type="text" name="nationality" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500" placeholder="e.g. South African">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Country of Birth</label>
                            <input type="text" name="country_of_birth" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500" placeholder="e.g. South Africa">
                        </div>
                    </div>
                </div>

                <!-- Contact & Address -->
                <div class="bg-white shadow-sm rounded-xl border border-gray-100 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 bg-blue-50">
                        <h2 class="text-sm font-semibold text-blue-900">Contact &amp; Address</h2>
                    </div>
                    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email <span class="text-red-500">*</span></label>
                            <input type="email" name="email" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Cell / Phone <span class="text-red-500">*</span></label>
                            <input type="text" name="cell_no" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Street Address <span class="text-red-500">*</span></label>
                            <textarea name="street_address" required rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">City <span class="text-red-500">*</span></label>
                            <input type="text" name="city" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Province <span class="text-red-500">*</span></label>
                            <input type="text" name="province" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Postal Code <span class="text-red-500">*</span></label>
                            <input type="text" name="postal_code" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                </div>

                <!-- Person Responsible for Fees -->
                <div class="bg-white shadow-sm rounded-xl border border-gray-100 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 bg-blue-50">
                        <h2 class="text-sm font-semibold text-blue-900">Person Responsible for Fees</h2>
                    </div>
                    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Full Name <span class="text-red-500">*</span></label>
                            <input type="text" name="responsible_full_name" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Relationship</label>
                            <input type="text" name="relationship" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500" placeholder="e.g. Parent, Spouse, Self">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">ID Number <span class="text-red-500">*</span></label>
                            <input type="text" name="responsible_id_number" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Phone <span class="text-red-500">*</span></label>
                            <input type="text" name="responsible_phone" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email <span class="text-red-500">*</span></label>
                            <input type="email" name="responsible_email" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Occupation <span class="text-red-500">*</span></label>
                            <input type="text" name="occupation" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Street Address</label>
                            <textarea name="responsible_street_address" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">City</label>
                            <input type="text" name="responsible_city" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Province</label>
                            <input type="text" name="responsible_province" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Postal Code</label>
                            <input type="text" name="responsible_postal_code" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Company Name</label>
                            <input type="text" name="company_name" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Work Telephone</label>
                            <input type="text" name="work_telephone" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Work Email</label>
                            <input type="email" name="work_email" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                </div>

                <!-- Emergency Contact -->
                <div class="bg-white shadow-sm rounded-xl border border-gray-100 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 bg-blue-50">
                        <h2 class="text-sm font-semibold text-blue-900">Emergency Contact</h2>
                    </div>
                    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Full Name <span class="text-red-500">*</span></label>
                            <input type="text" name="emergency_full_name" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Relationship</label>
                            <input type="text" name="emergency_relationship" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Phone <span class="text-red-500">*</span></label>
                            <input type="text" name="emergency_phone" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email <span class="text-red-500">*</span></label>
                            <input type="email" name="emergency_email" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Street Address</label>
                            <textarea name="emergency_street_address" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">City</label>
                            <input type="text" name="emergency_city" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Province</label>
                            <input type="text" name="emergency_province" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Postal Code</label>
                            <input type="text" name="emergency_postal_code" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                </div>

                <!-- Educational Background -->
                <div class="bg-white shadow-sm rounded-xl border border-gray-100 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 bg-blue-50">
                        <h2 class="text-sm font-semibold text-blue-900">Educational Background</h2>
                    </div>
                    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Highest Grade Completed <span class="text-red-500">*</span></label>
                            <input type="text" name="highest_grade" required placeholder="e.g. Grade 12" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Year Passed <span class="text-red-500">*</span></label>
                            <input type="text" name="year_passed" required placeholder="e.g. 2020" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">School Attended <span class="text-red-500">*</span></label>
                            <input type="text" name="school_attended" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">School Location</label>
                            <input type="text" name="school_location" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Other Qualifications</label>
                            <textarea name="other_qualifications" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500" placeholder="List any other qualifications"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Year of Completion</label>
                            <input type="text" name="year_completion" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                </div>

                <!-- Languages -->
                <div class="bg-white shadow-sm rounded-xl border border-gray-100 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 bg-blue-50">
                        <h2 class="text-sm font-semibold text-blue-900">Languages</h2>
                    </div>
                    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Home Language</label>
                            <input type="text" name="home_language" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                        </div>
                        <?php
                        $lang_fields = array(
                            array('label' => 'English Writing',  'name' => 'english_write'),
                            array('label' => 'English Reading',  'name' => 'english_read'),
                            array('label' => 'English Speaking', 'name' => 'english_speak'),
                        );
                        foreach ($lang_fields as $lf): ?>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1"><?php echo esc_html($lf['label']); ?></label>
                            <select name="<?php echo esc_attr($lf['name']); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                                <option value="Good">Good</option>
                                <option value="Average">Average</option>
                                <option value="Poor">Poor</option>
                            </select>
                        </div>
                        <?php endforeach; ?>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Other Language</label>
                            <input type="text" name="other_language" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                        </div>
                        <?php
                        $other_lang_fields = array(
                            array('label' => 'Other Language Writing',  'name' => 'other_language_write'),
                            array('label' => 'Other Language Reading',  'name' => 'other_language_read'),
                            array('label' => 'Other Language Speaking', 'name' => 'other_language_speak'),
                        );
                        foreach ($other_lang_fields as $lf): ?>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1"><?php echo esc_html($lf['label']); ?></label>
                            <select name="<?php echo esc_attr($lf['name']); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                                <option value="Good">Good</option>
                                <option value="Average">Average</option>
                                <option value="Poor">Poor</option>
                            </select>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Medical -->
                <div class="bg-white shadow-sm rounded-xl border border-gray-100 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 bg-blue-50">
                        <h2 class="text-sm font-semibold text-blue-900">Medical Information</h2>
                    </div>
                    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-5">
                        <?php
                        $medical_fields = array(
                            array('name' => 'physical_illness',    'label' => 'Physical illness / disability?', 'specify' => 'specify_physical_illness'),
                            array('name' => 'food_allergies',      'label' => 'Food allergies?',                'specify' => 'specify_food_allergies'),
                            array('name' => 'chronic_medication',  'label' => 'Chronic medication?',            'specify' => 'specify_chronic_medication'),
                            array('name' => 'pregnant_or_planning','label' => 'Pregnant or planning?',          'specify' => null),
                            array('name' => 'smoke',               'label' => 'Do you smoke?',                  'specify' => null),
                        );
                        foreach ($medical_fields as $mf): ?>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1"><?php echo esc_html($mf['label']); ?></label>
                            <select name="<?php echo esc_attr($mf['name']); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                                <option value="No">No</option>
                                <option value="Yes">Yes</option>
                            </select>
                            <?php if ($mf['specify']): ?>
                            <input type="text" name="<?php echo esc_attr($mf['specify']); ?>" placeholder="If yes, please specify" class="mt-2 w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Supporting Documents -->
                <div class="bg-white shadow-sm rounded-xl border border-gray-100 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 bg-blue-50">
                        <h2 class="text-sm font-semibold text-blue-900">Supporting Documents <span class="text-xs font-normal text-gray-500">(PDF, optional)</span></h2>
                    </div>
                    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-5">
                        <?php
                        $doc_fields = array(
                            'id_passport_applicant'   => "Applicant's ID / Passport",
                            'id_passport_responsible' => "Responsible Person's ID / Passport",
                            'saqa_certificate'        => 'SAQA Certificate',
                            'study_permit'            => 'Study Permit',
                            'parent_spouse_id'        => 'Parent / Spouse ID',
                            'latest_results'          => 'Latest Results',
                            'proof_residence'         => 'Proof of Residence',
                            'highest_grade_cert'      => 'Highest Grade Certificate',
                            'proof_medical_aid'       => 'Proof of Medical Aid',
                        );
                        foreach ($doc_fields as $field => $label): ?>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1"><?php echo esc_html($label); ?></label>
                            <input type="file" name="<?php echo esc_attr($field); ?>" accept=".pdf"
                                   class="w-full text-sm text-gray-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Motivation -->
                <div class="bg-white shadow-sm rounded-xl border border-gray-100 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 bg-blue-50">
                        <h2 class="text-sm font-semibold text-blue-900">Motivation Letter</h2>
                    </div>
                    <div class="p-6">
                        <textarea name="motivation_letter" rows="5" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500" placeholder="Why does the applicant want to enrol in this course?"></textarea>
                    </div>
                </div>

                <!-- Submit -->
                <div class="flex items-center justify-end gap-4">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=nds-applicants')); ?>"
                       class="inline-flex items-center px-5 py-2.5 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium">
                        Cancel
                    </a>
                    <button type="submit"
                            class="inline-flex items-center px-6 py-2.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold shadow-sm">
                        <span class="dashicons dashicons-yes-alt text-sm mr-1"></span>
                        Submit Application
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    (function() {
        var facultySelect  = document.getElementById('adm_faculty_id');
        var programSelect  = document.getElementById('adm_program_id');
        var courseSelect   = document.getElementById('adm_course_id');
        var courseNameInput = document.getElementById('adm_course_name');

        function filterPrograms() {
            var facId = facultySelect.value;
            var opts = programSelect.querySelectorAll('option');
            var firstVisible = '';
            opts.forEach(function(opt) {
                if (!opt.value) return;
                var show = !facId || opt.dataset.faculty === facId;
                opt.style.display = show ? '' : 'none';
                if (show && !firstVisible) firstVisible = opt.value;
            });
            // Reset program if current selection is hidden
            if (facId && programSelect.value && programSelect.querySelector('option[value="' + programSelect.value + '"]').style.display === 'none') {
                programSelect.value = '';
            }
            filterCourses();
        }

        function filterCourses() {
            var progId = programSelect.value;
            var opts = courseSelect.querySelectorAll('option');
            opts.forEach(function(opt) {
                if (!opt.value) return;
                var show = !progId || opt.dataset.program === progId;
                opt.style.display = show ? '' : 'none';
            });
            if (progId && courseSelect.value && courseSelect.querySelector('option[value="' + courseSelect.value + '"]').style.display === 'none') {
                courseSelect.value = '';
                courseNameInput.value = '';
            }
        }

        function updateCourseName() {
            var sel = courseSelect.options[courseSelect.selectedIndex];
            courseNameInput.value = sel ? (sel.dataset.name || sel.text) : '';
        }

        facultySelect.addEventListener('change', filterPrograms);
        programSelect.addEventListener('change', filterCourses);
        courseSelect.addEventListener('change', updateCourseName);
    })();
    </script>
    <?php
}

/**
 * Handle admin submission of a new application.
 * Hooked to: admin_post_nds_admin_create_application
 */
function nds_handle_admin_create_application() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    // Verify nonce
    if (!isset($_POST['nds_admin_app_nonce']) || !wp_verify_nonce($_POST['nds_admin_app_nonce'], 'nds_admin_create_application')) {
        wp_die('Security check failed. Please try again.');
    }

    global $wpdb;

    $course_id   = intval($_POST['course_id'] ?? 0);
    $course_name = sanitize_text_field($_POST['course_name'] ?? '');

    if (!$course_id) {
        wp_die('Please select a qualification before submitting.');
    }
    if (empty($course_name)) {
        // Fallback: fetch from DB
        $course_row  = $wpdb->get_var($wpdb->prepare("SELECT name FROM {$wpdb->prefix}nds_courses WHERE id = %d", $course_id));
        $course_name = $course_row ? $course_row : 'Unknown Course';
    }

    // Handle file uploads (optional – same logic as frontend, but skipped if not provided)
    $plugin_dir     = plugin_dir_path(dirname(__FILE__));
    $temp_upload_dir = $plugin_dir . 'public/temp-uploads/';
    if (!file_exists($temp_upload_dir)) {
        wp_mkdir_p($temp_upload_dir);
    }

    $file_fields = array(
        'id_passport_applicant', 'id_passport_responsible', 'saqa_certificate',
        'study_permit', 'parent_spouse_id', 'latest_results', 'proof_residence',
        'highest_grade_cert', 'proof_medical_aid',
    );
    $temp_uploaded_files = array();
    foreach ($file_fields as $field) {
        if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
            $file     = $_FILES[$field];
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if ($file_ext !== 'pdf') { continue; }
            $unique_filename = $field . '_' . time() . '_' . uniqid() . '.pdf';
            $temp_file_path  = $temp_upload_dir . $unique_filename;
            if (move_uploaded_file($file['tmp_name'], $temp_file_path)) {
                $temp_uploaded_files[$field] = array(
                    'temp_path'     => $temp_file_path,
                    'original_name' => sanitize_file_name(pathinfo($file['name'], PATHINFO_FILENAME)),
                    'unique_name'   => $unique_filename,
                );
            }
        }
    }

    // Build form data (same fields as nds_handle_application_form_submission)
    $data = array(
        'level'                      => sanitize_text_field($_POST['level'] ?? ''),
        'course_id'                  => $course_id,
        'course_name'                => $course_name,
        'full_name'                  => sanitize_text_field($_POST['full_name'] ?? ''),
        'id_number'                  => sanitize_text_field($_POST['id_number'] ?? ''),
        'date_of_birth'              => sanitize_text_field($_POST['date_of_birth'] ?? ''),
        'gender'                     => sanitize_text_field($_POST['gender'] ?? ''),
        'nationality'                => mb_substr(sanitize_text_field($_POST['nationality'] ?? ''), 0, 100),
        'country_of_birth'           => sanitize_text_field($_POST['country_of_birth'] ?? ''),
        'marital_status'             => sanitize_text_field($_POST['marital_status'] ?? ''),
        'street_address'             => sanitize_textarea_field($_POST['street_address'] ?? ''),
        'city'                       => sanitize_text_field($_POST['city'] ?? ''),
        'postal_code'                => sanitize_text_field($_POST['postal_code'] ?? ''),
        'province'                   => sanitize_text_field($_POST['province'] ?? ''),
        'cell_no'                    => sanitize_text_field($_POST['cell_no'] ?? ''),
        'email'                      => sanitize_email($_POST['email'] ?? ''),
        'responsible_full_name'      => sanitize_text_field($_POST['responsible_full_name'] ?? ''),
        'relationship'               => sanitize_text_field($_POST['relationship'] ?? ''),
        'responsible_id_number'      => sanitize_text_field($_POST['responsible_id_number'] ?? ''),
        'responsible_phone'          => sanitize_text_field($_POST['responsible_phone'] ?? ''),
        'responsible_email'          => sanitize_email($_POST['responsible_email'] ?? ''),
        'responsible_street_address' => sanitize_textarea_field($_POST['responsible_street_address'] ?? ''),
        'responsible_city'           => sanitize_text_field($_POST['responsible_city'] ?? ''),
        'responsible_postal_code'    => sanitize_text_field($_POST['responsible_postal_code'] ?? ''),
        'responsible_province'       => sanitize_text_field($_POST['responsible_province'] ?? ''),
        'occupation'                 => sanitize_text_field($_POST['occupation'] ?? ''),
        'company_name'               => sanitize_text_field($_POST['company_name'] ?? ''),
        'work_telephone'             => sanitize_text_field($_POST['work_telephone'] ?? ''),
        'work_email'                 => sanitize_email($_POST['work_email'] ?? ''),
        'emergency_full_name'        => sanitize_text_field($_POST['emergency_full_name'] ?? ''),
        'emergency_relationship'     => sanitize_text_field($_POST['emergency_relationship'] ?? ''),
        'emergency_phone'            => sanitize_text_field($_POST['emergency_phone'] ?? ''),
        'emergency_email'            => sanitize_email($_POST['emergency_email'] ?? ''),
        'emergency_street_address'   => sanitize_textarea_field($_POST['emergency_street_address'] ?? ''),
        'emergency_city'             => sanitize_text_field($_POST['emergency_city'] ?? ''),
        'emergency_postal_code'      => sanitize_text_field($_POST['emergency_postal_code'] ?? ''),
        'emergency_province'         => sanitize_text_field($_POST['emergency_province'] ?? ''),
        'highest_grade'              => sanitize_text_field($_POST['highest_grade'] ?? ''),
        'year_passed'                => sanitize_text_field($_POST['year_passed'] ?? ''),
        'school_attended'            => sanitize_text_field($_POST['school_attended'] ?? ''),
        'school_location'            => sanitize_text_field($_POST['school_location'] ?? ''),
        'other_qualifications'       => sanitize_text_field($_POST['other_qualifications'] ?? ''),
        'year_completion'            => sanitize_text_field($_POST['year_completion'] ?? ''),
        'home_language'              => sanitize_text_field($_POST['home_language'] ?? ''),
        'english_write'              => sanitize_text_field($_POST['english_write'] ?? 'Good'),
        'english_read'               => sanitize_text_field($_POST['english_read'] ?? 'Good'),
        'english_speak'              => sanitize_text_field($_POST['english_speak'] ?? 'Good'),
        'other_language'             => sanitize_text_field($_POST['other_language'] ?? ''),
        'other_language_write'       => sanitize_text_field($_POST['other_language_write'] ?? 'Good'),
        'other_language_read'        => sanitize_text_field($_POST['other_language_read'] ?? 'Good'),
        'other_language_speak'       => sanitize_text_field($_POST['other_language_speak'] ?? 'Good'),
        'physical_illness'           => sanitize_text_field($_POST['physical_illness'] ?? 'No'),
        'specify_physical_illness'   => sanitize_textarea_field($_POST['specify_physical_illness'] ?? ''),
        'food_allergies'             => sanitize_text_field($_POST['food_allergies'] ?? 'No'),
        'specify_food_allergies'     => sanitize_textarea_field($_POST['specify_food_allergies'] ?? ''),
        'chronic_medication'         => sanitize_text_field($_POST['chronic_medication'] ?? 'No'),
        'specify_chronic_medication' => sanitize_textarea_field($_POST['specify_chronic_medication'] ?? ''),
        'pregnant_or_planning'       => sanitize_text_field($_POST['pregnant_or_planning'] ?? 'No'),
        'smoke'                      => sanitize_text_field($_POST['smoke'] ?? 'No'),
        'id_passport_applicant'      => '',
        'id_passport_responsible'    => '',
        'saqa_certificate'           => '',
        'study_permit'               => '',
        'parent_spouse_id'           => '',
        'latest_results'             => '',
        'proof_residence'            => '',
        'highest_grade_cert'         => '',
        'proof_medical_aid'          => '',
        'declaration'                => 1, // Admin is submitting on behalf, so declaration is implied
        'motivation_letter'          => sanitize_textarea_field($_POST['motivation_letter'] ?? ''),
        'status'                     => 'pending',
    );

    // Set temp file paths for uploaded docs
    foreach ($temp_uploaded_files as $field => $info) {
        $data[$field] = $info['unique_name'];
    }

    $wpdb->query('START TRANSACTION');
    try {
        $forms_table = $wpdb->prefix . 'nds_application_forms';
        $inserted = $wpdb->insert($forms_table, $data);
        if (!$inserted) {
            throw new Exception('Failed to insert application form data: ' . $wpdb->last_error);
        }
        $application_form_id = $wpdb->insert_id;

        $application_no = 'APP-' . date('Y') . '-' . str_pad($application_form_id, 6, '0', STR_PAD_LEFT);

        // Resolve program / faculty from course
        $course_info = $wpdb->get_row($wpdb->prepare(
            "SELECT c.program_id, p.faculty_id
             FROM {$wpdb->prefix}nds_courses c
             JOIN {$wpdb->prefix}nds_programs p ON c.program_id = p.id
             WHERE c.id = %d",
            $course_id
        ), ARRAY_A);
        $program_id = $course_info ? $course_info['program_id'] : null;

        $application_data = array(
            'application_no' => $application_no,
            'wp_user_id'     => get_current_user_id() ?: null,
            'student_id'     => null,
            'program_id'     => $program_id,
            'course_id'      => $course_id,
            'source'         => 'admin',
            'status'         => 'submitted',
            'submitted_at'   => current_time('mysql'),
            'notes'          => 'Application submitted by admin on behalf of student',
            'created_at'     => current_time('mysql'),
        );

        $app_inserted = $wpdb->insert($wpdb->prefix . 'nds_applications', $application_data);
        if (!$app_inserted) {
            throw new Exception('Failed to insert application record: ' . $wpdb->last_error);
        }
        $application_id = $wpdb->insert_id;

        // Link form to application
        $wpdb->update($forms_table, array('application_id' => $application_id), array('id' => $application_form_id));

        $wpdb->query('COMMIT');

        wp_redirect(admin_url('admin.php?page=nds-applicants&app_created=1'));
        exit;
    } catch (Exception $e) {
        $wpdb->query('ROLLBACK');
        // Clean up any uploaded temp files
        foreach ($temp_uploaded_files as $info) {
            if (file_exists($info['temp_path'])) {
                @unlink($info['temp_path']);
            }
        }
        wp_die('Error creating application: ' . esc_html($e->getMessage()));
    }
}
add_action('admin_post_nds_admin_create_application', 'nds_handle_admin_create_application');
