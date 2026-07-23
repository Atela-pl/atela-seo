<?php
/**
 * Moduł XML Sitemap Generator.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Atela_SEO_Sitemap {

	public function __construct() {
		// parse_request fires po ustaleniu query, ale przed zapytaniem do DB – idealne do przejęcia sitemapy
		add_action( 'parse_request',     array( $this, 'intercept_sitemap_request' ) );
		add_filter( 'robots_txt',        array( $this, 'add_to_robots_txt' ), 10, 2 );
		add_action( 'wp_ajax_atela_seo_ping_search_engines', array( $this, 'ajax_ping_search_engines' ) );
	}

	/* -------------------------------------------------------------------------
	 * Przechwycenie żądania do sitemap.xml / sitemap_index.xml
	 * ---------------------------------------------------------------------- */
	public function intercept_sitemap_request() {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$request = $request_uri ? wp_parse_url( $request_uri, PHP_URL_PATH ) : '';
		$request = rtrim( ltrim( $request, '/' ), '/' );

		// Usuń prefix bazowy (np. /bc/ jeśli WP jest w podfolderze)
		$base = rtrim( ltrim( wp_parse_url( home_url(), PHP_URL_PATH ) ?? '', '/' ), '/' );
		if ( $base && strpos( $request, $base ) === 0 ) {
			$request = ltrim( substr( $request, strlen( $base ) ), '/' );
		}

		// Sprawdź czy to żądanie sitemapy
		$is_single_sitemap = ( $request === 'sitemap.xml' );
		$is_index_sitemap  = ( $request === 'sitemap_index.xml' );
		$is_sub_sitemap    = preg_match( '/^sitemap-([a-z0-9_-]+)\.xml$/', $request, $m );

		if ( ! $is_single_sitemap && ! $is_index_sitemap && ! $is_sub_sitemap ) {
			return; // Nie dotyczy nas
		}

		$options = get_option( 'atela_seo_options', array() );
		if ( empty( $options['sitemap_enabled'] ) ) {
			// Sitemap wyłączona – pozwól WP obsłużyć (pokaże 404)
			return;
		}

		$mode = $options['sitemap_mode'] ?? 'single';

		if ( $is_index_sitemap ) {
			$this->serve_xml( $this->generate_index_xml() );
		} elseif ( $is_sub_sitemap ) {
			$this->serve_xml( $this->generate_sitemap_xml( $m[1] ) );
		} elseif ( $is_single_sitemap ) {
			if ( $mode === 'index' ) {
				wp_safe_redirect( home_url( '/sitemap_index.xml' ), 301 );
				exit;
			}
			$this->serve_xml( $this->generate_sitemap_xml() );
		}
	}

	/* -------------------------------------------------------------------------
	 * Serwowanie XML
	 * ---------------------------------------------------------------------- */
	private function serve_xml( $xml ) {
		header( 'Content-Type: text/xml; charset=UTF-8' );
		header( 'X-Robots-Tag: noindex, follow', true );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $xml;
		exit;
	}

	/* -------------------------------------------------------------------------
	 * Generowanie pełnego sitemap.xml (single mode) lub sitemap-{type}.xml
	 * ---------------------------------------------------------------------- */
	public function generate_sitemap_xml( $type = null ) {
		$options = get_option( 'atela_seo_options', array() );

		$changefreq     = $options['sitemap_changefreq']     ?? 'weekly';
		$pri_home       = $options['sitemap_priority_home']  ?? '1.0';
		$pri_posts      = $options['sitemap_priority_posts'] ?? '0.8';
		$pri_pages      = $options['sitemap_priority_pages'] ?? '0.7';
		$pri_tax        = $options['sitemap_priority_tax']   ?? '0.5';
		$inc_posts      = ! empty( $options['sitemap_include_posts'] );
		$inc_pages      = ! empty( $options['sitemap_include_pages'] );
		$inc_cats       = ! empty( $options['sitemap_include_categories'] );
		$inc_images     = ! empty( $options['sitemap_include_images'] );
		$exc_noindex    = ! empty( $options['sitemap_exclude_noindex'] );
		$extra_pts      = (array) ( $options['sitemap_post_types'] ?? [] );

		$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
		$xml .= '        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

		// Strona główna
		if ( $type === null || $type === 'home' ) {
			$xml .= $this->url_entry( home_url( '/' ), current_time( 'Y-m-d' ), 'daily', $pri_home );
		}

		// Wpisy
		if ( ( $type === null && $inc_posts ) || $type === 'post' ) {
			$posts = get_posts( array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			) );
			foreach ( $posts as $id ) {
				if ( $exc_noindex && get_post_meta( $id, '_atela_seo_noindex', true ) ) {
					continue;
				}
				$images = $inc_images ? $this->get_post_images( $id ) : [];
				$xml .= $this->url_entry(
					get_permalink( $id ),
					get_the_modified_date( 'Y-m-d', $id ),
					$changefreq,
					$pri_posts,
					$images
				);
			}
		}

		// Strony
		if ( ( $type === null && $inc_pages ) || $type === 'page' ) {
			$pages = get_posts( array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			) );
			foreach ( $pages as $id ) {
				if ( $exc_noindex && get_post_meta( $id, '_atela_seo_noindex', true ) ) {
					continue;
				}
				$images = $inc_images ? $this->get_post_images( $id ) : [];
				$xml .= $this->url_entry(
					get_permalink( $id ),
					get_the_modified_date( 'Y-m-d', $id ),
					$changefreq,
					$pri_pages,
					$images
				);
			}
		}

		// Własne typy wpisów (CPT)
		if ( ! empty( $extra_pts ) ) {
			foreach ( $extra_pts as $pt ) {
				if ( $type !== null && $type !== $pt ) {
					continue;
				}
				$cpt_posts = get_posts( array(
					'post_type'      => sanitize_key( $pt ),
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'fields'         => 'ids',
				) );
				foreach ( $cpt_posts as $id ) {
					if ( $exc_noindex && get_post_meta( $id, '_atela_seo_noindex', true ) ) {
						continue;
					}
					$images = $inc_images ? $this->get_post_images( $id ) : [];
					$xml .= $this->url_entry(
						get_permalink( $id ),
						get_the_modified_date( 'Y-m-d', $id ),
						$changefreq,
						$pri_posts,
						$images
					);
				}
			}
		}

		// Kategorie i tagi
		if ( ( $type === null && $inc_cats ) || $type === 'taxonomy' ) {
			$terms = get_terms( array(
				'taxonomy'   => array( 'category', 'post_tag' ),
				'hide_empty' => true,
			) );
			if ( ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) {
					$xml .= $this->url_entry(
						get_term_link( $term ),
						current_time( 'Y-m-d' ),
						'weekly',
						$pri_tax
					);
				}
			}
		}

		$xml .= '</urlset>';
		return $xml;
	}

	/* -------------------------------------------------------------------------
	 * Generowanie sitemap_index.xml
	 * ---------------------------------------------------------------------- */
	public function generate_index_xml() {
		$options    = get_option( 'atela_seo_options', array() );
		$inc_posts  = ! empty( $options['sitemap_include_posts'] );
		$inc_pages  = ! empty( $options['sitemap_include_pages'] );
		$inc_cats   = ! empty( $options['sitemap_include_categories'] );
		$extra_pts  = (array) ( $options['sitemap_post_types'] ?? [] );
		$now        = current_time( 'Y-m-d' );

		$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

		// Zawsze home
		$xml .= $this->sitemap_entry( home_url( '/sitemap-home.xml' ), $now );

		if ( $inc_posts ) {
			$xml .= $this->sitemap_entry( home_url( '/sitemap-post.xml' ), $now );
		}
		if ( $inc_pages ) {
			$xml .= $this->sitemap_entry( home_url( '/sitemap-page.xml' ), $now );
		}
		if ( $inc_cats ) {
			$xml .= $this->sitemap_entry( home_url( '/sitemap-taxonomy.xml' ), $now );
		}
		foreach ( $extra_pts as $pt ) {
			$xml .= $this->sitemap_entry( home_url( '/sitemap-' . sanitize_key( $pt ) . '.xml' ), $now );
		}

		$xml .= '</sitemapindex>';
		return $xml;
	}

	/* -------------------------------------------------------------------------
	 * Pomocnik: budowanie tagu <url>
	 * ---------------------------------------------------------------------- */
	private function url_entry( $loc, $lastmod, $changefreq, $priority, $images = [] ) {
		$xml  = "\t<url>\n";
		$xml .= "\t\t<loc>" . esc_url( $loc ) . "</loc>\n";
		$xml .= "\t\t<lastmod>" . esc_html( $lastmod ) . "</lastmod>\n";
		$xml .= "\t\t<changefreq>" . esc_html( $changefreq ) . "</changefreq>\n";
		$xml .= "\t\t<priority>" . esc_html( $priority ) . "</priority>\n";
		foreach ( $images as $img ) {
			$xml .= "\t\t<image:image>\n";
			$xml .= "\t\t\t<image:loc>" . esc_url( $img['url'] ) . "</image:loc>\n";
			if ( ! empty( $img['title'] ) ) {
				$xml .= "\t\t\t<image:title>" . esc_html( $img['title'] ) . "</image:title>\n";
			}
			$xml .= "\t\t</image:image>\n";
		}
		$xml .= "\t</url>\n";
		return $xml;
	}

	/* -------------------------------------------------------------------------
	 * Pomocnik: budowanie tagu <sitemap> (dla indeksu)
	 * ---------------------------------------------------------------------- */
	private function sitemap_entry( $loc, $lastmod ) {
		$xml  = "\t<sitemap>\n";
		$xml .= "\t\t<loc>" . esc_url( $loc ) . "</loc>\n";
		$xml .= "\t\t<lastmod>" . esc_html( $lastmod ) . "</lastmod>\n";
		$xml .= "\t</sitemap>\n";
		return $xml;
	}

	/* -------------------------------------------------------------------------
	 * Pobieranie obrazów z wpisu (featured + z treści)
	 * ---------------------------------------------------------------------- */
	private function get_post_images( $post_id ) {
		$images = [];

		// Featured image
		$thumb_id = get_post_thumbnail_id( $post_id );
		if ( $thumb_id ) {
			$url = wp_get_attachment_image_url( $thumb_id, 'full' );
			if ( $url ) {
				$images[] = array(
					'url'   => $url,
					'title' => get_the_title( $thumb_id ),
				);
			}
		}

		// Obrazy z treści (content)
		$post = get_post( $post_id );
		if ( $post && ! empty( $post->post_content ) ) {
			preg_match_all( '/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $post->post_content, $matches );
			if ( ! empty( $matches[1] ) ) {
				foreach ( array_slice( $matches[1], 0, 5 ) as $src ) { // max 5 obrazów z treści
					if ( filter_var( $src, FILTER_VALIDATE_URL ) ) {
						$images[] = array( 'url' => $src, 'title' => '' );
					}
				}
			}
		}

		return $images;
	}

	/* -------------------------------------------------------------------------
	 * Dodanie Sitemap do robots.txt
	 * ---------------------------------------------------------------------- */
	public function add_to_robots_txt( $output, $public ) {
		if ( ! $public ) {
			return $output;
		}
		$options = get_option( 'atela_seo_options', array() );
		if ( empty( $options['sitemap_enabled'] ) ) {
			return $output;
		}
		$mode        = $options['sitemap_mode'] ?? 'single';
		$sitemap_url = ( $mode === 'index' )
			? home_url( '/sitemap_index.xml' )
			: home_url( '/sitemap.xml' );

		$output .= "\nSitemap: " . esc_url( $sitemap_url ) . "\n";
		return $output;
	}

	/* -------------------------------------------------------------------------
	 * AJAX: Pingowanie Google i Bing
	 * ---------------------------------------------------------------------- */
	public function ajax_ping_search_engines() {
		check_ajax_referer( 'atela_seo_ping', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Brak uprawnień.' );
		}

		$options     = get_option( 'atela_seo_options', array() );
		$mode        = $options['sitemap_mode'] ?? 'single';
		$sitemap_url = ( $mode === 'index' )
			? home_url( '/sitemap_index.xml' )
			: home_url( '/sitemap.xml' );

		$encoded = rawurlencode( $sitemap_url );
		$errors  = [];

		$google = wp_remote_get( 'https://www.google.com/ping?sitemap=' . $encoded, array( 'timeout' => 10 ) );
		if ( is_wp_error( $google ) ) {
			$errors[] = 'Google: ' . $google->get_error_message();
		}

		$bing = wp_remote_get( 'https://www.bing.com/ping?sitemap=' . $encoded, array( 'timeout' => 10 ) );
		if ( is_wp_error( $bing ) ) {
			$errors[] = 'Bing: ' . $bing->get_error_message();
		}

		if ( empty( $errors ) ) {
			update_option( 'atela_seo_last_ping', current_time( 'Y-m-d H:i:s' ) );
			wp_send_json_success( 'Google i Bing zostały powiadomione! (' . current_time( 'Y-m-d H:i:s' ) . ')' );
		} else {
			wp_send_json_error( implode( ' | ', $errors ) );
		}
	}
}
