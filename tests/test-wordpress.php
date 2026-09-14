<?php
/** Ausführung ausschließlich mit wp eval-file in einer lokalen Testinstallation. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit; }
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/wp-admin/tools.php?page=editorial-accessibility-checks';
require_once dirname( __DIR__ ) . '/editorial-accessibility-checks.php';
require_once dirname( __DIR__ ) . '/includes/class-editorial-accessibility-checks-admin.php';
require_once ABSPATH . 'wp-admin/includes/screen.php';
require_once ABSPATH . 'wp-admin/includes/template.php';
set_current_screen( 'tools_page_editorial-accessibility-checks' );
require_once dirname( __DIR__ ) . '/includes/class-editorial-accessibility-checks-list-table.php';

function editorial_accessibility_checks_test_assert( $condition, $name ) {
	if ( ! $condition ) { throw new RuntimeException( 'FAIL ' . $name ); }
	echo 'PASS ' . $name . "\n";
}
$editorial_accessibility_checks_ids = array();
$editorial_accessibility_checks_old_user = get_current_user_id();
$editorial_accessibility_checks_admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
if ( ! $editorial_accessibility_checks_admins ) { throw new RuntimeException( 'Keine Testadministration vorhanden.' ); }
wp_set_current_user( $editorial_accessibility_checks_admins[0] );
register_post_type( 'eac_test_fixture', array( 'public' => true, 'label' => 'Test fixture' ) );
add_filter( 'editorial_accessibility_checks_post_types', function() { return array( 'eac_test_fixture' ); } );
$editorial_accessibility_checks_options = array();
foreach ( array( Editorial_Accessibility_Checks_Plugin::CURSOR, Editorial_Accessibility_Checks_Plugin::LOCK ) as $key ) {
	$editorial_accessibility_checks_options[ $key ] = get_option( $key, null );
	delete_option( $key );
}
try {
	remove_action( 'save_post', array( 'Editorial_Accessibility_Checks_Plugin', 'saved' ), 10 );
	for ( $i = 1; $i <= 45; ++$i ) {
		$id = wp_insert_post( array( 'post_type' => 'eac_test_fixture', 'post_status' => 'publish', 'post_title' => 'EAC fixture ' . str_pad( $i, 2, '0', STR_PAD_LEFT ), 'post_content' => str_repeat( '<img src="fixture.jpg">', $i ), 'post_author' => get_current_user_id() ), true );
		if ( is_wp_error( $id ) ) { throw new RuntimeException( 'Testbeitrag konnte nicht angelegt werden.' ); }
		$editorial_accessibility_checks_ids[] = $id;
	}
	editorial_accessibility_checks_test_assert( 20 === Editorial_Accessibility_Checks_Plugin::batch(), 'Erstes Paket: 20 Beiträge' );
	editorial_accessibility_checks_test_assert( (int) get_option( Editorial_Accessibility_Checks_Plugin::CURSOR ) === 20, 'Offset nach Paket 1' );
	editorial_accessibility_checks_test_assert( '' === get_post_meta( $editorial_accessibility_checks_ids[20], Editorial_Accessibility_Checks_Plugin::META, true ), 'Paketgrenze hält' );
	editorial_accessibility_checks_test_assert( 20 === Editorial_Accessibility_Checks_Plugin::batch(), 'Zweites Paket: 20 Beiträge' );
	editorial_accessibility_checks_test_assert( 5 === Editorial_Accessibility_Checks_Plugin::batch() && 0 === (int) get_option( Editorial_Accessibility_Checks_Plugin::CURSOR ), 'Drittes Paket: 5 Beiträge, Cursor zurückgesetzt' );
	$filters = array( 'rule' => 'missing_alt', 'type' => '', 'search' => '', 'orderby' => 'total', 'order' => 'DESC', 'paged' => 1 );
	foreach ( $editorial_accessibility_checks_ids as $index => $id ) {
		$data = get_post_meta( $id, Editorial_Accessibility_Checks_Plugin::META, true );
		foreach ( $data['counts'] as $key => $value ) {
			editorial_accessibility_checks_test_assert( (string) $value === get_post_meta( $id, Editorial_Accessibility_Checks_Plugin::COUNT_PREFIX . $key, true ), 'Regelzähler ' . $index . ': ' . $key );
		}
		editorial_accessibility_checks_test_assert( (string) array_sum( $data['counts'] ) === get_post_meta( $id, Editorial_Accessibility_Checks_Plugin::COUNT_PREFIX . 'total', true ), 'Gesamtzähler ' . $index );
	}
	// Die Listenabfrage darf das serialisierte Sammelfeld nicht mehr benötigen.
	delete_post_meta( $editorial_accessibility_checks_ids[44], Editorial_Accessibility_Checks_Plugin::META );
	$table = new Editorial_Accessibility_Checks_List_Table( $filters );
	$table->prepare_items();
	editorial_accessibility_checks_test_assert( 20 === count( $table->items ) && $table->items[0]->ID === $editorial_accessibility_checks_ids[44], 'SQL: numerische Gesamtsortierung, 20 Zeilen' );
	$filters['paged'] = 3;
	$table = new Editorial_Accessibility_Checks_List_Table( $filters );
	$table->prepare_items();
	editorial_accessibility_checks_test_assert( 5 === count( $table->items ), 'SQL: dritte Ergebnisseite' );
	$filters['paged'] = 1; $filters['search'] = 'fixture 12';
	$table = new Editorial_Accessibility_Checks_List_Table( $filters ); $table->prepare_items();
	editorial_accessibility_checks_test_assert( 1 === count( $table->items ) && $table->items[0]->ID === $editorial_accessibility_checks_ids[11], 'SQL: Titelsuche' );
	$filters['search'] = ''; $filters['rule'] = 'skipped_heading';
	$table = new Editorial_Accessibility_Checks_List_Table( $filters ); $table->prepare_items();
	editorial_accessibility_checks_test_assert( 0 === count( $table->items ), 'SQL: Regelfilter ohne Treffer' );
	$filters['rule'] = 'missing_alt'; $filters['orderby'] = 'missing_alt'; $filters['order'] = 'ASC';
	$table = new Editorial_Accessibility_Checks_List_Table( $filters ); $table->prepare_items();
	editorial_accessibility_checks_test_assert( $table->items[0]->ID === $editorial_accessibility_checks_ids[0], 'SQL: einzelne Regel aufsteigend' );
	update_post_meta( $editorial_accessibility_checks_ids[0], Editorial_Accessibility_Checks_Plugin::TIME_META, time() + 500 );
	$filters['orderby'] = 'checked'; $filters['order'] = 'DESC';
	$table = new Editorial_Accessibility_Checks_List_Table( $filters ); $table->prepare_items();
	editorial_accessibility_checks_test_assert( $table->items[0]->ID === $editorial_accessibility_checks_ids[0], 'SQL: Prüfdatum' );
	ob_start(); $table->display(); $html = ob_get_clean();
	editorial_accessibility_checks_test_assert( false !== strpos( $html, '<details>' ) && false !== strpos( $html, '<summary>' ) && false !== strpos( $html, 'scope="col"' ), 'WordPress-Tabelle: Details und Spaltenköpfe' );
	add_action( 'save_post', array( 'Editorial_Accessibility_Checks_Plugin', 'saved' ), 10, 2 );
	wp_update_post( array( 'ID' => $editorial_accessibility_checks_ids[0], 'post_content' => '<img alt="">' ) );
	$data = get_post_meta( $editorial_accessibility_checks_ids[0], Editorial_Accessibility_Checks_Plugin::META, true );
	editorial_accessibility_checks_test_assert( 0 === array_sum( $data['counts'] ), 'save_post prüft geänderten Inhalt erneut' );
	editorial_accessibility_checks_test_assert( '0' === get_post_meta( $editorial_accessibility_checks_ids[0], Editorial_Accessibility_Checks_Plugin::COUNT_PREFIX . 'total', true ) && '0' === get_post_meta( $editorial_accessibility_checks_ids[0], Editorial_Accessibility_Checks_Plugin::COUNT_PREFIX . 'missing_alt', true ), 'Erneuter Prüflauf setzt Zähler auf null' );
	$html = $table->column_title( get_post( $editorial_accessibility_checks_ids[0] ) );
	editorial_accessibility_checks_test_assert( false !== strpos( $html, 'No issues found by these 5 checks. This does not mean the page is accessible.' ), 'Wörtlicher Nulltreffertext' );

	$before = get_post_meta( $editorial_accessibility_checks_ids[0], Editorial_Accessibility_Checks_Plugin::META, true );
	wp_installing( true );
	Editorial_Accessibility_Checks_Plugin::saved( $editorial_accessibility_checks_ids[0], (object) array( 'ID' => $editorial_accessibility_checks_ids[0], 'post_status' => 'publish', 'post_type' => 'eac_test_fixture', 'post_content' => '<img>' ) );
	wp_installing( false );
	editorial_accessibility_checks_test_assert( $before === get_post_meta( $editorial_accessibility_checks_ids[0], Editorial_Accessibility_Checks_Plugin::META, true ), 'Installation überspringt save_post' );
	Editorial_Accessibility_Checks_Plugin::saved( $editorial_accessibility_checks_ids[0], (object) array( 'ID' => $editorial_accessibility_checks_ids[0], 'post_status' => 'draft', 'post_type' => 'eac_test_fixture', 'post_content' => '<img>' ) );
	editorial_accessibility_checks_test_assert( $before === get_post_meta( $editorial_accessibility_checks_ids[0], Editorial_Accessibility_Checks_Plugin::META, true ), 'Entwurf wird nicht geprüft' );
	add_option( Editorial_Accessibility_Checks_Plugin::LOCK, time(), '', false );
	editorial_accessibility_checks_test_assert( is_wp_error( Editorial_Accessibility_Checks_Plugin::batch() ), 'Paralleler Lauf gesperrt' );
	delete_option( Editorial_Accessibility_Checks_Plugin::LOCK );
	wp_set_current_user( 0 );
	editorial_accessibility_checks_test_assert( is_wp_error( Editorial_Accessibility_Checks_Plugin::batch() ), 'Unberechtigter Paketstart abgelehnt' );
	wp_set_current_user( $editorial_accessibility_checks_admins[0] );
	$_GET = array();
	ob_start(); Editorial_Accessibility_Checks_Admin::render(); $html = ob_get_clean();
	editorial_accessibility_checks_test_assert( false !== strpos( $html, 'Automated checks can only detect a part' ) && false !== strpos( $html, 'editorial_accessibility_checks_filter_nonce' ), 'Backend-Seite mit Pflichttext und Formularschutz' );
	file_put_contents( '/tmp/editorial-accessibility-checks-render.html', $html );
	$url = add_query_arg( array( 'page' => 'editorial-accessibility-checks', 'editorial_accessibility_checks_notice' => 'scanned', 'editorial_accessibility_checks_notice_nonce' => wp_create_nonce( 'editorial_accessibility_checks_notice' ) ), admin_url( 'tools.php' ) );
	parse_str( wp_parse_url( $url, PHP_URL_QUERY ), $_GET );
	$_GET['editorial_accessibility_checks_count'] = '20';
	ob_start(); Editorial_Accessibility_Checks_Admin::render(); $html = ob_get_clean();
	editorial_accessibility_checks_test_assert( false !== strpos( $html, 'This batch checked 20 posts.' ) && false !== strpos( $html, 'role="status"' ), 'Scan-Meldung mit Statusrolle' );
	Editorial_Accessibility_Checks_Plugin::activate();
	editorial_accessibility_checks_test_assert( (bool) wp_next_scheduled( Editorial_Accessibility_Checks_Plugin::EVENT ), 'Aktivierung plant Cron' );
	Editorial_Accessibility_Checks_Plugin::deactivate();
	editorial_accessibility_checks_test_assert( ! wp_next_scheduled( Editorial_Accessibility_Checks_Plugin::EVENT ) && is_array( get_post_meta( $editorial_accessibility_checks_ids[0], Editorial_Accessibility_Checks_Plugin::META, true ) ), 'Deaktivierung entfernt Cron und behält Daten' );
	echo "WordPress integration: PASS\n";
} finally {
	remove_action( 'save_post', array( 'Editorial_Accessibility_Checks_Plugin', 'saved' ), 10 );
	foreach ( $editorial_accessibility_checks_ids as $id ) { wp_delete_post( $id, true ); }
	foreach ( $editorial_accessibility_checks_options as $key => $value ) {
		delete_option( $key );
		if ( null !== $value ) { add_option( $key, $value, '', false ); }
	}
	wp_set_current_user( $editorial_accessibility_checks_old_user );
}
