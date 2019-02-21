<?php
/**
 * WC_Backorder_Split_Frontend Class
 *
 * Split the order if it doesn't have enough products in stock
 *
 * @author      Akshaya Swaroop
 * @package     wc-backorder-split/classes
 * @since       1.0
 */

if(!defined('ABSPATH')) exit; // Exit if accessed directly

class WC_Backorder_Split_Frontend {

	public function __construct() {
		add_action('woocommerce_checkout_order_processed', array($this,'wcbs_review_and_split_order'), 10, 3);
		add_filter('wc_order_statuses', array($this,'wcbs_add_backorder_order_status'));

		add_action( 'woocommerce_order_status_pending', array($this,'wcbs_update_backorder_order_status'), 99, 1 );
		add_action( 'woocommerce_order_status_completed', array($this,'wcbs_update_backorder_order_status'), 99, 1 );
		add_action( 'woocommerce_order_status_processing', array($this,'wcbs_update_backorder_order_status'), 99, 1 );
		add_action( 'woocommerce_order_status_on-hold', array($this,'wcbs_update_backorder_order_status') );
		add_action( 'woocommerce_order_status_cancelled', array($this,'wcbs_update_backorder_order_status'), 99, 1 );

		add_filter( 'woocommerce_register_shop_order_post_statuses', array($this,'wcbs_register_new_order_status'));

		add_filter( 'bulk_actions-edit-shop_order', array($this,'wcbs_get_custom_order_status_bulk'));

		add_action( 'woocommerce_order_details_after_order_table', array($this,'wcbs_render_backorder_table') );

	}

