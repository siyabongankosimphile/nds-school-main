<?php
/**
 * Import NDS Database System.xlsx into the NDS School database.
 *
 * - Matches Excel sheets to DB tables; respects FK order (parents before children).
 * - Skips any column whose name ends with _ignore (e.g. Faculty_Name_ignore, Room_Name_Ignore).
 * - Column names are normalized to DB style (e.g. Room_Id -> room_id).
 *
 * Usage:
 *   Dry run (no DB writes): php import-excel-to-database.php --dry-run
 *   Actual import:          php import-excel-to-database.php
 *   From WordPress:         require 'import-excel-to-database.php'; nds_import_excel_run();
 *
 * Re-import: If tables already have data, clear or truncate them first to avoid duplicate key errors.
 */

// Load WordPress when run standalone (plugin lives at wp-content/plugins/nds-school-main/)
if (!defined('ABSPATH')) {
    $wp_load = dirname(__FILE__) . '/../../../../wp-load.php';
    if (!file_exists($wp_load)) {
        $wp_load = dirname(__FILE__) . '/../../../wp-load.php';
    }
    if (!file_exists($wp_load)) {
        die("wp-load.php not found. Run from WordPress context or fix path.\n");
    }
    require_once $wp_load;
}

if (!function_exists('nds_import_excel_run')) {

/**
 * Sheet name (Excel) => table key (nds_* table without prefix).
 * Order matches database.php FK dependencies: parents before children.
 * IDs are always created by the DB on insert; Excel "id" column is ignored.
 *
 * Import order (so FKs are always the current auto_increment ids):
 *   1. Programs (top level) → 2. Faculties (under program) → 3. Courses (under faculty) → ...
 * Internally the physical tables remain nds_faculties (storing Programs) and nds_programs (storing Faculties),
 * but Excel uses real-world naming so sheets are ordered Program → Faculty → Course.
 *
 * Flow: Lookups → Programs (stored in nds_faculties) → Faculties (stored in nds_programs, linked to program)
 *       → Program_Levels → Staff, Rooms → Courses (linked to faculties) → Course_Schedules, Course_Accreditations, Students.
 */
function nds_import_excel_get_sheet_order() {
    return [
        'Accreditation_Bodies' => 'accreditation_bodies',
        'Program_Types'        => 'program_types',
        'Course_Categories'    => 'course_categories',
        // Excel "Programs" sheet = nds_faculties (top-level); "Faculties" sheet = nds_programs (under program)
        'Programs'             => 'faculties',
        'Faculties'             => 'programs',
        'Program_Levels'       => 'program_levels',
        'Staff'                => 'staff',
        'wp_nds_rooms'         => 'rooms',
        'Courses'              => 'courses',
        'Course_Schedules'     => 'course_schedules',
        'Course_Accreditations'=> 'course_accreditations',
        'Students'             => 'students',
    ];
}

/**
 * Normalize column name for DB: lowercase, spaces to underscores.
 * Excel may have "Room_Id" -> "room_id".
 */
function nds_import_excel_normalize_column($name) {
    $name = trim($name);
    $name = preg_replace('/\s+/', '_', $name);
    return strtolower($name);
}

/**
 * Filter out columns that end with _ignore (case-insensitive).
 *
 * @param array $row Associative array header => value
 * @return array Filtered row
 */
function nds_import_excel_filter_ignore_columns($row) {
    $out = [];
    foreach ($row as $col => $value) {
        $normalized = nds_import_excel_normalize_column($col);
        if (substr($normalized, -7) === '_ignore') {
            continue;
        }
        $out[$col] = $value;
    }
    return $out;
}

/**
 * Map row keys to DB column names (normalized). Only include keys that exist in row.
 * Trims string values so whitespace-only does not cause duplicate '' or bad inserts.
 *
 * @param array $row Keys are Excel header names (after filtering _ignore)
 * @return array DB column name => value
 */
function nds_import_excel_row_to_db_columns($row) {
    $out = [];
    foreach ($row as $excel_col => $value) {
        $db_col = nds_import_excel_normalize_column($excel_col);
        if ($db_col === '') {
            continue;
        }
        $out[$db_col] = is_string($value) ? trim($value) : $value;
    }
    return $out;
}

/**
 * Excel column aliases per table: normalized Excel header => DB column name (same type, e.g. code).
 */
function nds_import_excel_get_column_aliases($table_key) {
    $aliases = [
        'accreditation_bodies' => ['accreditation_body_code' => 'code'],
        'program_types'        => ['program_type_code' => 'code'],
        'course_categories'   => ['course_category_code' => 'code', 'category_code' => 'code'],
        'faculties'           => ['faculty_code' => 'code'],
        'programs'             => ['program_code' => 'code', 'program_type' => 'program_type_code', 'accrediting_body_ignore' => 'accreditation_body_name_ignore'],
        'program_levels'      => [],
        'staff'               => [],
        'rooms'                => ['room_code' => 'code'],
        'courses'              => ['course_code' => 'code', 'programme' => 'program_code', 'program' => 'program_code'],
        'course_schedules'     => [],
        'course_accreditations'=> [
            'course_name' => 'course_name_ignore',
            'accreditation_body' => 'accreditation_body_name_ignore',
            'accreditation_body_name' => 'accreditation_body_name_ignore',
        ],
        'students'             => ['student_code' => 'student_number'],
    ];
    return isset($aliases[$table_key]) ? $aliases[$table_key] : [];
}

/**
 * Tables that have a unique key (other than id) for ON DUPLICATE KEY UPDATE.
 * When re-importing, we update existing rows instead of skipping.
 * Key = table_key, Value = array of column names that form the unique key (used only to know we should use ODKU).
 */
function nds_import_excel_get_odku_tables() {
    return [
        'accreditation_bodies' => ['code'],
        'program_types'        => ['code'],
        'course_categories'    => ['code'],
        'faculties'            => ['code'],
        'programs'             => ['code'],
        'program_levels'       => ['program_id', 'level_number'],
        'rooms'                => ['code'],
        'courses'              => ['code'],
        'students'             => ['student_number'],
        'course_accreditations'=> ['course_id', 'accreditation_body_id'],
        'staff'                => ['email'], // if UNIQUE(email) exists; else INSERT always succeeds
    ];
}

/**
 * Apply column aliases so Excel headers like Program_Code map to DB column code.
 */
function nds_import_excel_apply_column_aliases($db_row, $table_key) {
    $aliases = nds_import_excel_get_column_aliases($table_key);
    if (empty($aliases)) {
        return $db_row;
    }
    foreach ($aliases as $excel_name => $db_col) {
        if (array_key_exists($excel_name, $db_row) && !array_key_exists($db_col, $db_row)) {
            $db_row[$db_col] = $db_row[$excel_name];
            unset($db_row[$excel_name]);
        }
    }
    return $db_row;
}

/**
 * Normalize a string for DB lookup (trim, collapse spaces, normalize apostrophes so Excel and DB match).
 */
function nds_import_excel_normalize_lookup_name($s) {
    if ($s === null || $s === '') {
        return '';
    }
    $s = is_string($s) ? trim($s) : (string) $s;
    $s = preg_replace('/[\s]+/u', ' ', $s);
    $s = str_replace(["\xe2\x80\x99", "\xe2\x80\x98", "\xe2\x80\x9b"], "'", $s);
    return $s;
}

/**
 * Look up id in a table by name/code: try exact name, exact code, then LIKE on name.
 * Uses normalized name so Excel apostrophes match DB.
 */
function nds_import_excel_lookup_id_by_name($wpdb, $table_full, $name) {
    // #region agent log
    $log_path = dirname(__FILE__) . '/.cursor/debug.log';
    $raw = $name;
    // #endregion
    $name = nds_import_excel_normalize_lookup_name($name);
    if ($name === '') {
        // #region agent log
        @file_put_contents($log_path, json_encode(['sessionId' => 'debug-session', 'runId' => 'import-run', 'hypothesisId' => 'H2', 'location' => __FILE__ . ':lookup_id_by_name', 'message' => 'lookup empty name', 'data' => ['table' => $table_full, 'raw' => $raw], 'timestamp' => round(microtime(true) * 1000)]) . "\n", FILE_APPEND | LOCK_EX);
        // #endregion
        return null;
    }
    $id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table_full} WHERE name = %s LIMIT 1", $name));
    if ($id !== null) {
        return (int) $id;
    }
    $id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table_full} WHERE code = %s LIMIT 1", $name));
    if ($id !== null) {
        return (int) $id;
    }
    $like = '%' . $wpdb->esc_like($name) . '%';
    $id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table_full} WHERE name LIKE %s LIMIT 1", $like));
    // #region agent log
    $rows_in_table = $wpdb->get_results("SELECT id, name, code FROM {$table_full}", ARRAY_A);
    @file_put_contents($log_path, json_encode(['sessionId' => 'debug-session', 'runId' => 'import-run', 'hypothesisId' => 'H1', 'location' => __FILE__ . ':lookup_id_by_name', 'message' => 'lookup result', 'data' => ['table' => $table_full, 'raw' => $raw, 'normalized' => $name, 'id_returned' => $id !== null ? (int) $id : null, 'rows_in_table' => $rows_in_table], 'timestamp' => round(microtime(true) * 1000)]) . "\n", FILE_APPEND | LOCK_EX);
    // #endregion
    return $id !== null ? (int) $id : null;
}

