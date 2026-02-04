<?php
/**
 * Fired during plugin activation
 *
 * @package Chocante_Product_Filters
 */

defined( 'ABSPATH' ) || exit;

/**
 * The Chocante_Product_Filters class.
 */
class Chocante_Product_Filters {
	/**
	 * This class instance.
	 *
	 * @var \Chocante_Product_Filters Single instance of this class.
	 */
	private static $instance;

	/**
	 * The current version of the plugin.
	 *
	 * @var string The current version of the plugin.
	 */
	protected $version;

	/**
	 * Filters DB queries class.
	 *
	 * @var Chocante_Filter_Queries
	 */
	private $data;

	/**
	 * Product query params used in filters.
	 *
	 * @var array WC Product Query params.
	 */
	private $query_params;

	/**
	 * Queried taxonomy e.g. product category
	 *
	 * @var WP_Term Queried object Taxonomy object.
	 */
	private $current_taxonomy;

	/**
	 * Array of available filters for a given view.
	 *
	 * @var array Array of filters.
	 */
	private $filters = array();

	const PARAM_SALE       = 'on_sale';
	const PARAM_ORDERBY    = 'orderby';
	const DEFAULT_ORDERBY  = 'menu_order';
	const PARAM_CATEGORY   = 'product_cat';
	const PARAM_TAG        = 'product_tag';
	const PARAM_VISIBILITY = 'product_visibility';
	const SEARCH_PARAM     = 's';
	const EXCLUDED_FILTERS = 'chocante_product_filters_excluded_taxonomies';
	const EXCLUDED_TERMS   = 'chocante_product_filters_excluded_terms';

	/**
	 * Constructor
	 */
	public function __construct() {
		global $wpdb;

		if ( defined( 'CHOCANTE_PRODUCT_FILTERS_VERSION' ) ) {
			$this->version = CHOCANTE_PRODUCT_FILTERS_VERSION;
		} else {
			$this->version = '1.0.0';
		}

		$wc_taxonomies      = wc_get_attribute_taxonomies();
		$excluded_filters   = get_option( self::EXCLUDED_FILTERS, array() );
		$excluded_terms     = get_option( self::EXCLUDED_TERMS, array() );
		$user_filters       = array();
		$product_attributes = array( self::PARAM_CATEGORY, self::PARAM_TAG );

		foreach ( $wc_taxonomies as $tax ) {
			$attribute            = wc_attribute_taxonomy_name( $tax->attribute_name );
			$product_attributes[] = $attribute;

			if ( ! in_array( $attribute, $excluded_filters, true ) ) {
				$user_filters[] = $attribute;
			}
		}

		require_once plugin_dir_path( __FILE__ ) . 'class-chocante-filter-queries.php';
		$this->data = new Chocante_Filter_Queries( $wpdb, array( self::PARAM_CATEGORY, ...$user_filters, self::PARAM_TAG ) );

		require_once plugin_dir_path( __FILE__ ) . 'class-chocante-product-filters-admin.php';
		Chocante_Product_Filters_Admin::init( $product_attributes, $excluded_filters, $excluded_terms );

		$this->init();
	}

	/**
	 * Cloning is forbidden
	 */
	public function __clone() {
		wc_doing_it_wrong( __FUNCTION__, __( 'Cloning is forbidden.', 'chocante-product-filters' ), $this->version );
	}

	/**
	 * Unserializing instances of this class is forbidden
	 */
	public function __wakeup() {
		wc_doing_it_wrong( __FUNCTION__, __( 'Unserializing instances of this class is forbidden.', 'chocante-product-filters' ), $this->version );
	}

