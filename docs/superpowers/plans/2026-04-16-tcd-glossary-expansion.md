# TCD Glossary Plugin Expansion — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Expand the TCD Glossary plugin into a full Elementor widget with admin settings, frontend taxonomy filtering, full style controls, and GitHub-based auto-updates.

**Architecture:** Modular file-per-concern. Shared query class serves shortcode, Elementor widget, and AJAX. Settings page stores CPT/taxonomy selection. Elementor widget loaded conditionally. GitHub updater hooks into WP's native update transient system.

**Tech Stack:** WordPress 5.8+, Elementor 3.x+, WordPress Settings API, GitHub Releases API, vanilla JS for frontend filtering.

**Spec:** `docs/superpowers/specs/2026-04-16-tcd-glossary-expansion-design.md`

---

### Task 1: Extract Query Logic into `class-tcd-query.php`

**Files:**
- Create: `includes/class-tcd-query.php`
- Modify: `includes/class-tcd-glossary.php`
- Modify: `tcd-glossary.php`

- [ ] **Step 1: Create `includes/class-tcd-query.php`**

This class centralizes all WP_Query logic. Both shortcode and Elementor widget will use it.

```php
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
```

- [ ] **Step 2: Require `class-tcd-query.php` in `tcd-glossary.php`**

Add this line after the existing `require_once` in `tcd-glossary.php`:

```php
require_once TCD_GLOSSARY_PATH . 'includes/class-tcd-query.php';
```

The full requires section becomes:

```php
require_once TCD_GLOSSARY_PATH . 'includes/class-tcd-glossary.php';
require_once TCD_GLOSSARY_PATH . 'includes/class-tcd-query.php';
```

- [ ] **Step 3: Refactor `class-tcd-glossary.php` to use `TCD_Query`**

Remove the `get_grouped_terms()` method and the `POST_TYPE`/`TAXONOMY` constants from `TCD_Glossary`. The class becomes the orchestrator that loads modules and handles the shortcode.

Replace the entire file with:

```php
<?php
/**
 * Core plugin class.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TCD_Glossary {

	const HANDLE = 'tcd-glossary';

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_shortcode( 'tcd_glossary', array( $this, 'render_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
	}

	public function register_assets() {
		wp_register_style(
			self::HANDLE,
			TCD_GLOSSARY_URL . 'assets/css/tcd-glossary.css',
			array(),
			TCD_GLOSSARY_VERSION
		);

		wp_register_script(
			self::HANDLE,
			TCD_GLOSSARY_URL . 'assets/js/tcd-glossary.js',
			array(),
			TCD_GLOSSARY_VERSION,
			true
		);

		wp_localize_script( self::HANDLE, 'tcdGlossary', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'tcd_glossary_filter' ),
		) );
	}

	/**
	 * Get the saved settings with defaults.
	 *
	 * @return array{post_type: string, taxonomy: string}
	 */
	public static function get_settings() {
		$defaults = array(
			'post_type' => '',
			'taxonomy'  => '',
		);
		$settings = get_option( 'tcd_glossary_settings', array() );
		return wp_parse_args( $settings, $defaults );
	}

	public function render_shortcode( $atts = array() ) {
		$settings = self::get_settings();
		$post_type = $settings['post_type'];

		if ( ! $post_type ) {
			return '<p class="tcd-glossary__empty">' . esc_html__( 'TCD Glossary: No post type configured. Please visit Settings &rarr; TCD Glossary.', 'tcd-glossary' ) . '</p>';
		}

		$taxonomy = $settings['taxonomy'];

		wp_enqueue_style( self::HANDLE );
		wp_enqueue_script( self::HANDLE );

		$grouped        = TCD_Query::get_grouped_terms( $post_type, $taxonomy );
		$taxonomy_terms = TCD_Query::get_taxonomy_terms( $post_type, $taxonomy );

		$args = array(
			'posts_grouped'  => $grouped,
			'show_nav'       => true,
			'show_filter'    => ! empty( $taxonomy ) && ! empty( $taxonomy_terms ),
			'filter_style'   => 'pills',
			'taxonomy_terms' => $taxonomy_terms,
			'active_term'    => '',
			'post_type'      => $post_type,
			'taxonomy'       => $taxonomy,
			'widget_id'      => 'shortcode-' . wp_unique_id(),
		);

		ob_start();
		include TCD_GLOSSARY_PATH . 'templates/glossary.php';
		return ob_get_clean();
	}
}
```

- [ ] **Step 4: Commit**

```bash
git add includes/class-tcd-query.php includes/class-tcd-glossary.php tcd-glossary.php
git commit -m "refactor: extract query logic into TCD_Query class"
```

---

### Task 2: Create Shared Template

**Files:**
- Create: `templates/glossary.php`

- [ ] **Step 1: Create `templates/glossary.php`**

This template is used by both the shortcode and the Elementor widget. It receives its data via the `$args` array.

```php
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
```

- [ ] **Step 2: Commit**

```bash
git add templates/glossary.php
git commit -m "feat: add shared glossary template"
```

---

### Task 3: Create Settings Page

**Files:**
- Create: `includes/class-tcd-settings.php`
- Modify: `tcd-glossary.php`

- [ ] **Step 1: Create `includes/class-tcd-settings.php`**

