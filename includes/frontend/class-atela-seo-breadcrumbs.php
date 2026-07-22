<?php
/**
 * Moduł Breadcrumbs (Okruszki).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Atela_SEO_Breadcrumbs {

	public function __construct() {
		add_shortcode( 'atela_seo_breadcrumbs', array( $this, 'shortcode_handler' ) );
		add_action( 'wp_head', array( $this, 'print_schema_json_ld' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
	}

	public function enqueue_styles() {
		wp_enqueue_style(
			'atela-seo-breadcrumbs',
			ALPHA_SEO_URL . 'assets/css/breadcrumbs.css',
			array(),
			filemtime( ALPHA_SEO_DIR . 'assets/css/breadcrumbs.css' )
		);
	}

	public function shortcode_handler( $atts ) {
		return self::render();
	}

	/**
	 * Główna metoda renderująca HTML okruszków.
	 */
	public static function render() {
		$options = get_option( 'atela_seo_options', array() );

		$items = self::get_items();
		if ( empty( $items ) ) {
			return '';
		}

		$sep = $options['breadcrumbs_separator'] ?? '›';
		
		$html = '<nav aria-label="breadcrumb" class="aseo-breadcrumbs">';
		$html .= '<ol class="aseo-breadcrumbs__list" itemscope itemtype="https://schema.org/BreadcrumbList">';

		$count = count( $items );
		foreach ( $items as $i => $item ) {
			$is_last = ( $i === $count - 1 );
			$position = $i + 1;

			$html .= '<li class="aseo-breadcrumbs__item" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">';

			if ( ! $is_last && ! empty( $item['url'] ) ) {
				$html .= '<a href="' . esc_url( $item['url'] ) . '" class="aseo-breadcrumbs__link" itemprop="item">';
				$html .= '<span itemprop="name">' . esc_html( $item['name'] ) . '</span>';
				$html .= '</a>';
			} else {
				$html .= '<span class="aseo-breadcrumbs__current" itemprop="name" aria-current="page">' . esc_html( $item['name'] ) . '</span>';
			}

			$html .= '<meta itemprop="position" content="' . esc_attr( $position ) . '" />';
			$html .= '</li>';

			if ( ! $is_last ) {
				$html .= '<li class="aseo-breadcrumbs__separator" aria-hidden="true">' . esc_html( $sep ) . '</li>';
			}
		}

		$html .= '</ol>';
		$html .= '</nav>';

		return $html;
	}

	/**
	 * Wstrzykuje BreadcrumbList JSON-LD do sekcji <head>.
	 */
	public function print_schema_json_ld() {
		$options = get_option( 'atela_seo_options', array() );
		if ( empty( $options['breadcrumbs_enabled'] ) ) {
			return;
		}

		$items = self::get_items();
		if ( empty( $items ) ) {
			return;
		}

		$schema = array(
			'@context' => 'https://schema.org',
			'@type'    => 'BreadcrumbList',
			'itemListElement' => array(),
		);

		foreach ( $items as $i => $item ) {
			$schema['itemListElement'][] = array(
				'@type'    => 'ListItem',
				'position' => $i + 1,
				'name'     => $item['name'],
				'item'     => ! empty( $item['url'] ) ? $item['url'] : null,
			);
		}

		// Usuwamy null item z ostatniego elementu
		$last_idx = count($schema['itemListElement']) - 1;
		if ( empty( $schema['itemListElement'][$last_idx]['item'] ) ) {
			global $wp;
			$schema['itemListElement'][$last_idx]['item'] = home_url( add_query_arg( array(), $wp->request ) );
		}

		echo "\n<!-- Atela SEO Breadcrumbs Schema -->\n";
		echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . '</script>';
		echo "\n";
	}

	/**
	 * Zbiera elementy okruszków na podstawie aktualnego zapytania (WP Query).
	 */
	public static function get_items() {
		$options = get_option( 'atela_seo_options', array() );
		$items   = array();

		$show_home    = isset( $options['breadcrumbs_show_home'] ) ? (bool) $options['breadcrumbs_show_home'] : true;
		$show_current = isset( $options['breadcrumbs_show_current'] ) ? (bool) $options['breadcrumbs_show_current'] : true;
		$show_blog    = isset( $options['breadcrumbs_show_blog'] ) ? (bool) $options['breadcrumbs_show_blog'] : true;

		$home_text    = $options['breadcrumbs_home_text'] ?? 'Strona Główna';
		$blog_text    = $options['breadcrumbs_blog_text'] ?? 'Blog';
		$text_404     = $options['breadcrumbs_404_text'] ?? 'Nie znaleziono';

		if ( is_front_page() ) {
			if ( $show_home && $show_current ) {
				$items[] = array( 'name' => $home_text, 'url' => '' );
			}
			return $items;
		}

		if ( $show_home ) {
			$items[] = array(
				'name' => $home_text,
				'url'  => home_url( '/' )
			);
		}

		if ( is_home() && ! is_front_page() ) {
			if ( $show_current ) {
				$items[] = array( 'name' => get_the_title( get_option('page_for_posts', true) ), 'url' => '' );
			}
			return $items;
		}

		if ( is_singular() ) {
			global $post;
			
			if ( is_singular( 'post' ) && $show_blog && get_option( 'page_for_posts' ) ) {
				$items[] = array(
					'name' => $blog_text,
					'url'  => get_permalink( get_option( 'page_for_posts' ) )
				);
			}

			if ( is_singular( 'post' ) ) {
				$cats = get_the_category();
				if ( ! empty( $cats ) ) {
					$cat = $cats[0];
					$ancestors = get_ancestors( $cat->term_id, 'category' );
					$ancestors = array_reverse( $ancestors );
					foreach ( $ancestors as $anc_id ) {
						$anc_term = get_term( $anc_id, 'category' );
						$items[] = array(
							'name' => $anc_term->name,
							'url'  => get_term_link( $anc_term )
						);
					}
					$items[] = array(
						'name' => $cat->name,
						'url'  => get_term_link( $cat )
					);
				}
			}

			if ( is_page() && $post->post_parent ) {
				$ancestors = get_post_ancestors( $post->ID );
				$ancestors = array_reverse( $ancestors );
				foreach ( $ancestors as $anc_id ) {
					$items[] = array(
						'name' => get_the_title( $anc_id ),
						'url'  => get_permalink( $anc_id )
					);
				}
			}

			if ( $show_current ) {
				$items[] = array( 'name' => get_the_title(), 'url' => '' );
			}
		} elseif ( is_category() ) {
			if ( $show_blog && get_option( 'page_for_posts' ) ) {
				$items[] = array( 'name' => $blog_text, 'url'  => get_permalink( get_option( 'page_for_posts' ) ) );
			}
			$cat = get_queried_object();
			$ancestors = get_ancestors( $cat->term_id, 'category' );
			$ancestors = array_reverse( $ancestors );
			foreach ( $ancestors as $anc_id ) {
				$anc_term = get_term( $anc_id, 'category' );
				$items[] = array( 'name' => $anc_term->name, 'url'  => get_term_link( $anc_term ) );
			}
			if ( $show_current ) {
				$items[] = array( 'name' => single_cat_title( '', false ), 'url' => '' );
			}
		} elseif ( is_tag() ) {
			if ( $show_blog && get_option( 'page_for_posts' ) ) {
				$items[] = array( 'name' => $blog_text, 'url'  => get_permalink( get_option( 'page_for_posts' ) ) );
			}
			if ( $show_current ) {
				$items[] = array( 'name' => single_tag_title( '', false ), 'url' => '' );
			}
		} elseif ( is_search() ) {
			if ( $show_current ) {
				$search_text = $options['breadcrumbs_search_text'] ?? 'Szukaj: %s';
				$items[] = array( 'name' => sprintf( $search_text, get_search_query() ), 'url' => '' );
			}
		} elseif ( is_404() ) {
			if ( $show_current ) {
				$items[] = array( 'name' => $text_404, 'url' => '' );
			}
		} elseif ( is_archive() ) {
			if ( $show_blog && get_option( 'page_for_posts' ) ) {
				$items[] = array( 'name' => $blog_text, 'url'  => get_permalink( get_option( 'page_for_posts' ) ) );
			}
			if ( $show_current ) {
				$archive_text = $options['breadcrumbs_archive_text'] ?? 'Archiwum: %s';
				$items[] = array( 'name' => sprintf( $archive_text, get_the_archive_title() ), 'url' => '' );
			}
		}

		if ( count( $items ) <= 1 ) {
			return array();
		}

		return $items;
	}
}
