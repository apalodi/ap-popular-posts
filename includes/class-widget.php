<?php
/**
 * Widget API: AP_Popular_Posts_Widget class
 *
 * @package     AP_Popular_Posts/Widgets
 * @since       1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * AP_Popular_Posts_Widget class.
 */
class AP_Popular_Posts_Widget extends WP_Widget {

    /**
     * Sets up a new widget instance.
     *
     * @since   1.0.0
     * @access  public
     */
    public function __construct() {
        parent::__construct(
            'ap-popular-posts',
            esc_html__( 'AP Popular Posts', 'ap-popular-posts' ),
            array(
                'classname' => 'widget-ap-popular-posts',
                'description' => esc_html__( 'Most popular Posts.', 'ap-popular-posts' ),
                'customize_selective_refresh' => true,
            )
        );
    }

    /**
     * Outputs the content for the current widget instance.
     *
     * @since   1.0.0
     * @access  public
     * @param   array   $args       Widgets arguments
     * @param   array   $instance   Settings for the current widget instance
     */
    public function widget( $args, $instance ) {

        if ( ! isset( $args['widget_id'] ) ) {
            $args['widget_id'] = $this->id;
        }

        $title = ( ! empty( $instance['title'] ) ) ? $instance['title'] : __( 'Popular Posts', 'ap-popular-posts' );
        $title = apply_filters( 'widget_title', $title, $instance, $this->id_base );

        $number = ( ! empty( $instance['number'] ) ) ? absint( $instance['number'] ) : 4;
        $interval = ( ! empty( $instance['interval'] ) ) ? absint( $instance['interval'] ) : 3;
        $intervals = ap_popular_posts()->get_intervals();

        if ( ! $number ) {
            $number = 4;
        }

        if ( $number > 12 ) {
            $number = 12;
        }

        if ( ! $interval ) {
            $interval = 3;
        }

        if ( ! in_array( $interval, array_keys( $intervals ), true ) ) {
            $interval = 3;
        }

        $query = new WP_Query( apply_filters( 'ap_popular_posts_widget_query_args', array(
            'post_type' => 'post',
            'posts_per_page'=> $number,
            'post_status' => 'publish',
            'ap_popular_posts' => true,
            'ap_popular_posts_interval' => $interval,
            'suppres_filters' => false,
            'no_found_rows' => true,
            'ignore_sticky_posts' => true,
        ), $instance, $number, $interval ) );

        $options = array(
            'widget_id' => $this->number,
        );

        echo $args['before_widget'];

        if ( $title ) {
            echo $args['before_title'] . wp_kses_post( $title ) . $args['after_title'];
        }

        ?>

        <div class="ap-popular-posts-widget-content" data-options='<?php echo wp_json_encode( $options ) ?>'>
            <?php ap_popular_posts_widget_content( array( 'query' => $query ) ); ?>
        </div>

        <?php echo $args['after_widget'];

        wp_reset_postdata();
    }

    /**
     * Handles updating the settings for the current widget instance.
     *
     * @since   1.0.0
     * @access  public
     * @param   array   $new_instance     New settings for this instance.
     * @param   array   $old_instance     Old settings for this instance.
     * @return  array   $instance         Updated settings to save.
     */
    public function update( $new_instance, $old_instance ) {

        $instance = $old_instance;
        $number = absint( $new_instance['number'] );
        $interval = absint( $new_instance['interval'] );
        $intervals = ap_popular_posts()->get_intervals();

        if ( $number > 12 ) {
            $number = 12;
        }

        if ( ! in_array( $interval, array_keys( $intervals ), true ) ) {
            $interval = 3;
        }

        $instance['title'] = sanitize_text_field( $new_instance['title'] );
        $instance['number'] = $number;
        $instance['interval'] = $interval;

        return $instance;
    }

    /**
     * Outputs the settings form for the widget.
     *
     * @since   1.0.0
     * @access  public
     * @param   array $instance Current settings.
     */
    public function form( $instance ) {

        $title = isset( $instance['title'] ) ? $instance['title'] : '';
        $number = isset( $instance['number'] ) ? absint( $instance['number'] ) : 4;
        $interval = isset( $instance['interval'] ) ? absint( $instance['interval'] ) : 3;
        $intervals = ap_popular_posts()->get_intervals();

        if ( ! in_array( $interval, array_keys( $intervals ), true ) ) {
            $interval = 3;
        }

        ?>

        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'ap-popular-posts' ); ?></label>
            <input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
        </p>

        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'number' ) ); ?>"><?php esc_html_e( 'Number of posts to show:', 'ap-popular-posts' ); ?></label>
            <input class="tiny-text" id="<?php echo esc_attr( $this->get_field_id( 'number' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'number' ) ); ?>" type="number" step="1" min="1" max="12" value="<?php echo esc_attr( $number ); ?>" size="3" />
        </p>

        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'interval' ) ); ?>"><?php esc_html_e( 'Interval:', 'ap-popular-posts' ); ?></label>
            <select name="<?php echo esc_attr( $this->get_field_name( 'interval' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'interval' ) ); ?>" class="widefat">
                <?php foreach ( $intervals as $key => $title ) {
                    ?>
                    <option value="<?php echo esc_attr( $key ); ?>"<?php selected( $interval, $key ); ?>><?php echo esc_html( $title ); ?></option>
                    <?php
                }
                ?>
            </select>
        </p>

        <?php
    }
}
