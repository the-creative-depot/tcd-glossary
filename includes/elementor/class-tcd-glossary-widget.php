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

		$this->add_responsive_control( 'nav_sticky_offset', array(
			'label'      => __( 'Sticky Offset', 'tcd-glossary' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => array( 'px', 'em', 'rem' ),
			'range'      => array(
				'px' => array(
					'min' => 0,
					'max' => 200,
				),
			),
			'default'    => array(
				'size' => 0,
				'unit' => 'px',
			),
			'selectors'  => array(
				'{{WRAPPER}} .tcd-glossary__nav' => 'top: {{SIZE}}{{UNIT}};',
			),
			'condition'  => array(
				'nav_sticky' => 'yes',
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

		$this->add_control( 'nav_hover_bg_color', array(
			'label'     => __( 'Hover Background', 'tcd-glossary' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array(
				'{{WRAPPER}} .tcd-glossary__nav-link.is-active:hover' => 'background-color: {{VALUE}};',
				'{{WRAPPER}} .tcd-glossary__nav-link.is-active:focus' => 'background-color: {{VALUE}};',
			),
		) );

		$this->add_control( 'nav_hover_text_color', array(
			'label'     => __( 'Hover Text Color', 'tcd-glossary' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array(
				'{{WRAPPER}} .tcd-glossary__nav-link.is-active:hover' => 'color: {{VALUE}};',
				'{{WRAPPER}} .tcd-glossary__nav-link.is-active:focus' => 'color: {{VALUE}};',
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
