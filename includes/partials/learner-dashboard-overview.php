<?php
/**
 * Learner Dashboard - Overview Tab
 */
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$learner_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$learner = nds_get_student($learner_id);
$learner_data = (array) $learner;
$full_name = trim(($learner_data['first_name'] ?? '') . ' ' . ($learner_data['last_name'] ?? ''));

// Get recent enrollments
$recent_enrollments = $wpdb->get_results($wpdb->prepare(
    "SELECT e.*, c.name as course_name, c.code as course_code, p.name as program_name
     FROM {$wpdb->prefix}nds_student_enrollments e
     LEFT JOIN {$wpdb->prefix}nds_courses c ON e.course_id = c.id
     LEFT JOIN {$wpdb->prefix}nds_programs p ON c.program_id = p.id
     WHERE e.student_id = %d
     ORDER BY e.created_at DESC
     LIMIT 5",
    $learner_id
), ARRAY_A);
?>

<div class="space-y-6">
    <!-- Personal Information -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">Personal Information</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="text-sm font-medium text-gray-500">Full Name</label>
                <p class="mt-1 text-sm text-gray-900"><?php echo esc_html($full_name); ?></p>
            </div>
            <div>
                <label class="text-sm font-medium text-gray-500">Student Number</label>
                <p class="mt-1 text-sm text-gray-900"><?php echo esc_html($learner_data['student_number'] ?? 'N/A'); ?></p>
            </div>
            <div>
                <label class="text-sm font-medium text-gray-500">Email</label>
                <p class="mt-1 text-sm text-gray-900"><?php echo esc_html($learner_data['email'] ?? 'N/A'); ?></p>
            </div>
            <div>
                <label class="text-sm font-medium text-gray-500">Phone</label>
                <p class="mt-1 text-sm text-gray-900"><?php echo esc_html($learner_data['phone'] ?? 'N/A'); ?></p>
            </div>
            <div>
                <label class="text-sm font-medium text-gray-500">Date of Birth</label>
                <p class="mt-1 text-sm text-gray-900"><?php echo !empty($learner_data['date_of_birth']) ? esc_html(date('F j, Y', strtotime($learner_data['date_of_birth']))) : 'N/A'; ?></p>
            </div>
            <div>
                <label class="text-sm font-medium text-gray-500">Gender</label>
                <p class="mt-1 text-sm text-gray-900"><?php echo esc_html(ucfirst($learner_data['gender'] ?? 'N/A')); ?></p>
            </div>
            <div>
                <label class="text-sm font-medium text-gray-500">Status</label>
                <p class="mt-1">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                        <?php 
                        $status = $learner_data['status'] ?? 'prospect';
                        echo $status === 'active' ? 'bg-green-100 text-green-800' : 
                             ($status === 'prospect' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800');
                        ?>">
                        <?php echo esc_html(ucfirst($status)); ?>
                    </span>
                </p>
            </div>
            <div>
                <label class="text-sm font-medium text-gray-500">Address</label>
                <p class="mt-1 text-sm text-gray-900">
                    <?php 
                    $address_parts = array_filter([
                        $learner_data['address'] ?? '',
                        $learner_data['city'] ?? '',
                        $learner_data['country'] ?? 'South Africa'
                    ]);
                    echo !empty($address_parts) ? esc_html(implode(', ', $address_parts)) : 'N/A';
                    ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Recent Courses -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-semibold text-gray-900">Recent Courses</h2>
            <a href="<?php echo admin_url('admin.php?page=nds-learner-dashboard&id=' . $learner_id . '&tab=courses'); ?>"
               class="text-sm text-blue-600 hover:text-blue-700 font-medium">
                View All
            </a>
        </div>
        <?php if (!empty($recent_enrollments)): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Course</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Program</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Grade</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($recent_enrollments as $enrollment): ?>
                            <tr>
                                <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?php echo esc_html($enrollment['course_name'] ?? 'N/A'); ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo esc_html($enrollment['program_name'] ?? 'N/A'); ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs font-medium rounded-full 
                                        <?php 
                                        $status = $enrollment['status'] ?? '';
                                        echo $status === 'enrolled' ? 'bg-green-100 text-green-800' : 
                                             ($status === 'completed' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800');
                                        ?>">
                                        <?php echo esc_html(ucfirst($status)); ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                    <?php 
                                    if (!empty($enrollment['final_percentage'])) {
                                        echo esc_html($enrollment['final_percentage']) . '%';
                                    } elseif (!empty($enrollment['final_grade'])) {
                                        echo esc_html($enrollment['final_grade']);
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-gray-500 text-sm">No courses enrolled yet.</p>
        <?php endif; ?>
    </div>
</div>
