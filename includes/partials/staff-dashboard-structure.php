<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

$selected_course_id = isset($_GET['course_id']) ? (int) $_GET['course_id'] : 0;
if ($selected_course_id <= 0 && !empty($courses_taught)) {
    $selected_course_id = (int) $courses_taught[0]['id'];
}

$sections = array();
if ($selected_course_id > 0) {
    $sections = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}nds_course_sections WHERE course_id = %d ORDER BY position ASC, id ASC",
        $selected_course_id
    ), ARRAY_A);
}

$content_items = array();
if ($selected_course_id > 0) {
    $content_items = $wpdb->get_results($wpdb->prepare(
        "SELECT id, section_id, content_type, title, is_visible, access_start, access_end, completion_required, min_grade_required, access_grouping, allowed_cohort_ids
         FROM {$wpdb->prefix}nds_lecturer_content
         WHERE course_id = %d
         ORDER BY sort_order ASC, created_at DESC
         LIMIT 100",
        $selected_course_id
    ), ARRAY_A);
}

$course_cohorts = array();
if ($selected_course_id > 0) {
    $program_id = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT program_id FROM {$wpdb->prefix}nds_courses WHERE id = %d LIMIT 1",
        $selected_course_id
    ));
    if ($program_id > 0) {
        $course_cohorts = $wpdb->get_results($wpdb->prepare(
            "SELECT id, code, name, status
             FROM {$wpdb->prefix}nds_cohorts
             WHERE program_id = %d
             ORDER BY status = 'active' DESC, name ASC",
            $program_id
        ), ARRAY_A);
    }
}

$engagement_map = array();
if (!empty($content_items) && function_exists('nds_portal_ensure_quiz_attempts_table')) {
    $content_ids = array_values(array_filter(array_map('intval', array_column($content_items, 'id'))));
    if (!empty($content_ids)) {
        $placeholders = implode(',', array_fill(0, count($content_ids), '%d'));

        $quiz_attempts_table = nds_portal_ensure_quiz_attempts_table();
        $quiz_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT content_id, COUNT(*) AS attempts_count, COUNT(DISTINCT student_id) AS learners_count
             FROM {$quiz_attempts_table}
             WHERE content_id IN ({$placeholders})
             GROUP BY content_id",
            $content_ids
        ), ARRAY_A);

        foreach ($quiz_rows as $qr) {
            $cid = (int) ($qr['content_id'] ?? 0);
            if ($cid <= 0) {
                continue;
            }
            $engagement_map[$cid] = array(
                'attempts_count' => (int) ($qr['attempts_count'] ?? 0),
                'submissions_count' => 0,
                'views_count' => 0,
                'learners_count' => (int) ($qr['learners_count'] ?? 0),
            );
        }

        if (function_exists('nds_portal_ensure_assignment_submissions_table')) {
            $assignment_submissions_table = nds_portal_ensure_assignment_submissions_table();
            $assignment_rows = $wpdb->get_results($wpdb->prepare(
                "SELECT content_id, COUNT(*) AS submissions_count, COUNT(DISTINCT student_id) AS learners_count
                 FROM {$assignment_submissions_table}
                 WHERE content_id IN ({$placeholders})
                 GROUP BY content_id",
                $content_ids
            ), ARRAY_A);

            foreach ($assignment_rows as $ar) {
                $cid = (int) ($ar['content_id'] ?? 0);
                if ($cid <= 0) {
                    continue;
                }
                if (!isset($engagement_map[$cid])) {
                    $engagement_map[$cid] = array('attempts_count' => 0, 'submissions_count' => 0, 'views_count' => 0, 'learners_count' => 0);
                }
                $engagement_map[$cid]['submissions_count'] = (int) ($ar['submissions_count'] ?? 0);
                $engagement_map[$cid]['learners_count'] = max((int) $engagement_map[$cid]['learners_count'], (int) ($ar['learners_count'] ?? 0));
            }
        }

        if (function_exists('nds_portal_ensure_content_views_table')) {
            $content_views_table = nds_portal_ensure_content_views_table();
            $view_rows = $wpdb->get_results($wpdb->prepare(
                "SELECT content_id, COUNT(*) AS views_count, COUNT(DISTINCT student_id) AS learners_count
                 FROM {$content_views_table}
                 WHERE content_id IN ({$placeholders})
                 GROUP BY content_id",
                $content_ids
            ), ARRAY_A);

            foreach ($view_rows as $vr) {
                $cid = (int) ($vr['content_id'] ?? 0);
                if ($cid <= 0) {
                    continue;
                }
                if (!isset($engagement_map[$cid])) {
                    $engagement_map[$cid] = array('attempts_count' => 0, 'submissions_count' => 0, 'views_count' => 0, 'learners_count' => 0);
                }
                $engagement_map[$cid]['views_count'] = (int) ($vr['views_count'] ?? 0);
                $engagement_map[$cid]['learners_count'] = max((int) $engagement_map[$cid]['learners_count'], (int) ($vr['learners_count'] ?? 0));
            }
        }
    }
}

