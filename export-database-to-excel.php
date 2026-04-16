<?php
/**
 * Export Database Tables to Excel with Linked Dropdowns
 *
 * This script exports all major database tables to Excel format with proper
 * relationships and dropdown validations between sheets.
 */

// Load WordPress
$wp_load_path = dirname(__FILE__) . '/../../../wp-load.php';
if (!file_exists($wp_load_path)) {
    die("WordPress not found at: $wp_load_path\n");
}
require_once($wp_load_path);

// Check permissions when not CLI
if (!is_admin() && php_sapi_name() !== 'cli') {
    if (!current_user_can('manage_options')) {
        die("Access denied. Admin privileges required.\n");
    }
}

global $wpdb;

// Define table relationships and export order
$tables_config = [
    // Core lookup tables (no dependencies)
    'accreditation_bodies' => [
        'title' => 'Accreditation Bodies',
        'columns' => ['id', 'name', 'short_name', 'description', 'website_url', 'contact_email', 'status'],
        'dropdown_source' => true
    ],
    'program_types' => [
        'title' => 'Program Types',
        'columns' => ['id', 'name', 'description', 'typical_duration_years', 'level'],
        'dropdown_source' => true
    ],
    'course_categories' => [
        'title' => 'Course Categories',
        'columns' => ['id', 'name', 'description'],
        'dropdown_source' => true
    ],

    // Program/Faculty hierarchy (Programs are top level, Faculties sit under Programs)
    'faculties' => [
        // Note: These rows represent top-level Programs in the academic model
        'title' => 'Programs',
        'columns' => ['id', 'code', 'name', 'short_name', 'description', 'dean_name', 'contact_email', 'status'],
        'dropdown_source' => true
    ],
    'programs' => [
        // Note: These rows represent Faculties (under a Program) in the academic model
        'title' => 'Faculties',
        'columns' => ['id', 'faculty_id', 'program_type_id', 'code', 'name', 'short_name', 'nqf_level', 'total_credits', 'duration_years', 'accreditation_body_id', 'status'],
        'dropdowns' => [
            // In Excel, we point to the Programs sheet for the program name/code,
            // even though the physical FK column is still faculty_id.
            'faculty_id' => 'Programs!$B:$B', // Program name column for dropdown display
            'program_type_id' => 'Program Types!$B:$B',
            'accreditation_body_id' => 'Accreditation Bodies!$B:$B'
        ]
    ],
    'program_levels' => [
        'title' => 'Program Levels',
        'columns' => ['id', 'program_id', 'level_number', 'name', 'description', 'required_credits'],
        'dropdowns' => [
            'program_id' => 'Faculties!$D:$E' // Code, Name (faculty = nds_programs row)
        ]
    ],

    // Courses and related (program_id = nds_programs = Faculties sheet)
    'courses' => [
        'title' => 'Courses',
        'columns' => ['id', 'program_id', 'level_id', 'code', 'name', 'nqf_level', 'credits', 'contact_hours', 'category_id', 'is_required', 'status'],
        'dropdowns' => [
            'program_id' => 'Faculties!$D:$E', // Code, Name columns
            'level_id' => 'Program Levels!$C:$C', // Name column
            'category_id' => 'Course Categories!$B:$B' // Name column
        ]
    ],
    'course_prerequisites' => [
        'title' => 'Course Prerequisites',
        'columns' => ['id', 'course_id', 'prerequisite_course_id', 'is_mandatory', 'min_grade'],
        'dropdowns' => [
            'course_id' => 'Courses!$D:$E', // Code, Name columns
            'prerequisite_course_id' => 'Courses!$D:$E'
        ]
    ],

    // Accreditation relationships
    'program_accreditations' => [
        'title' => 'Program Accreditations',
        'columns' => ['id', 'program_id', 'accreditation_body_id', 'accreditation_number', 'accreditation_date', 'expiry_date', 'status'],
        'dropdowns' => [
            'program_id' => 'Faculties!$D:$E', // Code, Name (program_id = nds_programs)
            'accreditation_body_id' => 'Accreditation Bodies!$B:$B' // Name column
        ]
    ],
    'course_accreditations' => [
        'title' => 'Course Accreditations',
        'columns' => ['id', 'course_id', 'accreditation_body_id', 'accreditation_number', 'accreditation_date', 'expiry_date', 'status'],
        'dropdowns' => [
            'course_id' => 'Courses!$D:$E', // Code, Name columns
            'accreditation_body_id' => 'Accreditation Bodies!$B:$B' // Name column
        ]
    ],

    // Academic structure
    'academic_years' => [
        'title' => 'Academic Years',
        'columns' => ['id', 'year_name', 'start_date', 'end_date', 'is_active'],
        'dropdown_source' => true
    ],
    'semesters' => [
        'title' => 'Semesters',
        'columns' => ['id', 'academic_year_id', 'semester_name', 'start_date', 'end_date', 'is_active'],
        'dropdowns' => [
            'academic_year_id' => 'Academic Years!$B:$B' // year_name column
        ],
        'dropdown_source' => true
    ],

    // Course schedules
    'course_schedules' => [
        'title' => 'Course Schedules',
        'columns' => ['id', 'course_id', 'lecturer_id', 'days', 'start_time', 'end_time', 'day_hours', 'session_type', 'location', 'is_active'],
        'dropdowns' => [
            'course_id' => 'Courses!$D:$E', // Code, Name columns
            'lecturer_id' => 'Staff!$C:$C' // Full name (concatenated first+last)
        ]
    ],

    // Staff and students
    'staff' => [
        'title' => 'Staff',
        'columns' => ['id', 'user_id', 'first_name', 'last_name', 'email', 'phone', 'role'],
        'dropdown_source' => true
    ],
    'students' => [
        'title' => 'Students',
        'columns' => ['id', 'student_number', 'wp_user_id', 'faculty_id', 'first_name', 'last_name', 'email', 'phone', 'status'],
        'dropdowns' => [
            'faculty_id' => 'Programs!$B:$B' // Programs sheet (nds_faculties) = top-level program
        ]
    ]
];

