<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Check permissions
if (!nds_can_manage_timetables()) {
    wp_die('You do not have permission to manage timetables.');
}

$programs = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}nds_programs ORDER BY name");
$current_program_id = isset($_GET['program_id']) ? intval($_GET['program_id']) : (isset($programs[0]) ? $programs[0]->id : 0);

$rooms = nds_get_rooms();
$lecturers = nds_get_lecturers();
$program_schedules = $current_program_id ? nds_get_program_schedules($current_program_id) : [];

// Count statistics
$total_schedules = count($program_schedules);
$active_lecturers = [];
$active_rooms = [];
foreach ($program_schedules as $schedule) {
    if ($schedule->lecturer_id && !in_array($schedule->lecturer_id, $active_lecturers)) {
        $active_lecturers[] = $schedule->lecturer_id;
    }
    if ($schedule->room_id && !in_array($schedule->room_id, $active_rooms)) {
        $active_rooms[] = $schedule->room_id;
    }
}

// Get current program name
$current_program = $wpdb->get_row($wpdb->prepare(
    "SELECT name FROM {$wpdb->prefix}nds_programs WHERE id = %d",
    $current_program_id
));
$program_name = $current_program ? $current_program->name : 'Select a Program';

// Get all courses for selected program
$courses = $current_program_id ? $wpdb->get_results($wpdb->prepare(
    "SELECT id, code, name FROM {$wpdb->prefix}nds_courses WHERE program_id = %d AND status = 'active' ORDER BY name",
    $current_program_id
)) : [];
?>
<div class="wrap">
    <h1 class="wp-heading-inline">
        <i class="fas fa-calendar-check" style="color: #0066cc; margin-right: 10px;"></i>
        Timetable & Venue Management
    </h1>
    
    <?php if (isset($_GET['success'])): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html($_GET['success']); ?></p>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['error'])): ?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo esc_html($_GET['error']); ?></p>
        </div>
    <?php endif; ?>

    <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-6" style="background-color: #f0f7ff; border-left: 4px solid #0066cc; padding: 15px; margin: 20px 0;">
        <p style="margin: 0; color: #0066cc; font-weight: 500;">
            <strong>ℹ️ Quick Guide:</strong> Select a program to view its course schedules. Add new schedules, assign lecturers to rooms, and manage venues. The system prevents double-booking conflicts.
        </p>
    </div>

    <div class="nds-stats-grid" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px;">
        <div style="background: white; border: 1px solid #e0e0e0; border-radius: 8px; padding: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <div style="font-size: 12px; color: #666; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Total Schedules</div>
            <div style="font-size: 32px; font-weight: bold; color: #0066cc; margin-top: 10px;"><?php echo $total_schedules; ?></div>
        </div>
        <div style="background: white; border: 1px solid #e0e0e0; border-radius: 8px; padding: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <div style="font-size: 12px; color: #666; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Active Lecturers</div>
            <div style="font-size: 32px; font-weight: bold; color: #28a745; margin-top: 10px;"><?php echo count($active_lecturers); ?></div>
        </div>
        <div style="background: white; border: 1px solid #e0e0e0; border-radius: 8px; padding: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <div style="font-size: 12px; color: #666; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Venues in Use</div>
            <div style="font-size: 32px; font-weight: bold; color: #ff9800; margin-top: 10px;"><?php echo count($active_rooms); ?></div>
        </div>
        <div style="background: white; border: 1px solid #e0e0e0; border-radius: 8px; padding: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <div style="font-size: 12px; color: #666; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Total Venues</div>
            <div style="font-size: 32px; font-weight: bold; color: #9c27b0; margin-top: 10px;"><?php echo count($rooms); ?></div>
        </div>
    </div>

    <!-- Program Filter -->
    <div class="postbox" style="border: 1px solid #ccc; border-radius: 4px; background: white; margin-bottom: 20px;">
        <div class="postbox-header" style="padding: 12px 16px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 16px; font-weight: 600;">Select Program</h3>
        </div>
        <div class="inside" style="padding: 16px;">
            <label for="program_filter" style="display: block; margin-bottom: 8px; font-weight: 600;">Program:</label>
            <select id="program_filter" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px;">
                <option value="">-- Select a Program --</option>
                <?php foreach ($programs as $program): ?>
                    <option value="<?php echo $program->id; ?>" <?php echo $program->id == $current_program_id ? 'selected' : ''; ?>>
                        <?php echo esc_html($program->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="nds-main-grid" style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
        <!-- Left Column: Schedule Management -->
        <div>
            <!-- Add Schedule Form -->
            <div class="postbox" style="border: 1px solid #ccc; border-radius: 4px; background: white; margin-bottom: 20px;">
                <div class="postbox-header" style="padding: 12px 16px; border-bottom: 1px solid #eee;">
                    <h3 style="margin: 0; font-size: 16px; font-weight: 600;">
                        <i class="fas fa-plus" style="color: #0066cc; margin-right: 8px;"></i> Add New Schedule
                    </h3>
                </div>
                <div class="inside" style="padding: 16px;">
                    <form id="add_schedule_form" method="POST" action="">
                        <div class="nds-form-grid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                            <div>
                                <label style="display: block; margin-bottom: 4px; font-weight: 600; font-size: 13px;">Course/Qualification:</label>
                                <select name="course_id" id="course_id" required style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; font-size: 13px;">
                                    <option value="">-- Select Course --</option>
                                    <?php foreach ($courses as $course): ?>
                                        <option value="<?php echo $course->id; ?>">
                                            <?php echo esc_html($course->code . ' - ' . $course->name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label style="display: block; margin-bottom: 4px; font-weight: 600; font-size: 13px;">Lecturer:</label>
                                <select name="lecturer_id" id="lecturer_id" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; font-size: 13px;">
                                    <option value="">-- No Lecturer Assigned --</option>
                                    <?php foreach ($lecturers as $lecturer): ?>
                                        <option value="<?php echo $lecturer->id; ?>">
                                            <?php echo esc_html($lecturer->first_name . ' ' . $lecturer->last_name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="nds-form-grid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                            <div>
                                <label style="display: block; margin-bottom: 4px; font-weight: 600; font-size: 13px;">Days:</label>
                                <div class="nds-days-grid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px;">
                                    <?php 
                                    $days = ['mon' => 'Mon', 'tue' => 'Tue', 'wed' => 'Wed', 'thu' => 'Thu', 'fri' => 'Fri', 'sat' => 'Sat', 'sun' => 'Sun'];
                                    foreach ($days as $key => $label):
                                    ?>
                                        <label style="display: flex; align-items: center; font-weight: normal; font-size: 13px;">
                                            <input type="checkbox" name="days[]" value="<?php echo $key; ?>" style="margin-right: 4px;">
                                            <?php echo $label; ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div>
                                <label style="display: block; margin-bottom: 4px; font-weight: 600; font-size: 13px;">Session Type:</label>
                                <select name="session_type" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; font-size: 13px;">
                                    <option value="lecture">Lecture</option>
                                    <option value="practical">Practical</option>
                                    <option value="workshop">Workshop</option>
                                    <option value="seminar">Seminar</option>
                                    <option value="trade_test">Trade Test</option>
                                    <option value="exam">Exam</option>
                                </select>
                            </div>
                        </div>

                        <div class="nds-form-grid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                            <div>
                                <label style="display: block; margin-bottom: 4px; font-weight: 600; font-size: 13px;">Start Date:</label>
                                <input type="date" name="valid_from" id="valid_from" required value="<?php echo esc_attr(date('Y-m-d')); ?>" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; font-size: 13px;">
                            </div>
                            <div>
                                <label style="display: block; margin-bottom: 4px; font-weight: 600; font-size: 13px;">End Date:</label>
                                <input type="date" name="valid_to" id="valid_to" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; font-size: 13px;">
                            </div>
                        </div>

                        <div class="nds-form-grid-3" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                            <div>
                                <label style="display: block; margin-bottom: 4px; font-weight: 600; font-size: 13px;">Start Time:</label>
                                <input type="time" name="start_time" id="start_time" required style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; font-size: 13px;">
                            </div>
                            <div>
                                <label style="display: block; margin-bottom: 4px; font-weight: 600; font-size: 13px;">End Time:</label>
                                <input type="time" name="end_time" id="end_time" required style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; font-size: 13px;">
                            </div>
                            <div>
                                <label style="display: block; margin-bottom: 4px; font-weight: 600; font-size: 13px;">Venue/Room:</label>
                                <select name="room_id" id="room_id" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; font-size: 13px;">
                                    <option value="">-- No Room --</option>
                                    <?php foreach ($rooms as $room): ?>
                                        <option value="<?php echo $room->id; ?>">
                                            <?php echo esc_html($room->code . ' - ' . $room->name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div style="margin-bottom: 12px;">
                            <label style="display: block; margin-bottom: 4px; font-weight: 600; font-size: 13px;">Location/Room Reference:</label>
                            <input type="text" name="location" placeholder="e.g., Building A, Room 201" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; font-size: 13px;">
                        </div>

                        <div id="scheduleLiveStatus" style="margin-bottom: 12px; padding: 10px 12px; border-radius: 4px; background: #eff6ff; color: #1d4ed8; font-size: 13px;">
                            Select date and time to see the live schedule summary.
                        </div>

                        <button type="button" onclick="addSchedule()" style="background-color: #0066cc; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 14px;">
                            <i class="fas fa-check" style="margin-right: 6px;"></i> Add Schedule
                        </button>
                    </form>
                </div>
            </div>

            <!-- Schedules Table -->
            <div class="postbox" style="border: 1px solid #ccc; border-radius: 4px; background: white;">
                <div class="postbox-header" style="padding: 12px 16px; border-bottom: 1px solid #eee;">
                    <h3 style="margin: 0; font-size: 16px; font-weight: 600;">
                        <i class="fas fa-list" style="color: #0066cc; margin-right: 8px;"></i> Schedules for <?php echo esc_html($program_name); ?>
                    </h3>
                </div>
                <div class="inside" style="padding: 16px; overflow-x: auto;">
                    <?php if (!empty($program_schedules)): ?>
                        <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                            <thead>
                                <tr style="background-color: #f5f5f5; border-bottom: 2px solid #ddd;">
                                    <th style="padding: 10px; text-align: left; font-weight: 600; color: #333;">Course</th>
                                    <th style="padding: 10px; text-align: left; font-weight: 600; color: #333;">Lecturer</th>
                                    <th style="padding: 10px; text-align: left; font-weight: 600; color: #333;">Days</th>
                                    <th style="padding: 10px; text-align: left; font-weight: 600; color: #333;">Date Range</th>
                                    <th style="padding: 10px; text-align: left; font-weight: 600; color: #333;">Time</th>
                                    <th style="padding: 10px; text-align: left; font-weight: 600; color: #333;">Room</th>
                                    <th style="padding: 10px; text-align: left; font-weight: 600; color: #333;">Type</th>
                                    <th style="padding: 10px; text-align: center; font-weight: 600; color: #333;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($program_schedules as $schedule): ?>
                                    <tr style="border-bottom: 1px solid #eee; background-color: #fafafa;">
                                        <td style="padding: 10px;"><strong><?php echo esc_html($schedule->course_code . ' - ' . $schedule->course_name); ?></strong></td>
                                        <td style="padding: 10px;"><?php echo $schedule->first_name ? esc_html($schedule->first_name . ' ' . $schedule->last_name) : '<em>Not assigned</em>'; ?></td>
                                        <td style="padding: 10px;">
                                            <span style="background-color: #e3f2fd; color: #0066cc; padding: 4px 8px; border-radius: 3px; font-weight: 600;">
                                                <?php echo strtoupper(esc_html($schedule->days)); ?>
                                            </span>
                                        </td>
                                        <td style="padding: 10px;"><?php echo esc_html(($schedule->valid_from ?: 'Open') . ' to ' . ($schedule->valid_to ?: 'Ongoing')); ?></td>
                                        <td style="padding: 10px;"><code><?php echo esc_html($schedule->start_time . ' - ' . $schedule->end_time); ?></code></td>
                                        <td style="padding: 10px;"><?php echo $schedule->room_name ? esc_html($schedule->room_name) : '<em>No room assigned</em>'; ?></td>
                                        <td style="padding: 10px;"><span style="background-color: #f3e5f5; color: #9c27b0; padding: 4px 8px; border-radius: 3px; font-size: 12px;"><?php echo ucfirst(esc_html($schedule->session_type)); ?></span></td>
                                        <td style="padding: 10px; text-align: center;">
                                            <button onclick="editSchedule(<?php echo $schedule->id; ?>)" style="background-color: #ff9800; color: white; padding: 6px 12px; border: none; border-radius: 3px; cursor: pointer; font-size: 12px; margin-right: 4px;">Edit</button>
                                            <button onclick="deleteSchedule(<?php echo $schedule->id; ?>)" style="background-color: #f44336; color: white; padding: 6px 12px; border: none; border-radius: 3px; cursor: pointer; font-size: 12px;">Delete</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p style="text-align: center; color: #999; padding: 20px;">No schedules yet. Select a program and add a schedule.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right Column: Venue Management -->
        <div>
            <!-- Venues List -->
            <div class="postbox" style="border: 1px solid #ccc; border-radius: 4px; background: white;">
                <div class="postbox-header" style="padding: 12px 16px; border-bottom: 1px solid #eee;">
                    <h3 style="margin: 0; font-size: 16px; font-weight: 600;">
                        <i class="fas fa-building" style="color: #28a745; margin-right: 8px;"></i> Venues/Rooms
                    </h3>
                </div>
                <div class="inside" style="padding: 16px;">
                    <?php if (!empty($rooms)): ?>
                        <div style="display: grid; gap: 12px;">
                            <?php foreach ($rooms as $room): ?>
                                <div style="border: 1px solid #ddd; border-radius: 4px; padding: 12px; background-color: #f9f9f9;">
                                    <div style="font-weight: 600; color: #333; margin-bottom: 4px;">
                                        <i class="fas fa-door-open" style="color: #28a745; margin-right: 6px;"></i>
                                        <?php echo esc_html($room->code . ' - ' . $room->name); ?>
                                    </div>
                                    <div style="font-size: 12px; color: #666; margin-bottom: 4px;">
                                        <strong>Type:</strong> <?php echo ucfirst(esc_html($room->type)); ?>
                                    </div>
                                    <div style="font-size: 12px; color: #666; margin-bottom: 4px;">
                                        <strong>Capacity:</strong> <?php echo esc_html($room->capacity); ?> people
                                    </div>
                                    <div style="font-size: 12px; color: #666;">
                                        <strong>Location:</strong> <?php echo esc_html($room->location ?? 'Not specified'); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p style="text-align: center; color: #999; padding: 20px;">No venues/rooms configured yet. Set up venues in Admin Settings.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function addSchedule() {
    const form = document.getElementById('add_schedule_form');
    const formData = new FormData(form);
    
    // Collect checked days
    const days = Array.from(document.querySelectorAll('input[name="days[]"]:checked')).map(el => el.value).join(',');
    
    if (!formData.get('course_id')) {
        alert('Please select a course');
        return;
    }
    
    if (!days) {
        alert('Please select at least one day');
        return;
    }

    const validFrom = formData.get('valid_from');
    const validTo = formData.get('valid_to');
    const startTime = formData.get('start_time');
    const endTime = formData.get('end_time');

    if (!validFrom) {
        alert('Please select a start date');
        return;
    }

    if (validTo && validTo < validFrom) {
        alert('End date cannot be before start date');
        return;
    }

    if (startTime && endTime && endTime <= startTime) {
        alert('End time must be after start time');
        return;
    }
    
    formData.set('days', days);
    formData.set('action', 'nds_add_schedule');
    formData.set('program_id', document.getElementById('program_filter').value);
    
    const url = new URL(window.location);
    
    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.data.message);
            window.location.reload();
        } else {
            if (data.data.conflicts) {
                let conflictMsg = data.data.message + '\n\nConflicts:\n';
                data.data.conflicts.forEach(conflict => {
                    const dateWindow = (conflict.valid_from || conflict.valid_to)
                        ? ` [${conflict.valid_from || 'Open'} to ${conflict.valid_to || 'Ongoing'}]`
                        : '';
                    conflictMsg += `- ${conflict.course_name} on ${conflict.days}${dateWindow} at ${conflict.start_time}-${conflict.end_time}\n`;
                });
                alert(conflictMsg);
            } else {
                alert(data.data.message);
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error adding schedule');
    });
}

function editSchedule(scheduleId) {
    alert('Edit schedule #' + scheduleId + ' - Coming soon');
}

function deleteSchedule(scheduleId) {
    if (!confirm('Are you sure you want to delete this schedule?')) {
        return;
    }
    
    const formData = new FormData();
    formData.set('action', 'nds_delete_schedule');
    formData.set('schedule_id', scheduleId);
    
    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.data.message);
            window.location.reload();
        } else {
            alert(data.data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error deleting schedule');
    });
}