```php
<?php
/**
 * Settings page: Settings -> TCD Glossary.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TCD_Settings {

	const OPTION_NAME = 'tcd_glossary_settings';
	const PAGE_SLUG   = 'tcd-glossary-settings';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	public function add_menu_page() {
		add_options_page(
			__( 'TCD Glossary', 'tcd-glossary' ),
			__( 'TCD Glossary', 'tcd-glossary' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	public function register_settings() {
		register_setting( self::PAGE_SLUG, self::OPTION_NAME, array(
			'type'              => 'array',
			'sanitize_callback' => array( $this, 'sanitize' ),
			'default'           => array(
				'post_type' => '',
				'taxonomy'  => '',
			),
		) );

		add_settings_section(
			'tcd_glossary_main',
			__( 'Data Source', 'tcd-glossary' ),
			'__return_null',
			self::PAGE_SLUG
		);

		add_settings_field(
			'post_type',
			__( 'Post Type', 'tcd-glossary' ),
			array( $this, 'render_post_type_field' ),
			self::PAGE_SLUG,
			'tcd_glossary_main'
		);

		add_settings_field(
			'taxonomy',
			__( 'Taxonomy', 'tcd-glossary' ),
			array( $this, 'render_taxonomy_field' ),
			self::PAGE_SLUG,
			'tcd_glossary_main'
		);
	}

	public function sanitize( $input ) {
		$clean = array();

		$clean['post_type'] = isset( $input['post_type'] ) ? sanitize_key( $input['post_type'] ) : '';
		$clean['taxonomy']  = isset( $input['taxonomy'] ) ? sanitize_key( $input['taxonomy'] ) : '';

		return $clean;
	}

	public function render_post_type_field() {
		$settings   = get_option( self::OPTION_NAME, array() );
		$current    = isset( $settings['post_type'] ) ? $settings['post_type'] : '';
		$post_types = get_post_types( array( 'public' => true ), 'objects' );

		echo '<select name="' . esc_attr( self::OPTION_NAME ) . '[post_type]">';
		echo '<option value="">' . esc_html__( '-- Select --', 'tcd-glossary' ) . '</option>';
		foreach ( $post_types as $pt ) {
			printf(
				'<option value="%s"%s>%s (%s)</option>',
				esc_attr( $pt->name ),
				selected( $current, $pt->name, false ),
				esc_html( $pt->labels->singular_name ),
				esc_html( $pt->name )
			);
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Select the post type that contains your glossary terms.', 'tcd-glossary' ) . '</p>';
	}

	public function render_taxonomy_field() {
		$settings   = get_option( self::OPTION_NAME, array() );
		$current    = isset( $settings['taxonomy'] ) ? $settings['taxonomy'] : '';
		$taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );

		echo '<select name="' . esc_attr( self::OPTION_NAME ) . '[taxonomy]">';
		echo '<option value="">' . esc_html__( '-- None --', 'tcd-glossary' ) . '</option>';
		foreach ( $taxonomies as $tax ) {
			printf(
				'<option value="%s"%s>%s (%s)</option>',
				esc_attr( $tax->name ),
				selected( $current, $tax->name, false ),
				esc_html( $tax->labels->singular_name ),
				esc_html( $tax->name )
			);
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Optionally select a taxonomy to enable category filtering.', 'tcd-glossary' ) . '</p>';
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'TCD Glossary Settings', 'tcd-glossary' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( self::PAGE_SLUG );
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
```

- [ ] **Step 2: Require and instantiate in `tcd-glossary.php`**

Add after the existing requires:

```php
require_once TCD_GLOSSARY_PATH . 'includes/class-tcd-settings.php';
```

Inside the `plugins_loaded` callback, add:

```php
if ( is_admin() ) {
	new TCD_Settings();
}
```

The full `plugins_loaded` callback becomes:

```php
add_action( 'plugins_loaded', function () {
	TCD_Glossary::instance();

	if ( is_admin() ) {
		new TCD_Settings();
	}
} );
```

- [ ] **Step 3: Commit**

```bash
git add includes/class-tcd-settings.php tcd-glossary.php
git commit -m "feat: add settings page for CPT and taxonomy selection"
```

---

### Task 4: Create AJAX Handler for Frontend Filtering

**Files:**
- Modify: `includes/class-tcd-query.php`
- Modify: `includes/class-tcd-glossary.php`

- [ ] **Step 1: Add AJAX handler to `class-tcd-query.php`**

Add this method at the end of the `TCD_Query` class (before the closing `}`):

```php
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
```

- [ ] **Step 2: Register the AJAX actions in `class-tcd-glossary.php`**

In the `__construct()` method, add these two lines after the existing hooks:

```php
		add_action( 'wp_ajax_tcd_glossary_filter', array( 'TCD_Query', 'ajax_filter' ) );
		add_action( 'wp_ajax_nopriv_tcd_glossary_filter', array( 'TCD_Query', 'ajax_filter' ) );
```

- [ ] **Step 3: Commit**

```bash
git add includes/class-tcd-query.php includes/class-tcd-glossary.php
git commit -m "feat: add AJAX handler for frontend taxonomy filtering"
```

---

### Task 5: Create Frontend JavaScript

**Files:**
- Create: `assets/js/tcd-glossary.js`

- [ ] **Step 1: Create `assets/js/tcd-glossary.js`**

Uses DOMParser for safe HTML insertion from the server-rendered AJAX response (all HTML is escaped server-side via `esc_html()` and `esc_attr()`).

