<?php
/**
 * Fast Single Product Theme - woocommerce.php
 * Direct checkout layout - minimal, optimized for conversion
 */

get_header();
?>

<div class="site-content-inner woocommerce-content">
	<div class="woocommerce-container">
		<?php
		// Use function_exists to check if WooCommerce conditionals are available
		if ( function_exists( 'is_cart' ) && is_cart() ) {
			// Cart page
			echo '<div class="cart-page-wrapper">';
			woocommerce_cart();
			echo '</div>';
		} elseif ( function_exists( 'is_checkout' ) && is_checkout() ) {
			// Checkout page - ultra-lean, distraction-free
			?>
			<div class="checkout-page-wrapper">
				<div class="checkout-header">
					<h1><?php esc_html_e( 'Secure Checkout', 'fast-single-product' ); ?></h1>
					<p class="checkout-subtext"><?php esc_html_e( 'Your order is protected by our 30-day money-back guarantee', 'fast-single-product' ); ?></p>
				</div>
				<?php
				woocommerce_checkout();
				?>
			</div>
			<?php
		} elseif ( function_exists( 'is_account' ) && is_account() ) {
			// Account page
			echo '<div class="account-page-wrapper">';
			woocommerce_account_orders();
			echo '</div>';
		} else {
			// Product/archive pages - fallback to default WooCommerce template
			woocommerce_content();
		}
		?>
	</div>
</div>

<?php
get_footer();
?>
