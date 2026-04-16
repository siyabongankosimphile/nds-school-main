<?php
/**
 * Schedule Fields Component
 * Renders form fields for course schedules - supports multiple schedules per course
 */
if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('nds_render_schedule_fields')) {
    function nds_render_schedule_fields($args = []) {
        global $wpdb;
        
        $lecturers = $args['lecturers'] ?? [];
        $prefix = $args['prefix'] ?? 'schedule';
        $existing_schedules = $args['existing_schedules'] ?? [];
        $course_id = $args['course_id'] ?? 0;
        $default_lecturer_id = $args['default_lecturer_id'] ?? null;
        
        // Fetch rooms from database
        $rooms_table = $wpdb->prefix . 'nds_rooms';
        $rooms = [];
        $rooms_exist = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $rooms_table));
        if ($rooms_exist) {
            $rooms = $wpdb->get_results(
                "SELECT id, code, name, type FROM {$rooms_table} WHERE is_active = 1 ORDER BY name, code",
                ARRAY_A
            );
        }
        
        // Get course lecturers if editing a course
        $course_lecturers = [];
        if ($course_id > 0) {
            $course_lecturers_table = $wpdb->prefix . 'nds_course_lecturers';
            $course_lecturers = $wpdb->get_results($wpdb->prepare(
                "SELECT s.* FROM {$wpdb->prefix}nds_staff s
                 INNER JOIN {$course_lecturers_table} cl ON s.id = cl.lecturer_id
                 WHERE cl.course_id = %d",
                $course_id
            ), ARRAY_A);
        }
        
        // If editing and no existing schedules passed, fetch them
        if ($course_id > 0 && empty($existing_schedules)) {
            $existing_schedules = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}nds_course_schedules WHERE course_id = %d ORDER BY days, start_time",
                $course_id
            ), ARRAY_A);
        }
        
        // If no existing schedules, create one empty schedule
        if (empty($existing_schedules)) {
            $existing_schedules = [null];
        }
        
        $days_of_week = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $session_types = ['Lecture', 'Practical', 'Lab', 'Tutorial', 'Workshop', 'Demo', 'Assessment'];
        ?>
        <div class="schedule-fields-container">
            <div class="flex items-center justify-between mb-4">
                <label class="block text-sm font-semibold text-gray-900">
                    Course Schedule <span class="text-red-500">*</span>
                </label>
                <button type="button" onclick="ndsAddScheduleRow()" 
                        class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                    <i class="fas fa-plus mr-2"></i>
                    Add Schedule
                </button>
            </div>
            <p class="text-xs text-gray-500 mb-4">
                Courses can have multiple schedules per day (e.g., morning lecture and afternoon practical).
                <span class="text-red-600 font-semibold">Note: Schedules cannot overlap with other courses in the same program.</span>
            </p>
            
            <div id="schedule-rows" class="space-y-4">
                <?php foreach ($existing_schedules as $index => $schedule): ?>
                    <?php 
                    $schedule_index = $index;
                    $schedule_days = $schedule ? explode(',', $schedule['days']) : [];
                    $schedule_days = array_map('trim', $schedule_days);
                    ?>
                    <div class="schedule-row border border-gray-200 rounded-lg p-4 bg-gray-50" data-schedule-index="<?php echo $schedule_index; ?>">
                        <div class="flex items-center justify-between mb-3">
                            <h4 class="text-sm font-medium text-gray-700">Schedule <?php echo $schedule_index + 1; ?></h4>
                            <button type="button" onclick="ndsRemoveScheduleRow(this)" 
                                    class="text-red-600 hover:text-red-800 text-sm">
                                <i class="fas fa-trash"></i> Remove
                            </button>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <!-- Days -->
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-2">
                                    Days <span class="text-red-500">*</span>
                                </label>
                                <div class="flex flex-wrap gap-2">
                                    <?php foreach ($days_of_week as $day): ?>
                                        <label class="inline-flex items-center">
                                            <input type="checkbox" 
                                                   name="<?php echo esc_attr($prefix); ?>[<?php echo $schedule_index; ?>][days][]" 
                                                   value="<?php echo esc_attr($day); ?>"
                                                   class="schedule-day-checkbox rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                                   <?php echo in_array($day, $schedule_days) ? 'checked' : ''; ?>>
                                            <span class="ml-1 text-xs text-gray-700"><?php echo esc_html(substr($day, 0, 3)); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <input type="hidden" 
                                       name="<?php echo esc_attr($prefix); ?>[<?php echo $schedule_index; ?>][days]" 
                                       class="schedule-days-hidden"
                                       value="<?php echo esc_attr($schedule ? $schedule['days'] : ''); ?>">
                            </div>
                            
                            <!-- Start Time -->
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-2">
                                    Start Time <span class="text-red-500">*</span>
                                </label>
                                <input type="time" 
                                       name="<?php echo esc_attr($prefix); ?>[<?php echo $schedule_index; ?>][start_time]" 
                                       value="<?php echo esc_attr($schedule ? $schedule['start_time'] : '08:00'); ?>"
                                       class="schedule-start-time w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                       required>
                            </div>
                            
                            <!-- End Time -->
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-2">
                                    End Time
                                </label>
                                <input type="time" 
                                       name="<?php echo esc_attr($prefix); ?>[<?php echo $schedule_index; ?>][end_time]" 
                                       value="<?php echo esc_attr($schedule ? $schedule['end_time'] : '09:00'); ?>"
                                       class="schedule-end-time w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            </div>
                            
                            <!-- Location/Room -->
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-2">
                                    Location/Room
                                </label>
                                <?php if (!empty($rooms)): ?>
                                    <select name="<?php echo esc_attr($prefix); ?>[<?php echo $schedule_index; ?>][location]" 
                                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                        <option value="">Select Room</option>
                                        <?php foreach ($rooms as $room): ?>
                                            <?php 
                                            $room_display = trim($room['name'] . ($room['code'] ? ' (' . $room['code'] . ')' : ''));
                                            $room_value = $room_display;
                                            $is_selected = ($schedule && $schedule['location'] === $room_value);
                                            ?>
                                            <option value="<?php echo esc_attr($room_value); ?>" 
                                                    <?php echo $is_selected ? 'selected' : ''; ?>>
                                                <?php echo esc_html($room_display); ?>
                                                <?php if (!empty($room['type'])): ?>
                                                    - <?php echo esc_html(ucfirst($room['type'])); ?>
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php else: ?>
                                    <input type="text" 
                                           name="<?php echo esc_attr($prefix); ?>[<?php echo $schedule_index; ?>][location]" 
                                           value="<?php echo esc_attr($schedule ? $schedule['location'] : ''); ?>"
                                           placeholder="e.g., Kitchen Lab 1"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <p class="text-xs text-gray-500 mt-1">No rooms found in database. Using text input.</p>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Session Type -->
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-2">
                                    Session Type
                                </label>
                                <select name="<?php echo esc_attr($prefix); ?>[<?php echo $schedule_index; ?>][session_type]" 
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="">Select Type</option>
                                    <?php foreach ($session_types as $type): ?>
                                        <option value="<?php echo esc_attr($type); ?>" 
                                                <?php echo ($schedule && $schedule['session_type'] === $type) ? 'selected' : ''; ?>>
                                            <?php echo esc_html($type); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Lecturer -->
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-2">
                                    Lecturer
                                </label>
                                <?php 
                                // Determine selected lecturer: existing schedule lecturer, or default lecturer for new schedules
                                $selected_lecturer_id = null;
                                $selected_lecturer = null;
                                
                                if ($schedule && isset($schedule['lecturer_id']) && $schedule['lecturer_id']) {
                                    $selected_lecturer_id = intval($schedule['lecturer_id']);
                                    // Find lecturer in the lecturers array
                                    foreach ($lecturers as $lect) {
                                        if (isset($lect['id']) && intval($lect['id']) === $selected_lecturer_id) {
                                            $selected_lecturer = $lect;
                                            break;
                                        }
                                    }
                                } elseif (!$schedule && $default_lecturer_id) {
                                    $selected_lecturer_id = $default_lecturer_id;
                                    foreach ($lecturers as $lect) {
                                        if (isset($lect['id']) && intval($lect['id']) === $selected_lecturer_id) {
                                            $selected_lecturer = $lect;
                                            break;
                                        }
                                    }
                                }
                                
                                // If lecturer is already linked, show badge
                                if ($selected_lecturer): 
                                    $lecturer_name = trim(($selected_lecturer['first_name'] ?? '') . ' ' . ($selected_lecturer['last_name'] ?? ''));
                                    $lecturer_role = isset($selected_lecturer['role']) ? $selected_lecturer['role'] : '';
                                ?>
                                    <div class="flex items-center space-x-2 p-2 bg-purple-50 border border-purple-200 rounded-lg">
                                        <div class="w-8 h-8 bg-purple-500 rounded-full flex items-center justify-center flex-shrink-0">
                                            <i class="fas fa-user text-white text-xs"></i>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium text-gray-900 truncate"><?php echo esc_html($lecturer_name); ?></p>
                                            <?php if ($lecturer_role): ?>
                                                <p class="text-xs text-gray-600 truncate"><?php echo esc_html($lecturer_role); ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <button type="button" 
                                                onclick="ndsRemoveLecturerFromSchedule(this)"
                                                class="text-red-600 hover:text-red-800 text-xs flex-shrink-0"
                                                title="Remove lecturer">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                    <input type="hidden" 
                                           name="<?php echo esc_attr($prefix); ?>[<?php echo $schedule_index; ?>][lecturer_id]" 
                                           value="<?php echo esc_attr($selected_lecturer_id); ?>"
                                           class="schedule-lecturer-id">
                                <?php else: ?>
                                    <select name="<?php echo esc_attr($prefix); ?>[<?php echo $schedule_index; ?>][lecturer_id]" 
                                            class="schedule-lecturer-select w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                        <option value="">Select Lecturer</option>
                                        <?php if (!empty($lecturers)): ?>
                                            <?php foreach ($lecturers as $lecturer): ?>
                                                <?php 
                                                $lecturer_id = isset($lecturer['id']) ? intval($lecturer['id']) : 0;
                                                $lecturer_name = trim(($lecturer['first_name'] ?? '') . ' ' . ($lecturer['last_name'] ?? ''));
                                                $lecturer_role = isset($lecturer['role']) ? ' (' . esc_html($lecturer['role']) . ')' : '';
                                                if (empty($lecturer_name)) continue; // Skip if no name
                                                ?>
                                                <option value="<?php echo $lecturer_id; ?>">
                                                    <?php echo esc_html($lecturer_name . $lecturer_role); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <option value="" disabled>No lecturers available. Please add staff members first.</option>
                                        <?php endif; ?>
                                    </select>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <script>
        let scheduleRowIndex = <?php echo count($existing_schedules); ?>;
        const schedulePrefix = '<?php echo esc_js($prefix); ?>';
        const availableLecturers = <?php 
            $lecturer_options = [];
            foreach ($lecturers as $lecturer) {
                $lecturer_id = isset($lecturer['id']) ? intval($lecturer['id']) : 0;
                $lecturer_name = trim(($lecturer['first_name'] ?? '') . ' ' . ($lecturer['last_name'] ?? ''));
                if (empty($lecturer_name)) continue;
                $lecturer_role = isset($lecturer['role']) ? $lecturer['role'] : '';
                $lecturer_options[] = [
                    'id' => $lecturer_id,
                    'name' => $lecturer_name,
                    'role' => $lecturer_role
                ];
            }
            echo json_encode($lecturer_options);
        ?>;
        
        function ndsGetLecturerSelectHtml(scheduleIndex) {
            let optionsHtml = '<option value="">Select Lecturer</option>';
            if (availableLecturers.length > 0) {
                availableLecturers.forEach(function(lecturer) {
                    const displayName = lecturer.name + (lecturer.role ? ' (' + lecturer.role + ')' : '');
                    optionsHtml += '<option value="' + lecturer.id + '">' + displayName + '</option>';
                });
            } else {
                optionsHtml += '<option value="" disabled>No lecturers available. Please add staff members first.</option>';
            }
            
            return '<select name="' + schedulePrefix + '[' + scheduleIndex + '][lecturer_id]" ' +
                   'class="schedule-lecturer-select w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">' +
                   optionsHtml + '</select>';
        }
        
        function ndsRemoveLecturerFromSchedule(button) {
            const row = button.closest('.schedule-row');
            const lecturerContainer = button.closest('div').parentElement;
            const scheduleIndex = row.dataset.scheduleIndex;
            const label = lecturerContainer.querySelector('label');
            
            // Replace the badge with select
            lecturerContainer.innerHTML = label.outerHTML + ndsGetLecturerSelectHtml(scheduleIndex);
        }
        
        function ndsAddScheduleRow() {
            const container = document.getElementById('schedule-rows');
            const template = container.querySelector('.schedule-row').cloneNode(true);
            
            // Update index
            template.setAttribute('data-schedule-index', scheduleRowIndex);
            template.querySelector('h4').textContent = 'Schedule ' + (scheduleRowIndex + 1);
            
            // Update all input names
            template.querySelectorAll('input, select').forEach(input => {
                if (input.name) {
                    input.name = input.name.replace(/\[(\d+)\]/, '[' + scheduleRowIndex + ']');
                }
            });
            
            // Clear values
            template.querySelectorAll('input[type="time"], input[type="text"], select').forEach(input => {
                if (input.type === 'time') {
                    input.value = '08:00';
                } else if (input.type === 'text') {
                    input.value = '';
                } else if (input.tagName === 'SELECT') {
                    input.selectedIndex = 0;
                }
            });
            
            // Clear checkboxes
            template.querySelectorAll('input[type="checkbox"]').forEach(cb => {
                cb.checked = false;
            });
            
            // Update hidden days field
            template.querySelector('.schedule-days-hidden').value = '';
            
            // Reset lecturer to select if it was a badge
            const lecturerBadge = template.querySelector('.schedule-lecturer-id');
            if (lecturerBadge) {
                const lecturerContainer = lecturerBadge.closest('div').parentElement;
                const label = lecturerContainer.querySelector('label');
                lecturerContainer.innerHTML = label.outerHTML + ndsGetLecturerSelectHtml(scheduleRowIndex);
            }
            
            container.appendChild(template);
            scheduleRowIndex++;
            
            // Re-initialize day checkbox handlers and start time handlers
            ndsInitScheduleDayHandlers();
            ndsInitStartTimeHandlers();
        }
        
        function ndsRemoveScheduleRow(button) {
            const container = document.getElementById('schedule-rows');
            if (container.children.length <= 1) {
                alert('At least one schedule is required.');
                return;
            }
            
            if (confirm('Are you sure you want to remove this schedule?')) {
                button.closest('.schedule-row').remove();
                
                // Renumber schedules
                Array.from(container.children).forEach((row, index) => {
                    row.setAttribute('data-schedule-index', index);
                    row.querySelector('h4').textContent = 'Schedule ' + (index + 1);
                    row.querySelectorAll('input, select').forEach(input => {
                        if (input.name) {
                            input.name = input.name.replace(/\[(\d+)\]/, '[' + index + ']');
                        }
                    });
                });
            }
        }
        
        function ndsInitScheduleDayHandlers() {
            document.querySelectorAll('.schedule-day-checkbox').forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const row = this.closest('.schedule-row');
                    const hiddenInput = row.querySelector('.schedule-days-hidden');
                    const checkedDays = Array.from(row.querySelectorAll('.schedule-day-checkbox:checked'))
                        .map(cb => cb.value);
                    hiddenInput.value = checkedDays.join(', ');
                });
            });
        }
        
        // Validate schedule overlaps before form submission
        function ndsValidateScheduleOverlaps() {
            const scheduleRows = document.querySelectorAll('.schedule-row');
            const errors = [];
            
            scheduleRows.forEach((row, index) => {
                const daysInput = row.querySelector('.schedule-days-hidden');
                const startTimeInput = row.querySelector('input[name*="[start_time]"]');
                const endTimeInput = row.querySelector('input[name*="[end_time]"]');
                
                if (!daysInput || !startTimeInput || !daysInput.value || !startTimeInput.value) {
                    return; // Skip incomplete schedules
                }
                
                const days = daysInput.value.split(', ').filter(d => d.trim());
                const startTime = startTimeInput.value;
                const endTime = endTimeInput.value || (() => {
                    // Default to 1 hour after start
                    const start = new Date('2000-01-01T' + startTime);
                    start.setHours(start.getHours() + 1);
                    return start.toTimeString().slice(0, 5);
                })();
                
                // Check against other schedules in the same form
                scheduleRows.forEach((otherRow, otherIndex) => {
                    if (index >= otherIndex) return; // Only check once per pair
                    
                    const otherDaysInput = otherRow.querySelector('.schedule-days-hidden');
                    const otherStartTimeInput = otherRow.querySelector('input[name*="[start_time]"]');
                    const otherEndTimeInput = otherRow.querySelector('input[name*="[end_time]"]');
                    
                    if (!otherDaysInput || !otherStartTimeInput || !otherDaysInput.value || !otherStartTimeInput.value) {
                        return;
                    }
                    
                    const otherDays = otherDaysInput.value.split(', ').filter(d => d.trim());
                    const otherStartTime = otherStartTimeInput.value;
                    const otherEndTime = otherEndTimeInput.value || (() => {
                        const start = new Date('2000-01-01T' + otherStartTime);
                        start.setHours(start.getHours() + 1);
                        return start.toTimeString().slice(0, 5);
                    })();
                    
                    // Check if days overlap
                    const daysOverlap = days.some(d => otherDays.includes(d));
                    
                    if (daysOverlap) {
                        // Check if times overlap
                        const start1 = new Date('2000-01-01T' + startTime + ':00');
                        const end1 = new Date('2000-01-01T' + endTime + ':00');
                        const start2 = new Date('2000-01-01T' + otherStartTime + ':00');
                        const end2 = new Date('2000-01-01T' + otherEndTime + ':00');
                        
                        // Two time slots overlap if: (start1 < end2) AND (start2 < end1)
                        if (start1 < end2 && start2 < end1) {
                            errors.push(`Schedule ${index + 1} overlaps with Schedule ${otherIndex + 1} on ${days.filter(d => otherDays.includes(d)).join(', ')}`);
                        }
                    }
                });
            });
            
            return errors;
        }
        
        // Auto-update End Time to 1 hour after Start Time
        function ndsUpdateEndTimeFromStartTime(startTimeInput) {
            const row = startTimeInput.closest('.schedule-row');
            const endTimeInput = row.querySelector('.schedule-end-time');
            if (!endTimeInput || !startTimeInput.value) return;
            
            // Parse start time
            const [hours, minutes] = startTimeInput.value.split(':').map(Number);
            const startDate = new Date();
            startDate.setHours(hours, minutes, 0, 0);
            
            // Add 1 hour
            startDate.setHours(startDate.getHours() + 1);
            
            // Format as HH:MM
            const endHours = String(startDate.getHours()).padStart(2, '0');
            const endMinutes = String(startDate.getMinutes()).padStart(2, '0');
            endTimeInput.value = `${endHours}:${endMinutes}`;
        }
        
        // Initialize start time change handlers
        function ndsInitStartTimeHandlers() {
            document.querySelectorAll('.schedule-start-time').forEach(input => {
                // Remove existing listeners by cloning
                const newInput = input.cloneNode(true);
                input.parentNode.replaceChild(newInput, input);
                
                // Add new listener
                newInput.addEventListener('change', function() {
                    ndsUpdateEndTimeFromStartTime(this);
                });
                newInput.addEventListener('input', function() {
                    ndsUpdateEndTimeFromStartTime(this);
                });
            });
        }
        
        // Add validation to form submission
        document.addEventListener('DOMContentLoaded', function() {
            ndsInitScheduleDayHandlers();
            ndsInitStartTimeHandlers();
            
            // Find the form and add validation
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const errors = ndsValidateScheduleOverlaps();
                    if (errors.length > 0) {
                        e.preventDefault();
                        alert('Schedule Overlap Detected!\n\n' + errors.join('\n') + '\n\nPlease adjust the schedules to avoid overlaps within the same program.');
                        return false;
                    }
                });
            }
        });
        </script>
        <?php
    }
}