// Program filter
document.getElementById('program_filter').addEventListener('change', function() {
    if (this.value) {
        window.location.href = '<?php echo admin_url('admin.php?page=nds-timetable'); ?>&program_id=' + this.value;
    }
});

function updateScheduleLiveStatus() {
    const status = document.getElementById('scheduleLiveStatus');
    const startDate = document.getElementById('valid_from');
    const endDate = document.getElementById('valid_to');
    const startTime = document.getElementById('start_time');
    const endTime = document.getElementById('end_time');

    if (!status || !startDate || !endDate || !startTime || !endTime) {
        return;
    }

    const fromValue = startDate.value;
    const toValue = endDate.value;
    const startValue = startTime.value;
    const endValue = endTime.value;

    if (!fromValue && !startValue && !endValue) {
        status.textContent = 'Select date and time to see the live schedule summary.';
        status.style.background = '#eff6ff';
        status.style.color = '#1d4ed8';
        return;
    }

    if (toValue && fromValue && toValue < fromValue) {
        status.textContent = 'End date must be on or after the start date.';
        status.style.background = '#fef2f2';
        status.style.color = '#b91c1c';
        return;
    }

    if (startValue && endValue && endValue <= startValue) {
        status.textContent = 'End time must be after the start time.';
        status.style.background = '#fef2f2';
        status.style.color = '#b91c1c';
        return;
    }

    const dateLabel = fromValue
        ? (toValue ? `${fromValue} to ${toValue}` : `${fromValue} onward`)
        : 'Date not selected';
    const timeLabel = startValue && endValue
        ? `${startValue} to ${endValue}`
        : 'Time not fully selected';

    status.textContent = `This schedule will run from ${dateLabel} at ${timeLabel}. Calendar events will appear in this active date range.`;
    status.style.background = '#ecfdf5';
    status.style.color = '#047857';
}