/**
 * Ensure a program type exists by name: look up by name/code/LIKE; if not found, create one with a slug code (max 20 chars). Returns id.
 */
function nds_import_excel_ensure_program_type($wpdb, $prefix, $name) {
    $name = nds_import_excel_normalize_lookup_name($name);
    if ($name === '') {
        return null;
    }
    $id = nds_import_excel_lookup_id_by_name($wpdb, $prefix . 'program_types', $name);
    if ($id !== null) {
        return $id;
    }
    $code = preg_replace('/[^a-z0-9]+/i', '-', strtolower($name));
    $code = trim($code, '-');
    if (strlen($code) > 20) {
        $code = substr($code, 0, 20);
    }
    if ($code === '') {
        $code = 'pt-' . substr(md5($name), 0, 8);
    }
    $table = $prefix . 'program_types';
    $wpdb->insert($table, ['code' => $code, 'name' => $name], ['%s', '%s']);
    if ($wpdb->insert_id) {
        return (int) $wpdb->insert_id;
    }
    $id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE code = %s OR name = %s LIMIT 1", $code, $name));
    return $id !== null ? (int) $id : null;
}

/**
 * Resolve FK columns: *_ignore is the primary link – look up the *_ignore value in the relevant table and set *_id.
 * *_id in Excel is only used when *_ignore is empty or lookup fails (and then only if that id exists in the table).
 */