```js
(function () {
	'use strict';

	/**
	 * Safely parse an HTML string and return a DocumentFragment.
	 * Uses DOMParser so no raw string-to-DOM assignment occurs.
	 *
	 * @param {string} htmlString Server-rendered HTML.
	 * @returns {DocumentFragment}
	 */
	function parseSafeHTML( htmlString ) {
		var doc      = new DOMParser().parseFromString( htmlString, 'text/html' );
		var fragment = document.createDocumentFragment();
		while ( doc.body.firstChild ) {
			fragment.appendChild( doc.body.firstChild );
		}
		return fragment;
	}

	function initGlossary( container ) {
		var postType = container.getAttribute( 'data-post-type' );
		var taxonomy = container.getAttribute( 'data-taxonomy' );
		var widgetId = container.getAttribute( 'data-widget-id' );
		var nav      = container.querySelector( '.tcd-glossary__nav' );

		// Pill click handlers
		var pills = container.querySelectorAll( '.tcd-glossary__filter-pill' );
		pills.forEach( function ( pill ) {
			pill.addEventListener( 'click', function () {
				var termSlug = this.getAttribute( 'data-term' );
				setActivePill( container, this );
				fetchFiltered( container, postType, taxonomy, termSlug, widgetId, nav );
			} );
		} );

		// Dropdown change handler
		var dropdown = container.querySelector( '.tcd-glossary__filter-dropdown' );
		if ( dropdown ) {
			dropdown.addEventListener( 'change', function () {
				fetchFiltered( container, postType, taxonomy, this.value, widgetId, nav );
			} );
		}
	}

	function setActivePill( container, activePill ) {
		var pills = container.querySelectorAll( '.tcd-glossary__filter-pill' );
		pills.forEach( function ( pill ) {
			pill.classList.remove( 'is-active' );
		} );
		activePill.classList.add( 'is-active' );
	}

	function fetchFiltered( container, postType, taxonomy, termSlug, widgetId, nav ) {
		var body = new FormData();
		body.append( 'action', 'tcd_glossary_filter' );
		body.append( 'nonce', tcdGlossary.nonce );
		body.append( 'post_type', postType );
		body.append( 'taxonomy', taxonomy );
		body.append( 'term_slug', termSlug );
		body.append( 'widget_id', widgetId );

		container.classList.add( 'is-loading' );

		fetch( tcdGlossary.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body,
		} )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( result ) {
				if ( result.success ) {
					// Replace nav list using safe DOM parsing
					if ( nav ) {
						while ( nav.firstChild ) {
							nav.removeChild( nav.firstChild );
						}
						nav.appendChild( parseSafeHTML( result.data.nav ) );
					}
					// Replace sections (remove old sections + empty message, insert new)
					var oldSections = container.querySelector( '.tcd-glossary__sections' );
					var oldEmpty    = container.querySelector( '.tcd-glossary__empty' );
					if ( oldSections ) {
						oldSections.remove();
					}
					if ( oldEmpty ) {
						oldEmpty.remove();
					}
					container.appendChild( parseSafeHTML( result.data.sections ) );
				}
			} )
			.finally( function () {
				container.classList.remove( 'is-loading' );
			} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var glossaries = document.querySelectorAll( '.tcd-glossary' );
		glossaries.forEach( initGlossary );
	} );
})();
```

- [ ] **Step 2: Commit**

```bash
git add assets/js/tcd-glossary.js
git commit -m "feat: add frontend JS for taxonomy filtering via AJAX"
```

---

### Task 6: Update CSS for Taxonomy Filter and Loading State

**Files:**
- Modify: `assets/css/tcd-glossary.css`

- [ ] **Step 1: Add filter and loading styles to `assets/css/tcd-glossary.css`**

Append the following after the existing `/* Mobile */` media query block:

```css

/* Taxonomy filter */
.tcd-glossary__filter {
	margin-bottom: 1.25rem;
}

.tcd-glossary__filter-list {
	list-style: none;
	margin: 0;
	padding: 0;
	display: flex;
	flex-wrap: wrap;
	gap: 0.5rem;
}

.tcd-glossary__filter-item {
	margin: 0;
}

.tcd-glossary__filter-pill {
	display: inline-block;
	padding: 0.4rem 1rem;
	font-size: 0.9rem;
	font-weight: 600;
	border: 1px solid var(--tcd-border);
	border-radius: 9999px;
	background: #fff;
	color: var(--tcd-body);
	cursor: pointer;
	transition: background-color 0.15s ease, color 0.15s ease, border-color 0.15s ease;
}

.tcd-glossary__filter-pill:hover,
.tcd-glossary__filter-pill:focus {
	border-color: var(--tcd-orange);
	color: var(--tcd-orange);
	outline: none;
}

.tcd-glossary__filter-pill.is-active {
	background: var(--tcd-orange);
	border-color: var(--tcd-orange);
	color: #fff;
}

.tcd-glossary__filter-dropdown {
	padding: 0.5rem 1rem;
	font-size: 0.9rem;
	border: 1px solid var(--tcd-border);
	border-radius: 6px;
	background: #fff;
	color: var(--tcd-body);
}

/* Loading state */
.tcd-glossary.is-loading .tcd-glossary__sections,
.tcd-glossary.is-loading .tcd-glossary__empty {
	opacity: 0.4;
	pointer-events: none;
	transition: opacity 0.15s ease;
}
```

- [ ] **Step 2: Commit**

```bash
git add assets/css/tcd-glossary.css
git commit -m "feat: add CSS for taxonomy filter pills, dropdown, and loading state"
```

---

### Task 7: Create Elementor Integration Loader

**Files:**
- Create: `includes/elementor/class-tcd-elementor.php`
- Modify: `tcd-glossary.php`

- [ ] **Step 1: Create `includes/elementor/class-tcd-elementor.php`**

```php
<?php
/**
 * Elementor integration: registers the TCD widget category and glossary widget.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TCD_Elementor {

	public function __construct() {
		add_action( 'elementor/elements/categories_registered', array( $this, 'register_category' ) );
		add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );
	}

	/**
	 * Register the "TCD" widget category.
	 *
	 * @param \Elementor\Elements_Manager $elements_manager
	 */
	public function register_category( $elements_manager ) {
		$elements_manager->add_category( 'tcd', array(
			'title' => __( 'TCD', 'tcd-glossary' ),
			'icon'  => 'eicon-folder',
		) );
	}

	/**
	 * Register the glossary widget.
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager
	 */
	public function register_widgets( $widgets_manager ) {
		require_once TCD_GLOSSARY_PATH . 'includes/elementor/class-tcd-glossary-widget.php';
		$widgets_manager->register( new TCD_Glossary_Widget() );
	}
}
```

