# Baubericht: Editorial Accessibility Checks

Stand: 10.09.2026. Version 1.0.0.

## Status

Bau abgeschlossen, alle Abnahmen bestanden.

* **Plugin Check:** keine Fehler.
* **Funktionsabnahme:** 23 von 23 Prüffällen korrekt, je Regel mindestens ein Positiv- und ein Negativfall (`_testumgebung/abnahme-accessibility-checks.sh`).
* **Textabnahme:** 0 Sperrlisten-Treffer, 7 von 7 wörtlichen Pflichttexten vorhanden, Exit-Code 0.
* **Zwei unabhängige Gates** am 10.09.2026: Technik und Sicherheit sowie Rechtstexte und Außenwirkung. Alle Blocker eingearbeitet, siehe unten.

Quelle der Wahrheit: `/Users/nilsharder/Documents/PhpstormProjects/_wordpress-plugins/_neu/AUFTRAG-editorial-accessibility-checks.md`, vollständig gelesen. Alle sonstigen vorgegebenen Texte unverändert übernommen, lediglich die Zeilenumbrüche des Markdown-Fließtextes zu Leerzeichen zusammengeführt.

## Zielmanifest und Grenzen

- Lokaler Neubau im vorgegebenen Plugin-Ordner. Keine Kundeninstallation geändert, keine Veröffentlichung, keine Paketinstallation.
- Gespeicherter `post_content`, fünf Regeln, standardmäßig veröffentlichte Beiträge und Seiten.
- Datenfluss ausschließlich WordPress-Datenbank → DOM-Auswertung → eigene Beitragsmetadaten → Backend-Liste.
- Prüfungen schreiben ausschließlich eigene Ergebnisse und Position des Paketlaufs. Keine Inhaltskorrektur.
- Administration mit `edit_others_posts`, Editorlinks zusätzlich mit `edit_post`. Cron ist ein interner Einstieg ohne angemeldeten Benutzer und prüft `wp_doing_cron()`.
- Abnahme: PHP-Syntax, Positiv-/Negativfälle, Textvergleich, echte WordPress-Integration, unabhängiger Code- und Sicherheitsdurchlauf.

## Dateiübersicht

| Datei | Aufgabe |
| --- | --- |
| `editorial-accessibility-checks.php` | Vollständiger Plugin-Kopf, Includes, Aktivierungs- und Deaktivierungshooks |
| `includes/class-editorial-accessibility-checks-rules.php` | DOM-Auswertung, fünf Regeln, Unicode-Belegstellen, Speichergrenzen |
| `includes/class-editorial-accessibility-checks-plugin.php` | Zeitplan, Paketlauf, Cursor, Sperre, Speichern von Ergebnissen, save_post |
| `includes/class-editorial-accessibility-checks-admin.php` | Tools-Seite, Rechte, Formulare, Scan-Rückmeldung, feste Texte |
| `includes/class-editorial-accessibility-checks-list-table.php` | WP_List_Table, Filter, Titelsuche, Sortierung, Seiten, Details |
| `assets/admin.css` | Ausschließlich Backend, Fokusmarkierung, schmale Ansichten ohne Skriptabhängigkeit |
| `uninstall.php` | Entfernt beide Metadatenschlüssel, eigene Optionen und Cron, auch über Multisite-Blogs |
| `readme.txt` | Verzeichnismetadaten, Beschreibung, Installation, FAQ, Änderungsliste |
| `LICENSE` | Lizenzhinweis GPLv2 oder später und Verweis auf den vollständigen Lizenztext |
| `languages/editorial-accessibility-checks.pot` | Mit WP-CLI aus PHP-Quellen erzeugter Übersetzungskatalog |
| `tests/test-rules.php` | 35 isolierte Regel- und Grenzfalltests |
| `tests/test-wordpress.php` | 22 Integrationstests in einer lokalen WordPress-Testinstallation |
| `tests/check-texts.py` | Sperrlistenprüfung und wortgetreuer Abgleich mit dem Originalauftrag |
| `BAUBERICHT.md` | Bauumfang, Prüfergebnisse, Grenzen und Folgeauftrag |

## Hooks mit Fundstelle

Zeilennummern beziehen sich auf diesen Stand.

