<?php
/**
 * Data related functions and actions.
 *
 * @package     AP_Popular_Posts/Classes
 * @since       1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * AP_Popular_Posts_Data Class.
 */
class AP_Popular_Posts_Data {

    /**
     * DB updates and callbacks that need to be run per version.
     *
     * @var array
     */
    private static $db_updates = array(
        '1.2.0' => 'update_data_120',
    );

    /**
     * Hook in methods.
     *
     * @since   1.0.0
     * @access  public
     */
    public static function init() {
        add_action( 'admin_init', array( __CLASS__, 'update' ) );
        add_action( 'wpmu_new_blog', array( __CLASS__, 'wpmu_new_blog' ) );
        add_filter( 'wpmu_drop_tables', array( __CLASS__, 'wpmu_drop_tables' ) );
        // add_action( 'delete_blog', array( __CLASS__, 'delete_blog_tables' ) );
    }

    /**
     * Install plugin.
     *
     * @since   1.0.0
     * @access  public
     * @param   bool $network_wide
     */
    public static function install( $network_wide ) {

        if ( is_multisite() && $network_wide ) {
            global $wpdb;

            $current_blog_id = get_current_blog_id();
            $blog_ids = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );

            foreach ( $blog_ids as $blog_id ) {
                switch_to_blog( $blog_id );
                self::create_data();
            }

            /**
             * Switch back to the current blog. Probably preferable, as it is not a hack.
             *
             * @see https://codex.wordpress.org/Function_Reference/restore_current_blog
             */
            switch_to_blog( $current_blog_id );
            $GLOBALS['_wp_switched_stack'] = array();
            $GLOBALS['switched'] = FALSE;

        } else {
            self::create_data();
        }

        // flush_rewrite_rules();
    }

    /**
     * Uninstall plugin.
     *
     * @since   1.0.0
     * @access  public
     */
    public static function uninstall() {

        if ( is_multisite() ) {
            global $wpdb;

            $current_blog_id = get_current_blog_id();
            $blog_ids = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );

            foreach ( $blog_ids as $blog_id ) {
                switch_to_blog( $blog_id );
                self::delete_data();
            }

            /**
             * Switch back to the current blog. Probably preferable, as it is not a hack.
             *
             * @see https://codex.wordpress.org/Function_Reference/restore_current_blog
             */
            switch_to_blog( $current_blog_id );
            $GLOBALS['_wp_switched_stack'] = array();
            $GLOBALS['switched'] = FALSE;

        } else {
            self::delete_data();
        }

        // Clear any cached data that has been removed.
        wp_cache_flush();
    }

    /**
     * Update plugin.
     *
     * @since   1.2.0
     * @access  public
     */
    public static function update() {

        if ( is_multisite() ) {
            global $wpdb;

            $current_blog_id = get_current_blog_id();
            $blog_ids = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );

            foreach ( $blog_ids as $blog_id ) {
                switch_to_blog( $blog_id );
                self::update_data();
            }

            /**
             * Switch back to the current blog. Probably preferable, as it is not a hack.
             *
             * @see https://codex.wordpress.org/Function_Reference/restore_current_blog
             */
            switch_to_blog( $current_blog_id );
            $GLOBALS['_wp_switched_stack'] = array();
            $GLOBALS['switched'] = FALSE;

        } else {
            self::update_data();
        }

    }

    /**
     * Create data.
     *
     * @since   1.0.0
     * @access  private
     */
    private static function create_data() {
        self::create_tables();
        self::create_options();
    }

    /**
     * Delete data.
     *
     * @since   1.0.0
     * @access  private
     */
    private static function delete_data() {
        self::drop_tables();
        self::delete_options();
        self::delete_transients();
    }

    /**
     * Update data.
     *
     * @since   1.2.0
     * @access  private
     */
    private static function update_data() {
        if ( self::needs_db_update() ) {
            $current_db_version = self::get_current_db_version();
            $db_updates = self::get_db_update_callbacks();

            foreach ( $db_updates as $version => $callback ) {
                if ( version_compare( $current_db_version, $version, '<' ) ) {
                    call_user_func( array( __CLASS__, $callback ) );
                }
            }

            self::update_db_version();
        }
    }

    /**
     * Get list of DB update callbacks.
     *
     * @since   1.2.0
     * @return  array
     * @access  private
     */
    private static function get_db_update_callbacks() {
        return self::$db_updates;
    }

    /**
     * Update for version 1.2.0.
     *
     * @since   1.2.0
     * @access  private
     */
    private static function update_data_120() {
        global $wpdb;

        add_option( 'ap_popular_posts_use_wp_query', false );
        $wpdb->query( "ALTER TABLE {$wpdb->prefix}ap_popular_posts ADD KEY post_time (post_id, view_time);" );
    }

    /**
     * Sets up the default options.
     *
     * @since   1.0.0
     * @access  private
     */
    private static function create_options() {
        add_option( 'ap_popular_posts_use_wp_query', false );
        add_option( 'ap_popular_posts_ajax_update_views', false );
        add_option( 'ap_popular_posts_ajax_refresh_fragments', false );
        add_option( 'ap_popular_posts_use_object_cache', false );
        add_option( 'ap_popular_posts_data_sampling_rate', '0' );
        add_option( 'ap_popular_posts_db_version', ap_popular_posts()->get_db_version() );
    }

    /**
     * Delete plugin options.
     *
     * @since   1.0.0
     * @access  private
     */
    private static function delete_options() {
        delete_option( 'ap_popular_posts_ajax_update_views' );
        delete_option( 'ap_popular_posts_ajax_refresh_fragments' );
        delete_option( 'ap_popular_posts_use_object_cache' );
        delete_option( 'ap_popular_posts_data_sampling_rate' );
        delete_option( 'ap_popular_posts_db_version' );
    }

    /**
     * Is a DB update needed?
     *
     * @since   1.2.0
     * @access  private
     * @return  boolean
     */
    protected static function needs_db_update() {
        $current_db_version = get_option( 'ap_popular_posts_db_version', null );
        $updates = self::get_db_update_callbacks();
        $update_versions = array_keys( $updates );
        usort( $update_versions, 'version_compare' );

        return ! is_null( $current_db_version ) && version_compare( $current_db_version, end( $update_versions ), '<' );
    }

    /**
     * Update DB version to current.
     *
     * @since   1.0.0
     * @access  private
     */
    private static function update_db_version() {
        update_option( 'ap_popular_posts_db_version', ap_popular_posts()->get_db_version() );
    }

    /**
     * Get current DB version.
     *
     * @since   1.2.0
     * @access  private
     */
    private static function get_current_db_version() {
        return get_option( 'ap_popular_posts_db_version', ap_popular_posts()->get_db_version() );
    }

    /**
     * Set up the database tables which the plugin needs to function.
     *
     * @since   1.0.0
     * @access  private
     */
    private static function create_tables() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( self::get_schema() );
    }

    /**
     * Retrieve the SQL for creating database tables.
     *
     * Cache table is created for faster INSERTs and main table for faster SELECTs.
     *
     * @since   1.0.0
     * @access  private
     * @return  string The SQL needed to create the requested tables.
     */
    private static function get_schema() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $max_index_length = 191;

        $sql = "
