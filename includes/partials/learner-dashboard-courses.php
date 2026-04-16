<?php
/**
 * Learner Dashboard - Courses Tab with Drag & Drop Course Enrollment
 * Left: My Courses (enrolled courses as rows)
 * Right: All Courses organized by Program/Faculty (draggable rows)
 */
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$learner_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get learner info
$learner = nds_get_student($learner_id);
$learner_data = (array) $learner;

// Get active academic year and semester
$active_year = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}nds_academic_years WHERE is_active = 1 ORDER BY id DESC LIMIT 1", ARRAY_A);
$active_semester = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}nds_semesters WHERE is_active = 1 ORDER BY id DESC LIMIT 1", ARRAY_A);

// Get learner's enrolled courses
$enrolled_courses = $wpdb->get_results($wpdb->prepare(
    "SELECT e.id as enrollment_id, e.course_id, e.enrollment_date, e.status as enrollment_status,
            c.code as course_code, c.name as course_name, c.description as course_description,
            c.credits, c.nqf_level, c.start_date, c.end_date,
            p.id as program_id, p.name as program_name, p.code as program_code,
            f.id as faculty_id, f.name as faculty_name, f.code as faculty_code
     FROM {$wpdb->prefix}nds_student_enrollments e
     INNER JOIN {$wpdb->prefix}nds_courses c ON e.course_id = c.id
     LEFT JOIN {$wpdb->prefix}nds_programs p ON c.program_id = p.id
     LEFT JOIN {$wpdb->prefix}nds_faculties f ON p.faculty_id = f.id
     WHERE e.student_id = %d
     ORDER BY f.name, p.name, c.name",
    $learner_id
), ARRAY_A);

$enrolled_course_ids = array_column($enrolled_courses, 'course_id');

