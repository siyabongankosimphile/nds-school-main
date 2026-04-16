<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

$course_ids = isset($course_ids) && is_array($course_ids) ? array_values(array_map('intval', $course_ids)) : array();
$placeholders = !empty($course_ids) ? implode(',', array_fill(0, count($course_ids), '%d')) : '';

$recent_announcements = array();
$students_for_message = array();
if (!empty($course_ids)) {
    $recent_announcements = $wpdb->get_results($wpdb->prepare(
        "SELECT a.*, c.name AS course_name
         FROM {$wpdb->prefix}nds_lecturer_announcements a
         INNER JOIN {$wpdb->prefix}nds_courses c ON c.id = a.course_id
         WHERE a.course_id IN ($placeholders)
         ORDER BY a.created_at DESC
         LIMIT 20",
        $course_ids
    ), ARRAY_A);

    $students_for_message = $wpdb->get_results($wpdb->prepare(
        "SELECT DISTINCT s.id, s.student_number, s.first_name, s.last_name, s.email, e.course_id
         FROM {$wpdb->prefix}nds_students s
         INNER JOIN {$wpdb->prefix}nds_student_enrollments e ON e.student_id = s.id
         WHERE e.course_id IN ($placeholders)
         ORDER BY s.last_name, s.first_name",
        $course_ids
    ), ARRAY_A);
}

$recent_messages = $wpdb->get_results($wpdb->prepare(
    "SELECT m.*, s.student_number, s.first_name, s.last_name, c.name AS course_name
     FROM {$wpdb->prefix}nds_lecturer_messages m
     INNER JOIN {$wpdb->prefix}nds_students s ON s.id = m.student_id
     LEFT JOIN {$wpdb->prefix}nds_courses c ON c.id = m.course_id
     WHERE m.staff_id = %d
     ORDER BY m.created_at DESC
     LIMIT 20",
    (int) $staff_id
), ARRAY_A);
?>

<div class="space-y-6">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="bg-white border border-gray-200 rounded-lg p-4">
            <h3 class="text-lg font-semibold text-gray-900 mb-3">Post Announcement</h3>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="space-y-3">
                <?php wp_nonce_field('nds_staff_post_announcement', 'nds_staff_post_announcement_nonce'); ?>
                <input type="hidden" name="action" value="nds_staff_post_announcement">
                <input type="hidden" name="redirect_url" value="<?php echo esc_url(nds_staff_portal_tab_url('communication')); ?>">

                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Course</label>
                    <select name="course_id" class="w-full border border-gray-300 rounded-lg px-3 py-2" required>
                        <?php foreach ($courses_taught as $course): ?>
                            <option value="<?php echo esc_attr($course['id']); ?>"><?php echo esc_html($course['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Title</label>
                    <input type="text" name="title" class="w-full border border-gray-300 rounded-lg px-3 py-2" required>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Message</label>
                    <textarea name="message" rows="4" class="w-full border border-gray-300 rounded-lg px-3 py-2" required></textarea>
                </div>
                <label class="inline-flex items-center text-sm text-gray-700"><input type="checkbox" name="notify_students" value="1" checked class="mr-2">Notify students</label>
                <div class="text-right"><button class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Publish Announcement</button></div>
            </form>
        </div>

        <div class="bg-white border border-gray-200 rounded-lg p-4">
            <h3 class="text-lg font-semibold text-gray-900 mb-3">Send Message</h3>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="space-y-3">
                <?php wp_nonce_field('nds_staff_send_message', 'nds_staff_send_message_nonce'); ?>
                <input type="hidden" name="action" value="nds_staff_send_message">
                <input type="hidden" name="redirect_url" value="<?php echo esc_url(nds_staff_portal_tab_url('communication')); ?>">

                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Course</label>
                    <select name="course_id" class="w-full border border-gray-300 rounded-lg px-3 py-2" required>
                        <?php foreach ($courses_taught as $course): ?>
                            <option value="<?php echo esc_attr($course['id']); ?>"><?php echo esc_html($course['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Student</label>
                    <select name="student_id" class="w-full border border-gray-300 rounded-lg px-3 py-2" required>
                        <?php foreach ($students_for_message as $student): ?>
                            <option value="<?php echo esc_attr($student['id']); ?>"><?php echo esc_html($student['student_number'] . ' - ' . $student['first_name'] . ' ' . $student['last_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Subject</label>
                    <input type="text" name="subject" class="w-full border border-gray-300 rounded-lg px-3 py-2" required>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Message</label>
                    <textarea name="message" rows="4" class="w-full border border-gray-300 rounded-lg px-3 py-2" required></textarea>
                </div>
                <div class="text-right"><button class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">Send Message</button></div>
            </form>
        </div>
    </div>

    <div class="bg-white border border-gray-200 rounded-lg p-4">
        <h4 class="text-md font-semibold text-gray-900 mb-3">Recent Announcements</h4>
        <div class="space-y-2">
            <?php foreach ($recent_announcements as $row): ?>
                <div class="border border-gray-200 rounded-lg p-3">
                    <div class="font-medium text-gray-900"><?php echo esc_html($row['title']); ?></div>
                    <div class="text-xs text-gray-500 mt-1"><?php echo esc_html($row['course_name']); ?> • <?php echo esc_html(date_i18n('Y-m-d H:i', strtotime($row['created_at']))); ?></div>
                    <div class="text-sm text-gray-700 mt-2"><?php echo esc_html(wp_trim_words($row['message'], 24)); ?></div>
                </div>
            <?php endforeach; ?>
            <?php if (empty($recent_announcements)): ?>
                <p class="text-sm text-gray-600">No announcements posted yet.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="bg-white border border-gray-200 rounded-lg p-4">
        <h4 class="text-md font-semibold text-gray-900 mb-3">Recent Direct Messages</h4>
        <div class="space-y-2">
            <?php foreach ($recent_messages as $row): ?>
                <div class="border border-gray-200 rounded-lg p-3">
                    <div class="font-medium text-gray-900"><?php echo esc_html($row['subject']); ?></div>
                    <div class="text-xs text-gray-500 mt-1">To <?php echo esc_html($row['student_number'] . ' - ' . $row['first_name'] . ' ' . $row['last_name']); ?><?php echo !empty($row['course_name']) ? ' • ' . esc_html($row['course_name']) : ''; ?> • <?php echo esc_html(date_i18n('Y-m-d H:i', strtotime($row['created_at']))); ?></div>
                    <div class="text-sm text-gray-700 mt-2"><?php echo esc_html(wp_trim_words($row['message'], 24)); ?></div>
                </div>
            <?php endforeach; ?>
            <?php if (empty($recent_messages)): ?>
                <p class="text-sm text-gray-600">No direct messages sent yet.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
