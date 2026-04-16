<?php
// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Assign Lecturers to Programs & Courses (Drag & Drop UI)
 */
function nds_assign_lecturers_page_content() {
	if (!current_user_can('manage_options')) {
		wp_die('Unauthorized');
	}

	global $wpdb;

	$staff_table      = $wpdb->prefix . 'nds_staff';
	$courses_table    = $wpdb->prefix . 'nds_courses';
	$programs_table   = $wpdb->prefix . 'nds_programs';
	$faculties_table  = $wpdb->prefix . 'nds_faculties';
	$link_table       = $wpdb->prefix . 'nds_course_lecturers';

	// Faculties for filter
	$faculties = $wpdb->get_results("SELECT id, name FROM {$faculties_table} ORDER BY name", ARRAY_A);
	$selected_faculty_id = isset($_GET['faculty_id']) ? intval($_GET['faculty_id']) : 0;
	if (!$selected_faculty_id && !empty($faculties)) {
		$selected_faculty_id = (int) $faculties[0]['id'];
	}

	// Lecturers only
	$lecturers = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$staff_table} WHERE role = %s ORDER BY first_name, last_name",
			'Lecturer'
		),
		ARRAY_A
	);

	// Programs + Courses for selected faculty
	$programs = [];
	$courses_by_program = [];

	if ($selected_faculty_id) {
		$programs = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, name, description 
                 FROM {$programs_table}
                 WHERE faculty_id = %d
                 ORDER BY name",
				$selected_faculty_id
			),
			ARRAY_A
		);

		if ($programs) {
			$program_ids = wp_list_pluck($programs, 'id');
			$placeholders = implode(',', array_fill(0, count($program_ids), '%d'));

			$courses = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, program_id, name, code, description 
                     FROM {$courses_table}
                     WHERE program_id IN ($placeholders)
                     ORDER BY name",
					$program_ids
				),
				ARRAY_A
			);

			foreach ($courses as $course) {
				$courses_by_program[ $course['program_id'] ][] = $course;
			}
		}
	}

	// Current assignments for visible courses
	$current_assignments = [];
	if (!empty($courses_by_program)) {
		$course_ids = [];
		foreach ($courses_by_program as $pid => $course_list) {
			foreach ($course_list as $c) {
				$course_ids[] = (int) $c['id'];
			}
		}
		if (!empty($course_ids)) {
			// Sanitize course IDs and build query safely
			$course_ids = array_map('intval', $course_ids);
			$course_ids = array_filter($course_ids, function($id) { return $id > 0; });
			
			if (!empty($course_ids)) {
				// Build IN clause safely with sanitized integers
				$course_ids_clean = array_map('intval', $course_ids);
				$placeholders = implode(',', array_fill(0, count($course_ids_clean), '%d'));
				
				// Use call_user_func_array for variable number of arguments
				$query = "SELECT cl.course_id, s.id as lecturer_id, s.first_name, s.last_name
                     FROM {$link_table} cl
                     JOIN {$staff_table} s ON s.id = cl.lecturer_id
                     WHERE cl.course_id IN ($placeholders)
                     ORDER BY s.first_name, s.last_name";
				
				$prepared = call_user_func_array(
					array($wpdb, 'prepare'),
					array_merge(array($query), $course_ids_clean)
				);
				
				$rows = $wpdb->get_results($prepared, ARRAY_A);

				if ($rows && is_array($rows)) {
					foreach ($rows as $row) {
						$cid = (int) $row['course_id'];
						if (!isset($current_assignments[ $cid ])) {
							$current_assignments[ $cid ] = [];
						}
						$current_assignments[ $cid ][] = [
							'id'   => (int) $row['lecturer_id'],
							'name' => trim($row['first_name'] . ' ' . $row['last_name']),
						];
					}
				}
			}
		}
	}

	// Nonce for bulk save
	$nonce = wp_create_nonce('nds_bulk_assign_lecturers');

	// Include loading component
	require_once plugin_dir_path(__FILE__) . 'common.php';
	KIT_Commons::loadingCenterCenter('Saving assignments...');

	// Check for success message
	$success_msg = isset($_GET['bulk_assign']) && $_GET['bulk_assign'] === 'success' ? true : false;
	
	// Calculate statistics
	$total_lecturers = count($lecturers);
	$total_programs = count($programs);
	$total_courses = 0;
	foreach ($courses_by_program as $courses_list) {
		$total_courses += count($courses_list);
	}
	$total_assignments = $wpdb->get_var("SELECT COUNT(*) FROM {$link_table}");
	$selected_faculty_name = '';
	if ($selected_faculty_id) {
		$faculty_row = $wpdb->get_row($wpdb->prepare("SELECT name FROM {$faculties_table} WHERE id = %d", $selected_faculty_id), ARRAY_A);
		$selected_faculty_name = $faculty_row ? $faculty_row['name'] : '';
	}

	// --- NEW: Fetch lists for modals ---
	$all_assignments_list = $wpdb->get_results("
		SELECT cl.id, s.first_name, s.last_name, c.name as course_name 
		FROM {$link_table} cl
		JOIN {$staff_table} s ON cl.lecturer_id = s.id
		JOIN {$courses_table} c ON cl.course_id = c.id
		ORDER BY s.first_name, s.last_name
	", ARRAY_A);

	$flat_courses_list = [];
	foreach ($courses_by_program as $p_courses) {
		foreach ($p_courses as $c) {
			$flat_courses_list[] = $c;
		}
	}
	
	?>
	<style>
		/* Ensure the WordPress footer doesn't overlap our custom dashboard */
		body[class*="nds-assign-lecturers"] #wpfooter { display: none !important; }
		.nds-tailwind-wrapper { position: relative; z-index: 1; }
	</style>
	<div class="nds-tailwind-wrapper bg-gray-50 pb-32" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin-left: -20px; padding-left: 20px; margin-top: -20px;">
		<!-- Breadcrumb Navigation -->
		<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-6">
			<nav class="flex items-center space-x-2 text-sm text-gray-600 mb-4">
				<a href="<?php echo admin_url('admin.php?page=nds-academy'); ?>" class="hover:text-blue-600 transition-colors">
					<i class="fas fa-home mr-1"></i>NDS Academy
				</a>
				<i class="fas fa-chevron-right text-gray-400"></i>
				<a href="<?php echo admin_url('admin.php?page=nds-staff'); ?>" class="hover:text-blue-600 transition-colors">
					Staff
				</a>
				<i class="fas fa-chevron-right text-gray-400"></i>
				<span class="text-gray-900 font-medium">Assign Lecturers</span>
			</nav>
		</div>

		<!-- Header -->
		<div class="bg-white shadow-sm border-b border-gray-200">
			<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
				<div class="flex justify-between items-center py-6">
					<div class="flex items-center space-x-4">
						<div class="w-12 h-12 bg-gradient-to-br from-blue-600 to-indigo-600 rounded-xl flex items-center justify-center">
							<i class="fas fa-chalkboard-teacher text-white text-xl"></i>
						</div>
						<div>
							<h1 class="text-3xl font-bold text-gray-900" style="margin:0; line-height:1.2;">Assign Lecturers</h1>
							<p class="text-gray-600" style="margin:0;">Manage lecturer assignments across programs and courses within the academy.</p>
						</div>
					</div>
					<div class="flex items-center space-x-3">
						<div class="text-right">
							<p class="text-xs uppercase tracking-wide text-gray-500">Global Overview</p>
							<p class="text-sm font-medium text-gray-900"><?php echo esc_html(date_i18n('M j, Y')); ?></p>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Main content -->
		<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
			<?php if ($success_msg): ?>
				<div class="bg-green-50 border-l-4 border-green-400 p-4 rounded-lg shadow-sm">
					<div class="flex items-center">
						<div class="flex-shrink-0">
							<i class="fas fa-check-circle text-green-400 text-xl"></i>
						</div>
						<div class="ml-3">
							<p class="text-sm font-medium text-green-800">
								Success! Lecturer assignments have been saved.
							</p>
						</div>
					</div>
				</div>
			<?php endif; ?>

			<!-- Statistics Cards -->
			<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
				<!-- Total Lecturers -->
				<div onclick="openStatModal('lecturers')" class="bg-white shadow-sm rounded-xl p-5 border border-gray-100 flex flex-col justify-between hover:bg-gray-50 transition-all duration-200 cursor-pointer group">
					<div class="flex items-center justify-between">
						<div>
							<p class="text-sm font-medium text-gray-500 group-hover:text-gray-700">Available Lecturers</p>
							<p class="mt-2 text-2xl font-semibold text-gray-900">
								<?php echo number_format_i18n($total_lecturers); ?>
							</p>
						</div>
						<div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center">
							<i class="fas fa-user-tie text-blue-600 text-xl"></i>
						</div>
					</div>
				</div>

				<!-- Total Programs -->
				<div onclick="openStatModal('programs')" class="bg-white shadow-sm rounded-xl p-5 border border-gray-100 flex flex-col justify-between hover:bg-gray-50 transition-all duration-200 cursor-pointer group">
					<div class="flex items-center justify-between">
						<div>
							<p class="text-sm font-medium text-gray-500 group-hover:text-gray-700">Programs</p>
							<p class="mt-2 text-2xl font-semibold text-gray-900">
								<?php echo number_format_i18n($total_programs); ?>
							</p>
						</div>
						<div class="w-10 h-10 rounded-lg bg-emerald-50 flex items-center justify-center">
							<i class="fas fa-graduation-cap text-emerald-600 text-xl"></i>
						</div>
					</div>
					<?php if ($selected_faculty_name): ?>
						<p class="mt-3 text-xs text-gray-500">
							in <span class="font-medium text-gray-800"><?php echo esc_html($selected_faculty_name); ?></span>
						</p>
					<?php endif; ?>
				</div>

				<!-- Total Courses -->
				<div onclick="openStatModal('courses')" class="bg-white shadow-sm rounded-xl p-5 border border-gray-100 flex flex-col justify-between hover:bg-gray-50 transition-all duration-200 cursor-pointer group">
					<div class="flex items-center justify-between">
						<div>
							<p class="text-sm font-medium text-gray-500 group-hover:text-gray-700">Courses</p>
							<p class="mt-2 text-2xl font-semibold text-gray-900">
								<?php echo number_format_i18n($total_courses); ?>
							</p>
						</div>
						<div class="w-10 h-10 rounded-lg bg-purple-50 flex items-center justify-center">
							<i class="fas fa-book text-purple-600 text-xl"></i>
						</div>
					</div>
				</div>

				<!-- Total Assignments -->
				<div onclick="openStatModal('assignments')" class="bg-white shadow-sm rounded-xl p-5 border border-gray-100 flex flex-col justify-between hover:bg-gray-50 transition-all duration-200 cursor-pointer group">
					<div class="flex items-center justify-between">
						<div>
							<p class="text-sm font-medium text-gray-500 group-hover:text-gray-700">Total Assignments</p>
							<p class="mt-2 text-2xl font-semibold text-gray-900">
								<?php echo number_format_i18n($total_assignments); ?>
							</p>
						</div>
						<div class="w-10 h-10 rounded-lg bg-orange-50 flex items-center justify-center">
							<i class="fas fa-link text-orange-600 text-xl"></i>
						</div>
					</div>
					<p class="mt-3 text-xs text-gray-500">
						across all courses
					</p>
				</div>
			</div>

			<!-- Faculty Filter -->
			<div class="bg-white shadow-sm rounded-xl border border-gray-100 p-5">
				<form method="get" id="nds-faculty-filter-form">
					<input type="hidden" name="page" value="nds-assign-lecturers">
					<div class="flex items-center space-x-4">
						<label class="block text-sm font-semibold text-gray-700 whitespace-nowrap">Filter by Faculty:</label>
						<select name="faculty_id" class="flex-1 max-w-md px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all" onchange="if(typeof window.ndsShowLoading === 'function') window.ndsShowLoading('Loading faculty data...'); this.form.submit();">
							<?php foreach ($faculties as $faculty): ?>
								<option value="<?php echo intval($faculty['id']); ?>" <?php selected($selected_faculty_id, $faculty['id']); ?>>
									<?php echo esc_html($faculty['name']); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<?php if ($selected_faculty_name): ?>
							<div class="flex items-center text-sm text-gray-600">
								<i class="fas fa-info-circle mr-2"></i>
								<span>Showing programs and courses for <strong><?php echo esc_html($selected_faculty_name); ?></strong></span>
							</div>
						<?php endif; ?>
					</div>
				</form>
			</div>

			<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="nds-assign-form">
				<input type="hidden" name="action" value="nds_bulk_assign_lecturers">
				<input type="hidden" name="_wpnonce" value="<?php echo esc_attr($nonce); ?>">
				<input type="hidden" name="faculty_id" value="<?php echo intval($selected_faculty_id); ?>">
				<input type="hidden" name="assignments_json" id="nds-assignments-json" value="">

				<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-stretch">
					<!-- LEFT: Lecturers (sticky sidebar) -->
					<div class="flex flex-col self-start" style="position: sticky; top: 120px; max-height: calc(100vh - 140px); z-index: 1;">
						<div class="bg-white border border-gray-200 rounded-xl shadow-sm flex flex-col" style="height: 100%;">
							<div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
								<div>
									<h2 class="text-lg font-semibold text-gray-900">Available Lecturers</h2>
									<p class="text-xs text-gray-500 mt-1">Drag or click to assign to courses</p>
								</div>
							</div>
							<div class="px-5 py-3 border-b border-gray-100">
								<input
									type="text"
									id="nds-lecturer-search"
									placeholder="Search lecturers by name or email…"
									class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
								/>
							</div>
							<div id="nds-lecturer-list" class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-3 overflow-y-auto flex-1" style="max-height: calc(100vh - 280px);">
							<?php if ($lecturers): ?>
								<?php foreach ($lecturers as $lecturer): ?>
									<?php
									$lid   = (int) $lecturer['id'];
									$name  = trim($lecturer['first_name'] . ' ' . $lecturer['last_name']);
									$email = $lecturer['email'];
									$role  = $lecturer['role'];
									?>
									<div
										class="nds-lecturer-card cursor-move bg-gray-50 border border-gray-200 rounded-lg p-3 flex items-center gap-3 hover:bg-blue-50 hover:border-blue-300 hover:shadow-md transition-all duration-200 group"
										draggable="true"
										data-lecturer-id="<?php echo $lid; ?>"
										data-lecturer-name="<?php echo esc_attr($name); ?>"
									>
										<div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-blue-600 text-white flex items-center justify-center font-semibold shadow-sm group-hover:shadow-md transition-shadow">
											<?php echo esc_html(mb_strimwidth($lecturer['first_name'], 0, 1) . mb_strimwidth($lecturer['last_name'], 0, 1)); ?>
										</div>
										<div class="flex-1 min-w-0">
											<div class="font-semibold text-sm text-gray-900 truncate"><?php echo esc_html($name); ?></div>
											<div class="text-xs text-gray-500 truncate"><?php echo esc_html($email); ?></div>
											<div class="mt-1 inline-flex items-center px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 text-xs font-medium">
												<i class="fas fa-chalkboard-teacher mr-1 text-xs"></i>
												<?php echo esc_html($role); ?>
											</div>
										</div>
										<div class="opacity-0 group-hover:opacity-100 transition-opacity">
											<i class="fas fa-grip-vertical text-gray-400 text-xs"></i>
										</div>
									</div>
								<?php endforeach; ?>
							<?php else: ?>
								<div class="col-span-2 text-center py-12">
									<i class="fas fa-user-tie text-4xl text-gray-300 mb-3"></i>
									<p class="text-gray-500 text-sm">No lecturers found. Add staff with role "Lecturer" first.</p>
									<a href="<?php echo admin_url('admin.php?page=nds-add-staff'); ?>" class="mt-3 inline-block text-blue-600 hover:text-blue-700 text-sm font-medium">
										Add Staff Member →
									</a>
								</div>
							<?php endif; ?>
							</div>
						</div>
					</div>

					<!-- RIGHT: Programs & Courses -->
					<div class="bg-white border border-gray-200 rounded-xl shadow-sm flex flex-col">
						<div class="px-5 py-4 border-b border-gray-100">
							<div class="flex items-center justify-between">
								<div>
									<h2 class="text-lg font-semibold text-gray-900">Programs & Courses</h2>
									<p class="text-xs text-gray-500 mt-1">Drop lecturers on a program to assign all courses, or on individual courses</p>
								</div>
							</div>
						</div>

						<?php if (empty($programs)): ?>
							<div class="p-12 text-center">
								<i class="fas fa-graduation-cap text-4xl text-gray-300 mb-3"></i>
								<p class="text-gray-500 text-sm mb-2">
									No programs found for this faculty.
								</p>
								<p class="text-gray-400 text-xs">
									Create programs and courses first.
								</p>
								<a href="<?php echo admin_url('admin.php?page=nds-programs'); ?>" class="mt-4 inline-block text-blue-600 hover:text-blue-700 text-sm font-medium">
									Create Program →
								</a>
							</div>
						<?php else: ?>
							<div class="p-5 space-y-4 overflow-y-auto flex-1" id="nds-program-course-list" style="max-height: calc(100vh - 280px);">
								<?php foreach ($programs as $program): ?>
									<?php
									$pid          = (int) $program['id'];
									$program_name = $program['name'];
									$program_courses = isset($courses_by_program[ $pid ]) ? $courses_by_program[ $pid ] : [];
									$course_ids   = array_map(static function($c) { return (int) $c['id']; }, $program_courses);
									?>
									<div class="border border-gray-200 rounded-lg overflow-hidden bg-gradient-to-br from-gray-50 to-white shadow-sm hover:shadow-md transition-shadow">
										<div
											class="nds-program-drop px-4 py-3 bg-gradient-to-r from-blue-50 to-indigo-50 border-b border-gray-200 flex items-center justify-between cursor-pointer hover:from-blue-100 hover:to-indigo-100 transition-all group"
											data-program-id="<?php echo $pid; ?>"
											data-course-ids="<?php echo esc_attr(implode(',', $course_ids)); ?>"
										>
											<div class="flex items-center space-x-3">
												<div class="w-10 h-10 rounded-lg bg-blue-500 flex items-center justify-center shadow-sm group-hover:shadow-md transition-shadow">
													<i class="fas fa-graduation-cap text-white text-sm"></i>
												</div>
												<div>
													<div class="font-semibold text-gray-900"><?php echo esc_html($program_name); ?></div>
													<div class="text-xs text-gray-600 flex items-center mt-1">
														<i class="fas fa-book mr-1"></i>
														<?php echo count($program_courses); ?> course<?php echo count($program_courses) === 1 ? '' : 's'; ?>
													</div>
												</div>
											</div>
											<div class="flex items-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
												<span class="text-xs text-gray-600 font-medium">Drop here for all courses</span>
												<span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-blue-500 text-white text-xs shadow-sm">
													<i class="fas fa-arrow-down"></i>
												</span>
											</div>
										</div>
										<?php if (!empty($program_courses)): ?>
											<div class="border-t border-gray-200 p-4 grid grid-cols-1 md:grid-cols-2 gap-3 bg-white">
												<?php foreach ($program_courses as $course): ?>
													<?php
													$cid          = (int) $course['id'];
													$course_name  = $course['name'];
													$course_code  = $course['code'];
													$assigned     = isset($current_assignments[ $cid ]) ? $current_assignments[ $cid ] : [];
													?>
													<div
														class="nds-course-drop bg-white border-2 border-gray-200 rounded-lg p-3 hover:border-blue-400 hover:shadow-md transition-all cursor-pointer group"
														data-course-id="<?php echo $cid; ?>"
													>
														<div class="flex justify-between items-start mb-2">
															<div class="flex-1 min-w-0">
																<div class="font-medium text-sm text-gray-900 truncate"><?php echo esc_html($course_name); ?></div>
																<?php if (!empty($course_code)): ?>
																	<div class="text-xs text-gray-500 mt-0.5"><?php echo esc_html($course_code); ?></div>
																<?php endif; ?>
															</div>
															<div class="ml-2 opacity-0 group-hover:opacity-100 transition-opacity">
																<i class="fas fa-hand-pointer text-blue-400 text-xs"></i>
															</div>
														</div>
														<div
															class="nds-course-lecturers flex flex-wrap gap-1.5 min-h-[2rem] text-xs mt-2"
															data-course-id="<?php echo $cid; ?>"
														>
															<?php if ($assigned): ?>
																<?php foreach ($assigned as $lect): ?>
																	<span
																		class="nds-lecturer-pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-blue-100 text-blue-800 font-medium shadow-sm"
																		data-lecturer-id="<?php echo intval($lect['id']); ?>"
																	>
																		<i class="fas fa-user-tie text-xs"></i>
																		<?php echo esc_html($lect['name']); ?>
																	</span>
																<?php endforeach; ?>
															<?php else: ?>
																<span class="nds-course-placeholder text-xs text-gray-400 italic flex items-center">
																	<i class="fas fa-info-circle mr-1"></i>
																	No lecturers assigned yet
																</span>
															<?php endif; ?>
														</div>
													</div>
												<?php endforeach; ?>
											</div>
										<?php else: ?>
											<div class="p-4 text-center bg-gray-50">
												<p class="text-xs text-gray-500">
													<i class="fas fa-info-circle mr-1"></i>
													No courses in this program yet.
												</p>
											</div>
										<?php endif; ?>
									</div>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					</div>
				</div>

				<!-- Save Button Section -->
				<div class="bg-white shadow-sm rounded-xl border border-gray-100 p-5">
					<div class="flex items-center justify-between">
						<div class="flex items-center space-x-3 text-sm text-gray-600">
							<i class="fas fa-info-circle text-blue-500"></i>
							<span>
								Assignments are only saved when you click <strong class="text-gray-900">Save Assignments</strong>. 
								You can drag and drop as many combinations as you like first.
							</span>
						</div>
						<button
							type="submit"
							class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white font-semibold rounded-lg shadow-sm hover:from-blue-700 hover:to-blue-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200 hover:shadow-md"
							id="nds-assign-save-btn"
						>
							<i class="fas fa-save mr-2"></i>
							Save Assignments
						</button>
					</div>
				</div>
			</form>
			<!-- Clearfix/Spacer to ensure footer is pushed down -->
			<div style="clear: both; height: 1px;"></div>
		</div>
	</div>

	<script>
		(function() {
			const lecturerCards = document.querySelectorAll('.nds-lecturer-card');
			const programDrops  = document.querySelectorAll('.nds-program-drop');
			const courseDrops   = document.querySelectorAll('.nds-course-drop');
			const lecturerSearchInput = document.getElementById('nds-lecturer-search');
			const form          = document.getElementById('nds-assign-form');
			const assignmentsInput = document.getElementById('nds-assignments-json');

			// Currently selected lecturer for click-to-assign shortcut
			let activeLecturerId = null;

			// assignments[courseId] = { lecturerId: true }
			const assignments = {};

			// Seed with existing lecturer pills
			document.querySelectorAll('.nds-course-lecturers').forEach(container => {
				const courseId = container.getAttribute('data-course-id');
				if (!courseId) return;
				assignments[courseId] = assignments[courseId] || {};

				container.querySelectorAll('.nds-lecturer-pill').forEach(pill => {
					const lid = pill.getAttribute('data-lecturer-id');
					if (lid) {
						assignments[courseId][lid] = true;
					}
				});
			});

			// Drag start + click-to-assign activation
			lecturerCards.forEach(card => {
				const lecturerId = card.getAttribute('data-lecturer-id');

				card.addEventListener('dragstart', (e) => {
					e.dataTransfer.setData('text/plain', lecturerId);
					e.dataTransfer.effectAllowed = 'copyMove';
					card.classList.add('opacity-60', 'ring-2', 'ring-blue-400');
				});

				card.addEventListener('dragend', () => {
					card.classList.remove('opacity-60', 'ring-2', 'ring-blue-400');
				});

				// Click once to "arm" a lecturer for quicker assigning
				card.addEventListener('click', () => {
					// Toggle behaviour: clicking the same card again will unselect it
					if (activeLecturerId === lecturerId) {
						activeLecturerId = null;
					} else {
						activeLecturerId = lecturerId;
					}

					// Visual state
					lecturerCards.forEach(other => {
						other.classList.remove('ring-2', 'ring-blue-500', 'bg-blue-50');
					});
					if (activeLecturerId) {
						card.classList.add('ring-2', 'ring-blue-500', 'bg-blue-50');
					}
				});
			});

			function getLecturerNameById(id) {
				const card = document.querySelector('.nds-lecturer-card[data-lecturer-id="' + id + '"]');
				return card ? card.getAttribute('data-lecturer-name') : 'Lecturer #' + id;
			}

			function addAssignment(courseId, lecturerId) {
				if (!courseId || !lecturerId) return;

				assignments[courseId] = assignments[courseId] || {};
				if (assignments[courseId][lecturerId]) {
					return; // already assigned
				}
				assignments[courseId][lecturerId] = true;

				const container = document.querySelector('.nds-course-lecturers[data-course-id="' + courseId + '"]');
				if (!container) return;

				// Remove placeholder text if present
				const placeholder = container.querySelector('.nds-course-placeholder');
				if (placeholder) {
					placeholder.remove();
				}

				// Add pill
				const pill = document.createElement('span');
				pill.className = 'nds-lecturer-pill inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-blue-100 text-blue-800 text-xs';
				pill.setAttribute('data-lecturer-id', lecturerId);
				pill.textContent = getLecturerNameById(lecturerId);
				container.appendChild(pill);
			}

			function handleDropOnCourse(e, courseId) {
				e.preventDefault();
				const fromDrag = e.dataTransfer && e.dataTransfer.getData('text/plain');
				const lecturerId = fromDrag || activeLecturerId;
				if (!lecturerId) return;
				addAssignment(courseId, lecturerId);
			}

			function handleDropOnProgram(e, programEl) {
				e.preventDefault();
				const fromDrag = e.dataTransfer && e.dataTransfer.getData('text/plain');
				const lecturerId = fromDrag || activeLecturerId;
				if (!lecturerId) return;

				const courseIdsStr = programEl.getAttribute('data-course-ids') || '';
				if (!courseIdsStr) return;

				const courseIds = courseIdsStr.split(',').map(id => id.trim()).filter(Boolean);
				courseIds.forEach(cid => {
					addAssignment(cid, lecturerId);
				});
			}

			// Setup drop targets for courses (+ click-to-assign)
			courseDrops.forEach(drop => {
				const courseId = drop.getAttribute('data-course-id');

				drop.addEventListener('dragover', (e) => {
					e.preventDefault();
					e.dataTransfer.dropEffect = 'copy';
					drop.classList.add('ring-2', 'ring-blue-400', 'ring-offset-2');
				});

				drop.addEventListener('dragleave', () => {
					drop.classList.remove('ring-2', 'ring-blue-400', 'ring-offset-2');
				});

				drop.addEventListener('drop', (e) => {
					drop.classList.remove('ring-2', 'ring-blue-400', 'ring-offset-2');
					handleDropOnCourse(e, courseId);
				});

				// Click a course to assign the currently "armed" lecturer
				drop.addEventListener('click', (e) => {
					// Ignore clicks that originated from inside an existing pill
					if ((e.target && e.target.closest('.nds-lecturer-pill')) || !activeLecturerId) {
						return;
					}
					handleDropOnCourse(e, courseId);
				});
			});

			// Setup drop targets for programs (+ click-to-assign)
			programDrops.forEach(drop => {
				drop.addEventListener('dragover', (e) => {
					e.preventDefault();
					e.dataTransfer.dropEffect = 'copy';
					drop.classList.add('ring-2', 'ring-blue-400', 'ring-offset-2');
				});

				drop.addEventListener('dragleave', () => {
					drop.classList.remove('ring-2', 'ring-blue-400', 'ring-offset-2');
				});

				drop.addEventListener('drop', (e) => {
					drop.classList.remove('ring-2', 'ring-blue-400', 'ring-offset-2');
					handleDropOnProgram(e, drop);
				});

				// Click a program header to assign to all its courses
				drop.addEventListener('click', (e) => {
					// Only act if a lecturer is armed
					if (!activeLecturerId) {
						return;
					}
					handleDropOnProgram(e, drop);
				});
			});

			// Simple lecturer search filter
			if (lecturerSearchInput) {
				lecturerSearchInput.addEventListener('input', () => {
					const term = lecturerSearchInput.value.toLowerCase();
					document.querySelectorAll('.nds-lecturer-card').forEach(card => {
						const name  = (card.getAttribute('data-lecturer-name') || '').toLowerCase();
						const email = (card.querySelector('.text-xs')?.textContent || '').toLowerCase();
						if (!term || name.includes(term) || email.includes(term)) {
							card.classList.remove('hidden');
						} else {
							card.classList.add('hidden');
						}
					});
				});
			}

			// Before submit: serialize assignments into JSON and show loading
			if (form && assignmentsInput) {
				form.addEventListener('submit', (e) => {
					const payload = [];
					Object.keys(assignments).forEach(courseId => {
						const lecturersForCourse = assignments[courseId];
						Object.keys(lecturersForCourse).forEach(lecturerId => {
							if (lecturersForCourse[lecturerId]) {
								payload.push({
									course_id: parseInt(courseId, 10),
									lecturer_id: parseInt(lecturerId, 10)
								});
							}
						});
					});

					assignmentsInput.value = JSON.stringify(payload);
					
					// Show loading overlay before form submits
					if (typeof window.ndsShowLoading === 'function') {
						window.ndsShowLoading('Saving assignments...');
					}
				});
			}
		})();
	</script>
	</div>

	<!-- Drill-down Stat Modal -->
	<div id="drillDownModal" class="hidden" style="position:fixed; inset:0; z-index:999999; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
		<div style="position:fixed; inset:0; background:rgba(0,0,0,0.5);" onclick="closeStatModal()"></div>
		<div style="position:fixed; inset:0; display:flex; align-items:center; justify-content:center; padding:1rem;">
			<div style="background:#fff; border-radius:1rem; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25); width:100%; max-width:42rem; max-height:80vh; display:flex; flex-direction:column; position:relative;">
				<!-- Modal Header -->
				<div style="display:flex; align-items:center; justify-content:space-between; padding:1rem 1.5rem; border-bottom:1px solid #e5e7eb;">
					<div style="display:flex; align-items:center; gap:0.75rem;">
						<div id="modalIconBg" style="width:2.5rem; height:2.5rem; border-radius:0.5rem; display:flex; align-items:center; justify-content:center;">
							<i id="modalIcon" style="font-size:1.25rem;"></i>
						</div>
						<div>
							<h3 id="modalTitle" style="font-size:1.125rem; font-weight:700; color:#111827; margin:0;"></h3>
							<p id="modalCount" style="font-size:0.875rem; color:#6b7280; margin:0;"></p>
						</div>
					</div>
					<button onclick="closeStatModal()" style="color:#9ca3af; padding:0.5rem; border-radius:0.5rem; border:none; background:none; cursor:pointer;" onmouseover="this.style.color='#4b5563'; this.style.background='#f3f4f6'" onmouseout="this.style.color='#9ca3af'; this.style.background='none'">
						<i class="fas fa-times" style="font-size:1.25rem;"></i>
					</button>
				</div>
				<!-- Modal Body -->
				<div style="overflow-y:auto; flex:1; padding:0.5rem;">
					<table style="width:100%; border-collapse:collapse;">
						<thead style="background:#f9fafb; position:sticky; top:0; z-index:10;">
							<tr>
								<th id="col1Header" style="padding:0.75rem 1rem; text-align:left; font-size:0.75rem; font-weight:500; color:#6b7280; text-transform:uppercase;">Name</th>
								<th id="col2Header" style="padding:0.75rem 1rem; text-align:left; font-size:0.75rem; font-weight:500; color:#6b7280; text-transform:uppercase;">Details</th>
							</tr>
						</thead>
						<tbody id="modalBody"></tbody>
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
	document.addEventListener('DOMContentLoaded', function() {
		const statsData = {
			lecturers: <?php echo json_encode($lecturers); ?>,
			programs: <?php echo json_encode($programs); ?>,
			courses: <?php echo json_encode($flat_courses_list); ?>,
			assignments: <?php echo json_encode($all_assignments_list); ?>
		};

		const modalConfig = {
			lecturers: {
				title: 'Available Lecturers',
				icon: 'fas fa-user-tie',
				iconColor: '#2563eb',
				iconBg: '#eff6ff',
				col1: 'Lecturer Name',
				col2: 'Role'
			},
			programs: {
				title: 'Academic Programs',
				icon: 'fas fa-graduation-cap',
				iconColor: '#059669',
				iconBg: '#ecfdf5',
				col1: 'Program Name',
				col2: 'Description'
			},
			courses: {
				title: 'Available Courses',
				icon: 'fas fa-book',
				iconColor: '#7c3aed',
				iconBg: '#f5f3ff',
				col1: 'Course Name',
				col2: 'Code'
			},
			assignments: {
				title: 'Global Assignments',
				icon: 'fas fa-link',
				iconColor: '#d97706',
				iconBg: '#fffbeb',
				col1: 'Lecturer',
				col2: 'Course'
			}
		};

		window.openStatModal = function(type) {
			const modal = document.getElementById('drillDownModal');
			const config = modalConfig[type];
			const data = statsData[type];
			
			if (!modal || !config || !data) return;

			document.getElementById('modalTitle').textContent = config.title;
			document.getElementById('modalCount').textContent = data.length + ' item' + (data.length !== 1 ? 's' : '');
			document.getElementById('col1Header').textContent = config.col1;
			document.getElementById('col2Header').textContent = config.col2;
			
			const modalIcon = document.getElementById('modalIcon');
			const modalIconBg = document.getElementById('modalIconBg');
			modalIcon.className = config.icon;
			modalIcon.style.color = config.iconColor;
			modalIconBg.style.backgroundColor = config.iconBg;

			const tbody = document.getElementById('modalBody');
			tbody.innerHTML = '';
			
			data.forEach(item => {
				const row = document.createElement('tr');
				row.style.cssText = 'border-bottom:1px solid #f3f4f6; transition: background 0.15s;';
				row.onmouseover = function() { this.style.background = '#f9fafb'; };
				row.onmouseout = function() { this.style.background = ''; };
				
				let name = '', details = '';
				if (type === 'lecturers') {
					name = `${item.first_name || ''} ${item.last_name || ''}`;
					details = item.role || 'Lecturer';
				} else if (type === 'programs') {
					name = item.name;
					details = item.description ? item.description.substring(0, 60) + '...' : 'N/A';
				} else if (type === 'courses') {
					name = item.name;
					details = item.code || 'N/A';
				} else if (type === 'assignments') {
					name = `${item.first_name || ''} ${item.last_name || ''}`;
					details = item.course_name || 'N/A';
				}

				row.innerHTML = `
					<td style="padding:0.75rem 1rem; font-size:0.875rem; font-weight:600; color:#111827;">${name}</td>
					<td style="padding:0.75rem 1rem; font-size:0.875rem; color:#4b5563;">${details}</td>
				`;
				tbody.appendChild(row);
			});

			modal.classList.remove('hidden');
			document.body.style.overflow = 'hidden';
		};

		window.closeStatModal = function() {
			const modal = document.getElementById('drillDownModal');
			if (modal) {
				modal.classList.add('hidden');
				document.body.style.overflow = '';
			}
		};
	});
	</script>
<?php
}

