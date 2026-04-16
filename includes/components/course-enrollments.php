<?php
/**
 * Course Enrollments Component
 * Reusable component for displaying and managing course enrollments
 * 
 * @param int $student_id - The student/learner ID
 * @param array $args - Additional arguments:
 *   - show_title (bool) - Show the "Course Enrollments" title (default: true)
 *   - show_enroll_button (bool) - Show the "Enroll in Course" button (default: true)
 *   - show_actions (bool) - Show View/Unenroll actions (default: true)
 *   - empty_message (string) - Custom empty state message
 *   - limit (int) - Limit number of enrollments shown (default: null = all)
 *   - status_filter (string) - Filter by enrollment status (default: null = all)
 */

if (!defined('ABSPATH')) {
    exit;
}

function nds_course_enrollments_component($student_id, $args = []) {
    if (!$student_id) {
        return '<div class="bg-red-50 border border-red-200 rounded-lg p-4 text-red-700">Invalid student ID provided.</div>';
    }

    // Default arguments
    $defaults = [
        'show_title' => true,
        'show_enroll_button' => true,
        'show_actions' => true,
        'empty_message' => null,
        'limit' => null,
        'status_filter' => null,
        'enroll_button_text' => 'Enroll in Course',
        'enroll_button_url' => '#',
        'enroll_button_class' => 'inline-flex items-center px-4 py-2 rounded-lg bg-green-600 hover:bg-green-700 text-white text-sm font-medium shadow-sm transition-colors',
        'wrapper_class' => 'space-y-6',
        'table_class' => 'min-w-full divide-y divide-gray-200',
    ];
    
    $args = wp_parse_args($args, $defaults);
    
    global $wpdb;
    
    // Build query
    $query = "
        SELECT e.*, c.name as course_name, c.code as course_code, c.description as course_description,
                p.name as program_name, f.name as faculty_name,
                ay.year_name, s.semester_name
        FROM {$wpdb->prefix}nds_student_enrollments e
        LEFT JOIN {$wpdb->prefix}nds_courses c ON e.course_id = c.id
        LEFT JOIN {$wpdb->prefix}nds_programs p ON c.program_id = p.id
        LEFT JOIN {$wpdb->prefix}nds_faculties f ON p.faculty_id = f.id
        LEFT JOIN {$wpdb->prefix}nds_academic_years ay ON e.academic_year_id = ay.id
        LEFT JOIN {$wpdb->prefix}nds_semesters s ON e.semester_id = s.id
        WHERE e.student_id = %d
    ";
    
    $params = [$student_id];
    
    // Add status filter if provided
    if ($args['status_filter']) {
        $query .= " AND e.status = %s";
        $params[] = $args['status_filter'];
    }
    
    $query .= " ORDER BY e.created_at DESC";
    
    // Add limit if provided
    if ($args['limit']) {
        $query .= " LIMIT %d";
        $params[] = $args['limit'];
    }
    
    // Get enrollments
    $enrollments = $wpdb->get_results(
        $wpdb->prepare($query, $params),
        ARRAY_A
    );
    
    // Handle success/error messages
    $message = '';
    $message_type = '';
    if (isset($_GET['success']) && $_GET['success'] === 'unenrolled') {
        $message = 'Student successfully unenrolled from course.';
        $message_type = 'success';
    } elseif (isset($_GET['error'])) {
        $message_type = 'error';
        switch ($_GET['error']) {
            case 'unenroll_failed':
                $message = 'Failed to unenroll student. Please try again.';
                break;
            case 'invalid_data':
                $message = 'Invalid data provided.';
                break;
            case 'security_check_failed':
                $message = 'Security verification failed. Please try again.';
                break;
            default:
                $message = 'An error occurred.';
        }
    }
    
    // Start output
    ob_start();
    ?>
    
    <div class="<?php echo esc_attr($args['wrapper_class']); ?>">
        <?php if ($message): ?>
            <div class="mb-4 p-4 rounded-lg shadow-md <?php echo $message_type === 'success' ? 'bg-green-100 border border-green-400 text-green-700' : 'bg-red-100 border border-red-400 text-red-700'; ?> flex justify-between items-center">
                <div class="flex items-center">
                    <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?> mr-3 text-xl"></i>
                    <span class="font-medium"><?php echo esc_html($message); ?></span>
                </div>
                <button onclick="this.parentElement.parentElement.remove()" class="text-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-700 hover:text-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-900">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        <?php endif; ?>
        <?php if ($args['show_title'] || $args['show_enroll_button']): ?>
            <div class="flex items-center justify-between">
                <?php if ($args['show_title']): ?>
                    <h2 class="text-xl font-semibold text-gray-900">Course Enrollments</h2>
                <?php else: ?>
                    <div></div>
                <?php endif; ?>
                
                <?php if ($args['show_enroll_button']): ?>
                    <a href="<?php echo esc_url($args['enroll_button_url']); ?>" 
                       class="<?php echo esc_attr($args['enroll_button_class']); ?>">
                        <i class="fas fa-plus mr-2"></i>
                        <?php echo esc_html($args['enroll_button_text']); ?>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($enrollments)): ?>
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="<?php echo esc_attr($args['table_class']); ?>">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Course</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Program</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Term</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Grade</th>
                                <?php if ($args['show_actions']): ?>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($enrollments as $enrollment): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo esc_html($enrollment['course_name'] ?? 'N/A'); ?>
                                        </div>
                                        <?php if (!empty($enrollment['course_code'])): ?>
                                            <div class="text-sm text-gray-500"><?php echo esc_html($enrollment['course_code']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo esc_html($enrollment['program_name'] ?? 'N/A'); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php 
                                        $term = [];
                                        if (!empty($enrollment['year_name'])) $term[] = $enrollment['year_name'];
                                        if (!empty($enrollment['semester_name'])) $term[] = $enrollment['semester_name'];
                                        echo !empty($term) ? esc_html(implode(' - ', $term)) : 'N/A';
                                        ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs font-medium rounded-full 
                                            <?php 
                                            $status = $enrollment['status'] ?? '';
                                            echo $status === 'enrolled' ? 'bg-green-100 text-green-800' : 
                                                 ($status === 'completed' ? 'bg-blue-100 text-blue-800' : 
                                                 ($status === 'failed' ? 'bg-red-100 text-red-800' : 
                                                 ($status === 'dropped' ? 'bg-gray-100 text-gray-800' : 'bg-gray-100 text-gray-800')));
                                            ?>">
                                            <?php echo esc_html(ucfirst($status ?: 'N/A')); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php 
                                        if (!empty($enrollment['final_percentage'])) {
                                            echo esc_html($enrollment['final_percentage']) . '%';
                                        } elseif (!empty($enrollment['final_grade'])) {
                                            echo esc_html($enrollment['final_grade']);
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
                                    </td>
                                    <?php if ($args['show_actions']): ?>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <a href="<?php echo admin_url('admin.php?page=nds-course-overview&id=' . intval($enrollment['course_id'])); ?>" 
                                               class="text-blue-600 hover:text-blue-900 mr-3">
                                                <i class="fas fa-eye mr-1"></i>View
                                            </a>
                                            <button onclick="ndsUnenrollStudent(<?php echo intval($enrollment['id']); ?>, <?php echo intval($student_id); ?>)" 
                                                    class="text-red-600 hover:text-red-900">
                                                <i class="fas fa-times mr-1"></i>Unenroll
                                            </button>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-12 text-center">
                <i class="fas fa-book text-6xl text-gray-300 mb-4"></i>
                <h3 class="text-xl font-medium text-gray-900 mb-2">No Course Enrollments</h3>
                <p class="text-gray-600 mb-6">
                    <?php echo $args['empty_message'] ? esc_html($args['empty_message']) : 'This learner has not been enrolled in any courses yet.'; ?>
                </p>
                <?php if ($args['show_enroll_button']): ?>
                    <a href="<?php echo esc_url($args['enroll_button_url']); ?>" 
                       class="<?php echo esc_attr($args['enroll_button_class']); ?>">
                        <i class="fas fa-plus mr-2"></i>
                        Enroll in First Course
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <?php
    // Unenroll JavaScript function (only output once)
    static $unenroll_script_output = false;
    if (!$unenroll_script_output && $args['show_actions']): 
        $unenroll_script_output = true;
    ?>
    <script>
    function ndsUnenrollStudent(enrollmentId, studentId) {
        if (!confirm('Are you sure you want to unenroll this student from the course? This action cannot be undone.')) {
            return;
        }
        
        // Create form and submit via AJAX or POST
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '<?php echo admin_url('admin-post.php'); ?>';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'nds_unenroll_student';
        form.appendChild(actionInput);
        
        const enrollmentInput = document.createElement('input');
        enrollmentInput.type = 'hidden';
        enrollmentInput.name = 'enrollment_id';
        enrollmentInput.value = enrollmentId;
        form.appendChild(enrollmentInput);
        
        const studentInput = document.createElement('input');
        studentInput.type = 'hidden';
        studentInput.name = 'student_id';
        studentInput.value = studentId;
        form.appendChild(studentInput);
        
        const nonceInput = document.createElement('input');
        nonceInput.type = 'hidden';
        nonceInput.name = 'nds_unenroll_nonce';
        nonceInput.value = '<?php echo wp_create_nonce('nds_unenroll_student'); ?>';
        form.appendChild(nonceInput);
        
        document.body.appendChild(form);
        form.submit();
    }
    </script>
    <?php endif; ?>
    
    <?php
    return ob_get_clean();
}
