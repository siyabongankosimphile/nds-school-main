<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

$staff_id = isset($staff_id) ? (int) $staff_id : (int) nds_portal_get_current_staff_id();
$content_table = $wpdb->prefix . 'nds_lecturer_content';

$notice = isset($_GET['content_notice']) ? sanitize_text_field(wp_unslash($_GET['content_notice'])) : '';
$error = isset($_GET['content_error']) ? sanitize_text_field(wp_unslash($_GET['content_error'])) : '';

$course_ids = function_exists('nds_staff_get_lecturer_course_ids') ? nds_staff_get_lecturer_course_ids($staff_id) : array();
$modules_for_form = array();
$module_table = $wpdb->prefix . 'nds_modules';
$module_columns = $wpdb->get_col("SHOW COLUMNS FROM {$module_table}");
$module_code_col = in_array('code', $module_columns, true) ? 'code' : (in_array('module_code', $module_columns, true) ? 'module_code' : '');
if (!empty($course_ids)) {
    $select_module_code = $module_code_col ? "m.{$module_code_col} AS module_code" : "'' AS module_code";

    $placeholders = implode(',', array_fill(0, count($course_ids), '%d'));
    $modules_for_form = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT m.id, m.name, {$select_module_code}, c.id AS course_id, c.name AS course_name
             FROM {$module_table} m
             INNER JOIN {$wpdb->prefix}nds_courses c ON c.id = m.course_id
             WHERE m.course_id IN ($placeholders)
             ORDER BY c.name ASC, m.name ASC",
            $course_ids
        ),
        ARRAY_A
    );
}

$courses_for_form = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT c.id, c.name, c.code
         FROM {$wpdb->prefix}nds_courses c
         INNER JOIN {$wpdb->prefix}nds_course_lecturers cl ON cl.course_id = c.id
         WHERE cl.lecturer_id = %d
         ORDER BY c.name ASC",
        $staff_id
    ),
    ARRAY_A
);

$items = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT lc.*, c.name AS course_name, m.name AS module_name, " . ($module_code_col ? "m.{$module_code_col}" : "''") . " AS module_code
         FROM {$content_table} lc
         LEFT JOIN {$wpdb->prefix}nds_courses c ON c.id = lc.course_id
         LEFT JOIN {$module_table} m ON m.id = lc.module_id
         WHERE lc.staff_id = %d
         ORDER BY lc.created_at DESC
         LIMIT 50",
        $staff_id
    ),
    ARRAY_A
);

$edit_content_id = isset($_GET['edit_content_id']) ? (int) $_GET['edit_content_id'] : 0;
$edit_item = null;
if ($edit_content_id > 0) {
    $edit_item = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$content_table} WHERE id = %d AND staff_id = %d LIMIT 1",
        $edit_content_id,
        $staff_id
    ), ARRAY_A);
}

$type_labels = array(
    'study_material' => 'Study Material',
    'assignment' => 'Assignment',
    'quiz' => 'Quiz',
    'online_course' => 'Online Course',
    'announcement' => 'Announcement',
);
?>

