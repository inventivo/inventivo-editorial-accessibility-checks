<?php
/**
 * Lebenszyklus, Metadaten und Paketverarbeitung.
 *
 * @package Editorial_Accessibility_Checks
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Editorial_Accessibility_Checks_Plugin {
	const META = '_editorial_accessibility_checks';
	const COUNT_PREFIX = '_editorial_accessibility_checks_count_';
	const TIME_META = '_editorial_accessibility_checks_time';
	const EVENT = 'editorial_accessibility_checks_scan';
	const CURSOR = 'editorial_accessibility_checks_cursor';
	const LOCK = 'editorial_accessibility_checks_lock';

	public static function boot() {
		add_filter( 'cron_schedules', array( __CLASS__, 'schedules' ) );
		add_action( 'init', array( __CLASS__, 'schedule' ) );
		add_action( self::EVENT, array( __CLASS__, 'cron' ) );
		add_action( 'save_post', array( __CLASS__, 'saved' ), 10, 2 );
		if ( is_admin() ) {
			require_once __DIR__ . '/class-editorial-accessibility-checks-admin.php';
			Editorial_Accessibility_Checks_Admin::boot();
		}
	}

	public static function post_types() {
		$types = array_filter( (array) apply_filters( 'editorial_accessibility_checks_post_types', array( 'post', 'page' ) ), 'is_string' );
		return array_values( array_intersect( $types, get_post_types( array( 'public' => true ) ) ) );
	}

	public static function schedules( $schedules ) {
		$schedules['editorial_accessibility_checks_interval'] = array( 'interval' => 300, 'display' => __( 'Every five minutes', 'inventivo-editorial-accessibility-checks' ) );
		return $schedules;
	}

	public static function activate() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		self::schedule();
	}

	public static function schedule() {
		// Interner Lebenszyklus ohne Benutzerkontext, auch für WP-Cron erforderlich.
		if ( ! wp_installing() && ! wp_next_scheduled( self::EVENT ) ) {
			wp_schedule_event( time() + 10, 'editorial_accessibility_checks_interval', self::EVENT );
		}
	}

	public static function deactivate( $network_wide = false ) {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		if ( is_multisite() && $network_wide ) {
			$offset = 0;
			do {
				$sites = get_sites( array( 'fields' => 'ids', 'number' => 100, 'offset' => $offset ) );
				foreach ( $sites as $site ) {
					switch_to_blog( $site );
					wp_clear_scheduled_hook( self::EVENT );
					restore_current_blog();
				}
				$offset += 100;
			} while ( count( $sites ) === 100 );
		} else {
			wp_clear_scheduled_hook( self::EVENT );
		}
	}

	public static function cron() {
		// WP-Cron besitzt keinen angemeldeten Benutzer. Nur dessen Hook darf hier starten.
		if ( wp_doing_cron() && ! wp_installing() ) {
			self::batch();
		}
	}

	public static function saved( $post_id, $post ) {
		if ( wp_installing() || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( 'publish' === $post->post_status && in_array( $post->post_type, self::post_types(), true ) ) {
			self::check_post( $post );
		}
	}

	public static function check_post( $post ) {
		$result = Editorial_Accessibility_Checks_Rules::scan( $post->post_content, $post->ID );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		global $wpdb;
		update_post_meta( $post->ID, self::META, $result );
		if ( $wpdb->last_error ) {
			return new WP_Error( 'editorial_accessibility_checks_save_failed', __( 'The scan could not save its results. Please try again later.', 'inventivo-editorial-accessibility-checks' ) );
		}
		$counts = array_map( 'intval', $result['counts'] );
		$counts['total'] = array_sum( $counts );
		foreach ( $counts as $key => $count ) {
			update_post_meta( $post->ID, self::COUNT_PREFIX . $key, (int) $count );
			if ( $wpdb->last_error ) {
				return new WP_Error( 'editorial_accessibility_checks_save_failed', __( 'The scan could not save its results. Please try again later.', 'inventivo-editorial-accessibility-checks' ) );
			}
		}
		update_post_meta( $post->ID, self::TIME_META, $result['timestamp'] );
		if ( $wpdb->last_error ) {
			return new WP_Error( 'editorial_accessibility_checks_save_failed', __( 'The scan could not save its results. Please try again later.', 'inventivo-editorial-accessibility-checks' ) );
		}
		return true;
	}

	public static function batch() {
		if ( ! wp_doing_cron() && ! current_user_can( 'edit_others_posts' ) ) {
			return new WP_Error( 'editorial_accessibility_checks_permission', __( 'You cannot run these checks.', 'inventivo-editorial-accessibility-checks' ) );
		}
		if ( ! class_exists( 'DOMDocument' ) ) {
			return new WP_Error( 'editorial_accessibility_checks_dom_missing', __( 'The PHP DOM extension is required to run these checks.', 'inventivo-editorial-accessibility-checks' ) );
		}
		// Atomare Optionsanlage verhindert parallele Pakete; verwaiste Sperren laufen aus.
		$locked = (int) get_option( self::LOCK, 0 );
		if ( $locked && $locked < time() - HOUR_IN_SECONDS ) {
			delete_option( self::LOCK );
		}
		if ( ! add_option( self::LOCK, time(), '', false ) ) {
			return new WP_Error( 'editorial_accessibility_checks_busy', __( 'A scan batch is already running. Please try again later.', 'inventivo-editorial-accessibility-checks' ) );
		}
		try {
			$types = self::post_types();
			if ( ! $types ) {
				return 0;
			}
			$size   = max( 1, min( 200, (int) apply_filters( 'editorial_accessibility_checks_batch_size', 20 ) ) );
			// Die gespeicherte Position ist ein Zeilenoffset, keine Beitrags-ID.
			$offset = absint( get_option( self::CURSOR, 0 ) );
			global $wpdb;
			$query = new WP_Query( array(
				'post_type'           => $types,
				'post_status'         => 'publish',
				'fields'              => 'ids',
				'orderby'             => 'ID',
				'order'               => 'ASC',
				'posts_per_page'      => $size,
				// Kein 'paged': WP_Query ignoriert es, sobald 'offset' gesetzt ist.
				'offset'              => $offset,
				'no_found_rows'       => true,
				'ignore_sticky_posts' => true,
			) );
			$ids = $query->posts;
			if ( $wpdb->last_error ) {
				return new WP_Error( 'editorial_accessibility_checks_database', __( 'The scan could not read the posts. Please try again later.', 'inventivo-editorial-accessibility-checks' ) );
			}
			$count = 0;
			foreach ( $ids as $id ) {
				$post = get_post( $id );
				if ( $post && 'publish' === $post->post_status && in_array( $post->post_type, $types, true ) ) {
					$result = self::check_post( $post );
					if ( is_wp_error( $result ) ) {
						return $result;
					}
					++$count;
				}
				update_option( self::CURSOR, ++$offset, false );
				// Keine gesamten Inhalte über die Paketlaufzeit im Objektcache ansammeln.
				unset( $post, $result );
			}
			if ( count( $ids ) < $size ) {
				update_option( self::CURSOR, 0, false );
			}
			return $count;
		} finally {
			delete_option( self::LOCK );
		}
	}
}
