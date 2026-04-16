<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$programs_table = $wpdb->prefix . 'nds_programs';
$paths_table = $wpdb->prefix . 'nds_faculties';

// Check if a program exists
function nds_program_exists($name, $path_id)
{
    global $wpdb, $programs_table;
    return $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $programs_table WHERE name = %s AND faculty_id = %d", sanitize_text_field($name), intval($path_id))) > 0;
}

/**
 * Generate a unique program code
 * Format: First 3 letters of name + faculty_id + timestamp suffix if needed
 */
function nds_generate_program_code($name, $faculty_id, $wpdb = null, $programs_table = null)
{
    if (!$wpdb) {
        global $wpdb;
    }
    if (!$programs_table) {
        global $programs_table;
        if (!$programs_table) {
            $programs_table = $wpdb->prefix . 'nds_programs';
        }
    }

    // Get first 3 uppercase letters from name (remove spaces, special chars)
    $name_clean = preg_replace('/[^a-zA-Z0-9]/', '', $name);
    $prefix = strtoupper(substr($name_clean, 0, 3));
    if (empty($prefix)) {
        $prefix = 'PRG'; // Default prefix if name has no letters
    }

    // Base code: PREFIX-FACULTYID
    $base_code = $prefix . '-' . $faculty_id;
    $code = $base_code;
    $counter = 1;

    // Check if code exists, if so, append a number
    while ($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $programs_table WHERE code = %s", $code)) > 0) {
        $code = $base_code . '-' . $counter;
        $counter++;
        
        // Safety limit to prevent infinite loop
        if ($counter > 999) {
            $code = $prefix . '-' . $faculty_id . '-' . time();
            break;
        }
    }

    return $code;
}

