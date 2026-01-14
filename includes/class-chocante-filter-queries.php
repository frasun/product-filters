<?php
/**
 * Product Filters Database queries
 *
 * @package Chocante_Product_Filters
 */

defined( 'ABSPATH' ) || exit;

/**
 * The Chocante_Filter_Queries class.
 */
class Chocante_Filter_Queries {
	/**
	 * WordPress database helper
	 *
	 * @var wpdb
	 */
	private $wpdb;

	/**
	 * Code of currently used language
	 *
	 * @var string
	 */
	private $current_language;

	/**
	 * List of taxonomy slugs for supported filters
	 *
	 * @var array
	 */
	private $filters;

	const CACHE_GROUP = 'chocante_product_filters';

	/**
	 * Constructor
	 *
	 * @param wpdb  $wpdb WordPress database abstraction.
	 * @param array $filters List of supported filters.
	 */
	public function __construct( wpdb $wpdb, array $filters = array() ) {
		$this->wpdb    = $wpdb;
		$this->filters = $filters;

		if ( has_filter( 'wpml_current_language' ) ) {
			$this->current_language = apply_filters( 'wpml_current_language', null );
		}

		// Clear WP Cache on product changes.
		add_action( 'woocommerce_process_product_meta', array( $this, 'clear_cache' ) );
		add_action( 'woocommerce_new_product', array( $this, 'clear_cache' ) );
		add_action( 'woocommerce_new_product_variation', array( $this, 'clear_cache' ) );
		add_action( 'woocommerce_update_product', array( $this, 'clear_cache' ) );
		add_action( 'woocommerce_update_product_variation', array( $this, 'clear_cache' ) );
		add_action( 'wp_trash_post', array( $this, 'clear_cache_on_trash' ) );
	}

	/**
	 * Prepare supported filters to use in SQL
	 *
	 * @return string
	 */
	private function supported_fitlers() {
		return "'" . implode( "','", $this->filters ) . "'";
	}

	/**
	 * Include language clause in db queries
	 *
	 * @return string
	 */
	private function language_query() {
		$query = '';

		if ( isset( $this->current_language ) ) {
			// phpcs:disable
			$query = $this->wpdb->prepare(
			"JOIN {$this->wpdb->prefix}icl_translations wpml_translations ON p.ID = wpml_translations.element_id
					AND wpml_translations.element_type = 'post_product'
					AND wpml_translations.language_code = %s",
			$this->current_language
			);
			// phpcs:enable
		}

		return $query;
	}

	/**
	 * Include filter taxonomies in db queries
	 *
	 * @param int|null $tax_id Queried taxonomy ID.
	 * @return string
	 */
	private function filters_main_query( $tax_id = null ) {
		$tax_query = $tax_id ? $this->filter_query( array( 'product_cat' => array( $tax_id ) ) ) : null;

		return "{$this->wpdb->prefix}posts p
						{$this->language_query()}
						JOIN {$this->wpdb->prefix}term_relationships ttc ON p.ID = ttc.object_id
						JOIN {$this->wpdb->prefix}term_taxonomy tx ON ttc.term_taxonomy_id = tx.term_taxonomy_id
							AND(tx.taxonomy IN ({$this->supported_fitlers()})){$tax_query}";
	}

	/**
	 * DB query conditions for products
	 */
	private function where_product_query() {
		return "AND p.post_type = 'product'
							AND(p.post_status = 'publish'
								OR p.post_status = 'private')";
	}

	/**
	 * Include IDs of products on sale in db queries
	 *
	 * @param bool $on_sale Whether to include condition for products on sale.
	 * @return string;
	 */
	private function on_sale_query( $on_sale = false ) {
		if ( ! $on_sale ) {
			return '';
		}

		$on_sale_ids = implode( ',', wc_get_product_ids_on_sale() );

		return "AND p.ID IN({$on_sale_ids})";
	}

	/**
	 * Include query condition for given taxonomies
	 *
	 * @param array $taxonomies Taxonomy IDs to filter results.
	 * @return string
	 */
	private function filter_query( $taxonomies = array() ) {
		$query = '';

		foreach ( $taxonomies as $filter_name => $taxonomy_ids ) {
			if ( ! is_array( $taxonomy_ids ) ) {
				continue;
			}

			$not    = Chocante_Product_Filters::PARAM_VISIBILITY === $filter_name ? 'NOT ' : '';
			$filter = implode( ',', $taxonomy_ids );
			$query .= "
				AND {$not}EXISTS (
					SELECT 1
					FROM {$this->wpdb->prefix}term_relationships
					WHERE object_id = p.ID
					AND term_taxonomy_id IN ({$filter})
			)";
		}

		return $query;
	}

