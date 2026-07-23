<?php
/**
 * Główna klasa zarządzająca cyklem życia wtyczki.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Atela_SEO_Core {

	public function __construct() {
		$this->load_dependencies();
	}

	private function load_dependencies() {
		// Logika dla panelu admina (meta boxy w standardowym edytorze)
		if ( is_admin() ) {
			new Atela_SEO_Admin();
		}

		// Moduł SEO dla taksonomii (admin + frontend)
		new Atela_SEO_Taxonomy();

		// Menedżer Przekierowań 301/302
		new Atela_SEO_Redirects();

		// Logika dla frontendu (renderowanie tagów meta)
		new Atela_SEO_Frontend();

		// Moduł Social Media (Open Graph, Twitter Cards)
		new Atela_SEO_Social();

		// Moduł Okruszków (Breadcrumbs)
		new Atela_SEO_Breadcrumbs();

		// Moduł XML Sitemap
		new Atela_SEO_Sitemap();

		// Moduł Schema.org JSON-LD
		new Atela_SEO_Schema();

		// Integracja z Elementorem
		add_action( 'elementor/init', array( $this, 'init_elementor' ) );
	}

	public function init_elementor() {
		new Atela_SEO_Elementor();
	}

	public function run() {
		// Uruchomienie akcji wtyczki
	}

	/**
	 * Zwraca URL obrazu przyciętego/z letterboxingiem do 1200x630.
	 */
	public static function get_letterboxed_image_url( $attachment_id ) {
		if ( ! $attachment_id ) {
			return '';
		}
		$upload_dir = wp_upload_dir();
		$meta       = wp_get_attachment_metadata( $attachment_id );
		
		if ( empty( $meta['file'] ) ) {
			return wp_get_attachment_url( $attachment_id );
		}

		$original_path = $upload_dir['basedir'] . '/' . $meta['file'];
		if ( ! file_exists( $original_path ) ) {
			return wp_get_attachment_url( $attachment_id );
		}

		$path_parts      = pathinfo( $original_path );
		$padded_filename = $path_parts['filename'] . '-1200x630-aseo.' . $path_parts['extension'];
		$padded_path     = $path_parts['dirname'] . '/' . $padded_filename;
		$original_url    = wp_get_attachment_url( $attachment_id );
		$padded_url      = dirname( $original_url ) . '/' . $padded_filename;

		if ( file_exists( $padded_path ) ) {
			return $padded_url;
		}

		$editor = wp_get_image_editor( $original_path );
		if ( is_wp_error( $editor ) ) {
			return $original_url;
		}

		$target_w = 1200;
		$target_h = 630;
		
		$editor->resize( $target_w, $target_h, false );
		$fg_size = $editor->get_size();

		if ( $fg_size['width'] == $target_w && $fg_size['height'] == $target_h ) {
			return $original_url;
		}

		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			return $original_url;
		}

		$fg_path = $padded_path . '.tmp';
		$editor->save( $fg_path );

		$ext = strtolower( $path_parts['extension'] );
		if ( $ext == 'png' ) {
			$fg_img = @imagecreatefrompng( $fg_path );
		} elseif ( $ext == 'gif' ) {
			$fg_img = @imagecreatefromgif( $fg_path );
		} else {
			$fg_img = @imagecreatefromjpeg( $fg_path );
		}

		if ( ! $fg_img ) {
			wp_delete_file( $fg_path );
			return $original_url;
		}

		$canvas = imagecreatetruecolor( $target_w, $target_h );
		$bg_color = imagecolorallocate( $canvas, 255, 255, 255 );
		imagefill( $canvas, 0, 0, $bg_color );

		$dst_x = ( $target_w - $fg_size['width'] ) / 2;
		$dst_y = ( $target_h - $fg_size['height'] ) / 2;

		imagealphablending( $canvas, true );
		imagesavealpha( $canvas, true );

		imagecopy( $canvas, $fg_img, $dst_x, $dst_y, 0, 0, $fg_size['width'], $fg_size['height'] );

		if ( $ext == 'png' ) {
			imagepng( $canvas, $padded_path );
		} elseif ( $ext == 'gif' ) {
			imagegif( $canvas, $padded_path );
		} else {
			imagejpeg( $canvas, $padded_path, 90 );
		}

		imagedestroy( $fg_img );
		imagedestroy( $canvas );
		wp_delete_file( $fg_path );

		return $padded_url;
	}
}