// Create a Program
function nds_add_program()
{
    // Check nonce and permissions
    if (!isset($_POST['nds_add_program_nonce']) || !wp_verify_nonce($_POST['nds_add_program_nonce'], 'nds_add_program_nonce')) {
        wp_die('Security check failed');
    }
    
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    global $wpdb, $programs_table;

    $name = sanitize_text_field($_POST['program_name']);
    $description = sanitize_textarea_field($_POST['program_description']);
    $faculty_id = intval($_POST['faculty_id']);

    if (empty($name) || empty($faculty_id)) {
        wp_redirect(add_query_arg('error', 'missing_fields', wp_get_referer()));
        exit;
    }

    // Verify at least one faculty exists and the selected faculty is valid
    $paths_table = $wpdb->prefix . 'nds_faculties';
    $faculty_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM $paths_table");
    if ($faculty_count === 0) {
        wp_redirect(add_query_arg('error', 'no_faculty_exists', admin_url('admin.php?page=nds-faculties')));
        exit;
    }
    $faculty_valid = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $paths_table WHERE id = %d", $faculty_id));
    if ($faculty_valid === 0) {
        wp_redirect(add_query_arg('error', 'invalid_faculty', wp_get_referer()));
        exit;
    }

    // Check if program already exists
    if (nds_program_exists($name, $faculty_id)) {
        wp_redirect(add_query_arg('error', 'program_exists', wp_get_referer()));
        exit;
    }

    // Generate unique code for the program
    $code = nds_generate_program_code($name, $faculty_id, $wpdb, $programs_table);
    
    // Get faculty color and generate program color/palette
    require_once plugin_dir_path(__FILE__) . 'color-palette-generator.php';
    $color_generator = new NDS_ColorPaletteGenerator();
    
    // Get faculty color_primary
    $faculty = $wpdb->get_row($wpdb->prepare("SELECT color_primary, id FROM $paths_table WHERE id = %d", $faculty_id));
    $faculty_color = $faculty && $faculty->color_primary ? $faculty->color_primary : $color_generator->get_default_faculty_color($faculty_id);
    
    // Get program index (count of existing programs in this faculty)
    $program_index = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $programs_table WHERE faculty_id = %d", $faculty_id));
    $total_programs = $program_index + 1; // Including the one we're adding
    
    // Generate program color and palette
    $program_color_data = $color_generator->generate_program_color($faculty_color, $program_index, $total_programs);
    $program_palette = $color_generator->generate_program_palette($faculty_color, $program_index, $total_programs, 20); // 20 courses max

    $result = $wpdb->insert(
        $programs_table,
        [
            'code' => $code,
            'name' => $name,
            'description' => $description,
            'faculty_id' => $faculty_id,
            'color' => $program_color_data['hex'],
            'color_palette' => json_encode($program_palette),
            'program_type_id' => 1 // Default to 'diploma'
        ],
        ['%s', '%s', '%s', '%d', '%s', '%s', '%d']
    );

    if ($result === false) {
        $error_message = $wpdb->last_error;
        
        // Check for duplicate code error and retry
        if (strpos($error_message, 'Duplicate entry') !== false && strpos($error_message, 'code') !== false) {
            $code = nds_generate_program_code($name, $faculty_id, $wpdb, $programs_table);
            $result = $wpdb->insert(
                $programs_table,
                [
                    'code' => $code,
                    'name' => $name,
                    'description' => $description,
                    'faculty_id' => $faculty_id,
                    'color' => $program_color_data['hex'],
                    'color_palette' => json_encode($program_palette),
                    'program_type_id' => 1
                ],
                ['%s', '%s', '%s', '%d', '%s', '%s', '%d']
            );
        }
        
        if ($result === false) {
            error_log('NDS Program Creation Failed: ' . $wpdb->last_error);
            wp_redirect(add_query_arg('error', 'db_error', wp_get_referer()));
            exit;
        }
    }

    // Get the last inserted ID
    $program_id = $wpdb->insert_id;

    // Get faculty details
    $faculty = $wpdb->get_row($wpdb->prepare("SELECT page_id, category_id FROM $paths_table WHERE id = %d", $faculty_id));

    // Create WordPress page for the program
    $program_page_id = wp_insert_post([
        'post_title'   => $name,
        'post_content' => $description,
        'post_status'  => 'publish',
        'post_type'    => 'page',
        'post_parent'  => $faculty ? $faculty->page_id : 0,
    ]);

    if ($program_page_id && !is_wp_error($program_page_id)) {
        // Set the page template if needed
        update_post_meta($program_page_id, '_wp_page_template', 'program-single.php');
    }

    // Use the faculty's category
    $category_id = $faculty ? $faculty->category_id : 0;

    // Update the program record with the page_id and category_id
    $wpdb->update(
        $programs_table,
        [
            'page_id'     => $program_page_id,
            'category_id' => $category_id,
        ],
        ['id' => $program_id],
        ['%d', '%d'],
        ['%d']
    );

    // Redirect with success message
    wp_redirect(add_query_arg('success', 'program_created', wp_get_referer()));
    exit;
}
add_action('admin_post_nds_add_program', 'nds_add_program');

