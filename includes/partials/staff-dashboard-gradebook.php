<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

$selected_course_id = isset($_GET['course_id']) ? (int) $_GET['course_id'] : 0;
if ($selected_course_id <= 0 && !empty($courses_taught)) {
    $selected_course_id = (int) $courses_taught[0]['id'];
}

$rows = array();
if ($selected_course_id > 0) {
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT e.id AS enrollment_id, e.student_id, e.status, e.final_percentage, e.final_grade, e.updated_at,
                s.student_number, s.first_name, s.last_name, s.email
         FROM {$wpdb->prefix}nds_student_enrollments e
         INNER JOIN {$wpdb->prefix}nds_students s ON s.id = e.student_id
         WHERE e.course_id = %d AND e.academic_year_id = %d AND e.semester_id = %d
         ORDER BY s.last_name, s.first_name",
        $selected_course_id,
        (int) $active_year_id,
        (int) $active_semester_id
    ), ARRAY_A);
}

$average = 0;
$graded = 0;
$sum = 0;
foreach ($rows as $row) {
    if ($row['final_percentage'] !== null && $row['final_percentage'] !== '') {
        $graded++;
        $sum += (float) $row['final_percentage'];
    }
}
if ($graded > 0) {
    $average = round($sum / $graded, 2);
}
?>

<div class="space-y-6">
    <?php if (isset($_GET['gradebook_notice'])): ?>
        <div class="p-3 rounded-lg bg-green-50 border border-green-200 text-green-800 text-sm">Gradebook saved successfully.</div>
    <?php endif; ?>

    <div class="bg-white border border-gray-200 rounded-lg p-4">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Gradebook Management</h3>
                <p class="text-sm text-gray-600">Update final percentages and grades, then export to CSV.</p>
            </div>
            <?php if ($selected_course_id > 0): ?>
                <a href="<?php echo esc_url(admin_url('admin-post.php?action=nds_staff_export_gradebook&course_id=' . (int) $selected_course_id)); ?>" class="px-3 py-2 bg-indigo-600 text-white rounded-lg text-sm hover:bg-indigo-700">Export CSV</a>
            <?php endif; ?>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mt-4">
            <div class="p-3 border border-gray-200 rounded-lg">
                <p class="text-xs text-gray-500">Learners</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo esc_html(count($rows)); ?></p>
            </div>
            <div class="p-3 border border-gray-200 rounded-lg">
                <p class="text-xs text-gray-500">Graded</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo esc_html($graded); ?></p>
            </div>
            <div class="p-3 border border-gray-200 rounded-lg">
                <p class="text-xs text-gray-500">Average %</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo esc_html($average); ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white border border-gray-200 rounded-lg p-4">
        <label class="block text-sm font-medium text-gray-700 mb-2">Course</label>
        <select onchange="window.location.href='<?php echo esc_url(nds_staff_portal_tab_url('gradebook')); ?>&course_id=' + this.value" class="w-full md:w-96 border border-gray-300 rounded-lg px-3 py-2">
            <?php foreach ($courses_taught as $course): ?>
                <option value="<?php echo esc_attr($course['id']); ?>" <?php selected($selected_course_id, (int) $course['id']); ?>><?php echo esc_html($course['name']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <?php if (!empty($rows)): ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="bg-white border border-gray-200 rounded-lg overflow-x-auto">
            <?php wp_nonce_field('nds_staff_save_gradebook', 'nds_staff_save_gradebook_nonce'); ?>
            <input type="hidden" name="action" value="nds_staff_save_gradebook">
            <input type="hidden" name="redirect_url" value="<?php echo esc_url(nds_staff_portal_tab_url('gradebook') . '&course_id=' . (int) $selected_course_id); ?>">
            <input type="hidden" name="course_id" value="<?php echo esc_attr($selected_course_id); ?>">

            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left">Student</th>
                        <th class="px-3 py-2 text-left">Student #</th>
                        <th class="px-3 py-2 text-left">Status</th>
                        <th class="px-3 py-2 text-left">Final %</th>
                        <th class="px-3 py-2 text-left">Final Grade</th>
                        <th class="px-3 py-2 text-left">Updated</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    <?php foreach ($rows as $i => $row): ?>
                        <tr>
                            <td class="px-3 py-2"><?php echo esc_html(trim($row['first_name'] . ' ' . $row['last_name'])); ?></td>
                            <td class="px-3 py-2"><?php echo esc_html($row['student_number']); ?></td>
                            <td class="px-3 py-2"><?php echo esc_html(ucfirst($row['status'])); ?></td>
                            <td class="px-3 py-2">
                                <input type="hidden" name="enrollment_id[]" value="<?php echo esc_attr($row['enrollment_id']); ?>">
                                <input type="number" step="0.01" min="0" max="100" name="final_percentage[]" value="<?php echo esc_attr($row['final_percentage']); ?>" class="w-24 border border-gray-300 rounded px-2 py-1">
                            </td>
                            <td class="px-3 py-2">
                                <input type="text" name="final_grade[]" value="<?php echo esc_attr($row['final_grade']); ?>" class="w-24 border border-gray-300 rounded px-2 py-1">
                            </td>
                            <td class="px-3 py-2"><?php echo !empty($row['updated_at']) ? esc_html(date_i18n('Y-m-d H:i', strtotime($row['updated_at']))) : '-'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="p-4 border-t border-gray-200 text-right">
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Save Gradebook</button>
            </div>
        </form>
    <?php else: ?>
        <div class="bg-white border border-gray-200 rounded-lg p-8 text-center text-gray-600">No enrolled learners found for the selected course and active term.</div>
    <?php endif; ?>
</div>
