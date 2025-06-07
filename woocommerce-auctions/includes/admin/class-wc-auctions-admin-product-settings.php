<?php
/**
 * WooCommerce Auction Product Admin Settings
 *
 * @package WooCommerceAuctions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class WC_Auctions_Admin_Product_Settings.
 */
class WC_Auctions_Admin_Product_Settings {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_auction_product_data_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'auction_product_data_panel_content' ) );
		add_action( 'woocommerce_process_product_meta_auction', array( $this, 'save_auction_product_data' ) );
		// If not using 'woocommerce_process_product_meta_auction', use the general one and check product type:
		// add_action( 'woocommerce_process_product_meta', array( $this, 'save_auction_product_data_general' ), 10, 2 );
	}

	/**
	 * Add 'Auction' tab to product data meta box.
	 *
	 * @param array $tabs Existing tabs.
	 * @return array Modified tabs.
	 */
	public function add_auction_product_data_tab( $tabs ) {
		$tabs['auction'] = array(
			'label'    => __( 'Auction', 'woocommerce-auctions' ),
			'target'   => 'auction_product_data',
			'class'    => array( 'show_if_auction', 'hide_if_grouped', 'hide_if_external', 'hide_if_variable' ), // Show only for auction product type.
			'priority' => 21, // After 'Inventory' tab.
		);
		return $tabs;
	}

	/**
	 * Content for the 'Auction' tab.
	 */
	public function auction_product_data_panel_content() {
		global $post, $thepostid, $product_object;

		// $thepostid is available, or use $post->ID
		$post_id = $thepostid ? $thepostid : $post->ID;

		?>
		<div id="auction_product_data" class="panel woocommerce_options_panel">
			<div class="options_group">
				<?php
				woocommerce_wp_text_input(
					array(
						'id'          => '_auction_start_price',
						'label'       => __( 'Start Price', 'woocommerce-auctions' ) . ' (' . get_woocommerce_currency_symbol() . ')',
						'placeholder' => __( 'e.g., 10.00', 'woocommerce-auctions' ),
						'desc_tip'    => 'true',
						'description' => __( 'The initial price for the auction.', 'woocommerce-auctions' ),
						'type'        => 'number',
						'custom_attributes' => array(
							'step' => 'any',
							'min'  => '0',
						),
						'value'       => get_post_meta( $post_id, '_auction_start_price', true ),
					)
				);

				woocommerce_wp_text_input(
					array(
						'id'          => '_auction_bid_increment',
						'label'       => __( 'Bid Increment', 'woocommerce-auctions' ) . ' (' . get_woocommerce_currency_symbol() . ')',
						'placeholder' => __( 'e.g., 1.00', 'woocommerce-auctions' ),
						'desc_tip'    => 'true',
						'description' => __( 'The minimum amount by which a bid must be increased.', 'woocommerce-auctions' ),
						'type'        => 'number',
						'custom_attributes' => array(
							'step' => 'any',
							'min'  => '0.01',
						),
						'value'       => get_post_meta( $post_id, '_auction_bid_increment', true ),
					)
				);

				woocommerce_wp_text_input(
					array(
						'id'          => '_auction_reserved_price',
						'label'       => __( 'Reserved Price', 'woocommerce-auctions' ) . ' (' . get_woocommerce_currency_symbol() . ')',
						'placeholder' => __( 'Optional, e.g., 20.00', 'woocommerce-auctions' ),
						'desc_tip'    => 'true',
						'description' => __( 'The minimum price to sell the item. If not met, the auction may not have a winner. Leave blank for no reserve.', 'woocommerce-auctions' ),
						'type'        => 'number',
						'custom_attributes' => array(
							'step' => 'any',
							'min'  => '0',
						),
						'value'       => get_post_meta( $post_id, '_auction_reserved_price', true ),
					)
				);

				woocommerce_wp_text_input(
					array(
						'id'          => '_auction_buy_it_now_price',
						'label'       => __( 'Buy it Now Price', 'woocommerce-auctions' ) . ' (' . get_woocommerce_currency_symbol() . ')',
						'placeholder' => __( 'Optional, e.g., 50.00', 'woocommerce-auctions' ),
						'desc_tip'    => 'true',
						'description' => __( 'Allows users to purchase the item immediately at this price. Leave blank to disable.', 'woocommerce-auctions' ),
						'type'        => 'number',
						'custom_attributes' => array(
							'step' => 'any',
							'min'  => '0',
						),
						'value'       => get_post_meta( $post_id, '_auction_buy_it_now_price', true ),
					)
				);
				?>
			</div>
			<div class="options_group">
				<p class="form-field _auction_dates_fields">
					<label for="_auction_start_date"><?php esc_html_e( 'Auction Start Date', 'woocommerce-auctions' ); ?></label>
					<input type="text" class="short" name="_auction_start_date" id="_auction_start_date" value="<?php echo esc_attr( get_post_meta( $post_id, '_auction_start_date', true ) ); ?>" placeholder="<?php esc_attr_e( 'YYYY-MM-DD HH:MM', 'woocommerce-auctions' ); ?>" autocomplete="off" />
					<span class="description"><?php esc_html_e( 'Date and time when the auction begins. Use YYYY-MM-DD HH:MM format or use the date picker.', 'woocommerce-auctions'); ?></span>
				</p>

				<p class="form-field _auction_dates_fields">
					<label for="_auction_end_date"><?php esc_html_e( 'Auction End Date', 'woocommerce-auctions' ); ?></label>
					<input type="text" class="short" name="_auction_end_date" id="_auction_end_date" value="<?php echo esc_attr( get_post_meta( $post_id, '_auction_end_date', true ) ); ?>" placeholder="<?php esc_attr_e( 'YYYY-MM-DD HH:MM', 'woocommerce-auctions' ); ?>" autocomplete="off" />
					<span class="description"><?php esc_html_e( 'Date and time when the auction ends. Use YYYY-MM-DD HH:MM format or use the date picker.', 'woocommerce-auctions'); ?></span>
				</p>
			</div>
			<?php
			// Action hook for adding more fields
			do_action( 'woocommerce_product_options_auction' );
			?>
		</div>
		<script type="text/javascript">
			jQuery(document).ready(function($){
				// Datepicker for auction dates
				$('#_auction_start_date, #_auction_end_date').datetimepicker({
					dateFormat: 'yy-mm-dd',
					timeFormat: 'HH:mm',
					separator: ' ',
					showMillisec: false,
					showMicrosec: false,
					showTimezone: false,
					numberOfMonths: 1,
					showButtonPanel: true,
					stepMinute: 1,
				});

				// Show/hide auction tab based on product type
				function show_hide_auction_tab() {
					if ( $('select#product-type').val() === 'auction' ) {
						$('.show_if_auction').show();
						// Ensure our tab is selected if it's the only one relevant after 'Simple product' specific ones are hidden
						if( $('.product_data_tabs .active').is(':hidden') ) {
							$('.product_data_tabs li.auction_options').addClass('active');
							$('#auction_product_data').show();
						}
					} else {
						$('.show_if_auction').hide();
					}
				}
				show_hide_auction_tab();
				$('select#product-type').on('change', show_hide_auction_tab);

				// Ensure the 'auction_options' class is correctly targetable for the tab itself
				$('.product_data_tabs .auction_tab a').addClass('auction_options');

			});
		</script>
		<?php
	}

	/**
	 * Save auction product data.
	 *
	 * Hooked to woocommerce_process_product_meta_auction.
	 *
	 * @param int $post_id Post ID of the product being saved.
	 */
	public function save_auction_product_data( $post_id ) {
		// Sanitize and save Start Price
		$start_price = isset( $_POST['_auction_start_price'] ) ? wc_clean( wp_unslash( $_POST['_auction_start_price'] ) ) : '';
		if ( $start_price !== '' && ! is_numeric( $start_price ) ) $start_price = ''; // Ensure it's numeric or empty
		update_post_meta( $post_id, '_auction_start_price', $start_price );

		// Sanitize and save Bid Increment
		$bid_increment = isset( $_POST['_auction_bid_increment'] ) ? wc_clean( wp_unslash( $_POST['_auction_bid_increment'] ) ) : '';
		if ( $bid_increment !== '' && ( ! is_numeric( $bid_increment ) || $bid_increment < 0 ) ) $bid_increment = '';
		update_post_meta( $post_id, '_auction_bid_increment', $bid_increment );

		// Sanitize and save Reserved Price
		$reserved_price = isset( $_POST['_auction_reserved_price'] ) ? wc_clean( wp_unslash( $_POST['_auction_reserved_price'] ) ) : '';
		if ( $reserved_price !== '' && ( ! is_numeric( $reserved_price ) || $reserved_price < 0 ) ) $reserved_price = '';
		update_post_meta( $post_id, '_auction_reserved_price', $reserved_price );

		// Sanitize and save Buy it Now Price
		$buy_it_now_price = isset( $_POST['_auction_buy_it_now_price'] ) ? wc_clean( wp_unslash( $_POST['_auction_buy_it_now_price'] ) ) : '';
		if ( $buy_it_now_price !== '' && ( ! is_numeric( $buy_it_now_price ) || $buy_it_now_price < 0 ) ) $buy_it_now_price = '';
		update_post_meta( $post_id, '_auction_buy_it_now_price', $buy_it_now_price );

		// Sanitize and save Auction Start Date
		$start_date_str = isset( $_POST['_auction_start_date'] ) ? wc_clean( wp_unslash( $_POST['_auction_start_date'] ) ) : '';
		if ( ! empty( $start_date_str ) ) {
			// Validate datetime format YYYY-MM-DD HH:MM
			if ( preg_match( "/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1]) (2[0-3]|[01][0-9]):[0-5][0-9]$/", $start_date_str ) ) {
				update_post_meta( $post_id, '_auction_start_date', $start_date_str );
			} else {
				// Optionally, add an admin notice about invalid date format
				// WC_Admin_Meta_Boxes::add_error( __( 'Invalid Auction Start Date format. Please use YYYY-MM-DD HH:MM.', 'woocommerce-auctions' ) );
				update_post_meta( $post_id, '_auction_start_date', '' ); // Save as empty if invalid
			}
		} else {
			update_post_meta( $post_id, '_auction_start_date', '' );
		}

		// Sanitize and save Auction End Date
		$end_date_str = isset( $_POST['_auction_end_date'] ) ? wc_clean( wp_unslash( $_POST['_auction_end_date'] ) ) : '';
		if ( ! empty( $end_date_str ) ) {
			if ( preg_match( "/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1]) (2[0-3]|[01][0-9]):[0-5][0-9]$/", $end_date_str ) ) {
				// Further validation: end date must be after start date
				if ( ! empty( $start_date_str ) && strtotime( $end_date_str ) <= strtotime( $start_date_str ) ) {
					// WC_Admin_Meta_Boxes::add_error( __( 'Auction End Date must be after Auction Start Date.', 'woocommerce-auctions' ) );
					update_post_meta( $post_id, '_auction_end_date', '' );
				} else {
					update_post_meta( $post_id, '_auction_end_date', $end_date_str );
				}
			} else {
				// WC_Admin_Meta_Boxes::add_error( __( 'Invalid Auction End Date format. Please use YYYY-MM-DD HH:MM.', 'woocommerce-auctions' ) );
				update_post_meta( $post_id, '_auction_end_date', '' );
			}
		} else {
			update_post_meta( $post_id, '_auction_end_date', '' );
		}

		// Update the main product price to the start price for sorting/display purposes if it's empty or not an auction
		// This might be better handled when the product is first created as an auction
		$current_price = get_post_meta( $post_id, '_price', true );
		if ( $start_price !== '' && ( $current_price === '' || ! is_numeric( $current_price ) ) ) {
			update_post_meta( $post_id, '_regular_price', $start_price );
			update_post_meta( $post_id, '_price', $start_price );
			wc_delete_product_transients($post_id); // Clear price cache
		} elseif ($start_price === '') {
			// If start price is removed, perhaps clear the main prices or set to 0
			// update_post_meta( $post_id, '_regular_price', '0' );
			// update_post_meta( $post_id, '_price', '0' );
		}

		// Schedule/Unschedule auction end event
		$this->schedule_auction_end_event( $post_id, $start_date_str, $end_date_str );

	}

	/**
	 * Schedule or unschedule the auction end event.
	 *
	 * @param int $post_id Product ID.
	 * @param string $start_date_str Auction start date string.
	 * @param string $end_date_str Auction end date string.
	 */
	private function schedule_auction_end_event( $post_id, $start_date_str, $end_date_str ) {
		$event_timestamp_meta_key = '_auction_end_event_scheduled_ts';
		$hook = 'woocommerce_auction_ended';
		$args = array( 'product_id' => $post_id );

		// Get previously scheduled event timestamp
		$previously_scheduled_timestamp = get_post_meta( $post_id, $event_timestamp_meta_key, true );

		// If there was a previously scheduled event, unschedule it first
		if ( $previously_scheduled_timestamp ) {
			wp_unschedule_event( $previously_scheduled_timestamp, $hook, $args );
			delete_post_meta( $post_id, $event_timestamp_meta_key );
		}

		// Check if the auction has a valid end date and it's in the future
		if ( ! empty( $end_date_str ) ) {
			try {
				$end_datetime = new DateTime( $end_date_str, new DateTimeZone( 'UTC' ) ); // Assume dates saved in UTC or convert appropriately
				$start_datetime = !empty( $start_date_str ) ? new DateTime( $start_date_str, new DateTimeZone( 'UTC' ) ) : null;
				$now = new DateTime( 'now', new DateTimeZone( 'UTC' ) );

				// Ensure end date is after start date and in the future
				if ( $end_datetime > $now && ( !$start_datetime || $end_datetime > $start_datetime ) ) {
					$timestamp_to_schedule = $end_datetime->getTimestamp();
					wp_schedule_single_event( $timestamp_to_schedule, $hook, $args );
					update_post_meta( $post_id, $event_timestamp_meta_key, $timestamp_to_schedule );
					update_post_meta( $post_id, '_auction_closed', '0'); // Ensure auction is marked as not closed
				} else {
					// If end date is in the past, or invalid, consider closing it if not already.
					// For now, we only schedule for future dates. Existing past auctions might need a manual trigger or a separate cleanup cron.
					// If it's already past, and not closed, call the end auction handler directly.
                    $is_closed = get_post_meta( $post_id, '_auction_closed', true );
                    if ( $end_datetime <= $now && $is_closed !== '1') {
                        // If the auction end time is in the past and it's not marked as closed,
                        // trigger the end auction logic directly.
                        // This handles cases where cron might have missed or product is saved after end time.
                        if(class_exists('WC_Auctions_Manager')) { // Ensure class is loaded
                           WC_Auctions_Manager::handle_auction_end( $post_id );
                        }
                    }
				}
			} catch ( Exception $e ) {
				// Log error or add admin notice if date format is wrong
				error_log( "Error processing auction end date for product $post_id: " . $e->getMessage() );
			}
		} else {
            // No end date, ensure it's marked as not closed (unless it's a non-auction product type now)
            $product = wc_get_product($post_id);
            if ($product && $product->get_type() === 'auction') {
                 update_post_meta( $post_id, '_auction_closed', '0');
            }
        }
	}

	// Example of using the general hook if needed:
	// public function save_auction_product_data_general( $post_id, $post ) {
	// $product = wc_get_product( $post_id );
	// if ( $product && $product->get_type() === 'auction' ) {
	// $this->save_auction_product_data( $post_id );
	// }
	// }

}

new WC_Auctions_Admin_Product_Settings();
?>
