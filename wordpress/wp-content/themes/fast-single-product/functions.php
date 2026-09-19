<?php
/**
 * Fast Single Product Theme - functions.php
 * 
 * Core setup, asset enqueue, WooCommerce optimizations, and cleanup rules.
 * Performance-focused: minimal hooks, aggressive bloat removal.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FSP_VERSION', '1.0.0' );
define( 'FSP_THEME_DIR', get_template_directory() );
define( 'FSP_THEME_URI', get_template_directory_uri() );
define( 'FSP_ASSETS_URI', FSP_THEME_URI . '/assets' );

/**
 * Theme Setup & Support
 */
function fsp_theme_setup() {
	// Enable automatic feed link
	add_theme_support( 'automatic-feed-links' );
	
	// Enable title tag support
	add_theme_support( 'title-tag' );
	
	// Enable WooCommerce support
	add_theme_support( 'woocommerce' );
	add_theme_support( 'wc-product-gallery-zoom' );
	add_theme_support( 'wc-product-gallery-slider' );
	add_theme_support( 'wc-product-gallery-lightbox' );
	
	// Custom logo
	add_theme_support( 'custom-logo', array(
		'height'      => 60,
		'width'       => 200,
		'flex-height' => true,
		'flex-width'  => true,
	) );
	
	// HTML5 support
	add_theme_support( 'html5', array(
		'search-form',
		'comment-form',
		'comment-list',
		'gallery',
		'caption',
		'script',
		'style',
	) );
	
	// Register navigation menus
	register_nav_menus( array(
		'primary' => esc_html__( 'Primary Menu', 'fast-single-product' ),
		'footer'  => esc_html__( 'Footer Menu', 'fast-single-product' ),
	) );
}
add_action( 'after_setup_theme', 'fsp_theme_setup' );

/**
 * Customizer settings for hero section
 */
function fsp_customize_register( $wp_customize ) {
	$wp_customize->add_section( 'fsp_hero_section', array(
		'title'    => __( 'Hero Section', 'fast-single-product' ),
		'priority' => 120,
	) );

	$wp_customize->add_setting( 'fsp_hero_title', array(
		'default'   => 'Brain Fuel',
		'transport' => 'refresh',
	) );

	$wp_customize->add_control( 'fsp_hero_title', array(
		'label'   => __( 'Hero Title', 'fast-single-product' ),
		'section' => 'fsp_hero_section',
	) );

	$wp_customize->add_setting( 'fsp_hero_description', array(
		'default'   => 'Ketones support sharper focus, cleaner energy, and mental performance.',
		'transport' => 'refresh',
	) );

	$wp_customize->add_control( 'fsp_hero_description', array(
		'label'   => __( 'Hero Description', 'fast-single-product' ),
		'section' => 'fsp_hero_section',
	) );

	$wp_customize->add_setting( 'fsp_hero_cta', array(
		'default'   => 'Save 30% + Free Shipping',
		'transport' => 'refresh',
	) );

	$wp_customize->add_control( 'fsp_hero_cta', array(
		'label'   => __( 'Hero CTA Text', 'fast-single-product' ),
		'section' => 'fsp_hero_section',
	) );

	$wp_customize->add_setting( 'fsp_hero_bg', array(
		'default'   => '',
		'transport' => 'refresh',
	) );

	$wp_customize->add_control( new WP_Customize_Image_Control( $wp_customize, 'fsp_hero_bg', array(
		'label'   => __( 'Hero Background Image', 'fast-single-product' ),
		'section' => 'fsp_hero_section',
	) ) );
}
add_action( 'customize_register', 'fsp_customize_register' );

/**
 * Enqueue stylesheets
 * Critical CSS inlined, main CSS with preload/defer
 */
function fsp_enqueue_styles() {
	// Main theme stylesheet with preload
	wp_enqueue_style(
		'fsp-main',
		FSP_ASSETS_URI . '/css/main.css',
		array(),
		FSP_VERSION,
		'all'
	);
	
	// Add preload link header for faster delivery
	add_filter( 'style_loader_tag', function( $tag, $handle ) {
		if ( 'fsp-main' === $handle ) {
			return str_replace(
				"rel='stylesheet'",
				"rel='preload' as='style' onload=\"this.rel='stylesheet'\"",
				$tag
			);
		}
		return $tag;
	}, 10, 2 );
}
add_action( 'wp_enqueue_scripts', 'fsp_enqueue_styles', 1 );

/**
 * Enqueue scripts
 * Main JS deferred, no dependencies on jQuery
 */
function fsp_enqueue_scripts() {
	// Main theme script (deferred)
	wp_enqueue_script(
		'fsp-main',
		FSP_ASSETS_URI . '/js/main.js',
		array(),
		FSP_VERSION,
		array(
			'strategy' => 'defer',
		)
	);
	
	// Pass AJAX URL to JS
	wp_localize_script( 'fsp-main', 'fspAjax', array(
		'url'   => admin_url( 'admin-ajax.php' ),
		'nonce' => wp_create_nonce( 'fsp_nonce' ),
	) );
}
add_action( 'wp_enqueue_scripts', 'fsp_enqueue_scripts', 2 );

/**
 * Load WooCommerce optimizations
 */
require_once FSP_THEME_DIR . '/inc/woocommerce-optimizations.php';

/**
 * Aggressive Cleanup - Remove Bloat
 */

// Remove WordPress generator meta tag
remove_action( 'wp_head', 'wp_generator' );

// Remove emoji styles and scripts
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );
remove_filter( 'wp_mail', 'wp_staticize_emoji_in_email' );

// Remove REST API links from head (not needed for DTC store)
remove_action( 'wp_head', 'rest_output_link_wp_head' );
remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );

