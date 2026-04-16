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
		add_action( 'wp_ajax_tcd_glossary_filter', array( 'TCD_Query', 'ajax_filter' ) );
		add_action( 'wp_ajax_nopriv_tcd_glossary_filter', array( 'TCD_Query', 'ajax_filter' ) );
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
