<?php
/**
 * Manual Seed File for Gauteng and Free State Learners
 * Includes Claimed Learners table for profile claim management
 */

// Ensure WordPress is loaded when this script is accessed directly.
if (!defined('ABSPATH')) {
    $wp_load_path = dirname(__DIR__, 4) . '/wp-load.php';
    if (file_exists($wp_load_path)) {
        require_once $wp_load_path;
    } else {
        // Fallback: try one level up (in case directory depth changes slightly)
        $wp_load_fallback = dirname(__DIR__, 3) . '/wp-load.php';
        if (file_exists($wp_load_fallback)) {
            require_once $wp_load_fallback;
        } else {
            die('Unable to locate WordPress bootstrap (wp-load.php).');
        }
    }
}

// #region agent log
// Debug instrumentation for student seed bootstrap
try {
    $nds_debug_log_path = __DIR__ . '/../.cursor/debug.log';
    $nds_debug_entry = array(
        'sessionId'   => 'debug-session',
        'runId'       => 'student-seed',
        'hypothesisId'=> 'H1',
        'location'    => 'includes/student-seed.php:bootstrap',
        'message'     => 'Student seed bootstrap reached',
        'data'        => array(
            'ABSPATH_defined' => defined('ABSPATH'),
            'wp_load_path'    => isset($wp_load_path) ? $wp_load_path : null
        ),
        'timestamp'   => round(microtime(true) * 1000)
    );
    @file_put_contents($nds_debug_log_path, json_encode($nds_debug_entry) . "\n", FILE_APPEND);
} catch (\Throwable $e) {
    // Swallow any logging errors
}
// #endregion

require_once(ABSPATH . 'wp-load.php');
global $wpdb;

// #region agent log
// Confirm core tables and $wpdb are available before proceeding
try {
    global $wpdb;
    $nds_debug_log_path = __DIR__ . '/../.cursor/debug.log';
    $nds_debug_entry = array(
        'sessionId'   => 'debug-session',
        'runId'       => 'student-seed',
        'hypothesisId'=> 'H2',
        'location'    => 'includes/student-seed.php:tables_init',
        'message'     => 'Initializing table names for student seed',
        'data'        => array(
            'db_prefix' => isset($wpdb->prefix) ? $wpdb->prefix : null
        ),
        'timestamp'   => round(microtime(true) * 1000)
    );
    @file_put_contents($nds_debug_log_path, json_encode($nds_debug_entry) . "\n", FILE_APPEND);
} catch (\Throwable $e) {
}
// #endregion

// Table names
$t_faculties = $wpdb->prefix . 'nds_faculties';
$t_programs = $wpdb->prefix . 'nds_programs';
$t_courses = $wpdb->prefix . 'nds_courses';
$t_students = $wpdb->prefix . 'nds_students';
$t_claimed_learners = $wpdb->prefix . 'nds_claimed_learners';
$t_academic_years = $wpdb->prefix . 'nds_academic_years';
$t_semesters = $wpdb->prefix . 'nds_semesters';
$t_cohorts = $wpdb->prefix . 'nds_cohorts';
$t_student_cohorts = $wpdb->prefix . 'nds_student_cohorts';
$t_student_enrollments = $wpdb->prefix . 'nds_student_enrollments';

echo "<pre>";

// Helper function to generate claim token
function generate_claim_token() {
    return bin2hex(random_bytes(32));
}

// 1. Create Faculty if not exists
echo "Checking Faculty...\n";
$faculty_id = $wpdb->get_var($wpdb->prepare(
    "SELECT id FROM $t_faculties WHERE code = %s",
    'HOSPITALITY'
));

if (!$faculty_id) {
    $wpdb->insert($t_faculties, [
        'code' => 'HOSPITALITY',
        'name' => 'Hospitality Faculty',
        'short_name' => 'Hospitality',
        'description' => 'Faculty for Hospitality and Culinary Arts Programs',
        'status' => 'active',
        'created_at' => current_time('mysql')
    ]);
    $faculty_id = $wpdb->insert_id;
    echo "Created Faculty ID: $faculty_id\n";
} else {
    echo "Faculty exists with ID: $faculty_id\n";
}

// 2. Create Program if not exists (Artisan Chef under Hospitality Faculty)
echo "\nChecking Program...\n";
$program_id = $wpdb->get_var($wpdb->prepare(
    "SELECT id FROM $t_programs WHERE code = %s",
    'OCHEF'
));

if (!$program_id) {
    // Get program type ID for 'diploma' (create if missing)
    $program_types_table = $wpdb->prefix . 'nds_program_types';

    // #region agent log
    try {
        $nds_debug_log_path = __DIR__ . '/../.cursor/debug.log';
        $nds_debug_entry = array(
            'sessionId'   => 'debug-session',
            'runId'       => 'seed-run',
            'hypothesisId'=> 'P1',
            'location'    => 'student-seed.php:program_type_lookup',
            'message'     => 'Looking up diploma program type',
            'data'        => array(),
            'timestamp'   => round(microtime(true) * 1000)
        );
        @file_put_contents($nds_debug_log_path, json_encode($nds_debug_entry) . "\n", FILE_APPEND);
    } catch (\Throwable $e) {
    }
    // #endregion

    $program_type_id = $wpdb->get_var("SELECT id FROM {$program_types_table} WHERE code = 'diploma'");

    if (!$program_type_id) {
        // Create a minimal 'diploma' program type so this seed can proceed
        $insert_pt_result = $wpdb->insert(
            $program_types_table,
            array(
                'code'                   => 'diploma',
                'name'                   => 'Diploma',
                'typical_duration_years' => 1,
                'level'                  => 'undergraduate',
            ),
            array('%s','%s','%d','%s')
        );

        // #region agent log
        try {
            $nds_debug_log_path = __DIR__ . '/../.cursor/debug.log';
            $nds_debug_entry = array(
                'sessionId'   => 'debug-session',
                'runId'       => 'seed-run',
                'hypothesisId'=> 'P1',
                'location'    => 'student-seed.php:program_type_insert',
                'message'     => 'Inserted diploma program type',
                'data'        => array(
                    'insert_result' => $insert_pt_result,
                    'insert_id'     => $wpdb->insert_id,
                    'last_error'    => $wpdb->last_error,
                ),
                'timestamp'   => round(microtime(true) * 1000)
            );
            @file_put_contents($nds_debug_log_path, json_encode($nds_debug_entry) . "\n", FILE_APPEND);
        } catch (\Throwable $e) {
        }
        // #endregion

        if ($insert_pt_result === false) {
            die("ERROR: Failed to create 'diploma' program type: " . $wpdb->last_error . "\n");
        }

        $program_type_id = (int) $wpdb->insert_id;
    }

    if (!$program_type_id) {
        die("ERROR: program_type_id is still invalid after insert. Cannot create program.\n");
    }

    $insert_program_result = $wpdb->insert($t_programs, [
        'faculty_id'       => $faculty_id,
        'program_type_id'  => $program_type_id,
        'code'             => 'OCHEF',
        'name'             => 'Artisan Chef Program',
        'short_name'       => 'Artisan Chef Diploma',
        'description'      => 'Artisan Chef Diploma Program',
        'nqf_level'        => 5,
        'total_credits'    => 120,
        'duration_years'   => 1,
        'duration_months'  => 12,
        'status'           => 'active',
        'intake_periods'   => json_encode(['January', 'June', 'September']),
        'created_at'       => current_time('mysql')
    ]);

    // #region agent log
    try {
        $nds_debug_log_path = __DIR__ . '/../.cursor/debug.log';
        $nds_debug_entry = array(
            'sessionId'   => 'debug-session',
            'runId'       => 'seed-run',
            'hypothesisId'=> 'P1',
            'location'    => 'student-seed.php:program_insert',
            'message'     => 'Inserted Artisan Chef program',
            'data'        => array(
                'insert_result'   => $insert_program_result,
                'insert_id'       => $wpdb->insert_id,
                'faculty_id'      => $faculty_id,
                'program_type_id' => $program_type_id,
                'last_error'      => $wpdb->last_error,
            ),
            'timestamp'   => round(microtime(true) * 1000)
        );
        @file_put_contents($nds_debug_log_path, json_encode($nds_debug_entry) . "\n", FILE_APPEND);
    } catch (\Throwable $e) {
    }
    // #endregion

    if ($insert_program_result === false) {
        die("ERROR: Failed to create program OCHEF: " . $wpdb->last_error . "\n");
    }

    $program_id = (int) $wpdb->insert_id;
    echo "Created Program ID: $program_id\n";
} else {
    echo "Program exists with ID: $program_id\n";
}

