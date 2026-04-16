<?php
/**
 * Learner Dashboard - Results Tab
 */
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$learner_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get enrollments with grades
$results = $wpdb->get_results($wpdb->prepare(
    "SELECT e.*, c.name as course_name, c.code as course_code,
            p.name as program_name, f.name as faculty_name,
            ay.year_name, s.semester_name
     FROM {$wpdb->prefix}nds_student_enrollments e
     LEFT JOIN {$wpdb->prefix}nds_courses c ON e.course_id = c.id
     LEFT JOIN {$wpdb->prefix}nds_programs p ON c.program_id = p.id
     LEFT JOIN {$wpdb->prefix}nds_faculties f ON p.faculty_id = f.id
     LEFT JOIN {$wpdb->prefix}nds_academic_years ay ON e.academic_year_id = ay.id
     LEFT JOIN {$wpdb->prefix}nds_semesters s ON e.semester_id = s.id
     WHERE e.student_id = %d
       AND (e.final_percentage IS NOT NULL OR e.final_grade IS NOT NULL)
     ORDER BY e.updated_at DESC",
    $learner_id
), ARRAY_A);

// Calculate statistics
$grades = array_filter(array_column($results, 'final_percentage'));
$avg_grade = !empty($grades) ? round(array_sum($grades) / count($grades), 1) : 0;
$highest_grade = !empty($grades) ? max($grades) : 0;
$lowest_grade = !empty($grades) ? min($grades) : 0;
?>

<div class="space-y-6">
    <!-- Results Summary -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <p class="text-sm font-medium text-gray-500">Average Grade</p>
            <p class="mt-2 text-2xl font-semibold text-gray-900"><?php echo $avg_grade > 0 ? number_format_i18n($avg_grade, 1) . '%' : 'N/A'; ?></p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <p class="text-sm font-medium text-gray-500">Highest Grade</p>
            <p class="mt-2 text-2xl font-semibold text-green-600"><?php echo $highest_grade > 0 ? number_format_i18n($highest_grade, 1) . '%' : 'N/A'; ?></p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <p class="text-sm font-medium text-gray-500">Lowest Grade</p>
            <p class="mt-2 text-2xl font-semibold text-red-600"><?php echo $lowest_grade > 0 ? number_format_i18n($lowest_grade, 1) . '%' : 'N/A'; ?></p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <p class="text-sm font-medium text-gray-500">Courses Graded</p>
            <p class="mt-2 text-2xl font-semibold text-gray-900"><?php echo count($results); ?></p>
        </div>
    </div>

    <!-- Results Table -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-semibold text-gray-900">Academic Results</h2>
            <button class="inline-flex items-center px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium shadow-sm transition-colors">
                <i class="fas fa-download mr-2"></i>
                Download Transcript
            </button>
        </div>

        <?php if (!empty($results)): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Course</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Program</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Term</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Percentage</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Grade</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($results as $result): 
                            $percentage = $result['final_percentage'] ?? null;
                            $grade = $result['final_grade'] ?? null;
                            $grade_color = $percentage >= 75 ? 'text-green-600' : ($percentage >= 50 ? 'text-blue-600' : 'text-red-600');
                        ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo esc_html($result['course_name'] ?? 'N/A'); ?>
                                    </div>
                                    <?php if (!empty($result['course_code'])): ?>
                                        <div class="text-sm text-gray-500"><?php echo esc_html($result['course_code']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo esc_html($result['program_name'] ?? 'N/A'); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php 
                                    $term = [];
                                    if (!empty($result['year_name'])) $term[] = $result['year_name'];
                                    if (!empty($result['semester_name'])) $term[] = $result['semester_name'];
                                    echo !empty($term) ? esc_html(implode(' - ', $term)) : 'N/A';
                                    ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm font-semibold <?php echo $grade_color; ?>">
                                        <?php echo $percentage !== null ? number_format_i18n($percentage, 1) . '%' : 'N/A'; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium <?php echo $grade_color; ?>">
                                    <?php echo $grade ? esc_html($grade) : 'N/A'; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800">
                                        <?php echo esc_html(ucfirst($result['status'] ?? 'completed')); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-12">
                <i class="fas fa-chart-bar text-6xl text-gray-300 mb-4"></i>
                <h3 class="text-xl font-medium text-gray-900 mb-2">No Results Available</h3>
                <p class="text-gray-600">Results will appear here once courses are graded.</p>
            </div>
        <?php endif; ?>
    </div>
</div>