$GLOBALS['nds_export_tables_config'] = $tables_config;

// CLI: run export when script is executed directly
if (php_sapi_name() === 'cli') {
    echo "=== Exporting Database to Excel ===\n\n";
    $export_dir = dirname(__FILE__) . '/database-export-' . date('Y-m-d-H-i-s');
    $excel_file = $export_dir . '/nds-school-database.xlsx';
    if (!is_dir($export_dir)) {
        mkdir($export_dir, 0755, true);
    }
    generate_excel_file($tables_config, $export_dir, $excel_file, false);
    echo "\n✓ Export complete!\n";
    echo "Excel file created: $excel_file\n";
    echo "\nTo complete the setup:\n";
    echo "1. Open the Excel file\n";
    echo "2. For each sheet with dropdowns, select the appropriate columns\n";
    echo "3. Go to Data > Data Validation > Allow: List\n";
    echo "4. Enter the range formulas as specified in the dropdown configurations\n";
}

/**
 * Generate Excel file with multiple sheets and dropdown validations
 *
 * @param array  $tables_config
 * @param string $export_dir
 * @param string $excel_file
 * @param bool   $silent If true, do not echo (for admin download).
 */
function generate_excel_file($tables_config, $export_dir, $excel_file, $silent = false) {
    global $wpdb;

    $csv_files = [];
    $setup_instructions = [];
    $sheets_for_xlsx = [];

    foreach ($tables_config as $table_key => $config) {
        $table_name = $wpdb->prefix . 'nds_' . $table_key;

        if (!$silent) {
            echo "Exporting table: {$config['title']} ($table_name)\n";
        }

        $columns = implode(', ', $config['columns']);
        $query = "SELECT $columns FROM $table_name ORDER BY id";

        $results = $wpdb->get_results($query, ARRAY_A);

        if ($wpdb->last_error) {
            if (!$silent) {
                echo "✗ Error querying $table_name: " . $wpdb->last_error . "\n";
            }
            continue;
        }

        $csv_file = $export_dir . '/' . sanitize_filename($config['title']) . '.csv';
        $csv_files[] = $csv_file;

        $fp = fopen($csv_file, 'w');
        // Always write header row (use column list so empty tables still have structure)
        $header = !empty($results) ? array_keys($results[0]) : $config['columns'];
        fputcsv($fp, $header);
        foreach ($results as $row) {
            fputcsv($fp, $row);
        }
        fclose($fp);

        if (!$silent) {
            echo "✓ Exported " . count($results) . " records to " . basename($csv_file) . "\n";
        }

        if (isset($config['dropdowns'])) {
            $setup_instructions[$config['title']] = $config['dropdowns'];
        }

        // Collect data for single XLSX workbook
        $sheet_rows = $results;
        if (empty($sheet_rows)) {
            $sheet_rows = [ array_combine($config['columns'], $config['columns']) ];
        }
        $sheets_for_xlsx[] = [ 'name' => $config['title'], 'rows' => $sheet_rows ];
    }

    // Generate real .xlsx with all sheets
    if (!empty($sheets_for_xlsx)) {
        $writer_path = dirname(__FILE__) . '/includes/class-nds-xlsx-writer.php';
        if (file_exists($writer_path)) {
            require_once $writer_path;
            try {
                $writer = new NDS_XLSX_Writer($excel_file);
                foreach ($sheets_for_xlsx as $sheet) {
                    $writer->addSheet($sheet['name'], $sheet['rows']);
                }
                $writer->close();
                if (!$silent) {
                    echo "✓ Created Excel workbook: " . basename($excel_file) . "\n";
                }
            } catch (Exception $e) {
                if (!$silent) {
                    echo "✗ XLSX write failed: " . $e->getMessage() . "\n";
                }
            }
        }
    }

    create_excel_setup_guide($export_dir, $tables_config, $setup_instructions, $silent);
    create_excel_import_script($export_dir, $tables_config, $silent);
}

