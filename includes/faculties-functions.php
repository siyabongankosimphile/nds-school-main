<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

global $wpdb;
$table_faculties = $wpdb->prefix . "nds_faculties";
$table_courses = $wpdb->prefix . "nds_courses";

// ✅ Handle faculty data processing
function nds_handle_faculty_data($request_type = 'POST')
{
    $request_data = ($request_type === 'POST') ? $_POST : $_GET;
    
    require_once plugin_dir_path(__FILE__) . 'color-palette-generator.php';
    $color_generator = new NDS_ColorPaletteGenerator();

    $color_primary = isset($request_data['color_primary']) ? sanitize_text_field($request_data['color_primary']) : '';
    
    // If no color provided, get default based on existing faculty count
    if (empty($color_primary)) {
        global $wpdb, $table_faculties;
        $faculty_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_faculties");
        $color_primary = $color_generator->get_default_faculty_color($faculty_count);
    }

    return [
        'name'        => isset($request_data['name']) ? sanitize_text_field($request_data['name']) : '',
        'description' => isset($request_data['description']) ? sanitize_textarea_field($request_data['description']) : '',
        'color_primary' => $color_primary,
    ];
}

// ✅ Check if faculty exists (by name)
function nds_faculty_exists($name)
{
    global $wpdb, $table_faculties;
    $query = $wpdb->prepare("SELECT COUNT(*) FROM $table_faculties WHERE name = %s", $name);
    return ($wpdb->get_var($query) > 0);
}

// ✅ Get all faculties
function nds_get_all_faculties()
{
    global $wpdb, $table_faculties;
    return $wpdb->get_results("SELECT * FROM $table_faculties", ARRAY_A);
}

// ✅ Add Faculty
add_action('admin_post_nds_add_faculty', 'nds_add_faculty');
function nds_add_faculty()
{
    if (!isset($_POST['nds_add_faculty_nonce']) || !wp_verify_nonce($_POST['nds_add_faculty_nonce'], 'nds_add_faculty_action')) {
        wp_die('Security check failed');
    }
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    global $wpdb, $table_faculties;
    $data = nds_handle_faculty_data('POST');

    if (empty($data['name'])) {
        wp_die('Required fields are missing.');
    }

    if (nds_faculty_exists($data['name'])) {
        wp_die('Faculty name already exists.');
    }

    $wpdb->insert($table_faculties, $data, ['%s', '%s', '%s']);
    $last_inserted_id = $wpdb->insert_id;

    wp_redirect(admin_url('admin.php?page=nds-faculty-edit&faculty_id=' . $last_inserted_id . '&message=success'));
    exit;
}

// ✅ Get Faculty by ID
function nds_get_faculty_by_id($id)
{
    global $wpdb, $table_faculties;
    return $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM $table_faculties WHERE id = %d", intval($id)),
        ARRAY_A
    );
}

// ✅ Update Faculty
add_action('admin_post_nds_update_faculty', 'nds_update_faculty');
function nds_update_faculty()
{

    if (!isset($_POST['nds_faculty_nonce']) || !wp_verify_nonce($_POST['nds_faculty_nonce'], 'nds_edit_faculty_action')) {
        wp_die('Security check failed');
    }
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    global $wpdb, $table_faculties;
    $faculty_id = intval($_POST['faculty_id']);
    $data = nds_handle_faculty_data('POST');

    if (empty($faculty_id)) {
        wp_die('Invalid faculty ID.');
    }

    $wpdb->update($table_faculties, $data, ['id' => $faculty_id], ['%s', '%s', '%s'], ['%d']);
    wp_redirect(admin_url('admin.php?page=nds-faculty-edit&faculty_id=' . $faculty_id . '&message=updated'));
    exit;
}

function nds_school_breadcrumb($atts)
{
    // Set default attributes
    $atts = shortcode_atts(
        array(
            'data' => '[]', // Default is an empty array
        ),
        $atts,
        'custom_breadcrumbs'
    );

    // Decode the JSON data passed to the shortcode
    $breadlinks = json_decode(urldecode($atts['data']), true);

    // Check if the decoded data is valid
    if (!is_array($breadlinks)) {
        return "Invalid breadcrumb data.";
    }
    // Loop through each breadcrumb and generate the list items
    $total_links = count($breadlinks);

?>
<nav class="justify-between px-4 py-3 text-gray-700 border border-gray-200 rounded-lg sm:flex sm:px-5 bg-gray-50 dark:bg-gray-800 dark:border-gray-700"
    aria-label="Breadcrumb">
    <ol class="inline-flex items-center mb-3 space-x-1 md:space-x-2 rtl:space-x-reverse sm:mb-0">
        <?php
            $total_links = count($breadlinks);
            foreach ($breadlinks as $index => $link):
                if ($index === $total_links - 1): ?>
        <li>
            <div class="flex items-center">
                <span
                    class="text-sm font-medium text-gray-500 dark:text-gray-400"><?php echo esc_html($link['name']); ?></span>
                <?php if (isset($link['badge'])): ?>
                <span
                    class="bg-blue-100 text-blue-800 text-xs font-semibold me-2 px-2 py-0.5 rounded-sm dark:bg-blue-200 dark:text-blue-800 hidden sm:flex">
                    <?php echo esc_html($link['badge']); ?>
                </span>
                <?php endif; ?>
            </div>
        </li>
        <?php else: ?>
        <li>
            <div class="flex items-center">
                <a href="<?php echo esc_url($link['slug']); ?>"
                    class="ms-1 text-sm font-medium text-gray-700 hover:text-blue-600 md:ms-2 dark:text-gray-400 dark:hover:text-white">
                    <?php echo esc_html($link['name']); ?>
                </a>
                <svg class="rtl:rotate-180 w-3 h-3 mx-1 text-gray-400" aria-hidden="true"
                    xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 6 10">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="m1 9 4-4-4-4" />
                </svg>
            </div>
        </li>
        <?php endif;
            endforeach; ?>
    </ol>
</nav>

<?php

}

