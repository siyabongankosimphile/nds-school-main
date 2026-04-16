<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

$course_ids = isset($course_ids) && is_array($course_ids) ? array_values(array_map('intval', $course_ids)) : array();
$placeholders = !empty($course_ids) ? implode(',', array_fill(0, count($course_ids), '%d')) : '';

$total_learners = 0;
$avg_grade = 0;
$total_submissions = 0;
$graded_submissions = 0;
$struggling = array();

if (!empty($course_ids)) {
    $params = array_merge($course_ids, array((int) $active_year_id, (int) $active_semester_id));

    $total_learners = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(DISTINCT student_id)
         FROM {$wpdb->prefix}nds_student_enrollments
         WHERE course_id IN ($placeholders)
         AND academic_year_id = %d AND semester_id = %d
         AND status IN ('applied','enrolled','waitlisted')",
        $params
    ));

    $avg_grade_value = $wpdb->get_var($wpdb->prepare(
        "SELECT AVG(final_percentage)
         FROM {$wpdb->prefix}nds_student_enrollments
         WHERE course_id IN ($placeholders)
         AND academic_year_id = %d AND semester_id = %d
         AND final_percentage IS NOT NULL",
        $params
    ));
    $avg_grade = $avg_grade_value !== null ? round((float) $avg_grade_value, 2) : 0;

    $total_submissions = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}nds_assessment_submissions sub
         INNER JOIN {$wpdb->prefix}nds_assessments a ON a.id = sub.assessment_id
         WHERE a.course_id IN ($placeholders)",
        $course_ids
    ));

    $graded_submissions = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}nds_assessment_submissions sub
         INNER JOIN {$wpdb->prefix}nds_assessments a ON a.id = sub.assessment_id
         WHERE a.course_id IN ($placeholders) AND sub.status = 'graded'",
        $course_ids
    ));

    $struggling = $wpdb->get_results($wpdb->prepare(
        "SELECT s.student_number, s.first_name, s.last_name, e.final_percentage, c.name AS course_name
         FROM {$wpdb->prefix}nds_student_enrollments e
         INNER JOIN {$wpdb->prefix}nds_students s ON s.id = e.student_id
         INNER JOIN {$wpdb->prefix}nds_courses c ON c.id = e.course_id
         WHERE e.course_id IN ($placeholders)
         AND e.academic_year_id = %d AND e.semester_id = %d
         AND e.final_percentage IS NOT NULL
         AND e.final_percentage < 50
         ORDER BY e.final_percentage ASC
         LIMIT 25",
        $params
    ), ARRAY_A);
}

$submission_completion_rate = $total_submissions > 0 ? round(($graded_submissions / $total_submissions) * 100, 2) : 0;
?>

<div class="space-y-6">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white border border-gray-200 rounded-lg p-4">
            <p class="text-xs text-gray-500 uppercase">Active Learners</p>
            <p class="text-2xl font-bold text-gray-900 mt-1"><?php echo esc_html($total_learners); ?></p>
        </div>
        <div class="bg-white border border-gray-200 rounded-lg p-4">
            <p class="text-xs text-gray-500 uppercase">Average Grade</p>
            <p class="text-2xl font-bold text-gray-900 mt-1"><?php echo esc_html($avg_grade); ?>%</p>
        </div>
        <div class="bg-white border border-gray-200 rounded-lg p-4">
            <p class="text-xs text-gray-500 uppercase">Submissions</p>
            <p class="text-2xl font-bold text-gray-900 mt-1"><?php echo esc_html($total_submissions); ?></p>
        </div>
        <div class="bg-white border border-gray-200 rounded-lg p-4">
            <p class="text-xs text-gray-500 uppercase">Grading Completion</p>
            <p class="text-2xl font-bold text-gray-900 mt-1"><?php echo esc_html($submission_completion_rate); ?>%</p>
        </div>
    </div>

    <div class="bg-white border border-gray-200 rounded-lg p-4">
        <h3 class="text-lg font-semibold text-gray-900 mb-3">At-Risk Learners (Below 50%)</h3>
        <?php if (empty($struggling)): ?>
            <p class="text-sm text-gray-600">No struggling learners detected from available grade data.</p>
        <?php else: ?>
            <div class="overflow-x-auto border border-gray-200 rounded-lg">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50"><tr><th class="px-3 py-2 text-left">Student</th><th class="px-3 py-2 text-left">Student #</th><th class="px-3 py-2 text-left">Course</th><th class="px-3 py-2 text-left">Final %</th></tr></thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        <?php foreach ($struggling as $row): ?>
                            <tr>
                                <td class="px-3 py-2"><?php echo esc_html(trim($row['first_name'] . ' ' . $row['last_name'])); ?></td>
                                <td class="px-3 py-2"><?php echo esc_html($row['student_number']); ?></td>
                                <td class="px-3 py-2"><?php echo esc_html($row['course_name']); ?></td>
                                <td class="px-3 py-2 text-red-700 font-semibold"><?php echo esc_html($row['final_percentage']); ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
