<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

global $wpdb;
$staff_table = $wpdb->prefix . "nds_staff"; // Updated table name

function nds_ensure_staff_academic_columns() {
    global $wpdb;
    $staff_table = $wpdb->prefix . 'nds_staff';

    $required_columns = [
        'faculty_id' => 'INT NULL',
        'program_id' => 'INT NULL',
        'course_id' => 'INT NULL',
    ];

    foreach ($required_columns as $column_name => $column_def) {
        $exists = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$staff_table} LIKE %s", $column_name));
        if (empty($exists)) {
            $wpdb->query("ALTER TABLE {$staff_table} ADD COLUMN {$column_name} {$column_def}");
        }
    }
}

function nds_staff_data_formats(array $data) {
    $format_map = [
        'user_id' => '%d',
        'first_name' => '%s',
        'last_name' => '%s',
        'profile_picture' => '%s',
        'email' => '%s',
        'phone' => '%s',
        'role' => '%s',
        'address' => '%s',
        'dob' => '%s',
        'gender' => '%s',
        'faculty_id' => '%d',
        'program_id' => '%d',
        'course_id' => '%d',
        'created_at' => '%s',
    ];

    $formats = [];
    foreach (array_keys($data) as $key) {
        $formats[] = isset($format_map[$key]) ? $format_map[$key] : '%s';
    }

    return $formats;
}

// ✅ Handle the repetitive logic for fetching staff data
function nds_handle_staff_data($request_type = 'POST')
{
    $request_data = ($request_type === 'POST') ? $_POST : $_GET;
    $current_user_id = get_current_user_id();

    return [
        'user_id'  => $current_user_id,
        'first_name'  => isset($request_data['first_name']) ? sanitize_text_field($request_data['first_name']) : '',
        'last_name'   => isset($request_data['last_name']) ? sanitize_text_field($request_data['last_name']) : '',
        'profile_picture'   => isset($request_data['profile_picture']) ? sanitize_text_field($request_data['profile_picture']) : '',
        'email'       => isset($request_data['email']) ? sanitize_email($request_data['email']) : '',
        'phone'       => isset($request_data['phone']) ? sanitize_text_field($request_data['phone']) : '',
        'role'        => isset($request_data['role']) ? sanitize_text_field($request_data['role']) : '',
        'address'        => isset($request_data['address']) ? sanitize_text_field($request_data['address']) : '',
        'dob'        => isset($request_data['dob']) ? sanitize_text_field($request_data['dob']) : '',
        'gender'        => isset($request_data['gender']) ? sanitize_text_field($request_data['gender']) : '',
        'faculty_id' => isset($request_data['faculty_id']) ? intval($request_data['faculty_id']) : 0,
        'program_id' => isset($request_data['program_id']) ? intval($request_data['program_id']) : 0,
        'course_id' => isset($request_data['course_id']) ? intval($request_data['course_id']) : 0,
        'created_at'  => current_time('mysql'),
    ];
}

// ✅ Check if staff exists (by email)
function nds_staff_exists($email)
{
    global $wpdb;
    $staff_table = $wpdb->prefix . "nds_staff";

    $query = $wpdb->prepare("SELECT COUNT(*) FROM $staff_table WHERE email = %s", $email);
    return ($wpdb->get_var($query) > 0);
}