// 3. Create Course if not exists (Artisan Chef course within the Artisan Chef Program)
echo "\nChecking Course...\n";
$course_id = $wpdb->get_var($wpdb->prepare(
    "SELECT id FROM $t_courses WHERE code = %s AND program_id = %d",
    'PC101', $program_id
));

if (!$course_id) {
    // Get course category ID
    $category_id = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}nds_course_categories WHERE code = 'core'");
    
    $wpdb->insert($t_courses, [
        'program_id' => $program_id,
        'code' => 'PC101',
        'name' => 'Artisan Chef',
        'short_name' => 'Artisan Chef',
        'description' => 'Artisan Chef course for seeded learners',
        'nqf_level' => 5,
        'credits' => 12,
        'category_id' => $category_id,
        'is_required' => 1,
        'status' => 'active',
        'created_at' => current_time('mysql')
    ]);
    $course_id = $wpdb->insert_id;
    echo "Created Course ID: $course_id\n";
} else {
    echo "Course exists with ID: $course_id\n";
}

// 4. Create Academic Year if not exists and ensure it's active
echo "\nChecking Academic Year...\n";
$academic_year_id = $wpdb->get_var($wpdb->prepare(
    "SELECT id FROM $t_academic_years WHERE year_name = %s",
    '2024'
));

