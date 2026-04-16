<?php
/**
 * Partial for rendering module management fields
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render module fields for course edit form
 * 
 * @param array $args Arguments including existing modules
 */
function nds_render_module_fields($args = []) {
    $modules = $args['modules'] ?? [];
    $prefix = $args['prefix'] ?? 'modules';
    
    // Ensure at least one empty row if no modules exist
    if (empty($modules)) {
        // Option 1: Start with one empty row
        // $modules[] = (object) ['id' => '', 'code' => '', 'name' => '', 'type' => 'theory', 'duration_hours' => ''];
        // Option 2: Start empty and let user add
    }
    ?>
    <div class="module-fields-container mb-8">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900">
                Qualification Modules
            </h3>
            <button type="button" onclick="ndsAddModuleRow()" 
                    class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                <i class="fas fa-plus mr-2"></i>
                Add Module
            </button>
        </div>
        
        <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200" id="modules-table">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/6">Code</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/3">Module Name</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/6">Type</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/6">Duration (Hrs)</th>
                        <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider w-1/12">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200" id="module-rows">
                    <?php if (empty($modules)): ?>
                        <tr id="no-modules-row">
                            <td colspan="5" class="px-4 py-8 text-center text-gray-500 text-sm">
                                No modules added yet. Click "Add Module" to start.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($modules as $index => $module): ?>
                            <tr class="module-row">
                                <td class="px-4 py-3">
                                    <input type="hidden" name="<?php echo $prefix; ?>[<?php echo $index; ?>][id]" value="<?php echo esc_attr($module->id); ?>">
                                    <input type="text" name="<?php echo $prefix; ?>[<?php echo $index; ?>][code]" value="<?php echo esc_attr($module->code); ?>" 
                                           class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" 
                                           placeholder="e.g. MOD101" required>
                                </td>
                                <td class="px-4 py-3">
                                    <input type="text" name="<?php echo $prefix; ?>[<?php echo $index; ?>][name]" value="<?php echo esc_attr($module->name); ?>" 
                                           class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" 
                                           placeholder="Module Name" required>
                                </td>
                                <td class="px-4 py-3">
                                    <select name="<?php echo $prefix; ?>[<?php echo $index; ?>][type]" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                        <option value="theory" <?php echo isset($module->type) && $module->type === 'theory' ? 'selected' : ''; ?>>Theory</option>
                                        <option value="practical" <?php echo isset($module->type) && $module->type === 'practical' ? 'selected' : ''; ?>>Practical</option>
                                        <option value="workplace" <?php echo isset($module->type) && $module->type === 'workplace' ? 'selected' : ''; ?>>Workplace</option>
                                        <option value="assessment" <?php echo isset($module->type) && $module->type === 'assessment' ? 'selected' : ''; ?>>Assessment</option>
                                    </select>
                                </td>
                                <td class="px-4 py-3">
                                    <input type="number" name="<?php echo $prefix; ?>[<?php echo $index; ?>][duration_hours]" value="<?php echo esc_attr($module->duration_hours ?? ''); ?>" 
                                           class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" 
                                           min="0" step="1">
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <button type="button" onclick="ndsRemoveModuleRow(this)" class="text-red-600 hover:text-red-900">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <p class="mt-2 text-sm text-gray-500">Add modules that make up this qualification. You can associate schedule items with these modules.</p>
    </div>

    <script>
    function ndsAddModuleRow() {
        const container = document.getElementById('module-rows');
        const noModulesRow = document.getElementById('no-modules-row');
        if (noModulesRow) {
            noModulesRow.remove();
        }
        
        const timestamp = new Date().getTime(); // Unique ID for index
        const row = document.createElement('tr');
        row.className = 'module-row';
        row.innerHTML = `
            <td class="px-4 py-3">
                <input type="hidden" name="<?php echo $prefix; ?>[new_${timestamp}][id]" value="">
                <input type="text" name="<?php echo $prefix; ?>[new_${timestamp}][code]" 
                       class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" 
                       placeholder="Code" required>
            </td>
            <td class="px-4 py-3">
                <input type="text" name="<?php echo $prefix; ?>[new_${timestamp}][name]" 
                       class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" 
                       placeholder="Module Name" required>
            </td>
            <td class="px-4 py-3">
                <select name="<?php echo $prefix; ?>[new_${timestamp}][type]" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                    <option value="theory">Theory</option>
                    <option value="practical">Practical</option>
                    <option value="workplace">Workplace</option>
                    <option value="assessment">Assessment</option>
                </select>
            </td>
            <td class="px-4 py-3">
                <input type="number" name="<?php echo $prefix; ?>[new_${timestamp}][duration_hours]" 
                       class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" 
                       min="0" step="1">
            </td>
            <td class="px-4 py-3 text-right">
                <button type="button" onclick="ndsRemoveModuleRow(this)" class="text-red-600 hover:text-red-900">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        `;
        container.appendChild(row);
    }

    function ndsRemoveModuleRow(button) {
        if (confirm('Are you sure you want to remove this module?')) {
            const row = button.closest('tr');
            row.remove();
            
            const container = document.getElementById('module-rows');
            if (container.children.length === 0) {
                container.innerHTML = `
                    <tr id="no-modules-row">
                        <td colspan="5" class="px-4 py-8 text-center text-gray-500 text-sm">
                            No modules added yet. Click "Add Module" to start.
                        </td>
                    </tr>
                `;
            }
        }
    }
    </script>
    <?php
}