// ✅ Get all the staff
function nds_get_all_staff_table()
{
    global $wpdb;
    $staff_table = $wpdb->prefix . "nds_staff";

    // Get all staff data
    $staff_members = $wpdb->get_results("SELECT * FROM $staff_table", ARRAY_A);

    // Check if there are any staff members
    if (!empty($staff_members)) {
        echo '<table class="wp-list-table widefat nds-table striped posts">';

        // Table header row (adjust this as per your staff fields)
        echo '<thead>';
        echo '<tr>';
        echo '<th scope="col" class="manage-column">ID</th>';
        echo '<th scope="col" class="manage-column">Name</th>';
        echo '<th scope="col" class="manage-column">Email</th>';
        echo '<th scope="col" class="manage-column">Position</th>';  // Add more columns if needed
        echo '<th scope="col" class="manage-column">Actions</th>';
        echo '</tr>';
        echo '</thead>';

        // Table body
        echo '<tbody>';
        foreach ($staff_members as $staff) {
            echo '<tr>';
            echo '<td>' . esc_html($staff['id']) . '</td>';
            echo '<td>' . esc_html($staff['first_name']) . '</td>';
            echo '<td>' . esc_html($staff['email']) . '</td>';
            echo '<td>' . esc_html($staff['role']) . '</td>';  // Assuming 'position' field

            // Edit and Delete buttons
            $edit_url = admin_url('admin.php?page=nds-edit-staff&action=edit&staff_id=' . $staff['id']);

            echo '<td>';
            echo '<a href="' . esc_url($edit_url) . '" class="button">Edit</a>';
            echo ' | ';
            echo '<form method="POST" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline" onsubmit="return confirm(\'Are you sure?\');">';
            echo '<input type="hidden" name="action" value="nds_delete_staff">';
            echo '<input type="hidden" name="staff_id" value="' . intval($staff['id']) . '">';
            echo wp_nonce_field('nds_delete_staff', '_wpnonce', true, false);
            echo '<button type="submit" class="button button-link-delete" style="color:red">Delete</button>';
            echo '</form>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody>';

        // Close the table
        echo '</table>';
    } else {
        echo 'No staff members found.';
    }
}

// ✅ Get all the staff
function nds_get_all_staff_cards()
{
    global $wpdb;
    $staff_table = $wpdb->prefix . "nds_staff";

    // Get all staff data
    $staff_members = $wpdb->get_results("SELECT * FROM $staff_table", ARRAY_A);

    // Check if there are any staff members
    if (!empty($staff_members)) {
        echo '<div class="grid grid-cols-4 gap-4">';

        foreach ($staff_members as $staff) {
            echo '<div class="staff-member p-4 border bg-white">';

            // Profile Picture Upload
            echo '<div class="w-36 h-36 overflow-hidden rounded-full border mx-auto">';
            echo '<input type="hidden" name="profile_picture" class="profile_picture" value="' . esc_attr($staff['profile_picture']) . '">';
            echo '<img class="profile_picture_preview w-full h-full object-cover" src="' . esc_url($staff['profile_picture'] ? wp_get_attachment_url($staff['profile_picture']) : '') . '" 
                  style="max-width: 150px; display: ' . ($staff['profile_picture'] ? 'block' : 'none') . ';">';
            echo '</div>';

            // Staff Details
            echo '<div class="mt-4 space-y-2 text-center">';
            echo '<div><h3 class="text-lg font-bold">' . esc_html($staff['first_name']) . ' ' . esc_html($staff['last_name']) . '</h3></div>';
            echo '<div>' . esc_html($staff['role']) . '</div>';
            // Edit & Delete Links
            $edit_url = admin_url('admin.php?page=nds-edit-staff&action=edit&staff_id=' . $staff['id']);

            echo '<div>';
            echo '<a href="' . esc_url($edit_url) . '" class="button">Edit</a>';
            echo ' | ';
            echo '<form method="POST" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline" onsubmit="return confirm(\'Are you sure?\');">';
            echo '<input type="hidden" name="action" value="nds_delete_staff">';
            echo '<input type="hidden" name="staff_id" value="' . intval($staff['id']) . '">';
            echo wp_nonce_field('nds_delete_staff', '_wpnonce', true, false);
            echo '<button type="submit" class="button button-link-delete" style="color:red">Delete</button>';
            echo '</form>';
            echo '</div>';
            echo '</div>';
            echo '</div>'; // Close staff item container
        }

        echo '</div>'; // Close grid

    } else {
        echo 'No staff members found.';
    }
}
// ✅ Add Staff
add_action('admin_post_nds_add_staff', 'nds_add_staff');
function nds_add_staff()
{
    if (!isset($_POST['nds_add_staff_nonce']) || !wp_verify_nonce($_POST['nds_add_staff_nonce'], 'nds_add_staff_action')) {
        wp_die('Security check failed');
    }
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    global $wpdb;
    $staff_table = $wpdb->prefix . "nds_staff";
    nds_ensure_staff_academic_columns();
    $data = nds_handle_staff_data('POST');
    $is_lecturer = strtolower(trim((string) $data['role'])) === 'lecturer';

    if (empty($data['first_name']) || empty($data['last_name']) || empty($data['email']) || empty($data['role'])) {
        wp_die('Required fields are missing.');
    }

    if ($is_lecturer && (empty($data['faculty_id']) || empty($data['program_id']) || empty($data['course_id']))) {
        wp_die('Faculty, program, and qualification are required when adding a lecturer.');
    }

    // Check if email is already used in wp_users
    if (email_exists($data['email']) || username_exists($data['email'])) {
        wp_die('A user with this email already exists.');
    }

    $default_password = $is_lecturer ? ('Nds@' . date('Y')) : wp_generate_password(16, true, true);

    // Create a new WordPress user
    $user_id = wp_insert_user([
        'user_login' => $data['email'],  // Use email as username
        'user_pass'  => $default_password,
        'user_email' => $data['email'],
        'first_name' => $data['first_name'],
        'last_name'  => $data['last_name'],
        'role'       => 'subscriber',  // Change role if needed
    ]);

    if (is_wp_error($user_id)) {
        $error = urlencode('failed_to_create_user');
        wp_redirect(admin_url('admin.php?page=nds-add-staff&error=' . $error));
        exit;
    }

    // Add user ID to the staff data
    $data['user_id'] = $user_id;

    $staff_insert_data = [
        'user_id' => $data['user_id'],
        'first_name' => $data['first_name'],
        'last_name' => $data['last_name'],
        'profile_picture' => $data['profile_picture'],
        'email' => $data['email'],
        'phone' => $data['phone'],
        'role' => $data['role'],
        'address' => $data['address'],
        'dob' => $data['dob'],
        'gender' => $data['gender'],
        'faculty_id' => $data['faculty_id'] > 0 ? $data['faculty_id'] : null,
        'program_id' => $data['program_id'] > 0 ? $data['program_id'] : null,
        'course_id' => $data['course_id'] > 0 ? $data['course_id'] : null,
        'created_at' => $data['created_at'],
    ];

    // Insert into nds_staff table
    $staffMember = $wpdb->insert($staff_table, $staff_insert_data, nds_staff_data_formats($staff_insert_data));

    if (!$staffMember) {
        $error = urlencode('failed_to_add_staff');
        wp_redirect(admin_url('admin.php?page=nds-add-staff&error=' . $error));
        exit;
    }

    if ($is_lecturer && !empty($data['course_id'])) {
        $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$wpdb->prefix}nds_course_lecturers (course_id, lecturer_id) VALUES (%d, %d)",
            intval($data['course_id']),
            intval($wpdb->insert_id)
        ));
    }

    // Redirect after successful insertion
    // Log create action
    nds_log_staff_action($wpdb->insert_id, get_current_user_id(), 'create_staff', 'create', null, wp_json_encode($staff_insert_data));

    wp_redirect(admin_url('admin.php?page=nds-staff-management&success=staff_created'));
    exit;
}

