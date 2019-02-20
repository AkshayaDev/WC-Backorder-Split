<?php

if(!defined('ABSPATH')) exit; // Exit if accessed directly

class WC_Backorder_Split {

	public $plugin_url;

	public $plugin_path;

	public $version;

	public $token;
	
	public $text_domain;

	public $frontend;

	private $file;

	public function __construct($file) {

		$this->file = $file;
		$this->plugin_url = trailingslashit(plugins_url('', $plugin = $file));
		$this->plugin_path = trailingslashit(dirname($file));
		$this->token = WC_BACKORDER_SPLIT_PLUGIN_TOKEN;
		$this->text_domain = WC_BACKORDER_SPLIT_TEXT_DOMAIN;
		$this->version = WC_BACKORDER_SPLIT_PLUGIN_VERSION;
		
		add_action('init', array(&$this, 'wcbs_init'), 0);
		add_action( 'admin_enqueue_scripts', array( $this, 'wcbs_admin_styles' ) );		

		// Init Frontend
		if ( ! class_exists( 'WC_Backorder_Split_Frontend' ) ) {
			$this->wcbs_load_class('frontend');
			$this->frontend = new WC_Backorder_Split_Frontend();
		}

	}
	
	/**
	 * initilize plugin on WP init
	 */
	function wcbs_init() {		
		
		// Init Text Domain
		$this->wcbs_load_plugin_textdomain();
	}
	
/**
   * Load Localisation files.
   *
   * Note: the first-loaded translation file overrides any following ones if the same translation is present
   *
   * @access public
   * @return void
   */
  public function wcbs_load_plugin_textdomain() {
    $locale = apply_filters( 'plugin_locale', get_locale(), $this->token );

    load_textdomain( $this->text_domain, WP_LANG_DIR . "/wc-backorder-split/wc-backorder-split-$locale.mo" );
    load_textdomain( $this->text_domain, $this->plugin_path . "/languages/wc-backorder-split-$locale.mo" );
  }

	public function wcbs_load_class($class_name = '') {
		if ('' != $class_name && '' != $this->token) {
			require_once ('class-' . esc_attr($this->token) . '-' . esc_attr($class_name) . '.php');
		} // End If Statement
	}// End wcbs_load_class()
	
	/** Cache Helpers *********************************************************/

	/**
	 * Sets a constant preventing some caching plugins from caching a page. Used on dynamic pages
	 *
	 * @access public
	 * @return void
	 */
	public function wcbs_nocache() {
		if (!defined('DONOTCACHEPAGE'))
			define("DONOTCACHEPAGE", "true");
		// WP Super Cache constant
	}

	/**
	 * Enqueue styles.
	 */
	public function wcbs_admin_styles() {
		wp_enqueue_style($this->token.'-admin-css',  $this->plugin_url.'assets/admin/css/admin.css', array(), $this->version);
	}

}