| Hook | Fundstelle | Aufgabe |
| --- | --- | --- |
| `editorial_accessibility_checks_rules` | Rules-Klasse:41 | Aktive Regelschlüssel filtern, Beitrags-ID als Kontext |
| `editorial_accessibility_checks_unclear_link_texts` | Rules-Klasse:67 | Liste nichtssagender Verweistexte erweitern |
| `editorial_accessibility_checks_findings` | Rules-Klasse:136 | Bis zu 50 Belegstellen nach der Auswertung filtern, Beitrags-ID und vollständige Zähler als Kontext |
| `editorial_accessibility_checks_post_types` | Plugin-Klasse:30 | Inhaltstypen filtern, danach auf registrierte öffentliche Typen begrenzen |
| `editorial_accessibility_checks_batch_size` | Plugin-Klasse:126 | Paketgröße ändern, Standard 20, begrenzt auf 1 bis 200 |
| `editorial_accessibility_checks_scan` | Plugin-Klasse:21 | Wiederkehrender Paketlauf |
| `cron_schedules` | Plugin-Klasse:19 | Eigenes Fünf-Minuten-Intervall |
| `init` | Plugin-Klasse:20 | Fehlenden Zeitplan anlegen |
| `save_post` | Plugin-Klasse:22 | Berechtigte Speicherung erneut prüfen, Revisionen/Autosaves/Installation ausschließen |
| `admin_menu` | Admin-Klasse:13 | Menü unter Tools |
| `admin_enqueue_scripts` | Admin-Klasse:14 | CSS ausschließlich auf eigener Backend-Seite |
| `admin_post_editorial_accessibility_checks_scan_now` | Admin-Klasse:15 | POST mit Capability und Nonce, dasselbe Paketverfahren |
| Aktivierung/Deaktivierung | Hauptdatei:25/26 | Zeitplan anlegen beziehungsweise abmelden |

Die vollständigen Dateinamen der Klassen stehen in der Dateiübersicht. Es wurden ausschließlich die Erweiterungshooks gebaut, keine bezahlten Zusatzfunktionen. Der Findings-Filter bearbeitet die begrenzte Belegstellenliste, nicht die bereits vollständig ermittelten Zähler; das ist sein bewusst getrennter Vertrag.

## Datenhaltung und Paketverarbeitung

`_editorial_accessibility_checks` enthält ein Array mit `timestamp`, `counts` (fünf Integer-Zähler) und `findings`. Jede Fundstelle enthält `rule`, `excerpt` und `number`. Belegstellen sind HTML-frei und höchstens 120 Unicode-Zeichen lang. Bei textlosen Elementen werden vorhandene Attribute wie Bildquelle, Linkziel oder Feld-ID als Beleg benutzt. Fehlen auch diese, bleibt die Belegstelle leer; die laufende Fundstellennummer identifiziert sie weiterhin.

`_editorial_accessibility_checks_time` enthält denselben Unix-Zeitstempel separat und bleibt ebenso wie das Sammelfeld unverändert. Jeder Prüflauf schreibt zusätzlich Integer-Werte in `_editorial_accessibility_checks_count_missing_alt`, `_editorial_accessibility_checks_count_unclear_link_text`, `_editorial_accessibility_checks_count_skipped_heading`, `_editorial_accessibility_checks_count_input_without_label` und `_editorial_accessibility_checks_count_table_without_header`. `_editorial_accessibility_checks_count_total` enthält deren Summe. Auch Nullwerte werden geschrieben, damit erneute Prüfungen alte Treffer zurücksetzen.

Die Liste verwendet ausschließlich `WP_Query`: Regelfilter über numerische `meta_query` mit `> 0`, Sortierung über `meta_key` und `meta_value_num`, bei Gleichstand ID aufsteigend. Die Titelsuche nutzt `s` mit `search_columns = post_title`. Seiten enthalten maximal 20 Beiträge; eine zu hohe Seitenzahl wird auf die letzte Seite begrenzt. Keine Migration: Die Felder entstehen beim nächsten Prüflauf. Die Metasortierung berücksichtigt Beiträge mit vorhandenem Sortierfeld; noch nicht geprüfte Beiträge beziehungsweise noch fehlende Zähler erscheinen nach dem Prüflauf.

`editorial_accessibility_checks_cursor` hält jetzt den verarbeiteten Zeilenoffset. Die Paketabfrage verwendet `WP_Query` mit veröffentlichten freigegebenen Beitragstypen, `fields = ids`, ID aufsteigend, Paketgröße, `paged` und gespeichertem `offset`. Der explizite Offset erhält die Position auch bei wechselnder Paketgröße und nach einem teilweise fehlgeschlagenen Paket. Nach dem letzten Paket wird er zurückgesetzt. Löschungen oder Statuswechsel während eines Zyklus können Positionen verschieben; der nächste vollständige Zyklus erfasst diese Beiträge wieder. `editorial_accessibility_checks_lock` verhindert parallele Pakete über atomare Optionsanlage; verwaiste Sperren laufen nach einer Stunde aus. Optionen werden nicht automatisch geladen.

