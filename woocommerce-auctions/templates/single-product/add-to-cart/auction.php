<?php
/**
 * Auction product add to cart
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/single-product/add-to-cart/auction.php.
 *
 * @package WooCommerceAuctions
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

global $product;

// Ensure $product is a WC_Product_Auction object
if ( ! $product || $product->get_type() !== 'auction' ) {
	return;
}

// Get auction data
$auction_closed      = $product->get_meta( '_auction_closed', true ) === '1';
$winner_user_id      = $product->get_meta( '_auction_winner_user_id', true );
$winning_bid         = $product->get_meta( '_auction_winning_bid', true );
$reserve_not_met     = $product->get_meta( '_auction_reserve_not_met', true ) === 'yes';

$start_price         = $product->get_meta( '_auction_start_price', true );
$current_bid         = $product->get_meta( '_auction_current_bid', true );
$bid_increment       = $product->get_meta( '_auction_bid_increment', true );
$buy_it_now_price    = $product->get_meta( '_auction_buy_it_now_price', true );
$auction_start_date  = $product->get_meta( '_auction_start_date', true );
$auction_end_date    = $product->get_meta( '_auction_end_date', true );


if ( empty( $current_bid ) && ! $auction_closed ) { // Default to start price if no bids yet and not closed
	$current_bid = $start_price;
}

$now = new DateTime('now', new DateTimeZone('UTC')); // Ensure timezone consistency
$start_date_obj = !empty($auction_start_date) ? new DateTime($auction_start_date, new DateTimeZone('UTC')) : null;
$end_date_obj   = !empty($auction_end_date) ? new DateTime($auction_end_date, new DateTimeZone('UTC')) : null;

$auction_started = $start_date_obj && $now >= $start_date_obj;
// Use the _auction_closed flag as the primary determinant for "finished" status from WP Cron perspective
// $auction_finished_by_date = $end_date_obj && $now > $end_date_obj;
$auction_truly_finished = $auction_closed;


// Item condition (placeholder - you would add a field for this)
$item_condition = $product->get_meta( '_auction_item_condition', true );

?>

<?php if ( $auction_truly_finished ) : ?>
    <div class="woocommerce-info auction-finished">
        <h2 class="auction-status-title"><?php _e( 'Auction Finished', 'woocommerce-auctions' ); ?></h2>
        <?php if ( $winner_user_id && $winning_bid ) : ?>
            <?php
            $winner_data = get_userdata( $winner_user_id );
            $winner_name = $winner_data ? $winner_data->display_name : __( 'Someone', 'woocommerce-auctions' );
            ?>
            <p class="auction-winner-info">
                <?php printf( __( 'Winning Bid: %s', 'woocommerce-auctions' ), wc_price( $winning_bid ) ); ?><br>
                <?php printf( __( 'Winner: %s', 'woocommerce-auctions' ), esc_html( $winner_name ) ); ?>
            </p>
            <?php
            // "Pay Now" button for the winner
            if ( is_user_logged_in() && get_current_user_id() == $winner_user_id ) {
                $can_pay_flag = $product->get_meta('_auction_winner_can_pay', true);
                $win_token_user = get_user_meta( get_current_user_id(), '_auction_win_token_product_' . $product->get_id(), true );
                $win_token_product = $product->get_meta('_auction_win_token', true);

                if ( $can_pay_flag === 'yes' && !empty($win_token_user) && $win_token_user === $win_token_product ) {
                    $pay_now_url = add_query_arg(
                        array(
                            'add-to-cart' => $product->get_id(),
                            'auction_win_token' => $win_token_user,
                        ),
                        wc_get_cart_url() // Using cart URL as a base, can also be product URL or a custom endpoint
                    );
                    echo '<a href="' . esc_url( $pay_now_url ) . '" class="button alt auction-pay-now-button">' . __( 'Pay for Won Auction', 'woocommerce-auctions' ) . '</a>';
                } elseif ($can_pay_flag === 'paid') {
                    echo '<p class="auction-paid-message">' . __( 'You have already paid for this auction.', 'woocommerce-auctions' ) . '</p>';
                }
            }
            ?>
        <?php elseif ( $reserve_not_met ) : ?>
            <p><?php _e( 'This auction ended, but the reserve price was not met.', 'woocommerce-auctions' ); ?></p>
        <?php else : ?>
            <p><?php _e( 'This auction ended with no winning bids.', 'woocommerce-auctions' ); ?></p>
        <?php endif; ?>
    </div>
<?php elseif ( ! $auction_started && $start_date_obj ) : ?>
	<div class="woocommerce-info auction-not-started">
		<?php printf( __( 'This auction has not started yet. It is scheduled to start on: %s.', 'woocommerce-auctions' ), wc_format_datetime( $start_date_obj ) ); ?>
	</div>

<?php else : ?>
	<div class="auction-details">
		<?php if ( $item_condition ) : ?>
			<p class="auction-condition"><?php printf( __( 'Item Condition: %s', 'woocommerce-auctions' ), esc_html( $item_condition ) ); ?></p>
		<?php endif; ?>

		<p class="auction-dates">
			<?php if ( $auction_start_date ) : ?>
				<span class="auction-start-date"><?php printf( __( 'Starts: %s', 'woocommerce-auctions' ), wc_format_datetime( $start_date_obj ) ); ?></span><br>
			<?php endif; ?>
			<?php if ( $auction_end_date ) : ?>
				<span class="auction-end-date"><?php printf( __( 'Ends: %s', 'woocommerce-auctions' ), wc_format_datetime( $end_date_obj ) ); ?></span>
			<?php endif; ?>
		</p>

		<p class="auction-price starting-price">
			<?php printf( __( 'Starting Price: %s', 'woocommerce-auctions' ), wc_price( $start_price ) ); ?>
		</p>

        <?php if ( !empty( $current_bid ) || (empty( $current_bid ) && !empty($start_price) ) ): ?>
            <div class="auction-current-bid">
                <p class="price current-bid-value"><?php printf( __( 'Current Bid: %s', 'woocommerce-auctions' ), wc_price( $current_bid ? $current_bid : $start_price ) ); ?></p>
                <?php // Reserved price met/not met could be added here later ?>
            </div>
        <?php else: ?>
             <div class="auction-current-bid">
                <p class="price current-bid-value"><?php _e( 'No bids yet.', 'woocommerce-auctions' ); ?></p>
            </div>
        <?php endif; ?>

		<div id="auction-time-left" class="auction-time-left">
			<?php _e( 'Time left: ', 'woocommerce-auctions' ); ?>
			<span class="countdown" data-time="<?php echo $auction_end_date ? esc_attr( date( 'Y-m-d H:i:s', strtotime( $auction_end_date ) ) ) : ''; ?>">--:--:--:--</span>
            <?php // Hidden field for JS to easily grab the end date if needed, though data-time on countdown span is preferred for directness. ?>
            <input type="hidden" class="auction-end-date-meta" value="<?php echo $auction_end_date ? esc_attr( date( 'Y-m-d H:i:s', strtotime( $auction_end_date ) ) ) : ''; ?>" />
		</div>

        <div class="auction-messages woocommerce-notices-wrapper"></div> <?php // Container for AJAX messages ?>

		<form class="cart auction_form" action="" method="post" enctype="multipart/form-data">
			<?php do_action( 'woocommerce_before_auction_bid_form' ); ?>

			<div class="auction-bid-options">
				<?php
				// Placeholder for next minimum bid
				$min_bid_amount = $current_bid + $bid_increment;
				if (empty($current_bid) && !empty($start_price)) {
					$min_bid_amount = $start_price;
				} else if (!empty($bid_increment)) {
					$min_bid_amount = floatval($current_bid) + floatval($bid_increment);
				} else {
					// fallback if increment not set
					$min_bid_amount = floatval($current_bid) + 0.01;
				}
				?>
				<div class="bid-input-fields">
					<label for="bid_amount_<?php echo esc_attr( $product->get_id() ); ?>"><?php printf( __( 'Minimum bid: %s', 'woocommerce-auctions' ), wc_price( $min_bid_amount ) ); ?></label>
					<input type="number" name="bid_amount" id="bid_amount_<?php echo esc_attr( $product->get_id() ); ?>" class="input-text qty text bid" step="<?php echo esc_attr( $bid_increment ? $bid_increment : 'any' ); ?>" min="<?php echo esc_attr( $min_bid_amount ); ?>" placeholder="<?php echo wc_price($min_bid_amount); ?>" />
					<button type="submit" name="place_bid" value="<?php echo esc_attr( $product->get_id() ); ?>" class="button alt place-bid-button"><?php _e( 'Place Bid', 'woocommerce-auctions' ); ?></button>
					<input type="hidden" name="product_id" value="<?php echo esc_attr( $product->get_id() ); ?>" />
					<?php wp_nonce_field( 'wc_auctions_place_bid', 'nonce' ); // Nonce for security, matching the AJAX handler check ?>
				</div>
			</div>

			<?php do_action( 'woocommerce_after_auction_bid_form' ); ?>
		</form>

		<?php if ( $buy_it_now_price && $buy_it_now_price > 0 && !$auction_finished ) : // Also check if auction not finished for BIN ?>
			<div class="auction-buy-now">
				<p><?php printf( __( 'Or buy it now for %s', 'woocommerce-auctions' ), wc_price( $buy_it_now_price ) ); ?></p>
				<form class="cart buy_now_form" method="post" enctype="multipart/form-data"> <?php // Action should point to product page to trigger add-to-cart normally ?>
					<input type="hidden" name="add-to-cart" value="<?php echo esc_attr( $product->get_id() ); ?>">
					<?php // To identify this as a BIN purchase if special handling is needed on add-to-cart ?>
					<?php // <input type="hidden" name="auction_buy_now" value="1"> ?>
					<button type="submit" class="button alt buy-now-button"><?php _e( 'Buy it Now', 'woocommerce-auctions' ); ?></button>
				</form>
			</div>
		<?php endif; ?>

	</div>
<?php endif; // This endif closes the main display block for active/not-yet-started auctions ?>

<script type="text/javascript">
<?php
// The JavaScript for countdown and AJAX bid submission is now expected to be in auction.js
// and enqueued via WC_Auctions_Bidding class.
// We keep this script block if there's anything highly specific to this template instance
// that can't be generalized in auction.js, but usually it's better to move logic to the JS file.
?>
<!--
<script type="text/javascript">
jQuery(document).ready(function($) {
    // Example: If you needed to pass the specific end time directly to a function here
    // var specificEndTime = $(".auction-time-left .countdown").data('time');
    // console.log("Auction ends at (from template):", specificEndTime);
});
</script>
-->
