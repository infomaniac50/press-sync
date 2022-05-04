<?php
/**
 * Press Sync
 *
 * @package PressSync
 * @author  Marcus Battle (marcus @webdevstudios .com), Zach Owen (zach @webdevstudios .com), Viacom
 * @license GPL
 * @wordpress-plugin
 * Plugin Name: Press Sync
 * Description: The easiest way to synchronize posts, media and users between two WordPress sites
 * Version: 0.9.2
 * License: GPL
 * Author: Marcus Battle, WebDevStudios, Viacom
 * Author URI: http://webdevstudios.com/
 * Text Domain: press-sync
 */

require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';

add_action( 'plugins_loaded', array( \Press_Sync\Press_Sync::init( __FILE__ ), 'hooks' ), 10, 1 );
