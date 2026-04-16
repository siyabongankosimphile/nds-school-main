<?php
/**
 * Staff Roles Management Component
 * Quick action component for managing staff roles
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Render the staff roles management component
 */
function nds_render_staff_roles_component() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    $roles = nds_get_staff_roles();
    ?>
    <div class="bg-white shadow-sm rounded-xl border border-gray-100">
        <div class="px-5 py-4 border-b border-gray-100">
            <h3 class="text-sm font-semibold text-gray-900">Manage Roles</h3>
            <p class="text-xs text-gray-500 mt-1">Add or remove staff roles</p>
        </div>
        <div class="p-5">
            <div id="nds-roles-management">
                <div class="flex gap-2 mb-4">
                    <input type="text" id="nds-new-role" placeholder="New role name" 
                           class="flex-1 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />
                    <button type="button" id="nds-add-role-btn" 
                            class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2 px-3 rounded-lg text-xs transition-colors duration-200 flex items-center gap-1">
                        <i class="fas fa-plus text-xs"></i>Add
                    </button>
                </div>
                
                <div id="nds-roles-list">
                    <h4 class="text-xs font-medium text-gray-700 mb-2">Current Roles:</h4>
                    <ul id="nds-roles-ul" class="space-y-1.5">
                        <?php foreach ($roles as $role): ?>
                            <li class="flex items-center justify-between p-2 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                                <span class="text-xs text-gray-900"><?php echo esc_html($role); ?></span>
                                <button type="button" class="text-red-500 hover:text-red-700 text-xs nds-delete-role" 
                                        data-role="<?php echo esc_attr($role); ?>" title="Delete role">
                                    <i class="fas fa-times"></i>
                                </button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                
                <div class="mt-4 pt-4 border-t border-gray-100">
                    <button type="button" id="nds-restore-backup-btn" 
                            class="w-full text-xs text-gray-600 hover:text-gray-800 py-2 px-3 rounded-lg border border-gray-300 hover:bg-gray-50 transition-colors">
                        <i class="fas fa-undo mr-1"></i>Restore from Backup
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // Add role
        $('#nds-add-role-btn').on('click', function() {
            var role = $('#nds-new-role').val().trim();
            if (!role) {
                alert('Please enter a role name');
                return;
            }
            
            var $btn = $(this);
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin text-xs"></i>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'nds_add_staff_role',
                    role: role,
                    nonce: '<?php echo wp_create_nonce('nds_manage_roles'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data || 'Failed to add role');
                        $btn.prop('disabled', false).html('<i class="fas fa-plus text-xs"></i>Add');
                    }
                },
                error: function() {
                    alert('An error occurred. Please try again.');
                    $btn.prop('disabled', false).html('<i class="fas fa-plus text-xs"></i>Add');
                }
            });
        });
        
        // Delete role
        $(document).on('click', '.nds-delete-role', function() {
            if (!confirm('Are you sure you want to delete this role? Staff members with this role will need to be updated.')) {
                return;
            }
            
            var role = $(this).data('role');
            var $li = $(this).closest('li');
            var $btn = $(this);
            
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'nds_delete_staff_role',
                    role: role,
                    nonce: '<?php echo wp_create_nonce('nds_manage_roles'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        $li.fadeOut(300, function() {
                            $(this).remove();
                            if ($('#nds-roles-ul li').length === 0) {
                                $('#nds-roles-ul').append('<li class="text-sm text-gray-500 p-2">No roles found</li>');
                            }
                        });
                    } else {
                        alert(response.data || 'Failed to delete role');
                        $btn.prop('disabled', false).html('<i class="fas fa-trash-alt"></i>');
                    }
                },
                error: function() {
                    alert('An error occurred. Please try again.');
                    $btn.prop('disabled', false).html('<i class="fas fa-trash-alt"></i>');
                }
            });
        });
        
        // Restore from backup
        $('#nds-restore-backup-btn').on('click', function() {
            if (!confirm('This will restore roles from the backup file. Current roles will be replaced. Continue?')) {
                return;
            }
            
            var $btn = $(this);
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Restoring...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'nds_restore_roles_backup',
                    nonce: '<?php echo wp_create_nonce('nds_manage_roles'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        alert('Roles restored from backup successfully');
                        location.reload();
                    } else {
                        alert(response.data || 'Failed to restore from backup');
                        $btn.prop('disabled', false).html('<i class="fas fa-undo mr-1"></i>Restore from Backup');
                    }
                },
                error: function() {
                    alert('An error occurred. Please try again.');
                    $btn.prop('disabled', false).html('<i class="fas fa-undo mr-1"></i>Restore from Backup');
                }
            });
        });
        
        // Allow Enter key to add role
        $('#nds-new-role').on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                $('#nds-add-role-btn').click();
            }
        });
    });
    </script>
    <?php
}
