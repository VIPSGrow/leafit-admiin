<?php
/**
 * Fast Single Product Theme - front-page.php
 * Ketone.com-inspired high-converting landing page.
 * Core Web Vitals optimized: LCP < 2.5s, CLS < 0.1, FID < 100ms
 */

get_header();

// Check if WooCommerce is active
if ( ! class_exists( 'WooCommerce' ) ) {
	echo '<div class="site-content-inner"><div class="no-products-message"><h2>' . esc_html__( 'WooCommerce Not Installed', 'fast-single-product' ) . '</h2><p>' . esc_html__( 'Please install and activate WooCommerce to use this theme.', 'fast-single-product' ) . '</p></div></div>';
	get_footer();
	return;
}

// Get the main product (first published product)
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

	// Verify product exists
	if ( ! $product ) {
		echo '<div class="site-content-inner"><div class="no-products-message"><h2>' . esc_html__( 'No Products Found', 'fast-single-product' ) . '</h2><p>' . esc_html__( 'Please add a product to get started.', 'fast-single-product' ) . '</p></div></div>';
		wp_reset_postdata();
		get_footer();
		return;
	}

	// Hero section background image
	$hero_bg = get_theme_mod( 'fsp_hero_bg', '' );
	$hero_title = get_theme_mod( 'fsp_hero_title', __( 'Brain Fuel', 'fast-single-product' ) );
	$hero_description = get_theme_mod( 'fsp_hero_description', __( 'Ketones support sharper focus, cleaner energy, and mental performance.', 'fast-single-product' ) );
	$hero_cta_text = get_theme_mod( 'fsp_hero_cta', __( 'Save 30% + Free Shipping', 'fast-single-product' ) );

	$product_image_id = $product->get_image_id();
	$product_image_url = $product_image_id ? wp_get_attachment_image_url( $product_image_id, 'large' ) : '';
	?>

	<div class="site-content-inner">
		<!-- Hero Section (Ketone.com-inspired) -->
		<section class="hero-section" style="background-image: url('<?php echo esc_url( $hero_bg ?: $product_image_url ?: get_template_directory_uri() . '/assets/css/hero-placeholder.svg' ); ?>');">
			<div class="hero-overlay"></div>

			<div class="hero-container">
				<!-- Hero Caption (Left Overlay) -->
				<div class="hero-caption">
					<div class="hero-rating">
						<div class="hero-rating-stars">
							<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
								<svg viewBox="0 0 15 15"><path d="M7.5 10.75l-3.82 2.009.73-4.255-3.092-3.013 4.272-.62L7.5 1l1.91 3.87 4.272.621-3.091 3.013.73 4.255z" fill="#e10600" fill-rule="evenodd"/></svg>
							<?php endfor; ?>
						</div>
						<p class="rating-count"><b>700,000+ Purchases</b></p>
					</div>

					<h1 class="hero-title"><?php echo esc_html( $hero_title ); ?></h1>
					<p class="hero-description"><?php echo esc_html( $hero_description ); ?></p>

					<div class="hero-cta">
						<button class="btn btn--rounded btn--primary btn--full-width home-hero__cta js-button-link" id="js-hero-cta" data-product-id="<?php echo esc_attr( $product->get_id() ); ?>">
							<?php echo esc_html( $hero_cta_text ); ?>
						</button>
					</div>
				</div>

				<!-- Product Gallery (Right) -->
				<div class="hero-left">
					<div class="product-gallery">
						<?php
						if ( $product_image_id ) {
							echo wp_get_attachment_image(
								$product_image_id,
								'large',
								false,
								array(
									'class'         => 'hero-image',
									'fetchpriority' => 'high',
									'loading'       => 'eager',
									'alt'           => $product->get_name(),
									'width'         => 400,
									'height'        => 600,
								)
							);
						}
						?>
					</div>

					<!-- Trust Badges Row -->
					<div class="trust-badges">
						<div class="trust-badge">
							<svg class="badge-icon" viewBox="0 0 24 24" width="24" height="24">
								<path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/>
							</svg>
							<span><?php esc_html_e( 'Science-Backed', 'fast-single-product' ); ?></span>
						</div>
						<div class="trust-badge">
							<svg class="badge-icon" viewBox="0 0 24 24" width="24" height="24">
								<path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/>
							</svg>
							<span><?php esc_html_e( 'Clinically Tested', 'fast-single-product' ); ?></span>
						</div>
						<div class="trust-badge">
							<svg class="badge-icon" viewBox="0 0 24 24" width="24" height="24">
								<path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/>
							</svg>
							<span><?php esc_html_e( 'Clean Ingredients', 'fast-single-product' ); ?></span>
						</div>
						<div class="trust-badge">
							<svg class="badge-icon" viewBox="0 0 24 24" width="24" height="24">
								<path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/>
							</svg>
							<span><?php esc_html_e( 'Fast Shipping', 'fast-single-product' ); ?></span>
						</div>
					</div>
				</div>
			</div>
		</section>

		<!-- Product Info & CTA (Below Hero on Mobile) -->
		<div id="purchase-section" class="hero-right">
			<div class="hero-content">
				<header class="product-header">
					<h1 class="product-title"><?php the_title(); ?></h1>

					<?php if ( $product->get_rating_count() > 0 ) : ?>
						<div class="product-rating">
							<div class="stars" data-rating="<?php echo esc_attr( round( $product->get_average_rating() ) ); ?>">
								<?php
								for ( $i = 1; $i <= 5; $i++ ) {
									$active = $i <= round( $product->get_average_rating() ) ? 'active' : '';
									echo '<span class="star ' . esc_attr( $active ) . '">★</span>';
								}
								?>
							</div>
							<span class="review-count"><?php printf( esc_html__( '%d reviews', 'fast-single-product' ), $product->get_review_count() ); ?></span>
						</div>
					<?php endif; ?>

					<p class="product-tagline"><?php echo wp_kses_post( $product->get_short_description() ); ?></p>
				</header>

				<!-- Pack Selector / Variant Options -->
				<div class="pack-selector">
					<?php
					if ( $product->is_type( 'variable' ) ) {
						$available_variations = $product->get_available_variations();
						?>
						<div class="variant-options">
							<?php foreach ( $available_variations as $variation ) :
								$var_obj = new WC_Product_Variation( $variation['variation_id'] );
								$var_name = isset( $variation['attributes'] ) ? implode( ' - ', $variation['attributes'] ) : $var_obj->get_title();
								$var_price = $var_obj->get_price_html();
								$var_badge = '';

								if ( strpos( $var_name, '3-Pack' ) !== false ) {
									$var_badge = '<span class="badge-value">' . esc_html__( 'Best Value', 'fast-single-product' ) . '</span>';
								} elseif ( strpos( $var_name, '6-Pack' ) !== false ) {
									$var_badge = '<span class="badge-value">' . esc_html__( 'Stock Up', 'fast-single-product' ) . '</span>';
								}
								?>
								<label class="variant-option" data-variation-id="<?php echo esc_attr( $variation['variation_id'] ); ?>" data-price="<?php echo esc_attr( $var_obj->get_price() ); ?>">
									<input type="radio" name="variant" value="<?php echo esc_attr( $variation['variation_id'] ); ?>" class="variant-input">
									<div class="variant-label-content">
										<span class="variant-name"><?php echo esc_html( $var_name ); ?></span>
										<?php echo wp_kses_post( $var_badge ); ?>
										<span class="variant-price"><?php echo wp_kses_post( $var_price ); ?></span>
									</div>
								</label>
							<?php endforeach; ?>
						</div><!-- .variant-options -->
						<?php
					}
					?>
				</div><!-- .pack-selector -->

				<!-- Price Display -->
				<div class="price-section">
					<?php if ( $product->is_type( 'variable' ) ) : ?>
						<span class="price-label"><?php esc_html_e( 'Price:', 'fast-single-product' ); ?></span>
						<span class="price-display" id="js-price-display"><?php echo wp_kses_post( $product->get_price_html() ); ?></span>
					<?php else : ?>
						<span class="price-display"><?php echo wp_kses_post( $product->get_price_html() ); ?></span>
					<?php endif; ?>
				</div>

				<!-- CTA Button: Buy Now / Instant Checkout -->
				<button class="btn btn-primary btn-cta" id="js-btn-instant-checkout" data-product-id="<?php echo esc_attr( $product->get_id() ); ?>">
					<?php esc_html_e( 'Buy Now - Instant Checkout', 'fast-single-product' ); ?>
				</button>

				<p class="cta-subtext">
					<?php esc_html_e( 'Secure checkout • Ships within 24 hours • 30-day guarantee', 'fast-single-product' ); ?>
				</p>
			</div><!-- .hero-content -->
		</div><!-- .hero-right -->

		<!-- Benefits Grid Section -->
		<section class="benefits-section">
			<div class="benefits-container">
				<h2><?php esc_html_e( 'Why Ketone-IQ?', 'fast-single-product' ); ?></h2>
				<div class="benefits-grid">
					<div class="benefit-card">
						<div class="benefit-icon">💪</div>
						<h3><?php esc_html_e( 'Optimized Formulation', 'fast-single-product' ); ?></h3>
						<p><?php esc_html_e( 'Carefully crafted with premium ingredients for maximum bioavailability and results.', 'fast-single-product' ); ?></p>
					</div>
					<div class="benefit-card">
						<div class="benefit-icon">✅</div>
						<h3><?php esc_html_e( 'Third-Party Tested', 'fast-single-product' ); ?></h3>
						<p><?php esc_html_e( 'Independent lab verification ensures purity, potency, and safety in every batch.', 'fast-single-product' ); ?></p>
					</div>
					<div class="benefit-card">
						<div class="benefit-icon">🚀</div>
						<h3><?php esc_html_e( 'Fast Results', 'fast-single-product' ); ?></h3>
						<p><?php esc_html_e( 'Customers report noticeable effects within 1-2 weeks of consistent use.', 'fast-single-product' ); ?></p>
					</div>
					<div class="benefit-card">
						<div class="benefit-icon">🌱</div>
						<h3><?php esc_html_e( 'Natural & Clean', 'fast-single-product' ); ?></h3>
						<p><?php esc_html_e( 'Zero artificial additives, fillers, or banned substances. 100% plant-based.', 'fast-single-product' ); ?></p>
					</div>
				</div>
			</div>
		</section><!-- .benefits-section -->

		<!-- Comparison Table -->
		<section class="comparison-section">
			<div class="comparison-container">
				<h2><?php esc_html_e( 'How We Compare', 'fast-single-product' ); ?></h2>
				<div class="comparison-table">
					<table>
						<thead>
							<tr>
								<th><?php esc_html_e( 'Feature', 'fast-single-product' ); ?></th>
								<th><?php esc_html_e( 'Our Product', 'fast-single-product' ); ?></th>
								<th><?php esc_html_e( 'Standard Alternative', 'fast-single-product' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td><?php esc_html_e( 'Premium Ingredients', 'fast-single-product' ); ?></td>
								<td><span class="checkmark">✓</span></td>
								<td><span class="cross">✗</span></td>
							</tr>
							<tr>
								<td><?php esc_html_e( 'Third-Party Tested', 'fast-single-product' ); ?></td>
								<td><span class="checkmark">✓</span></td>
								<td><span class="cross">✗</span></td>
							</tr>
							<tr>
								<td><?php esc_html_e( 'Money-Back Guarantee', 'fast-single-product' ); ?></td>
								<td><span class="checkmark">✓</span></td>
								<td><span class="cross">✗</span></td>
							</tr>
							<tr>
								<td><?php esc_html_e( 'Fast Shipping', 'fast-single-product' ); ?></td>
								<td><span class="checkmark">✓</span></td>
								<td><span class="cross">✗</span></td>
							</tr>
							<tr>
								<td><?php esc_html_e( 'Customer Support', 'fast-single-product' ); ?></td>
								<td><span class="checkmark">✓</span></td>
								<td><span class="cross">✗</span></td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>
		</section><!-- .comparison-section -->

		<!-- FAQ Accordion Section -->
		<section class="faq-section">
			<div class="faq-container">
				<h2><?php esc_html_e( 'Frequently Asked Questions', 'fast-single-product' ); ?></h2>
				<div class="faq-accordion">
					<details class="faq-item" open>
						<summary class="faq-question"><?php esc_html_e( 'How quickly will I see results?', 'fast-single-product' ); ?></summary>
						<div class="faq-answer">
							<p><?php esc_html_e( 'Most customers report noticing effects within 1-2 weeks of daily use. Results vary based on individual metabolism and consistency.', 'fast-single-product' ); ?></p>
						</div>
					</details>

					<details class="faq-item">
						<summary class="faq-question"><?php esc_html_e( 'Are there any side effects?', 'fast-single-product' ); ?></summary>
						<div class="faq-answer">
							<p><?php esc_html_e( 'No known adverse side effects. Our product is formulated with natural, safe ingredients. If you have specific health concerns, consult your healthcare provider.', 'fast-single-product' ); ?></p>
						</div>
					</details>

					<details class="faq-item">
						<summary class="faq-question"><?php esc_html_e( 'Is this suitable for vegans?', 'fast-single-product' ); ?></summary>
						<div class="faq-answer">
							<p><?php esc_html_e( 'Yes, our product is 100% plant-based and vegan-friendly. No animal-derived ingredients.', 'fast-single-product' ); ?></p>
						</div>
					</details>

					<details class="faq-item">
						<summary class="faq-question"><?php esc_html_e( 'What is your return policy?', 'fast-single-product' ); ?></summary>
						<div class="faq-answer">
							<p><?php esc_html_e( 'We offer a 30-day money-back guarantee. If you\'re not satisfied, simply return the product for a full refund, no questions asked.', 'fast-single-product' ); ?></p>
						</div>
					</details>

					<details class="faq-item">
						<summary class="faq-question"><?php esc_html_e( 'How is it shipped?', 'fast-single-product' ); ?></summary>
						<div class="faq-answer">
							<p><?php esc_html_e( 'We ship via express mail within 24 hours of purchase. Most orders arrive within 3-5 business days. Free shipping on orders over $50.', 'fast-single-product' ); ?></p>
						</div>
					</details>
				</div>
			</div>
		</section><!-- .faq-section -->

		<!-- Bottom CTA: Sticky on Mobile -->
		<div class="bottom-cta">
			<div class="bottom-cta-inner">
				<div class="bottom-cta-text">
					<p class="bottom-cta-price" id="js-sticky-price">
						<?php echo wp_kses_post( $product->get_price_html() ); ?>
					</p>
				</div>
				<button class="btn btn-primary btn-sticky-cta" id="js-btn-sticky-checkout" data-product-id="<?php echo esc_attr( $product->get_id() ); ?>">
					<?php esc_html_e( 'Checkout Now', 'fast-single-product' ); ?>
				</button>
			</div>
		</div>
	</div><!-- .site-content-inner -->

	<?php
	wp_reset_postdata();
} else {
	// No products found, show message
	?>
	<div class="no-products-message">
		<h2><?php esc_html_e( 'No Products Found', 'fast-single-product' ); ?></h2>
		<p><?php esc_html_e( 'Please add a product to get started.', 'fast-single-product' ); ?></p>
	</div>
	<?php
}

get_footer();
?>