/**
 * Create Excel setup guide with dropdown instructions
 *
 * @param bool $silent If true, do not echo.
 */
function create_excel_setup_guide($export_dir, $tables_config, $setup_instructions, $silent = false) {
    $guide_file = $export_dir . '/EXCEL_SETUP_GUIDE.txt';

    $content = "=== NDS School Database Excel Setup Guide ===\n\n";
    $content .= "This guide will help you create an Excel file with linked dropdowns from the exported CSV files.\n\n";

    $content .= "STEP 1: Create New Excel Workbook\n";
    $content .= "1. Open Microsoft Excel\n";
    $content .= "2. Create a new workbook\n\n";

    $content .= "STEP 2: Import CSV Files as Sheets\n";
    $content .= "For each CSV file in this directory:\n";
    $content .= "1. Go to Data > Get External Data > From Text\n";
    $content .= "2. Select the CSV file\n";
    $content .= "3. Follow the Text Import Wizard (use defaults)\n";
    $content .= "4. Rename the sheet to match the table name\n\n";

    $content .= "Import order (important for dropdown relationships):\n";
    foreach ($tables_config as $table_key => $config) {
        $content .= "- " . $config['title'] . " (" . sanitize_filename($config['title']) . ".csv)\n";
    }

    $content .= "\nSTEP 3: Set Up Dropdown Validations\n\n";

    foreach ($setup_instructions as $sheet_name => $dropdowns) {
        $content .= "Sheet: $sheet_name\n";
        foreach ($dropdowns as $column => $range) {
            $content .= "  - Column '$column': Data Validation > List > Source: =$range\n";
            $content .= "    Note: This dropdown will show names/values. Enter the corresponding ID in the cell.\n";
        }
        $content .= "\n";
    }

    $content .= "STEP 4: Format and Save\n";
    $content .= "1. Format headers (bold, background color)\n";
    $content .= "2. Adjust column widths\n";
    $content .= "3. Save as: nds-school-database.xlsx\n\n";

    $content .= "=== Dropdown Reference ===\n";
    $content .= "When setting up dropdowns, reference these columns:\n\n";

    foreach ($tables_config as $table_key => $config) {
        if (isset($config['dropdown_source']) && $config['dropdown_source']) {
            $content .= "- {$config['title']}: Column B (Name/Display Value)\n";
        }
    }

    $content .= "\n=== Important Notes ===\n";
    $content .= "- Dropdowns show names/values for easy selection\n";
    $content .= "- You still need to enter the corresponding ID numbers in the ID columns\n";
    $content .= "- For example: Select 'Hospitality Management' from dropdown, then enter '1' in faculty_id column\n";

    file_put_contents($guide_file, $content);
    if (!$silent) {
        echo "✓ Created Excel setup guide: " . basename($guide_file) . "\n";
    }
}

