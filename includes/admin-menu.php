<?php
/**
 * NDS Academy Admin Menu - University Schema Structure
 * Clean, hierarchical menu matching the new database schema
 */
if (!defined('ABSPATH')) {
    exit;
}

function nds_school_add_admin_menu() {
    // ============================================================================
    // MAIN MENU
    // ============================================================================
    add_menu_page(
        'NDS Academy',
        'NDS Academy',
        'manage_options',
        'nds-academy',
        'nds_school_dashboard',
        'dashicons-welcome-learn-more',
        6
    );

    // Dashboard
    add_submenu_page(
        'nds-academy',
        'Dashboard',
        'Dashboard',
        'manage_options',
        'nds-academy',
        'nds_school_dashboard'
    );

    // ============================================================================
    // ACADEMIC STRUCTURE (Hierarchical: Faculties → Programs → Qualifications)
    // ============================================================================
    
    // Faculties
    add_submenu_page(
        'nds-academy',
        'Faculties',
        'Faculties',
        'manage_options',
        'nds-faculties',
        'nds_faculties_page'
    );

    // Faculty Edit (hidden - accessed via URL)
    add_submenu_page(
        'nds-academy',
        'Edit Faculty',
        '',
        'manage_options',
        'nds-edit-faculty',
        'nds_edit_faculty_page'
    );

    // Keep old slug for backward compatibility
    add_submenu_page(
        'nds-academy',
        'Programs',
        '',
        'manage_options',
        'nds-education-paths',
        'nds_school_education_paths_page'  // Keep for backward compatibility
    );

    add_submenu_page(
        'nds-academy',
        'Edit Program',
        '',
        'manage_options',
        'nds-edit-education-paths-page',
        'nds_edit_education_paths_page'  // Keep for backward compatibility
    );

    // Programs (can filter by faculty_id)
    add_submenu_page(
        'nds-academy',
        'Programs',
        'Programs',
        'manage_options',
        'nds-programs',
        'nds_programs_page'
    );

    // Program Edit (hidden)
    add_submenu_page(
        'nds-academy',
        'Edit Program',
        '',
        'manage_options',
        'nds-edit-program',
        'nds_edit_program_page'
    );

    // Qualifications (can filter by program_id)
    add_submenu_page(
        'nds-academy',
        'Qualifications',
        'Qualifications',
        'manage_options',
        'nds-courses',
        'nds_courses_page'
    );

    // Qualification Edit & Overview (hidden - accessed via Qualifications page)
    add_submenu_page(
        'nds-academy',
        'Edit Qualification',
        '',
        'manage_options',
        'nds-edit-course',
        'nds_edit_courses_page'
    );

    add_submenu_page(
        'nds-academy',
        'Qualification Overview',
        '',
        'manage_options',
        'nds-course-overview',
        'nds_course_overview_page'
    );

    // Keep old "Add Qualification" for backward compatibility (hidden)
    add_submenu_page(
        'nds-academy',
        'Add Qualification',
        '',
        'manage_options',
        'nds-add-course',
        'nds_add_courses_page'
    );

    // ============================================================================
    // STAFF MANAGEMENT (Simplified)
    // ============================================================================
    add_submenu_page(
        'nds-academy',
        'Staff',
        'Staff',
        'manage_options',
        'nds-staff',
        'nds_staff_dashboard'
    );

    // Keep old slug for backward compatibility
    add_submenu_page(
        'nds-academy',
        'Staff Management',
        '',
        'manage_options',
        'nds-staff-management',
        'nds_staff_dashboard'
    );

    add_submenu_page(
        'nds-academy',
        'Add Staff',
        '',
        'manage_options',
        'nds-add-staff',
        'nds_add_staff_page'
    );

    add_submenu_page(
        'nds-academy',
        'Edit Staff',
        '',
        'manage_options',
        'nds-edit-staff',
        'nds_edit_staff_page'
    );

    // Keep old "Staff Dashboard" for backward compatibility (hidden)
    add_submenu_page(
        'nds-academy',
        'Staff Dashboard',
        '',
        'manage_options',
        'nds-staff-dashboard',
        'nds_staff_dashboard'
    );

    // Lecturer Assignment page (drag-and-drop)
    add_submenu_page(
        'nds-academy',
        'Assign Lecturers',
        'Assign Lecturers',
        'manage_options',
        'nds-assign-lecturers',
        'nds_assign_lecturers_page'
    );

    // ============================================================================
    // STUDENTS (Simplified - was "Learner Management")
    // ============================================================================
    add_submenu_page(
        'nds-academy',
        'Students',
        'Students',
        'manage_options',
        'nds-students',
        'nds_students_dashboard'
    );

    // Keep old slug for backward compatibility
    add_submenu_page(
        'nds-academy',
        'Learner Management',
        '',
        'manage_options',
        'nds-learner-management',
        'nds_students_dashboard'
    );

    add_submenu_page(
        'nds-academy',
        'Add Student',
        '',
        'manage_options',
        'nds-add-student',
        'nds_add_student_page'
    );

    // Keep old slug for backward compatibility
    add_submenu_page(
        'nds-academy',
        'Add Learner',
        '',
        'manage_options',
        'nds-add-learner',
        'nds_add_student_page'
    );

    add_submenu_page(
        'nds-academy',
        'Edit Student',
        '',
        'manage_options',
        'nds-edit-student',
        'nds_edit_student_page'
    );

    // Keep old slug for backward compatibility
    add_submenu_page(
        'nds-academy',
        'Edit Learner',
        '',
        'manage_options',
        'nds-edit-learner',
        'nds_edit_student_page'
    );

    // Learner Dashboard (hidden - accessed via URL)
    add_submenu_page(
        'nds-academy',
        'Learner Dashboard',
        '',
        'manage_options',
        'nds-learner-dashboard',
        'nds_learner_dashboard_page'
    );

    // Student Sub-pages (under Students)
    add_submenu_page(
        'nds-students',
        'All Students',
        'All Students',
        'manage_options',
        'nds-all-students',
        'nds_all_students_page'
    );

    // Keep old slug for backward compatibility
    add_submenu_page(
        'nds-academy',
        'All Learners',
        '',
        'manage_options',
        'nds-all-learners',
        'nds_all_students_page'
    );

    add_submenu_page(
        'nds-students',
        'Enrollments',
        'Enrollments',
        'manage_options',
        'nds-enrollments',
        'nds_enrollments_page'
    );

    // Keep old slug for backward compatibility
    add_submenu_page(
        'nds-academy',
        'Enrollments (Old)',
        '',
        'manage_options',
        'nds-learner-enrollments',
        'nds_enrollments_page'
    );

    add_submenu_page(
        'nds-students',
        'Graduations',
        'Graduations',
        'manage_options',
        'nds-graduations',
        'nds_graduations_page'
    );

    add_submenu_page(
        'nds-students',
        'Alumni',
        'Alumni',
        'manage_options',
        'nds-alumni',
        'nds_alumni_page'
    );

    // Keep old "Students Applications" for backward compatibility (hidden)
    add_submenu_page(
        'nds-academy',
        'Students Applications',
        '',
        'manage_options',
        'nds-students-applications',
        'nds_students_applications_page'
    );

    // Keep old "Learner Dashboard" for backward compatibility (hidden)
    // REMOVED: Duplicate registration - 'nds-learner-dashboard' is already registered above with 'nds_learner_dashboard_page'
    // This was causing the Learner Management page to show on the learner dashboard
    // add_submenu_page(
    //     'nds-academy',
    //     'Learner Dashboard',
    //     '',
    //     'manage_options',
    //     'nds-learner-dashboard',
    //     'nds_students_dashboard'
    // );

    // Keep old "Students Mosaic Grid" for backward compatibility (hidden)
    add_submenu_page(
        'nds-academy',
        'Students Mosaic Grid',
        '',
        'manage_options',
        'nds-students-mosaic-grid',
        'nds_students_mosaic_grid_page'
    );

    // Keep old "Applications" under learner-management for backward compatibility (hidden)
    add_submenu_page(
        'nds-academy',
        'Learner Applications',
        '',
        'manage_options',
        'nds-learner-applications',
        'nds_student_applications_page'
    );

    // Keep old "Assigned Learners" for backward compatibility (hidden)
    add_submenu_page(
        'nds-academy',
        'Assigned Learners',
        '',
        'manage_options',
        'nds-assigned-learners',
        'nds_assigned_students_page'
    );

    // ============================================================================
    // APPLICATIONS
    // ============================================================================
    add_submenu_page(
        'nds-academy',
        'Applications',
        'Applications',
        'manage_options',
        'nds-applications',
        'nds_applicants_dashboard'
    );

    // Keep old slug for backward compatibility
    add_submenu_page(
        'nds-academy',
        'Applicants',
        '',
        'manage_options',
        'nds-applicants',
        'nds_applicants_dashboard'
    );

    // ============================================================================
    // CALENDAR / TIMETABLE
    // ============================================================================
    add_submenu_page(
        'nds-academy',
        'Calendar',
        'Calendar',
        'manage_options',
        'nds-calendar',
        'nds_calendar_page'
    );

    // ============================================================================
    // ROOMS & VENUES
    // ============================================================================
    add_submenu_page(
        'nds-academy',
        'Rooms & Venues',
        'Rooms & Venues',
        'manage_options',
        'nds-rooms',
        'nds_rooms_page'
    );

    add_submenu_page(
        'nds-academy',
        'Add Room',
        '',
        'manage_options',
        'nds-add-room',
        'nds_rooms_page'
    );

    add_submenu_page(
        'nds-academy',
        'Edit Room',
        '',
        'manage_options',
        'nds-edit-room',
        'nds_rooms_page'
    );

    // ============================================================================
    // CONTENT MANAGEMENT (Simplified)
    // ============================================================================
    add_submenu_page(
        'nds-academy',
        'Recipes',
        'Recipes',
        'manage_options',
        'nds-content',
        'nds_recipes_dashboard'
    );

    // Keep old slug for backward compatibility
    add_submenu_page(
        'nds-academy',
        'Recipe Management',
        '',
        'manage_options',
        'nds-content-management',
        'nds_recipes_dashboard'
    );

    add_submenu_page(
        'nds-academy',
        'Recipes',
        '',
        'manage_options',
        'nds-recipes',
        'nds_recipes_dashboard'
    );

    add_submenu_page(
        'nds-academy',
        'Add Recipe',
        '',
        'manage_options',
        'nds-add-recipe',
        'nds_add_recipes_page'
    );

    add_submenu_page(
        'nds-academy',
        'Recipe Details',
        '',
        'manage_options',
        'nds-recipe-details',
        'nds_recipe_details_page'
    );

    // ============================================================================
    // SETTINGS
    // ============================================================================
    add_submenu_page(
        'nds-academy',
        'Settings',
        'Settings',
        'manage_options',
        'nds-settings',
        'nds_settings_page'
    );

    // ============================================================================
    // DATABASE MIGRATION (Temporary - for migration tool)
    // ============================================================================
    add_submenu_page(
        'nds-academy',
        'Migrate DB Schema',
        '',
        'manage_options',
        'nds-migrate-university-schema',
        'nds_migrate_university_schema_page'
    );
}
add_action('admin_menu', 'nds_school_add_admin_menu', 100);

