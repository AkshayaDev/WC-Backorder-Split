<?php
/*
Plugin Name: WC Backorder Split
Plugin URI: https://github.com/AkshayaDev/WC-Backorder-Split
Description: A simple plugin that helps you split the WooCommerce order for the products that you do not have in stock.
Author: Akshaya Swaroop
Version: 1.0
Author URI: https://github.com/AkshayaDev
Requires at least: 4.9
Tested up to: 5.0.3
Text Domain: wc_backorder_split
Domain Path: /languages/
*/

if(!defined('ABSPATH')) exit; // Exit if accessed directly
if ( ! class_exists( 'WC_Dependencies_Backorder_Split', false ) ) {
    require_once( dirname( __FILE__ ) . '/includes/class-wc-backorder-split-dependencies.php');
}

require_once(dirname(__FILE__).'/config.php');
if(!defined('WC_BACKORDER_SPLIT_PLUGIN_TOKEN')) exit;
if(!defined('WC_BACKORDER_SPLIT_TEXT_DOMAIN')) exit;

if(!class_exists('WC_Backorder_Split') && WC_Dependencies_Backorder_Split::is_woocommerce_active()) {
    if(WC_Dependencies_Backorder_Split::wcbs_get_woocommerce_version() >= 3.0) {
    	require_once(dirname(__FILE__).'/classes/class-wc-backorder-split.php');
    	global $WC_Backorder_Split;
    	$WC_Backorder_Split = new WC_Backorder_Split( __FILE__ );
    	$GLOBALS['WC_Backorder_Split'] = $WC_Backorder_Split;       
    }else{
        add_action('admin_notices', 'wcbs_required_woocommerce_version_notice');
        if(!function_exists('wcbs_required_woocommerce_version_notice')) {
            function wcbs_required_woocommerce_version_notice() { ?>
                <div class="error">
                    <p><?php _e('WC Backorder Split plugin requires at least WooCommerce 3.0', WC_BACKORDER_SPLIT_TEXT_DOMAIN); ?></p>
                </div>
            <?php }
        }
    }
}else {
    add_action('admin_notices', 'wcbs_admin_notice');
    if (!function_exists('wcbs_admin_notice')) {
        function wcbs_admin_notice() {
        ?>
        <div class="error">
            <p><?php _e('WC Backorder Split plugin requires <a href="http://wordpress.org/extend/plugins/woocommerce/">WooCommerce</a> plugins to be active!', WC_BACKORDER_SPLIT_TEXT_DOMAIN); ?></p>
        </div>
        <?php
        }
    }    
}?>