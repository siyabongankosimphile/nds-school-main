<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

$course_ids = isset($course_ids) && is_array($course_ids) ? array_filter(array_map('intval', $course_ids)) : array();

if (empty($course_ids)) {
    echo '<div class="text-center py-10">';
    echo '<div class="w-14 h-14 mx-auto mb-3 bg-gray-100 rounded-full flex items-center justify-center">';
    echo '<i class="fas fa-graduation-cap text-gray-400 text-xl"></i>';
    echo '</div>';
    echo '<h3 class="text-lg font-semibold text-gray-900 mb-2">No assigned courses</h3>';
    echo '<p class="text-sm text-gray-600">Marks appear here once you are assigned to one or more courses.</p>';
    echo '</div>';
    return;
}

$placeholders = implode(',', array_fill(0, count($course_ids), '%d'));

$sql = "
    SELECT
        s.student_number,
        s.first_name,
        s.last_name,
        c.name AS course_name,
        e.final_percentage,
        e.final_grade,
        e.updated_at
    FROM {$wpdb->prefix}nds_student_enrollments e
    INNER JOIN {$wpdb->prefix}nds_students s ON s.id = e.student_id
    INNER JOIN {$wpdb->prefix}nds_courses c ON c.id = e.course_id
    WHERE e.course_id IN ($placeholders)
    ORDER BY c.name ASC, s.last_name ASC, s.first_name ASC
";

$rows = $wpdb->get_results($wpdb->prepare($sql, $course_ids), ARRAY_A);

$graded_count = 0;
$avg = 0.0;
$total = 0.0;

foreach ($rows as $row) {
    if ($row['final_percentage'] !== null && $row['final_percentage'] !== '') {
        $graded_count++;
        $total += (float) $row['final_percentage'];
    }
}

if ($graded_count > 0) {
    $avg = round($total / $graded_count, 1);
}
?>

<div class="space-y-6">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-gray-50 rounded-lg border border-gray-200 p-4">
            <p class="text-xs uppercase tracking-wide text-gray-500 mb-1">Total Learners</p>
            <p class="text-2xl font-bold text-gray-900"><?php echo esc_html(number_format_i18n(count($rows))); ?></p>
        </div>
        <div class="bg-gray-50 rounded-lg border border-gray-200 p-4">
            <p class="text-xs uppercase tracking-wide text-gray-500 mb-1">Graded</p>
            <p class="text-2xl font-bold text-gray-900"><?php echo esc_html(number_format_i18n($graded_count)); ?></p>
        </div>
        <div class="bg-gray-50 rounded-lg border border-gray-200 p-4">
            <p class="text-xs uppercase tracking-wide text-gray-500 mb-1">Average (%)</p>
            <p class="text-2xl font-bold text-gray-900"><?php echo esc_html(number_format_i18n($avg, 1)); ?></p>
        </div>
    </div>

    <?php if (empty($rows)) : ?>
        <div class="text-center py-10 bg-white border border-gray-200 rounded-lg">
            <div class="w-14 h-14 mx-auto mb-3 bg-gray-100 rounded-full flex items-center justify-center">
                <i class="fas fa-clipboard-list text-gray-400 text-xl"></i>
            </div>
            <h3 class="text-lg font-semibold text-gray-900 mb-2">No marks yet</h3>
            <p class="text-sm text-gray-600">Learner marks will appear here once enrollments and grades are available.</p>
        </div>
    <?php else : ?>
        <div class="overflow-x-auto border border-gray-200 rounded-lg">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700">Learner</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700">Student #</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700">Course</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700">Final %</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700">Grade</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700">Updated</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    <?php foreach ($rows as $row) : ?>
                        <tr>
                            <td class="px-4 py-3 text-gray-900"><?php echo esc_html(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))); ?></td>
                            <td class="px-4 py-3 text-gray-700"><?php echo esc_html($row['student_number'] ?? ''); ?></td>
                            <td class="px-4 py-3 text-gray-700"><?php echo esc_html($row['course_name'] ?? ''); ?></td>
                            <td class="px-4 py-3 text-gray-700">
                                <?php
                                if ($row['final_percentage'] !== null && $row['final_percentage'] !== '') {
                                    echo esc_html(number_format((float) $row['final_percentage'], 1)) . '%';
                                } else {
                                    echo '<span class="text-gray-400">-</span>';
                                }
                                ?>
                            </td>
                            <td class="px-4 py-3 text-gray-700">
                                <?php echo !empty($row['final_grade']) ? esc_html($row['final_grade']) : '<span class="text-gray-400">-</span>'; ?>
                            </td>
                            <td class="px-4 py-3 text-gray-500">
                                <?php
                                if (!empty($row['updated_at'])) {
                                    echo esc_html(date_i18n('Y-m-d H:i', strtotime($row['updated_at'])));
                                } else {
                                    echo '<span class="text-gray-400">-</span>';
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
