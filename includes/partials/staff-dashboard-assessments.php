<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

$modules_table = $wpdb->prefix . 'nds_modules';
$module_columns = $wpdb->get_col("SHOW COLUMNS FROM {$modules_table}", 0);
$module_code_col = in_array('module_code', $module_columns, true)
    ? 'module_code'
    : (in_array('code', $module_columns, true) ? 'code' : null);
$module_code_expr = $module_code_col ? "m.{$module_code_col} AS module_code" : "'' AS module_code";

$course_ids = isset($course_ids) && is_array($course_ids) ? array_values(array_map('intval', $course_ids)) : array();
$selected_assessment_id = isset($_GET['assessment_id']) ? (int) $_GET['assessment_id'] : 0;
$selected_content_course_filter = isset($_GET['content_course_id']) ? (int) $_GET['content_course_id'] : 0;
$selected_content_module_filter = isset($_GET['content_module_id']) ? (int) $_GET['content_module_id'] : 0;
$selected_content_status_filter = isset($_GET['content_submission_status']) ? sanitize_key((string) wp_unslash($_GET['content_submission_status'])) : '';

$assessments_tab_url = nds_staff_portal_tab_url('assessments');
$content_filter_query = array();
if ($selected_content_course_filter > 0) {
    $content_filter_query['content_course_id'] = $selected_content_course_filter;
}
if ($selected_content_module_filter > 0) {
    $content_filter_query['content_module_id'] = $selected_content_module_filter;
}
if ($selected_content_status_filter !== '') {
    $content_filter_query['content_submission_status'] = $selected_content_status_filter;
}
$assessments_tab_filtered_url = !empty($content_filter_query)
    ? add_query_arg($content_filter_query, $assessments_tab_url)
    : $assessments_tab_url;