	/**
	 * Gets the main instance.
	 *
	 * Ensures only one instance can be loaded.
	 *
	 * @return \Chocante_Product_Filters
	 */
	public static function instance() {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register hooks
	 */
	public function init() {
		if ( ! is_admin() ) {
			add_filter( 'loop_shop_post_in', array( $this, 'on_sale_query' ) );
			add_filter( 'woocommerce_layered_nav_default_query_type', array( $this, 'default_query_type' ) );
			add_filter( 'woocommerce_product_query_tax_query', array( $this, 'include_category_in_product_query' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'load_scripts' ) );
			add_action( 'woocommerce_before_shop_loop', array( $this, 'display_filters' ) );
			add_action( 'woocommerce_product_query', array( $this, 'set_query_params' ) );
			add_action( 'init', array( $this, 'add_shortcode' ) );
		}
	}

	/**
	 * Handle url param for on sale products
	 *
	 * @param array $posts Post IDs.
	 * @return array
	 */
	public function on_sale_query( $posts ) {
		if ( isset( $_GET[ 'filter_' . self::PARAM_SALE ] ) ) { // @codingStandardsIgnoreLine.
			return wc_get_product_ids_on_sale();
		}

		return $posts;
	}

	/**
	 * Use 'or' as default product attribute query type
	 */
	public function default_query_type() {
		return 'or';
	}

	/**
	 * Include product category filter in query
	 *
	 * @param array $tax_query Product tax query.
	 * @return array
	 */
	public function include_category_in_product_query( $tax_query ) {
		if ( ! is_main_query() ) {
			return $tax_query;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$category_filter = isset( $_GET['filter_product_cat'] ) ? explode( ',', sanitize_text_field( wp_unslash( $_GET['filter_product_cat'] ) ) ) : null;

		if ( isset( $category_filter ) ) {
			$tax_query[] = array(
				'taxonomy' => self::PARAM_CATEGORY,
				'field'    => 'slug',
				'terms'    => $category_filter,
			);
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tag_filter = isset( $_GET['filter_product_tag'] ) ? explode( ',', sanitize_text_field( wp_unslash( $_GET['filter_product_tag'] ) ) ) : null;

		if ( isset( $tag_filter ) ) {
			$tax_query[] = array(
				'taxonomy' => 'product_tag',
				'field'    => 'slug',
				'terms'    => $tag_filter,
			);
		}

		return $tax_query;
	}

	/**
	 * Extract params from query
	 *
	 * @param WP_Query $q Product query.
	 */
	public function set_query_params( $q ) {
		// Orderby.
		$orderby = isset( $q->query_vars[ self::PARAM_ORDERBY ] ) ? $q->query_vars[ self::PARAM_ORDERBY ] : null;

		if ( isset( $orderby ) && ! str_contains( $orderby, self::DEFAULT_ORDERBY ) ) {
			$this->query_params[ self::PARAM_ORDERBY ] = $orderby;
		}

		// On Sale.
		if ( ! empty( $q->query_vars['post__in'] ) ) {
			$this->query_params[ self::PARAM_SALE ] = true;
		}

		// Taxonomies.
		if ( isset( $q->query_vars['tax_query'] ) ) {
			foreach ( $q->query_vars['tax_query'] as $taxonomy ) {
				if ( ! is_array( $taxonomy ) ) {
					continue;
				}

				$this->query_params[ $taxonomy['taxonomy'] ] = array();

				if ( 'slug' === $taxonomy['field'] ) {
					foreach ( $taxonomy['terms'] as $taxonomy_term ) {
						$term = get_term_by( $taxonomy['field'], $taxonomy_term, $taxonomy['taxonomy'] );

						if ( is_wp_error( $term ) || null === $term ) {
							continue;
						}

						array_push( $this->query_params[ $taxonomy['taxonomy'] ], $term->term_id );
					}
				} else {
					array_push( $this->query_params[ $taxonomy['taxonomy'] ], ...$taxonomy['terms'] );
				}
			}
		}

		// Currently displayed taxonomy.
		if ( isset( $q->queried_object ) ) {
			$this->current_taxonomy = $q->queried_object;
		}
	}

	/**
	 * Display available filtering options
	 */
	public function display_filters() {
		if ( is_search() || ( ! woocommerce_products_will_display() && ! $this->has_filters() ) ) {
			return;
		}

		$query_params   = $this->query_params;
		$excluded_terms = get_option( self::EXCLUDED_TERMS, array() );
		$filters        = $this->data->get_filters( $this->query_params, $this->current_taxonomy, $excluded_terms );

		if ( ! empty( $filters ) ) {
			$this->filters = $filters;
			include plugin_dir_path( __FILE__ ) . 'product-filters.php';
		}
	}

	/**
	 * Load filters form JS
	 */
	public function load_scripts() {
		if ( ! ( is_shop() || is_product_taxonomy() ) ) {
			return;
		}

		$script_asset = include plugin_dir_path( __DIR__ ) . 'build/js/chocante-product-filters.asset.php';

		wp_enqueue_script(
			'chocante-product-filters',
			plugin_dir_url( __DIR__ ) . 'build/js/chocante-product-filters.js',
			$script_asset['dependencies'],
			$script_asset['version'],
			array(
				'strategy'  => 'defer',
				'in_footer' => 'true',
			)
		);
	}

	/**
	 * [chocante_product_filters] shortcode.
	 */
	public function add_shortcode() {
		add_shortcode( 'chocante_product_filters', array( $this, 'display_filters' ) );
	}

	/**
	 * Whether any filter is set
	 */
	public function has_filters() {
		return isset( $this->query_params ) && count( $this->query_params ) > 1;
	}

	/**
	 * Whether any filter is set
	 */
	public function has_available_filters() {
		return ! empty( $this->filters );
	}

	/**
	 * Number of active filters
	 */
	public function get_active_filters() {
		$query_params = $this->query_params;

		return array_reduce(
			array_keys( $query_params ),
			function ( $carry, $key ) use ( $query_params ) {
				if ( self::PARAM_VISIBILITY === $key ) {
					return $carry;
				}

				if ( self::PARAM_SALE === $key ) {
					return $carry + 1;
				}

				if ( is_array( $query_params[ $key ] ) ) {
					return $carry + count( $query_params[ $key ] );
				}

				return $carry;
			},
			0
		);
	}
}