add_shortcode('nds_breadcrumb', 'nds_school_breadcrumb');

function nds_get_all_faculties_table()
{
    $breadlinks = [
        ["name" => "", "slug" => "?page="],
        ["name" => "", "slug" => "?page="],
        ["name" => "", "slug" => "?page="],
    ];
    $breadlinks_json = urlencode(json_encode($breadlinks));
?>
<div class="space-y-4">
    <h2 class="mb-2 text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Faculties</h2>
    <?php echo do_shortcode('[nds_breadcrumb data="' . $breadlinks_json . '"]'); ?>

    <div class="grid grid-cols-3 gap-4">
        <?php
            $faculties = nds_get_all_faculties();
            foreach ($faculties as $key => $faculty) {
            ?>
        <div
            class="max-w-sm p-6 bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700">
            <a href="#">
                <h5 class="mb-2 text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                    <?php echo $faculty['name']; ?></h5>
            </a>
            <p class="mb-3 font-normal text-gray-700 dark:text-gray-400"><?php echo $faculty['description']; ?></p>
            <a href="?page=nds-faculty-edit&faculty_id=<?php echo $faculty['id']; ?>"
                class="inline-flex items-center px-3 py-2 text-sm font-medium text-center text-white bg-blue-700 rounded-lg hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                View
                <svg class="rtl:rotate-180 w-3.5 h-3.5 ms-2" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
                    fill="none" viewBox="0 0 14 10">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M1 5h12m0 0L9 1m4 4L9 9" />
                </svg>
            </a>
        </div>

        <?php
            }
            ?>

    </div>
</div>
<?php
}

// ✅ Delete Faculty
add_action('admin_post_nds_delete_faculty', 'nds_delete_faculty');
function nds_delete_faculty()
{
    $redirect_url = admin_url('admin.php?page=nds-faculties');

    if (!current_user_can('manage_options')) {
        wp_redirect(add_query_arg('message', 'unauthorized', $redirect_url));
        exit;
    }

    $nonce_valid = false;
    if (isset($_POST['_wpnonce'])) {
        $nonce_valid = wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'nds_delete_faculty');
    } elseif (isset($_GET['_wpnonce'])) {
        $nonce_valid = wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'nds_delete_faculty');
    }

    if (!$nonce_valid) {
        wp_redirect(add_query_arg('message', 'security_check_failed', $redirect_url));
        exit;
    }

    $faculty_raw = null;
    if (isset($_POST['faculty_id'])) {
        $faculty_raw = $_POST['faculty_id'];
    } elseif (isset($_GET['faculty_id'])) {
        $faculty_raw = $_GET['faculty_id'];
    }

    if (!is_numeric($faculty_raw)) {
        wp_redirect(add_query_arg('message', 'invalid_id', $redirect_url));
        exit;
    }

    $faculty_id = intval($faculty_raw);
    if ($faculty_id <= 0) {
        wp_redirect(add_query_arg('message', 'invalid_id', $redirect_url));
        exit;
    }

    // Reuse the central faculty delete flow that handles linked records.
    if (function_exists('nds_delete_education_path')) {
        nds_delete_education_path($faculty_id);
        return;
    }

    global $wpdb;
    $table_faculties = $wpdb->prefix . 'nds_faculties';
    $deleted = $wpdb->delete($table_faculties, ['id' => $faculty_id], ['%d']);

    if ($deleted === false) {
        wp_redirect(add_query_arg('message', 'delete_failed', $redirect_url));
        exit;
    }

    wp_redirect(add_query_arg('message', 'deleted', $redirect_url));
    exit;
}


