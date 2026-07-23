<?php
/**
 * Renderowanie tagów we front-endzie strony.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Atela_SEO_Frontend {

	public function __construct() {
		// Odpinamy domyślny canonical WordPressa, bo dodamy własny, bardziej zaawansowany
		remove_action( 'wp_head', 'rel_canonical' );
		
		// Renderowanie naszych tagów
		add_action( 'wp_head', array( $this, 'render_meta_tags' ), 1 );
		
		// Filtrowanie tytułu (wyższy priorytet, by nadpisać ewentualne inne filtry)
		add_filter( 'document_title_parts', array( $this, 'filter_document_title' ), 999 );
	}

	/**
	 * Główna metoda renderująca tagi w <head>
	 */
	public function render_meta_tags() {
		$this->render_robots();
		$this->render_canonical();
		$this->render_description();
	}

	/**
	 * Silnik zastępowania zmiennych w ciągach znaków
	 */
	public function replace_variables( $string, $post_id = 0 ) {
		if ( empty( $string ) ) {
			return '';
		}

		$options = get_option( 'atela_seo_options', array() );
		$sep     = isset( $options['separator'] ) ? $options['separator'] : '-';
		
		$replacements = array(
			'%site_name%' => get_bloginfo( 'name' ),
			'%site_desc%' => get_bloginfo( 'description' ),
			'%sep%'       => $sep,
		);

		// Zmienne zależne od kontekstu posta/strony
		if ( $post_id > 0 ) {
			$replacements['%title%'] = get_the_title( $post_id );
		}

		// Obsługa paginacji (%page%)
		global $page, $paged;
		$current_page = max( 1, get_query_var( 'paged' ), get_query_var( 'page' ) );
		$replacements['%page%'] = $current_page > 1 ? sprintf( 
			/* translators: %d: page number */
			__( 'Strona %d', 'atela-seo' ), 
			$current_page 
		) : '';

		// Wykonaj podmianę
		$string = str_replace( array_keys( $replacements ), array_values( $replacements ), $string );
		
		// Usuń ewentualne podwójne spacje czy wiszące separatory spowodowane pustymi zmiennymi
		$string = preg_replace( '/\s+/', ' ', $string );
		$string = trim( $string );
		
		return $string;
	}

	/**
	 * Renderowanie <meta name="robots">
	 */
	private function render_robots() {
		// Jeśli globalnie w WP wyłączone, nie dublujemy (WP sam wyrzuci <meta name='robots' content='noindex, nofollow' />)
		if ( get_option( 'blog_public' ) == '0' ) {
			return;
		}

		$robots = array( 'index', 'follow' );
		
		// Indywidualne ustawienia posta nadpisują globalne
		if ( is_singular() ) {
			$post_id = get_the_ID();
			$post_noindex = get_post_meta( $post_id, '_atela_seo_noindex', true );
			
			if ( $post_noindex ) {
				$robots = array( 'noindex', 'nofollow' );
			}
		}

		echo '<meta name="robots" content="' . esc_attr( implode( ', ', $robots ) ) . '" />' . "\n";
	}

	/**
	 * Renderowanie <link rel="canonical">
	 */
	private function render_canonical() {
		$canonical_url = '';

		if ( is_singular() ) {
			$post_id = get_the_ID();
			
			// Jeśli ustawiono niestandardowy canonical (funkcja na przyszłość)
			$custom_canonical = get_post_meta( $post_id, '_atela_seo_canonical', true );
			
			if ( ! empty( $custom_canonical ) ) {
				$canonical_url = $custom_canonical;
			} else {
				$canonical_url = get_permalink( $post_id );
			}
		} elseif ( is_front_page() || is_home() ) {
			$canonical_url = home_url( '/' );
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$canonical_url = get_term_link( get_queried_object() );
		}

		// Dodanie numeru strony do canonical (paginacja)
		global $wp_rewrite;
		$current_page = max( 1, get_query_var( 'paged' ), get_query_var( 'page' ) );
		if ( $current_page > 1 && ! empty( $canonical_url ) ) {
			if ( $wp_rewrite->using_permalinks() ) {
				$canonical_url = user_trailingslashit( trailingslashit( $canonical_url ) . $wp_rewrite->pagination_base . '/' . $current_page, 'paged' );
			} else {
				$canonical_url = add_query_arg( 'paged', $current_page, $canonical_url );
			}
		}

		if ( ! empty( $canonical_url ) && ! is_wp_error( $canonical_url ) ) {
			echo '<link rel="canonical" href="' . esc_url( $canonical_url ) . '" />' . "\n";
		}
	}

	/**
	 * Renderowanie <meta name="description">
	 */
	private function render_description() {
		$description = '';
		$post_id     = 0;

		if ( is_singular() ) {
			$post_id = get_the_ID();
			$description = get_post_meta( $post_id, '_atela_seo_description', true );
		} elseif ( is_front_page() || is_home() ) {
			$options = get_option( 'atela_seo_options', array() );
			$description = isset( $options['home_description'] ) ? $options['home_description'] : '';
		}

		if ( ! empty( $description ) ) {
			// Zastąp zmienne w opisie
			$description = $this->replace_variables( $description, $post_id );
			echo '<meta name="description" content="' . esc_attr( $description ) . '" />' . "\n";
		}
	}

	/**
	 * Filtrowanie <title> z użyciem zmiennych
	 */
	public function filter_document_title( $title ) {
		$custom_title = '';
		$post_id      = 0;

		if ( is_singular() ) {
			$post_id = get_the_ID();
			$custom_title = get_post_meta( $post_id, '_atela_seo_title', true );
		} elseif ( is_front_page() || is_home() ) {
			$options = get_option( 'atela_seo_options', array() );
			$custom_title = isset( $options['home_title'] ) ? $options['home_title'] : '';
		}

		if ( ! empty( $custom_title ) ) {
			$processed_title = $this->replace_variables( $custom_title, $post_id );
			
			// Przebudowujemy całą tablicę $title, aby WordPress użył dokładnie naszego ciągu
			$title = array( 'title' => $processed_title );
		}

		return $title;
	}
}