// ✅ Get Staff by ID
function nds_get_staff_by_id($id)
{
    global $wpdb;
    $staff_table = $wpdb->prefix . "nds_staff";

    return $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM $staff_table WHERE id = %d", intval($id)),
        ARRAY_A
    );
}

// ✅ Update Staff
add_action('admin_post_nds_update_staff', 'nds_handle_update_staff');
function nds_handle_update_staff()
{
    if (!isset($_POST['nds_edit_staff_nonce']) || !wp_verify_nonce($_POST['nds_edit_staff_nonce'], 'nds_edit_staff_action')) {
        wp_die('Security check failed');
    }
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    global $wpdb;
    $staff_table = $wpdb->prefix . "nds_staff";
    nds_ensure_staff_academic_columns();
    $staff_id = intval($_POST['staff_id']);
    $data = nds_handle_staff_data('POST');
    unset($data['user_id'], $data['created_at']);

    if (empty($staff_id)) {
        wp_die('Invalid staff ID.');
    }

    // Get old values for audit trail
    $old = nds_get_staff_by_id($staff_id);
    $updated = $wpdb->update($staff_table, $data, ['id' => $staff_id], nds_staff_data_formats($data), ['%d']);
    if ($updated === false) {
        wp_redirect(admin_url('admin.php?page=nds-edit-staff&staff_id=' . $staff_id . '&error=' . urlencode('update_failed')));
        exit;
    }

    // Log update action
    nds_log_staff_action($staff_id, get_current_user_id(), 'update_staff', 'update', wp_json_encode($old), wp_json_encode($data));

    wp_redirect(admin_url('admin.php?page=nds-staff-management&success=staff_updated'));
    exit;
}