function nds_import_excel_resolve_fks($wpdb, $table_key, $db_row) {
    $prefix = $wpdb->prefix . 'nds_';
    $row = $db_row;

    if ($table_key === 'programs') {
        unset($row['accreditation_body_id']);
        // 1) faculty_id: prefer faculty_name_ignore (look up in nds_faculties), then faculty_code, then validate Excel faculty_id
        if (!empty($row['faculty_name_ignore'])) {
            $id = nds_import_excel_lookup_id_by_name($wpdb, $prefix . 'faculties', $row['faculty_name_ignore']);
            if ($id !== null) {
                $row['faculty_id'] = $id;
            }
        }
        if (empty($row['faculty_id'])) {
            $faculty_val = isset($row['faculty_code']) ? $row['faculty_code'] : (isset($row['faculty']) ? $row['faculty'] : null);
            if ($faculty_val !== '' && $faculty_val !== null) {
                $id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$prefix}faculties WHERE code = %s LIMIT 1", $faculty_val));
                if ($id === null) {
                    $id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$prefix}faculties WHERE name = %s LIMIT 1", $faculty_val));
                }
                if ($id !== null) {
                    $row['faculty_id'] = (int) $id;
                }
                unset($row['faculty_code'], $row['faculty']);
            } elseif (array_key_exists('faculty_id', $row) && is_numeric($row['faculty_id']) && (int) $row['faculty_id'] > 0) {
                $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$prefix}faculties WHERE id = %d LIMIT 1", (int) $row['faculty_id']));
                if ($existing !== null) {
                    $row['faculty_id'] = (int) $existing;
                } else {
                    unset($row['faculty_id']);
                }
            }
        }
        // 2) program_type_id: prefer program_type_name_ignore (look up in nds_program_types), then program_type_code, then validate Excel program_type_id
        // #region agent log
        $log_path = dirname(__FILE__) . '/.cursor/debug.log';
        // #endregion
        if (!empty($row['program_type_name_ignore'])) {
            $id = nds_import_excel_lookup_id_by_name($wpdb, $prefix . 'program_types', $row['program_type_name_ignore']);
            if ($id !== null) {
                $row['program_type_id'] = $id;
            } else {
                $id = nds_import_excel_ensure_program_type($wpdb, $prefix, $row['program_type_name_ignore']);
                if ($id !== null) {
                    $row['program_type_id'] = $id;
                }
            }
        }
        if (empty($row['program_type_id'])) {
            $pt_val = isset($row['program_type_code']) ? $row['program_type_code'] : (isset($row['program_type']) ? $row['program_type'] : null);
            if ($pt_val !== '' && $pt_val !== null) {
                $id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$prefix}program_types WHERE code = %s LIMIT 1", $pt_val));
                if ($id === null) {
                    $id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$prefix}program_types WHERE name = %s LIMIT 1", $pt_val));
                }
                if ($id !== null) {
                    $row['program_type_id'] = (int) $id;
                }
                unset($row['program_type_code'], $row['program_type']);
            } elseif (array_key_exists('program_type_id', $row) && is_numeric($row['program_type_id']) && (int) $row['program_type_id'] > 0) {
                $excel_pt_id = (int) $row['program_type_id'];
                $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$prefix}program_types WHERE id = %d LIMIT 1", $excel_pt_id));
                if ($existing !== null) {
                    $row['program_type_id'] = (int) $existing;
                } else {
                    // #region agent log
                    $rows_pt = $wpdb->get_results("SELECT id, name, code FROM {$prefix}program_types", ARRAY_A);
                    @file_put_contents($log_path, json_encode(['sessionId' => 'debug-session', 'runId' => 'import-run', 'hypothesisId' => 'H3', 'location' => __FILE__ . ':resolve_fks:programs', 'message' => 'Excel program_type_id not in table', 'data' => ['excel_program_type_id' => $excel_pt_id, 'program_code' => $row['code'] ?? null, 'rows_in_nds_program_types' => $rows_pt], 'timestamp' => round(microtime(true) * 1000)]) . "\n", FILE_APPEND | LOCK_EX);
                    // #endregion
                    unset($row['program_type_id']);
                }
            }
        }
        // 3) accreditation_body_id: prefer accreditation_body_code, then accreditation_body_name_ignore when we add it
        if (array_key_exists('accreditation_body_code', $row) && $row['accreditation_body_code'] !== '' && $row['accreditation_body_code'] !== null) {
            $id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$prefix}accreditation_bodies WHERE code = %s LIMIT 1", $row['accreditation_body_code']));
            if ($id !== null) {
                $row['accreditation_body_id'] = (int) $id;
            }
            unset($row['accreditation_body_code']);
        }
        if (empty($row['accreditation_body_id']) && !empty($row['accreditation_body_name_ignore'])) {
            $id = nds_import_excel_lookup_id_by_name($wpdb, $prefix . 'accreditation_bodies', $row['accreditation_body_name_ignore']);
            if ($id !== null) {
                $row['accreditation_body_id'] = $id;
            }
        }
    }

    if ($table_key === 'program_levels') {
        if (array_key_exists('program_code', $row) && $row['program_code'] !== '' && $row['program_code'] !== null) {
            $id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$prefix}programs WHERE code = %s LIMIT 1", $row['program_code']));
            if ($id !== null) {
                $row['program_id'] = (int) $id;
            }
            unset($row['program_code']);
        }
    }

    if ($table_key === 'courses') {
        // program_id must be the actual nds_programs.id (auto_increment). Resolve ONLY by name/code so
        // after import order (Faculties → Programs → Courses) the FK always points to the correct program.
        // Do NOT use Excel program_id: it can be stale (e.g. 13 meant "Trade test - Artisan Chef" in an
        // old export, but after re-import that program may have a different id).
        if (!empty($row['program_name_ignore'])) {
            $id = nds_import_excel_lookup_id_by_name($wpdb, $prefix . 'programs', $row['program_name_ignore']);
            if ($id !== null) {
                $row['program_id'] = (int) $id;
            }
        }
        if (empty($row['program_id']) && array_key_exists('program_code', $row) && $row['program_code'] !== '' && $row['program_code'] !== null) {
            $id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$prefix}programs WHERE code = %s LIMIT 1", $row['program_code']));
            if ($id === null) {
                $id = nds_import_excel_lookup_id_by_name($wpdb, $prefix . 'programs', $row['program_code']);
            }
            if ($id !== null) {
                $row['program_id'] = (int) $id;
            }
            unset($row['program_code']);
        }
        // Intentionally do not use Excel program_id for courses; name/code resolution above is the only source.
        if (array_key_exists('category_code', $row) && $row['category_code'] !== '' && $row['category_code'] !== null) {
            $id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$prefix}course_categories WHERE code = %s LIMIT 1", $row['category_code']));
            if ($id !== null) {
                $row['category_id'] = (int) $id;
            }
            unset($row['category_code']);
        }
    }

    if ($table_key === 'course_schedules') {
        if (array_key_exists('course_code', $row) && $row['course_code'] !== '' && $row['course_code'] !== null) {
            $id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$prefix}courses WHERE code = %s LIMIT 1", $row['course_code']));
            if ($id !== null) {
                $row['course_id'] = (int) $id;
            }
            unset($row['course_code']);
        }
        if (array_key_exists('room_code', $row) && $row['room_code'] !== '' && $row['room_code'] !== null) {
            $id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$prefix}rooms WHERE code = %s LIMIT 1", $row['room_code']));
            if ($id !== null) {
                $row['room_id'] = (int) $id;
            }
            unset($row['room_code']);
        }
    }

    if ($table_key === 'students') {
        if (array_key_exists('faculty_code', $row) && $row['faculty_code'] !== '' && $row['faculty_code'] !== null) {
            $id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$prefix}faculties WHERE code = %s LIMIT 1", $row['faculty_code']));
            if ($id !== null) {
                $row['faculty_id'] = (int) $id;
            }
            unset($row['faculty_code']);
        }
    }

    if ($table_key === 'course_accreditations') {
        // 1) course_id: prefer course_name_ignore (look up in nds_courses), then course_code, then validate Excel course_id
        if (!empty($row['course_name_ignore'])) {
            $id = nds_import_excel_lookup_id_by_name($wpdb, $prefix . 'courses', $row['course_name_ignore']);
            if ($id !== null) {
                $row['course_id'] = $id;
            }
        }
        if (empty($row['course_id']) && array_key_exists('course_code', $row) && $row['course_code'] !== '' && $row['course_code'] !== null) {
            $id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$prefix}courses WHERE code = %s LIMIT 1", $row['course_code']));
            if ($id !== null) {
                $row['course_id'] = (int) $id;
            }
            unset($row['course_code']);
        }
        if (empty($row['course_id']) && array_key_exists('course_id', $row) && is_numeric($row['course_id']) && (int) $row['course_id'] > 0) {
            $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$prefix}courses WHERE id = %d LIMIT 1", (int) $row['course_id']));
            if ($existing !== null) {
                $row['course_id'] = (int) $existing;
            } else {
                unset($row['course_id']);
            }
        }
        // 2) accreditation_body_id: prefer accreditation_body_name_ignore (look up in nds_accreditation_bodies), then accreditation_body_code, then validate Excel accreditation_body_id
        // If Excel column "accreditation_body_id" contains a name (e.g. from dropdown), use it as name lookup
        if (empty($row['accreditation_body_name_ignore']) && array_key_exists('accreditation_body_id', $row) && $row['accreditation_body_id'] !== '' && $row['accreditation_body_id'] !== null && !is_numeric($row['accreditation_body_id'])) {
            $row['accreditation_body_name_ignore'] = is_string($row['accreditation_body_id']) ? trim($row['accreditation_body_id']) : (string) $row['accreditation_body_id'];
        }
        if (!empty($row['accreditation_body_name_ignore'])) {
            $id = nds_import_excel_lookup_id_by_name($wpdb, $prefix . 'accreditation_bodies', $row['accreditation_body_name_ignore']);
            if ($id !== null) {
                $row['accreditation_body_id'] = $id;
            }
        }
        if (empty($row['accreditation_body_id']) && array_key_exists('accreditation_body_code', $row) && $row['accreditation_body_code'] !== '' && $row['accreditation_body_code'] !== null) {
            $id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$prefix}accreditation_bodies WHERE code = %s LIMIT 1", $row['accreditation_body_code']));
            if ($id !== null) {
                $row['accreditation_body_id'] = (int) $id;
            }
            unset($row['accreditation_body_code']);
        }
        if (empty($row['accreditation_body_id']) && array_key_exists('accreditation_body_id', $row) && is_numeric($row['accreditation_body_id']) && (int) $row['accreditation_body_id'] > 0) {
            $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$prefix}accreditation_bodies WHERE id = %d LIMIT 1", (int) $row['accreditation_body_id']));
            if ($existing !== null) {
                $row['accreditation_body_id'] = (int) $existing;
            } else {
                unset($row['accreditation_body_id']);
            }
        }
    }

    if ($table_key === 'staff') {
        if (array_key_exists('user_id', $row)) {
            $uid = $row['user_id'];
            $valid = is_numeric($uid) && (int) $uid > 0 && $wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->users} WHERE ID = %d LIMIT 1", (int) $uid));
            if (!$valid) {
                $row['user_id'] = null;
            }
        }
    }

    return $row;
}

