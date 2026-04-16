<?php
function create_essential_pages()
{
    $pages = array(
        array(
            'title' => 'Home',
            'slug' => 'home',
            'template' => 'front-page.php',
            'content' => 'Welcome to our website'
        ),
        array(
            'title' => 'About Us',
            'slug' => 'about-us',
            'template' => 'about-us.php',
            'content' => 'Learn more about our organization'
        ),
        array(
            'title' => 'Academy',
            'slug' => 'academy',
            'template' => 'academy.php',
            'content' => 'Discover our academy programs'
        ),
        array(
            'title' => 'Gallery',
            'slug' => 'gallery',
            'template' => 'gallery.php',
            'content' => 'View our photo gallery'
        ),
        array(
            'title' => 'Prospectus',
            'slug' => 'prospectus',
            'template' => 'prospectus.php',
            'content' => 'Download our prospectus'
        ),
        array(
            'title' => 'Contact Us',
            'slug' => 'contact',
            'template' => 'contact.php',
            'content' => 'Get in touch with us'
        )
    );

    foreach ($pages as $page) {
        // Check if page already exists
        $existing_page = get_page_by_path($page['slug']);

        if (!$existing_page) {
            // Create the page
            $page_id = wp_insert_post(array(
                'post_title' => $page['title'],
                'post_name' => $page['slug'],
                'post_content' => $page['content'],
                'post_status' => 'publish',
                'post_type' => 'page'
            ));

            // Set the page template
            if ($page_id && !empty($page['template'])) {
                update_post_meta($page_id, '_wp_page_template', $page['template']);
            }
        }
    }

    // Set the home page
    $home_page = get_page_by_path('home');
    if ($home_page) {
        update_option('page_on_front', $home_page->ID);
        update_option('show_on_front', 'page');
    }
}

// Hook to run on theme activation
add_action('after_switch_theme', 'create_essential_pages');

// Optional: Add admin notice when pages are created
function pages_created_notice()
{
    if (isset($_GET['pages_created'])) {
        echo '<div class="notice notice-success is-dismissible">';
        echo '<p>Essential pages created successfully!</p>';
        echo '</div>';
    }
}
add_action('admin_notices', 'pages_created_notice');

function register_education_paths() {
    global $wpdb;
    create_essential_pages();

    $paths = array(
        array(
            'education_paths' => 'Full time qualification',
            'programs' => array(
                'Hospitality Management' => array(
                    'courses' => array(
                        'Diploma in Introduction to the Hospitality Industry'
                    )
                ),
                'Food Preparation and Culinary Arts' => array(
                    'courses' => array(
                        'Advanced Diploma in Culinary Arts and Supervision'
                    )
                ),
                'Food and Beverage Service' => array(
                    'courses' => array(
                        'Diploma Food and Beverage Service'
                    )
                ),
                'Housekeeping Service' => array(
                    'courses' => array(
                        'Diploma in Housekeeping Service',
                        'Advanced Diploma in Housekeeping Service'
                    )
                ),
                'Culinary Arts and Supervision' => array(
                    'courses' => array(
                        'Advanced Diploma in Culinary Arts and Supervision'
                    )
                ),
                'Patisserie and Confectionery' => array(
                    'courses' => array(
                        'Diploma Patisserie and Confectionery'
                    )
                ),
                'Barista' => array(
                    'courses' => array(
                        'Advanced Diploma in Barista'
                    )
                )
            )
        ),
        array(
            'education_paths' => 'Part Time Qualification',
            'programs' => array() // No courses added yet
        ),
        array(
            'education_paths' => 'Short Courses',
            'programs' => array(
                'Cooking Courses' => array(
                    'courses' => array(
                        'Basic Cooking Course',
                        'Advance Cooking Course'
                    )
                ),
                'Catering Courses' => array(
                    'courses' => array(
                        'Catering Fundamentals'
                    )
                ),
                'Baking Courses' => array(
                    'courses' => array(
                        'Variety of Baking skills with different toppings',
                        'Basic Baking Course (Variety of doughs)',
                        'Advanced Baking Course',
                        'One day Biscuit Making'
                    )
                ),
                'Confectionary Course' => array(
                    'courses' => array() // No courses added yet
                )
            )
        ),
        array(
            'education_paths' => 'ARPL-Trade Test',
            'programs' => array(
                'Trade test - Artisan Chef' => array(
                    'courses' => array(
                        'Trade test - Artisan Chef'
                    )
                )
            )
        )
    );

    foreach ($paths as $path) {
        // 1. Register education path and create WordPress page
        $wpdb->insert($wpdb->prefix.'nds_education_paths', [
            'name' => $path['education_paths'],
            'description' => $path['education_paths']
        ]);
        $path_id = $wpdb->insert_id;

        $page_id = wp_insert_post([
            'post_title' => $path['education_paths'],
            'post_content' => $path['education_paths'],
            'post_status' => 'publish',
            'post_type' => 'page'
        ]);
        update_post_meta($page_id, '_wp_page_template', 'program-type.php');

        // 2. Register programs and create categories
        foreach ($path['programs'] as $program_name => $program) {
            $wpdb->insert($wpdb->prefix.'nds_programs', [
                'name' => $program_name,
                'description' => $program_name,
                'path_id' => $path_id
            ]);
            $program_id = $wpdb->insert_id;

            $category = wp_insert_term($program_name, 'category');

            // 3. Register courses and create posts
            foreach ($program['courses'] as $course_name) {
                $post_id = wp_insert_post([
                    'post_title' => $course_name,
                    'post_content' => $course_name,
                    'post_status' => 'publish',
                    'post_type' => 'post',
                    'post_category' => [$category['term_id']]
            ]);
                update_post_meta($post_id, '_wp_page_template', 'single-course.php');

                $wpdb->insert($wpdb->prefix.'nds_courses', [
                    'post_id' => $post_id,
                    'program_id' => $program_id,
                    'name' => $course_name,
                    // Add other required course fields here
                ]);
            }
        }
    }
}

