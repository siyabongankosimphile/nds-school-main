<?php
if (!defined('ABSPATH')) {
    exit;
}
// This file expects: $staff, $staff_id, $courses_taught, $active_year_id, $active_semester_id
global $wpdb;

// Get selected course or default to first course
$selected_course_id = isset($_GET['course_id']) ? (int) $_GET['course_id'] : 0;
if ($selected_course_id <= 0 && !empty($courses_taught)) {
    $selected_course_id = $courses_taught[0]['id'];
}

// Get modules grouped by course for all lecturer courses
$lecturer_course_ids = array_column($courses_taught, 'id');
$modules_by_course = array();
if (!empty($lecturer_course_ids)) {
    $module_table = $wpdb->prefix . 'nds_modules';
    $module_columns = $wpdb->get_col("SHOW COLUMNS FROM {$module_table}");
    $module_code_col = in_array('code', $module_columns, true) ? 'code' : (in_array('module_code', $module_columns, true) ? 'module_code' : '');
    $module_type_col = in_array('type', $module_columns, true) ? 'type' : '';
    $module_status_col = in_array('status', $module_columns, true) ? 'status' : '';

    $select_code = $module_code_col ? "m.{$module_code_col} AS code" : "'' AS code";
    $select_type = $module_type_col ? "m.{$module_type_col} AS type" : "'theory' AS type";
    $select_status = $module_status_col ? "m.{$module_status_col} AS status" : "'active' AS status";

    $ids_placeholder = implode(',', array_map('intval', $lecturer_course_ids));
    $all_modules = $wpdb->get_results(
        "SELECT m.id, m.name, {$select_code}, {$select_type}, {$select_status}, m.course_id
         FROM {$module_table} m
         WHERE m.course_id IN ($ids_placeholder)
         ORDER BY m.course_id ASC, m.id ASC",
        ARRAY_A
    );
    foreach ($all_modules as $mod) {
        $modules_by_course[(int) $mod['course_id']][] = $mod;
    }
}