// ✅ Reusable Faculty Form
function faculty_form($type, $faculty = null)
{
    global $wpdb;

    $action = ($type === 'edit') ? 'nds_update_faculty' : 'nds_add_faculty';
    $submit_text = ($type === 'edit') ? 'Update Faculty' : 'Add Faculty';
    $nonce_action = ($type === 'edit') ? 'nds_edit_faculty_action' : 'nds_add_faculty_action';
    $nonce_name = ($type === 'edit') ? 'nds_faculty_nonce' : 'nds_add_faculty_nonce';

    require_once plugin_dir_path(__FILE__) . 'color-palette-generator.php';
    $color_generator = new NDS_ColorPaletteGenerator();
    
    $name = $faculty ? esc_attr($faculty['name'] ?? $faculty->name ?? '') : '';
    $description = $faculty ? esc_attr($faculty['description'] ?? $faculty->description ?? '') : '';
    $color_primary = $faculty ? esc_attr($faculty['color_primary'] ?? $faculty->color_primary ?? '') : '';
    
    // Get default color if empty
    if (empty($color_primary)) {
        global $wpdb, $table_faculties;
        $faculty_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_faculties");
        $color_primary = $color_generator->get_default_faculty_color($faculty_count);
    }

?>
<form method="POST" action="<?php echo admin_url('admin-post.php'); ?>" class="space-y-4">
    <?php wp_nonce_field($nonce_action, $nonce_name); ?>
    <?php if ($type === 'edit' && $faculty): ?>
    <input type="hidden" name="faculty_id" value="<?php echo esc_attr(is_array($faculty) ? $faculty['id'] : $faculty->id); ?>">
    <?php endif; ?>

    <div class="flex flex-col">
        <label for="name" class="font-medium text-gray-700">Faculty Name *</label>
        <input type="text" name="name" id="name"
            value="<?php echo $name; ?>"
            class="mt-2 p-3 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
    </div>

    <div class="flex flex-col">
        <label for="description" class="font-medium text-gray-700">Description</label>
        <textarea name="description" id="description" rows="4"
            class="mt-2 p-3 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"><?php echo $description; ?></textarea>
    </div>
    
    <div class="flex flex-col">
        <label for="color_primary" class="font-medium text-gray-700">Parent Color *</label>
        <p class="text-sm text-gray-500 mb-2">Choose a color for this faculty. Programs within this faculty will automatically use shades of this color.</p>
        <div class="flex items-center gap-3 mt-2">
            <input type="color" name="color_primary" id="color_primary" value="<?php echo $color_primary; ?>"
                class="h-12 w-20 border rounded-md cursor-pointer focus:outline-none focus:ring-2 focus:ring-blue-500">
            <input type="text" id="color_primary_text" value="<?php echo $color_primary; ?>"
                class="flex-1 p-3 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="#E53935" pattern="^#[0-9A-Fa-f]{6}$">
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const colorPicker = document.getElementById('color_primary');
        const colorText = document.getElementById('color_primary_text');
        
        if (colorPicker && colorText) {
            // Sync color picker to text input
            colorPicker.addEventListener('input', function(e) {
                colorText.value = e.target.value.toUpperCase();
            });
            
            // Sync text input to color picker
            colorText.addEventListener('input', function(e) {
                const value = e.target.value;
                if (/^#[0-9A-Fa-f]{6}$/.test(value)) {
                    colorPicker.value = value;
                }
            });
        }
    });
    </script>

    <div class="flex justify-end">
        <input type="hidden" name="action" value="<?php echo $action; ?>">
        <button type="submit"
            class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"><?php echo $submit_text; ?></button>
    </div>
</form>
<?php
}

function nds_get_faculty_by_course($faculty_id)
{
    global $wpdb;
    $table_courses = $wpdb->prefix . "nds_courses";
    $table_programs = $wpdb->prefix . "nds_programs";

    // Courses belong to programs, which belong to faculties.
    // So we need to join courses with programs to filter by faculty_id.
    $query = $wpdb->prepare("
        SELECT c.* 
        FROM $table_courses c
        JOIN $table_programs p ON c.program_id = p.id
        WHERE p.faculty_id = %d
    ", $faculty_id);
    
    $facultyCourses = $wpdb->get_results($query);
    $allCourses = nds_get_courses();
    
    if ($facultyCourses) {
        echo '<legend class="text-lg font-semibold text-gray-700">';
        foreach ($facultyCourses as $course) {
            $is_checked = "";
            foreach($allCourses as $acourse):
                if ($course->id === $acourse['id']) {
                    $is_checked = 'checked';
                    break;
                }
            endforeach;
            
            echo '<div class="flex items-center">';
            echo '<input type="checkbox" name="facultyCourses[]" value="' . $course->id . '" '.$is_checked.' class="mr-2 text-blue-500">';
            echo '<label class="text-sm text-gray-700">' . esc_html($course->name) . '</label>';
            echo '</div>';
        }
        echo '</legend>';
    } else {
        echo 'No courses found for this faculty.';
    }
}