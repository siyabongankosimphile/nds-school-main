<?php
/**
 * Learner Dashboard - Timetable Tab
 * Displays the learner's class schedule/timetable with Day, Week, Month, Year views
 */
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$learner_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$learner = nds_get_student($learner_id);
$learner_data = (array) $learner;

// Get current view (default to week)
$current_view = isset($_GET['view']) ? sanitize_text_field($_GET['view']) : 'week';
$current_date = isset($_GET['date']) ? sanitize_text_field($_GET['date']) : date('Y-m-d');

// Get learner's enrolled courses with schedule information
$enrollments = $wpdb->get_results($wpdb->prepare(
    "SELECT e.*, c.id as course_id, c.name as course_name, c.code as course_code, c.description as course_description,
            p.id as program_id, p.name as program_name, p.code as program_code,
            ay.year_name, s.semester_name, s.start_date as semester_start, s.end_date as semester_end
     FROM {$wpdb->prefix}nds_student_enrollments e
     LEFT JOIN {$wpdb->prefix}nds_courses c ON e.course_id = c.id
     LEFT JOIN {$wpdb->prefix}nds_programs p ON c.program_id = p.id
     LEFT JOIN {$wpdb->prefix}nds_academic_years ay ON e.academic_year_id = ay.id
     LEFT JOIN {$wpdb->prefix}nds_semesters s ON e.semester_id = s.id
     WHERE e.student_id = %d AND e.status IN ('enrolled', 'applied')
     ORDER BY c.name",
    $learner_id
), ARRAY_A);

// Get active academic year and semester
$active_year = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}nds_academic_years WHERE is_active = 1 ORDER BY id DESC LIMIT 1", ARRAY_A);
$active_semester = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}nds_semesters WHERE is_active = 1 ORDER BY id DESC LIMIT 1", ARRAY_A);

// Get course schedules for enrolled courses
$course_ids = array_column($enrollments, 'course_id');
$schedules = [];
if (!empty($course_ids)) {
    $placeholders = implode(',', array_fill(0, count($course_ids), '%d'));
    $schedules = $wpdb->get_results($wpdb->prepare(
        "SELECT cs.*, c.name as course_name, c.code as course_code,
                s.first_name as lecturer_first_name, s.last_name as lecturer_last_name
         FROM {$wpdb->prefix}nds_course_schedules cs
         LEFT JOIN {$wpdb->prefix}nds_courses c ON cs.course_id = c.id
         LEFT JOIN {$wpdb->prefix}nds_staff s ON cs.lecturer_id = s.id
         WHERE cs.course_id IN ($placeholders) AND cs.is_active = 1
         ORDER BY cs.days, cs.start_time",
        $course_ids
    ), ARRAY_A);
}

// Organize schedules by day and time
$schedule_grid = [];
$day_names = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
$day_map = [
    'monday' => 'Monday',
    'tuesday' => 'Tuesday',
    'wednesday' => 'Wednesday',
    'thursday' => 'Thursday',
    'friday' => 'Friday',
    'saturday' => 'Saturday',
    'sunday' => 'Sunday',
    'mon' => 'Monday',
    'tue' => 'Tuesday',
    'wed' => 'Wednesday',
    'thu' => 'Thursday',
    'fri' => 'Friday',
    'sat' => 'Saturday',
    'sun' => 'Sunday'
];

foreach ($schedules as $schedule) {
    $days_str = $schedule['days'] ?? '';
    $start_time = $schedule['start_time'] ?? '';
    $end_time = $schedule['end_time'] ?? '';
    
    if ($days_str && $start_time) {
        // Handle multiple days (comma-separated) or single day
        $days_array = array_map('trim', explode(',', $days_str));
        
        foreach ($days_array as $day) {
            // Normalize day name
            $day_lower = strtolower(trim($day));
            $day_normalized = $day_map[$day_lower] ?? ucfirst($day_lower);
            
            if (in_array($day_normalized, $day_names)) {
                if (!isset($schedule_grid[$day_normalized])) {
                    $schedule_grid[$day_normalized] = [];
                }
                $schedule_grid[$day_normalized][] = $schedule;
            }
        }
    }
}

// Calculate date ranges for different views
$date_obj = new DateTime($current_date);
$view_dates = [];

switch ($current_view) {
    case 'day':
        $view_dates = [$date_obj->format('Y-m-d')];
        break;
    case 'week':
        // Get Monday of the week
        $monday = clone $date_obj;
        $monday->modify('monday this week');
        for ($i = 0; $i < 7; $i++) {
            $day = clone $monday;
            $day->modify("+$i days");
            $view_dates[] = $day->format('Y-m-d');
        }
        break;
    case 'month':
        $first_day = clone $date_obj;
        $first_day->modify('first day of this month');
        $last_day = clone $date_obj;
        $last_day->modify('last day of this month');
        $current = clone $first_day;
        while ($current <= $last_day) {
            $view_dates[] = $current->format('Y-m-d');
            $current->modify('+1 day');
        }
        break;
    case 'year':
        // Show all months of the year
        $year = $date_obj->format('Y');
        $first_day = new DateTime("$year-01-01");
        $last_day = new DateTime("$year-12-31");
        $current = clone $first_day;
        while ($current <= $last_day) {
            $view_dates[] = $current->format('Y-m-d');
            $current->modify('+1 day');
        }
        break;
}

