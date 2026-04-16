<?php
/**
 * Fired when the plugin is uninstalled.
 * 
 * This file is called when the plugin is deleted from WordPress.
 * It removes all database tables created by the plugin.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit; // Exit if accessed directly.
}

// Load WordPress database functions
require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

function nds_school_uninstall() {
    global $wpdb;

    // Temporarily disable foreign key checks
    $wpdb->query("SET FOREIGN_KEY_CHECKS = 0;");

    // All NDS tables to drop (in order to handle dependencies)
    $tables = [
        // Application related (drop first due to foreign keys)
        $wpdb->prefix . 'nds_application_documents',
        $wpdb->prefix . 'nds_application_reviews',
        $wpdb->prefix . 'nds_application_payments',
        $wpdb->prefix . 'nds_applications',
        $wpdb->prefix . 'nds_application_forms',
        
        // Enrollment and student related
        $wpdb->prefix . 'nds_student_enrollments',
        $wpdb->prefix . 'nds_student_events',
        $wpdb->prefix . 'nds_student_progression',
        $wpdb->prefix . 'nds_student_cohorts',
        $wpdb->prefix . 'nds_cohorts',
        $wpdb->prefix . 'nds_students',
        
        // Course related
        $wpdb->prefix . 'nds_timetable_sessions',
        $wpdb->prefix . 'nds_course_accreditations',
        $wpdb->prefix . 'nds_course_prerequisites',
        $wpdb->prefix . 'nds_course_lecturers',
        $wpdb->prefix . 'nds_course_lecturers',
        $wpdb->prefix . 'nds_course_schedules',
        $wpdb->prefix . 'nds_courses',
        
        // Program related
        $wpdb->prefix . 'nds_program_accreditations',
        $wpdb->prefix . 'nds_program_levels',
        $wpdb->prefix . 'nds_program_levels',
        $wpdb->prefix . 'nds_programs',
        
        // Academic calendar
        $wpdb->prefix . 'nds_schedule_exceptions',
        $wpdb->prefix . 'nds_semesters',
        $wpdb->prefix . 'nds_academic_years',
        
        // Lookup and reference tables
        $wpdb->prefix . 'nds_course_categories',
        $wpdb->prefix . 'nds_program_types_lookup',
        $wpdb->prefix . 'nds_program_types',
        $wpdb->prefix . 'nds_accreditation_bodies',
        $wpdb->prefix . 'nds_faculties',
        
        // Staff and other
        $wpdb->prefix . 'nds_staff',
        $wpdb->prefix . 'nds_recipes',
        $wpdb->prefix . 'nds_hero_carousel',
        $wpdb->prefix . 'nds_trade_tests',
        $wpdb->prefix . 'nds_calendar_events',
        $wpdb->prefix . 'nds_claimed_learners',
        
        // Legacy tables (if they still exist)
        $wpdb->prefix . 'nds_education_paths',
        $wpdb->prefix . 'nds_possible_employment',
        $wpdb->prefix . 'nds_duration_breakdown',
        $wpdb->prefix . 'nds_student_activity_log',
        $wpdb->prefix . 'nds_alumni_profiles',
        $wpdb->prefix . 'nds_graduations',
        $wpdb->prefix . 'nds_student_documents',
    ];

    // Loop through each table and try to drop it
    foreach ( $tables as $table ) {
        $result = $wpdb->query( "DROP TABLE IF EXISTS $table" );
        
        // Check if the table was dropped successfully
        if ( $result !== false ) {
            error_log("NDS Plugin Uninstall: Table $table successfully dropped.");
        } else {
            error_log("NDS Plugin Uninstall: Failed to drop table $table - Error: " . $wpdb->last_error);
        }
    }

    // Re-enable foreign key checks
    $wpdb->query("SET FOREIGN_KEY_CHECKS = 1;");
    
    // Delete plugin options
    delete_option('nds_portal_rules_flushed');
    
    // Clear rewrite rules
    flush_rewrite_rules();
}

// Execute the uninstall function
nds_school_uninstall();