// ✅ Delete Staff
function nds_delete_staff($staff_id)
{
    global $wpdb;
    $staff_table = $wpdb->prefix . 'nds_staff';
    $redirect_url = admin_url('admin.php?page=nds-staff-management');

    if (intval($staff_id) > 0) {
        $old = nds_get_staff_by_id($staff_id);
        $deleted = $wpdb->delete($staff_table, ['id' => intval($staff_id)], ['%d']);
        if ($deleted === false) {
            wp_redirect(add_query_arg('error', urlencode('delete_failed'), $redirect_url));
            exit;
        }
        // Log delete action
        nds_log_staff_action($staff_id, get_current_user_id(), 'delete_staff', 'delete', wp_json_encode($old), null);
        wp_redirect(add_query_arg('success', 'staff_deleted', $redirect_url));
        exit;
    } else {
        wp_redirect(add_query_arg('error', urlencode('invalid_staff_id'), $redirect_url));
        exit;
    }
}

// Secure delete via admin-post
add_action('admin_post_nds_delete_staff', 'nds_delete_staff_post');
function nds_delete_staff_post() {
    $redirect_url = admin_url('admin.php?page=nds-staff-management');

    if (!current_user_can('manage_options')) {
        wp_redirect(add_query_arg('error', urlencode('unauthorized'), $redirect_url));
        exit;
    }
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'nds_delete_staff')) {
        wp_redirect(add_query_arg('error', urlencode('security_check_failed'), $redirect_url));
        exit;
    }
    $staff_id = isset($_POST['staff_id']) ? intval($_POST['staff_id']) : 0;
    if ($staff_id <= 0) {
        wp_redirect(add_query_arg('error', urlencode('invalid_staff_id'), $redirect_url));
        exit;
    }
    nds_delete_staff($staff_id);
}

