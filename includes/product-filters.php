<?php
/**
 * Product Filters
 *
 * @package Chocante_Product_Filters
 */

defined( 'ABSPATH' ) || exit;
$form_id = time();
?>

<form class="chocante-product-filters" method="get" action="<?php echo esc_attr( strtok( get_pagenum_link(), '?' ) ); ?>">
	<header>
		<?php esc_html_e( 'Filter products', 'chocante-product-filters' ); ?>
		<?php do_action( 'chocante_product_filters_header' ); ?>
	</header>
	<?php if ( isset( $query_params['orderby'] ) ) : ?>
		<input type="hidden" name="orderby-<?php echo esc_html( $form_id ); ?>" value="<?php echo esc_attr( $query_params['orderby'] ); ?>" />
	<?php endif; ?>
	<?php foreach ( $filters as $filter_name => $filter ) : ?>
			<?php if ( isset( $filter['items'] ) ) : ?>
				<?php if ( ! empty( $filter['items'] ) ) : ?>
				<fieldset>
					<legend><?php echo esc_html( $filter['label'] ); ?></legend>
					<?php
					foreach ( $filter['items'] as $item ) {
						$name    = $filter_name;
						$value   = urldecode( $item->slug );
						$label   = urldecode( $item->name );
						$checked = isset( $query_params[ $filter_name ] ) && in_array( (int) $item->term_id, $query_params[ $filter_name ], true );
						// $count    = $item->count;
						$count    = null;
						$disabled = null;

						include plugin_dir_path( __FILE__ ) . 'product-filters-item.php';
					}
					?>
				</fieldset>
				<?php endif; ?>
			<?php else : ?>
				<fieldset>
				<?php
					$name     = $filter_name;
					$value    = true;
					$label    = $filter['label'];
					$count    = $filter['count'];
					$checked  = isset( $query_params[ $filter_name ] );
					$disabled = 0 === (int) $filter['count'];

					include plugin_dir_path( __FILE__ ) . 'product-filters-item.php';
				?>
				</fieldset>
			<?php endif; ?>
	<?php endforeach; ?>
	<footer>
		<button type="submit" class="button">
			<?php esc_html_e( 'Filter', 'chocante-product-filters' ); ?>
		</button>
		<?php if ( isset( $query_params ) && count( $query_params ) > 1 ) : ?>
			<button type="reset" class="button">
				<?php esc_html_e( 'Reset filters', 'chocante-product-filters' ); ?>
			</button>
		<?php endif; ?>
	</footer>
</form>
