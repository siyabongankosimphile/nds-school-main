<?php
global $wpdb;

$program_id = 0;
if (is_page()) {
    // If it's a page, get program by page_id
    $current_page_id = get_the_ID();
    $program_table = $wpdb->prefix . 'nds_programs';
    $program = $wpdb->get_row($wpdb->prepare("SELECT * FROM $program_table WHERE page_id = %d", $current_page_id));
    if ($program) {
        $program_id = $program->id;
    }
} else {
    // Fallback for old URL structure
    $program_id = intval(get_query_var('nds_program_id'));
    $program_table = $wpdb->prefix . 'nds_programs';
    $program = $wpdb->get_row($wpdb->prepare("SELECT * FROM $program_table WHERE id = %d", $program_id));
}

if (!$program) {
    wp_redirect(home_url());
    exit;
}

// Fetch faculty
$path_table = $wpdb->prefix . 'nds_faculties';
$faculty = $wpdb->get_row($wpdb->prepare("SELECT * FROM $path_table WHERE id = %d", $program->faculty_id));

// Fetch courses under this program
$course_table = $wpdb->prefix . 'nds_courses';
$courses = $wpdb->get_results($wpdb->prepare("SELECT * FROM $course_table WHERE program_id = %d", $program_id));

get_header();
?>

<div class="container-new" style="margin: 40px 0;">
    <h3><?php echo esc_html($program->name); ?></h3><br>

    <!-- <p><?php echo esc_html($program->description); ?></p> -->

    <div class="split-content">
        <div class="sidebar">
            <h5>
                <a href="<?php echo $faculty->page_id ? esc_url(get_permalink($faculty->page_id)) : esc_url(site_url('/academy/' . sanitize_title($faculty->name) . '-' . $faculty->id)); ?>">
                    <?php echo esc_html($faculty->name); ?>
                </a>
            </h5><br>
            
            <div class="prog-list">
                <?php
                $faculty_id = $faculty->id;
                $programs = $wpdb->get_results($wpdb->prepare("SELECT * FROM $program_table WHERE faculty_id = %d", $faculty_id));

                if ($programs):
                    foreach ($programs as $prog):
                        if ($prog->page_id) {
                            $program_url = get_permalink($prog->page_id);
                        } else {
                            $program_url = site_url('/programs/' . sanitize_title($prog->name) . '-' . $prog->id);
                        }
                        echo '<div><a href="' . esc_url($program_url) . '">' . esc_html($prog->name) . '</a></div>';
                    endforeach;
                else:
                    echo '<p>No programs available under this faculty yet.</p>';
                endif;
                ?>
            </div>
            <br><br>
            <div class="prog-prices">
                <h6>&nbsp; Courses Price List</h6><br>
                <ul>
                    <li><a href="http://ndsacademy.co.za/wp-content/uploads/2025/07/NDS-Chefs-Academy-Full-Qualification-Price-List-2025-Rev-2.pdf">Full-Time Qualifications</a></li>
                    <li><a href="http://ndsacademy.co.za/wp-content/uploads/2025/07/NDS-Chefs-Academy-Accredited-courses-including-Learnership-Pricelist-Rev-2-2025.pdf">Part-Time Qualifications</a></li>
                    <li><a href="http://ndsacademy.co.za/wp-content/uploads/2025/06/NDS-Chefs-Academy-Short-Courses-2025-Revision-2.pdf">Short Courses</a></li>
                    <li><a href="http://ndsacademy.co.za/wp-content/uploads/2025/06/NDS-Chefs-Academy-Soft-Skills-for-Employers-Price-List-2025-Revision-2-.pdf">Basic Skills Courses</a></li>
                </ul>
            </div>
            <br>
        </div>
        <div class="content">

            <?php if ($courses): ?>
                <?php foreach ($courses as $course): ?>
                    <div class="coursee">
                        <h5><?php echo esc_html($course->name); ?></h5>
                        <!--<h5 style="margin-bottom: 10px;">NQF Level <?php echo esc_html($course->nqf_level); ?> </h5>-->
                        <!--<p>Enrollment Date: <?php echo esc_html($course->start_date); ?> |-->
                        <!--    Duration: <?php echo esc_html($course->duration); ?> Months |-->
                        <!--    Course Price: <?php echo esc_html($course->currency . ' ' . $course->price); ?></p>-->
                        <!-- <?php echo esc_html($course->description); ?> -->
                        
                        <div><br>
                            <a class="cus-btn" href="https://ndsacademy.co.za/contact/">Enquire</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No courses found under this program.</p>
            <?php endif; ?>
        </div>
    </div>


</div>

<?php get_footer(); ?>