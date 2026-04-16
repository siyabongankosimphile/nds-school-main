<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Verify required variables from parent scope
if (!isset($learner_id) || !isset($student)) {
    echo '<div class="p-6 text-red-600">Error: Student information not found.</div>';
    return;
}

$active_year = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}nds_academic_years WHERE is_active = 1 ORDER BY id DESC LIMIT 1", ARRAY_A);
$active_semester = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}nds_semesters WHERE is_active = 1 ORDER BY id DESC LIMIT 1", ARRAY_A);

$modules_table = $wpdb->prefix . 'nds_modules';
$student_modules_table = $wpdb->prefix . 'nds_student_modules';
$courses_table = $wpdb->prefix . 'nds_courses';

// Get currently enrolled modules
$query = "
    SELECT sm.*, m.name, m.code, m.type, m.duration_hours, m.description, c.name as course_name 
    FROM {$student_modules_table} sm
    JOIN {$modules_table} m ON sm.module_id = m.id
    JOIN {$courses_table} c ON m.course_id = c.id
    WHERE sm.student_id = %d 
    AND sm.academic_year_id = %d 
    AND sm.semester_id = %d 
    ORDER BY c.name, m.name
";

$enrolled_modules = $wpdb->get_results($wpdb->prepare(
    $query, 
    $learner_id, 
    $active_year ? $active_year['id'] : 0, 
    $active_semester ? $active_semester['id'] : 0
), ARRAY_A);

?>

<div class="space-y-6">
    <!-- Header/Overview -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h2 class="text-xl font-semibold text-gray-900 mb-4 flex items-center">
            <i class="fas fa-cubes text-blue-600 mr-3"></i>
            Enrolled Modules
        </h2>
        
        <?php if (!$active_year || !$active_semester): ?>
            <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-yellow-700">
                            Warning: Active academic year or semester is not set in the system. 
                        </p>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="text-sm text-gray-500 mb-6">
                Showing module enrollments for <span class="font-medium text-gray-800"><?php echo esc_html($active_year['year_name']); ?> - <?php echo esc_html($active_semester['semester_name']); ?></span>
            </div>
            
            <?php if (empty($enrolled_modules)): ?>
                <div class="text-center py-12 border-2 border-dashed border-gray-300 rounded-lg">
                    <i class="fas fa-box-open text-4xl text-gray-300 mb-3"></i>
                    <p class="text-gray-500 font-medium">No modules enrolled</p>
                    <p class="text-gray-400 text-sm mt-1">Enroll this learner into modules via the <a href="<?php echo esc_url(admin_url('admin.php?page=nds-learner-dashboard&id=' . $learner_id . '&tab=courses')); ?>" class="text-blue-600 hover:underline">Courses tab</a>.</p>
                </div>
            <?php else: ?>
                
                <?php 
                // Group modules by course
                $modules_by_course = [];
                foreach ($enrolled_modules as $mod) {
                    $course_name = $mod['course_name'];
                    if (!isset($modules_by_course[$course_name])) {
                        $modules_by_course[$course_name] = [];
                    }
                    $modules_by_course[$course_name][] = $mod;
                }
                ?>
                
                <div class="space-y-6">
                    <?php foreach ($modules_by_course as $course_name => $modules): ?>
                        <div class="border rounded-lg overflow-hidden">
                            <div class="bg-gray-50 px-4 py-3 border-b">
                                <h3 class="font-medium text-gray-800"><?php echo esc_html($course_name); ?> <span class="text-gray-500 font-normal text-sm ml-2">(<?php echo count($modules); ?> modules)</span></h3>
                            </div>
                            <div class="divide-y">
                                <?php foreach ($modules as $module): ?>
                                    <div class="p-4 hover:bg-gray-50 transition-colors flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                        <div class="flex-1">
                                            <div class="flex items-center gap-2 mb-1">
                                                <h4 class="font-semibold text-gray-900"><?php echo esc_html($module['name']); ?></h4>
                                                <?php if (!empty($module['code'])): ?>
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                                        <?php echo esc_html($module['code']); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (!empty($module['description'])): ?>
                                                <p class="text-sm text-gray-500 line-clamp-2"><?php echo esc_html($module['description']); ?></p>
                                            <?php endif; ?>
                                            
                                            <div class="flex items-center gap-4 mt-2 text-xs text-gray-500">
                                                <?php if (!empty($module['type'])): ?>
                                                    <span class="flex items-center capitalize">
                                                        <i class="fas fa-tag mr-1 text-gray-400"></i>
                                                        <?php echo esc_html($module['type']); ?>
                                                    </span>
                                                <?php endif; ?>
                                                <?php if (!empty($module['duration_hours'])): ?>
                                                    <span class="flex items-center">
                                                        <i class="fas fa-clock mr-1 text-gray-400"></i>
                                                        <?php echo intval($module['duration_hours']); ?> Hours
                                                    </span>
                                                <?php endif; ?>
                                                <span class="flex items-center capitalize <?php echo $module['status'] === 'enrolled' ? 'text-green-600 font-medium' : ''; ?>">
                                                    <i class="fas fa-info-circle mr-1 <?php echo $module['status'] === 'enrolled' ? 'text-green-500' : 'text-gray-400'; ?>"></i>
                                                    State: <?php echo esc_html($module['status']); ?>
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <div class="flex-shrink-0 text-right">
                                            <?php if (!empty($module['final_grade'])): ?>
                                                <div class="text-sm">
                                                    <span class="text-gray-500">Grade:</span> 
                                                    <span class="font-bold text-gray-900"><?php echo esc_html($module['final_grade']); ?></span>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($module['final_percentage'])): ?>
                                                <div class="text-sm">
                                                    <span class="text-gray-500">Score:</span> 
                                                    <span class="font-medium text-gray-900"><?php echo esc_html($module['final_percentage']); ?>%</span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