// ✅ Reusable Staff Form
function staff_form($type, $staff = null)
{
    global $wpdb;

    $action = ($type === 'edit') ? 'nds_update_staff' : 'nds_add_staff';
    $submit_text = ($type === 'edit') ? 'Update Staff' : 'Add Staff';
    $nonce_action = ($type === 'edit') ? 'nds_edit_staff_action' : 'nds_add_staff_action';
    $nonce_name = ($type === 'edit') ? 'nds_edit_staff_nonce' : 'nds_add_staff_nonce';

    if (isset($staff)):
        $first_name = $staff ? esc_attr($staff['first_name']) : '';
        $last_name  = $staff ? esc_attr($staff['last_name']) : '';
        $email      = $staff ? esc_attr($staff['email']) : '';
        $profile_picture      = $staff ? esc_attr($staff['profile_picture']) : '';
        $phone      = $staff ? esc_attr($staff['phone']) : '';
        $role       = $staff ? esc_attr($staff['role']) : '';
        $address       = $staff ? esc_attr($staff['address']) : '';
        $dob       = $staff ? esc_attr($staff['dob']) : '';
        $gender       = $staff ? esc_attr($staff['gender']) : '';

    elseif (!isset($staff)):
        $data = nds_handle_staff_data('POST');
        $first_name = $data ? esc_attr($data['first_name']) : '';
        $last_name  = $data ? esc_attr($data['last_name']) : '';
        $email      = $data ? esc_attr($data['email']) : '';
        $profile_picture      = $data ? esc_attr($data['profile_picture']) : '';
        $phone      = $data ? esc_attr($data['phone']) : '';
        $role       = $data ? esc_attr($data['role']) : '';
        $address       = $data ? esc_attr($data['address']) : '';
        $dob       = $data ? esc_attr($data['dob']) : '';
        $gender       = $data ? esc_attr($data['gender']) : '';

    endif;
?>
    <form method="POST" action="<?php echo admin_url('admin-post.php'); ?>">
        <?php wp_nonce_field($nonce_action, $nonce_name); ?>
        <?php if ($type === 'edit' && $staff): ?>
            <input type="hidden" name="staff_id" value="<?php echo esc_attr($staff['id']); ?>">
        <?php endif; ?>

        <table class="form-table">
            <tr>
                <th><label for="first_name">First Name</label></th>
                <td><input type="text" name="first_name" id="first_name" value="<?php echo esc_attr($first_name); ?>" required></td>
            </tr>
            <tr>
                <th><label for="last_name">Last Name</label></th>
                <td><input type="text" name="last_name" id="last_name" value="<?php echo esc_attr($last_name); ?>" required></td>
            </tr>
            <tr>
                <th><label for="email">Email</label></th>
                <td><input type="email" name="email" id="email" readonly value="<?php echo esc_attr($first_name); ?>@ndsacademy.co.za" required></td>
            </tr>
            <tr>
                <th><label for="phone">Phone</label></th>
                <td><input type="text" name="phone" id="phone" readonly value="<?php echo ($phone) ? esc_attr($phone) : '0111111111'; ?>"></td>
            </tr>
            <tr>
                <th><label for="gender">Gender</label></th>
                <td>
                    <select name="gender" id="gender" required>
                        <option value="Female" <?php selected($gender, 'Female'); ?>>Female</option>
                        <option value="Male" <?php selected($gender, 'Male'); ?>>Male</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="address">Address</label></th>
                <td><input type="text" name="address" id="address" readonly value="Vaal Triangle"></td>
            </tr>
            <tr>
                <th><label for="dob">Date Of Birth:</label></th>
                <td>
                    <input type="date" name="dob" id="dob" value="<?php echo $dob ? esc_attr($dob) : ''; ?>">
                </td>
            </tr>
            <tr>
                <th><label for="role">Role</label></th>
                <td>
                    <select name="role" id="role" required>
                        <?php
                        $available_roles = nds_get_staff_roles();
                        foreach ($available_roles as $available_role):
                        ?>
                            <option value="<?php echo esc_attr($available_role); ?>" <?php selected($role, $available_role); ?>>
                                <?php echo esc_html($available_role); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr class="recipePic">
                <th><label for="profile_picture">Profile Picture</label></th>
                <td>
                    <input type="hidden" name="profile_picture" id="profile_picture" value="<?php echo esc_attr($profile_picture); ?>">
                    <img id="profile_picture_preview" src="<?php echo esc_url($profile_picture ? wp_get_attachment_url($profile_picture) : ''); ?>" style="max-width: 150px; display: <?php echo $profile_picture ? 'block' : 'none'; ?>;">
                    <button type="button" class="button" id="upload_profile_picture">Upload</button>
                    <button type="button" class="button button-secondary" id="remove_profile_picture" style="display: <?php echo $profile_picture ? 'inline-block' : 'none'; ?>;">Remove</button>

                </td>
            </tr>
        </table>

        <input type="hidden" name="action" value="<?php echo esc_attr($action); ?>">
        <p><input type="submit" value="<?php echo esc_attr($submit_text); ?>" class="button button-primary"></p>
    </form>

    <script>
        jQuery(document).ready(function($) {
            // Handle upload button click - using ID selector
            jQuery('#upload_profile_picture').on('click', function(e) {
                e.preventDefault();
                var button = jQuery(this);
                var hiddenInput = jQuery('#profile_picture');
                var preview = jQuery('#profile_picture_preview');
                var removeBtn = jQuery('#remove_profile_picture');

                var frame = wp.media({
                    title: 'Select or Upload Profile Picture',
                    button: {
                        text: 'Use this image'
                    },
                    multiple: false
                });

                frame.on('select', function() {
                    var attachment = frame.state().get('selection').first().toJSON();
                    hiddenInput.val(attachment.id);
                    preview.attr('src', attachment.url).show();
                    removeBtn.show();
                });

                frame.open();
            });

            // Handle remove button click
            jQuery('#remove_profile_picture').on('click', function(e) {
                e.preventDefault();
                var hiddenInput = jQuery('#profile_picture');
                var preview = jQuery('#profile_picture_preview');
                var removeBtn = jQuery(this);

                hiddenInput.val('');
                preview.hide();
                removeBtn.hide();
            });
        });
    </script>
<?php
}

