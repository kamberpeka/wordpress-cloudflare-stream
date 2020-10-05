<?php
/**
 * @package Video Uploader
 */
/*
Plugin Name: Video Uploader
Plugin URI: http://proaudio.course
Description: Use this plugin to upload large videos.
Version: 1.0.0
Author: Kamber Peka
Author URI: https://github.com/kamberpeka
License: GPLv2 or later
Text Domain: proaudio
*/

// Make sure we don't expose any info if called directly
if ( !function_exists( 'add_action' ) ) {
    echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
    exit;
}

define( 'VU_VERSION', '1.0.0' );
define( 'VU_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require_once( VU_PLUGIN_DIR . 'VideoUploader.php' );
require_once( VU_PLUGIN_DIR . 'SettingsPage.php' );
require_once( VU_PLUGIN_DIR . 'VideoRepository.php' );

register_activation_hook( __FILE__, array( 'VideoUploader', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'VideoUploader', 'deactivate' ) );

add_action( 'init', array( 'VideoUploader', 'init' ) );

function get_video($id) {

    return VideoRepository::find($id);
}

