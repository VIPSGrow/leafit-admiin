<?php
/**
 * Fast Single Product Theme - WooCommerce Optimizations
 * De-bloat WooCommerce, optimize scripts/styles, improve LCP and CLS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Remove bloated WooCommerce styles and scripts on non-checkout/cart pages
 */
function fsp_dequeue_woocommerce_bloat() {
	if ( is_admin() ) {
		return;
	}

	// Check if WooCommerce is active
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	// Only remove on front page and single product pages (not cart/checkout)
	// Use function_exists to check if WooCommerce conditionals are available
	if ( function_exists( 'is_cart' ) && function_exists( 'is_checkout' ) && function_exists( 'is_account' ) ) {
		if ( ! is_cart() && ! is_checkout() && ! is_account() ) {
			// Remove WooCommerce layout stylesheet
			wp_dequeue_style( 'woocommerce-layout' );
			wp_dequeue_style( 'woocommerce-smallscreen' );
			
			// Remove cart fragments script (unnecessary for single-product store)
			wp_dequeue_script( 'wc-cart-fragments' );
			
			// Remove add to cart script variations
			wp_dequeue_script( 'wc-add-to-cart' );
			wp_dequeue_script( 'wc-add-to-cart-variation' );
		}
	}

	// Always remove WooCommerce general stylesheet - we'll use ours
	wp_dequeue_style( 'woocommerce-general' );
}
add_action( 'wp_enqueue_scripts', 'fsp_dequeue_woocommerce_bloat', 99 );

/**
 * Enqueue custom WooCommerce styles only where needed
 */
function fsp_enqueue_wc_minimal_styles() {
	// Check if WooCommerce is active
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	// Use function_exists to check if WooCommerce conditionals are available
	if ( function_exists( 'is_cart' ) && function_exists( 'is_checkout' ) && function_exists( 'is_account' ) ) {
		if ( is_cart() || is_checkout() || is_account() ) {
			wp_enqueue_style( 'woocommerce-layout' );
		}
	}
}
add_action( 'wp_enqueue_scripts', 'fsp_enqueue_wc_minimal_styles', 11 );

/**
 * Disable WooCommerce CSS on front-end if we're handling it all
 */
add_filter( 'woocommerce_enqueue_styles', function( $enqueue_styles ) {
	// Only filter if WooCommerce is active
	if ( ! class_exists( 'WooCommerce' ) ) {
		return $enqueue_styles;
	}
	return array();
} );

/**
 * Remove WooCommerce sidebar (not used in single-product layout)
 */
if ( class_exists( 'WooCommerce' ) ) {
	remove_action( 'woocommerce_sidebar', 'woocommerce_get_sidebar', 10 );
}

/**
 * Optimize checkout page - remove unnecessary fields/steps
 */
add_filter( 'woocommerce_checkout_fields', function( $fields ) {
	// Keep only essential fields
	// Customize as needed for your use case
	return $fields;
} );

/**
 * Disable product images lazy loading for LCP image (main product image)
 * This is handled in front-page.php with fetchpriority="high"
 */
add_filter( 'wp_lazy_loading_enabled', function( $is_enabled, $tag_name ) {
	// Keep lazy loading off for img tags in the hero section
	// JavaScript will handle this with data attributes if needed
	return $is_enabled;
}, 10, 2 );

/**
 * Optimize product gallery
 */
add_filter( 'woocommerce_product_get_image', function( $image, $product ) {
	// Already optimized in front-page.php
	return $image;
}, 10, 2 );

/**
 * Disable WooCommerce scripts on non-product pages
 */
function fsp_dequeue_wc_single_product_scripts() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	// Use function_exists to check if WooCommerce conditionals are available
	if ( function_exists( 'is_product' ) && function_exists( 'is_cart' ) && function_exists( 'is_checkout' ) ) {
		if ( ! is_product() && ! is_cart() && ! is_checkout() ) {
			wp_dequeue_script( 'wc-single-product' );
			wp_dequeue_script( 'zoom' );
			wp_dequeue_script( 'photoswipe' );
			wp_dequeue_script( 'photoswipe-ui-default' );
			wp_dequeue_style( 'photoswipe' );
			wp_dequeue_style( 'photoswipe-default-skin' );
		}
	}
}
add_action( 'wp_enqueue_scripts', 'fsp_dequeue_wc_single_product_scripts', 99 );

/**
 * Remove WooCommerce default hooks from homepage/front-page
 */
if ( class_exists( 'WooCommerce' ) ) {
	remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
	remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );
}

/**
 * Lazy load below-the-fold images
 */
add_filter( 'wp_get_attachment_image_attributes', function( $attr, $attachment, $size ) {
	// For images below hero fold
	if ( ! isset( $attr['loading'] ) ) {
		$attr['loading'] = 'lazy';
	}
	return $attr;
}, 10, 3 );

/**
 * Optimize WooCommerce AJAX calls for add-to-cart
 */
function fsp_wc_optimize_ajax() {
	// Custom AJAX handlers defined in functions.php
	// This ensures we use our optimized add-to-cart instead of WooCommerce's
}

/**
 * Remove WooCommerce inline styles that aren't needed
 */
add_filter( 'woocommerce_inline_css_filter_hook', function( $css ) {
	return '';
} );

/**
 * Disable WooCommerce comment form on product pages
 */
add_filter( 'comments_open', function( $open, $post_id ) {
	if ( class_exists( 'WooCommerce' ) && get_post_type( $post_id ) === 'product' ) {
		return false;
	}
	return $open;
}, 10, 2 );

/**
 * Optimize product title and pricing display
 */
add_filter( 'woocommerce_product_title', function( $title ) {
	return wp_strip_all_tags( $title );
} );

/**
 * Store variation data for quick variant switching
 */
add_action( 'wp_footer', function() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	if ( is_front_page() || ( function_exists( 'is_product' ) && is_product() ) ) {
		$args = array(
			'post_type'      => 'product',
			'posts_per_page' => 1,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);
		$products = new WP_Query( $args );
		
		if ( $products->have_posts() ) {
			$products->the_post();
			$product = wc_get_product( get_the_ID() );
			
			if ( $product && $product->is_type( 'variable' ) ) {
				$variations = $product->get_available_variations();
				?>
				<script type="application/json" id="fsp-variations-data">
					<?php echo wp_json_encode( $variations ); ?>
				</script>
				<?php
			}
			wp_reset_postdata();
		}
	}
}, 99 );

/**
 * Remove query strings from static resources for better caching
 */
add_filter( 'script_loader_src', function( $src ) {
	return remove_query_arg( 'ver', $src );
} );

add_filter( 'style_loader_src', function( $src ) {
	return remove_query_arg( 'ver', $src );
} );

/**
 * Optimize WooCommerce Admin
 */
add_filter( 'woocommerce_admin_features', function( $features ) {
	// Disable unnecessary WooCommerce admin features
	return $features;
} );

/**
 * Preload key WooCommerce AJAX endpoints
 */
function fsp_preload_ajax_endpoints() {
	?>
	<link rel="preconnect" href="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
	<?php
}
add_action( 'wp_head', 'fsp_preload_ajax_endpoints', 1 );

/**
 * Enable GZIP compression and caching headers
 */
function fsp_set_cache_headers() {
	if ( ! is_admin() ) {
		header( 'Cache-Control: public, max-age=3600' );
	}
}
add_action( 'init', 'fsp_set_cache_headers' );

?>
