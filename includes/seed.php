<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Seeding utilities for LMS (education paths, programs, courses, academic years/semesters, accreditation bodies)
 */

/**
 * Seed LMS baseline data.
 * - Creates default education paths
 * - Creates programs under each path
 * - Creates example courses under each program
 * - Creates baseline academic year and semesters if missing
 * - Creates a few accreditation bodies
 */
function nds_seed_lms_data() {
	if (function_exists('current_user_can') && !current_user_can('manage_options')) {
		return array('error' => 'Unauthorized');
	}

	global $wpdb;

	$faculties_table = $wpdb->prefix . 'nds_faculties';
	$programs_table = $wpdb->prefix . 'nds_programs';
	$courses_table = $wpdb->prefix . 'nds_courses';
	$accreditations_table = $wpdb->prefix . 'nds_accreditation_bodies';
	$years_table = $wpdb->prefix . 'nds_academic_years';
	$semesters_table = $wpdb->prefix . 'nds_semesters';

	$seed = [
		// Path 1: Full time qualification
		[
			'name' => 'Full time qualification',
			'description' => 'Full time qualification',
			'path_type' => 'culinary',
			'duration_years' => 2.0,
			'career_outcomes' => 'Professional chef, food service manager',
			'programs' => [
				['name' => 'Hospitality Management','description' => 'Hospitality Management','level' => 'beginner','program_type' => 'diploma','duration_months' => 24,
					'courses' => [
						['name' => 'Diploma in Introduction to the Hospitality Industry','accreditation_body' => 'QCTO','nqf_level' => 5,'duration' => '12 months','credits' => 120,'price' => 0.00]
					]
				],
				['name' => 'Food Preparation and Culinary Arts','description' => 'Food Preparation and Culinary Arts','level' => 'intermediate','program_type' => 'diploma','duration_months' => 24,
					'courses' => [
						['name' => 'Advanced Diploma in Culinary Arts and Supervision','accreditation_body' => 'QCTO','nqf_level' => 6,'duration' => '12 months','credits' => 120,'price' => 0.00],
						['name' => 'Diploma Patisserie and Confectionery','accreditation_body' => 'QCTO','nqf_level' => 5,'duration' => '12 months','credits' => 120,'price' => 0.00]
					]
				],
				['name' => 'Food and Beverage Service','description' => 'Food and Beverage Service','level' => 'beginner','program_type' => 'diploma','duration_months' => 12,
					'courses' => [
						['name' => 'Diploma Food and Beverage Service','accreditation_body' => 'QCTO','nqf_level' => 5,'duration' => '12 months','credits' => 120,'price' => 0.00]
					]
				],
				['name' => 'Housekeeping Service','description' => 'Housekeeping Service','level' => 'beginner','program_type' => 'diploma','duration_months' => 12,
					'courses' => [
						['name' => 'Diploma in Housekeeping Service','accreditation_body' => 'QCTO','nqf_level' => 5,'duration' => '12 months','credits' => 120,'price' => 0.00],
						['name' => 'Advanced Diploma in Housekeeping Service','accreditation_body' => 'QCTO','nqf_level' => 6,'duration' => '12 months','credits' => 120,'price' => 0.00]
					]
				],
				['name' => 'Culinary Arts and Supervision','description' => 'Culinary Arts and Supervision','level' => 'advanced','program_type' => 'diploma','duration_months' => 18,'courses' => []],
				['name' => 'Patisserie and Confectionery','description' => 'Patisserie and Confectionery','level' => 'advanced','program_type' => 'diploma','duration_months' => 12,'courses' => []],
				['name' => 'Barista','description' => 'Barista','level' => 'beginner','program_type' => 'certificate','duration_months' => 6,
					'courses' => [
						['name' => 'Advanced Diploma in Barista','accreditation_body' => 'QCTO','nqf_level' => 5,'duration' => '6 months','credits' => 60,'price' => 0.00]
					]
				]
			]
		],

		// Path 2: Part Time Qualification
		[
			'name' => 'Part Time Qualification',
			'description' => 'Part Time Qualification',
			'path_type' => 'management',
			'duration_years' => 1.0,
			'career_outcomes' => 'Upskilling and part-time learning',
			'programs' => [
				['name' => 'Hospitality Management (Part Time)','description' => 'Part time stream','level' => 'beginner','program_type' => 'short_course','duration_months' => 6,'courses' => []]
			]
		],

		// Path 3: Short Courses
		[
			'name' => 'Short Courses',
			'description' => 'Short Courses',
			'path_type' => 'culinary',
			'duration_years' => 0.5,
			'career_outcomes' => 'Targeted culinary skills',
			'programs' => [
				['name' => 'Cooking Courses','description' => 'Cooking Courses','level' => 'beginner','program_type' => 'short_course','duration_months' => 3,
					'courses' => [
						['name' => 'Basic Cooking Course','accreditation_body' => 'City & Guilds','nqf_level' => 3,'duration' => '4 weeks','credits' => 8,'price' => 0.00],
						['name' => 'Advance Cooking Course','accreditation_body' => 'City & Guilds','nqf_level' => 4,'duration' => '6 weeks','credits' => 12,'price' => 0.00]
					]
				],
				['name' => 'Catering Courses','description' => 'Catering Courses','level' => 'beginner','program_type' => 'short_course','duration_months' => 3,
					'courses' => [
						['name' => 'Catering Fundamentals','accreditation_body' => 'City & Guilds','nqf_level' => 3,'duration' => '4 weeks','credits' => 8,'price' => 0.00]
					]
				],
				['name' => 'Baking Courses','description' => 'Baking Courses','level' => 'beginner','program_type' => 'short_course','duration_months' => 3,
					'courses' => [
						['name' => 'Basic Baking Course (Variety of doughs)','accreditation_body' => 'City & Guilds','nqf_level' => 3,'duration' => '4 weeks','credits' => 8,'price' => 0.00],
						['name' => 'Advanced Baking Course','accreditation_body' => 'City & Guilds','nqf_level' => 4,'duration' => '6 weeks','credits' => 12,'price' => 0.00],
						['name' => 'One day Biscuit Making','accreditation_body' => 'City & Guilds','nqf_level' => 3,'duration' => '1 day','credits' => 1,'price' => 0.00]
					]
				],
				['name' => 'Confectionary Course','description' => 'Confectionary Course','level' => 'beginner','program_type' => 'short_course','duration_months' => 3,
					'courses' => [
						['name' => 'Variety of Baking skills with different toppings','accreditation_body' => 'City & Guilds','nqf_level' => 3,'duration' => '4 weeks','credits' => 8,'price' => 0.00]
					]
				]
			]
		],

		// Path 4: ARPL-Trade Test
		[
			'name' => 'ARPL-Trade Test',
			'description' => 'ARPL-Trade Test',
			'path_type' => 'management',
			'duration_years' => 0.5,
			'career_outcomes' => 'Trade test preparation',
			'programs' => [
				['name' => 'Trade test - Artisan Chef','description' => 'Trade test - Artisan Chef','level' => 'professional','program_type' => 'short_course','duration_months' => 6,
					'courses' => [
						['name' => 'Trade test - Artisan Chef','accreditation_body' => 'QCTO','nqf_level' => 5,'duration' => '6 months','credits' => 60,'price' => 0.00]
					]
				]
			]
		]
	];

	// Ensure required tables exist
	$required_tables = [$faculties_table, $programs_table, $courses_table, $accreditations_table, $years_table, $semesters_table];
	$missing_tables = [];
	foreach ($required_tables as $t) {
		$exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $t));
		if (!$exists) {
			$missing_tables[] = $t;
		}
	}
	if (!empty($missing_tables)) {
		// Try to create missing tables (specifically faculties)
		foreach ($missing_tables as $table) {
			if ($table === $faculties_table) {
				// Create faculties table if it doesn't exist (matching database.php schema)
				$charset_collate = $wpdb->get_charset_collate();
				$sql = "CREATE TABLE IF NOT EXISTS {$faculties_table} (
					id INT AUTO_INCREMENT PRIMARY KEY,
					code VARCHAR(20) UNIQUE NOT NULL,
					name VARCHAR(255) NOT NULL,
					short_name VARCHAR(100),
					description TEXT,
					dean_name VARCHAR(255),
					dean_email VARCHAR(255),
					contact_phone VARCHAR(20),
					contact_email VARCHAR(255),
					website_url VARCHAR(255),
					status ENUM('active', 'inactive', 'archived') DEFAULT 'active',
					created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
					updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
					INDEX idx_code (code),
					INDEX idx_status (status)
				) {$charset_collate};";
				
				require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
				dbDelta($sql);
				
				// Verify it was created
				$exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $faculties_table));
				if (!$exists) {
					return array('error' => 'Failed to create required table: ' . $faculties_table . '. Error: ' . $wpdb->last_error);
				}
			}
		}
	}

	// Ensure program_types table exists and is seeded
	$program_types_table = $wpdb->prefix . 'nds_program_types';
	$program_types_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $program_types_table));
	if (!$program_types_exists) {
		// If table doesn't exist, database.php hasn't run - seed will fail
		// Return error suggesting to run database migration first
		return array('error' => 'Program types table does not exist. Please activate the plugin or run database migration first.');
	}

	// Seed program types if they don't exist (idempotent)
	$program_types = [
		['diploma', 'Diploma', 1, 'undergraduate'],
		['bachelor', 'Bachelor\'s Degree', 3, 'undergraduate'],
		['honours', 'Honours Degree', 1, 'undergraduate'],
		['masters', 'Master\'s Degree', 2, 'postgraduate'],
		['phd', 'Doctor of Philosophy', 3, 'postgraduate'],
		['certificate', 'Certificate', 0, 'professional'],
		['short_course', 'Short Course', 0, 'professional']
	];
	foreach ($program_types as $type) {
		$exists = $wpdb->get_var($wpdb->prepare(
			"SELECT id FROM {$program_types_table} WHERE code = %s",
			$type[0]
		));
		if (!$exists) {
			$wpdb->query($wpdb->prepare(
				"INSERT IGNORE INTO {$program_types_table} (code, name, typical_duration_years, level) VALUES (%s, %s, %d, %s)",
				$type[0],
				$type[1],
				$type[2],
				$type[3]
			));
		}
	}

	// #region agent log
	@file_put_contents(__DIR__ . '/../.cursor/debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'seed-run','hypothesisId'=>'C','location'=>'seed.php:217','message'=>'Starting academic year creation in nds_seed_lms_data','data'=>[],'timestamp'=>round(microtime(true)*1000)]) . "\n", FILE_APPEND);
	// #endregion
	
	// Upsert academic year and semesters
	// First, check if 2024 exists (preferred for student data)
	$active_year_id = (int) $wpdb->get_var("SELECT id FROM {$years_table} WHERE year_name = '2024' LIMIT 1");
	
	// #region agent log
	@file_put_contents(__DIR__ . '/../.cursor/debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'seed-run','hypothesisId'=>'C','location'=>'seed.php:222','message'=>'Checked for 2024 year','data'=>['active_year_id'=>$active_year_id],'timestamp'=>round(microtime(true)*1000)]) . "\n", FILE_APPEND);
	// #endregion
	
	if (!$active_year_id) {
		// Check for any active year
	$active_year_id = (int) $wpdb->get_var("SELECT id FROM {$years_table} WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
		
		// #region agent log
		@file_put_contents(__DIR__ . '/../.cursor/debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'seed-run','hypothesisId'=>'C','location'=>'seed.php:226','message'=>'Checked for any active year','data'=>['active_year_id'=>$active_year_id],'timestamp'=>round(microtime(true)*1000)]) . "\n", FILE_APPEND);
		// #endregion
	}
	
	if (!$active_year_id) {
		// Deactivate all existing years first
		$wpdb->query("UPDATE {$years_table} SET is_active = 0");
		
		// #region agent log
		@file_put_contents(__DIR__ . '/../.cursor/debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'seed-run','hypothesisId'=>'C','location'=>'seed.php:232','message'=>'Before creating 2024 academic year','data'=>['is_active_value'=>1],'timestamp'=>round(microtime(true)*1000)]) . "\n", FILE_APPEND);
		// #endregion
		
		// Create 2024 year (preferred for student data compatibility)
		// Note: wp_nds_academic_years does NOT have a created_at column in the current schema
		$start = '2024-01-01';
		$end = '2024-12-31';
		$insert_result = $wpdb->insert($years_table, [
			'year_name' => '2024',
			'start_date' => $start,
			'end_date' => $end,
			'is_active' => 1
		], ['%s','%s','%s','%d']);
		
		// #region agent log
		@file_put_contents(__DIR__ . '/../.cursor/debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'seed-run','hypothesisId'=>'C','location'=>'seed.php:243','message'=>'After creating 2024 academic year','data'=>['insert_result'=>$insert_result,'insert_id'=>$wpdb->insert_id,'last_error'=>$wpdb->last_error],'timestamp'=>round(microtime(true)*1000)]) . "\n", FILE_APPEND);
		// #endregion
		
		$active_year_id = (int) $wpdb->insert_id;
		
		// #region agent log
		$verify_year = $wpdb->get_var($wpdb->prepare("SELECT is_active FROM {$years_table} WHERE id = %d", $active_year_id));
		@file_put_contents(__DIR__ . '/../.cursor/debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'seed-run','hypothesisId'=>'C','location'=>'seed.php:250','message'=>'Verify 2024 academic year is_active','data'=>['active_year_id'=>$active_year_id,'is_active_in_db'=>$verify_year],'timestamp'=>round(microtime(true)*1000)]) . "\n", FILE_APPEND);
		// #endregion
	} else {
		// Ensure this year is active and deactivate others
		$wpdb->query("UPDATE {$years_table} SET is_active = 0");
		$wpdb->update(
			$years_table,
			['is_active' => 1],
			['id' => $active_year_id],
			['%d'],
			['%d']
		);
	}
	
	// Check for active semester
	$active_semester_id = (int) $wpdb->get_var($wpdb->prepare(
		"SELECT id FROM {$semesters_table} WHERE academic_year_id = %d AND is_active = 1 ORDER BY id DESC LIMIT 1",
		$active_year_id
	));
	
	if (!$active_semester_id) {
		// Check if Semester 1 exists for this year
		$semester_1_id = (int) $wpdb->get_var($wpdb->prepare(
			"SELECT id FROM {$semesters_table} WHERE academic_year_id = %d AND semester_name = 'Semester 1' LIMIT 1",
			$active_year_id
		));
		
		if ($semester_1_id) {
			// Activate existing Semester 1
			$wpdb->query($wpdb->prepare(
				"UPDATE {$semesters_table} SET is_active = 0 WHERE academic_year_id = %d",
				$active_year_id
			));
			$wpdb->update(
				$semesters_table,
				['is_active' => 1],
				['id' => $semester_1_id],
				['%d'],
				['%d']
			);
			$active_semester_id = $semester_1_id;
		} else {
			// Create new semesters
			$wpdb->query($wpdb->prepare(
				"UPDATE {$semesters_table} SET is_active = 0 WHERE academic_year_id = %d",
				$active_year_id
			));
			
			// Note: wp_nds_semesters in the current schema does NOT have a semester_number or created_at column.
			// Insert only the columns that actually exist.
			
			// #region agent log
			@file_put_contents(__DIR__ . '/../.cursor/debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'seed-run','hypothesisId'=>'C','location'=>'seed.php:288','message'=>'Before creating Semester 1 in nds_seed_lms_data','data'=>['active_year_id'=>$active_year_id,'is_active_value'=>1],'timestamp'=>round(microtime(true)*1000)]) . "\n", FILE_APPEND);
			// #endregion
			
			$insert_result = $wpdb->insert($semesters_table, [
			'academic_year_id' => $active_year_id,
				'semester_name'    => 'Semester 1',
				'start_date'       => '2024-01-15',
				'end_date'         => '2024-06-15',
				'is_active'        => 1
			], ['%d','%s','%s','%s','%d']);
			
			// #region agent log
			@file_put_contents(__DIR__ . '/../.cursor/debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'seed-run','hypothesisId'=>'C','location'=>'seed.php:297','message'=>'After creating Semester 1 in nds_seed_lms_data','data'=>['insert_result'=>$insert_result,'insert_id'=>$wpdb->insert_id,'last_error'=>$wpdb->last_error],'timestamp'=>round(microtime(true)*1000)]) . "\n", FILE_APPEND);
			// #endregion
			
			$active_semester_id = (int) $wpdb->insert_id;
			
			// #region agent log
			$verify_semester = $wpdb->get_row($wpdb->prepare("SELECT id, is_active, academic_year_id FROM {$semesters_table} WHERE id = %d", $active_semester_id), ARRAY_A);
			@file_put_contents(__DIR__ . '/../.cursor/debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'seed-run','hypothesisId'=>'C','location'=>'seed.php:303','message'=>'Verify Semester 1 is_active in nds_seed_lms_data','data'=>['active_semester_id'=>$active_semester_id,'semester_data'=>$verify_semester],'timestamp'=>round(microtime(true)*1000)]) . "\n", FILE_APPEND);
			// #endregion
			
			// Note: wp_nds_semesters does NOT have a created_at column
		$wpdb->insert($semesters_table, [
			'academic_year_id' => $active_year_id,
				'semester_name'    => 'Semester 2',
				'start_date'       => '2024-07-01',
				'end_date'         => '2024-12-15',
				'is_active'        => 0
			], ['%d','%s','%s','%s','%d']);
		}
	} else {
		// Ensure only this semester is active for this year
		$wpdb->query($wpdb->prepare(
			"UPDATE {$semesters_table} SET is_active = 0 WHERE academic_year_id = %d AND id != %d",
			$active_year_id, $active_semester_id
		));
	}

	// Accreditation bodies (idempotent on name)
	$accreditations = ['QCTO', 'CATHSSETA', 'City & Guilds'];
	foreach ($accreditations as $acc) {
		$exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$accreditations_table} WHERE name = %s", $acc));
		if (!$exists) {
			$wpdb->insert($accreditations_table, [
				'name' => $acc,
				'created_at' => function_exists('current_time') ? current_time('mysql') : date('Y-m-d H:i:s')
			], ['%s','%s']);
		}
	}

	// Seed faculties first (required table) - create from seed data paths
	// Map seed data paths to faculties
	$faculty_map = []; // Maps path name to faculty_id

	// Seed hierarchical data (faculties -> programs -> courses)
	foreach ($seed as $path) {
		// Create or get faculty from path data
		// Generate a code from the name
		$faculty_code = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $path['name']), 0, 10));
		$existing_faculty_id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$faculties_table} WHERE code = %s OR name = %s", $faculty_code, $path['name']));
		if ($existing_faculty_id) {
			$faculty_id = $existing_faculty_id;
		} else {
			$insert_result = $wpdb->insert($faculties_table, [
				'code' => $faculty_code,
				'name' => $path['name'],
				'description' => $path['description'],
				'status' => 'active',
				'created_at' => function_exists('current_time') ? current_time('mysql') : date('Y-m-d H:i:s'),
				'updated_at' => function_exists('current_time') ? current_time('mysql') : date('Y-m-d H:i:s')
			], ['%s','%s','%s','%s','%s','%s']);
			if ($insert_result === false) {
				return array('error' => 'Failed to insert faculty: ' . $wpdb->last_error);
			}
			$faculty_id = (int) $wpdb->insert_id;
		}
		$faculty_map[$path['name']] = $faculty_id;

		foreach ($path['programs'] as $program) {
			$existing_program_id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$programs_table} WHERE name = %s AND faculty_id = %d", $program['name'], $faculty_id));
			if ($existing_program_id) {
				$program_id = $existing_program_id;
			} else {
				// Resolve program_type string to program_type_id dynamically to avoid FK issues
				$program_type_code = isset($program['program_type']) ? strtolower(trim($program['program_type'])) : '';
				$program_type_table = $wpdb->prefix . 'nds_program_types';

				// Normalise some common labels to codes if needed
				$code_map = [
					'diploma'       => 'diploma',
					'certificate'   => 'certificate',
					'short_course'  => 'short_course',
					'short-course'  => 'short_course',
					'short course'  => 'short_course',
					'trade_test'    => 'trade_test',
					'trade-test'    => 'trade_test',
					'trade test'    => 'trade_test',
					'workshop'      => 'workshop',
					'masterclass'   => 'masterclass',
				];

				if (isset($code_map[$program_type_code])) {
					$program_type_code = $code_map[$program_type_code];
				}

				$program_type_id = (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT id FROM {$program_type_table} WHERE code = %s",
						$program_type_code
					)
				);

				// Auto-create missing program type if it doesn't exist
				if (!$program_type_id && !empty($program_type_code)) {
					// Default values for auto-created types
					$default_name = ucfirst(str_replace('_', ' ', $program_type_code));
					$default_duration = 1;
					$default_level = 'professional';
					
					// Set better defaults for known types
					if ($program_type_code === 'diploma') {
						$default_name = 'Diploma';
						$default_duration = 1;
						$default_level = 'undergraduate';
					} elseif ($program_type_code === 'certificate') {
						$default_name = 'Certificate';
						$default_duration = 0;
						$default_level = 'professional';
					} elseif ($program_type_code === 'short_course') {
						$default_name = 'Short Course';
						$default_duration = 0;
						$default_level = 'professional';
					}
					
					$insert_result = $wpdb->insert(
						$program_type_table,
						[
							'code' => $program_type_code,
							'name' => $default_name,
							'typical_duration_years' => $default_duration,
							'level' => $default_level,
							'created_at' => function_exists('current_time') ? current_time('mysql') : date('Y-m-d H:i:s')
						],
						['%s', '%s', '%d', '%s', '%s']
					);
					
					if ($insert_result !== false) {
						$program_type_id = (int) $wpdb->insert_id;
					} else {
					return array(
						'error' => sprintf(
								'Failed to insert program: could not create program type "%s" in %s. Error: %s',
								$program_type_code,
								$program_type_table,
								$wpdb->last_error
							)
						);
					}
				} elseif (!$program_type_id) {
					return array(
						'error' => sprintf(
							'Failed to insert program: program type "%s" not found in %s and could not be auto-created',
							$program_type_code,
							$program_type_table
						)
					);
				}
				
				// Generate unique program code
				if (function_exists('nds_generate_program_code')) {
					$program_code = nds_generate_program_code($program['name'], $faculty_id, $wpdb, $programs_table);
				} else {
					// Fallback: simple code generation
					$name_clean = preg_replace('/[^a-zA-Z0-9]/', '', $program['name']);
					$prefix = strtoupper(substr($name_clean, 0, 3));
					if (empty($prefix)) {
						$prefix = 'PRG';
					}
					$program_code = $prefix . '-' . $faculty_id;
					$counter = 1;
					$base_code = $program_code;
					while ($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$programs_table} WHERE code = %s", $program_code)) > 0) {
						$program_code = $base_code . '-' . $counter;
						$counter++;
					}
				}
				
				$insert_result = $wpdb->insert($programs_table, [
					'faculty_id' => $faculty_id,
					'program_type_id' => $program_type_id,
					'code' => $program_code,
					'name' => $program['name'],
					'duration_months' => $program['duration_months'],
					'description' => $program['description'],
					'status' => 'active',
					'created_at' => function_exists('current_time') ? current_time('mysql') : date('Y-m-d H:i:s')
				], ['%d','%d','%s','%s','%d','%s','%s','%s']);
				if ($insert_result === false) {
					return array('error' => 'Failed to insert program: ' . $wpdb->last_error);
				}
				$program_id = (int) $wpdb->insert_id;
			}

			$course_counter = 1;
			foreach ($program['courses'] as $course) {
				$existing_course_id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$courses_table} WHERE name = %s AND program_id = %d", $course['name'], $program_id));
				if ($existing_course_id) {
					continue;
				}
				
				// Generate unique course code: program prefix + program_id + course counter
				$program_prefix = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $program['name']), 0, 3));
				if (empty($program_prefix)) {
					$program_prefix = 'CRS';
				}
				$base_code = $program_prefix . str_pad((string)$program_id, 2, '0', STR_PAD_LEFT) . str_pad((string)$course_counter, 3, '0', STR_PAD_LEFT);
				$course_code = $base_code;
				
				// Ensure uniqueness by checking existing codes and appending suffix if needed
				$suffix = 1;
				while ($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$courses_table} WHERE code = %s", $course_code)) > 0) {
					$course_code = $base_code . '-' . $suffix;
					$suffix++;
				}
				
				$course_counter++;
				
				// Parse duration string (e.g., "12 months" -> weeks estimate)
				$duration_weeks = null;
				if (isset($course['duration'])) {
					if (preg_match('/(\d+)\s*(month|week|day)/i', $course['duration'], $matches)) {
						$value = (int)$matches[1];
						$unit = strtolower($matches[2]);
						if ($unit === 'month') {
							$duration_weeks = $value * 4; // Approximate
						} elseif ($unit === 'week') {
							$duration_weeks = $value;
						} elseif ($unit === 'day') {
							$duration_weeks = max(1, round($value / 7)); // At least 1 week
						}
					}
				}
				
				// Calculate start and end dates based on academic year and duration
				$academic_year = $wpdb->get_row("SELECT start_date, end_date FROM {$years_table} WHERE is_active = 1 ORDER BY id DESC LIMIT 1", ARRAY_A);
				$semester = $wpdb->get_row("SELECT start_date, end_date FROM {$semesters_table} WHERE academic_year_id = (SELECT id FROM {$years_table} WHERE is_active = 1 LIMIT 1) AND is_active = 1 ORDER BY id DESC LIMIT 1", ARRAY_A);
				
				$course_start_date = $semester ? $semester['start_date'] : ($academic_year ? $academic_year['start_date'] : date('Y-01-15'));
				$course_end_date = $semester ? $semester['end_date'] : ($academic_year ? $academic_year['end_date'] : date('Y-12-15'));
				
				// If duration_weeks is set, calculate end date from start
				if ($duration_weeks && $duration_weeks > 0) {
					$start_obj = new DateTime($course_start_date);
					$start_obj->modify('+' . $duration_weeks . ' weeks');
					$course_end_date = $start_obj->format('Y-m-d');
				}
				
				// Assign color based on program (consistent colors per program)
				$color_palette = [
					'#3b82f6', // Blue
					'#10b981', // Green
					'#f59e0b', // Amber
					'#ef4444', // Red
					'#8b5cf6', // Purple
					'#ec4899', // Pink
					'#06b6d4', // Cyan
					'#84cc16', // Lime
					'#f97316', // Orange
					'#6366f1', // Indigo
					'#14b8a6', // Teal
					'#a855f7', // Violet
				];
				$course_color = $color_palette[$program_id % count($color_palette)];
				
				$insert_result = $wpdb->insert($courses_table, [
					'program_id' => $program_id,
					'code' => $course_code,
					'name' => $course['name'],
					'nqf_level' => (int) $course['nqf_level'],
					'description' => isset($course['description']) ? $course['description'] : '',
					'assessment_method' => isset($course['assessment_method']) ? $course['assessment_method'] : '',
					'duration_weeks' => $duration_weeks,
					'credits' => (int) $course['credits'],
					'price' => (float) $course['price'],
					'currency' => 'ZAR',
					'start_date' => $course_start_date,
					'end_date' => $course_end_date,
					'color' => $course_color,
					'status' => 'active',
					'created_at' => function_exists('current_time') ? current_time('mysql') : date('Y-m-d H:i:s')
				], ['%d','%s','%s','%d','%s','%s','%d','%d','%f','%s','%s','%s','%s','%s','%s']);
				
				// Handle accreditation body via M2M table if provided
				if ($insert_result !== false && isset($course['accreditation_body']) && !empty($course['accreditation_body'])) {
					$course_id = (int) $wpdb->insert_id;
					$acc_body_id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$accreditations_table} WHERE name = %s", $course['accreditation_body']));
					if ($acc_body_id) {
						$course_accreditations_table = $wpdb->prefix . 'nds_course_accreditations';
						$wpdb->query($wpdb->prepare(
							"INSERT IGNORE INTO {$course_accreditations_table} (course_id, accreditation_body_id, status, created_at) VALUES (%d, %d, 'active', %s)",
							$course_id, $acc_body_id, function_exists('current_time') ? current_time('mysql') : date('Y-m-d H:i:s')
						));
					}
				}
				if ($insert_result === false) {
					$error_msg = 'Failed to insert course "' . $course['name'] . '": ' . $wpdb->last_error;
					if (defined('WP_DEBUG') && WP_DEBUG) {
						error_log('[NDS Seed] ' . $error_msg);
					}
					// Continue with other courses instead of failing completely
					// But log the error for debugging
				}
			}
		}
	}

	// Verify that courses were actually created
	$courses_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$courses_table} WHERE status = 'active'");
	if ($courses_count === 0) {
		return array('error' => 'LMS seed completed but no active courses were created. Please check database tables and try again.');
	}

	// Also seed staff members so that a single seed action
	// prepares faculties/programs/courses AND core staff records.
	if (function_exists('nds_seed_staff_data')) {
		$staff_result = nds_seed_staff_data();
		if (is_array($staff_result) && isset($staff_result['error'])) {
			// Bubble staff seeding error up to the caller so the admin
			// sees a failure message instead of a silent partial seed.
			return $staff_result;
		}
	}

	// Seed rooms/venues (halls, classes, kitchens)
	$rooms_result = nds_seed_rooms();
	if (is_array($rooms_result) && isset($rooms_result['error'])) {
		return $rooms_result;
	}

	// Generate smart schedules for all courses
	$schedules_result = nds_generate_course_schedules(true); // Skip auth check when called from seed
	if (is_array($schedules_result) && isset($schedules_result['error'])) {
		return $schedules_result;
	}

	// Return success with counts
	$faculties_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$faculties_table}");
	$programs_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$programs_table}");
	$schedules_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}nds_course_schedules");
	
	return array(
		'success' => true,
		'message' => "LMS seed completed successfully. Created: {$faculties_count} faculties, {$programs_count} programs, {$courses_count} courses, {$schedules_count} schedules."
	);
}