// ================= Staff <-> Course Linking and Audit =================

// Assign lecturer to a course
add_action('admin_post_nds_assign_lecturer', 'nds_assign_lecturer_to_course');
function nds_assign_lecturer_to_course() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'nds_assign_lecturer')) {
        wp_die('Security check failed');
    }
    global $wpdb;
    $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
    $lecturer_id = isset($_POST['lecturer_id']) ? intval($_POST['lecturer_id']) : 0;
    
    // Get redirect_to and program_id from POST
    $redirect_to = isset($_POST['redirect_to']) ? esc_url_raw($_POST['redirect_to']) : admin_url('admin.php?page=nds-staff-management');
    $program_id = isset($_POST['program_id']) ? intval($_POST['program_id']) : 0;
    
    if ($course_id <= 0 || $lecturer_id <= 0) {
        $redirect_url = add_query_arg('error', urlencode('invalid_params'), $redirect_to);
        if ($program_id > 0) {
            $redirect_url = add_query_arg('program_id', $program_id, $redirect_url);
        }
        wp_redirect($redirect_url);
        exit;
    }
    $table = $wpdb->prefix . 'nds_course_lecturers';
    $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE course_id=%d AND lecturer_id=%d", $course_id, $lecturer_id));
    if ($exists) {
        $redirect_url = add_query_arg('error', urlencode('already_assigned'), $redirect_to);
        if ($program_id > 0) {
            $redirect_url = add_query_arg('program_id', $program_id, $redirect_url);
        }
        wp_redirect($redirect_url);
        exit;
    }
    $ok = $wpdb->insert($table, ['course_id' => $course_id, 'lecturer_id' => $lecturer_id], ['%d','%d']);
    if ($ok === false) {
        $redirect_url = add_query_arg('error', urlencode('assign_failed'), $redirect_to);
        if ($program_id > 0) {
            $redirect_url = add_query_arg('program_id', $program_id, $redirect_url);
        }
        wp_redirect($redirect_url);
        exit;
    }
    // Audit
    nds_log_staff_action($lecturer_id, get_current_user_id(), 'assign_course', 'assign', null, wp_json_encode(['course_id' => $course_id]));
    
    // Success redirect with program_id if provided
    $redirect_url = add_query_arg('success', urlencode('lecturer_assigned'), $redirect_to);
    if ($program_id > 0) {
        $redirect_url = add_query_arg('program_id', $program_id, $redirect_url);
    }
    wp_redirect($redirect_url);
    exit;
}