// Get all active courses with their program and faculty info
$all_courses = $wpdb->get_results("
    SELECT c.*, 
           p.id as program_id, p.name as program_name, p.code as program_code,
           f.id as faculty_id, f.name as faculty_name, f.code as faculty_code
    FROM {$wpdb->prefix}nds_courses c
    LEFT JOIN {$wpdb->prefix}nds_programs p ON c.program_id = p.id
    LEFT JOIN {$wpdb->prefix}nds_faculties f ON p.faculty_id = f.id
    WHERE c.status = 'active'
    ORDER BY f.name, p.name, c.name
", ARRAY_A);

// Group courses by faculty and program
$courses_by_faculty_program = [];
foreach ($all_courses as $course) {
    $faculty_id = $course['faculty_id'] ?? 0;
    $program_id = $course['program_id'] ?? 0;
    
    if (!isset($courses_by_faculty_program[$faculty_id])) {
        $courses_by_faculty_program[$faculty_id] = [];
    }
    if (!isset($courses_by_faculty_program[$faculty_id][$program_id])) {
        $courses_by_faculty_program[$faculty_id][$program_id] = [];
    }
    $courses_by_faculty_program[$faculty_id][$program_id][] = $course;
}

// Get all faculties
$all_faculties = $wpdb->get_results("
    SELECT f.*
    FROM {$wpdb->prefix}nds_faculties f
    WHERE f.status = 'active'
    ORDER BY f.name
", ARRAY_A);

// Get all programs
$all_programs = $wpdb->get_results("
    SELECT p.*, f.id as faculty_id, f.name as faculty_name
    FROM {$wpdb->prefix}nds_programs p
    LEFT JOIN {$wpdb->prefix}nds_faculties f ON p.faculty_id = f.id
    WHERE p.status = 'active'
    ORDER BY f.name, p.name
", ARRAY_A);
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-semibold text-gray-900">Course Enrollments</h2>
            <p class="text-sm text-gray-500 mt-1">Drag and drop courses to enroll this learner</p>
        </div>
    </div>

    <!-- Drag Instruction -->
    <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 flex items-center">
        <i class="fas fa-info-circle text-amber-600 mr-3 text-xl"></i>
        <div>
            <strong class="text-amber-900">Drag & Drop:</strong>
            <span class="text-amber-800 text-sm ml-2">Drag courses from the right (organized by Program/Faculty) and drop them into "My Courses" on the left to enroll this learner.</span>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Column: My Courses -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 sticky top-4">
                <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                    <i class="fas fa-book text-green-600 mr-2"></i>
                    My Courses
                    <span class="ml-2 px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs font-medium">
                        <?php echo count($enrolled_courses); ?>
                    </span>
                </h3>
                
                <div id="my-courses" class="space-y-2 min-h-[200px] border-2 border-dashed border-gray-300 rounded-lg p-4 drop-zone" 
                     style="background-color: #f9fafb;">
                    <?php if (!empty($enrolled_courses)): ?>
                        <?php foreach ($enrolled_courses as $course): ?>
                            <div class="course-item enrolled-course bg-white border-2 border-green-200 rounded-lg p-3 hover:shadow-md transition-shadow"
                                 data-course-id="<?php echo intval($course['course_id']); ?>"
                                 data-enrollment-id="<?php echo intval($course['enrollment_id']); ?>"
                                 data-course-name="<?php echo esc_attr($course['course_name']); ?>">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1 min-w-0">
                                        <h4 class="text-sm font-semibold text-gray-900 truncate">
                                            <?php echo esc_html($course['course_name']); ?>
                                        </h4>
                                        <div class="flex items-center gap-2 mt-1 text-xs text-gray-600">
                                            <?php if (!empty($course['course_code'])): ?>
                                                <span><?php echo esc_html($course['course_code']); ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($course['program_name'])): ?>
                                                <span class="text-gray-400">•</span>
                                                <span class="truncate"><?php echo esc_html($course['program_name']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($course['faculty_name'])): ?>
                                            <p class="text-xs text-gray-500 mt-1 truncate">
                                                <?php echo esc_html($course['faculty_name']); ?>
                                            </p>
                                        <?php endif; ?>
                                        <div class="flex items-center gap-3 mt-2 text-xs">
                                            <?php if (!empty($course['credits'])): ?>
                                                <span class="text-gray-500">
                                                    <i class="fas fa-certificate mr-1"></i><?php echo intval($course['credits']); ?> credits
                                                </span>
                                            <?php endif; ?>
                                            <?php if (!empty($course['nqf_level'])): ?>
                                                <span class="text-gray-500">
                                                    <i class="fas fa-layer-group mr-1"></i>NQF <?php echo intval($course['nqf_level']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <button onclick="ndsUnenrollFromCourse(<?php echo intval($course['enrollment_id']); ?>, <?php echo intval($learner_id); ?>, <?php echo intval($course['course_id']); ?>)" 
                                            class="text-red-600 hover:text-red-800 ml-2 flex-shrink-0 p-1">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-8 text-gray-500">
                            <i class="fas fa-book text-3xl mb-2 text-gray-300"></i>
                            <p class="text-sm font-medium">No courses enrolled</p>
                            <p class="text-xs mt-1 text-gray-400">Drag courses from the right to enroll</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right Column: Available Courses by Faculty/Program -->
        <div class="lg:col-span-2">
            <div class="space-y-6">
                <?php if (!empty($all_faculties)): ?>
                    <?php foreach ($all_faculties as $faculty): ?>
                        <?php 
                        $faculty_id = $faculty['id'];
                        $faculty_programs = array_filter($all_programs, function($p) use ($faculty_id) {
                            return ($p['faculty_id'] ?? 0) == $faculty_id;
                        });
                        ?>
                        
                        <?php if (!empty($faculty_programs)): ?>
                            <!-- Faculty Section -->
                            <div class="bg-gradient-to-r from-purple-50 to-indigo-50 rounded-lg border-2 border-purple-200 p-6">
                                <div class="flex items-center justify-between mb-4">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-12 h-12 bg-purple-600 rounded-lg flex items-center justify-center">
                                            <i class="fas fa-university text-white text-xl"></i>
                                        </div>
                                        <div>
                                            <h3 class="text-xl font-bold text-gray-900">
                                                <?php echo esc_html($faculty['name']); ?>
                                            </h3>
                                            <?php if (!empty($faculty['code'])): ?>
                                                <p class="text-sm text-gray-600"><?php echo esc_html($faculty['code']); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Programs under this Faculty -->
                                <div class="space-y-4">
                                    <?php foreach ($faculty_programs as $program): ?>
                                        <?php 
                                        $program_id = $program['id'];
                                        $program_courses = $courses_by_faculty_program[$faculty_id][$program_id] ?? [];
                                        ?>
                                        
                                        <?php if (!empty($program_courses)): ?>
                                            <!-- Program Section -->
                                            <div class="bg-white rounded-lg border border-gray-200 p-4">
                                                <div class="flex items-center justify-between mb-3">
                                                    <h4 class="text-md font-semibold text-gray-900">
                                                        <?php echo esc_html($program['name']); ?>
                                                        <?php if (!empty($program['code'])): ?>
                                                            <span class="text-sm text-gray-500 font-normal">(<?php echo esc_html($program['code']); ?>)</span>
                                                        <?php endif; ?>
                                                    </h4>
                                                    <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-xs font-medium">
                                                        <?php echo count($program_courses); ?> course<?php echo count($program_courses) !== 1 ? 's' : ''; ?>
                                                    </span>
                                                </div>

                                                <!-- Courses as draggable rows -->
                                                <div class="space-y-2">
                                                    <?php foreach ($program_courses as $course): ?>
                                                        <?php 
                                                        $course_id = $course['id'];
                                                        $is_enrolled = in_array($course_id, $enrolled_course_ids);
                                                        ?>
                                                        
                                                        <div class="course-item draggable-course bg-white border-2 <?php echo $is_enrolled ? 'border-green-300 bg-green-50 opacity-60' : 'border-gray-200 hover:border-blue-300 hover:shadow-sm'; ?> rounded-lg p-3 transition-all <?php echo $is_enrolled ? 'cursor-not-allowed' : 'cursor-grab hover:bg-blue-50'; ?>"
                                                             <?php if (!$is_enrolled): ?>draggable="true"<?php else: ?>draggable="false"<?php endif; ?>
                                                             data-course-id="<?php echo intval($course_id); ?>"
                                                             data-course-name="<?php echo esc_attr($course['name']); ?>"
                                                             data-course-code="<?php echo esc_attr($course['code'] ?? ''); ?>"
                                                             data-program-id="<?php echo intval($program_id); ?>"
                                                             data-program-name="<?php echo esc_attr($program['name']); ?>"
                                                             data-faculty-id="<?php echo intval($faculty_id); ?>"
                                                             data-faculty-name="<?php echo esc_attr($faculty['name']); ?>">
                                                            <div class="flex items-start justify-between">
                                                                <div class="flex-1 min-w-0">
                                                                    <div class="flex items-center gap-2">
                                                                        <?php if (!$is_enrolled): ?>
                                                                            <i class="fas fa-grip-vertical text-gray-400 mr-1"></i>
                                                                        <?php endif; ?>
                                                                        <h5 class="text-sm font-semibold text-gray-900 truncate">
                                                                            <?php echo esc_html($course['name']); ?>
                                                                        </h5>
                                                                        <?php if ($is_enrolled): ?>
                                                                            <span class="px-2 py-0.5 bg-green-100 text-green-800 rounded-full text-xs font-medium flex-shrink-0">
                                                                                <i class="fas fa-check-circle mr-1"></i>Enrolled
                                                                            </span>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                    <div class="flex items-center gap-2 mt-1 text-xs text-gray-600">
                                                                        <?php if (!empty($course['code'])): ?>
                                                                            <span><?php echo esc_html($course['code']); ?></span>
                                                                        <?php endif; ?>
                                                                        <?php if (!empty($course['credits'])): ?>
                                                                            <span class="text-gray-400">•</span>
                                                                            <span><i class="fas fa-certificate mr-1"></i><?php echo intval($course['credits']); ?> credits</span>
                                                                        <?php endif; ?>
                                                                        <?php if (!empty($course['nqf_level'])): ?>
                                                                            <span class="text-gray-400">•</span>
                                                                            <span><i class="fas fa-layer-group mr-1"></i>NQF <?php echo intval($course['nqf_level']); ?></span>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                    <?php if (!empty($course['description'])): ?>
                                                                        <p class="text-xs text-gray-500 mt-1 line-clamp-2"><?php echo esc_html($course['description']); ?></p>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-12 text-center">
                        <i class="fas fa-university text-6xl text-gray-300 mb-4"></i>
                        <h3 class="text-xl font-medium text-gray-900 mb-2">No Faculties Found</h3>
                        <p class="text-gray-600">Please create faculties and programs first.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.draggable-course {
    user-select: none;
    transition: all 0.2s;
}

.draggable-course:active {
    cursor: grabbing;
}

.draggable-course.dragging {
    opacity: 0.5;
    transform: scale(0.98);
}

#my-courses {
    transition: all 0.2s;
    min-height: 200px;
}

#my-courses.drag-over {
    background-color: #eff6ff !important;
    border-color: #3b82f6 !important;
    border-style: solid !important;
}

.drop-zone {
    position: relative;
}

.drop-zone::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    border-radius: 0.5rem;
    pointer-events: none;
    transition: all 0.2s;
}