- [ ] **Step 2: Load Elementor integration conditionally in `tcd-glossary.php`**

Add after the existing requires:

```php
require_once TCD_GLOSSARY_PATH . 'includes/elementor/class-tcd-elementor.php';
```

Inside the `plugins_loaded` callback, add:

```php
if ( did_action( 'elementor/loaded' ) ) {
	new TCD_Elementor();
}
```

The full `plugins_loaded` callback becomes:

```php
add_action( 'plugins_loaded', function () {
	TCD_Glossary::instance();

	if ( is_admin() ) {
		new TCD_Settings();
	}

	if ( did_action( 'elementor/loaded' ) ) {
		new TCD_Elementor();
	}
} );
```

- [ ] **Step 3: Commit**

```bash
git add includes/elementor/class-tcd-elementor.php tcd-glossary.php
git commit -m "feat: add Elementor integration loader with TCD category"
```

---

### Task 8: Create Elementor Glossary Widget

**Files:**
- Create: `includes/elementor/class-tcd-glossary-widget.php`

- [ ] **Step 1: Create `includes/elementor/class-tcd-glossary-widget.php` with content controls, style controls, and render method**

```php
<?php
/**
 * Elementor Glossary Widget.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TCD_Glossary_Widget extends \Elementor\Widget_Base {

	public function get_name() {
		return 'tcd-glossary';
	}

	public function get_title() {
		return __( 'TCD Glossary', 'tcd-glossary' );
	}

	public function get_icon() {
		return 'eicon-text';
	}

	public function get_categories() {
		return array( 'tcd' );
	}

	public function get_style_depends() {
		return array( 'tcd-glossary' );
	}

	public function get_script_depends() {
		return array( 'tcd-glossary' );
	}

	protected function register_controls() {
		$this->register_content_controls();
		$this->register_style_controls();
	}

	private function get_post_type_options() {
		$options    = array( '' => __( '-- Use Settings Page --', 'tcd-glossary' ) );
		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		foreach ( $post_types as $pt ) {
			$options[ $pt->name ] = $pt->labels->singular_name . ' (' . $pt->name . ')';
		}
		return $options;
	}

	private function get_taxonomy_options() {
		$options    = array( '' => __( '-- Use Settings Page --', 'tcd-glossary' ) );
		$taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );
		foreach ( $taxonomies as $tax ) {
			$options[ $tax->name ] = $tax->labels->singular_name . ' (' . $tax->name . ')';
		}
		return $options;
	}

	private function register_content_controls() {
		$this->start_controls_section( 'section_content', array(
			'label' => __( 'Content', 'tcd-glossary' ),
			'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
		) );

		$this->add_control( 'post_type', array(
			'label'   => __( 'Post Type', 'tcd-glossary' ),
			'type'    => \Elementor\Controls_Manager::SELECT,
			'options' => $this->get_post_type_options(),
			'default' => '',
		) );

		$this->add_control( 'taxonomy', array(
			'label'   => __( 'Taxonomy', 'tcd-glossary' ),
			'type'    => \Elementor\Controls_Manager::SELECT,
			'options' => $this->get_taxonomy_options(),
			'default' => '',
		) );

		$this->add_control( 'default_term', array(
			'label'       => __( 'Default Term', 'tcd-glossary' ),
			'type'        => \Elementor\Controls_Manager::TEXT,
			'default'     => '',
			'description' => __( 'Enter a term slug to pre-filter. Leave empty for "All".', 'tcd-glossary' ),
		) );

		$this->add_control( 'show_nav', array(
			'label'        => __( 'Show A-Z Navigation', 'tcd-glossary' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'label_on'     => __( 'Yes', 'tcd-glossary' ),
			'label_off'    => __( 'No', 'tcd-glossary' ),
			'return_value' => 'yes',
			'default'      => 'yes',
		) );

		$this->add_control( 'show_filter', array(
			'label'        => __( 'Show Taxonomy Filter', 'tcd-glossary' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'label_on'     => __( 'Yes', 'tcd-glossary' ),
			'label_off'    => __( 'No', 'tcd-glossary' ),
			'return_value' => 'yes',
			'default'      => '',
		) );

		$this->add_control( 'filter_style', array(
			'label'     => __( 'Filter Style', 'tcd-glossary' ),
			'type'      => \Elementor\Controls_Manager::SELECT,
			'options'   => array(
				'pills'    => __( 'Pills', 'tcd-glossary' ),
				'dropdown' => __( 'Dropdown', 'tcd-glossary' ),
			),
			'default'   => 'pills',
			'condition' => array(
				'show_filter' => 'yes',
			),
		) );

		$this->end_controls_section();
	}

	private function register_style_controls() {
		// --- Container ---
		$this->start_controls_section( 'section_style_container', array(
			'label' => __( 'Container', 'tcd-glossary' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );

		$this->add_responsive_control( 'container_max_width', array(
			'label'      => __( 'Max Width', 'tcd-glossary' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => array( 'px', '%' ),
			'range'      => array(
				'px' => array( 'min' => 300, 'max' => 1600 ),
			),
			'selectors'  => array(
				'{{WRAPPER}} .tcd-glossary' => 'max-width: {{SIZE}}{{UNIT}};',
			),
		) );

		$this->add_responsive_control( 'container_padding', array(
			'label'      => __( 'Padding', 'tcd-glossary' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', '%' ),
			'selectors'  => array(
				'{{WRAPPER}} .tcd-glossary' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		) );

		$this->add_control( 'container_bg_color', array(
			'label'     => __( 'Background Color', 'tcd-glossary' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array(
				'{{WRAPPER}} .tcd-glossary' => 'background-color: {{VALUE}};',
			),
		) );

		$this->add_group_control( \Elementor\Group_Control_Border::get_type(), array(
			'name'     => 'container_border',
			'selector' => '{{WRAPPER}} .tcd-glossary',
		) );

		$this->add_responsive_control( 'container_border_radius', array(
			'label'      => __( 'Border Radius', 'tcd-glossary' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', '%' ),
			'selectors'  => array(
				'{{WRAPPER}} .tcd-glossary' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		) );

		$this->add_group_control( \Elementor\Group_Control_Box_Shadow::get_type(), array(
			'name'     => 'container_box_shadow',
			'selector' => '{{WRAPPER}} .tcd-glossary',
		) );

		$this->add_control( 'container_css_class', array(
			'label'       => __( 'CSS Class', 'tcd-glossary' ),
			'type'        => \Elementor\Controls_Manager::TEXT,
			'default'     => '',
			'description' => __( 'Add a custom CSS class to the glossary container.', 'tcd-glossary' ),
		) );

		$this->end_controls_section();

		// --- A-Z Navigation ---
		$this->start_controls_section( 'section_style_nav', array(
			'label'     => __( 'A-Z Navigation', 'tcd-glossary' ),
			'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
			'condition' => array( 'show_nav' => 'yes' ),
		) );

		$this->add_control( 'nav_bg_color', array(
			'label'     => __( 'Background Color', 'tcd-glossary' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array(
				'{{WRAPPER}} .tcd-glossary__nav' => 'background-color: {{VALUE}};',
			),
		) );

		$this->add_group_control( \Elementor\Group_Control_Border::get_type(), array(
			'name'     => 'nav_border',
			'selector' => '{{WRAPPER}} .tcd-glossary__nav',
		) );

		$this->add_responsive_control( 'nav_border_radius', array(
			'label'      => __( 'Border Radius', 'tcd-glossary' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', '%' ),
			'selectors'  => array(
				'{{WRAPPER}} .tcd-glossary__nav' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		) );

		$this->add_responsive_control( 'nav_padding', array(
			'label'      => __( 'Padding', 'tcd-glossary' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em' ),
			'selectors'  => array(
				'{{WRAPPER}} .tcd-glossary__nav' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		) );

		$this->add_control( 'nav_sticky', array(
			'label'        => __( 'Sticky', 'tcd-glossary' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'label_on'     => __( 'Yes', 'tcd-glossary' ),
			'label_off'    => __( 'No', 'tcd-glossary' ),
			'return_value' => 'yes',
			'default'      => 'yes',
			'selectors'    => array(
				'{{WRAPPER}} .tcd-glossary__nav' => 'position: {{VALUE}};',
			),
			'selectors_dictionary' => array(
				'yes' => 'sticky',
				''    => 'relative',
			),
		) );

		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'     => 'nav_typography',
			'selector' => '{{WRAPPER}} .tcd-glossary__nav-link',
		) );

		$this->add_control( 'nav_active_color', array(
			'label'     => __( 'Active Color', 'tcd-glossary' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array(
				'{{WRAPPER}} .tcd-glossary__nav-link.is-active' => 'color: {{VALUE}};',
			),
		) );

		$this->add_control( 'nav_hover_color', array(
			'label'     => __( 'Hover Color', 'tcd-glossary' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array(
				'{{WRAPPER}} .tcd-glossary__nav-link.is-active:hover' => 'background-color: {{VALUE}}; color: #fff;',
				'{{WRAPPER}} .tcd-glossary__nav-link.is-active:focus' => 'background-color: {{VALUE}}; color: #fff;',
			),
		) );

		$this->add_control( 'nav_disabled_color', array(
			'label'     => __( 'Disabled Color', 'tcd-glossary' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array(
				'{{WRAPPER}} .tcd-glossary__nav-link.is-disabled' => 'color: {{VALUE}};',
			),
		) );

		$this->end_controls_section();

		// --- Taxonomy Filter ---
		$this->start_controls_section( 'section_style_filter', array(
			'label'     => __( 'Taxonomy Filter', 'tcd-glossary' ),
			'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
			'condition' => array( 'show_filter' => 'yes' ),
		) );

		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'     => 'filter_typography',
			'selector' => '{{WRAPPER}} .tcd-glossary__filter-pill, {{WRAPPER}} .tcd-glossary__filter-dropdown',
		) );

		$this->add_control( 'filter_bg_color', array(
			'label'     => __( 'Background Color', 'tcd-glossary' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array(
				'{{WRAPPER}} .tcd-glossary__filter-pill' => 'background-color: {{VALUE}};',
			),
		) );

		$this->add_control( 'filter_active_bg_color', array(
			'label'     => __( 'Active Background', 'tcd-glossary' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array(
				'{{WRAPPER}} .tcd-glossary__filter-pill.is-active' => 'background-color: {{VALUE}}; border-color: {{VALUE}};',
			),
		) );

		$this->add_control( 'filter_text_color', array(
			'label'     => __( 'Text Color', 'tcd-glossary' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array(
				'{{WRAPPER}} .tcd-glossary__filter-pill' => 'color: {{VALUE}};',
			),
		) );

		$this->add_control( 'filter_active_text_color', array(
			'label'     => __( 'Active Text Color', 'tcd-glossary' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array(
				'{{WRAPPER}} .tcd-glossary__filter-pill.is-active' => 'color: {{VALUE}};',
			),
		) );

		$this->add_responsive_control( 'filter_border_radius', array(
			'label'      => __( 'Border Radius', 'tcd-glossary' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array(
				'px' => array( 'min' => 0, 'max' => 50 ),
			),
			'selectors'  => array(
				'{{WRAPPER}} .tcd-glossary__filter-pill' => 'border-radius: {{SIZE}}{{UNIT}};',
			),
		) );

		$this->add_responsive_control( 'filter_gap', array(
			'label'      => __( 'Gap', 'tcd-glossary' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => array( 'px', 'em' ),
			'range'      => array(
				'px' => array( 'min' => 0, 'max' => 40 ),
			),
			'selectors'  => array(
				'{{WRAPPER}} .tcd-glossary__filter-list' => 'gap: {{SIZE}}{{UNIT}};',
			),
		) );

		$this->add_responsive_control( 'filter_padding', array(
			'label'      => __( 'Padding', 'tcd-glossary' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em' ),
			'selectors'  => array(
				'{{WRAPPER}} .tcd-glossary__filter-pill' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		) );

		$this->end_controls_section();

		// --- Letter Headings ---
		$this->start_controls_section( 'section_style_letter', array(
			'label' => __( 'Letter Headings', 'tcd-glossary' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );

		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'     => 'letter_typography',
			'selector' => '{{WRAPPER}} .tcd-glossary__letter',
		) );

		$this->add_control( 'letter_color', array(
			'label'     => __( 'Color', 'tcd-glossary' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array(
				'{{WRAPPER}} .tcd-glossary__letter' => 'color: {{VALUE}};',
			),
		) );

		$this->add_control( 'letter_border_color', array(
			'label'     => __( 'Border Bottom Color', 'tcd-glossary' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array(
				'{{WRAPPER}} .tcd-glossary__letter' => 'border-bottom-color: {{VALUE}};',
			),
		) );

		$this->add_responsive_control( 'letter_border_width', array(
			'label'      => __( 'Border Bottom Width', 'tcd-glossary' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array(
				'px' => array( 'min' => 0, 'max' => 10 ),
			),
			'selectors'  => array(
				'{{WRAPPER}} .tcd-glossary__letter' => 'border-bottom-width: {{SIZE}}{{UNIT}};',
			),
		) );

		$this->add_responsive_control( 'letter_margin', array(
			'label'      => __( 'Margin', 'tcd-glossary' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em' ),
			'selectors'  => array(
				'{{WRAPPER}} .tcd-glossary__letter' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		) );

		$this->add_responsive_control( 'letter_padding', array(
			'label'      => __( 'Padding', 'tcd-glossary' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em' ),
			'selectors'  => array(
				'{{WRAPPER}} .tcd-glossary__letter' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		) );

		$this->end_controls_section();

		// --- Term Titles ---
		$this->start_controls_section( 'section_style_term_title', array(
			'label' => __( 'Term Titles', 'tcd-glossary' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );

		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'     => 'term_title_typography',
			'selector' => '{{WRAPPER}} .tcd-glossary__term-title',
		) );

		$this->add_control( 'term_title_color', array(
			'label'     => __( 'Color', 'tcd-glossary' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array(
				'{{WRAPPER}} .tcd-glossary__term-title' => 'color: {{VALUE}};',
			),
		) );

		$this->add_responsive_control( 'term_title_margin', array(
			'label'      => __( 'Margin', 'tcd-glossary' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em' ),
			'selectors'  => array(
				'{{WRAPPER}} .tcd-glossary__term-title' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		) );

		$this->end_controls_section();

		// --- Term Definitions ---
		$this->start_controls_section( 'section_style_term_def', array(
			'label' => __( 'Term Definitions', 'tcd-glossary' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );

		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'     => 'term_def_typography',
			'selector' => '{{WRAPPER}} .tcd-glossary__term-definition',
		) );

		$this->add_control( 'term_def_color', array(
			'label'     => __( 'Color', 'tcd-glossary' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array(
				'{{WRAPPER}} .tcd-glossary__term-definition' => 'color: {{VALUE}};',
			),
		) );

		$this->end_controls_section();

		// --- Empty State ---
		$this->start_controls_section( 'section_style_empty', array(
			'label' => __( 'Empty State', 'tcd-glossary' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );

		$this->add_control( 'empty_bg_color', array(
			'label'     => __( 'Background Color', 'tcd-glossary' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array(
				'{{WRAPPER}} .tcd-glossary__empty' => 'background-color: {{VALUE}};',
			),
		) );

		$this->add_group_control( \Elementor\Group_Control_Border::get_type(), array(
			'name'     => 'empty_border',
			'selector' => '{{WRAPPER}} .tcd-glossary__empty',
		) );

		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'     => 'empty_typography',
			'selector' => '{{WRAPPER}} .tcd-glossary__empty',
		) );

		$this->add_control( 'empty_text_color', array(
			'label'     => __( 'Text Color', 'tcd-glossary' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array(
				'{{WRAPPER}} .tcd-glossary__empty' => 'color: {{VALUE}};',
			),
		) );

		$this->add_responsive_control( 'empty_padding', array(
			'label'      => __( 'Padding', 'tcd-glossary' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em' ),
			'selectors'  => array(
				'{{WRAPPER}} .tcd-glossary__empty' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		) );

		$this->end_controls_section();
	}

	protected function render() {
		$settings  = $this->get_settings_for_display();
		$global    = TCD_Glossary::get_settings();

		$post_type = ! empty( $settings['post_type'] ) ? $settings['post_type'] : $global['post_type'];
		$taxonomy  = ! empty( $settings['taxonomy'] ) ? $settings['taxonomy'] : $global['taxonomy'];

		if ( ! $post_type ) {
			echo '<p class="tcd-glossary__empty">' . esc_html__( 'TCD Glossary: No post type configured.', 'tcd-glossary' ) . '</p>';
			return;
		}

		$default_term   = ! empty( $settings['default_term'] ) ? $settings['default_term'] : '';
		$show_nav       = 'yes' === $settings['show_nav'];
		$show_filter    = 'yes' === $settings['show_filter'] && ! empty( $taxonomy );
		$filter_style   = ! empty( $settings['filter_style'] ) ? $settings['filter_style'] : 'pills';
		$taxonomy_terms = TCD_Query::get_taxonomy_terms( $post_type, $taxonomy );
		$grouped        = TCD_Query::get_grouped_terms( $post_type, $taxonomy, $default_term );
		$custom_class   = ! empty( $settings['container_css_class'] ) ? $settings['container_css_class'] : '';

		$args = array(
			'posts_grouped'  => $grouped,
			'show_nav'       => $show_nav,
			'show_filter'    => $show_filter && ! empty( $taxonomy_terms ),
			'filter_style'   => $filter_style,
			'taxonomy_terms' => $taxonomy_terms,
			'active_term'    => $default_term,
			'post_type'      => $post_type,
			'taxonomy'       => $taxonomy,
			'widget_id'      => $this->get_id(),
			'custom_class'   => $custom_class,
		);

		include TCD_GLOSSARY_PATH . 'templates/glossary.php';
	}
}
```

