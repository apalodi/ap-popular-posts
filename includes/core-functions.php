<?php
/**
 * Core Functions.
 *
 * @package     AP_Popular_Posts/Functions
 * @since       1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Load a template part with passing arguments.
 *
 * Makes it easy for a theme to reuse sections of code in a easy to overload way
 * for child themes.
 *
 * @since   1.0
 * @access  public
 * @param   string  $slug   The slug name for the generic template.
 * @param   array   $args   Pass args with the template load.
 */
function ap_popular_posts_get_template( $slug, $args = array() ) {

    $template_path = ap_popular_posts()->get_template_path();
    $templates = array( "{$template_path}/{$slug}.php" );
    $template = locate_template( $templates, false, false );

    if ( ! $template ) {
        $template = ap_popular_posts()->get_plugin_path() . '/templates/' . $slug . '.php';
    }

    include( $template );
}

/**
 * Define a constant if it is not already defined.
 *
 * @since   1.0.0
 * @access  public
 * @param   string  $name   Constant name.
 * @param   mixed   $value  Value.
 */
function ap_popular_posts_define_constant( $name, $value ) {
    if ( ! defined( $name ) ) {
        define( $name, $value );
    }
}

/**
 * Get the value of a transient.
 *
 * A better way of handling transients that executes fewer SQL queries.
 *
 * @since   1.0.0
 * @access  public
 * @param   string  $transient  Transient name.
 * @return  mixed   $value      Value of transient or false.
 */
function ap_popular_posts_get_transient( $transient, $use_old_value = false ) {

    $transient = 'ap_popular_posts_' . $transient;
    $value = false;

    /**
     * Filters the value of an existing transient.
     *
     * The dynamic portion of the hook name, `$transient`, refers to the transient name.
     *
     * Passing a truthy value to the filter will effectively short-circuit retrieval
     * of the transient, returning the passed value instead.
     *
     * @since   1.0.0
     * @param   mixed   $pre_transient  The default value to return if the transient does not exist.
     *                                  Any value other than false will short-circuit the retrieval
     *                                  of the transient, and return the returned value.
     * @param   string  $transient      Transient name.
     */
    $pre = apply_filters( "pre_transient_{$transient}", false, $transient );

    if ( false !== $pre ) {
        return $pre;
    }

    if ( wp_using_ext_object_cache() ) {

        $value = wp_cache_get( $transient, 'transient' );

        if ( isset( $value['timeout'] ) ) {
            wp_cache_delete( $transient, 'transient' );
            $value = $value['value'];
        }

    } else {

        $transient_option = '_transient_' . $transient;
        $transient_value = get_option( $transient_option );

        if ( isset( $transient_value['timeout'] ) ) {

            $timeout = $transient_value['timeout'];

            if ( $timeout > time() ) {
                $value = $transient_value['value'];
            }
        }
    }

    /**
     * Filters an existing transient's value.
     *
     * The dynamic portion of the hook name, `$transient`, refers to the transient name.
     *
     * @since   1.0.0
     * @param   mixed   $value      Value of transient.
     * @param   string  $transient  Transient name.
     */
    return apply_filters( "transient_{$transient}", $value, $transient );
}

/**
 * Set/update the value of a transient.
 *
 * A better way of handling transients that executes fewer SQL queries.
 *
 * @since   1.0.0
 * @access  public
 * @param   string  $transient      Transient name. Must be 172 characters or fewer in length.
 * @param   mixed   $value          Transient value.
 * @param   int     $expiration     Optional. Time until expiration in seconds. Default 0 (no expiration).
 * @return  bool    $result         False if value was not set and true if value was set.
 */
function ap_popular_posts_set_transient( $transient, $value, $expiration = 0 ) {

    $transient = 'ap_popular_posts_' . $transient;
    $expiration = (int) $expiration;

    /**
     * Filters a specific transient before its value is set.
     *
     * The dynamic portion of the hook name, `$transient`, refers to the transient name.
     *
     * @since   1.0.0
     * @param   mixed   $value          New value of transient.
     * @param   int     $expiration     Time until expiration in seconds.
     * @param   string  $transient      Transient name.
     */
    $value = apply_filters( "pre_set_transient_{$transient}", $value, $expiration, $transient );

    /**
     * Filters the expiration for a transient before its value is set.
     *
     * The dynamic portion of the hook name, `$transient`, refers to the transient name.
     *
     * @since   1.0.0
     * @param   int     $expiration     Time until expiration in seconds. Use 0 for no expiration.
     * @param   mixed   $value          New value of transient.
     * @param   string  $transient      Transient name.
     */
    $expiration = apply_filters( "expiration_of_transient_{$transient}", $expiration, $value, $transient );

    if ( wp_using_ext_object_cache() ) {

        $result = wp_cache_set( $transient, $value, 'transient', $expiration );

    } else {

        $transient_option = '_transient_' . $transient;
        $transient_value = array(
            'timeout' => time() + $expiration,
            'value' => $value
        );

        $result = update_option( $transient_option, $transient_value );
    }

    if ( $result ) {

        /**
         * Fires after the value for a specific transient has been set.
         *
         * The dynamic portion of the hook name, `$transient`, refers to the transient name.
         *
         * @since   1.0.0
         * @param   mixed   $value          Transient value.
         * @param   int     $expiration     Time until expiration in seconds.
         * @param   string  $transient      The name of the transient.
         */
        do_action( "set_transient_{$transient}", $value, $expiration, $transient );

        /**
         * Fires after the value for a transient has been set.
         *
         * @since   1.0.0
         * @param   string  $transient      The name of the transient.
         * @param   mixed   $value          Transient value.
         * @param   int     $expiration     Time until expiration in seconds.
         */
        do_action( 'setted_transient', $transient, $value, $expiration );
    }

    return $result;
}

/**
 * Delete a transient.
 *
 * A better way of handling transients that executes fewer SQL queries.
 *
 * @since   1.0.0
 * @access  public
 * @param   string  $transient  Transient name.
 * @return  bool    $result     True if successful, false otherwise
 */
function ap_popular_posts_delete_transient( $transient ) {

    $transient = 'ap_popular_posts_' . $transient;

    /**
     * Fires immediately before a specific transient is deleted.
     *
     * The dynamic portion of the hook name, `$transient`, refers to the transient name.
     *
     * @since   1.0.0
     * @param   string $transient Transient name.
     */
    do_action( "delete_transient_{$transient}", $transient );

    if ( wp_using_ext_object_cache() ) {
        $result = wp_cache_delete( $transient, 'transient' );
    } else {
        $option = '_transient_' . $transient;
        $result = delete_option( $option );
    }

    if ( $result ) {

        /**
         * Fires after a transient is deleted.
         *
         * @since   1.0.0
         * @param   string $transient Deleted transient name.
         */
        do_action( 'deleted_transient', $transient );
    }

    return $result;
}

/**
 * Delete all transients.
 *
 * @since   1.0.0
 * @access  public
 */
function ap_popular_posts_delete_transients() {
    AP_Popular_Posts_Data::delete_transients();
}

/**
 * Get popular posts ids.
 *
 * @since   1.0.0
 * @access  public
 * @param   int     $interval   Interval in days
 * @param   int     $number     Number of posts
 * @return  array   $ids        IDs
 */
function ap_popular_posts_get_ids( $interval, $number ) {
    return ap_popular_posts()->query->get_ids( $interval, $number );
}