.drop-zone.drag-over::before {
    background-color: rgba(59, 130, 246, 0.1);
}

.enrolled-course {
    transition: all 0.2s;
}

.enrolled-course:hover {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}
</style>

<script>
let draggedCourse = null;

// Initialize drag and drop
document.addEventListener('DOMContentLoaded', function() {
    initializeDragAndDrop();
});

function initializeDragAndDrop() {
    const draggableCourses = document.querySelectorAll('.draggable-course');
    const myCoursesContainer = document.getElementById('my-courses');

    // Make courses draggable
    draggableCourses.forEach(course => {
        const isDraggable = course.getAttribute('draggable') === 'true';
        if (isDraggable) {
            course.addEventListener('dragstart', handleDragStart);
            course.addEventListener('dragend', handleDragEnd);
        }
    });

    // Make "My Courses" container droppable
    if (myCoursesContainer) {
        myCoursesContainer.addEventListener('dragover', handleDragOver);
        myCoursesContainer.addEventListener('dragleave', handleDragLeave);
        myCoursesContainer.addEventListener('drop', handleDrop);
    }
}

function handleDragStart(e) {
    draggedCourse = this;
    this.classList.add('dragging');
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', this.dataset.courseId);
    console.log('Drag started:', this.dataset.courseName);
}

