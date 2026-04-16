<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

$selected_course_id = isset($_GET['course_id']) ? (int) $_GET['course_id'] : 0;
if ($selected_course_id <= 0 && !empty($courses_taught)) {
    $selected_course_id = (int) $courses_taught[0]['id'];
}

$enrolled_rows = array();
if ($selected_course_id > 0) {
    $enrolled_rows = $wpdb->get_results($wpdb->prepare(
        "SELECT e.student_id, e.status, e.enrollment_date, s.student_number, s.first_name, s.last_name, s.email
         FROM {$wpdb->prefix}nds_student_enrollments e
         INNER JOIN {$wpdb->prefix}nds_students s ON s.id = e.student_id
         WHERE e.course_id = %d AND e.academic_year_id = %d AND e.semester_id = %d
         ORDER BY s.last_name, s.first_name",
        $selected_course_id,
        (int) $active_year_id,
        (int) $active_semester_id
    ), ARRAY_A);
}
?>

<div class="space-y-6">
    <div class="bg-white border border-gray-200 rounded-lg p-4">
        <h3 class="text-lg font-semibold text-gray-900 mb-3">Enrollment Management</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Course</label>
                <select onchange="window.location.href='<?php echo esc_url(nds_staff_portal_tab_url('enrollment')); ?>&course_id=' + this.value" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <?php foreach ($courses_taught as $course): ?>
                        <option value="<?php echo esc_attr($course['id']); ?>" <?php selected($selected_course_id, (int) $course['id']); ?>><?php echo esc_html($course['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="space-y-2">
                <?php wp_nonce_field('nds_staff_manage_enrollment', 'nds_staff_manage_enrollment_nonce'); ?>
                <input type="hidden" name="action" value="nds_staff_manage_enrollment">
                <input type="hidden" name="intent" value="enroll">
                <input type="hidden" name="redirect_url" value="<?php echo esc_url(nds_staff_portal_tab_url('enrollment') . '&course_id=' . (int) $selected_course_id); ?>">
                <input type="hidden" name="course_id" value="<?php echo esc_attr($selected_course_id); ?>">

                <label class="block text-xs font-medium text-gray-700 mb-1">Add Learner (Student # or Email)</label>
                <div class="flex gap-2">
                    <input type="text" name="student_identifier" class="flex-1 border border-gray-300 rounded-lg px-3 py-2" placeholder="e.g. STU001 or user@email.com" required>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Enroll</button>
                </div>
            </form>
        </div>
    </div>

    <div class="bg-white border border-gray-200 rounded-lg overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-3 py-2 text-left">Student</th>
                    <th class="px-3 py-2 text-left">Student #</th>
                    <th class="px-3 py-2 text-left">Email</th>
                    <th class="px-3 py-2 text-left">Status</th>
                    <th class="px-3 py-2 text-left">Enrolled Date</th>
                    <th class="px-3 py-2 text-left">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white">
                <?php foreach ($enrolled_rows as $row): ?>
                    <tr>
                        <td class="px-3 py-2"><?php echo esc_html(trim($row['first_name'] . ' ' . $row['last_name'])); ?></td>
                        <td class="px-3 py-2"><?php echo esc_html($row['student_number']); ?></td>
                        <td class="px-3 py-2"><?php echo esc_html($row['email']); ?></td>
                        <td class="px-3 py-2"><?php echo esc_html(ucfirst($row['status'])); ?></td>
                        <td class="px-3 py-2"><?php echo esc_html($row['enrollment_date']); ?></td>
                        <td class="px-3 py-2">
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Unenroll this learner from this course?');">
                                <?php wp_nonce_field('nds_staff_manage_enrollment', 'nds_staff_manage_enrollment_nonce'); ?>
                                <input type="hidden" name="action" value="nds_staff_manage_enrollment">
                                <input type="hidden" name="intent" value="unenroll">
                                <input type="hidden" name="redirect_url" value="<?php echo esc_url(nds_staff_portal_tab_url('enrollment') . '&course_id=' . (int) $selected_course_id); ?>">
                                <input type="hidden" name="course_id" value="<?php echo esc_attr($selected_course_id); ?>">
                                <input type="hidden" name="student_id" value="<?php echo esc_attr($row['student_id']); ?>">
                                <button type="submit" class="px-2 py-1 bg-red-600 text-white rounded hover:bg-red-700 text-xs">Unenroll</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($enrolled_rows)): ?>
                    <tr><td colspan="6" class="px-3 py-6 text-center text-gray-600">No enrollments found for this course in the active term.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