Deaktivierung entfernt nur den Zeitplan und erhält Ergebnisse/Position. Netzwerk-Deaktivierung berücksichtigt die einzelnen Blogs. Deinstallation entfernt das Sammelfeld, das Zeitstempelfeld, alle sechs Zählerfelder, beide Optionen und den Zeitplan ausschließlich über WordPress-Funktionen.

## Selbstabnahme: PHP und Texte

### Gezielter WP_Query-Umbau, 10.09.2026

- Alle acht PHP-Dateien mit `php -l` unter PHP 8.4.17 geprüft, keine Syntaxfehler. Die 35 Regeltests bestehen unverändert.
- Echte WordPress-Integration im bestehenden lokalen Container `wp-plugintest-84`: 293 Assertions bestanden. 45 temporäre veröffentlichte Testbeiträge mit 1 bis 45 Bildtreffern wurden in Paketen 20/20/5 geprüft und anschließend gelöscht. Der gespeicherte Offset beträgt nach Paket 1 genau 20 und nach Abschluss 0.
- Alle fünf Regelzähler und die Summe wurden für jeden Testbeitrag gegen das Sammelfeld verglichen, einschließlich Nullwerten. Erneutes Speichern eines bereinigten Beitrags setzt Regel- und Gesamtzähler auf 0.
- Liste: Gesamttreffer absteigend (45 zuerst, 20 Zeilen), Einzelregel aufsteigend (1 zuerst), dritte Seite (5 Zeilen), Regelfilter ohne Treffer (0 Zeilen), Titelsuche `fixture 12` (genau Beitrag 12) und Prüfdatum absteigend (gezielt erhöhter Zeitstempel zuerst). Das Sammelfeld von Beitrag 45 wurde vor diesen Abfragen gelöscht: Filter und Sortierung funktionieren weiterhin anhand der separaten Metafelder. Die historischen Testlabels mit `SQL:` bezeichnen jetzt diese WP_Query-Prüfungen.
- Suche im gesamten Plugin nach `wpdb`, SQL-Operationen und den bisherigen SQL-Parserfunktionen: keine eigene SQL-Abfrage mehr, auch nicht in `uninstall.php`. Nur die Plugin-Klasse verwendet `$wpdb->last_error` für die vorhandene Fehlererkennung; die Listentabelle verwendet kein `$wpdb`. Keine PHPCS-Unterdrückung ergänzt.
- Nutzersichtbare Texte unverändert; ausschließlich bestehender Fehlertext für das Speichern der zusätzlichen Metafelder wiederverwendet. Kein offizieller Plugin-Check-Lauf in dieser Abnahme, sondern Syntax-, Quellen- und WordPress-Laufzeitprüfung. Deinstallation anhand der acht expliziten Metaschlüssel im Code geprüft, nicht auf einer laufenden Installation ausgeführt.

Die folgenden Abschnitte dokumentieren die frühere Erstabnahme.

- `php -l`: alle 8 PHP-Dateien unter PHP 7.4.33 und PHP 8.4.17 ohne Syntaxfehler.
- Regeltests: 35 von 35 unter beiden PHP-Versionen bestanden.
- Keine versteckten Dateien im Plugin-Ordner.
- Kein eigener Frontend-Hook, kein JavaScript, keine externe Laufzeitabfrage.
- Übersetzungskatalog aus Quellen erzeugt, keine manuell erfundenen Übersetzungseinträge.
- Sperrlistenprüfung über extrahierte Oberflächentexte, readme und Lizenzhinweis: 0 Treffer.
- Wörtliche Textprüfung: 7 von 7 vorhanden, Exit-Code 0.

## Positiv- und Negativfälle je Regel

| Regel | Positivfälle, erwartete und tatsächliche Treffer | Negativfälle, jeweils 0 Treffer |
| --- | --- | --- |
| `missing_alt` | Fehlendes Attribut: 1; Leerzeichen: 1; geschütztes Leerzeichen: 1 | `alt=""`; gefüllter Alternativtext |
| `unclear_link_text` | Leerer Link: 1; `READ MORE` mit äußeren Leerzeichen: 1; `here` trotz ARIA-Name: 1 | Bildlink mit gefülltem `alt`; leerer Link mit ARIA-Name; leerer Link mit Titel; beschreibender Linktext; Anker ohne href |
| `skipped_heading` | h2 direkt zu h4: 1 | h4 zu h2; einzelne erste h6; h2 zu h3 und nochmals h3 |
| `input_without_label` | Input, Select und Textarea ohne Label zusammen: 3; ARIA-Label nur aus Leerzeichen: 1 | Passendes label-for; umschließendes Label; gefülltes ARIA-Label; ARIA-Verweis; alle fünf ausgeschlossenen Input-Typen |
| `table_without_header` | Tabelle nur mit td: 1 | Tabelle mit th; role presentation; role none |