// Unassign lecturer from course
add_action('admin_post_nds_unassign_lecturer', 'nds_unassign_lecturer_from_course');
function nds_unassign_lecturer_from_course() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'nds_unassign_lecturer')) {
        wp_die('Security check failed');
    }
    global $wpdb;
    $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
    $lecturer_id = isset($_POST['lecturer_id']) ? intval($_POST['lecturer_id']) : 0;
    if ($course_id <= 0 || $lecturer_id <= 0) {
        wp_redirect(admin_url('admin.php?page=nds-staff-management&error=' . urlencode('invalid_params')));
        exit;
    }
    $table = $wpdb->prefix . 'nds_course_lecturers';
    $deleted = $wpdb->delete($table, ['course_id' => $course_id, 'lecturer_id' => $lecturer_id], ['%d','%d']);
    if ($deleted === false) {
        wp_redirect(admin_url('admin.php?page=nds-staff-management&error=' . urlencode('unassign_failed')));
        exit;
    }
    // Audit
    nds_log_staff_action($lecturer_id, get_current_user_id(), 'unassign_course', 'unassign', wp_json_encode(['course_id' => $course_id]), null);
    wp_redirect(admin_url('admin.php?page=nds-staff-management&success=' . urlencode('lecturer_unassigned')));
    exit;
}

// Audit helper
function nds_log_staff_action($staff_id, $actor_id, $action, $action_type, $old_values = null, $new_values = null) {
    global $wpdb;
    $table = $wpdb->prefix . 'nds_staff_activity_log';
    $wpdb->insert($table, array(
        'staff_id' => intval($staff_id),
        'actor_id' => intval($actor_id),
        'action' => sanitize_text_field($action),
        'action_type' => sanitize_text_field($action_type),
        'old_values' => $old_values,
        'new_values' => $new_values,
        'ip_address' => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : null,
        'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_textarea_field($_SERVER['HTTP_USER_AGENT']) : null,
    ), array('%d','%d','%s','%s','%s','%s','%s','%s'));
}

/**
 * Bulk assign lecturers to courses (from drag-and-drop UI)
 * Expects a JSON payload of [{course_id, lecturer_id}, ...]
 */
add_action('admin_post_nds_bulk_assign_lecturers', 'nds_bulk_assign_lecturers');
function nds_bulk_assign_lecturers() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'nds_bulk_assign_lecturers')) {
        wp_die('Security check failed');
    }

    if (empty($_POST['assignments_json'])) {
        // Nothing to do – just return to page
        wp_redirect(admin_url('admin.php?page=nds-assign-lecturers'));
        exit;
    }

    $raw = wp_unslash($_POST['assignments_json']);
    $data = json_decode($raw, true);

    if (!is_array($data)) {
        wp_die('Invalid assignments payload.');
    }

    global $wpdb;
    $table = $wpdb->prefix . 'nds_course_lecturers';

    foreach ($data as $entry) {
        $course_id   = isset($entry['course_id']) ? intval($entry['course_id']) : 0;
        $lecturer_id = isset($entry['lecturer_id']) ? intval($entry['lecturer_id']) : 0;

        if ($course_id <= 0 || $lecturer_id <= 0) {
            continue;
        }

        // Use INSERT IGNORE to avoid duplicate violations on unique_course_lecturer
        $wpdb->query(
            $wpdb->prepare(
                "INSERT IGNORE INTO {$table} (course_id, lecturer_id) VALUES (%d, %d)",
                $course_id,
                $lecturer_id
            )
        );

        // Simple audit log per lecturer (course IDs kept minimal)
        nds_log_staff_action(
            $lecturer_id,
            get_current_user_id(),
            'bulk_assign_course',
            'assign',
            null,
            wp_json_encode(array('course_id' => $course_id))
        );
    }

    // Preserve faculty_id if it was in the request
    $faculty_id = isset($_POST['faculty_id']) ? intval($_POST['faculty_id']) : (isset($_GET['faculty_id']) ? intval($_GET['faculty_id']) : 0);
    $redirect = admin_url('admin.php?page=nds-assign-lecturers&bulk_assign=success');
    if ($faculty_id > 0) {
        $redirect = add_query_arg('faculty_id', $faculty_id, $redirect);
    }
    wp_redirect($redirect);
    exit;
}
?>