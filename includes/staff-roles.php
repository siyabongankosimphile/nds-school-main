<?php
/**
 * Staff Roles Management
 * Manages staff roles using JSON file with automatic backup
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Global function: Convert string to Title Case (first letter capitalized, rest lowercase for each word)
 * Example: "aDmin" -> "Admin", "program coordinator" -> "Program Coordinator"
 * 
 * @param string $string The string to convert
 * @return string The string in Title Case
 */
function nds_senCase($string) {
    if (empty($string)) {
        return $string;
    }
    
    // Trim whitespace
    $string = trim($string);
    
    // Split by spaces to handle multiple words
    $words = explode(' ', $string);
    
    // Capitalize first letter and lowercase the rest for each word
    $words = array_map(function($word) {
        if (empty($word)) {
            return $word;
        }
        return ucfirst(strtolower($word));
    }, $words);
    
    // Join words back together
    return implode(' ', $words);
}

/**
 * Get the path to the roles JSON file
 */
function nds_get_roles_file_path() {
    // Get plugin directory (one level up from includes/)
    $plugin_dir = dirname(dirname(__FILE__));
    return trailingslashit($plugin_dir) . 'data/staff-roles.json';
}

/**
 * Get the path to the backup roles JSON file (hidden location)
 */
function nds_get_roles_backup_path() {
    // Store backup in a hidden directory
    $plugin_dir = dirname(dirname(__FILE__));
    $backup_dir = trailingslashit($plugin_dir) . '.backups/';
    if (!file_exists($backup_dir)) {
        wp_mkdir_p($backup_dir);
        // Create .htaccess to protect the directory
        $htaccess_file = trailingslashit($backup_dir) . '.htaccess';
        if (!file_exists($htaccess_file)) {
            file_put_contents($htaccess_file, "deny from all\n");
        }
    }
    return trailingslashit($backup_dir) . 'staff-roles.backup.json';
}

/**
 * Get all staff roles from JSON file
 * @return array List of role names
 */
function nds_get_staff_roles() {
    $roles_file = nds_get_roles_file_path();
    
    // Create data directory if it doesn't exist
    $data_dir = dirname($roles_file);
    if (!file_exists($data_dir)) {
        wp_mkdir_p($data_dir);
    }
    
    // If file doesn't exist, create it with default roles
    if (!file_exists($roles_file)) {
        $default_roles = ['Lecturer', 'Admin', 'Support', 'Program Coordinator', 'Head Chef'];
        nds_save_staff_roles($default_roles);
        return $default_roles;
    }
    
    $content = file_get_contents($roles_file);
    if ($content === false) {
        // Fallback to default roles if file read fails
        return ['Lecturer', 'Admin', 'Support', 'Program Coordinator', 'Head Chef'];
    }
    
    $data = json_decode($content, true);
    if (!is_array($data) || !isset($data['roles'])) {
        // Invalid JSON, return defaults
        return ['Lecturer', 'Admin', 'Support', 'Program Coordinator', 'Head Chef'];
    }
    
    // Ensure all roles are properly formatted (Title Case)
    $roles = array_map('nds_senCase', $data['roles']);
    
    return $roles;
}

/**
 * Save staff roles to JSON file and update backup
 * @param array $roles List of role names
 * @return bool Success status
 */