// Run only when triggered manually
if (isset($_GET['generate_courses']) && is_admin()) {
    add_action('init', 'register_education_paths');
}

function educationForm($path = null)
{
    $edu_path = $path ?? [];

    ?>
        <label for="path_name" class="block text-sm font-medium text-gray-700">Path Name:</label>
        <input type="text" name="path_name" value="<?php echo ($edu_path->name) ?? ''; ?>" placeholder="Path Name" required class="w-full p-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" />
        <label for="path_description" class="block text-sm font-medium text-gray-700">Description</label>
        <textarea name="path_description" placeholder="Path Description" class="w-full p-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"><?php echo ($edu_path->description) ?? ''; ?></textarea>
    <?php
}

function uni_modal($atts)
{
    $name = sanitize_text_field($atts['gama']);
    $pathID = sanitize_text_field($atts['pathid']);
    ?>
        <button id="openModalBtn" class="px-4 py-2 bg-blue-500 text-white rounded">Add New</button>
        <!-- Modal overlay -->
        <div id="modalOverlay" class="fixed inset-0 bg-black bg-opacity-50 hidden">
            <!-- Modal content -->
            <div id="modal" class="bg-white rounded p-6 max-w-sm mx-auto mt-20 hidden relative">
                <!-- Close button -->
                <span id="closeBtn" class="absolute top-2 right-2 cursor-pointer font-bold">X</span>

                <h1 class="text-2xl font-semibold mb-6">Add Education</h1>
                <!-- Modal content -->
                <form method="POST" action="<?php echo admin_url('admin-post.php'); ?>" class="space-y-4">
                    <?php wp_nonce_field('nds_add_education_path_nonce', 'nds_nonce'); ?>
                    <?php educationForm($path); ?>
                    <input type="submit" name="submit_path" value="Add Path" class="w-full bg-blue-500 text-white py-2 rounded-md hover:bg-blue-600 cursor-pointer" />
                    <input type="hidden" name="action" value="nds_add_education_path" />
                    <!-- Important hidden field -->
                </form>

            </div>
        </div>
    <?php
}
add_shortcode('universalModal', 'uni_modal');

function courseModal($atts)
{

    $name = sanitize_text_field($atts['gama']);
    $pathID = sanitize_text_field($atts['pathid']);
    ?>
        <button id="openModalBtn" class="px-4 py-2 bg-blue-500 text-white rounded">Add New</button>
        <!-- Modal overlay -->
        <div id="modalOverlay" class="fixed inset-0 bg-black bg-opacity-50 hidden">
            <!-- Modal content -->
            <div id="modal" class="bg-white rounded p-6 max-w-[80%] mx-auto mt-20 hidden relative">
                <!-- Close button -->
                <span id="closeBtn" class="absolute top-2 right-2 cursor-pointer font-bold">X</span>

                <h1 class="text-2xl font-semibold mb-6">Add Course</h1>
                <!-- Modal content -->
                <form method="POST" action="<?php echo admin_url('admin-post.php'); ?>">
                    <?php
                    wp_nonce_field('nds_add_course_nonce', 'nds_add_nonce');
                    $typ = "add";
                    course_form($typ, null, $pathID, 'nds-edit-program');
                    ?>
                </form>
            </div>
        </div>
    <?php
}
add_shortcode('universalCourseModal', 'courseModal');

function displayRecipePic($attachment_id)
{
    $image_src = wp_get_attachment_image_src($attachment_id, 'full');
    if ($image_src) {
        echo '<img src="' . $image_src[0] . '" alt="' . $attachment_id . ' " class="inline-block h-10 w-10 rounded-full ring-2 ring-white" />';
    } else {
        echo  'Error displaying image. Please try again.';
    }
}
