<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Moodle-style Content Management Interface for Lecturers
 * Features: Drag & drop uploads, rich text editor, resource/library picker, activity chooser
 */

global $wpdb;

// -------------------------------------------------------------------
// 1. Security & User Validation
// -------------------------------------------------------------------
$staff_id = isset($staff_id) ? (int) $staff_id : (int) nds_portal_get_current_staff_id();
if ($staff_id <= 0) {
    echo '<div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-lg"><p class="text-red-700">Staff profile not found. Please contact administrator.</p></div>';
    return;
}

$courses_taught_safe = isset($courses_taught) && is_array($courses_taught)
    ? $courses_taught
    : array();

// -------------------------------------------------------------------
// 2. Database Tables & Helpers
// -------------------------------------------------------------------
$content_table = $wpdb->prefix . 'nds_lecturer_content';
$modules_table = $wpdb->prefix . 'nds_modules';
$folders_table = $wpdb->prefix . 'nds_content_folders'; // Optional: for Moodle-like folder structure
$resource_table = $wpdb->prefix . 'nds_learning_resources'; // For uploaded files metadata
$quizzes_table = $wpdb->prefix . 'nds_quizzes';
$questions_table = $wpdb->prefix . 'nds_questions';
$quiz_questions_table = $wpdb->prefix . 'nds_quiz_questions';

$module_columns = $wpdb->get_col("SHOW COLUMNS FROM {$modules_table}", 0);
$module_code_col = in_array('module_code', $module_columns, true) ? 'module_code' : (in_array('code', $module_columns, true) ? 'code' : null);
$module_code_expr = $module_code_col ? "m.{$module_code_col} AS module_code" : "'' AS module_code";

// Get notices/errors

