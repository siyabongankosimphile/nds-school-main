<?php
// Prevent direct access - this file should only be included by WordPress
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Ensure WordPress functions are available
if (!function_exists('current_user_can')) {
    return;
}

// Superior Edit Staff Page
function nds_edit_staff_page_improved() {
    if (!current_user_can('manage_options')) { wp_die('Unauthorized'); }

    $staff_id = isset($_GET['staff_id']) ? intval($_GET['staff_id']) : 0;
    $staff = $staff_id ? nds_get_staff_by_id($staff_id) : null;

    if (!$staff) {
        ?>
        <div class="wrap nds-admin-wrap">
            <div class="nds-header-section">
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900 mb-2">
                            <i class="fas fa-user-times text-red-600 mr-3"></i>Staff Not Found
                        </h1>
                        <p class="text-gray-600">The requested staff member could not be found.</p>
                    </div>
                </div>

                <!-- Breadcrumbs -->
                <nav class="nds-breadcrumbs">
                    <a href="<?php echo admin_url('admin.php?page=nds-school'); ?>" class="nds-breadcrumb-link">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                    <span class="nds-breadcrumb-separator">></span>
                    <a href="<?php echo admin_url('admin.php?page=nds-staff'); ?>" class="nds-breadcrumb-link">
                        <i class="fas fa-users"></i> Staff Management
                    </a>
                    <span class="nds-breadcrumb-separator">></span>
                    <span class="nds-breadcrumb-current">Staff Not Found</span>
                </nav>
            </div>

            <div class="text-center py-12">
                <i class="fas fa-user-times text-6xl text-gray-300 mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 mb-2">Staff Member Not Found</h3>
                <p class="text-gray-600 mb-6">The staff member you're looking for doesn't exist or has been removed.</p>
                <a href="<?php echo admin_url('admin.php?page=nds-staff'); ?>" class="nds-btn-primary">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Staff Management
                </a>
            </div>
        </div>
        <?php
        return;
    }

    global $wpdb;
    $course_table = $wpdb->prefix . 'nds_courses';
    $link_table = $wpdb->prefix . 'nds_course_lecturers';

    // Get staff assignments
    $assignments = $wpdb->get_results($wpdb->prepare(
        "SELECT c.name AS course_name, l.assigned_date, c.id as course_id
         FROM {$link_table} l
         JOIN {$course_table} c ON c.id = l.course_id
         WHERE l.lecturer_id = %d
         ORDER BY l.assigned_date DESC",
        $staff_id
    ), ARRAY_A);

    // Get available courses for assignment
    $assigned_course_ids = array_column($assignments, 'course_id');
    $assigned_ids_placeholder = count($assigned_course_ids) > 0 ? implode(',', $assigned_course_ids) : '0';
    $available_courses = $wpdb->get_results(
        "SELECT id, name FROM {$course_table}
         WHERE id NOT IN ({$assigned_ids_placeholder})
         ORDER BY name",
        ARRAY_A
    );

    // Staff stats
    $total_assignments = count($assignments);
    $role_color = match(strtolower($staff['role'])) {
        'lecturer' => 'role-lecturer',
        'admin' => 'role-admin',
        'support' => 'role-support',
        default => 'role-support'
    };

    ?>
    <div class="nds-tailwind-wrapper bg-gray-50 pb-12" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin-left: -20px; padding-left: 20px; margin-top: -20px;">
        <!-- Header -->
        <div class="bg-white shadow-sm border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center py-6">
                    <div class="flex items-center space-x-4">
                        <div class="w-12 h-12 bg-gradient-to-br from-blue-600 to-purple-600 rounded-xl flex items-center justify-center">
                            <i class="fas fa-user-edit text-white text-xl"></i>
                        </div>
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900"><?php echo esc_html($staff['first_name'] . ' ' . $staff['last_name']); ?></h1>
                            <div class="flex items-center gap-3 mt-1">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?php
                                    echo match(strtolower($staff['role'])) {
                                        'lecturer' => 'bg-emerald-50 text-emerald-700',
                                        'admin' => 'bg-blue-50 text-blue-700',
                                        'support' => 'bg-purple-50 text-purple-700',
                                        default => 'bg-gray-50 text-gray-700'
                                    };
                                ?>">
                                    <?php echo esc_html($staff['role']); ?>
                                </span>
                                <span class="text-sm text-gray-600 flex items-center">
                                    <i class="fas fa-envelope mr-1.5 text-gray-400"></i><?php echo esc_html($staff['email']); ?>
                                </span>
                                <?php if ($staff['gender']): ?>
                                    <span class="text-sm text-gray-600 flex items-center">
                                        <i class="fas fa-<?php echo $staff['gender'] === 'Male' ? 'mars' : 'venus'; ?> mr-1.5 text-gray-400"></i><?php echo esc_html($staff['gender']); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center space-x-3">
                        <div class="text-right mr-4">
                            <p class="text-xs uppercase tracking-wide text-gray-500">Last updated</p>
                            <p class="text-sm font-medium text-gray-900"><?php echo esc_html(date_i18n('M j, Y \a\t g:i A')); ?></p>
                        </div>
                        <a href="<?php echo admin_url('admin.php?page=nds-staff'); ?>" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-2.5 px-5 rounded-lg flex items-center gap-2 transition-colors duration-200 shadow-sm hover:shadow">
                            <i class="fas fa-arrow-left text-sm"></i>Back
                        </a>
                        <button type="button" id="saveBtn" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 px-5 rounded-lg flex items-center gap-2 transition-colors duration-200 shadow-sm hover:shadow-md">
                            <i class="fas fa-save text-sm"></i>Save Changes
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main content -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
            <!-- KPI cards -->
            <div class="kpi-cards-grid grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Current Assignments -->
                <div class="bg-white shadow-sm rounded-xl p-5 border border-gray-100 flex flex-col justify-between">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500">Current Assignments</p>
                            <p class="mt-2 text-2xl font-semibold text-gray-900">
                                <?php echo number_format_i18n($total_assignments); ?>
                            </p>
                        </div>
                        <div class="w-10 h-10 rounded-lg bg-emerald-50 flex items-center justify-center">
                            <i class="fas fa-link text-emerald-600 text-xl"></i>
                        </div>
                    </div>
                    <p class="mt-3 text-xs text-gray-500">
                        Active course assignments
                    </p>
                </div>

                <!-- Join Date -->
                <div class="bg-white shadow-sm rounded-xl p-5 border border-gray-100 flex flex-col justify-between">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500">Join Date</p>
                            <p class="mt-2 text-lg font-semibold text-gray-900">
                                <?php echo esc_html(date('M j, Y', strtotime($staff['created_at']))); ?>
                            </p>
                        </div>
                        <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center">
                            <i class="fas fa-calendar text-blue-600 text-xl"></i>
                        </div>
                    </div>
                    <p class="mt-3 text-xs text-gray-500">
                        Staff member since
                    </p>
                </div>

                <!-- Staff ID -->
                <div class="bg-white shadow-sm rounded-xl p-5 border border-gray-100 flex flex-col justify-between">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500">Staff ID</p>
                            <p class="mt-2 text-lg font-semibold text-gray-900">
                                #<?php echo number_format_i18n($staff['id']); ?>
                            </p>
                        </div>
                        <div class="w-10 h-10 rounded-lg bg-purple-50 flex items-center justify-center">
                            <i class="fas fa-id-badge text-purple-600 text-xl"></i>
                        </div>
                    </div>
                    <p class="mt-3 text-xs text-gray-500">
                        Unique identifier
                    </p>
                </div>
            </div>

            <!-- Main Content Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Edit Form Section -->
                <div class="lg:col-span-2">
                    <div class="bg-white shadow-sm rounded-xl border border-gray-100">
                        <div class="px-5 py-4 border-b border-gray-100">
                            <h3 class="text-sm font-semibold text-gray-900">Edit Staff Information</h3>
                            <p class="text-xs text-gray-500 mt-1">Update staff member details</p>
                        </div>
                        <div class="p-5">
                        <form method="POST" action="<?php echo admin_url('admin-post.php'); ?>" id="editStaffForm">
                            <?php wp_nonce_field('nds_edit_staff_action', 'nds_edit_staff_nonce'); ?>
                            <input type="hidden" name="action" value="nds_update_staff">
                            <input type="hidden" name="staff_id" value="<?php echo esc_attr($staff['id']); ?>">

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1.5">First Name *</label>
                                    <input type="text" name="first_name" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                           value="<?php echo esc_attr($staff['first_name']); ?>" required>
                                </div>

                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1.5">Last Name *</label>
                                    <input type="text" name="last_name" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                           value="<?php echo esc_attr($staff['last_name']); ?>" required>
                                </div>

                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1.5">Email</label>
                                    <input type="email" name="email" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" id="staffEmail"
                                           value="<?php echo esc_attr($staff['email']); ?>" readonly>
                                    <p class="text-xs text-gray-500 mt-1">Email is auto-generated from first name</p>
                                </div>

                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1.5">Phone</label>
                                    <input type="text" name="phone" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                           value="<?php echo esc_attr($staff['phone'] ?? '0111111111'); ?>">
                                </div>

                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1.5">Gender *</label>
                                    <select name="gender" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                                        <option value="">Select gender...</option>
                                        <option value="Male" <?php selected($staff['gender'], 'Male'); ?>>Male</option>
                                        <option value="Female" <?php selected($staff['gender'], 'Female'); ?>>Female</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1.5">Role *</label>
                                    <select name="role" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                                        <option value="">Select role...</option>
                                        <?php
                                        $available_roles = nds_get_staff_roles();
                                        foreach ($available_roles as $available_role):
                                        ?>
                                            <option value="<?php echo esc_attr($available_role); ?>" <?php selected($staff['role'], $available_role); ?>>
                                                <?php echo esc_html($available_role); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1.5">Date of Birth</label>
                                    <input type="date" name="dob" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                           value="<?php echo esc_attr($staff['dob'] ?? ''); ?>">
                                </div>

                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1.5">Address</label>
                                    <input type="text" name="address" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                           value="<?php echo esc_attr($staff['address'] ?? 'Vaal Triangle'); ?>">
                                </div>
                            </div>

                            <!-- Profile Picture Section -->
                            <div class="mt-6">
                                <label class="block text-xs font-medium text-gray-700 mb-1.5">Profile Picture</label>
                                <div class="flex items-center gap-4">
                                    <div class="w-16 h-16 rounded-full overflow-hidden border-2 border-gray-200">
                                        <img id="profilePreview" src="<?php echo $staff['profile_picture'] ? esc_url(wp_get_attachment_url($staff['profile_picture'])) : ''; ?>" alt="Preview" class="w-full h-full object-cover <?php echo $staff['profile_picture'] ? '' : 'hidden'; ?>">
                                        <div id="avatarPlaceholder" class="w-full h-full bg-gray-200 flex items-center justify-center <?php echo $staff['profile_picture'] ? 'hidden' : ''; ?>">
                                            <i class="fas fa-user text-gray-400"></i>
                                        </div>
                                    </div>
                                    <div class="flex gap-2">
                                        <button type="button" id="uploadProfileBtn" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-2 px-4 rounded-lg text-sm transition-colors duration-200 flex items-center gap-2">
                                            <i class="fas fa-upload text-xs"></i><?php echo $staff['profile_picture'] ? 'Change' : 'Upload'; ?>
                                        </button>
                                        <button type="button" id="removeProfileBtn" class="bg-red-100 hover:bg-red-200 text-red-700 font-medium py-2 px-4 rounded-lg text-sm transition-colors duration-200 flex items-center gap-2 <?php echo $staff['profile_picture'] ? '' : 'hidden'; ?>">
                                            <i class="fas fa-trash-alt text-xs"></i>Remove
                                        </button>
                                    </div>
                                </div>
                                <input type="hidden" name="profile_picture" id="profilePictureInput" value="<?php echo esc_attr($staff['profile_picture'] ?? ''); ?>">
                            </div>

                            <div class="flex justify-end gap-3 pt-6 border-t border-gray-100 mt-6">
                                <a href="<?php echo admin_url('admin.php?page=nds-staff'); ?>" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-2 px-5 rounded-lg text-sm transition-colors duration-200">Cancel</a>
                                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-5 rounded-lg text-sm flex items-center gap-2 transition-colors duration-200 shadow-sm hover:shadow-md">
                                    <i class="fas fa-save text-sm"></i>Update Staff Member
                                </button>
                            </div>
                        </form>
                        </div>
                    </div>
                </div>

                <!-- Sidebar Section -->
                <div class="space-y-6">
                <!-- Quick Assignment Card -->
                <?php if ($available_courses): ?>
                <div class="bg-white shadow-sm rounded-xl border border-gray-100">
                    <div class="px-5 py-4 border-b border-gray-100">
                        <h3 class="text-sm font-semibold text-gray-900">Assign to Course</h3>
                        <p class="text-xs text-gray-500 mt-1">Link this lecturer to a course</p>
                    </div>
                    <div class="p-5">
                        <form method="POST" action="<?php echo admin_url('admin-post.php'); ?>" id="quickAssignForm">
                            <?php wp_nonce_field('nds_assign_lecturer'); ?>
                            <input type="hidden" name="action" value="nds_assign_lecturer">
                            <input type="hidden" name="lecturer_id" value="<?php echo intval($staff['id']); ?>">

                            <div class="space-y-4">
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1.5">Select Course</label>
                                    <select name="course_id" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                                        <option value="">Choose a course...</option>
                                        <?php foreach ($available_courses as $course): ?>
                                            <option value="<?php echo intval($course['id']); ?>">
                                                <?php echo esc_html($course['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2 px-4 rounded-lg text-sm transition-colors duration-200 flex items-center justify-center gap-2 shadow-sm hover:shadow">
                                    <i class="fas fa-plus text-xs"></i>Assign Course
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Current Assignments Card -->
                <div class="bg-white shadow-sm rounded-xl border border-gray-100">
                    <div class="px-5 py-4 border-b border-gray-100">
                        <h3 class="text-sm font-semibold text-gray-900">Current Assignments</h3>
                        <p class="text-xs text-gray-500 mt-1">Courses assigned to this lecturer</p>
                    </div>
                    <div class="p-5">
                        <?php if ($assignments): ?>
                            <div class="space-y-2">
                                <?php foreach ($assignments as $assignment): ?>
                                    <div class="flex items-center justify-between p-2.5 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                                        <div class="flex-1 min-w-0">
                                            <div class="font-medium text-xs text-gray-900 truncate">
                                                <?php echo esc_html($assignment['course_name']); ?>
                                            </div>
                                            <p class="text-xs text-gray-500 mt-0.5">
                                                <?php echo esc_html(date('M j, Y', strtotime($assignment['assigned_date']))); ?>
                                            </p>
                                        </div>
                                        <form method="POST" action="<?php echo admin_url('admin-post.php'); ?>" class="inline ml-2">
                                            <?php wp_nonce_field('nds_unassign_lecturer'); ?>
                                            <input type="hidden" name="action" value="nds_unassign_lecturer">
                                            <input type="hidden" name="lecturer_id" value="<?php echo intval($staff['id']); ?>">
                                            <input type="hidden" name="course_id" value="<?php echo intval($assignment['course_id']); ?>">
                                            <button type="submit" class="text-red-500 hover:text-red-700 text-xs" title="Unassign">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-6">
                                <i class="fas fa-graduation-cap text-3xl text-gray-300 mb-2"></i>
                                <p class="text-xs text-gray-500">No course assignments yet</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Actions Card -->
                <div class="bg-white shadow-sm rounded-xl border border-gray-100">
                    <div class="px-5 py-4 border-b border-gray-100">
                        <h3 class="text-sm font-semibold text-gray-900">Quick Actions</h3>
                        <p class="text-xs text-gray-500 mt-1">Common tasks and shortcuts</p>
                    </div>
                    <div class="p-5">
                        <div class="space-y-2">
                            <button onclick="window.print()" class="w-full flex items-center space-x-2 px-3 py-2 rounded-lg border border-gray-200 hover:border-indigo-400 hover:bg-indigo-50 transition-colors text-xs">
                                <i class="fas fa-print text-indigo-600"></i>
                                <span class="font-medium text-gray-800">Print Staff Details</span>
                            </button>

                            <button onclick="copyStaffEmail()" class="w-full flex items-center space-x-2 px-3 py-2 rounded-lg border border-gray-200 hover:border-indigo-400 hover:bg-indigo-50 transition-colors text-xs">
                                <i class="fas fa-copy text-indigo-600"></i>
                                <span class="font-medium text-gray-800">Copy Email Address</span>
                            </button>

                            <?php if ($staff['phone'] && $staff['phone'] !== '0111111111'): ?>
                            <button onclick="copyStaffPhone()" class="w-full flex items-center space-x-2 px-3 py-2 rounded-lg border border-gray-200 hover:border-indigo-400 hover:bg-indigo-50 transition-colors text-xs">
                                <i class="fas fa-phone text-indigo-600"></i>
                                <span class="font-medium text-gray-800">Copy Phone Number</span>
                            </button>
                            <?php endif; ?>

                            <a href="<?php echo admin_url('admin.php?page=nds-staff'); ?>" class="w-full flex items-center space-x-2 px-3 py-2 rounded-lg border border-gray-200 hover:border-indigo-400 hover:bg-indigo-50 transition-colors text-xs">
                                <i class="fas fa-users text-indigo-600"></i>
                                <span class="font-medium text-gray-800">View All Staff</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Auto-generate email
        const firstNameInput = document.querySelector('input[name="first_name"]');
        const emailInput = document.getElementById('staffEmail');

        if (firstNameInput && emailInput) {
            firstNameInput.addEventListener('input', function() {
                const firstName = this.value.toLowerCase().replace(/[^a-z]/g, '');
                emailInput.value = firstName ? firstName + '@ndsacademy.co.za' : '';
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
                    removeBtn.classList.remove('hidden');
                    uploadBtn.innerHTML = '<i class="fas fa-upload mr-2"></i>Change';
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
                removeBtn.classList.add('hidden');
                uploadBtn.innerHTML = '<i class="fas fa-upload mr-2"></i>Upload';
            });
        }

        // Quick action functions
        window.copyStaffEmail = function() {
            const email = '<?php echo esc_js($staff['email']); ?>';
            navigator.clipboard.writeText(email).then(() => {
                // Show toast notification if available
                if (window.Toastify) {
                    Toastify({ text: 'Email copied to clipboard!', backgroundColor: '#16a34a', duration: 2000 }).showToast();
                }
            });
        };

        window.copyStaffPhone = function() {
            const phone = '<?php echo esc_js($staff['phone']); ?>';
            navigator.clipboard.writeText(phone).then(() => {
                // Show toast notification if available
                if (window.Toastify) {
                    Toastify({ text: 'Phone number copied to clipboard!', backgroundColor: '#16a34a', duration: 2000 }).showToast();
                }
            });
        };

        // Save button handler
        const saveBtn = document.getElementById('saveBtn');
        const editForm = document.getElementById('editStaffForm');
        if (saveBtn && editForm) {
            saveBtn.addEventListener('click', function() {
                editForm.submit();
            });
        }
    });
    </script>

    <style>
    /* Additional styles for edit staff page */
    .nds-avatar-large {
        width: 5rem;
        height: 5rem;
        border-radius: 50%;
        overflow: hidden;
        border: 3px solid #e5e7eb;
    }
    .nds-avatar-large img { width: 100%; height: 100%; object-fit: cover; }
    .nds-avatar-placeholder-large {
        width: 100%;
        height: 100%;
        background: #f3f4f6;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
    }

    .nds-role-badge-large {
        padding: 0.5rem 1rem;
        border-radius: 0.5rem;
        font-size: 0.875rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .nds-breadcrumbs {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 2rem;
        font-size: 0.875rem;
    }
    .nds-breadcrumb-link {
        color: #6b7280;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 0.25rem;
        transition: color 0.2s;
    }
    .nds-breadcrumb-link:hover { color: #3b82f6; }
    .nds-breadcrumb-separator { color: #d1d5db; }
    .nds-breadcrumb-current {
        color: #374151;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 0.25rem;
    }

    /* Button variations */
    .nds-btn-secondary.w-full { width: 100%; }
    .nds-btn-secondary.justify-start { justify-content: flex-start; }

    /* Breadcrumb styles */
    .nds-breadcrumbs {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 2rem;
        font-size: 0.875rem;
        flex-wrap: wrap;
    }
    .nds-breadcrumb-link {
        color: #6b7280;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 0.25rem;
        transition: color 0.2s;
        padding: 0.25rem 0.5rem;
        border-radius: 0.25rem;
    }
    .nds-breadcrumb-link:hover {
        color: #3b82f6;
        background: #f3f4f6;
    }
    .nds-breadcrumb-separator {
        color: #d1d5db;
        font-weight: bold;
    }
    .nds-breadcrumb-current {
        color: #374151;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 0.25rem;
        padding: 0.25rem 0.5rem;
        background: #f9fafb;
        border-radius: 0.25rem;
        border: 1px solid #e5e7eb;
    }

    /* Ensure KPI cards grid displays correctly */
    .kpi-cards-grid {
        display: grid;
        gap: 1.5rem;
    }
    @media (min-width: 1024px) {
        .kpi-cards-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }
    @media (min-width: 640px) and (max-width: 1023px) {
        .kpi-cards-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }
    @media (max-width: 639px) {
        .kpi-cards-grid {
            grid-template-columns: 1fr;
        }
    }

    /* Print styles */
    @media print {
        .nds-admin-wrap { padding: 0; background: white !important; }
        .nds-card { box-shadow: none; border: 1px solid #e5e7eb; }
        .nds-btn-primary, .nds-btn-secondary, .nds-btn-danger { display: none !important; }
        .nds-modal-overlay { display: none !important; }
        .nds-breadcrumbs { display: none !important; }
    }
    </style>
    <?php
}
