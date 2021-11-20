<?php
/**
 * Plugin Name: AP Popular Posts
 * Description: Popular posts plugin.
 * Version: 1.2.1
 * Author: APALODI
 * Author URI: https://apalodi.com
 * Tags: popular, posts
 *
 * Text Domain: ap-popular-posts
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Main AP_Popular_Posts Class.
 *
 * @package     AP_Popular_Posts
 * @since       1.0.0
 */
final class AP_Popular_Posts {

    /**
     * Plugin version.
     *
     * @var     string
     * @access  protected
     */
    protected $version = '1.2.1';

    /**
	 * Database Schema version.
	 *
	 * @since   1.2.0
	 * @var     string
     * @access  protected
	 */
	protected $db_version = '1.2.0';

    /**
     * The single instance of the class.
     *
     * @since   1.0.0
     * @var     AP_Popular_Posts
     * @access  protected
     */
    protected static $_instance = null;

    /**
     * A dummy magic method to prevent class from being cloned.
     *
     * @since   1.0.0
     * @access  public
     */
    public function __clone() {
        _doing_it_wrong( __FUNCTION__, 'Cheatin&#8217; huh?', '1.0.0' );
    }

    /**
     * A dummy magic method to prevent class from being unserialized.
     *
     * @since   1.0.0
     * @access  public
     */
    public function __wakeup() {
        _doing_it_wrong( __FUNCTION__, 'Cheatin&#8217; huh?', '1.0.0' );
    }

    /**
     * Main instance.
     *
     * Ensures only one instance is loaded or can be loaded.
     *
     * @since   1.0.0
     * @access  public
     * @return  Main instance.
     */
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Constructor.
     *
     * @since   1.0.0
     * @access  public
     */
    public function __construct() {
        $this->file = __FILE__;
        $this->basename = plugin_basename( $this->file );

        $this->includes();
        $this->init_hooks();

        do_action( 'ap_popular_posts_loaded' );
    }

    /**
     * Hook into actions and filters.
     *
     * @since   1.0.0
     * @access  private
     */
    private function init_hooks() {
        register_activation_hook( $this->file, array( 'AP_Popular_Posts_Data', 'install' ) );

        add_action( 'after_setup_theme', array( $this, 'include_template_functions' ), 11 );
        add_action( 'init', array( $this, 'init' ), 0 );
        add_action( 'widgets_init', array( $this, 'register_widgets' ) );
    }

    /**
     * Include required files.
     *
     * @since   1.0.0
     * @access  public
     */
    public function includes() {
        $dir = $this->get_plugin_path();

        require_once( $dir . 'includes/core-functions.php' );
        require_once( $dir . 'includes/conditional-functions.php' );

        require_once( $dir . 'includes/class-data.php' );
        require_once( $dir . 'includes/class-ajax.php' );
        require_once( $dir . 'includes/class-views.php' );
        require_once( $dir . 'includes/class-query.php' );
        require_once( $dir . 'includes/class-widget.php' );
        require_once( $dir . 'includes/class-frontend-scripts.php' );

        if ( is_admin() ) {
            require_once( $dir . 'admin/class-admin.php' );
        }

        // Load class instances.
        $this->ajax = new AP_Popular_Posts_AJAX();
        $this->views = new AP_Popular_Posts_Views();
        $this->query = new AP_Popular_Posts_Query();
    }

    /**
     * Include template functions.
     *
     * This makes them pluggable by plugins and themes.
     *
     * @since   1.0.0
     * @access  public
     */
    public function include_template_functions() {
        $dir = $this->get_plugin_path();
        require_once( $dir . 'includes/template-tags.php' );
    }

    /**
     * Init when WordPress Initialises.
     *
     * @since   1.0.0
     * @access  public
     */
    public function init() {
        // Before init action.
        do_action( 'ap_popular_posts_before_init' );

        // Set up localisation.
        $this->load_plugin_textdomain();

        // Init action.
        do_action( 'ap_popular_posts_init' );
    }

    /**
     * Register widgets.
     *
     * @since   1.0.0
     * @access  public
     */
    public function register_widgets() {
        register_widget( 'AP_Popular_Posts_Widget' );
    }

