<?php
/**
 * Plugin Name: WooCommerce Auctions
 * Plugin URI: https://example.com/woocommerce-auctions
 * Description: A plugin to add auction functionality to WooCommerce.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: woocommerce-auctions
 * Domain Path: /languages
 */

// Define plugin version for use in activation and updates.
define( 'WC_AUCTIONS_VERSION', '1.0.0' );
define( 'WC_AUCTIONS_PLUGIN_FILE', __FILE__ );

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Activation hook.
 */
function woocommerce_auctions_activate() {
	// Store plugin version.
	update_option( 'woocommerce_auctions_version', WC_AUCTIONS_VERSION );

	// Flush rewrite rules (good practice, though not strictly needed for just a product type).
	flush_rewrite_rules();

	// You could also set up default settings, roles, or capabilities here if needed.
}
register_activation_hook( WC_AUCTIONS_PLUGIN_FILE, 'woocommerce_auctions_activate' );

/**
 * Deactivation hook.
 */
function woocommerce_auctions_deactivate() {
	// Remove plugin version option.
	// delete_option( 'woocommerce_auctions_version' ); // Or keep for potential re-activation logic

	// Unschedule all pending auction end events.
	$args = array(
		'post_type'      => 'product',
		'posts_per_page' => -1,
		'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			array(
				'taxonomy' => 'product_type',
				'field'    => 'slug',
				'terms'    => 'auction',
			),
		),
		'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			array(
				'key'     => '_auction_end_event_scheduled_ts',
				'compare' => 'EXISTS',
			),
		),
		'fields'         => 'ids', // Only get post IDs
	);
	$auction_products = get_posts( $args );

	if ( ! empty( $auction_products ) ) {
		foreach ( $auction_products as $product_id ) {
			$timestamp = get_post_meta( $product_id, '_auction_end_event_scheduled_ts', true );
			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, 'woocommerce_auction_ended', array( 'product_id' => $product_id ) );
				// delete_post_meta( $product_id, '_auction_end_event_scheduled_ts' ); // Optionally clear the meta
			}
		}
	}

	// Flush rewrite rules again.
	flush_rewrite_rules();
}
register_deactivation_hook( WC_AUCTIONS_PLUGIN_FILE, 'woocommerce_auctions_deactivate' );


/**
 * Placeholder function to confirm the plugin is active.
 */
function woocommerce_auctions_activation_check() {
	if ( is_admin() ) {
		add_action( 'admin_notices', function() {
			?>
			<div class="notice notice-success is-dismissible">
				<p><?php _e( 'WooCommerce Auctions plugin is active!', 'woocommerce-auctions' ); ?></p>
			</div>
			<?php
		} );
	}
}
add_action( 'admin_init', 'woocommerce_auctions_activation_check' );

/**
 * Include core files.
 */
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wc-product-auction.php';
if ( is_admin() ) { // Load admin-specific files only in admin
	require_once plugin_dir_path( __FILE__ ) . 'includes/admin/class-wc-auctions-admin-product-settings.php';
}
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wc-auctions-bidding.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wc-auctions-manager.php';

/**
 * Adds 'auction' to the product type selector.
 *
 * @param array $types Existing product types.
 * @return array Modified product types.
 */
function woocommerce_auctions_add_product_type( $types ) {
	$types['auction'] = __( 'Auction', 'woocommerce-auctions' );
	return $types;
}
add_filter( 'product_type_selector', 'woocommerce_auctions_add_product_type' );

/**
 * Tells WooCommerce to use our WC_Product_Auction class for 'auction' products.
 *
 * @param string $classname Name of the class.
 * @param string $product_type Type of the product.
 * @param int    $product_id ID of the product.
 * @return string Class name.
 */
function woocommerce_auctions_product_class( $classname, $product_type, $product_id ) {
	if ( 'auction' === $product_type ) {
		$classname = 'WC_Product_Auction';
	}
	return $classname;
}
add_filter( 'woocommerce_product_class', 'woocommerce_auctions_product_class', 10, 3 );

/**
 * Load plugin templates.
 *
 * Looks for template files in theme_directory/woocommerce-auctions/ then plugin_directory/templates/.
 *
 * @param string $template Original template path.
 * @param string $template_name Template name.
 * @param string $template_path Path to templates.
 * @return string The path to the template file.
 */
function woocommerce_auctions_locate_template( $template, $template_name, $template_path ) {
	global $woocommerce;

	$_template = $template;

	if ( ! $template_path ) {
		$template_path = $woocommerce->template_url;
	}

	// Set plugin specific template path
	$plugin_template_path = untrailingslashit( plugin_dir_path( __FILE__ ) ) . '/templates/';

	// Look within passed path within the theme - this is priority
	$template = locate_template(
		array(
			trailingslashit( $template_path ) . 'woocommerce-auctions/' . $template_name, // Theme specific path for this plugin's templates
			$template_name,
		)
	);

	// Get the template from this plugin, if it exists
	if ( ! $template && file_exists( $plugin_template_path . $template_name ) ) {
		$template = $plugin_template_path . $template_name;
	}

	if ( ! $template ) {
		$template = $_template; // Default to original WC template if not found
	}

	return $template;
}
add_filter( 'woocommerce_locate_template', 'woocommerce_auctions_locate_template', 10, 3 );

/**
 * Get template part.
 *
 * @param string $slug
 * @param string $name (default: '')
 */
function woocommerce_auctions_get_template_part( $slug, $name = '' ) {
    $template = '';
    // Look in yourtheme/slug-name.php and yourtheme/woocommerce/slug-name.php
    if ( $name ) {
        $template = locate_template( array( "{$slug}-{$name}.php", WC()->template_path() . "{$slug}-{$name}.php" ) );
    }

    // Get default slug-name.php
    if ( ! $template && $name && file_exists( plugin_dir_path( __FILE__ ) . "/templates/{$slug}-{$name}.php" ) ) {
        $template = plugin_dir_path( __FILE__ ) . "/templates/{$slug}-{$name}.php";
    }

    // If template file doesn't exist, look in yourtheme/slug.php and yourtheme/woocommerce/slug.php
    if ( ! $template ) {
        $template = locate_template( array( "{$slug}.php", WC()->template_path() . "{$slug}.php" ) );
    }

    if ( $template ) {
        load_template( $template, false );
    }
}

/**
 * Add the auction add to cart template to the single product page for auction products.
 */
function woocommerce_auctions_auction_add_to_cart() {
    global $product;
    if ( $product && 'auction' === $product->get_type() ) {
        // This will use the woocommerce_locate_template filter we added
        wc_get_template( 'single-product/add-to-cart/auction.php' );
    }
}
// Remove default add-to-cart for auctions and add our own
add_action( 'woocommerce_single_product_summary', function() {
    global $product;
    if ( $product && 'auction' === $product->get_type() ) {
        remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
        add_action( 'woocommerce_single_product_summary', 'woocommerce_auctions_auction_add_to_cart', 30 );
    }
}, 1 );


// More plugin code will go here.
?>