// Time slots for hourly display
$time_slots = [];
for ($hour = 6; $hour <= 22; $hour++) {
    $time_slots[] = sprintf('%02d:00', $hour);
}
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between flex-wrap gap-4">
        <div>
            <h2 class="text-xl font-semibold text-gray-900">Class Timetable</h2>
            <p class="text-sm text-gray-500 mt-1">
                <?php if ($active_year && $active_semester): ?>
                    <?php echo esc_html($active_year['year_name'] ?? 'Current'); ?> - <?php echo esc_html($active_semester['semester_name'] ?? 'Semester'); ?>
                <?php else: ?>
                    Current Academic Period
                <?php endif; ?>
            </p>
        </div>
    </div>

    <?php if (empty($enrollments)): ?>
        <!-- Empty State -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-12 text-center">
            <i class="fas fa-calendar-times text-6xl text-gray-300 mb-4"></i>
            <h3 class="text-xl font-medium text-gray-900 mb-2">No Enrolled Courses</h3>
            <p class="text-gray-600 mb-6">You are not enrolled in any courses yet.</p>
        </div>
    <?php else: ?>
        <!-- FullCalendar Timetable View -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <div id="nds-frontend-calendar" style="min-height: 600px;"></div>
            </div>
            
        <!-- Event Details Modal (Frontend) -->
        <div id="nds-event-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50" onclick="if(event.target === this) closeEventModal();">
            <div class="flex items-center justify-center min-h-screen p-4">
                <div class="bg-white rounded-xl shadow-xl max-w-lg w-full max-h-screen overflow-y-auto">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-6">
                            <h2 id="event-modal-title" class="text-2xl font-bold text-gray-900">Event Details</h2>
                            <button type="button" onclick="closeEventModal()" class="text-gray-400 hover:text-gray-600">
                                <i class="fas fa-times text-xl"></i>
                            </button>
            </div>
            
                        <div id="event-modal-content">
                            <!-- Content will be populated by JavaScript -->
            </div>
            
                        <div class="flex items-center justify-end mt-8 pt-6 border-t border-gray-200">
                            <button type="button" onclick="closeEventModal()"
                                class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-all duration-200 font-medium">
                                Close
                            </button>
                            </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Enrolled Courses Summary -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                <i class="fas fa-book-open text-blue-600 mr-2"></i>
                Enrolled Courses
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($enrollments as $enrollment): ?>
                    <?php 
                    $course_schedules = array_filter($schedules, function($s) use ($enrollment) {
                        return $s['course_id'] == $enrollment['course_id'];
                    });
                    ?>
                    <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                        <div class="flex items-start justify-between mb-2">
                            <div class="flex-1">
                                <h4 class="text-sm font-semibold text-gray-900">
                                    <?php echo esc_html($enrollment['course_name'] ?? 'N/A'); ?>
                                </h4>
                                <?php if (!empty($enrollment['course_code'])): ?>
                                    <p class="text-xs text-gray-500 mt-1"><?php echo esc_html($enrollment['course_code']); ?></p>
                                <?php endif; ?>
                                <?php if (!empty($enrollment['program_name'])): ?>
                                    <p class="text-xs text-gray-400 mt-1">
                                        <i class="fas fa-graduation-cap mr-1"></i>
                                        <?php echo esc_html($enrollment['program_name']); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                            <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-xs font-medium">
                                <?php echo esc_html(ucfirst($enrollment['status'] ?? 'enrolled')); ?>
                            </span>
                        </div>
                        <?php if (!empty($enrollment['course_description'])): ?>
                            <p class="text-xs text-gray-600 mt-2 line-clamp-2">
                                <?php echo esc_html($enrollment['course_description']); ?>
                            </p>
                        <?php endif; ?>
                        <div class="mt-3 pt-3 border-t border-gray-100">
                            <div class="flex items-center justify-between text-xs text-gray-500">
                                <span>
                                    <i class="fas fa-calendar mr-1"></i>
                                    <?php echo esc_html($enrollment['semester_name'] ?? 'N/A'); ?>
                                </span>
                                <?php if (!empty($course_schedules)): ?>
                                    <span class="text-green-600">
                                        <i class="fas fa-check-circle mr-1"></i>
                                        <?php echo count($course_schedules); ?> schedule<?php echo count($course_schedules) !== 1 ? 's' : ''; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-gray-400">
                                        <i class="fas fa-clock mr-1"></i>
                                        Schedule TBD
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Info Box -->
        <?php if (empty($schedules)): ?>
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 flex items-start">
                <i class="fas fa-info-circle text-blue-600 mr-3 text-xl mt-0.5"></i>
                <div>
                    <strong class="text-blue-900">Timetable Information</strong>
                    <p class="text-blue-800 text-sm mt-1">
                        Class schedules for your enrolled courses will be displayed here once they are assigned by your instructors.
                        Contact your program coordinator for detailed schedule information.
                    </p>
                </div>
            </div>
        <?php else: ?>
            <div class="bg-green-50 border border-green-200 rounded-lg p-4 flex items-start">
                <i class="fas fa-check-circle text-green-600 mr-3 text-xl mt-0.5"></i>
                <div>
                    <strong class="text-green-900">Timetable Active</strong>
                    <p class="text-green-800 text-sm mt-1">
                        Your class schedule is displayed above. Use the view buttons (Day, Week, Month, Year) to see different perspectives of your timetable.
                        Please arrive on time for all scheduled classes.
                    </p>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<style>
@media print {
    .bg-white {
        background: white !important;
    }
    .shadow-sm {
        box-shadow: none !important;
    }
    button, a[href] {
        display: none !important;
    }
    .sticky {
        position: static !important;
    }
}

/* Time blocking styles */
.timetable-block {
    position: relative;
    border-left: 4px solid;
    border-radius: 4px;
    padding: 8px;
    margin-bottom: 4px;
}

/* Ensure proper spacing in timetable cells */
td {
    vertical-align: top;
    position: relative;
}
</style>