	/**
	 * Check if order contains backorder products.
	 *
	 * @param  int  $order_id Order ID.
	 * @return bool
	 */
	public function wcbs_order_contains_backorder_products($order_id) {
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


	/**
	 * Create backorder from parent order.
	 *
	 * @param  int  $parent_order_id Parent Order ID.
	 * @return WC_Order|bool Order object if successful or false.
	 */
	public function wcbs_create_backorder($parent_order_id) {

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

	/**
	 * Add customer data to backorder.
	 *
	 * @param  int  $parent_order_id Parent Order ID.
	 * @param WC_Order $backorder Order object.
	 * @return void
	 */
	public function wcbs_add_customer_data($parent_order_id,$backorder) {

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

	/**
	 * Review and split the orginal order.
	 *
	 * @param  int  $order_id Order ID.
	 * @param array $posted_data Checkout form posted data.
	 * @param WC_Order $order Order object.
	 * @return void
	 */
	public function wcbs_review_and_split_order($order_id, $posted_data, $order) {
		global $WC_Backorder_Split;
		if ( ! $order = wc_get_order( $order_id ) ) {
			return;
		}

		$backorder_created = get_post_meta($order_id,'_backorder_created', true);

		if(!empty($backorder_created)) {
			return;
		}

		$order_items = $order->get_items( apply_filters( 'woocommerce_purchase_order_item_types', 'line_item' ) );

		$order_contains_backorder_products = $this->wcbs_order_contains_backorder_products($order_id);

		if($order_contains_backorder_products==false) {
			return;
		}

		// Create Backorder
		$backorder = $this->wcbs_create_backorder($order_id);

		if ( is_wp_error( $backorder ) ) {

			$order->add_order_note('Unable to create backorder for this order.');
			return;
		}

		// Add customer data to backorder
		$this->wcbs_add_customer_data($order_id,$backorder);

		if ( count( $order_items ) <= 0 ) {
			$backorder->delete(true);
			return;
		}

		foreach ( $order_items as $item_id => $item ) {
			$product = $item->get_product();

			if ( $product && $product->backorders_allowed() ) {

				if($product->is_on_backorder( $item->get_quantity() ) && ($product->get_stock_quantity() <=0)) {
					$backorder->add_product( $product, $item->get_quantity() );
					$order->remove_item( $item_id );
				}else{
					$backordered_quantity = $item->get_quantity() - max( 0, $product->get_stock_quantity() );

					$order->remove_item( $item_id );
					$order->add_product( $product, $product->get_stock_quantity());

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
			update_post_meta($order_id,'_backorder_created', 'yes');
			update_post_meta($order_id,'_backorder_id', $backorder->get_id());			

			update_post_meta($backorder->get_id(), '_initial_order_status', 'backordered');
			$order->add_order_note( sprintf( __( 'This order has backordered items and has been split into Order #%s.', $WC_Backorder_Split->text_domain ), $backorder->get_id() ) );

		}		

	}

	/**
	 * Add new order status in WC
	 *
	 * @param array $order_statuses
	 * @return array
	 */
	public function wcbs_add_backorder_order_status($order_statuses) {
		global $WC_Backorder_Split;
		$order_statuses['wc-backordered'] = _x( 'Backordered', 'Order status', $WC_Backorder_Split->text_domain );
		return $order_statuses;
	}

	/**
	 * Update backorder status on status change
	 *
	 * @param int $order_id
	 * @return void
	 */
	public function wcbs_update_backorder_order_status($order_id) {
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

	/**
	 * Register new order status in WC
	 *
	 * @param array $order_statuses
	 * @return array
	 */
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

	/**
	 * Add new order status in bulk actions (Order's Listing)
	 *
	 * @return array
	 */
	public function wcbs_get_custom_order_status_bulk() {
		// Note: "mark_" must be there instead of "wc"
		$bulk_actions['mark_backordered'] = 'Change status to backordered';
		return $bulk_actions;		
	}

	/**
	 * Add new backorder table in thank you page.
	 *
	 * @param WC_Order $order Order object.
	 */
	public function wcbs_render_backorder_table($order) {
		global $WC_Backorder_Split;
		if(!$order) {
			return;
		}

		if ( get_post_meta( $order->get_id(), '_backorder_id', true ) ) :
			$backorder = wc_get_order(get_post_meta( $order->get_id(), '_backorder_id', true ));

			if(!$backorder) {
				return;
			}

			$order_items = $backorder->get_items( 'line_item' );
			$show_purchase_note = $backorder->has_status( array( 'completed', 'processing' ) );			

		?>
		<h2 class="woocommerce-order-details__title"><?php _e( 'Backorder details', $WC_Backorder_Split->text_domain ); ?></h2>

		<table class="woocommerce-table woocommerce-table--order-details shop_table order_details">

		<thead>
			<tr>
				<th class="woocommerce-table__product-name product-name"><?php _e( 'Product', $WC_Backorder_Split->text_domain ); ?></th>
				<th class="woocommerce-table__product-table product-total"><?php _e( 'Total', $WC_Backorder_Split->text_domain ); ?></th>
			</tr>
		</thead>

		<tbody>
			<?php
			foreach ( $order_items as $item_id => $item ) {
				$product = $item->get_product();

				wc_get_template( 'order/order-details-item.php', array(
					'order'			     => $backorder,
					'item_id'		     => $item_id,
					'item'			     => $item,
					'show_purchase_note' => $show_purchase_note,
					'purchase_note'	     => $product ? $product->get_purchase_note() : '',
					'product'	         => $product,
				) );
			}
			?>
		</tbody>

		<tfoot>
			<?php
				foreach ( $backorder->get_order_item_totals() as $key => $total ) {
					?>
					<tr>
						<th scope="row"><?php echo $total['label']; ?></th>
						<td><?php echo $total['value']; ?></td>
					</tr>
					<?php
				}
			?>
			<?php if ( $backorder->get_customer_note() ) : ?>
				<tr>
					<th><?php _e( 'Note:', $WC_Backorder_Split->text_domain ); ?></th>
					<td><?php echo wptexturize( $backorder->get_customer_note() ); ?></td>
				</tr>
			<?php endif; ?>
		</tfoot>
		</table>
	<?php endif;
	}

}