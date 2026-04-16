<?php
ob_start();  // Start output buffering (this should be at the very beginning)

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (!defined('OBJECT')) {
    define('OBJECT', 'OBJECT');
}

function redd($url = null, $rayray = null)
{
    // If URL is provided, use it; otherwise use referer or fallback to admin
    if (!empty($url)) {
        // If URL is a relative path (starts with ?), prepend admin URL
        if (strpos($url, '?') === 0) {
            $redirect_url = admin_url('admin.php' . $url);
        } else {
            $redirect_url = $url;
        }
    } else {
        // Use HTTP_REFERER if available, otherwise fallback to admin dashboard
        $referer_url = isset($_SERVER['HTTP_REFERER']) && !empty($_SERVER['HTTP_REFERER']) 
            ? $_SERVER['HTTP_REFERER'] 
            : admin_url('admin.php?page=nds-faculties');
        $redirect_url = $referer_url;
    }

    // Append query parameters if provided
    if (!empty($rayray) && is_array($rayray)) {
        $redirect_url = add_query_arg($rayray, $redirect_url);
    }

    // Ensure we have a valid URL before redirecting
    if (empty($redirect_url)) {
        $redirect_url = admin_url('admin.php?page=nds-faculties');
    }

    // Perform the redirect
    wp_redirect($redirect_url);
    exit;
}
// Function to handle deletion of a faculty (formerly education path)
function nds_delete_education_path($path_id)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'nds_faculties';
    $programs_table = $wpdb->prefix . 'nds_programs';
    $redirect_url = admin_url('admin.php?page=nds-faculties');

    $link = "nds-school";

    if (!isset($link)) {
        $link = "nds-education-paths";
    }

    $path_id = intval($path_id);

    if ($path_id > 0) {
        $path = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $path_id));

        if ($path) {
            // Delete all programs under this faculty first to satisfy FK constraints.
            // Courses and related records are removed by their own cascades.
            $programs = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, name, page_id, category_id FROM {$programs_table} WHERE faculty_id = %d",
                    $path_id
                )
            );

            if (!empty($programs)) {
                foreach ($programs as $program) {
                    if (!empty($program->page_id)) {
                        wp_delete_post((int) $program->page_id, true);
                    }

                    if (!empty($program->category_id)) {
                        wp_delete_term((int) $program->category_id, 'category');
                    } elseif (!empty($program->name)) {
                        $category = get_term_by('name', $program->name, 'category', OBJECT, 'slug');
                        if ($category) {
                            wp_delete_term($category->term_id, 'category');
                        }
                    }

                    $deleted_program = $wpdb->delete($programs_table, ['id' => (int) $program->id], ['%d']);
                    if ($deleted_program === false) {
                        wp_redirect(add_query_arg('error', 'delete_failed_programs', $redirect_url));
                        exit;
                    }
                }
            }

            // Delete the WordPress page associated with this faculty.
            if (!empty($path->page_id)) {
                wp_delete_post((int) $path->page_id, true);
            } else {
                $page = get_page_by_title($path->name, OBJECT, 'page');
                if ($page) {
                    wp_delete_post($page->ID, true);
                }
            }

            // Delete associated category for this faculty if present.
            if (!empty($path->category_id)) {
                wp_delete_term((int) $path->category_id, 'category');
            }

            // Now delete the faculty row.
            $deleted = $wpdb->delete($table_name, ['id' => $path_id], ['%d']);
            if ($deleted === false) {
                wp_redirect(add_query_arg('error', 'delete_failed_faculty', $redirect_url));
                exit;
            }

            wp_redirect(admin_url('admin.php?page=nds-faculties&deleted=1'));
            exit;
        } else {
            wp_redirect(add_query_arg('error', 'not_found', $redirect_url));
            exit;
        }
    } else {
        wp_redirect(add_query_arg('error', 'invalid_id', $redirect_url));
        exit;
    }
    ob_end_flush(); // Flush the output buffer (send it to the browser)
}


