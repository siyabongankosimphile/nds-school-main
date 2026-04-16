<?php
/**
 * NDS Calendar
 * Full calendar with Day, Week, Month, Year views
 * Displays custom calendar events and active programs
 */

if (!defined('ABSPATH')) {
    exit; // Prevent direct access
}

class NDS_Calendar {
    
    public function __construct() {
        // Constructor - can be used for initialization
    }
    
    /**
     * Enqueue FullCalendar CSS and JS
     */
    private function enqueue_calendar_scripts() {
        // Only load on calendar page
        if (!isset($_GET['page']) || $_GET['page'] !== 'nds-calendar') {
            return;
        }
        
        // FullCalendar CSS
        wp_enqueue_style(
            'fullcalendar-css',
            'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css',
            array(),
            '6.1.10'
        );
        
        // FullCalendar JS
        wp_enqueue_script(
            'fullcalendar-js',
            'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js',
            array('jquery'),
            '6.1.10',
            true
        );
        
        // Custom calendar JS
        wp_enqueue_script(
            'nds-admin-calendar',
            plugin_dir_url(dirname(__FILE__)) . 'assets/js/admin-calendar.js',
            array('jquery', 'fullcalendar-js'),
            filemtime(plugin_dir_path(dirname(__FILE__)) . 'assets/js/admin-calendar.js'),
            true
        );
        
        // Localize script for AJAX
        wp_localize_script('nds-admin-calendar', 'ndsCalendar', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('nds_calendar_nonce')
        ));
        
        // Enqueue Tailwind CSS
        $plugin_dir = plugin_dir_path(dirname(__FILE__));
        $css_file = $plugin_dir . '../assets/css/frontend.css';
        if (file_exists($css_file)) {
            wp_enqueue_style(
                'nds-tailwindcss-calendar',
                plugin_dir_url(dirname(__FILE__)) . '../assets/css/frontend.css',
                array(),
                filemtime($css_file),
                'all'
            );
        }
        
        wp_enqueue_style('nds-icons', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css', array(), null, 'all');
    }
    
    /**
     * Render the calendar page content
     */
    public function render_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        // Enqueue scripts
        $this->enqueue_calendar_scripts();
        
        global $wpdb;
        
        // Get statistics
        $calendar_events_table = $wpdb->prefix . 'nds_calendar_events';
        $programs_table = $wpdb->prefix . 'nds_programs';
        $schedules_table = $wpdb->prefix . 'nds_course_schedules';
        