// Remove self-hosted XML-RPC (security + performance)
add_filter( 'xmlrpc_enabled', '__return_false' );

// Disable pingbacks
add_filter( 'xmlrpc_methods', function( $methods ) {
	unset( $methods['pingback.ping'] );
	unset( $methods['pingback.extensions.getPingbacks'] );
	return $methods;
} );

// Remove Windows Live Writer tag
remove_action( 'wp_head', 'wlwmanifest_link' );

// Clean up comment form (prevent unnecessary JS)
add_filter( 'comment_form_defaults', function( $defaults ) {
	$defaults['comment_field'] = '<p class="comment-form-comment"><label for="comment">' . _x( 'Comment', 'noun' ) . '</label><textarea id="comment" name="comment" cols="45" rows="8" required></textarea></p>';
	return $defaults;
} );

/**
 * Disable gutenberg on pages/posts if not needed for single product theme
 */
add_filter( 'use_block_editor_for_post_type', function( $use_block_editor, $post_type ) {
	if ( in_array( $post_type, array( 'page', 'post' ), true ) ) {
		return false;
	}
	return $use_block_editor;
}, 10, 2 );

/**
 * Optimize DOM footprint - Remove admin bar on frontend if not needed
 */
if ( ! current_user_can( 'manage_options' ) ) {
	show_admin_bar( false );
}

/**
 * Set blog to private (faster)
 */
function fsp_set_blog_privacy() {
	// Check and set blog visibility if needed
	$blog_public = get_option( 'blog_public' );
	if ( ! isset( $blog_public ) || empty( $blog_public ) ) {
		update_option( 'blog_public', '1' );
	}
}
add_action( 'wp_loaded', 'fsp_set_blog_privacy' );

/**
 * Disable search (not needed for single product store)
 * Prevent s query param waste
 */
function fsp_disable_search() {
	if ( is_search() ) {
		wp_redirect( home_url( '/' ) );
		exit;
	}
}
add_action( 'template_redirect', 'fsp_disable_search' );

/**
 * Custom logo output in header
 */
function fsp_get_logo_html() {
	if ( has_custom_logo() ) {
		return get_custom_logo();
	}
	// Fallback: simple text logo
	return '<a href="' . esc_url( home_url( '/' ) ) . '" class="logo-text">' .
		   esc_html( get_bloginfo( 'name' ) ) .
		   '</a>';
}

/**
 * Register navigation menu item CSS classes for Ketone-style nav
 */
function fsp_nav_menu_css_class( $classes, $item, $args ) {
	if ( $args->theme_location === 'primary' ) {
		$classes[] = 'navigation__link';
	} elseif ( $args->theme_location === 'footer' ) {
		$classes[] = 'footer-link';
	}
	return $classes;
}
add_filter( 'nav_menu_css_class', 'fsp_nav_menu_css_class', 10, 3 );

/**
 * AJAX Add-to-Cart Handler
 * Direct Add-to-Cart + redirect to checkout
 */
function fsp_ajax_add_to_cart() {
	check_ajax_referer( 'fsp_nonce', 'nonce' );
	
	// Ensure WooCommerce is active
	if ( ! class_exists( 'WooCommerce' ) ) {
		wp_send_json_error( 'WooCommerce not available' );
	}
	
	if ( ! isset( $_POST['product_id'] ) || ! isset( $_POST['quantity'] ) ) {
		wp_send_json_error( 'Invalid request' );
	}
	
	$product_id = absint( $_POST['product_id'] );
	$quantity   = absint( $_POST['quantity'] ) ?: 1;
	$variation_id = isset( $_POST['variation_id'] ) ? absint( $_POST['variation_id'] ) : 0;
	
	// Add to cart
	if ( $variation_id > 0 ) {
		WC()->cart->add_to_cart( $product_id, $quantity, $variation_id );
	} else {
		WC()->cart->add_to_cart( $product_id, $quantity );
	}
	
	wp_send_json_success( array(
		'redirect' => wc_get_checkout_url(),
	) );
}
add_action( 'wp_ajax_fsp_add_to_cart', 'fsp_ajax_add_to_cart' );
add_action( 'wp_ajax_nopriv_fsp_add_to_cart', 'fsp_ajax_add_to_cart' );

/**
 * Get variant pricing via AJAX
 */
function fsp_get_variant_price() {
	check_ajax_referer( 'fsp_nonce', 'nonce' );
	
	// Ensure WooCommerce is active
	if ( ! class_exists( 'WooCommerce' ) ) {
		wp_send_json_error( 'WooCommerce not available' );
	}
	
	if ( ! isset( $_POST['variation_id'] ) ) {
		wp_send_json_error( 'Invalid variation' );
	}
	
	$variation_id = absint( $_POST['variation_id'] );
	$variation    = new WC_Product_Variation( $variation_id );
	
	if ( ! $variation->get_id() ) {
		wp_send_json_error( 'Variation not found' );
	}
	
	wp_send_json_success( array(
		'price'     => $variation->get_price_html(),
		'price_raw' => $variation->get_price(),
	) );
}
add_action( 'wp_ajax_fsp_get_variant_price', 'fsp_get_variant_price' );
add_action( 'wp_ajax_nopriv_fsp_get_variant_price', 'fsp_get_variant_price' );

/**
 * Load custom theme features
 */
function fsp_load_custom_features() {
	/**
	 * Sticky CTA visible only on mobile
	 * Added via JS for better control
	 */
	
	/**
	 * FAQ Accordion
	 * Pure HTML5 details/summary or lightweight JS
	 */
}
add_action( 'wp_body_open', 'fsp_load_custom_features' );

?>
