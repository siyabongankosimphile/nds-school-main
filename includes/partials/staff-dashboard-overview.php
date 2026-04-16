<?php
if (!defined('ABSPATH')) {
    exit;
}
// This file expects: $staff, $staff_data, $courses_taught, $courses_count, $active_year, $active_semester
?>

<div class="space-y-6">
    <!-- Welcome Section -->
    <div class="bg-gradient-to-r from-blue-600 to-blue-800 rounded-lg shadow-lg p-8 text-white">
        <h2 class="text-3xl font-bold mb-2">Welcome back, <?php echo esc_html($staff_data['first_name'] ?? 'Staff'); ?>!</h2>
        <p class="text-blue-100 text-lg">Here's your teaching overview for this term.</p>
    </div>

    <!-- Quick Stats -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Total Students -->
        <?php
        global $wpdb;
        $total_students = 0;
        if (!empty($course_ids)) {
            $placeholders = implode(',', array_fill(0, count($course_ids), '%d'));
            $total_students = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT student_id) 
                 FROM {$wpdb->prefix}nds_student_enrollments 
                 WHERE course_id IN ($placeholders)
                 AND academic_year_id = %d
                 AND semester_id = %d
                 AND status IN ('applied', 'enrolled', 'waitlisted')",
                array_merge($course_ids, [$active_year_id, $active_semester_id])
            ));
        }
        ?>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 mb-1">Total Students</p>
                    <p class="text-3xl font-bold text-gray-900"><?php echo esc_html($total_students); ?></p>
                </div>
                <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-users text-purple-600 text-xl"></i>
                </div>
            </div>
            <p class="text-xs text-gray-500 mt-2">Across all your courses</p>
        </div>

        <!-- Courses Teaching -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 mb-1">Courses Teaching</p>
                    <p class="text-3xl font-bold text-gray-900"><?php echo esc_html($courses_count); ?></p>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-book text-blue-600 text-xl"></i>
                </div>
            </div>
            <p class="text-xs text-gray-500 mt-2">Active courses this term</p>
        </div>

        <!-- Active Term -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 mb-1">Active Term</p>
                    <p class="text-lg font-bold text-gray-900">
                        <?php 
                        if ($active_year && $active_semester) {
                            echo esc_html($active_year['year_name'] ?? '') . ' - ' . esc_html($active_semester['semester_name'] ?? '');
                        } else {
                            echo 'Not Set';
                        }
                        ?>
                    </p>
                </div>
                <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-calendar-check text-green-600 text-xl"></i>
                </div>
            </div>
            <p class="text-xs text-gray-500 mt-2">Current academic period</p>
        </div>
    </div>

    <!-- My Courses -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="p-6 border-b border-gray-200">
            <h3 class="text-xl font-semibold text-gray-900 flex items-center">
                <i class="fas fa-book-open text-blue-600 mr-2"></i>My Courses
            </h3>
        </div>
        <div class="p-6">
            <?php if (empty($courses_taught)): ?>
                <div class="text-center py-12">
                    <i class="fas fa-book text-6xl text-gray-300 mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No Courses Assigned</h3>
                    <p class="text-gray-600">You are not currently assigned to teach any courses.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($courses_taught as $course): 
                        // Get student count for this course
                        $student_count = (int) $wpdb->get_var($wpdb->prepare(
                            "SELECT COUNT(DISTINCT student_id) 
                             FROM {$wpdb->prefix}nds_student_enrollments 
                             WHERE course_id = %d
                             AND academic_year_id = %d
                             AND semester_id = %d
                             AND status IN ('applied', 'enrolled', 'waitlisted')",
                            $course['id'], $active_year_id, $active_semester_id
                        ));
                    ?>
                        <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                            <div class="flex items-start justify-between mb-3">
                                <div class="flex-1">
                                    <h4 class="font-semibold text-gray-900 mb-1"><?php echo esc_html($course['name']); ?></h4>
                                    <?php if (!empty($course['code'])): ?>
                                        <p class="text-sm text-gray-500"><?php echo esc_html($course['code']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-gray-600">
                                    <i class="fas fa-users mr-1"></i><?php echo esc_html($student_count); ?> students
                                </span>
                                <a href="<?php echo esc_url(nds_staff_portal_tab_url('classes') . '&course_id=' . $course['id']); ?>" 
                                   class="text-blue-600 hover:text-blue-700 font-medium">
                                    View Details <i class="fas fa-arrow-right ml-1"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