- [ ] **Step 2: Commit**

```bash
git add includes/elementor/class-tcd-glossary-widget.php
git commit -m "feat: add Elementor glossary widget with content and style controls"
```

---

### Task 9: Create GitHub Auto-Updater

**Files:**
- Create: `includes/class-tcd-updater.php`
- Modify: `tcd-glossary.php`

- [ ] **Step 1: Create `includes/class-tcd-updater.php`**

```php
<?php
/**
 * GitHub release auto-updater.
 *
 * Checks the GitHub releases API for new versions and injects update data
 * into WordPress's plugin update transient.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TCD_Updater {

	private $plugin_slug;
	private $plugin_basename;
	private $github_repo;
	private $current_version;
	private $transient_key = 'tcd_glossary_update_check';
	private $cache_hours   = 12;

	/**
	 * @param string $plugin_basename Plugin basename (e.g. 'tcd-glossary/tcd-glossary.php').
	 * @param string $github_repo     GitHub owner/repo (e.g. 'tcd/tcd-glossary').
	 * @param string $current_version Current plugin version.
	 */
	public function __construct( $plugin_basename, $github_repo, $current_version ) {
		$this->plugin_basename = $plugin_basename;
		$this->plugin_slug     = dirname( $plugin_basename );
		$this->github_repo     = $github_repo;
		$this->current_version = $current_version;

		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 );
		add_filter( 'upgrader_post_install', array( $this, 'after_install' ), 10, 3 );
	}

	/**
	 * Fetch latest release data from GitHub (cached).
	 *
	 * @return object|false Release data or false on failure.
	 */
	private function get_release_data() {
		$cached = get_transient( $this->transient_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$url      = 'https://api.github.com/repos/' . $this->github_repo . '/releases/latest';
		$response = wp_remote_get( $url, array(
			'headers' => array(
				'Accept' => 'application/vnd.github.v3+json',
			),
			'timeout' => 10,
		) );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ) );
		if ( empty( $body->tag_name ) ) {
			return false;
		}

		set_transient( $this->transient_key, $body, $this->cache_hours * HOUR_IN_SECONDS );

		return $body;
	}

	/**
	 * Get the download URL for the zip asset from a release.
	 *
	 * @param object $release GitHub release object.
	 * @return string|false Download URL or false.
	 */
	private function get_zip_url( $release ) {
		if ( ! empty( $release->assets ) ) {
			foreach ( $release->assets as $asset ) {
				if ( substr( $asset->name, -4 ) === '.zip' ) {
					return $asset->browser_download_url;
				}
			}
		}

		// Fallback to zipball URL.
		if ( ! empty( $release->zipball_url ) ) {
			return $release->zipball_url;
		}

		return false;
	}

	/**
	 * Normalize a version tag (strip leading 'v').
	 *
	 * @param string $tag Version tag.
	 * @return string
	 */
	private function normalize_version( $tag ) {
		return ltrim( $tag, 'vV' );
	}

	/**
	 * Inject update data into the update_plugins transient.
	 *
	 * @param object $transient The update_plugins transient data.
	 * @return object
	 */
	public function check_for_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$release = $this->get_release_data();
		if ( ! $release ) {
			return $transient;
		}

		$remote_version = $this->normalize_version( $release->tag_name );
		$zip_url        = $this->get_zip_url( $release );

		if ( ! $zip_url ) {
			return $transient;
		}

		if ( version_compare( $remote_version, $this->current_version, '>' ) ) {
			$transient->response[ $this->plugin_basename ] = (object) array(
				'slug'        => $this->plugin_slug,
				'plugin'      => $this->plugin_basename,
				'new_version' => $remote_version,
				'url'         => 'https://github.com/' . $this->github_repo,
				'package'     => $zip_url,
			);
		}

		return $transient;
	}

	/**
	 * Provide plugin info for the "View Details" modal.
	 *
	 * @param false|object|array $result
	 * @param string             $action
	 * @param object             $args
	 * @return false|object
	 */
	public function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		if ( ! isset( $args->slug ) || $args->slug !== $this->plugin_slug ) {
			return $result;
		}

		$release = $this->get_release_data();
		if ( ! $release ) {
			return $result;
		}

		$info              = new stdClass();
		$info->name        = 'TCD Glossary';
		$info->slug        = $this->plugin_slug;
		$info->version     = $this->normalize_version( $release->tag_name );
		$info->author      = 'TCD';
		$info->homepage    = 'https://github.com/' . $this->github_repo;
		$info->sections    = array(
			'description' => 'Displays glossary terms in an A-Z grouped layout with Elementor widget support.',
			'changelog'   => nl2br( esc_html( $release->body ) ),
		);
		$info->download_link = $this->get_zip_url( $release );
		$info->requires      = '5.8';
		$info->tested        = '6.5';

		return $info;
	}

	/**
	 * Ensure the installed directory is named correctly after update.
	 *
	 * @param bool  $response
	 * @param array $hook_extra
	 * @param array $result
	 * @return array
	 */
	public function after_install( $response, $hook_extra, $result ) {
		global $wp_filesystem;

		if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->plugin_basename ) {
			return $result;
		}

		$proper_destination = WP_PLUGIN_DIR . '/' . $this->plugin_slug;

		if ( $result['destination'] !== $proper_destination ) {
			$wp_filesystem->move( $result['destination'], $proper_destination );
			$result['destination'] = $proper_destination;
		}

		activate_plugin( $this->plugin_basename );

		return $result;
	}
}
```