$notice = isset($_GET['content_notice']) ? sanitize_text_field(wp_unslash($_GET['content_notice'])) : '';
$error = isset($_GET['content_error']) ? sanitize_text_field(wp_unslash($_GET['content_error'])) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_content_id'])) {
    if (isset($_POST['nds_delete_nonce']) && wp_verify_nonce($_POST['nds_delete_nonce'], 'nds_delete_content')) {
        $delete_id = (int) $_POST['delete_content_id'];
        $wpdb->delete($content_table, ['id' => $delete_id, 'staff_id' => $staff_id]);
        wp_redirect(add_query_arg('content_notice', 'Content deleted successfully.', remove_query_arg('content_error')));
        exit;
    }
    $error = 'Security verification failed for delete action.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nds_update_content_id'])) {
    if (!isset($_POST['nds_update_nonce']) || !wp_verify_nonce($_POST['nds_update_nonce'], 'nds_update_content')) {
        $error = 'Security verification failed for update action.';
    } else {
        $update_id = (int) $_POST['nds_update_content_id'];
        $existing_item = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$content_table} WHERE id = %d AND staff_id = %d",
            $update_id,
            $staff_id
        ), ARRAY_A);

        if (empty($existing_item)) {
            $error = 'Content item not found or access denied.';
        } else {
            $title = sanitize_text_field($_POST['content_title'] ?? '');
            $description = wp_kses_post($_POST['content_description'] ?? '');
            $content_type = sanitize_text_field($_POST['content_type'] ?? ($existing_item['content_type'] ?? 'resource'));
            $module_id = (int) ($_POST['module_id'] ?? 0);
            $access_start = sanitize_text_field($_POST['access_start'] ?? '');
            $access_end = sanitize_text_field($_POST['access_end'] ?? '');
            $is_visible = isset($_POST['is_visible']) ? 1 : 0;
            $resource_url = sanitize_url($_POST['resource_url'] ?? '');

            $quiz_id = isset($_POST['quiz_id']) ? (int) $_POST['quiz_id'] : 0;
            $quiz_time_limit = isset($_POST['time_limit']) ? max(0, (int) $_POST['time_limit']) : 0;
            $quiz_attempts_allowed = isset($_POST['attempts_allowed']) ? (int) $_POST['attempts_allowed'] : 1;
            $quiz_passing_grade = isset($_POST['passing_grade']) ? (float) $_POST['passing_grade'] : 60.00;
            $quiz_shuffle_questions = !empty($_POST['shuffle_questions']) ? 1 : 0;
            $quiz_shuffle_answers = !empty($_POST['shuffle_answers']) ? 1 : 0;
            $quiz_show_answers_after = !empty($_POST['show_answers_after']) ? 1 : 0;
            $quiz_show_correct_answers = !empty($_POST['show_correct_answers']) ? 1 : 0;
            $quiz_questions_per_page = isset($_POST['questions_per_page']) ? max(0, (int) $_POST['questions_per_page']) : 0;
            $quiz_requires_lockdown = !empty($_POST['requires_lockdown']) ? 1 : 0;
            $quiz_grade_method = isset($_POST['grade_method']) ? sanitize_key($_POST['grade_method']) : 'highest';
            $quiz_review_attempts = !empty($_POST['review_attempts']) ? 1 : 0;
            $quiz_open_date_raw = sanitize_text_field($_POST['open_date'] ?? '');
            $quiz_close_date_raw = sanitize_text_field($_POST['close_date'] ?? '');
            $quiz_open_date = $quiz_open_date_raw ? date('Y-m-d H:i:s', strtotime(str_replace('T', ' ', $quiz_open_date_raw))) : null;
            $quiz_close_date = $quiz_close_date_raw ? date('Y-m-d H:i:s', strtotime(str_replace('T', ' ', $quiz_close_date_raw))) : null;
            $quiz_questions_input = isset($_POST['questions']) && is_array($_POST['questions']) ? array_values(array_filter(array_map('intval', $_POST['questions']))) : [];
            $quiz_marks_input = isset($_POST['marks']) && is_array($_POST['marks']) ? $_POST['marks'] : [];

            $file_url = (string) ($existing_item['attachment_url'] ?? '');
            $file_name = (string) ($existing_item['file_name'] ?? '');
            $file_size = (int) ($existing_item['file_size'] ?? 0);
            $file_type = (string) ($existing_item['file_type'] ?? '');

            if (!empty($_FILES['content_file']['name'])) {
                $upload_dir = wp_upload_dir();
                $target_dir = $upload_dir['basedir'] . '/nds_course_content/';
                if (!file_exists($target_dir)) {
                    wp_mkdir_p($target_dir);
                }

                $new_file_name = sanitize_file_name(basename($_FILES['content_file']['name']));
                $prefixed_file_name = time() . '_' . $new_file_name;
                $target_file = $target_dir . $prefixed_file_name;
                $new_file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
                $new_file_size = (int) $_FILES['content_file']['size'];
                $allowed_types = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif', 'mp4', 'zip', 'txt'];

                if (!in_array($new_file_type, $allowed_types, true)) {
                    $error = 'File type not allowed. Allowed: ' . implode(', ', $allowed_types);
                } elseif (!move_uploaded_file($_FILES['content_file']['tmp_name'], $target_file)) {
                    $error = 'Failed to upload file. Please check directory permissions.';
                } else {
                    $file_url = $upload_dir['baseurl'] . '/nds_course_content/' . $prefixed_file_name;
                    $file_name = $new_file_name;
                    $file_size = $new_file_size;
                    $file_type = $new_file_type;
                }
            } elseif (in_array($content_type, ['url', 'forum'], true) && $resource_url !== '') {
                $file_url = $resource_url;
            }

            if (!$error && $title === '') {
                $error = 'Title is required.';
            }

            if (!$error) {
                $quiz_data = $existing_item['quiz_data'] ?? null;
                if ($content_type === 'quiz') {
                    $quiz_data = wp_json_encode([
                        'quiz_id' => $quiz_id,
                        'grade_method' => $quiz_grade_method,
                        'shuffle_answers' => $quiz_shuffle_answers,
                        'show_answers_after' => $quiz_show_answers_after,
                        'show_correct_answers' => $quiz_show_correct_answers,
                        'questions_per_page' => $quiz_questions_per_page,
                        'requires_lockdown' => $quiz_requires_lockdown,
                        'review_attempts' => $quiz_review_attempts,
                        'open_date' => $quiz_open_date,
                        'close_date' => $quiz_close_date,
                        'question_count' => count($quiz_questions_input),
                    ]);
                }

                $updated = $wpdb->update(
                    $content_table,
                    [
                        'title' => $title,
                        'description' => $description,
                        'content_type' => $content_type,
                        'module_id' => $module_id ?: null,
                        'attachment_url' => $file_url,
                        'file_name' => $file_name,
                        'file_size' => $file_size,
                        'file_type' => $file_type,
                        'quiz_data' => $quiz_data,
                        'time_limit_minutes' => $content_type === 'quiz' ? $quiz_time_limit : null,
                        'attempts_allowed' => $content_type === 'quiz' ? $quiz_attempts_allowed : 1,
                        'shuffle_questions' => $content_type === 'quiz' ? $quiz_shuffle_questions : 0,
                        'pass_percentage' => $content_type === 'quiz' ? $quiz_passing_grade : null,
                        'access_start' => $access_start ?: null,
                        'access_end' => $access_end ?: null,
                        'is_visible' => $is_visible,
                        'updated_at' => current_time('mysql'),
                    ],
                    [
                        'id' => $update_id,
                        'staff_id' => $staff_id,
                    ]
                );

                if ($updated === false) {
                    $error = 'Database error: ' . $wpdb->last_error;
                } else {
                    if ($content_type === 'quiz') {
                        $quiz_payload = [
                            'course_id' => (int) ($existing_item['course_id'] ?? 0),
                            'module_id' => $module_id ?: null,
                            'name' => $title,
                            'description' => $description,
                            'time_limit' => $quiz_time_limit,
                            'attempts_allowed' => $quiz_attempts_allowed,
                            'passing_grade' => $quiz_passing_grade,
                            'shuffle_questions' => $quiz_shuffle_questions,
                            'shuffle_answers' => $quiz_shuffle_answers,
                            'show_answers_after' => $quiz_show_answers_after,
                            'show_correct_answers' => $quiz_show_correct_answers,
                            'questions_per_page' => $quiz_questions_per_page,
                            'requires_lockdown' => $quiz_requires_lockdown,
                            'grade_method' => in_array($quiz_grade_method, ['highest', 'average', 'first', 'last'], true) ? $quiz_grade_method : 'highest',
                            'review_attempts' => $quiz_review_attempts,
                            'open_date' => $quiz_open_date,
                            'close_date' => $quiz_close_date,
                        ];

                        if ($quiz_id > 0) {
                            $wpdb->update($quizzes_table, $quiz_payload, ['id' => $quiz_id]);
                            $saved_quiz_id = $quiz_id;
                        } else {
                            $wpdb->insert($quizzes_table, $quiz_payload);
                            $saved_quiz_id = (int) $wpdb->insert_id;
                        }

                        if ($saved_quiz_id > 0) {
                            $wpdb->delete($quiz_questions_table, ['quiz_id' => $saved_quiz_id]);
                            foreach ($quiz_questions_input as $index => $question_id) {
                                $mark = isset($quiz_marks_input[$question_id]) ? (float) $quiz_marks_input[$question_id] : 1.00;
                                $wpdb->insert($quiz_questions_table, [
                                    'quiz_id' => $saved_quiz_id,
                                    'question_id' => $question_id,
                                    'mark' => $mark > 0 ? $mark : 1.00,
                                    'question_order' => $index + 1,
                                    'page_number' => $quiz_questions_per_page > 0 ? (int) floor($index / $quiz_questions_per_page) + 1 : 1,
                                ]);
                            }

                            $wpdb->update($content_table, [
                                'quiz_data' => wp_json_encode([
                                    'quiz_id' => $saved_quiz_id,
                                    'grade_method' => $quiz_payload['grade_method'],
                                    'shuffle_answers' => $quiz_shuffle_answers,
                                    'show_answers_after' => $quiz_show_answers_after,
                                    'show_correct_answers' => $quiz_show_correct_answers,
                                    'questions_per_page' => $quiz_questions_per_page,
                                    'requires_lockdown' => $quiz_requires_lockdown,
                                    'review_attempts' => $quiz_review_attempts,
                                    'open_date' => $quiz_open_date,
                                    'close_date' => $quiz_close_date,
                                    'question_count' => count($quiz_questions_input),
                                ]),
                            ], ['id' => $update_id]);
                        }
                    }

                    $notice = 'Content updated successfully!';
                }
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nds_grade_response_id'])) {
    if (!isset($_POST['nds_grade_response_nonce']) || !wp_verify_nonce($_POST['nds_grade_response_nonce'], 'nds_grade_response')) {
        $error = 'Security verification failed for grading action.';
    } else {
        $grade_response_id = (int) $_POST['nds_grade_response_id'];
        $marks_earned = isset($_POST['marks_earned']) ? max(0, (float) $_POST['marks_earned']) : 0;
        $grader_feedback = wp_kses_post($_POST['grader_feedback'] ?? '');
        $is_correct = isset($_POST['is_correct']) ? (int) $_POST['is_correct'] : null;

        $graded = $wpdb->update(
            $wpdb->prefix . 'nds_quiz_responses',
            [
                'marks_earned' => $marks_earned,
                'feedback' => $grader_feedback,
                'is_correct' => $is_correct === null ? 0 : ($is_correct ? 1 : 0),
                'graded_by' => $staff_id,
                'graded_at' => current_time('mysql'),
            ],
            ['id' => $grade_response_id],
            ['%f', '%s', '%d', '%d', '%s'],
            ['%d']
        );

        if ($graded === false) {
            $error = 'Could not save manual grade: ' . $wpdb->last_error;
        } else {
            $notice = 'Response graded successfully.';
        }
    }
}

// -------------------------------------------------------------------
// 3. Get Lecturer's Assigned Modules & Courses
// -------------------------------------------------------------------
$assigned_module_ids = isset($assigned_module_ids) && is_array($assigned_module_ids)
    ? array_values(array_map('intval', $assigned_module_ids))
    : array();

$course_ids = isset($course_ids) && is_array($course_ids)
    ? array_values(array_map('intval', $course_ids))
    : (function_exists('nds_staff_get_lecturer_course_ids') ? nds_staff_get_lecturer_course_ids($staff_id) : array());

// Ensure we have a selected course (context)
$selected_course = isset($selected_course) && is_array($selected_course) ? $selected_course : null;
$selected_course_id = isset($selected_course_id) ? (int) $selected_course_id : (isset($_GET['course_id']) ? (int) $_GET['course_id'] : 0);
if (!$selected_course_id && !empty($course_ids)) {
    $selected_course_id = $course_ids[0];
}

// Get selected module (if any)
$selected_module_id = isset($_GET['module_id']) ? (int) $_GET['module_id'] : 0;
$selected_content_item_id = isset($_GET['content_item_id']) ? (int) $_GET['content_item_id'] : 0;

// -------------------------------------------------------------------
// 4. Fetch available modules for the selected course
// -------------------------------------------------------------------
$available_modules = [];
$modules_for_form = array();
if ($selected_course_id > 0) {
    $available_modules = $wpdb->get_results($wpdb->prepare(
        "SELECT m.*, {$module_code_expr}
         FROM {$modules_table} m
         WHERE m.course_id = %d AND m.status = 'active'
         ORDER BY m.order_index ASC, m.name ASC",
        $selected_course_id
    ), ARRAY_A);
}

// -------------------------------------------------------------------
// 5. Handle Content Upload/Submission
// -------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nds_submit_content'])) {
    // Verify nonce
    if (!isset($_POST['nds_content_nonce']) || !wp_verify_nonce($_POST['nds_content_nonce'], 'nds_upload_content')) {
        $error = 'Security verification failed. Please try again.';
    } else {
        $title = sanitize_text_field($_POST['content_title'] ?? '');
        $description = wp_kses_post($_POST['content_description'] ?? '');
        $content_type = sanitize_text_field($_POST['content_type'] ?? 'resource');
        $course_id = (int) ($_POST['course_id'] ?? $selected_course_id);
        $module_id = (int) ($_POST['module_id'] ?? 0);
        $access_start = sanitize_text_field($_POST['access_start'] ?? '');
        $access_end = sanitize_text_field($_POST['access_end'] ?? '');
        $is_visible = isset($_POST['is_visible']) ? 1 : 0;

        $quiz_id = isset($_POST['quiz_id']) ? (int) $_POST['quiz_id'] : 0;
        $quiz_time_limit = isset($_POST['time_limit']) ? max(0, (int) $_POST['time_limit']) : 0;
        $quiz_attempts_allowed = isset($_POST['attempts_allowed']) ? (int) $_POST['attempts_allowed'] : 1;
        $quiz_passing_grade = isset($_POST['passing_grade']) ? (float) $_POST['passing_grade'] : 60.00;
        $quiz_shuffle_questions = !empty($_POST['shuffle_questions']) ? 1 : 0;
        $quiz_shuffle_answers = !empty($_POST['shuffle_answers']) ? 1 : 0;
        $quiz_show_answers_after = !empty($_POST['show_answers_after']) ? 1 : 0;
        $quiz_show_correct_answers = !empty($_POST['show_correct_answers']) ? 1 : 0;
        $quiz_questions_per_page = isset($_POST['questions_per_page']) ? max(0, (int) $_POST['questions_per_page']) : 0;
        $quiz_requires_lockdown = !empty($_POST['requires_lockdown']) ? 1 : 0;
        $quiz_grade_method = isset($_POST['grade_method']) ? sanitize_key($_POST['grade_method']) : 'highest';
        $quiz_review_attempts = !empty($_POST['review_attempts']) ? 1 : 0;
        $quiz_open_date_raw = sanitize_text_field($_POST['open_date'] ?? '');
        $quiz_close_date_raw = sanitize_text_field($_POST['close_date'] ?? '');
        $quiz_open_date = $quiz_open_date_raw ? date('Y-m-d H:i:s', strtotime(str_replace('T', ' ', $quiz_open_date_raw))) : null;
        $quiz_close_date = $quiz_close_date_raw ? date('Y-m-d H:i:s', strtotime(str_replace('T', ' ', $quiz_close_date_raw))) : null;
        $quiz_questions_input = isset($_POST['questions']) && is_array($_POST['questions']) ? array_values(array_filter(array_map('intval', $_POST['questions']))) : [];
        $quiz_marks_input = isset($_POST['marks']) && is_array($_POST['marks']) ? $_POST['marks'] : [];
        
        // Handle file upload
        $file_url = '';
        $file_name = '';
        $file_size = 0;
        $file_type = '';
        
        if (!empty($_FILES['content_file']['name'])) {
            $upload_dir = wp_upload_dir();
            $target_dir = $upload_dir['basedir'] . '/nds_course_content/';
            
            // Create directory if not exists
            if (!file_exists($target_dir)) {
                wp_mkdir_p($target_dir);
            }
            
            $file_name = sanitize_file_name(basename($_FILES['content_file']['name']));
            $target_file = $target_dir . time() . '_' . $file_name;
            $file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
            $file_size = $_FILES['content_file']['size'];
            
            // Allowed file types
            $allowed_types = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif', 'mp4', 'zip', 'txt'];
            
            if (in_array($file_type, $allowed_types)) {
                if (move_uploaded_file($_FILES['content_file']['tmp_name'], $target_file)) {
                    $file_url = $upload_dir['baseurl'] . '/nds_course_content/' . time() . '_' . $file_name;
                } else {
                    $error = 'Failed to upload file. Please check directory permissions.';
                }
            } else {
                $error = 'File type not allowed. Allowed: ' . implode(', ', $allowed_types);
            }
        }
        
        // Also handle URL resource
        $resource_url = sanitize_url($_POST['resource_url'] ?? '');
        
        if (!$error) {
            $quiz_data = null;
            if ($content_type === 'quiz') {
                $quiz_data = wp_json_encode([
                    'quiz_id' => $quiz_id,
                    'grade_method' => $quiz_grade_method,
                    'shuffle_answers' => $quiz_shuffle_answers,
                    'show_answers_after' => $quiz_show_answers_after,
                    'show_correct_answers' => $quiz_show_correct_answers,
                    'questions_per_page' => $quiz_questions_per_page,
                    'requires_lockdown' => $quiz_requires_lockdown,
                    'review_attempts' => $quiz_review_attempts,
                    'open_date' => $quiz_open_date,
                    'close_date' => $quiz_close_date,
                    'question_count' => count($quiz_questions_input),
                ]);
            }

            $insert_data = [
                'title' => $title,
                'description' => $description,
                'content_type' => $content_type,
                'course_id' => $course_id,
                'module_id' => $module_id ?: null,
                'staff_id' => $staff_id,
                'attachment_url' => $file_url ?: $resource_url,
                'file_name' => $file_name,
                'file_size' => $file_size,
                'file_type' => $file_type,
                'quiz_data' => $quiz_data,
                'time_limit_minutes' => $content_type === 'quiz' ? $quiz_time_limit : null,
                'attempts_allowed' => $content_type === 'quiz' ? $quiz_attempts_allowed : 1,
                'shuffle_questions' => $content_type === 'quiz' ? $quiz_shuffle_questions : 0,
                'pass_percentage' => $content_type === 'quiz' ? $quiz_passing_grade : null,
                'access_start' => $access_start ?: null,
                'access_end' => $access_end ?: null,
                'is_visible' => $is_visible,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ];
            
            $inserted = $wpdb->insert($content_table, $insert_data);
            
            if ($inserted) {
                $inserted_content_id = (int) $wpdb->insert_id;
                if ($content_type === 'quiz') {
                    $quiz_payload = [
                        'course_id' => $course_id,
                        'module_id' => $module_id ?: null,
                        'name' => $title,
                        'description' => $description,
                        'time_limit' => $quiz_time_limit,
                        'attempts_allowed' => $quiz_attempts_allowed,
                        'passing_grade' => $quiz_passing_grade,
                        'shuffle_questions' => $quiz_shuffle_questions,
                        'shuffle_answers' => $quiz_shuffle_answers,
                        'show_answers_after' => $quiz_show_answers_after,
                        'show_correct_answers' => $quiz_show_correct_answers,
                        'questions_per_page' => $quiz_questions_per_page,
                        'requires_lockdown' => $quiz_requires_lockdown,
                        'grade_method' => in_array($quiz_grade_method, ['highest', 'average', 'first', 'last'], true) ? $quiz_grade_method : 'highest',
                        'review_attempts' => $quiz_review_attempts,
                        'open_date' => $quiz_open_date,
                        'close_date' => $quiz_close_date,
                    ];

                    if ($quiz_id > 0) {
                        $wpdb->update($quizzes_table, $quiz_payload, ['id' => $quiz_id]);
                        $saved_quiz_id = $quiz_id;
                    } else {
                        $wpdb->insert($quizzes_table, $quiz_payload);
                        $saved_quiz_id = (int) $wpdb->insert_id;
                    }

                    if ($saved_quiz_id > 0) {
                        $wpdb->delete($quiz_questions_table, ['quiz_id' => $saved_quiz_id]);
                        foreach ($quiz_questions_input as $index => $question_id) {
                            $mark = isset($quiz_marks_input[$question_id]) ? (float) $quiz_marks_input[$question_id] : 1.00;
                            $wpdb->insert($quiz_questions_table, [
                                'quiz_id' => $saved_quiz_id,
                                'question_id' => $question_id,
                                'mark' => $mark > 0 ? $mark : 1.00,
                                'question_order' => $index + 1,
                                'page_number' => $quiz_questions_per_page > 0 ? (int) floor($index / $quiz_questions_per_page) + 1 : 1,
                            ]);
                        }

                        $wpdb->update($content_table, [
                            'quiz_data' => wp_json_encode([
                                'quiz_id' => $saved_quiz_id,
                                'grade_method' => $quiz_payload['grade_method'],
                                'shuffle_answers' => $quiz_shuffle_answers,
                                'show_answers_after' => $quiz_show_answers_after,
                                'show_correct_answers' => $quiz_show_correct_answers,
                                'questions_per_page' => $quiz_questions_per_page,
                                'requires_lockdown' => $quiz_requires_lockdown,
                                'review_attempts' => $quiz_review_attempts,
                                'open_date' => $quiz_open_date,
                                'close_date' => $quiz_close_date,
                                'question_count' => count($quiz_questions_input),
                            ]),
                        ], ['id' => $inserted_content_id]);
                    }
                }

                $notice = 'Content uploaded successfully!';
                // Clear form after success
                $_POST = [];
            } else {
                $error = 'Database error: ' . $wpdb->last_error;
            }
        }
    }
}

