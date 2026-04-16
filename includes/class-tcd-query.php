<?php
/**
 * Shared query logic for glossary terms.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TCD_Query {

	/**
	 * Fetch published posts grouped by first letter.
	 *
	 * @param string $post_type  CPT slug.
	 * @param string $taxonomy   Taxonomy slug (optional).
	 * @param string $term_slug  Term slug to filter by (optional).
	 * @return array<string, WP_Post[]> Map of uppercase letter (or '#') to posts.
	 */
	public static function get_grouped_terms( $post_type, $taxonomy = '', $term_slug = '' ) {
		$args = array(
			'post_type'              => $post_type,
			'post_status'            => 'publish',
			'posts_per_page'         => -1,
			'orderby'                => 'title',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
		);

		if ( $taxonomy && $term_slug ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => $taxonomy,
					'field'    => 'slug',
					'terms'    => $term_slug,
				),
			);
		}

		$query   = new WP_Query( $args );
		$grouped = array();

		foreach ( $query->posts as $post ) {
			$title = get_the_title( $post );
			$first = strtoupper( mb_substr( trim( $title ), 0, 1 ) );

			if ( ! preg_match( '/[A-Z]/', $first ) ) {
				$first = '#';
			}

			$grouped[ $first ][] = $post;
		}

		ksort( $grouped );

		return $grouped;
	}

	/**
	 * AJAX handler for frontend taxonomy filtering.
	 */
	public static function ajax_filter() {
		check_ajax_referer( 'tcd_glossary_filter', 'nonce' );

		$post_type = isset( $_POST['post_type'] ) ? sanitize_key( $_POST['post_type'] ) : '';
		$taxonomy  = isset( $_POST['taxonomy'] ) ? sanitize_key( $_POST['taxonomy'] ) : '';
		$term_slug = isset( $_POST['term_slug'] ) ? sanitize_key( $_POST['term_slug'] ) : '';

		if ( ! $post_type || ! post_type_exists( $post_type ) ) {
			wp_send_json_error( 'Invalid post type.' );
		}

		$grouped = self::get_grouped_terms( $post_type, $taxonomy, $term_slug );

		ob_start();

		$letters   = range( 'A', 'Z' );
		$widget_id = isset( $_POST['widget_id'] ) ? sanitize_key( $_POST['widget_id'] ) : '';

		// Nav HTML
		?>
		<ul class="tcd-glossary__nav-list">
			<?php foreach ( $letters as $letter ) :
				$active = isset( $grouped[ $letter ] );
				?>
				<li class="tcd-glossary__nav-item">
					<?php if ( $active ) : ?>
						<a class="tcd-glossary__nav-link is-active" href="#tcd-glossary-<?php echo esc_attr( $widget_id . '-' . $letter ); ?>">
							<?php echo esc_html( $letter ); ?>
						</a>
					<?php else : ?>
						<span class="tcd-glossary__nav-link is-disabled" aria-disabled="true">
							<?php echo esc_html( $letter ); ?>
						</span>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
		$nav_html = ob_get_clean();

		ob_start();

		if ( empty( $grouped ) ) : ?>
			<p class="tcd-glossary__empty"><?php esc_html_e( 'No glossary terms have been published yet.', 'tcd-glossary' ); ?></p>
		<?php else : ?>
			<div class="tcd-glossary__sections">
				<?php foreach ( $grouped as $letter => $posts ) : ?>
					<section
						class="tcd-glossary__section"
						id="tcd-glossary-<?php echo esc_attr( $widget_id . '-' . $letter ); ?>"
					>
						<h2 class="tcd-glossary__letter"><?php echo esc_html( $letter ); ?></h2>
						<div class="tcd-glossary__terms">
							<?php foreach ( $posts as $post ) : ?>
								<article class="tcd-glossary__term">
									<h3 class="tcd-glossary__term-title">
										<?php echo esc_html( get_the_title( $post ) ); ?>
									</h3>
									<div class="tcd-glossary__term-definition">
										<?php echo apply_filters( 'the_content', $post->post_content ); ?>
									</div>
								</article>
							<?php endforeach; ?>
						</div>
					</section>
				<?php endforeach; ?>
			</div>
		<?php endif;

		$sections_html = ob_get_clean();

		wp_send_json_success( array(
			'nav'      => $nav_html,
			'sections' => $sections_html,
		) );
	}

	/**
	 * Get taxonomy terms that have posts in the given post type.
	 *
	 * @param string $post_type CPT slug.
	 * @param string $taxonomy  Taxonomy slug.
	 * @return WP_Term[]
	 */
	public static function get_taxonomy_terms( $post_type, $taxonomy ) {
		if ( ! $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
			return array();
		}

		$terms = get_terms( array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => true,
		) );

		if ( is_wp_error( $terms ) ) {
			return array();
		}

		return $terms;
	}
}