// Get learners for selected course
$learners = array();
if ($selected_course_id > 0) {
    $learners = $wpdb->get_results($wpdb->prepare(
        "
        SELECT e.*, s.id as student_id, s.first_name, s.last_name, s.student_number, s.email,
               c.name as course_name, c.code as course_code
        FROM {$wpdb->prefix}nds_student_enrollments e
        INNER JOIN {$wpdb->prefix}nds_students s ON e.student_id = s.id
        INNER JOIN {$wpdb->prefix}nds_courses c ON e.course_id = c.id
        WHERE e.course_id = %d
        AND e.academic_year_id = %d
        AND e.semester_id = %d
        AND e.status IN ('applied', 'enrolled', 'waitlisted')
        ORDER BY s.first_name, s.last_name
        ",
        $selected_course_id, $active_year_id, $active_semester_id
    ), ARRAY_A);
}
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between flex-wrap gap-4">
        <div>
            <h2 class="text-xl font-semibold text-gray-900">My Classes</h2>
            <p class="text-sm text-gray-500 mt-1">View learners in your courses</p>
        </div>
    </div>

    <?php if (empty($courses_taught)): ?>
        <!-- Empty State -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-12 text-center">
            <i class="fas fa-chalkboard-teacher text-6xl text-gray-300 mb-4"></i>
            <h3 class="text-xl font-medium text-gray-900 mb-2">No Courses Assigned</h3>
            <p class="text-gray-600 mb-6">You are not currently assigned to teach any courses.</p>
        </div>
    <?php else: ?>
        <!-- Course Selector -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">Select Course:</label>
            <select id="course-selector" onchange="window.location.href='<?php echo esc_url(nds_staff_portal_tab_url('classes')); ?>&course_id=' + this.value" 
                    class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <?php foreach ($courses_taught as $course): ?>
                    <option value="<?php echo esc_attr($course['id']); ?>" <?php selected($selected_course_id, $course['id']); ?>>
                        <?php echo esc_html($course['name']); ?>
                        <?php if (!empty($course['code'])): ?>
                            (<?php echo esc_html($course['code']); ?>)
                        <?php endif; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Qualifications with Modules -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <div class="p-4 border-b border-gray-200 bg-gray-50">
                <h3 class="text-lg font-semibold text-gray-900">Qualifications &amp; Modules</h3>
                <p class="text-sm text-gray-500 mt-1">Modules grouped by each qualification you teach</p>
            </div>
            <?php
            $has_any_modules = false;
            foreach ($courses_taught as $course):
                $cid = (int) $course['id'];
                $course_modules = $modules_by_course[$cid] ?? array();
                if (!empty($course_modules)) $has_any_modules = true;
            ?>
                <div class="border-b border-gray-100 last:border-0">
                    <button type="button"
                        onclick="this.nextElementSibling.classList.toggle('hidden'); this.querySelector('.chevron').classList.toggle('rotate-180')"
                        class="w-full flex items-center justify-between px-6 py-4 text-left hover:bg-gray-50 focus:outline-none">
                        <div class="flex items-center gap-3">
                            <i class="fas fa-book text-blue-500"></i>
                            <div>
                                <span class="font-medium text-gray-900"><?php echo esc_html($course['name']); ?></span>
                                <?php if (!empty($course['code'])): ?>
                                    <span class="ml-2 text-xs text-gray-500">(<?php echo esc_html($course['code']); ?>)</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="text-xs font-semibold px-2 py-0.5 rounded-full <?php echo empty($course_modules) ? 'bg-gray-100 text-gray-500' : 'bg-blue-100 text-blue-700'; ?>">
                                <?php echo count($course_modules); ?> module<?php echo count($course_modules) !== 1 ? 's' : ''; ?>
                            </span>
                            <i class="fas fa-chevron-down chevron text-gray-400 transition-transform duration-200 <?php echo $cid === $selected_course_id ? '' : ''; ?>"></i>
                        </div>
                    </button>
                    <div class="<?php echo $cid === $selected_course_id ? '' : 'hidden'; ?> px-6 pb-4">
                        <?php if (empty($course_modules)): ?>
                            <p class="text-sm text-gray-400 italic py-2">No modules have been added to this qualification yet.</p>
                        <?php else: ?>
                            <div class="overflow-x-auto rounded-lg border border-gray-200">
                                <table class="min-w-full divide-y divide-gray-200 text-sm">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Code</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Module Name</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-100">
                                        <?php foreach ($course_modules as $mod): ?>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-4 py-2 font-mono text-blue-700"><?php echo esc_html($mod['code'] ?? '—'); ?></td>
                                                <td class="px-4 py-2 text-gray-900"><?php echo esc_html($mod['name']); ?></td>
                                                <td class="px-4 py-2 capitalize text-gray-600"><?php echo esc_html($mod['type'] ?? '—'); ?></td>
                                                <td class="px-4 py-2">
                                                    <?php
                                                    $s = $mod['status'] ?? 'active';
                                                    $sc = $s === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500';
                                                    ?>
                                                    <span class="px-2 py-0.5 rounded-full text-xs font-semibold <?php echo esc_attr($sc); ?>"><?php echo esc_html(ucfirst($s)); ?></span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (!$has_any_modules): ?>
                <div class="p-6 text-center text-sm text-gray-400 italic">None of your qualifications have modules added yet.</div>
            <?php endif; ?>
        </div>

        <!-- Learners List -->
        <?php if ($selected_course_id > 0): ?>
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="p-6 border-b border-gray-200 bg-gray-50">
                    <h3 class="text-lg font-semibold text-gray-900">
                        Learners - 
                        <?php 
                        $selected_course = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}nds_courses WHERE id = %d", $selected_course_id), ARRAY_A);
                        echo esc_html($selected_course['name'] ?? 'Course');
                        if (!empty($selected_course['code'])) {
                            echo ' (' . esc_html($selected_course['code']) . ')';
                        }
                        ?>
                    </h3>
                </div>
                
                <?php if (empty($learners)): ?>
                    <div class="p-12 text-center">
                        <i class="fas fa-users text-6xl text-gray-300 mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">No Learners Enrolled</h3>
                        <p class="text-gray-600">There are no learners currently enrolled in this course.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student Number</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($learners as $learner): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <?php echo esc_html($learner['student_number'] ?? 'N/A'); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo esc_html(trim(($learner['first_name'] ?? '') . ' ' . ($learner['last_name'] ?? ''))); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                            <?php echo esc_html($learner['email'] ?? 'N/A'); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php
                                            $status = $learner['status'] ?? 'enrolled';
                                            $status_colors = array(
                                                'enrolled' => 'bg-green-100 text-green-800',
                                                'applied' => 'bg-yellow-100 text-yellow-800',
                                                'waitlisted' => 'bg-blue-100 text-blue-800'
                                            );
                                            $status_color = $status_colors[$status] ?? 'bg-gray-100 text-gray-800';
                                            ?>
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo esc_attr($status_color); ?>">
                                                <?php echo esc_html(ucfirst($status)); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <a href="<?php echo esc_url(nds_staff_portal_tab_url('marks') . '&course_id=' . $selected_course_id . '&student_id=' . $learner['student_id']); ?>" 
                                               class="text-blue-600 hover:text-blue-900">
                                                View Marks <i class="fas fa-arrow-right ml-1"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