// -------------------------------------------------------------------
// 6. Fetch existing content for display
// -------------------------------------------------------------------
$existing_content = [];
$selected_content_access_grouping = 'all';
$default_export_course_id = 0;
if ($selected_course_id > 0) {
    $module_condition = $selected_module_id > 0 ? "AND module_id = {$selected_module_id}" : "";
    $existing_content = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$content_table}
         WHERE course_id = %d {$module_condition}
         ORDER BY created_at DESC
         LIMIT 50",
        $selected_course_id
    ), ARRAY_A);
}

// -------------------------------------------------------------------
// 7. Content Types Configuration (Moodle-style)
// -------------------------------------------------------------------
$content_types = [
    'resource' => ['label' => 'File/Resource', 'icon' => 'fa-file-alt', 'color' => 'blue'],
    'url' => ['label' => 'URL/Link', 'icon' => 'fa-link', 'color' => 'green'],
    'page' => ['label' => 'Text Page', 'icon' => 'fa-file-text', 'color' => 'purple'],
    'folder' => ['label' => 'Folder', 'icon' => 'fa-folder', 'color' => 'yellow'],
    'label' => ['label' => 'Label/Divider', 'icon' => 'fa-tag', 'color' => 'gray'],
    'quiz' => ['label' => 'Quiz', 'icon' => 'fa-question-circle', 'color' => 'orange'],
    'assignment' => ['label' => 'Assignment', 'icon' => 'fa-tasks', 'color' => 'red'],
    'forum' => ['label' => 'Forum', 'icon' => 'fa-comments', 'color' => 'indigo'],
];