/**
 * Validate FK columns: if a FK is set, verify it exists in the parent table; if not, unset it so the row is skipped instead of causing an FK error.
 */
function nds_import_excel_validate_fks($wpdb, $table_key, array $db_row) {
    $prefix = $wpdb->prefix . 'nds_';
    $row = $db_row;

    if ($table_key === 'programs') {
        if (!empty($row['program_type_id']) && is_numeric($row['program_type_id'])) {
            $exists = $wpdb->get_var($wpdb->prepare("SELECT 1 FROM {$prefix}program_types WHERE id = %d LIMIT 1", (int) $row['program_type_id']));
            if (!$exists) {
                unset($row['program_type_id']);
            }
        }
        if (!empty($row['faculty_id']) && is_numeric($row['faculty_id'])) {
            $exists = $wpdb->get_var($wpdb->prepare("SELECT 1 FROM {$prefix}faculties WHERE id = %d LIMIT 1", (int) $row['faculty_id']));
            if (!$exists) {
                unset($row['faculty_id']);
            }
        }
        if (isset($row['accreditation_body_id']) && $row['accreditation_body_id'] !== null && $row['accreditation_body_id'] !== '') {
            $exists = $wpdb->get_var($wpdb->prepare("SELECT 1 FROM {$prefix}accreditation_bodies WHERE id = %d LIMIT 1", (int) $row['accreditation_body_id']));
            if (!$exists) {
                unset($row['accreditation_body_id']);
            }
        }
    }

    if ($table_key === 'courses' && !empty($row['program_id']) && is_numeric($row['program_id'])) {
        $exists = $wpdb->get_var($wpdb->prepare("SELECT 1 FROM {$prefix}programs WHERE id = %d LIMIT 1", (int) $row['program_id']));
        if (!$exists) {
            unset($row['program_id']);
        }
    }

    if ($table_key === 'program_levels' && !empty($row['program_id']) && is_numeric($row['program_id'])) {
        $exists = $wpdb->get_var($wpdb->prepare("SELECT 1 FROM {$prefix}programs WHERE id = %d LIMIT 1", (int) $row['program_id']));
        if (!$exists) {
            unset($row['program_id']);
        }
    }

    if ($table_key === 'course_schedules') {
        if (!empty($row['course_id']) && is_numeric($row['course_id'])) {
            $exists = $wpdb->get_var($wpdb->prepare("SELECT 1 FROM {$prefix}courses WHERE id = %d LIMIT 1", (int) $row['course_id']));
            if (!$exists) {
                unset($row['course_id']);
            }
        }
        if (isset($row['lecturer_id']) && $row['lecturer_id'] !== null && $row['lecturer_id'] !== '') {
            $exists = $wpdb->get_var($wpdb->prepare("SELECT 1 FROM {$prefix}staff WHERE id = %d LIMIT 1", (int) $row['lecturer_id']));
            if (!$exists) {
                unset($row['lecturer_id']);
            }
        }
        if (isset($row['room_id']) && $row['room_id'] !== null && $row['room_id'] !== '') {
            $exists = $wpdb->get_var($wpdb->prepare("SELECT 1 FROM {$prefix}rooms WHERE id = %d LIMIT 1", (int) $row['room_id']));
            if (!$exists) {
                unset($row['room_id']);
            }
        }
    }

    if ($table_key === 'course_accreditations') {
        if (!empty($row['course_id']) && is_numeric($row['course_id'])) {
            $exists = $wpdb->get_var($wpdb->prepare("SELECT 1 FROM {$prefix}courses WHERE id = %d LIMIT 1", (int) $row['course_id']));
            if (!$exists) {
                unset($row['course_id']);
            }
        }
        if (!empty($row['accreditation_body_id']) && is_numeric($row['accreditation_body_id'])) {
            $exists = $wpdb->get_var($wpdb->prepare("SELECT 1 FROM {$prefix}accreditation_bodies WHERE id = %d LIMIT 1", (int) $row['accreditation_body_id']));
            if (!$exists) {
                unset($row['accreditation_body_id']);
            }
        }
    }

    return $row;
}

