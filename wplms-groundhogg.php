<?php
/*
Plugin Name: WPLMS GroundHogg
plugin URI: http://www.vibethemes.com/
Description: This Plugin Intergrates with the GroundHogg.
Version: 1.0
Author: Mr.Vibe
Author URI: http://www.vibethemes.com/
Text Domain: wplms-groundhogg
Domain Path: /languages/
Copyright 2016 VibeThemes  (email: vibethemes@gmail.com) 
*/
if(!defined('ABSPATH'))
exit;

define('WPLMS_GROUNDHOGG_VERSION','1.0');
define('WPLMS_GROUNDHOGG_OPTION','wplms_groundhogg');

include_once 'includes/class.updater.php';
include_once 'includes/class.config.php';
include_once 'includes/class.groundhogg.php';
include_once 'includes/class.admin.php';
include_once 'includes/class.init.php';

add_action('plugins_loaded','wplms_groundhogg_translations');
function wplms_groundhogg_translations(){
    $locale = apply_filters("plugin_locale", get_locale(), 'wplms-groundhogg');
    $lang_dir = dirname( __FILE__ ) . '/languages/';
    $mofile        = sprintf( '%1$s-%2$s.mo', 'wplms-groundhogg', $locale );
    $mofile_local  = $lang_dir . $mofile;
    $mofile_global = WP_LANG_DIR . '/plugins/' . $mofile;

    if ( file_exists( $mofile_global ) ) {
        load_textdomain( 'wplms-groundhogg', $mofile_global );
    } else {
        load_textdomain( 'wplms-groundhogg', $mofile_local );
    }  
}


function Wplms_GroundHogg_Plugin_updater() {
    $license_key = trim( get_option( 'wplms_groundhogg_license_key' ) );
    $edd_updater = new Wplms_GroundHogg_Plugin_Updater( 'https://wplms.io', __FILE__, array(
            'version'   => WPLMS_GROUNDHOGG_VERSION,               
            'license'   => $license_key,        
            'item_name' => 'WPLMS GetRespose',    
            'author'    => 'VibeThemes' 
        )
    );
}
add_action( 'admin_init', 'Wplms_GroundHogg_Plugin_updater', 0 );