function nds_save_staff_roles($roles) {
    if (!is_array($roles)) {
        return false;
    }
    
    // Sanitize and apply Title Case to role names
    $roles = array_map(function($role) {
        $role = sanitize_text_field($role);
        return nds_senCase($role);
    }, $roles);
    
    $roles = array_filter($roles, function($role) {
        return !empty(trim($role));
    });
    $roles = array_values($roles); // Re-index array
    
    // Create data structure
    $data = [
        'roles' => $roles,
        'updated_at' => current_time('mysql'),
        'updated_by' => get_current_user_id()
    ];
    
    $roles_file = nds_get_roles_file_path();
    $backup_file = nds_get_roles_backup_path();
    
    // Create data directory if it doesn't exist
    $data_dir = dirname($roles_file);
    if (!file_exists($data_dir)) {
        wp_mkdir_p($data_dir);
        // Create .htaccess to protect the directory (optional, but good practice)
        $htaccess_file = trailingslashit($data_dir) . '.htaccess';
        if (!file_exists($htaccess_file)) {
            file_put_contents($htaccess_file, "deny from all\n");
        }
    }
    
    // Create backup directory if it doesn't exist
    $backup_dir = dirname($backup_file);
    if (!file_exists($backup_dir)) {
        wp_mkdir_p($backup_dir);
        // Create .htaccess to protect the directory
        $htaccess_file = trailingslashit($backup_dir) . '.htaccess';
        if (!file_exists($htaccess_file)) {
            file_put_contents($htaccess_file, "deny from all\n");
        }
    }
    
    // Backup existing file if it exists
    if (file_exists($roles_file)) {
        copy($roles_file, $backup_file);
    }
    
    // Write new data to main file
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $result = file_put_contents($roles_file, $json);
    
    if ($result === false) {
        return false;
    }
    
    // Update backup after successful write
    copy($roles_file, $backup_file);
    
    return true;
}

/**
 * Add a new role
 * @param string $role Role name
 * @return array Result with success status and message
 */
function nds_add_staff_role($role) {
    $role = sanitize_text_field($role);
    
    if (empty(trim($role))) {
        return ['success' => false, 'message' => 'Role name cannot be empty'];
    }
    
    // Apply Title Case formatting (first letter capitalized, rest lowercase for each word)
    $role = nds_senCase($role);
    
    $roles = nds_get_staff_roles();
    
    // Check if role already exists (case-insensitive)
    $role_lower = strtolower($role);
    foreach ($roles as $existing_role) {
        if (strtolower($existing_role) === $role_lower) {
            return ['success' => false, 'message' => 'Role already exists'];
        }
    }
    
    $roles[] = $role;
    $result = nds_save_staff_roles($roles);
    
    if ($result) {
        return ['success' => true, 'message' => 'Role added successfully'];
    }
    
    return ['success' => false, 'message' => 'Failed to save role'];
}

/**
 * Delete a role
 * @param string $role Role name
 * @return array Result with success status and message
 */
function nds_delete_staff_role($role) {
    $role = sanitize_text_field($role);
    
    $roles = nds_get_staff_roles();
    
    // Find and remove the role (case-insensitive)
    $role_lower = strtolower($role);
    $found = false;
    foreach ($roles as $key => $existing_role) {
        if (strtolower($existing_role) === $role_lower) {
            unset($roles[$key]);
            $found = true;
            break;
        }
    }
    
    if (!$found) {
        return ['success' => false, 'message' => 'Role not found'];
    }
    
    // Re-index array
    $roles = array_values($roles);
    
    $result = nds_save_staff_roles($roles);
    
    if ($result) {
        return ['success' => true, 'message' => 'Role deleted successfully'];
    }
    
    return ['success' => false, 'message' => 'Failed to save changes'];
}

/**
 * Restore roles from backup
 * @return array Result with success status and message
 */
function nds_restore_roles_from_backup() {
    $roles_file = nds_get_roles_file_path();
    $backup_file = nds_get_roles_backup_path();
    
    if (!file_exists($backup_file)) {
        return ['success' => false, 'message' => 'Backup file not found'];
    }
    
    $backup_content = file_get_contents($backup_file);
    if ($backup_content === false) {
        return ['success' => false, 'message' => 'Failed to read backup file'];
    }
    
    $result = file_put_contents($roles_file, $backup_content);
    
    if ($result === false) {
        return ['success' => false, 'message' => 'Failed to restore from backup'];
    }
    
    return ['success' => true, 'message' => 'Roles restored from backup successfully'];
}
