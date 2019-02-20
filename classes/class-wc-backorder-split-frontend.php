<?php

if(!defined('ABSPATH')) exit; // Exit if accessed directly

class WC_Backorder_Split_Frontend {

	public function __construct() {
		add_action('woocommerce_checkout_order_processed', array($this,'review_and_split_order'), 10, 3);
		add_filter('wc_order_statuses', array($this,'add_backorder_order_status'));

		add_action( 'woocommerce_order_status_pending', array($this,'update_backorder_order_status'), 99, 1 );
		add_action( 'woocommerce_order_status_completed', array($this,'update_backorder_order_status'), 99, 1 );
		add_action( 'woocommerce_order_status_processing', array($this,'update_backorder_order_status'), 99, 1 );
		add_action( 'woocommerce_order_status_on-hold', array($this,'update_backorder_order_status') );
		add_action( 'woocommerce_order_status_cancelled', array($this,'update_backorder_order_status'), 99, 1 );

		add_filter( 'woocommerce_register_shop_order_post_statuses', array($this,'wcbs_register_new_order_status'));

		add_filter( 'bulk_actions-edit-shop_order', array($this,'wcbs_get_custom_order_status_bulk'));

	}

	public function order_contains_backorder_products($order_id) {
		if ( ! $order = wc_get_order( $order_id ) ) {
			return false;
		}

		$order_items = $order->get_items( apply_filters( 'woocommerce_purchase_order_item_types', 'line_item' ) );

		foreach ( $order_items as $item_id => $item ) {
			$product = $item->get_product();

			if ( $product && $product->is_on_backorder( $item->get_quantity() ) ) {
				return true; break;
			}
		}		

		return false;
	}

	public function create_backorder($parent_order_id) {

		if ( ! $parent_order = wc_get_order( $parent_order_id ) ) {
			return false;
		}

		$default_args = array(
			'status'        => 'backordered',
			'customer_id'   => $parent_order->get_customer_id(),
			'customer_note' => $parent_order->get_customer_note(),
			'parent'        => $parent_order_id,
			'created_via'   => 'wc-backorder-split-plugin',
			'cart_hash'     => null,
			'order_id'      => 0,
		);		

		return wc_create_order( $default_args );
	}

	public function add_customer_data($parent_order_id,$backorder) {

		if ( ! $parent_order = wc_get_order( $parent_order_id ) ) {
			return false;
		}

		if(! $backorder) {
			return false;
		}

		if ( 0 !== (int) $parent_order->get_customer_id() ) {
			$backorder->set_props(
				array(
					'customer_id'          => $parent_order->get_customer_id(),
					'billing_first_name'   => $parent_order->get_billing_first_name(),
					'billing_last_name'    => $parent_order->get_billing_last_name(),
					'billing_company'      => $parent_order->get_billing_company(),
					'billing_address_1'    => $parent_order->get_billing_address_1(),
					'billing_address_2'    => $parent_order->get_billing_address_2(),
					'billing_city'         => $parent_order->get_billing_city(),
					'billing_state'        => $parent_order->get_billing_state(),
					'billing_postcode'     => $parent_order->get_billing_postcode(),
					'billing_country'      => $parent_order->get_billing_country(),
					'billing_email'        => $parent_order->get_billing_email(),
					'billing_phone'        => $parent_order->get_billing_phone(),
					'shipping_first_name'  => $parent_order->get_shipping_first_name(),
					'shipping_last_name'   => $parent_order->get_shipping_last_name(),
					'shipping_company'     => $parent_order->get_shipping_company(),
					'shipping_address_1'   => $parent_order->get_shipping_address_1(),
					'shipping_address_2'   => $parent_order->get_shipping_address_2(),
					'shipping_city'        => $parent_order->get_shipping_city(),
					'shipping_state'       => $parent_order->get_shipping_state(),
					'shipping_postcode'    => $parent_order->get_shipping_postcode(),
					'shipping_country'     => $parent_order->get_shipping_country()
				)
			);

			$backorder->save();
		}
	}

