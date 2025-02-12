<?php
/**
 * Product Filters - Item
 *
 * @package Chocante_Product_Filters
 */

defined( 'ABSPATH' ) || exit;
?>
<div>
	<input type="checkbox" name="<?php echo esc_attr( str_replace( 'pa_', '', "filter_{$name}" ) ); ?>" value="<?php echo esc_attr( $value ); ?>" id="<?php echo esc_attr( $name ) . '-' . esc_attr( $value ) . '-' . esc_attr( $form_id ); ?>"<?php echo $checked ? ' checked' : ''; ?><?php echo isset( $disabled ) && $disabled ? ' disabled="disabled"' : ''; ?> />
	<label for="<?php echo esc_attr( $name ) . '-' . esc_attr( $value ) . '-' . esc_attr( $form_id ); ?>"><?php echo esc_html( $label ) . ( isset( $count ) && $count > 0 ? '<span>' . esc_html( $count ) . '</span>' : '' ); ?></label>
</div>