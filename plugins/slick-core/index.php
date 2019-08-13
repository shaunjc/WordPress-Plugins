<?php
/**
 * WordPress is not initialised.
 * Redirect to home URL.
 */
if ( ! defined( 'ABSPATH' ) ) {
    try {
        // Find wp-load.php
        $dir = dirname( __FILE__ );
        $ignore = array( '', DIRECTORY_SEPARATOR );
        while ( $dir && ! @file_exists( $dir . '/wp-load.php' ) && ! in_array( $dir, $ignore ) ) {
            $dir = dirname( $dir );
        }
        if ( @file_exists( $dir . '/wp-load.php' ) ) {
            // Load WordPress core files and redirect home.
            require_once $dir . '/wp-load.php';
            wp_safe_redirect( home_url() );
        }
    } catch (Exception $ex) {
        // Ignore errors.
    }
    die;
}
