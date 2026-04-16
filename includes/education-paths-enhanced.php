<?php
// Prevent direct access - this file should only be included by WordPress
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Ensure WordPress functions are available
if (!function_exists('current_user_can')) {
    return;
}

// Enhanced Programs Page with Modern UI/UX
function nds_education_paths_enhanced() {
    if (!current_user_can('manage_options')) { wp_die('Unauthorized'); }

    global $wpdb;
    $table_faculties = $wpdb->prefix . 'nds_faculties';
    $table_programs = $wpdb->prefix . 'nds_programs';
    $table_courses = $wpdb->prefix . 'nds_courses';

    // Handle delete action
    if (isset($_GET['delete']) && !empty($_GET['delete'])) {
        $delete_id = intval($_GET['delete']);
        nds_delete_education_path($delete_id);
    }

    // Get data
    $paths = $wpdb->get_results("SELECT * FROM {$table_faculties} ORDER BY name", ARRAY_A);
    $total_paths = count($paths);
    
    // Detailed data for modals
    $faculties_list = $wpdb->get_results("
        SELECT p.*, f.name as parent_name 
        FROM {$table_programs} p 
        LEFT JOIN {$table_faculties} f ON p.faculty_id = f.id 
        ORDER BY p.name
    ", ARRAY_A);
    $total_programs = count($faculties_list);

    $courses_list = $wpdb->get_results("
        SELECT c.*, p.name as parent_name 
        FROM {$table_courses} c 
        LEFT JOIN {$table_programs} p ON c.program_id = p.id 
        ORDER BY c.name
    ", ARRAY_A);
    $total_courses = count($courses_list);

    // Check for success/error messages
    $success = isset($_GET['success']) ? sanitize_text_field($_GET['success']) : '';
    $error = isset($_GET['error']) ? sanitize_text_field($_GET['error']) : '';

    // Force-load Tailwind and icons for this screen to avoid admin CSS overrides
    $plugin_dir = plugin_dir_path(dirname(__FILE__));
    $css_file   = $plugin_dir . 'assets/css/frontend.css';
    if (file_exists($css_file)) {
        wp_enqueue_style(
            'nds-tailwindcss-education-paths',
            plugin_dir_url(dirname(__FILE__)) . 'assets/css/frontend.css',
            array(),
            filemtime($css_file),
            'all'
        );
        // High-specificity wrapper utilities
        wp_add_inline_style('nds-tailwindcss-education-paths', '
            .nds-tailwind-wrapper { all: initial !important; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important; }
            .nds-tailwind-wrapper * { box-sizing: border-box !important; }
            .nds-tailwind-wrapper .bg-white { background-color: #ffffff !important; }
            .nds-tailwind-wrapper .bg-gray-50 { background-color: #f9fafb !important; }
            .nds-tailwind-wrapper .text-gray-900 { color: #111827 !important; }
            .nds-tailwind-wrapper .text-gray-600 { color: #4b5563 !important; }
            .nds-tailwind-wrapper .rounded-xl { border-radius: 0.75rem !important; }
            .nds-tailwind-wrapper .rounded-2xl { border-radius: 1rem !important; }
            .nds-tailwind-wrapper .shadow-lg { box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -2px rgb(0 0 0 / 0.05) !important; }
            .nds-tailwind-wrapper .border { border-width: 1px !important; }
            .nds-tailwind-wrapper .border-gray-200 { border-color: #e5e7eb !important; }
            .nds-tailwind-wrapper .px-4 { padding-left: 1rem !important; padding-right: 1rem !important; }
            .nds-tailwind-wrapper .py-4 { padding-top: 1rem !important; padding-bottom: 1rem !important; }
            .nds-tailwind-wrapper .p-6 { padding: 1.5rem !important; }
            .nds-tailwind-wrapper .p-8 { padding: 2rem !important; }
            .nds-tailwind-wrapper .mb-6 { margin-bottom: 1.5rem !important; }
            .nds-tailwind-wrapper .mb-8 { margin-bottom: 2rem !important; }
            .nds-tailwind-wrapper .max-w-7xl { max-width: 80rem !important; }
            .nds-tailwind-wrapper .mx-auto { margin-left: auto !important; margin-right: auto !important; }
            .nds-tailwind-wrapper .grid { display: grid !important; }
            .nds-tailwind-wrapper .gap-6 { gap: 1.5rem !important; }
            .nds-tailwind-wrapper .gap-8 { gap: 2rem !important; }
            .nds-tailwind-wrapper .rounded-full { border-radius: 9999px !important; }
            .nds-tailwind-wrapper .bg-gradient-to-r { background-image: linear-gradient(to right, var(--tw-gradient-stops)) !important; }
            .nds-tailwind-wrapper .from-blue-600 { --tw-gradient-from: #2563eb !important; --tw-gradient-to: rgb(37 99 235 / 0) !important; --tw-gradient-stops: var(--tw-gradient-from), var(--tw-gradient-to) !important; }
            .nds-tailwind-wrapper .via-purple-600 { --tw-gradient-to: rgb(147 51 234 / 0) !important; --tw-gradient-stops: var(--tw-gradient-from), #9333ea, var(--tw-gradient-to) !important; }
            .nds-tailwind-wrapper .to-indigo-700 { --tw-gradient-to: #4338ca !important; }
            .nds-tailwind-wrapper .hover\:scale-105:hover { transform: scale(1.05) !important; }
            .nds-tailwind-wrapper .transition-all { transition-property: all !important; transition-duration: 300ms !important; }
        ');
    }
    wp_enqueue_style('nds-icons', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css', array(), null, 'all');

    ?>
    <div class="nds-tailwind-wrapper bg-gray-50 pb-12" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin-left: -20px; padding-left: 20px; margin-top: -20px;">
        <!-- Header - aligned with main dashboard -->
        <div class="bg-white shadow-sm border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center py-6">
                    <div class="flex items-center space-x-4">
                        <div class="w-12 h-12 bg-gradient-to-br from-blue-600 to-purple-600 rounded-xl flex items-center justify-center">
                            <i class="fas fa-graduation-cap text-white text-xl"></i>
                        </div>
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900">Programs Management</h1>
                            <p class="text-gray-600">Design and manage high-level qualification groupings across your academy.</p>
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

        <!-- Main Content -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
            <!-- Action Buttons -->
            <div class="flex items-center gap-2">
                <button id="addPathBtn" class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2 px-3 rounded-lg flex items-center justify-center gap-1.5 transition-colors duration-200 shadow-sm hover:shadow-md text-xs" style="background-color: #059669 !important; color: #ffffff !important;">
                    <i class="fas fa-book text-xs"></i>Add Program
                </button>
                <a href="<?php echo esc_url(admin_url('admin.php?page=nds-programs')); ?>"
                   class="bg-purple-600 hover:bg-purple-700 text-white font-semibold py-2 px-3 rounded-lg flex items-center justify-center gap-1.5 transition-colors duration-200 shadow-sm hover:shadow-md text-xs" style="background-color: #9333ea !important; color: #ffffff !important;">
                    <i class="fas fa-graduation-cap text-xs"></i>Manage Faculties
                </a>
            </div>

            <!-- Success/Error Messages -->
            <?php if ($success): ?>
                <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-4 flex items-center">
                    <span class="dashicons dashicons-yes-alt text-emerald-600 mr-3 text-xl"></span>
                    <div>
                        <h3 class="text-sm font-semibold text-emerald-800">Success</h3>
                        <p class="text-sm text-emerald-700"><?php echo esc_html($success); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 flex items-center">
                    <span class="dashicons dashicons-warning text-red-600 mr-3 text-xl"></span>
                    <div>
                        <h3 class="text-sm font-semibold text-red-800">Error</h3>
                        <p class="text-sm text-red-700"><?php echo esc_html($error); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Stats Cards (aligned with dashboard KPIs) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Programs (High-level) -->
                <div onclick="openStatModal('programs')" class="bg-white shadow-sm rounded-xl p-5 border border-gray-100 flex flex-col justify-between hover:bg-gray-50 transition-all duration-200 cursor-pointer group">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500 group-hover:text-gray-700">Programs</p>
                            <p class="mt-2 text-2xl font-semibold text-gray-900">
                                <?php echo number_format_i18n($total_paths); ?>
                            </p>
                        </div>
                        <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center">
                            <span class="dashicons dashicons-networking text-blue-600 text-xl"></span>
                        </div>
                    </div>
                </div>

                <!-- Faculties -->
                <div onclick="openStatModal('faculties')" class="bg-white shadow-sm rounded-xl p-5 border border-gray-100 flex flex-col justify-between hover:bg-gray-50 transition-all duration-200 cursor-pointer group">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500 group-hover:text-gray-700">Faculties</p>
                            <p class="mt-2 text-2xl font-semibold text-gray-900">
                                <?php echo number_format_i18n($total_programs); ?>
                            </p>
                        </div>
                        <div class="w-10 h-10 rounded-lg bg-emerald-50 flex items-center justify-center">
                            <span class="dashicons dashicons-admin-page text-emerald-600 text-xl"></span>
                        </div>
                    </div>
                </div>

                <!-- Courses -->
                <div onclick="openStatModal('courses')" class="bg-white shadow-sm rounded-xl p-5 border border-gray-100 flex flex-col justify-between hover:bg-gray-50 transition-all duration-200 cursor-pointer group">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500 group-hover:text-gray-700">Courses</p>
                            <p class="mt-2 text-2xl font-semibold text-gray-900">
                                <?php echo number_format_i18n($total_courses); ?>
                            </p>
                        </div>
                        <div class="w-10 h-10 rounded-lg bg-purple-50 flex items-center justify-center">
                            <span class="dashicons dashicons-welcome-learn-more text-purple-600 text-xl"></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Programs grid / empty state -->
            <?php if ($paths): 
                // PERFORMANCE FIX: Get all counts in batch queries instead of N+1 queries
                $path_ids = array_column($paths, 'id');
                if (!empty($path_ids)) {
                    $placeholders = implode(',', array_fill(0, count($path_ids), '%d'));
                    
                    // Get programs counts for all faculties at once
                    $programs_counts = $wpdb->get_results($wpdb->prepare(
                        "SELECT faculty_id, COUNT(*) as count 
                         FROM {$table_programs} 
                         WHERE faculty_id IN ($placeholders)
                         GROUP BY faculty_id",
                        ...$path_ids
                    ), ARRAY_A);
                    
                    // Get courses counts for all faculties at once
                    $courses_counts = $wpdb->get_results($wpdb->prepare(
                        "SELECT p.faculty_id, COUNT(DISTINCT c.id) as count
                         FROM {$table_courses} c
                         JOIN {$table_programs} p ON c.program_id = p.id
                         WHERE p.faculty_id IN ($placeholders)
                         GROUP BY p.faculty_id",
                        ...$path_ids
                    ), ARRAY_A);
                    
                    // Convert to associative arrays for O(1) lookup
                    $programs_lookup = array();
                    foreach ($programs_counts as $pc) {
                        $programs_lookup[$pc['faculty_id']] = (int) $pc['count'];
                    }
                    
                    $courses_lookup = array();
                    foreach ($courses_counts as $cc) {
                        $courses_lookup[$cc['faculty_id']] = (int) $cc['count'];
                    }
                } else {
                    $programs_lookup = array();
                    $courses_lookup = array();
                }
            ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <?php foreach ($paths as $path):
                        // Get counts from lookup arrays (O(1) instead of database query)
                        $programs_count = isset($programs_lookup[$path['id']]) ? $programs_lookup[$path['id']] : 0;
                        $courses_count = isset($courses_lookup[$path['id']]) ? $courses_lookup[$path['id']] : 0;
                    ?>
                        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition-shadow duration-200">
                            <!-- Path Header -->
                            <div class="px-5 py-4 border-b border-gray-100">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-10 h-10 bg-blue-50 rounded-lg flex items-center justify-center">
                                            <i class="fas fa-graduation-cap text-blue-600 text-lg"></i>
                                        </div>
                                        <div>
                                            <h3 class="text-sm font-semibold text-gray-900"><?php echo esc_html($path['name']); ?></h3>
                                            <p class="text-xs text-gray-500">Program</p>
                                        </div>
                                    </div>
                                    <div class="flex gap-2">
                                        <a href="<?php echo admin_url('admin.php?page=nds-edit-faculty&edit=' . $path['id']); ?>"
                                           class="text-gray-600 hover:text-gray-700 text-xs px-2 py-1 rounded hover:bg-gray-100 transition-colors"
                                           title="Edit Program">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                                     <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=nds_delete_faculty&faculty_id=' . $path['id']), 'nds_delete_faculty')); ?>"
                                           class="text-red-500 hover:text-red-700 text-xs px-2 py-1 rounded hover:bg-red-50 transition-colors"
                                           title="Delete Program"
                                           onclick="return confirm('Are you sure you want to delete this program?')">
                                            <i class="fas fa-times"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <!-- Path Content -->
                            <div class="p-5">
                                <?php if ($path['description']): ?>
                                    <p class="text-gray-600 text-sm mb-4 line-clamp-2"><?php echo esc_html($path['description']); ?></p>
                                <?php endif; ?>

                                <!-- Stats -->
                                <div class="grid grid-cols-2 gap-3 mb-4">
                                    <div class="text-center p-2.5 bg-gray-50 rounded-lg">
                                        <p class="text-lg font-semibold text-gray-900"><?php echo number_format_i18n($programs_count); ?></p>
                                        <p class="text-xs text-gray-500 mt-1">Faculties</p>
                                    </div>
                                    <div class="text-center p-2.5 bg-gray-50 rounded-lg">
                                        <p class="text-lg font-semibold text-gray-900"><?php echo number_format_i18n($courses_count); ?></p>
                                        <p class="text-xs text-gray-500 mt-1">Courses</p>
                                    </div>
                                </div>

                                <!-- Actions -->
                                <div class="flex gap-2">
                                    <a href="<?php echo admin_url('admin.php?page=nds-programs&faculty_id=' . $path['id']); ?>"
                                       class="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2 px-3 rounded-lg text-center text-xs flex items-center justify-center gap-1.5 transition-colors duration-200 shadow-sm hover:shadow-md" style="background-color: #059669 !important; color: #ffffff !important;">
                                        <i class="fas fa-book text-xs"></i>Faculties
                                    </a>
                                    <a href="<?php echo admin_url('admin.php?page=nds-courses&faculty_id=' . $path['id']); ?>"
                                       class="flex-1 bg-purple-600 hover:bg-purple-700 text-white font-semibold py-2 px-3 rounded-lg text-center text-xs flex items-center justify-center gap-1.5 transition-colors duration-200 shadow-sm hover:shadow-md" style="background-color: #9333ea !important; color: #ffffff !important;">
                                        <i class="fas fa-graduation-cap text-xs"></i>Courses
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <!-- Empty State -->
                <div class="bg-white shadow-sm rounded-xl border border-gray-100 p-12">
                    <div class="text-center">
                        <i class="fas fa-graduation-cap text-5xl text-gray-300 mb-4"></i>
                        <h3 class="text-sm font-medium text-gray-900 mb-1">No Programs Yet</h3>
                        <p class="text-xs text-gray-500 mb-4">Get started by creating your first program to organize your faculties and courses.</p>
                        <button id="addFirstPathBtn" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 px-5 rounded-lg text-sm flex items-center gap-2 transition-colors duration-200 shadow-sm hover:shadow-md mx-auto">
                            <i class="fas fa-plus text-sm"></i>
                            Create First Program
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Stat Card Modal (Matching Student Management Style) -->
    <div id="statModal" class="hidden" style="position:fixed; inset:0; z-index:999999; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
        <div style="position:fixed; inset:0; background:rgba(0,0,0,0.5);" onclick="closeStatModal()"></div>
        <div style="position:fixed; inset:0; display:flex; align-items:center; justify-content:center; padding:1rem;">
            <div style="background:#fff; border-radius:1rem; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25); width:100%; max-width:42rem; max-height:80vh; display:flex; flex-direction:column; position:relative;">
                <!-- Modal Header -->
                <div id="statModalHeader" style="display:flex; align-items:center; justify-content:space-between; padding:1rem 1.5rem; border-bottom:1px solid #e5e7eb; border-radius:1rem 1rem 0 0;">
                    <div style="display:flex; align-items:center; gap:0.75rem;">
                        <div id="modalIconBg" style="width:2.5rem; height:2.5rem; border-radius:0.5rem; display:flex; align-items:center; justify-content:center;">
                            <i id="modalIcon" style="font-size:1.25rem;"></i>
                        </div>
                        <div>
                            <h3 id="statModalTitle" style="font-size:1.125rem; font-weight:700; color:#111827; margin:0;"></h3>
                            <p id="statModalCount" style="font-size:0.875rem; color:#6b7280; margin:0;"></p>
                        </div>
                    </div>
                    <button onclick="closeStatModal()" style="color:#9ca3af; padding:0.5rem; border-radius:0.5rem; border:none; background:none; cursor:pointer;" onmouseover="this.style.color='#4b5563'; this.style.background='#f3f4f6'" onmouseout="this.style.color='#9ca3af'; this.style.background='none'">
                        <i class="fas fa-times" style="font-size:1.25rem;"></i>
                    </button>
                </div>
                <!-- Modal Body (Scrollable) -->
                <div style="overflow-y:auto; flex:1; padding:0.5rem;">
                    <table style="width:100%; border-collapse:collapse;">
                        <thead style="background:#f9fafb; position:sticky; top:0; z-index:10;">
                            <tr>
                                <th id="col1Header" style="padding:0.75rem 1rem; text-align:left; font-size:0.75rem; font-weight:500; color:#6b7280; text-transform:uppercase;"></th>
                                <th id="col2Header" style="padding:0.75rem 1rem; text-align:left; font-size:0.75rem; font-weight:500; color:#6b7280; text-transform:uppercase;"></th>
                            </tr>
                        </thead>
                        <tbody id="statModalBody">
                            <!-- Dynamic Content -->
                        </tbody>
                    </table>
                </div>
                <!-- Modal Footer -->
                <div style="padding:0.75rem 1.5rem; border-top:1px solid #e5e7eb; background:#f9fafb; border-radius:0 0 1rem 1rem; text-align:right;">
                    <button onclick="closeStatModal()" style="padding:0.5rem 1rem; font-size:0.875rem; font-weight:500; color:#374151; background:#fff; border:1px solid #d1d5db; border-radius:0.5rem; cursor:pointer;" onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background='#fff'">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Path Modal -->
    <div id="addPathModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden" style="display: none;">
        <div class="flex items-center justify-center min-h-screen p-4">
                    <div class="bg-white rounded-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto shadow-2xl">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-xl font-semibold text-gray-900">
                    <i class="fas fa-plus text-blue-600 mr-3"></i>Add Program
                </h3>
                <button id="closeAddPathModal" class="text-gray-400 hover:text-gray-600 text-xl">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-6">
                <form method="POST" action="<?php echo admin_url('admin-post.php'); ?>" id="addPathForm">
                    <?php wp_nonce_field('nds_add_education_path_nonce', 'nds_add_education_path_nonce'); ?>
                    <input type="hidden" name="action" value="nds_add_education_path">

                    <?php
                    // Get default color for new faculty
                    require_once plugin_dir_path(__FILE__) . 'color-palette-generator.php';
                    $color_generator = new NDS_ColorPaletteGenerator();
                    global $wpdb;
                    $faculties_table = $wpdb->prefix . 'nds_faculties';
                    $faculty_count = $wpdb->get_var("SELECT COUNT(*) FROM $faculties_table");
                    $default_color = $color_generator->get_default_faculty_color($faculty_count);
                    ?>
                    <div class="space-y-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Program Name *</label>
                            <input type="text" name="path_name" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="e.g., Full-time Qualifications" required>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                            <textarea name="path_description" rows="4" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="Describe the program's focus, faculties, and career outcomes..."></textarea>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Parent Color *</label>
                            <p class="text-sm text-gray-500 mb-3">Choose a color for this program. Faculties within this program will automatically use shades of this color.</p>
                            <div class="flex items-center gap-3">
                                <div class="relative">
                                    <input type="color" name="color_primary" id="modal_color_primary" value="<?php echo esc_attr($default_color); ?>"
                                        class="h-14 w-24 border-2 border-gray-300 rounded-lg cursor-pointer focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-sm">
                                    <div class="absolute -bottom-1 -right-1 w-6 h-6 rounded-full border-2 border-white shadow-sm" style="background-color: <?php echo esc_attr($default_color); ?>;"></div>
                                </div>
                                <div class="flex-1">
                                    <input type="text" id="modal_color_primary_text" value="<?php echo esc_attr($default_color); ?>"
                                        class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent font-mono text-sm"
                                        placeholder="#E53935" pattern="^#[0-9A-Fa-f]{6}$">
                                    <p class="text-xs text-gray-400 mt-1">Hex color code</p>
                                </div>
                            </div>
                            <div class="mt-3 p-3 bg-gray-50 rounded-lg border border-gray-200">
                                <p class="text-xs text-gray-600 mb-2"><strong>Selected Color:</strong></p>
                                <div class="flex items-center gap-2">
                                    <div class="w-12 h-12 rounded-lg shadow-sm border border-gray-300" id="modal_color_preview" style="background-color: <?php echo esc_attr($default_color); ?>;"></div>
                                    <div>
                                        <p class="text-sm font-semibold text-gray-900" id="modal_color_display"><?php echo esc_html($default_color); ?></p>
                                        <p class="text-xs text-gray-500">This color will be used as the base for all faculties in this program</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-3 pt-6 border-t border-gray-100">
                        <button type="button" id="cancelAddPath" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-2 px-5 rounded-lg text-sm transition-colors duration-200">Cancel</button>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-5 rounded-lg text-sm flex items-center gap-2 transition-colors duration-200 shadow-sm hover:shadow-md">
                            <i class="fas fa-save text-sm"></i>Add Program
                        </button>
                    </div>
                </form>
            </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Color picker sync functionality
        const modalColorPicker = document.getElementById('modal_color_primary');
        const modalColorText = document.getElementById('modal_color_primary_text');
        const modalColorPreview = document.getElementById('modal_color_preview');
        const modalColorDisplay = document.getElementById('modal_color_display');
        
        if (modalColorPicker && modalColorText && modalColorPreview && modalColorDisplay) {
            // Sync color picker to text input and preview
            modalColorPicker.addEventListener('input', function(e) {
                const color = e.target.value.toUpperCase();
                modalColorText.value = color;
                modalColorPreview.style.backgroundColor = color;
                modalColorDisplay.textContent = color;
            });
            
            // Sync text input to color picker and preview
            modalColorText.addEventListener('input', function(e) {
                const value = e.target.value;
                if (/^#[0-9A-Fa-f]{6}$/.test(value)) {
                    modalColorPicker.value = value;
                    modalColorPreview.style.backgroundColor = value;
                    modalColorDisplay.textContent = value.toUpperCase();
                }
            });
            
            // Update preview when text input loses focus (to show validation)
            modalColorText.addEventListener('blur', function(e) {
                const value = e.target.value;
                if (!/^#[0-9A-Fa-f]{6}$/.test(value) && value.trim() !== '') {
                    // Invalid color, reset to picker value
                    modalColorText.value = modalColorPicker.value.toUpperCase();
                }
            });
        }
        
        // Modal functionality
        const modal = document.getElementById('addPathModal');
        const openBtns = [document.getElementById('addPathBtn'), document.getElementById('addFirstPathBtn')];
        const closeBtn = document.getElementById('closeAddPathModal');
        const cancelBtn = document.getElementById('cancelAddPath');

        function openAddPathModal() {
            if (!modal) return;

            // Ensure modal overlay is attached directly to <body> so it centers over the full viewport
            if (modal.parentElement !== document.body) {
                document.body.appendChild(modal);
            }

            modal.classList.remove('hidden');
            modal.classList.add('flex', 'items-center', 'justify-center');
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeAddPathModal() {
            if (!modal) return;
            modal.classList.add('hidden');
            modal.classList.remove('flex', 'items-center', 'justify-center');
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }

        openBtns.forEach(btn => {
            if (btn) {
                btn.addEventListener('click', openAddPathModal);
            }
        });

        [closeBtn, cancelBtn].forEach(btn => {
            if (btn) {
                btn.addEventListener('click', closeAddPathModal);
            }
        });

        if (modal) {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                    closeAddPathModal();
            }
        });
        }
    });

    // Stats Modal Logic
    const statsData = {
        programs: <?php echo json_encode($paths); ?>,
        faculties: <?php echo json_encode($faculties_list); ?>,
        courses: <?php echo json_encode($courses_list); ?>
    };

    const modalConfig = {
        programs:  { title: 'All Programs',  icon: 'fas fa-graduation-cap', iconBg: '#eff6ff', iconColor: '#3b82f6', col1: 'Program Name', col2: 'Description' },
        faculties: { title: 'All Faculties', icon: 'dashicons dashicons-admin-page',  iconBg: '#ecfdf5', iconColor: '#10b981', col1: 'Faculty Name', col2: 'Parent Program' },
        courses:   { title: 'All Courses',   icon: 'dashicons dashicons-welcome-learn-more', iconBg: '#f5f3ff', iconColor: '#8b5cf6', col1: 'Course Name',  col2: 'Parent Faculty' }
    };

    function openStatModal(type) {
        const modal = document.getElementById('statModal');
        const config = modalConfig[type];
        const data = statsData[type];
        
        if (!modal || !config || !data) return;

        // Set Text & Icons
        document.getElementById('statModalTitle').textContent = config.title;
        document.getElementById('statModalCount').textContent = data.length + ' item' + (data.length !== 1 ? 's' : '');
        document.getElementById('col1Header').textContent = config.col1;
        document.getElementById('col2Header').textContent = config.col2;
        
        const modalIcon = document.getElementById('modalIcon');
        const modalIconBg = document.getElementById('modalIconBg');
        
        // Handle FontAwesome vs Dashicons
        if (config.icon.startsWith('dashicons')) {
            modalIcon.className = 'dashicons ' + config.icon.replace('dashicons ', '');
        } else {
            modalIcon.className = config.icon;
        }
        
        modalIcon.style.color = config.iconColor;
        modalIconBg.style.backgroundColor = config.iconBg;

        // Populate Table
        const tbody = document.getElementById('statModalBody');
        tbody.innerHTML = '';
        
        if (data && data.length > 0) {
            data.forEach(item => {
                const row = document.createElement('tr');
                row.style.cssText = 'border-bottom:1px solid #f3f4f6; transition: background 0.15s;';
                row.onmouseover = function() { this.style.background = '#f9fafb'; };
                row.onmouseout = function() { this.style.background = ''; };
                
                let name = item.name || 'N/A';
                let detail = '';
                let iconClass = 'fas fa-folder';
                let iconColor = '#10b981';

                if (type === 'programs') {
                    detail = item.description || 'No description';
                    iconClass = 'fas fa-graduation-cap';
                    iconColor = '#3b82f6';
                } else if (type === 'faculties') {
                    detail = item.parent_name || '—';
                    iconClass = 'dashicons dashicons-admin-page';
                    iconColor = '#10b981';
                } else if (type === 'courses') {
                    detail = item.parent_name || '—';
                    iconClass = 'dashicons dashicons-welcome-learn-more';
                    iconColor = '#8b5cf6';
                }

                row.innerHTML = `
                    <td style="padding:0.75rem 1rem;">
                        <div style="display:flex; align-items:center;">
                            <div style="width:2rem; height:2rem; background:#f0fdf4; border-radius:50%; display:flex; align-items:center; justify-content:center; margin-right:0.75rem;">
                                <i class="${iconClass}" style="color:${iconColor}; font-size:0.75rem;"></i>
                            </div>
                            <div>
                                <div style="font-size:0.875rem; font-weight:600; color:#111827;">${name}</div>
                            </div>
                        </div>
                    </td>
                    <td style="padding:0.75rem 1rem; font-size:0.875rem; color:#6b7280; font-weight:400;">${detail}</td>
                `;
                tbody.appendChild(row);
            });
        } else {
            const row = document.createElement('tr');
            row.innerHTML = `<td colspan="2" style="padding:2rem 1rem; text-align:center; color:#9ca3af; font-style:italic;">No items found</td>`;
            tbody.appendChild(row);
        }

        // Show Modal
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeStatModal() {
        const modal = document.getElementById('statModal');
        if (modal) {
            modal.classList.add('hidden');
            document.body.style.overflow = '';
        }
    }

    // Close on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeStatModal();
            closeAddPathModal();
        }
    });
    </script>

    <style>
    .custom-scrollbar::-webkit-scrollbar {
        width: 6px;
    }
    .custom-scrollbar::-webkit-scrollbar-track {
        background: #f9fafb;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: #e5e7eb;
        border-radius: 10px;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
        background: #d1d5db;
    }
    @media print {
        .bg-gray-50 { background: white !important; }
        .bg-white { background: white !important; }
        .shadow-sm, .shadow-md { box-shadow: none !important; }
        .border { border: 1px solid #e5e7eb !important; }
        .bg-blue-600, .bg-green-600, .bg-purple-600 { display: none !important; }
        .hover\:bg-blue-700, .hover\:bg-green-700, .hover\:bg-purple-700 { display: none !important; }
    }
    </style>
    <?php
}
