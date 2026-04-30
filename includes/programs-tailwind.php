<?php
// Prevent direct access - this file should only be included by WordPress
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Ensure WordPress functions are available
if (!function_exists('current_user_can')) {
    return;
}

function nds_program_card($program, $option = 1)
{
    if ($option == 1) {
?>

        <div class="program-card bg-white border border-gray-200 rounded-lg p-4">
            <!-- Header Section -->
            <div class="flex items-start justify-between mb-3">
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-8 h-8 bg-gray-100 rounded flex items-center justify-center">
                            <i class="fas fa-graduation-cap text-gray-600 text-sm"></i>
                        </div>
                        <div>
                            <h4 class="text-lg font-semibold text-gray-900">
                                <?php echo esc_html($program['name']); ?>
                            </h4>
                            <div class="flex items-center gap-2 mt-1">
                                <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-gray-100 text-gray-700">
                                    Active
                                </span>
                                <?php if (!empty($program['path_name'])): ?>
                                    <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-gray-100 text-gray-700">
                                        <?php echo esc_html($program['path_name']); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Description -->
            <?php if (!empty($program['description'])): ?>
                <p class="text-gray-600 text-sm mb-3"><?php echo esc_html(substr($program['description'], 0, 100)) . (strlen($program['description']) > 100 ? '...' : ''); ?></p>
            <?php endif; ?>

            <!-- Stats Section -->
            <div class="flex items-center justify-between pt-3 border-t border-gray-100">
                <div class="flex items-center gap-4">
                    <div class="flex items-center text-sm text-gray-600">
                        <i class="fas fa-book mr-1"></i>
                        <span class="font-medium"><?php echo intval($program['course_count']); ?></span>
                        <span class="text-gray-500 ml-1">Courses</span>
                    </div>

                    <div class="flex items-center text-sm text-gray-600">
                        <i class="fas fa-cubes mr-1"></i>
                        <span class="font-medium"><?php echo intval($program['module_count'] ?? 0); ?></span>
                        <span class="text-gray-500 ml-1">Modules</span>
                    </div>

                    <div class="flex items-center text-sm text-gray-600">
                        <i class="fas fa-clock mr-1"></i>
                        <span class="font-medium"><?php echo esc_html(isset($program['duration_months']) ? $program['duration_months'] : '12'); ?></span>
                        <span class="text-gray-500 ml-1">Months</span>
                    </div>

                    <div class="flex items-center text-sm text-gray-600">
                        <i class="fas fa-certificate mr-1"></i>
                        <span class="font-medium"><?php echo esc_html(ucfirst(isset($program['program_type']) ? $program['program_type'] : 'diploma')); ?></span>
                    </div>
                </div>

                <!-- Action Button -->
                <div class="flex items-center gap-2">
                    <a href="<?php echo admin_url('admin.php?page=nds-courses&program_id=' . $program['id']); ?>"
                        class="inline-flex items-center px-3 py-1 text-xs font-medium text-white bg-blue-600 hover:bg-blue-700 rounded transition-colors shadow-sm">
                        <i class="fas fa-eye mr-1"></i>View Qualifications
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=nds-courses&edit_program=' . $program['id']); ?>"
                        class="inline-flex items-center px-3 py-1 text-xs font-medium text-white bg-gray-600 hover:bg-gray-700 rounded transition-colors shadow-sm">
                        <i class="fas fa-edit mr-1"></i>Edit
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=nds-module-management&faculty_id=' . intval($program['faculty_id']) . '&program_id=' . intval($program['id'])); ?>"
                        class="inline-flex items-center px-3 py-1 text-xs font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded transition-colors shadow-sm">
                        <i class="fas fa-cubes mr-1"></i>Manage Modules
                    </a>
                </div>
            </div>
        </div>
    <?php
    }
    if ($option == 2) {
    ?>
        <div class="programCard bg-white rounded-xl shadow-sm border border-gray-100 p-5 hover:shadow-md transition-shadow duration-200 flex flex-col min-h-0">
            <!-- Header Section -->
            <div class="flex items-start justify-between mb-3 flex-shrink-0">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 mb-2 flex-wrap">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-50 text-gray-700 whitespace-nowrap">
                    Active
                </span>
                    <?php if (!empty($program['path_name'])): ?>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-50 text-gray-600 truncate">
                            <?php echo esc_html($program['path_name']); ?>
                        </span>
                    <?php endif; ?>
                </div>
                    <h6 class="text-lg font-semibold text-gray-900 mb-2 truncate"><?php echo esc_html($program['name']); ?></h6>
                        <?php if (!empty($program['description'])): ?>
                            <p class="text-gray-600 text-sm mb-3 line-clamp-2"><?php echo esc_html($program['description']); ?></p>
                        <?php endif; ?>
                    </div>
                    </div>

            <!-- Stats Section -->
            <div class="grid grid-cols-4 gap-2 mb-4 pb-4 border-b border-gray-100 flex-shrink-0">
                    <div class="text-center">
                        <div class="flex items-center justify-center text-sm text-gray-600 mb-1">
                            <span class="dashicons dashicons-book text-purple-600 text-base mr-1"></span>
                            <span class="font-semibold text-gray-900"><?php echo intval($program['course_count']); ?></span>
                        </div>
                        <p class="text-xs text-gray-500">Courses</p>
                    </div>

                    <div class="text-center">
                        <div class="flex items-center justify-center text-sm text-gray-600 mb-1">
                            <span class="dashicons dashicons-screenoptions text-indigo-600 text-base mr-1"></span>
                            <span class="font-semibold text-gray-900"><?php echo intval($program['module_count'] ?? 0); ?></span>
                        </div>
                        <p class="text-xs text-gray-500">Modules</p>
                    </div>

                    <div class="text-center">
                        <div class="flex items-center justify-center text-sm text-gray-600 mb-1">
                            <span class="dashicons dashicons-clock text-blue-600 text-base mr-1"></span>
                            <span class="font-semibold text-gray-900"><?php echo esc_html(isset($program['duration_months']) ? $program['duration_months'] : '12'); ?></span>
                    </div>
                        <p class="text-xs text-gray-500">Months</p>
                </div>

                    <div class="text-center">
                        <div class="flex items-center justify-center text-sm text-gray-600 mb-1">
                            <span class="dashicons dashicons-awards text-emerald-600 text-base mr-1"></span>
                        <span class="font-semibold text-gray-900 truncate"><?php echo esc_html(ucfirst(isset($program['program_type']) ? $program['program_type'] : 'diploma')); ?></span>
                    </div>
                        <p class="text-xs text-gray-500">Type</p>
                </div>
                </div>
                
                <!-- Action Buttons -->
            <div class="flex gap-2 mt-auto pt-2 flex-shrink-0">
                    <button type="button" 
                            onclick="openAddCourseModal(<?php echo $program['id']; ?>, '<?php echo esc_js($program['name']); ?>')"
                        class="flex-1 inline-flex items-center justify-center px-2 py-2 text-xs font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors min-w-0">
                    <span class="dashicons dashicons-plus-alt2 mr-1 text-sm flex-shrink-0"></span>
                    <span class="truncate">Add Course</span>
                    </button>
                    <a href="<?php echo admin_url('admin.php?page=nds-courses&program_id=' . $program['id']); ?>"
                   class="flex-1 inline-flex items-center justify-center px-2 py-2 text-xs font-medium text-white bg-gray-600 hover:bg-gray-700 rounded-lg transition-colors min-w-0">
                    <span class="dashicons dashicons-book mr-1 text-sm flex-shrink-0"></span>
                    <span class="truncate">Manage</span>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=nds-module-management&faculty_id=' . intval($program['faculty_id']) . '&program_id=' . intval($program['id'])); ?>"
                   class="flex-1 inline-flex items-center justify-center px-2 py-2 text-xs font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg transition-colors min-w-0">
                    <span class="dashicons dashicons-screenoptions mr-1 text-sm flex-shrink-0"></span>
                    <span class="truncate">Modules</span>
                    </a>
            </div>
        </div>
    <?php
    }
    if ($option == 3) {
        // Style Three - Premium Card Design
    ?>
        <div class="group relative bg-white rounded-2xl shadow-sm hover:shadow-xl transition-all duration-300 border border-gray-100 overflow-hidden flex flex-col h-full">
            <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-blue-500 to-indigo-600 transform scale-x-0 group-hover:scale-x-100 transition-transform duration-300"></div>
            
            <div class="p-6 flex-1">
                <div class="flex justify-between items-start mb-4">
                    <div class="w-12 h-12 bg-blue-50 rounded-xl flex items-center justify-center text-blue-600 group-hover:bg-blue-600 group-hover:text-white transition-colors duration-300">
                        <i class="fas fa-graduation-cap text-xl"></i>
                    </div>
                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-50 text-emerald-600 border border-emerald-100">
                        Active
                    </span>
                </div>

                <h3 class="text-xl font-bold text-gray-900 mb-2 group-hover:text-blue-600 transition-colors">
                    <?php echo esc_html($program['name']); ?>
                </h3>
                
                <?php if (!empty($program['description'])): ?>
                    <p class="text-gray-500 text-sm mb-6 line-clamp-3 leading-relaxed">
                        <?php echo esc_html($program['description']); ?>
                    </p>
                <?php endif; ?>

                <div class="grid grid-cols-3 gap-4 mb-6">
                    <div class="bg-gray-50 rounded-xl p-3 border border-gray-100 transition-colors group-hover:bg-white group-hover:border-blue-100">
                        <div class="text-xs text-gray-500 mb-1">Qualifications</div>
                        <div class="text-lg font-bold text-gray-900"><?php echo intval($program['course_count']); ?></div>
                    </div>
                    <div class="bg-gray-50 rounded-xl p-3 border border-gray-100 transition-colors group-hover:bg-white group-hover:border-blue-100">
                        <div class="text-xs text-gray-500 mb-1">Modules</div>
                        <div class="text-lg font-bold text-gray-900"><?php echo intval($program['module_count'] ?? 0); ?></div>
                    </div>
                    <div class="bg-gray-50 rounded-xl p-3 border border-gray-100 transition-colors group-hover:bg-white group-hover:border-blue-100">
                        <div class="text-xs text-gray-500 mb-1">Duration</div>
                        <div class="text-lg font-bold text-gray-900"><?php echo esc_html(isset($program['duration_months']) ? $program['duration_months'] : '12'); ?>m</div>
                    </div>
                </div>
            </div>

            <div class="p-6 pt-0 mt-auto flex gap-3">
                <a href="<?php echo admin_url('admin.php?page=nds-courses&program_id=' . $program['id']); ?>"
                   class="flex-[2] inline-flex items-center justify-center gap-2 px-4 py-3 bg-blue-600 text-white rounded-xl text-sm font-bold hover:bg-blue-700 transition-all shadow-md hover:shadow-lg active:scale-95">
                    <i class="fas fa-eye text-xs"></i>
                    View Qualifications
                </a>
                <a href="<?php echo admin_url('admin.php?page=nds-courses&edit_program=' . $program['id']); ?>"
                   class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-3 bg-gray-100 text-gray-700 rounded-xl text-sm font-bold hover:bg-gray-200 transition-all active:scale-95">
                    <i class="fas fa-edit text-xs"></i>
                    Edit
                </a>
                <a href="<?php echo admin_url('admin.php?page=nds-module-management&faculty_id=' . intval($program['faculty_id']) . '&program_id=' . intval($program['id'])); ?>"
                   class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-3 bg-indigo-100 text-indigo-700 rounded-xl text-sm font-bold hover:bg-indigo-200 transition-all active:scale-95">
                    <i class="fas fa-cubes text-xs"></i>
                    Modules
                </a>
            </div>
        </div>
    <?php
    }
}

