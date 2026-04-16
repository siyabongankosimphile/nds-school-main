<?php
global $wpdb;

$path_id = 0;
if (is_page()) {
    // If it's a page, get faculty by page_id
    $current_page_id = get_the_ID();
    $path_table = $wpdb->prefix . 'nds_faculties';
    $path = $wpdb->get_row($wpdb->prepare("SELECT * FROM $path_table WHERE page_id = %d", $current_page_id));
    if ($path) {
        $path_id = $path->id;
    }
} else {
    // Fallback for old URL structure
    $path_id = intval(get_query_var('nds_education_path_id'));
    $path_table = $wpdb->prefix . 'nds_faculties';
    $path = $wpdb->get_row($wpdb->prepare("SELECT * FROM $path_table WHERE id = %d", $path_id));
}

if (!$path) {
    wp_redirect(home_url());
    exit;
}

get_header();
?>

<div class="container" style="width: 100%; margin: 40px 0;">
    <h3><?php echo esc_html($path->name); ?></h3>
    <!-- <p><?php echo esc_html($path->description); ?></p> -->
    <br>
    <hr>

    <div class="split-content">
        <div class="sidepic">
            <div class="sidepic-overlay"></div>
        </div>
        <div class="programs">
            <h4>Programs</h4>
            <br>
            <div class="prog-list">
                <?php
                $programs = $wpdb->get_results($wpdb->prepare("SELECT * FROM $program_table WHERE faculty_id = %d", $path_id));

                if ($programs):
                    foreach ($programs as $program):
                        if ($program->page_id) {
                            $program_url = get_permalink($program->page_id);
                        } else {
                            $program_url = site_url('/programs/' . sanitize_title($program->name) . '-' . $program->id);
                        }
                        echo '<a href="' . esc_url($program_url) . '">' . esc_html($program->name) . '</a>';
                    endforeach;
                else:
                    echo '<p>No programs available under this path yet.</p>';
                endif;
                ?>
            </div>
        </div>
    </div>


</div>

<?php
get_footer();
