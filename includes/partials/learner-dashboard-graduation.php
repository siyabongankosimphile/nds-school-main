<?php
/**
 * Learner Dashboard - Graduation Tab
 */
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$learner_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$learner = nds_get_student($learner_id);
$learner_data = (array) $learner;

// Check graduation eligibility
$status = $learner_data['status'] ?? 'prospect';
$is_graduated = in_array($status, ['graduated', 'alumni']);

// Get completed courses
$completed_courses = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}nds_student_enrollments 
     WHERE student_id = %d AND status = 'completed'",
    $learner_id
));

// Get all enrollments to check program completion
$enrollments = $wpdb->get_results($wpdb->prepare(
    "SELECT e.*, c.name as course_name, p.name as program_name, p.id as program_id
     FROM {$wpdb->prefix}nds_student_enrollments e
     LEFT JOIN {$wpdb->prefix}nds_courses c ON e.course_id = c.id
     LEFT JOIN {$wpdb->prefix}nds_programs p ON c.program_id = p.id
     WHERE e.student_id = %d AND e.status = 'completed'
     ORDER BY e.updated_at DESC",
    $learner_id
), ARRAY_A);
?>

<div class="space-y-6">
    <!-- Graduation Status -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-semibold text-gray-900">Graduation Status</h2>
            <?php if (!$is_graduated): ?>
                <button class="inline-flex items-center px-4 py-2 rounded-lg bg-green-600 hover:bg-green-700 text-white text-sm font-medium shadow-sm transition-colors">
                    <i class="fas fa-graduation-cap mr-2"></i>
                    Mark as Graduated
                </button>
            <?php endif; ?>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-gradient-to-br from-green-50 to-emerald-50 rounded-lg p-6 border border-green-200">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-green-700">Status</p>
                        <p class="mt-2 text-2xl font-semibold text-green-900">
                            <?php echo $is_graduated ? 'Graduated' : 'In Progress'; ?>
                        </p>
                    </div>
                    <div class="w-12 h-12 rounded-lg bg-green-100 flex items-center justify-center">
                        <i class="fas fa-graduation-cap text-green-600 text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-lg p-6 border border-blue-200">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-blue-700">Completed Courses</p>
                        <p class="mt-2 text-2xl font-semibold text-blue-900"><?php echo intval($completed_courses); ?></p>
                    </div>
                    <div class="w-12 h-12 rounded-lg bg-blue-100 flex items-center justify-center">
                        <i class="fas fa-check-circle text-blue-600 text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-gradient-to-br from-purple-50 to-pink-50 rounded-lg p-6 border border-purple-200">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-purple-700">Programs</p>
                        <p class="mt-2 text-2xl font-semibold text-purple-900">
                            <?php 
                            $programs = array_unique(array_filter(array_column($enrollments, 'program_id')));
                            echo count($programs);
                            ?>
                        </p>
                    </div>
                    <div class="w-12 h-12 rounded-lg bg-purple-100 flex items-center justify-center">
                        <i class="fas fa-book-open text-purple-600 text-xl"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Graduation Requirements -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">Graduation Requirements</h2>
        <div class="space-y-4">
            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                <div class="flex items-center space-x-3">
                    <i class="fas fa-check-circle text-green-600"></i>
                    <span class="text-sm font-medium text-gray-900">Complete all required courses</span>
                </div>
                <span class="text-sm text-gray-500">In Progress</span>
            </div>
            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                <div class="flex items-center space-x-3">
                    <i class="fas fa-check-circle text-green-600"></i>
                    <span class="text-sm font-medium text-gray-900">Maintain minimum GPA</span>
                </div>
                <span class="text-sm text-gray-500">Pending</span>
            </div>
            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                <div class="flex items-center space-x-3">
                    <i class="fas fa-circle text-gray-300"></i>
                    <span class="text-sm font-medium text-gray-900">Submit final project/thesis</span>
                </div>
                <span class="text-sm text-gray-500">Not Started</span>
            </div>
            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                <div class="flex items-center space-x-3">
                    <i class="fas fa-circle text-gray-300"></i>
                    <span class="text-sm font-medium text-gray-900">Clear all outstanding fees</span>
                </div>
                <span class="text-sm text-gray-500">Not Started</span>
            </div>
        </div>
    </div>

    <!-- Graduation History -->
    <?php if ($is_graduated): ?>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Graduation Details</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="text-sm font-medium text-gray-500">Graduation Date</label>
                    <p class="mt-1 text-sm text-gray-900">N/A</p>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-500">Degree/Diploma Awarded</label>
                    <p class="mt-1 text-sm text-gray-900">N/A</p>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-500">Honors/Distinction</label>
                    <p class="mt-1 text-sm text-gray-900">N/A</p>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-500">Final GPA</label>
                    <p class="mt-1 text-sm text-gray-900">N/A</p>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
