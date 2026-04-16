<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

$course_ids = isset($course_ids) && is_array($course_ids) ? array_values(array_map('intval', $course_ids)) : array();
$selected_assessment_id = isset($_GET['assessment_id']) ? (int) $_GET['assessment_id'] : 0;

$module_table = $wpdb->prefix . 'nds_modules';
$module_columns = $wpdb->get_col("SHOW COLUMNS FROM {$module_table}");
$module_code_col = in_array('code', $module_columns, true) ? 'code' : (in_array('module_code', $module_columns, true) ? 'module_code' : '');
$select_module_code = $module_code_col ? "m.{$module_code_col} AS module_code" : "'' AS module_code";

$modules_for_form = array();
if (!empty($course_ids)) {
    $placeholders = implode(',', array_fill(0, count($course_ids), '%d'));
    $modules_for_form = $wpdb->get_results($wpdb->prepare(
        "SELECT m.id, m.name, {$select_module_code}, m.course_id, c.name AS course_name
         FROM {$module_table} m
         INNER JOIN {$wpdb->prefix}nds_courses c ON c.id = m.course_id
         WHERE m.course_id IN ($placeholders)
         ORDER BY c.name ASC, m.name ASC",
        $course_ids
    ), ARRAY_A);
}

$assessments = array();
if (!empty($course_ids)) {
    $placeholders = implode(',', array_fill(0, count($course_ids), '%d'));
    $assessments = $wpdb->get_results($wpdb->prepare(
        "SELECT a.*, c.name AS course_name, m.name AS module_name, {$select_module_code}
         FROM {$wpdb->prefix}nds_assessments a
         INNER JOIN {$wpdb->prefix}nds_courses c ON c.id = a.course_id
         LEFT JOIN {$module_table} m ON m.id = a.module_id
         WHERE a.course_id IN ($placeholders)
         ORDER BY a.created_at DESC",
        $course_ids
    ), ARRAY_A);
}

$question_bank_items = $wpdb->get_results($wpdb->prepare(
    "SELECT qb.*, a.title AS assessment_title
     FROM {$wpdb->prefix}nds_question_bank qb
     LEFT JOIN {$wpdb->prefix}nds_assessments a ON a.id = qb.assessment_id
     WHERE qb.staff_id = %d
     ORDER BY qb.created_at DESC
     LIMIT 20",
    (int) $staff_id
), ARRAY_A);

$selected_assessment = null;
$submissions = array();
if ($selected_assessment_id > 0) {
    $selected_assessment = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}nds_assessments WHERE id = %d AND staff_id = %d",
        $selected_assessment_id,
        (int) $staff_id
    ), ARRAY_A);

    if (!empty($selected_assessment)) {
        $submissions = $wpdb->get_results($wpdb->prepare(
            "SELECT ssub.*, st.student_number, st.first_name, st.last_name
             FROM {$wpdb->prefix}nds_assessment_submissions ssub
             INNER JOIN {$wpdb->prefix}nds_students st ON st.id = ssub.student_id
             WHERE ssub.assessment_id = %d
             ORDER BY ssub.submitted_at DESC",
            $selected_assessment_id
        ), ARRAY_A);
    }
}
?>