Zusätzlich bestanden: 75 Treffer ergeben 50 gespeicherte Belege und korrekte Nummern; 120-Zeichen-Grenze mit Umlauten; leerer Inhalt; Parserzustand nach fehlerhaftem HTML wiederhergestellt; erweiterbare Linkliste; aktive Regeln filterbar; Belegstellenfilter wird weiterhin auf 50 Einträge und 120 Zeichen begrenzt.

## WordPress-Laufzeitprüfung

Lokaler bestehender Testcontainer `wp-plugintest-84`, WordPress 7.1. Das Plugin wurde für die Tests nach `/tmp/editorial-accessibility-checks` kopiert und nur im Testprozess eingebunden, nicht dauerhaft aktiviert. Eigene Testbeiträge wurden nach jedem Durchlauf gelöscht. Der zunächst versuchte Host-Zugriff auf die lokale Testdatenbank war nicht erreichbar; die erfolgreiche Prüfung erfolgte innerhalb des bereits laufenden Testcontainers.

22 Prüfungen bestanden, zuletzt ohne PHP-Warnungen:

- Pakete 20/20/5, Cursor nach erstem Paket, unangetasteter 21. Beitrag und Rücksetzung nach Ende.
- SQL: Gesamttreffer numerisch absteigend, Einzelregel aufsteigend, Prüfdatum, Titelsuche, Regelfilter, dritte Seite mit fünf Zeilen.
- Echtes WP_List_Table-Markup enthält native Details und Spaltenköpfe mit scope.
- save_post aktualisiert Ergebnisse; Installation und Entwurfsstatus werden übersprungen.
- Wörtlicher Nulltreffertext.
- Paralleler Paketstart und nicht angemeldeter Paketstart werden abgewiesen.
- Backend-Pflichttext, Formular-Nonce, Scan-Meldung und Statusrolle.
- Aktivierung plant Cron; Deaktivierung entfernt Cron und behält Daten.

Nicht als getestet behauptet: manuelle Screenreader-/Tastaturbedienung im Browser, echter Multisite-Lauf, Deinstallation in einer laufenden Installation, erzwungener SQL-Schreibfehler und HTTP-Ablehnung ungültiger Formular-Nonces. Diese Stellen wurden im Code geprüft. Ein zusätzlicher Testausbau für Fehlersimulation und Nonce-Ablehnung wurde vom automatischen Sicherheits-Hook als codebasierter Zugriff auf ein sensibles Ziel blockiert und nicht auf anderem Weg erzwungen.

## Unabhängiger Code- und Sicherheitsdurchlauf

Ein separater Reviewer prüfte gegen den Originalauftrag. Vier Befunde wurden behoben und gezielt nachgeprüft:

1. Rohe Redirect-URL statt HTML-maskierter URL, damit Scan-Rückmeldungen ankommen.
2. Prüfung der Filterformular-Nonce beim Absenden.
3. Netzwerkweite Cron-Abmeldung bei Netzwerk-Deaktivierung.
4. Rückgabe von Metadaten-Schreibfehlern, bevor der Paket-Cursor weiterrückt.

Die Nachprüfung bestätigte alle vier Korrekturen. Zusätzlich wurde für Schreibfehler eine eigene, zutreffende Meldung ergänzt und der Übersetzungskatalog danach erneut erzeugt. Keine konkrete SQL-Injection, Ausgabe-XSS oder externe Laufzeitübertragung im geprüften Code gefunden. Die abschließende Textfreigabe bleibt offen.