CREATE TABLE {$wpdb->prefix}ap_popular_posts (
  ID bigint(20) unsigned NOT NULL auto_increment,
  post_id bigint(20) unsigned NOT NULL,
  view_time int(10) unsigned NOT NULL,
  PRIMARY KEY (ID),
  KEY time_post (view_time, post_id),
  KEY post_time (post_id, view_time)
) $charset_collate;
CREATE TABLE {$wpdb->prefix}ap_popular_posts_cache (
  ID bigint(20) unsigned NOT NULL auto_increment,
  post_id bigint(20) unsigned NOT NULL,
  view_time int(10) unsigned NOT NULL,
  PRIMARY KEY (ID)
) $charset_collate;
        ";

        return $sql;
    }

    /**
     * Return a list of tables. Used to make sure all tables are dropped when uninstalling the plugin
     * in a single site or multi site environment.
     *
     * @since   1.0.0
     * @access  private
     * @return  array $tables.
     */
    private static function get_tables() {
        global $wpdb;

        $tables = array(
            "{$wpdb->prefix}ap_popular_posts",
            "{$wpdb->prefix}ap_popular_posts_cache",
        );

        return $tables;
    }

    /**
     * Drop tables.
     *
     * @since   1.0.0
     * @access  private
     */
    private static function drop_tables() {
        global $wpdb;

        $tables = self::get_tables();

        foreach ( $tables as $table ) {
            $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
        }
    }

    /**
     * Delete transients.
     *
     * @since   1.0.0
     * @access  public
     */
    public static function delete_transients() {
        global $wpdb;

        $like = $wpdb->esc_like( '_transient_ap_popular_posts_' ) . '%';

        $wpdb->query(
            $wpdb->prepare(
                "
                DELETE FROM {$wpdb->options}
                WHERE option_name LIKE %s
                ",
                $like
            )
        );
    }

    /**
     * When a new blog is created.
     *
     * @since   1.0.0
     * @access  public
     * @param   int $blog_id Blog ID of the created blog.
     */
    public static function wpmu_new_blog( $blog_id ) {
        if ( is_plugin_active_for_network( 'ap-popular-posts/ap-popular-posts.php' ) ) {
            switch_to_blog( $blog_id );
            self::create_data();
            restore_current_blog();
        }
    }

    /**
     * Uninstall tables when MU blog is deleted.
     *
     * @since   1.0.0
     * @access  public
     * @param   array $tables List of tables that will be deleted by WP.
     * @return  array
     */
    public static function wpmu_drop_tables( $tables ) {
        return array_merge( $tables, self::get_tables() );
    }

    /**
     * When a site is deleted.
     *
     * @since   1.0.0
     * @access  public
     * @param   int     $blog_id    The site ID.
     * @param   bool    $drop       True if site's table should be dropped. Default is false.
     */
    public static function delete_blog_tables( $blog_id, $drop ) {
        if ( $drop ) {
            switch_to_blog( $blog_id );
            self::delete_data();
            restore_current_blog();
        }
    }

}

AP_Popular_Posts_Data::init();