/**
 * Seed rooms/venues (1 Main hall, 3 classes, 4 kitchens)
 */
function nds_seed_rooms() {
	if (function_exists('current_user_can') && !current_user_can('manage_options')) {
		return array('error' => 'Unauthorized');
	}

	global $wpdb;
	$rooms_table = $wpdb->prefix . 'nds_rooms';
	
	// Check if table exists, create if it doesn't
	$table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $rooms_table));
	if (!$table_exists) {
		// Create the rooms table using the schema from database.php
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		$charset_collate = $wpdb->get_charset_collate();
		$sql_rooms = "CREATE TABLE IF NOT EXISTS $rooms_table (
			id INT AUTO_INCREMENT PRIMARY KEY,
			code VARCHAR(20) UNIQUE NOT NULL,
			name VARCHAR(255) NOT NULL,
			type ENUM('hall','classroom','kitchen','lab','workshop','other') NOT NULL,
			capacity INT DEFAULT 0,
			location VARCHAR(255),
			equipment TEXT,
			amenities TEXT,
			is_active BOOLEAN DEFAULT TRUE,
			created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			INDEX idx_type (type),
			INDEX idx_active (is_active)
		) $charset_collate;";
		dbDelta($sql_rooms);
		
		// Verify it was created
		$table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $rooms_table));
		if (!$table_exists) {
			return array('error' => 'Failed to create rooms table. Please run database migration first.');
		}
	}

	$rooms = [
		['code' => 'HALL-001', 'name' => 'Main Hall', 'type' => 'hall', 'capacity' => 200, 'location' => 'Ground Floor', 'equipment' => 'Projector, Sound System, Stage', 'amenities' => 'Air Conditioning, WiFi'],
		['code' => 'CLASS-001', 'name' => 'Classroom 1', 'type' => 'classroom', 'capacity' => 30, 'location' => 'First Floor', 'equipment' => 'Whiteboard, Projector', 'amenities' => 'Air Conditioning'],
		['code' => 'CLASS-002', 'name' => 'Classroom 2', 'type' => 'classroom', 'capacity' => 30, 'location' => 'First Floor', 'equipment' => 'Whiteboard, Projector', 'amenities' => 'Air Conditioning'],
		['code' => 'CLASS-003', 'name' => 'Classroom 3', 'type' => 'classroom', 'capacity' => 25, 'location' => 'Second Floor', 'equipment' => 'Whiteboard, Projector', 'amenities' => 'Air Conditioning'],
		['code' => 'KITCHEN-001', 'name' => 'Kitchen 1 - Main Training Kitchen', 'type' => 'kitchen', 'capacity' => 20, 'location' => 'Ground Floor', 'equipment' => 'Commercial Ovens, Stovetops, Prep Stations, Refrigeration', 'amenities' => 'Ventilation System, Safety Equipment'],
		['code' => 'KITCHEN-002', 'name' => 'Kitchen 2 - Pastry Lab', 'type' => 'kitchen', 'capacity' => 15, 'location' => 'Ground Floor', 'equipment' => 'Pastry Equipment, Mixers, Ovens, Chilling Units', 'amenities' => 'Ventilation System, Safety Equipment'],
		['code' => 'KITCHEN-003', 'name' => 'Kitchen 3 - Advanced Techniques', 'type' => 'kitchen', 'capacity' => 18, 'location' => 'Ground Floor', 'equipment' => 'Sous Vide, Smokers, Specialty Equipment', 'amenities' => 'Ventilation System, Safety Equipment'],
		['code' => 'KITCHEN-004', 'name' => 'Kitchen 4 - Butchery & Prep', 'type' => 'kitchen', 'capacity' => 12, 'location' => 'Ground Floor', 'equipment' => 'Butchery Equipment, Prep Tables, Cold Storage', 'amenities' => 'Ventilation System, Safety Equipment']
	];

	$created = 0;
	foreach ($rooms as $room) {
		$exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$rooms_table} WHERE code = %s", $room['code']));
		if (!$exists) {
			$wpdb->insert($rooms_table, [
				'code' => $room['code'],
				'name' => $room['name'],
				'type' => $room['type'],
				'capacity' => $room['capacity'],
				'location' => $room['location'],
				'equipment' => $room['equipment'],
				'amenities' => $room['amenities'],
				'is_active' => 1,
				'created_at' => current_time('mysql')
			], ['%s','%s','%s','%d','%s','%s','%s','%d','%s']);
			if ($wpdb->insert_id) {
				$created++;
			}
		}
	}

	return array('success' => true, 'message' => "Seeded {$created} rooms/venues.");
}

