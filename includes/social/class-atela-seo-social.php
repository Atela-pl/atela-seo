<?php
/**
 * Moduł Social Media – Open Graph & Twitter Cards
 *
 * Renderuje tagi OG i Twitter Cards w <head> strony,
 * korzystając z metadanych zapisanych per-post i globalnych ustawień.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Atela_SEO_Social {

	public function __construct() {
		add_action( 'wp_head', array( $this, 'render_social_tags' ), 2 );
	}

	/**
	 * Główna metoda – renderuje wszystkie tagi social
	 */
	public function render_social_tags() {
		$data = $this->get_social_data();

		if ( empty( $data ) ) {
			return;
		}

		echo "\n<!-- Atela SEO: Open Graph / Social -->\n";
		$this->render_open_graph( $data );
		$this->render_twitter_cards( $data );
		echo "<!-- /Atela SEO: Open Graph / Social -->\n\n";
	}

	/**
	 * Zbiera dane social dla aktualnego kontekstu strony
	 */
	private function get_social_data() {
		$options = get_option( 'atela_seo_options', array() );
		$frontend = new Atela_SEO_Frontend();

		$data = array(
			'site_name' => get_bloginfo( 'name' ),
			'locale'    => str_replace( '-', '_', get_bloginfo( 'language' ) ),
		);

		if ( is_singular() ) {
			$post_id = get_the_ID();
			$post    = get_post( $post_id );

			// Tytuł OG – własny > SEO Title > post title
			$og_title = get_post_meta( $post_id, '_atela_seo_og_title', true );
			if ( empty( $og_title ) ) {
				$og_title = get_post_meta( $post_id, '_atela_seo_title', true );
			}
			if ( empty( $og_title ) ) {
				$og_title = get_the_title( $post_id );
			}
			$data['title'] = $frontend->replace_variables( $og_title, $post_id );

			// Opis OG – własny > meta description > excerpt
			$og_desc = get_post_meta( $post_id, '_atela_seo_og_description', true );
			if ( empty( $og_desc ) ) {
				$og_desc = get_post_meta( $post_id, '_atela_seo_description', true );
			}
			if ( empty( $og_desc ) ) {
				$og_desc = wp_trim_words( get_the_excerpt( $post ), 30 );
			}
			$data['description'] = $frontend->replace_variables( $og_desc, $post_id );

			// URL
			$data['url']  = get_permalink( $post_id );
			$data['type'] = ( $post->post_type === 'post' ) ? 'article' : 'website';

			// Daty (dla type=article)
			if ( $data['type'] === 'article' ) {
				$data['published_time'] = get_the_date( 'c', $post_id );
				$data['modified_time']  = get_the_modified_date( 'c', $post_id );
				$data['author']         = get_the_author_meta( 'display_name', $post->post_author );
			}

			// Obraz – własny OG image ID > URL z Elementora > featured image > obraz globalny
			$og_image_id = get_post_meta( $post_id, '_atela_seo_og_image_id', true );
			if ( $og_image_id ) {
				$img_url = Atela_SEO_Core::get_letterboxed_image_url( $og_image_id );
				if ( $img_url ) {
					$data['image']        = $img_url;
					$data['image_width']  = 1200;
					$data['image_height'] = 630;
					$data['image_alt']    = get_post_meta( $og_image_id, '_wp_attachment_image_alt', true );
				}
			}

			// Fallback: URL wpisany ręcznie w Elementorze
			if ( empty( $data['image'] ) ) {
				$og_image_url = get_post_meta( $post_id, '_atela_seo_og_image_url', true );
				if ( ! empty( $og_image_url ) ) {
					$data['image'] = esc_url( $og_image_url );
				}
			}

			// Fallback: featured image
			if ( empty( $data['image'] ) && has_post_thumbnail( $post_id ) ) {
				$thumb_id = get_post_thumbnail_id( $post_id );
				$img_url  = Atela_SEO_Core::get_letterboxed_image_url( $thumb_id );
				if ( $img_url ) {
					$data['image']        = $img_url;
					$data['image_width']  = 1200;
					$data['image_height'] = 630;
					$data['image_alt']    = get_post_meta( $thumb_id, '_wp_attachment_image_alt', true );
				}
			}

			// Twitter Card – własna > globalna
			$data['twitter_card']    = get_post_meta( $post_id, '_atela_seo_twitter_card', true ) ?: ( $options['twitter_card_type'] ?? 'summary_large_image' );
			$data['twitter_creator'] = get_post_meta( $post_id, '_atela_seo_twitter_creator', true ) ?: ( $options['twitter_creator'] ?? '' );

		} elseif ( is_front_page() || is_home() ) {
			$data['title']       = $frontend->replace_variables( $options['home_title'] ?? get_bloginfo( 'name' ) );
			$data['description'] = $options['home_description'] ?? get_bloginfo( 'description' );
			$data['url']         = home_url( '/' );
			$data['type']        = 'website';
		} else {
			// Archiwa, taksonomie itp. – dane minimalne
			$data['title'] = wp_title( '|', false, 'right' ) . get_bloginfo( 'name' );
			$data['url']   = get_pagenum_link();
			$data['type']  = 'website';
		}

		// Globalny obraz fallback (jeśli nie ma OG image)
		if ( empty( $data['image'] ) && ! empty( $options['og_default_image_id'] ) ) {
			$img_url = Atela_SEO_Core::get_letterboxed_image_url( $options['og_default_image_id'] );
			if ( $img_url ) {
				$data['image']        = $img_url;
				$data['image_width']  = 1200;
				$data['image_height'] = 630;
			}
		}

		// Twitter site handle (globalny)
		$data['twitter_site'] = $options['twitter_site'] ?? '';

		return $data;
	}

	/**
	 * Renderowanie tagów Open Graph
	 */
	private function render_open_graph( $data ) {
		$this->og_tag( 'og:type',        $data['type'] ?? 'website' );
		$this->og_tag( 'og:site_name',   $data['site_name'] ?? '' );
		$this->og_tag( 'og:locale',      $data['locale'] ?? 'pl_PL' );
		$this->og_tag( 'og:url',         $data['url'] ?? '' );
		$this->og_tag( 'og:title',       $data['title'] ?? '' );
		$this->og_tag( 'og:description', $data['description'] ?? '' );

		if ( ! empty( $data['image'] ) ) {
			$this->og_tag( 'og:image', $data['image'] );
			if ( ! empty( $data['image_width'] ) )  $this->og_tag( 'og:image:width',  $data['image_width'] );
			if ( ! empty( $data['image_height'] ) )  $this->og_tag( 'og:image:height', $data['image_height'] );
			if ( ! empty( $data['image_alt'] ) )     $this->og_tag( 'og:image:alt',    $data['image_alt'] );
			$this->og_tag( 'og:image:type', 'image/jpeg' );
		}

		// Tagi specyficzne dla artykułów
		if ( ( $data['type'] ?? '' ) === 'article' ) {
			if ( ! empty( $data['published_time'] ) ) $this->og_tag( 'article:published_time', $data['published_time'] );
			if ( ! empty( $data['modified_time'] ) )  $this->og_tag( 'article:modified_time',  $data['modified_time'] );
			if ( ! empty( $data['author'] ) )          $this->og_tag( 'article:author',          $data['author'] );
		}
	}

	/**
	 * Renderowanie tagów Twitter Cards
	 */
	private function render_twitter_cards( $data ) {
		$card_type = $data['twitter_card'] ?? 'summary_large_image';

		$this->twitter_tag( 'twitter:card',        $card_type );
		$this->twitter_tag( 'twitter:title',       $data['title'] ?? '' );
		$this->twitter_tag( 'twitter:description', $data['description'] ?? '' );

		if ( ! empty( $data['twitter_site'] ) ) {
			$this->twitter_tag( 'twitter:site', '@' . ltrim( $data['twitter_site'], '@' ) );
		}
		if ( ! empty( $data['twitter_creator'] ) ) {
			$this->twitter_tag( 'twitter:creator', '@' . ltrim( $data['twitter_creator'], '@' ) );
		}
		if ( ! empty( $data['image'] ) ) {
			$this->twitter_tag( 'twitter:image', $data['image'] );
			if ( ! empty( $data['image_alt'] ) ) {
				$this->twitter_tag( 'twitter:image:alt', $data['image_alt'] );
			}
		}
	}

	/**
	 * Pomocnicze: wydrukuj tag og:
	 */
	private function og_tag( $property, $content ) {
		if ( empty( $content ) ) return;
		echo '<meta property="' . esc_attr( $property ) . '" content="' . esc_attr( $content ) . '" />' . "\n";
	}

	/**
	 * Pomocnicze: wydrukuj tag twitter:
	 */
	private function twitter_tag( $name, $content ) {
		if ( empty( $content ) ) return;
		echo '<meta name="' . esc_attr( $name ) . '" content="' . esc_attr( $content ) . '" />' . "\n";
	}
}
