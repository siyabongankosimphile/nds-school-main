<?php
/**
 * Lecturer Portal - Display Modules Assigned to Lecturer
 * Shows detailed information about modules the lecturer is assigned to teach
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Verify required variables from parent scope
if (!isset($staff_id)) {
    echo '<div class="p-6 text-red-600">Error: Staff ID not found.</div>';
    return;
}

$modules_table = $wpdb->prefix . 'nds_modules';
$module_lecturers_table = $wpdb->prefix . 'nds_module_lecturers';
$courses_table = $wpdb->prefix . 'nds_courses';
$programs_table = $wpdb->prefix . 'nds_programs';

// Get modules assigned to this lecturer
$assigned_modules = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT m.*, c.name as course_name, c.id as course_id, c.code as course_code, p.name as program_name, p.id as program_id
         FROM {$modules_table} m
         INNER JOIN {$module_lecturers_table} ml ON m.id = ml.module_id
         INNER JOIN {$courses_table} c ON m.course_id = c.id
         INNER JOIN {$programs_table} p ON c.program_id = p.id
         WHERE ml.lecturer_id = %d
         ORDER BY p.name, c.name, m.name",
        $staff_id
    ),
    ARRAY_A
);

// Also get courses via course_lecturers (backward compatibility)
$course_lecturer_columns = $wpdb->get_col("SHOW COLUMNS FROM {$wpdb->prefix}nds_course_lecturers", 0);
$assigned_col = in_array('assigned_at', $course_lecturer_columns, true)
    ? 'assigned_at'
    : (in_array('assigned_date', $course_lecturer_columns, true) ? 'assigned_date' : null);
$assigned_expr = $assigned_col ? "cl.{$assigned_col} AS assigned_at" : 'NULL AS assigned_at';

$course_assignments = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT c.*, p.name as program_name, p.id as program_id, {$assigned_expr}
         FROM {$wpdb->prefix}nds_course_lecturers cl
         INNER JOIN {$courses_table} c ON cl.course_id = c.id
         INNER JOIN {$programs_table} p ON c.program_id = p.id
         WHERE cl.lecturer_id = %d
         ORDER BY p.name, c.name",
        $staff_id
    ),
    ARRAY_A
);

// Group modules by course
$modules_by_course = [];
foreach ($assigned_modules as $module) {
    $course_key = $module['course_id'];
    if (!isset($modules_by_course[$course_key])) {
        $modules_by_course[$course_key] = [
            'course_name' => $module['course_name'],
            'course_code' => $module['course_code'],
            'course_id' => $module['course_id'],
            'program_name' => $module['program_name'],
            'program_id' => $module['program_id'],
            'modules' => []
        ];
    }
    $modules_by_course[$course_key]['modules'][] = $module;
}

?>

<div class="space-y-6">
    <!-- Info Card -->
    <div class="bg-blue-50 border-l-4 border-blue-500 p-6 rounded-r-lg">
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <i class="fas fa-info-circle text-blue-600 text-xl"></i>
            </div>
            <div class="ml-4">
                <h3 class="text-sm font-medium text-blue-900">Module-Level Teaching Assignments</h3>
                <p class="text-sm text-blue-700 mt-1">
                    Your teaching assignments are now organized at the module level. Below you can see all the modules you're assigned to teach, organized by qualification and program.
                </p>
            </div>
        </div>
    </div>

    <!-- Header -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h2 class="text-2xl font-semibold text-gray-900 mb-2 flex items-center">
            <i class="fas fa-book-open text-blue-600 mr-3"></i>
            My Teaching Modules
        </h2>
        <p class="text-gray-600">Total: <span class="font-semibold"><?php echo count($assigned_modules); ?> module(s)</span> across <?php echo count($modules_by_course); ?> qualification(s)</p>
    </div>

    <?php if (empty($modules_by_course) && empty($course_assignments)): ?>
        <!-- No Assignments -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-12 text-center">
            <i class="fas fa-inbox text-gray-300 text-5xl mb-4"></i>
            <h3 class="text-lg font-semibold text-gray-900 mb-2">No Teaching Modules Assigned</h3>
            <p class="text-gray-600 mb-4">You don't have any modules assigned to teach yet.</p>
            <p class="text-sm text-gray-500">An administrator will assign modules to you once your teaching schedule is set up.</p>
        </div>
    <?php else: ?>
        <!-- Modules by Course -->
        <div class="space-y-6">
            <?php foreach ($modules_by_course as $course_key => $course_data): ?>
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <!-- Course Header -->
                    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border-b border-gray-200 px-6 py-4">
                        <div class="flex items-start justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">
                                    <?php echo esc_html($course_data['course_name']); ?>
                                </h3>
                                <p class="text-sm text-gray-600 mt-1">
                                    <i class="fas fa-building text-gray-500 mr-2"></i>
                                    <?php echo esc_html($course_data['program_name']); ?>
                                </p>
                            </div>
                            <div class="bg-white rounded-lg px-3 py-2 shadow-sm">
                                <div class="text-2xl font-bold text-blue-600"><?php echo count($course_data['modules']); ?></div>
                                <div class="text-xs text-gray-600">Module(s)</div>
                            </div>
                        </div>
                    </div>

                    <!-- Modules List -->
                    <div class="divide-y">
                        <?php foreach ($course_data['modules'] as $module): ?>
                            <div class="p-6 hover:bg-gray-50 transition-colors">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-3 mb-2">
                                            <h4 class="text-base font-semibold text-gray-900">
                                                <?php echo esc_html($module['name']); ?>
                                            </h4>
                                            <span class="px-3 py-1 bg-blue-100 text-blue-800 text-xs font-medium rounded-full">
                                                <?php echo esc_html(ucfirst($module['type'] ?? 'theory')); ?>
                                            </span>
                                        </div>
                                        
                                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-3 text-sm">
                                            <?php if (!empty($module['module_code'])): ?>
                                                <div>
                                                    <span class="text-gray-600">Code:</span>
                                                    <div class="font-mono text-gray-900"><?php echo esc_html($module['module_code']); ?></div>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($module['hours'])): ?>
                                                <div>
                                                    <span class="text-gray-600">Duration:</span>
                                                    <div class="font-semibold text-gray-900"><?php echo intval($module['hours']); ?> hrs</div>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($module['nqf_level'])): ?>
                                                <div>
                                                    <span class="text-gray-600">NQF Level:</span>
                                                    <div class="font-semibold text-gray-900"><?php echo intval($module['nqf_level']); ?></div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- Course-Level Assignments (Backward Compatibility) -->
            <?php if (!empty($course_assignments)): ?>
                <div class="bg-amber-50 border-l-4 border-amber-400 p-6 rounded-r-lg">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-triangle text-amber-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-sm font-medium text-amber-900">Full Course Assignments (Legacy)</h3>
                            <p class="text-sm text-amber-700 mt-1">
                                You also have the following course-level assignments. These are being transitioned to module-level assignments.
                            </p>
                            <div class="mt-3 space-y-2">
                                <?php foreach ($course_assignments as $course): ?>
                                    <div class="text-sm font-medium text-amber-900">
                                        • <?php echo esc_html($course['program_name']); ?> - <?php echo esc_html($course['name']); ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