- [ ] **Step 2: Add GitHub repo constant and require updater in `tcd-glossary.php`**

Add the constant after the existing defines:

```php
define( 'TCD_GLOSSARY_GITHUB_REPO', 'tcd/tcd-glossary' );
```

Add the require after the other requires:

```php
require_once TCD_GLOSSARY_PATH . 'includes/class-tcd-updater.php';
```

Inside the `plugins_loaded` callback, add the updater alongside the settings:

```php
if ( is_admin() ) {
	new TCD_Settings();
	new TCD_Updater(
		plugin_basename( __FILE__ ),
		TCD_GLOSSARY_GITHUB_REPO,
		TCD_GLOSSARY_VERSION
	);
}
```

- [ ] **Step 3: Commit**

```bash
git add includes/class-tcd-updater.php tcd-glossary.php
git commit -m "feat: add GitHub release auto-updater"
```

---

### Task 10: Update Bootstrap File and Plugin Header

**Files:**
- Modify: `tcd-glossary.php`
- Modify: `readme.txt`

- [ ] **Step 1: Verify the final `tcd-glossary.php` looks like this**

```php
<?php
/**
 * Plugin Name: TCD Glossary
 * Description: A-Z glossary with Elementor widget, taxonomy filtering, and auto-updates from GitHub.
 * Version:     2.0.0
 * Author:      TCD
 * License:     GPL-2.0-or-later
 * Text Domain: tcd-glossary
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TCD_GLOSSARY_VERSION', '2.0.0' );
define( 'TCD_GLOSSARY_URL', plugin_dir_url( __FILE__ ) );
define( 'TCD_GLOSSARY_PATH', plugin_dir_path( __FILE__ ) );
define( 'TCD_GLOSSARY_GITHUB_REPO', 'tcd/tcd-glossary' );

require_once TCD_GLOSSARY_PATH . 'includes/class-tcd-glossary.php';
require_once TCD_GLOSSARY_PATH . 'includes/class-tcd-query.php';
require_once TCD_GLOSSARY_PATH . 'includes/class-tcd-settings.php';
require_once TCD_GLOSSARY_PATH . 'includes/class-tcd-updater.php';
require_once TCD_GLOSSARY_PATH . 'includes/elementor/class-tcd-elementor.php';

add_action( 'plugins_loaded', function () {
	TCD_Glossary::instance();

	if ( is_admin() ) {
		new TCD_Settings();
		new TCD_Updater(
			plugin_basename( __FILE__ ),
			TCD_GLOSSARY_GITHUB_REPO,
			TCD_GLOSSARY_VERSION
		);
	}

	if ( did_action( 'elementor/loaded' ) ) {
		new TCD_Elementor();
	}
} );
```