$requested_content_type = isset($_GET['content_view']) ? sanitize_key(wp_unslash($_GET['content_view'])) : '';
$is_type_page = ($requested_content_type !== '' && isset($content_types[$requested_content_type]));
$selected_content_type = $is_type_page ? $requested_content_type : 'resource';
$selected_content_item = null;
$is_item_page = false;

$type_page_base_url = add_query_arg(
    [
        'tab' => 'content',
        'course_id' => $selected_course_id,
        'module_id' => $selected_module_id > 0 ? $selected_module_id : null,
    ],
    home_url('/staff-portal/')
);

if ($selected_content_item_id > 0) {
    $selected_content_item = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$content_table} WHERE id = %d AND staff_id = %d AND course_id = %d",
        $selected_content_item_id,
        $staff_id,
        $selected_course_id
    ), ARRAY_A);

    if (!empty($selected_content_item)) {
        $is_item_page = true;
        $item_type = sanitize_key((string) ($selected_content_item['content_type'] ?? 'resource'));
        if (isset($content_types[$item_type])) {
            $selected_content_type = $item_type;
        }
    }
}

$to_datetime_local = static function ($value) {
    if (empty($value)) {
        return '';
    }
    $timestamp = strtotime((string) $value);
    if ($timestamp === false) {
        return '';
    }
    return date('Y-m-d\\TH:i', $timestamp);
};

$form_title = isset($_POST['content_title']) ? sanitize_text_field(wp_unslash($_POST['content_title'])) : (string) ($selected_content_item['title'] ?? '');
$form_description = isset($_POST['content_description']) ? wp_kses_post(wp_unslash($_POST['content_description'])) : (string) ($selected_content_item['description'] ?? '');
$form_module_id = isset($_POST['module_id']) ? (int) $_POST['module_id'] : (int) ($selected_content_item['module_id'] ?? 0);
$form_resource_url = isset($_POST['resource_url']) ? sanitize_url(wp_unslash($_POST['resource_url'])) : (string) ($selected_content_item['attachment_url'] ?? '');
$form_access_start = isset($_POST['access_start']) ? sanitize_text_field(wp_unslash($_POST['access_start'])) : $to_datetime_local($selected_content_item['access_start'] ?? '');
$form_access_end = isset($_POST['access_end']) ? sanitize_text_field(wp_unslash($_POST['access_end'])) : $to_datetime_local($selected_content_item['access_end'] ?? '');
$form_visible = isset($_POST['is_visible']) ? 1 : (int) ($selected_content_item['is_visible'] ?? 1);

$quiz_data_arr = [];
if (!empty($selected_content_item['quiz_data'])) {
    $decoded = json_decode((string) $selected_content_item['quiz_data'], true);
    if (is_array($decoded)) {
        $quiz_data_arr = $decoded;
    }
}

$form_quiz_id = isset($_POST['quiz_id']) ? (int) $_POST['quiz_id'] : (int) ($quiz_data_arr['quiz_id'] ?? 0);
$quiz_record = null;
if ($form_quiz_id > 0) {
    $quiz_record = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$quizzes_table} WHERE id = %d",
        $form_quiz_id
    ), ARRAY_A);
}

$selected_quiz_questions = [];
if ($form_quiz_id > 0) {
    $selected_quiz_questions = $wpdb->get_results($wpdb->prepare(
        "SELECT qq.question_id, qq.mark, qq.question_order, q.question_text, q.question_type
         FROM {$quiz_questions_table} qq
         INNER JOIN {$questions_table} q ON q.id = qq.question_id
         WHERE qq.quiz_id = %d
         ORDER BY qq.question_order ASC, qq.id ASC",
        $form_quiz_id
    ), ARRAY_A);
}

$selected_question_ids_from_post = isset($_POST['questions']) && is_array($_POST['questions'])
    ? array_values(array_filter(array_map('intval', $_POST['questions'])))
    : [];

if (!empty($selected_question_ids_from_post)) {
    $placeholders = implode(',', array_fill(0, count($selected_question_ids_from_post), '%d'));
    $selected_quiz_questions = $wpdb->get_results($wpdb->prepare(
        "SELECT q.id AS question_id, q.question_text, q.question_type
         FROM {$questions_table} q
         WHERE q.id IN ({$placeholders})",
        $selected_question_ids_from_post
    ), ARRAY_A);
    foreach ($selected_quiz_questions as &$sq) {
        $sq['mark'] = isset($_POST['marks'][(int) $sq['question_id']]) ? (float) $_POST['marks'][(int) $sq['question_id']] : 1.00;
    }
    unset($sq);
}

$bank_questions = $wpdb->get_results($wpdb->prepare(
    "SELECT id, question_type, category, question_text, default_mark
     FROM {$questions_table}
     WHERE created_by = %d
     ORDER BY created_at DESC
     LIMIT 200",
    $staff_id
), ARRAY_A);

$quiz_open_date_value = isset($_POST['open_date'])
    ? sanitize_text_field(wp_unslash($_POST['open_date']))
    : $to_datetime_local($quiz_record['open_date'] ?? ($quiz_data_arr['open_date'] ?? ''));
$quiz_close_date_value = isset($_POST['close_date'])
    ? sanitize_text_field(wp_unslash($_POST['close_date']))
    : $to_datetime_local($quiz_record['close_date'] ?? ($quiz_data_arr['close_date'] ?? ''));

$manual_grading_rows = [];
if ($is_item_page && $selected_content_type === 'quiz' && $form_quiz_id > 0) {
    $manual_grading_rows = $wpdb->get_results($wpdb->prepare(
        "SELECT qr.id AS response_id,
                qr.response_data,
                qr.is_correct,
                qr.marks_earned,
                qr.feedback,
                qr.graded_by,
                qr.graded_at,
                qa.student_id,
                qa.attempt_number,
                qa.final_grade,
                q.question_text,
                q.question_type,
                qq.mark AS max_mark
         FROM {$wpdb->prefix}nds_quiz_responses qr
         INNER JOIN {$wpdb->prefix}nds_quiz_attempts qa ON qa.id = qr.attempt_id
         INNER JOIN {$questions_table} q ON q.id = qr.question_id
         LEFT JOIN {$quiz_questions_table} qq ON qq.quiz_id = qa.quiz_id AND qq.question_id = qr.question_id
         WHERE qa.quiz_id = %d
           AND q.question_type IN ('essay', 'short_answer')
         ORDER BY qa.attempt_number DESC, qr.id DESC",
        $form_quiz_id
    ), ARRAY_A);
}

