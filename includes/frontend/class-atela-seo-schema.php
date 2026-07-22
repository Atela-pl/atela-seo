<?php
/**
 * Moduł Schema.org JSON-LD.
 *
 * Automatycznie wstrzykuje dane strukturalne JSON-LD do <head> strony:
 * - WebSite        – na stronie głównej
 * - Organization   – na stronie głównej (dane organizacji z ustawień)
 * - WebPage        – na stronach statycznych
 * - Article        – na wpisach blogowych i CPT
 * - BreadcrumbList – jeśli moduł Breadcrumbs jest aktywny (unikamy duplikatu)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Atela_SEO_Schema {

	public function __construct() {
		add_action( 'wp_head', array( $this, 'print_schema' ), 5 );
	}

	/* -------------------------------------------------------------------------
	 * Główna metoda: buduje i wypluwia JSON-LD
	 * ---------------------------------------------------------------------- */
	public function print_schema() {
		$schemas = array();

		if ( is_front_page() ) {
			$schemas[] = $this->get_website_schema();
			$org = $this->get_organization_schema();
			if ( $org ) {
				$schemas[] = $org;
			}
		} elseif ( is_singular( 'post' ) ) {
			$schemas[] = $this->get_article_schema();
		} elseif ( is_singular() ) {
			$schemas[] = $this->get_webpage_schema();
		}

		foreach ( $schemas as $schema ) {
			if ( empty( $schema ) ) {
				continue;
			}
			echo '<script type="application/ld+json">' . "\n";
			echo wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
			echo "\n</script>\n";
		}
	}

	/* -------------------------------------------------------------------------
	 * WebSite schema
	 * ---------------------------------------------------------------------- */
	private function get_website_schema() {
		$schema = array(
			'@context' => 'https://schema.org',
			'@type'    => 'WebSite',
			'name'     => get_bloginfo( 'name' ),
			'url'      => home_url( '/' ),
		);

		// Dodaj SearchAction (Google Sitelinks Search Box)
		$schema['potentialAction'] = array(
			'@type'       => 'SearchAction',
			'target'      => array(
				'@type'       => 'EntryPoint',
				'urlTemplate' => home_url( '/?s={search_term_string}' ),
			),
			'query-input' => 'required name=search_term_string',
		);

		return $schema;
	}

	/* -------------------------------------------------------------------------
	 * Organization schema (dane z ustawień Atela SEO)
	 * ---------------------------------------------------------------------- */
	private function get_organization_schema() {
		$options = get_option( 'atela_seo_options', array() );

		$org_name = $options['schema_org_name'] ?? get_bloginfo( 'name' );
		$org_url  = $options['schema_org_url']  ?? home_url( '/' );
		$org_logo = $options['schema_org_logo'] ?? '';

		$schema = array(
			'@context' => 'https://schema.org',
			'@type'    => 'Organization',
			'name'     => $org_name,
			'url'      => $org_url,
		);

		if ( $org_logo ) {
			$schema['logo'] = array(
				'@type' => 'ImageObject',
				'url'   => $org_logo,
			);
		}

		// Sieci społecznościowe (sameAs)
		$same_as = array();
		$social_fields = array(
			'schema_social_facebook',
			'schema_social_instagram',
			'schema_social_twitter',
			'schema_social_linkedin',
			'schema_social_youtube',
			'schema_social_pinterest',
		);
		foreach ( $social_fields as $field ) {
			if ( ! empty( $options[ $field ] ) ) {
				$same_as[] = esc_url_raw( $options[ $field ] );
			}
		}
		if ( ! empty( $same_as ) ) {
			$schema['sameAs'] = $same_as;
		}

		// Kontakt
		if ( ! empty( $options['schema_org_email'] ) ) {
			$schema['email'] = sanitize_email( $options['schema_org_email'] );
		}
		if ( ! empty( $options['schema_org_phone'] ) ) {
			$schema['telephone'] = sanitize_text_field( $options['schema_org_phone'] );
		}

		return $schema;
	}

	/* -------------------------------------------------------------------------
	 * WebPage schema (strony statyczne)
	 * ---------------------------------------------------------------------- */
	private function get_webpage_schema() {
		global $post;
		if ( ! $post ) {
			return null;
		}

		$options     = get_option( 'atela_seo_options', array() );
		$title       = get_post_meta( $post->ID, '_atela_seo_title', true ) ?: get_the_title( $post->ID );
		$description = get_post_meta( $post->ID, '_atela_seo_description', true ) ?: get_bloginfo( 'description' );

		$schema = array(
			'@context'         => 'https://schema.org',
			'@type'            => 'WebPage',
			'name'             => $title,
			'url'              => get_permalink( $post->ID ),
			'description'      => $description,
			'dateModified'     => get_the_modified_date( 'c', $post->ID ),
			'datePublished'    => get_the_date( 'c', $post->ID ),
			'inLanguage'       => get_bloginfo( 'language' ),
			'isPartOf'         => array(
				'@type' => 'WebSite',
				'url'   => home_url( '/' ),
			),
		);

		return $schema;
	}

	/* -------------------------------------------------------------------------
	 * Article schema (wpisy blogowe)
	 * ---------------------------------------------------------------------- */
	private function get_article_schema() {
		global $post;
		if ( ! $post ) {
			return null;
		}

		$options     = get_option( 'atela_seo_options', array() );
		$title       = get_post_meta( $post->ID, '_atela_seo_title', true ) ?: get_the_title( $post->ID );
		$description = get_post_meta( $post->ID, '_atela_seo_description', true ) ?: wp_trim_words( strip_shortcodes( $post->post_content ), 30, '...' );
		$author      = get_the_author_meta( 'display_name', $post->post_author );

		$schema = array(
			'@context'         => 'https://schema.org',
			'@type'            => 'Article',
			'headline'         => $title,
			'url'              => get_permalink( $post->ID ),
			'description'      => $description,
			'datePublished'    => get_the_date( 'c', $post->ID ),
			'dateModified'     => get_the_modified_date( 'c', $post->ID ),
			'inLanguage'       => get_bloginfo( 'language' ),
			'author'           => array(
				'@type' => 'Person',
				'name'  => $author,
			),
			'publisher'        => array(
				'@type' => 'Organization',
				'name'  => $options['schema_org_name'] ?? get_bloginfo( 'name' ),
				'url'   => home_url( '/' ),
			),
			'mainEntityOfPage' => array(
				'@type' => 'WebPage',
				'@id'   => get_permalink( $post->ID ),
			),
		);

		// Logo publishera
		if ( ! empty( $options['schema_org_logo'] ) ) {
			$schema['publisher']['logo'] = array(
				'@type' => 'ImageObject',
				'url'   => $options['schema_org_logo'],
			);
		}

		// Obrazek wyróżniony
		$thumb_id = get_post_thumbnail_id( $post->ID );
		if ( $thumb_id ) {
			$img = wp_get_attachment_image_src( $thumb_id, 'full' );
			if ( $img ) {
				$schema['image'] = array(
					'@type'  => 'ImageObject',
					'url'    => $img[0],
					'width'  => $img[1],
					'height' => $img[2],
				);
			}
		}

		// Kategorie jako keywords
		$categories = get_the_category( $post->ID );
		if ( ! empty( $categories ) ) {
			$schema['keywords'] = implode( ', ', wp_list_pluck( $categories, 'name' ) );
		}

		return $schema;
	}
}