add_action('admin_post_nds_update_education_path', 'nds_handle_update_education_path');

function nds_handle_update_education_path()
{

    // Check nonce and permissions

    if (!isset($_POST['nds_update_program_nonce']) || !wp_verify_nonce($_POST['nds_update_program_nonce'], 'nds_update_program_nonce')) {
        wp_die('Security check Updated Education failed');
    }
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }


    if (isset($_POST['id']) || isset($_POST['edit_id']) && isset($_POST['path_name'])) {
        $path_id = (isset($_POST['id']))? intval($_POST['id']) : intval($_POST['edit_id']);
        $path_name = sanitize_text_field($_POST['path_name']);
        $path_description = sanitize_text_field($_POST['path_description']);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'nds_faculties';

        // Get the existing path details to retrieve the current name (to update the page name)
        $existing_path = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $path_id));


        if ($existing_path) {
            // Update the path in the database
            $updated = $wpdb->update(
                $table_name,
                array(
                    'name' => $path_name,
                    'description' => $path_description  // Ensure this is the correct column name
                ),
                array('id' => $path_id), // WHERE condition (Must reference the primary key)
                array('%s', '%s'), // Data types for updated values
                array('%d') // Data type for WHERE condition (ID should be an integer)
            );

            if ($updated !== false) {
                // Update the WordPress page associated with this path (if it exists)
                $page = get_page_by_title($existing_path->name, OBJECT, 'page');
                if ($page) {
                    // Update the page title to match the new path name
                    wp_update_post(array(
                        'ID' => $page->ID,
                        'post_title' => $path_name,
                    ));
                }

                // Redirect back to the Faculties list page
                wp_redirect(admin_url('admin.php?page=nds-faculties&updated=1'));
                exit;
            } else {
                // If update fails, show an error with database details
                $error_msg = $wpdb->last_error ? 'Error updating the faculty: ' . esc_html($wpdb->last_error) : 'Error updating the faculty!';
                wp_die($error_msg);
            }
        } else {
            wp_die('The faculty does not exist.');
        }
    } else {
        wp_die('Invalid data received!');
    }
}


