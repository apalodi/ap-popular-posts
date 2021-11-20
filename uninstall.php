<?php
/**
 * AP Popular Posts Uninstall.
 *
 * Uninstalling AP Popular Posts deletes tables and options.
 *
 * @package     AP_Popular_Posts/Uninstaller
 * @version     1.0
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit; // Exit if uninstall not called from WordPress

include_once dirname( __FILE__ ) . '/includes/class-data.php';

AP_Popular_Posts_Data::uninstall();