    /**
     * Load Localisation files.
     *
     * @since   1.0.0
     * @access  public
     */
    public function load_plugin_textdomain() {
        load_plugin_textdomain( 'ap-popular-posts', false, plugin_basename( dirname( $this->file ) ) . '/languages' );
    }

    /**
     * Get all available intervals.
     *
     * @since   1.0.0
     * @access  public
     */
    public function get_intervals() {
        /**
         * Filters the view intervals adding option to add new interval.
         *
         * @since   1.0.0
         */
        $intervals = apply_filters( 'ap_popular_posts_views_intervals', array(
            '1' => __( 'Last 24 hours', 'ap-popular-posts' ),
            '3' => __( 'Last 3 days', 'ap-popular-posts' ),
            '7' => __( 'Last 7 days', 'ap-popular-posts' )
        ) );

        return $intervals;
    }

    /**
     * Get the max interval.
     *
     * @since   1.2.0
     * @access  public
     */
    public function get_max_interval() {
        $intervals = $this->get_intervals();
        return max( array_keys( $intervals ) );
    }

    /**
     * Get the plugin url.
     *
     * @since   1.0.0
     * @access  public
     * @return  string
     */
    public function get_plugin_url() {
        return plugin_dir_url( $this->file );
    }

    /**
     * Get the plugin path.
     *
     * @since   1.0.0
     * @access  public
     * @return  string
     */
    public function get_plugin_path() {
        return plugin_dir_path( $this->file );
    }

    /**
     * Get the template path.
     *
     * @since   1.0.0
     * @access  public
     * @return  string
     */
    public function get_template_path() {
        return trailingslashit( apply_filters( 'ap_popular_posts_template_path', 'ap-popular-posts' ) );
    }

    /**
     * Retrieve the version number of the plugin.
     *
     * @since   1.0.0
     * @access  public
     * @return  string
     */
    public function get_version() {
        return $this->version;
    }

    /**
     * Retrieve the database version number of the plugin.
     *
     * @since   1.2.0
     * @access  public
     * @return  string
     */
    public function get_db_version() {
        return $this->db_version;
    }

    /**
     * Get interval in days as Unix timestamp.
     *
     * @since   1.2.0
     * @access  public
     * @param   int     $interval   Interval in days
     * @return  int     $interval   Interval as timestamp
     */
    public function get_timestamp( $interval ) {
        return strtotime( sprintf( '-%d day', $interval ) );
    }

    /**
     * Determines whether the plugin is active for the entire network.
     *
     * @since   1.2.1
     * @access  public
     * @param   string  $plugin     Path to the plugin file relative to the plugins directory.
     * @return  bool    $is_active  True if active for the network, otherwise false.
     */
    public function is_plugin_active_for_network( $plugin ) {
        if ( ! is_multisite() ) {
            return false;
        }

        $plugins = get_site_option( 'active_sitewide_plugins' );

        if ( isset( $plugins[ $plugin ] ) ) {
            return true;
        }

        return false;
    }

    /**
     * Determines whether the plugin is active.
     *
     * @since   1.2.1
     * @access  public
     * @param   string  $plugin     Path to the plugin file relative to the plugins directory.
     * @return  bool    $is_active  True, if in the active plugins list. False, not in the list.
     */
    public function is_plugin_active( $plugin ) {
        $is_active = in_array( $plugin, (array) get_option( 'active_plugins', array() ), true );
        return $is_active || $this->is_plugin_active_for_network( $plugin );
    }

    /**
     * Checks if a multilingual plugin is active.
     *
     * @since   1.2.0
     * @access  public
     * @return  bool
     */
    public function is_multilingual_plugin_active() {
        $plugins = apply_filters( 'ap_popular_posts_multilingual_plugins', array(
            'sitepress-multilingual-cms/sitepress.php',
            'polylang-pro/polylang.php',
            'polylang/polylang.php',
        ) );

        foreach ( $plugins as $plugin ) {
            if ( $this->is_plugin_active( $plugin ) ) {
                return true;
            }
        }

        return false;
    }

}

/**
 * The main function responsible for returning the one true instance
 * to functions everywhere.
 *
 * @since   1.0.0
 * @access  public
 * @return  object The one true instance
 */
function ap_popular_posts() {
    return AP_Popular_Posts::instance();
}

// let's start
ap_popular_posts();
