<?php
/**
 * Module Management Page for Qualifications/Courses
 * Allows admins to view, edit, delete, and assign lecturers to modules
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

function nds_get_module_management_redirect_url($program_id = 0, $course_id = 0, $extra_args = array())
{
    $args = array('page' => 'nds-module-management');

    if ($program_id > 0) {
        $args['program_id'] = intval($program_id);
    }

    if ($course_id > 0) {
        $args['course_id'] = intval($course_id);
    }

    if (!empty($extra_args)) {
        $args = array_merge($args, $extra_args);
    }

    return add_query_arg($args, admin_url('admin.php'));
}

function nds_get_module_management_columns($table_name)
{
    global $wpdb;

    $columns = $wpdb->get_col("DESCRIBE {$table_name}");
    if (!is_array($columns)) {
        return array();
    }

    return $columns;
}

function nds_build_module_management_payload($request_data, $module_columns, $existing_module = array())
{
    $module_name = isset($request_data['module_name']) ? sanitize_text_field($request_data['module_name']) : '';
    $module_code = isset($request_data['module_code']) ? sanitize_text_field($request_data['module_code']) : '';
    $module_type = isset($request_data['module_type']) ? sanitize_text_field($request_data['module_type']) : 'theory';
    $module_hours = isset($request_data['module_hours']) ? intval($request_data['module_hours']) : 0;
    $module_nqf_level = isset($request_data['module_nqf_level']) ? intval($request_data['module_nqf_level']) : 0;

    $payload = array(
        'name' => $module_name,
    );
    $formats = array('%s');

    $code_column = in_array('module_code', $module_columns, true) ? 'module_code' : (in_array('code', $module_columns, true) ? 'code' : '');
    if ($code_column !== '') {
        $payload[$code_column] = $module_code;
        $formats[] = '%s';
    }

    if (in_array('type', $module_columns, true)) {
        $payload['type'] = $module_type ?: 'theory';
        $formats[] = '%s';
    }

    if (in_array('hours', $module_columns, true)) {
        $payload['hours'] = max(0, $module_hours);
        $formats[] = '%d';
    } elseif (in_array('duration_hours', $module_columns, true)) {
        $payload['duration_hours'] = max(0, $module_hours);
        $formats[] = '%d';
    }

    if (in_array('nqf_level', $module_columns, true)) {
        $payload['nqf_level'] = max(0, $module_nqf_level);
        $formats[] = '%d';
    }

    if (in_array('updated_at', $module_columns, true)) {
        $payload['updated_at'] = current_time('mysql');
        $formats[] = '%s';
    }

    if (empty($existing_module) && in_array('created_at', $module_columns, true)) {
        $payload['created_at'] = current_time('mysql');
        $formats[] = '%s';
    }

    return array($payload, $formats);
}

// ============================================================================
// MODULE MANAGEMENT PAGE DISPLAY
// ============================================================================
function nds_module_management_page()
{
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    global $wpdb;

    $faculties_table = $wpdb->prefix . 'nds_faculties';
    $courses_table = $wpdb->prefix . 'nds_courses';
    $modules_table = $wpdb->prefix . 'nds_modules';
    $programs_table = $wpdb->prefix . 'nds_programs';
    $staff_table = $wpdb->prefix . 'nds_staff';
    $module_lecturers_table = $wpdb->prefix . 'nds_module_lecturers';

    // Get selected course/qualification
    $selected_faculty_id = isset($_GET['faculty_id']) ? intval($_GET['faculty_id']) : 0;
    $selected_course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;
    $selected_program_id = isset($_GET['program_id']) ? intval($_GET['program_id']) : 0;

    $faculties = $wpdb->get_results("SELECT id, name FROM {$faculties_table} ORDER BY name", ARRAY_A);

    // Get all programs for dropdown
    if ($selected_faculty_id > 0) {
        $programs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, name, faculty_id FROM {$programs_table} WHERE faculty_id = %d ORDER BY name",
                $selected_faculty_id
            ),
            ARRAY_A
        );
    } else {
        $programs = $wpdb->get_results("SELECT id, name, faculty_id FROM {$programs_table} ORDER BY name", ARRAY_A);
    }

    // Get courses for dropdown. Respect faculty/program filters so admins can drill down.
    if ($selected_program_id > 0) {
        $courses = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT c.*, p.name as program_name, p.faculty_id, f.name AS faculty_name FROM {$courses_table} c 
                 LEFT JOIN {$programs_table} p ON c.program_id = p.id 
                 LEFT JOIN {$faculties_table} f ON f.id = p.faculty_id
                 WHERE c.program_id = %d 
                 ORDER BY c.name",
                $selected_program_id
            ),
            ARRAY_A
        );
    } elseif ($selected_faculty_id > 0) {
        $courses = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT c.*, p.name as program_name, p.faculty_id, f.name AS faculty_name FROM {$courses_table} c
                 LEFT JOIN {$programs_table} p ON c.program_id = p.id
                 LEFT JOIN {$faculties_table} f ON f.id = p.faculty_id
                 WHERE p.faculty_id = %d
                 ORDER BY c.name",
                $selected_faculty_id
            ),
            ARRAY_A
        );
    } else {
        $courses = $wpdb->get_results(
            "SELECT c.*, p.name as program_name, p.faculty_id, f.name AS faculty_name FROM {$courses_table} c
             LEFT JOIN {$programs_table} p ON c.program_id = p.id
             LEFT JOIN {$faculties_table} f ON f.id = p.faculty_id
             ORDER BY c.name",
            ARRAY_A
        );
    }

    $overview_where = array();
    $overview_params = array();
    if ($selected_faculty_id > 0) {
        $overview_where[] = 'p.faculty_id = %d';
        $overview_params[] = $selected_faculty_id;
    }
    if ($selected_program_id > 0) {
        $overview_where[] = 'c.program_id = %d';
        $overview_params[] = $selected_program_id;
    }
    if ($selected_course_id > 0) {
        $overview_where[] = 'c.id = %d';
        $overview_params[] = $selected_course_id;
    }

    $overview_sql = "SELECT c.id AS course_id, c.name AS course_name, c.code AS course_code, p.id AS program_id, p.name AS program_name,
                            f.id AS faculty_id, f.name AS faculty_name,
                            COUNT(m.id) AS module_count,
                            COALESCE(SUM(CASE WHEN m.id IS NOT NULL THEN 1 ELSE 0 END), 0) AS module_row_count
                     FROM {$courses_table} c
                     LEFT JOIN {$programs_table} p ON p.id = c.program_id
                     LEFT JOIN {$faculties_table} f ON f.id = p.faculty_id
                     LEFT JOIN {$modules_table} m ON m.course_id = c.id";
    if (!empty($overview_where)) {
        $overview_sql .= ' WHERE ' . implode(' AND ', $overview_where);
    }
    $overview_sql .= ' GROUP BY c.id, c.name, c.code, p.id, p.name, f.id, f.name ORDER BY f.name, p.name, c.name';
    $course_module_overview = empty($overview_params)
        ? $wpdb->get_results($overview_sql, ARRAY_A)
        : $wpdb->get_results($wpdb->prepare($overview_sql, $overview_params), ARRAY_A);

    $total_modules_for_filter = 0;
    $total_courses_for_filter = count($course_module_overview);
    foreach ($course_module_overview as $overview_row) {
        $total_modules_for_filter += intval($overview_row['module_count']);
    }

    // Get modules for selected course
    $modules = array();
    $course_name = '';
    if ($selected_course_id > 0) {
        $course = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT c.*, p.name as program_name, p.faculty_id FROM {$courses_table} c 
                 LEFT JOIN {$programs_table} p ON c.program_id = p.id 
                 WHERE c.id = %d",
                $selected_course_id
            ),
            ARRAY_A
        );
        
        if ($course) {
            $course_name = $course['name'];
            $selected_program_id = $course['program_id'];
            $selected_faculty_id = intval($course['faculty_id'] ?? 0);

            $modules = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT m.*, 
                            (SELECT COUNT(*) FROM {$module_lecturers_table} WHERE module_id = m.id) as lecturer_count
                     FROM {$modules_table} m 
                     WHERE m.course_id = %d 
                     ORDER BY m.name",
                    $selected_course_id
                ),
                ARRAY_A
            );

            // Get lecturer details for each module
            foreach ($modules as &$module) {
                $lecturers = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT s.id, s.first_name, s.last_name 
                         FROM {$staff_table} s 
                         INNER JOIN {$module_lecturers_table} ml ON s.id = ml.lecturer_id 
                         WHERE ml.module_id = %d 
                         ORDER BY s.first_name, s.last_name",
                        $module['id']
                    ),
                    ARRAY_A
                );
                $module['lecturers'] = $lecturers;
            }
        }
    }

    // Get all lecturers for assignment
    $all_lecturers = $wpdb->get_results(
        "SELECT id, first_name, last_name FROM {$staff_table} WHERE LOWER(role) = 'lecturer' ORDER BY first_name, last_name",
        ARRAY_A
    );

    foreach ($modules as &$module) {
        $module['display_code'] = $module['module_code'] ?? ($module['code'] ?? '');
        $module['display_hours'] = isset($module['hours']) ? intval($module['hours']) : intval($module['duration_hours'] ?? 0);
    }
    unset($module);

    // Display the page
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">
            <i class="fas fa-book-open"></i> Module Management
        </h1>
        <hr class="wp-header-end">

        <?php if (isset($_GET['module_created'])): ?>
            <div class="notice notice-success is-dismissible"><p>Module created successfully.</p></div>
        <?php endif; ?>

        <?php if (isset($_GET['module_updated'])): ?>
            <div class="notice notice-success is-dismissible"><p>Module updated successfully.</p></div>
        <?php endif; ?>

        <?php if (isset($_GET['module_deleted'])): ?>
            <div class="notice notice-success is-dismissible"><p>Module deleted successfully.</p></div>
        <?php endif; ?>

        <?php if (isset($_GET['module_assignments_updated'])): ?>
            <div class="notice notice-success is-dismissible"><p>Module lecturer assignments updated successfully.</p></div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="notice notice-error is-dismissible"><p><?php echo esc_html(ucwords(str_replace('_', ' ', sanitize_text_field(wp_unslash($_GET['error']))))); ?>.</p></div>
        <?php endif; ?>

        <!-- Filters Section -->
        <div style="background: #fff; padding: 20px; border-radius: 5px; margin-bottom: 20px; box-shadow: 0 1px 1px rgba(0,0,0,0.04);">
            <form method="get" id="nds-module-filter-form" style="display: flex; gap: 15px; align-items: flex-end;">
                <input type="hidden" name="page" value="nds-module-management">

                <div>
                    <label for="faculty_id"><strong>Select Faculty:</strong></label>
                    <select name="faculty_id" id="faculty_id" style="width: 220px; padding: 8px; border: 1px solid #ddd; border-radius: 3px;">
                        <option value="">-- All Faculties --</option>
                        <?php foreach ($faculties as $faculty): ?>
                            <option value="<?php echo intval($faculty['id']); ?>" <?php selected($selected_faculty_id, $faculty['id']); ?>>
                                <?php echo esc_html($faculty['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Program Filter -->
                <div>
                    <label for="program_id"><strong>Select Qualification Program:</strong></label>
                    <select name="program_id" id="program_id" style="width: 200px; padding: 8px; border: 1px solid #ddd; border-radius: 3px;">
                        <option value="">-- All Programs --</option>
                        <?php foreach ($programs as $prog): ?>
                            <option value="<?php echo $prog['id']; ?>" <?php selected($selected_program_id, $prog['id']); ?>>
                                <?php echo esc_html($prog['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Course Filter -->
                <div>
                    <label for="course_id"><strong>Select Qualification:</strong></label>
                    <select name="course_id" id="course_id" style="width: 250px; padding: 8px; border: 1px solid #ddd; border-radius: 3px;">
                        <option value="">-- Select a Qualification --</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?php echo $course['id']; ?>" <?php selected($selected_course_id, $course['id']); ?>>
                                <?php echo esc_html($course['name']); ?> (<?php echo esc_html($course['code'] ?? ''); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="button button-primary">Filter</button>
            </form>
        </div>

        <div style="display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; margin-bottom: 20px;">
            <div style="background: #fff; padding: 16px 20px; border-radius: 5px; box-shadow: 0 1px 1px rgba(0,0,0,0.04);">
                <div style="font-size: 12px; color: #646970; text-transform: uppercase; letter-spacing: 0.04em;">Total Modules</div>
                <div style="font-size: 28px; font-weight: 600; line-height: 1.2;"><?php echo intval($total_modules_for_filter); ?></div>
            </div>
            <div style="background: #fff; padding: 16px 20px; border-radius: 5px; box-shadow: 0 1px 1px rgba(0,0,0,0.04);">
                <div style="font-size: 12px; color: #646970; text-transform: uppercase; letter-spacing: 0.04em;">Qualifications In View</div>
                <div style="font-size: 28px; font-weight: 600; line-height: 1.2;"><?php echo intval($total_courses_for_filter); ?></div>
            </div>
            <div style="background: #fff; padding: 16px 20px; border-radius: 5px; box-shadow: 0 1px 1px rgba(0,0,0,0.04);">
                <div style="font-size: 12px; color: #646970; text-transform: uppercase; letter-spacing: 0.04em;">Selected Qualification</div>
                <div style="font-size: 18px; font-weight: 600; line-height: 1.3;"><?php echo $selected_course_id > 0 ? esc_html($course_name) : 'All Qualifications'; ?></div>
            </div>
        </div>

        <div style="background: #fff; padding: 20px; border-radius: 5px; margin-bottom: 20px; box-shadow: 0 1px 1px rgba(0,0,0,0.04);">
            <h2 style="margin-top: 0;">Module Overview By Qualification</h2>
            <p style="color: #666; margin-bottom: 16px;">See how many modules each qualification currently has under the selected filters.</p>

            <?php if (!empty($course_module_overview)): ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>Faculty</th>
                            <th>Program</th>
                            <th>Qualification</th>
                            <th>Module Count</th>
                            <th>Open</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($course_module_overview as $overview_row): ?>
                            <tr>
                                <td><?php echo esc_html($overview_row['faculty_name'] ?: 'Unassigned'); ?></td>
                                <td><?php echo esc_html($overview_row['program_name'] ?: 'Unassigned'); ?></td>
                                <td>
                                    <strong><?php echo esc_html($overview_row['course_name']); ?></strong>
                                    <?php if (!empty($overview_row['course_code'])): ?>
                                        <br><small style="color: #666;"><?php echo esc_html($overview_row['course_code']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo intval($overview_row['module_count']); ?></td>
                                <td>
                                    <a class="button button-small" href="<?php echo esc_url(nds_get_module_management_redirect_url(intval($overview_row['faculty_id']), intval($overview_row['program_id']), array('course_id' => intval($overview_row['course_id'])))); ?>">Manage</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="margin: 0; color: #666;">No qualifications found for the current filters.</p>
            <?php endif; ?>
        </div>

        <?php if ($selected_course_id > 0): ?>
            <!-- Modules Section -->
            <div style="background: #fff; padding: 20px; border-radius: 5px; box-shadow: 0 1px 1px rgba(0,0,0,0.04);">
                <h2><?php echo esc_html($course_name); ?> - Modules</h2>
                <p style="color: #666; margin-bottom: 20px;">
                    Manage modules for this qualification and assign lecturers to teach specific modules.
                </p>

                <div style="margin-bottom: 24px; padding: 16px; border: 1px solid #dcdcde; border-radius: 4px; background: #f6f7f7;">
                    <h3 style="margin-top: 0; margin-bottom: 12px;">Add Module</h3>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 12px; align-items: end;">
                        <input type="hidden" name="action" value="nds_create_module_from_management">
                        <input type="hidden" name="course_id" value="<?php echo intval($selected_course_id); ?>">
                        <input type="hidden" name="program_id" value="<?php echo intval($selected_program_id); ?>">
                        <?php wp_nonce_field('nds_manage_module_action', 'nds_manage_module_nonce'); ?>

                        <div>
                            <label for="nds-new-module-code"><strong>Code</strong></label>
                            <input id="nds-new-module-code" type="text" name="module_code" class="regular-text" style="width: 100%;" placeholder="MOD101" required>
                        </div>

                        <div>
                            <label for="nds-new-module-name"><strong>Module Name</strong></label>
                            <input id="nds-new-module-name" type="text" name="module_name" class="regular-text" style="width: 100%;" placeholder="Introduction to..." required>
                        </div>

                        <div>
                            <label for="nds-new-module-type"><strong>Type</strong></label>
                            <select id="nds-new-module-type" name="module_type" style="width: 100%;">
                                <option value="theory">Theory</option>
                                <option value="practical">Practical</option>
                                <option value="workplace">Workplace</option>
                                <option value="assessment">Assessment</option>
                            </select>
                        </div>

                        <div>
                            <label for="nds-new-module-hours"><strong>Hours</strong></label>
                            <input id="nds-new-module-hours" type="number" name="module_hours" min="0" step="1" style="width: 100%;" required>
                        </div>

                        <div>
                            <label for="nds-new-module-level"><strong>NQF Level</strong></label>
                            <input id="nds-new-module-level" type="number" name="module_nqf_level" min="0" step="1" style="width: 100%;">
                        </div>

                        <div style="grid-column: 1 / -1; display: flex; justify-content: flex-end;">
                            <button type="submit" class="button button-primary">Add Module</button>
                        </div>
                    </form>
                </div>

                <?php if (!empty($modules)): ?>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th style="width: 10%;">Module Code</th>
                                <th style="width: 30%;">Module Name</th>
                                <th style="width: 15%;">Type</th>
                                <th style="width: 12%;">Hours</th>
                                <th style="width: 20%;">Assigned Lecturers</th>
                                <th style="width: 18%;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($modules as $module): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html($module['display_code']); ?></strong>
                                    </td>
                                    <td>
                                        <strong><?php echo esc_html($module['name']); ?></strong>
                                        <?php if (!empty($module['nqf_level'])): ?>
                                            <br><small style="color: #999;">NQF Level: <?php echo intval($module['nqf_level']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge" style="display: inline-block; padding: 3px 8px; background: #e3f2fd; color: #1976d2; border-radius: 3px; font-size: 11px;">
                                            <?php echo esc_html(ucfirst($module['type'] ?? 'theory')); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo intval($module['display_hours']); ?> hrs
                                    </td>
                                    <td>
                                        <?php if (!empty($module['lecturers'])): ?>
                                            <div style="font-size: 13px;">
                                                <?php foreach ($module['lecturers'] as $lecturer): ?>
                                                    <div style="padding: 2px 0;">
                                                        • <?php echo esc_html($lecturer['first_name'] . ' ' . $lecturer['last_name']); ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <em style="color: #999;">No lecturers assigned</em>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="display: flex; flex-wrap: wrap; gap: 6px;">
                                            <button type="button" class="button button-small" onclick="ndsOpenEditModuleModal(<?php echo intval($module['id']); ?>, <?php echo wp_json_encode($module['display_code']); ?>, <?php echo wp_json_encode($module['name']); ?>, <?php echo wp_json_encode($module['type'] ?? 'theory'); ?>, <?php echo intval($module['display_hours']); ?>, <?php echo intval($module['nqf_level'] ?? 0); ?>)">
                                                <i class="fas fa-pen"></i> Edit
                                            </button>
                                            <button type="button" class="button button-small" onclick="ndsOpenModuleLecturerModal(<?php echo intval($module['id']); ?>, <?php echo wp_json_encode($module['name']); ?>, <?php echo wp_json_encode($module['lecturers'] ?? array()); ?>)">
                                                <i class="fas fa-user-plus"></i> Lecturers
                                            </button>
                                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Delete this module? This will also remove module lecturer assignments.');" style="margin: 0;">
                                                <input type="hidden" name="action" value="nds_delete_module_from_management">
                                                <input type="hidden" name="module_id" value="<?php echo intval($module['id']); ?>">
                                                <input type="hidden" name="course_id" value="<?php echo intval($selected_course_id); ?>">
                                                <input type="hidden" name="program_id" value="<?php echo intval($selected_program_id); ?>">
                                                <?php wp_nonce_field('nds_manage_module_action', 'nds_manage_module_nonce'); ?>
                                                <button type="submit" class="button button-small">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div style="padding: 20px; background: #f5f5f5; border-left: 4px solid #ffb900; border-radius: 3px;">
                        <p style="margin: 0; color: #666;">
                            <i class="fas fa-info-circle"></i> No modules found for this qualification. Modules must be added during qualification creation.
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- No Course Selected -->
            <div style="padding: 20px; background: #f5f5f5; border-left: 4px solid #0073aa; border-radius: 3px;">
                <p style="margin: 0; color: #666;">
                    <i class="fas fa-arrow-right"></i> Please select a qualification from the filters above to view its modules.
                </p>
            </div>
        <?php endif; ?>

        <!-- Modal for Editing Module -->
        <div id="nds-edit-module-modal" style="display: none; position: fixed; z-index: 9998; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4);">
            <div style="background-color: #fefefe; margin: 8% auto; padding: 20px; border: 1px solid #888; border-radius: 5px; width: 520px; max-width: calc(100% - 40px);">
                <span style="color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; line-height: 20px;" onclick="ndsCloseEditModuleModal()">&times;</span>
                <h2 style="margin-top: 0;">Edit Module</h2>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="nds_update_module_from_management">
                    <input type="hidden" name="module_id" id="nds-edit-module-id">
                    <input type="hidden" name="course_id" value="<?php echo intval($selected_course_id); ?>">
                    <input type="hidden" name="program_id" value="<?php echo intval($selected_program_id); ?>">
                    <?php wp_nonce_field('nds_manage_module_action', 'nds_manage_module_nonce'); ?>

                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row"><label for="nds-edit-module-code">Code</label></th>
                                <td><input id="nds-edit-module-code" type="text" name="module_code" class="regular-text" required></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="nds-edit-module-name">Module Name</label></th>
                                <td><input id="nds-edit-module-name" type="text" name="module_name" class="regular-text" required></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="nds-edit-module-type">Type</label></th>
                                <td>
                                    <select id="nds-edit-module-type" name="module_type">
                                        <option value="theory">Theory</option>
                                        <option value="practical">Practical</option>
                                        <option value="workplace">Workplace</option>
                                        <option value="assessment">Assessment</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="nds-edit-module-hours">Hours</label></th>
                                <td><input id="nds-edit-module-hours" type="number" name="module_hours" min="0" step="1" required></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="nds-edit-module-level">NQF Level</label></th>
                                <td><input id="nds-edit-module-level" type="number" name="module_nqf_level" min="0" step="1"></td>
                            </tr>
                        </tbody>
                    </table>

                    <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 16px;">
                        <button type="button" class="button" onclick="ndsCloseEditModuleModal()">Cancel</button>
                        <button type="submit" class="button button-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal for Assigning Lecturers to Module -->
        <div id="nds-module-lecturer-modal" style="display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4);">
            <div style="background-color: #fefefe; margin: 10% auto; padding: 20px; border: 1px solid #888; border-radius: 5px; width: 500px; max-height: 80vh; overflow-y: auto;">
                <span style="color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; line-height: 20px;" onclick="ndsCloseModuleLecturerModal()">&times;</span>
                <h2 id="modal-module-name" style="margin-top: 0;"></h2>

                <form id="nds-module-lecturer-form" method="post" action="">
                    <input type="hidden" name="action" value="nds_assign_module_lecturers">
                    <input type="hidden" name="module_id" id="modal-module-id">
                    <input type="hidden" name="course_id" value="<?php echo $selected_course_id; ?>">
                    <input type="hidden" name="program_id" value="<?php echo $selected_program_id; ?>">
                    <?php wp_nonce_field('nds_assign_module_lecturers'); ?>

                    <p><strong>Select lecturers to teach this module:</strong></p>
                    <div id="lecturers-list" style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; border-radius: 3px;">
                        <?php foreach ($all_lecturers as $lecturer): ?>
                            <label style="display: block; margin-bottom: 8px;">
                                <input type="checkbox" name="lecturer_ids[]" value="<?php echo $lecturer['id']; ?>" class="module-lecturer-checkbox">
                                <?php echo esc_html($lecturer['first_name'] . ' ' . $lecturer['last_name']); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                        <button type="button" class="button" onclick="ndsCloseModuleLecturerModal()">Cancel</button>
                        <button type="submit" class="button button-primary">Assign Lecturers</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    // Auto-refresh filters so modules load immediately when a selection changes.
    const ndsFilterForm = document.getElementById('nds-module-filter-form');
    const ndsFacultySelect = document.getElementById('faculty_id');
    const ndsProgramSelect = document.getElementById('program_id');
    const ndsCourseSelect = document.getElementById('course_id');

    if (ndsFacultySelect && ndsFilterForm) {
        ndsFacultySelect.addEventListener('change', function() {
            if (ndsProgramSelect) {
                ndsProgramSelect.value = '';
            }
            if (ndsCourseSelect) {
                ndsCourseSelect.value = '';
            }
            ndsFilterForm.submit();
        });
    }

    if (ndsProgramSelect && ndsFilterForm) {
        ndsProgramSelect.addEventListener('change', function() {
            if (ndsCourseSelect) {
                ndsCourseSelect.value = '';
            }
            ndsFilterForm.submit();
        });
    }

    if (ndsCourseSelect && ndsFilterForm) {
        ndsCourseSelect.addEventListener('change', function() {
            ndsFilterForm.submit();
        });
    }

    function ndsOpenModuleLecturerModal(moduleId, moduleName, lecturersJson) {
        // Set module info
        document.getElementById('modal-module-id').value = moduleId;
        document.getElementById('modal-module-name').textContent = 'Assign Lecturers to: ' + moduleName;

        // Parse and check currently assigned lecturers
        let currentLecturers = [];
        if (Array.isArray(lecturersJson)) {
            currentLecturers = lecturersJson;
        } else {
            try {
                currentLecturers = JSON.parse(lecturersJson);
            } catch (e) {
                currentLecturers = [];
            }
        }

        // Uncheck all checkboxes
        document.querySelectorAll('.module-lecturer-checkbox').forEach(checkbox => {
            checkbox.checked = false;
        });

        // Check boxes for currently assigned lecturers
        currentLecturers.forEach(lecturer => {
            const checkbox = document.querySelector('.module-lecturer-checkbox[value="' + lecturer.id + '"]');
            if (checkbox) {
                checkbox.checked = true;
            }
        });

        // Show modal
        document.getElementById('nds-module-lecturer-modal').style.display = 'block';
    }

    function ndsOpenEditModuleModal(moduleId, moduleCode, moduleName, moduleType, moduleHours, moduleLevel) {
        document.getElementById('nds-edit-module-id').value = moduleId;
        document.getElementById('nds-edit-module-code').value = moduleCode || '';
        document.getElementById('nds-edit-module-name').value = moduleName || '';
        document.getElementById('nds-edit-module-type').value = moduleType || 'theory';
        document.getElementById('nds-edit-module-hours').value = moduleHours || 0;
        document.getElementById('nds-edit-module-level').value = moduleLevel || '';
        document.getElementById('nds-edit-module-modal').style.display = 'block';
    }

    function ndsCloseEditModuleModal() {
        document.getElementById('nds-edit-module-modal').style.display = 'none';
    }

    function ndsCloseModuleLecturerModal() {
        document.getElementById('nds-module-lecturer-modal').style.display = 'none';
    }

    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
        const modal = document.getElementById('nds-module-lecturer-modal');
        const editModal = document.getElementById('nds-edit-module-modal');
        if (event.target === modal) {
            modal.style.display = 'none';
        }
        if (event.target === editModal) {
            editModal.style.display = 'none';
        }
    });

    // Handle form submission
    document.getElementById('nds-module-lecturer-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const nextUrl = new URL(window.location.href);
                nextUrl.searchParams.set('module_assignments_updated', '1');
                window.location.href = nextUrl.toString();
            } else {
                alert('Error: ' + (data.data ? data.data : 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error assigning lecturers');
        });
    });
    </script>

    <style>
        .badge {
            display: inline-block;
            padding: 3px 8px;
            background: #e3f2fd;
            color: #1976d2;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 500;
        }
    </style>
    <?php
}

// ============================================================================
// AJAX HANDLER FOR ASSIGNING LECTURERS TO MODULES
// ============================================================================
add_action('wp_ajax_nds_assign_module_lecturers', 'nds_handle_assign_module_lecturers');
function nds_handle_assign_module_lecturers()
{
    check_ajax_referer('nds_assign_module_lecturers');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    global $wpdb;

    $module_id = isset($_POST['module_id']) ? intval($_POST['module_id']) : 0;
    $lecturer_ids = isset($_POST['lecturer_ids']) ? array_map('intval', (array)$_POST['lecturer_ids']) : array();

    if ($module_id <= 0) {
        wp_send_json_error('Invalid module ID');
    }

    $module_lecturers_table = $wpdb->prefix . 'nds_module_lecturers';

    // Start transaction
    $wpdb->query('START TRANSACTION');

    try {
        // Remove all existing lecturer assignments for this module
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$module_lecturers_table} WHERE module_id = %d",
            $module_id
        ));

        // Add new assignments
        foreach ($lecturer_ids as $lecturer_id) {
            $wpdb->insert(
                $module_lecturers_table,
                array(
                    'module_id' => $module_id,
                    'lecturer_id' => $lecturer_id
                ),
                array('%d', '%d')
            );

            if ($wpdb->last_error) {
                throw new Exception($wpdb->last_error);
            }
        }

        // Commit transaction
        $wpdb->query('COMMIT');

        wp_send_json_success('Module lecturers updated successfully');
    } catch (Exception $e) {
        // Rollback on error
        $wpdb->query('ROLLBACK');
        wp_send_json_error('Error updating module lecturers: ' . $e->getMessage());
    }
}

add_action('admin_post_nds_create_module_from_management', 'nds_create_module_from_management');
function nds_create_module_from_management()
{
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    if (!isset($_POST['nds_manage_module_nonce']) || !wp_verify_nonce($_POST['nds_manage_module_nonce'], 'nds_manage_module_action')) {
        wp_die('Security check failed');
    }

    global $wpdb;

    $modules_table = $wpdb->prefix . 'nds_modules';
    $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
    $program_id = isset($_POST['program_id']) ? intval($_POST['program_id']) : 0;
    $redirect_url = nds_get_module_management_redirect_url($program_id, $course_id);

    if ($course_id <= 0) {
        wp_redirect(nds_get_module_management_redirect_url($program_id, 0, array('error' => 'invalid_course')));
        exit;
    }

    $module_columns = nds_get_module_management_columns($modules_table);
    list($payload, $formats) = nds_build_module_management_payload($_POST, $module_columns);

    if (empty($payload['name'])) {
        wp_redirect(add_query_arg('error', 'missing_module_name', $redirect_url));
        exit;
    }

    $payload = array_merge(array('course_id' => $course_id), $payload);
    $formats = array_merge(array('%d'), $formats);

    $result = $wpdb->insert($modules_table, $payload, $formats);
    if ($result === false) {
        wp_redirect(add_query_arg('error', 'module_create_failed', $redirect_url));
        exit;
    }

    wp_redirect(add_query_arg('module_created', '1', $redirect_url));
    exit;
}

add_action('admin_post_nds_update_module_from_management', 'nds_update_module_from_management');
function nds_update_module_from_management()
{
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    if (!isset($_POST['nds_manage_module_nonce']) || !wp_verify_nonce($_POST['nds_manage_module_nonce'], 'nds_manage_module_action')) {
        wp_die('Security check failed');
    }

    global $wpdb;

    $modules_table = $wpdb->prefix . 'nds_modules';
    $module_id = isset($_POST['module_id']) ? intval($_POST['module_id']) : 0;
    $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
    $program_id = isset($_POST['program_id']) ? intval($_POST['program_id']) : 0;
    $redirect_url = nds_get_module_management_redirect_url($program_id, $course_id);

    if ($module_id <= 0 || $course_id <= 0) {
        wp_redirect(add_query_arg('error', 'invalid_module', $redirect_url));
        exit;
    }

    $existing_module = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM {$modules_table} WHERE id = %d AND course_id = %d LIMIT 1", $module_id, $course_id),
        ARRAY_A
    );

    if (!$existing_module) {
        wp_redirect(add_query_arg('error', 'module_not_found', $redirect_url));
        exit;
    }

    $module_columns = nds_get_module_management_columns($modules_table);
    list($payload, $formats) = nds_build_module_management_payload($_POST, $module_columns, $existing_module);

    if (empty($payload['name'])) {
        wp_redirect(add_query_arg('error', 'missing_module_name', $redirect_url));
        exit;
    }

    $result = $wpdb->update($modules_table, $payload, array('id' => $module_id), $formats, array('%d'));
    if ($result === false) {
        wp_redirect(add_query_arg('error', 'module_update_failed', $redirect_url));
        exit;
    }

    wp_redirect(add_query_arg('module_updated', '1', $redirect_url));
    exit;
}

add_action('admin_post_nds_delete_module_from_management', 'nds_delete_module_from_management');
function nds_delete_module_from_management()
{
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    if (!isset($_POST['nds_manage_module_nonce']) || !wp_verify_nonce($_POST['nds_manage_module_nonce'], 'nds_manage_module_action')) {
        wp_die('Security check failed');
    }

    global $wpdb;

    $modules_table = $wpdb->prefix . 'nds_modules';
    $module_lecturers_table = $wpdb->prefix . 'nds_module_lecturers';
    $module_id = isset($_POST['module_id']) ? intval($_POST['module_id']) : 0;
    $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
    $program_id = isset($_POST['program_id']) ? intval($_POST['program_id']) : 0;
    $redirect_url = nds_get_module_management_redirect_url($program_id, $course_id);

    if ($module_id <= 0 || $course_id <= 0) {
        wp_redirect(add_query_arg('error', 'invalid_module', $redirect_url));
        exit;
    }

    $existing_module = $wpdb->get_var(
        $wpdb->prepare("SELECT id FROM {$modules_table} WHERE id = %d AND course_id = %d LIMIT 1", $module_id, $course_id)
    );

    if (!$existing_module) {
        wp_redirect(add_query_arg('error', 'module_not_found', $redirect_url));
        exit;
    }

    $wpdb->delete($module_lecturers_table, array('module_id' => $module_id), array('%d'));
    $result = $wpdb->delete($modules_table, array('id' => $module_id), array('%d'));

    if ($result === false) {
        wp_redirect(add_query_arg('error', 'module_delete_failed', $redirect_url));
        exit;
    }

    wp_redirect(add_query_arg('module_deleted', '1', $redirect_url));
    exit;
}
