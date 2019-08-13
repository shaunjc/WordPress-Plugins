<?php
/**
 * Plugin Name: Slick Design Core
 * Description: Power your website with Slick Design!
 * Author: Slick Design
 * Author URI: https://slickdesign.com.au/
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) die;

// Define plugin constants (file and directory)
define( 'SLICK_CORE_PLUGIN_FILE', __FILE__ );
define( 'SLICK_CORE_PLUGIN_DIR', dirname( __FILE__ ) );
define( 'SLICK_CORE_PLUGIN_CORE_DIR', SLICK_CORE_PLUGIN_DIR . '/core' );

// Include primary plugin class - automatically creates an instance
require_once SLICK_CORE_PLUGIN_CORE_DIR . '/class.slick-core.php';
