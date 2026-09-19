		</main><!-- #main -->

		<!-- Footer Top Link / Back to Top on scroll -->
		<a href="#" class="footer-back-to-top" aria-label="<?php esc_attr_e( 'Back to top', 'fast-single-product' ); ?>">
			<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
				<path d="M10 15L4 9H16L10 15Z" fill="white"/>
			</svg>
		</a>

		<footer id="colophon" class="site-footer">
			<div class="site-footer-inner">
				<div class="footer-content">
					<div class="footer-column footer-about">
						<h3><?php echo esc_html( get_bloginfo( 'name' ) ); ?></h3>
						<p><?php echo esc_html( get_bloginfo( 'description' ) ); ?></p>

						<?php
						// Social icon links (reuses Ketone-style nav icons)
						$custom_logo_id = get_theme_mod( 'custom_logo' );
						if ( $custom_logo_id ) {
							$logo = wp_get_attachment_image_src( $custom_logo_id, 'full' );
						}
						?>
						<div class="footer-social">
							<a href="#" class="footer-social-link" aria-label="Facebook"><span>fb</span></a>
							<a href="#" class="footer-social-link" aria-label="Instagram"><span>ig</span></a>
							<a href="#" class="footer-social-link" aria-label="Twitter"><span>x</span></a>
						</div>
					</div>

					<div class="footer-column footer-links">
						<h4><?php esc_html_e( 'Quick Links', 'fast-single-product' ); ?></h4>
						<?php
						wp_nav_menu( array(
							'theme_location' => 'footer',
							'fallback_cb'    => '',
							'container'      => false,
							'items_wrap'     => '<ul class="footer-menu">%3$s</ul>',
							'depth'          => 1,
						) )
						?>
					</div>

					<div class="footer-column footer-contact">
						<h4><?php esc_html_e( 'Contact', 'fast-single-product' ); ?></h4>
						<p>
							<a href="mailto:<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">
								<?php echo esc_html( get_option( 'admin_email' ) ); ?>
							</a>
						</p>
						<p><?php esc_html_e( '1-833-KETONE-IQ', 'fast-single-product' ); ?></p>
					</div>

					<div class="footer-column footer-info">
						<h4><?php esc_html_e( 'Legal', 'fast-single-product' ); ?></h4>
						<ul class="footer-menu">
							<li><a href="#"><?php esc_html_e( 'Privacy Policy', 'fast-single-product' ); ?></a></li>
							<li><a href="#"><?php esc_html_e( 'Terms of Service', 'fast-single-product' ); ?></a></li>
							<li><a href="#"><?php esc_html_e( 'Shipping Policy', 'fast-single-product' ); ?></a></li>
							<li><a href="#"><?php esc_html_e( 'Refund Policy', 'fast-single-product' ); ?></a></li>
						</ul>
					</div>
				</div>

				<div class="footer-bottom">
					<p class="site-info">
						<?php
						printf(
							esc_html__( '&copy; %1$s %2$s. All rights reserved.', 'fast-single-product' ),
							date( 'Y' ),
							esc_html( get_bloginfo( 'name' ) )
						);
						?>
					</p>
				</div>
			</div><!-- .site-footer-inner -->
		</footer><!-- #colophon -->
	</div><!-- #page -->

	<?php wp_footer(); ?>
</body>
</html>