/**
 * Smart scheduling function that generates schedules based on course type
 * - Lectures: Theory classes in classrooms
 * - Practicals: Hands-on sessions in kitchens
 * - Trade Tests: Assessment sessions
 */
function nds_generate_course_schedules($skip_auth = false) {
	// Allow execution from seed context (when called internally) or admin context
	// Check if we're in CLI/seed context or admin context
	$is_seed_context = $skip_auth || !function_exists('current_user_can') || (defined('WP_CLI') && WP_CLI);
	$is_admin_context = function_exists('current_user_can') && current_user_can('manage_options');
	
	if (!$is_seed_context && !$is_admin_context) {
		return array('error' => 'Unauthorized');
	}

	global $wpdb;
	$courses_table = $wpdb->prefix . 'nds_courses';
	$schedules_table = $wpdb->prefix . 'nds_course_schedules';
	$rooms_table = $wpdb->prefix . 'nds_rooms';
	$staff_table = $wpdb->prefix . 'nds_staff';
	$cohorts_table = $wpdb->prefix . 'nds_cohorts';
	$academic_years_table = $wpdb->prefix . 'nds_academic_years';
	$semesters_table = $wpdb->prefix . 'nds_semesters';
	
	// Get active academic year and semester
	$academic_year_id = (int) $wpdb->get_var("SELECT id FROM {$academic_years_table} WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
	
	$semester_id = (int) $wpdb->get_var($wpdb->prepare(
		"SELECT id FROM {$semesters_table} WHERE academic_year_id = %d AND is_active = 1 ORDER BY id DESC LIMIT 1",
		$academic_year_id
	));

	if (!$academic_year_id || !$semester_id) {
		return array('error' => 'No active academic year or semester found. Please seed academic data first.');
	}

	// Get room IDs
	$hall_id = (int) $wpdb->get_var("SELECT id FROM {$rooms_table} WHERE type = 'hall' AND is_active = 1 LIMIT 1");
	$classroom_ids = $wpdb->get_col("SELECT id FROM {$rooms_table} WHERE type = 'classroom' AND is_active = 1 ORDER BY id LIMIT 3");
	$kitchen_ids = $wpdb->get_col("SELECT id FROM {$rooms_table} WHERE type = 'kitchen' AND is_active = 1 ORDER BY id LIMIT 4");

	if (empty($classroom_ids) || empty($kitchen_ids)) {
		return array('error' => 'Rooms not found. Please seed rooms first.');
	}

	// Get available lecturers (staff table doesn't have status column, just check role)
	$lecturers = $wpdb->get_results("SELECT id FROM {$staff_table} WHERE (role LIKE '%lecturer%' OR role LIKE '%chef%') LIMIT 10", ARRAY_A);
	$lecturer_ids = array_column($lecturers, 'id');

	// Get all active cohorts
	$cohorts = $wpdb->get_results("SELECT id FROM {$cohorts_table} WHERE status = 'active'", ARRAY_A);
	$cohort_ids = array_column($cohorts, 'id');

	// Get all active courses with start/end dates
	$courses = $wpdb->get_results("
		SELECT id, code, name, start_date, end_date, duration_weeks, contact_hours
		FROM {$courses_table}
		WHERE status = 'active'
		AND start_date IS NOT NULL
		AND end_date IS NOT NULL
	", ARRAY_A);

	if (empty($courses)) {
		return array('error' => 'No active courses with dates found.');
	}

	$schedules_created = 0;
	$room_rotation = 0; // Rotate through rooms to distribute load

	foreach ($courses as $course) {
		$course_id = (int) $course['id'];
		$course_name = strtolower($course['name']);
		$duration_weeks = (int) ($course['duration_weeks'] ?: 12);
		$contact_hours = (int) ($course['contact_hours'] ?: 40);

		// Skip courses without start_date or end_date
		if (empty($course['start_date']) || empty($course['end_date'])) {
			continue;
		}

		// Determine course type based on name/keywords
		$has_practical = (
			stripos($course_name, 'culinary') !== false ||
			stripos($course_name, 'cooking') !== false ||
			stripos($course_name, 'kitchen') !== false ||
			stripos($course_name, 'food preparation') !== false ||
			stripos($course_name, 'pastry') !== false ||
			stripos($course_name, 'butchery') !== false ||
			stripos($course_name, 'practical') !== false
		);

		$has_trade_test = (
			stripos($course_name, 'trade test') !== false ||
			stripos($course_name, 'assessment') !== false ||
			stripos($course_name, 'exam') !== false ||
			stripos($course_name, 'evaluation') !== false
		);

		// Calculate hours per week
		$hours_per_week = max(2, round($contact_hours / $duration_weeks));

		// Assign lecturer (rotate or random)
		$lecturer_id = !empty($lecturer_ids) ? $lecturer_ids[array_rand($lecturer_ids)] : null;

		// Assign cohort (if available)
		$cohort_id = !empty($cohort_ids) ? $cohort_ids[array_rand($cohort_ids)] : null;

		// Clear existing schedules for this course (idempotent)
		$wpdb->delete($schedules_table, ['course_id' => $course_id], ['%d']);

		// Generate lecture schedules (theory classes)
		if ($hours_per_week >= 2) {
			// Lectures: 2-4 hours per week, typically Mon/Wed or Tue/Thu
			$lecture_hours = min(4, max(2, round($hours_per_week * 0.4))); // 40% theory
			// Use full day names for form compatibility (Monday, Tuesday, etc.)
			$lecture_days = ['Monday', 'Wednesday'];
			$lecture_start = '08:00:00';
			$lecture_end = date('H:i:s', strtotime($lecture_start) + ($lecture_hours * 3600));

			$classroom_id = $classroom_ids[$room_rotation % count($classroom_ids)];
			$room_rotation++;

			$room = $wpdb->get_row($wpdb->prepare("SELECT name, code FROM {$rooms_table} WHERE id = %d", $classroom_id), ARRAY_A);
			$room_name = $room['name'];
			$room_display = trim($room_name . ($room['code'] ? ' (' . $room['code'] . ')' : ''));
			
			$insert_result = $wpdb->insert($schedules_table, [
				'course_id' => $course_id,
				'lecturer_id' => $lecturer_id,
				'room_id' => $classroom_id,
				'days' => implode(', ', $lecture_days),
				'start_time' => $lecture_start,
				'end_time' => $lecture_end,
				'day_hours' => $lecture_hours,
				'session_type' => 'Lecture',
				'location' => $room_display,
				'cohort_id' => $cohort_id,
				'pattern_type' => 'every_week',
				'valid_from' => $course['start_date'],
				'valid_to' => $course['end_date'],
				'is_active' => 1,
				'created_at' => current_time('mysql')
			], ['%d','%d','%d','%s','%s','%s','%f','%s','%s','%d','%s','%s','%s','%d','%s']);

			if ($wpdb->insert_id) {
				$schedules_created++;
			} else {
				error_log("Failed to create lecture schedule for course {$course_id}. Error: " . $wpdb->last_error);
			}
		}

		// Generate practical schedules (kitchen sessions)
		if ($has_practical && $hours_per_week >= 4) {
			// Practicals: 4-8 hours per week, typically Tue/Thu or Wed/Fri
			$practical_hours = min(8, max(4, round($hours_per_week * 0.6))); // 60% practical
			// Use full day names for form compatibility
			$practical_days = ['Tuesday', 'Thursday'];
			$practical_start = '09:00:00';
			$practical_end = date('H:i:s', strtotime($practical_start) + ($practical_hours * 3600));

			$kitchen_id = $kitchen_ids[$room_rotation % count($kitchen_ids)];
			$room_rotation++;

			$room = $wpdb->get_row($wpdb->prepare("SELECT name, code FROM {$rooms_table} WHERE id = %d", $kitchen_id), ARRAY_A);
			$room_name = $room['name'];
			$room_display = trim($room_name . ($room['code'] ? ' (' . $room['code'] . ')' : ''));
			
			$insert_result = $wpdb->insert($schedules_table, [
				'course_id' => $course_id,
				'lecturer_id' => $lecturer_id,
				'room_id' => $kitchen_id,
				'days' => implode(', ', $practical_days),
				'start_time' => $practical_start,
				'end_time' => $practical_end,
				'day_hours' => $practical_hours,
				'session_type' => 'Practical',
				'location' => $room_display,
				'cohort_id' => $cohort_id,
				'pattern_type' => 'every_week',
				'valid_from' => $course['start_date'],
				'valid_to' => $course['end_date'],
				'is_active' => 1,
				'created_at' => current_time('mysql')
			], ['%d','%d','%d','%s','%s','%s','%f','%s','%s','%d','%s','%s','%s','%d','%s']);

			if ($wpdb->insert_id) {
				$schedules_created++;
			} else {
				error_log("Failed to create practical schedule for course {$course_id}. Error: " . $wpdb->last_error);
			}
		}

		// Generate trade test schedules (assessments)
		if ($has_trade_test || $has_practical) {
			// Trade tests: Typically scheduled mid-semester and end-semester
			// Calculate mid-point and end dates
			$start_date = new DateTime($course['start_date']);
			$end_date = new DateTime($course['end_date']);
			$mid_date = clone $start_date;
			$mid_date->modify('+' . round($duration_weeks / 2) . ' weeks');

			// Mid-semester trade test
			$test_start = '14:00:00';
			$test_end = '17:00:00';
			$test_room_id = $kitchen_ids[$room_rotation % count($kitchen_ids)];

			// Get the Friday of the mid-week
			$mid_friday = clone $mid_date;
			while ($mid_friday->format('N') != 5) { // 5 = Friday
				$mid_friday->modify('+1 day');
			}

			$room = $wpdb->get_row($wpdb->prepare("SELECT name, code FROM {$rooms_table} WHERE id = %d", $test_room_id), ARRAY_A);
			$room_name = $room['name'];
			$room_display = trim($room_name . ($room['code'] ? ' (' . $room['code'] . ')' : ''));

			$insert_result = $wpdb->insert($schedules_table, [
				'course_id' => $course_id,
				'lecturer_id' => $lecturer_id,
				'room_id' => $test_room_id,
				'days' => 'Friday',
				'start_time' => $test_start,
				'end_time' => $test_end,
				'day_hours' => 3.0,
				'session_type' => 'Assessment',
				'location' => $room_display,
				'cohort_id' => $cohort_id,
				'pattern_type' => 'once',
				'pattern_meta' => json_encode(['date' => $mid_friday->format('Y-m-d')]),
				'valid_from' => $mid_friday->format('Y-m-d'),
				'valid_to' => $mid_friday->format('Y-m-d'),
				'is_active' => 1,
				'created_at' => current_time('mysql')
			], ['%d','%d','%d','%s','%s','%s','%f','%s','%s','%d','%s','%s','%s','%s','%d','%s']);

			if ($wpdb->insert_id) {
				$schedules_created++;
			} else {
				error_log("Failed to create mid-semester trade test schedule for course {$course_id}. Error: " . $wpdb->last_error);
			}

			// End-semester trade test
			$end_friday = clone $end_date;
			while ($end_friday->format('N') != 5) { // 5 = Friday
				$end_friday->modify('-1 day');
			}

			$insert_result = $wpdb->insert($schedules_table, [
				'course_id' => $course_id,
				'lecturer_id' => $lecturer_id,
				'room_id' => $test_room_id,
				'days' => 'Friday',
				'start_time' => $test_start,
				'end_time' => $test_end,
				'day_hours' => 3.0,
				'session_type' => 'Assessment',
				'location' => $room_display,
				'cohort_id' => $cohort_id,
				'pattern_type' => 'once',
				'pattern_meta' => json_encode(['date' => $end_friday->format('Y-m-d')]),
				'valid_from' => $end_friday->format('Y-m-d'),
				'valid_to' => $end_friday->format('Y-m-d'),
				'is_active' => 1,
				'created_at' => current_time('mysql')
			], ['%d','%d','%d','%s','%s','%s','%f','%s','%s','%d','%s','%s','%s','%s','%d','%s']);

			if ($wpdb->insert_id) {
				$schedules_created++;
			} else {
				error_log("Failed to create end-semester trade test schedule for course {$course_id}. Error: " . $wpdb->last_error);
			}
		}
	}

	return array('success' => true, 'message' => "Generated {$schedules_created} course schedules.");
}

/**
 * Danger: reset/truncate LMS core data.
 */
function nds_reset_lms_data() {
	if (function_exists('current_user_can') && !current_user_can('manage_options')) {
		return array('error' => 'Unauthorized');
	}
	global $wpdb;
	$tables = [
		$wpdb->prefix . 'nds_courses',
		$wpdb->prefix . 'nds_programs',
		$wpdb->prefix . 'nds_education_paths',
		$wpdb->prefix . 'nds_course_prerequisites',
		$wpdb->prefix . 'nds_course_lecturers',
		$wpdb->prefix . 'nds_possible_employment',
		$wpdb->prefix . 'nds_duration_breakdown'
	];
	$wpdb->query('SET FOREIGN_KEY_CHECKS=0');
	foreach ($tables as $t) {
		$wpdb->query("TRUNCATE TABLE {$t}");
	}
	$wpdb->query('SET FOREIGN_KEY_CHECKS=1');
	return true;
}

/**
 * Danger: wipe all student-related data (students, enrollments, cohorts, claimed learners).
 */
function nds_wipe_students_data() {
	if (function_exists('current_user_can') && !current_user_can('manage_options')) {
		return array('error' => 'Unauthorized');
	}

	global $wpdb;
	$tables = [
		$wpdb->prefix . 'nds_student_enrollments',
		$wpdb->prefix . 'nds_student_events',
		$wpdb->prefix . 'nds_student_cohorts',
		$wpdb->prefix . 'nds_claimed_learners',
		$wpdb->prefix . 'nds_students',
	];

	$wpdb->query('SET FOREIGN_KEY_CHECKS=0');
	foreach ($tables as $t) {
		$wpdb->query("TRUNCATE TABLE {$t}");
	}
	$wpdb->query('SET FOREIGN_KEY_CHECKS=1');

	return array('success' => true, 'message' => 'All student-related tables wiped successfully.');
}

/**
 * Wipe selected nds_ tables (generic, checkbox-driven).
 */
function nds_wipe_selected_nds_tables($tables) {
	if (function_exists('current_user_can') && !current_user_can('manage_options')) {
		return array('error' => 'Unauthorized');
	}

	if (empty($tables) || !is_array($tables)) {
		return array('error' => 'No tables selected.');
	}

	global $wpdb;
	$prefix = $wpdb->prefix . 'nds_';

	$wpdb->query('SET FOREIGN_KEY_CHECKS=0');
	foreach ($tables as $raw_table) {
		$table = sanitize_text_field($raw_table);

		// Only allow nds_ tables for safety
		if (strpos($table, $prefix) !== 0) {
			continue;
		}

		// Basic safety: only letters, numbers, underscore
		if (!preg_match('/^[A-Za-z0-9_]+$/', str_replace($wpdb->prefix, '', $table))) {
			continue;
		}

		// Final safety: ensure table actually exists
		$exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
		if ($exists) {
			$wpdb->query("TRUNCATE TABLE `{$table}`");
		}
	}
	$wpdb->query('SET FOREIGN_KEY_CHECKS=1');

	return array('success' => true, 'message' => 'Selected tables wiped successfully.');
}

/**
 * Seed all major data sets (LMS + Students + Staff) in one action.
 * Wraps individual seeders and aggregates any errors.
 */
function nds_seed_all_data() {
	if (function_exists('current_user_can') && !current_user_can('manage_options')) {
		return array('error' => 'Unauthorized');
	}

	$results = [];

	// Seed LMS
	$results['lms'] = nds_seed_lms_data();

	// Seed Students via manual script (buffer output)
	$student_seed_file = plugin_dir_path(__FILE__) . 'student-seed.php';
	if (file_exists($student_seed_file)) {
		ob_start();
		include $student_seed_file;
		$results['students_output'] = ob_get_clean();
		$results['students'] = array('success' => true);
	} else {
		$results['students'] = array('error' => 'Student seed script not found.');
	}

	// Seed Staff
	$results['staff'] = nds_seed_staff_data();

	// Determine overall success
	$errors = [];
	foreach (array('lms', 'students', 'staff') as $key) {
		if (isset($results[$key]['error'])) {
			$errors[] = ucfirst($key) . ': ' . $results[$key]['error'];
		}
	}

	if (!empty($errors)) {
		return array('error' => implode(' | ', $errors));
	}

	return array(
		'success' => true,
		'message' => 'All seeds completed successfully (LMS, Students, Staff).'
	);
}

/**
 * Run seed by type: all, lms, staff, or students.
 *
 * @param string $type One of: all, lms, staff, students
 * @return array{success?: bool, message?: string, error?: string}
 */
function nds_seed_by_type($type) {
	$type = strtolower((string) $type);
	$allowed = array('all', 'lms', 'staff', 'students');
	if (!in_array($type, $allowed, true)) {
		return array('error' => 'Invalid seed type.');
	}
	if (function_exists('current_user_can') && !current_user_can('manage_options')) {
		return array('error' => 'Unauthorized');
	}
	switch ($type) {
		case 'all':
			return nds_seed_all_data();
		case 'lms':
			return nds_seed_lms_data();
		case 'staff':
			return nds_seed_staff_data();
		case 'students':
			$student_seed_file = plugin_dir_path(__FILE__) . 'student-seed.php';
			if (!file_exists($student_seed_file)) {
				return array('error' => 'Student seed script not found.');
			}
			ob_start();
			include $student_seed_file;
			ob_end_clean();
			return array('success' => true, 'message' => 'Students seeded successfully.');
		default:
			return array('error' => 'Invalid seed type.');
	}
}

// Admin actions
if (function_exists('add_action')) {
	add_action('admin_post_nds_seed', function () {
		if (function_exists('current_user_can') && !current_user_can('manage_options')) {
			if (function_exists('wp_die')) wp_die('Unauthorized');
		}
		if (function_exists('check_admin_referer')) check_admin_referer('nds_seed_nonce');
		$type = isset($_POST['nds_seed_type']) ? sanitize_text_field($_POST['nds_seed_type']) : '';
		$result = nds_seed_by_type($type);
		$is_success = is_array($result) && isset($result['success']) && $result['success'] === true;
		$msg = is_array($result) && isset($result['message']) ? $result['message'] : '';
		if (!$is_success && is_array($result) && isset($result['error'])) {
			$msg = $result['error'];
		} elseif (!$is_success) {
			$msg = $msg ?: 'Unknown error occurred during seeding';
		}
		$status = $is_success ? 'success' : 'error';
		$extra = $msg ? '&msg=' . rawurlencode($msg) : '';
		if (function_exists('wp_redirect') && function_exists('admin_url')) {
			wp_redirect(admin_url('admin.php?page=nds-settings&seed=' . $status . $extra));
			exit;
		}
	});

	add_action('admin_post_nds_seed_lms', function () {
		if (function_exists('current_user_can') && !current_user_can('manage_options')) {
			if (function_exists('wp_die')) wp_die('Unauthorized');
		}
		if (function_exists('check_admin_referer')) check_admin_referer('nds_seed_lms_nonce');
		$result = nds_seed_lms_data();
		
		if ($result !== true && (!is_array($result) || !isset($result['success']) || $result['success'] !== true)) {
			// Handle both array errors and WP_Error objects
			if (is_array($result) && isset($result['error'])) {
				$msg = $result['error'];
			} elseif (is_wp_error($result)) {
				$msg = $result->get_error_message();
			} else {
				$msg = 'Unknown error occurred during seeding';
			}
			if (function_exists('wp_redirect') && function_exists('admin_url')) {
				wp_redirect(admin_url('admin.php?page=nds-settings&seed=error&msg=' . rawurlencode($msg)));
				exit;
			}
		} else {
			// Success - show message if available
			$msg = '';
			if (is_array($result) && isset($result['message'])) {
				$msg = '&msg=' . rawurlencode($result['message']);
			}
			if (function_exists('wp_redirect') && function_exists('admin_url')) {
				wp_redirect(admin_url('admin.php?page=nds-settings&seed=success' . $msg));
				exit;
			}
		}
	});

	add_action('admin_post_nds_reset_lms', function () {
		if (function_exists('current_user_can') && !current_user_can('manage_options')) {
			if (function_exists('wp_die')) wp_die('Unauthorized');
		}
		if (function_exists('check_admin_referer')) check_admin_referer('nds_reset_lms_nonce');
		$result = nds_reset_lms_data();
		if ($result !== true) {
			$msg = is_array($result) && isset($result['error']) ? $result['error'] : 'Unknown error';
			if (function_exists('wp_redirect') && function_exists('admin_url')) {
				wp_redirect(admin_url('admin.php?page=nds-settings&reset=error&msg=' . rawurlencode($msg)));
				exit;
			}
		}
		if (function_exists('wp_redirect') && function_exists('admin_url')) {
			wp_redirect(admin_url('admin.php?page=nds-settings&reset=success'));
			exit;
		}
	});

	add_action('admin_post_nds_wipe_students', function () {
		if (function_exists('current_user_can') && !current_user_can('manage_options')) {
			if (function_exists('wp_die')) wp_die('Unauthorized');
		}
		if (function_exists('check_admin_referer')) check_admin_referer('nds_wipe_students_nonce');
		$result = nds_wipe_students_data();
		if ($result !== true && is_array($result) && isset($result['error'])) {
			$msg = $result['error'];
			if (function_exists('wp_redirect') && function_exists('admin_url')) {
				wp_redirect(admin_url('admin.php?page=nds-settings&wipe_students=error&msg=' . rawurlencode($msg)));
				exit;
			}
		}
		if (function_exists('wp_redirect') && function_exists('admin_url')) {
			$msg = is_array($result) && isset($result['message']) ? $result['message'] : '';
			$extra = $msg ? '&msg=' . rawurlencode($msg) : '';
			wp_redirect(admin_url('admin.php?page=nds-settings&wipe_students=success' . $extra));
			exit;
		}
	});

	add_action('admin_post_nds_wipe_selected_nds_tables', function () {
		if (function_exists('current_user_can') && !current_user_can('manage_options')) {
			if (function_exists('wp_die')) wp_die('Unauthorized');
		}
		if (function_exists('check_admin_referer')) check_admin_referer('nds_wipe_selected_nds_tables_nonce');

		$tables = isset($_POST['nds_tables']) && is_array($_POST['nds_tables']) ? $_POST['nds_tables'] : array();
		$result = nds_wipe_selected_nds_tables($tables);

		if (is_array($result) && isset($result['error'])) {
			$msg = $result['error'];
			if (function_exists('wp_redirect') && function_exists('admin_url')) {
				wp_redirect(admin_url('admin.php?page=nds-settings&wipe_tables=error&msg=' . rawurlencode($msg)));
				exit;
			}
		} else {
			$msg = isset($result['message']) ? $result['message'] : '';
			$extra = $msg ? '&msg=' . rawurlencode($msg) : '';
			if (function_exists('wp_redirect') && function_exists('admin_url')) {
				wp_redirect(admin_url('admin.php?page=nds-settings&wipe_tables=success' . $extra));
				exit;
			}
		}
	});

	add_action('admin_post_nds_seed_all', function () {
		if (function_exists('current_user_can') && !current_user_can('manage_options')) {
			if (function_exists('wp_die')) wp_die('Unauthorized');
		}
		if (function_exists('check_admin_referer')) check_admin_referer('nds_seed_all_nonce');
		$result = nds_seed_all_data();

		if (is_array($result) && isset($result['error'])) {
			$msg = $result['error'];
			if (function_exists('wp_redirect') && function_exists('admin_url')) {
				wp_redirect(admin_url('admin.php?page=nds-settings&seed_all=error&msg=' . rawurlencode($msg)));
				exit;
			}
		} else {
			$msg = isset($result['message']) ? $result['message'] : '';
			$extra = $msg ? '&msg=' . rawurlencode($msg) : '';
			if (function_exists('wp_redirect') && function_exists('admin_url')) {
				wp_redirect(admin_url('admin.php?page=nds-settings&seed_all=success' . $extra));
				exit;
			}
		}
	});
}

/**
 * Seed Staff Members
 * Creates sample staff members with realistic data for testing/development
 */
function nds_seed_staff_data() {
	if (function_exists('current_user_can') && !current_user_can('manage_options')) {
		return array('error' => 'Unauthorized');
	}

	global $wpdb;
	$staff_table = $wpdb->prefix . 'nds_staff';

	// Check if table exists
	$table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $staff_table));
	if (!$table_exists) {
		return array('error' => 'Staff table does not exist. Please run database migration first.');
	}

	// Sample staff data
	$staff_members = [
		[
			'first_name' => 'Lebogang',
			'last_name' => 'Lekotokoto',
			'email' => 'Lebogang@ndsacademy.co.za',
			'phone' => '0111111111',
			'role' => 'Lecturer',
			'address' => 'Vaal Triangle',
			'dob' => '1985-03-15',
			'gender' => 'Female'
		],
		[
			'first_name' => 'Tshepiso',
			'last_name' => 'Tunzi',
			'email' => 'MrsTshepiso@ndsacademy.co.za',
			'phone' => '0111111112',
			'role' => 'Lecturer',
			'address' => 'Vaal Triangle',
			'dob' => '1980-02-03',
			'gender' => 'Female'
		],
		[
			'first_name' => 'Estelle',
			'last_name' => 'Holtzhausen',
			'email' => 'MrsEstelle@ndsacademy.co.za',
			'phone' => '0111111113',
			'role' => 'Lecturer',
			'address' => 'Vaal Triangle',
			'dob' => '1982-05-20',
			'gender' => 'Female'
		],
		[
			'first_name' => 'Pontsho',
			'last_name' => 'Mogiwa',
			'email' => 'MrsPontsho@ndsacademy.co.za',
			'phone' => '0111111114',
			'role' => 'Lecturer',
			'address' => 'Vaal Triangle',
			'dob' => '1983-07-10',
			'gender' => 'Female'
		],
		[
			'first_name' => 'Jacobus',
			'last_name' => 'Sutton',
			'email' => 'MrJacobus@ndsacademy.co.za',
			'phone' => '0111111115',
			'role' => 'Lecturer',
			'address' => 'Vaal Triangle',
			'dob' => '1978-11-25',
			'gender' => 'Male'
		],
		[
			'first_name' => 'Lwethu',
			'last_name' => 'Htlatswayo',
			'email' => 'MsLwethu@ndsacademy.co.za',
			'phone' => '0111111116',
			'role' => 'Lecturer',
			'address' => 'Vaal Triangle',
			'dob' => '1987-09-12',
			'gender' => 'Female'
		],
		[
			'first_name' => 'Nseya',
			'last_name' => 'Tshitundui',
			'email' => 'MsNseya@ndsacademy.co.za',
			'phone' => '0111111117',
			'role' => 'Lecturer',
			'address' => 'Vaal Triangle',
			'dob' => '1986-04-08',
			'gender' => 'Female'
		],
		[
			'first_name' => 'Mpuse',
			'last_name' => 'Mnguni',
			'email' => 'MrsMpuse@ndsacademy.co.za',
			'phone' => '0111111118',
			'role' => 'Lecturer',
			'address' => 'Vaal Triangle',
			'dob' => '1984-12-30',
			'gender' => 'Female'
		],
		[
			'first_name' => 'Irene',
			'last_name' => 'Xaba',
			'email' => 'MsIrene@ndsacademy.co.za',
			'phone' => '0111111119',
			'role' => 'Lecturer',
			'address' => 'Vaal Triangle',
			'dob' => '1981-06-18',
			'gender' => 'Female'
		],
		[
			'first_name' => 'Thabo',
			'last_name' => 'Molefe',
			'email' => 'MrThabo@ndsacademy.co.za',
			'phone' => '0111111120',
			'role' => 'Head Chef',
			'address' => 'Johannesburg',
			'dob' => '1975-08-22',
			'gender' => 'Male'
		],
		[
			'first_name' => 'Sarah',
			'last_name' => 'Johnson',
			'email' => 'MsSarah@ndsacademy.co.za',
			'phone' => '0111111121',
			'role' => 'Administrator',
			'address' => 'Pretoria',
			'dob' => '1990-01-14',
			'gender' => 'Female'
		],
		[
			'first_name' => 'David',
			'last_name' => 'Nkomo',
			'email' => 'MrDavid@ndsacademy.co.za',
			'phone' => '0111111122',
			'role' => 'Program Coordinator',
			'address' => 'Cape Town',
			'dob' => '1988-03-05',
			'gender' => 'Male'
		]
	];

	$created_count = 0;
	$skipped_count = 0;
	$errors = [];

	foreach ($staff_members as $staff) {
		// Check if staff member already exists (by email)
		$exists = $wpdb->get_var($wpdb->prepare(
			"SELECT id FROM {$staff_table} WHERE email = %s",
			$staff['email']
		));

		if ($exists) {
			$skipped_count++;
			continue;
		}

		// Check if WordPress user exists with this email
		$wp_user_id = null;
		if (function_exists('get_user_by')) {
			$wp_user = get_user_by('email', $staff['email']);
			if ($wp_user) {
				$wp_user_id = $wp_user->ID;
			} else {
				// Optionally create WordPress user
				// Uncomment if you want to auto-create users
				/*
				$user_id = wp_insert_user([
					'user_login' => $staff['email'],
					'user_pass'  => wp_generate_password(),
					'user_email' => $staff['email'],
					'first_name' => $staff['first_name'],
					'last_name'  => $staff['last_name'],
					'role'       => 'subscriber',
				]);
				if (!is_wp_error($user_id)) {
					$wp_user_id = $user_id;
				}
				*/
			}
		}

		// Insert staff member
		$result = $wpdb->insert(
			$staff_table,
			[
				'user_id' => $wp_user_id,
				'first_name' => $staff['first_name'],
				'last_name' => $staff['last_name'],
				'profile_picture' => null, // Profile picture field (nullable)
				'email' => $staff['email'],
				'phone' => $staff['phone'],
				'role' => $staff['role'],
				'address' => $staff['address'],
				'dob' => $staff['dob'],
				'gender' => $staff['gender'],
				'created_at' => function_exists('current_time') ? current_time('mysql') : date('Y-m-d H:i:s')
			],
			['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
		);

		if ($result === false) {
			$errors[] = "Failed to insert {$staff['first_name']} {$staff['last_name']}: " . $wpdb->last_error;
		} else {
			$created_count++;
		}
	}

	$result = [
		'success' => true,
		'created' => $created_count,
		'skipped' => $skipped_count,
		'total' => count($staff_members)
	];

	if (!empty($errors)) {
		$result['errors'] = $errors;
		$result['success'] = false;
	}

	return $result;
}

/**
 * Reset Staff Data (Truncate table)
 * WARNING: This will delete all staff members!
 */
function nds_reset_staff_data() {
	if (function_exists('current_user_can') && !current_user_can('manage_options')) {
		return array('error' => 'Unauthorized');
	}

	global $wpdb;
	$staff_table = $wpdb->prefix . 'nds_staff';

	// Check if table exists
	$table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $staff_table));
	if (!$table_exists) {
		return array('error' => 'Staff table does not exist.');
	}

	// Disable foreign key checks temporarily
	$wpdb->query('SET FOREIGN_KEY_CHECKS = 0');
	
	// Truncate table
	$result = $wpdb->query("TRUNCATE TABLE {$staff_table}");
	
	// Re-enable foreign key checks
	$wpdb->query('SET FOREIGN_KEY_CHECKS = 1');

	if ($result === false) {
		return array('error' => 'Failed to reset staff data: ' . $wpdb->last_error);
	}

	return array('success' => true, 'message' => 'Staff data reset successfully');
}

