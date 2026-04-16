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
        "SELECT id, section_id, content_type, title, is_visible, access_start, access_end, completion_required, min_grade_required
         FROM {$wpdb->prefix}nds_lecturer_content
         WHERE course_id = %d
         ORDER BY sort_order ASC, created_at DESC
         LIMIT 100",
        $selected_course_id
    ), ARRAY_A);
}
?>

<div class="space-y-6">
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
                <div class="border border-gray-200 rounded-lg p-3 flex items-start justify-between gap-3">
                    <div>
                        <div class="font-medium text-gray-900"><?php echo esc_html($section['title']); ?></div>
                        <div class="text-xs text-gray-500 mt-1">Position: <?php echo esc_html($section['position']); ?> • Visible: <?php echo !empty($section['is_visible']) ? 'Yes' : 'No'; ?> • Completion required: <?php echo !empty($section['completion_required']) ? 'Yes' : 'No'; ?></div>
                        <div class="text-xs text-gray-500">Access: <?php echo !empty($section['access_start']) ? esc_html($section['access_start']) : '-'; ?> to <?php echo !empty($section['access_end']) ? esc_html($section['access_end']) : '-'; ?><?php echo $section['min_grade_required'] !== null ? ' • Min grade: ' . esc_html($section['min_grade_required']) . '%' : ''; ?></div>
                    </div>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="flex items-center gap-2">
                        <?php wp_nonce_field('nds_staff_update_section_visibility', 'nds_staff_update_section_visibility_nonce'); ?>
                        <input type="hidden" name="action" value="nds_staff_update_section_visibility">
                        <input type="hidden" name="redirect_url" value="<?php echo esc_url(nds_staff_portal_tab_url('structure') . '&course_id=' . (int) $selected_course_id); ?>">
                        <input type="hidden" name="section_id" value="<?php echo esc_attr($section['id']); ?>">
                        <input type="hidden" name="is_visible" value="<?php echo !empty($section['is_visible']) ? '0' : '1'; ?>">
                        <button type="submit" style="color:#fff" class="px-2 py-1 text-xs rounded <?php echo !empty($section['is_visible']) ? 'bg-yellow-600 hover:bg-yellow-700' : 'bg-green-600 hover:bg-green-700'; ?>"><?php echo !empty($section['is_visible']) ? 'Hide' : 'Show'; ?></button>
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
                    <thead class="bg-gray-50"><tr><th class="px-3 py-2 text-left">Title</th><th class="px-3 py-2 text-left">Type</th><th class="px-3 py-2 text-left">Visible</th><th class="px-3 py-2 text-left">Access</th><th class="px-3 py-2 text-left">Completion Rule</th></tr></thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        <?php foreach ($content_items as $item): ?>
                            <tr>
                                <td class="px-3 py-2"><?php echo esc_html($item['title']); ?></td>
                                <td class="px-3 py-2"><?php echo esc_html(ucwords(str_replace('_', ' ', $item['content_type']))); ?></td>
                                <td class="px-3 py-2"><?php echo !empty($item['is_visible']) ? 'Yes' : 'No'; ?></td>
                                <td class="px-3 py-2"><?php echo !empty($item['access_start']) ? esc_html($item['access_start']) : '-'; ?> to <?php echo !empty($item['access_end']) ? esc_html($item['access_end']) : '-'; ?></td>
                                <td class="px-3 py-2"><?php echo !empty($item['completion_required']) ? 'Required' : 'Optional'; ?><?php echo $item['min_grade_required'] !== null ? ' • Min grade: ' . esc_html($item['min_grade_required']) . '%' : ''; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
