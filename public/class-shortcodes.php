<?php
/**
 * NDS School Shortcodes Class
 * Handles all shortcodes for the NDS School plugin
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class NDS_Shortcodes {
    
    public function __construct() {
        add_action('init', array($this, 'register_shortcodes'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_shortcode_styles'));
    }
    
    /**
     * Register all shortcodes
     */
    public function register_shortcodes() {
        add_shortcode('nds_recipes', array($this, 'recipes_shortcode'));
        add_shortcode('nds_recipe_grid', array($this, 'recipe_grid_shortcode'));
        add_shortcode('nds_recipe_single', array($this, 'recipe_single_shortcode'));
        add_shortcode('nds_recipe_carousel', array($this, 'recipe_carousel_shortcode'));
        add_shortcode('nds_calendar', array($this, 'calendar_shortcode'));
    }
    
    /**
     * Enqueue styles and scripts for shortcodes
     */
    public function enqueue_shortcode_styles() {
        // Only enqueue if shortcodes are present on the page
        global $post;
        if (!is_a($post, 'WP_Post')) {
            return;
        }
        
        // Recipes shortcode assets
        if (has_shortcode($post->post_content, 'nds_recipes')) {
            // Enqueue Owl Carousel CSS and JS
            wp_enqueue_style('owl-carousel-css', 'https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.carousel.min.css', array(), '2.3.4');
            wp_enqueue_style('owl-carousel-theme', 'https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.theme.default.min.css', array(), '2.3.4');
            wp_enqueue_script('owl-carousel-js', 'https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/owl.carousel.min.js', array('jquery'), '2.3.4', true);
            
            // Enqueue our custom styles and scripts
            wp_enqueue_style('nds-recipes-shortcode', plugin_dir_url(__FILE__) . '../assets/css/recipes-shortcode.css', array(), '1.0.0');
            wp_enqueue_script('nds-recipes-shortcode', plugin_dir_url(__FILE__) . '../assets/js/recipes-shortcode.js', array('jquery', 'owl-carousel-js'), '1.0.0', true);
        }
        
        // Calendar shortcode assets
        if (has_shortcode($post->post_content, 'nds_calendar')) {
            // FullCalendar CSS
            wp_enqueue_style(
                'fullcalendar-css',
                'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css',
                array(),
                '6.1.10'
            );
            
            // FullCalendar JS
            wp_enqueue_script(
                'fullcalendar-js',
                'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js',
                array('jquery'),
                '6.1.10',
                true
            );
            
            // Custom calendar JS
            wp_enqueue_script(
                'nds-frontend-calendar',
                plugin_dir_url(__FILE__) . '../assets/js/frontend-calendar.js',
                array('jquery', 'fullcalendar-js'),
                filemtime(plugin_dir_path(__FILE__) . '../assets/js/frontend-calendar.js'),
                true
            );
            
            // Localize script for AJAX
            wp_localize_script('nds-frontend-calendar', 'ndsFrontendCalendar', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('nds_public_calendar_nonce')
            ));
            
            // Enqueue Tailwind CSS if available
            $plugin_dir = plugin_dir_path(__FILE__);
            $css_file = $plugin_dir . '../assets/css/frontend.css';
            if (file_exists($css_file)) {
                wp_enqueue_style(
                    'nds-tailwindcss-calendar',
                    plugin_dir_url(__FILE__) . '../assets/css/frontend.css',
                    array(),
                    filemtime($css_file),
                    'all'
                );
            }
            
            // Font Awesome icons
            wp_enqueue_style('nds-icons', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css', array(), null, 'all');
        }
    }
    
    /**
     * Main recipes shortcode - displays recipe grid with theme integration
     */
    public function recipes_shortcode($atts) {
        $atts = shortcode_atts(array(
            'limit' => 12,
            'columns' => 4,
            'category' => '',
            'orderby' => 'created_at',
            'order' => 'DESC',
            'show_image' => 'true',
            'show_description' => 'true',
            'show_time' => 'true',
            'show_servings' => 'true',
            'layout' => 'grid', // grid, list, masonry, carousel
            'theme_style' => 'auto', // auto, minimal, card, modern
            'elementor_compatible' => 'true',
            'carousel' => 'false' // Enable carousel mode
        ), $atts, 'nds_recipes');
        
        // Get recipes from database
        $recipes = $this->get_recipes($atts);
        
        if (empty($recipes)) {
            return '<div class="nds-no-recipes">No recipes found.</div>';
        }
        
        // Detect current theme and apply appropriate styling
        $theme_style = $this->detect_theme_style($atts['theme_style']);
        
        // If carousel is enabled, use carousel layout
        if ($atts['carousel'] === 'true' || $atts['layout'] === 'carousel') {
            return $this->render_carousel($recipes, $atts, $theme_style);
        }
        
        ob_start();
        ?>
        <div class="nds-recipes-container <?php echo esc_attr($theme_style); ?> layout-<?php echo esc_attr($atts['layout']); ?>">
            <div class="nds-recipes-grid" style="--columns: <?php echo esc_attr($atts['columns']); ?>;">
                <?php foreach ($recipes as $recipe): ?>
                    <?php echo $this->render_recipe_card($recipe, $atts); ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Recipe grid shortcode - specific grid layout
     */
    public function recipe_grid_shortcode($atts) {
        $atts['layout'] = 'grid';
        return $this->recipes_shortcode($atts);
    }
    
    /**
     * Single recipe shortcode
     */
    public function recipe_single_shortcode($atts) {
        $atts = shortcode_atts(array(
            'id' => 0,
            'show_image' => 'true',
            'show_ingredients' => 'true',
            'show_steps' => 'true',
            'show_meta' => 'true',
            'theme_style' => 'auto'
        ), $atts, 'nds_recipe_single');
        
        if (empty($atts['id'])) {
            return '<div class="nds-error">Recipe ID is required.</div>';
        }
        
        $recipe = $this->get_recipe_by_id($atts['id']);
        
        if (!$recipe) {
            return '<div class="nds-error">Recipe not found.</div>';
        }
        
        $theme_style = $this->detect_theme_style($atts['theme_style']);
        
        ob_start();
        ?>
        <div class="nds-recipe-single <?php echo esc_attr($theme_style); ?>">
            <?php echo $this->render_single_recipe($recipe, $atts); ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Recipe carousel shortcode
     */
    public function recipe_carousel_shortcode($atts) {
        $atts = shortcode_atts(array(
            'limit' => 8,
            'autoplay' => 'true',
            'dots' => 'true',
            'arrows' => 'true',
            'theme_style' => 'auto',
            'recipes_per_slide' => 4
        ), $atts, 'nds_recipe_carousel');
        
        $recipes = $this->get_recipes($atts);
        
        if (empty($recipes)) {
            return '<div class="nds-no-recipes">No recipes found.</div>';
        }
        
        $theme_style = $this->detect_theme_style($atts['theme_style']);
        
        return $this->render_carousel($recipes, $atts, $theme_style);
    }
    
    /**
     * Render carousel with recipes using Owl Carousel
     */
    private function render_carousel($recipes, $atts, $theme_style) {
        $recipes_per_slide = isset($atts['recipes_per_slide']) ? intval($atts['recipes_per_slide']) : 4;
        $autoplay = isset($atts['autoplay']) ? $atts['autoplay'] : 'true';
        $dots = isset($atts['dots']) ? $atts['dots'] : 'true';
        $arrows = isset($atts['arrows']) ? $atts['arrows'] : 'true';

        // Prevent duplicate slides when there is only one recipe
        $recipes_count = is_array($recipes) ? count($recipes) : 0;
        $should_loop = $recipes_count > 1;
        // If we don't loop, also disable autoplay, dots and arrows for a cleaner UI
        $js_loop = $should_loop ? 'true' : 'false';
        $js_autoplay = ($should_loop && $autoplay === 'true') ? 'true' : 'false';
        $js_dots = ($should_loop && $dots === 'true') ? 'true' : 'false';
        $js_nav = ($should_loop && $arrows === 'true') ? 'true' : 'false';
        
        // Generate unique ID for this carousel
        $carousel_id = 'nds-recipe-carousel-' . uniqid();
        
        ob_start();
        ?>
        <div class="nds-recipe-carousel <?php echo esc_attr($theme_style); ?>" id="<?php echo esc_attr($carousel_id); ?>">
            <div class="owl-carousel owl-theme">
                <?php foreach ($recipes as $recipe): ?>
                    <div class="item">
                        <?php echo $this->render_recipe_card($recipe, $atts); ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#<?php echo esc_js($carousel_id); ?> .owl-carousel').owlCarousel({
                loop: <?php echo $js_loop; ?>,
                margin: 20,
                nav: <?php echo $js_nav; ?>,
                dots: <?php echo $js_dots; ?>,
                autoplay: <?php echo $js_autoplay; ?>,
                autoplayTimeout: 5000,
                autoplayHoverPause: true,
                responsive: {
                    0: {
                        items: 1
                    },
                    768: {
                        items: Math.min(2, <?php echo (int) $recipes_count; ?>)
                    },
                    1200: {
                        items: Math.min(<?php echo (int) $recipes_per_slide; ?>, <?php echo (int) $recipes_count; ?>)
                    }
                },
                navText: ['<span class="owl-nav-prev">&lt;</span>', '<span class="owl-nav-next">&gt;</span>']
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get recipes from database
     */
    private function get_recipes($atts) {
        global $wpdb;
        $table = $wpdb->prefix . 'nds_recipes';
        
        $limit = intval($atts['limit']);
        $orderby = sanitize_sql_orderby($atts['orderby']);
        $order = strtoupper($atts['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        $query = "SELECT * FROM $table ORDER BY $orderby $order";
        
        if ($limit > 0) {
            $query .= " LIMIT $limit";
        }
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Get single recipe by ID
     */
    private function get_recipe_by_id($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'nds_recipes';
        
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
    }
    
    /**
     * Detect current theme and return appropriate style class
     */
    private function detect_theme_style($preferred_style) {
        if ($preferred_style !== 'auto') {
            return 'nds-style-' . $preferred_style;
        }
        
        // Detect popular themes
        $theme = wp_get_theme();
        $theme_name = strtolower($theme->get('Name'));
        
        // Elementor detection
        if (class_exists('Elementor\Plugin')) {
            return 'nds-style-elementor';
        }
        
        // Popular theme detection
        $theme_styles = array(
            'astra' => 'nds-style-modern',
            'oceanwp' => 'nds-style-modern',
            'generatepress' => 'nds-style-minimal',
            'divi' => 'nds-style-card',
            'avada' => 'nds-style-modern',
            'enfold' => 'nds-style-card',
            'twenty' => 'nds-style-minimal'
        );
        
        foreach ($theme_styles as $theme_key => $style) {
            if (strpos($theme_name, $theme_key) !== false) {
                return $style;
            }
        }
        
        // Default to modern style
        return 'nds-style-modern';
    }
    
    /**
     * Render recipe card
     */
    private function render_recipe_card($recipe, $atts) {
        $recipe_data = json_decode($recipe->the_recipe, true);
        $image_url = !empty($recipe->image) ? wp_get_attachment_url($recipe->image) : '';
        
        ob_start();
        ?>
        <div class="nds-recipe-card">
            <?php if ($atts['show_image'] === 'true' && !empty($image_url)): ?>
                <div class="nds-recipe-image">
                    <img src="<?php echo esc_url($image_url); ?>" 
                         alt="<?php echo esc_attr($recipe->recipe_name); ?>"
                         loading="lazy">
                </div>
            <?php endif; ?>
            
            <div class="nds-recipe-content">
                <h3 class="nds-recipe-title">
                    <a href="<?php echo esc_url($this->get_recipe_url($recipe->id)); ?>">
                        <?php echo esc_html($recipe->recipe_name); ?>
                    </a>
                </h3>
                
                <?php if ($atts['show_description'] === 'true' && !empty($recipe_data['mini_description'])): ?>
                    <p class="nds-recipe-description">
                        <?php echo esc_html($recipe_data['mini_description']); ?>
                    </p>
                <?php endif; ?>
                
                <?php if ($atts['show_time'] === 'true' || $atts['show_servings'] === 'true'): ?>
                    <div class="nds-recipe-meta">
                        <?php if ($atts['show_time'] === 'true' && !empty($recipe_data['cooking'])): ?>
                            <span class="nds-recipe-time">
                                <i class="fas fa-clock"></i>
                                <?php echo esc_html($recipe_data['cooking']); ?> min
                            </span>
                        <?php endif; ?>
                        
                        <?php if ($atts['show_servings'] === 'true' && !empty($recipe_data['servings'])): ?>
                            <span class="nds-recipe-servings">
                                <i class="fas fa-users"></i>
                                <?php echo esc_html($recipe_data['servings']); ?> servings
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render single recipe
     */
    private function render_single_recipe($recipe, $atts) {
        $recipe_data = json_decode($recipe->the_recipe, true);
        $image_url = !empty($recipe->image) ? wp_get_attachment_url($recipe->image) : '';
        
        ob_start();
        ?>
        <div class="nds-recipe-single-content">
            <?php if ($atts['show_image'] === 'true' && !empty($image_url)): ?>
                <div class="nds-recipe-single-image">
                    <img src="<?php echo esc_url($image_url); ?>" 
                         alt="<?php echo esc_attr($recipe->recipe_name); ?>">
                </div>
            <?php endif; ?>
            
            <h1 class="nds-recipe-single-title"><?php echo esc_html($recipe->recipe_name); ?></h1>
            
            <?php if ($atts['show_meta'] === 'true'): ?>
                <div class="nds-recipe-single-meta">
                    <?php if (!empty($recipe_data['cooking'])): ?>
                        <span class="nds-recipe-time">
                            <i class="fas fa-clock"></i>
                            Cooking: <?php echo esc_html($recipe_data['cooking']); ?> min
                        </span>
                    <?php endif; ?>
                    
                    <?php if (!empty($recipe_data['prep'])): ?>
                        <span class="nds-recipe-prep">
                            <i class="fas fa-utensils"></i>
                            Prep: <?php echo esc_html($recipe_data['prep']); ?> min
                        </span>
                    <?php endif; ?>
                    
                    <?php if (!empty($recipe_data['servings'])): ?>
                        <span class="nds-recipe-servings">
                            <i class="fas fa-users"></i>
                            <?php echo esc_html($recipe_data['servings']); ?> servings
                        </span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($recipe_data['mini_description'])): ?>
                <div class="nds-recipe-single-description">
                    <?php echo esc_html($recipe_data['mini_description']); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($atts['show_ingredients'] === 'true' && !empty($recipe_data['ingredients'])): ?>
                <div class="nds-recipe-ingredients">
                    <h3>Ingredients</h3>
                    <ul>
                        <?php foreach ($recipe_data['ingredients'] as $ingredient): ?>
                            <li><?php echo esc_html($ingredient); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <?php if ($atts['show_steps'] === 'true' && !empty($recipe_data['steps'])): ?>
                <div class="nds-recipe-steps">
                    <h3>Instructions</h3>
                    <ol>
                        <?php foreach ($recipe_data['steps'] as $step): ?>
                            <li><?php echo esc_html($step); ?></li>
                        <?php endforeach; ?>
                    </ol>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get recipe URL
     */
    private function get_recipe_url($recipe_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'nds_recipes';
        // Try to use the WP post if it exists
        $post_id = (int) $wpdb->get_var($wpdb->prepare("SELECT post_id FROM $table WHERE id = %d", $recipe_id));
        if ($post_id && get_post_status($post_id)) {
            $permalink = get_permalink($post_id);
            if ($permalink) {
                return $permalink;
            }
        }
        // Fallback to plugin route
        return home_url('/recipe/' . $recipe_id);
    }
    
    /**
     * Calendar shortcode - displays public calendar
     */
    public function calendar_shortcode($atts) {
        $atts = shortcode_atts(array(
            'show_stats' => 'true',
            'initial_view' => 'dayGridMonth',
            'height' => 'auto'
        ), $atts, 'nds_calendar');
        
        global $wpdb;
        
        // Get statistics if enabled
        $stats = array();
        if ($atts['show_stats'] === 'true') {
            $calendar_events_table = $wpdb->prefix . 'nds_calendar_events';
            $programs_table = $wpdb->prefix . 'nds_programs';
            $schedules_table = $wpdb->prefix . 'nds_course_schedules';
            
            $total_events = 0;
            $events_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$calendar_events_table'") === $calendar_events_table;
            if ($events_table_exists) {
                $total_events = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$calendar_events_table} WHERE status = 'active'");
            }
            
            $total_programs = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$programs_table} WHERE status = 'active'");
            $total_schedules = 0;
            $schedules_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$schedules_table'") === $schedules_table;
            if ($schedules_table_exists) {
                $total_schedules = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$schedules_table} WHERE is_active = 1");
            }
            
            $stats = array(
                'events' => $total_events,
                'programs' => $total_programs,
                'schedules' => $total_schedules
            );
        }
        
        ob_start();
        ?>
        <div class="nds-calendar-wrapper bg-gray-50 min-h-screen p-4 md:p-8" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
            <div class="max-w-7xl mx-auto">
                
                <!-- Header Section -->
                <div class="mb-8">
                    <h1 class="text-3xl md:text-4xl font-bold text-gray-900 mb-2">
                        <i class="fas fa-calendar text-blue-600 mr-4"></i>Calendar
                    </h1>
                    <p class="text-gray-600 text-lg">View calendar events, programs, and schedules</p>
                </div>

                <!-- Statistics Cards -->
                <?php if ($atts['show_stats'] === 'true' && !empty($stats)): ?>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 hover:shadow-md transition-shadow duration-200">
                        <div class="flex items-center">
                            <div class="w-12 h-12 bg-blue-500 rounded-lg flex items-center justify-center mr-4">
                                <i class="fas fa-calendar-alt text-white text-xl"></i>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-600 mb-1">Calendar Events</p>
                                <p class="text-3xl font-bold text-gray-900"><?php echo number_format($stats['events']); ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 hover:shadow-md transition-shadow duration-200">
                        <div class="flex items-center">
                            <div class="w-12 h-12 bg-green-500 rounded-lg flex items-center justify-center mr-4">
                                <i class="fas fa-graduation-cap text-white text-xl"></i>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-600 mb-1">Active Programs</p>
                                <p class="text-3xl font-bold text-gray-900"><?php echo number_format($stats['programs']); ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 hover:shadow-md transition-shadow duration-200">
                        <div class="flex items-center">
                            <div class="w-12 h-12 bg-purple-500 rounded-lg flex items-center justify-center mr-4">
                                <i class="fas fa-clock text-white text-xl"></i>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-600 mb-1">Course Schedules</p>
                                <p class="text-3xl font-bold text-gray-900"><?php echo number_format($stats['schedules']); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Calendar Container -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-8">
                    <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4">
                        <h3 class="text-lg font-bold text-white flex items-center">
                            <i class="fas fa-calendar-check mr-2"></i>Calendar View
                        </h3>
                    </div>

                    <div class="p-6">
                        <div id="nds-frontend-calendar" style="min-height: 600px;"></div>
                    </div>
                </div>

                <!-- Event Details Modal -->
                <div id="nds-event-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden" style="z-index: 99999;" onclick="if(event.target === this) closeEventModal();">
                    <div class="flex items-center justify-center min-h-screen p-4">
                        <div class="bg-white rounded-xl shadow-xl max-w-lg w-full max-h-screen overflow-y-auto">
                            <div class="p-6">
                                <div class="flex items-center justify-between mb-6">
                                    <h2 id="event-modal-title" class="text-2xl font-bold text-gray-900">Event Details</h2>
                                    <button type="button" onclick="closeEventModal()" class="text-gray-400 hover:text-gray-600">
                                        <i class="fas fa-times text-xl"></i>
                                    </button>
                                </div>

                                <div id="event-modal-content">
                                    <!-- Content will be populated by JavaScript -->
                                </div>

                                <div class="flex items-center justify-end mt-8 pt-6 border-t border-gray-200">
                                    <button type="button" onclick="closeEventModal()"
                                        class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-all duration-200 font-medium">
                                        Close
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
}

// Initialize the shortcodes class
new NDS_Shortcodes();
