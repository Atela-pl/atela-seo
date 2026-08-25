<?php
/**
 * Elementor Widget: Atela SEO Okruszki (Breadcrumbs).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Atela_SEO_Breadcrumbs_Widget extends \Elementor\Widget_Base {

	public function get_name() {
		return 'atela_seo_breadcrumbs';
	}

	public function get_title() {
		return 'Atela SEO Breadcrumbs';
	}

	public function get_icon() {
		return 'eicon-nav-menu';
	}

	public function get_categories() {
		return [ 'general', 'theme-elements' ];
	}

	public function get_keywords() {
		return [ 'seo', 'breadcrumbs', 'okruszki', 'nawigacja', 'alpha' ];
	}

	protected function register_controls() {
		// Content Tab
		$this->start_controls_section(
			'content_section',
			[
				'label' => __( 'Breadcrumbs Settings', 'atela-seo' ),
				'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'notice',
			[
				'type' => \Elementor\Controls_Manager::RAW_HTML,
				'raw' => __( 'The configuration of separators and texts is located in the main settings panel <b>Atela SEO &rarr; Breadcrumbs</b>.', 'atela-seo' ),
				'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
			]
		);

		$this->add_responsive_control(
			'align',
			[
				'label' => __( 'Alignment', 'atela-seo' ),
				'type' => \Elementor\Controls_Manager::CHOOSE,
				'options' => [
					'flex-start' => [
						'title' => __( 'Left', 'atela-seo' ),
						'icon' => 'eicon-text-align-left',
					],
					'center' => [
						'title' => __( 'Center', 'atela-seo' ),
						'icon' => 'eicon-text-align-center',
					],
					'flex-end' => [
						'title' => __( 'Right', 'atela-seo' ),
						'icon' => 'eicon-text-align-right',
					],
				],
				'selectors' => [
					'{{WRAPPER}} .aseo-breadcrumbs__list' => 'justify-content: {{VALUE}};',
				],
			]
		);

		$this->end_controls_section();

		// Style Tab
		$this->start_controls_section(
			'style_section',
			[
				'label' => __( 'Breadcrumbs Style', 'atela-seo' ),
				'tab' => \Elementor\Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name' => 'typography',
				'selector' => '{{WRAPPER}} .aseo-breadcrumbs',
			]
		);

		$this->add_control(
			'link_color',
			[
				'label' => __( 'Link Color', 'atela-seo' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .aseo-breadcrumbs__link' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'link_hover_color',
			[
				'label' => __( 'Link Hover Color', 'atela-seo' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .aseo-breadcrumbs__link:hover' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'current_color',
			[
				'label' => __( 'Current Page Color', 'atela-seo' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .aseo-breadcrumbs__current' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'separator_color',
			[
				'label' => __( 'Separator Color', 'atela-seo' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .aseo-breadcrumbs__separator' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_responsive_control(
			'gap',
			[
				'label' => __( 'Gap', 'atela-seo' ),
				'type' => \Elementor\Controls_Manager::SLIDER,
				'range' => [
					'px' => [
						'min' => 0,
						'max' => 50,
					],
				],
				'selectors' => [
					'{{WRAPPER}} .aseo-breadcrumbs__separator' => 'margin-left: {{SIZE}}{{UNIT}}; margin-right: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();
	}

	protected function render() {
		if ( function_exists( 'atela_seo_breadcrumbs' ) ) {
			atela_seo_breadcrumbs();
		}
	}
}