if (!$academic_year_id) {
    // Deactivate all other academic years first
    $wpdb->query("UPDATE $t_academic_years SET is_active = 0");
    
    // #region agent log
    @file_put_contents(__DIR__ . '/../.cursor/debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'seed-run','hypothesisId'=>'A','location'=>'student-seed.php:183','message'=>'Before academic year insert','data'=>['is_active_value'=>1,'table'=>$t_academic_years],'timestamp'=>round(microtime(true)*1000)]) . "\n", FILE_APPEND);
    // #endregion
    
    $insert_result = $wpdb->insert($t_academic_years, [
        'year_name' => '2024',
        'start_date' => '2024-01-01',
        'end_date' => '2024-12-31',
        'is_active' => 1
    ]);
    
    // #region agent log
    @file_put_contents(__DIR__ . '/../.cursor/debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'seed-run','hypothesisId'=>'A','location'=>'student-seed.php:194','message'=>'After academic year insert','data'=>['insert_result'=>$insert_result,'insert_id'=>$wpdb->insert_id,'last_error'=>$wpdb->last_error],'timestamp'=>round(microtime(true)*1000)]) . "\n", FILE_APPEND);
    // #endregion
    
    $academic_year_id = $wpdb->insert_id;
    
    // #region agent log
    $verify_active = $wpdb->get_var($wpdb->prepare("SELECT is_active FROM $t_academic_years WHERE id = %d", $academic_year_id));
    @file_put_contents(__DIR__ . '/../.cursor/debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'seed-run','hypothesisId'=>'A','location'=>'student-seed.php:200','message'=>'Verify academic year is_active','data'=>['academic_year_id'=>$academic_year_id,'is_active_in_db'=>$verify_active,'is_active_type'=>gettype($verify_active)],'timestamp'=>round(microtime(true)*1000)]) . "\n", FILE_APPEND);
    // #endregion
    
    echo "Created Academic Year ID: $academic_year_id\n";
} else {
    // Ensure this year is active and deactivate others
    $wpdb->query("UPDATE $t_academic_years SET is_active = 0");
    
    // #region agent log
    @file_put_contents(__DIR__ . '/../.cursor/debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'seed-run','hypothesisId'=>'A','location'=>'student-seed.php:195','message'=>'Before academic year update','data'=>['academic_year_id'=>$academic_year_id,'is_active_value'=>1],'timestamp'=>round(microtime(true)*1000)]) . "\n", FILE_APPEND);
    // #endregion
    
    $update_result = $wpdb->update(
        $t_academic_years,
        ['is_active' => 1],
        ['id' => $academic_year_id],
        ['%d'],
        ['%d']
    );
    
    // #region agent log
    $verify_active = $wpdb->get_var($wpdb->prepare("SELECT is_active FROM $t_academic_years WHERE id = %d", $academic_year_id));
    @file_put_contents(__DIR__ . '/../.cursor/debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'seed-run','hypothesisId'=>'A','location'=>'student-seed.php:203','message'=>'After academic year update','data'=>['update_result'=>$update_result,'is_active_in_db'=>$verify_active,'last_error'=>$wpdb->last_error],'timestamp'=>round(microtime(true)*1000)]) . "\n", FILE_APPEND);
    // #endregion
    
    echo "Academic Year exists with ID: $academic_year_id (activated)\n";
}

// 5. Create Semester if not exists and ensure it's active
echo "\nChecking Semester...\n";
if (!$academic_year_id || $academic_year_id <= 0) {
    die("ERROR: Invalid academic_year_id: $academic_year_id. Cannot create semester.\n");
}

$semester_id = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT id FROM $t_semesters WHERE academic_year_id = %d AND semester_name = %s",
    $academic_year_id, 'Semester 1'
));

if (!$semester_id || $semester_id <= 0) {
    // Deactivate all other semesters for this year
    $wpdb->query($wpdb->prepare(
        "UPDATE $t_semesters SET is_active = 0 WHERE academic_year_id = %d",
        $academic_year_id
    ));
    
    // Note: wp_nds_semesters does NOT have a semester_number column in the current schema.
    // We only insert columns that actually exist on this installation.
    
    // #region agent log
    @file_put_contents(__DIR__ . '/../.cursor/debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'seed-run','hypothesisId'=>'A','location'=>'student-seed.php:224','message'=>'Before semester insert','data'=>['academic_year_id'=>$academic_year_id,'is_active_value'=>1],'timestamp'=>round(microtime(true)*1000)]) . "\n", FILE_APPEND);
    // #endregion
    
    $insert_result = $wpdb->insert($t_semesters, [
        'academic_year_id' => $academic_year_id,
        'semester_name'    => 'Semester 1',
        'start_date'       => '2024-01-15',
        'end_date'         => '2024-06-15',
        'is_active'        => 1,
    ]);
    
    // #region agent log
    @file_put_contents(__DIR__ . '/../.cursor/debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'seed-run','hypothesisId'=>'A','location'=>'student-seed.php:234','message'=>'After semester insert','data'=>['insert_result'=>$insert_result,'insert_id'=>$wpdb->insert_id,'last_error'=>$wpdb->last_error],'timestamp'=>round(microtime(true)*1000)]) . "\n", FILE_APPEND);
    // #endregion
    
    if ($insert_result === false) {
        die("ERROR: Failed to create semester. Database error: " . $wpdb->last_error . "\n");
    }
    
    $semester_id = (int) $wpdb->insert_id;
    
    if (!$semester_id || $semester_id <= 0) {
        die("ERROR: Semester insert succeeded but insert_id is invalid: $semester_id\n");
    }
    
    // #region agent log
    $verify_semester = $wpdb->get_row($wpdb->prepare("SELECT id, is_active, academic_year_id FROM $t_semesters WHERE id = %d", $semester_id), ARRAY_A);
    @file_put_contents(__DIR__ . '/../.cursor/debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'seed-run','hypothesisId'=>'A','location'=>'student-seed.php:243','message'=>'Verify semester is_active','data'=>['semester_id'=>$semester_id,'semester_data'=>$verify_semester],'timestamp'=>round(microtime(true)*1000)]) . "\n", FILE_APPEND);
    // #endregion
    
    echo "Created Semester ID: $semester_id\n";
} else {
    // Ensure this semester is active and deactivate others for this year
    $wpdb->query($wpdb->prepare(
        "UPDATE $t_semesters SET is_active = 0 WHERE academic_year_id = %d",
        $academic_year_id
    ));
    $wpdb->update(
        $t_semesters,
        ['is_active' => 1],
        ['id' => $semester_id],
        ['%d'],
        ['%d']
    );
    echo "Semester exists with ID: $semester_id (activated)\n";
}

// Validate semester_id before proceeding
if (!$semester_id || $semester_id <= 0) {
    die("ERROR: Invalid semester_id: $semester_id. Cannot proceed with student seeding.\n");
}

// 6. Create Cohorts for each province
echo "\nCreating Cohorts...\n";
$cohorts = [
    'GP' => [
        'code' => 'COH-OCHEF-2024-S1-GP',
        'name' => 'Gauteng Chef Cohort - 2024 - Semester 1',
        'province' => 'Gauteng'
    ],
    'FS' => [
        'code' => 'COH-OCHEF-2024-S1-FS',
        'name' => 'Free State Chef Cohort - 2024 - Semester 1',
        'province' => 'Free State'
    ]
];

// Validate required IDs before creating cohorts
if (!$program_id || $program_id <= 0) {
    die("ERROR: Invalid program_id: $program_id. Cannot create cohorts.\n");
}
if (!$academic_year_id || $academic_year_id <= 0) {
    die("ERROR: Invalid academic_year_id: $academic_year_id. Cannot create cohorts.\n");
}
if (!$semester_id || $semester_id <= 0) {
    die("ERROR: Invalid semester_id: $semester_id. Cannot create cohorts.\n");
}

$cohort_ids = [];
foreach ($cohorts as $code => $cohort_data) {
    $cohort_id = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $t_cohorts WHERE code = %s",
        $cohort_data['code']
    ));
    
    if (!$cohort_id || $cohort_id <= 0) {
        $insert_result = $wpdb->insert($t_cohorts, [
            'program_id' => $program_id,
            'academic_year_id' => $academic_year_id,
            'semester_id' => $semester_id,
            'code' => $cohort_data['code'],
            'name' => $cohort_data['name'],
            'notes' => 'Cohort for ' . $cohort_data['province'] . ' students',
            'status' => 'active',
            'created_at' => current_time('mysql')
        ]);
        
        if ($insert_result === false) {
            die("ERROR: Failed to create cohort {$cohort_data['code']}. Database error: " . $wpdb->last_error . "\n");
        }
        
        $cohort_id = (int) $wpdb->insert_id;
        
        if (!$cohort_id || $cohort_id <= 0) {
            die("ERROR: Cohort insert succeeded but insert_id is invalid: $cohort_id for {$cohort_data['code']}\n");
        }
        
        echo "Created $code Cohort ID: $cohort_id\n";
    } else {
        echo "$code Cohort exists with ID: $cohort_id\n";
    }
    $cohort_ids[$code] = $cohort_id;
}

// Validate cohort IDs before proceeding
if (empty($cohort_ids['GP']) || $cohort_ids['GP'] <= 0) {
    die("ERROR: Invalid GP cohort_id: " . ($cohort_ids['GP'] ?? 'null') . "\n");
}
if (empty($cohort_ids['FS']) || $cohort_ids['FS'] <= 0) {
    die("ERROR: Invalid FS cohort_id: " . ($cohort_ids['FS'] ?? 'null') . "\n");
}

// 7. GAUTENG STUDENTS
echo "\n=== IMPORTING GAUTENG STUDENTS ===\n";
$gauteng_students = [
    [
        'student_number' => 'OCHEF0032G',
        'surname' => 'Bakhetsi',
        'first_name' => 'Princess Rethabile',
        'email' => 'rethabileprincess3@gmail.com',
        'phone' => '0656159454',
        'id_number' => '0506270622083',
        'date_of_birth' => '27/06/2005'
    ],
    [
        'student_number' => 'OCHEF0033G',
        'surname' => 'Gala',
        'first_name' => 'Hlalanathi',
        'email' => 'galahlalanathi@gmail.com',
        'phone' => '0799236877',
        'id_number' => '0605240748083',
        'date_of_birth' => '24/05/2006'
    ],
    [
        'student_number' => 'OCHEF0034G',
        'surname' => 'Gaveni',
        'first_name' => 'Mbali Fortunate',
        'email' => 'mbaligaveni040@gmail.com',
        'phone' => '0718607206',
        'id_number' => '0509270372089',
        'date_of_birth' => '27/09/2005'
    ],
    [
        'student_number' => 'OCHEF0035G',
        'surname' => 'Hlajoane',
        'first_name' => 'Dieketseng',
        'email' => 'dhlajoane03@gmail.com',
        'phone' => '0661335476',
        'id_number' => '0503240703085',
        'date_of_birth' => '24/03/2005'
    ],
    [
        'student_number' => 'OCHEF0036G',
        'surname' => 'Jansen',
        'first_name' => 'Danica Hay-Leigh',
        'email' => 'jansendanica7@gmail.com',
        'phone' => '0767860253',
        'id_number' => '0611300341082',
        'date_of_birth' => '30/11/2006'
    ],
    [
        'student_number' => 'OCHEF0037G',
        'surname' => 'Kaba',
        'first_name' => 'Lebohang Ernest',
        'email' => 'kabalebohang@gmail.com',
        'phone' => '0676958403',
        'id_number' => '0510305717082',
        'date_of_birth' => '30/10/2005'
    ],
    [
        'student_number' => 'OCHEF0038G',
        'surname' => 'Khasi',
        'first_name' => 'Ntombizodwa',
        'email' => 'khasintombizodwa@gmail.com',
        'phone' => '0678340751',
        'id_number' => '0605120692088',
        'date_of_birth' => '12/05/2006'
    ],
    [
        'student_number' => 'OCHEF0039G',
        'surname' => 'Mafa',
        'first_name' => 'Retshidisitswe Romeo',
        'email' => 'romeomafa10@icloud.com',
        'phone' => '0623207310',
        'id_number' => '0003225405087',
        'date_of_birth' => '22/03/2000'
    ],
    [
        'student_number' => 'OCHEF0040G',
        'surname' => 'Maqabe',
        'first_name' => 'Ntsoaki',
        'email' => 'maqabentsoaki9@gmail.com',
        'phone' => '0716317656',
        'id_number' => '0203061107082',
        'date_of_birth' => '06/03/2002'
    ],
    [
        'student_number' => 'OCHEF0041G',
        'surname' => 'Matiwane',
        'first_name' => 'Aphelele',
        'email' => 'aphelelematiwane28@gmail.com',
        'phone' => '0787827037',
        'id_number' => '9806021411088',
        'date_of_birth' => '02/06/1998'
    ],
    [
        'student_number' => 'OCHEF0042G',
        'surname' => 'Mbele',
        'first_name' => 'Zanele Nomasonto',
        'email' => 'zanelembele94@gmail.com',
        'phone' => '0797413115',
        'id_number' => '9406080104086',
        'date_of_birth' => '08/06/1994'
    ],
    [
        'student_number' => 'OCHEF0043G',
        'surname' => 'Mejoana',
        'first_name' => 'Itumeleng',
        'email' => '', // No email in banking details
        'phone' => '', // No phone in banking details
        'id_number' => '9302110280089',
        'date_of_birth' => '11/02/1993'
    ],
    [
        'student_number' => 'OCHEF0044G',
        'surname' => 'Minnie',
        'first_name' => 'Sarah Dineo',
        'email' => 'dineominnie9@gmail.com',
        'phone' => '0685756008',
        'id_number' => '9912311119082',
        'date_of_birth' => '31/12/1999'
    ],
    [
        'student_number' => 'OCHEF0045G',
        'surname' => 'Mkhwanazi',
        'first_name' => 'Olwethu',
        'email' => 'omkhwanazi2111@gmail.com',
        'phone' => '0694386773',
        'id_number' => '0411210935085',
        'date_of_birth' => '21/11/2004'
    ],
    [
        'student_number' => 'OCHEF0046G',
        'surname' => 'Mnguni',
        'first_name' => 'Samukele',
        'email' => 'samkelemnguni22@gmail.com',
        'phone' => '0764580636',
        'id_number' => '0605226381081',
        'date_of_birth' => '22/05/2006'
    ],
    [
        'student_number' => 'OCHEF0047G',
        'surname' => 'Mokhema',
        'first_name' => 'Mapuleng',
        'email' => 'lebohangmokhema62@gmail.com',
        'phone' => '0664162750',
        'id_number' => '0203030624084',
        'date_of_birth' => '03/03/2002'
    ],
    [
        'student_number' => 'OCHEF0048G',
        'surname' => 'Morobe',
        'first_name' => 'Relebohile Motshidisi',
        'email' => 'lebo9020@gmail.com',
        'phone' => '0717689675',
        'id_number' => '0404220305084',
        'date_of_birth' => '22/04/2004'
    ],
    [
        'student_number' => 'OCHEF0049G',
        'surname' => 'Motloenya',
        'first_name' => 'Lerato',
        'email' => 'leratomotloenya13@gmail.com',
        'phone' => '0698612970',
        'id_number' => '0310210586080',
        'date_of_birth' => '21/10/2003'
    ],
    [
        'student_number' => 'OCHEF0050G',
        'surname' => 'Mpembe',
        'first_name' => 'Neo Mankosasane',
        'email' => 'Khosieymakhosazanat@gmail.com',
        'phone' => '0747979358',
        'id_number' => '0701260596088',
        'date_of_birth' => '26/01/2007'
    ],
    [
        'student_number' => 'OCHEF0051G',
        'surname' => 'Mzizi',
        'first_name' => 'Mpho Totius',
        'email' => 'Mphomzizi408@gmail.com',
        'phone' => '0687095751',
        'id_number' => '0605090837085',
        'date_of_birth' => '09/05/2006'
    ],
    [
        'student_number' => 'OCHEF0052G',
        'surname' => 'Ncaphayi',
        'first_name' => 'Somila',
        'email' => '', // No email in banking details
        'phone' => '', // No phone in banking details
        'id_number' => '0410150229087',
        'date_of_birth' => '15/10/2004'
    ],
    [
        'student_number' => 'OCHEF0053G',
        'surname' => 'Nehring',
        'first_name' => 'Stefan Chris',
        'email' => 'sc.nehring@gmail.com',
        'phone' => '0763030549',
        'id_number' => '0604205091084',
        'date_of_birth' => '20/04/2006'
    ],
    [
        'student_number' => 'OCHEF0054G',
        'surname' => 'Netnou',
        'first_name' => 'Andile Shaun',
        'email' => '', // Empty in banking details
        'phone' => '', // Empty in banking details
        'id_number' => '9610245157083',
        'date_of_birth' => '24/10/1996'
    ],
    [
        'student_number' => 'OCHEF0055G',
        'surname' => 'Pule',
        'first_name' => 'Rethabile Princess',
        'email' => 'princessbile111@gmail.com',
        'phone' => '0684066481',
        'id_number' => '0608210120085',
        'date_of_birth' => '21/08/2006'
    ],
    [
        'student_number' => 'OCHEF0056G',
        'surname' => 'Radebe',
        'first_name' => 'Hope Jabulile',
        'email' => 'radebehope300@gmail.com',
        'phone' => '0763037889',
        'id_number' => '9702130671086',
        'date_of_birth' => '13/02/1997'
    ],
    [
        'student_number' => 'OCHEF0057G',
        'surname' => 'Radebe',
        'first_name' => 'Boitumelo',
        'email' => '', // Empty in banking details
        'phone' => '', // Empty in banking details
        'id_number' => '9808215820082',
        'date_of_birth' => '21/08/1998'
    ],
    [
        'student_number' => 'OCHEF0058G',
        'surname' => 'Sobekwa',
        'first_name' => 'Buhle',
        'email' => 'buhlesobekwa@gmail.com',
        'phone' => '0731541138',
        'id_number' => '0503195925089',
        'date_of_birth' => '19/03/2005'
    ],
    [
        'student_number' => 'OCHEF0059G',
        'surname' => 'Thonka',
        'first_name' => 'Karabo',
        'email' => 'thonkakarabo49@gmail.com',
        'phone' => '0634345494',
        'id_number' => '0511301425084',
        'date_of_birth' => '30/11/2005'
    ],
    [
        'student_number' => 'OCHEF0060G',
        'surname' => 'Tsekahali',
        'first_name' => 'Mamello Patience',
        'email' => 'tsekahalimp@gmail.com',
        'phone' => '0735468341',
        'id_number' => '0111040338084',
        'date_of_birth' => '04/11/2001'
    ],
    [
        'student_number' => 'OCHEF0061G',
        'surname' => 'Tsekahali',
        'first_name' => 'Itumeleng Hessie',
        'email' => 'tsekahaliituhe@gmail.com',
        'phone' => '0791552961',
        'id_number' => '9901090208083',
        'date_of_birth' => '09/01/1999'
    ]
];

$gauteng_imported = 0;
$gauteng_claimed_added = 0;
$gauteng_claimed_updated = 0;
foreach ($gauteng_students as $student_data) {
    // Check if student exists
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $t_students WHERE student_number = %s",
        $student_data['student_number']
    ));
    
    $student_id = null;
    
    if (!$existing) {
        // Generate email if empty
        if (empty($student_data['email'])) {
            $email = strtolower($student_data['surname']) . '.' . 
                    strtolower(substr(str_replace(' ', '', $student_data['first_name']), 0, 3)) . 
                    '@student.nds.example';
            $student_data['email'] = $email;
        }
        
        // Insert student
        // Note: wp_nds_students does NOT have a province column; it DOES have date_of_birth.
        $date_of_birth = null;
        if (!empty($student_data['date_of_birth'])) {
            $dob_obj = \DateTime::createFromFormat('d/m/Y', $student_data['date_of_birth']);
            if ($dob_obj) {
                $date_of_birth = $dob_obj->format('Y-m-d');
            }
        }

        // Resolve intake year from academic year row for snapshot fields
        $intake_year_value = null;
        if ($academic_year_id) {
            $intake_year_value = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT year_name FROM $t_academic_years WHERE id = %d",
                $academic_year_id
            ));
        }

        $wpdb->insert($t_students, [
            'student_number'   => $student_data['student_number'],
            'faculty_id'       => $faculty_id,
            'first_name'       => $student_data['first_name'],
            'last_name'        => $student_data['surname'],
            'email'            => $student_data['email'],
            'phone'            => $student_data['phone'],
            'date_of_birth'    => $date_of_birth,
            'gender'           => 'Other',
            'country'          => 'South Africa',
            'status'           => 'active',
            // Snapshot of first intake (truth still lives in enrollments/cohorts)
            'intake_year'      => $intake_year_value,
            'intake_semester'  => 'Semester 1',
            'created_at'       => current_time('mysql')
        ]);
        
        $student_id = (int) $wpdb->insert_id;
        
        if (!$student_id || $student_id <= 0) {
            echo "  ERROR: Failed to get student_id after insert for {$student_data['student_number']}\n";
            continue;
        }
        
        // Validate IDs before inserting
        if (empty($cohort_ids['GP']) || $cohort_ids['GP'] <= 0) {
            echo "  ERROR: Invalid GP cohort_id for {$student_data['student_number']}\n";
            continue;
        }
        if (!$course_id || $course_id <= 0) {
            echo "  ERROR: Invalid course_id for {$student_data['student_number']}\n";
            continue;
        }
        if (!$academic_year_id || $academic_year_id <= 0) {
            echo "  ERROR: Invalid academic_year_id for {$student_data['student_number']}\n";
            continue;
        }
        if (!$semester_id || $semester_id <= 0) {
            echo "  ERROR: Invalid semester_id for {$student_data['student_number']}\n";
            continue;
        }
        
        // Add to cohort
        $cohort_insert = $wpdb->insert($t_student_cohorts, [
            'student_id' => $student_id,
            'cohort_id' => $cohort_ids['GP'],
            'start_date' => current_time('mysql'),
            'status' => 'active',
            'created_at' => current_time('mysql')
        ]);
        
        if ($cohort_insert === false) {
            echo "  ERROR: Failed to add to cohort for {$student_data['student_number']}: " . $wpdb->last_error . "\n";
        }
        
        // Create enrollment
        $enrollment_insert = $wpdb->insert($t_student_enrollments, [
            'student_id' => $student_id,
            'course_id' => $course_id,
            'academic_year_id' => $academic_year_id,
            'semester_id' => $semester_id,
            'enrollment_date' => current_time('mysql'),
            'status' => 'enrolled',
            'delivery_mode' => 'in-person',
            'created_at' => current_time('mysql')
        ]);
        
        if ($enrollment_insert === false) {
            echo "  ERROR: Failed to create enrollment for {$student_data['student_number']}: " . $wpdb->last_error . "\n";
        }
        
        $gauteng_imported++;
        echo "Imported Gauteng: {$student_data['student_number']} - {$student_data['surname']}\n";
    } else {
        // Student already exists - ensure we cast to int to avoid any type issues
        $student_id = (int) $existing;
    }
    
    // Upsert to claimed learners table (insert or update)
    if ($student_id) {
        $claim_token = generate_claim_token();
        $claim_expiry = date('Y-m-d H:i:s', strtotime('+30 days'));
        $claim_link = home_url("/claim-profile?token=" . $claim_token);
        
        $existing_claimed = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $t_claimed_learners WHERE student_number = %s",
            $student_data['student_number']
        ));
        
        if (!$existing_claimed) {
            // Insert new record
            $insert_result = $wpdb->insert($t_claimed_learners, [
                'student_id' => $student_id,
                'student_number' => $student_data['student_number'],
                'id_number' => $student_data['id_number'],
                'surname' => $student_data['surname'],
                'first_name' => $student_data['first_name'],
                'email' => $student_data['email'],
                'phone' => $student_data['phone'],
                'province' => 'Gauteng',
                'claim_token' => $claim_token,
                'claim_link' => $claim_link,
                'claim_expiry' => $claim_expiry,
                'is_claimed' => 0,
                'created_at' => current_time('mysql')
            ]);
            
            if ($insert_result !== false) {
            $gauteng_claimed_added++;
            echo "  Added to claimed learners table\n";
            } else {
                echo "  ERROR: Failed to add to claimed learners table: " . $wpdb->last_error . "\n";
            }
        } else {
            // Update existing record with fresh token and current student_id
            $update_result = $wpdb->update(
                $t_claimed_learners,
                [
                    'student_id' => $student_id,
                    'id_number' => $student_data['id_number'],
                    'surname' => $student_data['surname'],
                    'first_name' => $student_data['first_name'],
                    'email' => $student_data['email'],
                    'phone' => $student_data['phone'],
                    'province' => 'Gauteng',
                    'claim_token' => $claim_token,
                    'claim_link' => $claim_link,
                    'claim_expiry' => $claim_expiry,
                    'is_claimed' => 0, // Reset claim status
                    'updated_at' => current_time('mysql')
                ],
                ['student_number' => $student_data['student_number']],
                ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s'],
                ['%s']
            );
            
            if ($update_result !== false) {
                $gauteng_claimed_updated++;
                echo "  Updated claimed learners table with fresh token\n";
            } else {
                echo "  ERROR: Failed to update claimed learners table: " . $wpdb->last_error . "\n";
            }
        }
    } else {
        // No student_id available; skip claimed learners update
    }
}
echo "Total Gauteng students imported: $gauteng_imported\n";
echo "Added to claimed table: $gauteng_claimed_added\n";
echo "Updated in claimed table: $gauteng_claimed_updated\n";