function handleDragEnd(e) {
    this.classList.remove('dragging');
    draggedCourse = null;
    
    // Remove drag-over class
    const myCourses = document.getElementById('my-courses');
    if (myCourses) {
        myCourses.classList.remove('drag-over');
    }
}

function handleDragOver(e) {
    e.preventDefault();
    e.stopPropagation();
    e.dataTransfer.dropEffect = 'move';
    this.classList.add('drag-over');
}

function handleDragLeave(e) {
    this.classList.remove('drag-over');
}

function handleDrop(e) {
    e.preventDefault();
    e.stopPropagation();
    this.classList.remove('drag-over');
    
    console.log('Drop event triggered');
    
    if (!draggedCourse) {
        console.log('No dragged course found');
        return;
    }
    
    // Store reference to dragged course element
    const draggedElement = draggedCourse;
    const courseId = draggedElement.dataset.courseId;
    const courseName = draggedElement.dataset.courseName;
    const courseCode = draggedElement.dataset.courseCode;
    const programName = draggedElement.dataset.programName;
    const facultyName = draggedElement.dataset.facultyName;
    const studentId = <?php echo intval($learner_id); ?>;
    
    // Check if already enrolled
    const existingCourse = this.querySelector(`[data-course-id="${courseId}"]`);
    if (existingCourse) {
        alert('This learner is already enrolled in this course.');
        return;
    }
    
    // Show loading state
    this.style.opacity = '0.6';
    
    // Enroll in course via AJAX
    const formData = new FormData();
    formData.append('action', 'nds_enroll_student_course');
    formData.append('student_id', studentId);
    formData.append('course_id', courseId);
    formData.append('nonce', '<?php echo wp_create_nonce('nds_enroll_student_course'); ?>');
    
    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        this.style.opacity = '1';
        
        if (data.success) {
            // Create enrolled course row
            const courseRow = document.createElement('div');
            courseRow.className = 'course-item enrolled-course bg-white border-2 border-green-200 rounded-lg p-3 hover:shadow-md transition-shadow';
            courseRow.dataset.courseId = courseId;
            courseRow.dataset.enrollmentId = data.data.enrollment_id || '';
            courseRow.dataset.courseName = courseName;
            courseRow.innerHTML = `
                <div class="flex items-start justify-between">
                    <div class="flex-1 min-w-0">
                        <h4 class="text-sm font-semibold text-gray-900 truncate">${courseName}</h4>
                        <div class="flex items-center gap-2 mt-1 text-xs text-gray-600">
                            ${courseCode ? `<span>${courseCode}</span>` : ''}
                            ${programName ? `<span class="text-gray-400">•</span><span class="truncate">${programName}</span>` : ''}
                        </div>
                        ${facultyName ? `<p class="text-xs text-gray-500 mt-1 truncate">${facultyName}</p>` : ''}
                    </div>
                    <button onclick="ndsUnenrollFromCourse(${data.data.enrollment_id || ''}, ${studentId}, ${courseId})" 
                            class="text-red-600 hover:text-red-800 ml-2 flex-shrink-0 p-1">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            
            // Remove empty state if exists
            const emptyState = this.querySelector('.text-center.py-8');
            if (emptyState) {
                emptyState.remove();
            }
            
            this.appendChild(courseRow);
            
            // Update dragged course to show as enrolled
            if (draggedElement && draggedElement.parentElement) {
                draggedElement.setAttribute('draggable', 'false');
                draggedElement.classList.add('border-green-300', 'bg-green-50', 'opacity-60', 'cursor-not-allowed');
                draggedElement.classList.remove('hover:border-blue-300', 'hover:shadow-sm', 'hover:bg-blue-50', 'cursor-grab');
                
                // Remove grip icon
                const gripIcon = draggedElement.querySelector('.fa-grip-vertical');
                if (gripIcon) gripIcon.remove();
                
                // Add enrolled badge
                const h5Element = draggedElement.querySelector('h5');
                if (h5Element && h5Element.parentElement) {
                    const existingBadge = h5Element.parentElement.querySelector('[class*="bg-green-100"]');
                    if (!existingBadge) {
                        const enrolledBadge = document.createElement('span');
                        enrolledBadge.className = 'px-2 py-0.5 bg-green-100 text-green-800 rounded-full text-xs font-medium flex-shrink-0';
                        enrolledBadge.innerHTML = '<i class="fas fa-check-circle mr-1"></i>Enrolled';
                        h5Element.parentElement.appendChild(enrolledBadge);
                    }
                }
            }
            
            // Update course count
            const courseCountBadge = document.querySelector('.fa-book').parentElement.querySelector('[class*="bg-green-100"]');
            if (courseCountBadge) {
                const currentCount = parseInt(courseCountBadge.textContent) || 0;
                courseCountBadge.textContent = currentCount + 1;
            }
            
            // Show success message
            showMessage('Course enrolled successfully!', 'success');
        } else {
            const errorMsg = data.data || 'Failed to enroll in course';
            console.error('Enrollment error:', errorMsg);
            showMessage('Error: ' + errorMsg, 'error');
        }
    })
    .catch(error => {
        this.style.opacity = '1';
        console.error('AJAX Error:', error);
        const errorMessage = error.message || 'An error occurred while enrolling in the course.';
        alert('Error: ' + errorMessage + '\n\nPlease check the browser console for more details.');
    });
}

function ndsUnenrollFromCourse(enrollmentId, studentId, courseId) {
    if (!confirm('Are you sure you want to unenroll from this course?')) {
        return;
    }
    
    // Find the course row element
    const courseRow = document.querySelector(`[data-course-id="${courseId}"].enrolled-course`);
    if (!courseRow) {
        alert('Course row not found');
        return;
    }
    
    // Show loading state
    courseRow.style.opacity = '0.6';
    courseRow.style.pointerEvents = 'none';
    
    // Prepare AJAX request
    const formData = new FormData();
    formData.append('action', 'nds_unenroll_student_course');
    formData.append('enrollment_id', enrollmentId);
    formData.append('student_id', studentId);
    formData.append('course_id', courseId);
    formData.append('nonce', '<?php echo wp_create_nonce('nds_unenroll_student_course'); ?>');
    
    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // Remove the course row from "My Courses"
            courseRow.remove();
            
            // Check if "My Courses" is now empty
            const myCoursesContainer = document.getElementById('my-courses');
            const remainingCourses = myCoursesContainer.querySelectorAll('.enrolled-course');
            
            if (remainingCourses.length === 0) {
                // Show empty state
                const emptyState = document.createElement('div');
                emptyState.className = 'text-center py-8 text-gray-500';
                emptyState.innerHTML = `
                    <i class="fas fa-book text-3xl mb-2 text-gray-300"></i>
                    <p class="text-sm font-medium">No courses enrolled</p>
                    <p class="text-xs mt-1 text-gray-400">Drag courses from the right to enroll</p>
                `;
                myCoursesContainer.appendChild(emptyState);
            }
            
            // Update course on the right to make it draggable again
            const courseOnRight = document.querySelector(`.draggable-course[data-course-id="${courseId}"]`);
            if (courseOnRight) {
                courseOnRight.setAttribute('draggable', 'true');
                courseOnRight.classList.remove('border-green-300', 'bg-green-50', 'opacity-60', 'cursor-not-allowed');
                courseOnRight.classList.add('border-gray-200', 'hover:border-blue-300', 'hover:shadow-sm', 'hover:bg-blue-50', 'cursor-grab');
                
                // Remove enrolled badge
                const enrolledBadge = courseOnRight.querySelector('[class*="bg-green-100"]');
                if (enrolledBadge) {
                    enrolledBadge.remove();
                }
                
                // Add grip icon back
                const h5Element = courseOnRight.querySelector('h5');
                if (h5Element && h5Element.parentElement) {
                    const gripIcon = document.createElement('i');
                    gripIcon.className = 'fas fa-grip-vertical text-gray-400 mr-1';
                    h5Element.parentElement.insertBefore(gripIcon, h5Element);
                }
                
                // Re-initialize drag handlers
                courseOnRight.addEventListener('dragstart', handleDragStart);
                courseOnRight.addEventListener('dragend', handleDragEnd);
            }
            
            // Update course count
            const courseCountBadge = document.querySelector('.fa-book').parentElement.querySelector('[class*="bg-green-100"]');
            if (courseCountBadge) {
                const currentCount = parseInt(courseCountBadge.textContent) || 0;
                courseCountBadge.textContent = Math.max(0, currentCount - 1);
            }
            
            // Show success message
            showMessage('Course unenrolled successfully.', 'success');
        } else {
            courseRow.style.opacity = '1';
            courseRow.style.pointerEvents = 'auto';
            const errorMsg = data.data || 'Failed to unenroll from course';
            console.error('Unenrollment error:', errorMsg);
            showMessage('Error: ' + errorMsg, 'error');
        }
    })
    .catch(error => {
        courseRow.style.opacity = '1';
        courseRow.style.pointerEvents = 'auto';
        console.error('AJAX Error:', error);
        const errorMessage = error.message || 'An error occurred while unenrolling from the course.';
        showMessage('Error: ' + errorMessage, 'error');
    });
}

function showMessage(message, type) {
    const messageDiv = document.createElement('div');
    messageDiv.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 ${
        type === 'success' ? 'bg-green-100 border border-green-400 text-green-700' : 'bg-red-100 border border-red-400 text-red-700'
    }`;
    messageDiv.innerHTML = `
        <div class="flex items-center">
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} mr-2"></i>
            <span>${message}</span>
        </div>
    `;
    document.body.appendChild(messageDiv);
    
    setTimeout(() => {
        messageDiv.remove();
    }, 3000);
}
</script>