Technische Referenzen: [WP_List_Table](https://developer.wordpress.org/reference/classes/wp_list_table/), [Sortierdefinitionen](https://developer.wordpress.org/reference/classes/wp_list_table/get_sortable_columns/), [wp_admin_notice](https://developer.wordpress.org/reference/functions/wp_admin_notice/). Das lokale WordPress-7.1-Core-Verhalten wurde zusätzlich zur Dokumentation geprüft.

## Bewusst nicht gebaut

Keine Editor-Integration, Kontrastprüfung, Verlaufsspeicherung, Ausnahmenverwaltung, Berichtsausgabe, REST-Route, Frontend-Komponente oder Einstellungsseite. Keine bezahlten Funktionen, keine automatische Änderung oder Korrektur. Keine Produktbehauptung über rechtliche Folgen fremder Werkzeuge.

## Der Textwiderspruch aus dem Bauauftrag, erledigt

Der Bauauftrag hatte einen Widerspruch: `BFSG` und `ADA compliant` standen auf der Sperrliste,
während der wörtlich vorgeschriebene Fußtext dieselben Begriffe enthält. Der Bau hat den
Konflikt markiert und den Fußtext zunächst ausgelassen, statt ihn eigenmächtig umzuformulieren.

**Am 10.09.2026 aufgelöst:** Der Fußtext nennt WCAG, EAA, BFSG und ADA ausschließlich in der
**Verneinung** und ist damit das Gegenteil einer Konformitätsbehauptung. Genau das ist der Zweck
von Auflage 2 der Rechtsprüfung. Er steht jetzt unverändert an zwei Stellen:

* `includes/class-editorial-accessibility-checks-admin.php`, als letzter Absatz jeder Plugin-Seite
* `readme.txt`, im Abschnitt „Legal note"

Die Sperrliste gilt unverändert für alle übrigen Texte. Die Ausnahme ist auf diesen einen
vollständigen Pflichttext begrenzt.


## Befunde der beiden Gates vom 10.09.2026, alle eingearbeitet

**Technik und Sicherheit, ein Blocker:**

* `table_without_header` erkannte verschachtelte Tabellen falsch. `getElementsByTagName( 'th' )`
  sucht über alle Nachfahren, eine kopflose äußere Tabelle galt deshalb als ausgezeichnet, sobald
  eine innere Tabelle eine Kopfzelle hatte. Behoben mit `has_own_header()`, das für jede Kopfzelle
  prüft, ob die nächste Tabelle darüber wirklich die geprüfte ist. Zwei neue Prüffälle in der
  Abnahme decken beide Richtungen ab.
* Totes `paged`-Argument neben `offset` in der Cron-Abfrage entfernt.

**Rechtstexte und Außenwirkung, fünf Blocker:**

* Der Haftungssatz fehlte im Übersetzungskatalog. In einem deutschen Backend wäre ausgerechnet er
  als einziger Absatz englisch geblieben. Katalog neu aus den Quellen erzeugt.
* Ein gescheiterter Parser wurde als „No issues found" ausgegeben, also als hätte eine Prüfung
  stattgefunden. Das war die einzige unwahre Aussage des Plugins über die eigene Tätigkeit. Der
  Zustand wird jetzt eigens gespeichert (`unreadable`) und eigens ausgegeben.
* Die Leerliste sagte „No posts match these filters." ohne Einschränkung. Bei aktivem Regelfilter
  erscheinen ungeprüfte Beiträge dort gar nicht, die Meldung las sich also wie eine Entwarnung.
  Text um genau diesen Hinweis ergänzt.
* Der Prüfumfang stand nur in der readme. Ein Satz in der Oberfläche sagt jetzt, dass ausschließlich
  der gespeicherte Editor-Inhalt gelesen wird, nicht Theme, Seitenbaukasten, Shortcodes oder Skripte.
* Die Detailansicht trug keinen Bezugsrahmen. Sie beginnt jetzt mit dem Hinweis, dass dort nur die
  Funde dieser fünf Regeln stehen.

**Weiter eingearbeitet:** interner Verweis auf die Rechtsprüfung aus dem Auslieferungscode entfernt,
Angabe zur Datenlöschung in der readme korrigiert (es sind acht Metaschlüssel, nicht zwei), das
Textprüfskript nimmt den Pflichtsatz gezielt von der Sperrlistenprüfung aus statt dauerhaft rot zu
melden.

**Bewusst nicht geändert, als Hinweis festgehalten:**

* `aria-labelledby` wird nicht gegen die Existenz des referenzierten Elements geprüft. So im Auftrag
  vorgesehen, in der Praxis ein falsch-negativer Fall. Kandidat für die nächste Auftragsrevision.
* Wird ein Inhaltstyp über den Filter aus der Prüfliste entfernt, bleiben seine Metadaten bis zur
  Deinstallation stehen.
* Der Nonce auf dem reinen Lesefilter greift nicht bei allen Parameterkombinationen. Unschädlich,
  weil dort nichts geschrieben wird und die Capability-Prüfung davor liegt.
* Für sehr große Einzelbeiträge gibt es keine eigene Größengrenze, nur die Paketgrenze über alle
  Beiträge.

**Für die spätere bezahlte Fassung vorzumerken:** Die Aussage „No data collection" wird falsch,
sobald Freemius eingebaut wird, und sie wird nach §§ 327 ff. BGB Teil der geschuldeten
Beschaffenheit. Vor dem Pro-Bau anpassen.