// #region agent log
// Log Gauteng import summary
try {
    $nds_debug_log_path = __DIR__ . '/../.cursor/debug.log';
    $nds_debug_entry = array(
        'sessionId'   => 'debug-session',
        'runId'       => 'student-seed',
        'hypothesisId'=> 'H3',
        'location'    => 'includes/student-seed.php:gauteng_summary',
        'message'     => 'Gauteng import summary',
        'data'        => array(
            'gauteng_imported'      => $gauteng_imported,
            'gauteng_claimed_added' => $gauteng_claimed_added,
            'gauteng_claimed_updated' => $gauteng_claimed_updated
        ),
        'timestamp'   => round(microtime(true) * 1000)
    );
    @file_put_contents($nds_debug_log_path, json_encode($nds_debug_entry) . "\n", FILE_APPEND);
} catch (\Throwable $e) {
}
// #endregion

// 8. FREE STATE STUDENTS
echo "\n=== IMPORTING FREE STATE STUDENTS ===\n";
$freestate_students = [
    [
        'student_number' => 'OCHEF0001F',
        'surname' => 'Alosiobi',
        'first_name' => 'Felicia Nnedimma',
        'email' => 'alisiobifelicia2@gmail.com',
        'phone' => '0738201924',
        'id_number' => '0503291092081',
        'date_of_birth' => '29/03/2005'
    ],
    [
        'student_number' => 'OCHEF0002F',
        'surname' => 'Buthelezi',
        'first_name' => 'Mbali',
        'email' => 'buthelezimbali@gmail.com',
        'phone' => '0764748608',
        'id_number' => '9302060583086',
        'date_of_birth' => '06/02/1993'
    ],
    [
        'student_number' => 'OCHEF0003F',
        'surname' => 'Dlamini',
        'first_name' => 'Eunice Fundokuhle',
        'email' => 'edhlamini98@gmail.com',
        'phone' => '0810353895',
        'id_number' => '0406081284084',
        'date_of_birth' => '08/06/2004'
    ],
    [
        'student_number' => 'OCHEF0004F',
        'surname' => 'Fukuthwa',
        'first_name' => 'Siwamkele',
        'email' => 'siwamkele04@gmail.com',
        'phone' => '0635337087',
        'id_number' => '0312091165083',
        'date_of_birth' => '09/12/2003'
    ],
    [
        'student_number' => 'OCHEF0005F',
        'surname' => 'Kumalo',
        'first_name' => 'Mamello',
        'email' => 'khumalomamello560@gmail.com',
        'phone' => '0657455701',
        'id_number' => '0504050817080',
        'date_of_birth' => '05/04/2005'
    ],
    [
        'student_number' => 'OCHEF0006F',
        'surname' => 'Lebusa',
        'first_name' => 'Malefa',
        'email' => '', // No banking details
        'phone' => '', // No banking details
        'id_number' => '0511241020086',
        'date_of_birth' => '24/11/2005'
    ],
    [
        'student_number' => 'OCHEF0007F',
        'surname' => 'Lehoko',
        'first_name' => 'Keorapetse',
        'email' => 'keorapetselehoko378@gmail.com',
        'phone' => '0719400928',
        'id_number' => '0506160464083',
        'date_of_birth' => '16/06/2005'
    ],
    [
        'student_number' => 'OCHEF0008F',
        'surname' => 'Lejone',
        'first_name' => 'Rethabile',
        'email' => '', // No banking details
        'phone' => '', // No banking details
        'id_number' => '0403121380089',
        'date_of_birth' => '12/03/2004'
    ],
    [
        'student_number' => 'OCHEF0009F',
        'surname' => 'Madumise',
        'first_name' => 'Rethabile',
        'email' => 'retha751@gmail.com',
        'phone' => '0663311224',
        'id_number' => '9902170098089',
        'date_of_birth' => '17/02/1999'
    ],
    [
        'student_number' => 'OCHEF0010F',
        'surname' => 'Makhotla',
        'first_name' => 'Mmakirileng Dorah',
        'email' => 'makhotlammakirileng@gmailcom',
        'phone' => '0670860967',
        'id_number' => '9411120484084',
        'date_of_birth' => '12/11/1994'
    ],
    [
        'student_number' => 'OCHEF0012F',
        'surname' => 'Mathe',
        'first_name' => 'Lerato Emily',
        'email' => 'leratomate099@gmail.com',
        'phone' => '0681232349',
        'id_number' => '0210031412088',
        'date_of_birth' => '03/10/2002'
    ],
    [
        'student_number' => 'OCHEF0013F',
        'surname' => 'Matsapola',
        'first_name' => 'Reabetsoe',
        'email' => 'matsapolareabetsoe@gmail.com',
        'phone' => '0683175809',
        'id_number' => '0604291102084',
        'date_of_birth' => '29/04/2006'
    ],
    [
        'student_number' => 'OCHEF0014F',
        'surname' => 'Matsha',
        'first_name' => 'Bongiwe',
        'email' => 'bongiwematsha7@gmail.com',
        'phone' => '0634268447',
        'id_number' => '0501170744087',
        'date_of_birth' => '17/01/2005'
    ],
    [
        'student_number' => 'OCHEF0015F',
        'surname' => 'Mlindazwe',
        'first_name' => 'Luvo',
        'email' => 'luvomlindazwe0@gmail.com',
        'phone' => '0734438395',
        'id_number' => '0303235726088',
        'date_of_birth' => '23/03/2003'
    ],
    [
        'student_number' => 'OCHEF0016F',
        'surname' => 'Modupe',
        'first_name' => 'Katleho Godwill',
        'email' => 'Katlehochandrey8@gmail.com',
        'phone' => '0795644570',
        'id_number' => '0201265561088',
        'date_of_birth' => '26/01/2002'
    ],
    [
        'student_number' => 'OCHEF0017F',
        'surname' => 'Mofokeng',
        'first_name' => 'Kagiso',
        'email' => 'Kgk928588@gail.com',
        'phone' => '0718018280',
        'id_number' => '0506285544082',
        'date_of_birth' => '28/06/2005'
    ],
    [
        'student_number' => 'OCHEF0018F',
        'surname' => 'Mofokeng',
        'first_name' => 'Nthabeleng Millicent',
        'email' => 'mnthabeleng760@gmail.com',
        'phone' => '0810655968',
        'id_number' => '9304261127085',
        'date_of_birth' => '26/04/1993'
    ],
    [
        'student_number' => 'OCHEF0019F',
        'surname' => 'Mofokeng',
        'first_name' => 'Mpho',
        'email' => 'mphomofokeng2014@gmail.com',
        'phone' => '0814519254',
        'id_number' => '9601050361084',
        'date_of_birth' => '05/01/1996'
    ],
    [
        'student_number' => 'OCHEF0020F',
        'surname' => 'Mofokeng',
        'first_name' => 'Puleng',
        'email' => 'mofokeng462@gmail.com',
        'phone' => '0717072917',
        'id_number' => '0501250055081',
        'date_of_birth' => '25/01/2005'
    ],
    [
        'student_number' => 'OCHEF0021F',
        'surname' => 'Moisa',
        'first_name' => 'Paballo Lavonne',
        'email' => 'paballom1411@gmail.com',
        'phone' => '0656042208',
        'id_number' => '0111140862082',
        'date_of_birth' => '14/11/2001'
    ],
    [
        'student_number' => 'OCHEF0022F',
        'surname' => 'Molaodi',
        'first_name' => 'Thato',
        'email' => 'prettymolaodi260@gmail.com',
        'phone' => '0635213033',
        'id_number' => '0508260092087',
        'date_of_birth' => '26/08/2005'
    ],
    [
        'student_number' => 'OCHEF0023F',
        'surname' => 'Nyawuza',
        'first_name' => 'Betty Bessie',
        'email' => '', // No banking details
        'phone' => '', // No banking details
        'id_number' => '9210250727084',
        'date_of_birth' => '25/10/1992'
    ],
    [
        'student_number' => 'OCHEF0024F',
        'surname' => 'Phadi',
        'first_name' => 'Comfort Sthembiso',
        'email' => '', // No banking details
        'phone' => '', // No banking details
        'id_number' => '9510010323086',
        'date_of_birth' => '01/10/1995'
    ],
    [
        'student_number' => 'OCHEF0025F',
        'surname' => 'Rammai',
        'first_name' => 'Mpho',
        'email' => 'mphorammai27@gmail.com',
        'phone' => '0822309550',
        'id_number' => '0205220704089',
        'date_of_birth' => '22/05/2002'
    ],
    [
        'student_number' => 'OCHEF0026F',
        'surname' => 'Sibusiso',
        'first_name' => 'David Samente',
        'email' => 'Sibusisosamente@moov.life',
        'phone' => '0735061408',
        'id_number' => '9207035746082',
        'date_of_birth' => '03/07/1992'
    ],
    [
        'student_number' => 'OCHEF0027F',
        'surname' => 'Sibaya',
        'first_name' => 'Ndosi',
        'email' => 'Ndosiiannah69@gmail.com',
        'phone' => '0606100802',
        'id_number' => '0104071318083',
        'date_of_birth' => '07/04/2001'
    ],
    [
        'student_number' => 'OCHEF0028F',
        'surname' => 'Tala',
        'first_name' => 'Lerato Natasha',
        'email' => 'leratotala6@gmail.com',
        'phone' => '0680240291',
        'id_number' => '0601290704084',
        'date_of_birth' => '29/01/2006'
    ],
    [
        'student_number' => 'OCHEF0029F',
        'surname' => 'Tau',
        'first_name' => 'Refiloe',
        'email' => '', // No banking details
        'phone' => '', // No banking details
        'id_number' => '0612270579081',
        'date_of_birth' => '27/12/2006'
    ],
    [
        'student_number' => 'OCHEF0030F',
        'surname' => 'Thapedi',
        'first_name' => 'Lerato Angela',
        'email' => 'leratoangelathapedi@gmail.com',
        'phone' => '0787867157',
        'id_number' => '0306270751085',
        'date_of_birth' => '27/06/2003'
    ],
    [
        'student_number' => 'OCHEF0031F',
        'surname' => 'Tsehle',
        'first_name' => 'Kevin Kamohelo',
        'email' => 'kevintsehle18@gmail.com',
        'phone' => '0677647132',
        'id_number' => '0510075476083',
        'date_of_birth' => '07/10/2005'
    ]
];

