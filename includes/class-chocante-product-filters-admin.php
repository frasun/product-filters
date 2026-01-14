<?php
/**
 * Fired during plugin activation
 *
 * @package Chocante_Product_Filters
 */

defined( 'ABSPATH' ) || exit;

/**
 * The Chocante_Product_Filters_Admin class.
 */
class Chocante_Product_Filters_Admin {
	/**
	 * List of excluded product attribute taxonomies.
	 *
	 * @var string[]
	 */
	private static $excluded_filters;

	/**
	 * List of excluded product attribute terms.
	 *
	 * @var int[]
	 */
	private static $excluded_terms;

	/**
	 * Init hooks.
	 *
	 * @param string[] $attributes Names of active product attribues.
	 * @param string[] $excluded_attributes Names of excluded product attribues.
	 * @param string[] $excluded_terms IDs of excluded product attribue terms.
	 */
	public static function init( $attributes = array(), $excluded_attributes = array(), $excluded_terms = array() ) {
		self::$excluded_filters = $excluded_attributes;
		self::$excluded_terms   = $excluded_terms;

		add_action( 'woocommerce_after_add_attribute_fields', array( __CLASS__, 'add_exclude_taxonomy_field' ) );
		add_action( 'woocommerce_after_edit_attribute_fields', array( __CLASS__, 'edit_exclude_taxonomy_field' ) );
		add_action( 'woocommerce_attribute_added', array( __CLASS__, 'save_exclude_taxonomy_setting' ), 10, 2 );
		add_action( 'woocommerce_attribute_updated', array( __CLASS__, 'save_exclude_taxonomy_setting' ), 10, 2 );

		foreach ( $attributes as $taxonomy ) {
			add_action( "{$taxonomy}_add_form_fields", array( __CLASS__, 'add_exclude_term_field' ) );
			add_action( "{$taxonomy}_edit_form_fields", array( __CLASS__, 'edit_exclude_term_field' ) );
			add_action( 'created_term', array( __CLASS__, 'save_exclude_term_setting' ), 10, 3 );
			add_action( 'edit_term', array( __CLASS__, 'save_exclude_term_setting' ), 10, 3 );
		}
	}

	/**
	 * Display exclude taxonomy field in create product taxonomy form.
	 */
	public static function add_exclude_taxonomy_field() {
		?>
		<div class="form-field">
			<label for="chocante_exclude_from_filters">
				<input name="chocante_exclude_from_filters" id="chocante_exclude_from_filters" type="checkbox" value="0">
				<?php esc_html_e( 'Exclude from product filters', 'chocante-product-filters' ); ?>
			</label>
			<p class="description"><?php esc_html_e( 'Select if you want to exclude this attribute from product filters', 'chocante-product-filters' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Display exclude taxonomy field in edit product taxonomy form.
	 */
	public static function edit_exclude_taxonomy_field() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$attribute_id = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : false;
		$attribute    = wc_get_attribute( $attribute_id );
		$is_excluded  = in_array( $attribute->slug, self::$excluded_filters, true );
		?>
		<tr class="form-field form-required">
			<th scope="row" valign="top">
				<label for="chocante_exclude_from_filters"><?php esc_html_e( 'Exclude from product filters', 'chocante-product-filters' ); ?></label>
			</th>
			<td>
				<input name="chocante_exclude_from_filters" id="chocante_exclude_from_filters" type="checkbox" value="1" <?php checked( $is_excluded, true ); ?>>
			</td>
		</tr>
		<?php
	}

	/**
	 * Save excluded fitlers when product attribute is added or updated.
	 *
	 * @param int   $id   Added attribute ID.
	 * @param array $data Attribute data.
	 */
	public static function save_exclude_taxonomy_setting( $id, $data ) {
		if ( ! isset( $_POST['_wpnonce'] ) ) {
			return;
		}

		$nonce     = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) );
		$is_create = wp_verify_nonce( $nonce, 'woocommerce-add-new_attribute' );
		$is_update = wp_verify_nonce( $nonce, 'woocommerce-save-attribute_' . $id );

		if ( ! $is_create && ! $is_update ) {
			return;
		}

