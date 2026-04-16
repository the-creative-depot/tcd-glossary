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