$freestate_imported = 0;
$freestate_claimed_added = 0;
$freestate_claimed_updated = 0;
foreach ($freestate_students as $student_data) {
    // Skip empty student numbers
    if (empty($student_data['student_number'])) {
        continue;
    }
    
    // Check if student exists
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $t_students WHERE student_number = %s",
        $student_data['student_number']
    ));
    
    $student_id = null;
    
    if (!$existing) {
        // Generate email if empty
        if (empty($student_data['email'])) {
            $email = strtolower($student_data['surname']) . '.' . 
                    strtolower(substr(str_replace(' ', '', $student_data['first_name']), 0, 3)) . 
                    '@student.nds.example';
            $student_data['email'] = $email;
        }
        
        // Insert student
        // Note: wp_nds_students does NOT have a province column; it DOES have date_of_birth.
        $date_of_birth = null;
        if (!empty($student_data['date_of_birth'])) {
            $dob_obj = \DateTime::createFromFormat('d/m/Y', $student_data['date_of_birth']);
            if ($dob_obj) {
                $date_of_birth = $dob_obj->format('Y-m-d');
            }
        }

        // Resolve intake year from academic year row for snapshot fields
        $intake_year_value = null;
        if ($academic_year_id) {
            $intake_year_value = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT year_name FROM $t_academic_years WHERE id = %d",
                $academic_year_id
            ));
        }

        $wpdb->insert($t_students, [
            'student_number'   => $student_data['student_number'],
            'faculty_id'       => $faculty_id,
            'first_name'       => $student_data['first_name'],
            'last_name'        => $student_data['surname'],
            'email'            => $student_data['email'],
            'phone'            => $student_data['phone'],
            'date_of_birth'    => $date_of_birth,
            'gender'           => 'Other',
            'country'          => 'South Africa',
            'status'           => 'active',
            'intake_year'      => $intake_year_value,
            'intake_semester'  => 'Semester 1',
            'created_at'       => current_time('mysql')
        ]);
        
        $student_id = (int) $wpdb->insert_id;
        
        if (!$student_id || $student_id <= 0) {
            echo "  ERROR: Failed to get student_id after insert for {$student_data['student_number']}\n";
            continue;
        }
        
        // Validate IDs before inserting
        if (empty($cohort_ids['FS']) || $cohort_ids['FS'] <= 0) {
            echo "  ERROR: Invalid FS cohort_id for {$student_data['student_number']}\n";
            continue;
        }
        if (!$course_id || $course_id <= 0) {
            echo "  ERROR: Invalid course_id for {$student_data['student_number']}\n";
            continue;
        }
        if (!$academic_year_id || $academic_year_id <= 0) {
            echo "  ERROR: Invalid academic_year_id for {$student_data['student_number']}\n";
            continue;
        }
        if (!$semester_id || $semester_id <= 0) {
            echo "  ERROR: Invalid semester_id for {$student_data['student_number']}\n";
            continue;
        }
        
        // Add to cohort
        $cohort_insert = $wpdb->insert($t_student_cohorts, [
            'student_id' => $student_id,
            'cohort_id' => $cohort_ids['FS'],
            'start_date' => current_time('mysql'),
            'status' => 'active',
            'created_at' => current_time('mysql')
        ]);
        
        if ($cohort_insert === false) {
            echo "  ERROR: Failed to add to cohort for {$student_data['student_number']}: " . $wpdb->last_error . "\n";
        }
        
        // Create enrollment
        $enrollment_insert = $wpdb->insert($t_student_enrollments, [
            'student_id' => $student_id,
            'course_id' => $course_id,
            'academic_year_id' => $academic_year_id,
            'semester_id' => $semester_id,
            'enrollment_date' => current_time('mysql'),
            'status' => 'enrolled',
            'delivery_mode' => 'in-person',
            'created_at' => current_time('mysql')
        ]);
        
        if ($enrollment_insert === false) {
            echo "  ERROR: Failed to create enrollment for {$student_data['student_number']}: " . $wpdb->last_error . "\n";
        }
        
        $freestate_imported++;
        echo "Imported Free State: {$student_data['student_number']} - {$student_data['surname']}\n";
    } else {
        // Student already exists - ensure we cast to int to avoid any type issues
        $student_id = (int) $existing;
    }
    
    // Upsert to claimed learners table (insert or update)
    if ($student_id) {
        $claim_token = generate_claim_token();
        $claim_expiry = date('Y-m-d H:i:s', strtotime('+30 days'));
        $claim_link = home_url("/claim-profile?token=" . $claim_token);
        
        $existing_claimed = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $t_claimed_learners WHERE student_number = %s",
            $student_data['student_number']
        ));
        
        if (!$existing_claimed) {
            // Insert new record
            $insert_result = $wpdb->insert($t_claimed_learners, [
                'student_id' => $student_id,
                'student_number' => $student_data['student_number'],
                'id_number' => $student_data['id_number'],
                'surname' => $student_data['surname'],
                'first_name' => $student_data['first_name'],
                'email' => $student_data['email'],
                'phone' => $student_data['phone'],
                'province' => 'Free State',
                'claim_token' => $claim_token,
                'claim_link' => $claim_link,
                'claim_expiry' => $claim_expiry,
                'is_claimed' => 0,
                'created_at' => current_time('mysql')
            ]);
            
            if ($insert_result !== false) {
            $freestate_claimed_added++;
            echo "  Added to claimed learners table\n";
            } else {
                echo "  ERROR: Failed to add to claimed learners table: " . $wpdb->last_error . "\n";
            }
        } else {
            // Update existing record with fresh token and current student_id
            $update_result = $wpdb->update(
                $t_claimed_learners,
                [
                    'student_id' => $student_id,
                    'id_number' => $student_data['id_number'],
                    'surname' => $student_data['surname'],
                    'first_name' => $student_data['first_name'],
                    'email' => $student_data['email'],
                    'phone' => $student_data['phone'],
                    'province' => 'Free State',
                    'claim_token' => $claim_token,
                    'claim_link' => $claim_link,
                    'claim_expiry' => $claim_expiry,
                    'is_claimed' => 0, // Reset claim status
                    'updated_at' => current_time('mysql')
                ],
                ['student_number' => $student_data['student_number']],
                ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s'],
                ['%s']
            );
            
            if ($update_result !== false) {
                $freestate_claimed_updated++;
                echo "  Updated claimed learners table with fresh token\n";
            } else {
                echo "  ERROR: Failed to update claimed learners table: " . $wpdb->last_error . "\n";
            }
        }
    } else {
        // No student_id available; skip claimed learners update
    }
}
echo "Total Free State students imported: $freestate_imported\n";
echo "Added to claimed table: $freestate_claimed_added\n";
echo "Updated in claimed table: $freestate_claimed_updated\n";

