<?php
/**
 * Übersicht mit serverseitiger Filterung und Sortierung.
 *
 * @package Editorial_Accessibility_Checks
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Editorial_Accessibility_Checks_List_Table extends WP_List_Table {
	private $filters;

	public function __construct( $filters ) {
		parent::__construct( array( 'singular' => 'editorial_accessibility_checks_post', 'plural' => 'editorial_accessibility_checks_posts', 'ajax' => false ) );
		$this->filters = $filters;
	}

	public function get_columns() {
		return array_merge(
			array( 'title' => __( 'Title', 'inventivo-editorial-accessibility-checks' ), 'post_type' => __( 'Content type', 'inventivo-editorial-accessibility-checks' ) ),
			Editorial_Accessibility_Checks_Rules::labels(),
			array( 'total' => __( 'Total findings', 'inventivo-editorial-accessibility-checks' ), 'checked' => __( 'Last checked', 'inventivo-editorial-accessibility-checks' ) )
		);
	}

	protected function get_sortable_columns() {
		$columns = array( 'checked' => array( 'checked', true ), 'total' => array( 'total', true ) );
		foreach ( array_keys( Editorial_Accessibility_Checks_Rules::labels() ) as $key ) {
			$columns[ $key ] = array( $key, true );
		}
		return $columns;
	}

	protected function get_primary_column_name() {
		return 'title';
	}

	public function no_items() {
		// Eine leere Liste ist keine Entwarnung: ungeprüfte Beiträge erscheinen hier nicht.
		echo esc_html__( 'No posts match these filters. Pages that have not been checked yet are not shown here. This does not mean the pages are accessible.', 'inventivo-editorial-accessibility-checks' );
	}

	public function prepare_items() {
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			return;
		}
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'title' );
		$types = Editorial_Accessibility_Checks_Plugin::post_types();
		if ( $this->filters['type'] ) {
			$types = array_intersect( $types, array( $this->filters['type'] ) );
		}
		if ( ! $types ) {
			$this->items = array();
			return;
		}
		$keys = array_keys( Editorial_Accessibility_Checks_Rules::labels() );
		$sort = in_array( $this->filters['orderby'], array_merge( $keys, array( 'total' ) ), true ) ? $this->filters['orderby'] : 'checked';
		$args = array(
			'post_type'           => array_values( $types ),
			'post_status'         => 'publish',
			'posts_per_page'      => 20,
			'paged'               => max( 1, (int) $this->filters['paged'] ),
			's'                   => $this->filters['search'],
			'search_columns'      => array( 'post_title' ),
			'ignore_sticky_posts' => true,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Sorting by finding count is the purpose of this screen. The key comes from a whitelist and the list is paginated at 20 rows.
			'meta_key'            => 'checked' === $sort ? Editorial_Accessibility_Checks_Plugin::TIME_META : Editorial_Accessibility_Checks_Plugin::COUNT_PREFIX . $sort,
			'orderby'             => array( 'meta_value_num' => 'ASC' === $this->filters['order'] ? 'ASC' : 'DESC', 'ID' => 'ASC' ),
		);
		if ( in_array( $this->filters['rule'], $keys, true ) ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Filtering by rule is the purpose of this screen and cannot be expressed without a meta query. The key comes from a whitelist and the list is paginated at 20 rows.
			$args['meta_query'] = array(
				array(
					'key'     => Editorial_Accessibility_Checks_Plugin::COUNT_PREFIX . $this->filters['rule'],
					'value'   => 0,
					'compare' => '>',
					'type'    => 'NUMERIC',
				),
			);
		}
		$query = new WP_Query( $args );
		if ( $args['paged'] > max( 1, (int) $query->max_num_pages ) ) {
			$args['paged'] = max( 1, (int) $query->max_num_pages );
			$query = new WP_Query( $args );
		}
		$this->items = $query->posts;
		$this->set_pagination_args( array( 'total_items' => (int) $query->found_posts, 'per_page' => 20, 'total_pages' => (int) $query->max_num_pages ) );
	}

	public function column_title( $post ) {
		$title = get_the_title( $post );
		if ( '' === $title ) {
			$title = __( '(no title)', 'inventivo-editorial-accessibility-checks' );
		}
		$link = current_user_can( 'edit_post', $post->ID ) ? get_edit_post_link( $post->ID, 'raw' ) : '';
		$html = $link ? '<strong><a href="' . esc_url( $link ) . '">' . esc_html( $title ) . '</a></strong>' : '<strong>' . esc_html( $title ) . '</strong>';
		$data = get_post_meta( $post->ID, Editorial_Accessibility_Checks_Plugin::META, true );
		if ( ! is_array( $data ) || ! isset( $data['counts'], $data['findings'] ) ) {
			return $html . '<p>' . esc_html__( 'Not checked yet.', 'inventivo-editorial-accessibility-checks' ) . '</p>';
		}
		if ( ! empty( $data['unreadable'] ) ) {
			// Der Inhalt konnte nicht gelesen werden. Das ist NICHT "nichts gefunden".
			return $html . '<p>' . esc_html__( 'This content could not be analysed. No check has been performed for this page.', 'inventivo-editorial-accessibility-checks' ) . '</p>';
		}
		if ( 0 === array_sum( $data['counts'] ) ) {
			return $html . '<p>' . esc_html__( 'No issues found by these 5 checks. This does not mean the page is accessible.', 'inventivo-editorial-accessibility-checks' ) . '</p>';
		}
		$html .= '<details><summary>' . esc_html__( 'Show findings', 'inventivo-editorial-accessibility-checks' ) . '</summary>';
		$html .= '<p>' . esc_html__( 'Findings of these 5 checks in the saved editor content. Other accessibility issues on this page are not listed here.', 'inventivo-editorial-accessibility-checks' ) . '</p><ol>';
		$labels = Editorial_Accessibility_Checks_Rules::labels();
		foreach ( $data['findings'] as $finding ) {
			if ( ! isset( $labels[ $finding['rule'] ] ) ) {
				continue;
			}
			$html .= '<li value="' . esc_attr( $finding['number'] ) . '"><strong>' . esc_html( $labels[ $finding['rule'] ] ) . '</strong>: ' . esc_html( $finding['excerpt'] ) . '</li>';
		}
		$html .= '</ol>';
		if ( array_sum( $data['counts'] ) > count( $data['findings'] ) ) {
			$html .= '<p>' . esc_html__( 'Only the first 50 findings are stored. The counts include all findings.', 'inventivo-editorial-accessibility-checks' ) . '</p>';
		}
		return $html . '</details>';
	}

	public function column_default( $post, $column_name ) {
		if ( 'post_type' === $column_name ) {
			$type = get_post_type_object( $post->post_type );
			return esc_html( $type ? $type->labels->singular_name : $post->post_type );
		}
		if ( 'checked' === $column_name ) {
			$time = (int) get_post_meta( $post->ID, Editorial_Accessibility_Checks_Plugin::TIME_META, true );
			return $time ? esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $time ) ) : esc_html__( 'Not checked yet.', 'inventivo-editorial-accessibility-checks' );
		}
		$data = get_post_meta( $post->ID, Editorial_Accessibility_Checks_Plugin::META, true );
		if ( ! is_array( $data ) || ! isset( $data['counts'] ) ) {
			return esc_html__( 'Not checked yet.', 'inventivo-editorial-accessibility-checks' );
		}
		$count = 'total' === $column_name ? array_sum( $data['counts'] ) : ( isset( $data['counts'][ $column_name ] ) ? $data['counts'][ $column_name ] : 0 );
		return esc_html( number_format_i18n( $count ) );
	}
}
