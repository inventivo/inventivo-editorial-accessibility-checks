<?php
/**
 * Backend-Einstiegspunkte und Formulare.
 *
 * @package Editorial_Accessibility_Checks
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Editorial_Accessibility_Checks_Admin {
	public static function boot() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'styles' ) );
		add_action( 'admin_post_editorial_accessibility_checks_scan_now', array( __CLASS__, 'scan_now' ) );
	}

	public static function menu() {
		if ( current_user_can( 'edit_others_posts' ) ) {
			add_management_page( __( 'Editorial Accessibility Checks', 'inventivo-editorial-accessibility-checks' ), __( 'Accessibility Checks', 'inventivo-editorial-accessibility-checks' ), 'edit_others_posts', 'editorial-accessibility-checks', array( __CLASS__, 'render' ) );
		}
	}

	public static function styles( $hook ) {
		if ( 'tools_page_editorial-accessibility-checks' === $hook && current_user_can( 'edit_others_posts' ) ) {
			wp_enqueue_style( 'editorial_accessibility_checks_admin', plugins_url( '../assets/admin.css', __FILE__ ), array(), '1.0.1' );
		}
	}

	public static function scan_now() {
		self::authorize();
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
			wp_die( esc_html__( 'Please use the Scan now button.', 'inventivo-editorial-accessibility-checks' ), '', array( 'response' => 405 ) );
		}
		check_admin_referer( 'editorial_accessibility_checks_scan_now' );
		$result = Editorial_Accessibility_Checks_Plugin::batch();
		$args = array( 'page' => 'editorial-accessibility-checks', 'editorial_accessibility_checks_notice' => is_wp_error( $result ) ? $result->get_error_code() : 'scanned', 'editorial_accessibility_checks_count' => is_wp_error( $result ) ? 0 : $result );
		$args['editorial_accessibility_checks_notice_nonce'] = wp_create_nonce( 'editorial_accessibility_checks_notice' );
		$url = add_query_arg( $args, admin_url( 'tools.php' ) );
		wp_safe_redirect( $url );
		exit;
	}

	private static function authorize() {
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			wp_die( esc_html__( 'You cannot view these checks.', 'inventivo-editorial-accessibility-checks' ), '', array( 'response' => 403 ) );
		}
	}

	/**
	 * Liest einen Filterwert aus der Adresszeile.
	 *
	 * Rein lesend. Der Wert wird hier bereinigt und beim Aufrufer gegen eine feste Liste
	 * erlaubter Werte geprüft. Es wird nichts geschrieben und nichts ausgelöst, deshalb
	 * braucht die Listenansicht keine Nonce, genauso wie die Listenansichten im Kern.
	 *
	 * @param string $key Name des Parameters.
	 * @return string
	 */
	private static function input( $key ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list filter, sanitized here and checked against a whitelist by the caller. Nothing is written.
		return isset( $_GET[ $key ] ) && is_string( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : '';
	}

	public static function render() {
		self::authorize();
		require_once __DIR__ . '/class-editorial-accessibility-checks-list-table.php';
		if ( isset( $_GET['filter_action'] ) || isset( $_GET['search_submit'] ) || isset( $_GET['editorial_accessibility_checks_filter_nonce'] ) ) {
			check_admin_referer( 'editorial_accessibility_checks_filter', 'editorial_accessibility_checks_filter_nonce' );
		}
		$labels = Editorial_Accessibility_Checks_Rules::labels();
		$types  = Editorial_Accessibility_Checks_Plugin::post_types();
		$rule   = self::input( 'editorial_accessibility_checks_rule' );
		$type   = self::input( 'editorial_accessibility_checks_type' );
		$filters = array(
			'rule' => isset( $labels[ $rule ] ) ? $rule : '',
			'type' => in_array( $type, $types, true ) ? $type : '',
			'search' => self::input( 's' ),
			'orderby' => sanitize_key( self::input( 'orderby' ) ),
			'order' => 'ASC' === strtoupper( self::input( 'order' ) ) ? 'ASC' : 'DESC',
			'paged' => max( 1, absint( self::input( 'paged' ) ) ),
		);
		$table = new Editorial_Accessibility_Checks_List_Table( $filters );
		$table->prepare_items();
		echo '<div class="wrap editorial-accessibility-checks"><h1>' . esc_html__( 'Editorial Accessibility Checks', 'inventivo-editorial-accessibility-checks' ) . '</h1>';
		self::notice();
		if ( ! class_exists( 'DOMDocument' ) ) {
			wp_admin_notice( esc_html__( 'The PHP DOM extension is required to run these checks. Ask your hosting provider to enable the PHP extensions dom and xml, then start the scan again.', 'inventivo-editorial-accessibility-checks' ), array( 'type' => 'error', 'attributes' => array( 'role' => 'alert' ), 'dismissible' => true ) );
		}
		echo '<p>' . esc_html__( 'Automated checks can only detect a part of the accessibility issues on a page. Testing by a person, including with a screen reader and by keyboard, remains necessary.', 'inventivo-editorial-accessibility-checks' ) . '</p>';
		echo '<p>' . esc_html__( 'These checks read the saved editor content of your posts and pages. Content added by your theme, by page builders, by shortcodes or by scripts is not read.', 'inventivo-editorial-accessibility-checks' ) . '</p>';
		echo '<p>' . esc_html__( 'The checks are based on the WCAG 2.2 success criteria. The plugin reports what it finds. It does not change your content and it does not change your website.', 'inventivo-editorial-accessibility-checks' ) . '</p>';
		echo '<p>' . esc_html__( 'No overlay. No widget on your site. No automatic fixes. This plugin only reads and reports.', 'inventivo-editorial-accessibility-checks' ) . '</p>';
		echo '<p>' . esc_html__( 'Results are stored in your own database. Nothing is sent to us or to any third party.', 'inventivo-editorial-accessibility-checks' ) . '</p>';
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Asks for a single ID to find out whether any scan has run at all. Limited to one row and no row count.
		$checked = $types ? new WP_Query( array( 'post_type' => $types, 'post_status' => 'publish', 'posts_per_page' => 1, 'fields' => 'ids', 'no_found_rows' => true, 'meta_key' => Editorial_Accessibility_Checks_Plugin::TIME_META ) ) : null;
		if ( ! $checked || ! $checked->posts ) {
			echo '<p>' . esc_html__( 'No pages have been checked yet. The first scan runs automatically, or you can start it now.', 'inventivo-editorial-accessibility-checks' ) . '</p>';
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="editorial_accessibility_checks_scan_now">';
		wp_nonce_field( 'editorial_accessibility_checks_scan_now' );
		submit_button( __( 'Scan now', 'inventivo-editorial-accessibility-checks' ), 'secondary' );
		echo '</form><form method="get" action="' . esc_url( admin_url( 'tools.php' ) ) . '"><input type="hidden" name="page" value="editorial-accessibility-checks">';
		wp_nonce_field( 'editorial_accessibility_checks_filter', 'editorial_accessibility_checks_filter_nonce', false );
		echo '<div class="editorial-accessibility-checks-filters"><label for="editorial-accessibility-checks-rule">' . esc_html__( 'Rule', 'inventivo-editorial-accessibility-checks' ) . '</label> <select id="editorial-accessibility-checks-rule" name="editorial_accessibility_checks_rule"><option value="">' . esc_html__( 'All rules', 'inventivo-editorial-accessibility-checks' ) . '</option>';
		foreach ( $labels as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '" ' . selected( $filters['rule'], $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select> <label for="editorial-accessibility-checks-type">' . esc_html__( 'Content type', 'inventivo-editorial-accessibility-checks' ) . '</label> <select id="editorial-accessibility-checks-type" name="editorial_accessibility_checks_type"><option value="">' . esc_html__( 'All content types', 'inventivo-editorial-accessibility-checks' ) . '</option>';
		foreach ( $types as $key ) {
			$object = get_post_type_object( $key );
			echo '<option value="' . esc_attr( $key ) . '" ' . selected( $filters['type'], $key, false ) . '>' . esc_html( $object->labels->singular_name ) . '</option>';
		}
		echo '</select> ';
		submit_button( __( 'Filter', 'inventivo-editorial-accessibility-checks' ), 'secondary', 'filter_action', false );
		echo '</div><p><label for="editorial-accessibility-checks-search">' . esc_html__( 'Search titles', 'inventivo-editorial-accessibility-checks' ) . '</label> <input type="search" id="editorial-accessibility-checks-search" name="s" value="' . esc_attr( $filters['search'] ) . '"> ';
		submit_button( __( 'Search titles', 'inventivo-editorial-accessibility-checks' ), 'secondary', 'search_submit', false );
		echo '</p>';
		$table->display();
		echo '</form>';
		// Plain footer text on this screen only, not an admin notice.
		echo '<p class="editorial-accessibility-checks-legal">' . esc_html__( 'This plugin does not determine, certify or guarantee conformance with WCAG, the European Accessibility Act, the German BFSG, the ADA or any other standard or law. Whether your website meets legal requirements is a question only you and your advisers can answer.', 'inventivo-editorial-accessibility-checks' ) . '</p>';
		echo '</div>';
	}

	private static function notice() {
		$nonce = self::input( 'editorial_accessibility_checks_notice_nonce' );
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'editorial_accessibility_checks_notice' ) ) {
			return;
		}
		$code = self::input( 'editorial_accessibility_checks_notice' );
		if ( 'scanned' === $code ) {
			/* translators: %d: Number of posts checked in this batch. */
			$message = sprintf( __( 'This batch checked %d posts. Further batches run automatically.', 'inventivo-editorial-accessibility-checks' ), absint( self::input( 'editorial_accessibility_checks_count' ) ) );
		} elseif ( 'editorial_accessibility_checks_busy' === $code ) {
			$message = __( 'A scan batch is already running. Please try again later.', 'inventivo-editorial-accessibility-checks' );
		} elseif ( 'editorial_accessibility_checks_dom_missing' === $code ) {
			$message = __( 'The PHP DOM extension is required to run these checks. Ask your hosting provider to enable the PHP extensions dom and xml, then start the scan again.', 'inventivo-editorial-accessibility-checks' );
		} elseif ( 'editorial_accessibility_checks_save_failed' === $code ) {
			$message = __( 'The scan could not save its results. Please try again later. If this keeps happening, check the PHP error log of your site.', 'inventivo-editorial-accessibility-checks' );
		} else {
			$message = __( 'The scan could not read the posts. Please try again later. If this keeps happening, check the PHP error log of your site.', 'inventivo-editorial-accessibility-checks' );
		}
		wp_admin_notice( esc_html( $message ), array( 'type' => 'info', 'attributes' => array( 'role' => 'status' ), 'dismissible' => true ) );
	}
}
