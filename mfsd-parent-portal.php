<?php
/**
 * Plugin Name: MFSD Parent Portal
 * Plugin URI: https://mfsd.me
 * Description: Parent dashboard to view linked student progress in the High Performance Pathway
 * Version: 1.0.1
 * Author: MisterT9007
 * Author URI: https://mfsd.me
 * Text Domain: mfsd-parent-portal
 */

if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('MFSD_PARENT_PORTAL_VERSION', '1.0.1');
define('MFSD_PARENT_PORTAL_PATH', plugin_dir_path(__FILE__));
define('MFSD_PARENT_PORTAL_URL', plugin_dir_url(__FILE__));

/**
 * Main plugin class
 */
class MFSD_Parent_Portal {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    private function load_dependencies() {
        require_once MFSD_PARENT_PORTAL_PATH . 'includes/class-parent-portal-data.php';
        require_once MFSD_PARENT_PORTAL_PATH . 'includes/class-parent-portal-renderer.php';
    }
    
    private function init_hooks() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_shortcode('mfsd_parent_portal', [$this, 'render_portal']);
    }
    
    public function enqueue_assets() {
        // Only load on pages with our shortcode
        global $post;
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'mfsd_parent_portal')) {
            wp_enqueue_style(
                'mfsd-parent-portal',
                MFSD_PARENT_PORTAL_URL . 'assets/css/parent-portal.css',
                [],
                MFSD_PARENT_PORTAL_VERSION
            );
            
            wp_enqueue_script(
                'mfsd-parent-portal',
                MFSD_PARENT_PORTAL_URL . 'assets/js/parent-portal.js',
                ['jquery'],
                MFSD_PARENT_PORTAL_VERSION,
                true
            );
        }
    }
    
    public function render_portal($atts) {
        // Must be logged in
        if (!is_user_logged_in()) {
            return '<div class="mfsd-pp-notice mfsd-pp-notice--warning">Please log in to view the Parent Portal.</div>';
        }
        
        $current_user_id = get_current_user_id();
        
        // Check if user is a parent (has linked students)
        $data = new MFSD_Parent_Portal_Data();
        $linked_students = $data->get_linked_students($current_user_id);
        
        if (empty($linked_students)) {
            return '<div class="mfsd-pp-notice mfsd-pp-notice--info">
                <h3>No Students Linked</h3>
                <p>You don\'t currently have any students linked to your account. If you believe this is an error, please contact your school administrator.</p>
            </div>';
        }
        
        // Render the portal
        $renderer = new MFSD_Parent_Portal_Renderer($data);
        return $renderer->render($linked_students);
    }
}

// Initialize plugin
function mfsd_parent_portal_init() {
    MFSD_Parent_Portal::get_instance();
}
add_action('plugins_loaded', 'mfsd_parent_portal_init');

// Activation hook
register_activation_hook(__FILE__, function() {
    // Nothing to create - we use existing tables
    // But we could add options or flush rewrite rules if needed
    flush_rewrite_rules();
});
