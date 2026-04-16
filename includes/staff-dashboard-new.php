<?php
// Prevent direct access - this file should only be included by WordPress
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Ensure WordPress functions are available
if (!function_exists('current_user_can')) {
    return;
}

// Include components
require_once plugin_dir_path(__FILE__) . 'components/staff-roles-management.php';

// Superior Staff Dashboard with Integrated Add Staff Modal
function nds_staff_dashboard_improved() {
    if (!current_user_can('manage_options')) { wp_die('Unauthorized'); }
    global $wpdb;
    $staff_table = $wpdb->prefix . 'nds_staff';
    $course_table = $wpdb->prefix . 'nds_courses';
    $link_table = $wpdb->prefix . 'nds_course_lecturers';

    // Data
    $staff = $wpdb->get_results("SELECT * FROM {$staff_table} ORDER BY first_name, last_name", ARRAY_A);
    $courses = $wpdb->get_results("SELECT id, name FROM {$course_table} ORDER BY name", ARRAY_A);
    $faculties = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}nds_faculties WHERE status = 'active' ORDER BY name", ARRAY_A);
    $programs = $wpdb->get_results("SELECT id, name, faculty_id FROM {$wpdb->prefix}nds_programs WHERE status = 'active' ORDER BY name", ARRAY_A);
    $qualifications = $wpdb->get_results("SELECT id, name, program_id FROM {$wpdb->prefix}nds_courses WHERE status = 'active' ORDER BY name", ARRAY_A);

    // Stats
    $total_staff = count($staff);
    $lecturers_list = array_values(array_filter($staff, function($s) { return strtolower($s['role']) === 'lecturer'; }));
    $lecturers = count($lecturers_list);
    
    $admins_list = array_values(array_filter($staff, function($s) { return strtolower($s['role']) !== 'lecturer'; }));
    
    // Get full assignments list for modal
    $all_assignments = $wpdb->get_results("
        SELECT l.*, s.first_name, s.last_name, c.name as course_name 
        FROM {$link_table} l 
        JOIN {$staff_table} s ON l.lecturer_id = s.id 
        JOIN {$course_table} c ON l.course_id = c.id 
        ORDER BY l.assigned_date DESC
    ", ARRAY_A);
    $total_assignments = count($all_assignments);
    ?>
    <div class="nds-tailwind-wrapper bg-gray-50 pb-12" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin-left: -20px; padding-left: 20px; margin-top: -20px;">
        <!-- Header -->
        <div class="bg-white shadow-sm border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center py-6">
                    <div class="flex items-center space-x-4">
                        <div class="w-12 h-12 bg-gradient-to-br from-blue-600 to-purple-600 rounded-xl flex items-center justify-center">
                            <i class="fas fa-users text-white text-xl"></i>
                        </div>
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900">Staff Management</h1>
                            <p class="text-gray-600">Manage staff members, assign lecturers to courses, and track assignments</p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-3">
                        <div class="text-right mr-4">
                            <p class="text-xs uppercase tracking-wide text-gray-500">Last updated</p>
                            <p class="text-sm font-medium text-gray-900"><?php echo esc_html(date_i18n('M j, Y \a\t g:i A')); ?></p>
                        </div>
                        <button id="addStaffBtn" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 px-5 rounded-lg flex items-center gap-2 transition-colors duration-200 shadow-sm hover:shadow-md">
                            <i class="fas fa-plus text-sm"></i>
                            Add Lecturer
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main content -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
            <!-- KPI cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Total Staff -->
                <div onclick="openStatModal('total_staff')" class="bg-white shadow-sm rounded-xl p-5 border border-gray-100 flex flex-col justify-between hover:bg-gray-50 transition-all duration-200 cursor-pointer group">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500 group-hover:text-gray-700">Total Staff</p>
                            <p class="mt-2 text-2xl font-semibold text-gray-900">
                                <?php echo number_format_i18n($total_staff); ?>
                            </p>
                        </div>
                        <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center">
                            <i class="fas fa-users text-blue-600 text-xl"></i>
                        </div>
                    </div>
                    <p class="mt-3 text-xs text-gray-500">
                        Lecturers: <span class="font-medium text-gray-800"><?php echo number_format_i18n($lecturers); ?></span>
                        (<?php echo $total_staff > 0 ? round(($lecturers / $total_staff) * 100) : 0; ?>% of staff)
                    </p>
                </div>

                <!-- Lecturers -->
                <div onclick="openStatModal('lecturers')" class="bg-white shadow-sm rounded-xl p-5 border border-gray-100 flex flex-col justify-between hover:bg-gray-50 transition-all duration-200 cursor-pointer group">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500 group-hover:text-gray-700">Lecturers</p>
                            <p class="mt-2 text-2xl font-semibold text-gray-900">
                                <?php echo number_format_i18n($lecturers); ?>
                            </p>
                        </div>
                        <div class="w-10 h-10 rounded-lg bg-emerald-50 flex items-center justify-center">
                            <i class="fas fa-chalkboard-teacher text-emerald-600 text-xl"></i>
                        </div>
                    </div>
                    <p class="mt-3 text-xs text-gray-500">
                        Active: <span class="font-medium text-gray-800"><?php echo number_format_i18n($lecturers); ?></span>
                        teaching staff members
                    </p>
                </div>

                <!-- Assignments -->
                <div onclick="openStatModal('assignments')" class="bg-white shadow-sm rounded-xl p-5 border border-gray-100 flex flex-col justify-between hover:bg-gray-50 transition-all duration-200 cursor-pointer group">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500 group-hover:text-gray-700">Assignments</p>
                            <p class="mt-2 text-2xl font-semibold text-gray-900">
                                <?php echo number_format_i18n($total_assignments); ?>
                            </p>
                        </div>
                        <div class="w-10 h-10 rounded-lg bg-purple-50 flex items-center justify-center">
                            <i class="fas fa-link text-purple-600 text-xl"></i>
                        </div>
                    </div>
                    <p class="mt-3 text-xs text-gray-500">
                        Course assignments: <span class="font-medium text-gray-800"><?php echo number_format_i18n($total_assignments); ?></span>
                        active links
                    </p>
                </div>

                <!-- Admins -->
                <div onclick="openStatModal('admins')" class="bg-white shadow-sm rounded-xl p-5 border border-gray-100 flex flex-col justify-between hover:bg-gray-50 transition-all duration-200 cursor-pointer group">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500 group-hover:text-gray-700">Admins</p>
                            <p class="mt-2 text-2xl font-semibold text-gray-900">
                                <?php echo number_format_i18n($total_staff - $lecturers); ?>
                            </p>
                        </div>
                        <div class="w-10 h-10 rounded-lg bg-orange-50 flex items-center justify-center">
                            <i class="fas fa-user-cog text-orange-600 text-xl"></i>
                        </div>
                    </div>
                    <p class="mt-3 text-xs text-gray-500">
                        Admin & Support: <span class="font-medium text-gray-800"><?php echo number_format_i18n($total_staff - $lecturers); ?></span>
                        staff members
                    </p>
                </div>
            </div>

            <!-- Activity & quick links -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Staff Members -->
                <div class="lg:col-span-2 bg-white shadow-sm rounded-xl border border-gray-100">
                    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                        <div>
                            <h2 class="text-sm font-semibold text-gray-900">Staff Members</h2>
                            <p class="text-xs text-gray-500">All staff members in the system.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="text" id="staffSearch" placeholder="Search staff..." 
                                   class="px-3 py-1.5 border border-gray-300 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <i class="fas fa-search text-gray-400 text-xs"></i>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <?php if ($staff): ?>
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-5 py-2 text-left font-medium text-gray-500">Staff</th>
                                        <th class="px-5 py-2 text-left font-medium text-gray-500">Email</th>
                                        <th class="px-5 py-2 text-left font-medium text-gray-500">Role</th>
                                        <th class="px-5 py-2 text-left font-medium text-gray-500">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 bg-white" id="staffList">
                                    <?php foreach ($staff as $member): ?>
                                        <tr>
                                            <td class="px-5 py-2 whitespace-nowrap">
                                                <div class="flex items-center space-x-3">
                                                    <div class="w-8 h-8 rounded-full overflow-hidden border border-gray-200">
                                                        <?php if ($member['profile_picture']): ?>
                                                            <img src="<?php echo esc_url(wp_get_attachment_url($member['profile_picture'])); ?>" alt="Profile" class="w-full h-full object-cover">
                                                        <?php else: ?>
                                                            <div class="w-full h-full bg-gray-200 flex items-center justify-center">
                                                                <i class="fas fa-user text-gray-400 text-xs"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div>
                                                        <div class="font-medium text-gray-900">
                                                            <?php echo esc_html($member['first_name'] . ' ' . $member['last_name']); ?>
                                                        </div>
                                                        <?php if ($member['gender']): ?>
                                                            <div class="text-xs text-gray-500 flex items-center">
                                                                <i class="fas fa-<?php echo $member['gender'] === 'Male' ? 'mars' : 'venus'; ?> mr-1"></i>
                                                                <?php echo esc_html($member['gender']); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-5 py-2 whitespace-nowrap text-gray-700">
                                                <?php echo esc_html($member['email']); ?>
                                            </td>
                                            <td class="px-5 py-2 whitespace-nowrap">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?php
                                                    echo match(strtolower($member['role'])) {
                                                        'lecturer' => 'bg-emerald-50 text-emerald-700',
                                                        'admin' => 'bg-blue-50 text-blue-700',
                                                        'support' => 'bg-purple-50 text-purple-700',
                                                        default => 'bg-gray-50 text-gray-700'
                                                    };
                                                ?>">
                                                    <?php echo esc_html($member['role']); ?>
                                                </span>
                                            </td>
                                            <td class="px-5 py-2 whitespace-nowrap">
                                                <div class="flex items-center gap-2">
                                                    <a href="<?php echo admin_url('admin.php?page=nds-edit-staff&staff_id=' . $member['id']); ?>"
                                                       class="text-indigo-600 hover:text-indigo-700 text-xs font-medium">
                                                        Edit
                                                    </a>
                                                    <span class="text-gray-300">|</span>
                                                    <form method="POST" action="<?php echo admin_url('admin-post.php'); ?>" class="inline"
                                                          onsubmit="return confirm('Are you sure you want to delete this staff member?')">
                                                        <?php wp_nonce_field('nds_delete_staff', '_wpnonce'); ?>
                                                        <input type="hidden" name="action" value="nds_delete_staff">
                                                        <input type="hidden" name="staff_id" value="<?php echo intval($member['id']); ?>">
                                                        <button type="submit" class="text-red-600 hover:text-red-700 text-xs font-medium">
                                                            Delete
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="text-center py-12">
                                <i class="fas fa-users text-4xl text-gray-300 mb-3"></i>
                                <h3 class="text-sm font-medium text-gray-900 mb-1">No Staff Members Yet</h3>
                                <p class="text-xs text-gray-500 mb-4">Get started by adding your first staff member.</p>
                                <button id="addFirstStaffBtn" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg text-xs transition-colors duration-200 flex items-center gap-2 mx-auto">
                                    <i class="fas fa-plus text-xs"></i>Add First Lecturer
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Sidebar Section -->
                <div class="space-y-6">
                    <!-- Staff Roles Management Component -->
                    <?php nds_render_staff_roles_component(); ?>
                    
                    <!-- Quick Assignment Card -->
                    <div class="bg-white shadow-sm rounded-xl border border-gray-100">
                        <div class="px-5 py-4 border-b border-gray-100">
                            <h3 class="text-sm font-semibold text-gray-900">Quick Assignment</h3>
                            <p class="text-xs text-gray-500 mt-1">Assign lecturers to courses</p>
                        </div>
                        <div class="p-5">
                            <form method="POST" action="<?php echo admin_url('admin-post.php'); ?>" id="quickAssignForm">
                                <?php wp_nonce_field('nds_assign_lecturer'); ?>
                                <input type="hidden" name="action" value="nds_assign_lecturer">

                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 mb-1.5">Select Lecturer</label>
                                        <select name="lecturer_id" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                                            <option value="">Choose a lecturer...</option>
                                            <?php foreach ($staff as $s): ?>
                                                <option value="<?php echo intval($s['id']); ?>">
                                                    <?php echo esc_html($s['first_name'] . ' ' . $s['last_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 mb-1.5">Select Course</label>
                                        <select name="course_id" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                                            <option value="">Choose a course...</option>
                                            <?php foreach ($courses as $c): ?>
                                                <option value="<?php echo intval($c['id']); ?>">
                                                    <?php echo esc_html($c['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2 px-4 rounded-lg text-sm transition-colors duration-200 flex items-center justify-center gap-2 shadow-sm hover:shadow">
                                        <i class="fas fa-link text-xs"></i>Assign Lecturer
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Current Assignments Card -->
                    <div class="bg-white shadow-sm rounded-xl border border-gray-100">
                        <div class="px-5 py-4 border-b border-gray-100">
                            <h3 class="text-sm font-semibold text-gray-900">Current Assignments</h3>
                            <p class="text-xs text-gray-500 mt-1">Recent lecturer-course links</p>
                        </div>
                        <div class="p-5">
                            <?php
                            $assignments = $wpdb->get_results("SELECT l.id, s.first_name, s.last_name, c.name AS course_name, l.assigned_date, s.id as staff_id, c.id as course_id
                                FROM {$link_table} l
                                JOIN {$staff_table} s ON s.id = l.lecturer_id
                                JOIN {$course_table} c ON c.id = l.course_id
                                ORDER BY l.assigned_date DESC LIMIT 5", ARRAY_A);
                            if ($assignments): ?>
                                <div class="space-y-2">
                                    <?php foreach ($assignments as $a): ?>
                                        <div class="flex items-center justify-between p-2.5 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                                            <div class="flex-1 min-w-0">
                                                <div class="font-medium text-xs text-gray-900 truncate">
                                                    <?php echo esc_html($a['first_name'] . ' ' . $a['last_name']); ?>
                                                </div>
                                                <p class="text-xs text-gray-600 truncate mt-0.5">
                                                    <?php echo esc_html($a['course_name']); ?>
                                                </p>
                                                <p class="text-xs text-gray-500 mt-0.5">
                                                    <?php echo esc_html(date('M j, Y', strtotime($a['assigned_date']))); ?>
                                                </p>
                                            </div>
                                            <form method="POST" action="<?php echo admin_url('admin-post.php'); ?>" class="inline ml-2">
                                                <?php wp_nonce_field('nds_unassign_lecturer'); ?>
                                                <input type="hidden" name="action" value="nds_unassign_lecturer">
                                                <input type="hidden" name="lecturer_id" value="<?php echo intval($a['staff_id']); ?>">
                                                <input type="hidden" name="course_id" value="<?php echo intval($a['course_id']); ?>">
                                                <button type="submit" class="text-red-500 hover:text-red-700 text-xs" title="Unassign">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </form>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-6">
                                    <i class="fas fa-link text-3xl text-gray-300 mb-2"></i>
                                    <p class="text-xs text-gray-500">No assignments yet</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Staff Modal -->
    <div id="addStaffModal" class="fixed inset-0 bg-black bg-opacity-50 items-center justify-center z-50 hidden">
        <div class="bg-white rounded-xl w-full max-w-4xl max-h-[90vh] overflow-y-auto shadow-2xl">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-xl font-semibold text-gray-900">
                    <i class="fas fa-user-plus text-blue-600 mr-3"></i>Add New Lecturer
                </h3>
                <button id="closeAddStaffModal" class="text-gray-400 hover:text-gray-600 text-xl">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-6">
                <form method="POST" action="<?php echo admin_url('admin-post.php'); ?>" id="addStaffForm">
                    <?php wp_nonce_field('nds_add_staff_action', 'nds_add_staff_nonce'); ?>
                    <input type="hidden" name="action" value="nds_add_staff">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">First Name *</label>
                            <input type="text" name="first_name" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Last Name *</label>
                            <input type="text" name="last_name" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Email Address *</label>
                            <input type="email" name="email" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" id="staffEmail" required>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Phone</label>
                            <input type="text" name="phone" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" value="0111111111">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Gender *</label>
                            <select name="gender" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                                <option value="">Select gender...</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Role *</label>
                            <select name="role" id="staffRole" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                                <option value="">Select role...</option>
                                <?php
                                $available_roles = nds_get_staff_roles();
                                foreach ($available_roles as $available_role):
                                ?>
                                    <option value="<?php echo esc_attr($available_role); ?>" <?php selected(strtolower($available_role), 'lecturer'); ?>>
                                        <?php echo esc_html($available_role); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div id="lecturerAcademicFields" class="md:col-span-2">
                            <div class="rounded-lg border border-blue-100 bg-blue-50 p-4 mb-4">
                                <p class="text-sm text-blue-900">
                                    Lecturer default password: <strong>Nds@<?php echo esc_html(date('Y')); ?></strong>
                                </p>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Faculty *</label>
                                    <select name="faculty_id" id="lecturerFaculty" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                        <option value="">Select faculty...</option>
                                        <?php foreach ($faculties as $faculty): ?>
                                            <option value="<?php echo intval($faculty['id']); ?>"><?php echo esc_html($faculty['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Program *</label>
                                    <select name="program_id" id="lecturerProgram" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" disabled>
                                        <option value="">Select program...</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Qualification *</label>
                                    <select name="course_id" id="lecturerQualification" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" disabled>
                                        <option value="">Select qualification...</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Date of Birth</label>
                            <input type="date" name="dob" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Address</label>
                            <input type="text" name="address" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" value="Vaal Triangle">
                        </div>
                    </div>

                    <div class="mt-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Profile Picture</label>
                        <div class="flex items-center gap-4">
                            <div class="w-16 h-16 rounded-full overflow-hidden border-2 border-gray-200">
                                <img id="profilePreview" src="" alt="Preview" class="w-full h-full object-cover hidden">
                                <div id="avatarPlaceholder" class="w-full h-full bg-gray-200 flex items-center justify-center">
                                    <i class="fas fa-user text-gray-400 text-lg"></i>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <button type="button" id="uploadProfileBtn" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-2 px-4 rounded-lg flex items-center gap-2 transition-colors duration-200">
                                    <i class="fas fa-upload"></i>Upload
                                </button>
                                <button type="button" id="removeProfileBtn" class="bg-red-100 hover:bg-red-200 text-red-700 font-medium py-2 px-4 rounded-lg items-center gap-2 transition-colors duration-200 hidden">
                                    <i class="fas fa-trash-alt"></i>Remove
                                </button>
                            </div>
                        </div>
                        <input type="hidden" name="profile_picture" id="profilePictureInput">
                    </div>

                    <div class="mt-8 flex justify-end gap-3 pt-6 border-t border-gray-200">
                        <button type="button" id="cancelAddStaff" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-2 px-6 rounded-lg transition-colors duration-200">Cancel</button>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-lg flex items-center gap-2 transition-colors duration-200 shadow-md hover:shadow-lg">
                            <i class="fas fa-save"></i>Add Lecturer
                        </button>
                    </div>
                </form>
            </div>
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

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Modal functionality for Add Staff
        const addStaffModal = document.getElementById('addStaffModal');
        const openBtn = document.getElementById('addStaffBtn');
        const firstStaffBtn = document.getElementById('addFirstStaffBtn');
        const closeBtn = document.getElementById('closeAddStaffModal');
        const cancelBtn = document.getElementById('cancelAddStaff');

        if (openBtn) openBtn.addEventListener('click', () => { addStaffModal.classList.remove('hidden'); addStaffModal.classList.add('flex'); });
        if (firstStaffBtn) firstStaffBtn.addEventListener('click', () => { addStaffModal.classList.remove('hidden'); addStaffModal.classList.add('flex'); });
        if (closeBtn) closeBtn.addEventListener('click', () => { addStaffModal.classList.add('hidden'); addStaffModal.classList.remove('flex'); });
        if (cancelBtn) cancelBtn.addEventListener('click', () => { addStaffModal.classList.add('hidden'); addStaffModal.classList.remove('flex'); });

        addStaffModal.addEventListener('click', (e) => { if (e.target === addStaffModal) { addStaffModal.classList.add('hidden'); addStaffModal.classList.remove('flex'); } });

        // Stats Data for Modals
        const statsData = {
            total_staff: <?php echo json_encode($staff); ?>,
            lecturers: <?php echo json_encode($lecturers_list); ?>,
            assignments: <?php echo json_encode($all_assignments); ?>,
            admins: <?php echo json_encode($admins_list); ?>
        };

        const modalConfig = {
            total_staff: {
                title: 'Total Staff Members',
                col1: 'Staff Name',
                col2: 'Role / Email',
                icon: 'fas fa-users',
                iconColor: '#2563eb',
                iconBg: '#eff6ff'
            },
            lecturers: {
                title: 'Lecturers',
                col1: 'Lecturer Name',
                col2: 'Email',
                icon: 'fas fa-chalkboard-teacher',
                iconColor: '#059669',
                iconBg: '#ecfdf5'
            },
            assignments: {
                title: 'Course Assignments',
                col1: 'Lecturer',
                col2: 'Course Assigned',
                icon: 'fas fa-link',
                iconColor: '#7c3aed',
                iconBg: '#f5f3ff'
            },
            admins: {
                title: 'Administrators & Support',
                col1: 'Staff Name',
                col2: 'Role / Email',
                icon: 'fas fa-user-cog',
                iconColor: '#ea580c',
                iconBg: '#fff7ed'
            }
        };

        window.openStatModal = function(type) {
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
            
            modalIcon.className = config.icon;
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
                    
                    let col1 = '';
                    let col2 = '';
                    let iconClass = 'fas fa-user';
                    let iconColor = '#3b82f6';

                    if (type === 'assignments') {
                        col1 = (item.first_name || '') + ' ' + (item.last_name || '');
                        col2 = item.course_name || '—';
                        iconClass = 'fas fa-link';
                        iconColor = '#8b5cf6';
                    } else {
                        col1 = (item.first_name || '') + ' ' + (item.last_name || '');
                        col2 = (item.role || 'Staff') + ' • ' + (item.email || '');
                        iconClass = item.role === 'Lecturer' ? 'fas fa-chalkboard-teacher' : 'fas fa-user-cog';
                        iconColor = item.role === 'Lecturer' ? '#10b981' : '#f59e0b';
                    }

                    row.innerHTML = `
                        <td style="padding:0.75rem 1rem;">
                            <div style="display:flex; align-items:center;">
                                <div style="width:2rem; height:2rem; background:#f0fdf4; border-radius:50%; display:flex; align-items:center; justify-content:center; margin-right:0.75rem;">
                                    <i class="${iconClass}" style="color:${iconColor}; font-size:0.75rem;"></i>
                                </div>
                                <div style="font-size:0.875rem; font-weight:600; color:#111827;">${col1}</div>
                            </div>
                        </td>
                        <td style="padding:0.75rem 1rem; font-size:0.875rem; color:#6b7280; font-weight:400;">${col2}</td>
                    `;
                    tbody.appendChild(row);
                });
            } else {
                const row = document.createElement('tr');
                row.innerHTML = `<td colspan="2" style="padding:2rem 1rem; text-align:center; color:#9ca3af; font-style:italic;">No items found</td>`;
                tbody.appendChild(row);
            }

            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        };

        window.closeStatModal = function() {
            const modal = document.getElementById('statModal');
            if (modal) {
                modal.classList.add('hidden');
                document.body.style.overflow = '';
            }
        };

        // Lecturer-specific academic selection (Faculty -> Program -> Qualification)
        const staffRole = document.getElementById('staffRole');
        const lecturerFields = document.getElementById('lecturerAcademicFields');
        const facultySelect = document.getElementById('lecturerFaculty');
        const programSelect = document.getElementById('lecturerProgram');
        const qualificationSelect = document.getElementById('lecturerQualification');
        const programsData = <?php echo wp_json_encode($programs); ?>;
        const qualificationsData = <?php echo wp_json_encode($qualifications); ?>;

        function resetSelect(selectEl, placeholder) {
            selectEl.innerHTML = `<option value="">${placeholder}</option>`;
            selectEl.value = '';
        }

        function applyLecturerFieldState() {
            if (!staffRole || !lecturerFields || !facultySelect || !programSelect || !qualificationSelect) return;

            const isLecturer = (staffRole.value || '').toLowerCase() === 'lecturer';
            lecturerFields.style.display = isLecturer ? '' : 'none';
            facultySelect.required = isLecturer;
            programSelect.required = isLecturer;
            qualificationSelect.required = isLecturer;

            if (!isLecturer) {
                facultySelect.value = '';
                resetSelect(programSelect, 'Select program...');
                resetSelect(qualificationSelect, 'Select qualification...');
                programSelect.disabled = true;
                qualificationSelect.disabled = true;
            }
        }

        function populatePrograms() {
            if (!facultySelect || !programSelect || !qualificationSelect) return;

            const facultyId = parseInt(facultySelect.value || '0', 10);
            resetSelect(programSelect, 'Select program...');
            resetSelect(qualificationSelect, 'Select qualification...');

            if (!facultyId) {
                programSelect.disabled = true;
                qualificationSelect.disabled = true;
                return;
            }

            const filteredPrograms = programsData.filter(p => parseInt(p.faculty_id, 10) === facultyId);
            filteredPrograms.forEach(program => {
                const option = document.createElement('option');
                option.value = program.id;
                option.textContent = program.name;
                programSelect.appendChild(option);
            });

            programSelect.disabled = filteredPrograms.length === 0;
            qualificationSelect.disabled = true;
        }

        function populateQualifications() {
            if (!programSelect || !qualificationSelect) return;

            const programId = parseInt(programSelect.value || '0', 10);
            resetSelect(qualificationSelect, 'Select qualification...');

            if (!programId) {
                qualificationSelect.disabled = true;
                return;
            }

            const filteredQualifications = qualificationsData.filter(q => parseInt(q.program_id, 10) === programId);
            filteredQualifications.forEach(qualification => {
                const option = document.createElement('option');
                option.value = qualification.id;
                option.textContent = qualification.name;
                qualificationSelect.appendChild(option);
            });

            qualificationSelect.disabled = filteredQualifications.length === 0;
        }

        if (staffRole) {
            staffRole.addEventListener('change', applyLecturerFieldState);
            applyLecturerFieldState();
        }

        if (facultySelect) {
            facultySelect.addEventListener('change', populatePrograms);
        }

        if (programSelect) {
            programSelect.addEventListener('change', populateQualifications);
        }

        // Search functionality
        const searchInput = document.getElementById('staffSearch');
        const staffTable = document.getElementById('staffList');

        if (searchInput && staffTable) {
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const rows = staffTable.querySelectorAll('tr');
                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    if (text.includes(searchTerm)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        }

        // Profile picture upload
        const uploadBtn = document.getElementById('uploadProfileBtn');
        const removeBtn = document.getElementById('removeProfileBtn');
        const preview = document.getElementById('profilePreview');
        const placeholder = document.getElementById('avatarPlaceholder');
        const hiddenInput = document.getElementById('profilePictureInput');

        if (uploadBtn) {
            uploadBtn.addEventListener('click', function() {
                const mediaUploader = wp.media({
                    title: 'Select Profile Picture',
                    button: { text: 'Use this image' },
                    multiple: false
                });

                mediaUploader.on('select', function() {
                    const attachment = mediaUploader.state().get('selection').first().toJSON();
                    hiddenInput.value = attachment.id;
                    preview.src = attachment.url;
                    preview.classList.remove('hidden');
                    placeholder.classList.add('hidden');
                    removeBtn.classList.remove('hidden'); removeBtn.classList.add('flex');
                });

                mediaUploader.open();
            });
        }

        if (removeBtn) {
            removeBtn.addEventListener('click', function() {
                hiddenInput.value = '';
                preview.src = '';
                preview.classList.add('hidden');
                placeholder.classList.remove('hidden');
                removeBtn.classList.add('hidden'); removeBtn.classList.remove('flex');
            });
        }
    });
    </script>
    <?php
}
