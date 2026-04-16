<?php
/**
 * NDS Hero Carousel Admin Interface
 * Admin pages and functionality for managing carousel slides
 */

if (!defined('ABSPATH')) {
    exit; // Prevent direct access
}

class NDS_Hero_Carousel_Admin {
    
    private $carousel;
    
    public function __construct() {
        $this->carousel = new NDS_Hero_Carousel();
        add_action('admin_menu', array($this, 'add_admin_menu'), 120);
        add_action('admin_post_nds_save_carousel_slide', array($this, 'save_carousel_slide'));
        add_action('admin_post_nds_delete_carousel_slide', array($this, 'delete_carousel_slide'));
        add_action('admin_post_nds_reorder_carousel_slides', array($this, 'reorder_carousel_slides'));
        add_action('wp_ajax_nds_toggle_carousel_slide', array($this, 'toggle_carousel_slide'));
        add_action('wp_ajax_nds_reorder_carousel_slides', array($this, 'reorder_carousel_slides_ajax'));
    }
    
    /**
     * Add admin menu for carousel management
     */
    public function add_admin_menu() {
        // Top-level menu for Hero Carousel
        add_menu_page(
            'NDS Hero Carousel', // Page title
            'Hero Carousel', // Menu title
            'manage_options', // Capability
            'nds-hero-carousel', // Menu slug
            array($this, 'carousel_management_page'), // Callback
            'dashicons-images-alt2', // Icon
            7 // Position (near top)
        );

        // Submenu: All Slides (points to same slug to replace top-level content)
        add_submenu_page(
            'nds-hero-carousel',
            'All Slides',
            'All Slides',
            'manage_options',
            'nds-hero-carousel',
            array($this, 'carousel_management_page')
        );

        // Submenu: Add New Slide
        add_submenu_page(
            'nds-hero-carousel',
            'Add Carousel Slide',
            'Add New',
            'manage_options',
            'nds-add-carousel-slide',
            array($this, 'add_carousel_slide_page')
        );

        // Hidden edit page
        add_submenu_page(
            'nds-hero-carousel',
            'Edit Carousel Slide',
            '',
            'manage_options',
            'nds-edit-carousel-slide',
            array($this, 'edit_carousel_slide_page')
        );
    }
    