// ============================================================================
// REMOVE EMPTY MENU ITEMS (Hide submenu items with empty titles)
// ============================================================================
function nds_remove_empty_menu_items($parent_file) {
    global $submenu;
    
    if (isset($submenu['nds-academy'])) {
        foreach ($submenu['nds-academy'] as $key => $item) {
            // Remove items with empty menu titles (second element in array)
            if (empty($item[0])) {
                unset($submenu['nds-academy'][$key]);
            }
        }
    }
    
    return $parent_file;
}
add_filter('parent_file', 'nds_remove_empty_menu_items');

// ============================================================================
// FUNCTION ALIASES (For backward compatibility)
// ============================================================================

// Alias for Faculties page
if (!function_exists('nds_faculties_page')) {
    function nds_faculties_page() {
        nds_education_management_page();
    }
}

// Alias for Edit Faculty page
if (!function_exists('nds_edit_faculty_page')) {
    function nds_edit_faculty_page() {
        if (!isset($_GET['action'])) {
            $_GET['action'] = 'edit-path';
        }
        nds_education_management_page();
    }
}

// ============================================================================
// APPLICATION COUNTERS (Menu & Admin Bar)
// ============================================================================

/**
 * Get count of new applications (submitted or under_review)
 */
function nds_get_new_applications_count() {
    global $wpdb;
    
    $count = $wpdb->get_var("
        SELECT COUNT(*) 
        FROM {$wpdb->prefix}nds_applications 
        WHERE status IN ('submitted', 'under_review')
        AND status != 'converted_to_student'
    ");
    
    return (int) $count;
}

/**
 * Add counter badge to Applications menu item
 */
function nds_add_applications_menu_counter() {
    global $submenu;
    
    if (!isset($submenu['nds-academy'])) {
        return;
    }
    
    $count = nds_get_new_applications_count();
    
    foreach ($submenu['nds-academy'] as $key => $item) {
        if (isset($item[2]) && $item[2] === 'nds-applications') {
            if ($count > 0) {
                $submenu['nds-academy'][$key][0] .= ' <span class="awaiting-mod">' . number_format_i18n($count) . '</span>';
            }
            break;
        }
    }
}
add_action('admin_menu', 'nds_add_applications_menu_counter', 999);

/**
 * Add Applications link to admin bar with counter badge
 */
function nds_add_applications_admin_bar_item($wp_admin_bar) {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    $count = nds_get_new_applications_count();
    
    // Build title with badge using WordPress core badge classes
    $badge = '';
    if ($count > 0) {
        // 'ab-label update-plugins' gives a red badge consistent with core
        // Add proper spacing before the badge
        $badge = '&nbsp;<span class="ab-label update-plugins count-' . $count . '">' . number_format_i18n($count) . '</span>';
    }
    
    $wp_admin_bar->add_node([
        'id'    => 'nds-applications',
        'title' => 'Applications' . $badge,
        'href'  => admin_url('admin.php?page=nds-applications'),
        'meta'  => [
            'title' => $count > 0 ? sprintf('View Applications (%d new)', $count) : 'View Applications'
        ]
    ]);
}
add_action('admin_bar_menu', 'nds_add_applications_admin_bar_item', 100);

/**
 * Add CSS to ensure admin bar badge has red color
 */
function nds_applications_admin_bar_styles() {
    if (!is_admin_bar_showing()) {
        return;
    }
    ?>
    <style type="text/css">
        #wpadminbar #wp-admin-bar-nds-applications .ab-label.update-plugins {
            background: #d63638;
            color: #fff;
            display: inline-block;
            padding: 0 5px;
            min-width: 18px;
            height: 18px;
            border-radius: 9px;
            font-size: 11px;
            line-height: 1.6;
            text-align: center;
            font-weight: 600;
            margin-left: 5px;
        }
    </style>
    <?php
}
add_action('wp_head', 'nds_applications_admin_bar_styles');
add_action('admin_head', 'nds_applications_admin_bar_styles');
