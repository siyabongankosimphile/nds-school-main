<?php
/**
 * Learner Dashboard - Activity Tab
 */
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$learner_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get activity log
$activity_log = [];
$log_table = $wpdb->prefix . 'nds_student_activity_log';
$table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $log_table));

if ($table_exists) {
    $activity_log = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$log_table} 
         WHERE student_id = %d 
         ORDER BY timestamp DESC 
         LIMIT 50",
        $learner_id
    ), ARRAY_A);
}

// Get recent enrollments as activity
$recent_enrollments = $wpdb->get_results($wpdb->prepare(
    "SELECT e.*, c.name as course_name, 
            'enrollment' as activity_type,
            e.created_at as activity_date
     FROM {$wpdb->prefix}nds_student_enrollments e
     LEFT JOIN {$wpdb->prefix}nds_courses c ON e.course_id = c.id
     WHERE e.student_id = %d
     ORDER BY e.created_at DESC
     LIMIT 20",
    $learner_id
), ARRAY_A);
?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="text-xl font-semibold text-gray-900">Activity Timeline</h2>
        <div class="flex items-center space-x-2">
            <button class="inline-flex items-center px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium transition-colors">
                <i class="fas fa-filter mr-2"></i>
                Filter
            </button>
            <button class="inline-flex items-center px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium transition-colors">
                <i class="fas fa-download mr-2"></i>
                Export
            </button>
        </div>
    </div>

    <!-- Activity Feed -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flow-root">
            <ul class="-mb-8">
                <?php 
                $all_activities = [];
                
                // Add activity log entries
                foreach ($activity_log as $log) {
                    $all_activities[] = [
                        'type' => 'log',
                        'action' => $log['action'] ?? '',
                        'date' => $log['timestamp'] ?? '',
                        'actor' => $log['actor_id'] ?? 0,
                        'data' => $log
                    ];
                }
                
                // Add enrollment activities
                foreach ($recent_enrollments as $enrollment) {
                    $all_activities[] = [
                        'type' => 'enrollment',
                        'action' => 'Enrolled in course',
                        'date' => $enrollment['activity_date'] ?? '',
                        'course' => $enrollment['course_name'] ?? '',
                        'status' => $enrollment['status'] ?? '',
                        'data' => $enrollment
                    ];
                }
                
                // Sort by date (newest first)
                usort($all_activities, function($a, $b) {
                    return strtotime($b['date']) - strtotime($a['date']);
                });
                
                $activity_count = 0;
                foreach (array_slice($all_activities, 0, 30) as $activity):
                    $activity_count++;
                    $is_last = $activity_count === min(30, count($all_activities));
                    $date = !empty($activity['date']) ? date('M j, Y g:i A', strtotime($activity['date'])) : 'Unknown date';
                    
                    $icon = 'fa-circle';
                    $icon_color = 'text-blue-500';
                    $bg_color = 'bg-blue-50';
                    
                    if ($activity['type'] === 'enrollment') {
                        $icon = 'fa-book';
                        $icon_color = 'text-green-500';
                        $bg_color = 'bg-green-50';
                    } elseif (strpos(strtolower($activity['action']), 'created') !== false) {
                        $icon = 'fa-plus-circle';
                        $icon_color = 'text-green-500';
                        $bg_color = 'bg-green-50';
                    } elseif (strpos(strtolower($activity['action']), 'updated') !== false) {
                        $icon = 'fa-edit';
                        $icon_color = 'text-blue-500';
                        $bg_color = 'bg-blue-50';
                    } elseif (strpos(strtolower($activity['action']), 'deleted') !== false) {
                        $icon = 'fa-trash';
                        $icon_color = 'text-red-500';
                        $bg_color = 'bg-red-50';
                    }
                ?>
                    <li>
                        <div class="relative pb-8">
                            <?php if (!$is_last): ?>
                                <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200" aria-hidden="true"></span>
                            <?php endif; ?>
                            <div class="relative flex space-x-3">
                                <div>
                                    <span class="h-8 w-8 rounded-full <?php echo $bg_color; ?> flex items-center justify-center ring-8 ring-white">
                                        <i class="fas <?php echo $icon; ?> <?php echo $icon_color; ?> text-xs"></i>
                                    </span>
                                </div>
                                <div class="min-w-0 flex-1 pt-1.5 flex justify-between space-x-4">
                                    <div>
                                        <p class="text-sm text-gray-500">
                                            <?php if ($activity['type'] === 'enrollment'): ?>
                                                <span class="font-medium text-gray-900">Enrolled in</span> 
                                                <span class="font-semibold"><?php echo esc_html($activity['course']); ?></span>
                                                <span class="text-gray-400">•</span>
                                                <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                    <?php echo esc_html(ucfirst($activity['status'])); ?>
                                                </span>
                                            <?php else: ?>
                                                <?php echo esc_html($activity['action']); ?>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    <div class="text-right text-sm whitespace-nowrap text-gray-500">
                                        <?php echo esc_html($date); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
                
                <?php if (empty($all_activities)): ?>
                    <li>
                        <div class="text-center py-12">
                            <i class="fas fa-history text-6xl text-gray-300 mb-4"></i>
                            <h3 class="text-xl font-medium text-gray-900 mb-2">No Activity Recorded</h3>
                            <p class="text-gray-600">Activity will appear here as actions are taken on this learner's account.</p>
                        </div>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</div>