// Handle form submission for adding a new faculty
function nds_handle_add_education_path()
{
    // Check nonce and permissions
    // Check for both possible nonce field names for backward compatibility
    $nonce_field = isset($_POST['nds_add_education_path_nonce']) ? 'nds_add_education_path_nonce' : 'nds_nonce';
    $nonce_value = isset($_POST['nds_add_education_path_nonce']) ? $_POST['nds_add_education_path_nonce'] : (isset($_POST['nds_nonce']) ? $_POST['nds_nonce'] : '');
    
    if (empty($nonce_value) || !wp_verify_nonce($nonce_value, 'nds_add_education_path_nonce')) {
        $error_details = [
            'nonce_field' => $nonce_field,
            'nonce_value_present' => !empty($nonce_value),
            'post_keys' => array_keys($_POST),
            'action' => isset($_POST['action']) ? $_POST['action'] : 'not set',
            'referer' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'not set'
        ];
        error_log('NDS Security Check Failed for Add Education Path: ' . print_r($error_details, true));
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            wp_die('Security check Add Education failed. Details: ' . print_r($error_details, true));
        } else {
            wp_die('Security check failed. Please refresh the page and try again.');
        }
    }
    
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    // Get the form values
    global $wpdb;
    $table_name = $wpdb->prefix . 'nds_faculties';
    $path_name  = sanitize_text_field($_POST['path_name']);
    $path_desc  = sanitize_textarea_field($_POST['path_description']);
    
    // Handle color_primary field
    require_once plugin_dir_path(__FILE__) . 'color-palette-generator.php';
    $color_generator = new NDS_ColorPaletteGenerator();
    
    $color_primary = isset($_POST['color_primary']) ? sanitize_text_field($_POST['color_primary']) : '';
    
    // If no color provided, get default based on existing faculty count
    if (empty($color_primary) || !preg_match('/^#[0-9A-Fa-f]{6}$/i', $color_primary)) {
        $faculty_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $color_primary = $color_generator->get_default_faculty_color($faculty_count);
    }
    
    // Ensure uppercase hex format
    $color_primary = strtoupper($color_primary);


    // If editing, check if the path name exists
    if (isset($_POST['edit_id'])) {
        $edit_id = intval($_POST['edit_id']);

        // Check if the path name exists already
        $existing_path = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE name = %s AND id != %d", $path_name, $edit_id));

        if ($existing_path > 0) {
            wp_die('The path name already exists. Please choose a different name.');
        }

        // Update the path in the database
        $wpdb->update(
            $table_name,
            ['name' => $path_name, 'description' => $path_desc, 'color_primary' => $color_primary],
            ['id' => $edit_id],
            ['%s', '%s', '%s'],
            ['%d']
        );

        // Update the associated page and category
        $faculty = $wpdb->get_row($wpdb->prepare("SELECT page_id, category_id FROM $table_name WHERE id = %d", $edit_id));
        if ($faculty) {
            if ($faculty->page_id) {
                wp_update_post([
                    'ID'           => $faculty->page_id,
                    'post_title'   => $path_name,
                    'post_content' => $path_desc,
                ]);
            }
            if ($faculty->category_id) {
                wp_update_term($faculty->category_id, 'category', [
                    'name' => $path_name,
                    'description' => $path_desc,
                ]);
            }
        }

        // Use admin_post redirect (no output before this)
        //wp_redirect(admin_url('admin.php?page=nds-education-paths&success=1'));
        //exit;
    }

    // For adding new path (no edit ID provided)
    // Faculties table now requires a unique, non-null `code` column.
    // Generate a short, unique code based on the name if one does not exist yet.
    $base_code = strtoupper(preg_replace('/[^A-Z0-9]/', '', substr($path_name, 0, 12)));
    if ($base_code === '') {
        $base_code = 'FAC' . time();
    }

    $code = $base_code;
    $suffix = 1;
    while ((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE code = %s", $code)) > 0) {
        $code = $base_code . $suffix;
        $suffix++;
    }

    $inserted = $wpdb->insert(
        $table_name,
        [
            'code'        => $code,
            'name'        => $path_name,
            'description' => $path_desc,
            'color_primary' => $color_primary,
        ],
        ['%s', '%s', '%s', '%s']
    );

    if ($inserted === false) {
        // Surface a clearer error if something goes wrong.
        wp_die('Failed to create faculty: ' . esc_html($wpdb->last_error));
    }

    $faculty_id = $wpdb->insert_id;

    // Create WordPress page for the faculty
    $page_id = wp_insert_post([
        'post_title'   => $path_name,
        'post_content' => $path_desc,
        'post_status'  => 'publish',
        'post_type'    => 'page',
    ]);

    if ($page_id && !is_wp_error($page_id)) {
        // Set the page template
        update_post_meta($page_id, '_wp_page_template', 'education-path-single.php');
    }

    // Create WordPress category for the faculty
    $category = wp_insert_term(
        $path_name,
        'category',
        [
            'description' => $path_desc,
            'slug'        => sanitize_title($path_name . '-faculty'),
        ]
    );

    $category_id = 0;
    if (!is_wp_error($category)) {
        $category_id = $category['term_id'];
    } else {
        // Try to get existing category
        $existing_category = get_term_by('name', $path_name, 'category');
        $category_id = $existing_category ? $existing_category->term_id : 0;
    }

    // Update the faculty record with page_id and category_id (color_primary already set during insert)
    $wpdb->update(
        $table_name,
        [
            'page_id'     => $page_id,
            'category_id' => $category_id,
        ],
        ['id' => $faculty_id],
        ['%d', '%d'],
        ['%d']
    );

    // Redirect back to the Faculties page with success flag
    wp_redirect(admin_url('admin.php?page=nds-faculties&success=' . urlencode('Faculty created successfully.')));
    exit;
}

add_action('admin_post_nds_add_education_path', 'nds_handle_add_education_path');
