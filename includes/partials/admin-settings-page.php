<?php
/**
 * NDS Academy Settings page content.
 * Expects: $seed_status, $wipe_status, $wipe_tables_status, $import_export_status, $msg (set by nds_settings_page).
 */

if (!defined('ABSPATH')) {
    exit;
}

// Store messages for JavaScript
$js_messages = [
    'seed' => ['status' => $seed_status, 'message' => $msg],
    'wipe_core' => ['status' => $wipe_status, 'message' => $msg],
    'wipe_selected' => ['status' => $wipe_tables_status, 'message' => $msg],
    'import_export' => ['status' => $import_export_status, 'message' => $msg]
];
?>
<div class="nds-tailwind-wrapper bg-gray-50 pb-32" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin-left: -20px; padding-left: 20px; margin-top: -20px;">
    <style>
        /* Ensure the WordPress footer doesn't overlap our custom dashboard */
        body[class*="nds-settings"] #wpfooter { display: none !important; }
        .nds-tailwind-wrapper { position: relative; z-index: 1; }
    </style>
    <!-- Modern Header -->
    <div class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-6">
                <div class="flex items-center space-x-4">
                    <div class="w-12 h-12 bg-gradient-to-br from-purple-600 to-indigo-600 rounded-xl flex items-center justify-center">
                        <span class="dashicons dashicons-admin-settings text-white text-2xl"></span>
                    </div>
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900" style="margin:0; line-height:1.2;">Academy Settings</h1>
                        <p class="text-gray-600" style="margin:0;">Configure core system data, imports, exports, and access controls.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- ===== STATUS PANEL (RIGHT SIDE) ===== -->
        <div id="nds-status-panel" style="
            position: fixed;
            right: 20px;
            top: 100px;
            width: 320px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            padding: 20px;
            z-index: 9999;
            border-left: 4px solid #9333ea;
            display: none;
            opacity: 0;
            transform: translateX(20px);
            transition: all 0.3s ease;
        ">
            <button id="nds-close-panel" style="
                position: absolute;
                top: 8px;
                right: 8px;
                background: none;
                border: none;
                font-size: 18px;
                cursor: pointer;
                color: #6b7280;
                padding: 4px;
                width: 24px;
                height: 24px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
            ">×</button>
            
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 15px;">
                <div id="nds-status-icon" style="
                    width: 40px;
                    height: 40px;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 20px;
                    background: #f3f4f6;
                ">
                    ⭕
                </div>
                <div>
                    <h3 style="margin: 0; color: #1f2937; font-size: 16px;" id="nds-status-title">Processing</h3>
                    <p style="margin: 4px 0 0 0; color: #6b7280; font-size: 13px;" id="nds-status-subtitle">Please wait...</p>
                </div>
            </div>
            
            <p id="nds-main-message" style="margin: 0; font-size: 14px; color: #374151; line-height: 1.5;">
                Operation in progress
            </p>
            
            <div id="nds-action-buttons" style="margin-top: 15px; display: none; gap: 10px;">
                <button id="nds-dismiss-btn" style="
                    padding: 8px 16px;
                    background: #9333ea;
                    color: white;
                    border: none;
                    border-radius: 4px;
                    font-size: 13px;
                    cursor: pointer;
                ">
                    Dismiss
                </button>
            </div>
        </div>
        <!-- ===== END STATUS PANEL ===== -->
        
        <!-- Add SweetAlert2 CSS -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
        
        <style>
            .nds-settings-row {
                display: flex;
                flex-wrap: wrap;
                gap: 20px;
                margin-top: 20px;
                margin-bottom: 20px;
            }
            .nds-settings-card {
                background: #fff;
                border: 1px solid #dcdcde;
                box-shadow: 0 1px 1px rgba(0,0,0,0.04);
                padding: 16px;
                box-sizing: border-box;
                flex: 1 1 260px;
                min-width: 260px;
            }
            .nds-settings-card h2 {
                margin-top: 0;
            }
            .nds-settings-card-full {
                width: 100%;
            }
            /* NDS brand: primary purple */
            .nds-btn-brand {
                background: #9333ea !important;
                border-color: #9333ea !important;
                color: #fff !important;
            }
            .nds-btn-brand:hover {
                background: #7c3aed !important;
                border-color: #7c3aed !important;
                color: #fff !important;
            }
            .nds-import-export-card {
                border-left: 4px solid #9333ea;
            }
            @media (max-width: 782px) {
                .nds-settings-row {
                    display: block;
                }
                .nds-settings-card {
                    margin-bottom: 20px;
                }
                #nds-status-panel {
                    position: relative;
                    right: auto;
                    top: auto;
                    width: 100%;
                    margin: 20px 0;
                }
            }
        </style>

        <div class="nds-settings-row">
            <div class="nds-settings-card">
                <h2>Seed</h2>
                <p style="margin-bottom:12px;">Run sample data seed: All (LMS + Staff + Students), LMS only, Staff only, or Students only.</p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="seed-form">
                    <?php wp_nonce_field('nds_seed_nonce'); ?>
                    <input type="hidden" name="action" value="nds_seed" />
                    <label for="nds_seed_type" class="screen-reader-text">Seed type</label>
                    <select name="nds_seed_type" id="nds_seed_type" style="min-width:140px; margin-right:8px;">
                        <option value="all">All</option>
                        <option value="lms">LMS</option>
                        <option value="staff">Staff</option>
                        <option value="students">Students</option>
                    </select>
                    <button type="submit" class="button button-primary" onclick="return handleSeed(event)">Run seed</button>
                </form>
            </div>

            <div class="nds-settings-card">
                <h2>Danger Zone</h2>
                <p style="margin-bottom:8px;">Careful – these actions permanently remove data.</p>
                <?php
                global $wpdb;
                $nds_tables = $wpdb->get_col(
                    $wpdb->prepare(
                        'SHOW TABLES LIKE %s',
                        $wpdb->esc_like($wpdb->prefix . 'nds_') . '%'
                    )
                );
                ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="wipe-selected-form">
                    <?php wp_nonce_field('nds_wipe_selected_nds_tables_nonce'); ?>
                    <input type="hidden" name="action" value="nds_wipe_selected_nds_tables" />
                    <p><strong>Wipe Selected nds_ Tables</strong></p>
                    <p class="description">Tick specific nds_ tables to wipe instead of wiping everything.</p>
                    <p>
                        <label><input type="checkbox" id="nds-wipe-select-all" /> Select all</label>
                    </p>
                    <div style="max-height:200px; overflow:auto; border:1px solid #dcdcde; padding:8px; background:#fff;">
                        <?php if (!empty($nds_tables)) : ?>
                            <?php foreach ($nds_tables as $table_name) : ?>
                                <label style="display:block; margin-bottom:4px;">
                                    <input type="checkbox" class="nds-wipe-table-checkbox" name="nds_tables[]" value="<?php echo esc_attr($table_name); ?>" />
                                    <code><?php echo esc_html($table_name); ?></code>
                                </label>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <p class="description">No nds_ tables found.</p>
                        <?php endif; ?>
                    </div>
                    <p style="margin-top:8px;">
                        <button type="submit" class="button button-secondary" onclick="return handleWipeSelected(event)">Wipe Selected Tables</button>
                    </p>
                </form>
                <script>
                    (function() {
                        var selectAll = document.getElementById('nds-wipe-select-all');
                        if (!selectAll) return;
                        selectAll.addEventListener('change', function() {
                            var boxes = document.querySelectorAll('.nds-wipe-table-checkbox');
                            for (var i = 0; i < boxes.length; i++) {
                                boxes[i].checked = selectAll.checked;
                            }
                        });
                    })();
                </script>
            </div>

            <div class="nds-settings-card nds-import-export-card">
                <h2>Import / Export</h2>
                <p style="margin-bottom:12px;">Export database to CSV (ZIP) or import from <strong>NDS Database System.xlsx</strong>. If no file is selected, import uses the default <strong>assets/NDS Database System.xlsx</strong>. Columns named <code>*_ignore</code> are skipped on import.</p>
                <div style="display:flex; flex-wrap:wrap; gap:12px; align-items:flex-start;">
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="export-form" style="margin:0;">
                        <?php wp_nonce_field('nds_export_database_nonce'); ?>
                        <input type="hidden" name="action" value="nds_export_database" />
                        <button type="submit" class="button nds-btn-brand" onclick="return handleExport(event)">Export database (ZIP)</button>
                    </form>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="import-form" enctype="multipart/form-data" style="margin:0;">
                        <?php wp_nonce_field('nds_import_excel_nonce'); ?>
                        <input type="hidden" name="action" value="nds_import_excel" />
                        <label for="nds_import_xlsx" class="screen-reader-text">Select Excel file</label>
                        <input type="file" name="nds_import_xlsx" id="nds_import_xlsx" accept=".xlsx" style="margin-right:8px;" />
                        <button type="submit" class="button nds-btn-brand" onclick="return handleImport(event)">Import from Excel</button>
                    </form>
                </div>
                <p class="description" style="margin-top:10px;">Export creates CSVs + setup guide in a ZIP. Import respects FK order and skips <code>*_ignore</code> columns. If import fails, check the PHP error log (e.g. <code>wp-content/debug.log</code> when <code>WP_DEBUG_LOG</code> is on, or your server log); errors are prefixed with <code>[NDS Import]</code>.</p>
            </div>
        </div>

        <div class="nds-settings-card" style="margin-bottom:20px;">
            <h2>Access Control</h2>
            <p>Control subscriber access to the WordPress backend.</p>
            <?php
            if (isset($_POST['nds_save_access_settings']) && check_admin_referer('nds_access_settings_nonce')) {
                $block_subscribers = isset($_POST['nds_block_subscribers_backend']) ? '1' : '0';
                $hide_admin_bar = isset($_POST['nds_hide_subscriber_admin_bar']) ? '1' : '0';
                update_option('nds_block_subscribers_backend', $block_subscribers);
                update_option('nds_hide_subscriber_admin_bar', $hide_admin_bar);
            }
            $block_subscribers = get_option('nds_block_subscribers_backend', '1');
            $hide_admin_bar = get_option('nds_hide_subscriber_admin_bar', '0');
            ?>
            <form method="post" action="" id="access-form">
                <?php wp_nonce_field('nds_access_settings_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="nds_block_subscribers_backend">Block Subscribers from Backend</label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="nds_block_subscribers_backend" value="1" <?php checked($block_subscribers, '1'); ?> />
                                Redirect subscribers to the learner portal instead of allowing access to /wp-admin/
                            </label>
                            <p class="description">When enabled, users with the "subscriber" role will be redirected to <code>/portal/</code> when they try to access the WordPress admin area.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="nds_hide_subscriber_admin_bar">Hide Admin Bar for Subscribers</label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="nds_hide_subscriber_admin_bar" value="1" <?php checked($hide_admin_bar, '1'); ?> />
                                Hide the WordPress admin bar on the front-end for subscribers
                            </label>
                            <p class="description">This only takes effect when "Block Subscribers from Backend" is enabled.</p>
                        </td>
                    </tr>
                </table>
                <input type="hidden" name="nds_save_access_settings" value="1" />
                <?php submit_button('Save Access Control Settings'); ?>
            </form>
        </div>

        <div class="nds-settings-card nds-settings-card-full" style="margin-top:20px; margin-bottom:20px;">
            <h2>Available Shortcodes</h2>
            <p>Use these shortcodes in your pages and posts to display NDS Academy content.</p>
            <table class="widefat" style="margin-top: 15px;">
                <thead>
                    <tr>
                        <th style="width: 200px;">Shortcode</th>
                        <th>Description</th>
                        <th style="width: 300px;">Usage Example</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>[nds_login]</code></td>
                        <td><strong>Custom Login Page</strong><br>Displays a modern split-screen login form. Automatically redirects users based on role.</td>
                        <td><code>[nds_login]</code></td>
                    </tr>
                    <tr>
                        <td><code>[nds_recipes]</code></td>
                        <td><strong>Recipe Grid</strong><br>Displays a grid of recipes with customizable columns, layout, and display options.</td>
                        <td><code>[nds_recipes limit="12" columns="4"]</code></td>
                    </tr>
                    <tr>
                        <td><code>[nds_recipe_grid]</code></td>
                        <td><strong>Recipe Grid (Alias)</strong><br>Same as <code>[nds_recipes]</code> with grid layout preset.</td>
                        <td><code>[nds_recipe_grid limit="8"]</code></td>
                    </tr>
                    <tr>
                        <td><code>[nds_recipe_single]</code></td>
                        <td><strong>Single Recipe Display</strong><br>Displays a single recipe with full details including ingredients, steps, and images.</td>
                        <td><code>[nds_recipe_single id="5"]</code></td>
                    </tr>
                    <tr>
                        <td><code>[nds_recipe_carousel]</code></td>
                        <td><strong>Recipe Carousel</strong><br>Displays recipes in a carousel/slider format.</td>
                        <td><code>[nds_recipe_carousel limit="6"]</code></td>
                    </tr>
                    <tr>
                        <td><code>[nds_calendar]</code></td>
                        <td><strong>Academic Calendar</strong><br>Displays an interactive calendar showing course schedules, events, and academic dates.</td>
                        <td><code>[nds_calendar]</code></td>
                    </tr>
                </tbody>
            </table>
            <div style="margin-top: 20px; padding: 15px; background: #f0f6ff; border-left: 4px solid #2563eb; border-radius: 4px;">
                <h3 style="margin-top: 0; color: #2563eb;">💡 Tips</h3>
                <ul style="margin: 10px 0 0 20px; padding: 0;">
                    <li>Copy the shortcode and paste it into any WordPress page or post</li>
                    <li>Most shortcodes support additional attributes - check the usage examples above</li>
                    <li>Shortcodes work in page builders like Elementor, Gutenberg, and Classic Editor</li>
                </ul>
            </div>
        </div>

        <div class="card" style="padding:16px; max-width:800px; margin-top:20px; border-left: 4px solid #dc3232;">
            <h2 style="color: #dc3232;">⚠️ Wipe Core Tables</h2>
            <p><strong>DANGER:</strong> This will permanently delete ALL data from faculties, programs, courses, and staff tables. This action cannot be undone!</p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="wipe-core-form">
                <?php wp_nonce_field('nds_wipe_core_tables_nonce'); ?>
                <input type="hidden" name="action" value="nds_wipe_core_tables" />
                <button type="submit" class="button button-secondary" onclick="return handleWipeCore(event)" style="background-color: #dc3232; border-color: #dc3232; color: white;">Wipe All Core Tables</button>
            </form>
        </div>
    </div>
</div>

    <!-- Add SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script type="text/javascript">
    // Status Panel Functions
    var ndsMessages = <?php echo json_encode($js_messages); ?>;
    
    function showStatusPanel() {
        const panel = document.getElementById('nds-status-panel');
        panel.style.display = 'block';
        setTimeout(() => {
            panel.style.opacity = '1';
            panel.style.transform = 'translateX(0)';
        }, 10);
    }
    
    function hideStatusPanel() {
        const panel = document.getElementById('nds-status-panel');
        panel.style.opacity = '0';
        panel.style.transform = 'translateX(20px)';
        setTimeout(() => {
            panel.style.display = 'none';
        }, 300);
    }
    
    function showLoading(title, subtitle, message) {
        const icon = document.getElementById('nds-status-icon');
        const panel = document.getElementById('nds-status-panel');
        
        // Set loading state
        panel.style.borderLeftColor = '#9333ea';
        icon.innerHTML = '<div style="width: 20px; height: 20px; border: 2px solid #f3f4f6; border-top: 2px solid #9333ea; border-radius: 50%; animation: spin 1s linear infinite;"></div>';
        icon.style.background = '#f3f4f6';
        
        // Update text
        document.getElementById('nds-status-title').textContent = title;
        document.getElementById('nds-status-subtitle').textContent = subtitle;
        document.getElementById('nds-main-message').textContent = message;
        
        // Hide action buttons
        document.getElementById('nds-action-buttons').style.display = 'none';
        
        // Show panel
        showStatusPanel();
    }
    
    function showSuccess(title, message) {
        const icon = document.getElementById('nds-status-icon');
        const panel = document.getElementById('nds-status-panel');
        
        // Set success state
        panel.style.borderLeftColor = '#10b981';
        icon.innerHTML = '✅';
        icon.style.background = '#d1fae5';
        
        // Update text
        document.getElementById('nds-status-title').textContent = title;
        document.getElementById('nds-status-subtitle').textContent = 'Operation completed';
        document.getElementById('nds-main-message').textContent = message;
        
        // Show dismiss button
        document.getElementById('nds-action-buttons').style.display = 'flex';
        
        // Auto-hide after 5 seconds
        setTimeout(hideStatusPanel, 5000);
        
        // Show SweetAlert success message
        Swal.fire({
            icon: 'success',
            title: title,
            text: message,
            timer: 4000,
            timerProgressBar: true,
            showConfirmButton: false,
            position: 'top-end',
            toast: true,
            background: '#f0fdf4',
            color: '#065f46',
            iconColor: '#10b981'
        });
    }
    
    function showError(title, message) {
        const icon = document.getElementById('nds-status-icon');
        const panel = document.getElementById('nds-status-panel');
        
        // Set error state
        panel.style.borderLeftColor = '#ef4444';
        icon.innerHTML = '❌';
        icon.style.background = '#fee2e2';
        
        // Update text
        document.getElementById('nds-status-title').textContent = title;
        document.getElementById('nds-status-subtitle').textContent = 'Operation failed';
        document.getElementById('nds-main-message').textContent = message;
        
        // Show dismiss button
        document.getElementById('nds-action-buttons').style.display = 'flex';
        
        // Show SweetAlert error message
        Swal.fire({
            icon: 'error',
            title: title,
            text: message,
            timer: 5000,
            timerProgressBar: true,
            showConfirmButton: false,
            position: 'top-end',
            toast: true,
            background: '#fef2f2',
            color: '#991b1b',
            iconColor: '#ef4444'
        });
    }
    
    // Event Handlers
    function handleSeed(e) {
        e.preventDefault();
        const form = e.target.form;
        const type = document.getElementById('nds_seed_type').value;
        const typeText = type === 'all' ? 'All Data' : 
                       type === 'lms' ? 'LMS Data' :
                       type === 'staff' ? 'Staff Data' : 'Students Data';
        
        Swal.fire({
            title: `Run ${typeText} Seed?`,
            text: 'This will create sample data in your database.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#9333ea',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Yes, run seed',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                showLoading('Running Seed', `Creating ${typeText.toLowerCase()}`, 'Please wait while we create the sample data...');
                setTimeout(() => form.submit(), 500);
            }
        });
        return false;
    }
    
    function handleExport(e) {
        e.preventDefault();
        const form = e.target.form;
        
        Swal.fire({
            title: 'Export Database?',
            text: 'This will create a ZIP file with all your data.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#9333ea',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Yes, export',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                showLoading('Exporting', 'Creating backup files', 'Preparing database export...');
                setTimeout(() => form.submit(), 500);
            }
        });
        return false;
    }
    
    function handleImport(e) {
        e.preventDefault();
        const form = e.target.form;
        const fileInput = document.getElementById('nds_import_xlsx');
        const hasFile = fileInput && fileInput.files.length > 0;
        
        Swal.fire({
            title: 'Import from Excel?',
            html: hasFile 
                ? 'Import from selected Excel file? Existing rows may cause duplicate key errors.'
                : 'Import using default Excel file?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#9333ea',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Yes, import',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                showLoading('Importing', 'Reading Excel file', 'Processing spreadsheet data...');
                setTimeout(() => form.submit(), 500);
            }
        });
        return false;
    }
    
    function handleWipeSelected(e) {
        e.preventDefault();
        const form = e.target.form;
        const checkboxes = document.querySelectorAll('.nds-wipe-table-checkbox:checked');
        
        if (checkboxes.length === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'No Tables Selected',
                text: 'Please select at least one table to wipe.',
                toast: true,
                position: 'top-end',
                timer: 3000,
                showConfirmButton: false
            });
            return false;
        }
        
        Swal.fire({
            title: `Wipe ${checkboxes.length} Table(s)?`,
            html: `This will <strong>TRUNCATE ${checkboxes.length} selected table(s)</strong>.<br><br>This action <strong>CANNOT</strong> be undone!`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Yes, wipe selected',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                showLoading('Wiping Tables', `Truncating ${checkboxes.length} table(s)`, 'Removing data from selected tables...');
                setTimeout(() => form.submit(), 500);
            }
        });
        return false;
    }
    
    function handleWipeCore(e) {
        e.preventDefault();
        const form = e.target.form;
        
        Swal.fire({
            title: '⚠️ DANGER!',
            html: `This will <strong>DELETE ALL DATA</strong> from:<br>
                  • Faculties<br>
                  • Programs<br>
                  • Courses<br>
                  • Staff<br><br>
                  <strong style="color: #dc3232;">This action CANNOT be undone!</strong>`,
            icon: 'error',
            showCancelButton: true,
            confirmButtonColor: '#dc3232',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Yes, wipe everything',
            cancelButtonText: 'Cancel',
            backdrop: 'rgba(0,0,0,0.8)'
        }).then((result) => {
            if (result.isConfirmed) {
                showLoading('Wiping Core', 'Deleting all data', 'Removing all data from core tables...');
                setTimeout(() => form.submit(), 500);
            }
        });
        return false;
    }
    
    // Check for existing messages on page load
    document.addEventListener('DOMContentLoaded', function() {
        // Bind panel buttons
        document.getElementById('nds-close-panel').addEventListener('click', hideStatusPanel);
        document.getElementById('nds-dismiss-btn').addEventListener('click', hideStatusPanel);
        
        // Add CSS animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        `;
        document.head.appendChild(style);
        
        // Check for PHP messages and show them with SweetAlert
        setTimeout(function() {
            // Show success message for Import/Export if successful
            if (ndsMessages.import_export.status === 'success') {
                if (ndsMessages.import_export.message) {
                    showSuccess('Import/Export Successful', ndsMessages.import_export.message);
                } else {
                    showSuccess('Import/Export Successful', 'Operation completed successfully!');
                }
            } else if (ndsMessages.import_export.status === 'error' && ndsMessages.import_export.message) {
                showError('Import/Export Failed', ndsMessages.import_export.message);
            }
            
            // Show success message for Seed if successful
            if (ndsMessages.seed.status === 'success' && ndsMessages.seed.message) {
                showSuccess('Seed Completed', ndsMessages.seed.message);
            } else if (ndsMessages.seed.status === 'error' && ndsMessages.seed.message) {
                showError('Seed Failed', ndsMessages.seed.message);
            }
            
            // Show success message for Wipe Core if successful
            if (ndsMessages.wipe_core.status === 'success' && ndsMessages.wipe_core.message) {
                showSuccess('Tables Wiped', ndsMessages.wipe_core.message);
            } else if (ndsMessages.wipe_core.status === 'error' && ndsMessages.wipe_core.message) {
                showError('Wipe Failed', ndsMessages.wipe_core.message);
            }
            
            // Show success message for Wipe Selected if successful
            if (ndsMessages.wipe_selected.status === 'success' && ndsMessages.wipe_selected.message) {
                showSuccess('Tables Wiped', ndsMessages.wipe_selected.message);
            } else if (ndsMessages.wipe_selected.status === 'error' && ndsMessages.wipe_selected.message) {
                showError('Wipe Failed', ndsMessages.wipe_selected.message);
            }
            
            // If page was just loaded (no operations performed), show a welcome message
            const allStatuses = Object.values(ndsMessages).map(msg => msg.status);
            const hasAnyStatus = allStatuses.some(status => status !== null);
            
            if (!hasAnyStatus) {
                // Page just loaded, show a subtle welcome message
                Swal.fire({
                    icon: 'success',
                    title: 'Ready!',
                    text: 'NDS Academy Settings loaded successfully',
                    timer: 2000,
                    timerProgressBar: true,
                    showConfirmButton: false,
                    position: 'top-end',
                    toast: true,
                    background: '#f0fdf4',
                    color: '#065f46',
                    iconColor: '#10b981'
                });
            }
        }, 500);
    });
    </script>