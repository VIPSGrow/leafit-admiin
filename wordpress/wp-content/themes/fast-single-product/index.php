<?php
/**
 * Fast Single Product Theme - index.php
 * Fallback template for posts/archive pages
 */

get_header();
?>

<div class="content-wrapper content-blog">
	<?php
	if ( have_posts() ) {
		while ( have_posts() ) {
			the_post();
			?>
			<article id="post-<?php the_ID(); ?>" <?php post_class( 'post-item' ); ?>>
				<header class="entry-header">
					<?php
					if ( is_singular() ) {
						the_title( '<h1 class="entry-title">', '</h1>' );
					} else {
						the_title( '<h2 class="entry-title"><a href="' . esc_url( get_permalink() ) . '" rel="bookmark">', '</a></h2>' );
					}
					?>
					<?php if ( 'post' === get_post_type() ) : ?>
						<div class="entry-meta">
							<?php
							printf(
								esc_html__( 'Posted on %s', 'fast-single-product' ),
								'<time class="entry-date published" datetime="' . esc_attr( get_the_date( DATE_W3C ) ) . '">' . esc_html( get_the_date() ) . '</time>'
							);
							?>
						</div>
					<?php endif; ?>
				</header>

				<div class="entry-content">
					<?php
					the_excerpt();
					?>
					<a href="<?php the_permalink(); ?>" class="read-more"><?php esc_html_e( 'Read More →', 'fast-single-product' ); ?></a>
				</div>
			</article>
			<?php
		}

		// Pagination
		the_posts_pagination( array(
			'prev_text' => esc_html__( '← Previous', 'fast-single-product' ),
			'next_text' => esc_html__( 'Next →', 'fast-single-product' ),
		) );
	} else {
		?>
		<div class="no-content">
			<h2><?php esc_html_e( 'Nothing Found', 'fast-single-product' ); ?></h2>
			<p><?php esc_html_e( 'No posts or pages found.', 'fast-single-product' ); ?></p>
		</div>
		<?php
	}
	?>
</div>

<?php
get_footer();
?>
