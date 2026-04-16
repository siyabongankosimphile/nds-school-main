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
        // 1. Register faculty and create WordPress page
        $wpdb->insert($wpdb->prefix . 'nds_faculties', [
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
            $wpdb->insert($wpdb->prefix . 'nds_programs', [
                'name' => $program_name,
                'description' => $program_name,
                'faculty_id' => $path_id
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
                    <?php educationForm(); ?>
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
                    wp_nonce_field('nds_add_course_nonce', 'nds_add_course_nonce');
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

/**
 * KIT Commons - Reusable UI Components
 */
class KIT_Commons {
    /**
     * Get top-level academic Programs.
     *
     * NOTE: For historical reasons these are stored in the nds_faculties table,
     * but in the current university model they represent Programs such as
     * "Full-time Qualifications", "Part-time Qualifications", etc.
     */
    public static function getFaculties() {
        global $wpdb;
        return $wpdb->get_results("SELECT id, name, description FROM {$wpdb->prefix}nds_faculties ORDER BY name ASC");
    }

    public static function getCoursesByFaculty($faculty_id) {
        global $wpdb;
        $faculty_id = intval($faculty_id);
        if ($faculty_id <= 0) return [];
        return $wpdb->get_results($wpdb->prepare(
            "SELECT c.id, c.name, c.program_id 
             FROM {$wpdb->prefix}nds_courses c
             JOIN {$wpdb->prefix}nds_programs p ON c.program_id = p.id
             WHERE p.faculty_id = %d ORDER BY c.name ASC",
            $faculty_id
        ));
    }
    
    /**
     * Generate a faculty select dropdown
     * 
     * @param string $name Field name
     * @param int $selected_id Currently selected faculty ID
     * @param string $css_classes Additional CSS classes
     * @param bool $include_empty Whether to include an empty option
     * @param string $empty_text Text for empty option
     * @param string $onchange JavaScript onchange event
     * @return void
     */
    public static function FacultySelect($name = 'faculty_id', $selected_id = 0, $css_classes = '', $include_empty = true, $empty_text = 'Select Faculty', $onchange = '') {
        global $wpdb;
        
        // Get all faculties
        $faculties = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}nds_faculties ORDER BY name ASC");
        
        // Build CSS classes
        $classes = 'w-full border border-gray-300 rounded-md px-3 py-2';
        if (!empty($css_classes)) {
            $classes .= ' ' . $css_classes;
        }
        
        // Build onchange attribute
        $onchange_attr = '';
        if (!empty($onchange)) {
            $onchange_attr = ' onchange="' . esc_attr($onchange) . '"';
        }
        
        echo '<select name="' . esc_attr($name) . '" id="' . esc_attr($name) . '" class="' . esc_attr($classes) . '"' . $onchange_attr . '>';
        
        if ($include_empty) {
            echo '<option value="">' . esc_html($empty_text) . '</option>';
        }
        
        if ($faculties) {
            foreach ($faculties as $faculty) {
                $selected = ($selected_id == $faculty->id) ? ' selected' : '';
                echo '<option value="' . intval($faculty->id) . '"' . $selected . '>' . esc_html($faculty->name) . '</option>';
            }
        }
        
        echo '</select>';
    }
    
    /**
     * Generate a course select dropdown based on faculty
     * 
     * @param string $name Field name
     * @param int $selected_id Currently selected course ID
     * @param int $faculty_id Faculty ID to filter courses
     * @param string $css_classes Additional CSS classes
     * @param bool $include_empty Whether to include an empty option
     * @param string $empty_text Text for empty option
     * @return void
     */
    public static function CourseSelect($name = 'course_id', $selected_id = 0, $faculty_id = 0, $css_classes = '', $include_empty = true, $empty_text = 'Select Course') {
        global $wpdb;
        
        // Build CSS classes
        $classes = 'w-full border border-gray-300 rounded-md px-3 py-2';
        if (!empty($css_classes)) {
            $classes .= ' ' . $css_classes;
        }
        
        echo '<select name="' . esc_attr($name) . '" id="' . esc_attr($name) . '" class="' . esc_attr($classes) . '">';
        
        if ($include_empty) {
            echo '<option value="">' . esc_html($empty_text) . '</option>';
        }
        
        // If faculty_id is provided, get courses for that faculty
        if ($faculty_id > 0) {
            $courses = $wpdb->get_results($wpdb->prepare(
                "SELECT c.id, c.name 
                 FROM {$wpdb->prefix}nds_courses c
                 JOIN {$wpdb->prefix}nds_programs p ON c.program_id = p.id
                 WHERE p.faculty_id = %d ORDER BY c.name ASC",
                $faculty_id
            ));
            
            if ($courses) {
                foreach ($courses as $course) {
                    $selected = ($selected_id == $course->id) ? ' selected' : '';
                    echo '<option value="' . intval($course->id) . '"' . $selected . '>' . esc_html($course->name) . '</option>';
                }
            }
        } else {
            echo '<option value="">Select Faculty First</option>';
        }
        
        echo '</select>';
    }
    
    /**
     * Courses screen: Quick Filters card
     *
     * @param array<int, array{id:int, program_name:string}> $programs
     * @return void
     */
    public static function CoursesQuickFilters(array $programs) {
        ?>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-900">Quick Filters</h2>
                <p class="text-xs text-gray-500">Filter courses by program or status</p>
            </div>
            <div class="p-4 space-y-3">
                <select class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="">All Programs</option>
                    <?php foreach ($programs as $program): ?>
                        <option value="<?php echo intval($program['id']); ?>">
                            <?php echo esc_html($program['program_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="draft">Draft</option>
                </select>

                <button type="button" onclick="applyFilters()"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition-all duration-200 shadow-md hover:shadow-lg">
                    Apply Filters
                </button>
            </div>
        </div>
        <?php
    }

    /**
     * Courses screen: Lecturer Assignment card
     *
     * @param array<int, array{id:int,name:string}> $courses
     * @param array<int, array{id:int,first_name:string,last_name:string,role:string}> $staff
     * @return void
     */
    public static function CoursesLecturerAssignment(array $courses, array $staff) {
        ?>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-900">Assign Chef Instructor</h2>
                <p class="text-xs text-gray-500">Assign instructors to courses</p>
            </div>
            <div class="p-4">
                <form id="assignLecturerForm" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Course</label>
                        <select id="assign_course_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                            <option value="">Select Course</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?php echo intval($course['id']); ?>">
                                    <?php echo esc_html($course['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Chef Instructor</label>
                        <select id="assign_lecturer_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                            <option value="">Select Chef Instructor</option>
                            <?php foreach ($staff as $member): ?>
                                <option value="<?php echo intval($member['id']); ?>">
                                    <?php echo esc_html($member['first_name'] . ' ' . $member['last_name'] . ' (' . $member['role'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="button" onclick="assignLecturer()"
                        class="w-full bg-purple-600 hover:bg-purple-700 text-white font-medium py-2 px-4 rounded-lg transition-all duration-200 shadow-md hover:shadow-lg">
                        <i class="fas fa-plus mr-2"></i>Assign Chef Instructor
                    </button>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Courses screen: Quick Actions card
     *
     * @return void
     */
    public static function CoursesQuickActions() {
        ?>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-900">Quick Actions</h2>
                <p class="text-xs text-gray-500">Navigate to related sections</p>
            </div>
            <div class="p-4 space-y-3">
                <a href="<?php echo admin_url('admin.php?page=nds-programs'); ?>"
                    class="w-full inline-flex items-center justify-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-all duration-200 shadow-md hover:shadow-lg">
                    <i class="fas fa-graduation-cap mr-2"></i>Manage Programs
                </a>
                <a href="<?php echo admin_url('admin.php?page=nds-education-paths'); ?>"
                    class="w-full inline-flex items-center justify-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-all duration-200 shadow-md hover:shadow-lg">
                    <i class="fas fa-route mr-2"></i>Manage Paths
                </a>
                <button type="button" onclick="exportCourses()"
                    class="w-full inline-flex items-center justify-center px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-all duration-200 shadow-md hover:shadow-lg">
                    <i class="fas fa-download mr-2"></i>Export Data
                </button>
            </div>
        </div>
        <?php
    }

    /**
     * Render loading overlay component (center-center)
     * Call this once per page, then use JavaScript helpers to show/hide
     * 
     * @param string $message Optional loading message
     * @return void
     */
    public static function loadingCenterCenter($message = 'Loading...') {
        ?>
        <div id="nds-loading-overlay" class="nds-loading-overlay fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center" style="display: none; z-index: 999999 !important;">
            <div class="bg-white rounded-lg shadow-xl p-8 flex flex-col items-center gap-4 min-w-[200px]">
                <div class="nds-spinner w-12 h-12 border-4 border-blue-200 border-t-blue-600 rounded-full animate-spin"></div>
                <p class="text-gray-700 font-medium text-lg" id="nds-loading-message"><?php echo esc_html($message); ?></p>
            </div>
        </div>
        <script>
            (function() {
                // Helper functions to show/hide loading overlay
                window.ndsShowLoading = function(message) {
                    const overlay = document.getElementById('nds-loading-overlay');
                    const messageEl = document.getElementById('nds-loading-message');
                    if (overlay) {
                        if (message && messageEl) {
                            messageEl.textContent = message;
                        }
                        overlay.classList.remove('hidden');
                        overlay.style.display = 'flex';
                        // Disable all buttons and form inputs
                        document.body.style.pointerEvents = 'none';
                        overlay.style.pointerEvents = 'auto';
                    }
                };
                
                window.ndsHideLoading = function() {
                    const overlay = document.getElementById('nds-loading-overlay');
                    if (overlay) {
                        overlay.classList.add('hidden');
                        overlay.style.display = 'none';
                        // Re-enable interactions
                        document.body.style.pointerEvents = '';
                    }
                };
                
                // Auto-hide on form submissions that redirect (optional enhancement)
                document.addEventListener('submit', function(e) {
                    const form = e.target;
                    if (form && form.tagName === 'FORM' && !form.hasAttribute('data-no-loading')) {
                        // Check if form will cause a page reload (not AJAX)
                        const isAjaxForm = form.hasAttribute('data-ajax') || 
                                         form.querySelector('[data-ajax-submit]') ||
                                         form.getAttribute('action')?.includes('admin-ajax.php');
                        
                        if (!isAjaxForm) {
                            window.ndsShowLoading('Processing...');
                        }
                    }
                });
            })();
        </script>
        <style>
            .nds-loading-overlay {
                backdrop-filter: blur(2px);
                z-index: 999999 !important;
            }
            .nds-spinner {
                animation: nds-spin 1s linear infinite;
            }
            @keyframes nds-spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        </style>
        <?php
    }
}

/**
 * Standalone function wrapper for loading component (for easier use)
 * 
 * @param string $message Optional loading message
 * @return void
 */
function nds_loading_center_center($message = 'Loading...') {
    KIT_Commons::loadingCenterCenter($message);
}

/**
 * Generate a random color for programs
 */
function nds_generate_program_color() {
    // Generate a random hue between 0-360
    $hue = rand(0, 360);
    // Use fixed saturation and lightness for consistency
    $saturation = 70; // 70%
    $lightness = 50;  // 50%
    
    return nds_hsl_to_hex($hue, $saturation, $lightness);
}

/**
 * Generate a shade of the program color for courses
 */
function nds_generate_course_color($program_color, $index = 0) {
    // Convert hex to HSL
    list($h, $s, $l) = nds_hex_to_hsl($program_color);
    
    // Vary lightness for different shades
    $lightness_variations = [40, 45, 55, 60, 65]; // Different shades
    $l = $lightness_variations[$index % count($lightness_variations)];
    
    // Slightly vary saturation
    $s = max(50, min(90, $s + rand(-10, 10)));
    
    return nds_hsl_to_hex($h, $s, $l);
}

/**
 * Convert HSL to Hex
 */
function nds_hsl_to_hex($h, $s, $l) {
    $h /= 360;
    $s /= 100;
    $l /= 100;
    
    $c = (1 - abs(2 * $l - 1)) * $s;
    $x = $c * (1 - abs(fmod($h * 6, 2) - 1));
    $m = $l - $c / 2;
    
    if ($h >= 0 && $h < 1/6) {
        $r = $c; $g = $x; $b = 0;
    } elseif ($h >= 1/6 && $h < 2/6) {
        $r = $x; $g = $c; $b = 0;
    } elseif ($h >= 2/6 && $h < 3/6) {
        $r = 0; $g = $c; $b = $x;
    } elseif ($h >= 3/6 && $h < 4/6) {
        $r = 0; $g = $x; $b = $c;
    } elseif ($h >= 4/6 && $h < 5/6) {
        $r = $x; $g = 0; $b = $c;
    } else {
        $r = $c; $g = 0; $b = $x;
    }
    
    $r = round(($r + $m) * 255);
    $g = round(($g + $m) * 255);
    $b = round(($b + $m) * 255);
    
    return sprintf("#%02x%02x%02x", $r, $g, $b);
}

/**
 * Convert Hex to HSL
 */
function nds_hex_to_hsl($hex) {
    $hex = ltrim($hex, '#');
    $r = hexdec(substr($hex, 0, 2)) / 255;
    $g = hexdec(substr($hex, 2, 2)) / 255;
    $b = hexdec(substr($hex, 4, 2)) / 255;
    
    $max = max($r, $g, $b);
    $min = min($r, $g, $b);
    $diff = $max - $min;
    
    $l = ($max + $min) / 2;
    
    if ($diff == 0) {
        $h = $s = 0;
    } else {
        $s = $diff / (1 - abs(2 * $l - 1));
        
        switch ($max) {
            case $r: $h = fmod(($g - $b) / $diff, 6); break;
            case $g: $h = ($b - $r) / $diff + 2; break;
            case $b: $h = ($r - $g) / $diff + 4; break;
        }
        $h /= 6;
    }
    
    return [round($h * 360), round($s * 100), round($l * 100)];
}