// -------------------------------------------------------------------
// 8. Start HTML Output
// -------------------------------------------------------------------
?>
<div class="nds-moodle-content-manager">
    <!-- Success/Error Messages -->
    <?php if ($notice): ?>
        <div class="mb-6 bg-green-50 border-l-4 border-green-500 p-4 rounded-r-lg animate-pulse">
            <div class="flex items-center">
                <i class="fas fa-check-circle text-green-500 mr-3 text-xl"></i>
                <p class="text-green-700"><?php echo esc_html($notice); ?></p>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded-r-lg">
            <div class="flex items-center">
                <i class="fas fa-exclamation-triangle text-red-500 mr-3 text-xl"></i>
                <p class="text-red-700"><?php echo esc_html($error); ?></p>
            </div>
        </div>
    <?php endif; ?>

    <!-- Course Context Bar (Moodle-style) -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div class="flex items-center space-x-3 min-w-0">
                <i class="fas fa-graduation-cap text-blue-500 text-xl"></i>
                <span class="font-semibold text-gray-700">Course:</span>
                <select id="moodle-course-selector" class="w-full md:w-auto border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500" <?php echo empty($courses_taught_safe) ? 'disabled' : ''; ?>>
                    <?php if (empty($courses_taught_safe)) : ?>
                        <option value="">No courses assigned</option>
                    <?php endif; ?>
                    <?php foreach ($courses_taught_safe as $course): ?>
                        <option value="<?php echo esc_attr($course['id']); ?>" <?php selected($selected_course_id, $course['id']); ?>>
                            <?php echo esc_html($course['name']); ?> (<?php echo esc_html($course['code'] ?? 'N/A'); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <?php if (!empty($available_modules)): ?>
            <div class="flex items-center space-x-3">
                <i class="fas fa-cubes text-purple-500 text-xl"></i>
                <span class="font-semibold text-gray-700">Section:</span>
                <select id="moodle-module-selector" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="0">-- All Sections --</option>
                    <?php foreach ($available_modules as $module): ?>
                        <option value="<?php echo esc_attr($module['id']); ?>" <?php selected($selected_module_id, $module['id']); ?>>
                            <?php echo esc_html($module['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- LEFT COLUMN: Add Content Form (Moodle "Add an activity or resource" style) -->
        <div class="<?php echo ($is_type_page || $is_item_page) ? 'lg:col-span-3' : 'lg:col-span-1'; ?>">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 sticky top-24">
                <div class="border-b border-gray-200 p-5 bg-gradient-to-r from-blue-50 to-white rounded-t-xl">
                    <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                        <i class="fas fa-plus-circle text-blue-600 mr-2"></i>
                        <?php echo $is_item_page ? 'Edit Content Item' : ($is_type_page ? esc_html($content_types[$selected_content_type]['label']) . ' Page' : 'Add Course Content'); ?>
                    </h3>
                    <p class="text-sm text-gray-500 mt-1"><?php echo $is_item_page ? 'Update this content item on its own page.' : ($is_type_page ? 'You are creating one content type on its own page view.' : 'Upload files, add links, or create activities'); ?></p>
                </div>
                
                <div class="p-5">
                    <!-- Content Type Selector (Moodle Activity Chooser) -->
                    <div class="mb-5">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Content Type</label>
                        <div class="grid grid-cols-2 gap-2">
                            <?php foreach ($content_types as $type_key => $type_info): ?>
                                <?php $type_url = add_query_arg('content_view', $type_key, $type_page_base_url); ?>
                                <a href="<?php echo esc_url($type_url); ?>"
                                        class="moodle-type-selector block p-3 rounded-lg border-2 transition-all duration-200 hover:shadow-md <?php echo ($type_key === $selected_content_type) ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-blue-300'; ?>">
                                    <i class="fas <?php echo esc_attr($type_info['icon']); ?> text-<?php echo esc_attr($type_info['color']); ?>-500 text-xl block mb-1"></i>
                                    <span class="text-xs font-medium"><?php echo esc_html($type_info['label']); ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <form method="post" enctype="multipart/form-data" class="space-y-4" id="moodle-content-form">
                        <?php if ($is_item_page): ?>
                            <?php wp_nonce_field('nds_update_content', 'nds_update_nonce'); ?>
                            <input type="hidden" name="nds_update_content_id" value="<?php echo esc_attr((int) $selected_content_item['id']); ?>">
                        <?php else: ?>
                            <?php wp_nonce_field('nds_upload_content', 'nds_content_nonce'); ?>
                            <input type="hidden" name="nds_submit_content" value="1">
                        <?php endif; ?>
                        <input type="hidden" name="course_id" id="form_course_id" value="<?php echo esc_attr($selected_course_id); ?>">
                        <input type="hidden" name="content_type" id="form_content_type" value="<?php echo esc_attr($selected_content_type); ?>">
                        <input type="hidden" name="quiz_id" value="<?php echo esc_attr($form_quiz_id); ?>">
                        
                        <!-- Title -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Title *</label>
                            <input type="text" name="content_title" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                value="<?php echo esc_attr($form_title); ?>"
                                   placeholder="e.g., Lecture 1: Introduction">
                        </div>
                        
                        <!-- Module/Section Selector -->
                        <?php if (!empty($available_modules)): ?>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Section (Topic/Week)</label>
                            <select name="module_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                <option value="0">-- General Section --</option>
                                <?php foreach ($available_modules as $module): ?>
                                    <option value="<?php echo esc_attr($module['id']); ?>" <?php selected($form_module_id, (int) $module['id']); ?>>
                                        <?php echo esc_html($module['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="text-xs text-gray-500 mt-1">Organize content into sections (Moodle topics/weeks)</p>
                        </div>
                        <?php endif; ?>
                        
                        <!-- File Upload Field (shown for resource type) -->
                        <div id="file_upload_field" class="upload-area <?php echo in_array($selected_content_type, ['resource', 'assignment', 'folder'], true) ? '' : 'hidden'; ?>">
                            <label class="block text-sm font-medium text-gray-700 mb-1">File</label>
                            <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-blue-500 transition cursor-pointer"
                                 onclick="document.getElementById('content_file_input').click()">
                                <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-2"></i>
                                <p class="text-sm text-gray-600">Click or drag file to upload</p>
                                <p class="text-xs text-gray-400 mt-1">PDF, DOC, PPT, JPG, MP4, ZIP (Max 100MB)</p>
                                <input type="file" name="content_file" id="content_file_input" class="hidden" accept=".pdf,.doc,.docx,.ppt,.pptx,.jpg,.jpeg,.png,.gif,.mp4,.zip,.txt">
                            </div>
                            <div id="file_name_display" class="mt-2 text-xs text-gray-600 hidden"></div>
                        </div>
                        
                        <!-- URL Field (shown for url type) -->
                        <div id="url_field" class="<?php echo in_array($selected_content_type, ['url', 'forum'], true) ? '' : 'hidden'; ?>">
                            <label class="block text-sm font-medium text-gray-700 mb-1">URL/Link</label>
                            <input type="url" name="resource_url" class="w-full px-3 py-2 border border-gray-300 rounded-lg"
                                value="<?php echo esc_attr($form_resource_url); ?>"
                                   placeholder="https://example.com/resource">
                        </div>
                        
                        <!-- Description -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                            <textarea name="content_description" rows="4"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                      placeholder="Add a description, instructions, or summary..."><?php echo esc_textarea($form_description); ?></textarea>
                        </div>

                        <?php if ($selected_content_type === 'quiz'): ?>
                        <div class="bg-gray-50 rounded-lg p-5 border border-gray-200 space-y-5">
                            <h4 class="text-base font-semibold text-gray-800 flex items-center">
                                <i class="fas fa-puzzle-piece text-blue-600 mr-2"></i> Quiz Builder
                            </h4>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Time Limit (minutes)</label>
                                    <input type="number" name="time_limit" min="0" step="5" value="<?php echo esc_attr(isset($_POST['time_limit']) ? (int) $_POST['time_limit'] : (int) ($quiz_record['time_limit'] ?? $selected_content_item['time_limit_minutes'] ?? 0)); ?>" class="w-full px-3 py-2 border rounded-lg">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Attempts Allowed</label>
                                    <select name="attempts_allowed" class="w-full px-3 py-2 border rounded-lg">
                                        <?php $attempt_val = isset($_POST['attempts_allowed']) ? (int) $_POST['attempts_allowed'] : (int) ($quiz_record['attempts_allowed'] ?? $selected_content_item['attempts_allowed'] ?? 1); ?>
                                        <option value="1" <?php selected($attempt_val, 1); ?>>1 attempt</option>
                                        <option value="2" <?php selected($attempt_val, 2); ?>>2 attempts</option>
                                        <option value="3" <?php selected($attempt_val, 3); ?>>3 attempts</option>
                                        <option value="5" <?php selected($attempt_val, 5); ?>>5 attempts</option>
                                        <option value="0" <?php selected($attempt_val, 0); ?>>Unlimited</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Passing Grade (%)</label>
                                    <input type="number" name="passing_grade" min="0" max="100" step="0.01" value="<?php echo esc_attr(isset($_POST['passing_grade']) ? (float) $_POST['passing_grade'] : (float) ($quiz_record['passing_grade'] ?? $selected_content_item['pass_percentage'] ?? 60)); ?>" class="w-full px-3 py-2 border rounded-lg">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Open Date</label>
                                    <input type="datetime-local" name="open_date" value="<?php echo esc_attr($quiz_open_date_value); ?>" class="w-full px-3 py-2 border rounded-lg">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Close Date</label>
                                    <input type="datetime-local" name="close_date" value="<?php echo esc_attr($quiz_close_date_value); ?>" class="w-full px-3 py-2 border rounded-lg">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Grade Method</label>
                                    <?php $grade_method_val = isset($_POST['grade_method']) ? sanitize_key($_POST['grade_method']) : (string) ($quiz_record['grade_method'] ?? $quiz_data_arr['grade_method'] ?? 'highest'); ?>
                                    <select name="grade_method" class="w-full px-3 py-2 border rounded-lg">
                                        <option value="highest" <?php selected($grade_method_val, 'highest'); ?>>Highest grade</option>
                                        <option value="average" <?php selected($grade_method_val, 'average'); ?>>Average grade</option>
                                        <option value="first" <?php selected($grade_method_val, 'first'); ?>>First attempt</option>
                                        <option value="last" <?php selected($grade_method_val, 'last'); ?>>Last attempt</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Questions per page</label>
                                    <?php $qpp_val = isset($_POST['questions_per_page']) ? (int) $_POST['questions_per_page'] : (int) ($quiz_record['questions_per_page'] ?? $quiz_data_arr['questions_per_page'] ?? 0); ?>
                                    <select name="questions_per_page" class="w-full px-3 py-2 border rounded-lg">
                                        <option value="0" <?php selected($qpp_val, 0); ?>>All on one page</option>
                                        <option value="1" <?php selected($qpp_val, 1); ?>>1 question per page</option>
                                        <option value="5" <?php selected($qpp_val, 5); ?>>5 questions per page</option>
                                        <option value="10" <?php selected($qpp_val, 10); ?>>10 questions per page</option>
                                    </select>
                                </div>
                                <div class="flex items-end">
                                    <label class="flex items-center text-sm text-gray-700">
                                        <input type="checkbox" name="review_attempts" value="1" <?php checked(isset($_POST['review_attempts']) ? 1 : (int) ($quiz_record['review_attempts'] ?? $quiz_data_arr['review_attempts'] ?? 1), 1); ?> class="mr-2">
                                        Allow review attempts
                                    </label>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <label class="flex items-center"><input type="checkbox" name="shuffle_questions" value="1" <?php checked(isset($_POST['shuffle_questions']) ? 1 : (int) ($quiz_record['shuffle_questions'] ?? $selected_content_item['shuffle_questions'] ?? 0), 1); ?> class="mr-2">Shuffle questions</label>
                                <label class="flex items-center"><input type="checkbox" name="shuffle_answers" value="1" <?php checked(isset($_POST['shuffle_answers']) ? 1 : (int) ($quiz_record['shuffle_answers'] ?? $quiz_data_arr['shuffle_answers'] ?? 0), 1); ?> class="mr-2">Shuffle answers</label>
                                <label class="flex items-center"><input type="checkbox" name="show_answers_after" value="1" <?php checked(isset($_POST['show_answers_after']) ? 1 : (int) ($quiz_record['show_answers_after'] ?? $quiz_data_arr['show_answers_after'] ?? 0), 1); ?> class="mr-2">Show answers after submission</label>
                                <label class="flex items-center"><input type="checkbox" name="show_correct_answers" value="1" <?php checked(isset($_POST['show_correct_answers']) ? 1 : (int) ($quiz_record['show_correct_answers'] ?? $quiz_data_arr['show_correct_answers'] ?? 0), 1); ?> class="mr-2">Show correct answers</label>
                                <label class="flex items-center"><input type="checkbox" name="requires_lockdown" value="1" <?php checked(isset($_POST['requires_lockdown']) ? 1 : (int) ($quiz_record['requires_lockdown'] ?? $quiz_data_arr['requires_lockdown'] ?? 0), 1); ?> class="mr-2">Requires lockdown browser</label>
                            </div>

                            <div class="border-t pt-4">
                                <div class="flex items-center justify-between mb-3">
                                    <h5 class="font-medium text-gray-800">Question Bank</h5>
                                    <button type="button" id="open-question-selector" class="px-3 py-1.5 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700">Add from Question Bank</button>
                                </div>

                                <div id="selected-questions-list" class="space-y-2">
                                    <?php if (empty($selected_quiz_questions)): ?>
                                        <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-sm text-gray-500" data-empty-state="1">No questions selected yet.</div>
                                    <?php else: ?>
                                        <?php foreach ($selected_quiz_questions as $sq): ?>
                                            <?php
                                            $sqid = (int) $sq['question_id'];
                                            $sqmark = isset($sq['mark']) ? (float) $sq['mark'] : 1.00;
                                            ?>
                                            <div class="selected-question-item border rounded-lg p-3 bg-white" draggable="true" data-question-id="<?php echo esc_attr($sqid); ?>">
                                                <div class="flex items-start gap-3">
                                                    <button type="button" class="drag-handle text-gray-400 mt-1" title="Drag to reorder"><i class="fas fa-grip-vertical"></i></button>
                                                    <div class="flex-1">
                                                        <div class="text-sm font-medium text-gray-800"><?php echo esc_html(wp_trim_words((string) ($sq['question_text'] ?? ''), 20)); ?></div>
                                                        <div class="text-xs text-gray-500"><?php echo esc_html(ucwords(str_replace('_', ' ', (string) ($sq['question_type'] ?? 'question')))); ?></div>
                                                    </div>
                                                    <div class="w-24">
                                                        <input type="number" name="marks[<?php echo esc_attr($sqid); ?>]" min="0.01" step="0.01" value="<?php echo esc_attr(number_format($sqmark, 2, '.', '')); ?>" class="w-full px-2 py-1 border rounded text-sm" title="Mark">
                                                    </div>
                                                    <button type="button" class="remove-selected-question text-red-600 hover:text-red-800" title="Remove"><i class="fas fa-trash"></i></button>
                                                </div>
                                                <input type="hidden" name="questions[]" value="<?php echo esc_attr($sqid); ?>">
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>

                                <?php
                                $question_categories = [];
                                foreach ($bank_questions as $bq) {
                                    $cat = trim((string) ($bq['category'] ?? 'General'));
                                    if ($cat === '') {
                                        $cat = 'General';
                                    }
                                    $question_categories[$cat] = true;
                                }
                                $question_categories = array_keys($question_categories);
                                sort($question_categories);
                                ?>
                                <div id="question-selector-modal" class="hidden fixed inset-0 bg-black/50 z-50 items-center justify-center p-4">
                                    <div class="bg-white w-full max-w-4xl rounded-xl shadow-xl overflow-hidden">
                                        <div class="px-4 py-3 border-b flex items-center justify-between">
                                            <h4 class="font-semibold text-gray-800">Question Bank</h4>
                                            <button type="button" id="close-question-selector" class="text-gray-500 hover:text-gray-700"><i class="fas fa-times"></i></button>
                                        </div>
                                        <div class="p-4 border-b grid grid-cols-1 md:grid-cols-3 gap-3">
                                            <input type="text" id="question-search" class="px-3 py-2 border rounded-lg text-sm md:col-span-2" placeholder="Search questions...">
                                            <select id="question-category-filter" class="px-3 py-2 border rounded-lg text-sm">
                                                <option value="">All Categories</option>
                                                <?php foreach ($question_categories as $cat): ?>
                                                    <option value="<?php echo esc_attr(strtolower($cat)); ?>"><?php echo esc_html($cat); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div id="question-bank-modal-list" class="p-4 max-h-[60vh] overflow-y-auto space-y-2">
                                            <?php foreach ($bank_questions as $bq): ?>
                                                <?php
                                                $bq_id = (int) $bq['id'];
                                                $bq_text = (string) ($bq['question_text'] ?? '');
                                                $bq_type = (string) ($bq['question_type'] ?? 'question');
                                                $bq_cat = trim((string) ($bq['category'] ?? 'General'));
                                                if ($bq_cat === '') {
                                                    $bq_cat = 'General';
                                                }
                                                $bq_mark = (float) ($bq['default_mark'] ?? 1.00);
                                                ?>
                                                <div class="question-bank-row border rounded-lg p-3" data-question-id="<?php echo esc_attr($bq_id); ?>" data-text="<?php echo esc_attr(strtolower(wp_strip_all_tags($bq_text))); ?>" data-category="<?php echo esc_attr(strtolower($bq_cat)); ?>" data-type="<?php echo esc_attr($bq_type); ?>" data-mark="<?php echo esc_attr(number_format($bq_mark, 2, '.', '')); ?>" data-question-text="<?php echo esc_attr(wp_trim_words($bq_text, 20)); ?>">
                                                    <div class="flex items-start justify-between gap-3">
                                                        <div>
                                                            <div class="text-sm font-medium text-gray-800"><?php echo esc_html(wp_trim_words($bq_text, 20)); ?></div>
                                                            <div class="text-xs text-gray-500"><?php echo esc_html(ucwords(str_replace('_', ' ', $bq_type))); ?> | <?php echo esc_html($bq_cat); ?></div>
                                                        </div>
                                                        <button type="button" class="add-question-btn px-3 py-1 rounded border border-blue-200 text-blue-700 hover:bg-blue-50 text-sm">Add</button>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Access Restrictions (Moodle-style) -->
                        <div class="border-t border-gray-200 pt-4 mt-2">
                            <p class="text-sm font-medium text-gray-700 mb-3 flex items-center">
                                <i class="fas fa-lock mr-2 text-gray-500"></i> Restrict Access
                            </p>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">Available from</label>
                                    <input type="datetime-local" name="access_start" value="<?php echo esc_attr($form_access_start); ?>" class="w-full px-2 py-1.5 text-sm border rounded">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">Available until</label>
                                    <input type="datetime-local" name="access_end" value="<?php echo esc_attr($form_access_end); ?>" class="w-full px-2 py-1.5 text-sm border rounded">
                                </div>
                            </div>
                        </div>

                        <?php if ($is_item_page && !empty($selected_content_item['attachment_url'])): ?>
                        <div class="bg-gray-50 border border-gray-200 rounded-lg p-3 text-sm">
                            <p class="font-medium text-gray-700">Current attachment/link:</p>
                            <a class="text-blue-600 break-all" href="<?php echo esc_url($selected_content_item['attachment_url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($selected_content_item['file_name'] ?: $selected_content_item['attachment_url']); ?></a>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Visibility -->
                        <div class="flex items-center justify-between">
                            <label class="flex items-center cursor-pointer">
                                <input type="checkbox" name="is_visible" <?php checked($form_visible, 1); ?> class="mr-2">
                                <span class="text-sm text-gray-700">Visible to students</span>
                            </label>
                            <div class="flex items-center gap-2">
                                <?php if ($is_type_page || $is_item_page): ?>
                                    <a href="<?php echo esc_url($type_page_base_url); ?>" class="px-4 py-2 rounded-lg border border-gray-300 text-sm text-gray-700 hover:bg-gray-50">Back</a>
                                <?php endif; ?>
                                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-medium transition shadow-sm">
                                    <i class="fas fa-save mr-2"></i> <?php echo $is_item_page ? 'Update' : 'Save'; ?>
                                </button>
                            </div>
                        </div>
                    </form>

                    <?php if ($is_item_page && $selected_content_type === 'quiz'): ?>
                    <div class="mt-6 border-t border-gray-200 pt-5">
                        <h4 class="text-base font-semibold text-gray-800 flex items-center mb-3">
                            <i class="fas fa-clipboard-check text-emerald-600 mr-2"></i> Manual Grading (Essay/Short Answer)
                        </h4>
                        <?php if (empty($manual_grading_rows)): ?>
                            <div class="text-sm text-gray-500 bg-gray-50 border border-gray-200 rounded-lg p-4">No manual responses pending or available for this quiz yet.</div>
                        <?php else: ?>
                            <div class="space-y-3 max-h-[28rem] overflow-y-auto pr-1">
                                <?php foreach ($manual_grading_rows as $grade_row): ?>
                                    <form method="post" class="border border-gray-200 rounded-lg p-4 bg-gray-50 space-y-3">
                                        <?php wp_nonce_field('nds_grade_response', 'nds_grade_response_nonce'); ?>
                                        <input type="hidden" name="nds_grade_response_id" value="<?php echo (int) $grade_row['response_id']; ?>">
                                        <div class="flex items-center justify-between text-xs text-gray-500">
                                            <span>Student ID: <?php echo (int) $grade_row['student_id']; ?> | Attempt: <?php echo (int) $grade_row['attempt_number']; ?></span>
                                            <span>Max mark: <?php echo esc_html(number_format((float) ($grade_row['max_mark'] ?? 1), 2)); ?></span>
                                        </div>
                                        <div class="text-sm text-gray-800 font-medium"><?php echo esc_html(wp_trim_words((string) $grade_row['question_text'], 30)); ?></div>
                                        <div class="text-sm bg-white border border-gray-200 rounded p-3"><strong>Response:</strong> <?php echo nl2br(esc_html((string) ($grade_row['response_data'] ?? ''))); ?></div>
                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
                                            <div>
                                                <label class="block text-xs text-gray-600 mb-1">Marks earned</label>
                                                <input type="number" step="0.01" min="0" name="marks_earned" value="<?php echo esc_attr(number_format((float) ($grade_row['marks_earned'] ?? 0), 2, '.', '')); ?>" class="w-full px-3 py-2 border rounded-lg text-sm">
                                            </div>
                                            <div>
                                                <label class="block text-xs text-gray-600 mb-1">Correctness</label>
                                                <select name="is_correct" class="w-full px-3 py-2 border rounded-lg text-sm">
                                                    <option value="1" <?php selected((int) ($grade_row['is_correct'] ?? 0), 1); ?>>Correct</option>
                                                    <option value="0" <?php selected((int) ($grade_row['is_correct'] ?? 0), 0); ?>>Needs review</option>
                                                </select>
                                            </div>
                                            <div class="text-right">
                                                <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm">Save Grade</button>
                                            </div>
                                        </div>
                                        <div>
                                            <label class="block text-xs text-gray-600 mb-1">Feedback</label>
                                            <textarea name="grader_feedback" rows="2" class="w-full px-3 py-2 border rounded-lg text-sm"><?php echo esc_textarea((string) ($grade_row['feedback'] ?? '')); ?></textarea>
                                        </div>
                                    </form>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <?php if (!$is_type_page && !$is_item_page): ?>
        <!-- RIGHT COLUMN: Existing Content Library (Moodle "Course content" view) -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                <div class="border-b border-gray-200 p-5 bg-gray-50 rounded-t-xl">
                    <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                        <i class="fas fa-database text-green-600 mr-2"></i>
                        Course Content Library
                    </h3>
                    <p class="text-sm text-gray-500">Manage and organize your learning materials</p>
                </div>
                
                <div class="p-5">
                    <?php if (empty($existing_content)): ?>
                        <div class="text-center py-12">
                            <i class="fas fa-folder-open text-gray-300 text-5xl mb-3"></i>
                            <p class="text-gray-500">No content yet. Use the form to add your first resource.</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($existing_content as $item): 
                                $type_info = $content_types[$item['content_type']] ?? $content_types['resource'];
                                $item_page_url = add_query_arg(['content_view' => sanitize_key((string) $item['content_type']), 'content_item_id' => (int) $item['id']], $type_page_base_url);
                            ?>
                                <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition group">
                                    <div class="flex items-start justify-between">
                                        <div class="flex items-start space-x-3">
                                            <div class="w-10 h-10 rounded-lg bg-<?php echo $type_info['color']; ?>-100 flex items-center justify-center flex-shrink-0">
                                                <i class="fas <?php echo $type_info['icon']; ?> text-<?php echo $type_info['color']; ?>-600"></i>
                                            </div>
                                            <div>
                                                <h4 class="font-medium text-gray-800">
                                                    <a href="<?php echo esc_url($item_page_url); ?>" class="hover:text-blue-700 hover:underline">
                                                        <?php echo esc_html($item['title']); ?>
                                                    </a>
                                                </h4>
                                                <?php if ($item['description']): ?>
                                                    <p class="text-sm text-gray-500 mt-1"><?php echo wp_trim_words($item['description'], 20); ?></p>
                                                <?php endif; ?>
                                                <div class="flex flex-wrap gap-3 mt-2 text-xs text-gray-400">
                                                    <span><i class="far fa-clock mr-1"></i> <?php echo date_i18n(get_option('date_format'), strtotime($item['created_at'])); ?></span>
                                                    <?php if ($item['file_name']): ?>
                                                        <span><i class="fas fa-file mr-1"></i> <?php echo esc_html($item['file_name']); ?></span>
                                                        <span><?php echo size_format($item['file_size'], 2); ?></span>
                                                    <?php endif; ?>
                                                    <?php if ($item['access_start']): ?>
                                                        <span><i class="fas fa-calendar-alt mr-1"></i> Starts: <?php echo date_i18n(get_option('date_format'), strtotime($item['access_start'])); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="opacity-0 group-hover:opacity-100 transition">
                                            <div class="flex space-x-2">
                                                <a href="<?php echo esc_url($item_page_url); ?>" class="text-indigo-600 hover:text-indigo-800 px-2 py-1 rounded border border-indigo-200 text-xs font-medium" title="Open item page">
                                                    View
                                                </a>
                                                <?php if ($item['attachment_url']): ?>
                                                    <a href="<?php echo esc_url($item['attachment_url']); ?>" target="_blank" class="text-blue-600 hover:text-blue-800 p-1" title="Preview">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="<?php echo esc_url($item['attachment_url']); ?>" download class="text-green-600 hover:text-green-800 p-1" title="Download">
                                                        <i class="fas fa-download"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <button class="text-red-600 hover:text-red-800 p-1 delete-content" data-id="<?php echo $item['id']; ?>" title="Delete">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Quick Tips (Moodle-style documentation) -->
            <div class="mt-6 bg-blue-50 rounded-xl p-4 border border-blue-100">
                <div class="flex items-start space-x-3">
                    <i class="fas fa-lightbulb text-blue-600 text-xl mt-0.5"></i>
                    <div>
                        <h4 class="font-medium text-blue-800">Tips for organizing your course</h4>
                        <ul class="text-sm text-blue-700 mt-1 space-y-1">
                            <li>• Use <strong>Sections</strong> to organize content by week, topic, or module</li>
                            <li>• Set <strong>availability dates</strong> to control when students can access materials</li>
                            <li>• Hide content until it's ready using the visibility toggle</li>
                            <li>• Drag & drop files directly from your computer (coming soon!)</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
(function() {
    const openSelectorBtn = document.getElementById('open-question-selector');
    const closeSelectorBtn = document.getElementById('close-question-selector');
    const selectorModal = document.getElementById('question-selector-modal');
    const searchInput = document.getElementById('question-search');
    const categoryFilter = document.getElementById('question-category-filter');
    const selectedList = document.getElementById('selected-questions-list');

    function openQuestionSelector() {
        if (!selectorModal) return;
        selectorModal.classList.remove('hidden');
        selectorModal.classList.add('flex');
    }

    function closeQuestionSelector() {
        if (!selectorModal) return;
        selectorModal.classList.add('hidden');
        selectorModal.classList.remove('flex');
    }

    function ensureEmptySelectedState() {
        if (!selectedList) return;
        const items = selectedList.querySelectorAll('.selected-question-item');
        const emptyState = selectedList.querySelector('[data-empty-state="1"]');
        if (items.length === 0 && !emptyState) {
            const div = document.createElement('div');
            div.className = 'border-2 border-dashed border-gray-300 rounded-lg p-4 text-sm text-gray-500';
            div.setAttribute('data-empty-state', '1');
            div.textContent = 'No questions selected yet.';
            selectedList.appendChild(div);
        }
        if (items.length > 0 && emptyState) {
            emptyState.remove();
        }
    }

    function selectedQuestionIds() {
        if (!selectedList) return new Set();
        const ids = new Set();
        selectedList.querySelectorAll('.selected-question-item').forEach((row) => {
            const qid = row.getAttribute('data-question-id');
            if (qid) ids.add(qid);
        });
        return ids;
    }

    function addQuestionToSelected(row) {
        if (!selectedList || !row) return;
        const qid = row.getAttribute('data-question-id');
        if (!qid) return;

        if (selectedQuestionIds().has(qid)) {
            return;
        }

        const questionText = row.getAttribute('data-question-text') || 'Question';
        const questionType = row.getAttribute('data-type') || 'question';
        const mark = row.getAttribute('data-mark') || '1.00';

        const wrapper = document.createElement('div');
        wrapper.className = 'selected-question-item border rounded-lg p-3 bg-white';
        wrapper.setAttribute('draggable', 'true');
        wrapper.setAttribute('data-question-id', qid);
        wrapper.innerHTML = `
            <div class="flex items-start gap-3">
                <button type="button" class="drag-handle text-gray-400 mt-1" title="Drag to reorder"><i class="fas fa-grip-vertical"></i></button>
                <div class="flex-1">
                    <div class="text-sm font-medium text-gray-800"></div>
                    <div class="text-xs text-gray-500"></div>
                </div>
                <div class="w-24">
                    <input type="number" name="marks[${qid}]" min="0.01" step="0.01" value="${mark}" class="w-full px-2 py-1 border rounded text-sm" title="Mark">
                </div>
                <button type="button" class="remove-selected-question text-red-600 hover:text-red-800" title="Remove"><i class="fas fa-trash"></i></button>
            </div>
            <input type="hidden" name="questions[]" value="${qid}">
        `;
        wrapper.querySelector('.text-sm').textContent = questionText;
        wrapper.querySelector('.text-xs').textContent = questionType.replaceAll('_', ' ');
        selectedList.appendChild(wrapper);
        ensureEmptySelectedState();
        attachSelectedRowHandlers(wrapper);
    }

    function attachSelectedRowHandlers(row) {
        const removeBtn = row.querySelector('.remove-selected-question');
        if (removeBtn) {
            removeBtn.addEventListener('click', function() {
                row.remove();
                ensureEmptySelectedState();
            });
        }

        row.addEventListener('dragstart', function(e) {
            row.classList.add('opacity-50');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', row.getAttribute('data-question-id') || '');
            window.__draggingQuestionRow = row;
        });
        row.addEventListener('dragend', function() {
            row.classList.remove('opacity-50');
            window.__draggingQuestionRow = null;
        });
        row.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
        });
        row.addEventListener('drop', function(e) {
            e.preventDefault();
            const dragging = window.__draggingQuestionRow;
            if (!dragging || dragging === row || !selectedList) return;
            const rowRect = row.getBoundingClientRect();
            const before = e.clientY < rowRect.top + rowRect.height / 2;
            selectedList.insertBefore(dragging, before ? row : row.nextSibling);
        });
    }

    function filterQuestionRows() {
        const query = (searchInput?.value || '').toLowerCase().trim();
        const category = (categoryFilter?.value || '').toLowerCase().trim();
        document.querySelectorAll('.question-bank-row').forEach((row) => {
            const text = row.getAttribute('data-text') || '';
            const rowCategory = row.getAttribute('data-category') || '';
            const matchQuery = query === '' || text.includes(query);
            const matchCategory = category === '' || rowCategory === category;
            row.classList.toggle('hidden', !(matchQuery && matchCategory));
        });
    }

    if (openSelectorBtn) openSelectorBtn.addEventListener('click', openQuestionSelector);
    if (closeSelectorBtn) closeSelectorBtn.addEventListener('click', closeQuestionSelector);
    if (selectorModal) {
        selectorModal.addEventListener('click', function(e) {
            if (e.target === selectorModal) closeQuestionSelector();
        });
    }
    if (searchInput) searchInput.addEventListener('input', filterQuestionRows);
    if (categoryFilter) categoryFilter.addEventListener('change', filterQuestionRows);

    document.querySelectorAll('.add-question-btn').forEach((btn) => {
        btn.addEventListener('click', function() {
            addQuestionToSelected(btn.closest('.question-bank-row'));
        });
    });

    if (selectedList) {
        selectedList.querySelectorAll('.selected-question-item').forEach(attachSelectedRowHandlers);
        ensureEmptySelectedState();
    }
})();

// File upload display
const contentFileInput = document.getElementById('content_file_input');
if (contentFileInput) {
    contentFileInput.addEventListener('change', function(e) {
        const fileName = e.target.files[0]?.name;
        const display = document.getElementById('file_name_display');
        if (!display) return;
        if (fileName) {
            display.innerHTML = '<i class="fas fa-check-circle text-green-500 mr-1"></i> ' + fileName;
            display.classList.remove('hidden');
        } else {
            display.classList.add('hidden');
        }
    });
}

// Course selector change (reload page with new course context)
document.getElementById('moodle-course-selector')?.addEventListener('change', function(e) {
    const courseId = e.target.value;
    const currentUrl = new URL(window.location.href);
    currentUrl.searchParams.set('course_id', courseId);
    currentUrl.searchParams.delete('module_id');
    window.location.href = currentUrl.toString();
});

// Module selector change (filter content)
document.getElementById('moodle-module-selector')?.addEventListener('change', function(e) {
    const moduleId = e.target.value;
    const currentUrl = new URL(window.location.href);
    if (moduleId && moduleId !== '0') {
        currentUrl.searchParams.set('module_id', moduleId);
    } else {
        currentUrl.searchParams.delete('module_id');
    }
    window.location.href = currentUrl.toString();
});

// Delete content with confirmation
document.querySelectorAll('.delete-content').forEach(btn => {
    btn.addEventListener('click', async function() {
        const contentId = this.dataset.id;
        if (confirm('Are you sure you want to delete this content? This action cannot be undone.')) {
            // Implement AJAX delete or form submission
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';
            form.innerHTML = `
                <input type="hidden" name="delete_content_id" value="${contentId}">
                <input type="hidden" name="nds_delete_nonce" value="<?php echo wp_create_nonce('nds_delete_content'); ?>">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    });
});
</script>

<style>
/* Moodle-style enhancements */
.upload-area .border-dashed {
    transition: all 0.2s ease;
}
.upload-area .border-dashed:hover {
    background-color: #f8fafc;
}
.moodle-type-selector {
    transition: all 0.2s ease;
}
.moodle-type-selector:hover {
    transform: translateY(-2px);
}
</style>