// AJAX handler for adding program
function nds_add_program_ajax() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'nds_add_program_nonce')) {
        wp_send_json_error(['message' => 'Security check failed']);
    }
    
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb, $programs_table;

    // Ensure color column exists in programs table
    $programs_columns = $wpdb->get_col("DESCRIBE $programs_table");
    if (!in_array('color', $programs_columns)) {
        $wpdb->query("ALTER TABLE $programs_table ADD COLUMN color VARCHAR(7) NULL AFTER category_id");
    }

    $name = sanitize_text_field($_POST['program_name']);
    $description = sanitize_textarea_field($_POST['program_description']);
    $faculty_id = intval($_POST['faculty_id']);

    if (empty($name) || empty($faculty_id)) {
        wp_send_json_error(['message' => 'Please fill in all required fields']);
    }

    // Verify at least one faculty exists and the selected faculty is valid
    $ajax_paths_table = $wpdb->prefix . 'nds_faculties';
    $ajax_faculty_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM $ajax_paths_table");
    if ($ajax_faculty_count === 0) {
        wp_send_json_error(['message' => 'No faculty exists. Please create a Faculty before adding a Program.']);
    }
    $ajax_faculty_valid = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $ajax_paths_table WHERE id = %d", $faculty_id));
    if ($ajax_faculty_valid === 0) {
        wp_send_json_error(['message' => 'The selected faculty does not exist.']);
    }

    // Check if program already exists
    if (nds_program_exists($name, $faculty_id)) {
        wp_send_json_error(['message' => 'A program with this name already exists in the selected faculty']);
    }

    // Generate unique code for the program
    $code = nds_generate_program_code($name, $faculty_id, $wpdb, $programs_table);
    
    // Get faculty color and generate program color/palette
    require_once plugin_dir_path(__FILE__) . 'color-palette-generator.php';
    $color_generator = new NDS_ColorPaletteGenerator();
    
    $paths_table = $wpdb->prefix . 'nds_faculties';
    
    // Get faculty color_primary
    $faculty = $wpdb->get_row($wpdb->prepare("SELECT color_primary, id FROM $paths_table WHERE id = %d", $faculty_id));
    $faculty_color = $faculty && $faculty->color_primary ? $faculty->color_primary : $color_generator->get_default_faculty_color($faculty_id);
    
    // Get program index (count of existing programs in this faculty)
    $program_index = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $programs_table WHERE faculty_id = %d", $faculty_id));
    $total_programs = $program_index + 1; // Including the one we're adding
    
    // Generate program color and palette
    $program_color_data = $color_generator->generate_program_color($faculty_color, $program_index, $total_programs);
    $program_palette = $color_generator->generate_program_palette($faculty_color, $program_index, $total_programs, 20); // 20 courses max

    $result = $wpdb->insert(
        $programs_table,
        [
            'code' => $code,
            'name' => $name,
            'description' => $description,
            'faculty_id' => $faculty_id,
            'program_type_id' => 1, // Default to 'diploma'
            'color' => $program_color_data['hex'],
            'color_palette' => json_encode($program_palette)
        ],
        ['%s', '%s', '%s', '%d', '%d', '%s', '%s']
    );

    if ($result === false) {
        $error_message = $wpdb->last_error;
        
        // Check for duplicate code error
        if (strpos($error_message, 'Duplicate entry') !== false && strpos($error_message, 'code') !== false) {
            // Retry with a different code
            $code = nds_generate_program_code($name, $faculty_id, $wpdb, $programs_table);
            $result = $wpdb->insert(
                $programs_table,
                [
                    'code' => $code,
                    'name' => $name,
                    'description' => $description,
                    'faculty_id' => $faculty_id,
                    'program_type_id' => 1,
                    'color' => $program_color
                ],
                ['%s', '%s', '%s', '%d', '%d', '%s']
            );
            
            if ($result === false) {
                $error_message = $wpdb->last_error;
            }
        }
        
        // Check for foreign key constraint error
        if ($result === false && strpos($error_message, 'foreign key constraint') !== false) {
            $error_message = 'Database constraint error: The faculty reference is invalid. Please run the database fix script to update the foreign key constraint.';
        }
        
        if ($result === false) {
            wp_send_json_error(['message' => $error_message]);
        }
    }

    // Get the inserted program
    $program_id = $wpdb->insert_id;
    
    // Get the full program data with faculty name
    $table_paths = $wpdb->prefix . 'nds_faculties';
    $table_courses = $wpdb->prefix . 'nds_courses';
    $program = $wpdb->get_row($wpdb->prepare("
        SELECT p.*,
               COUNT(c.id) as course_count,
               ep.name as path_name
        FROM {$programs_table} p
        LEFT JOIN {$table_courses} c ON p.id = c.program_id
        LEFT JOIN {$table_paths} ep ON p.faculty_id = ep.id
        WHERE p.id = %d
        GROUP BY p.id
    ", $program_id), ARRAY_A);

    wp_send_json_success([
        'message' => 'Program created successfully!',
        'program' => $program,
        'faculty_id' => $faculty_id
    ]);
}
add_action('wp_ajax_nds_add_program_ajax', 'nds_add_program_ajax');

// Retrieve all programs
function nds_get_programs()
{
    global $wpdb;

    $paths_table = $wpdb->prefix . 'nds_faculties';
    $programs_table = $wpdb->prefix . 'nds_programs';

    // Get all faculties with their associated programs
    $query = "
        SELECT e.id AS faculty_id, e.name AS faculty_name, e.description AS faculty_description, 
               p.id AS program_id, p.name AS program_name, p.description AS program_description 
        FROM $paths_table e
        LEFT JOIN $programs_table p ON e.id = p.faculty_id
        ORDER BY e.id, p.id
    ";

    $results = $wpdb->get_results($query);

    if (!$results) {
        return [];
    }

    // Structure data: Group programs under their respective faculties
    $structured_data = [];
    foreach ($results as $row) {
        $faculty_id = $row->faculty_id;

        if (!isset($structured_data[$faculty_id])) {
            $structured_data[$faculty_id] = [
                'faculty_id' => $row->faculty_id,
                'faculty_name' => $row->faculty_name,
                'faculty_description' => $row->faculty_description,
                'programs' => []
            ];
        }


        if ($row->program_id) { // Only add if there’s a valid program
            $structured_data[$faculty_id]['programs'][] = [
                'program_id' => $row->program_id,
                'program_name' => $row->program_name,
                'program_description' => $row->program_description
            ];
        }
    }

    return array_values($structured_data); // Reset array keys
}
// Retrieve all programs
function nds_get_programs_by_path($pathID)
{

    global $wpdb;

    $paths_table = $wpdb->prefix . 'nds_faculties';
    $programs_table = $wpdb->prefix . 'nds_programs';

    // Get all faculties with their associated programs
    $query = "
        SELECT e.id AS faculty_id, e.name AS faculty_name, e.description AS faculty_description, 
       p.id AS program_id, p.name AS program_name, p.description AS program_description 
FROM $paths_table e
LEFT JOIN $programs_table p ON e.id = p.faculty_id
WHERE e.id = $pathID
ORDER BY e.id, p.id
LIMIT 0, 25;
    ";

    $results = $wpdb->get_results($query);

    if (!$results) {
        return [];
    }

    // Structure data: Group programs under their respective faculties
    $structured_data = [];
    foreach ($results as $row) {
        $faculty_id = $row->faculty_id;

        if (!isset($structured_data[$faculty_id])) {
            $structured_data[$faculty_id] = [
                'faculty_id' => $row->faculty_id,
                'faculty_name' => $row->faculty_name,
                'faculty_description' => $row->faculty_description,
                'programs' => []
            ];
        }


        if ($row->program_id) { // Only add if there’s a valid program
            $structured_data[$faculty_id]['programs'][] = [
                'program_id' => $row->program_id,
                'program_name' => $row->program_name,
                'program_description' => $row->program_description
            ];
        }
    }

    return array_values($structured_data); // Reset array keys
}

// Retrieve a single program
function nds_get_program($id)
{
    global $wpdb, $programs_table;
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM $programs_table WHERE id = %d", intval($id)));
}

// Update a Program
function nds_update_program()
{
    global $wpdb, $programs_table;

    $id = $_POST['program_id'];
    $faculty_id = $_POST['faculty_id'];
    $name = $_POST['program_name'];
    $description = $_POST['program_description'];
    $program_color = isset($_POST['program_color']) ? sanitize_hex_color($_POST['program_color']) : nds_generate_program_color();
    // Verify nonce before processing xxxxxxx

    if (!isset($_POST['nds_update_nonce']) || !wp_verify_nonce($_POST['nds_update_nonce'], 'nds_update_program_nonce')) {
        return false; // Nonce verification failed
    }

    // Validate required fields
    if (empty($name) || empty($faculty_id)) {
        return false;
    }

    // Check if program already exists
    if (nds_program_exists($name, $faculty_id)) {
        return false; // Program already exists
    }

    $updated = $wpdb->update(
        $programs_table,
        [
            'name' => sanitize_text_field($name),
            'description' => sanitize_textarea_field($description),
            'faculty_id' => intval($faculty_id),
            'color' => $program_color
        ],
        ['id' => intval($id)],
        ['%s', '%s', '%d', '%s'],
        ['%d']
    );

    if ($updated) {
        // Update the associated page
        $program = $wpdb->get_row($wpdb->prepare("SELECT page_id FROM $programs_table WHERE id = %d", intval($id)));
        if ($program && $program->page_id) {
            wp_update_post([
                'ID'           => $program->page_id,
                'post_title'   => sanitize_text_field($name),
                'post_content' => sanitize_textarea_field($description),
            ]);
        }

        $rayray = array('faculty_id' => $faculty_id);
        redd("?page=nds-programs", $rayray);
        exit;
    } else {
        return "Sorry Boss!!!";
    }
}
add_action('admin_post_nds_update_program', 'nds_update_program');

// Handle program deletion via admin-post.php
add_action('admin_post_nds_delete_program', 'nds_handle_delete_program_post');
function nds_handle_delete_program_post()
{
    $redirect_url = wp_get_referer() ?: admin_url('admin.php?page=nds-programs');

    // Check nonce and permissions before deleting
    if (!isset($_POST['nds_delete_program_nonce']) || !wp_verify_nonce($_POST['nds_delete_program_nonce'], 'nds_delete_program_nonce')) {
        wp_redirect(add_query_arg('error', 'security_check_failed', $redirect_url));
        exit;
    }

    if (!current_user_can('manage_options')) {
        wp_redirect(add_query_arg('error', 'unauthorized', $redirect_url));
        exit;
    }

    $program_id = isset($_POST['program_id']) ? intval($_POST['program_id']) : 0;
    if ($program_id <= 0) {
        wp_redirect(add_query_arg('error', 'invalid_program_id', $redirect_url));
        exit;
    }

    // nds_delete_program handles successful redirects and exits.
    $result = nds_delete_program($program_id);

    if ($result) {
        $redirect_url = add_query_arg('error', rawurlencode($result), $redirect_url);
        wp_redirect($redirect_url);
        exit;
    }
}


function nds_delete_program($id)
{
    global $wpdb;
    $programs_table = $wpdb->prefix . 'nds_programs';

    // Query to fetch the program details by ID
    $query = $wpdb->prepare(
        "SELECT * FROM {$programs_table} WHERE id = %d",
        $id
    );
    $program = $wpdb->get_row($query);

    if ($program) {
        // If there's an associated category, delete it
        if (!empty($program->name)) {
            $category = get_term_by('name', $program->name, 'category', OBJECT, 'slug');
            if ($category) {
                wp_delete_term($category->term_id, 'category');
            }
        }

        $deleted = $wpdb->delete($programs_table, ['id' => intval($id)], ['%d']);
        if ($deleted) {
            // Get faculty_id from the deleted program
            $faculty_id = $program->faculty_id;
            $rayray = array('faculty_id' => $faculty_id);
            redd("?page=nds-programs", $rayray);
            exit;
        } else {
            return "Sorry Boss!!!";
        }
    } else {
        return "Program not found.";
    }
}
function program_form($act, $pathID, $program = null)
{
    global $wpdb;
    $paths_table = $wpdb->prefix . 'nds_faculties';
    $paths = $wpdb->get_results("SELECT id, name FROM $paths_table");

    // Block adding a program if no faculty exists yet
    if ($act === 'add' && empty($paths)) {
        $faculty_url = admin_url('admin.php?page=nds-faculties');
        echo '<div class="bg-yellow-50 border border-yellow-300 text-yellow-800 rounded-md p-4">';
        echo '<p class="font-semibold">No Faculty Found</p>';
        echo '<p class="text-sm mt-1">You must <a href="' . esc_url($faculty_url) . '" class="underline font-medium">create a Faculty</a> before you can add a Program.</p>';
        echo '</div>';
        return;
    }

    $pathID = (isset($program)) ? $program['id'] : $pathID;
    
    // For add action, use AJAX submission
    $form_action = ($act === 'add') ? 'javascript:void(0);' : admin_url('admin-post.php');
    $onsubmit = ($act === 'add') ? 'event.preventDefault(); submitProgramForm(this);' : '';
    $ajax_action = ($act === 'add') ? 'nds_add_program_ajax' : 'nds_update_program';
    $nonce_action = ($act === 'add') ? 'nds_add_program_nonce' : 'nds_update_program_nonce';
    $nonce_name = ($act === 'add') ? 'nds_add_program_nonce' : 'nds_update_nonce';
    
?>

    <div class="bg-white rounded p-4 space-y-4">
        <h2 class="text-2xl font-bold"><?php echo ucwords($act); ?> Program</h2>
        <form method="POST" action="<?php echo $form_action; ?>" onsubmit="<?php echo $onsubmit; ?>">
            <?php wp_nonce_field($nonce_action, $nonce_name); ?>
            <?php if ($act === 'add'): ?>
                <input type="hidden" name="action" value="<?php echo $ajax_action; ?>">
                <input type="hidden" name="nonce" value="<?php echo wp_create_nonce($nonce_action); ?>">
            <?php endif; ?>
            <div class="space-y-4">
                <div>
                    <label for="program_name" class="block text-sm font-medium text-gray-700">Program Name:</label>
                    <input type="text" value="<?php echo (isset($program)) ? $program['name'] : ''; ?>" name="program_name"
                        placeholder="Program Name" required
                        class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                </div>
                <div>
                    <label for="program_description" class="block text-sm font-medium text-gray-700">Description:</label>
                    <textarea name="program_description" placeholder="Program Description"
                        class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"><?php echo (isset($program)) ? $program['description'] : ''; ?></textarea>
                </div>
                <div>
                    <label for="faculty_id" class="block text-sm font-medium text-gray-700">Faculty:</label>
                    <select name="faculty_id" required
                        class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">Select Faculty</option>
                        <?php foreach ($paths as $path): ?>
                            <option value="<?php echo $path->id; ?>"

                                <?php echo (isset($pathID) && $pathID === $path->id || isset($program['faculty_id']) && $program['faculty_id'] === $path->id) ? 'selected' : ''; ?>>
                                <?php echo $path->name; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div id="program_color_preview" style="display: none;">
                    <label class="block text-sm font-medium text-gray-700">Program Color (Auto-generated):</label>
                    <div class="mt-1 flex items-center gap-3">
                        <div class="h-12 w-20 border border-gray-300 rounded-md" id="program_color_display" style="background-color: <?php echo (isset($program) && isset($program['color'])) ? $program['color'] : '#607D8B'; ?>;"></div>
                        <span class="text-sm text-gray-600" id="program_color_text"><?php echo (isset($program) && isset($program['color'])) ? $program['color'] : '#607D8B'; ?></span>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">This color is automatically generated from the faculty's parent color. Courses will use shades of this color.</p>
                </div>

                <div class="mt-4">
                    <input
                        class="w-full py-2 px-4 bg-indigo-600 text-white font-semibold rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-opacity-50"
                        type="submit" name="<?php echo $act; ?>_program" value="<?php echo ucwords($act); ?> Program" />
                    <?php if ($act !== 'add'): ?>
                        <input type="hidden" name="action" value="nds_<?php echo $act; ?>_program" />
                        <input type="hidden" name="program_id" value="<?php echo $pathID; ?>" />
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
<?php
}

function programForm($pathID, $act, $program = null)
{
    global $wpdb;
    $paths_table = $wpdb->prefix . 'nds_faculties';
    $paths = $wpdb->get_results("SELECT id, name FROM $paths_table");

?>
    <div class="bg-white rounded p-4">
        <h1 class="text-2xl font-bold"> Program</h1>

        <form method="POST" action="<?php echo admin_url('admin-post.php'); ?>">
            <?php wp_nonce_field('nds_' . $act . '_program_nonce', 'nds_' . $act . '_nonce'); ?>
            <div class="space-y-4">
                <div>
                    <label for="program_name" class="block text-sm font-medium text-gray-700">Course Name:</label>
                    <input type="text" value="<?php echo (isset($program)) ? $program['name'] : ''; ?>" name="program_name"
                        placeholder="Program Name" required
                        class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                </div>
                <div>
                    <label for="program_description" class="block text-sm font-medium text-gray-700">Description:</label>
                    <textarea name="program_description" placeholder="Program Description"
                        class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"><?php echo (isset($program)) ? $program['description'] : ''; ?></textarea>
                </div>
                <div class="mt-4">
                    <input
                        class="w-full py-2 px-4 bg-indigo-600 text-white font-semibold rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-opacity-50"
                        type="submit" name="<?php echo $act; ?>_program" value="<?php echo $act; ?> Program" />
                    <input type="hidden" name="action" value="nds_<?php echo $act; ?>_program" />
                    <input type="hidden" name="program_id" value="<?php echo (isset($program)) ? $program['id'] : ''; ?>" />
                    <input type="hidden" name="faculty_id" value="<?php echo (isset($pathID)) ? $pathID : ''; ?>" />
                </div>
            </div>
        </form>
    </div>
<?php
}

// Form to add a new program
function nds_add_program_form()
{
    echo program_form('add', null, null);
}

function nds_display_programs_table($atts)
{


    if (isset($atts['pathid'])) {
        $programs = nds_get_programs_by_path($atts['pathid']);
    } else {
        $programs = nds_get_programs();
    }


    if (empty($programs)) {
        return "<p>No programs found.</p>";
    }

    ob_start(); // Start output buffering
?>
    <table border="1" cellpadding="5" cellspacing="0" width="100%">
        <thead>
            <tr>
                <th></th>
                <th></th>
                <!--<th>Program Description</th> -->
            </tr>
        </thead>
        <tbody>
            <?php foreach ($programs as $path) : ?>
                <tr class="bg-slate-100 border-b-2">
                    <td valign="top" colspan="2"><strong><?= esc_html($path['path_name']); ?></strong></td>
                </tr>
                <tr>
                    <?php if (!empty($path['programs'])) :
                        $first = true;
                        foreach ($path['programs'] as $program) : ?>
                            <?php if (!$first) echo '<tr>'; ?>
                            <td><?= esc_html($program['program_name']); ?></td>
                            <!-- <td><?= esc_html($program['program_description']); ?></td> -->
                            <td align="end">
                                <a href="?page=nds-edit-program&edit_program=<?= $program['program_id']; ?>" class="button">Edit</a>
                                <a href="?page=nds-edit-program&delete_program=<?php echo $program['program_id']; ?>" class="button"
                                    onclick="return confirm('Are you sure?');">Delete</a>
                            </td>
                            <?php $first = false; ?>
                    <?php endforeach;
                    endif; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <script>
        function addRow() {
            let table = document.getElementById("programTable").getElementsByTagName('tbody')[0];
            let newRow = table.insertRow();

            // Add new cells
            let cell1 = newRow.insertCell(0);
            let cell2 = newRow.insertCell(1);

            // Input field for program name
            cell1.innerHTML = '<input type="text" name="program_name[]" placeholder="Enter program name">';

            // Remove button
            cell2.innerHTML = '<button onclick="removeRow(this)">Remove</button>';
        }

        function removeRow(button) {
            let row = button.parentNode.parentNode;
            row.parentNode.removeChild(row);
        }

        function saveRows() {
            let inputs = document.querySelectorAll('input[name="program_name[]"]');
            let data = [];

            inputs.forEach(input => {
                data.push(input.value);
            });

            console.log("Saving programs:", data);

            // Here you can send the `data` array to the backend using Fetch or AJAX
        }
    </script>
<?php
    return ob_get_clean(); // Get buffer contents and clean buffer
}
add_shortcode('nds_programs_table', 'nds_display_programs_table');

function nds_get_program_by_id($program_id)
{
    global $wpdb;
    $programs_table = $wpdb->prefix . 'nds_programs';
    // Query to fetch the program details by ID
    $query = $wpdb->prepare(
        "SELECT * FROM {$programs_table} WHERE id = %d",
        $program_id
    );
    // Fetch the result and return it
    return $wpdb->get_row($query, ARRAY_A); // Returns the row as an associative array
}

function modalFunc($atts)
{
    $name = sanitize_text_field($atts['gama']);
    $pathID = sanitize_text_field($atts['pathid']);

    $prom = array('id' => $pathID);
?>
    <!-- Button to trigger modal -->
    <button data-modal-target="<?php echo $name . $pathID; ?>" class="open-modal px-4 py-2 bg-blue-500 text-white rounded">Add Program</button>

    <!-- Modal overlay -->
    <div class="modal-overlay fixed inset-0 bg-black bg-opacity-50 <?php echo $name . $pathID; ?> hidden">
        <!-- Modal content -->
        <div class="bg-white rounded p-6 max-w-sm mx-auto mt-20 relative">
            <!-- Close button -->
            <span class="close-modal absolute top-2 right-2 cursor-pointer font-bold">X</span>
            <!-- Modal content -->
            <?php program_form('add', null, $prom); ?>
            


        </div>
    </div>
<?php
}


add_shortcode('modalBtn', 'modalFunc');