        $total_events = 0;
        $events_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$calendar_events_table'") === $calendar_events_table;
        if ($events_table_exists) {
            $total_events = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$calendar_events_table} WHERE status = 'active'");
        }
        
        $total_programs = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$programs_table} WHERE status = 'active'");
        $total_schedules = 0;
        $schedules_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$schedules_table'") === $schedules_table;
        if ($schedules_table_exists) {
            $total_schedules = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$schedules_table} WHERE is_active = 1");
        }

        // --- NEW: Fetch lists for modals ---
        $events_list = $wpdb->get_results("SELECT id, event_title as name, start_date as details FROM {$calendar_events_table} WHERE status = 'active' ORDER BY start_date DESC LIMIT 50", ARRAY_A);
        $programs_list = $wpdb->get_results("SELECT id, name, status as details FROM {$programs_table} WHERE status = 'active' ORDER BY name ASC", ARRAY_A);
        $schedules_list = $wpdb->get_results("
            SELECT cs.id, c.name as name, CONCAT(cs.days, ' ', cs.start_time, '-', cs.end_time) as details 
            FROM {$schedules_table} cs 
            JOIN {$wpdb->prefix}nds_courses c ON cs.course_id = c.id 
            WHERE cs.is_active = 1 
            ORDER BY c.name ASC
        ", ARRAY_A);
        
        ?>
        <div class="wrap">
            <style>
                /* Ensure the WordPress footer doesn't overlap our custom dashboard */
                body[class*="nds-calendar"] #wpfooter { display: none !important; }
                .nds-tailwind-wrapper { position: relative; z-index: 1; }
            </style>
            <div class="nds-tailwind-wrapper bg-gray-50 pb-32" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin-left: -20px; padding-left: 20px; margin-top: -20px;">
                <!-- Header -->
                <div class="bg-white shadow-sm border-b border-gray-200">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div class="flex justify-between items-center py-6">
                            <div class="flex items-center space-x-4">
                                <div class="w-12 h-12 bg-gradient-to-br from-blue-600 to-indigo-600 rounded-xl flex items-center justify-center shadow-lg">
                                    <i class="fas fa-calendar text-white text-2xl"></i>
                                </div>
                                <div>
                                    <h1 class="text-3xl font-bold text-gray-900" style="margin:0; line-height:1.2;">Academy Calendar</h1>
                                    <p class="text-gray-600" style="margin:0;">View and manage calendar events, programs, and schedules across the academy.</p>
                                </div>
                            </div>
                            <div class="flex items-center space-x-3">
                                <button type="button"
                                    onclick="ndsOpenCalendarGuide()"
                                    class="inline-flex items-center px-4 py-2 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 text-sm font-medium shadow-sm transition-all duration-200">
                                    <i class="fas fa-file-pdf text-red-500 mr-2"></i>
                                    Download Guide
                                </button>
                                <button type="button" onclick="openAddEventModal()" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 px-6 rounded-lg flex items-center gap-2 transition-all duration-200 shadow-md hover:shadow-lg transform hover:-translate-y-0.5">
                                    <i class="fas fa-plus"></i>
                                    Add Event
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Breadcrumb Navigation -->
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-6 pb-4">
                    <nav class="flex items-center space-x-2 text-sm text-gray-600">
                        <a href="<?php echo admin_url('admin.php?page=nds-academy'); ?>" class="hover:text-blue-600 transition-colors flex items-center">
                            <i class="fas fa-home mr-1"></i>NDS Academy
                        </a>
                        <i class="fas fa-chevron-right text-gray-400 text-xs"></i>
                        <span class="text-gray-900 font-medium">Calendar</span>
                    </nav>
                </div>

                <!-- Main Content -->
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-8">
                    
                    <!-- Success/Error Messages -->
                    <?php if (isset($_GET['success'])): ?>
                        <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg">
                            <i class="fas fa-check-circle mr-2"></i>
                            <?php
                            switch ($_GET['success']) {
                                case 'event_created':
                                    echo 'Calendar event created successfully!';
                                    break;
                                case 'event_updated':
                                    echo 'Calendar event updated successfully!';
                                    break;
                                default:
                                    echo 'Operation completed successfully!';
                            }
                            ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_GET['error'])): ?>
                        <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg">
                            <i class="fas fa-exclamation-circle mr-2"></i>
                            <?php
                            switch ($_GET['error']) {
                                case 'missing_fields':
                                    echo 'Please fill in all required fields.';
                                    break;
                                case 'db_error':
                                    echo 'Database error occurred. Please try again.';
                                    break;
                                default:
                                    echo 'An error occurred. Please try again.';
                            }
                            ?>
                        </div>
                    <?php endif; ?>

                    <!-- Statistics Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                        <div onclick="openStatModal('events')" class="bg-white shadow-sm rounded-xl p-5 border border-gray-100 flex flex-col justify-between hover:bg-gray-50 transition-all duration-200 cursor-pointer group">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-500 group-hover:text-gray-700">Calendar Events</p>
                                    <p class="mt-2 text-2xl font-semibold text-gray-900">
                                        <?php echo number_format_i18n($total_events); ?>
                                    </p>
                                </div>
                                <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center">
                                    <i class="fas fa-calendar-alt text-blue-600 text-xl"></i>
                                </div>
                            </div>
                            <p class="mt-3 text-xs text-gray-500">
                                Active events scheduled.
                            </p>
                        </div>

                        <div onclick="openStatModal('programs')" class="bg-white shadow-sm rounded-xl p-5 border border-gray-100 flex flex-col justify-between hover:bg-gray-50 transition-all duration-200 cursor-pointer group">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-500 group-hover:text-gray-700">Active Programs</p>
                                    <p class="mt-2 text-2xl font-semibold text-gray-900">
                                        <?php echo number_format_i18n($total_programs); ?>
                                    </p>
                                </div>
                                <div class="w-10 h-10 rounded-lg bg-green-50 flex items-center justify-center">
                                    <i class="fas fa-graduation-cap text-green-600 text-xl"></i>
                                </div>
                            </div>
                            <p class="mt-3 text-xs text-gray-500">
                                Programs currently active.
                            </p>
                        </div>

                        <div onclick="openStatModal('schedules')" class="bg-white shadow-sm rounded-xl p-5 border border-gray-100 flex flex-col justify-between hover:bg-gray-50 transition-all duration-200 cursor-pointer group">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-500 group-hover:text-gray-700">Course Schedules</p>
                                    <p class="mt-2 text-2xl font-semibold text-gray-900">
                                        <?php echo number_format_i18n($total_schedules); ?>
                                    </p>
                                </div>
                                <div class="w-10 h-10 rounded-lg bg-purple-50 flex items-center justify-center">
                                    <i class="fas fa-clock text-purple-600 text-xl"></i>
                                </div>
                            </div>
                            <p class="mt-3 text-xs text-gray-500">
                                Scheduled course sessions.
                            </p>
                        </div>
                    </div>

                    <!-- Calendar Container -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-8">
                        <div class="bg-blue-600 px-6 py-4 shadow-md">
                            <h3 class="text-lg font-bold text-white flex items-center">
                                <i class="fas fa-calendar-check mr-2"></i>Calendar View
                            </h3>
                        </div>

                        <div class="p-6">
                            <div id="nds-admin-calendar" style="min-height: 600px;"></div>
                        </div>
                    </div>

                    <!-- Admin / Teacher One-Page Guide (hidden print template) -->
                    <div id="nds-calendar-guide-template" class="hidden">
                        <div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; padding: 32px; max-width: 900px; margin: 0 auto; color: #111827;">
                            <h1 style="font-size: 28px; font-weight: 800; margin-bottom: 8px;">Calendar & Timetable – Quick Guide for Staff</h1>
                            <p style="margin: 0 0 16px; color: #4B5563;">How to add events and link course timetables so students see the correct schedule.</p>

                            <h2 style="font-size: 18px; font-weight: 700; margin: 16px 0 8px;">1. Two things that appear on the calendar</h2>
                            <ul style="margin: 0 0 12px 18px; padding: 0; font-size: 14px;">
                                <li><strong>Calendar Events</strong> – one-off items like open days, exams, holidays.</li>
                                <li><strong>Course Schedules</strong> – regular weekly classes linked to specific courses (this feeds student timetables).</li>
                            </ul>

                            <h2 style="font-size: 18px; font-weight: 700; margin: 16px 0 8px;">2. Add a general Calendar Event (open day, exam week, holiday)</h2>
                            <ol style="margin: 0 0 12px 18px; padding: 0; font-size: 14px; line-height: 1.5;">
                                <li>In WordPress admin, go to <strong>NDS Academy → Calendar</strong>.</li>
                                <li>Click <strong>Add Event</strong> (top right).</li>
                                <li>Fill in:
                                    <ul style="margin: 4px 0 0 18px; padding: 0;">
                                        <li><strong>Title</strong> – e.g. “Exam Week – Diploma Culinary”.</li>
                                        <li><strong>Start / End</strong> date &amp; time (tick <strong>All Day</strong> if needed).</li>
                                        <li><strong>Location</strong> and <strong>Colour</strong> (optional).</li>
                                    </ul>
                                </li>
                                <li>Click <strong>Create Event</strong>. It now shows on the calendar for everyone.</li>
                            </ol>

                            <h2 style="font-size: 18px; font-weight: 700; margin: 16px 0 8px;">3. Link a course to a timetable (regular classes)</h2>
                            <ol style="margin: 0 0 12px 18px; padding: 0; font-size: 14px; line-height: 1.5;">
                                <li>Go to <strong>NDS Academy → Courses</strong> and <strong>Edit</strong> the course.</li>
                                <li>Find the section called <strong>Course Schedule / Timetable</strong>.</li>
                                <li>For each regular class, create a <strong>Schedule</strong> row:
                                    <ul style="margin: 4px 0 0 18px; padding: 0;">
                                        <li><strong>Days</strong> – tick all days it runs (e.g. Mon &amp; Wed).</li>
                                        <li><strong>Start / End Time</strong> – e.g. 09:00–11:00.</li>
                                        <li><strong>Location / Room</strong> – e.g. “Kitchen Lab 1”.</li>
                                        <li><strong>Session Type</strong> – Lecture, Practical, etc.</li>
                                        <li><strong>Lecturer</strong> – pick the teacher.</li>
                                    </ul>
                                </li>
                                <li>Use <strong>Add Schedule</strong> to add more rows (e.g. separate lecture and practical times).</li>
                                <li>Click <strong>Update / Save</strong> on the course.</li>
                            </ol>

                            <p style="font-size: 14px; margin: 0 0 12px;"><strong>Result:</strong> These schedules appear as purple blocks on the Calendar and feed directly into each enrolled student’s <strong>Timetable</strong> in the portal.</p>

                            <h2 style="font-size: 18px; font-weight: 700; margin: 16px 0 8px;">4. When to use which</h2>
                            <ul style="margin: 0 0 12px 18px; padding: 0; font-size: 14px;">
                                <li><strong>Use “Add Event”</strong> for special dates: open days, holidays, exam weeks.</li>
                                <li><strong>Use Course Schedule / Timetable</strong> inside each course for all regular weekly classes.</li>
                            </ul>

                            <p style="font-size: 13px; color: #6B7280; margin-top: 16px;">Tip: After updating a course schedule, open <strong>NDS Academy → Calendar</strong> in Week view to quickly check that all classes appear on the correct days and times.</p>
                        </div>
                    </div>
                </div>

                <!-- Add Event Modal -->
                <div id="addEventModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
                    <div class="flex items-center justify-center min-h-screen p-4">
                        <div class="bg-white rounded-xl shadow-xl max-w-2xl w-full max-h-screen overflow-y-auto">
                            <div class="p-6">
                                <div class="flex items-center justify-between mb-6">
                                    <h2 class="text-2xl font-bold text-gray-900">Add New Calendar Event</h2>
                                    <button type="button" onclick="closeEventModal()" class="text-gray-400 hover:text-gray-600">
                                        <i class="fas fa-times text-xl"></i>
                                    </button>
                                </div>

                                <form id="addEventForm" method="POST" action="<?php echo admin_url('admin-post.php'); ?>">
                                    <?php wp_nonce_field('nds_add_calendar_event_nonce', 'nds_add_calendar_event_nonce'); ?>
                                    <input type="hidden" name="action" value="nds_add_calendar_event">

                                    <div class="space-y-6">
                                        <div>
                                            <label for="event_title" class="block text-sm font-semibold text-gray-900 mb-2">
                                                Event Title <span class="text-red-500">*</span>
                                            </label>
                                            <input type="text" id="event_title" name="event_title" required
                                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                                placeholder="Enter event title">
                                        </div>

                                        <div>
                                            <label for="event_description" class="block text-sm font-semibold text-gray-900 mb-2">
                                                Description
                                            </label>
                                            <textarea id="event_description" name="event_description" rows="3"
                                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                                placeholder="Enter event description"></textarea>
                                        </div>

                                        <div class="grid grid-cols-2 gap-6">
                                            <div>
                                                <label for="event_start_date" class="block text-sm font-semibold text-gray-900 mb-2">
                                                    Start Date & Time <span class="text-red-500">*</span>
                                                </label>
                                                <input type="datetime-local" id="event_start_date" name="event_start_date" required
                                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                            </div>

                                            <div>
                                                <label for="event_end_date" class="block text-sm font-semibold text-gray-900 mb-2">
                                                    End Date & Time
                                                </label>
                                                <input type="datetime-local" id="event_end_date" name="event_end_date"
                                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                            </div>
                                        </div>

                                        <div>
                                            <label class="flex items-center">
                                                <input type="checkbox" id="event_all_day" name="event_all_day" value="1" class="mr-2">
                                                <span class="text-sm font-semibold text-gray-900">All Day Event</span>
                                            </label>
                                        </div>

                                        <div>
                                            <label for="event_location" class="block text-sm font-semibold text-gray-900 mb-2">
                                                Location
                                            </label>
                                            <input type="text" id="event_location" name="event_location"
                                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                                placeholder="e.g., Main Hall, Room 101">
                                        </div>

                                        <div>
                                            <label for="event_color" class="block text-sm font-semibold text-gray-900 mb-2">
                                                Event Color
                                            </label>
                                            <input type="color" id="event_color" name="event_color" value="#3788d8"
                                                class="w-full h-12 border border-gray-300 rounded-lg cursor-pointer">
                                        </div>

                                        <div>
                                            <label class="block text-sm font-semibold text-gray-900 mb-3">
                                                Audience <span class="text-red-500">*</span>
                                            </label>
                                            <div class="space-y-2">
                                                <label class="flex items-center">
                                                    <input type="checkbox" name="event_audience[]" value="all" class="mr-2 audience-checkbox" checked>
                                                    <span class="text-sm text-gray-700">All Users</span>
                                                </label>
                                                <label class="flex items-center">
                                                    <input type="checkbox" name="event_audience[]" value="administrator" class="mr-2 audience-checkbox">
                                                    <span class="text-sm text-gray-700">Administrators</span>
                                                </label>
                                                <label class="flex items-center">
                                                    <input type="checkbox" name="event_audience[]" value="nds_student" class="mr-2 audience-checkbox">
                                                    <span class="text-sm text-gray-700">Students</span>
                                                </label>
                                                <label class="flex items-center">
                                                    <input type="checkbox" name="event_audience[]" value="nds_lecturer" class="mr-2 audience-checkbox">
                                                    <span class="text-sm text-gray-700">Lecturers</span>
                                                </label>
                                            </div>
                                            <p class="text-xs text-gray-500 mt-2">Select who can see this event. If "All Users" is checked, everyone will see it.</p>
                                        </div>
                                    </div>

                                    <div class="flex items-center justify-end space-x-3 mt-8 pt-6 border-t border-gray-200">
                                        <button type="button" onclick="closeEventModal()"
                                            class="px-6 py-3 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                                            Cancel
                                        </button>
                                        <button type="submit"
                                            class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-all duration-200 font-medium shadow-md hover:shadow-lg">
                                            Create Event
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Event Details Modal -->
                <div id="eventDetailsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50" onclick="if(event.target === this) closeEventDetailsModal();">
                        <div class="flex items-center justify-center min-h-screen p-4">
                            <div class="bg-white rounded-xl shadow-xl max-w-lg w-full max-h-screen overflow-y-auto">
                                <div class="p-6">
                                    <div class="flex items-center justify-between mb-6">
                                        <h2 id="event-details-title" class="text-2xl font-bold text-gray-900">Event Details</h2>
                                        <button type="button" onclick="closeEventDetailsModal()" class="text-gray-400 hover:text-gray-600">
                                            <i class="fas fa-times text-xl"></i>
                                        </button>
                                    </div>

                                    <div id="event-details-content">
                                        <!-- Content will be populated by JavaScript -->
                                    </div>

                                    <div class="flex items-center justify-end mt-8 pt-6 border-t border-gray-200">
                                        <button type="button" onclick="closeEventDetailsModal()"
                                            class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-all duration-200 font-medium">
                                            Close
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <script type="text/javascript">
                    // Function to close event details modal
                    function closeEventDetailsModal() {
                        console.log('closeEventDetailsModal called');
                        var modal = document.getElementById('eventDetailsModal');
                        if (modal) {
                            modal.classList.add('hidden'); // Use Tailwind class instead of inline style
                            document.body.style.overflow = ''; // Restore body scrolling
                            console.log('Modal closed');
                        } else {
                            console.log('Modal not found');
                        }
                    }
                    
                    // Handle audience checkbox logic
                    document.addEventListener('DOMContentLoaded', function() {
                        const audienceCheckboxes = document.querySelectorAll('.audience-checkbox');
                        const allCheckbox = document.querySelector('input[name="event_audience[]"][value="all"]');
                        
                        function updateAudienceCheckboxes() {
                            if (allCheckbox.checked) {
                                // If "all" is checked, disable and uncheck others
                                audienceCheckboxes.forEach(cb => {
                                    if (cb !== allCheckbox) {
                                        cb.checked = false;
                                        cb.disabled = true;
                                    }
                                });
                            } else {
                                // If "all" is unchecked, enable others
                                audienceCheckboxes.forEach(cb => {
                                    if (cb !== allCheckbox) {
                                        cb.disabled = false;
                                    }
                                });
                            }
                        }
                        
                        // Add event listeners
                        audienceCheckboxes.forEach(cb => {
                            cb.addEventListener('change', function() {
                                if (this === allCheckbox) {
                                    updateAudienceCheckboxes();
                                } else if (this.checked && allCheckbox.checked) {
                                    // If checking a specific role while "all" is checked, uncheck "all"
                                    allCheckbox.checked = false;
                                    updateAudienceCheckboxes();
                                }
                            });
                        });
                        
                        // Initial state
                        updateAudienceCheckboxes();
                    });
                    
                    function ndsOpenCalendarGuide() {
                        var template = document.getElementById('nds-calendar-guide-template');
                        if (!template) return;
                        var content = template.innerHTML;
                        var printWindow = window.open('', '_blank', 'width=900,height=700');
                        if (!printWindow) return;
                        printWindow.document.open();
                        printWindow.document.write('<html><head><title>Calendar & Timetable – Staff Guide</title>');
                        printWindow.document.write('<style>body{margin:0;padding:0;background:#ffffff;}</style>');
                        printWindow.document.write('</head><body>' + content + '</body></html>');
                        printWindow.document.close();
                        printWindow.focus();
                        // Give the browser a short moment before triggering print
                        setTimeout(function() {
                            printWindow.print();
                        }, 500);
                    }
                    </script>
                </div>
            </div>
        </div>

        <script>
            function closeEventModal() {
                document.getElementById('addEventModal').classList.add('hidden');
                document.body.style.overflow = '';
            }

            function openAddEventModal() {
                console.log('openAddEventModal called');
                document.getElementById('addEventModal').classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            }

            document.addEventListener('DOMContentLoaded', function() {
                const modalLinks = document.querySelectorAll('a[href="#addEventModal"]');
                console.log('Found modal links:', modalLinks.length);
                modalLinks.forEach(link => {
                    link.addEventListener('click', function(e) {
                        console.log('Add Event link clicked');
                        e.preventDefault();
                        console.log('Opening addEventModal');
                        document.getElementById('addEventModal').classList.remove('hidden');
                        document.body.style.overflow = 'hidden';
                    });
                });
            });
        </script>
                    </div>

                    <!-- Drill-down Stat Modal -->
                    <div id="drillDownModal" class="hidden" style="position:fixed; inset:0; z-index:999999; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
                        <div style="position:fixed; inset:0; background:rgba(0,0,0,0.5);" onclick="closeStatModal()"></div>
                        <div style="position:fixed; inset:0; display:flex; align-items:center; justify-content:center; padding:1rem;">
                            <div style="background:#fff; border-radius:1rem; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25); width:100%; max-width:42rem; max-height:80vh; display:flex; flex-direction:column; position:relative;">
                                <!-- Modal Header -->
                                <div style="display:flex; align-items:center; justify-content:space-between; padding:1rem 1.5rem; border-bottom:1px solid #e5e7eb;">
                                    <div style="display:flex; align-items:center; gap:0.75rem;">
                                        <div id="modalIconBg" style="width:2.5rem; height:2.5rem; border-radius:0.5rem; display:flex; align-items:center; justify-content:center;">
                                            <i id="modalIcon" style="font-size:1.25rem;"></i>
                                        </div>
                                        <div>
                                            <h3 id="modalTitle" style="font-size:1.125rem; font-weight:700; color:#111827; margin:0;"></h3>
                                            <p id="modalCount" style="font-size:0.875rem; color:#6b7280; margin:0;"></p>
                                        </div>
                                    </div>
                                    <button onclick="closeStatModal()" style="color:#9ca3af; padding:0.5rem; border-radius:0.5rem; border:none; background:none; cursor:pointer;" onmouseover="this.style.color='#4b5563'; this.style.background='#f3f4f6'" onmouseout="this.style.color='#9ca3af'; this.style.background='none'">
                                        <i class="fas fa-times" style="font-size:1.25rem;"></i>
                                    </button>
                                </div>
                                <!-- Modal Body -->
                                <div style="overflow-y:auto; flex:1; padding:0.5rem;">
                                    <table style="width:100%; border-collapse:collapse;">
                                        <thead style="background:#f9fafb; position:sticky; top:0; z-index:10;">
                                            <tr>
                                                <th id="col1Header" style="padding:0.75rem 1rem; text-align:left; font-size:0.75rem; font-weight:500; color:#6b7280; text-transform:uppercase;">Name</th>
                                                <th id="col2Header" style="padding:0.75rem 1rem; text-align:left; font-size:0.75rem; font-weight:500; color:#6b7280; text-transform:uppercase;">Details</th>
                                            </tr>
                                        </thead>
                                        <tbody id="modalBody"></tbody>
                                    </table>
                                </div>
                                <!-- Modal Footer -->
                                <div style="padding:0.75rem 1.5rem; border-top:1px solid #e5e7eb; background:#f9fafb; border-radius:0 0 1rem 1rem; text-align:right;">
                                    <button onclick="closeStatModal()" style="padding:0.5rem 1rem; font-size:0.875rem; font-weight:500; color:#374151; background:#fff; border:1px solid #d1d5db; border-radius:0.5rem; cursor:pointer;" onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background='#fff'">Close</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        const statsData = {
                            events: <?php echo json_encode($events_list); ?>,
                            programs: <?php echo json_encode($programs_list); ?>,
                            schedules: <?php echo json_encode($schedules_list); ?>
                        };

                        const modalConfig = {
                            events: {
                                title: 'Calendar Events',
                                icon: 'fas fa-calendar-alt',
                                iconColor: '#2563eb',
                                iconBg: '#eff6ff',
                                col1: 'Event Title',
                                col2: 'Start Date'
                            },
                            programs: {
                                title: 'Active Programs',
                                icon: 'fas fa-graduation-cap',
                                iconColor: '#059669',
                                iconBg: '#ecfdf5',
                                col1: 'Program Name',
                                col2: 'Status'
                            },
                            schedules: {
                                title: 'Course Schedules',
                                icon: 'fas fa-clock',
                                iconColor: '#7c3aed',
                                iconBg: '#f5f3ff',
                                col1: 'Course',
                                col2: 'Schedule'
                            }
                        };

                        window.openStatModal = function(type) {
                            const modal = document.getElementById('drillDownModal');
                            const config = modalConfig[type];
                            const data = statsData[type];
                            
                            if (!modal || !config || !data) return;

                            document.getElementById('modalTitle').textContent = config.title;
                            document.getElementById('modalCount').textContent = data.length + ' item' + (data.length !== 1 ? 's' : '');
                            document.getElementById('col1Header').textContent = config.col1;
                            document.getElementById('col2Header').textContent = config.col2;
                            
                            const modalIcon = document.getElementById('modalIcon');
                            const modalIconBg = document.getElementById('modalIconBg');
                            modalIcon.className = config.icon;
                            modalIcon.style.color = config.iconColor;
                            modalIconBg.style.backgroundColor = config.iconBg;

                            const tbody = document.getElementById('modalBody');
                            tbody.innerHTML = '';
                            
                            data.forEach(item => {
                                const row = document.createElement('tr');
                                row.style.cssText = 'border-bottom:1px solid #f3f4f6; transition: background 0.15s;';
                                row.onmouseover = function() { this.style.background = '#f9fafb'; };
                                row.onmouseout = function() { this.style.background = ''; };
                                
                                row.innerHTML = `
                                    <td style="padding:0.75rem 1rem; font-size:0.875rem; font-weight:600; color:#111827;">${item.name}</td>
                                    <td style="padding:0.75rem 1rem; font-size:0.875rem; color:#4b5563;">${item.details || 'N/A'}</td>
                                `;
                                tbody.appendChild(row);
                            });

                            modal.classList.remove('hidden');
                            document.body.style.overflow = 'hidden';
                        };

                        window.closeStatModal = function() {
                            const modal = document.getElementById('drillDownModal');
                            if (modal) {
                                modal.classList.add('hidden');
                                document.body.style.overflow = '';
                            }
                        };
                    });
                    </script>
        <?php
    }
}