<div class="space-y-6">
    <?php if (isset($_GET['assessment_notice'])): ?>
        <div class="p-3 rounded-lg bg-green-50 border border-green-200 text-green-800 text-sm">
            Assessment update successful.
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-lg border border-gray-200 p-5">
        <h3 class="text-lg font-semibold text-gray-900 mb-3">Create Assignment / Quiz / Exam</h3>
        <?php if (empty($courses_taught)): ?>
            <p class="text-sm text-gray-600">No assigned courses yet.</p>
        <?php else: ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <?php wp_nonce_field('nds_staff_create_assessment', 'nds_staff_create_assessment_nonce'); ?>
                <input type="hidden" name="action" value="nds_staff_create_assessment">
                <input type="hidden" name="redirect_url" value="<?php echo esc_url(nds_staff_portal_tab_url('assessments')); ?>">

                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Course</label>
                    <select id="nds-assessment-course" name="course_id" class="w-full border border-gray-300 rounded-lg px-3 py-2" required>
                        <?php foreach ($courses_taught as $course): ?>
                            <option value="<?php echo esc_attr($course['id']); ?>"><?php echo esc_html($course['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Module</label>
                    <select id="nds-assessment-module" name="module_id" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                        <option value="">Select module</option>
                        <?php foreach ($modules_for_form as $module): ?>
                            <option value="<?php echo esc_attr($module['id']); ?>" data-course-id="<?php echo esc_attr($module['course_id']); ?>">
                                <?php echo esc_html($module['course_name'] . ' - ' . $module['name'] . (!empty($module['module_code']) ? ' (' . $module['module_code'] . ')' : '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Title</label>
                    <input type="text" name="title" class="w-full border border-gray-300 rounded-lg px-3 py-2" required>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Type</label>
                    <select name="assessment_type" class="w-full border border-gray-300 rounded-lg px-3 py-2" required>
                        <option value="assignment">Assignment</option>
                        <option value="quiz">Quiz</option>
                        <option value="exam">Exam</option>
                    </select>
                </div>

                <div class="md:col-span-3">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Instructions</label>
                    <textarea name="instructions" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2"></textarea>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Due Date</label>
                    <input type="datetime-local" name="due_date" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Max Grade</label>
                    <input type="number" step="0.01" name="max_grade" value="100" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Attempts Allowed</label>
                    <input type="number" name="attempts_allowed" value="1" min="1" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Time Limit (mins)</label>
                    <input type="number" name="time_limit_minutes" min="0" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Pass %</label>
                    <input type="number" step="0.01" name="pass_percentage" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Late Penalty %</label>
                    <input type="number" step="0.01" name="late_penalty_percent" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                </div>

                <div class="md:col-span-3 flex items-center justify-between mt-2">
                    <label class="inline-flex items-center text-sm text-gray-700">
                        <input type="checkbox" name="shuffle_questions" value="1" class="mr-2">Shuffle questions
                    </label>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Save Assessment</button>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <div class="bg-white rounded-lg border border-gray-200 p-5">
        <h3 class="text-lg font-semibold text-gray-900 mb-3">Question Bank</h3>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
            <?php wp_nonce_field('nds_staff_create_question', 'nds_staff_create_question_nonce'); ?>
            <input type="hidden" name="action" value="nds_staff_create_question">
            <input type="hidden" name="redirect_url" value="<?php echo esc_url(nds_staff_portal_tab_url('assessments')); ?>">

            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Assessment (optional)</label>
                <select name="assessment_id" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <option value="">General Question Bank</option>
                    <?php foreach ($assessments as $a): ?>
                        <option value="<?php echo esc_attr($a['id']); ?>"><?php echo esc_html($a['title']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Category</label>
                <input type="text" name="category" class="w-full border border-gray-300 rounded-lg px-3 py-2" placeholder="e.g. Topic A">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Question Type</label>
                <select name="question_type" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <option value="multiple_choice">Multiple Choice</option>
                    <option value="true_false">True/False</option>
                    <option value="short_answer">Short Answer</option>
                    <option value="matching">Matching</option>
                    <option value="essay">Essay</option>
                </select>
            </div>
            <div class="md:col-span-3">
                <label class="block text-xs font-medium text-gray-700 mb-1">Question</label>
                <textarea name="question_text" required rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2"></textarea>
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs font-medium text-gray-700 mb-1">Options (one per line, optional)</label>
                <textarea name="options_json" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2" placeholder="Option 1&#10;Option 2"></textarea>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Correct Answer</label>
                <input type="text" name="correct_answer" class="w-full border border-gray-300 rounded-lg px-3 py-2">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Marks</label>
                <input type="number" step="0.01" min="0" name="marks" value="1" class="w-full border border-gray-300 rounded-lg px-3 py-2">
            </div>
            <div class="md:col-span-3 text-right">
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">Add Question</button>
            </div>
        </form>

        <div class="overflow-x-auto border border-gray-200 rounded-lg">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50"><tr><th class="px-3 py-2 text-left">Category</th><th class="px-3 py-2 text-left">Type</th><th class="px-3 py-2 text-left">Question</th><th class="px-3 py-2 text-left">Assessment</th><th class="px-3 py-2 text-left">Marks</th></tr></thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    <?php foreach ($question_bank_items as $q): ?>
                        <tr>
                            <td class="px-3 py-2"><?php echo esc_html($q['category'] ?: 'General'); ?></td>
                            <td class="px-3 py-2"><?php echo esc_html(ucwords(str_replace('_', ' ', $q['question_type']))); ?></td>
                            <td class="px-3 py-2"><?php echo esc_html(wp_trim_words($q['question_text'], 10)); ?></td>
                            <td class="px-3 py-2"><?php echo esc_html($q['assessment_title'] ?: 'Bank'); ?></td>
                            <td class="px-3 py-2"><?php echo esc_html($q['marks']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-white rounded-lg border border-gray-200 p-5">
        <h3 class="text-lg font-semibold text-gray-900 mb-3">Assessments and Submissions</h3>
        <div class="overflow-x-auto border border-gray-200 rounded-lg mb-4">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50"><tr><th class="px-3 py-2 text-left">Title</th><th class="px-3 py-2 text-left">Type</th><th class="px-3 py-2 text-left">Course</th><th class="px-3 py-2 text-left">Module</th><th class="px-3 py-2 text-left">Due</th><th class="px-3 py-2 text-left">Max</th><th class="px-3 py-2 text-left">Action</th></tr></thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    <?php foreach ($assessments as $a): ?>
                        <tr>
                            <td class="px-3 py-2"><?php echo esc_html($a['title']); ?></td>
                            <td class="px-3 py-2"><?php echo esc_html(ucfirst($a['assessment_type'])); ?></td>
                            <td class="px-3 py-2"><?php echo esc_html($a['course_name']); ?></td>
                            <td class="px-3 py-2"><?php echo esc_html($a['module_name'] ?: 'All modules / course-wide'); ?></td>
                            <td class="px-3 py-2"><?php echo !empty($a['due_date']) ? esc_html(date_i18n('Y-m-d H:i', strtotime($a['due_date']))) : '-'; ?></td>
                            <td class="px-3 py-2"><?php echo esc_html($a['max_grade']); ?></td>
                            <td class="px-3 py-2"><a class="text-blue-600 hover:text-blue-800" href="<?php echo esc_url(nds_staff_portal_tab_url('assessments') . '&assessment_id=' . (int) $a['id']); ?>">View Submissions</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if (!empty($selected_assessment)): ?>
            <h4 class="text-md font-semibold text-gray-900 mb-2">Grade Submissions: <?php echo esc_html($selected_assessment['title']); ?></h4>
            <?php if (empty($submissions)): ?>
                <p class="text-sm text-gray-600">No submissions yet.</p>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($submissions as $submission): ?>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="border border-gray-200 rounded-lg p-3 grid grid-cols-1 md:grid-cols-6 gap-2">
                            <?php wp_nonce_field('nds_staff_grade_submission', 'nds_staff_grade_submission_nonce'); ?>
                            <input type="hidden" name="action" value="nds_staff_grade_submission">
                            <input type="hidden" name="submission_id" value="<?php echo esc_attr($submission['id']); ?>">
                            <input type="hidden" name="redirect_url" value="<?php echo esc_url(nds_staff_portal_tab_url('assessments') . '&assessment_id=' . (int) $selected_assessment_id); ?>">
                            <div class="md:col-span-2 text-sm text-gray-700">
                                <div class="font-medium"><?php echo esc_html(trim($submission['first_name'] . ' ' . $submission['last_name'])); ?></div>
                                <div><?php echo esc_html($submission['student_number']); ?></div>
                            </div>
                            <div class="md:col-span-2">
                                <label class="text-xs text-gray-600">Feedback</label>
                                <textarea name="feedback" rows="2" class="w-full border border-gray-300 rounded px-2 py-1 text-sm"><?php echo esc_textarea($submission['feedback']); ?></textarea>
                            </div>
                            <div>
                                <label class="text-xs text-gray-600">Score</label>
                                <input type="number" step="0.01" name="score" value="<?php echo esc_attr($submission['score']); ?>" class="w-full border border-gray-300 rounded px-2 py-1 text-sm">
                            </div>
                            <div class="flex items-end">
                                <button type="submit" class="px-3 py-2 bg-green-600 text-white rounded hover:bg-green-700 text-sm">Save Grade</button>
                            </div>
                        </form>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var courseSelect = document.getElementById('nds-assessment-course');
    var moduleSelect = document.getElementById('nds-assessment-module');

    if (!courseSelect || !moduleSelect) {
        return;
    }

    function syncAssessmentModules() {
        var selectedCourseId = courseSelect.value;
        var options = moduleSelect.querySelectorAll('option');
        var hasVisibleOption = false;

        options.forEach(function (option, index) {
            if (index === 0) {
                option.hidden = false;
                return;
            }

            var matchesCourse = option.getAttribute('data-course-id') === selectedCourseId;
            option.hidden = !matchesCourse;
            if (matchesCourse) {
                hasVisibleOption = true;
            }
            if (!matchesCourse && option.selected) {
                moduleSelect.value = '';
            }
        });

        options[0].textContent = hasVisibleOption ? 'Select module' : 'No modules for selected course';
    }

    courseSelect.addEventListener('change', syncAssessmentModules);
    syncAssessmentModules();
});
</script>