	public function review_and_split_order($order_id, $posted_data, $order) {

		if ( ! $order = wc_get_order( $order_id ) ) {
			return;
		}

		$backorder_created = get_post_meta($order_id,'_backorder_created', true);

		if(!empty($backorder_created)) {
			return;
		}

		$order_items = $order->get_items( apply_filters( 'woocommerce_purchase_order_item_types', 'line_item' ) );

		$order_contains_backorder_products = $this->order_contains_backorder_products($order_id);

		if($order_contains_backorder_products==false) {
			return;
		}

		// Create Backorder
		$backorder = $this->create_backorder($order_id);

		if ( is_wp_error( $backorder ) ) {

			$order->add_order_note('Unable to create backorder for this order.');
			return;
		}

		// Add customer data to backorder
		$this->add_customer_data($order_id,$backorder);

		if ( count( $order_items ) <= 0 ) {
			$backorder->delete(true);
			return;
		}

		foreach ( $order_items as $item_id => $item ) {
			$product = $item->get_product();

			if ( $product && $product->backorders_allowed() ) {

				if($product->is_on_backorder( $item->get_quantity() )) {
					$backorder->add_product( $product, $item->get_quantity() );
					$order->remove_item( $item_id );
				}else{
					$backordered_quantity = $item->get_quantity() - max( 0, $product->get_stock_quantity() );

					$item->set_quantity($product->get_stock_quantity());
					$item->save();

					if($backorder && !empty($backordered_quantity)) {
						$backorder->add_product( $product, $backordered_quantity );
					}
				}
			}
		}

		// Save modified orders
		$backorder->calculate_totals();
		$backorder->save();
		$order->calculate_totals();
		$order->save();

		if ( count( $order->get_items('line_item') ) <= 0 ) {
			$backorder_line_items = $backorder->get_items('line_item');

			if(!empty($backorder_line_items)) {
				foreach ( $backorder_line_items as $backorder_item_id => $backorder_item ) {
					$backorder_product = $backorder_item->get_product();
					$order->add_product( $backorder_product, $backorder_item->get_quantity() );
				}				
			}

			$order->set_status('backordered');
			$order->calculate_totals();
			$order->save();

			$backorder->delete(true);
			$backorder = false;
		}

		if($backorder) {
			// Status without the "wc-" prefix
			$backorder->set_status('backordered');
			$backorder->calculate_totals();
			$backorder->save();

			// update order post meta
			update_post_meta($order_id,'_backorder_created','yes');

			update_post_meta($backorder->get_id(), '_initial_order_status', 'backordered');
			$order->add_order_note( sprintf( __( 'This order has backordered items and has been split into Order #%s.', 'woocommerce' ), $backorder->get_id() ) );

		}		

	}

	public function add_backorder_order_status($order_statuses) {
		global $WC_Backorder_Split;
		$order_statuses['wc-backordered'] = _x( 'Backordered', 'Order status', $WC_Backorder_Split->text_domain );
		return $order_statuses;
	}

	public function update_backorder_order_status($order_id) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		$initial_order_status = get_post_meta($order_id,'_initial_order_status',true);

		if(!empty($initial_order_status) && ($initial_order_status=='backordered')) {
			// Status without the "wc-" prefix
			$order->set_status($initial_order_status);
			$order->save();
		}

	}

	public function wcbs_register_new_order_status($order_statuses) {
		global $WC_Backorder_Split;
		// Status must start with "wc-"
		$order_statuses['wc-backordered'] = array(
					'label'                     => _x( 'Backordered', 'Order status', $WC_Backorder_Split->text_domain ),
					'public'                    => false,
					'exclude_from_search'       => false,
					'show_in_admin_all_list'    => true,
					'show_in_admin_status_list' => true,
					/* translators: %s: number of orders */
					'label_count'               => _n_noop( 'Backordered <span class="count">(%s)</span>', 'Backordered <span class="count">(%s)</span>', $WC_Backorder_Split->text_domain ),
				);      
		return $order_statuses;
	}

	public function wcbs_get_custom_order_status_bulk() {
		// Note: "mark_" must be there instead of "wc"
		$bulk_actions['mark_backordered'] = 'Change status to backordered';
		return $bulk_actions;		
	}	

}