	/**
	 * Get all filters from the database
	 *
	 * @param array    $filters Array of selected taxonomies and taxonomy IDs.
	 * @param int|null $tax_id Queried taxonomy ID.
	 *
	 * @return array
	 */
	private function query_all_filters( $filters = array(), $tax_id = null ) {
		$filter_keys = $this->get_filter_cache_key( $filters, $tax_id );

		$results = wp_cache_get( "chocante_filters{$filter_keys}", self::CACHE_GROUP, false, $found );

		if ( false === $found ) {
			$visibility = array(
				Chocante_Product_Filters::PARAM_VISIBILITY => $filters[ Chocante_Product_Filters::PARAM_VISIBILITY ],
			);

			$query = "SELECT
					t.term_id,
					t.slug,
					t.name,
					tx.taxonomy
				FROM
					{$this->filters_main_query($tax_id)}
					JOIN {$this->wpdb->prefix}terms t ON tx.term_id = t.term_id
					LEFT JOIN {$this->wpdb->prefix}termmeta tm ON tm.term_id = t.term_id
						AND tm.meta_key = 'order'
				WHERE 1=1
					{$this->filter_query($visibility)}
					{$this->where_product_query()}
				GROUP BY
					t.term_id,
					t.slug,
					t.name,
					tx.taxonomy,
					tm.meta_value
				ORDER BY
					tx.taxonomy,
					ABS(COALESCE(tm.meta_value, 0)),
					t.slug";

			$results = $this->wpdb->get_results( $query ); // @codingStandardsIgnoreLine.

			wp_cache_set( "chocante_filters{$filter_keys}", $results, self::CACHE_GROUP );
		}

		return $results;
	}

	/**
	 * Get active filters
	 *
	 * @param array    $filters Array of selected taxonomies and taxonomy IDs.
	 * @param bool     $on_sale Include on sale filter.
	 * @param int|null $tax_id Queried taxonomy ID.
	 *
	 * @return array
	 */
	private function query_active_filters( $filters = array(), $on_sale = false, $tax_id = null ) {
		$filter_keys = $this->get_filter_cache_key( $filters, $tax_id );

		$results = wp_cache_get( "chocante_active_filters{$filter_keys}", self::CACHE_GROUP, false, $found );

		if ( false === $found ) {
			$query = "SELECT
								tx.term_id,
								COUNT(DISTINCT p.ID) AS count
							FROM
								{$this->filters_main_query($tax_id)}
							WHERE 1=1
								{$this->on_sale_query($on_sale)}
								{$this->filter_query($filters)}
								{$this->where_product_query()}
							GROUP BY
								tx.term_id";

			$results = $this->wpdb->get_results( $query ); // @codingStandardsIgnoreLine.

			wp_cache_set( "chocante_active_filters{$filter_keys}", $results, self::CACHE_GROUP );
		}

		return $results;
	}

	/**
	 * Get on sale count when filtered
	 *
	 * @param array    $filters Array of selected taxonomies and taxonomy IDs.
	 * @param int|null $tax_id Queried taxonomy ID.
	 *
	 * @return string
	 */
	private function query_sale_count( $filters = array(), $tax_id = null ) {
		$filter_keys = $this->get_filter_cache_key( $filters, $tax_id );

		$results = wp_cache_get( "chocante_sale_count{$filter_keys}", self::CACHE_GROUP, false, $found );

		if ( false === $found ) {
			$query = "SELECT
								COUNT(DISTINCT p.ID) AS count
							FROM
								{$this->filters_main_query($tax_id )}
							WHERE 1=1
								{$this->on_sale_query(true)}
								{$this->filter_query($filters)}
								{$this->where_product_query()}";

			$results = $this->wpdb->get_col( $query ); // @codingStandardsIgnoreLine.

			wp_cache_set( "chocante_sale_count{$filter_keys}", $results, self::CACHE_GROUP );
		}

		return ! empty( $results ) ? $results[0] : 0;
	}

	/**
	 * Determine if sale filter is available
	 *
	 * @param array    $filters Array of selected taxonomies and taxonomy IDs.
	 * @param int|null $tax_id Queried taxonomy ID.
	 *
	 * @return bool
	 */
	private function query_has_sale( $filters = array(), $tax_id = null ) {
		$filter_keys = $this->get_filter_cache_key( $filters, $tax_id );

		$results = wp_cache_get( "chocante_has_sales{$filter_keys}", self::CACHE_GROUP, false, $found );

		if ( false === $found ) {
			foreach ( $filters as $key => $filter ) {
				if ( Chocante_Product_Filters::PARAM_VISIBILITY !== $key ) {
					unset( $filters[ $key ] );
				}
			}

			$query = "SELECT CASE
									WHEN EXISTS (
										SELECT 1
										FROM
											{$this->filters_main_query($tax_id )}
										WHERE 1=1
											{$this->on_sale_query(true)}
											{$this->filter_query($filters)}
											{$this->where_product_query()}
									)
									THEN TRUE
									ELSE FALSE
								END";

			$results = $this->wpdb->get_col( $query ); // @codingStandardsIgnoreLine.

			wp_cache_set( "chocante_has_sales{$filter_keys}", $results, self::CACHE_GROUP );
		}

		return ! empty( $results ) ? $results[0] : 0;
	}

