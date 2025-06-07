<?php
/**
 * WooCommerce Auction Manager
 *
 * Handles auction ending, winner determination, and other management tasks.
 *
 * @package WooCommerceAuctions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class WC_Auctions_Manager.
 */
class WC_Auctions_Manager {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_auction_ended', array( __CLASS__, 'handle_auction_end' ), 10, 1 );
        // Hook to handle products saved with an end date in the past (called from admin settings)
        // add_action( 'woocommerce_auction_process_past_end_date', array( __CLASS__, 'handle_auction_end' ), 10, 1 );
	}

	/**
	 * Handle the auction end process.
	 *
	 * Triggered by WP Cron or direct call for past auctions.
	 *
	 * @param int $product_id The ID of the auction product.
	 */
	public static function handle_auction_end( $product_id ) {
		$product = wc_get_product( $product_id );

		if ( ! $product || $product->get_type() !== 'auction' ) {
			return;
		}

        // Double check if auction should indeed be closed
        $end_date_str = $product->get_meta('_auction_end_date', true);
        if (empty($end_date_str)) {
            error_log("Auction $product_id ended event triggered but no end date found.");
            return; // Should not happen if scheduling is correct
        }

        try {
            $end_datetime = new DateTime($end_date_str, new DateTimeZone('UTC')); // Assuming UTC
            $now = new DateTime('now', new DateTimeZone('UTC'));

            if ($end_datetime > $now && !$product->get_meta('_auction_force_end_now', true) ) {
                 error_log("Auction $product_id ended event triggered prematurely. End time: $end_date_str. Current time: " . $now->format('Y-m-d H:i:s'));
                 // Reschedule if somehow triggered too early, unless forced.
                 // This might happen if site timezone settings are misconfigured vs cron timezone
                 // For now, we just log and exit. Robust handling might reschedule.
                return;
            }
        } catch (Exception $e) {
            error_log("Error comparing dates for auction $product_id: " . $e->getMessage());
            return;
        }


		// Prevent duplicate processing if already marked closed by this logic
		if ( $product->get_meta( '_auction_processed_by_handle_end', true ) === 'yes' && $product->get_meta('_auction_closed', true) === '1' ) {
            error_log("Auction $product_id end processing already ran and marked as closed. Skipping.");
			return;
		}

        // Mark that this function has started processing this auction
        update_post_meta( $product_id, '_auction_processing_end', 'yes' );


		$bids           = $product->get_meta( '_auction_bids', true );
		$reserved_price = floatval( $product->get_meta( '_auction_reserved_price', true ) );
		$winner_found   = false;
		$winning_bid    = null;
		$winner_user_id = null;

		if ( ! empty( $bids ) && is_array( $bids ) ) {
			// Sort bids: highest first. If amounts are equal, earlier bid wins.
			// This is simplified; a real system might need more complex sorting if bids can be identical.
			// For now, assume last bid in array is highest if array is built chronologically with increasing bids.
            // A more robust way is to sort them properly here.
            usort($bids, function($a, $b) {
                if ($a['bid'] == $b['bid']) {
                    return $a['timestamp'] <=> $b['timestamp']; // Earlier timestamp wins for same amount
                }
                return $b['bid'] <=> $a['bid']; // Higher bid wins
            });

            $highest_bid_obj = $bids[0]; // Highest bid is now the first element

			if ( $highest_bid_obj ) {
				$current_high_bid = floatval( $highest_bid_obj['bid'] );
				$current_high_bidder = intval( $highest_bid_obj['user_id'] );

				// Check if reserve price is met
				if ( $current_high_bid >= $reserved_price ) {
					$winner_found   = true;
					$winning_bid    = $current_high_bid;
					$winner_user_id = $current_high_bidder;

					update_post_meta( $product_id, '_auction_winner_user_id', $winner_user_id );
					update_post_meta( $product_id, '_auction_winning_bid', $winning_bid );

					// Update WooCommerce prices
					update_post_meta( $product_id, '_regular_price', $winning_bid );
					update_post_meta( $product_id, '_sale_price', $winning_bid );
					update_post_meta( $product_id, '_price', $winning_bid );

                    // Generate a unique token for the winner to claim the purchase
                    $win_token = wp_generate_password( 20, false );
                    update_user_meta( $winner_user_id, '_auction_win_token_product_' . $product_id, $win_token );
                    update_post_meta( $product_id, '_auction_win_token', $win_token ); // Store on product for verification
                    update_post_meta( $product_id, '_auction_winner_can_pay', 'yes' ); // Flag that winner can proceed to pay

                    // Set stock status - product is now reserved for winner
                    // $product->set_stock_status('outofstock'); // Don't set to outofstock generally, control via purchasable filter
                    $product->set_manage_stock('yes');
                    $product->set_stock_quantity(1); // Set to 1, as it's for the winner
                    // If you want to prevent anyone else from seeing it as purchasable, use the purchasable filter primarily.
                    // For general catalog visibility, 'outofstock' might be too blunt if you want winner to see it.
                    $product->save(); // Save product changes

					// Optionally: Send email notification to winner and admin
                    // do_action('woocommerce_auction_winner_notification', $product_id, $winner_user_id, $winning_bid);
                    error_log("Auction $product_id: Winner User ID $winner_user_id, Winning Bid $winning_bid. Token generated.");


				} else {
					// Reserve not met
                    update_post_meta( $product_id, '_auction_reserve_not_met', 'yes' );
                    $product->set_stock_status('outofstock'); // No winner, so out of stock
                    $product->set_manage_stock('yes');
                    $product->set_stock_quantity(0);
                    $product->save();
                    error_log("Auction $product_id: Reserve not met. Current high bid: $current_high_bid, Reserve: $reserved_price");
				}
			} else { // No bids at all
                $product->set_stock_status('outofstock');
                $product->set_manage_stock('yes');
                $product->set_stock_quantity(0);
                $product->save();
                error_log("Auction $product_id: Ended with no bids.");
            }
		} else { // No bids array or empty
            $product->set_stock_status('outofstock');
            $product->set_manage_stock('yes');
            $product->set_stock_quantity(0);
            $product->save();
            error_log("Auction $product_id: Ended with no bids (bids array empty/not found).");
        }

		// Mark auction as closed
		update_post_meta( $product_id, '_auction_closed', '1' );
        delete_post_meta( $product_id, '_auction_end_event_scheduled_ts' ); // Clean up scheduled event meta
        delete_post_meta( $product_id, '_auction_force_end_now' ); // Clean up force end meta
        update_post_meta( $product_id, '_auction_processed_by_handle_end', 'yes' ); // Mark as processed
        delete_post_meta( $product_id, '_auction_processing_end' ); // Remove processing lock

        // Clear product pricing cache
		wc_delete_product_transients( $product_id );

		if ( $winner_found ) {
			// Action hook for further integrations when an auction has a winner
			do_action( 'woocommerce_auction_won', $product_id, $winner_user_id, $winning_bid );
            error_log("Auction $product_id ended. Winner: User $winner_user_id, Bid: $winning_bid. Product status updated.");
		} else {
            // Action hook for auctions ending without a winner
            do_action( 'woocommerce_auction_no_winner', $product_id );
            if (!$product->get_meta('_auction_reserve_not_met', true)) { // Avoid double logging if reserve not met already logged
                error_log("Auction $product_id ended. No winner (reason: other than reserve not met / no bids). Product status updated to outofstock.");
            }
            // Optionally, set product to draft or pending if no winner
            // $product->set_status('draft');
            // $product->save();
		}
	}
}

new WC_Auctions_Manager();
?>
