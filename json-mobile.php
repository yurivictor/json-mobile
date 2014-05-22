<?php
/**
 * JSON Mobile Feed
 *
 * A plugin for Washington post mobile apps that creates a JSON endpoint for every article
 *
 * @package   JSON_Mobile_Feed
 * @author    Yuri Victor <yurivictor@gmail.com>
 * @license   GPL-2.0+
 * @link      http://www.washingtonpost.com/
 * @copyright 2013 Yuri Victor
 *
 * @wordpress-plugin
 * Plugin Name:       JSON Mobile Feed
 * Plugin URI:        http://www.washingtonpost.com/
 * Description:       A plugin for Washington post mobile apps that creates a JSON endpoint for every article
 * Version:           0.0.1
 * Author:            Yuri Victor
 * Author URI:        
 * Text Domain:       JSON_Mobile
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Domain Path:       /languages
 * GitHub Plugin URI: 
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/*----------------------------------------------------------------------------*
 * Public-Facing Functionality
 *----------------------------------------------------------------------------*/

require_once( plugin_dir_path( __FILE__ ) . 'public/class-json-mobile.php' );

/*
 * Register hooks that are fired when the plugin is activated or deactivated.
 * When the plugin is deleted, the uninstall.php file is loaded.
 */
register_activation_hook( __FILE__, array( 'JSON_Mobile_Feed', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'JSON_Mobile_Feed', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'JSON_Mobile_Feed', 'get_instance' ) );