// #region agent log
// Log Free State import summary
try {
    $nds_debug_log_path = __DIR__ . '/../.cursor/debug.log';
    $nds_debug_entry = array(
        'sessionId'   => 'debug-session',
        'runId'       => 'student-seed',
        'hypothesisId'=> 'H4',
        'location'    => 'includes/student-seed.php:freestate_summary',
        'message'     => 'Free State import summary',
        'data'        => array(
            'freestate_imported'      => $freestate_imported,
            'freestate_claimed_added' => $freestate_claimed_added,
            'freestate_claimed_updated' => $freestate_claimed_updated
        ),
        'timestamp'   => round(microtime(true) * 1000)
    );
    @file_put_contents($nds_debug_log_path, json_encode($nds_debug_entry) . "\n", FILE_APPEND);
} catch (\Throwable $e) {
}
// #endregion

// 9. SUMMARY
echo "\n=== IMPORT SUMMARY ===\n";
echo "Gauteng students: $gauteng_imported\n";
echo "Free State students: $freestate_imported\n";
echo "Total students imported: " . ($gauteng_imported + $freestate_imported) . "\n";
echo "\nClaimed Learners Table:\n";
echo "  - Gauteng: $gauteng_claimed_added added, $gauteng_claimed_updated updated\n";
echo "  - Free State: $freestate_claimed_added added, $freestate_claimed_updated updated\n";
echo "  - Total processed: " . ($gauteng_claimed_added + $freestate_claimed_added + $gauteng_claimed_updated + $freestate_claimed_updated) . "\n";

