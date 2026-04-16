<?php
/**
 * Glossary template.
 *
 * @var array $args {
 *     @type array    $posts_grouped  A-Z grouped WP_Post arrays.
 *     @type bool     $show_nav       Whether to show A-Z navigation.
 *     @type bool     $show_filter    Whether to show taxonomy filter.
 *     @type string   $filter_style   'pills' or 'dropdown'.
 *     @type WP_Term[] $taxonomy_terms Available taxonomy terms.
 *     @type string   $active_term    Active term slug (empty = all).
 *     @type string   $post_type      CPT slug (for AJAX data attributes).
 *     @type string   $taxonomy       Taxonomy slug (for AJAX data attributes).
 *     @type string   $widget_id      Unique ID for this widget instance.
 *     @type string   $custom_class   Optional custom CSS class.
 * }
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$posts_grouped  = $args['posts_grouped'];
$show_nav       = $args['show_nav'];
$show_filter    = $args['show_filter'];
$filter_style   = $args['filter_style'];
$taxonomy_terms = $args['taxonomy_terms'];
$active_term    = $args['active_term'];
$post_type      = $args['post_type'];
$taxonomy       = $args['taxonomy'];
$widget_id      = $args['widget_id'];
$custom_class   = isset( $args['custom_class'] ) ? $args['custom_class'] : '';

$letters  = range( 'A', 'Z' );
$has_data = ! empty( $posts_grouped );
?>
<section
	class="tcd-glossary<?php echo $custom_class ? ' ' . esc_attr( $custom_class ) : ''; ?>"
	aria-label="<?php esc_attr_e( 'Glossary', 'tcd-glossary' ); ?>"
	data-widget-id="<?php echo esc_attr( $widget_id ); ?>"
	data-post-type="<?php echo esc_attr( $post_type ); ?>"
	data-taxonomy="<?php echo esc_attr( $taxonomy ); ?>"
>

	<?php if ( $show_filter && ! empty( $taxonomy_terms ) ) : ?>
		<div class="tcd-glossary__filter" aria-label="<?php esc_attr_e( 'Filter by category', 'tcd-glossary' ); ?>">
			<?php if ( 'pills' === $filter_style ) : ?>
				<ul class="tcd-glossary__filter-list">
					<li class="tcd-glossary__filter-item">
						<button
							type="button"
							class="tcd-glossary__filter-pill<?php echo empty( $active_term ) ? ' is-active' : ''; ?>"
							data-term=""
						>
							<?php esc_html_e( 'All', 'tcd-glossary' ); ?>
						</button>
					</li>
					<?php foreach ( $taxonomy_terms as $term ) : ?>
						<li class="tcd-glossary__filter-item">
							<button
								type="button"
								class="tcd-glossary__filter-pill<?php echo $active_term === $term->slug ? ' is-active' : ''; ?>"
								data-term="<?php echo esc_attr( $term->slug ); ?>"
							>
								<?php echo esc_html( $term->name ); ?>
							</button>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<select class="tcd-glossary__filter-dropdown">
					<option value=""<?php selected( $active_term, '' ); ?>><?php esc_html_e( 'All categories', 'tcd-glossary' ); ?></option>
					<?php foreach ( $taxonomy_terms as $term ) : ?>
						<option value="<?php echo esc_attr( $term->slug ); ?>"<?php selected( $active_term, $term->slug ); ?>>
							<?php echo esc_html( $term->name ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if ( $show_nav ) : ?>
		<nav class="tcd-glossary__nav" aria-label="<?php esc_attr_e( 'Glossary alphabet navigation', 'tcd-glossary' ); ?>">
			<ul class="tcd-glossary__nav-list">
				<?php foreach ( $letters as $letter ) :
					$active = isset( $posts_grouped[ $letter ] );
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
		</nav>
	<?php endif; ?>

	<?php if ( ! $has_data ) : ?>
		<p class="tcd-glossary__empty"><?php esc_html_e( 'No glossary terms have been published yet.', 'tcd-glossary' ); ?></p>
	<?php else : ?>
		<div class="tcd-glossary__sections">
			<?php foreach ( $posts_grouped as $letter => $posts ) : ?>
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
	<?php endif; ?>

</section>
