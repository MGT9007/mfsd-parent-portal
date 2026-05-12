<?php
/**
 * Plugin Name: MFSD Parent Portal
 * Plugin URI: https://mfsd.me
 * Description: Combined parent and student progress portal for the High Performance Pathway
 * Version: 4.4.4
 * Author: MisterT9007
 * Author URI: https://mfsd.me
 * Text Domain: mfsd-parent-portal
 */

if (!defined('ABSPATH')) exit;

define('MFSD_PARENT_PORTAL_VERSION', '4.4.4');
define('MFSD_PARENT_PORTAL_PATH',    plugin_dir_path(__FILE__));
define('MFSD_PARENT_PORTAL_URL',     plugin_dir_url(__FILE__));

class MFSD_Parent_Portal {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) self::$instance = new self();
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
        add_filter('body_class', [$this, 'add_body_classes']);
    }

    public function add_body_classes($classes) {
        global $post;
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'mfsd_parent_portal')) {
            $classes[] = 'mfsd-no-page-title';
        }
        return $classes;
    }

    public function enqueue_assets() {
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
        if (!is_user_logged_in()) {
            return '<div class="mfsd-pp-notice mfsd-pp-notice--warning">Please log in to view your portal.</div>';
        }

        $current_user_id = get_current_user_id();
        $current_user    = wp_get_current_user();
        $roles           = (array) $current_user->roles;
        $course_id       = isset($_GET['course_id']) ? (int) $_GET['course_id'] : 0;
        $student_id      = isset($_GET['student_id']) ? (int) $_GET['student_id'] : 0;

        $data     = new MFSD_Parent_Portal_Data();
        $is_student = in_array('student', $roles) && !in_array('administrator', $roles);

        // ── Course detail view (course_id present in URL) ─────────────────────
        if ($course_id) {
            // Students always view themselves; parents/admins use student_id param
            $view_student_id = $is_student ? $current_user_id : $student_id;

            if (!$view_student_id) {
                return '<div class="mfsd-pp-notice mfsd-pp-notice--warning">No student specified.</div>';
            }

            $renderer = new MFSD_Parent_Portal_Renderer($data, $is_student ? 'student' : 'parent');

            if ($is_student) {
                return $renderer->render_student_self($current_user_id, $course_id);
            }

            // Validate parent is actually linked to this student
            $linked = $data->get_linked_students($current_user_id);
            $linked_ids = array_column((array) $linked, 'student_user_id');
            if (!in_array($view_student_id, array_map('intval', $linked_ids))) {
                return '<div class="mfsd-pp-notice mfsd-pp-notice--warning">Student not found.</div>';
            }

            $student = current(array_filter((array) $linked, fn($s) => (int)$s->student_user_id === $view_student_id));
            return $renderer->render([$student], $course_id);
        }

        // ── Landing view (no course_id) ───────────────────────────────────────
        $renderer = new MFSD_Parent_Portal_Renderer($data, $is_student ? 'student' : 'parent');

        if ($is_student) {
            return $renderer->render_landing_student($current_user_id);
        }

        $linked_students = $data->get_linked_students($current_user_id);
        if (empty($linked_students)) {
            return '<div class="mfsd-pp-notice mfsd-pp-notice--info">
                <h3>No Students Linked</h3>
                <p>You don\'t currently have any students linked to your account. If you believe this is an error, please contact your school administrator.</p>
            </div>';
        }

        return $renderer->render_landing_parent($linked_students);
    }
}

function mfsd_parent_portal_init() {
    MFSD_Parent_Portal::get_instance();
}
add_action('plugins_loaded', 'mfsd_parent_portal_init');

register_activation_hook(__FILE__, function() {
    flush_rewrite_rules();
});