- [ ] **Step 2: Update `readme.txt`**

Replace the entire file with:

```
=== TCD Glossary ===
Contributors: tcd
Tags: glossary, elementor, cpt, taxonomy, a-z
Requires at least: 5.8
Tested up to: 6.5
Stable tag: 2.0.0
License: GPLv2 or later

A-Z glossary with Elementor widget support, taxonomy filtering, and GitHub auto-updates.

== Description ==

Display any custom post type as an A-Z grouped glossary. Features include:

* **Elementor Widget** with full style controls (typography, colors, spacing, borders, shadows)
* **Taxonomy filtering** via pill buttons or dropdown
* **Settings page** (Settings > TCD Glossary) to select which CPT and taxonomy to use
* **Shortcode** `[tcd_glossary]` for non-Elementor pages
* **Auto-updates** from GitHub releases

== Installation ==

1. Upload the `tcd-glossary` folder to `/wp-content/plugins/`.
2. Activate the plugin through the Plugins menu.
3. Go to Settings > TCD Glossary and select your post type and taxonomy.
4. Use the Elementor widget or add `[tcd_glossary]` to any page.

== Changelog ==

= 2.0.0 =
* Elementor widget with full style controls
* Settings page for CPT and taxonomy selection
* Frontend taxonomy filtering (pills and dropdown)
* GitHub release auto-updater
* Shared template system (shortcode + widget)

= 1.0.0 =
* Initial release.
```

- [ ] **Step 3: Commit**

```bash
git add tcd-glossary.php readme.txt
git commit -m "chore: update plugin header, version to 2.0.0, and readme"
```

---

### Task 11: Final Verification

- [ ] **Step 1: Verify file structure matches the spec**

Run:

```bash
find . -type f \( -name "*.php" -o -name "*.css" -o -name "*.js" -o -name "*.txt" \) | sort
```

Expected:

```
./assets/css/tcd-glossary.css
./assets/js/tcd-glossary.js
./includes/class-tcd-glossary.php
./includes/class-tcd-query.php
./includes/class-tcd-settings.php
./includes/class-tcd-updater.php
./includes/elementor/class-tcd-elementor.php
./includes/elementor/class-tcd-glossary-widget.php
./readme.txt
./tcd-glossary.php
./templates/glossary.php
```

- [ ] **Step 2: Check for PHP syntax errors**

Run:

```bash
find . -name "*.php" -exec php -l {} \;
```

Expected: `No syntax errors detected` for every file.

- [ ] **Step 3: Commit any fixes if needed**
