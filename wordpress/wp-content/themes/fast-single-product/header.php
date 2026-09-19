<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="profile" href="https://gmpg.org/xfn/11">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
	<?php wp_body_open(); ?>

	<div id="page" class="site">
		<a class="skip-link screen-reader-text" href="#main"><?php esc_html_e( 'Skip to content', 'fast-single-product' ); ?></a>

		<!-- Announcement Bar -->
		<div class="announcement-bar">
			<div class="announcement-content">
				<span class="announcement-badge">✨ Exclusive Offer</span>
				<span class="announcement-text"><?php esc_html_e( 'Free U.S. Shipping Over $70', 'fast-single-product' ); ?></span>
			</div>
		</div>

		<!-- Fixed Navigation Header -->
		<nav class="navigation navigation--desktop navigation--fixed navigation--bordered" data-fixed-top>
			<div class="navigation-bar navigation-bar--desktop navigation-bar--bordered">
				<div class="centered-vertical">
					<!-- Logo -->
					<div class="navigation__logo">
						<?php
						if ( has_custom_logo() ) {
							the_custom_logo();
						} else {
							?>
							<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="logo-text">
								<?php echo esc_html( get_bloginfo( 'name' ) ); ?>
							</a>
							<?php
						}
						?>
					</div>

					<!-- Shipping Info -->
					<div class="navigation__separator"></div>
					<div class="navigation__shipping-info">
						<div class="navigation__shipping-info-item">
							<svg fill="none" height="24" viewBox="0 0 24 24" width="24" xmlns="http://www.w3.org/2000/svg">
								<path clip-rule="evenodd" d="M21.0933 17.4493L12.4538 20.926V9.97288L21.0933 6.49616V17.4493ZM2.90665 6.49616L5.64945 7.59244L5.6286 12.7107C5.6286 12.8792 5.73585 13.0477 5.90864 13.1106L8.00397 13.9745C8.06852 13.9958 8.11221 14.0161 8.17676 14.0161C8.26079 14.0182 8.3436 13.9963 8.41509 13.9532C8.53775 13.8707 8.61665 13.7395 8.63059 13.5949L8.73883 8.85626L11.5462 9.99322V20.925L2.90665 17.4493V6.49616ZM11.7627 2.93713L13.6415 3.58986L5.95233 6.77023L3.70606 5.86474L11.7627 2.93713ZM12.4538 9.02477L12 9.19328L11.8709 9.15067L9.2999 8.11831L17.1619 4.81204L20.2502 5.88604L12.4538 9.02477ZM6.57895 7.99241L6.75174 8.05536L7.83118 8.49794L7.74479 12.8995L6.5571 12.4366L6.57895 7.99241ZM15.9096 4.36946L8.13406 7.63409L7.11817 7.23412L14.8937 4.01113L15.9096 4.36946ZM22 5.84343L21.9782 5.73884L21.9573 5.69623L21.8918 5.59067L21.8272 5.52772L21.7845 5.50641L21.6753 5.46477L11.9136 2.03163C11.8176 1.98946 11.7077 1.98946 11.6117 2.03163L2.30189 5.42216L2.17279 5.48608L2.0864 5.57033C2.0864 5.57033 2.02185 5.69623 2.02185 5.71753C2.02185 5.73884 2 5.78048 2 5.82309V17.7447C2 17.9345 2.10824 18.0817 2.28103 18.1446L11.8699 21.9787L11.9791 22L12.1082 21.9787L21.719 18.1446C21.8878 18.08 21.9991 17.9215 22 17.7447V5.84343Z" fill-rule="evenodd" fill="black" id="Shape"/>
							</g>
							</svg>
						</div>
						<p><?php esc_html_e( 'Free U.S. Shipping Over $70', 'fast-single-product' ); ?></p>
					</div>
				</div>

				<!-- Horizontal Separator -->
				<div class="navigation__separator-horizontal"></div>

				<!-- Centered Navigation Links -->
				<div class="navigation-bar navigation-bar--desktop navigation-bar--centered">
					<?php
					wp_nav_menu( array(
						'theme_location' => 'primary',
						'fallback_cb'    => '',
						'container'      => false,
						'items_wrap'     => '<div class="navigation__navigation-group" id="primary-navigation-group">%3$s</div>',
						'depth'          => 1,
						'link_class'     => 'navigation__link',
					) );
					?>

					<a class="navigation__link" id="products-navigation-link" href="#purchase-section">
						<span><?php esc_html_e( 'Shop', 'fast-single-product' ); ?></span>
						<svg width="20px" height="20px" viewBox="0 0 20 20" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:sketch="http://www.bohemiancoding.com/sketch/ns">
							<path fill="none" d="M14,8 L10,12 L6,8" stroke-linecap="round"></path>
						</svg>
					</a>
				</div>
			</div>
		</nav>

		<!-- Mobile Navigation -->
		<nav class="navigation navigation--mobile navigation--fixed navigation--bordered" id="mobile-navigation-bar" data-fixed-top>
			<div class="navigation__navigation-group--mobile-v2">
				<div class="navigation__mobile-item js-navigation-burger" id="navigation-burger">
					<div class="navigation__mobile-icon">
						<button class="menu-toggle" aria-label="<?php esc_attr_e( 'Toggle menu', 'fast-single-product' ); ?>">
							<span class="burger-icon__bar burger-icon__bar--top"></span>
							<span class="burger-icon__bar burger-icon__bar--middle"></span>
							<span class="burger-icon__bar burger-icon__bar--bottom"></span>
						</button>
					</div>
					<div class="navigation__mobile-icon-text"><?php esc_html_e( 'MENU', 'fast-single-product' ); ?></div>
				</div>

				<div class="navigation__mobile-icon-back hidden" id="mobile-navigation-back">
					<svg width="20px" height="20px" viewBox="0 0 20 20" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:sketch="http://www.bohemiancoding.com/sketch/ns">
						<path fill="none" d="M14,8 L10,12 L6,8" stroke-linecap="round"></path>
					</svg>
				</div>

				<!-- Mobile Logo -->
				<div class="navigation__logo">
					<?php
					if ( has_custom_logo() ) {
						the_custom_logo();
					} else {
						?>
						<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="logo-text">
							<?php echo esc_html( get_bloginfo( 'name' ) ); ?>
						</a>
						<?php
					}
					?>
				</div>

				<!-- Mobile Cart -->
				<?php if ( class_exists( 'WooCommerce' ) ) : ?>
					<div class="navigation__mobile-item js-cart-popup">
						<div class="navigation__mobile-icon">
							<a href="<?php echo esc_url( wc_get_cart_url() ); ?>" class="cart-link-mobile" title="<?php esc_attr_e( 'View Cart', 'fast-single-product' ); ?>">
								<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
									<path d="M15.75 10.5V6C15.75 3.92893 14.0711 2.25 12 2.25C9.92893 2.25 8.25 3.92893 8.25 6V10.5M19.606 8.50723L20.8692 20.5072C20.9391 21.1715 20.4183 21.75 19.7504 21.75H4.24963C3.58172 21.75 3.06089 21.1715 3.13081 20.5072L4.39397 8.50723C4.45424 7.93466 4.93706 7.5 5.51279 7.5H18.4872C19.0629 7.5 19.5458 7.93466 19.606 8.50723ZM8.625 10.5C8.625 10.7071 8.4571 10.875 8.25 10.875C8.04289 10.875 7.875 10.7071 7.875 10.5C7.875 10.2929 8.04289 10.125 8.25 10.125C8.4571 10.125 8.625 10.2929 8.625 10.5ZM16.125 10.5C16.125 10.7071 15.9571 10.875 15.75 10.875C15.5429 10.875 15.375 10.7071 15.375 10.5C15.375 10.2929 15.5429 10.125 15.75 10.125C15.9571 10.125 16.125 10.2929 16.125 10.5Z" stroke="#313131" stroke-width="1.5"/>
								</svg>
							</a>
							<?php if ( WC()->cart->get_cart_contents_count() > 0 ) : ?>
								<div class="navigation__cart-count js-cart-count">
									<?php echo absint( WC()->cart->get_cart_contents_count() ); ?>
								</div>
							<?php endif; ?>
						</div>
						<div class="navigation__mobile-icon-text"><?php esc_html_e( 'CART', 'fast-single-product' ); ?></div>
					</div>
				<?php endif; ?>
			</div>
		</nav>

		<main id="main" class="site-main">
