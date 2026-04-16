<?php
/**
 * Template Name: Student Application Form
 */

// #region agent log: applicationForm template entry
@file_put_contents(
    __DIR__ . '/../.cursor/debug.log',
    json_encode(array(
        'sessionId' => 'debug-session',
        'runId' => 'pre-fix',
        'hypothesisId' => 'H2',
        'location' => 'applicationForm.php:entry',
        'message' => 'Application form template loaded',
        'data' => array(
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            'is_user_logged_in' => is_user_logged_in(),
            'wp_user_id' => get_current_user_id(),
        ),
        'timestamp' => round(microtime(true) * 1000),
    )) . PHP_EOL,
    FILE_APPEND
);
// #endregion

get_header();

// Fetch data from database
global $wpdb;

// Table names
$table_faculties = $wpdb->prefix . 'nds_faculties';
$table_programs  = $wpdb->prefix . 'nds_programs';
$table_courses   = $wpdb->prefix . 'nds_courses';

// Fetch Faculties
$faculties = $wpdb->get_results("
    SELECT id, name
    FROM {$table_faculties}
    ORDER BY name ASC
", ARRAY_A);

// Fetch Programs
$programs = $wpdb->get_results("
    SELECT id, name, faculty_id, nqf_level AS level
    FROM {$table_programs}
    ORDER BY name ASC
", ARRAY_A);

// Courses / modules
$total_courses = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_courses}");

// Get all active courses with their programme information
// NOTE: Handle both 'active' (new schema) and 'Active' (old migration schema) for case-insensitive matching
$courses = $wpdb->get_results("
    SELECT c.id, c.name, c.nqf_level, c.program_id
    FROM {$table_courses} c
    WHERE LOWER(c.status) = 'active'
    ORDER BY c.name ASC
", ARRAY_A);

// #region agent log: courses query result
@file_put_contents(
    __DIR__ . '/../.cursor/debug.log',
    json_encode(array(
        'sessionId' => 'debug-session',
        'runId' => 'pre-fix-courses',
        'hypothesisId' => 'H_courses_query',
        'location' => 'applicationForm.php:courses_result',
        'message' => 'Courses query result',
        'data' => array(
            'courses_found' => count($courses),
            'first_3_course_ids' => array_slice(array_column($courses, 'id'), 0, 3),
        ),
        'timestamp' => round(microtime(true) * 1000),
    )) . PHP_EOL,
    FILE_APPEND
);
// #endregion

// If no courses found, keep arrays empty so the notice shows
if (empty($courses)) {
    $courses = array();
}

// Build a hierarchical catalogue: Faculty -> Program -> Qualification
$catalogue = array();

// Index faculties
foreach ($faculties as $faculty) {
    $fid = (int) $faculty['id'];
    $catalogue[$fid] = array(
    'id'       => $fid,
    'name'     => $faculty['name'],
    'programs' => array(),
    );
}

// Attach programs to their faculty
foreach ($programs as $program) {
    $pid        = (int) $program['id'];
    $faculty_id = isset($program['faculty_id']) ? (int) $program['faculty_id'] : 0;
    if (!$faculty_id || !isset($catalogue[$faculty_id])) {
        continue;
    }
  $catalogue[$faculty_id]['programs'][$pid] = array(
        'id'      => $pid,
        'name'    => $program['name'],
        'level'   => isset($program['level']) ? $program['level'] : '',
        'courses' => array(),
    );
}

// Attach qualifications (courses) to their program
foreach ($courses as $course) {
    $cid        = (int) $course['id'];
    $program_id = isset($course['program_id']) ? (int) $course['program_id'] : 0;
    if (!$program_id) {
        continue;
    }

    foreach ($catalogue as $fid => &$faculty_item) {
      if (isset($faculty_item['programs'][$program_id])) {
        $faculty_item['programs'][$program_id]['courses'][$cid] = array(
                'id'        => $cid,
                'name'      => $course['name'],
                'nqf_level' => isset($course['nqf_level']) ? $course['nqf_level'] : '',
            );
            break;
        }
    }
    unset($faculty_item);
}

// #region agent log: catalogue summary
@file_put_contents(
    __DIR__ . '/../.cursor/debug.log',
    json_encode(array(
        'sessionId'   => 'debug-session',
        'runId'       => 'pre-fix',
        'hypothesisId'=> 'H3',
        'location'    => 'applicationForm.php:catalogue',
        'message'     => 'Catalogue summary',
        'data'        => array(
            'facultyCount'   => count($catalogue),
            'facultyIds'     => array_slice(array_keys($catalogue), 0, 5),
        ),
        'timestamp'   => round(microtime(true) * 1000),
    )) . PHP_EOL,
    FILE_APPEND
);
// #endregion

// Prefill data for logged-in users (after Step 1 registration or existing accounts)
$prefill_full_name = '';
$prefill_email = '';
$lock_identity_fields = false;
if (is_user_logged_in()) {
    $current_user = wp_get_current_user();
    if ($current_user) {
        $first = isset($current_user->first_name) ? $current_user->first_name : '';
        $last  = isset($current_user->last_name) ? $current_user->last_name : '';
        $combined = trim($first . ' ' . $last);
        $prefill_full_name = $combined ?: $current_user->display_name;
        $prefill_email     = $current_user->user_email;
        // When a user is logged in, treat name + email as authoritative and lock them in the application.
        $lock_identity_fields = true;
    }
}

?>

<style>
/* Styling for input fields */
input[type=text],
input[type=date],
input[type=tel],
input[type=email],
select,
textarea {
  padding: 10px;
  border: 1px solid #ccc; /* Light gray border */
  border-radius: 5px; /* Rounded corners */
  width: 100%;
  box-sizing: border-box; /* Ensure padding and border are included in element's total width */
}

/* Styling for radio buttons and checkboxes */
input[type=radio],
input[type=checkbox] {
  margin-right: 5px;
}

/* Styling for submit button */
input[type=submit] {
  background-color: #4CAF50; /* Green */
  color: white;
  padding: 10px 20px;
  border: none;
  border-radius: 5px;
  cursor: pointer;
}

input[type=submit]:hover {
  background-color: #45a049; /* Darker green */
}

/* Styling for labels */
label {
  display: block;
  margin-bottom: 2px;
}

/* Styling for file input */
input[type=file] {
  margin-bottom: 10px;
}

/* Styling for checkboxes and radios wrapped in label elements */
label input[type=checkbox],
label input[type=radio] {
  margin-right: 5px;
}

.cf7-hidden {
  display: none !important;
}

.grid {
  display: grid;
}

.grid-cols-1 {
  grid-template-columns: repeat(1, minmax(0, 1fr));
}

.gap-4 {
  gap: 1rem;
}

.flex {
  display: flex;
}

@media (min-width: 768px) {
  .md\:grid-cols-2 {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  
  .md\:grid-cols-3 {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}

/* Simple multi-step wizard styling */
.nds-step {
  display: none;
}
.nds-step-active {
  display: block;
}
.nds-step-header {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 12px;
}
.nds-step-pill {
  width: 26px;
  height: 26px;
  border-radius: 999px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 0.8rem;
  background: #1f2933;
  color: #f9fafb;
}
.nds-step-title {
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: .08em;
  font-size: 0.9rem;
}
.nds-step-muted {
  font-size: 0.85rem;
  color: #6b7280;
}
.nds-error {
  background: #fef2f2;
  border: 1px solid #fecaca;
  color: #b91c1c;
  padding: 10px 12px;
  border-radius: 8px;
  font-size: 0.85rem;
  margin-bottom: 12px;
}
.nds-success-inline {
  background: #ecfdf3;
  border: 1px solid #bbf7d0;
  color: #047857;
  padding: 8px 10px;
  border-radius: 8px;
  font-size: 0.85rem;
  margin-bottom: 12px;
}
.nds-button-primary {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 10px 20px;
  border-radius: 999px;
  border: none;
  cursor: pointer;
  font-size: 0.95rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  background: linear-gradient(135deg, #1f2933, #0f172a);
  color: #f9fafb;
}
.nds-button-primary[disabled] {
  opacity: 0.6;
  cursor: default;
}

.nds-button-secondary {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 8px 18px;
  border-radius: 999px;
  border: 1px solid #d1d5db;
  background: #ffffff;
  color: #111827;
  font-size: 0.9rem;
  font-weight: 500;
}

.nds-step-nav {
  display: flex;
  justify-content: space-between;
  margin-top: 24px;
}

.nds-form-step {
  display: none;
}
.nds-form-step-active {
  display: block;
}

.password-field-wrap {
  position: relative;
}

.password-field-wrap input {
  padding-right: 92px;
}

.nds-password-toggle {
  position: absolute;
  right: 8px;
  top: 50%;
  transform: translateY(-50%);
  border: 1px solid #d1d5db;
  background: #ffffff;
  border-radius: 6px;
  padding: 6px 8px;
  font-size: 12px;
  cursor: pointer;
}

.nds-password-toggle:focus {
  outline: 2px solid #1f2933;
  outline-offset: 1px;
}

.nds-required-star {
  color: #b91c1c;
  font-weight: 700;
  margin-left: 4px;
}
</style>

<div id="registration-form" class="ast-container" style="max-width: 1200px; margin: 100px auto 40px; padding: 20px;">

<?php if (isset($_GET['application']) && $_GET['application'] === 'success'): ?>
  <div class="nds-app-success">
    <h3>
      <span>✓</span>
      Application submitted successfully
    </h3>
    <p>Thank you for your application. We have received your submission and will review it shortly. You will be contacted via email regarding the next steps.</p>
    <?php if (isset($_GET['id'])): ?>
      <p><strong>Application reference:</strong> <?php echo esc_html($_GET['id']); ?></p>
    <?php endif; ?>
  </div>
<?php endif; ?>

<!-- STEP 1: Create portal account -->
<div id="nds-step-1" class="nds-step nds-step-active">
  <div class="nds-step-header">
    <div class="nds-step-pill">1</div>
    <div>
      <div class="nds-step-title">Create your NDS Academy login</div>
      <div class="nds-step-muted">First create a portal account. You’ll use this to track your application.</div>
    </div>
  </div>

  <div id="nds-step1-error" class="nds-error" style="display:none;"></div>
  <div id="nds-step1-success" class="nds-success-inline" style="display:none;"></div>

  <form id="nds-step1-form" novalidate>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label for="nds_reg_first_name">First name</label>
      <input type="text" id="nds_reg_first_name" name="first_name" required>
      </div>
      <div>
        <label for="nds_reg_last_name">Last name</label>
      <input type="text" id="nds_reg_last_name" name="last_name" required>
      </div>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label for="nds_reg_email">Email (this will be your username)</label>
        <input type="email" id="nds_reg_email" name="email" required>
      </div>
      <div>
        <label for="nds_reg_email_confirm">Confirm email</label>
        <input type="email" id="nds_reg_email_confirm" name="email_confirm" required>
      </div>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label for="nds_reg_password">Password</label>
        <div class="password-field-wrap">
          <input type="password" id="nds_reg_password" name="password" required minlength="8">
          <button type="button" class="nds-password-toggle" data-target="nds_reg_password" aria-label="Show password" aria-pressed="false">&#128065; Show</button>
        </div>
        <small>Please match the requested format for password: at least 8 characters, 1 capital letter, 1 number, and 1 special character.</small>
      </div>
      <div>
        <label for="nds_reg_password_confirm">Confirm password</label>
        <div class="password-field-wrap">
          <input type="password" id="nds_reg_password_confirm" name="password_confirm" required minlength="8">
          <button type="button" class="nds-password-toggle" data-target="nds_reg_password_confirm" aria-label="Show password" aria-pressed="false">&#128065; Show</button>
        </div>
      </div>
    </div>
    <input type="hidden" id="nds_applicant_reg_nonce" value="<?php echo esc_attr( wp_create_nonce( 'nds_applicant_reg' ) ); ?>">
    <button type="submit" class="nds-button-primary" id="nds-step1-submit">
      Create account &amp; continue
    </button>
  </form>
</div>

<!-- STEP 2: Application form -->
<div id="nds-step-2" class="nds-step">

  <form method="POST" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" enctype="multipart/form-data">
  
  <input type="hidden" name="action" value="nds_application_form_submission">
  <?php wp_nonce_field('nds_application_form', 'nds_application_nonce'); ?>

  <!-- STEP 2: Personal details -->
  <div class="nds-form-step nds-form-step-active" data-step="2">
    <div class="nds-step-header" style="margin-top:24px;">
      <div class="nds-step-pill">2</div>
      <div>
        <div class="nds-step-title">Personal details</div>
        <div class="nds-step-muted">Tell us a bit about yourself.</div>
      </div>
    </div>

    <div class="form-control">
      <label for="full_name">Full Name:</label>
      <input type="text" name="full_name" id="full_name" required value="<?php echo esc_attr( $prefill_full_name ); ?>" <?php echo $lock_identity_fields ? 'readonly' : ''; ?>>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div>
        <label for="id_number">ID Number:</label>
        <input type="text" name="id_number" id="id_number" required>
      </div>
      <div>
        <label for="date_of_birth">Date Of Birth:</label>
        <input type="date" name="date_of_birth" id="date_of_birth" required>
      </div>
      <div>
        <label for="gender">Gender:</label>
        <select name="gender" id="gender" required>
          <option value="">---</option>
          <option value="Male">Male</option>
          <option value="Female">Female</option>
          <option value="Other">Other</option>
        </select>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div>
        <label for="nationality">Nationality:</label>
        <select name="nationality" id="nationality" required>
          <option value="">Select nationality</option>
          <option value="South African">South African</option>
          <option value="Lesotho">Lesotho</option>
          <option value="Eswatini">Eswatini</option>
          <option value="Botswana">Botswana</option>
          <option value="Zimbabwe">Zimbabwe</option>
          <option value="Mozambique">Mozambique</option>
          <option value="Namibia">Namibia</option>
          <option value="Other">Other</option>
        </select>
      </div>
      <div>
        <label for="country_of_birth">Country Of Birth:</label>
        <select name="country_of_birth" id="country_of_birth" required>
          <option value="">Select country</option>
          <option value="South Africa">South Africa</option>
          <option value="Lesotho">Lesotho</option>
          <option value="Eswatini">Eswatini</option>
          <option value="Botswana">Botswana</option>
          <option value="Zimbabwe">Zimbabwe</option>
          <option value="Mozambique">Mozambique</option>
          <option value="Namibia">Namibia</option>
          <option value="Other">Other</option>
        </select>
      </div>
      <div>
        <label for="marital_status">Marital Status:</label>
        <select name="marital_status" id="marital_status" required>
          <option value="">Select marital status</option>
          <option value="Single">Single</option>
          <option value="Married">Married</option>
          <option value="Divorced">Divorced</option>
          <option value="Widowed">Widowed</option>
          <option value="Separated">Separated</option>
          <option value="Co-habiting / Life Partner">Co-habiting / Life Partner</option>
        </select>
      </div>
    </div>

    <label for="street_address">Address:</label>
    <input type="text" name="street_address" id="street_address" placeholder="Street Address" required>
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <input type="text" name="city" id="city" placeholder="City" required>
      </div>
      <div>
        <input type="text" name="postal_code" id="postal_code" placeholder="Postal Code" required>
      </div>
    </div>
    
    <label for="province">Province:</label>
    <select name="province" id="province" required>
      <option value="">Select province</option>
      <option value="Eastern Cape">Eastern Cape</option>
      <option value="Free State">Free State</option>
      <option value="Gauteng">Gauteng</option>
      <option value="KwaZulu-Natal">KwaZulu-Natal</option>
      <option value="Limpopo">Limpopo</option>
      <option value="Mpumalanga">Mpumalanga</option>
      <option value="North West">North West</option>
      <option value="Northern Cape">Northern Cape</option>
      <option value="Western Cape">Western Cape</option>
    </select>

    <label for="cell_no">Cell No:</label>
    <input type="tel" name="cell_no" id="cell_no" required>
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label for="email">E-mail:</label>
        <input type="email" name="email" id="email" required value="<?php echo esc_attr( $prefill_email ); ?>" <?php echo $lock_identity_fields ? 'readonly' : ''; ?>>
      </div>
      <div>
        <label for="confirm_email">Confirm Email:</label>
        <input type="email" name="confirm_email" id="confirm_email" required value="<?php echo esc_attr( $prefill_email ); ?>" <?php echo $lock_identity_fields ? 'readonly' : ''; ?>>
      </div>
    </div>

    <div class="nds-step-nav">
      <span></span>
      <button type="button" class="nds-button-primary" data-next-step="3">Next: Choose faculty</button>
    </div>
  </div>

  <!-- STEP 3: Choose faculty, program and qualification -->
  <div class="nds-form-step" data-step="3">
    <div class="nds-step-header" style="margin-top:24px;">
      <div class="nds-step-pill">3</div>
      <div>
        <div class="nds-step-title">Choose faculty and qualification</div>
        <div class="nds-step-muted">Select your faculty first, then program, then qualification.</div>
      </div>
    </div>

  <h4 style="background-color: #d5dce1; padding: 10px;"><strong>3. PROGRAM AND QUALIFICATION SELECTION</strong></h4>
  <p>Please select the Faculty, Program and the intended Qualification you wish to apply for.</p>

  <div class="nds-form-grid">
    <div>
      <label for="faculty_select">Faculty:</label>
      <select name="faculty_id" id="faculty_select" required>
        <option value="">--- Select Faculty ---</option>
        <?php foreach ( $faculties as $faculty ) : ?>
          <option value="<?php echo esc_attr( $faculty['id'] ); ?>"><?php echo esc_html( $faculty['name'] ); ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div>
      <label for="program_select">Program:</label>
      <select name="program_id" id="program_select" required>
        <option value="">--- Select Program ---</option>
      </select>
    </div>

    <div>
      <label for="course_select">Qualification:</label> <!-- Mapped to $courses table -->
      <select name="course_id" id="course_select" required>
        <option value="">--- Select Qualification ---</option>
      </select>
      <!-- Helper hidden fields for processing -->
      <input type="hidden" name="course_name" id="course_name_hidden">
      <input type="hidden" name="level" id="course_level_hidden">
    </div>
  </div>
    <div class="nds-step-nav">
      <button type="button" class="nds-button-secondary" data-prev-step="2">Back</button>
      <button type="button" class="nds-button-primary" data-next-step="4">Next: Fees contact</button>
    </div>
  </div>

  <!-- STEP 4: Person responsible for fees -->
  <div class="nds-form-step" data-step="4">
    <div class="nds-step-header" style="margin-top:24px;">
      <div class="nds-step-pill">4</div>
      <div>
        <div class="nds-step-title">Person responsible for fees</div>
        <div class="nds-step-muted">Who will be paying for your studies?</div>
      </div>
    </div>

    <h4 style="background-color: #d5dce1; padding: 10px;"><strong>DETAILS OF PERSON RESPONSIBLE FOR FEES</strong></h4>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label for="responsible_full_name">Full Name:</label>
        <input type="text" name="responsible_full_name" id="responsible_full_name" required>
      </div>
      <div>
        <label for="relationship">Relationship to Applicant:</label>
        <select name="relationship" id="relationship">
          <option value="">Select relationship</option>
          <option value="Mother">Mother</option>
          <option value="Father">Father</option>
          <option value="Guardian">Guardian</option>
          <option value="Spouse / Partner">Spouse / Partner</option>
          <option value="Sibling">Sibling</option>
          <option value="Grandparent">Grandparent</option>
          <option value="Other">Other</option>
        </select>
      </div>
      <div>
        <label for="responsible_id_number">ID Number:</label>
        <input type="text" name="responsible_id_number" id="responsible_id_number" required>
      </div>
      <div>
        <label for="responsible_phone">Phone number:</label>
        <input type="tel" name="responsible_phone" id="responsible_phone" required>
      </div>
      <div>
        <label for="responsible_email">Email:</label>
        <input type="email" name="responsible_email" id="responsible_email" required>
      </div>
      <div>
        <label for="confirm_responsible_email">Confirm Email:</label>
        <input type="email" name="confirm_responsible_email" id="confirm_responsible_email" required>
      </div>
    </div>
    
    <label for="responsible_street_address">Address:</label>
    <input type="text" name="responsible_street_address" id="responsible_street_address" placeholder="Street Address">
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <input type="text" name="responsible_city" id="responsible_city" placeholder="City">
      </div>
      <div>
        <input type="text" name="responsible_postal_code" id="responsible_postal_code" placeholder="Postal Code">
      </div>
    </div>
    
    <label for="responsible_province">Province:</label>
    <select name="responsible_province" id="responsible_province">
      <option value="">Select province</option>
      <option value="Eastern Cape">Eastern Cape</option>
      <option value="Free State">Free State</option>
      <option value="Gauteng">Gauteng</option>
      <option value="KwaZulu-Natal">KwaZulu-Natal</option>
      <option value="Limpopo">Limpopo</option>
      <option value="Mpumalanga">Mpumalanga</option>
      <option value="North West">North West</option>
      <option value="Northern Cape">Northern Cape</option>
      <option value="Western Cape">Western Cape</option>
    </select>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label for="occupation">Occupation:</label>
        <input type="text" name="occupation" id="occupation" required>
      </div>
      <div>
        <label for="company_name">Company Name:</label>
        <input type="text" name="company_name" id="company_name">
      </div>
      <div>
        <label for="work_telephone">Work Telephone:</label>
        <input type="tel" name="work_telephone" id="work_telephone">
      </div>
      <div>
        <label for="work_email">Work E-mail (manager/HR):</label>
        <input type="email" name="work_email" id="work_email">
      </div>
    </div>

    <div class="nds-step-nav">
      <button type="button" class="nds-button-secondary" data-prev-step="3">Back</button>
      <button type="button" class="nds-button-primary" data-next-step="5">Next: Emergency contact</button>
    </div>
  </div>

  <!-- STEP 5: Emergency contact -->
  <div class="nds-form-step" data-step="5">
    <div class="nds-step-header" style="margin-top:24px;">
      <div class="nds-step-pill">5</div>
      <div>
        <div class="nds-step-title">Emergency contact</div>
        <div class="nds-step-muted">Who should we contact in case of emergency?</div>
      </div>
    </div>

    <label style="margin-bottom:12px; display:block;">
      <input type="checkbox" id="ice_same_as_responsible" checked>
      Emergency contact is the same as the person responsible for fees
    </label>

    <div id="emergency-fields">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label for="emergency_full_name">First Name:</label>
          <input type="text" name="emergency_full_name" id="emergency_full_name" required>
        </div>
        <div>
          <label for="emergency_relationship">Relationship:</label>
          <select name="emergency_relationship" id="emergency_relationship">
            <option value="">Select relationship</option>
            <option value="Mother">Mother</option>
            <option value="Father">Father</option>
            <option value="Guardian">Guardian</option>
            <option value="Spouse / Partner">Spouse / Partner</option>
            <option value="Sibling">Sibling</option>
            <option value="Grandparent">Grandparent</option>
            <option value="Friend">Friend</option>
            <option value="Other">Other</option>
          </select>
        </div>
        <div>
          <label for="emergency_phone">Phone number:</label>
          <input type="tel" name="emergency_phone" id="emergency_phone" required>
        </div>
        <div>
          <label for="emergency_email">Email:</label>
          <input type="email" name="emergency_email" id="emergency_email" required>
        </div>
      </div>
      
      <label for="emergency_street_address">Address:</label>
      <input type="text" name="emergency_street_address" id="emergency_street_address" placeholder="Street Address">
      
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <input type="text" name="emergency_city" id="emergency_city" placeholder="City">
        </div>
        <div>
          <input type="text" name="emergency_postal_code" id="emergency_postal_code" placeholder="Postal Code">
        </div>
      </div>
      
      <label for="emergency_province">Province:</label>
      <select name="emergency_province" id="emergency_province">
        <option value="">Select province</option>
        <option value="Eastern Cape">Eastern Cape</option>
        <option value="Free State">Free State</option>
        <option value="Gauteng">Gauteng</option>
        <option value="KwaZulu-Natal">KwaZulu-Natal</option>
        <option value="Limpopo">Limpopo</option>
        <option value="Mpumalanga">Mpumalanga</option>
        <option value="North West">North West</option>
        <option value="Northern Cape">Northern Cape</option>
        <option value="Western Cape">Western Cape</option>
      </select>
    </div>

    <div class="nds-step-nav">
      <button type="button" class="nds-button-secondary" data-prev-step="4">Back</button>
      <button type="button" class="nds-button-primary" data-next-step="6">Next: Documents & submit</button>
    </div>
  </div>

  <!-- STEP 6: Upload documents & submit -->
  <div class="nds-form-step" data-step="6">
    <div class="nds-step-header" style="margin-top:24px;">
      <div class="nds-step-pill">6</div>
      <div>
        <div class="nds-step-title">Upload documents & submit</div>
        <div class="nds-step-muted">Upload your supporting documents and send your application.</div>
      </div>
    </div>

  <h4 style="background-color: #d5dce1; padding: 10px;"><strong>EDUCATIONAL BACKGROUND</strong></h4>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
      <label for="highest_grade">Highest Grade Passed:</label>
      <select name="highest_grade" id="highest_grade" required>
        <option value="">Select highest level</option>
        <option value="Grade 10">Grade 10</option>
        <option value="Grade 11">Grade 11</option>
        <option value="Grade 12 / Matric (NSC)">Grade 12 / Matric (NSC)</option>
        <option value="N3">N3</option>
        <option value="N4">N4</option>
        <option value="Higher Certificate">Higher Certificate</option>
        <option value="Diploma">Diploma</option>
        <option value="Bachelor's Degree">Bachelor's Degree</option>
        <option value="Other">Other</option>
      </select>
    </div>
    <div>
      <label for="year_passed">Year Passed:</label>
      <select name="year_passed" id="year_passed" required>
        <option value="">Select year</option>
        <?php for ( $y = (int) date( 'Y' ); $y >= ( (int) date( 'Y' ) - 40 ); $y-- ) : ?>
          <option value="<?php echo esc_attr( $y ); ?>"><?php echo esc_html( $y ); ?></option>
        <?php endfor; ?>
      </select>
    </div>
    <div>
      <label for="school_attended">School Attended:</label>
      <input type="text" name="school_attended" id="school_attended" required>
    </div>
    <div>
      <label for="school_location">Area/Town Of School:</label>
      <input type="text" name="school_location" id="school_location">
    </div>
    <div>
      <label for="other_qualifications">Other Qualifications:</label>
      <input type="text" name="other_qualifications" id="other_qualifications">
    </div>
    <div>
      <label for="year_completion">Year of Completion:</label>
      <select name="year_completion" id="year_completion">
        <option value="">Select year</option>
        <?php for ( $y = (int) date( 'Y' ); $y >= ( (int) date( 'Y' ) - 40 ); $y-- ) : ?>
          <option value="<?php echo esc_attr( $y ); ?>"><?php echo esc_html( $y ); ?></option>
        <?php endfor; ?>
      </select>
    </div>
  </div>

  <h4 style="background-color: #d5dce1; padding: 10px;"><strong>LANGUAGES</strong></h4>

  <label for="home_language">Home Language:</label>
  <select name="home_language" id="home_language">
    <option value="">Select language</option>
    <option value="Afrikaans">Afrikaans</option>
    <option value="English">English</option>
    <option value="isiNdebele">isiNdebele</option>
    <option value="isiXhosa">isiXhosa</option>
    <option value="isiZulu">isiZulu</option>
    <option value="Sepedi (Northern Sotho)">Sepedi (Northern Sotho)</option>
    <option value="Sesotho">Sesotho</option>
    <option value="Setswana">Setswana</option>
    <option value="siSwati">siSwati</option>
    <option value="Tshivenda">Tshivenda</option>
    <option value="Xitsonga">Xitsonga</option>
    <option value="Other">Other</option>
  </select>
  <br>

  <label for="english"><b>English:</b></label>
  
  <div class="flex gap-4">
    <p>Write:</p>
    <label><input type="radio" name="english_write" value="Good" checked> Good</label>
    <label><input type="radio" name="english_write" value="Fair"> Fair</label>
    <label><input type="radio" name="english_write" value="Poor"> Poor</label>
  </div>

  <div class="flex gap-4">
    <p>Read:</p>
    <label><input type="radio" name="english_read" value="Good" checked> Good</label>
    <label><input type="radio" name="english_read" value="Fair"> Fair</label>
    <label><input type="radio" name="english_read" value="Poor"> Poor</label>
  </div>

  <div class="flex gap-4">
    <p>Speak:</p>
    <label><input type="radio" name="english_speak" value="Good" checked> Good</label>
    <label><input type="radio" name="english_speak" value="Fair"> Fair</label>
    <label><input type="radio" name="english_speak" value="Poor"> Poor</label>
  </div>
  <br>

  <label for="other_language">Other Language:</label>
  <select name="other_language" id="other_language">
    <option value="">Select language</option>
    <option value="Afrikaans">Afrikaans</option>
    <option value="English">English</option>
    <option value="isiNdebele">isiNdebele</option>
    <option value="isiXhosa">isiXhosa</option>
    <option value="isiZulu">isiZulu</option>
    <option value="Sepedi (Northern Sotho)">Sepedi (Northern Sotho)</option>
    <option value="Sesotho">Sesotho</option>
    <option value="Setswana">Setswana</option>
    <option value="siSwati">siSwati</option>
    <option value="Tshivenda">Tshivenda</option>
    <option value="Xitsonga">Xitsonga</option>
    <option value="Other">Other</option>
  </select>

  <div class="flex gap-4">
    <p>Write:</p>
    <label><input type="radio" name="other_language_write" value="Good" checked> Good</label>
    <label><input type="radio" name="other_language_write" value="Fair"> Fair</label>
    <label><input type="radio" name="other_language_write" value="Poor"> Poor</label>
  </div>

  <div class="flex gap-4">
    <p>Read:</p>
    <label><input type="radio" name="other_language_read" value="Good" checked> Good</label>
    <label><input type="radio" name="other_language_read" value="Fair"> Fair</label>
    <label><input type="radio" name="other_language_read" value="Poor"> Poor</label>
  </div>

  <div class="flex gap-4">
    <p>Speak:</p>
    <label><input type="radio" name="other_language_speak" value="Good" checked> Good</label>
    <label><input type="radio" name="other_language_speak" value="Fair"> Fair</label>
    <label><input type="radio" name="other_language_speak" value="Poor"> Poor</label>
  </div>

  <h4 style="background-color: #d5dce1; padding: 10px;"><strong>MEDICAL QUESTIONS</strong></h4>

  <label for="physical_illness">Do you suffer from any physical illness or disability?</label><br>
  <label><input type="radio" name="physical_illness" value="Yes" checked> Yes</label>
  <label><input type="radio" name="physical_illness" value="No"> No</label>
  <label for="specify_physical_illness">If YES please specify:</label>
  <input type="text" name="specify_physical_illness" id="specify_physical_illness">

  <label for="food_allergies">Do you have any food allergies?</label><br>
  <label><input type="radio" name="food_allergies" value="Yes" checked> Yes</label>
  <label><input type="radio" name="food_allergies" value="No"> No</label>
  <label for="specify_food_allergies">If answered YES please specify:</label>
  <input type="text" name="specify_food_allergies" id="specify_food_allergies">

  <label for="chronic_medication">Are you on any chronic medication?</label><br>
  <label><input type="radio" name="chronic_medication" value="Yes" checked> Yes</label>
  <label><input type="radio" name="chronic_medication" value="No"> No</label>
  <label for="specify_chronic_medication">If answered YES please specify:</label>
  <input type="text" name="specify_chronic_medication" id="specify_chronic_medication">

  <label for="pregnant_or_planning">Are you pregnant or planning on to be during your training?</label><br>
  <label><input type="radio" name="pregnant_or_planning" value="Yes" checked> Yes</label>
  <label><input type="radio" name="pregnant_or_planning" value="No"> No</label>

  <label for="smoke">Do you smoke?</label><br>
  <label><input type="radio" name="smoke" value="Yes" checked> Yes</label>
  <label><input type="radio" name="smoke" value="No"> No</label>

  <h4 style="background-color: #d5dce1; padding: 10px;"><strong>SUPPORTING DOCUMENTS</strong></h4>

  <label for="id_passport_applicant">Certified ID/Passport copy:</label>
  <input type="file" name="id_passport_applicant" id="id_passport_applicant" accept=".pdf">

  <label for="id_passport_responsible">Certified ID/Passport copy of person respon2323sible for fees:</label>
  <input type="file" name="id_passport_responsible" id="id_passport_responsible" accept=".pdf">

  <label for="saqa_certificate">Certified SAQA certificate copy (for non South Africans):</label>
  <input type="file" name="saqa_certificate" id="saqa_certificate" accept=".pdf">

  <label for="study_permit">Certified study permit copy if not South African:</label>
  <input type="file" name="study_permit" id="study_permit" accept=".pdf">

  <label for="parent_spouse_id">ID/Passport copy of Parent/Spouse:</label>
  <input type="file" name="parent_spouse_id" id="parent_spouse_id" accept=".pdf">

  <label for="latest_results">Certified copy of latest results:</label>
  <input type="file" name="latest_results" id="latest_results" accept=".pdf">

  <label for="proof_residence">Proof of residence:</label>
  <input type="file" name="proof_residence" id="proof_residence" accept=".pdf">

  <label for="highest_grade_cert">Certified copy of highest grade passed certificate/report:</label>
  <input type="file" name="highest_grade_cert" id="highest_grade_cert" accept=".pdf">

  <label for="proof_medical_aid">Proof of medical aid if available:</label>
  <input type="file" name="proof_medical_aid" id="proof_medical_aid" accept=".pdf">

  <h4 style="background-color: #d5dce1; padding: 10px;"><strong>DECLARATION</strong></h4>

  <label>
    <input type="checkbox" name="declaration" value="1" required>
    I hereby declare that the above information is complete, true and correct according to my knowledge.
  </label>

  <h4 style="background-color: #d5dce1; padding: 10px;"><strong>MOTIVATION LETTER</strong></h4>

  <label for="motivation_letter">Write a short motivational letter about why you want to study in the field of hospitality and why you should be enrolled with NDS Academy:</label><br>
  <textarea name="motivation_letter" id="motivation_letter" rows="6" required></textarea>

  <div class="nds-step-nav">
    <button type="button" class="nds-button-secondary" data-prev-step="5">Back</button>
    <input type="submit" value="Submit">
  </div>

  </div> <!-- /step 6 -->

  </form>
</div> <!-- /#nds-step-2 -->

<script>
document.addEventListener('DOMContentLoaded', function() {
  const ajaxUrl = "<?php echo esc_url( admin_url('admin-ajax.php') ); ?>";
  const ndsCatalogue = <?php echo wp_json_encode( $catalogue ); ?>;

  // Multi-step flow: if already logged in, skip straight to step 2
  <?php if ( is_user_logged_in() ) : ?>
    document.getElementById('nds-step-1').classList.remove('nds-step-active');
    document.getElementById('nds-step-1').style.display = 'none';
    document.getElementById('nds-step-2').classList.add('nds-step-active');
  <?php endif; ?>

  const step1Form = document.getElementById('nds-step1-form');
  const step1Error = document.getElementById('nds-step1-error');
  const step1Success = document.getElementById('nds-step1-success');
  const step1Button = document.getElementById('nds-step1-submit');
  const strongPasswordPattern = /^(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/;
  const passwordFormatMessage = 'Please match the requested format for password: at least 8 characters, 1 capital letter, 1 number, and 1 special character.';

  function addRequiredStars(root) {
    if (!root) return;

    var requiredFields = root.querySelectorAll('input[required], select[required], textarea[required]');
    requiredFields.forEach(function(field) {
      var label = null;

      if (field.id) {
        label = root.querySelector('label[for="' + field.id + '"]');
      }

      if (!label) {
        label = field.closest('label');
      }

      if (!label || label.querySelector('.nds-required-star')) {
        return;
      }

      var star = document.createElement('span');
      star.className = 'nds-required-star';
      star.setAttribute('aria-hidden', 'true');
      star.textContent = '*';
      label.appendChild(star);
    });
  }

  addRequiredStars(document.getElementById('nds-step1-form'));
  addRequiredStars(document.getElementById('nds-step-2'));

  var passwordToggles = document.querySelectorAll('.nds-password-toggle');
  if (passwordToggles && passwordToggles.length) {
    passwordToggles.forEach(function(btn) {
      btn.addEventListener('click', function() {
        var targetId = btn.getAttribute('data-target');
        var input = targetId ? document.getElementById(targetId) : null;
        if (!input) return;

        var isHidden = input.type === 'password';
        input.type = isHidden ? 'text' : 'password';
        btn.setAttribute('aria-pressed', isHidden ? 'true' : 'false');
        btn.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
        btn.innerHTML = isHidden ? '&#128065; Hide' : '&#128065; Show';
      });
    });
  }

  if (step1Form) {
    step1Form.addEventListener('submit', function(e) {
      e.preventDefault();

      step1Error.style.display = 'none';
      step1Success.style.display = 'none';

      const firstName = document.getElementById('nds_reg_first_name').value.trim();
      const lastName = document.getElementById('nds_reg_last_name').value.trim();
      const email = document.getElementById('nds_reg_email').value.trim();
      const emailConfirm = document.getElementById('nds_reg_email_confirm').value.trim();
      const password = document.getElementById('nds_reg_password').value;
      const passwordConfirm = document.getElementById('nds_reg_password_confirm').value;
      const nonce = document.getElementById('nds_applicant_reg_nonce').value;

      if (!email || email !== emailConfirm) {
        step1Error.textContent = 'Email addresses must match.';
        step1Error.style.display = 'block';
        return;
      }
      if (!strongPasswordPattern.test(password)) {
        step1Error.textContent = passwordFormatMessage;
        step1Error.style.display = 'block';
        return;
      }
      if (password !== passwordConfirm) {
        step1Error.textContent = 'Passwords must match.';
        step1Error.style.display = 'block';
        return;
      }

      step1Button.disabled = true;
      step1Button.textContent = 'Creating account...';

      const params = new URLSearchParams();
      params.append('action', 'nds_register_applicant_user');
      params.append('nonce', nonce);
      params.append('first_name', firstName);
      params.append('last_name', lastName);
      params.append('email', email);
      params.append('password', password);

      fetch(ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: params.toString(),
      })
        .then(function(res) { return res.json(); })
        .then(function(json) {
          if (!json || !json.success) {
            const msg = (json && json.data) ? json.data : 'Failed to create account. Please try again.';
            step1Error.textContent = msg;
            step1Error.style.display = 'block';
            return;
          }

          // Success: reload page so WordPress creates a fresh nonce for the now-logged-in user.
          // The PHP template will detect the logged-in user and automatically start on Step 2.
          window.location.reload();
        })
        .catch(function() {
          step1Error.textContent = 'Something went wrong while creating your account. Please try again.';
          step1Error.style.display = 'block';
        })
        .finally(function() {
          step1Button.disabled = false;
          step1Button.textContent = 'Create account & continue';
        });
    });
  }

  // #region agent log: final application form submit (frontend) - AJAX SUBMISSION
  (function () {
    const appStep2 = document.getElementById('nds-step-2');
    const appForm = appStep2 ? appStep2.querySelector('form') : null;
    if (appForm) {
      appForm.addEventListener('submit', function (e) {
        e.preventDefault(); // PREVENT default form submission - we'll use AJAX
        
        // #region agent log: form submit intercepted for AJAX
        try {
          fetch('http://127.0.0.1:7247/ingest/dd126561-a5b5-4577-8b70-512cd5168604', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              sessionId: 'debug-session',
              runId: 'post-fix-ajax',
              hypothesisId: 'H_js_submit_ajax',
              location: 'applicationForm.php:form_submit_ajax',
              message: 'Form submit intercepted - converting to AJAX',
              data: {
                formFieldsCount: appForm.querySelectorAll('input, select, textarea').length
              },
              timestamp: Date.now()
            })
          }).catch(function () {});
        } catch (e) {}
        // #endregion

        // Disable submit button
        const submitBtn = appForm.querySelector('input[type="submit"]');
        if (submitBtn) {
          submitBtn.disabled = true;
          submitBtn.value = 'Submitting...';
        }

        // Build FormData from form
        const formData = new FormData(appForm);
        formData.append('action', 'nds_submit_application'); // Change to AJAX action
        formData.append('nonce', '<?php echo wp_create_nonce("nds_application_form"); ?>'); // Add fresh nonce

        // Submit via AJAX
        fetch(ajaxUrl, {
          method: 'POST',
          credentials: 'same-origin',
          body: formData
        })
        .then(function(res) { return res.json(); })
        .then(function(json) {
          // #region agent log: AJAX response received
          try {
            fetch('http://127.0.0.1:7247/ingest/dd126561-a5b5-4577-8b70-512cd5168604', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({
                sessionId: 'debug-session',
                runId: 'post-fix-ajax',
                hypothesisId: 'H_ajax_response',
                location: 'applicationForm.php:ajax_response',
                message: 'AJAX response received',
                data: {
                  success: json && json.success,
                  has_data: !!(json && json.data),
                  error_message: (json && json.data && json.data.message) ? json.data.message : null,
                },
                timestamp: Date.now()
              })
            }).catch(function () {});
          } catch (e) {}
          // #endregion

          // Show error message on page (NO REDIRECT)
          if (!json || !json.success) {
            const errorMsg = (json && json.data && json.data.message) 
              ? json.data.message 
              : 'Failed to submit application. Please try again.';
            
            // Create or update error display
            let errorDiv = document.getElementById('nds-application-error');
            if (!errorDiv) {
              errorDiv = document.createElement('div');
              errorDiv.id = 'nds-application-error';
              errorDiv.className = 'nds-error';
              errorDiv.style.display = 'block';
              errorDiv.style.marginTop = '20px';
              errorDiv.style.marginBottom = '20px';
              appForm.insertBefore(errorDiv, appForm.firstChild);
            }
            errorDiv.textContent = 'ERROR: ' + errorMsg;
            errorDiv.style.display = 'block';
            
            // Scroll to error
            errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
            
            if (submitBtn) {
              submitBtn.disabled = false;
              submitBtn.value = 'Submit';
            }
            return;
          }

          // Success: Redirect to portal with application ID
          if (json.data && json.data.redirect_url) {
            window.location.href = json.data.redirect_url;
          } else {
            // Fallback redirect
            const portalUrl = "<?php echo esc_js( home_url('/portal/') ); ?>";
            const redirectUrl = new URL(portalUrl);
            redirectUrl.searchParams.set('application', 'success');
            if (json.data && json.data.application_id) {
              redirectUrl.searchParams.set('id', json.data.application_id);
            }
            window.location.href = redirectUrl.toString();
          }
        })
        .catch(function(error) {
          console.error('Application submission error:', error);
          
          // Show error on page
          let errorDiv = document.getElementById('nds-application-error');
          if (!errorDiv) {
            errorDiv = document.createElement('div');
            errorDiv.id = 'nds-application-error';
            errorDiv.className = 'nds-error';
            errorDiv.style.display = 'block';
            errorDiv.style.marginTop = '20px';
            errorDiv.style.marginBottom = '20px';
            const appForm = document.getElementById('nds-step-2').querySelector('form');
            if (appForm) {
              appForm.insertBefore(errorDiv, appForm.firstChild);
            }
          }
          errorDiv.textContent = 'ERROR: Network error - ' + (error.message || 'Failed to submit application. Please check your connection and try again.');
          errorDiv.style.display = 'block';
          errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
          
          if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.value = 'Submit';
          }
        });
      });
    }
  })();
  // #endregion

  // Multi-step navigation for main application (steps 2-6)
  const formSteps = document.querySelectorAll('.nds-form-step');
  function showFormStep(step) {
    formSteps.forEach(function(el) {
      if (parseInt(el.getAttribute('data-step'), 10) === step) {
        el.classList.add('nds-form-step-active');
      } else {
        el.classList.remove('nds-form-step-active');
      }
    });
    window.scrollTo({ top: document.getElementById('registration-form').offsetTop, behavior: 'smooth' });
  }

  document.querySelectorAll('[data-next-step]').forEach(function(btn) {
    btn.addEventListener('click', function() {
      const target = parseInt(this.getAttribute('data-next-step'), 10);
      const activeStep = document.querySelector('.nds-form-step.nds-form-step-active');

      // Block moving forward until all required fields in current step are valid.
      if (activeStep) {
        const requiredFields = activeStep.querySelectorAll('input[required], select[required], textarea[required]');
        for (let i = 0; i < requiredFields.length; i++) {
          const field = requiredFields[i];

          // Ignore fields that are disabled or not currently visible.
          if (field.disabled || field.offsetParent === null) {
            continue;
          }

          if (!field.checkValidity()) {
            field.reportValidity();
            return;
          }
        }
      }

      if (!isNaN(target)) {
        showFormStep(target);
      }
    });
  });

  document.querySelectorAll('[data-prev-step]').forEach(function(btn) {
    btn.addEventListener('click', function() {
      const target = parseInt(this.getAttribute('data-prev-step'), 10);
      if (!isNaN(target)) {
        showFormStep(target);
      }
    });
  });

  // Emergency contact: same as responsible for fees toggle
  const sameAsResponsibleCheckbox = document.getElementById('ice_same_as_responsible');
  const emergencyFieldsWrapper = document.getElementById('emergency-fields');
  const emergencyRequiredFields = emergencyFieldsWrapper
    ? emergencyFieldsWrapper.querySelectorAll('input[required], select[required], textarea[required]')
    : [];

  function setEmergencyRequired(enabled) {
    if (!emergencyRequiredFields || !emergencyRequiredFields.length) {
      return;
    }

    emergencyRequiredFields.forEach(function(field) {
      if (enabled) {
        field.setAttribute('required', 'required');
      } else {
        field.removeAttribute('required');
      }
    });
  }

  function syncEmergencyFromResponsible() {
    const responsibleName = document.getElementById('responsible_full_name')?.value || '';
    const responsiblePhone = document.getElementById('responsible_phone')?.value || '';
    const responsibleEmail = document.getElementById('responsible_email')?.value || '';
    const responsibleAddress = document.getElementById('responsible_street_address')?.value || '';
    const responsibleCity = document.getElementById('responsible_city')?.value || '';
    const responsiblePostal = document.getElementById('responsible_postal_code')?.value || '';
    const responsibleProvince = document.getElementById('responsible_province')?.value || '';

    const efName = document.getElementById('emergency_full_name');
    const efPhone = document.getElementById('emergency_phone');
    const efEmail = document.getElementById('emergency_email');
    const efAddress = document.getElementById('emergency_street_address');
    const efCity = document.getElementById('emergency_city');
    const efPostal = document.getElementById('emergency_postal_code');
    const efProvince = document.getElementById('emergency_province');

    if (efName) efName.value = responsibleName;
    if (efPhone) efPhone.value = responsiblePhone;
    if (efEmail) efEmail.value = responsibleEmail;
    if (efAddress) efAddress.value = responsibleAddress;
    if (efCity) efCity.value = responsibleCity;
    if (efPostal) efPostal.value = responsiblePostal;
    if (efProvince) efProvince.value = responsibleProvince;
  }

  if (sameAsResponsibleCheckbox && emergencyFieldsWrapper) {
    const toggleEmergencyFields = function() {
      if (sameAsResponsibleCheckbox.checked) {
        emergencyFieldsWrapper.style.display = 'none';
        setEmergencyRequired(false);
        syncEmergencyFromResponsible();
      } else {
        emergencyFieldsWrapper.style.display = 'block';
        setEmergencyRequired(true);
      }
    };

    sameAsResponsibleCheckbox.addEventListener('change', toggleEmergencyFields);
    toggleEmergencyFields();
  }

  const facultySelect = document.getElementById('faculty_select');
  const programSelect = document.getElementById('program_select');
  const courseSelect = document.getElementById('course_select');
  const courseNameHidden = document.getElementById('course_name_hidden');
  const courseLevelHidden = document.getElementById('course_level_hidden');

  if (facultySelect && programSelect && courseSelect && courseNameHidden) {
    // Users must choose in order: faculty -> program -> qualification.
    programSelect.disabled = true;
    courseSelect.disabled = true;

    // When faculty changes, populate program options.
    facultySelect.addEventListener('change', function () {
      const fid = this.value ? parseInt(this.value, 10) : 0;

      // Reset program and qualification selects.
      programSelect.value = '';
      courseSelect.value = '';
      if (courseNameHidden) courseNameHidden.value = '';

      // Rebuild program options.
      programSelect.innerHTML = '<option value="">--- Select Program ---</option>';
      programSelect.disabled = true;

      if (fid && ndsCatalogue && ndsCatalogue[fid]) {
        const item = ndsCatalogue[fid];
        if (item.programs) {
          const programsList = item.programs;
          const programCount = Object.keys(programsList).length;
          console.log('Faculty ID:', fid, 'Programs found:', programCount);
          
          Object.keys(programsList).forEach(function (pid) {
            const programObj = programsList[pid];
            const opt = document.createElement('option');
            opt.value = programObj.id;
            opt.textContent = programObj.name;
            programSelect.appendChild(opt);
          });

          if (programCount > 0) {
            programSelect.disabled = false;
          }
        } else {
          console.warn('No programs found for faculty ID:', fid);
        }
      } else {
        console.warn('Faculty not found in catalogue:', fid);
      }

      // Clear qualifications.
      courseSelect.innerHTML = '<option value="">--- Select Qualification ---</option>';
      courseSelect.disabled = true;
    });

    // When program changes, populate qualification options.
    programSelect.addEventListener('change', function () {
      const fid = facultySelect.value ? parseInt(facultySelect.value, 10) : 0;
      const pid = this.value ? parseInt(this.value, 10) : 0;

      courseSelect.value = '';
      if (courseNameHidden) courseNameHidden.value = '';

      courseSelect.innerHTML = '<option value="">--- Select Qualification ---</option>';
      courseSelect.disabled = true;

      if (fid && pid && ndsCatalogue && ndsCatalogue[fid] && ndsCatalogue[fid].programs && ndsCatalogue[fid].programs[pid]) {
        const courses = ndsCatalogue[fid].programs[pid].courses || {};
        const courseCount = Object.keys(courses).length;
        console.log('Program ID:', pid, 'Qualifications found:', courseCount);
        
        Object.keys(courses).forEach(function (cid) {
          const course = courses[cid];
          const opt = document.createElement('option');
          opt.value = course.id;
          opt.textContent = course.name + (course.nqf_level ? ' (NQF ' + course.nqf_level + ')' : '');
          courseSelect.appendChild(opt);
        });

        if (courseCount > 0) {
          courseSelect.disabled = false;
        }
      } else {
        console.warn('Program not found in catalogue. Faculty ID:', fid, 'Program ID:', pid);
      }
    });

    // Store course name in hidden field
    courseSelect.addEventListener('change', function () {
      if (this.selectedIndex >= 0 && this.options[this.selectedIndex]) {
        const selectedOption = this.options[this.selectedIndex];
        if (courseNameHidden) {
          courseNameHidden.value = selectedOption.text || '';
        }
        if (courseLevelHidden) {
          var courseId = parseInt(this.value, 10);
          var fid = facultySelect.value ? parseInt(facultySelect.value, 10) : 0;
          var pid = programSelect.value ? parseInt(programSelect.value, 10) : 0;
          var levelValue = '';
          if (fid && pid && ndsCatalogue && ndsCatalogue[fid] && ndsCatalogue[fid].programs && ndsCatalogue[fid].programs[pid]) {
            var courses = ndsCatalogue[fid].programs[pid].courses || {};
            if (courses[courseId] && typeof courses[courseId].nqf_level !== 'undefined' && courses[courseId].nqf_level !== null) {
              levelValue = String(courses[courseId].nqf_level);
            }
          }
          courseLevelHidden.value = levelValue;
        }
      }
    });
  }

  // Email validation - confirm email must match
  const emailInput = document.getElementById('email');
  const confirmEmailInput = document.getElementById('confirm_email');
  
  if (emailInput && confirmEmailInput) {
    confirmEmailInput.addEventListener('blur', function() {
      if (this.value && emailInput.value !== this.value) {
        alert('Email addresses do not match!');
        this.value = '';
      }
    });
  }

  // Responsible person email validation
  const respEmailInput = document.getElementById('responsible_email');
  const confirmRespEmailInput = document.getElementById('confirm_responsible_email');
  
  if (respEmailInput && confirmRespEmailInput) {
    confirmRespEmailInput.addEventListener('blur', function() {
      if (this.value && respEmailInput.value !== this.value) {
        alert('Email addresses do not match!');
        this.value = '';
      }
    });
  }
});
</script>

</div>

<?php
get_footer();
?>