// Modern Faculties Management with Tailwind CSS (under a Program)
function nds_programs_page_tailwind()
{
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    global $wpdb;

    $table_programs = $wpdb->prefix . 'nds_programs';
    $table_courses = $wpdb->prefix . 'nds_courses';
    $table_paths = $wpdb->prefix . 'nds_faculties';

    // Get program_id (formerly faculty_id) from URL for filtering
    $filter_faculty_id = isset($_GET['faculty_id']) ? intval($_GET['faculty_id']) : 0;

    // Get faculties with course counts (stored in nds_programs but conceptually Faculties)
    $programs = array();
    if ($filter_faculty_id > 0) {
        $programs = $wpdb->get_results($wpdb->prepare("
        SELECT p.*,
               COUNT(DISTINCT c.id) as course_count,
               COUNT(DISTINCT m.id) as module_count,
               ep.name as path_name
        FROM {$table_programs} p
        LEFT JOIN {$table_courses} c ON p.id = c.program_id
           LEFT JOIN {$wpdb->prefix}nds_modules m ON m.course_id = c.id
        LEFT JOIN {$table_paths} ep ON p.faculty_id = ep.id
            WHERE p.faculty_id = %d
        GROUP BY p.id
        ORDER BY p.name
        ", $filter_faculty_id), ARRAY_A);
    } else {
        // Load all programs if no faculty filter
        $programs = $wpdb->get_results("
        SELECT p.*,
               COUNT(DISTINCT c.id) as course_count,
               COUNT(DISTINCT m.id) as module_count,
               ep.name as path_name
        FROM {$table_programs} p
        LEFT JOIN {$table_courses} c ON p.id = c.program_id
           LEFT JOIN {$wpdb->prefix}nds_modules m ON m.course_id = c.id
        LEFT JOIN {$table_paths} ep ON p.faculty_id = ep.id
        GROUP BY p.id
        ORDER BY p.name
        ", ARRAY_A);
    }

    // Get the current program info if filtering by faculty_id
    $current_path = null;
    if ($filter_faculty_id) {
        $current_path = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_paths} WHERE id = %d", $filter_faculty_id), ARRAY_A);
    }

    // Get all programs (stored in nds_faculties) for filter dropdown
    $all_faculties = $wpdb->get_results("SELECT id, name FROM {$table_paths} ORDER BY name", ARRAY_A);

    // Get recent programs
    $recent_programs = $wpdb->get_results("
        SELECT * FROM {$table_programs}
        ORDER BY created_at DESC
        LIMIT 5
    ", ARRAY_A);

    // Statistics
    $total_programs = count($programs);
    $active_programs = $total_programs; // All programs are considered active for now
    
    // Get all courses with their parent faculty name for the modal
    $courses_list = $wpdb->get_results("
        SELECT c.*, p.name as parent_name 
        FROM {$table_courses} c 
        LEFT JOIN {$table_programs} p ON c.program_id = p.id 
        ORDER BY c.name
    ", ARRAY_A);
    $total_courses = count($courses_list);

    // Get all programs (Education Paths) with details for the modal
    $path_data_list = $wpdb->get_results("
        SELECT * FROM {$table_paths} ORDER BY name
    ", ARRAY_A);

    // Get faculty_id from URL for auto-selection
    $selected_faculty_id = isset($_GET['faculty_id']) ? intval($_GET['faculty_id']) : 0;

    ?>
    <div class="nds-tailwind-wrapper bg-gray-50 pb-12" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin-left: -20px; padding-left: 20px; margin-top: -20px;">
        <!-- Header -->
        <div class="bg-white shadow-sm border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center py-6">
                    <div class="flex items-center space-x-4">
                        <div class="w-12 h-12 bg-gradient-to-br from-blue-600 to-purple-600 rounded-xl flex items-center justify-center">
                            <span class="dashicons dashicons-welcome-learn-more text-white text-2xl"></span>
                        </div>
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900">
                                <?php echo $current_path ? esc_html($current_path['name']) : 'Programs Management'; ?>
                            </h1>
                            <p class="text-gray-600">
                                <?php echo $current_path ? 'Programs under this faculty' : 'Manage programs and their associated qualifications'; ?>
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-3">
                <?php if ($current_path): ?>
                            <a href="<?php echo admin_url('admin.php?page=nds-programs'); ?>"
                                class="inline-flex items-center px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium shadow-sm">
                                <span class="dashicons dashicons-arrow-left-alt2 mr-1 text-base"></span>
                                Back to All Programs
                            </a>
                        <?php endif; ?>
                        <?php
                        // Check if faculties exist
                        global $wpdb;
                        $paths_table = $wpdb->prefix . 'nds_faculties';
                        $paths_count = $wpdb->get_var("SELECT COUNT(*) FROM $paths_table");
                        if ($paths_count == 0): ?>
                            <a href="<?php echo admin_url('admin.php?page=nds-faculties'); ?>"
                                class="inline-flex items-center px-4 py-2 rounded-lg bg-yellow-600 hover:bg-yellow-700 text-white text-sm font-medium shadow-sm">
                                <span class="dashicons dashicons-networking mr-1 text-base"></span>
                                Create Faculty First
                            </a>
                <?php else: ?>
                            <a href="#addProgramModal" class="inline-flex items-center px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium shadow-sm">
                                <span class="dashicons dashicons-plus-alt2 mr-1 text-base"></span>
                                Add Program
                            </a>
                <?php endif; ?>
        </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
            <!-- Success/Error Messages -->
            <?php if (isset($_GET['success']) && $_GET['success'] === 'program_created'): ?>
                <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-4 flex items-center">
                    <span class="dashicons dashicons-yes-alt text-emerald-600 mr-3 text-xl"></span>
                    <div>
                        <h3 class="text-sm font-semibold text-emerald-800">Success</h3>
                        <p class="text-sm text-emerald-700">Program created successfully!</p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['error'])): ?>
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 flex items-center">
                    <span class="dashicons dashicons-warning text-red-600 mr-3 text-xl"></span>
                    <div>
                        <h3 class="text-sm font-semibold text-red-800">Error</h3>
                        <p class="text-sm text-red-700">
                    <?php
                    switch ($_GET['error']) {
                        case 'missing_fields':
                            echo 'Please fill in all required fields.';
                            break;
                        case 'program_exists':
                            echo 'A program with this name already exists in the selected faculty.';
                            break;
                        case 'db_error':
                            echo 'Database error occurred. Please try again.';
                            break;
                        default:
                            echo 'An error occurred. Please try again.';
                    }
                    ?>
                        </p>
                </div>
                </div>
                    <?php endif; ?>


            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Total Faculties -->
                <div onclick="openStatModal('faculties')" class="bg-white shadow-sm rounded-xl p-5 border border-gray-100 flex flex-col justify-between hover:bg-gray-50 transition-all duration-200 cursor-pointer group">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500 group-hover:text-gray-700">Total Programs</p>
                            <p class="mt-2 text-2xl font-semibold text-gray-900">
                                <?php echo number_format_i18n($total_programs); ?>
                            </p>
                        </div>
                        <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center">
                            <i class="dashicons dashicons-welcome-learn-more text-blue-600 text-xl"></i>
                        </div>
                    </div>
                </div>

                <!-- Active Faculties -->
                <div onclick="openStatModal('active_faculties')" class="bg-white shadow-sm rounded-xl p-5 border border-gray-100 flex flex-col justify-between hover:bg-gray-50 transition-all duration-200 cursor-pointer group">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500 group-hover:text-gray-700">Active Programs</p>
                            <p class="mt-2 text-2xl font-semibold text-gray-900">
                                <?php echo number_format_i18n($active_programs); ?>
                            </p>
                        </div>
                        <div class="w-10 h-10 rounded-lg bg-emerald-50 flex items-center justify-center">
                            <i class="dashicons dashicons-yes-alt text-emerald-600 text-xl"></i>
                        </div>
                    </div>
                </div>

                <!-- Total Courses -->
                <div onclick="openStatModal('courses')" class="bg-white shadow-sm rounded-xl p-5 border border-gray-100 flex flex-col justify-between hover:bg-gray-50 transition-all duration-200 cursor-pointer group">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500 group-hover:text-gray-700">Total Courses</p>
                            <p class="mt-2 text-2xl font-semibold text-gray-900">
                                <?php echo number_format_i18n($total_courses); ?>
                            </p>
                        </div>
                        <div class="w-10 h-10 rounded-lg bg-purple-50 flex items-center justify-center">
                            <i class="dashicons dashicons-book text-purple-600 text-xl"></i>
                        </div>
                    </div>
                </div>

                <!-- Programs -->
                <div onclick="openStatModal('programs')" class="bg-white shadow-sm rounded-xl p-5 border border-gray-100 flex flex-col justify-between hover:bg-gray-50 transition-all duration-200 cursor-pointer group">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500 group-hover:text-gray-700">Faculties</p>
                            <p class="mt-2 text-2xl font-semibold text-gray-900">
                                <?php echo number_format_i18n(count($all_faculties)); ?>
                            </p>
                        </div>
                        <div class="w-10 h-10 rounded-lg bg-orange-50 flex items-center justify-center">
                            <i class="dashicons dashicons-networking text-orange-600 text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Faculty Filter -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Filter by Faculty</h3>
                        <p class="text-sm text-gray-500">Select a faculty to view only its programs</p>
                    </div>
                    <div class="flex items-center space-x-3">
                        <form method="GET" action="" class="flex items-center space-x-3">
                            <input type="hidden" name="page" value="nds-programs">
                            
                            <!-- Qualification Component Dropdown -->
                            <select name="qualification_component" class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                                <option value="">Qualification Component</option>
                                <option value="academic">Academic</option>
                                <option value="practical">Practical</option>
                                <option value="workplace">Workplace</option>
                            </select>

                            <select name="faculty_id" onchange="this.form.submit()" class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                                <option value="">All Faculties</option>
                                <?php foreach ($all_faculties as $faculty): ?>
                                    <option value="<?php echo esc_attr($faculty['id']); ?>" <?php selected($filter_faculty_id, $faculty['id']); ?>>
                                        <?php echo esc_html($faculty['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Main Content Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                <!-- Faculties List -->
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                            <div>
                                <h2 class="text-sm font-semibold text-gray-900">
                                    <?php echo $current_path ? 'Programs' : 'All Programs'; ?>
                                </h2>
                                <p class="text-xs text-gray-500">Manage and organize your programs</p>
                            </div>
                        </div>

                        <div class="p-6" id="programsContainer">
                            <?php if (empty($programs)): ?>
                                <div class="text-center py-12" id="emptyState">
                                    <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                        <span class="dashicons dashicons-welcome-learn-more text-gray-400 text-3xl"></span>
                                    </div>
                                    <h4 class="text-lg font-semibold text-gray-900 mb-2">No Programs Found</h4>
                                    <p class="text-sm text-gray-500 mb-6">Get started by creating your first program under this faculty.</p>
                                    <div class="flex flex-col sm:flex-row gap-3 justify-center items-center">
                                        <?php if (empty($all_faculties)): ?>
                                            <a href="<?php echo admin_url('admin.php?page=nds-faculties'); ?>"
                                                class="inline-flex items-center px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium transition-colors">
                                                <span class="dashicons dashicons-networking mr-2 text-base"></span>Create Faculty First
                                            </a>
                                        <?php else: ?>
                                            <a href="#addProgramModal" class="inline-flex items-center px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium transition-colors">
                                                <span class="dashicons dashicons-plus-alt2 mr-2 text-base"></span>Add Program
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="overflow-x-auto">
                                    <table id="programsTable" class="min-w-full divide-y divide-gray-200 text-sm">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th scope="col" class="px-4 py-3 text-left font-semibold text-gray-700">Program</th>
                                                <th scope="col" class="px-4 py-3 text-left font-semibold text-gray-700">Faculty</th>
                                                <th scope="col" class="px-4 py-3 text-left font-semibold text-gray-700">Qualifications</th>
                                                <th scope="col" class="px-4 py-3 text-left font-semibold text-gray-700">Modules</th>
                                                <th scope="col" class="px-4 py-3 text-left font-semibold text-gray-700">Duration (months)</th>
                                                <th scope="col" class="px-4 py-3 text-left font-semibold text-gray-700">Type</th>
                                                <th scope="col" class="px-4 py-3 text-right font-semibold text-gray-700">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-100">
                                            <?php foreach ($programs as $program): ?>
                                                <tr class="hover:bg-gray-50">
                                                    <td class="px-4 py-3 align-top">
                                                        <div class="font-medium text-gray-900">
                                                            <?php echo esc_html($program['name']); ?>
                                                        </div>
                                                        <?php if (!empty($program['description'])): ?>
                                                            <div class="mt-1 text-xs text-gray-500 line-clamp-2">
                                                                <?php echo esc_html($program['description']); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-4 py-3 align-top text-gray-700">
                                                        <?php echo esc_html($program['path_name'] ?? '—'); ?>
                                                    </td>
                                                    <td class="px-4 py-3 align-top text-gray-700">
                                                        <?php echo intval($program['course_count']); ?>
                                                    </td>
                                                    <td class="px-4 py-3 align-top text-gray-700">
                                                        <?php echo intval($program['module_count'] ?? 0); ?>
                                                    </td>
                                                    <td class="px-4 py-3 align-top text-gray-700">
                                                        <?php echo esc_html(isset($program['duration_months']) ? $program['duration_months'] : '12'); ?>
                                                    </td>
                                                    <td class="px-4 py-3 align-top text-gray-700">
                                                        <?php echo esc_html(ucfirst(isset($program['program_type']) ? $program['program_type'] : 'diploma')); ?>
                                                    </td>
                                                    <td class="px-4 py-3 align-top text-right">
                                                        <div class="inline-flex items-center gap-2">
                                                            <a href="<?php echo admin_url('admin.php?page=nds-courses&program_id=' . $program['id']); ?>"
                                                               class="inline-flex items-center px-3 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium">
                                                                <i class="fas fa-eye mr-1 text-sm"></i>
                                                                View Qualifications
                                                            </a>
                                                            <button type="button"
                                                                onclick="openAddCourseModal(<?php echo $program['id']; ?>, '<?php echo esc_js($program['name']); ?>')"
                                                                class="inline-flex items-center px-3 py-1.5 rounded-lg bg-gray-600 hover:bg-gray-700 text-white text-xs font-medium">
                                                                <span class="dashicons dashicons-plus-alt2 mr-1 text-sm"></span>
                                                                Add Qualification
                                                            </button>
                                                            <a href="<?php echo admin_url('admin.php?page=nds-module-management&faculty_id=' . intval($program['faculty_id']) . '&program_id=' . intval($program['id'])); ?>"
                                                               class="inline-flex items-center px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium">
                                                                <i class="fas fa-cubes mr-1 text-sm"></i>
                                                                Manage Modules
                                                            </a>
                                                            <button type="button"
                                                                onclick="confirmDelete(<?php echo $program['id']; ?>, '<?php echo esc_js($program['name']); ?>')"
                                                                class="inline-flex items-center px-2 py-1.5 rounded-lg bg-red-50 hover:bg-red-100 text-red-700 text-xs font-medium">
                                                                <span class="dashicons dashicons-trash text-sm"></span>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div class="space-y-6">

                        <!-- Recent Faculties -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                            <div class="px-5 py-4 border-b border-gray-100">
                                <h2 class="text-sm font-semibold text-gray-900">Recent Programs</h2>
                                <p class="text-xs text-gray-500">Latest programs created</p>
                            </div>
                            <div class="p-4">
                                <?php if (empty($recent_programs)): ?>
                                    <p class="text-gray-500 text-sm">No recent faculties</p>
                                <?php else: ?>
                                    <div class="space-y-3">
                                        <?php foreach ($recent_programs as $program): ?>
                                            <div class="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                                                <div class="w-8 h-8 bg-emerald-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                                    <span class="dashicons dashicons-welcome-learn-more text-emerald-600 text-base"></span>
                                                </div>
                                                <div class="flex-1 min-w-0">
                                                    <h5 class="text-sm font-medium text-gray-900 truncate"><?php echo esc_html($program['name']); ?></h5>
                                                    <p class="text-xs text-gray-500"><?php echo date('M j, Y', strtotime($program['created_at'])); ?></p>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                            <div class="px-5 py-4 border-b border-gray-100">
                                <h2 class="text-sm font-semibold text-gray-900">Quick Actions</h2>
                                <p class="text-xs text-gray-500">Navigate to related sections</p>
                            </div>
                            <div class="p-4 space-y-3">
                                <a href="<?php echo admin_url('admin.php?page=nds-courses'); ?>"
                                    class="w-full inline-flex items-center justify-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm font-medium">
                                    <span class="dashicons dashicons-book mr-2 text-base"></span>Go to Course Management
                                </a>
                                <a href="<?php echo admin_url('admin.php?page=nds-faculties'); ?>"
                                    class="w-full inline-flex items-center justify-center px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors text-sm font-medium">
                                    <span class="dashicons dashicons-networking mr-2 text-base"></span>Manage Faculties
                                </a>
                                <button type="button" onclick="exportPrograms()"
                                    class="w-full inline-flex items-center justify-center px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors text-sm font-medium">
                                    <span class="dashicons dashicons-download mr-2 text-base"></span>Export Data
                                </button>
                            </div>
                        </div>

                    </div>

                </div>

            </div>

            <!-- Add Program Modal -->
            <div id="addProgramModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999;" onclick="if(event.target === this) closeModal();">
                <div class="flex items-center justify-center min-h-screen p-4">
                    <div class="bg-white rounded-xl shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto" onclick="event.stopPropagation();">
                        <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                            <div class="flex items-center">
                                <span class="dashicons dashicons-plus-alt2 text-blue-600 mr-3 text-xl"></span>
                                <h2 class="text-xl font-semibold text-gray-900">Add New Program</h2>
                            </div>
                            <button type="button" onclick="closeModal()" class="text-gray-400 hover:text-gray-600 transition-colors p-2 rounded-lg hover:bg-gray-100">
                                <span class="dashicons dashicons-no-alt text-xl"></span>
                                </button>
                            </div>
                        <div class="p-6">

                            <?php
                            // Use the same form template as the edit program page
                            echo program_form(act: 'add', pathID: 0);
                            ?>

                        </div>
                    </div>
                </div>
            </div>

            <!-- Add Course Modal -->
            <div id="addCourseModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden" style="display: none;" onclick="if(event.target === this) closeAddCourseModal();">
                <div class="flex items-center justify-center min-h-screen p-4">
                    <div class="bg-white rounded-xl shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto" onclick="event.stopPropagation();">
                        <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                            <div class="flex items-center min-w-0 flex-1">
                                <span class="dashicons dashicons-plus-alt2 text-blue-600 mr-3 text-xl flex-shrink-0"></span>
                                <div class="min-w-0 flex-1">
                                    <h3 class="text-xl font-semibold text-gray-900">Add Course</h3>
                                    <p class="text-sm text-gray-500 mt-0.5 truncate">to <span id="modal-program-name" class="font-medium text-gray-700"></span></p>
                                </div>
                            </div>
                            <button type="button" onclick="closeAddCourseModal()" class="text-gray-400 hover:text-gray-600 transition-colors p-2 rounded-lg hover:bg-gray-100 flex-shrink-0 ml-3">
                                <span class="dashicons dashicons-no-alt text-xl"></span>
                            </button>
                        </div>
                        <div class="p-6">
                            <form method="POST" action="javascript:void(0);" onsubmit="event.preventDefault(); submitCourseForm(this);">
                                <?php wp_nonce_field('nds_course_nonce', 'nds_course_nonce'); ?>
                                <input type="hidden" name="action" value="nds_create_course_ajax">
                                <?php
                                // Get program ID from the modal trigger - will be set by JavaScript
                                echo course_form(typ: 'add', modal: true);
                                ?>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Hidden Delete Form -->
            <form id="deleteProgramForm" method="POST" action="<?php echo admin_url('admin-post.php'); ?>" style="display: none;">
                <input type="hidden" name="action" value="nds_delete_program">
                <input type="hidden" name="program_id" id="delete_program_id">
                <?php wp_nonce_field('nds_delete_program_nonce', 'nds_delete_program_nonce'); ?>
            </form>

        </div>

        <!-- Custom Styles -->
        <style>
            .line-clamp-2 {
                display: -webkit-box;
                -webkit-line-clamp: 2;
                line-clamp: 2;
                -webkit-box-orient: vertical;
                overflow: hidden;
            }

            .group:hover .group-hover\:opacity-100 {
                opacity: 1;
            }

            .program-card {
                transition: all 0.2s ease-in-out;
            }

            .program-card:hover {
                transform: translateY(-1px);
            }
        </style>

        <!-- Include Auto-Select Helper -->
        <!-- Stat Card Modal (Matching Student Management Style) -->
        <div id="statModal" class="hidden" style="position:fixed; inset:0; z-index:999999; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
            <div style="position:fixed; inset:0; background:rgba(0,0,0,0.5);" onclick="closeStatModal()"></div>
            <div style="position:fixed; inset:0; display:flex; align-items:center; justify-content:center; padding:1rem;">
                <div style="background:#fff; border-radius:1rem; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25); width:100%; max-width:42rem; max-height:80vh; display:flex; flex-direction:column; position:relative;">
                    <!-- Modal Header -->
                    <div id="statModalHeader" style="display:flex; align-items:center; justify-content:space-between; padding:1rem 1.5rem; border-bottom:1px solid #e5e7eb; border-radius:1rem 1rem 0 0;">
                        <div style="display:flex; align-items:center; gap:0.75rem;">
                            <div id="modalIconBg" style="width:2.5rem; height:2.5rem; border-radius:0.5rem; display:flex; align-items:center; justify-content:center;">
                                <i id="modalIcon" style="font-size:1.25rem;"></i>
                            </div>
                            <div>
                                <h3 id="statModalTitle" style="font-size:1.125rem; font-weight:700; color:#111827; margin:0;"></h3>
                                <p id="statModalCount" style="font-size:0.875rem; color:#6b7280; margin:0;"></p>
                            </div>
                        </div>
                        <button onclick="closeStatModal()" style="color:#9ca3af; padding:0.5rem; border-radius:0.5rem; border:none; background:none; cursor:pointer;" onmouseover="this.style.color='#4b5563'; this.style.background='#f3f4f6'" onmouseout="this.style.color='#9ca3af'; this.style.background='none'">
                            <i class="fas fa-times" style="font-size:1.25rem;"></i>
                        </button>
                    </div>
                    <!-- Modal Body (Scrollable) -->
                    <div style="overflow-y:auto; flex:1; padding:0.5rem;">
                        <table style="width:100%; border-collapse:collapse;">
                            <thead style="background:#f9fafb; position:sticky; top:0; z-index:10;">
                                <tr>
                                    <th id="col1Header" style="padding:0.75rem 1rem; text-align:left; font-size:0.75rem; font-weight:500; color:#6b7280; text-transform:uppercase;"></th>
                                    <th id="col2Header" style="padding:0.75rem 1rem; text-align:left; font-size:0.75rem; font-weight:500; color:#6b7280; text-transform:uppercase;"></th>
                                </tr>
                            </thead>
                            <tbody id="statModalBody">
                                <!-- Dynamic Content -->
                            </tbody>
                        </table>
                    </div>
                    <!-- Modal Footer -->
                    <div style="padding:0.75rem 1.5rem; border-top:1px solid #e5e7eb; background:#f9fafb; border-radius:0 0 1rem 1rem; text-align:right;">
                        <button onclick="closeStatModal()" style="padding:0.5rem 1rem; font-size:0.875rem; font-weight:500; color:#374151; background:#fff; border:1px solid #d1d5db; border-radius:0.5rem; cursor:pointer;" onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background='#fff'">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <script>
            const statsData = {
                faculties: <?php echo json_encode($programs); ?>,
                active_faculties: <?php echo json_encode($programs); ?>,
                courses: <?php echo json_encode($courses_list); ?>,
                programs: <?php echo json_encode($path_data_list); ?>
            };

            const modalConfig = {
                faculties: {
                    title: 'Total Programs',
                    col1: 'Program Name',
                    col2: 'Faculty',
                    icon: 'dashicons dashicons-welcome-learn-more',
                    iconColor: '#2563eb',
                    iconBg: '#eff6ff'
                },
                active_faculties: {
                    title: 'Active Programs',
                    col1: 'Program Name',
                    col2: 'Faculty',
                    icon: 'dashicons dashicons-yes-alt',
                    iconColor: '#059669',
                    iconBg: '#ecfdf5'
                },
                courses: {
                    title: 'Total Courses',
                    col1: 'Course Name',
                    col2: 'Program',
                    icon: 'dashicons dashicons-book',
                    iconColor: '#7c3aed',
                    iconBg: '#f5f3ff'
                },
                programs: {
                    title: 'Faculties',
                    col1: 'Faculty Name',
                    col2: 'Description',
                    icon: 'dashicons dashicons-networking',
                    iconColor: '#ea580c',
                    iconBg: '#fff7ed'
                }
            };

            function openStatModal(type) {
                const modal = document.getElementById('statModal');
                const config = modalConfig[type];
                const data = statsData[type];
                
                if (!modal || !config || !data) return;

                // Set Text & Icons
                document.getElementById('statModalTitle').textContent = config.title;
                document.getElementById('statModalCount').textContent = data.length + ' item' + (data.length !== 1 ? 's' : '');
                document.getElementById('col1Header').textContent = config.col1;
                document.getElementById('col2Header').textContent = config.col2;
                
                const modalIcon = document.getElementById('modalIcon');
                const modalIconBg = document.getElementById('modalIconBg');
                
                if (config.icon.startsWith('dashicons')) {
                    modalIcon.className = 'dashicons ' + config.icon.replace('dashicons ', '');
                } else {
                    modalIcon.className = config.icon;
                }
                
                modalIcon.style.color = config.iconColor;
                modalIconBg.style.backgroundColor = config.iconBg;

                // Populate Table
                const tbody = document.getElementById('statModalBody');
                tbody.innerHTML = '';
                
                if (data && data.length > 0) {
                    data.forEach(item => {
                        const row = document.createElement('tr');
                        row.style.cssText = 'border-bottom:1px solid #f3f4f6; transition: background 0.15s;';
                        row.onmouseover = function() { this.style.background = '#f9fafb'; };
                        row.onmouseout = function() { this.style.background = ''; };
                        
                        let name = item.name || 'N/A';
                        let detail = '';
                        let iconClass = 'fas fa-folder';
                        let iconColor = '#10b981';

                        if (type === 'faculties' || type === 'active_faculties') {
                            detail = item.path_name || '—'; // Faculty name
                            iconClass = 'dashicons dashicons-admin-page';
                            iconColor = '#10b981';
                        } else if (type === 'courses') {
                            detail = item.parent_name || '—'; // Program name
                            iconClass = 'dashicons dashicons-welcome-learn-more';
                            iconColor = '#8b5cf6';
                        } else if (type === 'programs') {
                            detail = item.description || 'No description'; // Faculty description
                            iconClass = 'fas fa-graduation-cap';
                            iconColor = '#3b82f6';
                        }

                        row.innerHTML = `
                            <td style="padding:0.75rem 1rem;">
                                <div style="display:flex; align-items:center;">
                                    <div style="width:2rem; height:2rem; background:#f0fdf4; border-radius:50%; display:flex; align-items:center; justify-content:center; margin-right:0.75rem;">
                                        <i class="${iconClass}" style="color:${iconColor}; font-size:0.75rem;"></i>
                                    </div>
                                    <div>
                                        <div style="font-size:0.875rem; font-weight:600; color:#111827;">${name}</div>
                                    </div>
                                </div>
                            </td>
                            <td style="padding:0.75rem 1rem; font-size:0.875rem; color:#6b7280; font-weight:400;">${detail}</td>
                        `;
                        tbody.appendChild(row);
                    });
                } else {
                    const row = document.createElement('tr');
                    row.innerHTML = `<td colspan="2" style="padding:2rem 1rem; text-align:center; color:#9ca3af; font-style:italic;">No items found</td>`;
                    tbody.appendChild(row);
                }

                // Show Modal
                modal.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            }

            function closeStatModal() {
                const modal = document.getElementById('statModal');
                if (modal) {
                    modal.classList.add('hidden');
                    document.body.style.overflow = '';
                }
            }

            function openProgramModal() {
                const modal = document.getElementById('addProgramModal');
                if (modal) {
                    modal.style.display = 'block';
                    document.body.style.overflow = 'hidden';
                }
            }

            function closeModal() {
                const modal = document.getElementById('addProgramModal');
                if (modal) {
                    modal.style.display = 'none';
                    document.body.style.overflow = '';
                }
            }

            function confirmDelete(programId, programName) {
                if (confirm(`Are you sure you want to delete "${programName}"? This will also remove all associated courses.`)) {
                    document.getElementById('delete_program_id').value = programId;
                    document.getElementById('deleteProgramForm').submit();
                }
            }

            function exportPrograms() {
                alert('Export functionality will be implemented soon.');
            }

            // Modal trigger
            document.addEventListener('DOMContentLoaded', function() {
                // Handle links with href="#addProgramModal"
                const modalLinks = document.querySelectorAll('a[href="#addProgramModal"]');
                modalLinks.forEach(link => {
                    link.addEventListener('click', function(e) {
                        e.preventDefault();
                        openProgramModal();
                    });
                });

                // Handle button with id="addProgramButton"
                const addProgramButton = document.getElementById('addProgramButton');
                if (addProgramButton) {
                    addProgramButton.addEventListener('click', function(e) {
                        e.preventDefault();
                        openProgramModal();
                    });
                }

                // AJAX form submission
                const addProgramForm = document.getElementById('addProgramForm');
                if (addProgramForm) {
                    addProgramForm.addEventListener('submit', function(e) {
                        e.preventDefault();
                        
                        const submitBtn = document.getElementById('saveProgramBtn');
                        const saveText = submitBtn.querySelector('.save-text');
                        const loadingText = submitBtn.querySelector('.loading-text');
                        
                        // Show loading state
                        saveText.classList.add('hidden');
                        loadingText.classList.remove('hidden');
                        submitBtn.disabled = true;
                        
                        // Get form data
                        const formData = new FormData(addProgramForm);
                        formData.append('action', 'nds_add_program_ajax');
                        
                        // Submit via AJAX
                        const ajaxUrl = typeof ajaxurl !== 'undefined' ? ajaxurl : '<?php echo admin_url('admin-ajax.php'); ?>';
                        fetch(ajaxUrl, {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Close modal
                                closeModal();
                                
                                // Show success message
                                if (typeof NDSNotification !== 'undefined') {
                                    NDSNotification.success('Program created successfully!');
                                } else {
                                    alert('Program created successfully!');
                                }
                                
                                // Reload programs
                                reloadPrograms(data.data.faculty_id);
                            } else {
                                // Show error message
                                const errorMsg = data.data.message || 'Error creating program';
                                if (typeof NDSNotification !== 'undefined') {
                                    NDSNotification.error(errorMsg);
                                } else {
                                    alert(errorMsg);
                                }
                                
                                // Reset button
                                saveText.classList.remove('hidden');
                                loadingText.classList.add('hidden');
                                submitBtn.disabled = false;
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            if (typeof NDSNotification !== 'undefined') {
                                NDSNotification.error('An error occurred. Please try again.');
                            } else {
                                alert('An error occurred. Please try again.');
                            }
                            
                            // Reset button
                            saveText.classList.remove('hidden');
                            loadingText.classList.add('hidden');
                            submitBtn.disabled = false;
                        });
                    });
                }
            });

            // Function to reload programs via AJAX
            function reloadPrograms(facultyId) {
                const programsContainer = document.getElementById('programsContainer');
                const programsGrid = document.getElementById('programsGrid');
                const programsList = document.getElementById('programsList');
                const emptyState = document.getElementById('emptyState');
                
                if (!programsContainer) {
                    // If container not found, reload the page
                    window.location.reload();
                    return;
                }
                
                // Show loading state
                programsContainer.innerHTML = '<div class="text-center py-12"><i class="fas fa-spinner fa-spin text-4xl text-gray-400"></i><p class="mt-4 text-gray-600">Loading programs...</p></div>';
                
                // Build URL with faculty_id if provided
                const url = new URL(window.location.href);
                if (facultyId) {
                    url.searchParams.set('faculty_id', facultyId);
                } else {
                    url.searchParams.delete('faculty_id');
                }
                
                // Fetch updated page
                fetch(url.toString(), {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => response.text())
                .then(html => {
                    // Create a temporary container to parse the HTML
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = html;
                    
                    // Find the programs container in the new HTML
                    const newContainer = tempDiv.querySelector('#programsContainer');
                    
                    if (newContainer) {
                        // Replace with new content
                        programsContainer.outerHTML = newContainer.outerHTML;
                        
                        // Re-initialize modal triggers for new content
                        const modalLinks = document.querySelectorAll('a[href="#addProgramModal"]');
                        modalLinks.forEach(link => {
                            link.addEventListener('click', function(e) {
                                e.preventDefault();
                                openProgramModal();
                            });
                        });

                        // Re-initialize button trigger
                        const addProgramButton = document.getElementById('addProgramButton');
                        if (addProgramButton) {
                            addProgramButton.addEventListener('click', function(e) {
                                e.preventDefault();
                                openProgramModal();
                            });
                        }
                    } else {
                        // Fallback: reload page
                        window.location.reload();
                    }
                })
                .catch(error => {
                    console.error('Error reloading programs:', error);
                    // Fallback: reload page
                    window.location.reload();
                });
            }

            // Function to show notifications (using SweetAlert2 if available)
            function showNotification(message, type) {
                if (typeof NDSNotification !== 'undefined') {
                    // Use SweetAlert2
                    switch(type) {
                        case 'success':
                            NDSNotification.success(message);
                            break;
                        case 'error':
                            NDSNotification.error(message);
                            break;
                        case 'warning':
                            NDSNotification.warning(message);
                            break;
                        default:
                            NDSNotification.info(message);
                    }
                } else {
                    // Fallback to simple alert
                    alert(message);
                }
            }
        </script>

        <script>
            // Add Course Modal Functions
            function openAddCourseModal(programId, programName) {
                const modal = document.getElementById('addCourseModal');
                if (modal) {
                    // Ensure modal is attached directly to <body> so it centers over full viewport
                    if (modal.parentElement !== document.body) {
                        document.body.appendChild(modal);
                    }

                    // Set program name in modal header
                    const programNameSpan = modal.querySelector('#modal-program-name');
                    if (programNameSpan) {
                        programNameSpan.textContent = programName;
                    }

                    // Set program ID in the form
                    const form = modal.querySelector('form');
                    if (form) {
                        // Remove any existing program_id input
                        const existingInput = form.querySelector('input[name="program_id"]');
                        if (existingInput) {
                            existingInput.remove();
                        }
                        
                        // Hide program selection dropdown if it exists
                        const programSelect = form.querySelector('select[name="program_id"]');
                        if (programSelect) {
                            const selectContainer = programSelect.closest('.flex.flex-col');
                            if (selectContainer) {
                                selectContainer.style.display = 'none';
                            }
                            // Hidden required fields can block form submit silently.
                            programSelect.required = false;
                            programSelect.value = String(programId);
                        }
                        
                        // Add hidden input with program_id
                        const hiddenInput = document.createElement('input');
                        hiddenInput.type = 'hidden';
                        hiddenInput.name = 'program_id';
                        hiddenInput.value = programId;
                        form.appendChild(hiddenInput);
                    }

                    modal.classList.remove('hidden');
                    modal.classList.add('flex', 'items-center', 'justify-center');
                    modal.style.display = 'flex';
                    document.body.style.overflow = 'hidden';
                }
            }

            function closeAddCourseModal() {
                const modal = document.getElementById('addCourseModal');
                if (modal) {
                    modal.classList.add('hidden');
                    modal.classList.remove('flex', 'items-center', 'justify-center');
                    modal.style.display = 'none';
                    document.body.style.overflow = '';
                    
                    // Reset form
                    const form = modal.querySelector('form');
                    if (form) {
                        form.reset();
                        
                        // Remove the hidden program_id input we added
                        const hiddenInput = form.querySelector('input[name="program_id"]');
                        if (hiddenInput) {
                            hiddenInput.remove();
                        }
                        
                        // Show program selection dropdown again
                        const programSelect = form.querySelector('select[name="program_id"]');
                        if (programSelect) {
                            const selectContainer = programSelect.closest('.flex.flex-col');
                            if (selectContainer) {
                                selectContainer.style.display = '';
                            }
                            programSelect.required = true;
                            programSelect.value = '';
                        }
                    }
                    
                    // Clear program name
                    const programNameSpan = modal.querySelector('#modal-program-name');
                    if (programNameSpan) {
                        programNameSpan.textContent = '';
                    }
                }
            }

            // Handle Add Course Form Submission
            document.addEventListener('DOMContentLoaded', function() {
                // The form now uses onsubmit handler directly
            });
        </script>

        <script>
            // Course Form Submission
            function submitCourseForm(form) {
                const formData = new FormData(form);
                const submitBtn = form.querySelector('input[type="submit"]');
                const originalText = submitBtn.value;

                // Force expected AJAX routing fields even if duplicate inputs exist in markup.
                formData.set('action', 'nds_create_course_ajax');
                if (!formData.get('nonce') && formData.get('nds_course_nonce')) {
                    formData.set('nonce', formData.get('nds_course_nonce'));
                }
                
                // Show loading state
                submitBtn.value = 'Creating...';
                 submitBtn.disabled = true;
                
                // Submit via AJAX
                const ajaxUrl = typeof ajaxurl !== 'undefined' ? ajaxurl : '<?php echo admin_url('admin-ajax.php'); ?>';
                
                fetch(ajaxUrl, {
                    method: 'POST',
                    body: formData
                })
                .then(async response => {
                    const raw = await response.text();
                    const trimmed = raw.trim();

                    // WordPress returns "0" when AJAX action is not resolved.
                    if (trimmed === '0') {
                        throw new Error('Server returned 0 (AJAX action handler not resolved).');
                    }

                    try {
                        return JSON.parse(trimmed);
                    } catch (e) {
                        // Recover JSON payload from noisy output (PHP notices before/after JSON).
                        const firstBrace = trimmed.indexOf('{');
                        const lastBrace = trimmed.lastIndexOf('}');
                        if (firstBrace !== -1 && lastBrace !== -1 && lastBrace > firstBrace) {
                            const candidate = trimmed.substring(firstBrace, lastBrace + 1);
                            try {
                                return JSON.parse(candidate);
                            } catch (innerErr) {
                                // fall through to detailed error below
                            }
                        }
                        throw new Error('Invalid JSON response: ' + trimmed.substring(0, 300));
                    }
                })
                .then(data => {
                    if (data.success) {
                        // Close modal
                        closeAddCourseModal();
                        
                        // Show success message
                        if (typeof NDSNotification !== 'undefined') {
                            NDSNotification.success('Course created successfully!');
                        } else {
                            alert('Course created successfully!');
                        }
                        
                        // Reload page to show new course
                        setTimeout(() => {
                            window.location.reload();
                        }, 500);
                    } else {
                        // Show error message
                        const errorMsg = data.data && data.data.message ? data.data.message : (data.data || 'Error creating course');
                        if (typeof NDSNotification !== 'undefined') {
                            NDSNotification.error(errorMsg);
                        } else {
                            alert('Error: ' + errorMsg);
                        }
                        
                        // Reset button
                        submitBtn.value = originalText;
                        submitBtn.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    const details = error && error.message ? (' ' + error.message) : '';
                    if (typeof NDSNotification !== 'undefined') {
                        NDSNotification.error('An error occurred. Please try again.' + details);
                    } else {
                        alert('An error occurred. Please try again.' + details);
                    }
                    
                    // Reset button
                    submitBtn.value = originalText;
                    submitBtn.disabled = false;
                });
            }
        </script>

        <script>
            // Program Form Submission
            function submitProgramForm(form) {
                const formData = new FormData(form);
                const submitBtn = form.querySelector('input[type="submit"]');
                const originalText = submitBtn.value;
                
                // Show loading state
                submitBtn.value = 'Saving...';
                submitBtn.disabled = true;
                
                // Submit via AJAX
                const ajaxUrl = typeof ajaxurl !== 'undefined' ? ajaxurl : '<?php echo admin_url('admin-ajax.php'); ?>';
                
                fetch(ajaxUrl, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Close modal
                        closeModal();
                        
                        // Show success message
                        if (typeof NDSNotification !== 'undefined') {
                            NDSNotification.success('Program created successfully!');
                        } else {
                            alert('Program created successfully!');
                        }
                        
                        // Reload page to show new program
                        setTimeout(() => {
                            window.location.reload();
                        }, 500);
                    } else {
                        // Show error message
                        const errorMsg = data.data && data.data.message ? data.data.message : (data.data || 'Error creating program');
                        if (typeof NDSNotification !== 'undefined') {
                            NDSNotification.error(errorMsg);
                        } else {
                            alert('Error: ' + errorMsg);
                        }
                        
                        // Reset button
                        submitBtn.value = originalText;
                        submitBtn.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    if (typeof NDSNotification !== 'undefined') {
                        NDSNotification.error('An error occurred. Please try again.');
                    } else {
                        alert('An error occurred. Please try again.');
                    }
                    
                    // Reset button
                    submitBtn.value = originalText;
                    submitBtn.disabled = false;
                });
            }
        </script>
    <?php
} ?>