	/**
	 * Retrieve and merge filter data
	 *
	 * @param array        $filters Array of taxonomy IDs.
	 * @param WP_Term|null $current_tax Queried taxonomy ID.
	 * @param array        $excluded_terms Array of term IDs excluded by admin.
	 * @return array
	 */
	public function get_filters( $filters = array(), $current_tax = null, $excluded_terms = array() ) {
		// $filter_on_sale = isset( $filters[ Chocante_Product_Filters::PARAM_SALE ] );
		$tax_id    = isset( $current_tax ) ? $current_tax->term_id : null;
		$results   = array();
		$has_sales = $this->query_has_sale( $filters, $tax_id );

		if ( $has_sales ) {
			$results[ Chocante_Product_Filters::PARAM_SALE ] = array(
				'label' => __( 'On Sale', 'woocommerce' ),
				'count' => $this->query_sale_count( $filters, $tax_id ),
			);
		}

		foreach ( $this->filters as  $filter ) {
			$taxonomy = get_taxonomy( $filter );

			if ( ! $taxonomy ) {
				continue;
			}

			$results[ $filter ] = array(
				'label' => $taxonomy->labels->singular_name,
				'items' => array(),
			);
		}

		$all_filters = $this->query_all_filters( $filters, $tax_id );

		// $active_filters = wp_list_pluck( $this->query_active_filters( $filters, $filter_on_sale, $tax_id ), 'count', 'term_id' );

		// Children of currently displayed taxonomy.
		$current_taxonomy_children = array();

		if ( isset( $current_tax ) ) {
			$current_taxonomy_children = get_term_children( $current_tax->term_id, $current_tax->taxonomy );

			if ( is_wp_error( $current_taxonomy_children ) ) {
				$current_taxonomy_children = array();
			}
		}

		foreach ( $all_filters as $filter ) {
			if ( isset( $results[ $filter->taxonomy ] ) ) {
				// For currently displayed taxonomy skip filters that are not children of this taxonomy.
				if ( isset( $current_tax ) ) {
					if ( $filter->taxonomy === $current_tax->taxonomy && ! in_array( $filter->term_id, $current_taxonomy_children, true ) ) {
						continue;
					}
				}

				// Skip filters that are excluded by admin.
				if ( in_array( (int) $filter->term_id, $excluded_terms, true ) ) {
					continue;
				}

				// $filter->count = isset( $active_filters[ $filter->term_id ] ) ? $active_filters[ $filter->term_id ] : 0;
				array_push( $results[ $filter->taxonomy ]['items'], $filter );
			}
		}

		return $results;
	}

	/**
	 * Prepare cache key based on filters
	 *
	 * @param array    $filters List of filter names and taxonomy term IDs.
	 * @param int|null $tax_id ID of currently displayed taxonomy.
	 * @return string
	 */
	private function get_filter_cache_key( $filters, $tax_id ) {
		$tax_key          = isset( $tax_id ) ? "_{$tax_id}" : '';
		$filter_keys      = '';
		$current_language = isset( $this->current_language ) ? "_{$this->current_language}" : '';

		foreach ( $filters as $filter_name => $filter ) {
			if ( is_bool( $filter ) && $filter ) {
				$filter_keys .= "_{$filter_name}";
			}

			if ( is_array( $filter ) ) {
				$filter_terms = implode( '_', $filter );
				$filter_keys .= "_{$filter_name}_{$filter_terms}";
			}
		}

		return $tax_key . $filter_keys . $current_language;
	}

	/**
	 * Clear wp cache on product save
	 */
	public function clear_cache() {
		if ( ! wp_cache_supports( self::CACHE_GROUP ) ) {
			wp_cache_flush_group( self::CACHE_GROUP );
		} else {
			wp_cache_flush();
		}
	}

	/**
	 * Clear wp cache on product delete
	 *
	 * @param int $post_id Post ID.
	 */
	public function clear_cache_on_trash( $post_id ) {
		if ( 'product' === get_post_type( $post_id ) ) {
			$this->clear_cache();
		}
	}
}