// #region agent log
// Final summary log for entire seed run
try {
    $nds_debug_log_path = __DIR__ . '/../.cursor/debug.log';
    $nds_debug_entry = array(
        'sessionId'   => 'debug-session',
        'runId'       => 'student-seed',
        'hypothesisId'=> 'H5',
        'location'    => 'includes/student-seed.php:final_summary',
        'message'     => 'Overall student seed summary',
        'data'        => array(
            'gauteng_imported'        => $gauteng_imported,
            'freestate_imported'      => $freestate_imported,
            'total_imported'          => $gauteng_imported + $freestate_imported,
            'gauteng_claimed_added'   => $gauteng_claimed_added,
            'gauteng_claimed_updated' => $gauteng_claimed_updated,
            'freestate_claimed_added' => $freestate_claimed_added,
            'freestate_claimed_updated' => $freestate_claimed_updated,
            'total_claimed_processed' => $gauteng_claimed_added + $freestate_claimed_added + $gauteng_claimed_updated + $freestate_claimed_updated
        ),
        'timestamp'   => round(microtime(true) * 1000)
    );
    @file_put_contents($nds_debug_log_path, json_encode($nds_debug_entry) . "\n", FILE_APPEND);
} catch (\Throwable $e) {
}
// #endregion
echo "\nCohort assignments:\n";
echo "  - Gauteng Cohort: " . $cohort_ids['GP'] . " (COH-OCHEF-2024-S1-GP)\n";
echo "  - Free State Cohort: " . $cohort_ids['FS'] . " (COH-OCHEF-2024-S1-FS)\n";
echo "All students enrolled in Course: PC101 (Professional Chef Fundamentals)\n";
echo "\nClaim Management:\n";
echo "- Each student has a unique claim token valid for 30 days\n";
echo "- Claim links are stored in the database\n";
echo "- Existing records are updated with fresh tokens and reset claim status\n";
echo "- Once claimed, is_claimed flag will be set to 1 and claimed_at timestamp recorded\n";
echo "- Claimed records can be deleted to clean up the table\n";

echo "</pre>";