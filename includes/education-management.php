<?php
// Prevent direct access - this file should only be included by WordPress
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Ensure WordPress functions are available
if (!function_exists('current_user_can')) {
    return;
}

// Faculties Management Functions (formerly Education Management)
function nds_education_management_page()
{
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    global $wpdb;
    $table_paths = $wpdb->prefix . 'nds_faculties';
    $table_programs = $wpdb->prefix . 'nds_programs';
    $table_courses = $wpdb->prefix . 'nds_courses';

    // Handle delete action via GET
    if (isset($_GET['delete']) && !empty($_GET['delete'])) {
        $delete_id = intval($_GET['delete']);
        nds_delete_education_path($delete_id);
    }

    // Get current action
    $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
    $edit_id = isset($_GET['edit']) ? intval($_GET['edit']) : 0;

?>
    <div class="nds-tailwind-wrapper bg-gray-50 pb-12" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin-left: -20px; padding-left: 20px; margin-top: -20px;">
        <?php if ($action === 'list'): ?>
            <!-- Header -->
            <div class="bg-white shadow-sm border-b border-gray-200">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between items-center py-6">
                        <div class="flex items-center space-x-4">
                            <div class="w-12 h-12 bg-gradient-to-br from-blue-600 to-purple-600 rounded-xl flex items-center justify-center">
                                <i class="fas fa-graduation-cap text-white text-xl"></i>
                            </div>
                            <div>
                                <h1 class="text-3xl font-bold text-gray-900">Faculties Management</h1>
                                <p class="text-gray-600">Manage faculties, programs, and courses</p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-3">
                            <div class="text-right mr-4">
                                <p class="text-xs uppercase tracking-wide text-gray-500">Last updated</p>
                                <p class="text-sm font-medium text-gray-900"><?php echo esc_html(date_i18n('M j, Y \a\t g:i A')); ?></p>
                            </div>
                            <a href="<?php echo admin_url('admin.php?page=nds-faculties&action=add'); ?>"
                                class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 px-5 rounded-lg flex items-center gap-2 transition-colors duration-200 shadow-sm hover:shadow-md">
                                <i class="fas fa-plus text-sm"></i>
                                Add Faculty
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main content -->
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
                <!-- Faculties Table -->
                <div class="bg-white shadow-sm rounded-xl border border-gray-100 overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-100">
                        <h2 class="text-sm font-semibold text-gray-900">Faculties</h2>
                        <p class="text-xs text-gray-500 mt-1">All available faculties and their details</p>
                    </div>

                    <?php
                    $paths = $wpdb->get_results("SELECT * FROM {$table_paths} ORDER BY name", ARRAY_A);
                    if ($paths): ?>
                        <div class="overflow-x-auto">
                            <table class="w-full divide-y divide-gray-100">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Programs</th>
                                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Courses</th>
                                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-100">
                                    <?php foreach ($paths as $path):
                                        $programs_count = (int) $wpdb->get_var($wpdb->prepare(
                                            "SELECT COUNT(*) FROM {$table_programs} WHERE faculty_id = %d",
                                            $path['id']
                                        ));
                                        $courses_count = (int) $wpdb->get_var($wpdb->prepare(
                                            "SELECT COUNT(*) FROM {$table_courses} c
                                         JOIN {$table_programs} p ON c.program_id = p.id
                                         WHERE p.faculty_id = %d",
                                            $path['id']
                                        ));
                                    ?>
                                        <tr class="hover:bg-gray-50 transition-colors">
                                            <td class="px-5 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <div class="w-8 h-8 bg-blue-50 rounded-lg flex items-center justify-center mr-3">
                                                        <i class="fas fa-graduation-cap text-blue-600 text-sm"></i>
                                                    </div>
                                                    <div class="text-sm font-medium text-gray-900"><?php echo esc_html($path['name']); ?></div>
                                                </div>
                                            </td>
                                            <td class="px-5 py-4">
                                                <div class="text-sm text-gray-600 max-w-xs truncate">
                                                    <?php echo esc_html($path['description'] ?: 'No description'); ?>
                                                </div>
                                            </td>
                                            <td class="px-5 py-4 whitespace-nowrap">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700">
                                                    <?php echo number_format_i18n($programs_count); ?>
                                                </span>
                                            </td>
                                            <td class="px-5 py-4 whitespace-nowrap">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-purple-50 text-purple-700">
                                                    <?php echo number_format_i18n($courses_count); ?>
                                                </span>
                                            </td>
                                            <td class="px-5 py-4 whitespace-nowrap text-sm font-medium">
                                                <div class="flex gap-2">
                                                    <!-- View -->
                                                    <a href="<?php echo admin_url('admin.php?page=nds-faculties&action=edit&edit=' . $path['id']); ?>"
                                                        class="text-blue-600 hover:text-blue-700 text-xs"
                                                        title="View">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <!-- Edit -->
                                                    <a href="<?php echo admin_url('admin.php?page=nds-faculties&action=edit-path&edit=' . $path['id']); ?>"
                                                        class="text-gray-600 hover:text-gray-700 text-xs"
                                                        title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <!-- Delete -->
                                                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=nds_delete_faculty&faculty_id=' . $path['id']), 'nds_delete_faculty')); ?>"
                                                        class="text-red-500 hover:text-red-700 text-xs"
                                                        title="Delete" onclick="return confirm('Are you sure you want to delete this faculty?')">
                                                        <i class="fas fa-times"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="px-5 py-12 text-center">
                            <i class="fas fa-graduation-cap text-4xl text-gray-300 mb-3"></i>
                            <h3 class="text-sm font-medium text-gray-900 mb-1">No Faculties Yet</h3>
                            <p class="text-xs text-gray-500 mb-4">Get started by creating your first faculty.</p>
                            <a href="<?php echo admin_url('admin.php?page=nds-faculties&action=add'); ?>"
                                class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-5 rounded-lg text-sm flex items-center gap-2 transition-colors duration-200 shadow-sm hover:shadow-md mx-auto w-fit">
                                <i class="fas fa-plus text-sm"></i>
                                Create First Faculty
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($action === 'edit'): ?>
            <!-- Show Programs for Faculty -->
            <?php
            $path_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_paths} WHERE id = %d", $edit_id), ARRAY_A);
            if (!$path_data) {
                echo '<div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-4">Faculty not found.</div>';
                return;
            }

            // Get programs for this faculty
            $programs = $wpdb->get_results($wpdb->prepare("
                SELECT p.*, COUNT(c.id) as course_count
                FROM {$table_programs} p
                LEFT JOIN {$table_courses} c ON p.id = c.program_id
                WHERE p.faculty_id = %d
                GROUP BY p.id
                ORDER BY p.name
            ", $edit_id), ARRAY_A);
            ?>

            <!-- Header -->
            <div class="bg-white shadow-sm border-b border-gray-200">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between items-center py-6">
                        <div class="flex items-center space-x-4">
                            <div class="w-12 h-12 bg-gradient-to-br from-blue-600 to-purple-600 rounded-xl flex items-center justify-center">
                                <i class="fas fa-graduation-cap text-white text-xl"></i>
                            </div>
                            <div>
                                <h1 class="text-3xl font-bold text-gray-900"><?php echo esc_html($path_data['name']); ?></h1>
                                <p class="text-gray-600">Programs under this faculty</p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-3">
                            <div class="text-right mr-4">
                                <p class="text-xs uppercase tracking-wide text-gray-500">Last updated</p>
                                <p class="text-sm font-medium text-gray-900"><?php echo esc_html(date_i18n('M j, Y \a\t g:i A')); ?></p>
                            </div>
                            <a href="<?php echo admin_url('admin.php?page=nds-programs&faculty_id=' . $edit_id . '#addProgramModal'); ?>"
                                class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2.5 px-5 rounded-lg flex items-center gap-2 transition-colors duration-200 shadow-sm hover:shadow-md">
                                <i class="fas fa-plus text-sm"></i>
                                Add Program
                            </a>
                            <a href="<?php echo admin_url('admin.php?page=nds-faculties'); ?>"
                                class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-2.5 px-5 rounded-lg flex items-center gap-2 transition-colors duration-200 shadow-sm hover:shadow">
                                <i class="fas fa-arrow-left text-sm"></i>
                                Back
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main content -->
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="lg:col-span-1">
                        <?php
                        require_once plugin_dir_path(__FILE__) . 'partials/education-path-form.php';
                        nds_render_education_path_form(array(
                            'mode' => $action,
                            'path_data' => $path_data,
                            'edit_id' => $edit_id,
                        ));
                        ?>
                    </div>
                    <div class="lg:col-span-2">
                        <!-- Programs List -->
                        <div class="bg-white shadow-sm rounded-xl border border-gray-100 overflow-hidden">
                            <div class="px-5 py-4 border-b border-gray-100">
                                <h2 class="text-sm font-semibold text-gray-900">Programs</h2>
                                <p class="text-xs text-gray-500 mt-1"><?php echo number_format_i18n(count($programs)); ?> program<?php echo count($programs) !== 1 ? 's' : ''; ?> in this faculty</p>
                            </div>

                            <?php if ($programs): ?>
                                <div class="divide-y divide-gray-100">
                                    <?php foreach ($programs as $program): ?>
                                        <div class="px-5 py-4 hover:bg-gray-50 transition-colors">
                                            <div class="flex items-start justify-between">
                                                <div class="flex-1 min-w-0">
                                                    <div class="flex items-center space-x-2 mb-2">
                                                        <h3 class="text-sm font-semibold text-gray-900"><?php echo esc_html($program['name']); ?></h3>
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700">
                                                            Active
                                                        </span>
                                                    </div>

                                                    <?php if (!empty($program['description'])): ?>
                                                        <p class="text-xs text-gray-600 mb-2 line-clamp-2"><?php echo esc_html($program['description']); ?></p>
                                                    <?php endif; ?>

                                                    <div class="flex items-center space-x-4 text-xs text-gray-500">
                                                        <div class="flex items-center">
                                                            <i class="fas fa-book mr-1.5 text-gray-400"></i>
                                                            <?php echo number_format_i18n(intval($program['course_count'])); ?> Courses
                                                        </div>
                                                        <div class="flex items-center">
                                                            <i class="fas fa-clock mr-1.5 text-gray-400"></i>
                                                            <?php echo esc_html($program['duration_months'] ?? '12'); ?> months
                                                        </div>
                                                        <div class="flex items-center">
                                                            <i class="fas fa-graduation-cap mr-1.5 text-gray-400"></i>
                                                            <?php $ptype = $program['program_type'] ?? 'diploma';
                                                            echo esc_html(ucfirst($ptype)); ?>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="flex items-center space-x-1.5 ml-4">
                                                    <a href="<?php echo admin_url('admin.php?page=nds-edit-program&edit_program=' . $program['id']); ?>"
                                                        class="text-gray-600 hover:text-gray-700 text-xs px-2 py-1 rounded hover:bg-gray-100 transition-colors"
                                                        title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="<?php echo admin_url('admin.php?page=nds-courses&program_id=' . $program['id']); ?>"
                                                        class="text-blue-600 hover:text-blue-700 text-xs px-2 py-1 rounded hover:bg-blue-50 transition-colors"
                                                        title="Courses">
                                                        <i class="fas fa-book"></i>
                                                    </a>
                                                    <button type="button" onclick="confirmDelete(<?php echo $program['id']; ?>, '<?php echo esc_js($program['name']); ?>')"
                                                        class="text-red-500 hover:text-red-700 text-xs px-2 py-1 rounded hover:bg-red-50 transition-colors"
                                                        title="Delete">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="px-5 py-12 text-center">
                                    <i class="fas fa-graduation-cap text-4xl text-gray-300 mb-3"></i>
                                    <h3 class="text-sm font-medium text-gray-900 mb-1">No Programs Yet</h3>
                                    <p class="text-xs text-gray-500 mb-4">Get started by creating your first program for this faculty.</p>
                                    <a href="<?php echo admin_url('admin.php?page=nds-programs&faculty_id=' . $edit_id . '#addProgramModal'); ?>"
                                        class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-5 rounded-lg text-sm flex items-center gap-2 transition-colors duration-200 shadow-sm hover:shadow-md mx-auto w-fit">
                                        <i class="fas fa-plus text-sm"></i>
                                        Create First Program
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>


                    <!-- Hidden Delete Form -->
                    <form id="deleteProgramForm" method="POST" action="<?php echo admin_url('admin-post.php'); ?>" style="display: none;">
                        <input type="hidden" name="action" value="nds_delete_program">
                        <input type="hidden" name="program_id" id="delete_program_id">
                        <?php wp_nonce_field('nds_delete_program_nonce', 'nds_delete_program_nonce'); ?>
                    </form>

                    <script>
                        function confirmDelete(programId, programName) {
                            if (confirm(`Are you sure you want to delete "${programName}"? This will also remove all associated courses.`)) {
                                document.getElementById('delete_program_id').value = programId;
                                document.getElementById('deleteProgramForm').submit();
                            }
                        }
                    </script>

        <?php elseif ($action === 'add' || $action === 'edit-path'): ?>
            <!-- Add/Edit Faculty Form -->
            <!-- Header -->
            <div class="bg-white shadow-sm border-b border-gray-200">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between items-center py-6">
                        <div class="flex items-center space-x-4">
                            <div class="w-12 h-12 bg-gradient-to-br from-blue-600 to-purple-600 rounded-xl flex items-center justify-center">
                                <i class="fas fa-<?php echo $action === 'edit-path' ? 'edit' : 'plus'; ?> text-white text-xl"></i>
                            </div>
                            <div>
                                <h1 class="text-3xl font-bold text-gray-900"><?php echo $action === 'edit-path' ? 'Edit' : 'Add'; ?> Faculty</h1>
                                <p class="text-gray-600"><?php echo $action === 'edit-path' ? 'Update faculty details' : 'Create a new faculty'; ?></p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-3">
                            <div class="text-right mr-4">
                                <p class="text-xs uppercase tracking-wide text-gray-500">Last updated</p>
                                <p class="text-sm font-medium text-gray-900"><?php echo esc_html(date_i18n('M j, Y \a\t g:i A')); ?></p>
                            </div>
                            <a href="<?php echo admin_url('admin.php?page=nds-faculties'); ?>"
                                class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-2.5 px-5 rounded-lg flex items-center gap-2 transition-colors duration-200 shadow-sm hover:shadow">
                                <i class="fas fa-arrow-left text-sm"></i>
                                Back
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main content -->
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

                    <?php
                    $path_data = null;
                    if ($action === 'edit-path' && $edit_id) {
                        $path_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_paths} WHERE id = %d", $edit_id), ARRAY_A);
                        if (!$path_data) {
                            echo '<div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-4">Faculty not found.</div>';
                            return;
                        }
                    }
                    require_once plugin_dir_path(__FILE__) . 'partials/education-path-form.php';
                    nds_render_education_path_form(array(
                        'mode' => $action,
                        'path_data' => $path_data,
                        'edit_id' => $edit_id,
                    ));
                    ?>
            </div>
        <?php endif; ?>
    </div>
<?php
}