/**
 * Count how many course rows in the Courses sheet reference a given program (by code or name as shown in Excel).
 *
 * @param string|null $excel_path Path to NDS Database System.xlsx (default: assets/NDS Database System.xlsx)
 * @param string $program_value Program code or name to match (e.g. "dvsdfvdsfvdz")
 * @return int Count of course rows (0 if file/sheet missing or no match)
 */
function nds_import_excel_count_courses_by_program_in_sheet($excel_path = null, $program_value = '') {
    $excel_path = $excel_path ?? (dirname(__FILE__) . '/assets/NDS Database System.xlsx');
    if (!file_exists($excel_path)) {
        return 0;
    }
    require_once dirname(__FILE__) . '/includes/class-nds-xlsx-reader.php';
    $reader = new NDS_XLSX_Reader($excel_path);
    $sheet_names = $reader->getSheetNames();
    $courses_idx = array_search('Courses', $sheet_names, true);
    if ($courses_idx === false) {
        return 0;
    }
    $data = $reader->getSheetData($courses_idx);
    $headers = $data['headers'];
    $rows = $data['rows'];
    if (empty($headers) || empty($rows)) {
        return 0;
    }
    $program_value = trim((string) $program_value);
    $count = 0;
    $aliases = nds_import_excel_get_column_aliases('courses');
    foreach ($rows as $row) {
        $db_row = nds_import_excel_row_to_db_columns($row);
        foreach ($aliases as $excel_name => $db_col) {
            if (array_key_exists($excel_name, $db_row) && !array_key_exists($db_col, $db_row)) {
                $db_row[$db_col] = $db_row[$excel_name];
                unset($db_row[$excel_name]);
            }
        }
        // Match program by name (program_name_ignore), code (program_code), or id (program_id)
        $program_cell = isset($db_row['program_name_ignore']) ? $db_row['program_name_ignore'] : (isset($db_row['program_code']) ? $db_row['program_code'] : (isset($db_row['program_id']) ? $db_row['program_id'] : null));
        $cell_str = $program_cell === null || $program_cell === '' ? '' : trim((string) $program_cell);
        if ($cell_str === $program_value) {
            $count++;
        }
    }
    return $count;
}

/**
 * Run the import.
 *
 * @param string|null $excel_path Path to NDS Database System.xlsx (default: assets/NDS Database System.xlsx)
 * @param array $opts 'dry_run' => true to only report, 'truncate' => table key to truncate before insert (not used by default)
 * @return array{success: bool, message: string, stats: array}
 */