['valid_from', 'valid_to', 'start_time', 'end_time'].forEach(function(fieldId) {
    const field = document.getElementById(fieldId);
    if (field) {
        field.addEventListener('input', updateScheduleLiveStatus);
        field.addEventListener('change', updateScheduleLiveStatus);
    }
});

updateScheduleLiveStatus();
</script>

<style>
.postbox {
    background-color: white !important;
}

.postbox-header {
    background-color: #f1f1f1 !important;
}

/* --- Responsive overrides for timetable page --- */
@media (max-width: 1024px) {
    .nds-stats-grid {
        grid-template-columns: repeat(2, 1fr) !important;
    }
    .nds-main-grid {
        grid-template-columns: 1fr !important;
    }
}

@media (max-width: 640px) {
    .nds-stats-grid {
        grid-template-columns: 1fr 1fr !important;
    }
    .nds-form-grid-2 {
        grid-template-columns: 1fr !important;
    }
    .nds-form-grid-3 {
        grid-template-columns: 1fr 1fr !important;
    }
    .nds-days-grid {
        grid-template-columns: repeat(4, 1fr) !important;
    }
    .wrap h1.wp-heading-inline {
        font-size: 1.3rem !important;
    }
}

@media (max-width: 480px) {
    .nds-stats-grid {
        grid-template-columns: 1fr !important;
    }
    .nds-form-grid-3 {
        grid-template-columns: 1fr !important;
    }
    .nds-days-grid {
        grid-template-columns: repeat(3, 1fr) !important;
    }
}
</style>