$total_sections = count($sections);
$total_items = count($content_items);
$visible_items = 0;
foreach ($content_items as $item) {
    if (!empty($item['is_visible'])) {
        $visible_items++;
    }
}
?>

<div class="space-y-6">
    <div class="bg-white border border-gray-200 rounded-xl p-5 sm:p-6">
        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Course Layout</h2>
                <p class="text-sm text-gray-600 mt-1">Manage sections, release rules, and visibility for course learning content.</p>
            </div>
            <div class="grid grid-cols-3 gap-2 sm:gap-3 min-w-[260px]">
                <div class="rounded-lg bg-gray-50 border border-gray-200 px-3 py-2">
                    <p class="text-[11px] uppercase tracking-wide text-gray-500">Sections</p>
                    <p class="text-lg font-semibold text-gray-900"><?php echo esc_html((string) $total_sections); ?></p>
                </div>
                <div class="rounded-lg bg-gray-50 border border-gray-200 px-3 py-2">
                    <p class="text-[11px] uppercase tracking-wide text-gray-500">Items</p>
                    <p class="text-lg font-semibold text-gray-900"><?php echo esc_html((string) $total_items); ?></p>
                </div>
                <div class="rounded-lg bg-gray-50 border border-gray-200 px-3 py-2">
                    <p class="text-[11px] uppercase tracking-wide text-gray-500">Visible</p>
                    <p class="text-lg font-semibold text-gray-900"><?php echo esc_html((string) $visible_items); ?></p>
                </div>
            </div>
        </div>
    </div>

    <?php if (isset($_GET['structure_notice'])) : ?>
        <?php $structure_notice = sanitize_key((string) wp_unslash($_GET['structure_notice'])); ?>
        <div class="p-4 rounded-lg border border-green-200 bg-green-50 text-green-800 text-sm">
            <?php
            $notice_map = array(
                'section_created' => 'Section created successfully.',
                'section_updated' => 'Section updated successfully.',
                'section_deleted' => 'Section deleted successfully.',
                'content_updated' => 'Content visibility updated successfully.',
            );
            echo esc_html($notice_map[$structure_notice] ?? 'Course structure updated successfully.');
            ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['structure_error'])) : ?>
        <?php $structure_error = sanitize_key((string) wp_unslash($_GET['structure_error'])); ?>
        <div class="p-4 rounded-lg border border-red-200 bg-red-50 text-red-800 text-sm">
            <?php
            $error_map = array(
                'security' => 'Security validation failed. Please refresh and try again.',
                'permission' => 'You do not have permission to change this course structure.',
                'missing_fields' => 'Please complete all required fields.',
                'save_failed' => 'Could not save structure changes. Please try again.',
            );
            echo esc_html($error_map[$structure_error] ?? 'Could not update course structure.');
            ?>
        </div>
    <?php endif; ?>

    <div class="bg-white border border-gray-200 rounded-lg p-4">
        <h3 class="text-lg font-semibold text-gray-900 mb-3">Course Structure and Completion Rules</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Course</label>
                <select onchange="window.location.href='<?php echo esc_url(nds_staff_portal_tab_url('structure')); ?>&course_id=' + this.value" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <?php foreach ($courses_taught as $course): ?>
                        <option value="<?php echo esc_attr($course['id']); ?>" <?php selected($selected_course_id, (int) $course['id']); ?>><?php echo esc_html($course['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="text-sm text-gray-600">You can add sections, define order with position, and set visibility/access rules by date and required grade.</div>
        </div>
    </div>

    <div class="bg-white border border-gray-200 rounded-lg p-4">
        <h4 class="text-md font-semibold text-gray-900 mb-3">Add Section / Topic</h4>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <?php wp_nonce_field('nds_staff_add_section', 'nds_staff_add_section_nonce'); ?>
            <input type="hidden" name="action" value="nds_staff_add_section">
            <input type="hidden" name="redirect_url" value="<?php echo esc_url(nds_staff_portal_tab_url('structure') . '&course_id=' . (int) $selected_course_id); ?>">
            <input type="hidden" name="course_id" value="<?php echo esc_attr($selected_course_id); ?>">

            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Section Title</label>
                <input type="text" name="title" required class="w-full border border-gray-300 rounded-lg px-3 py-2" placeholder="Section 1: Introduction">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Position (order)</label>
                <input type="number" name="position" value="0" class="w-full border border-gray-300 rounded-lg px-3 py-2">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Minimum Grade Required</label>
                <input type="number" step="0.01" min="0" max="100" name="min_grade_required" class="w-full border border-gray-300 rounded-lg px-3 py-2">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Access Start</label>
                <input type="datetime-local" name="access_start" class="w-full border border-gray-300 rounded-lg px-3 py-2">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Access End</label>
                <input type="datetime-local" name="access_end" class="w-full border border-gray-300 rounded-lg px-3 py-2">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Group Access</label>
                <select name="access_grouping" class="nds-section-grouping-select w-full border border-gray-300 rounded-lg px-3 py-2">
                    <option value="all">All enrolled learners</option>
                    <option value="cohorts">Specific cohorts only</option>
                </select>
            </div>
            <div class="nds-allowed-cohorts-wrap hidden">
                <label class="block text-xs font-medium text-gray-700 mb-1">Allowed Cohort IDs</label>
                <input type="hidden" id="nds-section-add-allowed-cohorts" name="allowed_cohort_ids" value="">
                <div class="flex items-center gap-2">
                    <button type="button" class="nds-open-cohort-picker px-3 py-2 text-xs rounded border border-gray-300 bg-white hover:bg-gray-50" data-target-input-id="nds-section-add-allowed-cohorts">Choose cohorts</button>
                    <span class="text-[11px] text-gray-500">Search and pick cohorts visually.</span>
                </div>
                <div class="nds-cohort-pill-list mt-2 flex flex-wrap gap-1.5" data-input-id="nds-section-add-allowed-cohorts"></div>
            </div>
            <div class="flex items-center gap-4 mt-5">
                <label class="inline-flex items-center text-sm text-gray-700"><input type="checkbox" name="is_visible" value="1" checked class="mr-2">Visible</label>
                <label class="inline-flex items-center text-sm text-gray-700"><input type="checkbox" name="completion_required" value="1" class="mr-2">Completion Required</label>
            </div>
            <div class="md:col-span-3">
                <label class="block text-xs font-medium text-gray-700 mb-1">Description</label>
                <textarea name="description" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2"></textarea>
            </div>
            <div class="md:col-span-3 text-right">
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Add Section</button>
            </div>
        </form>
    </div>

    <div class="bg-white border border-gray-200 rounded-lg p-4">
        <h4 class="text-md font-semibold text-gray-900 mb-3">Sections and Visibility</h4>
        <div class="space-y-2">
            <?php foreach ($sections as $section): ?>
                <div class="border border-gray-200 rounded-lg p-3">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="font-medium text-gray-900"><?php echo esc_html($section['title']); ?></div>
                            <div class="text-xs text-gray-500 mt-1">Position: <?php echo esc_html($section['position']); ?> • Visible: <?php echo !empty($section['is_visible']) ? 'Yes' : 'No'; ?> • Completion required: <?php echo !empty($section['completion_required']) ? 'Yes' : 'No'; ?></div>
                            <div class="text-xs text-gray-500">Access: <?php echo !empty($section['access_start']) ? esc_html($section['access_start']) : '-'; ?> to <?php echo !empty($section['access_end']) ? esc_html($section['access_end']) : '-'; ?><?php echo $section['min_grade_required'] !== null ? ' • Min grade: ' . esc_html($section['min_grade_required']) . '%' : ''; ?></div>
                            <div class="text-xs text-gray-500">Group access: <?php echo (isset($section['access_grouping']) && $section['access_grouping'] === 'cohorts') ? 'Specific cohorts (' . esc_html((string) ($section['allowed_cohort_ids'] ?? '')) . ')' : 'All enrolled learners'; ?></div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                <?php wp_nonce_field('nds_staff_move_section', 'nds_staff_move_section_nonce'); ?>
                                <input type="hidden" name="action" value="nds_staff_move_section">
                                <input type="hidden" name="redirect_url" value="<?php echo esc_url(nds_staff_portal_tab_url('structure') . '&course_id=' . (int) $selected_course_id); ?>">
                                <input type="hidden" name="section_id" value="<?php echo esc_attr($section['id']); ?>">
                                <input type="hidden" name="direction" value="up">
                                <button type="submit" class="px-2 py-1 text-xs rounded bg-gray-100 hover:bg-gray-200 text-gray-700">Move Up</button>
                            </form>

                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                <?php wp_nonce_field('nds_staff_move_section', 'nds_staff_move_section_nonce'); ?>
                                <input type="hidden" name="action" value="nds_staff_move_section">
                                <input type="hidden" name="redirect_url" value="<?php echo esc_url(nds_staff_portal_tab_url('structure') . '&course_id=' . (int) $selected_course_id); ?>">
                                <input type="hidden" name="section_id" value="<?php echo esc_attr($section['id']); ?>">
                                <input type="hidden" name="direction" value="down">
                                <button type="submit" class="px-2 py-1 text-xs rounded bg-gray-100 hover:bg-gray-200 text-gray-700">Move Down</button>
                            </form>

                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="flex items-center gap-2">
                                <?php wp_nonce_field('nds_staff_update_section_visibility', 'nds_staff_update_section_visibility_nonce'); ?>
                                <input type="hidden" name="action" value="nds_staff_update_section_visibility">
                                <input type="hidden" name="redirect_url" value="<?php echo esc_url(nds_staff_portal_tab_url('structure') . '&course_id=' . (int) $selected_course_id); ?>">
                                <input type="hidden" name="section_id" value="<?php echo esc_attr($section['id']); ?>">
                                <input type="hidden" name="is_visible" value="<?php echo !empty($section['is_visible']) ? '0' : '1'; ?>">
                                <button type="submit" style="color:#fff" class="px-2 py-1 text-xs rounded <?php echo !empty($section['is_visible']) ? 'bg-yellow-600 hover:bg-yellow-700' : 'bg-green-600 hover:bg-green-700'; ?>"><?php echo !empty($section['is_visible']) ? 'Hide' : 'Show'; ?></button>
                            </form>

                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Delete this section? Content in this section will be moved to unsectioned content.');">
                                <?php wp_nonce_field('nds_staff_delete_section', 'nds_staff_delete_section_nonce'); ?>
                                <input type="hidden" name="action" value="nds_staff_delete_section">
                                <input type="hidden" name="redirect_url" value="<?php echo esc_url(nds_staff_portal_tab_url('structure') . '&course_id=' . (int) $selected_course_id); ?>">
                                <input type="hidden" name="section_id" value="<?php echo esc_attr($section['id']); ?>">
                                <button type="submit" class="px-2 py-1 text-xs rounded bg-red-600 hover:bg-red-700 text-white">Delete</button>
                            </form>
                        </div>
                    </div>

                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="mt-3 grid grid-cols-1 md:grid-cols-4 gap-2">
                        <?php wp_nonce_field('nds_staff_update_section', 'nds_staff_update_section_nonce'); ?>
                        <input type="hidden" name="action" value="nds_staff_update_section">
                        <input type="hidden" name="redirect_url" value="<?php echo esc_url(nds_staff_portal_tab_url('structure') . '&course_id=' . (int) $selected_course_id); ?>">
                        <input type="hidden" name="section_id" value="<?php echo esc_attr($section['id']); ?>">

                        <div class="md:col-span-2">
                            <label class="block text-[11px] font-medium text-gray-600 mb-1">Section Title</label>
                            <input type="text" name="title" value="<?php echo esc_attr($section['title']); ?>" required class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                        </div>
                        <div>
                            <label class="block text-[11px] font-medium text-gray-600 mb-1">Position</label>
                            <input type="number" name="position" value="<?php echo esc_attr((int) $section['position']); ?>" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                        </div>
                        <div>
                            <label class="block text-[11px] font-medium text-gray-600 mb-1">Min Grade %</label>
                            <input type="number" step="0.01" min="0" max="100" name="min_grade_required" value="<?php echo $section['min_grade_required'] !== null ? esc_attr((float) $section['min_grade_required']) : ''; ?>" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                        </div>
                        <div>
                            <label class="block text-[11px] font-medium text-gray-600 mb-1">Access Start</label>
                            <input type="datetime-local" name="access_start" value="<?php echo !empty($section['access_start']) ? esc_attr(date('Y-m-d\TH:i', strtotime((string) $section['access_start']))) : ''; ?>" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                        </div>
                        <div>
                            <label class="block text-[11px] font-medium text-gray-600 mb-1">Access End</label>
                            <input type="datetime-local" name="access_end" value="<?php echo !empty($section['access_end']) ? esc_attr(date('Y-m-d\TH:i', strtotime((string) $section['access_end']))) : ''; ?>" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                        </div>
                        <div>
                            <label class="block text-[11px] font-medium text-gray-600 mb-1">Group Access</label>
                            <select name="access_grouping" class="nds-section-grouping-select w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                                <option value="all" <?php selected(isset($section['access_grouping']) ? (string) $section['access_grouping'] : 'all', 'all'); ?>>All enrolled learners</option>
                                <option value="cohorts" <?php selected(isset($section['access_grouping']) ? (string) $section['access_grouping'] : 'all', 'cohorts'); ?>>Specific cohorts only</option>
                            </select>
                        </div>
                        <div class="nds-allowed-cohorts-wrap <?php echo (isset($section['access_grouping']) && (string) $section['access_grouping'] === 'cohorts') ? '' : 'hidden'; ?>">
                            <label class="block text-[11px] font-medium text-gray-600 mb-1">Allowed Cohort IDs</label>
                            <?php $section_cohort_input_id = 'nds-section-edit-allowed-cohorts-' . (int) $section['id']; ?>
                            <input type="hidden" id="<?php echo esc_attr($section_cohort_input_id); ?>" name="allowed_cohort_ids" value="<?php echo esc_attr((string) ($section['allowed_cohort_ids'] ?? '')); ?>">
                            <div class="flex items-center gap-2">
                                <button type="button" class="nds-open-cohort-picker px-2 py-1.5 text-xs rounded border border-gray-300 bg-white hover:bg-gray-50" data-target-input-id="<?php echo esc_attr($section_cohort_input_id); ?>">Choose cohorts</button>
                                <span class="text-[11px] text-gray-500">Search and pick cohorts visually.</span>
                            </div>
                            <div class="nds-cohort-pill-list mt-2 flex flex-wrap gap-1.5" data-input-id="<?php echo esc_attr($section_cohort_input_id); ?>"></div>
                        </div>
                        <div class="flex items-end gap-3 text-sm text-gray-700">
                            <label class="inline-flex items-center"><input type="checkbox" name="is_visible" value="1" <?php checked((int) $section['is_visible'], 1); ?> class="mr-1">Visible</label>
                            <label class="inline-flex items-center"><input type="checkbox" name="completion_required" value="1" <?php checked((int) $section['completion_required'], 1); ?> class="mr-1">Completion Required</label>
                        </div>
                        <div class="md:col-span-4">
                            <label class="block text-[11px] font-medium text-gray-600 mb-1">Description</label>
                            <textarea name="description" rows="2" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm"><?php echo esc_textarea((string) ($section['description'] ?? '')); ?></textarea>
                        </div>
                        <div class="md:col-span-4 text-right">
                            <button type="submit" class="px-3 py-1.5 text-xs rounded bg-blue-600 hover:bg-blue-700 text-white">Save Section</button>
                        </div>
                    </form>
                </div>
            <?php endforeach; ?>
            <?php if (empty($sections)): ?>
                <p class="text-sm text-gray-600">No sections added yet.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="bg-white border border-gray-200 rounded-lg p-4">
        <h4 class="text-md font-semibold text-gray-900 mb-3">Content Items (Restrictions)</h4>
        <?php if (empty($content_items)): ?>
            <p class="text-sm text-gray-600">No content items for this course yet. Add content in the Content tab.</p>
        <?php else: ?>
            <div class="overflow-x-auto border border-gray-200 rounded-lg">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50"><tr><th class="px-3 py-2 text-left">Title</th><th class="px-3 py-2 text-left">Type</th><th class="px-3 py-2 text-left">Visible</th><th class="px-3 py-2 text-left">Access</th><th class="px-3 py-2 text-left">Group Access</th><th class="px-3 py-2 text-left">Completion Rule</th><th class="px-3 py-2 text-left">Progress</th><th class="px-3 py-2 text-left">Action</th></tr></thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        <?php foreach ($content_items as $item): ?>
                            <?php
                            $item_id = (int) ($item['id'] ?? 0);
                            $engagement = $engagement_map[$item_id] ?? array('attempts_count' => 0, 'submissions_count' => 0, 'views_count' => 0, 'learners_count' => 0);
                            $progress_label = 'No tracked activity';
                            if ((int) ($engagement['attempts_count'] ?? 0) > 0) {
                                $progress_label = (int) $engagement['attempts_count'] . ' quiz attempt(s) • ' . (int) ($engagement['learners_count'] ?? 0) . ' learner(s)';
                            } elseif ((int) ($engagement['submissions_count'] ?? 0) > 0) {
                                $progress_label = (int) $engagement['submissions_count'] . ' submission(s) • ' . (int) ($engagement['learners_count'] ?? 0) . ' learner(s)';
                            } elseif ((int) ($engagement['views_count'] ?? 0) > 0) {
                                $progress_label = (int) $engagement['views_count'] . ' view(s) • ' . (int) ($engagement['learners_count'] ?? 0) . ' learner(s)';
                            }
                            ?>
                            <tr>
                                <td class="px-3 py-2"><?php echo esc_html($item['title']); ?></td>
                                <td class="px-3 py-2"><?php echo esc_html(ucwords(str_replace('_', ' ', $item['content_type']))); ?></td>
                                <td class="px-3 py-2"><?php echo !empty($item['is_visible']) ? 'Yes' : 'No'; ?></td>
                                <td class="px-3 py-2"><?php echo !empty($item['access_start']) ? esc_html($item['access_start']) : '-'; ?> to <?php echo !empty($item['access_end']) ? esc_html($item['access_end']) : '-'; ?></td>
                                <td class="px-3 py-2"><?php echo (isset($item['access_grouping']) && $item['access_grouping'] === 'cohorts') ? 'Cohorts: ' . esc_html((string) ($item['allowed_cohort_ids'] ?? '')) : 'All'; ?></td>
                                <td class="px-3 py-2"><?php echo !empty($item['completion_required']) ? 'Required' : 'Optional'; ?><?php echo $item['min_grade_required'] !== null ? ' • Min grade: ' . esc_html($item['min_grade_required']) . '%' : ''; ?></td>
                                <td class="px-3 py-2"><?php echo esc_html($progress_label); ?></td>
                                <td class="px-3 py-2">
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="inline">
                                        <?php wp_nonce_field('nds_staff_toggle_content_visibility', 'nds_staff_toggle_content_visibility_nonce'); ?>
                                        <input type="hidden" name="action" value="nds_staff_toggle_content_visibility">
                                        <input type="hidden" name="redirect_url" value="<?php echo esc_url(nds_staff_portal_tab_url('structure') . '&course_id=' . (int) $selected_course_id); ?>">
                                        <input type="hidden" name="content_id" value="<?php echo esc_attr($item_id); ?>">
                                        <input type="hidden" name="is_visible" value="<?php echo !empty($item['is_visible']) ? '0' : '1'; ?>">
                                        <?php if (!empty($item['is_visible'])): ?>
                                            <button type="submit" class="px-2 py-1 text-xs rounded bg-yellow-600 hover:bg-yellow-700 text-white">Hide</button>
                                        <?php else: ?>
                                            <button type="submit" class="px-2 py-1 text-xs rounded bg-green-600 hover:bg-green-700 text-white">Show</button>
                                        <?php endif; ?>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div id="nds-section-cohort-picker-modal" class="hidden fixed inset-0 z-[1100] bg-black/40 items-center justify-center p-4">
    <div class="w-full max-w-2xl bg-white rounded-xl border border-gray-200 shadow-xl">
        <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between gap-3">
            <h5 class="text-sm font-semibold text-gray-900">Select Cohorts</h5>
            <button type="button" id="nds-section-close-cohort-modal" class="text-gray-500 hover:text-gray-700 text-lg leading-none">&times;</button>
        </div>
        <div class="p-4 space-y-3">
            <div class="flex flex-col md:flex-row md:items-center gap-2">
                <input type="search" id="nds-section-cohort-search" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Search by cohort ID, code, or name">
                <div class="flex items-center gap-2">
                    <button type="button" id="nds-section-select-all-cohorts" class="px-2 py-1.5 text-xs rounded border border-gray-300 bg-white hover:bg-gray-50">Select all shown</button>
                    <button type="button" id="nds-section-unselect-all-cohorts" class="px-2 py-1.5 text-xs rounded border border-gray-300 bg-white hover:bg-gray-50">Unselect all shown</button>
                </div>
            </div>
            <div id="nds-section-cohort-list" class="max-h-72 overflow-y-auto border border-gray-200 rounded-lg divide-y divide-gray-100"></div>
        </div>
        <div class="px-4 py-3 border-t border-gray-200 flex items-center justify-end gap-2">
            <button type="button" id="nds-section-clear-cohort-selection" class="px-3 py-1.5 text-xs rounded border border-gray-300 bg-white hover:bg-gray-50">Clear</button>
            <button type="button" id="nds-section-cancel-cohort-selection" class="px-3 py-1.5 text-xs rounded border border-gray-300 bg-white hover:bg-gray-50">Cancel</button>
            <button type="button" id="nds-section-apply-cohort-selection" class="px-3 py-1.5 text-xs rounded bg-blue-600 hover:bg-blue-700 text-white">Apply Selection</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const courseCohorts = <?php echo wp_json_encode(array_values(array_map(static function ($cohort) {
        return array(
            'id' => (int) ($cohort['id'] ?? 0),
            'code' => (string) ($cohort['code'] ?? ''),
            'name' => (string) ($cohort['name'] ?? ''),
            'status' => (string) ($cohort['status'] ?? ''),
        );
    }, (array) $course_cohorts))); ?>;

    const groupingSelects = Array.prototype.slice.call(document.querySelectorAll('.nds-section-grouping-select'));
    const pickerButtons = Array.prototype.slice.call(document.querySelectorAll('.nds-open-cohort-picker'));
    const pillContainers = Array.prototype.slice.call(document.querySelectorAll('.nds-cohort-pill-list'));

    const modal = document.getElementById('nds-section-cohort-picker-modal');
    const closeModalBtn = document.getElementById('nds-section-close-cohort-modal');
    const cancelBtn = document.getElementById('nds-section-cancel-cohort-selection');
    const applyBtn = document.getElementById('nds-section-apply-cohort-selection');
    const clearBtn = document.getElementById('nds-section-clear-cohort-selection');
    const searchInput = document.getElementById('nds-section-cohort-search');
    const listWrap = document.getElementById('nds-section-cohort-list');
    const selectAllBtn = document.getElementById('nds-section-select-all-cohorts');
    const unselectAllBtn = document.getElementById('nds-section-unselect-all-cohorts');

    let activeInputId = '';
    let draftSelection = [];

    function parseCsvValue(value) {
        return String(value || '').split(',').map(function (part) {
            return parseInt(String(part || '').trim(), 10);
        }).filter(function (id, index, all) {
            return !Number.isNaN(id) && id > 0 && all.indexOf(id) === index;
        });
    }

    function readSelectionFromInput(inputId) {
        const input = document.getElementById(inputId);
        if (!input) {
            return [];
        }
        return parseCsvValue(input.value);
    }

    function writeSelectionToInput(inputId, ids) {
        const input = document.getElementById(inputId);
        if (!input) {
            return;
        }
        const normalized = Array.isArray(ids) ? ids.map(function (id) {
            return parseInt(id, 10);
        }).filter(function (id, index, all) {
            return !Number.isNaN(id) && id > 0 && all.indexOf(id) === index;
        }) : [];

        input.value = normalized.join(',');
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function getCohortById(id) {
        return courseCohorts.find(function (cohort) {
            return parseInt(cohort.id || 0, 10) === id;
        }) || null;
    }

    function renderPillsForInput(inputId) {
        const container = document.querySelector('.nds-cohort-pill-list[data-input-id="' + inputId + '"]');
        if (!container) {
            return;
        }

        const selected = readSelectionFromInput(inputId);
        container.innerHTML = '';

        if (!selected.length) {
            const empty = document.createElement('span');
            empty.className = 'text-[11px] text-gray-400';
            empty.textContent = 'No cohorts selected.';
            container.appendChild(empty);
            return;
        }

        selected.forEach(function (id) {
            const cohort = getCohortById(id);
            const chip = document.createElement('button');
            chip.type = 'button';
            chip.className = 'inline-flex items-center gap-1 px-2 py-1 text-[11px] rounded border border-gray-300 bg-gray-50 text-gray-700 hover:bg-gray-100';
            chip.textContent = '#' + id + ' ' + String((cohort && (cohort.name || cohort.code)) || 'Cohort');
            chip.addEventListener('click', function () {
                const next = readSelectionFromInput(inputId).filter(function (currentId) {
                    return currentId !== id;
                });
                writeSelectionToInput(inputId, next);
                renderPillsForInput(inputId);
            });
            container.appendChild(chip);
        });
    }

    function renderAllPills() {
        pillContainers.forEach(function (container) {
            const inputId = container.getAttribute('data-input-id') || '';
            if (!inputId) {
                return;
            }
            renderPillsForInput(inputId);
        });
    }

    function getVisibleRows() {
        const keyword = searchInput ? String(searchInput.value || '').trim().toLowerCase() : '';
        return courseCohorts.filter(function (cohort) {
            if (!keyword) {
                return true;
            }
            const haystack = [
                String(cohort.id || ''),
                String(cohort.code || ''),
                String(cohort.name || ''),
                String(cohort.status || ''),
            ].join(' ').toLowerCase();
            return haystack.indexOf(keyword) !== -1;
        });
    }

    function renderModalList() {
        if (!listWrap) {
            return;
        }
        listWrap.innerHTML = '';
        const visibleRows = getVisibleRows();

        if (!visibleRows.length) {
            const empty = document.createElement('div');
            empty.className = 'px-3 py-2 text-sm text-gray-500';
            empty.textContent = 'No matching cohorts.';
            listWrap.appendChild(empty);
            return;
        }

        visibleRows.forEach(function (cohort) {
            const id = parseInt(cohort.id || 0, 10);
            if (!id) {
                return;
            }

            const row = document.createElement('label');
            row.className = 'flex items-center gap-3 px-3 py-2 hover:bg-gray-50 cursor-pointer';

            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.value = String(id);
            checkbox.checked = draftSelection.indexOf(id) !== -1;
            checkbox.addEventListener('change', function () {
                if (checkbox.checked) {
                    if (draftSelection.indexOf(id) === -1) {
                        draftSelection.push(id);
                    }
                } else {
                    draftSelection = draftSelection.filter(function (currentId) {
                        return currentId !== id;
                    });
                }
            });

            const text = document.createElement('div');
            text.className = 'text-sm text-gray-700';
            const label = '#' + id + ' ' + String(cohort.name || cohort.code || 'Cohort');
            text.textContent = cohort.status ? label + ' (' + cohort.status + ')' : label;

            row.appendChild(checkbox);
            row.appendChild(text);
            listWrap.appendChild(row);
        });
    }

    function openModalForInput(inputId) {
        if (!modal || !inputId) {
            return;
        }
        activeInputId = inputId;
        draftSelection = readSelectionFromInput(inputId);
        if (searchInput) {
            searchInput.value = '';
        }
        renderModalList();
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closeModal() {
        if (!modal) {
            return;
        }
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    function toggleVisibleRows(checked) {
        const ids = getVisibleRows().map(function (cohort) {
            return parseInt(cohort.id || 0, 10);
        }).filter(function (id) {
            return id > 0;
        });

        if (checked) {
            ids.forEach(function (id) {
                if (draftSelection.indexOf(id) === -1) {
                    draftSelection.push(id);
                }
            });
        } else {
            draftSelection = draftSelection.filter(function (id) {
                return ids.indexOf(id) === -1;
            });
        }
        renderModalList();
    }

    function syncGroupingVisibility(selectElement) {
        if (!selectElement || !selectElement.form) {
            return;
        }
        const wrap = selectElement.form.querySelector('.nds-allowed-cohorts-wrap');
        if (!wrap) {
            return;
        }
        if (String(selectElement.value) === 'cohorts') {
            wrap.classList.remove('hidden');
        } else {
            wrap.classList.add('hidden');
        }
    }

    groupingSelects.forEach(function (selectElement) {
        syncGroupingVisibility(selectElement);
        selectElement.addEventListener('change', function () {
            syncGroupingVisibility(selectElement);
        });
    });

    pickerButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            const inputId = button.getAttribute('data-target-input-id') || '';
            openModalForInput(inputId);
        });
    });

    if (closeModalBtn) {
        closeModalBtn.addEventListener('click', closeModal);
    }
    if (cancelBtn) {
        cancelBtn.addEventListener('click', closeModal);
    }
    if (applyBtn) {
        applyBtn.addEventListener('click', function () {
            if (!activeInputId) {
                closeModal();
                return;
            }
            writeSelectionToInput(activeInputId, draftSelection);
            renderPillsForInput(activeInputId);
            closeModal();
        });
    }
    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            if (!activeInputId) {
                return;
            }
            draftSelection = [];
            renderModalList();
        });
    }
    if (searchInput) {
        searchInput.addEventListener('input', renderModalList);
    }
    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', function () {
            toggleVisibleRows(true);
        });
    }
    if (unselectAllBtn) {
        unselectAllBtn.addEventListener('click', function () {
            toggleVisibleRows(false);
        });
    }

    if (modal) {
        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                closeModal();
            }
        });
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modal && !modal.classList.contains('hidden')) {
            closeModal();
        }
    });

    renderAllPills();
});
</script>
