<?php
/**
 * Plugin Dependency Checker
 *
 */
class WC_Dependencies_Backorder_Split {
	private static $active_plugins;
	static function init() {
		self::$active_plugins = (array) get_option( 'active_plugins', array() );
		if ( is_multisite() )
			self::$active_plugins = array_merge( self::$active_plugins, get_site_option( 'active_sitewide_plugins', array() ) );
	}
	/**
     * Check woocommerce exist
     * @return Boolean
     */
    public static function woocommerce_active_check() {
        if (!self::$active_plugins)
            self::init();
        return in_array('woocommerce/woocommerce.php', self::$active_plugins) || array_key_exists('woocommerce/woocommerce.php', self::$active_plugins);
    }
    /**
     * Check if woocommerce active
     * @return Boolean
     */
    public static function is_woocommerce_active() {
        return self::woocommerce_active_check();
    }
    
    public static function wcbs_get_woocommerce_version(){
        if ( ! function_exists( 'get_plugins' ) )
            require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
        
        $plugin_folder = get_plugins( '/' . 'woocommerce' );
        $plugin_file = 'woocommerce.php';
        
        if ( isset( $plugin_folder[$plugin_file]['Version'] ) ) {
            return $plugin_folder[$plugin_file]['Version'];
        
        } else {
            return NULL;
        }
    }
}