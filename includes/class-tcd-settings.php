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