<div class="space-y-6">
    <?php if ($notice === 'created') : ?>
        <div class="p-4 rounded-lg border border-green-200 bg-green-50 text-green-800 text-sm">
            Content published successfully and notifications were sent to enrolled students.
        </div>
    <?php elseif ($notice === 'updated') : ?>
        <div class="p-4 rounded-lg border border-green-200 bg-green-50 text-green-800 text-sm">
            Content updated successfully.
        </div>
    <?php elseif ($notice === 'deleted') : ?>
        <div class="p-4 rounded-lg border border-green-200 bg-green-50 text-green-800 text-sm">
            Content deleted successfully.
        </div>
    <?php endif; ?>

    <?php if (!empty($error)) : ?>
        <div class="p-4 rounded-lg border border-red-200 bg-red-50 text-red-800 text-sm">
            <?php
            $error_map = array(
                'permission' => 'You do not have permission to publish content for this module.',
                'missing_fields' => 'Please complete all required fields.',
                'invalid_module' => 'Please select a valid module.',
                'upload_failed' => 'File upload failed. Please try again.',
                'save_failed' => 'Could not save content. Please try again.',
                'security' => 'Security validation failed. Please reload and try again.',
            );
            echo esc_html($error_map[$error] ?? 'An unexpected error occurred.');
            ?>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="p-6 border-b border-gray-200">
            <h3 class="text-xl font-semibold text-gray-900 flex items-center">
                <i class="fas fa-upload text-blue-600 mr-2"></i><?php echo !empty($edit_item) ? 'Edit Published Content' : 'Publish Teaching Content'; ?>
            </h3>
            <p class="text-sm text-gray-600 mt-1">Upload study materials, assignments, quizzes, online course links, or announcements. Modules load automatically from the qualifications you teach.</p>
        </div>

        <div class="p-6">
            <?php if (empty($courses_for_form)) : ?>
                <div class="text-sm text-gray-600">No qualifications are assigned to you yet.</div>
            <?php else : ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php if (!empty($edit_item)) : ?>
                        <?php wp_nonce_field('nds_staff_update_content', 'nds_staff_update_content_nonce'); ?>
                        <input type="hidden" name="action" value="nds_staff_update_content">
                        <input type="hidden" name="content_id" value="<?php echo esc_attr($edit_item['id']); ?>">
                    <?php else : ?>
                        <?php wp_nonce_field('nds_staff_create_content', 'nds_staff_create_content_nonce'); ?>
                        <input type="hidden" name="action" value="nds_staff_create_content">
                    <?php endif; ?>
                    <input type="hidden" name="redirect_url" value="<?php echo esc_url(nds_staff_portal_tab_url('content')); ?>">

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Course *</label>
                        <select id="nds-content-course" name="course_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                            <option value="">Select course</option>
                            <?php foreach ($courses_for_form as $course_opt) : ?>
                                <option value="<?php echo esc_attr($course_opt['id']); ?>" <?php selected((int) ($edit_item['course_id'] ?? 0), (int) $course_opt['id']); ?>>
                                    <?php echo esc_html($course_opt['name'] . (!empty($course_opt['code']) ? ' (' . $course_opt['code'] . ')' : '')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Module *</label>
                        <select id="nds-content-module" name="module_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                            <option value="">Select module</option>
                            <?php foreach ($modules_for_form as $mod_opt) : ?>
                                <option value="<?php echo esc_attr($mod_opt['id']); ?>" data-course-id="<?php echo esc_attr($mod_opt['course_id']); ?>" <?php selected((int) ($edit_item['module_id'] ?? 0), (int) $mod_opt['id']); ?>>
                                    <?php echo esc_html($mod_opt['course_name'] . ' - ' . $mod_opt['name'] . ' (' . $mod_opt['module_code'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="mt-1 text-xs text-gray-500">Only modules from the selected qualification will be shown.</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Content Type *</label>
                        <select id="content_type" name="content_type" required class="w-full px-3 py-2 border border-gray-300 rounded-lg" onchange="ndsToggleDueDateField(this.value)">
                            <option value="study_material" <?php selected(($edit_item['content_type'] ?? 'study_material'), 'study_material'); ?>>Study Material</option>
                            <option value="assignment" <?php selected(($edit_item['content_type'] ?? ''), 'assignment'); ?>>Assignment</option>
                            <option value="quiz" <?php selected(($edit_item['content_type'] ?? ''), 'quiz'); ?>>Quiz</option>
                            <option value="online_course" <?php selected(($edit_item['content_type'] ?? ''), 'online_course'); ?>>Online Course</option>
                            <option value="announcement" <?php selected(($edit_item['content_type'] ?? ''), 'announcement'); ?>>Announcement</option>
                        </select>
                    </div>

                    <div id="due-date-wrap" class="hidden">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Assignment Due Date *</label>
                        <input id="due_date" type="date" name="due_date" value="<?php echo !empty($edit_item['due_date']) ? esc_attr(date('Y-m-d', strtotime($edit_item['due_date']))) : ''; ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Title *</label>
                        <input type="text" name="title" value="<?php echo esc_attr($edit_item['title'] ?? ''); ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="e.g. Week 1 Reading Pack">
                    </div>

                    <div class="md:col-span-2">
                        <label id="nds-desc-label" class="block text-sm font-medium text-gray-700 mb-1">Description *</label>
                        <textarea id="nds-description" name="description" rows="4" class="w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="Write details students should see..."><?php echo esc_textarea($edit_item['description'] ?? ''); ?></textarea>
                    </div>

                    <!-- Quiz Question Builder (shown only when Quiz is selected) -->
                    <div id="nds-quiz-builder" class="md:col-span-2 hidden">
                        <div class="border border-indigo-200 rounded-xl bg-indigo-50 p-5">

                            <!-- Header -->
                            <div class="flex items-center justify-between mb-3">
                                <div>
                                    <h4 class="text-sm font-semibold text-indigo-900">Quiz Questions</h4>
                                    <p class="text-xs text-indigo-500 mt-0.5">Click a number to go to that question. Only one question is shown at a time.</p>
                                </div>
                                <button type="button" onclick="ndsAddQuestion()" class="flex items-center gap-1.5 px-3 py-1.5 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700">
                                    <i class="fas fa-plus text-xs"></i> Add Question
                                </button>
                            </div>

                            <!-- Numbered question tabs -->
                            <div id="nds-q-tabs" class="flex flex-wrap gap-2 mb-4 min-h-8">
                                <p id="nds-q-empty" class="text-sm text-indigo-400 italic">No questions yet. Click &ldquo;Add Question&rdquo; to start.</p>
                            </div>

                            <!-- Active question card -->
                            <div id="nds-q-card"></div>

                            <!-- Prev / Next navigation -->
                            <div id="nds-q-nav" class="flex items-center justify-between mt-4 pt-3 border-t border-indigo-200" style="display:none">
                                <button type="button" id="nds-prev-btn" onclick="ndsNavQ(-1)" class="flex items-center gap-1 px-3 py-1.5 text-sm text-indigo-700 border border-indigo-300 rounded-lg hover:bg-indigo-100 disabled:opacity-40 disabled:cursor-not-allowed">&larr; Previous</button>
                                <span id="nds-q-counter" class="text-xs text-indigo-500 font-medium"></span>
                                <button type="button" id="nds-next-btn" onclick="ndsNavQ(1)" class="flex items-center gap-1 px-3 py-1.5 text-sm text-indigo-700 border border-indigo-300 rounded-lg hover:bg-indigo-100 disabled:opacity-40 disabled:cursor-not-allowed">Next &rarr;</button>
                            </div>

                        </div>
                        <input type="hidden" name="quiz_data" id="nds-quiz-data" value="<?php echo esc_attr($edit_item['quiz_data'] ?? '[]'); ?>">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">External Link (Optional)</label>
                        <input type="url" name="resource_url" value="<?php echo esc_attr($edit_item['resource_url'] ?? ''); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="https://...">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Upload File (Optional)</label>
                        <input type="file" name="attachment_file" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Access Start (Optional)</label>
                        <input type="datetime-local" name="access_start" value="<?php echo !empty($edit_item['access_start']) ? esc_attr(date('Y-m-d\TH:i', strtotime($edit_item['access_start']))) : ''; ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Access End (Optional)</label>
                        <input type="datetime-local" name="access_end" value="<?php echo !empty($edit_item['access_end']) ? esc_attr(date('Y-m-d\TH:i', strtotime($edit_item['access_end']))) : ''; ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Minimum Grade Required (Optional %)</label>
                        <input type="number" step="0.01" min="0" max="100" name="min_grade_required" value="<?php echo isset($edit_item['min_grade_required']) ? esc_attr($edit_item['min_grade_required']) : ''; ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    </div>

                    <div class="md:col-span-2 flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <label class="inline-flex items-center text-sm text-gray-700">
                                <input type="checkbox" name="is_visible" value="1" <?php checked(!empty($edit_item) ? (int) $edit_item['is_visible'] : 1, 1); ?> class="mr-2">
                                Visible to students
                            </label>
                            <label class="inline-flex items-center text-sm text-gray-700">
                                <input type="checkbox" name="completion_required" value="1" <?php checked((int) ($edit_item['completion_required'] ?? 0), 1); ?> class="mr-2">
                                Completion required
                            </label>
                        </div>
                        <label class="inline-flex items-center text-sm text-gray-700">
                            <input type="checkbox" name="notify_students" value="1" checked class="mr-2">
                            Notify enrolled students
                        </label>
                        <div class="flex items-center gap-2">
                            <?php if (!empty($edit_item)) : ?>
                                <a href="<?php echo esc_url(nds_staff_portal_tab_url('content')); ?>" class="inline-flex items-center px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">Cancel Edit</a>
                            <?php endif; ?>
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                <i class="fas fa-paper-plane mr-2"></i><?php echo !empty($edit_item) ? 'Update Content' : 'Publish Content'; ?>
                            </button>
                        </div>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="p-6 border-b border-gray-200">
            <h3 class="text-xl font-semibold text-gray-900 flex items-center">
                <i class="fas fa-list text-green-600 mr-2"></i>Published Items
            </h3>
        </div>

        <div class="p-6">
            <?php if (empty($items)) : ?>
                <p class="text-sm text-gray-600">No content published yet.</p>
            <?php else : ?>
                <div class="space-y-4">
                    <?php foreach ($items as $item) : ?>
                        <div class="border border-gray-200 rounded-lg p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h4 class="font-semibold text-gray-900"><?php echo esc_html($item['title']); ?></h4>
                                    <p class="text-xs text-gray-500 mt-1">
                                        <?php echo esc_html($type_labels[$item['content_type']] ?? ucfirst(str_replace('_', ' ', $item['content_type']))); ?>
                                        • <?php echo !empty($item['module_name']) ? esc_html($item['module_name'] . ' (' . $item['module_code'] . ')') : esc_html($item['course_name'] ?? 'Unknown'); ?>
                                        <?php if (!empty($item['due_date']) && $item['content_type'] === 'assignment') : ?>
                                            • Due: <?php echo esc_html(date_i18n('j M Y', strtotime($item['due_date']))); ?>
                                        <?php endif; ?>
                                        • <?php echo esc_html(date_i18n('Y-m-d H:i', strtotime($item['created_at']))); ?>
                                    </p>
                                </div>
                            </div>
                            <p class="text-sm text-gray-700 mt-2"><?php echo esc_html(wp_trim_words($item['description'], 30)); ?></p>

                            <?php if ($item['content_type'] === 'quiz' && !empty($item['quiz_data'])) :
                                $quiz_qs = json_decode($item['quiz_data'], true);
                                $quiz_count = is_array($quiz_qs) ? count($quiz_qs) : 0;
                                if ($quiz_count > 0) : ?>
                                <div class="mt-3 border border-indigo-100 rounded-lg bg-indigo-50 p-3">
                                    <div class="text-xs font-semibold text-indigo-700 mb-2">
                                        <i class="fas fa-question-circle mr-1"></i><?php echo esc_html($quiz_count); ?> Question<?php echo $quiz_count !== 1 ? 's' : ''; ?>
                                    </div>
                                    <ol class="space-y-2">
                                        <?php foreach ($quiz_qs as $qi => $q) : ?>
                                            <li class="text-sm">
                                                <span class="font-medium text-gray-800"><?php echo esc_html(($qi + 1) . '. ' . $q['text']); ?></span>
                                                <?php if (($q['type'] ?? '') === 'multiple_choice') : ?>
                                                    <div class="flex flex-wrap gap-x-4 gap-y-1 mt-1">
                                                        <?php $letters = array('A','B','C','D'); foreach ($letters as $li => $letter) :
                                                            if (empty($q['options'][$li])) continue;
                                                            $is_correct = ($q['correct'] ?? '') === $letter; ?>
                                                            <span class="text-xs <?php echo $is_correct ? 'text-green-700 font-semibold' : 'text-gray-500'; ?>">
                                                                <?php echo esc_html($letter . '. ' . $q['options'][$li]); ?>
                                                                <?php if ($is_correct) : ?><i class="fas fa-check text-green-600 ml-0.5"></i><?php endif; ?>
                                                            </span>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php elseif (!empty($q['model_answer'])) : ?>
                                                    <div class="text-xs text-gray-500 mt-0.5 italic">Model: <?php echo esc_html(wp_trim_words($q['model_answer'], 15)); ?></div>
                                                <?php endif; ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ol>
                                </div>
                                <?php endif; ?>
                            <?php endif; ?>
                            <div class="mt-3 flex items-center gap-4 text-sm">
                                <?php if (!empty($item['resource_url'])) : ?>
                                    <a href="<?php echo esc_url($item['resource_url']); ?>" target="_blank" rel="noopener" class="text-blue-600 hover:text-blue-800">
                                        <i class="fas fa-link mr-1"></i>Open Link
                                    </a>
                                <?php endif; ?>
                                <?php if (!empty($item['attachment_url'])) : ?>
                                    <a href="<?php echo esc_url($item['attachment_url']); ?>" target="_blank" rel="noopener" class="text-blue-600 hover:text-blue-800">
                                        <i class="fas fa-file-download mr-1"></i>Download File
                                    </a>
                                <?php endif; ?>
                                <a href="<?php echo esc_url(add_query_arg('edit_content_id', (int) $item['id'], nds_staff_portal_tab_url('content'))); ?>" class="text-indigo-600 hover:text-indigo-800">
                                    <i class="fas fa-pen mr-1"></i>Edit
                                </a>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Delete this content item?');" class="inline">
                                    <?php wp_nonce_field('nds_staff_delete_content', 'nds_staff_delete_content_nonce'); ?>
                                    <input type="hidden" name="action" value="nds_staff_delete_content">
                                    <input type="hidden" name="content_id" value="<?php echo esc_attr($item['id']); ?>">
                                    <button type="submit" class="text-red-600 hover:text-red-800"><i class="fas fa-trash mr-1"></i>Delete</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function ndsToggleDueDateField(contentType) {
    const wrap = document.getElementById('due-date-wrap');
    const input = document.getElementById('due_date');
    if (!wrap || !input) return;

    if (contentType === 'assignment') {
        wrap.classList.remove('hidden');
        input.required = true;
    } else {
        wrap.classList.add('hidden');
        input.required = false;
        input.value = '';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const typeSelect = document.getElementById('content_type');
    if (typeSelect) {
        ndsToggleDueDateField(typeSelect.value);
        typeSelect.addEventListener('change', function() {
            ndsToggleQuizBuilder(this.value);
        });
        ndsToggleQuizBuilder(typeSelect.value);
    }

    const courseSelect = document.getElementById('nds-content-course');
    const moduleSelect = document.getElementById('nds-content-module');

    function syncContentModules() {
        if (!courseSelect || !moduleSelect) {
            return;
        }

        const selectedCourseId = courseSelect.value;
        const options = moduleSelect.querySelectorAll('option');
        let hasVisibleOption = false;

        options.forEach(function(option, index) {
            if (index === 0) {
                option.hidden = false;
                return;
            }

            const matchesCourse = option.getAttribute('data-course-id') === selectedCourseId;
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

    if (courseSelect && moduleSelect) {
        courseSelect.addEventListener('change', syncContentModules);
        syncContentModules();
    }
});

// ---------- Quiz Builder ----------
(function () {
    var ndsQ    = [];
    var ndsQId  = 0;
    var ndsActive = 0; // index of the currently visible question

    function ndsInitFromHidden() {
        var hidden = document.getElementById('nds-quiz-data');
        if (!hidden || !hidden.value) {
            return;
        }

        try {
            var parsed = JSON.parse(hidden.value);
            if (!Array.isArray(parsed)) {
                return;
            }

            ndsQ = parsed
                .filter(function (q) { return q && typeof q === 'object'; })
                .map(function (q, idx) {
                    var options = Array.isArray(q.options) ? q.options.slice(0, 4) : [];
                    while (options.length < 4) {
                        options.push('');
                    }

                    return {
                        id: idx + 1,
                        type: (q.type === 'descriptive') ? 'descriptive' : 'multiple_choice',
                        text: q.text || '',
                        options: options,
                        correct: ['A', 'B', 'C', 'D'].indexOf(q.correct) !== -1 ? q.correct : 'A',
                        model_answer: q.model_answer || ''
                    };
                });

            ndsQId = ndsQ.length;
            ndsActive = 0;
            ndsRender();
        } catch (e) {
            // Keep defaults on malformed JSON
        }
    }

    function ndsEsc(s) {
        return String(s || '')
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function ndsSerQ() {
        var f = document.getElementById('nds-quiz-data');
        if (f) f.value = JSON.stringify(ndsQ);
    }

    // Read live DOM values into the data model before navigating away
    function ndsFlushActive() {
        if (!ndsQ.length) return;
        var q = ndsQ[ndsActive];
        if (!q) return;

        var textEl = document.getElementById('nds-q-text-' + q.id);
        if (textEl) q.text = textEl.value;

        if (q.type === 'multiple_choice') {
            ['A','B','C','D'].forEach(function (letter, i) {
                var el = document.getElementById('nds-opt-' + q.id + '-' + letter);
                if (el) q.options[i] = el.value;
            });
            var radios = document.getElementsByName('nds_correct_' + q.id);
            for (var r = 0; r < radios.length; r++) {
                if (radios[r].checked) { q.correct = radios[r].value; break; }
            }
        } else {
            var maEl = document.getElementById('nds-ma-' + q.id);
            if (maEl) q.model_answer = maEl.value;
        }
        ndsSerQ();
    }

    function ndsRenderTabs() {
        var tabsEl  = document.getElementById('nds-q-tabs');
        var emptyEl = document.getElementById('nds-q-empty');
        var navEl   = document.getElementById('nds-q-nav');
        var counter = document.getElementById('nds-q-counter');
        if (!tabsEl) return;

        if (!ndsQ.length) {
            tabsEl.innerHTML = '';
            if (emptyEl) { emptyEl.style.display = ''; tabsEl.appendChild(emptyEl); }
            if (navEl) { navEl.classList.add('hidden'); navEl.classList.remove('flex'); navEl.style.display = 'none'; }
            return;
        }

        if (emptyEl) emptyEl.style.display = 'none';
        if (navEl) { navEl.classList.remove('hidden'); navEl.classList.add('flex'); navEl.style.display = ''; }

        var h = '';
        ndsQ.forEach(function (q, idx) {
            var active = idx === ndsActive;
            h += '<button type="button" onclick="ndsGoQ(' + idx + ')" class="w-8 h-8 flex items-center justify-center rounded-full text-sm font-semibold border transition-colors ';
            h += active
                ? 'bg-indigo-600 text-white border-indigo-600 shadow'
                : 'bg-white text-indigo-700 border-indigo-300 hover:bg-indigo-100';
            h += '">' + (idx + 1) + '</button>';
        });
        tabsEl.innerHTML = h;

        if (counter) counter.textContent = 'Question ' + (ndsActive + 1) + ' of ' + ndsQ.length;

        var prevBtn = document.getElementById('nds-prev-btn');
        var nextBtn = document.getElementById('nds-next-btn');
        if (prevBtn) prevBtn.disabled = ndsActive === 0;
        if (nextBtn) nextBtn.disabled = ndsActive === ndsQ.length - 1;
    }

    function ndsRenderCard() {
        var cardEl = document.getElementById('nds-q-card');
        if (!cardEl) return;
        if (!ndsQ.length) { cardEl.innerHTML = ''; return; }

        var q = ndsQ[ndsActive];
        if (!q) return;

        var h = '<div class="bg-white border border-gray-200 rounded-xl p-4">';

        // Type selector + Remove
        h += '<div class="flex items-center justify-between mb-3">';
        h += '<span class="text-sm font-semibold text-gray-800">Question ' + (ndsActive + 1) + '</span>';
        h += '<div class="flex items-center gap-2">';
        h += '<select class="text-xs border border-gray-300 rounded-lg px-2 py-1 bg-white" onchange="ndsChangeQType(' + q.id + ', this.value)">';
        h += '<option value="multiple_choice"' + (q.type === 'multiple_choice' ? ' selected' : '') + '>Multiple Choice</option>';
        h += '<option value="descriptive"'     + (q.type === 'descriptive'     ? ' selected' : '') + '>Descriptive</option>';
        h += '</select>';
        h += '<button type="button" onclick="ndsRemoveQ(' + q.id + ')" class="text-xs px-2 py-1 text-red-600 border border-red-200 rounded-lg hover:bg-red-50">Remove</button>';
        h += '</div></div>';

        // Question text
        h += '<div class="mb-3">';
        h += '<label class="block text-xs font-medium text-gray-600 mb-1">Question Text *</label>';
        h += '<textarea id="nds-q-text-' + q.id + '" rows="2" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2" placeholder="Type your question here...">' + ndsEsc(q.text) + '</textarea>';
        h += '</div>';

        if (q.type === 'multiple_choice') {
            // Options A-D
            h += '<div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mb-3">';
            ['A','B','C','D'].forEach(function (letter, i) {
                h += '<div class="flex items-center gap-2">';
                h += '<span class="flex-shrink-0 w-6 h-6 flex items-center justify-center rounded-full bg-indigo-100 text-indigo-700 text-xs font-bold">' + letter + '</span>';
                h += '<input id="nds-opt-' + q.id + '-' + letter + '" type="text" class="flex-1 text-sm border border-gray-300 rounded-lg px-2 py-1.5" placeholder="Option ' + letter + '" value="' + ndsEsc(q.options[i] || '') + '">';
                h += '</div>';
            });
            h += '</div>';

            // Correct-answer radios
            h += '<div class="flex flex-wrap items-center gap-4 pt-3 border-t border-gray-100">';
            h += '<span class="text-xs font-semibold text-gray-600">Correct Answer:</span>';
            ['A','B','C','D'].forEach(function (letter) {
                h += '<label class="inline-flex items-center gap-1.5 cursor-pointer">';
                h += '<input type="radio" name="nds_correct_' + q.id + '" value="' + letter + '"' + (q.correct === letter ? ' checked' : '') + ' class="accent-indigo-600">';
                h += '<span class="text-sm text-gray-700 font-medium">' + letter + '</span></label>';
            });
            h += '</div>';
        } else {
            // Descriptive
            h += '<div>';
            h += '<label class="block text-xs font-medium text-gray-600 mb-1">Model Answer <span class="font-normal text-gray-400">(optional — for your marking reference)</span></label>';
            h += '<textarea id="nds-ma-' + q.id + '" rows="3" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2" placeholder="Expected answer or marking guide...">' + ndsEsc(q.model_answer || '') + '</textarea>';
            h += '</div>';
        }

        h += '</div>';
        cardEl.innerHTML = h;
    }

    function ndsRender() {
        ndsRenderTabs();
        ndsRenderCard();
        ndsSerQ();
    }

    // Public API
    window.ndsGoQ = function (idx) {
        ndsFlushActive();
        ndsActive = idx;
        ndsRender();
    };

    window.ndsNavQ = function (dir) {
        ndsFlushActive();
        ndsActive = Math.max(0, Math.min(ndsQ.length - 1, ndsActive + dir));
        ndsRender();
    };

    window.ndsAddQuestion = function () {
        ndsFlushActive();
        ndsQId++;
        ndsQ.push({ id: ndsQId, type: 'multiple_choice', text: '', options: ['','','',''], correct: 'A', model_answer: '' });
        ndsActive = ndsQ.length - 1;
        ndsRender();
    };

    window.ndsRemoveQ = function (id) {
        ndsFlushActive();
        ndsQ = ndsQ.filter(function (q) { return q.id !== id; });
        if (ndsActive >= ndsQ.length) ndsActive = Math.max(0, ndsQ.length - 1);
        ndsRender();
    };

    window.ndsChangeQType = function (id, type) {
        ndsFlushActive();
        var q = ndsQ.find(function (q) { return q.id === id; });
        if (q) { q.type = type; ndsRender(); }
    };

    // Kept for backward compat
    window.ndsSetQ = function (id, field, value) {
        var q = ndsQ.find(function (q) { return q.id === id; });
        if (!q) return;
        if (field === 'A') q.options[0] = value;
        else if (field === 'B') q.options[1] = value;
        else if (field === 'C') q.options[2] = value;
        else if (field === 'D') q.options[3] = value;
        else q[field] = value;
        ndsSerQ();
    };

    document.addEventListener('DOMContentLoaded', function () {
        ndsInitFromHidden();

        var form = document.querySelector('form[enctype="multipart/form-data"]');
        if (form) {
            form.addEventListener('submit', function () { ndsFlushActive(); ndsSerQ(); });
        }
    });
}());

function ndsToggleQuizBuilder(contentType) {
    var builder = document.getElementById('nds-quiz-builder');
    var descLabel = document.getElementById('nds-desc-label');
    var descArea  = document.getElementById('nds-description');
    if (!builder) return;

    if (contentType === 'quiz') {
        builder.classList.remove('hidden');
        if (descLabel) descLabel.textContent = 'Introduction / Overview (Optional)';
        if (descArea)  descArea.required = false;
    } else {
        builder.classList.add('hidden');
        if (descLabel) descLabel.textContent = 'Description *';
        if (descArea)  descArea.required = true;
    }
}
</script>
