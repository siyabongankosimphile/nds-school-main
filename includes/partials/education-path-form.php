<?php
if (!defined('ABSPATH')) { exit; }

if (!function_exists('nds_render_education_path_form')) {
    /**
     * Render the Faculty form/view card (formerly Education Path).
     * Args: [ 'mode' => 'add'|'edit-path'|'edit'|'view', 'path_data' => array|null, 'edit_id' => int|null ]
     */
    function nds_render_education_path_form($args = array())
    {
        $mode = isset($args['mode']) ? $args['mode'] : 'add';
        $path_data = isset($args['path_data']) ? $args['path_data'] : null;
        $edit_id = isset($args['edit_id']) ? intval($args['edit_id']) : 0;
        $is_view = ($mode === 'view' || $mode === 'edit'); // 'edit' screen shows programs; left pane is view-only
        $edit_url = $edit_id ? admin_url('admin.php?page=nds-faculties&action=edit-path&edit=' . $edit_id) : admin_url('admin.php?page=nds-faculties');
        ?>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8">
            <?php if ($is_view): ?>
                <div class="space-y-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Path Name</label>
                        <div class="w-full px-3 py-2 border border-gray-200 rounded-lg bg-gray-50 text-gray-800"><?php echo $path_data ? esc_html($path_data['name']) : '—'; ?></div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                        <div class="w-full px-3 py-2 border border-gray-200 rounded-lg bg-gray-50 text-gray-700 min-h-[48px]"><?php echo $path_data ? nl2br(esc_html($path_data['description'])) : '—'; ?></div>
                    </div>
                    <div class="flex justify-end gap-3 pt-6 border-t border-gray-200">
                        <a href="<?php echo esc_url($edit_url); ?>" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-lg flex items-center gap-2 transition-colors duration-200 shadow-md hover:shadow-lg">
                            <i class="fas fa-edit"></i>
                            Edit
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <form method="POST" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php if ($mode === 'edit-path'): ?>
                        <?php wp_nonce_field('nds_update_program_nonce', 'nds_update_program_nonce'); ?>
                        <input type="hidden" name="action" value="nds_update_education_path">
                        <input type="hidden" name="edit_id" value="<?php echo esc_attr($edit_id); ?>">
                    <?php else: ?>
                        <?php wp_nonce_field('nds_add_education_path_nonce', 'nds_nonce'); ?>
                        <input type="hidden" name="action" value="nds_add_education_path">
                    <?php endif; ?>

                    <?php
                    // Get color for faculty (existing or default)
                    require_once plugin_dir_path(__FILE__) . '../color-palette-generator.php';
                    $color_generator = new NDS_ColorPaletteGenerator();
                    global $wpdb;
                    $faculties_table = $wpdb->prefix . 'nds_faculties';
                    
                    $current_color = '';
                    if ($path_data && isset($path_data['color_primary']) && !empty($path_data['color_primary'])) {
                        $current_color = $path_data['color_primary'];
                    } else {
                        $faculty_count = $wpdb->get_var("SELECT COUNT(*) FROM $faculties_table");
                        $current_color = $color_generator->get_default_faculty_color($faculty_count);
                    }
                    ?>
                    <div class="space-y-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Path Name *</label>
                            <input type="text" name="path_name" value="<?php echo $path_data ? esc_attr($path_data['name']) : ''; ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                            <textarea name="path_description" rows="4"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                placeholder="Describe this faculty..."><?php echo $path_data ? esc_textarea($path_data['description']) : ''; ?></textarea>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Parent Color *</label>
                            <p class="text-sm text-gray-500 mb-3">Choose a color for this faculty. Programs within this faculty will automatically use shades of this color.</p>
                            <div class="flex items-center gap-3">
                                <div class="relative">
                                    <input type="color" name="color_primary" id="form_color_primary" value="<?php echo esc_attr($current_color); ?>"
                                        class="h-14 w-24 border-2 border-gray-300 rounded-lg cursor-pointer focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-sm">
                                    <div class="absolute -bottom-1 -right-1 w-6 h-6 rounded-full border-2 border-white shadow-sm" style="background-color: <?php echo esc_attr($current_color); ?>;"></div>
                                </div>
                                <div class="flex-1">
                                    <input type="text" id="form_color_primary_text" value="<?php echo esc_attr($current_color); ?>"
                                        class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent font-mono text-sm"
                                        placeholder="#E53935" pattern="^#[0-9A-Fa-f]{6}$">
                                    <p class="text-xs text-gray-400 mt-1">Hex color code</p>
                                </div>
                            </div>
                            <div class="mt-3 p-3 bg-gray-50 rounded-lg border border-gray-200">
                                <p class="text-xs text-gray-600 mb-2"><strong>Selected Color:</strong></p>
                                <div class="flex items-center gap-2">
                                    <div class="w-12 h-12 rounded-lg shadow-sm border border-gray-300" id="form_color_preview" style="background-color: <?php echo esc_attr($current_color); ?>;"></div>
                                    <div>
                                        <p class="text-sm font-semibold text-gray-900" id="form_color_display"><?php echo esc_html($current_color); ?></p>
                                        <p class="text-xs text-gray-500">This color will be used as the base for all programs</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            const formColorPicker = document.getElementById('form_color_primary');
                            const formColorText = document.getElementById('form_color_primary_text');
                            const formColorPreview = document.getElementById('form_color_preview');
                            const formColorDisplay = document.getElementById('form_color_display');
                            
                            if (formColorPicker && formColorText && formColorPreview && formColorDisplay) {
                                formColorPicker.addEventListener('input', function(e) {
                                    const color = e.target.value.toUpperCase();
                                    formColorText.value = color;
                                    formColorPreview.style.backgroundColor = color;
                                    formColorDisplay.textContent = color;
                                });
                                
                                formColorText.addEventListener('input', function(e) {
                                    const value = e.target.value;
                                    if (/^#[0-9A-Fa-f]{6}$/.test(value)) {
                                        formColorPicker.value = value;
                                        formColorPreview.style.backgroundColor = value;
                                        formColorDisplay.textContent = value.toUpperCase();
                                    }
                                });
                                
                                formColorText.addEventListener('blur', function(e) {
                                    const value = e.target.value;
                                    if (!/^#[0-9A-Fa-f]{6}$/.test(value) && value.trim() !== '') {
                                        formColorText.value = formColorPicker.value.toUpperCase();
                                    }
                                });
                            }
                        });
                        </script>

                        <div class="flex justify-end gap-3 pt-6 border-t border-gray-200">
                            <a href="<?php echo esc_url(admin_url('admin.php?page=nds-faculties')); ?>"
                                class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-2 px-6 rounded-lg transition-colors duration-200">Cancel</a>
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-lg flex items-center gap-2 transition-colors duration-200 shadow-md hover:shadow-lg">
                                <i class="fas fa-save"></i>
                                <?php echo ($mode === 'edit-path') ? 'Update Path' : 'Add Path'; ?>
                            </button>
                        </div>
                    </div>
                </form>
            <?php endif; ?>
        </div>
        <?php
    }
}