$modules_for_form = array();
if (!empty($course_ids)) {
    $placeholders = implode(',', array_fill(0, count($course_ids), '%d'));
    $modules_for_form = $wpdb->get_results($wpdb->prepare(
        "SELECT m.id, m.name, {$module_code_expr}, m.course_id, c.name AS course_name
         FROM {$wpdb->prefix}nds_modules m
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
        "SELECT a.*, c.name AS course_name, m.name AS module_name, {$module_code_expr}
         FROM {$wpdb->prefix}nds_assessments a
         INNER JOIN {$wpdb->prefix}nds_courses c ON c.id = a.course_id
         LEFT JOIN {$wpdb->prefix}nds_modules m ON m.id = a.module_id
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

$quiz_attempt_rows = array();
if (!empty($course_ids) && function_exists('nds_portal_ensure_quiz_attempts_table')) {
    $quiz_attempts_table = nds_portal_ensure_quiz_attempts_table();
    $placeholders = implode(',', array_fill(0, count($course_ids), '%d'));

    $quiz_attempt_rows = $wpdb->get_results($wpdb->prepare(
        "SELECT qa.id, qa.attempt_no, qa.total_questions, qa.graded_questions, qa.correct_answers,
                qa.score_percent, qa.submitted_at,
                st.student_number, st.first_name, st.last_name,
                lc.id AS content_id, lc.title AS quiz_title, lc.min_grade_required,
                c.name AS course_name,
                m.name AS module_name
         FROM {$quiz_attempts_table} qa
         INNER JOIN {$wpdb->prefix}nds_lecturer_content lc ON lc.id = qa.content_id
         INNER JOIN {$wpdb->prefix}nds_students st ON st.id = qa.student_id
         LEFT JOIN {$wpdb->prefix}nds_courses c ON c.id = qa.course_id
         LEFT JOIN {$wpdb->prefix}nds_modules m ON m.id = qa.module_id
         WHERE lc.staff_id = %d
           AND qa.course_id IN ({$placeholders})
         ORDER BY qa.submitted_at DESC
         LIMIT 100",
        array_merge(array((int) $staff_id), $course_ids)
    ), ARRAY_A);
}

$content_assignment_submission_rows = array();
if (!empty($course_ids) && function_exists('nds_portal_ensure_assignment_submissions_table')) {
    $assignment_submissions_table = nds_portal_ensure_assignment_submissions_table();
    $filtered_course_ids = $course_ids;
    if ($selected_content_course_filter > 0 && in_array($selected_content_course_filter, $course_ids, true)) {
        $filtered_course_ids = array($selected_content_course_filter);
    }

    if (!empty($filtered_course_ids)) {
        $where_clauses = array('lc.staff_id = %d');
        $query_params = array((int) $staff_id);

        $course_placeholders = implode(',', array_fill(0, count($filtered_course_ids), '%d'));
        $where_clauses[] = "cs.course_id IN ({$course_placeholders})";
        $query_params = array_merge($query_params, $filtered_course_ids);

        if ($selected_content_module_filter > 0) {
            $where_clauses[] = 'cs.module_id = %d';
            $query_params[] = $selected_content_module_filter;
        }

        if ($selected_content_status_filter !== '' && in_array($selected_content_status_filter, array('submitted', 'late', 'graded'), true)) {
            $where_clauses[] = 'cs.status = %s';
            $query_params[] = $selected_content_status_filter;
        }

        $where_sql = implode(' AND ', $where_clauses);

        $content_assignment_submission_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT cs.id, cs.content_id, cs.attempt_no, cs.submitted_text, cs.submission_link, cs.file_url,
                    cs.file_name, cs.file_size, cs.score, cs.feedback, cs.status, cs.submitted_at, cs.graded_at,
                    st.student_number, st.first_name, st.last_name,
                    lc.title AS assignment_title,
                    c.name AS course_name,
                    m.name AS module_name
             FROM {$assignment_submissions_table} cs
             INNER JOIN {$wpdb->prefix}nds_lecturer_content lc ON lc.id = cs.content_id
             INNER JOIN {$wpdb->prefix}nds_students st ON st.id = cs.student_id
             LEFT JOIN {$wpdb->prefix}nds_courses c ON c.id = cs.course_id
             LEFT JOIN {$wpdb->prefix}nds_modules m ON m.id = cs.module_id
             WHERE {$where_sql}
             ORDER BY cs.submitted_at DESC
             LIMIT 100",
            $query_params
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

    <div class="bg-white rounded-lg border border-gray-200 p-5">
        <h3 class="text-lg font-semibold text-gray-900 mb-3">Learner Quiz Attempts (Content Quizzes)</h3>

        <?php if (empty($quiz_attempt_rows)) : ?>
            <p class="text-sm text-gray-600">No learner quiz attempts yet.</p>
        <?php else : ?>
            <div class="overflow-x-auto border border-gray-200 rounded-lg">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left">Student</th>
                            <th class="px-3 py-2 text-left">Quiz</th>
                            <th class="px-3 py-2 text-left">Course / Module</th>
                            <th class="px-3 py-2 text-left">Attempt</th>
                            <th class="px-3 py-2 text-left">Score</th>
                            <th class="px-3 py-2 text-left">Status</th>
                            <th class="px-3 py-2 text-left">Submitted</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        <?php foreach ($quiz_attempt_rows as $qa_row) : ?>
                            <?php
                            $score = isset($qa_row['score_percent']) ? (float) $qa_row['score_percent'] : null;
                            $graded_questions = (int) ($qa_row['graded_questions'] ?? 0);
                            $threshold = isset($qa_row['min_grade_required']) && $qa_row['min_grade_required'] !== null
                                ? max(0.0, min(100.0, (float) $qa_row['min_grade_required']))
                                : 50.0;
                            $has_auto_score = $score !== null && $graded_questions > 0;
                            $is_pass = $has_auto_score && $score >= $threshold;
                            ?>
                            <tr>
                                <td class="px-3 py-2">
                                    <div class="font-medium text-gray-800"><?php echo esc_html(trim(($qa_row['first_name'] ?? '') . ' ' . ($qa_row['last_name'] ?? ''))); ?></div>
                                    <div class="text-xs text-gray-500"><?php echo esc_html($qa_row['student_number'] ?? ''); ?></div>
                                </td>
                                <td class="px-3 py-2"><?php echo esc_html($qa_row['quiz_title'] ?? 'Quiz'); ?></td>
                                <td class="px-3 py-2">
                                    <div><?php echo esc_html($qa_row['course_name'] ?? '-'); ?></div>
                                    <div class="text-xs text-gray-500"><?php echo esc_html($qa_row['module_name'] ?? '-'); ?></div>
                                </td>
                                <td class="px-3 py-2">#<?php echo (int) ($qa_row['attempt_no'] ?? 0); ?></td>
                                <td class="px-3 py-2">
                                    <?php if ($has_auto_score) : ?>
                                        <?php echo esc_html(number_format($score, 2)); ?>%
                                        <span class="text-xs text-gray-500">(<?php echo (int) ($qa_row['correct_answers'] ?? 0); ?>/<?php echo (int) ($qa_row['graded_questions'] ?? 0); ?>)</span>
                                    <?php else : ?>
                                        <span class="text-xs text-amber-700">Pending review</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-3 py-2">
                                    <?php if ($has_auto_score) : ?>
                                        <span class="text-[11px] font-semibold px-2 py-1 rounded <?php echo $is_pass ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'; ?>">
                                            <?php echo $is_pass ? 'Pass' : 'Fail'; ?>
                                        </span>
                                    <?php else : ?>
                                        <span class="text-[11px] font-semibold px-2 py-1 rounded bg-amber-100 text-amber-700">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-3 py-2"><?php echo esc_html(date_i18n('Y-m-d H:i', strtotime((string) ($qa_row['submitted_at'] ?? 'now')))); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="bg-white rounded-lg border border-gray-200 p-5">
        <h3 class="text-lg font-semibold text-gray-900 mb-3">Learner Assignment Submissions (Content Assignments)</h3>

        <form method="get" action="<?php echo esc_url(home_url('/staff-portal/')); ?>" class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-4">
            <input type="hidden" name="tab" value="assessments">

            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Course</label>
                <select name="content_course_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">All courses</option>
                    <?php foreach ($courses_taught as $course) : ?>
                        <option value="<?php echo (int) $course['id']; ?>" <?php selected($selected_content_course_filter, (int) $course['id']); ?>>
                            <?php echo esc_html($course['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Module</label>
                <select name="content_module_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">All modules</option>
                    <?php foreach ($modules_for_form as $module) : ?>
                        <?php
                        if ($selected_content_course_filter > 0 && (int) $module['course_id'] !== $selected_content_course_filter) {
                            continue;
                        }
                        ?>
                        <option value="<?php echo (int) $module['id']; ?>" <?php selected($selected_content_module_filter, (int) $module['id']); ?>>
                            <?php echo esc_html($module['course_name'] . ' - ' . $module['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Status</label>
                <select name="content_submission_status" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">All statuses</option>
                    <option value="submitted" <?php selected($selected_content_status_filter, 'submitted'); ?>>Submitted</option>
                    <option value="late" <?php selected($selected_content_status_filter, 'late'); ?>>Late</option>
                    <option value="graded" <?php selected($selected_content_status_filter, 'graded'); ?>>Graded</option>
                </select>
            </div>

            <div class="flex items-end gap-2">
                <button type="submit" class="px-4 py-2 bg-slate-800 text-white rounded-lg hover:bg-slate-900 text-sm">Filter</button>
                <a href="<?php echo esc_url($assessments_tab_url); ?>" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50">Reset</a>
            </div>
        </form>

        <?php if (empty($content_assignment_submission_rows)) : ?>
            <p class="text-sm text-gray-600">No learner assignment submissions yet.</p>
        <?php else : ?>
            <div class="space-y-3">
                <?php foreach ($content_assignment_submission_rows as $submission_row) : ?>
                    <?php
                    $submission_status = sanitize_key((string) ($submission_row['status'] ?? 'submitted'));
                    $status_class = $submission_status === 'graded'
                        ? 'bg-emerald-100 text-emerald-700'
                        : ($submission_status === 'late' ? 'bg-amber-100 text-amber-700' : 'bg-blue-100 text-blue-700');
                    ?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="border border-gray-200 rounded-lg p-4 grid grid-cols-1 lg:grid-cols-8 gap-3">
                        <?php wp_nonce_field('nds_staff_grade_content_assignment_submission', 'nds_staff_grade_content_assignment_submission_nonce'); ?>
                        <input type="hidden" name="action" value="nds_staff_grade_content_assignment_submission">
                        <input type="hidden" name="content_submission_id" value="<?php echo esc_attr($submission_row['id']); ?>">
                        <input type="hidden" name="redirect_url" value="<?php echo esc_url($assessments_tab_filtered_url); ?>">

                        <div class="lg:col-span-2 text-sm text-gray-700">
                            <div class="font-semibold text-gray-900"><?php echo esc_html(trim((string) ($submission_row['first_name'] ?? '') . ' ' . (string) ($submission_row['last_name'] ?? ''))); ?></div>
                            <div class="text-xs text-gray-500"><?php echo esc_html($submission_row['student_number'] ?? ''); ?></div>
                            <div class="mt-2 text-xs text-gray-600"><?php echo esc_html($submission_row['assignment_title'] ?? 'Assignment'); ?></div>
                            <div class="text-[11px] text-gray-500"><?php echo esc_html($submission_row['course_name'] ?? '-'); ?><?php if (!empty($submission_row['module_name'])) : ?> · <?php echo esc_html($submission_row['module_name']); ?><?php endif; ?></div>
                            <div class="mt-2 text-[11px] text-gray-500">Attempt #<?php echo (int) ($submission_row['attempt_no'] ?? 0); ?> · <?php echo esc_html(date_i18n('Y-m-d H:i', strtotime((string) ($submission_row['submitted_at'] ?? 'now')))); ?></div>
                            <div class="mt-1"><span class="text-[11px] font-semibold px-2 py-1 rounded <?php echo esc_attr($status_class); ?>"><?php echo esc_html(ucfirst(str_replace('_', ' ', $submission_status))); ?></span></div>
                        </div>

                        <div class="lg:col-span-3 text-sm text-gray-700 space-y-2">
                            <?php if (!empty($submission_row['submitted_text'])) : ?>
                                <div class="rounded border border-gray-200 bg-gray-50 p-2 whitespace-pre-wrap"><?php echo esc_html((string) $submission_row['submitted_text']); ?></div>
                            <?php else : ?>
                                <div class="text-xs text-gray-500">No submission note.</div>
                            <?php endif; ?>

                            <div class="flex flex-wrap items-center gap-3 text-xs">
                                <?php if (!empty($submission_row['submission_link'])) : ?>
                                    <a href="<?php echo esc_url($submission_row['submission_link']); ?>" target="_blank" rel="noopener" class="text-blue-600 hover:underline font-medium">Open submitted link</a>
                                <?php endif; ?>
                                <?php if (!empty($submission_row['file_url'])) : ?>
                                    <a href="<?php echo esc_url($submission_row['file_url']); ?>" target="_blank" rel="noopener" class="text-orange-600 hover:underline font-medium">Open uploaded file<?php echo !empty($submission_row['file_name']) ? ': ' . esc_html((string) $submission_row['file_name']) : ''; ?></a>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="lg:col-span-2 space-y-2">
                            <div>
                                <label class="text-xs text-gray-600">Feedback</label>
                                <textarea name="feedback" rows="3" class="w-full border border-gray-300 rounded px-2 py-1 text-sm"><?php echo esc_textarea((string) ($submission_row['feedback'] ?? '')); ?></textarea>
                            </div>
                            <div>
                                <label class="text-xs text-gray-600">Score</label>
                                <input type="number" step="0.01" name="score" value="<?php echo esc_attr($submission_row['score']); ?>" class="w-full border border-gray-300 rounded px-2 py-1 text-sm" placeholder="e.g. 85">
                            </div>
                            <div class="flex items-end justify-end pt-1">
                                <button type="submit" class="px-3 py-2 bg-green-600 text-white rounded hover:bg-green-700 text-sm">Save Review</button>
                            </div>
                        </div>
                    </form>
                <?php endforeach; ?>
            </div>
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