/**
 * Wipe core tables: faculties, programs, courses, and staff
 * WARNING: This will delete ALL data from these tables!
 */
function nds_wipe_core_tables() {
	if (function_exists('current_user_can') && !current_user_can('manage_options')) {
		return array('error' => 'Unauthorized');
	}

	global $wpdb;
	
	$tables = [
		$wpdb->prefix . 'nds_faculties',
		$wpdb->prefix . 'nds_programs',
		$wpdb->prefix . 'nds_courses',
		$wpdb->prefix . 'nds_staff'
	];

	$errors = [];
	$wiped_count = 0;

	// Disable foreign key checks temporarily
	$wpdb->query('SET FOREIGN_KEY_CHECKS = 0');

	foreach ($tables as $table) {
		// Check if table exists
		$table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
		if (!$table_exists) {
			$errors[] = "Table {$table} does not exist";
			continue;
		}

		// Truncate table
		$result = $wpdb->query("TRUNCATE TABLE {$table}");
		if ($result === false) {
			$errors[] = "Failed to truncate {$table}: " . $wpdb->last_error;
		} else {
			$wiped_count++;
		}
	}

	// Re-enable foreign key checks
	$wpdb->query('SET FOREIGN_KEY_CHECKS = 1');

	if (!empty($errors)) {
		return array('error' => 'Some tables failed to wipe: ' . implode('; ', $errors));
	}

	return array('success' => true, 'message' => "Successfully wiped {$wiped_count} table(s)");
}

