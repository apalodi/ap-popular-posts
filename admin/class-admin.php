<?php
/**
 * Admin functions, definitions and includes.
 *
 * @package     AP_Popular_Posts/Classes
 * @since       1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * AP_Popular_Posts_Admin Class.
 */
class AP_Popular_Posts_Admin {

    /**
     * Hook in methods.
     *
     * @since   1.0.0
     * @access  public
     */
    public function __construct() {
        add_filter( 'plugin_action_links_' . ap_popular_posts()->basename, array( $this, 'plugin_action_links' ) );
        add_action( 'admin_menu', array( $this, 'add_options_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_init', array( $this, 'add_settings' ) );
        add_action( 'admin_head', array( $this, 'admin_head' ) );
    }

    /**
     * Add style to head.
     *
     * @since   1.0.0
     * @access  public
     */
    public function admin_head() {
        $css = '
        .ap-popular-posts-max-width {
            max-width: 420px
        }
        .ap-popular-posts-notice {
            background: #fff;
            border-style: solid;
            border-width: 1px;
            border-left-width: 4px;
            border-top-color: #ccd0d4;
            border-right-color: #ccd0d4;
            border-bottom-color: #ccd0d4;
            box-shadow: 0 1px 1px rgb(0 0 0 / 4%);
            margin: 5px 0 15px;
            padding: 1px 12px;
        }
        .form-table .ap-popular-posts-notice p {
            margin: .5em 0;
            padding: 2px;
        }
        ';
        echo '<style>' . $css . '</style>';
    }

    /**
     * Show action links on the plugin screen.
     *
     * @since   1.0.0
     * @access  public
     * @param   array $links Plugin Action links
     * @return  array $links Plugin Action links
     */
    public function plugin_action_links( $links ) {
        $links['settings'] = '<a href="' . esc_url( admin_url( 'options-general.php?page=ap-popular-posts' ) ) . '" aria-label="'. esc_attr__( 'AP Popular Posts Settings', 'ap-popular-posts' ) .'">'. esc_attr__( 'Settings', 'ap-popular-posts' ) .'</a>';
        return $links;
    }

    /**
     * Add options page under "Settings".
     *
     * @since   1.0.0
     * @access  public
     */
    public function add_options_page() {
        add_options_page(
            esc_html__( 'AP Popular Posts Settings', 'ap-popular-posts' ),
            esc_html__( 'AP Popular Posts', 'ap-popular-posts' ),
            'manage_options',
            'ap-popular-posts',
            array( $this, 'create_options_page' )
        );
    }

    /**
     * Options page callback.
     *
     * @since   1.0.0
     * @access  public
     */
    public function create_options_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'AP Popular Posts Settings', 'ap-popular-posts' ); ?></h1>
            <form method="post" action="options.php">
            <?php
                settings_fields( 'ap_popular_posts_options' );
                do_settings_sections( 'ap-popular-posts-settings' );
                submit_button();
            ?>
            </form>
        </div>
        <?php
    }

    /**
     * Register settings.
     *
     * @since   1.0.0
     * @access  public
     */
    public function register_settings() {
        // 'string', 'boolean', 'integer', and 'number'

        register_setting(
            'ap_popular_posts_options',
            'ap_popular_posts_use_wp_query',
            array(
                'type' => 'boolean',
                'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
                'default' => false,
            )
        );

        register_setting(
            'ap_popular_posts_options',
            'ap_popular_posts_ajax_update_views',
            array(
                'type' => 'boolean',
                'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
                'default' => false,
            )
        );

        register_setting(
            'ap_popular_posts_options',
            'ap_popular_posts_ajax_refresh_fragments',
            array(
                'type' => 'boolean',
                'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
                'default' => false,
            )
        );

        register_setting(
            'ap_popular_posts_options',
            'ap_popular_posts_use_object_cache',
            array(
                'type' => 'boolean',
                'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
                'default' => false,
            )
        );

        register_setting(
            'ap_popular_posts_options',
            'ap_popular_posts_data_sampling_rate',
            array(
                'type' => 'number',
                'sanitize_callback' => array( $this, 'sanitize_number' ),
                'default' => 0,
            )
        );
    }

    /**
     * Add settings sections and fields.
     *
     * @since   1.0.0
     * @access  public
     */
    public function add_settings() {
        add_settings_section(
            'ap-popular-posts-section',
            '',
            '__return_false',
            'ap-popular-posts-settings'
        );

        add_settings_field(
            'use_wp_query',
            esc_html__( 'Query', 'ap-popular-posts' ),
            array( $this, 'wp_query_callback' ),
            'ap-popular-posts-settings',
            'ap-popular-posts-section'
        );

        add_settings_field(
            'ajax_update_views',
            esc_html__( 'Ajax', 'ap-popular-posts' ),
            array( $this, 'ajax_update_callback' ),
            'ap-popular-posts-settings',
            'ap-popular-posts-section'
        );

        add_settings_field(
            'use_object_cache',
            esc_html__( 'Object Cache', 'ap-popular-posts' ),
            array( $this, 'use_object_cache_callback' ),
            'ap-popular-posts-settings',
            'ap-popular-posts-section'
        );

        add_settings_field(
            'data_sampling_rate',
            esc_html__( 'Data Sampling (Experimental)', 'ap-popular-posts' ),
            array( $this, 'data_sampling_rate_callback' ),
            'ap-popular-posts-settings',
            'ap-popular-posts-section',
            array( 'label_for' => 'data_sampling_rate' )
        );
    }

    /**
     * Callback for setting ID wp_query_callback.
     *
     * @since   1.0.0
     * @access  public
     */
    public function wp_query_callback() {
        $is_multilingual_plugin = ap_popular_posts()->is_multilingual_plugin_active();
        $using_wp_query = ap_popular_posts()->query->using_wp_query();
        ?>
        <fieldset class="ap-popular-posts-max-width">
            <legend class="screen-reader-text"><span><?php esc_html_e( 'WP Query', 'ap-popular-posts' ); ?></span></legend>
            <label for="use_wp_query"><input name="ap_popular_posts_use_wp_query" type="checkbox" id="use_wp_query" <?php echo checked( $using_wp_query, 1 ); ?> <?php echo disabled( $is_multilingual_plugin, 1 ); ?> value="1"><?php esc_html_e( 'Use WP Query to get popular posts', 'ap-popular-posts' ); ?></label>

            <?php if ( $is_multilingual_plugin ) : ?>
                <div class="ap-popular-posts-notice notice-info">
                    <p class="description ap-popular-posts-max-width ap-popular-posts-info"><?php esc_html_e( "This option is enabled automatically because you are using a multilingual plugin that needs to use WP Query to get popular posts.", 'ap-popular-posts' ); ?></p>
                </div>
            <?php endif; ?>

            <p class="description ap-popular-posts-max-width"><?php esc_html_e( "By default we are using custom query to get popular posts that is optimized for speed. Enable this option if you have some issues with popular posts.", 'ap-popular-posts' ); ?></p>
         </fieldset>
        <?php
    }

    /**
     * Callback for setting ID ajax_update_views.
     *
     * @since   1.0.0
     * @access  public
     */
    public function ajax_update_callback() {
        $ajax_update = get_option( 'ap_popular_posts_ajax_update_views' );
        $ajax_refresh = get_option( 'ap_popular_posts_ajax_refresh_fragments' );
        ?>
        <fieldset class="ap-popular-posts-max-width">
            <legend class="screen-reader-text"><span><?php esc_html_e( 'Ajax', 'ap-popular-posts' ); ?></span></legend>
            <label for="ajax_update_views"><input name="ap_popular_posts_ajax_update_views" type="checkbox" id="ajax_update_views" <?php echo checked( $ajax_update, 1 ); ?> value="1"><?php esc_html_e( 'Use ajax to save posts views', 'ap-popular-posts' ); ?></label>
            <p class="description ap-popular-posts-max-width"><?php esc_html_e( "If you are using some cache plugin that is serving static html files you need to enable this option or your posts views won't be saved.", 'ap-popular-posts' ); ?></p>
            <br><br>
            <label for="ajax_refresh_fragments"><input name="ap_popular_posts_ajax_refresh_fragments" type="checkbox" id="ajax_refresh_fragments" <?php echo checked( $ajax_refresh, 1 ); ?> value="1"><?php esc_html_e( 'Use ajax to refresh fragments like widgets or other parts', 'ap-popular-posts' ); ?></label>
            <p class="description ap-popular-posts-max-width"><?php esc_html_e( 'If you are using some cache plugin you might want to enable this option but it could also be unnecessary if your cache time is less than 1 hour.', 'ap-popular-posts' ); ?></p>
        </fieldset>
        <?php
    }

    /**
     * Callback for setting ID use_object_cache.
     *
     * @since   1.0.0
     * @access  public
     */
    public function use_object_cache_callback() {
        $value = get_option( 'ap_popular_posts_use_object_cache' );
        ?>
        <fieldset class="ap-popular-posts-max-width">
            <legend class="screen-reader-text"><span><?php esc_html_e( 'Object Cache', 'ap-popular-posts' ); ?></span></legend>
            <label for="use_object_cache"><input name="ap_popular_posts_use_object_cache" type="checkbox" id="use_object_cache" <?php echo checked( $value, 1 ); ?> value="1"><?php esc_html_e( 'Use object cache to save views', 'ap-popular-posts' ); ?></label>
            <p class="description ap-popular-posts-max-width"><?php esc_html_e( 'On high traffic sites if you have some object cache solution installed you can use this option to save views in memory for 5 minutes and then transfer them to database to increase performance.', 'ap-popular-posts' ); ?></p>
            <br>
            <p class="description ap-popular-posts-max-width"><?php esc_html_e( "Please note that object cache isn't a reliable method of saving views as the cache can be cleared and the views for the last 5 minutes will be lost.", 'asona' ); ?></p>
        </fieldset>
        <?php
    }

    /**
     * Callback for setting ID data_sampling_rate.
     *
     * @since   1.0.0
     * @access  public
     */
    public function data_sampling_rate_callback() {
        $value = get_option( 'ap_popular_posts_data_sampling_rate' );
        ?>
        <input name="ap_popular_posts_data_sampling_rate" type="number" step="1" min="0" id="data_sampling_rate" value="<?php echo esc_attr( $value ); ?>" class="small-text"> <?php esc_html_e( 'Enter 0 to disable', 'ap-popular-posts' ); ?>
        <br><br>
        <p class="description ap-popular-posts-max-width"><?php esc_html_e( "On high traffic sites the constant writing to the database or object cache may have an impact on performance if your web server isn't great. A sampling rate of 80-100 is usually for high traffic sites, 40-80 for medium traffic sites and 10-40 for low traffic sites. If you don't see any performance issues make the number as low as you can. Even if you have high traffic site you can use e.g. 40 if you don't see any performance issues." ); ?></p>
        <br>
        <p class="description ap-popular-posts-max-width"><?php esc_html_e( 'Using this method not every post view is saved. It picks random number between 0 and sample rate number. If it picks the correct number then the views are increased by sampling rate. On high traffic site it should give accurate post views.', 'ap-popular-posts' ); ?></p>
        <?php
    }

    /**
     * Checkbox sanitization callback.
     *
     * @since   1.0.0
     * @access  public
     * @param   bool $checked Whether the checkbox is checked.
     * @return  bool Whether the checkbox is checked.
     */
    public function sanitize_checkbox( $checked ) {
        return $checked ? true : false;
    }

    /**
     * Sanitize number.
     *
     * @since   1.0
     * @access  public
     * @param   int     $number     Number to sanitize.
     * @return  int     $number     Sanitized number otherwise, the setting default.
     */
    public function sanitize_number( $number ) {
        $number = intval( $number );
        return $number > 0 ? $number : 0;
    }
}

new AP_Popular_Posts_Admin();
