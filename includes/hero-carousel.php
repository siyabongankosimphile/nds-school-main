<?php
/**
 * NDS Hero Carousel
 * A standalone carousel feature for the NDS School plugin
 */

if (!defined('ABSPATH')) {
    exit; // Prevent direct access
}

class NDS_Hero_Carousel {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_shortcode('nds_hero_carousel', array($this, 'hero_carousel_shortcode'));
    }
    
    /**
     * Slide component: renders a single hero slide consistently
     */
    private function render_hero_slide($slide, $atts) {
        ob_start();
        ?>
        <div class="swiper-slide">
            <div class="nds-slide-content theme-dark align-left">
                <?php if (!empty($slide->image_url) || !empty($slide->image_id)): ?>
                    <div class="nds-slide-image">
                        <?php
                        $image_url = '';
                        if (!empty($slide->image_id)) {
                            $image_url = wp_get_attachment_image_url($slide->image_id, 'full');
                        } elseif (!empty($slide->image_url)) {
                            $image_url = $slide->image_url;
                        }
                        if ($image_url):
                        ?>
                            <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($slide->title); ?>" loading="lazy">
                        <?php endif; ?>
                        <?php if ($atts['show_overlay'] === 'true'): ?>
                            <div class="nds-slide-overlay" style="opacity: <?php echo esc_attr($atts['overlay_opacity']); ?>;"></div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="nds-slide-text">
                    <div class="nds-slide-text-inner">
                        <?php if (!empty($slide->title)): ?>
                            <h2 class="nds-slide-title nds-stagger" data-delay="120"><?php echo esc_html($slide->title); ?></h2>
                        <?php endif; ?>
                        <?php if (!empty($slide->subtitle)): ?>
                            <h3 class="nds-slide-subtitle nds-stagger" data-delay="220"><?php echo esc_html($slide->subtitle); ?></h3>
                        <?php endif; ?>
                        <?php if (!empty($slide->description)): ?>
                            <p class="nds-slide-description nds-stagger" data-delay="320"><?php echo esc_html($slide->description); ?></p>
                        <?php endif; ?>
                        <div class="nds-cta-group">
                            <?php if (!empty($slide->button_text) && !empty($slide->button_url)): ?>
                                <a href="<?php echo esc_url($slide->button_url); ?>" class="nds-slide-button nds-stagger" data-delay="420" target="<?php echo esc_attr($slide->button_target); ?>">
                                    <?php echo esc_html($slide->button_text); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Initialize the carousel
     */
    public function init() {
        // Create database table for carousel slides
        $this->create_carousel_table();
    }
    
    /**
     * Create carousel slides table
     */
    private function create_carousel_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'nds_hero_carousel';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            subtitle text,
            description text,
            image_id int(11) DEFAULT NULL,
            image_url varchar(500) DEFAULT NULL,
            button_text varchar(100) DEFAULT NULL,
            button_url varchar(500) DEFAULT NULL,
            button_target varchar(20) DEFAULT '_self',
            slide_order int(11) DEFAULT 0,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY slide_order (slide_order),
            KEY is_active (is_active)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_scripts() {
        // Only enqueue if shortcode is present on the page
        global $post;
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'nds_hero_carousel')) {
            // Enqueue Swiper CSS and JS
            wp_enqueue_style('swiper-css', 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css', array(), '11.0.0');
            wp_enqueue_script('swiper-js', 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js', array(), '11.0.0', true);
            
            // Enqueue our custom styles and scripts with cache-busting versions
            $css_rel_path = '../assets/css/hero-carousel.css';
            $js_rel_path  = '../assets/js/hero-carousel.js';
            $css_abs_path = plugin_dir_path(__FILE__) . $css_rel_path;
            $js_abs_path  = plugin_dir_path(__FILE__) . $js_rel_path;
            $css_ver = file_exists($css_abs_path) ? filemtime($css_abs_path) : time();
            $js_ver  = file_exists($js_abs_path) ? filemtime($js_abs_path) : time();

            wp_enqueue_style('nds-hero-carousel', plugin_dir_url(__FILE__) . $css_rel_path, array('swiper-css'), $css_ver);
            wp_enqueue_script('nds-hero-carousel', plugin_dir_url(__FILE__) . $js_rel_path, array('jquery', 'swiper-js'), $js_ver, true);
        }
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function admin_enqueue_scripts($hook) {
        // Only load on our carousel admin pages
        if (strpos($hook, 'nds-hero-carousel') === false) {
            return;
        }
        
        wp_enqueue_media();
        // Frontend assets required for preview
        wp_enqueue_style('swiper-css', 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css', array(), '11.0.0');
        wp_enqueue_script('swiper-js', 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js', array(), '11.0.0', true);
        $css_rel_path = '../assets/css/hero-carousel.css';
        $js_rel_path  = '../assets/js/hero-carousel.js';
        $css_abs_path = plugin_dir_path(__FILE__) . $css_rel_path;
        $js_abs_path  = plugin_dir_path(__FILE__) . $js_rel_path;
        $css_ver = file_exists($css_abs_path) ? filemtime($css_abs_path) : time();
        $js_ver  = file_exists($js_abs_path) ? filemtime($js_abs_path) : time();

        wp_enqueue_style('nds-hero-carousel', plugin_dir_url(__FILE__) . $css_rel_path, array('swiper-css'), $css_ver);
        wp_enqueue_script('nds-hero-carousel', plugin_dir_url(__FILE__) . $js_rel_path, array('jquery', 'swiper-js'), $js_ver, true);

        // Admin-only styles/scripts
        wp_enqueue_style('nds-hero-carousel-admin', plugin_dir_url(__FILE__) . '../assets/css/hero-carousel-admin.css', array(), '1.0.0');
        wp_enqueue_script('nds-hero-carousel-admin', plugin_dir_url(__FILE__) . '../assets/js/hero-carousel-admin.js', array('jquery'), '1.0.0', true);

        // Force-disable WP sticky headers inside our table to avoid overlap
        $override_css = '
            .wrap .nds-carousel-table .wp-list-table thead,
            .wrap .nds-carousel-table .wp-list-table thead th,
            .wrap .nds-carousel-table .wp-list-table thead td,
            .wrap .nds-carousel-table .wp-list-table tfoot,
            .wrap .nds-carousel-table .wp-list-table tfoot th,
            .wrap .nds-carousel-table .wp-list-table tfoot td {
                position: static !important;
                top: auto !important;
                display: table-header-group !important;
                box-shadow: none !important;
                z-index: auto !important;
            }
            .wrap .nds-carousel-table .wp-list-table .is-sticky { position: static !important; }
        ';
        wp_add_inline_style('nds-hero-carousel-admin', $override_css);
    }
    
    /**
     * Hero carousel shortcode
     */
    public function hero_carousel_shortcode($atts) {
        // Ensure assets are present even when rendered outside a WP Post
        wp_enqueue_style('swiper-css', 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css', array(), '11.0.0');
        wp_enqueue_script('swiper-js', 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js', array(), '11.0.0', true);
        $css_rel_path = '../assets/css/hero-carousel.css';
        $js_rel_path  = '../assets/js/hero-carousel.js';
        $css_abs_path = plugin_dir_path(__FILE__) . $css_rel_path;
        $js_abs_path  = plugin_dir_path(__FILE__) . $js_rel_path;
        $css_ver = file_exists($css_abs_path) ? filemtime($css_abs_path) : time();
        $js_ver  = file_exists($js_abs_path) ? filemtime($js_abs_path) : time();

        wp_enqueue_style('nds-hero-carousel', plugin_dir_url(__FILE__) . $css_rel_path, array('swiper-css'), $css_ver);
        wp_enqueue_script('nds-hero-carousel', plugin_dir_url(__FILE__) . $js_rel_path, array('jquery', 'swiper-js'), $js_ver, true);
        $atts = shortcode_atts(array(
            'autoplay' => 'true',
            'autoplay_delay' => '5000',
            'loop' => 'true',
            'pagination' => 'true',
            'navigation' => 'true',
            'effect' => 'fade', // slide, fade, cube, coverflow
            'height' => '100vh',
            'show_overlay' => 'true',
            'overlay_opacity' => '0.4'
        ), $atts, 'nds_hero_carousel');
        
        $slides = $this->get_active_slides();
        
        if (empty($slides)) {
            return '<div class="nds-hero-carousel-empty">No carousel slides found. Please add some slides in the admin panel.</div>';
        }
        
        $carousel_id = 'nds-hero-carousel-' . uniqid();

        // Normalize height (append px if numeric)
        $height_value = trim((string) $atts['height']);
        if ($height_value !== '' && preg_match('/^\d+$/', $height_value)) {
            $height_value .= 'px';
        }
        if ($height_value === '') {
            $height_value = '100vh';
        }
        
        ob_start();
        ?>
        <div class="nds-hero-carousel" id="<?php echo esc_attr($carousel_id); ?>" style="--carousel-height: <?php echo esc_attr($height_value); ?>;"
             data-loop="<?php echo $atts['loop'] === 'true' ? 'true' : 'false'; ?>"
             data-autoplay="<?php echo $atts['autoplay'] === 'true' ? 'true' : 'false'; ?>"
             data-autoplay-delay="<?php echo intval($atts['autoplay_delay']); ?>"
             data-effect="<?php echo esc_attr($atts['effect']); ?>">
            <div class="swiper">
                <div class="swiper-wrapper">
                    <?php foreach ($slides as $slide): ?>
                        <?php echo $this->render_hero_slide($slide, $atts); ?>
                    <?php endforeach; ?>
                </div>
                
                <?php if ($atts['pagination'] === 'true'): ?>
                    <div class="swiper-pagination"></div>
                <?php endif; ?>
                
                <?php if ($atts['navigation'] === 'true'): ?>
                    <div class="swiper-button-next"></div>
                    <div class="swiper-button-prev"></div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get active carousel slides
     */
    private function get_active_slides() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'nds_hero_carousel';
        
        return $wpdb->get_results(
            "SELECT * FROM $table_name 
             WHERE is_active = 1 
             ORDER BY slide_order ASC, created_at ASC"
        );
    }
    
    /**
     * Get all carousel slides (for admin)
     */
    public function get_all_slides() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'nds_hero_carousel';
        
        return $wpdb->get_results(
            "SELECT * FROM $table_name 
             ORDER BY slide_order ASC, created_at ASC"
        );
    }
    
    /**
     * Add new slide
     */
    public function add_slide($data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'nds_hero_carousel';
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'title' => sanitize_text_field($data['title']),
                'subtitle' => sanitize_text_field($data['subtitle']),
                'description' => sanitize_textarea_field($data['description']),
                'image_id' => intval($data['image_id']),
                'image_url' => esc_url_raw($data['image_url']),
                'button_text' => sanitize_text_field($data['button_text']),
                'button_url' => esc_url_raw($data['button_url']),
                'button_target' => sanitize_text_field($data['button_target']),
                'slide_order' => intval($data['slide_order']),
                'is_active' => intval($data['is_active'])
            ),
            array('%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%d')
        );
        
        return $result !== false ? $wpdb->insert_id : false;
    }
    
    /**
     * Update slide
     */
    public function update_slide($id, $data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'nds_hero_carousel';
        
        $result = $wpdb->update(
            $table_name,
            array(
                'title' => sanitize_text_field($data['title']),
                'subtitle' => sanitize_text_field($data['subtitle']),
                'description' => sanitize_textarea_field($data['description']),
                'image_id' => intval($data['image_id']),
                'image_url' => esc_url_raw($data['image_url']),
                'button_text' => sanitize_text_field($data['button_text']),
                'button_url' => esc_url_raw($data['button_url']),
                'button_target' => sanitize_text_field($data['button_target']),
                'slide_order' => intval($data['slide_order']),
                'is_active' => intval($data['is_active']),
                'updated_at' => current_time('mysql')
            ),
            array('id' => intval($id)),
            array('%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s'),
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Delete slide
     */
    public function delete_slide($id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'nds_hero_carousel';
        
        return $wpdb->delete(
            $table_name,
            array('id' => intval($id)),
            array('%d')
        );
    }
    
    /**
     * Get slide by ID
     */
    public function get_slide($id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'nds_hero_carousel';
        
        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", intval($id))
        );
    }
}

// Initialize the carousel
new NDS_Hero_Carousel();