/**
 * Create Excel import script for automation
 *
 * @param bool $silent If true, do not echo.
 */
function create_excel_import_script($export_dir, $tables_config, $silent = false) {
    $script_file = $export_dir . '/import-to-excel.vbs';

    $content = "' === Excel Import Script ===\n";
    $content .= "' Run this VBScript to automatically import all CSV files into Excel\n\n";

    $content .= "Dim ExcelApp\n";
    $content .= "Dim ExcelWorkbook\n";
    $content .= "Dim ExcelSheet\n";
    $content .= "Dim fso\n";
    $content .= "Dim folder\n";
    $content .= "Dim file\n\n";

    $content .= "Set ExcelApp = CreateObject(\"Excel.Application\")\n";
    $content .= "ExcelApp.Visible = True\n";
    $content .= "Set ExcelWorkbook = ExcelApp.Workbooks.Add\n\n";

    $content .= "Set fso = CreateObject(\"Scripting.FileSystemObject\")\n";
    $content .= "Set folder = fso.GetFolder(\"" . str_replace('\\', '\\\\', $export_dir) . "\")\n\n";

    $sheet_num = 1;
    foreach ($tables_config as $table_key => $config) {
        $csv_name = sanitize_filename($config['title']) . '.csv';
        $sheet_name = substr($config['title'], 0, 31); // Excel sheet name limit

        $content .= "' Import " . $config['title'] . "\n";
        $content .= "Set ExcelSheet = ExcelWorkbook.Sheets($sheet_num)\n";
        $content .= "ExcelSheet.Name = \"$sheet_name\"\n";
        $content .= "With ExcelWorkbook.Sheets(\"$sheet_name\").QueryTables.Add(Connection:= _\n";
        $content .= "    \"TEXT;" . str_replace('\\', '\\\\', $export_dir) . "\\\\$csv_name\", _\n";
        $content .= "    Destination:=ExcelWorkbook.Sheets(\"$sheet_name\").Range(\"\$A\$1\"))\n";
        $content .= "    .TextFileParseType = xlDelimited\n";
        $content .= "    .TextFileCommaDelimiter = True\n";
        $content .= "    .Refresh\n";
        $content .= "End With\n\n";

        $sheet_num++;
    }

    $content .= "MsgBox \"CSV files imported successfully! Follow the EXCEL_SETUP_GUIDE.txt to configure dropdowns.\"\n";

    file_put_contents($script_file, $content);
    if (!$silent) {
        echo "✓ Created Excel import script: " . basename($script_file) . "\n";
    }
}

/**
 * Sanitize filename for cross-platform compatibility
 */
function sanitize_filename($filename) {
    return preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);
}

/**
 * Run export to a given directory (used by admin download).
 *
 * @param string $export_dir Full path to directory (will be created if needed).
 * @param bool   $silent     If true, no echo output.
 * @return string|false Export directory path on success, false on failure.
 */
function nds_run_export_to_directory($export_dir, $silent = true) {
    global $wpdb;
    if (empty($GLOBALS['nds_export_tables_config'])) {
        return false;
    }
    $tables_config = $GLOBALS['nds_export_tables_config'];
    if (!is_dir($export_dir)) {
        if (!@mkdir($export_dir, 0755, true)) {
            return false;
        }
    }
    $excel_file = $export_dir . '/nds-school-database.xlsx';
    generate_excel_file($tables_config, $export_dir, $excel_file, $silent);
    return $export_dir;
}

if (php_sapi_name() === 'cli') {
    echo "\n=== Export Summary ===\n";
    echo "Export directory: $export_dir\n";
    echo "Files created:\n";
    echo "- nds-school-database.xlsx (multi-sheet Excel workbook)\n";
    echo "- CSV files for each table\n";
    echo "- EXCEL_SETUP_GUIDE.txt (step-by-step instructions for dropdowns)\n";
    echo "- import-to-excel.vbs (optional: automated CSV import)\n";
}
?>