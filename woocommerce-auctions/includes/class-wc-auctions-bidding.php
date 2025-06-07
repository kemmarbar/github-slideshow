<?php
/**
 * WooCommerce Auction Bidding Handler
 *
 * @package WooCommerceAuctions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class WC_Auctions_Bidding.
 */
class WC_Auctions_Bidding {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'init_hooks' ) );
	}

    /**
     * Initialize AJAX hooks.
     */
    public function init_hooks() {
        // AJAX handler for placing a bid - only for logged-in users
		add_action( 'wp_ajax_place_auction_bid', array( $this, 'handle_place_bid_ajax' ) );
        // To allow guest bidding (not recommended for auctions usually):
        // add_action( 'wp_ajax_nopriv_place_auction_bid', array( $this, 'handle_place_bid_ajax' ) );

        // Enqueue scripts
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

        // Custom add to cart handler for auction type products (for winners)
        add_action( 'woocommerce_add_to_cart_handler_auction', array( $this, 'handle_auction_winner_add_to_cart' ), 10, 1 );

        // Filter to make product purchasable for winner
        add_filter( 'woocommerce_is_purchasable', array( $this, 'filter_auction_is_purchasable' ), 10, 2 );

        // Validate cart item price for auctions
        add_action( 'woocommerce_before_calculate_totals', array( $this, 'validate_auction_cart_item_price' ), 10, 1 );

        // Clear winner token after successful order
        add_action( 'woocommerce_checkout_order_processed', array( $this, 'clear_winner_token_after_order' ), 10, 3 );
        // For WC Blocks checkout which might not use the above hook consistently for payment success
        add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'clear_winner_token_after_block_checkout_order' ), 10, 2 );


    }

	/**
	 * Enqueue scripts and styles for auctions.
	 */
	public function enqueue_scripts() {
		global $post;

		if ( is_singular( 'product' ) && $post ) {
			$product = wc_get_product( $post->ID );
			if ( $product && $product->get_type() === 'auction' ) {
				wp_enqueue_script(
					'wc-auctions-main',
					plugin_dir_url( dirname( __FILE__ ) ) . 'assets/js/auction.js',
					array( 'jquery', 'jquery-ui-datepicker' ), // Assuming datepicker might be used on frontend later
					'1.0.0',
					true
				);

				// Localize script with necessary data
				wp_localize_script(
					'wc-auctions-main',
					'wc_auctions_params',
					array(
						'ajax_url'           => admin_url( 'admin-ajax.php' ),
						'place_bid_nonce'    => wp_create_nonce( 'wc_auctions_place_bid' ),
						'product_id'         => $post->ID,
                        'currency_symbol'    => get_woocommerce_currency_symbol(),
						'i18n_invalid_bid_amount' => __( 'Please enter a valid bid amount, higher than or equal to the current minimum.', 'woocommerce-auctions' ),
						'i18n_bid_error'     => __( 'Error placing bid. Please try again.', 'woocommerce-auctions' ),
						'i18n_placing_bid'   => __( 'Placing bid...', 'woocommerce-auctions' ),
						'i18n_place_bid'     => __( 'Place Bid', 'woocommerce-auctions' ),
                        'i18n_processing'    => __( 'Processing...', 'woocommerce-auctions' ),
                        'i18n_min_bid'       => __( 'Minimum bid: ', 'woocommerce-auctions' ),
                        'i18n_countdown_ended' => __('Auction Ended', 'woocommerce-auctions'),
					)
				);
			}
		}
	}

	/**
	 * Handle AJAX request to place a bid.
	 */
	public function handle_place_bid_ajax() {
		// Check nonce for security
		check_ajax_referer( 'wc_auctions_place_bid', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in to place a bid.', 'woocommerce-auctions' ) ) );
			return;
		}

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$bid_amount = isset( $_POST['bid_amount'] ) ? floatval( wc_clean( wp_unslash( $_POST['bid_amount'] ) ) ) : 0;
		$user_id    = get_current_user_id();

		if ( ! $product_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid product for bidding.', 'woocommerce-auctions' ) ) );
			return;
		}

		$product = wc_get_product( $product_id );

		if ( ! $product || $product->get_type() !== 'auction' ) {
			wp_send_json_error( array( 'message' => __( 'This product is not available for auction.', 'woocommerce-auctions' ) ) );
			return;
		}

        // Check if auction is closed
        if ( $product->get_meta('_auction_closed', true) === '1' ) {
            wp_send_json_error( array( 'message' => __( 'This auction has ended.', 'woocommerce-auctions' ) ) );
            return;
        }

		// Validate auction status (active, not ended, not future)
        $start_date_str = $product->get_meta('_auction_start_date', true);
        $end_date_str = $product->get_meta('_auction_end_date', true);
        $now = new DateTime();
        $start_date = !empty($start_date_str) ? new DateTime($start_date_str) : null;
        $end_date = !empty($end_date_str) ? new DateTime($end_date_str) : null;

        if ($start_date && $now < $start_date) {
            wp_send_json_error( array( 'message' => __( 'This auction has not started yet.', 'woocommerce-auctions' ) ) );
            return;
        }
        if ($end_date && $now > $end_date) {
            wp_send_json_error( array( 'message' => __( 'This auction has ended.', 'woocommerce-auctions' ) ) );
            return;
        }
        if (!$end_date) { // Auctions must have an end date to be biddable
             wp_send_json_error( array( 'message' => __( 'This auction cannot be bid on (missing end date).', 'woocommerce-auctions' ) ) );
            return;
        }


		// Bid Validation
		$current_bid    = floatval( $product->get_meta( '_auction_current_bid', true ) );
		$start_price    = floatval( $product->get_meta( '_auction_start_price', true ) );
		$bid_increment  = floatval( $product->get_meta( '_auction_bid_increment', true ) );
        $current_bidder = $product->get_meta( '_auction_current_bidder', true );

        if ($user_id == $current_bidder) {
            wp_send_json_error( array( 'message' => __( 'You are already the highest bidder.', 'woocommerce-auctions' ) ) );
            return;
        }

		$minimum_bid = $current_bid ? ( $current_bid + $bid_increment ) : $start_price;
        // If bid increment is very small or zero, ensure at least a penny difference or use start price
        if ($bid_increment <= 0) {
            $minimum_bid = $current_bid ? ($current_bid + 0.01) : $start_price;
        }


		if ( $bid_amount < $minimum_bid ) {
			$message = sprintf( __( 'Your bid must be at least %s.', 'woocommerce-auctions' ), wc_price( $minimum_bid ) );
			wp_send_json_error( array( 'message' => $message ) );
			return;
		}

		// Process and save the bid
		$bids = $product->get_meta( '_auction_bids', true );
		if ( ! is_array( $bids ) ) {
			$bids = array();
		}

		$new_bid_entry = array(
			'user_id'   => $user_id,
			'bid'       => $bid_amount,
			'timestamp' => time(),
		);
		$bids[] = $new_bid_entry;

		// Sort bids by amount (desc) then timestamp (asc) if amounts are equal
        // For now, a simple append is fine, assuming highest bid is always last.
        // A more robust system would sort or ensure the highest is always easily retrievable.

		update_post_meta( $product_id, '_auction_bids', $bids );
		update_post_meta( $product_id, '_auction_current_bid', $bid_amount );
		update_post_meta( $product_id, '_auction_current_bidder', $user_id );
        update_post_meta( $product_id, '_price', $bid_amount ); // Update WC price to current bid

		// Clear product pricing cache
		wc_delete_product_transients( $product_id );

        // Reserved price check (notification only for now, actual handling at auction end)
        $reserved_price = floatval($product->get_meta('_auction_reserved_price', true));
        $message = __( 'Bid placed successfully!', 'woocommerce-auctions' );
        if ($reserved_price > 0 && $bid_amount < $reserved_price) {
            $message .= ' ' . __( 'Note: The reserve price has not yet been met.', 'woocommerce-auctions' );
        } elseif ($reserved_price > 0 && $bid_amount >= $reserved_price) {
             $message .= ' ' . __( 'The reserve price has been met.', 'woocommerce-auctions' );
        }

        // Prepare data for AJAX response
        $next_min_bid_val = $bid_amount + ($bid_increment > 0 ? $bid_increment : 0.01);

		wp_send_json_success(
			array(
				'message'          => $message,
				'current_bid_html' => wc_price( $bid_amount ),
                'next_min_bid'     => wc_price( $next_min_bid_val ),
                'next_min_bid_raw' => $next_min_bid_val,
				// 'bid_history_html' => $this->get_bid_history_html( $product_id ), // If implementing bid history display
			)
		);
	}

    // Placeholder for bid history display if needed later
	// public function get_bid_history_html( $product_id ) {
	// $bids = get_post_meta( $product_id, '_auction_bids', true );
	// if ( empty( $bids ) ) {
	// return '<p>' . __( 'No bids yet.', 'woocommerce-auctions' ) . '</p>';
	// }
	// // Format and return HTML for bid history
	// $html = '<ul>';
	// foreach ( $bids as $bid ) {
	// $user = get_userdata( $bid['user_id'] );
	// $html .= '<li>' . wc_price( $bid['bid'] ) . ' by ' . ( $user ? $user->display_name : __( 'Someone', 'woocommerce-auctions' ) ) . ' on ' . date_i18n( wc_date_format() . ' ' . wc_time_format(), $bid['timestamp'] ) . '</li>';
	// }
	// $html .= '</ul>';
	// return $html;
	// }

    /**
	 * Custom handler for adding auction products to cart (for winners).
	 *
	 * This is hooked to woocommerce_add_to_cart_handler_auction.
	 */
	public function handle_auction_winner_add_to_cart() {
		if ( ! isset( $_REQUEST['add-to-cart'] ) || ! is_numeric( $_REQUEST['add-to-cart'] ) ) {
			return;
		}

		$product_id = absint( $_REQUEST['add-to-cart'] );
		$product    = wc_get_product( $product_id );
        $current_user_id = get_current_user_id();

		if ( ! $product || $product->get_type() !== 'auction' ) {
            wc_add_notice( __( 'This product is not an auction product.', 'woocommerce-auctions' ), 'error' );
			wp_redirect( $product ? $product->get_permalink() : wc_get_shop_url() );
			exit;
		}

        // Check if user is the winner and token is valid
        $winner_user_id = (int) $product->get_meta('_auction_winner_user_id', true);
        $win_token_product = $product->get_meta('_auction_win_token', true);
        $win_token_url = isset($_REQUEST['auction_win_token']) ? wc_clean(wp_unslash($_REQUEST['auction_win_token'])) : '';
        $user_can_pay = $product->get_meta('_auction_winner_can_pay', true) === 'yes';
        $win_token_user_stored = get_user_meta( $current_user_id, '_auction_win_token_product_' . $product_id, true );


        if ( $current_user_id !== $winner_user_id || empty($win_token_url) || $win_token_url !== $win_token_product || $win_token_url !== $win_token_user_stored || !$user_can_pay ) {
            wc_add_notice( __( 'You are not eligible to purchase this auction item, or the payment token is invalid/expired.', 'woocommerce-auctions' ), 'error' );
            wp_redirect( $product->get_permalink() );
            exit;
        }

        // Ensure product is not already in cart by this winner (optional, WC usually handles quantity)
        foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
            if ( $cart_item['product_id'] == $product_id ) {
                 wc_add_notice( __( 'This auction item is already in your cart.', 'woocommerce-auctions' ), 'notice' );
                 wp_redirect( wc_get_cart_url() );
                 exit;
            }
        }

		// Add to cart
		try {
			$cart_item_data = array(
                'auction_win' => true, // Mark this cart item as an auction win
                'winning_bid' => floatval( $product->get_meta('_auction_winning_bid', true) )
            );

            // Temporarily make it purchasable for WC core logic if `is_purchasable` is too restrictive at this point
            add_filter('woocommerce_is_purchasable', '__return_true', 999);
			$result = WC()->cart->add_to_cart( $product_id, 1, 0, array(), $cart_item_data );
            remove_filter('woocommerce_is_purchasable', '__return_true', 999);


			if ( $result ) {
                // Don't remove token here yet, remove after order is paid.
                // update_user_meta( $current_user_id, '_auction_win_token_product_' . $product_id, '' ); // Clear user token
                // update_post_meta( $product_id, '_auction_winner_can_pay', 'no_longer' ); // Mark as added to cart
				wc_add_notice( sprintf( __( '"%s" has been added to your cart.', 'woocommerce-auctions' ), $product->get_name() ), 'success' );
				wp_redirect( wc_get_cart_url() );
				exit;
			} else {
                throw new Exception( __( 'Failed to add auction item to cart.', 'woocommerce-auctions' ) );
            }
		} catch ( Exception $e ) {
			wc_add_notice( $e->getMessage(), 'error' );
			wp_redirect( $product->get_permalink() );
			exit;
		}
	}

    /**
     * Filter purchasable status for auction products.
     */
    public function filter_auction_is_purchasable( $is_purchasable, $product ) {
        if ( $product->get_type() === 'auction' ) {
            $auction_closed = $product->get_meta('_auction_closed', true) === '1';
            $winner_user_id = (int) $product->get_meta('_auction_winner_user_id', true);
            $current_user_id = get_current_user_id();
            $can_pay_flag = $product->get_meta('_auction_winner_can_pay', true); // 'yes', 'paid'

            if ( ! $auction_closed ) { // Active auction
                return false; // Not purchasable directly, only BIN if implemented or bidding
            } else { // Auction closed
                if ( $winner_user_id && $current_user_id === $winner_user_id && $can_pay_flag === 'yes' ) {
                     // Check token validity as well for add-to-cart link context
                    $win_token_product = $product->get_meta('_auction_win_token', true);
                    $win_token_user_stored = get_user_meta( $current_user_id, '_auction_win_token_product_' . $product->get_id(), true );

                    // This check is more for general catalog. Add-to-cart link provides its own token.
                    if (!empty($win_token_product) && !empty($win_token_user_stored) && $win_token_product === $win_token_user_stored) {
                        return true; // Purchasable by winner if token matches and not yet paid
                    }
                }
                return false; // Not purchasable by others, or if winner already paid/token invalid
            }
        }
        return $is_purchasable;
    }

    /**
     * Validate auction item price in cart.
     */
    public function validate_auction_cart_item_price( $cart_obj ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;

        foreach ( $cart_obj->get_cart() as $key => $value ) {
            if ( isset( $value['auction_win'] ) && $value['auction_win'] === true ) {
                $product = wc_get_product( $value['product_id'] );
                if($product && $product->get_type() === 'auction') {
                    $winning_bid = floatval( $product->get_meta('_auction_winning_bid', true) );
                    if ( $value['data']->get_price() != $winning_bid ) {
                        $value['data']->set_price( $winning_bid );
                    }
                }
            }
        }
    }

    /**
     * Clear winner token and mark as paid after successful order processing.
     */
    public function clear_winner_token_after_order( $order_id, $posted_data, $order ) {
        foreach ( $order->get_items() as $item ) {
            $product_id = $item->get_product_id();
            $product = wc_get_product( $product_id );
            if ( $product && $product->get_type() === 'auction' ) {
                $winner_user_id = (int) $product->get_meta('_auction_winner_user_id', true);
                $order_user_id = $order->get_user_id();

                if ( $winner_user_id && $order_user_id == $winner_user_id ) {
                     // Check if this order item was from an auction win
                    $is_auction_win_item = false;
                    foreach ($item->get_meta_data() as $meta) {
                        if ($meta->key === '_cart_item_data_auction_win' && $meta->value === true) { // Example meta key from add_to_cart
                           $is_auction_win_item = true;
                           break;
                        }
                    }
                    // More robust check: if product ID in order matches a won auction for this user
                    $win_token_user = get_user_meta( $order_user_id, '_auction_win_token_product_' . $product_id, true );
                    if (!empty($win_token_user)) { // If a token exists, this was a won auction not yet fully paid.
                        delete_user_meta( $order_user_id, '_auction_win_token_product_' . $product_id );
                        update_post_meta( $product_id, '_auction_winner_can_pay', 'paid' );
                        // Optionally remove _auction_win_token from product meta if single winner scenario
                        // delete_post_meta( $product_id, '_auction_win_token' );
                        error_log("Auction $product_id: Payment completed by winner User ID $order_user_id for Order ID $order_id. Tokens cleared.");
                    }
                }
            }
        }
    }

    /**
     * Clear winner token for orders processed via WC Blocks / Store API.
     */
    public function clear_winner_token_after_block_checkout_order( $order, $request ) {
         // We can reuse the same logic as the standard checkout hook if the $order object is similar
        if ($order && $order->get_id()) {
            $this->clear_winner_token_after_order($order->get_id(), array(), $order);
        }
    }

}

new WC_Auctions_Bidding();
?>
