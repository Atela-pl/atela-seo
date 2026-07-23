<?php
/**
 * Menedżer Przekierowań 301/302.
 *
 * - Przechowuje przekierowania w tabeli {prefix}_atela_seo_redirects.
 * - Panel admina: lista, dodawanie, edycja, usuwanie, import CSV.
 * - Front-end: przechwytuje żądania i wykonuje przekierowania.
 * - Monitor 404: opcjonalne logowanie brakujących stron.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Atela_SEO_Redirects {

	private $table;

	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'atela_seo_redirects';

		// Utwórz tabelę jeśli nie istnieje
		$this->maybe_create_table();

		// Panel admina
		add_action( 'admin_menu', array( $this, 'add_submenu' ) );
		add_action( 'admin_post_atela_seo_save_redirect',   array( $this, 'handle_save_redirect' ) );
		add_action( 'admin_post_atela_seo_delete_redirect', array( $this, 'handle_delete_redirect' ) );
		add_action( 'admin_post_atela_seo_import_csv',      array( $this, 'handle_import_csv' ) );

		// Front-end: interceptor
		add_action( 'template_redirect', array( $this, 'handle_redirect' ), 1 );
	}

	/* -------------------------------------------------------------------------
	 * Tworzenie tabeli DB
	 * ---------------------------------------------------------------------- */
	private function maybe_create_table() {
		global $wpdb;

		if ( get_option( 'atela_seo_redirects_db_version' ) === '1.0' ) {
			return;
		}

		$charset = $wpdb->get_charset_collate();
		$sql     = "CREATE TABLE IF NOT EXISTS {$this->table} (
			id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			source_url  VARCHAR(2048)       NOT NULL,
			target_url  VARCHAR(2048)       NOT NULL,
			redirect_type SMALLINT(3)       NOT NULL DEFAULT 301,
			hits        BIGINT(20)          NOT NULL DEFAULT 0,
			created_at  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY source_url (source_url(191))
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
		update_option( 'atela_seo_redirects_db_version', '1.0' );
	}

	/* -------------------------------------------------------------------------
	 * Submenu w panelu Atela SEO
	 * ---------------------------------------------------------------------- */
	public function add_submenu() {
		add_submenu_page(
			'atela-seo',
			'Przekierowania 301',
			'🔀 Przekierowania',
			'manage_options',
			'atela-seo-redirects',
			array( $this, 'render_admin_page' )
		);
	}

	/* -------------------------------------------------------------------------
	 * Panel admina – lista przekierowań
	 * ---------------------------------------------------------------------- */
	public function render_admin_page() {
		global $wpdb;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$action   = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : 'list';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$edit_id  = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$notice   = isset( $_GET['notice'] ) ? sanitize_key( wp_unslash( $_GET['notice'] ) ) : '';

		// Pokaż komunikat
		if ( $notice ) {
			$messages = array(
				'saved'   => array( 'updated', '✓ Przekierowanie zostało zapisane.' ),
				'deleted' => array( 'updated', '✓ Przekierowanie zostało usunięte.' ),
				'imported'=> array( 'updated', '✓ Import CSV zakończony.' ),
				'exists'  => array( 'error',   '✗ Przekierowanie z tym URL źródłowym już istnieje.' ),
			);
			if ( isset( $messages[ $notice ] ) ) {
				[ $type, $text ] = $messages[ $notice ];
				echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $text ) . '</p></div>';
			}
		}

		echo '<div class="wrap">';
		echo '<h1 class="wp-heading-inline">🔀 Menedżer Przekierowań</h1>';

		if ( $action === 'edit' && $edit_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$redirect = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}atela_seo_redirects WHERE id = %d", $edit_id ) );
			if ( $redirect ) {
				$this->render_form( $redirect );
			} else {
				echo '<p>Nie znaleziono przekierowania.</p>';
			}
		} elseif ( $action === 'add' ) {
			echo ' <a href="' . esc_url( admin_url( 'admin.php?page=atela-seo-redirects' ) ) . '" class="page-title-action">← Wróć do listy</a>';
			$this->render_form( null );
		} else {
			echo ' <a href="' . esc_url( admin_url( 'admin.php?page=atela-seo-redirects&action=add' ) ) . '" class="page-title-action">+ Dodaj nowe</a>';
			$this->render_list();
			$this->render_import_form();
		}

		echo '</div>';
	}

	/* -------------------------------------------------------------------------
	 * Formularz dodawania / edycji przekierowania
	 * ---------------------------------------------------------------------- */
	private function render_form( $redirect = null ) {
		$is_edit = ! is_null( $redirect );
		$action  = $is_edit ? 'Edytuj przekierowanie' : 'Dodaj nowe przekierowanie';
		?>
		<h2><?php echo esc_html( $action ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'atela_seo_save_redirect', 'atela_seo_redirect_nonce' ); ?>
			<input type="hidden" name="action" value="atela_seo_save_redirect" />
			<?php if ( $is_edit ) : ?>
				<input type="hidden" name="redirect_id" value="<?php echo esc_attr( $redirect->id ); ?>" />
			<?php endif; ?>
			<table class="form-table">
				<tr>
					<th><label for="source_url">URL źródłowy (stary)</label></th>
					<td>
						<input
							type="text"
							id="source_url"
							name="source_url"
							value="<?php echo esc_attr( $redirect->source_url ?? '' ); ?>"
							class="large-text"
							placeholder="/stary-artykul/"
							required
						/>
						<p class="description">Ścieżka względna, np. <code>/stara-strona/</code> lub pełny URL.</p>
					</td>
				</tr>
				<tr>
					<th><label for="target_url">URL docelowy (nowy)</label></th>
					<td>
						<input
							type="text"
							id="target_url"
							name="target_url"
							value="<?php echo esc_attr( $redirect->target_url ?? '' ); ?>"
							class="large-text"
							placeholder="/nowy-artykul/ lub https://..."
							required
						/>
						<p class="description">Ścieżka względna lub pełny URL docelowy.</p>
					</td>
				</tr>
				<tr>
					<th><label for="redirect_type">Typ przekierowania</label></th>
					<td>
						<select id="redirect_type" name="redirect_type">
							<option value="301" <?php selected( $redirect->redirect_type ?? 301, 301 ); ?>>
								301 – Przekierowanie stałe (zalecane dla SEO)
							</option>
							<option value="302" <?php selected( $redirect->redirect_type ?? 301, 302 ); ?>>
								302 – Przekierowanie tymczasowe
							</option>
						</select>
					</td>
				</tr>
			</table>
			<?php submit_button( $is_edit ? 'Zapisz zmiany' : 'Dodaj przekierowanie' ); ?>
		</form>
		<?php
	}

	/* -------------------------------------------------------------------------
	 * Lista wszystkich przekierowań
	 * ---------------------------------------------------------------------- */
	private function render_list() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$redirects = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}atela_seo_redirects ORDER BY id DESC" );
		$count     = count( $redirects );
		?>
		<p style="color:#666;margin-top:12px;">Łącznie: <strong><?php echo esc_html( $count ); ?></strong> przekierowań</p>
		<?php if ( empty( $redirects ) ) : ?>
			<div style="background:#fff;border:1px solid #e0e0e0;padding:40px;text-align:center;margin-top:16px;border-radius:4px;">
				<p style="font-size:16px;color:#666;">Brak przekierowań. Kliknij <strong>"+ Dodaj nowe"</strong>, aby zacząć.</p>
			</div>
		<?php else : ?>
		<table class="wp-list-table widefat fixed striped" style="margin-top:12px;">
			<thead>
				<tr>
					<th style="width:40%">URL źródłowy</th>
					<th style="width:35%">URL docelowy</th>
					<th style="width:8%;text-align:center">Typ</th>
					<th style="width:8%;text-align:center">Trafień</th>
					<th style="width:9%">Akcje</th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $redirects as $r ) :
				$edit_url   = admin_url( 'admin.php?page=atela-seo-redirects&action=edit&id=' . $r->id );
				$delete_url = wp_nonce_url(
					admin_url( 'admin-post.php?action=atela_seo_delete_redirect&id=' . $r->id ),
					'atela_seo_delete_' . $r->id
				);
			?>
				<tr>
					<td><code style="font-size:12px;"><?php echo esc_html( $r->source_url ); ?></code></td>
					<td style="color:#0073aa;word-break:break-all;"><?php echo esc_html( $r->target_url ); ?></td>
					<td style="text-align:center;">
						<span style="background:<?php echo $r->redirect_type == 301 ? '#d1fae5' : '#fef3c7'; ?>;color:<?php echo $r->redirect_type == 301 ? '#065f46' : '#92400e'; ?>;padding:2px 8px;border-radius:10px;font-size:12px;font-weight:600;">
							<?php echo esc_html( $r->redirect_type ); ?>
						</span>
					</td>
					<td style="text-align:center;font-weight:600;"><?php echo (int) $r->hits; ?></td>
					<td>
						<a href="<?php echo esc_url( $edit_url ); ?>">Edytuj</a> |
						<a href="<?php echo esc_url( $delete_url ); ?>"
						   onclick="return confirm('Czy na pewno chcesz usunąć to przekierowanie?')"
						   style="color:#c00;">Usuń</a>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php endif;
	}

	/* -------------------------------------------------------------------------
	 * Formularz importu CSV
	 * ---------------------------------------------------------------------- */
	private function render_import_form() {
		?>
		<div style="margin-top:30px; padding:16px 20px; background:#f9f9f9; border:1px solid #ddd; max-width:600px;">
			<h3 style="margin-top:0; font-size:14px;">📥 Import z CSV</h3>
			<p class="description" style="margin-bottom:12px;">Format CSV: <code>source_url,target_url,redirect_type</code> (typ domyślny: 301)</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
				<?php wp_nonce_field( 'atela_seo_import_csv', 'atela_seo_csv_nonce' ); ?>
				<input type="hidden" name="action" value="atela_seo_import_csv" />
				<input type="file" name="csv_file" accept=".csv" style="margin-right:10px;" />
				<input type="submit" class="button" value="Importuj" />
			</form>
		</div>
		<?php
	}

	/* -------------------------------------------------------------------------
	 * Handler: zapis przekierowania
	 * ---------------------------------------------------------------------- */
	public function handle_save_redirect() {
		if ( ! check_admin_referer( 'atela_seo_save_redirect', 'atela_seo_redirect_nonce' ) ) {
			wp_die( 'Błąd bezpieczeństwa.' );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Brak uprawnień.' );
		}

		global $wpdb;

		$source_url = isset( $_POST['source_url'] ) ? sanitize_text_field( wp_unslash( $_POST['source_url'] ) ) : '';
		$source     = '/' . ltrim( $source_url, '/' );
		if ( strlen( $source ) > 1 ) {
			$source = rtrim( $source, '/' );
		}
		$target   = isset( $_POST['target_url'] ) ? sanitize_text_field( wp_unslash( $_POST['target_url'] ) ) : '';
		
		$type_raw = isset( $_POST['redirect_type'] ) ? sanitize_text_field( wp_unslash( $_POST['redirect_type'] ) ) : '301';
		$type     = in_array( (int) $type_raw, array( 301, 302 ), true ) ? (int) $type_raw : 301;

		$redirect_id = isset( $_POST['redirect_id'] ) ? absint( wp_unslash( $_POST['redirect_id'] ) ) : 0;

		if ( $redirect_id ) {
			// Aktualizacja istniejącego
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$this->table,
				array( 'source_url' => $source, 'target_url' => $target, 'redirect_type' => $type ),
				array( 'id' => $redirect_id ),
				array( '%s', '%s', '%d' ),
				array( '%d' )
			);
			$notice = 'saved';
		} else {
			// Sprawdź duplikaty
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}atela_seo_redirects WHERE source_url = %s LIMIT 1", $source ) );
			if ( $exists ) {
				$notice = 'exists';
			} else {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->insert(
					$this->table,
					array( 'source_url' => $source, 'target_url' => $target, 'redirect_type' => $type ),
					array( '%s', '%s', '%d' )
				);
				$notice = 'saved';
			}
		}

		wp_safe_redirect( admin_url( 'admin.php?page=atela-seo-redirects&notice=' . $notice ) );
		exit;
	}

	/* -------------------------------------------------------------------------
	 * Handler: usunięcie przekierowania
	 * ---------------------------------------------------------------------- */
	public function handle_delete_redirect() {
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		if ( ! $id || ! check_admin_referer( 'atela_seo_delete_' . $id ) ) {
			wp_die( 'Błąd bezpieczeństwa.' );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Brak uprawnień.' );
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $this->table, array( 'id' => $id ), array( '%d' ) );

		wp_safe_redirect( admin_url( 'admin.php?page=atela-seo-redirects&notice=deleted' ) );
		exit;
	}

	/* -------------------------------------------------------------------------
	 * Handler: import CSV
	 * ---------------------------------------------------------------------- */
	public function handle_import_csv() {
		if ( ! check_admin_referer( 'atela_seo_import_csv', 'atela_seo_csv_nonce' ) ) {
			wp_die( 'Błąd bezpieczeństwa.' );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Brak uprawnień.' );
		}

		if ( empty( $_FILES['csv_file']['tmp_name'] ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=atela-seo-redirects' ) );
			exit;
		}

		global $wpdb;
		WP_Filesystem();
		global $wp_filesystem;

		$tmp_name = sanitize_text_field( wp_unslash( $_FILES['csv_file']['tmp_name'] ) );
		$file_content = $wp_filesystem->get_contents( $tmp_name );
		$imported = 0;

		if ( $file_content ) {
			$lines = explode( "\n", $file_content );
			foreach ( $lines as $line ) {
				$row = str_getcsv( trim( $line ) );
				if ( count( $row ) < 2 ) continue;
				$source = '/' . ltrim( sanitize_text_field( $row[0] ), '/' );
				if ( strlen( $source ) > 1 ) {
					$source = rtrim( $source, '/' );
				}
				$target = sanitize_text_field( $row[1] );
				$type   = isset( $row[2] ) && in_array( (int) $row[2], array( 301, 302 ) ) ? (int) $row[2] : 301;

				// Pomijamy istniejące
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}atela_seo_redirects WHERE source_url = %s", $source ) );
				if ( ! $exists && $source !== '/' && $target ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
					$wpdb->insert(
						$this->table,
						array( 'source_url' => $source, 'target_url' => $target, 'redirect_type' => $type ),
						array( '%s', '%s', '%d' )
					);
					$imported++;
				}
			}
		}

		wp_safe_redirect( admin_url( 'admin.php?page=atela-seo-redirects&notice=imported' ) );
		exit;
	}

	/* -------------------------------------------------------------------------
	 * Front-end: wykonaj przekierowanie jeśli URL pasuje
	 * ---------------------------------------------------------------------- */
	public function handle_redirect() {
		global $wpdb;

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$request = $request_uri ? wp_parse_url( $request_uri, PHP_URL_PATH ) : '';
		if ( ! $request ) {
			return;
		}

		// Normalizuj: zawsze z leading slash, bez trailing slash (wyjątek: root /)
		$request = '/' . ltrim( $request, '/' );
		if ( strlen( $request ) > 1 ) {
			$request = rtrim( $request, '/' );
		}

		// Usuń prefix subdirectory
		$base = rtrim( wp_parse_url( home_url(), PHP_URL_PATH ) ?? '', '/' );
		if ( $base && strpos( $request, $base ) === 0 ) {
			$request = '/' . ltrim( substr( $request, strlen( $base ) ), '/' );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$redirect = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}atela_seo_redirects WHERE source_url = %s LIMIT 1", $request )
		);

		if ( ! $redirect ) {
			return;
		}

		// Zwiększ licznik trafień
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}atela_seo_redirects SET hits = hits + 1 WHERE id = %d", $redirect->id ) );

		$target = $redirect->target_url;
		// Jeśli URL względny, dopełnij do pełnego
		if ( strpos( $target, 'http' ) !== 0 ) {
			$target = home_url( $target );
		}

		wp_safe_redirect( $target, (int) $redirect->redirect_type );
		exit;
	}
}