    /**
     * Carousel management page
     */
    public function carousel_management_page() {
        $slides = $this->carousel->get_all_slides();
        $active_count = count(array_filter($slides, function($slide) {
            return $slide->is_active == 1;
        }));
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Hero Carousel Management</h1>
            <a href="<?php echo admin_url('admin.php?page=nds-add-carousel-slide'); ?>" class="page-title-action">Add New Slide</a>
            <hr class="wp-header-end">
            
            <!-- Carousel Preview -->
            <div class="nds-carousel-preview">
                <h2>Carousel Preview</h2>
                <div class="nds-preview-container">
                    <?php echo do_shortcode('[nds_hero_carousel autoplay="false" height="300px"]'); ?>
                </div>
            </div>
            
            <!-- Statistics -->
            <div class="nds-carousel-stats">
                <div class="nds-stat-box">
                    <h3><?php echo count($slides); ?></h3>
                    <p>Total Slides</p>
                </div>
                <div class="nds-stat-box">
                    <h3><?php echo $active_count; ?></h3>
                    <p>Active Slides</p>
                </div>
                <div class="nds-stat-box">
                    <h3><?php echo count($slides) - $active_count; ?></h3>
                    <p>Inactive Slides</p>
                </div>
            </div>
            
            <!-- Slides List (custom, no WP list-table) -->
            <div class="nds-carousel-table">
                <div class="nds-slides-header">
                    <label class="nds-cell nds-cb">
                        <input type="checkbox" id="cb-select-all">
                    </label>
                    <div class="nds-cell nds-image">Image</div>
                    <div class="nds-cell nds-title">Title</div>
                    <div class="nds-cell nds-subtitle">Subtitle</div>
                    <div class="nds-cell nds-order">Order</div>
                    <div class="nds-cell nds-status">Status</div>
                    <div class="nds-cell nds-actions">Actions</div>
                </div>
                <div class="nds-slides-body">
                    <?php if (empty($slides)): ?>
                        <div class="nds-empty-row">No carousel slides found. <a href="<?php echo admin_url('admin.php?page=nds-add-carousel-slide'); ?>">Add your first slide</a>.</div>
                    <?php else: ?>
                        <?php foreach ($slides as $slide): ?>
                            <div class="nds-slide-row" data-slide-id="<?php echo esc_attr($slide->id); ?>">
                                <label class="nds-cell nds-cb">
                                    <input type="checkbox" name="slide_ids[]" value="<?php echo esc_attr($slide->id); ?>">
                                </label>
                                <div class="nds-cell nds-image">
                                    <?php
                                    $image_url = '';
                                    if (!empty($slide->image_id)) {
                                        $image_url = wp_get_attachment_image_url($slide->image_id, 'thumbnail');
                                    } elseif (!empty($slide->image_url)) {
                                        $image_url = $slide->image_url;
                                    }
                                    if ($image_url):
                                    ?>
                                        <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($slide->title); ?>" style="width: 60px; height: 40px; object-fit: cover; border-radius: 4px;">
                                    <?php else: ?>
                                        <div class="nds-no-image">No Image</div>
                                    <?php endif; ?>
                                </div>
                                <div class="nds-cell nds-title"><strong><?php echo esc_html($slide->title); ?></strong></div>
                                <div class="nds-cell nds-subtitle"><?php echo esc_html($slide->subtitle); ?></div>
                                <div class="nds-cell nds-order">
                                    <input type="number" name="slide_order[<?php echo esc_attr($slide->id); ?>]" value="<?php echo esc_attr($slide->slide_order); ?>" class="small-text" min="0" max="999">
                                </div>
                                <div class="nds-cell nds-status">
                                    <label class="nds-toggle">
                                        <input type="checkbox" class="slide-status-toggle" data-slide-id="<?php echo esc_attr($slide->id); ?>" <?php checked($slide->is_active, 1); ?>>
                                        <span class="nds-toggle-slider"></span>
                                    </label>
                                </div>
                                <div class="nds-cell nds-actions">
                                    <a href="<?php echo admin_url('admin.php?page=nds-edit-carousel-slide&slide_id=' . $slide->id); ?>" class="button button-small">Edit</a>
                                    <a href="<?php echo admin_url('admin-post.php?action=nds_delete_carousel_slide&slide_id=' . $slide->id . '&_wpnonce=' . wp_create_nonce('nds_delete_carousel_slide')); ?>" class="button button-small button-link-delete" onclick="return confirm('Are you sure you want to delete this slide?');">Delete</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Bulk Actions -->
            <div class="nds-bulk-actions">
                <select name="bulk_action" id="bulk-action-selector">
                    <option value="">Bulk Actions</option>
                    <option value="activate">Activate</option>
                    <option value="deactivate">Deactivate</option>
                    <option value="delete">Delete</option>
                </select>
                <button type="button" class="button" id="bulk-action-apply">Apply</button>
                <button type="button" class="button" id="save-order">Save Order</button>
            </div>
            
            <!-- Shortcode Usage -->
            <div class="nds-shortcode-usage">
                <h3>Shortcode Usage</h3>
                <p>Use the following shortcode to display the carousel on any page or post:</p>
                <code>[nds_hero_carousel]</code>
                
                <h4>Available Parameters:</h4>
                <ul>
                    <li><code>autoplay</code> - Enable/disable autoplay (true/false, default: true)</li>
                    <li><code>autoplay_delay</code> - Autoplay delay in milliseconds (default: 5000)</li>
                    <li><code>loop</code> - Enable/disable loop (true/false, default: true)</li>
                    <li><code>pagination</code> - Show/hide pagination dots (true/false, default: true)</li>
                    <li><code>navigation</code> - Show/hide navigation arrows (true/false, default: true)</li>
                    <li><code>effect</code> - Carousel effect (slide/fade/cube/coverflow, default: slide)</li>
                    <li><code>height</code> - Carousel height (default: 100vh)</li>
                    <li><code>show_overlay</code> - Show/hide overlay (true/false, default: true)</li>
                    <li><code>overlay_opacity</code> - Overlay opacity (0-1, default: 0.4)</li>
                </ul>
                
                <h4>Example:</h4>
                <code>[nds_hero_carousel autoplay="true" height="600px" effect="fade" pagination="true"]</code>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Toggle slide status
            $('.slide-status-toggle').on('change', function() {
                const slideId = $(this).data('slide-id');
                const isActive = $(this).is(':checked') ? 1 : 0;
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'nds_toggle_carousel_slide',
                        slide_id: slideId,
                        is_active: isActive,
                        nonce: '<?php echo wp_create_nonce('nds_toggle_carousel_slide'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            // Show success message
                            $('<div class="notice notice-success is-dismissible"><p>Slide status updated successfully.</p></div>')
                                .insertAfter('.wp-header-end')
                                .delay(3000)
                                .fadeOut();
                        } else {
                            alert('Error updating slide status: ' + response.data);
                        }
                    },
                    error: function() {
                        alert('Error updating slide status. Please try again.');
                    }
                });
            });
            
            // Save order
            $('#save-order').on('click', function() {
                const orders = {};
                $('input[name^="slide_order"]').each(function() {
                    const slideId = $(this).attr('name').match(/\[(\d+)\]/)[1];
                    orders[slideId] = $(this).val();
                });
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'nds_reorder_carousel_slides',
                        orders: orders,
                        nonce: '<?php echo wp_create_nonce('nds_reorder_carousel_slides'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $('<div class="notice notice-success is-dismissible"><p>Slide order updated successfully.</p></div>')
                                .insertAfter('.wp-header-end')
                                .delay(3000)
                                .fadeOut();
                        } else {
                            alert('Error updating slide order: ' + response.data);
                        }
                    },
                    error: function() {
                        alert('Error updating slide order. Please try again.');
                    }
                });
            });
            
            // Bulk actions
            $('#bulk-action-apply').on('click', function() {
                const action = $('#bulk-action-selector').val();
                const selectedSlides = $('input[name="slide_ids[]"]:checked').map(function() {
                    return $(this).val();
                }).get();
                
                if (!action || selectedSlides.length === 0) {
                    alert('Please select an action and at least one slide.');
                    return;
                }
                
                if (action === 'delete' && !confirm('Are you sure you want to delete the selected slides?')) {
                    return;
                }
                
                // Implement bulk actions here
                console.log('Bulk action:', action, 'on slides:', selectedSlides);
            });
        });
        </script>
        <?php
    }
    
    /**
     * Add carousel slide page
     */
    public function add_carousel_slide_page() {
        $this->render_slide_form();
    }
    
    /**
     * Edit carousel slide page
     */
    public function edit_carousel_slide_page() {
        $slide_id = isset($_GET['slide_id']) ? intval($_GET['slide_id']) : 0;
        
        if (!$slide_id) {
            wp_die('Invalid slide ID');
        }
        
        $slide = $this->carousel->get_slide($slide_id);
        
        if (!$slide) {
            wp_die('Slide not found');
        }
        
        $this->render_slide_form($slide);
    }
    
    /**
     * Render slide form
     */
    private function render_slide_form($slide = null) {
        $is_edit = !is_null($slide);
        $slide_id = $is_edit ? $slide->id : 0;
        
        ?>
        <div class="wrap">
            <h1><?php echo $is_edit ? 'Edit Carousel Slide' : 'Add New Carousel Slide'; ?></h1>
            
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" enctype="multipart/form-data">
                <input type="hidden" name="action" value="nds_save_carousel_slide">
                <input type="hidden" name="slide_id" value="<?php echo esc_attr($slide_id); ?>">
                <?php wp_nonce_field('nds_save_carousel_slide', 'nds_save_carousel_slide_nonce'); ?>
                
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="slide_title">Title *</label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="slide_title" 
                                       name="slide_title" 
                                       value="<?php echo $is_edit ? esc_attr($slide->title) : ''; ?>" 
                                       class="regular-text" 
                                       required>
                                <p class="description">The main title displayed on the slide.</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="slide_subtitle">Subtitle</label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="slide_subtitle" 
                                       name="slide_subtitle" 
                                       value="<?php echo $is_edit ? esc_attr($slide->subtitle) : ''; ?>" 
                                       class="regular-text">
                                <p class="description">Optional subtitle displayed below the title.</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="slide_description">Description</label>
                            </th>
                            <td>
                                <textarea id="slide_description" 
                                          name="slide_description" 
                                          rows="4" 
                                          class="large-text"><?php echo $is_edit ? esc_textarea($slide->description) : ''; ?></textarea>
                                <p class="description">Optional description text displayed on the slide.</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="slide_image">Image</label>
                            </th>
                            <td>
                                <div class="nds-image-upload">
                                    <input type="hidden" 
                                           id="slide_image_id" 
                                           name="slide_image_id" 
                                           value="<?php echo $is_edit ? esc_attr($slide->image_id) : ''; ?>">
                                    <input type="url" 
                                           id="slide_image_url" 
                                           name="slide_image_url" 
                                           value="<?php echo $is_edit ? esc_attr($slide->image_url) : ''; ?>" 
                                           class="regular-text" 
                                           placeholder="Or enter image URL">
                                    
                                    <div class="nds-image-preview" id="image_preview">
                                        <?php if ($is_edit && ($slide->image_id || $slide->image_url)): ?>
                                            <?php
                                            $image_url = '';
                                            if ($slide->image_id) {
                                                $image_url = wp_get_attachment_image_url($slide->image_id, 'medium');
                                            } elseif ($slide->image_url) {
                                                $image_url = $slide->image_url;
                                            }
                                            ?>
                                            <img src="<?php echo esc_url($image_url); ?>" alt="Preview" style="max-width: 300px; max-height: 200px;">
                                        <?php endif; ?>
                                    </div>
                                    
                                    <button type="button" 
                                            class="button" 
                                            id="upload_image_button">Select Image</button>
                                    <button type="button" 
                                            class="button" 
                                            id="remove_image_button" 
                                            style="<?php echo $is_edit && ($slide->image_id || $slide->image_url) ? '' : 'display: none;'; ?>">Remove Image</button>
                                </div>
                                <p class="description">Upload an image or enter an image URL. Recommended size: 1920x1080px.</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="slide_button_text">Button Text</label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="slide_button_text" 
                                       name="slide_button_text" 
                                       value="<?php echo $is_edit ? esc_attr($slide->button_text) : ''; ?>" 
                                       class="regular-text">
                                <p class="description">Text for the call-to-action button.</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="slide_button_url">Button URL</label>
                            </th>
                            <td>
                                <input type="url" 
                                       id="slide_button_url" 
                                       name="slide_button_url" 
                                       value="<?php echo $is_edit ? esc_attr($slide->button_url) : ''; ?>" 
                                       class="regular-text">
                                <p class="description">URL for the call-to-action button.</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="slide_button_target">Button Target</label>
                            </th>
                            <td>
                                <select id="slide_button_target" name="slide_button_target">
                                    <option value="_self" <?php selected($is_edit ? $slide->button_target : '_self', '_self'); ?>>Same Window</option>
                                    <option value="_blank" <?php selected($is_edit ? $slide->button_target : '_self', '_blank'); ?>>New Window</option>
                                </select>
                                <p class="description">How the button link should open.</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="slide_order">Slide Order</label>
                            </th>
                            <td>
                                <input type="number" 
                                       id="slide_order" 
                                       name="slide_order" 
                                       value="<?php echo $is_edit ? esc_attr($slide->slide_order) : '0'; ?>" 
                                       class="small-text" 
                                       min="0" 
                                       max="999">
                                <p class="description">Order of the slide (lower numbers appear first).</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="slide_status">Status</label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" 
                                           id="slide_status" 
                                           name="slide_status" 
                                           value="1" 
                                           <?php checked($is_edit ? $slide->is_active : 1, 1); ?>>
                                    Active
                                </label>
                                <p class="description">Only active slides will be displayed in the carousel.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <p class="submit">
                    <input type="submit" 
                           class="button button-primary" 
                           value="<?php echo $is_edit ? 'Update Slide' : 'Add Slide'; ?>">
                    <a href="<?php echo admin_url('admin.php?page=nds-hero-carousel'); ?>" 
                       class="button">Cancel</a>
                </p>
            </form>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Image upload
            $('#upload_image_button').on('click', function(e) {
                e.preventDefault();
                
                const frame = wp.media({
                    title: 'Select Image',
                    button: {
                        text: 'Use Image'
                    },
                    multiple: false
                });
                
                frame.on('select', function() {
                    const attachment = frame.state().get('selection').first().toJSON();
                    $('#slide_image_id').val(attachment.id);
                    $('#slide_image_url').val('');
                    $('#image_preview').html('<img src="' + attachment.sizes.medium.url + '" alt="Preview" style="max-width: 300px; max-height: 200px;">');
                    $('#remove_image_button').show();
                });
                
                frame.open();
            });
            
            // Remove image
            $('#remove_image_button').on('click', function() {
                $('#slide_image_id').val('');
                $('#slide_image_url').val('');
                $('#image_preview').html('');
                $(this).hide();
            });
            
            // URL input change
            $('#slide_image_url').on('input', function() {
                const url = $(this).val();
                if (url) {
                    $('#slide_image_id').val('');
                    $('#image_preview').html('<img src="' + url + '" alt="Preview" style="max-width: 300px; max-height: 200px;">');
                    $('#remove_image_button').show();
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Save carousel slide
     */
    public function save_carousel_slide() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        if (!wp_verify_nonce($_POST['nds_save_carousel_slide_nonce'], 'nds_save_carousel_slide')) {
            wp_die('Security check failed');
        }
        
        $slide_id = intval($_POST['slide_id']);
        $data = array(
            'title' => sanitize_text_field($_POST['slide_title']),
            'subtitle' => sanitize_text_field($_POST['slide_subtitle']),
            'description' => sanitize_textarea_field($_POST['slide_description']),
            'image_id' => intval($_POST['slide_image_id']),
            'image_url' => esc_url_raw($_POST['slide_image_url']),
            'button_text' => sanitize_text_field($_POST['slide_button_text']),
            'button_url' => esc_url_raw($_POST['slide_button_url']),
            'button_target' => sanitize_text_field($_POST['slide_button_target']),
            'slide_order' => intval($_POST['slide_order']),
            'is_active' => isset($_POST['slide_status']) ? 1 : 0
        );
        
        if ($slide_id > 0) {
            $result = $this->carousel->update_slide($slide_id, $data);
            $message = $result ? 'Slide updated successfully.' : 'Error updating slide.';
        } else {
            $result = $this->carousel->add_slide($data);
            $message = $result ? 'Slide added successfully.' : 'Error adding slide.';
        }
        
        $redirect_url = add_query_arg(array(
            'page' => 'nds-hero-carousel',
            'message' => $result ? 'success' : 'error',
            'message_text' => urlencode($message)
        ), admin_url('admin.php'));
        
        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * Delete carousel slide
     */
    public function delete_carousel_slide() {
        $redirect_url = add_query_arg(array('page' => 'nds-hero-carousel'), admin_url('admin.php'));

        if (!current_user_can('manage_options')) {
            wp_redirect(add_query_arg(array(
                'message' => 'error',
                'message_text' => urlencode('Unauthorized')
            ), $redirect_url));
            exit;
        }

        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'nds_delete_carousel_slide')) {
            wp_redirect(add_query_arg(array(
                'message' => 'error',
                'message_text' => urlencode('Security check failed')
            ), $redirect_url));
            exit;
        }

        $slide_id = isset($_GET['slide_id']) ? intval($_GET['slide_id']) : 0;
        if ($slide_id <= 0) {
            wp_redirect(add_query_arg(array(
                'message' => 'error',
                'message_text' => urlencode('Invalid slide ID')
            ), $redirect_url));
            exit;
        }

        $result = $this->carousel->delete_slide($slide_id);
        
        $message = $result ? 'Slide deleted successfully.' : 'Error deleting slide.';
        
        $redirect_url = add_query_arg(array(
            'page' => 'nds-hero-carousel',
            'message' => $result ? 'success' : 'error',
            'message_text' => urlencode($message)
        ), admin_url('admin.php'));
        
        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * Reorder carousel slides (admin_post)
     */
    public function reorder_carousel_slides() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        if (!wp_verify_nonce($_POST['_wpnonce'], 'nds_reorder_carousel_slides')) {
            wp_die('Security check failed');
        }
        
        $orders = $_POST['orders'];
        $success_count = 0;
        
        foreach ($orders as $slide_id => $order) {
            $slide = $this->carousel->get_slide($slide_id);
            if ($slide) {
                $data = array(
                    'title' => $slide->title,
                    'subtitle' => $slide->subtitle,
                    'description' => $slide->description,
                    'image_id' => $slide->image_id,
                    'image_url' => $slide->image_url,
                    'button_text' => $slide->button_text,
                    'button_url' => $slide->button_url,
                    'button_target' => $slide->button_target,
                    'slide_order' => intval($order),
                    'is_active' => $slide->is_active
                );
                
                if ($this->carousel->update_slide($slide_id, $data)) {
                    $success_count++;
                }
            }
        }
        
        $message = $success_count > 0 ? 'Slide order updated successfully.' : 'Error updating slide order.';
        
        $redirect_url = add_query_arg(array(
            'page' => 'nds-hero-carousel',
            'message' => $success_count > 0 ? 'success' : 'error',
            'message_text' => urlencode($message)
        ), admin_url('admin.php'));
        
        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * Toggle carousel slide status (AJAX)
     */
    public function toggle_carousel_slide() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'nds_toggle_carousel_slide')) {
            wp_send_json_error('Security check failed');
        }
        
        $slide_id = intval($_POST['slide_id']);
        $is_active = intval($_POST['is_active']);
        
        $slide = $this->carousel->get_slide($slide_id);
        if (!$slide) {
            wp_send_json_error('Slide not found');
        }
        
        $data = array(
            'title' => $slide->title,
            'subtitle' => $slide->subtitle,
            'description' => $slide->description,
            'image_id' => $slide->image_id,
            'image_url' => $slide->image_url,
            'button_text' => $slide->button_text,
            'button_url' => $slide->button_url,
            'button_target' => $slide->button_target,
            'slide_order' => $slide->slide_order,
            'is_active' => $is_active
        );
        
        $result = $this->carousel->update_slide($slide_id, $data);
        
        if ($result) {
            wp_send_json_success('Slide status updated');
        } else {
            wp_send_json_error('Error updating slide status');
        }
    }
    
    /**
     * Reorder carousel slides (AJAX)
     */
    public function reorder_carousel_slides_ajax() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'nds_reorder_carousel_slides')) {
            wp_send_json_error('Security check failed');
        }
        
        $orders = $_POST['orders'];
        $success_count = 0;
        
        foreach ($orders as $slide_id => $order) {
            $slide = $this->carousel->get_slide($slide_id);
            if ($slide) {
                $data = array(
                    'title' => $slide->title,
                    'subtitle' => $slide->subtitle,
                    'description' => $slide->description,
                    'image_id' => $slide->image_id,
                    'image_url' => $slide->image_url,
                    'button_text' => $slide->button_text,
                    'button_url' => $slide->button_url,
                    'button_target' => $slide->button_target,
                    'slide_order' => intval($order),
                    'is_active' => $slide->is_active
                );
                
                if ($this->carousel->update_slide($slide_id, $data)) {
                    $success_count++;
                }
            }
        }
        
        if ($success_count > 0) {
            wp_send_json_success('Slide order updated successfully');
        } else {
            wp_send_json_error('Error updating slide order');
        }
    }
}

// Initialize admin interface
new NDS_Hero_Carousel_Admin();
