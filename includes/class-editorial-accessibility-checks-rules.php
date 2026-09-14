<?php
/**
 * Regeln für gespeicherte redaktionelle Inhalte.
 *
 * @package Editorial_Accessibility_Checks
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Editorial_Accessibility_Checks_Rules {
	const UNCLEAR_LINK_TEXTS = array( 'click here', 'here', 'read more', 'more', 'link', 'this link', 'details', 'learn more', 'continue', 'download', 'hier', 'hier klicken', 'mehr', 'weiterlesen', 'mehr erfahren', 'weitere informationen' );

	public static function labels() {
		return array(
			'missing_alt'          => __( 'Missing alternative text', 'inventivo-editorial-accessibility-checks' ),
			'unclear_link_text'    => __( 'Unclear link text', 'inventivo-editorial-accessibility-checks' ),
			'skipped_heading'      => __( 'Skipped heading level', 'inventivo-editorial-accessibility-checks' ),
			'input_without_label'  => __( 'Form field without label', 'inventivo-editorial-accessibility-checks' ),
			'table_without_header' => __( 'Table without header', 'inventivo-editorial-accessibility-checks' ),
		);
	}

	public static function normalize( $text ) {
		$text = preg_replace( '/[\s\p{Z}]+/u', ' ', (string) $text );
		return trim( (string) $text );
	}

	public static function excerpt( $text ) {
		$text = self::normalize( wp_strip_all_tags( $text ) );
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $text, 0, 120, 'UTF-8' );
		}
		preg_match( '/^.{0,120}/us', $text, $matches );
		return isset( $matches[0] ) ? $matches[0] : '';
	}

	public static function scan( $content, $post_id = 0 ) {
		$keys   = array_keys( self::labels() );
		$active = array_intersect( $keys, (array) apply_filters( 'editorial_accessibility_checks_rules', $keys, $post_id ) );
		$result = array( 'timestamp' => time(), 'counts' => array_fill_keys( $keys, 0 ), 'findings' => array() );
		if ( '' === trim( $content ) ) {
			return self::finish( $result, $post_id );
		}
		if ( ! class_exists( 'DOMDocument' ) ) {
			return new WP_Error( 'editorial_accessibility_checks_dom_missing', __( 'The PHP DOM extension is required to run these checks.', 'inventivo-editorial-accessibility-checks' ) );
		}
		$previous = libxml_use_internal_errors( true );
		libxml_clear_errors();
		$document = new DOMDocument();
		try {
			$loaded = $document->loadHTML( '<?xml encoding="UTF-8">' . $content, LIBXML_NONET );
		} finally {
			libxml_clear_errors();
			libxml_use_internal_errors( $previous );
		}
		if ( ! $loaded ) {
			/*
			 * Konnte der Inhalt nicht gelesen werden, ist das NICHT dasselbe wie "nichts
			 * gefunden". Ein Ergebnis mit lauter Nullen wäre an dieser Stelle eine unwahre
			 * Aussage über die eigene Tätigkeit. Deshalb wird der Zustand eigens vermerkt
			 * und in der Liste eigens ausgegeben.
			 */
			$result['unreadable'] = true;

			return self::finish( $result, $post_id );
		}
		$xpath  = new DOMXPath( $document );
		$labels = array();
		foreach ( $xpath->query( '//label[@for]' ) as $label ) {
			$labels[ $label->getAttribute( 'for' ) ] = true;
		}
		$unclear = array();
		foreach ( (array) apply_filters( 'editorial_accessibility_checks_unclear_link_texts', self::UNCLEAR_LINK_TEXTS ) as $text ) {
			if ( is_string( $text ) ) {
				$unclear[] = self::lower( self::normalize( $text ) );
			}
		}
		$heading = 0;
		$number  = 0;
		foreach ( $xpath->query( '//img | //a[@href] | //h1 | //h2 | //h3 | //h4 | //h5 | //h6 | //input | //select | //textarea | //table' ) as $node ) {
			$rule = '';
			$tag  = $node->tagName;
			if ( 'img' === $tag && ( ! $node->hasAttribute( 'alt' ) || ( '' !== $node->getAttribute( 'alt' ) && '' === self::normalize( $node->getAttribute( 'alt' ) ) ) ) ) {
				$rule = 'missing_alt';
			} elseif ( 'a' === $tag ) {
				$text  = self::normalize( $node->textContent );
				$named = '' !== self::normalize( $node->getAttribute( 'aria-label' ) ) || '' !== self::normalize( $node->getAttribute( 'title' ) );
				foreach ( $node->getElementsByTagName( 'img' ) as $image ) {
					$named = $named || '' !== self::normalize( $image->getAttribute( 'alt' ) );
				}
				if ( ( '' === $text && ! $named ) || in_array( self::lower( $text ), $unclear, true ) ) {
					$rule = 'unclear_link_text';
				}
			} elseif ( preg_match( '/^h[1-6]$/', $tag ) ) {
				$level = (int) substr( $tag, 1 );
				if ( $heading && $level > $heading + 1 ) {
					$rule = 'skipped_heading';
				}
				$heading = $level;
			} elseif ( in_array( $tag, array( 'input', 'select', 'textarea' ), true ) ) {
				$excluded = 'input' === $tag && in_array( strtolower( trim( $node->getAttribute( 'type' ) ) ), array( 'hidden', 'submit', 'button', 'reset', 'image' ), true );
				$id       = $node->getAttribute( 'id' );
				$named    = ( '' !== $id && isset( $labels[ $id ] ) ) || '' !== self::normalize( $node->getAttribute( 'aria-label' ) ) || '' !== self::normalize( $node->getAttribute( 'aria-labelledby' ) );
				for ( $parent = $node->parentNode; $parent instanceof DOMElement; $parent = $parent->parentNode ) {
					if ( 'label' === $parent->tagName ) {
						$named = true;
						break;
					}
				}
				if ( ! $excluded && ! $named ) {
					$rule = 'input_without_label';
				}
			} elseif ( 'table' === $tag && ! in_array( strtolower( trim( $node->getAttribute( 'role' ) ) ), array( 'presentation', 'none' ), true ) && ! self::has_own_header( $node ) ) {
				$rule = 'table_without_header';
			}
			if ( '' === $rule || ! in_array( $rule, $active, true ) ) {
				continue;
			}
			++$result['counts'][ $rule ];
			++$number;
			if ( count( $result['findings'] ) < 50 ) {
				$evidence = self::normalize( $node->textContent );
				if ( '' === $evidence ) {
					foreach ( array( 'src', 'href', 'id', 'name', 'placeholder', 'type' ) as $attribute ) {
						if ( '' !== $node->getAttribute( $attribute ) ) {
							$evidence = $node->getAttribute( $attribute );
							break;
						}
					}
				}
				$result['findings'][] = array( 'rule' => $rule, 'excerpt' => self::excerpt( $evidence ), 'number' => $number );
			}
		}
		return self::finish( $result, $post_id );
	}

	private static function lower( $text ) {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
	}

	/**
	 * Prüft, ob eine Tabelle eine EIGENE Kopfzelle hat.
	 *
	 * `getElementsByTagName()` sucht über alle Nachfahren. Steckt in einer Zelle eine zweite
	 * Tabelle, die eine Kopfzelle hat, würde die äußere Tabelle sonst als ausgezeichnet gelten,
	 * obwohl sie selbst keine Kopfzeile besitzt. Deshalb wird für jede gefundene Kopfzelle
	 * geprüft, ob die nächste Tabelle über ihr wirklich diese Tabelle ist.
	 *
	 * @param DOMElement $table Die zu prüfende Tabelle.
	 * @return bool
	 */
	private static function has_own_header( $table ) {
		foreach ( $table->getElementsByTagName( 'th' ) as $cell ) {
			$parent = $cell->parentNode;

			while ( $parent instanceof DOMElement ) {
				if ( 'table' === strtolower( $parent->tagName ) ) {
					if ( $parent->isSameNode( $table ) ) {
						return true;
					}

					break;
				}

				$parent = $parent->parentNode;
			}
		}

		return false;
	}

	private static function finish( $result, $post_id ) {
		$findings = apply_filters( 'editorial_accessibility_checks_findings', $result['findings'], $post_id, $result['counts'] );
		$result['findings'] = array();
		foreach ( array_slice( (array) $findings, 0, 50 ) as $finding ) {
			if ( ! is_array( $finding ) || ! isset( $finding['rule'], $finding['excerpt'], $finding['number'] ) || ! isset( $result['counts'][ $finding['rule'] ] ) || ! is_scalar( $finding['excerpt'] ) ) {
				continue;
			}
			$result['findings'][] = array( 'rule' => $finding['rule'], 'excerpt' => self::excerpt( (string) $finding['excerpt'] ), 'number' => absint( $finding['number'] ) );
		}
		$result['unreadable'] = ! empty( $result['unreadable'] );
		return $result;
	}
}