// Admin actions for staff seeding
if (function_exists('add_action')) {
	add_action('admin_post_nds_seed_staff', function () {
		if (function_exists('current_user_can') && !current_user_can('manage_options')) {
			if (function_exists('wp_die')) wp_die('Unauthorized');
		}
		if (function_exists('check_admin_referer')) check_admin_referer('nds_seed_staff_nonce');
		
		$result = nds_seed_staff_data();
		
		// Check for errors (either 'error' key or 'success' === false with 'errors' array)
		if (is_array($result) && (isset($result['error']) || (isset($result['success']) && $result['success'] === false))) {
			$msg = isset($result['error']) ? $result['error'] : '';
			if (isset($result['errors']) && is_array($result['errors']) && !empty($result['errors'])) {
				$msg = implode('; ', $result['errors']);
			}
			if (empty($msg)) {
				$msg = 'Staff seed failed. Please check the error logs.';
			}
			if (function_exists('wp_redirect') && function_exists('admin_url')) {
				wp_redirect(admin_url('admin.php?page=nds-settings&staff_seed=error&msg=' . rawurlencode($msg)));
				exit;
			}
		} else {
			$msg = "Created: {$result['created']}, Skipped: {$result['skipped']}, Total: {$result['total']}";
			if (isset($result['errors']) && is_array($result['errors']) && !empty($result['errors'])) {
				$msg .= ' (Note: Some errors occurred: ' . implode('; ', $result['errors']) . ')';
			}
			if (function_exists('wp_redirect') && function_exists('admin_url')) {
				wp_redirect(admin_url('admin.php?page=nds-settings&staff_seed=success&msg=' . rawurlencode($msg)));
				exit;
			}
		}
	});

	add_action('admin_post_nds_reset_staff', function () {
		if (function_exists('current_user_can') && !current_user_can('manage_options')) {
			if (function_exists('wp_die')) wp_die('Unauthorized');
		}
		if (function_exists('check_admin_referer')) check_admin_referer('nds_reset_staff_nonce');
		
		$result = nds_reset_staff_data();
		
		if (is_array($result) && isset($result['error'])) {
			$msg = $result['error'];
			if (function_exists('wp_redirect') && function_exists('admin_url')) {
				wp_redirect(admin_url('admin.php?page=nds-settings&staff_reset=error&msg=' . rawurlencode($msg)));
				exit;
			}
		} else {
			if (function_exists('wp_redirect') && function_exists('admin_url')) {
				wp_redirect(admin_url('admin.php?page=nds-settings&staff_reset=success'));
				exit;
			}
		}
	});

	add_action('admin_post_nds_wipe_core_tables', function () {
		if (function_exists('current_user_can') && !current_user_can('manage_options')) {
			if (function_exists('wp_die')) wp_die('Unauthorized');
		}
		if (function_exists('check_admin_referer')) check_admin_referer('nds_wipe_core_tables_nonce');
		
		$result = nds_wipe_core_tables();
		
		if (is_array($result) && isset($result['error'])) {
			$msg = $result['error'];
			if (function_exists('wp_redirect') && function_exists('admin_url')) {
				wp_redirect(admin_url('admin.php?page=nds-settings&wipe=error&msg=' . rawurlencode($msg)));
				exit;
			}
		} else {
			$msg = isset($result['message']) ? $result['message'] : 'Core tables wiped successfully';
			if (function_exists('wp_redirect') && function_exists('admin_url')) {
				wp_redirect(admin_url('admin.php?page=nds-settings&wipe=success&msg=' . rawurlencode($msg)));
				exit;
			}
		}
	});
}

