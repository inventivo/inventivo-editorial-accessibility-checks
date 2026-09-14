<?php
/** Lokale Regeltests ohne WordPress-Installation. */
if ( PHP_SAPI !== 'cli' ) { exit; }
define( 'ABSPATH', __DIR__ . '/' );
function __( $text, $domain = '' ) { return $text; }
function wp_strip_all_tags( $text ) { return strip_tags( $text ); }
function absint( $number ) { return abs( (int) $number ); }
function apply_filters( $name, $value, ...$args ) {
	return isset( $GLOBALS['editorial_accessibility_checks_test_filters'][ $name ] ) ? $GLOBALS['editorial_accessibility_checks_test_filters'][ $name ]( $value, ...$args ) : $value;
}
require dirname( __DIR__ ) . '/includes/class-editorial-accessibility-checks-rules.php';
$editorial_accessibility_checks_cases = array(
	array( 'missing_alt', '<img src="example.jpg">', 1, 'Attribut fehlt' ),
	array( 'missing_alt', '<img alt="   ">', 1, 'Leerzeichen' ),
	array( 'missing_alt', '<img alt="">', 0, 'Dekoratives Bild' ),
	array( 'missing_alt', '<img alt="Eine Katze">', 0, 'Gefüllter Alternativtext' ),
	array( 'missing_alt', '<img alt="&nbsp;">', 1, 'Geschütztes Leerzeichen' ),
	array( 'unclear_link_text', '<a href="/target"> READ MORE </a>', 1, 'Nichtssagender Text' ),
	array( 'unclear_link_text', '<a href="/target"></a>', 1, 'Leerer Verweis' ),
	array( 'unclear_link_text', '<a href="/target"><img alt="Produkt ansehen"></a>', 0, 'Bildlink mit Alternativtext' ),
	array( 'unclear_link_text', '<a href="/target" aria-label="Kontakt"></a>', 0, 'ARIA-Name' ),
	array( 'unclear_link_text', '<a href="/target" title="Kontakt"></a>', 0, 'Titel' ),
	array( 'unclear_link_text', '<a href="/target">Kontakt zum Team</a>', 0, 'Beschreibender Text' ),
	array( 'unclear_link_text', '<a>here</a>', 0, 'Kein href' ),
	array( 'unclear_link_text', '<a href="/target" aria-label="Kontakt">here</a>', 1, 'Nichtssagender Text trotz ARIA' ),
	array( 'skipped_heading', '<h2>A</h2><h4>B</h4>', 1, 'Ebene übersprungen' ),
	array( 'skipped_heading', '<h4>A</h4><h2>B</h2>', 0, 'Absteigende Ebene' ),
	array( 'skipped_heading', '<h6>A</h6>', 0, 'Erste Überschrift' ),
	array( 'skipped_heading', '<h2>A</h2><h3>B</h3><h3>C</h3>', 0, 'Nächste und gleiche Ebene' ),
	array( 'input_without_label', '<input id="a"><select></select><textarea></textarea>', 3, 'Drei unbeschriftete Feldarten' ),
	array( 'input_without_label', '<label for="a">Name</label><input id="a">', 0, 'Zugeordnetes Label' ),
	array( 'input_without_label', '<label>Name <span><input></span></label>', 0, 'Umschließendes Label' ),
	array( 'input_without_label', '<select aria-label="Auswahl"></select>', 0, 'ARIA-Beschriftung' ),
	array( 'input_without_label', '<textarea aria-labelledby="name"></textarea>', 0, 'ARIA-Verweis laut Regel' ),
	array( 'input_without_label', '<input type="hidden"><input type="submit"><input type="button"><input type="reset"><input type="image">', 0, 'Ausgeschlossene Typen' ),
	array( 'input_without_label', '<input aria-label=" ">', 1, 'Leere ARIA-Beschriftung' ),
	array( 'table_without_header', '<table><tr><td>A</td></tr></table>', 1, 'Kein Tabellenkopf' ),
	array( 'table_without_header', '<table><tr><th>A</th></tr></table>', 0, 'Tabellenkopf' ),
	array( 'table_without_header', '<table role="presentation"><tr><td>A</td></tr></table>', 0, 'Präsentationstabelle' ),
	array( 'table_without_header', '<table role="none"><tr><td>A</td></tr></table>', 0, 'Neutrale Tabelle' ),
);
$editorial_accessibility_checks_failures = 0;
foreach ( $editorial_accessibility_checks_cases as $editorial_accessibility_checks_case ) {
	list( $rule, $html, $expected, $name ) = $editorial_accessibility_checks_case;
	$result = Editorial_Accessibility_Checks_Rules::scan( $html );
	$ok = $result['counts'][ $rule ] === $expected;
	echo ( $ok ? 'PASS ' : 'FAIL ' ) . $rule . ': ' . $name . PHP_EOL;
	$editorial_accessibility_checks_failures += $ok ? 0 : 1;
}
function editorial_accessibility_checks_assert( $condition, $name ) {
	echo ( $condition ? 'PASS ' : 'FAIL ' ) . $name . PHP_EOL;
	$GLOBALS['editorial_accessibility_checks_failures'] += $condition ? 0 : 1;
}
$result = Editorial_Accessibility_Checks_Rules::scan( str_repeat( '<img src="bild.jpg">', 75 ) );
editorial_accessibility_checks_assert( 75 === $result['counts']['missing_alt'] && 50 === count( $result['findings'] ) && 50 === $result['findings'][49]['number'], '75 Treffer, 50 Belegstellen, laufende Nummern' );
$result = Editorial_Accessibility_Checks_Rules::scan( '<h2>A</h2><h4>' . str_repeat( 'Ä', 200 ) . '</h4>' );
editorial_accessibility_checks_assert( 120 === mb_strlen( $result['findings'][0]['excerpt'] ), '120 Unicode-Zeichen' );
$result = Editorial_Accessibility_Checks_Rules::scan( '' );
editorial_accessibility_checks_assert( 0 === array_sum( $result['counts'] ), 'Leerer Inhalt' );
libxml_use_internal_errors( false );
$result = Editorial_Accessibility_Checks_Rules::scan( '<p><a href="/x">here' );
editorial_accessibility_checks_assert( false === libxml_use_internal_errors() && 0 === count( libxml_get_errors() ), 'Parserzustand wiederhergestellt' );
$GLOBALS['editorial_accessibility_checks_test_filters']['editorial_accessibility_checks_unclear_link_texts'] = function( $texts ) { $texts[] = 'weiter bitte'; return $texts; };
$result = Editorial_Accessibility_Checks_Rules::scan( '<a href="/x">weiter bitte</a>' );
editorial_accessibility_checks_assert( 1 === $result['counts']['unclear_link_text'], 'Erweiterbare Verweisliste' );
$GLOBALS['editorial_accessibility_checks_test_filters']['editorial_accessibility_checks_rules'] = function() { return array( 'missing_alt' ); };
$result = Editorial_Accessibility_Checks_Rules::scan( '<img><a href="/x">here</a>' );
editorial_accessibility_checks_assert( 1 === array_sum( $result['counts'] ), 'Aktive Regeln filterbar' );
$GLOBALS['editorial_accessibility_checks_test_filters']['editorial_accessibility_checks_findings'] = function( $findings ) { return array_fill( 0, 70, array( 'rule' => 'missing_alt', 'excerpt' => '<b>' . str_repeat( 'Ä', 200 ) . '</b>', 'number' => 1 ) ); };
$result = Editorial_Accessibility_Checks_Rules::scan( '<img>' );
editorial_accessibility_checks_assert( 50 === count( $result['findings'] ) && 120 === mb_strlen( $result['findings'][0]['excerpt'] ) && false === strpos( $result['findings'][0]['excerpt'], '<' ), 'Filtergrenzen und HTML-Entfernung' );
echo 'Failures: ' . $editorial_accessibility_checks_failures . PHP_EOL;
exit( $editorial_accessibility_checks_failures ? 1 : 0 );