		$slug        = wc_attribute_taxonomy_name( $data['attribute_name'] );
		$is_excluded = isset( $_POST['chocante_exclude_from_filters'] ) && '1' === $_POST['chocante_exclude_from_filters'];
		$in_excluded = in_array( $slug, self::$excluded_filters, true );

		if ( $is_excluded && ! $in_excluded ) {
			self::$excluded_filters = array( ...self::$excluded_filters, $slug );
		}

		if ( ! $is_excluded && $in_excluded ) {
			self::$excluded_filters = array_values( array_diff( self::$excluded_filters, array( $slug ) ) );
		}

		update_option( Chocante_Product_Filters::EXCLUDED_FILTERS, self::$excluded_filters, false );
	}

	/**
	 * Display exclude term field in create product attribute term form.
	 */
	public static function add_exclude_term_field() {
		?>
		<div class="form-field">
			<label for="chocante_exclude_from_filters">
				<input name="chocante_exclude_from_filters" id="chocante_exclude_from_filters" type="checkbox" value="0">
				<?php esc_html_e( 'Exclude from product filters', 'chocante-product-filters' ); ?>
			</label>
			<p class="description"><?php esc_html_e( 'Select if you want to exclude this attribute from product filters', 'chocante-product-filters' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Display exclude taxonomy field in edit product taxonomy form.
	 */
	public static function edit_exclude_term_field() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$attribute_id = isset( $_GET['tag_ID'] ) ? absint( $_GET['tag_ID'] ) : false;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$taxonomy    = isset( $_GET['taxonomy'] ) ? sanitize_text_field( wp_unslash( $_GET['taxonomy'] ) ) : false;
		$term        = get_term( $attribute_id, $taxonomy );
		$is_excluded = in_array( $term->term_id, self::$excluded_terms, true );

		?>
		<tr class="form-field form-required">
			<th scope="row" valign="top">
				<label for="chocante_exclude_from_filters"><?php esc_html_e( 'Exclude from product filters', 'chocante-product-filters' ); ?></label>
			</th>
			<td>
				<input name="chocante_exclude_from_filters" id="chocante_exclude_from_filters" type="checkbox" value="1" <?php checked( $is_excluded, true ); ?>>
			</td>
		</tr>
		<?php
	}

	/**
	 * Save excluded terms when product term is added or updated.
	 *
	 * @param int    $term_id Term ID.
	 * @param int    $tt_id Term taxonomy ID.
	 * @param string $taxonomy Taxonomy slug.
	 */
	public static function save_exclude_term_setting( $term_id, $tt_id, $taxonomy ) {
		if ( ! isset( $_POST['_wpnonce'] ) ) {
			return;
		}

		$nonce     = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) );
		$is_create = wp_verify_nonce( $nonce, 'add-tag' );
		$is_update = wp_verify_nonce( $nonce, 'update-tag_' . $term_id );

		if ( ! $is_create && ! $is_update ) {
			return;
		}

		$is_excluded = isset( $_POST['chocante_exclude_from_filters'] ) && '1' === $_POST['chocante_exclude_from_filters'];
		$term_ids    = array( $term_id );

		// Handle WPML translations.
		if ( class_exists( 'SitePress' ) ) {
			$default_language = apply_filters( 'wpml_default_language', null );
			$current_language = apply_filters( 'wpml_current_language', null );

			if ( $current_language === $default_language ) {
				$trid = apply_filters( 'wpml_element_trid', null, $term_id, 'tax_' . $taxonomy );

				if ( $trid ) {
					$translations = apply_filters( 'wpml_get_element_translations', null, $trid, 'tax_' . $taxonomy );

					if ( is_array( $translations ) ) {
						foreach ( $translations as $translation ) {
							if ( isset( $translation->element_id ) ) {
								$term_ids[] = (int) $translation->element_id;
							}
						}
					}
				}
			}
		}

		if ( $is_excluded ) {
			self::$excluded_terms = array_unique( array_merge( self::$excluded_terms, $term_ids ) );
		} else {
			self::$excluded_terms = array_values( array_diff( self::$excluded_terms, $term_ids ) );
		}

		update_option( Chocante_Product_Filters::EXCLUDED_TERMS, self::$excluded_terms, false );
	}
}