function nds_import_excel_run($excel_path = null, $opts = []) {
    global $wpdb;
    $dry_run = !empty($opts['dry_run']);
    $excel_path = $excel_path ?? (dirname(__FILE__) . '/assets/NDS Database System.xlsx');

    // #region agent log
    $log_path = dirname(__FILE__) . '/.cursor/debug.log';
    $agent_log = function ($hypothesisId, $message, $data = []) use ($log_path) {
        $line = json_encode(array_merge(['sessionId' => 'debug-session', 'runId' => 'import-run', 'hypothesisId' => $hypothesisId, 'location' => 'import-excel-to-database.php:nds_import_excel_run', 'message' => $message, 'data' => $data, 'timestamp' => round(microtime(true) * 1000)])) . "\n";
        @file_put_contents($log_path, $line, FILE_APPEND | LOCK_EX);
    };
    $agent_log('H3', 'nds_import_excel_run entry', ['excel_path_len' => strlen($excel_path), 'file_exists' => file_exists($excel_path)]);
    // #endregion

    if (!file_exists($excel_path)) {
        $err = 'Excel file not found: ' . $excel_path;
        if (function_exists('error_log')) {
            error_log('[NDS Import] ' . $err);
        }
        return [
            'success' => false,
            'message' => $err,
            'stats' => [],
        ];
    }

    require_once dirname(__FILE__) . '/includes/class-nds-xlsx-reader.php';

    try {
        $reader = new NDS_XLSX_Reader($excel_path);
    } catch (Exception $e) {
        $agent_log('H3', 'NDS_XLSX_Reader exception', ['message' => $e->getMessage()]);
        $err = 'Failed to open Excel: ' . $e->getMessage();
        if (function_exists('error_log')) {
            error_log('[NDS Import] ' . $err . ' | path=' . $excel_path);
        }
        return [
            'success' => false,
            'message' => $err,
            'stats' => [],
        ];
    }

    $sheet_order = nds_import_excel_get_sheet_order();
    $sheet_names = $reader->getSheetNames();
    $agent_log('H1', 'Excel sheet names vs expected', ['excel_sheet_names' => $sheet_names, 'expected_sheet_names' => array_keys($sheet_order)]);
    $stats = [];
    $errors = [];
    $skipped_details = [];

    foreach ($sheet_order as $excel_sheet_name => $table_key) {
        $sheet_index = array_search($excel_sheet_name, $sheet_names, true);
        if ($sheet_index === false) {
            $agent_log('H1', 'Sheet not found in Excel', ['expected_name' => $excel_sheet_name, 'table_key' => $table_key]);
            if ($table_key !== 'program_levels') {
                $errors[] = "Sheet not found in Excel: {$excel_sheet_name}";
            }
            continue;
        }

        $table_full = $wpdb->prefix . 'nds_' . $table_key;
        $data = $reader->getSheetData($sheet_index);
        $headers = $data['headers'];
        $rows = $data['rows'];

        if (empty($headers)) {
            $agent_log('H5', 'Sheet has no headers', ['table_key' => $table_key, 'rows_count' => count($rows)]);
            $stats[$table_key] = ['inserted' => 0, 'skipped' => 0, 'errors' => 0];
            continue;
        }

        // Only insert columns that exist in the table (Excel may have extra columns)
        $table_columns = [];
        $table_columns_list = [];
        if (!$dry_run) {
            $escaped_table = '`' . str_replace('`', '``', $table_full) . '`';
            $table_columns_list = $wpdb->get_col("SHOW COLUMNS FROM {$escaped_table}");
            // Ensure course_accreditations.accreditation_body_id allows NULL (for existing DBs)
            if ($table_key === 'course_accreditations' && in_array('accreditation_body_id', $table_columns_list, true)) {
                $col = $wpdb->get_row($wpdb->prepare("SHOW COLUMNS FROM {$escaped_table} WHERE Field = %s", 'accreditation_body_id'), ARRAY_A);
                if (!empty($col) && isset($col['Null']) && $col['Null'] === 'NO') {
                    $wpdb->query("ALTER TABLE {$escaped_table} MODIFY COLUMN accreditation_body_id INT NULL DEFAULT NULL");
                }
            }
            if (!is_array($table_columns_list) || empty($table_columns_list)) {
                $agent_log('H2', 'Table does not exist or no columns', ['table_key' => $table_key, 'table_full' => $table_full]);
                $errors[] = "Table does not exist or is empty: {$table_key}";
                continue;
            }
            $table_columns = array_flip($table_columns_list);
        }

        $inserted = 0;
        $row_errors = 0;
        $skipped = 0;
        $first_sheet_error = null;

        foreach ($rows as $row) {
            $db_row = nds_import_excel_row_to_db_columns($row);
            $db_row = nds_import_excel_apply_column_aliases($db_row, $table_key);
            $excel_program_type_id = array_key_exists('program_type_id', $db_row) ? $db_row['program_type_id'] : null;
            $excel_program_type_name = array_key_exists('program_type_name_ignore', $db_row) ? $db_row['program_type_name_ignore'] : null;
            $excel_course_id = array_key_exists('course_id', $db_row) ? $db_row['course_id'] : null;
            $excel_course_name = array_key_exists('course_name_ignore', $db_row) ? $db_row['course_name_ignore'] : null;
            $excel_accred_body_id = $table_key === 'course_accreditations' && array_key_exists('accreditation_body_id', $db_row) ? $db_row['accreditation_body_id'] : null;
            $excel_accred_body_name = $table_key === 'course_accreditations' && array_key_exists('accreditation_body_name_ignore', $db_row) ? $db_row['accreditation_body_name_ignore'] : null;
            $excel_program_code = $table_key === 'courses' && array_key_exists('program_code', $db_row) ? $db_row['program_code'] : null;
            $excel_program_id = $table_key === 'courses' && array_key_exists('program_id', $db_row) ? $db_row['program_id'] : null;
            $excel_course_sheet_code = $table_key === 'courses' && array_key_exists('code', $db_row) ? $db_row['code'] : null;
            $excel_course_sheet_name = $table_key === 'courses' && array_key_exists('name', $db_row) ? $db_row['name'] : null;
            if (!$dry_run) {
                $db_row = nds_import_excel_resolve_fks($wpdb, $table_key, $db_row);
                $db_row = nds_import_excel_validate_fks($wpdb, $table_key, $db_row);
            }
            $db_row = nds_import_excel_filter_ignore_columns($db_row);

            // Remove null/empty keys so we don't INSERT empty strings where not needed
            $db_row = array_filter($db_row, function ($v) {
                return $v !== '' && $v !== null;
            });

            if (empty($db_row)) {
                continue;
            }

            // Drop columns not in table (Excel may have display_label etc. that DB doesn't have)
            if (!empty($table_columns)) {
                $db_row = array_intersect_key($db_row, $table_columns);
            }
            if (empty($db_row)) {
                continue;
            }

            // Never insert Excel 'id' – let the DB auto-generate. Stops "duplicate" when same id appears in multiple rows (e.g. one teacher, many courses).
            if (in_array('id', $table_columns_list, true) && array_key_exists('id', $db_row)) {
                unset($db_row['id']);
            }
            if (empty($db_row)) {
                continue;
            }

            // Skip only when a required unique column is present in the row but empty (don't skip when column is missing – let INSERT fail with clear error)
            $unique_key_cols = ['code', 'student_number', 'application_no', 'claim_token', 'module_code', 'year_name'];
            foreach ($unique_key_cols as $uk) {
                if (!in_array($uk, $table_columns_list, true)) {
                    continue;
                }
                if (!array_key_exists($uk, $db_row)) {
                    continue;
                }
                $val = $db_row[$uk];
                if ($val === '' || $val === null) {
                    $skipped++;
                    $row_hint = isset($db_row['id']) ? 'id=' . $db_row['id'] : (isset($db_row['name']) ? 'name=' . substr((string) $db_row['name'], 0, 40) : 'row');
                    $skipped_details[] = ['table' => $table_key, 'reason' => 'empty_required', 'column' => $uk, 'row_hint' => $row_hint];
                    continue 2;
                }
            }

            if ($table_key === 'course_accreditations') {
                if (empty($db_row['course_id'])) {
                    $skipped++;
                    $hint = 'Excel course_id=' . ($excel_course_id !== null && $excel_course_id !== '' ? json_encode($excel_course_id) : 'empty');
                    $hint .= ', course_name_ignore=' . ($excel_course_name !== null && $excel_course_name !== '' ? json_encode($excel_course_name) : 'empty');
                    $hint .= ' | accreditation_body_id=' . ($excel_accred_body_id !== null && $excel_accred_body_id !== '' ? json_encode($excel_accred_body_id) : 'empty');
                    $hint .= ', accreditation_body_name_ignore=' . ($excel_accred_body_name !== null && $excel_accred_body_name !== '' ? json_encode($excel_accred_body_name) : 'empty');
                    $hint .= ' → course_id could not be resolved from DB';
                    $skipped_details[] = ['table' => $table_key, 'reason' => 'empty_required', 'column' => 'course_id', 'row_hint' => $hint];
                    continue;
                }
            }
            if ($table_key === 'programs' && empty($db_row['program_type_id'])) {
                $skipped++;
                $code = isset($db_row['code']) ? $db_row['code'] : '?';
                $hint = "code={$code}. Excel program_type_id=" . ($excel_program_type_id !== null && $excel_program_type_id !== '' ? json_encode($excel_program_type_id) : 'empty');
                $hint .= ", program_type_name_ignore=" . ($excel_program_type_name !== null && $excel_program_type_name !== '' ? json_encode($excel_program_type_name) : 'empty');
                $hint .= ' → id not in nds_program_types table, or name/code/LIKE lookup found no row';
                $skipped_details[] = ['table' => $table_key, 'reason' => 'empty_required', 'column' => 'program_type_id', 'row_hint' => $hint];
                continue;
            }
            if ($table_key === 'courses' && empty($db_row['program_id'])) {
                $skipped++;
                $hint = 'course code=' . ($excel_course_sheet_code !== null && $excel_course_sheet_code !== '' ? json_encode($excel_course_sheet_code) : 'empty');
                $hint .= ', name=' . ($excel_course_sheet_name !== null && $excel_course_sheet_name !== '' ? json_encode(mb_substr((string) $excel_course_sheet_name, 0, 50)) : 'empty');
                $hint .= ' | Excel program_code=' . ($excel_program_code !== null && $excel_program_code !== '' ? json_encode($excel_program_code) : 'empty');
                $hint .= ', program_id=' . ($excel_program_id !== null && $excel_program_id !== '' ? json_encode($excel_program_id) : 'empty');
                $hint .= ' → program_id could not be resolved (code/name lookup in nds_programs found no row)';
                $skipped_details[] = ['table' => $table_key, 'reason' => 'empty_required', 'column' => 'program_id', 'row_hint' => $hint];
                continue;
            }

            $columns = array_keys($db_row);
            $format = [];
            foreach ($db_row as $v) {
                $format[] = is_numeric($v) && (is_int($v) || ctype_digit((string) $v)) ? '%d' : '%s';
            }

            if (!$dry_run) {
                $escaped_table = '`' . str_replace('`', '``', $table_full) . '`';
                $escaped_cols = array_map(function ($c) {
                    return '`' . str_replace('`', '``', $c) . '`';
                }, $columns);
                $placeholders = implode(',', $format);
                $odku_tables = nds_import_excel_get_odku_tables();
                $use_odku = isset($odku_tables[$table_key]);
                if ($use_odku) {
                    // ON DUPLICATE KEY UPDATE: re-import updates existing rows instead of skipping
                    $updates = [];
                    foreach ($columns as $c) {
                        $updates[] = '`' . str_replace('`', '``', $c) . '` = VALUES(`' . str_replace('`', '``', $c) . '`)';
                    }
                    $sql = "INSERT INTO {$escaped_table} (" . implode(',', $escaped_cols) . ") VALUES ({$placeholders}) ON DUPLICATE KEY UPDATE " . implode(', ', $updates);
                } else {
                    $sql = "INSERT IGNORE INTO {$escaped_table} (" . implode(',', $escaped_cols) . ") VALUES ({$placeholders})";
                }
                $prepared = $wpdb->prepare($sql, array_values($db_row));
                $result = $prepared ? $wpdb->query($prepared) : false;
                if ($result === false) {
                    $row_errors++;
                    if ($first_sheet_error === null) {
                        $first_sheet_error = $wpdb->last_error;
                    }
                    $errors[] = $table_key . ': ' . $wpdb->last_error . ' for row id=' . ($db_row['id'] ?? '?');
                } elseif ($use_odku || $wpdb->rows_affected > 0) {
                    // ODKU: count as success even when rows_affected=0 (MySQL returns 0 when update sets same values)
                    $inserted++;
                } else {
                    $skipped++;
                    $row_hint = isset($db_row['id']) ? 'id=' . $db_row['id'] : (isset($db_row['code']) ? 'code=' . $db_row['code'] : (isset($db_row['name']) ? 'name=' . substr((string) $db_row['name'], 0, 40) : 'row'));
                    $skipped_details[] = ['table' => $table_key, 'reason' => 'duplicate', 'column' => '', 'row_hint' => $row_hint];
                }
            } else {
                $inserted++;
            }
        }

        $stats[$table_key] = ['inserted' => $inserted, 'errors' => $row_errors, 'skipped' => $skipped];
        $agent_log('H4', 'Sheet processed', ['table_key' => $table_key, 'rows_in_sheet' => count($rows), 'inserted' => $inserted, 'row_errors' => $row_errors, 'skipped' => $skipped, 'first_sheet_error' => $first_sheet_error]);
    }

    $total_inserted = 0;
    $total_skipped = 0;
    foreach ($stats as $s) {
        $total_inserted += isset($s['inserted']) ? (int) $s['inserted'] : 0;
        $total_skipped += isset($s['skipped']) ? (int) $s['skipped'] : 0;
    }

    $message = $dry_run
        ? 'Dry run complete. No data written.'
        : 'Import complete.';
    if ($total_inserted > 0 || $total_skipped > 0) {
        $message .= " {$total_inserted} row(s) inserted.";
        if ($total_skipped > 0) {
            $n_dup = count(array_filter($skipped_details, function ($d) { return isset($d['reason']) && $d['reason'] === 'duplicate'; }));
            $n_empty = count(array_filter($skipped_details, function ($d) { return isset($d['reason']) && $d['reason'] === 'empty_required'; }));
            $reasons = [];
            if ($n_dup > 0) {
                $reasons[] = $n_dup . ' duplicate key';
            }
            if ($n_empty > 0) {
                $reasons[] = $n_empty . ' empty required field';
            }
            $message .= ' ' . $total_skipped . ' row(s) skipped (' . implode(', ', $reasons) . ').';
        }
    }
    if (!empty($errors)) {
        $message .= ' Errors: ' . implode('; ', array_slice($errors, 0, 5));
        if (count($errors) > 5) {
            $message .= ' (+' . (count($errors) - 5) . ' more)';
        }
        if (function_exists('error_log')) {
            error_log('[NDS Import] Row errors: ' . implode(' | ', array_slice($errors, 0, 15)));
        }
    }

    $agent_log('H4', 'nds_import_excel_run exit', ['errors_count' => count($errors), 'success' => empty($errors), 'first_error' => isset($errors[0]) ? $errors[0] : null]);

    return [
        'success' => empty($errors),
        'message' => $message,
        'stats'   => $stats,
        'errors'  => $errors,
        'skipped_details' => $skipped_details,
    ];
}

}

// CLI: run import when executed directly (or --count-program <name> to count courses by program in sheet)
if (php_sapi_name() === 'cli' && defined('ABSPATH')) {
    $argv = $argv ?? [];
    $count_idx = array_search('--count-program', $argv, true);
    if ($count_idx !== false && isset($argv[$count_idx + 1])) {
        $program_name = $argv[$count_idx + 1];
        $n = nds_import_excel_count_courses_by_program_in_sheet(null, $program_name);
        echo "Courses in sheet for program " . json_encode($program_name) . ": {$n}\n";
        exit(0);
    }
    $opts = ['dry_run' => in_array('--dry-run', $argv ?? [], true)];
    $result = nds_import_excel_run(null, $opts);
    echo $result['message'] . "\n";
    if (!empty($result['stats'])) {
        echo "Stats:\n";
        foreach ($result['stats'] as $table => $s) {
            $sk = isset($s['skipped']) ? $s['skipped'] : 0;
            echo "  {$table}: inserted={$s['inserted']}, errors={$s['errors']}, skipped={$sk}\n";
        }
    }
    if (!empty($result['errors'])) {
        foreach ($result['errors'] as $e) {
            echo "  Error: {$e}\n";
        }
    }
    exit($result['success'] ? 0 : 1);
}
